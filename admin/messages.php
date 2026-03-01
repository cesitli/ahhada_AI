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

$messages = $pdo->query("
    SELECT m.*, u.username, c.title as conversation_title
    FROM messages m
    LEFT JOIN users u ON m.user_id = u.id
    LEFT JOIN conversations c ON m.conversation_id = c.id
    ORDER BY m.created_at DESC
    LIMIT 100
")->fetchAll();
?>
<!DOCTYPE html>
<html>
<head>
    <title>Mesajlar</title>
    <style>
        body { font-family: Arial; padding: 20px; }
        table { width: 100%; border-collapse: collapse; margin-top: 20px; }
        th, td { padding: 10px; border: 1px solid #ddd; }
        .message-content { max-width: 500px; }
    </style>
</head>
<body>
    <h1>Mesajlar (Son 100)</h1>
    <table>
        <tr>
            <th>ID</th>
            <th>Konuşma</th>
            <th>Kullanıcı</th>
            <th>Rol</th>
            <th>İçerik</th>
            <th>Tarih</th>
        </tr>
        <?php foreach ($messages as $msg): ?>
        <tr>
            <td><?php echo $msg['id']; ?></td>
            <td><?php echo htmlspecialchars($msg['conversation_title'] ?? 'N/A'); ?></td>
            <td><?php echo htmlspecialchars($msg['username'] ?? 'Anonim'); ?></td>
            <td><?php echo htmlspecialchars($msg['role'] ?? 'user'); ?></td>
            <td class="message-content"><?php echo substr(htmlspecialchars($msg['content'] ?? ''), 0, 100); ?>...</td>
            <td><?php echo date('H:i:s', strtotime($msg['created_at'])); ?></td>
        </tr>
        <?php endforeach; ?>
    </table>
    <p><a href="dashboard.php">← Dashboard'a Dön</a></p>
</body>
</html>