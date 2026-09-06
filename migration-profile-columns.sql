USE royalfamilytz;

-- Run this once if your existing users table does not yet have these fields.
ALTER TABLE users ADD COLUMN IF NOT EXISTS profile_image VARCHAR(255) NULL;
ALTER TABLE users ADD COLUMN IF NOT EXISTS bio TEXT NULL;
