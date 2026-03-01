<?php

namespace App\Models;

use App\Config\Database;
use PDO;
use JsonSerializable;

class MemorySummary implements JsonSerializable
{
    private ?int $id = null;
    private int $conversationId;
    private string $summaryText;
    private array $sourceMessageIds;
    private int $tokenCount;
    private string $createdAt;
    private int $version;
    
    public function __construct(
        int $conversationId,
        string $summaryText,
        array $sourceMessageIds,
        int $tokenCount,
        int $version = 1
    ) {
        $this->conversationId = $conversationId;
        $this->summaryText = $summaryText;
        $this->sourceMessageIds = $sourceMessageIds;
        $this->tokenCount = $tokenCount;
        $this->version = $version;
        $this->createdAt = date('Y-m-d H:i:s');
    }
    
    public static function find(int $id): ?self
    {
        $sql = "SELECT * FROM memory_summaries WHERE id = ?";
        $stmt = Database::executeQuery($sql, [$id]);
        $data = $stmt->fetch();
        
        if (!$data) {
            return null;
        }
        
        return self::fromArray($data);
    }
    
    public static function findByConversation(int $conversationId): array
    {
        $sql = "SELECT * FROM memory_summaries 
                WHERE conversation_id = ? 
                ORDER BY created_at DESC";
        
        $stmt = Database::executeQuery($sql, [$conversationId]);
        
        $summaries = [];
        while ($row = $stmt->fetch()) {
            $summaries[] = self::fromArray($row);
        }
        
        return $summaries;
    }
    
    public static function getLatestByConversation(int $conversationId): ?self
    {
        $sql = "SELECT * FROM memory_summaries 
                WHERE conversation_id = ? 
                ORDER BY created_at DESC 
                LIMIT 1";
        
        $stmt = Database::executeQuery($sql, [$conversationId]);
        $data = $stmt->fetch();
        
        if (!$data) {
            return null;
        }
        
        return self::fromArray($data);
    }
    
    public function save(): bool
    {
        if ($this->id === null) {
            return $this->insert();
        }
        
        return $this->update();
    }
    
    private function insert(): bool
    {
        $sql = "INSERT INTO memory_summaries (
                    conversation_id, summary_text, source_message_ids,
                    token_count, created_at, version
                ) VALUES (?, ?, ?::bigint[], ?, ?, ?)
                RETURNING id";
        
        $stmt = Database::executeQuery($sql, [
            $this->conversationId,
            $this->summaryText,
            '{' . implode(',', $this->sourceMessageIds) . '}',
            $this->tokenCount,
            $this->createdAt,
            $this->version
        ]);
        
        $this->id = (int) $stmt->fetch()['id'];
        
        // Update conversation summary count
        if ($this->id > 0) {
            $this->updateConversationSummaryCount();
            return true;
        }
        
        return false;
    }
    
    private function update(): bool
    {
        $sql = "UPDATE memory_summaries SET
                    summary_text = ?,
                    source_message_ids = ?::bigint[],
                    token_count = ?,
                    version = ?
                WHERE id = ?";
        
        $stmt = Database::executeQuery($sql, [
            $this->summaryText,
            '{' . implode(',', $this->sourceMessageIds) . '}',
            $this->tokenCount,
            $this->version,
            $this->id
        ]);
        
        return $stmt->rowCount() > 0;
    }
    
    private function updateConversationSummaryCount(): void
    {
        $conversation = Conversation::find($this->conversationId);
        if ($conversation) {
            $conversation->incrementSummaryCount();
        }
    }
    
    public function getCoveredMessageIds(): array
    {
        $sql = "SELECT DISTINCT UNNEST(source_message_ids) as message_id
                FROM memory_summaries 
                WHERE conversation_id = ? 
                AND id != ?";
        
        $stmt = Database::executeQuery($sql, [$this->conversationId, $this->id ?? 0]);
        
        $coveredIds = [];
        while ($row = $stmt->fetch()) {
            $coveredIds[] = (int) $row['message_id'];
        }
        
        return $coveredIds;
    }
    
    public static function getTotalSummaryTokens(int $conversationId): int
    {
        $sql = "SELECT COALESCE(SUM(token_count), 0) as total 
                FROM memory_summaries 
                WHERE conversation_id = ?";
        
        $stmt = Database::executeQuery($sql, [$conversationId]);
        $result = $stmt->fetch();
        
        return (int) $result['total'];
    }
    
    public static function fromArray(array $data): self
    {
        // Parse array from PostgreSQL format
        $sourceIds = [];
        if (!empty($data['source_message_ids'])) {
            if (is_string($data['source_message_ids'])) {
                // Remove braces and split
                $ids = trim($data['source_message_ids'], '{}');
                if (!empty($ids)) {
                    $sourceIds = array_map('intval', explode(',', $ids));
                }
            } elseif (is_array($data['source_message_ids'])) {
                $sourceIds = array_map('intval', $data['source_message_ids']);
            }
        }
        
        $summary = new self(
            (int) $data['conversation_id'],
            $data['summary_text'],
            $sourceIds,
            (int) $data['token_count'],
            (int) $data['version']
        );
        
        $summary->id = (int) $data['id'];
        $summary->createdAt = $data['created_at'];
        
        return $summary;
    }
    
    public function jsonSerialize(): array
    {
        return [
            'id' => $this->id,
            'conversation_id' => $this->conversationId,
            'summary_text' => $this->summaryText,
            'source_message_ids' => $this->sourceMessageIds,
            'token_count' => $this->tokenCount,
            'created_at' => $this->createdAt,
            'version' => $this->version,
            'covers_messages' => count($this->sourceMessageIds)
        ];
    }
    
    // Getters
    public function getId(): ?int { return $this->id; }
    public function getConversationId(): int { return $this->conversationId; }
    public function getSummaryText(): string { return $this->summaryText; }
    public function getSourceMessageIds(): array { return $this->sourceMessageIds; }
    public function getTokenCount(): int { return $this->tokenCount; }
    public function getCreatedAt(): string { return $this->createdAt; }
    public function getVersion(): int { return $this->version; }
}