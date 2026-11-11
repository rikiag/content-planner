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

  /* ---------- Helper for AJAX POST requests ---------- */
  const postAjax = async (url, data, csrf) => {
    const fd = new FormData();
    fd.append('_csrf', csrf || '');
    fd.append('_ajax', '1');
    Object.entries(data || {}).forEach(([k, v]) => fd.append(k, v ?? ''));
    const res = await fetch(url, {
      method: 'POST',
      headers: { 'X-Requested-With': 'XMLHttpRequest', 'X-CSRF-Token': csrf || '' },
      body: fd,
    });
    return res.json();
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

    /* ----- Content Plan Manager (List pagination, Board drag & drop, Calendar Day/Week/Month + side column) ----- */
    A.data('contentManager', (cfg) => ({
      items: cfg.items || [],
      statuses: cfg.statuses || [],
      platforms: cfg.platforms || [],
      campaigns: cfg.campaigns || [],
      view: cfg.view || 'list',
      status: cfg.status || '',
      platform: cfg.platform || '',
      campaign: String(cfg.campaign || '0'),
      q: cfg.q || '',
      page: Number(cfg.page || 1),
      perPage: Number(cfg.perPage || 10),
      calMode: cfg.calMode || 'month',
      selectedDate: cfg.selectedDate || cfg.today || fmtYmd(new Date()),
      today: cfg.today || fmtYmd(new Date()),
      csrf: cfg.csrf || '',
      baseUrl: cfg.baseUrl || '',
      draggingId: null,
      dragOverCol: null,

      init() {
        A.store('contentModal').onUpdate((updated) => {
          const idx = this.items.findIndex((i) => Number(i.id) === Number(updated.id));
          if (idx !== -1) this.items[idx] = { ...this.items[idx], ...updated };
        });
        addEventListener('popstate', () => {
          const u = new URL(location.href);
          this.view = u.searchParams.get('view') || 'list';
          this.status = u.searchParams.get('status') || '';
          this.platform = u.searchParams.get('platform') || '';
          this.campaign = u.searchParams.get('campaign') || '0';
          this.q = u.searchParams.get('q') || '';
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
        setOrDel('view', this.view, 'list');
        setOrDel('status', this.status, '');
        setOrDel('platform', this.platform, '');
        setOrDel('campaign', this.campaign, '0');
        setOrDel('q', this.q.trim(), '');
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
        this.syncUrl(true);
      },
      setStatus(s) {
        this.status = s;
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

      matchesBaseFilters(item) {
        if (this.platform && item.platform !== this.platform) return false;
        if (this.campaign && this.campaign !== '0' && String(item.campaign_id) !== String(this.campaign)) return false;
        const q = (this.q || '').toLowerCase().trim();
        if (q) {
          const hay = `${item.title || ''} ${item.caption || ''} ${item.hashtags || ''} ${item.pillar || ''} ${item.format || ''} ${item.platform || ''} ${item.campaign_name || ''}`.toLowerCase();
          if (!hay.includes(q)) return false;
        }
        return true;
      },

      filteredItems() {
        return this.items.filter((item) => {
          if (this.status && item.status !== this.status) return false;
          return this.matchesBaseFilters(item);
        });
      },

      activeCount() {
        return this.items.filter((item) => this.matchesBaseFilters(item)).length;
      },

      statusCount(statusKey) {
        return this.items.filter((item) => item.status === statusKey && this.matchesBaseFilters(item)).length;
      },

      columnItems(statusKey) {
        return this.items.filter((item) => item.status === statusKey && this.matchesBaseFilters(item));
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
        if (this.calMode === 'day') {
          d.setDate(d.getDate() + dir);
        } else if (this.calMode === 'week') {
          d.setDate(d.getDate() + dir * 7);
        } else {
          d.setMonth(d.getMonth() + dir);
        }
        this.selectedDate = fmtYmd(d);
        this.syncUrl(false);
      },
      itemsOnDate(ymd) {
        return this.filteredItems().filter((i) => i.scheduled_date === ymd);
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
        const dow = (d.getDay() + 6) % 7; // Monday = 0
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
        const startOffset = (firstOfMonth.getDay() + 6) % 7; // Monday = 0
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
    }));

    /* ----- Campaign List Manager ----- */
    A.data('campaignManager', (cfg) => ({
      items: cfg.items || [],
      statuses: cfg.statuses || [],
      status: cfg.status || '',
      csrf: cfg.csrf || '',
      baseUrl: cfg.baseUrl || '',

      setStatus(s) {
        this.status = s;
        const u = new URL(location.href);
        if (s) u.searchParams.set('status', s);
        else u.searchParams.delete('status');
        history.pushState({}, '', u);
      },
      countByStatus(k) {
        return this.items.filter((i) => i.status === k).length;
      },
      filtered() {
        return this.status ? this.items.filter((i) => i.status === this.status) : this.items;
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

    /* ----- Campaign Detail Manager (Inline campaign status + paginated content) ----- */
    A.data('campaignDetail', (cfg) => ({
      campaignId: cfg.campaignId,
      campaignStatus: cfg.campaignStatus || 'active',
      campaignStatuses: cfg.campaignStatuses || [],
      statuses: cfg.statuses || [],
      items: cfg.items || [],
      page: Number(cfg.page || 1),
      perPage: Number(cfg.perPage || 8),
      csrf: cfg.csrf || '',
      baseUrl: cfg.baseUrl || '',

      init() {
        A.store('contentModal').onUpdate((updated) => {
          const idx = this.items.findIndex((i) => Number(i.id) === Number(updated.id));
          if (idx !== -1) this.items[idx] = { ...this.items[idx], ...updated };
        });
      },
      campaignStatusChip(k) {
        const found = this.campaignStatuses.find((s) => s.key === k);
        return found ? found.chip : 'bg-soft text-muted';
      },
      statusMeta(k) {
        return this.statuses.find((s) => s.key === k) || { key: k, label: k, chip: 'bg-soft text-muted', dot: 'bg-slate-400' };
      },
      statusChip(k) { return this.statusMeta(k).chip; },
      publishedCount() {
        return this.items.filter((i) => i.status === 'published').length;
      },
      totalPages() {
        return Math.max(1, Math.ceil(this.items.length / this.perPage));
      },
      pagedItems() {
        const max = this.totalPages();
        const cur = Math.min(Math.max(1, this.page), max);
        const start = (cur - 1) * this.perPage;
        return this.items.slice(start, start + this.perPage);
      },
      pageNumbers() {
        return Array.from({ length: this.totalPages() }, (_, i) => i + 1);
      },
      setPage(p) {
        this.page = Math.min(Math.max(1, p), this.totalPages());
        const u = new URL(location.href);
        if (this.page > 1) u.searchParams.set('page', String(this.page));
        else u.searchParams.delete('page');
        history.pushState({}, '', u);
      },
      openDetail(item) {
        A.store('contentModal').show(item);
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
      async changeContentStatus(item, newStatus) {
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
          A.store('toast').push('Network error', 'err');
        }
      },
      deleteContentItem(item) {
        A.store('confirm').ask(`Delete content "${item.title}"?`, async () => {
          try {
            const data = await postAjax(`${this.baseUrl}/content/${item.id}/delete`, {}, this.csrf);
            if (data.ok) {
              this.items = this.items.filter((i) => Number(i.id) !== Number(item.id));
              if (this.page > this.totalPages()) this.page = this.totalPages();
              A.store('toast').push(data.msg || 'Content deleted', 'warn');
            }
          } catch {
            A.store('toast').push('Network error', 'err');
          }
        });
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
      q: '',
      newTitle: '',
      newPillar: (cfg.pillars && cfg.pillars[0]) || 'Education',
      newPriority: 'medium',
      saving: false,
      csrf: cfg.csrf || '',
      baseUrl: cfg.baseUrl || '',

      syncUrl() {
        const u = new URL(location.href);
        if (this.status) u.searchParams.set('status', this.status);
        else u.searchParams.delete('status');
        if (this.pillar) u.searchParams.set('pillar', this.pillar);
        else u.searchParams.delete('pillar');
        history.pushState({}, '', u);
      },
      setStatus(s) {
        this.status = s;
        this.syncUrl();
      },
      togglePillar(p) {
        this.pillar = this.pillar === p ? '' : p;
        this.syncUrl();
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
      const v = document.body.dataset.version || '1.1.0';
      navigator.serviceWorker.register(`${b}/sw.js?v=${encodeURIComponent(v)}`, { scope: b + '/' }).catch(() => {});
    });
  }
})();
