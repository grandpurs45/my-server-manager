INSERT INTO settings (category, setting_key, setting_value)
VALUES ('msm', 'debug_mode', 'false')
ON DUPLICATE KEY UPDATE setting_value = 'false';