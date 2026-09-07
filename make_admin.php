<?php
declare(strict_types=1);

require_once __DIR__ . '/db.php';

// Your account email address
$adminEmail = 'juniornyange33@gmail.com'; 

try {
    $pdo = db();

    $stmt = $pdo->prepare("UPDATE users SET role = 'admin', membership_active = 1 WHERE email = ?");
    $stmt->execute([$adminEmail]);

    if ($stmt->rowCount() > 0) {
        echo "<h1 style='color:green; text-align:center;'>SUCCESS: " . htmlspecialchars($adminEmail) . " is now an Admin!</h1>";
    } else {
        echo "<h1 style='color:orange; text-align:center;'>NO CHANGES: User not found or already an Admin.</h1>";
    }
} catch (Throwable $e) {
    echo "<h1 style='color:red; text-align:center;'>ERROR</h1>";
    echo "<p style='text-align:center;'>" . htmlspecialchars($e->getMessage()) . "</p>";
}
