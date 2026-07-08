INSERT INTO alert_rules (rule_key, name, source, severity, enabled, threshold_value)
VALUES ('security_check_error', 'Check securite en erreur', 'security', 'warning', 1, NULL)
ON DUPLICATE KEY UPDATE
    name = VALUES(name),
    source = VALUES(source);
