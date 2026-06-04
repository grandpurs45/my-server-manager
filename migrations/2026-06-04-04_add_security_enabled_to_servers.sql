ALTER TABLE servers
    ADD COLUMN IF NOT EXISTS security_enabled TINYINT(1) NOT NULL DEFAULT 0;

UPDATE servers
SET security_enabled = 1
WHERE ssh_enabled = 1
  AND target_type IN ('linux', 'proxmox', 'docker');
