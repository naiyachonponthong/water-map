/* แอปภาคสนามของทีมกู้ภัย
   - สถานะล่าสุดเก็บในเครื่อง เปิดตอนไม่มีเน็ตก็ยังเห็นงาน
   - ทุกการกดเข้าคิวในเครื่องก่อน (มี key ไม่ซ้ำ) แล้วค่อยส่ง เซิร์ฟเวอร์ทำงานครั้งเดียวต่อ key
   - หน้าจออัปเดตทันทีแบบคาดการณ์ แล้วยืนยันกับเซิร์ฟเวอร์ภายหลัง */
(function () {
  'use strict';
  const C = window.FIELD;
  if (!C) return;

  const $ = s => document.querySelector(s);
  const esc = v => String(v ?? '').replace(/[&<>"']/g, c => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]));
  const tel = v => 'tel:' + String(v || '').replace(/\D+/g, '');
  const km = m => m == null ? '' : (m < 1000 ? `${m} ม.` : `${(m / 1000).toFixed(1)} กม.`);
  const STATE_KEY = `field-state-${C.teamId}`, QUEUE_KEY = `field-queue-${C.teamId}`;
  const token = () => document.querySelector('meta[name=csrf-token]').content;
  const uuid = () => (crypto.randomUUID ? crypto.randomUUID() : 'xxxxxxxx-xxxx-4xxx-yxxx-xxxxxxxxxxxx'.replace(/[xy]/g, c => { const r = Math.random() * 16 | 0; return (c === 'x' ? r : (r & 3 | 8)).toString(16); }));

  let state = load(STATE_KEY) || { jobs: [], nearby: [], team: { status: 'available' }, today: {} };
  let queue = load(QUEUE_KEY) || [];
  let tab = 'jobs', online = navigator.onLine, flushing = false, myPos = state.team?.position || null;

  function load(k) { try { return JSON.parse(localStorage.getItem(k) || 'null'); } catch (_) { return null; } }
  function save(k, v) { try { localStorage.setItem(k, JSON.stringify(v)); } catch (_) {} }

  /* ================= เครือข่าย ================= */

  async function api(url, opts = {}) {
    const res = await fetch(url, {
      credentials: 'same-origin',
      ...opts,
      headers: { Accept: 'application/json', 'X-CSRF-TOKEN': token(), ...(opts.body && !(opts.body instanceof FormData) ? { 'Content-Type': 'application/json' } : {}), ...(opts.headers || {}) },
    });
    if (res.status === 401 || res.status === 419) { location.reload(); throw new Error('session'); }
    if (!res.ok) throw Object.assign(new Error('http ' + res.status), { status: res.status });
    return res.json();
  }

  async function refresh() {
    if (!navigator.onLine) return setNet();
    try {
      const s = await api(C.stateUrl);
      state = s; myPos = myPos || s.team.position;
      // งานที่ยังค้างในคิว: ทาสถานะคาดการณ์ทับข้อมูลจากเซิร์ฟเวอร์
      queue.forEach(q => predict(q));
      save(STATE_KEY, state);
      online = true;
    } catch (e) { if (e.message !== 'session') online = false; }
    setNet(); render();
  }

  /** กดปุ่มใดๆ = เข้าคิวก่อนเสมอ */
  function act(type, payload, toastMsg) {
    const item = { key: uuid(), type, payload, client_at: new Date().toISOString() };
    queue.push(item); save(QUEUE_KEY, queue);
    predict(item); save(STATE_KEY, state);
    render();
    if (toastMsg) toast(toastMsg + (navigator.onLine ? '' : ' (จะส่งเมื่อมีสัญญาณ)'), 'success');
    flush();
  }

  async function flush() {
    if (flushing || !queue.length || !navigator.onLine) return setNet();
    flushing = true; setNet();
    while (queue.length) {
      const item = queue[0];
      try {
        const r = await api(C.actionUrl, { method: 'POST', body: JSON.stringify(item) });
        queue.shift(); save(QUEUE_KEY, queue);
        if (!r.ok) toast('ทำไม่ได้: ' + r.message, 'error');
      } catch (e) {
        if (e.status && e.status >= 400 && e.status < 500 && e.status !== 429) { queue.shift(); save(QUEUE_KEY, queue); toast('ส่งไม่ได้ ข้อมูลไม่ถูกต้อง', 'error'); continue; }
        break; // เน็ตหลุด รอรอบหน้า
      }
    }
    flushing = false;
    await refresh();
  }

  /** ปรับหน้าจอตามสิ่งที่กดไปแล้ว (ก่อนเซิร์ฟเวอร์ยืนยัน) */
  function predict(q) {
    const job = q.payload?.assignment_id && state.jobs.find(j => j.assignment_id === q.payload.assignment_id);
    const mark = (j, status, label) => { j.status = status; j.status_label = label; j.pending = true; };
    switch (q.type) {
      case 'respond': if (job) q.payload.accept ? mark(job, 'accepted', 'รับงานแล้ว') : (state.jobs = state.jobs.filter(j => j !== job)); break;
      case 'progress': if (job) mark(job, q.payload.step, q.payload.step === 'en_route' ? 'กำลังเดินทาง' : 'ถึงที่เกิดเหตุ'); break;
      case 'complete': if (job) state.jobs = state.jobs.filter(j => j !== job); break;
      case 'pick': {
        const c = state.nearby.find(n => n.id === q.payload.case_id);
        if (c) { state.nearby = state.nearby.filter(n => n !== c); state.jobs.push({ assignment_id: null, status: 'accepted', status_label: 'รับงานแล้ว', case: c, pending: true }); }
        break;
      }
      case 'team_status': { const s = C.options.team_status[q.payload.status]; if (s) Object.assign(state.team, { status: q.payload.status, status_label: s.label, color: s.color }); break; }
      case 'water': state.jobs.forEach(j => { if (j.case.id === q.payload.case_id) { const l = C.options.levels[q.payload.water_level]; j.case.water_level = q.payload.water_level; j.case.water = l.label; j.case.water_color = l.color; } }); break;
      case 'sos': state.sos = { status: 'open', status_label: 'กำลังส่งถึงศูนย์', kind: C.options.sos[q.payload.kind] }; break;
      case 'sos_safe': state.sos = null; break;
      case 'household_check': state.jobs.forEach(j => { if (j.case.id === q.payload.case_id && j.case.household) j.case.household.last_check = C.options.check[q.payload.status]; }); break;
    }
  }

  function setNet() {
    const el = $('#fNet');
    if (!el) return;
    const n = queue.length;
    el.className = 'f-net ms-auto' + (!navigator.onLine ? ' off' : (n ? ' sync' : ''));
    el.innerHTML = !navigator.onLine ? '<i class="bi bi-wifi-off"></i> ออฟไลน์' : (n ? `<i class="bi bi-arrow-repeat"></i> ส่ง ${n}` : '<i class="bi bi-wifi"></i> ออนไลน์');
    const qb = $('#fQueueBanner');
    qb.classList.toggle('d-none', !n);
    qb.textContent = n ? `มี ${n} รายการรอส่งให้ศูนย์ ${navigator.onLine ? 'กำลังส่ง...' : 'จะส่งเองเมื่อมีสัญญาณ'}` : '';
  }

  /* ================= พิกัด ================= */

  function locate(send = true) {
    if (!navigator.geolocation) return;
    navigator.geolocation.getCurrentPosition(p => {
      myPos = [p.coords.latitude, p.coords.longitude];
      if (send && navigator.onLine && ['available', 'busy'].includes(state.team.status)) {
        api(C.locationUrl, { method: 'POST', body: JSON.stringify({ lat: myPos[0], lng: myPos[1] }) }).catch(() => {});
      }
      if (tab === 'jobs') renderJobs();
    }, () => {}, { enableHighAccuracy: true, timeout: 15000, maximumAge: 30000 });
  }

  /** เรียงจุดแวะแบบใกล้สุดก่อน (เรือลำเดียวช่วยหลายบ้านในรอบเดียว) */
  function routeOrder(jobs) {
    const left = jobs.filter(j => ['accepted', 'en_route', 'on_site'].includes(j.status));
    if (!myPos || left.length < 2) return left;
    const d = (a, b) => Math.hypot(a[0] - b[0], (a[1] - b[1]) * Math.cos(a[0] * Math.PI / 180));
    const out = []; let cur = myPos;
    // จุดที่ถึงแล้ว/วิกฤตไปก่อน
    left.sort((a, b) => (b.status === 'on_site') - (a.status === 'on_site') || (b.case.priority === 'critical') - (a.case.priority === 'critical'));
    while (left.length) {
      let bi = 0;
      if (left[0].status !== 'on_site' && left[0].case.priority !== 'critical') {
        left.forEach((j, i) => { if (d(cur, [j.case.lat, j.case.lng]) < d(cur, [left[bi].case.lat, left[bi].case.lng])) bi = i; });
      }
      const [n] = left.splice(bi, 1); out.push(n); cur = [n.case.lat, n.case.lng];
    }
    return out;
  }

  function routeLink(stops) {
    if (!stops.length) return '';
    const p = s => `${s.case.lat},${s.case.lng}`;
    const dest = p(stops[stops.length - 1]);
    const way = stops.slice(0, -1).slice(0, 8).map(p).join('|');
    return `https://www.google.com/maps/dir/?api=1${myPos ? '&origin=' + myPos.join(',') : ''}&destination=${dest}${way ? '&waypoints=' + encodeURIComponent(way) : ''}&travelmode=driving`;
  }

  /* ================= หน้าจอ ================= */

  function render() {
    $('#fTeam').textContent = state.team.name || $('#fTeam').textContent;
    const st = $('#fStatus');
    st.style.setProperty('--c', state.team.color || '#16a34a');
    $('#fStatusText').textContent = state.team.status_label || '';

    const offers = state.jobs.filter(j => j.status === 'offered').length;
    const badge = $('#badgeJobs');
    badge.textContent = offers || state.jobs.length;
    badge.classList.toggle('d-none', !(offers || state.jobs.length));

    const sb = $('#fSosBanner');
    sb.classList.toggle('d-none', !state.sos);
    if (state.sos) { sb.className = 'f-banner' + (state.sos.status === 'ack' ? ' ack' : ''); sb.textContent = `SOS: ${state.sos.kind} · ${state.sos.status_label}`; }
    if ($('#sosCall') && state.hotline) $('#sosCall').href = tel(state.hotline);

    if (tab === 'jobs') renderJobs();
    if (tab === 'near') renderNear();
    if (tab === 'team') renderTeam();
    if (tab === 'sos') renderSos();
    if (tab === 'map') renderMap();
    setNet();
  }

  function caseBlock(c, withContact) {
    return `
      <div class="d-flex align-items-start gap-2">
        <div class="min-w-0 flex-grow-1">
          <div class="small text-muted mono">${esc(c.code)} · ${esc(c.area)}</div>
          <div class="f-name">${esc(c.name)}</div>
        </div>
        <span class="score-pill" style="background:${c.color}">${esc(c.priority_label)}</span>
      </div>
      <div class="f-meta mt-1">
        <span><span class="lv-dot" style="background:${c.water_color}"></span> ${esc(c.water)}</span>
        <span><i class="bi bi-people"></i> ${c.people} คน${c.floor ? ' · ชั้น ' + c.floor : ''}</span>
        ${c.distance_m != null ? `<span><i class="bi bi-signpost"></i> ${km(c.distance_m)}</span>` : ''}
        <span><i class="bi bi-clock"></i> รอ ${esc(c.waiting)}</span>
      </div>
      ${c.vulnerable.length || c.needs.length ? `<div class="f-tags">${c.vulnerable.map(v => `<span class="chip chip-danger">${esc(v)}</span>`).join('')}${c.needs.map(v => `<span class="chip chip-primary">${esc(v)}</span>`).join('')}</div>` : ''}
      ${c.address || c.landmark ? `<div class="small"><i class="bi bi-geo-alt"></i> ${esc(c.address || '')}${c.landmark ? ' · ' + esc(c.landmark) : ''}</div>` : ''}
      ${c.note ? `<div class="f-note">${esc(c.note)}</div>` : ''}
      ${c.household ? householdBlock(c.household) : (c.proactive ? '<div class="small text-warning"><i class="bi bi-house-heart"></i> เคสตรวจเยี่ยมเชิงรุก</div>' : '')}
      ${withContact ? `<div class="f-actions">
          <a class="btn btn-soft" href="https://www.google.com/maps/dir/?api=1&destination=${c.lat},${c.lng}" target="_blank" rel="noopener"><i class="bi bi-sign-turn-right"></i> นำทาง</a>
          ${c.contact_phone || c.phone ? `<a class="btn btn-light" href="${tel(c.contact_phone || c.phone)}"><i class="bi bi-telephone"></i> โทร${c.contact_phone ? (c.contact_name ? ' ' + esc(c.contact_name) : 'คนในพื้นที่') : 'ผู้แจ้ง'}</a>` : '<span></span>'}
        </div>` : ''}`;
  }

  function householdBlock(h) {
    return `<div class="f-hh">
      <div class="fw-600"><i class="bi bi-house-heart-fill"></i> ครัวเรือนเปราะบาง: ${esc(h.name)} · ${h.members} คน</div>
      ${h.conditions.length ? `<div class="f-tags">${h.conditions.map(v => `<span class="chip chip-danger">${esc(v)}</span>`).join('')}</div>` : ''}
      ${h.note ? `<div class="small">${esc(h.note)}</div>` : ''}
      ${h.caretaker_phone ? `<a class="btn btn-sm btn-light mt-1" href="${tel(h.caretaker_phone)}"><i class="bi bi-telephone"></i> ผู้ดูแล${h.caretaker_name ? ' ' + esc(h.caretaker_name) : ''}</a>` : ''}
      ${h.last_check ? `<div class="small text-muted mt-1">เยี่ยมล่าสุด: ${esc(h.last_check)}</div>` : ''}
    </div>`;
  }

  /** จุดเสี่ยงที่กำลังเตือนใกล้ทีม (ภายใน 5 กม.) */
  function riskAlerts() {
    const near = (state.risks || []).filter(r => r.status === 'threatened' && (r.distance_m == null || r.distance_m <= 5000));
    if (!near.length) return '';
    return `<div class="f-section-title text-warning"><i class="bi bi-exclamation-triangle-fill"></i> จุดเสี่ยงใกล้ทีม</div>` + near.slice(0, 5).map(r => `
      <div class="f-risk"><i class="bi bi-${esc(r.icon)}"></i><div class="min-w-0"><b>${esc(r.name)}</b>${r.distance_m != null ? ` · ${km(r.distance_m)}` : ''}<div class="small">${esc(r.reason || r.type)}</div>${r.description ? `<div class="small opacity-75">${esc(r.description)}</div>` : ''}</div></div>`).join('');
  }

  function renderJobs() {
    const el = $('#tabJobs');
    const offers = state.jobs.filter(j => j.status === 'offered');
    const route = routeOrder(state.jobs);
    let h = (state.alerts || []).map(a => `<div class="f-risk" style="background:${esc(a.color)}"><i class="bi bi-megaphone-fill"></i><div class="min-w-0"><b>${esc(a.level)}: ${esc(a.title)}</b>${a.body ? `<div class="small">${esc(a.body)}</div>` : ''}</div></div>`).join('') + riskAlerts();

    if (offers.length) {
      h += `<div class="f-section-title text-danger"><i class="bi bi-bell-fill"></i> ศูนย์เสนองาน รอทีมตอบ</div>`;
      h += offers.map(j => `
        <div class="f-card offer" style="--c:${j.case.color}">
          ${caseBlock(j.case, false)}
          ${j.eta ? `<div class="small text-muted mt-1">ประมาณ ${km(j.distance_m)} · ${j.eta} นาที</div>` : ''}
          <div class="f-actions">
            <button class="btn btn-primary f-big" data-do="accept" data-a="${j.assignment_id}"><i class="bi bi-check-lg"></i> รับงาน</button>
            <button class="btn btn-outline-danger f-big" data-do="decline" data-a="${j.assignment_id}">ปฏิเสธ</button>
          </div>
          ${j.pending ? '<div class="f-pending"><i class="bi bi-hourglass-split"></i> รอส่งให้ศูนย์</div>' : ''}
        </div>`).join('');
    }

    h += `<div class="f-section-title"><i class="bi bi-life-preserver"></i> งานที่กำลังทำ (${route.length})</div>`;
    if (route.length > 1) {
      h += `<div class="f-route"><i class="bi bi-signpost-split fs-4 text-primary"></i><div class="flex-grow-1 small">เรียงจุดแวะให้แล้ว ${route.length} จุด<br><span class="text-muted">ใกล้สุดก่อน วิกฤตก่อนเสมอ</span></div><a class="btn btn-primary" target="_blank" rel="noopener" href="${routeLink(route)}">นำทางทั้งรอบ</a></div>`;
    }
    if (!route.length) {
      h += `<div class="f-card text-center text-muted" style="border-left-color:#e5e7eb"><i class="bi bi-check2-circle fs-1 d-block mb-1"></i>ไม่มีงานค้าง${state.team.self_assign ? '<br><button class="btn btn-soft mt-2" data-go="near">ดูเคสรอบตัว</button>' : '<br><span class="small">รอศูนย์มอบหมายงาน</span>'}</div>`;
    }
    h += route.map((j, i) => {
      const a = j.assignment_id;
      const next = j.status === 'accepted'
        ? `<button class="btn btn-primary f-big wide" data-do="progress" data-step="en_route" data-a="${a}"><i class="bi bi-truck"></i> เริ่มเดินทาง</button>`
        : j.status === 'en_route'
          ? `<button class="btn btn-primary f-big wide" data-do="progress" data-step="on_site" data-a="${a}"><i class="bi bi-geo-alt-fill"></i> ถึงที่เกิดเหตุแล้ว</button>`
          : '';
      return `
        <div class="f-card" style="--c:${j.case.color}">
          <div class="d-flex align-items-center gap-2 mb-2">
            ${route.length > 1 ? `<span class="f-stop">${i + 1}</span>` : ''}
            <span class="chip chip-primary">${esc(j.status_label)}</span>
            ${j.eta && j.status !== 'on_site' ? `<span class="small text-muted">ราว ${j.eta} นาที</span>` : ''}
          </div>
          ${caseBlock(j.case, true)}
          <div class="f-actions">
            ${a ? next : ''}
            ${a ? `<button class="btn ${j.status === 'on_site' ? 'btn-success f-big wide' : 'btn-light'}" data-do="complete" data-a="${a}" data-people="${j.case.people}"><i class="bi bi-check2-circle"></i> ปิดงาน</button>` : ''}
            ${j.case.household ? `<button class="btn btn-warning" data-do="hhcheck" data-case="${j.case.id}"><i class="bi bi-clipboard-check"></i> ผลเยี่ยมบ้าน</button>` : ''}
            <button class="btn btn-light" data-do="water" data-case="${j.case.id}"><i class="bi bi-droplet-half"></i> ระดับน้ำจริง</button>
            <button class="btn btn-light" data-do="note" data-case="${j.case.id}"><i class="bi bi-chat-left-text"></i> บันทึก</button>
            <label class="btn btn-light mb-0"><i class="bi bi-camera"></i> ถ่ายรูป<input type="file" accept="image/*" capture="environment" class="d-none" data-photo="${j.case.id}"></label>
          </div>
          ${j.pending ? '<div class="f-pending"><i class="bi bi-hourglass-split"></i> รอยืนยันจากศูนย์</div>' : ''}
        </div>`;
    }).join('');

    if (state.today) h += `<div class="text-center small text-muted mt-2">วันนี้ปิดงานแล้ว ${state.today.done || 0} งาน · ช่วยออกมา ${state.today.people || 0} คน</div>`;
    el.innerHTML = h;
  }

  function renderNear() {
    const el = $('#tabNear');
    if (!state.team.self_assign) {
      el.innerHTML = `<div class="f-card text-center text-muted" style="border-left-color:#e5e7eb">ศูนย์ยังไม่เปิดให้ทีมหยิบเคสเอง<br><span class="small">รองานที่ศูนย์มอบหมายในแท็บ งานของฉัน</span></div>`;
      return;
    }
    el.innerHTML = `<div class="f-section-title"><i class="bi bi-geo"></i> เคสรอทีม เรียงความเร่งด่วนและระยะ</div>` + (state.nearby.length
      ? state.nearby.map(c => `<div class="f-card" style="--c:${c.color}">${caseBlock(c, false)}
          <div class="f-actions"><button class="btn btn-primary f-big wide" data-do="pick" data-case="${c.id}" data-code="${esc(c.code)}"><i class="bi bi-hand-index-thumb"></i> รับเคสนี้</button></div></div>`).join('')
      : `<div class="f-card text-center text-muted" style="border-left-color:#e5e7eb"><i class="bi bi-check2-all fs-1 d-block"></i>ไม่มีเคสรอทีม</div>`);
  }

  function renderTeam() {
    const t = state.team;
    $('#tabTeam').innerHTML = `
      <div class="f-card" style="--c:${t.color}">
        <div class="f-name">${esc(t.name)}</div>
        <div class="f-meta mb-2"><span>สถานะ: ${esc(t.status_label)}</span><span>${t.last_seen ? 'ส่งพิกัดล่าสุด ' + new Date(t.last_seen).toLocaleTimeString('th-TH', { hour: '2-digit', minute: '2-digit' }) : 'ยังไม่ส่งพิกัด'}</span></div>
        <div class="f-section-title">เปลี่ยนสถานะทีม</div>
        <div class="f-actions">${Object.entries(C.options.team_status).map(([k, s]) => `<button class="btn ${t.status === k ? 'text-white' : 'btn-light'}" style="${t.status === k ? 'background:' + s.color : ''}" data-do="status" data-status="${k}">${esc(s.label)}</button>`).join('')}</div>
      </div>
      <div class="f-card" style="--c:#2563eb">
        <div class="f-section-title mt-0"><i class="bi bi-droplet-half"></i> รายงานระดับน้ำ</div>
        <div class="small text-muted mb-2">รายงานจากทีมถือว่ายืนยันแล้ว ศูนย์ใช้เตือนจุดเสี่ยงและครัวเรือนเปราะบางทันที</div>
        <button class="btn btn-primary f-big wide" data-do="report"><i class="bi bi-geo-alt"></i> รายงานระดับน้ำตรงนี้</button>
      </div>
      <div class="f-card" style="--c:#94a3b8">
        <div class="f-section-title mt-0">ยานพาหนะ</div>
        ${(t.vehicles || []).map(v => `<div class="d-flex justify-content-between py-1"><span>${esc(v.name)} <span class="text-muted small">${esc(v.label)}</span></span><span class="small">${v.capacity ? v.capacity + ' คน' : ''} ${v.status === 'maintenance' ? '<span class="chip chip-danger">ซ่อม</span>' : ''}</span></div>`).join('') || '<div class="text-muted small">ยังไม่มีข้อมูล</div>'}
      </div>
      <div class="f-card" style="--c:#94a3b8">
        <div class="small text-muted mb-2">${esc(C.userName)}</div>
        <div class="f-actions">
          <a class="btn btn-light" href="${C.desktopUrl}">หน้าจอแบบเต็ม</a>
          <button class="btn btn-light" data-do="install" id="installBtn">ติดตั้งแอป</button>
          <form method="POST" action="${C.logoutUrl}" class="wide"><input type="hidden" name="_token" value="${token()}"><button class="btn btn-outline-danger w-100">ออกจากระบบ</button></form>
        </div>
      </div>`;
  }

  function renderSos() {
    const el = $('#sosState');
    el.innerHTML = state.sos
      ? `<div class="alert ${state.sos.status === 'ack' ? 'alert-primary' : 'alert-danger'} text-start"><div><b>${esc(state.sos.kind)}</b><br>${esc(state.sos.status_label)}</div></div>
         <button class="btn btn-success w-100" data-do="sos_safe">ปลอดภัยแล้ว ยกเลิก SOS</button>`
      : '';
    $('#sosBtn').disabled = !!state.sos;
    $('#sosBtn').style.opacity = state.sos ? .4 : 1;
  }

  /* ---------- แผนที่ ---------- */
  let map = null, layer = null;
  function renderMap() {
    if (!window.L) return;
    if (!map) {
      map = L.map('fMap', { zoomControl: false }).setView(myPos || [13.75, 100.5], 13);
      L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', { maxZoom: 19, attribution: '&copy; OSM' }).addTo(map);
      layer = L.layerGroup().addTo(map);
    }
    setTimeout(() => map.invalidateSize(), 50);
    layer.clearLayers();
    const pts = [];
    const route = routeOrder(state.jobs);
    route.forEach((j, i) => {
      pts.push([j.case.lat, j.case.lng]);
      L.marker([j.case.lat, j.case.lng], { icon: L.divIcon({ className: '', html: `<div class="f-stop" style="background:${j.case.color};border:3px solid #fff">${i + 1}</div>`, iconSize: [30, 30], iconAnchor: [15, 15] }) })
        .bindPopup(`<b>${esc(j.case.name)}</b><br>${esc(j.case.water)} · ${j.case.people} คน`).addTo(layer);
    });
    state.jobs.filter(j => j.status === 'offered').concat(state.team.self_assign ? state.nearby.map(c => ({ case: c, near: true })) : []).forEach(j => {
      pts.push([j.case.lat, j.case.lng]);
      L.circleMarker([j.case.lat, j.case.lng], { radius: 8, color: '#fff', weight: 2, fillColor: j.case.color, fillOpacity: j.near ? .6 : 1 })
        .bindPopup(`<b>${esc(j.case.name)}</b><br>${esc(j.case.water)} · ${j.case.people} คน${j.near ? '<br>รอทีม' : '<br>ศูนย์เสนองาน'}`).addTo(layer);
    });
    (state.reports || []).forEach(r => {
      L.circleMarker([r.lat, r.lng], { radius: 3 + r.level, color: r.trusted ? '#1d4ed8' : '#fff', weight: r.trusted ? 2 : 1, fillColor: r.color, fillOpacity: .75 })
        .bindPopup(`<b>น้ำ${esc(r.label)}</b><br>${esc(r.ago)}${r.trend ? ' · ' + esc(r.trend) : ''}${r.note ? '<br>' + esc(r.note) : ''}`).addTo(layer);
    });
    (state.risks || []).forEach(r => {
      const threat = r.status === 'threatened';
      const style = { color: r.color, weight: threat ? 2 : 1, fillColor: r.color, fillOpacity: threat ? .18 : .06, interactive: false };
      if (r.zone) L.geoJSON(r.zone, { style: () => style }).addTo(layer);
      else if (r.radius && threat) L.circle([r.lat, r.lng], Object.assign({ radius: r.radius }, style)).addTo(layer);
      L.marker([r.lat, r.lng], { icon: L.divIcon({ className: '', html: `<div class="risk-marker${threat ? ' threat' : ''}" style="background:${r.color}"><i class="bi bi-${esc(r.icon)}"></i></div>`, iconSize: [26, 26], iconAnchor: [13, 13] }) })
        .bindPopup(`<b>${esc(r.name)}</b><br>${esc(r.type)}${threat ? `<br><span class="text-danger">${esc(r.reason || 'กำลังเตือน')}</span>` : ''}${r.description ? '<br>' + esc(r.description) : ''}`).addTo(layer);
    });
    if (myPos) { pts.push(myPos); L.circleMarker(myPos, { radius: 9, color: '#fff', weight: 3, fillColor: '#2563eb', fillOpacity: 1 }).bindTooltip('ทีมของฉัน').addTo(layer); }
    if (route.length > 1 || (myPos && route.length)) L.polyline([...(myPos ? [myPos] : []), ...route.map(j => [j.case.lat, j.case.lng])], { color: '#0e2233', weight: 3, dashArray: '6 6' }).addTo(layer);
    if (pts.length) map.fitBounds(L.latLngBounds(pts).pad(0.25), { maxZoom: 15 });
    $('#fMapLegend').innerHTML = 'ตัวเลข = ลำดับจุดแวะ · วงกลมทึบ = ศูนย์เสนองาน · วงกลมจาง = เคสรอทีม · จุดน้ำเงิน = ทีม · ไอคอนสี = จุดเสี่ยง (แดงกระพริบ = กำลังเตือน) · จุดเล็ก = รายงานระดับน้ำ';
  }

  /* ================= แผ่นเลือก (modal) ================= */
  let sheetOk = null;
  function sheet(title, body, onOk, okLabel = 'ยืนยัน') {
    $('#sheetTitle').textContent = title;
    $('#sheetBody').innerHTML = body;
    $('#sheetOk').textContent = okLabel;
    sheetOk = onOk;
    bootstrap.Modal.getOrCreateInstance($('#sheet')).show();
  }
  $('#sheetOk').addEventListener('click', () => {
    const ok = sheetOk && sheetOk($('#sheetBody'));
    if (ok !== false) bootstrap.Modal.getOrCreateInstance($('#sheet')).hide();
  });
  const choices = (name, opts, checked) => `<div class="f-choice">${Object.entries(opts).map(([k, v]) => `<label><input type="radio" name="${name}" value="${k}" ${String(k) === String(checked) ? 'checked' : ''}> ${esc(typeof v === 'object' ? v.label : v)}</label>`).join('')}</div>`;

  /* ================= ปุ่มต่างๆ ================= */
  document.addEventListener('click', e => {
    const go = e.target.closest('[data-go]');
    if (go) { switchTab(go.dataset.go); return; }
    const b = e.target.closest('[data-do]');
    if (!b) return;
    const a = Number(b.dataset.a), cid = Number(b.dataset.case);
    switch (b.dataset.do) {
      case 'accept': act('respond', { assignment_id: a, accept: 1 }, 'รับงานแล้ว'); vibrate(); break;
      case 'decline': sheet('ปฏิเสธงาน', choices('reason', C.options.decline, 'full'), body => act('respond', { assignment_id: a, accept: 0, reason: body.querySelector('[name=reason]:checked').value }, 'ปฏิเสธงานแล้ว')); break;
      case 'progress': act('progress', { assignment_id: a, step: b.dataset.step }, b.dataset.step === 'en_route' ? 'เริ่มเดินทาง' : 'ถึงที่เกิดเหตุแล้ว'); vibrate(); break;
      case 'complete':
        sheet('ปิดงาน', choices('outcome', C.options.outcomes, 'shelter') + `<label class="form-label mt-3">ช่วยออกมาได้กี่คน</label><input type="number" class="form-control form-control-lg" name="people" min="0" max="500" value="${b.dataset.people}"><input class="form-control mt-2" name="note" maxlength="500" placeholder="หมายเหตุ เช่น ส่งศูนย์พักพิงวัดโสธร">`,
          body => act('complete', { assignment_id: a, outcome: body.querySelector('[name=outcome]:checked').value, people_rescued: body.querySelector('[name=people]').value, note: body.querySelector('[name=note]').value }, 'ปิดงานแล้ว'), 'ปิดงาน');
        break;
      case 'water': {
        const job = state.jobs.find(j => j.case.id === cid);
        sheet('ระดับน้ำจริงที่หน้างาน', choices('lv', C.options.levels, job?.case.water_level), body => act('water', { case_id: cid, water_level: Number(body.querySelector('[name=lv]:checked').value) }, 'บันทึกระดับน้ำแล้ว'));
        break;
      }
      case 'note': sheet('บันทึกถึงศูนย์', '<textarea class="form-control" rows="4" name="note" maxlength="1000" placeholder="เช่น ผู้ป่วยต้องใช้เปล ต้องการรถพยาบาลรอที่ถนนใหญ่"></textarea>', body => {
        const v = body.querySelector('[name=note]').value.trim(); if (!v) return false; act('note', { case_id: cid, note: v }, 'ส่งบันทึกแล้ว');
      }); break;
      case 'report': {
        const go = (pos, accm) => sheet('ระดับน้ำตรงนี้', choices('lv', C.options.levels, 3) +
          `<div class="small fw-600 mt-3 mb-1">แนวโน้ม</div>${choices('tr', C.options.trends, '')}` +
          '<input class="form-control mt-3" name="note" maxlength="500" placeholder="เช่น ถนนสายหลักรถเล็กผ่านไม่ได้">' +
          `<div class="small text-muted mt-2">${pos ? 'พิกัด ' + pos[0].toFixed(5) + ', ' + pos[1].toFixed(5) + (accm ? ' (±' + accm + ' ม.)' : '') : ''}</div>`,
          body => {
            const tr = body.querySelector('[name=tr]:checked');
            act('report', { lat: pos[0], lng: pos[1], accuracy_m: accm || null, level: Number(body.querySelector('[name=lv]:checked').value), trend: tr ? tr.value : null, note: body.querySelector('[name=note]').value }, 'ส่งรายงานระดับน้ำแล้ว');
          }, 'ส่งรายงาน');
        if (!navigator.geolocation) { if (myPos) go(myPos); else toast('ไม่มีพิกัด เปิด GPS ก่อน', 'error'); break; }
        toast('กำลังหาตำแหน่ง...', 'success');
        navigator.geolocation.getCurrentPosition(p => { myPos = [p.coords.latitude, p.coords.longitude]; go(myPos, Math.round(p.coords.accuracy || 0)); },
          () => { if (myPos) go(myPos); else toast('หาตำแหน่งไม่ได้ เปิด GPS แล้วลองใหม่', 'error'); },
          { enableHighAccuracy: true, timeout: 12000, maximumAge: 30000 });
        break;
      }
      case 'hhcheck': {
        const cid = Number(b.dataset.case);
        sheet('ผลการเยี่ยมบ้าน', choices('ck', C.options.check, 'safe') + '<input class="form-control mt-3" name="note" maxlength="500" placeholder="หมายเหตุ เช่น ยาเหลือ 3 วัน ญาติมารับแล้ว">' +
          '<div class="small text-muted mt-2">ถ้าต้องพาออกมา ให้ปิดงานตามปกติหลังบันทึกผล</div>',
          body => act('household_check', { case_id: cid, status: body.querySelector('[name=ck]:checked').value, note: body.querySelector('[name=note]').value }, 'บันทึกผลเยี่ยมแล้ว'));
        break;
      }
      case 'pick': if (confirm(`รับเคส ${b.dataset.code}?`)) { act('pick', { case_id: cid }, 'รับเคสแล้ว'); switchTab('jobs'); } break;
      case 'status': act('team_status', { status: b.dataset.status }, 'เปลี่ยนสถานะทีมแล้ว'); break;
      case 'sos_safe': act('sos_safe', {}, 'แจ้งศูนย์ว่าปลอดภัยแล้ว'); break;
      case 'install': if (deferredInstall) { deferredInstall.prompt(); deferredInstall = null; } else toast('เปิดเมนูของเบราว์เซอร์ แล้วเลือก เพิ่มไปยังหน้าจอหลัก', 'success'); break;
    }
  });

  $('#fStatus').addEventListener('click', () => sheet('สถานะทีม', choices('st', C.options.team_status, state.team.status), body => act('team_status', { status: body.querySelector('[name=st]:checked').value }, 'เปลี่ยนสถานะทีมแล้ว')));

  // รูปจากหน้างาน (ต้องมีเน็ต)
  document.addEventListener('change', async e => {
    const input = e.target.closest('[data-photo]');
    if (!input || !input.files[0]) return;
    if (!navigator.onLine) { toast('ต้องมีสัญญาณเน็ตเพื่อส่งรูป', 'error'); return; }
    const fd = new FormData(); fd.append('case_id', input.dataset.photo); fd.append('photo', input.files[0]);
    toast('กำลังส่งรูป...', 'success');
    try { const r = await api(C.photoUrl, { method: 'POST', body: fd }); toast(r.message || 'แนบรูปแล้ว', 'success'); } catch (_) { toast('ส่งรูปไม่สำเร็จ', 'error'); }
    input.value = '';
  });

  /* ---------- SOS กดค้าง ---------- */
  (function sos() {
    const btn = $('#sosBtn'), ring = $('#sosRing');
    let t0 = 0, raf = null;
    const HOLD = 2000, LEN = 352;
    const reset = () => { cancelAnimationFrame(raf); ring.style.strokeDashoffset = LEN; t0 = 0; };
    const tick = () => {
      const p = Math.min(1, (Date.now() - t0) / HOLD);
      ring.style.strokeDashoffset = LEN * (1 - p);
      if (p >= 1) { reset(); fire(); return; }
      raf = requestAnimationFrame(tick);
    };
    const fire = () => {
      vibrate([400, 100, 400]);
      const send = (pos) => act('sos', { kind: document.querySelector('[name=sosKind]:checked')?.value || 'backup', note: $('#sosNote').value, lat: pos?.[0], lng: pos?.[1] }, 'ส่ง SOS แล้ว');
      if (navigator.geolocation) navigator.geolocation.getCurrentPosition(p => send([p.coords.latitude, p.coords.longitude]), () => send(myPos), { enableHighAccuracy: true, timeout: 5000 });
      else send(myPos);
    };
    btn.addEventListener('pointerdown', e => { if (btn.disabled) return; e.preventDefault(); t0 = Date.now(); vibrate(40); raf = requestAnimationFrame(tick); });
    ['pointerup', 'pointerleave', 'pointercancel'].forEach(ev => btn.addEventListener(ev, reset));
    btn.addEventListener('contextmenu', e => e.preventDefault());
  })();

  /* ================= ทั่วไป ================= */
  function switchTab(name) {
    tab = name;
    document.querySelectorAll('.f-tab').forEach(t => t.classList.toggle('d-none', t.dataset.tab !== name));
    document.querySelectorAll('.f-nav button').forEach(b => b.classList.toggle('active', b.dataset.go === name));
    render();
    window.scrollTo(0, 0);
  }

  function toast(msg, kind = 'success') {
    const w = $('#fToast');
    const el = document.createElement('div');
    el.className = 'flash ' + kind;
    el.innerHTML = `<i class="bi bi-${kind === 'error' ? 'exclamation-circle-fill' : 'check-circle-fill'}"></i><div class="flex-grow-1">${esc(msg)}</div>`;
    w.appendChild(el);
    setTimeout(() => el.remove(), 3500);
  }

  function vibrate(p = 60) { try { navigator.vibrate && navigator.vibrate(p); } catch (_) {} }

  let deferredInstall = null;
  window.addEventListener('beforeinstallprompt', e => { e.preventDefault(); deferredInstall = e; });
  window.addEventListener('online', () => { setNet(); flush(); });
  window.addEventListener('offline', setNet);
  document.addEventListener('visibilitychange', () => { if (document.visibilityState === 'visible') { refresh(); flush(); } });

  // realtime: ศูนย์เสนองาน / ยกเลิกงาน / ตอบ SOS
  if (window.FloodLive) {
    FloodLive.channel('team.' + C.teamId)
      .on('case.changed', e => { if (e.status === 'offered') { vibrate([300, 150, 300]); FloodLive.sound.beep(2); toast('ศูนย์เสนองานใหม่ ' + e.code); } refresh(); })
      .on('team.sos', () => refresh());
  }

  render();
  refresh().then(flush);
  locate();
  setInterval(() => { refresh(); flush(); }, 30000);
  setInterval(() => locate(true), 60000);
})();
