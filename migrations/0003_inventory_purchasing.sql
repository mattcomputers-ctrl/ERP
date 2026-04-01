-- Migration: 0003_inventory_purchasing
-- Description: Inventory management, FIFO lots, cycle counts, purchasing, and receiving
-- Created: 2026-04-01

SET NAMES utf8mb4;
SET CHARACTER SET utf8mb4;

-- ------------------------------------------------------------
-- FIFO Lots
-- ------------------------------------------------------------
CREATE TABLE fifo_lots (
    id INT AUTO_INCREMENT PRIMARY KEY,
    item_id INT NOT NULL,
    pack_extension_id INT NULL,
    facility_id INT NOT NULL,
    lot_number VARCHAR(100) NOT NULL,
    quantity DECIMAL(15,4) NOT NULL,
    remaining_quantity DECIMAL(15,4) NOT NULL,
    unit_cost DECIMAL(15,4) NOT NULL,
    source_type ENUM('RECEIPT','BATCH','REPACK','ADJUSTMENT','RMA','TRANSFER') NOT NULL,
    source_id BIGINT NULL,
    expiration_date DATE NULL,
    status ENUM('AVAILABLE','PENDING_INSPECTION','QUARANTINED','EXPIRED') NOT NULL DEFAULT 'AVAILABLE',
    quarantine_reason TEXT NULL,
    quarantined_by INT NULL,
    quarantined_at DATETIME NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (item_id) REFERENCES items(id) ON UPDATE CASCADE ON DELETE RESTRICT,
    FOREIGN KEY (facility_id) REFERENCES facilities(id) ON UPDATE CASCADE ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------
-- Inventory Transactions
-- ------------------------------------------------------------
CREATE TABLE inventory_transactions (
    id INT AUTO_INCREMENT PRIMARY KEY,
    item_id INT NOT NULL,
    pack_extension_id INT NULL,
    facility_id INT NOT NULL,
    fifo_lot_id INT NULL,
    transaction_type VARCHAR(50) NOT NULL,
    quantity DECIMAL(15,4) NOT NULL,
    unit_cost DECIMAL(15,4) NOT NULL,
    reference_type VARCHAR(50) NOT NULL,
    reference_id BIGINT NOT NULL,
    user_id INT NOT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (item_id) REFERENCES items(id) ON UPDATE CASCADE ON DELETE RESTRICT,
    FOREIGN KEY (facility_id) REFERENCES facilities(id) ON UPDATE CASCADE ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------
-- Inventory Reservations
-- ------------------------------------------------------------
CREATE TABLE inventory_reservations (
    id INT AUTO_INCREMENT PRIMARY KEY,
    item_id INT NOT NULL,
    facility_id INT NOT NULL,
    quantity DECIMAL(15,4) NOT NULL,
    reservation_type ENUM('BATCH','QUOTE') NOT NULL,
    reference_id BIGINT NOT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (item_id) REFERENCES items(id) ON UPDATE CASCADE ON DELETE RESTRICT,
    FOREIGN KEY (facility_id) REFERENCES facilities(id) ON UPDATE CASCADE ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------
-- Inventory Snapshots
-- ------------------------------------------------------------
CREATE TABLE inventory_snapshots (
    id INT AUTO_INCREMENT PRIMARY KEY,
    snapshot_date DATE NOT NULL,
    item_id INT NOT NULL,
    facility_id INT NOT NULL,
    quantity_on_hand DECIMAL(15,4) NOT NULL,
    unit_cost DECIMAL(15,4) NOT NULL,
    total_value DECIMAL(15,4) NOT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (item_id) REFERENCES items(id) ON UPDATE CASCADE ON DELETE RESTRICT,
    FOREIGN KEY (facility_id) REFERENCES facilities(id) ON UPDATE CASCADE ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------
-- Cycle Count Sessions
-- ------------------------------------------------------------
CREATE TABLE cycle_count_sessions (
    id INT AUTO_INCREMENT PRIMARY KEY,
    facility_id INT NOT NULL,
    status ENUM('OPEN','IN_REVIEW','POSTED','CANCELLED') NOT NULL DEFAULT 'OPEN',
    is_blind TINYINT(1) NOT NULL DEFAULT 0,
    created_by INT NOT NULL,
    posted_by INT NULL,
    posted_at DATETIME NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (facility_id) REFERENCES facilities(id) ON UPDATE CASCADE ON DELETE RESTRICT,
    FOREIGN KEY (created_by) REFERENCES users(id) ON UPDATE CASCADE ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------
-- Cycle Count Lines
-- ------------------------------------------------------------
CREATE TABLE cycle_count_lines (
    id INT AUTO_INCREMENT PRIMARY KEY,
    session_id INT NOT NULL,
    item_id INT NOT NULL,
    pack_extension_id INT NULL,
    book_quantity DECIMAL(15,4) NOT NULL,
    counted_quantity DECIMAL(15,4) NULL,
    variance DECIMAL(15,4) NULL,
    approved TINYINT(1) NOT NULL DEFAULT 0,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (session_id) REFERENCES cycle_count_sessions(id) ON UPDATE CASCADE ON DELETE CASCADE,
    FOREIGN KEY (item_id) REFERENCES items(id) ON UPDATE CASCADE ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------
-- Purchase Requisitions
-- ------------------------------------------------------------
CREATE TABLE purchase_requisitions (
    id INT AUTO_INCREMENT PRIMARY KEY,
    req_number VARCHAR(30) NOT NULL UNIQUE,
    requested_by INT NOT NULL,
    request_date DATE NOT NULL,
    required_by_date DATE NULL,
    status ENUM('DRAFT','SUBMITTED','APPROVED','REJECTED','CONVERTED','CANCELLED') NOT NULL DEFAULT 'DRAFT',
    justification TEXT NULL,
    notes TEXT NULL,
    approved_by INT NULL,
    approved_at DATETIME NULL,
    approval_notes TEXT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (requested_by) REFERENCES users(id) ON UPDATE CASCADE ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------
-- Purchase Requisition Lines
-- ------------------------------------------------------------
CREATE TABLE purchase_requisition_lines (
    id INT AUTO_INCREMENT PRIMARY KEY,
    requisition_id INT NOT NULL,
    item_id INT NOT NULL,
    pack_extension_id INT NULL,
    quantity DECIMAL(15,4) NOT NULL,
    uom_id INT NOT NULL,
    estimated_unit_cost DECIMAL(15,4) NULL,
    preferred_supplier_id INT NULL,
    notes TEXT NULL,
    converted_po_id INT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (requisition_id) REFERENCES purchase_requisitions(id) ON UPDATE CASCADE ON DELETE CASCADE,
    FOREIGN KEY (item_id) REFERENCES items(id) ON UPDATE CASCADE ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------
-- Purchase Orders
-- ------------------------------------------------------------
CREATE TABLE purchase_orders (
    id INT AUTO_INCREMENT PRIMARY KEY,
    po_number VARCHAR(30) NOT NULL UNIQUE,
    po_type ENUM('STANDARD','BLANKET') NOT NULL DEFAULT 'STANDARD',
    supplier_id INT NOT NULL,
    facility_id INT NOT NULL,
    order_date DATE NOT NULL,
    expected_delivery_date DATE NULL,
    status ENUM('DRAFT','SENT','PARTIAL','RECEIVED','CANCELLED') NOT NULL DEFAULT 'DRAFT',
    notes TEXT NULL,
    shipping_instructions TEXT NULL,
    revision_number INT NOT NULL DEFAULT 0,
    contract_start_date DATE NULL,
    contract_end_date DATE NULL,
    contracted_quantity DECIMAL(15,4) NULL,
    contracted_value DECIMAL(15,4) NULL,
    requisition_id INT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    deleted_at DATETIME NULL,
    FOREIGN KEY (supplier_id) REFERENCES suppliers(id) ON UPDATE CASCADE ON DELETE RESTRICT,
    FOREIGN KEY (facility_id) REFERENCES facilities(id) ON UPDATE CASCADE ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------
-- Purchase Order Lines
-- ------------------------------------------------------------
CREATE TABLE purchase_order_lines (
    id INT AUTO_INCREMENT PRIMARY KEY,
    po_id INT NOT NULL,
    item_id INT NOT NULL,
    pack_extension_id INT NULL,
    ordered_quantity DECIMAL(15,4) NOT NULL,
    uom_id INT NOT NULL,
    unit_cost DECIMAL(15,4) NOT NULL,
    received_quantity DECIMAL(15,4) NOT NULL DEFAULT 0,
    line_status ENUM('OPEN','PARTIAL','RECEIVED','CANCELLED') NOT NULL DEFAULT 'OPEN',
    cancellation_reason TEXT NULL,
    notes TEXT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (po_id) REFERENCES purchase_orders(id) ON UPDATE CASCADE ON DELETE CASCADE,
    FOREIGN KEY (item_id) REFERENCES items(id) ON UPDATE CASCADE ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------
-- PO Revision History
-- ------------------------------------------------------------
CREATE TABLE po_revision_history (
    id INT AUTO_INCREMENT PRIMARY KEY,
    po_id INT NOT NULL,
    revision_number INT NOT NULL,
    changed_by INT NOT NULL,
    field_changes JSON NOT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (po_id) REFERENCES purchase_orders(id) ON UPDATE CASCADE ON DELETE CASCADE,
    FOREIGN KEY (changed_by) REFERENCES users(id) ON UPDATE CASCADE ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------
-- PO Receipts
-- ------------------------------------------------------------
CREATE TABLE po_receipts (
    id INT AUTO_INCREMENT PRIMARY KEY,
    po_id INT NOT NULL,
    facility_id INT NOT NULL,
    received_by INT NOT NULL,
    receipt_date DATE NOT NULL,
    supplier_invoice_number VARCHAR(100) NOT NULL,
    notes TEXT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (po_id) REFERENCES purchase_orders(id) ON UPDATE CASCADE ON DELETE RESTRICT,
    FOREIGN KEY (received_by) REFERENCES users(id) ON UPDATE CASCADE ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------
-- PO Receipt Lines
-- ------------------------------------------------------------
CREATE TABLE po_receipt_lines (
    id INT AUTO_INCREMENT PRIMARY KEY,
    receipt_id INT NOT NULL,
    po_line_id INT NOT NULL,
    received_quantity DECIMAL(15,4) NOT NULL,
    unit_cost DECIMAL(15,4) NOT NULL,
    coc_received TINYINT(1) NOT NULL DEFAULT 0,
    coc_reference VARCHAR(100) NULL,
    coc_date DATE NULL,
    discrepancy_note TEXT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (receipt_id) REFERENCES po_receipts(id) ON UPDATE CASCADE ON DELETE CASCADE,
    FOREIGN KEY (po_line_id) REFERENCES purchase_order_lines(id) ON UPDATE CASCADE ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------
-- PO Receipt Lots
-- ------------------------------------------------------------
CREATE TABLE po_receipt_lots (
    id INT AUTO_INCREMENT PRIMARY KEY,
    receipt_line_id INT NOT NULL,
    supplier_lot_number VARCHAR(100) NOT NULL,
    quantity DECIMAL(15,4) NOT NULL,
    expiration_date DATE NULL,
    fifo_lot_id INT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (receipt_line_id) REFERENCES po_receipt_lines(id) ON UPDATE CASCADE ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------
-- Landed Costs
-- ------------------------------------------------------------
CREATE TABLE landed_costs (
    id INT AUTO_INCREMENT PRIMARY KEY,
    receipt_id INT NOT NULL,
    cost_type VARCHAR(100) NOT NULL,
    amount DECIMAL(15,4) NOT NULL,
    allocation_method ENUM('BY_QUANTITY','BY_VALUE','MANUAL') NOT NULL,
    notes TEXT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (receipt_id) REFERENCES po_receipts(id) ON UPDATE CASCADE ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------
-- Landed Cost Allocations
-- ------------------------------------------------------------
CREATE TABLE landed_cost_allocations (
    id INT AUTO_INCREMENT PRIMARY KEY,
    landed_cost_id INT NOT NULL,
    po_receipt_line_id INT NOT NULL,
    allocated_amount DECIMAL(15,4) NOT NULL,
    fifo_lot_id INT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (landed_cost_id) REFERENCES landed_costs(id) ON UPDATE CASCADE ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------
-- Record this migration
-- ------------------------------------------------------------
INSERT INTO schema_migrations (migration_name) VALUES ('0003_inventory_purchasing');
