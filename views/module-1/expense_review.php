<?php
$expenseDetail = isset($expenseDetail) && is_array($expenseDetail) ? $expenseDetail : [];
$request = isset($expenseDetail['request']) && is_array($expenseDetail['request']) ? $expenseDetail['request'] : [];
$attachments = isset($expenseDetail['attachments']) && is_array($expenseDetail['attachments']) ? $expenseDetail['attachments'] : [];
$actions = isset($expenseDetail['actions']) && is_array($expenseDetail['actions']) ? $expenseDetail['actions'] : [];
$steps = isset($expenseDetail['steps']) && is_array($expenseDetail['steps']) ? $expenseDetail['steps'] : [];
$canAct = isset($expenseDetail['canAct']) && $expenseDetail['canAct'];
$formError = isset($formError) ? (string) $formError : '';
$requestId = (int) ($request['request_id'] ?? 0);
$status = strtolower((string) ($request['request_status'] ?? 'pending'));
switch ($status) {
	case 'approved':
		$statusClass = 'status-active';
		break;
	case 'rejected':
		$statusClass = 'status-inactive';
		break;
	default:
		$statusClass = 'status-pending';
}
$submittedAt = (string) ($request['request_submitted_at'] ?? '');
$formattedSubmittedAt = $submittedAt !== '' ? date('d M Y, h:i A', strtotime($submittedAt)) : '';
?>

<main class="main">
	<div class="page-shell user-create-page">
		<div class="user-create-shell">
			<?php require ROOT_PATH . '/views/templates/flash_message.php'; ?>

			<section class="user-create-hero">
				<p class="user-create-kicker">Expense Review</p>
				<h1 class="user-create-title"><?php echo htmlspecialchars((string) ($request['request_title'] ?? 'Review Expense Request'), ENT_QUOTES, 'UTF-8'); ?></h1>
			</section>

			<?php if ($formError !== ''): ?>
				<div class="alert alert-danger py-2 mb-3" role="alert">
					<?php echo htmlspecialchars($formError, ENT_QUOTES, 'UTF-8'); ?>
				</div>
			<?php endif; ?>

			<section class="user-create-section mb-3">
				<div class="user-create-head">
					<div>
						<h2 class="user-create-section-title">Request Details</h2>
						<p class="user-create-note">Reference: <?php echo htmlspecialchars((string) ($request['request_reference_no'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></p>
					</div>
				</div>

				<div class="user-create-grid">
					<div class="user-create-field">
						<label class="user-create-label">Amount</label>
						<div class="user-create-input"><?php echo htmlspecialchars((string) ($request['request_currency'] ?? 'INR'), ENT_QUOTES, 'UTF-8'); ?> <?php echo number_format((float) ($request['request_amount'] ?? 0), 2); ?></div>
					</div>
					<div class="user-create-field">
						<label class="user-create-label">Status</label>
						<div class="user-create-input">
							<span class="status-pill <?php echo $statusClass; ?>">
								<?php echo htmlspecialchars(ucfirst($status), ENT_QUOTES, 'UTF-8'); ?>
							</span>
						</div>
					</div>
					<div class="user-create-field">
						<label class="user-create-label">Priority</label>
						<div class="user-create-input"><?php echo htmlspecialchars(ucfirst((string) ($request['request_priority'] ?? 'medium')), ENT_QUOTES, 'UTF-8'); ?></div>
					</div>
					<div class="user-create-field">
						<label class="user-create-label">Submitted By</label>
						<div class="user-create-input"><?php echo htmlspecialchars((string) ($request['submitted_by_name'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></div>
					</div>
					<div class="user-create-field">
						<label class="user-create-label">Department</label>
						<div class="user-create-input"><?php echo htmlspecialchars((string) ($request['department_name'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></div>
					</div>
					<div class="user-create-field">
						<label class="user-create-label">Budget Category</label>
						<div class="user-create-input">ID: <?php echo (int) ($request['budget_category_id'] ?? 0); ?></div>
					</div>
					<div class="user-create-field">
						<label class="user-create-label">Workflow</label>
						<div class="user-create-input">ID: <?php echo (int) ($request['workflow_id'] ?? 0); ?></div>
					</div>
					<div class="user-create-field user-create-field-medium">
						<label class="user-create-label">Category</label>
						<div class="user-create-input"><?php echo htmlspecialchars((string) ($request['request_category'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></div>
					</div>
					<div class="user-create-field user-create-field-medium">
						<label class="user-create-label">Submitted At</label>
						<div class="user-create-input"><?php echo htmlspecialchars($formattedSubmittedAt, ENT_QUOTES, 'UTF-8'); ?></div>
					</div>
					<div class="user-create-field user-create-field-medium">
						<label class="user-create-label">Description</label>
						<div class="user-create-input"><?php echo nl2br(htmlspecialchars((string) ($request['request_description'] ?? ''), ENT_QUOTES, 'UTF-8')); ?></div>
					</div>
					<div class="user-create-field user-create-field-medium">
						<label class="user-create-label">Notes</label>
						<div class="user-create-input"><?php echo nl2br(htmlspecialchars((string) ($request['request_notes'] ?? ''), ENT_QUOTES, 'UTF-8')); ?></div>
					</div>
				</div>
			</section>

			<section class="user-create-section mb-3">
				<div class="user-create-head">
					<div>
						<h2 class="user-create-section-title">Attachments</h2>
						<p class="user-create-note">Uploaded files linked to this request.</p>
					</div>
				</div>
				<div class="table-responsive user-table-wrap">
					<table class="table user-list-table align-middle mb-0">
						<thead>
							<tr>
								<th>File Name</th>
								<th>Type</th>
								<th>Size</th>
								<th>Uploaded At</th>
								<th class="text-end">Action</th>
							</tr>
						</thead>
						<tbody>
							<?php if (empty($attachments)): ?>
								<tr>
									<td colspan="5" class="text-center py-4 text-muted">No attachments found.</td>
								</tr>
							<?php else: ?>
								<?php foreach ($attachments as $attachment): ?>
									<?php
									$attachmentDate = (string) ($attachment['attachment_uploaded_at'] ?? '');
									$formattedAttachmentDate = $attachmentDate !== '' ? date('d M Y, h:i A', strtotime($attachmentDate)) : '';
									?>
									<tr>
										<td><?php echo htmlspecialchars((string) ($attachment['attachment_file_name'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></td>
										<td><?php echo htmlspecialchars((string) ($attachment['attachment_type'] ?? 'other'), ENT_QUOTES, 'UTF-8'); ?></td>
										<td><?php echo number_format(((int) ($attachment['attachment_file_size'] ?? 0)) / 1024, 2); ?> KB</td>
										<td><?php echo htmlspecialchars($formattedAttachmentDate, ENT_QUOTES, 'UTF-8'); ?></td>
										<td class="text-end">
											<a class="btn btn-sm btn-warning edit-btn" href="<?php echo htmlspecialchars((string) ($attachment['attachment_file_path'] ?? '#'), ENT_QUOTES, 'UTF-8'); ?>" target="_blank">View</a>
										</td>
									</tr>
								<?php endforeach; ?>
							<?php endif; ?>
						</tbody>
					</table>
				</div>
			</section>

			<section class="user-create-section mb-3">
				<div class="user-create-head">
					<div>
						<h2 class="user-create-section-title">Workflow Placeholder</h2>
						<p class="user-create-note">Step assignments are shown when records exist.</p>
					</div>
				</div>
				<div class="table-responsive user-table-wrap">
					<table class="table user-list-table align-middle mb-0">
						<thead>
							<tr>
								<th>Step Order</th>
								<th>Step Name</th>
								<th>Assigned To</th>
								<th>Status</th>
								<th>Comment</th>
							</tr>
						</thead>
						<tbody>
							<?php if (empty($steps)): ?>
								<tr>
									<td colspan="5" class="text-center py-4 text-muted">Workflow not yet configured or pending assignment.</td>
								</tr>
							<?php else: ?>
								<?php foreach ($steps as $step): ?>
									<tr>
										<td><?php echo (int) ($step['step_order'] ?? 0); ?></td>
										<td><?php echo htmlspecialchars((string) ($step['step_name'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></td>
										<td><?php echo htmlspecialchars((string) ($step['assigned_to_name'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></td>
										<td><?php echo htmlspecialchars((string) ($step['request_step_status'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></td>
										<td><?php echo htmlspecialchars((string) ($step['request_step_comment'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></td>
									</tr>
								<?php endforeach; ?>
							<?php endif; ?>
						</tbody>
					</table>
				</div>
			</section>

			<section class="user-create-section mb-3">
				<div class="user-create-head">
					<div>
						<h2 class="user-create-section-title">Actions</h2>
						<p class="user-create-note">Approve or reject pending requests.</p>
					</div>
				</div>

				<?php if ($status === 'pending' && $canAct): ?>
					<form method="POST" action="?route=expenses/action" class="user-create-form">
						<input type="hidden" name="request_id" value="<?php echo $requestId; ?>">
						<div class="user-create-grid">
							<div class="user-create-field user-create-field-medium">
								<label class="user-create-label" for="action_comment">Comment</label>
								<textarea class="user-create-input" id="action_comment" name="action_comment" rows="4" placeholder="Add review comment"></textarea>
							</div>
						</div>
						<div class="user-create-action-bar">
							<div class="user-create-action-copy">
								<strong>Review action</strong>
							</div>
							<div class="user-create-actions">
								<button type="submit" name="action" value="reject" class="user-create-btn user-create-btn-secondary">Reject</button>
								<button type="submit" name="action" value="approve" class="user-create-btn user-create-btn-primary">Approve</button>
							</div>
						</div>
					</form>
				<?php elseif ($status === 'pending' && !$canAct): ?>
					<div class="alert alert-info mb-0">You are not authorized to act on this request at this time.</div>
				<?php else: ?>
					<div class="alert alert-info mb-0">This request is already <?php echo htmlspecialchars($status, ENT_QUOTES, 'UTF-8'); ?>.</div>
				<?php endif; ?>
			</section>

			<section class="user-create-section">
				<div class="user-create-head">
					<div>
						<h2 class="user-create-section-title">Action History</h2>
						<p class="user-create-note">Audit records from request_actions.</p>
					</div>
				</div>
				<div class="table-responsive user-table-wrap">
					<table class="table user-list-table align-middle mb-0">
						<thead>
							<tr>
								<th>Action</th>
								<th>Actor ID</th>
								<th>Acted At</th>
								<th>Comment</th>
							</tr>
						</thead>
						<tbody>
							<?php if (empty($actions)): ?>
								<tr>
									<td colspan="4" class="text-center py-4 text-muted">No actions recorded.</td>
								</tr>
							<?php else: ?>
								<?php foreach ($actions as $actionRow): ?>
									<?php
									$actionDate = (string) ($actionRow['acted_at'] ?? '');
									$formattedActionDate = $actionDate !== '' ? date('d M Y, h:i A', strtotime($actionDate)) : '';
									?>
									<tr>
										<td><?php echo htmlspecialchars((string) ($actionRow['action'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></td>
										<td><?php echo (int) ($actionRow['action_actor_id'] ?? 0); ?></td>
										<td><?php echo htmlspecialchars($formattedActionDate, ENT_QUOTES, 'UTF-8'); ?></td>
										<td><?php echo htmlspecialchars((string) ($actionRow['action_comment'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></td>
									</tr>
								<?php endforeach; ?>
							<?php endif; ?>
						</tbody>
					</table>
				</div>
			</section>

			<div class="user-create-action-bar">
				<div class="user-create-action-copy">
					<strong>Back to all expense requests</strong>
				</div>
				<div class="user-create-actions">
					<a href="?route=expenses" class="user-create-btn user-create-btn-secondary">Back to Expense List</a>
				</div>
			</div>
		</div>
	</div>
</main>
