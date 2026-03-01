<?php
// /s3/working_test.php

error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "<pre>\n";
echo "WORKING TEST\n";
echo "============\n\n";

// Load composer
require_once 'vendor/autoload.php';

// Test 1: Basic classes
echo "1. Testing basic classes:\n";
$classes = [
    'App\Config\Config',
    'App\Config\Database',
    'Dotenv\Dotenv'
];

foreach ($classes as $class) {
    if (class_exists($class)) {
        echo "  ✓ $class\n";
    } else {
        echo "  ✗ $class\n";
    }
}

// Test 2: Load environment
echo "\n2. Testing environment:\n";
try {
    $dotenv = Dotenv\Dotenv::createImmutable(__DIR__);
    $dotenv->load();
    echo "  ✓ .env loaded\n";
    
    echo "  DB Host: " . ($_ENV['DB_HOST'] ?? 'NOT SET') . "\n";
    echo "  DB Database: " . ($_ENV['DB_DATABASE'] ?? 'NOT SET') . "\n";
    
} catch (Exception $e) {
    echo "  ✗ Environment error: " . $e->getMessage() . "\n";
}

// Test 3: Database connection
echo "\n3. Testing database:\n";
try {
    $db = App\Config\Database::getConnection();
    echo "  ✓ Database connected\n";
    
    // Simple query
    $stmt = $db->query("SELECT COUNT(*) as count FROM conversations");
    $result = $stmt->fetch();
    echo "  Conversations: " . $result['count'] . "\n";
    
} catch (Exception $e) {
    echo "  ✗ Database error: " . $e->getMessage() . "\n";
}

// Test 4: Check src directory structure
echo "\n4. Checking src structure:\n";
$srcDirs = ['Config', 'Models', 'Controllers', 'Services', 'Utils', 'Middleware'];
foreach ($srcDirs as $dir) {
    $path = "src/$dir";
    if (is_dir($path)) {
        $files = glob("$path/*.php");
        echo "  $dir: " . count($files) . " files\n";
    } else {
        echo "  $dir: MISSING\n";
    }
}

echo "\n✅ TEST COMPLETED\n";
echo "</pre>";