CREATE TABLE IF NOT EXISTS hardware_health_checks (
    id INT AUTO_INCREMENT PRIMARY KEY,
    server_id INT NOT NULL,
    collector VARCHAR(50) DEFAULT NULL,
    status VARCHAR(20) NOT NULL DEFAULT 'unknown',
    sensors_count INT NOT NULL DEFAULT 0,
    max_temperature_celsius DECIMAL(6,2) DEFAULT NULL,
    checked_at DATETIME NOT NULL,
    duration_ms INT DEFAULT NULL,
    error_message TEXT DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_hardware_health_checks_server
        FOREIGN KEY (server_id) REFERENCES servers(id)
        ON DELETE CASCADE,
    INDEX idx_hardware_health_checks_server_checked (server_id, checked_at),
    INDEX idx_hardware_health_checks_status (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS hardware_temperature_readings (
    id INT AUTO_INCREMENT PRIMARY KEY,
    hardware_check_id INT NOT NULL,
    sensor_key VARCHAR(190) NOT NULL,
    sensor_label VARCHAR(255) NOT NULL,
    sensor_type VARCHAR(30) NOT NULL DEFAULT 'other',
    temperature_celsius DECIMAL(6,2) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_hardware_temperature_readings_check
        FOREIGN KEY (hardware_check_id) REFERENCES hardware_health_checks(id)
        ON DELETE CASCADE,
    INDEX idx_hardware_temperature_readings_check (hardware_check_id),
    INDEX idx_hardware_temperature_readings_type (sensor_type)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO settings (category, setting_key, setting_value)
VALUES ('hardware_health', 'check_interval_minutes', '15')
ON DUPLICATE KEY UPDATE setting_value = setting_value;
