INSERT INTO settings (category, setting_key, setting_value)
VALUES ('os_lifecycle', 'external_products', 'proxmox_ve=proxmox-ve')
ON DUPLICATE KEY UPDATE setting_value = CASE
    WHEN INSTR(setting_value, 'proxmox_ve=') > 0 THEN setting_value
    WHEN TRIM(setting_value) = '' THEN 'proxmox_ve=proxmox-ve'
    ELSE CONCAT(TRIM(TRAILING '\n' FROM setting_value), '\nproxmox_ve=proxmox-ve')
END;
