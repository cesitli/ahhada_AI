<?php
header('Content-Type: application/json');

try {
    require_once __DIR__ . '/vendor/autoload.php';
    
    $tm = new App\Services\TokenManager();
    
    $tests = [
        'small' => "Küçük metin testi",
        'medium' => str_repeat("Orta büyüklükte AI context testi. ", 100),
        'large' => str_repeat("Çok büyük 128K context testi için uzun metin. ", 5000)
    ];
    
    $results = [];
    foreach ($tests as $name => $text) {
        $analysis = $tm->quickAnalyze($text);
        $optimized = $tm->optimizeFor128K($text, "Test mesajı");
        
        $results[$name] = [
            'size_bytes' => strlen($text),
            'analysis' => $analysis,
            'optimization_strategy' => $optimized['strategy'],
            'tokens' => $optimized['tokens'] ?? 'N/A'
        ];
    }
    
    echo json_encode([
        'success' => true,
        'system' => '128K AI Context Manager',
        'status' => 'OPERATIONAL',
        'max_tokens' => 128000,
        'tests' => $results,
        'strategies_tested' => array_unique(array_column($results, 'optimization_strategy')),
        'timestamp' => date('Y-m-d H:i:s')
    ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
    
} catch (Exception $e) {
    echo json_encode(['success' => false, 'error' => $e->getMessage()], JSON_PRETTY_PRINT);
}
