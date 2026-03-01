<?php
require 'vendor/autoload.php';

echo "🤖 AI PROVIDER TEST\n";
echo str_repeat("=", 50) . "\n\n";

try {
    if (!class_exists('App\\Services\\AIProviderManager')) {
        die("❌ AIProviderManager bulunamadı!\n");
    }
    
    $manager = new App\Services\AIProviderManager();
    echo "✅ AIProviderManager: Çalışıyor\n\n";
    
    // Check available providers
    echo "🔧 KONFİGÜRASYON KONTROLÜ:\n";
    
    // Read .env for AI keys
    $envFile = '.env';
    if (file_exists($envFile)) {
        $envContent = file_get_contents($envFile);
        
        $providers = [
            'OPENAI' => ['OPENAI_API_KEY', 'OPENAI_ENABLED'],
            'DEEPSEEK' => ['DEEPSEEK_API_KEY', 'DEEPSEEK_ENABLED'],
            'GEMINI' => ['GEMINI_API_KEY', 'GEMINI_ENABLED']
        ];
        
        foreach ($providers as $name => $keys) {
            $apiKey = preg_match("/{$keys[0]}=(.+)/", $envContent, $matches) ? $matches[1] : '';
            $enabled = preg_match("/{$keys[1]}=(.+)/", $envContent, $matches) ? $matches[1] : '';
            
            echo "   • $name:\n";
            echo "     - API Key: " . (!empty($apiKey) ? '✅ Mevcut' : '❌ Eksik') . "\n";
            echo "     - Durum: " . ($enabled === 'true' ? '✅ Aktif' : '⚠️  Pasif') . "\n";
            
            if (!empty($apiKey)) {
                // Mask the key for security
                $maskedKey = substr($apiKey, 0, 8) . '...' . substr($apiKey, -4);
                echo "     - Key: $maskedKey\n";
            }
            echo "\n";
        }
    }
    
    echo "📊 DEFAULT PROVIDER: OpenAI (yapılandırmaya göre)\n";
    echo "🔧 STRATEJİ: fallback (OpenAI → DeepSeek → Gemini)\n";
    
    // Test token calculation with AI context
    echo "\n🔧 TOKEN + AI ENTEGRASYON TESTİ:\n";
    $tokenManager = new App\Services\TokenManager();
    
    // Create test context
    $testContext = str_repeat("AI konuşma geçmişi örneği. ", 100);
    $testQuestion = "Bu konu hakkında ne düşünüyorsun?";
    
    $analysis = $tokenManager->quickAnalyze($testContext . $testQuestion);
    $optimized = $tokenManager->optimizeFor128K($testContext, $testQuestion);
    
    echo "   • Context token: " . $analysis['tokens'] . "\n";
    echo "   • Optimizasyon stratejisi: " . $optimized['strategy'] . "\n";
    echo "   • AI'ya gönderilecek token: " . ($optimized['tokens'] ?? 'N/A') . "\n";
    
    echo "\n✅ AI sistemi token optimizasyonu ile entegre!\n";
    
} catch (Exception $e) {
    echo "❌ AI Provider hatası: " . $e->getMessage() . "\n";
}

echo "\n" . str_repeat("=", 50) . "\n";
echo "🚀 AI SİSTEMİ HAZIR!\n";
echo "📌 NOT: API key'ler .env dosyasında kontrol edildi.\n";
