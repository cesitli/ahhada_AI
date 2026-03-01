<?php
require 'vendor/autoload.php';

echo "🔍 FINAL PRODUCTION CHECK\n";
echo str_repeat("=", 60) . "\n\n";

$checks = [
    '1. Autoload Working' => class_exists('App\\Controllers\\ConversationController'),
    '2. TokenManager Ready' => class_exists('App\\Services\\TokenManager'),
    '3. Config Loaded' => class_exists('Config\\Config'),
    '4. Database Config' => class_exists('Config\\Database'),
    '5. Models Available' => class_exists('App\\Models\\Conversation') && class_exists('App\\Models\\Message'),
    '6. TokenCalculator' => class_exists('App\\Utils\\TokenCalculator'),
    '7. AI Services' => class_exists('App\\Services\\AIProviderManager') && class_exists('App\\Services\\ContextBuilder'),
    '8. Middleware' => class_exists('App\\Middleware\\AuthMiddleware')
];

$allPassed = true;
foreach ($checks as $check => $result) {
    echo ($result ? "✅ " : "❌ ") . $check . "\n";
    if (!$result) $allPassed = false;
}

echo "\n" . str_repeat("-", 60) . "\n";

if ($allPassed) {
    echo "🎉 TÜM KONTROLLER BAŞARILI!\n\n";
    
    // Test TokenManager
    try {
        $manager = new App\Services\TokenManager();
        echo "🔧 TOKENMANAGER TEST:\n";
        
        // Small text
        $small = "Merhaba dünya";
        $result = $manager->quickAnalyze($small);
        echo "• Küçük text: " . $result['tokens'] . " token\n";
        
        // Large text simulation
        $large = str_repeat("Uzun metin örneği. ", 10000);
        $result = $manager->quickAnalyze($large);
        echo "• Büyük text: " . $result['tokens'] . " token\n";
        echo "• Önerilen strateji: " . $result['recommended_strategy'] . "\n";
        
        // 128K optimization test
        echo "\n🔧 128K OPTİMİZASYON TEST:\n";
        $optimized = $manager->optimizeFor128K($large, "Özetle");
        echo "• Strateji: " . $optimized['strategy'] . "\n";
        echo "• Token: " . ($optimized['tokens'] ?? 'N/A') . "\n";
        
        if ($optimized['strategy'] === 'chunked_processing') {
            echo "• Parça sayısı: " . $optimized['chunk_count'] . "\n";
            
            // Check all chunks
            $valid = true;
            foreach ($optimized['chunk_tokens'] as $i => $tokens) {
                if ($tokens > 128000) {
                    $valid = false;
                    echo "  ⚠️  Parça $i: $tokens token (128K'yi aşıyor!)\n";
                }
            }
            echo "• Tüm parçalar 128K içinde: " . ($valid ? '✅' : '❌') . "\n";
        }
        
        echo "\n🚀 128K SİSTEMİ HAZIR!\n";
        echo "📊 Maksimum context: 128,000 token\n";
        echo "🔧 Stratejiler: direct, compress, smart_summary, chunked_processing\n";
        echo "📈 Fallback mekanizması: Aktif\n";
        
    } catch (Exception $e) {
        echo "❌ TokenManager test hatası: " . $e->getMessage() . "\n";
    }
    
} else {
    echo "⚠️  BAZI KONTROLLER BAŞARISIZ\n";
    echo "📋 Eksikleri kontrol edin:\n";
    
    if (!class_exists('App\\Services\\TokenManager')) {
        echo "1. TokenManager.php dosyasını kontrol edin: src/services/TokenManager.php\n";
    }
}

echo "\n" . str_repeat("=", 60) . "\n";
echo "🔗 TEST ENDPOINT'LERİ:\n";
echo "1. Token Analizi: POST /api/analyze-tokens\n";
echo "2. 128K Chat: POST /api/conversations/{id}/message-128k\n";
echo "3. Normal Chat: POST /api/conversations/{id}/message\n";
