<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "<pre>=== TOKEN CALCULATOR DEBUG ===\n";

require_once __DIR__ . '/vendor/autoload.php';

// Check TokenCalculator
echo "1. Checking TokenCalculator...\n";
if (class_exists('App\Utils\TokenCalculator')) {
    echo "   TokenCalculator class exists\n";
    
    try {
        $calc = new App\Utils\TokenCalculator();
        echo "   TokenCalculator instance created\n";
        
        // Test methods
        $methods = get_class_methods($calc);
        echo "   Available methods: " . implode(', ', $methods) . "\n";
        
        // Test estimate
        if (method_exists($calc, 'estimate')) {
            $result = $calc->estimate("Test metin");
            echo "   estimate('Test metin') = " . $result . "\n";
        } else {
            echo "   ERROR: estimate method not found!\n";
        }
        
    } catch (Exception $e) {
        echo "   ERROR: " . $e->getMessage() . "\n";
        echo "   TRACE: " . $e->getTraceAsString() . "\n";
    }
} else {
    echo "   ERROR: TokenCalculator class NOT FOUND!\n";
}

echo "=== DEBUG COMPLETE ===</pre>";
