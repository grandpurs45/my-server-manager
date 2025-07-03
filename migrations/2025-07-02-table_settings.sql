CREATE TABLE IF NOT EXISTS `settings` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `category` VARCHAR(50) NOT NULL,      -- Exemple : 'reseau', 'supervision', 'bdd', 'msm'
    `setting_key` VARCHAR(100) NOT NULL,  -- Exemple : 'dns_suffix', 'check_interval', etc.
    `setting_value` TEXT,                 -- Valeur générique (texte, booléen, JSON...)
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY `unique_setting` (`category`, `setting_key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
