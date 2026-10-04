// Unit checks with an in-memory DOM and mocked HTTP, not a browser/visual test.
const { test } = require('node:test');
const assert = require('node:assert/strict');
const fs = require('node:fs');
const vm = require('node:vm');
const path = require('node:path');
const source = fs.readFileSync(path.join(__dirname, '../../public/js/public-weather.js'), 'utf8');
const settle = () => new Promise(resolve => setImmediate(resolve));

class Element {
  constructor() { this.dataset = {}; this.attributes = {}; this.events = {}; this.hidden = false; this.value = ''; this.textContent = ''; this.classes = new Set(); this.classList = { toggle: (name, on) => on ? this.classes.add(name) : this.classes.delete(name) }; }
  set innerHTML(html) {
    this.html = html;
    if (this.select) this.value = /<option value="([^"]+)"/.exec(html)?.[1] || '';
  }
  get innerHTML() { return this.html || ''; }
  setAttribute(key, value) { this.attributes[key] = value; }
  getAttribute(key) { return this.attributes[key]; }
  addEventListener(name, fn) { this.events[name] = fn; }
  emit(name, event = {}) { return this.events[name]?.({ target: this, preventDefault() {}, ...event }); }
  querySelectorAll() { return []; }
  focus() { this.focused = true; }
}

function setup(wide = true, options = {}) {
  const elements = new Map();
  const el = id => { if (!elements.has(id)) elements.set(id, new Element()); return elements.get(id); };
  const locations = [['trang', 'ตรัง'], ['chiang-mai', 'เชียงใหม่'], ['bangkok', 'กรุงเทพมหานคร']].map(([slug, name]) => ({
    slug, name, center: [7, 99], url: `/${slug}/weather`, homeUrl: `/${slug}`,
    forecastUrl: `/${slug}/weather/forecast.json`, contextUrl: `/${slug}/weather/context.json`, radarUrl: `/${slug}/weather/radar.json`,
  }));
  el('weatherLocations').textContent = JSON.stringify(locations);
  el('weatherBoundaries').textContent = JSON.stringify({type: 'FeatureCollection', features: []});
  el('weatherAreas').textContent = JSON.stringify([{code: '9201', name: 'อ.เมืองตรัง', subdistricts: [{code: '920101', name: 'ต.ทับเที่ยง', center: [7.56, 99.61]}]}]);
  el('weatherPage').dataset = { slug: 'trang', lat: '7', lng: '99', hasCenter: '1', radarUrl: locations[0].radarUrl };
  el('forecastDay').select = true;
  el('currentTemperature').textContent = '—';
  const pending = [], historyCalls = [], media = { matches: wide, addEventListener() {} };
  const documentEvents = {}, windowEvents = {}, timers = new Map(); let timerId = 0;
  const document = { title: '', hidden: false, getElementById: el, querySelectorAll: () => [], addEventListener: (name, fn) => { documentEvents[name] = fn; } };
  const mapLayers = new Set(), mapEvents = {};
  const map = {setView() {return this;}, on(name, fn) {mapEvents[name] = fn;}, hasLayer: layer => mapLayers.has(layer), removeLayer: layer => mapLayers.delete(layer), invalidateSize() {}};
  class Layer {
    constructor() {this.events = {}; this.loading = false;}
    on(name, fn) {this.events[name] = fn; return this;}
    setOpacity(n) {this.opacity = n; return this;}
    addTo() {mapLayers.add(this); return this;}
    bindTooltip() {return this;}
    isLoading() {return this.loading;}
  }
  const radarLayers = [];
  class RadarLayer extends Layer {constructor() {super(); radarLayers.push(this);}}
  const context = {
    document, window: { location: { origin: 'http://localhost:8003' }, matchMedia: query => query.includes('reduced-motion') ? {matches: !!options.reduced} : media, addEventListener: (name, fn) => { windowEvents[name] = fn; } },
    history: { replaceState: (...args) => historyCalls.push(args) },
    URL, AbortController, Intl, Date, Error, TypeError, SyntaxError,
    setTimeout: (fn, delay) => { timers.set(++timerId, {fn, delay}); return timerId; }, clearTimeout: id => timers.delete(id), requestAnimationFrame: fn => fn(),
    fetch: (url, options) => new Promise((resolve, reject) => pending.push({ url, options, resolve: data => resolve({ ok: true, json: async () => data }), reject })),
  };
  if (options.radar) context.L = {map: () => map, tileLayer: () => new Layer(), circleMarker: () => new Layer(), geoJSON: () => new Layer(), TileLayer: {extend: () => RadarLayer}};
  vm.runInNewContext(source, context);
  const change = slug => { el('weatherProvince').value = slug; el('weatherProvince').emit('change'); };
  const runTimer = delay => { const entry = [...timers].find(([,t]) => t.delay === delay); assert.ok(entry, `Missing timer ${delay}`); timers.delete(entry[0]); entry[1].fn(); };
  return { el, pending, change, historyCalls, media, document, documentEvents, windowEvents, runTimer, radarLayers, mapEvents };
}

function forecast(temperature) {
  const now = new Date();
  const today = new Intl.DateTimeFormat('en-CA', { timeZone: 'Asia/Bangkok', year: 'numeric', month: '2-digit', day: '2-digit' }).format(now);
  const base = new Date(`${today}T00:00:00+07:00`);
  const days = Array.from({ length: 7 }, (_, i) => ({ date: new Date(+base + i * 86400000).toISOString().slice(0, 10), code: 61, rain_mm: 10 + i, probability: 80, temp_min: 25, temp_max: 32, wind_kmh: 10 }));
  // Keep test dates at Thai midnight, including the UTC previous-day offset.
  days.forEach((day, i) => { day.date = new Intl.DateTimeFormat('en-CA', { timeZone: 'Asia/Bangkok', year: 'numeric', month: '2-digit', day: '2-digit' }).format(new Date(+base + i * 86400000)); });
  return {
    current: { temperature, feels_like: 32, humidity: 80, code: 61, wind_kmh: 9, time: now.toISOString() },
    summary: { category: 'ฝนปานกลาง', rain_mm: 12, probability: 80, first_rain: now.toISOString() },
    days, hours: days.map(d => ({ time: `${d.date}T13:00:00+07:00`, code: 61, rain_mm: 1, probability: 80, temperature, wind_kmh: 9, wind_direction: 240 })),
    updated_at: now.toISOString(), stale: false,
  };
}

test('seven days, current conditions, hourly details and date selection', async () => {
  const app = setup();
  const data = forecast(28);
  app.pending[0].resolve(data); await settle();
  assert.equal(app.el('currentTemperature').textContent, '28');
  assert.equal((app.el('weatherDays').innerHTML.match(/data-day=/g) || []).length, 7);
  assert.match(app.el('forecastHours').innerHTML, /weatherHour0/);
  app.el('forecastDay').value = data.days[3].date; app.el('forecastDay').emit('change');
  assert.match(app.el('forecastDayMetrics').innerHTML, /13 มม\./);
  assert.equal(app.el('forecastPanel').hidden, false);
  assert.equal(app.el('radarPanel').hidden, false);
});

test('province changes clear values immediately and late responses cannot overwrite the newest province', async () => {
  const app = setup();
  app.pending[0].resolve(forecast(28)); await settle();
  app.change('chiang-mai');
  assert.equal(app.el('currentTemperature').textContent, '—');
  const chiangMai = app.pending.find(p => p.url === '/chiang-mai/weather/forecast.json');
  app.change('bangkok');
  assert.equal(chiangMai.options.signal.aborted, true);
  const bangkok = app.pending.find(p => p.url === '/bangkok/weather/forecast.json');
  bangkok.resolve(forecast(31)); await settle();
  chiangMai.resolve(forecast(22)); await settle();
  assert.equal(app.el('currentTemperature').textContent, '31');
  assert.equal(app.el('weatherProvinceTitle').textContent, 'กรุงเทพมหานคร');
  assert.equal(app.el('weatherHome').href, '/bangkok');
  assert.equal(app.historyCalls.at(-1)[2], '/bangkok/weather');
  assert.equal(app.el('forecastRefresh').disabled, false);
});

test('mobile view switches between forecast and radar; keyboard Home chooses the first visible tab', () => {
  const app = setup(false);
  assert.equal(app.el('radarPanel').hidden, true);
  app.el('radarTab').emit('click');
  assert.equal(app.el('forecastPanel').hidden, true);
  assert.equal(app.el('radarPanel').hidden, false);
  app.el('radarTab').emit('keydown', { key: 'Home' });
  assert.equal(app.el('forecastPanel').hidden, false);
  assert.equal(app.el('forecastTab').focused, true);
});

test('network failures show a Thai retry message instead of fabricated weather', async () => {
  const app = setup();
  app.pending[0].reject(new TypeError('Failed to fetch')); await settle();
  assert.match(app.el('weatherUpdated').textContent, /เชื่อมต่อระบบไม่ได้/);
  assert.equal(app.el('forecastRetry').hidden, false);
  assert.equal(app.el('currentTemperature').textContent, '—');
  assert.match(app.el('forecastHours').innerHTML, /ยังไม่มีพยากรณ์/);
});

function radarData() {
  const now = Math.floor(Date.now()/1000);
  return {host: 'https://tilecache.rainviewer.com', frames: [0,1,2].map(i => ({time: now - (2-i)*600, path: `/v2/radar/abcdef00${i}`})), updated_at: new Date().toISOString(), stale: false};
}

test('radar autoplays, waits for loading tiles, suspends in hidden tabs and respects manual pause', async () => {
  const app = setup(true, {radar: true});
  app.pending.find(p => p.url.includes('radar.json')).resolve(radarData()); await settle();
  assert.equal(app.el('radarPlay').getAttribute('aria-pressed'), 'true');
  assert.equal(app.el('radarSlider').value, '2');
  app.radarLayers[0].loading = true; app.runTimer(1500);
  assert.equal(app.el('radarSlider').value, '2');
  app.radarLayers[0].loading = false; app.runTimer(500);
  assert.equal(app.el('radarSlider').value, '0');
  app.document.hidden = true; app.documentEvents.visibilitychange();
  assert.equal(app.el('radarPlay').getAttribute('aria-pressed'), 'false');
  app.document.hidden = false; app.documentEvents.visibilitychange();
  assert.equal(app.el('radarPlay').getAttribute('aria-pressed'), 'true');
  app.el('radarPlay').emit('click');
  app.documentEvents.visibilitychange();
  assert.equal(app.el('radarPlay').getAttribute('aria-pressed'), 'false');
});

test('mobile radar only plays while visible and reduced motion needs manual play', async () => {
  const app = setup(false, {radar: true});
  app.pending.find(p => p.url.includes('radar.json')).resolve(radarData()); await settle();
  assert.equal(app.el('radarPlay').getAttribute('aria-pressed'), 'false');
  app.el('radarTab').emit('click');
  assert.equal(app.el('radarPlay').getAttribute('aria-pressed'), 'true');
  app.el('forecastTab').emit('click');
  assert.equal(app.el('radarPlay').getAttribute('aria-pressed'), 'false');
  const reduced = setup(true, {radar: true, reduced: true});
  reduced.pending.find(p => p.url.includes('radar.json')).resolve(radarData()); await settle();
  assert.equal(reduced.el('radarPlay').getAttribute('aria-pressed'), 'false');
  reduced.el('radarPlay').emit('click');
  assert.equal(reduced.el('radarPlay').getAttribute('aria-pressed'), 'true');
});

test('subdistrict changes request its coordinates via scoped codes and clear earlier weather', async () => {
  const app = setup();
  app.pending[0].resolve(forecast(28)); await settle();
  app.el('weatherDistrict').value = '9201'; app.el('weatherDistrict').emit('change');
  app.el('weatherSubdistrict').value = '920101'; app.el('weatherSubdistrict').emit('change');
  assert.equal(app.el('currentTemperature').textContent, '—');
  const request = app.pending.find(p => p.url.includes('subdistrict=920101'));
  assert.ok(request); assert.match(request.url, /district=9201/);
  const data = forecast(25); data.location = {name: 'ต.ทับเที่ยง · อ.เมืองตรัง · ตรัง'};
  request.resolve(data); await settle();
  assert.equal(app.el('currentTemperature').textContent, '25');
  assert.match(app.el('weatherCity').textContent, /ทับเที่ยง/);
});
