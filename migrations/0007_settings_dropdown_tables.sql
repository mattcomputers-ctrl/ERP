-- Migration: 0007_settings_dropdown_tables
-- Description: Add missing dropdown/lookup tables for settings management
-- Created: 2026-04-01

SET NAMES utf8mb4;
SET CHARACTER SET utf8mb4;

-- ------------------------------------------------------------
-- RMA Return Reasons
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS rma_return_reasons (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    active TINYINT(1) NOT NULL DEFAULT 1,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------
-- Lost Quote Reasons
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS lost_quote_reasons (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    active TINYINT(1) NOT NULL DEFAULT 1,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------
-- Landed Cost Types
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS landed_cost_types (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    active TINYINT(1) NOT NULL DEFAULT 1,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------
-- Seed: Lost Quote Reasons
-- ------------------------------------------------------------
INSERT IGNORE INTO lost_quote_reasons (name, active) VALUES
('Price', 1),
('Competition', 1),
('Timing', 1),
('No Longer Needed', 1),
('Other', 1);

-- ------------------------------------------------------------
-- Seed: Landed Cost Types
-- ------------------------------------------------------------
INSERT IGNORE INTO landed_cost_types (name, active) VALUES
('Freight', 1),
('Duty', 1),
('Brokerage', 1),
('Insurance', 1);

-- ------------------------------------------------------------
-- Seed: session_warning_minutes (missing from 0006)
-- ------------------------------------------------------------
INSERT IGNORE INTO system_settings (setting_key, setting_value)
SELECT 'session_warning_minutes', '5'
FROM DUAL WHERE NOT EXISTS (
    SELECT 1 FROM system_settings WHERE setting_key = 'session_warning_minutes'
);

-- ------------------------------------------------------------
-- Record this migration
-- ------------------------------------------------------------
INSERT IGNORE INTO schema_migrations (migration_name) VALUES ('0007_settings_dropdown_tables');
