<?php
require_once __DIR__ . '/../src/config/auth.php';
require_once __DIR__ . '/../src/config/database.php';
requireLogin();

$pdo = getDbConnection();
$userId = (int)$_SESSION['user_id'];
$stmt = $pdo->prepare(
    'SELECT u.imie, u.nazwisko, u.email, u.telefon, p.data_urodzenia, p.adres, p.miasto, p.kod_pocztowy '
    . 'FROM uzytkownicy u LEFT JOIN profil_pacjenta p ON p.uzytkownik_id = u.id WHERE u.id = ?'
);
$stmt->execute([$userId]);
$profile = $stmt->fetch();

if (!$profile) {
    http_response_code(404);
    exit('Nie znaleziono profilu użytkownika.');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!hasValidCsrfToken($_POST['csrf_token'] ?? null)) {
        $_SESSION['error'] = 'Sesja formularza wygasła. Odśwież stronę i spróbuj ponownie.';
        header('Location: profile.php', true, 303);
        exit;
    }

    $action = is_string($_POST['action'] ?? null) ? $_POST['action'] : '';

    if ($action === 'profile') {
        $imie = is_string($_POST['imie'] ?? null) ? trim($_POST['imie']) : '';
        $nazwisko = is_string($_POST['nazwisko'] ?? null) ? trim($_POST['nazwisko']) : '';
        $email = is_string($_POST['email'] ?? null) ? strtolower(trim($_POST['email'])) : '';
        $telefon = is_string($_POST['telefon'] ?? null) ? trim($_POST['telefon']) : '';
        $adres = is_string($_POST['adres'] ?? null) ? trim($_POST['adres']) : '';
        $miasto = is_string($_POST['miasto'] ?? null) ? trim($_POST['miasto']) : '';
        $kod = is_string($_POST['kod_pocztowy'] ?? null) ? trim($_POST['kod_pocztowy']) : '';
        $birthDate = is_string($_POST['data_urodzenia'] ?? null) && $_POST['data_urodzenia'] !== ''
            ? $_POST['data_urodzenia']
            : null;
        $parsedBirthDate = $birthDate !== null ? DateTime::createFromFormat('!Y-m-d', $birthDate) : null;
        $length = static fn (string $value): int => function_exists('mb_strlen') ? mb_strlen($value, 'UTF-8') : strlen($value);

        if ($imie === '' || $length($imie) > 80 || $nazwisko === '' || $length($nazwisko) > 80) {
            $_SESSION['error'] = 'Imię i nazwisko są wymagane i nie mogą przekraczać 80 znaków.';
        } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL) || $length($email) > 254) {
            $_SESSION['error'] = 'Podaj prawidłowy adres e-mail.';
        } elseif ($length($telefon) > 30 || $length($adres) > 255 || $length($miasto) > 100 || $length($kod) > 12) {
            $_SESSION['error'] = 'Jedno z pól przekracza maksymalną dozwoloną długość.';
        } elseif ($birthDate !== null && (!$parsedBirthDate || $parsedBirthDate->format('Y-m-d') !== $birthDate || $birthDate > date('Y-m-d'))) {
            $_SESSION['error'] = 'Podaj prawidłową datę urodzenia.';
        } else {
            try {
                $pdo->beginTransaction();
                $updateUser = $pdo->prepare('UPDATE uzytkownicy SET imie = ?, nazwisko = ?, email = ?, telefon = ? WHERE id = ?');
                $updateUser->execute([$imie, $nazwisko, $email, $telefon, $userId]);

                if (hasRole('klient')) {
                    $updatePatient = $pdo->prepare(
                        'INSERT INTO profil_pacjenta (uzytkownik_id, data_urodzenia, adres, miasto, kod_pocztowy) '
                        . 'VALUES (?, ?, ?, ?, ?) ON DUPLICATE KEY UPDATE '
                        . 'data_urodzenia = VALUES(data_urodzenia), adres = VALUES(adres), '
                        . 'miasto = VALUES(miasto), kod_pocztowy = VALUES(kod_pocztowy)'
                    );
                    $updatePatient->execute([$userId, $birthDate, $adres, $miasto, $kod]);
                }
                $pdo->commit();

                $_SESSION['email'] = $email;
                $_SESSION['user_name'] = (hasRole('pracownik') ? 'lek. ' : '') . $imie . ' ' . $nazwisko;
                $_SESSION['success'] = 'Dane profilu zostały zapisane.';
                header('Location: profile.php', true, 303);
                exit;
            } catch (PDOException $e) {
                if ($pdo->inTransaction()) {
                    $pdo->rollBack();
                }
                error_log('Profile update failed: ' . $e->getMessage());
                $_SESSION['error'] = $e->getCode() === '23000'
                    ? 'Podany adres e-mail jest już używany przez inne konto.'
                    : 'Nie udało się zapisać profilu. Spróbuj ponownie później.';
            }
        }
    } elseif ($action === 'password') {
        $currentPassword = is_string($_POST['current_password'] ?? null) ? $_POST['current_password'] : '';
        $newPassword = is_string($_POST['new_password'] ?? null) ? $_POST['new_password'] : '';
        $confirmPassword = is_string($_POST['confirm_password'] ?? null) ? $_POST['confirm_password'] : '';

        $passwordStmt = $pdo->prepare('SELECT haslo FROM uzytkownicy WHERE id = ?');
        $passwordStmt->execute([$userId]);
        $currentHash = $passwordStmt->fetchColumn();

        if (!is_string($currentHash) || !password_verify($currentPassword, $currentHash)) {
            $_SESSION['error'] = 'Aktualne hasło jest nieprawidłowe.';
        } elseif (strlen($newPassword) < 8 || strlen($newPassword) > 72) {
            $_SESSION['error'] = 'Nowe hasło musi mieć co najmniej 8 znaków i maksymalnie 72 bajty.';
        } elseif (!hash_equals($newPassword, $confirmPassword)) {
            $_SESSION['error'] = 'Nowe hasła nie są zgodne.';
        } else {
            $updatePassword = $pdo->prepare('UPDATE uzytkownicy SET haslo = ? WHERE id = ?');
            $updatePassword->execute([password_hash($newPassword, PASSWORD_DEFAULT), $userId]);
            session_regenerate_id(true);
            $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
            $_SESSION['success'] = 'Hasło zostało zmienione.';
            header('Location: profile.php', true, 303);
            exit;
        }
    } else {
        $_SESSION['error'] = 'Nieprawidłowa operacja.';
    }

    header('Location: profile.php', true, 303);
    exit;
}

$pageTitle = 'Mój profil | Przychodnia Pochodnia';
require_once __DIR__ . '/../src/templates/header.php';
?>
<section class="content-section account-layout">
    <div class="section-heading">
        <p class="eyebrow">Ustawienia konta</p>
        <h1>Mój profil</h1>
        <p>Aktualizuj swoje dane i dbaj o bezpieczeństwo konta.</p>
    </div>
    <?php if (isset($_SESSION['success'])): ?>
        <div class="alert alert-success" role="status"><?= htmlspecialchars($_SESSION['success'], ENT_QUOTES, 'UTF-8'); unset($_SESSION['success']); ?></div>
    <?php endif; ?>
    <?php if (isset($_SESSION['error'])): ?>
        <div class="alert alert-danger" role="alert"><?= htmlspecialchars($_SESSION['error'], ENT_QUOTES, 'UTF-8'); unset($_SESSION['error']); ?></div>
    <?php endif; ?>

    <div class="profile-grid">
        <section class="auth-card">
            <h2 class="form-section-title mt-0">Dane podstawowe</h2>
            <form method="post" action="profile.php">
                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(csrfToken(), ENT_QUOTES, 'UTF-8') ?>">
                <input type="hidden" name="action" value="profile">
                <div class="row">
                    <div class="col-md-6 mb-3"><label class="form-label" for="imie">Imię</label><input class="form-control" id="imie" name="imie" maxlength="80" required value="<?= htmlspecialchars($profile['imie'], ENT_QUOTES, 'UTF-8') ?>"></div>
                    <div class="col-md-6 mb-3"><label class="form-label" for="nazwisko">Nazwisko</label><input class="form-control" id="nazwisko" name="nazwisko" maxlength="80" required value="<?= htmlspecialchars($profile['nazwisko'], ENT_QUOTES, 'UTF-8') ?>"></div>
                </div>
                <div class="mb-3"><label class="form-label" for="email">Adres e-mail</label><input class="form-control" id="email" type="email" name="email" maxlength="254" required value="<?= htmlspecialchars($profile['email'], ENT_QUOTES, 'UTF-8') ?>"></div>
                <div class="mb-3"><label class="form-label" for="telefon">Telefon</label><input class="form-control" id="telefon" type="tel" name="telefon" maxlength="30" value="<?= htmlspecialchars($profile['telefon'] ?? '', ENT_QUOTES, 'UTF-8') ?>"></div>
                <?php if (hasRole('klient')): ?>
                    <div class="mb-3"><label class="form-label" for="data_urodzenia">Data urodzenia</label><input class="form-control" id="data_urodzenia" type="date" name="data_urodzenia" value="<?= htmlspecialchars($profile['data_urodzenia'] ?? '', ENT_QUOTES, 'UTF-8') ?>"></div>
                    <div class="mb-3"><label class="form-label" for="adres">Adres</label><input class="form-control" id="adres" name="adres" maxlength="255" value="<?= htmlspecialchars($profile['adres'] ?? '', ENT_QUOTES, 'UTF-8') ?>"></div>
                    <div class="row">
                        <div class="col-md-8 mb-3"><label class="form-label" for="miasto">Miasto</label><input class="form-control" id="miasto" name="miasto" maxlength="100" value="<?= htmlspecialchars($profile['miasto'] ?? '', ENT_QUOTES, 'UTF-8') ?>"></div>
                        <div class="col-md-4 mb-3"><label class="form-label" for="kod_pocztowy">Kod pocztowy</label><input class="form-control" id="kod_pocztowy" name="kod_pocztowy" maxlength="12" value="<?= htmlspecialchars($profile['kod_pocztowy'] ?? '', ENT_QUOTES, 'UTF-8') ?>"></div>
                    </div>
                <?php endif; ?>
                <button class="button button-primary" type="submit">Zapisz dane</button>
            </form>
        </section>

        <section class="auth-card">
            <h2 class="form-section-title mt-0">Zmiana hasła</h2>
            <p class="text-muted small">Podaj aktualne hasło, a następnie ustaw nowe.</p>
            <form method="post" action="profile.php">
                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(csrfToken(), ENT_QUOTES, 'UTF-8') ?>">
                <input type="hidden" name="action" value="password">
                <div class="mb-3"><label class="form-label" for="current_password">Aktualne hasło</label><input class="form-control" id="current_password" type="password" name="current_password" autocomplete="current-password" required></div>
                <div class="mb-3"><label class="form-label" for="new_password">Nowe hasło</label><input class="form-control" id="new_password" type="password" name="new_password" minlength="8" maxlength="72" autocomplete="new-password" required></div>
                <div class="mb-4"><label class="form-label" for="confirm_password">Powtórz nowe hasło</label><input class="form-control" id="confirm_password" type="password" name="confirm_password" minlength="8" maxlength="72" autocomplete="new-password" required></div>
                <button class="button button-primary" type="submit">Zmień hasło</button>
            </form>
        </section>
    </div>
</section>
<?php require_once __DIR__ . '/../src/templates/footer.php'; ?>
