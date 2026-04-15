<main class="main">
	<div class="page-shell user-create-page">
		<div class="user-create-shell">
			<?php require ROOT_PATH . '/views/templates/flash_message.php'; ?>

			<section class="user-create-hero">
				<p class="user-create-kicker">Finance Automation</p>
				<h1 class="user-create-title">Budget Upload</h1>
			</section>

			<form method="POST" action="?route=budget-uploader" enctype="multipart/form-data" class="user-create-form">
				<section class="user-create-section">
					<div class="user-create-head">
						<div>
							<h2 class="user-create-section-title">Upload File</h2>
							<p class="user-create-note">Supported formats: <strong>CSV</strong>, <strong>XLSX/XLS</strong>, <strong>JPG/JPEG/PNG</strong>.</p>
						</div>
					</div>

					<div class="user-create-grid">
						<div class="user-create-field user-create-field-medium">
							<label class="user-create-label" for="budget_file">Budget File</label>
							<input
								type="file"
								class="user-create-input"
								id="budget_file"
								name="budget_file"
								accept=".csv,.xlsx,.xls,.jpg,.jpeg,.png"
								required
							>
						</div>
					</div>
				</section>

				<div class="user-create-action-bar">
					<div class="user-create-action-copy">
						<strong>Validation happens before insert</strong>
						<span>Rows that fail mapping are skipped and reported below.</span>
					</div>
					<div class="user-create-actions">
						<a href="?route=home" class="user-create-btn user-create-btn-secondary">Back to Dashboard</a>
						<button type="submit" class="user-create-btn user-create-btn-primary">Upload and Process</button>
					</div>
				</div>
			</form>

			<?php
			
			$preview = isset($_SESSION['budget_uploader_preview']) ? $_SESSION['budget_uploader_preview'] : [];
			$successMsg = isset($_SESSION['budget_upload_success']) ? $_SESSION['budget_upload_success'] : '';
			$errorMsg = isset($_SESSION['budget_upload_error']) ? $_SESSION['budget_upload_error'] : '';
			
			if (!empty($preview)): ?>
				<section class="user-create-section" style="margin-top: 30px; background: #f8f9fa; border-radius: 12px; padding: 20px;">
					<div class="user-create-head">
						<div>
							<h2 class="user-create-section-title">Upload Preview</h2>
							<p class="user-create-note" style="margin-bottom: 15px;">
								<?php 
								$successCount = count(array_filter($preview, fn($p) => $p['status'] === 'success'));
								$skippedCount = count($preview) - $successCount;
								echo "✓ $successCount imported • ✗ $skippedCount skipped";
								?>
							</p>
						</div>
					</div>

					<div style="overflow-x: auto;">
						<table style="width: 100%; border-collapse: collapse; font-size: 13px;">
							<thead>
								<tr style="background: #e8eef7; border-bottom: 2px solid #cbd5e0;">
									<th style="padding: 10px; text-align: left; font-weight: 600;">Row</th>
									<th style="padding: 10px; text-align: left; font-weight: 600;">Department</th>
									<th style="padding: 10px; text-align: left; font-weight: 600;">Fiscal Year</th>
									<th style="padding: 10px; text-align: left; font-weight: 600;">Period</th>
									<th style="padding: 10px; text-align: left; font-weight: 600;">Category</th>
									<th style="padding: 10px; text-align: left; font-weight: 600;">Category ID</th>
									<th style="padding: 10px; text-align: left; font-weight: 600;">Amount</th>
									<th style="padding: 10px; text-align: left; font-weight: 600;">Currency</th>
									<th style="padding: 10px; text-align: left; font-weight: 600;">Status</th>
								</tr>
							</thead>
							<tbody>
								<?php foreach ($preview as $item): ?>
									<tr style="border-bottom: 1px solid #e2e8f0; background: <?php echo $item['status'] === 'success' ? '#f0f9ff' : '#fef7e6'; ?>;">
										<td style="padding: 10px;"><strong>#<?php echo $item['row']; ?></strong></td>
										<td style="padding: 10px;">
											<?php 
											echo $item['data']['department_name'] ?: '<span style="color: #dc2626;">✗ Missing</span>';
											?>
										</td>
										<td style="padding: 10px;">
											<?php 
											echo $item['data']['budget_fiscal_year'] ?: '<span style="color: #dc2626;">✗ Missing</span>';
											?>
										</td>
										<td style="padding: 10px;">
											<?php 
											echo $item['data']['budget_fiscal_period'] ?: '<span style="color: #dc2626;">✗ Missing</span>';
											?>
										</td>
										<td style="padding: 10px;">
											<?php 
											echo $item['data']['budget_category'] ?: '<span style="color: #dc2626;">✗ Missing</span>';
											?>
										</td>
										<td style="padding: 10px;">
											<?php 
											echo $item['data']['budget_category_id'] !== null ? (int) $item['data']['budget_category_id'] : '<span style="color: #dc2626;">✗ Missing</span>';
											?>
										</td>
										<td style="padding: 10px; text-align: right;">
											<?php 
											echo $item['data']['budget_allocated_amount'] ? number_format($item['data']['budget_allocated_amount'], 2) : '<span style="color: #dc2626;">✗ Invalid</span>';
											?>
										</td>
										<td style="padding: 10px;">
											<?php 
											echo $item['data']['budget_currency'] ?: 'INR';
											?>
										</td>
										<td style="padding: 10px;">
											<?php 
											if ($item['status'] === 'success') {
												echo '<span style="color: #16a34a; font-weight: 600;">✓ Success</span>';
											} else {
												echo '<span style="color: #dc2626; font-weight: 600;">✗ ' . implode('; ', $item['issues']) . '</span>';
											}
											?>
										</td>
									</tr>
								<?php endforeach; ?>
							</tbody>
						</table>
					</div>
				</section>

				<?php
				
				unset($_SESSION['budget_uploader_preview']);
				unset($_SESSION['budget_upload_success']);
				unset($_SESSION['budget_upload_error']);
				?>
			<?php endif; ?>
		</div>
	</div>
</main>
