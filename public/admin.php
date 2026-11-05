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
$appointmentStatuses = [
    'zaplanowana' => 'Zaplanowana',
    'potwierdzona' => 'Potwierdzona',
    'zarejestrowana' => 'Zarejestrowana',
    'zakonczona' => 'Zakończona',
    'anulowana' => 'Anulowana',
    'nieobecnosc' => 'Nieobecność',
];
$allowedAppointmentTransitions = [
    'zaplanowana' => ['potwierdzona', 'anulowana'],
    'potwierdzona' => ['zarejestrowana', 'anulowana'],
    'zarejestrowana' => ['zakonczona', 'nieobecnosc'],
    'zakonczona' => [],
    'anulowana' => [],
    'nieobecnosc' => [],
];
$days = [1 => 'Poniedziałek', 2 => 'Wtorek', 3 => 'Środa', 4 => 'Czwartek', 5 => 'Piątek', 6 => 'Sobota', 7 => 'Niedziela'];
$validDate = static function (mixed $value): bool {
    if (!is_string($value) || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $value)) {
        return false;
    }

    $date = DateTime::createFromFormat('!Y-m-d', $value);
    return $date !== false && $date->format('Y-m-d') === $value;
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
        } elseif ($action === 'service_toggle') {
            $serviceId = filter_var($_POST['service_id'] ?? '', FILTER_VALIDATE_INT);
            $active = filter_var($_POST['active'] ?? '', FILTER_VALIDATE_INT);
            if (!$serviceId || $serviceId < 1 || !in_array($active, [0, 1], true)) {
                throw new InvalidArgumentException('Nieprawidłowe dane usługi.');
            }
            $toggleService = $pdo->prepare('UPDATE uslugi SET czy_aktywny = ? WHERE id = ?');
            $toggleService->execute([$active, $serviceId]);
            if ($toggleService->rowCount() === 0) {
                $serviceCheck = $pdo->prepare('SELECT czy_aktywny FROM uslugi WHERE id = ?');
                $serviceCheck->execute([$serviceId]);
                $currentActive = $serviceCheck->fetchColumn();
                if ($currentActive === false || (int)$currentActive !== $active) {
                    throw new InvalidArgumentException('Usługa nie istnieje.');
                }
            }
            $_SESSION['success'] = 'Status usługi został zmieniony.';
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
            if ($stmt->rowCount() === 0) {
                $check = $pdo->prepare("SELECT czy_aktywny FROM uzytkownicy WHERE id = ? AND rola = 'lekarz'");
                $check->execute([$employeeId]);
                $currentActive = $check->fetchColumn();
                if ($currentActive === false || (int)$currentActive !== $active) {
                    throw new InvalidArgumentException('Pracownik nie istnieje.');
                }
            }
            $_SESSION['success'] = 'Status pracownika został zmieniony.';
        } elseif ($action === 'employee_services_save') {
            $employeeId = filter_var($_POST['employee_id'] ?? '', FILTER_VALIDATE_INT);
            $serviceIds = $_POST['service_ids'] ?? [];
            if (!$employeeId || $employeeId < 1 || !is_array($serviceIds)) {
                throw new InvalidArgumentException('Nieprawidłowe dane przypisania usług.');
            }

            $normalizedServiceIds = [];
            foreach ($serviceIds as $serviceId) {
                $parsedId = filter_var($serviceId, FILTER_VALIDATE_INT);
                if ($parsedId === false || $parsedId < 1) {
                    throw new InvalidArgumentException('Lista usług zawiera nieprawidłowy identyfikator.');
                }
                $normalizedServiceIds[] = (int)$parsedId;
            }
            $normalizedServiceIds = array_values(array_unique($normalizedServiceIds));

            $pdo->beginTransaction();
            $employeeCheck = $pdo->prepare(
                "SELECT u.id FROM uzytkownicy u JOIN profil_lekarza pl ON pl.uzytkownik_id = u.id "
                . "WHERE u.id = ? AND u.rola = 'lekarz' FOR UPDATE"
            );
            $employeeCheck->execute([$employeeId]);
            if ($employeeCheck->fetchColumn() === false) {
                throw new InvalidArgumentException('Pracownik nie istnieje.');
            }

            $availableServices = $pdo->prepare(
                'SELECT DISTINCT us.id FROM uslugi us '
                . 'JOIN lekarz_specjalizacja ls ON ls.specjalizacja_id = us.specjalizacja_id AND ls.lekarz_id = ? '
                . 'LEFT JOIN lekarz_usluga lu ON lu.lekarz_id = ? AND lu.usluga_id = us.id '
                . 'WHERE us.czy_aktywny = 1 OR lu.lekarz_id IS NOT NULL'
            );
            $availableServices->execute([$employeeId, $employeeId]);
            $allowedServiceIds = array_map('intval', $availableServices->fetchAll(PDO::FETCH_COLUMN));
            if (array_diff($normalizedServiceIds, $allowedServiceIds)) {
                throw new InvalidArgumentException('Można przypisać tylko usługi zgodne ze specjalizacją pracownika.');
            }

            $currentServices = $pdo->prepare('SELECT usluga_id FROM lekarz_usluga WHERE lekarz_id = ? FOR UPDATE');
            $currentServices->execute([$employeeId]);
            $currentServiceIds = array_map('intval', $currentServices->fetchAll(PDO::FETCH_COLUMN));
            $removedServiceIds = array_values(array_diff($currentServiceIds, $normalizedServiceIds));
            if ($removedServiceIds) {
                $placeholders = implode(',', array_fill(0, count($removedServiceIds), '?'));
                $reservationCheck = $pdo->prepare(
                    'SELECT COUNT(*) FROM wizyty WHERE lekarz_id = ? AND usluga_id IN (' . $placeholders . ')'
                );
                $reservationCheck->execute(array_merge([$employeeId], $removedServiceIds));
                if ((int)$reservationCheck->fetchColumn() > 0) {
                    throw new InvalidArgumentException('Nie można odpiąć usługi, do której przypisano wizyty. Zachowaj ją na liście.');
                }
            }

            if ($removedServiceIds) {
                $placeholders = implode(',', array_fill(0, count($removedServiceIds), '?'));
                $removeAssignments = $pdo->prepare(
                    'DELETE FROM lekarz_usluga WHERE lekarz_id = ? AND usluga_id IN (' . $placeholders . ')'
                );
                $removeAssignments->execute(array_merge([$employeeId], $removedServiceIds));
            }
            $addedServiceIds = array_values(array_diff($normalizedServiceIds, $currentServiceIds));
            if ($addedServiceIds) {
                $insertAssignment = $pdo->prepare('INSERT INTO lekarz_usluga (lekarz_id, usluga_id) VALUES (?, ?)');
                foreach ($addedServiceIds as $serviceId) {
                    $insertAssignment->execute([$employeeId, $serviceId]);
                }
            }
            $pdo->commit();
            $_SESSION['success'] = 'Usługi pracownika zostały zaktualizowane.';
        } elseif ($action === 'availability_add') {
            $employeeClinicValue = is_string($_POST['employee_clinic'] ?? null) ? $_POST['employee_clinic'] : '';
            $employeeClinicParts = explode(':', $employeeClinicValue);
            $employeeId = count($employeeClinicParts) === 2 ? filter_var($employeeClinicParts[0], FILTER_VALIDATE_INT) : false;
            $clinicId = count($employeeClinicParts) === 2 ? filter_var($employeeClinicParts[1], FILTER_VALIDATE_INT) : false;
            $day = filter_var($_POST['day'] ?? '', FILTER_VALIDATE_INT);
            $start = is_string($_POST['start_time'] ?? null) ? $_POST['start_time'] : '';
            $end = is_string($_POST['end_time'] ?? null) ? $_POST['end_time'] : '';
            $validFrom = is_string($_POST['valid_from'] ?? null) && $_POST['valid_from'] !== '' ? $_POST['valid_from'] : null;
            $validUntil = is_string($_POST['valid_until'] ?? null) && $_POST['valid_until'] !== '' ? $_POST['valid_until'] : null;

            if (!$employeeId || $employeeId < 1 || !$clinicId || $clinicId < 1
                || !is_int($day) || $day < 1 || $day > 7
                || !preg_match('/^(?:[01]\d|2[0-3]):[0-5]\d$/', $start)
                || !preg_match('/^(?:[01]\d|2[0-3]):[0-5]\d$/', $end) || $end <= $start
                || ($validFrom !== null && !$validDate($validFrom))
                || ($validUntil !== null && !$validDate($validUntil))
                || ($validFrom !== null && $validUntil !== null && $validUntil < $validFrom)) {
                throw new InvalidArgumentException('Sprawdź pracownika, placówkę, dzień, godziny i zakres dat dostępności.');
            }

            $employeeClinic = $pdo->prepare(
                "SELECT lp.lekarz_id FROM lekarz_placowka lp "
                . "JOIN uzytkownicy u ON u.id = lp.lekarz_id AND u.rola = 'lekarz' "
                . 'WHERE lp.lekarz_id = ? AND lp.placowka_id = ?'
            );
            $employeeClinic->execute([$employeeId, $clinicId]);
            if ($employeeClinic->fetchColumn() === false) {
                throw new InvalidArgumentException('Pracownik nie jest przypisany do wybranej placówki.');
            }

            $overlapCheck = $pdo->prepare(
                'SELECT id FROM dostepnosc_lekarza WHERE lekarz_id = ? AND placowka_id = ? '
                . 'AND dzien_tygodnia = ? AND godzina_od < ? AND godzina_do > ? '
                . 'AND (? IS NULL OR obowiazuje_do IS NULL OR obowiazuje_do >= ?) '
                . 'AND (? IS NULL OR obowiazuje_od IS NULL OR obowiazuje_od <= ?) LIMIT 1'
            );
            $overlapCheck->execute([
                $employeeId, $clinicId, $day, $end, $start,
                $validFrom, $validFrom, $validUntil, $validUntil,
            ]);
            if ($overlapCheck->fetchColumn() !== false) {
                throw new InvalidArgumentException('Ten przedział nakłada się na już zdefiniowaną dostępność pracownika.');
            }

            $insertAvailability = $pdo->prepare(
                'INSERT INTO dostepnosc_lekarza '
                . '(lekarz_id, placowka_id, dzien_tygodnia, godzina_od, godzina_do, obowiazuje_od, obowiazuje_do) '
                . 'VALUES (?, ?, ?, ?, ?, ?, ?)'
            );
            $insertAvailability->execute([$employeeId, $clinicId, $day, $start, $end, $validFrom, $validUntil]);
            $_SESSION['success'] = 'Dostępność pracownika została dodana.';
        } elseif ($action === 'availability_delete') {
            $availabilityId = filter_var($_POST['availability_id'] ?? '', FILTER_VALIDATE_INT);
            if (!$availabilityId || $availabilityId < 1) {
                throw new InvalidArgumentException('Nieprawidłowy wpis dostępności.');
            }
            $deleteAvailability = $pdo->prepare('DELETE FROM dostepnosc_lekarza WHERE id = ?');
            $deleteAvailability->execute([$availabilityId]);
            if ($deleteAvailability->rowCount() === 0) {
                throw new InvalidArgumentException('Wpis dostępności nie istnieje.');
            }
            $_SESSION['success'] = 'Wpis dostępności został usunięty.';
        } elseif ($action === 'appointment_status') {
            $appointmentId = filter_var($_POST['appointment_id'] ?? '', FILTER_VALIDATE_INT);
            $newStatus = is_string($_POST['status'] ?? null) ? $_POST['status'] : '';
            if (!$appointmentId || $appointmentId < 1 || !isset($appointmentStatuses[$newStatus])) {
                throw new InvalidArgumentException('Nieprawidłowe dane zmiany statusu wizyty.');
            }

            $pdo->beginTransaction();
            $currentAppointment = $pdo->prepare('SELECT status FROM wizyty WHERE id = ? FOR UPDATE');
            $currentAppointment->execute([$appointmentId]);
            $oldStatus = $currentAppointment->fetchColumn();
            if (!is_string($oldStatus)) {
                throw new InvalidArgumentException('Wizyta nie istnieje.');
            }
            if (!in_array($newStatus, $allowedAppointmentTransitions[$oldStatus] ?? [], true)) {
                throw new InvalidArgumentException('Niedozwolona zmiana statusu wizyty. Statusy końcowe są nieodwracalne.');
            }

            $pdo->prepare('SET @aktor_uzytkownik_id = ?')->execute([(int)$_SESSION['user_id']]);
            $updateAppointment = $pdo->prepare(
                'UPDATE wizyty SET status = ?, anulowal_uzytkownik_id = ? WHERE id = ?'
            );
            $updateAppointment->execute([
                $newStatus,
                $newStatus === 'anulowana' ? (int)$_SESSION['user_id'] : null,
                $appointmentId,
            ]);
            $pdo->commit();
            $_SESSION['success'] = 'Status wizyty został zmieniony. Zapisano wpis w historii.';
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
$appointmentSelect = 'SELECT w.id, w.data_wizyty, w.godzina_wizyty, w.czas_trwania_minut, w.cena, w.status, '
    . 'CONCAT(p.imie, \' \', p.nazwisko) AS patient_name, '
    . 'CONCAT(d.imie, \' \', d.nazwisko) AS doctor_name, us.nazwa AS service_name, pl.nazwa AS clinic_name '
    . 'FROM wizyty w JOIN uzytkownicy p ON p.id = w.pacjent_id '
    . 'JOIN uzytkownicy d ON d.id = w.lekarz_id JOIN uslugi us ON us.id = w.usluga_id '
    . 'JOIN placowki pl ON pl.id = w.placowka_id';
$filterFrom = is_string($_GET['date_from'] ?? null) ? $_GET['date_from'] : '';
$filterTo = is_string($_GET['date_to'] ?? null) ? $_GET['date_to'] : '';
$filterStatus = is_string($_GET['status'] ?? null) ? $_GET['status'] : '';
$filterEmployee = filter_var($_GET['employee'] ?? '', FILTER_VALIDATE_INT);
$search = is_string($_GET['q'] ?? null) ? trim($_GET['q']) : '';
$where = [];
$filterValues = [];
if ($filterFrom !== '' && !$validDate($filterFrom)) {
    $_SESSION['error'] = 'Filtr daty początkowej jest nieprawidłowy.';
    $filterFrom = '';
}
if ($filterTo !== '' && !$validDate($filterTo)) {
    $_SESSION['error'] = 'Filtr daty końcowej jest nieprawidłowy.';
    $filterTo = '';
}
if ($filterFrom !== '' && $filterTo !== '' && $filterFrom > $filterTo) {
    $_SESSION['error'] = 'Data początkowa nie może być późniejsza niż końcowa.';
    $filterFrom = '';
    $filterTo = '';
}
if ($filterFrom !== '') {
    $where[] = 'w.data_wizyty >= ?';
    $filterValues[] = $filterFrom;
}
if ($filterTo !== '') {
    $where[] = 'w.data_wizyty <= ?';
    $filterValues[] = $filterTo;
}
if ($filterStatus !== '' && isset($appointmentStatuses[$filterStatus])) {
    $where[] = 'w.status = ?';
    $filterValues[] = $filterStatus;
} else {
    if ($filterStatus !== '') {
        $_SESSION['error'] = 'Wybrany status wizyty jest nieprawidłowy.';
    }
    $filterStatus = '';
}
if ($filterEmployee !== false && $filterEmployee > 0) {
    $where[] = 'w.lekarz_id = ?';
    $filterValues[] = $filterEmployee;
} else {
    if (isset($_GET['employee']) && $_GET['employee'] !== '') {
        $_SESSION['error'] = 'Wybrany pracownik jest nieprawidłowy.';
    }
    $filterEmployee = 0;
}
if ($search !== '') {
    if ($length($search) > 120) {
        $_SESSION['error'] = 'Wyszukiwany tekst może mieć maksymalnie 120 znaków.';
        $search = '';
    }
}
if ($search !== '') {
    $where[] = '(CONCAT(p.imie, \' \', p.nazwisko) LIKE ? OR CONCAT(d.imie, \' \', d.nazwisko) LIKE ? OR us.nazwa LIKE ?)';
    $searchValue = '%' . $search . '%';
    array_push($filterValues, $searchValue, $searchValue, $searchValue);
}
$appointmentWhere = $where ? ' WHERE ' . implode(' AND ', $where) : '';
$exportAppointments = ($_GET['export'] ?? '') === 'appointments';
if ($exportAppointments) {
    $exportQuery = $pdo->prepare($appointmentSelect . $appointmentWhere . ' ORDER BY w.data_wizyty, w.godzina_wizyty, w.id');
    $exportQuery->execute($filterValues);
    header('Content-Type: text/csv; charset=UTF-8');
    header('Content-Disposition: attachment; filename="wizyty-' . date('Y-m-d') . '.csv"');
    $output = fopen('php://output', 'wb');
    if ($output === false) {
        throw new RuntimeException('Nie można przygotować pliku CSV.');
    }
    fwrite($output, "\xEF\xBB\xBF");
    fputcsv($output, ['Data', 'Godzina', 'Pacjent', 'Pracownik', 'Usługa', 'Placówka', 'Czas (min)', 'Cena (zł)', 'Status'], ';');
    while ($row = $exportQuery->fetch()) {
        $values = [
            $row['data_wizyty'],
            substr($row['godzina_wizyty'], 0, 5),
            $row['patient_name'],
            $row['doctor_name'],
            $row['service_name'],
            $row['clinic_name'],
            $row['czas_trwania_minut'],
            $row['cena'],
            $appointmentStatuses[$row['status']] ?? $row['status'],
        ];
        foreach ($values as &$value) {
            $value = (string)$value;
            if (preg_match('/^[=+\-@]/', $value)) {
                $value = "'" . $value;
            }
        }
        unset($value);
        fputcsv($output, $values, ';');
    }
    fclose($output);
    exit;
}

$pageSize = 25;
$appointmentCountQuery = $pdo->prepare(
    'SELECT COUNT(*) FROM wizyty w JOIN uzytkownicy p ON p.id = w.pacjent_id '
    . 'JOIN uzytkownicy d ON d.id = w.lekarz_id JOIN uslugi us ON us.id = w.usluga_id'
    . $appointmentWhere
);
$appointmentCountQuery->execute($filterValues);
$appointmentCount = (int)$appointmentCountQuery->fetchColumn();
$appointmentPages = max(1, (int)ceil($appointmentCount / $pageSize));
$appointmentPage = filter_var($_GET['page'] ?? 1, FILTER_VALIDATE_INT);
$appointmentPage = $appointmentPage === false ? 1 : max(1, min($appointmentPages, $appointmentPage));
$appointmentQuery = $pdo->prepare(
    $appointmentSelect . $appointmentWhere
    . ' ORDER BY w.data_wizyty DESC, w.godzina_wizyty DESC, w.id DESC LIMIT '
    . $pageSize . ' OFFSET ' . (($appointmentPage - 1) * $pageSize)
);
$appointmentQuery->execute($filterValues);
$appointments = $appointmentQuery->fetchAll();

$todayStats = $pdo->prepare(
    "SELECT COUNT(*) AS total, SUM(status IN ('zaplanowana', 'potwierdzona', 'zarejestrowana')) AS active "
    . 'FROM wizyty WHERE data_wizyty = CURDATE()'
);
$todayStats->execute();
$todayStats = $todayStats->fetch();
$monthStatsQuery = $pdo->prepare(
    "SELECT COUNT(*) AS total, SUM(status = 'zakonczona') AS completed, "
    . "SUM(status = 'anulowana') AS cancelled, SUM(CASE WHEN status = 'zakonczona' THEN cena ELSE 0 END) AS revenue "
    . 'FROM wizyty WHERE data_wizyty >= ? AND data_wizyty < ?'
);
$monthStart = date('Y-m-01');
$nextMonthStart = date('Y-m-01', strtotime('+1 month'));
$monthStatsQuery->execute([$monthStart, $nextMonthStart]);
$monthStats = $monthStatsQuery->fetch();

$chartStart = date('Y-m-01', strtotime('-5 months'));
$chartEnd = $nextMonthStart;
$monthlyVisitsQuery = $pdo->prepare(
    "SELECT DATE_FORMAT(data_wizyty, '%Y-%m') AS month_key, COUNT(*) AS total "
    . 'FROM wizyty WHERE data_wizyty >= ? AND data_wizyty < ? GROUP BY month_key ORDER BY month_key'
);
$monthlyVisitsQuery->execute([$chartStart, $chartEnd]);
$monthlyVisits = [];
foreach ($monthlyVisitsQuery->fetchAll() as $monthRow) {
    $monthlyVisits[$monthRow['month_key']] = (int)$monthRow['total'];
}
$chart = [];
for ($offset = 5; $offset >= 0; $offset--) {
    $monthKey = date('Y-m', strtotime('-' . $offset . ' months'));
    $chart[] = [
        'key' => $monthKey,
        'label' => date('m/Y', strtotime($monthKey . '-01')),
        'total' => $monthlyVisits[$monthKey] ?? 0,
    ];
}
$chartMax = max(1, ...array_column($chart, 'total'));

$calendarMonthInput = is_string($_GET['calendar_month'] ?? null) ? $_GET['calendar_month'] : date('Y-m');
if (!preg_match('/^\d{4}-\d{2}$/', $calendarMonthInput)
    || !checkdate((int)substr($calendarMonthInput, 5, 2), 1, (int)substr($calendarMonthInput, 0, 4))) {
    $calendarMonthInput = date('Y-m');
    $_SESSION['error'] = 'Wybrany miesiąc kalendarza jest nieprawidłowy.';
}
$calendarStart = DateTime::createFromFormat('!Y-m-d', $calendarMonthInput . '-01');
$calendarEnd = (clone $calendarStart)->modify('first day of next month');
$calendarQuery = $pdo->prepare(
    "SELECT data_wizyty, COUNT(*) AS total, "
    . "SUM(status IN ('zaplanowana', 'potwierdzona', 'zarejestrowana')) AS active "
    . 'FROM wizyty WHERE data_wizyty >= ? AND data_wizyty < ? GROUP BY data_wizyty'
);
$calendarQuery->execute([$calendarStart->format('Y-m-d'), $calendarEnd->format('Y-m-d')]);
$calendarDays = [];
foreach ($calendarQuery->fetchAll() as $calendarDay) {
    $calendarDays[(int)substr($calendarDay['data_wizyty'], 8, 2)] = $calendarDay;
}
$calendarPrevious = (clone $calendarStart)->modify('-1 month')->format('Y-m');
$calendarNext = (clone $calendarStart)->modify('+1 month')->format('Y-m');

$servicesForEmployee = [];
$editingEmployeeId = filter_input(INPUT_GET, 'edit_employee_services', FILTER_VALIDATE_INT) ?: 0;
if ($editingEmployeeId > 0) {
    $serviceOptionsQuery = $pdo->prepare(
        'SELECT DISTINCT us.id, us.nazwa, us.czy_aktywny, s.nazwa AS category_name, '
        . 'EXISTS(SELECT 1 FROM lekarz_usluga lu WHERE lu.lekarz_id = ? AND lu.usluga_id = us.id) AS assigned '
        . 'FROM uslugi us JOIN specjalizacje s ON s.id = us.specjalizacja_id '
        . 'JOIN lekarz_specjalizacja ls ON ls.specjalizacja_id = us.specjalizacja_id AND ls.lekarz_id = ? '
        . 'LEFT JOIN lekarz_usluga lu ON lu.lekarz_id = ? AND lu.usluga_id = us.id '
        . 'WHERE us.czy_aktywny = 1 OR lu.lekarz_id IS NOT NULL ORDER BY s.nazwa, us.nazwa'
    );
    $serviceOptionsQuery->execute([$editingEmployeeId, $editingEmployeeId, $editingEmployeeId]);
    $servicesForEmployee = $serviceOptionsQuery->fetchAll();
}
$employeeClinics = $pdo->query(
    "SELECT lp.lekarz_id, lp.placowka_id, CONCAT(u.imie, ' ', u.nazwisko) AS employee_name, p.nazwa AS clinic_name "
    . 'FROM lekarz_placowka lp JOIN uzytkownicy u ON u.id = lp.lekarz_id '
    . 'JOIN placowki p ON p.id = lp.placowka_id WHERE u.rola = \'lekarz\' AND u.czy_aktywny = 1 '
    . 'AND p.czy_aktywny = 1 ORDER BY u.nazwisko, p.nazwa'
)->fetchAll();
$availability = $pdo->query(
    'SELECT d.id, d.lekarz_id, d.dzien_tygodnia, d.godzina_od, d.godzina_do, d.obowiazuje_od, d.obowiazuje_do, '
    . 'CONCAT(u.imie, \' \', u.nazwisko) AS employee_name, p.nazwa AS clinic_name '
    . 'FROM dostepnosc_lekarza d JOIN uzytkownicy u ON u.id = d.lekarz_id '
    . 'JOIN placowki p ON p.id = d.placowka_id ORDER BY u.nazwisko, d.dzien_tygodnia, d.godzina_od'
)->fetchAll();
$appointmentHistory = $pdo->query(
    'SELECT h.wizyta_id, h.poprzedni_status, h.nowy_status, h.notatka, h.utworzono_at, '
    . 'CONCAT(u.imie, \' \', u.nazwisko) AS actor_name '
    . 'FROM historia_statusow_wizyt h LEFT JOIN uzytkownicy u ON u.id = h.zmienil_uzytkownik_id '
    . 'ORDER BY h.utworzono_at DESC, h.id DESC LIMIT 30'
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
        <p>Zarządzaj przychodnią, kontroluj terminy i sprawdzaj bieżące statystyki.</p>
    </div>
    <?php if (isset($_SESSION['success'])): ?>
        <div class="alert alert-success" role="status"><?= $escape($_SESSION['success']); unset($_SESSION['success']); ?></div>
    <?php endif; ?>
    <?php if (isset($_SESSION['error'])): ?>
        <div class="alert alert-danger" role="alert"><?= $escape($_SESSION['error']); unset($_SESSION['error']); ?></div>
    <?php endif; ?>

    <nav class="admin-shortcuts" aria-label="Nawigacja panelu administratora">
        <a href="#overview">Podsumowanie</a><a href="#calendar">Kalendarz</a><a href="#appointments">Wizyty</a>
        <a href="#employees">Pracownicy</a><a href="#availability">Dostępność</a><a href="#services">Usługi</a>
        <a href="#categories">Kategorie</a><a href="#accounts">Konta</a><a href="#history">Historia</a>
    </nav>

    <section id="overview" class="admin-section">
    <div class="admin-stats">
        <article class="feature-card"><span class="feature-number">KONTA</span><h2><?= (int)$counts['users'] ?></h2><p>Użytkownicy ogółem</p></article>
        <article class="feature-card"><span class="feature-number">PACJENCI</span><h2><?= (int)$counts['patients'] ?></h2><p>Pacjenci przychodni</p></article>
        <article class="feature-card"><span class="feature-number">PERSONEL</span><h2><?= (int)$counts['employees'] ?></h2><p>Konta pracowników</p></article>
        <article class="feature-card"><span class="feature-number">DZISIAJ</span><h2><?= (int)$todayStats['total'] ?></h2><p>Wizyty, <?= (int)$todayStats['active'] ?> aktywne</p></article>
        <article class="feature-card"><span class="feature-number">TEN MIESIĄC</span><h2><?= (int)$monthStats['total'] ?></h2><p>Wizyty łącznie</p></article>
        <article class="feature-card"><span class="feature-number">ZAKOŃCZONE</span><h2><?= (int)$monthStats['completed'] ?></h2><p>Wizyty w tym miesiącu</p></article>
        <article class="feature-card"><span class="feature-number">ANULOWANE</span><h2><?= (int)$monthStats['cancelled'] ?></h2><p>Wizyty w tym miesiącu</p></article>
        <article class="feature-card"><span class="feature-number">PRZYCHÓD</span><h2><?= $escape(number_format((float)$monthStats['revenue'], 2, ',', ' ')) ?> zł</h2><p>Z zakończonych wizyt w tym miesiącu</p></article>
        <article class="feature-card"><span class="feature-number">ADMINISTRATORZY</span><h2><?= (int)$counts['administrators'] ?></h2><p>Konta administratorów</p></article>
    </div>

    <section class="auth-card admin-section admin-chart-card" aria-labelledby="visits-chart-title">
        <div class="admin-section-heading">
            <div><h2 class="form-section-title mt-0" id="visits-chart-title">Wizyty — ostatnie 6 miesięcy</h2><p class="admin-help">Liczba wizyt zarejestrowanych w każdym miesiącu.</p></div>
            <span class="admin-chart-total"><?= array_sum(array_column($chart, 'total')) ?> wizyt</span>
        </div>
        <div class="admin-chart" role="img" aria-label="Wykres liczby wizyt w ostatnich sześciu miesiącach">
            <?php foreach ($chart as $month): ?>
                <div class="admin-chart-column" aria-label="<?= $escape($month['label'] . ': ' . $month['total'] . ' wizyt') ?>">
                    <span><?= (int)$month['total'] ?></span>
                    <div class="admin-chart-track"><i style="height: <?= max(3, (int)round($month['total'] / $chartMax * 100)) ?>%"></i></div>
                    <strong><?= $escape($month['label']) ?></strong>
                </div>
            <?php endforeach; ?>
        </div>
    </section>
    </section>

    <section class="auth-card admin-section" id="calendar">
        <?php
        $monthNames = [1 => 'styczeń', 2 => 'luty', 3 => 'marzec', 4 => 'kwiecień', 5 => 'maj', 6 => 'czerwiec', 7 => 'lipiec', 8 => 'sierpień', 9 => 'wrzesień', 10 => 'październik', 11 => 'listopad', 12 => 'grudzień'];
        $calendarTitle = $monthNames[(int)$calendarStart->format('n')] . ' ' . $calendarStart->format('Y');
        $firstWeekdayOffset = (int)$calendarStart->format('N') - 1;
        $daysInMonth = (int)$calendarStart->format('t');
        ?>
        <div class="admin-section-heading">
            <div><h2 class="form-section-title mt-0">Kalendarz wizyt</h2><p class="admin-help">Podgląd dziennych rezerwacji w wybranym miesiącu.</p></div>
            <div class="admin-calendar-navigation">
                <a class="button button-outline button-small" href="admin.php?calendar_month=<?= $escape($calendarPrevious) ?>#calendar" aria-label="Poprzedni miesiąc">‹</a>
                <strong><?= $escape(ucfirst($calendarTitle)) ?></strong>
                <a class="button button-outline button-small" href="admin.php?calendar_month=<?= $escape($calendarNext) ?>#calendar" aria-label="Następny miesiąc">›</a>
            </div>
        </div>
        <div class="admin-calendar">
            <?php foreach (['Pon', 'Wt', 'Śr', 'Czw', 'Pt', 'Sob', 'Niedz'] as $weekday): ?><div class="admin-calendar-weekday"><?= $weekday ?></div><?php endforeach; ?>
            <?php for ($blank = 0; $blank < $firstWeekdayOffset; $blank++): ?><div class="admin-calendar-day is-empty" aria-hidden="true"></div><?php endfor; ?>
            <?php for ($day = 1; $day <= $daysInMonth; $day++): ?>
                <?php $dayStats = $calendarDays[$day] ?? null; ?>
                <a class="admin-calendar-day <?= $dayStats ? 'has-appointments' : '' ?>" href="admin.php?date_from=<?= $escape($calendarStart->format('Y-m-') . sprintf('%02d', $day)) ?>&amp;date_to=<?= $escape($calendarStart->format('Y-m-') . sprintf('%02d', $day)) ?>#appointments">
                    <strong><?= $day ?></strong>
                    <?php if ($dayStats): ?><span><?= (int)$dayStats['total'] ?> wiz.</span><small><?= (int)$dayStats['active'] ?> aktywne</small><?php endif; ?>
                </a>
            <?php endfor; ?>
        </div>
    </section>

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
                <tr><td><strong><?= $escape($service['nazwa']) ?></strong><?php if ($service['opis']): ?><br><span class="text-muted"><?= $escape($service['opis']) ?></span><?php endif; ?></td><td><?= $escape($service['category_name']) ?></td><td><?= (int)$service['czas_trwania_minut'] ?> min</td><td><?= $escape(number_format((float)$service['cena'], 2, ',', ' ')) ?> zł</td><td><?= (bool)$service['czy_aktywny'] ? 'Aktywna' : 'Nieaktywna' ?></td><td class="admin-actions"><a class="button button-outline button-small" href="admin.php?edit_service=<?= (int)$service['id'] ?>#services">Edytuj</a><form method="post" action="admin.php#services" onsubmit="return confirm('<?= (bool)$service['czy_aktywny'] ? 'Dezaktywować' : 'Aktywować' ?> tę usługę?');"><input type="hidden" name="csrf_token" value="<?= $escape(csrfToken()) ?>"><input type="hidden" name="action" value="service_toggle"><input type="hidden" name="service_id" value="<?= (int)$service['id'] ?>"><input type="hidden" name="active" value="<?= (bool)$service['czy_aktywny'] ? 0 : 1 ?>"><button class="button button-outline button-small" type="submit"><?= (bool)$service['czy_aktywny'] ? 'Dezaktywuj' : 'Aktywuj' ?></button></form><form method="post" action="admin.php#services" onsubmit="return confirm('Usunąć usługę? Można usunąć tylko usługę bez przypisanych wizyt.');"><input type="hidden" name="csrf_token" value="<?= $escape(csrfToken()) ?>"><input type="hidden" name="action" value="service_delete"><input type="hidden" name="service_id" value="<?= (int)$service['id'] ?>"><button class="button button-outline button-small button-danger" type="submit">Usuń</button></form></td></tr>
            <?php endforeach; ?>
            <?php if (!$services): ?><tr><td colspan="6">Brak usług. Dodaj pierwszą powyżej.</td></tr><?php endif; ?>
            </tbody></table>
        </div>
    </section>

    <section class="auth-card admin-section" id="employees">
        <h2 class="form-section-title mt-0"><?= $editingEmployeeId > 0 ? 'Przypisz usługi pracownikowi' : 'Dodaj pracownika' ?></h2>
        <p class="admin-help">Nowy pracownik otrzyma kategorię, aktywne usługi z tej kategorii i przypisanie do pierwszej aktywnej placówki.</p>
        <?php if ($editingEmployeeId > 0): ?>
            <?php
            $editingEmployee = null;
            foreach ($employees as $employee) {
                if ((int)$employee['id'] === $editingEmployeeId) {
                    $editingEmployee = $employee;
                    break;
                }
            }
            ?>
            <?php if ($editingEmployee): ?>
                <p class="admin-help">Pracownik: <strong><?= $escape($editingEmployee['imie'] . ' ' . $editingEmployee['nazwisko']) ?></strong>. Usługi z wcześniejszymi wizytami muszą pozostać przypisane.</p>
                <form method="post" action="admin.php#employees" class="admin-service-assignments">
                    <input type="hidden" name="csrf_token" value="<?= $escape(csrfToken()) ?>">
                    <input type="hidden" name="action" value="employee_services_save">
                    <input type="hidden" name="employee_id" value="<?= $editingEmployeeId ?>">
                    <?php foreach ($servicesForEmployee as $serviceOption): ?>
                        <label class="admin-service-option">
                            <input type="checkbox" name="service_ids[]" value="<?= (int)$serviceOption['id'] ?>" <?= (bool)$serviceOption['assigned'] ? 'checked' : '' ?>>
                            <span><strong><?= $escape($serviceOption['nazwa']) ?></strong><small><?= $escape($serviceOption['category_name']) ?><?= !(bool)$serviceOption['czy_aktywny'] ? ' · nieaktywna' : '' ?></small></span>
                        </label>
                    <?php endforeach; ?>
                    <?php if (!$servicesForEmployee): ?><p class="admin-help">Brak usług zgodnych ze specjalizacją pracownika.</p><?php endif; ?>
                    <div class="admin-form-actions"><button class="button button-primary button-small" type="submit">Zapisz przypisania</button><a class="button button-outline button-small" href="admin.php#employees">Anuluj</a></div>
                </form>
            <?php else: ?>
                <div class="alert alert-warning">Nie znaleziono pracownika do edycji.</div>
            <?php endif; ?>
        <?php else: ?>
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
        <?php endif; ?>
        <div class="table-responsive admin-table-wrap">
            <table class="table align-middle"><thead><tr><th>Pracownik</th><th>E-mail</th><th>Numer PWZ</th><th>Kategorie</th><th>Status</th><th>Akcje</th></tr></thead><tbody>
            <?php foreach ($employees as $employee): ?>
                <tr><td><?= $escape($employee['imie'] . ' ' . $employee['nazwisko']) ?></td><td><?= $escape($employee['email']) ?></td><td><?= $escape($employee['numer_pwz']) ?></td><td><?= $escape($employee['categories'] ?? '—') ?></td><td><?= (bool)$employee['czy_aktywny'] ? 'Aktywny' : 'Nieaktywny' ?></td><td class="admin-actions"><a class="button button-outline button-small" href="admin.php?edit_employee_services=<?= (int)$employee['id'] ?>#employees">Usługi</a><form method="post" action="admin.php#employees" onsubmit="return confirm('<?= (bool)$employee['czy_aktywny'] ? 'Dezaktywować' : 'Aktywować' ?> konto tego pracownika?');"><input type="hidden" name="csrf_token" value="<?= $escape(csrfToken()) ?>"><input type="hidden" name="action" value="employee_toggle"><input type="hidden" name="employee_id" value="<?= (int)$employee['id'] ?>"><input type="hidden" name="active" value="<?= (bool)$employee['czy_aktywny'] ? 0 : 1 ?>"><button class="button button-outline button-small" type="submit"><?= (bool)$employee['czy_aktywny'] ? 'Dezaktywuj' : 'Aktywuj' ?></button></form></td></tr>
            <?php endforeach; ?>
            <?php if (!$employees): ?><tr><td colspan="6">Brak pracowników.</td></tr><?php endif; ?>
            </tbody></table>
        </div>
    </section>

    <section class="auth-card admin-section" id="availability">
        <h2 class="form-section-title mt-0">Dostępność pracowników</h2>
        <p class="admin-help">Dodawaj cykliczne godziny przyjęć z opcjonalnym zakresem obowiązywania. Dni wolne można prowadzić jako wyjątki grafiku w bazie.</p>
        <form method="post" action="admin.php#availability" class="admin-form-grid">
            <input type="hidden" name="csrf_token" value="<?= $escape(csrfToken()) ?>">
            <input type="hidden" name="action" value="availability_add">
            <div><label class="form-label" for="availability-employee">Pracownik i placówka *</label><select class="form-select form-control" id="availability-employee" name="employee_clinic" required>
                <option value="">Wybierz pracownika / placówkę</option>
                <?php foreach ($employeeClinics as $employeeClinic): ?>
                    <option value="<?= (int)$employeeClinic['lekarz_id'] ?>:<?= (int)$employeeClinic['placowka_id'] ?>"><?= $escape($employeeClinic['employee_name'] . ' — ' . $employeeClinic['clinic_name']) ?></option>
                <?php endforeach; ?>
            </select></div>
            <div><label class="form-label" for="availability-day">Dzień tygodnia *</label><select class="form-select form-control" id="availability-day" name="day" required><option value="">Wybierz dzień</option><?php foreach ($days as $dayNumber => $dayLabel): ?><option value="<?= $dayNumber ?>"><?= $escape($dayLabel) ?></option><?php endforeach; ?></select></div>
            <div><label class="form-label" for="availability-start">Godzina od *</label><input class="form-control" id="availability-start" type="time" name="start_time" required></div>
            <div><label class="form-label" for="availability-end">Godzina do *</label><input class="form-control" id="availability-end" type="time" name="end_time" required></div>
            <div><label class="form-label" for="availability-from">Obowiązuje od</label><input class="form-control" id="availability-from" type="date" name="valid_from"></div>
            <div><label class="form-label" for="availability-until">Obowiązuje do</label><input class="form-control" id="availability-until" type="date" name="valid_until"></div>
            <div class="admin-form-actions"><button class="button button-primary button-small" type="submit">Dodaj dostępność</button></div>
        </form>
        <div class="table-responsive admin-table-wrap">
            <table class="table align-middle"><thead><tr><th>Pracownik</th><th>Dzień</th><th>Godziny</th><th>Placówka</th><th>Obowiązuje</th><th>Akcja</th></tr></thead><tbody>
            <?php foreach ($availability as $schedule): ?>
                <tr><td><?= $escape($schedule['employee_name']) ?></td><td><?= $escape($days[(int)$schedule['dzien_tygodnia']] ?? '—') ?></td><td><?= $escape(substr($schedule['godzina_od'], 0, 5) . '–' . substr($schedule['godzina_do'], 0, 5)) ?></td><td><?= $escape($schedule['clinic_name']) ?></td><td><?= $escape(($schedule['obowiazuje_od'] ?: 'bez początku') . ' – ' . ($schedule['obowiazuje_do'] ?: 'bez końca')) ?></td><td><form method="post" action="admin.php#availability" onsubmit="return confirm('Usunąć ten wpis dostępności?');"><input type="hidden" name="csrf_token" value="<?= $escape(csrfToken()) ?>"><input type="hidden" name="action" value="availability_delete"><input type="hidden" name="availability_id" value="<?= (int)$schedule['id'] ?>"><button class="button button-outline button-small button-danger" type="submit">Usuń</button></form></td></tr>
            <?php endforeach; ?>
            <?php if (!$availability): ?><tr><td colspan="6">Brak zdefiniowanej dostępności.</td></tr><?php endif; ?>
            </tbody></table>
        </div>
    </section>

    <section class="auth-card admin-section" id="appointments">
        <div class="admin-section-heading">
            <div><h2 class="form-section-title mt-0">Przegląd wizyt</h2><p class="admin-help">Znaleziono: <?= $appointmentCount ?> · strona <?= (int)$appointmentPage ?> z <?= $appointmentPages ?></p></div>
            <?php $exportQueryString = http_build_query(array_merge($_GET, ['export' => 'appointments'])); ?>
            <a class="button button-outline button-small" href="admin.php?<?= $escape($exportQueryString) ?>">Eksportuj CSV</a>
        </div>
        <form method="get" action="admin.php#appointments" class="admin-filter-grid">
            <div><label class="form-label" for="filter-from">Data od</label><input class="form-control" id="filter-from" type="date" name="date_from" value="<?= $escape($filterFrom) ?>"></div>
            <div><label class="form-label" for="filter-to">Data do</label><input class="form-control" id="filter-to" type="date" name="date_to" value="<?= $escape($filterTo) ?>"></div>
            <div><label class="form-label" for="filter-status">Status</label><select class="form-select form-control" id="filter-status" name="status"><option value="">Wszystkie</option><?php foreach ($appointmentStatuses as $statusValue => $statusLabel): ?><option value="<?= $escape($statusValue) ?>" <?= $filterStatus === $statusValue ? 'selected' : '' ?>><?= $escape($statusLabel) ?></option><?php endforeach; ?></select></div>
            <div><label class="form-label" for="filter-employee">Pracownik</label><select class="form-select form-control" id="filter-employee" name="employee"><option value="">Wszyscy</option><?php foreach ($employees as $employee): ?><option value="<?= (int)$employee['id'] ?>" <?= (int)$filterEmployee === (int)$employee['id'] ? 'selected' : '' ?>><?= $escape($employee['imie'] . ' ' . $employee['nazwisko']) ?></option><?php endforeach; ?></select></div>
            <div><label class="form-label" for="filter-search">Szukaj pacjenta, pracownika lub usługi</label><input class="form-control" id="filter-search" name="q" type="search" maxlength="120" value="<?= $escape($search) ?>" placeholder="Wpisz nazwisko lub nazwę"></div>
            <div class="admin-filter-actions"><button class="button button-primary button-small" type="submit">Filtruj</button><a class="button button-outline button-small" href="admin.php#appointments">Wyczyść</a></div>
        </form>
        <div class="table-responsive">
            <table class="table align-middle"><thead><tr><th>Termin</th><th>Pacjent</th><th>Pracownik</th><th>Usługa</th><th>Placówka</th><th>Cena</th><th>Status i zmiana</th></tr></thead><tbody>
            <?php foreach ($appointments as $appointment): ?>
                <?php $nextStatuses = $allowedAppointmentTransitions[$appointment['status']] ?? []; ?>
                <tr><td><?= $escape($appointment['data_wizyty']) ?><br><?= $escape(substr($appointment['godzina_wizyty'], 0, 5)) ?></td><td><?= $escape($appointment['patient_name']) ?></td><td><?= $escape($appointment['doctor_name']) ?></td><td><?= $escape($appointment['service_name']) ?><br><span class="text-muted"><?= (int)$appointment['czas_trwania_minut'] ?> min</span></td><td><?= $escape($appointment['clinic_name']) ?></td><td><?= $escape(number_format((float)$appointment['cena'], 2, ',', ' ')) ?> zł</td><td><strong><?= $escape($appointmentStatuses[$appointment['status']] ?? $appointment['status']) ?></strong>
                    <?php if ($nextStatuses): ?>
                        <form method="post" action="admin.php#appointments" class="admin-status-form" onsubmit="return confirm('Zmienić status tej wizyty? Zmiana zostanie zapisana w historii.');">
                            <input type="hidden" name="csrf_token" value="<?= $escape(csrfToken()) ?>"><input type="hidden" name="action" value="appointment_status"><input type="hidden" name="appointment_id" value="<?= (int)$appointment['id'] ?>">
                            <select class="form-select form-control" name="status" aria-label="Nowy status wizyty <?= (int)$appointment['id'] ?>"><?php foreach ($nextStatuses as $nextStatus): ?><option value="<?= $escape($nextStatus) ?>"><?= $escape($appointmentStatuses[$nextStatus]) ?></option><?php endforeach; ?></select>
                            <button class="button button-outline button-small" type="submit">Zapisz</button>
                        </form>
                    <?php endif; ?>
                </td></tr>
            <?php endforeach; ?>
            <?php if (!$appointments): ?><tr><td colspan="7">Brak wizyt.</td></tr><?php endif; ?>
            </tbody></table>
        </div>
        <?php if ($appointmentPages > 1): ?>
            <?php $paginationParameters = $_GET; unset($paginationParameters['page'], $paginationParameters['export']); ?>
            <nav class="admin-pagination" aria-label="Strony listy wizyt">
                <?php if ($appointmentPage > 1): ?><a class="button button-outline button-small" href="admin.php?<?= $escape(http_build_query(array_merge($paginationParameters, ['page' => $appointmentPage - 1]))) ?>#appointments">Poprzednia</a><?php endif; ?>
                <span>Strona <?= (int)$appointmentPage ?> z <?= $appointmentPages ?></span>
                <?php if ($appointmentPage < $appointmentPages): ?><a class="button button-outline button-small" href="admin.php?<?= $escape(http_build_query(array_merge($paginationParameters, ['page' => $appointmentPage + 1]))) ?>#appointments">Następna</a><?php endif; ?>
            </nav>
        <?php endif; ?>
    </section>

    <section class="auth-card admin-section" id="history">
        <h2 class="form-section-title mt-0">Historia zmian statusów wizyt</h2>
        <p class="admin-help">Ostatnie 30 wpisów zapisanych automatycznie przy tworzeniu wizyty lub zmianie jej statusu.</p>
        <div class="table-responsive">
            <table class="table align-middle"><thead><tr><th>Data zmiany</th><th>Wizyta</th><th>Zmiana statusu</th><th>Administrator / użytkownik</th><th>Opis</th></tr></thead><tbody>
            <?php foreach ($appointmentHistory as $history): ?>
                <tr><td><?= $escape(date('Y-m-d H:i', strtotime($history['utworzono_at']))) ?></td><td>#<?= (int)$history['wizyta_id'] ?></td><td><?= $escape($appointmentStatuses[$history['poprzedni_status']] ?? '—') ?> → <strong><?= $escape($appointmentStatuses[$history['nowy_status']] ?? $history['nowy_status']) ?></strong></td><td><?= $escape($history['actor_name'] ?? 'System') ?></td><td><?= $escape($history['notatka'] ?? '') ?></td></tr>
            <?php endforeach; ?>
            <?php if (!$appointmentHistory): ?><tr><td colspan="5">Brak zapisanej historii.</td></tr><?php endif; ?>
            </tbody></table>
        </div>
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
