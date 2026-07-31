ALTER TABLE servers
    ADD COLUMN IF NOT EXISTS enabled TINYINT(1) NOT NULL DEFAULT 1 AFTER hostname;

CREATE INDEX IF NOT EXISTS idx_servers_enabled ON servers (enabled);
