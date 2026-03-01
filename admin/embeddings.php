<?php
require_once __DIR__ . '/../vendor/autoload.php';

$dotenv = Dotenv\Dotenv::createImmutable(__DIR__ . '/..');
$dotenv->safeLoad();

if (session_status() === PHP_SESSION_NONE) session_start();
if (!isset($_SESSION['admin_logged_in'])) {
    header('Location: login.php');
    exit;
}

$dsn = "pgsql:host=" . ($_ENV['DB_HOST'] ?? 'localhost') . 
       ";port=" . ($_ENV['DB_PORT'] ?? '5432') . 
       ";dbname=" . ($_ENV['DB_NAME'] ?? 'ahhada_s1');
$pdo = new PDO($dsn, 
               $_ENV['DB_USERNAME'] ?? 'ahhada_s1', 
               $_ENV['DB_PASS'] ?? '', 
               [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);

$embeddings = $pdo->query("SELECT COUNT(*) FROM embedding_cache")->fetchColumn();
?>
<!DOCTYPE html>
<html>
<head>
    <title>Embeddings</title>
</head>
<body>
    <h1>Embeddings Cache</h1>
    <p>Toplam embedding: <?php echo $embeddings; ?></p>
    <p><a href="dashboard.php">← Dashboard'a Dön</a></p>
</body>
</html>