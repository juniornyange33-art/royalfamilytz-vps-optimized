<?php
// Force maximum error reporting to catch parse & syntax crashes
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

echo "<h2>PHP Debug Output</h2>";
echo "<p>PHP Version: " . phpversion() . "</p>";

// 1. Check required include files
$required_files = ['db.php', 'clickpesa.php', 'google.php', 'mailer.php'];
echo "<h3>1. Checking Required Files:</h3><ul>";
foreach ($required_files as $file) {
    if (file_exists(__DIR__ . '/' . $file)) {
        echo "<li style='color:green;'>✓ {$file} exists</li>";
    } else {
        echo "<li style='color:red;'>✗ {$file} IS MISSING!</li>";
    }
}
echo "</ul>";

// 2. Try isolating index.php to capture syntax/parse errors
echo "<h3>2. Testing index.php Execution:</h3>";
if (!file_exists(__DIR__ . '/index.php')) {
    echo "<p style='color:red;'>index.php does not exist in this directory!</p>";
} else {
    try {
        include_once __DIR__ . '/index.php';
    } catch (Throwable $e) {
        echo "<div style='background:#f8d7da; color:#721c24; padding:15px; border-radius:5px;'>";
        echo "<strong>Fatal Error Captured:</strong><br>";
        echo "<strong>Message:</strong> " . htmlspecialchars($e->getMessage()) . "<br>";
        echo "<strong>File:</strong> " . htmlspecialchars($e->getFile()) . "<br>";
        echo "<strong>Line:</strong> " . $e->getLine();
        echo "</div>";
    }
}
