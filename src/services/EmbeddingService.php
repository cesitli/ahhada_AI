<?php

namespace App\Services;

use App\Config\Database;
use App\Config\Config;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\RequestException;
use Exception;

class EmbeddingService
{
    private  $httpClient;
    private  $apiKey;
    private  $model;
    private  $dimensions;
    
    public function __construct()
    {
        $config = Config::get('openai');
        
        $this->httpClient = new Client([
            'base_uri' => 'https://api.openai.com/v1/',
            'timeout' => 30,
            'headers' => [
                'Authorization' => 'Bearer ' . $config['api_key'],
                'Content-Type' => 'application/json',
            ]
        ]);
        
        $this->apiKey = $config['api_key'];
        $this->model = $config['embedding_model'];
        $this->dimensions = Config::get('context.embedding_dimensions');
    }
    
    public function getEmbedding(string $text): ?string
    {
        try {
            // Check cache first
            $cacheKey = $this->generateCacheKey($text);
            $cached = $this->getFromCache($cacheKey);
            
            if ($cached !== null) {
                $this->updateCacheAccess($cacheKey);
                return $cached;
            }
            
            // Generate new embedding
            $response = $this->httpClient->post('embeddings', [
                'json' => [
                    'model' => $this->model,
                    'input' => $text,
                    'dimensions' => $this->dimensions
                ]
            ]);
            
            $data = json_decode($response->getBody()->getContents(), true);
            
            if (isset($data['data'][0]['embedding'])) {
                $embedding = $data['data'][0]['embedding'];
                $vectorString = '[' . implode(',', $embedding) . ']';
                
                // Cache the result
                $this->saveToCache($cacheKey, $text, $vectorString);
                
                return $vectorString;
            }
            
            return null;
            
        } catch (RequestException $e) {
            error_log("OpenAI embedding error: " . $e->getMessage());
            return null;
        } catch (Exception $e) {
            error_log("Embedding generation error: " . $e->getMessage());
            return null;
        }
    }
    
    public function getEmbeddingBatch(array $texts): array
    {
        $results = [];
        
        foreach ($texts as $index => $text) {
            $embedding = $this->getEmbedding($text);
            if ($embedding) {
                $results[$index] = $embedding;
            }
        }
        
        return $results;
    }
    
    private function generateCacheKey(string $text): string
    {
        return hash('sha256', $text . '|' . $this->model);
    }
    
    private function getFromCache(string $cacheKey): ?string
    {
        try {
            $sql = "SELECT embedding FROM embedding_cache 
                    WHERE cache_key = ? 
                    AND (expires_at IS NULL OR expires_at > NOW())
                    LIMIT 1";
            
            $stmt = Database::executeQuery($sql, [$cacheKey]);
            $result = $stmt->fetch();
            
            if ($result && !empty($result['embedding'])) {
                return $result['embedding'];
            }
            
            return null;
            
        } catch (Exception $e) {
            error_log("Cache read error: " . $e->getMessage());
            return null;
        }
    }
    
    private function saveToCache(string $cacheKey, string $text, string $embedding): bool
    {
        try {
            $sql = "INSERT INTO embedding_cache (
                        cache_key, text, embedding_model, embedding, 
                        created_at, last_accessed_at, usage_count
                    ) VALUES (?, ?, ?, ?::vector, NOW(), NOW(), 1)
                    ON CONFLICT (cache_key) 
                    DO UPDATE SET 
                        last_accessed_at = NOW(),
                        usage_count = embedding_cache.usage_count + 1,
                        expires_at = CASE 
                            WHEN embedding_cache.expires_at IS NULL THEN NULL
                            ELSE NOW() + INTERVAL '24 hours'
                        END";
            
            Database::executeQuery($sql, [
                $cacheKey,
                $text,
                $this->model,
                $embedding
            ]);
            
            return true;
            
        } catch (Exception $e) {
            error_log("Cache save error: " . $e->getMessage());
            return false;
        }
    }
    
    private function updateCacheAccess(string $cacheKey): void
    {
        try {
            $sql = "UPDATE embedding_cache 
                    SET last_accessed_at = NOW(),
                        usage_count = usage_count + 1
                    WHERE cache_key = ?";
            
            Database::executeQuery($sql, [$cacheKey]);
            
        } catch (Exception $e) {
            error_log("Cache update error: " . $e->getMessage());
        }
    }
    
    public function cleanExpiredCache(): int
    {
        try {
            $sql = "DELETE FROM embedding_cache 
                    WHERE expires_at IS NOT NULL 
                    AND expires_at <= NOW()";
            
            $stmt = Database::executeQuery($sql);
            return $stmt->rowCount();
            
        } catch (Exception $e) {
            error_log("Cache cleanup error: " . $e->getMessage());
            return 0;
        }
    }
    
    public function searchSimilar(string $embedding, int $limit = 10, float $threshold = 0.7): array
    {
        try {
            $sql = "SELECT text, 
                    1 - (embedding <=> ?::vector) as similarity
                    FROM embedding_cache
                    WHERE embedding IS NOT NULL
                    AND (1 - (embedding <=> ?::vector)) > ?
                    ORDER BY similarity DESC
                    LIMIT ?";
            
            $stmt = Database::executeQuery($sql, [
                $embedding,
                $embedding,
                $threshold,
                $limit
            ]);
            
            $results = [];
            while ($row = $stmt->fetch()) {
                $results[] = [
                    'text' => $row['text'],
                    'similarity' => (float) $row['similarity']
                ];
            }
            
            return $results;
            
        } catch (Exception $e) {
            error_log("Similarity search error: " . $e->getMessage());
            return [];
        }
    }
}