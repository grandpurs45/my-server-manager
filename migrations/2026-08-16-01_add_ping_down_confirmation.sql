ALTER TABLE servers
    ADD COLUMN IF NOT EXISTS ping_consecutive_failures INT NOT NULL DEFAULT 0 AFTER ping_loss_percent;

UPDATE alert_rules
SET threshold_value = 2
WHERE rule_key = 'server_down'
  AND threshold_value IS NULL;
