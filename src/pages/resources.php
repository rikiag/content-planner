<?php
declare(strict_types=1);

defined('ROOT') || (http_response_code(403) && exit);

function page_resources(): void
{
    $t = input('type');
    $s = input('q');
    $where = [];
    $par = [];
    if (isset(RESOURCE_TYPES[$t])) {
        $where[] = 'type=?';
        $par[] = $t;
    }
    if ($s !== '') {
        $where[] = '(title LIKE ? OR body LIKE ? OR tag LIKE ?)';
        array_push($par, "%$s%", "%$s%", "%$s%");
    }
    $rows = q('SELECT * FROM resources' . ($where ? ' WHERE ' . implode(' AND ', $where) : '') . ' ORDER BY id DESC', $par);
    $counts = [];
    foreach (q('SELECT type, COUNT(*) n FROM resources GROUP BY type') as $r) {
        $counts[$r['type']] = (int) $r['n'];
    }
    $back = request_path() . qs();

    ob_start(); ?>
    <div x-data="liveFilter(<?= e(json_encode($s, JSON_UNESCAPED_UNICODE)) ?>)">
    <div class="mb-4 flex items-center gap-2">
        <form action="<?= e(url('/resources')) ?>" method="get" class="relative min-w-0 flex-1">
            <?php if ($t): ?><input type="hidden" name="type" value="<?= e($t) ?>"><?php endif; ?>
            <span class="pointer-events-none absolute left-3.5 top-1/2 -translate-y-1/2 text-muted"><?= icon('search', 'size-4') ?></span>
            <input type="search" name="q" x-model="q" placeholder="Search templates, links, hashtags…" class="input !pl-10" aria-label="Search resources" enterkeyhint="search">
        </form>
        <a href="<?= e(url('/resources/new')) ?>" class="btn-primary shrink-0 !px-3.5 sm:!px-4" aria-label="Add resource"><?= icon('plus', 'size-4') ?><span class="hidden sm:inline">Resource</span></a>
    </div>
    <div class="-mx-4 mb-4 flex gap-2 overflow-x-auto px-4 pb-1 no-scrollbar sm:mx-0 sm:flex-wrap sm:px-0">
        <a class="pill" href="<?= e(url('/resources' . qs(['type' => null]))) ?>" <?= $t === '' ? 'aria-current="true"' : '' ?>>All <b><?= array_sum($counts) ?></b></a>
        <?php foreach (RESOURCE_TYPES as $k => $m): ?>
            <a class="pill" href="<?= e(url('/resources' . qs(['type' => $k]))) ?>" <?= $t === $k ? 'aria-current="true"' : '' ?>><?= icon($m['icon'], 'size-3.5') ?><?= e($m['label']) ?> <b><?= $counts[$k] ?? 0 ?></b></a>
        <?php endforeach; ?>
    </div>

    <?php if (!$rows): ?>
        <?= empty_state('folder', 'No resources yet', 'Save brand guidelines, caption templates, hashtag sets, or asset folder links here.', '<a href="' . e(url('/resources/new')) . '" class="btn-primary mt-2">' . icon('plus', 'size-4') . 'Add resource</a>') ?>
    <?php else: ?>
        <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 xl:grid-cols-3">
            <?php foreach ($rows as $r): $m = RESOURCE_TYPES[$r['type']] ?? RESOURCE_TYPES['link']; ?>
                <article class="card flex flex-col gap-3 p-5" data-search="<?= e(mb_strtolower($r['title'] . ' ' . $r['body'] . ' ' . $r['tag'] . ' ' . (RESOURCE_TYPES[$r['type']]['label'] ?? ''))) ?>" x-show="match($el)" x-transition.opacity>
                    <div class="flex items-start gap-3">
                        <span class="grid size-11 shrink-0 place-items-center rounded-2xl <?= $m['cls'] ?>"><?= icon($m['icon'], 'size-5') ?></span>
                        <div class="min-w-0 flex-1">
                            <h2 class="text-[15px] font-extrabold leading-snug"><?= e($r['title']) ?></h2>
                            <p class="mt-0.5 text-[11px] font-semibold text-muted"><?= e($m['label']) ?><?= $r['tag'] ? ' · ' . e($r['tag']) : '' ?></p>
                        </div>
                    </div>
                    <?php if ($r['body']): ?><p class="line-clamp-4 whitespace-pre-line rounded-2xl bg-soft2 p-3 text-xs leading-relaxed text-muted"><?= e($r['body']) ?></p><?php endif; ?>
                    <div class="mt-auto flex flex-wrap items-center gap-1.5">
                        <?php if ($r['url']): ?><a href="<?= e($r['url']) ?>" target="_blank" rel="noopener noreferrer" class="btn-soft !px-3.5 !py-2 !text-xs"><?= icon('external', 'size-3.5') ?>Open</a><?php endif; ?>
                        <?php if ($r['body']): ?><button type="button" class="btn-soft !px-3.5 !py-2 !text-xs" @click="$copy(<?= e(json_encode($r['body'], JSON_UNESCAPED_UNICODE)) ?>)"><?= icon('copy', 'size-3.5') ?>Copy text</button><?php endif; ?>
                        <a href="<?= e(url('/resources/' . $r['id'] . '/edit')) ?>" class="ml-auto grid size-9 place-items-center rounded-xl text-muted hover:bg-soft hover:text-ink" aria-label="Edit"><?= icon('pencil', 'size-4') ?></a>
                        <?= delete_form('/resources/' . $r['id'] . '/delete', 'Delete this resource?', 'Delete', 'grid size-9 place-items-center rounded-xl text-muted hover:bg-rose-50 hover:text-rose-600 dark:hover:bg-rose-500/10', true, $back) ?>
                    </div>
                </article>
            <?php endforeach; ?>
        </div>
        <p x-show="visible() === 0" x-cloak class="card mt-4 px-6 py-10 text-center text-sm text-muted">No resources match “<b x-text="q"></b>”.</p>
    <?php endif; ?>
    </div>
    <?php
    page('Resources', 'resources', (string) ob_get_clean(), ['subtitle' => 'Templates, links & team assets']);
}

function page_resource_form(int $id = 0): void
{
    $r = $id ? q1('SELECT * FROM resources WHERE id=?', [$id]) : null;
    if ($id && !$r) {
        not_found();
    }
    $r ??= ['title' => '', 'type' => input('type') ?: 'link', 'url' => '', 'body' => '', 'tag' => ''];
    $f = field('title', 'Title', 'text', $r['title'], ['req' => true, 'max' => 120])
        . '<div class="grid grid-cols-1 gap-4 sm:grid-cols-2">'
        . field('type', 'Type', 'select', $r['type'], ['options' => array_map(fn($m) => $m['label'], RESOURCE_TYPES)])
        . field('tag', 'Tag', 'text', $r['tag'], ['ph' => 'e.g. Caption, Assets, Brand', 'max' => 40])
        . '</div>'
        . field('url', 'URL (optional)', 'url', $r['url'], ['ph' => 'https://…'])
        . field('body', 'Content / Notes', 'textarea', $r['body'], ['rows' => 6, 'hint' => 'For caption templates and hashtag sets, the “Copy text” button copies this field.']);
    page($id ? 'Edit Resource' : 'New Resource', 'resources', form_shell($id ? "/resources/$id/edit" : '/resources/new', $f, '/resources'), ['subtitle' => 'Resources']);
}

function action_resource_save(int $id = 0): void
{
    $title = mb_substr(input('title'), 0, 120);
    if ($title === '') {
        flash('Title is required.', 'err');
        redirect($id ? "/resources/$id/edit" : '/resources/new');
    }
    $url = input('url');
    if ($url !== '' && !preg_match('#^https?://#i', $url)) {
        $url = 'https://' . $url;
    }
    if ($url !== '' && !filter_var($url, FILTER_VALIDATE_URL)) {
        flash('Invalid URL format.', 'err');
        redirect($id ? "/resources/$id/edit" : '/resources/new');
    }
    $vals = [$title, isset(RESOURCE_TYPES[input('type')]) ? input('type') : 'link', $url, input('body'), mb_substr(input('tag'), 0, 40)];
    if ($id) {
        exec_sql('UPDATE resources SET title=?,type=?,url=?,body=?,tag=? WHERE id=?', [...$vals, $id]);
        flash('Resource updated.');
    } else {
        exec_sql('INSERT INTO resources(title,type,url,body,tag) VALUES(?,?,?,?,?)', $vals);
        flash('Resource added.');
    }
    redirect('/resources');
}

function action_resource_delete(int $id): void
{
    exec_sql('DELETE FROM resources WHERE id=?', [$id]);
    flash('Resource deleted.');
    redirect(safe_back('/resources'));
}
