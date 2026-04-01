-- Migration: 0012_audit_email_indexes
-- Description: Add indexes to audit_log and outbound_email_log for query performance
-- Created: 2026-04-01

SET NAMES utf8mb4;
SET CHARACTER SET utf8mb4;

-- ------------------------------------------------------------
-- Audit Log Indexes (idempotent — ignore if already exist)
-- ------------------------------------------------------------
CREATE INDEX IF NOT EXISTS idx_audit_user_id ON audit_log (user_id);
CREATE INDEX IF NOT EXISTS idx_audit_module ON audit_log (module);
CREATE INDEX IF NOT EXISTS idx_audit_action_type ON audit_log (action_type);
CREATE INDEX IF NOT EXISTS idx_audit_created_at ON audit_log (created_at);

-- ------------------------------------------------------------
-- Outbound Email Log Indexes
-- ------------------------------------------------------------
CREATE INDEX IF NOT EXISTS idx_email_document_type ON outbound_email_log (document_type);
CREATE INDEX IF NOT EXISTS idx_email_status ON outbound_email_log (status);
CREATE INDEX IF NOT EXISTS idx_email_created_at ON outbound_email_log (created_at);
CREATE INDEX IF NOT EXISTS idx_email_reference ON outbound_email_log (reference_type, reference_id);

-- ------------------------------------------------------------
-- Record this migration
-- ------------------------------------------------------------
INSERT INTO schema_migrations (migration_name) VALUES ('0012_audit_email_indexes');
