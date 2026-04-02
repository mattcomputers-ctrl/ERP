-- Add facility_id to consignment_placements for ship-from tracking
ALTER TABLE consignment_placements ADD COLUMN facility_id INT NULL AFTER ship_to_id;
INSERT INTO schema_migrations (migration_name) VALUES ('0017_consignment_facility');
