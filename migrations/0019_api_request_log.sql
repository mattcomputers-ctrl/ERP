-- API request logging table
CREATE TABLE IF NOT EXISTS api_request_log (
    id BIGINT AUTO_INCREMENT PRIMARY KEY,
    api_key_id INT NULL,
    method VARCHAR(10) NOT NULL,
    path VARCHAR(255) NOT NULL,
    query_params TEXT NULL,
    response_code INT NOT NULL DEFAULT 200,
    duration_ms INT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_api_key (api_key_id),
    INDEX idx_created_at (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT IGNORE INTO schema_migrations (migration_name) VALUES ('0019_api_request_log');
