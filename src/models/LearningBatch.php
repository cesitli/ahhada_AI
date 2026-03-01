<?php

namespace App\Models;

use App\Services\EmbeddingService;
use App\Utils\Logger;

class LearningBatch extends BaseModel
{
    protected $table = 'learning_batches';
    
    protected $fillable = [
        'conversation_ids',
        'batch_size',
        'processed',
        'processed_at',
        'created_at'
    ];
    
    protected $casts = [
        'conversation_ids' => 'array',
        'processed' => 'boolean',
        'processed_at' => 'datetime',
        'created_at' => 'datetime',
        'batch_size' => 'integer'
    ];
    
    /**
     * Create learning batch from conversations
     */
    public static function createFromConversations($conversationIds, $batchSize = 100)
    {
        $batch = self::create([
            'conversation_ids' => $conversationIds,
            'batch_size' => $batchSize,
            'processed' => false,
            'created_at' => date('Y-m-d H:i:s')
        ]);
        
        Logger::info("Learning batch created", [
            'batch_id' => $batch->id,
            'conversation_count' => count($conversationIds),
            'batch_size' => $batchSize
        ]);
        
        return $batch;
    }
    
    /**
     * Process the batch
     */
    public function process()
    {
        if ($this->processed) {
            return ['already_processed' => true];
        }
        
        $this->processed = true;
        $this->processed_at = date('Y-m-d H:i:s');
        $this->save();
        
        $results = [
            'batch_id' => $this->id,
            'total_conversations' => count($this->conversation_ids),
            'processed_messages' => 0,
            'created_embeddings' => 0,
            'similar_conversations_found' => 0
        ];
        
        // Process each conversation
        foreach ($this->conversation_ids as $conversationId) {
            $conversationResults = $this->processConversation($conversationId);
            
            $results['processed_messages'] += $conversationResults['processed_messages'];
            $results['created_embeddings'] += $conversationResults['created_embeddings'];
            $results['similar_conversations_found'] += $conversationResults['similar_conversations_found'];
        }
        
        // Find similar conversations
        $similarResults = $this->findSimilarConversations();
        $results['similar_conversations_found'] = $similarResults['found'];
        
        Logger::info("Learning batch processed", $results);
        
        return $results;
    }
    
    /**
     * Process a single conversation
     */
    private function processConversation($conversationId)
    {
        $results = [
            'processed_messages' => 0,
            'created_embeddings' => 0,
            'similar_conversations_found' => 0
        ];
        
        // Get messages without embeddings
        $messages = Message::where('conversation_id', $conversationId)
            ->whereNull('embedding')
            ->whereIn('role', ['user', 'assistant'])
            ->limit($this->batch_size)
            ->get();
        
        $embeddingService = new EmbeddingService();
        
        foreach ($messages as $message) {
            try {
                $embedding = $embeddingService->getEmbedding($message->content);
                
                if ($embedding) {
                    // Update message
                    $message->embedding = json_encode($embedding);
                    $message->save();
                    
                    // Create message embedding record
                    MessageEmbedding::create([
                        'message_id' => $message->id,
                        'embedding_model' => $embeddingService->getModelName(),
                        'embedding' => $embedding,
                        'created_at' => date('Y-m-d H:i:s')
                    ]);
                    
                    $results['created_embeddings']++;
                }
                
                $results['processed_messages']++;
                
            } catch (\Exception $e) {
                Logger::error("Failed to process message embedding", [
                    'message_id' => $message->id,
                    'error' => $e->getMessage()
                ]);
            }
        }
        
        return $results;
    }
    
    /**
     * Find similar conversations
     */
    private function findSimilarConversations()
    {
        $found = 0;
        $conversationIds = $this->conversation_ids;
        
        // Her konuşma için benzer konuşmaları bul
        foreach ($conversationIds as $conversationId1) {
            foreach ($conversationIds as $conversationId2) {
                if ($conversationId1 >= $conversationId2) {
                    continue; // Skip same or already compared
                }
                
                $similarity = $this->calculateConversationSimilarity($conversationId1, $conversationId2);
                
                if ($similarity > 0.7) { // Threshold
                    SimilarConversation::create([
                        'conversation_id_1' => $conversationId1,
                        'conversation_id_2' => $conversationId2,
                        'similarity_score' => $similarity,
                        'detected_at' => date('Y-m-d H:i:s')
                    ]);
                    
                    $found++;
                }
            }
        }
        
        return ['found' => $found];
    }
    
    /**
     * Calculate conversation similarity
     */
    private function calculateConversationSimilarity($convId1, $convId2)
    {
        // Get average embeddings for each conversation
        $avgEmbedding1 = $this->getAverageEmbedding($convId1);
        $avgEmbedding2 = $this->getAverageEmbedding($convId2);
        
        if (!$avgEmbedding1 || !$avgEmbedding2) {
            return 0;
        }
        
        // Calculate cosine similarity
        $embeddingService = new EmbeddingService();
        return $embeddingService->cosineSimilarity($avgEmbedding1, $avgEmbedding2);
    }
    
    /**
     * Get average embedding for conversation
     */
    private function getAverageEmbedding($conversationId)
    {
        $embeddings = Message::where('conversation_id', $conversationId)
            ->whereNotNull('embedding')
            ->whereIn('role', ['user', 'assistant'])
            ->limit(10)
            ->get()
            ->pluck('embedding')
            ->toArray();
        
        if (empty($embeddings)) {
            return null;
        }
        
        // Convert JSON strings to arrays
        $embeddings = array_map(function($embedding) {
            return json_decode($embedding, true);
        }, $embeddings);
        
        // Calculate average embedding
        $embeddingSize = count($embeddings[0]);
        $avgEmbedding = array_fill(0, $embeddingSize, 0);
        
        foreach ($embeddings as $embedding) {
            for ($i = 0; $i < $embeddingSize; $i++) {
                $avgEmbedding[$i] += $embedding[$i];
            }
        }
        
        $count = count($embeddings);
        for ($i = 0; $i < $embeddingSize; $i++) {
            $avgEmbedding[$i] /= $count;
        }
        
        return $avgEmbedding;
    }
}