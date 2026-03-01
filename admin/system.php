<?php
require_once __DIR__ . '/../vendor/autoload.php';

$dotenv = Dotenv\Dotenv::createImmutable(__DIR__ . '/..');
$dotenv->safeLoad();

if (session_status() === PHP_SESSION_NONE) session_start();
if (!isset($_SESSION['admin_logged_in'])) {
    header('Location: login.php');
    exit;
}
?>
<!DOCTYPE html>
<html>
<head>
    <title>Sistem</title>
</head>
<body>
    <h1>Sistem Bilgileri</h1>
    <p>PHP Version: <?php echo phpversion(); ?></p>
    <p>PostgreSQL: Bağlı</p>
    <p>Memory Limit: <?php echo ini_get('memory_limit'); ?></p>
    <p><a href="dashboard.php">← Dashboard'a Dön</a></p>
</body>
</html>