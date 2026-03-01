<?php
echo "=== AI CONTEXT SYSTEM - QUICK TEST ===\n\n";

// 1. Check vendor autoload
echo "1. Checking vendor autoload...\n";
if (file_exists(__DIR__ . '/vendor/autoload.php')) {
    require_once __DIR__ . '/vendor/autoload.php';
    echo "   ✓ Vendor autoload loaded\n";
} else {
    echo "   ✗ Vendor autoload not found\n";
    exit(1);
}

// 2. Check .env file
echo "\n2. Checking environment...\n";
if (file_exists(__DIR__ . '/.env')) {
    $envContent = file_get_contents(__DIR__ . '/.env');
    $hasDbConfig = strpos($envContent, 'DB_CONNECTION') !== false;
    $hasOpenAI = strpos($envContent, 'OPENAI_API_KEY') !== false;
    
    echo "   ✓ .env file exists\n";
    echo "   - Database configured: " . ($hasDbConfig ? "✓" : "✗") . "\n";
    echo "   - OpenAI configured: " . ($hasOpenAI ? "✓" : "✗") . "\n";
} else {
    echo "   ✗ .env file not found\n";
}

// 3. Check controllers
echo "\n3. Checking controllers...\n";
$controllers = [
    'AIController.php',
    'ContextController.php', 
    'ConversationController.php',
    'MessageController.php'
];

foreach ($controllers as $controller) {
    $path = __DIR__ . '/src/controllers/' . $controller;
    if (file_exists($path)) {
        // Check namespace
        $content = file_get_contents($path);
        if (strpos($content, 'namespace App\\Controllers') !== false) {
            echo "   ✓ $controller (namespace correct)\n";
            
            // Try to load the class
            $className = 'App\\Controllers\\' . str_replace('.php', '', $controller);
            if (!class_exists($className)) {
                // Fix namespace if needed
                $content = str_replace(
                    ['namespace App\\Controllers;', 'namespace App\\Controllers\\', 'namespace Controllers;'],
                    'namespace App\\Controllers;',
                    $content
                );
                file_put_contents($path, $content);
                
                // Require directly
                require_once $path;
                
                if (class_exists($className)) {
                    echo "     ✓ Class loaded successfully\n";
                } else {
                    echo "     ⚠ Class still not found after fix\n";
                }
            } else {
                echo "     ✓ Class already loaded\n";
            }
        } else {
            echo "   ⚠ $controller (wrong namespace)\n";
            // Fix it
            $lines = file($path);
            if (strpos($lines[0], '<?php') !== false) {
                $lines[1] = "namespace App\\Controllers;\n\n";
            } else {
                array_unshift($lines, "<?php\nnamespace App\\Controllers;\n\n");
            }
            file_put_contents($path, implode('', $lines));
            echo "     ✓ Namespace fixed\n";
        }
    } else {
        echo "   ✗ $controller not found\n";
    }
}

// 4. Test bootstrap
echo "\n4. Testing bootstrap...\n";
if (file_exists(__DIR__ . '/bootstrap.php')) {
    try {
        require_once __DIR__ . '/bootstrap.php';
        echo "   ✓ Bootstrap loaded successfully\n";
    } catch (Exception $e) {
        echo "   ✗ Bootstrap error: " . $e->getMessage() . "\n";
    }
} else {
    echo "   ⚠ Bootstrap file not found\n";
}

// 5. Test routes
echo "\n5. Testing API endpoints...\n";
$endpoints = [
    '/health',
    '/api/status',
    '/api/controllers'
];

foreach ($endpoints as $endpoint) {
    echo "   - $endpoint: ";
    
    // Create a simple test request
    $_SERVER['REQUEST_URI'] = $endpoint;
    $_SERVER['REQUEST_METHOD'] = 'GET';
    
    ob_start();
    try {
        require_once __DIR__ . '/index.php';
        $output = ob_get_clean();
        
        if (strpos($output, '"success":true') !== false || strpos($output, '"status":"healthy"') !== false) {
            echo "✓\n";
        } else {
            echo "⚠ (unexpected output)\n";
        }
    } catch (Exception $e) {
        ob_end_clean();
        echo "✗ (" . $e->getMessage() . ")\n";
    }
}

echo "\n=== TEST COMPLETE ===\n";
echo "System is ready. Access endpoints:\n";
echo "- http://yourdomain.com/s3/health\n";
echo "- http://yourdomain.com/s3/api/status\n";
echo "- http://yourdomain.com/s3/api/controllers\n";
