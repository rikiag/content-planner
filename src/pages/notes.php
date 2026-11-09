<?php
declare(strict_types=1);

defined('ROOT') || (http_response_code(403) && exit);

function page_notes(): void
{
    $rows = q('SELECT * FROM notes ORDER BY pinned DESC, updated_at DESC, id DESC');
    ob_start(); ?>
    <form method="post" action="<?= e(url('/notes')) ?>" class="card mb-5 flex flex-col gap-3 p-4 sm:p-5">
        <?= csrf_field() ?>
        <label for="nb" class="sr-only">New note</label>
        <textarea id="nb" name="body" required rows="3" maxlength="2000" placeholder="Write a quick note… (caption ideas, reminders, meeting takeaways)" class="input resize-y" <?= input('new') ? 'autofocus' : '' ?>></textarea>
        <div class="flex flex-wrap items-center gap-3">
            <fieldset class="flex items-center gap-2"><legend class="sr-only">Color</legend>
                <?php foreach (NOTE_COLORS as $k => $cls): ?>
                    <label class="cursor-pointer"><input type="radio" name="color" value="<?= $k ?>" class="peer sr-only" <?= $k === 'yellow' ? 'checked' : '' ?>><span class="block size-7 rounded-full <?= $cls ?> ring-1 ring-black/10 ring-offset-2 ring-offset-[var(--c-surface)] peer-checked:ring-2 peer-checked:ring-brand"></span><span class="sr-only"><?= $k ?></span></label>
                <?php endforeach; ?>
            </fieldset>
            <button class="btn-primary ml-auto"><?= icon('plus', 'size-4') ?>Add note</button>
        </div>
    </form>

    <?php if (!$rows): ?>
        <?= empty_state('note', 'No notes yet', 'Jot down a quick note above. Pinned notes stay at the top and appear in the sidebar.') ?>
    <?php else: ?>
        <div class="columns-1 gap-4 sm:columns-2 xl:columns-3">
            <?php foreach ($rows as $n): ?>
                <article class="mb-4 break-inside-avoid rounded-3xl p-4 shadow-card <?= NOTE_COLORS[$n['color']] ?? NOTE_COLORS['yellow'] ?>">
                    <form method="post" action="<?= e(url('/notes/' . $n['id'])) ?>">
                        <?= csrf_field() ?>
                        <textarea name="body" rows="<?= clamp(substr_count($n['body'], "\n") + 3, 3, 12) ?>" maxlength="2000" aria-label="Note content" class="w-full resize-none border-0 bg-transparent p-0 text-sm leading-relaxed focus:outline-none" @change="$el.form.requestSubmit()"><?= e($n['body']) ?></textarea>
                        <button class="sr-only">Save</button>
                    </form>
                    <div class="mt-2 flex items-center gap-1 border-t border-black/10 pt-2 dark:border-white/10">
                        <span class="text-[10px] font-semibold opacity-70"><?= e(fmt_dt($n['updated_at'])) ?></span>
                        <form method="post" action="<?= e(url('/notes/' . $n['id'] . '/pin')) ?>" class="ml-auto"><?= csrf_field() ?><button class="grid size-8 place-items-center rounded-lg hover:bg-black/10 <?= $n['pinned'] ? 'text-accent' : 'opacity-70' ?>" aria-label="<?= $n['pinned'] ? 'Unpin' : 'Pin' ?>" aria-pressed="<?= $n['pinned'] ? 'true' : 'false' ?>"><?= icon('pin', 'size-4') ?></button></form>
                        <?= delete_form('/notes/' . $n['id'] . '/delete', 'Delete this note?', 'Delete', 'grid size-8 place-items-center rounded-lg opacity-70 hover:bg-black/10', true) ?>
                    </div>
                </article>
            <?php endforeach; ?>
        </div>
    <?php endif;
    page('Notes', 'notes', (string) ob_get_clean(), ['subtitle' => 'Quick team & studio notes']);
}

function action_note_add(): void
{
    $b = mb_substr(input('body'), 0, 2000);
    if ($b !== '') {
        exec_sql('INSERT INTO notes(body,color) VALUES(?,?)', [$b, isset(NOTE_COLORS[input('color')]) ? input('color') : 'yellow']);
        flash('Note saved.');
    }
    redirect(safe_back('/notes'));
}

function action_note_update(int $id): void
{
    $b = mb_substr(input('body'), 0, 2000);
    if ($b === '') {
        flash('Empty notes cannot be saved. Use the delete button instead.', 'err');
    } else {
        exec_sql("UPDATE notes SET body=?, updated_at=datetime('now','localtime') WHERE id=?", [$b, $id]);
        flash('Note updated.');
    }
    redirect('/notes');
}

function action_note_pin(int $id): void
{
    exec_sql('UPDATE notes SET pinned = 1 - pinned WHERE id=?', [$id]);
    redirect('/notes');
}

function action_note_delete(int $id): void
{
    exec_sql('DELETE FROM notes WHERE id=?', [$id]);
    flash('Note deleted.');
    redirect('/notes');
}
