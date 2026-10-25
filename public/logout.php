<?php
// public/logout.php
require_once __DIR__ . '/../src/config/session.php';

// Czyszczenie sesji i jej niszczenie
$_SESSION = array();

if (ini_get("session.use_cookies")) {
    $params = session_get_cookie_params();
    setcookie(session_name(), '', time() - 42000,
        $params["path"], $params["domain"],
        $params["secure"], $params["httponly"]
    );
}

session_destroy();

// Przekierowanie do logowania
header("Location: login.php");
exit();