<?php

namespace App\Services;

use App\Config\Database;
use App\Config\Config;
use Exception;

class HealthCheckService
{
    public function checkAll(): array
    {
        $checks = [];
        
        // 1. Database connection
        $checks['database'] = $this->checkDatabase();
        
        // 2. AI Providers
        $checks['ai_providers'] = $this->checkAIProviders();
        
        // 3. Disk space
        $checks['disk_space'] = $this->checkDiskSpace();
        
        // 4. Memory usage
        $checks['memory'] = $this->checkMemory();
        
        // 5. Cache status
        $checks['cache'] = $this->checkCache();
        
        // 6. System load
        $checks['system_load'] = $this->checkSystemLoad();
        
        // Calculate overall status
        $allHealthy = true;
        foreach ($checks as $check) {
            if (!$check['healthy']) {
                $allHealthy = false;
                break;
            }
        }
        
        return [
            'overall' => $allHealthy ? 'healthy' : 'unhealthy',
            'timestamp' => date('Y-m-d H:i:s'),
            'checks' => $checks
        ];
    }
    
    private function checkDatabase(): array
    {
        try {
            $start = microtime(true);
            
            // Test connection
            $conn = Database::getConnection();
            
            // Test query
            $stmt = $conn->query("SELECT 1 as test, version() as version");
            $result = $stmt->fetch();
            
            $responseTime = round((microtime(true) - $start) * 1000, 2);
            
            // Check connection pool
            $poolSql = "SELECT count(*) as connections FROM pg_stat_activity WHERE datname = current_database()";
            $poolStmt = $conn->query($poolSql);
            $poolResult = $poolStmt->fetch();
            
            return [
                'healthy' => true,
                'response_time_ms' => $responseTime,
                'version' => $result['version'] ?? 'unknown',
                'connections' => (int) $poolResult['connections'],
                'message' => 'Database connection OK'
            ];
            
        } catch (Exception $e) {
            return [
                'healthy' => false,
                'error' => $e->getMessage(),
                'message' => 'Database connection failed'
            ];
        }
    }
    
    private function checkAIProviders(): array
    {
        $providers = Config::getEnabledProviders();
        $results = [];
        
        foreach ($providers as $name => $config) {
            $results[$name] = $this->checkAIProvider($name, $config);
        }
        
        $healthyCount = count(array_filter($results, fn($r) => $r['healthy']));
        
        return [
            'healthy' => $healthyCount > 0, // At least one provider must work
            'providers' => $results,
            'message' => "$healthyCount of " . count($providers) . " AI providers healthy"
        ];
    }
    
    private function checkAIProvider(string $name, array $config): array
    {
        try {
            // Simple ping test based on provider
            switch ($name) {
                case 'openai':
                    $testUrl = $config['base_url'] . 'models';
                    break;
                case 'deepseek':
                    $testUrl = $config['base_url'] . 'models';
                    break;
                case 'gemini':
                    $testUrl = $config['base_url'] . 'models';
                    break;
                default:
                    return [
                        'healthy' => false,
                        'message' => "Unknown provider: $name"
                    ];
            }
            
            $client = new \GuzzleHttp\Client(['timeout' => 10]);
            $response = $client->get($testUrl, [
                'headers' => [
                    'Authorization' => 'Bearer ' . $config['api_key']
                ]
            ]);
            
            return [
                'healthy' => $response->getStatusCode() === 200,
                'status_code' => $response->getStatusCode(),
                'message' => "Provider $name responded"
            ];
            
        } catch (Exception $e) {
            return [
                'healthy' => false,
                'error' => $e->getMessage(),
                'message' => "Provider $name failed"
            ];
        }
    }
    
    private function checkDiskSpace(): array
    {
        try {
            $free = disk_free_space(__DIR__);
            $total = disk_total_space(__DIR__);
            $used = $total - $free;
            $percentage = $total > 0 ? round(($used / $total) * 100, 2) : 0;
            
            $healthy = $percentage < 90; // Alert if >90% used
            
            return [
                'healthy' => $healthy,
                'free_gb' => round($free / 1024 / 1024 / 1024, 2),
                'total_gb' => round($total / 1024 / 1024 / 1024, 2),
                'used_percentage' => $percentage,
                'message' => $healthy ? 'Disk space OK' : 'Disk space running low'
            ];
            
        } catch (Exception $e) {
            return [
                'healthy' => false,
                'error' => $e->getMessage(),
                'message' => 'Disk check failed'
            ];
        }
    }
    
    private function checkMemory(): array
    {
        try {
            if (function_exists('memory_get_usage')) {
                $current = memory_get_usage(true);
                $peak = memory_get_peak_usage(true);
                $limit = ini_get('memory_limit');
                
                // Convert limit to bytes
                $limitBytes = $this->convertToBytes($limit);
                $percentage = $limitBytes > 0 ? round(($current / $limitBytes) * 100, 2) : 0;
                
                $healthy = $percentage < 80; // Alert if >80% used
                
                return [
                    'healthy' => $healthy,
                    'current_mb' => round($current / 1024 / 1024, 2),
                    'peak_mb' => round($peak / 1024 / 1024, 2),
                    'limit' => $limit,
                    'used_percentage' => $percentage,
                    'message' => $healthy ? 'Memory usage OK' : 'Memory usage high'
                ];
            }
            
            return [
                'healthy' => true,
                'message' => 'Memory check not available'
            ];
            
        } catch (Exception $e) {
            return [
                'healthy' => false,
                'error' => $e->getMessage(),
                'message' => 'Memory check failed'
            ];
        }
    }
    
    private function checkCache(): array
    {
        try {
            $sql = "SELECT 
                        COUNT(*) as total,
                        COUNT(CASE WHEN expires_at IS NOT NULL AND expires_at <= NOW() THEN 1 END) as expired,
                        COUNT(CASE WHEN last_accessed_at < NOW() - INTERVAL '7 days' THEN 1 END) as stale
                    FROM embedding_cache";
            
            $stmt = Database::executeQuery($sql);
            $result = $stmt->fetch();
            
            $total = (int) $result['total'];
            $expired = (int) $result['expired'];
            $stale = (int) $result['stale'];
            
            $healthy = $expired < ($total * 0.5); // Alert if >50% expired
            
            return [
                'healthy' => $healthy,
                'total_entries' => $total,
                'expired_entries' => $expired,
                'stale_entries' => $stale,
                'expired_percentage' => $total > 0 ? round(($expired / $total) * 100, 2) : 0,
                'message' => $healthy ? 'Cache OK' : 'High percentage of expired cache entries'
            ];
            
        } catch (Exception $e) {
            return [
                'healthy' => false,
                'error' => $e->getMessage(),
                'message' => 'Cache check failed'
            ];
        }
    }
    
    private function checkSystemLoad(): array
    {
        try {
            if (function_exists('sys_getloadavg')) {
                $load = sys_getloadavg();
                $cores = (int) shell_exec('nproc') ?? 1;
                
                $load1 = $load[0] ?? 0;
                $load5 = $load[1] ?? 0;
                $load15 = $load[2] ?? 0;
                
                $loadPerCore1 = $load1 / $cores;
                $loadPerCore5 = $load5 / $cores;
                $loadPerCore15 = $load15 / $cores;
                
                $healthy = $loadPerCore1 < 2.0; // Alert if load > 2 per core
                
                return [
                    'healthy' => $healthy,
                    'load_1min' => $load1,
                    'load_5min' => $load5,
                    'load_15min' => $load15,
                    'cores' => $cores,
                    'load_per_core_1min' => round($loadPerCore1, 2),
                    'load_per_core_5min' => round($loadPerCore5, 2),
                    'load_per_core_15min' => round($loadPerCore15, 2),
                    'message' => $healthy ? 'System load OK' : 'High system load detected'
                ];
            }
            
            return [
                'healthy' => true,
                'message' => 'Load average not available'
            ];
            
        } catch (Exception $e) {
            return [
                'healthy' => false,
                'error' => $e->getMessage(),
                'message' => 'System load check failed'
            ];
        }
    }
    
    private function convertToBytes(string $memoryLimit): int
    {
        $memoryLimit = trim($memoryLimit);
        $last = strtolower($memoryLimit[strlen($memoryLimit) - 1]);
        $value = (int) $memoryLimit;
        
        switch ($last) {
            case 'g':
                $value *= 1024 * 1024 * 1024;
                break;
            case 'm':
                $value *= 1024 * 1024;
                break;
            case 'k':
                $value *= 1024;
                break;
        }
        
        return $value;
    }
    
    public function logHealthMetrics(): void
    {
        try {
            $health = $this->checkAll();
            
            $sql = "INSERT INTO system_health_log (
                        check_time, component, status, metric, details
                    ) VALUES ";
            
            $values = [];
            $params = [];
            
            foreach ($health['checks'] as $component => $check) {
                $values[] = "(NOW(), ?, ?, ?, ?::jsonb)";
                $params[] = $component;
                $params[] = $check['healthy'] ? 'healthy' : 'unhealthy';
                $params[] = $check['used_percentage'] ?? $check['response_time_ms'] ?? 0;
                $params[] = json_encode($check);
            }
            
            if (!empty($values)) {
                $sql .= implode(', ', $values);
                Database::executeQuery($sql, $params);
            }
            
        } catch (Exception $e) {
            error_log("Health metrics logging failed: " . $e->getMessage());
        }
    }
}