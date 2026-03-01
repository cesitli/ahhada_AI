<?php

namespace App\Models;

use App\Config\Database;
use App\Utils\Logger;

class AIPerformanceDaily extends BaseModel
{
    protected $table = 'ai_performance_daily';
    
    protected $fillable = [
        'report_date',
        'metrics',
        'created_at'
    ];
    
    protected $casts = [
        'report_date' => 'date',
        'metrics' => 'array',
        'created_at' => 'datetime'
    ];
    
    /**
     * Günlük performans raporu oluştur
     */
    public static function generateDailyReport($date = null)
    {
        $reportDate = $date ?: date('Y-m-d');
        
        // AI provider'larına göre metrikleri topla
        $providers = ['openai', 'deepseek', 'gemini', 'anthropic'];
        $metrics = [
            'total_requests' => 0,
            'total_tokens' => 0,
            'total_cost' => 0.0,
            'avg_response_time' => 0,
            'success_rate' => 1.0,
            'by_provider' => []
        ];
        
        foreach ($providers as $provider) {
            // Provider'a göre istatistikleri hesapla
            $providerMetrics = self::calculateProviderMetrics($provider, $reportDate);
            
            $metrics['by_provider'][$provider] = $providerMetrics;
            
            $metrics['total_requests'] += $providerMetrics['request_count'] ?? 0;
            $metrics['total_tokens'] += $providerMetrics['total_tokens'] ?? 0;
            $metrics['total_cost'] += $providerMetrics['total_cost'] ?? 0;
        }
        
        // Ortalama response time hesapla
        if ($metrics['total_requests'] > 0) {
            $totalTime = 0;
            foreach ($metrics['by_provider'] as $providerMetrics) {
                $totalTime += ($providerMetrics['avg_response_time'] ?? 0) * ($providerMetrics['request_count'] ?? 0);
            }
            $metrics['avg_response_time'] = $totalTime / $metrics['total_requests'];
        }
        
        // Başarı oranı hesapla
        $failedRequests = 0;
        foreach ($metrics['by_provider'] as $providerMetrics) {
            $failedRequests += $providerMetrics['failed_requests'] ?? 0;
        }
        if ($metrics['total_requests'] > 0) {
            $metrics['success_rate'] = 1 - ($failedRequests / $metrics['total_requests']);
        }
        
        // Raporu kaydet
        $report = self::create([
            'report_date' => $reportDate,
            'metrics' => $metrics,
            'created_at' => date('Y-m-d H:i:s')
        ]);
        
        Logger::info("AI Performance Daily Report generated", [
            'report_date' => $reportDate,
            'total_requests' => $metrics['total_requests'],
            'total_tokens' => $metrics['total_tokens']
        ]);
        
        return $report;
    }
    
    /**
     * Provider metriklerini hesapla
     */
    private static function calculateProviderMetrics($provider, $date)
    {
        // Messages tablosundan provider'a göre istatistikler
        $sql = "
            SELECT 
                COUNT(*) as request_count,
                SUM(tokens) as total_tokens,
                AVG(
                    CASE 
                        WHEN metadata->>'response_time' IS NOT NULL 
                        THEN (metadata->>'response_time')::numeric 
                        ELSE 0 
                    END
                ) as avg_response_time,
                SUM(
                    CASE 
                        WHEN metadata->>'failed' = 'true' 
                        THEN 1 ELSE 0 
                    END
                ) as failed_requests
            FROM messages 
            WHERE role = 'assistant'
            AND metadata->>'provider' = ?
            AND DATE(created_at) = ?
        ";
        
        $stmt = Database::executeQuery($sql, [$provider, $date]);
        $result = $stmt->fetch();
        
        // Maliyet hesapla
        $cost = self::calculateProviderCost($provider, $result['total_tokens'] ?? 0);
        
        return [
            'request_count' => (int)($result['request_count'] ?? 0),
            'total_tokens' => (int)($result['total_tokens'] ?? 0),
            'avg_response_time' => (float)($result['avg_response_time'] ?? 0),
            'failed_requests' => (int)($result['failed_requests'] ?? 0),
            'total_cost' => $cost,
            'cost_per_token' => $result['total_tokens'] > 0 ? $cost / $result['total_tokens'] : 0
        ];
    }
    
    /**
     * Provider maliyeti hesapla
     */
    private static function calculateProviderCost($provider, $totalTokens)
    {
        $rates = [
            'openai' => 0.00001, // $0.01 per 1K tokens
            'deepseek' => 0.0000014, // $0.0014 per 1K tokens
            'gemini' => 0.00000375, // $0.00375 per 1K tokens
            'anthropic' => 0.000003 // $0.003 per 1K tokens
        ];
        
        $rate = $rates[$provider] ?? 0.00001;
        return ($totalTokens / 1000) * $rate;
    }
    
    /**
     * Raporları getir
     */
    public static function getReports($startDate, $endDate, $limit = 30)
    {
        return self::whereBetween('report_date', [$startDate, $endDate])
            ->orderBy('report_date', 'desc')
            ->limit($limit)
            ->get();
    }
    
    /**
     * Trend analizi
     */
    public static function analyzeTrend($days = 7)
    {
        $endDate = date('Y-m-d');
        $startDate = date('Y-m-d', strtotime("-{$days} days"));
        
        $reports = self::whereBetween('report_date', [$startDate, $endDate])
            ->orderBy('report_date', 'asc')
            ->get();
        
        $trends = [
            'date_range' => ['start' => $startDate, 'end' => $endDate],
            'total_requests_trend' => [],
            'total_tokens_trend' => [],
            'total_cost_trend' => [],
            'success_rate_trend' => [],
            'provider_share' => []
        ];
        
        $totalRequests = 0;
        $totalTokens = 0;
        $totalCost = 0;
        
        foreach ($reports as $report) {
            $metrics = $report->metrics;
            
            $trends['total_requests_trend'][] = [
                'date' => $report->report_date,
                'value' => $metrics['total_requests'] ?? 0
            ];
            
            $trends['total_tokens_trend'][] = [
                'date' => $report->report_date,
                'value' => $metrics['total_tokens'] ?? 0
            ];
            
            $trends['total_cost_trend'][] = [
                'date' => $report->report_date,
                'value' => $metrics['total_cost'] ?? 0
            ];
            
            $trends['success_rate_trend'][] = [
                'date' => $report->report_date,
                'value' => $metrics['success_rate'] ?? 0
            ];
            
            $totalRequests += $metrics['total_requests'] ?? 0;
            $totalTokens += $metrics['total_tokens'] ?? 0;
            $totalCost += $metrics['total_cost'] ?? 0;
            
            // Provider paylaşımı
            foreach ($metrics['by_provider'] ?? [] as $provider => $providerMetrics) {
                if (!isset($trends['provider_share'][$provider])) {
                    $trends['provider_share'][$provider] = 0;
                }
                $trends['provider_share'][$provider] += $providerMetrics['request_count'] ?? 0;
            }
        }
        
        // Provider paylaşımı yüzdeleri
        if ($totalRequests > 0) {
            foreach ($trends['provider_share'] as $provider => $count) {
                $trends['provider_share'][$provider] = [
                    'count' => $count,
                    'percentage' => round(($count / $totalRequests) * 100, 2)
                ];
            }
        }
        
        return $trends;
    }
}