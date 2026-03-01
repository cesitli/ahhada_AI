<?php
header('Content-Type: application/json');

try {
    require_once __DIR__ . '/vendor/autoload.php';
    
    $tm = new App\Services\TokenManager();
    
    // 115K tokenlık büyük metin (önceki testte direct kalmıştı)
    $large_text = str_repeat("128K AI Context System Test - Büyük metin. ", 25000);
    
    $analysis = $tm->quickAnalyze($large_text);
    $optimized = $tm->optimizeFor128K($large_text, "Test mesajı");
    
    echo json_encode([
        'success' => true,
        'test' => [
            'size_bytes' => strlen($large_text),
            'analysis' => $analysis,
            'optimization' => [
                'strategy' => $optimized['strategy'],
                'tokens' => $optimized['tokens'] ?? 'N/A',
                'compression' => $optimized['compression_rate'] ?? 'N/A',
                'chunks' => $optimized['chunk_count'] ?? 1
            ]
        ],
        'thresholds' => [
            'max_tokens' => 128000,
            'safety_margin' => 20000,
            'warning' => 90000,
            'compression' => 70000,
            'current_usage_percent' => round(($analysis['tokens'] / 128000) * 100, 1) . '%'
        ],
        'recommendation' => $analysis['tokens'] > 90000 ? 'USE_OPTIMIZED_STRATEGY' : 'DIRECT_OK'
    ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
    
} catch (Exception $e) {
    echo json_encode(['success' => false, 'error' => $e->getMessage()], JSON_PRETTY_PRINT);
}
