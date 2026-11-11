<?php
declare(strict_types=1);

defined('ROOT') || (http_response_code(403) && exit);

function campaign_stats(int $id): array
{
    return q1("SELECT COUNT(*) AS total,
                      SUM(status='published') AS done,
                      COALESCE(SUM(CASE WHEN status='published' THEN views END),0) AS views,
                      COALESCE(SUM(CASE WHEN status='published' THEN likes+comments+shares+saves END),0) AS inter
               FROM contents WHERE campaign_id=?", [$id]) ?: ['total' => 0, 'done' => 0, 'views' => 0, 'inter' => 0];
}

function page_campaigns(): void
{
    $f = input('status');
    $rows = q("SELECT * FROM campaigns ORDER BY CASE status WHEN 'active' THEN 0 WHEN 'planned' THEN 1 ELSE 2 END, start_date DESC, id DESC");

    $campaigns = [];
    foreach ($rows as $c) {
        $s = campaign_stats((int) $c['id']);
        $pct = $s['total'] > 0 ? (int) round($s['done'] / $s['total'] * 100) : 0;
        $inter = $s['views'] > 0 ? $s['inter'] / $s['views'] * 100 : 0;
        $campaigns[] = [
            'id'         => (int) $c['id'],
            'name'       => (string) $c['name'],
            'goal'       => (string) ($c['goal'] ?: 'No campaign goal specified.'),
            'status'     => (string) $c['status'],
            'start_fmt'  => fmt_date($c['start_date']),
            'end_fmt'    => fmt_date($c['end_date']),
            'grad'       => CAMPAIGN_COLORS[$c['color']] ?? CAMPAIGN_COLORS['blue'],
            'total'      => (int) $s['total'],
            'done'       => (int) $s['done'],
            'pct'        => $pct,
            'views_fmt'  => fmt_k($s['views']),
            'er_fmt'     => fmt_pct($inter),
        ];
    }

    $cStatuses = [];
    foreach (CAMPAIGN_STATUSES as $k => $m) {
        $cStatuses[] = ['key' => $k, 'label' => $m['label'], 'chip' => $m['chip']];
    }

    $config = [
        'items'    => $campaigns,
        'statuses' => $cStatuses,
        'status'   => isset(CAMPAIGN_STATUSES[$f]) ? $f : '',
        'q'        => input('q'),
        'csrf'     => csrf_token(),
        'baseUrl'  => url(''),
    ];

    ob_start(); ?>
    <div x-data="campaignManager(<?= e(json_encode($config, JSON_UNESCAPED_UNICODE)) ?>)">
        <!-- Search Filter -->
        <label class="relative mb-3 block">
            <span class="sr-only">Search campaigns</span>
            <span class="pointer-events-none absolute left-3.5 top-1/2 -translate-y-1/2 text-muted"><?= icon('search', 'size-4') ?></span>
            <input type="search" x-model="q" @input.debounce.200ms="onFilterChange()" placeholder="Search campaigns…" class="input !pl-10">
        </label>

        <div class="mb-4 flex items-center gap-2">
            <div class="flex min-w-0 flex-1 gap-2 overflow-x-auto no-scrollbar">
                <button type="button" class="pill" :aria-current="status === '' ? 'true' : 'false'" @click="setStatus('')">
                    All <b x-text="items.length"></b>
                </button>
                <template x-for="st in statuses" :key="st.key">
                    <button type="button" class="pill" :aria-current="status === st.key ? 'true' : 'false'" @click="setStatus(st.key)">
                        <span x-text="st.label"></span> <b x-text="countByStatus(st.key)"></b>
                    </button>
                </template>
            </div>
            <a href="<?= e(url('/campaigns/new')) ?>" class="btn-primary shrink-0 !px-3.5 sm:!px-4" aria-label="New campaign"><?= icon('plus', 'size-4') ?><span class="hidden sm:inline">Campaign</span></a>
        </div>

        <template x-if="filtered().length === 0">
            <div class="card grid place-items-center gap-2 px-6 py-12 text-center">
                <div class="grid size-14 place-items-center rounded-2xl bg-soft text-brand"><?= icon('flag', 'size-7') ?></div>
                <h3 class="text-base font-extrabold">No campaigns found</h3>
                <p class="max-w-sm text-sm text-muted">Group your content into campaigns to track goals and results.</p>
                <a href="<?= e(url('/campaigns/new')) ?>" class="btn-primary mt-2"><?= icon('plus', 'size-4') ?>Create campaign</a>
            </div>
        </template>

        <div x-show="filtered().length > 0" class="grid grid-cols-1 gap-4 sm:grid-cols-2 2xl:grid-cols-3">
            <template x-for="c in filtered()" :key="c.id">
                <article class="card group flex flex-col overflow-hidden transition hover:-translate-y-0.5 hover:shadow-lg">
                    <div class="relative bg-linear-to-br p-5 text-white" :class="c.grad">
                        <div class="relative z-10 flex items-center justify-between gap-2">
                            <select :value="c.status" @change="changeCampaignStatus(c, $event.target.value)" aria-label="Campaign status"
                                class="cursor-pointer appearance-none rounded-full border-0 bg-white/25 py-1 pl-3 pr-3 text-[11px] font-bold text-white focus:outline-2 focus:outline-white">
                                <template x-for="st in statuses" :key="st.key">
                                    <option :value="st.key" :selected="st.key === c.status" class="bg-surface text-ink" x-text="st.label"></option>
                                </template>
                            </select>
                        </div>
                        <a :href="baseUrl + '/campaigns/' + c.id" class="mt-2 block">
                            <h2 class="text-lg font-extrabold leading-snug hover:underline" x-text="c.name"></h2>
                            <p class="mt-1 text-xs text-white/85"><span x-text="c.start_fmt"></span> – <span x-text="c.end_fmt"></span></p>
                        </a>
                        <span class="pointer-events-none absolute right-4 top-4 opacity-30"><?= icon('flag', 'size-10') ?></span>
                    </div>
                    <a :href="baseUrl + '/campaigns/' + c.id" class="flex flex-1 flex-col gap-3 p-5">
                        <p class="line-clamp-2 text-xs text-muted" x-text="c.goal"></p>
                        <div>
                            <div class="mb-1 flex justify-between text-[11px] font-bold">
                                <span><span x-text="c.done"></span>/<span x-text="c.total"></span> posts published</span>
                                <span class="text-muted" x-text="c.pct + '%'"></span>
                            </div>
                            <div class="bar-track"><div class="h-full rounded-full bg-brand" :style="'width:' + c.pct + '%'"></div></div>
                        </div>
                        <div class="mt-auto flex gap-4 text-xs font-semibold text-muted">
                            <span class="inline-flex items-center gap-1"><?= icon('eye', 'size-4') ?><span x-text="c.views_fmt"></span></span>
                            <span class="inline-flex items-center gap-1"><?= icon('heart', 'size-4') ?>ER <span x-text="c.er_fmt"></span></span>
                        </div>
                    </a>
                </article>
            </template>
        </div>
    </div>
    <?php
    page('Campaigns', 'campaign', (string) ob_get_clean(), ['subtitle' => 'Organize content by objective']);
}

function page_campaign_detail(int $id): void
{
    $c = q1('SELECT * FROM campaigns WHERE id=?', [$id]) ?? not_found();
    $s = campaign_stats($id);
    $rows = q('SELECT c.*, ca.name AS campaign_name FROM contents c LEFT JOIN campaigns ca ON ca.id=c.campaign_id WHERE c.campaign_id=? ORDER BY (c.scheduled_at IS NULL), c.scheduled_at DESC, c.id DESC', [$id]);
    $inter = $s['views'] > 0 ? $s['inter'] / $s['views'] * 100 : 0;

    $items = array_map('content_payload', $rows);
    $allStatuses = [];
    foreach (statuses() as $k => $m) {
        $allStatuses[] = ['key' => (string) $k, 'label' => (string) $m['label'], 'chip' => (string) $m['chip'], 'dot' => (string) $m['dot']];
    }
    $cStatuses = [];
    foreach (CAMPAIGN_STATUSES as $k => $m) {
        $cStatuses[] = ['key' => $k, 'label' => $m['label'], 'chip' => $m['chip']];
    }

    $config = [
        'campaignId'       => $id,
        'campaignStatus'   => (string) $c['status'],
        'campaignStatuses' => $cStatuses,
        'items'            => $items,
        'statuses'         => $allStatuses,
        'page'             => max(1, (int) input('page', '1')),
        'perPage'          => 5,
        'csrf'             => csrf_token(),
        'baseUrl'          => url(''),
    ];

    ob_start(); ?>
    <div x-data="campaignDetail(<?= e(json_encode($config, JSON_UNESCAPED_UNICODE)) ?>)" class="mx-auto flex max-w-4xl flex-col gap-5">
        <section class="overflow-hidden rounded-[1.75rem] bg-linear-to-br <?= CAMPAIGN_COLORS[$c['color']] ?? CAMPAIGN_COLORS['blue'] ?> p-6 text-white shadow-card">
            <div class="flex flex-wrap items-start gap-3">
                <div class="min-w-0 flex-1">
                    <select :value="campaignStatus" @change="updateCampaignStatus($event.target.value)" aria-label="Campaign status"
                        class="cursor-pointer appearance-none rounded-full border-0 bg-white/25 py-1 pl-3 pr-3 text-xs font-bold text-white focus:outline-2 focus:outline-white">
                        <template x-for="cs in campaignStatuses" :key="cs.key">
                            <option :value="cs.key" :selected="cs.key === campaignStatus" class="bg-surface text-ink" x-text="cs.label"></option>
                        </template>
                    </select>
                    <h2 class="mt-2 text-2xl font-extrabold leading-tight"><?= e($c['name']) ?></h2>
                    <p class="mt-1 text-sm text-white/90"><?= e(fmt_date($c['start_date'])) ?> – <?= e(fmt_date($c['end_date'])) ?></p>
                </div>
                <a href="<?= e(url('/campaigns/' . $id . '/edit')) ?>" class="btn bg-white/20 text-white hover:bg-white/30"><?= icon('pencil', 'size-4') ?>Edit</a>
            </div>
            <?php if ($c['goal']): ?><p class="mt-3 max-w-2xl text-sm leading-relaxed text-white/95"><?= e($c['goal']) ?></p><?php endif; ?>
            <div class="mt-5 grid grid-cols-2 gap-3 sm:grid-cols-4">
                <div class="rounded-2xl bg-white/15 px-4 py-3"><div class="text-[11px] font-semibold text-white/80">Posts</div><div class="text-xl font-extrabold" x-text="items.length"><?= (int) $s['total'] ?></div></div>
                <div class="rounded-2xl bg-white/15 px-4 py-3"><div class="text-[11px] font-semibold text-white/80">Published</div><div class="text-xl font-extrabold" x-text="publishedCount()"><?= (int) $s['done'] ?></div></div>
                <div class="rounded-2xl bg-white/15 px-4 py-3"><div class="text-[11px] font-semibold text-white/80">Views</div><div class="text-xl font-extrabold"><?= e(fmt_k($s['views'])) ?></div></div>
                <div class="rounded-2xl bg-white/15 px-4 py-3"><div class="text-[11px] font-semibold text-white/80">ER</div><div class="text-xl font-extrabold"><?= e(fmt_pct($inter)) ?></div></div>
            </div>
        </section>

        <div class="flex items-center justify-between">
            <h2 class="section-title">Content in this campaign</h2>
            <a href="<?= e(url('/content/new?campaign=' . $id)) ?>" class="btn-primary"><?= icon('plus', 'size-4') ?>Add Content</a>
        </div>

        <template x-if="items.length === 0">
            <?= empty_state('calendar', 'No content yet', 'Add the first post to this campaign.') ?>
        </template>

        <template x-if="items.length > 0">
            <div>
                <div class="flex flex-col gap-3">
                    <template x-for="item in pagedItems()" :key="item.id">
                        <article @click="openDetail(item)" class="card flex cursor-pointer flex-col gap-3 p-4 transition hover:shadow-md sm:flex-row sm:items-center sm:gap-4">
                            <div class="flex min-w-0 flex-1 items-start gap-3">
                                <span class="grid size-11 shrink-0 place-items-center rounded-2xl text-xs font-extrabold" :class="item.platform_cls" x-text="item.platform_abbr"></span>
                                <div class="min-w-0 flex-1">
                                    <h3 class="line-clamp-2 text-[15px] font-extrabold leading-snug hover:text-brand" x-text="item.title"></h3>
                                    <p class="mt-0.5 truncate text-xs text-muted">
                                        <span x-text="item.format"></span>
                                        <template x-if="item.pillar"><span> · <span x-text="item.pillar"></span></span></template>
                                    </p>
                                    <p class="mt-1 flex flex-wrap items-center gap-x-2 text-xs" :class="item.is_overdue && item.status !== 'published' ? 'font-bold text-rose-600 dark:text-rose-400' : 'text-muted'">
                                        <?= icon('clock', 'size-3.5') ?><span x-text="item.scheduled_fmt"></span>
                                        <template x-if="item.is_overdue && item.status !== 'published'"><span> · Overdue</span></template>
                                    </p>
                                    <template x-if="item.status === 'published'">
                                        <p class="mt-1.5 flex flex-wrap gap-x-3 text-[11px] font-semibold text-muted">
                                            <span class="inline-flex items-center gap-1"><?= icon('eye', 'size-3.5') ?><span x-text="item.views_fmt"></span></span>
                                            <span class="inline-flex items-center gap-1"><?= icon('heart', 'size-3.5') ?><span x-text="item.likes_fmt"></span></span>
                                            <span class="inline-flex items-center gap-1"><?= icon('comment', 'size-3.5') ?><span x-text="item.comments_fmt"></span></span>
                                            <span class="text-emerald-600 dark:text-emerald-400">ER <span x-text="item.er_fmt"></span></span>
                                        </p>
                                    </template>
                                    <template x-if="item.status !== 'published'">
                                        <div class="mt-2 flex items-center gap-2">
                                            <div class="bar-track w-28"><div class="h-full rounded-full bg-accent" :style="'width:' + item.progress + '%'"></div></div>
                                            <span class="text-[11px] font-bold text-muted" x-text="item.progress + '%'"></span>
                                        </div>
                                    </template>
                                </div>
                            </div>
                            <div class="flex items-center justify-between gap-2 sm:justify-end" @click.stop>
                                <select :value="item.status" @change="changeContentStatus(item, $event.target.value)" aria-label="Change status"
                                    class="cursor-pointer appearance-none rounded-full border-0 py-1.5 pl-3 pr-3 text-[11px] font-bold focus:outline-2 focus:outline-brand"
                                    :class="statusChip(item.status)">
                                    <template x-for="st in statuses" :key="st.key">
                                        <option :value="st.key" :selected="st.key === item.status" class="bg-surface text-ink" x-text="st.label"></option>
                                    </template>
                                </select>
                                <div class="flex items-center gap-1">
                                    <a :href="baseUrl + '/content/' + item.id" class="grid size-9 place-items-center rounded-xl text-muted hover:bg-soft hover:text-ink" aria-label="Edit" title="Edit"><?= icon('pencil', 'size-4') ?></a>
                                    <form method="post" :action="baseUrl + '/content/' + item.id + '/duplicate'" class="inline"><?= csrf_field() ?><button class="grid size-9 place-items-center rounded-xl text-muted hover:bg-soft hover:text-ink" aria-label="Duplicate" title="Duplicate"><?= icon('copy', 'size-4') ?></button></form>
                                    <button type="button" @click="deleteContentItem(item)" class="grid size-9 place-items-center rounded-xl text-muted hover:bg-rose-50 hover:text-rose-600 dark:hover:bg-rose-500/10" aria-label="Delete" title="Delete"><?= icon('trash', 'size-4') ?></button>
                                </div>
                            </div>
                        </article>
                    </template>
                </div>

                <!-- Pagination Bar -->
                <div x-show="items.length > 0" class="mt-4 flex flex-col items-center justify-center gap-2 rounded-3xl bg-surface px-4 py-3 text-center shadow-sm sm:flex-row sm:justify-between sm:text-left">
                    <p class="text-xs text-muted">
                        Showing <b class="text-ink" x-text="((page - 1) * perPage) + 1"></b>–<b class="text-ink" x-text="Math.min(page * perPage, items.length)"></b>
                        of <b class="text-ink" x-text="items.length"></b> posts
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

        <div class="text-right"><?= delete_form('/campaigns/' . $id . '/delete', 'Delete this campaign? Posts inside it will not be deleted.', 'Delete campaign') ?></div>
    </div>
    <?php
    page($c['name'], 'campaign', (string) ob_get_clean(), ['subtitle' => 'Campaign details']);
}

function page_campaign_form(int $id = 0): void
{
    $c = $id ? q1('SELECT * FROM campaigns WHERE id=?', [$id]) : null;
    if ($id && !$c) {
        not_found();
    }
    $c ??= ['name' => '', 'goal' => '', 'status' => 'planned', 'start_date' => date('Y-m-d'), 'end_date' => date('Y-m-d', strtotime('+30 days')), 'color' => 'blue'];

    $swatches = '';
    foreach (CAMPAIGN_COLORS as $k => $grad) {
        $swatches .= '<label class="cursor-pointer"><input type="radio" name="color" value="' . $k . '" class="peer sr-only"' . ($c['color'] === $k ? ' checked' : '') . '>'
            . '<span class="block size-10 rounded-2xl bg-linear-to-br ' . $grad . ' ring-offset-2 ring-offset-[var(--c-surface)] transition peer-checked:ring-2 peer-checked:ring-brand peer-focus-visible:ring-2"></span><span class="sr-only">' . e($k) . '</span></label>';
    }
    $f = field('name', 'Campaign Name', 'text', $c['name'], ['req' => true, 'max' => 120])
        . field('goal', 'Goal / Objective', 'textarea', $c['goal'], ['rows' => 3, 'ph' => 'What do you want to achieve?'])
        . '<div class="grid grid-cols-1 gap-4 sm:grid-cols-3">'
        . field('status', 'Status', 'select', $c['status'], ['options' => array_map(fn($s) => $s['label'], CAMPAIGN_STATUSES)])
        . field('start_date', 'Start Date', 'date', $c['start_date'])
        . field('end_date', 'End Date', 'date', $c['end_date'])
        . '</div><div><span class="label">Color</span><div class="flex flex-wrap gap-3">' . $swatches . '</div></div>';

    $leftDeleteBtn = '';
    $hiddenDeleteForm = '';
    if ($id) {
        $leftDeleteBtn = '<button type="button" class="btn-ghost !text-rose-600" aria-label="Delete campaign" title="Delete campaign" x-data @click="$store.confirm.ask(\'Delete this campaign?\', () => document.getElementById(\'del-campaign-form\').submit())">'
            . icon('trash', 'size-4') . '<span class="hidden sm:inline">Delete campaign</span></button>';
        $hiddenDeleteForm = '<form id="del-campaign-form" method="post" action="' . e(url('/campaigns/' . $id . '/delete')) . '" class="hidden">'
            . csrf_field() . '</form>';
    }

    page($id ? 'Edit Campaign' : 'New Campaign', 'campaign', form_shell($id ? "/campaigns/$id/edit" : '/campaigns/new', $f, $id ? "/campaigns/$id" : '/campaigns', $leftDeleteBtn, $hiddenDeleteForm), ['subtitle' => $id ? e($c['name']) : 'Create a new campaign']);
}

function action_campaign_save(int $id = 0): void
{
    $name = mb_substr(input('name'), 0, 120);
    if ($name === '') {
        flash('Campaign name is required.', 'err');
        redirect($id ? "/campaigns/$id/edit" : '/campaigns/new');
    }
    $date = fn(string $k) => preg_match('/^\d{4}-\d{2}-\d{2}$/', input($k)) ? input($k) : null;
    $vals = [$name, input('goal'), isset(CAMPAIGN_STATUSES[input('status')]) ? input('status') : 'planned', $date('start_date'), $date('end_date'), isset(CAMPAIGN_COLORS[input('color')]) ? input('color') : 'blue'];
    if ($id) {
        exec_sql('UPDATE campaigns SET name=?,goal=?,status=?,start_date=?,end_date=?,color=? WHERE id=?', [...$vals, $id]);
        flash('Campaign updated.');
        redirect("/campaigns/$id");
    }
    exec_sql('INSERT INTO campaigns(name,goal,status,start_date,end_date,color) VALUES(?,?,?,?,?,?)', $vals);
    flash('Campaign created.');
    redirect('/campaigns/' . (int) db()->lastInsertId());
}

function action_campaign_status(int $id): void
{
    $c = q1('SELECT * FROM campaigns WHERE id=?', [$id]);
    $s = input('status');
    if (!$c || !isset(CAMPAIGN_STATUSES[$s])) {
        if (is_ajax()) {
            json_out(['ok' => false, 'error' => 'Invalid campaign or status.'], 400);
        }
        not_found();
    }
    exec_sql('UPDATE campaigns SET status=? WHERE id=?', [$s, $id]);
    $msg = 'Campaign status: ' . CAMPAIGN_STATUSES[$s]['label'] . '.';
    if (is_ajax()) {
        json_out(['ok' => true, 'msg' => $msg, 'status' => $s]);
    }
    flash($msg);
    redirect(safe_back('/campaigns/' . $id));
}

function action_campaign_delete(int $id): void
{
    exec_sql('DELETE FROM campaigns WHERE id=?', [$id]);
    flash('Campaign deleted.');
    redirect('/campaigns');
}
