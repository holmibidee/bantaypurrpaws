<?php
require_once __DIR__ . '/includes/auth.php';
requireLogin();

if (isAdmin()) {
    header('Location: ' . url('admin/dashboard.php'));
    exit;
}

$pageTitle = 'Dashboard';
$user = currentUser();
$db   = getDB();

$stmt = $db->prepare("SELECT 
    COUNT(*) as total,
    SUM(status = 'pending') as pending,
    SUM(status = 'in_progress') as in_progress,
    SUM(status = 'rescued') as rescued
    FROM rescue_reports WHERE reporter_id = ?");
$stmt->execute([$user['id']]);
$stats = $stmt->fetch();

$stmt = $db->prepare("SELECT * FROM rescue_reports WHERE reporter_id = ? ORDER BY created_at DESC LIMIT 5");
$stmt->execute([$user['id']]);
$recentReports = $stmt->fetchAll();

require_once __DIR__ . '/includes/header.php';
?>

<div class="dashboard-hero">
    <div>
        <p class="page-eyebrow">Your account</p>
        <h2>Hello, <?= sanitize(explode(' ', $user['name'])[0]) ?></h2>
        <p>Submit rescue reports, track their progress, and browse pets available for adoption.</p>
    </div>
    <span class="role-chip role-user">User</span>
</div>

<div class="quick-actions">
    <a href="<?= url('report.php') ?>" class="quick-action primary">🐾 Submit rescue report</a>
    <a href="<?= url('adoption.php') ?>" class="quick-action">❤ Adopt a pet</a>
    <a href="<?= url('my-reports.php') ?>" class="quick-action">📋 My reports</a>
    <a href="<?= url('announcements.php') ?>" class="quick-action">📢 Announcements</a>
</div>

<div class="stats-grid">
    <div class="stat-card">
        <div class="stat-label">Total reports</div>
        <div class="stat-value"><?= (int) $stats['total'] ?></div>
        <div class="stat-sub">All time</div>
    </div>
    <div class="stat-card blue">
        <div class="stat-label">Pending</div>
        <div class="stat-value"><?= (int) $stats['pending'] ?></div>
        <div class="stat-sub">Awaiting response</div>
    </div>
    <div class="stat-card">
        <div class="stat-label">In progress</div>
        <div class="stat-value"><?= (int) $stats['in_progress'] ?></div>
        <div class="stat-sub">Being handled</div>
    </div>
    <div class="stat-card green">
        <div class="stat-label">Rescued</div>
        <div class="stat-value"><?= (int) $stats['rescued'] ?></div>
        <div class="stat-sub">Successful</div>
    </div>
</div>

<div class="card">
    <div class="card-header">
        <span class="card-title">Recent reports</span>
        <a href="<?= url('my-reports.php') ?>" class="btn btn-ghost btn-sm">View all</a>
    </div>
    <?php if (empty($recentReports)): ?>
        <div class="empty-state">
            <div class="empty-icon">📋</div>
            <h3>No reports yet</h3>
            <p>Submit your first rescue report to get started.</p>
            <a href="<?= url('report.php') ?>" class="btn btn-accent mt-3">Submit report</a>
        </div>
    <?php else: ?>
        <div class="table-wrapper table-responsive-stack">
            <table>
                <thead>
                    <tr>
                        <th>Code</th>
                        <th>Location</th>
                        <th>Animal</th>
                        <th>Status</th>
                        <th>Submitted</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($recentReports as $report):
                        $status = formatStatus($report['status']);
                    ?>
                    <tr>
                        <td><span class="report-code"><?= sanitize($report['report_code']) ?></span></td>
                        <td><?= sanitize(mb_strimwidth($report['location'], 0, 40, '…')) ?></td>
                        <td><?= sanitize($report['animal_type'] ?: '—') ?></td>
                        <td>
                            <span class="status-badge <?= $status['class'] ?>">
                                <span class="status-dot"></span>
                                <?= $status['label'] ?>
                            </span>
                        </td>
                        <td class="text-secondary text-sm"><?= timeAgo($report['created_at']) ?></td>
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
