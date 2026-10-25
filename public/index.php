<?php
require_once __DIR__ . '/../src/config/session.php';
$pageTitle = 'Strona główna | Przychodnia Pochodnia';
require_once __DIR__ . '/../src/templates/header.php';
?>

<?php if (isLoggedIn()): ?>
    <?php
    $dashboard = match ($_SESSION['role'] ?? '') {
        'pacjent', 'klient' => [
            'label' => 'Panel klienta',
            'cards' => [
                ['Dane pacjenta', 'Twoje konto jest aktywne. W razie potrzeby skontaktuj się z rejestracją, aby zaktualizować dane.'],
                ['Wizyty', 'Internetowa rezerwacja wizyt będzie dostępna po uruchomieniu modułu rezerwacji.'],
            ],
        ],
        'lekarz', 'pracownik' => [
            'label' => 'Panel pracownika',
            'cards' => [
                ['Profil pracownika', 'Twoje konto personelu jest aktywne. W sprawie aktualizacji danych skontaktuj się z administratorem.'],
                ['Grafik i wizyty', 'Sprawdź swój grafik i informacje o dostępności w panelu pracownika.'],
            ],
        ],
        'administrator' => [
            'label' => 'Panel administratora',
            'cards' => [
                ['Konto administratora', 'Zalogowano do konta z uprawnieniami administratora systemu.'],
                ['Zarządzanie przychodnią', 'Narzędzia do zarządzania personelem i wizytami będą dostępne po uruchomieniu odpowiedniego modułu.'],
            ],
        ],
        default => [
            'label' => 'Panel użytkownika',
            'cards' => [
                ['Twoje konto', 'Twoje konto jest aktywne.'],
                ['Wizyty', 'Informacje o wizytach będą dostępne po uruchomieniu modułu rezerwacji.'],
            ],
        ],
    };
    ?>
    <section class="dashboard-hero">
        <div class="dashboard-copy">
            <p class="eyebrow"><?= htmlspecialchars($dashboard['label'], ENT_QUOTES, 'UTF-8') ?></p>
            <h1>Dzień dobry, <?= htmlspecialchars($_SESSION['user_name'] ?? $_SESSION['email'], ENT_QUOTES, 'UTF-8') ?></h1>
            <p class="lead">Witamy w systemie Przychodni Pochodnia.</p>
        </div>
        <div class="role-card">
            <span class="role-label">Zalogowano jako</span>
            <strong><?= htmlspecialchars(userRoleLabel($_SESSION['role'] ?? ''), ENT_QUOTES, 'UTF-8') ?></strong>
            <span class="role-email"><?= htmlspecialchars($_SESSION['email'] ?? '', ENT_QUOTES, 'UTF-8') ?></span>
        </div>
    </section>

    <section class="content-section">
        <div class="section-heading">
            <p class="eyebrow">Twoje konto</p>
            <h2>Wszystko, czego potrzebujesz, w jednym miejscu</h2>
        </div>
        <div class="feature-grid">
            <?php foreach ($dashboard['cards'] as $index => [$title, $description]): ?>
            <article class="feature-card">
                <span class="feature-number"><?= sprintf('%02d', $index + 1) ?></span>
                <h3><?= htmlspecialchars($title, ENT_QUOTES, 'UTF-8') ?></h3>
                <p><?= htmlspecialchars($description, ENT_QUOTES, 'UTF-8') ?></p>
            </article>
            <?php endforeach; ?>
            <article class="feature-card">
                <span class="feature-number">03</span>
                <h3>Kontakt z przychodnią</h3>
                <p>Zadzwoń pod numer <a href="tel:+48221234567">22 123 45 67</a> lub napisz na <a href="mailto:kontakt@pochodnia.pl">kontakt@pochodnia.pl</a>.</p>
            </article>
        </div>
    </section>
<?php else: ?>
    <section class="hero">
        <div class="hero-copy">
            <p class="eyebrow">Przychodnia Pochodnia · Warszawa</p>
            <h1>Zdrowie zaczyna się od dobrej opieki.</h1>
            <p class="lead">Poznaj zespół specjalistów, załóż konto pacjenta i miej najważniejsze informacje zawsze pod ręką.</p>
            <div class="hero-actions">
                <a class="button button-primary" href="register.php">Załóż konto pacjenta</a>
                <a class="button button-secondary" href="login.php">Zaloguj się</a>
            </div>
            <div class="hero-note">
                <span class="status-dot" aria-hidden="true"></span>
                <span>Opieka w jednym, wygodnym miejscu</span>
            </div>
        </div>
        <div class="hero-panel" aria-label="Informacje o przychodni">
            <div class="hero-panel-top">
                <span class="panel-kicker">Dobra opieka, blisko Ciebie</span>
                <span class="panel-mark" aria-hidden="true">P</span>
            </div>
            <div class="panel-line"></div>
            <p class="panel-caption">Wspieramy Cię na każdym etapie dbania o zdrowie.</p>
            <div class="panel-stats">
                <div><strong>5</strong><span>specjalizacji</span></div>
                <div><strong>Pon–Pt</strong><span>dni przyjęć</span></div>
            </div>
        </div>
    </section>

    <section class="content-section">
        <div class="section-heading">
            <p class="eyebrow">Nasza oferta</p>
            <h2>Specjaliści, którym możesz zaufać</h2>
            <p>Wybierz zakres opieki dopasowany do swoich potrzeb.</p>
        </div>
        <div class="specialty-grid">
            <article class="specialty-card"><span>01</span><h3>Kardiologia</h3><p>Profilaktyka i diagnostyka chorób serca.</p></article>
            <article class="specialty-card"><span>02</span><h3>Okulistyka</h3><p>Badania wzroku i konsultacje okulistyczne.</p></article>
            <article class="specialty-card"><span>03</span><h3>Pediatria</h3><p>Opieka i konsultacje dla dzieci.</p></article>
            <article class="specialty-card"><span>04</span><h3>Medycyna rodzinna</h3><p>Podstawowa opieka dla całej rodziny.</p></article>
            <article class="specialty-card"><span>05</span><h3>Dermatologia</h3><p>Konsultacje dotyczące zdrowia skóry.</p></article>
        </div>
    </section>

    <section class="contact-band">
        <div>
            <p class="eyebrow">Jesteśmy do Twojej dyspozycji</p>
            <h2>Masz pytanie? Chętnie pomożemy.</h2>
        </div>
        <div class="contact-details">
            <a href="tel:+48221234567">22 123 45 67</a>
            <a href="mailto:kontakt@pochodnia.pl">kontakt@pochodnia.pl</a>
            <span>ul. Zdrowa 10, Warszawa</span>
        </div>
    </section>
<?php endif; ?>

<?php require_once __DIR__ . '/../src/templates/footer.php'; ?>
