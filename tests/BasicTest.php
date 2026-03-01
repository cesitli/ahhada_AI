<?php
// /s3/tests/BasicTest.php

// Enable ALL errors
error_reporting(E_ALL);
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);

// Set content type
header('Content-Type: text/plain; charset=utf-8');

echo "=== Basic System Tests ===\n\n";

// 1. Check if vendor/autoload.php exists
echo "1. Checking Composer Autoload... ";
if (file_exists(__DIR__ . '/../vendor/autoload.php')) {
    require_once __DIR__ . '/../vendor/autoload.php';
    echo "✅ Loaded\n";
} else {
    echo "❌ Missing\n";
    exit(1);
}

// 2. Try to load Config
echo "2. Loading Configuration... ";
try {
    // Load .env first
    $dotenv = Dotenv\Dotenv::createImmutable(__DIR__ . '/..');
    $dotenv->load();
    echo "✅ .env loaded\n";
    
    // Try Config class
    if (class_exists('App\Config\Config')) {
        \App\Config\Config::load();
        echo "✅ Config class loaded\n";
    } else {
        echo "❌ Config class not found\n";
    }
    
} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
    echo "Trying manual config...\n";
    
    // Manual config
    $_ENV['DB_HOST'] = 'localhost';
    $_ENV['DB_DATABASE'] = 'ahhada_s1';
    $_ENV['DB_USERNAME'] = 'ahhada_s1';
    $_ENV['DB_PASSWORD'] = 'Sinurhira42zihni'; // Gerçek şifre
}

// 3. Test Database
echo "3. Testing Database Connection... ";
try {
    if (class_exists('App\Config\Database')) {
        $conn = \App\Config\Database::getConnection();
        echo "✅ Connected via Database class\n";
    } else {
        // Fallback to direct PDO
        $pdo = new PDO(
            "pgsql:host={$_ENV['DB_HOST']};dbname={$_ENV['DB_DATABASE']}",
            $_ENV['DB_USERNAME'],
            $_ENV['DB_PASSWORD']
        );
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        echo "✅ Connected via direct PDO\n";
    }
} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
}

// 4. Test simple models
echo "4. Testing Model Classes... ";
try {
    $classes = [
        'App\Models\Conversation',
        'App\Models\Message',
        'App\Models\MemorySummary',
        'App\Services\ContextBuilder'
    ];
    
    foreach ($classes as $class) {
        if (class_exists($class)) {
            echo "\n   ✅ $class exists";
        } else {
            echo "\n   ❌ $class missing";
        }
    }
    echo "\n";
    
} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
}

// 5. Check directory structure
echo "5. Checking Directory Structure... \n";
$dirs = [
    '/config',
    '/controllers',
    '/models',
    '/services',
    '/utils',
    '/routes',
    '/logs'
];

foreach ($dirs as $dir) {
    $path = __DIR__ . '/..' . $dir;
    echo "   $dir: " . (is_dir($path) ? "✅ Exists" : "❌ Missing") . "\n";
}

// 6. File permissions
echo "6. Checking File Permissions... \n";
$files = [
    '/.env' => 'readable',
    '/logs/' => 'writable',
    '/vendor/autoload.php' => 'readable'
];

foreach ($files as $file => $type) {
    $path = __DIR__ . '/..' . $file;
    if ($type === 'writable') {
        echo "   $file: " . (is_writable($path) ? "✅ Writable" : "❌ Not writable") . "\n";
    } else {
        echo "   $file: " . (is_readable($path) ? "✅ Readable" : "❌ Not readable") . "\n";
    }
}

echo "\n=== Test Completed ===\n";
?>