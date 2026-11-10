/* Content Planner Service Worker
 * - Static assets (CSS/JS/fonts/icons): cache-first with background revalidation
 * - Pages (HTML): network-first, falling back to cached copy, then offline.html
 * - Non-GET requests (mutations) are never cached
 * Increment VERSION when updating the PRECACHE list.
 */
const verParam = new URL(self.location.href).searchParams.get('v') || '1.1.0';
const VERSION = `cp-v2-${verParam}`;
const STATIC = `${VERSION}-static`;
const PAGES = `${VERSION}-pages`;
const scope = self.registration.scope;
const at = (p) => new URL(p, scope).href;

const PRECACHE = [
  'offline.html',
  'manifest.webmanifest',
  'assets/app.css',
  'assets/app.js',
  'assets/vendor/alpine.min.js',
  'assets/fonts/plus-jakarta-sans-latin-wght-normal.woff2',
  'assets/fonts/plus-jakarta-sans-latin-ext-wght-normal.woff2',
  'icons/icon.svg',
  'icons/icon-192.png',
  'icons/icon-512.png',
].map(at);

self.addEventListener('install', (e) => {
  e.waitUntil(caches.open(STATIC).then((c) => c.addAll(PRECACHE)).then(() => self.skipWaiting()));
});

self.addEventListener('activate', (e) => {
  e.waitUntil(
    caches.keys()
      .then((keys) => Promise.all(keys.filter((k) => !k.startsWith(VERSION)).map((k) => caches.delete(k))))
      .then(() => self.clients.claim())
  );
});

const isStatic = (url) => /\/(assets|icons)\//.test(url.pathname) || url.pathname.endsWith('manifest.webmanifest');

self.addEventListener('fetch', (e) => {
  const req = e.request;
  if (req.method !== 'GET') return;
  const url = new URL(req.url);
  if (url.origin !== location.origin) return;
  if (url.pathname.includes('/export/')) return; // downloads bypass service worker cache

  if (req.mode === 'navigate') {
    e.respondWith(
      fetch(req)
        .then((res) => {
          const type = res.headers.get('content-type') || '';
          const auth = /\/(login|register|logout)$/.test(url.pathname);
          if (res.ok && !res.redirected && !auth && type.includes('text/html')) {
            const copy = res.clone();
            caches.open(PAGES).then((c) => c.put(req, copy));
          }
          return res;
        })
        .catch(async () => (await caches.match(req)) || (await caches.match(at('offline.html'))))
    );
    return;
  }

  if (isStatic(url)) {
    e.respondWith(
      caches.match(req).then((hit) => {
        const net = fetch(req)
          .then((res) => {
            if (res.ok) caches.open(STATIC).then((c) => c.put(req, res.clone()));
            return res;
          })
          .catch(async () => hit || (await caches.match(req, { ignoreSearch: true })));
        return hit || net;
      })
    );
  }
});
