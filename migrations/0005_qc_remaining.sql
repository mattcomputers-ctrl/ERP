-- Migration: 0005_qc_remaining
-- Description: QC specs, results, inspections, SCARs, equipment maintenance, transfers, RMAs, consignment, and repack
-- Created: 2026-04-01

SET NAMES utf8mb4;
SET CHARACTER SET utf8mb4;

-- ------------------------------------------------------------
-- QC Specifications
-- ------------------------------------------------------------
CREATE TABLE qc_specs (
    id INT AUTO_INCREMENT PRIMARY KEY,
    item_id INT NOT NULL,
    version_number INT NOT NULL DEFAULT 1,
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    created_by INT NOT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (item_id) REFERENCES items(id) ON UPDATE CASCADE ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------
-- QC Spec Tests
-- ------------------------------------------------------------
CREATE TABLE qc_spec_tests (
    id INT AUTO_INCREMENT PRIMARY KEY,
    spec_id INT NOT NULL,
    test_name VARCHAR(150) NOT NULL,
    test_type ENUM('PASS_FAIL','NUMERIC_RANGE') NOT NULL,
    min_value DECIMAL(15,4) NULL,
    max_value DECIMAL(15,4) NULL,
    uom VARCHAR(50) NULL,
    is_required TINYINT(1) NOT NULL DEFAULT 1,
    display_sequence INT NOT NULL DEFAULT 0,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (spec_id) REFERENCES qc_specs(id) ON UPDATE CASCADE ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------
-- QC Results
-- ------------------------------------------------------------
CREATE TABLE qc_results (
    id INT AUTO_INCREMENT PRIMARY KEY,
    batch_id INT NOT NULL,
    spec_id INT NOT NULL,
    test_id INT NOT NULL,
    result_value VARCHAR(255) NOT NULL,
    pass_fail ENUM('PASS','FAIL') NULL,
    is_out_of_spec TINYINT(1) NOT NULL DEFAULT 0,
    notes TEXT NULL,
    entered_by INT NOT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (batch_id) REFERENCES batch_tickets(id) ON UPDATE CASCADE ON DELETE RESTRICT,
    FOREIGN KEY (test_id) REFERENCES qc_spec_tests(id) ON UPDATE CASCADE ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------
-- QC Incoming Inspections
-- ------------------------------------------------------------
CREATE TABLE qc_incoming_inspections (
    id INT AUTO_INCREMENT PRIMARY KEY,
    fifo_lot_id INT NOT NULL,
    po_receipt_line_id INT NULL,
    inspected_by INT NULL,
    inspection_date DATE NULL,
    status ENUM('PENDING','PASSED','FAILED') NOT NULL DEFAULT 'PENDING',
    notes TEXT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (fifo_lot_id) REFERENCES fifo_lots(id) ON UPDATE CASCADE ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------
-- Supplier Corrective Action Requests (SCARs)
-- ------------------------------------------------------------
CREATE TABLE scars (
    id INT AUTO_INCREMENT PRIMARY KEY,
    scar_number VARCHAR(30) NOT NULL UNIQUE,
    supplier_id INT NOT NULL,
    po_receipt_id INT NULL,
    lot_number VARCHAR(100) NULL,
    issue_date DATE NOT NULL,
    description TEXT NOT NULL,
    required_action TEXT NULL,
    due_date DATE NULL,
    status ENUM('OPEN','RESPONSE_RECEIVED','CLOSED','CANCELLED') NOT NULL DEFAULT 'OPEN',
    supplier_response TEXT NULL,
    closure_notes TEXT NULL,
    closed_by INT NULL,
    closed_at DATETIME NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (supplier_id) REFERENCES suppliers(id) ON UPDATE CASCADE ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------
-- Equipment Maintenance Log
-- ------------------------------------------------------------
CREATE TABLE equipment_maintenance_log (
    id INT AUTO_INCREMENT PRIMARY KEY,
    equipment_id INT NOT NULL,
    maintenance_date DATE NOT NULL,
    maintenance_type ENUM('PREVENTIVE','CORRECTIVE','CALIBRATION','CLEANING') NOT NULL,
    description TEXT NOT NULL,
    performed_by VARCHAR(150) NOT NULL,
    next_due_date DATE NULL,
    logged_by INT NOT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (equipment_id) REFERENCES equipment(id) ON UPDATE CASCADE ON DELETE RESTRICT,
    FOREIGN KEY (logged_by) REFERENCES users(id) ON UPDATE CASCADE ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------
-- Transfer Orders
-- ------------------------------------------------------------
CREATE TABLE transfer_orders (
    id INT AUTO_INCREMENT PRIMARY KEY,
    trf_number VARCHAR(30) NOT NULL UNIQUE,
    from_facility_id INT NOT NULL,
    to_facility_id INT NOT NULL,
    requested_date DATE NOT NULL,
    status ENUM('DRAFT','SHIPPED','RECEIVED','CANCELLED') NOT NULL DEFAULT 'DRAFT',
    notes TEXT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (from_facility_id) REFERENCES facilities(id) ON UPDATE CASCADE ON DELETE RESTRICT,
    FOREIGN KEY (to_facility_id) REFERENCES facilities(id) ON UPDATE CASCADE ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------
-- Transfer Order Lines
-- ------------------------------------------------------------
CREATE TABLE transfer_order_lines (
    id INT AUTO_INCREMENT PRIMARY KEY,
    transfer_id INT NOT NULL,
    item_id INT NOT NULL,
    pack_extension_id INT NULL,
    quantity DECIMAL(15,4) NOT NULL,
    uom_id INT NOT NULL,
    shipped_quantity DECIMAL(15,4) NULL,
    received_quantity DECIMAL(15,4) NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (transfer_id) REFERENCES transfer_orders(id) ON UPDATE CASCADE ON DELETE RESTRICT,
    FOREIGN KEY (item_id) REFERENCES items(id) ON UPDATE CASCADE ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------
-- Transfer Line Lots
-- ------------------------------------------------------------
CREATE TABLE transfer_line_lots (
    id INT AUTO_INCREMENT PRIMARY KEY,
    transfer_line_id INT NOT NULL,
    lot_number VARCHAR(100) NOT NULL,
    quantity DECIMAL(15,4) NOT NULL,
    source_fifo_lot_id INT NULL,
    dest_fifo_lot_id INT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (transfer_line_id) REFERENCES transfer_order_lines(id) ON UPDATE CASCADE ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------
-- RMA Orders
-- ------------------------------------------------------------
CREATE TABLE rma_orders (
    id INT AUTO_INCREMENT PRIMARY KEY,
    rma_number VARCHAR(30) NOT NULL UNIQUE,
    customer_id INT NOT NULL,
    so_id INT NULL,
    rma_date DATE NOT NULL,
    return_reason VARCHAR(150) NOT NULL,
    status ENUM('OPEN','RECEIVED','CLOSED','CANCELLED') NOT NULL DEFAULT 'OPEN',
    notes TEXT NULL,
    facility_id INT NOT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (customer_id) REFERENCES customers(id) ON UPDATE CASCADE ON DELETE RESTRICT,
    FOREIGN KEY (facility_id) REFERENCES facilities(id) ON UPDATE CASCADE ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------
-- RMA Lines
-- ------------------------------------------------------------
CREATE TABLE rma_lines (
    id INT AUTO_INCREMENT PRIMARY KEY,
    rma_id INT NOT NULL,
    item_id INT NOT NULL,
    pack_extension_id INT NULL,
    authorized_quantity DECIMAL(15,4) NOT NULL,
    received_quantity DECIMAL(15,4) NULL,
    lot_number VARCHAR(100) NULL,
    disposition ENUM('RETURN_TO_STOCK','WRITE_OFF','HOLD_FOR_INSPECTION') NOT NULL,
    condition_notes TEXT NULL,
    fifo_lot_id INT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (rma_id) REFERENCES rma_orders(id) ON UPDATE CASCADE ON DELETE RESTRICT,
    FOREIGN KEY (item_id) REFERENCES items(id) ON UPDATE CASCADE ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------
-- Consignment Placements
-- ------------------------------------------------------------
CREATE TABLE consignment_placements (
    id INT AUTO_INCREMENT PRIMARY KEY,
    con_number VARCHAR(30) NOT NULL UNIQUE,
    customer_id INT NOT NULL,
    ship_to_id INT NOT NULL,
    item_id INT NOT NULL,
    pack_extension_id INT NULL,
    quantity_placed DECIMAL(15,4) NOT NULL,
    uom_id INT NOT NULL,
    placement_date DATE NOT NULL,
    status ENUM('ACTIVE','CLOSED') NOT NULL DEFAULT 'ACTIVE',
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (customer_id) REFERENCES customers(id) ON UPDATE CASCADE ON DELETE RESTRICT,
    FOREIGN KEY (item_id) REFERENCES items(id) ON UPDATE CASCADE ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------
-- Consignment Consumption
-- ------------------------------------------------------------
CREATE TABLE consignment_consumption (
    id INT AUTO_INCREMENT PRIMARY KEY,
    placement_id INT NOT NULL,
    consumption_date DATE NOT NULL,
    quantity_consumed DECIMAL(15,4) NOT NULL,
    customer_reference VARCHAR(100) NULL,
    invoice_id INT NULL,
    entered_by INT NOT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (placement_id) REFERENCES consignment_placements(id) ON UPDATE CASCADE ON DELETE RESTRICT,
    FOREIGN KEY (entered_by) REFERENCES users(id) ON UPDATE CASCADE ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------
-- Repack Tickets
-- ------------------------------------------------------------
CREATE TABLE repack_tickets (
    id INT AUTO_INCREMENT PRIMARY KEY,
    rpk_number VARCHAR(30) NOT NULL UNIQUE,
    facility_id INT NOT NULL,
    item_id INT NOT NULL,
    source_pack_id INT NULL,
    source_quantity DECIMAL(15,4) NOT NULL,
    destination_pack_id INT NULL,
    destination_quantity DECIMAL(15,4) NOT NULL,
    reason TEXT NULL,
    repack_date DATE NOT NULL,
    status ENUM('OPEN','CLOSED') NOT NULL DEFAULT 'OPEN',
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (facility_id) REFERENCES facilities(id) ON UPDATE CASCADE ON DELETE RESTRICT,
    FOREIGN KEY (item_id) REFERENCES items(id) ON UPDATE CASCADE ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
