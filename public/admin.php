<?php
require_once __DIR__ . '/../src/config/auth.php';
require_once __DIR__ . '/../src/config/database.php';
requireRole('administrator');

$pdo = getDbConnection();
$counts = $pdo->query(
    'SELECT COUNT(*) AS users, '
    . 'SUM(rola = \'pacjent\') AS clients, '
    . 'SUM(rola = \'lekarz\') AS employees, '
    . 'SUM(rola = \'administrator\') AS administrators '
    . 'FROM uzytkownicy'
)->fetch();
$users = $pdo->query(
    'SELECT id, imie, nazwisko, email, rola, czy_aktywny, utworzono_at '
    . 'FROM uzytkownicy ORDER BY utworzono_at DESC, id DESC LIMIT 100'
)->fetchAll();
$pageTitle = 'Panel administratora | Przychodnia Pochodnia';
require_once __DIR__ . '/../src/templates/header.php';
?>
<section class="content-section account-layout">
    <div class="section-heading">
        <p class="eyebrow">Zarządzanie systemem</p>
        <h1>Panel administratora</h1>
        <p>Przegląd kont i ról użytkowników przychodni.</p>
    </div>
    <div class="admin-stats">
        <article class="feature-card"><span class="feature-number">KONTA</span><h2><?= (int)$counts['users'] ?></h2><p>Użytkownicy ogółem</p></article>
        <article class="feature-card"><span class="feature-number">KLIENCI</span><h2><?= (int)$counts['clients'] ?></h2><p>Pacjenci przychodni</p></article>
        <article class="feature-card"><span class="feature-number">PERSONEL</span><h2><?= (int)$counts['employees'] ?></h2><p>Konta pracowników</p></article>
        <article class="feature-card"><span class="feature-number">ADMIN</span><h2><?= (int)$counts['administrators'] ?></h2><p>Administratorzy</p></article>
    </div>
    <section class="auth-card">
        <h2 class="form-section-title mt-0">Konta użytkowników</h2>
        <div class="table-responsive">
            <table class="table align-middle">
                <thead><tr><th>Użytkownik</th><th>E-mail</th><th>Rola</th><th>Status</th><th>Utworzono</th></tr></thead>
                <tbody>
                <?php foreach ($users as $user): ?>
                    <tr>
                        <td><?= htmlspecialchars($user['imie'] . ' ' . $user['nazwisko'], ENT_QUOTES, 'UTF-8') ?></td>
                        <td><?= htmlspecialchars($user['email'], ENT_QUOTES, 'UTF-8') ?></td>
                        <td><?= htmlspecialchars(userRoleLabel($user['rola']), ENT_QUOTES, 'UTF-8') ?></td>
                        <td><?= (bool)$user['czy_aktywny'] ? 'Aktywne' : 'Nieaktywne' ?></td>
                        <td><?= htmlspecialchars(date('Y-m-d', strtotime($user['utworzono_at'])), ENT_QUOTES, 'UTF-8') ?></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php if (count($users) === 100): ?>
            <p class="text-muted small mb-0">Wyświetlono 100 ostatnio utworzonych kont.</p>
        <?php endif; ?>
    </section>
</section>
<?php require_once __DIR__ . '/../src/templates/footer.php'; ?>
