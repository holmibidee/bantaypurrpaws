<?php
require_once __DIR__ . '/includes/auth.php';
requireLogin();

if (isAdmin()) {
    header('Location: ' . url('admin/reports.php'));
    exit;
}

$pageTitle = 'My Reports';
$user = currentUser();
$db   = getDB();

$stmt = $db->prepare("SELECT * FROM rescue_reports WHERE reporter_id = ? ORDER BY created_at DESC");
$stmt->execute([$user['id']]);
$reports = $stmt->fetchAll();

require_once __DIR__ . '/includes/header.php';
?>

<div class="page-header">
    <h2>My Rescue Reports</h2>
    <p>Track the status of all your submitted reports.</p>
</div>

<div class="flex gap-3 mb-6">
    <a href="<?= url('report.php') ?>" class="btn btn-accent">🐾 Submit New Report</a>
</div>

<div class="card">
    <?php if (empty($reports)): ?>
        <div class="empty-state">
            <div class="empty-icon">📋</div>
            <h3>No reports submitted yet</h3>
            <p>When you submit a report, it will appear here.</p>
        </div>
    <?php else: ?>
        <div class="table-wrapper table-responsive-stack">
            <table>
                <thead>
                    <tr>
                        <th>Report Code</th>
                        <th>Animal</th>
                        <th>Location</th>
                        <th>Status</th>
                        <th>Last Updated</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($reports as $report):
                        $status = formatStatus($report['status']);
                    ?>
                    <tr>
                        <td><span class="report-code"><?= sanitize($report['report_code']) ?></span></td>
                        <td><?= sanitize($report['animal_type'] ?: '—') ?></td>
                        <td style="max-width:240px; white-space:nowrap; overflow:hidden; text-overflow:ellipsis;">
                            <?= sanitize($report['location']) ?>
                        </td>
                        <td>
                            <span class="status-badge <?= $status['class'] ?>">
                                <span class="status-dot"></span>
                                <?= $status['label'] ?>
                            </span>
                        </td>
                        <td class="text-secondary text-sm"><?= $report['updated_at'] ?></td>
                        <td>
                            <a href="<?= url('view-report.php?id=' . $report['id']) ?>" class="btn btn-ghost btn-sm">View</a>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
