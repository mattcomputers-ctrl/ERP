-- Fix missing columns discovered during production testing

-- scars: add created_by column referenced by ScarController
ALTER TABLE scars ADD COLUMN created_by INT NULL AFTER closed_at;

INSERT IGNORE INTO schema_migrations (migration_name) VALUES ('0021_missing_columns');
