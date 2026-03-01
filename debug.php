<?php
// /s3/debug.php

error_reporting(E_ALL);
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/logs/php_errors.log');

header('Content-Type: text/plain; charset=utf-8');

echo "DEBUG MODE\n";
echo "==========\n\n";

// 1. Check basic PHP
echo "1. PHP Information:\n";
echo "   PHP Version: " . phpversion() . "\n";
echo "   SAPI: " . php_sapi_name() . "\n";
echo "   Memory Limit: " . ini_get('memory_limit') . "\n";
echo "   Max Execution Time: " . ini_get('max_execution_time') . "\n\n";

// 2. Check files
echo "2. File Checks:\n";

$files = [
    'vendor/autoload.php' => 'Composer autoload',
    '.env' => 'Environment file',
    'src/Config/Config.php' => 'Config class',
    'src/Config/Database.php' => 'Database class',
    'composer.json' => 'Composer config'
];

foreach ($files as $file => $desc) {
    if (file_exists($file)) {
        $size = filesize($file);
        echo "   ✅ $desc ($file) - $size bytes\n";
        
        // Check if readable
        if (!is_readable($file)) {
            echo "      ⚠️  File not readable!\n";
        }
    } else {
        echo "   ❌ $desc ($file) - MISSING\n";
    }
}

// 3. Test Composer autoload
echo "\n3. Testing Composer Autoload:\n";
try {
    require_once 'vendor/autoload.php';
    echo "   ✅ vendor/autoload.php loaded\n";
    
    // Test if Dotenv class exists
    if (class_exists('Dotenv\Dotenv')) {
        echo "   ✅ Dotenv class found\n";
    } else {
        echo "   ❌ Dotenv class NOT found\n";
    }
    
    // Test if our Config class exists
    if (class_exists('App\Config\Config')) {
        echo "   ✅ App\Config\Config class found\n";
    } else {
        echo "   ❌ App\Config\Config class NOT found\n";
        
        // Try to manually include
        if (file_exists('src/Config/Config.php')) {
            echo "   Trying to manually include Config.php...\n";
            require_once 'src/Config/Config.php';
            
            if (class_exists('App\Config\Config')) {
                echo "   ✅ Config class loaded manually\n";
            } else {
                echo "   ❌ Still not found after manual include\n";
                
                // Check the file content
                $content = file_get_contents('src/Config/Config.php');
                if (strpos($content, 'namespace App\Config') === false) {
                    echo "   ⚠️  Namespace missing in Config.php!\n";
                }
                if (strpos($content, 'class Config') === false) {
                    echo "   ⚠️  Class declaration missing!\n";
                }
            }
        }
    }
    
} catch (Exception $e) {
    echo "   ❌ ERROR loading autoload: " . $e->getMessage() . "\n";
    echo "   Trace: " . $e->getTraceAsString() . "\n";
}

// 4. Test .env loading
echo "\n4. Testing .env Loading:\n";
try {
    if (class_exists('Dotenv\Dotenv')) {
        $dotenv = Dotenv\Dotenv::createImmutable(__DIR__);
        $dotenv->load();
        echo "   ✅ .env loaded successfully\n";
        
        // Show some values (mask passwords)
        echo "   DB_HOST: " . ($_ENV['DB_HOST'] ?? 'NOT SET') . "\n";
        echo "   DB_DATABASE: " . ($_ENV['DB_DATABASE'] ?? 'NOT SET') . "\n";
        echo "   DB_USERNAME: " . ($_ENV['DB_USERNAME'] ?? 'NOT SET') . "\n";
        echo "   DB_PASS: " . (isset($_ENV['DB_PASS']) ? '***SET***' : 'NOT SET') . "\n";
    } else {
        echo "   ❌ Dotenv class not available\n";
    }
} catch (Exception $e) {
    echo "   ❌ ERROR loading .env: " . $e->getMessage() . "\n";
}

// 5. Test Database connection
echo "\n5. Testing Database Connection:\n";
try {
    if (class_exists('App\Config\Database')) {
        $db = App\Config\Database::getConnection();
        echo "   ✅ Database connected\n";
        
        // Test query
        $stmt = $db->query('SELECT 1 as test');
        $result = $stmt->fetch();
        echo "   ✅ Query test: " . ($result['test'] == 1 ? 'OK' : 'FAIL') . "\n";
        
    } else {
        echo "   ❌ Database class not found\n";
        
        // Try direct PDO
        echo "   Trying direct PDO connection...\n";
        $pdo = new PDO(
            'pgsql:host=localhost;dbname=ahhada_s1',
            'ahhada_s1',
            'SIFREN_BURAYA'  // GERÇEK ŞİFRENİ GİR
        );
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        echo "   ✅ Direct PDO connected\n";
    }
} catch (Exception $e) {
    echo "   ❌ Database ERROR: " . $e->getMessage() . "\n";
}

// 6. Check directory permissions
echo "\n6. Directory Permissions:\n";
$dirs = [
    '.' => 'Current directory',
    'logs' => 'Logs directory',
    'src' => 'Source directory',
    'vendor' => 'Vendor directory'
];

foreach ($dirs as $dir => $desc) {
    if (is_dir($dir)) {
        $perms = substr(sprintf('%o', fileperms($dir)), -4);
        $readable = is_readable($dir) ? 'R' : '-';
        $writable = is_writable($dir) ? 'W' : '-';
        $executable = is_executable($dir) ? 'X' : '-';
        
        echo "   $desc ($dir): $perms [$readable$writable$executable]\n";
    } else {
        echo "   $desc ($dir): NOT A DIRECTORY\n";
    }
}

// 7. Check last PHP errors
echo "\n7. Recent PHP Errors:\n";
$errorLog = __DIR__ . '/logs/php_errors.log';
if (file_exists($errorLog)) {
    $errors = file($errorLog, FILE_IGNORE_NEW_LINES);
    $recent = array_slice($errors, -10); // Last 10 errors
    foreach ($recent as $error) {
        echo "   " . $error . "\n";
    }
} else {
    echo "   No error log file found\n";
}

echo "\n==========\n";
echo "DEBUG COMPLETE\n";
?>