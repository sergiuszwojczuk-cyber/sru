<?php
require_once __DIR__ . '/../src/config/auth.php';
require_once __DIR__ . '/../src/config/database.php';
requireRole('pacjent');

$pdo = getDbConnection();
$escape = static fn (mixed $value): string => htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');

$validDate = static function (mixed $value): bool {
    if (!is_string($value) || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $value)) {
        return false;
    }
    $date = DateTimeImmutable::createFromFormat('!Y-m-d', $value);
    return $date !== false && $date->format('Y-m-d') === $value;
};

function generateSlots(string $date, string $from, string $to, int $duration): array
{
    $slots = [];
    $current = new DateTimeImmutable($date . ' ' . substr($from, 0, 8));
    $end = new DateTimeImmutable($date . ' ' . substr($to, 0, 8));
    $now = new DateTimeImmutable('now');
    while ($current->modify('+' . $duration . ' minutes') <= $end) {
        if ($current >= $now) {
            $slots[] = $current->format('H:i:s');
        }
        $current = $current->modify('+30 minutes');
    }
    return $slots;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!hasValidCsrfToken($_POST['csrf_token'] ?? null)) {
        $_SESSION['error'] = 'Sesja formularza wygasła. Odśwież stronę i spróbuj ponownie.';
        header('Location: rezerwacja.php', true, 303);
        exit;
    }

    try {
        $action = is_string($_POST['action'] ?? null) ? $_POST['action'] : '';
        if ($action === 'cancel') {
            $appointmentId = filter_var($_POST['appointment_id'] ?? '', FILTER_VALIDATE_INT);
            if (!$appointmentId || $appointmentId < 1) {
                throw new InvalidArgumentException('Nieprawidłowa wizyta.');
            }

            $pdo->beginTransaction();
            $stmt = $pdo->prepare(
                'SELECT pacjent_id, lekarz_id, data_wizyty, godzina_wizyty, status '
                . 'FROM wizyty WHERE id = ?'
            );
            $stmt->execute([$appointmentId]);
            $appointment = $stmt->fetch();
            if (!$appointment || (int)$appointment['pacjent_id'] !== (int)$_SESSION['user_id']) {
                throw new InvalidArgumentException('Nie masz uprawnień do anulowania tej wizyty.');
            }
            $employeeLock = $pdo->prepare('SELECT id FROM uzytkownicy WHERE id = ? FOR UPDATE');
            $employeeLock->execute([(int)$appointment['lekarz_id']]);
            $employeeLock->fetchColumn();
            $appointmentLock = $pdo->prepare(
                'SELECT pacjent_id, data_wizyty, godzina_wizyty, status '
                . 'FROM wizyty WHERE id = ? FOR UPDATE'
            );
            $appointmentLock->execute([$appointmentId]);
            $appointment = $appointmentLock->fetch();
            if (!$appointment || (int)$appointment['pacjent_id'] !== (int)$_SESSION['user_id']) {
                throw new InvalidArgumentException('Nie masz uprawnień do anulowania tej wizyty.');
            }
            if (!in_array($appointment['status'], ['zaplanowana', 'potwierdzona', 'zarejestrowana'], true)) {
                throw new InvalidArgumentException('Ta wizyta nie może zostać anulowana.');
            }

            $appointmentDateTime = new DateTimeImmutable(
                $appointment['data_wizyty'] . ' ' . $appointment['godzina_wizyty']
            );
            if ($appointmentDateTime <= new DateTimeImmutable('now')) {
                throw new InvalidArgumentException('Nie można anulować wizyty z przeszłości.');
            }

            $pdo->prepare('SET @aktor_uzytkownik_id = ?')->execute([(int)$_SESSION['user_id']]);
            $cancel = $pdo->prepare(
                'UPDATE wizyty SET status = \'anulowana\', powod_anulowania = ?, anulowal_uzytkownik_id = ? '
                . 'WHERE id = ?'
            );
            $cancel->execute([
                'Anulowana przez klienta',
                (int)$_SESSION['user_id'],
                $appointmentId,
            ]);
            $pdo->commit();
            $_SESSION['success'] = 'Wizyta została anulowana.';
            header('Location: rezerwacja.php', true, 303);
            exit;
        }

        $serviceId = filter_var($_POST['service_id'] ?? '', FILTER_VALIDATE_INT);
        $employeeId = filter_var($_POST['employee_id'] ?? '', FILTER_VALIDATE_INT);
        $clinicId = filter_var($_POST['clinic_id'] ?? '', FILTER_VALIDATE_INT);
        $date = $_POST['date'] ?? null;
        $time = $_POST['time'] ?? null;

        if (!$serviceId || $serviceId < 1 || !$employeeId || $employeeId < 1
            || !$clinicId || $clinicId < 1 || !$validDate($date)
            || !is_string($time) || !preg_match('/^\d{2}:\d{2}:\d{2}$/', $time)) {
            throw new InvalidArgumentException('Wybierz usługę, pracownika, placówkę, datę i godzinę.');
        }

        $startDate = new DateTimeImmutable('today');
        $lastDate = $startDate->modify('+90 days');
        $selectedDate = new DateTimeImmutable($date);
        if ($selectedDate < $startDate || $selectedDate > $lastDate) {
            throw new InvalidArgumentException('Można zarezerwować wizytę maksymalnie 90 dni do przodu.');
        }

        $pdo->beginTransaction();

        $employeeLock = $pdo->prepare(
            'SELECT id FROM uzytkownicy WHERE id = ? AND rola = \'lekarz\' AND czy_aktywny = 1 FOR UPDATE'
        );
        $employeeLock->execute([$employeeId]);
        if (!$employeeLock->fetchColumn()) {
            throw new InvalidArgumentException('Wybrany pracownik jest niedostępny.');
        }

        $serviceStmt = $pdo->prepare(
            'SELECT s.id, s.nazwa, s.czas_trwania_minut, s.cena '
            . 'FROM uslugi s '
            . 'JOIN lekarz_usluga lu ON lu.usluga_id = s.id '
            . 'WHERE s.id = ? AND lu.lekarz_id = ? AND s.czy_aktywny = 1'
        );
        $serviceStmt->execute([$serviceId, $employeeId]);
        $service = $serviceStmt->fetch();
        if (!$service) {
            throw new InvalidArgumentException('Wybrana usługa nie jest dostępna u tego pracownika.');
        }

        $assignmentStmt = $pdo->prepare(
            'SELECT 1 FROM lekarz_placowka WHERE lekarz_id = ? AND placowka_id = ?'
        );
        $assignmentStmt->execute([$employeeId, $clinicId]);
        if (!$assignmentStmt->fetchColumn()) {
            throw new InvalidArgumentException('Wybrana placówka nie jest przypisana do pracownika.');
        }

        $patientStmt = $pdo->prepare('SELECT uzytkownik_id FROM profil_pacjenta WHERE uzytkownik_id = ?');
        $patientStmt->execute([(int)$_SESSION['user_id']]);
        $patientId = $patientStmt->fetchColumn();
        if (!$patientId) {
            throw new RuntimeException('Brakuje profilu pacjenta.');
        }
        $patientLock = $pdo->prepare('SELECT uzytkownik_id FROM profil_pacjenta WHERE uzytkownik_id = ? FOR UPDATE');
        $patientLock->execute([(int)$patientId]);
        $patientLock->fetchColumn();

        $slots = [];
        $weekday = (int)$selectedDate->format('N');
        $availabilityStmt = $pdo->prepare(
            'SELECT godzina_od, godzina_do FROM dostepnosc_lekarza '
            . 'WHERE lekarz_id = ? AND placowka_id = ? AND dzien_tygodnia = ? '
            . 'AND (obowiazuje_od IS NULL OR obowiazuje_od <= ?) '
            . 'AND (obowiazuje_do IS NULL OR obowiazuje_do >= ?) '
            . 'FOR UPDATE'
        );
        $availabilityStmt->execute([$employeeId, $clinicId, $weekday, $date, $date]);

        $exceptionsStmt = $pdo->prepare(
            'SELECT typ_wyjatku, godzina_od, godzina_do '
            . 'FROM wyjatki_grafiku_lekarza '
            . 'WHERE lekarz_id = ? AND placowka_id = ? AND data_wyjatku = ? '
            . 'FOR UPDATE'
        );
        $exceptionsStmt->execute([$employeeId, $clinicId, $date]);
        $exceptions = $exceptionsStmt->fetchAll();
        $isUnavailable = false;
        $extraIntervals = [];
        foreach ($exceptions as $exception) {
            if ($exception['typ_wyjatku'] === 'niedostepny') {
                $isUnavailable = true;
            } elseif ($exception['typ_wyjatku'] === 'dodatkowe_godziny'
                && $exception['godzina_od'] && $exception['godzina_do']) {
                $extraIntervals[] = [$exception['godzina_od'], $exception['godzina_do']];
            }
        }

        $availabilityRows = $availabilityStmt->fetchAll();
        if (!$isUnavailable) {
            foreach ($availabilityRows as $availability) {
                $slots = array_merge($slots, generateSlots(
                    $date,
                    $availability['godzina_od'],
                    $availability['godzina_do'],
                    (int)$service['czas_trwania_minut']
                ));
            }
        }
        foreach ($extraIntervals as [$from, $to]) {
            $slots = array_merge($slots, generateSlots(
                $date,
                $from,
                $to,
                (int)$service['czas_trwania_minut']
            ));
        }
        $slots = array_values(array_unique($slots));
        sort($slots);

        if (!in_array($time, $slots, true)) {
            throw new InvalidArgumentException('Wybrana godzina jest niedostępna. Odśwież formularz i wybierz ponownie.');
        }

        $start = new DateTimeImmutable($date . ' ' . $time);
        $end = $start->modify('+' . (int)$service['czas_trwania_minut'] . ' minutes');

        $overlapStmt = $pdo->prepare(
            'SELECT data_wizyty, godzina_wizyty, czas_trwania_minut, lekarz_id, pacjent_id '
            . 'FROM wizyty '
            . 'WHERE data_wizyty BETWEEN DATE_SUB(?, INTERVAL 1 DAY) AND DATE_ADD(?, INTERVAL 1 DAY) '
            . 'AND status IN (\'zaplanowana\', \'potwierdzona\', \'zarejestrowana\') '
            . 'AND (lekarz_id = ? OR pacjent_id = ?) '
            . 'FOR UPDATE'
        );
        $overlapStmt->execute([
            $date,
            $date,
            $employeeId,
            (int)$patientId,
        ]);
        foreach ($overlapStmt->fetchAll() as $busyAppointment) {
            $busyStart = new DateTimeImmutable(
                $busyAppointment['data_wizyty'] . ' ' . $busyAppointment['godzina_wizyty']
            );
            $busyEnd = $busyStart->modify('+' . (int)$busyAppointment['czas_trwania_minut'] . ' minutes');
            if ($start < $busyEnd && $end > $busyStart) {
                throw new InvalidArgumentException('Termin został właśnie zajęty. Wybierz inną godzinę.');
            }
        }

        $pdo->prepare("SET @aktor_uzytkownik_id = ?")->execute([(int)$_SESSION['user_id']]);
        $insert = $pdo->prepare(
            'INSERT INTO wizyty '
            . '(pacjent_id, lekarz_id, placowka_id, usluga_id, data_wizyty, godzina_wizyty, '
            . 'czas_trwania_minut, cena, status, notatki) '
            . 'VALUES (?, ?, ?, ?, ?, ?, ?, ?, \'zaplanowana\', ?)'
        );
        $insert->execute([
            (int)$patientId,
            $employeeId,
            $clinicId,
            $serviceId,
            $date,
            $time,
            (int)$service['czas_trwania_minut'],
            $service['cena'],
            is_string($_POST['notes'] ?? null) ? trim((string)$_POST['notes']) ?: null : null,
        ]);
        $appointmentId = (int)$pdo->lastInsertId();

        $pdo->commit();
        $_SESSION['success'] = 'Wizyta została zarezerwowana.';
        header('Location: rezerwacja.php?booked=' . $appointmentId, true, 303);
        exit;
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        if ($e instanceof InvalidArgumentException || $e instanceof RuntimeException) {
            $_SESSION['error'] = $e->getMessage();
        } else {
            error_log('Appointment reservation failed: ' . $e->getMessage());
            $_SESSION['error'] = 'Nie udało się zapisać rezerwacji. Spróbuj ponownie.';
        }
        header('Location: rezerwacja.php', true, 303);
        exit;
    }
}

$serviceId = filter_var($_GET['service_id'] ?? '', FILTER_VALIDATE_INT);
$employeeId = filter_var($_GET['employee_id'] ?? '', FILTER_VALIDATE_INT);
$clinicId = filter_var($_GET['clinic_id'] ?? '', FILTER_VALIDATE_INT);
$date = is_string($_GET['date'] ?? null) && $validDate($_GET['date']) ? $_GET['date'] : date('Y-m-d');

$services = $pdo->query(
    'SELECT s.id, s.nazwa, s.opis, s.czas_trwania_minut, s.cena, sp.nazwa AS specjalizacja '
    . 'FROM uslugi s JOIN specjalizacje sp ON sp.id = s.specjalizacja_id '
    . 'WHERE s.czy_aktywny = 1 AND EXISTS ('
    . 'SELECT 1 FROM lekarz_usluga lu JOIN uzytkownicy u ON u.id = lu.lekarz_id '
    . 'WHERE lu.usluga_id = s.id AND u.rola = \'lekarz\' AND u.czy_aktywny = 1'
    . ') ORDER BY sp.nazwa, s.nazwa'
)->fetchAll();

$employees = [];
if ($serviceId && $serviceId > 0) {
    $stmt = $pdo->prepare(
        'SELECT DISTINCT u.id, u.imie, u.nazwisko, pl.numer_pwz '
        . 'FROM uzytkownicy u JOIN profil_lekarza pl ON pl.uzytkownik_id = u.id '
        . 'JOIN lekarz_usluga lu ON lu.lekarz_id = u.id '
        . 'WHERE lu.usluga_id = ? AND u.rola = \'lekarz\' AND u.czy_aktywny = 1 '
        . 'ORDER BY u.nazwisko, u.imie'
    );
    $stmt->execute([$serviceId]);
    $employees = $stmt->fetchAll();
}

$clinics = [];
if ($employeeId && $employeeId > 0) {
    $stmt = $pdo->prepare(
        'SELECT p.id, p.nazwa, p.ulica, p.miasto '
        . 'FROM placowki p JOIN lekarz_placowka lp ON lp.placowka_id = p.id '
        . 'WHERE lp.lekarz_id = ? AND p.czy_aktywny = 1 ORDER BY p.nazwa'
    );
    $stmt->execute([$employeeId]);
    $clinics = $stmt->fetchAll();
}

$slots = [];
$selectedService = null;
$selectedClinic = null;
if ($serviceId && $employeeId && $clinicId) {
    $stmt = $pdo->prepare(
        'SELECT s.nazwa, s.czas_trwania_minut, s.cena FROM uslugi s '
        . 'JOIN lekarz_usluga lu ON lu.usluga_id = s.id '
        . 'WHERE s.id = ? AND lu.lekarz_id = ? AND s.czy_aktywny = 1'
    );
    $stmt->execute([$serviceId, $employeeId]);
    $selectedService = $stmt->fetch();

    $stmt = $pdo->prepare(
        'SELECT p.nazwa, p.ulica, p.miasto FROM placowki p '
        . 'JOIN lekarz_placowka lp ON lp.placowka_id = p.id '
        . 'WHERE p.id = ? AND lp.lekarz_id = ?'
    );
    $stmt->execute([$clinicId, $employeeId]);
    $selectedClinic = $stmt->fetch();

    if ($selectedService && $selectedClinic && $validDate($date)) {
        $selected = new DateTimeImmutable($date);
        $today = new DateTimeImmutable('today');
        $maximum = $today->modify('+90 days');
        if ($selected >= $today && $selected <= $maximum) {
            $weekday = (int)$selected->format('N');
            $stmt = $pdo->prepare(
                'SELECT godzina_od, godzina_do FROM dostepnosc_lekarza '
                . 'WHERE lekarz_id = ? AND placowka_id = ? AND dzien_tygodnia = ? '
                . 'AND (obowiazuje_od IS NULL OR obowiazuje_od <= ?) '
                . 'AND (obowiazuje_do IS NULL OR obowiazuje_do >= ?)'
            );
            $stmt->execute([$employeeId, $clinicId, $weekday, $date, $date]);
            $availability = $stmt->fetchAll();

            $stmt = $pdo->prepare(
                'SELECT typ_wyjatku, godzina_od, godzina_do FROM wyjatki_grafiku_lekarza '
                . 'WHERE lekarz_id = ? AND placowka_id = ? AND data_wyjatku = ?'
            );
            $stmt->execute([$employeeId, $clinicId, $date]);
            $unavailable = false;
            foreach ($stmt->fetchAll() as $exception) {
                if ($exception['typ_wyjatku'] === 'niedostepny') {
                    $unavailable = true;
                } elseif ($exception['typ_wyjatku'] === 'dodatkowe_godziny'
                    && $exception['godzina_od'] && $exception['godzina_do']) {
                    $availability[] = ['godzina_od' => $exception['godzina_od'], 'godzina_do' => $exception['godzina_do']];
                }
            }

            if (!$unavailable) {
                foreach ($availability as $period) {
                    $slots = array_merge($slots, generateSlots(
                        $date,
                        $period['godzina_od'],
                        $period['godzina_do'],
                        (int)$selectedService['czas_trwania_minut']
                    ));
                }
            }
            $slots = array_values(array_unique($slots));
            sort($slots);

            if ($slots) {
                $occupiedStmt = $pdo->prepare(
                    'SELECT data_wizyty, godzina_wizyty, czas_trwania_minut FROM wizyty '
                    . 'WHERE (lekarz_id = ? OR pacjent_id = ?) '
                    . 'AND data_wizyty BETWEEN DATE_SUB(?, INTERVAL 1 DAY) AND DATE_ADD(?, INTERVAL 1 DAY) '
                    . 'AND status IN (\'zaplanowana\', \'potwierdzona\', \'zarejestrowana\')'
                );
                $occupiedStmt->execute([$employeeId, (int)$_SESSION['user_id'], $date, $date]);
                $occupied = $occupiedStmt->fetchAll();
                $slots = array_values(array_filter($slots, static function (string $slot) use ($occupied, $date, $selectedService): bool {
                    $start = new DateTimeImmutable($date . ' ' . $slot);
                    $end = $start->modify('+' . (int)$selectedService['czas_trwania_minut'] . ' minutes');
                    foreach ($occupied as $appointment) {
                        $busyStart = new DateTimeImmutable(
                            $appointment['data_wizyty'] . ' ' . $appointment['godzina_wizyty']
                        );
                        $busyEnd = $busyStart->modify('+' . (int)$appointment['czas_trwania_minut'] . ' minutes');
                        if ($start < $busyEnd && $end > $busyStart) {
                            return false;
                        }
                    }
                    return true;
                }));
            }
        }
    }
}

$appointmentsStmt = $pdo->prepare(
    'SELECT w.id, w.data_wizyty, w.godzina_wizyty, w.status, w.cena, '
    . 's.nazwa AS usluga, p.nazwa AS placowka, p.ulica, p.miasto, '
    . 'u.imie AS pracownik_imie, u.nazwisko AS pracownik_nazwisko '
    . 'FROM wizyty w '
    . 'JOIN uslugi s ON s.id = w.usluga_id '
    . 'JOIN placowki p ON p.id = w.placowka_id '
    . 'JOIN uzytkownicy u ON u.id = w.lekarz_id '
    . 'WHERE w.pacjent_id = ? '
    . 'AND w.status IN (\'zaplanowana\', \'potwierdzona\', \'zarejestrowana\') '
    . 'AND (w.data_wizyty > CURDATE() OR (w.data_wizyty = CURDATE() AND w.godzina_wizyty >= CURTIME())) '
    . 'ORDER BY w.data_wizyty, w.godzina_wizyty'
);
$appointmentsStmt->execute([(int)$_SESSION['user_id']]);
$appointments = $appointmentsStmt->fetchAll();

$pageTitle = 'Rezerwacja wizyty | Przychodnia Pochodnia';
require_once __DIR__ . '/../src/templates/header.php';
?>
<section class="content-section booking-layout">
    <div class="section-heading">
        <p class="eyebrow">Internetowa rezerwacja</p>
        <h1>Zarezerwuj wizytę</h1>
        <p>Wybierz usługę, pracownika, placówkę i dostępny termin.</p>
    </div>

    <?php if (isset($_SESSION['success'])): ?>
        <div class="alert alert-success" role="status"><?= $escape($_SESSION['success']); unset($_SESSION['success']); ?></div>
    <?php endif; ?>
    <?php if (isset($_SESSION['error'])): ?>
        <div class="alert alert-danger" role="alert"><?= $escape($_SESSION['error']); unset($_SESSION['error']); ?></div>
    <?php endif; ?>

    <form class="booking-filters" method="get" action="rezerwacja.php">
        <div>
            <label class="form-label" for="service_id">Usługa</label>
            <select class="form-control" id="service_id" name="service_id" required onchange="this.form.submit()">
                <option value="">Wybierz usługę</option>
                <?php foreach ($services as $service): ?>
                    <option value="<?= (int)$service['id'] ?>" <?= $serviceId === (int)$service['id'] ? 'selected' : '' ?>>
                        <?= $escape($service['nazwa']) ?> · <?= $escape(number_format((float)$service['cena'], 2, ',', ' ')) ?> zł
                    </option>
                <?php endforeach; ?>
            </select>
        </div>
        <?php if ($employees): ?>
            <div>
                <label class="form-label" for="employee_id">Pracownik</label>
                <select class="form-control" id="employee_id" name="employee_id" required onchange="this.form.submit()">
                    <option value="">Wybierz pracownika</option>
                    <?php foreach ($employees as $employee): ?>
                        <option value="<?= (int)$employee['id'] ?>" <?= $employeeId === (int)$employee['id'] ? 'selected' : '' ?>>
                            lek. <?= $escape($employee['imie'] . ' ' . $employee['nazwisko']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
        <?php endif; ?>
        <?php if ($clinics): ?>
            <div>
                <label class="form-label" for="clinic_id">Placówka</label>
                <select class="form-control" id="clinic_id" name="clinic_id" onchange="this.form.submit()">
                    <option value="">Wybierz placówkę</option>
                    <?php foreach ($clinics as $clinic): ?>
                        <option value="<?= (int)$clinic['id'] ?>" <?= $clinicId === (int)$clinic['id'] ? 'selected' : '' ?>>
                            <?= $escape($clinic['nazwa']) ?> — <?= $escape($clinic['ulica']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
        <?php endif; ?>
    </form>

    <?php if ($selectedService && $selectedClinic): ?>
        <div class="booking-grid">
            <section class="booking-summary auth-card">
                <p class="eyebrow">Podsumowanie</p>
                <h2><?= $escape($selectedService['nazwa']) ?></h2>
                <dl>
                    <div><dt>Czas trwania</dt><dd><?= (int)$selectedService['czas_trwania_minut'] ?> min</dd></div>
                    <div><dt>Cena</dt><dd><?= $escape(number_format((float)$selectedService['cena'], 2, ',', ' ')) ?> zł</dd></div>
                    <div><dt>Placówka</dt><dd><?= $escape($selectedClinic['nazwa']) ?></dd></div>
                </dl>
            </section>

            <section class="auth-card booking-form-card">
                <h2 class="form-section-title mt-0">Data i godzina</h2>
                <form method="get" action="rezerwacja.php" class="mb-3">
                    <input type="hidden" name="service_id" value="<?= (int)$serviceId ?>">
                    <input type="hidden" name="employee_id" value="<?= (int)$employeeId ?>">
                    <input type="hidden" name="clinic_id" value="<?= (int)$clinicId ?>">
                    <label class="form-label" for="date">Wybierz datę</label>
                    <input class="form-control" id="date" name="date" type="date"
                        value="<?= $escape($date) ?>" min="<?= date('Y-m-d') ?>" max="<?= date('Y-m-d', strtotime('+90 days')) ?>" required>
                    <button class="button button-secondary button-small mt-3" type="submit">Pokaż godziny</button>
                </form>

                <?php if ($slots): ?>
                    <form method="post" action="rezerwacja.php">
                        <input type="hidden" name="csrf_token" value="<?= $escape(csrfToken()) ?>">
                        <input type="hidden" name="service_id" value="<?= (int)$serviceId ?>">
                        <input type="hidden" name="employee_id" value="<?= (int)$employeeId ?>">
                        <input type="hidden" name="clinic_id" value="<?= (int)$clinicId ?>">
                        <input type="hidden" name="date" value="<?= $escape($date) ?>">
                        <fieldset class="booking-slots">
                            <legend class="form-label">Dostępne godziny</legend>
                            <?php foreach ($slots as $slot): ?>
                                <label>
                                    <input type="radio" name="time" value="<?= $escape($slot) ?>" required>
                                    <span><?= $escape(substr($slot, 0, 5)) ?></span>
                                </label>
                            <?php endforeach; ?>
                        </fieldset>
                        <label class="form-label mt-3" for="notes">Notatka (opcjonalnie)</label>
                        <textarea class="form-control mb-3" id="notes" name="notes" maxlength="10000" rows="3"></textarea>
                        <button class="button button-primary button-full" type="submit">Zarezerwuj wizytę</button>
                    </form>
                <?php else: ?>
                    <p class="alert alert-light mb-0">Brak wolnych terminów w tym dniu. Wybierz inną datę.</p>
                <?php endif; ?>
            </section>
        </div>
    <?php elseif ($serviceId && $employees && !$employeeId): ?>
        <p class="alert alert-light">Teraz wybierz pracownika, który oferuje tę usługę.</p>
    <?php elseif (!$services): ?>
        <p class="alert alert-light">Brak aktywnych usług dostępnych do rezerwacji.</p>
    <?php endif; ?>

    <section class="auth-card booking-appointments">
        <h2 class="form-section-title mt-0">Moje nadchodzące wizyty</h2>
        <?php if (!$appointments): ?>
            <p class="text-muted mb-0">Nie masz jeszcze zarezerwowanych wizyt.</p>
        <?php else: ?>
            <div class="table-responsive">
                <table class="table align-middle mb-0">
                    <thead>
                        <tr><th>Termin</th><th>Usługa</th><th>Pracownik</th><th>Placówka</th><th>Status</th><th></th></tr>
                    </thead>
                    <tbody>
                    <?php foreach ($appointments as $appointment): ?>
                        <tr>
                            <td><?= $escape(date('d.m.Y', strtotime($appointment['data_wizyty']))) ?><br><strong><?= $escape(substr($appointment['godzina_wizyty'], 0, 5)) ?></strong></td>
                            <td><?= $escape($appointment['usluga']) ?><br><small class="text-muted"><?= $escape(number_format((float)$appointment['cena'], 2, ',', ' ')) ?> zł</small></td>
                            <td>lek. <?= $escape($appointment['pracownik_imie'] . ' ' . $appointment['pracownik_nazwisko']) ?></td>
                            <td><?= $escape($appointment['placowka']) ?><br><small class="text-muted"><?= $escape($appointment['ulica'] . ', ' . $appointment['miasto']) ?></small></td>
                            <td><span class="booking-status"><?= $escape(match ($appointment['status']) {
                                'potwierdzona' => 'Potwierdzona',
                                'zarejestrowana' => 'Zarejestrowana',
                                default => 'Zaplanowana',
                            }) ?></span></td>
                            <td>
                                <form method="post" action="rezerwacja.php" onsubmit="return confirm('Anulować tę wizytę?');">
                                    <input type="hidden" name="csrf_token" value="<?= $escape(csrfToken()) ?>">
                                    <input type="hidden" name="action" value="cancel">
                                    <input type="hidden" name="appointment_id" value="<?= (int)$appointment['id'] ?>">
                                    <button class="button button-outline button-small" type="submit">Anuluj</button>
                                </form>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </section>
</section>
<?php require_once __DIR__ . '/../src/templates/footer.php'; ?>
