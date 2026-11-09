<?php
declare(strict_types=1);

defined('ROOT') || (http_response_code(403) && exit);

/** CSV headers and rows per dataset */
function export_dataset(string $name): array
{
    return match ($name) {
        'content' => [
            ['Title', 'Platform', 'Format', 'Pillar', 'Status', 'Campaign', 'Scheduled At', 'Published At', 'Progress %', 'Views', 'Likes', 'Comments', 'Shares', 'Saves', 'ER %', 'Post URL'],
            array_map(fn($c) => [$c['title'], $c['platform'], $c['format'], $c['pillar'], STATUSES[$c['status']]['label'] ?? $c['status'], $c['campaign_name'], $c['scheduled_at'], $c['published_at'], $c['progress'], $c['views'], $c['likes'], $c['comments'], $c['shares'], $c['saves'], number_format(er($c), 2, '.', ''), $c['post_url']],
                q('SELECT c.*, ca.name AS campaign_name FROM contents c LEFT JOIN campaigns ca ON ca.id=c.campaign_id ORDER BY c.scheduled_at')),
        ],
        'campaigns' => [
            ['Name', 'Goal', 'Status', 'Start Date', 'End Date', 'Total Content', 'Published'],
            array_map(fn($c) => [$c['name'], $c['goal'], CAMPAIGN_STATUSES[$c['status']]['label'] ?? $c['status'], $c['start_date'], $c['end_date'], campaign_stats((int) $c['id'])['total'], (int) campaign_stats((int) $c['id'])['done']], q('SELECT * FROM campaigns ORDER BY start_date')),
        ],
        default => [
            ['Idea', 'Notes', 'Pillar', 'Priority', 'Status', 'Created At'],
            array_map(fn($i) => [$i['title'], $i['notes'], $i['pillar'], $i['priority'], $i['status'], $i['created_at']], q('SELECT * FROM ideas ORDER BY id DESC')),
        ],
    };
}

/** Prevent spreadsheet formula injection when CSV is opened in Excel / Google Sheets */
function csv_safe(mixed $v): string
{
    $s = (string) $v;
    return $s !== '' && strpbrk($s[0], "=+-@\t\r") !== false && !is_numeric($s) ? "'" . $s : $s;
}

function action_export_csv(string $name): void
{
    [$head, $rows] = export_dataset($name);
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="content-planner-' . $name . '-' . date('Ymd') . '.csv"');
    $out = fopen('php://output', 'w');
    fwrite($out, "\xEF\xBB\xBF");                       // UTF-8 BOM for Excel compatibility
    fputcsv($out, $head, ',', '"', '');
    foreach ($rows as $r) {
        fputcsv($out, array_map('csv_safe', $r), ',', '"', '');
    }
    fclose($out);
}

function action_export_json(): void
{
    $data = [
        'app' => APP_NAME, 'version' => APP_VERSION, 'exported_at' => date('c'),
        'settings' => q('SELECT * FROM settings'), 'campaigns' => q('SELECT * FROM campaigns'), 'contents' => q('SELECT * FROM contents'),
        'ideas' => q('SELECT * FROM ideas'), 'resources' => q('SELECT * FROM resources'), 'notes' => q('SELECT * FROM notes'),
    ];
    header('Content-Type: application/json; charset=utf-8');
    header('Content-Disposition: attachment; filename="content-planner-backup-' . date('Ymd') . '.json"');
    echo json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
}

function page_export(): void
{
    [$head, $rows] = export_dataset('content');
    $tsv = implode("\t", $head) . "\n";
    foreach ($rows as $r) {
        $tsv .= implode("\t", array_map(fn($v) => str_replace(["\t", "\r", "\n"], ' ', csv_safe($v)), $r)) . "\n";
    }
    $files = [
        ['content', 'Content schedule & performance', 'All content items with status, schedule, and performance metrics.', 'calendar', count($rows)],
        ['campaigns', 'Campaigns', 'Campaign summary with total and published post counts.', 'flag', (int) qv('SELECT COUNT(*) FROM campaigns')],
        ['ideas', 'Idea Bank', 'All saved ideas with content pillar and priority.', 'bulb', (int) qv('SELECT COUNT(*) FROM ideas')],
    ];

    ob_start(); ?>
    <div class="mx-auto grid grid-cols-1 max-w-5xl gap-5 lg:grid-cols-2">
        <section class="card p-5 sm:p-6 lg:col-span-2">
            <div class="flex items-start gap-4">
                <span class="grid size-12 shrink-0 place-items-center rounded-2xl bg-emerald-100 text-emerald-600 dark:bg-emerald-500/20"><?= icon('sheet', 'size-6') ?></span>
                <div><h2 class="text-lg font-extrabold">Send to Google Sheets</h2>
                    <p class="mt-1 text-sm text-muted">Fastest zero-auth workflow: copy the table below, open Google Sheets, and paste (Ctrl/⌘ + V). Columns split automatically.</p></div>
            </div>
            <div class="mt-4 flex flex-wrap gap-2">
                <button type="button" class="btn-primary" @click="$copy($refs.tsv.value)"><?= icon('copy', 'size-4') ?>Copy content table</button>
                <a href="https://sheets.new" target="_blank" rel="noopener noreferrer" class="btn-soft"><?= icon('external', 'size-4') ?>Open new Google Sheet</a>
            </div>
            <textarea id="tsv" x-ref="tsv" readonly rows="5" class="input mt-4 whitespace-pre font-mono text-[11px]" aria-label="Table preview (TSV)"><?= e($tsv) ?></textarea>
            <p class="mt-2 text-[11px] text-muted">Alternative: download a CSV file below, then in Google Sheets choose File → Import → Upload.</p>
        </section>

        <?php foreach ($files as [$k, $t, $d, $ic, $n]): ?>
            <section class="card flex items-center gap-4 p-5">
                <span class="grid size-11 shrink-0 place-items-center rounded-2xl bg-soft text-brand"><?= icon($ic, 'size-5') ?></span>
                <div class="min-w-0 flex-1"><h3 class="text-sm font-extrabold"><?= e($t) ?></h3><p class="text-xs text-muted"><?= e($d) ?> <b>(<?= $n ?> rows)</b></p></div>
                <a href="<?= e(url('/export/' . $k . '.csv')) ?>" class="btn-ghost !px-3.5 !py-2 !text-xs" download><?= icon('download', 'size-4') ?>CSV</a>
            </section>
        <?php endforeach; ?>
        <section class="card flex items-center gap-4 p-5">
            <span class="grid size-11 shrink-0 place-items-center rounded-2xl bg-soft text-brand"><?= icon('database', 'size-5') ?></span>
            <div class="min-w-0 flex-1"><h3 class="text-sm font-extrabold">Full workspace backup</h3><p class="text-xs text-muted">All workspace tables exported in a single JSON file.</p></div>
            <a href="<?= e(url('/export/backup.json')) ?>" class="btn-ghost !px-3.5 !py-2 !text-xs" download><?= icon('download', 'size-4') ?>JSON</a>
        </section>
    </div>
    <?php
    page('Export Sheet', 'export', (string) ob_get_clean(), ['subtitle' => 'Export data to Google Sheets / Excel']);
}
