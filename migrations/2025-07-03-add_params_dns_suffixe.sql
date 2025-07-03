INSERT INTO settings (category, setting_key, setting_value)
VALUES ('reseau', 'Suffixe DNS', '')
ON DUPLICATE KEY UPDATE setting_value = 'false';