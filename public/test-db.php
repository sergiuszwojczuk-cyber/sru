<?php
// public/test-db.php
require_once __DIR__ . '/../src/config/database.php';

try {
    // KLUCZOWY MOMENT: Wywołujemy funkcję i przypisujemy do $pdo
    $pdo = getDbConnection();

    echo "<h1>Diagnostyka Railway - Przychodnia Pochodnia</h1>";
    echo "<p style='color: green;'>✅ Połączenie z bazą danych: OK</p>";

    // Sprawdzenie tabel
    $stmt = $pdo->query("SHOW TABLES");
    $tables = $stmt->fetchAll(PDO::FETCH_COLUMN);

    if (count($tables) > 0) {
        echo "<h3>Tabele w bazie:</h3><ul>";
        foreach ($tables as $table) {
            echo "<li>$table</li>";
        }
        echo "</ul>";

        // Sprawdzenie czy są użytkownicy testowi
        $stmtUsers = $pdo->query("SELECT first_name, last_name, role FROM users");
        $users = $stmtUsers->fetchAll(PDO::FETCH_ASSOC);
        
        echo "<h3>Użytkownicy w systemie: " . count($users) . "</h3>";
    } else {
        echo "<p style='color: red;'>⚠ Brak tabel w bazie!</p>";
    }

} catch (Exception $e) {
    echo "<h1>❌ Błąd diagnostyki:</h1>";
    echo "<pre style='background: #fee; padding: 10px; border: 1px solid red;'>" . $e->getMessage() . "</pre>";
}

echo "<br><hr><a href='index.php'>← Powrót do strony głównej</a>";