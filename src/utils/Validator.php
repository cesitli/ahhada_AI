<?php

namespace App\Utils;

class Validator
{
    public static function validateEmail(string $email): bool
    {
        return filter_var($email, FILTER_VALIDATE_EMAIL) !== false;
    }
    
    public static function validateUsername(string $username): bool
    {
        // 3-20 characters, letters, numbers, underscores
        return preg_match('/^[a-zA-Z0-9_]{3,20}$/', $username) === 1;
    }
    
    public static function validatePassword(string $password): array
    {
        $errors = [];
        
        if (strlen($password) < 8) {
            $errors[] = 'Password must be at least 8 characters long';
        }
        
        if (!preg_match('/[A-Z]/', $password)) {
            $errors[] = 'Password must contain at least one uppercase letter';
        }
        
        if (!preg_match('/[a-z]/', $password)) {
            $errors[] = 'Password must contain at least one lowercase letter';
        }
        
        if (!preg_match('/[0-9]/', $password)) {
            $errors[] = 'Password must contain at least one number';
        }
        
        return [
            'valid' => empty($errors),
            'errors' => $errors
        ];
    }
    
    public static function validateJson(string $json): bool
    {
        json_decode($json);
        return json_last_error() === JSON_ERROR_NONE;
    }
    
    public static function sanitizeInput(string $input): string
    {
        // Remove unwanted characters, trim, escape
        $input = trim($input);
        $input = stripslashes($input);
        $input = htmlspecialchars($input, ENT_QUOTES, 'UTF-8');
        
        return $input;
    }
    
    public static function sanitizeArray(array $array): array
    {
        $sanitized = [];
        
        foreach ($array as $key => $value) {
            if (is_array($value)) {
                $sanitized[$key] = self::sanitizeArray($value);
            } elseif (is_string($value)) {
                $sanitized[$key] = self::sanitizeInput($value);
            } else {
                $sanitized[$key] = $value;
            }
        }
        
        return $sanitized;
    }
    
    public static function validateConversationData(array $data): array
    {
        $errors = [];
        
        if (empty($data['user_id'])) {
            $errors[] = 'User ID is required';
        } elseif (!is_numeric($data['user_id'])) {
            $errors[] = 'User ID must be numeric';
        }
        
        if (isset($data['title']) && strlen($data['title']) > 255) {
            $errors[] = 'Title must be 255 characters or less';
        }
        
        if (isset($data['metadata']) && !self::validateJson(json_encode($data['metadata']))) {
            $errors[] = 'Metadata must be valid JSON';
        }
        
        return [
            'valid' => empty($errors),
            'errors' => $errors
        ];
    }
    
    public static function validateMessageData(array $data): array
    {
        $errors = [];
        
        $required = ['conversation_id', 'user_id', 'content'];
        foreach ($required as $field) {
            if (empty($data[$field])) {
                $errors[] = ucfirst(str_replace('_', ' ', $field)) . ' is required';
            }
        }
        
        if (isset($data['conversation_id']) && !is_numeric($data['conversation_id'])) {
            $errors[] = 'Conversation ID must be numeric';
        }
        
        if (isset($data['user_id']) && !is_numeric($data['user_id'])) {
            $errors[] = 'User ID must be numeric';
        }
        
        if (isset($data['role']) && !in_array($data['role'], ['user', 'assistant', 'system'])) {
            $errors[] = 'Role must be one of: user, assistant, system';
        }
        
        if (isset($data['content']) && strlen($data['content']) > 10000) {
            $errors[] = 'Content must be 10,000 characters or less';
        }
        
        if (isset($data['metadata']) && !self::validateJson(json_encode($data['metadata']))) {
            $errors[] = 'Metadata must be valid JSON';
        }
        
        return [
            'valid' => empty($errors),
            'errors' => $errors
        ];
    }
    
    public static function validateTokenLimit(string $text, int $maxTokens = 128000): bool
    {
        $tokenCalculator = new TokenCalculator();
        $tokens = $tokenCalculator->countTokens($text);
        
        return $tokens <= $maxTokens;
    }
    
    public static function validateApiKey(string $apiKey): bool
    {
        // Basic API key validation (starts with sk- for OpenAI, etc.)
        if (strlen($apiKey) < 20) {
            return false;
        }
        
        // Check for common API key patterns
        $patterns = [
            '/^sk-[a-zA-Z0-9]{32,}$/', // OpenAI
            '/^[a-zA-Z0-9]{32,}$/', // Generic
        ];
        
        foreach ($patterns as $pattern) {
            if (preg_match($pattern, $apiKey)) {
                return true;
            }
        }
        
        return false;
    }
    
    public static function validateDateRange(?string $start, ?string $end): array
    {
        $errors = [];
        
        if ($start && !strtotime($start)) {
            $errors[] = 'Invalid start date format';
        }
        
        if ($end && !strtotime($end)) {
            $errors[] = 'Invalid end date format';
        }
        
        if ($start && $end && strtotime($start) > strtotime($end)) {
            $errors[] = 'Start date cannot be after end date';
        }
        
        return [
            'valid' => empty($errors),
            'errors' => $errors
        ];
    }
    
    public static function validatePagination(int $page, int $perPage): array
    {
        $errors = [];
        
        if ($page < 1) {
            $errors[] = 'Page must be 1 or greater';
        }
        
        if ($perPage < 1 || $perPage > 1000) {
            $errors[] = 'Per page must be between 1 and 1000';
        }
        
        return [
            'valid' => empty($errors),
            'errors' => $errors
        ];
    }
}