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

require_once __DIR__ . '/../src/views/admin.php';
