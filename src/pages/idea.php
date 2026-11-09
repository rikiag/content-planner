<?php
declare(strict_types=1);

defined('ROOT') || (http_response_code(403) && exit);

const IDEA_STATUSES = [
    'new'   => ['label' => 'New',   'chip' => 'bg-rose-100 text-rose-700 dark:bg-rose-500/20 dark:text-rose-300'],
    'saved' => ['label' => 'Saved', 'chip' => 'bg-amber-100 text-amber-800 dark:bg-amber-500/20 dark:text-amber-300'],
    'used'  => ['label' => 'Used',  'chip' => 'bg-emerald-100 text-emerald-700 dark:bg-emerald-500/20 dark:text-emerald-300'],
];
const PRIORITY_CLS = [
    'high'   => 'text-rose-600 dark:text-rose-400',
    'medium' => 'text-amber-600 dark:text-amber-400',
    'low'    => 'text-slate-500',
];

function page_ideas(): void
{
    $f = input('status');
    $pillar = input('pillar');
    $where = [];
    $par = [];
    if (isset(IDEA_STATUSES[$f])) {
        $where[] = 'status=?';
        $par[] = $f;
    }
    if (in_array($pillar, PILLARS, true)) {
        $where[] = 'pillar=?';
        $par[] = $pillar;
    }
    $rows = q('SELECT * FROM ideas' . ($where ? ' WHERE ' . implode(' AND ', $where) : '') . " ORDER BY status='used', CASE priority WHEN 'high' THEN 0 WHEN 'medium' THEN 1 ELSE 2 END, id DESC", $par);
    $counts = [];
    foreach (q('SELECT status, COUNT(*) n FROM ideas GROUP BY status') as $r) {
        $counts[$r['status']] = (int) $r['n'];
    }
    $back = request_path() . qs();

    ob_start(); ?>
    <div x-data="liveFilter()">
    <form method="post" action="<?= e(url('/ideas')) ?>" class="card mb-4 flex flex-col gap-2 p-3 sm:flex-row sm:items-center sm:p-4">
        <?= csrf_field() ?>
        <span class="hidden size-10 shrink-0 place-items-center rounded-2xl bg-amber-100 text-amber-600 sm:grid dark:bg-amber-400/15"><?= icon('bulb', 'size-5') ?></span>
        <input name="title" required maxlength="160" placeholder="Capture a new idea before it slips away…" class="input flex-1" aria-label="New idea">
        <select name="pillar" class="input sm:!w-44" aria-label="Pillar"><option value="">Pillar…</option><?php foreach (PILLARS as $p): ?><option><?= e($p) ?></option><?php endforeach; ?></select>
        <button class="btn-primary"><?= icon('plus', 'size-4') ?>Save idea</button>
    </form>

    <label class="relative mb-3 block">
        <span class="sr-only">Filter ideas</span>
        <span class="pointer-events-none absolute left-3.5 top-1/2 -translate-y-1/2 text-muted"><?= icon('search', 'size-4') ?></span>
        <input type="search" x-model="q" placeholder="Filter displayed ideas…" class="input !pl-10">
    </label>
    <div class="-mx-4 mb-4 flex gap-2 overflow-x-auto px-4 pb-1 no-scrollbar sm:mx-0 sm:flex-wrap sm:px-0">
        <a class="pill" href="<?= e(url('/ideas' . qs(['status' => null]))) ?>" <?= $f === '' ? 'aria-current="true"' : '' ?>>All <b><?= array_sum($counts) ?></b></a>
        <?php foreach (IDEA_STATUSES as $k => $m): ?>
            <a class="pill" href="<?= e(url('/ideas' . qs(['status' => $k]))) ?>" <?= $f === $k ? 'aria-current="true"' : '' ?>><?= e($m['label']) ?> <b><?= $counts[$k] ?? 0 ?></b></a>
        <?php endforeach; ?>
        <span class="mx-1 hidden w-px bg-line sm:block"></span>
        <?php foreach (PILLARS as $p): ?>
            <a class="pill" href="<?= e(url('/ideas' . qs(['pillar' => $pillar === $p ? null : $p]))) ?>" <?= $pillar === $p ? 'aria-current="true"' : '' ?>><?= e($p) ?></a>
        <?php endforeach; ?>
    </div>

    <?php if (!$rows): ?>
        <?= empty_state('bulb', 'No ideas yet', 'Jot down anything that comes to mind. Convert ideas into scheduled posts with a single click.') ?>
    <?php else: ?>
        <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 xl:grid-cols-3">
            <?php foreach ($rows as $i): $st = IDEA_STATUSES[$i['status']] ?? IDEA_STATUSES['new']; ?>
                <article class="card flex flex-col gap-3 p-5 <?= $i['status'] === 'used' ? 'opacity-70' : '' ?>" data-search="<?= e(mb_strtolower($i['title'] . ' ' . $i['notes'] . ' ' . $i['pillar'])) ?>" x-show="match($el)" x-transition.opacity>
                    <div class="flex flex-wrap items-center gap-2">
                        <span class="chip <?= $st['chip'] ?>"><?= e($st['label']) ?></span>
                        <?php if ($i['pillar']): ?><span class="chip bg-soft text-muted"><?= e($i['pillar']) ?></span><?php endif; ?>
                        <span class="ml-auto text-[11px] font-bold <?= PRIORITY_CLS[$i['priority']] ?? '' ?>">● <?= e(PRIORITIES[$i['priority']] ?? $i['priority']) ?></span>
                    </div>
                    <h2 class="text-[15px] font-extrabold leading-snug"><?= e($i['title']) ?></h2>
                    <?php if ($i['notes']): ?><p class="line-clamp-3 text-xs leading-relaxed text-muted"><?= e($i['notes']) ?></p><?php endif; ?>
                    <div class="mt-auto flex flex-wrap items-center gap-1.5 pt-1">
                        <?php if ($i['status'] !== 'used'): ?>
                            <form method="post" action="<?= e(url('/ideas/' . $i['id'] . '/convert')) ?>"><?= csrf_field() ?><button class="btn-dark !px-3.5 !py-2 !text-xs"><?= icon('calendar', 'size-3.5') ?>Turn into post</button></form>
                            <form method="post" action="<?= e(url('/ideas/' . $i['id'] . '/status')) ?>"><?= csrf_field() ?><input type="hidden" name="back" value="<?= e($back) ?>"><input type="hidden" name="status" value="<?= $i['status'] === 'saved' ? 'new' : 'saved' ?>">
                                <button class="grid size-9 place-items-center rounded-xl text-muted hover:bg-soft hover:text-ink <?= $i['status'] === 'saved' ? '!text-amber-500' : '' ?>" aria-label="<?= $i['status'] === 'saved' ? 'Unsave' : 'Save' ?>" title="<?= $i['status'] === 'saved' ? 'Unsave' : 'Save' ?>"><?= icon('bookmark', 'size-4') ?></button></form>
                        <?php endif; ?>
                        <a href="<?= e(url('/ideas/' . $i['id'] . '/edit')) ?>" class="ml-auto grid size-9 place-items-center rounded-xl text-muted hover:bg-soft hover:text-ink" aria-label="Edit"><?= icon('pencil', 'size-4') ?></a>
                        <?= delete_form('/ideas/' . $i['id'] . '/delete', 'Delete this idea?', 'Delete', 'grid size-9 place-items-center rounded-xl text-muted hover:bg-rose-50 hover:text-rose-600 dark:hover:bg-rose-500/10', true, $back) ?>
                    </div>
                </article>
            <?php endforeach; ?>
        </div>
        <p x-show="visible() === 0" x-cloak class="card mt-4 px-6 py-10 text-center text-sm text-muted">No ideas match “<b x-text="q"></b>”.</p>
    <?php endif; ?>
    </div>
    <?php
    page('Idea Bank', 'idea', (string) ob_get_clean(), ['subtitle' => 'Capture & organize content ideas']);
}

function page_idea_form(int $id): void
{
    $i = q1('SELECT * FROM ideas WHERE id=?', [$id]) ?? not_found();
    $f = field('title', 'Idea', 'text', $i['title'], ['req' => true, 'max' => 160])
        . field('notes', 'Notes / Outline', 'textarea', $i['notes'], ['rows' => 5])
        . '<div class="grid grid-cols-1 gap-4 sm:grid-cols-3">'
        . field('pillar', 'Pillar', 'select', $i['pillar'], ['options' => ['' => '— Select —'] + array_combine(PILLARS, PILLARS)])
        . field('priority', 'Priority', 'select', $i['priority'], ['options' => PRIORITIES])
        . field('status', 'Status', 'select', $i['status'], ['options' => array_map(fn($s) => $s['label'], IDEA_STATUSES)])
        . '</div>';
    page('Edit Idea', 'idea', form_shell("/ideas/$id/edit", $f, '/ideas'), ['subtitle' => 'Idea Bank']);
}

function action_idea_quick_add(): void
{
    $t = mb_substr(input('title'), 0, 160);
    if ($t === '') {
        flash('Please enter an idea first.', 'err');
        redirect('/ideas');
    }
    exec_sql('INSERT INTO ideas(title,pillar) VALUES(?,?)', [$t, in_array(input('pillar'), PILLARS, true) ? input('pillar') : '']);
    flash('Idea saved to Idea Bank.');
    redirect('/ideas');
}

function action_idea_save(int $id): void
{
    q1('SELECT 1 FROM ideas WHERE id=?', [$id]) ?? not_found();
    $t = mb_substr(input('title'), 0, 160);
    if ($t === '') {
        flash('Idea title cannot be empty.', 'err');
        redirect("/ideas/$id/edit");
    }
    exec_sql('UPDATE ideas SET title=?, notes=?, pillar=?, priority=?, status=? WHERE id=?', [
        $t, input('notes'), in_array(input('pillar'), PILLARS, true) ? input('pillar') : '',
        isset(PRIORITIES[input('priority')]) ? input('priority') : 'medium',
        isset(IDEA_STATUSES[input('status')]) ? input('status') : 'new', $id,
    ]);
    flash('Idea updated.');
    redirect('/ideas');
}

function action_idea_status(int $id): void
{
    if (isset(IDEA_STATUSES[input('status')])) {
        exec_sql('UPDATE ideas SET status=? WHERE id=?', [input('status'), $id]);
    }
    redirect(safe_back('/ideas'));
}

function action_idea_to_content(int $id): void
{
    $i = q1('SELECT * FROM ideas WHERE id=?', [$id]) ?? not_found();
    exec_sql("INSERT INTO contents(title,pillar,status,caption,progress) VALUES(?,?, 'idea', ?, 10)", [$i['title'], $i['pillar'], $i['notes']]);
    $cid = (int) db()->lastInsertId();
    exec_sql("UPDATE ideas SET status='used' WHERE id=?", [$id]);
    flash('Idea converted into content. Complete the details below.');
    redirect('/content/' . $cid);
}

function action_idea_delete(int $id): void
{
    exec_sql('DELETE FROM ideas WHERE id=?', [$id]);
    flash('Idea deleted.');
    redirect(safe_back('/ideas'));
}
