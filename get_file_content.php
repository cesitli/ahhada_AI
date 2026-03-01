<?php
// get_file_content.php
error_reporting(E_ALL);
ini_set('display_errors', 1);

$file = $_GET['file'] ?? '';
if (empty($file)) {
    die('Dosya belirtilmedi');
}

// Güvenlik kontrolü - sadece mevcut dizin ve alt dizinlerine izin ver
$basePath = __DIR__;
$requestedFile = realpath($basePath . '/' . $file);

if (!$requestedFile || strpos($requestedFile, $basePath) !== 0) {
    die('❌ Güvenlik hatası: Geçersiz dosya yolu');
}

if (!file_exists($requestedFile)) {
    die('❌ Dosya bulunamadı: ' . $file);
}

if (is_dir($requestedFile)) {
    die('📁 Bu bir dizin, dosya değil');
}

// MIME tipine göre içerik göster
$extension = strtolower(pathinfo($requestedFile, PATHINFO_EXTENSION));
$content = file_get_contents($requestedFile);

// Güvenlik için bazı dosya tiplerini sınırla
$allowedExtensions = ['php', 'txt', 'json', 'env', 'html', 'htm', 'css', 'js', 'md', 'sql', 'log'];
if (!in_array($extension, $allowedExtensions)) {
    die('❌ Bu dosya tipi görüntülenemez');
}

echo htmlspecialchars($content, ENT_QUOTES, 'UTF-8');
?>