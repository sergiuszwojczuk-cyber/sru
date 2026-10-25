<?php require_once __DIR__ . '/../src/templates/header.php'; ?>

<div class="p-5 mb-4 bg-white rounded-3 shadow-sm">
    <div class="container-fluid py-3">
        <?php if (isLoggedIn()): ?>
            <h1 class="display-5 fw-bold text-primary">Witaj, <?php echo htmlspecialchars($_SESSION['user_name'] ?? $_SESSION['email']); ?>! 👋</h1>
            <p class="col-md-8 fs-4">
                Jesteś zalogowany jako: 
                <span class="badge bg-success">
                    <?php echo strtoupper(htmlspecialchars($_SESSION['role'])); ?>
                </span>
            </p>
            <hr class="my-4">
            <p>Twój system rezerwacji wizyt medycznych jest gotowy do działania.</p>
            
            <?php if (hasRole('pacjent')): ?>
                <a class="btn btn-primary btn-lg" href="#" role="button">📅 Zarezerwuj wizytę (Etap 3)</a>
            <?php elseif (hasRole('lekarz')): ?>
                <a class="btn btn-info text-white btn-lg" href="#" role="button">📋 Zobacz mój grafik (Etap 3)</a>
            <?php elseif (hasRole('admin')): ?>
                <a class="btn btn-warning btn-lg" href="#" role="button">⚙️ Panel Administratora (Etap 3)</a>
            <?php endif; ?>

        <?php else: ?>
            <h1 class="display-5 fw-bold text-primary">Przychodnia Pochodnia ⚕️</h1>
            <p class="col-md-8 fs-4">Innowacyjny system rezerwacji wizyt lekarskich online. Szybko, wygodnie i bez kolejek.</p>
            <hr class="my-4">
            <p>Zaloguj się na swoje konto lub załóż nowe, aby zarezerwować wizytę u specjalisty.</p>
            <a class="btn btn-primary btn-lg" href="login.php" role="button">Zaloguj się</a>
            <a class="btn btn-outline-secondary btn-lg ms-2" href="register.php" role="button">Załóż konto</a>
        <?php endif; ?>
    </div>
</div>

<?php require_once __DIR__ . '/../src/templates/footer.php'; ?>