<?php
$file = 'routes/api.php';
$content = file_get_contents($file);

// 128K endpoint ekle
$newRoute = '
// ============================================
// 128K OPTIMIZED ENDPOINTS
// ============================================

// 128K optimized chat
if ($method === "POST" && preg_match("#^/api/conversations/(\d+)/message-128k$#", $path, $matches)) {
    $routeFound = true;
    
    // Get data
    $data = json_decode(file_get_contents("php://input"), true);
    $conversationId = $matches[1];
    $message = $data["message"] ?? "";
    $userId = $data["user_id"] ?? 1; // Default or from JWT
    
    if (empty($message)) {
        http_response_code(400);
        echo json_encode(["error" => "Message is required"]);
        exit;
    }
    
    // Handle with 128K optimization
    $result = $conversationController->handleMessage128K($conversationId, $message, $userId);
    
    if ($result["success"]) {
        echo json_encode($result);
    } else {
        // Fallback to normal endpoint
        $fallbackResult = $conversationController->handleMessage($conversationId, $message, $userId);
        echo json_encode(array_merge($fallbackResult, ["128k_fallback" => true]));
    }
    
    exit;
}

// 128K Token analysis
if ($method === "POST" && $path === "/api/analyze-tokens") {
    $routeFound = true;
    
    $data = json_decode(file_get_contents("php://input"), true);
    $text = $data["text"] ?? "";
    
    if (empty($text)) {
        http_response_code(400);
        echo json_encode(["error" => "Text is required"]);
        exit;
    }
    
    $tokenManager = new \App\Services\TokenManager();
    $analysis = $tokenManager->quickAnalyze($text);
    $optimization = $tokenManager->optimizeFor128K($text, "Analysis query");
    
    echo json_encode([
        "analysis" => $analysis,
        "optimization" => $optimization,
        "recommendation" => $analysis["needs_attention"] ? "Use 128K optimized endpoint" : "Normal endpoint is fine"
    ]);
    
    exit;
}';

// Routes dosyasına ekle (diğer route'lardan önce)
$insertPoint = '// API Routing';
if (strpos($content, '// 128K OPTIMIZED ENDPOINTS') === false) {
    $content = str_replace(
        '// API Routing',
        '// API Routing' . $newRoute,
        $content
    );
    echo "✅ 128K API routes eklendi\n";
}

file_put_contents($file, $content);
echo "🎉 API routes güncellendi!\n";
