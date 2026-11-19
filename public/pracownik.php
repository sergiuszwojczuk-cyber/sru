<?php
require_once __DIR__ . '/../src/config/auth.php';
require_once __DIR__ . '/../src/config/database.php';
requireRole('pracownik');

$pdo = getDbConnection();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!hasValidCsrfToken($_POST['csrf_token'] ?? null)) {
        $_SESSION['error'] = 'Sesja formularza wygasła. Odśwież stronę i spróbuj ponownie.';
        header('Location: pracownik.php', true, 303);
        exit;
    }

    $action = is_string($_POST['action'] ?? null) ? $_POST['action'] : '';
    $appointmentId = filter_var($_POST['appointment_id'] ?? '', FILTER_VALIDATE_INT);
    $newStatus = is_string($_POST['status'] ?? null) ? $_POST['status'] : '';

    if ($action === 'appointment_status') {
        $allowed = [
            'zaplanowana' => ['potwierdzona', 'anulowana'],
            'potwierdzona' => ['zarejestrowana', 'anulowana'],
            'zarejestrowana' => ['zakonczona', 'nieobecnosc'],
        ];
        if (!$appointmentId || $appointmentId < 1 || !in_array($newStatus, ['potwierdzona', 'zarejestrowana', 'zakonczona', 'anulowana', 'nieobecnosc'], true)) {
            $_SESSION['error'] = 'Nieprawidłowy formularz zmiany statusu.';
        } else {
            try {
                $pdo->beginTransaction();
                $stmt = $pdo->prepare('SELECT status FROM wizyty WHERE id = ? AND lekarz_id = ? FOR UPDATE');
                $stmt->execute([$appointmentId, (int)$_SESSION['user_id']]);
                $oldStatus = $stmt->fetchColumn();
                if (!is_string($oldStatus)) {
                    throw new RuntimeException('Wizyta nie należy do tego pracownika.');
                }
                if (!in_array($newStatus, $allowed[$oldStatus] ?? [], true)) {
                    throw new InvalidArgumentException('Niedozwolona zmiana statusu wizyty.');
                }

                $pdo->prepare('SET @aktor_uzytkownik_id = ?')->execute([(int)$_SESSION['user_id']]);
                $update = $pdo->prepare('UPDATE wizyty SET status = ? WHERE id = ?');
                $update->execute([$newStatus, $appointmentId]);
                $pdo->commit();
                $_SESSION['success'] = 'Status wizyty został zmieniony.';
            } catch (Throwable $e) {
                if ($pdo->inTransaction()) {
                    $pdo->rollBack();
                }
                $_SESSION['error'] = $e instanceof InvalidArgumentException || $e instanceof RuntimeException
                    ? $e->getMessage()
                    : 'Nie udało się zmienić statusu wizyty.';
            }
        }
        header('Location: pracownik.php', true, 303);
        exit;
    }
}

$stmt = $pdo->prepare(
    'SELECT d.dzien_tygodnia, d.godzina_od, d.godzina_do, p.nazwa AS placowka, p.ulica, p.miasto '
    . 'FROM dostepnosc_lekarza d JOIN placowki p ON p.id = d.placowka_id '
    . 'WHERE d.lekarz_id = ? ORDER BY d.dzien_tygodnia, d.godzina_od'
);
$stmt->execute([(int)$_SESSION['user_id']]);
$schedule = $stmt->fetchAll();

$appointmentsStmt = $pdo->prepare(
    'SELECT w.id, w.data_wizyty, w.godzina_wizyty, w.status, w.czas_trwania_minut, w.cena, '
    . 'w.notatki, s.nazwa AS usluga, p.nazwa AS placowka, '
    . 'pu.imie AS patient_imie, pu.nazwisko AS patient_nazwisko, pu.telefon AS patient_phone '
    . 'FROM wizyty w '
    . 'JOIN uslugi s ON s.id = w.usluga_id '
    . 'JOIN placowki p ON p.id = w.placowka_id '
    . 'JOIN uzytkownicy pu ON pu.id = w.pacjent_id '
    . 'WHERE w.lekarz_id = ? '
    . 'AND w.status IN (\'zaplanowana\', \'potwierdzona\', \'zarejestrowana\') '
    . 'AND (w.data_wizyty > CURDATE() OR (w.data_wizyty = CURDATE() AND w.godzina_wizyty >= CURTIME())) '
    . 'ORDER BY w.data_wizyty, w.godzina_wizyty'
);
$appointmentsStmt->execute([(int)$_SESSION['user_id']]);
$appointments = $appointmentsStmt->fetchAll();

$days = [1 => 'Poniedziałek', 2 => 'Wtorek', 3 => 'Środa', 4 => 'Czwartek', 5 => 'Piątek', 6 => 'Sobota', 7 => 'Niedziela'];
$appointmentStatuses = [
    'zaplanowana' => 'Zaplanowana',
    'potwierdzona' => 'Potwierdzona',
    'zarejestrowana' => 'Zarejestrowana',
    'zakonczona' => 'Zakończona',
    'anulowana' => 'Anulowana',
    'nieobecnosc' => 'Nieobecność',
];
$allowedTransitions = [
    'zaplanowana' => ['potwierdzona', 'anulowana'],
    'potwierdzona' => ['zarejestrowana', 'anulowana'],
    'zarejestrowana' => ['zakonczona', 'nieobecnosc'],
];
$escape = static fn (mixed $value): string => htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
$pageTitle = 'Panel pracownika | Przychodnia Pochodnia';
require_once __DIR__ . '/../src/templates/header.php';
?>
<section class="content-section account-layout">
    <div class="section-heading">
        <p class="eyebrow">Strefa personelu</p>
        <h1>Panel pracownika</h1>
        <p>Witaj, <?= htmlspecialchars($_SESSION['user_name'] ?? '', ENT_QUOTES, 'UTF-8') ?>. Poniżej znajdziesz swój aktualny grafik dostępności.</p>
    </div>
    <?php if (isset($_SESSION['success'])): ?>
        <div class="alert alert-success" role="status"><?= $escape($_SESSION['success']); unset($_SESSION['success']); ?></div>
    <?php endif; ?>
    <?php if (isset($_SESSION['error'])): ?>
        <div class="alert alert-danger" role="alert"><?= $escape($_SESSION['error']); unset($_SESSION['error']); ?></div>
    <?php endif; ?>

    <section class="auth-card mb-4">
        <h2 class="form-section-title mt-0">Nadchodzące wizyty</h2>
        <?php if (!$appointments): ?>
            <p class="text-muted mb-0">Brak nadchodzących wizyt przypisanych do tego pracownika.</p>
        <?php else: ?>
            <div class="table-responsive">
                <table class="table align-middle mb-0">
                    <thead><tr><th>Termin</th><th>Pacjent</th><th>Usługa</th><th>Placówka</th><th>Status i zmiana</th></tr></thead>
                    <tbody>
                    <?php foreach ($appointments as $appointment): ?>
                        <?php $nextStatuses = $allowedTransitions[$appointment['status']] ?? []; ?>
                        <tr>
                            <td><?= $escape(date('d.m.Y', strtotime($appointment['data_wizyty']))) ?><br><strong><?= $escape(substr($appointment['godzina_wizyty'], 0, 5)) ?></strong></td>
                            <td><?= $escape($appointment['patient_imie'] . ' ' . $appointment['patient_nazwisko']) ?><br><small class="text-muted"><?= $escape($appointment['patient_phone'] ?? '') ?></small></td>
                            <td><?= $escape($appointment['usluga']) ?><br><small class="text-muted"><?= (int)$appointment['czas_trwania_minut'] ?> min · <?= $escape(number_format((float)$appointment['cena'], 2, ',', ' ')) ?> zł</small></td>
                            <td><?= $escape($appointment['placowka']) ?></td>
                            <td>
                                <strong><?= $escape($appointmentStatuses[$appointment['status']] ?? $appointment['status']) ?></strong>
                                <?php if ($nextStatuses): ?>
                                    <form method="post" action="pracownik.php" class="mt-2" onsubmit="return confirm('Zmienić status tej wizyty?');">
                                        <input type="hidden" name="csrf_token" value="<?= $escape(csrfToken()) ?>">
                                        <input type="hidden" name="action" value="appointment_status">
                                        <input type="hidden" name="appointment_id" value="<?= (int)$appointment['id'] ?>">
                                        <select class="form-select form-control mb-2" name="status" aria-label="Nowy status wizyty">
                                            <?php foreach ($nextStatuses as $nextStatus): ?>
                                                <option value="<?= $escape($nextStatus) ?>"><?= $escape($appointmentStatuses[$nextStatus]) ?></option>
                                            <?php endforeach; ?>
                                        </select>
                                        <button class="button button-outline button-small" type="submit">Zapisz</button>
                                    </form>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </section>

    <section class="auth-card">
        <h2 class="form-section-title mt-0">Grafik dostępności</h2>
        <?php if (!$schedule): ?>
            <p class="text-muted mb-0">Nie przypisano jeszcze godzin przyjęć do tego konta.</p>
        <?php else: ?>
            <div class="table-responsive">
                <table class="table align-middle">
                    <thead><tr><th>Dzień</th><th>Godziny</th><th>Placówka</th><th>Adres</th></tr></thead>
                    <tbody>
                    <?php foreach ($schedule as $item): ?>
                        <tr>
                            <td><?= htmlspecialchars($days[(int)$item['dzien_tygodnia']] ?? 'Nieznany dzień', ENT_QUOTES, 'UTF-8') ?></td>
                            <td><?= htmlspecialchars(substr($item['godzina_od'], 0, 5) . '–' . substr($item['godzina_do'], 0, 5), ENT_QUOTES, 'UTF-8') ?></td>
                            <td><?= htmlspecialchars($item['placowka'], ENT_QUOTES, 'UTF-8') ?></td>
                            <td><?= htmlspecialchars($item['ulica'] . ', ' . $item['miasto'], ENT_QUOTES, 'UTF-8') ?></td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </section>
</section>
<?php require_once __DIR__ . '/../src/templates/footer.php'; ?>
