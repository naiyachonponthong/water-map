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

function setup(wide = true) {
  const elements = new Map();
  const el = id => { if (!elements.has(id)) elements.set(id, new Element()); return elements.get(id); };
  const locations = [['trang', 'ตรัง'], ['chiang-mai', 'เชียงใหม่'], ['bangkok', 'กรุงเทพมหานคร']].map(([slug, name]) => ({
    slug, name, center: [7, 99], url: `/${slug}/weather`, homeUrl: `/${slug}`,
    forecastUrl: `/${slug}/weather/forecast.json`, contextUrl: `/${slug}/weather/context.json`, radarUrl: `/${slug}/weather/radar.json`,
  }));
  el('weatherLocations').textContent = JSON.stringify(locations);
  el('weatherPage').dataset = { slug: 'trang', lat: '7', lng: '99', hasCenter: '1', radarUrl: locations[0].radarUrl };
  el('forecastDay').select = true;
  el('currentTemperature').textContent = '—';
  const pending = [], historyCalls = [], media = { matches: wide, addEventListener() {} };
  const document = { title: '', hidden: false, getElementById: el, querySelectorAll: () => [], addEventListener() {} };
  const context = {
    document, window: { location: { origin: 'http://localhost:8003' }, matchMedia: () => media, addEventListener() {} },
    history: { replaceState: (...args) => historyCalls.push(args) },
    URL, AbortController, Intl, Date, Error, TypeError, SyntaxError,
    setTimeout: () => 1, clearTimeout() {}, requestAnimationFrame: fn => fn(),
    fetch: (url, options) => new Promise((resolve, reject) => pending.push({ url, options, resolve: data => resolve({ ok: true, json: async () => data }), reject })),
  };
  vm.runInNewContext(source, context);
  const change = slug => { el('weatherProvince').value = slug; el('weatherProvince').emit('change'); };
  return { el, pending, change, historyCalls, media, document };
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
