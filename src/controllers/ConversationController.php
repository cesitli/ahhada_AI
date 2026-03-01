<?php

namespace App\Controllers;

use App\Models\Conversation;
use App\Models\Message;
use App\Services\ContextBuilder;
use App\Services\TokenManager;
use App\Services\AIProviderManager;

class ConversationController
{
    private $contextBuilder;
    private $tokenManager;
    private $aiProvider;
    
    public function __construct()
    {
        $this->contextBuilder = new ContextBuilder();
        $this->tokenManager = new TokenManager();
        $this->aiProvider = new AIProviderManager();
    }
    
    /**
     * Enhanced 128K message handler
     */
    public function handleMessage128KEnhanced($conversationId, $messageContent, $userId, $aiOptions = [])
{
    try {
        // 1. Intelligent context al
        $context = $this->contextBuilder->buildIntelligentContext($conversationId, $messageContent);
        
        // 2. AI options
        $provider = $aiOptions['provider'] ?? 'openai';
        $model = $aiOptions['model'] ?? 'gpt-4o';
        $temperature = $aiOptions['temperature'] ?? 0.7;
        $maxTokens = $aiOptions['max_tokens'] ?? 4000;
        
        // 3. AI'ya gönder (multi-provider)
        $aiResponse = $this->aiProvider->chat(
            $context['context'], 
            $messageContent, 
            [
                'provider' => $provider,
                'model' => $model,
                'temperature' => $temperature,
                'max_tokens' => $maxTokens,
                'conversation_id' => $conversationId
            ]
        );
        
        // 4. Mesajları kaydet
        $userMessage = Message::create([
            'conversation_id' => $conversationId,
            'user_id' => $userId,
            'role' => 'user',
            'content' => $messageContent,
            'tokens' => $this->tokenManager->countTokens($messageContent),
            'metadata' => json_encode([
                'provider' => $provider,
                'model' => $model
            ])
        ]);
        
        $assistantMessage = Message::create([
            'conversation_id' => $conversationId,
            'user_id' => $userId,
            'role' => 'assistant',
            'content' => $aiResponse,
            'tokens' => $this->tokenManager->countTokens($aiResponse),
            'metadata' => json_encode([
                'provider' => $provider,
                'model' => $model,
                'response_tokens' => $this->tokenManager->countTokens($aiResponse)
            ])
        ]);
        
        // 5. Cost tracking
        $inputTokens = $context['tokens'] + $this->tokenManager->countTokens($messageContent);
        $outputTokens = $this->tokenManager->countTokens($aiResponse);
        $costEstimate = $this->aiProvider->estimateCost($provider, $model, $inputTokens, $outputTokens);
        
        // 6. Özetleme kontrolü
        $needsSummary = $this->contextBuilder->checkAndSummarizeIfNeeded($conversationId);
        $summaryTriggered = false;
        
        if ($needsSummary) {
            $summaryTriggered = $this->triggerBackgroundSummarization($conversationId);
        }
        
        return [
            'success' => true,
            'response' => $aiResponse,
            'provider' => $provider,
            'model' => $model,
            'tokens' => [
                'context' => $context['tokens'],
                'user_message' => $this->tokenManager->countTokens($messageContent),
                'ai_response' => $outputTokens,
                'total_input' => $inputTokens,
                'total_output' => $outputTokens
            ],
            'cost_estimate' => $costEstimate,
            'summary_triggered' => $summaryTriggered,
            'message_id' => $assistantMessage->id
        ];
        
    } catch (\Exception $e) {
        Logger::error("128K enhanced message handler failed", [
            'conversation_id' => $conversationId,
            'provider' => $provider ?? 'unknown',
            'error' => $e->getMessage()
        ]);
        
        // Fallback to default provider
        try {
            return $this->fallbackToDefaultProvider($conversationId, $messageContent, $userId, $context, $e);
        } catch (\Exception $fallbackError) {
            return [
                'success' => false,
                'error' => "Primary: " . $e->getMessage() . " | Fallback: " . $fallbackError->getMessage()
            ];
        }
    }
}

private function fallbackToDefaultProvider($conversationId, $messageContent, $userId, $context, $originalError)
{
    Logger::warning("Falling back to default provider", [
        'conversation_id' => $conversationId,
        'original_error' => $originalError->getMessage()
    ]);
    
    // Try OpenAI as fallback
    $aiResponse = $this->aiProvider->chat(
        $context['context'], 
        $messageContent, 
        [
            'provider' => 'openai',
            'model' => 'gpt-4o-mini',
            'conversation_id' => $conversationId
        ]
    );
    
    // Kaydet
    $userMessage = Message::create([
        'conversation_id' => $conversationId,
        'user_id' => $userId,
        'role' => 'user',
        'content' => $messageContent,
        'tokens' => $this->tokenManager->countTokens($messageContent)
    ]);
    
    $assistantMessage = Message::create([
        'conversation_id' => $conversationId,
        'user_id' => $userId,
        'role' => 'assistant',
        'content' => $aiResponse,
        'tokens' => $this->tokenManager->countTokens($aiResponse),
        'metadata' => json_encode([
            'provider' => 'openai',
            'model' => 'gpt-4o-mini',
            'fallback' => true,
            'original_error' => $originalError->getMessage()
        ])
    ]);
    
    return [
        'success' => true,
        'response' => $aiResponse,
        'provider' => 'openai',
        'model' => 'gpt-4o-mini',
        'fallback_used' => true,
        'original_error' => $originalError->getMessage(),
        'message_id' => $assistantMessage->id
    ];
}
    
    /**
     * Background summarization tetikle
     */
    private function triggerBackgroundSummarization($conversationId)
    {
        try {
            $job = \App\Models\BackgroundJob::create([
                'job_type' => 'auto_summarize',
                'payload' => [
                    'conversation_id' => $conversationId,
                    'trigger_reason' => 'auto_check'
                ],
                'status' => 'pending',
                'priority' => 'low'
            ]);
            
            return $job->id;
            
        } catch (\Exception $e) {
            error_log("Background job creation failed: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Toplam token sayısını hesapla
     */
    private function calculateTotalTokens($conversationId)
    {
        $messages = Message::where('conversation_id', $conversationId)->get();
        
        $totalTokens = 0;
        foreach ($messages as $message) {
            $totalTokens += $message->tokens ?? $this->tokenManager->countTokens($message->content);
        }
        
        return $totalTokens;
    }
    
    /**
     * Yeni API endpoint: Context analizi
     */
    public function analyzeContext($conversationId)
    {
        $context = $this->contextBuilder->buildIntelligentContext($conversationId);
        
        $messageCount = Message::where('conversation_id', $conversationId)->count();
        $summaryCount = \App\Models\MemorySummary::where('conversation_id', $conversationId)->count();
        
        return [
            'conversation_id' => $conversationId,
            'context_tokens' => $context['tokens'],
            'context_source' => $context['source'],
            'message_count' => $messageCount,
            'summary_count' => $summaryCount,
            'needs_summarization' => $this->contextBuilder->checkAndSummarizeIfNeeded($conversationId)
        ];
    }
    
    /**
     * Yeni API endpoint: Manuel özetleme
     */
    public function forceSummarize($conversationId)
    {
        $result = $this->contextBuilder->triggerEmergencySummarization($conversationId);
        
        return [
            'success' => $result,
            'message' => $result ? 
                'Emergency summarization triggered' : 
                'Summarization failed'
        ];
    }
}