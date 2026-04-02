-- Migration 0025: Price Lists with quantity breaks and package types
-- NOTE: numbered 0025 because 0024 is taken by global QC tests

-- Package types (global)
CREATE TABLE IF NOT EXISTS package_types (
  id INT AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(100) NOT NULL UNIQUE,
  active TINYINT(1) DEFAULT 1,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Seed defaults
INSERT IGNORE INTO package_types (name) VALUES ('Drum'), ('Pail'), ('Bag'), ('Tote'), ('Box'), ('IBC');

-- Price list headers
CREATE TABLE IF NOT EXISTS price_lists (
  id INT AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(150) NOT NULL,
  list_type ENUM('CUSTOMER','SUPPLIER') NOT NULL DEFAULT 'CUSTOMER',
  default_priority INT NOT NULL DEFAULT 10,
  effective_date DATE NOT NULL,
  expiration_date DATE NULL,
  notes TEXT NULL,
  active TINYINT(1) DEFAULT 1,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Price list lines
CREATE TABLE IF NOT EXISTS price_list_lines (
  id INT AUTO_INCREMENT PRIMARY KEY,
  price_list_id INT NOT NULL,
  item_id INT NOT NULL,
  external_code VARCHAR(100) NULL,
  package_type_id INT NULL,
  qty_per_package DECIMAL(15,4) NULL,
  break_qty_1 DECIMAL(15,4) NOT NULL DEFAULT 0,
  break_price_1 DECIMAL(15,4) NOT NULL DEFAULT 0,
  break_qty_2 DECIMAL(15,4) NULL,
  break_price_2 DECIMAL(15,4) NULL,
  break_qty_3 DECIMAL(15,4) NULL,
  break_price_3 DECIMAL(15,4) NULL,
  break_qty_4 DECIMAL(15,4) NULL,
  break_price_4 DECIMAL(15,4) NULL,
  break_qty_5 DECIMAL(15,4) NULL,
  break_price_5 DECIMAL(15,4) NULL,
  notes TEXT NULL,
  active TINYINT(1) DEFAULT 1,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  FOREIGN KEY (price_list_id) REFERENCES price_lists(id),
  FOREIGN KEY (item_id) REFERENCES items(id),
  FOREIGN KEY (package_type_id) REFERENCES package_types(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Customer price list assignments
CREATE TABLE IF NOT EXISTS customer_price_list_assignments (
  id INT AUTO_INCREMENT PRIMARY KEY,
  customer_id INT NOT NULL,
  price_list_id INT NOT NULL,
  priority_override INT NULL,
  active TINYINT(1) DEFAULT 1,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY uq_customer_list (customer_id, price_list_id),
  FOREIGN KEY (customer_id) REFERENCES customers(id),
  FOREIGN KEY (price_list_id) REFERENCES price_lists(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Supplier price list assignments
CREATE TABLE IF NOT EXISTS supplier_price_list_assignments (
  id INT AUTO_INCREMENT PRIMARY KEY,
  supplier_id INT NOT NULL,
  price_list_id INT NOT NULL,
  priority_override INT NULL,
  active TINYINT(1) DEFAULT 1,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY uq_supplier_list (supplier_id, price_list_id),
  FOREIGN KEY (supplier_id) REFERENCES suppliers(id),
  FOREIGN KEY (price_list_id) REFERENCES price_lists(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Add package columns to quote/SO/PO lines
ALTER TABLE quote_lines ADD COLUMN IF NOT EXISTS package_type_id INT NULL,
    ADD COLUMN IF NOT EXISTS qty_per_package DECIMAL(15,4) NULL,
    ADD COLUMN IF NOT EXISTS external_code VARCHAR(100) NULL;

ALTER TABLE sales_order_lines ADD COLUMN IF NOT EXISTS package_type_id INT NULL,
    ADD COLUMN IF NOT EXISTS qty_per_package DECIMAL(15,4) NULL,
    ADD COLUMN IF NOT EXISTS external_code VARCHAR(100) NULL;

ALTER TABLE purchase_order_lines ADD COLUMN IF NOT EXISTS external_code VARCHAR(100) NULL,
    ADD COLUMN IF NOT EXISTS package_type_id INT NULL,
    ADD COLUMN IF NOT EXISTS qty_per_package DECIMAL(15,4) NULL;

INSERT IGNORE INTO schema_migrations (filename) VALUES ('0025_price_lists.sql');
