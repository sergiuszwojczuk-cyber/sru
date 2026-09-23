<?php
require_once __DIR__ . '/../src/config/database.php';

try {
    $pdo = getDbConnection();
    
    // Odczytaj plik SQL
    $sqlFile = __DIR__ . '/../database/database.sql';
    
    if (!file_exists($sqlFile)) {
        die("Błąd: Plik database/database.sql nie istnieje!");
    }
    
    $sql = file_get_contents($sqlFile);
    
    // Wykonaj kod SQL
    $pdo->exec($sql);
    
    echo "<h1>SUKCES! Baza danych została pomyślnie zainicjowana.</h1>";
    echo "<p>Utworzono tabele oraz wgrano dane testowe.</p>";
    echo "<a href='test-db.php'>Przejdź do testu bazy</a>";

} catch (PDOException $e) {
    echo "<h1>Błąd podczas tworzenia bazy danych:</h1>";
    echo "<pre>" . $e->getMessage() . "</pre>";
}