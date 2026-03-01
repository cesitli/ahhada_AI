<?php

namespace Config;

use PDO;
use PDOException;
use RuntimeException;
use Psr\Log\LoggerInterface;
use Monolog\Logger;
use Monolog\Handler\StreamHandler;

class Database
{
    /** @var PDO|null */
    private static $connection = null;
    
    /** @var LoggerInterface|null */
    private static $logger = null;
    
    public static function getConnection(): PDO
    {
        if (self::$connection === null) {
            self::initialize();
        }
        
        return self::$connection;
    }
    
    public static function getLogger(): LoggerInterface
    {
        if (self::$logger === null) {
            // Logs dizini yoksa oluştur
            $logDir = __DIR__ . '/../logs';
            if (!is_dir($logDir)) {
                mkdir($logDir, 0755, true);
            }
            
            self::$logger = new Logger('database');
            self::$logger->pushHandler(
                new StreamHandler($logDir . '/database.log', Logger::INFO)
            );
        }
        
        return self::$logger;
    }
    
    private static function initialize(): void
    {
        // .env'den direkt oku (Config sınıfı yerine)
        $host = getenv('DB_HOST') ?: 'localhost';
        $port = getenv('DB_PORT') ?: '5432';
        $database = getenv('DB_DATABASE') ?: 'ahhada_s1';
        $username = getenv('DB_USERNAME') ?: 'ahhada_s1';
        $password = getenv('DB_PASS') ?: 'Sinurhira42zihni';
        
        $dsn = "pgsql:host={$host};port={$port};dbname={$database}";
        
        $options = [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
            PDO::ATTR_PERSISTENT => false,  // ⬅️ Persistent'i kapat daha iyi
            PDO::ATTR_TIMEOUT => 30,
        ];
        
        try {
            self::$connection = new PDO($dsn, $username, $password, $options);
            
            // Set PostgreSQL specific options
            self::$connection->exec("SET TIME ZONE 'Europe/Istanbul'");
            self::$connection->exec("SET client_encoding = 'UTF8'");
            self::$connection->exec("SET statement_timeout = 30000"); // 30 seconds
            
            self::getLogger()->info('Database connection established successfully');
            
        } catch (PDOException $e) {
            self::getLogger()->error('Database connection failed: ' . $e->getMessage());
            throw new RuntimeException('Database connection failed: ' . $e->getMessage());
        }
    }
    
    public static function beginTransaction(): bool
    {
        return self::getConnection()->beginTransaction();
    }
    
    public static function commit(): bool
    {
        return self::getConnection()->commit();
    }
    
    public static function rollback(): bool
    {
        return self::getConnection()->rollBack();
    }
    
    /**
     * Execute a SQL query with parameters
     *
     * @param string $sql The SQL query
     * @param array $params The parameters
     * @return \PDOStatement
     */
    public static function executeQuery(string $sql, array $params = []): \PDOStatement
    {
        $stmt = self::getConnection()->prepare($sql);
        $stmt->execute($params);
        return $stmt;
    }
    
    /**
     * Get last insert ID
     *
     * @param string|null $name Sequence name (for PostgreSQL)
     * @return string
     */
    public static function lastInsertId(?string $name = null): string
    {
        return self::getConnection()->lastInsertId($name);
    }
    
    /**
     * Check if database connection is alive
     *
     * @return bool
     */
    public static function isConnected(): bool
    {
        try {
            self::getConnection()->query('SELECT 1');
            return true;
        } catch (PDOException $e) {
            return false;
        }
    }
    
    /**
     * Close database connection
     */
    public static function close(): void
    {
        self::$connection = null;
    }
}