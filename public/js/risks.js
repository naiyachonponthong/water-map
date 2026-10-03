/* จุดเสี่ยงบนแผนที่ ใช้ทั้งหลังบ้าน ศูนย์สั่งการ โหมดทีวี แอปภาคสนาม และเว็บประชาชน
   FloodRisks.layer(map, data, {circles}) -> L.layerGroup
   FloodRisks.admin({...}) หน้าจัดการจุดเสี่ยง */
(function () {
  'use strict';

  const esc = s => String(s ?? '').replace(/[&<>"']/g, c => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]));

  function icon(p) {
    const cls = 'risk-marker' + (p.pending ? ' pending' : '') + (p.status === 'threatened' ? ' threat' : '');
    return L.divIcon({
      className: '', iconSize: [30, 30], iconAnchor: [15, 15],
      html: `<div class="${cls}" style="background:${esc(p.color)}"><i class="bi bi-${esc(p.icon)}"></i></div>`,
    });
  }

  function popup(p) {
    return `<div style="min-width:180px"><b>${esc(p.name)}</b><div class="small text-muted">${esc(p.type)}</div>` +
      (p.status === 'threatened' ? `<div class="small text-danger mt-1"><i class="bi bi-exclamation-octagon"></i> ${esc(p.reason || 'กำลังเตือน')}</div>` : '') +
      (p.pending ? '<div class="small text-warning mt-1">รอเจ้าหน้าที่ตรวจ</div>' : '') +
      (p.description ? `<div class="small mt-1">${esc(p.description)}</div>` : '') + '</div>';
  }

  /** วาดจุดเสี่ยงจาก FeatureCollection */
  function layer(map, data, opts = {}) {
    const group = L.layerGroup();
    const byId = {};
    (data && data.features || []).forEach(f => {
      const p = f.properties || {};
      const items = [];
      const style = { color: p.color, weight: p.status === 'threatened' ? 2.5 : 1.5, fillColor: p.color, fillOpacity: p.status === 'threatened' ? .18 : .08, dashArray: p.pending ? '6 4' : null };
      if (f.geometry && f.geometry.type !== 'Point') {
        items.push(L.geoJSON(f.geometry, { style: () => style }));
      } else if (opts.circles !== false && p.radius) {
        items.push(L.circle([p.lat, p.lng], Object.assign({ radius: p.radius, interactive: false }, style)));
      }
      const m = L.marker([p.lat, p.lng], { icon: icon(p), zIndexOffset: p.status === 'threatened' ? 500 : 0 }).bindPopup(popup(p));
      if (opts.onClick) m.on('click', () => opts.onClick(p));
      items.push(m);
      items.forEach(i => group.addLayer(i));
      byId[p.id] = m;
    });
    group.byId = byId;
    if (map) group.addTo(map);
    return group;
  }

  async function load(map, url, opts = {}) {
    try {
      const res = await fetch(url, { headers: { Accept: 'application/json' } });
      if (!res.ok) return null;
      return layer(map, await res.json(), opts);
    } catch (e) { return null; }
  }

  /* ---------- หน้าจัดการจุดเสี่ยง ---------- */
  function admin(o) {
    const map = FloodMap.create(o.mapEl, { center: o.center, zoom: o.zoom, areasUrl: o.areasUrl, fit: true });
    let risks = null;
    load(map, o.risksUrl).then(g => { risks = g; });

    document.addEventListener('click', e => {
      const a = e.target.closest('[data-focus-risk]');
      if (!a || !risks) return;
      e.preventDefault();
      const m = risks.byId[a.dataset.focusRisk];
      if (m) { map.flyTo(m.getLatLng(), 15); m.openPopup(); o.mapEl.scrollIntoView({ behavior: 'smooth', block: 'nearest' }); }
    });

    // แผนที่ปักหมุด / วาดโซนใน modal
    const form = o.modal.querySelector('form');
    const f = n => form.querySelector(`[name="${n}"]`);
    let pick = null, marker = null, zoneLayer = null, circle = null, drawer = null;

    function radius() { return parseInt(f('radius_m').value, 10) || o.defaultRadius || 1000; }

    function drawCircle() {
      if (circle) { pick.removeLayer(circle); circle = null; }
      if (marker && !zoneLayer) circle = L.circle(marker.getLatLng(), { radius: radius(), color: '#dc2626', weight: 1, fillOpacity: .08, interactive: false }).addTo(pick);
    }

    function setPoint(ll, fly) {
      f('lat').value = ll.lat.toFixed(7); f('lng').value = ll.lng.toFixed(7);
      if (!marker) { marker = L.marker(ll, { draggable: true }).addTo(pick); marker.on('dragend', () => setPoint(marker.getLatLng())); }
      else marker.setLatLng(ll);
      if (fly) pick.setView(ll, 14);
      drawCircle();
    }

    function setZone(geo) {
      if (zoneLayer) { pick.removeLayer(zoneLayer); zoneLayer = null; }
      f('zone').value = geo ? JSON.stringify(geo) : '';
      if (geo) {
        zoneLayer = L.geoJSON(geo, { style: { color: '#dc2626', weight: 2, fillOpacity: .12 } }).addTo(pick);
        const c = zoneLayer.getBounds().getCenter();
        setPoint(c);
        pick.fitBounds(zoneLayer.getBounds(), { padding: [20, 20] });
      }
      drawCircle();
    }

    function sync() {
      const lat = parseFloat(f('lat').value), lng = parseFloat(f('lng').value);
      if (marker) { pick.removeLayer(marker); marker = null; }
      let zone = null;
      try { zone = f('zone').value ? JSON.parse(f('zone').value) : null; } catch (e) { zone = null; }
      if (zone) setZone(zone);
      else {
        setZone(null);
        if (!isNaN(lat) && !isNaN(lng)) setPoint(L.latLng(lat, lng), true);
        else { pick.setView(o.center, o.zoom); drawCircle(); }
      }
    }

    o.modal.addEventListener('shown.bs.modal', () => {
      if (!pick) {
        pick = FloodMap.create(o.pickEl, { center: o.center, zoom: o.zoom, areasUrl: o.areasUrl, fit: false });
        pick.on('click', e => { if (!drawer || !drawer._enabled) { if (zoneLayer) setZone(null); setPoint(e.latlng); } });
        if (L.Draw) {
          if (L.drawLocal) {
            L.drawLocal.draw.handlers.polygon.tooltip = { start: 'คลิกเพื่อเริ่มวาดโซน', cont: 'คลิกเพิ่มมุมต่อไป', end: 'คลิกจุดแรกเพื่อปิดโซน' };
          }
          pick.on(L.Draw.Event.CREATED, e => setZone(e.layer.toGeoJSON().geometry));
        }
      }
      pick.invalidateSize();
      sync();
    });
    f('radius_m').addEventListener('input', drawCircle);

    document.getElementById('rmDraw').addEventListener('click', () => {
      if (!L.Draw || !pick) return;
      drawer = new L.Draw.Polygon(pick, { shapeOptions: { color: '#dc2626' }, allowIntersection: false, showArea: false });
      drawer.enable();
    });
    document.getElementById('rmClearZone').addEventListener('click', () => setZone(null));
  }

  window.FloodRisks = { layer, load, admin };
})();
