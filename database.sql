CREATE DATABASE IF NOT EXISTS royalfamilytz CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE royalfamilytz;

CREATE TABLE IF NOT EXISTS users (
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
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS contact_messages (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(120) NOT NULL,
  email VARCHAR(190) NOT NULL,
  message TEXT NOT NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS transactions (
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
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS blog_posts (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  title VARCHAR(180) NOT NULL,
  excerpt TEXT NOT NULL,
  body TEXT NULL,
  published_at TIMESTAMP NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

INSERT INTO users (name, email, password_hash, role)
SELECT 'Administrator', 'admin@royalfamilytz.org', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC8J4cXxC5B9p9C7N9eK', 'admin'
WHERE NOT EXISTS (SELECT 1 FROM users WHERE email = 'admin@royalfamilytz.org');
-- Demo admin password: password. Change it immediately in production.
