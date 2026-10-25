<?php
// public/login-action.php
require_once __DIR__ . '/../src/config/session.php';
require_once __DIR__ . '/../src/config/database.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header("Location: login.php");
    exit();
}

$email = trim($_POST['email']);
$password = $_POST['password'];

try {
    $pdo = getDbConnection();

    // 1. Wyszukanie użytkownika po e-mailu
    $stmt = $pdo->prepare("SELECT * FROM uzytkownicy WHERE email = ? AND czy_aktywny = TRUE");
    $stmt->execute([$email]);
    $user = $stmt->fetch();

    // 2. Weryfikacja użytkownika i password_verify()
    if ($user && password_verify($password, $user['haslo'])) {
        // Pomyślne logowanie! Zapisujemy kluczowe dane w sesji
        $_SESSION['user_id'] = $user['id'];
        $_SESSION['email'] = $user['email'];
        $_SESSION['role'] = $user['rola'];

        // Pobranie imienia i nazwiska w zależności od roli (Żeby widać było np. Witaj, Anna Nowak!)
        if ($user['rola'] === 'pacjent') {
            $stmtDetails = $pdo->prepare("SELECT imie, nazwisko FROM pacjenci WHERE uzytkownik_id = ?");
            $stmtDetails->execute([$user['id']]);
            $details = $stmtDetails->fetch();
            if ($details) {
                $_SESSION['user_name'] = $details['imie'] . ' ' . $details['nazwisko'];
            }
        } elseif ($user['rola'] === 'lekarz') {
            $stmtDetails = $pdo->prepare("SELECT imie, nazwisko FROM lekarze WHERE uzytkownik_id = ?");
            $stmtDetails->execute([$user['id']]);
            $details = $stmtDetails->fetch();
            if ($details) {
                $_SESSION['user_name'] = 'lek. ' . $details['imie'] . ' ' . $details['nazwisko'];
            }
        } else {
            $_SESSION['user_name'] = 'Administrator';
        }

        header("Location: index.php");
        exit();
    } else {
        $_SESSION['error'] = "Nieprawidłowy adres email lub hasło.";
        header("Location: login.php");
        exit();
    }

} catch (Exception $e) {
    $_SESSION['error'] = "Błąd systemu: " . $e->getMessage();
    header("Location: login.php");
    exit();
}