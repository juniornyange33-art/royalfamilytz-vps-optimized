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

    $pdo->exec("SET FOREIGN_KEY_CHECKS = 0;");
    $pdo->exec("TRUNCATE TABLE users;");
    $pdo->exec("SET FOREIGN_KEY_CHECKS = 1;");

    echo "<h2 style='color:green;'>Users table cleared successfully! You can sign up again.</h2>";
} catch (PDOException $e) {
    echo "<h2 style='color:red;'>Database Error:</h2> " . $e->getMessage();
}
