<?php

namespace App\Services;

use App\Models\Conversation;
use App\Models\Message;
use App\Models\MemorySummary;
use App\Config\Config;
use Exception;

class Summarizer
{
    private AIProviderManager $aiManager;
    private int $maxSummaryTokens;
    
    public function __construct()
    {
        $this->aiManager = new AIProviderManager();
        $this->maxSummaryTokens = Config::get('context.summary_threshold', 1000);
    }
    
    public function autoSummarizeConversation(int $conversationId, ?string $provider = null): bool
    {
        try {
            $conversation = Conversation::find($conversationId);
            if (!$conversation) {
                throw new Exception("Conversation not found");
            }
            
            // Get messages that aren't already summarized
            $latestSummary = MemorySummary::getLatestByConversation($conversationId);
            $coveredIds = $latestSummary ? $latestSummary->getCoveredMessageIds() : [];
            
            // Get messages after the last summary
            $messages = $conversation->getMessages();
            $messagesToSummarize = [];
            
            foreach ($messages as $message) {
                if (!in_array($message->getId(), $coveredIds)) {
                    $messagesToSummarize[] = $message;
                }
            }
            
            // If we have enough messages to summarize
            if (count($messagesToSummarize) >= 10) {
                $summary = $this->generateSummaryWithAI($messagesToSummarize, $provider);
                
                if ($summary) {
                    $sourceIds = array_map(function($msg) {
                        return $msg->getId();
                    }, $messagesToSummarize);
                    
                    $tokenCount = $this->estimateTokens($summary);
                    
                    $memorySummary = new MemorySummary(
                        $conversationId,
                        $summary,
                        $sourceIds,
                        $tokenCount,
                        ($latestSummary ? $latestSummary->getVersion() + 1 : 1)
                    );
                    
                    return $memorySummary->save();
                }
            }
            
            return false;
            
        } catch (Exception $e) {
            error_log("Auto summarization failed: " . $e->getMessage());
            return false;
        }
    }
    
    public function forceEmergencySummarize(int $conversationId, ?string $provider = null): string
    {
        try {
            $conversation = Conversation::find($conversationId);
            if (!$conversation) {
                throw new Exception("Conversation not found");
            }
            
            // Get all messages
            $messages = $conversation->getMessages();
            
            // Create chunks if too many messages
            $chunks = array_chunk($messages, 50);
            $allSummaries = [];
            
            foreach ($chunks as $chunkIndex => $chunk) {
                $summary = $this->generateSummaryWithAI($chunk, $provider);
                if ($summary) {
                    $allSummaries[] = "Bölüm " . ($chunkIndex + 1) . ": " . $summary;
                    
                    // Save summary for this chunk
                    $sourceIds = array_map(function($msg) {
                        return $msg->getId();
                    }, $chunk);
                    
                    $tokenCount = $this->estimateTokens($summary);
                    
                    $memorySummary = new MemorySummary(
                        $conversationId,
                        $summary,
                        $sourceIds,
                        $tokenCount
                    );
                    
                    $memorySummary->save();
                }
            }
            
            // Create a final summary of summaries
            if (count($allSummaries) > 1) {
                $finalSummary = $this->generateFinalSummary($allSummaries, $provider);
                
                if ($finalSummary) {
                    $allSourceIds = [];
                    foreach ($messages as $message) {
                        $allSourceIds[] = $message->getId();
                    }
                    
                    $tokenCount = $this->estimateTokens($finalSummary);
                    
                    $finalMemorySummary = new MemorySummary(
                        $conversationId,
                        $finalSummary,
                        $allSourceIds,
                        $tokenCount,
                        999 // Special version for emergency summary
                    );
                    
                    $finalMemorySummary->save();
                    
                    return $finalSummary;
                }
            }
            
            return implode("\n\n", $allSummaries);
            
        } catch (Exception $e) {
            error_log("Emergency summarization failed: " . $e->getMessage());
            return "Acil özetleme başarısız oldu. Sistem normal moda döndü.";
        }
    }
    
    private function generateSummaryWithAI(array $messages, ?string $provider = null): ?string
    {
        try {
            // Prepare conversation text
            $conversationText = "";
            foreach ($messages as $message) {
                $conversationText .= $message->getRole() . ": " . $message->getContent() . "\n";
            }
            
            // Truncate if too long
            if (strlen($conversationText) > 15000) {
                $conversationText = substr($conversationText, 0, 15000) . "\n[Devamı var...]";
            }
            
            // Use AIProviderManager for summarization
            $summary = $this->aiManager->summarize($conversationText, [
                'provider' => $provider,
                'max_tokens' => $this->maxSummaryTokens,
                'temperature' => 0.3,
            ]);
            
            return trim($summary);
            
        } catch (Exception $e) {
            error_log("Summary generation error: " . $e->getMessage());
            return $this->generateSimpleSummary($messages);
        }
    }
    
    private function generateFinalSummary(array $chunkSummaries, ?string $provider = null): ?string
    {
        try {
            $allSummaries = implode("\n\n", $chunkSummaries);
            
            // Use AIProviderManager for final summary
            $summary = $this->aiManager->summarize($allSummaries, [
                'provider' => $provider,
                'max_tokens' => 1500,
                'temperature' => 0.3,
            ]);
            
            return trim($summary);
            
        } catch (Exception $e) {
            error_log("Final summary generation failed: " . $e->getMessage());
            return null;
        }
    }
    
    // ... (generateSimpleSummary ve estimateTokens metodları aynı kalacak)
    // Bu metodlar önceki dosyada mevcut, tekrar yazmıyorum
}
    
    public function forceEmergencySummarize(int $conversationId): string
    {
        try {
            $conversation = Conversation::find($conversationId);
            if (!$conversation) {
                throw new Exception("Conversation not found");
            }
            
            // Get all messages
            $messages = $conversation->getMessages();
            
            // Create chunks if too many messages
            $chunks = array_chunk($messages, 50);
            $allSummaries = [];
            
            foreach ($chunks as $chunkIndex => $chunk) {
                $summary = $this->generateSummaryWithAI($chunk);
                if ($summary) {
                    $allSummaries[] = "Bölüm " . ($chunkIndex + 1) . ": " . $summary;
                    
                    // Save summary for this chunk
                    $sourceIds = array_map(function($msg) {
                        return $msg->getId();
                    }, $chunk);
                    
                    $tokenCount = $this->estimateTokens($summary);
                    
                    $memorySummary = new MemorySummary(
                        $conversationId,
                        $summary,
                        $sourceIds,
                        $tokenCount
                    );
                    
                    $memorySummary->save();
                }
            }
            
            // Create a final summary of summaries
            if (count($allSummaries) > 1) {
                $finalSummary = $this->generateFinalSummary($allSummaries);
                
                if ($finalSummary) {
                    $allSourceIds = [];
                    foreach ($messages as $message) {
                        $allSourceIds[] = $message->getId();
                    }
                    
                    $tokenCount = $this->estimateTokens($finalSummary);
                    
                    $finalMemorySummary = new MemorySummary(
                        $conversationId,
                        $finalSummary,
                        $allSourceIds,
                        $tokenCount,
                        999 // Special version for emergency summary
                    );
                    
                    $finalMemorySummary->save();
                    
                    return $finalSummary;
                }
            }
            
            return implode("\n\n", $allSummaries);
            
        } catch (Exception $e) {
            error_log("Emergency summarization failed: " . $e->getMessage());
            return "Acil özetleme başarısız oldu. Sistem normal moda döndü.";
        }
    }
    
    private function generateSummaryWithAI(array $messages): ?string
    {
        try {
            // Prepare conversation text
            $conversationText = "";
            foreach ($messages as $message) {
                $conversationText .= $message->getRole() . ": " . $message->getContent() . "\n";
            }
            
            // Truncate if too long
            if (strlen($conversationText) > 15000) {
                $conversationText = substr($conversationText, 0, 15000) . "\n[Devamı var...]";
            }
            
            $prompt = "Aşağıdaki konuşmayı özetleyin. Önemli noktaları, kararları, planları ve tekrarlanan temaları vurgulayın.\n\n" .
                     "Konuşma:\n" . $conversationText . "\n\n" .
                     "Özet:";
            
            $response = $this->httpClient->post('chat/completions', [
                'json' => [
                    'model' => $this->gptModel,
                    'messages' => [
                        ['role' => 'system', 'content' => 'Sen bir konuşma özetleyicisin. Konuşmanın özünü yakalayan kısa ve öz bir özet sağla.'],
                        ['role' => 'user', 'content' => $prompt]
                    ],
                    'max_tokens' => $this->maxSummaryTokens,
                    'temperature' => 0.3
                ]
            ]);
            
            $data = json_decode($response->getBody()->getContents(), true);
            
            if (isset($data['choices'][0]['message']['content'])) {
                return trim($data['choices'][0]['message']['content']);
            }
            
            return null;
            
        } catch (RequestException $e) {
            error_log("OpenAI API error: " . $e->getMessage());
            return $this->generateSimpleSummary($messages);
        } catch (Exception $e) {
            error_log("Summary generation error: " . $e->getMessage());
            return $this->generateSimpleSummary($messages);
        }
    }
    
    private function generateFinalSummary(array $chunkSummaries): ?string
    {
        try {
            $allSummaries = implode("\n\n", $chunkSummaries);
            
            $prompt = "Aşağıdaki bölüm özetlerinden birleşik bir ana özet oluşturun:\n\n" . $allSummaries;
            
            $response = $this->httpClient->post('chat/completions', [
                'json' => [
                    'model' => $this->gptModel,
                    'messages' => [
                        ['role' => 'system', 'content' => 'Sen bir özet birleştiricisin. Bölüm özetlerinden kapsamlı bir ana özet oluştur.'],
                        ['role' => 'user', 'content' => $prompt]
                    ],
                    'max_tokens' => 1500,
                    'temperature' => 0.3
                ]
            ]);
            
            $data = json_decode($response->getBody()->getContents(), true);
            
            if (isset($data['choices'][0]['message']['content'])) {
                return trim($data['choices'][0]['message']['content']);
            }
            
            return null;
            
        } catch (Exception $e) {
            error_log("Final summary generation failed: " . $e->getMessage());
            return null;
        }
    }
    
    private function generateSimpleSummary(array $messages): string
    {
        // Fallback simple summary
        $userMessages = [];
        $assistantMessages = [];
        
        foreach ($messages as $message) {
            if ($message->getRole() === 'user') {
                $userMessages[] = $message->getContent();
            } else {
                $assistantMessages[] = $message->getContent();
            }
        }
        
        $summary = "Konuşma Özeti:\n";
        $summary .= "- Toplam mesaj: " . count($messages) . "\n";
        $summary .= "- Kullanıcı mesajları: " . count($userMessages) . "\n";
        $summary .= "- Asistan mesajları: " . count($assistantMessages) . "\n";
        
        if (!empty($userMessages)) {
            $lastUserMsg = end($userMessages);
            $summary .= "- Son kullanıcı sorusu: " . substr($lastUserMsg, 0, 100) . "...\n";
        }
        
        return $summary;
    }
    
    private function estimateTokens(string $text): int
    {
        // Rough estimation: 1 token ≈ 4 characters for English, 2 for Turkish
        return (int) (strlen($text) / 2);
    }
}