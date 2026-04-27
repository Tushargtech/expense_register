<?php
$request = isset($request) && is_array($request) ? $request : [];
$attachments = isset($attachments) && is_array($attachments) ? $attachments : [];
$workflowProgress = isset($workflowProgress) && is_array($workflowProgress) ? $workflowProgress : [];
$actionHistory = isset($actionHistory) && is_array($actionHistory) ? $actionHistory : [];
$isOwnRequest = isset($isOwnRequest) ? (bool) $isOwnRequest : false;
$canTakeAction = isset($canTakeAction) ? (bool) $canTakeAction : false;
$canReassignRequest = isset($canReassignRequest) ? (bool) $canReassignRequest : false;
$reassignableUsers = isset($reassignableUsers) && is_array($reassignableUsers) ? $reassignableUsers : [];

$requestId = (int) ($request['request_id'] ?? 0);
$actionFormUrl = isset($actionFormUrl) ? (string) $actionFormUrl : buildCleanRouteUrl('expenses/review', ['id' => $requestId]);
$requestStatus = strtolower(trim((string) ($request['request_status'] ?? 'pending')));
$requestType = strtolower(trim((string) ($request['request_type'] ?? '')));
$priority = strtolower(trim((string) ($request['request_priority'] ?? 'low')));
$submittedAt = (string) ($request['request_submitted_at'] ?? '');
$resolvedAt = (string) ($request['request_resolved_at'] ?? '');

$typeLabel = match ($requestType) {
    'expense' => 'Expense',
    'purchase' => 'Purchase',
    default => ucfirst($requestType),
};

$priorityLabel = ucfirst($priority !== '' ? $priority : 'low');
$statusLabel = ucfirst($requestStatus !== '' ? $requestStatus : 'pending');
?>

<main class="main">
    <div class="page-shell user-create-page expense-review-page">
        <div class="user-create-shell">
            <?php require ROOT_PATH . '/views/templates/flash_message.php'; ?>

            <section class="user-create-hero">
                <p class="user-create-kicker">Expense Management</p>
                <h1 class="user-create-title">Request Details</h1>
            </section>

            <div class="user-create-form expense-review-layout">
                <section class="user-create-section expense-review-left-section">
                    <div class="user-create-head">
                        <div>
                            <h2 class="user-create-section-title">Request Information</h2>
                        </div>
                    </div>

                    <div class="user-create-grid expense-details-grid">
                        <div class="user-create-field user-create-field-medium">
                            <label class="user-create-label">Title</label>
                            <div class="user-review-value"><?php echo htmlspecialchars((string) ($request['request_title'] ?? '—'), ENT_QUOTES, 'UTF-8'); ?></div>
                        </div>

                        <div class="user-create-field">
                            <label class="user-create-label">Reference No</label>
                            <div class="user-review-value"><?php echo htmlspecialchars((string) ($request['request_reference_no'] ?? '—'), ENT_QUOTES, 'UTF-8'); ?></div>
                        </div>

                        <div class="user-create-field">
                            <label class="user-create-label">Request Type</label>
                            <div class="user-review-value"><?php echo htmlspecialchars($typeLabel, ENT_QUOTES, 'UTF-8'); ?></div>
                        </div>

                        <div class="user-create-field">
                            <label class="user-create-label">Amount</label>
                            <div class="user-review-value">
                                <?php echo htmlspecialchars((string) ($request['request_currency'] ?? 'INR'), ENT_QUOTES, 'UTF-8'); ?>
                                <?php echo number_format((float) ($request['request_amount'] ?? 0), 2); ?>
                            </div>
                        </div>

                        <div class="user-create-field">
                            <label class="user-create-label">Department</label>
                            <div class="user-review-value"><?php echo htmlspecialchars((string) ($request['department_name'] ?? '—'), ENT_QUOTES, 'UTF-8'); ?></div>
                        </div>

                        <div class="user-create-field">
                            <label class="user-create-label">Budget Category</label>
                            <div class="user-review-value"><?php echo htmlspecialchars((string) ($request['budget_category_name'] ?? $request['request_category'] ?? '—'), ENT_QUOTES, 'UTF-8'); ?></div>
                        </div>

                        <div class="user-create-field">
                            <label class="user-create-label">Priority</label>
                            <div class="user-review-value"><?php echo htmlspecialchars($priorityLabel, ENT_QUOTES, 'UTF-8'); ?></div>
                        </div>

                        <div class="user-create-field">
                            <label class="user-create-label">Status</label>
                            <div class="user-review-value user-review-status status-<?php echo htmlspecialchars($requestStatus !== '' ? $requestStatus : 'pending', ENT_QUOTES, 'UTF-8'); ?>">
                                <?php echo htmlspecialchars($statusLabel, ENT_QUOTES, 'UTF-8'); ?>
                            </div>
                        </div>

                        <div class="user-create-field">
                            <label class="user-create-label">Submitted By</label>
                            <div class="user-review-value"><?php echo htmlspecialchars((string) ($request['submitter_name'] ?? '—'), ENT_QUOTES, 'UTF-8'); ?></div>
                        </div>

                        <div class="user-create-field">
                            <label class="user-create-label">Submitted On</label>
                            <div class="user-review-value"><?php echo $submittedAt !== '' ? htmlspecialchars(date('d M Y, h:i A', strtotime($submittedAt)), ENT_QUOTES, 'UTF-8') : '—'; ?></div>
                        </div>

                    </div>
                </section>

                <section class="user-create-section expense-review-right-section">
                    <div class="user-create-head">
                        <div>
                            <h2 class="user-create-section-title">Approval Progress</h2>
                        </div>
                    </div>

                    <div class="review-progress-list">
                        <?php if ($workflowProgress === []): ?>
                            <div class="user-review-empty">No workflow steps available.</div>
                        <?php else: ?>
                            <?php foreach ($workflowProgress as $step): ?>
                                <?php
                                $progressStatus = (string) ($step['progress_status'] ?? 'pending');
                                $actedAt = (string) ($step['latest_action_at'] ?? $step['request_step_acted_at'] ?? '');
                                $actorName = (string) ($step['latest_action_actor_name'] ?? $step['assigned_to_name'] ?? '');
                                ?>
                                <div class="review-progress-card progress-<?php echo htmlspecialchars($progressStatus, ENT_QUOTES, 'UTF-8'); ?>">
                                    <div class="review-progress-head">
                                        <strong><?php echo htmlspecialchars((string) ($step['step_name'] ?? 'Approval Step'), ENT_QUOTES, 'UTF-8'); ?></strong>
                                        <span><?php echo ucfirst(str_replace('_', ' ', $progressStatus)); ?></span>
                                    </div>
                                    <div class="review-progress-meta">
                                        <span>Step <?php echo (int) ($step['workflow_step_id'] ?? $step['step_id'] ?? 0); ?></span>
                                        <?php if ($actorName !== ''): ?>
                                            <span><?php echo htmlspecialchars($actorName, ENT_QUOTES, 'UTF-8'); ?></span>
                                        <?php endif; ?>
                                        <?php if ($actedAt !== ''): ?>
                                            <span><?php echo htmlspecialchars(date('d M Y, h:i A', strtotime($actedAt)), ENT_QUOTES, 'UTF-8'); ?></span>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>

                    <div class="review-action-card">
                        <div class="user-create-head">
                            <div>
                                <h2 class="user-create-section-title">Approval Actions</h2>
                            </div>
                        </div>

                        <?php if ($canTakeAction): ?>
                            <form method="POST" action="<?php echo htmlspecialchars($actionFormUrl, ENT_QUOTES, 'UTF-8'); ?>" class="review-action-form" data-review-action-form>
                                <input type="hidden" name="request_action" value="approve" data-review-action-field>

                                <div class="review-action-button-row">
                                    <button type="button" class="btn btn-success review-action-trigger" data-review-action="approve">Approve</button>
                                    <button type="button" class="btn btn-outline-danger review-action-trigger" data-review-action="reject">Reject</button>
                                    <?php if ($canReassignRequest): ?>
                                        <button type="button" class="btn btn-outline-secondary review-action-trigger" data-review-action="reassign">Reassign</button>
                                    <?php endif; ?>
                                </div>

                                <div class="review-action-panel" data-review-panel="reject" hidden>
                                    <label class="user-create-label" for="action_comment_reject">Rejection Description</label>
                                    <textarea id="action_comment_reject" name="action_comment" class="user-create-input review-action-textarea" rows="4" placeholder="Explain why this request was rejected."></textarea>
                                    <button type="submit" class="btn btn-danger review-action-confirm">Confirm Reject</button>
                                </div>

                                <?php if ($canReassignRequest): ?>
                                    <div class="review-action-panel" data-review-panel="reassign" hidden>
                                        <label class="user-create-label" for="reassign_to">Reassign To</label>
                                        <select id="reassign_to" name="reassign_to" class="form-select review-action-select">
                                            <option value="">Select employee</option>
                                            <?php foreach ($reassignableUsers as $user): ?>
                                                <?php $userId = (int) ($user['user_id'] ?? 0); ?>
                                                <?php $deptName = trim((string) ($user['department_name'] ?? '')); ?>
                                                <option value="<?php echo $userId; ?>"><?php echo htmlspecialchars((string) ($user['user_name'] ?? 'User') . ($deptName !== '' ? ' (' . $deptName . ')' : ''), ENT_QUOTES, 'UTF-8'); ?></option>
                                            <?php endforeach; ?>
                                        </select>
                                        <label class="user-create-label" for="action_comment_reassign">Reassign Reason</label>
                                        <textarea id="action_comment_reassign" name="action_comment" class="user-create-input review-action-textarea" rows="4" placeholder="Provide the reason for reassignment."></textarea>
                                        <button type="submit" class="btn btn-secondary review-action-confirm">Confirm Reassign</button>
                                    </div>
                                <?php endif; ?>
                            </form>
                        <?php else: ?>
                            <div class="user-review-empty">You are not the current approver for this request.</div>
                        <?php endif; ?>
                    </div>
                </section>

                <section class="user-create-section expense-review-additional-section">
                    <div class="user-create-head">
                        <div>
                            <h2 class="user-create-section-title">Additional Details</h2>
                        </div>
                    </div>

                    <div class="user-create-grid expense-additional-grid">
                        <div class="user-create-field">
                            <label class="user-create-label">Description</label>
                            <div class="user-review-value user-review-multiline"><?php echo nl2br(htmlspecialchars((string) ($request['request_description'] ?? '—'), ENT_QUOTES, 'UTF-8')); ?></div>
                        </div>

                        <div class="user-create-field">
                            <label class="user-create-label">Notes</label>
                            <div class="user-review-value user-review-multiline"><?php echo nl2br(htmlspecialchars((string) ($request['request_notes'] ?? '—'), ENT_QUOTES, 'UTF-8')); ?></div>
                        </div>

                        <div class="user-create-field">
                            <label class="user-create-label">Attachments</label>
                            <?php if ($attachments === []): ?>
                                <div class="user-review-value">No attachments.</div>
                            <?php else: ?>
                                <div class="review-attachment-list">
                                    <?php foreach ($attachments as $attachment): ?>
                                        <?php
                                        $attachmentId = (int) ($attachment['attachment_id'] ?? 0);
                                        $fileName = (string) ($attachment['attachment_file_name'] ?? 'Attachment');
                                        ?>
                                        <div class="review-attachment-item">
                                            <span class="review-attachment-name"><?php echo htmlspecialchars($fileName, ENT_QUOTES, 'UTF-8'); ?></span>
                                            <span class="review-attachment-actions">
                                                <a href="<?php echo htmlspecialchars(buildCleanRouteUrl('expenses/attachment/view', ['request_id' => $requestId, 'attachment_id' => $attachmentId]), ENT_QUOTES, 'UTF-8'); ?>" target="_blank" rel="noopener" aria-label="View attachment" title="View attachment"><i class="bi bi-eye"></i></a>
                                                <a href="<?php echo htmlspecialchars(buildCleanRouteUrl('expenses/attachment/download', ['request_id' => $requestId, 'attachment_id' => $attachmentId]), ENT_QUOTES, 'UTF-8'); ?>" aria-label="Download attachment" title="Download attachment"><i class="bi bi-download"></i></a>
                                            </span>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            <?php endif; ?>
                        </div>

                        <?php if ($resolvedAt !== ''): ?>
                            <div class="user-create-field">
                                <label class="user-create-label">Resolved At</label>
                                <div class="user-review-value"><?php echo htmlspecialchars(date('d M Y, h:i A', strtotime($resolvedAt)), ENT_QUOTES, 'UTF-8'); ?></div>
                            </div>
                        <?php endif; ?>
                    </div>
                </section>

                <div class="user-create-action-bar">
                    <div class="user-create-action-copy">
                        <strong>Request #<?php echo htmlspecialchars((string) ($request['request_reference_no'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></strong>
                        <span><?php echo $isOwnRequest ? 'You are viewing your request progress.' : 'You are viewing request details for review.'; ?></span>
                    </div>
                    <div class="user-create-actions">
                        <a href="<?php echo htmlspecialchars(buildCleanRouteUrl('expenses'), ENT_QUOTES, 'UTF-8'); ?>" class="user-create-btn user-create-btn-secondary">Back to Expense List</a>
                    </div>
                </div>
            </div>
        </div>
    </div>
</main>

<script>
(function () {
    var form = document.querySelector('[data-review-action-form]');
    if (!form) {
        return;
    }

    var actionField = form.querySelector('[data-review-action-field]');
    var triggers = form.querySelectorAll('[data-review-action]');
    var panels = form.querySelectorAll('[data-review-panel]');
    var confirmButtons = form.querySelectorAll('.review-action-confirm');
    var rejectComment = form.querySelector('#action_comment_reject');
    var reassignSelect = form.querySelector('#reassign_to');
    var reassignComment = form.querySelector('#action_comment_reassign');

    var hidePanels = function () {
        panels.forEach(function (panel) {
            panel.hidden = true;
        });

        if (rejectComment) {
            rejectComment.required = false;
            rejectComment.value = '';
        }

        if (reassignSelect) {
            reassignSelect.required = false;
        }

        if (reassignComment) {
            reassignComment.required = false;
            reassignComment.value = '';
        }
    };

    triggers.forEach(function (trigger) {
        trigger.addEventListener('click', function () {
            var action = trigger.getAttribute('data-review-action') || 'approve';

            if (actionField) {
                actionField.value = action;
            }

            hidePanels();

            if (action === 'approve') {
                if (confirm('Are you sure you want to approve this request?')) {
                    form.submit();
                }
                return;
            }

            var panel = form.querySelector('[data-review-panel="' + action + '"]');
            if (panel) {
                panel.hidden = false;
            }

            if (action === 'reject' && rejectComment) {
                rejectComment.required = true;
                rejectComment.focus();
            }

            if (action === 'reassign' && reassignSelect) {
                reassignSelect.required = true;
                if (reassignComment) {
                    reassignComment.required = true;
                }
                reassignSelect.focus();
            }
        });
    });

    confirmButtons.forEach(function (btn) {
        btn.addEventListener('click', function (e) {
            var action = actionField.value;
            var message = 'Are you sure you want to ' + action + ' this request?';
            if (!confirm(message)) {
                e.preventDefault();
            }
        });
    });

    hidePanels();
})();
</script>
