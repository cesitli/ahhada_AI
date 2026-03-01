<?php

namespace App\Controllers;

use App\Services\AIProviderManager;
use Exception;

class AIController
{
    private AIProviderManager $aiManager;
    
    public function __construct()
    {
        $this->aiManager = new AIProviderManager();
    }
    
    public function testAllProviders(): array
    {
        try {
            $testMessage = "Merhaba, bu bir test mesajıdır. Lütfen 'Test başarılı!' şeklinde yanıt verin.";
            
            $results = [];
            $providers = $this->aiManager->getAvailableProviders();
            
            foreach ($providers as $provider) {
                try {
                    $startTime = microtime(true);
                    
                    $response = $this->aiManager->chat([
                        ['role' => 'user', 'content' => $testMessage]
                    ], ['provider' => $provider]);
                    
                    $endTime = microtime(true);
                    
                    $results[$provider] = [
                        'success' => true,
                        'response' => $response['response'],
                        'response_time' => round($endTime - $startTime, 3),
                        'provider' => $response['provider'],
                        'tokens' => $response['usage']['total_tokens'] ?? 0,
                    ];
                    
                } catch (Exception $e) {
                    $results[$provider] = [
                        'success' => false,
                        'error' => $e->getMessage(),
                        'response_time' => null,
                    ];
                }
            }
            
            return [
                'success' => true,
                'data' => [
                    'results' => $results,
                    'timestamp' => date('Y-m-d H:i:s'),
                    'total_providers' => count($providers),
                    'successful' => count(array_filter($results, fn($r) => $r['success'])),
                ]
            ];
            
        } catch (Exception $e) {
            return [
                'success' => false,
                'error' => 'Provider test failed: ' . $e->getMessage(),
                'status' => 500
            ];
        }
    }
    
    public function getProviderStats(): array
    {
        try {
            $stats = $this->aiManager->getStats();
            
            $summary = [];
            foreach ($stats as $provider => $data) {
                $successRate = $data['requests'] > 0 
                    ? round(($data['success'] / $data['requests']) * 100, 2)
                    : 0;
                
                $summary[$provider] = [
                    'requests' => $data['requests'],
                    'success' => $data['success'],
                    'errors' => $data['errors'],
                    'success_rate' => $successRate . '%',
                    'total_tokens' => $data['total_tokens'],
                    'last_used' => $data['last_used'],
                ];
            }
            
            return [
                'success' => true,
                'data' => [
                    'stats' => $summary,
                    'timestamp' => date('Y-m-d H:i:s'),
                ]
            ];
            
        } catch (Exception $e) {
            return [
                'success' => false,
                'error' => 'Stats retrieval failed: ' . $e->getMessage(),
                'status' => 500
            ];
        }
    }
    
    public function chatTest(array $data): array
    {
        try {
            if (empty($data['message'])) {
                return [
                    'success' => false,
                    'error' => 'Message is required',
                    'status' => 400
                ];
            }
            
            $provider = $data['provider'] ?? null;
            $model = $data['model'] ?? null;
            $temperature = $data['temperature'] ?? 0.7;
            
            $messages = [
                ['role' => 'system', 'content' => 'Sen yardımcı bir AI asistansın.'],
                ['role' => 'user', 'content' => $data['message']]
            ];
            
            $options = [];
            if ($provider) {
                $options['provider'] = $provider;
            }
            if ($model) {
                $options['model'] = $model;
            }
            $options['temperature'] = $temperature;
            
            $response = $this->aiManager->chat($messages, $options);
            
            return [
                'success' => true,
                'data' => [
                    'response' => $response['response'],
                    'provider' => $response['provider'],
                    'usage' => $response['usage'],
                    'response_time' => microtime(true) - $_SERVER['REQUEST_TIME_FLOAT'],
                ]
            ];
            
        } catch (Exception $e) {
            return [
                'success' => false,
                'error' => 'Chat test failed: ' . $e->getMessage(),
                'status' => 500
            ];
        }
    }
    
    public function embeddingTest(array $data): array
    {
        try {
            if (empty($data['text'])) {
                return [
                    'success' => false,
                    'error' => 'Text is required',
                    'status' => 400
                ];
            }
            
            $provider = $data['provider'] ?? null;
            
            $embedding = $this->aiManager->getEmbedding($data['text'], $provider);
            
            return [
                'success' => true,
                'data' => [
                    'provider' => $provider,
                    'embedding_length' => count($embedding['embedding'] ?? []),
                    'model' => $embedding['model'] ?? 'unknown',
                    'dimensions' => count($embedding['embedding'] ?? []),
                ]
            ];
            
        } catch (Exception $e) {
            return [
                'success' => false,
                'error' => 'Embedding test failed: ' . $e->getMessage(),
                'status' => 500
            ];
        }
    }
}