<?php require_once __DIR__ . '/../config/session.php'; ?>
<!DOCTYPE html>
<html lang="pl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description" content="Przychodnia Pochodnia w Warszawie. Poznaj naszą ofertę i zarządzaj swoim kontem pacjenta.">
    <meta name="theme-color" content="#f5f8f7">
    <title><?= htmlspecialchars($pageTitle ?? 'Przychodnia Pochodnia', ENT_QUOTES, 'UTF-8') ?></title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@400;500;600;700&family=Manrope:wght@500;600;700;800&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="assets/css/style.css" rel="stylesheet">
</head>
<body>
<nav class="site-nav">
    <div class="nav-inner">
        <a class="brand" href="index.php" aria-label="Przychodnia Pochodnia — strona główna">
            <span class="brand-mark" aria-hidden="true">P</span>
            <span>Przychodnia <strong>Pochodnia</strong></span>
        </a>
        <button class="nav-toggle" type="button" data-bs-toggle="collapse" data-bs-target="#mainNavigation" aria-controls="mainNavigation" aria-expanded="false" aria-label="Otwórz menu">
            <span></span><span></span><span></span>
        </button>
        <div class="collapse nav-collapse" id="mainNavigation">
            <ul class="nav-links">
                <li><a href="index.php">Strona główna</a></li>
                <?php if (isLoggedIn()): ?>
                    <li><span class="nav-welcome"><?= htmlspecialchars($_SESSION['user_name'] ?? $_SESSION['email'], ENT_QUOTES, 'UTF-8') ?></span></li>
                    <li><a href="profile.php">Mój profil</a></li>
                    <?php if (hasRole('pracownik')): ?>
                        <li><a href="pracownik.php">Panel pracownika</a></li>
                    <?php endif; ?>
                    <?php if (hasRole('administrator')): ?>
                        <li><a href="admin.php">Panel administratora</a></li>
                    <?php endif; ?>
                    <li>
                        <form action="logout.php" method="post" class="nav-form">
                            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(csrfToken(), ENT_QUOTES, 'UTF-8') ?>">
                            <button class="button button-small button-outline" type="submit">Wyloguj się</button>
                        </form>
                    </li>
                <?php else: ?>
                    <li><a href="login.php">Zaloguj się</a></li>
                    <li><a class="button button-small button-primary" href="register.php">Załóż konto</a></li>
                <?php endif; ?>
            </ul>
        </div>
    </div>
</nav>
<main class="site-main">
