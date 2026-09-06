USE royalfamilytz;

ALTER TABLE users ADD COLUMN IF NOT EXISTS email_verified_at DATETIME NULL;
ALTER TABLE users ADD COLUMN IF NOT EXISTS email_verification_token CHAR(64) NULL;
ALTER TABLE users ADD COLUMN IF NOT EXISTS email_verification_expires_at DATETIME NULL;

-- Keep the seeded administrator usable while normal members verify their email.
UPDATE users SET email_verified_at = NOW()
WHERE email = 'admin@royalfamilytz.org' AND email_verified_at IS NULL;
