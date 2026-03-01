<?php
// 1. Composer autoload'u yükle
require_once 'vendor/autoload.php';

echo "=== FINAL AUTOLOAD TEST ===\n\n";

// 2. Tüm gerekli sınıfları test et
$classes = [
    'App\Controllers\ConversationController',
    'App\Controllers\AIController',
    'App\Controllers\ContextController',
    'App\Models\Conversation',
    'App\Models\Message',
    'App\Services\ContextBuilder',
    'App\Services\AIProviderManager',
    'App\Utils\TokenCalculator',
    'App\Middleware\AuthMiddleware',
    'Config\Config',
    'Config\Database'
];

$allPassed = true;
foreach ($classes as $class) {
    if (class_exists($class)) {
        echo "✅ $class\n";
    } else {
        echo "❌ $class\n";
        $allPassed = false;
        
        // Dosya yolunu kontrol et
        $path = str_replace(['App\\', 'Config\\'], ['src/', 'src/Config/'], $class);
        $path = str_replace('\\', '/', $path) . '.php';
        echo "   📍 Beklenen dosya: $path\n";
        echo "   📦 Dosya var mı: " . (file_exists($path) ? 'EVET' : 'HAYIR') . "\n";
    }
}

echo "\n" . ($allPassed ? "🎉 TÜM SINIFLAR BULUNDU!" : "⚠ BAZI SINIFLAR BULUNAMADI") . "\n";

// 3. 128K token testine hazırlık
if (class_exists('App\Utils\TokenCalculator')) {
    echo "\n=== 128K TOKEN TESTİ HAZIR ===\n";
    
    // TokenCalculator'ı test et
    $calculator = new App\Utils\TokenCalculator();
    $testText = "Merhaba dünya, bu bir test mesajıdır.";
    $tokens = $calculator->estimate($testText);
    
    echo "Test mesajı: '$testText'\n";
    echo "Tahmini token: $tokens\n";
    
    if ($tokens > 0) {
        echo "✅ TokenCalculator çalışıyor!\n";
        
        // Büyük context testi
        $largeText = str_repeat("Bu uzun bir metin parçasıdır. ", 10000);
        $largeTokens = $calculator->estimate($largeText);
        echo "Büyük metin (~" . round(strlen($largeText)/1024) . "KB): $largeTokens token\n";
        echo "128K sınırına " . round(($largeTokens/128000)*100, 1) . "% yakın\n";
    }
}
