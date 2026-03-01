// src/services/ContextOptimizer.php
class ContextOptimizer {
    
    public function optimizeForAI($context, $query, $provider) {
        // Provider'a göre optimizasyon
        switch ($provider) {
            case 'openai':
                return $this->optimizeForOpenAI($context, $query);
            case 'deepseek':
                return $this->optimizeForDeepSeek($context, $query);
            case 'gemini':
                return $this->optimizeForGemini($context, $query);
            default:
                return $this->genericOptimize($context, $query);
        }
    }
    
    private function optimizeForOpenAI($context, $query) {
        // OpenAI için özel optimizasyon
        $optimized = $context;
        
        // 1. System prompt ekle
        $systemPrompt = "You are a helpful AI assistant. Use the following context to answer:\n\n";
        
        // 2. Context'i yapılandır
        $optimized = $systemPrompt . $this->structureContext($context) . "\n\nQuestion: " . $query;
        
        // 3. Token optimizasyonu
        return $this->trimToOptimalLength($optimized, 127000);
    }
    
    private function structureContext($context) {
        // Context'i yapılandırılmış hale getir
        $lines = explode("\n", $context);
        $structured = [];
        
        foreach ($lines as $line) {
            if (trim($line)) {
                $structured[] = "- " . trim($line);
            }
        }
        
        return implode("\n", $structured);
    }
}