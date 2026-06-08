INSERT INTO settings (category, setting_key, setting_value)
VALUES ('os_lifecycle', 'check_interval_hours', '168')
ON DUPLICATE KEY UPDATE setting_value = setting_value;
