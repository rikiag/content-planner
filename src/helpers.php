<?php
declare(strict_types=1);

defined('ROOT') || (http_response_code(403) && exit);

const APP_NAME = 'Content Planner';
const APP_VERSION = '1.0.0';

/* ====================== Domain constants ====================== */
const MONTHS = ['January', 'February', 'March', 'April', 'May', 'June', 'July', 'August', 'September', 'October', 'November', 'December'];
const MONTHS_SHORT = ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'];
const DAYS_SHORT = ['Sun', 'Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat']; // index = date('w')
const DAYS_FULL = ['Sunday', 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday'];

const PLATFORMS = [
    'Instagram' => ['abbr' => 'IG', 'cls' => 'bg-pink-100 text-pink-700 dark:bg-pink-500/20 dark:text-pink-300'],
    'TikTok'    => ['abbr' => 'TT', 'cls' => 'bg-slate-200 text-slate-800 dark:bg-slate-500/25 dark:text-slate-200'],
    'Facebook'  => ['abbr' => 'FB', 'cls' => 'bg-blue-100 text-blue-700 dark:bg-blue-500/20 dark:text-blue-300'],
    'YouTube'   => ['abbr' => 'YT', 'cls' => 'bg-red-100 text-red-700 dark:bg-red-500/20 dark:text-red-300'],
    'LinkedIn'  => ['abbr' => 'LI', 'cls' => 'bg-sky-100 text-sky-700 dark:bg-sky-500/20 dark:text-sky-300'],
    'X'         => ['abbr' => 'X',  'cls' => 'bg-zinc-200 text-zinc-800 dark:bg-zinc-500/25 dark:text-zinc-200'],
];
const FORMATS = ['Feed', 'Carousel', 'Reels', 'Story', 'Video', 'Thread'];
const PILLARS = ['Education', 'Inspiration', 'Promotion', 'Community', 'Behind the Scenes', 'Entertainment'];

/** Pipeline order and visual styling */
const STATUSES = [
    'idea'      => ['label' => 'Idea',      'chip' => 'bg-violet-100 text-violet-700 dark:bg-violet-500/20 dark:text-violet-300',   'dot' => 'bg-violet-500',  'tint' => 'bg-violet-100/80 dark:bg-violet-500/10',   'icon' => 'bulb'],
    'draft'     => ['label' => 'Draft',     'chip' => 'bg-rose-100 text-rose-700 dark:bg-rose-500/20 dark:text-rose-300',           'dot' => 'bg-rose-500',    'tint' => 'bg-rose-100/80 dark:bg-rose-500/10',       'icon' => 'pencil'],
    'review'    => ['label' => 'Review',    'chip' => 'bg-amber-100 text-amber-800 dark:bg-amber-500/20 dark:text-amber-300',       'dot' => 'bg-amber-500',   'tint' => 'bg-amber-100/80 dark:bg-amber-500/10',     'icon' => 'eye'],
    'scheduled' => ['label' => 'Scheduled', 'chip' => 'bg-sky-100 text-sky-700 dark:bg-sky-500/20 dark:text-sky-300',               'dot' => 'bg-sky-500',     'tint' => 'bg-sky-100/80 dark:bg-sky-500/10',         'icon' => 'clock'],
    'published' => ['label' => 'Published', 'chip' => 'bg-emerald-100 text-emerald-700 dark:bg-emerald-500/20 dark:text-emerald-300', 'dot' => 'bg-emerald-500', 'tint' => 'bg-emerald-100/80 dark:bg-emerald-500/10', 'icon' => 'check'],
];

const CAMPAIGN_STATUSES = [
    'planned' => ['label' => 'Planned',   'chip' => 'bg-slate-200 text-slate-700 dark:bg-slate-500/25 dark:text-slate-200'],
    'active'  => ['label' => 'Active',    'chip' => 'bg-emerald-100 text-emerald-700 dark:bg-emerald-500/20 dark:text-emerald-300'],
    'done'    => ['label' => 'Completed', 'chip' => 'bg-blue-100 text-blue-700 dark:bg-blue-500/20 dark:text-blue-300'],
];

/** Campaign colors: full gradient class strings so Tailwind detects them */
const CAMPAIGN_COLORS = [
    'blue'   => 'from-blue-500 to-indigo-600',
    'orange' => 'from-orange-400 to-rose-500',
    'green'  => 'from-emerald-400 to-teal-600',
    'purple' => 'from-violet-500 to-fuchsia-600',
    'amber'  => 'from-amber-400 to-orange-500',
    'pink'   => 'from-pink-400 to-rose-600',
];

const RESOURCE_TYPES = [
    'link'     => ['label' => 'Link',     'icon' => 'link',    'cls' => 'bg-blue-100 text-blue-700 dark:bg-blue-500/20 dark:text-blue-300'],
    'template' => ['label' => 'Template', 'icon' => 'note',    'cls' => 'bg-amber-100 text-amber-800 dark:bg-amber-500/20 dark:text-amber-300'],
    'hashtag'  => ['label' => 'Hashtags', 'icon' => 'tag',     'cls' => 'bg-pink-100 text-pink-700 dark:bg-pink-500/20 dark:text-pink-300'],
    'file'     => ['label' => 'Files',    'icon' => 'folder',  'cls' => 'bg-emerald-100 text-emerald-700 dark:bg-emerald-500/20 dark:text-emerald-300'],
    'brand'    => ['label' => 'Brand',    'icon' => 'sparkle', 'cls' => 'bg-violet-100 text-violet-700 dark:bg-violet-500/20 dark:text-violet-300'],
];

const NOTE_COLORS = [
    'yellow' => 'bg-note text-noteink',
    'blue'   => 'bg-sky-100 text-sky-900 dark:bg-sky-500/15 dark:text-sky-100',
    'green'  => 'bg-emerald-100 text-emerald-900 dark:bg-emerald-500/15 dark:text-emerald-100',
    'pink'   => 'bg-pink-100 text-pink-900 dark:bg-pink-500/15 dark:text-pink-100',
];

const PRIORITIES = ['low' => 'Low', 'medium' => 'Medium', 'high' => 'High'];

/* ====================== Escaping, URLs, and request parsing ====================== */
function e(mixed $val): string
{
    return htmlspecialchars((string) $val, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function base_path(): string
{
    static $base = null;
    if ($base === null) {
        $cfg = app_config('base_path');
        if ($cfg !== null) {
            $base = trim((string) $cfg, '/') === '' ? '' : '/' . trim((string) $cfg, '/');
            return $base;
        }
        // SCRIPT_NAME = /content-planner/public/index.php -> base = /content-planner
        $sn = str_replace('\\', '/', (string) ($_SERVER['SCRIPT_NAME'] ?? ''));
        $dir = str_ends_with($sn, '.php') ? rtrim(dirname($sn), '/') : '';
        if (str_ends_with($dir, '/public')) {
            $dir = substr($dir, 0, -7);
        }
        $base = $dir === '.' ? '' : $dir;
    }
    return $base;
}

function url(string $path = ''): string
{
    return base_path() . '/' . ltrim($path, '/');
}

function asset(string $path): string
{
    $file = ROOT . '/public/' . ltrim($path, '/');
    $version = is_file($file) ? (string) filemtime($file) : APP_VERSION;
    return url($path) . '?v=' . $version;
}

function request_path(): string
{
    $path = parse_url((string) ($_SERVER['REQUEST_URI'] ?? '/'), PHP_URL_PATH) ?: '/';
    $base = base_path();
    if ($base !== '' && str_starts_with($path, $base)) {
        $path = substr($path, strlen($base));
    }
    return '/' . trim(rawurldecode($path), '/');
}

function input(string $key, string $default = ''): string
{
    $val = $_POST[$key] ?? $_GET[$key] ?? $default;
    return is_string($val) ? trim($val) : $default;
}

function redirect(string $path): never
{
    $target = str_starts_with($path, 'http') ? $path : url($path);
    header('Location: ' . $target, true, 303);
    exit;
}

/** Only allow redirecting back to internal application paths */
function safe_back(string $fallback): string
{
    $back = input('back');
    if ($back !== '' && str_starts_with($back, '/') && !str_starts_with($back, '//')) {
        return $back;
    }
    return $fallback;
}

function not_found(): never
{
    if (!current_user()) {
        redirect('/login');
    }
    http_response_code(404);
    page('Not Found', '', '<div class="card mx-auto mt-10 max-w-md p-8 text-center">'
        . '<div class="mx-auto mb-4 grid size-16 place-items-center rounded-2xl bg-soft text-brand">' . icon('search', 'size-8') . '</div>'
        . '<h2 class="text-xl font-extrabold">Page not found</h2>'
        . '<p class="mt-1 text-sm text-muted">The link you followed does not exist or the item has been deleted.</p>'
        . '<a href="' . e(url('/')) . '" class="btn-primary mt-5">Back to Dashboard</a></div>');
    exit;
}

/* ====================== Session, flash messages, and CSRF ====================== */
/**
 * Start a session lazily so bots scanning random URLs do not flood the session directory:
 * a session is initialized only if a session cookie exists, the request is non-GET (requires CSRF),
 * or the page renders an authentication form.
 */
function boot_session(): void
{
    if (session_status() !== PHP_SESSION_NONE) {
        return;
    }
    $needsSession = isset($_COOKIE['cpsess'])
        || ($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'GET'
        || in_array(request_path(), ['/login', '/register'], true);
    if (!$needsSession) {
        $_SESSION = [];
        return;
    }
    start_app_session();
}

function start_app_session(): void
{
    if (session_status() === PHP_SESSION_NONE) {
        ini_set('session.gc_maxlifetime', (string) REMEMBER_SECONDS);
        ini_set('session.gc_probability', '1');
        ini_set('session.gc_divisor', '100');
        ini_set('session.use_strict_mode', '1');
        ini_set('session.use_only_cookies', '1');
        try {
            $savePath = data_dir() . '/sessions';
            ensure_dir($savePath);
            if (is_writable($savePath)) {
                session_save_path($savePath);
            }
        } catch (Throwable $e) {
            error_log('[content-planner] session path: ' . $e->getMessage());
        }
        session_name('cpsess');
        session_set_cookie_params(cookie_params(0));
        session_start();
    }
    if (empty($_SESSION['csrf'])) {
        $_SESSION['csrf'] = bin2hex(random_bytes(24));
    }
}

function csrf_field(): string
{
    return '<input type="hidden" name="_csrf" value="' . e($_SESSION['csrf'] ?? '') . '">';
}

function csrf_verify(): void
{
    $token = (string) ($_POST['_csrf'] ?? '');
    $expected = (string) ($_SESSION['csrf'] ?? '');
    if ($expected === '' || !hash_equals($expected, $token)) {
        http_response_code(419);
        exit('Session expired. Please reload the page and try again.');
    }
}

function flash(string $msg, string $type = 'ok'): void
{
    $_SESSION['flash'][] = ['m' => $msg, 't' => $type];
}

function take_flash(): array
{
    $flashes = $_SESSION['flash'] ?? [];
    unset($_SESSION['flash']);
    return $flashes;
}

/* ====================== Formatting helpers ====================== */
function fmt_k(float|int $n): string
{
    $n = (float) $n;
    if ($n >= 1_000_000) {
        $s = number_format($n / 1_000_000, 1, '.', ',');
        return (str_ends_with($s, '.0') ? substr($s, 0, -2) : $s) . 'M';
    }
    if ($n >= 1000) {
        $s = number_format($n / 1000, 1, '.', ',');
        return (str_ends_with($s, '.0') ? substr($s, 0, -2) : $s) . 'K';
    }
    return (string) (int) $n;
}

function fmt_num(float|int $n): string
{
    return number_format((float) $n, 0, '.', ',');
}

function fmt_pct(float $n, int $decimals = 1): string
{
    return number_format($n, $decimals, '.', ',') . '%';
}

function er(array $c): float
{
    $views = (int) ($c['views'] ?? 0);
    if ($views <= 0) {
        return 0.0;
    }
    return ((int) $c['likes'] + (int) $c['comments'] + (int) $c['shares'] + (int) $c['saves']) / $views * 100;
}

function ts(?string $d): ?int
{
    if (!$d) {
        return null;
    }
    $t = strtotime($d);
    return $t === false ? null : $t;
}

/** Format datetime e.g. "Thu, 22 Oct 07:00" */
function fmt_dt(?string $d, bool $withTime = true): string
{
    $t = ts($d);
    if ($t === null) {
        return 'Unscheduled';
    }
    $s = DAYS_SHORT[(int) date('w', $t)] . ', ' . (int) date('j', $t) . ' ' . MONTHS_SHORT[(int) date('n', $t) - 1];
    if ((int) date('Y', $t) !== (int) date('Y')) {
        $s .= ' ' . date('Y', $t);
    }
    return $withTime ? $s . ' ' . date('H:i', $t) : $s;
}

function fmt_date(?string $d): string
{
    $t = ts($d);
    if ($t === null) {
        return '-';
    }
    return (int) date('j', $t) . ' ' . MONTHS_SHORT[(int) date('n', $t) - 1] . ' ' . date('Y', $t);
}

function now_str(): string
{
    return date('Y-m-d H:i');
}

function is_overdue(array $c): bool
{
    return $c['status'] !== 'published' && !empty($c['scheduled_at']) && $c['scheduled_at'] < now_str();
}

function initials(string $name): string
{
    $parts = preg_split('/\s+/', trim($name)) ?: [];
    $str = '';
    foreach (array_slice($parts, 0, 2) as $part) {
        $str .= mb_strtoupper(mb_substr($part, 0, 1));
    }
    return $str !== '' ? $str : 'U';
}

function to_input_dt(?string $d): string
{
    $t = ts($d);
    return $t === null ? '' : date('Y-m-d\TH:i', $t);
}

function from_input_dt(string $v): ?string
{
    $v = trim($v);
    if ($v === '') {
        return null;
    }
    $t = strtotime(str_replace('T', ' ', $v));
    return $t === false ? null : date('Y-m-d H:i', $t);
}

function clamp(int $v, int $min, int $max): int
{
    return max($min, min($max, $v));
}

/* ====================== UI micro-components ====================== */
function platform_chip(string $p, bool $full = false): string
{
    $m = PLATFORMS[$p] ?? ['abbr' => mb_strtoupper(mb_substr($p, 0, 2)), 'cls' => 'bg-slate-200 text-slate-700'];
    return '<span class="chip ' . $m['cls'] . '">' . e($full ? $p : $m['abbr']) . '</span>';
}

function platform_abbr(string $p): string
{
    return PLATFORMS[$p]['abbr'] ?? mb_strtoupper(mb_substr($p, 0, 2));
}

/** SVG progress ring. $center may contain trusted HTML. */
function ring(float $pct, int $size = 64, int $sw = 8, string $center = '', string $barCls = 'text-emerald-400', string $trackCls = 'text-white/15'): string
{
    $pct = max(0, min(100, $pct));
    $r = ($size - $sw) / 2;
    $c = 2 * M_PI * $r;
    $dash = $c * $pct / 100;
    return '<div class="relative shrink-0" style="width:' . $size . 'px;height:' . $size . 'px">'
        . '<svg viewBox="0 0 ' . $size . ' ' . $size . '" class="size-full -rotate-90" aria-hidden="true">'
        . '<circle cx="' . $size / 2 . '" cy="' . $size / 2 . '" r="' . $r . '" fill="none" stroke="currentColor" stroke-width="' . $sw . '" class="' . $trackCls . '"/>'
        . '<circle cx="' . $size / 2 . '" cy="' . $size / 2 . '" r="' . $r . '" fill="none" stroke="currentColor" stroke-width="' . $sw . '" stroke-linecap="round" stroke-dasharray="' . round($dash, 2) . ' ' . round($c, 2) . '" class="' . $barCls . '"/>'
        . '</svg><div class="absolute inset-0 grid place-items-center text-center">' . $center . '</div></div>';
}

function empty_state(string $icon, string $title, string $text, string $actionHtml = ''): string
{
    return '<div class="card grid place-items-center gap-2 px-6 py-12 text-center">'
        . '<div class="grid size-14 place-items-center rounded-2xl bg-soft text-brand">' . icon($icon, 'size-7') . '</div>'
        . '<h3 class="text-base font-extrabold">' . e($title) . '</h3>'
        . '<p class="max-w-sm text-sm text-muted">' . e($text) . '</p>' . $actionHtml . '</div>';
}

function delete_form(string $action, string $confirm, string $label = 'Delete', string $cls = 'btn-ghost !text-rose-600', bool $iconOnly = false, string $back = ''): string
{
    return '<form method="post" action="' . e(url($action)) . '" x-confirm="' . e(json_encode($confirm, JSON_UNESCAPED_UNICODE)) . '" class="inline">'
        . csrf_field()
        . ($back !== '' ? '<input type="hidden" name="back" value="' . e($back) . '">' : '')
        . '<button class="' . $cls . '" title="' . e($label) . '" aria-label="' . e($label) . '">' . icon('trash', 'size-4') . ($iconOnly ? '' : e($label)) . '</button></form>';
}

/* ====================== Shared statistics (header & dashboard) ====================== */
function pub_date_sql(): string
{
    return "COALESCE(published_at, scheduled_at, created_at)";
}

function week_range(?int $t = null): array
{
    $t ??= time();
    $dow = (int) date('N', $t); // 1 = Monday
    $start = strtotime(date('Y-m-d', $t) . ' -' . ($dow - 1) . ' days');
    return [date('Y-m-d', $start), date('Y-m-d', strtotime('+6 days', $start))];
}

function published_days(): array
{
    $rows = q("SELECT DISTINCT substr(" . pub_date_sql() . ",1,10) AS d FROM contents WHERE status='published'");
    return array_column($rows, 'd');
}

function streak_days(): int
{
    $days = array_flip(published_days());
    $d = date('Y-m-d');
    if (!isset($days[$d])) {
        $d = date('Y-m-d', strtotime('-1 day')); // allow today to still be in progress
    }
    $n = 0;
    while (isset($days[$d])) {
        $n++;
        $d = date('Y-m-d', strtotime($d . ' -1 day'));
    }
    return $n;
}

function overdue_count(): int
{
    return (int) qv("SELECT COUNT(*) FROM contents WHERE status!='published' AND scheduled_at IS NOT NULL AND scheduled_at < ?", [now_str()]);
}

function new_ideas_count(): int
{
    return (int) qv("SELECT COUNT(*) FROM ideas WHERE status='new'");
}

function reminders(): array
{
    $now = now_str();
    $soon = date('Y-m-d H:i', strtotime('+24 hours'));
    $late = q("SELECT * FROM contents WHERE status!='published' AND scheduled_at IS NOT NULL AND scheduled_at < ? ORDER BY scheduled_at DESC LIMIT 5", [$now]);
    $next = q("SELECT * FROM contents WHERE status!='published' AND scheduled_at >= ? AND scheduled_at <= ? ORDER BY scheduled_at LIMIT 5", [$now, $soon]);
    return ['late' => $late, 'soon' => $next];
}
