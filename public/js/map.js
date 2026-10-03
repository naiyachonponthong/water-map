/* แผนที่ด้วย Leaflet: ใช้ร่วมกันทุกหน้า
   FloodMap.create(el, {center:[lat,lng], zoom, areasUrl, onAreaClick}) */
(function () {
  'use strict';

  const TILE = 'https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png';
  const ATTR = '&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a>';

  async function loadAreas(map, url, opts = {}) {
    const res = await fetch(url, { headers: { Accept: 'application/json' } });
    if (!res.ok) return null;
    const data = await res.json();
    if (!data.features || !data.features.length) return null;

    const primary = getComputedStyle(document.documentElement).getPropertyValue('--sb-primary').trim() || '#1565C0';
    const layer = L.geoJSON(data, {
      style: () => ({ color: primary, weight: opts.weight || 1.5, fillColor: primary, fillOpacity: .06 }),
      onEachFeature: (f, l) => {
        l.bindTooltip(f.properties.name, { sticky: true, direction: 'top' });
        l.on('mouseover', () => l.setStyle({ fillOpacity: .18 }));
        l.on('mouseout', () => l.setStyle({ fillOpacity: .06 }));
        if (opts.onAreaClick) l.on('click', () => opts.onAreaClick(f.properties));
      },
    }).addTo(map);
    if (opts.fit !== false) map.fitBounds(layer.getBounds(), { padding: [16, 16] });

    return layer;
  }

  function create(el, opts = {}) {
    if (!window.L || !el) return null;
    const center = opts.center && opts.center[0] ? opts.center : [13.7563, 100.5018];
    const map = L.map(el, { zoomControl: true, attributionControl: true }).setView(center, opts.zoom || 10);
    L.tileLayer(TILE, { maxZoom: 19, attribution: ATTR }).addTo(map);

    if (opts.areasUrl) loadAreas(map, opts.areasUrl, opts).then(layer => { map._areas = layer; });

    // แผนที่ใน modal/แท็บ ต้องคำนวณขนาดใหม่เมื่อแสดง
    setTimeout(() => map.invalidateSize(), 200);
    return map;
  }

  /** ช่องเลือกพิกัด: คลิกบนแผนที่แล้วใส่ค่าในช่อง lat/lng */
  function picker(el, latInput, lngInput, opts = {}) {
    const lat = parseFloat(latInput.value), lng = parseFloat(lngInput.value);
    const has = !isNaN(lat) && !isNaN(lng);
    const map = create(el, { center: has ? [lat, lng] : opts.center, zoom: has ? 12 : (opts.zoom || 9), areasUrl: opts.areasUrl, fit: !has });
    if (!map) return null;
    let marker = has ? L.marker([lat, lng], { draggable: true }).addTo(map) : null;

    const set = ll => {
      latInput.value = ll.lat.toFixed(7); lngInput.value = ll.lng.toFixed(7);
      if (!marker) { marker = L.marker(ll, { draggable: true }).addTo(map); marker.on('dragend', () => set(marker.getLatLng())); }
      else marker.setLatLng(ll);
    };
    if (marker) marker.on('dragend', () => set(marker.getLatLng()));
    map.on('click', e => set(e.latlng));
    return map;
  }

  window.FloodMap = { create, picker, loadAreas };
})();
