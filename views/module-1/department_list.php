<?php
$departments = isset($departments) && is_array($departments) ? $departments : [];
$filters = isset($filters) && is_array($filters) ? $filters : [];
$searchValue = (string) ($filters['search'] ?? '');
$currentPage = isset($currentPage) ? (int) $currentPage : 1;
$totalPages = isset($totalPages) ? (int) $totalPages : 1;
$canManageDepartments = isset($canManageDepartments) ? (bool) $canManageDepartments : false;
$baseQuery = [
	'route' => 'departments',
	'search' => $searchValue,
];
?>

<main class="main">
	<div class="page-shell user-list-page">
		
		<section class="user-list-panel">
			
			<?php require ROOT_PATH . '/views/templates/flash_message.php'; ?>

			
			<form class="user-filter-bar search-bar" method="GET" action="">
				<input type="hidden" name="route" value="departments">
				
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

							
							<div class="filter-actions">
								<button type="submit" class="btn btn-primary btn-filter">
									<i class="bi bi-search me-1"></i>Search
								</button>
								<a href="?route=departments" class="btn btn-outline-secondary btn-filter">
									<i class="bi bi-arrow-counterclockwise me-1"></i>Reset
								</a>
							</div>
						</div>
					</div>

					
					<?php if ($canManageDepartments): ?>
						<div class="add-record-wrap">
							<a href="?route=departments/create" class="btn btn-primary add-record-btn add-btn">
								<i class="bi bi-plus-lg"></i>Add Department
							</a>
						</div>
					<?php endif; ?>
				</div>
			</form>

			
			<div class="table-responsive user-table-wrap">
				<?php if (count($departments) > 0): ?>
					<table class="table table-hover align-middle mb-0 user-list-table">
						
						<thead>
							<tr>
								<th>Serial No.</th>
								<th>Department Code</th>
								<th>Department Name</th>
								<th>Department Head</th>
								<th>Head Email</th>
								<th class="text-end pe-3">Actions</th>
							</tr>
						</thead>

						
						
						<tbody>
							<?php foreach ($departments as $index => $dept): ?>
								<?php $serialNumber = (($currentPage - 1) * 10) + $index + 1; ?>
								<tr>
									<td>
										<?php echo htmlspecialchars((string) $serialNumber, ENT_QUOTES, 'UTF-8'); ?>
									</td>

									<td>
										<?php echo htmlspecialchars((string) ($dept['department_code'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>
									</td>

									<td>
										<?php echo htmlspecialchars((string) ($dept['department_name'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>
									</td>

									
									
									<td>
										<?php 
											$headName = (string) ($dept['head_name'] ?? '');
											echo !empty($headName) ? htmlspecialchars($headName, ENT_QUOTES, 'UTF-8') : '<em style="color: cadetgray;">Not Assigned</em>';
										?>
									</td>

									
									
									<td>
										<?php 
											$headEmail = (string) ($dept['head_email'] ?? '');
											echo !empty($headEmail) ? htmlspecialchars($headEmail, ENT_QUOTES, 'UTF-8') : '<em style="color: cadetgray;">Not Assigned</em>';
										?>
									</td>

									<td class="text-end pe-3">
										
										
										<?php if ($canManageDepartments): ?>
											<a href="?route=departments/edit&id=<?php echo htmlspecialchars((string) ($dept['id'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>" 
											   class="btn btn-sm btn-warning edit-btn">
												<i class="bi bi-pencil-square"></i>Edit
											</a>
										<?php else: ?>
											<span class="text-muted small">View Only</span>
										<?php endif; ?>

										
										
										
									</td>
								</tr>
							<?php endforeach; ?>
						</tbody>
					</table>
				<?php else: ?>
					
					<div class="text-center py-4 text-muted">
						<div class="mb-1">No departments found</div>
						<div>
							<?php if (!empty($searchValue)): ?>
								Try adjusting your search filters or <a href="?route=departments">view all departments</a>.
							<?php else: ?>
								<a href="?route=departments/create">Create a new department</a> to get started.
							<?php endif; ?>
						</div>
					</div>
				<?php endif; ?>
			</div>

			
			<?php if ($totalPages > 1): ?>
				<nav class="user-pagination-wrap" aria-label="Department list pagination">
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
