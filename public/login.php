<?php require_once DIR . '/../src/templates/header.php'; ?>

<div class="row justify-content-center mt-5">
    <div class="col-md-5">
        <div class="card shadow">
            <div class="card-header bg-primary text-white text-center">
                <h4>Logowanie do systemu</h4>
            </div>
            <div class="card-body">
                <?php if(isset($_SESSION['success'])): ?>
                    <div class="alert alert-success"><?php echo $_SESSION['success']; unset($_SESSION['success']); ?></div>
                <?php endif; ?>

                <?php if(isset($_SESSION['error'])): ?>
                    <div class="alert alert-danger"><?php echo $_SESSION['error']; unset($_SESSION['error']); ?></div>
                <?php endif; ?>

                <form action="login-action.php" method="POST">
                    <div class="mb-3">
                        <label class="form-label">Adres Email</label>
                        <input type="email" name="email" class="form-control" required placeholder="np. pacjent@pochodnia.pl">
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Hasło</label>
                        <input type="password" name="password" class="form-control" required>
                    </div>
                    <button type="submit" class="btn btn-primary w-100">Zaloguj się</button>
                </form>
            </div>
            <div class="card-footer text-center">
                <small>Nie masz jeszcze konta? <a href="register.php">Zarejestruj się</a></small>
            </div>
        </div>
    </div>
</div>

<?php require_once DIR . '/../src/templates/footer.php'; ?>