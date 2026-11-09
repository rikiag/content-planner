<?php
declare(strict_types=1);

defined('ROOT') || (http_response_code(403) && exit);

/** Single item card in the "Continue Working" section */
function continue_card(array $c): string
{
    $late = is_overdue($c);
    $grad = match ($c['format']) {
        'Reels', 'Video' => 'from-rose-500 to-orange-500',
        'Carousel'       => 'from-sky-500 to-blue-600',
        'Feed'           => 'from-emerald-500 to-teal-600',
        default          => 'from-violet-500 to-indigo-600',
    };
    $prog = clamp((int) $c['progress'], 0, 100);
    ob_start(); ?>
    <a href="<?= e(url('/content/' . $c['id'])) ?>" class="group flex items-center gap-3 rounded-[1.4rem] bg-white/70 p-2.5 pr-3 shadow-sm transition hover:shadow-md dark:bg-white/5 sm:gap-4">
        <span class="grid size-[4.5rem] shrink-0 place-items-center rounded-2xl bg-linear-to-br <?= $grad ?> text-center text-white shadow-md sm:size-20">
            <span class="grid place-items-center gap-0.5"><?= icon('file', 'size-6') ?><span class="text-[10px] font-bold leading-none"><?= e($c['format']) ?> · <?= e(platform_abbr($c['platform'])) ?></span></span>
        </span>
        <span class="min-w-0 flex-1">
            <span class="block truncate text-[15px] font-extrabold sm:text-base"><?= e($c['title']) ?></span>
            <span class="mt-0.5 block truncate text-[11px] text-muted sm:text-xs">
                <?= e($c['campaign_name'] ?: $c['pillar'] ?: 'No campaign') ?> • <?= e(fmt_dt($c['scheduled_at'])) ?>
                <?php if ($late): ?> • <b class="text-rose-600 dark:text-rose-400">Overdue</b><?php endif; ?>
            </span>
            <span class="mt-2 flex items-center gap-2.5">
                <span class="bar-track flex-1 !bg-white/80 dark:!bg-white/10"><span class="block h-full rounded-full bg-accent" style="width:<?= $prog ?>%"></span></span>
                <span class="text-[11px] font-bold text-muted"><?= $prog ?>%</span>
            </span>
        </span>
        <span class="hidden items-center gap-1.5 rounded-full bg-accent px-5 py-2.5 text-sm font-bold text-white shadow-md transition group-hover:brightness-110 sm:inline-flex"><?= icon('play', 'size-3.5') ?>Open</span>
        <span class="grid size-10 shrink-0 place-items-center rounded-full bg-accent text-white shadow-md sm:hidden"><?= icon('play', 'size-3.5') ?></span>
    </a>
    <?php return (string) ob_get_clean();
}

function page_home(): void
{
    if (!current_user()) {
        page_landing();
        return;
    }
    $month = date('Y-m');
    $pd = pub_date_sql();

    // ---- Monthly progress ----
    $monthTotal = (int) qv("SELECT COUNT(*) FROM contents WHERE substr(COALESCE(scheduled_at, created_at),1,7) = ?", [$month]);
    $monthDone = (int) qv("SELECT COUNT(*) FROM contents WHERE status='published' AND substr($pd,1,7) = ?", [$month]);
    $monthPct = $monthTotal > 0 ? (int) round($monthDone / $monthTotal * 100) : 0;
    $monthName = MONTHS[(int) date('n') - 1];

    // ---- Weekly target ----
    $target = max(1, (int) setting('weekly_target', '4'));
    [$ws, $we] = week_range();
    $weekDone = (int) qv("SELECT COUNT(*) FROM contents WHERE status='published' AND substr($pd,1,10) BETWEEN ? AND ?", [$ws, $we]);
    $remaining = max(0, $target - $weekDone);
    $days = array_flip(published_days());
    $streak = streak_days();

    // ---- Quick navigation tiles ----
    $overdue = overdue_count();
    $tiles = [
        ['Content',      '/content',   'calendar', 'text-blue-600',    $overdue],
        ['Campaigns',    '/campaigns', 'flag',     'text-orange-600',  0],
        ['Idea Bank',    '/ideas',     'bulb',     'text-amber-500',   new_ideas_count()],
        ['Resources',    '/resources', 'folder',   'text-emerald-600', 0],
        ['Analytics',    '/analytics', 'chart',    'text-violet-600',  0],
        ['Account',      '/account',   'users',    'text-rose-600',    0],
        ['Export Sheet', '/export',    'sheet',    'text-green-600',   0],
        ['Notes',        '/notes',     'note',     'text-yellow-600',  0],
    ];

    // ---- Continue working ----
    $cont = q("SELECT c.*, ca.name AS campaign_name FROM contents c LEFT JOIN campaigns ca ON ca.id=c.campaign_id
               WHERE c.status IN ('draft','review','idea') ORDER BY (c.scheduled_at IS NULL), c.scheduled_at ASC LIMIT 3");
    $firstOpen = $cont[0]['id'] ?? null;

    // ---- Pipeline ----
    $pipe = [];
    foreach (array_keys(STATUSES) as $s) {
        $pipe[$s] = [
            'count' => (int) qv('SELECT COUNT(*) FROM contents WHERE status=?', [$s]),
            'items' => q('SELECT id,title FROM contents WHERE status=? ORDER BY (scheduled_at IS NULL), scheduled_at DESC LIMIT 2', [$s]),
        ];
    }

    // ---- Top content this month ----
    $top = q("SELECT * FROM contents WHERE status='published' AND substr($pd,1,7)=? ORDER BY views DESC LIMIT 5", [$month]);

    ob_start(); ?>
<div class="grid grid-cols-1 gap-5 xl:grid-cols-[minmax(0,1fr)_19.5rem]">

    <!-- ================= Left column ================= -->
    <div class="flex min-w-0 flex-col gap-6">

        <!-- Hero progress -->
        <section class="relative overflow-hidden rounded-[1.75rem] bg-linear-to-br from-hero to-hero2 p-5 text-white shadow-card sm:p-7" aria-labelledby="hero-t">
            <div class="pointer-events-none absolute -right-10 -top-16 size-56 rounded-full bg-blue-400/10 blur-2xl"></div>
            <div class="relative flex items-center gap-4 sm:gap-6">
                <?= ring($monthPct, 92, 11, '<span class="text-xl font-extrabold">' . $monthPct . '%</span>', 'text-emerald-400', 'text-white/15') ?>
                <div class="min-w-0 flex-1">
                    <p class="text-[11px] font-semibold text-white/70"><?= e(setting('studio', 'Move Studio')) ?></p>
                    <h2 id="hero-t" class="text-xl font-extrabold leading-tight tracking-tight sm:text-2xl"><?= e($monthName) ?> Content Progress</h2>
                    <p class="mt-1 text-base font-bold sm:text-lg"><span class="text-xl font-extrabold text-emerald-400 sm:text-2xl"><?= $monthDone ?></span> of <?= $monthTotal ?> posts <span class="text-amber-300">published</span></p>
                    <div class="bar-track mt-2.5 max-w-sm !bg-white/15"><div class="h-full rounded-full bg-emerald-400" style="width:<?= $monthPct ?>%"></div></div>
                    <p class="mt-2 text-[11px] text-white/80 sm:text-xs">
                        <?= $remaining > 0
                            ? $remaining . ' more post' . ($remaining === 1 ? '' : 's') . ' to reach this week’s goal!'
                            : 'Weekly goal achieved. Great momentum!' ?>
                    </p>
                </div>
            </div>
            <div class="relative mt-5 flex justify-end sm:mt-1">
                <a href="<?= e(url($firstOpen ? '/content/' . $firstOpen : '/content/new')) ?>" class="inline-flex w-full items-center justify-center gap-3 rounded-2xl border-2 border-blue-400 bg-blue-500/20 py-2.5 pl-5 pr-2.5 text-sm font-bold shadow-lg backdrop-blur transition hover:bg-blue-500/35 active:scale-[.98] sm:w-auto">
                    Continue Working <span class="grid size-8 place-items-center rounded-lg bg-slate-900/80"><?= icon('arrow-right', 'size-4') ?></span>
                </a>
            </div>
        </section>

        <!-- Main menu -->
        <section aria-labelledby="menu-t">
            <h2 id="menu-t" class="section-title mb-3">Quick Access</h2>
            <div class="grid grid-cols-4 gap-2.5 sm:gap-4">
                <?php foreach ($tiles as $i => [$label, $href, $ic, $col, $badge]): ?>
                    <a href="<?= e(url($href)) ?>" class="group flex flex-col items-center justify-center gap-2 rounded-[1.4rem] px-1 py-4 text-center transition active:scale-95 sm:gap-2.5 sm:py-6 <?= $i === 0 ? 'bg-hero text-white shadow-lg' : 'bg-soft text-ink hover:bg-brandsoft' ?>">
                        <span class="relative grid size-11 place-items-center rounded-2xl bg-white shadow-md transition group-hover:-translate-y-0.5 sm:size-12 dark:bg-slate-900/70 <?= $col ?>">
                            <?= icon($ic, 'size-5.5') ?>
                            <?php if ($badge > 0): ?><span class="absolute -right-1.5 -top-1.5 grid size-5 place-items-center rounded-full bg-rose-500 text-[10px] font-bold text-white ring-2 ring-white dark:ring-slate-900"><?= (int) $badge ?></span><?php endif; ?>
                        </span>
                        <span class="text-[11px] font-bold leading-tight sm:text-xs"><?= e($label) ?></span>
                    </a>
                <?php endforeach; ?>
            </div>
        </section>

        <!-- Continue working -->
        <section aria-labelledby="cont-t">
            <div class="mb-3 flex items-center justify-between">
                <h2 id="cont-t" class="section-title">Continue Working</h2>
                <a href="<?= e(url('/content?status=active')) ?>" class="text-xs font-bold text-accent hover:underline">View All</a>
            </div>
            <?php if ($cont): ?>
                <div class="flex flex-col gap-2.5 rounded-[1.75rem] bg-peach p-3 sm:p-4">
                    <?php foreach ($cont as $c): ?><?= continue_card($c) ?><?php endforeach; ?>
                </div>
            <?php else: ?>
                <?= empty_state('check', 'All caught up', 'No drafts waiting right now. Time to plan your next post.', '<a href="' . e(url('/content/new')) . '" class="btn-primary mt-2">' . icon('plus', 'size-4') . 'New content</a>') ?>
            <?php endif; ?>
        </section>

        <!-- Pipeline -->
        <section aria-labelledby="pipe-t">
            <div class="mb-3 flex items-center justify-between">
                <h2 id="pipe-t" class="section-title">Content Pipeline</h2>
                <a href="<?= e(url('/content?v=board')) ?>" class="text-xs font-bold text-brand hover:underline">Open board</a>
            </div>
            <div class="-mx-4 flex snap-x scroll-px-4 gap-3 overflow-x-auto px-4 pb-2 no-scrollbar sm:mx-0 sm:grid sm:grid-cols-5 sm:overflow-visible sm:px-0">
                <?php foreach (STATUSES as $key => $m): ?>
                    <div class="w-[44vw] max-w-52 shrink-0 snap-start rounded-[1.4rem] p-3.5 sm:w-auto sm:max-w-none <?= $m['tint'] ?>">
                        <div class="flex items-center justify-between">
                            <span class="grid size-9 place-items-center rounded-xl text-white <?= $m['dot'] ?>"><?= icon($m['icon'], 'size-4.5') ?></span>
                            <span class="text-2xl font-extrabold"><?= $pipe[$key]['count'] ?></span>
                        </div>
                        <a href="<?= e(url('/content?status=' . $key)) ?>" class="mt-2.5 block text-sm font-extrabold hover:underline"><?= e($m['label']) ?></a>
                        <ul class="mt-1.5 space-y-1 text-[11px] text-muted">
                            <?php foreach ($pipe[$key]['items'] as $it): ?>
                                <li class="truncate"><a href="<?= e(url('/content/' . $it['id'])) ?>" class="hover:text-ink">• <?= e($it['title']) ?></a></li>
                            <?php endforeach; ?>
                            <?php if (!$pipe[$key]['items']): ?><li class="opacity-70">Empty</li><?php endif; ?>
                        </ul>
                    </div>
                <?php endforeach; ?>
            </div>
        </section>
    </div>

    <!-- ================= Right column ================= -->
    <div class="flex min-w-0 flex-col gap-5">
        <section class="card p-5" aria-labelledby="wk-t">
            <h2 id="wk-t" class="section-title">Weekly Goal</h2>
            <div class="mt-3 flex items-center gap-4">
                <?= ring($weekDone / $target * 100, 92, 11, '<span class="leading-none"><span class="block text-xl font-extrabold"><span class="text-accent">' . $weekDone . '</span>/' . $target . '</span><span class="block text-[10px] text-muted">posts</span></span>', 'text-blue-500', 'text-track') ?>
                <div class="min-w-0 text-xs leading-relaxed text-muted">
                    <b class="block text-sm text-ink"><?= $remaining > 0 ? 'Keep going!' : 'Goal reached!' ?></b>
                    <span class="inline-flex items-center gap-1"><span class="text-orange-500"><?= icon('flame', 'size-3.5') ?></span><?= $streak ?>-day posting streak.</span>
                    <?= $remaining > 0 ? $remaining . ' left to go.' : '' ?>
                </div>
            </div>
            <div class="mt-4 grid grid-cols-7 gap-1 text-center" aria-label="Posts this week">
                <?php for ($i = 0; $i < 7; $i++):
                    $d = date('Y-m-d', strtotime($ws . " +$i days"));
                    $done = isset($days[$d]);
                    $isToday = $d === date('Y-m-d'); ?>
                    <div class="flex flex-col items-center gap-1.5">
                        <span class="grid size-6 place-items-center rounded-full border-2 text-white <?= $done ? 'border-accent bg-accent' : ($isToday ? 'border-accent' : 'border-line bg-soft/50') ?>"><?php if ($done): ?><?= icon('check', 'size-3.5') ?><?php endif; ?></span>
                        <span class="text-[10px] font-semibold <?= $isToday ? 'text-accent' : 'text-muted' ?>"><?= DAYS_SHORT[(int) date('w', strtotime($d))] ?></span>
                    </div>
                <?php endfor; ?>
            </div>
        </section>

        <section class="card p-5" aria-labelledby="best-t">
            <div class="flex items-center justify-between">
                <h2 id="best-t" class="section-title">Top Content</h2>
                <span class="chip bg-soft text-muted">This month</span>
            </div>
            <?php if ($top): ?>
                <ol class="mt-3 flex flex-col gap-1">
                    <?php foreach ($top as $i => $c): ?>
                        <li><a href="<?= e(url('/content/' . $c['id'])) ?>" class="flex items-center gap-2.5 rounded-2xl p-1.5 transition hover:bg-soft">
                            <span class="grid size-6 shrink-0 place-items-center rounded-full text-[11px] font-extrabold <?= match ($i) { 0 => 'bg-amber-400 text-amber-950', 1 => 'bg-slate-300 text-slate-700', 2 => 'bg-orange-400 text-orange-950', default => 'text-muted' } ?>"><?= $i + 1 ?></span>
                            <span class="grid size-8 shrink-0 place-items-center rounded-lg text-[10px] font-extrabold <?= PLATFORMS[$c['platform']]['cls'] ?? 'bg-soft' ?>"><?= e(platform_abbr($c['platform'])) ?></span>
                            <span class="min-w-0 flex-1 leading-tight"><b class="block truncate text-xs"><?= e($c['title']) ?></b><span class="text-[10px] text-muted"><?= e($c['format']) ?> · ER <?= e(fmt_pct(er($c))) ?></span></span>
                            <b class="text-xs"><?= e(fmt_k($c['views'])) ?></b>
                        </a></li>
                    <?php endforeach; ?>
                </ol>
            <?php else: ?>
                <p class="mt-3 text-xs text-muted">No published posts this month yet. Mark a post as “Published” and enter its metrics to see rankings.</p>
            <?php endif; ?>
        </section>
    </div>
</div>
<?php
    page('Dashboard', 'home', (string) ob_get_clean());
}
