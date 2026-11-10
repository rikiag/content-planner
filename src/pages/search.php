<?php
declare(strict_types=1);

defined('ROOT') || (http_response_code(403) && exit);

function page_search(): void
{
    $s = mb_substr(input('q'), 0, 80);
    $like = '%' . str_replace(['%', '_'], ['\%', '\_'], $s) . '%';
    $res = ['content' => [], 'campaigns' => [], 'ideas' => [], 'resources' => [], 'notes' => []];
    if ($s !== '') {
        $res['content'] = q("SELECT c.*, ca.name AS campaign_name FROM contents c LEFT JOIN campaigns ca ON ca.id=c.campaign_id WHERE c.title LIKE :q ESCAPE '\\' OR c.caption LIKE :q ESCAPE '\\' OR c.hashtags LIKE :q ESCAPE '\\' ORDER BY c.scheduled_at DESC LIMIT 15", ["q" => $like]);
        $res['campaigns'] = q("SELECT * FROM campaigns WHERE name LIKE :q ESCAPE '\\' OR goal LIKE :q ESCAPE '\\' LIMIT 10", ["q" => $like]);
        $res['ideas'] = q("SELECT * FROM ideas WHERE title LIKE :q ESCAPE '\\' OR notes LIKE :q ESCAPE '\\' LIMIT 10", ["q" => $like]);
        $res['resources'] = q("SELECT * FROM resources WHERE title LIKE :q ESCAPE '\\' OR body LIKE :q ESCAPE '\\' OR tag LIKE :q ESCAPE '\\' LIMIT 10", ["q" => $like]);
        $res['notes'] = q("SELECT * FROM notes WHERE body LIKE :q ESCAPE '\\' LIMIT 10", ["q" => $like]);
    }
    $total = array_sum(array_map('count', $res));

    $group = function (string $title, string $icon, array $items, callable $row) {
        if (!$items) {
            return '';
        }
        $h = '<section class="mb-6"><h2 class="mb-2 flex items-center gap-2 text-sm font-extrabold">' . icon($icon, 'size-4') . e($title) . ' <span class="chip bg-soft text-muted">' . count($items) . '</span></h2><div class="flex flex-col gap-2">';
        foreach ($items as $i) {
            $h .= $row($i);
        }
        return $h . '</div></section>';
    };
    $link = fn(string $href, string $title, string $meta, string $lead = '') => '<a href="' . e(url($href)) . '" class="card flex items-center gap-3 px-4 py-3 transition hover:-translate-y-0.5">' . $lead
        . '<span class="min-w-0 flex-1"><b class="block truncate text-sm">' . e($title) . '</b><span class="block truncate text-xs text-muted">' . e($meta) . '</span></span>' . icon('chevron-right', 'size-4 text-muted') . '</a>';

    ob_start(); ?>
    <div class="mx-auto max-w-3xl">
        <?php if ($s === ''): ?>
            <?= empty_state('search', 'Search everything', 'Type a title, caption, hashtag, campaign name, idea, or note in the search bar above.') ?>
        <?php elseif ($total === 0): ?>
            <?= empty_state('search', 'No results found', 'Could not find “' . $s . '”. Try a shorter or different keyword.') ?>
        <?php else: ?>
            <p class="mb-4 text-xs text-muted"><?= $total ?> results for “<b class="text-ink"><?= e($s) ?></b>”</p>
            <?= $group('Content Plan', 'calendar', $res['content'], fn($c) => $link('/content/' . $c['id'], $c['title'], $c['format'] . ' · ' . fmt_dt($c['scheduled_at']) . ' · ' . (statuses()[$c['status']]['label'] ?? $c['status']), '<span class="grid size-9 shrink-0 place-items-center rounded-xl text-[10px] font-extrabold ' . (platforms()[$c['platform']]['cls'] ?? 'bg-soft') . '">' . e(platform_abbr($c['platform'])) . '</span>')) ?>
            <?= $group('Campaigns', 'flag', $res['campaigns'], fn($c) => $link('/campaigns/' . $c['id'], $c['name'], CAMPAIGN_STATUSES[$c['status']]['label'] ?? '')) ?>
            <?= $group('Idea Bank', 'bulb', $res['ideas'], fn($i) => $link('/ideas/' . $i['id'] . '/edit', $i['title'], $i['pillar'] ?: 'No pillar')) ?>
            <?= $group('Resources', 'folder', $res['resources'], fn($r) => $link('/resources/' . $r['id'] . '/edit', $r['title'], (RESOURCE_TYPES[$r['type']]['label'] ?? '') . ($r['tag'] ? ' · ' . $r['tag'] : ''))) ?>
            <?= $group('Notes', 'note', $res['notes'], fn($n) => $link('/notes', mb_strimwidth($n['body'], 0, 70, '…'), fmt_dt($n['updated_at']))) ?>
        <?php endif; ?>
    </div>
    <?php
    page('Search', 'search', (string) ob_get_clean(), ['subtitle' => $s !== '' ? 'Results for “' . $s . '”' : 'Search across your workspace']);
}
