/* Service worker ของแอปภาคสนาม (scope /field)
   - หน้าแอปและสถานะทีม: เน็ตก่อน หลุดใช้ของที่เก็บไว้
   - ไฟล์ CSS/JS/ฟอนต์/ไลบรารี: ใช้ของในเครื่องก่อน แล้วอัปเดตเบื้องหลัง
   - แผ่นแผนที่: เก็บที่เคยดูไว้ (สูงสุด 1,500 แผ่น) ใช้ในพื้นที่ไม่มีสัญญาณ
   - POST (การกดปุ่ม) ไม่แตะ แอปมีคิวของตัวเอง */
const VERSION = 'field-v1';
const SHELL = `${VERSION}-shell`, ASSETS = `${VERSION}-assets`, TILES = `${VERSION}-tiles`;
const TILE_LIMIT = 1500;

self.addEventListener('install', e => {
  e.waitUntil(caches.open(SHELL).then(c => c.addAll(['/field', '/css/app.css', '/css/field.css', '/js/field.js', '/js/live.js', '/js/app.js', '/icons/icon-192.png']).catch(() => {})));
  self.skipWaiting();
});

self.addEventListener('activate', e => {
  e.waitUntil(caches.keys().then(keys => Promise.all(keys.filter(k => !k.startsWith(VERSION)).map(k => caches.delete(k)))).then(() => self.clients.claim()));
});

async function networkFirst(req, cacheName) {
  const cache = await caches.open(cacheName);
  try {
    const res = await fetch(req);
    if (res.ok) cache.put(req, res.clone());
    return res;
  } catch (_) {
    const hit = await cache.match(req, { ignoreSearch: true });
    return hit || new Response(JSON.stringify({ offline: true }), { status: 503, headers: { 'Content-Type': 'application/json' } });
  }
}

async function staleWhileRevalidate(req, cacheName, limit) {
  const cache = await caches.open(cacheName);
  const hit = await cache.match(req);
  const update = fetch(req).then(async res => {
    if (res.ok || res.type === 'opaque') {
      await cache.put(req, res.clone());
      if (limit) trim(cacheName, limit);
    }
    return res;
  }).catch(() => hit);
  return hit || update;
}

async function trim(cacheName, limit) {
  const cache = await caches.open(cacheName);
  const keys = await cache.keys();
  for (let i = 0; i < keys.length - limit; i++) await cache.delete(keys[i]);
}

self.addEventListener('fetch', e => {
  const req = e.request;
  if (req.method !== 'GET') return;
  const url = new URL(req.url);

  if (url.hostname.endsWith('tile.openstreetmap.org')) return e.respondWith(staleWhileRevalidate(req, TILES, TILE_LIMIT));
  if (url.origin === location.origin && (url.pathname === '/field' || url.pathname === '/field/' || url.pathname === '/field/api/state')) return e.respondWith(networkFirst(req, SHELL));
  if (url.origin === location.origin && /^\/(css|js|icons)\//.test(url.pathname)) return e.respondWith(staleWhileRevalidate(req, ASSETS));
  if (/cdn\.jsdelivr\.net|fonts\.(googleapis|gstatic)\.com/.test(url.hostname)) return e.respondWith(staleWhileRevalidate(req, ASSETS));
});
