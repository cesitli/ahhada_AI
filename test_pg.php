<?php
// test_pg.php
error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "<pre>";

// PostgreSQL bağlantı bilgileri
$host = 'localhost';
$port = '5432';
$dbname = 'ahhada_s1';
$username = 'ahhada_s1';
$password = 'Sinurhira42zihni'; // Buraya gerçek şifreyi yaz

echo "=== POSTGRESQL CONNECTION TEST ===\n\n";
echo "Host: $host\n";
echo "Port: $port\n";
echo "Database: $dbname\n";
echo "Username: $username\n";
echo "Password: " . (empty($password) ? '(empty)' : '***') . "\n\n";

try {
    // 1. Normal bağlantı denemesi
    echo "1. Normal PDO connection attempt...\n";
    $dsn = "pgsql:host=$host;port=$port;dbname=$dbname";
    $pdo = new PDO($dsn, $username, $password, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ]);
    
    echo "✅ PostgreSQL bağlantısı BAŞARILI\n";
    
    // 2. PostgreSQL versiyonu
    $stmt = $pdo->query('SELECT version()');
    $version = $stmt->fetch()['version'];
    echo "PostgreSQL Version: $version\n\n";
    
    // 3. Authentication method kontrolü
    echo "2. Authentication method check...\n";
    $stmt = $pdo->query("
        SELECT username, auth_method 
        FROM pg_authid 
        WHERE usename = current_user
    ");
    
    $authInfo = $stmt->fetch();
    echo "User: " . ($authInfo['username'] ?? 'N/A') . "\n";
    echo "Auth Method: " . ($authInfo['auth_method'] ?? 'N/A') . "\n\n";
    
    // 4. Tabloları listele
    echo "3. Tables in database:\n";
    $stmt = $pdo->query("
        SELECT table_name 
        FROM information_schema.tables 
        WHERE table_schema = 'public' 
        ORDER BY table_name
    ");
    
    $tables = $stmt->fetchAll(PDO::FETCH_COLUMN);
    
    if (empty($tables)) {
        echo "⚠️ No tables found in public schema\n";
    } else {
        foreach ($tables as $table) {
            echo "- $table\n";
        }
    }
    
    // 5. Gerekli tablolar var mı?
    echo "\n4. Required tables check:\n";
    $requiredTables = ['conversations', 'messages', 'memory_summaries'];
    
    foreach ($requiredTables as $table) {
        try {
            $stmt = $pdo->query("SELECT 1 FROM $table LIMIT 1");
            echo "✅ $table - EXISTS\n";
        } catch (Exception $e) {
            echo "❌ $table - NOT FOUND\n";
        }
    }
    
} catch (PDOException $e) {
    echo "❌ PostgreSQL connection FAILED\n";
    echo "Error: " . $e->getMessage() . "\n";
    echo "Error Code: " . $e->getCode() . "\n";
    
    // Authentication hatası mı?
    if (strpos($e->getMessage(), 'password authentication failed') !== false) {
        echo "\n🔑 PASSWORD AUTHENTICATION FAILED\n";
        echo "Possible solutions:\n";
        echo "1. Wrong password\n";
        echo "2. PostgreSQL uses scram-sha-256 (PHP 7.4+ required)\n";
        echo "3. Check pg_hba.conf configuration\n";
    }
    
    // Connection hatası mı?
    if (strpos($e->getMessage(), 'could not connect') !== false) {
        echo "\n🔌 CONNECTION FAILED\n";
        echo "Possible solutions:\n";
        echo "1. PostgreSQL service not running\n";
        echo "2. Wrong host/port\n";
        echo "3. Firewall blocking port 5432\n";
    }
}

echo "\n=== TEST COMPLETE ===\n";
echo "</pre>";
?>