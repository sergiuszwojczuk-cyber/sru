<?php
// src/config/session.php
session_start();
session_regenerate_id(true); // Zabezpieczenie na 6: Zapobiega kradzieży sesji

// Pomocnicza funkcja do sprawdzania, czy ktoś jest zalogowany
function isLoggedIn() {
    return isset($_SESSION['user_id']);
}

// Pomocnicza funkcja do sprawdzania roli
function hasRole($role) {
    return isset($_SESSION['role']) && $_SESSION['role'] === $role;
}