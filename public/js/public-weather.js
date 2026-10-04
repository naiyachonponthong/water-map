(function () {
  'use strict';
  const esc = s => String(s ?? '').replace(/[&<>"']/g, c => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]));
  const fmt = n => n == null ? '—' : Number(n).toLocaleString('th-TH', { maximumFractionDigits: 1 });
  const clock = t => new Intl.DateTimeFormat('en-GB', { timeZone: 'Asia/Bangkok', hour: '2-digit', minute: '2-digit', hourCycle: 'h23' }).format(new Date(t));
  const date = t => new Intl.DateTimeFormat('th-TH', { timeZone: 'Asia/Bangkok', day: 'numeric', month: 'short' }).format(new Date(t));
  const dayKey = t => new Intl.DateTimeFormat('en-CA', { timeZone: 'Asia/Bangkok', year: 'numeric', month: '2-digit', day: '2-digit' }).format(new Date(t));
  const fullTime = t => `${date(t)} ${clock(t)} น.`;
  function condition(code) {
    if (code == null) return ['ยังไม่มีข้อมูลสภาพอากาศ', 'cloud'];
    if (code >= 95) return ['พายุฝนฟ้าคะนอง', 'cloud-lightning-rain'];
    if (code >= 80) return ['ฝนตกเป็นช่วง', 'cloud-rain'];
    if (code >= 51) return ['มีฝน', 'cloud-rain'];
    if (code >= 45) return ['หมอก', 'cloud-haze'];
    if (code >= 1) return ['มีเมฆ', 'cloud-sun'];
    return ['ท้องฟ้าแจ่มใส', 'sun'];
  }
  async function get(url, externalSignal) {
    const controller = new AbortController();
    const cancel = () => controller.abort();
    if (externalSignal?.aborted) controller.abort();
    externalSignal?.addEventListener('abort', cancel, { once: true });
    const timeout = setTimeout(cancel, 35000);
    try {
      const localUrl = new URL(url, window.location.origin);
      const response = await fetch(localUrl.pathname + localUrl.search, { headers: { Accept: 'application/json' }, signal: controller.signal });
      const data = await response.json();
      if (!response.ok) throw new Error(data.message || 'ยังโหลดข้อมูลไม่ได้ กรุณาลองใหม่');
      return data;
    } catch (e) {
      if (externalSignal?.aborted) throw e;
      if (e.name === 'AbortError') throw new Error('แหล่งข้อมูลตอบกลับช้า กรุณาลองโหลดอีกครั้ง');
      if (e instanceof TypeError || e instanceof SyntaxError) throw new Error('เชื่อมต่อระบบไม่ได้ กรุณาตรวจว่าเซิร์ฟเวอร์กำลังทำงาน แล้วลองอีกครั้ง');
      throw e;
    } finally { clearTimeout(timeout); externalSignal?.removeEventListener('abort', cancel); }
  }
  function chart(hours, interactive = false) {
    const measured = hours.map(h => h.rain_mm).filter(n => n != null);
    const max = Math.max(1, ...measured);
    const tag = interactive ? 'button' : 'span';
    return `<div class="pw-chart" ${interactive ? '' : 'role="img" aria-label="กราฟปริมาณฝนรายชั่วโมง"'}>${hours.map((h, i) => {
      const text = `${fullTime(h.time)} ฝน ${fmt(h.rain_mm)} มม. โอกาสฝน ${fmt(h.probability)}%`;
      return `<${tag} ${interactive ? `type="button" data-hour="${i}" aria-label="${esc(text)}"` : ''} title="${esc(text)}" class="${h.rain_mm == null ? 'pw-missing' : (h.rain_mm < .1 ? 'pw-no-rain' : '')}" style="--bar-height:${Math.max(3, (h.rain_mm ?? 0) / max * 100)}%">${i % 4 === 0 ? `<small>${clock(h.time)}</small>` : ''}</${tag}>`;
    }).join('')}</div><div class="pw-chart-caption">ฝนในชั่วโมงก่อนเวลาที่ระบุ · สูงสุด ${fmt(measured.length ? Math.max(...measured) : null)} มม.</div>`;
  }

  document.querySelectorAll('[data-weather-card]').forEach(card => {
    const category = card.querySelector('[data-weather-category]');
    const summary = card.querySelector('[data-weather-summary]');
    const graph = card.querySelector('[data-weather-chart]');
    const updated = card.querySelector('[data-weather-updated]');
    const retry = card.querySelector('[data-weather-retry]');
    async function load() {
      retry.hidden = true; category.textContent = 'กำลังโหลดพยากรณ์…';
      card.setAttribute('aria-busy', 'true');
      try {
        const data = await get(card.dataset.url), s = data.summary;
        category.textContent = s.category;
        summary.textContent = `รวมประมาณ ${fmt(s.rain_mm)} มม. · โอกาสฝนสูงสุด ${fmt(s.probability)}%` + (s.first_rain ? ` · คาดว่ามีฝนช่วง ${fullTime(s.first_rain)}` : '');
        graph.innerHTML = chart(data.hours.slice(0, 24)); graph.hidden = false;
        updated.textContent = `${data.stale ? 'ข้อมูลเก่า: โหลดข้อมูลล่าสุดไม่ได้ · ' : ''}Open-Meteo · ดึงข้อมูล ${fullTime(data.updated_at)}`;
      } catch (e) {
        category.textContent = 'ยังไม่มีข้อมูลพยากรณ์'; summary.textContent = e.message;
        graph.hidden = true; updated.textContent = ''; retry.hidden = false;
      } finally { card.setAttribute('aria-busy', 'false'); }
    }
    retry.addEventListener('click', load); load();
  });

  const page = document.getElementById('weatherPage');
  if (!page) return;
  const el = id => document.getElementById(id);
  let forecast = null;
  const locations = JSON.parse(el('weatherLocations').textContent);
  let currentLocation = locations.find(p => p.slug === page.dataset.slug);
  let areas = JSON.parse(el('weatherAreas')?.textContent || '[]'), areaLoading = false;
  let selectedPoint = null, refreshTimer = null;
  let forecastController = null, contextController = null, generation = 0;
  function dayLabel(d) {
    const today = dayKey(new Date());
    const offset = Math.round((new Date(`${d}T00:00:00+07:00`) - new Date(`${today}T00:00:00+07:00`)) / 86400000);
    return ['วันนี้', 'พรุ่งนี้', 'มะรืนนี้'][offset] || date(`${d}T00:00:00+07:00`);
  }
  function drawHours() {
    if (!forecast) return;
    const day = forecast.days.find(d => d.date === el('forecastDay').value);
    if (!day) return;
    el('forecastSelectedDay').textContent = `${dayLabel(day.date)} · ${date(`${day.date}T00:00:00+07:00`)}`;
    el('forecastDayMetrics').innerHTML = [
      ['ปริมาณฝนทั้งวัน', `${fmt(day.rain_mm)} มม.`], ['โอกาสฝนสูงสุด', `${fmt(day.probability)}%`],
      ['อุณหภูมิต่ำสุด–สูงสุด', `${fmt(day.temp_min)}–${fmt(day.temp_max)} °C`], ['ลมสูงสุด', `${fmt(day.wind_kmh)} กม./ชม.`],
    ].map(([label, value]) => `<div><small>${label}</small><strong>${value}</strong></div>`).join('');
    el('weatherDays').querySelectorAll('[data-day]').forEach(b => b.setAttribute('aria-pressed', String(b.dataset.day === day.date)));
    const hours = forecast.hours.filter(h => dayKey(h.time) === el('forecastDay').value);
    el('forecastChart').innerHTML = chart(hours, true);
    el('forecastHours').innerHTML = hours.length ? hours.map((h, i) => {
      const [label, icon] = condition(h.code);
      return `<tr id="weatherHour${i}" tabindex="-1"><td><time datetime="${esc(h.time)}">${clock(h.time)} น.</time></td><td><i class="bi bi-${icon}" aria-hidden="true"></i> ${esc(label)}</td><td><strong>${fmt(h.rain_mm)}</strong></td><td>${fmt(h.probability)}%</td><td>${fmt(h.temperature)} °C</td><td class="pw-wind">${h.wind_direction == null ? '' : `<i class="bi bi-arrow-up" style="transform:rotate(${h.wind_direction + 180}deg)" title="ลมจาก ${h.wind_direction}°" aria-hidden="true"></i>`}${fmt(h.wind_kmh)}</td></tr>`;
    }).join('') : '<tr><td colspan="6">ไม่มีชั่วโมงพยากรณ์ที่เหลือในวันนี้</td></tr>';
  }
  el('forecastChart').addEventListener('click', e => {
    const button = e.target.closest('[data-hour]');
    if (button) { const row = el(`weatherHour${button.dataset.hour}`); row?.scrollIntoView({ block: 'nearest' }); row?.focus({ preventScroll: true }); }
  });
  el('forecastDay').addEventListener('change', drawHours);
  el('weatherDays').addEventListener('click', e => { const b = e.target.closest('[data-day]'); if (b) { el('forecastDay').value = b.dataset.day; drawHours(); } });
  function drawOverview(data) {
    const c = data.current || {}, s = data.summary, [label, icon] = condition(c.code);
    el('currentTemperature').textContent = fmt(c.temperature);
    el('currentCondition').textContent = label;
    el('currentIcon').className = `bi bi-${icon}`;
    el('currentFeels').textContent = `${fmt(c.feels_like)} °C`;
    el('currentHumidity').textContent = `${fmt(c.humidity)}%`;
    el('currentWind').textContent = `${fmt(c.wind_kmh)} กม./ชม.`;
    el('currentTime').textContent = c.time ? `${data.current_stale ? 'สภาพอากาศหมดอายุ · ' : data.stale ? 'ข้อมูลเก่า · ' : ''}แบบจำลอง ณ ${fullTime(c.time)}` : 'ยังไม่มีข้อมูลสภาพอากาศจากแบบจำลอง';
    el('summaryCategory').textContent = s.category;
    el('summaryRain').textContent = fmt(s.rain_mm); el('summaryChance').textContent = fmt(s.probability);
    el('summaryStart').textContent = s.first_rain ? fullTime(s.first_rain) : (s.rain_mm == null ? 'ข้อมูลไม่ครบ' : 'ยังไม่คาดว่าจะมีฝน');
    el('summaryChart').innerHTML = chart(data.hours.slice(0, 24));
    el('weatherCity').textContent = data.location?.name || `ตัวเมือง${currentLocation.name}`;
    if (el('rainWindows')) el('rainWindows').innerHTML = (s.rain_windows || []).map(w => `<span>${esc(fullTime(w.from))}–${esc(dayKey(w.from) === dayKey(w.to) ? `${clock(w.to)} น.` : fullTime(w.to))} · ${fmt(w.rain_mm)} มม.</span>`).join('') || (s.rain_mm == null ? 'ข้อมูลช่วงฝนไม่ครบ' : 'ยังไม่คาดว่าจะมีฝนใน 24 ชั่วโมง');
    if (el('forecastQuality')) el('forecastQuality').textContent = `${data.location?.name || `ตัวเมือง${currentLocation.name}`} · ฝนสูงสุดรายชั่วโมง ${fmt(s.max_hour_mm)} มม. · คาดว่ามีฝน ${fmt(s.rain_hours)} ชม. · ${data.stale ? 'ข้อมูลสำรองเก่า' : 'อัปเดตอัตโนมัติทุก 15 นาที'}${data.current_stale ? ' · สภาพอากาศปัจจุบันหมดอายุ' : ''}`;
  }
  function clearForecast() {
    forecast = null;
    el('forecastDay').innerHTML = ''; el('forecastDay').disabled = true;
    el('forecastDayMetrics').innerHTML = ''; el('forecastChart').innerHTML = ''; el('summaryChart').innerHTML = '';
    el('forecastHours').innerHTML = '<tr><td colspan="6">กำลังโหลดพยากรณ์ของจังหวัดที่เลือก…</td></tr>';
    ['currentTemperature', 'summaryRain', 'summaryChance', 'summaryStart'].forEach(id => { el(id).textContent = '—'; });
    el('currentCondition').textContent = 'กำลังโหลดข้อมูล…'; el('currentTime').textContent = 'ข้อมูลจากแบบจำลอง ไม่ใช่การวัดภาคสนาม';
    el('currentFeels').textContent = '— °C'; el('currentHumidity').textContent = '— %'; el('currentWind').textContent = '— กม./ชม.';
    el('currentIcon').className = 'bi bi-cloud-sun'; el('summaryCategory').textContent = 'กำลังโหลด…';
    if (el('rainWindows')) el('rainWindows').textContent = '';
    if (el('forecastQuality')) el('forecastQuality').textContent = 'กำลังโหลดข้อมูลพื้นที่ที่เลือก…';
    el('weatherDays').innerHTML = Array.from({ length: 7 }, (_, i) => `<article class="pw-day"><div class="pw-day-label">${['วันนี้', 'พรุ่งนี้', 'มะรืนนี้'][i] || `อีก ${i} วัน`}</div><div class="pw-skeleton"></div><strong>—</strong></article>`).join('');
  }
  async function loadForecast() {
    forecastController?.abort(); forecastController = new AbortController();
    const version = ++generation, location = currentLocation, signal = forecastController.signal;
    el('forecastRetry').hidden = true;
    el('forecastRefresh').disabled = true;
    el('weatherUpdated').textContent = `กำลังโหลดพยากรณ์ของ${location.name}…`;
    el('weatherDays').setAttribute('aria-busy', 'true');
    try {
      const forecastUrl = new URL(location.forecastUrl, window.location.origin);
      if (selectedPoint) { forecastUrl.searchParams.set('district', el('weatherDistrict').value); forecastUrl.searchParams.set('subdistrict', el('weatherSubdistrict').value); }
      const data = await get(forecastUrl.href, signal);
      if (version !== generation) return;
      forecast = data; drawOverview(data);
      el('weatherDays').innerHTML = data.days.map(d => {
        const [label, icon] = condition(d.code);
        return `<button type="button" class="pw-day" data-day="${esc(d.date)}" aria-pressed="false" aria-label="ดูพยากรณ์ ${esc(dayLabel(d.date))} ${date(`${d.date}T00:00:00+07:00`)}"><div class="pw-day-label">${esc(dayLabel(d.date))} · ${date(`${d.date}T00:00:00+07:00`)}</div><i class="bi bi-${icon} pw-condition-icon" aria-hidden="true" title="${esc(label)}"></i><strong>${fmt(d.temp_min)}–${fmt(d.temp_max)} °C</strong><div class="pw-day-metrics"><span>ฝน ${fmt(d.rain_mm)} มม.</span><span class="pw-day-prob">โอกาสฝน ${fmt(d.probability)}%</span></div></button>`;
      }).join('');
      el('weatherUpdated').textContent = `${data.location?.name || `ตัวเมือง${location.name}`} · ${data.stale ? 'ข้อมูลเก่า: อัปเดตล่าสุดไม่ได้ · ' : ''}ดึงข้อมูล ${fullTime(data.updated_at)}`;
      const selected = el('forecastDay').value;
      el('forecastDay').innerHTML = data.days.map(d => `<option value="${esc(d.date)}">${esc(dayLabel(d.date))} · ${date(`${d.date}T00:00:00+07:00`)}</option>`).join('');
      if (data.days.some(d => d.date === selected)) el('forecastDay').value = selected;
      el('forecastDay').disabled = false; drawHours();
    } catch (e) {
      if (version !== generation || signal.aborted) return;
      el('weatherUpdated').textContent = e.message; el('forecastRetry').hidden = false;
      if (!forecast) {
        el('currentCondition').textContent = 'ยังโหลดข้อมูลไม่ได้'; el('summaryCategory').textContent = 'ยังไม่มีข้อมูล';
        el('weatherDays').innerHTML = '<div class="pw-empty-forecast">ยังโหลดพยากรณ์ไม่ได้ กด “ลองโหลดอีกครั้ง” หรือ “อัปเดต” ด้านบน</div>';
        el('forecastHours').innerHTML = '<tr><td colspan="6">ยังไม่มีพยากรณ์ กด “ลองโหลดอีกครั้ง” ด้านบน</td></tr>';
      }
    } finally { if (version === generation) { el('weatherDays').setAttribute('aria-busy', 'false'); el('forecastRefresh').disabled = false; } }
  }
    el('forecastRetry').addEventListener('click', loadForecast);
  el('forecastRefresh').addEventListener('click', () => { loadForecast(); loadRadar(); });

  function drawAreas() {
    if (!el('weatherDistrict')) return;
    el('weatherDistrict').innerHTML = '<option value="">ตัวเมืองจังหวัด</option>' + areas.map(d => `<option value="${esc(d.code)}">${esc(d.name)}</option>`).join('');
    el('weatherDistrict').disabled = areaLoading;
    el('weatherSubdistrict').innerHTML = '<option value="">เลือกตำบล / แขวง</option>';
    el('weatherSubdistrict').disabled = true;
  }
  el('weatherDistrict')?.addEventListener('change', () => {
    const district = areas.find(d => d.code === el('weatherDistrict').value);
    selectedPoint = null;
    el('weatherSubdistrict').innerHTML = '<option value="">เลือกตำบล / แขวง</option>' + (district?.subdistricts || []).map(s => `<option value="${esc(s.code)}" ${s.center ? '' : 'disabled'}>${esc(s.name)}</option>`).join('');
    el('weatherSubdistrict').disabled = !district;
    clearForecast(); updateMapLocation(currentLocation); loadForecast();
  });
  el('weatherSubdistrict')?.addEventListener('change', () => {
    selectedPoint = areas.find(d => d.code === el('weatherDistrict').value)?.subdistricts.find(s => s.code === el('weatherSubdistrict').value) || null;
    clearForecast(); updateMapLocation(currentLocation); loadForecast();
  });

  let map = null, activeLayer = null, frames = [], timer = null, frameIndex = 0, boundaryLayer = null, centerMarker = null;
  const layers = new Map();
  let playbackRequested = !window.matchMedia('(prefers-reduced-motion: reduce)').matches;
  let mapMoving = false, radarLoading = false;
  function radarMessage(text) { el('radarMessage').textContent = text; el('radarMessage').hidden = !text; }
  function stop() {
    clearTimeout(timer); timer = null;
    el('radarPlay').setAttribute('aria-pressed', 'false'); el('radarPlay').setAttribute('aria-label', 'เล่นเรดาร์ย้อนหลัง');
    el('radarPlay').innerHTML = '<i class="bi bi-play-fill" aria-hidden="true"></i>';
  }
  function initMap() {
    if (typeof L === 'undefined') { radarMessage('โหลดแผนที่ไม่ได้ กรุณารีเฟรชหน้าเว็บ'); return; }
    const center = [Number(page.dataset.lat), Number(page.dataset.lng)];
    map = L.map('weatherMap', { zoomControl: true, minZoom: 4, maxZoom: 12 }).setView(center, page.dataset.hasCenter === '1' ? 7 : 5);
    L.tileLayer('https://tile.openstreetmap.org/{z}/{x}/{y}.png', { maxZoom: 19, attribution: '&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a>' }).addTo(map);
    map.on('movestart', () => {
      mapMoving = true;
      stop();
      // Keep cached frames for smooth playback, but don't reload all hidden frames after panning.
      for (const [path, layer] of layers) if (layer !== activeLayer) { map.removeLayer(layer); layers.delete(path); }
    });
    map.on('moveend', () => { mapMoving = false; resumeRadar(); });
    const boundaries = JSON.parse(el('weatherBoundaries').textContent);
    updateMapLocation(currentLocation, boundaries);
    el('radarCenter').addEventListener('click', () => { const center = selectedPoint?.center || currentLocation.center; if (center) map.setView(center, 7); });
  }
  function updateMapLocation(location, boundaries) {
    if (!map) return;
    if (centerMarker) map.removeLayer(centerMarker);
    if (boundaryLayer) map.removeLayer(boundaryLayer);
    centerMarker = null; boundaryLayer = null;
    const center = selectedPoint?.center || location.center;
    if (center) {
      map.setView(center, selectedPoint ? 9 : 7);
      centerMarker = L.circleMarker(center, { radius: 7, color: '#fff', weight: 3, fillColor: '#126fac', fillOpacity: 1 })
        .bindTooltip(esc(selectedPoint?.name || `บริเวณตัวเมือง${location.name}`)).addTo(map);
    }
    if (boundaries?.features?.length) boundaryLayer = L.geoJSON(boundaries, { style: { color: '#ed6370', weight: 2, fillOpacity: .02 } }).addTo(map);
  }
  async function changeLocation(slug) {
    const next = locations.find(p => p.slug === slug);
    if (!next || next.slug === currentLocation.slug) return;
    stop(); contextController?.abort(); contextController = new AbortController();
    const signal = contextController.signal;
    currentLocation = next; page.dataset.province = next.name; page.dataset.slug = next.slug;
    selectedPoint = null; areas = []; areaLoading = true; drawAreas();
    page.dataset.forecastUrl = next.forecastUrl; page.dataset.radarUrl = next.radarUrl;
    el('weatherProvinceTitle').textContent = next.name; el('weatherCity').textContent = `ตัวเมือง${next.name}`;
    el('weatherHome').href = next.homeUrl; document.title = `พยากรณ์อากาศ ${next.name} | ศูนย์ช่วยเหลือน้ำท่วม`;
    const waterLink = el('weatherWaterLink'); if (waterLink) waterLink.href = next.homeUrl + '/water-map';
    history.replaceState(null, '', next.url);
    clearForecast(); updateMapLocation(next); loadForecast();
    if (!frames.length) loadRadar();
    try {
      const context = await get(next.contextUrl, signal);
      if (currentLocation.slug === next.slug && !signal.aborted) { areas = context.areas || []; areaLoading = false; drawAreas(); updateMapLocation(next, context.boundaries); }
    } catch (_) { /* No fabricated boundary on an unavailable geometry endpoint. */ }
  }
  el('weatherProvince').addEventListener('change', e => changeLocation(e.target.value));

  // Enforce a conservative per-browser tile budget below the provider's 100/IP/min limit.
  // Frames load only when selected; browser cache and reuse avoid preloading all images.
  const tileQueue = [], sentAt = []; let queueTimer = null;
  function drainTiles() {
    clearTimeout(queueTimer);
    const now = Date.now(); while (sentAt.length && now - sentAt[0] >= 60000) sentAt.shift();
    while (tileQueue.length && sentAt.length < 80) {
      const job = tileQueue.shift();
      if (job.image.dataset.cancelled === '1') continue;
      sentAt.push(Date.now()); job.image.src = job.url;
    }
    if (tileQueue.length) queueTimer = setTimeout(drainTiles, Math.max(50, 60010 - (Date.now() - sentAt[0])));
  }
  function layerFor(frame, host) {
    if (layers.has(frame.path)) return layers.get(frame.path);
    const LimitedTiles = L.TileLayer.extend({
      createTile(coords, done) {
        const image = document.createElement('img'); image.alt = ''; image.setAttribute('role', 'presentation');
        image.onload = () => done(null, image); image.onerror = () => done(new Error('Radar tile unavailable'), image);
        tileQueue.push({ image, url: this.getTileUrl(coords) }); setTimeout(drainTiles, 0);
        return image;
      },
    });
    const layer = new LimitedTiles(`${host}${frame.path}/512/{z}/{x}/{y}/2/1_1.png`, {
      tileSize: 512, zoomOffset: -1, maxNativeZoom: 8, maxZoom: 12, opacity: .72, zIndex: 300,
      keepBuffer: 0, updateWhenIdle: true, attribution: 'เรดาร์ฝน: <a href="https://www.rainviewer.com/">RainViewer</a>',
    });
    layer.on('tileunload', e => { e.tile.dataset.cancelled = '1'; });
    layer.on('loading', () => { layer.weatherError = false; if (layer === activeLayer) radarMessage('กำลังโหลดภาพเรดาร์…'); });
    layer.on('load', () => { if (layer === activeLayer && !layer.weatherError) frameNotice(); });
    layer.on('tileerror', () => { layer.weatherError = true; if (layer === activeLayer) { playbackRequested = false; stop(); radarMessage('ภาพเรดาร์บางส่วนโหลดไม่ได้ ภาพว่างไม่ยืนยันว่าไม่มีฝน · ลองโหลดใหม่'); } });
    layers.set(frame.path, layer); return layer;
  }
  let radarHost = '', radarStale = false;
  function frameNotice() {
    const age = (Date.now() - frames[frameIndex].time * 1000) / 60000;
    radarMessage(radarStale || (frameIndex === frames.length - 1 && age > 30) ? 'ภาพล่าสุดล่าช้า โปรดตรวจเวลาในแถบด้านล่าง' : '');
  }
  function showFrame(index) {
    if (!map || !frames.length) return;
    frameIndex = index;
    const frame = frames[index], time = frame.time * 1000;
    el('radarSlider').value = String(index); el('radarSlider').setAttribute('aria-valuetext', fullTime(time));
    el('radarTime').textContent = `${clock(time)} น.`; el('radarDate').textContent = `${date(time)} · เวลาไทย`;
    if (activeLayer) activeLayer.setOpacity(0);
    activeLayer = layerFor(frame, radarHost);
    activeLayer.setOpacity(.72);
    if (!map.hasLayer(activeLayer)) activeLayer.addTo(map);
    if (activeLayer.weatherError) radarMessage('ภาพเรดาร์บางส่วนโหลดไม่ได้ · ลองโหลดใหม่');
    else if (activeLayer.isLoading()) radarMessage('กำลังโหลดภาพเรดาร์…');
    else frameNotice();
  }
  function tick() {
    if (!playbackRequested || document.hidden || el('radarPanel').hidden || mapMoving || radarLoading || frames.length < 2) { stop(); return; }
    if (activeLayer?.weatherError) { playbackRequested = false; stop(); return; }
    if (!activeLayer?.isLoading()) showFrame((frameIndex + 1) % frames.length);
    timer = setTimeout(tick, activeLayer?.isLoading() ? 500 : (frameIndex === frames.length - 1 ? 2500 : 1500));
  }
  function resumeRadar() {
    if (timer || !playbackRequested || document.hidden || el('radarPanel').hidden || mapMoving || radarLoading || frames.length < 2) return;
    el('radarPlay').setAttribute('aria-pressed', 'true'); el('radarPlay').setAttribute('aria-label', 'หยุดเรดาร์ย้อนหลัง');
    el('radarPlay').innerHTML = '<i class="bi bi-pause-fill" aria-hidden="true"></i>';
    timer = setTimeout(tick, 1500);
  }
  el('radarPlay').addEventListener('click', () => {
    playbackRequested = !playbackRequested;
    if (!playbackRequested) stop(); else resumeRadar();
  });
  el('radarSlider').addEventListener('input', () => { playbackRequested = false; stop(); showFrame(Number(el('radarSlider').value)); });
  async function loadRadar() {
    if (!map || radarLoading) return;
    radarLoading = true;
    stop(); el('radarRefresh').disabled = true; el('radarPlay').disabled = true; el('radarSlider').disabled = true;
    radarMessage('กำลังโหลดภาพเรดาร์…');
    try {
      const data = await get(page.dataset.radarUrl);
      frames = data.frames; radarHost = data.host; radarStale = data.stale;
      // Explicit reload also retries failed image tiles, not just the manifest.
      for (const [path, layer] of layers) if (layer.weatherError) { map.removeLayer(layer); layers.delete(path); }
      for (const [path, layer] of layers) if (!frames.some(f => f.path === path)) { map.removeLayer(layer); layers.delete(path); }
      el('radarSlider').max = String(frames.length - 1); el('radarSlider').disabled = false; el('radarPlay').disabled = frames.length < 2;
      el('radarStart').textContent = clock(frames[0].time * 1000); el('radarEnd').textContent = clock(frames[frames.length - 1].time * 1000);
      el('radarNote').textContent = `${data.stale ? 'ข้อมูลเก่า: อัปเดตเรดาร์ไม่ได้ · ' : ''}ดึงข้อมูล ${fullTime(data.updated_at)} · เรดาร์ไม่ใช่พยากรณ์อนาคต · บางพื้นที่อาจไม่มีข้อมูล · ซูมเกินระดับ 7 เป็นการขยายภาพเดิม`;
      showFrame(frames.length - 1);
    } catch (e) {
      stop(); radarMessage(e.message + ' · กดปุ่มโหลดใหม่ด้านล่าง');
      frames = [];
      if (activeLayer) { map.removeLayer(activeLayer); activeLayer = null; }
      el('radarSlider').disabled = true; el('radarPlay').disabled = true;
    } finally { radarLoading = false; el('radarRefresh').disabled = false; resumeRadar(); }
  }
  el('radarRefresh').addEventListener('click', loadRadar);
  const tabs = [el('forecastTab'), el('radarTab')];
  function selectTab(index, focus = false) {
    tabs.forEach((tab, i) => { tab.setAttribute('aria-selected', String(i === index)); tab.tabIndex = i === index ? 0 : -1; });
    el('radarPanel').hidden = index !== 1 && !window.matchMedia('(min-width: 1101px)').matches;
    el('forecastPanel').hidden = index !== 0;
    el('weatherWorkspace').classList.toggle('pw-radar-focus', index === 1);
    stop(); requestAnimationFrame(() => { map?.invalidateSize(); resumeRadar(); });
    if (focus) tabs[index].focus();
  }
  tabs.forEach((tab, i) => {
    tab.addEventListener('click', () => selectTab(i));
    tab.addEventListener('keydown', e => { if (['ArrowLeft', 'ArrowRight', 'Home', 'End'].includes(e.key)) { e.preventDefault(); selectTab(e.key === 'Home' ? 0 : e.key === 'End' ? 1 : 1 - i, true); } });
  });
  function scheduleRefresh() {
    clearTimeout(refreshTimer);
    refreshTimer = setTimeout(() => { if (!document.hidden) { loadForecast(); loadRadar(); } scheduleRefresh(); }, 900000);
  }
  document.addEventListener('visibilitychange', () => { if (document.hidden) stop(); else { resumeRadar(); if (forecast && Date.now() - new Date(forecast.updated_at).getTime() > 900000) { loadForecast(); loadRadar(); } } });
  window.addEventListener('pagehide', () => { stop(); clearTimeout(refreshTimer); clearTimeout(queueTimer); forecastController?.abort(); contextController?.abort(); });
  window.matchMedia('(min-width: 1101px)').addEventListener('change', () => selectTab(el('radarTab').getAttribute('aria-selected') === 'true' ? 1 : 0));
  window.addEventListener('online', () => { if (!forecast) loadForecast(); if (!frames.length) loadRadar(); });
  drawAreas(); initMap(); selectTab(0); loadForecast(); loadRadar(); scheduleRefresh();
})();
