<?php
$flash = function_exists('flash_consume') ? flash_consume() : ['type' => '', 'message' => ''];
$flashType = trim((string) ($flash['type'] ?? ''));
$flashMessage = trim((string) ($flash['message'] ?? ''));
$flashSuccess = trim((string) ($_GET['success'] ?? ''));
$flashError = trim((string) ($_GET['error'] ?? ''));

if ($flashMessage !== '') {
	if ($flashType === 'error') {
		$flashError = $flashMessage;
	} else {
		$flashSuccess = $flashMessage;
	}
}
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