<?php

namespace App\Models;

use App\Config\Database;
use PDO;
use JsonSerializable;

class User implements JsonSerializable
{
    private ?int $id = null;
    private string $username;
    private string $email;
    private string $hashedPassword;
    private bool $isActive;
    private string $createdAt;
    private string $updatedAt;
    private array $settings;
    private int $rateLimitRequests;
    private int $rateLimitPeriod;
    
    public function __construct(
        string $username,
        string $email,
        string $password,
        array $settings = [],
        int $rateLimitRequests = 1000,
        int $rateLimitPeriod = 3600
    ) {
        $this->username = $username;
        $this->email = $email;
        $this->hashedPassword = password_hash($password, PASSWORD_BCRYPT);
        $this->isActive = true;
        $this->settings = $settings;
        $this->rateLimitRequests = $rateLimitRequests;
        $this->rateLimitPeriod = $rateLimitPeriod;
        $this->createdAt = date('Y-m-d H:i:s');
        $this->updatedAt = date('Y-m-d H:i:s');
    }
    
    public static function find(int $id): ?self
    {
        $sql = "SELECT * FROM users WHERE id = ?";
        $stmt = Database::executeQuery($sql, [$id]);
        $data = $stmt->fetch();
        
        if (!$data) {
            return null;
        }
        
        return self::fromArray($data);
    }
    
    public static function findByUsername(string $username): ?self
    {
        $sql = "SELECT * FROM users WHERE username = ?";
        $stmt = Database::executeQuery($sql, [$username]);
        $data = $stmt->fetch();
        
        if (!$data) {
            return null;
        }
        
        return self::fromArray($data);
    }
    
    public static function findByEmail(string $email): ?self
    {
        $sql = "SELECT * FROM users WHERE email = ?";
        $stmt = Database::executeQuery($sql, [$email]);
        $data = $stmt->fetch();
        
        if (!$data) {
            return null;
        }
        
        return self::fromArray($data);
    }
    
    public static function create(array $data): ?self
    {
        $user = new self(
            $data['username'],
            $data['email'],
            $data['password'],
            $data['settings'] ?? [],
            $data['rate_limit_requests'] ?? 1000,
            $data['rate_limit_period'] ?? 3600
        );
        
        if ($user->save()) {
            return $user;
        }
        
        return null;
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
        $sql = "INSERT INTO users (
                    username, email, hashed_password, is_active,
                    created_at, updated_at, settings, 
                    rate_limit_requests, rate_limit_period
                ) VALUES (?, ?, ?, ?, ?, ?, ?::jsonb, ?, ?)
                RETURNING id";
        
        $stmt = Database::executeQuery($sql, [
            $this->username,
            $this->email,
            $this->hashedPassword,
            $this->isActive,
            $this->createdAt,
            $this->updatedAt,
            json_encode($this->settings),
            $this->rateLimitRequests,
            $this->rateLimitPeriod
        ]);
        
        $this->id = (int) $stmt->fetch()['id'];
        return $this->id > 0;
    }
    
    private function update(): bool
    {
        $sql = "UPDATE users SET
                    username = ?,
                    email = ?,
                    hashed_password = ?,
                    is_active = ?,
                    updated_at = NOW(),
                    settings = ?::jsonb,
                    rate_limit_requests = ?,
                    rate_limit_period = ?
                WHERE id = ?";
        
        $stmt = Database::executeQuery($sql, [
            $this->username,
            $this->email,
            $this->hashedPassword,
            $this->isActive,
            json_encode($this->settings),
            $this->rateLimitRequests,
            $this->rateLimitPeriod,
            $this->id
        ]);
        
        return $stmt->rowCount() > 0;
    }
    
    public function verifyPassword(string $password): bool
    {
        return password_verify($password, $this->hashedPassword);
    }
    
    public function updatePassword(string $newPassword): bool
    {
        $this->hashedPassword = password_hash($newPassword, PASSWORD_BCRYPT);
        $this->updatedAt = date('Y-m-d H:i:s');
        return $this->update();
    }
    
    public function updateSettings(array $settings): bool
    {
        $this->settings = array_merge($this->settings, $settings);
        $this->updatedAt = date('Y-m-d H:i:s');
        return $this->update();
    }
    
    public function getConversations(int $limit = 50, int $offset = 0): array
    {
        $sql = "SELECT * FROM conversations 
                WHERE user_id = ? 
                ORDER BY last_message_at DESC NULLS LAST 
                LIMIT ? OFFSET ?";
        
        $stmt = Database::executeQuery($sql, [$this->id, $limit, $offset]);
        
        $conversations = [];
        while ($row = $stmt->fetch()) {
            $conversations[] = $row;
        }
        
        return $conversations;
    }
    
    public function getApiKeys(): array
    {
        $sql = "SELECT * FROM user_api_keys 
                WHERE user_id = ? AND is_active = true";
        
        $stmt = Database::executeQuery($sql, [$this->id]);
        
        $keys = [];
        while ($row = $stmt->fetch()) {
            $keys[] = $row;
        }
        
        return $keys;
    }
    
    public static function fromArray(array $data): self
    {
        $user = new self(
            $data['username'],
            $data['email'],
            '', // Password not stored in object
            json_decode($data['settings'] ?? '{}', true),
            (int) $data['rate_limit_requests'],
            (int) $data['rate_limit_period']
        );
        
        $user->id = (int) $data['id'];
        $user->hashedPassword = $data['hashed_password'];
        $user->isActive = (bool) $data['is_active'];
        $user->createdAt = $data['created_at'];
        $user->updatedAt = $data['updated_at'];
        
        return $user;
    }
    
    public function jsonSerialize(): array
    {
        return [
            'id' => $this->id,
            'username' => $this->username,
            'email' => $this->email,
            'is_active' => $this->isActive,
            'created_at' => $this->createdAt,
            'updated_at' => $this->updatedAt,
            'settings' => $this->settings,
            'rate_limit' => [
                'requests' => $this->rateLimitRequests,
                'period' => $this->rateLimitPeriod
            ]
        ];
    }
    
    // Getters
    public function getId(): ?int { return $this->id; }
    public function getUsername(): string { return $this->username; }
    public function getEmail(): string { return $this->email; }
    public function isActive(): bool { return $this->isActive; }
    public function getCreatedAt(): string { return $this->createdAt; }
    public function getSettings(): array { return $this->settings; }
    
    // Setters
    public function setUsername(string $username): void { $this->username = $username; }
    public function setEmail(string $email): void { $this->email = $email; }
    public function setActive(bool $active): void { $this->isActive = $active; }
    public function setSettings(array $settings): void { $this->settings = $settings; }
}