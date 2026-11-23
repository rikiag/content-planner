/* Content Planner Service Worker
 * - Static assets (CSS/JS/fonts/icons): cache-first with background revalidation
 * - Pages (HTML): network-first with timeout, falling back to cached copy, then offline.html.
 *   Cloudflare Tunnel "origin unreachable" responses (Error 1033 = HTTP 530, 502, 521, 522, 523)
 *   are treated exactly like a network failure, so the cached app keeps working while the home server is down.
 * - Mutations (POST) that cannot reach the origin are stored in an encrypted IndexedDB outbox
 *   (AES-GCM 256, non-extractable key) and replayed automatically when the server is reachable again.
 *   Sensitive routes (login, register, logout, account, export) are NEVER queued, and CSRF tokens are never stored.
 * Increment VERSION when updating the PRECACHE list.
 */
const verParam = new URL(self.location.href).searchParams.get('v') || '1.4.0';
const VERSION = `cp-v3-${verParam}`;
const STATIC = `${VERSION}-static`;
const PAGES = `${VERSION}-pages`;
const scope = self.registration.scope;
const scopePath = new URL(scope).pathname; // e.g. "/content-planner/"
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

/* Responses that mean "origin unreachable" (Cloudflare edge answered, home server did not).
 * 524 (origin timeout) and 520 (unknown) are excluded on purpose: the origin MAY have processed the request. */
const ORIGIN_DOWN = new Set([502, 503, 521, 522, 523, 530]);
const NAV_TIMEOUT = 10000;
const NO_QUEUE = /^\/(login|register|logout|account|export|api)(\/|$)/;
const MAX_AGE = 14 * 24 * 60 * 60 * 1000; // queued changes expire after 14 days
const MAX_ITEMS = 200;
const MAX_BYTES = 256 * 1024;

const relPath = (url) => '/' + url.pathname.slice(scopePath.length).replace(/^\/+/, '');
const inScope = (url) => url.origin === location.origin && url.href.startsWith(scope);

self.addEventListener('install', (e) => {
  e.waitUntil(caches.open(STATIC).then((c) => c.addAll(PRECACHE)).then(() => self.skipWaiting()));
});

self.addEventListener('activate', (e) => {
  e.waitUntil(
    caches.keys()
      .then((keys) => Promise.all(keys.filter((k) => !k.startsWith(VERSION)).map((k) => caches.delete(k))))
      .then(() => self.clients.claim())
      .then(() => syncOutbox().catch(() => {}))
  );
});

const isStatic = (url) => /\/(assets|icons)\//.test(url.pathname) || url.pathname.endsWith('manifest.webmanifest');

/* ====================== Encrypted IndexedDB outbox ====================== */
let dbPromise = null;
const db = () => dbPromise || (dbPromise = new Promise((resolve, reject) => {
  const r = indexedDB.open('cp-offline', 1);
  r.onupgradeneeded = () => {
    const d = r.result;
    if (!d.objectStoreNames.contains('meta')) d.createObjectStore('meta');
    if (!d.objectStoreNames.contains('outbox')) d.createObjectStore('outbox', { keyPath: 'id' });
  };
  r.onsuccess = () => resolve(r.result);
  r.onerror = () => { dbPromise = null; reject(r.error); };
}));
const idb = async (store, mode, fn) => {
  const d = await db();
  return new Promise((resolve, reject) => {
    const t = d.transaction(store, mode);
    const req = fn(t.objectStore(store));
    t.oncomplete = () => resolve(req ? req.result : undefined);
    t.onerror = () => reject(t.error);
    t.onabort = () => reject(t.error);
  });
};
const metaGet = (k) => idb('meta', 'readonly', (s) => s.get(k));
const metaSet = (k, v) => idb('meta', 'readwrite', (s) => s.put(v, k));
const metaDel = (k) => idb('meta', 'readwrite', (s) => s.delete(k));
const outboxAll = () => idb('outbox', 'readonly', (s) => s.getAll());
const outboxGet = (id) => idb('outbox', 'readonly', (s) => s.get(id));
const outboxPut = (rec) => idb('outbox', 'readwrite', (s) => s.put(rec));
const outboxDel = (id) => idb('outbox', 'readwrite', (s) => s.delete(id));
const outboxClear = () => idb('outbox', 'readwrite', (s) => s.clear());

/* The AES key is generated on this device, is non-extractable (cannot be read/exported by any script)
 * and never leaves the browser. Each record uses a random IV, and record id + user id are bound as AAD. */
let keyPromise = null;
const getKey = () => keyPromise || (keyPromise = (async () => {
  let key = await metaGet('key');
  if (!key) {
    key = await crypto.subtle.generateKey({ name: 'AES-GCM', length: 256 }, false, ['encrypt', 'decrypt']);
    await metaSet('key', key);
  }
  return key;
})().catch((err) => { keyPromise = null; throw err; }));
const te = new TextEncoder();
const td = new TextDecoder();
const seal = async (obj, aad) => {
  const iv = crypto.getRandomValues(new Uint8Array(12));
  const ct = await crypto.subtle.encrypt({ name: 'AES-GCM', iv, additionalData: te.encode(aad) }, await getKey(), te.encode(JSON.stringify(obj)));
  return { iv, ct };
};
const unseal = async (rec) => JSON.parse(td.decode(await crypto.subtle.decrypt(
  { name: 'AES-GCM', iv: rec.iv, additionalData: te.encode(`${rec.id}|${rec.uid}`) }, await getKey(), rec.ct)));

const labelFor = (path, f) => {
  const what = String(f.title || f.name || f.label || f.body || '').replace(/\s+/g, ' ').trim().slice(0, 60);
  let act = 'Update';
  if (/\/delete$/.test(path)) act = 'Delete';
  else if (/\/status$/.test(path)) act = 'Status change';
  else if (/\/duplicate$/.test(path)) act = 'Duplicate';
  else if (/\/convert$/.test(path)) act = 'Convert to content';
  else if (/\/pin$/.test(path)) act = 'Pin note';
  else if (/\/new$/.test(path) || /^\/(ideas|notes)$/.test(path)) act = 'Create';
  const area = { content: 'Content', campaigns: 'Campaign', ideas: 'Idea', notes: 'Note', resources: 'Resource', categories: 'Label' }[path.split('/')[1]] || 'Item';
  return `${act} · ${area}${what ? ` — ${what}` : ''}`;
};

const currentUid = () => metaGet('uid').catch(() => null);

const queueState = async () => {
  const uid = await currentUid();
  const all = (await outboxAll()).filter((r) => r.uid === uid);
  return { pending: all.filter((r) => r.status === 'pending').length, failed: all.filter((r) => r.status === 'failed').length };
};

const broadcast = async (extra = {}) => {
  let state = { pending: 0, failed: 0 };
  try { state = await queueState(); } catch { /* IndexedDB unavailable */ }
  const list = await self.clients.matchAll({ type: 'window', includeUncontrolled: true });
  list.forEach((c) => c.postMessage({ type: 'cp-queue', ...state, ...extra }));
};

/** Store a failed POST (form or AJAX). Returns the record, or null if it must not / cannot be queued. */
const queueRequest = async (req) => {
  const url = new URL(req.url);
  const path = relPath(url);
  if (!inScope(url) || NO_QUEUE.test(path)) return null;
  const uid = await currentUid();
  if (!uid) return null; // unknown user: never attribute data to the wrong account
  const type = req.headers.get('content-type') || '';
  if (!/application\/x-www-form-urlencoded|multipart\/form-data/i.test(type)) return null;

  const fd = await req.formData();
  const entries = [];
  let size = 0;
  for (const [k, v] of fd) {
    if (typeof v !== 'string') return null; // file uploads are not stored offline
    if (/pass(word)?/i.test(k)) return null; // never persist credentials
    if (k === '_csrf') continue; // a fresh token is fetched at sync time
    size += k.length + v.length;
    entries.push([k, v]);
  }
  if (size > MAX_BYTES) return null;
  const all = await outboxAll();
  if (all.length >= MAX_ITEMS) return null;

  const id = crypto.randomUUID();
  const rec = { id, uid, createdAt: Date.now(), status: 'pending', tries: 0, error: '' };
  Object.assign(rec, await seal({ path, search: url.search, entries, label: labelFor(path, Object.fromEntries(entries)) }, `${id}|${uid}`));
  await outboxPut(rec);
  try { await self.registration.sync?.register('cp-outbox'); } catch { /* Background Sync unsupported */ }
  broadcast({ server: false });
  return rec;
};

const jsonResponse = (data, status = 200) => new Response(JSON.stringify(data), {
  status, headers: { 'Content-Type': 'application/json; charset=utf-8', 'Cache-Control': 'no-store' },
});

/** Where to send the user after an offline form submission (always inside the app scope). */
const backTarget = (req, path) => {
  try {
    const ref = new URL(req.referrer);
    if (inScope(ref)) {
      const refPath = relPath(ref);
      const section = refPath.split('/')[1] || '';
      // Leaving a form page (new/edit/detail form) -> go back to its list, like the server would
      if (/\/(new|edit)$/.test(refPath) || /^\/(content|resources)\/\d+$/.test(refPath)) return at(section);
      return ref.href.split('#')[0];
    }
  } catch { /* no referrer */ }
  return at(path.split('/')[1] || '');
};

const handlePost = async (req, url) => {
  const backup = req.clone();
  let res = null;
  try {
    res = await fetch(req);
    if (!ORIGIN_DOWN.has(res.status)) return res;
  } catch { /* network failure: request never reached the origin */ }

  const ajax = (req.headers.get('x-requested-with') || '').toLowerCase() === 'xmlhttprequest';
  let rec = null;
  try { rec = await queueRequest(backup); } catch { rec = null; }

  if (ajax) {
    return rec
      ? jsonResponse({ ok: true, queued: true, msg: 'Offline — saved on this device. It will sync automatically.' }, 202)
      : jsonResponse({ ok: false, offline: true, msg: 'Server is unreachable and this action cannot be saved offline.' }, 503);
  }
  if (rec) return Response.redirect(`${backTarget(req, relPath(url))}#cp-queued`, 303);
  return (await caches.match(at('offline.html'))) || res || Response.error();
};

/* ====================== Sync (replay) ====================== */
const fetchSession = async () => {
  const r = await fetch(at('api/session'), { credentials: 'same-origin', cache: 'no-store', headers: { Accept: 'application/json' } });
  if (!r.ok || !(r.headers.get('content-type') || '').includes('application/json')) throw new Error('origin-down');
  return r.json();
};

let syncing = null;
const syncOutbox = () => syncing || (syncing = (async () => {
  const now = Date.now();
  const all = await outboxAll();
  for (const r of all) if (now - r.createdAt > MAX_AGE) await outboxDel(r.id); // expire stale changes
  let items = all.filter((r) => r.status === 'pending' && now - r.createdAt <= MAX_AGE).sort((a, b) => a.createdAt - b.createdAt);
  if (!items.length) return { synced: 0 };

  let sess;
  try { sess = await fetchSession(); } catch { return { offline: true }; }
  if (!sess.auth) return { needsLogin: true };
  items = items.filter((r) => r.uid === sess.uid); // only replay this user's own changes
  if (!items.length) return { synced: 0 };

  await broadcast({ server: true, syncing: true });
  let synced = 0;
  let failed = 0;
  for (const rec of items) {
    let payload;
    try { payload = await unseal(rec); } catch { await outboxDel(rec.id); continue; } // tampered/undecryptable: drop
    if (NO_QUEUE.test(payload.path)) { await outboxDel(rec.id); continue; }
    const target = at(payload.path.replace(/^\/+/, '') + (payload.search || ''));

    let res = null;
    for (let attempt = 0; attempt < 2; attempt++) {
      const body = new URLSearchParams();
      payload.entries.forEach(([k, v]) => body.append(k, v));
      body.set('_csrf', sess.csrf || '');
      try {
        res = await fetch(target, {
          method: 'POST', body, credentials: 'same-origin', redirect: 'follow', cache: 'no-store',
          headers: { 'X-CSRF-Token': sess.csrf || '', 'X-CP-Replay-Id': rec.id },
        });
      } catch { res = null; break; }
      if (res.status !== 419 || attempt) break;
      try { sess = await fetchSession(); } catch { res = null; break; } // CSRF rotated: refresh once
    }
    if (!res || ORIGIN_DOWN.has(res.status)) return { offline: true, synced, failed }; // keep the rest for later

    let ok = res.ok && !/\/login$/.test(new URL(res.url || target).pathname);
    let error = ok ? '' : (/\/login$/.test(new URL(res.url || target).pathname) ? 'Please sign in again.' : `Server responded with HTTP ${res.status}.`);
    if ((res.headers.get('content-type') || '').includes('application/json')) {
      try {
        const data = await res.json();
        if (data && data.ok === false) { ok = false; error = String(data.error || data.msg || error || 'Rejected by server.').slice(0, 200); }
      } catch { /* not JSON after all */ }
    }
    if (!ok && /sign in/i.test(error)) return { needsLogin: true, synced, failed };
    if (ok) { await outboxDel(rec.id); synced++; }
    else { await outboxPut({ ...rec, status: 'failed', tries: rec.tries + 1, error }); failed++; }
  }
  return { synced, failed };
})().then(async (result) => {
  syncing = null;
  await broadcast({
    syncing: false,
    ...(result.offline ? { server: false } : { server: true }),
    ...(result.needsLogin ? { needsLogin: true } : { needsLogin: false }),
    ...(result.synced ? { synced: result.synced } : {}),
  });
  return result;
}, async (err) => { syncing = null; await broadcast({ syncing: false }); throw err; }));

self.addEventListener('sync', (e) => {
  if (e.tag !== 'cp-outbox') return;
  e.waitUntil(syncOutbox().then((r) => { if (r && r.offline) throw new Error('origin-down'); })); // throwing = browser retries later
});

/* ====================== Pre-warm page cache ====================== */
const warmPages = async (paths) => {
  const cache = await caches.open(PAGES);
  for (const p of paths.slice(0, 15)) {
    if (typeof p !== 'string' || !/^\/[a-z0-9/_-]*$/i.test(p) || NO_QUEUE.test(p)) continue;
    const href = at(p.replace(/^\/+/, ''));
    try {
      const res = await fetch(href, { credentials: 'same-origin', cache: 'no-store' });
      const type = res.headers.get('content-type') || '';
      if (res.ok && !res.redirected && type.includes('text/html')) await cache.put(href, res);
    } catch { return; } // offline: stop warming
  }
};

/* ====================== Messages from pages ====================== */
self.addEventListener('message', (e) => {
  const msg = e.data || {};
  const reply = (data) => e.ports && e.ports[0] && e.ports[0].postMessage(data);
  const job = (async () => {
    switch (msg.type) {
      case 'cp-identity':
        if (typeof msg.uid === 'string' && /^[a-f0-9]{16,64}$/.test(msg.uid)) await metaSet('uid', msg.uid);
        return reply(await queueState());
      case 'cp-state':
        return reply(await queueState());
      case 'cp-sync':
        return reply(await syncOutbox().catch(() => ({ offline: true })));
      case 'cp-list': {
        const uid = await currentUid();
        const recs = (await outboxAll()).filter((r) => r.uid === uid).sort((a, b) => a.createdAt - b.createdAt);
        const items = [];
        for (const r of recs) {
          let label = 'Pending change';
          try { label = (await unseal(r)).label; } catch { /* undecryptable */ }
          items.push({ id: r.id, label, createdAt: r.createdAt, status: r.status, error: r.error });
        }
        return reply({ items });
      }
      case 'cp-discard':
        if (typeof msg.id === 'string') await outboxDel(msg.id);
        await broadcast();
        return reply({ ok: true });
      case 'cp-retry': {
        const r = typeof msg.id === 'string' ? await outboxGet(msg.id) : null;
        if (r) await outboxPut({ ...r, status: 'pending', error: '' });
        reply({ ok: true });
        return syncOutbox().catch(() => {});
      }
      case 'cp-warm':
        if (Array.isArray(msg.paths)) await warmPages(msg.paths);
        return reply({ ok: true });
      case 'cp-logout': // wipe everything private left on this device
        await outboxClear();
        await metaDel('uid');
        await metaDel('key');
        keyPromise = null;
        await Promise.all((await caches.keys()).filter((k) => k.endsWith('-pages')).map((k) => caches.delete(k)));
        return reply({ ok: true });
      default:
        return undefined;
    }
  })().catch(() => reply({ ok: false }));
  e.waitUntil(job);
});

/* ====================== Fetch routing ====================== */
const cachedPage = async (req) => (await caches.match(req)) || (await (await caches.open(PAGES)).match(req, { ignoreSearch: true }));

const handleNavigate = (e, req, url) => {
  const authPage = /\/(login|register|logout)$/.test(url.pathname);
  const network = fetch(req).then((res) => {
    if (ORIGIN_DOWN.has(res.status)) throw new Error('origin-down'); // e.g. Cloudflare Error 1033
    const type = res.headers.get('content-type') || '';
    if (res.ok && !res.redirected && !authPage && type.includes('text/html')) {
      const copy = res.clone();
      caches.open(PAGES).then((c) => c.put(req, copy)).catch(() => {});
    }
    return res;
  });
  e.waitUntil(network.then(() => {}, () => {}));
  return (async () => {
    const cached = authPage ? null : await cachedPage(req);
    try {
      if (!cached) return await network;
      return await Promise.race([network, new Promise((_, reject) => setTimeout(() => reject(new Error('timeout')), NAV_TIMEOUT))]);
    } catch {
      return cached || (await caches.match(at('offline.html'))) || Response.error();
    }
  })();
};

self.addEventListener('fetch', (e) => {
  const req = e.request;
  const url = new URL(req.url);
  if (url.origin !== location.origin) return;

  if (req.method === 'POST') {
    if (inScope(url) && !NO_QUEUE.test(relPath(url))) e.respondWith(handlePost(req, url));
    return;
  }
  if (req.method !== 'GET') return;
  if (url.pathname.includes('/export/') || url.pathname.includes('/api/')) return; // downloads & API bypass cache

  if (req.mode === 'navigate') {
    e.respondWith(handleNavigate(e, req, url));
    return;
  }

  if (isStatic(url)) {
    e.respondWith(
      caches.match(req).then((hit) => {
        const net = fetch(req)
          .then((res) => {
            if (res.ok && res.status === 200) {
              const copy = res.clone();
              caches.open(STATIC).then((c) => c.put(req, copy)).catch(() => {});
            }
            return res;
          })
          .catch(async () => hit || (await caches.match(req, { ignoreSearch: true })));
        return hit || net;
      })
    );
  }
});
