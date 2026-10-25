<?php
require_once __DIR__ . '/session.php';

function requireLogin(): void
{
    if (!isLoggedIn()) {
        $_SESSION['error'] = 'Zaloguj się, aby otworzyć tę stronę.';
        header('Location: login.php', true, 303);
        exit;
    }
}

function requireRole(string ...$roles): void
{
    requireLogin();

    foreach ($roles as $role) {
        if (hasRole($role)) {
            return;
        }
    }

    http_response_code(403);
    $homeUrl = 'index.php';
    echo '<!doctype html><html lang="pl"><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">'
        . '<title>Brak dostępu</title><body style="font:16px system-ui,sans-serif;max-width:640px;margin:15vh auto;padding:24px;color:#183b3b">'
        . '<h1>Brak dostępu</h1><p>Twoje konto nie ma uprawnień do wyświetlenia tej strony.</p>'
        . '<p><a href="' . htmlspecialchars($homeUrl, ENT_QUOTES, 'UTF-8') . '">Wróć do strony głównej</a></p></body></html>';
    exit;
}
