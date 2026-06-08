CREATE TABLE IF NOT EXISTS alert_rules (
    id INT AUTO_INCREMENT PRIMARY KEY,
    rule_key VARCHAR(100) NOT NULL UNIQUE,
    name VARCHAR(150) NOT NULL,
    source VARCHAR(50) NOT NULL,
    severity VARCHAR(20) NOT NULL DEFAULT 'warning',
    enabled TINYINT(1) NOT NULL DEFAULT 1,
    threshold_value INT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_alert_rules_enabled (enabled),
    INDEX idx_alert_rules_source (source)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS alerts (
    id INT AUTO_INCREMENT PRIMARY KEY,
    rule_key VARCHAR(100) NOT NULL,
    server_id INT NULL,
    severity VARCHAR(20) NOT NULL,
    status VARCHAR(20) NOT NULL DEFAULT 'active',
    title VARCHAR(180) NOT NULL,
    message TEXT NOT NULL,
    fingerprint VARCHAR(190) NOT NULL,
    first_seen_at DATETIME NOT NULL,
    last_seen_at DATETIME NOT NULL,
    resolved_at DATETIME NULL,
    occurrence_count INT NOT NULL DEFAULT 1,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT fk_alerts_server
        FOREIGN KEY (server_id) REFERENCES servers(id)
        ON DELETE CASCADE,
    INDEX idx_alerts_status_severity (status, severity),
    INDEX idx_alerts_rule_status (rule_key, status),
    INDEX idx_alerts_server_status (server_id, status),
    INDEX idx_alerts_fingerprint (fingerprint)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS alert_events (
    id INT AUTO_INCREMENT PRIMARY KEY,
    alert_id INT NOT NULL,
    event_type VARCHAR(30) NOT NULL,
    severity VARCHAR(20) NOT NULL,
    message TEXT NOT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_alert_events_alert
        FOREIGN KEY (alert_id) REFERENCES alerts(id)
        ON DELETE CASCADE,
    INDEX idx_alert_events_alert_created (alert_id, created_at),
    INDEX idx_alert_events_type_created (event_type, created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

INSERT INTO alert_rules (rule_key, name, source, severity, enabled, threshold_value)
VALUES
    ('server_down', 'Serveur down', 'supervision', 'critical', 1, NULL),
    ('ssh_failed', 'SSH KO', 'supervision', 'warning', 1, NULL),
    ('stale_supervision_check', 'Dernier check supervision trop ancien', 'supervision', 'warning', 1, 30),
    ('patch_security_updates', 'Mises a jour de securite disponibles', 'patch_management', 'warning', 1, NULL),
    ('reboot_required', 'Reboot requis', 'patch_management', 'warning', 1, NULL),
    ('os_eol', 'OS obsolete', 'os_lifecycle', 'critical', 1, NULL),
    ('os_eol_soon', 'Fin de support OS proche', 'os_lifecycle', 'warning', 1, NULL),
    ('security_exposed_ports', 'Ports exposes', 'security', 'warning', 1, NULL),
    ('security_firewall_disabled', 'Firewall inactif ou non detecte', 'security', 'warning', 1, NULL)
ON DUPLICATE KEY UPDATE
    name = VALUES(name),
    source = VALUES(source);

INSERT INTO settings (category, setting_key, setting_value)
VALUES ('alerting', 'check_interval_minutes', '5')
ON DUPLICATE KEY UPDATE setting_value = setting_value;
