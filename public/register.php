<?php require_once __DIR__ . '/../src/templates/header.php'; ?>

<div class="row justify-content-center">
    <div class="col-md-6">
        <div class="card shadow">
            <div class="card-header bg-primary text-white text-center">
                <h4>Rejestracja Pacjenta</h4>
            </div>
            <div class="card-body">
                <!-- Komunikaty o błędach/sukcesach -->
                <?php if(isset($_SESSION['error'])): ?>
                    <div class="alert alert-danger"><?php echo $_SESSION['error']; unset($_SESSION['error']); ?></div>
                <?php endif; ?>
                
                <form action="register-action.php" method="POST">
                    <h5 class="mb-3 text-muted">Dane logowania</h5>
                    <div class="mb-3">
                        <label class="form-label">Adres Email</label>
                        <input type="email" name="email" class="form-control" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Hasło</label>
                        <input type="password" name="password" class="form-control" minlength="8" required>
                    </div>

                    <h5 class="mb-3 text-muted mt-4">Dane osobiste</h5>
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Imię</label>
                            <input type="text" name="first_name" class="form-control" required>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Nazwisko</label>
                            <input type="text" name="last_name" class="form-control" required>
                        </div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">PESEL</label>
                        <input type="text" name="pesel" class="form-control" pattern="[0-9]{11}" title="PESEL musi mieć 11 cyfr" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Telefon</label>
                        <input type="text" name="phone" class="form-control">
                    </div>
                    <button type="submit" class="btn btn-primary w-100 mt-3">Załóż konto</button>
                </form>
            </div>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../src/templates/footer.php'; ?>