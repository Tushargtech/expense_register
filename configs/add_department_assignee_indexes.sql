ALTER TABLE `users`
  ADD INDEX `idx_users_department_manager_active` (`department_id`, `manager_id`, `user_is_active`);
