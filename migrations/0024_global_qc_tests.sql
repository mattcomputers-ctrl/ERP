-- Migration 0024: Global QC Test Library
-- Creates global test definitions and per-item test assignments

CREATE TABLE IF NOT EXISTS qc_test_definitions (
  id INT AUTO_INCREMENT PRIMARY KEY,
  test_name VARCHAR(150) NOT NULL UNIQUE,
  test_type ENUM('PASS_FAIL','NUMERIC_RANGE') NOT NULL,
  default_min_value DECIMAL(15,4) NULL,
  default_max_value DECIMAL(15,4) NULL,
  uom VARCHAR(50) NULL,
  description TEXT NULL,
  active TINYINT(1) DEFAULT 1,
  display_sequence INT DEFAULT 0,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS item_qc_tests (
  id INT AUTO_INCREMENT PRIMARY KEY,
  item_id INT NOT NULL,
  qc_test_definition_id INT NOT NULL,
  min_value DECIMAL(15,4) NULL,
  max_value DECIMAL(15,4) NULL,
  is_required TINYINT(1) DEFAULT 1,
  display_sequence INT DEFAULT 0,
  active TINYINT(1) DEFAULT 1,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY uq_item_test (item_id, qc_test_definition_id),
  FOREIGN KEY (item_id) REFERENCES items(id),
  FOREIGN KEY (qc_test_definition_id) REFERENCES qc_test_definitions(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Migrate existing qc_spec_tests into global definitions (best-effort)
INSERT IGNORE INTO qc_test_definitions (test_name, test_type, default_min_value, default_max_value, uom)
SELECT DISTINCT test_name, test_type, AVG(min_value), AVG(max_value), MAX(uom)
FROM qc_spec_tests
GROUP BY test_name, test_type;

INSERT IGNORE INTO schema_migrations (filename) VALUES ('0024_global_qc_tests.sql');
