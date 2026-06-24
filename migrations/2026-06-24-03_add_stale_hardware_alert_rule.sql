INSERT INTO alert_rules (rule_key, name, source, severity, enabled, threshold_value)
VALUES
    ('stale_hardware_health_check', 'Check materiel trop ancien', 'hardware_health', 'warning', 1, 45)
ON DUPLICATE KEY UPDATE
    name = VALUES(name),
    source = VALUES(source);
