<?php
// test.php - ÇOK BASİT
echo "PHP çalışıyor!<br>";
echo "PHP Version: " . phpversion() . "<br>";

// Session test
if (session_status() === PHP_SESSION_NONE) {
    echo "Session başlatılıyor...<br>";
    session_start();
    echo "Session ID: " . session_id() . "<br>";
} else {
    echo "Session zaten başlatılmış<br>";
    echo "Session ID: " . session_id() . "<br>";
}

// Simple form
?>
<form method="POST" action="test.php">
    <input type="text" name="test" value="test">
    <button type="submit">Test</button>
</form>
<?php
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    echo "POST çalıştı: " . $_POST['test'];
}
?>