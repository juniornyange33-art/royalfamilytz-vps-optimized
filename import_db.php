<?php
$host = getenv('DB_HOST');
$port = getenv('DB_PORT') ?: '26708';
$user = getenv('DB_USER');
$pass = getenv('DB_PASS');
$dbname = getenv('DB_NAME') ?: 'defaultdb';

try {
    $pdo = new PDO("mysql:host=$host;port=$port;dbname=$dbname;charset=utf8mb4", $user, $pass, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION
    ]);

    echo "<h3>Connected to Database: $dbname on $host</h3>";

    if (file_exists('database.sql')) {
        $sql = file_get_contents('database.sql');
        
        // Remove comments and split by semicolon
        $statements = array_filter(array_map('trim', explode(';', $sql)));

        foreach ($statements as $stmt) {
            if (!empty($stmt)) {
                try {
                    $pdo->exec($stmt);
                } catch (PDOException $e) {
                    echo "<p style='color:orange;'>Query Notice: " . $e->getMessage() . "</p>";
                }
            }
        }
        echo "<h2 style='color:green;'>Import Finished! Checking existing tables:</h2>";
        
        $tables = $pdo->query("SHOW TABLES")->fetchAll(PDO::FETCH_COLUMN);
        echo "<ul>";
        foreach ($tables as $table) {
            echo "<li><strong>$table</strong></li>";
        }
        echo "</ul>";

    } else {
        echo "<h2 style='color:red;'>database.sql file not found!</h2>";
    }
} catch (PDOException $e) {
    echo "<h2 style='color:red;'>Connection Error:</h2> " . $e->getMessage();
}
