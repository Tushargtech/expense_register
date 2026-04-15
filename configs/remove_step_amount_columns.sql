-- Remove workflow step amount range columns.
-- Safe to run multiple times due IF EXISTS guards.

ALTER TABLE workflow_steps
    DROP COLUMN IF EXISTS step_amount_min,
    DROP COLUMN IF EXISTS step_amount_max;
