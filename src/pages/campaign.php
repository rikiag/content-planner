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
    $where = isset(CAMPAIGN_STATUSES[$f]) ? 'WHERE status = ?' : '';
    $rows = q("SELECT * FROM campaigns $where ORDER BY CASE status WHEN 'active' THEN 0 WHEN 'planned' THEN 1 ELSE 2 END, start_date DESC", $where ? [$f] : []);
    $counts = [];
    foreach (q('SELECT status, COUNT(*) n FROM campaigns GROUP BY status') as $r) {
        $counts[$r['status']] = (int) $r['n'];
    }

    ob_start(); ?>
    <div class="mb-4 flex items-center gap-2">
        <div class="flex min-w-0 flex-1 gap-2 overflow-x-auto no-scrollbar">
            <a class="pill" href="<?= e(url('/campaigns')) ?>" <?= $f === '' ? 'aria-current="true"' : '' ?>>All <b><?= array_sum($counts) ?></b></a>
            <?php foreach (CAMPAIGN_STATUSES as $k => $m): ?>
                <a class="pill" href="<?= e(url('/campaigns?status=' . $k)) ?>" <?= $f === $k ? 'aria-current="true"' : '' ?>><?= e($m['label']) ?> <b><?= $counts[$k] ?? 0 ?></b></a>
            <?php endforeach; ?>
        </div>
        <a href="<?= e(url('/campaigns/new')) ?>" class="btn-primary shrink-0 !px-3.5 sm:!px-4" aria-label="New campaign"><?= icon('plus', 'size-4') ?><span class="hidden sm:inline">Campaign</span></a>
    </div>

    <?php if (!$rows): ?>
        <?= empty_state('flag', 'No campaigns yet', 'Group your content into campaigns to track goals and results.', '<a href="' . e(url('/campaigns/new')) . '" class="btn-primary mt-2">' . icon('plus', 'size-4') . 'Create campaign</a>') ?>
    <?php else: ?>
        <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 2xl:grid-cols-3">
            <?php foreach ($rows as $c):
                $s = campaign_stats((int) $c['id']);
                $pct = $s['total'] > 0 ? (int) round($s['done'] / $s['total'] * 100) : 0;
                $inter = $s['views'] > 0 ? $s['inter'] / $s['views'] * 100 : 0; ?>
                <a href="<?= e(url('/campaigns/' . $c['id'])) ?>" class="card group flex flex-col overflow-hidden transition hover:-translate-y-0.5 hover:shadow-lg">
                    <div class="relative bg-linear-to-br <?= CAMPAIGN_COLORS[$c['color']] ?? CAMPAIGN_COLORS['blue'] ?> p-5 text-white">
                        <span class="chip bg-white/25 text-white"><?= e(CAMPAIGN_STATUSES[$c['status']]['label'] ?? $c['status']) ?></span>
                        <h2 class="mt-2 text-lg font-extrabold leading-snug"><?= e($c['name']) ?></h2>
                        <p class="mt-1 text-xs text-white/85"><?= e(fmt_date($c['start_date'])) ?> – <?= e(fmt_date($c['end_date'])) ?></p>
                        <span class="absolute right-4 top-4 opacity-30"><?= icon('flag', 'size-10') ?></span>
                    </div>
                    <div class="flex flex-1 flex-col gap-3 p-5">
                        <p class="line-clamp-2 text-xs text-muted"><?= e($c['goal'] ?: 'No campaign goal specified.') ?></p>
                        <div>
                            <div class="mb-1 flex justify-between text-[11px] font-bold"><span><?= (int) $s['done'] ?>/<?= (int) $s['total'] ?> posts published</span><span class="text-muted"><?= $pct ?>%</span></div>
                            <div class="bar-track"><div class="h-full rounded-full bg-brand" style="width:<?= $pct ?>%"></div></div>
                        </div>
                        <div class="mt-auto flex gap-4 text-xs font-semibold text-muted">
                            <span class="inline-flex items-center gap-1"><?= icon('eye', 'size-4') ?><?= e(fmt_k($s['views'])) ?></span>
                            <span class="inline-flex items-center gap-1"><?= icon('heart', 'size-4') ?>ER <?= e(fmt_pct($inter)) ?></span>
                        </div>
                    </div>
                </a>
            <?php endforeach; ?>
        </div>
    <?php endif;
    page('Campaigns', 'campaign', (string) ob_get_clean(), ['subtitle' => 'Organize content by objective']);
}

function page_campaign_detail(int $id): void
{
    $c = q1('SELECT * FROM campaigns WHERE id=?', [$id]) ?? not_found();
    $s = campaign_stats($id);
    $rows = q('SELECT c.*, ca.name AS campaign_name FROM contents c LEFT JOIN campaigns ca ON ca.id=c.campaign_id WHERE c.campaign_id=? ORDER BY (c.scheduled_at IS NULL), c.scheduled_at DESC', [$id]);
    $back = '/campaigns/' . $id;
    $inter = $s['views'] > 0 ? $s['inter'] / $s['views'] * 100 : 0;

    ob_start(); ?>
    <div class="mx-auto flex max-w-4xl flex-col gap-5">
        <section class="overflow-hidden rounded-[1.75rem] bg-linear-to-br <?= CAMPAIGN_COLORS[$c['color']] ?? CAMPAIGN_COLORS['blue'] ?> p-6 text-white shadow-card">
            <div class="flex flex-wrap items-start gap-3">
                <div class="min-w-0 flex-1">
                    <span class="chip bg-white/25 text-white"><?= e(CAMPAIGN_STATUSES[$c['status']]['label'] ?? '') ?></span>
                    <h2 class="mt-2 text-2xl font-extrabold leading-tight"><?= e($c['name']) ?></h2>
                    <p class="mt-1 text-sm text-white/90"><?= e(fmt_date($c['start_date'])) ?> – <?= e(fmt_date($c['end_date'])) ?></p>
                </div>
                <a href="<?= e(url('/campaigns/' . $id . '/edit')) ?>" class="btn bg-white/20 text-white hover:bg-white/30"><?= icon('pencil', 'size-4') ?>Edit</a>
            </div>
            <?php if ($c['goal']): ?><p class="mt-3 max-w-2xl text-sm leading-relaxed text-white/95"><?= e($c['goal']) ?></p><?php endif; ?>
            <div class="mt-5 grid grid-cols-2 gap-3 sm:grid-cols-4">
                <?php foreach ([['Posts', (string) $s['total']], ['Published', (string) (int) $s['done']], ['Views', fmt_k($s['views'])], ['ER', fmt_pct($inter)]] as [$l, $v]): ?>
                    <div class="rounded-2xl bg-white/15 px-4 py-3"><div class="text-[11px] font-semibold text-white/80"><?= $l ?></div><div class="text-xl font-extrabold"><?= e($v) ?></div></div>
                <?php endforeach; ?>
            </div>
        </section>

        <div class="flex items-center justify-between">
            <h2 class="section-title">Content in this campaign</h2>
            <a href="<?= e(url('/content/new?campaign=' . $id)) ?>" class="btn-primary"><?= icon('plus', 'size-4') ?>Add Content</a>
        </div>
        <?php if ($rows): ?>
            <div class="flex flex-col gap-3"><?php foreach ($rows as $r): ?><?= content_row($r, $back) ?><?php endforeach; ?></div>
        <?php else: ?>
            <?= empty_state('calendar', 'No content yet', 'Add the first post to this campaign.') ?>
        <?php endif; ?>
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

    $del = $id ? '<div class="mx-auto mt-3 max-w-3xl text-right">' . delete_form('/campaigns/' . $id . '/delete', 'Delete this campaign?', 'Delete campaign') . '</div>' : '';
    page($id ? 'Edit Campaign' : 'New Campaign', 'campaign', form_shell($id ? "/campaigns/$id/edit" : '/campaigns/new', $f, $id ? "/campaigns/$id" : '/campaigns') . $del, ['subtitle' => $id ? e($c['name']) : 'Create a new campaign']);
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

function action_campaign_delete(int $id): void
{
    exec_sql('DELETE FROM campaigns WHERE id=?', [$id]);
    flash('Campaign deleted.');
    redirect('/campaigns');
}
