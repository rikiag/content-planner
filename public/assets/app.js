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
  addEventListener('beforeinstallprompt', (e) => { deferredInstall = e; installListeners.forEach((f) => f()); });
  addEventListener('appinstalled', () => { deferredInstall = null; installListeners.forEach((f) => f()); });

  /* ---------- Service Worker messaging (offline outbox lives in the SW, encrypted in IndexedDB) ---------- */
  const swSupported = 'serviceWorker' in navigator && (location.protocol === 'https:' || ['localhost', '127.0.0.1'].includes(location.hostname));
  const swCall = (msg, timeout = 4000) => new Promise((resolve) => {
    if (!swSupported) { resolve(null); return; }
    const timer = setTimeout(() => resolve(null), timeout);
    navigator.serviceWorker.ready.then((reg) => {
      if (!reg.active) { clearTimeout(timer); resolve(null); return; }
      const ch = new MessageChannel();
      ch.port1.onmessage = (ev) => { clearTimeout(timer); resolve(ev.data); };
      reg.active.postMessage(msg, [ch.port2]);
    }).catch(() => { clearTimeout(timer); resolve(null); });
  });

  /* ---------- Logout: wipe cached pages + unsynced offline changes so private data is never left behind ---------- */
  window.cpLogout = async (form) => {
    const wipe = async () => {
      await swCall({ type: 'cp-logout' }, 2500);
      try {
        if ('caches' in window) {
          const keys = await caches.keys();
          await Promise.all(keys.filter((k) => k.endsWith('-pages')).map((k) => caches.delete(k)));
        }
      } catch { /* ignore */ }
      form.submit();
    };
    const st = await swCall({ type: 'cp-state' }, 1500);
    const n = st ? (st.pending || 0) + (st.failed || 0) : 0;
    const confirmStore = window.Alpine && window.Alpine.store('confirm');
    if (n > 0 && confirmStore) {
      confirmStore.ask(`You have ${n} unsynced change${n > 1 ? 's' : ''} saved on this device. Logging out will permanently delete ${n > 1 ? 'them' : 'it'}. Continue?`, wipe);
      return;
    }
    wipe();
  };

  /* ---------- Date helpers ---------- */
  const MONTHS_LONG = ['January', 'February', 'March', 'April', 'May', 'June', 'July', 'August', 'September', 'October', 'November', 'December'];
  const MONTHS_SHORT = ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'];
  const DAYS_SHORT = ['Sun', 'Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat'];
  const DAYS_LONG = ['Sunday', 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday'];

  const parseYmd = (ymd) => {
    const m = /^(\d{4})-(\d{2})-(\d{2})$/.exec(ymd || '');
    if (!m) return new Date();
    return new Date(Number(m[1]), Number(m[2]) - 1, Number(m[3]));
  };
  const fmtYmd = (d) => {
    const y = d.getFullYear();
    const m = String(d.getMonth() + 1).padStart(2, '0');
    const day = String(d.getDate()).padStart(2, '0');
    return `${y}-${m}-${day}`;
  };
  const parseSchedMs = (s) => {
    if (!s) return NaN;
    const d = new Date(String(s).replace(' ', 'T'));
    return Number.isNaN(d.getTime()) ? NaN : d.getTime();
  };
  const readSavedView = (key) => {
    if (!key) return '';
    try {
      const v = localStorage.getItem(key);
      return ['list', 'board', 'calendar'].includes(v) ? v : '';
    } catch { return ''; }
  };
  const saveView = (key, v) => {
    if (!key) return;
    try { localStorage.setItem(key, v); } catch { /* private mode */ }
  };

  /* ---------- Helper for AJAX POST requests ---------- */
  /* Uses the freshest CSRF token known (pages served from the offline cache may embed an older one). */
  const netFail = () => { try { window.Alpine && window.Alpine.store('net') && window.Alpine.store('net').fail(); } catch { /* ignore */ } };
  const postAjax = async (url, data, csrf) => {
    const token = window.cpCsrf || csrf || '';
    const fd = new FormData();
    fd.append('_csrf', token);
    fd.append('_ajax', '1');
    Object.entries(data || {}).forEach(([k, v]) => fd.append(k, v ?? ''));
    let res;
    try {
      res = await fetch(url, {
        method: 'POST',
        headers: { 'X-Requested-With': 'XMLHttpRequest', 'X-CSRF-Token': token },
        body: fd,
      });
    } catch (err) { netFail(); throw err; }
    let json;
    try { json = await res.json(); } catch (err) { if (res.status >= 500) netFail(); throw err; }
    if (json && (json.queued || json.offline)) netFail();
    return json;
  };

  document.addEventListener('alpine:init', () => {
    const A = window.Alpine;

    /* ----- Lightweight, XSS-safe Markdown renderer + simple editor ----- */
    const mdEsc = (s) => s.replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;');
    const mdInline = (s) => {
      s = mdEsc(s);
      s = s.replace(/`([^`]+)`/g, '<code>$1</code>');
      s = s.replace(/\*\*([^*]+)\*\*/g, '<strong>$1</strong>');
      s = s.replace(/(^|[^*])\*([^*\s][^*]*)\*/g, '$1<em>$2</em>');
      s = s.replace(/\[([^\]]+)\]\(((?:https?:\/\/|mailto:)[^\s)]+)\)/g, '<a href="$2" target="_blank" rel="noopener noreferrer">$1</a>');
      return s;
    };
    const cpMarkdown = (text) => {
      const lines = String(text || '').replace(/\r\n?/g, '\n').split('\n');
      const out = [];
      let list = null;
      let para = [];
      const flushPara = () => { if (para.length) { out.push('<p>' + para.map(mdInline).join('<br>') + '</p>'); para = []; } };
      const flushList = () => { if (list) { out.push(`</${list}>`); list = null; } };
      for (const line of lines) {
        let m;
        if (!line.trim()) { flushPara(); flushList(); continue; }
        if ((m = /^(#{1,3})\s+(.*)$/.exec(line))) { flushPara(); flushList(); out.push(`<h${m[1].length + 2}>${mdInline(m[2])}</h${m[1].length + 2}>`); continue; }
        if ((m = /^\s*[-*]\s+(.*)$/.exec(line))) { flushPara(); if (list !== 'ul') { flushList(); out.push('<ul>'); list = 'ul'; } out.push('<li>' + mdInline(m[1]) + '</li>'); continue; }
        if ((m = /^\s*\d+\.\s+(.*)$/.exec(line))) { flushPara(); if (list !== 'ol') { flushList(); out.push('<ol>'); list = 'ol'; } out.push('<li>' + mdInline(m[1]) + '</li>'); continue; }
        if ((m = /^>\s?(.*)$/.exec(line))) { flushPara(); flushList(); out.push('<blockquote>' + mdInline(m[1]) + '</blockquote>'); continue; }
        flushList();
        para.push(line);
      }
      flushPara(); flushList();
      return out.join('');
    };
    window.cpMarkdown = cpMarkdown;
    A.magic('md', () => cpMarkdown);

    A.data('mdEditor', (max = 0) => ({
      preview: false,
      src: '',
      n: 0,
      max,
      init() { this.src = this.$refs.ta.value; this.n = this.src.length; },
      sync() { this.src = this.$refs.ta.value; this.n = this.src.length; this.$refs.ta.dispatchEvent(new Event('input', { bubbles: true })); },
      wrap(pre, post) {
        const ta = this.$refs.ta; const s = ta.selectionStart; const e = ta.selectionEnd;
        const sel = ta.value.slice(s, e) || 'text';
        ta.setRangeText(pre + sel + post, s, e, 'end');
        ta.focus(); ta.setSelectionRange(s + pre.length, s + pre.length + sel.length);
        this.sync();
      },
      line(prefix) {
        const ta = this.$refs.ta; const s = ta.selectionStart;
        const start = ta.value.lastIndexOf('\n', s - 1) + 1;
        ta.setRangeText(prefix, start, start, 'end');
        ta.focus(); this.sync();
      },
      link() {
        const ta = this.$refs.ta; const s = ta.selectionStart; const e = ta.selectionEnd;
        const sel = ta.value.slice(s, e) || 'link text';
        ta.setRangeText(`[${sel}](https://)`, s, e, 'end');
        ta.focus(); this.sync();
      },
    }));

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
    A.directive('confirm', (el, { expression }, { evaluate }) => {
      el.addEventListener('submit', (e) => {
        e.preventDefault();
        A.store('confirm').ask(evaluate(expression), () => el.submit());
      });
    });

    /* ----- Global Content Detail Modal ----- */
    const modalListeners = new Set();
    A.store('contentModal', {
      open: false,
      item: null,
      csrf: '',
      baseUrl: '',
      statuses: [],
      initConfig(cfg) {
        this.csrf = cfg.csrf || '';
        this.baseUrl = cfg.baseUrl || '';
        this.statuses = cfg.statuses || [];
      },
      show(item) {
        if (!item) return;
        this.item = { ...item };
        this.open = true;
      },
      close() {
        this.open = false;
      },
      url(path) {
        return (this.baseUrl || '') + path;
      },
      statusChip(key) {
        const found = (this.statuses || []).find((s) => s.key === key);
        return found ? found.chip : (this.item?.status_chip || 'bg-soft text-muted');
      },
      onUpdate(fn) {
        modalListeners.add(fn);
      },
      async changeStatus(newStatus) {
        if (!this.item) return;
        try {
          const data = await postAjax(this.url(`/content/${this.item.id}/status`), { status: newStatus }, this.csrf);
          if (data.ok) {
            if (data.item) {
              this.item = { ...this.item, ...data.item };
            } else {
              const st = (this.statuses || []).find((s) => s.key === newStatus);
              this.item.status = newStatus;
              if (st) {
                this.item.status_label = st.label;
                this.item.status_chip = st.chip;
                this.item.status_dot = st.dot;
              }
            }
            modalListeners.forEach((fn) => fn(this.item));
            A.store('toast').push(data.msg || 'Status updated');
          } else {
            A.store('toast').push(data.msg || 'Could not update status', 'err');
          }
        } catch {
          A.store('toast').push('Network error while updating status', 'err');
        }
      },
    });

    /* ----- Offline / server-down status + outbox sync (see public/sw.js) ----- */
    const plural = (n, w) => `${n} ${w}${n === 1 ? '' : 's'}`;
    A.store('net', {
      online: navigator.onLine,
      server: true,
      checking: false,
      pending: 0,
      failed: 0,
      syncing: false,
      needsLogin: false,
      synced: 0,
      open: false,
      items: [],
      get offline() { return !this.online || !this.server; },
      get tone() {
        if (this.offline) return 'off';
        if (this.needsLogin && this.pending) return 'warn';
        if (this.syncing || this.pending) return 'sync';
        if (this.failed) return 'warn';
        if (this.synced) return 'ok';
        return '';
      },
      get visible() { return this.tone !== ''; },
      get title() {
        switch (this.tone) {
          case 'off': return this.online ? 'Server is offline.' : 'Your device is offline.';
          case 'warn': return this.needsLogin ? 'Sign in again to sync.' : `${plural(this.failed, 'change')} could not be synced.`;
          case 'sync': return this.syncing ? 'Back online — syncing…' : `${plural(this.pending, 'change')} waiting to sync.`;
          case 'ok': return `All changes synced (${this.synced}).`;
          default: return '';
        }
      },
      get detail() {
        switch (this.tone) {
          case 'off': return 'Your changes are saved securely on this device and will be synced automatically when the connection is back.';
          case 'warn': return this.needsLogin ? 'Your session has expired. Unsynced changes are kept safely on this device.' : 'Open Details to retry or discard them.';
          case 'sync': return 'Sending changes saved on this device to the server.';
          case 'ok': return 'Reload to see the latest data.';
          default: return '';
        }
      },
      loginUrl() { return `${document.body?.dataset.base || base}/login`; },
      ago(ts) {
        const s = Math.max(0, Math.round((Date.now() - ts) / 1000));
        if (s < 60) return 'just now';
        if (s < 3600) return `${Math.round(s / 60)} min ago`;
        if (s < 86400) return `${Math.round(s / 3600)} h ago`;
        return new Date(ts).toLocaleString();
      },
      apply(d) {
        if (!d) return;
        if (typeof d.pending === 'number') this.pending = d.pending;
        if (typeof d.failed === 'number') this.failed = d.failed;
        if (typeof d.syncing === 'boolean') this.syncing = d.syncing;
        if (typeof d.needsLogin === 'boolean') this.needsLogin = d.needsLogin;
        if (d.server === false) this.server = false;
        if (d.server === true) this.server = true;
        if (d.synced) {
          this.synced += d.synced;
          A.store('toast').push(`${plural(d.synced, 'offline change')} synced to the server`);
          clearTimeout(this._okTimer);
          this._okTimer = setTimeout(() => { this.synced = 0; }, 10000);
        }
        if (this.open) this.refreshList();
      },
      fail() {
        this.server = false;
        clearTimeout(this._failTimer);
        this._failTimer = setTimeout(() => this.check(), 4000);
      },
      /** Health check against /api/session (also refreshes the CSRF token and account identity). */
      async check(manual = false) {
        if (this.checking) return null;
        this.online = navigator.onLine;
        this.checking = true;
        const ctl = new AbortController();
        const timer = setTimeout(() => ctl.abort(), 6000);
        let d = null;
        try {
          const res = await fetch(`${document.body?.dataset.base || base}/api/session`, {
            cache: 'no-store', credentials: 'same-origin', headers: { Accept: 'application/json' }, signal: ctl.signal,
          });
          if (!res.ok || !(res.headers.get('content-type') || '').includes('application/json')) throw new Error('down');
          d = await res.json();
        } catch { d = null; } finally { clearTimeout(timer); this.checking = false; }

        const wasDown = !this.server;
        this.server = !!d;
        if (d) {
          this.online = true;
          if (d.csrf) window.cpCsrf = d.csrf;
          if (d.uid) swCall({ type: 'cp-identity', uid: d.uid }).then((st) => this.apply(st));
          this.needsLogin = !d.auth && !!document.body?.dataset.uid;
          if ((wasDown || manual || this.pending) && !this.needsLogin) this.sync();
        } else if (manual) {
          A.store('toast').push('Still offline. Your changes stay saved on this device.', 'err');
        }
        return d;
      },
      sync() {
        if (this.offline || this.syncing) return;
        swCall({ type: 'cp-sync' }, 120000).then((r) => {
          if (r && r.offline) this.server = false;
          if (r && r.needsLogin) this.needsLogin = true;
        });
      },
      async toggle() {
        this.open = !this.open;
        if (this.open) await this.refreshList();
      },
      async refreshList() {
        const r = await swCall({ type: 'cp-list' });
        this.items = (r && r.items) || [];
      },
      discard(it) {
        A.store('confirm').ask(`Discard “${it.label}”? This change will be lost.`, async () => {
          await swCall({ type: 'cp-discard', id: it.id });
          await this.refreshList();
        });
      },
      async retry(id) {
        if (this.offline) { this.check(true); return; }
        await swCall({ type: 'cp-retry', id });
        await this.refreshList();
      },
    });

    if (swSupported) {
      navigator.serviceWorker.addEventListener('message', (ev) => {
        if (ev.data && ev.data.type === 'cp-queue') A.store('net').apply(ev.data);
      });
    }
    const net = A.store('net');
    addEventListener('online', () => { net.online = true; net.check(); });
    addEventListener('offline', () => { net.online = false; });
    document.addEventListener('visibilitychange', () => { if (document.visibilityState === 'visible') net.check(); });
    // While offline (or with unsynced changes), probe the server periodically to detect recovery quickly.
    setInterval(() => { if (document.visibilityState === 'visible' && (net.offline || net.pending) && !net.syncing) net.check(); }, 15000);

    const uid = document.body?.dataset.uid || '';
    swCall(uid ? { type: 'cp-identity', uid } : { type: 'cp-state' }).then((st) => net.apply(st));
    net.check().then((d) => {
      // Pre-cache the main pages once in a while so they open even when the home server is down.
      if (!d || !d.auth || !uid || !swSupported) return;
      const k = `cp-warm-at:${uid}`;
      let last = 0;
      try { last = Number(localStorage.getItem(k) || 0); } catch { /* private mode */ }
      if (Date.now() - last < 6 * 3600 * 1000) return;
      try { localStorage.setItem(k, String(Date.now())); } catch { /* private mode */ }
      swCall({ type: 'cp-warm', paths: ['/', '/content', '/content/new', '/campaigns', '/campaigns/new', '/ideas', '/notes', '/categories', '/resources', '/resources/new', '/analytics'] }, 120000);
    });
    if (location.hash === '#cp-queued') {
      history.replaceState(history.state, '', location.pathname + location.search);
      setTimeout(() => A.store('toast').push('Saved offline on this device. It will sync automatically when the server is back.', 'ok', 6000), 300);
    }

    /* -----PWA installation ----- */
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

    /* ----- Release / Changelog "New" indicator (localStorage) ----- */
    const VER_KEY = 'cp-seen-version';
    const currentVer = document.body?.dataset.version || '';
    const readSeenVer = () => { try { return localStorage.getItem(VER_KEY) || ''; } catch { return ''; } };
    A.store('changelog', {
      current: currentVer,
      seen: readSeenVer(),
      get hasNew() {
        return !!this.current && this.seen !== this.current;
      },
      markSeen() {
        if (!this.current) return;
        this.seen = this.current;
        try { localStorage.setItem(VER_KEY, this.current); } catch { /* private mode */ }
      },
    });
    if (/\/changelog\/?$/.test(location.pathname)) {
      A.store('changelog').markSeen();
    }

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
    A.data('liveFilter', (initial = '') => ({
      q: initial,
      norm(s) { return (s || '').toLowerCase(); },
      match(el) { const q = this.norm(this.q).trim(); return !q || (el.dataset.search || '').includes(q); },
      visible() { return [...this.$root.querySelectorAll('[data-search]')].filter((el) => this.match(el)).length; },
    }));

    /* ----- Character counter for textareas ----- */
    A.data('counter', (n = 0, max = 0) => ({ n, max, get over() { return this.max > 0 && this.n > this.max; } }));

    /* ----- Shared Content Workspace (List + Order By + Board drag & drop + Calendar Day/Week/Month) ----- */
    const createContentWorkspace = (cfg, opts = {}) => {
      const viewKey = opts.viewStorageKey || '';
      const hasCampaignFilter = !!opts.hasCampaignFilter;
      const savedView = !cfg.hasExplicitView ? readSavedView(viewKey) : '';
      const initialView = cfg.hasExplicitView ? (cfg.view || 'list') : (savedView || cfg.view || 'list');

      return {
        items: cfg.items || [],
        statuses: cfg.statuses || [],
        platforms: cfg.platforms || [],
        campaigns: cfg.campaigns || [],
        view: initialView,
        status: cfg.status || '',
        platform: cfg.platform || '',
        campaign: hasCampaignFilter ? String(cfg.campaign || '') : '',
        q: cfg.q || '',
        sort: cfg.sort || 'closest',
        page: Number(cfg.page || 1),
        perPage: Number(cfg.perPage || 8),
        calMode: cfg.calMode || 'month',
        selectedDate: cfg.selectedDate || cfg.today || fmtYmd(new Date()),
        today: cfg.today || fmtYmd(new Date()),
        csrf: cfg.csrf || '',
        baseUrl: cfg.baseUrl || '',
        draggingId: null,
        dragOverCol: null,

        initWorkspace() {
          A.store('contentModal').onUpdate((updated) => {
            const idx = this.items.findIndex((i) => Number(i.id) === Number(updated.id));
            if (idx !== -1) this.items[idx] = { ...this.items[idx], ...updated };
          });
          addEventListener('popstate', () => {
            const u = new URL(location.href);
            this.view = u.searchParams.get('view') || readSavedView(viewKey) || 'list';
            this.status = u.searchParams.get('status') || '';
            this.platform = u.searchParams.get('platform') || '';
            if (hasCampaignFilter) this.campaign = u.searchParams.get('campaign') || '';
            this.q = u.searchParams.get('q') || '';
            this.sort = u.searchParams.get('sort') || 'closest';
            this.page = Math.max(1, Number(u.searchParams.get('page') || 1));
            this.calMode = u.searchParams.get('cal_mode') || 'month';
            this.selectedDate = u.searchParams.get('date') || this.today;
          });
        },

        syncUrl(push = false) {
          const u = new URL(location.href);
          const setOrDel = (k, v, def = '') => {
            if (v !== undefined && v !== null && String(v) !== String(def) && String(v) !== '') u.searchParams.set(k, String(v));
            else u.searchParams.delete(k);
          };
          setOrDel('view', this.view, viewKey ? '' : 'list');
          setOrDel('status', this.status, '');
          setOrDel('platform', this.platform, '');
          if (hasCampaignFilter) setOrDel('campaign', this.campaign, '');
          setOrDel('q', this.q.trim(), '');
          setOrDel('sort', this.sort, 'closest');
          setOrDel('page', this.view === 'list' ? this.page : 1, 1);
          if (this.view === 'calendar') {
            setOrDel('cal_mode', this.calMode, 'month');
            setOrDel('date', this.selectedDate, this.today);
            u.searchParams.set('ym', this.selectedDate.slice(0, 7));
          } else {
            u.searchParams.delete('cal_mode');
            u.searchParams.delete('date');
            u.searchParams.delete('ym');
          }
          if (push) history.pushState({}, '', u);
          else history.replaceState({}, '', u);
        },

        setView(v) {
          this.view = v;
          saveView(viewKey, v);
          this.syncUrl(true);
        },
        setStatus(s) {
          this.status = s;
          this.page = 1;
          this.syncUrl(true);
        },
        viewAllStatus(s) {
          this.status = s;
          this.page = 1;
          this.view = 'list';
          saveView(viewKey, 'list');
          this.syncUrl(true);
        },
        onFilterChange() {
          this.page = 1;
          this.syncUrl(false);
        },
        setPage(p) {
          const max = this.totalPages();
          this.page = Math.min(Math.max(1, p), max);
          this.syncUrl(true);
        },

        matchesBaseFilters(item) {
          if (this.platform && item.platform !== this.platform) return false;
          if (hasCampaignFilter && this.campaign && this.campaign !== '0' && String(item.campaign_id) !== String(this.campaign)) return false;
          const q = (this.q || '').toLowerCase().trim();
          if (q) {
            const hay = `${item.title || ''} ${item.caption || ''} ${item.hashtags || ''} ${item.pillar || ''} ${item.format || ''} ${item.platform || ''} ${item.campaign_name || ''}`.toLowerCase();
            if (!hay.includes(q)) return false;
          }
          return true;
        },

        sortItems(list) {
          const mode = this.sort || 'closest';
          const now = Date.now();
          return [...list].sort((a, b) => {
            if (mode === 'title_asc') {
              return String(a.title || '').localeCompare(String(b.title || '')) || (b.id - a.id);
            }
            if (mode === 'newest') return b.id - a.id;
            if (mode === 'views_desc') {
              const vd = (b.views || 0) - (a.views || 0);
              if (vd !== 0) return vd;
            }
            const ta = parseSchedMs(a.scheduled_at);
            const tb = parseSchedMs(b.scheduled_at);
            const aHas = !Number.isNaN(ta);
            const bHas = !Number.isNaN(tb);
            if (!aHas && !bHas) return b.id - a.id;
            if (!aHas) return 1;
            if (!bHas) return -1;
            if (mode === 'sched_asc') return (ta - tb) || (b.id - a.id);
            if (mode === 'sched_desc') return (tb - ta) || (b.id - a.id);
            const da = Math.abs(ta - now);
            const db = Math.abs(tb - now);
            if (da !== db) return da - db;
            if ((ta >= now) !== (tb >= now)) return ta >= now ? -1 : 1;
            return (ta - tb) || (b.id - a.id);
          });
        },

        filteredItems() {
          return this.sortItems(this.items.filter((item) => {
            if (this.status === 'active') { if (item.status === 'published') return false; }
            else if (this.status && item.status !== this.status) return false;
            return this.matchesBaseFilters(item);
          }));
        },

        activeCount() {
          return this.items.filter((item) => item.status !== 'published' && this.matchesBaseFilters(item)).length;
        },

        statusCount(statusKey) {
          return this.items.filter((item) => item.status === statusKey && this.matchesBaseFilters(item)).length;
        },

        columnItems(statusKey) {
          return this.sortItems(this.items.filter((item) => item.status === statusKey && this.matchesBaseFilters(item)));
        },

        totalPages() {
          return Math.max(1, Math.ceil(this.filteredItems().length / this.perPage));
        },

        pagedItems() {
          const list = this.filteredItems();
          const max = Math.max(1, Math.ceil(list.length / this.perPage));
          const cur = Math.min(Math.max(1, this.page), max);
          const start = (cur - 1) * this.perPage;
          return list.slice(start, start + this.perPage);
        },

        pageNumbers() {
          return Array.from({ length: this.totalPages() }, (_, i) => i + 1);
        },

        statusMeta(key) {
          return this.statuses.find((s) => s.key === key) || { key, label: key, chip: 'bg-soft text-muted', dot: 'bg-slate-400' };
        },
        statusChip(key) { return this.statusMeta(key).chip; },
        statusDot(key) { return this.statusMeta(key).dot; },
        statusLabel(key) { return this.statusMeta(key).label; },

        itemUrl(id) {
          return `${this.baseUrl}/content/${id}`;
        },
        newDateUrl(ymd) {
          return `${this.baseUrl}/content/new?date=${encodeURIComponent(ymd || this.selectedDate)}`;
        },
        openDetail(item) {
          A.store('contentModal').show(item);
        },

        /* --- Board Drag and Drop --- */
        onDragStart(e, id) {
          this.draggingId = Number(id);
          if (e.dataTransfer) {
            e.dataTransfer.effectAllowed = 'move';
            e.dataTransfer.setData('text/plain', String(id));
          }
        },
        onDragEnd() {
          this.draggingId = null;
          this.dragOverCol = null;
        },
        onDrop(statusKey) {
          const id = this.draggingId;
          this.draggingId = null;
          this.dragOverCol = null;
          if (!id) return;
          const item = this.items.find((i) => Number(i.id) === Number(id));
          if (item && item.status !== statusKey) {
            this.changeStatus(item, statusKey);
          }
        },

        /* --- Status & Delete Mutations --- */
        async changeStatus(item, newStatus) {
          const prev = item.status;
          const meta = this.statusMeta(newStatus);
          item.status = newStatus;
          item.status_label = meta.label;
          item.status_chip = meta.chip;
          item.status_dot = meta.dot;
          try {
            const data = await postAjax(`${this.baseUrl}/content/${item.id}/status`, { status: newStatus }, this.csrf);
            if (data.ok) {
              if (data.item) Object.assign(item, data.item);
              A.store('toast').push(data.msg || 'Status updated');
            } else {
              item.status = prev;
              A.store('toast').push(data.msg || 'Could not update status', 'err');
            }
          } catch {
            item.status = prev;
            A.store('toast').push('Network error while updating status', 'err');
          }
        },

        deleteItem(item) {
          A.store('confirm').ask(`Delete content "${item.title}"?`, async () => {
            try {
              const data = await postAjax(`${this.baseUrl}/content/${item.id}/delete`, {}, this.csrf);
              if (data.ok) {
                this.items = this.items.filter((i) => Number(i.id) !== Number(item.id));
                if (this.page > this.totalPages()) this.page = this.totalPages();
                A.store('toast').push(data.msg || 'Content deleted', 'warn');
              } else {
                A.store('toast').push(data.msg || 'Could not delete content', 'err');
              }
            } catch {
              A.store('toast').push('Network error while deleting content', 'err');
            }
          });
        },

        /* --- Calendar View (Day / Week / Month + Side Column) --- */
        setCalMode(m) {
          this.calMode = m;
          this.syncUrl(true);
        },
        selectDate(ymd) {
          this.selectedDate = ymd;
          this.syncUrl(false);
        },
        calToday() {
          this.selectedDate = this.today;
          this.syncUrl(false);
        },
        calStep(dir) {
          const d = parseYmd(this.selectedDate);
          if (this.calMode === 'day') d.setDate(d.getDate() + dir);
          else if (this.calMode === 'week') d.setDate(d.getDate() + dir * 7);
          else d.setMonth(d.getMonth() + dir);
          this.selectedDate = fmtYmd(d);
          this.syncUrl(false);
        },
        itemsOnDate(ymd) {
          return this.filteredItems()
            .filter((i) => i.scheduled_date === ymd)
            .sort((a, b) => (parseSchedMs(a.scheduled_at) || 0) - (parseSchedMs(b.scheduled_at) || 0) || (a.id - b.id));
        },
        selectedDateItems() {
          return this.itemsOnDate(this.selectedDate);
        },
        formatDateFull(ymd) {
          const d = parseYmd(ymd);
          return `${DAYS_LONG[d.getDay()]}, ${String(d.getDate()).padStart(2, '0')} ${MONTHS_SHORT[d.getMonth()]} ${d.getFullYear()}`;
        },
        calTitle() {
          const d = parseYmd(this.selectedDate);
          if (this.calMode === 'day') {
            return `${DAYS_SHORT[d.getDay()]}, ${String(d.getDate()).padStart(2, '0')} ${MONTHS_SHORT[d.getMonth()]} ${d.getFullYear()}`;
          }
          if (this.calMode === 'week') {
            const days = this.weekDays();
            if (!days.length) return '';
            const first = parseYmd(days[0].date);
            const last = parseYmd(days[6].date);
            return `${String(first.getDate()).padStart(2, '0')} ${MONTHS_SHORT[first.getMonth()]} – ${String(last.getDate()).padStart(2, '0')} ${MONTHS_SHORT[last.getMonth()]} ${last.getFullYear()}`;
          }
          return `${MONTHS_LONG[d.getMonth()]} ${d.getFullYear()}`;
        },
        weekDays() {
          const d = parseYmd(this.selectedDate);
          const dow = (d.getDay() + 6) % 7;
          const mon = new Date(d.getFullYear(), d.getMonth(), d.getDate() - dow);
          const out = [];
          for (let i = 0; i < 7; i++) {
            const cur = new Date(mon.getFullYear(), mon.getMonth(), mon.getDate() + i);
            const ymd = fmtYmd(cur);
            out.push({
              date: ymd,
              dow: DAYS_SHORT[cur.getDay()],
              dayNum: cur.getDate(),
              monthShort: MONTHS_SHORT[cur.getMonth()],
              isToday: ymd === this.today,
              items: this.itemsOnDate(ymd),
            });
          }
          return out;
        },
        monthCells() {
          const d = parseYmd(this.selectedDate);
          const year = d.getFullYear();
          const month = d.getMonth();
          const firstOfMonth = new Date(year, month, 1);
          const startOffset = (firstOfMonth.getDay() + 6) % 7;
          const daysInMonth = new Date(year, month + 1, 0).getDate();
          const totalCells = Math.ceil((startOffset + daysInMonth) / 7) * 7;
          const cells = [];
          for (let i = 0; i < totalCells; i++) {
            const cur = new Date(year, month, 1 - startOffset + i);
            const ymd = fmtYmd(cur);
            cells.push({
              key: ymd,
              date: ymd,
              day: cur.getDate(),
              dayNum: cur.getDate(),
              inMonth: cur.getMonth() === month,
              isToday: ymd === this.today,
              items: this.itemsOnDate(ymd),
            });
          }
          return cells;
        },
      };
    };

    /* ----- Content Plan Manager ----- */
    A.data('contentManager', (cfg) => ({
      ...createContentWorkspace(cfg, { viewStorageKey: 'cp-content-view', hasCampaignFilter: true }),
      init() {
        this.initWorkspace();
      },
    }));

    /* ----- Campaign List Manager ----- */
    A.data('campaignManager', (cfg) => ({
      items: cfg.items || [],
      statuses: cfg.statuses || [],
      status: cfg.status || '',
      q: cfg.q || '',
      csrf: cfg.csrf || '',
      baseUrl: cfg.baseUrl || '',

      syncUrl(push = false) {
        const u = new URL(location.href);
        if (this.status) u.searchParams.set('status', this.status);
        else u.searchParams.delete('status');
        const q = (this.q || '').trim();
        if (q) u.searchParams.set('q', q);
        else u.searchParams.delete('q');
        if (push) history.pushState({}, '', u);
        else history.replaceState({}, '', u);
      },
      setStatus(s) {
        this.status = s;
        this.syncUrl(true);
      },
      onFilterChange() {
        this.syncUrl(false);
      },
      matchesQuery(i) {
        const q = (this.q || '').toLowerCase().trim();
        if (!q) return true;
        return `${i.name || ''} ${i.goal || ''}`.toLowerCase().includes(q);
      },
      countByStatus(k) {
        return this.items.filter((i) => i.status === k && this.matchesQuery(i)).length;
      },
      filtered() {
        return this.items.filter((i) => {
          if (this.status && i.status !== this.status) return false;
          return this.matchesQuery(i);
        });
      },
      statusMeta(k) {
        return this.statuses.find((s) => s.key === k) || { key: k, label: k, chip: 'bg-soft text-muted' };
      },
      statusChip(k) { return this.statusMeta(k).chip; },
      async changeCampaignStatus(c, newStatus) {
        const prev = c.status;
        c.status = newStatus;
        c.status_chip = this.statusChip(newStatus);
        c.status_label = this.statusMeta(newStatus).label;
        try {
          const data = await postAjax(`${this.baseUrl}/campaigns/${c.id}/status`, { status: newStatus }, this.csrf);
          if (data.ok) {
            A.store('toast').push(data.msg || 'Campaign status updated');
          } else {
            c.status = prev;
            A.store('toast').push(data.msg || 'Could not update status', 'err');
          }
        } catch {
          c.status = prev;
          A.store('toast').push('Network error', 'err');
        }
      },
    }));

    /* ----- Campaign Detail Manager (Inline campaign status + List/Board/Calendar + Filters) ----- */
    A.data('campaignDetail', (cfg) => ({
      ...createContentWorkspace(cfg, { hasCampaignFilter: false }),
      campaignId: cfg.campaignId,
      campaignStatus: cfg.campaignStatus || 'active',
      campaignStatuses: cfg.campaignStatuses || [],

      init() {
        this.initWorkspace();
      },
      campaignStatusChip(k) {
        const found = this.campaignStatuses.find((s) => s.key === k);
        return found ? found.chip : 'bg-soft text-muted';
      },
      publishedCount() {
        return this.items.filter((i) => i.status === 'published').length;
      },
      newDateUrl(ymd) {
        return `${this.baseUrl}/content/new?campaign=${encodeURIComponent(this.campaignId)}&date=${encodeURIComponent(ymd || this.selectedDate)}`;
      },
      async updateCampaignStatus(newStatus) {
        this.campaignStatus = newStatus;
        try {
          const data = await postAjax(`${this.baseUrl}/campaigns/${this.campaignId}/status`, { status: newStatus }, this.csrf);
          if (data.ok) A.store('toast').push(data.msg || 'Campaign status updated');
          else A.store('toast').push(data.msg || 'Could not update status', 'err');
        } catch {
          A.store('toast').push('Network error', 'err');
        }
      },
    }));

    /* ----- Idea Bank Manager (Reactive status & pillar filters + inline updates) ----- */
    A.data('ideaManager', (cfg) => ({
      items: cfg.items || [],
      statuses: cfg.statuses || [],
      pillars: cfg.pillars || [],
      priorities: cfg.priorities || [],
      status: cfg.status || '',
      pillar: cfg.pillar || '',
      q: cfg.q || '',
      page: Number(cfg.page || 1),
      perPage: Number(cfg.perPage || 6),
      newTitle: '',
      newPillar: (cfg.pillars && cfg.pillars[0]) || 'Education',
      newPriority: 'medium',
      saving: false,
      editOpen: false,
      edit: null,
      openEdit(item) { this.edit = { ...item, notes: item.notes || '', pillar: item.pillar || '' }; this.editOpen = true; },
      csrf: cfg.csrf || '',
      baseUrl: cfg.baseUrl || '',

      syncUrl(push = true) {
        const u = new URL(location.href);
        if (this.status) u.searchParams.set('status', this.status);
        else u.searchParams.delete('status');
        if (this.pillar) u.searchParams.set('pillar', this.pillar);
        else u.searchParams.delete('pillar');
        const q = (this.q || '').trim();
        if (q) u.searchParams.set('q', q);
        else u.searchParams.delete('q');
        if (this.page > 1) u.searchParams.set('page', String(this.page));
        else u.searchParams.delete('page');
        if (push) history.pushState({}, '', u);
        else history.replaceState({}, '', u);
      },
      setStatus(s) {
        this.status = s;
        this.page = 1;
        this.syncUrl(true);
      },
      togglePillar(p) {
        this.pillar = this.pillar === p ? '' : p;
        this.page = 1;
        this.syncUrl(true);
      },
      onFilterChange() {
        this.page = 1;
        this.syncUrl(false);
      },
      setPage(p) {
        const max = this.totalPages();
        this.page = Math.min(Math.max(1, p), max);
        this.syncUrl(true);
      },
      countByStatus(k) {
        return this.items.filter((i) => i.status === k).length;
      },
      statusChip(k) {
        const found = this.statuses.find((s) => s.key === k);
        return found ? found.chip : 'bg-soft text-muted';
      },
      filtered() {
        const q = (this.q || '').toLowerCase().trim();
        return this.items.filter((i) => {
          if (this.status && i.status !== this.status) return false;
          if (this.pillar && i.pillar !== this.pillar) return false;
          if (q) {
            const hay = `${i.title || ''} ${i.notes || ''} ${i.pillar || ''}`.toLowerCase();
            if (!hay.includes(q)) return false;
          }
          return true;
        });
      },
      totalPages() {
        return Math.max(1, Math.ceil(this.filtered().length / this.perPage));
      },
      pagedItems() {
        const list = this.filtered();
        const max = Math.max(1, Math.ceil(list.length / this.perPage));
        const cur = Math.min(Math.max(1, this.page), max);
        const start = (cur - 1) * this.perPage;
        return list.slice(start, start + this.perPage);
      },
      pageNumbers() {
        return Array.from({ length: this.totalPages() }, (_, i) => i + 1);
      },
      async quickAdd() {
        if (!this.newTitle.trim() || this.saving) return;
        this.saving = true;
        try {
          const data = await postAjax(`${this.baseUrl}/ideas`, {
            title: this.newTitle.trim(),
            pillar: this.newPillar,
            priority: this.newPriority,
            status: 'new',
          }, this.csrf);
          if (data.ok && data.item) {
            this.items.unshift(data.item);
            this.newTitle = '';
            A.store('toast').push(data.msg || 'Idea added to bank!');
          } else if (data.ok && data.queued) {
            // Saved offline: show a temporary card until the change is synced and the page is reloaded
            const pr = (this.priorities || []).find((p) => (p.key || p) === this.newPriority);
            this.items.unshift({
              id: `tmp-${Date.now()}`, title: this.newTitle.trim(), notes: '', pillar: this.newPillar, priority: this.newPriority,
              priority_lbl: (pr && (pr.label || pr)) || this.newPriority, priority_cls: '', status: 'new', _pending: true,
            });
            this.newTitle = '';
            A.store('toast').push(data.msg);
          } else {
            A.store('toast').push(data.msg || 'Could not add idea', 'err');
          }
        } catch {
          A.store('toast').push('Network error', 'err');
        } finally {
          this.saving = false;
        }
      },
      async updateIdea(item, patch) {
        Object.assign(item, patch);
        try {
          const data = await postAjax(`${this.baseUrl}/ideas/${item.id}/status`, {
            status: item.status,
            pillar: item.pillar,
          }, this.csrf);
          if (data.ok) {
            if (data.item) Object.assign(item, data.item);
            A.store('toast').push(data.msg || 'Idea updated');
          } else {
            A.store('toast').push(data.msg || 'Could not update idea', 'err');
          }
        } catch {
          A.store('toast').push('Network error', 'err');
        }
      },
      deleteIdea(item) {
        A.store('confirm').ask(`Delete idea "${item.title}"?`, async () => {
          try {
            const data = await postAjax(`${this.baseUrl}/ideas/${item.id}/delete`, {}, this.csrf);
            if (data.ok) {
              this.items = this.items.filter((i) => Number(i.id) !== Number(item.id));
              if (this.page > this.totalPages()) this.page = this.totalPages();
              A.store('toast').push(data.msg || 'Idea deleted', 'warn');
            }
          } catch {
            A.store('toast').push('Network error', 'err');
          }
        });
      },
    }));

    /* ----- Category / Labels CRUD Manager (Platform, Format, Pillar, Status) ----- */
    A.data('categoryManager', (cfg) => ({
      tab: cfg.tab || 'platform',
      platforms: cfg.platforms || [],
      formats: cfg.formats || [],
      pillars: cfg.pillars || [],
      statuses: cfg.statuses || [],
      colors: cfg.colors || {},
      icons: cfg.icons || [],
      csrf: cfg.csrf || '',
      endpoint: cfg.endpoint || ((cfg.baseUrl || '') + '/categories'),
      busy: false,
      saving: false,
      modal: {
        open: false,
        mode: 'create',
        type: 'platform',
        orig: '',
        key: '',
        name: '',
        label: '',
        abbr: '',
        color: 'blue',
        icon: 'bulb',
        progress: 0,
      },

      tabLabel() {
        return { platform: 'Platform', format: 'Format', pillar: 'Pillar', status: 'Status' }[this.tab] || 'Label';
      },
      setTab(t) {
        this.tab = t;
        const u = new URL(location.href);
        u.searchParams.set('tab', t);
        history.pushState({}, '', u);
      },
      openCreate(type) {
        const t = type || this.tab;
        this.modal = {
          open: true,
          mode: 'create',
          type: t,
          orig: '',
          key: '',
          name: '',
          label: '',
          abbr: '',
          color: 'blue',
          icon: 'bulb',
          progress: 0,
        };
        this.$nextTick(() => this.$refs.nameInput && this.$refs.nameInput.focus());
      },
      openEditPlatform(p) {
        this.modal = {
          open: true,
          mode: 'edit',
          type: 'platform',
          orig: p.name,
          key: p.name,
          name: p.name,
          label: p.name,
          abbr: p.abbr || '',
          color: p.color || 'blue',
          icon: 'bulb',
          progress: 0,
        };
        this.$nextTick(() => this.$refs.nameInput && this.$refs.nameInput.focus());
      },
      openEditSimple(type, name) {
        this.modal = {
          open: true,
          mode: 'edit',
          type,
          orig: name,
          key: name,
          name,
          label: name,
          abbr: '',
          color: 'blue',
          icon: 'bulb',
          progress: 0,
        };
        this.$nextTick(() => this.$refs.nameInput && this.$refs.nameInput.focus());
      },
      openEditStatus(s) {
        this.modal = {
          open: true,
          mode: 'edit',
          type: 'status',
          orig: s.key,
          key: s.key,
          name: s.label,
          label: s.label,
          abbr: '',
          color: s.color || 'slate',
          icon: s.icon || 'bulb',
          progress: Number(s.progress || 0),
        };
        this.$nextTick(() => this.$refs.nameInput && this.$refs.nameInput.focus());
      },
      async saveModal() {
        const nameVal = (this.modal.name || this.modal.label || '').trim();
        if (!nameVal || this.busy) return;
        this.busy = true;
        this.saving = true;
        try {
          const data = await postAjax(this.endpoint, {
            type: this.modal.type,
            op: 'save',
            action: 'save',
            orig: this.modal.orig,
            key: this.modal.key,
            name: nameVal,
            label: nameVal,
            abbr: (this.modal.abbr || '').trim(),
            color: this.modal.color,
            icon: this.modal.icon,
            progress: this.modal.progress,
          }, this.csrf);
          if (data.ok) {
            const st = data.state || data;
            this.platforms = st.platforms || this.platforms;
            this.formats = st.formats || this.formats;
            this.pillars = st.pillars || this.pillars;
            this.statuses = st.statuses || this.statuses;
            this.modal.open = false;
            A.store('toast').push(data.msg || 'Saved');
          } else {
            A.store('toast').push(data.error || data.msg || 'Could not save', 'err');
          }
        } catch {
          A.store('toast').push('Network error', 'err');
        } finally {
          this.busy = false;
          this.saving = false;
        }
      },
      removeItem(type, orig) {
        A.store('confirm').ask(`Delete ${type} "${orig}"?`, async () => {
          try {
            const data = await postAjax(this.endpoint, {
              type,
              op: 'delete',
              action: 'delete',
              orig,
            }, this.csrf);
            if (data.ok) {
              const st = data.state || data;
              this.platforms = st.platforms || this.platforms;
              this.formats = st.formats || this.formats;
              this.pillars = st.pillars || this.pillars;
              this.statuses = st.statuses || this.statuses;
              A.store('toast').push(data.msg || 'Deleted', 'warn');
            } else {
              A.store('toast').push(data.error || data.msg || 'Could not delete', 'err');
            }
          } catch {
            A.store('toast').push('Network error', 'err');
          }
        });
      },
    }));
  });

  /* ---------- Service worker registration ---------- */
  if ('serviceWorker' in navigator && (location.protocol === 'https:' || ['localhost', '127.0.0.1'].includes(location.hostname))) {
    addEventListener('load', () => {
      const b = document.body.dataset.base || base;
      const v = document.body.dataset.version || '1.5.0';
      navigator.serviceWorker.register(`${b}/sw.js?v=${encodeURIComponent(v)}`, { scope: b + '/' }).catch(() => {});
    });
  }
})();
