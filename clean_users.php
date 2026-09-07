<?php
declare(strict_types=1);

require_once __DIR__ . '/db.php';

try {
    $pdo = db();

    // Disable foreign key checks to allow deleting referenced users safely
    $pdo->exec("SET FOREIGN_KEY_CHECKS = 0;");

    // Delete all users except your default admin account
    $pdo->exec("DELETE FROM users WHERE email != 'admin@royalfamilytz.org';");

    // Reset AUTO_INCREMENT back to start
    $pdo->exec("ALTER TABLE users AUTO_INCREMENT = 2;");

    // Re-enable foreign key checks
    $pdo->exec("SET FOREIGN_KEY_CHECKS = 1;");

    echo "<h1 style='color:green; text-align:center;'>ALL TEST USERS CLEARED!</h1>";
    echo "<p style='text-align:center;'>The users table has been reset. Only admin@royalfamilytz.org remains.</p>";

} catch (Throwable $e) {
    echo "<h1 style='color:red; text-align:center;'>CLEANUP FAILED</h1>";
    echo "<p style='text-align:center;'>" . htmlspecialchars($e->getMessage()) . "</p>";
}
