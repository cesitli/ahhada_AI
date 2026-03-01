<?php
// admin/conversations.php
require_once __DIR__ . '/../vendor/autoload.php';

$dotenv = Dotenv\Dotenv::createImmutable(__DIR__ . '/..');
$dotenv->safeLoad();

if (session_status() === PHP_SESSION_NONE) session_start();
if (!isset($_SESSION['admin_logged_in'])) {
    header('Location: login.php');
    exit;
}

// Database connection
$dsn = "pgsql:host=" . ($_ENV['DB_HOST'] ?? 'localhost') . 
       ";port=" . ($_ENV['DB_PORT'] ?? '5432') . 
       ";dbname=" . ($_ENV['DB_NAME'] ?? 'ahhada_s1');
$pdo = new PDO($dsn, 
               $_ENV['DB_USERNAME'] ?? 'ahhada_s1', 
               $_ENV['DB_PASS'] ?? '', 
               [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);

// Pagination
$page = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
$limit = 20;
$offset = ($page - 1) * $limit;

// Total count
$total = $pdo->query("SELECT COUNT(*) FROM conversations")->fetchColumn();
$total_pages = ceil($total / $limit);

// Get conversations
$stmt = $pdo->prepare("
    SELECT c.*, u.username, 
           (SELECT COUNT(*) FROM messages m WHERE m.conversation_id = c.id) as message_count
    FROM conversations c
    LEFT JOIN users u ON c.user_id = u.id
    ORDER BY c.created_at DESC
    LIMIT :limit OFFSET :offset
");
$stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
$stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
$stmt->execute();
$conversations = $stmt->fetchAll();

// Single conversation view
$single_conversation = null;
$conversation_messages = [];
if (isset($_GET['id'])) {
    $conversation_id = (int)$_GET['id'];
    $stmt = $pdo->prepare("
        SELECT c.*, u.username 
        FROM conversations c
        LEFT JOIN users u ON c.user_id = u.id
        WHERE c.id = :id
    ");
    $stmt->execute([':id' => $conversation_id]);
    $single_conversation = $stmt->fetch();
    
    if ($single_conversation) {
        $stmt = $pdo->prepare("
            SELECT * FROM messages 
            WHERE conversation_id = :id 
            ORDER BY created_at ASC
        ");
        $stmt->execute([':id' => $conversation_id]);
        $conversation_messages = $stmt->fetchAll();
    }
}
?>
<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Konuşmalar</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; background: #f8f9fa; color: #333; }
        
        .admin-header { background: #2c3e50; color: white; padding: 20px 30px; }
        .admin-header h1 { margin-bottom: 10px; }
        .admin-nav { background: #34495e; padding: 15px 30px; }
        .admin-nav a { color: white; text-decoration: none; margin-right: 20px; }
        
        .container { max-width: 1200px; margin: 30px auto; padding: 0 20px; }
        
        /* Table */
        .table-container { background: white; border-radius: 10px; overflow: hidden; box-shadow: 0 5px 15px rgba(0,0,0,0.05); }
        table { width: 100%; border-collapse: collapse; }
        th { background: #f8f9fa; padding: 15px; text-align: left; border-bottom: 2px solid #eee; color: #7f8c8d; }
        td { padding: 12px 15px; border-bottom: 1px solid #eee; }
        tr:hover { background: #f8f9fa; }
        
        /* Badges */
        .badge { display: inline-block; padding: 4px 10px; border-radius: 20px; font-size: 12px; font-weight: 600; }
        .badge-primary { background: #3498db; color: white; }
        .badge-success { background: #2ecc71; color: white; }
        
        /* Conversation Detail */
        .conversation-detail { background: white; border-radius: 10px; padding: 25px; margin-bottom: 30px; box-shadow: 0 5px 15px rgba(0,0,0,0.05); }
        .message { border-left: 4px solid #3498db; padding: 15px; margin: 15px 0; background: #f8f9fa; border-radius: 0 8px 8px 0; }
        .message.user { border-left-color: #2ecc71; }
        .message.assistant { border-left-color: #9b59b6; }
        
        /* Pagination */
        .pagination { margin-top: 30px; text-align: center; }
        .pagination a { display: inline-block; padding: 8px 15px; margin: 0 5px; background: white; border: 1px solid #ddd; border-radius: 5px; text-decoration: none; color: #3498db; }
        .pagination a.active { background: #3498db; color: white; border-color: #3498db; }
        
        /* Back button */
        .back-btn { display: inline-block; margin-bottom: 20px; padding: 10px 20px; background: #7f8c8d; color: white; text-decoration: none; border-radius: 5px; }
    </style>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>
<body>
    <div class="admin-header">
        <h1><i class="fas fa-comments"></i> Konuşmalar</h1>
        <p>Toplam <?php echo $total; ?> konuşma</p>
    </div>
    
    <div class="admin-nav">
        <a href="dashboard.php"><i class="fas fa-tachometer-alt"></i> Dashboard</a>
        <a href="users.php"><i class="fas fa-users"></i> Kullanıcılar</a>
        <a href="conversations.php" style="color: #3498db;"><i class="fas fa-comments"></i> Konuşmalar</a>
        <a href="logout.php"><i class="fas fa-sign-out-alt"></i> Çıkış</a>
    </div>
    
    <div class="container">
        <?php if ($single_conversation): ?>
            <!-- Single Conversation View -->
            <a href="conversations.php" class="back-btn"><i class="fas fa-arrow-left"></i> Tüm Konuşmalara Dön</a>
            
            <div class="conversation-detail">
                <h2><?php echo htmlspecialchars($single_conversation['title'] ?? 'Başlıksız Konuşma'); ?></h2>
                <p><strong>ID:</strong> #<?php echo $single_conversation['id']; ?></p>
                <p><strong>Kullanıcı:</strong> <?php echo htmlspecialchars($single_conversation['username'] ?? 'Anonim'); ?></p>
                <p><strong>Oluşturulma:</strong> <?php echo date('d.m.Y H:i:s', strtotime($single_conversation['created_at'])); ?></p>
                <p><strong>Toplam Mesaj:</strong> <span class="badge badge-success"><?php echo count($conversation_messages); ?></span></p>
                
                <hr style="margin: 25px 0;">
                
                <h3><i class="fas fa-envelope"></i> Mesajlar</h3>
                
                <?php if (empty($conversation_messages)): ?>
                    <p style="text-align: center; color: #7f8c8d; padding: 30px;">Bu konuşmada henüz mesaj yok.</p>
                <?php else: ?>
                    <?php foreach ($conversation_messages as $message): ?>
                        <div class="message <?php echo $message['role'] ?? 'user'; ?>">
                            <strong><?php echo ucfirst($message['role'] ?? 'user'); ?>:</strong>
                            <p style="margin-top: 8px;"><?php echo nl2br(htmlspecialchars($message['content'] ?? '')); ?></p>
                            <small style="color: #7f8c8d; margin-top: 10px; display: block;">
                                <i class="far fa-clock"></i> <?php echo date('H:i:s', strtotime($message['created_at'])); ?>
                            </small>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
            
        <?php else: ?>
            <!-- Conversations List -->
            <div class="table-container">
                <table>
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Başlık</th>
                            <th>Kullanıcı</th>
                            <th>Mesajlar</th>
                            <th>Oluşturulma</th>
                            <th>İşlemler</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($conversations)): ?>
                            <tr>
                                <td colspan="6" style="text-align: center; padding: 40px; color: #7f8c8d;">
                                    <i class="fas fa-inbox" style="font-size: 48px; margin-bottom: 15px; opacity: 0.5;"></i>
                                    <p>Henüz konuşma kaydı bulunmamaktadır.</p>
                                </td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($conversations as $conv): ?>
                            <tr>
                                <td>#<?php echo $conv['id']; ?></td>
                                <td><?php echo htmlspecialchars($conv['title'] ?? 'Başlıksız'); ?></td>
                                <td><?php echo htmlspecialchars($conv['username'] ?? 'Anonim'); ?></td>
                                <td><span class="badge badge-primary"><?php echo $conv['message_count']; ?></span></td>
                                <td><?php echo date('d.m.Y H:i', strtotime($conv['created_at'])); ?></td>
                                <td>
                                    <a href="conversations.php?id=<?php echo $conv['id']; ?>" 
                                       style="color: #3498db; text-decoration: none;">
                                        <i class="fas fa-eye"></i> Görüntüle
                                    </a>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
            
            <!-- Pagination -->
            <?php if ($total_pages > 1): ?>
            <div class="pagination">
                <?php if ($page > 1): ?>
                    <a href="conversations.php?page=<?php echo $page - 1; ?>"><i class="fas fa-chevron-left"></i></a>
                <?php endif; ?>
                
                <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                    <?php if ($i == $page): ?>
                        <a href="conversations.php?page=<?php echo $i; ?>" class="active"><?php echo $i; ?></a>
                    <?php elseif ($i == 1 || $i == $total_pages || ($i >= $page - 2 && $i <= $page + 2)): ?>
                        <a href="conversations.php?page=<?php echo $i; ?>"><?php echo $i; ?></a>
                    <?php elseif ($i == $page - 3 || $i == $page + 3): ?>
                        <span>...</span>
                    <?php endif; ?>
                <?php endfor; ?>
                
                <?php if ($page < $total_pages): ?>
                    <a href="conversations.php?page=<?php echo $page + 1; ?>"><i class="fas fa-chevron-right"></i></a>
                <?php endif; ?>
            </div>
            <?php endif; ?>
            
            <div style="margin-top: 30px; text-align: center; color: #7f8c8d;">
                <p>Toplam <?php echo $total; ?> konuşma, <?php echo $total_pages; ?> sayfa</p>
                <p>Sayfa <?php echo $page; ?> / <?php echo $total_pages; ?></p>
            </div>
        <?php endif; ?>
    </div>
    
    <script>
        // Table row click
        document.querySelectorAll('table tbody tr').forEach(row => {
            if (!row.querySelector('td:last-child a')) return;
            row.style.cursor = 'pointer';
            row.addEventListener('click', function(e) {
                if (e.target.tagName === 'A' || e.target.closest('a')) return;
                const link = this.querySelector('td:last-child a');
                if (link) link.click();
            });
        });
        
        // Auto-refresh every 5 minutes
        setTimeout(() => {
            if (!window.location.href.includes('id=')) {
                window.location.reload();
            }
        }, 300000);
    </script>
</body>
</html>