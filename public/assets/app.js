/* Content Planner — Alpine.js components (loaded BEFORE alpine.min.js) */
(() => {
  'use strict';
  const KEY = 'cp-theme';
  const root = document.documentElement;
  const base = document.body ? document.body.dataset.base || '' : '';
  const mq = matchMedia('(prefers-color-scheme: dark)');
  const readPref = () => { try { return localStorage.getItem(KEY) || 'system'; } catch { return 'system'; } };
  const wantsDark = (p) => p === 'dark' || (p === 'system' && mq.matches);

  /* ---------- PWA: capture install prompt event early ---------- */
  let deferredInstall = null;
  const installListeners = new Set();
  addEventListener('beforeinstallprompt', (e) => { e.preventDefault(); deferredInstall = e; installListeners.forEach((f) => f()); });
  addEventListener('appinstalled', () => { deferredInstall = null; installListeners.forEach((f) => f()); });

  /* ---------- Clear cached pages on logout so private data is never left behind ---------- */
  window.cpLogout = async (form) => {
    try {
      if ('caches' in window) {
        const keys = await caches.keys();
        await Promise.all(keys.filter((k) => k.endsWith('-pages')).map((k) => caches.delete(k)));
      }
    } catch { /* ignore */ }
    form.submit();
  };

  document.addEventListener('alpine:init', () => {
    const A = window.Alpine;

    /* ----- Theme: light / dark / system ----- */
    A.store('theme', {
      pref: readPref(),
      dark: root.classList.contains('dark'),
      apply(p) {
        this.pref = p;
        this.dark = wantsDark(p);
        root.classList.toggle('dark', this.dark);
        root.dataset.theme = p;
        const m = document.querySelector('meta[name=theme-color]');
        if (m) m.content = this.dark ? '#12306a' : '#2160d6';
      },
      set(p) {
        try { localStorage.setItem(KEY, p); } catch { /* private mode */ }
        this.apply(p);
      },
      toggle() { this.set(this.dark ? 'light' : 'dark'); },
    });
    mq.addEventListener?.('change', () => { if (A.store('theme').pref === 'system') A.store('theme').apply('system'); });
    addEventListener('storage', (e) => { if (e.key === KEY) A.store('theme').apply(readPref()); });

    /* ----- Toast notifications ----- */
    let tid = 0;
    A.store('toast', {
      items: [],
      push(msg, type = 'ok', ms = 3400) {
        const id = ++tid;
        this.items.push({ id, msg, type });
        setTimeout(() => this.dismiss(id), ms);
      },
      seed(list) { (list || []).forEach((f, i) => setTimeout(() => this.push(f.m, f.t), i * 250)); },
      dismiss(id) { this.items = this.items.filter((t) => t.id !== id); },
    });

    /* ----- Confirmation modal (replaces native browser confirm()) ----- */
    A.store('confirm', {
      open: false, msg: '', cb: null,
      ask(msg, cb) { this.msg = msg; this.cb = cb; this.open = true; },
      accept() { const cb = this.cb; this.close(); cb && cb(); },
      cancel() { this.close(); },
      close() { this.open = false; this.cb = null; },
    });
    // <form x-confirm="'Delete this item?'"> — submits form only after user confirms
    A.directive('confirm', (el, { expression }, { evaluate }) => {
      el.addEventListener('submit', (e) => {
        e.preventDefault();
        A.store('confirm').ask(evaluate(expression), () => el.submit());
      });
    });

    /* ----- PWA installation ----- */
    A.store('pwa', {
      can: !!deferredInstall,
      init() { installListeners.add(() => { this.can = !!deferredInstall; }); },
      async install() {
        if (!deferredInstall) return;
        deferredInstall.prompt();
        await deferredInstall.userChoice;
        deferredInstall = null; this.can = false;
      },
    });

    /* ----- $copy('text') ----- */
    A.magic('copy', () => async (text) => {
      try { await navigator.clipboard.writeText(text); }
      catch {
        const t = document.createElement('textarea');
        t.value = text; t.style.position = 'fixed'; t.style.opacity = '0';
        document.body.append(t); t.select(); document.execCommand('copy'); t.remove();
      }
      A.store('toast').push('Copied to clipboard');
    });

    /* ----- Instant client-side list filter ----- */
    // container: x-data="liveFilter"; input: x-model="q"; item: data-search="..." x-show="match($el)"
    A.data('liveFilter', (initial = '') => ({
      q: initial,
      norm(s) { return (s || '').toLowerCase(); },
      match(el) { const q = this.norm(this.q).trim(); return !q || (el.dataset.search || '').includes(q); },
      visible() { return [...this.$root.querySelectorAll('[data-search]')].filter((el) => this.match(el)).length; },
    }));

    /* ----- Character counter for textareas ----- */
    A.data('counter', (n = 0, max = 0) => ({ n, max, get over() { return this.max > 0 && this.n > this.max; } }));
  });

  /* ---------- Service worker registration ---------- */
  if ('serviceWorker' in navigator && (location.protocol === 'https:' || ['localhost', '127.0.0.1'].includes(location.hostname))) {
    addEventListener('load', () => {
      const b = document.body.dataset.base || base;
      navigator.serviceWorker.register(b + '/sw.js', { scope: b + '/' }).catch(() => {});
    });
  }
})();
