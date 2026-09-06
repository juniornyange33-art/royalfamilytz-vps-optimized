USE royalfamilytz;

ALTER TABLE users
  MODIFY password_hash VARCHAR(255) NULL,
  ADD COLUMN google_id VARCHAR(190) NULL,
  ADD COLUMN avatar_url VARCHAR(500) NULL,
  ADD COLUMN profile_image VARCHAR(255) NULL;

ALTER TABLE users ADD UNIQUE KEY uq_users_google_id (google_id);
