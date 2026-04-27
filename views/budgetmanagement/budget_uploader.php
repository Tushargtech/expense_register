<main class="main">
	<div class="page-shell user-create-page">
		<div class="user-create-shell">
			<?php require ROOT_PATH . '/views/templates/flash_message.php'; ?>
			<?php
			$showPreview = !empty($_SESSION['budget_uploader_show_preview_once']);
			unset($_SESSION['budget_uploader_show_preview_once']);

			$preview = isset($_SESSION['budget_uploader_preview']) && is_array($_SESSION['budget_uploader_preview'])
				? $_SESSION['budget_uploader_preview']
				: [];
			$staged = isset($_SESSION['budget_uploader_staged']) && is_array($_SESSION['budget_uploader_staged'])
				? $_SESSION['budget_uploader_staged']
				: [];

			if (!$showPreview) {
				$preview = [];
				$staged = [];
				unset($_SESSION['budget_uploader_preview'], $_SESSION['budget_uploader_staged']);
			}

			$canConfirmUpload = !empty($staged) && !empty($staged['valid_rows']) && empty($staged['has_errors']);
			$hasDuplicateBudgets = !empty($staged['has_duplicates']);
			$duplicateRows = (int) ($staged['duplicate_rows'] ?? 0);
			$stagedFileName = isset($staged['source_name']) ? (string) $staged['source_name'] : '';
			?>

			<section class="user-create-hero">
				<p class="user-create-kicker">Budget Management</p>
				<h1 class="user-create-title">Budget Upload</h1>
			</section>

			<form method="POST" action="<?php echo htmlspecialchars(buildCleanRouteUrl('budget-uploader'), ENT_QUOTES, 'UTF-8'); ?>" enctype="multipart/form-data" class="user-create-form">
				<input type="hidden" name="upload_intent" value="preview">
				<section class="user-create-section">
					<div class="row g-4">
						<div class="col-12 col-lg-5">
							<div class="user-create-head">
								<div>
									<h2 class="user-create-section-title">Choose Budget File</h2>
									<p class="user-create-note">Supported formats: <strong>CSV</strong>, <strong>XLSX/XLS</strong>. Images are not supported.</p>
								</div>
							</div>

							<div class="user-create-field">
								<label class="user-create-label" for="budget_file">Budget File</label>
								<input
									type="file"
									class="user-create-input"
									id="budget_file"
									name="budget_file"
									accept=".csv,.xlsx,.xls"
									required
								>
								<p class="user-create-note">Select a file and click Generate Preview to view parsed rows before upload.</p>
							</div>
						</div>

						<div class="col-12 col-lg-7">
							<div class="user-create-head">
								<div>
									<h2 class="user-create-section-title">Sample Upload Format</h2>
									<p class="user-create-note">Use this column structure in your CSV/Excel file. Department and Budget Category should match existing master data.</p>
								</div>
							</div>

							<div class="table-responsive user-table-wrap" style="max-height: none;">
								<table class="table user-list-table align-middle mb-0">
									<thead>
										<tr>
											<th>Department</th>
											<th>Category ID</th>
											<th>Budget Fiscal Year</th>
											<th>Budget Fiscal Period</th>
											<th>Budget Allocated Amount</th>
											
											<th>Budget Notes</th>
										</tr>
									</thead>
									<tbody>
										<tr>
											<td>HR</td>
											<td>12</td>
											<td>2026</td>
											<td>Q1</td>
											<td>250000</td>
											
											<td>Quarter 1 hiring allocation</td>
										</tr>
										<tr>
											<td>Finance</td>
											<td>4</td>
											<td>2026</td>
											<td>Q2</td>
											<td>180000</td>
										
											<td>Audit and compliance spend</td>
										</tr>
									</tbody>
								</table>
							</div>

							<div class="user-create-actions" style="margin-top: 14px;">
								<a
									href="<?php echo htmlspecialchars(buildAssetUrl('assets/samples/budget_upload_sample.csv'), ENT_QUOTES, 'UTF-8'); ?>"
									class="user-create-btn user-create-btn-secondary"
									download
								>
									Download Sample CSV
								</a>
							</div>
						</div>
					</div>
				</section>

				<div class="user-create-action-bar">
					<div class="user-create-action-copy">
						<strong>Generate Preview</strong>
						<span>Data is not saved in this step.</span>
					</div>
					<div class="user-create-actions">
						<a href="<?php echo htmlspecialchars(buildCleanRouteUrl('dashboard'), ENT_QUOTES, 'UTF-8'); ?>" class="user-create-btn user-create-btn-secondary">Back to Dashboard</a>
						<button type="submit" class="user-create-btn user-create-btn-primary">Generate Preview</button>
					</div>
				</div>
			</form>

			<?php if (!empty($preview)): ?>
				<section class="user-create-section" style="margin-top: 30px;">
					<?php
					$previewHasIssues = false;
					foreach ($preview as $previewItem) {
						$itemStatus = (string) ($previewItem['status'] ?? '');
						$itemIssues = isset($previewItem['issues']) && is_array($previewItem['issues']) ? $previewItem['issues'] : [];
						if ($itemStatus === 'skipped' || !empty($itemIssues)) {
							$previewHasIssues = true;
							break;
						}
					}

					$displayPreviewRows = $previewHasIssues ? $preview : array_slice($preview, 0, 5);
					$displayedRowCount = count($displayPreviewRows);
					$totalPreviewRows = count($preview);
					?>
					<div class="user-create-head">
						<div>
							<h2 class="user-create-section-title">Preview</h2>
							<p class="user-create-note" style="margin-bottom: 15px;">
								<?php 
								$readyCount = count(array_filter($preview, static fn($p) => ($p['status'] ?? '') === 'ready'));
								$successCount = count(array_filter($preview, static fn($p) => ($p['status'] ?? '') === 'success'));
								$skippedCount = count($preview) - $readyCount - $successCount;
								echo 'Ready: ' . $readyCount . ' • Imported: ' . $successCount . ' • Issues: ' . $skippedCount;
								if ($stagedFileName !== '') {
									echo ' • File: ' . htmlspecialchars($stagedFileName, ENT_QUOTES, 'UTF-8');
								}
								if (!$previewHasIssues && $totalPreviewRows > 5) {
									echo ' • Showing first ' . $displayedRowCount . ' of ' . $totalPreviewRows . ' rows';
								}
								if ($hasDuplicateBudgets) {
									echo ' • Existing budget matches: ' . $duplicateRows;
								}
								?>
							</p>
						</div>
					</div>

					<div class="table-responsive user-table-wrap" style="max-height: none;">
						<table class="table user-list-table align-middle mb-0">
							<thead>
								<tr>
									<th>Row</th>
									<th>Department</th>
									<th>Fiscal Year</th>
									<th>Period</th>
									<th>Category</th>
									<th class="text-end">Amount</th>
									<th>Already Assigned Budget</th>
									<th>Status</th>
								</tr>
							</thead>
							<tbody>
								<?php foreach ($displayPreviewRows as $item): ?>
									<?php
									$status = (string) ($item['status'] ?? 'skipped');
									$rowBg = $status === 'success' ? '#eefbf3' : ($status === 'ready' ? '#f3f7ff' : '#fff6f6');
									$data = isset($item['data']) && is_array($item['data']) ? $item['data'] : [];
									$issues = isset($item['issues']) && is_array($item['issues']) ? $item['issues'] : [];
									$existingBudget = isset($item['existing_budget']) && is_array($item['existing_budget']) ? $item['existing_budget'] : null;
									$hasExistingBudget = is_array($existingBudget) && (int) ($existingBudget['budget_id'] ?? 0) > 0;
									?>
									<tr style="background: <?php echo $rowBg; ?>;">
										<td><strong>#<?php echo (int) ($item['row'] ?? 0); ?></strong></td>
										<td>
											<?php 
											echo !empty($data['department_name']) ? htmlspecialchars((string) $data['department_name'], ENT_QUOTES, 'UTF-8') : '<span class="text-danger">Missing</span>';
											?>
										</td>
										<td>
											<?php 
											echo !empty($data['budget_fiscal_year']) ? htmlspecialchars((string) $data['budget_fiscal_year'], ENT_QUOTES, 'UTF-8') : '<span class="text-danger">Missing</span>';
											?>
										</td>
										<td>
											<?php 
											echo !empty($data['budget_fiscal_period']) ? htmlspecialchars((string) $data['budget_fiscal_period'], ENT_QUOTES, 'UTF-8') : '<span class="text-danger">Missing</span>';
											?>
										</td>
										<td>
											<?php 
											echo !empty($data['budget_category']) ? htmlspecialchars((string) $data['budget_category'], ENT_QUOTES, 'UTF-8') : '<span class="text-danger">Missing</span>';
											?>
										</td>
										<td class="text-end">
											<?php 
											echo isset($data['budget_allocated_amount']) && is_numeric((string) $data['budget_allocated_amount'])
												? number_format((float) $data['budget_allocated_amount'], 2)
												: '<span class="text-danger">Invalid</span>';
											?>
										</td>
										<td>
											<?php if ($hasExistingBudget): ?>
												<div class="small">
													<div><strong>ID:</strong> <?php echo (int) ($existingBudget['budget_id'] ?? 0); ?></div>
													<div><strong>Amount:</strong> <?php echo number_format((float) ($existingBudget['budget_allocated_amount'] ?? 0), 2); ?> <?php echo htmlspecialchars((string) ($existingBudget['budget_currency'] ?? 'INR'), ENT_QUOTES, 'UTF-8'); ?></div>
												</div>
											<?php else: ?>
												<span class="text-muted small">No</span>
											<?php endif; ?>
										</td>
										<td>
											<?php 
											if ($status === 'success') {
												echo '<span class="status-pill status-active">Imported</span>';
											} elseif ($status === 'updated') {
												echo '<span class="status-pill status-approved">Updated</span>';
											} elseif ($status === 'ready') {
												echo $hasExistingBudget
													? '<span class="status-pill status-pending">Will Update</span>'
													: '<span class="status-pill status-pending">Ready</span>';
											} else {
												echo '<span class="status-pill status-rejected">Issues</span>';
												if (!empty($issues)) {
													echo ' <span class="text-danger small">' . htmlspecialchars(implode('; ', $issues), ENT_QUOTES, 'UTF-8') . '</span>';
												}
											}
											?>
										</td>
									</tr>
								<?php endforeach; ?>
							</tbody>
						</table>
					</div>

					<div class="user-create-action-bar" style="margin-top: 16px;">
						<div class="user-create-action-copy">
							<strong>Ready to Upload</strong>
							<span><?php echo $hasDuplicateBudgets ? 'Matching budgets already exist. Continue to update existing records.' : 'Review the table and continue.'; ?></span>
						</div>
						<div class="user-create-actions">
							<form method="POST" action="<?php echo htmlspecialchars(buildCleanRouteUrl('budget-uploader'), ENT_QUOTES, 'UTF-8'); ?>" style="display: inline;">
								<input type="hidden" name="upload_intent" value="cancel">
								<button type="submit" class="user-create-btn user-create-btn-secondary">Cancel</button>
							</form>
							<?php if ($canConfirmUpload): ?>
								<form method="POST" action="<?php echo htmlspecialchars(buildCleanRouteUrl('budget-uploader'), ENT_QUOTES, 'UTF-8'); ?>" style="display: inline;">
									<input type="hidden" name="upload_intent" value="confirm">
									<?php if ($hasDuplicateBudgets): ?>
										<input type="hidden" name="overwrite_existing" value="1">
										<button type="submit" class="user-create-btn user-create-btn-primary">Upload and Update Existing</button>
									<?php else: ?>
										<button type="submit" class="user-create-btn user-create-btn-primary">Upload Budget</button>
									<?php endif; ?>
								</form>
							<?php endif; ?>
						</div>
					</div>
				</section>
			<?php endif; ?>
		</div>
	</div>
</main>