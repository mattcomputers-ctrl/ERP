-- Migration: 0004_sales_production
-- Description: Sales quotes, sales orders, pick lists, shipments, invoices, and production batch management
-- Created: 2026-04-01

SET NAMES utf8mb4;
SET CHARACTER SET utf8mb4;

-- ------------------------------------------------------------
-- Quotes
-- ------------------------------------------------------------
CREATE TABLE quotes (
    id INT AUTO_INCREMENT PRIMARY KEY,
    quote_number VARCHAR(30) NOT NULL UNIQUE,
    customer_id INT NOT NULL,
    ship_to_id INT NULL,
    quote_date DATE NOT NULL,
    expiration_date DATE NOT NULL,
    status ENUM('DRAFT','SENT','ACCEPTED','DECLINED','EXPIRED','CONVERTED') NOT NULL DEFAULT 'DRAFT',
    notes TEXT NULL,
    lost_reason VARCHAR(100) NULL,
    lost_notes TEXT NULL,
    soft_reserve TINYINT(1) NOT NULL DEFAULT 0,
    created_by INT NOT NULL,
    converted_so_id INT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    deleted_at DATETIME NULL,
    FOREIGN KEY (customer_id) REFERENCES customers(id) ON UPDATE CASCADE ON DELETE RESTRICT,
    FOREIGN KEY (created_by) REFERENCES users(id) ON UPDATE CASCADE ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------
-- Quote Lines
-- ------------------------------------------------------------
CREATE TABLE quote_lines (
    id INT AUTO_INCREMENT PRIMARY KEY,
    quote_id INT NOT NULL,
    item_id INT NOT NULL,
    pack_extension_id INT NULL,
    quantity DECIMAL(15,4) NOT NULL,
    uom_id INT NOT NULL,
    unit_price DECIMAL(15,4) NOT NULL,
    notes TEXT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (quote_id) REFERENCES quotes(id) ON UPDATE CASCADE ON DELETE RESTRICT,
    FOREIGN KEY (item_id) REFERENCES items(id) ON UPDATE CASCADE ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------
-- Sales Orders
-- ------------------------------------------------------------
CREATE TABLE sales_orders (
    id INT AUTO_INCREMENT PRIMARY KEY,
    so_number VARCHAR(30) NOT NULL UNIQUE,
    customer_id INT NOT NULL,
    ship_to_id INT NOT NULL,
    facility_id INT NOT NULL,
    order_date DATE NOT NULL,
    requested_ship_date DATE NULL,
    promised_ship_date DATE NULL,
    promised_delivery_date DATE NULL,
    status ENUM('DRAFT','CONFIRMED','ON_HOLD','PARTIAL','SHIPPED','CANCELLED') NOT NULL DEFAULT 'DRAFT',
    hold_reason TEXT NULL,
    payment_terms_id INT NULL,
    ship_via_id INT NULL,
    external_notes TEXT NULL,
    internal_notes TEXT NULL,
    customer_po_number VARCHAR(100) NULL,
    is_sample TINYINT(1) NOT NULL DEFAULT 0,
    deposit_amount DECIMAL(15,4) NOT NULL DEFAULT 0,
    sales_rep_id INT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    deleted_at DATETIME NULL,
    FOREIGN KEY (customer_id) REFERENCES customers(id) ON UPDATE CASCADE ON DELETE RESTRICT,
    FOREIGN KEY (ship_to_id) REFERENCES ship_to_locations(id) ON UPDATE CASCADE ON DELETE RESTRICT,
    FOREIGN KEY (facility_id) REFERENCES facilities(id) ON UPDATE CASCADE ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------
-- Sales Order Lines
-- ------------------------------------------------------------
CREATE TABLE sales_order_lines (
    id INT AUTO_INCREMENT PRIMARY KEY,
    so_id INT NOT NULL,
    item_id INT NOT NULL,
    pack_extension_id INT NULL,
    ordered_quantity DECIMAL(15,4) NOT NULL,
    uom_id INT NOT NULL,
    unit_price DECIMAL(15,4) NOT NULL,
    shipped_quantity DECIMAL(15,4) NOT NULL DEFAULT 0,
    backordered_quantity DECIMAL(15,4) NOT NULL DEFAULT 0,
    external_notes TEXT NULL,
    packing_slip_note TEXT NULL,
    print_alias TINYINT(1) NOT NULL DEFAULT 1,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (so_id) REFERENCES sales_orders(id) ON UPDATE CASCADE ON DELETE RESTRICT,
    FOREIGN KEY (item_id) REFERENCES items(id) ON UPDATE CASCADE ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------
-- Order Surcharges
-- ------------------------------------------------------------
CREATE TABLE order_surcharges (
    id INT AUTO_INCREMENT PRIMARY KEY,
    so_id INT NOT NULL,
    surcharge_type_id INT NOT NULL,
    amount DECIMAL(15,4) NOT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (so_id) REFERENCES sales_orders(id) ON UPDATE CASCADE ON DELETE RESTRICT,
    FOREIGN KEY (surcharge_type_id) REFERENCES surcharge_types(id) ON UPDATE CASCADE ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------
-- Freight Quotes
-- ------------------------------------------------------------
CREATE TABLE freight_quotes (
    id INT AUTO_INCREMENT PRIMARY KEY,
    so_id INT NOT NULL,
    carrier_name VARCHAR(100) NOT NULL,
    service_level VARCHAR(100) NOT NULL,
    quoted_cost DECIMAL(15,4) NOT NULL,
    transit_days INT NULL,
    notes TEXT NULL,
    is_selected TINYINT(1) NOT NULL DEFAULT 0,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (so_id) REFERENCES sales_orders(id) ON UPDATE CASCADE ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------
-- Pick Lists
-- ------------------------------------------------------------
CREATE TABLE pick_lists (
    id INT AUTO_INCREMENT PRIMARY KEY,
    pkl_number VARCHAR(30) NOT NULL UNIQUE,
    facility_id INT NOT NULL,
    status ENUM('OPEN','IN_PROGRESS','COMPLETE') NOT NULL DEFAULT 'OPEN',
    created_by INT NOT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (facility_id) REFERENCES facilities(id) ON UPDATE CASCADE ON DELETE RESTRICT,
    FOREIGN KEY (created_by) REFERENCES users(id) ON UPDATE CASCADE ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------
-- Pick List Orders
-- ------------------------------------------------------------
CREATE TABLE pick_list_orders (
    id INT AUTO_INCREMENT PRIMARY KEY,
    pick_list_id INT NOT NULL,
    so_id INT NOT NULL,
    FOREIGN KEY (pick_list_id) REFERENCES pick_lists(id) ON UPDATE CASCADE ON DELETE RESTRICT,
    FOREIGN KEY (so_id) REFERENCES sales_orders(id) ON UPDATE CASCADE ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------
-- Pick List Lines
-- ------------------------------------------------------------
CREATE TABLE pick_list_lines (
    id INT AUTO_INCREMENT PRIMARY KEY,
    pick_list_id INT NOT NULL,
    so_line_id INT NOT NULL,
    quantity_to_pick DECIMAL(15,4) NOT NULL,
    quantity_picked DECIMAL(15,4) NULL,
    lot_number VARCHAR(100) NULL,
    status ENUM('PENDING','PICKED','PARTIAL','UNABLE') NOT NULL DEFAULT 'PENDING',
    unable_reason TEXT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (pick_list_id) REFERENCES pick_lists(id) ON UPDATE CASCADE ON DELETE RESTRICT,
    FOREIGN KEY (so_line_id) REFERENCES sales_order_lines(id) ON UPDATE CASCADE ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------
-- Shipments
-- ------------------------------------------------------------
CREATE TABLE shipments (
    id INT AUTO_INCREMENT PRIMARY KEY,
    shipment_number VARCHAR(30) NOT NULL UNIQUE,
    facility_id INT NOT NULL,
    ship_date DATE NOT NULL,
    actual_delivery_date DATE NULL,
    ship_via_id INT NULL,
    tracking_number VARCHAR(100) NULL,
    freight_cost DECIMAL(15,4) NULL,
    ship_to_id INT NOT NULL,
    sales_rep_id INT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (facility_id) REFERENCES facilities(id) ON UPDATE CASCADE ON DELETE RESTRICT,
    FOREIGN KEY (ship_to_id) REFERENCES ship_to_locations(id) ON UPDATE CASCADE ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------
-- Shipment Orders
-- ------------------------------------------------------------
CREATE TABLE shipment_orders (
    id INT AUTO_INCREMENT PRIMARY KEY,
    shipment_id INT NOT NULL,
    so_id INT NOT NULL,
    FOREIGN KEY (shipment_id) REFERENCES shipments(id) ON UPDATE CASCADE ON DELETE RESTRICT,
    FOREIGN KEY (so_id) REFERENCES sales_orders(id) ON UPDATE CASCADE ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------
-- Shipment Lines
-- ------------------------------------------------------------
CREATE TABLE shipment_lines (
    id INT AUTO_INCREMENT PRIMARY KEY,
    shipment_id INT NOT NULL,
    so_line_id INT NOT NULL,
    quantity_shipped DECIMAL(15,4) NOT NULL,
    unit_price DECIMAL(15,4) NOT NULL,
    packing_slip_note TEXT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (shipment_id) REFERENCES shipments(id) ON UPDATE CASCADE ON DELETE RESTRICT,
    FOREIGN KEY (so_line_id) REFERENCES sales_order_lines(id) ON UPDATE CASCADE ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------
-- Shipment Line Lots
-- ------------------------------------------------------------
CREATE TABLE shipment_line_lots (
    id INT AUTO_INCREMENT PRIMARY KEY,
    shipment_line_id INT NOT NULL,
    lot_number VARCHAR(100) NOT NULL,
    quantity_shipped DECIMAL(15,4) NOT NULL,
    fifo_lot_id INT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (shipment_line_id) REFERENCES shipment_lines(id) ON UPDATE CASCADE ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------
-- Invoices
-- ------------------------------------------------------------
CREATE TABLE invoices (
    id INT AUTO_INCREMENT PRIMARY KEY,
    invoice_number VARCHAR(30) NOT NULL UNIQUE,
    shipment_id INT NOT NULL,
    customer_id INT NOT NULL,
    so_id INT NULL,
    invoice_date DATE NOT NULL,
    due_date DATE NOT NULL,
    payment_terms_id INT NULL,
    ship_via_id INT NULL,
    subtotal DECIMAL(15,4) NOT NULL,
    freight_amount DECIMAL(15,4) NOT NULL DEFAULT 0,
    deposit_amount DECIMAL(15,4) NOT NULL DEFAULT 0,
    total_due DECIMAL(15,4) NOT NULL,
    status ENUM('OPEN','PAID','VOID') NOT NULL DEFAULT 'OPEN',
    internal_notes TEXT NULL,
    external_notes TEXT NULL,
    sales_rep_id INT NULL,
    last_edited_by INT NULL,
    last_edited_at DATETIME NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (shipment_id) REFERENCES shipments(id) ON UPDATE CASCADE ON DELETE RESTRICT,
    FOREIGN KEY (customer_id) REFERENCES customers(id) ON UPDATE CASCADE ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------
-- Invoice Edit History
-- ------------------------------------------------------------
CREATE TABLE invoice_edit_history (
    id INT AUTO_INCREMENT PRIMARY KEY,
    invoice_id INT NOT NULL,
    edited_by INT NOT NULL,
    field_changes JSON NOT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (invoice_id) REFERENCES invoices(id) ON UPDATE CASCADE ON DELETE RESTRICT,
    FOREIGN KEY (edited_by) REFERENCES users(id) ON UPDATE CASCADE ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------
-- Batch Templates
-- ------------------------------------------------------------
CREATE TABLE batch_templates (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(150) NOT NULL,
    item_id INT NOT NULL,
    recipe_version_id INT NULL,
    target_quantity DECIMAL(15,4) NOT NULL,
    notes TEXT NULL,
    internal_notes TEXT NULL,
    active TINYINT(1) NOT NULL DEFAULT 1,
    created_by INT NOT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (item_id) REFERENCES items(id) ON UPDATE CASCADE ON DELETE RESTRICT,
    FOREIGN KEY (created_by) REFERENCES users(id) ON UPDATE CASCADE ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------
-- Batch Template Packs
-- ------------------------------------------------------------
CREATE TABLE batch_template_packs (
    id INT AUTO_INCREMENT PRIMARY KEY,
    template_id INT NOT NULL,
    pack_extension_id INT NOT NULL,
    target_quantity DECIMAL(15,4) NOT NULL,
    FOREIGN KEY (template_id) REFERENCES batch_templates(id) ON UPDATE CASCADE ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------
-- Batch Template Equipment
-- ------------------------------------------------------------
CREATE TABLE batch_template_equipment (
    id INT AUTO_INCREMENT PRIMARY KEY,
    template_id INT NOT NULL,
    equipment_id INT NOT NULL,
    FOREIGN KEY (template_id) REFERENCES batch_templates(id) ON UPDATE CASCADE ON DELETE RESTRICT,
    FOREIGN KEY (equipment_id) REFERENCES equipment(id) ON UPDATE CASCADE ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------
-- Batch Tickets
-- ------------------------------------------------------------
CREATE TABLE batch_tickets (
    id INT AUTO_INCREMENT PRIMARY KEY,
    batch_number VARCHAR(30) NOT NULL UNIQUE,
    facility_id INT NOT NULL,
    item_id INT NOT NULL,
    recipe_version_id INT NOT NULL,
    target_quantity DECIMAL(15,4) NOT NULL,
    status ENUM('OPEN','IN_PROGRESS','CLOSED','CANCELLED') NOT NULL DEFAULT 'OPEN',
    priority ENUM('NORMAL','RUSH') NOT NULL DEFAULT 'NORMAL',
    scheduled_date DATE NOT NULL,
    assigned_to INT NULL,
    internal_notes TEXT NULL,
    external_notes TEXT NULL,
    parent_batch_id INT NULL,
    rework_of_batch_id INT NULL,
    is_rework TINYINT(1) NOT NULL DEFAULT 0,
    actual_yield DECIMAL(15,4) NULL,
    yield_percentage DECIMAL(8,4) NULL,
    total_batch_cost DECIMAL(15,4) NULL,
    cost_per_unit DECIMAL(15,4) NULL,
    closed_by INT NULL,
    closed_at DATETIME NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    deleted_at DATETIME NULL,
    FOREIGN KEY (facility_id) REFERENCES facilities(id) ON UPDATE CASCADE ON DELETE RESTRICT,
    FOREIGN KEY (item_id) REFERENCES items(id) ON UPDATE CASCADE ON DELETE RESTRICT,
    FOREIGN KEY (recipe_version_id) REFERENCES recipe_versions(id) ON UPDATE CASCADE ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------
-- Batch Ticket Packs
-- ------------------------------------------------------------
CREATE TABLE batch_ticket_packs (
    id INT AUTO_INCREMENT PRIMARY KEY,
    batch_id INT NOT NULL,
    pack_extension_id INT NOT NULL,
    target_quantity DECIMAL(15,4) NOT NULL,
    actual_quantity DECIMAL(15,4) NULL,
    container_count INT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (batch_id) REFERENCES batch_tickets(id) ON UPDATE CASCADE ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------
-- Batch Ticket Lines
-- ------------------------------------------------------------
CREATE TABLE batch_ticket_lines (
    id INT AUTO_INCREMENT PRIMARY KEY,
    batch_id INT NOT NULL,
    item_id INT NOT NULL,
    uom_id INT NOT NULL,
    theoretical_quantity DECIMAL(15,4) NOT NULL,
    reserved_quantity DECIMAL(15,4) NOT NULL DEFAULT 0,
    actual_quantity DECIMAL(15,4) NULL,
    substitute_used TINYINT(1) NOT NULL DEFAULT 0,
    substitute_item_id INT NULL,
    sequence INT NOT NULL DEFAULT 0,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (batch_id) REFERENCES batch_tickets(id) ON UPDATE CASCADE ON DELETE RESTRICT,
    FOREIGN KEY (item_id) REFERENCES items(id) ON UPDATE CASCADE ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------
-- Batch Ingredient Lots
-- ------------------------------------------------------------
CREATE TABLE batch_ingredient_lots (
    id INT AUTO_INCREMENT PRIMARY KEY,
    batch_id INT NOT NULL,
    batch_line_id INT NOT NULL,
    ingredient_item_id INT NOT NULL,
    supplier_lot_number VARCHAR(100) NULL,
    fifo_lot_id INT NULL,
    quantity_used DECIMAL(15,4) NOT NULL,
    unit_cost DECIMAL(15,4) NOT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (batch_id) REFERENCES batch_tickets(id) ON UPDATE CASCADE ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------
-- Batch Scrap
-- ------------------------------------------------------------
CREATE TABLE batch_scrap (
    id INT AUTO_INCREMENT PRIMARY KEY,
    batch_id INT NOT NULL,
    material_description VARCHAR(255) NOT NULL,
    item_id INT NULL,
    quantity DECIMAL(15,4) NOT NULL,
    uom_id INT NOT NULL,
    scrap_type ENUM('PROCESS_LOSS','CONTAMINATION','DAMAGED','DISPOSAL_REQUIRED') NOT NULL,
    notes TEXT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (batch_id) REFERENCES batch_tickets(id) ON UPDATE CASCADE ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------
-- Batch Lineage
-- ------------------------------------------------------------
CREATE TABLE batch_lineage (
    id INT AUTO_INCREMENT PRIMARY KEY,
    parent_batch_id INT NOT NULL,
    child_batch_id INT NOT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (parent_batch_id) REFERENCES batch_tickets(id) ON UPDATE CASCADE ON DELETE RESTRICT,
    FOREIGN KEY (child_batch_id) REFERENCES batch_tickets(id) ON UPDATE CASCADE ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------
-- Batch Equipment
-- ------------------------------------------------------------
CREATE TABLE batch_equipment (
    id INT AUTO_INCREMENT PRIMARY KEY,
    batch_id INT NOT NULL,
    equipment_id INT NOT NULL,
    FOREIGN KEY (batch_id) REFERENCES batch_tickets(id) ON UPDATE CASCADE ON DELETE RESTRICT,
    FOREIGN KEY (equipment_id) REFERENCES equipment(id) ON UPDATE CASCADE ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
