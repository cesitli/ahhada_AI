<?php
require 'vendor/autoload.php';

// Büyük context simülasyonu (150K token)
$largeContext = str_repeat("This is a sample message for testing 128K token limit. ", 30000);
$query = "What is the main topic?";

$tokenManager = new App\Services\TokenManager();
$result = $tokenManager->manageContext($largeContext, $query);

echo "Strategy: " . $result['strategy'] . "\n";
echo "Tokens: " . $result['tokens'] . "\n";
echo "Success: " . ($result['tokens'] <= 128000 ? 'YES' : 'NO') . "\n";