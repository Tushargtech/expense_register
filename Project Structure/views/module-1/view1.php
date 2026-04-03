<?php

$pageTitle = 'Dashboard - Expense Register';
$pageStyles = ['assets/css/dashboard.css'];
require ROOT_PATH . '/views/templates/header.php';
require ROOT_PATH . '/views/templates/navbar.php';

$userName = isset($userName) ? (string) $userName : 'User';
?>

<aside class="sidebar">
	<div class="sidebar-section">
		<div class="sidebar-section-title">Main</div>
		<a href="?route=module-1" class="active">Dashboard</a>
	</div>
	<div class="sidebar-section">
		<div class="sidebar-section-title">Expenses</div>
		<a href="#">Create Expense</a>
		<a href="#">Expense History</a>
	</div>
</aside>

<main class="main">
	<div class="page-shell">
		<section class="hero">
			<h1>Welcome, <?php echo htmlspecialchars($userName, ENT_QUOTES, 'UTF-8'); ?></h1>
			<p>Your expense dashboard is active. This screen is only available after successful login.</p>
		</section>

		<section class="cards-grid">
			<article class="metric-card total-tickets">
				<div class="metric-label">Total Requests</div>
				<div class="metric-value">24</div>
			</article>
			<article class="metric-card accepted-tickets">
				<div class="metric-label">Approved</div>
				<div class="metric-value">16</div>
			</article>
			<article class="metric-card rejected-tickets">
				<div class="metric-label">Rejected</div>
				<div class="metric-value">3</div>
			</article>
			<article class="metric-card total-expense">
				<div class="metric-label">Total Expense</div>
				<div class="metric-value">$12,480</div>
			</article>
			<article class="metric-card budget-remaining">
				<div class="metric-label">Budget Left</div>
				<div class="metric-value">$7,520</div>
			</article>
		</section>

		<section class="tickets-panel">
			<div class="tabs">
				<button type="button" class="tab-btn active">Pending</button>
				<button type="button" class="tab-btn">Approved</button>
				<button type="button" class="tab-btn">Rejected</button>
			</div>

			<div class="ticket-list">
				<article class="ticket-card">
					<div class="ticket-top">
						<div>
							<div class="ticket-id">EXP-1001</div>
							<div class="ticket-title">Team travel reimbursement</div>
						</div>
						<span class="status-badge status-pending">Pending</span>
					</div>
					<div class="ticket-meta">
						<div>
							<div class="meta-label">Amount</div>
							<div class="meta-value">$640</div>
						</div>
						<div>
							<div class="meta-label">Department</div>
							<div class="meta-value">Sales</div>
						</div>
						<div>
							<div class="meta-label">Date</div>
							<div class="meta-value">03 Apr 2026</div>
						</div>
					</div>
					<div class="ticket-footer">Open details</div>
				</article>

				<article class="ticket-card">
					<div class="ticket-top">
						<div>
							<div class="ticket-id">EXP-1002</div>
							<div class="ticket-title">Software subscription renewal</div>
						</div>
						<span class="status-badge status-approved">Approved</span>
					</div>
					<div class="ticket-meta">
						<div>
							<div class="meta-label">Amount</div>
							<div class="meta-value">$1,920</div>
						</div>
						<div>
							<div class="meta-label">Department</div>
							<div class="meta-value">Engineering</div>
						</div>
						<div>
							<div class="meta-label">Date</div>
							<div class="meta-value">02 Apr 2026</div>
						</div>
					</div>
					<div class="ticket-footer">Open details</div>
				</article>
			</div>
		</section>
	</div>
</main>

<?php require ROOT_PATH . '/views/templates/footer.php'; ?>
