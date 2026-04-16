<?php
$expenses = isset($expenses) && is_array($expenses) ? $expenses : [];
$filters = isset($filters) && is_array($filters) ? $filters : [];

$searchValue = (string) ($filters['search'] ?? '');
$selectedStatus = (string) ($filters['status'] ?? 'pending');
$selectedType = (string) ($filters['type'] ?? '');
$selectedDepartment = (string) ($filters['department'] ?? '');

$currentPage = isset($currentPage) ? (int) $currentPage : 1;
$totalPages = isset($totalPages) ? (int) $totalPages : 1;
$total = isset($total) ? (int) $total : count($expenses);
$perPage = isset($perPage) ? (int) $perPage : 15;

$rangeStart = $total > 0 ? (($currentPage - 1) * $perPage) + 1 : 0;
$rangeEnd = $total > 0 ? min($total, $rangeStart + count($expenses) - 1) : 0;

// Mock data for filters - in real app, these would come from controller
$departments = isset($departments) && is_array($departments) ? $departments : [];
$requestTypes = ['expense' => 'Reimbursable', 'purchase' => 'Company Paid'];
$statuses = ['pending' => 'Pending', 'approved' => 'Approved', 'rejected' => 'Rejected'];
$canCreateExpense = isset($canCreateExpense) ? (bool) $canCreateExpense : false;
?>

<main class="main">
    <div class="page-shell user-list-page">
        <section class="user-list-panel">
            <?php require ROOT_PATH . '/views/templates/flash_message.php'; ?>

            <!-- ── Top row: title and Add button ── -->
                <div class="list-page-header">
                    <h2 class="list-page-title">Expenses</h2>
                </div>
        

            <!-- ── Filters ── -->
            <form class="user-filter-bar search-bar" method="GET" action="?route=expenses">
                <div class="filter-layout">
                    <div class="filter-left">
                        <div class="filter-field search-field">
                            <input
                                type="text"
                                name="search"
                                class="form-control"
                                placeholder="Search by title or description"
                                value="<?php echo htmlspecialchars($searchValue, ENT_QUOTES, 'UTF-8'); ?>"
                            >
                        </div>

                        <div class="filter-field">
                            <select name="status" class="form-select">
                                <option value="">All Status</option>
                                <?php foreach ($statuses as $key => $label): ?>
                                    <option value="<?php echo htmlspecialchars($key, ENT_QUOTES, 'UTF-8'); ?>" <?php echo $selectedStatus === $key ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($label, ENT_QUOTES, 'UTF-8'); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="filter-field">
                            <select name="type" class="form-select">
                                <option value="">All Types</option>
                                <?php foreach ($requestTypes as $key => $label): ?>
                                    <option value="<?php echo htmlspecialchars($key, ENT_QUOTES, 'UTF-8'); ?>" <?php echo $selectedType === $key ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($label, ENT_QUOTES, 'UTF-8'); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="filter-field">
                            <select name="department" class="form-select">
                                <option value="">All Departments</option>
                                <?php foreach ($departments as $dept): ?>
                                    <option value="<?php echo htmlspecialchars((string) ($dept['id'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>" <?php echo $selectedDepartment === (string) ($dept['id'] ?? '') ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars((string) ($dept['department_name'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="filter-actions">
                            <button type="submit" class="btn btn-primary btn-filter">Search</button>
                            <a href="?route=expenses" class="btn btn-outline-secondary btn-filter">Reset</a>
                        </div>
                    </div>

                    <?php if ($canCreateExpense): ?>
                        <div class="add-record-wrap">
                            <a href="?route=expenses/create" class="btn btn-primary add-record-btn add-btn">
                                <i class="bi bi-plus-lg me-1"></i>Add Expense
                            </a>
                        </div>
                    <?php endif; ?>
                </div>
            </form>

            <!-- ── Table ── -->
            <div class="table-responsive user-table-wrap">
                <table class="table user-list-table align-middle mb-0">
                    <thead>
                        <tr>
                            <th>Title</th>
                            <th>Department</th>
                            <th>Amount</th>
                            <th>Type</th>
                            <th>Status</th>
                            <th>Submitted By</th>
                            <th>Date</th>
                            <th class="text-center">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($expenses)): ?>
                            <tr>
                                <td colspan="8" class="text-center py-5 text-muted">
                                    No expenses found.
                                    <a href="?route=expenses/create">Create your first request</a>.
                                </td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($expenses as $expense): ?>
                                <tr>
                                    <td>
                                        <span class="fw-semibold">
                                            <?php echo htmlspecialchars((string) ($expense['request_title'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>
                                        </span>
                                    </td>
                                    <td><?php echo htmlspecialchars((string) ($expense['department_name'] ?? '—'), ENT_QUOTES, 'UTF-8'); ?></td>
                                    <td>
                                        <?php echo htmlspecialchars((string) ($expense['request_currency'] ?? 'INR'), ENT_QUOTES, 'UTF-8'); ?>
                                        <?php echo number_format((float) ($expense['request_amount'] ?? 0), 2); ?>
                                    </td>
                                    <td>
                                        <?php echo htmlspecialchars(
                                            match ($expense['request_type'] ?? '') {
                                                'expense' => 'Reimbursable',
                                                'purchase' => 'Company Paid',
                                                default => ucfirst((string) ($expense['request_type'] ?? '')),
                                            },
                                            ENT_QUOTES,
                                            'UTF-8'
                                        ); ?>
                                    </td>
                                    <td>
                                        <span class="status-pill <?php echo match ($expense['request_status'] ?? '') {
                                            'approved' => 'status-active',
                                            'rejected' => 'status-inactive',
                                            default => 'status-pending',
                                        }; ?>">
                                            <?php echo ucfirst((string) ($expense['request_status'] ?? 'pending')); ?>
                                        </span>
                                    </td>
                                    <td><?php echo htmlspecialchars((string) ($expense['submitter_name'] ?? '—'), ENT_QUOTES, 'UTF-8'); ?></td>
                                    <td>
                                        <?php
                                        $dt = $expense['request_submitted_at'] ?? '';
                                        echo $dt ? date('d M Y', strtotime($dt)) : '—';
                                        ?>
                                    </td>
                                    <td class="text-center">
                                        <a href="?route=expenses/review&id=<?php echo (int) ($expense['request_id'] ?? 0); ?>"
                                           title="View"
                                           class="btn btn-sm btn-outline-secondary border-0 p-1">
                                            <i class="bi bi-eye"></i>
                                        </a>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>

            <!-- ── Pagination ── -->
            <nav class="user-pagination-wrap" aria-label="Expenses pagination">
                <div class="pagination-meta"><?php echo $rangeStart; ?>&ndash;<?php echo $rangeEnd; ?> of <?php echo $total; ?></div>
                <ul class="pagination user-pagination mb-0">
                    <?php $prev = max(1, $currentPage - 1); ?>
                    <li class="page-item <?php echo $currentPage <= 1 ? 'disabled' : ''; ?>">
                        <a class="page-link" href="?route=expenses&page=<?php echo $prev; ?>">Prev</a>
                    </li>
                    <?php for ($p = max(1, $currentPage - 2); $p <= min($totalPages, $currentPage + 2); $p++): ?>
                        <li class="page-item <?php echo $p === $currentPage ? 'active' : ''; ?>">
                            <a class="page-link" href="?route=expenses&page=<?php echo $p; ?>"><?php echo $p; ?></a>
                        </li>
                    <?php endfor; ?>
                    <?php $next = min($totalPages, $currentPage + 1); ?>
                    <li class="page-item <?php echo $currentPage >= $totalPages ? 'disabled' : ''; ?>">
                        <a class="page-link" href="?route=expenses&page=<?php echo $next; ?>">Next</a>
                    </li>
                </ul>
            </nav>

        </section>
    </div>
</main>