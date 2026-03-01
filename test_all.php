<?php
// test_all.php
error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once __DIR__ . '/vendor/autoload.php';

use App\Config\Config;
use App\Config\Database;

echo "=== PHP 8.4 Compatibility Test ===\n\n";

// 1. Test basic PHP
echo "1. PHP Version: " . PHP_VERSION . "\n";

// 2. Test Composer Autoload
echo "2. Loading autoload... OK\n";

// 3. Test Config
echo "3. Testing Config class... ";
try {
    Config::load();
    $config = Config::getAll();
    echo "OK\n";
    echo "   Database config loaded: " . (!empty($config['database']['database']) ? 'YES' : 'NO') . "\n";
    echo "   DB Host: " . ($config['database']['host'] ?? 'NOT SET') . "\n";
    echo "   DB Name: " . ($config['database']['database'] ?? 'NOT SET') . "\n";
    echo "   DB User: " . ($config['database']['username'] ?? 'NOT SET') . "\n";
    echo "   DB Pass set: " . (!empty($config['database']['password']) ? 'YES' : 'NO') . "\n";
} catch (Exception $e) {
    echo "ERROR: " . $e->getMessage() . "\n";
}

// 4. Test Database
echo "4. Testing Database connection... ";
try {
    $db = Database::getConnection();
    echo "CONNECTED\n";
    
    // Simple query
    $stmt = $db->query('SELECT 1 as test');
    $result = $stmt->fetch();
    echo "   Query test: " . ($result['test'] == 1 ? 'OK' : 'FAILED') . "\n";
    
    // Get PostgreSQL version
    $stmt = $db->query('SELECT version()');
    $version = $stmt->fetchColumn();
    echo "   PostgreSQL: " . substr($version, 0, 50) . "...\n";
} catch (Exception $e) {
    echo "ERROR: " . $e->getMessage() . "\n";
}

// 5. Test Controllers
echo "5. Testing Controllers...\n";
$controllers = ['ConversationController', 'MessageController', 'AIController', 'ContextController'];
foreach ($controllers as $controller) {
    $found = false;
    
    // Try different namespaces
    $namespaces = ['App\\controllers\\', 'App\\Controllers\\', 'App\\'];
    
    foreach ($namespaces as $namespace) {
        $className = $namespace . $controller;
        if (class_exists($className)) {
            echo "   ✅ $controller found in $namespace\n";
            $found = true;
            
            // Get file path if possible
            try {
                $reflection = new ReflectionClass($className);
                echo "       File: " . $reflection->getFileName() . "\n";
            } catch (Exception $e) {
                // Ignore reflection errors
            }
            break;
        }
    }
    
    if (!$found) {
        echo "   ❌ $controller not found\n";
        
        // Check if file exists
        $possiblePaths = [
            __DIR__ . '/src/controllers/' . $controller . '.php',
            __DIR__ . '/src/Controllers/' . $controller . '.php',
            __DIR__ . '/src/' . $controller . '.php'
        ];
        
        foreach ($possiblePaths as $path) {
            if (file_exists($path)) {
                echo "       📁 File exists at: $path\n";
                echo "       📄 Contents check:\n";
                $content = file_get_contents($path);
                if (preg_match('/namespace\s+([^\s;]+)/', $content, $matches)) {
                    echo "       📦 Namespace: " . $matches[1] . "\n";
                }
                if (preg_match('/class\s+' . $controller . '/', $content)) {
                    echo "       🏗️  Class defined\n";
                }
            }
        }
    }
}

// 6. Test Routes
echo "6. Testing Routes file... ";
if (file_exists(__DIR__ . '/routes/api.php')) {
    echo "EXISTS\n";
    
    // Check for errors in routes
    $routesContent = file_get_contents(__DIR__ . '/routes/api.php');
    
    // Look for use statements
    if (preg_match_all('/use\s+([^;]+);/', $routesContent, $matches)) {
        echo "   Use statements found:\n";
        foreach ($matches[1] as $useStatement) {
            echo "       - " . trim($useStatement) . "\n";
        }
    }
    
    // Look for Controller usage
    if (strpos($routesContent, 'ConversationController') !== false) {
        echo "   ConversationController referenced in routes\n";
    }
} else {
    echo "NOT FOUND\n";
}

echo "\n=== Test Complete ===\n";