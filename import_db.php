<?php
$host = getenv('DB_HOST');
$port = getenv('DB_PORT') ?: '26708';
$user = getenv('DB_USER');
$pass = getenv('DB_PASS');
$dbname = getenv('DB_NAME');

try {
    $pdo = new PDO("mysql:host=$host;port=$port;dbname=$dbname;charset=utf8mb4", $user, $pass, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION
    ]);

    if (file_exists('database.sql')) {
        $sql = file_get_contents('database.sql');
        $pdo->exec($sql);
        echo "<h2 style='color:green;'>SUCCESS: database.sql and users table imported!</h2>";
    } else {
        echo "<h2 style='color:red;'>ERROR: database.sql not found in root directory!</h2>";
    }
} catch (PDOException $e) {
    echo "<h2 style='color:red;'>Database Error:</h2> " . $e->getMessage();
}
