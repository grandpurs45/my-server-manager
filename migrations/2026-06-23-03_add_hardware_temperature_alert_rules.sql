INSERT INTO alert_rules (rule_key, name, source, severity, enabled, threshold_value)
VALUES
    ('hardware_temperature_warning', 'Temperature materielle elevee', 'hardware_health', 'warning', 1, 70),
    ('hardware_temperature_critical', 'Temperature materielle critique', 'hardware_health', 'critical', 1, 85)
ON DUPLICATE KEY UPDATE
    name = VALUES(name),
    source = VALUES(source);
