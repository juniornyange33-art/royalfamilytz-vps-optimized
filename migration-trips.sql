USE royalfamilytz;

ALTER TABLE transactions MODIFY type ENUM('donation','subscription','trip') NOT NULL;

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

ALTER TABLE trips ADD COLUMN IF NOT EXISTS poster_image VARCHAR(255) NULL;

INSERT INTO trips (title, slug, description, destination, trip_date, meeting_point, published)
SELECT 'Chemka Trip', 'chemka-trip', 'A refreshing community day at the Chemka hot springs with transport, connection, and shared memories.', 'Chemka Hot Springs, Kilimanjaro', '2026-09-12', 'Royal Family TZ meeting point, Dar es Salaam', 1
WHERE NOT EXISTS (SELECT 1 FROM trips WHERE slug = 'chemka-trip');

INSERT INTO trip_packages (trip_id, name, description, price, capacity)
SELECT id, 'Day Pass', 'Entry, shared transport, and community lunch.', 75000, 40 FROM trips WHERE slug = 'chemka-trip' AND NOT EXISTS (SELECT 1 FROM trip_packages p WHERE p.trip_id = trips.id AND p.name = 'Day Pass');
INSERT INTO trip_packages (trip_id, name, description, price, capacity)
SELECT id, 'Comfort Package', 'Day Pass plus reserved seat, refreshments, and activity kit.', 120000, 20 FROM trips WHERE slug = 'chemka-trip' AND NOT EXISTS (SELECT 1 FROM trip_packages p WHERE p.trip_id = trips.id AND p.name = 'Comfort Package');
INSERT INTO trip_packages (trip_id, name, description, price, capacity)
SELECT id, 'Family Package', 'Two adults and two children with shared transport and lunch.', 250000, 10 FROM trips WHERE slug = 'chemka-trip' AND NOT EXISTS (SELECT 1 FROM trip_packages p WHERE p.trip_id = trips.id AND p.name = 'Family Package');
