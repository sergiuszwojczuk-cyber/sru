<?php
// src/config/session.php

if (session_status() === PHP_SESSION_NONE) {
    ini_set('session.use_strict_mode', '1');
    ini_set('session.use_only_cookies', '1');
    ini_set('session.use_trans_sid', '0');
    session_set_cookie_params([
        'httponly' => true,
        'secure' => (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
            || ($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https',
        'samesite' => 'Lax',
    ]);
    session_start();
}

if (isset($_SESSION['user_id'])) {
    $now = time();
    $lastActivity = (int)($_SESSION['last_activity'] ?? $now);
    $sessionCreated = (int)($_SESSION['session_created'] ?? $now);

    if ($now - $lastActivity > 1800 || $now - $sessionCreated > 28800) {
        $_SESSION = [];
        session_regenerate_id(true);
        $_SESSION['error'] = 'Sesja wygasła. Zaloguj się ponownie.';
    } else {
        $_SESSION['last_activity'] = $now;
    }
}

function isLoggedIn(): bool
{
    return isset($_SESSION['user_id']);
}

function hasRole(string $role): bool
{
    $roleAliases = [
        'klient' => ['klient', 'pacjent'],
        'pacjent' => ['klient', 'pacjent'],
        'pracownik' => ['pracownik', 'lekarz'],
        'lekarz' => ['pracownik', 'lekarz'],
        'administrator' => ['administrator'],
    ];

    return isset($_SESSION['role'])
        && in_array($_SESSION['role'], $roleAliases[$role] ?? [$role], true);
}

function userRoleLabel(string $role): string
{
    return match ($role) {
        'pacjent', 'klient' => 'Klient',
        'lekarz', 'pracownik' => 'Pracownik',
        'administrator' => 'Administrator',
        default => 'Użytkownik',
    };
}

function csrfToken(): string
{
    if (!isset($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }

    return $_SESSION['csrf_token'];
}

function hasValidCsrfToken(mixed $token): bool
{
    return is_string($token)
        && isset($_SESSION['csrf_token'])
        && hash_equals($_SESSION['csrf_token'], $token);
}