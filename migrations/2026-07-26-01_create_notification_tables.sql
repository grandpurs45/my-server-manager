CREATE TABLE IF NOT EXISTS notification_channels (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(150) NOT NULL,
    channel_type VARCHAR(30) NOT NULL,
    endpoint_encrypted TEXT NOT NULL,
    enabled TINYINT(1) NOT NULL DEFAULT 1,
    minimum_severity VARCHAR(20) NOT NULL DEFAULT 'warning',
    notify_on_open TINYINT(1) NOT NULL DEFAULT 1,
    notify_on_resolve TINYINT(1) NOT NULL DEFAULT 1,
    starts_after_event_id INT NOT NULL DEFAULT 0,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_notification_channels_enabled (enabled)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS notification_deliveries (
    id BIGINT AUTO_INCREMENT PRIMARY KEY,
    channel_id INT NOT NULL,
    alert_event_id INT NOT NULL,
    status VARCHAR(20) NOT NULL DEFAULT 'pending',
    attempt_count INT NOT NULL DEFAULT 0,
    response_code INT NULL,
    error_message VARCHAR(500) NULL,
    last_attempt_at DATETIME NULL,
    sent_at DATETIME NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT fk_notification_deliveries_channel
        FOREIGN KEY (channel_id) REFERENCES notification_channels(id)
        ON DELETE CASCADE,
    CONSTRAINT fk_notification_deliveries_event
        FOREIGN KEY (alert_event_id) REFERENCES alert_events(id)
        ON DELETE CASCADE,
    UNIQUE KEY unique_notification_delivery (channel_id, alert_event_id),
    INDEX idx_notification_deliveries_status_attempts (status, attempt_count),
    INDEX idx_notification_deliveries_created (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

INSERT INTO settings (category, setting_key, setting_value)
VALUES
    ('notifications', 'public_base_url', ''),
    ('notifications', 'max_attempts', '3')
ON DUPLICATE KEY UPDATE setting_value = setting_value;
