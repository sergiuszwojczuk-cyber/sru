<?php
// public/register-action.php
require_once __DIR__ . '/../src/config/session.php';
require_once __DIR__ . '/../src/config/database.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header("Location: register.php");
    exit();
}

try {
    $pdo = getDbConnection();
    
    // 1. Pobranie i oczyszczenie danych z formularza
    $email     = trim($_POST['email'] ?? '');
    $password  = $_POST['password'] ?? '';
    $imie      = trim($_POST['imie'] ?? '');
    $nazwisko  = trim($_POST['nazwisko'] ?? '');
    $telefon   = trim($_POST['telefon'] ?? '');
    $dataUr    = !empty($_POST['data_urodzenia']) ? $_POST['data_urodzenia'] : null;
    $adres     = trim($_POST['adres'] ?? '');
    $miasto    = trim($_POST['miasto'] ?? '');
    $kod       = trim($_POST['kod_pocztowy'] ?? '');

    // 2. Walidacja
    if ($email === '' || $password === '' || $imie === '' || $nazwisko === '') {
        throw new Exception("Wypełnij wszystkie wymagane pola.");
    }
    
    if (strlen($password) < 8) {
        throw new Exception("Hasło musi mieć co najmniej 8 znaków.");
    }

    // 3. Sprawdzenie czy email już istnieje
    $stmtCheck = $pdo->prepare("SELECT id FROM uzytkownicy WHERE email = ?");
    $stmtCheck->execute([$email]);
    if ($stmtCheck->rowCount() > 0) {
        throw new Exception("Podany adres e-mail jest już zajęty.");
    }

    // 4. Haszowanie hasła
    $hashedPassword = password_hash($password, PASSWORD_BCRYPT);

    // ==============================================================
    // 5. TRANSAKCJA BAZODANOWA (Zapis do uzytkownicy + profil_pacjenta)
    // ==============================================================
    $pdo->beginTransaction();

    // A) Zapis podstawowy do uzytkownicy
    $stmtUser = $pdo->prepare("
        INSERT INTO uzytkownicy (imie, nazwisko, email, haslo, rola, telefon) 
        VALUES (?, ?, ?, ?, 'pacjent', ?)
    ");
    $stmtUser->execute([$imie, $nazwisko, $email, $hashedPassword, $telefon]);
    
    $newUserId = (int)$pdo->lastInsertId();

    // B) Zapis profilu do profil_pacjenta (Poprawna nazwa tabeli!)
    $stmtProfile = $pdo->prepare("
        INSERT INTO profil_pacjenta (uzytkownik_id, data_urodzenia, adres, miasto, kod_pocztowy) 
        VALUES (?, ?, ?, ?, ?)
    ");
    $stmtProfile->execute([$newUserId, $dataUr, $adres, $miasto, $kod]);

    // Zatwierdzenie transakcji
    $pdo->commit();

    $_SESSION['success'] = "Konto zostało pomyślnie utworzone! Możesz się zalogować.";
    header("Location: login.php");
    exit();

} catch (Exception $e) {
    if (isset($pdo) && $pdo->inTransaction()) {
        $pdo->rollBack();
    }
    $_SESSION['error'] = "Błąd rejestracji: " . $e->getMessage();
    header("Location: register.php");
    exit();
}