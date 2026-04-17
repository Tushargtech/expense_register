<?php

$pageTitle = 'Dashboard - Expense Register';
$pageStyles = [];
require ROOT_PATH . '/views/templates/app_layout.php';
renderAppLayoutStart([
	'pageTitle' => $pageTitle,
	'pageStyles' => $pageStyles,
	'activeMenu' => $activeMenu ?? 'dashboard',
	'includeChrome' => true,
]);

$userName = isset($userName) ? (string) $userName : 'User';
$dashboardKpis = isset($dashboardKpis) && is_array($dashboardKpis) ? $dashboardKpis : [];
$recentActivity = isset($recentActivity) && is_array($recentActivity) ? $recentActivity : [];
$isDepartmentHead = isset($isDepartmentHead) ? (bool) $isDepartmentHead : false;
$departmentBudgetAllocated = isset($departmentBudgetAllocated) ? (float) $departmentBudgetAllocated : 0;
$departmentBudgetRemaining = isset($departmentBudgetRemaining) ? (float) $departmentBudgetRemaining : 0;
$totalRequests = (int) ($dashboardKpis['total_requests'] ?? 0);
$acceptedRequests = (int) ($dashboardKpis['accepted_requests'] ?? 0);
$rejectedRequests = (int) ($dashboardKpis['rejected_requests'] ?? 0);
$totalExpense = (float) ($dashboardKpis['total_expense'] ?? 0);
?>

<main class="main">
	<div class="page-shell budget-monitor-page">
		<section class="page-card mb-3 budget-monitor-hero">
			<div class="card-body p-4 p-md-5">
				<div class="d-flex justify-content-between align-items-start flex-wrap gap-3">
					<div>
						<h1 class="h2 mb-2">Dashboard</h1>
						<p class="mb-0 opacity-75">Welcome, <?php echo htmlspecialchars($userName, ENT_QUOTES, 'UTF-8'); ?>.</p>
					</div>
				</div>
			</div>
		</section>

		<section class="kpi-grid mb-3 budget-kpi-grid">
			<article class="kpi-card kpi-card-allocated">
				<div class="kpi-label">Pending Requests</div>
				<p class="kpi-value"><?php echo number_format($totalRequests); ?></p>
			</article>
			<article class="kpi-card kpi-card-spent">
				<div class="kpi-label">Accepted Requests</div>
				<p class="kpi-value"><?php echo number_format($acceptedRequests); ?></p>
			</article>
			<article class="kpi-card kpi-card-remaining">
				<div class="kpi-label">Rejected Requests</div>
				<p class="kpi-value"><?php echo number_format($rejectedRequests); ?></p>
			</article>
			<article class="kpi-card kpi-card-utilization">
				<div class="kpi-label">Total Expense</div>
				<p class="kpi-value">INR <?php echo number_format($totalExpense, 2); ?></p>
			</article>
			<?php if ($isDepartmentHead && isset($departmentBudgetAllocated)): ?>
				<article class="kpi-card kpi-card-allocated">
					<div class="kpi-label">Department Budget</div>
					<p class="kpi-value">INR <?php echo number_format((float) $departmentBudgetAllocated, 2); ?></p>
				</article>
			<?php endif; ?>
			<?php if ($isDepartmentHead && isset($departmentBudgetRemaining)): ?>
				<article class="kpi-card kpi-card-remaining">
					<div class="kpi-label">Budget Remaining</div>
					<p class="kpi-value">INR <?php echo number_format((float) $departmentBudgetRemaining, 2); ?></p>
				</article>
			<?php endif; ?>
		</section>

		<div class="row g-3 mb-3">
			<div class="col-lg-8">
				<div class="card page-card h-100">
					<div class="card-body">
						<div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
							<h5 class="mb-0">Recent Activity</h5>
							<span class="small-help">Actions performed today in the expenses module.</span>
						</div>
						<div class="table-responsive">
							<table class="table table-hover align-middle mb-0">
								<thead class="table-light">
									<tr>
										<th>Action</th>
										<th class="ps-3">Ref No</th>
										<th>Title</th>
										<th>Amount</th>
										<th>Status</th>
										<th class="ps-3">Time</th>
									</tr>
								</thead>
								<tbody>
									<?php if ($recentActivity === []): ?>
										<tr>
											<td colspan="6" class="text-center py-4 text-muted">No expense activity found for today.</td>
										</tr>
									<?php else: ?>
										<?php foreach ($recentActivity as $activity): ?>
											<?php
											$activityStatus = strtolower(trim((string) ($activity['request_status'] ?? 'pending')));
											$badgeClass = $activityStatus === 'approved'
												? 'badge-approved'
												: ($activityStatus === 'rejected' ? 'badge-rejected' : 'badge-pending');
											$activityType = strtolower(trim((string) ($activity['activity_type'] ?? 'update')));
											$activityLabel = $activityType === 'create' ? 'Created' : ucfirst($activityType);
											$activityAt = (string) ($activity['activity_at'] ?? '');
											?>
											<tr>
												<td><?php echo htmlspecialchars($activityLabel, ENT_QUOTES, 'UTF-8'); ?></td>
												<td class="ps-3"><?php echo htmlspecialchars((string) ($activity['request_reference_no'] ?? '—'), ENT_QUOTES, 'UTF-8'); ?></td>
												<td><?php echo htmlspecialchars((string) ($activity['request_title'] ?? '—'), ENT_QUOTES, 'UTF-8'); ?></td>
												<td><?php echo htmlspecialchars((string) ($activity['request_currency'] ?? 'INR'), ENT_QUOTES, 'UTF-8'); ?> <?php echo number_format((float) ($activity['request_amount'] ?? 0), 2); ?></td>
												<td><span class="status-badge <?php echo $badgeClass; ?>"><?php echo htmlspecialchars(ucfirst($activityStatus), ENT_QUOTES, 'UTF-8'); ?></span></td>
												<td><?php echo $activityAt !== '' ? htmlspecialchars(date('h:i A', strtotime($activityAt)), ENT_QUOTES, 'UTF-8') : '—'; ?></td>
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
						<h5 class="mb-3">Quick Actions</h5>
						<div class="d-grid gap-2 mb-3">
							<a href="<?php echo htmlspecialchars(buildCleanRouteUrl('expenses/create'), ENT_QUOTES, 'UTF-8'); ?>" class="btn btn-primary"><i class="bi bi-plus-circle me-1"></i>New Request</a>
							<a href="<?php echo htmlspecialchars(buildCleanRouteUrl('expenses', ['request_scope' => 'pending_approvals']), ENT_QUOTES, 'UTF-8'); ?>" class="btn btn-outline-primary"><i class="bi bi-list-check me-1"></i>Pending Approvals</a>
						</div>

						<h6 class="mb-2">Quick Links</h6>
						<div class="d-grid gap-2">
							<a href="<?php echo htmlspecialchars(buildAssetUrl('README.md'), ENT_QUOTES, 'UTF-8'); ?>" class="btn btn-outline-secondary text-start" target="_blank" rel="noopener">
								<i class="bi bi-file-earmark-text me-1"></i>Expense Policy
							</a>
							<a href="<?php echo htmlspecialchars(buildAssetUrl('fsd.md'), ENT_QUOTES, 'UTF-8'); ?>" class="btn btn-outline-secondary text-start" target="_blank" rel="noopener">
								<i class="bi bi-journal-text me-1"></i>Purchase Policy
							</a>
							<a href="<?php echo htmlspecialchars(buildCleanRouteUrl('budget-uploader'), ENT_QUOTES, 'UTF-8'); ?>" class="btn btn-outline-secondary text-start">
								<i class="bi bi-info-circle me-1"></i>Budget Request Guidelines
							</a>
						</div>
					</div>
				</div>
			</div>
		</div>
	</div>
</main>

<?php renderAppLayoutEnd(); ?>
