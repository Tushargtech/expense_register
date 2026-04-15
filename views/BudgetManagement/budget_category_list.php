<?php
$categories = isset($categories) && is_array($categories) ? $categories : [];
$filters = isset($filters) && is_array($filters) ? $filters : [];
$searchValue = (string) ($filters['search'] ?? '');
$statusValue = (string) ($filters['status'] ?? '');
$typeValue = (string) ($filters['type'] ?? '');
$currentPage = isset($currentPage) ? (int) $currentPage : 1;
$totalPages = isset($totalPages) ? (int) $totalPages : 1;
$perPage = 10;
$canManageBudgetCategories = isset($canManageBudgetCategories) ? (bool) $canManageBudgetCategories : false;
$categoryTypeOptions = isset($categoryTypeOptions) && is_array($categoryTypeOptions) ? $categoryTypeOptions : [];

if ($categoryTypeOptions === [] && $typeValue !== '') {
	$categoryTypeOptions = [strtolower($typeValue)];
}

$baseQuery = [
	'search' => $searchValue,
	'status' => $statusValue,
	'type' => $typeValue,
];
?>

<main class="main">
	<div class="page-shell user-list-page">
		<section class="user-list-panel">
			<div class="list-page-header">
				<h2 class="list-page-title">Budget Categories List</h2>
			</div>
			<?php require ROOT_PATH . '/views/templates/flash_message.php'; ?>
			<form class="user-filter-bar search-bar" method="GET" action="<?php echo htmlspecialchars(buildCleanRouteUrl('budget-categories'), ENT_QUOTES, 'UTF-8'); ?>">
				<input type="hidden" name="status" value="<?php echo htmlspecialchars($statusValue, ENT_QUOTES, 'UTF-8'); ?>">
				<div class="filter-layout">
					<div class="filter-left">
						<div class="filter-grid">
							<div class="filter-field search-field">
								<input
									type="text"
									name="search"
									class="form-control"
									placeholder="Search by code or name..."
									value="<?php echo htmlspecialchars($searchValue, ENT_QUOTES, 'UTF-8'); ?>"
								>
							</div>

							<div class="filter-field">
								<select name="type" class="form-select">
									<option value="">All Types</option>
									<?php foreach ($categoryTypeOptions as $categoryType): ?>
										<?php $normalizedCategoryType = strtolower(trim((string) $categoryType)); ?>
										<?php if ($normalizedCategoryType === '') { continue; } ?>
										<option value="<?php echo htmlspecialchars($normalizedCategoryType, ENT_QUOTES, 'UTF-8'); ?>" <?php echo $typeValue === $normalizedCategoryType ? 'selected' : ''; ?>>
											<?php echo htmlspecialchars(ucfirst($normalizedCategoryType), ENT_QUOTES, 'UTF-8'); ?>
										</option>
									<?php endforeach; ?>
								</select>
							</div>

							<div class="filter-actions">
								<button type="submit" class="btn btn-primary btn-filter">
									<i class="bi bi-search me-1"></i>Search
								</button>
								<a href="<?php echo htmlspecialchars(buildCleanRouteUrl('budget-categories'), ENT_QUOTES, 'UTF-8'); ?>" class="btn btn-outline-secondary btn-filter">
									<i class="bi bi-arrow-counterclockwise me-1"></i>Reset
								</a>
							</div>
						</div>
					</div>

					<?php if ($canManageBudgetCategories): ?>
						<div class="add-record-wrap">
							<a href="<?php echo htmlspecialchars(buildCleanRouteUrl('budget-categories/create'), ENT_QUOTES, 'UTF-8'); ?>" class="btn btn-primary add-record-btn add-btn">
								<i class="bi bi-plus-lg me-1"></i>Add Budget Category
							</a>
						</div>
					<?php endif; ?>
				</div>
			</form>

			<div class="table-responsive user-table-wrap">
				<table class="table user-list-table align-middle mb-0">
					<thead>
						<tr>
							<th>Category Code</th>
							<th>Category Name</th>
							<th>Category Type</th>
							<th class="text-end">Actions</th>
						</tr>
					</thead>
					<tbody>
						<?php if (count($categories) === 0): ?>
							<tr>
								<td colspan="4" class="text-center py-4 text-muted">No budget categories found.</td>
							</tr>
						<?php else: ?>
							<?php foreach ($categories as $index => $category): ?>
								<?php $isActive = (int) ($category['budget_category_is_active'] ?? 0) === 1; ?>
								<?php $typeLabel = ucfirst(strtolower((string) ($category['budget_category_type'] ?? ''))); ?>
								<?php $rowClass = $isActive ? 'row-active' : 'row-inactive'; ?>
								<tr class="<?php echo $rowClass; ?>">
									<td><?php echo htmlspecialchars((string) ($category['budget_category_code'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></td>
									<td><?php echo htmlspecialchars((string) ($category['budget_category_name'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></td>
									<td><?php echo htmlspecialchars($typeLabel, ENT_QUOTES, 'UTF-8'); ?></td>
									<td class="text-end">
										<div class="d-inline-flex gap-2 flex-wrap justify-content-end">
											<?php if ($canManageBudgetCategories): ?>
												<a
													href="<?php echo htmlspecialchars(buildCleanRouteUrl('budget-categories/edit', ['id' => (int) ($category['budget_category_id'] ?? 0)]), ENT_QUOTES, 'UTF-8'); ?>"
													class="btn btn-sm btn-warning edit-btn action-icon-btn"
													title="Edit"
													aria-label="Edit budget category"
												>
													<i class="bi bi-pencil-square" aria-hidden="true"></i>
												</a>
											<?php else: ?>
												<span class="text-muted small">View Only</span>
											<?php endif; ?>
										</div>
									</td>
								</tr>
							<?php endforeach; ?>
						<?php endif; ?>
					</tbody>
				</table>
			</div>

			<nav class="user-pagination-wrap" aria-label="Budget category list pagination">
				<?php
				$totalCategoryCount = isset($totalCategories) ? (int) $totalCategories : count($categories);
				$rangeStart = $totalCategoryCount > 0 ? (($currentPage - 1) * $perPage) + 1 : 0;
				$rangeEnd = $totalCategoryCount > 0 ? min($totalCategoryCount, $rangeStart + count($categories) - 1) : 0;
				?>
				<div class="pagination-meta"><?php echo $rangeStart; ?>&ndash;<?php echo $rangeEnd; ?> of <?php echo $totalCategoryCount; ?></div>
				<ul class="pagination user-pagination mb-0">
					<?php $prevPage = max(1, $currentPage - 1); ?>
					<li class="page-item <?php echo $currentPage <= 1 ? 'disabled' : ''; ?>">
						<a class="page-link" href="<?php echo htmlspecialchars(buildCleanRouteUrl('budget-categories', $baseQuery + ['page' => $prevPage]), ENT_QUOTES, 'UTF-8'); ?>">Prev</a>
					</li>

					<?php
					$startPage = max(1, $currentPage - 2);
					$endPage = min($totalPages, $currentPage + 2);
					for ($page = $startPage; $page <= $endPage; $page++):
					?>
						<li class="page-item <?php echo $page === $currentPage ? 'active' : ''; ?>">
							<a class="page-link" href="<?php echo htmlspecialchars(buildCleanRouteUrl('budget-categories', $baseQuery + ['page' => $page]), ENT_QUOTES, 'UTF-8'); ?>"><?php echo $page; ?></a>
						</li>
					<?php endfor; ?>

					<?php $nextPage = min($totalPages, $currentPage + 1); ?>
					<li class="page-item <?php echo $currentPage >= $totalPages ? 'disabled' : ''; ?>">
						<a class="page-link" href="<?php echo htmlspecialchars(buildCleanRouteUrl('budget-categories', $baseQuery + ['page' => $nextPage]), ENT_QUOTES, 'UTF-8'); ?>">Next</a>
					</li>
				</ul>
			</nav>
		</section>
	</div>
</main>
