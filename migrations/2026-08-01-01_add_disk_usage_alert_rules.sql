INSERT INTO alert_rules (rule_key, name, source, severity, enabled, threshold_value)
VALUES
    ('disk_usage_warning', 'Espace disque utilise eleve', 'supervision', 'warning', 1, 85),
    ('disk_usage_critical', 'Espace disque utilise critique', 'supervision', 'critical', 1, 95)
ON DUPLICATE KEY UPDATE
    name = VALUES(name),
    source = VALUES(source);
