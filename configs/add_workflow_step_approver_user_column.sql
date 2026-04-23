ALTER TABLE `workflow_steps`
  ADD COLUMN `step_approver_user_id` int(11) DEFAULT NULL AFTER `step_approver_role`;

ALTER TABLE `workflow_steps`
  ADD KEY `idx_workflow_steps_approver_user_id` (`step_approver_user_id`);

ALTER TABLE `workflow_steps`
  ADD CONSTRAINT `workflow_steps_ibfk_2` FOREIGN KEY (`step_approver_user_id`) REFERENCES `users` (`user_id`);