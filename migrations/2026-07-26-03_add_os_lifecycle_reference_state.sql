ALTER TABLE os_lifecycle_references
    ADD COLUMN support_state VARCHAR(20) NOT NULL DEFAULT 'unknown' AFTER support_ends_at;

UPDATE os_lifecycle_references
SET support_state = 'supported'
WHERE support_ends_at IS NULL
  AND source LIKE 'endoflife.date/%';
