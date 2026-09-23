<?php
require_once '../src/config/database.php';

echo "<h2> Test połączenia z bazą - Przychodnia Pochodnia</h2>";

try {
    $stmt = $pdo->query("SELECT COUNT(*) as count FROM users");
    $users = $stmt->fetch();
    
    $stmt2 = $pdo->query("SELECT COUNT(*) as count FROM specializations");
    $specs = $stmt2->fetch();

    echo "<p><strong>Połączenie z bazą: </strong> UDANE </p>";
    echo "<p>Użytkowników w bazie: <strong>" . $users['count'] . "</strong></p>";
    echo "<p>Specjalizacji w bazie: <strong>" . $specs['count'] . "</strong></p>";
    echo "<hr>";
    echo "<p><a href='/'>← Powrót do strony głównej</a></p>";

} catch (Exception $e) {
    echo "<h3 style='color:red'>Błąd połączenia:</h3>";
    echo $e->getMessage();
}
?>