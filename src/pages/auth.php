<?php
declare(strict_types=1);

defined('ROOT') || (http_response_code(403) && exit);

/** Input field for auth pages (left icon + optional password visibility toggle) */
function auth_input(string $name, string $label, string $type, string $icon, array $o = []): string
{
    $pw = $type === 'password';
    $id = $o['id'] ?? 'a-' . $name;
    $html = '<div><label for="' . e($id) . '" class="label">' . e($label) . '</label><div class="relative">'
        . '<span class="pointer-events-none absolute left-3.5 top-1/2 -translate-y-1/2 text-muted">' . icon($icon, 'size-4.5') . '</span>'
        . '<input id="' . e($id) . '" name="' . $name . '" ' . ($pw ? ':type="show ? \'text\' : \'password\'"' : 'type="' . $type . '"')
        . ' value="' . e($o['value'] ?? '') . '" class="input !pl-11' . ($pw ? ' !pr-12' : '') . '"'
        . (!empty($o['req']) ? ' required' : '') . (isset($o['ac']) ? ' autocomplete="' . $o['ac'] . '"' : '')
        . (isset($o['ph']) ? ' placeholder="' . e($o['ph']) . '"' : '') . ' ' . ($o['attrs'] ?? '') . '>';
    if ($pw) {
        $html .= '<button type="button" class="absolute right-2 top-1/2 grid size-9 -translate-y-1/2 place-items-center rounded-xl text-muted hover:bg-soft hover:text-ink" @click="show = !show" :aria-label="show ? \'Hide password\' : \'Show password\'">'
            . '<span x-show="!show">' . icon('eye', 'size-4.5') . '</span><span x-show="show" x-cloak>' . icon('eye-off', 'size-4.5') . '</span></button>';
    }
    return $html . '</div>' . ($o['after'] ?? '') . '</div>';
}

function page_login(): void
{
    if (current_user()) {
        redirect('/');
    }
    $body = '<h2 class="text-xl font-extrabold">Sign In</h2><p class="mb-5 mt-1 text-sm text-muted">Welcome back. Continue planning your content.</p>'
        . '<form method="post" action="' . e(url('/login')) . '" class="flex flex-col gap-4" x-data="{ show: false, busy: false }" @submit="busy = true">' . csrf_field()
        . auth_input('email', 'Email', 'email', 'mail', ['value' => input('email'), 'req' => true, 'ac' => 'username', 'ph' => 'name@example.com', 'attrs' => 'inputmode="email" autocapitalize="none" spellcheck="false" autofocus'])
        . auth_input('password', 'Password', 'password', 'key', ['req' => true, 'ac' => 'current-password', 'ph' => '••••••••'])
        . '<label class="flex cursor-pointer items-center gap-2 text-sm"><input type="checkbox" name="remember" value="1" class="size-4 rounded accent-[var(--c-brand)]" checked> Remember me on this device (30 days)</label>'
        . '<button class="btn-primary w-full !py-3" :disabled="busy"><span x-show="!busy">Sign In</span><span x-show="busy" x-cloak>Signing in…</span></button></form>'
        . '<p class="mt-5 text-center text-sm text-muted">Don’t have an account? <a href="' . e(url('/register')) . '" class="font-bold text-brand hover:underline">Create a free account</a></p>';
    auth_page('Sign In', $body);
}

function action_login(): void
{
    $email = normalize_email(input('email'));
    $pw = (string) ($_POST['password'] ?? '');
    $key = attempt_key('login', $email);

    if (attempts_count($key) >= MAX_ATTEMPTS) {
        flash('Too many failed attempts. Please try again in 15 minutes.', 'err');
        redirect('/login');
    }
    $rows = auth_q('SELECT id, password_hash FROM users WHERE email = ?', [$email]);
    $user = $rows[0] ?? null;
    $ok = password_verify($pw, $user['password_hash'] ?? dummy_hash()) && $user !== null;

    if (!$ok) {
        attempts_add($key);
        flash('Invalid email or password.', 'err');
        redirect('/login?email=' . rawurlencode($email));
    }
    auth_exec('DELETE FROM attempts WHERE k = ?', [$key]);
    if (password_needs_rehash($user['password_hash'], PASSWORD_DEFAULT)) {
        auth_exec('UPDATE users SET password_hash = ? WHERE id = ?', [password_hash($pw, PASSWORD_DEFAULT), $user['id']]);
    }
    $next = $_SESSION['next'] ?? '/';
    login_user((int) $user['id'], input('remember') === '1');
    flash('Signed in successfully.');
    redirect(is_string($next) && str_starts_with($next, '/') && !str_starts_with($next, '//') ? $next : '/');
}

function page_register(): void
{
    if (current_user()) {
        redirect('/');
    }
    $strength = <<<'JS'
{ show: false, busy: false, pw: '', pw2: '',
  get score() { const p = this.pw; if (!p) return 0; let s = 0; if (p.length >= 8) s++; if (p.length >= 12) s++; if (/[A-Za-z]/.test(p) && /\d/.test(p)) s++; if (/[^A-Za-z0-9]/.test(p) || (/[a-z]/.test(p) && /[A-Z]/.test(p))) s++; return Math.min(s, 4); },
  get label() { return ['', 'Weak', 'Fair', 'Good', 'Strong'][this.score]; },
  get color() { return ['bg-track', 'bg-rose-500', 'bg-amber-500', 'bg-sky-500', 'bg-emerald-500'][this.score]; },
  get mismatch() { return this.pw2 !== '' && this.pw !== this.pw2; } }
JS;
    $meter = '<div class="mt-2" x-show="pw" x-cloak><div class="flex gap-1"><template x-for="i in 4"><span class="h-1.5 flex-1 rounded-full transition-colors" :class="i <= score ? color : \'bg-track\'"></span></template></div>'
        . '<p class="mt-1 text-[11px] text-muted">Strength: <b x-text="label"></b> · at least 8 characters with letters &amp; numbers</p></div>';
    $match = '<p class="mt-1 text-[11px] font-semibold text-rose-600" x-show="mismatch" x-cloak>Passwords do not match yet.</p>';

    $body = '<h2 class="text-xl font-extrabold">Create Account</h2><p class="mb-5 mt-1 text-sm text-muted">Free. Your workspace is isolated and accessible only by you.</p>'
        . '<form method="post" action="' . e(url('/register')) . '" class="flex flex-col gap-4" x-data=\'' . e($strength) . '\' @submit="if (mismatch) { $event.preventDefault(); return; } busy = true">' . csrf_field()
        . auth_input('name', 'Display Name', 'text', 'user', ['value' => input('name'), 'req' => true, 'ac' => 'name', 'ph' => 'e.g. Alex', 'attrs' => 'maxlength="40" autofocus'])
        . auth_input('email', 'Email', 'email', 'mail', ['value' => input('email'), 'req' => true, 'ac' => 'username', 'ph' => 'name@example.com', 'attrs' => 'inputmode="email" autocapitalize="none" spellcheck="false"'])
        . auth_input('studio', 'Studio / Brand Name (optional)', 'text', 'sparkle', ['value' => input('studio'), 'ph' => 'e.g. Move Studio', 'attrs' => 'maxlength="60"'])
        . auth_input('password', 'Password', 'password', 'key', ['req' => true, 'ac' => 'new-password', 'ph' => 'Minimum 8 characters', 'attrs' => 'x-model="pw" minlength="8"', 'after' => $meter])
        . auth_input('password2', 'Confirm Password', 'password', 'key', ['req' => true, 'ac' => 'new-password', 'ph' => 'Re-enter password', 'attrs' => 'x-model="pw2"', 'after' => $match])
        . '<label class="flex cursor-pointer items-start gap-2 text-sm"><input type="checkbox" name="sample" value="1" class="mt-0.5 size-4 shrink-0 rounded accent-[var(--c-brand)]" checked><span>Start with <b>sample data</b> to explore the dashboard right away <span class="text-muted">(can be cleared anytime)</span></span></label>'
        . '<button class="btn-primary w-full !py-3" :disabled="busy"><span x-show="!busy">Create Account &amp; Start</span><span x-show="busy" x-cloak>Creating account…</span></button></form>'
        . '<p class="mt-5 text-center text-sm text-muted">Already have an account? <a href="' . e(url('/login')) . '" class="font-bold text-brand hover:underline">Sign In</a></p>';
    auth_page('Register', $body);
}

function action_register(): void
{
    $name = mb_substr(input('name'), 0, 40);
    $email = normalize_email(input('email'));
    $studio = mb_substr(input('studio'), 0, 60);
    $pw = (string) ($_POST['password'] ?? '');
    $back = '/register?' . http_build_query(['name' => $name, 'email' => $email, 'studio' => $studio]);

    $key = attempt_key('register', '');
    if (attempts_count($key, 3600) >= 10) {
        flash('Too many registration attempts from this network. Please try again later.', 'err');
        redirect('/register');
    }
    attempts_add($key);

    $err = null;
    if ($name === '') {
        $err = 'Name is required.';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL) || strlen($email) > 120) {
        $err = 'Invalid email address.';
    } else {
        $err = password_error($pw, (string) ($_POST['password2'] ?? ''));
    }
    if ($err === null && auth_q('SELECT 1 FROM users WHERE email = ?', [$email])) {
        $err = 'This email is already registered. Please sign in.';
    }
    if ($err !== null) {
        flash($err, 'err');
        redirect($back);
    }

    auth_exec('INSERT INTO users(name, email, password_hash) VALUES(?,?,?)', [$name, $email, password_hash($pw, PASSWORD_DEFAULT)]);
    $id = (int) auth_db()->lastInsertId();

    $pdo = open_workspace($id);
    $profile = ['name' => $name, 'studio' => $studio];
    if (input('sample') === '1') {
        seed_demo($pdo, $profile);
    } else {
        write_profile($pdo, $profile, false);
    }

    login_user($id, true);
    flash('Account created. Welcome, ' . $name . '!');
    redirect('/');
}

function action_logout(): void
{
    logout_user();
    flash('You have signed out.');
    redirect('/login');
}
