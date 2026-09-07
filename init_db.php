<?php
declare(strict_types=1);

require_once __DIR__ . '/db.php';

try {
    $pdo = db();
    
    // Add 'name' column if missing, or update schema
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS users (
            id INT AUTO_INCREMENT PRIMARY KEY,
            name VARCHAR(255) NULL,
            full_name VARCHAR(255) NULL,
            email VARCHAR(255) UNIQUE NOT NULL,
            password VARCHAR(255) NOT NULL,
            role VARCHAR(50) DEFAULT 'user',
            is_verified TINYINT(1) DEFAULT 0,
            verification_token VARCHAR(255) NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
    ");

    // Ensure 'name' column exists if table was already created
    try {
        $pdo->exec("ALTER TABLE users ADD COLUMN name VARCHAR(255) NULL AFTER id;");
    } catch (Throwable $e) {
        // Column already exists
    }

    echo "<h2 style='color:green;'>SUCCESS: Table schema updated with 'name' column!</h2>";
} catch (Throwable $e) {
    echo "<h2 style='color:red;'>ERROR:</h2> " . $e->getMessage();
}
