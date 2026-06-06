<?php
require_once __DIR__ . '/../includes/auth.php';
requireAdmin();

$pageTitle = adminAreaTitle();
$user      = currentUser();

$adoptionStats = null;
try {
    require_once __DIR__ . '/../includes/adoption.php';
    $adoptionStats = getAdoptionStats();
} catch (Throwable $e) {
    $adoptionStats = null;
}

$allReports  = db_select('rescue_reports') ?? [];
$stats = [
    'total'       => count($allReports),
    'pending'     => count(array_filter($allReports, fn($r) => $r['status'] === 'pending')),
    'in_progress' => count(array_filter($allReports, fn($r) => $r['status'] === 'in_progress')),
    'rescued'     => count(array_filter($allReports, fn($r) => $r['status'] === 'rescued')),
    'failed'      => count(array_filter($allReports, fn($r) => $r['status'] === 'failed')),
];

$userCount  = 0;
$staffCount = 0;
if (isAdministrator()) {
    $allUsers   = db_select('users') ?? [];
    $userCount  = count(array_filter($allUsers, fn($u) => $u['role'] === 'user'));
    $staffCount = count(array_filter($allUsers, fn($u) => in_array($u['role'], ['admin', 'staff'], true)));
}

$recentReports = db_select('rescue_reports', 'order=created_at.desc&limit=8');
$userIds    = array_unique(array_column($recentReports, 'reporter_id'));
$usersById  = [];
if (!empty($userIds)) {
    $userRows = db_select('users', 'id=in.(' . implode(',', $userIds) . ')&select=id,full_name');
    foreach ($userRows as $u) {
        $usersById[$u['id']] = $u['full_name'];
    }
}

$recent = array_map(function ($r) use ($usersById) {
    $r['reporter_full'] = $usersById[$r['reporter_id']] ?? 'Unknown';
    return $r;
}, $recentReports);

$useSweetAlert = true;
require_once __DIR__ . '/../includes/header.php';
?>

<div class="dashboard-hero">
    <div>
        <p class="page-eyebrow">Welcome back</p>
        <h2><?= sanitize(explode(' ', $user['name'])[0]) ?>, <?= isStaff() ? 'ready to support rescues?' : 'here’s your system overview' ?></h2>
        <p><?php if (isAdministrator()): ?>
            Manage rescue reports, accounts, adoption approvals, and announcements across BantayPurrPaws.
        <?php else: ?>
            Handle approved rescue operations, update animal records, and monitor adoption activity.
        <?php endif; ?></p>
    </div>
    <span class="role-chip <?= roleBadgeClass() ?>"><?= roleLabel() ?></span>
</div>

<div class="quick-actions">
    <a href="<?= url('admin/reports.php') ?>" class="quick-action primary">📋 Rescue reports</a>
    <?php if (canManagePetListings()): ?>
    <a href="<?= url('admin/pets.php') ?>" class="quick-action">🐾 Animal records</a>
    <?php endif; ?>
    <?php if (canReviewAdoptionApplications()): ?>
    <a href="<?= url('admin/adoption-requests.php') ?>" class="quick-action">📝 Adoption requests</a>
    <?php elseif (canViewAdoptionApplications()): ?>
    <a href="<?= url('admin/adoption-requests.php') ?>" class="quick-action">📊 Adoption monitor</a>
    <?php endif; ?>
    <?php if (canPostAnnouncements()): ?>
    <a href="<?= url('admin/announcements.php') ?>" class="quick-action">📢 Post announcement</a>
    <?php endif; ?>
    <?php if (canManageAccounts()): ?>
    <a href="<?= url('admin/users.php') ?>" class="quick-action">👥 User accounts</a>
    <?php endif; ?>
</div>

<div class="stats-grid">
    <div class="stat-card">
        <div class="stat-label">Total reports</div>
        <div class="stat-value"><?= $stats['total'] ?></div>
        <div class="stat-sub">All submissions</div>
    </div>
    <div class="stat-card blue">
        <div class="stat-label">Pending review</div>
        <div class="stat-value"><?= $stats['pending'] ?></div>
        <div class="stat-sub"><?= isAdministrator() ? 'Needs your approval' : 'Awaiting administrator' ?></div>
    </div>
    <div class="stat-card">
        <div class="stat-label">In progress</div>
        <div class="stat-value"><?= $stats['in_progress'] ?></div>
        <div class="stat-sub">Active rescues</div>
    </div>
    <div class="stat-card green">
        <div class="stat-label">Rescued</div>
        <div class="stat-value"><?= $stats['rescued'] ?></div>
        <div class="stat-sub">Completed</div>
    </div>
    <?php if (isAdministrator()): ?>
    <div class="stat-card">
        <div class="stat-label">Registered users</div>
        <div class="stat-value"><?= $userCount ?></div>
        <div class="stat-sub"><?= $staffCount ?> staff &amp; admin</div>
    </div>
    <?php endif; ?>
</div>

<?php if ($adoptionStats !== null): ?>
<div class="page-header" style="margin-top:0">
    <h3 style="font-family:var(--font-display);font-size:1.35rem;margin:0">Adoption overview</h3>
</div>
<div class="stats-grid">
    <?php if (canManagePetListings()): ?>
    <div class="stat-card">
        <div class="stat-label">Total pets</div>
        <div class="stat-value"><?= $adoptionStats['total_pets'] ?></div>
        <div class="stat-sub"><a href="<?= url('admin/pets.php') ?>">Manage records →</a></div>
    </div>
    <div class="stat-card green">
        <div class="stat-label">Available</div>
        <div class="stat-value"><?= $adoptionStats['available_pets'] ?></div>
    </div>
    <?php endif; ?>
    <div class="stat-card blue">
        <div class="stat-label">Pending applications</div>
        <div class="stat-value"><?= $adoptionStats['pending_applications'] ?></div>
        <div class="stat-sub"><a href="<?= url('admin/adoption-requests.php') ?>">View queue →</a></div>
    </div>
    <div class="stat-card">
        <div class="stat-label">Approved adoptions</div>
        <div class="stat-value"><?= $adoptionStats['approved_adoptions'] ?></div>
    </div>
</div>
<?php endif; ?>

<div class="card">
    <div class="card-header">
        <span class="card-title">Recent rescue reports</span>
        <a href="<?= url('admin/reports.php') ?>" class="btn btn-ghost btn-sm">View all</a>
    </div>
    <?php if (empty($recent)): ?>
        <div class="empty-state">
            <div class="empty-icon">📭</div>
            <h3>No reports yet</h3>
            <p>Reports submitted by users will appear here.</p>
        </div>
    <?php else: ?>
        <div class="table-wrapper table-responsive-stack">
            <table>
                <thead>
                    <tr>
                        <th>Code</th>
                        <th>Reporter</th>
                        <th>Animal</th>
                        <th>Location</th>
                        <th>Status</th>
                        <th>Submitted</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($recent as $r):
                        $status = formatStatus($r['status']);
                    ?>
                    <tr>
                        <td><span class="report-code"><?= sanitize($r['report_code']) ?></span></td>
                        <td><?= sanitize($r['reporter_full']) ?></td>
                        <td><?= sanitize($r['animal_type'] ?: '—') ?></td>
                        <td style="max-width:180px;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;">
                            <?= sanitize($r['location']) ?>
                        </td>
                        <td>
                            <span class="status-badge <?= $status['class'] ?>">
                                <span class="status-dot"></span>
                                <?= $status['label'] ?>
                            </span>
                        </td>
                        <td class="text-secondary text-sm"><?= timeAgo($r['created_at']) ?></td>
                        <td>
                            <a href="<?= url('view-report.php?id=' . $r['id']) ?>" class="btn btn-ghost btn-sm">Open</a>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
