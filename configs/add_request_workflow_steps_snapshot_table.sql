CREATE TABLE IF NOT EXISTS request_workflow_steps (
	request_workflow_step_id INT NOT NULL AUTO_INCREMENT,
	request_id INT NOT NULL,
	workflow_step_id INT NOT NULL,
	step_order INT NOT NULL,
	step_name VARCHAR(150) DEFAULT NULL,
	step_approver_type VARCHAR(50) DEFAULT NULL,
	step_approver_role VARCHAR(50) DEFAULT NULL,
	step_approver_user_id INT DEFAULT NULL,
	step_is_required TINYINT(1) DEFAULT 1,
	step_timeout_hours INT DEFAULT NULL,
	step_created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
	PRIMARY KEY (request_workflow_step_id),
	KEY idx_request_workflow_steps_request_id (request_id),
	KEY idx_request_workflow_steps_step_id (workflow_step_id),
	CONSTRAINT request_workflow_steps_ibfk_1 FOREIGN KEY (request_id) REFERENCES requests (request_id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
