<?php
// chat.php - AI Sohbet Arayüzü
require_once __DIR__ . '/vendor/autoload.php';

$dotenv = Dotenv\Dotenv::createImmutable(__DIR__);
$dotenv->safeLoad();

// Session başlat
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Kullanıcı ID'si (session'dan veya cookie'den)
if (!isset($_SESSION['user_id'])) {
    $_SESSION['user_id'] = 'user_' . uniqid();
    $_SESSION['username'] = 'Misafir_' . substr(uniqid(), -6);
}

// Database bağlantısı
$dsn = "pgsql:host=" . ($_ENV['DB_HOST'] ?? 'localhost') . 
       ";port=" . ($_ENV['DB_PORT'] ?? '5432') . 
       ";dbname=" . ($_ENV['DB_NAME'] ?? 'ahhada_s1');
try {
    $pdo = new PDO($dsn, 
                   $_ENV['DB_USERNAME'] ?? 'ahhada_s1', 
                   $_ENV['DB_PASS'] ?? '', 
                   [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
} catch (Exception $e) {
    $db_error = $e->getMessage();
}

// Yeni konuşma başlat
if (isset($_GET['new'])) {
    unset($_SESSION['current_conversation_id']);
}

// Mevcut konuşmayı getir
$current_conversation_id = $_SESSION['current_conversation_id'] ?? null;
$conversation = null;
$messages = [];

if ($current_conversation_id && isset($pdo)) {
    try {
        $stmt = $pdo->prepare("SELECT * FROM conversations WHERE id = ?");
        $stmt->execute([$current_conversation_id]);
        $conversation = $stmt->fetch();
        
        $stmt = $pdo->prepare("SELECT * FROM messages WHERE conversation_id = ? ORDER BY created_at ASC");
        $stmt->execute([$current_conversation_id]);
        $messages = $stmt->fetchAll();
    } catch (Exception $e) {
        // Hata durumunda
    }
}

// Kullanıcının tüm konuşmalarını listele
$user_conversations = [];
if (isset($pdo)) {
    try {
        $stmt = $pdo->prepare("SELECT * FROM conversations WHERE user_id = ? ORDER BY created_at DESC LIMIT 10");
        $stmt->execute([$_SESSION['user_id']]);
        $user_conversations = $stmt->fetchAll();
    } catch (Exception $e) {
        // Hata
    }
}
?>
<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>AI Sohbet - Ahhada AI</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); min-height: 100vh; }
        
        .chat-container { display: flex; max-width: 1400px; margin: 0 auto; min-height: 100vh; background: white; box-shadow: 0 20px 60px rgba(0,0,0,0.3); }
        
        /* Sidebar */
        .sidebar { width: 300px; background: #2c3e50; color: white; padding: 25px; overflow-y: auto; }
        .sidebar-header { margin-bottom: 30px; }
        .sidebar-header h2 { font-size: 24px; margin-bottom: 10px; }
        .user-info { background: rgba(255,255,255,0.1); padding: 15px; border-radius: 10px; margin-bottom: 25px; }
        .new-chat-btn { display: block; width: 100%; padding: 15px; background: #3498db; color: white; border: none; border-radius: 10px; font-size: 16px; cursor: pointer; margin-bottom: 25px; text-align: center; text-decoration: none; }
        .conversation-list { margin-top: 20px; }
        .conversation-item { padding: 12px 15px; border-radius: 8px; margin-bottom: 10px; cursor: pointer; transition: background 0.2s; }
        .conversation-item:hover { background: rgba(255,255,255,0.1); }
        .conversation-item.active { background: #3498db; }
        
        /* Main Chat Area */
        .chat-area { flex: 1; display: flex; flex-direction: column; }
        .chat-header { padding: 25px 30px; border-bottom: 1px solid #eee; }
        .chat-header h1 { color: #2c3e50; font-size: 28px; }
        .chat-header p { color: #7f8c8d; margin-top: 5px; }
        
        .messages-container { flex: 1; padding: 30px; overflow-y: auto; background: #f8f9fa; }
        .message { margin-bottom: 25px; max-width: 80%; }
        .message.user { margin-left: auto; }
        .message.ai { margin-right: auto; }
        .message-content { padding: 20px; border-radius: 20px; position: relative; }
        .message.user .message-content { background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; border-bottom-right-radius: 5px; }
        .message.ai .message-content { background: white; color: #333; border: 1px solid #eee; border-bottom-left-radius: 5px; box-shadow: 0 5px 15px rgba(0,0,0,0.05); }
        .message-sender { font-weight: 600; margin-bottom: 8px; font-size: 14px; }
        .message-time { font-size: 12px; color: #95a5a6; margin-top: 10px; text-align: right; }
        
        /* Input Area */
        .input-area { padding: 25px 30px; border-top: 1px solid #eee; background: white; }
        .input-container { display: flex; gap: 15px; }
        .input-container textarea { flex: 1; padding: 18px; border: 2px solid #e0e0e0; border-radius: 15px; font-size: 16px; resize: none; min-height: 60px; max-height: 150px; }
        .input-container textarea:focus { outline: none; border-color: #667eea; }
        .send-btn { padding: 0 30px; background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; border: none; border-radius: 15px; font-size: 16px; cursor: pointer; }
        
        /* AI Models */
        .model-selector { display: flex; gap: 10px; margin-bottom: 20px; flex-wrap: wrap; }
        .model-btn { padding: 10px 20px; background: #f8f9fa; border: 2px solid #e0e0e0; border-radius: 10px; cursor: pointer; transition: all 0.2s; }
        .model-btn.active { background: #667eea; color: white; border-color: #667eea; }
        
        /* Responsive */
        @media (max-width: 768px) {
            .chat-container { flex-direction: column; }
            .sidebar { width: 100%; height: auto; }
        }
        
        /* Loading */
        .typing-indicator { display: flex; padding: 20px; background: white; border-radius: 20px; border: 1px solid #eee; width: 120px; }
        .typing-dot { width: 8px; height: 8px; background: #667eea; border-radius: 50%; margin: 0 3px; animation: typing 1.4s infinite; }
        .typing-dot:nth-child(2) { animation-delay: 0.2s; }
        .typing-dot:nth-child(3) { animation-delay: 0.4s; }
        @keyframes typing {
            0%, 60%, 100% { transform: translateY(0); }
            30% { transform: translateY(-10px); }
        }
    </style>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>
<body>
    <div class="chat-container">
        <!-- Sidebar -->
        <aside class="sidebar">
            <div class="sidebar-header">
                <h2><i class="fas fa-robot"></i> Ahhada AI</h2>
                <p>Akıllı Asistanınız</p>
            </div>
            
            <div class="user-info">
                <p><strong><i class="fas fa-user"></i> <?php echo htmlspecialchars($_SESSION['username']); ?></strong></p>
                <p style="font-size: 12px; opacity: 0.8;">ID: <?php echo substr($_SESSION['user_id'], 0, 10); ?>...</p>
            </div>
            
            <a href="chat.php?new=1" class="new-chat-btn">
                <i class="fas fa-plus"></i> Yeni Sohbet
            </a>
            
            <div class="model-selector">
                <div class="model-btn active" data-model="gpt-4">
                    <i class="fas fa-brain"></i> GPT-4
                </div>
                <div class="model-btn" data-model="deepseek">
                    <i class="fas fa-bolt"></i> DeepSeek
                </div>
                <div class="model-btn" data-model="gemini">
                    <i class="fas fa-gem"></i> Gemini
                </div>
            </div>
            
            <h3 style="margin: 25px 0 15px 0; font-size: 16px;"><i class="fas fa-history"></i> Geçmiş Sohbetler</h3>
            <div class="conversation-list">
                <?php if (empty($user_conversations)): ?>
                    <p style="text-align: center; opacity: 0.7; font-size: 14px;">Henüz sohbet yok</p>
                <?php else: ?>
                    <?php foreach ($user_conversations as $conv): ?>
                        <div class="conversation-item <?php echo $conv['id'] == $current_conversation_id ? 'active' : ''; ?>" 
                             onclick="loadConversation(<?php echo $conv['id']; ?>)">
                            <div style="font-weight: 500;"><?php echo htmlspecialchars($conv['title'] ?? 'Başlıksız'); ?></div>
                            <div style="font-size: 12px; opacity: 0.7; margin-top: 5px;">
                                <?php echo date('d.m H:i', strtotime($conv['created_at'])); ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
            
            <div style="margin-top: auto; padding-top: 25px; border-top: 1px solid rgba(255,255,255,0.1);">
                <a href="/s3/" style="color: #95a5a6; text-decoration: none; display: block; margin-bottom: 10px;">
                    <i class="fas fa-home"></i> Ana Sayfa
                </a>
                <a href="/s3/admin/" style="color: #3498db; text-decoration: none; display: block;">
                    <i class="fas fa-cogs"></i> Yönetim Paneli
                </a>
            </div>
        </aside>
        
        <!-- Main Chat Area -->
        <div class="chat-area">
            <div class="chat-header">
                <h1 id="conversation-title">
                    <?php echo $conversation ? htmlspecialchars($conversation['title']) : 'Yeni Sohbet'; ?>
                </h1>
                <p id="conversation-info">
                    <?php if ($conversation): ?>
                        <?php echo date('d.m.Y H:i', strtotime($conversation['created_at'])); ?> • 
                        <?php echo count($messages); ?> mesaj
                    <?php else: ?>
                        AI ile sohbete başlayın
                    <?php endif; ?>
                </p>
            </div>
            
            <div class="messages-container" id="messages-container">
                <?php if (empty($messages)): ?>
                    <div style="text-align: center; padding: 60px 20px; color: #7f8c8d;">
                        <i class="fas fa-robot" style="font-size: 64px; opacity: 0.3; margin-bottom: 20px;"></i>
                        <h2 style="margin-bottom: 15px;">Ahhada AI'ya Hoş Geldiniz!</h2>
                        <p>Nasıl yardımcı olabilirim? Aşağıdan mesajınızı yazın.</p>
                        
                        <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); gap: 20px; margin-top: 40px;">
                            <div style="background: white; padding: 20px; border-radius: 15px; box-shadow: 0 5px 15px rgba(0,0,0,0.05);">
                                <h3><i class="fas fa-lightbulb" style="color: #f1c40f;"></i> Öneriler</h3>
                                <p>İşte bazı başlangıç soruları:</p>
                                <ul style="text-align: left; margin-top: 10px; padding-left: 20px;">
                                    <li>Bugün hava durumu nasıl?</li>
                                    <li>PHP'de nasıl API yazılır?</li>
                                    <li>Yapay zeka nedir?</li>
                                </ul>
                            </div>
                        </div>
                    </div>
                <?php else: ?>
                    <?php foreach ($messages as $msg): ?>
                        <div class="message <?php echo $msg['role']; ?>">
                            <div class="message-sender">
                                <?php echo $msg['role'] == 'user' ? 
                                    '<i class="fas fa-user"></i> ' . htmlspecialchars($_SESSION['username']) : 
                                    '<i class="fas fa-robot"></i> Ahhada AI'; ?>
                            </div>
                            <div class="message-content">
                                <?php echo nl2br(htmlspecialchars($msg['content'])); ?>
                                <div class="message-time">
                                    <?php echo date('H:i', strtotime($msg['created_at'])); ?>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
                
                <!-- Typing indicator (hidden by default) -->
                <div id="typing-indicator" style="display: none;">
                    <div class="message ai">
                        <div class="message-sender"><i class="fas fa-robot"></i> Ahhada AI</div>
                        <div class="typing-indicator">
                            <div class="typing-dot"></div>
                            <div class="typing-dot"></div>
                            <div class="typing-dot"></div>
                        </div>
                    </div>
                </div>
            </div>
            
            <div class="input-area">
                <div class="input-container">
                    <textarea 
                        id="message-input" 
                        placeholder="Mesajınızı yazın... (Shift+Enter for new line, Enter to send)"
                        onkeydown="if(event.keyCode===13&&!event.shiftKey){event.preventDefault();sendMessage();}"
                    ></textarea>
                    <button class="send-btn" onclick="sendMessage()">
                        <i class="fas fa-paper-plane"></i> Gönder
                    </button>
                </div>
                <div style="margin-top: 15px; font-size: 12px; color: #95a5a6; text-align: center;">
                    <i class="fas fa-info-circle"></i> Ahhada AI • GPT-4, DeepSeek, Gemini • 
                    <span id="char-count">0</span> karakter
                </div>
            </div>
        </div>
    </div>
    
    <script>
        // Auto-scroll to bottom
        function scrollToBottom() {
            const container = document.getElementById('messages-container');
            container.scrollTop = container.scrollHeight;
        }
        
        // Update character count
        document.getElementById('message-input').addEventListener('input', function() {
            document.getElementById('char-count').textContent = this.value.length;
        });
        
        // Send message function
        async function sendMessage() {
            const input = document.getElementById('message-input');
            const message = input.value.trim();
            
            if (!message) return;
            
            // Clear input
            input.value = '';
            document.getElementById('char-count').textContent = '0';
            
            // Add user message to UI
            const messagesContainer = document.getElementById('messages-container');
            const userMessageHtml = `
                <div class="message user">
                    <div class="message-sender"><i class="fas fa-user"></i> <?php echo htmlspecialchars($_SESSION['username']); ?></div>
                    <div class="message-content">
                        ${message.replace(/\n/g, '<br>')}
                        <div class="message-time">${new Date().toLocaleTimeString('tr-TR', {hour: '2-digit', minute:'2-digit'})}</div>
                    </div>
                </div>
            `;
            messagesContainer.insertAdjacentHTML('beforeend', userMessageHtml);
            
            // Show typing indicator
            document.getElementById('typing-indicator').style.display = 'block';
            scrollToBottom();
            
            // Get selected model
            const selectedModel = document.querySelector('.model-btn.active').dataset.model;
            
            try {
                // Send to API
                const response = await fetch('/s3/index.php?route=/chat/send', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                    },
                    body: JSON.stringify({
                        message: message,
                        model: selectedModel,
                        conversation_id: <?php echo $current_conversation_id ?: 'null'; ?>,
                        user_id: '<?php echo $_SESSION["user_id"]; ?>'
                    })
                });
                
                const data = await response.json();
                
                // Hide typing indicator
                document.getElementById('typing-indicator').style.display = 'none';
                
                if (data.status === 'success') {
                    // Add AI response
                    const aiMessageHtml = `
                        <div class="message ai">
                            <div class="message-sender"><i class="fas fa-robot"></i> Ahhada AI</div>
                            <div class="message-content">
                                ${data.response.replace(/\n/g, '<br>')}
                                <div class="message-time">${new Date().toLocaleTimeString('tr-TR', {hour: '2-digit', minute:'2-digit'})}</div>
                            </div>
                        </div>
                    `;
                    messagesContainer.insertAdjacentHTML('beforeend', aiMessageHtml);
                    
                    // Update conversation title if first message
                    if (!<?php echo $current_conversation_id ? 'false' : 'true'; ?>) {
                        document.getElementById('conversation-title').textContent = data.conversation_title || 'Yeni Sohbet';
                        document.getElementById('conversation-info').textContent = 
                            new Date().toLocaleDateString('tr-TR') + ' • 2 mesaj';
                        
                        // Reload conversation list
                        setTimeout(() => {
                            window.location.reload();
                        }, 1000);
                    }
                } else {
                    // Error
                    const errorHtml = `
                        <div class="message ai">
                            <div class="message-sender"><i class="fas fa-exclamation-triangle"></i> Hata</div>
                            <div class="message-content" style="background: #ffe6e6; color: #c00;">
                                Üzgünüm, bir hata oluştu: ${data.message || 'Bilinmeyen hata'}
                            </div>
                        </div>
                    `;
                    messagesContainer.insertAdjacentHTML('beforeend', errorHtml);
                }
                
                scrollToBottom();
                
            } catch (error) {
                document.getElementById('typing-indicator').style.display = 'none';
                
                const errorHtml = `
                    <div class="message ai">
                        <div class="message-sender"><i class="fas fa-exclamation-triangle"></i> Hata</div>
                        <div class="message-content" style="background: #ffe6e6; color: #c00;">
                            Bağlantı hatası: ${error.message}
                        </div>
                    </div>
                `;
                messagesContainer.insertAdjacentHTML('beforeend', errorHtml);
                scrollToBottom();
            }
        }
        
        // Model selection
        document.querySelectorAll('.model-btn').forEach(btn => {
            btn.addEventListener('click', function() {
                document.querySelectorAll('.model-btn').forEach(b => b.classList.remove('active'));
                this.classList.add('active');
            });
        });
        
        // Load conversation
        function loadConversation(conversationId) {
            window.location.href = `chat.php?id=${conversationId}`;
        }
        
        // Auto-focus input
        document.getElementById('message-input').focus();
        
        // Initial scroll
        scrollToBottom();
    </script>
</body>
</html>