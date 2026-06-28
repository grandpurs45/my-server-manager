INSERT INTO alert_rules (rule_key, name, source, severity, enabled, threshold_value)
VALUES
    ('home_assistant_check_error', 'Check Home Assistant en erreur', 'home_assistant', 'warning', 1, NULL),
    ('home_assistant_check_stale', 'Check Home Assistant trop ancien', 'home_assistant', 'warning', 1, 60),
    ('home_assistant_core_update_available', 'Update Home Assistant Core disponible', 'home_assistant', 'warning', 1, NULL),
    ('home_assistant_supervisor_update_available', 'Update Home Assistant Supervisor disponible', 'home_assistant', 'warning', 1, NULL),
    ('home_assistant_os_update_available', 'Update Home Assistant OS disponible', 'home_assistant', 'warning', 1, NULL)
ON DUPLICATE KEY UPDATE
    name = VALUES(name),
    source = VALUES(source);
