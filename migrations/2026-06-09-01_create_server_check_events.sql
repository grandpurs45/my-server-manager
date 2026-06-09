CREATE TABLE IF NOT EXISTS server_check_events (
    id INT AUTO_INCREMENT PRIMARY KEY,
    server_id INT NOT NULL,
    event_type VARCHAR(50) NOT NULL,
    previous_value VARCHAR(100) DEFAULT NULL,
    new_value VARCHAR(100) DEFAULT NULL,
    message VARCHAR(255) DEFAULT NULL,
    created_at DATETIME NOT NULL,
    INDEX idx_server_check_events_server_date (server_id, created_at),
    INDEX idx_server_check_events_type_date (event_type, created_at),
    CONSTRAINT fk_server_check_events_server
        FOREIGN KEY (server_id) REFERENCES servers(id)
        ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
