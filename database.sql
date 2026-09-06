-- Royal Family TZ Production Database Schema
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
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_users_email (email)
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
  type ENUM('donation','subscription','trip') NOT NULL,
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
  CONSTRAINT fk_transactions_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL,
  INDEX idx_transactions_user_id (user_id),
  INDEX idx_transactions_status (status),
  INDEX idx_transactions_reference (order_reference)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS blog_posts (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  title VARCHAR(180) NOT NULL,
  excerpt TEXT NOT NULL,
  body TEXT NULL,
  published_at TIMESTAMP NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_blog_published (published_at)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS events (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  title VARCHAR(180) NOT NULL,
  description TEXT NOT NULL,
  event_date DATETIME NULL,
  location VARCHAR(180) NULL,
  published TINYINT(1) NOT NULL DEFAULT 1,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS notifications (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  title VARCHAR(180) NOT NULL,
  message TEXT NOT NULL,
  audience ENUM('all','members','admins') NOT NULL DEFAULT 'all',
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS trips (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  title VARCHAR(180) NOT NULL,
  slug VARCHAR(180) NOT NULL UNIQUE,
  description TEXT NOT NULL,
  destination VARCHAR(180) NOT NULL,
  trip_date DATE NULL,
  meeting_point VARCHAR(180) NULL,
  poster_image VARCHAR(255) NULL,
  published TINYINT(1) NOT NULL DEFAULT 1,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS trip_packages (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  trip_id INT UNSIGNED NOT NULL,
  name VARCHAR(120) NOT NULL,
  description TEXT NOT NULL,
  price DECIMAL(12,2) NOT NULL,
  capacity INT UNSIGNED NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_trip_packages_trip FOREIGN KEY (trip_id) REFERENCES trips(id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS trip_bookings (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  user_id INT UNSIGNED NOT NULL,
  trip_id INT UNSIGNED NOT NULL,
  package_id INT UNSIGNED NOT NULL,
  transaction_id INT UNSIGNED NULL,
  guests INT UNSIGNED NOT NULL DEFAULT 1,
  status ENUM('pending','paid','cancelled') NOT NULL DEFAULT 'pending',
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_trip_bookings_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
  CONSTRAINT fk_trip_bookings_trip FOREIGN KEY (trip_id) REFERENCES trips(id) ON DELETE CASCADE,
  CONSTRAINT fk_trip_bookings_package FOREIGN KEY (package_id) REFERENCES trip_packages(id) ON DELETE CASCADE,
  CONSTRAINT fk_trip_bookings_transaction FOREIGN KEY (transaction_id) REFERENCES transactions(id) ON DELETE SET NULL
) ENGINE=InnoDB;

-- Default Admin (password: password)
INSERT INTO users (name, email, password_hash, role)
SELECT 'Administrator', 'admin@royalfamilytz.org', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC8J4cXxC5B9p9C7N9eK', 'admin'
WHERE NOT EXISTS (SELECT 1 FROM users WHERE email = 'admin@royalfamilytz.org');
