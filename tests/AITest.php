<?php

require_once __DIR__ . '/../vendor/autoload.php';

use App\Services\AIProviderManager;
use App\Config\Config;

// Load configuration
Config::load();

echo "=== AI Provider Test ===\n\n";

$aiManager = new AIProviderManager();

// Test 1: List available providers
echo "1. Available Providers:\n";
$providers = $aiManager->getAvailableProviders();
foreach ($providers as $provider) {
    $info = $aiManager->getProviderInfo($provider);
    echo "   - $provider (Priority: {$info['priority']})\n";
}
echo "\n";

// Test 2: Test chat with each provider
echo "2. Testing Chat with Each Provider:\n";
foreach ($providers as $provider) {
    echo "   Testing $provider... ";
    
    try {
        $start = microtime(true);
        
        $result = $aiManager->chat([
            ['role' => 'system', 'content' => 'You are a helpful assistant.'],
            ['role' => 'user', 'content' => 'Hello! Please respond with "OK" only.']
        ], ['provider' => $provider, 'max_tokens' => 10]);
        
        $time = round(microtime(true) - $start, 3);
        
        echo "✓ Success ($time seconds)\n";
        echo "   Response: " . substr($result['response'], 0, 50) . "...\n";
        
    } catch (Exception $e) {
        echo "✗ Failed: " . $e->getMessage() . "\n";
    }
}
echo "\n";

// Test 3: Test embedding
echo "3. Testing Embedding:\n";
$embeddingProvider = Config::get('ai_providers.embedding_provider', 'openai');
echo "   Using $embeddingProvider for embeddings... ";

try {
    $embedding = $aiManager->getEmbedding('Test embedding text', $embeddingProvider);
    echo "✓ Success\n";
    echo "   Dimensions: " . count($embedding['embedding']) . "\n";
} catch (Exception $e) {
    echo "✗ Failed: " . $e->getMessage() . "\n";
}
echo "\n";

// Test 4: Test summarization
echo "4. Testing Summarization:\n";
$testText = "Artificial intelligence (AI) is intelligence demonstrated by machines, as opposed to natural intelligence displayed by animals including humans. Leading AI textbooks define the field as the study of intelligent agents: any system that perceives its environment and takes actions that maximize its chance of achieving its goals. Some popular accounts use the term artificial intelligence to describe machines that mimic cognitive functions that humans associate with the human mind, such as learning and problem solving.";
echo "   Text length: " . strlen($testText) . " characters\n";

try {
    $summary = $aiManager->summarize($testText, ['max_tokens' => 100]);
    echo "   Summary: " . substr($summary, 0, 100) . "...\n";
    echo "   ✓ Success\n";
} catch (Exception $e) {
    echo "   ✗ Failed: " . $e->getMessage() . "\n";
}
echo "\n";

// Test 5: Show statistics
echo "5. Current Statistics:\n";
$stats = $aiManager->getStats();
foreach ($stats as $provider => $data) {
    $successRate = $data['requests'] > 0 
        ? round(($data['success'] / $data['requests']) * 100, 2)
        : 0;
    
    echo "   $provider:\n";
    echo "     Requests: {$data['requests']}\n";
    echo "     Success: {$data['success']} ($successRate%)\n";
    echo "     Errors: {$data['errors']}\n";
    echo "     Total Tokens: {$data['total_tokens']}\n";
    echo "     Last Used: {$data['last_used']}\n";
}
echo "\n";

echo "=== Test Completed ===\n";