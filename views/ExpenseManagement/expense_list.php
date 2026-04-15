<?php
$requests = isset($requests) && is_array($requests) ? $requests : [];
$filters = isset($filters) && is_array($filters) ? $filters : [];
$departments = isset($departments) && is_array($departments) ? $departments : [];
$searchValue = (string) ($filters['search'] ?? '');
$statusValue = (string) ($filters['status'] ?? '');
$dateFromValue = (string) ($filters['date_from'] ?? '');
$dateToValue = (string) ($filters['date_to'] ?? '');
$expenseScopeRole = isset($expenseScopeRole) ? (string) $expenseScopeRole : '';
$canReviewExpenseRequests = isset($canReviewExpenseRequests) ? (bool) $canReviewExpenseRequests : false;
$currentPage = isset($currentPage) ? (int) $currentPage : 1;
$totalPages = isset($totalPages) ? (int) $totalPages : 1;
$perPage = 10;

$baseQuery = [
	'search' => $searchValue,
	'status' => $statusValue,
	'date_from' => $dateFromValue,
	'date_to' => $dateToValue,
];
?>

<main class="main">
	<div class="page-shell user-list-page">
		<section class="user-list-panel">
			<div class="list-page-header">
				<h2 class="list-page-title">Expenses List</h2>
			</div>
			<?php require ROOT_PATH . '/views/templates/flash_message.php'; ?>

			<form class="user-filter-bar search-bar" method="GET" action="<?php echo htmlspecialchars(buildCleanRouteUrl('expenses'), ENT_QUOTES, 'UTF-8'); ?>">
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
								<input type="date" name="date_from" class="form-control" value="<?php echo htmlspecialchars($dateFromValue, ENT_QUOTES, 'UTF-8'); ?>" placeholder="From">
							</div>

							<div class="filter-actions">
								<button type="submit" class="btn btn-primary btn-filter">Search</button>
								<a href="<?php echo htmlspecialchars(buildCleanRouteUrl('expenses'), ENT_QUOTES, 'UTF-8'); ?>" class="btn btn-outline-secondary btn-filter">Reset</a>
							</div>
						</div>
					</div>
					<div class="add-record-wrap">
						<a href="<?php echo htmlspecialchars(buildCleanRouteUrl('expenses/create'), ENT_QUOTES, 'UTF-8'); ?>" class="btn btn-primary add-record-btn add-btn">
							<i class="bi bi-plus-lg me-1"></i>Add Expense
						</a>
					</div>
				</div>
			</form>

			<div class="table-responsive user-table-wrap">
				<table class="table user-list-table align-middle mb-0">
					<thead>
						<tr>
							<th>Title</th>
							<th>Type</th>
							<th>Category</th>
							<th>Amount</th>
							<th>Department</th>
							<th class="text-end pe-3">Action</th>
						</tr>
					</thead>
					<tbody>
						<?php if (empty($requests)): ?>
							<tr>
								<td colspan="6" class="text-center py-4 text-muted">No expense requests found.</td>
							</tr>
						<?php else: ?>
							<?php foreach ($requests as $index => $row): ?>
								<?php
								$status = strtolower((string) ($row['request_status'] ?? 'pending'));
								$rowClass = $status === 'approved' ? 'row-active' : 'row-inactive';
								$amount = number_format((float) ($row['request_amount'] ?? 0), 2);
								$typeLabel = ucfirst(strtolower((string) ($row['request_type'] ?? 'other')));
								?>
								<tr class="<?php echo $rowClass; ?>">
									<td><?php echo htmlspecialchars((string) ($row['request_title'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></td>
									<td><?php echo htmlspecialchars($typeLabel, ENT_QUOTES, 'UTF-8'); ?></td>
									<td><?php echo htmlspecialchars((string) ($row['request_category'] ?? '-'), ENT_QUOTES, 'UTF-8'); ?></td>
									<td><?php echo htmlspecialchars((string) ($row['request_currency'] ?? 'INR'), ENT_QUOTES, 'UTF-8'); ?> <?php echo $amount; ?></td>
									<td><?php echo htmlspecialchars((string) ($row['department_name'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></td>
									<td class="text-end pe-3">
										<?php if ($canReviewExpenseRequests): ?>
											<a href="<?php echo htmlspecialchars(buildCleanRouteUrl('expenses/review', ['id' => (int) ($row['request_id'] ?? 0)]), ENT_QUOTES, 'UTF-8'); ?>" class="btn btn-sm btn-warning edit-btn">Review</a>
										<?php else: ?>
											<a
												href="<?php echo htmlspecialchars(buildCleanRouteUrl('expenses/review', ['id' => (int) ($row['request_id'] ?? 0)]), ENT_QUOTES, 'UTF-8'); ?>"
												class="btn btn-sm btn-outline-secondary edit-btn action-icon-btn"
												title="View"
												aria-label="View expense request"
											>
												<i class="bi bi-eye" aria-hidden="true"></i>
											</a>
										<?php endif; ?>
									</td>
								</tr>
							<?php endforeach; ?>
						<?php endif; ?>
					</tbody>
				</table>
			</div>

			<nav class="user-pagination-wrap" aria-label="Expense request list pagination">
				<?php
				$totalExpenseCount = isset($totalRecords) ? (int) $totalRecords : count($requests);
				$rangeStart = $totalExpenseCount > 0 ? (($currentPage - 1) * $perPage) + 1 : 0;
				$rangeEnd = $totalExpenseCount > 0 ? min($totalExpenseCount, $rangeStart + count($requests) - 1) : 0;
				?>
				<div class="pagination-meta"><?php echo $rangeStart; ?>&ndash;<?php echo $rangeEnd; ?> of <?php echo $totalExpenseCount; ?></div>
				<ul class="pagination user-pagination mb-0">
					<?php $prevPage = max(1, $currentPage - 1); ?>
					<li class="page-item <?php echo $currentPage <= 1 ? 'disabled' : ''; ?>">
						<a class="page-link" href="<?php echo htmlspecialchars(buildCleanRouteUrl('expenses', $baseQuery + ['page' => $prevPage]), ENT_QUOTES, 'UTF-8'); ?>">Prev</a>
					</li>

					<?php
					$startPage = max(1, $currentPage - 2);
					$endPage = min($totalPages, $currentPage + 2);
					for ($page = $startPage; $page <= $endPage; $page++):
					?>
						<li class="page-item <?php echo $page === $currentPage ? 'active' : ''; ?>">
							<a class="page-link" href="<?php echo htmlspecialchars(buildCleanRouteUrl('expenses', $baseQuery + ['page' => $page]), ENT_QUOTES, 'UTF-8'); ?>"><?php echo $page; ?></a>
						</li>
					<?php endfor; ?>

					<?php $nextPage = min($totalPages, $currentPage + 1); ?>
					<li class="page-item <?php echo $currentPage >= $totalPages ? 'disabled' : ''; ?>">
						<a class="page-link" href="<?php echo htmlspecialchars(buildCleanRouteUrl('expenses', $baseQuery + ['page' => $nextPage]), ENT_QUOTES, 'UTF-8'); ?>">Next</a>
					</li>
				</ul>
			</nav>
		</section>
	</div>
</main>
