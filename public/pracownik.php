<?php
require_once __DIR__ . '/../src/config/auth.php';
require_once __DIR__ . '/../src/config/database.php';
requireRole('pracownik');

$pdo = getDbConnection();
$stmt = $pdo->prepare(
    'SELECT d.dzien_tygodnia, d.godzina_od, d.godzina_do, p.nazwa AS placowka, p.ulica, p.miasto '
    . 'FROM dostepnosc_lekarza d JOIN placowki p ON p.id = d.placowka_id '
    . 'WHERE d.lekarz_id = ? ORDER BY d.dzien_tygodnia, d.godzina_od'
);
$stmt->execute([(int)$_SESSION['user_id']]);
$schedule = $stmt->fetchAll();
$days = [1 => 'Poniedziałek', 2 => 'Wtorek', 3 => 'Środa', 4 => 'Czwartek', 5 => 'Piątek', 6 => 'Sobota', 7 => 'Niedziela'];
$pageTitle = 'Panel pracownika | Przychodnia Pochodnia';
require_once __DIR__ . '/../src/templates/header.php';
?>
<section class="content-section account-layout">
    <div class="section-heading">
        <p class="eyebrow">Strefa personelu</p>
        <h1>Panel pracownika</h1>
        <p>Witaj, <?= htmlspecialchars($_SESSION['user_name'] ?? '', ENT_QUOTES, 'UTF-8') ?>. Poniżej znajdziesz swój aktualny grafik dostępności.</p>
    </div>
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
