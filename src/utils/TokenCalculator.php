<?php

namespace App\Utils;

class TokenCalculator
{
    // Rough estimation functions for tokens
    // For production, use OpenAI's tiktoken or similar
    
    public function countTokens(string $text): int
    {
        // Simple estimation: 1 token ≈ 4 characters for English
        // For Turkish: 1 token ≈ 2 characters (more agglutinative)
        
        $charCount = mb_strlen($text, 'UTF-8');
        
        // Check language (very basic detection)
        $isTurkish = $this->isLikelyTurkish($text);
        
        if ($isTurkish) {
            // Turkish tends to have more characters per token
            return (int) ceil($charCount / 2);
        } else {
            // English/other languages
            return (int) ceil($charCount / 4);
        }
    }
    
    public function countTokensArray(array $texts): int
    {
        $total = 0;
        foreach ($texts as $text) {
            $total += $this->countTokens($text);
        }
        return $total;
    }
    
    private function isLikelyTurkish(string $text): bool
    {
        // Common Turkish characters and words
        $turkishIndicators = [
            'ç', 'ğ', 'ı', 'ö', 'ş', 'ü', 'Ç', 'Ğ', 'İ', 'Ö', 'Ş', 'Ü',
            've', 'bir', 'bu', 'de', 'ile', 'için', 'ki', 'mi', 'mu'
        ];
        
        $text = mb_strtolower($text, 'UTF-8');
        
        foreach ($turkishIndicators as $indicator) {
            if (mb_strpos($text, $indicator) !== false) {
                return true;
            }
        }
        
        return false;
    }
    
    public function estimateMaxTokensForModel(string $model): int
    {
        $modelLimits = [
            'gpt-4' => 8192,
            'gpt-4-32k' => 32768,
            'gpt-4-1106-preview' => 128000,
            'gpt-3.5-turbo' => 4096,
            'gpt-3.5-turbo-16k' => 16384,
            'text-davinci-003' => 4097,
        ];
        
        return $modelLimits[$model] ?? 4096;
    }
    
    public function truncateToTokens(string $text, int $maxTokens): string
    {
        $tokens = $this->countTokens($text);
        
        if ($tokens <= $maxTokens) {
            return $text;
        }
        
        // Estimate characters to keep
        $charsPerToken = strlen($text) / max(1, $tokens);
        $maxChars = (int) ($maxTokens * $charsPerToken * 0.9); // 90% safety margin
        
        // Try to cut at sentence boundary
        $truncated = mb_substr($text, 0, $maxChars, 'UTF-8');
        
        // Find last sentence end
        $lastPeriod = max(
            mb_strrpos($truncated, '.', 0, 'UTF-8'),
            mb_strrpos($truncated, '!', 0, 'UTF-8'),
            mb_strrpos($truncated, '?', 0, 'UTF-8'),
            mb_strrpos($truncated, "\n", 0, 'UTF-8')
        );
        
        if ($lastPeriod !== false && $lastPeriod > $maxChars * 0.5) {
            $truncated = mb_substr($truncated, 0, $lastPeriod + 1, 'UTF-8');
        }
        
        return $truncated . "\n[... devamı var]";
    }
    
    public function calculateContextEfficiency(string $fullText, string $compressedText): float
    {
        $fullTokens = $this->countTokens($fullText);
        $compressedTokens = $this->countTokens($compressedText);
        
        if ($fullTokens === 0) {
            return 0.0;
        }
        
        return round(($fullTokens - $compressedTokens) / $fullTokens * 100, 2);
    }
    
    public static function staticCountTokens(string $text): int
    {
        $calculator = new self();
        return $calculator->countTokens($text);
    }
    
    public function estimateCost(int $tokens, string $model = 'gpt-4'): float
    {
        $costPerThousand = [
            'gpt-4' => 0.03,
            'gpt-4-32k' => 0.06,
            'gpt-4-1106-preview' => 0.01,
            'gpt-3.5-turbo' => 0.001,
            'gpt-3.5-turbo-16k' => 0.003,
            'text-davinci-003' => 0.02
        ];
        
        $rate = $costPerThousand[$model] ?? 0.03;
        return ($tokens / 1000) * $rate;
    }
    public function estimate($text): int {
    return $this->countTokens($text);
}
}
