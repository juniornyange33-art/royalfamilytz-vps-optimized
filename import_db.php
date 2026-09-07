<?php
$host = getenv('DB_HOST');
$port = getenv('DB_PORT') ?: '26708';
$user = getenv('DB_USER');
$pass = getenv('DB_PASS');
$dbname = getenv('DB_NAME');

echo "<h2>Starting Migration Import...</h2>";

try {
    $pdo = new PDO("mysql:host=$host;port=$port;dbname=$dbname;charset=utf8mb4", $user, $pass, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION
    ]);

    // Exclude database.sql since it already ran successfully
    $files = [
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
            echo "Skipped: $file (not found)<br>";
            continue;
        }

        $sqlContent = file_get_contents($file);
        // Clean IF NOT EXISTS syntax for ADD COLUMN if present
        $cleanedSql = str_replace('ADD COLUMN IF NOT EXISTS', 'ADD COLUMN', $sqlContent);
        
        $statements = array_filter(array_map('trim', explode(';', $cleanedSql)));

        foreach ($statements as $stmt) {
            if (empty($stmt)) continue;
            try {
                $pdo->exec($stmt);
            } catch (PDOException $e) {
                // Ignore "duplicate column" or "already exists" errors gracefully
                if ($e->getCode() == '42S21' || strpos($e->getMessage(), 'Duplicate column') !== false) {
                    continue;
                }
            }
        }
        echo "<strong style='color:green;'>Successfully executed:</strong> $file<br>";
    }

    echo "<h3 style='color:blue;'>All migrations finished!</h3>";

} catch (PDOException $e) {
    echo "<h3 style='color:red;'>Database Error:</h3> " . $e->getMessage();
}
