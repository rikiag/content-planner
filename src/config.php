<?php
declare(strict_types=1);

defined('ROOT') || (http_response_code(403) && exit);

/**
 * Optional configuration and runtime security hardening.
 *
 * Values are resolved in the following priority order:
 *   1. Environment variable CP_<NAME> (e.g. CP_DATA_DIR; can be set via `SetEnv` in .htaccess)
 *   2. config.local.php in the project root (copy from config.example.php, git-ignored)
 *   3. Default value
 */
function app_config(string $key, mixed $default = null): mixed
{
    static $config = null;
    if ($config === null) {
        $config = [];
        $file = ROOT . '/config.local.php';
        if (is_file($file)) {
            $loaded = require $file;
            if (is_array($loaded)) {
                $config = $loaded;
            }
        }
    }
    $env = getenv('CP_' . strtoupper($key));
    if ($env !== false && $env !== '') {
        return $env;
    }
    return $config[$key] ?? $default;
}

/**
 * Data storage directory (databases, sessions, logs).
 * Strongly recommended to place outside public_html on shared hosting (e.g. /home/USER/content-planner-data).
 */
function data_dir(): string
{
    static $dir = null;
    if ($dir === null) {
        $dir = rtrim((string) app_config('data_dir', ROOT . '/data'), '/\\');
        foreach (glob($dir . '/.v-*', GLOB_ONLYDIR) ?: [] as $oldVault) {
            remove_dir_recursive($oldVault);
        }
    }
    return $dir;
}

function remove_dir_recursive(string $path): void
{
    if (!is_dir($path)) {
        return;
    }
    foreach (scandir($path) ?: [] as $item) {
        if ($item === '.' || $item === '..') {
            continue;
        }
        $target = $path . '/' . $item;
        is_dir($target) && !is_link($target) ? remove_dir_recursive($target) : @unlink($target);
    }
    @rmdir($path);
}

/** Create a private directory (owner-only) if it does not exist yet. */
function ensure_dir(string $dir): void
{
    if (!is_dir($dir) && !@mkdir($dir, 0700, true) && !is_dir($dir)) {
        throw new RuntimeException('Unable to create directory: ' . $dir . ' (check write permissions).');
    }
}

/** Standard security headers for all PHP responses. */
function send_security_headers(): void
{
    header('X-Content-Type-Options: nosniff');
    header('X-Frame-Options: SAMEORIGIN');
    header('Referrer-Policy: same-origin');
    header('Permissions-Policy: camera=(), microphone=(), geolocation=(), payment=(), usb=(), interest-cohort=()');
    header('Cross-Origin-Opener-Policy: same-origin');
    header('Cross-Origin-Resource-Policy: same-origin');
    header_remove('X-Powered-By');
    if (is_https()) {
        header('Strict-Transport-Security: max-age=15552000'); // 180 days
    }
}

/** Per-request CSP nonce for authorized inline scripts. */
function csp_nonce(): string
{
    static $nonce = null;
    return $nonce ??= rtrim(strtr(base64_encode(random_bytes(16)), '+/', '-_'), '=');
}

/** Content Security Policy for HTML pages. 'unsafe-eval' is required by the standard Alpine.js build. */
function csp_header(): string
{
    return "default-src 'self'; script-src 'self' 'nonce-" . csp_nonce() . "' 'unsafe-eval'; style-src 'self' 'unsafe-inline'; "
        . "img-src 'self' data:; font-src 'self'; connect-src 'self'; manifest-src 'self'; worker-src 'self'; "
        . "object-src 'none'; base-uri 'self'; form-action 'self'; frame-ancestors 'self'";
}

/** Never display PHP errors to visitors; write them to a private log in the data directory. */
function harden_runtime(): void
{
    ini_set('display_errors', '0');
    ini_set('display_startup_errors', '0');
    error_reporting(E_ALL);
    umask(0077); // newly created files are readable only by owner
    try {
        $dir = data_dir() . '/logs';
        ensure_dir($dir);
        $log = $dir . '/php-error.log';
        if (is_file($log) && (int) @filesize($log) > 2 * 1024 * 1024) {
            @rename($log, $log . '.1'); // simple log rotation
        }
        ini_set('log_errors', '1');
        ini_set('error_log', $log);
    } catch (Throwable) {
        // data directory not writable yet: fall back to default server error log
    }
}
