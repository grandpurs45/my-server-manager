ALTER TABLE alerts
    ADD COLUMN IF NOT EXISTS acknowledged_at DATETIME NULL AFTER resolved_at,
    ADD COLUMN IF NOT EXISTS acknowledged_by_user_id INT NULL AFTER acknowledged_at,
    ADD COLUMN IF NOT EXISTS acknowledged_comment TEXT NULL AFTER acknowledged_by_user_id,
    ADD COLUMN IF NOT EXISTS ignored_at DATETIME NULL AFTER acknowledged_comment,
    ADD COLUMN IF NOT EXISTS ignored_by_user_id INT NULL AFTER ignored_at,
    ADD COLUMN IF NOT EXISTS ignored_comment TEXT NULL AFTER ignored_by_user_id;
