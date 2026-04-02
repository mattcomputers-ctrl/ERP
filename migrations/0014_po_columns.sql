-- Add created_by to purchase_orders and posted flag to landed_costs

ALTER TABLE purchase_orders ADD COLUMN created_by INT NULL AFTER requisition_id;
ALTER TABLE landed_costs ADD COLUMN posted TINYINT(1) NOT NULL DEFAULT 0 AFTER notes;

INSERT IGNORE INTO schema_migrations (migration_name) VALUES ('0014_po_columns');
