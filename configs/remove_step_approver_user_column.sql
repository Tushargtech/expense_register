-- Remove workflow step approver user column and related constraints/indexes.
-- Safe to run multiple times.

SET @has_fk := (
  SELECT COUNT(*)
  FROM information_schema.TABLE_CONSTRAINTS
  WHERE TABLE_SCHEMA = DATABASE()
    AND TABLE_NAME = 'workflow_steps'
    AND CONSTRAINT_NAME = 'workflow_steps_ibfk_2'
    AND CONSTRAINT_TYPE = 'FOREIGN KEY'
);
SET @sql := IF(@has_fk > 0,
  'ALTER TABLE workflow_steps DROP FOREIGN KEY workflow_steps_ibfk_2',
  'SELECT 1'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @has_idx := (
  SELECT COUNT(*)
  FROM information_schema.STATISTICS
  WHERE TABLE_SCHEMA = DATABASE()
    AND TABLE_NAME = 'workflow_steps'
    AND INDEX_NAME = 'idx_workflow_steps_approver_user_id'
);
SET @sql := IF(@has_idx > 0,
  'ALTER TABLE workflow_steps DROP INDEX idx_workflow_steps_approver_user_id',
  'SELECT 1'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

ALTER TABLE workflow_steps DROP COLUMN IF EXISTS step_approver_user_id;
