<?php
if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

require_once __DIR__ . '/../src/config/database.php';

try {
    $pdo = getDbConnection();
    $tables = $pdo->query('SHOW TABLES')->fetchAll(PDO::FETCH_COLUMN);
    echo "Połączenie z bazą danych: OK\n";
    echo 'Liczba tabel: ' . count($tables) . "\n";

    $userCount = (int)$pdo->query('SELECT COUNT(*) FROM uzytkownicy')->fetchColumn();
    echo "Liczba użytkowników: $userCount\n";
} catch (Exception $e) {
    error_log('Database diagnostic failed: ' . $e->getMessage());
    fwrite(STDERR, "Diagnostyka bazy danych nie powiodła się. Sprawdź konfigurację połączenia i logi aplikacji.\n");
    exit(1);
}
