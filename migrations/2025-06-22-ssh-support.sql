ALTER TABLE servers
ADD COLUMN port INT DEFAULT 22 AFTER hostname,
ADD COLUMN ssh_user VARCHAR(255) AFTER port,
ADD COLUMN ssh_password VARCHAR(255) AFTER ssh_user,
ADD COLUMN ssh_status ENUM('success', 'fail') DEFAULT 'fail' AFTER ssh_password;