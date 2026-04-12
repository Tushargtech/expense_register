<?php
$requests = isset($requests) && is_array($requests) ? $requests : [];
$filters = isset($filters) && is_array($filters) ? $filters : [];
$departments = isset($departments) && is_array($departments) ? $departments : [];
$searchValue = (string) ($filters['search'] ?? '');
$statusValue = (string) ($filters['status'] ?? '');
$departmentValue = (int) ($filters['department_id'] ?? 0);
$dateFromValue = (string) ($filters['date_from'] ?? '');
$dateToValue = (string) ($filters['date_to'] ?? '');
$currentPage = isset($currentPage) ? (int) $currentPage : 1;
$totalPages = isset($totalPages) ? (int) $totalPages : 1;
$perPage = (int) ($filters['limit'] ?? 10);

$baseQuery = [
	'route' => 'expenses',
	'search' => $searchValue,
	'status' => $statusValue,
	'department_id' => $departmentValue,
	'date_from' => $dateFromValue,
	'date_to' => $dateToValue,
];
?>

<main class="main">
	<div class="page-shell user-list-page">
		<section class="user-list-panel">
			<?php require ROOT_PATH . '/views/templates/flash_message.php'; ?>

			<form class="user-filter-bar search-bar" method="GET" action="">
				<input type="hidden" name="route" value="expenses">
				<div class="filter-layout">
					<div class="filter-left">
						<div class="filter-grid">
							<div class="filter-field search-field">
								<input
									type="text"
									name="search"
									class="form-control"
									placeholder="Search by id, reference, title or category"
									value="<?php echo htmlspecialchars($searchValue, ENT_QUOTES, 'UTF-8'); ?>"
								>
							</div>

							<div class="filter-field">
								<select name="status" class="form-select">
									<option value="">All Status</option>
									<option value="pending" <?php echo $statusValue === 'pending' ? 'selected' : ''; ?>>Pending</option>
									<option value="approved" <?php echo $statusValue === 'approved' ? 'selected' : ''; ?>>Approved</option>
									<option value="rejected" <?php echo $statusValue === 'rejected' ? 'selected' : ''; ?>>Rejected</option>
								</select>
							</div>

							<div class="filter-field">
								<select name="department_id" class="form-select">
									<option value="">All Departments</option>
									<?php foreach ($departments as $department): ?>
										<option value="<?php echo (int) ($department['id'] ?? 0); ?>" <?php echo $departmentValue === (int) ($department['id'] ?? 0) ? 'selected' : ''; ?>>
											<?php echo htmlspecialchars((string) ($department['department_name'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>
										</option>
									<?php endforeach; ?>
								</select>
							</div>

							<div class="filter-field">
								<input type="date" name="date_from" class="form-control" value="<?php echo htmlspecialchars($dateFromValue, ENT_QUOTES, 'UTF-8'); ?>" placeholder="From">
							</div>

							<div class="filter-actions">
								<button type="submit" class="btn btn-primary btn-filter">Search</button>
								<a href="?route=expenses" class="btn btn-outline-secondary btn-filter">Reset</a>
							</div>
						</div>
					</div>
					<div class="add-record-wrap">
						<a href="?route=expenses/create" class="btn btn-primary add-record-btn add-btn">
							<i class="bi bi-plus-lg me-1"></i>Add Expense
						</a>
					</div>
				</div>
			</form>

			<div class="table-responsive user-table-wrap">
				<table class="table user-list-table align-middle mb-0">
					<thead>
						<tr>
							<th>Serial No.</th>
							<th>Title</th>
							<th>Type</th>
							<th>Category</th>
							<th>Amount</th>
							<th>Department</th>
							<th>Status</th>
							<th class="text-end pe-3">Action</th>
						</tr>
					</thead>
					<tbody>
						<?php if (empty($requests)): ?>
							<tr>
								<td colspan="8" class="text-center py-4 text-muted">No expense requests found.</td>
							</tr>
						<?php else: ?>
							<?php foreach ($requests as $index => $row): ?>
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
								$serialNumber = (($currentPage - 1) * $perPage) + $index + 1;
								$typeLabel = ucfirst(strtolower((string) ($row['request_type'] ?? 'other')));
								?>
								<tr>
									<td><?php echo $serialNumber; ?></td>
									<td><?php echo htmlspecialchars((string) ($row['request_title'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></td>
									<td><?php echo htmlspecialchars($typeLabel, ENT_QUOTES, 'UTF-8'); ?></td>
									<td><?php echo htmlspecialchars((string) ($row['request_category'] ?? '-'), ENT_QUOTES, 'UTF-8'); ?></td>
									<td><?php echo htmlspecialchars((string) ($row['request_currency'] ?? 'INR'), ENT_QUOTES, 'UTF-8'); ?> <?php echo $amount; ?></td>
									<td><?php echo htmlspecialchars((string) ($row['department_name'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></td>
									<td><span class="status-pill <?php echo $statusClass; ?>"><?php echo htmlspecialchars(ucfirst($status), ENT_QUOTES, 'UTF-8'); ?></span></td>
									<td class="text-end pe-3">
											<a href="?route=expenses/review&id=<?php echo (int) ($row['request_id'] ?? 0); ?>" class="btn btn-sm btn-warning edit-btn">Review</a>
									</td>
								</tr>
							<?php endforeach; ?>
						<?php endif; ?>
					</tbody>
				</table>
			</div>

			<?php if ($totalPages > 1): ?>
				<nav class="user-pagination-wrap" aria-label="Expense request list pagination">
					<ul class="pagination user-pagination mb-0">
						<?php $prevPage = max(1, $currentPage - 1); ?>
						<li class="page-item <?php echo $currentPage <= 1 ? 'disabled' : ''; ?>">
							<a class="page-link" href="?<?php echo htmlspecialchars(http_build_query($baseQuery + ['page' => $prevPage]), ENT_QUOTES, 'UTF-8'); ?>">Prev</a>
						</li>

						<?php
						$startPage = max(1, $currentPage - 2);
						$endPage = min($totalPages, $currentPage + 2);
						for ($page = $startPage; $page <= $endPage; $page++):
						?>
							<li class="page-item <?php echo $page === $currentPage ? 'active' : ''; ?>">
								<a class="page-link" href="?<?php echo htmlspecialchars(http_build_query($baseQuery + ['page' => $page]), ENT_QUOTES, 'UTF-8'); ?>"><?php echo $page; ?></a>
							</li>
						<?php endfor; ?>

						<?php $nextPage = min($totalPages, $currentPage + 1); ?>
						<li class="page-item <?php echo $currentPage >= $totalPages ? 'disabled' : ''; ?>">
							<a class="page-link" href="?<?php echo htmlspecialchars(http_build_query($baseQuery + ['page' => $nextPage]), ENT_QUOTES, 'UTF-8'); ?>">Next</a>
						</li>
					</ul>
				</nav>
			<?php endif; ?>
		</section>
	</div>
</main>
