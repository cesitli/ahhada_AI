<?php
require_once __DIR__ . '/../vendor/autoload.php';

$dotenv = Dotenv\Dotenv::createImmutable(__DIR__ . '/..');
$dotenv->safeLoad();

if (session_status() === PHP_SESSION_NONE) session_start();
if (!isset($_SESSION['admin_logged_in'])) {
    header('Location: login.php');
    exit;
}

// Database
$dsn = "pgsql:host=" . ($_ENV['DB_HOST'] ?? 'localhost') . 
       ";port=" . ($_ENV['DB_PORT'] ?? '5432') . 
       ";dbname=" . ($_ENV['DB_NAME'] ?? 'ahhada_s1');
$pdo = new PDO($dsn, 
               $_ENV['DB_USERNAME'] ?? 'ahhada_s1', 
               $_ENV['DB_PASS'] ?? '', 
               [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);

$users = $pdo->query("SELECT * FROM users ORDER BY id DESC")->fetchAll();
?>
<!DOCTYPE html>
<html>
<head>
    <title>Kullanıcılar</title>
    <style>
        body { font-family: Arial; padding: 20px; }
        table { width: 100%; border-collapse: collapse; }
        th, td { padding: 10px; border: 1px solid #ddd; }
    </style>
</head>
<body>
    <h1>Kullanıcılar (<?php echo count($users); ?>)</h1>
    <table>
        <tr><th>ID</th><th>Kullanıcı Adı</th><th>E-posta</th><th>Kayıt Tarihi</th></tr>
        <?php foreach ($users as $user): ?>
        <tr>
            <td><?php echo $user['id']; ?></td>
            <td><?php echo htmlspecialchars($user['username']); ?></td>
            <td><?php echo htmlspecialchars($user['email'] ?? ''); ?></td>
            <td><?php echo date('d.m.Y H:i', strtotime($user['created_at'])); ?></td>
        </tr>
        <?php endforeach; ?>
    </table>
    <p><a href="dashboard.php">← Dashboard'a Dön</a></p>
</body>
</html>