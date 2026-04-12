<?php
$errorCode = isset($errorCode) ? (string) $errorCode : 'unauthorized';
$errorMessage = isset($errorMessage) ? (string) $errorMessage : 'You do not have permission to access this area.';
$isLoggedIn = !empty($_SESSION['auth']['is_logged_in']);
?>

<main class="main">
    <div class="page-shell dashboard-page">
        <div class="card page-card">
            <div class="card-body p-4 p-md-5">
                <h1 class="h4 mb-2">Access Denied</h1>
                <p class="text-muted mb-3">Code: <?php echo htmlspecialchars($errorCode, ENT_QUOTES, 'UTF-8'); ?></p>
                <p class="mb-4"><?php echo htmlspecialchars($errorMessage, ENT_QUOTES, 'UTF-8'); ?></p>
                <div class="d-flex gap-2 flex-wrap">
                    <?php if ($isLoggedIn): ?>
                        <a href="?route=home" class="btn btn-primary">Go To Dashboard</a>
                    <?php else: ?>
                        <a href="?route=dashboard" class="btn btn-primary">Go To Login</a>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</main>
