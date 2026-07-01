ALTER TABLE servers
ADD COLUMN IF NOT EXISTS ping_packets_sent INT DEFAULT NULL,
ADD COLUMN IF NOT EXISTS ping_packets_received INT DEFAULT NULL,
ADD COLUMN IF NOT EXISTS ping_loss_percent DECIMAL(5,2) DEFAULT NULL,
ADD COLUMN IF NOT EXISTS latency_min_ms INT DEFAULT NULL,
ADD COLUMN IF NOT EXISTS latency_max_ms INT DEFAULT NULL;

ALTER TABLE server_metrics
MODIFY COLUMN type ENUM('ping', 'ping_loss', 'cpu', 'ram', 'disk', 'availability') NOT NULL;

INSERT INTO settings (category, setting_key, setting_value)
VALUES
    ('supervision', 'ping_packet_count', '4'),
    ('supervision', 'ping_timeout_seconds', '1')
ON DUPLICATE KEY UPDATE setting_value = setting_value;

INSERT INTO alert_rules (rule_key, name, source, severity, enabled, threshold_value)
VALUES
    ('ping_packet_loss', 'Perte de ping', 'supervision', 'warning', 1, 25),
    ('ping_latency_high', 'Latence ping elevee', 'supervision', 'warning', 1, 100)
ON DUPLICATE KEY UPDATE
    name = VALUES(name),
    source = VALUES(source);
