<?php

namespace Config;

use Dotenv\Dotenv;
use RuntimeException;

class Config
{
    private static $config = null;
    
    public static function load(): void
    {
        if (self::$config !== null) {
            return;
        }
        
        $dotenv = Dotenv::createImmutable(__DIR__ . '/..');
        // $dotenv->load(); // Dotenv yüklü değil
        // $dotenv->required([ // Dotenv yüklü değil
        //     'DB_HOST', 'DB_DATABASE', 'DB_USERNAME', 'DB_PASSWORD'
        // ]);
        
        self::$config = [
            'database' => [
                'host' => 'localhost',
                'database' => 'test_db',
                'username' => 'root',
                'password' => '',
            ],
            'ai_providers' => [
                'openai' => [
                    'enabled' => true,
                    'api_key' => '',
                ],
            ],
            'context' => [
                'max_tokens' => 8000,
                'summary_threshold' => 4000,
                'emergency_threshold' => 7000,
            ],
            'database' => [
                'host' => 'localhost',
                'database' => 'test_db',
                'username' => 'root',
                'password' => '',
            ],
            'ai_providers' => [
                'openai' => [
                    'enabled' => true,
                    'api_key' => '',
                ],
            ],
            'context' => [
                'max_tokens' => 8000,
                'summary_threshold' => 4000,
                'emergency_threshold' => 7000,
            ],
            'database' => [
                'host' => $_ENV['DB_HOST'] ?? 'localhost',
                'port' => $_ENV['DB_PORT'] ?? 5432,
                'database' => $_ENV['DB_DATABASE'],
                'username' => $_ENV['DB_USERNAME'],
                'password' => $_ENV['DB_PASS'],
                'pool_size' => (int) ($_ENV['DB_POOL_SIZE'] ?? 20),
            ],
            
            // MULTI-AI API CONFIGURATION
            'ai_providers' => [
                'openai' => [
                    'enabled' => filter_var($_ENV['OPENAI_ENABLED'] ?? true, FILTER_VALIDATE_BOOLEAN),
                    'api_key' => $_ENV['OPENAI_API_KEY'] ?? '',
                    'base_url' => $_ENV['OPENAI_BASE_URL'] ?? 'https://api.openai.com/v1/',
                    'models' => [
                        'chat' => $_ENV['OPENAI_CHAT_MODEL'] ?? 'gpt-4-1106-preview',
                        'embedding' => $_ENV['OPENAI_EMBEDDING_MODEL'] ?? 'text-embedding-3-small',
                        'summary' => $_ENV['OPENAI_SUMMARY_MODEL'] ?? 'gpt-4',
                    ],
                    'timeout' => (int) ($_ENV['OPENAI_TIMEOUT'] ?? 60),
                    'max_retries' => (int) ($_ENV['OPENAI_MAX_RETRIES'] ?? 3),
                    'priority' => (int) ($_ENV['OPENAI_PRIORITY'] ?? 1),
                ],
                
                'deepseek' => [
                    'enabled' => filter_var($_ENV['DEEPSEEK_ENABLED'] ?? true, FILTER_VALIDATE_BOOLEAN),
                    'api_key' => $_ENV['DEEPSEEK_API_KEY'] ?? '',
                    'base_url' => $_ENV['DEEPSEEK_BASE_URL'] ?? 'https://api.deepseek.com/v1/',
                    'models' => [
                        'chat' => $_ENV['DEEPSEEK_CHAT_MODEL'] ?? 'deepseek-chat',
                        'embedding' => $_ENV['DEEPSEEK_EMBEDDING_MODEL'] ?? null,
                        'summary' => $_ENV['DEEPSEEK_SUMMARY_MODEL'] ?? 'deepseek-chat',
                    ],
                    'timeout' => (int) ($_ENV['DEEPSEEK_TIMEOUT'] ?? 60),
                    'max_retries' => (int) ($_ENV['DEEPSEEK_MAX_RETRIES'] ?? 3),
                    'priority' => (int) ($_ENV['DEEPSEEK_PRIORITY'] ?? 2),
                ],
                
                'gemini' => [
                    'enabled' => filter_var($_ENV['GEMINI_ENABLED'] ?? true, FILTER_VALIDATE_BOOLEAN),
                    'api_key' => $_ENV['GEMINI_API_KEY'] ?? '',
                    'base_url' => $_ENV['GEMINI_BASE_URL'] ?? 'https://generativelanguage.googleapis.com/v1beta/',
                    'models' => [
                        'chat' => $_ENV['GEMINI_CHAT_MODEL'] ?? 'gemini-pro',
                        'embedding' => $_ENV['GEMINI_EMBEDDING_MODEL'] ?? 'embedding-001',
                        'summary' => $_ENV['GEMINI_SUMMARY_MODEL'] ?? 'gemini-pro',
                    ],
                    'timeout' => (int) ($_ENV['GEMINI_TIMEOUT'] ?? 60),
                    'max_retries' => (int) ($_ENV['GEMINI_MAX_RETRIES'] ?? 3),
                    'priority' => (int) ($_ENV['GEMINI_PRIORITY'] ?? 3),
                ],
                
                // Configuration for fallback and load balancing
                'strategy' => $_ENV['AI_STRATEGY'] ?? 'fallback', // fallback, round_robin, weighted
                'default_provider' => $_ENV['DEFAULT_AI_PROVIDER'] ?? 'openai',
                'embedding_provider' => $_ENV['EMBEDDING_PROVIDER'] ?? 'openai',
            ],
            
            'context' => [
                'max_tokens' => (int) ($_ENV['MAX_CONTEXT_TOKENS'] ?? 128000),
                'summary_threshold' => (int) ($_ENV['SUMMARY_THRESHOLD'] ?? 32000),
                'emergency_threshold' => (int) ($_ENV['EMERGENCY_THRESHOLD'] ?? 100000),
                'embedding_dimensions' => (int) ($_ENV['EMBEDDING_DIMENSIONS'] ?? 1536),
            ],
            
            'security' => [
                'jwt_secret' => $_ENV['JWT_SECRET'] ?? 'your-secret-key-change-in-production',
                'jwt_expire_hours' => (int) ($_ENV['JWT_EXPIRE_HOURS'] ?? 24),
                'rate_limit' => [
                    'requests' => (int) ($_ENV['RATE_LIMIT_REQUESTS'] ?? 1000),
                    'period' => (int) ($_ENV['RATE_LIMIT_PERIOD'] ?? 3600),
                ],
            ],
            
            'system' => [
                'log_level' => $_ENV['LOG_LEVEL'] ?? 'INFO',
                'enable_health_checks' => filter_var($_ENV['ENABLE_HEALTH_CHECKS'] ?? true, FILTER_VALIDATE_BOOLEAN),
                'timezone' => $_ENV['TIMEZONE'] ?? 'UTC',
                'debug' => filter_var($_ENV['DEBUG'] ?? false, FILTER_VALIDATE_BOOLEAN),
            ],
            
            'cache' => [
                'embedding_ttl_hours' => (int) ($_ENV['EMBEDDING_CACHE_TTL_HOURS'] ?? 24),
                'max_cache_size' => (int) ($_ENV['MAX_CACHE_SIZE'] ?? 10000),
                'cache_cleanup_frequency' => (int) ($_ENV['CACHE_CLEANUP_FREQUENCY'] ?? 3600),
            ],
        ];
    }
    
    public static function get(string $key, mixed $default = null): mixed
    {
        self::load();
        
        $keys = explode('.', $key);
        $value = self::$config;
        
        foreach ($keys as $k) {
            if (!isset($value[$k])) {
                return $default;
            }
            $value = $value[$k];
        }
        
        return $value;
    }
    
    public static function all(): array
    {
        self::load();
        return self::$config;
    }
    
    public static function getEnabledProviders(): array
    {
        $providers = self::get('ai_providers');
        $enabled = [];
        
        foreach ($providers as $name => $config) {
            if ($name === 'strategy' || $name === 'default_provider' || $name === 'embedding_provider') {
                continue;
            }
            
            if ($config['enabled'] && !empty($config['api_key'])) {
                $enabled[$name] = $config;
            }
        }
        
        // Sort by priority
        uasort($enabled, function($a, $b) {
            return $a['priority'] <=> $b['priority'];
        });
        
        return $enabled;
    }
    
    public static function getProviderConfig(string $provider): ?array
    {
        $providers = self::get('ai_providers');
        return $providers[$provider] ?? null;
    }
}