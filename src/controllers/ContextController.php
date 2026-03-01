<?php

namespace App\Controllers;

use App\Services\ContextBuilder;
use App\Models\Conversation;
use App\Models\Message;
use App\Utils\TokenCalculator;
use Exception;

class ContextController
{
    private ContextBuilder $contextBuilder;
    private TokenCalculator $tokenCalculator;
    
    public function __construct()
    {
        $this->contextBuilder = new ContextBuilder();
        $this->tokenCalculator = new TokenCalculator();
    }
    
    public function getContext(int $conversationId, ?string $newMessage = null): array
    {
        try {
            // Check conversation exists
            $conversation = Conversation::find($conversationId);
            if (!$conversation) {
                return [
                    'success' => false,
                    'error' => 'Konuşma bulunamadı',
                    'status' => 404
                ];
            }
            
            // Calculate current token count
            $currentTokens = Message::getConversationTokenCount($conversationId);
            
            // Check if summarization is needed
            $summarizationNeeded = $this->contextBuilder->checkAndSummarizeIfNeeded($conversationId);
            
            // Build intelligent context
            $context = $this->contextBuilder->buildIntelligentContext(
                $conversationId,
                $newMessage,
                128000
            );
            
            $contextTokens = $this->tokenCalculator->countTokens($context);
            
            return [
                'success' => true,
                'data' => [
                    'conversation_id' => $conversationId,
                    'context' => $context,
                    'context_tokens' => $contextTokens,
                    'total_conversation_tokens' => $currentTokens,
                    'summarization_performed' => $summarizationNeeded,
                    'efficiency_ratio' => round($contextTokens / max(1, $currentTokens) * 100, 2)
                ]
            ];
            
        } catch (Exception $e) {
            error_log("Context generation error: " . $e->getMessage());
            
            return [
                'success' => false,
                'error' => 'Context oluşturulurken hata: ' . $e->getMessage(),
                'status' => 500
            ];
        }
    }
    
    public function forceSummarization(int $conversationId): array
    {
        try {
            $summarizer = new \App\Services\Summarizer();
            $result = $summarizer->forceEmergencySummarize($conversationId);
            
            return [
                'success' => true,
                'data' => [
                    'conversation_id' => $conversationId,
                    'result' => $result,
                    'message' => 'Acil özetleme tamamlandı'
                ]
            ];
            
        } catch (Exception $e) {
            error_log("Force summarization error: " . $e->getMessage());
            
            return [
                'success' => false,
                'error' => 'Acil özetleme başarısız: ' . $e->getMessage(),
                'status' => 500
            ];
        }
    }
    
    public function getConversationStats(int $conversationId): array
    {
        try {
            $conversation = Conversation::find($conversationId);
            if (!$conversation) {
                return [
                    'success' => false,
                    'error' => 'Konuşma bulunamadı',
                    'status' => 404
                ];
            }
            
            $messages = $conversation->getMessages();
            $summaries = $conversation->getMemorySummaries();
            
            $messageTokens = Message::getConversationTokenCount($conversationId);
            $summaryTokens = 0;
            
            foreach ($summaries as $summary) {
                $summaryTokens += $summary->getTokenCount();
            }
            
            $totalTokens = $messageTokens + $summaryTokens;
            
            // Calculate token distribution
            $userTokens = 0;
            $assistantTokens = 0;
            $systemTokens = 0;
            
            foreach ($messages as $message) {
                switch ($message->getRole()) {
                    case 'user':
                        $userTokens += $message->getTokens();
                        break;
                    case 'assistant':
                        $assistantTokens += $message->getTokens();
                        break;
                    case 'system':
                        $systemTokens += $message->getTokens();
                        break;
                }
            }
            
            return [
                'success' => true,
                'data' => [
                    'conversation' => $conversation->jsonSerialize(),
                    'stats' => [
                        'total_messages' => count($messages),
                        'total_summaries' => count($summaries),
                        'message_tokens' => $messageTokens,
                        'summary_tokens' => $summaryTokens,
                        'total_tokens' => $totalTokens,
                        'token_distribution' => [
                            'user' => $userTokens,
                            'assistant' => $assistantTokens,
                            'system' => $systemTokens,
                            'summaries' => $summaryTokens
                        ],
                        'compression_ratio' => $totalTokens > 0 ? 
                            round(($totalTokens - $summaryTokens) / $totalTokens * 100, 2) : 0
                    ]
                ]
            ];
            
        } catch (Exception $e) {
            error_log("Stats generation error: " . $e->getMessage());
            
            return [
                'success' => false,
                'error' => 'İstatistikler oluşturulurken hata: ' . $e->getMessage(),
                'status' => 500
            ];
        }
    }
}