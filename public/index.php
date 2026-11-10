<?php
declare(strict_types=1);

ini_set('display_errors', '0');
if (PHP_VERSION_ID < 80235) {
    http_response_code(500);
    header('Content-Type: text/plain; charset=utf-8');
    exit('Content Planner requires PHP >= 8.2.35. Server version: ' . PHP_VERSION . '. Please upgrade your PHP version in your hosting control panel.');
}

// Built-in PHP server (php -S ... public/index.php): serve static files directly
if (PHP_SAPI === 'cli-server') {
    $file = __DIR__ . parse_url((string) $_SERVER['REQUEST_URI'], PHP_URL_PATH);
    if (is_file($file) && realpath($file) !== __FILE__) {
        return false;
    }
}

define('ROOT', dirname(__DIR__));

require ROOT . '/src/config.php';
require ROOT . '/src/helpers.php';
require ROOT . '/src/icons.php';
require ROOT . '/src/db.php';
require ROOT . '/src/auth.php';
require ROOT . '/src/seed.php';
require ROOT . '/src/layout.php';
require ROOT . '/src/forms.php';
foreach (['auth', 'landing', 'home', 'content', 'campaign', 'idea', 'resources', 'analytics', 'account', 'notes', 'export', 'search'] as $page) {
    require ROOT . '/src/pages/' . $page . '.php';
}

date_default_timezone_set((string) app_config('timezone', 'UTC'));
harden_runtime();
send_security_headers();

boot_session();

/** [method, path pattern, handler] — regex capture groups are passed as int/string arguments */
$routes = [
    // --- Public (unauthenticated) ---
    ['GET',  '#^/welcome$#',                   'page_landing'],
    ['GET',  '#^/login$#',                     'page_login'],
    ['POST', '#^/login$#',                     'action_login'],
    ['GET',  '#^/register$#',                  'page_register'],
    ['POST', '#^/register$#',                  'action_register'],
    ['POST', '#^/logout$#',                    'action_logout'],

    // --- Home (Dashboard when signed in, Landing Page when guest) ---
    ['GET',  '#^/$#',                          'page_home'],

    ['GET',  '#^/content$#',                   'page_contents'],
    ['GET',  '#^/content/new$#',               'page_content_form'],
    ['POST', '#^/content/new$#',               'action_content_save'],
    ['GET',  '#^/content/(\d+)$#',             'page_content_form'],
    ['POST', '#^/content/(\d+)$#',             'action_content_save'],
    ['POST', '#^/content/(\d+)/status$#',      'action_content_status'],
    ['POST', '#^/content/(\d+)/duplicate$#',   'action_content_duplicate'],
    ['POST', '#^/content/(\d+)/delete$#',      'action_content_delete'],

    ['GET',  '#^/campaigns$#',                 'page_campaigns'],
    ['GET',  '#^/campaigns/new$#',             'page_campaign_form'],
    ['POST', '#^/campaigns/new$#',             'action_campaign_save'],
    ['GET',  '#^/campaigns/(\d+)$#',           'page_campaign_detail'],
    ['GET',  '#^/campaigns/(\d+)/edit$#',      'page_campaign_form'],
    ['POST', '#^/campaigns/(\d+)/edit$#',      'action_campaign_save'],
    ['POST', '#^/campaigns/(\d+)/delete$#',    'action_campaign_delete'],

    ['GET',  '#^/ideas$#',                     'page_ideas'],
    ['POST', '#^/ideas$#',                     'action_idea_quick_add'],
    ['GET',  '#^/ideas/(\d+)/edit$#',          'page_idea_form'],
    ['POST', '#^/ideas/(\d+)/edit$#',          'action_idea_save'],
    ['POST', '#^/ideas/(\d+)/status$#',        'action_idea_status'],
    ['POST', '#^/ideas/(\d+)/convert$#',       'action_idea_to_content'],
    ['POST', '#^/ideas/(\d+)/delete$#',        'action_idea_delete'],

    ['GET',  '#^/resources$#',                 'page_resources'],
    ['GET',  '#^/resources/new$#',             'page_resource_form'],
    ['POST', '#^/resources/new$#',             'action_resource_save'],
    ['GET',  '#^/resources/(\d+)/edit$#',      'page_resource_form'],
    ['POST', '#^/resources/(\d+)/edit$#',      'action_resource_save'],
    ['POST', '#^/resources/(\d+)/delete$#',    'action_resource_delete'],

    ['GET',  '#^/analytics$#',                 'page_analytics'],

    ['GET',  '#^/account$#',                   'page_account'],
    ['POST', '#^/account$#',                   'action_account_save'],
    ['POST', '#^/account/password$#',          'action_account_password'],
    ['POST', '#^/account/delete$#',            'action_account_delete'],
    ['POST', '#^/account/reset$#',             'action_account_reset'],
    ['POST', '#^/account/wipe$#',              'action_account_wipe'],

    ['GET',  '#^/notes$#',                     'page_notes'],
    ['POST', '#^/notes$#',                     'action_note_add'],
    ['POST', '#^/notes/(\d+)$#',               'action_note_update'],
    ['POST', '#^/notes/(\d+)/pin$#',           'action_note_pin'],
    ['POST', '#^/notes/(\d+)/delete$#',        'action_note_delete'],

    ['GET',  '#^/export$#',                    'page_export'],
    ['GET',  '#^/export/(content|campaigns|ideas)\.csv$#', 'action_export_csv'],
    ['GET',  '#^/export/backup\.json$#',       'action_export_json'],

    ['GET',  '#^/search$#',                    'page_search'],
];

$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
$path = request_path();

// Redirect direct /public/... access to canonical URL
if (preg_match('#^/public(/.*)?$#', $path, $matches)) {
    $sub = ($matches[1] ?? '/') === '/index.php' ? '/' : ($matches[1] ?? '/');
    $query = (string) ($_SERVER['QUERY_STRING'] ?? '');
    header('Location: ' . url($sub) . ($query !== '' ? '?' . $query : ''), true, 301);
    exit;
}

const PUBLIC_HANDLERS = ['page_home', 'page_landing', 'page_login', 'action_login', 'page_register', 'action_register', 'action_logout'];

try {
    foreach ($routes as [$routeMethod, $pattern, $handler]) {
        if ($routeMethod === $method && preg_match($pattern, $path, $matches)) {
            if ($routeMethod === 'POST') {
                csrf_verify();
            }
            if (!in_array($handler, PUBLIC_HANDLERS, true) && !current_user()) {
                if ($routeMethod === 'GET' && isset($_COOKIE['cpsess'])) {
                    $_SESSION['next'] = $path . (!empty($_SERVER['QUERY_STRING']) ? '?' . $_SERVER['QUERY_STRING'] : '');
                }
                redirect('/login');
            }
            array_shift($matches);
            $args = array_map(fn($val) => ctype_digit($val) ? (int) $val : $val, $matches);
            $handler(...$args);
            exit;
        }
    }
    not_found();
} catch (Throwable $ex) {
    error_log('[content-planner] ' . $ex);
    http_response_code(500);
    $msg = '<div class="card mx-auto mt-10 max-w-md p-8 text-center"><h2 class="text-xl font-extrabold">Something went wrong</h2>'
        . '<p class="mt-1 text-sm text-muted">Please reload the page. If the issue persists, check the PHP error log and write permissions on the <code>data/</code> directory.</p></div>';
    try {
        current_user() ? page('Error', '', $msg) : auth_page('Error', $msg);
    } catch (Throwable $fallbackEx) {
        echo '<!doctype html><meta charset="utf-8"><title>Error</title><p>An unexpected error occurred. Check the server logs and write permissions on the data/ directory.</p>';
    }
}
