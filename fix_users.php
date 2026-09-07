<?php
$host = getenv('DB_HOST') ?: 'mysql-1b6a03ce-juniornyange33-b2e7.aivencloud.com';
$port = getenv('DB_PORT') ?: '26708';
$user = getenv('DB_USER') ?: 'avnadmin';
$pass = getenv('DB_PASS');
$dbname = getenv('DB_NAME') ?: 'defaultdb';

try {
    $pdo = new PDO("mysql:host=$host;port=$port;dbname=$dbname;charset=utf8mb4", $user, $pass, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION
    ]);

    $sql = "CREATE TABLE IF NOT EXISTS users (
        id INT AUTO_INCREMENT PRIMARY KEY,
        full_name VARCHAR(255) NOT NULL,
        email VARCHAR(255) UNIQUE NOT NULL,
        password VARCHAR(255) NOT NULL,
        role VARCHAR(50) DEFAULT 'user',
        is_verified TINYINT(1) DEFAULT 0,
        verification_token VARCHAR(255) NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;";

    $pdo->exec($sql);
    echo "<h2 style='color:green;'>Table 'users' successfully created or verified!</h2>";
} catch (PDOException $e) {
    echo "<h2 style='color:red;'>Database Error:</h2> " . $e->getMessage();
}
