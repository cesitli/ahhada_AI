<?php
namespace App\Services;

use App\Utils\TokenCalculator;

class TokenManager {
const MAX_TOKENS = 128000;
const WARNING_THRESHOLD = 90000;      // %70 - ESKİ: 100000
const COMPRESSION_THRESHOLD = 70000;  // %55 - ESKİ: 80000  
const SAFETY_MARGIN = 20000;          // %16 - ESKİ: 5000 (DAHA GÜVENLİ!)
    
    private $calculator;
    
    public function __construct() {
        $this->calculator = new TokenCalculator();
    }
    
    /**
     * 128K token sınırına uygun hale getir
     */
    public function optimizeFor128K($context, $currentMessage = '') {
    $fullText = $context . "\n" . $currentMessage;
    $tokens = $this->calculator->countTokens($fullText);
    
    error_log("📊 Token Durumu: $tokens / " . self::MAX_TOKENS . " (" . round(($tokens/self::MAX_TOKENS)*100, 1) . "%)");
    
    // YENİ LOGIC: Daha agresif optimizasyon
    if ($tokens <= 70000) { // %55 altı
        return $this->directUse($context, $tokens);
    }
    
    if ($tokens <= 90000) { // %70 altı
        return $this->compressStrategy($context, $tokens);
    }
    
    if ($tokens <= 110000) { // %86 altı
        return $this->smartSummarize($context, $tokens, $currentMessage);
    }
    
    // %86 üstü - kesinlikle chunking
    return $this->chunkAndProcess($context, $tokens, $currentMessage);
}
    /**
     * Hızlı analiz
     */
    public function quickAnalyze($text) {
        $tokens = $this->calculator->estimate($text);
        
        return [
            'length_bytes' => strlen($text),
            'length_chars' => mb_strlen($text),
            'tokens' => $tokens,
            'within_128k' => $tokens <= self::MAX_TOKENS,
            'percent_of_limit' => round(($tokens / self::MAX_TOKENS) * 100, 1) . '%',
            'needs_attention' => $tokens > self::WARNING_THRESHOLD,
            'recommended_strategy' => $this->recommendStrategy($tokens)
        ];
    }
    
    /**
     * Strateji 1: Direkt kullan
     */
    private function directUse($context, $tokens) {
        return [
            'strategy' => 'direct',
            'content' => $context,
            'tokens' => $tokens,
            'compressed' => false,
            'summary' => false
        ];
    }
    
    /**
     * Strateji 2: Sıkıştırma
     */
    private function compressStrategy($context, $tokens) {
        $compressed = $this->compress($context);
        $newTokens = $this->calculator->estimate($compressed);
        
        return [
            'strategy' => 'compressed',
            'content' => $compressed,
            'tokens' => $newTokens,
            'compression_rate' => round(100 * ($tokens - $newTokens) / $tokens, 1) . '%',
            'original_tokens' => $tokens
        ];
    }
    
    /**
     * Strateji 3: Akıllı özetleme
     */
    private function smartSummarize($context, $tokens, $currentMessage) {
        // 1. Alakalı kısımları çıkar
        $keywords = $this->extractKeywords($currentMessage);
        $relevant = $this->extractRelevant($context, $keywords, 70000);
        
        // 2. Geri kalanı özetle
        $remaining = $this->removeRelevant($context, $relevant);
        $summary = $this->createSummary($remaining, 30000);
        
        $final = $relevant . "\n\n[Özet: " . $summary . "]";
        $finalTokens = $this->calculator->estimate($final);
        
        return [
            'strategy' => 'smart_summary',
            'content' => $final,
            'tokens' => $finalTokens,
            'relevant_parts' => $relevant,
            'summary' => $summary,
            'keywords' => $keywords
        ];
    }
    
    /**
     * Strateji 4: Parçalama ve işleme
     */
    private function chunkAndProcess($context, $tokens, $currentMessage) {
        $chunks = $this->createSmartChunks($context, 120000);
        
        return [
            'strategy' => 'chunked_processing',
            'chunks' => $chunks,
            'chunk_count' => count($chunks),
            'total_tokens' => $tokens,
            'requires_streaming' => true,
            'chunk_tokens' => array_map([$this->calculator, 'estimate'], $chunks)
        ];
    }
    
    /**
     * Basit sıkıştırma
     */
    private function compress($text) {
        // 1. Fazla boşluk
        $text = preg_replace('/\s+/', ' ', $text);
        
        // 2. Tekrarlar
        $text = preg_replace('/(\b\w+\b)(?:\s+\1)+/i', '$1', $text);
        
        // 3. Uzun boşluklar
        $text = preg_replace('/\n{3,}/', "\n\n", $text);
        
        // 4. Gereksiz noktalar
        $text = preg_replace('/([.!?]){2,}/', '$1', $text);
        
        return trim($text);
    }
    
    /**
     * Anahtar kelimeleri çıkar
     */
    private function extractKeywords($text) {
        $words = str_word_count(mb_strtolower($text), 1, 'çğıöşüÇĞİÖŞÜ');
        $stopWords = ['ve', 'veya', 'ama', 'için', 'bir', 'bu', 'şu', 'o', 'de', 'da', 'ki', 'mi', 'mı', 'mu', 'mü'];
        
        $keywords = array_diff($words, $stopWords);
        return array_unique(array_filter($keywords, function($w) {
            return strlen($w) > 2;
        }));
    }
    
    /**
     * Alakalı kısımları çıkar
     */
    private function extractRelevant($context, $keywords, $maxTokens = 70000) {
        if (empty($keywords)) {
            // İlk 50K karakter al
            return substr($context, 0, 50000);
        }
        
        $lines = explode("\n", $context);
        $relevant = [];
        $currentTokens = 0;
        
        foreach ($lines as $line) {
            if ($currentTokens >= $maxTokens) break;
            
            foreach ($keywords as $keyword) {
                if (stripos($line, $keyword) !== false) {
                    $lineTokens = $this->calculator->estimate($line);
                    if ($currentTokens + $lineTokens <= $maxTokens) {
                        $relevant[] = $line;
                        $currentTokens += $lineTokens;
                    }
                    break;
                }
            }
        }
        
        return implode("\n", $relevant);
    }
    
    /**
     * Özet oluştur
     */
    private function createSummary($text, $maxTokens = 30000) {
        // Basit özet: ilk 500 karakter + son 500 karakter
        if ($this->calculator->estimate($text) <= $maxTokens) {
            return $text;
        }
        
        $firstPart = substr($text, 0, 1500);
        $lastPart = substr($text, -1500);
        
        return $firstPart . "\n...\n" . $lastPart;
    }
    
    /**
     * Akıllı parçalar oluştur
     */
    private function createSmartChunks($text, $chunkSize = 120000) {
        $paragraphs = explode("\n\n", $text);
        $chunks = [];
        $currentChunk = '';
        $currentTokens = 0;
        
        foreach ($paragraphs as $para) {
            $paraTokens = $this->calculator->estimate($para);
            
            if ($paraTokens > $chunkSize) {
                // Paragraf çok büyük, satırlara böl
                $lines = explode("\n", $para);
                foreach ($lines as $line) {
                    $lineTokens = $this->calculator->estimate($line);
                    
                    if ($currentTokens + $lineTokens > $chunkSize && $currentChunk !== '') {
                        $chunks[] = trim($currentChunk);
                        $currentChunk = $line;
                        $currentTokens = $lineTokens;
                    } else {
                        $currentChunk .= "\n" . $line;
                        $currentTokens += $lineTokens;
                    }
                }
            } elseif ($currentTokens + $paraTokens > $chunkSize) {
                // Yeni chunk
                if ($currentChunk !== '') {
                    $chunks[] = trim($currentChunk);
                }
                $currentChunk = $para;
                $currentTokens = $paraTokens;
            } else {
                // Aynı chunk'a ekle
                $currentChunk .= "\n\n" . $para;
                $currentTokens += $paraTokens;
            }
        }
        
        // Son chunk
        if ($currentChunk !== '') {
            $chunks[] = trim($currentChunk);
        }
        
        return $chunks;
    }
    
    /**
     * Alakalı kısımları kaldır
     */
    private function removeRelevant($context, $relevant) {
        return str_replace($relevant, '', $context);
    }
    
    /**
     * Strateji öner
     */
    private function recommendStrategy($tokens) {
        if ($tokens <= self::MAX_TOKENS - self::SAFETY_MARGIN) {
            return 'direct';
        } elseif ($tokens <= self::WARNING_THRESHOLD) {
            return 'compress';
        } elseif ($tokens <= self::MAX_TOKENS * 1.5) {
            return 'smart_summary';
        } else {
            return 'chunked';
        }
    }
    
    /**
     * Backward compatibility için eski method
     */
    public function manageContext($fullContext, $currentQuery) {
        return $this->optimizeFor128K($fullContext, $currentQuery);
    }
}
