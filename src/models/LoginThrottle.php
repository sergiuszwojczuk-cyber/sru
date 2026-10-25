<?php

function loginThrottleKeys(string $email, string $ip): array
{
    return [
        ['key' => hash('sha256', 'email:' . $email), 'limit' => 5],
        ['key' => hash('sha256', 'ip:' . $ip), 'limit' => 20],
    ];
}

function ensureLoginThrottleTable(PDO $pdo): bool
{
    static $available = null;

    if ($available !== null) {
        return $available;
    }

    try {
        $pdo->exec(
            'CREATE TABLE IF NOT EXISTS auth_login_attempts ('
            . 'bucket_key CHAR(64) NOT NULL, '
            . 'failed_attempts TINYINT UNSIGNED NOT NULL DEFAULT 0, '
            . 'window_started_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP, '
            . 'locked_until DATETIME DEFAULT NULL, '
            . 'PRIMARY KEY (bucket_key), '
            . 'KEY idx_auth_login_lock (locked_until)'
            . ') ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci'
        );
        $available = true;
    } catch (PDOException $e) {
        error_log('Login throttle table could not be created: ' . $e->getMessage());
        $available = false;
    }

    return $available;
}

function isMissingLoginThrottleTable(PDOException $e): bool
{
    return $e->getCode() === '42S02' || (int)($e->errorInfo[1] ?? 0) === 1146;
}

function isLoginThrottled(PDO $pdo, array $buckets): bool
{
    try {
        $stmt = $pdo->prepare(
            'SELECT COUNT(*) FROM auth_login_attempts '
            . 'WHERE bucket_key IN (?, ?) AND locked_until > CURRENT_TIMESTAMP'
        );
        $stmt->execute([$buckets[0]['key'], $buckets[1]['key']]);
    } catch (PDOException $e) {
        if (!isMissingLoginThrottleTable($e)) {
            throw $e;
        }

        if (!ensureLoginThrottleTable($pdo)) {
            return false;
        }

        $stmt = $pdo->prepare(
            'SELECT COUNT(*) FROM auth_login_attempts '
            . 'WHERE bucket_key IN (?, ?) AND locked_until > CURRENT_TIMESTAMP'
        );
        $stmt->execute([$buckets[0]['key'], $buckets[1]['key']]);
    }

    return (int)$stmt->fetchColumn() > 0;
}

function recordLoginFailure(PDO $pdo, array $buckets): void
{
    $query =
        'INSERT INTO auth_login_attempts (bucket_key, failed_attempts, window_started_at) '
        . 'VALUES (?, 1, CURRENT_TIMESTAMP) '
        . 'ON DUPLICATE KEY UPDATE '
        . 'locked_until = CASE '
        . 'WHEN window_started_at < DATE_SUB(CURRENT_TIMESTAMP, INTERVAL 15 MINUTE) '
        . 'THEN IF(1 >= ?, DATE_ADD(CURRENT_TIMESTAMP, INTERVAL 15 MINUTE), NULL) '
        . 'WHEN failed_attempts + 1 >= ? THEN DATE_ADD(CURRENT_TIMESTAMP, INTERVAL 15 MINUTE) '
        . 'ELSE locked_until END, '
        . 'failed_attempts = IF(window_started_at < DATE_SUB(CURRENT_TIMESTAMP, INTERVAL 15 MINUTE), 1, failed_attempts + 1), '
        . 'window_started_at = IF(window_started_at < DATE_SUB(CURRENT_TIMESTAMP, INTERVAL 15 MINUTE), CURRENT_TIMESTAMP, window_started_at)';

    try {
        $stmt = $pdo->prepare($query);
        foreach ($buckets as $bucket) {
            $stmt->execute([$bucket['key'], $bucket['limit'], $bucket['limit']]);
        }
    } catch (PDOException $e) {
        if (!isMissingLoginThrottleTable($e)) {
            throw $e;
        }

        if (!ensureLoginThrottleTable($pdo)) {
            return;
        }

        $stmt = $pdo->prepare($query);
        foreach ($buckets as $bucket) {
            $stmt->execute([$bucket['key'], $bucket['limit'], $bucket['limit']]);
        }
    }
}

function clearLoginFailures(PDO $pdo, string $emailBucketKey): void
{
    try {
        $stmt = $pdo->prepare('DELETE FROM auth_login_attempts WHERE bucket_key = ?');
        $stmt->execute([$emailBucketKey]);
    } catch (PDOException $e) {
        if (!isMissingLoginThrottleTable($e)) {
            throw $e;
        }

        if (!ensureLoginThrottleTable($pdo)) {
            return;
        }

        $stmt = $pdo->prepare('DELETE FROM auth_login_attempts WHERE bucket_key = ?');
        $stmt->execute([$emailBucketKey]);
    }
}
