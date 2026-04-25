
CREATE DATABASE IF NOT EXISTS `expense_register`;
USE `expense_register`;

CREATE TABLE `auth_login_attempts` (
  `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
  `attempt_email` varchar(150) NOT NULL,
  `attempt_success` tinyint(1) NOT NULL DEFAULT 0,
  `attempted_at` timestamp NOT NULL DEFAULT current_timestamp(),

  PRIMARY KEY (`id`),
  KEY `idx_auth_attempt_email_time` (`attempt_email`, `attempted_at`),
  KEY `idx_auth_attempt_time` (`attempted_at`)

) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;



CREATE TABLE `budget_categories` (
  `budget_category_id` int(11) NOT NULL AUTO_INCREMENT,
  `budget_category_name` varchar(100) DEFAULT NULL,
  `budget_category_code` varchar(50) DEFAULT NULL,
  `budget_category_type` enum('expense','purchase') DEFAULT NULL,
  `budget_category_description` text DEFAULT NULL,
  `budget_category_is_active` tinyint(1) DEFAULT 1,
  `budget_category_created_by` int(11) DEFAULT NULL,
  `budget_category_created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `budget_category_updated_at` timestamp NULL DEFAULT NULL,

  PRIMARY KEY (`budget_category_id`),
  UNIQUE KEY `uk_budget_categories_code` (`budget_category_code`),
  KEY `idx_budget_categories_created_by` (`budget_category_created_by`)

) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;





INSERT INTO `budget_categories` (`budget_category_id`, `budget_category_name`, `budget_category_code`, `budget_category_type`, `budget_category_description`, `budget_category_is_active`, `budget_category_created_by`, `budget_category_created_at`, `budget_category_updated_at`) VALUES
(1, 'TRAVEL', 'CAT-01', 'expense', 'Travel expenses reimbursment.', 1, 1, '2026-04-08 08:01:49', '2026-04-23 13:09:05'),
(2, 'PARKING', 'CAT-02', 'expense', 'Parking expense reimbursement.', 1, 1, '2026-04-08 08:05:28', '2026-04-15 04:42:01'),
(3, 'SOFTWARE', 'CAT-03', 'expense', 'Purchase of new software for development.', 0, 1, '2026-04-08 11:49:13', '2026-04-15 04:45:56'),
(4, 'OPERATIONS', 'CAT-04', 'expense', 'Rent, electricity bills, employee salaries, and office supplies etc.', 1, 1, '2026-04-09 05:38:33', NULL),
(5, 'INFRASTRUCTURE', 'CAT-05', 'purchase', 'Buying an office building or land.', 1, 1, '2026-04-09 05:55:08', '2026-04-09 06:37:33'),
(6, 'VEHICLES', 'CAT-06', 'purchase', 'Buying a delivery van or a company car.', 1, 1, '2026-04-09 05:55:43', NULL),
(7, 'HARDWARE', 'CAT-07', 'purchase', 'Bulk purchases of laptops, servers, or high-end monitors.', 1, 1, '2026-04-09 05:56:20', NULL),
(8, 'FURNITURE', 'CAT-08', 'purchase', 'Desks, chairs, and conference tables for a new office.', 1, 1, '2026-04-09 05:56:56', NULL),
(9, 'OFFICE SUPPLIES', 'CAT-09', 'expense', 'Pens, paper, printer ink, and coffee.', 1, 1, '2026-04-09 05:59:17', NULL),
(10, 'RENT AND UTILITIES', 'CAT-10', 'expense', 'Monthly office rent, electricity, water, and internet.', 1, 1, '2026-04-09 06:01:33', NULL),
(11, 'PAYROLL', 'CAT-11', 'expense', 'Salaries, wages, bonuses, and benefits for your team.', 1, 1, '2026-04-09 06:02:04', NULL),
(12, 'RECRUITMENT', 'CAT-12', 'expense', 'The general bucket for finding and onboarding talent. Costs for posting jobs on LinkedIn or Indeed.\r\nPayments made to third-party headhunters or agencies.', 1, 1, '2026-04-09 06:19:33', '2026-04-15 04:33:19');



CREATE TABLE `departments` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `department_name` varchar(100) NOT NULL,
  `department_code` varchar(50) NOT NULL,
  `department_head_user_id` int(11) DEFAULT NULL,
  `department_created_at` timestamp NOT NULL DEFAULT current_timestamp(),

  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_departments_code` (`department_code`),
  KEY `idx_departments_head_user_id` (`department_head_user_id`)

) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;



INSERT INTO `departments` (`id`, `department_name`, `department_code`, `department_head_user_id`, `department_created_at`) VALUES
(1, 'ADMIN', 'DEPT-01', 1, '2026-04-06 11:34:46'),
(2, 'HR', 'DEPT-02', 2, '2026-04-06 11:34:46');



CREATE TABLE `department_budgets` (
  `budget_id` int(11) NOT NULL AUTO_INCREMENT,
  `department_id` int(11) DEFAULT NULL,
  `budget_fiscal_year` year(4) DEFAULT NULL,
  `budget_fiscal_period` enum('Q1','Q2','Q3','Q4','annual') DEFAULT NULL,
  `budget_category` varchar(100) DEFAULT NULL,
  `budget_category_id` int(11) DEFAULT NULL,
  `budget_allocated_amount` decimal(15,2) DEFAULT NULL,
  `budget_currency` varchar(3) DEFAULT NULL,
  `budget_notes` text DEFAULT NULL,
  `budget_uploaded_by` int(11) DEFAULT NULL,
  `budget_created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `budget_updated_at` timestamp NULL DEFAULT NULL,

  PRIMARY KEY (`budget_id`),
  KEY `idx_department_budgets_department_id` (`department_id`),
  KEY `idx_department_budgets_category_id` (`budget_category_id`),
  KEY `idx_department_budgets_uploaded_by` (`budget_uploaded_by`)

) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;



CREATE TABLE `password_reset_tokens` (
  `token_id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL,
  `token_hash` varchar(255) NOT NULL,
  `token_expires_at` datetime NOT NULL,
  `is_used` tinyint(1) DEFAULT 0,
  `used_at` datetime DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),

  PRIMARY KEY (`token_id`),
  UNIQUE KEY `token_hash` (`token_hash`),
  KEY `idx_token_hash` (`token_hash`),
  KEY `idx_user_id` (`user_id`),
  KEY `idx_expires_at` (`token_expires_at`)

) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;



CREATE TABLE `requests` (
  `request_id` int(11) NOT NULL AUTO_INCREMENT,
  `request_reference_no` varchar(30) NOT NULL,
  `request_type` enum('expense','purchase') DEFAULT NULL,
  `request_title` varchar(200) NOT NULL,
  `request_description` text DEFAULT NULL,
  `request_amount` decimal(15,2) NOT NULL,
  `request_currency` varchar(3) NOT NULL,
  `department_id` int(11) DEFAULT NULL,
  `request_category` varchar(100) DEFAULT NULL,
  `budget_category_id` int(11) DEFAULT NULL,
  `workflow_id` int(11) NOT NULL,
  `request_current_step_id` int(11) DEFAULT NULL,
  `request_submitted_by` int(11) NOT NULL,
  `request_status` enum('pending','approved','rejected') NOT NULL,
  `request_priority` enum('low','medium','high') NOT NULL,
  `request_notes` text DEFAULT NULL,
  `request_submitted_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `request_updated_at` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `request_resolved_at` timestamp NULL DEFAULT NULL,

  PRIMARY KEY (`request_id`),
  UNIQUE KEY `uk_requests_reference_no` (`request_reference_no`),

  KEY `idx_requests_department_id` (`department_id`),
  KEY `idx_requests_budget_category_id` (`budget_category_id`),
  KEY `idx_requests_workflow_id` (`workflow_id`),
  KEY `idx_requests_current_step_id` (`request_current_step_id`),
  KEY `idx_requests_submitted_by` (`request_submitted_by`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;



CREATE TABLE `request_actions` (
  `action_id` int(11) NOT NULL AUTO_INCREMENT,
  `request_id` int(11) NOT NULL,
  `workflow_step_id` int(11) DEFAULT NULL,
  `action` enum('approve','reject','reassign') NOT NULL,
  `acted_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `action_actor_id` int(11) NOT NULL,
  `action_reassigned_to` int(11) DEFAULT NULL,
  `action_comment` text DEFAULT NULL,

  PRIMARY KEY (`action_id`),
  KEY `idx_request_actions_request_id` (`request_id`),
  KEY `idx_request_actions_workflow_step_id` (`workflow_step_id`),
  KEY `idx_request_actions_actor_id` (`action_actor_id`),
  KEY `idx_request_actions_reassigned_to` (`action_reassigned_to`)

) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;




CREATE TABLE `request_attachments` (
  `attachment_id` int(11) NOT NULL AUTO_INCREMENT,
  `request_id` int(11) NOT NULL,
  `attachment_file_name` varchar(255) NOT NULL,
  `attachment_stored_name` varchar(255) NOT NULL,
  `attachment_file_path` varchar(500) DEFAULT NULL,
  `attachment_file_size` int(11) NOT NULL,
  `attachment_mime_type` varchar(100) NOT NULL,
  `attachment_type` enum('invoice','receipt','other') NOT NULL,
  `attachment_uploaded_by` int(11) NOT NULL,
  `attachment_uploaded_at` timestamp NOT NULL DEFAULT current_timestamp(),

  PRIMARY KEY (`attachment_id`),
  KEY `idx_request_attachments_request_id` (`request_id`),
  KEY `idx_request_attachments_uploaded_by` (`attachment_uploaded_by`)

) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;


CREATE TABLE `request_step_assignments` (
  `request_step_id` int(11) NOT NULL AUTO_INCREMENT,
  `request_id` int(11) NOT NULL,
  `workflow_step_id` int(11) NOT NULL,
  `request_step_assigned_to` int(11) NOT NULL,
  `request_step_status` enum('pending','approved','rejected','auto_approved','skipped') NOT NULL DEFAULT 'pending',
  `request_step_assigned_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `request_step_acted_at` timestamp NULL DEFAULT NULL,
  `request_step_comment` text DEFAULT NULL,
  `step_approver_type` varchar(50) DEFAULT NULL,
  `step_approver_role` varchar(50) DEFAULT NULL,
  `step_approver_user_id` int(11) DEFAULT NULL,

  PRIMARY KEY (`request_step_id`),
  KEY `idx_request_step_assignments_request_id` (`request_id`),
  KEY `idx_request_step_assignments_workflow_step_id` (`workflow_step_id`),
  KEY `idx_request_step_assignments_assigned_to` (`request_step_assigned_to`),
  KEY `idx_request_step_assignments_assigned_at` (`request_step_assigned_at`),
  KEY `idx_request_id` (`request_id`),
  KEY `idx_status` (`request_step_status`),
  KEY `idx_assigned_to` (`request_step_assigned_to`)

) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;



CREATE TABLE `roles` (
  `role_id` int(11) NOT NULL AUTO_INCREMENT,
  `role_name` varchar(100) NOT NULL,
  `role_slug` varchar(100) NOT NULL,
  `role_permissions` text DEFAULT NULL,

  PRIMARY KEY (`role_id`),
  UNIQUE KEY `uk_roles_slug` (`role_slug`)

) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;



INSERT INTO `roles` (`role_id`, `role_name`, `role_slug`, `role_permissions`) VALUES
(1, 'Finance', 'finance', '{\"users\": {\"view\": true, \"list\": true, \"view_all\": true, \"manage\": false}, \"departments\": {\"view\": true, \"list\": true, \"manage\": false, \"create\": false, \"edit\": false}, \"budget_categories\": {\"view\": true, \"manage\": true}, \"budget_monitor\": {\"view\": true, \"view_all\": true}, \"workflows\": {\"list\": true, \"view\": true, \"create\": true, \"edit\": true, \"manage\": false}, \"expenses\": {\"view\": true, \"review\": true, \"review_all\": true}}'),
(2, 'Admin', 'admin', '{\"users\": {\"view\": true, \"list\": true, \"view_all\": true, \"manage\": false}, \"departments\": {\"view\": true, \"list\": true, \"manage\": true, \"create\": true, \"edit\": true}, \"budget_categories\": {\"view\": true, \"manage\": false}, \"budget_monitor\": {\"view\": false, \"view_all\": false}, \"workflows\": {\"list\": true, \"view\": true, \"create\": true, \"edit\": true, \"manage\": false}, \"expenses\": {\"view\": true, \"review\": false, \"review_all\": false}}'),
(3, 'HR', 'hr', '{\"users\": {\"view\": true, \"list\": true, \"view_all\": true, \"manage\": true}, \"departments\": {\"view\": true, \"list\": true, \"manage\": false, \"create\": false, \"edit\": false}, \"budget_categories\": {\"view\": false, \"manage\": false}, \"budget_monitor\": {\"view\": false, \"view_all\": false}, \"workflows\": {\"list\": false, \"view\": false, \"create\": false, \"edit\": false, \"manage\": false}, \"expenses\": {\"view\": true, \"review\": false, \"review_all\": false}}'),
(4, 'Employee', 'employee', '{\"users\": {\"view\": true, \"list\": true, \"view_all\": false, \"manage\": false}, \"departments\": {\"view\": true, \"list\": true, \"manage\": false, \"create\": false, \"edit\": false}, \"budget_categories\": {\"view\": false, \"manage\": false}, \"budget_monitor\": {\"view\": false, \"view_all\": false}, \"workflows\": {\"list\": false, \"view\": true, \"create\": false, \"edit\": false, \"manage\": false}, \"expenses\": {\"view\": true, \"review\": false, \"review_all\": false}}');





CREATE TABLE `users` (
  `user_id` int(11) NOT NULL AUTO_INCREMENT,
  `user_name` varchar(100) NOT NULL,
  `user_email` varchar(150) NOT NULL,
  `user_password_hash` varchar(255) NOT NULL,
  `user_role` varchar(100) NOT NULL,
  `department_id` int(11) DEFAULT NULL,
  `manager_id` int(11) DEFAULT NULL,
  `user_is_active` tinyint(1) DEFAULT 1,
  `user_created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `password_must_reset` tinyint(1) DEFAULT 0,
  `force_password_change` tinyint(1) DEFAULT 0,

  PRIMARY KEY (`user_id`),
  UNIQUE KEY `uk_users_email` (`user_email`),
  KEY `idx_users_department_id` (`department_id`),
  KEY `idx_users_manager_id` (`manager_id`),
  KEY `idx_users_department_manager_active` (`department_id`,`manager_id`,`user_is_active`),
  KEY `idx_users_role` (`user_role`)

) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

INSERT INTO `users` (`user_id`, `user_name`, `user_email`, `user_password_hash`, `user_role`, `department_id`, `manager_id`, `user_is_active`, `user_created_at`, `password_must_reset`, `force_password_change`) VALUES
(1, 'System Administrator', 'admin@example.com', 'admin123', 'admin', 1, NULL, 1, '2026-04-03 07:57:40', 0, 0),
(2, 'HR Department Head', 'hr.depthead@example.com', '$2y$12$EwppjASakgFWaX07e4QPbO3ePQFOR.ukMX8.ptrtyLnVmgZgc7iB6', 'hr', 2, NULL, 1, '2026-04-12 08:36:54', 0, 0);


CREATE TABLE `workflows` (
  `workflow_id` int(11) NOT NULL AUTO_INCREMENT,
  `workflow_name` varchar(150) NOT NULL,
  `workflow_description` text DEFAULT NULL,
  `workflow_type` enum('expense','purchase') DEFAULT NULL,
  `workflow_is_active` tinyint(1) DEFAULT 1,
  `workflow_is_default` tinyint(1) DEFAULT 0,
  `workflow_amount_min` decimal(15,2) DEFAULT NULL,
  `workflow_amount_max` decimal(15,2) DEFAULT NULL,
  `workflow_created_by` int(11) DEFAULT NULL,
  `workflow_created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `workflow_updated_at` timestamp NULL DEFAULT NULL,

  PRIMARY KEY (`workflow_id`),
  KEY `idx_workflows_created_by` (`workflow_created_by`)

) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;


INSERT INTO `workflows` (`workflow_id`, `workflow_name`, `workflow_description`, `workflow_type`, `workflow_is_active`, `workflow_is_default`, `workflow_amount_min`, `workflow_amount_max`, `workflow_created_by`, `workflow_created_at`, `workflow_updated_at`) VALUES
(1, 'Standard Expense Claim', 'This is the step', 'expense', 1, 1, 0.00, 5000.00, 1, '2026-04-23 12:33:56', '2026-04-24 09:14:07'),
(2, 'High Value Expense Claim', 'This is a high value expense for employees.', 'expense', 1, 1, 5000.00, 100000000.00, 1, '2026-04-23 12:38:34', NULL),
(3, 'Standard Purchase', 'This workflow is for standard purchase of employees.', 'purchase', 1, 1, 0.00, 100000.00, 1, '2026-04-23 12:41:48', NULL),
(4, 'Small Purchase Request', 'This is workflow for small value purchase.', 'purchase', 1, 1, 0.00, 1000.00, 1, '2026-04-23 12:43:14', NULL);



CREATE TABLE `workflow_steps` (
  `step_id` int(11) NOT NULL AUTO_INCREMENT,
  `workflow_id` int(11) NOT NULL,
  `step_order` int(11) NOT NULL,
  `step_name` varchar(150) DEFAULT NULL,
  `step_approver_type` varchar(50) DEFAULT NULL,
  `step_approver_role` varchar(50) DEFAULT NULL,
  `step_approver_user_id` int(11) DEFAULT NULL,
  `step_is_required` tinyint(1) DEFAULT 1,
  `step_timeout_hours` int(11) DEFAULT NULL,
  `step_created_at` timestamp NOT NULL DEFAULT current_timestamp(),

  PRIMARY KEY (`step_id`),
  KEY `idx_workflow_steps_workflow_id` (`workflow_id`),
  KEY `idx_workflow_steps_approver_user_id` (`step_approver_user_id`)

) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;


INSERT INTO `workflow_steps` (`step_id`, `workflow_id`, `step_order`, `step_name`, `step_approver_type`, `step_approver_role`, `step_approver_user_id`, `step_is_required`, `step_timeout_hours`, `step_created_at`) VALUES
(25, 1, 1, 'Manager Approval', 'manager', 'manager', NULL, 1, 45, '2026-04-23 12:33:56'),
(26, 1, 3, 'Finance Approval', 'role', 'finance', 11, 1, 48, '2026-04-23 12:33:56'),
(27, 2, 1, 'Manager Approval', 'manager', 'manager', NULL, 1, 48, '2026-04-23 12:38:34'),
(28, 2, 2, 'Department Head Approval', 'department_head', 'department_head', NULL, 1, 48, '2026-04-23 12:38:34'),
(29, 2, 3, 'Finance Approval', 'role', 'finance', 11, 1, 48, '2026-04-23 12:38:34'),
(30, 3, 1, 'Manager Approval', 'manager', 'manager', NULL, 1, 49, '2026-04-23 12:41:48'),
(31, 3, 2, 'Finance Approval', 'role', 'finance', 11, 1, 49, '2026-04-23 12:41:48'),
(32, 4, 1, 'Manager Approval', 'manager', 'manager', NULL, 1, 40, '2026-04-23 12:43:14'),
(33, 1, 2, 'Department Head Approval', 'department_head', 'department_head', NULL, 1, 27, '2026-04-24 09:14:07');
