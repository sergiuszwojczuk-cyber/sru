<?php
require_once __DIR__ . '/../src/config/auth.php';
require_once __DIR__ . '/../src/config/database.php';
requireRole('administrator');

$pdo = getDbConnection();
$length = static fn (string $value): int => function_exists('mb_strlen')
    ? mb_strlen($value, 'UTF-8')
    : (preg_match_all('/./us', $value, $matches) === false ? strlen($value) : count($matches[0]));
$escape = static fn (mixed $value): string => htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
$optionalId = static function (mixed $value, string $field): ?int {
    if ($value === null || $value === '') {
        return null;
    }

    $id = filter_var($value, FILTER_VALIDATE_INT);
    if ($id === false || $id < 1) {
        throw new InvalidArgumentException('Nieprawidłowy identyfikator: ' . $field . '.');
    }

    return $id;
};

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!hasValidCsrfToken($_POST['csrf_token'] ?? null)) {
        $_SESSION['error'] = 'Sesja formularza wygasła. Odśwież stronę i spróbuj ponownie.';
        header('Location: admin.php', true, 303);
        exit;
    }

    $action = is_string($_POST['action'] ?? null) ? $_POST['action'] : '';
    try {
        if ($action === 'category_save') {
            $categoryId = $optionalId($_POST['category_id'] ?? '', 'kategoria');
            $name = is_string($_POST['name'] ?? null) ? trim($_POST['name']) : '';
            $description = is_string($_POST['description'] ?? null) ? trim($_POST['description']) : '';

            if ($name === '' || $length($name) > 100 || $length($description) > 10000) {
                throw new InvalidArgumentException('Podaj nazwę kategorii (maks. 100 znaków); opis może mieć maks. 10 000 znaków.');
            }

            if ($categoryId !== null) {
                $stmt = $pdo->prepare('UPDATE specjalizacje SET nazwa = ?, opis = ? WHERE id = ?');
                $stmt->execute([$name, $description !== '' ? $description : null, $categoryId]);
                $_SESSION['success'] = 'Kategoria została zaktualizowana.';
            } else {
                $stmt = $pdo->prepare('INSERT INTO specjalizacje (nazwa, opis) VALUES (?, ?)');
                $stmt->execute([$name, $description !== '' ? $description : null]);
                $_SESSION['success'] = 'Kategoria została dodana.';
            }
        } elseif ($action === 'category_delete') {
            $categoryId = filter_var($_POST['category_id'] ?? '', FILTER_VALIDATE_INT);
            if (!$categoryId || $categoryId < 1) {
                throw new InvalidArgumentException('Nieprawidłowa kategoria.');
            }
            $stmt = $pdo->prepare('DELETE FROM specjalizacje WHERE id = ?');
            $stmt->execute([$categoryId]);
            $_SESSION['success'] = 'Kategoria została usunięta.';
        } elseif ($action === 'service_save') {
            $serviceId = $optionalId($_POST['service_id'] ?? '', 'usługa');
            $categoryId = filter_var($_POST['category_id'] ?? '', FILTER_VALIDATE_INT);
            $name = is_string($_POST['name'] ?? null) ? trim($_POST['name']) : '';
            $description = is_string($_POST['description'] ?? null) ? trim($_POST['description']) : '';
            $duration = filter_var($_POST['duration'] ?? '', FILTER_VALIDATE_INT);
            $price = is_string($_POST['price'] ?? null) ? trim($_POST['price']) : '';
            $active = isset($_POST['active']) ? 1 : 0;

            if (!$categoryId || $categoryId < 1
                || $name === '' || $length($name) > 160 || $length($description) > 10000
                || $duration === false || $duration < 1 || $duration > 65535
                || !preg_match('/^\d{1,6}(?:[.,]\d{1,2})?$/', $price)
                || (float)str_replace(',', '.', $price) > 999999.99) {
                throw new InvalidArgumentException('Sprawdź kategorię, nazwę, czas trwania (1–65 535 minut) i cenę (0–999 999,99 zł).');
            }

            $price = number_format((float)str_replace(',', '.', $price), 2, '.', '');
            $categoryCheck = $pdo->prepare('SELECT id FROM specjalizacje WHERE id = ?');
            $categoryCheck->execute([$categoryId]);
            if ($categoryCheck->fetchColumn() === false) {
                throw new InvalidArgumentException('Wybrana kategoria nie istnieje.');
            }

            $pdo->beginTransaction();
            if ($serviceId !== null) {
                $current = $pdo->prepare('SELECT specjalizacja_id FROM uslugi WHERE id = ? FOR UPDATE');
                $current->execute([$serviceId]);
                $oldCategoryId = $current->fetchColumn();
                if ($oldCategoryId === false) {
                    throw new InvalidArgumentException('Usługa nie istnieje.');
                }

                if ((int)$oldCategoryId !== $categoryId) {
                    $reservationCheck = $pdo->prepare('SELECT COUNT(*) FROM wizyty WHERE usluga_id = ?');
                    $reservationCheck->execute([$serviceId]);
                    if ((int)$reservationCheck->fetchColumn() > 0) {
                        throw new InvalidArgumentException('Nie można zmienić kategorii usługi, która ma przypisane wizyty.');
                    }

                    $removeAssignments = $pdo->prepare('DELETE FROM lekarz_usluga WHERE usluga_id = ?');
                    $removeAssignments->execute([$serviceId]);
                }

                $stmt = $pdo->prepare(
                    'UPDATE uslugi SET specjalizacja_id = ?, nazwa = ?, opis = ?, '
                    . 'czas_trwania_minut = ?, cena = ?, czy_aktywny = ? WHERE id = ?'
                );
                $stmt->execute([$categoryId, $name, $description !== '' ? $description : null, $duration, $price, $active, $serviceId]);
                $_SESSION['success'] = 'Usługa została zaktualizowana.';
            } else {
                $stmt = $pdo->prepare(
                    'INSERT INTO uslugi (specjalizacja_id, nazwa, opis, czas_trwania_minut, cena, czy_aktywny) '
                    . 'VALUES (?, ?, ?, ?, ?, ?)'
                );
                $stmt->execute([$categoryId, $name, $description !== '' ? $description : null, $duration, $price, $active]);
                $serviceId = (int)$pdo->lastInsertId();
                $_SESSION['success'] = 'Usługa została dodana.';
            }

            $assignDoctors = $pdo->prepare(
                'INSERT IGNORE INTO lekarz_usluga (lekarz_id, usluga_id) '
                . 'SELECT ls.lekarz_id, ? FROM lekarz_specjalizacja ls '
                . 'JOIN uzytkownicy u ON u.id = ls.lekarz_id AND u.czy_aktywny = 1 '
                . 'WHERE ls.specjalizacja_id = ?'
            );
            $assignDoctors->execute([$serviceId, $categoryId]);
            $pdo->commit();
        } elseif ($action === 'service_delete') {
            $serviceId = filter_var($_POST['service_id'] ?? '', FILTER_VALIDATE_INT);
            if (!$serviceId || $serviceId < 1) {
                throw new InvalidArgumentException('Nieprawidłowa usługa.');
            }
            $reservationCheck = $pdo->prepare('SELECT COUNT(*) FROM wizyty WHERE usluga_id = ?');
            $reservationCheck->execute([$serviceId]);
            if ((int)$reservationCheck->fetchColumn() > 0) {
                throw new InvalidArgumentException('Nie można usunąć usługi, do której przypisano wizyty.');
            }

            $pdo->beginTransaction();
            $removeAssignments = $pdo->prepare('DELETE FROM lekarz_usluga WHERE usluga_id = ?');
            $removeAssignments->execute([$serviceId]);
            $stmt = $pdo->prepare('DELETE FROM uslugi WHERE id = ?');
            $stmt->execute([$serviceId]);
            $pdo->commit();
            $_SESSION['success'] = 'Usługa została usunięta.';
        } elseif ($action === 'employee_create') {
            $firstName = is_string($_POST['first_name'] ?? null) ? trim($_POST['first_name']) : '';
            $lastName = is_string($_POST['last_name'] ?? null) ? trim($_POST['last_name']) : '';
            $email = is_string($_POST['email'] ?? null) ? strtolower(trim($_POST['email'])) : '';
            $phone = is_string($_POST['phone'] ?? null) ? trim($_POST['phone']) : '';
            $password = is_string($_POST['password'] ?? null) ? $_POST['password'] : '';
            $license = is_string($_POST['license'] ?? null) ? trim($_POST['license']) : '';
            $categoryId = filter_var($_POST['category_id'] ?? '', FILTER_VALIDATE_INT);
            $duration = filter_var($_POST['default_duration'] ?? '', FILTER_VALIDATE_INT);

            if ($firstName === '' || $length($firstName) > 80 || $lastName === '' || $length($lastName) > 80
                || !filter_var($email, FILTER_VALIDATE_EMAIL) || $length($email) > 254
                || $length($phone) > 30 || strlen($password) < 8 || strlen($password) > 72
                || $license === '' || $length($license) > 40
                || !$categoryId || $categoryId < 1
                || $duration === false || $duration < 1 || $duration > 65535) {
                throw new InvalidArgumentException('Uzupełnij poprawnie wszystkie wymagane dane pracownika. Hasło musi mieć 8–72 bajty.');
            }

            $pdo->beginTransaction();
            $categoryCheck = $pdo->prepare('SELECT id FROM specjalizacje WHERE id = ?');
            $categoryCheck->execute([$categoryId]);
            if ($categoryCheck->fetchColumn() === false) {
                throw new InvalidArgumentException('Wybrana kategoria nie istnieje.');
            }
            $clinicId = $pdo->query('SELECT id FROM placowki WHERE czy_aktywny = 1 ORDER BY id LIMIT 1')->fetchColumn();
            if ($clinicId === false) {
                throw new RuntimeException('Dodaj aktywną placówkę w bazie przed utworzeniem pracownika.');
            }

            $insertUser = $pdo->prepare(
                "INSERT INTO uzytkownicy (imie, nazwisko, email, haslo, rola, telefon) "
                . "VALUES (?, ?, ?, ?, 'lekarz', ?)"
            );
            $insertUser->execute([$firstName, $lastName, $email, password_hash($password, PASSWORD_DEFAULT), $phone !== '' ? $phone : null]);
            $employeeId = (int)$pdo->lastInsertId();

            $insertProfile = $pdo->prepare(
                'INSERT INTO profil_lekarza (uzytkownik_id, numer_pwz, domyslny_czas_wizyty) VALUES (?, ?, ?)'
            );
            $insertProfile->execute([$employeeId, $license, $duration]);

            $assignSpecialty = $pdo->prepare('INSERT INTO lekarz_specjalizacja (lekarz_id, specjalizacja_id) VALUES (?, ?)');
            $assignSpecialty->execute([$employeeId, $categoryId]);
            $assignClinic = $pdo->prepare('INSERT INTO lekarz_placowka (lekarz_id, placowka_id) VALUES (?, ?)');
            $assignClinic->execute([$employeeId, $clinicId]);

            $assignServices = $pdo->prepare(
                'INSERT INTO lekarz_usluga (lekarz_id, usluga_id) '
                . 'SELECT ?, id FROM uslugi WHERE specjalizacja_id = ? AND czy_aktywny = 1'
            );
            $assignServices->execute([$employeeId, $categoryId]);
            $pdo->commit();
            $_SESSION['success'] = 'Konto pracownika zostało utworzone i przypisane do placówki oraz usług kategorii.';
        } elseif ($action === 'employee_toggle') {
            $employeeId = filter_var($_POST['employee_id'] ?? '', FILTER_VALIDATE_INT);
            $active = filter_var($_POST['active'] ?? '', FILTER_VALIDATE_INT);
            if (!$employeeId || $employeeId < 1 || !in_array($active, [0, 1], true)) {
                throw new InvalidArgumentException('Nieprawidłowe dane pracownika.');
            }
            $stmt = $pdo->prepare("UPDATE uzytkownicy SET czy_aktywny = ? WHERE id = ? AND rola = 'lekarz'");
            $stmt->execute([$active, $employeeId]);
            $_SESSION['success'] = 'Status pracownika został zmieniony.';
        } else {
            throw new InvalidArgumentException('Nieprawidłowa operacja.');
        }
    } catch (InvalidArgumentException $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        $_SESSION['error'] = $e->getMessage();
    } catch (PDOException $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        error_log('Admin operation failed: ' . $e->getMessage());
        $_SESSION['error'] = $e->getCode() === '23000'
            ? 'Nie można wykonać operacji: rekord jest już używany albo istnieje wpis o tej samej nazwie, e-mailu lub numerze PWZ.'
            : 'Nie udało się wykonać operacji. Spróbuj ponownie później.';
    } catch (RuntimeException $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        error_log('Admin operation failed: ' . $e->getMessage());
        $_SESSION['error'] = $e->getMessage();
    }

    header('Location: admin.php', true, 303);
    exit;
}

$counts = $pdo->query(
    'SELECT COUNT(*) AS users, '
    . "SUM(rola = 'pacjent') AS patients, "
    . "SUM(rola = 'lekarz') AS employees, "
    . "SUM(rola = 'administrator') AS administrators FROM uzytkownicy"
)->fetch();
$users = $pdo->query(
    'SELECT id, imie, nazwisko, email, rola, czy_aktywny, utworzono_at '
    . 'FROM uzytkownicy ORDER BY utworzono_at DESC, id DESC LIMIT 100'
)->fetchAll();
$categories = $pdo->query(
    'SELECT s.id, s.nazwa, s.opis, COUNT(u.id) AS service_count '
    . 'FROM specjalizacje s LEFT JOIN uslugi u ON u.specjalizacja_id = s.id '
    . 'GROUP BY s.id, s.nazwa, s.opis ORDER BY s.nazwa'
)->fetchAll();
$services = $pdo->query(
    'SELECT u.id, u.specjalizacja_id, u.nazwa, u.opis, u.czas_trwania_minut, u.cena, u.czy_aktywny, s.nazwa AS category_name '
    . 'FROM uslugi u JOIN specjalizacje s ON s.id = u.specjalizacja_id ORDER BY s.nazwa, u.nazwa'
)->fetchAll();
$employees = $pdo->query(
    "SELECT u.id, u.imie, u.nazwisko, u.email, u.telefon, u.czy_aktywny, pl.numer_pwz, "
    . "GROUP_CONCAT(DISTINCT s.nazwa ORDER BY s.nazwa SEPARATOR ', ') AS categories "
    . 'FROM uzytkownicy u JOIN profil_lekarza pl ON pl.uzytkownik_id = u.id '
    . 'LEFT JOIN lekarz_specjalizacja ls ON ls.lekarz_id = u.id '
    . 'LEFT JOIN specjalizacje s ON s.id = ls.specjalizacja_id '
    . "WHERE u.rola = 'lekarz' GROUP BY u.id, pl.numer_pwz ORDER BY u.nazwisko, u.imie"
)->fetchAll();
$appointments = $pdo->query(
    'SELECT w.id, w.data_wizyty, w.godzina_wizyty, w.czas_trwania_minut, w.cena, w.status, '
    . 'CONCAT(p.imie, \' \', p.nazwisko) AS patient_name, '
    . 'CONCAT(d.imie, \' \', d.nazwisko) AS doctor_name, us.nazwa AS service_name, pl.nazwa AS clinic_name '
    . 'FROM wizyty w JOIN uzytkownicy p ON p.id = w.pacjent_id '
    . 'JOIN uzytkownicy d ON d.id = w.lekarz_id JOIN uslugi us ON us.id = w.usluga_id '
    . 'JOIN placowki pl ON pl.id = w.placowka_id '
    . 'ORDER BY w.data_wizyty DESC, w.godzina_wizyty DESC, w.id DESC LIMIT 100'
)->fetchAll();
$editingCategoryId = filter_input(INPUT_GET, 'edit_category', FILTER_VALIDATE_INT) ?: 0;
$editingServiceId = filter_input(INPUT_GET, 'edit_service', FILTER_VALIDATE_INT) ?: 0;
$editingCategory = null;
$editingService = null;
foreach ($categories as $category) {
    if ((int)$category['id'] === $editingCategoryId) {
        $editingCategory = $category;
        break;
    }
}
foreach ($services as $service) {
    if ((int)$service['id'] === $editingServiceId) {
        $editingService = $service;
        break;
    }
}

$pageTitle = 'Panel administratora | Przychodnia Pochodnia';
require_once __DIR__ . '/../src/templates/header.php';
?>
<section class="content-section account-layout admin-page">
    <div class="section-heading">
        <p class="eyebrow">Zarządzanie systemem</p>
        <h1>Panel administratora</h1>
        <p>Zarządzaj kategoriami, ofertą usług, pracownikami i wizytami.</p>
    </div>
    <?php if (isset($_SESSION['success'])): ?>
        <div class="alert alert-success" role="status"><?= $escape($_SESSION['success']); unset($_SESSION['success']); ?></div>
    <?php endif; ?>
    <?php if (isset($_SESSION['error'])): ?>
        <div class="alert alert-danger" role="alert"><?= $escape($_SESSION['error']); unset($_SESSION['error']); ?></div>
    <?php endif; ?>

    <div class="admin-stats">
        <article class="feature-card"><span class="feature-number">KONTA</span><h2><?= (int)$counts['users'] ?></h2><p>Użytkownicy ogółem</p></article>
        <article class="feature-card"><span class="feature-number">PACJENCI</span><h2><?= (int)$counts['patients'] ?></h2><p>Pacjenci przychodni</p></article>
        <article class="feature-card"><span class="feature-number">PERSONEL</span><h2><?= (int)$counts['employees'] ?></h2><p>Konta pracowników</p></article>
        <article class="feature-card"><span class="feature-number">ADMIN</span><h2><?= (int)$counts['administrators'] ?></h2><p>Administratorzy</p></article>
    </div>

    <section class="auth-card admin-section" id="categories">
        <h2 class="form-section-title mt-0"><?= $editingCategory ? 'Edytuj kategorię' : 'Dodaj kategorię' ?></h2>
        <form method="post" action="admin.php#categories" class="admin-form-grid">
            <input type="hidden" name="csrf_token" value="<?= $escape(csrfToken()) ?>">
            <input type="hidden" name="action" value="category_save">
            <input type="hidden" name="category_id" value="<?= $editingCategory ? (int)$editingCategory['id'] : '' ?>">
            <div><label class="form-label" for="category-name">Nazwa kategorii *</label><input class="form-control" id="category-name" name="name" maxlength="100" required value="<?= $escape($editingCategory['nazwa'] ?? '') ?>"></div>
            <div><label class="form-label" for="category-description">Opis</label><textarea class="form-control" id="category-description" name="description" rows="2" maxlength="10000"><?= $escape($editingCategory['opis'] ?? '') ?></textarea></div>
            <div class="admin-form-actions"><button class="button button-primary button-small" type="submit"><?= $editingCategory ? 'Zapisz kategorię' : 'Dodaj kategorię' ?></button><?php if ($editingCategory): ?><a class="button button-outline button-small" href="admin.php#categories">Anuluj</a><?php endif; ?></div>
        </form>
        <div class="table-responsive admin-table-wrap">
            <table class="table align-middle"><thead><tr><th>Kategoria</th><th>Usługi</th><th>Akcje</th></tr></thead><tbody>
            <?php foreach ($categories as $category): ?>
                <tr><td><strong><?= $escape($category['nazwa']) ?></strong><?php if ($category['opis']): ?><br><span class="text-muted"><?= $escape($category['opis']) ?></span><?php endif; ?></td><td><?= (int)$category['service_count'] ?></td><td class="admin-actions"><a class="button button-outline button-small" href="admin.php?edit_category=<?= (int)$category['id'] ?>#categories">Edytuj</a><form method="post" action="admin.php#categories" onsubmit="return confirm('Usunąć kategorię? Można usunąć tylko kategorię niepowiązaną z usługami i pracownikami.');"><input type="hidden" name="csrf_token" value="<?= $escape(csrfToken()) ?>"><input type="hidden" name="action" value="category_delete"><input type="hidden" name="category_id" value="<?= (int)$category['id'] ?>"><button class="button button-outline button-small button-danger" type="submit">Usuń</button></form></td></tr>
            <?php endforeach; ?>
            <?php if (!$categories): ?><tr><td colspan="3">Brak kategorii. Dodaj pierwszą powyżej.</td></tr><?php endif; ?>
            </tbody></table>
        </div>
    </section>

    <section class="auth-card admin-section" id="services">
        <h2 class="form-section-title mt-0"><?= $editingService ? 'Edytuj usługę' : 'Dodaj usługę' ?></h2>
        <form method="post" action="admin.php#services" class="admin-form-grid admin-service-form">
            <input type="hidden" name="csrf_token" value="<?= $escape(csrfToken()) ?>">
            <input type="hidden" name="action" value="service_save">
            <input type="hidden" name="service_id" value="<?= $editingService ? (int)$editingService['id'] : '' ?>">
            <div><label class="form-label" for="service-name">Nazwa usługi *</label><input class="form-control" id="service-name" name="name" maxlength="160" required value="<?= $escape($editingService['nazwa'] ?? '') ?>"></div>
            <div><label class="form-label" for="service-category">Kategoria *</label><select class="form-select form-control" id="service-category" name="category_id" required><option value="">Wybierz kategorię</option><?php foreach ($categories as $category): ?><option value="<?= (int)$category['id'] ?>" <?= (int)($editingService['specjalizacja_id'] ?? 0) === (int)$category['id'] ? 'selected' : '' ?>><?= $escape($category['nazwa']) ?></option><?php endforeach; ?></select></div>
            <div><label class="form-label" for="service-duration">Czas trwania (min) *</label><input class="form-control" id="service-duration" type="number" name="duration" min="1" max="65535" required value="<?= $escape($editingService['czas_trwania_minut'] ?? 30) ?>"></div>
            <div><label class="form-label" for="service-price">Cena (zł) *</label><input class="form-control" id="service-price" type="number" name="price" min="0" max="999999.99" step="0.01" required value="<?= $escape($editingService['cena'] ?? '0.00') ?>"></div>
            <div class="admin-form-wide"><label class="form-label" for="service-description">Opis</label><textarea class="form-control" id="service-description" name="description" rows="2" maxlength="10000"><?= $escape($editingService['opis'] ?? '') ?></textarea></div>
            <label class="admin-checkbox"><input type="checkbox" name="active" value="1" <?= !$editingService || (bool)$editingService['czy_aktywny'] ? 'checked' : '' ?>> Usługa aktywna</label>
            <div class="admin-form-actions"><button class="button button-primary button-small" type="submit"><?= $editingService ? 'Zapisz usługę' : 'Dodaj usługę' ?></button><?php if ($editingService): ?><a class="button button-outline button-small" href="admin.php#services">Anuluj</a><?php endif; ?></div>
        </form>
        <div class="table-responsive admin-table-wrap">
            <table class="table align-middle"><thead><tr><th>Usługa</th><th>Kategoria</th><th>Czas</th><th>Cena</th><th>Status</th><th>Akcje</th></tr></thead><tbody>
            <?php foreach ($services as $service): ?>
                <tr><td><strong><?= $escape($service['nazwa']) ?></strong><?php if ($service['opis']): ?><br><span class="text-muted"><?= $escape($service['opis']) ?></span><?php endif; ?></td><td><?= $escape($service['category_name']) ?></td><td><?= (int)$service['czas_trwania_minut'] ?> min</td><td><?= $escape(number_format((float)$service['cena'], 2, ',', ' ')) ?> zł</td><td><?= (bool)$service['czy_aktywny'] ? 'Aktywna' : 'Nieaktywna' ?></td><td class="admin-actions"><a class="button button-outline button-small" href="admin.php?edit_service=<?= (int)$service['id'] ?>#services">Edytuj</a><form method="post" action="admin.php#services" onsubmit="return confirm('Usunąć usługę? Można usunąć tylko usługę bez przypisanych wizyt.');"><input type="hidden" name="csrf_token" value="<?= $escape(csrfToken()) ?>"><input type="hidden" name="action" value="service_delete"><input type="hidden" name="service_id" value="<?= (int)$service['id'] ?>"><button class="button button-outline button-small button-danger" type="submit">Usuń</button></form></td></tr>
            <?php endforeach; ?>
            <?php if (!$services): ?><tr><td colspan="6">Brak usług. Dodaj pierwszą powyżej.</td></tr><?php endif; ?>
            </tbody></table>
        </div>
    </section>

    <section class="auth-card admin-section" id="employees">
        <h2 class="form-section-title mt-0">Dodaj pracownika</h2>
        <p class="admin-help">Nowy pracownik otrzyma kategorię, aktywne usługi z tej kategorii i przypisanie do pierwszej aktywnej placówki.</p>
        <form method="post" action="admin.php#employees" class="admin-form-grid admin-employee-form">
            <input type="hidden" name="csrf_token" value="<?= $escape(csrfToken()) ?>">
            <input type="hidden" name="action" value="employee_create">
            <div><label class="form-label" for="employee-first-name">Imię *</label><input class="form-control" id="employee-first-name" name="first_name" maxlength="80" required></div>
            <div><label class="form-label" for="employee-last-name">Nazwisko *</label><input class="form-control" id="employee-last-name" name="last_name" maxlength="80" required></div>
            <div><label class="form-label" for="employee-email">E-mail *</label><input class="form-control" id="employee-email" type="email" name="email" maxlength="254" required></div>
            <div><label class="form-label" for="employee-phone">Telefon</label><input class="form-control" id="employee-phone" type="tel" name="phone" maxlength="30"></div>
            <div><label class="form-label" for="employee-password">Hasło początkowe *</label><input class="form-control" id="employee-password" type="password" name="password" minlength="8" maxlength="72" required></div>
            <div><label class="form-label" for="employee-license">Numer PWZ *</label><input class="form-control" id="employee-license" name="license" maxlength="40" required></div>
            <div><label class="form-label" for="employee-category">Kategoria *</label><select class="form-select form-control" id="employee-category" name="category_id" required><option value="">Wybierz kategorię</option><?php foreach ($categories as $category): ?><option value="<?= (int)$category['id'] ?>"><?= $escape($category['nazwa']) ?></option><?php endforeach; ?></select></div>
            <div><label class="form-label" for="employee-duration">Domyślny czas wizyty (min) *</label><input class="form-control" id="employee-duration" type="number" name="default_duration" min="1" max="65535" value="30" required></div>
            <div class="admin-form-actions"><button class="button button-primary button-small" type="submit">Utwórz konto pracownika</button></div>
        </form>
        <div class="table-responsive admin-table-wrap">
            <table class="table align-middle"><thead><tr><th>Pracownik</th><th>E-mail</th><th>Numer PWZ</th><th>Kategorie</th><th>Status</th><th>Akcja</th></tr></thead><tbody>
            <?php foreach ($employees as $employee): ?>
                <tr><td><?= $escape($employee['imie'] . ' ' . $employee['nazwisko']) ?></td><td><?= $escape($employee['email']) ?></td><td><?= $escape($employee['numer_pwz']) ?></td><td><?= $escape($employee['categories'] ?? '—') ?></td><td><?= (bool)$employee['czy_aktywny'] ? 'Aktywny' : 'Nieaktywny' ?></td><td><form method="post" action="admin.php#employees"><input type="hidden" name="csrf_token" value="<?= $escape(csrfToken()) ?>"><input type="hidden" name="action" value="employee_toggle"><input type="hidden" name="employee_id" value="<?= (int)$employee['id'] ?>"><input type="hidden" name="active" value="<?= (bool)$employee['czy_aktywny'] ? 0 : 1 ?>"><button class="button button-outline button-small" type="submit"><?= (bool)$employee['czy_aktywny'] ? 'Dezaktywuj' : 'Aktywuj' ?></button></form></td></tr>
            <?php endforeach; ?>
            <?php if (!$employees): ?><tr><td colspan="6">Brak pracowników.</td></tr><?php endif; ?>
            </tbody></table>
        </div>
    </section>

    <section class="auth-card admin-section" id="appointments">
        <h2 class="form-section-title mt-0">Przegląd wizyt</h2>
        <div class="table-responsive">
            <table class="table align-middle"><thead><tr><th>Termin</th><th>Pacjent</th><th>Pracownik</th><th>Usługa</th><th>Placówka</th><th>Cena</th><th>Status</th></tr></thead><tbody>
            <?php foreach ($appointments as $appointment): ?>
                <tr><td><?= $escape($appointment['data_wizyty']) ?><br><?= $escape(substr($appointment['godzina_wizyty'], 0, 5)) ?></td><td><?= $escape($appointment['patient_name']) ?></td><td><?= $escape($appointment['doctor_name']) ?></td><td><?= $escape($appointment['service_name']) ?><br><span class="text-muted"><?= (int)$appointment['czas_trwania_minut'] ?> min</span></td><td><?= $escape($appointment['clinic_name']) ?></td><td><?= $escape(number_format((float)$appointment['cena'], 2, ',', ' ')) ?> zł</td><td><?= $escape($appointment['status']) ?></td></tr>
            <?php endforeach; ?>
            <?php if (!$appointments): ?><tr><td colspan="7">Brak wizyt.</td></tr><?php endif; ?>
            </tbody></table>
        </div>
        <?php if (count($appointments) === 100): ?><p class="admin-help">Wyświetlono 100 ostatnich wizyt.</p><?php endif; ?>
    </section>

    <section class="auth-card admin-section" id="accounts">
        <h2 class="form-section-title mt-0">Konta użytkowników</h2>
        <div class="table-responsive">
            <table class="table align-middle"><thead><tr><th>Użytkownik</th><th>E-mail</th><th>Rola</th><th>Status</th><th>Utworzono</th></tr></thead><tbody>
            <?php foreach ($users as $user): ?>
                <tr><td><?= $escape($user['imie'] . ' ' . $user['nazwisko']) ?></td><td><?= $escape($user['email']) ?></td><td><?= $escape(userRoleLabel($user['rola'])) ?></td><td><?= (bool)$user['czy_aktywny'] ? 'Aktywne' : 'Nieaktywne' ?></td><td><?= $escape(date('Y-m-d', strtotime($user['utworzono_at']))) ?></td></tr>
            <?php endforeach; ?>
            <?php if (!$users): ?><tr><td colspan="5">Brak kont.</td></tr><?php endif; ?>
            </tbody></table>
        </div>
        <?php if (count($users) === 100): ?><p class="admin-help">Wyświetlono 100 ostatnio utworzonych kont.</p><?php endif; ?>
    </section>
</section>
<?php require_once __DIR__ . '/../src/templates/footer.php'; ?>
