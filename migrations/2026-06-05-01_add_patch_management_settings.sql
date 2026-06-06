INSERT INTO settings (category, setting_key, setting_value) VALUES
('patch_management', 'check_interval_hours', '6')
ON DUPLICATE KEY UPDATE setting_value = setting_value;
