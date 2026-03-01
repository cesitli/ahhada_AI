<?php
echo "🚀 FINAL COMPOSER AUTOLOAD TEST\n";
echo str_repeat("=", 40) . "\n\n";

// 1. Autoload dosyasını yükle
if (!file_exists('vendor/autoload.php')) {
    die("❌ vendor/autoload.php BULUNAMADI!\n");
}

require_once 'vendor/autoload.php';
echo "✅ vendor/autoload.php yüklendi\n\n";

// 2. Case-insensitive class kontrolü
function checkClass($className) {
    echo "🔍 $className: ";
    
    if (class_exists($className, true)) {
        echo "✅ BULUNDU\n";
        return true;
    }
    
    echo "⚠️  BULUNAMADI - ";
    
    // Dosya yolunu bulmaya çalış
    $relativePath = str_replace(['App\\', 'Config\\'], ['src/', 'src/Config/'], $className);
    $expectedPath = str_replace('\\', '/', $relativePath) . '.php';
    
    // Farklı case kombinasyonlarını dene
    $possibilities = [
        $expectedPath,
        strtolower($expectedPath),
        dirname($expectedPath) . '/' . strtolower(basename($expectedPath)),
        str_replace(['Controllers', 'Models', 'Services', 'Utils'], 
                   ['controllers', 'models', 'services', 'utils'], $expectedPath)
    ];
    
    $found = false;
    foreach ($possibilities as $path) {
        if (file_exists($path)) {
            echo "📁 Dosya: $path (manuel yüklenebilir)\n";
            $found = true;
            break;
        }
    }
    
    if (!$found) {
        echo "📭 Dosya bulunamadı\n";
    }
    
    return false;
}

// 3. Tüm kritik sınıfları test et
echo "📦 KRİTİK SINIFLAR:\n";
$criticalClasses = [
    'App\\Controllers\\ConversationController',
    'App\\Controllers\\AIController', 
    'App\\Controllers\\ContextController',
    'App\\Models\\Conversation',
    'App\\Models\\Message',
    'App\\Services\\ContextBuilder',
    'App\\Services\\AIProviderManager',
    'App\\Utils\\TokenCalculator',
    'App\\Middleware\\AuthMiddleware',
    'Config\\Config',
    'Config\\Database'
];

$allPassed = true;
foreach ($criticalClasses as $class) {
    if (!checkClass($class)) {
        $allPassed = false;
    }
}

// 4. PSR-4 mapping kontrolü
echo "\n📊 PSR-4 MAPPING:\n";
if (function_exists('class_alias')) {
    $loader = require 'vendor/autoload.php';
    $prefixes = $loader->getPrefixesPsr4();
    
    foreach ($prefixes as $prefix => $paths) {
        if (strpos($prefix, 'App') === 0 || strpos($prefix, 'Config') === 0) {
            echo "🔧 $prefix → " . implode(', ', $paths) . "\n";
        }
    }
}

// 5. Sonuç
echo "\n" . str_repeat("=", 40) . "\n";
if ($allPassed) {
    echo "🎉 TEBRİKLER! Tüm sınıflar bulundu.\n";
    echo "🚀 128K Token çözümüne geçebiliriz.\n";
} else {
    echo "⚠️  Bazı sınıflar bulunamadı.\n";
    echo "🔧 Case sensitivity sorunu olabilir.\n";
}
