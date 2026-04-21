-- Migrate request and category/workflow types from legacy values to new values.
-- Run this once on existing databases before using the updated code.

START TRANSACTION;

-- Normalize request and category type data values first.
UPDATE requests
SET request_type = CASE
    WHEN LOWER(TRIM(request_type)) = 'expense' THEN 'reimbursable'
    WHEN LOWER(TRIM(request_type)) = 'purchase' THEN 'company paid'
    ELSE request_type
END;

UPDATE budget_categories
SET budget_category_type = CASE
    WHEN LOWER(TRIM(budget_category_type)) = 'expense' THEN 'reimbursable'
    WHEN LOWER(TRIM(budget_category_type)) = 'purchase' THEN 'company paid'
    ELSE budget_category_type
END;

UPDATE workflows
SET workflow_type = CASE
    WHEN LOWER(TRIM(workflow_type)) = 'expense' THEN 'Reimbursable'
    WHEN LOWER(TRIM(workflow_type)) = 'purchase' THEN 'Company Paid'
    ELSE workflow_type
END;

-- Update enum definition to the new values.
ALTER TABLE requests
MODIFY COLUMN request_type ENUM('reimbursable', 'company paid', 'other') NOT NULL;

COMMIT;
