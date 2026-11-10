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

function idea_payload(array $i): array
{
    return [
        'id'           => (int) $i['id'],
        'title'        => (string) $i['title'],
        'notes'        => (string) ($i['notes'] ?? ''),
        'pillar'       => (string) ($i['pillar'] ?? ''),
        'priority'     => (string) ($i['priority'] ?? 'medium'),
        'priority_lbl' => PRIORITIES[$i['priority'] ?? 'medium'] ?? (string) $i['priority'],
        'priority_cls' => PRIORITY_CLS[$i['priority'] ?? 'medium'] ?? '',
        'status'       => (string) ($i['status'] ?? 'new'),
    ];
}

function page_ideas(): void
{
    $f = input('status');
    $pillar = input('pillar');
    $allPillars = pillars();

    $rows = q("SELECT * FROM ideas ORDER BY status='used', CASE priority WHEN 'high' THEN 0 WHEN 'medium' THEN 1 ELSE 2 END, id DESC");
    $items = array_map('idea_payload', $rows);

    $iStatuses = [];
    foreach (IDEA_STATUSES as $k => $m) {
        $iStatuses[] = ['key' => $k, 'label' => $m['label'], 'chip' => $m['chip']];
    }

    $config = [
        'items'    => $items,
        'statuses' => $iStatuses,
        'pillars'  => $allPillars,
        'status'   => isset(IDEA_STATUSES[$f]) ? $f : '',
        'pillar'   => in_array($pillar, $allPillars, true) ? $pillar : '',
        'q'        => '',
        'csrf'     => csrf_token(),
        'baseUrl'  => url(''),
    ];

    ob_start(); ?>
    <div x-data="ideaManager(<?= e(json_encode($config, JSON_UNESCAPED_UNICODE)) ?>)">
        <!-- Quick Add Idea Form -->
        <form method="post" action="<?= e(url('/ideas')) ?>" @submit.prevent="quickAdd()" class="card mb-4 flex flex-col gap-2 p-3 sm:flex-row sm:items-center sm:p-4">
            <?= csrf_field() ?>
            <span class="hidden size-10 shrink-0 place-items-center rounded-2xl bg-amber-100 text-amber-600 sm:grid dark:bg-amber-400/15"><?= icon('bulb', 'size-5') ?></span>
            <input name="title" x-model="newTitle" required maxlength="160" placeholder="Capture a new idea before it slips away…" class="input flex-1" aria-label="New idea">
            <select name="pillar" x-model="newPillar" class="input sm:!w-44" aria-label="Pillar">
                <option value="">Pillar…</option>
                <template x-for="p in pillars" :key="p">
                    <option :value="p" x-text="p"></option>
                </template>
            </select>
            <button type="submit" class="btn-primary" :disabled="busy"><?= icon('plus', 'size-4') ?>Save idea</button>
        </form>

        <!-- Search Filter -->
        <label class="relative mb-3 block">
            <span class="sr-only">Filter ideas</span>
            <span class="pointer-events-none absolute left-3.5 top-1/2 -translate-y-1/2 text-muted"><?= icon('search', 'size-4') ?></span>
            <input type="search" x-model="q" placeholder="Filter displayed ideas…" class="input !pl-10">
        </label>

        <!-- Status & Pillar Filter Pills -->
        <div class="-mx-4 mb-4 flex gap-2 overflow-x-auto px-4 pb-1 no-scrollbar sm:mx-0 sm:flex-wrap sm:px-0">
            <button type="button" class="pill" :aria-current="status === '' ? 'true' : 'false'" @click="setStatus('')">
                All <b x-text="items.length"></b>
            </button>
            <template x-for="st in statuses" :key="st.key">
                <button type="button" class="pill" :aria-current="status === st.key ? 'true' : 'false'" @click="setStatus(st.key)">
                    <span x-text="st.label"></span> <b x-text="countByStatus(st.key)"></b>
                </button>
            </template>
            <span class="mx-1 hidden w-px bg-line sm:block"></span>
            <template x-for="p in pillars" :key="p">
                <button type="button" class="pill" :aria-current="pillar === p ? 'true' : 'false'" @click="togglePillar(p)" x-text="p"></button>
            </template>
        </div>

        <template x-if="filtered().length === 0">
            <div class="card grid place-items-center gap-2 px-6 py-12 text-center">
                <div class="grid size-14 place-items-center rounded-2xl bg-soft text-brand"><?= icon('bulb', 'size-7') ?></div>
                <h3 class="text-base font-extrabold">No ideas found</h3>
                <p class="max-w-sm text-sm text-muted">Jot down anything that comes to mind. Convert ideas into scheduled posts with a single click.</p>
            </div>
        </template>

        <div x-show="filtered().length > 0" class="grid grid-cols-1 gap-4 sm:grid-cols-2 xl:grid-cols-3">
            <template x-for="item in filtered()" :key="item.id">
                <article class="card flex flex-col gap-3 p-5 transition" :class="item.status === 'used' ? 'opacity-70' : ''">
                    <div class="flex flex-wrap items-center gap-2">
                        <!-- Alpine inline status selector -->
                        <select :value="item.status" @change="updateIdea(item, { status: $event.target.value })" aria-label="Idea status"
                            class="cursor-pointer appearance-none rounded-full border-0 py-1 pl-2.5 pr-2.5 text-[11px] font-bold focus:outline-2 focus:outline-brand"
                            :class="statusChip(item.status)">
                            <template x-for="st in statuses" :key="st.key">
                                <option :value="st.key" :selected="st.key === item.status" class="bg-surface text-ink" x-text="st.label"></option>
                            </template>
                        </select>

                        <!-- Alpine inline pillar selector -->
                        <select :value="item.pillar" @change="updateIdea(item, { pillar: $event.target.value })" aria-label="Idea pillar"
                            class="cursor-pointer appearance-none rounded-full border-0 bg-soft py-1 pl-2.5 pr-2.5 text-[11px] font-semibold text-muted focus:outline-2 focus:outline-brand">
                            <option value="" class="bg-surface text-ink">No pillar</option>
                            <template x-for="p in pillars" :key="p">
                                <option :value="p" :selected="p === item.pillar" class="bg-surface text-ink" x-text="p"></option>
                            </template>
                        </select>

                        <span class="ml-auto text-[11px] font-bold" :class="item.priority_cls">● <span x-text="item.priority_lbl"></span></span>
                    </div>

                    <h2 class="text-[15px] font-extrabold leading-snug" x-text="item.title"></h2>
                    <template x-if="item.notes">
                        <p class="line-clamp-3 text-xs leading-relaxed text-muted" x-text="item.notes"></p>
                    </template>

                    <div class="mt-auto flex flex-wrap items-center gap-1.5 pt-1">
                        <template x-if="item.status !== 'used'">
                            <div class="flex items-center gap-1.5">
                                <form method="post" :action="baseUrl + '/ideas/' + item.id + '/convert'">
                                    <?= csrf_field() ?>
                                    <button class="btn-dark !px-3.5 !py-2 !text-xs"><?= icon('calendar', 'size-3.5') ?>Turn into post</button>
                                </form>
                                <button type="button" @click="updateIdea(item, { status: item.status === 'saved' ? 'new' : 'saved' })"
                                    class="grid size-9 place-items-center rounded-xl text-muted hover:bg-soft hover:text-ink"
                                    :class="item.status === 'saved' ? '!text-amber-500' : ''"
                                    :aria-label="item.status === 'saved' ? 'Unsave' : 'Save'"
                                    :title="item.status === 'saved' ? 'Unsave' : 'Save'">
                                    <?= icon('bookmark', 'size-4') ?>
                                </button>
                            </div>
                        </template>
                        <a :href="baseUrl + '/ideas/' + item.id + '/edit'" class="ml-auto grid size-9 place-items-center rounded-xl text-muted hover:bg-soft hover:text-ink" aria-label="Edit"><?= icon('pencil', 'size-4') ?></a>
                        <button type="button" @click="deleteIdea(item)" class="grid size-9 place-items-center rounded-xl text-muted hover:bg-rose-50 hover:text-rose-600 dark:hover:bg-rose-500/10" aria-label="Delete" title="Delete"><?= icon('trash', 'size-4') ?></button>
                    </div>
                </article>
            </template>
        </div>
    </div>
    <?php
    page('Idea Bank', 'idea', (string) ob_get_clean(), ['subtitle' => 'Capture & organize content ideas']);
}

function page_idea_form(int $id): void
{
    $i = q1('SELECT * FROM ideas WHERE id=?', [$id]) ?? not_found();
    $allPillars = pillars();
    $f = field('title', 'Idea', 'text', $i['title'], ['req' => true, 'max' => 160])
        . field('notes', 'Notes / Outline', 'textarea', $i['notes'], ['rows' => 5])
        . '<div class="grid grid-cols-1 gap-4 sm:grid-cols-3">'
        . field('pillar', 'Pillar', 'select', $i['pillar'], ['options' => ['' => '— Select —'] + array_combine($allPillars, $allPillars)])
        . field('priority', 'Priority', 'select', $i['priority'], ['options' => PRIORITIES])
        . field('status', 'Status', 'select', $i['status'], ['options' => array_map(fn($s) => $s['label'], IDEA_STATUSES)])
        . '</div>';
    page('Edit Idea', 'idea', form_shell("/ideas/$id/edit", $f, '/ideas'), ['subtitle' => 'Idea Bank']);
}

function action_idea_quick_add(): void
{
    $t = mb_substr(input('title'), 0, 160);
    if ($t === '') {
        if (is_ajax()) {
            json_out(['ok' => false, 'error' => 'Please enter an idea first.'], 400);
        }
        flash('Please enter an idea first.', 'err');
        redirect(safe_back('/ideas'));
    }
    $allPillars = pillars();
    $pil = in_array(input('pillar'), $allPillars, true) ? input('pillar') : '';
    exec_sql('INSERT INTO ideas(title,pillar) VALUES(?,?)', [$t, $pil]);
    $newId = (int) db()->lastInsertId();
    if (is_ajax()) {
        $row = q1('SELECT * FROM ideas WHERE id=?', [$newId]);
        json_out(['ok' => true, 'msg' => 'Idea saved to Idea Bank.', 'item' => idea_payload($row)]);
    }
    flash('Idea saved to Idea Bank.');
    redirect(safe_back('/ideas'));
}

function action_idea_save(int $id): void
{
    q1('SELECT 1 FROM ideas WHERE id=?', [$id]) ?? not_found();
    $t = mb_substr(input('title'), 0, 160);
    if ($t === '') {
        flash('Idea title cannot be empty.', 'err');
        redirect("/ideas/$id/edit");
    }
    $allPillars = pillars();
    exec_sql('UPDATE ideas SET title=?, notes=?, pillar=?, priority=?, status=? WHERE id=?', [
        $t, input('notes'), in_array(input('pillar'), $allPillars, true) ? input('pillar') : '',
        isset(PRIORITIES[input('priority')]) ? input('priority') : 'medium',
        isset(IDEA_STATUSES[input('status')]) ? input('status') : 'new', $id,
    ]);
    flash('Idea updated.');
    redirect('/ideas');
}

function action_idea_status(int $id): void
{
    $row = q1('SELECT * FROM ideas WHERE id=?', [$id]);
    if (!$row) {
        if (is_ajax()) {
            json_out(['ok' => false, 'error' => 'Idea not found.'], 404);
        }
        not_found();
    }
    $allPillars = pillars();
    $newStatus = isset($_POST['status']) && isset(IDEA_STATUSES[input('status')]) ? input('status') : $row['status'];
    $newPillar = isset($_POST['pillar']) ? (in_array(input('pillar'), $allPillars, true) ? input('pillar') : '') : $row['pillar'];
    exec_sql('UPDATE ideas SET status=?, pillar=? WHERE id=?', [$newStatus, $newPillar, $id]);

    if (is_ajax()) {
        $updated = q1('SELECT * FROM ideas WHERE id=?', [$id]);
        json_out(['ok' => true, 'msg' => 'Idea updated.', 'item' => idea_payload($updated)]);
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
    if (is_ajax()) {
        json_out(['ok' => true, 'msg' => 'Idea deleted.']);
    }
    flash('Idea deleted.');
    redirect(safe_back('/ideas'));
}
