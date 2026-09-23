<?php
// src/config/database.php - wersja pod Railway

$host     = getenv('DB_HOST')     ?: getenv('MYSQLHOST')     ?: 'localhost';
$dbname   = getenv('DB_NAME')     ?: getenv('MYSQLDATABASE') ?: 'przychodnia_pochodnia';
$username = getenv('DB_USER')     ?: getenv('MYSQLUSER')     ?: 'root';
$password = getenv('DB_PASSWORD') ?: getenv('MYSQLPASSWORD') ?: '';
$port     = getenv('DB_PORT')     ?: getenv('MYSQLPORT')     ?: '3306';

try {
    $dsn = "mysql:host=$host;port=$port;dbname=$dbname;charset=utf8mb4";
    $pdo = new PDO($dsn, $username, $password, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
    ]);

    // Tylko do testów - usuń później
    // echo "✅ Połączenie z bazą danych udane!";

} catch (PDOException $e) {
    http_response_code(500);
    die("❌ Błąd połączenia z bazą: " . $e->getMessage());
}
?>