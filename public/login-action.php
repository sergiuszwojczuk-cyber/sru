<?php
// public/login-action.php
require_once __DIR__ . '/../src/config/session.php';
require_once __DIR__ . '/../src/config/database.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header("Location: login.php");
    exit();
}

$email = trim($_POST['email'] ?? '');
$password = $_POST['password'] ?? '';

try {
    $pdo = getDbConnection();

    // Szukamy użytkownika o podanym mailu, który jest aktywny
    $stmt = $pdo->prepare("SELECT id, imie, nazwisko, email, haslo, rola FROM uzytkownicy WHERE email = ? AND czy_aktywny = 1");
    $stmt->execute([$email]);
    $user = $stmt->fetch();

    // Weryfikacja hasła
    if ($user && password_verify($password, $user['haslo'])) {
        // Logowanie udane! Zapisujemy dane do sesji
        $_SESSION['user_id'] = $user['id'];
        $_SESSION['email'] = $user['email'];
        $_SESSION['role'] = $user['rola']; // pacjent / lekarz / administrator

        // Budujemy nazwę wyświetlaną na pasku
        if ($user['rola'] === 'lekarz') {
            $_SESSION['user_name'] = 'lek. ' . $user['imie'] . ' ' . $user['nazwisko'];
        } else {
            $_SESSION['user_name'] = $user['imie'] . ' ' . $user['nazwisko'];
        }

        header("Location: index.php");
        exit();
    } else {
        $_SESSION['error'] = "Nieprawidłowy adres e-mail lub hasło.";
        header("Location: login.php");
        exit();
    }

} catch (Exception $e) {
    $_SESSION['error'] = "Błąd systemu: " . $e->getMessage();
    header("Location: login.php");
    exit();
}