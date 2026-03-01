<?php

use App\Controllers\ConversationController;
use App\Controllers\AIController;
use App\Middleware\AuthMiddleware;

// ==================== AI PROVIDER ENDPOINTS ====================

// Get available providers
$router->get('/ai/providers', function($request, $response) {
    $auth = new AuthMiddleware();
    if (!$auth->validateToken($request)) {
        return $response->json(['error' => 'Unauthorized'], 401);
    }
    
    $aiManager = new \App\Services\AIProviderManager();
    $providers = $aiManager->getAvailableProviders();
    
    return $response->json([
        'success' => true,
        'providers' => $providers,
        'default_provider' => \App\Config\Config::getInstance()->get('ai.default_provider', 'openai')
    ]);
});

// Test provider connectivity
$router->post('/ai/providers/test', function($request, $response) {
    $auth = new AuthMiddleware();
    if (!$auth->validateToken($request)) {
        return $response->json(['error' => 'Unauthorized'], 401);
    }
    
    $data = $request->getJson();
    $provider = $data['provider'] ?? 'openai';
    
    $aiManager = new \App\Services\AIProviderManager();
    $testResult = $aiManager->testProvider($provider);
    
    return $response->json($testResult);
});

// Switch active provider for conversation
$router->post('/conversations/{id}/switch-provider', function($request, $response, $args) {
    $auth = new AuthMiddleware();
    if (!$auth->validateToken($request)) {
        return $response->json(['error' => 'Unauthorized'], 401);
    }
    
    $data = $request->getJson();
    $conversationId = $args['id'];
    $provider = $data['provider'] ?? 'openai';
    $model = $data['model'] ?? null;
    
    $conversation = \App\Models\Conversation::find($conversationId);
    if (!$conversation) {
        return $response->json(['error' => 'Conversation not found'], 404);
    }
    
    // Provider'ı kontrol et
    $aiManager = new \App\Services\AIProviderManager();
    $providers = $aiManager->getAvailableProviders();
    
    if (!isset($providers[$provider])) {
        return $response->json(['error' => 'Provider not available'], 400);
    }
    
    // Model kontrolü
    if ($model && !in_array($model, $providers[$provider]['models'])) {
        return $response->json(['error' => 'Model not available for this provider'], 400);
    }
    
    // Conversation'ı güncelle
    $updateData = ['ai_provider' => $provider];
    if ($model) {
        $updateData['model'] = $model;
    }
    
    $conversation->update($updateData);
    
    return $response->json([
        'success' => true,
        'message' => 'Provider switched successfully',
        'conversation' => [
            'id' => $conversation->id,
            'ai_provider' => $conversation->ai_provider,
            'model' => $conversation->model
        ]
    ]);
});

// Get provider info
$router->get('/ai/providers/{provider}', function($request, $response, $args) {
    $auth = new AuthMiddleware();
    if (!$auth->validateToken($request)) {
        return $response->json(['error' => 'Unauthorized'], 401);
    }
    
    $aiManager = new \App\Services\AIProviderManager();
    $providerInfo = $aiManager->getProviderInfo($args['provider']);
    
    if (!$providerInfo) {
        return $response->json(['error' => 'Provider not found'], 404);
    }
    
    return $response->json([
        'success' => true,
        'provider' => $args['provider'],
        'info' => $providerInfo
    ]);
});

// Estimate cost
$router->post('/ai/estimate-cost', function($request, $response) {
    $auth = new AuthMiddleware();
    if (!$auth->validateToken($request)) {
        return $response->json(['error' => 'Unauthorized'], 401);
    }
    
    $data = $request->getJson();
    $provider = $data['provider'] ?? 'openai';
    $model = $data['model'] ?? 'gpt-4o';
    $inputTokens = $data['input_tokens'] ?? 1000;
    $outputTokens = $data['output_tokens'] ?? 500;
    
    $aiManager = new \App\Services\AIProviderManager();
    $costEstimate = $aiManager->estimateCost($provider, $model, $inputTokens, $outputTokens);
    
    return $response->json([
        'success' => true,
        'estimate' => $costEstimate
    ]);
});

// ==================== 128K ENHANCED ENDPOINTS (Multi-Provider) ====================

// Enhanced 128K message with provider selection
$router->post('/conversations/{id}/message-128k-enhanced', function($request, $response, $args) {
    $auth = new AuthMiddleware();
    if (!$auth->validateToken($request)) {
        return $response->json(['error' => 'Unauthorized'], 401);
    }
    
    $data = $request->getJson();
    $conversationId = $args['id'];
    $userId = $auth->getUserId($request);
    
    // Provider ve model seçimi
    $provider = $data['provider'] ?? null;
    $model = $data['model'] ?? null;
    $temperature = $data['temperature'] ?? 0.7;
    $maxTokens = $data['max_tokens'] ?? 4000;
    
    // Conversation'dan provider/model al veya varsayılanı kullan
    $conversation = \App\Models\Conversation::find($conversationId);
    if (!$conversation) {
        return $response->json(['error' => 'Conversation not found'], 404);
    }
    
    $finalProvider = $provider ?: $conversation->ai_provider ?: 'openai';
    $finalModel = $model ?: $conversation->model ?: 'gpt-4o';
    
    // Controller'ı oluştur
    $controller = new ConversationController();
    
    // AI options
    $aiOptions = [
        'provider' => $finalProvider,
        'model' => $finalModel,
        'temperature' => $temperature,
        'max_tokens' => $maxTokens,
        'conversation_id' => $conversationId
    ];
    
    // Enhanced handler'ı çağır
    $result = $controller->handleMessage128KEnhanced(
        $conversationId,
        $data['message'],
        $userId,
        $aiOptions
    );
    
    // Conversation'ı güncelle (provider/model değiştiyse)
    if ($provider && $conversation->ai_provider != $provider) {
        $conversation->update(['ai_provider' => $provider]);
    }
    if ($model && $conversation->model != $model) {
        $conversation->update(['model' => $model]);
    }
    
    return $response->json($result);
});

// Direct AI call with provider selection
$router->post('/ai/chat', function($request, $response) {
    $auth = new AuthMiddleware();
    if (!$auth->validateToken($request)) {
        return $response->json(['error' => 'Unauthorized'], 401);
    }
    
    $data = $request->getJson();
    $message = $data['message'] ?? '';
    $context = $data['context'] ?? '';
    $provider = $data['provider'] ?? 'openai';
    $model = $data['model'] ?? null;
    $temperature = $data['temperature'] ?? 0.7;
    $maxTokens = $data['max_tokens'] ?? 4000;
    
    if (empty($message)) {
        return $response->json(['error' => 'Message is required'], 400);
    }
    
    try {
        $aiManager = new \App\Services\AIProviderManager();
        
        // Provider ve model kontrolü
        $providers = $aiManager->getAvailableProviders();
        if (!isset($providers[$provider])) {
            return $response->json(['error' => 'Provider not available'], 400);
        }
        
        // Model belirtilmediyse, provider'ın ilk modelini kullan
        if (!$model) {
            $model = array_keys($providers[$provider]['models'])[0];
        }
        
        // AI çağrısı
        $aiResponse = $aiManager->chat($context, $message, [
            'provider' => $provider,
            'model' => $model,
            'temperature' => $temperature,
            'max_tokens' => $maxTokens
        ]);
        
        // Token hesaplama
        $tokenManager = new \App\Services\TokenManager();
        $inputTokens = $tokenManager->countTokens($context . $message);
        $outputTokens = $tokenManager->countTokens($aiResponse);
        
        // Maliyet tahmini
        $costEstimate = $aiManager->estimateCost($provider, $model, $inputTokens, $outputTokens);
        
        return $response->json([
            'success' => true,
            'response' => $aiResponse,
            'tokens' => [
                'input' => $inputTokens,
                'output' => $outputTokens,
                'total' => $inputTokens + $outputTokens
            ],
            'cost_estimate' => $costEstimate,
            'provider' => $provider,
            'model' => $model
        ]);
        
    } catch (\Exception $e) {
        return $response->json([
            'success' => false,
            'error' => $e->getMessage()
        ], 500);
    }
});

// Batch test all providers
$router->get('/ai/providers/test-all', function($request, $response) {
    $auth = new AuthMiddleware();
    if (!$auth->validateToken($request)) {
        return $response->json(['error' => 'Unauthorized'], 401);
    }
    
    $aiManager = new \App\Services\AIProviderManager();
    $providers = $aiManager->getAvailableProviders();
    
    $results = [];
    foreach (array_keys($providers) as $provider) {
        $results[$provider] = $aiManager->testProvider($provider);
    }
    
    return $response->json([
        'success' => true,
        'results' => $results,
        'timestamp' => date('Y-m-d H:i:s')
    ]);
});

// ==================== CONVERSATION ENDPOINTS (Updated) ====================

// Create conversation with provider selection
$router->post('/conversations', function($request, $response) {
    $auth = new AuthMiddleware();
    if (!$auth->validateToken($request)) {
        return $response->json(['error' => 'Unauthorized'], 401);
    }
    
    $data = $request->getJson();
    $userId = $auth->getUserId($request);
    
    // Provider ve model kontrolü
    $provider = $data['ai_provider'] ?? 'openai';
    $model = $data['model'] ?? 'gpt-4o';
    
    $aiManager = new \App\Services\AIProviderManager();
    $providers = $aiManager->getAvailableProviders();
    
    if (!isset($providers[$provider])) {
        return $response->json(['error' => 'Provider not available'], 400);
    }
    
    if (!in_array($model, $providers[$provider]['models'])) {
        return $response->json(['error' => 'Model not available for this provider'], 400);
    }
    
    // Conversation oluştur
    $conversation = \App\Models\Conversation::create([
        'user_id' => $userId,
        'title' => $data['title'] ?? 'New Conversation',
        'ai_provider' => $provider,
        'model' => $model,
        'metadata' => json_encode([
            'created_with_provider' => $provider,
            'created_with_model' => $model,
            'created_at' => date('Y-m-d H:i:s')
        ])
    ]);
    
    return $response->json([
        'success' => true,
        'conversation' => $conversation
    ]);
});

// Get conversation with provider info
$router->get('/conversations/{id}', function($request, $response, $args) {
    $auth = new AuthMiddleware();
    if (!$auth->validateToken($request)) {
        return $response->json(['error' => 'Unauthorized'], 401);
    }
    
    $conversation = \App\Models\Conversation::find($args['id']);
    if (!$conversation) {
        return $response->json(['error' => 'Conversation not found'], 404);
    }
    
    // Provider info ekle
    $aiManager = new \App\Services\AIProviderManager();
    $providerInfo = $aiManager->getProviderInfo($conversation->ai_provider);
    
    $conversationData = $conversation->toArray();
    $conversationData['provider_info'] = $providerInfo;
    
    return $response->json([
        'success' => true,
        'conversation' => $conversationData
    ]);
});