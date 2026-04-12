<?php
$rows = isset($rows) && is_array($rows) ? $rows : [];
$departmentSummary = isset($departmentSummary) && is_array($departmentSummary) ? $departmentSummary : [];
$categorySummary = isset($categorySummary) && is_array($categorySummary) ? $categorySummary : [];
$typeSummary = isset($typeSummary) && is_array($typeSummary) ? $typeSummary : [];
$departmentOptions = isset($departmentOptions) && is_array($departmentOptions) ? $departmentOptions : [];
$categoryOptions = isset($categoryOptions) && is_array($categoryOptions) ? $categoryOptions : [];
$fiscalYears = isset($fiscalYears) && is_array($fiscalYears) ? $fiscalYears : [];
$selectedDepartmentId = isset($selectedDepartmentId) ? (int) $selectedDepartmentId : 0;
$selectedCategoryId = isset($selectedCategoryId) ? (int) $selectedCategoryId : 0;
$selectedDepartmentName = isset($selectedDepartmentName) ? (string) $selectedDepartmentName : '';
$selectedYear = isset($selectedYear) ? (string) $selectedYear : '';
$selectedType = isset($selectedType) ? (string) $selectedType : '';
$roleLabel = isset($roleLabel) ? (string) $roleLabel : 'Budget Management';
$scopeNote = isset($scopeNote) ? (string) $scopeNote : '';
$hasSpendData = isset($hasSpendData) ? (bool) $hasSpendData : false;
$totals = isset($totals) && is_array($totals) ? $totals : [];
$userName = isset($userName) ? (string) $userName : 'User';
$isFinanceRole = isset($isFinanceRole) ? (bool) $isFinanceRole : true;
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
					<div class="text-end"></div>
				</div>
			</div>
		</section>

		<?php require ROOT_PATH . '/views/templates/flash_message.php'; ?>

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

		<div class="card page-card mb-3 budget-filter-card">
			<div class="card-body">
				<form method="GET" action="" class="user-filter-bar search-bar">
					<input type="hidden" name="route" value="budget-monitor">
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
								<?php else: ?>
									<input type="hidden" name="department_id" value="<?php echo $selectedDepartmentId; ?>">
								<?php endif; ?>

								<div class="filter-field">
									<select name="fiscal_year" class="form-select">
										<option value="">All Fiscal Years</option>
										<?php foreach ($fiscalYears as $year): ?>
											<option value="<?php echo htmlspecialchars((string) $year, ENT_QUOTES, 'UTF-8'); ?>" <?php echo $selectedYear === (string) $year ? 'selected' : ''; ?>>
												<?php echo htmlspecialchars((string) $year, ENT_QUOTES, 'UTF-8'); ?>
											</option>
										<?php endforeach; ?>
									</select>
								</div>

								<div class="filter-field">
									<select name="type" class="form-select">
										<option value="">All Types</option>
										<option value="expense" <?php echo $selectedType === 'expense' ? 'selected' : ''; ?>>Expense</option>
										<option value="purchase" <?php echo $selectedType === 'purchase' ? 'selected' : ''; ?>>Purchase</option>
									</select>
								</div>

								<div class="filter-field">
									<select name="category_id" class="form-select">
										<option value="">All Categories</option>
										<?php foreach ($categoryOptions as $category): ?>
											<?php $categoryId = (int) ($category['id'] ?? 0); ?>
											<option value="<?php echo $categoryId; ?>" <?php echo $selectedCategoryId === $categoryId ? 'selected' : ''; ?>>
												<?php echo htmlspecialchars((string) ($category['name'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>
											</option>
										<?php endforeach; ?>
									</select>
								</div>

								<div class="filter-actions">
									<button type="submit" class="btn btn-primary btn-filter">
										<i class="bi bi-search me-1"></i>View
									</button>
									<a href="?route=budget-monitor" class="btn btn-outline-secondary btn-filter">
										<i class="bi bi-arrow-counterclockwise me-1"></i>Reset
									</a>
								</div>
							</div>
						</div>
					</div>
				</form>
			</div>
		</div>

		<div class="row g-3 mb-3">
			<div class="col-lg-8">
				<div class="card page-card h-100">
					<div class="card-body">
						<div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
							<h5 class="mb-0">Department Wise Budget</h5>
						</div>
						<div class="table-responsive">
							<table class="table table-hover align-middle mb-0">
								<thead class="table-light">
									<tr>
										<th>Department</th>
										<th>Budgets</th>
										<th class="text-end">Allocated</th>
										<th class="text-end">Spent</th>
										<th class="text-end">Remaining</th>
										<th class="text-end">Utilization</th>
									</tr>
								</thead>
								<tbody>
									<?php if (empty($departmentSummary)): ?>
										<tr>
											<td colspan="6" class="text-center py-4 text-muted">No department budgets found for the selected scope.</td>
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
												<td><?php echo htmlspecialchars((string) ($summary['label'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></td>
												<td><?php echo (int) ($summary['count'] ?? 0); ?></td>
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
					</div>
				</div>
			</div>

			<div class="col-lg-4">
				<div class="card page-card h-100">
					<div class="card-body">
						<h5 class="mb-3">Budget Types</h5>
						<div class="d-flex flex-column gap-3">
							<?php if (empty($typeSummary)): ?>
								<div class="text-muted">No budget type data found.</div>
							<?php else: ?>
								<?php foreach ($typeSummary as $item): ?>
									<div class="border rounded-3 p-3 bg-light">
										<div class="d-flex justify-content-between align-items-center gap-2">
											<strong><?php echo htmlspecialchars((string) ($item['label'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></strong>
											<span class="badge text-bg-primary"><?php echo (int) ($item['count'] ?? 0); ?> items</span>
										</div>
										<div class="small-help mt-2">Allocated: <?php echo 'INR ' . number_format((float) ($item['allocated'] ?? 0), 2); ?></div>
										<div class="small-help">Spent: <?php echo ($item['has_spend_data'] ?? false) ? 'INR ' . number_format((float) ($item['spent'] ?? 0), 2) : 'N/A'; ?></div>
									</div>
								<?php endforeach; ?>
							<?php endif; ?>
						</div>
					</div>
				</div>
			</div>
		</div>

		<div class="card page-card mb-3">
			<div class="card-body">
				<div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
					<h5 class="mb-0">Category Wise Budget</h5>
				</div>
				<div class="table-responsive">
					<table class="table table-hover align-middle mb-0">
						<thead class="table-light">
							<tr>
								<th>Category</th>
								<th>Type</th>
								<th class="text-end">Count</th>
								<th class="text-end">Allocated</th>
								<th class="text-end">Spent</th>
								<th class="text-end">Remaining</th>
								<th class="text-end">Utilization</th>
							</tr>
						</thead>
						<tbody>
							<?php if (empty($categorySummary)): ?>
								<tr>
									<td colspan="7" class="text-center py-4 text-muted">No budget category data found for the selected scope.</td>
								</tr>
							<?php else: ?>
								<?php foreach ($categorySummary as $summary): ?>
									<?php
									$allocated = (float) ($summary['allocated'] ?? 0);
									$spent = ($summary['has_spend_data'] ?? false) ? (float) ($summary['spent'] ?? 0) : null;
									$remaining = $spent !== null ? max(0, $allocated - $spent) : null;
									$utilization = ($spent !== null && $allocated > 0) ? round(($spent / $allocated) * 100, 1) : null;
									?>
									<tr>
										<td><?php echo htmlspecialchars((string) ($summary['label'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></td>
										<td><?php echo htmlspecialchars((string) (($summary['type'] ?? '') !== '' ? $summary['type'] : 'General'), ENT_QUOTES, 'UTF-8'); ?></td>
										<td class="text-end"><?php echo (int) ($summary['count'] ?? 0); ?></td>
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
			</div>
		</div>

		<div class="card page-card">
			<div class="card-body">
				<div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
					<h5 class="mb-0">Detailed Budget View</h5>
				</div>
				<div class="table-responsive">
					<table class="table table-hover align-middle mb-0">
						<thead class="table-light">
							<tr>
								<th>Department</th>
								<th>Fiscal Year</th>
								<th>Period</th>
								<th>Category</th>
								<th>Type</th>
								<th class="text-end">Allocated</th>
								<th class="text-end">Spent</th>
								<th class="text-end">Remaining</th>
								<th class="text-end">Utilization</th>
							</tr>
						</thead>
						<tbody>
							<?php if (empty($rows)): ?>
								<tr>
									<td colspan="9" class="text-center py-4 text-muted">No budget rows found for the selected scope.</td>
								</tr>
							<?php else: ?>
								<?php foreach ($rows as $row): ?>
									<?php
									$allocated = (float) ($row['budget_allocated_amount'] ?? 0);
									$spent = ($row['budget_spent_amount'] ?? null) !== null && $row['budget_spent_amount'] !== '' ? (float) $row['budget_spent_amount'] : null;
									$remaining = $spent !== null ? max(0, $allocated - $spent) : null;
									$utilization = ($spent !== null && $allocated > 0) ? round(($spent / $allocated) * 100, 1) : null;
									$typeLabel = trim((string) ($row['budget_category_type'] ?? ''));
									if ($typeLabel === '') {
										$typeLabel = 'General';
									}
									?>
									<tr>
										<td><?php echo htmlspecialchars((string) ($row['department_name'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></td>
										<td><?php echo htmlspecialchars((string) ($row['budget_fiscal_year'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></td>
										<td><?php echo htmlspecialchars((string) ($row['budget_fiscal_period'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></td>
										<td><?php echo htmlspecialchars((string) ($row['budget_category_name'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></td>
										<td><?php echo htmlspecialchars($typeLabel, ENT_QUOTES, 'UTF-8'); ?></td>
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
			</div>
		</div>
	</div>
</main>
