/* Public ThaiWater layer. Reference boundary and MSL are not flood-depth evidence. */
(function (root) {
  'use strict';
  const esc = value => String(value ?? '').replace(/[&<>"']/g, c => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]));
  const number = value => Number.isFinite(value) ? value.toLocaleString('th-TH', { maximumFractionDigits: 2, minimumFractionDigits: 2 }) : '—';
  const time = value => value ? new Date(value).toLocaleString('th-TH', { month: 'short', day: 'numeric', hour: '2-digit', minute: '2-digit', timeZone: 'Asia/Bangkok' }) + ' น.' : '—';
  const bank = s => !Number.isFinite(s.relative_bank) ? 'ยังไม่มีตลิ่งอ้างอิงที่เทียบได้' : s.relative_bank > 0 ? `สูงกว่าตลิ่ง ${number(s.relative_bank)} ม.` : s.relative_bank < 0 ? `ต่ำกว่าตลิ่ง ${number(-s.relative_bank)} ม.` : 'เท่าระดับตลิ่ง';
  function popup(s, stale) {
    const old = stale || s.outdated;
    const trend = !Number.isFinite(s.trend) ? 'ยังไม่มีค่าเปรียบเทียบ' : Math.abs(s.trend) < .01 ? 'ทรงตัว' : `${s.trend > 0 ? 'เพิ่มขึ้น' : 'ลดลง'} ${number(Math.abs(s.trend))} ม.`;
    return `<div class="sm-water-pop"><h3>${esc(s.name || s.code)}</h3><p>${esc([s.subdistrict, s.district].filter(Boolean).join(' · '))}</p><span class="sm-pop-status" style="--tone:${esc(old ? '#87949c' : s.color)}">${esc(old ? 'ข้อมูลเก่า' : s.label)}</span><div class="sm-pop-value">${number(s.value)}<small>${esc(s.unit || 'ไม่มีค่าระดับน้ำ')}</small></div><p><b>${esc(bank(s))}</b></p><dl><dt>แนวโน้ม</dt><dd>${esc(trend)}</dd><dt>เวลาวัด</dt><dd>${esc(time(s.measured_at))}</dd><dt>ลำน้ำ</dt><dd>${esc(s.river || 'ไม่ระบุ')}</dd><dt>หน่วยงาน</dt><dd>${esc(s.agency || 'ไม่ระบุ')}</dd><dt>รหัสสถานี</dt><dd>${esc(s.code || s.id)}</dd></dl><footer>${old ? 'ข้อมูลเก่า ไม่ใช้แทนสถานการณ์ปัจจุบัน · ' : ''}ม. รทก. คือระดับอ้างอิงน้ำทะเลปานกลาง ไม่ใช่ความลึกน้ำท่วมบ้าน<br>ข้อมูล: ThaiWater สสน.</footer></div>`;
  }
  function init(map, opts) {
    const $ = id => document.getElementById(id);
    const layer = map ? L.layerGroup().addTo(map) : null;
    const areas = map ? L.layerGroup().addTo(map) : null;
    let water = null, bounds = null, contextLoaded = false, shown = true, busy = false;
    const markers = new Map();
    if (map && typeof ResizeObserver !== 'undefined') {
      const resize = new ResizeObserver(() => map.invalidateSize({ pan: false }));
      resize.observe(map.getContainer());
    }
    const message = text => { $('pmBoundaryMessage').hidden = !text; $('pmBoundaryMessage').textContent = text || ''; };
    function drawList() {
      if (!water) return;
      const query = $('pmWaterSearch').value.trim().toLowerCase();
      const stations = water.stations.filter(s => `${s.name} ${s.code} ${s.district} ${s.subdistrict}`.toLowerCase().includes(query));
      $('pmWaterList').innerHTML = stations.length ? stations.map(s => `<button type="button" data-water-id="${esc(s.id)}" style="--tone:${esc(water.stale || s.outdated ? '#87949c' : s.color)}"><i></i><span><b>${esc(s.name || s.code)}</b><small>${esc(bank(s))} · ${esc(water.stale || s.outdated ? 'ข้อมูลเก่า' : s.label)}</small></span><strong>${number(s.value)}<small>${esc(s.unit || 'ไม่มีค่า')}</small></strong></button>`).join('') : '<p>ไม่พบสถานีที่ตรงกับการค้นหา</p>';
    }
    function drawStations() {
      layer?.clearLayers(); markers.clear();
      if (!map || !shown || !water) return;
      water.stations.forEach(s => {
        const old = water.stale || s.outdated, color = old ? '#87949c' : s.color;
        const marker = L.marker([s.lat, s.lng], { keyboard: true, title: `${s.name} · ${old ? 'ข้อมูลเก่า' : s.label}`, icon: L.divIcon({ className: '', iconSize: [42, 42], iconAnchor: [21, 21], html: `<span class="sm-water-marker" style="--tone:${esc(color)}">${Number.isFinite(s.value) ? s.value.toFixed(1) : '—'}</span>` }) })
          .bindPopup(popup(s, water.stale), { maxWidth: 300 }).bindTooltip(`${esc(s.name)}<br>${esc(bank(s))}`, { direction: 'top', offset: [0, -20] }).addTo(layer);
        marker.getElement()?.setAttribute('aria-label', `สถานี ${s.name} ${number(s.value)} ${s.unit || ''} ${old ? 'ข้อมูลเก่า' : s.label}`);
        markers.set(String(s.id), marker);
      });
    }
    function drawContext(data) {
      contextLoaded = true;
      if (!map) { message('โหลดตัวแผนที่ไม่ได้ ยังดูข้อมูลสถานีจากรายการด้านข้างได้'); return; }
      areas.clearLayers(); bounds = null;
      const g = data.boundary?.geometry;
      if (!g) { if (data.center) map.setView(data.center, 8); message('ยังไม่มีขอบเขตอ้างอิงจังหวัดนี้'); return; }
      const polygons = g.type === 'Polygon' ? [g.coordinates] : g.coordinates;
      const world = [[-85, -180], [85, -180], [85, 180], [-85, 180]];
      L.polygon([world, ...polygons.map(p => p[0].map(([lng, lat]) => [lat, lng]))], { stroke: false, fillColor: '#173d4b', fillOpacity: .38, fillRule: 'evenodd', interactive: false }).addTo(areas);
      polygons.forEach(p => p.slice(1).forEach(r => L.polygon(r.map(([lng, lat]) => [lat, lng]), { stroke: false, fillColor: '#173d4b', fillOpacity: .38, interactive: false }).addTo(areas)));
      L.geoJSON(data.boundary, { style: { color: '#fff', weight: 6, fillOpacity: 0 }, interactive: false }).addTo(areas);
      const outline = L.geoJSON(data.boundary, { style: { color: '#087b78', weight: 3, fillOpacity: 0 }, interactive: false }).addTo(areas);
      bounds = outline.getBounds(); map.fitBounds(bounds.pad(.12)); message('');
      opts.onBoundary?.(); $('pmBoundarySource').textContent = data.boundary_source;
    }
    async function json(url) {
      const controller = new AbortController(); const timeout = setTimeout(() => controller.abort(), 22000);
      try { const response = await fetch(url, { signal: controller.signal, headers: { Accept: 'application/json' } }); if (!response.ok) throw Error('Unavailable'); return await response.json(); }
      finally { clearTimeout(timeout); }
    }
    async function load() {
      if (busy) return; busy = true;
      $('pmWaterStatus').textContent = 'กำลังโหลดข้อมูล ThaiWater…';
      await Promise.all([
        contextLoaded ? Promise.resolve() : json(opts.contextUrl).then(drawContext).catch(() => message('ยังโหลดขอบเขตไม่ได้ กดอัปเดตเพื่อลองใหม่')),
        json(opts.waterUrl).then(data => {
          water = data; $('pmWaterCount').textContent = data.stations.length;
          $('pmWaterStatus').textContent = `${data.stale ? 'ข้อมูลเก่า · ต้นทางยังอัปเดตไม่ได้ · ' : ''}ดึงข้อมูล ${time(data.fetched_at)}${data.stations.length ? '' : ' · ต้นทางยังไม่มีสถานีในจังหวัดนี้'}`;
          drawStations(); drawList();
        }).catch(() => {
          water = null; layer?.clearLayers(); markers.clear(); $('pmWaterCount').textContent = '—';
          $('pmWaterStatus').textContent = 'ยังเชื่อม ThaiWater ไม่ได้ กดอัปเดตเพื่อลองใหม่'; $('pmWaterList').textContent = 'ข้อมูลไม่พร้อม ไม่สามารถสรุปว่าระดับน้ำเป็นปกติ';
        })
      ]); busy = false;
    }
    $('pmFit').addEventListener('click', () => { if (bounds && map) map.fitBounds(bounds.pad(.12)); });
    $('pmWaterSearch').addEventListener('input', drawList);
    $('pmWaterList').addEventListener('click', e => { const button = e.target.closest('[data-water-id]'); if (!button) return; const marker = markers.get(button.dataset.waterId); if (marker && map) { map.setView(marker.getLatLng(), Math.max(map.getZoom(), 11)); marker.openPopup(); } else if (!shown) $('pmWaterStatus').textContent = 'เปิดชั้นระดับน้ำ ThaiWater เพื่อเลือกสถานีบนแผนที่'; });
    load(); setInterval(() => { if (!document.hidden && !document.querySelector('.leaflet-popup')) load(); }, 300000);
    return { load, toggle(value) { shown = value; drawStations(); } };
  }
  const api = { init, popup, bank, number }; root.FloodSituationWater = api;
  if (typeof module !== 'undefined') module.exports = api;
})(typeof window !== 'undefined' ? window : globalThis);
