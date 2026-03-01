<?php

namespace App\Models;

use App\Config\Database;
use App\Services\AIProviderManager;
use App\Services\TokenManager;
use App\Utils\Logger;

class Conversation extends BaseModel
{
    protected $table = 'conversations';
    
    protected $fillable = [
        'user_id',
        'title',
        'total_messages',
        'total_tokens',
        'summary_count',
        'last_message_at',
        'location',
        'metadata',
        // Yeni alanlar (migration gerekecek)
        'ai_provider',
        'model',
        'is_archived',
        'is_pinned',
        'custom_instructions',
        'context_strategy',
        'max_context_tokens',
        'temperature',
        'max_response_tokens'
    ];
    
    protected $casts = [
        'total_messages' => 'integer',
        'total_tokens' => 'integer',
        'summary_count' => 'integer',
        'metadata' => 'array',
        'last_message_at' => 'datetime',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
        // Yeni alanlar için cast
        'is_archived' => 'boolean',
        'is_pinned' => 'boolean',
        'max_context_tokens' => 'integer',
        'temperature' => 'float',
        'max_response_tokens' => 'integer'
    ];
    
    protected $attributes = [
        'total_messages' => 0,
        'total_tokens' => 0,
        'summary_count' => 0,
        'metadata' => [],
        'ai_provider' => 'openai',
        'model' => 'gpt-4o',
        'is_archived' => false,
        'is_pinned' => false,
        'context_strategy' => 'hierarchical',
        'max_context_tokens' => 120000,
        'temperature' => 0.7,
        'max_response_tokens' => 4000
    ];
    
    /**
     * User ilişkisi
     */
    public function user()
    {
        return $this->belongsTo(User::class);
    }
    
    /**
     * Messages ilişkisi
     */
    public function messages()
    {
        return $this->hasMany(Message::class)->orderBy('created_at', 'asc');
    }
    
    /**
     * MemorySummaries ilişkisi
     */
    public function memorySummaries()
    {
        return $this->hasMany(MemorySummary::class)->orderBy('created_at', 'desc');
    }
    
    /**
     * Create a new conversation (mevcut schema ile uyumlu)
     */
    public static function create(array $attributes = [])
    {
        $conversation = new self();
        
        // Default values - mevcut schema'ya uygun
        $defaults = [
            'created_at' => date('Y-m-d H:i:s'),
            'updated_at' => date('Y-m-d H:i:s'),
            'total_messages' => 0,
            'total_tokens' => 0,
            'summary_count' => 0,
            'metadata' => [],
            // Yeni alanlar için default değerler
            'ai_provider' => 'openai',
            'model' => 'gpt-4o',
            'is_archived' => false,
            'is_pinned' => false,
            'context_strategy' => 'hierarchical',
            'max_context_tokens' => 120000,
            'temperature' => 0.7,
            'max_response_tokens' => 4000
        ];
        
        // Merge defaults with attributes
        $attributes = array_merge($defaults, $attributes);
        
        // Handle metadata
        if (isset($attributes['metadata'])) {
            if (is_array($attributes['metadata'])) {
                $attributes['metadata'] = json_encode($attributes['metadata']);
            }
        } else {
            $attributes['metadata'] = json_encode([]);
        }
        
        // Validate and set provider/model (metadata'ya kaydet)
        $aiProvider = $attributes['ai_provider'] ?? 'openai';
        $aiModel = $attributes['model'] ?? 'gpt-4o';
        
        if (!self::validateProviderModel($aiProvider, $aiModel)) {
            // Fallback to defaults
            $aiProvider = 'openai';
            $aiModel = 'gpt-4o';
            
            Logger::warning("Invalid provider/model, falling back to defaults", [
                'requested_provider' => $attributes['ai_provider'] ?? 'unknown',
                'requested_model' => $attributes['model'] ?? 'unknown'
            ]);
        }
        
        // Metadata'ya AI bilgilerini ekle (yeni kolonlar yoksa)
        $metadata = json_decode($attributes['metadata'], true) ?: [];
        $metadata['ai_config'] = [
            'provider' => $aiProvider,
            'model' => $aiModel,
            'context_strategy' => $attributes['context_strategy'] ?? 'hierarchical',
            'max_context_tokens' => $attributes['max_context_tokens'] ?? 120000,
            'temperature' => $attributes['temperature'] ?? 0.7,
            'max_response_tokens' => $attributes['max_response_tokens'] ?? 4000,
            'custom_instructions' => $attributes['custom_instructions'] ?? null,
            'is_archived' => $attributes['is_archived'] ?? false,
            'is_pinned' => $attributes['is_pinned'] ?? false
        ];
        
        $attributes['metadata'] = json_encode($metadata);
        
        // Fill attributes (mevcut kolonlara)
        foreach ($attributes as $key => $value) {
            if (in_array($key, $conversation->fillable) || $key === 'created_at' || $key === 'updated_at') {
                // Yeni kolonlar database'de yoksa metadata'da tut
                if (!in_array($key, ['ai_provider', 'model', 'is_archived', 'is_pinned', 
                    'custom_instructions', 'context_strategy', 'max_context_tokens', 
                    'temperature', 'max_response_tokens'])) {
                    $conversation->{$key} = $value;
                }
            }
        }
        
        // Save to database
        $conversation->save();
        
        Logger::info("Conversation created", [
            'conversation_id' => $conversation->id,
            'user_id' => $conversation->user_id,
            'ai_provider' => $aiProvider,
            'model' => $aiModel
        ]);
        
        return $conversation;
    }
    
    /**
     * Validate provider and model combination
     */
    public static function validateProviderModel($provider, $model)
    {
        try {
            $aiManager = new AIProviderManager();
            $providers = $aiManager->getAvailableProviders();
            
            // Provider kontrolü
            if (!isset($providers[$provider])) {
                return false;
            }
            
            // Model kontrolü
            return in_array($model, $providers[$provider]['models']);
            
        } catch (\Exception $e) {
            Logger::error("Provider/model validation failed", [
                'provider' => $provider,
                'model' => $model,
                'error' => $e->getMessage()
            ]);
            return false;
        }
    }
    
    /**
     * Get AI configuration from metadata
     */
    public function getAIConfig()
    {
        $metadata = $this->metadata ?: [];
        $aiConfig = $metadata['ai_config'] ?? [];
        
        return array_merge([
            'provider' => 'openai',
            'model' => 'gpt-4o',
            'context_strategy' => 'hierarchical',
            'max_context_tokens' => 120000,
            'temperature' => 0.7,
            'max_response_tokens' => 4000,
            'custom_instructions' => null,
            'is_archived' => false,
            'is_pinned' => false
        ], $aiConfig);
    }
    
    /**
     * Update AI configuration
     */
    public function updateAIConfig($config)
    {
        $metadata = $this->metadata ?: [];
        $currentConfig = $metadata['ai_config'] ?? [];
        
        // Provider/model validation
        if (isset($config['provider']) || isset($config['model'])) {
            $provider = $config['provider'] ?? $currentConfig['provider'] ?? 'openai';
            $model = $config['model'] ?? $currentConfig['model'] ?? 'gpt-4o';
            
            if (!self::validateProviderModel($provider, $model)) {
                throw new \Exception("Invalid provider/model combination: {$provider}/{$model}");
            }
        }
        
        // Update config
        $metadata['ai_config'] = array_merge($currentConfig, $config);
        $metadata['ai_config']['updated_at'] = date('Y-m-d H:i:s');
        
        $this->metadata = $metadata;
        $this->updated_at = date('Y-m-d H:i:s');
        $this->save();
        
        return $this;
    }
    
    /**
     * Get AI provider
     */
    public function getAIProvider()
    {
        return $this->getAIConfig()['provider'];
    }
    
    /**
     * Get AI model
     */
    public function getAIModel()
    {
        return $this->getAIConfig()['model'];
    }
    
    /**
     * Switch AI provider
     */
    public function switchProvider($provider, $model = null)
    {
        $currentConfig = $this->getAIConfig();
        
        if (!self::validateProviderModel($provider, $model ?: $currentConfig['model'])) {
            throw new \Exception("Invalid provider/model combination");
        }
        
        $oldProvider = $currentConfig['provider'];
        $oldModel = $currentConfig['model'];
        
        $config = ['provider' => $provider];
        if ($model) {
            $config['model'] = $model;
        }
        
        // Provider history
        $metadata = $this->metadata ?: [];
        $providerHistory = $metadata['provider_history'] ?? [];
        $providerHistory[] = [
            'old_provider' => $oldProvider,
            'old_model' => $oldModel,
            'new_provider' => $provider,
            'new_model' => $model ?: $currentConfig['model'],
            'switched_at' => date('Y-m-d H:i:s')
        ];
        
        $metadata['provider_history'] = $providerHistory;
        $this->metadata = $metadata;
        
        $this->updateAIConfig($config);
        
        Logger::info("AI provider switched", [
            'conversation_id' => $this->id,
            'old_provider' => $oldProvider,
            'old_model' => $oldModel,
            'new_provider' => $provider,
            'new_model' => $model ?: $currentConfig['model']
        ]);
        
        return $this;
    }
    
    /**
     * Update conversation statistics
     */
    public function updateStatistics($inputTokens, $outputTokens, $cost = null)
    {
        $this->total_messages += 1;
        $this->total_tokens += ($inputTokens + $outputTokens);
        $this->last_message_at = date('Y-m-d H:i:s');
        
        // Calculate cost if needed
        if ($cost === null) {
            $aiConfig = $this->getAIConfig();
            $aiManager = new AIProviderManager();
            $costEstimate = $aiManager->estimateCost(
                $aiConfig['provider'],
                $aiConfig['model'],
                $inputTokens,
                $outputTokens
            );
            $cost = $costEstimate['total_cost'] ?? 0;
        }
        
        // Update cost in metadata
        $metadata = $this->metadata ?: [];
        $currentCost = $metadata['total_cost'] ?? 0;
        $metadata['total_cost'] = round($currentCost + $cost, 6);
        
        // Cost history
        $costHistory = $metadata['cost_history'] ?? [];
        $costHistory[] = [
            'input_tokens' => $inputTokens,
            'output_tokens' => $outputTokens,
            'cost' => $cost,
            'timestamp' => date('Y-m-d H:i:s'),
            'provider' => $this->getAIProvider(),
            'model' => $this->getAIModel()
        ];
        $metadata['cost_history'] = $costHistory;
        
        $this->metadata = $metadata;
        $this->updated_at = date('Y-m-d H:i:s');
        $this->save();
        
        Logger::debug("Conversation statistics updated", [
            'conversation_id' => $this->id,
            'input_tokens' => $inputTokens,
            'output_tokens' => $outputTokens,
            'new_total_tokens' => $this->total_tokens,
            'new_total_messages' => $this->total_messages,
            'cost_added' => $cost
        ]);
        
        return $this;
    }
    
    /**
     * Get conversation statistics
     */
    public function getStatistics()
    {
        $aiConfig = $this->getAIConfig();
        $metadata = $this->metadata ?: [];
        
        return [
            'id' => $this->id,
            'title' => $this->title,
            'user_id' => $this->user_id,
            'ai_provider' => $aiConfig['provider'],
            'model' => $aiConfig['model'],
            'total_tokens' => $this->total_tokens,
            'total_messages' => $this->total_messages,
            'summary_count' => $this->summary_count,
            'total_cost' => $metadata['total_cost'] ?? 0,
            'avg_tokens_per_message' => $this->total_messages > 0 ? 
                round($this->total_tokens / $this->total_messages, 2) : 0,
            'cost_per_message' => $this->total_messages > 0 ? 
                round(($metadata['total_cost'] ?? 0) / $this->total_messages, 6) : 0,
            'is_archived' => $aiConfig['is_archived'] ?? false,
            'is_pinned' => $aiConfig['is_pinned'] ?? false,
            'created_at' => $this->created_at,
            'last_message_at' => $this->last_message_at,
            'context_strategy' => $aiConfig['context_strategy'],
            'max_context_tokens' => $aiConfig['max_context_tokens'],
            'temperature' => $aiConfig['temperature'],
            'max_response_tokens' => $aiConfig['max_response_tokens'],
            'custom_instructions' => $aiConfig['custom_instructions']
        ];
    }
    
    /**
     * Archive conversation
     */
    public function archive()
    {
        $this->updateAIConfig(['is_archived' => true]);
        Logger::info("Conversation archived", ['conversation_id' => $this->id]);
        return $this;
    }
    
    /**
     * Unarchive conversation
     */
    public function unarchive()
    {
        $this->updateAIConfig(['is_archived' => false]);
        return $this;
    }
    
    /**
     * Pin conversation
     */
    public function pin()
    {
        $this->updateAIConfig(['is_pinned' => true]);
        return $this;
    }
    
    /**
     * Unpin conversation
     */
    public function unpin()
    {
        $this->updateAIConfig(['is_pinned' => false]);
        return $this;
    }
    
    /**
     * Check if conversation needs summarization
     */
    public function needsSummarization()
    {
        // Check message count
        if ($this->total_messages >= 100) {
            return ['needed' => true, 'reason' => 'message_count', 'count' => $this->total_messages];
        }
        
        // Check token count
        if ($this->total_tokens >= 50000) {
            return ['needed' => true, 'reason' => 'token_count', 'tokens' => $this->total_tokens];
        }
        
        // Check last summary age
        $lastSummary = MemorySummary::where('conversation_id', $this->id)
            ->orderBy('created_at', 'desc')
            ->first();
            
        if ($lastSummary) {
            $daysSinceLastSummary = (time() - strtotime($lastSummary->created_at)) / (60 * 60 * 24);
            if ($daysSinceLastSummary >= 7) {
                return ['needed' => true, 'reason' => 'time_based', 'days' => round($daysSinceLastSummary, 1)];
            }
        } else {
            // No summary yet
            if ($this->total_messages >= 50) {
                return ['needed' => true, 'reason' => 'first_summary', 'count' => $this->total_messages];
            }
        }
        
        return ['needed' => false];
    }
    
    /**
     * Update summary count
     */
    public function incrementSummaryCount()
    {
        $this->summary_count += 1;
        $this->save();
        return $this;
    }
    
    /**
     * Get recent messages
     */
    public function getRecentMessages($limit = 50)
    {
        return Message::where('conversation_id', $this->id)
            ->orderBy('created_at', 'desc')
            ->limit($limit)
            ->get()
            ->reverse();
    }
    
    /**
     * Get core memories
     */
    public function getCoreMemories()
    {
        return Message::where('conversation_id', $this->id)
            ->where('is_core_memory', true)
            ->orderBy('created_at', 'asc')
            ->get();
    }
    
    /**
     * Get summaries
     */
    public function getSummaries($type = null, $limit = 10)
    {
        $query = MemorySummary::where('conversation_id', $this->id);
        
        if ($type) {
            $query->where('summary_type', $type);
        }
        
        return $query->orderBy('created_at', 'desc')
            ->limit($limit)
            ->get();
    }
    
    /**
     * Get provider information
     */
    public function getProviderInfo()
    {
        try {
            $aiManager = new AIProviderManager();
            return $aiManager->getProviderInfo($this->getAIProvider());
        } catch (\Exception $e) {
            Logger::error("Failed to get provider info", [
                'conversation_id' => $this->id,
                'provider' => $this->getAIProvider(),
                'error' => $e->getMessage()
            ]);
            return null;
        }
    }
    
    /**
     * Estimate cost for a hypothetical message
     */
    public function estimateCost($inputTokens, $outputTokens)
    {
        try {
            $aiManager = new AIProviderManager();
            $aiConfig = $this->getAIConfig();
            
            return $aiManager->estimateCost(
                $aiConfig['provider'],
                $aiConfig['model'],
                $inputTokens,
                $outputTokens
            );
        } catch (\Exception $e) {
            Logger::error("Cost estimation failed", [
                'conversation_id' => $this->id,
                'error' => $e->getMessage()
            ]);
            return null;
        }
    }
    
    /**
     * Duplicate conversation
     */
    public function duplicate($newTitle = null)
    {
        $aiConfig = $this->getAIConfig();
        $metadata = $this->metadata ?: [];
        
        $newConversation = self::create([
            'user_id' => $this->user_id,
            'title' => $newTitle ?: $this->title . ' (Copy)',
            'metadata' => array_merge($metadata, [
                'duplicated_from' => $this->id,
                'duplicated_at' => date('Y-m-d H:i:s'),
                'ai_config' => $aiConfig
            ])
        ]);
        
        // Copy core memories
        $coreMemories = $this->getCoreMemories();
        foreach ($coreMemories as $memory) {
            Message::create([
                'conversation_id' => $newConversation->id,
                'user_id' => $this->user_id,
                'role' => $memory->role,
                'content' => $memory->content,
                'is_core_memory' => true,
                'metadata' => array_merge(
                    $memory->metadata ?: [],
                    ['copied_from' => $memory->id]
                )
            ]);
        }
        
        Logger::info("Conversation duplicated", [
            'original_id' => $this->id,
            'new_id' => $newConversation->id
        ]);
        
        return $newConversation;
    }
    
    /**
     * Export conversation data
     */
    public function export($format = 'json')
    {
        $aiConfig = $this->getAIConfig();
        $metadata = $this->metadata ?: [];
        
        $data = [
            'conversation' => [
                'id' => $this->id,
                'title' => $this->title,
                'user_id' => $this->user_id,
                'ai_config' => $aiConfig,
                'statistics' => $this->getStatistics(),
                'created_at' => $this->created_at,
                'last_message_at' => $this->last_message_at
            ],
            'messages' => [],
            'summaries' => [],
            'export_info' => [
                'exported_at' => date('Y-m-d H:i:s'),
                'format' => $format,
                'version' => '1.0'
            ]
        ];
        
        // Get all messages
        $messages = $this->messages;
        foreach ($messages as $message) {
            $data['messages'][] = [
                'id' => $message->id,
                'role' => $message->role,
                'content' => $message->content,
                'created_at' => $message->created_at,
                'is_core_memory' => $message->is_core_memory,
                'tokens' => $message->tokens
            ];
        }
        
        // Get summaries
        $summaries = $this->memorySummaries;
        foreach ($summaries as $summary) {
            $data['summaries'][] = [
                'id' => $summary->id,
                'summary_type' => $summary->summary_type,
                'summary_text' => $summary->summary_text,
                'period_start' => $summary->period_start,
                'period_end' => $summary->period_end,
                'created_at' => $summary->created_at
            ];
        }
        
        if ($format === 'json') {
            return json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
        } elseif ($format === 'text') {
            $text = "=== CONVERSATION EXPORT ===\n";
            $text .= "Title: {$this->title}\n";
            $text .= "ID: {$this->id}\n";
            $text .= "Provider: {$aiConfig['provider']}\n";
            $text .= "Model: {$aiConfig['model']}\n";
            $text .= "Created: {$this->created_at}\n";
            $text .= "Messages: {$this->total_messages}\n";
            $text .= "Tokens: {$this->total_tokens}\n\n";
            
            foreach ($data['messages'] as $msg) {
                $text .= "[{$msg['created_at']}] {$msg['role']}: {$msg['content']}\n";
                if ($msg['is_core_memory']) {
                    $text .= "⭐ CORE MEMORY\n";
                }
                $text .= "\n";
            }
            
            return $text;
        }
        
        return $data;
    }
    
    /**
     * Get all conversations for user with filters
     */
    public static function getUserConversations($userId, $filters = [])
    {
        $query = self::where('user_id', $userId);
        
        // Apply filters
        if (isset($filters['archived']) && $filters['archived'] !== null) {
            $query->whereRaw("metadata->'ai_config'->>'is_archived' = ?", [$filters['archived'] ? 'true' : 'false']);
        }
        
        if (isset($filters['pinned']) && $filters['pinned'] !== null) {
            $query->whereRaw("metadata->'ai_config'->>'is_pinned' = ?", [$filters['pinned'] ? 'true' : 'false']);
        }
        
        if (isset($filters['provider']) && $filters['provider']) {
            $query->whereRaw("metadata->'ai_config'->>'provider' = ?", [$filters['provider']]);
        }
        
        if (isset($filters['search']) && $filters['search']) {
            $search = $filters['search'];
            $query->where(function($q) use ($search) {
                $q->where('title', 'ILIKE', "%{$search}%")
                  ->orWhereRaw("metadata->'ai_config'->>'custom_instructions' ILIKE ?", ["%{$search}%"]);
            });
        }
        
        // Sorting
        $sortBy = $filters['sort_by'] ?? 'last_message_at';
        $sortOrder = $filters['sort_order'] ?? 'desc';
        
        $query->orderBy($sortBy, $sortOrder);
        
        // Pagination
        $page = $filters['page'] ?? 1;
        $perPage = $filters['per_page'] ?? 20;
        
        $conversations = $query->paginate($perPage, ['*'], 'page', $page);
        
        // AI config'leri decode et
        $conversations->getCollection()->transform(function($conversation) {
            $conversation->ai_config = $conversation->getAIConfig();
            return $conversation;
        });
        
        return $conversations;
    }
    
    /**
     * Get conversation usage statistics
     */
    public static function getUsageStatistics($userId = null, $period = 'month')
    {
        $query = self::query();
        
        if ($userId) {
            $query->where('user_id', $userId);
        }
        
        // Date range
        $endDate = date('Y-m-d H:i:s');
        $startDate = date('Y-m-d H:i:s', strtotime("-1 {$period}"));
        
        $query->whereBetween('created_at', [$startDate, $endDate]);
        
        $conversations = $query->get();
        
        $stats = [
            'total_conversations' => $conversations->count(),
            'total_messages' => 0,
            'total_tokens' => 0,
            'total_cost' => 0,
            'by_provider' => [],
            'by_model' => [],
            'period' => [
                'start' => $startDate,
                'end' => $endDate,
                'period' => $period
            ]
        ];
        
        foreach ($conversations as $conversation) {
            $aiConfig = $conversation->getAIConfig();
            $metadata = $conversation->metadata ?: [];
            
            $stats['total_messages'] += $conversation->total_messages;
            $stats['total_tokens'] += $conversation->total_tokens;
            $stats['total_cost'] += ($metadata['total_cost'] ?? 0);
            
            // Group by provider
            $provider = $aiConfig['provider'];
            if (!isset($stats['by_provider'][$provider])) {
                $stats['by_provider'][$provider] = [
                    'count' => 0,
                    'messages' => 0,
                    'tokens' => 0,
                    'cost' => 0
                ];
            }
            $stats['by_provider'][$provider]['count']++;
            $stats['by_provider'][$provider]['messages'] += $conversation->total_messages;
            $stats['by_provider'][$provider]['tokens'] += $conversation->total_tokens;
            $stats['by_provider'][$provider]['cost'] += ($metadata['total_cost'] ?? 0);
            
            // Group by model
            $model = $aiConfig['model'];
            $modelKey = "{$provider}:{$model}";
            if (!isset($stats['by_model'][$modelKey])) {
                $stats['by_model'][$modelKey] = [
                    'provider' => $provider,
                    'model' => $model,
                    'count' => 0,
                    'messages' => 0,
                    'tokens' => 0,
                    'cost' => 0
                ];
            }
            $stats['by_model'][$modelKey]['count']++;
            $stats['by_model'][$modelKey]['messages'] += $conversation->total_messages;
            $stats['by_model'][$modelKey]['tokens'] += $conversation->total_tokens;
            $stats['by_model'][$modelKey]['cost'] += ($metadata['total_cost'] ?? 0);
        }
        
        return $stats;
    }
    
    /**
     * Migration: Add new columns if needed
     */
    public static function migrateIfNeeded()
    {
        try {
            $db = Database::getConnection();
            
            // Check if new columns exist
            $columns = [
                'ai_provider',
                'model',
                'is_archived',
                'is_pinned',
                'custom_instructions',
                'context_strategy',
                'max_context_tokens',
                'temperature',
                'max_response_tokens'
            ];
            
            foreach ($columns as $column) {
                $stmt = $db->prepare("
                    SELECT column_name 
                    FROM information_schema.columns 
                    WHERE table_name = 'conversations' 
                    AND column_name = ?
                ");
                $stmt->execute([$column]);
                
                if (!$stmt->fetch()) {
                    // Column doesn't exist, we'll use metadata
                    Logger::info("Column {$column} doesn't exist, using metadata instead");
                }
            }
            
        } catch (\Exception $e) {
            Logger::error("Migration check failed", ['error' => $e->getMessage()]);
        }
    }
}