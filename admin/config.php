<?php
// Admin Configuration
session_start();

class AdminConfig {
    // Admin şifresi (.env'den alınacak)
    public static function getAdminPassword() {
        return $_ENV['ADMIN_PASSWORD'] ?? 'admin123'; // Default
    }
    
    // Session timeout (saat)
    public static function getSessionTimeout() {
        return 8; // 8 saat
    }
    
    // IP restriction (opsiyonel)
    public static function getAllowedIPs() {
        return ['127.0.0.1', '::1']; // Localhost only
    }
    
    // Check if user is authenticated
    public static function isAuthenticated() {
        if (!isset($_SESSION['admin_authenticated']) || 
            $_SESSION['admin_authenticated'] !== true) {
            return false;
        }
        
        // Check session timeout
        if (time() - $_SESSION['admin_login_time'] > 
            (self::getSessionTimeout() * 3600)) {
            self::logout();
            return false;
        }
        
        // Optional: IP check
        // if (!in_array($_SERVER['REMOTE_ADDR'], self::getAllowedIPs())) {
        //     self::logout();
        //     return false;
        // }
        
        return true;
    }
    
    // Login
    public static function login($password) {
        if ($password === self::getAdminPassword()) {
            $_SESSION['admin_authenticated'] = true;
            $_SESSION['admin_login_time'] = time();
            $_SESSION['admin_ip'] = $_SERVER['REMOTE_ADDR'];
            return true;
        }
        return false;
    }
    
    // Logout
    public static function logout() {
        session_destroy();
        session_start();
    }
    
    // Require authentication
    public static function requireAuth() {
        if (!self::isAuthenticated()) {
            header('Location: /s3/index.php?route=/admin/login');
            exit;
        }
    }
}
?>