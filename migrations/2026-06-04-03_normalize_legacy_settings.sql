INSERT INTO settings (category, setting_key, setting_value)
SELECT category, 'dns_suffix', setting_value
FROM settings
WHERE category = 'reseau' AND setting_key = 'Suffixe DNS'
ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value);

DELETE FROM settings
WHERE category = 'reseau' AND setting_key = 'Suffixe DNS';
