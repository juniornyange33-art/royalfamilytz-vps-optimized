<?php
declare(strict_types=1);

require_once __DIR__ . '/db.php';

try {
    $pdo = db();

    // Disable foreign key checks to allow dropping and recreating cleanly
    $pdo->exec("SET FOREIGN_KEY_CHECKS = 0;");

    // Drop old tables to eliminate type mismatches
    $pdo->exec("DROP TABLE IF EXISTS transactions;");
    $pdo->exec("DROP TABLE IF EXISTS contact_messages;");
    $pdo->exec("DROP TABLE IF EXISTS blog_posts;");
    $pdo->exec("DROP TABLE IF EXISTS users;");

    // 1. Users Table
    $pdo->exec("CREATE TABLE users (
      id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
      name VARCHAR(120) NOT NULL,
      email VARCHAR(190) NOT NULL UNIQUE,
      password_hash VARCHAR(255) NULL,
      google_id VARCHAR(190) NULL UNIQUE,
      avatar_url VARCHAR(500) NULL,
      profile_image VARCHAR(255) NULL,
      role ENUM('member','admin') NOT NULL DEFAULT 'member',
      membership_active TINYINT(1) NOT NULL DEFAULT 0,
      membership_id VARCHAR(40) NULL UNIQUE,
      bio TEXT NULL,
      email_verified_at DATETIME NULL,
      email_verification_token CHAR(64) NULL,
      email_verification_expires_at DATETIME NULL,
      created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;");

    // 2. Contact Messages Table
    $pdo->exec("CREATE TABLE contact_messages (
      id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
      name VARCHAR(120) NOT NULL,
      email VARCHAR(190) NOT NULL,
      message TEXT NOT NULL,
      created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;");

    // 3. Transactions Table
    $pdo->exec("CREATE TABLE transactions (
      id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
      user_id INT UNSIGNED NULL,
      type ENUM('donation','subscription') NOT NULL,
      tier VARCHAR(80) NULL,
      amount DECIMAL(12,2) NOT NULL,
      currency CHAR(3) NOT NULL DEFAULT 'TZS',
      method VARCHAR(40) NOT NULL,
      status ENUM('pending','paid','failed') NOT NULL DEFAULT 'pending',
      order_reference VARCHAR(80) NOT NULL UNIQUE,
      provider_ref VARCHAR(120) NULL,
      channel VARCHAR(80) NULL,
      failure_message VARCHAR(255) NULL,
      created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
      CONSTRAINT fk_transactions_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;");

    // 4. Blog Posts Table
    $pdo->exec("CREATE TABLE blog_posts (
      id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
      title VARCHAR(180) NOT NULL,
      excerpt TEXT NOT NULL,
      body TEXT NULL,
      published_at TIMESTAMP NULL,
      created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;");

    // Re-enable foreign key checks
    $pdo->exec("SET FOREIGN_KEY_CHECKS = 1;");

    // 5. Seed Default Admin User
    $insertStmt = $pdo->prepare("INSERT INTO users (name, email, password_hash, role) VALUES (?, ?, ?, ?)");
    $insertStmt->execute([
        'Administrator',
        'admin@royalfamilytz.org',
        '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC8J4cXxC5B9p9C7N9eK',
        'admin'
    ]);

    echo "<h1 style='color:green; text-align:center;'>DATABASE REBUILT SUCCESSFULLY!</h1>";
    echo "<p style='text-align:center;'>All tables matching local schema created and seeded.</p>";

} catch (Throwable $e) {
    echo "<h1 style='color:red; text-align:center;'>INITIALIZATION ERROR</h1>";
    echo "<p style='text-align:center;'>" . htmlspecialchars($e->getMessage()) . "</p>";
}
