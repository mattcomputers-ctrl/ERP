-- Add missing columns to batch_tickets and create daily_batch_counter

ALTER TABLE batch_tickets ADD COLUMN qc_spec_id INT NULL AFTER recipe_version_id;
ALTER TABLE batch_tickets ADD COLUMN created_by INT NOT NULL DEFAULT 1 AFTER closed_at;

CREATE TABLE IF NOT EXISTS daily_batch_counter (
    counter_date DATE NOT NULL UNIQUE,
    counter INT NOT NULL DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT IGNORE INTO schema_migrations (migration_name) VALUES ('0015_batch_columns');
