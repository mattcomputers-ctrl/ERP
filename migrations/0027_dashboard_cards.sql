-- Migration 0027: Dashboard card layout system

CREATE TABLE IF NOT EXISTS dashboard_card_definitions (
  id INT AUTO_INCREMENT PRIMARY KEY,
  card_key VARCHAR(100) NOT NULL UNIQUE,
  card_name VARCHAR(150) NOT NULL,
  description TEXT NULL,
  default_cols INT NOT NULL DEFAULT 4,
  required_permission VARCHAR(100) NULL,
  active TINYINT(1) DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS dashboard_group_layouts (
  id INT AUTO_INCREMENT PRIMARY KEY,
  group_id INT NOT NULL,
  card_key VARCHAR(100) NOT NULL,
  position INT NOT NULL DEFAULT 0,
  cols INT NOT NULL DEFAULT 4,
  active TINYINT(1) DEFAULT 1,
  UNIQUE KEY uq_group_card (group_id, card_key),
  FOREIGN KEY (group_id) REFERENCES `groups`(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS dashboard_user_layouts (
  id INT AUTO_INCREMENT PRIMARY KEY,
  user_id INT NOT NULL,
  card_key VARCHAR(100) NOT NULL,
  position INT NOT NULL DEFAULT 0,
  cols INT NOT NULL DEFAULT 4,
  visible TINYINT(1) DEFAULT 1,
  UNIQUE KEY uq_user_card (user_id, card_key),
  FOREIGN KEY (user_id) REFERENCES users(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

INSERT IGNORE INTO dashboard_card_definitions (card_key, card_name, description, default_cols, required_permission) VALUES
('orders_to_ship_today', 'Shipping Today', 'Orders with promised ship date = today', 3, 'sales_orders'),
('orders_to_ship_week', 'Shipping This Week', 'Orders shipping this week', 3, 'sales_orders'),
('unfulfillable_orders', 'Unfulfillable Orders', 'Items on order with insufficient inventory', 6, 'sales_orders'),
('uncovered_demand', 'Uncovered Demand', 'Items with no coverage plan', 6, 'sales_orders'),
('open_batches', 'Open Batches', 'Batches OPEN or IN_PROGRESS', 3, 'batch_tickets'),
('rush_batches', 'RUSH Batches', 'Batches flagged RUSH', 3, 'batch_tickets'),
('low_stock', 'Low Stock', 'Items below reorder minimum', 4, 'inventory'),
('pending_inspections', 'Pending Inspections', 'Lots awaiting QC inspection', 3, 'qc'),
('pending_requisitions', 'Pending Requisitions', 'Requisitions awaiting approval', 3, 'purchase_orders'),
('overdue_scars', 'Overdue SCARs', 'SCARs past due date', 3, 'qc'),
('my_tasks', 'My Tasks', 'CRM tasks assigned to me', 4, 'crm'),
('ar_summary', 'AR Summary', 'Outstanding receivables by aging', 6, 'invoices'),
('sales_mtd', 'Sales MTD', 'Revenue this month vs last', 4, 'invoices'),
('backorder_count', 'Backorders', 'Backordered sales order lines', 3, 'sales_orders'),
('announcements', 'Announcements', 'System announcements', 6, NULL);

INSERT IGNORE INTO schema_migrations (filename) VALUES ('0027_dashboard_cards.sql');
