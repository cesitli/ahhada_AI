<?php

namespace App\Models;

use App\Config\Database;
use App\Services\ContextBuilder;
use App\Services\EmbeddingService;
use App\Utils\Logger;

class Message extends BaseModel
{
    protected $table = 'messages';
    
    protected $fillable = [
        'conversation_id',
        'user_id',
        'role',
        'content',
        'tokens',
        'is_core_memory',
        'embedding',
        'metadata'
    ];
    
    protected $casts = [
        'is_core_memory' => 'boolean',
        'metadata' => 'array',
        'tokens' => 'integer'
    ];
    
    /**
     * Conversation ilişkisi
     */
    public function conversation()
    {
        return $this->belongsTo(Conversation::class);
    }
    
    /**
     * User ilişkisi
     */
    public function user()
    {
        return $this->belongsTo(User::class);
    }
    
    /**
     * Create a new message
     */
    public static function create(array $attributes = [])
    {
        $message = new self();
        
        // Fill attributes
        foreach ($attributes as $key => $value) {
            if (in_array($key, $message->fillable)) {
                $message->{$key} = $value;
            }
        }
        
        // Set default values
        if (!isset($message->created_at)) {
            $message->created_at = date('Y-m-d H:i:s');
        }
        
        if (!isset($message->updated_at)) {
            $message->updated_at = date('Y-m-d H:i:s');
        }
        
        // Save to database
        $message->save();
        
        // Trigger after insert hook
        self::afterInsert($message);
        
        return $message;
    }
    
    /**
     * After insert hook - otomatik özetleme kontrolü
     */
    public static function afterInsert($message)
    {
        try {
            Logger::info("Message afterInsert hook triggered", [
                'message_id' => $message->id,
                'conversation_id' => $message->conversation_id,
                'role' => $message->role
            ]);
            
            // ContextBuilder'ı kullanarak kontrol et
            $contextBuilder = new ContextBuilder();
            $needsSummary = $contextBuilder->checkAndSummarizeIfNeeded($message->conversation_id);
            
            if ($needsSummary) {
                // Background job oluştur
                BackgroundJob::create([
                    'job_type' => 'auto_summarize',
                    'payload' => json_encode([
                        'conversation_id' => $message->conversation_id,
                        'trigger_reason' => 'message_count_threshold',
                        'trigger_message_id' => $message->id,
                        'triggered_at' => date('Y-m-d H:i:s')
                    ]),
                    'status' => 'pending',
                    'priority' => 'medium',
                    'created_at' => date('Y-m-d H:i:s')
                ]);
                
                Logger::info("Auto-summarization triggered", [
                    'conversation_id' => $message->conversation_id,
                    'message_id' => $message->id
                ]);
            }
            
            // Embedding oluştur (relevance search için)
            self::createEmbeddingIfNeeded($message);
            
            // Core memory kontrolü (önemli mesajlar)
            self::checkForCoreMemory($message);
            
        } catch (\Exception $e) {
            Logger::error("Message afterInsert hook failed", [
                'message_id' => $message->id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
        }
    }
    
    /**
     * Embedding oluştur
     */
    private static function createEmbeddingIfNeeded($message)
    {
        try {
            // Sadece user ve assistant mesajları için
            if (in_array($message->role, ['user', 'assistant'])) {
                // Content boş değilse
                if (!empty(trim($message->content))) {
                    $embeddingService = new EmbeddingService();
                    $embedding = $embeddingService->getEmbedding($message->content);
                    
                    if ($embedding && is_array($embedding)) {
                        // Güncelle
                        self::where('id', $message->id)
                            ->update([
                                'embedding' => json_encode($embedding),
                                'updated_at' => date('Y-m-d H:i:s')
                            ]);
                        
                        Logger::debug("Embedding created for message", [
                            'message_id' => $message->id,
                            'embedding_size' => count($embedding)
                        ]);
                    }
                }
            }
        } catch (\Exception $e) {
            Logger::error("Embedding creation failed", [
                'message_id' => $message->id,
                'error' => $e->getMessage()
            ]);
        }
    }
    
    /**
     * Core memory kontrolü
     */
    private static function checkForCoreMemory($message)
    {
        try {
            // Core memory işaretliyse, özel işlem yapma
            if ($message->is_core_memory) {
                return;
            }
            
            // Önemli mesajları tespit et
            $isImportant = self::detectImportantMessage($message);
            
            if ($isImportant) {
                // Core memory olarak işaretle
                self::where('id', $message->id)
                    ->update([
                        'is_core_memory' => true,
                        'metadata' => json_encode(array_merge(
                            $message->metadata ?: [],
                            ['marked_core_at' => date('Y-m-d H:i:s')]
                        ))
                    ]);
                
                Logger::info("Message marked as core memory", [
                    'message_id' => $message->id,
                    'content_preview' => substr($message->content, 0, 100)
                ]);
            }
            
        } catch (\Exception $e) {
            Logger::error("Core memory check failed", [
                'message_id' => $message->id,
                'error' => $e->getMessage()
            ]);
        }
    }
    
    /**
     * Önemli mesaj tespiti
     */
    private static function detectImportantMessage($message)
    {
        $content = strtolower(trim($message->content));
        
        // Önemli keyword'ler
        $importantKeywords = [
            'önemli', 'critical', 'essential', 'must', 'kesinlikle',
            'asla unutma', 'remember', 'dikkat', 'attention', 'warning',
            'kural', 'rule', 'policy', 'prensipler', 'principles',
            'anahtar', 'key', 'temel', 'fundamental', 'core',
            'değerler', 'values', 'inançlar', 'beliefs', 'hedefler', 'goals'
        ];
        
        // Soru işareti ile biten uzun mesajlar
        $isQuestion = substr($content, -1) === '?';
        $isLongMessage = strlen($content) > 200;
        
        // Keyword kontrolü
        foreach ($importantKeywords as $keyword) {
            if (strpos($content, $keyword) !== false) {
                return true;
            }
        }
        
        // Önemli sorular
        if ($isQuestion && $isLongMessage) {
            return true;
        }
        
        // User tarafından explicit olarak işaretlenmiş
        if (isset($message->metadata['importance']) && $message->metadata['importance'] >= 8) {
            return true;
        }
        
        return false;
    }
    
    /**
     * Get messages by conversation with pagination
     */
    public static function getByConversation($conversationId, $limit = 50, $offset = 0)
    {
        return self::where('conversation_id', $conversationId)
            ->orderBy('created_at', 'asc')
            ->limit($limit)
            ->offset($offset)
            ->get();
    }
    
    /**
     * Get recent messages
     */
    public static function getRecent($conversationId, $limit = 50)
    {
        return self::where('conversation_id', $conversationId)
            ->orderBy('created_at', 'desc')
            ->limit($limit)
            ->get();
    }
    
    /**
     * Get core memories
     */
    public static function getCoreMemories($conversationId)
    {
        return self::where('conversation_id', $conversationId)
            ->where('is_core_memory', true)
            ->orderBy('created_at', 'asc')
            ->get();
    }
    
    /**
     * Get messages within date range
     */
    public static function getByDateRange($conversationId, $startDate, $endDate)
    {
        return self::where('conversation_id', $conversationId)
            ->whereBetween('created_at', [$startDate, $endDate])
            ->orderBy('created_at', 'asc')
            ->get();
    }
    
    /**
     * Get message count
     */
    public static function getCount($conversationId)
    {
        return self::where('conversation_id', $conversationId)->count();
    }
    
    /**
     * Get total tokens
     */
    public static function getTotalTokens($conversationId)
    {
        $total = self::where('conversation_id', $conversationId)
            ->sum('tokens');
            
        return $total ?: 0;
    }
    
    /**
     * Update message content
     */
    public function updateContent($newContent, $updateTokens = true)
    {
        $this->content = $newContent;
        
        if ($updateTokens) {
            $tokenManager = new \App\Services\TokenManager();
            $this->tokens = $tokenManager->countTokens($newContent);
        }
        
        $this->updated_at = date('Y-m-d H:i:s');
        $this->save();
        
        return $this;
    }
    
    /**
     * Mark as core memory
     */
    public function markAsCoreMemory($reason = 'manual')
    {
        $this->is_core_memory = true;
        
        $metadata = $this->metadata ?: [];
        $metadata['core_memory_reason'] = $reason;
        $metadata['marked_core_at'] = date('Y-m-d H:i:s');
        
        $this->metadata = $metadata;
        $this->updated_at = date('Y-m-d H:i:s');
        $this->save();
        
        Logger::info("Message manually marked as core memory", [
            'message_id' => $this->id,
            'conversation_id' => $this->conversation_id,
            'reason' => $reason
        ]);
        
        return $this;
    }
    
    /**
     * Remove core memory mark
     */
    public function removeCoreMemory()
    {
        $this->is_core_memory = false;
        
        $metadata = $this->metadata ?: [];
        $metadata['removed_core_at'] = date('Y-m-d H:i:s');
        
        $this->metadata = $metadata;
        $this->updated_at = date('Y-m-d H:i:s');
        $this->save();
        
        return $this;
    }
    
    /**
     * Get formatted message
     */
    public function getFormatted()
    {
        return sprintf(
            "[%s] %s: %s",
            $this->created_at,
            ucfirst($this->role),
            $this->content
        );
    }
    
    /**
     * Get message with embedding for similarity search
     */
    public static function getWithEmbeddings($conversationId, $limit = 1000)
    {
        return self::where('conversation_id', $conversationId)
            ->whereNotNull('embedding')
            ->where('embedding', '!=', '')
            ->whereIn('role', ['user', 'assistant'])
            ->orderBy('created_at', 'desc')
            ->limit($limit)
            ->get();
    }
    
    /**
     * Delete message and cleanup
     */
    public function delete()
    {
        $conversationId = $this->conversation_id;
        $messageId = $this->id;
        
        // Database'den sil
        parent::delete();
        
        // Cleanup için background job
        BackgroundJob::create([
            'job_type' => 'cleanup_after_delete',
            'payload' => json_encode([
                'conversation_id' => $conversationId,
                'deleted_message_id' => $messageId,
                'deleted_at' => date('Y-m-d H:i:s')
            ]),
            'status' => 'pending',
            'priority' => 'low'
        ]);
        
        Logger::info("Message deleted", [
            'message_id' => $messageId,
            'conversation_id' => $conversationId
        ]);
        
        return true;
    }
    
    /**
     * Batch operations
     */
    public static function batchUpdate(array $ids, array $data)
    {
        if (empty($ids)) {
            return 0;
        }
        
        $data['updated_at'] = date('Y-m-d H:i:s');
        
        return self::whereIn('id', $ids)->update($data);
    }
    
    /**
     * Search in messages
     */
    public static function search($conversationId, $query, $limit = 20)
    {
        return self::where('conversation_id', $conversationId)
            ->where('content', 'LIKE', "%{$query}%")
            ->orderBy('created_at', 'desc')
            ->limit($limit)
            ->get();
    }
    
    /**
     * Get statistics
     */
    public static function getStatistics($conversationId)
    {
        $stats = [
            'total_messages' => 0,
            'user_messages' => 0,
            'assistant_messages' => 0,
            'system_messages' => 0,
            'core_memories' => 0,
            'total_tokens' => 0,
            'avg_tokens_per_message' => 0,
            'first_message_date' => null,
            'last_message_date' => null
        ];
        
        // Temel istatistikler
        $stats['total_messages'] = self::where('conversation_id', $conversationId)->count();
        $stats['user_messages'] = self::where('conversation_id', $conversationId)
            ->where('role', 'user')->count();
        $stats['assistant_messages'] = self::where('conversation_id', $conversationId)
            ->where('role', 'assistant')->count();
        $stats['system_messages'] = self::where('conversation_id', $conversationId)
            ->where('role', 'system')->count();
        $stats['core_memories'] = self::where('conversation_id', $conversationId)
            ->where('is_core_memory', true)->count();
        
        // Token istatistikleri
        $stats['total_tokens'] = self::getTotalTokens($conversationId);
        if ($stats['total_messages'] > 0) {
            $stats['avg_tokens_per_message'] = round($stats['total_tokens'] / $stats['total_messages'], 2);
        }
        
        // Tarih aralıkları
        $firstMessage = self::where('conversation_id', $conversationId)
            ->orderBy('created_at', 'asc')
            ->first();
        $lastMessage = self::where('conversation_id', $conversationId)
            ->orderBy('created_at', 'desc')
            ->first();
        
        if ($firstMessage) {
            $stats['first_message_date'] = $firstMessage->created_at;
        }
        if ($lastMessage) {
            $stats['last_message_date'] = $lastMessage->created_at;
        }
        
        return $stats;
    }
}