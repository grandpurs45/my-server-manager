ALTER TABLE servers
ADD COLUMN port INT DEFAULT 22,
ADD COLUMN ssh_user VARCHAR(255),
ADD COLUMN ssh_password VARCHAR(255),
ADD COLUMN ssh_status ENUM('success', 'fail') DEFAULT 'fail';