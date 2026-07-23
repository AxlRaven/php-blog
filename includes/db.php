<?php
declare(strict_types=1);

function db(): PDO
{
    static $pdo = null;
    if ($pdo instanceof PDO) {
        return $pdo;
    }

    $c = $GLOBALS['config']['db'];
    $dsn = sprintf(
        'mysql:host=%s;dbname=%s;charset=%s',
        $c['host'],
        $c['name'],
        $c['charset'] ?? 'utf8mb4'
    );

    $pdo = new PDO($dsn, $c['user'], $c['pass'], [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
    ]);

    return $pdo;
}

function setting(string $key, ?string $default = null): ?string
{
    static $cache = null;
    if ($cache === null) {
        $cache = [];
        try {
            $rows = db()->query('SELECT `key`, `value` FROM settings')->fetchAll();
            foreach ($rows as $row) {
                $cache[$row['key']] = $row['value'];
            }
        } catch (Throwable $e) {
            return $default;
        }
    }
    return $cache[$key] ?? $default;
}

function set_setting(string $key, string $value): void
{
    $stmt = db()->prepare(
        'INSERT INTO settings (`key`, `value`) VALUES (?, ?)
         ON DUPLICATE KEY UPDATE `value` = VALUES(`value`)'
    );
    $stmt->execute([$key, $value]);
}
