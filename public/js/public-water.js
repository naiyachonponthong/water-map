(function () {
  'use strict';
  const page = document.getElementById('waterPage'); if (!page) return;
  const $ = id => document.getElementById(id), F = FloodNear, esc = F.esc;
  const locations = JSON.parse($('waterLocations').textContent);
  let current = locations.find(p => p.slug === page.dataset.slug), context = null, water = null, reports = null, selected = null;
  let version = 0, controller, fitBounds = null;
  const map = window.L ? L.map('waterMap', { zoomControl: true }).setView([13.5, 101], 6) : null;
  if (map) L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', { maxZoom: 18, attribution: '© <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a>' }).addTo(map);
  const areaLayer = map ? L.layerGroup().addTo(map) : null, stationLayer = map ? L.layerGroup().addTo(map) : null;
  const reportLayer = map ? L.layerGroup().addTo(map) : null, locationLayer = map ? L.layerGroup().addTo(map) : null;
  let markers = new Map();
  const nearby = F.mount(page.querySelector('[data-nearby-flood]'), (point, radius) => {
    if (!map) return;
    locationLayer.clearLayers();
    L.circleMarker(point, { radius: 8, color: 'white', weight: 3, fillColor: '#174f65', fillOpacity: 1 })
      .bindTooltip(radius ? 'ตำแหน่งที่ใช้คำนวณในเบราว์เซอร์' : 'จุดอ้างอิงตำบล ไม่ใช่พิกัดบ้าน').addTo(locationLayer);
    if (radius) L.circle(point, { radius, color: '#397f97', weight: 1.5, fillOpacity: .07, dashArray: '5 5' }).addTo(locationLayer);
    map.setView(point, radius ? 12 : 13);
  });
  const number = v => Number.isFinite(v) ? v.toLocaleString('th-TH', { maximumFractionDigits: 2, minimumFractionDigits: 2 }) : '—';
  const time = v => v ? new Date(v).toLocaleString('th-TH', { month: 'short', day: 'numeric', hour: '2-digit', minute: '2-digit', timeZone: 'Asia/Bangkok' }) + ' น.' : '—';
  const bank = s => s.relative_bank === null ? 'ยังไม่มีระดับตลิ่งอ้างอิง' : s.relative_bank > 0 ? `สูงกว่าตลิ่ง ${number(s.relative_bank)} ม.` : s.relative_bank < 0 ? `ต่ำกว่าตลิ่ง ${number(-s.relative_bank)} ม.` : 'เท่าระดับตลิ่ง';
  function icon(s) {
    return L.divIcon({ className: '', iconSize: [42, 42], iconAnchor: [21, 21], html: `<span class="wm-station-marker ${selected === s.id ? 'is-selected' : ''}" style="--station-color:${esc(s.color)}">${Number.isFinite(s.value) ? s.value.toFixed(1) : '—'}</span>` });
  }
  function selectStation(id, pan = true) {
    selected = id;
    const s = water?.stations.find(s => s.id === id); if (!s) return;
    const trend = s.trend === null ? 'ยังไม่มีค่าเปรียบเทียบ' : Math.abs(s.trend) < .01 ? 'ทรงตัว' : `${s.trend > 0 ? 'เพิ่มขึ้น' : 'ลดลง'} ${number(Math.abs(s.trend))} ม. จากค่าก่อนหน้า`;
    $('waterStationDetail').innerHTML = `<div class="wm-detail-title"><div><h2>${esc(s.name || s.code)}</h2><small>${esc([s.subdistrict, s.district].filter(Boolean).join(' · '))}</small></div><span class="wm-badge" style="--station-color:${esc(s.color)}">${esc(s.outdated || water.stale ? 'ข้อมูลเก่า' : s.label)}</span></div><div class="wm-level-value">${number(s.value)}<small>${esc(s.unit || 'ยังไม่มีระดับน้ำ')}</small></div><div class="wm-bank-indicator">${esc(bank(s))}</div><div class="wm-detail-grid"><div><small>แนวโน้ม</small><strong>${esc(trend)}</strong></div><div><small>รหัสสถานี</small><strong>${esc(s.code || s.id)}</strong></div><div><small>เวลาตรวจวัด</small><strong>${esc(time(s.measured_at))}</strong></div><div><small>ระดับตลิ่งอ้างอิง</small><strong>${number(s.bank)} ม. รทก.</strong></div><div><small>ลำน้ำ</small><strong>${esc(s.river || 'ไม่ระบุ')}</strong></div><div><small>หน่วยงานตรวจวัด</small><strong>${esc(s.agency || 'ไม่ระบุ')}</strong></div></div><p class="wm-detail-note">${s.outdated || water.stale ? 'ข้อมูลนี้เก่า / ยังอัปเดตไม่ได้ ห้ามใช้แทนสถานการณ์ปัจจุบัน<br>' : ''}รทก. คือระดับอ้างอิงน้ำทะเลปานกลาง ไม่ใช่ความลึกน้ำที่ท่วมบ้าน · ข้อมูลจาก ThaiWater สสน.</p>`;
    markers.forEach((m, key) => m.setIcon(icon(water.stations.find(x => x.id === key))));
    if (pan && map) map.setView([s.lat, s.lng], Math.max(map.getZoom(), 11));
    drawList();
  }
  function drawList() {
    const query = $('waterSearch').value.trim().toLowerCase();
    const list = (water?.stations || []).filter(s => `${s.name} ${s.code} ${s.district} ${s.subdistrict}`.toLowerCase().includes(query));
    list.sort((a, b) => Number(b.situation === 5 && !b.outdated) - Number(a.situation === 5 && !a.outdated) || a.name.localeCompare(b.name, 'th'));
    $('waterListCount').textContent = list.length + ' สถานี';
    $('waterStationList').innerHTML = list.length ? list.map(s => `<button type="button" data-station="${esc(s.id)}" class="${selected === s.id ? 'is-selected' : ''}" style="--station-color:${esc(s.color)}"><i aria-hidden="true"></i><span><b>${esc(s.name || s.code)}</b><small>${esc(s.district)} · ${esc(s.outdated || water.stale ? 'ข้อมูลเก่า' : s.label)}</small></span><strong>${number(s.value)}<small>${esc(s.unit || 'ไม่มีค่า')}</small></strong></button>`).join('') : '<p>ไม่พบสถานีที่ตรงกับการค้นหา</p>';
  }
  function drawStations() {
    stationLayer?.clearLayers(); markers.clear();
    if (!map || !$('waterStationsToggle').checked) return;
    (water?.stations || []).forEach(s => {
      const marker = L.marker([s.lat, s.lng], { icon: icon(s), title: `${s.name} · ${s.label}`, keyboard: true });
      marker.bindTooltip(`${esc(s.name)}<br>${esc(bank(s))}${s.outdated || water.stale ? '<br>ข้อมูลเก่า' : ''}`, { direction: 'top', offset: [0, -24] });
      marker.on('click', () => selectStation(s.id, false)).addTo(stationLayer);
      marker.getElement()?.setAttribute('aria-label', `สถานี ${s.name} ${number(s.value)} ${s.unit || ''} ${s.outdated || water.stale ? 'ข้อมูลเก่า' : s.label}`);
      markers.set(s.id, marker);
    });
  }
  function drawReports() {
    reportLayer?.clearLayers();
    if (!map || !$('waterReportsToggle').checked) return;
    F.recent(reports?.reports).forEach(r => L.circleMarker([r.lat, r.lng], { radius: 6 + Math.max(0, r.level - 2), color: r.verified ? '#143a58' : '#fff', weight: 2, fillColor: r.color, fillOpacity: .8 })
      .bindPopup(`<b>รายงานน้ำท่วม: ${esc(r.label)}</b><br>${r.trusted ? 'มีการยืนยันในระบบ' : 'ยังไม่มีการยืนยัน'}<br>${esc(time(r.updated_at))}<br><small>รายงานจากพื้นที่ ไม่ใช่สถานี ThaiWater</small>`).addTo(reportLayer));
  }
  function drawBoundary() {
    if (!map) { $('waterMapMessage').textContent = 'โหลดตัวแผนที่ไม่ได้ ข้อมูลสถานียังดูได้จากรายการ'; return; }
    areaLayer.clearLayers(); fitBounds = null;
    const geometry = context?.boundary?.geometry;
    if (geometry) {
      const polygons = geometry.type === 'Polygon' ? [geometry.coordinates] : geometry.coordinates;
      const world = [[-85, -180], [85, -180], [85, 180], [-85, 180]];
      const holes = polygons.map(p => p[0].map(([lng, lat]) => [lat, lng]));
      L.polygon([world, ...holes], { stroke: false, fillColor: '#173d4b', fillOpacity: .38, fillRule: 'evenodd', interactive: false }).addTo(areaLayer);
      polygons.forEach(p => p.slice(1).forEach(r => L.polygon(r.map(([lng, lat]) => [lat, lng]), { stroke: false, fillColor: '#173d4b', fillOpacity: .38, interactive: false }).addTo(areaLayer)));
      L.geoJSON(context.boundary, { style: { color: '#fff', weight: 6, fillOpacity: 0 }, interactive: false }).addTo(areaLayer);
      const outline = L.geoJSON(context.boundary, { style: { color: '#087b78', weight: 3, fillOpacity: 0 }, interactive: false }).addTo(areaLayer);
      fitBounds = outline.getBounds(); map.fitBounds(fitBounds.pad(.12)); $('waterMapMessage').hidden = true;
    } else {
      if (context?.center) map.setView(context.center, 8);
      $('waterMapMessage').hidden = false; $('waterMapMessage').textContent = 'ยังไม่มีขอบเขตอ้างอิงจังหวัดนี้';
    }
  }
  async function json(url, signal) {
    const res = await fetch(url, { signal, headers: { Accept: 'application/json' } }); if (!res.ok) throw Error('Unavailable'); return res.json();
  }
  function reset() {
    context = water = reports = null; selected = null; fitBounds = null; nearby.reset();
    [areaLayer, stationLayer, reportLayer, locationLayer].forEach(l => l?.clearLayers()); markers.clear();
    ['waterStationCount', 'waterOverflowCount', 'waterReportCount', 'waterLatest', 'waterListCount'].forEach(id => $(id).textContent = '—');
    $('waterStationDetail').innerHTML = '<div class="wm-detail-empty"><i class="bi bi-cursor"></i><h2>เลือกสถานีบนแผนที่</h2><p>ระดับน้ำ เทียบตลิ่ง และเวลาตรวจวัด</p></div>';
    $('waterStationList').textContent = 'กำลังโหลดสถานี…'; $('waterSearch').value = ''; $('waterMapMessage').hidden = false; $('waterMapMessage').textContent = 'กำลังโหลดขอบเขตจังหวัด…';
  }
  async function load(change = false) {
    controller?.abort(); controller = new AbortController(); const signal = controller.signal, sequence = ++version, location = current;
    if (change) reset();
    $('waterStatus').textContent = 'กำลังโหลดข้อมูล ThaiWater…';
    const alive = () => sequence === version;
    const tasks = [
      json(location.contextUrl, signal).then(data => { if (!alive()) return; const hadBoundary = !!context; context = data; nearby.setContext(data); if (change || !hadBoundary) drawBoundary(); $('waterBoundaryNote').textContent = data.boundary_source; }).catch(e => { if (!alive() || e.name === 'AbortError') return; nearby.contextError(); $('waterMapMessage').hidden = false; $('waterMapMessage').textContent = 'ยังโหลดขอบเขตไม่ได้ กดลองอัปเดตอีกครั้ง'; }),
      json(location.dataUrl, signal).then(data => {
        if (!alive()) return; water = data;
        $('waterStationCount').textContent = data.stations.length;
        $('waterOverflowCount').textContent = data.stale ? '—' : data.stations.filter(s => s.situation === 5 && !s.outdated).length;
        const times = data.stations.map(s => Date.parse(s.measured_at)).filter(Number.isFinite);
        $('waterLatest').textContent = times.length ? time(new Date(Math.max(...times))) : '—';
        $('waterStatus').textContent = `${data.stale ? 'ข้อมูลเก่า · อัปเดตต้นทางไม่ได้ · ' : ''}ดึงข้อมูล ${time(data.fetched_at)}${!data.stations.length ? ' · ไม่พบสถานีของจังหวัดนี้ในชุดข้อมูลต้นทาง' : ''}`;
        drawStations(); drawList(); if (selected) selectStation(selected, false);
      }).catch(e => { if (!alive() || e.name === 'AbortError') return; water = null; selected = null; stationLayer?.clearLayers(); markers.clear(); ['waterStationCount', 'waterOverflowCount', 'waterLatest'].forEach(id => $(id).textContent = '—'); $('waterStatus').textContent = 'ยังเชื่อมข้อมูล ThaiWater ไม่ได้ กดอัปเดตเพื่อลองใหม่'; $('waterStationList').textContent = 'ยังโหลดรายการไม่ได้ สามารถเปิด ThaiWater ต้นทางได้'; $('waterStationDetail').textContent = 'ยังโหลดรายละเอียดสถานีไม่ได้'; }),
      json(location.reportsUrl, signal).then(data => { if (!alive()) return; reports = data; nearby.setReports(data); $('waterReportCount').textContent = F.recent(data.reports).length + (data.truncated ? '+' : ''); drawReports(); }).catch(e => { if (!alive() || e.name === 'AbortError') return; reports = null; reportLayer?.clearLayers(); $('waterReportCount').textContent = '—'; nearby.setReports(null, true); })
    ];
    await Promise.all(tasks);
  }
  $('waterProvince').addEventListener('change', () => {
    current = locations.find(p => p.slug === $('waterProvince').value); if (!current) return;
    ['waterTitle', 'waterMapTitle', 'waterMapChip'].forEach(id => $(id).textContent = current.name);
    page.dataset.slug = current.slug;
    $('waterHome').href = current.homeUrl; $('waterReportsLink').href = current.homeUrl + '/map';
    page.querySelector('[data-near-map]').href = current.url + '#nearby';
    history.replaceState(null, '', current.url); document.title = `แผนที่ระดับน้ำ ${current.name} | ศูนย์ช่วยเหลือน้ำท่วม`; load(true);
  });
  $('waterRefresh').addEventListener('click', () => load(false));
  $('waterFit').addEventListener('click', () => { if (fitBounds && map) map.fitBounds(fitBounds.pad(.12)); });
  $('waterStationsToggle').addEventListener('change', drawStations); $('waterReportsToggle').addEventListener('change', drawReports);
  $('waterSearch').addEventListener('input', drawList);
  $('waterStationList').addEventListener('click', e => { const b = e.target.closest('[data-station]'); if (b) selectStation(b.dataset.station); });
  load(true); setInterval(() => { if (!document.hidden) load(false); }, 300000);
})();
