<?php
require 'vendor/autoload.php';

use Illuminate\Database\Capsule\Manager as Capsule;

echo "🔍 DATABASE CONNECTION TEST\n";
echo str_repeat("=", 50) . "\n\n";

try {
    // Capsule configuration
    $capsule = new Capsule;
    
    $capsule->addConnection([
        'driver' => 'pgsql',
        'host' => 'localhost',
        'port' => '5432',
        'database' => 'ahhada_s1',
        'username' => 'ahhada_s1',
        'password' => 'Sinurhira42zihni',
        'charset' => 'utf8',
        'prefix' => '',
        'schema' => 'public',
        'sslmode' => 'prefer',
    ]);
    
    $capsule->setAsGlobal();
    $capsule->bootEloquent();
    
    // Test connection
    $pdo = $capsule->getConnection()->getPdo();
    echo "✅ PostgreSQL bağlantısı BAŞARILI!\n";
    
    // Get version
    $version = $capsule->select('SELECT version() as v');
    echo "📊 PostgreSQL Version: " . $version[0]->v . "\n";
    
    // Check tables
    $tables = $capsule->select("SELECT table_name FROM information_schema.tables WHERE table_schema = 'public'");
    
    if (count($tables) > 0) {
        echo "📋 Tablolar bulundu:\n";
        foreach ($tables as $table) {
            echo "   • " . $table->table_name . "\n";
        }
    } else {
        echo "⚠️  Tablo bulunamadı (migration gerekebilir)\n";
    }
    
    // Test models
    echo "\n🔧 MODELS TEST:\n";
    if (class_exists('App\\Models\\Conversation')) {
        echo "✅ Conversation model: Çalışıyor\n";
        
        // Try to count (if table exists)
        try {
            $count = App\Models\Conversation::count();
            echo "   📊 Conversation kayıt sayısı: $count\n";
        } catch (Exception $e) {
            echo "   ⚠️  Conversation tablosu yok veya boş: " . $e->getMessage() . "\n";
        }
    }
    
    if (class_exists('App\\Models\\Message')) {
        echo "✅ Message model: Çalışıyor\n";
    }
    
} catch (Exception $e) {
    echo "❌ Database hatası: " . $e->getMessage() . "\n";
    echo "🔧 Sorun giderme:\n";
    echo "1. .env dosyasını kontrol et\n";
    echo "2. PostgreSQL servisi çalışıyor mu?\n";
    echo "3. Kullanıcı yetkilerini kontrol et\n";
}

echo "\n" . str_repeat("=", 50) . "\n";
echo "📌 NOT: Tablolar yoksa migration çalıştırman gerekebilir.\n";
