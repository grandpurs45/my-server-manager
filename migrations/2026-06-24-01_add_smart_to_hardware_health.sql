ALTER TABLE hardware_health_checks
    ADD COLUMN IF NOT EXISTS smart_disks_count INT NOT NULL DEFAULT 0 AFTER sensors_count,
    ADD COLUMN IF NOT EXISTS smart_error_message TEXT DEFAULT NULL AFTER error_message;

CREATE TABLE IF NOT EXISTS hardware_smart_disks (
    id INT AUTO_INCREMENT PRIMARY KEY,
    hardware_check_id INT NOT NULL,
    device_name VARCHAR(190) NOT NULL,
    device_type VARCHAR(50) DEFAULT NULL,
    protocol VARCHAR(50) DEFAULT NULL,
    model_name VARCHAR(255) DEFAULT NULL,
    serial_number VARCHAR(255) DEFAULT NULL,
    capacity_bytes BIGINT UNSIGNED DEFAULT NULL,
    smart_supported TINYINT(1) DEFAULT NULL,
    smart_enabled TINYINT(1) DEFAULT NULL,
    smart_passed TINYINT(1) DEFAULT NULL,
    temperature_celsius DECIMAL(6,2) DEFAULT NULL,
    power_on_hours BIGINT UNSIGNED DEFAULT NULL,
    percentage_used DECIMAL(6,2) DEFAULT NULL,
    media_errors BIGINT UNSIGNED DEFAULT NULL,
    error_message TEXT DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_hardware_smart_disks_check
        FOREIGN KEY (hardware_check_id) REFERENCES hardware_health_checks(id)
        ON DELETE CASCADE,
    INDEX idx_hardware_smart_disks_check (hardware_check_id),
    INDEX idx_hardware_smart_disks_passed (smart_passed)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
