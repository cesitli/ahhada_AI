<?php
// admin/dashboard.php - GELİŞMİŞ VERSİYON
require_once __DIR__ . '/../vendor/autoload.php';

$dotenv = Dotenv\Dotenv::createImmutable(__DIR__ . '/..');
$dotenv->safeLoad();

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!isset($_SESSION['admin_logged_in'])) {
    header('Location: login.php');
    exit;
}

// Database connection
$db_host = $_ENV['DB_HOST'] ?? 'localhost';
$db_port = $_ENV['DB_PORT'] ?? '5432';
$db_name = $_ENV['DB_NAME'] ?? $_ENV['DB_DATABASE'] ?? 'ahhada_s1';
$db_user = $_ENV['DB_USERNAME'] ?? $_ENV['DB_USER'] ?? 'ahhada_s1';
$db_pass = $_ENV['DB_PASS'] ?? '';

$stats = [];
$recent_data = [];
$error = null;

try {
    $dsn = "pgsql:host=$db_host;port=$db_port;dbname=$db_name";
    $pdo = new PDO($dsn, $db_user, $db_pass, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
    ]);
    
    // Temel istatistikler
    $stats['users'] = $pdo->query("SELECT COUNT(*) FROM users")->fetchColumn();
    $stats['conversations'] = $pdo->query("SELECT COUNT(*) FROM conversations")->fetchColumn();
    $stats['messages'] = $pdo->query("SELECT COUNT(*) FROM messages")->fetchColumn();
    
    // Son 24 saatteki aktivite
    $stats['active_today'] = $pdo->query("SELECT COUNT(*) FROM conversations WHERE created_at > NOW() - INTERVAL '24 hours'")->fetchColumn();
    
    // Database boyutu
    $db_size = $pdo->query("SELECT pg_database_size('$db_name')")->fetchColumn();
    $stats['db_size_mb'] = round($db_size / 1024 / 1024, 2);
    
    // Son konuşmalar
    $recent_conversations = $pdo->query("
        SELECT c.id, c.title, u.username, c.created_at, 
               (SELECT COUNT(*) FROM messages m WHERE m.conversation_id = c.id) as message_count
        FROM conversations c
        LEFT JOIN users u ON c.user_id = u.id
        ORDER BY c.created_at DESC
        LIMIT 10
    ")->fetchAll();
    
    // Son kullanıcılar
    $recent_users = $pdo->query("
        SELECT id, username, email, created_at
        FROM users
        ORDER BY created_at DESC
        LIMIT 10
    ")->fetchAll();
    
} catch (Exception $e) {
    $error = $e->getMessage();
}
?>
<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; background: #f8f9fa; color: #333; }
        .admin-container { display: flex; min-height: 100vh; }
        
        /* Sidebar */
        .sidebar { width: 250px; background: #2c3e50; color: white; }
        .sidebar-header { padding: 25px 20px; background: #1a252f; }
        .sidebar-header h2 { font-size: 20px; }
        .sidebar-header p { font-size: 14px; opacity: 0.8; margin-top: 5px; }
        .sidebar-nav { padding: 20px 0; }
        .sidebar-nav a { display: block; padding: 15px 25px; color: #ecf0f1; text-decoration: none; border-left: 4px solid transparent; }
        .sidebar-nav a:hover, .sidebar-nav a.active { background: #34495e; border-left-color: #3498db; }
        .sidebar-nav i { width: 25px; text-align: center; margin-right: 10px; }
        
        /* Main Content */
        .main-content { flex: 1; padding: 30px; }
        .header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 40px; }
        .header h1 { color: #2c3e50; font-size: 28px; }
        .logout-btn { background: #e74c3c; color: white; padding: 10px 20px; border-radius: 5px; text-decoration: none; }
        
        /* Stats Grid */
        .stats-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); gap: 25px; margin-bottom: 40px; }
        .stat-card { background: white; padding: 25px; border-radius: 10px; box-shadow: 0 5px 15px rgba(0,0,0,0.05); border-top: 4px solid #3498db; }
        .stat-card h3 { font-size: 16px; color: #7f8c8d; margin-bottom: 15px; text-transform: uppercase; }
        .stat-value { font-size: 36px; font-weight: bold; color: #2c3e50; margin-bottom: 10px; }
        .stat-label { color: #95a5a6; font-size: 14px; }
        
        /* Tables */
        .card { background: white; border-radius: 10px; padding: 25px; margin-bottom: 30px; box-shadow: 0 5px 15px rgba(0,0,0,0.05); }
        .card-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px; }
        .card-title { font-size: 18px; font-weight: 600; color: #2c3e50; }
        table { width: 100%; border-collapse: collapse; }
        th { text-align: left; padding: 15px; background: #f8f9fa; color: #7f8c8d; font-weight: 600; border-bottom: 2px solid #eee; }
        td { padding: 12px 15px; border-bottom: 1px solid #eee; }
        tr:hover { background: #f8f9fa; }
        
        /* Badges */
        .badge { display: inline-block; padding: 4px 10px; border-radius: 20px; font-size: 12px; font-weight: 600; }
        .badge-primary { background: #3498db; color: white; }
        .badge-success { background: #2ecc71; color: white; }
        
        /* Responsive */
        @media (max-width: 768px) {
            .admin-container { flex-direction: column; }
            .sidebar { width: 100%; }
            .stats-grid { grid-template-columns: 1fr; }
        }
    </style>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>
<body>
    <div class="admin-container">
        <!-- Sidebar -->
        <aside class="sidebar">
            <div class="sidebar-header">
                <h2><i class="fas fa-robot"></i> AI Admin</h2>
                <p><?php echo htmlspecialchars($_SESSION['admin_username']); ?></p>
            </div>
            <nav class="sidebar-nav">
                <a href="dashboard.php" class="active"><i class="fas fa-tachometer-alt"></i> Dashboard</a>
                <a href="users.php"><i class="fas fa-users"></i> Kullanıcılar</a>
                <a href="conversations.php"><i class="fas fa-comments"></i> Konuşmalar</a>
                <a href="messages.php"><i class="fas fa-envelope"></i> Mesajlar</a>
                <a href="embeddings.php"><i class="fas fa-brain"></i> Embeddings</a>
                <a href="system.php"><i class="fas fa-cogs"></i> Sistem</a>
                <a href="/s3/"><i class="fas fa-home"></i> Ana Sayfa</a>
                <a href="logout.php" style="color: #e74c3c;"><i class="fas fa-sign-out-alt"></i> Çıkış</a>
            </nav>
        </aside>
        
        <!-- Main Content -->
        <main class="main-content">
            <div class="header">
                <h1><i class="fas fa-tachometer-alt"></i> Dashboard</h1>
                <a href="logout.php" class="logout-btn"><i class="fas fa-sign-out-alt"></i> Çıkış Yap</a>
            </div>
            
            <?php if ($error): ?>
                <div class="card" style="border-left-color: #e74c3c;">
                    <h3 style="color: #e74c3c;"><i class="fas fa-exclamation-triangle"></i> Hata</h3>
                    <p><?php echo htmlspecialchars($error); ?></p>
                </div>
            <?php endif; ?>
            
            <!-- Stats Grid -->
            <div class="stats-grid">
                <div class="stat-card">
                    <h3><i class="fas fa-users"></i> Kullanıcılar</h3>
                    <div class="stat-value"><?php echo $stats['users'] ?? 0; ?></div>
                    <div class="stat-label">Toplam kayıtlı kullanıcı</div>
                </div>
                
                <div class="stat-card">
                    <h3><i class="fas fa-comments"></i> Konuşmalar</h3>
                    <div class="stat-value"><?php echo $stats['conversations'] ?? 0; ?></div>
                    <div class="stat-label">Toplam konuşma</div>
                </div>
                
                <div class="stat-card">
                    <h3><i class="fas fa-envelope"></i> Mesajlar</h3>
                    <div class="stat-value"><?php echo $stats['messages'] ?? 0; ?></div>
                    <div class="stat-label">Toplam mesaj</div>
                </div>
                
                <div class="stat-card">
                    <h3><i class="fas fa-bolt"></i> Bugünkü Aktiviteler</h3>
                    <div class="stat-value"><?php echo $stats['active_today'] ?? 0; ?></div>
                    <div class="stat-label">Son 24 saat</div>
                </div>
                
                <div class="stat-card">
                    <h3><i class="fas fa-database"></i> Veritabanı</h3>
                    <div class="stat-value"><?php echo $stats['db_size_mb'] ?? 0; ?> MB</div>
                    <div class="stat-label">Toplam boyut</div>
                </div>
                
                <div class="stat-card">
                    <h3><i class="fas fa-table"></i> Tablolar</h3>
                    <div class="stat-value">23</div>
                    <div class="stat-label">Toplam tablo sayısı</div>
                </div>
            </div>
            
            <!-- Recent Conversations -->
            <div class="card">
                <div class="card-header">
                    <div class="card-title"><i class="fas fa-history"></i> Son Konuşmalar</div>
                    <a href="conversations.php" class="badge badge-primary">Tümünü Gör</a>
                </div>
                <table>
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Başlık</th>
                            <th>Kullanıcı</th>
                            <th>Mesaj Sayısı</th>
                            <th>Tarih</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($recent_conversations as $conv): ?>
                        <tr>
                            <td>#<?php echo $conv['id']; ?></td>
                            <td><?php echo htmlspecialchars($conv['title'] ?? 'Başlıksız'); ?></td>
                            <td><?php echo htmlspecialchars($conv['username'] ?? 'Anonim'); ?></td>
                            <td><span class="badge badge-success"><?php echo $conv['message_count']; ?></span></td>
                            <td><?php echo date('d.m.Y H:i', strtotime($conv['created_at'])); ?></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            
            <!-- Recent Users -->
            <div class="card">
                <div class="card-header">
                    <div class="card-title"><i class="fas fa-user-plus"></i> Yeni Kullanıcılar</div>
                    <a href="users.php" class="badge badge-primary">Tümünü Gör</a>
                </div>
                <table>
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Kullanıcı Adı</th>
                            <th>E-posta</th>
                            <th>Kayıt Tarihi</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($recent_users as $user): ?>
                        <tr>
                            <td>#<?php echo $user['id']; ?></td>
                            <td><?php echo htmlspecialchars($user['username']); ?></td>
                            <td><?php echo htmlspecialchars($user['email'] ?? 'N/A'); ?></td>
                            <td><?php echo date('d.m.Y', strtotime($user['created_at'])); ?></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            
            <!-- System Info -->
            <div class="card">
                <div class="card-header">
                    <div class="card-title"><i class="fas fa-info-circle"></i> Sistem Bilgileri</div>
                </div>
                <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 20px;">
                    <div>
                        <h4>PostgreSQL</h4>
                        <p>Host: <?php echo $db_host; ?></p>
                        <p>Database: <?php echo $db_name; ?></p>
                        <p>User: <?php echo $db_user; ?></p>
                    </div>
                    <div>
                        <h4>PHP</h4>
                        <p>Version: <?php echo phpversion(); ?></p>
                        <p>Memory Limit: <?php echo ini_get('memory_limit'); ?></p>
                        <p>Max Execution: <?php echo ini_get('max_execution_time'); ?>s</p>
                    </div>
                    <div>
                        <h4>Session</h4>
                        <p>ID: <?php echo substr(session_id(), 0, 10); ?>...</p>
                        <p>Giriş: <?php echo date('H:i:s', $_SESSION['login_time'] ?? time()); ?></p>
                        <p>Server: <?php echo $_SERVER['SERVER_SOFTWARE'] ?? 'Unknown'; ?></p>
                    </div>
                </div>
            </div>
        </main>
    </div>
    
    <script>
        // Auto-refresh every 2 minutes
        setTimeout(() => {
            window.location.reload();
        }, 120000);
        
        // Table row clicks
        document.querySelectorAll('table tbody tr').forEach(row => {
            row.style.cursor = 'pointer';
            row.addEventListener('click', function() {
                const id = this.querySelector('td:first-child').textContent.replace('#', '');
                window.location.href = 'conversations.php?id=' + id;
            });
        });
    </script>
</body>
</html>