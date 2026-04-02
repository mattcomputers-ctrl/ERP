-- Global pack extension types and per-item overrides
CREATE TABLE IF NOT EXISTS pack_extension_types (
    id INT AUTO_INCREMENT PRIMARY KEY,
    code VARCHAR(20) NOT NULL UNIQUE,
    name VARCHAR(100) NOT NULL,
    default_net_weight DECIMAL(10,4) NOT NULL DEFAULT 0,
    tare_weight DECIMAL(10,4) NOT NULL DEFAULT 0,
    active TINYINT(1) NOT NULL DEFAULT 1,
    display_sequence INT NOT NULL DEFAULT 0,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS pack_extension_materials (
    id INT AUTO_INCREMENT PRIMARY KEY,
    pack_extension_type_id INT NOT NULL,
    item_id INT NOT NULL,
    quantity_per_pack DECIMAL(10,4) NOT NULL DEFAULT 1,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (pack_extension_type_id) REFERENCES pack_extension_types(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS item_pack_extension_overrides (
    id INT AUTO_INCREMENT PRIMARY KEY,
    item_id INT NOT NULL,
    pack_extension_type_id INT NOT NULL,
    net_weight_override DECIMAL(10,4) NULL,
    active TINYINT(1) NOT NULL DEFAULT 1,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uq_item_pack (item_id, pack_extension_type_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Migrate existing data
INSERT IGNORE INTO pack_extension_types (code, name, default_net_weight, tare_weight)
SELECT DISTINCT
    CONCAT('-', REPLACE(CAST(net_weight AS CHAR), '.0000', '')) as code,
    name,
    net_weight as default_net_weight,
    tare_weight
FROM item_pack_extensions
WHERE active = 1 AND name IS NOT NULL AND name != '';

INSERT IGNORE INTO item_pack_extension_overrides (item_id, pack_extension_type_id, net_weight_override)
SELECT ipe.item_id, pet.id,
       CASE WHEN ABS(ipe.net_weight - pet.default_net_weight) > 0.001 THEN ipe.net_weight ELSE NULL END
FROM item_pack_extensions ipe
JOIN pack_extension_types pet ON pet.name = ipe.name
WHERE ipe.active = 1;

INSERT IGNORE INTO schema_migrations (migration_name) VALUES ('0023_global_pack_extensions');
