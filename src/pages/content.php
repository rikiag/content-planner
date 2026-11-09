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
    $m = STATUSES[$c['status']] ?? STATUSES['draft'];
    $html = '<form method="post" action="' . e(url('/content/' . $c['id'] . '/status')) . '" class="inline-block">' . csrf_field()
        . '<input type="hidden" name="back" value="' . e($back) . '">'
        . '<select name="status" @change="$el.form.requestSubmit()" aria-label="Change status" class="cursor-pointer appearance-none rounded-full border-0 py-1.5 pl-3 pr-3 text-[11px] font-bold focus:outline-2 focus:outline-brand ' . $m['chip'] . '">';
    foreach (STATUSES as $k => $s) {
        $html .= '<option value="' . $k . '"' . ($k === $c['status'] ? ' selected' : '') . ' class="bg-surface text-ink">' . e($s['label']) . '</option>';
    }
    return $html . '</select></form>';
}

function content_row(array $c, string $back, bool $live = false): string
{
    $hay = mb_strtolower(implode(' ', [$c['title'], $c['caption'] ?? '', $c['hashtags'] ?? '', $c['platform'], $c['format'], $c['pillar'], $c['campaign_name'] ?? '', STATUSES[$c['status']]['label'] ?? '']));
    $late = is_overdue($c);
    $prog = clamp((int) $c['progress'], 0, 100);
    ob_start(); ?>
    <article class="card flex flex-col gap-3 p-4 sm:flex-row sm:items-center sm:gap-4"<?= $live ? ' data-search="' . e($hay) . '" x-show="match($el)" x-transition.opacity' : '' ?>>
        <div class="flex min-w-0 flex-1 items-start gap-3">
            <span class="grid size-11 shrink-0 place-items-center rounded-2xl text-xs font-extrabold <?= PLATFORMS[$c['platform']]['cls'] ?? 'bg-soft' ?>"><?= e(platform_abbr($c['platform'])) ?></span>
            <div class="min-w-0 flex-1">
                <a href="<?= e(url('/content/' . $c['id'])) ?>" class="line-clamp-2 text-[15px] font-extrabold leading-snug hover:text-brand"><?= e($c['title']) ?></a>
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
        <div class="flex items-center justify-between gap-2 sm:justify-end">
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
    $view = in_array(input('v'), ['board', 'calendar'], true) ? input('v') : 'list';
    $status = input('status');
    $platform = input('platform');
    $camp = (int) input('campaign');
    $search = input('q');
    $back = request_path() . qs();

    $where = ['1=1'];
    $par = [];
    if ($status === 'active') {
        $where[] = "c.status IN ('idea','draft','review','scheduled')";
    } elseif (isset(STATUSES[$status])) {
        $where[] = 'c.status = ?';
        $par[] = $status;
    }
    if (isset(PLATFORMS[$platform])) {
        $where[] = 'c.platform = ?';
        $par[] = $platform;
    }
    if ($camp > 0) {
        $where[] = 'c.campaign_id = ?';
        $par[] = $camp;
    }
    if ($search !== '') {
        $where[] = '(c.title LIKE ? OR c.caption LIKE ? OR c.hashtags LIKE ?)';
        array_push($par, "%$search%", "%$search%", "%$search%");
    }

    $month = preg_match('/^\d{4}-\d{2}$/', input('m')) ? input('m') : date('Y-m');
    if ($view === 'calendar') {
        $where[] = 'substr(c.scheduled_at,1,7) = ?';
        $par[] = $month;
    }
    $order = ($status === 'active' || $view === 'calendar') ? 'ORDER BY (c.scheduled_at IS NULL), c.scheduled_at ASC' : 'ORDER BY (c.scheduled_at IS NULL), c.scheduled_at DESC';
    $rows = q('SELECT c.*, ca.name AS campaign_name FROM contents c LEFT JOIN campaigns ca ON ca.id=c.campaign_id WHERE ' . implode(' AND ', $where) . " $order LIMIT 300", $par);

    $counts = [];
    foreach (q('SELECT status, COUNT(*) n FROM contents GROUP BY status') as $r) {
        $counts[$r['status']] = (int) $r['n'];
    }
    $total = array_sum($counts);

    ob_start(); ?>
    <div x-data="liveFilter(<?= e(json_encode($search, JSON_UNESCAPED_UNICODE)) ?>)">
    <!-- Toolbar -->
    <div class="mb-4 flex flex-col gap-3">
        <div class="flex items-center gap-2">
            <div class="grid flex-1 grid-cols-3 rounded-full bg-surface p-1 shadow-sm sm:flex-none" role="tablist" aria-label="View mode">
                <?php foreach (['list' => ['List', 'list'], 'board' => ['Board', 'board'], 'calendar' => ['Calendar', 'calendar']] as $k => [$lbl, $ic]): ?>
                    <a href="<?= e(url('/content' . qs(['v' => $k === 'list' ? null : $k, 'm' => null]))) ?>" role="tab" aria-selected="<?= $view === $k ? 'true' : 'false' ?>" class="inline-flex items-center justify-center gap-1.5 rounded-full px-3 py-2 text-xs font-bold transition sm:px-4 <?= $view === $k ? 'bg-hero text-white' : 'text-muted hover:text-ink' ?>"><?= icon($ic, 'size-4') ?><?= $lbl ?></a>
                <?php endforeach; ?>
            </div>
            <a href="<?= e(url('/content/new')) ?>" class="btn-primary ml-auto hidden sm:inline-flex"><?= icon('plus', 'size-4') ?>New content</a>
        </div>

        <form action="<?= e(url('/content')) ?>" method="get" class="grid grid-cols-2 gap-2 sm:flex sm:flex-wrap sm:items-center">
            <input type="hidden" name="v" value="<?= e($view === 'list' ? '' : $view) ?>">
            <?php if ($status): ?><input type="hidden" name="status" value="<?= e($status) ?>"><?php endif; ?>
            <?php if ($view === 'calendar'): ?><input type="hidden" name="m" value="<?= e($month) ?>"><?php endif; ?>
            <label class="relative col-span-2 block sm:min-w-0 sm:flex-1 sm:basis-52">
                <span class="sr-only">Search content</span>
                <span class="pointer-events-none absolute left-3.5 top-1/2 -translate-y-1/2 text-muted"><?= icon('search', 'size-4') ?></span>
                <input type="search" name="q" x-model="q" placeholder="Search title / caption…" class="input !pl-10" enterkeyhint="search">
            </label>
            <select name="platform" class="input sm:!w-auto" @change="$el.form.requestSubmit()" aria-label="Platform">
                <option value="">All platforms</option>
                <?php foreach (PLATFORMS as $p => $_): ?><option value="<?= e($p) ?>" <?= $platform === $p ? 'selected' : '' ?>><?= e($p) ?></option><?php endforeach; ?>
            </select>
            <select name="campaign" class="input sm:!w-auto sm:max-w-52" @change="$el.form.requestSubmit()" aria-label="Campaign">
                <option value="">All campaigns</option>
                <?php foreach (campaign_options(false) as $id => $n): ?><option value="<?= $id ?>" <?= $camp === (int) $id ? 'selected' : '' ?>><?= e($n) ?></option><?php endforeach; ?>
            </select>
        </form>

        <div class="-mx-4 flex gap-2 overflow-x-auto px-4 pb-1 no-scrollbar sm:mx-0 sm:flex-wrap sm:px-0">
            <a class="pill" href="<?= e(url('/content' . qs(['status' => null]))) ?>" <?= $status === '' ? 'aria-current="true"' : '' ?>>All <b><?= $total ?></b></a>
            <a class="pill" href="<?= e(url('/content' . qs(['status' => 'active']))) ?>" <?= $status === 'active' ? 'aria-current="true"' : '' ?>>In progress <b><?= $total - ($counts['published'] ?? 0) ?></b></a>
            <?php foreach (STATUSES as $k => $m): ?>
                <a class="pill" href="<?= e(url('/content' . qs(['status' => $k]))) ?>" <?= $status === $k ? 'aria-current="true"' : '' ?>><span class="size-2 rounded-full <?= $m['dot'] ?>"></span><?= e($m['label']) ?> <b><?= $counts[$k] ?? 0 ?></b></a>
            <?php endforeach; ?>
        </div>
    </div>

    <?php if ($view === 'list'): ?>
        <?php if (!$rows): ?>
            <?= empty_state('calendar', 'No content yet', $search || $status || $platform || $camp ? 'No content matches this filter.' : 'Start building your content calendar from a simple idea.', '<a href="' . e(url('/content/new')) . '" class="btn-primary mt-2">' . icon('plus', 'size-4') . 'New content</a>') ?>
        <?php else: ?>
            <div class="flex flex-col gap-3"><?php foreach ($rows as $c): ?><?= content_row($c, $back, true) ?><?php endforeach; ?></div>
            <p x-show="visible() === 0" x-cloak class="card px-6 py-10 text-center text-sm text-muted">No content matches “<b x-text="q"></b>”.</p>
        <?php endif; ?>

    <?php elseif ($view === 'board'): ?>
        <?php
        $cols = [];
        foreach ($rows as $c) {
            $cols[$c['status']][] = $c;
        } ?>
        <div class="-mx-4 flex snap-x snap-mandatory scroll-px-4 gap-3 overflow-x-auto px-4 pb-3 no-scrollbar lg:mx-0 lg:grid lg:grid-cols-5 lg:overflow-visible lg:px-0">
            <?php foreach (STATUSES as $k => $m): ?>
                <section class="w-[78vw] max-w-80 shrink-0 snap-center rounded-[1.5rem] p-3 sm:w-72 lg:w-auto lg:max-w-none <?= $m['tint'] ?>" aria-label="<?= e($m['label']) ?>">
                    <div class="mb-3 flex items-center gap-2 px-1"><span class="size-2.5 rounded-full <?= $m['dot'] ?>"></span><h3 class="text-sm font-extrabold"><?= e($m['label']) ?></h3><span class="ml-auto rounded-full bg-surface px-2 py-0.5 text-[11px] font-bold"><?= count($cols[$k] ?? []) ?></span></div>
                    <div class="flex flex-col gap-2.5">
                        <?php foreach ($cols[$k] ?? [] as $c): $late = is_overdue($c); ?>
                            <article class="rounded-2xl bg-surface p-3 shadow-sm">
                                <div class="flex items-start gap-2"><?= platform_chip($c['platform']) ?><a href="<?= e(url('/content/' . $c['id'])) ?>" class="line-clamp-3 text-[13px] font-bold leading-snug hover:text-brand"><?= e($c['title']) ?></a></div>
                                <p class="mt-1.5 text-[11px] <?= $late ? 'font-bold text-rose-600 dark:text-rose-400' : 'text-muted' ?>"><?= e($c['format']) ?> · <?= e(fmt_dt($c['scheduled_at'])) ?></p>
                                <div class="mt-2 flex items-center justify-between gap-2"><?= $c['status'] === 'published' ? '<span class="text-[11px] font-bold text-muted">' . e(fmt_k($c['views'])) . ' views</span>' : '<div class="bar-track w-16"><div class="h-full rounded-full bg-accent" style="width:' . clamp((int) $c['progress'], 0, 100) . '%"></div></div>' ?><?= status_select($c, $back) ?></div>
                            </article>
                        <?php endforeach; ?>
                        <?php if (empty($cols[$k])): ?><p class="rounded-2xl border border-dashed border-line px-3 py-6 text-center text-xs text-muted">Empty</p><?php endif; ?>
                    </div>
                </section>
            <?php endforeach; ?>
        </div>

    <?php else:
        $first = strtotime($month . '-01');
        $prev = date('Y-m', strtotime('-1 month', $first));
        $next = date('Y-m', strtotime('+1 month', $first));
        $lead = (int) date('N', $first) - 1;
        $dim = (int) date('t', $first);
        $by = [];
        foreach ($rows as $c) {
            $by[(int) substr((string) $c['scheduled_at'], 8, 2)][] = $c;
        }
        $todayStr = date('Y-m-d'); ?>
        <div class="card p-3 sm:p-5">
            <div class="mb-3 flex items-center justify-between">
                <a href="<?= e(url('/content' . qs(['m' => $prev]))) ?>" class="grid size-10 place-items-center rounded-xl hover:bg-soft" aria-label="Previous month"><?= icon('chevron-left') ?></a>
                <h2 class="text-base font-extrabold"><?= MONTHS[(int) date('n', $first) - 1] ?> <?= date('Y', $first) ?></h2>
                <a href="<?= e(url('/content' . qs(['m' => $next]))) ?>" class="grid size-10 place-items-center rounded-xl hover:bg-soft" aria-label="Next month"><?= icon('chevron-right') ?></a>
            </div>
            <div class="grid grid-cols-7 gap-px overflow-hidden rounded-2xl bg-line text-center">
                <?php foreach (['Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat', 'Sun'] as $d): ?><div class="bg-soft py-2 text-[10px] font-bold uppercase tracking-wide text-muted sm:text-xs"><?= $d ?></div><?php endforeach; ?>
                <?php for ($i = 0; $i < $lead; $i++): ?><div class="min-h-14 bg-surface/60 sm:min-h-24"></div><?php endfor; ?>
                <?php for ($d = 1; $d <= $dim; $d++):
                    $ds = $month . '-' . str_pad((string) $d, 2, '0', STR_PAD_LEFT);
                    $items = $by[$d] ?? []; ?>
                    <div class="min-h-14 bg-surface p-1 text-left sm:min-h-24 sm:p-1.5">
                        <a href="<?= e(url('/content/new?date=' . $ds)) ?>" class="mb-0.5 grid size-6 place-items-center rounded-full text-[11px] font-bold hover:bg-soft <?= $ds === $todayStr ? '!bg-accent text-white' : 'text-muted' ?>" aria-label="Add content on day <?= $d ?>"><?= $d ?></a>
                        <div class="hidden flex-col gap-0.5 sm:flex">
                            <?php foreach (array_slice($items, 0, 3) as $c): ?>
                                <a href="<?= e(url('/content/' . $c['id'])) ?>" class="truncate rounded-md px-1.5 py-0.5 text-[10px] font-bold <?= STATUSES[$c['status']]['chip'] ?>" title="<?= e($c['title']) ?>"><?= e(date('H:i', (int) ts($c['scheduled_at']))) ?> <?= e($c['title']) ?></a>
                            <?php endforeach; ?>
                            <?php if (count($items) > 3): ?><span class="px-1 text-[10px] font-bold text-muted">+<?= count($items) - 3 ?> more</span><?php endif; ?>
                        </div>
                        <div class="flex flex-wrap gap-0.5 sm:hidden"><?php foreach ($items as $c): ?><span class="size-1.5 rounded-full <?= STATUSES[$c['status']]['dot'] ?>"></span><?php endforeach; ?></div>
                    </div>
                <?php endfor; ?>
            </div>
        </div>
        <div class="mt-4 flex flex-col gap-3 sm:hidden">
            <?php foreach ($rows as $c): ?><?= content_row($c, $back) ?><?php endforeach; ?>
        </div>
    <?php endif; ?>
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
    $c ??= [
        'title' => '', 'platform' => 'Instagram', 'format' => 'Feed', 'pillar' => '', 'status' => 'draft',
        'campaign_id' => (int) input('campaign') ?: '', 'scheduled_at' => preg_match('/^\d{4}-\d{2}-\d{2}$/', input('date')) ? input('date') . ' 09:00' : date('Y-m-d H:00', strtotime('+1 day')),
        'progress' => 0, 'caption' => '', 'hashtags' => '', 'post_url' => '', 'views' => 0, 'likes' => 0, 'comments' => 0, 'shares' => 0, 'saves' => 0,
    ];
    $opt = fn(array $a) => array_combine($a, $a);

    $f = field('title', 'Title / Topic', 'text', $c['title'], ['req' => true, 'max' => 160, 'ph' => 'e.g. 5-Minute Daily Mobility Checklist'])
        . '<div class="grid grid-cols-1 gap-4 sm:grid-cols-2">'
        . field('platform', 'Platform', 'select', $c['platform'], ['options' => $opt(array_keys(PLATFORMS))])
        . field('format', 'Format', 'select', $c['format'], ['options' => $opt(FORMATS)])
        . field('pillar', 'Content Pillar', 'select', $c['pillar'], ['options' => ['' => '— Select —'] + $opt(PILLARS)])
        . field('campaign_id', 'Campaign', 'select', $c['campaign_id'], ['options' => campaign_options()])
        . field('status', 'Status', 'select', $c['status'], ['options' => array_map(fn($s) => $s['label'], STATUSES)])
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

    $form = form_shell($id ? '/content/' . $id : '/content/new', $f, '/content');
    $del = $id ? '<div class="mx-auto mt-3 max-w-3xl text-right">' . delete_form('/content/' . $id . '/delete', 'Delete this content item?', 'Delete content', 'btn-ghost !text-rose-600') . '</div>' : '';

    page($id ? 'Edit Content' : 'New Content', 'content', $form . $del, ['subtitle' => $id ? e($c['title']) : 'Plan your next post']);
}

function action_content_save(int $id = 0): void
{
    $title = mb_substr(input('title'), 0, 160);
    if ($title === '') {
        flash('Title is required.', 'err');
        redirect($id ? "/content/$id" : '/content/new');
    }
    $platform = isset(PLATFORMS[input('platform')]) ? input('platform') : 'Instagram';
    $format = in_array(input('format'), FORMATS, true) ? input('format') : 'Feed';
    $pillar = in_array(input('pillar'), PILLARS, true) ? input('pillar') : '';
    $status = isset(STATUSES[input('status')]) ? input('status') : 'draft';
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
    $c = q1('SELECT * FROM contents WHERE id=?', [$id]);
    if (!$c || !isset(STATUSES[$s])) {
        not_found();
    }
    $pub = $s === 'published' ? ($c['published_at'] ?: now_str()) : null;
    $prog = $s === 'published' ? 100 : $c['progress'];
    exec_sql("UPDATE contents SET status=?, published_at=?, progress=?, updated_at=datetime('now','localtime') WHERE id=?", [$s, $pub, $prog, $id]);
    flash('Status: ' . STATUSES[$s]['label'] . '.');
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
    flash('Content deleted.');
    $back = safe_back('/content');
    redirect(str_contains($back, '/content/' . $id) ? '/content' : $back);
}
