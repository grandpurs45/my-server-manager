CREATE TABLE IF NOT EXISTS security_checks (
    id INT AUTO_INCREMENT PRIMARY KEY,
    server_id INT NOT NULL,
    status VARCHAR(20) NOT NULL DEFAULT 'unknown',
    open_ports_count INT NOT NULL DEFAULT 0,
    exposed_ports_count INT NOT NULL DEFAULT 0,
    firewall_status VARCHAR(30) DEFAULT NULL,
    checked_at DATETIME NOT NULL,
    duration_ms INT DEFAULT NULL,
    error_message TEXT DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_security_checks_server
        FOREIGN KEY (server_id) REFERENCES servers(id)
        ON DELETE CASCADE,
    INDEX idx_security_checks_server_checked (server_id, checked_at),
    INDEX idx_security_checks_status (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS security_open_ports (
    id INT AUTO_INCREMENT PRIMARY KEY,
    security_check_id INT NOT NULL,
    protocol VARCHAR(20) NOT NULL,
    address VARCHAR(255) NOT NULL,
    port INT NOT NULL,
    exposure VARCHAR(20) NOT NULL DEFAULT 'unknown',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_security_open_ports_check
        FOREIGN KEY (security_check_id) REFERENCES security_checks(id)
        ON DELETE CASCADE,
    INDEX idx_security_open_ports_check (security_check_id),
    INDEX idx_security_open_ports_exposure (exposure)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO settings (category, setting_key, setting_value)
VALUES ('security', 'check_interval_hours', '24')
ON DUPLICATE KEY UPDATE setting_value = setting_value;
