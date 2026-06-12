CREATE TABLE IF NOT EXISTS `msm_users` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `username` VARCHAR(80) NOT NULL UNIQUE,
  `display_name` VARCHAR(120) DEFAULT NULL,
  `password_hash` VARCHAR(255) NOT NULL,
  `is_admin` TINYINT(1) NOT NULL DEFAULT 0,
  `is_active` TINYINT(1) NOT NULL DEFAULT 1,
  `password_must_change` TINYINT(1) NOT NULL DEFAULT 0,
  `last_login_at` DATETIME DEFAULT NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `msm_user_module_permissions` (
  `user_id` INT NOT NULL,
  `module_key` VARCHAR(80) NOT NULL,
  `can_access` TINYINT(1) NOT NULL DEFAULT 1,
  PRIMARY KEY (`user_id`, `module_key`),
  CONSTRAINT `fk_msm_user_module_permissions_user`
    FOREIGN KEY (`user_id`) REFERENCES `msm_users` (`id`)
    ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `msm_auth_events` (
  `id` BIGINT AUTO_INCREMENT PRIMARY KEY,
  `user_id` INT DEFAULT NULL,
  `username` VARCHAR(80) DEFAULT NULL,
  `event_type` VARCHAR(80) NOT NULL,
  `ip_address` VARCHAR(64) DEFAULT NULL,
  `user_agent` VARCHAR(255) DEFAULT NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  INDEX `idx_msm_auth_events_created_at` (`created_at`),
  INDEX `idx_msm_auth_events_user_id` (`user_id`),
  CONSTRAINT `fk_msm_auth_events_user`
    FOREIGN KEY (`user_id`) REFERENCES `msm_users` (`id`)
    ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO `msm_users` (`username`, `display_name`, `password_hash`, `is_admin`, `is_active`, `password_must_change`)
SELECT 'admin', 'Administrateur MSM', '$2y$10$AFtjuae9M6jx66s9TSf.suHuSvI9AK4j6MglSx1sB/KxAzCHOASCG', 1, 1, 1
WHERE NOT EXISTS (SELECT 1 FROM `msm_users` WHERE `username` = 'admin');

INSERT INTO `settings` (`category`, `setting_key`, `setting_value`) VALUES
('auth', 'password_min_length', '12'),
('auth', 'password_require_uppercase', 'true'),
('auth', 'password_require_lowercase', 'true'),
('auth', 'password_require_digit', 'true'),
('auth', 'password_require_special', 'true'),
('auth', 'password_generator_length', '18')
ON DUPLICATE KEY UPDATE `setting_value` = `setting_value`;
