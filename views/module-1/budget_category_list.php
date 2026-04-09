<?php
$categories = isset($categories) && is_array($categories) ? $categories : [];
$filters = isset($filters) && is_array($filters) ? $filters : [];
$searchValue = (string) ($filters['search'] ?? '');
$statusValue = (string) ($filters['status'] ?? '');
$typeValue = (string) ($filters['type'] ?? '');
$currentPage = isset($currentPage) ? (int) $currentPage : 1;
$totalPages = isset($totalPages) ? (int) $totalPages : 1;

$baseQuery = [
	'route' => 'budget-categories',
	'search' => $searchValue,
	'status' => $statusValue,
	'type' => $typeValue,
];
?>

<main class="main">
	<div class="page-shell user-list-page">
		<section class="user-list-panel">
			<?php require ROOT_PATH . '/views/templates/flash_message.php'; ?>
			<form class="user-filter-bar search-bar" method="GET" action="">
				<input type="hidden" name="route" value="budget-categories">
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
									<option value="expense" <?php echo $typeValue === 'expense' ? 'selected' : ''; ?>>Expense</option>
									<option value="purchase" <?php echo $typeValue === 'purchase' ? 'selected' : ''; ?>>Purchase</option>
								</select>
							</div>

							<div class="filter-actions">
								<button type="submit" class="btn btn-primary btn-filter">
									<i class="bi bi-search me-1"></i>Search
								</button>
								<a href="?route=budget-categories" class="btn btn-outline-secondary btn-filter">
									<i class="bi bi-arrow-counterclockwise me-1"></i>Reset
								</a>
							</div>
						</div>
					</div>

					<div class="add-record-wrap">
						<a href="?route=budget-categories/create" class="btn btn-primary add-record-btn add-btn">
							<i class="bi bi-plus-lg me-1"></i>Add Budget Category
						</a>
					</div>
				</div>
			</form>

			<div class="table-responsive user-table-wrap">
				<table class="table user-list-table align-middle mb-0">
					<thead>
						<tr>
							<th>Serial No.</th>
							<th>Category Code</th>
							<th>Category Name</th>
							<th>Category Type</th>
							<th>Status</th>
							<th class="text-end">Actions</th>
						</tr>
					</thead>
					<tbody>
						<?php if (count($categories) === 0): ?>
							<tr>
								<td colspan="6" class="text-center py-4 text-muted">No budget categories found.</td>
							</tr>
						<?php else: ?>
							<?php foreach ($categories as $index => $category): ?>
								<?php $isActive = (int) ($category['budget_category_is_active'] ?? 0) === 1; ?>
								<?php $typeLabel = ucfirst(strtolower((string) ($category['budget_category_type'] ?? ''))); ?>
								<?php $serialNumber = (($currentPage - 1) * 10) + $index + 1; ?>
								<tr>
									<td><?php echo $serialNumber; ?></td>
									<td><?php echo htmlspecialchars((string) ($category['budget_category_code'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></td>
									<td><?php echo htmlspecialchars((string) ($category['budget_category_name'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></td>
									<td><?php echo htmlspecialchars($typeLabel, ENT_QUOTES, 'UTF-8'); ?></td>
									<td>
										<span class="status-pill <?php echo $isActive ? 'status-active' : 'status-inactive'; ?>">
											<?php echo $isActive ? 'Active' : 'Inactive'; ?>
										</span>
									</td>
									<td class="text-end">
										<div class="d-inline-flex gap-2 flex-wrap justify-content-end">
											<a href="?route=budget-categories/edit&id=<?php echo (int) ($category['budget_category_id'] ?? 0); ?>" class="btn btn-sm btn-warning edit-btn">Edit</a>
										</div>
									</td>
								</tr>
							<?php endforeach; ?>
						<?php endif; ?>
					</tbody>
				</table>
			</div>

			<?php if ($totalPages > 1): ?>
					<nav class="user-pagination-wrap" aria-label="Budget category list pagination">
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
