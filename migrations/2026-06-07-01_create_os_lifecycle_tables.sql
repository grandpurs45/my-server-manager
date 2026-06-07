CREATE TABLE IF NOT EXISTS os_lifecycle_references (
    id INT AUTO_INCREMENT PRIMARY KEY,
    os_family VARCHAR(50) NOT NULL,
    os_version VARCHAR(50) NOT NULL,
    os_codename VARCHAR(100) DEFAULT NULL,
    support_ends_at DATE DEFAULT NULL,
    upgrade_target_version VARCHAR(50) DEFAULT NULL,
    upgrade_target_label VARCHAR(150) DEFAULT NULL,
    source VARCHAR(255) DEFAULT NULL,
    notes TEXT DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uniq_os_lifecycle_reference (os_family, os_version)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS os_lifecycle_checks (
    id INT AUTO_INCREMENT PRIMARY KEY,
    server_id INT NOT NULL,
    os_family VARCHAR(50) DEFAULT NULL,
    os_version VARCHAR(50) DEFAULT NULL,
    os_codename VARCHAR(100) DEFAULT NULL,
    os_pretty_name VARCHAR(255) DEFAULT NULL,
    support_status VARCHAR(30) NOT NULL DEFAULT 'unknown',
    support_ends_at DATE DEFAULT NULL,
    upgrade_available TINYINT(1) NOT NULL DEFAULT 0,
    upgrade_target_version VARCHAR(50) DEFAULT NULL,
    upgrade_target_label VARCHAR(150) DEFAULT NULL,
    checked_at DATETIME NOT NULL,
    error_message TEXT DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_os_lifecycle_checks_server
        FOREIGN KEY (server_id) REFERENCES servers(id)
        ON DELETE CASCADE,
    INDEX idx_os_lifecycle_checks_server_checked (server_id, checked_at),
    INDEX idx_os_lifecycle_checks_status (support_status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO os_lifecycle_references (
    os_family,
    os_version,
    os_codename,
    support_ends_at,
    upgrade_target_version,
    upgrade_target_label,
    source,
    notes
) VALUES
('ubuntu', '22.04', 'jammy', '2027-04-30', '24.04', 'Ubuntu 24.04 LTS (Noble Numbat)', 'Ubuntu Releases', 'Ubuntu LTS standard support is five years.'),
('ubuntu', '24.04', 'noble', '2029-04-30', '26.04', 'Ubuntu 26.04 LTS', 'Ubuntu Releases', 'Ubuntu LTS standard support is five years.'),
('ubuntu', '26.04', 'resolute', '2031-04-30', NULL, NULL, 'Ubuntu Releases', 'Ubuntu LTS standard support is five years.'),
('debian', '12', 'bookworm', '2028-06-30', '13', 'Debian 13 (trixie)', 'Debian bookworm release information', 'Debian 12 full support ended 2026-06-10; LTS continues until 2028-06-30.'),
('debian', '13', 'trixie', '2030-06-30', NULL, NULL, 'Debian trixie release information', 'Debian 13 full support ends 2028-08-09; LTS continues until 2030-06-30.'),
('rocky', '10.1', 'red_quartz', '2035-05-31', '10.2', 'Rocky Linux 10.2', 'Rocky Linux Release and Version Guide', 'Rocky Linux 10.1 remains in the Rocky Linux 10 lifecycle, but a newer minor release is known.'),
('rocky', '10.2', 'red_quartz', '2035-05-31', NULL, NULL, 'Rocky Linux Release and Version Guide', 'Current Rocky Linux 10 minor release at implementation time.'),
('rocky', '10', 'red_quartz', '2035-05-31', NULL, NULL, 'Rocky Linux Release and Version Guide', 'Rocky Linux 10 major release end of life.'),
('rocky', '9', 'blue_onyx', '2032-05-31', '10', 'Rocky Linux 10', 'Rocky Linux Release and Version Guide', 'Major upgrades are not formally supported by Rocky Linux; migration planning is recommended.')
ON DUPLICATE KEY UPDATE
    os_codename = VALUES(os_codename),
    support_ends_at = VALUES(support_ends_at),
    upgrade_target_version = VALUES(upgrade_target_version),
    upgrade_target_label = VALUES(upgrade_target_label),
    source = VALUES(source),
    notes = VALUES(notes);
