<?php
// index.php - TAM VERSİYON
// index.php - DEBUG MODE

// MAX DEBUG
error_reporting(E_ALL);
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/php_errors.log');


// Başlangıçta JSON header set et, admin için override edilecek
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

// OTURUM BAŞLAT - SADECE BİR KEZ
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

try {
    // 1. Load composer and environment ONCE
    require_once __DIR__ . '/vendor/autoload.php';
    
    $dotenv = Dotenv\Dotenv::createImmutable(__DIR__);
    $dotenv->safeLoad();
    
    // 2. Get DB config from .env
    $dbConfig = [
        'host' => $_ENV['DB_HOST'] ?? 'localhost',
        'port' => $_ENV['DB_PORT'] ?? '5432',
        'name' => $_ENV['DB_NAME'] ?? 'ahhada_s1',
        'user' => $_ENV['DB_USER'] ?? 'ahhada_s1',
        'pass' => $_ENV['DB_PASS'] ?? '',
    ];
    
    // 3. ROUTE DETERMINATION
    $requestUri = $_SERVER['REQUEST_URI'];
    
    // /s3 prefix'ini kaldır
    if (strpos($requestUri, '/s3') === 0) {
        $requestUri = substr($requestUri, 3);
    }
    
    // Query string'den route'u al
    $route = '/';
    if (isset($_GET['route'])) {
        $route = $_GET['route'];
    } elseif (isset($_GET['url'])) {
        $route = $_GET['url'];
    } else {
        // PATH_INFO'yu dene
        $pathInfo = $_SERVER['PATH_INFO'] ?? '';
        if ($pathInfo) {
            $route = $pathInfo;
        } else {
            // URL parsing
            $parsed = parse_url($requestUri);
            $path = $parsed['path'] ?? '/';
            
            // /index.php'yi kaldır
            if (strpos($path, '/index.php') === 0) {
                $path = substr($path, 10);
            }
            
            $route = $path ?: '/';
        }
    }
    
    // Route'u normalize et
    $route = '/' . ltrim($route, '/');
    $route = rtrim($route, '/');
    if ($route === '') $route = '/';
    
    $method = $_SERVER['REQUEST_METHOD'];
    
    // LOGIN FONKSİYONLARI
    function isLoggedIn() {
        return isset($_SESSION['admin_logged_in']) && $_SESSION['admin_logged_in'] === true;
    }
    
    function requireLogin() {
        if (!isLoggedIn()) {
            // Login sayfasına yönlendir
            header('Location: ?route=/admin/login');
            exit();
        }
    }
    
    // LOGIN İŞLEMİ
    if ($route === '/admin/login' && $method === 'POST') {
        // Formdan gelen veriler
        $username = $_POST['username'] ?? '';
        $password = $_POST['password'] ?? '';
        
        // .env'den kullanıcı bilgilerini oku veya default kullan
        $validUsername = $_ENV['ADMIN_USERNAME'] ?? 'admin';
        $validPassword = $_ENV['ADMIN_PASSWORD'] ?? 'admin123';
        
        if ($username === $validUsername && $password === $validPassword) {
            $_SESSION['admin_logged_in'] = true;
            $_SESSION['admin_username'] = $username;
            $_SESSION['login_time'] = time();
            
            header('Location: ?route=/admin/dashboard');
            exit();
        } else {
            $loginError = 'Kullanıcı adı veya şifre hatalı!';
        }
    }
    
    // LOGOUT İŞLEMİ
    if ($route === '/admin/logout') {
        session_destroy();
        header('Location: ?route=/admin/login');
        exit();
    }
    
    // 4. ÖNCE DOĞRUDAN ADMIN DOSYALARINI KONTROL ET
    // Eğer /admin/index.php gibi direkt dosya varsa
    if ($route === '/admin' || strpos($route, '/admin/') === 0) {
        // Admin route'ları için HTML döndür
        header('Content-Type: text/html; charset=utf-8');
        
        // Admin dosya yolları
        $adminBasePath = __DIR__ . '/admin/';
        
        // '/admin' veya '/admin/' kısmını çıkar
        $adminRoute = $route === '/admin' ? '/' : substr($route, 6);
        $adminRoute = rtrim($adminRoute, '/');
        if ($adminRoute === '') $adminRoute = '/';
        
        // Önce mevcut admin dosyalarını kontrol et
        $adminFiles = [
            '/index.php',
            '/dashboard.php', 
            '/login.php',
            '/logout.php',
            '/users.php',
            '/conversations.php',
            '/embeddings.php',
            '/system.php',
            '/database.php'
        ];
        
        // Dosya var mı kontrol et
        $fileFound = false;
        foreach ($adminFiles as $file) {
            if ($adminRoute === rtrim($file, '.php') || $adminRoute === $file) {
                $filePath = $adminBasePath . ltrim($file, '/');
                if (file_exists($filePath)) {
                    $fileFound = true;
                    
                    // Login kontrolü (login sayfası hariç)
                    if ($file !== '/login.php' && $file !== '/index.php') {
                        requireLogin();
                    }
                    
                    // index.php için özel kontrol
                    if ($file === '/index.php') {
                        // Eğer zaten login olmuşsa dashboard'a yönlendir
                        if (isLoggedIn() && $adminRoute === '/') {
                            header('Location: ?route=/admin/dashboard');
                            exit();
                        }
                    }
                    
                    include $filePath;
                    exit();
                }
            }
        }
        
        // Eğer dosya bulunamazsa, fallback routing'i kullan
        if (!$fileFound) {
            switch ($adminRoute) {
                case '/login':
                    // Login sayfası - oturum açılmamışsa göster
                    if (isLoggedIn()) {
                        header('Location: ?route=/admin/dashboard');
                        exit();
                    }
                    
                    // Basit login formu
                    echo '<!DOCTYPE html>
                    <html>
                    <head>
                        <title>Admin Login</title>
                        <meta charset="utf-8">
                        <style>
                            body { font-family: Arial, sans-serif; background: #f5f5f5; display: flex; justify-content: center; align-items: center; min-height: 100vh; margin: 0; }
                            .login-container { background: white; padding: 2rem; border-radius: 10px; box-shadow: 0 0 20px rgba(0,0,0,0.1); width: 100%; max-width: 400px; }
                            h1 { text-align: center; color: #333; margin-bottom: 2rem; }
                            .form-group { margin-bottom: 1rem; }
                            label { display: block; margin-bottom: 0.5rem; color: #555; }
                            input[type="text"], input[type="password"] { width: 100%; padding: 0.75rem; border: 1px solid #ddd; border-radius: 5px; font-size: 1rem; }
                            button { width: 100%; padding: 0.75rem; background: #007bff; color: white; border: none; border-radius: 5px; font-size: 1rem; cursor: pointer; }
                            button:hover { background: #0056b3; }
                            .error { background: #ffe6e6; color: #d00; padding: 0.75rem; border-radius: 5px; margin-bottom: 1rem; text-align: center; }
                            .info { background: #e6f7ff; color: #0066cc; padding: 0.75rem; border-radius: 5px; margin-bottom: 1rem; text-align: center; }
                        </style>
                    </head>
                    <body>
                        <div class="login-container">
                            <h1>Admin Girişi</h1>';
                            
                    if (isset($loginError)) {
                        echo '<div class="error">' . htmlspecialchars($loginError) . '</div>';
                    }
                    
                    // Demo bilgileri göster
                    echo '<div class="info">Demo: Kullanıcı: <strong>admin</strong> | Şifre: <strong>admin123</strong></div>';
                    
                    echo '<form method="POST" action="?route=/admin/login">
                                <div class="form-group">
                                    <label for="username">Kullanıcı Adı:</label>
                                    <input type="text" id="username" name="username" required value="' . (isset($_POST['username']) ? htmlspecialchars($_POST['username']) : '') . '">
                                </div>
                                <div class="form-group">
                                    <label for="password">Şifre:</label>
                                    <input type="password" id="password" name="password" required>
                                </div>
                                <button type="submit">Giriş Yap</button>
                            </form>
                        </div>
                    </body>
                    </html>';
                    exit();
                    
                case '/':
                case '/dashboard':
                    requireLogin();
                    // Fallback dashboard
                    echo '<!DOCTYPE html>
                    <html>
                    <head>
                        <title>Admin Dashboard</title>
                        <style>
                            body { font-family: Arial; margin: 20px; }
                            .header { background: #333; color: white; padding: 15px; }
                            .menu { margin: 20px 0; }
                            .menu a { margin-right: 15px; padding: 10px; background: #007bff; color: white; text-decoration: none; }
                            .card { background: #f5f5f5; padding: 20px; margin: 20px 0; border-radius: 5px; }
                        </style>
                    </head>
                    <body>
                        <div class="header">
                            <h1>Admin Dashboard</h1>
                            <p>Hoş geldiniz, ' . htmlspecialchars($_SESSION['admin_username'] ?? 'Admin') . ' | 
                            <a href="?route=/admin/logout" style="color: #ffcc00;">Çıkış Yap</a></p>
                        </div>
                        <div class="menu">
                            <a href="?route=/admin/dashboard">Dashboard</a>
                            <a href="?route=/admin/users">Kullanıcılar</a>
                            <a href="?route=/admin/conversations">Konuşmalar</a>
                            <a href="?route=/admin/embeddings">Embeddings</a>
                            <a href="?route=/admin/system">Sistem</a>
                            <a href="?route=/admin/database">Veritabanı</a>
                            <a href="?route=/">API Ana Sayfa</a>
                        </div>
                        <div class="card">
                            <h2>Yönetim Paneli</h2>
                            <p>Sistemi buradan yönetebilirsiniz.</p>
                            <p>Oturum Açılma Zamanı: ' . date('Y-m-d H:i:s', $_SESSION['login_time'] ?? time()) . '</p>
                            <h3>Admin Dosyaları:</h3>
                            <ul>';
                    
                    // Admin klasöründeki dosyaları listele
                    if (is_dir($adminBasePath)) {
                        $files = scandir($adminBasePath);
                        foreach ($files as $file) {
                            if ($file !== '.' && $file !== '..') {
                                echo '<li>' . htmlspecialchars($file) . '</li>';
                            }
                        }
                    }
                    
                    echo '</ul>
                        </div>
                    </body>
                    </html>';
                    break;
                    
                case '/users':
                case '/conversations':
                case '/embeddings':
                case '/system':
                case '/database':
                    requireLogin();
                    echo '<h1>' . htmlspecialchars(ucfirst(substr($adminRoute, 1))) . ' Sayfası</h1>';
                    echo '<p>Bu sayfa henüz hazır değil. Admin klasörünüzde ' . htmlspecialchars($adminRoute . '.php') . ' dosyası bulunamadı.</p>';
                    echo '<a href="?route=/admin/dashboard">Dashboard\'a Dön</a>';
                    break;
                    
                default:
                    requireLogin();
                    http_response_code(404);
                    echo '<h1>404 - Admin Sayfası Bulunamadı</h1>';
                    echo '<p>Aranan: ' . htmlspecialchars($adminRoute) . '</p>';
            }
        }
        exit();
    }
    
    // 5. API ROUTE HANDLING - TAM VERSİYON
    $response = [];
    
    if ($route === '/') {
        // Home - API info
        $response = [
            'status' => 'success',
            'service' => 'AI Context Management API',
            'version' => '1.0.0',
            'timestamp' => date('Y-m-d H:i:s'),
            'routing_method' => 'LiteSpeed Query String',
            'endpoints' => [
                '/health' => 'GET - System health',
                '/conversations' => 'GET - List conversations',
                '/conversations/{id}' => 'GET - Get conversation',
                '/debug' => 'GET - Debug info',
                '/admin' => 'GET - Admin panel'
            ],
            'usage' => 'Add ?route=/endpoint parameter',
            'examples' => [
                'Home' => 'index.php?route=/',
                'Health' => 'index.php?route=/health',
                'Conversations' => 'index.php?route=/conversations',
                'Admin' => 'index.php?route=/admin'
            ]
        ];
        
    } elseif ($route === '/health') {
        // Health check
        $response = [
            'status' => 'healthy',
            'service' => 'AI Context Management',
            'timestamp' => date('Y-m-d H:i:s'),
            'server' => $_SERVER['SERVER_SOFTWARE'] ?? 'LiteSpeed',
            'php_version' => PHP_VERSION
        ];
        
        // Try to connect to DB for health check
        if (!empty($dbConfig['pass'])) {
            try {
                $dsn = "pgsql:host={$dbConfig['host']};port={$dbConfig['port']};dbname={$dbConfig['name']}";
                $db = new PDO($dsn, $dbConfig['user'], $dbConfig['pass'], [
                    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                ]);
                
                $stmt = $db->query('SELECT version()');
                $version = $stmt->fetch()['version'];
                
                $response['database'] = [
                    'status' => 'connected',
                    'type' => 'PostgreSQL',
                    'version' => $version
                ];
                
            } catch (Exception $e) {
                $response['database'] = [
                    'status' => 'disconnected',
                    'error' => $e->getMessage()
                ];
            }
        } else {
            $response['database'] = [
                'status' => 'not_configured',
                'message' => 'DB_PASS not set in .env'
            ];
        }
        
    } elseif ($route === '/conversations' && $method === 'GET') {
        // List conversations - WITH DB CONNECTION
        if (empty($dbConfig['pass'])) {
            http_response_code(500);
            $response = [
                'status' => 'error',
                'message' => 'Database not configured',
                'solution' => 'Set DB_PASS in .env file'
            ];
        } else {
            try {
                $dsn = "pgsql:host={$dbConfig['host']};port={$dbConfig['port']};dbname={$dbConfig['name']}";
                $db = new PDO($dsn, $dbConfig['user'], $dbConfig['pass'], [
                    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                ]);
                
                // Check if table exists
                $tableCheck = $db->query("SELECT EXISTS (SELECT FROM information_schema.tables WHERE table_schema='public' AND table_name='conversations')");
                $tableExists = $tableCheck->fetch()['exists'];
                
                if (!$tableExists) {
                    $response = [
                        'status' => 'error',
                        'message' => 'Conversations table not found',
                        'sql_suggestion' => "CREATE TABLE conversations (id SERIAL PRIMARY KEY, user_id INTEGER, title VARCHAR(255), created_at TIMESTAMP DEFAULT NOW())"
                    ];
                } else {
                    // Get conversations
                    $page = max(1, intval($_GET['page'] ?? 1));
                    $limit = min(100, max(1, intval($_GET['limit'] ?? 20)));
                    $offset = ($page - 1) * $limit;
                    
                    $stmt = $db->prepare('SELECT * FROM conversations ORDER BY created_at DESC LIMIT :limit OFFSET :offset');
                    $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
                    $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
                    $stmt->execute();
                    
                    $conversations = $stmt->fetchAll();
                    
                    // Count
                    $countStmt = $db->query('SELECT COUNT(*) as total FROM conversations');
                    $total = $countStmt->fetch()['total'];
                    
                    $response = [
                        'status' => 'success',
                        'data' => $conversations,
                        'pagination' => [
                            'page' => $page,
                            'limit' => $limit,
                            'total' => (int)$total,
                            'total_pages' => ceil($total / $limit)
                        ],
                        'database' => 'PostgreSQL connected'
                    ];
                }
                
            } catch (PDOException $e) {
                http_response_code(500);
                $response = [
                    'status' => 'error',
                    'message' => 'Database error',
                    'error' => $e->getMessage()
                ];
            }
        }
        
    } elseif (preg_match('#^/conversations/(\d+)$#', $route, $matches) && $method === 'GET') {
        // Get single conversation
        $conversationId = (int)$matches[1];
        
        if (empty($dbConfig['pass'])) {
            http_response_code(500);
            $response = ['status' => 'error', 'message' => 'Database not configured'];
        } else {
            try {
                $dsn = "pgsql:host={$dbConfig['host']};port={$dbConfig['port']};dbname={$dbConfig['name']}";
                $db = new PDO($dsn, $dbConfig['user'], $dbConfig['pass']);
                
                $stmt = $db->prepare('SELECT * FROM conversations WHERE id = :id');
                $stmt->execute([':id' => $conversationId]);
                $conversation = $stmt->fetch();
                
                if (!$conversation) {
                    http_response_code(404);
                    $response = ['status' => 'error', 'message' => 'Conversation not found'];
                } else {
                    $response = [
                        'status' => 'success',
                        'data' => $conversation
                    ];
                }
                
            } catch (PDOException $e) {
                $response = ['status' => 'error', 'message' => $e->getMessage()];
            }
        }
        
    // Ana index.php'ye ekle (conversations route'undan sonra)
    } elseif ($route === '/chat/send' && $method === 'POST') {
    // Chat mesajı gönderme
    $input = json_decode(file_get_contents('php://input'), true);
    
    $message = $input['message'] ?? '';
    $model = $input['model'] ?? 'gpt-4';
    $conversation_id = $input['conversation_id'] ?? null;
    $user_id = $input['user_id'] ?? 1; // DEFAULT numeric ID
    
    // Eğer user_id string ise, users tablosundan bul veya oluştur
    if (!is_numeric($user_id)) {
        try {
            $dsn = "pgsql:host={$dbConfig['host']};port={$dbConfig['port']};dbname={$dbConfig['name']}";
            $db = new PDO($dsn, $dbConfig['user'], $dbConfig['pass'], [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            ]);
            
            // String user_id'yi kullanıcı adı olarak kabul et ve ID'yi bul
            $stmt = $db->prepare("SELECT id FROM users WHERE username = ? LIMIT 1");
            $stmt->execute([$user_id]);
            $user = $stmt->fetch();
            
            if ($user) {
                $user_id = $user['id'];
            } else {
                // Yeni kullanıcı oluştur
                $stmt = $db->prepare("
                    INSERT INTO users (username, created_at) 
                    VALUES (?, NOW()) 
                    RETURNING id
                ");
                $stmt->execute([$user_id]);
                $user_id = $stmt->fetch()['id'];
            }
        } catch (Exception $e) {
            // Hata durumunda default user
            $user_id = 1;
        }
    }
    
    if (empty($message)) {
        http_response_code(400);
        $response = ['status' => 'error', 'message' => 'Mesaj boş olamaz'];
    } else {
        try {
            // Database'e bağlan
            $dsn = "pgsql:host={$dbConfig['host']};port={$dbConfig['port']};dbname={$dbConfig['name']}";
            $db = new PDO($dsn, $dbConfig['user'], $dbConfig['pass'], [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            ]);
            
            // Kullanıcı ID'sini integer'a çevir
            $user_id = (int)$user_id;
            
            // Yeni konuşma oluştur veya mevcut konuşmayı kullan
            if (!$conversation_id) {
                // İlk 50 karakteri başlık yap
                $title = substr($message, 0, 50) . (strlen($message) > 50 ? '...' : '');
                
                $stmt = $db->prepare("
                    INSERT INTO conversations (user_id, title, total_messages) 
                    VALUES (?, ?, 1) 
                    RETURNING id
                ");
                $stmt->execute([$user_id, $title]);
                $conversation_id = $stmt->fetch()['id'];
            }
            
            // Kullanıcı mesajını kaydet
            $stmt = $db->prepare("
                INSERT INTO messages (conversation_id, user_id, role, content) 
                VALUES (?, ?, 'user', ?)
            ");
            $stmt->execute([$conversation_id, $user_id, $message]);
            
            // AI yanıtını oluştur
            $ai_response = "Merhaba! Mesajınızı aldım: \"$message\" \n\n";
            $ai_response .= "Bu, Ahhada AI'nın demo yanıtıdır. Sistem şu anda çalışıyor!\n\n";
            $ai_response .= "Seçilen model: **$model**\n";
            $ai_response .= "Konuşma ID: #$conversation_id\n";
            $ai_response .= "Kullanıcı ID: $user_id\n\n";
            $ai_response .= "Gerçek AI yanıtı için API anahtarlarınızı .env dosyasına ekleyin.";
            
            // AI yanıtını kaydet
            $stmt = $db->prepare("
                INSERT INTO messages (conversation_id, user_id, role, content) 
                VALUES (?, ?, 'assistant', ?)
            ");
            $stmt->execute([$conversation_id, $user_id, $ai_response]);
            
            // Konuşma sayısını güncelle
            $db->exec("UPDATE conversations SET total_messages = total_messages + 2 WHERE id = $conversation_id");
            
            $response = [
                'status' => 'success',
                'response' => $ai_response,
                'conversation_id' => $conversation_id,
                'conversation_title' => $title ?? 'Yeni Sohbet'
            ];
            
        } catch (Exception $e) {
            http_response_code(500);
            $response = ['status' => 'error', 'message' => $e->getMessage()];
        }
    }
    
    echo json_encode($response, JSON_UNESCAPED_UNICODE);
}    
} catch (Throwable $e) {
    http_response_code(500);
    
    $errorResponse = [
        'status' => 'error',
        'message' => $e->getMessage(),
        'type' => get_class($e)
    ];
    
    if (($_ENV['APP_DEBUG'] ?? 'false') === 'true') {
        $errorResponse['debug'] = [
            'file' => $e->getFile(),
            'line' => $e->getLine()
        ];
    }
    
    echo json_encode($errorResponse, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
}