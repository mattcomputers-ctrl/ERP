-- Migration: 0013_transfer_orders_columns
-- Description: Add missing columns to transfer_orders for workflow tracking
-- Created: 2026-04-01

SET NAMES utf8mb4;
SET CHARACTER SET utf8mb4;

-- Add created_by, shipped_by, shipped_at, received_by, received_at to transfer_orders
ALTER TABLE transfer_orders
    ADD COLUMN created_by INT NOT NULL AFTER notes,
    ADD COLUMN shipped_by INT NULL AFTER created_by,
    ADD COLUMN shipped_at DATETIME NULL AFTER shipped_by,
    ADD COLUMN received_by INT NULL AFTER shipped_at,
    ADD COLUMN received_at DATETIME NULL AFTER received_by,
    ADD FOREIGN KEY (created_by) REFERENCES users(id) ON UPDATE CASCADE ON DELETE RESTRICT;

-- Add index on record_type + record_id for attachments
ALTER TABLE attachments ADD INDEX idx_record (record_type, record_id);

-- Ensure default facility seed exists
INSERT IGNORE INTO facilities (id, code, name, facility_type, is_default, active)
VALUES (1, 'DEFAULT', 'Main Facility', 'MANUFACTURING', 1, 1);

-- Record this migration
INSERT IGNORE INTO schema_migrations (migration_name) VALUES ('0013_transfer_orders_columns');
