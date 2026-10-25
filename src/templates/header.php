<!-- src/templates/header.php -->
<?php require_once __DIR__ . '/../config/session.php'; ?>
<!DOCTYPE html>
<html lang="pl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Przychodnia Pochodnia</title>
    <!-- Bootstrap 5 CDN (Profesjonalny wygląd) -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="bg-light">

<nav class="navbar navbar-expand-lg navbar-dark bg-primary mb-4">
    <div class="container">
        <a class="navbar-brand" href="index.php">⚕️ Przychodnia Pochodnia</a>
        <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-toggle="collapse" data-bs-target="#navbarNav">
            <span class="navbar-toggler-icon"></span>
        </button>
        <div class="collapse navbar-collapse" id="navbarNav">
            <ul class="navbar-nav ms-auto">
                <?php if (isLoggedIn()): ?>
                    <li class="nav-item">
                        <span class="nav-link text-warning">Witaj, <?php echo htmlspecialchars($_SESSION['email']); ?>!</span>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link btn btn-danger text-white ms-2 px-3" href="logout.php">Wyloguj</a>
                    </li>
                <?php else: ?>
                    <li class="nav-item">
                        <a class="nav-link" href="login.php">Zaloguj</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link btn btn-light text-primary ms-2 px-3" href="register.php">Załóż konto</a>
                    </li>
                <?php endif; ?>
            </ul>
        </div>
    </div>
</nav>
<div class="container">
<!-- Koniec header.php. Poniżej będzie ładowana treść konkretnej strony -->