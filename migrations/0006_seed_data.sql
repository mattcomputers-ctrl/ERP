-- Migration: 0006_seed_data
-- Description: Seed data for foundation tables
-- Created: 2026-04-01

SET NAMES utf8mb4;
SET CHARACTER SET utf8mb4;

-- ------------------------------------------------------------
-- Schema Migrations (mark 0001–0005 as applied)
-- ------------------------------------------------------------
INSERT IGNORE INTO schema_migrations (migration_name) VALUES
('0001_foundation'),
('0002_items_customers'),
('0003_inventory_purchasing'),
('0004_sales_production'),
('0005_qc_remaining');

-- ------------------------------------------------------------
-- Facilities
-- ------------------------------------------------------------
INSERT IGNORE INTO facilities (id, code, name, facility_type, is_default, active) VALUES
(1, 'DEFAULT', 'Main Facility', 'MANUFACTURING', 1, 1);

-- ------------------------------------------------------------
-- Groups
-- ------------------------------------------------------------
INSERT IGNORE INTO `groups` (id, name, is_system_admin, active) VALUES
(1, 'System Administrator', 1, 1);

-- ------------------------------------------------------------
-- Units of Measure
-- ------------------------------------------------------------
INSERT IGNORE INTO uom (abbreviation, name, active) VALUES
('lb', 'Pound', 1),
('kg', 'Kilogram', 1),
('oz', 'Ounce', 1),
('gal', 'Gallon', 1),
('qt', 'Quart', 1),
('pt', 'Pint', 1),
('fl oz', 'Fluid Ounce', 1),
('each', 'Each', 1),
('case', 'Case', 1),
('drum', 'Drum', 1),
('pail', 'Pail', 1),
('bag', 'Bag', 1);

-- ------------------------------------------------------------
-- Ship Via
-- ------------------------------------------------------------
INSERT IGNORE INTO ship_via (name, transit_days, active) VALUES
('UPS Ground', 3, 1),
('UPS 2-Day Air', 2, 1),
('UPS Next Day Air', 1, 1),
('FedEx Ground', 3, 1),
('FedEx 2-Day', 2, 1),
('FedEx Overnight', 1, 1),
('Customer Pickup', 0, 1),
('Our Truck', 1, 1),
('Common Carrier', 5, 1);

-- ------------------------------------------------------------
-- Payment Terms
-- ------------------------------------------------------------
INSERT IGNORE INTO payment_terms (name, net_days, active) VALUES
('Net 30', 30, 1),
('Net 60', 60, 1),
('Net 90', 90, 1),
('Due on Receipt', 0, 1),
('2/10 Net 30', 30, 1),
('COD', 0, 1);

-- ------------------------------------------------------------
-- Reason Codes
-- ------------------------------------------------------------
INSERT IGNORE INTO reason_codes (name, active) VALUES
('Cycle Count Correction', 1),
('Damaged', 1),
('Waste/Scrap', 1),
('Expired', 1),
('Opening Balance', 1),
('Other', 1);

-- ------------------------------------------------------------
-- Document Numbering Sequences
-- ------------------------------------------------------------
INSERT IGNORE INTO document_numbering_sequences (sequence_key, prefix, next_number) VALUES
('PURCHASE_ORDER', 'PO', 1),
('SALES_ORDER', 'SO', 1),
('REPACK_TICKET', 'RPK', 1),
('RMA', 'RMA', 1),
('QUOTE', 'QUO', 1),
('CONSIGNMENT', 'CON', 1),
('TRANSFER', 'TRF', 1),
('PICK_LIST', 'PKL', 1),
('SCAR', 'SCAR', 1),
('REQUISITION', 'REQ', 1);

-- ------------------------------------------------------------
-- System Settings
-- ------------------------------------------------------------
INSERT IGNORE INTO system_settings (setting_key, setting_value) VALUES
('default_lead_time_days', '3'),
('quote_expiration_days', '30'),
('receiving_discrepancy_threshold', '5'),
('cost_change_alert_threshold', '5'),
('session_timeout_minutes', '30'),
('max_login_attempts', '5'),
('lockout_duration_minutes', '15'),
('password_min_length', '10'),
('password_require_uppercase', '1'),
('password_require_number', '1'),
('password_require_special', '1'),
('password_expiry_days', '0'),
('password_history_count', '5');
