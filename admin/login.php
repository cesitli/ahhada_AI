<?php
// admin/login.php - COMPOSER İLE .env YÜKLE
require_once __DIR__ . '/../vendor/autoload.php';

// Dotenv yükle
$dotenv = Dotenv\Dotenv::createImmutable(__DIR__ . '/..');
$dotenv->safeLoad();

// Session başlat
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Zaten login olmuşsa
if (isset($_SESSION['admin_logged_in']) && $_SESSION['admin_logged_in'] === true) {
    header('Location: dashboard.php');
    exit;
}

// .env'den admin bilgileri
$admin_user = $_ENV['ADMIN_USERNAME'] ?? 'admin';
$admin_pass = $_ENV['ADMIN_PASSWORD'] ?? 'admin123';

$error = null;
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = $_POST['username'] ?? '';
    $password = $_POST['password'] ?? '';
    
    if ($username === $admin_user && $password === $admin_pass) {
        $_SESSION['admin_logged_in'] = true;
        $_SESSION['admin_username'] = $username;
        header('Location: dashboard.php');
        exit;
    } else {
        $error = 'Kullanıcı adı veya şifre hatalı!';
    }
}
?>
<!DOCTYPE html>
<html>
<head>
    <title>Admin Login</title>
    <style>
        body { font-family: Arial; padding: 50px; background: #f5f5f5; }
        .login-box { max-width: 400px; margin: 0 auto; background: white; padding: 30px; border-radius: 10px; }
        input { width: 100%; padding: 10px; margin: 10px 0; }
        button { width: 100%; padding: 10px; background: #007bff; color: white; border: none; }
        .error { color: red; }
    </style>
</head>
<body>
    <div class="login-box">
        <h2>Giriş Yap</h2>
        <?php if ($error): ?>
            <div class="error"><?php echo $error; ?></div>
        <?php endif; ?>
        <form method="POST">
            <input type="text" name="username" placeholder="Kullanıcı" value="<?php echo htmlspecialchars($admin_user); ?>" required>
            <input type="password" name="password" placeholder="Şifre" value="<?php echo htmlspecialchars($admin_pass); ?>" required>
            <button type="submit">Giriş</button>
        </form>
    </div>
</body>
</html>