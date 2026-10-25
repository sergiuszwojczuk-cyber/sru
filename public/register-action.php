<?php
// public/register-action.php
require_once __DIR__ . '/../src/config/session.php';
require_once __DIR__ . '/../src/config/database.php';

// Zabezpieczenie - tylko wywołanie POST jest akceptowane
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header("Location: register.php");
    exit();
}

try {
    $pdo = getDbConnection();
    
    // 1. Pobranie i czyszczenie danych
    $email = trim($_POST['email']);
    $password = $_POST['password'];
    $firstName = trim($_POST['first_name']);
    $lastName = trim($_POST['last_name']);
    $pesel = trim($_POST['pesel']);
    $phone = trim($_POST['phone']);

    // 2. Sprawdzenie, czy email lub PESEL już istnieje
    $stmtCheck = $pdo->prepare("SELECT id FROM uzytkownicy WHERE email = ?");
    $stmtCheck->execute([$email]);
    if ($stmtCheck->rowCount() > 0) {
        $_SESSION['error'] = "Podany adres email jest już zajęty.";
        header("Location: register.php");
        exit();
    }

    $stmtPesel = $pdo->prepare("SELECT uzytkownik_id FROM pacjenci WHERE pesel = ?");
    $stmtPesel->execute([$pesel]);
    if ($stmtPesel->rowCount() > 0) {
        $_SESSION['error'] = "Podany PESEL istnieje już w naszej bazie.";
        header("Location: register.php");
        exit();
    }

    // 3. Haszowanie hasła (BARDZO WAŻNE DLA BEZPIECZEŃSTWA)
    $hashedPassword = password_hash($password, PASSWORD_BCRYPT);

    // ==============================================================
    // 4. ROZPOCZĘCIE TRANSAKCJI BAZODANOWEJ (Poziom zaawansowany)
    // ==============================================================
    $pdo->beginTransaction();

    // KROK A: Zapis do tabeli uzytkownicy
    $stmtUser = $pdo->prepare("INSERT INTO uzytkownicy (email, haslo, rola) VALUES (?, ?, 'pacjent')");
    $stmtUser->execute([$email, $hashedPassword]);
    
    // Pobranie ID nowo utworzonego użytkownika
    $newUserId = $pdo->lastInsertId();

    // KROK B: Zapis do tabeli pacjenci powiązanego z nowym kontem
    $stmtPatient = $pdo->prepare("INSERT INTO pacjenci (uzytkownik_id, imie, nazwisko, pesel, telefon) VALUES (?, ?, ?, ?, ?)");
    $stmtPatient->execute([$newUserId, $firstName, $lastName, $pesel, $phone]);

    // Jeśli oba zapytania się udały -> zatwierdzamy zmiany!
    $pdo->commit();

    // 5. Zakończenie sukcesem
    $_SESSION['success'] = "Konto zostało pomyślnie utworzone! Możesz się teraz zalogować.";
    header("Location: login.php");
    exit();

} catch (Exception $e) {
    // JEŚLI COŚ POSZŁO NIE TAK (np. błąd SQL) -> Cofamy całą transakcję!
    if (isset($pdo) && $pdo->inTransaction()) {
        $pdo->rollBack();
    }
    $_SESSION['error'] = "Wystąpił krytyczny błąd systemu: " . $e->getMessage();
    header("Location: register.php");
    exit();
}