ALTER TABLE servers
    ADD COLUMN IF NOT EXISTS patch_management_enabled TINYINT(1) NOT NULL DEFAULT 0;

CREATE TABLE IF NOT EXISTS patch_checks (
    id INT AUTO_INCREMENT PRIMARY KEY,
    server_id INT NOT NULL,
    status VARCHAR(20) NOT NULL DEFAULT 'unknown',
    normal_updates_count INT NOT NULL DEFAULT 0,
    security_updates_count INT NOT NULL DEFAULT 0,
    reboot_required TINYINT(1) NOT NULL DEFAULT 0,
    checked_at DATETIME NOT NULL,
    duration_ms INT DEFAULT NULL,
    error_message TEXT DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_patch_checks_server
        FOREIGN KEY (server_id) REFERENCES servers(id)
        ON DELETE CASCADE,
    INDEX idx_patch_checks_server_checked (server_id, checked_at),
    INDEX idx_patch_checks_status (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS patch_updates (
    id INT AUTO_INCREMENT PRIMARY KEY,
    patch_check_id INT NOT NULL,
    update_type VARCHAR(20) NOT NULL DEFAULT 'normal',
    package_name VARCHAR(255) NOT NULL,
    installed_version VARCHAR(255) DEFAULT NULL,
    candidate_version VARCHAR(255) DEFAULT NULL,
    source VARCHAR(255) DEFAULT NULL,
    severity VARCHAR(50) DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_patch_updates_check
        FOREIGN KEY (patch_check_id) REFERENCES patch_checks(id)
        ON DELETE CASCADE,
    INDEX idx_patch_updates_check_type (patch_check_id, update_type),
    INDEX idx_patch_updates_package (package_name)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
