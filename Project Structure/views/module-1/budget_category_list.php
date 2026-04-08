<?php
$categories = isset($categories) && is_array($categories) ? $categories : [];
$filters = isset($filters) && is_array($filters) ? $filters : [];
$searchValue = (string) ($filters['search'] ?? '');
$statusValue = (string) ($filters['status'] ?? '');
$currentPage = isset($currentPage) ? (int) $currentPage : 1;
$totalPages = isset($totalPages) ? (int) $totalPages : 1;

$baseQuery = [
	'route' => 'budget-categories',
	'search' => $searchValue,
	'status' => $statusValue,
];
?>

<main class="main">
	<div class="page-shell budget-category-list-page">
		<section class="budget-category-list-panel">
			<?php require ROOT_PATH . '/views/templates/flash_message.php'; ?>
			<form class="budget-category-filter-bar" method="GET" action="">
				<input type="hidden" name="route" value="budget-categories">
				<input type="hidden" name="status" value="<?php echo htmlspecialchars($statusValue, ENT_QUOTES, 'UTF-8'); ?>">
				<div class="budget-category-filter-layout">
					<div class="budget-category-filter-left">
						<input
							type="text"
							name="search"
							class="form-control budget-category-filter-input"
							placeholder="Search by code or name..."
							value="<?php echo htmlspecialchars($searchValue, ENT_QUOTES, 'UTF-8'); ?>"
						>
					</div>
					<div class="budget-category-filter-controls">
						<button type="submit" class="btn btn-primary btn-filter">Search</button>
						<a href="?route=budget-categories" class="btn btn-outline-secondary btn-filter">Reset</a>
						<a href="?route=budget-categories" class="btn btn-primary btn-filter btn-add-category">+ Add Budget Category</a>
					</div>
				</div>
			</form>

			<div class="budget-category-table-wrapper">
				<table class="table budget-category-table align-middle mb-0">
					<thead>
						<tr>
							<th>Serial No.</th>
							<th>Category Code</th>
							<th>Category Name</th>
							<th>Status</th>
							<th class="text-end">Actions</th>
						</tr>
					</thead>
					<tbody>
						<?php if (count($categories) === 0): ?>
							<tr>
								<td colspan="5" class="text-center py-4 text-muted">No budget categories found.</td>
							</tr>
						<?php else: ?>
							<?php foreach ($categories as $index => $category): ?>
								<?php $isActive = (int) ($category['budget_category_is_active'] ?? 0) === 1; ?>
								<?php $serialNumber = (($currentPage - 1) * 10) + $index + 1; ?>
								<tr>
									<td><?php echo $serialNumber; ?></td>
									<td><?php echo htmlspecialchars((string) ($category['budget_category_code'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></td>
									<td><?php echo htmlspecialchars((string) ($category['budget_category_name'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></td>
									<td>
										<span class="budget-category-status <?php echo $isActive ? 'status-active' : 'status-inactive'; ?>">
											<?php echo $isActive ? 'Active' : 'Inactive'; ?>
										</span>
									</td>
									<td class="text-end">
										<div class="budget-category-actions">
											<button type="button" class="btn-action btn-action-edit">Edit</button>
											<button type="button" class="btn-action btn-action-delete">Delete</button>
										</div>
									</td>
								</tr>
							<?php endforeach; ?>
						<?php endif; ?>
					</tbody>
				</table>
			</div>

			<?php if ($totalPages > 1): ?>
				<div class="budget-category-pagination">
					<?php if ($currentPage > 1): ?>
						<a href="?<?php echo http_build_query(array_merge($baseQuery, ['page' => $currentPage - 1])); ?>">Prev</a>
					<?php else: ?>
						<span>Prev</span>
					<?php endif; ?>

					<?php for ($page = 1; $page <= $totalPages; $page++): ?>
						<?php if ($page === $currentPage): ?>
							<span class="active"><?php echo htmlspecialchars((string) $page, ENT_QUOTES, 'UTF-8'); ?></span>
						<?php else: ?>
							<a href="?<?php echo http_build_query(array_merge($baseQuery, ['page' => $page])); ?>"><?php echo htmlspecialchars((string) $page, ENT_QUOTES, 'UTF-8'); ?></a>
						<?php endif; ?>
					<?php endfor; ?>

					<?php if ($currentPage < $totalPages): ?>
						<a href="?<?php echo http_build_query(array_merge($baseQuery, ['page' => $currentPage + 1])); ?>">Next</a>
					<?php else: ?>
						<span>Next</span>
					<?php endif; ?>
				</div>
			<?php endif; ?>
		</section>
	</div>
</main>
