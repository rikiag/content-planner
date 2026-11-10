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

/** Render the full Analytics block (embedded inside Dashboard). */
function render_analytics_section(): string
{
    $initialRange = input('r', '30');
    $initialRange = in_array($initialRange, ['7', '30', '90', 'all'], true) ? $initialRange : '30';
    $pd = pub_date_sql();

    // Weekly trend (last 8 weeks)
    $weeks = [];
    for ($i = 7; $i >= 0; $i--) {
        [$ws, $we] = week_range(strtotime("-$i weeks"));
        $k = kpis($ws, $we);
        $weeks[] = ['label' => (int) date('j', strtotime($ws)) . ' ' . MONTHS_SHORT[(int) date('n', strtotime($ws)) - 1], 'v' => $k['views'], 'n' => $k['n']];
    }
    $wmax = max(1, ...array_column($weeks, 'v'));

    $rangesMeta = [
        '7'   => ['days' => 7,    'tab' => '7 days',  'lbl' => 'Last 7 days'],
        '30'  => ['days' => 30,   'tab' => '30 days', 'lbl' => 'Last 30 days'],
        '90'  => ['days' => 90,   'tab' => '90 days', 'lbl' => 'Last 90 days'],
        'all' => ['days' => 3650, 'tab' => 'All',     'lbl' => 'All time'],
    ];

    ob_start(); ?>
    <section id="analytics" aria-labelledby="ana-t"
        x-data="{
            range: '<?= e($initialRange) ?>',
            setRange(r) {
                this.range = r;
                const u = new URL(location.href);
                if (r === '30') u.searchParams.delete('r'); else u.searchParams.set('r', r);
                history.replaceState({}, '', u);
            }
        }">
        <div class="mb-3 flex flex-wrap items-center justify-between gap-2">
            <h2 id="ana-t" class="section-title">Analytics</h2>
            <div class="flex items-center gap-2">
                <div class="grid grid-cols-4 rounded-full bg-surface p-1 shadow-sm sm:flex" role="tablist" aria-label="Date range">
                    <?php foreach ($rangesMeta as $rk => $rm): ?>
                        <button type="button" role="tab" :aria-selected="range === '<?= $rk ?>'" @click="setRange('<?= $rk ?>')"
                            class="rounded-full px-3 py-1.5 text-center text-xs font-bold transition"
                            :class="range === '<?= $rk ?>' ? 'bg-hero text-white' : 'text-muted hover:text-ink'"><?= e($rm['tab']) ?></button>
                    <?php endforeach; ?>
                </div>
                <a href="<?= e(url('/export/content.csv')) ?>" class="btn-ghost shrink-0 !px-3.5 !py-2 !text-xs" aria-label="Download CSV"><?= icon('download', 'size-4') ?><span class="hidden sm:inline">Download CSV</span></a>
            </div>
        </div>

        <?php foreach ($rangesMeta as $rk => $rm):
            $days = $rm['days'];
            $to = date('Y-m-d');
            $from = date('Y-m-d', strtotime("-" . ($days - 1) . " days"));
            $pFrom = date('Y-m-d', strtotime("-" . (2 * $days - 1) . " days"));
            $pTo = date('Y-m-d', strtotime("-$days days"));

            $now = kpis($from, $to);
            $prev = kpis($pFrom, $pTo);

            $dow = q("SELECT CAST(strftime('%w', $pd) AS INTEGER) d, AVG(views) a, COUNT(*) n FROM contents WHERE status='published' AND substr($pd,1,10) >= ? GROUP BY d", [$from]);
            $dowMap = [];
            foreach ($dow as $r) {
                $dowMap[(int) $r['d']] = (float) $r['a'];
            }
            $best = [];
            foreach ([1, 2, 3, 4, 5, 6, 0] as $d) {
                $best[] = [DAYS_FULL[$d], (int) ($dowMap[$d] ?? 0), $dowMap ? fmt_k((int) ($dowMap[$d] ?? 0)) . ' avg' : '', ''];
            }

            $top = q("SELECT c.*, ca.name AS campaign_name FROM contents c LEFT JOIN campaigns ca ON ca.id=c.campaign_id WHERE c.status='published' AND substr(COALESCE(c.published_at, c.scheduled_at, c.created_at),1,10) >= ? ORDER BY c.views DESC LIMIT 8", [$from]);
            $kpiCards = [
                ['Views', fmt_k($now['views']), delta_chip($now['views'], $prev['views']), 'eye', 'bg-sky-100 text-sky-600 dark:bg-sky-500/20'],
                ['Interactions', fmt_k($now['inter']), delta_chip($now['inter'], $prev['inter']), 'heart', 'bg-rose-100 text-rose-600 dark:bg-rose-500/20'],
                ['Engagement rate', fmt_pct($now['er']), delta_chip($now['er'], $prev['er'], true), 'target', 'bg-violet-100 text-violet-600 dark:bg-violet-500/20'],
                ['Published posts', (string) $now['n'], delta_chip($now['n'], $prev['n']), 'calendar', 'bg-emerald-100 text-emerald-600 dark:bg-emerald-500/20'],
            ];
        ?>
        <div x-show="range === '<?= $rk ?>'" <?= $rk !== $initialRange ? 'x-cloak' : '' ?> x-transition.opacity>
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
            <p class="mt-2 px-1 text-[11px] text-muted">Period: <?= e($rm['lbl']) ?>. Interactions = likes + comments + shares + saves.</p>

            <div class="mt-4 grid grid-cols-1 gap-4 lg:grid-cols-2">
                <section class="card p-5 lg:col-span-2" aria-label="Weekly views">
                    <h3 class="section-title">Weekly views</h3>
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

                <section class="card p-5"><h3 class="section-title mb-4">By platform</h3><?= ($r = group_stats('platform', $from)) ? bar_list($r) : '<p class="text-xs text-muted">No data yet.</p>' ?></section>
                <section class="card p-5"><h3 class="section-title mb-4">By format</h3><?= ($r = group_stats('format', $from)) ? bar_list($r, 'bg-violet-500') : '<p class="text-xs text-muted">No data yet.</p>' ?></section>
                <section class="card p-5"><h3 class="section-title mb-4">By content pillar</h3><?= ($r = group_stats('pillar', $from)) ? bar_list($r, 'bg-emerald-500') : '<p class="text-xs text-muted">No data yet.</p>' ?></section>
                <section class="card p-5"><h3 class="section-title mb-1">Best publishing days</h3><p class="mb-4 text-xs text-muted">Average views per day of week</p><?= bar_list($best, 'bg-accent') ?></section>

                <section class="card min-w-0 p-5 lg:col-span-2" aria-label="Top performing content">
                    <h3 class="section-title mb-3">Top performing content</h3>
                    <?php if (!$top): ?><p class="text-xs text-muted">No published content in this period yet.</p><?php else: ?>
                        <div class="overflow-x-auto">
                            <table class="w-full min-w-[34rem] text-left text-xs">
                                <thead class="text-[11px] uppercase tracking-wide text-muted"><tr><th class="pb-2 pr-2 font-bold">Content</th><th class="pb-2 pr-2 text-right font-bold">Views</th><th class="pb-2 pr-2 text-right font-bold">Likes</th><th class="pb-2 pr-2 text-right font-bold">Comments</th><th class="pb-2 pr-2 text-right font-bold">Shares</th><th class="pb-2 text-right font-bold">ER</th></tr></thead>
                                <tbody class="divide-y divide-line">
                                    <?php foreach ($top as $c): $cp = content_payload($c); ?>
                                        <tr><td class="py-2.5 pr-2"><a href="<?= e(url('/content/' . $c['id'])) ?>" @click.prevent="$store.contentModal.show(<?= e(json_encode($cp, JSON_UNESCAPED_UNICODE)) ?>)" class="flex items-center gap-2 font-bold hover:text-brand"><?= platform_chip($c['platform']) ?><span class="line-clamp-1"><?= e($c['title']) ?></span></a></td>
                                            <td class="pr-2 text-right font-bold"><?= e(fmt_k($c['views'])) ?></td><td class="pr-2 text-right"><?= e(fmt_k($c['likes'])) ?></td><td class="pr-2 text-right"><?= e(fmt_k($c['comments'])) ?></td><td class="pr-2 text-right"><?= e(fmt_k($c['shares'])) ?></td>
                                            <td class="text-right font-bold text-emerald-600 dark:text-emerald-400"><?= e(fmt_pct(er($c))) ?></td></tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php endif; ?>
                </section>
            </div>
        </div>
        <?php endforeach; ?>
    </section>
    <?php
    return (string) ob_get_clean();
}

function page_analytics(): void
{
    $r = input('r');
    redirect('/' . ($r !== '' ? '?r=' . rawurlencode($r) : '') . '#analytics');
}
