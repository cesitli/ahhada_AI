<?php

namespace App\Models;

use App\Services\EmbeddingService;
use App\Utils\Logger;

class EmbeddingCache extends BaseModel
{
    protected $table = 'embedding_cache';
    protected $primaryKey = 'cache_key';
    public $incrementing = false;
    protected $keyType = 'string';
    
    protected $fillable = [
        'cache_key',
        'text',
        'embedding_model',
        'embedding',
        'usage_count',
        'last_accessed_at',
        'expires_at',
        'embedding_vector'
    ];
    
    protected $casts = [
        'embedding' => 'array',
        'last_accessed_at' => 'datetime',
        'expires_at' => 'datetime',
        'created_at' => 'datetime',
        'usage_count' => 'integer'
    ];
    
    /**
     * Get embedding from cache or create new
     */
    public static function getOrCreate($text, $model = null, $ttl = 86400) // 24 hours
    {
        $model = $model ?: EmbeddingService::DEFAULT_MODEL;
        $cacheKey = self::generateCacheKey($text, $model);
        
        // Try cache first
        $cached = self::where('cache_key', $cacheKey)
            ->where(function($query) use ($ttl) {
                $query->whereNull('expires_at')
                      ->orWhere('expires_at', '>', date('Y-m-d H:i:s'));
            })
            ->first();
        
        if ($cached) {
            // Update usage stats
            $cached->usage_count += 1;
            $cached->last_accessed_at = date('Y-m-d H:i:s');
            $cached->save();
            
            return json_decode($cached->embedding, true);
        }
        
        // Create new embedding
        $embeddingService = new EmbeddingService();
        $embedding = $embeddingService->getEmbedding($text);
        
        if (!$embedding) {
            throw new \Exception("Failed to create embedding");
        }
        
        // Cache it
        self::create([
            'cache_key' => $cacheKey,
            'text' => $text,
            'embedding_model' => $model,
            'embedding' => json_encode($embedding),
            'usage_count' => 1,
            'last_accessed_at' => date('Y-m-d H:i:s'),
            'expires_at' => $ttl ? date('Y-m-d H:i:s', time() + $ttl) : null,
            'created_at' => date('Y-m-d H:i:s')
        ]);
        
        return $embedding;
    }
    
    /**
     * Generate cache key
     */
    private static function generateCacheKey($text, $model)
    {
        $normalizedText = trim(strtolower($text));
        return $model . '_' . md5($normalizedText);
    }
    
    /**
     * Clean expired cache entries
     */
    public static function cleanExpired($limit = 1000)
    {
        $deleted = self::where('expires_at', '<', date('Y-m-d H:i:s'))
            ->limit($limit)
            ->delete();
        
        Logger::info("Expired embedding cache cleaned", [
            'deleted_count' => $deleted
        ]);
        
        return $deleted;
    }
    
    /**
     * Get cache statistics
     */
    public static function getStatistics()
    {
        $total = self::count();
        $active = self::where('expires_at', '>', date('Y-m-d H:i:s'))
            ->orWhereNull('expires_at')
            ->count();
        
        $totalSize = self::selectRaw('SUM(LENGTH(text) + LENGTH(embedding::text)) as total_size')
            ->first();
        
        $topUsed = self::orderBy('usage_count', 'desc')
            ->limit(10)
            ->get(['cache_key', 'usage_count', 'last_accessed_at']);
        
        return [
            'total_entries' => $total,
            'active_entries' => $active,
            'expired_entries' => $total - $active,
            'total_size_bytes' => $totalSize->total_size ?? 0,
            'top_used' => $topUsed
        ];
    }
}