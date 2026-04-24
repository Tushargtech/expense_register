<?php
$departmentSummary = isset($departmentSummary) && is_array($departmentSummary) ? $departmentSummary : [];
$departmentOptions = isset($departmentOptions) && is_array($departmentOptions) ? $departmentOptions : [];
$selectedDepartmentId = isset($selectedDepartmentId) ? (int) $selectedDepartmentId : 0;
$roleLabel = isset($roleLabel) ? (string) $roleLabel : 'Budget Management';
$scopeNote = isset($scopeNote) ? (string) $scopeNote : '';
$hasSpendData = isset($hasSpendData) ? (bool) $hasSpendData : false;
$totals = isset($totals) && is_array($totals) ? $totals : [];
$isFinanceRole = isset($isFinanceRole) ? (bool) $isFinanceRole : true;
$fiscalYears = isset($fiscalYears) && is_array($fiscalYears) ? $fiscalYears : [];
$selectedFiscalYear = isset($selectedFiscalYear) ? (string) $selectedFiscalYear : '';
$currentPage = isset($currentPage) ? (int) $currentPage : 1;
$totalPages = isset($totalPages) ? (int) $totalPages : 1;
$perPage = isset($perPage) ? (int) $perPage : 10;
$totalSummaryRows = isset($totalSummaryRows) ? (int) $totalSummaryRows : count($departmentSummary);
?>

<main class="main">
	<div class="page-shell dashboard-page budget-monitor-page">
		<section class="page-card mb-3 budget-monitor-hero">
			<div class="card-body p-4 p-md-5">
				<div class="d-flex justify-content-between align-items-start flex-wrap gap-3">
					<div>
						<p class="small text-uppercase opacity-75 mb-1"><?php echo htmlspecialchars($roleLabel, ENT_QUOTES, 'UTF-8'); ?></p>
						<h1 class="h2 mb-2">Budget Monitor</h1>
						<?php if ($scopeNote !== ''): ?>
							<p class="mb-0 opacity-75"><?php echo htmlspecialchars($scopeNote, ENT_QUOTES, 'UTF-8'); ?></p>
						<?php endif; ?>
					</div>
				</div>
			</div>
		</section>

		<?php require ROOT_PATH . '/views/templates/flash_message.php'; ?>

		<div class="card page-card mb-3 budget-filter-card">
			<div class="card-body">
				<form method="GET" action="<?php echo htmlspecialchars(buildCleanRouteUrl('budget-monitor'), ENT_QUOTES, 'UTF-8'); ?>" class="user-filter-bar search-bar">
					<div class="filter-layout">
						<div class="filter-left">
							<div class="filter-grid">
								<?php if ($isFinanceRole): ?>
									<div class="filter-field">
										<select name="department_id" class="form-select">
											<option value="">All Departments</option>
											<?php foreach ($departmentOptions as $department): ?>
												<?php $departmentId = (int) ($department['id'] ?? 0); ?>
												<option value="<?php echo $departmentId; ?>" <?php echo $selectedDepartmentId === $departmentId ? 'selected' : ''; ?>>
													<?php echo htmlspecialchars((string) ($department['name'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>
												</option>
											<?php endforeach; ?>
										</select>
									</div>
								<?php endif; ?>

								<div class="filter-field">
									<select name="fiscal_year" class="form-select">
										<option value="">All Fiscal Years</option>
										<?php foreach ($fiscalYears as $year): ?>
											<option value="<?php echo htmlspecialchars((string) $year, ENT_QUOTES, 'UTF-8'); ?>" <?php echo $selectedFiscalYear === (string) $year ? 'selected' : ''; ?>>
												<?php echo htmlspecialchars((string) $year, ENT_QUOTES, 'UTF-8'); ?>
											</option>
										<?php endforeach; ?>
									</select>
								</div>

								<div class="filter-actions">
									<button type="submit" class="btn btn-primary btn-filter">
										<i class="bi bi-search me-1"></i>Search
									</button>
									<a href="<?php echo htmlspecialchars(buildCleanRouteUrl('budget-monitor'), ENT_QUOTES, 'UTF-8'); ?>" class="btn btn-outline-secondary btn-filter">
										<i class="bi bi-arrow-counterclockwise me-1"></i>Reset
									</a>
								</div>
							</div>
						</div>
					</div>
				</form>
			</div>
		</div>

		<section class="kpi-grid mb-3 budget-kpi-grid">
			<article class="kpi-card kpi-card-allocated">
				<div class="kpi-label">Total Allocated</div>
				<p class="kpi-value"><?php echo 'INR ' . number_format((float) ($totals['allocated'] ?? 0), 2); ?></p>
			</article>
			<article class="kpi-card kpi-card-spent">
				<div class="kpi-label">Total Spent</div>
				<p class="kpi-value"><?php echo $hasSpendData ? 'INR ' . number_format((float) ($totals['spent'] ?? 0), 2) : 'N/A'; ?></p>
			</article>
			<article class="kpi-card kpi-card-remaining">
				<div class="kpi-label">Remaining</div>
				<p class="kpi-value"><?php echo $hasSpendData ? 'INR ' . number_format((float) ($totals['remaining'] ?? 0), 2) : 'N/A'; ?></p>
			</article>
			<article class="kpi-card kpi-card-utilization">
				<div class="kpi-label">Utilization</div>
				<p class="kpi-value"><?php echo $hasSpendData && isset($totals['utilization']) ? $totals['utilization'] . '%' : 'N/A'; ?></p>
			</article>
		</section>

		<div class="card page-card mb-3">
			<div class="card-body">
				<div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
					<h5 class="mb-0">Department Wise Budget</h5>
				</div>
				<div class="table-responsive">
					<table class="table table-hover align-middle mb-0">
						<thead class="table-light">
							<tr>
								<th>Department</th>
								<th>Budget Category</th>
								<th>Fiscal Year</th>
								<th>Fiscal Period</th>
								<th class="text-end">Allocated</th>
								<th class="text-end">Spent</th>
								<th class="text-end">Remaining</th>
								<th class="text-end">Utilization</th>
							</tr>
						</thead>
						<tbody>
							<?php if (empty($departmentSummary)): ?>
								<tr>
									<td colspan="8" class="text-center py-4 text-muted">No department budgets found for the selected scope.</td>
								</tr>
							<?php else: ?>
								<?php foreach ($departmentSummary as $summary): ?>
									<?php
									$allocated = (float) ($summary['allocated'] ?? 0);
									$spent = ($summary['has_spend_data'] ?? false) ? (float) ($summary['spent'] ?? 0) : null;
									$remaining = $spent !== null ? max(0, $allocated - $spent) : null;
									$utilization = ($spent !== null && $allocated > 0) ? round(($spent / $allocated) * 100, 1) : null;
									?>
									<tr>
										<td><?php echo htmlspecialchars((string) ($summary['department'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></td>
										<td><?php echo htmlspecialchars((string) ($summary['budget_category'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></td>
										<td><?php echo htmlspecialchars((string) ($summary['fiscal_year'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></td>
										<td><?php echo htmlspecialchars((string) ($summary['fiscal_period'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></td>
										<td class="text-end"><?php echo 'INR ' . number_format($allocated, 2); ?></td>
										<td class="text-end"><?php echo $spent !== null ? 'INR ' . number_format($spent, 2) : 'N/A'; ?></td>
										<td class="text-end"><?php echo $remaining !== null ? 'INR ' . number_format($remaining, 2) : 'N/A'; ?></td>
										<td class="text-end"><?php echo $utilization !== null ? $utilization . '%' : 'N/A'; ?></td>
									</tr>
								<?php endforeach; ?>
							<?php endif; ?>
						</tbody>
					</table>
				</div>

				<?php
				$baseQuery = [
					'department_id' => $selectedDepartmentId > 0 ? $selectedDepartmentId : '',
					'fiscal_year' => $selectedFiscalYear !== '' ? $selectedFiscalYear : '',
				];
				$rangeStart = $totalSummaryRows > 0 ? (($currentPage - 1) * $perPage) + 1 : 0;
				$rangeEnd = $totalSummaryRows > 0 ? min($totalSummaryRows, $rangeStart + count($departmentSummary) - 1) : 0;
				$downloadQuery = $baseQuery + ['download' => 1];
				?>
				<nav class="user-pagination-wrap" aria-label="Budget monitor pagination" style="margin-top: 12px;">
					<div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-2">
						<div class="pagination-meta"><?php echo $rangeStart; ?>&ndash;<?php echo $rangeEnd; ?> of <?php echo $totalSummaryRows; ?></div>
						<a href="<?php echo htmlspecialchars(buildCleanRouteUrl('budget-monitor', $downloadQuery), ENT_QUOTES, 'UTF-8'); ?>" class="btn btn-filter list-download-btn">
							<i class="bi bi-download me-1"></i>Download Excel
						</a>
					</div>
					<ul class="pagination user-pagination mb-0">
						<?php $prevPage = max(1, $currentPage - 1); ?>
						<li class="page-item <?php echo $currentPage <= 1 ? 'disabled' : ''; ?>">
							<a class="page-link" href="<?php echo htmlspecialchars(buildCleanRouteUrl('budget-monitor', $baseQuery + ['page' => $prevPage]), ENT_QUOTES, 'UTF-8'); ?>">Prev</a>
						</li>

						<?php
						$startPage = max(1, $currentPage - 2);
						$endPage = min($totalPages, $currentPage + 2);
						for ($page = $startPage; $page <= $endPage; $page++):
						?>
							<li class="page-item <?php echo $page === $currentPage ? 'active' : ''; ?>">
								<a class="page-link" href="<?php echo htmlspecialchars(buildCleanRouteUrl('budget-monitor', $baseQuery + ['page' => $page]), ENT_QUOTES, 'UTF-8'); ?>"><?php echo $page; ?></a>
							</li>
						<?php endfor; ?>

						<?php $nextPage = min($totalPages, $currentPage + 1); ?>
						<li class="page-item <?php echo $currentPage >= $totalPages ? 'disabled' : ''; ?>">
							<a class="page-link" href="<?php echo htmlspecialchars(buildCleanRouteUrl('budget-monitor', $baseQuery + ['page' => $nextPage]), ENT_QUOTES, 'UTF-8'); ?>">Next</a>
						</li>
					</ul>
				</nav>
			</div>
		</div>
	</div>
</main>
