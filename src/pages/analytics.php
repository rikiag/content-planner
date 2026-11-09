<?php
declare(strict_types=1);

defined('ROOT') || (http_response_code(403) && exit);

/** Aggregate KPI metrics for the date range [from, to] (inclusive, Y-m-d) */
function kpis(string $from, string $to): array
{
    $pd = pub_date_sql();
    $r = q1("SELECT COUNT(*) n, COALESCE(SUM(views),0) v, COALESCE(SUM(likes),0) l, COALESCE(SUM(comments),0) c, COALESCE(SUM(shares),0) s, COALESCE(SUM(saves),0) sv
             FROM contents WHERE status='published' AND substr($pd,1,10) BETWEEN ? AND ?", [$from, $to]);
    $inter = (int) $r['l'] + (int) $r['c'] + (int) $r['s'] + (int) $r['sv'];
    return [
        'n' => (int) $r['n'],
        'views' => (int) $r['v'],
        'inter' => $inter,
        'er' => $r['v'] > 0 ? $inter / $r['v'] * 100 : 0.0,
        'likes' => (int) $r['l'],
        'comments' => (int) $r['c'],
        'shares' => (int) $r['s'],
        'saves' => (int) $r['sv'],
    ];
}

function delta_chip(float $now, float $prev, bool $pp = false): string
{
    if ($prev <= 0 && $now <= 0) {
        return '<span class="text-[11px] font-semibold text-muted">—</span>';
    }
    if ($pp) {
        $d = $now - $prev;
        $txt = ($d >= 0 ? '+' : '') . number_format($d, 1, '.', ',') . ' pp';
    } else {
        if ($prev <= 0) {
            return '<span class="text-[11px] font-bold text-emerald-600 dark:text-emerald-400">new</span>';
        }
        $d = ($now - $prev) / $prev * 100;
        $txt = ($d >= 0 ? '+' : '') . number_format($d, 0, '.', ',') . '%';
    }
    $up = $d >= 0;
    return '<span class="text-[11px] font-bold ' . ($up ? 'text-emerald-600 dark:text-emerald-400' : 'text-rose-600 dark:text-rose-400') . '">' . ($up ? '▲' : '▼') . ' ' . $txt . '</span>';
}

/** Horizontal bar list: $rows = [[label, value, rightText, subText?]] */
function bar_list(array $rows, string $color = 'bg-brand'): string
{
    $max = max(1, ...array_map(fn($r) => $r[1], $rows ?: [[0, 0]]));
    $h = '<ul class="flex flex-col gap-3">';
    foreach ($rows as [$label, $val, $right, $small]) {
        $h .= '<li><div class="mb-1 flex items-baseline justify-between gap-2 text-xs"><span class="min-w-0 truncate font-bold">' . e($label) . '</span><span class="shrink-0 font-semibold text-muted">' . e($right) . ($small ? ' · <span class="text-emerald-600 dark:text-emerald-400">' . e($small) . '</span>' : '') . '</span></div>'
            . '<div class="bar-track"><div class="h-full rounded-full ' . $color . '" style="width:' . max(2, round($val / $max * 100)) . '%"></div></div></li>';
    }
    return $h . '</ul>';
}

function group_stats(string $col, string $from): array
{
    $pd = pub_date_sql();
    $rows = q("SELECT $col AS k, COUNT(*) n, SUM(views) v, SUM(likes+comments+shares+saves) i FROM contents WHERE status='published' AND $col != '' AND substr($pd,1,10) >= ? GROUP BY $col ORDER BY v DESC", [$from]);
    return array_map(fn($r) => [$r['k'], (int) $r['v'], fmt_k((int) $r['v']) . ' · ' . $r['n'] . ' posts', $r['v'] > 0 ? 'ER ' . fmt_pct($r['i'] / $r['v'] * 100) : ''], $rows);
}

function page_analytics(): void
{
    $range = input('r', '30');
    $days = ['7' => 7, '30' => 30, '90' => 90, 'all' => 3650][$range] ?? 30;
    $range = in_array($range, ['7', '30', '90', 'all'], true) ? $range : '30';
    $to = date('Y-m-d');
    $from = date('Y-m-d', strtotime("-" . ($days - 1) . " days"));
    $pFrom = date('Y-m-d', strtotime("-" . (2 * $days - 1) . " days"));
    $pTo = date('Y-m-d', strtotime("-$days days"));

    $now = kpis($from, $to);
    $prev = kpis($pFrom, $pTo);
    $pd = pub_date_sql();

    // Weekly trend (last 8 weeks)
    $weeks = [];
    for ($i = 7; $i >= 0; $i--) {
        [$ws, $we] = week_range(strtotime("-$i weeks"));
        $k = kpis($ws, $we);
        $weeks[] = ['label' => (int) date('j', strtotime($ws)) . ' ' . MONTHS_SHORT[(int) date('n', strtotime($ws)) - 1], 'v' => $k['views'], 'n' => $k['n']];
    }
    $wmax = max(1, ...array_column($weeks, 'v'));

    // Best publishing days (average views per day of week)
    $dow = q("SELECT CAST(strftime('%w', $pd) AS INTEGER) d, AVG(views) a, COUNT(*) n FROM contents WHERE status='published' AND substr($pd,1,10) >= ? GROUP BY d", [$from]);
    $dowMap = [];
    foreach ($dow as $r) {
        $dowMap[(int) $r['d']] = (float) $r['a'];
    }
    $best = [];
    foreach ([1, 2, 3, 4, 5, 6, 0] as $d) {
        $best[] = [DAYS_FULL[$d], (int) ($dowMap[$d] ?? 0), $dowMap ? fmt_k((int) ($dowMap[$d] ?? 0)) . ' avg' : '', ''];
    }

    $top = q("SELECT * FROM contents WHERE status='published' AND substr($pd,1,10) >= ? ORDER BY views DESC LIMIT 8", [$from]);
    $lbl = ['7' => 'Last 7 days', '30' => 'Last 30 days', '90' => 'Last 90 days', 'all' => 'All time'][$range];

    $kpiCards = [
        ['Views', fmt_k($now['views']), delta_chip($now['views'], $prev['views']), 'eye', 'bg-sky-100 text-sky-600 dark:bg-sky-500/20'],
        ['Interactions', fmt_k($now['inter']), delta_chip($now['inter'], $prev['inter']), 'heart', 'bg-rose-100 text-rose-600 dark:bg-rose-500/20'],
        ['Engagement rate', fmt_pct($now['er']), delta_chip($now['er'], $prev['er'], true), 'target', 'bg-violet-100 text-violet-600 dark:bg-violet-500/20'],
        ['Published posts', (string) $now['n'], delta_chip($now['n'], $prev['n']), 'calendar', 'bg-emerald-100 text-emerald-600 dark:bg-emerald-500/20'],
    ];

    ob_start(); ?>
    <div class="mb-4 flex items-center gap-2">
        <div class="grid min-w-0 flex-1 grid-cols-4 rounded-full bg-surface p-1 shadow-sm sm:flex sm:flex-none" role="tablist" aria-label="Date range">
            <?php foreach (['7' => '7 days', '30' => '30 days', '90' => '90 days', 'all' => 'All'] as $k => $l): ?>
                <a href="<?= e(url('/analytics?r=' . $k)) ?>" role="tab" aria-selected="<?= $range === (string) $k ? 'true' : 'false' ?>" class="rounded-full px-3.5 py-2 text-center text-xs font-bold transition <?= $range === (string) $k ? 'bg-hero text-white' : 'text-muted hover:text-ink' ?>"><?= $l ?></a>
            <?php endforeach; ?>
        </div>
        <a href="<?= e(url('/export/content.csv')) ?>" class="btn-ghost shrink-0 !px-3.5 !py-2 !text-xs" aria-label="Download CSV"><?= icon('download', 'size-4') ?><span class="hidden sm:inline">Download CSV</span></a>
    </div>

    <div class="grid grid-cols-2 gap-3 lg:grid-cols-4">
        <?php foreach ($kpiCards as [$l, $v, $d, $ic, $cls]): ?>
            <div class="card p-4">
                <span class="grid size-9 place-items-center rounded-xl <?= $cls ?>"><?= icon($ic, 'size-4.5') ?></span>
                <div class="mt-3 text-[11px] font-semibold text-muted"><?= e($l) ?></div>
                <div class="text-2xl font-extrabold tracking-tight"><?= e($v) ?></div>
                <div class="mt-0.5"><?= $d ?> <span class="text-[10px] text-muted">vs previous period</span></div>
            </div>
        <?php endforeach; ?>
    </div>
    <p class="mt-2 px-1 text-[11px] text-muted">Period: <?= e($lbl) ?>. Interactions = likes + comments + shares + saves.</p>

    <div class="mt-5 grid grid-cols-1 gap-5 lg:grid-cols-2">
        <section class="card p-5 lg:col-span-2" aria-labelledby="trend-t">
            <h2 id="trend-t" class="section-title">Weekly views</h2>
            <p class="text-xs text-muted">Last 8 weeks</p>
            <div class="mt-4 flex h-44 items-end gap-2 sm:gap-4" role="img" aria-label="Weekly views bar chart">
                <?php foreach ($weeks as $i => $w): $h = $w['v'] > 0 ? max(4, round($w['v'] / $wmax * 100)) : 2; ?>
                    <div class="flex h-full min-w-0 flex-1 flex-col items-center justify-end gap-1.5">
                        <span class="text-[10px] font-bold text-muted"><?= $w['v'] > 0 ? e(fmt_k($w['v'])) : '' ?></span>
                        <div class="w-full max-w-12 rounded-t-xl <?= $i === 7 ? 'bg-accent' : 'bg-brand/70' ?>" style="height:<?= $h ?>%" title="<?= e($w['label']) ?>: <?= e(fmt_num($w['v'])) ?> views, <?= $w['n'] ?> posts"></div>
                        <span class="whitespace-nowrap text-[9px] font-semibold text-muted sm:text-[10px]"><?= e($w["label"]) ?></span>
                    </div>
                <?php endforeach; ?>
            </div>
        </section>

        <section class="card p-5"><h2 class="section-title mb-4">By platform</h2><?= ($r = group_stats('platform', $from)) ? bar_list($r) : '<p class="text-xs text-muted">No data yet.</p>' ?></section>
        <section class="card p-5"><h2 class="section-title mb-4">By format</h2><?= ($r = group_stats('format', $from)) ? bar_list($r, 'bg-violet-500') : '<p class="text-xs text-muted">No data yet.</p>' ?></section>
        <section class="card p-5"><h2 class="section-title mb-4">By content pillar</h2><?= ($r = group_stats('pillar', $from)) ? bar_list($r, 'bg-emerald-500') : '<p class="text-xs text-muted">No data yet.</p>' ?></section>
        <section class="card p-5"><h2 class="section-title mb-1">Best publishing days</h2><p class="mb-4 text-xs text-muted">Average views per day of week</p><?= bar_list($best, 'bg-accent') ?></section>

        <section class="card min-w-0 p-5 lg:col-span-2" aria-labelledby="top-t">
            <h2 id="top-t" class="section-title mb-3">Top performing content</h2>
            <?php if (!$top): ?><p class="text-xs text-muted">No published content in this period yet.</p><?php else: ?>
                <div class="overflow-x-auto">
                    <table class="w-full min-w-[34rem] text-left text-xs">
                        <thead class="text-[11px] uppercase tracking-wide text-muted"><tr><th class="pb-2 pr-2 font-bold">Content</th><th class="pb-2 pr-2 text-right font-bold">Views</th><th class="pb-2 pr-2 text-right font-bold">Likes</th><th class="pb-2 pr-2 text-right font-bold">Comments</th><th class="pb-2 pr-2 text-right font-bold">Shares</th><th class="pb-2 text-right font-bold">ER</th></tr></thead>
                        <tbody class="divide-y divide-line">
                            <?php foreach ($top as $c): ?>
                                <tr><td class="py-2.5 pr-2"><a href="<?= e(url('/content/' . $c['id'])) ?>" class="flex items-center gap-2 font-bold hover:text-brand"><?= platform_chip($c['platform']) ?><span class="line-clamp-1"><?= e($c['title']) ?></span></a></td>
                                    <td class="pr-2 text-right font-bold"><?= e(fmt_k($c['views'])) ?></td><td class="pr-2 text-right"><?= e(fmt_k($c['likes'])) ?></td><td class="pr-2 text-right"><?= e(fmt_k($c['comments'])) ?></td><td class="pr-2 text-right"><?= e(fmt_k($c['shares'])) ?></td>
                                    <td class="text-right font-bold text-emerald-600 dark:text-emerald-400"><?= e(fmt_pct(er($c))) ?></td></tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </section>
    </div>
    <?php
    page('Analytics', 'analytics', (string) ob_get_clean(), ['subtitle' => 'Published content performance']);
}
