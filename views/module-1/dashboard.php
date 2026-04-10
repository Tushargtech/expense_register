<?php

$pageTitle = 'Dashboard - Expense Register';
$pageStyles = ['assets/css/dashboard.css'];
require ROOT_PATH . '/views/templates/header.php';
require ROOT_PATH . '/views/templates/navbar.php';
require ROOT_PATH . '/views/templates/sidebar.php';

$userName = isset($userName) ? (string) $userName : 'User';
?>

<main class="main">
	<div class="page-shell dashboard-page">
		<div class="top-bar d-flex justify-content-between align-items-center flex-wrap gap-3">
			<div>
				<h2 class="mb-1">Dashboard</h2>
				<p class="small-help mb-0">Welcome, <?php echo htmlspecialchars($userName, ENT_QUOTES, 'UTF-8'); ?>.</p>
			</div>
		</div>

		<section class="kpi-grid">
			<article class="kpi-card">
				<div class="kpi-label">Total Requests</div>
				<p class="kpi-value">24</p>
			</article>
			<article class="kpi-card">
				<div class="kpi-label">Total Amount</div>
				<p class="kpi-value">INR 2,53,500.00</p>
			</article>
			<article class="kpi-card">
				<div class="kpi-label">Approved Amount</div>
				<p class="kpi-value">INR 1,86,700.00</p>
			</article>
			<article class="kpi-card">
				<div class="kpi-label">Budget Utilization</div>
				<p class="kpi-value">62%</p>
			</article>
			<article class="kpi-card">
				<div class="kpi-label">Pending/In Review</div>
				<p class="kpi-value">6</p>
			</article>
			<article class="kpi-card">
				<div class="kpi-label">Approval Rate</div>
				<p class="kpi-value">71%</p>
			</article>
			<article class="kpi-card">
				<div class="kpi-label">Rejected Amount</div>
				<p class="kpi-value">INR 22,100.00</p>
			</article>
			<article class="kpi-card">
				<div class="kpi-label">Pending My Approval</div>
				<p class="kpi-value">2</p>
			</article>
		</section>

		<div class="row g-3 mb-3">
			<div class="col-lg-8">
				<div class="card page-card h-100">
					<div class="card-body">
						<div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
							<h5 class="mb-0">Recent Activity</h5>
							<span class="small-help">Latest request updates visible to your role.</span>
						</div>
						<div class="table-responsive">
							<table class="table table-hover align-middle mb-0">
								<thead class="table-light">
									<tr>
										<th class="ps-3">Ref No</th>
										<th>Title</th>
										<th>Type</th>
										<th>Amount</th>
										<th>Status</th>
										<th>Updated</th>
									</tr>
								</thead>
								<tbody>
									<tr>
										<td class="ps-3">REQ-2026-001</td>
										<td>Client Visit Travel</td>
										<td>Expense</td>
										<td>INR 12,500.00</td>
										<td><span class="status-badge badge-pending">In Review</span></td>
										<td>06 Apr 2026, 10:10</td>
									</tr>
									<tr>
										<td class="ps-3">REQ-2026-002</td>
										<td>Laptop Purchase</td>
										<td>Purchase</td>
										<td>INR 78,500.00</td>
										<td><span class="status-badge badge-approved">Approved</span></td>
										<td>05 Apr 2026, 16:20</td>
									</tr>
									<tr>
										<td class="ps-3">REQ-2026-003</td>
										<td>Workshop Vendor Fee</td>
										<td>Purchase</td>
										<td>INR 51,000.00</td>
										<td><span class="status-badge badge-rejected">Rejected</span></td>
										<td>04 Apr 2026, 11:42</td>
									</tr>
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

							<a href="#" class="btn btn-outline-primary"><i class="bi bi-list-check me-1"></i>Pending Approvals</a>
						</div>

						<h6 class="mb-2">Quick Links</h6>
						<a href="#" class="quick-link">Expense &amp; Purchase Policy</a>
						<a href="#" class="quick-link">Budget Request Guidelines</a>
					</div>
				</div>
			</div>
		</div>
	</div>
</main>

<?php require ROOT_PATH . '/views/templates/footer.php'; ?>
