<?php
header('Content-Type: application/json');
require_once __DIR__ . '/vendor/autoload.php';

$start = microtime(true);
$memory_start = memory_get_usage();

$tm = new App\Services\TokenManager();

// 1. Küçük metin testi
$small = "Küçük metin testi";
$small_result = $tm->quickAnalyze($small);

// 2. Orta büyüklükte metin
$medium = str_repeat("Orta büyüklükte AI context testi için örnek metin. ", 1000);
$medium_result = $tm->optimizeFor128K($medium);

// 3. Büyük metin (128K sınırına yakın)
$large = str_repeat("128K token sınırını test etmek için çok büyük bir metin oluşturuyoruz. AI sistemleri geniş context window'lar ile daha iyi çalışır. ", 5000);
$large_result = $tm->optimizeFor128K($large);

$time = round((microtime(true) - $start) * 1000, 2);
$memory = round((memory_get_peak_usage() - $memory_start) / 1024 / 1024, 2);

echo json_encode([
    'success' => true,
    'performance' => [
        'execution_time_ms' => $time,
        'memory_usage_mb' => $memory,
        'timestamp' => date('Y-m-d H:i:s')
    ],
    'tests' => [
        'small_text' => $small_result,
        'medium_text' => [
            'strategy' => $medium_result['strategy'],
            'tokens' => $medium_result['tokens']
        ],
        'large_text' => [
            'strategy' => $large_result['strategy'],
            'chunks' => $large_result['chunk_count'] ?? 1,
            'compression' => $large_result['compression_rate'] ?? 'N/A'
        ]
    ],
    'system' => [
        'max_tokens' => 128000,
        'status' => 'READY',
        'version' => '1.0.0'
    ]
], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
