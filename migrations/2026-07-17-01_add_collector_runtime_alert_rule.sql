INSERT INTO alert_rules (rule_key, name, source, severity, enabled, threshold_value)
VALUES (
    'collector_execution_stale',
    'Collecteur planifie trop ancien',
    'maintenance',
    'warning',
    1,
    NULL
)
ON DUPLICATE KEY UPDATE
    name = VALUES(name),
    source = VALUES(source);

INSERT INTO alert_rules (rule_key, name, source, severity, enabled, threshold_value)
VALUES (
    'collector_execution_error',
    'Derniere execution collecteur en erreur',
    'maintenance',
    'warning',
    1,
    NULL
)
ON DUPLICATE KEY UPDATE
    name = VALUES(name),
    source = VALUES(source);
