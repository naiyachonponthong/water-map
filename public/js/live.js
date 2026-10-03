/* ข้อมูลสดของศูนย์สั่งการ
   - มี Reverb: ฟัง event แล้วโหลด snapshot ใหม่ทันที
   - ไม่มี / หลุด: ดึง snapshot ทุก 20 วินาที
   ใช้: FloodLive.channel('province.5').on('case.changed', fn)
        FloodLive.watch({ url, every, onData }) */
(function () {
  'use strict';
  let echo = null, connected = false;
  const statusListeners = [];

  function setStatus(ok) {
    connected = ok;
    statusListeners.forEach(fn => { try { fn(ok); } catch (_) {} });
  }

  function getEcho() {
    if (echo || !window.REVERB || !window.Echo || !window.Pusher) return echo;
    const r = window.REVERB;
    try {
      echo = new window.Echo({
        broadcaster: 'reverb',
        key: r.key,
        wsHost: r.host,
        wsPort: r.port,
        wssPort: r.port,
        forceTLS: r.tls,
        enabledTransports: ['ws', 'wss'],
        authEndpoint: '/broadcasting/auth',
        auth: { headers: { 'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]')?.content || '' } },
      });
      const conn = echo.connector.pusher.connection;
      conn.bind('connected', () => setStatus(true));
      ['disconnected', 'unavailable', 'failed'].forEach(ev => conn.bind(ev, () => setStatus(false)));
    } catch (e) {
      echo = null;
    }
    return echo;
  }

  /** ช่องส่วนตัว คืน object ที่ .on(event, fn) ได้ (ไม่มี Reverb = ไม่ทำอะไร) */
  function channel(name) {
    const e = getEcho();
    const ch = e ? e.private(name) : null;
    const api = {
      on(event, fn) { if (ch) ch.listen('.' + event, fn); return api; },
    };
    return api;
  }

  /** ดึงข้อมูลซ้ำ: เร็วเมื่อไม่มี socket, ช้า (กันพลาด) เมื่อมี socket; refresh() เรียกทันทีแบบหน่วงสั้นๆ */
  function watch(opts) {
    let timer = null, debounce = null, running = false;
    const every = opts.every || 20000;
    const run = async () => {
      if (running || document.visibilityState === 'hidden') return;
      running = true;
      try {
        const res = await fetch(typeof opts.url === 'function' ? opts.url() : opts.url, { headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' } });
        // หมดเวลาใช้งาน / ต้องเปิด 2 ชั้น: โหลดหน้าใหม่ให้ระบบพาไปหน้าที่ถูก
        if (res.status === 401 || res.status === 419 || res.status === 403) { location.reload(); return; }
        if (res.ok) opts.onData(opts.html ? await res.text() : await res.json());
      } catch (_) {} finally { running = false; }
    };
    const schedule = () => { clearInterval(timer); timer = setInterval(run, connected ? Math.max(every * 6, 120000) : every); };
    onStatus(schedule);
    schedule();
    document.addEventListener('visibilitychange', () => document.visibilityState === 'visible' && run());
    return {
      refresh(delay = 600) { clearTimeout(debounce); debounce = setTimeout(run, delay); },
      run,
    };
  }

  function onStatus(fn) { statusListeners.push(fn); fn(connected); }

  /* ---------- เสียงเตือน (ต้องให้ผู้ใช้กดเปิดหนึ่งครั้งตามกฎเบราว์เซอร์) ---------- */
  const sound = {
    ctx: null,
    get enabled() { try { return localStorage.getItem('flood-sound') === '1'; } catch (_) { return false; } },
    enable() {
      try { localStorage.setItem('flood-sound', '1'); } catch (_) {}
      this.ctx = this.ctx || new (window.AudioContext || window.webkitAudioContext)();
      this.ctx.resume();
      this.beep(1);
    },
    disable() { try { localStorage.setItem('flood-sound', '0'); } catch (_) {} },
    beep(times = 3) {
      if (!this.enabled) return;
      try {
        this.ctx = this.ctx || new (window.AudioContext || window.webkitAudioContext)();
        for (let i = 0; i < times; i++) {
          const t = this.ctx.currentTime + i * 0.35;
          const o = this.ctx.createOscillator(), g = this.ctx.createGain();
          o.type = 'square'; o.frequency.setValueAtTime(i % 2 ? 660 : 990, t);
          o.connect(g); g.connect(this.ctx.destination);
          g.gain.setValueAtTime(0.18, t); g.gain.exponentialRampToValueAtTime(0.001, t + 0.28);
          o.start(t); o.stop(t + 0.3);
        }
      } catch (_) {}
    },
  };

  /* ---------- ชั้นแผนที่: เคส + ทีม ---------- */
  function mapLayers(map) {
    const reportLayer = L.layerGroup().addTo(map);
    const stationLayer = L.layerGroup().addTo(map);
    const riskLayer = L.layerGroup().addTo(map);
    const caseLayer = L.layerGroup().addTo(map);
    const teamLayer = L.layerGroup().addTo(map);
    let fitted = false;

    const caseIcon = (p) => {
      const s = p.priority === 'critical' ? 26 : 18;
      const ring = p.priority === 'critical' ? 'animation:pulse-ring 1.6s infinite;' : '';
      return L.divIcon({ className: '', html: `<div class="case-marker" style="background:${p.color};width:${s}px;height:${s}px;${ring}${p.has_team ? 'outline:3px solid #2563eb;outline-offset:1px;' : ''}"></div>`, iconSize: [s, s], iconAnchor: [s / 2, s / 2] });
    };
    const teamIcon = (p) => L.divIcon({ className: '', html: `<div class="team-marker" style="background:${p.color};opacity:${p.live ? 1 : .55}"><i class="bi bi-truck"></i></div>`, iconSize: [30, 30], iconAnchor: [15, 15] });

    return {
      update(data) {
        caseLayer.clearLayers(); teamLayer.clearLayers();
        if (data.reports) {
          reportLayer.clearLayers();
          data.reports.forEach(f => {
            const p = f.properties, [lng, lat] = f.geometry.coordinates;
            L.circleMarker([lat, lng], { radius: 3 + p.level, color: p.trusted ? '#1d4ed8' : '#fff', weight: p.trusted ? 2 : 1, fillColor: p.color, fillOpacity: p.opacity * .85, interactive: true })
              .bindTooltip(`น้ำ${p.label} · ${p.source} · ${p.ago}${p.trend ? ' · ' + p.trend : ''}`)
              .addTo(reportLayer);
          });
        }
        if (data.stations && window.FloodStations) {
          stationLayer.clearLayers();
          stationLayer.addLayer(FloodStations.layer(null, { features: data.stations }));
        }
        if (data.risks && window.FloodRisks) {
          riskLayer.clearLayers();
          riskLayer.addLayer(FloodRisks.layer(null, { features: data.risks }, { circles: true }));
        }
        (data.cases || []).forEach(f => {
          const p = f.properties, [lng, lat] = f.geometry.coordinates;
          L.marker([lat, lng], { icon: caseIcon(p), zIndexOffset: p.score })
            .bindPopup(`<div style="min-width:180px"><div class="small text-muted mono">${p.code}</div><b>${p.name}</b><div class="small">${p.status} · ${p.priority_label} ${p.score}</div><div class="small">น้ำ${p.water} · ${p.people} คน · ${p.ago}</div><a href="${p.url}" class="btn btn-sm btn-primary mt-2 w-100 text-white">เปิดเคส</a></div>`)
            .addTo(caseLayer);
        });
        (data.teams || []).forEach(f => {
          const p = f.properties, [lng, lat] = f.geometry.coordinates;
          L.marker([lat, lng], { icon: teamIcon(p), zIndexOffset: 1000 })
            .bindTooltip(`<b>${p.name}</b><br>${p.status_label}${p.jobs.length ? ' · ' + p.jobs.join(', ') : ''}<br><small>${p.live ? 'พิกัดสด ' + (p.seen || '') : 'พิกัดฐาน'}</small>`)
            .addTo(teamLayer);
        });
        if (!fitted) {
          const all = [...caseLayer.getLayers(), ...teamLayer.getLayers()];
          if (all.length) { map.fitBounds(L.featureGroup(all).getBounds().pad(0.2), { maxZoom: 13 }); fitted = true; }
        }
      },
    };
  }

  window.FloodLive = { channel, watch, onStatus, sound, mapLayers, get connected() { return connected; }, get enabled() { return !!window.REVERB; } };
})();
