<?php

namespace App\Utils;

use Monolog\Logger as MonoLogger;
use Monolog\Handler\StreamHandler;
use Monolog\Handler\RotatingFileHandler;
use Monolog\Formatter\LineFormatter;

class Logger
{
    private static array $instances = [];
    
    public static function get(string $name = 'app'): MonoLogger
    {
        if (!isset(self::$instances[$name])) {
            self::$instances[$name] = self::createLogger($name);
        }
        
        return self::$instances[$name];
    }
    
    private static function createLogger(string $name): MonoLogger
    {
        $logger = new MonoLogger($name);
        
        // Create logs directory if it doesn't exist
        $logDir = __DIR__ . '/../logs';
        if (!is_dir($logDir)) {
            mkdir($logDir, 0755, true);
        }
        
        // Main log file (rotates daily, keeps 7 days)
        $mainHandler = new RotatingFileHandler(
            $logDir . '/' . $name . '.log',
            7,
            MonoLogger::INFO
        );
        
        // Error log file (separate, keeps all errors)
        $errorHandler = new StreamHandler(
            $logDir . '/error.log',
            MonoLogger::ERROR
        );
        
        // Console output in debug mode
        if (php_sapi_name() === 'cli' || ($_ENV['DEBUG'] ?? false)) {
            $consoleHandler = new StreamHandler('php://stdout', MonoLogger::DEBUG);
            $consoleHandler->setFormatter(new LineFormatter(
                "[%datetime%] %channel%.%level_name%: %message% %context% %extra%\n",
                'Y-m-d H:i:s'
            ));
            $logger->pushHandler($consoleHandler);
        }
        
        // Custom formatter
        $formatter = new LineFormatter(
            "[%datetime%] %channel%.%level_name%: %message% %context% %extra%\n",
            'Y-m-d H:i:s.u',
            true,
            true
        );
        
        $mainHandler->setFormatter($formatter);
        $errorHandler->setFormatter($formatter);
        
        $logger->pushHandler($mainHandler);
        $logger->pushHandler($errorHandler);
        
        return $logger;
    }
    
    public static function emergency(string $message, array $context = []): void
    {
        self::get()->emergency($message, $context);
    }
    
    public static function alert(string $message, array $context = []): void
    {
        self::get()->alert($message, $context);
    }
    
    public static function critical(string $message, array $context = []): void
    {
        self::get()->critical($message, $context);
    }
    
    public static function error(string $message, array $context = []): void
    {
        self::get()->error($message, $context);
    }
    
    public static function warning(string $message, array $context = []): void
    {
        self::get()->warning($message, $context);
    }
    
    public static function notice(string $message, array $context = []): void
    {
        self::get()->notice($message, $context);
    }
    
    public static function info(string $message, array $context = []): void
    {
        self::get()->info($message, $context);
    }
    
    public static function debug(string $message, array $context = []): void
    {
        self::get()->debug($message, $context);
    }
    
    public static function logApiRequest(array $data): void
    {
        self::get('api')->info('API Request', $data);
    }
    
    public static function logDatabaseQuery(string $query, array $params = [], float $time = 0): void
    {
        self::get('database')->debug('Query executed', [
            'query' => $query,
            'params' => $params,
            'time_ms' => round($time * 1000, 2)
        ]);
    }
    
    public static function logAIRequest(string $provider, array $data): void
    {
        self::get('ai')->info('AI Request', array_merge(['provider' => $provider], $data));
    }
    
    public static function logPerformance(string $operation, array $metrics): void
    {
        self::get('performance')->info($operation, $metrics);
    }
}