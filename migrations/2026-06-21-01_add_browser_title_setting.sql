INSERT INTO settings (category, setting_key, setting_value)
VALUES ('msm', 'browser_title', 'My Server Manager')
ON DUPLICATE KEY UPDATE setting_value = setting_value;
