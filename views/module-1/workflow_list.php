<?php
$workflows = isset($workflows) && is_array($workflows) ? $workflows : [];
$filters = isset($filters) && is_array($filters) ? $filters : [];

$searchValue = (string) ($filters['search'] ?? '');
$currentPage = isset($currentPage) ? (int) $currentPage : 1;
$totalPages = isset($totalPages) ? (int) $totalPages : 1;
$perPage = isset($perPage) ? max(1, (int) $perPage) : 10;

$selectedWorkflowType = (string) ($filters['workflow_type'] ?? '');

$baseQuery = [
	'route' => 'workflows',
	'search' => $searchValue,
	'workflow_type' => $selectedWorkflowType,
];

$workflowTypes = isset($workflowTypes) && is_array($workflowTypes) ? $workflowTypes : [];
$canCreateWorkflow = isset($canCreateWorkflow) ? (bool) $canCreateWorkflow : false;
$canEditWorkflow = isset($canEditWorkflow) ? (bool) $canEditWorkflow : false;

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
			<?php require ROOT_PATH . '/views/templates/flash_message.php'; ?>

			<form class="user-filter-bar search-bar" method="GET" action="">
				<input type="hidden" name="route" value="workflows">
				<div class="filter-layout">
					<div class="filter-left">
						<div class="filter-grid">
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
								<select name="workflow_type" class="form-select">
									<option value="">Workflow Types</option>
									<?php foreach ($workflowTypes as $type): ?>
										<option value="<?php echo htmlspecialchars($type, ENT_QUOTES, 'UTF-8'); ?>" <?php echo $selectedWorkflowType === $type ? 'selected' : ''; ?>>
											<?php echo htmlspecialchars($type, ENT_QUOTES, 'UTF-8'); ?>
										</option>
									<?php endforeach; ?>
								</select>
							</div>

							<div class="filter-actions">
								<button type="submit" class="btn btn-primary btn-filter">Search</button>
								<a href="?route=workflows" class="btn btn-outline-secondary btn-filter">Reset</a>
							</div>
							<?php if ($canCreateWorkflow): ?>
							<div class="add-record-wrap">
						      <a href="?route=workflows/create" class="btn btn-primary add-record-btn add-btn">
							  <i class="bi bi-plus-lg me-1"></i>Add Workflow
						      </a>
					        </div>
							<?php endif; ?>
						</div>
					</div>
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
								$amountRange = formatWorkflowAmountRange($row['workflow_amount_min'] ?? null, $row['workflow_amount_max'] ?? null);
								?>
								<tr>
									<td><?php echo htmlspecialchars((string) ($row['workflow_name'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></td>
									<td><?php echo htmlspecialchars((string) ($row['workflow_type'] ?? '-'), ENT_QUOTES, 'UTF-8'); ?></td>
									<td><?php echo htmlspecialchars($amountRange, ENT_QUOTES, 'UTF-8'); ?></td>
									<td><?php echo htmlspecialchars((string) ($row['approval_flow'] ?? '-'), ENT_QUOTES, 'UTF-8'); ?></td>
									<td class="text-end pe-3">
										<?php if ($canEditWorkflow): ?>
											<a href="?route=workflows/edit&id=<?php echo (int) ($row['workflow_id'] ?? 0); ?>" class="btn btn-sm btn-warning edit-btn" title="Edit Workflow" aria-label="Edit Workflow">
												<i class="bi bi-pencil-square"></i>
											</a>
										<?php else: ?>
											<span class="text-muted">-</span>
										<?php endif; ?>
									</td>
								</tr>
							<?php endforeach; ?>
						<?php endif; ?>
					</tbody>
				</table>
			</div>

			<nav class="user-pagination-wrap" aria-label="Workflow list pagination">
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
		</section>
	</div>
</main>
