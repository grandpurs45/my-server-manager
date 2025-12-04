-- Paramètre de fréquence de vérification des serveurs (en minutes)
INSERT INTO settings (category, setting_key, setting_value)
VALUES ('supervision', 'check_interval_minutes', '10')
ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value);
