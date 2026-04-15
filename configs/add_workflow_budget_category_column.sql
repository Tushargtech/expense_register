-- Add budget category linkage to workflows.
-- Safe to run multiple times.

ALTER TABLE workflows
    ADD COLUMN IF NOT EXISTS budget_category_id INT(11) DEFAULT NULL AFTER workflow_description;

UPDATE workflows w
LEFT JOIN (
    SELECT
        LOWER(TRIM(budget_category_type)) AS mapped_type,
        MIN(budget_category_id) AS mapped_budget_category_id
    FROM budget_categories
    WHERE budget_category_is_active = 1
    GROUP BY LOWER(TRIM(budget_category_type))
) type_map ON type_map.mapped_type = LOWER(TRIM(w.workflow_type))
SET w.budget_category_id = type_map.mapped_budget_category_id
WHERE w.budget_category_id IS NULL;

SET @has_idx := (
  SELECT COUNT(*)
  FROM information_schema.STATISTICS
  WHERE TABLE_SCHEMA = DATABASE()
    AND TABLE_NAME = 'workflows'
    AND INDEX_NAME = 'idx_workflows_budget_category_id'
);
SET @sql := IF(@has_idx > 0,
  'SELECT 1',
  'ALTER TABLE workflows ADD INDEX idx_workflows_budget_category_id (budget_category_id)'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @has_fk := (
  SELECT COUNT(*)
  FROM information_schema.TABLE_CONSTRAINTS
  WHERE TABLE_SCHEMA = DATABASE()
    AND TABLE_NAME = 'workflows'
    AND CONSTRAINT_NAME = 'workflows_ibfk_2'
    AND CONSTRAINT_TYPE = 'FOREIGN KEY'
);
SET @sql := IF(@has_fk > 0,
  'SELECT 1',
  'ALTER TABLE workflows ADD CONSTRAINT workflows_ibfk_2 FOREIGN KEY (budget_category_id) REFERENCES budget_categories (budget_category_id)'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;
