<?php
$file = 'src/controllers/ConversationController.php';
$content = file_get_contents($file);

// 1. TokenManager property ekle (zaten varsa kontrol et)
if (strpos($content, 'private $tokenManager;') === false) {
    // Property'leri bul ve TokenManager ekle
    $pattern = '/class ConversationController\s*\{(.*?)(private \\$[^;]+;)/s';
    if (preg_match($pattern, $content, $matches)) {
        $replacement = "class ConversationController\n{\n$matches[1]private \$tokenManager;\n$matches[2]";
        $content = preg_replace($pattern, $replacement, $content, 1);
        echo "✅ TokenManager property eklendi\n";
    }
}

// 2. __construct'a TokenManager ekle
if (strpos($content, 'new \\App\\Services\\TokenManager()') === false) {
    // __construct içinde TokenManager initialization ekle
    $content = str_replace(
        '$this->contextBuilder = new ContextBuilder();',
        "\$this->contextBuilder = new ContextBuilder();\n        \$this->tokenManager = new \\App\\Services\\TokenManager();",
        $content
    );
    echo "✅ TokenManager __construct'a eklendi\n";
}

// 3. 128K handle method'u ekle
$newMethod = '

    /**
     * 128K Token optimized message handling
     */
    public function handleMessage128K($conversationId, $message, $userId) {
        try {
            // Debug
            error_log("🚀 128K Processing started for conversation: " . $conversationId);
            
            // Get conversation
            $conversation = Conversation::where("id", $conversationId)
                ->where("user_id", $userId)
                ->first();
                
            if (!$conversation) {
                return [
                    "success" => false,
                    "error" => "Conversation not found"
                ];
            }
            
            // Get messages
            $messages = Message::where("conversation_id", $conversationId)
                ->orderBy("created_at", "asc")
                ->get();
            
            // Build context
            $context = "";
            foreach ($messages as $msg) {
                $prefix = $msg->role === "user" ? "Kullanıcı: " : "AI: ";
                $context .= $prefix . $msg->content . "\n";
            }
            
            // 128K OPTIMIZATION
            $optimized = $this->tokenManager->optimizeFor128K($context, $message);
            
            // Log optimization strategy
            error_log("📊 128K Strategy: " . $optimized["strategy"] . 
                     ", Tokens: " . ($optimized["tokens"] ?? "N/A") .
                     ", Chunks: " . ($optimized["chunk_count"] ?? 1));
            
            // Prepare content for AI
            $contentForAI = "";
            if (isset($optimized["chunks"])) {
                // Chunked processing
                $contentForAI = implode("\n\n[Bölüm Devamı]\n\n", $optimized["chunks"]);
            } else {
                // Single content
                $contentForAI = $optimized["content"] ?? $context;
            }
            
            // Call AI (simplified - you need to implement your AI call)
            $aiResponse = "AI Response - 128K optimized. Strategy: " . $optimized["strategy"];
            
            // Save response
            Message::create([
                "conversation_id" => $conversationId,
                "role" => "assistant",
                "content" => $aiResponse,
                "token_count" => $this->tokenCalculator->estimate($aiResponse)
            ]);
            
            return [
                "success" => true,
                "response" => $aiResponse,
                "token_info" => [
                    "strategy" => $optimized["strategy"],
                    "tokens_used" => $optimized["tokens"] ?? null,
                    "chunks" => $optimized["chunk_count"] ?? 1,
                    "compression_rate" => $optimized["compression_rate"] ?? null,
                    "optimized" => true,
                    "timestamp" => date("Y-m-d H:i:s")
                ]
            ];
            
        } catch (\\Exception $e) {
            error_log("❌ 128K Error: " . $e->getMessage() . " in " . $e->getFile() . ":" . $e->getLine());
            
            // Fallback to normal handling
            return [
                "success" => false,
                "error" => "128K processing failed: " . $e->getMessage(),
                "fallback_available" => true,
                "timestamp" => date("Y-m-d H:i:s")
            ];
        }
    }';

// Method'u ekle (eğer yoksa)
if (strpos($content, 'function handleMessage128K') === false) {
    // Son }'dan önce ekle
    $content = preg_replace('/\s*\}$/', $newMethod . "\n}", $content);
    echo "✅ 128K handle method eklendi\n";
}

file_put_contents($file, $content);
echo "🎉 ConversationController güncellendi!\n";
