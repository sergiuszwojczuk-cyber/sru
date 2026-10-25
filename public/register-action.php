<?php
require_once __DIR__ . '/../src/config/session.php';
require_once __DIR__ . '/../src/config/database.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: register.php', true, 303);
    exit;
}

if (!hasValidCsrfToken($_POST['csrf_token'] ?? null)) {
    $_SESSION['error'] = 'Sesja formularza wygasła. Spróbuj ponownie.';
    header('Location: register.php', true, 303);
    exit;
}

$email = isset($_POST['email']) && is_string($_POST['email']) ? strtolower(trim($_POST['email'])) : '';
$password = isset($_POST['password']) && is_string($_POST['password']) ? $_POST['password'] : '';
$passwordConfirm = isset($_POST['password_confirm']) && is_string($_POST['password_confirm']) ? $_POST['password_confirm'] : '';
$imie = isset($_POST['imie']) && is_string($_POST['imie']) ? trim($_POST['imie']) : '';
$nazwisko = isset($_POST['nazwisko']) && is_string($_POST['nazwisko']) ? trim($_POST['nazwisko']) : '';
$telefon = isset($_POST['telefon']) && is_string($_POST['telefon']) ? trim($_POST['telefon']) : '';
$dataUr = isset($_POST['data_urodzenia']) && is_string($_POST['data_urodzenia']) && $_POST['data_urodzenia'] !== ''
    ? $_POST['data_urodzenia']
    : null;
$adres = isset($_POST['adres']) && is_string($_POST['adres']) ? trim($_POST['adres']) : '';
$miasto = isset($_POST['miasto']) && is_string($_POST['miasto']) ? trim($_POST['miasto']) : '';
$kod = isset($_POST['kod_pocztowy']) && is_string($_POST['kod_pocztowy']) ? trim($_POST['kod_pocztowy']) : '';
$parsedDate = $dataUr !== null ? DateTime::createFromFormat('!Y-m-d', $dataUr) : null;

if (!filter_var($email, FILTER_VALIDATE_EMAIL) || strlen($email) > 254) {
    $_SESSION['error'] = 'Podaj prawidłowy adres e-mail.';
} elseif (strlen($password) < 8 || strlen($password) > 72) {
    $_SESSION['error'] = 'Hasło musi mieć co najmniej 8 znaków i nie może przekraczać 72 bajtów.';
} elseif (!hash_equals($password, $passwordConfirm)) {
    $_SESSION['error'] = 'Hasła nie są zgodne.';
} elseif ($imie === '' || strlen($imie) > 80 || $nazwisko === '' || strlen($nazwisko) > 80) {
    $_SESSION['error'] = 'Podaj prawidłowe imię i nazwisko.';
} elseif (strlen($telefon) > 30 || strlen($adres) > 255 || strlen($miasto) > 100 || strlen($kod) > 12) {
    $_SESSION['error'] = 'Jedno z podanych pól przekracza dozwoloną długość.';
} elseif ($dataUr !== null && (!$parsedDate || $parsedDate->format('Y-m-d') !== $dataUr || $dataUr > date('Y-m-d'))) {
    $_SESSION['error'] = 'Podaj prawidłową datę urodzenia.';
} else {
    $_SESSION['error'] = null;
}

if ($_SESSION['error'] !== null) {
    header('Location: register.php', true, 303);
    exit;
}
unset($_SESSION['error']);

try {
    $pdo = getDbConnection();
    $stmtCheck = $pdo->prepare('SELECT id FROM uzytkownicy WHERE email = ? LIMIT 1');
    $stmtCheck->execute([$email]);
    if ($stmtCheck->fetchColumn() !== false) {
        $_SESSION['error'] = 'Podany adres e-mail jest już zajęty.';
        header('Location: register.php', true, 303);
        exit;
    }

    $hashedPassword = password_hash($password, PASSWORD_DEFAULT);
    $pdo->beginTransaction();

    $stmtUser = $pdo->prepare("INSERT INTO uzytkownicy (imie, nazwisko, email, haslo, rola, telefon) VALUES (?, ?, ?, ?, 'pacjent', ?)");
    $stmtUser->execute([$imie, $nazwisko, $email, $hashedPassword, $telefon]);
    $newUserId = (int)$pdo->lastInsertId();

    $stmtProfile = $pdo->prepare('INSERT INTO profil_pacjenta (uzytkownik_id, data_urodzenia, adres, miasto, kod_pocztowy) VALUES (?, ?, ?, ?, ?)');
    $stmtProfile->execute([$newUserId, $dataUr, $adres, $miasto, $kod]);
    $pdo->commit();

    $_SESSION['success'] = 'Konto zostało utworzone. Możesz się zalogować.';
    header('Location: login.php', true, 303);
    exit;
} catch (PDOException $e) {
    if (isset($pdo) && $pdo->inTransaction()) {
        $pdo->rollBack();
    }
    error_log('Registration failed: ' . $e->getMessage());
    if ($e->getCode() === '23000') {
        $_SESSION['error'] = 'Podany adres e-mail jest już zajęty.';
    } else {
        $_SESSION['error'] = 'Rejestracja jest chwilowo niedostępna. Spróbuj ponownie później.';
    }
    header('Location: register.php', true, 303);
    exit;
} catch (Exception $e) {
    if (isset($pdo) && $pdo->inTransaction()) {
        $pdo->rollBack();
    }
    error_log('Registration failed: ' . $e->getMessage());
    $_SESSION['error'] = 'Rejestracja jest chwilowo niedostępna. Spróbuj ponownie później.';
    header('Location: register.php', true, 303);
    exit;
}