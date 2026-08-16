ALTER TABLE api_sources
    ADD COLUMN IF NOT EXISTS discovery_interval_minutes INT NOT NULL DEFAULT 60 AFTER timeout_seconds,
    ADD COLUMN IF NOT EXISTS next_discovery_at DATETIME NULL AFTER last_discovered_at;
