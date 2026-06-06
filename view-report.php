<?php
require_once __DIR__ . '/includes/auth.php';
requireLogin();

$user     = currentUser();
$reportId = (int) ($_GET['id'] ?? 0);

if (!$reportId) {
    header('Location: ' . url('dashboard.php'));
    exit;
}

$filters = 'id=eq.' . $reportId . '&limit=1';
if (!isAdmin()) {
    $filters .= '&reporter_id=eq.' . (int) $user['id'];
}

$report = db_select('rescue_reports', $filters, true);

if (!$report) {
    flash('error', 'Report not found or access denied.');
    header('Location: ' . (isAdmin() ? url('admin/reports.php') : url('my-reports.php')));
    exit;
}

// Decrypt contact number for display
require_once __DIR__ . '/includes/sensitive-data.php';
$report['contact_number'] = revealSubmissionPhone($report);

$assignedName = null;
if (!empty($report['assigned_to'])) {
    $assigned = db_select('users', 'id=eq.' . (int) $report['assigned_to'] . '&select=full_name&limit=1', true);
    $assignedName = $assigned['full_name'] ?? null;
}
$report['assigned_name'] = $assignedName;

$logs = db_select('report_logs', 'report_id=eq.' . $reportId . '&order=created_at.asc');

$actorIds = array_unique(array_column($logs, 'updated_by'));
$actorsById = [];
if (!empty($actorIds)) {
    $actorRows = db_select('users', 'id=in.(' . implode(',', $actorIds) . ')&select=id,full_name');
    foreach ($actorRows as $a) {
        $actorsById[$a['id']] = $a['full_name'];
    }
}

$logs = array_map(function ($log) use ($actorsById) {
    $log['actor_name'] = $actorsById[$log['updated_by']] ?? 'System';
    return $log;
}, $logs);

$pageTitle = 'Report ' . sanitize($report['report_code']);
$status    = formatStatus($report['status']);
$canApproveReject = canApproveOrRejectRescueReports();
$canUpdateStatus  = canUpdateRescueReport($report);

require_once __DIR__ . '/includes/header.php';
?>

<div class="page-header">
    <div class="flex items-center gap-3 flex-wrap">
        <a href="<?= isAdmin() ? url('admin/reports.php') : url('my-reports.php') ?>" class="btn btn-ghost btn-sm">← Back</a>
        <h2 style="margin:0"><?= sanitize($report['report_code']) ?></h2>
        <span class="status-badge <?= $status['class'] ?>">
            <span class="status-dot"></span>
            <?= $status['label'] ?>
        </span>
        <?php if ($report['status'] === 'in_progress'): ?>
        <p style="margin:8px 0 0;font-size:.82rem;color:var(--text-secondary);font-style:italic;">
            🚑 Rescue submitted to rescue team.
        </p>
        <?php endif; ?>
    </div>
</div>

<div class="report-detail-grid">
    <div>
        <div class="card mb-4">
            <div class="card-header"><span class="card-title">Report Details</span></div>
            <div class="card-body">
                <div class="detail-row">
                    <span class="detail-label">Report Code</span>
                    <span class="detail-value"><span class="report-code"><?= sanitize($report['report_code']) ?></span></span>
                </div>
                <div class="detail-row">
                    <span class="detail-label">Reporter Name</span>
                    <span class="detail-value"><?= sanitize($report['reporter_name']) ?></span>
                </div>
                <div class="detail-row">
                    <span class="detail-label">Contact Number</span>
                    <span class="detail-value"><?= sanitize($report['contact_number']) ?></span>
                </div>
                <div class="detail-row">
                    <span class="detail-label">Animal Type</span>
                    <span class="detail-value"><?= sanitize($report['animal_type'] ?: '—') ?></span>
                </div>
                <div class="detail-row">
                    <span class="detail-label">Location</span>
                    <span class="detail-value" style="text-align:right"><?= sanitize($report['location']) ?></span>
                </div>
                <?php if ($report['description']): ?>
                <div class="detail-row">
                    <span class="detail-label">Description</span>
                    <span class="detail-value" style="text-align:right"><?= sanitize($report['description']) ?></span>
                </div>
                <?php endif; ?>
                <?php if ($report['assigned_name']): ?>
                <div class="detail-row">
                    <span class="detail-label">Assigned To</span>
                    <span class="detail-value"><?= sanitize($report['assigned_name']) ?></span>
                </div>
                <?php endif; ?>
                <div class="detail-row">
                    <span class="detail-label">Submitted</span>
                    <span class="detail-value text-secondary"><?= date('F j, Y — g:i A', strtotime($report['created_at'])) ?></span>
                </div>
            </div>
        </div>

        <?php if ($report['photo_path']): ?>
        <div class="card mb-4">
            <div class="card-header"><span class="card-title">Submitted Photo</span></div>
            <div class="card-body">
                <img
                    src="<?= str_starts_with($report['photo_path'], 'http') ? sanitize($report['photo_path']) : url(ltrim($report['photo_path'], '/')) ?>"
                    alt="Report photo"
                    style="border-radius: var(--radius); max-height: 360px; width:100%; object-fit:cover; border: 1px solid var(--border);"
                >
            </div>
        </div>
        <?php endif; ?>
    </div>

    <div>
        <?php if ($canApproveReject && in_array($report['status'], ['pending','submitted'], true)): ?>
        <div class="card mb-4">
            <div class="card-header"><span class="card-title">Administrator review</span></div>
            <div class="card-body flex flex-col gap-2">
                <form method="POST" action="<?= url('admin/update-status.php') ?>" class="report-action-form">
                    <input type="hidden" name="report_id" value="<?= (int) $report['id'] ?>">
                    <input type="hidden" name="action" value="approve">
                    <button type="submit" class="btn btn-accent w-full" style="justify-content:center;">Approve Report</button>
                </form>
                <form method="POST" action="<?= url('admin/update-status.php') ?>" class="report-action-form">
                    <input type="hidden" name="report_id" value="<?= (int) $report['id'] ?>">
                    <input type="hidden" name="action" value="reject">
                    <button type="submit" class="btn btn-ghost w-full" style="justify-content:center;color:var(--status-failed)">Reject Report</button>
                </form>
            </div>
        </div>
        <?php elseif (isStaff() && in_array($report['status'], ['pending','submitted'], true)): ?>
        <div class="permission-notice mb-4">
            This report has been submitted and is awaiting review. Once approved, you can update its progress.
        </div>
        <?php endif; ?>

        <?php if ($canUpdateStatus): ?>
        <div class="card mb-4">
            <div class="card-header"><span class="card-title"><?= isStaff() ? 'Update rescue progress' : 'Update Status' ?></span></div>
            <div class="card-body">
                <form method="POST" action="<?= url('admin/update-status.php') ?>">
                    <input type="hidden" name="report_id" value="<?= (int) $report['id'] ?>">
                    <div class="form-group">
                        <label class="form-label">New Status</label>
                        <select name="status" class="form-control">
                            <option value="submitted"   <?= in_array($report['status'],['pending','submitted']) ? 'selected' : '' ?>>Submitted</option>
                            <option value="in_progress" <?= $report['status'] === 'in_progress' ? 'selected' : '' ?>>In Progress</option>
                            <option value="rescued"     <?= $report['status'] === 'rescued'     ? 'selected' : '' ?>>Rescued (Approved)</option>
                            <option value="failed"      <?= $report['status'] === 'failed'      ? 'selected' : '' ?>>Failed (Rejected)</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Notes (optional)</label>
                        <textarea name="notes" class="form-control" rows="3" placeholder="Add update notes..."></textarea>
                    </div>
                    <button type="submit" class="btn btn-primary w-full" style="justify-content:center;">Update Status</button>
                </form>
            </div>
        </div>
        <?php endif; ?>

        <div class="card">
            <div class="card-header"><span class="card-title">Activity Log</span></div>
            <div class="card-body">
                <?php if (empty($logs)): ?>
                    <p class="text-secondary text-sm">No activity yet.</p>
                <?php else: ?>
                    <div class="timeline">
                        <?php foreach ($logs as $log): ?>
                        <div class="timeline-item">
                            <div class="timeline-line">
                                <div class="timeline-dot"></div>
                                <div class="timeline-connector"></div>
                            </div>
                            <div class="timeline-content">
                                <div class="timeline-meta">
                                    <?= sanitize($log['actor_name']) ?> · <?= timeAgo($log['created_at']) ?>
                                </div>
                                <div class="timeline-text">
                                    <?php if ($log['old_status']): ?>
                                        Status changed from
                                        <strong><?= sanitize($log['old_status']) ?></strong>
                                        to
                                        <strong><?= sanitize($log['new_status']) ?></strong>
                                    <?php else: ?>
                                        Report submitted with status <strong><?= sanitize($log['new_status']) ?></strong>
                                    <?php endif; ?>
                                    <?php if ($log['notes']): ?>
                                        <div class="text-secondary text-sm mt-1"><?= sanitize($log['notes']) ?></div>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<?php if ($canApproveReject): ?>
<script>
document.querySelectorAll('.report-action-form').forEach(function (form) {
    form.addEventListener('submit', function (e) {
        e.preventDefault();
        const action = form.querySelector('[name="action"]').value;
        const title = action === 'approve' ? 'Approve this report?' : 'Reject this report?';
        const text = action === 'approve'
            ? 'Status will change to "In Progress" and the reporter will be notified.'
            : 'The reporter will be notified by email that the report was rejected.';
        if (typeof Swal !== 'undefined') {
            Swal.fire({
                title, text, icon: 'question',
                showCancelButton: true,
                confirmButtonColor: action === 'approve' ? '#8B3A3A' : '#ef4444',
                confirmButtonText: action === 'approve' ? 'Approve' : 'Reject'
            }).then(function (r) { if (r.isConfirmed) form.submit(); });
        } else if (confirm(title)) {
            form.submit();
        }
    });
});
</script>
<?php endif; ?>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
