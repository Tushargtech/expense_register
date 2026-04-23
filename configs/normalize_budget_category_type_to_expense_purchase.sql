UPDATE budget_categories
SET budget_category_type = CASE
    WHEN LOWER(TRIM(budget_category_type)) IN ('reimbursable', 'expense') THEN 'expense'
    WHEN LOWER(TRIM(budget_category_type)) IN ('company paid', 'purchase') THEN 'purchase'
    ELSE NULL
END
WHERE budget_category_type IS NOT NULL
  AND budget_category_type <> '';

ALTER TABLE `budget_categories`
    CHANGE `budget_category_type` `budget_category_type`
    ENUM('expense','purchase')
    CHARACTER SET utf8mb4
    COLLATE utf8mb4_general_ci
    NULL DEFAULT NULL;
