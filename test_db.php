<?php
// test_db.php
require_once 'vendor/autoload.php';

use App\Config\Database;

echo "=== Database Connection Test ===\n";

try {
    // 1. Database bağlantısı
    $pdo = Database::getConnection();
    echo "✅ Database connection successful\n";
    
    // 2. PostgreSQL version
    $stmt = $pdo->query("SELECT version()");
    $version = $stmt->fetch()['version'];
    echo "📊 PostgreSQL: " . $version . "\n";
    
    // 3. Tablo kontrolü
    $tables = ['users', 'conversations', 'messages'];
    foreach ($tables as $table) {
        $sql = "SELECT COUNT(*) as count FROM $table";
        try {
            $stmt = $pdo->query($sql);
            $count = $stmt->fetch()['count'];
            echo "   📈 Table '$table': $count rows\n";
        } catch (Exception $e) {
            echo "   ⚠️ Table '$table': " . $e->getMessage() . "\n";
        }
    }
    
    // 4. Database::executeQuery test
    $testSql = "SELECT NOW() as current_time";
    $stmt = Database::executeQuery($testSql);
    $result = $stmt->fetch();
    echo "⏰ Current DB time: " . $result['current_time'] . "\n";
    
    echo "\n🎉 Database connection is fully functional!\n";
    
} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
    echo "Stack trace:\n" . $e->getTraceAsString() . "\n";
}