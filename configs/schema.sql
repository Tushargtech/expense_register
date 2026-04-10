-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: localhost
-- Generation Time: Apr 10, 2026 at 10:37 AM
-- Server version: 10.4.28-MariaDB
-- PHP Version: 8.2.4

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Database: `expense_register`
--

-- --------------------------------------------------------

--
-- Table structure for table `budget_categories`
--

CREATE TABLE `budget_categories` (
  `budget_category_id` int(11) NOT NULL,
  `budget_category_name` varchar(100) DEFAULT NULL,
  `budget_category_code` varchar(50) DEFAULT NULL,
  `budget_category_type` varchar(50) DEFAULT NULL,
  `budget_category_description` text DEFAULT NULL,
  `budget_category_is_active` tinyint(1) DEFAULT 1,
  `budget_category_created_by` int(11) DEFAULT NULL,
  `budget_category_created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `budget_category_updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `departments`
--

CREATE TABLE `departments` (
  `id` int(11) NOT NULL,
  `department_name` varchar(100) NOT NULL,
  `department_code` varchar(50) NOT NULL,
  `department_head_user_id` int(11) DEFAULT NULL,
  `department_created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `department_budgets`
--

CREATE TABLE `department_budgets` (
  `budget_id` int(11) NOT NULL,
  `department_id` int(11) DEFAULT NULL,
  `budget_fiscal_year` year(4) DEFAULT NULL,
  `budget_fiscal_period` varchar(20) DEFAULT NULL,
  `budget_category` varchar(100) DEFAULT NULL,
  `budget_category_id` int(11) DEFAULT NULL,
  `budget_allocated_amount` decimal(15,2) DEFAULT NULL,
  `budget_currency` varchar(3) DEFAULT NULL,
  `budget_notes` text DEFAULT NULL,
  `budget_uploaded_by` int(11) DEFAULT NULL,
  `budget_created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `budget_updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `requests`
--

CREATE TABLE `requests` (
  `request_id` int(11) NOT NULL,
  `request_reference_no` varchar(30) NOT NULL,
  `request_type` enum('expense','purchase','other') NOT NULL,
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
  `request_resolved_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `request_actions`
--

CREATE TABLE `request_actions` (
  `action_id` int(11) NOT NULL,
  `request_id` int(11) NOT NULL,
  `workflow_step_id` int(11) DEFAULT NULL,
  `action` enum('approve','reject','reassign') NOT NULL,
  `acted_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `action_actor_id` int(11) NOT NULL,
  `action_reassigned_to` int(11) DEFAULT NULL,
  `action_comment` text DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `request_attachments`
--

CREATE TABLE `request_attachments` (
  `attachment_id` int(11) NOT NULL,
  `request_id` int(11) NOT NULL,
  `attachment_file_name` varchar(255) NOT NULL,
  `attachment_stored_name` varchar(255) NOT NULL,
  `attachment_file_path` varchar(500) NOT NULL,
  `attachment_file_size` int(11) NOT NULL,
  `attachment_mime_type` varchar(100) NOT NULL,
  `attachment_type` enum('invoice','receipt','other') NOT NULL,
  `attachment_uploaded_by` int(11) NOT NULL,
  `attachment_uploaded_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `request_step_assignments`
--

CREATE TABLE `request_step_assignments` (
  `request_step_id` int(11) NOT NULL,
  `request_id` int(11) NOT NULL,
  `workflow_step_id` int(11) NOT NULL,
  `request_step_assigned_to` int(11) NOT NULL,
  `request_step_status` enum('pending','approved','rejected') NOT NULL,
  `request_step_acted_at` timestamp NULL DEFAULT NULL,
  `request_step_comment` text DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `roles`
--

CREATE TABLE `roles` (
  `role_id` int(11) NOT NULL,
  `role_name` varchar(100) NOT NULL,
  `role_slug` varchar(100) NOT NULL,
  `role_permissions` text DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `roles`
--

INSERT INTO `roles` (`role_id`, `role_name`, `role_slug`, `role_permissions`) VALUES
(1, 'Finance', 'finance', NULL),
(2, 'Admin', 'admin', NULL),
(3, 'HR', 'hr', NULL),
(4, 'Manager', 'manager', NULL),
(5, 'Department Head', 'department_head', NULL);

-- --------------------------------------------------------

--
-- Table structure for table `users`
--

CREATE TABLE `users` (
  `user_id` int(11) NOT NULL,
  `user_name` varchar(100) NOT NULL,
  `user_email` varchar(150) NOT NULL,
  `user_password_hash` varchar(255) NOT NULL,
  `user_role` enum('admin','hr','manager','employee','finance','department_head') NOT NULL,
  `department_id` int(11) DEFAULT NULL,
  `manager_id` int(11) DEFAULT NULL,
  `user_is_active` tinyint(1) DEFAULT 1,
  `user_created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `users`
--

INSERT INTO `users` (`user_id`, `user_name`, `user_email`, `user_password_hash`, `user_role`, `department_id`, `manager_id`, `user_is_active`, `user_created_at`) VALUES
(1, 'System Administrator', 'admin@example.com', 'admin123', 'admin', NULL, NULL, 1, '2026-04-03 07:57:40');

-- --------------------------------------------------------

--
-- Table structure for table `workflows`
--

CREATE TABLE `workflows` (
  `workflow_id` int(11) NOT NULL,
  `workflow_name` varchar(150) NOT NULL,
  `workflow_description` text DEFAULT NULL,
  `workflow_type` varchar(50) DEFAULT NULL,
  `workflow_is_active` tinyint(1) DEFAULT 1,
  `workflow_is_default` tinyint(1) DEFAULT 0,
  `workflow_amount_min` decimal(15,2) DEFAULT NULL,
  `workflow_amount_max` decimal(15,2) DEFAULT NULL,
  `workflow_created_by` int(11) DEFAULT NULL,
  `workflow_created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `workflow_updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `workflow_steps`
--

CREATE TABLE `workflow_steps` (
  `step_id` int(11) NOT NULL,
  `workflow_id` int(11) NOT NULL,
  `step_order` int(11) NOT NULL,
  `step_name` varchar(150) DEFAULT NULL,
  `step_approver_type` varchar(50) DEFAULT NULL,
  `step_approver_role` varchar(50) DEFAULT NULL,
  `step_approver_user_id` int(11) DEFAULT NULL,
  `step_amount_min` decimal(15,2) DEFAULT NULL,
  `step_amount_max` decimal(15,2) DEFAULT NULL,
  `step_is_required` tinyint(1) DEFAULT 1,
  `step_timeout_hours` int(11) DEFAULT NULL,
  `step_created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Indexes for dumped tables
--

--
-- Indexes for table `budget_categories`
--
ALTER TABLE `budget_categories`
  ADD PRIMARY KEY (`budget_category_id`),
  ADD UNIQUE KEY `uk_budget_categories_code` (`budget_category_code`),
  ADD KEY `idx_budget_categories_created_by` (`budget_category_created_by`);

--
-- Indexes for table `departments`
--
ALTER TABLE `departments`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uk_departments_code` (`department_code`),
  ADD KEY `idx_departments_head_user_id` (`department_head_user_id`);

--
-- Indexes for table `department_budgets`
--
ALTER TABLE `department_budgets`
  ADD PRIMARY KEY (`budget_id`),
  ADD KEY `idx_department_budgets_department_id` (`department_id`),
  ADD KEY `idx_department_budgets_category_id` (`budget_category_id`),
  ADD KEY `idx_department_budgets_uploaded_by` (`budget_uploaded_by`);

--
-- Indexes for table `requests`
--
ALTER TABLE `requests`
  ADD PRIMARY KEY (`request_id`),
  ADD UNIQUE KEY `uk_requests_reference_no` (`request_reference_no`),
  ADD KEY `idx_requests_department_id` (`department_id`),
  ADD KEY `idx_requests_budget_category_id` (`budget_category_id`),
  ADD KEY `idx_requests_workflow_id` (`workflow_id`),
  ADD KEY `idx_requests_current_step_id` (`request_current_step_id`),
  ADD KEY `idx_requests_submitted_by` (`request_submitted_by`);

--
-- Indexes for table `request_actions`
--
ALTER TABLE `request_actions`
  ADD PRIMARY KEY (`action_id`),
  ADD KEY `idx_request_actions_request_id` (`request_id`),
  ADD KEY `idx_request_actions_workflow_step_id` (`workflow_step_id`),
  ADD KEY `idx_request_actions_actor_id` (`action_actor_id`),
  ADD KEY `idx_request_actions_reassigned_to` (`action_reassigned_to`);

--
-- Indexes for table `request_attachments`
--
ALTER TABLE `request_attachments`
  ADD PRIMARY KEY (`attachment_id`),
  ADD KEY `idx_request_attachments_request_id` (`request_id`),
  ADD KEY `idx_request_attachments_uploaded_by` (`attachment_uploaded_by`);

--
-- Indexes for table `request_step_assignments`
--
ALTER TABLE `request_step_assignments`
  ADD PRIMARY KEY (`request_step_id`),
  ADD KEY `idx_request_step_assignments_request_id` (`request_id`),
  ADD KEY `idx_request_step_assignments_workflow_step_id` (`workflow_step_id`),
  ADD KEY `idx_request_step_assignments_assigned_to` (`request_step_assigned_to`);

--
-- Indexes for table `roles`
--
ALTER TABLE `roles`
  ADD PRIMARY KEY (`role_id`),
  ADD UNIQUE KEY `uk_roles_slug` (`role_slug`);

--
-- Indexes for table `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`user_id`),
  ADD UNIQUE KEY `user_email` (`user_email`),
  ADD KEY `idx_users_department_id` (`department_id`),
  ADD KEY `idx_users_manager_id` (`manager_id`);

--
-- Indexes for table `workflows`
--
ALTER TABLE `workflows`
  ADD PRIMARY KEY (`workflow_id`),
  ADD KEY `idx_workflows_created_by` (`workflow_created_by`);

--
-- Indexes for table `workflow_steps`
--
ALTER TABLE `workflow_steps`
  ADD PRIMARY KEY (`step_id`),
  ADD KEY `idx_workflow_steps_workflow_id` (`workflow_id`),
  ADD KEY `idx_workflow_steps_approver_user_id` (`step_approver_user_id`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `budget_categories`
--
ALTER TABLE `budget_categories`
  MODIFY `budget_category_id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `departments`
--
ALTER TABLE `departments`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `department_budgets`
--
ALTER TABLE `department_budgets`
  MODIFY `budget_id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `requests`
--
ALTER TABLE `requests`
  MODIFY `request_id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `request_actions`
--
ALTER TABLE `request_actions`
  MODIFY `action_id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `request_attachments`
--
ALTER TABLE `request_attachments`
  MODIFY `attachment_id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `request_step_assignments`
--
ALTER TABLE `request_step_assignments`
  MODIFY `request_step_id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `roles`
--
ALTER TABLE `roles`
  MODIFY `role_id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `users`
--
ALTER TABLE `users`
  MODIFY `user_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `workflows`
--
ALTER TABLE `workflows`
  MODIFY `workflow_id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `workflow_steps`
--
ALTER TABLE `workflow_steps`
  MODIFY `step_id` int(11) NOT NULL AUTO_INCREMENT;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `budget_categories`
--
ALTER TABLE `budget_categories`
  ADD CONSTRAINT `budget_categories_ibfk_1` FOREIGN KEY (`budget_category_created_by`) REFERENCES `users` (`user_id`);

--
-- Constraints for table `departments`
--
ALTER TABLE `departments`
  ADD CONSTRAINT `departments_ibfk_1` FOREIGN KEY (`department_head_user_id`) REFERENCES `users` (`user_id`);

--
-- Constraints for table `department_budgets`
--
ALTER TABLE `department_budgets`
  ADD CONSTRAINT `department_budgets_ibfk_1` FOREIGN KEY (`department_id`) REFERENCES `departments` (`id`),
  ADD CONSTRAINT `department_budgets_ibfk_2` FOREIGN KEY (`budget_category_id`) REFERENCES `budget_categories` (`budget_category_id`),
  ADD CONSTRAINT `department_budgets_ibfk_3` FOREIGN KEY (`budget_uploaded_by`) REFERENCES `users` (`user_id`);

--
-- Constraints for table `requests`
--
ALTER TABLE `requests`
  ADD CONSTRAINT `requests_ibfk_1` FOREIGN KEY (`department_id`) REFERENCES `departments` (`id`),
  ADD CONSTRAINT `requests_ibfk_2` FOREIGN KEY (`workflow_id`) REFERENCES `workflows` (`workflow_id`),
  ADD CONSTRAINT `requests_ibfk_3` FOREIGN KEY (`request_current_step_id`) REFERENCES `workflow_steps` (`step_id`),
  ADD CONSTRAINT `requests_ibfk_4` FOREIGN KEY (`request_submitted_by`) REFERENCES `users` (`user_id`),
  ADD CONSTRAINT `requests_ibfk_5` FOREIGN KEY (`budget_category_id`) REFERENCES `budget_categories` (`budget_category_id`);

--
-- Constraints for table `request_actions`
--
ALTER TABLE `request_actions`
  ADD CONSTRAINT `request_actions_ibfk_1` FOREIGN KEY (`request_id`) REFERENCES `requests` (`request_id`),
  ADD CONSTRAINT `request_actions_ibfk_2` FOREIGN KEY (`workflow_step_id`) REFERENCES `workflow_steps` (`step_id`),
  ADD CONSTRAINT `request_actions_ibfk_3` FOREIGN KEY (`action_actor_id`) REFERENCES `users` (`user_id`),
  ADD CONSTRAINT `request_actions_ibfk_4` FOREIGN KEY (`action_reassigned_to`) REFERENCES `users` (`user_id`);

--
-- Constraints for table `request_attachments`
--
ALTER TABLE `request_attachments`
  ADD CONSTRAINT `request_attachments_ibfk_1` FOREIGN KEY (`request_id`) REFERENCES `requests` (`request_id`),
  ADD CONSTRAINT `request_attachments_ibfk_2` FOREIGN KEY (`attachment_uploaded_by`) REFERENCES `users` (`user_id`);

--
-- Constraints for table `request_step_assignments`
--
ALTER TABLE `request_step_assignments`
  ADD CONSTRAINT `request_step_assignments_ibfk_1` FOREIGN KEY (`request_id`) REFERENCES `requests` (`request_id`),
  ADD CONSTRAINT `request_step_assignments_ibfk_2` FOREIGN KEY (`workflow_step_id`) REFERENCES `workflow_steps` (`step_id`),
  ADD CONSTRAINT `request_step_assignments_ibfk_3` FOREIGN KEY (`request_step_assigned_to`) REFERENCES `users` (`user_id`);

--
-- Constraints for table `users`
--
ALTER TABLE `users`
  ADD CONSTRAINT `users_ibfk_1` FOREIGN KEY (`department_id`) REFERENCES `departments` (`id`),
  ADD CONSTRAINT `users_ibfk_2` FOREIGN KEY (`manager_id`) REFERENCES `users` (`user_id`);

--
-- Constraints for table `workflows`
--
ALTER TABLE `workflows`
  ADD CONSTRAINT `workflows_ibfk_1` FOREIGN KEY (`workflow_created_by`) REFERENCES `users` (`user_id`);

--
-- Constraints for table `workflow_steps`
--
ALTER TABLE `workflow_steps`
  ADD CONSTRAINT `workflow_steps_ibfk_1` FOREIGN KEY (`workflow_id`) REFERENCES `workflows` (`workflow_id`),
  ADD CONSTRAINT `workflow_steps_ibfk_2` FOREIGN KEY (`step_approver_user_id`) REFERENCES `users` (`user_id`);
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
