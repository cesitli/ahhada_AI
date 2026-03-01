<?php

namespace App\Services;

use App\Config\Config;
use App\Utils\Logger;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\RequestException;

class AIProviderManager
{
    private $config;
    private $httpClient;
    private $activeProvider;
    private $providers = [];
    
    public function __construct($provider = null)
    {
        $this->config = Config::getInstance();
        $this->httpClient = new Client([
            'timeout' => 60.0,
            'connect_timeout' => 10.0,
        ]);
        
        // Provider'ları yükle
        $this->loadProviders();
        
        // Aktif provider'ı belirle
        $this->activeProvider = $provider ?: $this->config->get('ai.default_provider', 'openai');
        
        Logger::info("AIProviderManager initialized", [
            'active_provider' => $this->activeProvider,
            'available_providers' => array_keys($this->providers)
        ]);
    }
    
    /**
     * Tüm provider'ları yükle
     */
    private function loadProviders()
    {
        $this->providers = [
            'openai' => [
                'name' => 'OpenAI',
                'models' => [
                    'gpt-4o' => ['max_tokens' => 128000, 'cost_per_1k_input' => 0.005, 'cost_per_1k_output' => 0.015],
                    'gpt-4o-mini' => ['max_tokens' => 128000, 'cost_per_1k_input' => 0.00015, 'cost_per_1k_output' => 0.0006],
                    'gpt-4-turbo' => ['max_tokens' => 128000, 'cost_per_1k_input' => 0.01, 'cost_per_1k_output' => 0.03],
                    'o1-preview' => ['max_tokens' => 128000, 'cost_per_1k_input' => 0.015, 'cost_per_1k_output' => 0.06],
                    'o1-mini' => ['max_tokens' => 128000, 'cost_per_1k_input' => 0.003, 'cost_per_1k_output' => 0.012]
                ],
                'base_url' => 'https://api.openai.com/v1',
                'api_key' => $this->config->get('ai.openai_api_key'),
                'headers' => [
                    'Content-Type' => 'application/json',
                ]
            ],
            'deepseek' => [
                'name' => 'DeepSeek',
                'models' => [
                    'deepseek-chat' => ['max_tokens' => 64000, 'cost_per_1k_input' => 0.00014, 'cost_per_1k_output' => 0.00028],
                    'deepseek-coder' => ['max_tokens' => 64000, 'cost_per_1k_input' => 0.00014, 'cost_per_1k_output' => 0.00028]
                ],
                'base_url' => 'https://api.deepseek.com',
                'api_key' => $this->config->get('ai.deepseek_api_key'),
                'headers' => [
                    'Content-Type' => 'application/json',
                ]
            ],
            'gemini' => [
                'name' => 'Google Gemini',
                'models' => [
                    'gemini-1.5-pro' => ['max_tokens' => 1000000, 'cost_per_1k_input' => 0.00375, 'cost_per_1k_output' => 0.015],
                    'gemini-1.5-flash' => ['max_tokens' => 1000000, 'cost_per_1k_input' => 0.000075, 'cost_per_1k_output' => 0.0003]
                ],
                'base_url' => 'https://generativelanguage.googleapis.com/v1beta',
                'api_key' => $this->config->get('ai.gemini_api_key'),
                'headers' => [
                    'Content-Type' => 'application/json',
                ]
            ],
            'anthropic' => [
                'name' => 'Anthropic Claude',
                'models' => [
                    'claude-3-5-sonnet' => ['max_tokens' => 200000, 'cost_per_1k_input' => 0.003, 'cost_per_1k_output' => 0.015],
                    'claude-3-opus' => ['max_tokens' => 200000, 'cost_per_1k_input' => 0.015, 'cost_per_1k_output' => 0.075]
                ],
                'base_url' => 'https://api.anthropic.com/v1',
                'api_key' => $this->config->get('ai.anthropic_api_key'),
                'headers' => [
                    'Content-Type' => 'application/json',
                    'anthropic-version' => '2023-06-01',
                    'x-api-key' => $this->config->get('ai.anthropic_api_key')
                ]
            ]
        ];
        
        // API key kontrolü
        foreach ($this->providers as $providerKey => $provider) {
            if (empty($provider['api_key'])) {
                unset($this->providers[$providerKey]);
                Logger::warning("Provider disabled - missing API key", ['provider' => $providerKey]);
            }
        }
    }
    
    /**
     * AI ile konuşma
     */
    public function chat($context, $message, $options = [])
    {
        $conversationId = $options['conversation_id'] ?? null;
        $model = $options['model'] ?? $this->config->get('ai.default_model', 'gpt-4o');
        $provider = $options['provider'] ?? $this->activeProvider;
        $temperature = $options['temperature'] ?? 0.7;
        $maxTokens = $options['max_tokens'] ?? 4000;
        
        Logger::info("AI Chat Request", [
            'provider' => $provider,
            'model' => $model,
            'conversation_id' => $conversationId,
            'context_tokens' => (new TokenManager())->countTokens($context),
            'message_tokens' => (new TokenManager())->countTokens($message)
        ]);
        
        // Provider kontrolü
        if (!isset($this->providers[$provider])) {
            throw new \Exception("Provider not available: {$provider}");
        }
        
        // Model kontrolü
        $providerConfig = $this->providers[$provider];
        if (!isset($providerConfig['models'][$model])) {
            throw new \Exception("Model not supported: {$model} for provider {$provider}");
        }
        
        // Provider'a özel çağrı
        switch ($provider) {
            case 'openai':
                return $this->callOpenAI($context, $message, $model, $providerConfig, $temperature, $maxTokens);
                
            case 'deepseek':
                return $this->callDeepSeek($context, $message, $model, $providerConfig, $temperature, $maxTokens);
                
            case 'gemini':
                return $this->callGemini($context, $message, $model, $providerConfig, $temperature, $maxTokens);
                
            case 'anthropic':
                return $this->callAnthropic($context, $message, $model, $providerConfig, $temperature, $maxTokens);
                
            default:
                throw new \Exception("Unsupported provider: {$provider}");
        }
    }
    
    /**
     * OpenAI çağrısı
     */
    private function callOpenAI($context, $message, $model, $providerConfig, $temperature, $maxTokens)
    {
        $messages = [];
        
        // Context'i system message olarak ekle
        if (!empty($context)) {
            $messages[] = [
                'role' => 'system',
                'content' => $context
            ];
        }
        
        // User message
        $messages[] = [
            'role' => 'user',
            'content' => $message
        ];
        
        $payload = [
            'model' => $model,
            'messages' => $messages,
            'temperature' => $temperature,
            'max_tokens' => $maxTokens,
            'stream' => false
        ];
        
        try {
            $response = $this->httpClient->post($providerConfig['base_url'] . '/chat/completions', [
                'headers' => array_merge($providerConfig['headers'], [
                    'Authorization' => 'Bearer ' . $providerConfig['api_key']
                ]),
                'json' => $payload
            ]);
            
            $data = json_decode($response->getBody(), true);
            
            if (isset($data['choices'][0]['message']['content'])) {
                return $data['choices'][0]['message']['content'];
            }
            
            throw new \Exception("Invalid response from OpenAI");
            
        } catch (RequestException $e) {
            Logger::error("OpenAI API Error", [
                'error' => $e->getMessage(),
                'model' => $model
            ]);
            throw new \Exception("OpenAI API Error: " . $e->getMessage());
        }
    }
    
    /**
     * DeepSeek çağrısı
     */
    private function callDeepSeek($context, $message, $model, $providerConfig, $temperature, $maxTokens)
    {
        $messages = [];
        
        // Context'i system message olarak ekle
        if (!empty($context)) {
            $messages[] = [
                'role' => 'system',
                'content' => $context
            ];
        }
        
        // User message
        $messages[] = [
            'role' => 'user',
            'content' => $message
        ];
        
        $payload = [
            'model' => $model,
            'messages' => $messages,
            'temperature' => $temperature,
            'max_tokens' => $maxTokens,
            'stream' => false
        ];
        
        try {
            $response = $this->httpClient->post($providerConfig['base_url'] . '/chat/completions', [
                'headers' => array_merge($providerConfig['headers'], [
                    'Authorization' => 'Bearer ' . $providerConfig['api_key']
                ]),
                'json' => $payload
            ]);
            
            $data = json_decode($response->getBody(), true);
            
            if (isset($data['choices'][0]['message']['content'])) {
                return $data['choices'][0]['message']['content'];
            }
            
            throw new \Exception("Invalid response from DeepSeek");
            
        } catch (RequestException $e) {
            Logger::error("DeepSeek API Error", [
                'error' => $e->getMessage(),
                'model' => $model
            ]);
            
            // Fallback to OpenAI
            if ($this->providers['openai']) {
                Logger::info("Falling back to OpenAI");
                return $this->callOpenAI($context, $message, 'gpt-4o-mini', $this->providers['openai'], $temperature, $maxTokens);
            }
            
            throw new \Exception("DeepSeek API Error: " . $e->getMessage());
        }
    }
    
    /**
     * Gemini çağrısı
     */
    private function callGemini($context, $message, $model, $providerConfig, $temperature, $maxTokens)
    {
        // Gemini için context ve message birleştirme
        $fullPrompt = $context . "\n\nUser: " . $message . "\n\nAssistant:";
        
        $payload = [
            'contents' => [
                [
                    'parts' => [
                        ['text' => $fullPrompt]
                    ]
                ]
            ],
            'generationConfig' => [
                'temperature' => $temperature,
                'maxOutputTokens' => $maxTokens,
                'topP' => 0.95,
                'topK' => 40
            ]
        ];
        
        try {
            $response = $this->httpClient->post(
                $providerConfig['base_url'] . "/models/{$model}:generateContent?key=" . $providerConfig['api_key'],
                [
                    'headers' => $providerConfig['headers'],
                    'json' => $payload
                ]
            );
            
            $data = json_decode($response->getBody(), true);
            
            if (isset($data['candidates'][0]['content']['parts'][0]['text'])) {
                return $data['candidates'][0]['content']['parts'][0]['text'];
            }
            
            throw new \Exception("Invalid response from Gemini");
            
        } catch (RequestException $e) {
            Logger::error("Gemini API Error", [
                'error' => $e->getMessage(),
                'model' => $model
            ]);
            throw new \Exception("Gemini API Error: " . $e->getMessage());
        }
    }
    
    /**
     * Anthropic Claude çağrısı
     */
    private function callAnthropic($context, $message, $model, $providerConfig, $temperature, $maxTokens)
    {
        // Anthropic için system prompt
        $systemPrompt = $context;
        $userMessage = $message;
        
        $payload = [
            'model' => $model,
            'max_tokens' => $maxTokens,
            'temperature' => $temperature,
            'system' => $systemPrompt,
            'messages' => [
                [
                    'role' => 'user',
                    'content' => $userMessage
                ]
            ]
        ];
        
        try {
            $response = $this->httpClient->post($providerConfig['base_url'] . '/messages', [
                'headers' => $providerConfig['headers'],
                'json' => $payload
            ]);
            
            $data = json_decode($response->getBody(), true);
            
            if (isset($data['content'][0]['text'])) {
                return $data['content'][0]['text'];
            }
            
            throw new \Exception("Invalid response from Anthropic");
            
        } catch (RequestException $e) {
            Logger::error("Anthropic API Error", [
                'error' => $e->getMessage(),
                'model' => $model
            ]);
            throw new \Exception("Anthropic API Error: " . $e->getMessage());
        }
    }
    
    /**
     * Get available providers
     */
    public function getAvailableProviders()
    {
        $available = [];
        
        foreach ($this->providers as $key => $config) {
            $available[$key] = [
                'name' => $config['name'],
                'models' => array_keys($config['models'])
            ];
        }
        
        return $available;
    }
    
    /**
     * Get provider info
     */
    public function getProviderInfo($provider)
    {
        if (!isset($this->providers[$provider])) {
            return null;
        }
        
        $config = $this->providers[$provider];
        
        return [
            'name' => $config['name'],
            'models' => $config['models'],
            'has_api_key' => !empty($config['api_key'])
        ];
    }
    
    /**
     * Estimate cost
     */
    public function estimateCost($provider, $model, $inputTokens, $outputTokens)
    {
        if (!isset($this->providers[$provider]['models'][$model])) {
            return 0;
        }
        
        $modelConfig = $this->providers[$provider]['models'][$model];
        
        $inputCost = ($inputTokens / 1000) * $modelConfig['cost_per_1k_input'];
        $outputCost = ($outputTokens / 1000) * $modelConfig['cost_per_1k_output'];
        
        return [
            'input_tokens' => $inputTokens,
            'output_tokens' => $outputTokens,
            'input_cost' => round($inputCost, 6),
            'output_cost' => round($outputCost, 6),
            'total_cost' => round($inputCost + $outputCost, 6),
            'currency' => 'USD'
        ];
    }
    
    /**
     * Switch active provider
     */
    public function setActiveProvider($provider)
    {
        if (!isset($this->providers[$provider])) {
            throw new \Exception("Provider not available: {$provider}");
        }
        
        $this->activeProvider = $provider;
        Logger::info("Active provider changed", ['provider' => $provider]);
        
        return $this;
    }
    
    /**
     * Test provider connectivity
     */
    public function testProvider($provider)
    {
        if (!isset($this->providers[$provider])) {
            return [
                'success' => false,
                'error' => 'Provider not configured'
            ];
        }
        
        try {
            $testMessage = "Hello, this is a test message.";
            $testContext = "You are a helpful assistant.";
            
            // Kısa bir test çağrısı yap
            $response = $this->chat($testContext, $testMessage, [
                'provider' => $provider,
                'model' => array_keys($this->providers[$provider]['models'])[0],
                'max_tokens' => 50
            ]);
            
            return [
                'success' => true,
                'provider' => $provider,
                'response' => $response,
                'timestamp' => date('Y-m-d H:i:s')
            ];
            
        } catch (\Exception $e) {
            return [
                'success' => false,
                'provider' => $provider,
                'error' => $e->getMessage(),
                'timestamp' => date('Y-m-d H:i:s')
            ];
        }
    }
}