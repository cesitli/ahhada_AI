<?php

namespace App\Models;

use App\Utils\Logger;

class SystemHealthLog extends BaseModel
{
    protected $table = 'system_health_log';
    
    protected $fillable = [
        'check_time',
        'component',
        'status',
        'metric',
        'details'
    ];
    
    protected $casts = [
        'check_time' => 'datetime',
        'details' => 'array',
        'metric' => 'float'
    ];
    
    /**
     * Log system health check
     */
    public static function logHealth($component, $status, $metric = null, $details = [])
    {
        $log = self::create([
            'check_time' => date('Y-m-d H:i:s'),
            'component' => $component,
            'status' => $status,
            'metric' => $metric,
            'details' => $details
        ]);
        
        $logLevel = $status === 'healthy' ? 'info' : ($status === 'warning' ? 'warning' : 'error');
        Logger::$logLevel("System health check: {$component} - {$status}", $details);
        
        return $log;
    }
    
    /**
     * Run comprehensive health check
     */
    public static function runHealthCheck()
    {
        $results = [];
        
        // Database health
        $dbHealth = self::checkDatabaseHealth();
        $results[] = $dbHealth;
        
        // Redis/cache health (if applicable)
        $cacheHealth = self::checkCacheHealth();
        $results[] = $cacheHealth;
        
        // External API health
        $apiHealth = self::checkAPIHealth();
        $results[] = $apiHealth;
        
        // Storage health
        $storageHealth = self::checkStorageHealth();
        $results[] = $storageHealth;
        
        // Background jobs health
        $jobsHealth = self::checkJobsHealth();
        $results[] = $jobsHealth;
        
        // Overall status
        $hasError = collect($results)->contains('status', 'error');
        $hasWarning = collect($results)->contains('status', 'warning');
        
        $overallStatus = $hasError ? 'error' : ($hasWarning ? 'warning' : 'healthy');
        
        $overallLog = self::logHealth(
            'system_overall',
            $overallStatus,
            null,
            ['components' => $results]
        );
        
        return [
            'overall_status' => $overallStatus,
            'components' => $results,
            'check_time' => date('Y-m-d H:i:s')
        ];
    }
    
    /**
     * Check database health
     */
    private static function checkDatabaseHealth()
    {
        try {
            $db = \App\Config\Database::getConnection();
            
            // Test connection
            $stmt = $db->query('SELECT 1 as test');
            $result = $stmt->fetch();
            
            // Check table counts
            $tables = ['conversations', 'messages', 'users'];
            $counts = [];
            
            foreach ($tables as $table) {
                $stmt = $db->query("SELECT COUNT(*) as count FROM {$table}");
                $counts[$table] = $stmt->fetch()['count'];
            }
            
            // Check for long-running queries
            $stmt = $db->query("
                SELECT count(*) as long_queries 
                FROM pg_stat_activity 
                WHERE state = 'active' 
                AND now() - query_start > interval '5 minutes'
            ");
            $longQueries = $stmt->fetch()['long_queries'];
            
            $status = $longQueries > 0 ? 'warning' : 'healthy';
            
            return self::logHealth(
                'database',
                $status,
                $longQueries,
                [
                    'connection_test' => $result['test'] == 1,
                    'table_counts' => $counts,
                    'long_running_queries' => $longQueries
                ]
            );
            
        } catch (\Exception $e) {
            return self::logHealth(
                'database',
                'error',
                null,
                ['error' => $e->getMessage()]
            );
        }
    }
    
    /**
     * Get health status history
     */
    public static function getHealthHistory($component = null, $hours = 24)
    {
        $query = self::where('check_time', '>=', date('Y-m-d H:i:s', strtotime("-{$hours} hours")));
        
        if ($component) {
            $query->where('component', $component);
        }
        
        return $query->orderBy('check_time', 'desc')->get();
    }
    
    /**
     * Get health statistics
     */
    public static function getHealthStatistics($days = 7)
    {
        $startDate = date('Y-m-d H:i:s', strtotime("-{$days} days"));
        
        $stats = self::where('check_time', '>=', $startDate)
            ->selectRaw('
                component,
                status,
                COUNT(*) as count,
                AVG(metric) as avg_metric
            ')
            ->groupBy(['component', 'status'])
            ->orderBy('component')
            ->orderBy('status')
            ->get();
        
        $uptime = self::where('component', 'system_overall')
            ->where('check_time', '>=', $startDate)
            ->where('status', 'healthy')
            ->count();
        
        $totalChecks = self::where('component', 'system_overall')
            ->where('check_time', '>=', $startDate)
            ->count();
        
        $uptimePercentage = $totalChecks > 0 ? ($uptime / $totalChecks) * 100 : 0;
        
        return [
            'period_days' => $days,
            'uptime_percentage' => round($uptimePercentage, 2),
            'total_checks' => $totalChecks,
            'healthy_checks' => $uptime,
            'component_stats' => $stats
        ];
    }
}