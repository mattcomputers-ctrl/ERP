-- Migration 0026: QuickBooks Sync tables
-- Sync log, settings, and account mappings

CREATE TABLE IF NOT EXISTS qb_sync_log (
  id BIGINT AUTO_INCREMENT PRIMARY KEY,
  sync_type VARCHAR(50) NOT NULL,
  record_type VARCHAR(50) NOT NULL,
  record_id BIGINT NOT NULL,
  qb_mode ENUM('DESKTOP','ONLINE') NOT NULL,
  status ENUM('PENDING','SUCCESS','FAILED','SKIPPED') DEFAULT 'PENDING',
  qb_txn_id VARCHAR(100) NULL,
  error_message TEXT NULL,
  synced_at DATETIME NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_record (record_type, record_id),
  INDEX idx_status (status),
  INDEX idx_created (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS qb_settings (
  id INT AUTO_INCREMENT PRIMARY KEY,
  setting_key VARCHAR(100) NOT NULL UNIQUE,
  setting_value TEXT NULL,
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS qb_account_mappings (
  id INT AUTO_INCREMENT PRIMARY KEY,
  mapping_type VARCHAR(50) NOT NULL,
  mapping_key VARCHAR(100) NOT NULL,
  qb_account_name VARCHAR(200) NOT NULL,
  qb_account_number VARCHAR(50) NULL,
  active TINYINT(1) DEFAULT 1,
  UNIQUE KEY uq_mapping (mapping_type, mapping_key)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Seed default QB settings
INSERT IGNORE INTO qb_settings (setting_key, setting_value) VALUES
('qb_mode', 'DESKTOP'),
('qb_company_name', ''),
('qb_ar_account', 'Accounts Receivable'),
('qb_ap_account', 'Accounts Payable'),
('qb_default_income_account', 'Sales'),
('qb_default_cogs_account', 'Cost of Goods Sold'),
('qb_default_inventory_account', 'Inventory Asset'),
('qb_online_client_id', ''),
('qb_online_client_secret', ''),
('qb_online_realm_id', ''),
('qb_online_access_token', ''),
('qb_online_refresh_token', ''),
('qb_online_token_expires_at', '');

INSERT IGNORE INTO schema_migrations (migration_name) VALUES ('0026_qb_sync.sql');
