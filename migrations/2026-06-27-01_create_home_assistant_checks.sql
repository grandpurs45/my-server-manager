CREATE TABLE IF NOT EXISTS home_assistant_checks (
    id INT AUTO_INCREMENT PRIMARY KEY,
    server_id INT NOT NULL,
    collector VARCHAR(80) DEFAULT NULL,
    status VARCHAR(20) NOT NULL DEFAULT 'unknown',
    installation_type VARCHAR(80) DEFAULT NULL,
    ha_version VARCHAR(80) DEFAULT NULL,
    ha_latest_version VARCHAR(80) DEFAULT NULL,
    ha_update_available TINYINT(1) DEFAULT NULL,
    supervisor_version VARCHAR(80) DEFAULT NULL,
    supervisor_latest_version VARCHAR(80) DEFAULT NULL,
    supervisor_update_available TINYINT(1) DEFAULT NULL,
    os_version VARCHAR(120) DEFAULT NULL,
    os_latest_version VARCHAR(120) DEFAULT NULL,
    os_update_available TINYINT(1) DEFAULT NULL,
    host_os VARCHAR(190) DEFAULT NULL,
    kernel VARCHAR(190) DEFAULT NULL,
    checked_at DATETIME NOT NULL,
    duration_ms INT DEFAULT NULL,
    error_message TEXT DEFAULT NULL,
    raw_summary TEXT DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_home_assistant_checks_server
        FOREIGN KEY (server_id) REFERENCES servers(id)
        ON DELETE CASCADE,
    INDEX idx_home_assistant_checks_server_checked (server_id, checked_at),
    INDEX idx_home_assistant_checks_status (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO settings (category, setting_key, setting_value)
VALUES ('home_assistant', 'check_interval_minutes', '15')
ON DUPLICATE KEY UPDATE setting_value = setting_value;

UPDATE settings
SET setting_value = CONCAT(TRIM(TRAILING '\n' FROM setting_value), '\nhome_assistant=Home Assistant')
WHERE category = 'inventaire'
  AND setting_key = 'target_types'
  AND setting_value NOT LIKE '%home_assistant=%';
