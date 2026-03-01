<?php

namespace App\Middleware;

use App\Config\Database;
use App\Utils\Logger;

class AuthMiddleware
{
    private $db;
    
    public function __construct()
    {
        $this->db = Database::getConnection();
    }
    
    public function handle(): bool
    {
        // Get token from header
        $headers = getallheaders();
        $authHeader = $headers['Authorization'] ?? $_SERVER['HTTP_AUTHORIZATION'] ?? '';
        
        if (empty($authHeader)) {
            // Try to get from query parameter (for development)
            $authHeader = $_GET['token'] ?? '';
        }
        
        if (empty($authHeader)) {
            $this->sendUnauthorized('Authorization header missing');
            return false;
        }
        
        // Extract token from "Bearer {token}" format
        if (preg_match('/Bearer\s+(.*)$/i', $authHeader, $matches)) {
            $token = $matches[1];
        } else {
            $token = $authHeader;
        }
        
        if (empty($token) || strlen($token) < 10) {
            $this->sendUnauthorized('Invalid token format');
            return false;
        }
        
        // Validate token in database
        try {
            $sql = "SELECT u.* FROM users u 
                    JOIN user_tokens ut ON u.id = ut.user_id 
                    WHERE ut.token = :token 
                    AND ut.expires_at > NOW() 
                    AND ut.is_active = true";
            
            $stmt = $this->db->prepare($sql);
            $stmt->execute([':token' => $token]);
            $user = $stmt->fetch();
            
            if (!$user) {
                $this->sendUnauthorized('Invalid or expired token');
                return false;
            }
            
            // Store user in global context for later use
            $GLOBALS['current_user'] = $user;
            $GLOBALS['auth_token'] = $token;
            
            // Log the authentication
            Logger::info('User authenticated', [
                'user_id' => $user['id'],
                'email' => $user['email']
            ]);
            
            return true;
            
        } catch (\Exception $e) {
            Logger::error('Auth validation failed', ['error' => $e->getMessage()]);
            
            $this->sendUnauthorized('Authentication system error');
            return false;
        }
    }
    
    public function handleApiKey(): bool
    {
        // Alternative: API key authentication
        $apiKey = $_SERVER['HTTP_X_API_KEY'] ?? $_GET['api_key'] ?? '';
        
        if (empty($apiKey)) {
            $this->sendUnauthorized('API key required');
            return false;
        }
        
        try {
            $sql = "SELECT * FROM api_keys 
                    WHERE api_key = :api_key 
                    AND is_active = true 
                    AND (expires_at IS NULL OR expires_at > NOW())";
            
            $stmt = $this->db->prepare($sql);
            $stmt->execute([':api_key' => $apiKey]);
            $apiKeyData = $stmt->fetch();
            
            if (!$apiKeyData) {
                $this->sendUnauthorized('Invalid API key');
                return false;
            }
            
            // Update last used
            $updateSql = "UPDATE api_keys SET last_used = NOW() WHERE id = :id";
            $updateStmt = $this->db->prepare($updateSql);
            $updateStmt->execute([':id' => $apiKeyData['id']]);
            
            $GLOBALS['api_key'] = $apiKeyData;
            
            Logger::info('API key authenticated', [
                'key_id' => $apiKeyData['id'],
                'name' => $apiKeyData['name']
            ]);
            
            return true;
            
        } catch (\Exception $e) {
            Logger::error('API key validation failed', ['error' => $e->getMessage()]);
            
            $this->sendUnauthorized('API key validation error');
            return false;
        }
    }
    
    public function requireRole(string $role): bool
    {
        if (!isset($GLOBALS['current_user'])) {
            $this->sendUnauthorized('Authentication required');
            return false;
        }
        
        $user = $GLOBALS['current_user'];
        
        if ($user['role'] !== $role && $user['role'] !== 'admin') {
            $this->sendForbidden('Insufficient permissions');
            return false;
        }
        
        return true;
    }
    
    public function requireAnyRole(array $roles): bool
    {
        if (!isset($GLOBALS['current_user'])) {
            $this->sendUnauthorized('Authentication required');
            return false;
        }
        
        $user = $GLOBALS['current_user'];
        
        if (!in_array($user['role'], $roles) && $user['role'] !== 'admin') {
            $this->sendForbidden('Insufficient permissions');
            return false;
        }
        
        return true;
    }
    
    private function sendUnauthorized(string $message): void
    {
        http_response_code(401);
        header('Content-Type: application/json');
        echo json_encode([
            'success' => false,
            'error' => $message,
            'status' => 401
        ]);
    }
    
    private function sendForbidden(string $message): void
    {
        http_response_code(403);
        header('Content-Type: application/json');
        echo json_encode([
            'success' => false,
            'error' => $message,
            'status' => 403
        ]);
    }
    
    public static function getCurrentUser(): ?array
    {
        return $GLOBALS['current_user'] ?? null;
    }
    
    public static function getAuthToken(): ?string
    {
        return $GLOBALS['auth_token'] ?? null;
    }
}