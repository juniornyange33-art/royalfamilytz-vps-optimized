<?php
declare(strict_types=1);

require_once __DIR__ . '/db.php';

try {
    $pdo = db();
    
    // Attempt to add the 'name' column directly
    $pdo->exec("ALTER TABLE users ADD COLUMN name VARCHAR(255) NULL AFTER id;");
    echo "<h2 style='color:green;'>SUCCESS: Added 'name' column to users table!</h2>";
} catch (Throwable $e) {
    // If column already exists, treat as success
    if (str_contains($e->getMessage(), 'Duplicate column name') || str_contains($e->getMessage(), '1060')) {
        echo "<h2 style='color:green;'>SUCCESS: Column 'name' already exists!</h2>";
    } else {
        echo "<h2 style='color:red;'>ERROR:</h2> " . $e->getMessage();
    }
}
