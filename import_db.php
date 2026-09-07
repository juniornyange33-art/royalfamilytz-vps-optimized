<?php
// Secure setup runner
$host = getenv('DB_HOST');
$port = getenv('DB_PORT') ?: '26708';
$user = getenv('DB_USER');
$pass = getenv('DB_PASS');
$dbname = getenv('DB_NAME');

echo "<h2>Starting Database Import...</h2>";

try {
    $pdo = new PDO("mysql:host=$host;port=$port;dbname=$dbname;charset=utf8mb4", $user, $pass, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION
    ]);

    $files = [
        'database.sql',
        'migration-clickpesa.sql',
        'migration-contact-inbox.sql',
        'migration-email-verification.sql',
        'migration-events-notifications.sql',
        'migration-profile-columns.sql',
        'migration-profile-google.sql',
        'migration-trips.sql'
    ];

    foreach ($files as $file) {
        if (!file_exists($file)) {
            echo "Skipped: $file (file not found)<br>";
            continue;
        }
        $sql = file_get_contents($file);
        $pdo->exec($sql);
        echo "<strong style='color:green;'>Successfully executed:</strong> $file<br>";
    }
    
    echo "<h3>Import Finished! Refresh your website now.</h3>";

} catch (PDOException $e) {
    echo "<h3 style='color:red;'>Database Error:</h3> " . $e->getMessage();
}
