<?php
$users = isset($users) && is_array($users) ? $users : [];
$filters = isset($filters) && is_array($filters) ? $filters : [];
$roleOptions = isset($roleOptions) && is_array($roleOptions) ? $roleOptions : [];
$departmentOptions = isset($departmentOptions) && is_array($departmentOptions) ? $departmentOptions : [];

$searchValue = (string) ($filters['search'] ?? '');
$selectedRole = (string) ($filters['role'] ?? '');
$selectedDepartment = (string) ($filters['department'] ?? '');
$selectedStatus = (string) ($filters['status'] ?? '');
$currentPage = isset($currentPage) ? (int) $currentPage : 1;
$totalPages = isset($totalPages) ? (int) $totalPages : 1;
$baseQuery = [
	'route' => 'users',
	'search' => $searchValue,
	'role' => $selectedRole,
	'department' => $selectedDepartment,
	'status' => $selectedStatus,
];
?>

<main class="main">
	<div class="page-shell user-list-page">
		<section class="user-list-panel">
			<?php require ROOT_PATH . '/views/templates/flash_message.php'; ?>

			<form class="user-filter-bar search-bar" method="GET" action="">
				<input type="hidden" name="route" value="users">
				<div class="filter-layout">
					<div class="filter-left">
						<div class="filter-grid">
							<div class="filter-field search-field">
								<input
									type="text"
									name="search"
									class="form-control"
									placeholder="Search by id, name or email"
									value="<?php echo htmlspecialchars($searchValue, ENT_QUOTES, 'UTF-8'); ?>"
								>
							</div>

							<div class="filter-field">
								<select name="role" class="form-select">
									<option value="">All Roles</option>
									<?php foreach ($roleOptions as $role): ?>
										<option value="<?php echo htmlspecialchars((string) $role, ENT_QUOTES, 'UTF-8'); ?>" <?php echo $selectedRole === (string) $role ? 'selected' : ''; ?>>
											<?php echo htmlspecialchars((string) $role, ENT_QUOTES, 'UTF-8'); ?>
										</option>
									<?php endforeach; ?>
								</select>
							</div>

							<div class="filter-field">
								<select name="department" class="form-select">
									<option value="">All Departments</option>
									<?php foreach ($departmentOptions as $department): ?>
										<option value="<?php echo htmlspecialchars((string) $department, ENT_QUOTES, 'UTF-8'); ?>" <?php echo $selectedDepartment === (string) $department ? 'selected' : ''; ?>>
											<?php echo htmlspecialchars((string) $department, ENT_QUOTES, 'UTF-8'); ?>
										</option>
									<?php endforeach; ?>
								</select>
							</div>

							<div class="filter-field">
								<select name="status" class="form-select">
									<option value="">All Status</option>
									<option value="1" <?php echo $selectedStatus === '1' ? 'selected' : ''; ?>>Active</option>
									<option value="0" <?php echo $selectedStatus === '0' ? 'selected' : ''; ?>>Inactive</option>
								</select>
							</div>

							<div class="filter-actions">
								<button type="submit" class="btn btn-primary btn-filter">Search</button>
								<a href="?route=users" class="btn btn-outline-secondary btn-filter">Reset</a>
							</div>
						</div>
					</div>
					<div class="add-employee-wrap">
						<a href="?route=users/create" class="btn btn-primary add-employee-btn add-btn">
							<i class="bi bi-plus-lg me-1"></i>Add Employee
						</a>
					</div>
				</div>
			</form>

			<div class="table-responsive user-table-wrap">
				<table class="table user-list-table align-middle mb-0">
					<thead>
						<tr>
							<th>Serial No.</th>
							<th>User Name</th>
							<th>Email</th>
							<th>Role</th>
							<th>Department Name</th>
							<th>Manager ID</th>
							<th>Manager Name</th>
							<th>Status</th>
							<th class="text-end pe-3">Action</th>
						</tr>
					</thead>
					<tbody>
						<?php if (empty($users)): ?>
							<tr>
								<td colspan="10" class="text-center py-4 text-muted">No users found for the selected filters.</td>
							</tr>
						<?php else: ?>
							<?php foreach ($users as $index => $row): ?>
								<?php
								$isActive = (int) ($row['user_is_active'] ?? 0) === 1;
								$statusClass = $isActive ? 'status-active' : 'status-inactive';
								$statusLabel = $isActive ? 'Active' : 'Inactive';
								$serialNumber = (($currentPage - 1) * 10) + $index + 1;
								?>
								<tr>
									<td><?php echo $serialNumber; ?></td>
									<td><?php echo htmlspecialchars((string) ($row['user_name'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></td>
									<td><?php echo htmlspecialchars((string) ($row['user_email'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></td>
									<td><?php echo htmlspecialchars((string) ($row['user_role'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></td>
									<td><?php echo htmlspecialchars((string) ($row['dept_name'] ?? '-'), ENT_QUOTES, 'UTF-8'); ?></td>
									<td><?php echo (int) ($row['manager_id'] ?? 0); ?></td>
									<td><?php echo htmlspecialchars((string) ($row['manager_name'] ?? '-'), ENT_QUOTES, 'UTF-8'); ?></td>
									<td><span class="status-pill <?php echo $statusClass; ?>"><?php echo $statusLabel; ?></span></td>
									<td class="text-end pe-3">
										<a href="?route=users/edit&id=<?php echo (int) ($row['user_id'] ?? 0); ?>" class="btn btn-sm btn-warning edit-btn">Edit</a>
									</td>
								</tr>
							<?php endforeach; ?>
						<?php endif; ?>
					</tbody>
				</table>
			</div>

			<nav class="user-pagination-wrap" aria-label="User list pagination">
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
