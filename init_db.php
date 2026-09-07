<?php
declare(strict_types=1);

require_once __DIR__ . '/db.php';

try {
    $pdo = db();
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
    echo "SUCCESS: 'users' table is verified and ready!";
} catch (Throwable $e) {
    echo "ERROR: " . $e->getMessage();
}
