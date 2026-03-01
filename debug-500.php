<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);

echo "<pre>=== DEBUG 500 ERROR ===\n";

// 1. Check vendor
echo "1. Vendor check:\n";
echo "   vendor/autoload.php exists: " . (file_exists(__DIR__ . '/vendor/autoload.php') ? 'YES' : 'NO') . "\n";

// 2. Try to load autoload
if (file_exists(__DIR__ . '/vendor/autoload.php')) {
    echo "2. Loading autoload...\n";
    require_once __DIR__ . '/vendor/autoload.php';
    echo "   Autoload loaded\n";
    
    // 3. Check TokenManager
    echo "3. Checking TokenManager class...\n";
    if (class_exists('App\Services\TokenManager')) {
        echo "   TokenManager class exists\n";
        
        try {
            $tm = new App\Services\TokenManager();
            echo "   TokenManager instance created\n";
            
            // Test quickAnalyze
            $test = $tm->quickAnalyze("Test");
            echo "   quickAnalyze test: " . print_r($test, true) . "\n";
        } catch (Exception $e) {
            echo "   ERROR creating TokenManager: " . $e->getMessage() . "\n";
            echo "   TRACE: " . $e->getTraceAsString() . "\n";
        }
    } else {
        echo "   ERROR: TokenManager class NOT FOUND!\n";
        
        // Check autoload mapping
        echo "4. Checking autoload mapping...\n";
        $autoload = require __DIR__ . '/vendor/composer/autoload_psr4.php';
        echo "   App\Services mapping: " . print_r($autoload['App\\Services\\'] ?? 'NOT FOUND', true) . "\n";
    }
} else {
    echo "ERROR: vendor/autoload.php not found!\n";
}

echo "=== DEBUG COMPLETE ===</pre>";
