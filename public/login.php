<?php
$pageTitle = 'Logowanie | Przychodnia Pochodnia';
require_once __DIR__ . '/../src/templates/header.php';
?>

<section class="auth-layout">
    <div class="auth-intro">
        <p class="eyebrow">Witaj ponownie</p>
        <h1>Zaloguj się do swojego konta.</h1>
        <p>Uzyskaj dostęp do swojego konta pacjenta i najważniejszych informacji.</p>
        <a class="auth-back-link" href="index.php">Powrót na stronę główną</a>
    </div>
    <div class="auth-card">
        <div class="auth-card-heading">
            <span class="auth-mark" aria-hidden="true">P</span>
            <div><h2>Logowanie</h2><p>Wpisz dane swojego konta.</p></div>
        </div>

        <?php if (isset($_SESSION['success'])): ?>
            <div class="alert alert-success" role="status"><?= htmlspecialchars($_SESSION['success'], ENT_QUOTES, 'UTF-8'); unset($_SESSION['success']); ?></div>
        <?php endif; ?>
        <?php if (isset($_SESSION['error'])): ?>
            <div class="alert alert-danger" role="alert"><?= htmlspecialchars($_SESSION['error'], ENT_QUOTES, 'UTF-8'); unset($_SESSION['error']); ?></div>
        <?php endif; ?>

        <form action="login-action.php" method="post">
            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(csrfToken(), ENT_QUOTES, 'UTF-8') ?>">
            <div class="mb-3">
                <label class="form-label" for="email">Adres e-mail</label>
                <input class="form-control" id="email" type="email" name="email" autocomplete="username" maxlength="254" required placeholder="np. jan@example.pl">
            </div>
            <div class="mb-4">
                <label class="form-label" for="password">Hasło</label>
                <input class="form-control" id="password" type="password" name="password" autocomplete="current-password" required>
            </div>
            <button class="button button-primary button-full" type="submit">Zaloguj się</button>
        </form>
        <p class="auth-switch">Nie masz jeszcze konta? <a href="register.php">Utwórz konto</a></p>
    </div>
</section>

<?php require_once __DIR__ . '/../src/templates/footer.php'; ?>
