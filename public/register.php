<?php require_once __DIR__ . '/../src/templates/header.php'; ?>

<div class="row justify-content-center">
    <div class="col-md-7">
        <div class="card shadow">
            <div class="card-header bg-primary text-white text-center">
                <h4>Rejestracja Pacjenta</h4>
            </div>
            <div class="card-body">
                <?php if(isset($_SESSION['error'])): ?>
                    <div class="alert alert-danger"><?php echo $_SESSION['error']; unset($_SESSION['error']); ?></div>
                <?php endif; ?>

                <form action="register-action.php" method="POST">
                    <h5 class="text-muted mb-3">Dane logowania</h5>
                    <div class="mb-3">
                        <label class="form-label">Adres Email *</label>
                        <input type="email" name="email" class="form-control" required placeholder="np. jan@domena.pl">
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Hasło (min. 8 znaków) *</label>
                        <input type="password" name="password" class="form-control" minlength="8" required>
                    </div>

                    <h5 class="text-muted mb-3 mt-4">Dane osobowe</h5>
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Imię *</label>
                            <input type="text" name="imie" class="form-control" required>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Nazwisko *</label>
                            <input type="text" name="nazwisko" class="form-control" required>
                        </div>
                    </div>

                    <div class="mb-3">
                        <label class="form-label">Numer telefonu</label>
                        <input type="text" name="telefon" class="form-control" placeholder="np. 555666777">
                    </div>

                    <div class="mb-3">
                        <label class="form-label">Data urodzenia</label>
                        <input type="date" name="data_urodzenia" class="form-control">
                    </div>

                    <div class="mb-3">
                        <label class="form-label">Adres (ulica i numer)</label>
                        <input type="text" name="adres" class="form-control" placeholder="np. ul. Prosta 12/4">
                    </div>

                    <div class="row">
                        <div class="col-md-8 mb-3">
                            <label class="form-label">Miasto</label>
                            <input type="text" name="miasto" class="form-control" placeholder="np. Warszawa">
                        </div>
                        <div class="col-md-4 mb-3">
                            <label class="form-label">Kod pocztowy</label>
                            <input type="text" name="kod_pocztowy" class="form-control" placeholder="00-000">
                        </div>
                    </div>

                    <button type="submit" class="btn btn-primary w-100 mt-2">Załóż konto</button>
                </form>
            </div>
            <div class="card-footer text-center">
                Masz już konto? <a href="login.php">Zaloguj się</a>
            </div>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../src/templates/footer.php'; ?>