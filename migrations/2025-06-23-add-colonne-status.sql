ALTER TABLE servers
ADD COLUMN status ENUM('up', 'down') DEFAULT 'down' AFTER ssh_status;