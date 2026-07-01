INSERT INTO alert_rules (rule_key, name, source, severity, enabled, threshold_value)
VALUES ('os_lifecycle_unknown', 'Cycle de vie OS inconnu', 'os_lifecycle', 'info', 1, NULL)
ON DUPLICATE KEY UPDATE
    name = VALUES(name),
    source = VALUES(source);
