<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/submission-notifications.php';
requireCanManageReports();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: ' . url('admin/reports.php'));
    exit;
}

$reportId  = (int) ($_POST['report_id'] ?? 0);
$action    = $_POST['action'] ?? '';
$newStatus = $_POST['status'] ?? '';
$notes     = sanitize($_POST['notes'] ?? '');
$user      = currentUser();

$validStatuses = ['submitted', 'pending', 'in_progress', 'rescued', 'failed'];

if ($action === 'approve') {
    $newStatus = 'in_progress';
} elseif ($action === 'reject') {
    $newStatus = 'failed';
}

if (!$reportId || !in_array($newStatus, $validStatuses, true)) {
    flash('error', 'Invalid request.');
    header('Location: ' . url('admin/reports.php'));
    exit;
}

$report = db_select('rescue_reports', 'id=eq.' . $reportId . '&limit=1', true);

if (!$report) {
    flash('error', 'Report not found.');
    header('Location: ' . url('admin/reports.php'));
    exit;
}

$isApproveReject = in_array($action, ['approve', 'reject'], true);

if ($isApproveReject && !canApproveOrRejectRescueReports()) {
    flash('error', 'Only administrators can approve or reject rescue reports.');
    header('Location: ' . url('view-report.php?id=' . $reportId));
    exit;
}

if (!$isApproveReject && !canUpdateRescueReport($report)) {
    flash('error', isStaff()
        ? 'Pending reports must be approved or rejected by an administrator first.'
        : 'You do not have permission to update this report.');
    header('Location: ' . url('view-report.php?id=' . $reportId));
    exit;
}

if ($report['status'] === $newStatus) {
    flash('error', 'Status is already set to that value.');
    header('Location: ' . url('view-report.php?id=' . $reportId));
    exit;
}

db_update('rescue_reports', ['status' => $newStatus], 'id=eq.' . $reportId);

db_insert('report_logs', [
    'report_id'  => $reportId,
    'updated_by' => $user['id'],
    'old_status' => $report['status'],
    'new_status' => $newStatus,
    'notes'      => $notes ?: null,
]);

$emailSent = false;
try {
    if ($newStatus === 'in_progress') {
        // Approved → in progress: notify reporter
        $emailSent = notifyReportApproved($report);
    } elseif ($newStatus === 'rescued') {
        $emailSent = notifyReportApproved($report);
    } elseif ($newStatus === 'failed') {
        $emailSent = notifyReportRejected($report);
    }
} catch (Throwable $e) {
    error_log('report status notification: ' . $e->getMessage());
}

$statusLabel = ucfirst(str_replace('_', ' ', $newStatus));
$msg = 'Report status updated to "' . $statusLabel . '".';
if ($newStatus === 'rescued' || $newStatus === 'failed') {
    $msg .= $emailSent ? ' Notification email sent.' : ' (Email could not be sent.)';
}
flash('success', $msg);
header('Location: ' . url('view-report.php?id=' . $reportId));
exit;
