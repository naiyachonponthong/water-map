/* แดชบอร์ดศูนย์สั่งการและโหมดทีวี (ใช้ไฟล์เดียวกัน) */
(function () {
  'use strict';
  const cfg = window.LIVE_CFG;
  if (!cfg || !window.FloodLive) return;
  const $ = s => document.querySelector(s);
  let lastEventId = cfg.lastEventId || 0;
  const seenSos = new Set(cfg.sosOpen || []);
  const seenAlerts = new Set(cfg.alertsUnack || []);
  let booted = false;
  setTimeout(() => { booted = true; }, 1500);
  function sosAlarm() {
    FloodLive.sound.beep(6);
    document.title = '🆘 ' + document.title.replace(/^(🆘|⚠)\s*/, '');
  }

  // แผนที่
  const map = FloodMap.create($('#dashMap'), { center: cfg.center, zoom: cfg.zoom, areasUrl: cfg.areasUrl, fit: false, weight: 1 });
  const layers = FloodLive.mapLayers(map);
  layers.update(cfg.initial || {});

  // สถานะการเชื่อมต่อ
  FloodLive.onStatus(ok => {
    const dot = $('#liveDot'), text = $('#liveText');
    if (dot) dot.classList.toggle('on', ok);
    if (text) text.textContent = ok ? 'เชื่อมต่อสด' : (FloodLive.enabled ? 'กำลังเชื่อมต่อใหม่ ดึงข้อมูลทุก 20 วินาที' : 'ดึงข้อมูลทุก 20 วินาที');
  });

  function render(d) {
    Object.entries(d.stats || {}).forEach(([k, v]) => document.querySelectorAll(`[data-stat="${k}"]`).forEach(el => {
      const next = Number(v ?? 0).toLocaleString('th-TH');
      if (el.textContent !== next) { el.textContent = next; el.classList.remove('bump'); void el.offsetWidth; el.classList.add('bump'); }
    }));
    const wait = document.querySelector('[data-wait]');
    if (wait) wait.textContent = d.stats.longest_wait ? `นานสุด ${d.stats.longest_wait} นาที` : '';
    if (d.urgent_html && $('#liveUrgent')) $('#liveUrgent').innerHTML = d.urgent_html;
    if (d.feed_html && $('#liveFeed')) $('#liveFeed').innerHTML = d.feed_html;
    if (d.sos_html !== undefined && $('#liveSos')) $('#liveSos').innerHTML = d.sos_html;
    if (d.alerts_html !== undefined && $('#liveAlerts')) $('#liveAlerts').innerHTML = d.alerts_html;
    // ประกาศเตือนภัยใหม่/รุนแรงขึ้นที่ยังไม่มีใครรับทราบ
    (d.alerts_unack || []).forEach(id => { if (!seenAlerts.has(id)) { seenAlerts.add(id); if (booted) FloodLive.sound.beep(4); } });
    // SOS ใหม่ที่ยังไม่เคยเห็น (กรณีไม่มี realtime)
    (d.sos_open || []).forEach(id => { if (!seenSos.has(id)) { seenSos.add(id); if (booted) sosAlarm(); } });
    if ($('#liveTime')) $('#liveTime').textContent = d.time;
    layers.update(d);
    if (d.new_critical && d.new_critical.length) alertCritical(d.new_critical);
    lastEventId = d.last_event_id || lastEventId;
  }

  function alertCritical(codes) {
    const bar = $('#criticalBar');
    if (bar) { bar.classList.remove('d-none'); $('#criticalCodes').textContent = codes.join(', '); }
    FloodLive.sound.beep(3);
    document.title = '⚠ ' + document.title.replace(/^⚠\s*/, '');
  }

  const watcher = FloodLive.watch({ url: () => `${cfg.snapshotUrl}${cfg.snapshotUrl.includes('?') ? '&' : '?'}since=${lastEventId}`, every: 20000, onData: render });

  // realtime: เคส/ทีมเปลี่ยน = โหลด snapshot ใหม่ (รวบหลาย event ไว้รอบเดียว)
  FloodLive.channel('province.' + cfg.provinceId)
    .on('case.changed', e => {
      if (e.critical && (e.type === 'created' || e.type === 'update')) alertCritical([e.code]);
      watcher.refresh();
    })
    .on('team.changed', () => watcher.refresh(1500))
    .on('report.changed', () => watcher.refresh(4000))
    .on('shelter.changed', () => watcher.refresh(3000))
    .on('alert.changed', e => {
      if (e.type === 'escalated') seenAlerts.delete(e.id);
      watcher.refresh(300);
    })
    .on('risk.changed', e => {
      if (e.threatened > 0 || e.cases > 0) FloodLive.sound.beep(2);
      watcher.refresh(500);
    })
    .on('team.sos', e => { if (e.status === 'open') { seenSos.add(e.id); sosAlarm(); } watcher.refresh(200); });

  // ปุ่มเสียง
  const btn = $('#soundBtn');
  const syncBtn = () => {
    if (!btn) return;
    const on = FloodLive.sound.enabled;
    btn.innerHTML = on ? '<i class="bi bi-volume-up"></i> เสียงเตือนเปิด' : '<i class="bi bi-volume-mute"></i> เปิดเสียงเตือน';
    btn.classList.toggle('btn-success', on); btn.classList.toggle('btn-light', !on);
  };
  if (btn) btn.addEventListener('click', () => { FloodLive.sound.enabled ? FloodLive.sound.disable() : FloodLive.sound.enable(); syncBtn(); });
  syncBtn();

  // โหมดทีวี: นาฬิกา + เต็มจอ
  const clock = $('#tvClock');
  if (clock) setInterval(() => { clock.textContent = new Date().toLocaleTimeString('th-TH', { hour: '2-digit', minute: '2-digit', second: '2-digit' }); }, 1000);
  const fs = $('#fullscreenBtn');
  if (fs) fs.addEventListener('click', () => document.fullscreenElement ? document.exitFullscreen() : document.documentElement.requestFullscreen());
})();
