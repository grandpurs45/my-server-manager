INSERT INTO settings (category, setting_key, setting_value)
VALUES ('msm', 'date_display_format', 'd/m/Y H:i:s')
ON DUPLICATE KEY UPDATE setting_value = setting_value;
