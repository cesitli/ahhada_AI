<?php

if (!function_exists('config')) {
    function config(string $key, mixed $default = null): mixed
    {
        return \App\Config\Config::get($key, $default);
    }
}

if (!function_exists('db')) {
    function db(): PDO
    {
        return \App\Config\Database::getConnection();
    }
}

if (!function_exists('logger')) {
    function logger(string $channel = 'app'): \Monolog\Logger
    {
        return \App\Utils\Logger::get($channel);
    }
}

if (!function_exists('ai')) {
    function ai(): \App\Services\AIProviderManager
    {
        return new \App\Services\AIProviderManager();
    }
}

if (!function_exists('validate')) {
    function validate(array $data, array $rules): array
    {
        return \App\Utils\Validator::validateArray($data, $rules);
    }
}

if (!function_exists('sanitize')) {
    function sanitize(mixed $input): mixed
    {
        if (is_array($input)) {
            return \App\Utils\Validator::sanitizeArray($input);
        }
        
        if (is_string($input)) {
            return \App\Utils\Validator::sanitizeInput($input);
        }
        
        return $input;
    }
}

if (!function_exists('response_json')) {
    function response_json(array $data, int $status = 200): void
    {
        http_response_code($status);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
        exit();
    }
}

if (!function_exists('success_response')) {
    function success_response(mixed $data = null, string $message = 'Success'): void
    {
        response_json([
            'success' => true,
            'message' => $message,
            'data' => $data,
            'timestamp' => date('Y-m-d H:i:s')
        ]);
    }
}

if (!function_exists('error_response')) {
    function error_response(string $message, int $status = 400, mixed $errors = null): void
    {
        response_json([
            'success' => false,
            'message' => $message,
            'errors' => $errors,
            'timestamp' => date('Y-m-d H:i:s')
        ], $status);
    }
}

if (!function_exists('get_input')) {
    function get_input(): array
    {
        $contentType = $_SERVER['CONTENT_TYPE'] ?? '';
        
        if (strpos($contentType, 'application/json') !== false) {
            $input = file_get_contents('php://input');
            return json_decode($input, true) ?? [];
        }
        
        return $_POST;
    }
}

if (!function_exists('cors_headers')) {
    function cors_headers(): void
    {
        header("Access-Control-Allow-Origin: *");
        header("Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS");
        header("Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With");
        
        if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
            http_response_code(200);
            exit();
        }
    }
}