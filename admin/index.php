<?php
// admin/index.php - MANUEL SESSION START

// MANUEL session başlat
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Eğer giriş yapılmışsa dashboard'a git
if (isset($_SESSION['admin_logged_in']) && $_SESSION['admin_logged_in'] === true) {
    header('Location: dashboard.php');
    exit();
}

// Cookie kontrolü
if (isset($_COOKIE['admin_logged_in']) && $_COOKIE['admin_logged_in'] === '1') {
    $_SESSION['admin_logged_in'] = true;
    $_SESSION['admin_username'] = $_COOKIE['admin_user'] ?? 'Admin';
    header('Location: dashboard.php');
    exit();
}

// Giriş yapılmamışsa login'e git
header('Location: login.php');
exit();
?>