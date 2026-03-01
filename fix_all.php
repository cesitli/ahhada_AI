<?php
// /s3/fix_all.php

error_reporting(E_ALL);
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);

echo "<pre>";
echo "FIXING ALL ISSUES\n";
echo "=================\n\n";

// 1. Check current structure
echo "1. Checking current structure...\n";

// src/ dizini var mı?
if (is_dir('src')) {
    echo "  ✓ src/ directory exists\n";
    
    // src altındaki klasörleri listele
    $srcDirs = array_filter(glob('src/*'), 'is_dir');
    foreach ($srcDirs as $dir) {
        echo "  - " . basename($dir) . "/\n";
    }
} else {
    echo "  ✗ src/ directory missing - creating...\n";
    mkdir('src', 0755, true);
}

// 2. Create missing Config class
echo "\n2. Creating missing Config class...\n";

$configContent = <<<'PHP'
<?php

namespace App\Config;

use Dotenv\Dotenv;

class Config
{
    private static array $config = [];
    
    public static function load(): void
    {
        if (!empty(self::$config)) {
            return;
        }
        
        // Load .env
        $dotenv = Dotenv::createImmutable(__DIR__ . '/../..');
        $dotenv->load();
        
        self::$config = [
            'database' => [
                'host' => $_ENV['DB_HOST'] ?? 'localhost',
                'port' => $_ENV['DB_PORT'] ?? 5432,
                'database' => $_ENV['DB_DATABASE'],
                'username' => $_ENV['DB_USERNAME'],
                'password' => $_ENV['DB_PASSWORD']
            ],
            'openai' => [
                'api_key' => $_ENV['OPENAI_API_KEY'] ?? '',
                'model' => $_ENV['OPENAI_MODEL'] ?? 'gpt-4'
            ],
            'system' => [
                'timezone' => $_ENV['TIMEZONE'] ?? 'UTC'
            ]
        ];
    }
    
    public static function get(string $key, $default = null)
    {
        self::load();
        
        $keys = explode('.', $key);
        $value = self::$config;
        
        foreach ($keys as $k) {
            if (!isset($value[$k])) {
                return $default;
            }
            $value = $value[$k];
        }
        
        return $value;
    }
}
PHP;

$configDir = 'src/Config';
if (!is_dir($configDir)) {
    mkdir($configDir, 0755, true);
}

if (!file_exists("$configDir/Config.php")) {
    file_put_contents("$configDir/Config.php", $configContent);
    echo "  ✓ Created Config.php\n";
} else {
    echo "  ✓ Config.php already exists\n";
}

// 3. Create missing Database class
echo "\n3. Checking Database class...\n";

$databaseContent = <<<'PHP'
<?php

namespace App\Config;

use PDO;
use PDOException;

class Database
{
    private static ?PDO $connection = null;
    
    public static function getConnection(): PDO
    {
        if (self::$connection === null) {
            $config = Config::get('database');
            
            $dsn = "pgsql:host={$config['host']};port={$config['port']};dbname={$config['database']}";
            
            try {
                self::$connection = new PDO($dsn, $config['username'], $config['password'], [
                    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                    PDO::ATTR_PERSISTENT => true
                ]);
                
                // Set timezone
                self::$connection->exec("SET TIME ZONE 'UTC'");
                
            } catch (PDOException $e) {
                throw new \Exception("Database connection failed: " . $e->getMessage());
            }
        }
        
        return self::$connection;
    }
    
    public static function execute(string $sql, array $params = []): \PDOStatement
    {
        $stmt = self::getConnection()->prepare($sql);
        $stmt->execute($params);
        return $stmt;
    }
}
PHP;

if (!file_exists("$configDir/Database.php")) {
    file_put_contents("$configDir/Database.php", $databaseContent);
    echo "  ✓ Created Database.php\n";
} else {
    echo "  ✓ Database.php already exists\n";
}

// 4. Move existing files to correct locations
echo "\n4. Organizing existing files...\n";

// Eğer dosyalar kök dizindeyse, src/ altına taşı
$moveMappings = [
    'Config' => ['Config.php', 'Database.php'],
    'Models' => ['Conversation.php', 'Message.php', 'MemorySummary.php', 'User.php', 'EmbeddingCache.php', 'BackgroundJob.php'],
    'Controllers' => ['ConversationController.php', 'MessageController.php', 'ContextController.php', 'AIController.php'],
    'Services' => ['ContextBuilder.php', 'EmbeddingService.php', 'Summarizer.php', 'AIProviderManager.php', 'HealthCheckService.php'],
    'Utils' => ['TokenCalculator.php', 'Logger.php', 'Validator.php'],
    'Middleware' => ['AuthMiddleware.php']
];

foreach ($moveMappings as $category => $files) {
    $targetDir = "src/$category";
    if (!is_dir($targetDir)) {
        mkdir($targetDir, 0755, true);
        echo "  Created directory: $targetDir\n";
    }
    
    foreach ($files as $file) {
        // Kök dizinde mi?
        if (file_exists($file)) {
            rename($file, "$targetDir/$file");
            echo "  Moved: $file -> $targetDir/$file\n";
        }
        // config/, models/ gibi eski dizinlerde mi?
        elseif (file_exists(strtolower($category) . "/$file")) {
            rename(strtolower($category) . "/$file", "$targetDir/$file");
            echo "  Moved: " . strtolower($category) . "/$file -> $targetDir/$file\n";
        }
        // zaten src/ altında mı?
        elseif (file_exists("src/$category/$file")) {
            echo "  Already in place: src/$category/$file\n";
        }
    }
}

// 5. Fix namespace in all PHP files
echo "\n5. Fixing namespaces...\n";

function fixFileNamespace($file) {
    $content = file_get_contents($file);
    
    // Determine namespace based on directory
    $relativePath = str_replace(__DIR__ . '/', '', $file);
    
    if (strpos($relativePath, 'src/Config/') === 0) {
        $namespace = 'App\\Config';
    } elseif (strpos($relativePath, 'src/Models/') === 0) {
        $namespace = 'App\\Models';
    } elseif (strpos($relativePath, 'src/Controllers/') === 0) {
        $namespace = 'App\\Controllers';
    } elseif (strpos($relativePath, 'src/Services/') === 0) {
        $namespace = 'App\\Services';
    } elseif (strpos($relativePath, 'src/Utils/') === 0) {
        $namespace = 'App\\Utils';
    } elseif (strpos($relativePath, 'src/Middleware/') === 0) {
        $namespace = 'App\\Middleware';
    } else {
        return; // Skip
    }
    
    // Fix namespace declaration
    if (strpos($content, 'namespace ') === false) {
        // Add namespace at the beginning
        $content = "<?php\n\nnamespace $namespace;\n\n" . substr($content, 6);
    } else {
        // Replace existing namespace
        $content = preg_replace('/namespace\s+[^;]+;/', "namespace $namespace;", $content);
    }
    
    // Fix use statements
    $useStatements = [
        'use App\\Config\\' => 'use App\\Config\\',
        'use App\\Models\\' => 'use App\\Models\\',
        'use App\\Controllers\\' => 'use App\\Controllers\\',
        'use App\\Services\\' => 'use App\\Services\\',
        'use App\\Utils\\' => 'use App\\Utils\\',
        'use App\\Middleware\\' => 'use App\\Middleware\\',
    ];
    
    foreach ($useStatements as $old => $new) {
        $content = str_replace($old, $new, $content);
    }
    
    file_put_contents($file, $content);
    echo "  Fixed: $file\n";
}

// Find all PHP files in src/
$phpFiles = [];
$dirs = ['src/Config', 'src/Models', 'src/Controllers', 'src/Services', 'src/Utils', 'src/Middleware'];

foreach ($dirs as $dir) {
    if (is_dir($dir)) {
        $files = glob("$dir/*.php");
        $phpFiles = array_merge($phpFiles, $files);
    }
}

foreach ($phpFiles as $file) {
    fixFileNamespace($file);
}

// 6. Update composer autoload
echo "\n6. Updating composer autoload...\n";

// Ensure composer.json has correct autoload
$composerFile = 'composer.json';
if (file_exists($composerFile)) {
    $composer = json_decode(file_get_contents($composerFile), true);
    
    if (!isset($composer['autoload']['psr-4']['App\\'])) {
        $composer['autoload']['psr-4']['App\\'] = 'src/';
        file_put_contents($composerFile, json_encode($composer, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
        echo "  Updated composer.json\n";
    }
}

// Run composer dump-autoload
echo shell_exec('composer dump-autoload 2>&1');
echo "  ✓ Autoload dumped\n";

// 7. Create a working test file
echo "\n7. Creating working test...\n";

$testContent = <<<'PHP'
<?php
// /s3/working_test.php

error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "<pre>\n";
echo "WORKING TEST\n";
echo "============\n\n";

// Load composer
require_once 'vendor/autoload.php';

// Test 1: Basic classes
echo "1. Testing basic classes:\n";
$classes = [
    'App\Config\Config',
    'App\Config\Database',
    'Dotenv\Dotenv'
];

foreach ($classes as $class) {
    if (class_exists($class)) {
        echo "  ✓ $class\n";
    } else {
        echo "  ✗ $class\n";
    }
}

// Test 2: Load environment
echo "\n2. Testing environment:\n";
try {
    $dotenv = Dotenv\Dotenv::createImmutable(__DIR__);
    $dotenv->load();
    echo "  ✓ .env loaded\n";
    
    echo "  DB Host: " . ($_ENV['DB_HOST'] ?? 'NOT SET') . "\n";
    echo "  DB Database: " . ($_ENV['DB_DATABASE'] ?? 'NOT SET') . "\n";
    
} catch (Exception $e) {
    echo "  ✗ Environment error: " . $e->getMessage() . "\n";
}

// Test 3: Database connection
echo "\n3. Testing database:\n";
try {
    $db = App\Config\Database::getConnection();
    echo "  ✓ Database connected\n";
    
    // Simple query
    $stmt = $db->query("SELECT COUNT(*) as count FROM conversations");
    $result = $stmt->fetch();
    echo "  Conversations: " . $result['count'] . "\n";
    
} catch (Exception $e) {
    echo "  ✗ Database error: " . $e->getMessage() . "\n";
}

// Test 4: Check src directory structure
echo "\n4. Checking src structure:\n";
$srcDirs = ['Config', 'Models', 'Controllers', 'Services', 'Utils', 'Middleware'];
foreach ($srcDirs as $dir) {
    $path = "src/$dir";
    if (is_dir($path)) {
        $files = glob("$path/*.php");
        echo "  $dir: " . count($files) . " files\n";
    } else {
        echo "  $dir: MISSING\n";
    }
}

echo "\n✅ TEST COMPLETED\n";
echo "</pre>";
PHP;

file_put_contents('working_test.php', $testContent);
echo "  ✓ Created working_test.php\n";

// 8. Create simple index.php if missing
echo "\n8. Creating/checking index.php...\n";

if (!file_exists('index.php')) {
    $indexContent = <<<'PHP'
<?php
// /s3/index.php

error_reporting(E_ALL);
ini_set('display_errors', 1);

header('Content-Type: application/json; charset=utf-8');

try {
    require_once 'vendor/autoload.php';
    
    $dotenv = Dotenv\Dotenv::createImmutable(__DIR__);
    $dotenv->load();
    
    $db = App\Config\Database::getConnection();
    
    echo json_encode([
        'status' => 'running',
        'service' => 'AI Context System',
        'database' => 'connected',
        'timestamp' => date('Y-m-d H:i:s')
    ], JSON_PRETTY_PRINT);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'error' => $e->getMessage(),
        'file' => $e->getFile(),
        'line' => $e->getLine()
    ]);
}
PHP;
    
    file_put_contents('index.php', $indexContent);
    echo "  ✓ Created index.php\n";
} else {
    echo "  ✓ index.php exists\n";
}

echo "\n=================\n";
echo "FIX COMPLETED! 🎉\n";
echo "=================\n";
echo "\nNow run these tests:\n";
echo "1. https://ahhada.com/s3/working_test.php\n";
echo "2. https://ahhada.com/s3/index.php\n";
echo "3. Check if classes exist: php -r \"require_once 'vendor/autoload.php'; echo class_exists('App\\\\Config\\\\Config') ? 'OK' : 'FAIL';\"\n";
echo "</pre>";
?>