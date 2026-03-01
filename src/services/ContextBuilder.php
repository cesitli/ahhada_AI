<?php

namespace App\Services;

use App\Models\Message;
use App\Models\MemorySummary;
use App\Services\TokenManager;
use App\Services\EmbeddingService;
use App\Config\Database;

class ContextBuilder
{
    private $tokenManager;
    private $embeddingService;
    
    public function __construct()
    {
        $this->tokenManager = new TokenManager();
        $this->embeddingService = new EmbeddingService();
    }
    
    /**
     * 128K için intelligent context oluştur (Database fonksiyonunu kullan)
     */
    public function buildIntelligentContext($conversationId, $newMessage = null)
    {
        try {
            // Database fonksiyonunu çağır
            $sql = "SELECT build_intelligent_context(?, ?, ?) as optimized_context";
            $stmt = Database::executeQuery($sql, [
                $conversationId, 
                $newMessage, 
                120000 // max_tokens
            ]);
            
            $result = $stmt->fetch();
            
            return [
                'context' => $result['optimized_context'] ?? '',
                'source' => 'database_function',
                'tokens' => $this->tokenManager->countTokens($result['optimized_context'] ?? '')
            ];
            
        } catch (\Exception $e) {
            // Fallback: eski yöntem
            return $this->buildFallbackContext($conversationId, $newMessage);
        }
    }
    
    /**
     * Fallback context builder (database fonksiyonu çalışmazsa)
     */
    private function buildFallbackContext($conversationId, $newMessage = null)
    {
        // Hierarchical memory sistemi
        $contextParts = [];
        
        // 1. Core memories (her zaman dahil)
        $coreMemories = $this->getCoreMemories($conversationId);
        $contextParts[] = "=== CORE MEMORIES ===\n" . $coreMemories;
        
        // 2. Son 1 aylık özetler
        $monthlySummaries = $this->getMonthlySummaries($conversationId, 3); // Son 3 ay
        $contextParts[] = "=== MONTHLY SUMMARIES ===\n" . $monthlySummaries;
        
        // 3. Son 1 haftalık özet
        $weeklySummary = $this->getWeeklySummary($conversationId);
        $contextParts[] = "=== WEEKLY SUMMARY ===\n" . $weeklySummary;
        
        // 4. Son 50 mesaj (direct context)
        $recentMessages = $this->getRecentMessages($conversationId, 50);
        $contextParts[] = "=== RECENT MESSAGES (Last 50) ===\n" . $recentMessages;
        
        // 5. Relevance-based selection
        if ($newMessage) {
            $relevantMessages = $this->findRelevantMessages($conversationId, $newMessage);
            $contextParts[] = "=== RELEVANT OLD MESSAGES ===\n" . $relevantMessages;
        }
        
        $fullContext = implode("\n\n", $contextParts);
        $tokens = $this->tokenManager->countTokens($fullContext);
        
        // 128K limit kontrolü
        if ($tokens > 120000) {
            $fullContext = $this->tokenManager->optimizeFor128K($fullContext);
            $tokens = $this->tokenManager->countTokens($fullContext);
        }
        
        return [
            'context' => $fullContext,
            'source' => 'fallback_hierarchical',
            'tokens' => $tokens
        ];
    }
    
    /**
     * Otomatik özetleme kontrolü
     */
    public function checkAndSummarizeIfNeeded($conversationId)
    {
        try {
            // Database fonksiyonunu çağır
            $sql = "SELECT check_and_summarize_if_needed(?) as should_summarize";
            $stmt = Database::executeQuery($sql, [$conversationId]);
            $result = $stmt->fetch();
            
            return $result['should_summarize'] ?? false;
            
        } catch (\Exception $e) {
            // Fallback kontrol
            return $this->fallbackCheckSummarization($conversationId);
        }
    }
    
    /**
     * Fallback özetleme kontrolü
     */
    private function fallbackCheckSummarization($conversationId)
    {
        // 1. Mesaj sayısı kontrolü (100+)
        $messageCount = Message::where('conversation_id', $conversationId)->count();
        if ($messageCount >= 100) {
            return true;
        }
        
        // 2. Son özetlemeden bu yana geçen zaman (7+ gün)
        $lastSummary = MemorySummary::where('conversation_id', $conversationId)
            ->orderBy('created_at', 'desc')
            ->first();
            
        if ($lastSummary) {
            $daysSinceLastSummary = (time() - strtotime($lastSummary->created_at)) / (60 * 60 * 24);
            if ($daysSinceLastSummary >= 7) {
                return true;
            }
        } else {
            // Hiç özet yoksa ve 50+ mesaj varsa
            if ($messageCount >= 50) {
                return true;
            }
        }
        
        // 3. Token sayısı kontrolü (50K+)
        $messages = Message::where('conversation_id', $conversationId)
            ->orderBy('created_at', 'asc')
            ->get();
            
        $totalTokens = 0;
        foreach ($messages as $message) {
            $totalTokens += $this->tokenManager->countTokens($message->content);
            if ($totalTokens >= 50000) {
                return true;
            }
        }
        
        return false;
    }
    
    /**
     * Alakalı mesajları bul (embedding similarity)
     */
    public function findRelevantMessages($conversationId, $newMessage, $limit = 20)
    {
        try {
            // Yeni mesajın embedding'ini al
            $newEmbedding = $this->embeddingService->getEmbedding($newMessage);
            
            // Eski mesajları al (son 1000 mesaj)
            $messages = Message::where('conversation_id', $conversationId)
                ->where('embedding', '!=', null)
                ->orderBy('created_at', 'desc')
                ->limit(1000)
                ->get();
            
            $scoredMessages = [];
            foreach ($messages as $message) {
                // Cosine similarity hesapla
                $similarity = $this->embeddingService->cosineSimilarity(
                    $newEmbedding,
                    json_decode($message->embedding, true)
                );
                
                // Importance score ekle (core memories daha yüksek)
                $importanceScore = $message->is_core_memory ? 1.5 : 1.0;
                $totalScore = $similarity * $importanceScore;
                
                $scoredMessages[] = [
                    'message' => $message,
                    'score' => $totalScore
                ];
            }
            
            // Score'a göre sırala
            usort($scoredMessages, function($a, $b) {
                return $b['score'] <=> $a['score'];
            });
            
            // Top limit al
            $relevantMessages = array_slice($scoredMessages, 0, $limit);
            
            // Formatla
            $formatted = [];
            foreach ($relevantMessages as $item) {
                $msg = $item['message'];
                $formatted[] = sprintf(
                    "[%s] %s: %s (score: %.3f)",
                    $msg->created_at,
                    $msg->role,
                    $msg->content,
                    $item['score']
                );
            }
            
            return implode("\n", $formatted);
            
        } catch (\Exception $e) {
            return "Relevance search failed: " . $e->getMessage();
        }
    }
    
    /**
     * Core memories al
     */
    private function getCoreMemories($conversationId)
    {
        $memories = Message::where('conversation_id', $conversationId)
            ->where('is_core_memory', true)
            ->orderBy('created_at', 'asc')
            ->get();
            
        $formatted = [];
        foreach ($memories as $memory) {
            $formatted[] = sprintf("[Core] %s: %s", $memory->role, $memory->content);
        }
        
        return implode("\n", $formatted);
    }
    
    /**
     * Aylık özetler al
     */
    private function getMonthlySummaries($conversationId, $months = 3)
    {
        $summaries = MemorySummary::where('conversation_id', $conversationId)
            ->where('summary_type', 'monthly')
            ->orderBy('created_at', 'desc')
            ->limit($months)
            ->get();
            
        $formatted = [];
        foreach ($summaries as $summary) {
            $formatted[] = sprintf(
                "[%s - %s] %s",
                $summary->period_start,
                $summary->period_end,
                $summary->summary_text
            );
        }
        
        return implode("\n\n", $formatted);
    }
    
    /**
     * Haftalık özet al
     */
    private function getWeeklySummary($conversationId)
    {
        $summary = MemorySummary::where('conversation_id', $conversationId)
            ->where('summary_type', 'weekly')
            ->orderBy('created_at', 'desc')
            ->first();
            
        return $summary ? $summary->summary_text : "No weekly summary available.";
    }
    
    /**
     * Son mesajları al
     */
    private function getRecentMessages($conversationId, $limit = 50)
    {
        $messages = Message::where('conversation_id', $conversationId)
            ->orderBy('created_at', 'desc')
            ->limit($limit)
            ->get()
            ->reverse(); // Eskiye göre sırala
            
        $formatted = [];
        foreach ($messages as $message) {
            $formatted[] = sprintf("[%s] %s: %s", 
                $message->created_at, 
                $message->role, 
                $message->content
            );
        }
        
        return implode("\n", $formatted);
    }
    
    /**
     * Emergency summarize tetikle
     */
    public function triggerEmergencySummarization($conversationId)
    {
        try {
            $sql = "SELECT force_emergency_summarize(?) as result";
            $stmt = Database::executeQuery($sql, [$conversationId]);
            $result = $stmt->fetch();
            
            return $result['result'] ?? false;
            
        } catch (\Exception $e) {
            error_log("Emergency summarization failed: " . $e->getMessage());
            return false;
        }
    }
}