<?php
require_once '../src/config/database.php';

echo "<h1>🔍 Diagnostyka Railway - Przychodnia Pochodnia</h1>";

try {
    // Pokazuje aktualnie używaną bazę
    $stmt = $pdo->query("SELECT DATABASE() as db_name");
    $db = $stmt->fetch();
    echo "<p><strong>Aktualna baza danych:</strong> " . htmlspecialchars($db['db_name']) . "</p>";

    // Pokazuje wszystkie tabele w bieżącej bazie
    $stmt = $pdo->query("SHOW TABLES");
    $tables = $stmt->fetchAll(PDO::FETCH_COLUMN);
    
    echo "<h2>📋 Tabele w bazie:</h2>";
    if (empty($tables)) {
        echo "<p style='color:red'>Brak tabel w bazie!</p>";
    } else {
        echo "<ul>";
        foreach ($tables as $table) {
            echo "<li>✅ " . htmlspecialchars($table) . "</li>";
        }
        echo "</ul>";
    }

    // Test zapytania do tabeli users
    $stmt = $pdo->query("SELECT COUNT(*) as count FROM users");
    $count = $stmt->fetch();
    echo "<p><strong>Liczba użytkowników:</strong> " . $count['count'] . "</p>";

    echo "<hr><p style='color:green; font-weight:bold'>✅ Połączenie z bazą działa prawidłowo.</p>";

} catch (PDOException $e) {
    echo "<h2 style='color:red'>❌ Błąd:</h2>";
    echo "<pre>" . htmlspecialchars($e->getMessage()) . "</pre>";
    
    echo "<h3>Informacje diagnostyczne:</h3>";
    echo "<p>HOST: " . getenv('MYSQLHOST') . "</p>";
    echo "<p>DATABASE: " . getenv('MYSQLDATABASE') . "</p>";
    echo "<p>USER: " . getenv('MYSQLUSER') . "</p>";
}

echo "<br><a href='index.php'>← Powrót do strony głównej</a>";
?>