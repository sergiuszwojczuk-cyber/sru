<?php require_once __DIR__ . '/../src/templates/header.php'; ?>

<div class="p-5 mb-4 bg-white rounded-3 shadow-sm mt-4">
    <div class="container-fluid py-3">
        <?php if (isLoggedIn()): ?>
            <h1 class="display-5 fw-bold text-primary">Witaj, <?php echo htmlspecialchars($_SESSION['user_name'] ?? $_SESSION['email']); ?>! 馃憢</h1>
            <p class="col-md-8 fs-4">
                Jeste艣 zalogowany jako: 
                <span class="badge bg-success">
                    <?php echo strtoupper(htmlspecialchars($_SESSION['role'])); ?>
                </span>
            </p>
            <hr class="my-4">
            
            <?php if (hasRole('pacjent')): ?>
                <p>Tw贸j system rezerwacji wizyt medycznych jest gotowy do dzia艂ania.</p>
                <a class="btn btn-primary btn-lg" href="#" role="button">馃搮 Zarezerwuj wizyt臋 (Etap 3)</a>
                <a class="btn btn-outline-primary btn-lg ms-2" href="#" role="button">Moje wizyty</a>
            <?php elseif (hasRole('lekarz')): ?>
                <p>Witaj w panelu lekarza. Zarz膮dzaj swoimi pacjentami i grafikiem wizyt.</p>
                <a class="btn btn-info text-white btn-lg" href="#" role="button">馃搵 Zobacz m贸j grafik (Etap 3)</a>
            <?php elseif (hasRole('administrator')): ?>
                <p>Oto panel zarz膮dzania systemem Przychodni Pochodnia.</p>
                <a class="btn btn-warning btn-lg" href="#" role="button">鈿欙笍 Panel Administratora (Etap 3)</a>
            <?php endif; ?>

        <?php else: ?>
            <h1 class="display-5 fw-bold text-primary">Przychodnia Pochodnia 鈿曪笍</h1>
            <p class="col-md-8 fs-4">Innowacyjny system rezerwacji wizyt lekarskich online. Szybko, wygodnie i bez kolejek.</p>
            <hr class="my-4">
            <p>Zaloguj si臋 na swoje konto lub za艂贸偶 nowe, aby zarezerwowa膰 wizyt臋 u specjalisty.</p>
            <a class="btn btn-primary btn-lg" href="login.php" role="button">Zaloguj si臋</a>
            <a class="btn btn-outline-secondary btn-lg ms-2" href="register.php" role="button">Za艂贸偶 konto</a>
        <?php endif; ?>
    </div>
</div>

<?php require_once __DIR__ . '/../src/templates/footer.php'; ?>