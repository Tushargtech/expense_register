-- Normalize workflow_type values and fix common misspellings.
-- Safe to run multiple times.

UPDATE workflows
SET workflow_type = CASE
    WHEN LOWER(TRIM(workflow_type)) IN ('expnse', 'expence', 'exponse') THEN 'Reimbursable'
    WHEN LOWER(TRIM(workflow_type)) IN ('puchase', 'purchse', 'prchase') THEN 'Company Paid'
    WHEN LOWER(TRIM(workflow_type)) = 'reimbursable' THEN 'Reimbursable'
    WHEN LOWER(TRIM(workflow_type)) = 'company paid' THEN 'Company Paid'
    WHEN LOWER(TRIM(workflow_type)) = 'other' THEN 'Other'
    ELSE workflow_type
END
WHERE workflow_type IS NOT NULL
  AND workflow_type <> '';

-- Align workflow_type with mapped budget category type where category mapping exists.
UPDATE workflows w
INNER JOIN budget_categories bc ON bc.budget_category_id = w.budget_category_id
SET w.workflow_type = CASE
    WHEN LOWER(TRIM(bc.budget_category_type)) = 'reimbursable' THEN 'Reimbursable'
    WHEN LOWER(TRIM(bc.budget_category_type)) = 'company paid' THEN 'Company Paid'
    WHEN LOWER(TRIM(bc.budget_category_type)) = 'other' THEN 'Other'
    ELSE w.workflow_type
END
WHERE bc.budget_category_type IS NOT NULL
  AND bc.budget_category_type <> '';
