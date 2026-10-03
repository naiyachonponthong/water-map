/* หน้าเคส: แผนที่เคสที่เปิดอยู่ + แจ้งเตือนเคสใหม่ด้วยการ poll (เฟสศูนย์สั่งการเปลี่ยนเป็น realtime) */
(function () {
  'use strict';

  function caseIcon(color, big) {
    const s = big ? 24 : 18;
    return L.divIcon({ className: '', html: `<div class="case-marker" style="background:${color};width:${s}px;height:${s}px"></div>`, iconSize: [s, s], iconAnchor: [s / 2, s / 2] });
  }

  async function loadCases(map, url, layer) {
    const res = await fetch(url, { headers: { Accept: 'application/json' } });
    if (!res.ok) return;
    const data = await res.json();
    layer.clearLayers();
    data.features.forEach(f => {
      const p = f.properties, [lng, lat] = f.geometry.coordinates;
      L.marker([lat, lng], { icon: caseIcon(p.color, p.priority === 'critical'), zIndexOffset: p.score })
        .bindPopup(`<div style="min-width:180px"><div class="small text-muted mono">${p.code}</div><b>${p.name}</b>
          <div class="small">${p.status} · ${p.priority_label} ${p.score}</div>
          <div class="small">น้ำ${p.water} · ${p.people} คน · ${p.ago}</div>
          <a href="${p.url}" class="btn btn-sm btn-primary mt-2 w-100 text-white">เปิดเคส</a></div>`)
        .addTo(layer);
    });
    return data.features.length;
  }

  function initMap(el, opts) {
    if (!el || !window.FloodMap) return null;
    const map = FloodMap.create(el, { center: opts.center, zoom: opts.zoom, areasUrl: opts.areasUrl, fit: false });
    const layer = L.layerGroup().addTo(map);
    loadCases(map, opts.casesUrl, layer).then(n => {
      if (n) { const b = L.featureGroup(layer.getLayers()).getBounds(); if (b.isValid()) map.fitBounds(b.pad(0.2), { maxZoom: 14 }); }
    });
    setInterval(() => loadCases(map, opts.casesUrl, layer), 30000);
    return map;
  }

  function beep() {
    try {
      const ctx = new (window.AudioContext || window.webkitAudioContext)();
      [0, 0.25].forEach(t => {
        const o = ctx.createOscillator(), g = ctx.createGain();
        o.frequency.value = 880; o.connect(g); g.connect(ctx.destination);
        g.gain.setValueAtTime(0.25, ctx.currentTime + t); g.gain.exponentialRampToValueAtTime(0.001, ctx.currentTime + t + 0.2);
        o.start(ctx.currentTime + t); o.stop(ctx.currentTime + t + 0.2);
      });
    } catch (_) {}
  }

  function poll(url, since, provinceId) {
    const bar = document.getElementById('newCaseBar');
    let total = 0;
    const tick = async () => {
      try {
        const res = await fetch(`${url}?since=${since}`, { headers: { Accept: 'application/json' } });
        if (!res.ok) return;
        const d = await res.json();
        if (d.count > 0) {
          since = d.latest_id; total += d.count;
          if (bar) {
            bar.classList.remove('d-none');
            document.getElementById('newCaseText').textContent = `มีเคสใหม่ ${total} รายการ` + (d.critical ? ` (วิกฤต ${d.critical})` : '');
            document.getElementById('newCaseList').textContent = d.items.map(i => `${i.code} ${i.name}`).join(' · ');
          }
          document.title = `(${total}) ` + document.title.replace(/^\(\d+\)\s*/, '');
          beep();
        }
      } catch (_) {}
    };
    setInterval(tick, 20000);
    // มี realtime: ดึงทันทีเมื่อมีเคสใหม่
    if (window.FloodLive && provinceId) {
      FloodLive.channel('province.' + provinceId).on('case.changed', e => { if (e.type === 'created') setTimeout(tick, 300); });
    }
  }

  window.FloodCases = { map: initMap, poll, loadCases, caseIcon };
})();
