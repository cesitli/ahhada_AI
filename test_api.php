<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once __DIR__ . '/vendor/autoload.php';

// Simulate a request
$_SERVER['REQUEST_METHOD'] = 'GET';
$_SERVER['REQUEST_URI'] = '/api/test';

echo "=== API Test ===\n\n";

// Test 1: Load routes
echo "1. Loading routes... ";
require_once __DIR__ . '/routes/api.php';
echo "OK\n";

// Test 2: Check if controllers can be instantiated
echo "2. Testing controller instantiation...\n";

$controllers = [
    'App\controllers\ConversationController',
    'App\controllers\ContextController',
    'App\Middleware\AuthMiddleware'
];

foreach ($controllers as $controller) {
    echo "   $controller: ";
    if (class_exists($controller)) {
        try {
            $reflection = new ReflectionClass($controller);
            if ($reflection->isInstantiable()) {
                echo "✅ Instantiable\n";
            } else {
                echo "⚠️  Exists but not instantiable\n";
            }
        } catch (Exception $e) {
            echo "❌ Error: " . $e->getMessage() . "\n";
        }
    } else {
        echo "❌ Not found\n";
    }
}

// Test 3: Check router configuration
echo "\n3. Checking router...\n";
if (class_exists('Bramus\Router\Router')) {
    echo "   ✅ Router class found\n";
    
    // Try to create router
    $router = new Bramus\Router\Router();
    echo "   ✅ Router instantiated\n";
} else {
    echo "   ❌ Router class not found\n";
}

echo "\n=== Test Complete ===\n";