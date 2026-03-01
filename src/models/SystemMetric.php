<?php

namespace App\Models;

use App\Utils\Logger;

class SystemMetric extends BaseModel
{
    protected $table = 'system_metrics';
    
    protected $fillable = [
        'metric_name',
        'metric_value',
        'recorded_at'
    ];
    
    protected $casts = [
        'metric_value' => 'array',
        'recorded_at' => 'datetime'
    ];
    
    /**
     * Record a system metric
     */
    public static function record($metricName, $value, $timestamp = null)
    {
        $metric = self::create([
            'metric_name' => $metricName,
            'metric_value' => is_array($value) ? $value : ['value' => $value],
            'recorded_at' => $timestamp ?: date('Y-m-d H:i:s')
        ]);
        
        Logger::debug("System metric recorded", [
            'metric_name' => $metricName,
            'value' => $value
        ]);
        
        return $metric;
    }
    
    /**
     * Get metrics for analysis
     */
    public static function getMetrics($metricName = null, $startDate = null, $endDate = null, $limit = 1000)
    {
        $query = self::query();
        
        if ($metricName) {
            $query->where('metric_name', $metricName);
        }
        
        if ($startDate) {
            $query->where('recorded_at', '>=', $startDate);
        }
        
        if ($endDate) {
            $query->where('recorded_at', '<=', $endDate);
        }
        
        return $query->orderBy('recorded_at', 'desc')
            ->limit($limit)
            ->get();
    }
    
    /**
     * Get aggregated metrics
     */
    public static function getAggregatedMetrics($metricName, $interval = 'hour', $startDate = null, $endDate = null)
    {
        $query = self::where('metric_name', $metricName);
        
        if ($startDate) {
            $query->where('recorded_at', '>=', $startDate);
        }
        
        if ($endDate) {
            $query->where('recorded_at', '<=', $endDate);
        }
        
        $timeFormat = $interval === 'hour' ? 'YYYY-MM-DD HH24:00:00' : 'YYYY-MM-DD';
        
        return $query->selectRaw("
            DATE_TRUNC('{$interval}', recorded_at) as time_bucket,
            COUNT(*) as count,
            AVG((metric_value->>'value')::numeric) as avg_value,
            MIN((metric_value->>'value')::numeric) as min_value,
            MAX((metric_value->>'value')::numeric) as max_value,
            STDDEV((metric_value->>'value')::numeric) as stddev_value
        ")
        ->groupByRaw("DATE_TRUNC('{$interval}', recorded_at)")
        ->orderBy('time_bucket', 'desc')
        ->get();
    }
    
    /**
     * Record system health metrics
     */
    public static function recordHealthMetrics()
    {
        $metrics = [];
        
        // Memory usage
        $memoryUsage = memory_get_usage(true) / 1024 / 1024; // MB
        $metrics[] = self::record('memory_usage_mb', $memoryUsage);
        
        // CPU load (approximate)
        if (function_exists('sys_getloadavg')) {
            $load = sys_getloadavg();
            $metrics[] = self::record('cpu_load_1min', $load[0]);
            $metrics[] = self::record('cpu_load_5min', $load[1]);
            $metrics[] = self::record('cpu_load_15min', $load[2]);
        }
        
        // Database connections
        $dbStats = self::getDatabaseStats();
        $metrics[] = self::record('database_connections', $dbStats['connections'] ?? 0);
        
        // Active conversations
        $activeConversations = Conversation::where('last_message_at', '>=', date('Y-m-d H:i:s', strtotime('-1 hour')))
            ->count();
        $metrics[] = self::record('active_conversations', $activeConversations);
        
        // Pending jobs
        $pendingJobs = BackgroundJob::where('status', 'pending')->count();
        $metrics[] = self::record('pending_background_jobs', $pendingJobs);
        
        // Cache hit rate (if using embedding cache)
        $cacheStats = EmbeddingCache::getStatistics();
        $metrics[] = self::record('embedding_cache_hit_rate', 
            $cacheStats['total_entries'] > 0 ? 
            ($cacheStats['active_entries'] / $cacheStats['total_entries']) * 100 : 0
        );
        
        return $metrics;
    }
    
    /**
     * Get database statistics
     */
    private static function getDatabaseStats()
    {
        try {
            $db = \App\Config\Database::getConnection();
            
            // Active connections
            $stmt = $db->query("SELECT count(*) as connections FROM pg_stat_activity WHERE state = 'active'");
            $connections = $stmt->fetch()['connections'] ?? 0;
            
            // Table sizes
            $stmt = $db->query("
                SELECT 
                    table_name,
                    pg_size_pretty(pg_total_relation_size('\"' || table_name || '\"')) as total_size
                FROM information_schema.tables
                WHERE table_schema = 'public'
                ORDER BY pg_total_relation_size('\"' || table_name || '\"') DESC
            ");
            
            $tableSizes = [];
            while ($row = $stmt->fetch()) {
                $tableSizes[$row['table_name']] = $row['total_size'];
            }
            
            return [
                'connections' => $connections,
                'table_sizes' => $tableSizes
            ];
            
        } catch (\Exception $e) {
            Logger::error("Failed to get database stats", ['error' => $e->getMessage()]);
            return ['connections' => 0, 'table_sizes' => []];
        }
    }
}