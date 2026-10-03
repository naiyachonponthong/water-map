/* ฟอร์มขอความช่วยเหลือ: แบ่ง 4 ขั้น, GPS, ปักหมุด, วางลิงก์, เก็บร่างในเครื่อง */
(function () {
  'use strict';
  const cfg = window.HELP_CFG || {};
  const form = document.getElementById('helpForm');
  if (!form) return;

  const $ = (s, el = form) => el.querySelector(s);
  const $$ = (s, el = form) => Array.from(el.querySelectorAll(s));
  const steps = $$('.help-step');
  const dots = Array.from(document.querySelectorAll('[data-step-dot]'));
  const btnNext = $('#btnNext'), btnBack = $('#btnBack'), btnSubmit = $('#btnSubmit');
  const f = name => form.elements[name];
  let current = 0;

  /* ---------- แผนที่ ---------- */
  let map = null, marker = null, pinMode = false;
  function initMap() {
    if (map || !window.L) return;
    map = L.map('helpMap', { zoomControl: true }).setView(cfg.center, cfg.zoom || 10);
    L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', { maxZoom: 19, attribution: '&copy; OpenStreetMap' }).addTo(map);
    map.on('click', e => { if (pinMode || !marker) setLocation(e.latlng.lat, e.latlng.lng, 'map'); });
    if (f('lat').value && f('lng').value) setLocation(+f('lat').value, +f('lng').value, f('location_source').value || 'map', null, true);
  }

  function setLocation(lat, lng, source, accuracy, silent) {
    f('lat').value = (+lat).toFixed(7);
    f('lng').value = (+lng).toFixed(7);
    f('location_source').value = source;
    f('accuracy_m').value = accuracy ? Math.round(accuracy) : '';
    if (map) {
      if (!marker) {
        marker = L.marker([lat, lng], { draggable: true }).addTo(map);
        marker.on('dragend', () => { const p = marker.getLatLng(); setLocation(p.lat, p.lng, 'map'); });
      } else marker.setLatLng([lat, lng]);
      map.setView([lat, lng], Math.max(map.getZoom(), 16));
    }
    const label = { gps: 'ตำแหน่งจาก GPS', map: 'ปักหมุดบนแผนที่', link: 'จากลิงก์ที่วาง' }[source] || 'ตำแหน่ง';
    $('#locStatus').innerHTML = `<span class="text-success fw-600"><i class="bi bi-check-circle-fill"></i> ${label}</span>` +
      (accuracy ? ` <span class="text-muted">แม่นยำประมาณ ${Math.round(accuracy)} ม.</span>` : '') +
      ` <span class="text-muted">· ลากหมุดเพื่อขยับได้</span>`;
    if (!silent) saveDraft();
  }

  $('#btnGps').addEventListener('click', () => {
    const status = $('#locStatus');
    if (!navigator.geolocation) { status.innerHTML = '<span class="text-danger">เครื่องนี้ไม่รองรับ GPS ปักหมุดบนแผนที่แทน</span>'; return; }
    status.innerHTML = '<span class="text-muted"><span class="spinner-border spinner-border-sm"></span> กำลังหาตำแหน่ง...</span>';
    navigator.geolocation.getCurrentPosition(
      p => setLocation(p.coords.latitude, p.coords.longitude, 'gps', p.coords.accuracy),
      err => {
        status.innerHTML = '<span class="text-danger">' + (err.code === 1 ? 'ไม่ได้รับอนุญาตให้ใช้ตำแหน่ง เปิดสิทธิ์ตำแหน่งในเบราว์เซอร์ หรือปักหมุดบนแผนที่แทน' : 'หาตำแหน่งไม่ได้ ลองอีกครั้งหรือปักหมุดบนแผนที่') + '</span>';
      },
      { enableHighAccuracy: true, timeout: 15000, maximumAge: 60000 }
    );
  });

  $('#btnPin').addEventListener('click', () => {
    pinMode = true;
    $('#locStatus').innerHTML = '<span class="text-primary fw-600"><i class="bi bi-hand-index"></i> แตะบนแผนที่ตรงจุดที่คุณอยู่</span>';
    document.getElementById('helpMap').scrollIntoView({ behavior: 'smooth', block: 'center' });
  });

  $('#btnPaste').addEventListener('click', async e => {
    const text = $('#pasteLoc').value.trim();
    if (!text) return;
    const btn = e.currentTarget; btn.disabled = true;
    try {
      const res = await fetch(btn.dataset.url, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json', Accept: 'application/json', 'X-CSRF-TOKEN': f('_token').value },
        body: JSON.stringify({ text }),
      });
      const data = await res.json();
      if (data.ok) { f('location_raw').value = text.slice(0, 500); setLocation(data.lat, data.lng, 'link'); }
      else $('#locStatus').innerHTML = `<span class="text-danger">${data.message || 'อ่านพิกัดไม่ได้'}</span>`;
    } catch (_) {
      $('#locStatus').innerHTML = '<span class="text-danger">เชื่อมต่อไม่ได้ ลองปักหมุดบนแผนที่แทน</span>';
    } finally { btn.disabled = false; }
  });

  /* ---------- ขั้นตอน ---------- */
  function validateStep(i) {
    const err = msg => { steps[i].classList.remove('step-invalid'); void steps[i].offsetWidth; steps[i].classList.add('step-invalid'); toast(msg); return false; };
    if (i === 0 && (!f('lat').value || !f('lng').value)) return err('กรุณาระบุตำแหน่ง: กดตำแหน่งปัจจุบัน ปักบนแผนที่ หรือวางลิงก์');
    if (i === 1 && !form.querySelector('[name=water_level]:checked')) return err('เลือกระดับน้ำ');
    if (i === 2 && !form.querySelector('[name="needs[]"]:checked')) return err('เลือกสิ่งที่ต้องการอย่างน้อย 1 ข้อ');
    if (i === 3) {
      if (!f('requester_name').value.trim()) return err('กรอกชื่อผู้แจ้ง');
      if (f('requester_phone').value.replace(/\D/g, '').length < 9) return err('กรอกเบอร์โทรให้ถูกต้อง');
    }
    return true;
  }

  function show(i) {
    current = Math.max(0, Math.min(i, steps.length - 1));
    steps.forEach((s, n) => s.classList.toggle('d-none', n !== current));
    dots.forEach((d, n) => { d.classList.toggle('active', n === current); d.classList.toggle('done', n < current); });
    btnBack.classList.toggle('d-none', current === 0);
    btnNext.classList.toggle('d-none', current === steps.length - 1);
    btnSubmit.classList.toggle('d-none', current !== steps.length - 1);
    if (current === 0) { initMap(); setTimeout(() => map && map.invalidateSize(), 100); }
    window.scrollTo({ top: 0, behavior: 'smooth' });
  }

  btnNext.addEventListener('click', () => { if (validateStep(current)) show(current + 1); });
  btnBack.addEventListener('click', () => show(current - 1));
  form.addEventListener('submit', e => {
    for (let i = 0; i < steps.length; i++) {
      if (!validateStep(i)) { e.preventDefault(); show(i); return; }
    }
    btnSubmit.disabled = true;
    btnSubmit.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span> กำลังส่ง...';
    clearDraft();
  });

  /* ---------- ตัวช่วยอื่น ---------- */
  $$('[data-step-num]').forEach(b => b.addEventListener('click', () => {
    const input = f('people_count');
    input.value = Math.max(1, Math.min(500, (+input.value || 1) + (+b.dataset.stepNum)));
    saveDraft();
  }));

  const behalf = $('#onBehalf');
  behalf.addEventListener('change', () => $('#behalfFields').classList.toggle('d-none', !behalf.checked));

  $('#photoInput').addEventListener('change', e => {
    const box = $('#photoPreview'); box.innerHTML = '';
    const files = Array.from(e.target.files).slice(0, 3);
    if (e.target.files.length > 3) toast('แนบได้สูงสุด 3 รูป ระบบจะใช้ 3 รูปแรก');
    files.forEach(file => { const img = document.createElement('img'); img.className = 'photo-thumb'; img.src = URL.createObjectURL(file); box.appendChild(img); });
  });

  function toast(msg) {
    let wrap = document.querySelector('.flash-wrap');
    if (!wrap) { wrap = document.createElement('div'); wrap.className = 'flash-wrap'; document.body.appendChild(wrap); }
    const el = document.createElement('div');
    el.className = 'flash error';
    el.innerHTML = `<i class="bi bi-exclamation-circle-fill"></i><div class="flex-grow-1">${msg}</div>`;
    wrap.appendChild(el);
    setTimeout(() => el.remove(), 3500);
  }

  /* ---------- ร่างในเครื่อง (สัญญาณหลุดแล้วกลับมาไม่ต้องกรอกใหม่) ---------- */
  const SKIP = ['_token', 'website', 'photos[]'];
  function saveDraft() {
    try {
      const data = {};
      new FormData(form).forEach((v, k) => {
        if (SKIP.includes(k) || v instanceof File) return;
        if (k.endsWith('[]')) (data[k] = data[k] || []).push(v); else data[k] = v;
      });
      localStorage.setItem(cfg.draftKey, JSON.stringify({ at: Date.now(), data }));
      $('#draftNote').textContent = 'บันทึกร่างไว้ในเครื่องแล้ว';
    } catch (_) {}
  }
  function clearDraft() { try { localStorage.removeItem(cfg.draftKey); } catch (_) {} }
  function loadDraft() {
    if (cfg.hasErrors) return; // มีค่า old() จากเซิร์ฟเวอร์แล้ว
    try {
      const raw = JSON.parse(localStorage.getItem(cfg.draftKey) || 'null');
      if (!raw || Date.now() - raw.at > 6 * 3600 * 1000) return;
      Object.entries(raw.data).forEach(([k, v]) => {
        const els = form.querySelectorAll(`[name="${k}"]`);
        els.forEach(el => {
          if (el.type === 'checkbox' || el.type === 'radio') el.checked = Array.isArray(v) ? v.includes(el.value) : el.value === v;
          else if (el.type !== 'file') el.value = v;
        });
      });
      $('#behalfFields').classList.toggle('d-none', !behalf.checked);
      $('#draftNote').textContent = 'กู้ข้อมูลที่กรอกค้างไว้ให้แล้ว';
    } catch (_) {}
  }
  form.addEventListener('change', saveDraft);

  loadDraft();
  // validate ไม่ผ่านจากเซิร์ฟเวอร์: ไปขั้นแรกที่ยังไม่ครบ
  if (cfg.hasErrors) {
    for (let i = 0; i < steps.length; i++) { if (!validateStep(i)) { show(i); break; } if (i === steps.length - 1) show(i); }
    document.querySelectorAll('.flash-wrap .flash.error').forEach((el, n) => n > 0 && el.remove());
  } else show(0);
})();
