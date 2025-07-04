-- 2025-07-04-02_create_server_metrics_table.sql

CREATE TABLE IF NOT EXISTS server_metrics (
    id INT AUTO_INCREMENT PRIMARY KEY,
    server_id INT NOT NULL,
    type ENUM('ping', 'cpu', 'ram', 'disk') NOT NULL,
    value FLOAT NOT NULL,
    measured_at DATETIME NOT NULL,
    FOREIGN KEY (server_id) REFERENCES servers(id)
        ON DELETE CASCADE
);
