INSERT INTO alert_rules (rule_key, name, source, severity, enabled, threshold_value)
VALUES
    ('hardware_smart_failed', 'Etat SMART en echec', 'hardware_health', 'critical', 1, NULL),
    ('hardware_smart_media_errors', 'Erreurs media SMART', 'hardware_health', 'critical', 1, 1),
    ('hardware_smart_wear_warning', 'Usure disque elevee', 'hardware_health', 'warning', 1, 80),
    ('hardware_smart_wear_critical', 'Usure disque critique', 'hardware_health', 'critical', 1, 95)
ON DUPLICATE KEY UPDATE
    name = VALUES(name),
    source = VALUES(source);
