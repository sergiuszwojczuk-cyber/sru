<?php
require_once __DIR__ . '/../src/config/session.php';
require_once __DIR__ . '/../src/config/database.php';
require_once __DIR__ . '/../src/models/LoginThrottle.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: login.php', true, 303);
    exit;
}

if (!hasValidCsrfToken($_POST['csrf_token'] ?? null)) {
    $_SESSION['error'] = 'Sesja formularza wygasła. Spróbuj ponownie.';
    header('Location: login.php', true, 303);
    exit;
}

$email = isset($_POST['email']) && is_string($_POST['email']) ? strtolower(trim($_POST['email'])) : '';
$password = isset($_POST['password']) && is_string($_POST['password']) ? $_POST['password'] : '';

if (!filter_var($email, FILTER_VALIDATE_EMAIL) || $password === '') {
    $_SESSION['error'] = 'Nieprawidłowy adres e-mail lub hasło.';
    header('Location: login.php', true, 303);
    exit;
}

try {
    $pdo = getDbConnection();
    $ip = filter_var($_SERVER['REMOTE_ADDR'] ?? '', FILTER_VALIDATE_IP) ?: 'unknown';
    $buckets = loginThrottleKeys($email, $ip);

    if (isLoginThrottled($pdo, $buckets)) {
        $_SESSION['error'] = 'Zbyt wiele nieudanych prób. Odczekaj 15 minut i spróbuj ponownie.';
        header('Location: login.php', true, 303);
        exit;
    }

    $stmt = $pdo->prepare('SELECT id, imie, nazwisko, email, haslo, rola FROM uzytkownicy WHERE email = ? AND czy_aktywny = 1 LIMIT 1');
    $stmt->execute([$email]);
    $user = $stmt->fetch();

    if ($user && password_verify($password, $user['haslo'])) {
        if (password_needs_rehash($user['haslo'], PASSWORD_DEFAULT)) {
            $update = $pdo->prepare('UPDATE uzytkownicy SET haslo = ? WHERE id = ?');
            $update->execute([password_hash($password, PASSWORD_DEFAULT), $user['id']]);
        }
        clearLoginFailures($pdo, $buckets[0]['key']);
        session_regenerate_id(true);
        $_SESSION['user_id'] = $user['id'];
        $_SESSION['email'] = $user['email'];
        $_SESSION['role'] = $user['rola'];
        $_SESSION['session_created'] = time();
        $_SESSION['last_activity'] = time();

        $_SESSION['user_name'] = ($user['rola'] === 'lekarz' ? 'lek. ' : '')
            . $user['imie'] . ' ' . $user['nazwisko'];
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));

        header('Location: index.php', true, 303);
        exit;
    }

    recordLoginFailure($pdo, $buckets);
    $_SESSION['error'] = 'Nieprawidłowy adres e-mail lub hasło.';
    header('Location: login.php', true, 303);
    exit;
} catch (Exception $e) {
    error_log('Login failed: ' . $e->getMessage());
    $_SESSION['error'] = 'Logowanie jest chwilowo niedostępne. Spróbuj ponownie później.';
    header('Location: login.php', true, 303);
    exit;
}