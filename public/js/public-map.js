/* แผนที่สถานการณ์น้ำสำหรับประชาชน: รายงานระดับน้ำ + จุดเสี่ยง + โหวตยืนยัน */
(function () {
  'use strict';

  const esc = s => String(s ?? '').replace(/[&<>"']/g, c => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]));
  const token = () => (document.querySelector('meta[name=csrf-token]') || {}).content || '';

  function reportIcon(p) {
    const s = 14 + p.level * 3;
    const ring = p.verified ? 'box-shadow:0 0 0 3px #fff,0 0 0 5px #1d4ed8;' : 'box-shadow:0 0 0 2px #fff,0 1px 4px rgba(0,0,0,.35);';
    return L.divIcon({
      className: '', iconSize: [s, s], iconAnchor: [s / 2, s / 2],
      html: `<div style="width:${s}px;height:${s}px;border-radius:50%;background:${esc(p.color)};opacity:${p.opacity};${ring}"></div>`,
    });
  }

  function popup(p, votes) {
    const photos = (p.photos || []).map(u => `<a href="${esc(u)}" target="_blank" rel="noopener"><img src="${esc(u)}" alt="" style="width:64px;height:64px;object-fit:cover;border-radius:8px"></a>`).join(' ');
    return `<div class="pm-pop" data-id="${p.id}">
      <div class="d-flex align-items-center gap-2 mb-1"><span class="lv-dot" style="background:${esc(p.color)};width:12px;height:12px"></span><b>น้ำ${esc(p.label)}</b>${p.verified ? ' <span class="chip chip-primary" style="font-size:.7rem">ยืนยันแล้ว</span>' : ''}</div>
      <div class="small text-muted">${esc(p.area)} · ${esc(p.ago)} · ${esc(p.source)}</div>
      ${p.trend || p.place ? `<div class="small">${[p.place, p.trend].filter(Boolean).map(esc).join(' · ')}</div>` : ''}
      ${p.note ? `<div class="small mt-1">${esc(p.note)}</div>` : ''}
      ${photos ? `<div class="mt-2 d-flex gap-1 flex-wrap">${photos}</div>` : ''}
      <div class="small text-muted mt-2">ยืนยัน <b class="pm-c">${p.confirm}</b> · แจ้งน้ำลด <b class="pm-r">${p.receded}</b></div>
      <div class="pm-votes mt-1">${Object.entries(votes).map(([k, l]) => `<button type="button" class="btn btn-sm ${k === 'confirm' ? 'btn-primary' : (k === 'receded' ? 'btn-success' : 'btn-light')}" data-vote="${k}">${esc(l)}</button>`).join('')}</div>
      <div class="pm-msg small mt-1"></div>
    </div>`;
  }

  function init(o) {
    const map = FloodMap.create(o.el, { center: o.center, zoom: o.zoom });
    const water = window.FloodSituationWater?.init(map, { contextUrl: o.contextUrl, waterUrl: o.waterUrl, onBoundary: () => focusReport() });
    if (!map) { document.getElementById('pmDataStatus').textContent = 'โหลดตัวแผนที่ไม่ได้ ลองอัปเดตหน้าอีกครั้ง'; return null; }
    const reportLayer = L.layerGroup().addTo(map);
    let riskLayer = null, stationLayer = null, cameraLayer = null;
    let showStations = true, showCameras = true, showShelters = true, shelterLayer = null;
    const hiddenLevels = new Set();
    let showRisks = true, fitted = false, lastData = null;
    const markers = {};
    function focusReport() {
      if (o.focus && markers[o.focus]) { map.setView(markers[o.focus].getLatLng(), 15); markers[o.focus].openPopup(); }
    }

    function draw(d) {
      lastData = d;
      reportLayer.clearLayers();
      Object.keys(markers).forEach(k => delete markers[k]);
      (d.reports.features || []).forEach(f => {
        const p = f.properties;
        if (hiddenLevels.has(String(p.level))) return;
        const [lng, lat] = f.geometry.coordinates;
        const m = L.marker([lat, lng], { icon: reportIcon(p), zIndexOffset: p.level * 10 }).bindPopup(popup(p, o.votes), { maxWidth: 260 });
        m.addTo(reportLayer);
        markers[p.id] = m;
      });
      if (riskLayer) map.removeLayer(riskLayer);
      riskLayer = showRisks && window.FloodRisks ? FloodRisks.layer(map, d.risks, { circles: false }) : null;
      if (stationLayer) map.removeLayer(stationLayer);
      stationLayer = showStations && window.FloodStations && d.stations ? FloodStations.layer(map, d.stations) : null;
      if (shelterLayer) map.removeLayer(shelterLayer);
      shelterLayer = null;
      if (showShelters && d.shelters) {
        shelterLayer = L.layerGroup().addTo(map);
        d.shelters.forEach(s => L.marker([s.lat, s.lng], { icon: L.divIcon({ className: '', iconSize: [28, 28], iconAnchor: [14, 14], html: `<div class="risk-marker" style="background:${esc(s.color)};width:28px;height:28px"><i class="bi bi-house-heart-fill"></i></div>` }) })
          .bindPopup(`<b>${esc(s.name)}</b><br>${esc(s.status)}${s.available != null ? ' · ว่าง ' + s.available + ' ที่' : ''}${s.phone ? '<br>โทร ' + esc(s.phone) : ''}<br><a target="_blank" rel="noopener" href="https://www.google.com/maps/dir/?api=1&destination=${s.lat},${s.lng}">นำทาง</a>`).addTo(shelterLayer));
      }
      if (cameraLayer) map.removeLayer(cameraLayer);
      cameraLayer = showCameras && window.FloodStations && d.cameras ? FloodStations.cameraLayer(map, d.cameras) : null;

      if (!fitted) {
        fitted = true;
        focusReport();
      }
      const t = document.getElementById('pmTime'); if (t && d.time) t.textContent = d.time;
      const n = document.getElementById('pmReports'); if (n) n.textContent = d.reports.features.length;
      const deep = document.getElementById('pmDeep'); if (deep) deep.textContent = d.reports.features.filter(f => f.properties.level >= 4).length;
      document.getElementById('pmRiskCount').textContent = d.risks.features.filter(f => f.properties.status === 'threatened').length;
      document.getElementById('pmDataStatus').textContent = `รายงานอัปเดต ${d.time || '—'} น. · ${d.shelters?.length || 0} ศูนย์พักพิง · ไม่พบรายงานไม่ได้แปลว่าปลอดภัย`;
    }

    async function load() {
      try {
        const res = await fetch(o.dataUrl, { headers: { Accept: 'application/json' } });
        if (!res.ok) throw Error('Unavailable');
        draw(await res.json());
      } catch (_) {
        document.getElementById('pmDataStatus').textContent = 'ยังอัปเดตรายงานไม่ได้ ข้อมูลเดิมอาจเก่า กดลองอัปเดตอีกครั้ง';
        ['pmReports', 'pmDeep', 'pmRiskCount'].forEach(id => document.getElementById(id).textContent = '—');
      }
    }
    load();
    document.getElementById('pmRefresh').addEventListener('click', () => { load(); water?.load(); });
    document.getElementById('pmProvince').addEventListener('change', e => { window.location.assign(e.target.value); });
    setInterval(() => { if (!document.hidden && !document.querySelector('.leaflet-popup')) load(); }, 60000);

    // ตัวกรองระดับ / ชั้นจุดเสี่ยง
    document.querySelectorAll('.pmap-legend input').forEach(cb => cb.addEventListener('change', () => {
      if (cb.dataset.level) cb.checked ? hiddenLevels.delete(cb.dataset.level) : hiddenLevels.add(cb.dataset.level);
      if (cb.dataset.layer === 'risks') showRisks = cb.checked;
      if (cb.dataset.layer === 'stations') showStations = cb.checked;
      if (cb.dataset.layer === 'cameras') showCameras = cb.checked;
      if (cb.dataset.layer === 'shelters') showShelters = cb.checked;
      if (cb.dataset.layer === 'thaiwater') { water?.toggle(cb.checked); return; }
      if (lastData) draw(lastData);
    }));

    // โหวตในป๊อปอัป
    o.el.addEventListener('click', async e => {
      const b = e.target.closest('[data-vote]');
      if (!b) return;
      const box = b.closest('.pm-pop');
      const msg = box.querySelector('.pm-msg');
      box.querySelectorAll('[data-vote]').forEach(x => (x.disabled = true));
      try {
        const res = await fetch(o.voteUrl.replace('__ID__', box.dataset.id), {
          method: 'POST',
          headers: { 'Content-Type': 'application/json', Accept: 'application/json', 'X-CSRF-TOKEN': token() },
          body: JSON.stringify({ kind: b.dataset.vote }),
        });
        const d = await res.json().catch(() => ({}));
        msg.className = 'pm-msg small mt-1 ' + (d.ok ? 'text-success' : 'text-danger');
        msg.textContent = d.message || (res.status === 429 ? 'กดถี่เกินไป รอสักครู่' : 'ส่งไม่สำเร็จ');
        if (d.confirm !== undefined) { box.querySelector('.pm-c').textContent = d.confirm; box.querySelector('.pm-r').textContent = d.receded; }
      } catch (_) {
        msg.className = 'pm-msg small mt-1 text-danger'; msg.textContent = 'ไม่มีอินเทอร์เน็ต';
        box.querySelectorAll('[data-vote]').forEach(x => (x.disabled = false));
      }
    });

    return map;
  }

  window.FloodPublicMap = { init };
})();
