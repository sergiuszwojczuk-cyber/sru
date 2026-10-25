<?php
// public/logout.php
require_once __DIR__ . '/../src/config/session.php';

// Wyczyść wszystkie zmienne sesji
$_SESSION = array();

// Usuń ciasteczko sesji
if (ini_get("session.use_cookies")) {
    $params = session_get_cookie_params();
    setcookie(session_name(), '', time() - 42000,
        $params["path"], $params["domain"],
        $params["secure"], $params["httponly"]
    );
}

// Zniszcz sesję
session_destroy();

// Przekieruj na stronę logowania
header("Location: login.php");
exit();
