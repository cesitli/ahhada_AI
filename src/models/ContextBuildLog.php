<?php

namespace App\Models;

class ContextBuildLog extends BaseModel
{
    protected $table = 'context_build_logs';
    
    protected $fillable = [
        'conversation_id',
        'context_tokens',
        'summary_tokens',
        'message_tokens',
        'created_at'
    ];
    
    protected $casts = [
        'created_at' => 'datetime'
    ];
    
    /**
     * Conversation ilişkisi
     */
    public function conversation()
    {
        return $this->belongsTo(Conversation::class);
    }
    
    /**
     * Context build istatistikleri
     */
    public static function getBuildStatistics($conversationId = null, $days = 7)
    {
        $query = self::query();
        
        if ($conversationId) {
            $query->where('conversation_id', $conversationId);
        }
        
        $query->where('created_at', '>=', date('Y-m-d H:i:s', strtotime("-{$days} days")));
        
        return $query->selectRaw('
            DATE(created_at) as build_date,
            COUNT(*) as build_count,
            AVG(context_tokens) as avg_context_tokens,
            AVG(summary_tokens) as avg_summary_tokens,
            AVG(message_tokens) as avg_message_tokens,
            SUM(context_tokens) as total_context_tokens
        ')
        ->groupBy('DATE(created_at)')
        ->orderBy('build_date', 'desc')
        ->get();
    }
}