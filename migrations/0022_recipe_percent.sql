-- Add percentage column to recipe_steps for percent-based formulas
ALTER TABLE recipe_steps ADD COLUMN percentage DECIMAL(10,6) NULL AFTER quantity;

-- Migrate existing data: convert quantity to percentage
UPDATE recipe_steps rs
JOIN (
    SELECT recipe_version_id, SUM(quantity) as total
    FROM recipe_steps WHERE step_type = 'INGREDIENT'
    GROUP BY recipe_version_id
) totals ON rs.recipe_version_id = totals.recipe_version_id
SET rs.percentage = CASE WHEN totals.total > 0 THEN (rs.quantity / totals.total * 100) ELSE 0 END
WHERE rs.step_type = 'INGREDIENT' AND rs.percentage IS NULL;

INSERT IGNORE INTO schema_migrations (migration_name) VALUES ('0022_recipe_percent.sql');
