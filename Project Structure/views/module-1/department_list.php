<?php
$departments = isset($departments) && is_array($departments) ? $departments : [];
$filters = isset($filters) && is_array($filters) ? $filters : [];
$searchValue = (string) ($filters['search'] ?? '');
$currentPage = isset($currentPage) ? (int) $currentPage : 1;
$totalPages = isset($totalPages) ? (int) $totalPages : 1;
$baseQuery = [
	'route' => 'departments',
	'search' => $searchValue,
];
?>

<main class="main">
	<div class="page-shell department-list-page">
		
		<section class="department-list-panel">
			
			<?php require ROOT_PATH . '/views/templates/flash_message.php'; ?>

			
			<form class="department-filter-bar search-bar" method="GET" action="">
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

					
					<div class="add-department-wrap">
						<a href="?route=departments/create" class="add-department-btn">
							<i class="bi bi-plus-lg"></i>Add Department
						</a>
					</div>
				</div>
			</form>

			
			<div class="department-table-wrapper">
				<?php if (count($departments) > 0): ?>
					<table class="department-table">
						
						<thead>
							<tr>
								<th class="col-serial">Serial No.</th>
								<th class="col-code">Department Code</th>
								<th class="col-name">Department Name</th>
								<th class="col-head">Department Head</th>
								<th class="col-head-email">Head Email</th>
								<th class="col-actions">Actions</th>
							</tr>
						</thead>

						
						
						<tbody>
							<?php foreach ($departments as $index => $dept): ?>
								<?php $serialNumber = (($currentPage - 1) * 10) + $index + 1; ?>
								<tr>
									
									<td class="col-serial">
										<?php echo htmlspecialchars((string) $serialNumber, ENT_QUOTES, 'UTF-8'); ?>
									</td>

									
									<td class="col-code">
										<?php echo htmlspecialchars((string) ($dept['department_code'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>
									</td>

									
									<td class="col-name">
										<?php echo htmlspecialchars((string) ($dept['department_name'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>
									</td>

									
									
									<td class="col-head">
										<?php 
											$headName = (string) ($dept['head_name'] ?? '');
											echo !empty($headName) ? htmlspecialchars($headName, ENT_QUOTES, 'UTF-8') : '<em style="color: cadetgray;">Not Assigned</em>';
										?>
									</td>

									
									
									<td class="col-head-email">
										<?php 
											$headEmail = (string) ($dept['head_email'] ?? '');
											echo !empty($headEmail) ? htmlspecialchars($headEmail, ENT_QUOTES, 'UTF-8') : '<em style="color: cadetgray;">Not Assigned</em>';
										?>
									</td>

									
									<td class="col-actions">
										
										
										<a href="?route=departments/edit&id=<?php echo htmlspecialchars((string) ($dept['id'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>" 
										   class="btn-edit-department">
											<i class="bi bi-pencil-square"></i>Edit
										</a>

										
										
										
									</td>
								</tr>
							<?php endforeach; ?>
						</tbody>
					</table>
				<?php else: ?>
					
					<div class="department-empty-state">
						<div class="department-empty-state-icon">📭</div>
						<div class="department-empty-state-text">No departments found</div>
						<div class="department-empty-state-subtext">
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
				<div class="department-pagination">
					
					<?php if ($currentPage > 1): ?>
						<a href="?<?php echo http_build_query(array_merge($baseQuery, ['page' => $currentPage - 1])); ?>">
							<i class="bi bi-chevron-left me-1"></i>Prev
						</a>
					<?php else: ?>
						<span>
							<i class="bi bi-chevron-left me-1"></i>Prev
						</span>
					<?php endif; ?>

					
					<?php for ($page = 1; $page <= $totalPages; $page++): ?>
						<?php if ($page === $currentPage): ?>
							
							<span class="active">
								<?php echo htmlspecialchars((string) $page, ENT_QUOTES, 'UTF-8'); ?>
							</span>
						<?php else: ?>
							
							<a href="?<?php echo http_build_query(array_merge($baseQuery, ['page' => $page])); ?>">
								<?php echo htmlspecialchars((string) $page, ENT_QUOTES, 'UTF-8'); ?>
							</a>
						<?php endif; ?>
					<?php endfor; ?>

					
					<?php if ($currentPage < $totalPages): ?>
						<a href="?<?php echo http_build_query(array_merge($baseQuery, ['page' => $currentPage + 1])); ?>">
							Next<i class="bi bi-chevron-right ms-1"></i>
						</a>
					<?php else: ?>
						<span>
							Next<i class="bi bi-chevron-right ms-1"></i>
						</span>
					<?php endif; ?>

					
					<span class="pagination-info">
						Page <?php echo htmlspecialchars((string) $currentPage, ENT_QUOTES, 'UTF-8'); ?> of 
						<?php echo htmlspecialchars((string) max(1, $totalPages), ENT_QUOTES, 'UTF-8'); ?>
					</span>
				</div>
			<?php endif; ?>
		</section>
	</div>
</main>
