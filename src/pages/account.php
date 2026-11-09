<?php
declare(strict_types=1);

defined('ROOT') || (http_response_code(403) && exit);

function page_account(): void
{
    $stats = [
        ['Content', (int) qv('SELECT COUNT(*) FROM contents')],
        ['Campaigns', (int) qv('SELECT COUNT(*) FROM campaigns')],
        ['Ideas', (int) qv('SELECT COUNT(*) FROM ideas')],
        ['Resources', (int) qv('SELECT COUNT(*) FROM resources')],
    ];
    $themes = [['system', 'System', 'monitor'], ['light', 'Light', 'sun'], ['dark', 'Dark', 'moon']];

    ob_start(); ?>
    <div class="mx-auto grid grid-cols-1 max-w-5xl gap-5 lg:grid-cols-[1fr_22rem]">
        <div class="flex flex-col gap-5">
            <section class="card p-5 sm:p-7" aria-labelledby="prof-t">
                <div class="mb-5 flex items-center gap-4">
                    <span class="grid size-16 place-items-center rounded-3xl bg-linear-to-br from-blue-500 to-indigo-600 text-xl font-extrabold text-white shadow-md"><?= e(initials(setting('name', 'A'))) ?></span>
                    <div><h2 id="prof-t" class="text-lg font-extrabold"><?= e(setting('name')) ?></h2><p class="text-xs text-muted"><?= e(setting('role')) ?> · <?= e(setting('studio')) ?></p><p class="text-xs text-muted"><?= icon('mail', 'size-3.5 inline -mt-0.5') ?> <?= e(current_user()['email'] ?? '') ?></p></div>
                </div>
                <form method="post" action="<?= e(url('/account')) ?>" class="flex flex-col gap-4">
                    <?= csrf_field() ?>
                    <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                        <?= field('name', 'Display name', 'text', setting('name'), ['req' => true, 'max' => 40]) ?>
                        <?= field('role', 'Role', 'text', setting('role'), ['max' => 60]) ?>
                        <?= field('studio', 'Studio / brand name', 'text', setting('studio'), ['max' => 60]) ?>
                        <?= field('handle', 'Primary social handle', 'text', setting('handle'), ['max' => 60, 'ph' => '@yourhandle']) ?>
                        <?= field('weekly_target', 'Weekly posting goal', 'number', setting('weekly_target', '4'), ['min' => 1, 'max' => 50]) ?>
                        <?= field('monthly_target', 'Monthly content goal', 'number', setting('monthly_target', '13'), ['min' => 1, 'max' => 300]) ?>
                    </div>
                    <div class="flex justify-end"><button class="btn-primary"><?= icon('check', 'size-4') ?>Save profile</button></div>
                </form>
            </section>

            <section class="card p-5 sm:p-7" aria-labelledby="sec-t" x-data="{ show: false, pw: '', pw2: '' }">
                <h2 id="sec-t" class="section-title">Security</h2>
                <p class="mb-4 mt-1 text-xs text-muted">Change your password. Sessions on other devices will remain active until signed out.</p>
                <form method="post" action="<?= e(url('/account/password')) ?>" class="flex flex-col gap-4">
                    <?= csrf_field() ?>
                    <?= auth_input('current', 'Current password', 'password', 'lock', ['req' => true, 'ac' => 'current-password']) ?>
                    <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                        <?= auth_input('password', 'New password', 'password', 'key', ['req' => true, 'ac' => 'new-password', 'attrs' => 'minlength="8" x-model="pw"']) ?>
                        <?= auth_input('password2', 'Confirm new password', 'password', 'key', ['req' => true, 'ac' => 'new-password', 'attrs' => 'x-model="pw2"', 'after' => '<p class="mt-1 text-[11px] font-semibold text-rose-600" x-show="pw2 && pw !== pw2" x-cloak>Passwords do not match.</p>']) ?>
                    </div>
                    <div class="flex justify-end"><button class="btn-primary" :disabled="pw2 && pw !== pw2"><?= icon('check', 'size-4') ?>Update password</button></div>
                </form>
            </section>

            <section class="card p-5 sm:p-7" aria-labelledby="theme-t">
                <h2 id="theme-t" class="section-title">Appearance</h2>
                <p class="mb-4 mt-1 text-xs text-muted">Saved on this device (localStorage). Choosing “System” follows your operating system light/dark preference.</p>
                <div class="grid grid-cols-3 gap-3" role="radiogroup" aria-label="Theme" x-data>
                    <?php foreach ($themes as [$v, $l, $ic]): ?>
                        <label class="cursor-pointer">
                            <input type="radio" name="theme" value="<?= $v ?>" class="peer sr-only" :checked="$store.theme.pref === '<?= $v ?>'" @change="$store.theme.set('<?= $v ?>')">
                            <span class="flex flex-col items-center gap-2 rounded-2xl border-2 border-line bg-soft2 px-2 py-4 text-center text-xs font-bold transition peer-checked:border-brand peer-checked:bg-brandsoft peer-focus-visible:ring-2 peer-focus-visible:ring-brand"><?= icon($ic, 'size-6') ?><?= e($l) ?></span>
                        </label>
                    <?php endforeach; ?>
                </div>
            </section>
        </div>

        <div class="flex flex-col gap-5">
            <section class="card p-5" aria-labelledby="pwa-t">
                <span class="grid size-11 place-items-center rounded-2xl bg-brandsoft text-brand"><?= icon('smartphone', 'size-5') ?></span>
                <h2 id="pwa-t" class="section-title mt-3">Install as app</h2>
                <p class="mt-1 text-xs leading-relaxed text-muted">Launch from your home screen or desktop dock without browser chrome, with offline access to previously visited pages.</p>
                <div x-data>
                    <button type="button" x-show="$store.pwa.can" x-cloak class="btn-primary mt-3 w-full" @click="$store.pwa.install()"><?= icon('download', 'size-4') ?>Install app</button>
                </div>
                <p class="mt-3 text-[11px] leading-relaxed text-muted">If the button doesn’t appear: in Chrome/Edge open menu ⋮ → <b>Install app</b>; in Safari on iPhone/iPad tap Share → <b>Add to Home Screen</b>. PWA requires HTTPS (except on localhost).</p>
            </section>

            <section class="card p-5" aria-labelledby="data-t">
                <span class="grid size-11 place-items-center rounded-2xl bg-emerald-100 text-emerald-600 dark:bg-emerald-500/20"><?= icon('database', 'size-5') ?></span>
                <h2 id="data-t" class="section-title mt-3">Workspace data</h2>
                <dl class="mt-3 grid grid-cols-4 gap-2 text-center">
                    <?php foreach ($stats as [$l, $n]): ?><div class="rounded-2xl bg-soft2 py-2"><dt class="text-[10px] text-muted"><?= $l ?></dt><dd class="text-base font-extrabold"><?= $n ?></dd></div><?php endforeach; ?>
                </dl>
                <div class="mt-4 flex flex-col gap-2">
                    <a href="<?= e(url('/export/backup.json')) ?>" class="btn-soft"><?= icon('download', 'size-4') ?>Download backup (JSON)</a>
                    <form method="post" action="<?= e(url('/account/reset')) ?>" x-confirm="'All existing workspace data will be replaced with sample data. Continue?'"><?= csrf_field() ?><button class="btn-ghost w-full"><?= icon('refresh', 'size-4') ?>Load sample data</button></form>
                    <form method="post" action="<?= e(url('/account/wipe')) ?>" x-confirm="'Delete ALL content, campaigns, ideas, resources, and notes? This action cannot be undone.'"><?= csrf_field() ?><button class="btn-ghost w-full !text-rose-600"><?= icon('trash', 'size-4') ?>Clear all data</button></form>
                </div>
            </section>

            <section class="card border border-rose-200 p-5 dark:border-rose-500/30" aria-labelledby="del-t" x-data="{ open: false }">
                <h2 id="del-t" class="section-title text-rose-600 dark:text-rose-400">Danger zone</h2>
                <p class="mt-1 text-xs text-muted">Deleting your account permanently removes your profile and all workspace data.</p>
                <button type="button" class="btn-ghost mt-3 w-full !text-rose-600" @click="open = !open" :aria-expanded="open">Delete my account</button>
                <form x-show="open" x-cloak x-transition method="post" action="<?= e(url('/account/delete')) ?>" class="mt-3 flex flex-col gap-3" x-data="{ show: false }" x-confirm="'Your account and all workspace data will be permanently deleted. Continue?'">
                    <?= csrf_field() ?>
                    <?= auth_input('password', 'Enter your password to confirm', 'password', 'lock', ['id' => 'a-del-password', 'req' => true, 'ac' => 'current-password']) ?>
                    <button class="btn-danger w-full"><?= icon('trash', 'size-4') ?>Delete account permanently</button>
                </form>
            </section>
        </div>
    </div>
    <?php
    page('Account', 'account', (string) ob_get_clean(), ['subtitle' => 'Profile, appearance & data']);
}

function action_account_save(): void
{
    $name = mb_substr(input('name'), 0, 40);
    if ($name === '') {
        flash('Display name cannot be empty.', 'err');
        redirect('/account');
    }
    save_setting('name', $name);
    auth_exec('UPDATE users SET name = ? WHERE id = ?', [$name, (int) current_user()['id']]);
    save_setting('role', mb_substr(input('role'), 0, 60));
    save_setting('studio', mb_substr(input('studio'), 0, 60));
    save_setting('handle', mb_substr(input('handle'), 0, 60));
    save_setting('weekly_target', (string) clamp((int) input('weekly_target', '4'), 1, 50));
    save_setting('monthly_target', (string) clamp((int) input('monthly_target', '13'), 1, 300));
    flash('Profile saved.');
    redirect('/account');
}

function action_account_reset(): void
{
    reset_demo();
    flash('Sample data loaded.');
    redirect('/');
}

function action_account_wipe(): void
{
    db()->exec('DELETE FROM contents; DELETE FROM campaigns; DELETE FROM ideas; DELETE FROM resources; DELETE FROM notes;');
    flash('All workspace data cleared.');
    redirect('/');
}

function action_account_password(): void
{
    $u = current_user();
    $row = auth_q('SELECT password_hash FROM users WHERE id = ?', [(int) $u['id']])[0] ?? null;
    $key = attempt_key('chpw', (string) $u['id']);
    if (attempts_count($key) >= MAX_ATTEMPTS) {
        flash('Too many attempts. Please try again in 15 minutes.', 'err');
        redirect('/account');
    }
    if (!$row || !password_verify((string) ($_POST['current'] ?? ''), $row['password_hash'])) {
        attempts_add($key);
        flash('Current password is incorrect.', 'err');
        redirect('/account');
    }
    $err = password_error((string) ($_POST['password'] ?? ''), (string) ($_POST['password2'] ?? ''));
    if ($err !== null) {
        flash($err, 'err');
        redirect('/account');
    }
    auth_exec('UPDATE users SET password_hash = ? WHERE id = ?', [password_hash((string) $_POST['password'], PASSWORD_DEFAULT), (int) $u['id']]);
    auth_exec('DELETE FROM attempts WHERE k = ?', [$key]);
    $_SESSION['csrf'] = bin2hex(random_bytes(24));
    session_regenerate_id(true);
    flash('Password updated.');
    redirect('/account');
}

function action_account_delete(): void
{
    $u = current_user();
    $row = auth_q('SELECT password_hash FROM users WHERE id = ?', [(int) $u['id']])[0] ?? null;
    $key = attempt_key('delacc', (string) $u['id']);
    if (attempts_count($key) >= MAX_ATTEMPTS || !$row || !password_verify((string) ($_POST['password'] ?? ''), $row['password_hash'])) {
        attempts_add($key);
        flash('Incorrect password. Account was not deleted.', 'err');
        redirect('/account');
    }
    $id = (int) $u['id'];
    auth_exec('DELETE FROM users WHERE id = ?', [$id]);
    db()->exec('PRAGMA wal_checkpoint(TRUNCATE)');
    delete_workspace($id);
    logout_user();
    flash('Your account has been deleted.');
    redirect('/login');
}
