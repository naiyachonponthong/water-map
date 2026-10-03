/* สถานีวัดน้ำ + กล้อง CCTV บนแผนที่ ใช้ทั้งหลังบ้าน แดชบอร์ด และเว็บประชาชน
   FloodStations.layer(map, featureCollection, {link})
   FloodStations.cameraLayer(map, cameras)
   FloodStations.mountCamera(el, cam)  แสดงภาพกล้องในกล่อง (รีเฟรชภาพนิ่ง / HLS / iframe) */
(function () {
  'use strict';

  const esc = s => String(s ?? '').replace(/[&<>"']/g, c => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]));
  const safeUrl = u => /^https?:\/\//i.test(String(u || '')) ? String(u) : '';

  function stationIcon(p) {
    const crit = p.status === 'critical' ? ' crit' : '';
    return L.divIcon({ className: '', iconSize: [22, 22], iconAnchor: [11, 11], html: `<div class="station-marker${crit}" style="background:${esc(p.color)}"></div>` });
  }

  function stationPopup(p, opts) {
    const bank = p.to_bank == null ? '' : (p.to_bank >= 0 ? `<span class="text-danger">เกินตลิ่ง +${Number(p.to_bank).toFixed(2)} ม.</span>` : `ต่ำกว่าตลิ่ง ${Math.abs(p.to_bank).toFixed(2)} ม.`);
    return `<div style="min-width:190px"><b>${esc(p.name)}</b>${p.river ? `<div class="small text-muted">ลำน้ำ${esc(p.river)}</div>` : ''}
      <div class="mt-1"><span class="chip" style="background:${esc(p.color)};color:#fff">${esc(p.status_label)}</span></div>
      ${p.value != null ? `<div class="mt-1"><b class="mono">${Number(p.value).toFixed(2)}</b> ${esc(p.unit)} <span class="small text-muted">${esc(p.at || '')}</span></div>` : '<div class="small text-muted mt-1">ยังไม่มีค่า</div>'}
      ${bank ? `<div class="small">${bank}</div>` : ''}${p.trend ? `<div class="small">${esc(p.trend)}</div>` : ''}
      ${opts.link ? `<a href="${esc(opts.link(p.id))}" class="btn btn-sm btn-primary w-100 mt-2 text-white">ดูกราฟ</a>` : (safeUrl(p.link) ? `<a href="${esc(safeUrl(p.link))}" target="_blank" rel="noopener" class="small">ข้อมูลต้นทาง</a>` : '')}
    </div>`;
  }

  function layer(map, data, opts = {}) {
    const group = L.featureGroup();
    (data && data.features || []).forEach(f => {
      const p = f.properties, [lng, lat] = f.geometry.coordinates;
      L.marker([lat, lng], { icon: stationIcon(p), zIndexOffset: 400 }).bindPopup(stationPopup(p, opts)).addTo(group);
    });
    if (map) group.addTo(map);
    return group;
  }

  /* ---------- กล้อง ---------- */
  const timers = new WeakMap();

  function mountCamera(el, cam) {
    unmount(el);
    const url = safeUrl(cam.url);
    if (!url) { el.innerHTML = '<div class="cam-off">URL ไม่ถูกต้อง</div>'; return; }
    if (cam.type === 'image') {
      const img = document.createElement('img');
      img.alt = cam.name || '';
      img.referrerPolicy = 'no-referrer';
      const load = () => { img.src = url + (url.includes('?') ? '&' : '?') + '_t=' + Date.now(); };
      img.onerror = () => { el.querySelector('.cam-off') || el.insertAdjacentHTML('beforeend', '<div class="cam-off">ภาพไม่พร้อม</div>'); };
      img.onload = () => { const o = el.querySelector('.cam-off'); if (o) o.remove(); };
      el.prepend(img); load();
      timers.set(el, setInterval(() => { if (!document.hidden) load(); }, Math.max(10, cam.refresh || 60) * 1000));
    } else if (cam.type === 'hls') {
      const v = document.createElement('video');
      v.muted = true; v.autoplay = true; v.playsInline = true; v.controls = true;
      el.prepend(v);
      if (v.canPlayType('application/vnd.apple.mpegurl')) v.src = url;
      else if (window.Hls && Hls.isSupported()) { const h = new Hls(); h.loadSource(url); h.attachMedia(v); timers.set(el, { hls: h }); }
      else el.insertAdjacentHTML('beforeend', `<div class="cam-off"><a href="${esc(url)}" target="_blank" rel="noopener">เปิดวิดีโอ</a></div>`);
    } else if (cam.type === 'iframe') {
      const f = document.createElement('iframe');
      f.src = url; f.loading = 'lazy'; f.referrerPolicy = 'no-referrer';
      f.setAttribute('sandbox', 'allow-scripts allow-same-origin');
      f.setAttribute('allow', 'autoplay; fullscreen');
      el.prepend(f);
    } else {
      el.insertAdjacentHTML('afterbegin', `<div class="cam-off"><a class="btn btn-sm btn-light" href="${esc(url)}" target="_blank" rel="noopener"><i class="bi bi-box-arrow-up-right"></i> เปิดภาพกล้อง</a></div>`);
    }
  }

  function unmount(el) {
    const t = timers.get(el);
    if (t) { if (t.hls) t.hls.destroy(); else clearInterval(t); timers.delete(el); }
    el.querySelectorAll('img,video,iframe,.cam-off').forEach(n => n.remove());
  }

  function cameraLayer(map, cams) {
    const group = L.featureGroup();
    (cams || []).forEach(c => {
      const m = L.marker([c.lat, c.lng], {
        icon: L.divIcon({ className: '', iconSize: [26, 26], iconAnchor: [13, 13], html: '<div class="risk-marker" style="background:#0f172a;width:26px;height:26px"><i class="bi bi-camera-video-fill"></i></div>' }),
      });
      m.bindPopup(`<div style="width:260px"><b>${esc(c.name)}</b>${c.owner ? `<div class="small text-muted">${esc(c.owner)}</div>` : ''}<div class="cam-tile mt-1" data-cam></div></div>`, { maxWidth: 280 });
      m.on('popupopen', e => { const box = e.popup.getElement().querySelector('[data-cam]'); if (box) mountCamera(box, c); });
      m.on('popupclose', e => { const box = e.popup.getElement() && e.popup.getElement().querySelector('[data-cam]'); if (box) unmount(box); });
      m.addTo(group);
    });
    if (map) group.addTo(map);
    return group;
  }

  window.FloodStations = { layer, cameraLayer, mountCamera, unmount };
})();
