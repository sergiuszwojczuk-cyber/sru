<?php
$pageTitle = 'Rejestracja | Przychodnia Pochodnia';
require_once __DIR__ . '/../src/templates/header.php';
?>

<section class="auth-layout auth-layout-register">
    <div class="auth-intro">
        <p class="eyebrow">Dołącz do nas</p>
        <h1>Twoje zdrowie. Twoje konto. Twoja wygoda.</h1>
        <p>Utwórz konto pacjenta, aby bezpiecznie korzystać z usług naszej przychodni.</p>
        <a class="auth-back-link" href="index.php">Powrót na stronę główną</a>
    </div>
    <div class="auth-card">
        <div class="auth-card-heading">
            <span class="auth-mark" aria-hidden="true">P</span>
            <div><h2>Utwórz konto</h2><p>Pola oznaczone gwiazdką są wymagane.</p></div>
        </div>

        <?php if (isset($_SESSION['error'])): ?>
            <div class="alert alert-danger" role="alert"><?= htmlspecialchars($_SESSION['error'], ENT_QUOTES, 'UTF-8'); unset($_SESSION['error']); ?></div>
        <?php endif; ?>

        <form action="register-action.php" method="post">
            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(csrfToken(), ENT_QUOTES, 'UTF-8') ?>">
            <h3 class="form-section-title">Dane logowania</h3>
            <div class="mb-3">
                <label class="form-label" for="email">Adres e-mail *</label>
                <input class="form-control" id="email" type="email" name="email" autocomplete="email" maxlength="254" required placeholder="np. jan@example.pl">
            </div>
            <div class="mb-4">
                <label class="form-label" for="password">Hasło *</label>
                <input class="form-control" id="password" type="password" name="password" autocomplete="new-password" minlength="8" maxlength="72" required aria-describedby="passwordHelp">
                <div class="form-text" id="passwordHelp">Co najmniej 8 znaków, maksymalnie 72 bajty.</div>
            </div>

            <h3 class="form-section-title">Dane osobowe</h3>
            <div class="row">
                <div class="col-md-6 mb-3">
                    <label class="form-label" for="imie">Imię *</label>
                    <input class="form-control" id="imie" type="text" name="imie" autocomplete="given-name" maxlength="80" required>
                </div>
                <div class="col-md-6 mb-3">
                    <label class="form-label" for="nazwisko">Nazwisko *</label>
                    <input class="form-control" id="nazwisko" type="text" name="nazwisko" autocomplete="family-name" maxlength="80" required>
                </div>
            </div>
            <div class="mb-3">
                <label class="form-label" for="telefon">Numer telefonu</label>
                <input class="form-control" id="telefon" type="tel" name="telefon" autocomplete="tel" maxlength="30" placeholder="np. 555 666 777">
            </div>
            <div class="mb-3">
                <label class="form-label" for="data_urodzenia">Data urodzenia</label>
                <input class="form-control" id="data_urodzenia" type="date" name="data_urodzenia" autocomplete="bday">
            </div>
            <div class="mb-3">
                <label class="form-label" for="adres">Adres</label>
                <input class="form-control" id="adres" type="text" name="adres" autocomplete="street-address" maxlength="255" placeholder="np. ul. Prosta 12/4">
            </div>
            <div class="row">
                <div class="col-md-8 mb-3">
                    <label class="form-label" for="miasto">Miasto</label>
                    <input class="form-control" id="miasto" type="text" name="miasto" autocomplete="address-level2" maxlength="100">
                </div>
                <div class="col-md-4 mb-3">
                    <label class="form-label" for="kod_pocztowy">Kod pocztowy</label>
                    <input class="form-control" id="kod_pocztowy" type="text" name="kod_pocztowy" autocomplete="postal-code" maxlength="12" placeholder="00-000">
                </div>
            </div>
            <button class="button button-primary button-full mt-2" type="submit">Utwórz konto pacjenta</button>
        </form>
        <p class="auth-switch">Masz już konto? <a href="login.php">Zaloguj się</a></p>
    </div>
</section>

<?php require_once __DIR__ . '/../src/templates/footer.php'; ?>
