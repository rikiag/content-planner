<?php
declare(strict_types=1);

defined('ROOT') || (http_response_code(403) && exit);

/**
 * Connection to the currently authenticated user's isolated SQLite workspace (singleton).
 * File: data/users/{id}.sqlite — created automatically upon registration.
 */
function db(): PDO
{
    static $pdo = null;
    if ($pdo instanceof PDO) {
        return $pdo;
    }
    $id = (int) ($_SESSION['uid'] ?? 0);
    if ($id <= 0) {
        throw new RuntimeException('Unauthenticated database access.');
    }
    return $pdo = open_workspace($id);
}

function migrate(PDO $pdo): void
{
    $pdo->exec(<<<'SQL'
CREATE TABLE IF NOT EXISTS settings (
    key   TEXT PRIMARY KEY,
    value TEXT NOT NULL DEFAULT ''
);

CREATE TABLE IF NOT EXISTS campaigns (
    id         INTEGER PRIMARY KEY AUTOINCREMENT,
    name       TEXT NOT NULL,
    goal       TEXT NOT NULL DEFAULT '',
    status     TEXT NOT NULL DEFAULT 'planned',   -- planned | active | done
    start_date TEXT,
    end_date   TEXT,
    color      TEXT NOT NULL DEFAULT 'blue',
    created_at TEXT NOT NULL DEFAULT (datetime('now','localtime'))
);

CREATE TABLE IF NOT EXISTS contents (
    id           INTEGER PRIMARY KEY AUTOINCREMENT,
    title        TEXT NOT NULL,
    platform     TEXT NOT NULL DEFAULT 'Instagram',
    format       TEXT NOT NULL DEFAULT 'Feed',
    pillar       TEXT NOT NULL DEFAULT '',
    status       TEXT NOT NULL DEFAULT 'draft',    -- idea | draft | review | scheduled | published
    campaign_id  INTEGER REFERENCES campaigns(id) ON DELETE SET NULL,
    scheduled_at TEXT,                             -- Y-m-d H:i
    published_at TEXT,
    progress     INTEGER NOT NULL DEFAULT 0,
    caption      TEXT NOT NULL DEFAULT '',
    hashtags     TEXT NOT NULL DEFAULT '',
    post_url     TEXT NOT NULL DEFAULT '',
    views        INTEGER NOT NULL DEFAULT 0,
    likes        INTEGER NOT NULL DEFAULT 0,
    comments     INTEGER NOT NULL DEFAULT 0,
    shares       INTEGER NOT NULL DEFAULT 0,
    saves        INTEGER NOT NULL DEFAULT 0,
    created_at   TEXT NOT NULL DEFAULT (datetime('now','localtime')),
    updated_at   TEXT NOT NULL DEFAULT (datetime('now','localtime'))
);
CREATE INDEX IF NOT EXISTS idx_contents_status ON contents(status);
CREATE INDEX IF NOT EXISTS idx_contents_sched  ON contents(scheduled_at);
CREATE INDEX IF NOT EXISTS idx_contents_camp   ON contents(campaign_id);

CREATE TABLE IF NOT EXISTS ideas (
    id         INTEGER PRIMARY KEY AUTOINCREMENT,
    title      TEXT NOT NULL,
    notes      TEXT NOT NULL DEFAULT '',
    pillar     TEXT NOT NULL DEFAULT '',
    priority   TEXT NOT NULL DEFAULT 'medium',    -- low | medium | high
    status     TEXT NOT NULL DEFAULT 'new',       -- new | saved | used
    created_at TEXT NOT NULL DEFAULT (datetime('now','localtime'))
);

CREATE TABLE IF NOT EXISTS resources (
    id         INTEGER PRIMARY KEY AUTOINCREMENT,
    title      TEXT NOT NULL,
    type       TEXT NOT NULL DEFAULT 'link',      -- link | template | hashtag | file | brand
    url        TEXT NOT NULL DEFAULT '',
    body       TEXT NOT NULL DEFAULT '',
    tag        TEXT NOT NULL DEFAULT '',
    created_at TEXT NOT NULL DEFAULT (datetime('now','localtime'))
);

CREATE TABLE IF NOT EXISTS notes (
    id         INTEGER PRIMARY KEY AUTOINCREMENT,
    body       TEXT NOT NULL,
    color      TEXT NOT NULL DEFAULT 'yellow',
    pinned     INTEGER NOT NULL DEFAULT 0,
    created_at TEXT NOT NULL DEFAULT (datetime('now','localtime')),
    updated_at TEXT NOT NULL DEFAULT (datetime('now','localtime'))
);
SQL);
}

/* ---------- Query helpers ---------- */
function q(string $sql, array $params = []): array
{
    $stmt = db()->prepare($sql);
    $stmt->execute($params);
    return $stmt->fetchAll();
}

function q1(string $sql, array $params = []): ?array
{
    $stmt = db()->prepare($sql);
    $stmt->execute($params);
    $row = $stmt->fetch();
    return $row === false ? null : $row;
}

function qv(string $sql, array $params = []): mixed
{
    $stmt = db()->prepare($sql);
    $stmt->execute($params);
    $val = $stmt->fetchColumn();
    return $val === false ? null : $val;
}

function exec_sql(string $sql, array $params = []): int
{
    $stmt = db()->prepare($sql);
    $stmt->execute($params);
    return $stmt->rowCount();
}

function setting(string $key, string $default = ''): string
{
    static $cache = null;
    if ($cache === null) {
        $cache = [];
        foreach (q('SELECT key, value FROM settings') as $row) {
            $cache[$row['key']] = $row['value'];
        }
    }
    return $cache[$key] ?? $default;
}

function save_setting(string $key, string $value): void
{
    exec_sql('INSERT INTO settings(key,value) VALUES(?,?) ON CONFLICT(key) DO UPDATE SET value=excluded.value', [$key, $value]);
}
