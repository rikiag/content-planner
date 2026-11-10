<?php
declare(strict_types=1);

defined('ROOT') || (http_response_code(403) && exit);

const APP_NAME = 'Content Planner';
const APP_VERSION = '1.1.0';
const GITHUB_URL = 'https://github.com/rikiag/content-planner';

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

/** Color presets for dynamic Platforms and Statuses */
const CATEGORY_COLORS = [
    'violet'  => ['label' => 'Violet',  'chip' => 'bg-violet-100 text-violet-700 dark:bg-violet-500/20 dark:text-violet-300',   'dot' => 'bg-violet-500',  'tint' => 'bg-violet-100/80 dark:bg-violet-500/10'],
    'rose'    => ['label' => 'Rose',    'chip' => 'bg-rose-100 text-rose-700 dark:bg-rose-500/20 dark:text-rose-300',           'dot' => 'bg-rose-500',    'tint' => 'bg-rose-100/80 dark:bg-rose-500/10'],
    'amber'   => ['label' => 'Amber',   'chip' => 'bg-amber-100 text-amber-800 dark:bg-amber-500/20 dark:text-amber-300',       'dot' => 'bg-amber-500',   'tint' => 'bg-amber-100/80 dark:bg-amber-500/10'],
    'sky'     => ['label' => 'Sky',     'chip' => 'bg-sky-100 text-sky-700 dark:bg-sky-500/20 dark:text-sky-300',               'dot' => 'bg-sky-500',     'tint' => 'bg-sky-100/80 dark:bg-sky-500/10'],
    'emerald' => ['label' => 'Emerald', 'chip' => 'bg-emerald-100 text-emerald-700 dark:bg-emerald-500/20 dark:text-emerald-300', 'dot' => 'bg-emerald-500', 'tint' => 'bg-emerald-100/80 dark:bg-emerald-500/10'],
    'pink'    => ['label' => 'Pink',    'chip' => 'bg-pink-100 text-pink-700 dark:bg-pink-500/20 dark:text-pink-300',           'dot' => 'bg-rose-400',    'tint' => 'bg-rose-100/80 dark:bg-rose-500/10'],
    'blue'    => ['label' => 'Blue',    'chip' => 'bg-blue-100 text-blue-700 dark:bg-blue-500/20 dark:text-blue-300',           'dot' => 'bg-blue-500',    'tint' => 'bg-sky-100/80 dark:bg-sky-500/10'],
    'red'     => ['label' => 'Red',     'chip' => 'bg-red-100 text-red-700 dark:bg-red-500/20 dark:text-red-300',               'dot' => 'bg-rose-500',    'tint' => 'bg-rose-100/80 dark:bg-rose-500/10'],
    'slate'   => ['label' => 'Slate',   'chip' => 'bg-slate-200 text-slate-800 dark:bg-slate-500/25 dark:text-slate-200',       'dot' => 'bg-slate-300',   'tint' => 'bg-soft'],
    'zinc'    => ['label' => 'Zinc',    'chip' => 'bg-zinc-200 text-zinc-800 dark:bg-zinc-500/25 dark:text-zinc-200',           'dot' => 'bg-slate-300',   'tint' => 'bg-soft'],
];

const STATUS_ICONS = ['bulb', 'pencil', 'eye', 'clock', 'check', 'sparkle', 'flag', 'calendar', 'file'];

/** Dynamic workspace categories with default fallbacks */
function platforms(): array
{
    if (!current_user()) {
        return PLATFORMS;
    }
    $raw = setting('custom_platforms');
    if ($raw !== '') {
        $parsed = json_decode($raw, true);
        if (is_array($parsed) && $parsed !== []) {
            return $parsed;
        }
    }
    return PLATFORMS;
}

function formats(): array
{
    if (!current_user()) {
        return FORMATS;
    }
    $raw = setting('custom_formats');
    if ($raw !== '') {
        $parsed = json_decode($raw, true);
        if (is_array($parsed) && $parsed !== []) {
            return array_values($parsed);
        }
    }
    return FORMATS;
}

function pillars(): array
{
    if (!current_user()) {
        return PILLARS;
    }
    $raw = setting('custom_pillars');
    if ($raw !== '') {
        $parsed = json_decode($raw, true);
        if (is_array($parsed) && $parsed !== []) {
            return array_values($parsed);
        }
    }
    return PILLARS;
}

function statuses(): array
{
    if (!current_user()) {
        return STATUSES;
    }
    $raw = setting('custom_statuses');
    if ($raw !== '') {
        $parsed = json_decode($raw, true);
        if (is_array($parsed) && $parsed !== []) {
            return $parsed;
        }
    }
    return STATUSES;
}

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

function is_ajax(): bool
{
    $xhr = strtolower((string) ($_SERVER['HTTP_X_REQUESTED_WITH'] ?? '')) === 'xmlhttprequest';
    $acc = str_contains(strtolower((string) ($_SERVER['HTTP_ACCEPT'] ?? '')), 'application/json');
    return $xhr || $acc || input('_ajax') === '1';
}

function json_out(array $data, int $code = 200): never
{
    http_response_code($code);
    header('Content-Type: application/json; charset=utf-8');
    header('Cache-Control: private, no-store');
    echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

function csrf_token(): string
{
    return (string) ($_SESSION['csrf'] ?? '');
}

function csrf_field(): string
{
    return '<input type="hidden" name="_csrf" value="' . e(csrf_token()) . '">';
}

function csrf_verify(): void
{
    $token = (string) ($_POST['_csrf'] ?? $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '');
    $expected = (string) ($_SESSION['csrf'] ?? '');
    if ($expected === '' || !hash_equals($expected, $token)) {
        if (is_ajax()) {
            json_out(['ok' => false, 'error' => 'Session expired. Please reload the page and try again.'], 419);
        }
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
    $plats = platforms();
    $m = $plats[$p] ?? ['abbr' => mb_strtoupper(mb_substr($p, 0, 2)), 'cls' => 'bg-slate-200 text-slate-700 dark:bg-slate-500/25 dark:text-slate-200'];
    return '<span class="chip ' . $m['cls'] . '">' . e($full ? $p : $m['abbr']) . '</span>';
}

function platform_abbr(string $p): string
{
    $plats = platforms();
    return $plats[$p]['abbr'] ?? mb_strtoupper(mb_substr($p, 0, 2));
}

function content_payload(array $c): array
{
    $plats = platforms();
    $stats = statuses();
    $pMeta = $plats[$c['platform']] ?? ['abbr' => mb_strtoupper(mb_substr((string) $c['platform'], 0, 2)), 'cls' => 'bg-slate-200 text-slate-700 dark:bg-slate-500/25 dark:text-slate-200'];
    $sMeta = $stats[$c['status']] ?? ['label' => ucfirst((string) $c['status']), 'chip' => 'bg-slate-200 text-slate-700 dark:bg-slate-500/25 dark:text-slate-200', 'dot' => 'bg-slate-400'];
    $schedTs = ts($c['scheduled_at'] ?? null);
    return [
        'id'             => (int) $c['id'],
        'title'          => (string) $c['title'],
        'platform'       => (string) $c['platform'],
        'platform_abbr'  => (string) $pMeta['abbr'],
        'platform_cls'   => (string) $pMeta['cls'],
        'format'         => (string) $c['format'],
        'pillar'         => (string) ($c['pillar'] ?? ''),
        'status'         => (string) $c['status'],
        'status_label'   => (string) $sMeta['label'],
        'status_chip'    => (string) $sMeta['chip'],
        'status_dot'     => (string) $sMeta['dot'],
        'campaign_id'    => $c['campaign_id'] ? (int) $c['campaign_id'] : null,
        'campaign_name'  => (string) ($c['campaign_name'] ?? ''),
        'scheduled_at'   => (string) ($c['scheduled_at'] ?? ''),
        'scheduled_fmt'  => fmt_dt($c['scheduled_at'] ?? null),
        'scheduled_date' => $schedTs ? date('Y-m-d', $schedTs) : '',
        'scheduled_time' => $schedTs ? date('H:i', $schedTs) : '',
        'published_at'   => (string) ($c['published_at'] ?? ''),
        'published_fmt'  => !empty($c['published_at']) ? fmt_dt($c['published_at']) : '',
        'progress'       => clamp((int) ($c['progress'] ?? 0), 0, 100),
        'caption'        => (string) ($c['caption'] ?? ''),
        'hashtags'       => (string) ($c['hashtags'] ?? ''),
        'post_url'       => (string) ($c['post_url'] ?? ''),
        'views'          => (int) ($c['views'] ?? 0),
        'views_fmt'      => fmt_k((int) ($c['views'] ?? 0)),
        'likes'          => (int) ($c['likes'] ?? 0),
        'likes_fmt'      => fmt_k((int) ($c['likes'] ?? 0)),
        'comments'       => (int) ($c['comments'] ?? 0),
        'comments_fmt'   => fmt_k((int) ($c['comments'] ?? 0)),
        'shares'         => (int) ($c['shares'] ?? 0),
        'shares_fmt'     => fmt_k((int) ($c['shares'] ?? 0)),
        'saves'          => (int) ($c['saves'] ?? 0),
        'saves_fmt'      => fmt_k((int) ($c['saves'] ?? 0)),
        'er_fmt'         => fmt_pct(er($c)),
        'is_overdue'     => is_overdue($c),
    ];
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

/* ====================== Changelog parser & rendering ====================== */
const CHANGELOG_CATEGORY_META = [
    'Added'      => ['chip' => 'bg-emerald-100 text-emerald-700 dark:bg-emerald-500/20 dark:text-emerald-300', 'dot' => 'bg-emerald-500', 'icon' => 'sparkle'],
    'Changed'    => ['chip' => 'bg-sky-100 text-sky-700 dark:bg-sky-500/20 dark:text-sky-300',                 'dot' => 'bg-sky-500',     'icon' => 'refresh'],
    'Fixed'      => ['chip' => 'bg-amber-100 text-amber-800 dark:bg-amber-500/20 dark:text-amber-300',         'dot' => 'bg-amber-500',   'icon' => 'check'],
    'Removed'    => ['chip' => 'bg-slate-200 text-slate-700 dark:bg-slate-500/25 dark:text-slate-200',         'dot' => 'bg-slate-400',   'icon' => 'trash'],
    'Security'   => ['chip' => 'bg-rose-100 text-rose-700 dark:bg-rose-500/20 dark:text-rose-300',             'dot' => 'bg-rose-500',    'icon' => 'shield'],
    'Deprecated' => ['chip' => 'bg-violet-100 text-violet-700 dark:bg-violet-500/20 dark:text-violet-300',     'dot' => 'bg-violet-500',  'icon' => 'clock'],
    'Upgrade'    => ['chip' => 'bg-blue-100 text-blue-700 dark:bg-blue-500/20 dark:text-blue-300',             'dot' => 'bg-blue-500',    'icon' => 'terminal'],
];

function changelog_category_meta(string $category): array
{
    return CHANGELOG_CATEGORY_META[$category] ?? [
        'chip' => 'bg-soft text-muted',
        'dot'  => 'bg-slate-400',
        'icon' => 'sparkle',
    ];
}

/**
 * Safely format a single changelog bullet line:
 * escapes all HTML first, then converts **bold** and `code` spans.
 */
function format_changelog_line(string $text): string
{
    $escaped = e($text);
    $escaped = (string) preg_replace('/\*\*(.+?)\*\*/', '<strong class="font-bold text-ink">$1</strong>', $escaped);
    $escaped = (string) preg_replace('/`([^`]+)`/', '<code class="rounded-md bg-soft px-1.5 py-0.5 font-mono text-[11px] font-bold text-brand">$1</code>', $escaped);
    return $escaped;
}

/**
 * Parse root CHANGELOG.md (Keep a Changelog format) into structured release entries.
 *
 * @param bool $includeUnreleased Whether to include non-empty [Unreleased] section
 * @param int|null $limit Maximum number of releases to return
 * @return array<int, array{version:string, is_unreleased:bool, date:string, date_fmt:string, image:?array{alt:string,src:string}, categories:array<string, list<string>>}>
 */
function parse_changelog(bool $includeUnreleased = false, ?int $limit = null): array
{
    $file = ROOT . '/CHANGELOG.md';
    if (!is_file($file)) {
        return [];
    }
    $lines = file($file, FILE_IGNORE_NEW_LINES) ?: [];
    $releases = [];
    $current = null;
    $category = null;

    foreach ($lines as $rawLine) {
        $line = trim($rawLine);
        if ($line === '') {
            continue;
        }
        // Match ## [1.1.0] - 2026-11-10 or ## [Unreleased]
        if (preg_match('/^##\s+\[([^\]]+)\](?:\s*-\s*(\d{4}-\d{2}-\d{2}))?/', $line, $m)) {
            if ($current !== null) {
                $releases[] = $current;
            }
            $ver = trim($m[1]);
            $date = trim($m[2] ?? '');
            $current = [
                'version'       => $ver,
                'is_unreleased' => strcasecmp($ver, 'Unreleased') === 0,
                'date'          => $date,
                'date_fmt'      => $date !== '' ? fmt_date($date) : 'Upcoming',
                'image'         => null,
                'categories'    => [],
            ];
            $category = null;
            continue;
        }
        if ($current === null) {
            continue;
        }
        // Optional screenshot / GIF: ![Alt text](public/assets/images/... or assets/images/...)
        if (preg_match('/^!\[([^\]]*)\]\(([^)]+)\)$/', $line, $m)) {
            $imgPath = trim($m[2]);
            if (str_starts_with($imgPath, 'public/')) {
                $imgPath = substr($imgPath, 7);
            }
            $current['image'] = [
                'alt' => trim($m[1]) ?: ('Content Planner v' . $current['version']),
                'src' => str_starts_with($imgPath, 'http') ? $imgPath : asset($imgPath),
            ];
            continue;
        }
        // Match ### Category or inline ### Category- item
        if (preg_match('/^###\s+([A-Za-z]+)(?:\s*-\s*(.+))?$/', $line, $m)) {
            $category = ucfirst(strtolower(trim($m[1])));
            $current['categories'][$category] ??= [];
            if (!empty($m[2])) {
                $current['categories'][$category][] = trim($m[2]);
            }
            continue;
        }
        // Match bullet item: - text or * text
        if ($category !== null && preg_match('/^[-*]\s+(.+)$/', $line, $m)) {
            $current['categories'][$category][] = trim($m[1]);
        }
    }
    if ($current !== null) {
        $releases[] = $current;
    }

    $filtered = array_values(array_filter($releases, function (array $r) use ($includeUnreleased): bool {
        if ($r['is_unreleased']) {
            return $includeUnreleased && !empty($r['categories']);
        }
        return !empty($r['categories']);
    }));

    if ($limit !== null && $limit > 0) {
        return array_slice($filtered, 0, $limit);
    }
    return $filtered;
}
