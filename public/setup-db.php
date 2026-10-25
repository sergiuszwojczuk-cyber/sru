<?php
if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

require_once __DIR__ . '/../src/config/database.php';

try {
    $sqlFile = __DIR__ . '/../database/database.sql';
    if (!is_file($sqlFile) || !is_readable($sqlFile)) {
        throw new RuntimeException('Nie znaleziono pliku database/database.sql.');
    }

    $sql = file_get_contents($sqlFile);
    if ($sql === false) {
        throw new RuntimeException('Nie można odczytać pliku database/database.sql.');
    }

    $pdo = getDbConnection();
    $queries = array_filter(array_map('trim', explode(';', $sql)));
    foreach ($queries as $query) {
        $pdo->exec($query);
    }

    echo "Schemat bazy i dane demonstracyjne zostały przygotowane.\n";
    echo "Konto administratora: admin@pochodnia.pl (hasło: demo1234).\n";
    echo "Zmień hasło demonstracyjne przed użyciem produkcyjnym.\n";
} catch (Exception $e) {
    error_log('Database setup failed: ' . $e->getMessage());
    fwrite(STDERR, "Nie udało się przygotować bazy danych. Sprawdź konfigurację połączenia i logi aplikacji.\n");
    exit(1);
}
