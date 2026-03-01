<?php
// admin/debug.php
echo "<pre>";
echo "=== SESSION DEBUG ===\n";
echo "Session ID: " . session_id() . "\n";
echo "Session Status: " . session_status() . "\n";
echo "Session Data:\n";
print_r($_SESSION);
echo "\n=== COOKIE DEBUG ===\n";
print_r($_COOKIE);
echo "\n=== POST DEBUG ===\n";
print_r($_POST);
echo "\n=== SERVER DEBUG ===\n";
echo "REQUEST_URI: " . $_SERVER['REQUEST_URI'] . "\n";
echo "PHP_SELF: " . $_SERVER['PHP_SELF'] . "\n";
echo "</pre>";

// Session başlatmak için link
if (session_status() === PHP_SESSION_NONE) {
    echo "<p>Session başlatılmamış. <a href='?start=1'>Session Başlat</a></p>";
    if (isset($_GET['start'])) {
        session_start();
        $_SESSION['test'] = 'Test değeri';
        header('Location: debug.php');
        exit;
    }
}
?>