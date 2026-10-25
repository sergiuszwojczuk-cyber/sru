<?php
// public/setup-db.php
require_once __DIR__ . '/../src/config/database.php';

try {
    $pdo = getDbConnection();
    
    $sqlFile = __DIR__ . '/../database/database.sql';
    if (!file_exists($sqlFile)) {
        die("Błąd: Nie znaleziono pliku database/database.sql");
    }

    $sql = file_get_contents($sqlFile);

    // Wyłączenie sprawdzania kluczy obcych na czas wgrywania
    $pdo->exec("SET FOREIGN_KEY_CHECKS = 0;");

    // Dzielimy plik SQL na pojedyncze komendy po średnikach
    $queries = array_filter(array_map('trim', explode(';', $sql)));

    foreach ($queries as $query) {
        if (!empty($query)) {
            $pdo->exec($query);
        }
    }

    $pdo->exec("SET FOREIGN_KEY_CHECKS = 1;");

    echo "<h1 style='color: green;'>✅ SUKCES! Baza danych została w 100% poprawnie utworzona na Railway.</h1>";
    echo "<p>Wszystkie tabele i dane testowe są gotowe.</p>";
    echo "<a href='test-db.php'>Sprawdź diagnostykę bazy</a> | <a href='index.php'>Strona główna</a>";

} catch (Exception $e) {
    echo "<h1 style='color: red;'>❌ Błąd instalacji bazy danych:</h1>";
    echo "<pre>" . $e->getMessage() . "</pre>";
}