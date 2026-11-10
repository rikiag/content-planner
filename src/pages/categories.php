<?php
declare(strict_types=1);

defined('ROOT') || (http_response_code(403) && exit);

function detect_color_key(string $cls, string $default = 'blue'): string
{
    foreach (CATEGORY_COLORS as $k => $m) {
        if ($m['chip'] === $cls || str_contains($cls, '-' . $k . '-')) {
            return $k;
        }
    }
    return $default;
}

function categories_state(): array
{
    $pCounts = [];
    foreach (q('SELECT platform, COUNT(*) n FROM contents GROUP BY platform') as $r) {
        $pCounts[$r['platform']] = (int) $r['n'];
    }
    $fCounts = [];
    foreach (q('SELECT format, COUNT(*) n FROM contents GROUP BY format') as $r) {
        $fCounts[$r['format']] = (int) $r['n'];
    }
    $pilCounts = [];
    foreach (q("SELECT pillar, COUNT(*) n FROM contents WHERE pillar != '' GROUP BY pillar") as $r) {
        $pilCounts[$r['pillar']] = (int) $r['n'];
    }
    foreach (q("SELECT pillar, COUNT(*) n FROM ideas WHERE pillar != '' GROUP BY pillar") as $r) {
        $pilCounts[$r['pillar']] = ($pilCounts[$r['pillar']] ?? 0) + (int) $r['n'];
    }
    $sCounts = [];
    foreach (q('SELECT status, COUNT(*) n FROM contents GROUP BY status') as $r) {
        $sCounts[$r['status']] = (int) $r['n'];
    }

    $plats = [];
    foreach (platforms() as $name => $m) {
        $plats[] = [
            'name'  => (string) $name,
            'abbr'  => (string) ($m['abbr'] ?? mb_strtoupper(mb_substr((string) $name, 0, 2))),
            'cls'   => (string) ($m['cls'] ?? CATEGORY_COLORS['blue']['chip']),
            'color' => (string) ($m['color'] ?? detect_color_key((string) ($m['cls'] ?? ''), 'blue')),
            'count' => $pCounts[$name] ?? 0,
        ];
    }

    $fmts = [];
    foreach (formats() as $name) {
        $fmts[] = [
            'name'  => (string) $name,
            'count' => $fCounts[$name] ?? 0,
        ];
    }

    $pils = [];
    foreach (pillars() as $name) {
        $pils[] = [
            'name'  => (string) $name,
            'count' => $pilCounts[$name] ?? 0,
        ];
    }

    $stats = [];
    foreach (statuses() as $key => $m) {
        $stats[] = [
            'key'   => (string) $key,
            'label' => (string) ($m['label'] ?? ucfirst((string) $key)),
            'chip'  => (string) ($m['chip'] ?? CATEGORY_COLORS['slate']['chip']),
            'dot'   => (string) ($m['dot'] ?? CATEGORY_COLORS['slate']['dot']),
            'tint'  => (string) ($m['tint'] ?? CATEGORY_COLORS['slate']['tint']),
            'icon'  => (string) ($m['icon'] ?? 'bulb'),
            'color' => (string) ($m['color'] ?? detect_color_key((string) ($m['chip'] ?? ''), 'slate')),
            'count' => $sCounts[$key] ?? 0,
        ];
    }

    return [
        'platforms' => $plats,
        'formats'   => $fmts,
        'pillars'   => $pils,
        'statuses'  => $stats,
    ];
}

function page_categories(): void
{
    $tab = in_array(input('tab'), ['platform', 'format', 'pillar', 'status'], true) ? input('tab') : 'platform';
    $state = categories_state();
    $colors = CATEGORY_COLORS;
    $iconsSvg = [];
    foreach (STATUS_ICONS as $ic) {
        $iconsSvg[$ic] = icon($ic, 'size-4');
    }

    ob_start(); ?>
    <div x-data="categoryManager(<?= e(json_encode([
        'tab'       => $tab,
        'platforms' => $state['platforms'],
        'formats'   => $state['formats'],
        'pillars'   => $state['pillars'],
        'statuses'  => $state['statuses'],
        'colors'    => $colors,
        'icons'     => STATUS_ICONS,
        'csrf'      => csrf_token(),
        'endpoint'  => url('/categories'),
    ], JSON_UNESCAPED_UNICODE)) ?>)" class="mx-auto max-w-5xl">

        <!-- Category Tabs -->
        <div class="mb-5 flex flex-wrap items-center justify-between gap-3">
            <div class="grid flex-1 grid-cols-2 gap-1.5 rounded-3xl bg-surface p-1.5 shadow-sm sm:flex sm:flex-none" role="tablist" aria-label="Master Data Categories">
                <button type="button" role="tab" :aria-selected="tab === 'platform'" @click="setTab('platform')"
                    class="inline-flex items-center justify-center gap-2 rounded-2xl px-4 py-2 text-xs font-extrabold transition"
                    :class="tab === 'platform' ? 'bg-hero text-white shadow-sm' : 'text-muted hover:text-ink'">
                    <?= icon('smartphone', 'size-4') ?><span>Platform</span>
                    <span class="rounded-full px-1.5 py-0.5 text-[10px]" :class="tab === 'platform' ? 'bg-white/20 text-white' : 'bg-soft text-muted'" x-text="platforms.length"></span>
                </button>
                <button type="button" role="tab" :aria-selected="tab === 'format'" @click="setTab('format')"
                    class="inline-flex items-center justify-center gap-2 rounded-2xl px-4 py-2 text-xs font-extrabold transition"
                    :class="tab === 'format' ? 'bg-hero text-white shadow-sm' : 'text-muted hover:text-ink'">
                    <?= icon('file', 'size-4') ?><span>Format</span>
                    <span class="rounded-full px-1.5 py-0.5 text-[10px]" :class="tab === 'format' ? 'bg-white/20 text-white' : 'bg-soft text-muted'" x-text="formats.length"></span>
                </button>
                <button type="button" role="tab" :aria-selected="tab === 'pillar'" @click="setTab('pillar')"
                    class="inline-flex items-center justify-center gap-2 rounded-2xl px-4 py-2 text-xs font-extrabold transition"
                    :class="tab === 'pillar' ? 'bg-hero text-white shadow-sm' : 'text-muted hover:text-ink'">
                    <?= icon('bulb', 'size-4') ?><span>Pillar</span>
                    <span class="rounded-full px-1.5 py-0.5 text-[10px]" :class="tab === 'pillar' ? 'bg-white/20 text-white' : 'bg-soft text-muted'" x-text="pillars.length"></span>
                </button>
                <button type="button" role="tab" :aria-selected="tab === 'status'" @click="setTab('status')"
                    class="inline-flex items-center justify-center gap-2 rounded-2xl px-4 py-2 text-xs font-extrabold transition"
                    :class="tab === 'status' ? 'bg-hero text-white shadow-sm' : 'text-muted hover:text-ink'">
                    <?= icon('board', 'size-4') ?><span>Status</span>
                    <span class="rounded-full px-1.5 py-0.5 text-[10px]" :class="tab === 'status' ? 'bg-white/20 text-white' : 'bg-soft text-muted'" x-text="statuses.length"></span>
                </button>
            </div>

            <button type="button" class="btn-primary" @click="openCreate()">
                <?= icon('plus', 'size-4') ?><span x-text="'Add ' + tabLabel()"></span>
            </button>
        </div>

        <!-- 1. PLATFORMS -->
        <section x-show="tab === 'platform'" x-transition.opacity class="card p-5">
            <div class="mb-4 flex items-center justify-between">
                <div>
                    <h2 class="section-title">Platforms</h2>
                    <p class="text-xs text-muted">Social networks and channels available when creating content.</p>
                </div>
            </div>
            <div class="grid grid-cols-1 gap-3 sm:grid-cols-2 lg:grid-cols-3">
                <template x-for="p in platforms" :key="p.name">
                    <div class="flex items-center gap-3 rounded-2xl border border-line bg-soft2 p-3.5">
                        <span class="grid size-11 shrink-0 place-items-center rounded-2xl text-xs font-extrabold" :class="p.cls" x-text="p.abbr"></span>
                        <div class="min-w-0 flex-1">
                            <div class="truncate text-sm font-extrabold" x-text="p.name"></div>
                            <div class="text-[11px] text-muted"><span x-text="p.count"></span> posts</div>
                        </div>
                        <div class="flex items-center gap-1">
                            <button type="button" class="grid size-8 place-items-center rounded-xl text-muted hover:bg-soft hover:text-ink" @click="openEditPlatform(p)" aria-label="Edit" title="Edit"><?= icon('pencil', 'size-4') ?></button>
                            <button type="button" class="grid size-8 place-items-center rounded-xl text-muted hover:bg-rose-50 hover:text-rose-600 dark:hover:bg-rose-500/10"
                                @click="removeItem('platform', p.name)" :disabled="platforms.length <= 1" aria-label="Delete" title="Delete"><?= icon('trash', 'size-4') ?></button>
                        </div>
                    </div>
                </template>
            </div>
        </section>

        <!-- 2. FORMATS -->
        <section x-show="tab === 'format'" x-cloak x-transition.opacity class="card p-5">
            <div class="mb-4 flex items-center justify-between">
                <div>
                    <h2 class="section-title">Content Formats</h2>
                    <p class="text-xs text-muted">Post types such as Feed, Carousel, Reels, Story, Video, or Thread.</p>
                </div>
            </div>
            <div class="grid grid-cols-1 gap-3 sm:grid-cols-2 lg:grid-cols-3">
                <template x-for="f in formats" :key="f.name">
                    <div class="flex items-center gap-3 rounded-2xl border border-line bg-soft2 p-3.5">
                        <span class="grid size-10 shrink-0 place-items-center rounded-2xl bg-soft text-brand"><?= icon('file', 'size-4.5') ?></span>
                        <div class="min-w-0 flex-1">
                            <div class="truncate text-sm font-extrabold" x-text="f.name"></div>
                            <div class="text-[11px] text-muted"><span x-text="f.count"></span> posts</div>
                        </div>
                        <div class="flex items-center gap-1">
                            <button type="button" class="grid size-8 place-items-center rounded-xl text-muted hover:bg-soft hover:text-ink" @click="openEditSimple('format', f.name)" aria-label="Edit" title="Edit"><?= icon('pencil', 'size-4') ?></button>
                            <button type="button" class="grid size-8 place-items-center rounded-xl text-muted hover:bg-rose-50 hover:text-rose-600 dark:hover:bg-rose-500/10"
                                @click="removeItem('format', f.name)" :disabled="formats.length <= 1" aria-label="Delete" title="Delete"><?= icon('trash', 'size-4') ?></button>
                        </div>
                    </div>
                </template>
            </div>
        </section>

        <!-- 3. PILLARS -->
        <section x-show="tab === 'pillar'" x-cloak x-transition.opacity class="card p-5">
            <div class="mb-4 flex items-center justify-between">
                <div>
                    <h2 class="section-title">Content Pillars</h2>
                    <p class="text-xs text-muted">Core themes and topics (e.g. Education, Inspiration, Promotion, Community).</p>
                </div>
            </div>
            <div class="grid grid-cols-1 gap-3 sm:grid-cols-2 lg:grid-cols-3">
                <template x-for="pil in pillars" :key="pil.name">
                    <div class="flex items-center gap-3 rounded-2xl border border-line bg-soft2 p-3.5">
                        <span class="grid size-10 shrink-0 place-items-center rounded-2xl bg-amber-100 text-amber-700 dark:bg-amber-500/20 dark:text-amber-300"><?= icon('bulb', 'size-4.5') ?></span>
                        <div class="min-w-0 flex-1">
                            <div class="truncate text-sm font-extrabold" x-text="pil.name"></div>
                            <div class="text-[11px] text-muted"><span x-text="pil.count"></span> items</div>
                        </div>
                        <div class="flex items-center gap-1">
                            <button type="button" class="grid size-8 place-items-center rounded-xl text-muted hover:bg-soft hover:text-ink" @click="openEditSimple('pillar', pil.name)" aria-label="Edit" title="Edit"><?= icon('pencil', 'size-4') ?></button>
                            <button type="button" class="grid size-8 place-items-center rounded-xl text-muted hover:bg-rose-50 hover:text-rose-600 dark:hover:bg-rose-500/10"
                                @click="removeItem('pillar', pil.name)" :disabled="pillars.length <= 1" aria-label="Delete" title="Delete"><?= icon('trash', 'size-4') ?></button>
                        </div>
                    </div>
                </template>
            </div>
        </section>

        <!-- 4. STATUSES -->
        <section x-show="tab === 'status'" x-cloak x-transition.opacity class="card p-5">
            <div class="mb-4 flex items-center justify-between">
                <div>
                    <h2 class="section-title">Workflow Statuses</h2>
                    <p class="text-xs text-muted">Stages in your Content Pipeline and Kanban Board.</p>
                </div>
            </div>
            <div class="grid grid-cols-1 gap-3 sm:grid-cols-2 lg:grid-cols-3">
                <template x-for="s in statuses" :key="s.key">
                    <div class="flex items-center gap-3 rounded-2xl border border-line bg-soft2 p-3.5">
                        <span class="size-3 shrink-0 rounded-full" :class="s.dot"></span>
                        <div class="min-w-0 flex-1">
                            <div class="flex items-center gap-2">
                                <span class="chip" :class="s.chip" x-text="s.label"></span>
                                <code class="text-[10px] text-muted" x-text="s.key"></code>
                            </div>
                            <div class="mt-1 text-[11px] text-muted"><span x-text="s.count"></span> posts</div>
                        </div>
                        <div class="flex items-center gap-1">
                            <button type="button" class="grid size-8 place-items-center rounded-xl text-muted hover:bg-soft hover:text-ink" @click="openEditStatus(s)" aria-label="Edit" title="Edit"><?= icon('pencil', 'size-4') ?></button>
                            <button type="button" class="grid size-8 place-items-center rounded-xl text-muted hover:bg-rose-50 hover:text-rose-600 dark:hover:bg-rose-500/10"
                                @click="removeItem('status', s.key)" :disabled="statuses.length <= 1 || s.key === 'published'"
                                :title="s.key === 'published' ? 'Published status is required for metrics' : 'Delete'" aria-label="Delete"><?= icon('trash', 'size-4') ?></button>
                        </div>
                    </div>
                </template>
            </div>
        </section>

        <!-- Modal for Create / Edit -->
        <div x-show="modal.open" x-cloak x-transition.opacity
            @keydown.escape.window="modal.open = false"
            class="fixed inset-0 z-[85] grid place-items-center bg-black/55 p-4 backdrop-blur-sm" role="dialog" aria-modal="true">
            <div x-show="modal.open" x-transition.scale.95 @click.outside="modal.open = false" class="card w-full max-w-md p-6">
                <div class="mb-4 flex items-center justify-between">
                    <h3 class="text-base font-extrabold" x-text="(modal.orig ? 'Edit ' : 'Add ') + tabLabel()"></h3>
                    <button type="button" class="grid size-8 place-items-center rounded-xl text-muted hover:bg-soft" @click="modal.open = false" aria-label="Close"><?= icon('x', 'size-4') ?></button>
                </div>

                <form @submit.prevent="saveModal()" class="flex flex-col gap-4">
                    <div>
                        <label class="label">Name / Label <span class="text-rose-500">*</span></label>
                        <input type="text" x-ref="nameInput" x-model="modal.name" required maxlength="50" class="input" placeholder="Enter name…">
                    </div>

                    <template x-if="modal.type === 'platform'">
                        <div class="flex flex-col gap-4">
                            <div>
                                <label class="label">Badge Abbreviation (2–4 chars)</label>
                                <input type="text" x-model="modal.abbr" maxlength="4" class="input" placeholder="e.g. IG, TT, YT">
                            </div>
                            <div>
                                <label class="label">Badge Color</label>
                                <div class="flex flex-wrap gap-2">
                                    <template x-for="(cMeta, cKey) in colors" :key="cKey">
                                        <button type="button" @click="modal.color = cKey"
                                            class="chip cursor-pointer transition"
                                            :class="[cMeta.chip, modal.color === cKey ? 'ring-2 ring-brand ring-offset-2 ring-offset-[var(--c-surface)]' : 'opacity-70 hover:opacity-100']"
                                            x-text="cMeta.label"></button>
                                    </template>
                                </div>
                            </div>
                        </div>
                    </template>

                    <template x-if="modal.type === 'status'">
                        <div class="flex flex-col gap-4">
                            <div>
                                <label class="label">Color Theme</label>
                                <div class="flex flex-wrap gap-2">
                                    <template x-for="(cMeta, cKey) in colors" :key="cKey">
                                        <button type="button" @click="modal.color = cKey"
                                            class="chip cursor-pointer transition"
                                            :class="[cMeta.chip, modal.color === cKey ? 'ring-2 ring-brand ring-offset-2 ring-offset-[var(--c-surface)]' : 'opacity-70 hover:opacity-100']"
                                            x-text="cMeta.label"></button>
                                    </template>
                                </div>
                            </div>
                            <div>
                                <label class="label">Icon</label>
                                <select x-model="modal.icon" class="input">
                                    <template x-for="ic in icons" :key="ic">
                                        <option :value="ic" x-text="ic"></option>
                                    </template>
                                </select>
                            </div>
                        </div>
                    </template>

                    <div class="mt-2 flex items-center justify-end gap-2">
                        <button type="button" class="btn-ghost" @click="modal.open = false">Cancel</button>
                        <button type="submit" class="btn-primary" :disabled="busy"><?= icon('check', 'size-4') ?><span>Save</span></button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    <?php
    page('Categories', 'categories', (string) ob_get_clean(), ['subtitle' => 'Manage Platform, Format, Pillar & Status']);
}

function action_categories_save(): void
{
    $type = input('type');
    $op = input('op'); // save | delete
    $orig = input('orig');
    $name = mb_substr(input('name'), 0, 50);

    if (!in_array($type, ['platform', 'format', 'pillar', 'status'], true)) {
        json_out(['ok' => false, 'error' => 'Invalid category type.'], 400);
    }

    if ($type === 'platform') {
        $items = platforms();
        if ($op === 'delete') {
            if (count($items) <= 1 || !isset($items[$orig])) {
                json_out(['ok' => false, 'error' => 'Cannot delete the last platform.'], 400);
            }
            unset($items[$orig]);
            $fallback = (string) array_key_first($items);
            exec_sql('UPDATE contents SET platform = ? WHERE platform = ?', [$fallback, $orig]);
            save_setting('custom_platforms', json_encode($items, JSON_UNESCAPED_UNICODE));
            json_out(['ok' => true, 'msg' => 'Platform deleted.', 'state' => categories_state()]);
        }
        if ($name === '') {
            json_out(['ok' => false, 'error' => 'Platform name is required.'], 400);
        }
        $abbr = mb_strtoupper(mb_substr(input('abbr') ?: $name, 0, 4));
        $color = isset(CATEGORY_COLORS[input('color')]) ? input('color') : 'blue';
        $meta = ['abbr' => $abbr, 'cls' => CATEGORY_COLORS[$color]['chip'], 'color' => $color];

        $newItems = [];
        $replaced = false;
        foreach ($items as $k => $v) {
            if ($orig !== '' && (string) $k === $orig) {
                $newItems[$name] = $meta;
                $replaced = true;
            } else {
                $newItems[$k] = $v;
            }
        }
        if (!$replaced) {
            $newItems[$name] = $meta;
        }
        if ($orig !== '' && $orig !== $name) {
            exec_sql('UPDATE contents SET platform = ? WHERE platform = ?', [$name, $orig]);
        }
        save_setting('custom_platforms', json_encode($newItems, JSON_UNESCAPED_UNICODE));
        json_out(['ok' => true, 'msg' => 'Platform saved.', 'state' => categories_state()]);
    }

    if ($type === 'format') {
        $items = formats();
        if ($op === 'delete') {
            $items = array_values(array_filter($items, fn($x) => $x !== $orig));
            if (!$items) {
                json_out(['ok' => false, 'error' => 'At least one format is required.'], 400);
            }
            exec_sql('UPDATE contents SET format = ? WHERE format = ?', [$items[0], $orig]);
            save_setting('custom_formats', json_encode($items, JSON_UNESCAPED_UNICODE));
            json_out(['ok' => true, 'msg' => 'Format deleted.', 'state' => categories_state()]);
        }
        if ($name === '') {
            json_out(['ok' => false, 'error' => 'Format name is required.'], 400);
        }
        if ($orig !== '') {
            $idx = array_search($orig, $items, true);
            if ($idx !== false) {
                $items[$idx] = $name;
            } else {
                $items[] = $name;
            }
            if ($orig !== $name) {
                exec_sql('UPDATE contents SET format = ? WHERE format = ?', [$name, $orig]);
            }
        } elseif (!in_array($name, $items, true)) {
            $items[] = $name;
        }
        save_setting('custom_formats', json_encode(array_values(array_unique($items)), JSON_UNESCAPED_UNICODE));
        json_out(['ok' => true, 'msg' => 'Format saved.', 'state' => categories_state()]);
    }

    if ($type === 'pillar') {
        $items = pillars();
        if ($op === 'delete') {
            $items = array_values(array_filter($items, fn($x) => $x !== $orig));
            if (!$items) {
                json_out(['ok' => false, 'error' => 'At least one pillar is required.'], 400);
            }
            exec_sql("UPDATE contents SET pillar = '' WHERE pillar = ?", [$orig]);
            exec_sql("UPDATE ideas SET pillar = '' WHERE pillar = ?", [$orig]);
            save_setting('custom_pillars', json_encode($items, JSON_UNESCAPED_UNICODE));
            json_out(['ok' => true, 'msg' => 'Pillar deleted.', 'state' => categories_state()]);
        }
        if ($name === '') {
            json_out(['ok' => false, 'error' => 'Pillar name is required.'], 400);
        }
        if ($orig !== '') {
            $idx = array_search($orig, $items, true);
            if ($idx !== false) {
                $items[$idx] = $name;
            } else {
                $items[] = $name;
            }
            if ($orig !== $name) {
                exec_sql('UPDATE contents SET pillar = ? WHERE pillar = ?', [$name, $orig]);
                exec_sql('UPDATE ideas SET pillar = ? WHERE pillar = ?', [$name, $orig]);
            }
        } elseif (!in_array($name, $items, true)) {
            $items[] = $name;
        }
        save_setting('custom_pillars', json_encode(array_values(array_unique($items)), JSON_UNESCAPED_UNICODE));
        json_out(['ok' => true, 'msg' => 'Pillar saved.', 'state' => categories_state()]);
    }

    if ($type === 'status') {
        $items = statuses();
        if ($op === 'delete') {
            if ($orig === 'published' || count($items) <= 1 || !isset($items[$orig])) {
                json_out(['ok' => false, 'error' => 'Cannot delete this status.'], 400);
            }
            unset($items[$orig]);
            $fallback = (string) array_key_first($items);
            exec_sql('UPDATE contents SET status = ? WHERE status = ?', [$fallback, $orig]);
            save_setting('custom_statuses', json_encode($items, JSON_UNESCAPED_UNICODE));
            json_out(['ok' => true, 'msg' => 'Status deleted.', 'state' => categories_state()]);
        }
        if ($name === '') {
            json_out(['ok' => false, 'error' => 'Status label is required.'], 400);
        }
        $color = isset(CATEGORY_COLORS[input('color')]) ? input('color') : 'violet';
        $icon = in_array(input('icon'), STATUS_ICONS, true) ? input('icon') : 'bulb';
        $cMeta = CATEGORY_COLORS[$color];
        $meta = [
            'label' => $name,
            'chip'  => $cMeta['chip'],
            'dot'   => $cMeta['dot'],
            'tint'  => $cMeta['tint'],
            'icon'  => $icon,
            'color' => $color,
        ];
        if ($orig !== '' && isset($items[$orig])) {
            $items[$orig] = $meta;
        } else {
            $slug = strtolower((string) preg_replace('/[^a-zA-Z0-9]+/', '_', $name));
            $slug = trim($slug, '_') ?: 'status_' . count($items);
            $baseSlug = $slug;
            $n = 2;
            while (isset($items[$slug])) {
                $slug = $baseSlug . '_' . $n++;
            }
            $items[$slug] = $meta;
        }
        save_setting('custom_statuses', json_encode($items, JSON_UNESCAPED_UNICODE));
        json_out(['ok' => true, 'msg' => 'Status saved.', 'state' => categories_state()]);
    }
}
