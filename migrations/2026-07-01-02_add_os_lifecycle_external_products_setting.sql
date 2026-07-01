INSERT INTO settings (category, setting_key, setting_value)
VALUES ('os_lifecycle', 'external_products', 'alpine=alpine
ubuntu=ubuntu
debian=debian
rocky=rocky-linux')
ON DUPLICATE KEY UPDATE setting_value = setting_value;
