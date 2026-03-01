<?php

namespace App\Controllers;

use App\Models\Message;
use App\Models\Conversation;
use App\Services\EmbeddingService;
use App\Utils\TokenCalculator;
use Exception;

class MessageController
{
    private TokenCalculator $tokenCalculator;
    private EmbeddingService $embeddingService;
    
    public function __construct()
    {
        $this->tokenCalculator = new TokenCalculator();
        $this->embeddingService = new EmbeddingService();
    }
    
    public function get(int $id): array
    {
        try {
            $message = Message::find($id);
            
            if (!$message) {
                return [
                    'success' => false,
                    'error' => 'Mesaj bulunamadı',
                    'status' => 404
                ];
            }
            
            return [
                'success' => true,
                'data' => [
                    'message' => $message->jsonSerialize(),
                    'similar_messages' => $message->getSimilarMessages(5)
                ]
            ];
            
        } catch (Exception $e) {
            return [
                'success' => false,
                'error' => 'Mesaj getirilirken hata: ' . $e->getMessage(),
                'status' => 500
            ];
        }
    }
    
    public function update(int $id, array $data): array
    {
        try {
            $message = Message::find($id);
            
            if (!$message) {
                return [
                    'success' => false,
                    'error' => 'Mesaj bulunamadı',
                    'status' => 404
                ];
            }
            
            // Update fields if provided
            if (isset($data['content'])) {
                $message->setContent($data['content']);
                $message->setTokens($this->tokenCalculator->countTokens($data['content']));
                
                // Regenerate embedding if content changed
                if ($data['regenerate_embedding'] ?? true) {
                    $embedding = $this->embeddingService->getEmbedding($data['content']);
                    if ($embedding) {
                        $message->setEmbedding($embedding);
                    }
                }
            }
            
            if (isset($data['metadata'])) {
                $message->setMetadata($data['metadata']);
            }
            
            if ($message->save()) {
                return [
                    'success' => true,
                    'data' => [
                        'message' => $message->jsonSerialize(),
                        'updated' => true
                    ]
                ];
            }
            
            return [
                'success' => false,
                'error' => 'Mesaj güncellenemedi',
                'status' => 500
            ];
            
        } catch (Exception $e) {
            return [
                'success' => false,
                'error' => 'Mesaj güncellenirken hata: ' . $e->getMessage(),
                'status' => 500
            ];
        }
    }
    
    public function delete(int $id): array
    {
        try {
            $message = Message::find($id);
            
            if (!$message) {
                return [
                    'success' => false,
                    'error' => 'Mesaj bulunamadı',
                    'status' => 404
                ];
            }
            
            $conversationId = $message->getConversationId();
            
            // Delete message
            $sql = "DELETE FROM messages WHERE id = ?";
            $stmt = \App\Config\Database::executeQuery($sql, [$id]);
            
            if ($stmt->rowCount() > 0) {
                // Update conversation stats
                $this->updateConversationStats($conversationId);
                
                return [
                    'success' => true,
                    'data' => [
                        'message' => 'Mesaj başarıyla silindi',
                        'conversation_id' => $conversationId
                    ]
                ];
            }
            
            return [
                'success' => false,
                'error' => 'Mesaj silinemedi',
                'status' => 500
            ];
            
        } catch (Exception $e) {
            return [
                'success' => false,
                'error' => 'Mesaj silinirken hata: ' . $e->getMessage(),
                'status' => 500
            ];
        }
    }
    
    public function searchInConversation(int $conversationId, array $filters): array
    {
        try {
            $conversation = Conversation::find($conversationId);
            
            if (!$conversation) {
                return [
                    'success' => false,
                    'error' => 'Konuşma bulunamadı',
                    'status' => 404
                ];
            }
            
            $whereClauses = ['conversation_id = ?'];
            $params = [$conversationId];
            
            // Apply filters
            if (!empty($filters['role'])) {
                $whereClauses[] = 'role = ?';
                $params[] = $filters['role'];
            }
            
            if (!empty($filters['search'])) {
                $whereClauses[] = 'content ILIKE ?';
                $params[] = '%' . $filters['search'] . '%';
            }
            
            if (!empty($filters['start_date'])) {
                $whereClauses[] = 'created_at >= ?';
                $params[] = $filters['start_date'];
            }
            
            if (!empty($filters['end_date'])) {
                $whereClauses[] = 'created_at <= ?';
                $params[] = $filters['end_date'];
            }
            
            $where = implode(' AND ', $whereClauses);
            
            $page = $filters['page'] ?? 1;
            $perPage = $filters['per_page'] ?? 50;
            $offset = ($page - 1) * $perPage;
            
            // Get total count
            $countSql = "SELECT COUNT(*) as total FROM messages WHERE $where";
            $countStmt = \App\Config\Database::executeQuery($countSql, $params);
            $total = (int) $countStmt->fetch()['total'];
            
            // Get messages
            $sql = "SELECT * FROM messages WHERE $where ORDER BY created_at DESC LIMIT ? OFFSET ?";
            $params[] = $perPage;
            $params[] = $offset;
            
            $stmt = \App\Config\Database::executeQuery($sql, $params);
            
            $messages = [];
            while ($row = $stmt->fetch()) {
                $messages[] = Message::fromArray($row)->jsonSerialize();
            }
            
            return [
                'success' => true,
                'data' => [
                    'messages' => $messages,
                    'pagination' => [
                        'page' => $page,
                        'per_page' => $perPage,
                        'total' => $total,
                        'total_pages' => ceil($total / $perPage)
                    ],
                    'filters' => $filters
                ]
            ];
            
        } catch (Exception $e) {
            return [
                'success' => false,
                'error' => 'Mesaj arama sırasında hata: ' . $e->getMessage(),
                'status' => 500
            ];
        }
    }
    
    public function findSimilar(int $messageId, array $options = []): array
    {
        try {
            $message = Message::find($messageId);
            
            if (!$message) {
                return [
                    'success' => false,
                    'error' => 'Mesaj bulunamadı',
                    'status' => 404
                ];
            }
            
            $embedding = $message->getEmbedding();
            
            if (!$embedding) {
                return [
                    'success' => false,
                    'error' => 'Mesaj embedding içermiyor',
                    'status' => 400
                ];
            }
            
            $threshold = $options['threshold'] ?? 0.7;
            $limit = $options['limit'] ?? 10;
            
            $sql = "SELECT m.*, 
                    1 - (m.embedding <=> ?::vector) as similarity
                    FROM messages m
                    WHERE m.id != ?
                    AND m.conversation_id = ?
                    AND m.embedding IS NOT NULL
                    AND (1 - (m.embedding <=> ?::vector)) > ?
                    ORDER BY similarity DESC
                    LIMIT ?";
            
            $stmt = \App\Config\Database::executeQuery($sql, [
                $embedding,
                $messageId,
                $message->getConversationId(),
                $embedding,
                $threshold,
                $limit
            ]);
            
            $similarMessages = [];
            while ($row = $stmt->fetch()) {
                $similarMessages[] = [
                    'message' => Message::fromArray($row)->jsonSerialize(),
                    'similarity' => (float) $row['similarity']
                ];
            }
            
            return [
                'success' => true,
                'data' => [
                    'original_message' => $message->jsonSerialize(),
                    'similar_messages' => $similarMessages,
                    'threshold' => $threshold,
                    'found' => count($similarMessages)
                ]
            ];
            
        } catch (Exception $e) {
            return [
                'success' => false,
                'error' => 'Benzer mesaj aranırken hata: ' . $e->getMessage(),
                'status' => 500
            ];
        }
    }
    
    private function updateConversationStats(int $conversationId): void
    {
        // Recalculate conversation stats
        $sql = "UPDATE conversations SET
                    total_messages = (SELECT COUNT(*) FROM messages WHERE conversation_id = ?),
                    total_tokens = (SELECT COALESCE(SUM(tokens), 0) FROM messages WHERE conversation_id = ?),
                    last_message_at = (SELECT MAX(created_at) FROM messages WHERE conversation_id = ?),
                    updated_at = NOW()
                WHERE id = ?";
        
        \App\Config\Database::executeQuery($sql, [
            $conversationId,
            $conversationId,
            $conversationId,
            $conversationId
        ]);
    }
}