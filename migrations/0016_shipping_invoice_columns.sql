-- Add created_by to shipments, void_reason to invoices, document numbering sequences

ALTER TABLE shipments ADD COLUMN created_by INT NULL AFTER sales_rep_id;
ALTER TABLE invoices ADD COLUMN void_reason TEXT NULL AFTER status;

INSERT IGNORE INTO document_numbering_sequences (sequence_key, prefix, next_number) VALUES
('SHIPMENT', 'SHP', 1),
('INVOICE', 'INV', 1);

INSERT IGNORE INTO schema_migrations (migration_name) VALUES ('0016_shipping_invoice_columns');
