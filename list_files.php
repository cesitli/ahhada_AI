<?php
// simple_list.php - Basit dosya listeleme
error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "<pre>";

function listFilesRecursive($dir, $indent = '') {
    if (!is_readable($dir)) {
        echo "{$indent}[ERROR: Dizin okunamıyor: $dir]\n";
        return;
    }
    
    $items = @scandir($dir);
    if ($items === false) {
        echo "{$indent}[ERROR: scandir başarısız: $dir]\n";
        return;
    }
    
    foreach ($items as $item) {
        if ($item == '.' || $item == '..') continue;
        
        $path = $dir . '/' . $item;
        $isDir = is_dir($path);
        
        echo $indent . ($isDir ? "[DIR] " : "[FILE] ") . $item;
        
        if (!$isDir) {
            $size = @filesize($path);
            echo " (" . ($size !== false ? formatSize($size) : '?') . ")";
        }
        
        echo "\n";
        
        if ($isDir) {
            listFilesRecursive($path, $indent . '  ');
        }
    }
}

function formatSize($bytes) {
    if ($bytes == 0) return "0 B";
    $units = ['B', 'KB', 'MB', 'GB'];
    $i = floor(log($bytes, 1024));
    return round($bytes / pow(1024, $i), 2) . ' ' . $units[$i];
}

echo "📁 DOSYA LİSTESİ: " . __DIR__ . "\n";
echo str_repeat("=", 80) . "\n\n";

// Önce önemli dosya kontrolleri
echo "🔍 ÖNEMLİ DOSYA KONTROLLERİ:\n";
$importantFiles = [
    'index.php',
    '.env',
    'composer.json',
    'vendor/autoload.php',
    'src/Config/Database.php',
    'src/Config/Config.php'
];

foreach ($importantFiles as $file) {
    $path = __DIR__ . '/' . $file;
    if (file_exists($path)) {
        $size = filesize($path);
        echo "✅ $file (" . formatSize($size) . ")\n";
    } else {
        echo "❌ $file (BULUNAMADI)\n";
    }
}

echo "\n📊 DİZİN İSTATİSTİKLERİ:\n";

// Basit istatistik
$totalDirs = 0;
$totalFiles = 0;
$totalSize = 0;

function countFiles($dir, &$dirs, &$files, &$size) {
    if (!is_readable($dir)) return;
    
    $items = @scandir($dir);
    if ($items === false) return;
    
    foreach ($items as $item) {
        if ($item == '.' || $item == '..') continue;
        
        $path = $dir . '/' . $item;
        
        if (is_dir($path)) {
            $dirs++;
            countFiles($path, $dirs, $files, $size);
        } else {
            $files++;
            $fileSize = @filesize($path);
            if ($fileSize !== false) {
                $size += $fileSize;
            }
        }
    }
}

countFiles(__DIR__, $totalDirs, $totalFiles, $totalSize);

echo "Klasörler: $totalDirs\n";
echo "Dosyalar: $totalFiles\n";
echo "Toplam Boyut: " . formatSize($totalSize) . "\n";

echo "\n🌳 TAM DOSYA LİSTESİ:\n";
listFilesRecursive(__DIR__);

// PHP bilgileri
echo "\n🐘 PHP BİLGİLERİ:\n";
echo "PHP Version: " . PHP_VERSION . "\n";
echo "Memory Limit: " . ini_get('memory_limit') . "\n";
echo "Max Execution Time: " . ini_get('max_execution_time') . "s\n";

// Hata varsa göster
echo "\n⚠️ SON HATALAR:\n";
$errorLog = __DIR__ . '/logs/error.log';
if (file_exists($errorLog)) {
    $lines = file($errorLog, FILE_IGNORE_NEW_LINES);
    $recent = array_slice($lines, -10);
    foreach ($recent as $line) {
        echo "$line\n";
    }
} else {
    echo "Hata log dosyası bulunamadı: $errorLog\n";
}

echo "</pre>";
?>