<?php
$flashSuccess = trim((string) ($_GET['success'] ?? ''));
$flashError = trim((string) ($_GET['error'] ?? ''));
?>

<?php if ($flashSuccess !== ''): ?>
	<div class="alert alert-success alert-dismissible fade show mb-3" role="alert">
		<?php echo htmlspecialchars($flashSuccess, ENT_QUOTES, 'UTF-8'); ?>
		<button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
	</div>
<?php endif; ?>

<?php if ($flashError !== ''): ?>
	<div class="alert alert-danger alert-dismissible fade show mb-3" role="alert">
		<?php echo htmlspecialchars($flashError, ENT_QUOTES, 'UTF-8'); ?>
		<button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
	</div>
<?php endif; ?>