ALTER TABLE servers
    ADD COLUMN IF NOT EXISTS hardware_profile VARCHAR(30) NOT NULL DEFAULT 'unknown' AFTER target_type;
