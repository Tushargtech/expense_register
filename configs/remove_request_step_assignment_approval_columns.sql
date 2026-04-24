ALTER TABLE request_step_assignments
    DROP COLUMN IF EXISTS approved_by,
    DROP COLUMN IF EXISTS is_auto_approved;
