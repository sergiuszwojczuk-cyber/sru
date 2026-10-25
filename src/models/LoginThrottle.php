<?php

function loginThrottleKeys(string $email, string $ip): array
{
    return [
        ['key' => hash('sha256', 'email:' . $email), 'limit' => 5],
        ['key' => hash('sha256', 'ip:' . $ip), 'limit' => 20],
    ];
}

function isLoginThrottled(PDO $pdo, array $buckets): bool
{
    $stmt = $pdo->prepare(
        'SELECT COUNT(*) FROM auth_login_attempts '
        . 'WHERE bucket_key IN (?, ?) AND locked_until > CURRENT_TIMESTAMP'
    );
    $stmt->execute([$buckets[0]['key'], $buckets[1]['key']]);

    return (int)$stmt->fetchColumn() > 0;
}

function recordLoginFailure(PDO $pdo, array $buckets): void
{
    $stmt = $pdo->prepare(
        'INSERT INTO auth_login_attempts (bucket_key, failed_attempts, window_started_at) '
        . 'VALUES (?, 1, CURRENT_TIMESTAMP) '
        . 'ON DUPLICATE KEY UPDATE '
        . 'locked_until = CASE '
        . 'WHEN window_started_at < DATE_SUB(CURRENT_TIMESTAMP, INTERVAL 15 MINUTE) '
        . 'THEN IF(1 >= ?, DATE_ADD(CURRENT_TIMESTAMP, INTERVAL 15 MINUTE), NULL) '
        . 'WHEN failed_attempts + 1 >= ? THEN DATE_ADD(CURRENT_TIMESTAMP, INTERVAL 15 MINUTE) '
        . 'ELSE locked_until END, '
        . 'failed_attempts = IF(window_started_at < DATE_SUB(CURRENT_TIMESTAMP, INTERVAL 15 MINUTE), 1, failed_attempts + 1), '
        . 'window_started_at = IF(window_started_at < DATE_SUB(CURRENT_TIMESTAMP, INTERVAL 15 MINUTE), CURRENT_TIMESTAMP, window_started_at)'
    );

    foreach ($buckets as $bucket) {
        $stmt->execute([$bucket['key'], $bucket['limit'], $bucket['limit']]);
    }
}

function clearLoginFailures(PDO $pdo, string $emailBucketKey): void
{
    $stmt = $pdo->prepare('DELETE FROM auth_login_attempts WHERE bucket_key = ?');
    $stmt->execute([$emailBucketKey]);
}
