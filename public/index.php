<?php
require_once '../src/config/database.php';
?>
<!DOCTYPE html>
<html lang="pl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Przychodnia Pochodnia</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            text-align: center;
            padding: 50px;
            background: linear-gradient(135deg, #667eea, #764ba2);
            color: white;
            min-height: 100vh;
        }
        h1 { font-size: 3rem; margin-bottom: 20px; }
        .success { color: #4ade80; font-size: 1.3rem; }
    </style>
</head>
<body>
    <h1>🏥 Przychodnia Pochodnia</h1>
    <p class="success">✅ Aplikacja została pomyślnie zdeployowana na Railway!</p>
    <hr style="margin: 30px auto; max-width: 400px;">
    <p><strong>Adres:</strong> <?php echo $_SERVER['HTTP_HOST']; ?></p>
    <p><strong>Data deployu:</strong> <?php echo date('Y-m-d H:i:s'); ?></p>
    
    <br><br>
    <a href="test-db.php" style="color:white; background:#4ade80; padding:12px 25px; text-decoration:none; border-radius:8px; font-weight:bold;">
        → Test połączenia z bazą danych
    </a>
</body>
</html>