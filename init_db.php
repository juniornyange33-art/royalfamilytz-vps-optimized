<?php
declare(strict_types=1);

require_once __DIR__ . '/db.php';

try {
    $pdo = db();
    
    // Add password_hash column if missing
    try {
        $pdo->exec("ALTER TABLE users ADD COLUMN password_hash VARCHAR(255) NULL AFTER password;");
    } catch (Throwable $e) {}

    // Add name column if missing
    try {
        $pdo->exec("ALTER TABLE users ADD COLUMN name VARCHAR(255) NULL AFTER id;");
    } catch (Throwable $e) {}

    echo "<h2 style='color:green;'>SUCCESS: Table columns updated!</h2>";
} catch (Throwable $e) {
    echo "<h2 style='color:red;'>ERROR:</h2> " . $e->getMessage();
}
