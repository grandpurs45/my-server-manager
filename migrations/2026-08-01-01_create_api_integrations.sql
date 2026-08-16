CREATE TABLE IF NOT EXISTS api_credentials (
    id INT AUTO_INCREMENT PRIMARY KEY,
    username_encrypted TEXT NOT NULL,
    secret_encrypted TEXT NOT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS api_sources (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(150) NOT NULL,
    connector_type VARCHAR(60) NOT NULL,
    protocol VARCHAR(10) NOT NULL DEFAULT 'https',
    hostname VARCHAR(255) NOT NULL,
    port INT NOT NULL DEFAULT 443,
    credentials_id INT NOT NULL,
    verify_tls TINYINT(1) NOT NULL DEFAULT 1,
    timeout_seconds INT NOT NULL DEFAULT 15,
    enabled TINYINT(1) NOT NULL DEFAULT 0,
    configuration_json TEXT NULL,
    last_test_status VARCHAR(30) NULL,
    last_test_message VARCHAR(500) NULL,
    last_tested_at DATETIME NULL,
    last_discovered_at DATETIME NULL,
    last_collected_at DATETIME NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT fk_api_sources_credentials
        FOREIGN KEY (credentials_id) REFERENCES api_credentials(id)
        ON DELETE RESTRICT,
    INDEX idx_api_sources_enabled (enabled, connector_type),
    UNIQUE KEY uq_api_source_name (name)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS api_discovery_runs (
    id BIGINT AUTO_INCREMENT PRIMARY KEY,
    api_source_id INT NOT NULL,
    status VARCHAR(30) NOT NULL,
    resource_count INT NOT NULL DEFAULT 0,
    metric_count INT NOT NULL DEFAULT 0,
    message VARCHAR(500) NULL,
    raw_result_json MEDIUMTEXT NULL,
    started_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    finished_at DATETIME NULL,
    raw_expires_at DATETIME NULL,
    CONSTRAINT fk_api_discovery_source
        FOREIGN KEY (api_source_id) REFERENCES api_sources(id)
        ON DELETE CASCADE,
    INDEX idx_api_discovery_source_date (api_source_id, started_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS api_resources (
    id BIGINT AUTO_INCREMENT PRIMARY KEY,
    api_source_id INT NOT NULL,
    external_id VARCHAR(255) NOT NULL,
    resource_type VARCHAR(60) NOT NULL,
    name VARCHAR(255) NOT NULL,
    parent_external_id VARCHAR(255) NULL,
    enabled TINYINT(1) NOT NULL DEFAULT 1,
    metadata_json TEXT NULL,
    first_seen_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    last_seen_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    missing_since DATETIME NULL,
    CONSTRAINT fk_api_resources_source
        FOREIGN KEY (api_source_id) REFERENCES api_sources(id)
        ON DELETE CASCADE,
    UNIQUE KEY uq_api_resource_external (api_source_id, external_id),
    INDEX idx_api_resources_source_type (api_source_id, resource_type)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS api_metrics (
    id BIGINT AUTO_INCREMENT PRIMARY KEY,
    api_resource_id BIGINT NOT NULL,
    external_key VARCHAR(190) NOT NULL,
    name VARCHAR(255) NOT NULL,
    data_type VARCHAR(30) NOT NULL,
    unit VARCHAR(30) NULL,
    enabled TINYINT(1) NOT NULL DEFAULT 1,
    collection_interval_minutes INT NOT NULL DEFAULT 15,
    last_value_json TEXT NULL,
    last_raw_value_json TEXT NULL,
    last_status VARCHAR(30) NULL,
    last_collected_at DATETIME NULL,
    next_collection_at DATETIME NULL,
    metadata_json TEXT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT fk_api_metrics_resource
        FOREIGN KEY (api_resource_id) REFERENCES api_resources(id)
        ON DELETE CASCADE,
    UNIQUE KEY uq_api_metric_external (api_resource_id, external_key),
    INDEX idx_api_metrics_due (enabled, next_collection_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS api_metric_samples (
    id BIGINT AUTO_INCREMENT PRIMARY KEY,
    api_metric_id BIGINT NOT NULL,
    value_json TEXT NULL,
    status VARCHAR(30) NOT NULL DEFAULT 'success',
    collected_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_api_samples_metric
        FOREIGN KEY (api_metric_id) REFERENCES api_metrics(id)
        ON DELETE CASCADE,
    INDEX idx_api_samples_metric_date (api_metric_id, collected_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO settings (category, setting_key, setting_value)
VALUES
    ('api_integrations', 'check_interval_minutes', '1'),
    ('api_integrations', 'raw_retention_days', '7'),
    ('api_integrations', 'sample_retention_days', '30')
ON DUPLICATE KEY UPDATE setting_value = setting_value;
