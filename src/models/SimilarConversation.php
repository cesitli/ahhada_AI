<?php

namespace App\Models;

class SimilarConversation extends BaseModel
{
    protected $table = 'similar_conversations';
    
    protected $fillable = [
        'conversation_id_1',
        'conversation_id_2',
        'similarity_score',
        'detected_at'
    ];
    
    protected $casts = [
        'similarity_score' => 'float',
        'detected_at' => 'datetime'
    ];
    
    /**
     * First conversation relationship
     */
    public function conversation1()
    {
        return $this->belongsTo(Conversation::class, 'conversation_id_1');
    }
    
    /**
     * Second conversation relationship
     */
    public function conversation2()
    {
        return $this->belongsTo(Conversation::class, 'conversation_id_2');
    }
    
    /**
     * Find similar conversations for a conversation
     */
    public static function findSimilar($conversationId, $threshold = 0.7, $limit = 10)
    {
        return self::where(function($query) use ($conversationId) {
                $query->where('conversation_id_1', $conversationId)
                      ->orWhere('conversation_id_2', $conversationId);
            })
            ->where('similarity_score', '>=', $threshold)
            ->orderBy('similarity_score', 'desc')
            ->limit($limit)
            ->get()
            ->map(function($similar) use ($conversationId) {
                // Get the other conversation
                $otherConversationId = $similar->conversation_id_1 == $conversationId 
                    ? $similar->conversation_id_2 
                    : $similar->conversation_id_1;
                
                $conversation = Conversation::find($otherConversationId);
                
                return [
                    'similarity_id' => $similar->id,
                    'similarity_score' => $similar->similarity_score,
                    'detected_at' => $similar->detected_at,
                    'conversation' => $conversation ? $conversation->toArray() : null
                ];
            });
    }
    
    /**
     * Get similarity statistics
     */
    public static function getSimilarityStatistics($days = 30)
    {
        $startDate = date('Y-m-d H:i:s', strtotime("-{$days} days"));
        
        $stats = self::where('detected_at', '>=', $startDate)
            ->selectRaw('
                DATE(detected_at) as detection_date,
                COUNT(*) as detection_count,
                AVG(similarity_score) as avg_similarity,
                MIN(similarity_score) as min_similarity,
                MAX(similarity_score) as max_similarity
            ')
            ->groupBy('DATE(detected_at)')
            ->orderBy('detection_date', 'desc')
            ->get();
        
        return [
            'days' => $days,
            'total_detections' => $stats->sum('detection_count'),
            'average_similarity' => $stats->avg('avg_similarity'),
            'daily_stats' => $stats
        ];
    }
}