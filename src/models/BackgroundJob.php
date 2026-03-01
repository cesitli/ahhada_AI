<?php

namespace App\Models;

use App\Config\Database;
use App\Services\Summarizer;
use App\Services\EmbeddingService;
use App\Utils\Logger;

class BackgroundJob extends BaseModel
{
    protected $table = 'background_jobs';
    
    protected $fillable = [
        'job_type',
        'payload',
        'status',
        'attempts',
        'max_attempts',
        'error_message',
        'scheduled_at',
        'started_at',
        'completed_at',
        'user_id',
        'priority' // 'high', 'medium', 'low'
    ];
    
    protected $casts = [
        'payload' => 'array',
        'scheduled_at' => 'datetime',
        'started_at' => 'datetime',
        'completed_at' => 'datetime',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
        'attempts' => 'integer',
        'max_attempts' => 'integer'
    ];
    
    protected $attributes = [
        'status' => 'pending',
        'attempts' => 0,
        'max_attempts' => 3,
        'priority' => 'medium'
    ];
    
    /**
     * Create a new job
     */
    public static function createJob($jobType, $payload = [], $options = [])
    {
        $job = self::create([
            'job_type' => $jobType,
            'payload' => $payload,
            'status' => 'pending',
            'scheduled_at' => $options['scheduled_at'] ?? date('Y-m-d H:i:s'),
            'user_id' => $options['user_id'] ?? null,
            'priority' => $options['priority'] ?? 'medium',
            'max_attempts' => $options['max_attempts'] ?? 3
        ]);
        
        Logger::info("Background job created", [
            'job_id' => $job->id,
            'job_type' => $jobType,
            'priority' => $options['priority'] ?? 'medium'
        ]);
        
        return $job;
    }
    
    /**
     * Process the job
     */
    public function process()
    {
        $this->status = 'processing';
        $this->started_at = date('Y-m-d H:i:s');
        $this->attempts += 1;
        $this->save();
        
        try {
            Logger::info("Processing background job", [
                'job_id' => $this->id,
                'job_type' => $this->job_type,
                'attempt' => $this->attempts
            ]);
            
            // Job tipine göre işlem
            switch ($this->job_type) {
                case 'auto_summarize':
                    $result = $this->processAutoSummarization();
                    break;
                    
                case 'embedding_batch':
                    $result = $this->processEmbeddingBatch();
                    break;
                    
                case 'context_build':
                    $result = $this->processContextBuild();
                    break;
                    
                case 'cleanup_old_data':
                    $result = $this->processCleanup();
                    break;
                    
                case 'ai_performance_report':
                    $result = $this->processAIPerformanceReport();
                    break;
                    
                case 'similarity_analysis':
                    $result = $this->processSimilarityAnalysis();
                    break;
                    
                default:
                    throw new \Exception("Unknown job type: {$this->job_type}");
            }
            
            $this->status = 'completed';
            $this->completed_at = date('Y-m-d H:i:s');
            $this->save();
            
            Logger::info("Background job completed", [
                'job_id' => $this->id,
                'job_type' => $this->job_type,
                'duration' => strtotime($this->completed_at) - strtotime($this->started_at)
            ]);
            
            return $result;
            
        } catch (\Exception $e) {
            $this->status = 'failed';
            $this->error_message = $e->getMessage();
            $this->save();
            
            Logger::error("Background job failed", [
                'job_id' => $this->id,
                'job_type' => $this->job_type,
                'error' => $e->getMessage(),
                'attempt' => $this->attempts
            ]);
            
            // Retry if attempts left
            if ($this->attempts < $this->max_attempts) {
                $this->retry();
            }
            
            throw $e;
        }
    }
    
    /**
     * Auto-summarization job
     */
    private function processAutoSummarization()
    {
        $payload = $this->payload;
        $conversationId = $payload['conversation_id'] ?? null;
        
        if (!$conversationId) {
            throw new \Exception("No conversation ID provided");
        }
        
        $summarizer = new Summarizer();
        $triggerReason = $payload['trigger_reason'] ?? 'auto';
        
        switch ($triggerReason) {
            case 'message_count_threshold':
                $summary = $summarizer->createWeeklySummary($conversationId);
                break;
                
            case 'token_threshold':
                $summary = $summarizer->createEmergencySummary($conversationId);
                break;
                
            case 'time_threshold':
                $summary = $summarizer->createMonthlySummary($conversationId);
                break;
                
            default:
                $summary = $summarizer->autoSummarize($conversationId);
        }
        
        // Update conversation summary count
        $conversation = Conversation::find($conversationId);
        if ($conversation) {
            $conversation->increment('summary_count');
            $conversation->save();
        }
        
        return [
            'summary_id' => $summary->id ?? null,
            'summary_type' => $summary->summary_type ?? 'auto',
            'conversation_id' => $conversationId
        ];
    }
    
    /**
     * Embedding batch job
     */
    private function processEmbeddingBatch()
    {
        $payload = $this->payload;
        $batchSize = $payload['batch_size'] ?? 100;
        
        // Embedding gerektiren mesajları bul
        $messages = Message::whereNull('embedding')
            ->whereIn('role', ['user', 'assistant'])
            ->whereNotNull('content')
            ->where('content', '!=', '')
            ->limit($batchSize)
            ->get();
        
        $processed = 0;
        $embeddingService = new EmbeddingService();
        
        foreach ($messages as $message) {
            try {
                $embedding = $embeddingService->getEmbedding($message->content);
                
                if ($embedding) {
                    // Message tablosuna kaydet
                    $message->update(['embedding' => json_encode($embedding)]);
                    
                    // MessageEmbeddings tablosuna kaydet
                    MessageEmbedding::create([
                        'message_id' => $message->id,
                        'embedding_model' => $embeddingService->getModelName(),
                        'embedding' => $embedding,
                        'created_at' => date('Y-m-d H:i:s')
                    ]);
                    
                    $processed++;
                }
            } catch (\Exception $e) {
                Logger::error("Failed to create embedding", [
                    'message_id' => $message->id,
                    'error' => $e->getMessage()
                ]);
            }
        }
        
        return [
            'processed' => $processed,
            'total' => count($messages),
            'batch_size' => $batchSize
        ];
    }
    
    /**
     * Context build job
     */
    private function processContextBuild()
    {
        $payload = $this->payload;
        $conversationId = $payload['conversation_id'] ?? null;
        
        if (!$conversationId) {
            throw new \Exception("No conversation ID provided");
        }
        
        // Context build log oluştur
        $contextBuilder = new \App\Services\ContextBuilder();
        $context = $contextBuilder->buildIntelligentContext($conversationId);
        
        // Log
        ContextBuildLog::create([
            'conversation_id' => $conversationId,
            'context_tokens' => $context['tokens'] ?? 0,
            'summary_tokens' => 0, // TODO: Calculate
            'message_tokens' => 0, // TODO: Calculate
            'created_at' => date('Y-m-d H:i:s')
        ]);
        
        return [
            'conversation_id' => $conversationId,
            'context_tokens' => $context['tokens'] ?? 0,
            'source' => $context['source'] ?? 'unknown'
        ];
    }
    
    /**
     * Get pending jobs
     */
    public static function getPendingJobs($limit = 10, $priority = null)
    {
        $query = self::where('status', 'pending')
            ->where('scheduled_at', '<=', date('Y-m-d H:i:s'))
            ->orderBy('priority', 'desc')
            ->orderBy('created_at', 'asc');
        
        if ($priority) {
            $query->where('priority', $priority);
        }
        
        return $query->limit($limit)->get();
    }
    
    /**
     * Retry failed jobs
     */
    public static function retryFailedJobs($hours = 24, $limit = 50)
    {
        $cutoffTime = date('Y-m-d H:i:s', strtotime("-{$hours} hours"));
        
        $jobs = self::where('status', 'failed')
            ->where('attempts', '<', \DB::raw('max_attempts'))
            ->where('updated_at', '<', $cutoffTime)
            ->limit($limit)
            ->get();
        
        $retried = 0;
        foreach ($jobs as $job) {
            $job->status = 'pending';
            $job->scheduled_at = date('Y-m-d H:i:s');
            $job->save();
            $retried++;
        }
        
        return $retried;
    }
}