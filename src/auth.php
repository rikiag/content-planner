<?php
declare(strict_types=1);

defined('ROOT') || (http_response_code(403) && exit);

/**
 * Authentication and multi-tenant workspace management.
 *  - data/auth.sqlite       : user accounts + login rate limiting
 *  - data/users/{id}.sqlite : isolated workspace database per user
 */

const REMEMBER_SECONDS = 2592000; // 30 days
const MAX_ATTEMPTS = 5;           // failed attempts allowed per 15 minutes (per email + IP)
const ATTEMPT_WINDOW = 900;

function auth_db(): PDO
{
    static $pdo = null;
    if ($pdo instanceof PDO) {
        return $pdo;
    }
    $dir = data_dir();
    ensure_dir($dir);
    $pdo = new PDO('sqlite:' . $dir . '/auth.sqlite', null, null, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ]);
    $pdo->exec('PRAGMA journal_mode = WAL; PRAGMA busy_timeout = 4000;');
    $pdo->exec(<<<'SQL'
CREATE TABLE IF NOT EXISTS users (
    id            INTEGER PRIMARY KEY AUTOINCREMENT,
    name          TEXT NOT NULL,
    email         TEXT NOT NULL UNIQUE COLLATE NOCASE,
    password_hash TEXT NOT NULL,
    created_at    TEXT NOT NULL DEFAULT (datetime('now','localtime'))
);
CREATE TABLE IF NOT EXISTS attempts (
    k  TEXT NOT NULL,
    ts INTEGER NOT NULL
);
CREATE INDEX IF NOT EXISTS idx_attempts_k ON attempts(k, ts);
SQL);
    return $pdo;
}

function auth_q(string $sql, array $params = []): array
{
    $stmt = auth_db()->prepare($sql);
    $stmt->execute($params);
    return $stmt->fetchAll();
}

function auth_exec(string $sql, array $params = []): int
{
    $stmt = auth_db()->prepare($sql);
    $stmt->execute($params);
    return $stmt->rowCount();
}

/* ====================== Session management ====================== */
function current_user(): ?array
{
    static $user = false;
    if ($user === false) {
        $id = (int) ($_SESSION['uid'] ?? 0);
        $user = null;
        if ($id > 0) {
            $rows = auth_q('SELECT id, name, email, created_at FROM users WHERE id = ?', [$id]);
            $user = $rows[0] ?? null;
            if ($user === null) {
                unset($_SESSION['uid']); // account was deleted
            }
        }
    }
    return $user;
}

/** Start an authenticated session. $remember = persist cookie for 30 days. */
function login_user(int $id, bool $remember): void
{
    $keep = $_SESSION;
    session_write_close();
    session_set_cookie_params(cookie_params($remember ? REMEMBER_SECONDS : 0));
    session_start();
    session_regenerate_id(true); // prevent session fixation
    $_SESSION = $keep;
    $_SESSION['uid'] = $id;
    $_SESSION['csrf'] = bin2hex(random_bytes(24));
    unset($_SESSION['next']);
}

function logout_user(): void
{
    $keep = ['flash' => $_SESSION['flash'] ?? []];
    $_SESSION = [];
    session_write_close();
    session_set_cookie_params(cookie_params(0));
    session_start();
    session_regenerate_id(true);
    $_SESSION = $keep;
    $_SESSION['csrf'] = bin2hex(random_bytes(24));
}

function cookie_params(int $lifetime): array
{
    return [
        'lifetime' => $lifetime,
        'path'     => base_path() . '/',
        'httponly' => true,
        'samesite' => 'Lax',
        'secure'   => is_https(),
    ];
}

function is_https(): bool
{
    return (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
        || strtolower((string) ($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '')) === 'https';
}

/* ====================== Rate limiting ====================== */
function attempt_key(string $scope, string $id): string
{
    return hash('sha256', $scope . '|' . strtolower($id) . '|' . ($_SERVER['REMOTE_ADDR'] ?? ''));
}

function attempts_count(string $key, int $window = ATTEMPT_WINDOW): int
{
    auth_exec('DELETE FROM attempts WHERE ts < ?', [time() - 86400]);
    return (int) (auth_q('SELECT COUNT(*) c FROM attempts WHERE k = ? AND ts > ?', [$key, time() - $window])[0]['c'] ?? 0);
}

function attempts_add(string $key): void
{
    auth_exec('INSERT INTO attempts(k, ts) VALUES(?, ?)', [$key, time()]);
}

/* ====================== User workspaces ====================== */
function workspace_path(int $id): string
{
    return data_dir() . '/users/' . $id . '.sqlite';
}

function open_workspace(int $id): PDO
{
    ensure_dir(data_dir() . '/users');
    $pdo = new PDO('sqlite:' . workspace_path($id), null, null, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ]);
    $pdo->exec('PRAGMA journal_mode = WAL; PRAGMA foreign_keys = ON; PRAGMA busy_timeout = 4000;');
    migrate($pdo);
    return $pdo;
}

function delete_workspace(int $id): void
{
    foreach (['', '-wal', '-shm'] as $suffix) {
        @unlink(workspace_path($id) . $suffix);
    }
}

/* ====================== Validation ====================== */
function normalize_email(string $email): string
{
    return strtolower(trim($email));
}

function password_error(string $pw, string $confirm): ?string
{
    if (mb_strlen($pw) < 8) {
        return 'Password must be at least 8 characters long.';
    }
    if (strlen($pw) > 72) {
        return 'Password is too long (maximum 72 bytes).';
    }
    if (!preg_match('/[A-Za-z]/', $pw) || !preg_match('/\d/', $pw)) {
        return 'Password must contain both letters and numbers.';
    }
    if ($pw !== $confirm) {
        return 'Password confirmation does not match.';
    }
    return null;
}

/** Dummy hash to equalize login response timing when an email is not found */
function dummy_hash(): string
{
    static $hash = null;
    return $hash ??= password_hash('unused-' . bin2hex(random_bytes(4)), PASSWORD_DEFAULT);
}
