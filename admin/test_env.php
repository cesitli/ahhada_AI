<?php
// admin/test_env.php
echo "<pre>";
echo "=== ENV TEST ===\n";
echo "DB_USER: " . ($_ENV['DB_USER'] ?? 'NOT SET') . "\n";
echo "DB_PASS: " . (isset($_ENV['DB_PASS']) ? 'SET (hidden)' : 'NOT SET') . "\n";
echo "ADMIN_USERNAME: " . ($_ENV['ADMIN_USERNAME'] ?? 'NOT SET') . "\n";

// Tüm ENV'yi göster (şifreler hariç)
echo "\nAll ENV:\n";
foreach ($_ENV as $key => $value) {
    if (strpos($key, 'PASS') === false && strpos($key, 'KEY') === false) {
        echo "$key: $value\n";
    }
}
echo "</pre>";