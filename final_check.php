<?php
// /s3/final_check.php

error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "FINAL SYSTEM CHECK\n";
echo "=================\n\n";

// 1. Check essential files
$essential = [
    'composer.json',
    'vendor/autoload.php',
    '.env',
    'src/Config/Config.php',
    'src/Config/Database.php',
    'index.php'
];

foreach ($essential as $file) {
    echo (file_exists($file) ? "✅ " : "❌ ") . "$file\n";
}

// 2. Test system
echo "\nTesting system...\n";

try {
    require_once 'vendor/autoload.php';
    echo "✅ Composer autoload loaded\n";
    
    $dotenv = Dotenv\Dotenv::createImmutable(__DIR__);
    $dotenv->load();
    echo "✅ .env loaded\n";
    
    $db = App\Config\Database::getConnection();
    echo "✅ Database connected\n";
    
    // Test a query
    $stmt = $db->query("SELECT COUNT(*) as count FROM conversations");
    $result = $stmt->fetch();
    echo "✅ Conversations: " . $result['count'] . "\n";
    
    echo "\n🎉 SYSTEM IS READY!\n";
    echo "Test URLs:\n";
    echo "• https://ahhada.com/s3/index.php\n";
    echo "• https://ahhada.com/s3/index.php/health\n";
    echo "• https://ahhada.com/s3/index.php/conversations\n";
    
} catch (Exception $e) {
    echo "❌ ERROR: " . $e->getMessage() . "\n";
    echo "File: " . $e->getFile() . ":" . $e->getLine() . "\n";
}
?>