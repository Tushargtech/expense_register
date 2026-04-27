<?php
$workflows = isset($workflows) && is_array($workflows) ? $workflows : [];
$filters = isset($filters) && is_array($filters) ? $filters : [];

$searchValue = (string) ($filters['search'] ?? '');
$selectedStatus = (string) ($filters['status'] ?? '');
$currentPage = isset($currentPage) ? (int) $currentPage : 1;
$totalPages = isset($totalPages) ? (int) $totalPages : 1;
$perPage = 10;

$selectedWorkflowType = (string) ($filters['workflow_type'] ?? '');

$baseQuery = [
	'search' => $searchValue,
	'status' => $selectedStatus,
	'workflow_type' => $selectedWorkflowType,
];

$workflowTypes = isset($workflowTypes) && is_array($workflowTypes) ? $workflowTypes : [];
$canCreateWorkflow = isset($canCreateWorkflow) ? (bool) $canCreateWorkflow : false;
$canEditWorkflow = isset($canEditWorkflow) ? (bool) $canEditWorkflow : false;

$workflowTypeLabels = [
	'expense' => 'Expense',
	'purchase' => 'Purchase',
];

function formatWorkflowAmountRange($minAmount, $maxAmount): string
{
	$hasMin = $minAmount !== null && $minAmount !== '';
	$hasMax = $maxAmount !== null && $maxAmount !== '';

	if (!$hasMin && !$hasMax) {
		return '-';
	}

	if ($hasMin && $hasMax) {
		return number_format((float) $minAmount, 2) . ' - ' . number_format((float) $maxAmount, 2);
	}

	if ($hasMin) {
		return '>= ' . number_format((float) $minAmount, 2);
	}

	return '<= ' . number_format((float) $maxAmount, 2);
}
?>

<main class="main">
	<div class="page-shell user-list-page">
		<section class="user-list-panel">
			<div class="list-page-header">
				<h2 class="list-page-title">Workflows</h2>
			</div>
			<?php require ROOT_PATH . '/views/templates/flash_message.php'; ?>
<form class="user-filter-bar search-bar" method="GET" action="<?php echo htmlspecialchars(buildCleanRouteUrl('workflows'), ENT_QUOTES, 'UTF-8'); ?>">
	<div class="filter-layout">

		<div class="filter-left">

			<div class="filter-field search-field">
				<input
					type="text"
					name="search"
					class="form-control"
					placeholder="Search by workflow name or type"
					value="<?php echo htmlspecialchars($searchValue, ENT_QUOTES, 'UTF-8'); ?>"
				>
			</div>

			<div class="filter-field">
				<select name="status" class="form-select">
					<option value="">All Status</option>
					<option value="1" <?php echo $selectedStatus === '1' ? 'selected' : ''; ?>>Active</option>
					<option value="0" <?php echo $selectedStatus === '0' ? 'selected' : ''; ?>>Inactive</option>
				</select>
			</div>

			<div class="filter-field">
				<select name="workflow_type" class="form-select">
					<option value="">Workflow Types</option>
					<?php foreach ($workflowTypes as $type): ?>
						<option value="<?php echo htmlspecialchars($type, ENT_QUOTES, 'UTF-8'); ?>" <?php echo $selectedWorkflowType === $type ? 'selected' : ''; ?>>
							<?php echo htmlspecialchars($workflowTypeLabels[strtolower($type)] ?? $type, ENT_QUOTES, 'UTF-8'); ?>
						</option>
					<?php endforeach; ?>
				</select>
			</div>

			<div class="filter-actions">
				<button type="submit" class="btn btn-primary btn-filter">Search</button>
				<a href="<?php echo htmlspecialchars(buildCleanRouteUrl('workflows'), ENT_QUOTES, 'UTF-8'); ?>" class="btn btn-outline-secondary btn-filter">Reset</a>
			</div>

		</div>

		<?php if ($canCreateWorkflow): ?>
			<div class="add-record-wrap">
				<a href="<?php echo htmlspecialchars(buildCleanRouteUrl('workflows/create'), ENT_QUOTES, 'UTF-8'); ?>" class="btn btn-primary add-record-btn add-btn">
					<i class="bi bi-plus-lg me-1"></i>Add Workflow
				</a>
			</div>
		<?php endif; ?>

	</div>
</form>

			<div class="table-responsive user-table-wrap">
				<table class="table user-list-table align-middle mb-0">
					<thead>
						<tr>
							<th>Workflow Name</th>
							<th>Workflow Type</th>
							<th>Amount Range</th>
							<th>Approval Flow</th>
							<th class="text-end pe-3">Action</th>
						</tr>
					</thead>
					<tbody>
						<?php if (empty($workflows)): ?>
							<tr>
								<td colspan="5" class="text-center py-4 text-muted">No workflows found for the selected filters.</td>
							</tr>
						<?php else: ?>
							<?php foreach ($workflows as $index => $row): ?>
								<?php
								$isActive = (int) ($row['workflow_is_active'] ?? 0) === 1;
								$rowClass = $isActive ? 'row-active' : 'row-inactive';
								$amountRange = formatWorkflowAmountRange($row['workflow_amount_min'] ?? null, $row['workflow_amount_max'] ?? null);
								?>
								<tr class="<?php echo $rowClass; ?>">
									<td><?php echo htmlspecialchars((string) ($row['workflow_name'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></td>
									<td><?php echo htmlspecialchars($workflowTypeLabels[strtolower((string) ($row['workflow_type'] ?? ''))] ?? (string) ($row['workflow_type'] ?? '-'), ENT_QUOTES, 'UTF-8'); ?></td>
									<td><?php echo htmlspecialchars($amountRange, ENT_QUOTES, 'UTF-8'); ?></td>
									<td><?php echo htmlspecialchars((string) ($row['approval_flow'] ?? '-'), ENT_QUOTES, 'UTF-8'); ?></td>
									<td class="text-end pe-3">
										<?php if ($canEditWorkflow): ?>
											<a
												href="<?php echo htmlspecialchars(buildCleanRouteUrl('workflows/edit', ['id' => (int) ($row['workflow_id'] ?? 0)]), ENT_QUOTES, 'UTF-8'); ?>"
												class="btn btn-sm btn-warning edit-btn action-icon-btn"
												title="Edit"
												aria-label="Edit workflow"
											>
												<i class="bi bi-pencil-square" aria-hidden="true"></i>
											</a>
										<?php else: ?>
											<a
												href="<?php echo htmlspecialchars(buildCleanRouteUrl('workflows/edit', ['id' => (int) ($row['workflow_id'] ?? 0)]), ENT_QUOTES, 'UTF-8'); ?>"
												class="btn btn-sm btn-outline-secondary edit-btn action-icon-btn"
												title="View"
												aria-label="View workflow"
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

			<nav class="user-pagination-wrap" aria-label="Workflow list pagination">
				<?php
				$totalWorkflowCount = isset($totalWorkflows) ? (int) $totalWorkflows : count($workflows);
				$rangeStart = $totalWorkflowCount > 0 ? (($currentPage - 1) * $perPage) + 1 : 0;
				$rangeEnd = $totalWorkflowCount > 0 ? min($totalWorkflowCount, $rangeStart + count($workflows) - 1) : 0;
				$downloadQuery = $baseQuery + ['download' => 1];
				?>
				<div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-2">
					<div class="pagination-meta"><?php echo $rangeStart; ?>&ndash;<?php echo $rangeEnd; ?> of <?php echo $totalWorkflowCount; ?></div>
					<a href="<?php echo htmlspecialchars(buildCleanRouteUrl('workflows', $downloadQuery), ENT_QUOTES, 'UTF-8'); ?>" class="btn btn-filter list-download-btn" title="Download Excel">
						<i class="bi bi-download"></i>
					</a>
				</div>
				<ul class="pagination user-pagination mb-0">
					<?php $prevPage = max(1, $currentPage - 1); ?>
					<li class="page-item <?php echo $currentPage <= 1 ? 'disabled' : ''; ?>">
						<a class="page-link" href="<?php echo htmlspecialchars(buildCleanRouteUrl('workflows', $baseQuery + ['page' => $prevPage]), ENT_QUOTES, 'UTF-8'); ?>">Prev</a>
					</li>

					<?php
					$startPage = max(1, $currentPage - 2);
					$endPage = min($totalPages, $currentPage + 2);
					for ($page = $startPage; $page <= $endPage; $page++):
					?>
						<li class="page-item <?php echo $page === $currentPage ? 'active' : ''; ?>">
							<a class="page-link" href="<?php echo htmlspecialchars(buildCleanRouteUrl('workflows', $baseQuery + ['page' => $page]), ENT_QUOTES, 'UTF-8'); ?>"><?php echo $page; ?></a>
						</li>
					<?php endfor; ?>

					<?php $nextPage = min($totalPages, $currentPage + 1); ?>
					<li class="page-item <?php echo $currentPage >= $totalPages ? 'disabled' : ''; ?>">
						<a class="page-link" href="<?php echo htmlspecialchars(buildCleanRouteUrl('workflows', $baseQuery + ['page' => $nextPage]), ENT_QUOTES, 'UTF-8'); ?>">Next</a>
					</li>
				</ul>
			</nav>
		</section>
	</div>
</main>