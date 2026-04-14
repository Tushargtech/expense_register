<?php
$pendingRequests = isset($pendingRequests) && is_array($pendingRequests) ? $pendingRequests : [];
$workflowStepsMap = isset($workflowStepsMap) && is_array($workflowStepsMap) ? $workflowStepsMap : [];
$selectedRequest = isset($selectedRequest) && is_array($selectedRequest) ? $selectedRequest : [];
$selectedWorkflowSteps = isset($selectedWorkflowSteps) && is_array($selectedWorkflowSteps) ? $selectedWorkflowSteps : [];
$selectedRequestId = isset($selectedRequestId) ? (int) $selectedRequestId : 0;

$statusLabelMap = [
	'pending' => 'Pending',
	'approved' => 'Approved',
	'rejected' => 'Rejected',
];

$formatRange = static function ($minAmount, $maxAmount): string {
	$hasMin = $minAmount !== null && $minAmount !== '';
	$hasMax = $maxAmount !== null && $maxAmount !== '';
	if (!$hasMin && !$hasMax) {
		return '-';
	}
	if ($hasMin && $hasMax) {
		return 'INR ' . number_format((float) $minAmount, 2) . ' - INR ' . number_format((float) $maxAmount, 2);
	}
	if ($hasMin) {
		return '>= INR ' . number_format((float) $minAmount, 2);
	}
	return '<= INR ' . number_format((float) $maxAmount, 2);
};

$selectedRequestStatus = strtolower((string) ($selectedRequest['request_status'] ?? 'pending'));
$selectedStatusLabel = $statusLabelMap[$selectedRequestStatus] ?? ucfirst($selectedRequestStatus);
$selectedTicketRef = (string) ($selectedRequest['request_reference_no'] ?? 'N/A');
$selectedTicketTitle = (string) ($selectedRequest['request_title'] ?? 'No pending request selected');
$selectedWorkflowName = (string) ($selectedRequest['workflow_name'] ?? 'Unknown Workflow');
$selectedWorkflowType = (string) ($selectedRequest['workflow_type'] ?? '-');
$selectedAmountRange = $formatRange($selectedRequest['workflow_amount_min'] ?? null, $selectedRequest['workflow_amount_max'] ?? null);
$selectedCurrentStep = (string) ($selectedRequest['current_step_name'] ?? 'Not started');
$selectedCurrentActor = (string) ($selectedRequest['current_actor_name'] ?? 'System');
$requesterName = (string) ($_SESSION['auth']['name'] ?? 'Requester');

$currentStepIndex = -1;
foreach ($selectedWorkflowSteps as $index => $step) {
	if ((int) ($step['step_id'] ?? 0) === (int) ($selectedRequest['request_current_step_id'] ?? 0)) {
		$currentStepIndex = $index;
		break;
	}
}

$timelineSteps = [[
	'title' => 'Submitted',
	'actor' => $requesterName,
	'is_terminal' => true,
]];

foreach ($selectedWorkflowSteps as $index => $step) {
	$approverType = strtolower((string) ($step['step_approver_type'] ?? ''));
	if ($approverType === 'user') {
		$actor = (string) ($step['approver_user_name'] ?? 'User');
	} elseif ($approverType === 'role') {
		$approverRole = strtolower((string) ($step['step_approver_role'] ?? ''));
		if ($approverRole === 'manager' && trim((string) ($selectedRequest['submitter_manager_name'] ?? '')) !== '') {
			$actor = (string) $selectedRequest['submitter_manager_name'];
		} else {
			$actor = (string) ($step['step_approver_role'] ?? 'Role');
		}
	} elseif ($approverType === 'department_head') {
		$actor = 'Department Head';
	} else {
		$actor = 'System';
	}

	$timelineSteps[] = [
		'title' => (string) ($step['step_name'] ?? ('Step ' . ($index + 1))),
		'actor' => $actor,
		'is_terminal' => false,
	];
}

$timelineSteps[] = [
	'title' => 'Approved',
	'actor' => 'System',
	'is_terminal' => true,
];

$activeTimelineIndex = 0;
if ($selectedRequestStatus === 'approved') {
	$activeTimelineIndex = max(0, count($timelineSteps) - 1);
} elseif ($currentStepIndex >= 0) {
	$activeTimelineIndex = $currentStepIndex + 1;
} elseif (count($timelineSteps) > 1) {
	$activeTimelineIndex = 1;
}

if ($selectedRequestStatus === 'approved') {
	$selectedCurrentStep = 'Approved';
} elseif ($activeTimelineIndex === 0) {
	$selectedCurrentStep = 'Submitted';
}

$progressPercent = 0;
if (count($timelineSteps) > 1) {
	$progressPercent = (int) round(($activeTimelineIndex / (count($timelineSteps) - 1)) * 100);
}

$pendingRequestsJson = json_encode($pendingRequests, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
if ($pendingRequestsJson === false) {
	$pendingRequestsJson = '[]';
}

$workflowStepsMapJson = json_encode($workflowStepsMap, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
if ($workflowStepsMapJson === false) {
	$workflowStepsMapJson = '{}';
}
?>

<main class="main">
	<div class="page-shell workflow-view-page">
		<section class="workflow-view-selector">
			<div class="workflow-view-selector-head">
				<div>
					<p class="workflow-view-kicker">Workflow Management</p>
					<h1 class="workflow-view-title">Workflow View</h1>
					<p class="workflow-view-subtitle">Track your pending request stages in the approval workflow.</p>
				</div>
			</div>

			<label for="workflowTicketSelect" class="workflow-view-label">Select Raised Ticket</label>
			<select id="workflowTicketSelect" class="form-select workflow-view-select">
				<?php foreach ($pendingRequests as $request): ?>
					<?php
					$requestId = (int) ($request['request_id'] ?? 0);
					$referenceNo = (string) ($request['request_reference_no'] ?? 'N/A');
					$title = (string) ($request['request_title'] ?? '');
					?>
					<option value="<?php echo $requestId; ?>" <?php echo $requestId === $selectedRequestId ? 'selected' : ''; ?>><?php echo htmlspecialchars($referenceNo . ' - ' . $title, ENT_QUOTES, 'UTF-8'); ?></option>
				<?php endforeach; ?>
			</select>
			<div class="workflow-view-help">Choose your pending request to view its current workflow stage.</div>
		</section>

		<section class="workflow-view-card">
			<div class="workflow-view-heading">
				<div>
					<div class="workflow-view-ticket" id="ticketRef">Ticket: <?php echo htmlspecialchars($selectedTicketRef, ENT_QUOTES, 'UTF-8'); ?></div>
					<div class="workflow-view-name" id="ticketTitle"><?php echo htmlspecialchars($selectedTicketTitle, ENT_QUOTES, 'UTF-8'); ?></div>
					<div class="workflow-view-flow" id="workflowTitle">Workflow: <?php echo htmlspecialchars($selectedWorkflowName, ENT_QUOTES, 'UTF-8'); ?></div>
				</div>
				<span class="status-pill status-pending" id="workflowStatus"><?php echo htmlspecialchars($selectedStatusLabel, ENT_QUOTES, 'UTF-8'); ?></span>
			</div>

			<div class="workflow-view-stepper">
				<div class="workflow-view-stepper-track"></div>
				<div class="workflow-view-stepper-progress" id="stepperProgress" style="width: <?php echo $progressPercent; ?>%;"></div>
				<div class="workflow-view-steps" id="workflowStepsContainer">
					<?php if (empty($timelineSteps)): ?>
						<div class="workflow-view-empty">No pending request data found.</div>
					<?php else: ?>
						<?php foreach ($timelineSteps as $index => $timelineStep): ?>
							<?php
							$stepClass = 'workflow-view-step';
							$isApprovedCompleted = $selectedRequestStatus === 'approved';
							if ($isApprovedCompleted && $index <= $activeTimelineIndex) {
								$stepClass .= ' completed';
							} elseif ($index < $activeTimelineIndex) {
								$stepClass .= ' completed';
							} elseif ($index === $activeTimelineIndex) {
								$stepClass .= ' active';
							}
							$actor = (string) ($timelineStep['actor'] ?? 'System');
							$markerLabel = ($index < $activeTimelineIndex || ($isApprovedCompleted && $index === $activeTimelineIndex)) ? '✓' : (string) ($index + 1);
							$meta = $actor . ' · Pending';
							if ($isApprovedCompleted && $index === $activeTimelineIndex) {
								$meta = $actor . ' · Approved';
							} elseif ($index < $activeTimelineIndex) {
								$meta = $actor . ' · Completed';
							} elseif ($index === $activeTimelineIndex) {
								if ((string) ($timelineStep['title'] ?? '') === 'Submitted') {
									$meta = $actor . ' · Submitted';
								} elseif ((string) ($timelineStep['title'] ?? '') === 'Approved') {
									$meta = $actor . ' · Approved';
								} else {
									$meta = 'Approver: ' . $selectedCurrentActor . ' · Active';
								}
							}
							?>
							<div class="<?php echo htmlspecialchars($stepClass, ENT_QUOTES, 'UTF-8'); ?>">
								<div class="workflow-view-marker"><?php echo htmlspecialchars($markerLabel, ENT_QUOTES, 'UTF-8'); ?></div>
								<div class="workflow-view-step-title"><?php echo htmlspecialchars((string) ($timelineStep['title'] ?? ('Step ' . ($index + 1))), ENT_QUOTES, 'UTF-8'); ?></div>
								<div class="workflow-view-step-meta"><?php echo htmlspecialchars($meta, ENT_QUOTES, 'UTF-8'); ?></div>
							</div>
						<?php endforeach; ?>
					<?php endif; ?>
				</div>
			</div>

			<div class="workflow-view-info-grid">
				<div>
					<div class="workflow-view-info-label">Workflow Type</div>
					<div class="workflow-view-info-value" id="workflowTypeValue"><?php echo htmlspecialchars($selectedWorkflowType, ENT_QUOTES, 'UTF-8'); ?></div>
				</div>
				<div>
					<div class="workflow-view-info-label">Amount Range</div>
					<div class="workflow-view-info-value" id="amountRangeValue"><?php echo htmlspecialchars($selectedAmountRange, ENT_QUOTES, 'UTF-8'); ?></div>
				</div>
				<div>
					<div class="workflow-view-info-label">Current Step</div>
					<div class="workflow-view-info-value" id="currentStepValue"><?php echo htmlspecialchars($selectedCurrentStep, ENT_QUOTES, 'UTF-8'); ?></div>
				</div>
				<div>
					<div class="workflow-view-info-label">Current Actor</div>
					<div class="workflow-view-info-value" id="currentActorValue"><?php echo htmlspecialchars($selectedCurrentActor, ENT_QUOTES, 'UTF-8'); ?></div>
				</div>
			</div>
		</section>
	</div>
</main>

<script>
(function () {
	const requests = <?php echo $pendingRequestsJson; ?>;
	const stepsMap = <?php echo $workflowStepsMapJson; ?>;
	const select = document.getElementById('workflowTicketSelect');
	const ticketRef = document.getElementById('ticketRef');
	const ticketTitle = document.getElementById('ticketTitle');
	const workflowTitle = document.getElementById('workflowTitle');
	const workflowStatus = document.getElementById('workflowStatus');
	const workflowTypeValue = document.getElementById('workflowTypeValue');
	const amountRangeValue = document.getElementById('amountRangeValue');
	const currentStepValue = document.getElementById('currentStepValue');
	const currentActorValue = document.getElementById('currentActorValue');
	const stepperProgress = document.getElementById('stepperProgress');
	const workflowStepsContainer = document.getElementById('workflowStepsContainer');
	const workflowStepperTrack = document.querySelector('.workflow-view-stepper-track');
	const requesterName = <?php echo json_encode($requesterName, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE); ?>;

	function escapeHtml(value) {
		return String(value || '')
			.replace(/&/g, '&amp;')
			.replace(/</g, '&lt;')
			.replace(/>/g, '&gt;')
			.replace(/"/g, '&quot;')
			.replace(/'/g, '&#39;');
	}

	function amountRange(min, max) {
		const hasMin = min !== null && min !== undefined && min !== '';
		const hasMax = max !== null && max !== undefined && max !== '';
		if (!hasMin && !hasMax) {
			return '-';
		}
		if (hasMin && hasMax) {
			return 'INR ' + Number(min).toFixed(2) + ' - INR ' + Number(max).toFixed(2);
		}
		if (hasMin) {
			return '>= INR ' + Number(min).toFixed(2);
		}
		return '<= INR ' + Number(max).toFixed(2);
	}

	function formatStatus(status) {
		const value = String(status || 'pending').toLowerCase();
		if (value === 'approved') {
			return 'Approved';
		}
		if (value === 'rejected') {
			return 'Rejected';
		}
		return 'Pending';
	}

	function statusClass(status) {
		const value = String(status || 'pending').toLowerCase();
		if (value === 'approved') {
			return 'status-active';
		}
		if (value === 'rejected') {
			return 'status-inactive';
		}
		return 'status-pending';
	}

	function findRequest(requestId) {
		const target = Number(requestId);
		return requests.find(function (item) {
			return Number(item.request_id) === target;
		}) || null;
	}

	function actorLabel(step, request) {
		const approverType = String(step.step_approver_type || '').toLowerCase();
		if (approverType === 'user') {
			return String(step.approver_user_name || 'User');
		}
		if (approverType === 'role') {
			const role = String(step.step_approver_role || '').toLowerCase();
			if (role === 'manager' && String((request && request.submitter_manager_name) || '').trim() !== '') {
				return String(request.submitter_manager_name);
			}
			return String(step.step_approver_role || 'Role');
		}
		if (approverType === 'department_head') {
			return 'Department Head';
		}
		return 'System';
	}

	function renderSteps(request) {
		const workflowId = String(request.workflow_id || '');
		const steps = stepsMap[workflowId] || [];
		const requestStatus = String(request.request_status || '').toLowerCase();
		if (!Array.isArray(steps)) {
			workflowStepsContainer.innerHTML = '<div class="workflow-view-empty">No pending request data found.</div>';
			stepperProgress.style.width = '0%';
			if (workflowStepperTrack) {
				workflowStepperTrack.style.width = '0px';
			}
			return;
		}

		const timeline = [{ title: 'Submitted', actor: requesterName }]
			.concat(steps.map(function (step, index) {
				return {
					title: step.step_name || ('Step ' + (index + 1)),
					actor: actorLabel(step, request),
					type: 'workflow'
				};
			}))
			.concat([{ title: 'Approved', actor: 'System' }]);

		if (timeline.length === 0) {
			workflowStepsContainer.innerHTML = '<div class="workflow-view-empty">No pending request data found.</div>';
			stepperProgress.style.width = '0%';
			if (workflowStepperTrack) {
				workflowStepperTrack.style.width = '0px';
			}
			return;
		}

		const currentStepId = Number(request.request_current_step_id || 0);
		const currentIndex = steps.findIndex(function (step) {
			return Number(step.step_id) === currentStepId;
		});
		let activeIndex = 0;
		if (requestStatus === 'approved') {
			activeIndex = Math.max(0, timeline.length - 1);
		} else if (currentIndex >= 0) {
			activeIndex = currentIndex + 1;
		} else if (timeline.length > 1) {
			activeIndex = 1;
		}

		const isApprovedCompleted = requestStatus === 'approved';

		workflowStepsContainer.innerHTML = timeline.map(function (step, index) {
			const stateClass = (isApprovedCompleted && index <= activeIndex)
				? 'completed'
				: (index < activeIndex ? 'completed' : (index === activeIndex ? 'active' : ''));
			const marker = (index < activeIndex || (isApprovedCompleted && index === activeIndex)) ? '✓' : String(index + 1);
			let meta = step.actor + ' · Pending';
			if (isApprovedCompleted && index === activeIndex) {
				meta = step.actor + ' · Approved';
			} else if (index < activeIndex) {
				meta = step.actor + ' · Completed';
			} else if (index === activeIndex) {
				if (step.title === 'Submitted') {
					meta = step.actor + ' · Submitted';
				} else if (step.title === 'Approved') {
					meta = step.actor + ' · Approved';
				} else {
					meta = 'Approver: ' + String(request.current_actor_name || step.actor || 'System') + ' · Active';
				}
			}

			return '<div class="workflow-view-step ' + stateClass + '">' +
				'<div class="workflow-view-marker">' + escapeHtml(marker) + '</div>' +
				'<div class="workflow-view-step-title">' + escapeHtml(step.title || ('Step ' + (index + 1))) + '</div>' +
				'<div class="workflow-view-step-meta">' + escapeHtml(meta) + '</div>' +
			'</div>';
		}).join('');

		const markers = workflowStepsContainer.querySelectorAll('.workflow-view-marker');
		if (!workflowStepperTrack || !stepperProgress || markers.length === 0) {
			return;
		}

		const stepsRect = workflowStepsContainer.getBoundingClientRect();
		const firstRect = markers[0].getBoundingClientRect();
		const lastRect = markers[markers.length - 1].getBoundingClientRect();
		const firstCenter = (firstRect.left - stepsRect.left) + (firstRect.width / 2);
		const lastCenter = (lastRect.left - stepsRect.left) + (lastRect.width / 2);
		const trackWidth = Math.max(0, lastCenter - firstCenter);

		workflowStepperTrack.style.left = firstCenter + 'px';
		workflowStepperTrack.style.right = 'auto';
		workflowStepperTrack.style.width = trackWidth + 'px';

		const activeMarkerRect = markers[Math.min(activeIndex, markers.length - 1)].getBoundingClientRect();
		const activeCenter = (activeMarkerRect.left - stepsRect.left) + (activeMarkerRect.width / 2);
		const progressWidth = Math.max(0, activeCenter - firstCenter);
		stepperProgress.style.left = firstCenter + 'px';
		stepperProgress.style.right = 'auto';
		stepperProgress.style.width = progressWidth + 'px';

		if (requestStatus === 'approved') {
			currentStepValue.textContent = 'Approved';
			currentActorValue.textContent = 'System';
		} else if (activeIndex === 0) {
			currentStepValue.textContent = 'Submitted';
			currentActorValue.textContent = requesterName;
		} else {
			currentStepValue.textContent = String(request.current_step_name || 'Not started');
			currentActorValue.textContent = String(request.current_actor_name || 'System');
		}
	}

	function renderRequest(requestId) {
		const request = findRequest(requestId) || requests[0] || null;
		if (!request) {
			return;
		}

		ticketRef.textContent = 'Ticket: ' + String(request.request_reference_no || 'N/A');
		ticketTitle.textContent = String(request.request_title || 'No pending request selected');
		workflowTitle.textContent = 'Workflow: ' + String(request.workflow_name || 'Unknown Workflow');
		workflowStatus.textContent = formatStatus(request.request_status);
		workflowStatus.className = 'status-pill ' + statusClass(request.request_status);
		workflowTypeValue.textContent = String(request.workflow_type || '-');
		amountRangeValue.textContent = amountRange(request.workflow_amount_min, request.workflow_amount_max);
		renderSteps(request);
	}

	if (select) {
		select.addEventListener('change', function () {
			renderRequest(select.value);
		});
	}

	renderRequest(select ? select.value : '0');
})();
</script>
