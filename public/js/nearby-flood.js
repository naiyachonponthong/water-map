/* GPS stays in this browser. Only province-wide public observations are fetched. */
(function (root) {
  'use strict';
  const esc = s => String(s ?? '').replace(/[&<>"']/g, c => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]));
  const distance = (a, b) => {
    const rad = Math.PI / 180, dlat = (b[0] - a[0]) * rad, dlng = (b[1] - a[1]) * rad;
    const n = Math.sin(dlat / 2) ** 2 + Math.cos(a[0] * rad) * Math.cos(b[0] * rad) * Math.sin(dlng / 2) ** 2;
    return 6371000 * 2 * Math.atan2(Math.sqrt(n), Math.sqrt(Math.max(0, 1 - n)));
  };
  function inRing(point, ring) {
    let inside = false;
    for (let i = 0, j = ring.length - 1; i < ring.length; j = i++) {
      const [x, y] = ring[i], [xx, yy] = ring[j];
      if (((y > point[0]) !== (yy > point[0])) && point[1] < (xx - x) * (point[0] - y) / ((yy - y) || 1e-12) + x) inside = !inside;
    }
    return inside;
  }
  function contains(point, geometry) {
    const polygons = geometry?.type === 'Polygon' ? [geometry.coordinates] : geometry?.type === 'MultiPolygon' ? geometry.coordinates : [];
    return polygons.some(p => inRing(point, p[0]) && !p.slice(1).some(r => inRing(point, r)));
  }
  function recent(rows, at = Date.now()) {
    return (rows || []).filter(r => Number.isFinite(r.lat) && Number.isFinite(r.lng) && r.level >= 2 &&
      Date.parse(r.updated_at) >= at - 12 * 3600000 && Date.parse(r.updated_at) <= at + 600000 && Date.parse(r.expires_at) > at);
  }
  function summarize(rows, scope, at = Date.now()) {
    const current = recent(rows, at);
    const hits = current.filter(r => scope.point ? distance(scope.point, [r.lat, r.lng]) <= (scope.radius || 3000) :
      String(r.subdistrict_code || '') === scope.subdistrict);
    return { hits, trusted: hits.filter(r => r.trusted).length,
      unassigned: scope.subdistrict ? current.filter(r => !r.subdistrict_code).length : 0 };
  }
  function mount(node, onFocus) {
    const find = s => node.querySelector(s), result = find('[data-near-result]'), district = find('[data-near-district]'), sub = find('[data-near-subdistrict]');
    let context = null, reports = null, scope = null, generation = 0, dataError = false;
    const gps = find('[data-near-gps]');
    function message(text, cls = '') { result.className = 'wn-result ' + cls; result.textContent = text; }
    function show() {
      if (!scope) return;
      if (dataError) { message('ยังโหลดรายงานน้ำท่วมไม่ได้ จึงตรวจพื้นที่นี้ไม่ได้ในขณะนี้ กรุณาลองอัปเดตข้อมูลอีกครั้ง', 'is-error'); return; }
      if (!reports || !context) { message('กำลังโหลดรายงานของจังหวัดที่เลือก…'); return; }
      if (scope.point && (!context.boundary || !contains(scope.point, context.boundary.geometry))) {
        message(context.boundary ? 'ตำแหน่งนี้อยู่นอกขอบเขตจังหวัดที่เลือก กรุณาเปลี่ยนจังหวัด หรือเลือกตำบลแทน' : 'ยังยืนยันไม่ได้ว่าพิกัดอยู่ในจังหวัดที่เลือก กรุณาเลือกตำบลแทน', 'is-neutral'); return;
      }
      const summary = summarize(reports.reports, scope);
      const label = scope.point ? `รอบตำแหน่งของคุณ ${((scope.radius || 3000) / 1000).toFixed(0)} กม.` : scope.label;
      const incomplete = reports.truncated ? 'ชุดรายงานมีจำนวนมากและอาจแสดงไม่ครบ' : summary.unassigned ? `มี ${summary.unassigned} รายงานในจังหวัดที่ยังไม่ระบุตำบล` : '';
      result.className = 'wn-result ' + (summary.hits.length ? 'is-alert' : 'is-neutral');
      result.innerHTML = `<strong>${summary.hits.length ? `พบ ${summary.hits.length} รายงานน้ำท่วม` : 'ยังไม่พบรายงานที่ตรงกับพื้นที่นี้'}</strong><span>${esc(label)} · ช่วง 12 ชม. ล่าสุด</span>${summary.hits.length ? `<br>มีการยืนยัน ${summary.trusted} รายงาน · รายงานอื่นยังต้องตรวจสอบ` : ''}<br>${esc(incomplete)}${incomplete ? '<br>' : ''}ไม่พบรายงานไม่ได้แปลว่าปลอดภัย โปรดตรวจสภาพจริงและประกาศทางการ`;
      if (onFocus && scope.point) onFocus(scope.point, scope.radius || 3000, summary.hits);
    }
    function reset() {
      generation++; context = reports = scope = null; dataError = false; gps.disabled = false;
      district.innerHTML = '<option value="">กำลังโหลดพื้นที่…</option>'; district.disabled = true;
      sub.innerHTML = '<option value="">เลือกอำเภอหรือเขตก่อน</option>'; sub.disabled = true;
      message('เลือกตำแหน่งเพื่อดูรายงาน · ไม่พบรายงานไม่ได้แปลว่าปลอดภัย');
    }
    function setContext(data) {
      context = data;
      find('[data-near-manual]').innerHTML = `<i class="bi bi-geo-alt" aria-hidden="true"></i> เลือก${data.code === '10' ? 'แขวง' : 'ตำบล'}`;
      const labels = node.querySelectorAll('[data-near-selectors] label>span');
      if (labels[0]) labels[0].textContent = data.code === '10' ? 'เขต' : 'อำเภอ';
      if (labels[1]) labels[1].textContent = data.code === '10' ? 'แขวง' : 'ตำบล';
      const selected = district.value;
      district.innerHTML = '<option value="">เลือกอำเภอ / เขต</option>' + data.areas.map(a => `<option value="${esc(a.code)}">${esc(a.name)}</option>`).join('');
      district.disabled = !data.areas.length;
      if (data.areas.some(a => a.code === selected)) district.value = selected;
      show();
    }
    function setReports(data, error = false) { reports = data; dataError = error; show(); }
    find('[data-near-manual]').addEventListener('click', () => {
      generation++; gps.disabled = false;
      const selectors = find('[data-near-selectors]'); selectors.hidden = !selectors.hidden;
      find('[data-near-manual]').setAttribute('aria-expanded', String(!selectors.hidden));
      if (!selectors.hidden) district.focus();
    });
    district.addEventListener('change', () => {
      generation++; gps.disabled = false; scope = null;
      const area = context?.areas.find(a => a.code === district.value);
      sub.innerHTML = '<option value="">เลือกตำบล / แขวง</option>' + (area?.subdistricts || []).map(s => `<option value="${esc(s.code)}">${esc(s.name)}</option>`).join('');
      sub.disabled = !area; message('เลือกตำบลหรือแขวงเพื่อดูรายงานในพื้นที่');
    });
    sub.addEventListener('change', () => {
      generation++; gps.disabled = false;
      const area = context?.areas.find(a => a.code === district.value), selected = area?.subdistricts.find(s => s.code === sub.value);
      scope = selected ? { subdistrict: selected.code, label: `${selected.name} ${area.name} ${context.name}` } : null;
      if (selected?.center && onFocus) onFocus(selected.center, null, []);
      show();
    });
    gps.addEventListener('click', () => {
      if (!navigator.geolocation) { message('เครื่องนี้ไม่รองรับ GPS กรุณาเลือกตำบลแทน', 'is-error'); return; }
      const version = ++generation; gps.disabled = true; message('กำลังขอตำแหน่งของคุณ… คุณสามารถเลือกตำบลแทนได้');
      navigator.geolocation.getCurrentPosition(position => {
        if (version !== generation) return;
        gps.disabled = false;
        if (!Number.isFinite(position.coords.accuracy) || position.coords.accuracy > 1000 || !Number.isFinite(position.coords.latitude) || !Number.isFinite(position.coords.longitude)) {
          message('GPS คลาดเคลื่อนมากกว่า 1 กม. ยังตรวจใกล้บ้านไม่ได้ กรุณาเลือกตำบลหรือเปิด GPS ให้แม่นยำขึ้น', 'is-error'); return;
        }
        scope = { point: [position.coords.latitude, position.coords.longitude], radius: 3000 }; show();
      }, () => {
        if (version !== generation) return;
        gps.disabled = false; message('ยังใช้ตำแหน่งไม่ได้ ตรวจสิทธิ์ GPS ของเบราว์เซอร์ หรือเลือกตำบลแทน', 'is-error');
      }, { enableHighAccuracy: true, timeout: 12000, maximumAge: 60000 });
    });
    return { reset, setContext, setReports, contextError: () => message('ยังโหลดรายชื่อพื้นที่ไม่ได้ กรุณาลองอัปเดตอีกครั้ง', 'is-error') };
  }
  const api = { esc, distance, contains, recent, summarize, mount };
  if (typeof module !== 'undefined' && module.exports) module.exports = api;
  else root.FloodNear = api;
  if (typeof document !== 'undefined' && !document.getElementById('waterPage')) {
    document.querySelectorAll('[data-nearby-flood]').forEach(async node => {
      const widget = mount(node);
      const fetchJson = async url => { const r = await fetch(url, { headers: { Accept: 'application/json' } }); if (!r.ok) throw Error('Unavailable'); return r.json(); };
      fetchJson(node.dataset.contextUrl).then(widget.setContext).catch(widget.contextError);
      const reload = () => fetchJson(node.dataset.reportsUrl).then(data => widget.setReports(data)).catch(() => widget.setReports(null, true));
      reload(); setInterval(() => { if (!document.hidden) reload(); }, 60000);
    });
  }
})(typeof window !== 'undefined' ? window : globalThis);
