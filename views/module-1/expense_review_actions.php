<?php
$requests = isset($requests) && is_array($requests) ? $requests : [];
$departments = isset($departments) && is_array($departments) ? $departments : [];
?>

<main class="main">
	<div class="page-shell user-list-page">
		<section class="user-list-panel">
			<?php require ROOT_PATH . '/views/templates/flash_message.php'; ?>

			<div class="page-header d-flex justify-content-between align-items-center mb-3">
				<div>
					<h1 class="page-title">Expense Review & Actions</h1>
					<p class="page-note">Requests currently assigned to you for approval or rejection.</p>
				</div>
			</div>

			<div class="table-responsive user-table-wrap">
				<table class="table user-list-table align-middle mb-0">
					<thead>
						<tr>
							<th>Request ID</th>
							<th>Reference No.</th>
							<th>Title</th>
							<th>Category</th>
							<th>Amount</th>
							<th>Submitted By</th>
							<th>Department</th>
							<th>Priority</th>
							<th>Status</th>
							<th>Submitted At</th>
							<th class="text-end pe-3">Action</th>
						</tr>
					</thead>
					<tbody>
						<?php if (empty($requests)): ?>
							<tr>
								<td colspan="11" class="text-center py-4 text-muted">No assigned expense approvals found.</td>
							</tr>
						<?php else: ?>
							<?php foreach ($requests as $row): ?>
								<?php
								$status = strtolower((string) ($row['request_status'] ?? 'pending'));
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
								$amount = number_format((float) ($row['request_amount'] ?? 0), 2);
								$submittedAt = (string) ($row['request_submitted_at'] ?? '');
								$formattedDate = $submittedAt !== '' ? date('d M Y, h:i A', strtotime($submittedAt)) : '';
								?>
								<tr>
									<td><?php echo (int) ($row['request_id'] ?? 0); ?></td>
									<td><?php echo htmlspecialchars((string) ($row['request_reference_no'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></td>
									<td><?php echo htmlspecialchars((string) ($row['request_title'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></td>
									<td><?php echo htmlspecialchars((string) ($row['request_category'] ?? '-'), ENT_QUOTES, 'UTF-8'); ?></td>
									<td><?php echo htmlspecialchars((string) ($row['request_currency'] ?? 'INR'), ENT_QUOTES, 'UTF-8'); ?> <?php echo $amount; ?></td>
									<td><?php echo htmlspecialchars((string) ($row['submitted_by_name'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></td>
									<td><?php echo htmlspecialchars((string) ($row['department_name'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></td>
									<td><?php echo htmlspecialchars(ucfirst((string) ($row['request_priority'] ?? 'medium')), ENT_QUOTES, 'UTF-8'); ?></td>
									<td><span class="status-pill <?php echo $statusClass; ?>"><?php echo htmlspecialchars(ucfirst($status), ENT_QUOTES, 'UTF-8'); ?></span></td>
									<td><?php echo htmlspecialchars($formattedDate, ENT_QUOTES, 'UTF-8'); ?></td>
									<td class="text-end pe-3">
										<a href="?route=expenses/review&id=<?php echo (int) ($row['request_id'] ?? 0); ?>" class="btn btn-sm btn-warning edit-btn">Review</a>
									</td>
								</tr>
							<?php endforeach; ?>
						<?php endif; ?>
					</tbody>
				</table>
			</div>
		</section>
	</div>
</main>
