ALTER TABLE server_metrics
MODIFY COLUMN type ENUM('ping', 'cpu', 'ram', 'disk', 'availability') NOT NULL;