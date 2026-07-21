CREATE TABLE IF NOT EXISTS web_targets (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(150) NOT NULL,
    url VARCHAR(2048) NOT NULL,
    enabled TINYINT(1) NOT NULL DEFAULT 1,
    environment VARCHAR(50) NOT NULL DEFAULT 'production',
    criticality VARCHAR(20) NOT NULL DEFAULT 'medium',
    interval_minutes INT NOT NULL DEFAULT 5,
    timeout_seconds INT NOT NULL DEFAULT 10,
    follow_redirects TINYINT(1) NOT NULL DEFAULT 1,
    verify_tls TINYINT(1) NOT NULL DEFAULT 1,
    expected_status_codes VARCHAR(100) NOT NULL DEFAULT '200-399',
    expected_content VARCHAR(255) NULL,
    failure_threshold INT NOT NULL DEFAULT 2,
    consecutive_failures INT NOT NULL DEFAULT 0,
    last_checked_at DATETIME NULL,
    next_check_at DATETIME NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_web_targets_due (enabled, next_check_at),
    INDEX idx_web_targets_environment (environment),
    INDEX idx_web_targets_criticality (criticality)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS web_check_results (
    id BIGINT AUTO_INCREMENT PRIMARY KEY,
    web_target_id INT NOT NULL,
    success TINYINT(1) NOT NULL DEFAULT 0,
    http_status INT NULL,
    error_type VARCHAR(40) NULL,
    error_message VARCHAR(500) NULL,
    dns_ms DECIMAL(10,2) NULL,
    connect_ms DECIMAL(10,2) NULL,
    tls_ms DECIMAL(10,2) NULL,
    ttfb_ms DECIMAL(10,2) NULL,
    total_ms DECIMAL(10,2) NULL,
    final_url VARCHAR(2048) NULL,
    redirect_count INT NOT NULL DEFAULT 0,
    tls_valid TINYINT(1) NULL,
    certificate_expires_at DATETIME NULL,
    certificate_expiry_days INT NULL,
    content_matched TINYINT(1) NULL,
    checked_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_web_check_results_target
        FOREIGN KEY (web_target_id) REFERENCES web_targets(id)
        ON DELETE CASCADE,
    INDEX idx_web_check_target_checked (web_target_id, checked_at),
    INDEX idx_web_check_success_checked (success, checked_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO settings (category, setting_key, setting_value)
VALUES ('web_monitoring', 'check_interval_minutes', '1')
ON DUPLICATE KEY UPDATE setting_value = setting_value;

INSERT INTO alert_rules (rule_key, name, source, severity, enabled, threshold_value)
VALUES
    ('url_unavailable', 'URL indisponible', 'web_monitoring', 'critical', 1, NULL),
    ('url_http_status', 'Code HTTP inattendu', 'web_monitoring', 'warning', 1, NULL),
    ('url_latency_high', 'Latence URL elevee', 'web_monitoring', 'warning', 1, 2000),
    ('url_tls_expiry', 'Certificat TLS proche expiration', 'web_monitoring', 'warning', 1, 30),
    ('url_content_mismatch', 'Contenu attendu absent', 'web_monitoring', 'warning', 1, NULL)
ON DUPLICATE KEY UPDATE
    name = VALUES(name),
    source = VALUES(source);
