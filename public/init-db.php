<?php
// public/init-db.php
require_once __DIR__ . '/../src/config/database.php';

try {
    $pdo = getDbConnection();
    echo "Połączono z bazą. Rozpoczynanie tworzenia tabel...<br>";
    
    $sqlFile = __DIR__ . '/../database/database.sql';
    
    if (!file_exists($sqlFile)) {
        die("Błąd: Nie znaleziono pliku SQL w: $sqlFile");
    }
    
    $sql = file_get_contents($sqlFile);
    
    // Ważne: Railway/MySQL czasem nie lubi wielu komend w jednym exec()
    // ale przy standardowych tabelach powinno przejść.
    $pdo->exec($sql);
    
    echo "<h2>Sukces! Tabele zostały utworzone.</h2>";
    echo "<a href='test-db.php'>Sprawdź diagnostykę</a>";

} catch (Exception $e) {
    echo "<h2>Wystąpił błąd:</h2>";
    echo "<pre>" . $e->getMessage() . "</pre>";
}