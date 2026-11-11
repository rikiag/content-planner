<?php
declare(strict_types=1);

defined('ROOT') || (http_response_code(403) && exit);

function campaign_options(bool $blank = true): array
{
    $options = $blank ? ['' => '— No campaign —'] : [];
    foreach (q('SELECT id, name FROM campaigns ORDER BY status="active" DESC, name') as $c) {
        $options[$c['id']] = $c['name'];
    }
    return $options;
}

function status_select(array $c, string $back): string
{
    $allStatuses = statuses();
    $m = $allStatuses[$c['status']] ?? reset($allStatuses);
    $html = '<form method="post" action="' . e(url('/content/' . $c['id'] . '/status')) . '" class="inline-block" @click.stop>' . csrf_field()
        . '<input type="hidden" name="back" value="' . e($back) . '">'
        . '<select name="status" @change="$el.form.requestSubmit()" aria-label="Change status" class="cursor-pointer appearance-none rounded-full border-0 py-1.5 pl-3 pr-3 text-[11px] font-bold focus:outline-2 focus:outline-brand ' . $m['chip'] . '">';
    foreach ($allStatuses as $k => $s) {
        $html .= '<option value="' . e($k) . '"' . ($k === $c['status'] ? ' selected' : '') . ' class="bg-surface text-ink">' . e($s['label']) . '</option>';
    }
    return $html . '</select></form>';
}

function content_row(array $c, string $back, bool $live = false): string
{
    $allStatuses = statuses();
    $allPlatforms = platforms();
    $hay = mb_strtolower(implode(' ', [$c['title'], $c['caption'] ?? '', $c['hashtags'] ?? '', $c['platform'], $c['format'], $c['pillar'], $c['campaign_name'] ?? '', $allStatuses[$c['status']]['label'] ?? '']));
    $late = is_overdue($c);
    $prog = clamp((int) $c['progress'], 0, 100);
    $payload = content_payload($c);
    ob_start(); ?>
    <article class="card flex cursor-pointer flex-col gap-3 p-4 transition hover:shadow-md sm:flex-row sm:items-center sm:gap-4"
        @click="$store.contentModal.show(<?= e(json_encode($payload, JSON_UNESCAPED_UNICODE)) ?>)"
        <?= $live ? ' data-search="' . e($hay) . '" x-show="match($el)" x-transition.opacity' : '' ?>>
        <div class="flex min-w-0 flex-1 items-start gap-3">
            <span class="grid size-11 shrink-0 place-items-center rounded-2xl text-xs font-extrabold <?= $allPlatforms[$c['platform']]['cls'] ?? 'bg-soft' ?>"><?= e(platform_abbr($c['platform'])) ?></span>
            <div class="min-w-0 flex-1">
                <a href="<?= e(url('/content/' . $c['id'])) ?>" @click.prevent class="line-clamp-2 text-[15px] font-extrabold leading-snug hover:text-brand"><?= e($c['title']) ?></a>
                <p class="mt-0.5 truncate text-xs text-muted"><?= e($c['format']) ?><?= $c['pillar'] ? ' · ' . e($c['pillar']) : '' ?><?= !empty($c['campaign_name']) ? ' · ' . e($c['campaign_name']) : '' ?></p>
                <p class="mt-1 flex flex-wrap items-center gap-x-2 text-xs <?= $late ? 'font-bold text-rose-600 dark:text-rose-400' : 'text-muted' ?>">
                    <?= icon('clock', 'size-3.5') ?><?= e(fmt_dt($c['scheduled_at'])) ?><?= $late ? ' · Overdue' : '' ?>
                </p>
                <?php if ($c['status'] === 'published'): ?>
                    <p class="mt-1.5 flex flex-wrap gap-x-3 text-[11px] font-semibold text-muted">
                        <span class="inline-flex items-center gap-1"><?= icon('eye', 'size-3.5') ?><?= e(fmt_k($c['views'])) ?></span>
                        <span class="inline-flex items-center gap-1"><?= icon('heart', 'size-3.5') ?><?= e(fmt_k($c['likes'])) ?></span>
                        <span class="inline-flex items-center gap-1"><?= icon('comment', 'size-3.5') ?><?= e(fmt_k($c['comments'])) ?></span>
                        <span class="text-emerald-600 dark:text-emerald-400">ER <?= e(fmt_pct(er($c))) ?></span>
                    </p>
                <?php else: ?>
                    <div class="mt-2 flex items-center gap-2"><div class="bar-track w-28"><div class="h-full rounded-full bg-accent" style="width:<?= $prog ?>%"></div></div><span class="text-[11px] font-bold text-muted"><?= $prog ?>%</span></div>
                <?php endif; ?>
            </div>
        </div>
        <div class="flex items-center justify-between gap-2 sm:justify-end" @click.stop>
            <?= status_select($c, $back) ?>
            <div class="flex items-center gap-1">
                <a href="<?= e(url('/content/' . $c['id'])) ?>" class="grid size-9 place-items-center rounded-xl text-muted hover:bg-soft hover:text-ink" aria-label="Edit" title="Edit"><?= icon('pencil', 'size-4') ?></a>
                <form method="post" action="<?= e(url('/content/' . $c['id'] . '/duplicate')) ?>" class="inline"><?= csrf_field() ?><button class="grid size-9 place-items-center rounded-xl text-muted hover:bg-soft hover:text-ink" aria-label="Duplicate" title="Duplicate"><?= icon('copy', 'size-4') ?></button></form>
                <?= delete_form('/content/' . $c['id'] . '/delete', 'Delete content “' . $c['title'] . '”?', 'Delete', 'grid size-9 place-items-center rounded-xl text-muted hover:bg-rose-50 hover:text-rose-600 dark:hover:bg-rose-500/10', true, $back) ?>
            </div>
        </div>
    </article>
    <?php return (string) ob_get_clean();
}

function page_contents(): void
{
    $rawView = input('view') ?: input('v');
    $view = in_array($rawView, ['board', 'calendar'], true) ? $rawView : 'list';
    $status = input('status');
    $platform = input('platform');
    $camp = input('campaign');
    $search = input('q');
    $pageNum = max(1, (int) input('page', '1'));
    $rawCal = input('cal_mode') ?: input('cal');
    $calMode = in_array($rawCal, ['day', 'week', 'month'], true) ? $rawCal : 'month';
    $selectedDate = preg_match('/^\d{4}-\d{2}-\d{2}$/', input('date')) ? input('date') : date('Y-m-d');
    $rawYm = input('ym') ?: input('m');
    if (preg_match('/^\d{4}-\d{2}$/', $rawYm) && !preg_match('/^\d{4}-\d{2}-\d{2}$/', input('date')) && $rawYm !== date('Y-m')) {
        $selectedDate = $rawYm . '-01';
    }

    $rows = q('SELECT c.*, ca.name AS campaign_name FROM contents c LEFT JOIN campaigns ca ON ca.id=c.campaign_id ORDER BY (c.scheduled_at IS NULL), c.scheduled_at DESC, c.id DESC');
    $items = array_map('content_payload', $rows);

    $allStatuses = [];
    foreach (statuses() as $k => $m) {
        $allStatuses[] = [
            'key'   => (string) $k,
            'label' => (string) $m['label'],
            'chip'  => (string) $m['chip'],
            'dot'   => (string) $m['dot'],
            'tint'  => (string) $m['tint'],
            'icon'  => (string) ($m['icon'] ?? 'bulb'),
        ];
    }
    $allPlatforms = array_keys(platforms());
    $allCampaigns = [];
    foreach (campaign_options(false) as $cid => $cname) {
        $allCampaigns[] = ['id' => (int) $cid, 'name' => (string) $cname];
    }

    $config = [
        'items'        => $items,
        'statuses'     => $allStatuses,
        'platforms'    => $allPlatforms,
        'campaigns'    => $allCampaigns,
        'view'         => $view,
        'status'       => $status,
        'platform'     => $platform,
        'campaign'     => $camp !== '' ? (string) (int) $camp : '',
        'q'            => $search,
        'page'         => $pageNum,
        'perPage'      => 8,
        'calMode'      => $calMode,
        'selectedDate' => $selectedDate,
        'today'        => date('Y-m-d'),
        'csrf'         => csrf_token(),
        'baseUrl'      => url(''),
    ];

    ob_start(); ?>
    <div x-data="contentManager(<?= e(json_encode($config, JSON_UNESCAPED_UNICODE)) ?>)">
        <!-- Toolbar -->
        <div class="mb-4 flex flex-col gap-3">
            <div class="flex flex-wrap items-center gap-2">
                <div class="grid flex-1 grid-cols-3 rounded-full bg-surface p-1 shadow-sm sm:flex-none" role="tablist" aria-label="View mode">
                    <button type="button" role="tab" :aria-selected="view === 'list'" @click="setView('list')"
                        class="inline-flex items-center justify-center gap-1.5 rounded-full px-3 py-2 text-xs font-bold transition sm:px-4"
                        :class="view === 'list' ? 'bg-hero text-white' : 'text-muted hover:text-ink'"><?= icon('list', 'size-4') ?>List</button>
                    <button type="button" role="tab" :aria-selected="view === 'board'" @click="setView('board')"
                        class="inline-flex items-center justify-center gap-1.5 rounded-full px-3 py-2 text-xs font-bold transition sm:px-4"
                        :class="view === 'board' ? 'bg-hero text-white' : 'text-muted hover:text-ink'"><?= icon('board', 'size-4') ?>Board</button>
                    <button type="button" role="tab" :aria-selected="view === 'calendar'" @click="setView('calendar')"
                        class="inline-flex items-center justify-center gap-1.5 rounded-full px-3 py-2 text-xs font-bold transition sm:px-4"
                        :class="view === 'calendar' ? 'bg-hero text-white' : 'text-muted hover:text-ink'"><?= icon('calendar', 'size-4') ?>Calendar</button>
                </div>
                <div class="ml-auto flex items-center gap-2">
                    <a href="<?= e(url('/categories')) ?>" class="btn-soft hidden !px-3.5 !py-2 !text-xs sm:inline-flex" title="Manage Platform, Format, Pillar & Status"><?= icon('tag', 'size-4') ?>Labels</a>
                    <a href="<?= e(url('/content/new')) ?>" class="btn-primary hidden sm:inline-flex"><?= icon('plus', 'size-4') ?>New content</a>
                </div>
            </div>

            <!-- Search & Dropdown Filters -->
            <div class="grid grid-cols-2 gap-2 sm:flex sm:flex-wrap sm:items-center">
                <label class="relative col-span-2 block sm:min-w-0 sm:flex-1 sm:basis-52">
                    <span class="sr-only">Search content</span>
                    <span class="pointer-events-none absolute left-3.5 top-1/2 -translate-y-1/2 text-muted"><?= icon('search', 'size-4') ?></span>
                    <input type="search" x-model="q" @input.debounce.200ms="onFilterChange()" placeholder="Search title / caption / hashtags…" class="input !pl-10" enterkeyhint="search">
                </label>
                <select x-model="platform" @change="onFilterChange()" class="input sm:!w-auto" aria-label="Platform">
                    <option value="">All platforms</option>
                    <template x-for="p in platforms" :key="p">
                        <option :value="p" x-text="p"></option>
                    </template>
                </select>
                <select x-model="campaign" @change="onFilterChange()" class="input sm:!w-auto sm:max-w-52" aria-label="Campaign">
                    <option value="">All campaigns</option>
                    <template x-for="ca in campaigns" :key="ca.id">
                        <option :value="String(ca.id)" x-text="ca.name"></option>
                    </template>
                </select>
            </div>

            <!-- Status Filter Pills -->
            <div x-show="view !== 'board'" <?= $view === 'board' ? 'x-cloak' : '' ?> class="-mx-4 flex gap-2 overflow-x-auto px-4 pb-1 no-scrollbar sm:mx-0 sm:flex-wrap sm:px-0">
                <button type="button" class="pill" :aria-current="status === '' ? 'true' : 'false'" @click="setStatus('')">
                    All <b x-text="items.length"></b>
                </button>
                <button type="button" class="pill" :aria-current="status === 'active' ? 'true' : 'false'" @click="setStatus('active')">
                    In progress <b x-text="activeCount()"></b>
                </button>
                <template x-for="s in statuses" :key="s.key">
                    <button type="button" class="pill" :aria-current="status === s.key ? 'true' : 'false'" @click="setStatus(s.key)">
                        <span class="size-2 rounded-full" :class="s.dot"></span>
                        <span x-text="s.label"></span>
                        <b x-text="statusCount(s.key)"></b>
                    </button>
                </template>
            </div>
        </div>

        <!-- ==================== 1. LIST VIEW ==================== -->
        <div x-show="view === 'list'" <?= $view !== 'list' ? 'x-cloak' : '' ?> x-transition.opacity>
            <template x-if="filteredItems().length === 0">
                <div class="card grid place-items-center gap-2 px-6 py-12 text-center">
                    <div class="grid size-14 place-items-center rounded-2xl bg-soft text-brand"><?= icon('calendar', 'size-7') ?></div>
                    <h3 class="text-base font-extrabold">No content found</h3>
                    <p class="max-w-sm text-sm text-muted" x-text="(q || status || platform || campaign) ? 'No content matches the active filters.' : 'Start building your content calendar from a simple idea.'"></p>
                    <a href="<?= e(url('/content/new')) ?>" class="btn-primary mt-2"><?= icon('plus', 'size-4') ?>New content</a>
                </div>
            </template>

            <template x-if="filteredItems().length > 0">
                <div>
                    <div class="flex flex-col gap-3">
                        <template x-for="c in pagedItems()" :key="c.id">
                            <article @click="openDetail(c)" class="card flex cursor-pointer gap-3 p-4 transition hover:shadow-md sm:flex-row sm:items-center sm:gap-4">
                                <div class="flex min-w-0 flex-1 items-start gap-3">
                                    <span class="grid size-11 shrink-0 place-items-center rounded-2xl text-xs font-extrabold" :class="c.platform_cls" x-text="c.platform_abbr"></span>
                                    <div class="min-w-0 flex-1">
                                        <h3 class="line-clamp-2 text-[15px] font-extrabold leading-snug hover:text-brand" x-text="c.title"></h3>
                                        <p class="mt-0.5 truncate text-xs text-muted">
                                            <span x-text="c.format"></span>
                                            <template x-if="c.pillar"><span> · <span x-text="c.pillar"></span></span></template>
                                            <template x-if="c.campaign_name"><span> · <span x-text="c.campaign_name"></span></span></template>
                                        </p>
                                        <p class="mt-1 flex flex-wrap items-center gap-x-2 text-xs" :class="c.is_overdue && c.status !== 'published' ? 'font-bold text-rose-600 dark:text-rose-400' : 'text-muted'">
                                            <?= icon('clock', 'size-3.5') ?>
                                            <span x-text="c.scheduled_fmt"></span>
                                            <template x-if="c.is_overdue && c.status !== 'published'"><span> · Overdue</span></template>
                                        </p>
                                        <template x-if="c.status === 'published'">
                                            <p class="mt-1.5 flex flex-wrap gap-x-3 text-[11px] font-semibold text-muted">
                                                <span class="inline-flex items-center gap-1"><?= icon('eye', 'size-3.5') ?><span x-text="c.views_fmt"></span></span>
                                                <span class="inline-flex items-center gap-1"><?= icon('heart', 'size-3.5') ?><span x-text="c.likes_fmt"></span></span>
                                                <span class="inline-flex items-center gap-1"><?= icon('comment', 'size-3.5') ?><span x-text="c.comments_fmt"></span></span>
                                                <span class="text-emerald-600 dark:text-emerald-400">ER <span x-text="c.er_fmt"></span></span>
                                            </p>
                                        </template>
                                        <template x-if="c.status !== 'published'">
                                            <div class="mt-2 flex items-center gap-2">
                                                <div class="bar-track w-28"><div class="h-full rounded-full bg-accent" :style="'width:' + c.progress + '%'"></div></div>
                                                <span class="text-[11px] font-bold text-muted" x-text="c.progress + '%'"></span>
                                            </div>
                                        </template>
                                    </div>
                                </div>
                                <div class="flex items-center justify-between gap-2 sm:justify-end" @click.stop>
                                    <select :value="c.status" @change="changeStatus(c, $event.target.value)" aria-label="Change status"
                                        class="cursor-pointer appearance-none rounded-full border-0 py-1.5 pl-3 pr-3 text-[11px] font-bold focus:outline-2 focus:outline-brand"
                                        :class="statusChip(c.status)">
                                        <template x-for="st in statuses" :key="st.key">
                                            <option :value="st.key" :selected="st.key === c.status" class="bg-surface text-ink" x-text="st.label"></option>
                                        </template>
                                    </select>
                                    <div class="flex items-center gap-1">
                                        <a :href="itemUrl(c.id)" class="grid size-9 place-items-center rounded-xl text-muted hover:bg-soft hover:text-ink" aria-label="Edit" title="Edit"><?= icon('pencil', 'size-4') ?></a>
                                        <form method="post" :action="itemUrl(c.id) + '/duplicate'" class="inline"><?= csrf_field() ?><button class="grid size-9 place-items-center rounded-xl text-muted hover:bg-soft hover:text-ink" aria-label="Duplicate" title="Duplicate"><?= icon('copy', 'size-4') ?></button></form>
                                        <button type="button" @click="deleteItem(c)" class="grid size-9 place-items-center rounded-xl text-muted hover:bg-rose-50 hover:text-rose-600 dark:hover:bg-rose-500/10" aria-label="Delete" title="Delete"><?= icon('trash', 'size-4') ?></button>
                                    </div>
                                </div>
                            </article>
                        </template>
                    </div>

                    <!-- Pagination Bar -->
                    <div x-show="totalPages() > 1" class="mt-4 flex flex-col items-center justify-center gap-2 rounded-3xl bg-surface px-4 py-3 text-center shadow-sm sm:flex-row sm:justify-between sm:text-left">
                        <p class="text-xs text-muted">
                            Showing <b class="text-ink" x-text="((page - 1) * perPage) + 1"></b>–<b class="text-ink" x-text="Math.min(page * perPage, filteredItems().length)"></b>
                            of <b class="text-ink" x-text="filteredItems().length"></b> items
                        </p>
                        <div class="flex items-center gap-1">
                            <button type="button" @click="setPage(page - 1)" :disabled="page <= 1"
                                class="grid size-9 place-items-center rounded-xl border border-line bg-surface text-xs font-bold text-ink transition hover:bg-soft disabled:opacity-40" aria-label="Previous page">
                                <?= icon('chevron-left', 'size-4') ?>
                            </button>
                            <template x-for="p in pageNumbers()" :key="p">
                                <button type="button" @click="setPage(p)"
                                    class="grid size-9 place-items-center rounded-xl text-xs font-extrabold transition"
                                    :class="p === page ? 'bg-hero text-white shadow-sm' : 'text-muted hover:bg-soft hover:text-ink'"
                                    x-text="p"></button>
                            </template>
                            <button type="button" @click="setPage(page + 1)" :disabled="page >= totalPages()"
                                class="grid size-9 place-items-center rounded-xl border border-line bg-surface text-xs font-bold text-ink transition hover:bg-soft disabled:opacity-40" aria-label="Next page">
                                <?= icon('chevron-right', 'size-4') ?>
                            </button>
                        </div>
                    </div>
                </div>
            </template>
        </div>

        <!-- ==================== 2. KANBAN BOARD VIEW (WITH DRAG & DROP) ==================== -->
        <div x-show="view === 'board'" <?= $view !== 'board' ? 'x-cloak' : '' ?> x-transition.opacity>
            <div class="-mx-4 flex snap-x snap-mandatory scroll-px-4 gap-3 overflow-x-auto px-4 pb-3 no-scrollbar lg:mx-0 lg:grid lg:grid-cols-5 lg:overflow-visible lg:px-0">
                <template x-for="st in statuses" :key="st.key">
                    <section class="w-[78vw] max-w-80 shrink-0 snap-center rounded-[1.5rem] p-3 transition sm:w-72 lg:w-auto lg:max-w-none"
                        :class="[st.tint, dragOverCol === st.key ? 'ring-2 ring-brand' : '']"
                        @dragover.prevent="dragOverCol = st.key"
                        @dragleave="if ($event.currentTarget === $event.target) dragOverCol = null"
                        @drop.prevent="onDrop(st.key)"
                        :aria-label="st.label">
                        <div class="mb-3 flex items-center gap-2 px-1">
                            <span class="size-2.5 rounded-full" :class="st.dot"></span>
                            <h3 class="text-sm font-extrabold" x-text="st.label"></h3>
                            <span class="ml-auto rounded-full bg-surface px-2 py-0.5 text-[11px] font-bold" x-text="columnItems(st.key).length"></span>
                        </div>
                        <div class="flex min-h-24 flex-col gap-2.5">
                            <template x-for="c in columnItems(st.key).slice(0, 5)" :key="c.id">
                                <article draggable="true"
                                    @dragstart="onDragStart($event, c.id)"
                                    @dragend="onDragEnd()"
                                    @click="openDetail(c)"
                                    class="cursor-grab rounded-2xl bg-surface p-3 shadow-sm transition hover:shadow-md active:cursor-grabbing"
                                    :class="draggingId === c.id ? 'opacity-40' : ''">
                                    <div class="flex items-start gap-2">
                                        <span class="chip" :class="c.platform_cls" x-text="c.platform_abbr"></span>
                                        <h4 class="line-clamp-3 text-[13px] font-bold leading-snug hover:text-brand" x-text="c.title"></h4>
                                    </div>
                                    <p class="mt-1.5 text-[11px]" :class="c.is_overdue && c.status !== 'published' ? 'font-bold text-rose-600 dark:text-rose-400' : 'text-muted'">
                                        <span x-text="c.format"></span> · <span x-text="c.scheduled_fmt"></span>
                                    </p>
                                    <div class="mt-2 flex items-center justify-between gap-2" @click.stop>
                                        <template x-if="c.status === 'published'">
                                            <span class="text-[11px] font-bold text-muted" x-text="c.views_fmt + ' views'"></span>
                                        </template>
                                        <template x-if="c.status !== 'published'">
                                            <div class="bar-track w-16"><div class="h-full rounded-full bg-accent" :style="'width:' + c.progress + '%'"></div></div>
                                        </template>
                                        <select :value="c.status" @change="changeStatus(c, $event.target.value)" aria-label="Change status"
                                            class="cursor-pointer appearance-none rounded-full border-0 py-1.5 pl-3 pr-3 text-[11px] font-bold focus:outline-2 focus:outline-brand"
                                            :class="statusChip(c.status)">
                                            <template x-for="opt in statuses" :key="opt.key">
                                                <option :value="opt.key" :selected="opt.key === c.status" class="bg-surface text-ink" x-text="opt.label"></option>
                                            </template>
                                        </select>
                                    </div>
                                </article>
                            </template>
                            <template x-if="columnItems(st.key).length > 5">
                                <button type="button" @click="viewAllStatus(st.key)" class="mt-1 w-full rounded-xl bg-surface/90 py-2 text-center text-xs font-extrabold text-brand transition hover:bg-surface">
                                    View all (<span x-text="columnItems(st.key).length"></span>) →
                                </button>
                            </template>
                            <template x-if="columnItems(st.key).length === 0">
                                <p class="rounded-2xl border border-dashed border-line px-3 py-6 text-center text-xs text-muted">Drop content here</p>
                            </template>
                        </div>
                    </section>
                </template>
            </div>
        </div>

        <!-- ==================== 3. CALENDAR VIEW (DAY, WEEK, MONTH + SIDE CONTENT LIST) ==================== -->
        <div x-show="view === 'calendar'" <?= $view !== 'calendar' ? 'x-cloak' : '' ?> x-transition.opacity>
            <div class="grid grid-cols-1 gap-5" :class="calMode === 'month' ? 'lg:grid-cols-[1fr_22rem]' : ''">
                <!-- Left Column: Calendar (Day / Week / Month) -->
                <div class="card p-3 sm:p-5">
                    <!-- Calendar Header -->
                    <div class="mb-4 flex flex-wrap items-center justify-between gap-2">
                        <div class="flex items-center gap-1.5">
                            <button type="button" @click="calStep(-1)" class="grid size-9 place-items-center rounded-xl border border-line hover:bg-soft" aria-label="Previous"><?= icon('chevron-left', 'size-4') ?></button>
                            <button type="button" @click="calToday()" class="rounded-xl border border-line px-3 py-1.5 text-xs font-bold hover:bg-soft">Today</button>
                            <button type="button" @click="calStep(1)" class="grid size-9 place-items-center rounded-xl border border-line hover:bg-soft" aria-label="Next"><?= icon('chevron-right', 'size-4') ?></button>
                            <h2 class="ml-1 text-sm font-extrabold sm:text-base" x-text="calTitle()"></h2>
                        </div>

                        <div class="inline-flex rounded-full bg-soft p-1 text-xs font-bold" role="tablist" aria-label="Calendar mode">
                            <button type="button" role="tab" :aria-selected="calMode === 'day'" @click="setCalMode('day')"
                                class="rounded-full px-3 py-1.5 transition" :class="calMode === 'day' ? 'bg-hero text-white shadow-sm' : 'text-muted hover:text-ink'">Day</button>
                            <button type="button" role="tab" :aria-selected="calMode === 'week'" @click="setCalMode('week')"
                                class="rounded-full px-3 py-1.5 transition" :class="calMode === 'week' ? 'bg-hero text-white shadow-sm' : 'text-muted hover:text-ink'">Week</button>
                            <button type="button" role="tab" :aria-selected="calMode === 'month'" @click="setCalMode('month')"
                                class="rounded-full px-3 py-1.5 transition" :class="calMode === 'month' ? 'bg-hero text-white shadow-sm' : 'text-muted hover:text-ink'">Month</button>
                        </div>
                    </div>

                    <!-- MONTH VIEW -->
                    <template x-if="calMode === 'month'">
                        <div class="grid grid-cols-7 gap-px overflow-hidden rounded-2xl bg-line text-center">
                            <template x-for="dow in ['Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat', 'Sun']" :key="dow">
                                <div class="bg-soft py-2 text-[10px] font-bold uppercase tracking-wide text-muted sm:text-xs" x-text="dow"></div>
                            </template>
                            <template x-for="(cell, idx) in monthCells()" :key="idx">
                                <div @click="cell.date && selectDate(cell.date)"
                                    class="min-h-14 p-1 text-left transition sm:min-h-24 sm:p-1.5"
                                    :class="!cell.inMonth ? 'cursor-pointer bg-surface/60 opacity-60' : (cell.date === selectedDate ? 'cursor-pointer bg-brandsoft/60 ring-2 ring-inset ring-brand' : 'cursor-pointer bg-surface hover:bg-soft/40')">
                                    <template x-if="cell.date">
                                        <div>
                                            <div class="mb-1 flex items-center justify-between">
                                                <span class="grid size-6 place-items-center rounded-full text-[11px] font-bold"
                                                    :class="cell.date === today ? '!bg-accent text-white' : (cell.date === selectedDate ? 'bg-brand text-white' : (cell.inMonth ? 'text-ink' : 'text-muted'))"
                                                    x-text="cell.dayNum"></span>
                                                <span x-show="cell.items.length > 0" class="text-[10px] font-bold text-muted sm:hidden" x-text="cell.items.length"></span>
                                            </div>
                                            <div class="hidden flex-col gap-0.5 sm:flex">
                                                <template x-for="c in cell.items.slice(0, 3)" :key="c.id">
                                                    <button type="button" @click.stop="selectDate(cell.date); openDetail(c)"
                                                        class="w-full truncate rounded-md px-1.5 py-0.5 text-left text-[10px] font-bold transition hover:brightness-95"
                                                        :class="statusChip(c.status)" :title="c.title">
                                                        <span x-text="c.scheduled_time"></span> <span x-text="c.title"></span>
                                                    </button>
                                                </template>
                                                <template x-if="cell.items.length > 3">
                                                    <span class="px-1 text-[10px] font-bold text-muted" x-text="'+' + (cell.items.length - 3) + ' more'"></span>
                                                </template>
                                            </div>
                                            <div class="flex flex-wrap gap-0.5 sm:hidden">
                                                <template x-for="c in cell.items" :key="c.id">
                                                    <span class="size-1.5 rounded-full" :class="statusDot(c.status)"></span>
                                                </template>
                                            </div>
                                        </div>
                                    </template>
                                </div>
                            </template>
                        </div>
                    </template>

                    <!-- WEEK VIEW -->
                    <template x-if="calMode === 'week'">
                        <div class="flex snap-x gap-2 overflow-x-auto pb-2 no-scrollbar sm:grid sm:grid-cols-7 sm:gap-0 sm:overflow-visible sm:pb-0 sm:divide-x sm:divide-line sm:rounded-2xl sm:border sm:border-line">
                            <template x-for="day in weekDays()" :key="day.date">
                                <div @click="selectDate(day.date)"
                                    class="flex min-h-40 w-44 min-w-0 shrink-0 snap-start cursor-pointer flex-col rounded-2xl border p-2.5 transition sm:min-h-56 sm:w-auto sm:rounded-none sm:border-0"
                                    :class="day.date === selectedDate ? 'border-brand bg-brandsoft/40 sm:bg-brandsoft/40' : 'border-line bg-soft2/60 hover:bg-soft'">
                                    <div class="mb-2 flex items-center justify-between border-b border-line pb-1.5">
                                        <span class="text-xs font-bold text-muted" x-text="day.dow"></span>
                                        <span class="grid size-6 place-items-center rounded-full text-xs font-extrabold"
                                            :class="day.date === today ? '!bg-accent text-white' : (day.date === selectedDate ? 'bg-brand text-white' : 'text-ink')"
                                            x-text="day.dayNum"></span>
                                    </div>
                                    <div class="flex flex-col gap-1.5">
                                        <template x-for="c in day.items" :key="c.id">
                                            <button type="button" @click.stop="selectDate(day.date); openDetail(c)"
                                                class="flex flex-col gap-0.5 rounded-xl bg-surface p-2 text-left shadow-sm transition hover:shadow-md">
                                                <div class="flex items-center justify-between gap-1">
                                                    <span class="chip !px-1.5 !py-0.5 !text-[9px]" :class="c.platform_cls" x-text="c.platform_abbr"></span>
                                                    <span class="text-[10px] font-bold text-muted" x-text="c.scheduled_time"></span>
                                                </div>
                                                <span class="line-clamp-2 text-[11px] font-bold leading-tight text-ink" x-text="c.title"></span>
                                            </button>
                                        </template>
                                        <template x-if="day.items.length === 0">
                                            <span class="py-2 text-center text-[11px] text-muted opacity-60">No posts</span>
                                        </template>
                                    </div>
                                </div>
                            </template>
                        </div>
                    </template>

                    <!-- DAY VIEW -->
                    <template x-if="calMode === 'day'">
                        <div class="flex flex-col gap-3">
                            <div class="flex items-center justify-between rounded-2xl bg-soft2 px-4 py-3">
                                <div>
                                    <div class="text-xs font-semibold text-muted" x-text="selectedDate === today ? 'Today’s Schedule' : 'Selected Day Schedule'"></div>
                                    <div class="text-base font-extrabold" x-text="formatDateFull(selectedDate)"></div>
                                </div>
                                <a :href="newDateUrl(selectedDate)" class="btn-primary !px-3.5 !py-2 !text-xs"><?= icon('plus', 'size-4') ?>Add on this day</a>
                            </div>

                            <template x-if="selectedDateItems().length === 0">
                                <div class="rounded-2xl border border-dashed border-line px-4 py-10 text-center">
                                    <p class="text-sm font-bold">No content scheduled on this day</p>
                                    <p class="mt-1 text-xs text-muted">Click the button above to schedule a post for <span x-text="formatDateFull(selectedDate)"></span>.</p>
                                </div>
                            </template>

                            <div class="flex flex-col gap-2.5">
                                <template x-for="c in selectedDateItems()" :key="c.id">
                                    <div @click="openDetail(c)" class="flex cursor-pointer items-center gap-3 rounded-2xl border border-line bg-soft2 p-3.5 transition hover:bg-soft">
                                        <div class="w-14 shrink-0 text-center">
                                            <span class="block text-xs font-extrabold text-brand" x-text="c.scheduled_time || '--:--'"></span>
                                            <span class="text-[10px] text-muted" x-text="c.format"></span>
                                        </div>
                                        <span class="grid size-10 shrink-0 place-items-center rounded-xl text-xs font-extrabold" :class="c.platform_cls" x-text="c.platform_abbr"></span>
                                        <div class="min-w-0 flex-1">
                                            <div class="truncate text-sm font-extrabold" x-text="c.title"></div>
                                            <div class="text-xs text-muted" x-text="c.pillar || c.campaign_name || 'No pillar'"></div>
                                        </div>
                                        <span class="chip" :class="statusChip(c.status)" x-text="statusLabel(c.status)"></span>
                                    </div>
                                </template>
                            </div>
                        </div>
                    </template>
                </div>

                <!-- Right Column: 1 Kolom List Content untuk Tanggal yang Diklik (Hanya di Month View) -->
                <aside x-show="calMode === 'month'" class="card flex flex-col p-4 sm:p-5" aria-label="Content for selected date">
                    <div class="mb-3 flex items-center justify-between gap-2 border-b border-line pb-3">
                        <div class="min-w-0">
                            <div class="flex items-center gap-1.5">
                                <span class="text-xs font-extrabold uppercase tracking-wider text-brand" x-text="selectedDate === today ? 'Today' : 'Selected Date'"></span>
                                <span class="rounded-full bg-soft px-2 py-0.5 text-[11px] font-bold text-ink" x-text="selectedDateItems().length"></span>
                            </div>
                            <h3 class="mt-0.5 truncate text-sm font-extrabold" x-text="formatDateFull(selectedDate)"></h3>
                        </div>
                        <a :href="newDateUrl(selectedDate)" class="btn-primary shrink-0 !px-3 !py-2 !text-xs" title="Add content on this date"><?= icon('plus', 'size-4') ?>Add</a>
                    </div>

                    <template x-if="selectedDateItems().length === 0">
                        <div class="my-auto flex flex-col items-center justify-center py-10 text-center">
                            <div class="grid size-12 place-items-center rounded-2xl bg-soft text-brand"><?= icon('calendar', 'size-6') ?></div>
                            <p class="mt-2.5 text-sm font-extrabold">No content on this date</p>
                            <p class="mt-1 max-w-[15rem] text-xs text-muted">Click another date on the calendar or schedule a new post for this day.</p>
                            <a :href="newDateUrl(selectedDate)" class="btn-soft mt-3 !px-3.5 !py-2 !text-xs"><?= icon('plus', 'size-3.5') ?>Plan content</a>
                        </div>
                    </template>

                    <div class="flex flex-col gap-2.5" x-show="selectedDateItems().length > 0">
                        <template x-for="c in selectedDateItems()" :key="c.id">
                            <article @click="openDetail(c)" class="cursor-pointer rounded-2xl border border-line bg-soft2 p-3 transition hover:border-brand hover:shadow-sm">
                                <div class="flex items-start gap-2.5">
                                    <span class="grid size-9 shrink-0 place-items-center rounded-xl text-[11px] font-extrabold" :class="c.platform_cls" x-text="c.platform_abbr"></span>
                                    <div class="min-w-0 flex-1">
                                        <h4 class="line-clamp-2 text-xs font-extrabold leading-snug hover:text-brand" x-text="c.title"></h4>
                                        <p class="mt-0.5 text-[11px] text-muted">
                                            <span x-text="c.scheduled_time || 'All day'"></span> · <span x-text="c.format"></span>
                                            <template x-if="c.pillar"><span> · <span x-text="c.pillar"></span></span></template>
                                        </p>
                                    </div>
                                </div>
                                <div class="mt-2.5 flex items-center justify-between gap-2 border-t border-line pt-2" @click.stop>
                                    <select :value="c.status" @change="changeStatus(c, $event.target.value)" aria-label="Change status"
                                        class="cursor-pointer appearance-none rounded-full border-0 py-1 pl-2.5 pr-2.5 text-[10px] font-bold focus:outline-2 focus:outline-brand"
                                        :class="statusChip(c.status)">
                                        <template x-for="st in statuses" :key="st.key">
                                            <option :value="st.key" :selected="st.key === c.status" class="bg-surface text-ink" x-text="st.label"></option>
                                        </template>
                                    </select>
                                    <a :href="itemUrl(c.id)" class="text-[11px] font-bold text-brand hover:underline">Edit →</a>
                                </div>
                            </article>
                        </template>
                    </div>
                </aside>
            </div>
        </div>
    </div>
    <?php
    page('Content', 'content', (string) ob_get_clean(), ['subtitle' => 'Content calendar & pipeline']);
}

function page_content_form(int $id = 0): void
{
    $c = $id ? q1('SELECT * FROM contents WHERE id=?', [$id]) : null;
    if ($id && !$c) {
        not_found();
    }
    $allPlatforms = array_keys(platforms());
    $allFormats = formats();
    $allPillars = pillars();
    $allStatuses = statuses();

    $c ??= [
        'title' => '', 'platform' => $allPlatforms[0] ?? 'Instagram', 'format' => $allFormats[0] ?? 'Feed', 'pillar' => '', 'status' => isset($allStatuses['draft']) ? 'draft' : (string) array_key_first($allStatuses),
        'campaign_id' => (int) input('campaign') ?: '', 'scheduled_at' => preg_match('/^\d{4}-\d{2}-\d{2}$/', input('date')) ? input('date') . ' 09:00' : date('Y-m-d H:00', strtotime('+1 day')),
        'progress' => 0, 'caption' => '', 'hashtags' => '', 'post_url' => '', 'views' => 0, 'likes' => 0, 'comments' => 0, 'shares' => 0, 'saves' => 0,
    ];
    $opt = fn(array $a) => array_combine($a, $a);

    $f = field('title', 'Title / Topic', 'text', $c['title'], ['req' => true, 'max' => 160, 'ph' => 'e.g. 5-Minute Daily Mobility Checklist'])
        . '<div class="grid grid-cols-1 gap-4 sm:grid-cols-2">'
        . field('platform', 'Platform', 'select', $c['platform'], ['options' => $opt($allPlatforms)])
        . field('format', 'Format', 'select', $c['format'], ['options' => $opt($allFormats)])
        . field('pillar', 'Content Pillar', 'select', $c['pillar'], ['options' => ['' => '— Select —'] + $opt($allPillars)])
        . field('campaign_id', 'Campaign', 'select', $c['campaign_id'], ['options' => campaign_options()])
        . field('status', 'Status', 'select', $c['status'], ['options' => array_map(fn($s) => $s['label'], $allStatuses)])
        . field('scheduled_at', 'Scheduled Date & Time', 'datetime', to_input_dt($c['scheduled_at']))
        . '</div>'
        . field('progress', 'Completion Progress', 'range', (int) $c['progress'], ['step' => 5])
        . field('caption', 'Caption / Script', 'textarea', $c['caption'], ['rows' => 5, 'counter' => 2200, 'ph' => 'Write your caption or script outline here…'])
        . field('hashtags', 'Hashtags', 'text', $c['hashtags'], ['ph' => '#movestudio #dailymovement'])
        . section_label('Performance Metrics (fill after publishing)')
        . field('post_url', 'Published Post URL', 'url', $c['post_url'], ['ph' => 'https://…'])
        . '<div class="grid grid-cols-2 gap-4 sm:grid-cols-5">'
        . field('views', 'Views', 'number', $c['views']) . field('likes', 'Likes', 'number', $c['likes'])
        . field('comments', 'Comments', 'number', $c['comments']) . field('shares', 'Shares', 'number', $c['shares'])
        . field('saves', 'Saves', 'number', $c['saves']) . '</div>';

    $leftDeleteBtn = '';
    $hiddenDeleteForm = '';
    if ($id) {
        $leftDeleteBtn = '<button type="button" class="btn-ghost !text-rose-600" aria-label="Delete content" title="Delete content" x-data @click="$store.confirm.ask(\'Delete this content item?\', () => document.getElementById(\'del-content-form\').submit())">'
            . icon('trash', 'size-4') . '<span class="hidden sm:inline">Delete content</span></button>';
        $hiddenDeleteForm = '<form id="del-content-form" method="post" action="' . e(url('/content/' . $id . '/delete')) . '" class="hidden">'
            . csrf_field()
            . '<input type="hidden" name="back" value="' . e(input('back')) . '">'
            . '</form>';
    }

    $form = form_shell($id ? '/content/' . $id : '/content/new', $f, '/content', $leftDeleteBtn, $hiddenDeleteForm);

    page($id ? 'Edit Content' : 'New Content', 'content', $form, ['subtitle' => $id ? e($c['title']) : 'Plan your next post']);
}

function action_content_save(int $id = 0): void
{
    $title = mb_substr(input('title'), 0, 160);
    if ($title === '') {
        flash('Title is required.', 'err');
        redirect($id ? "/content/$id" : '/content/new');
    }
    $allPlatforms = platforms();
    $allFormats = formats();
    $allPillars = pillars();
    $allStatuses = statuses();

    $platform = isset($allPlatforms[input('platform')]) ? input('platform') : (string) array_key_first($allPlatforms);
    $format = in_array(input('format'), $allFormats, true) ? input('format') : ($allFormats[0] ?? 'Feed');
    $pillar = in_array(input('pillar'), $allPillars, true) ? input('pillar') : '';
    $status = isset($allStatuses[input('status')]) ? input('status') : (isset($allStatuses['draft']) ? 'draft' : (string) array_key_first($allStatuses));
    $cid = (int) input('campaign_id');
    if ($cid && !q1('SELECT 1 FROM campaigns WHERE id=?', [$cid])) {
        $cid = 0;
    }
    $sched = from_input_dt(input('scheduled_at'));
    $progress = $status === 'published' ? 100 : clamp((int) input('progress'), 0, 100);
    $n = fn(string $k) => max(0, (int) input($k));
    $url = filter_var(input('post_url'), FILTER_VALIDATE_URL) && preg_match('#^https?://#i', input('post_url')) ? input('post_url') : '';

    $old = $id ? q1('SELECT * FROM contents WHERE id=?', [$id]) : null;
    $pub = null;
    if ($status === 'published') {
        $pub = $old['published_at'] ?? null;
        $pub ??= ($sched && $sched <= now_str()) ? $sched : now_str();
    }
    $vals = [$title, $platform, $format, $pillar, $status, $cid ?: null, $sched, $pub, $progress, input('caption'), input('hashtags'), $url, $n('views'), $n('likes'), $n('comments'), $n('shares'), $n('saves')];

    if ($id) {
        exec_sql("UPDATE contents SET title=?,platform=?,format=?,pillar=?,status=?,campaign_id=?,scheduled_at=?,published_at=?,progress=?,caption=?,hashtags=?,post_url=?,views=?,likes=?,comments=?,shares=?,saves=?,updated_at=datetime('now','localtime') WHERE id=?", [...$vals, $id]);
        flash('Content updated.');
    } else {
        exec_sql('INSERT INTO contents(title,platform,format,pillar,status,campaign_id,scheduled_at,published_at,progress,caption,hashtags,post_url,views,likes,comments,shares,saves) VALUES(?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)', $vals);
        flash('Content created.');
    }
    redirect(safe_back('/content'));
}

function action_content_status(int $id): void
{
    $s = input('status');
    $allStatuses = statuses();
    $c = q1('SELECT * FROM contents WHERE id=?', [$id]);
    if (!$c || !isset($allStatuses[$s])) {
        if (is_ajax()) {
            json_out(['ok' => false, 'error' => 'Invalid content or status.'], 404);
        }
        not_found();
    }
    $pub = $s === 'published' ? ($c['published_at'] ?: now_str()) : null;
    $prog = $s === 'published' ? 100 : (int) $c['progress'];
    exec_sql("UPDATE contents SET status=?, published_at=?, progress=?, updated_at=datetime('now','localtime') WHERE id=?", [$s, $pub, $prog, $id]);
    $msg = 'Status: ' . $allStatuses[$s]['label'] . '.';
    if (is_ajax()) {
        $updated = q1('SELECT c.*, ca.name AS campaign_name FROM contents c LEFT JOIN campaigns ca ON ca.id=c.campaign_id WHERE c.id=?', [$id]);
        json_out(['ok' => true, 'msg' => $msg, 'item' => content_payload($updated)]);
    }
    flash($msg);
    redirect(safe_back('/content'));
}

function action_content_duplicate(int $id): void
{
    $c = q1('SELECT * FROM contents WHERE id=?', [$id]) ?? not_found();
    exec_sql("INSERT INTO contents(title,platform,format,pillar,status,campaign_id,caption,hashtags) VALUES(?,?,?,?, 'draft', ?,?,?)",
        [mb_substr($c['title'], 0, 140) . ' (copy)', $c['platform'], $c['format'], $c['pillar'], $c['campaign_id'], $c['caption'], $c['hashtags']]);
    $newId = (int) db()->lastInsertId();
    flash('Content duplicated as draft.');
    redirect('/content/' . $newId);
}

function action_content_delete(int $id): void
{
    exec_sql('DELETE FROM contents WHERE id=?', [$id]);
    if (is_ajax()) {
        json_out(['ok' => true, 'msg' => 'Content deleted.']);
    }
    flash('Content deleted.');
    $back = safe_back('/content');
    redirect(str_contains($back, '/content/' . $id) ? '/content' : $back);
}
