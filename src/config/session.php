<?php
// src/config/session.php

// Uruchamiamy sesję tylko wtedy, gdy jeszcze nie wystartowała
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Funkcja pomocnicza: czy użytkownik jest zalogowany
function isLoggedIn() {
    return isset($_SESSION['user_id']);
}

// Funkcja pomocnicza: sprawdzanie roli (pacjent, lekarz, admin)
function hasRole($role) {
    return isset($_SESSION['role']) && $_SESSION['role'] === $role;
}