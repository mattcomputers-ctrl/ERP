-- Migration: 0009_settings_smtp_email_notifications
-- Description: Email templates, notifications config, scheduled reports tables
-- Created: 2026-04-01

SET NAMES utf8mb4;
SET CHARACTER SET utf8mb4;

-- ------------------------------------------------------------
-- Email Templates
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS email_templates (
    id INT AUTO_INCREMENT PRIMARY KEY,
    template_type VARCHAR(100) NOT NULL UNIQUE,
    subject VARCHAR(255) NOT NULL DEFAULT '',
    body TEXT,
    active TINYINT(1) NOT NULL DEFAULT 1,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ------------------------------------------------------------
-- Notifications Config
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS notifications_config (
    id INT AUTO_INCREMENT PRIMARY KEY,
    alert_type VARCHAR(100) NOT NULL UNIQUE,
    enabled TINYINT(1) NOT NULL DEFAULT 1,
    recipients TEXT NULL,
    threshold_value DECIMAL(10,2) NULL,
    threshold_unit VARCHAR(50) NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ------------------------------------------------------------
-- Scheduled Reports
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS scheduled_reports (
    id INT AUTO_INCREMENT PRIMARY KEY,
    report_name VARCHAR(100) NOT NULL,
    schedule_type ENUM('DAILY','WEEKLY','MONTHLY') NOT NULL DEFAULT 'DAILY',
    schedule_day INT NULL,
    schedule_time VARCHAR(10) NOT NULL DEFAULT '06:00',
    output_format ENUM('CSV','PDF') NOT NULL DEFAULT 'CSV',
    recipients TEXT NOT NULL,
    active TINYINT(1) NOT NULL DEFAULT 1,
    last_run_at DATETIME NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ------------------------------------------------------------
-- Seed: Default Email Templates
-- ------------------------------------------------------------
INSERT INTO email_templates (template_type, subject, body, active) VALUES
('invoice', 'Invoice {invoice_number} from Precision Ink', '<p>Dear {customer_name},</p><p>Please find attached invoice <strong>{invoice_number}</strong> for order {order_number}.</p><p>Amount due: <strong>{amount_due}</strong><br>Due date: {due_date}</p><p>Thank you for your business.</p><p>Best regards,<br>{rep_name}</p>', 1),
('order_acknowledgment', 'Order Acknowledgment — {order_number}', '<p>Dear {customer_name},</p><p>Thank you for your order <strong>{order_number}</strong>.</p><p>Promised ship date: <strong>{promised_ship_date}</strong></p><p>We will notify you when your order ships.</p><p>Best regards,<br>{rep_name}</p>', 1),
('quote', 'Quote {quote_number} from Precision Ink', '<p>Dear {customer_name},</p><p>Please find attached quote <strong>{quote_number}</strong>.</p><p>This quote is valid until <strong>{expiration_date}</strong>.</p><p>Please do not hesitate to reach out with any questions.</p><p>Best regards,<br>{rep_name}</p>', 1),
('coa', 'Certificate of Analysis — {batch_number}', '<p>Dear {customer_name},</p><p>Please find attached the Certificate of Analysis for batch <strong>{batch_number}</strong> — {item_description}.</p><p>Best regards,<br>{rep_name}</p>', 1),
('purchase_order', 'Purchase Order {po_number} — Precision Ink', '<p>Dear {supplier_name},</p><p>Please find attached purchase order <strong>{po_number}</strong>.</p><p>Expected delivery date: <strong>{expected_delivery}</strong></p><p>Please confirm receipt and expected delivery.</p><p>Best regards,<br>{rep_name}</p>', 1),
('scar', 'Supplier Corrective Action Request — {scar_number}', '<p>Dear {supplier_name},</p><p>A Supplier Corrective Action Request (<strong>{scar_number}</strong>) has been issued regarding:</p><p>{issue_description}</p><p>Please respond by <strong>{due_date}</strong>.</p><p>Best regards,<br>Precision Ink Quality Team</p>', 1),
('credit_memo', 'Credit Memo for RMA {rma_number}', '<p>Dear {customer_name},</p><p>A credit memo has been issued for RMA <strong>{rma_number}</strong>.</p><p>Credit amount: <strong>{credit_amount}</strong></p><p>This credit will be applied to your account.</p><p>Best regards,<br>Precision Ink</p>', 1);

-- ------------------------------------------------------------
-- Seed: Notification Alert Types
-- ------------------------------------------------------------
INSERT INTO notifications_config (alert_type, enabled, recipients, threshold_value, threshold_unit) VALUES
('low_stock', 1, NULL, NULL, NULL),
('batch_overdue', 1, NULL, NULL, NULL),
('credit_limit_warning', 1, NULL, NULL, NULL),
('credit_override_used', 1, NULL, NULL, NULL),
('cost_change', 1, NULL, 5.00, 'percent'),
('quote_expiring', 1, NULL, 3.00, 'days'),
('orders_open_too_long', 1, NULL, 14.00, 'days'),
('orders_approaching_ship_date', 1, NULL, 3.00, 'days'),
('task_overdue', 1, NULL, NULL, NULL),
('batch_cost_variance', 1, NULL, 10.00, 'percent');

-- ------------------------------------------------------------
-- Mark migration as applied
-- ------------------------------------------------------------
INSERT INTO schema_migrations (migration_name) VALUES ('0009_settings_smtp_email_notifications');
