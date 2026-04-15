<?php
$budget = isset($budget) && is_array($budget) ? $budget : [];
$departments = isset($departments) && is_array($departments) ? $departments : [];
$categories = isset($categories) && is_array($categories) ? $categories : [];
$currencyOptions = isset($currencyOptions) && is_array($currencyOptions) ? $currencyOptions : [];

$budgetId = (int) ($budget['budget_id'] ?? 0);
$selectedDepartmentId = (int) ($budget['department_id'] ?? 0);
$selectedYear = (string) ($budget['budget_fiscal_year'] ?? '');
$selectedPeriod = (string) ($budget['budget_fiscal_period'] ?? '');
$selectedCategoryId = (int) ($budget['budget_category_id'] ?? 0);
$selectedAmount = (string) ($budget['budget_allocated_amount'] ?? '');
$selectedCurrency = strtoupper((string) ($budget['budget_currency'] ?? ''));
$selectedNotes = (string) ($budget['budget_notes'] ?? '');
$formAction = buildCleanRouteUrl('budgets/edit', ['id' => $budgetId]);
$deleteAction = buildCleanRouteUrl('budgets/delete', ['id' => $budgetId]);

if ($currencyOptions === [] && $selectedCurrency !== '') {
    $currencyOptions = [$selectedCurrency];
}
?>

<main class="main">
    <div class="page-shell user-create-page">
        <div class="user-create-shell">
            <?php require ROOT_PATH . '/views/templates/flash_message.php'; ?>

            <section class="user-create-hero">
                <p class="user-create-kicker">Budget Management</p>
                <h1 class="user-create-title">Edit Budget</h1>
            </section>

            <form method="POST" action="<?php echo htmlspecialchars($formAction, ENT_QUOTES, 'UTF-8'); ?>" class="user-create-form">
                <section class="user-create-section">
                    <div class="user-create-head">
                        <div>
                            <h2 class="user-create-section-title">Budget Details</h2>
                            <p class="user-create-note">Update the selected budget row and save changes.</p>
                        </div>
                    </div>

                    <div class="user-create-grid">
                        <div class="user-create-field">
                            <label class="user-create-label" for="department_id">Department</label>
                            <select class="user-create-select" id="department_id" name="department_id" required>
                                <option value="">Select Department</option>
                                <?php foreach ($departments as $department): ?>
                                    <?php $departmentId = (int) ($department['id'] ?? 0); ?>
                                    <option value="<?php echo $departmentId; ?>" <?php echo $selectedDepartmentId === $departmentId ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars((string) ($department['department_name'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="user-create-field">
                            <label class="user-create-label" for="budget_fiscal_year">Fiscal Year</label>
                            <input type="text" class="user-create-input" id="budget_fiscal_year" name="budget_fiscal_year" value="<?php echo htmlspecialchars($selectedYear, ENT_QUOTES, 'UTF-8'); ?>" required>
                        </div>

                        <div class="user-create-field">
                            <label class="user-create-label" for="budget_fiscal_period">Fiscal Period</label>
                            <input type="text" class="user-create-input" id="budget_fiscal_period" name="budget_fiscal_period" value="<?php echo htmlspecialchars($selectedPeriod, ENT_QUOTES, 'UTF-8'); ?>" required>
                        </div>

                        <div class="user-create-field user-create-field-medium">
                            <label class="user-create-label" for="budget_category_id">Budget Category</label>
                            <select class="user-create-select" id="budget_category_id" name="budget_category_id" required>
                                <option value="">Select Category</option>
                                <?php foreach ($categories as $category): ?>
                                    <?php $categoryId = (int) ($category['budget_category_id'] ?? 0); ?>
                                    <option value="<?php echo $categoryId; ?>" <?php echo $selectedCategoryId === $categoryId ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars((string) ($category['budget_category_name'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="user-create-field">
                            <label class="user-create-label" for="budget_allocated_amount">Allocated Amount</label>
                            <input type="number" class="user-create-input" id="budget_allocated_amount" name="budget_allocated_amount" min="0.01" step="0.01" value="<?php echo htmlspecialchars($selectedAmount, ENT_QUOTES, 'UTF-8'); ?>" required>
                        </div>

                        <div class="user-create-field">
                            <label class="user-create-label" for="budget_currency">Currency</label>
                            <select class="user-create-select" id="budget_currency" name="budget_currency" required>
                                <?php foreach ($currencyOptions as $currencyOption): ?>
                                    <?php $currency = strtoupper(trim((string) $currencyOption)); ?>
                                    <?php if ($currency === '') { continue; } ?>
                                    <option value="<?php echo htmlspecialchars($currency, ENT_QUOTES, 'UTF-8'); ?>" <?php echo $selectedCurrency === $currency ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($currency, ENT_QUOTES, 'UTF-8'); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="user-create-field user-create-field-medium">
                            <label class="user-create-label" for="budget_notes">Notes</label>
                            <textarea class="user-create-input" id="budget_notes" name="budget_notes" rows="4" placeholder="Update notes"><?php echo htmlspecialchars($selectedNotes, ENT_QUOTES, 'UTF-8'); ?></textarea>
                        </div>
                    </div>
                </section>

                <div class="user-create-action-bar">
                    <div class="user-create-action-copy">
                        <strong>Save changes to update budget in database</strong>
                    </div>
                    <div class="user-create-actions">
                        <a href="<?php echo htmlspecialchars(buildCleanRouteUrl('budget-monitor'), ENT_QUOTES, 'UTF-8'); ?>" class="user-create-btn user-create-btn-secondary">Back to Budget Monitor</a>
                        <a href="<?php echo htmlspecialchars(buildCleanRouteUrl('budget-uploader'), ENT_QUOTES, 'UTF-8'); ?>" class="user-create-btn user-create-btn-secondary">New Budget Upload</a>
                        <form method="POST" action="<?php echo htmlspecialchars($deleteAction, ENT_QUOTES, 'UTF-8'); ?>" class="d-inline" id="deleteBudgetForm">
                            <button type="submit" class="user-create-btn btn btn-danger">Delete Budget</button>
                        </form>
                        <button type="submit" class="user-create-btn user-create-btn-primary">Update Budget</button>
                    </div>
                </div>
            </form>
        </div>
    </div>
</main>

<script>
document.addEventListener('DOMContentLoaded', function () {
    const deleteForm = document.getElementById('deleteBudgetForm');
    if (!deleteForm) {
        return;
    }

    deleteForm.addEventListener('submit', function (event) {
        const confirmed = window.confirm('Are you sure you want to delete this budget? This action cannot be undone.');
        if (!confirmed) {
            event.preventDefault();
        }
    });
});
</script>
