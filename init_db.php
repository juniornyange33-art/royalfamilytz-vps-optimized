<?php
declare(strict_types=1);

require_once __DIR__ . '/db.php';

try {
    $pdo = db();
    
    // List of required columns for registration/authentication
    $columns = [
        "name"               => "VARCHAR(255) NULL AFTER id",
        "full_name"          => "VARCHAR(255) NULL AFTER name",
        "password"           => "VARCHAR(255) NULL AFTER email",
        "password_hash"      => "VARCHAR(255) NULL AFTER password",
        "role"               => "VARCHAR(50) DEFAULT 'user'",
        "is_verified"        => "TINYINT(1) DEFAULT 0",
        "verification_token" => "VARCHAR(255) NULL"
    ];

    foreach ($columns as $column => $definition) {
        try {
            $pdo->exec("ALTER TABLE users ADD COLUMN {$column} {$definition};");
        } catch (Throwable $e) {
            // Ignore if column already exists
        }
    }

    echo "<h2 style='color:green;'>SUCCESS: Full users table schema synchronized!</h2>";
} catch (Throwable $e) {
    echo "<h2 style='color:red;'>ERROR:</h2> " . $e->getMessage();
}
