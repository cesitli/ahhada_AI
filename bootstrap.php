<?php
// ============================================
// AI CONTEXT SYSTEM - BOOTSTRAP
// ============================================

// Load Composer autoload
require_once __DIR__ . '/vendor/autoload.php';

// Custom autoloader for our project structure
spl_autoload_register(function ($className) {
    // Map namespaces to directories
    $namespaceMap = [
        'App\\Controllers\\' => __DIR__ . '/src/controllers/',
        'App\\Models\\' => __DIR__ . '/src/models/',
        'App\\Services\\' => __DIR__ . '/src/services/',
        'App\\Middleware\\' => __DIR__ . '/src/Middleware/',
        'App\\Utils\\' => __DIR__ . '/src/utils/',
        'Config\\' => __DIR__ . '/src/Config/',
        'App\\' => __DIR__ . '/src/',
    ];
    
    foreach ($namespaceMap as $prefix => $baseDir) {
        $len = strlen($prefix);
        if (strncmp($prefix, $className, $len) === 0) {
            $relativeClass = substr($className, $len);
            $file = $baseDir . str_replace('\\', '/', $relativeClass) . '.php';
            
            if (file_exists($file)) {
                require $file;
                return;
            }
        }
    }
});

// Load environment variables
try {
    $dotenv = Dotenv\Dotenv::createImmutable(__DIR__);
    $dotenv->safeLoad();
    
    // Validate required environment variables
    $dotenv->required([
        'DB_CONNECTION',
        'DB_HOST', 
        'DB_DATABASE',
        'DB_USERNAME',
        'DB_PASS'
    ]);
} catch (Exception $e) {
    die('Environment configuration error: ' . $e->getMessage());
}

// Set error reporting based on APP_DEBUG
if (isset($_ENV['APP_DEBUG']) && $_ENV['APP_DEBUG'] === 'true') {
    error_reporting(E_ALL);
    ini_set('display_errors', '1');
} else {
    error_reporting(0);
    ini_set('display_errors', '0');
}

// Set timezone
date_default_timezone_set($_ENV['TIMEZONE'] ?? 'Europe/Istanbul');

// Initialize database connection
try {
    $capsule = new Illuminate\Database\Capsule\Manager;
    
    $capsule->addConnection([
        'driver' => $_ENV['DB_CONNECTION'] ?? 'pgsql',
        'host' => $_ENV['DB_HOST'] ?? 'localhost',
        'port' => $_ENV['DB_PORT'] ?? '5432',
        'database' => $_ENV['DB_DATABASE'] ?? 'ahhada_s1',
        'username' => $_ENV['DB_USERNAME'] ?? 'ahhada_s1',
        'password' => $_ENV['DB_PASS'] ?? 'Sinurhira42zihni',
        'charset' => 'utf8',
        'collation' => 'utf8_unicode_ci',
        'prefix' => '',
        'schema' => 'public',
        'sslmode' => 'prefer',
    ]);
    
    $capsule->setAsGlobal();
    $capsule->bootEloquent();
    
    // Test connection
    $capsule->getConnection()->getPdo();
    
} catch (Exception $e) {
    error_log('Database connection failed: ' . $e->getMessage());
    // Don't die, continue without database for health checks
}

// Return capsule for use in other files
return $capsule ?? null;
