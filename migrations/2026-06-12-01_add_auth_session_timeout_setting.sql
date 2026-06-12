INSERT INTO settings (`category`, `setting_key`, `setting_value`)
VALUES ('auth', 'session_timeout_minutes', '60')
ON DUPLICATE KEY UPDATE `setting_value` = `setting_value`;
