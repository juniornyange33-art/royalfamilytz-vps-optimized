USE royalfamilytz;
ALTER TABLE contact_messages ADD COLUMN status ENUM('new','replied') NOT NULL DEFAULT 'new';
ALTER TABLE contact_messages ADD COLUMN replied_at DATETIME NULL;
