<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/adoption.php';
require_once __DIR__ . '/../includes/sensitive-data.php';

requireCanViewAdoptionApplications();

$pageTitle = 'Adoption Requests';

// ← CHANGED: removed $db argument
$stats  = getAdoptionStats();
$filter = $_GET['status'] ?? '';

// ← CHANGED: replaced PDO JOIN with two db_select() calls + merge
$appFilters = 'order=created_at.desc';
if (in_array($filter, ['pending', 'approved', 'rejected'], true)) {
    $appFilters .= '&status=eq.' . $filter;
}

$apps = db_select('adoption_applications', $appFilters);

// Build pet name lookup
$petIds  = array_unique(array_column($apps, 'pet_id'));
$petsById = [];
if (!empty($petIds)) {
    $petRows = db_select('pets', 'id=in.(' . implode(',', $petIds) . ')&select=id,name,status');
    foreach ($petRows as $p) $petsById[$p['id']] = $p;
}

// Attach pet_name and pet_status to each application
$applications = array_map(function ($a) use ($petsById) {
    $a = hydrateAdoptionApplication($a);
    $p = $petsById[$a['pet_id']] ?? [];
    $a['pet_name']   = $p['name']   ?? 'Unknown Pet';
    $a['pet_status'] = $p['status'] ?? 'unknown';
    return $a;
}, $apps);

require_once __DIR__ . '/../includes/header.php';
?>

<div class="dashboard-hero">
    <div>
        <p class="page-eyebrow">Adoption</p>
        <h2><?= isStaff() ? 'Adoption Monitor' : 'Adoption Requests' ?></h2>
        <p><?= isStaff()
            ? 'Track adoption applications and animal status. Pet records can be updated under Animal Records.'
            : 'Review, approve, or reject pet adoption submissions.' ?></p>
    </div>
    <?php if (isStaff()): ?>
    <span class="role-chip role-staff">Staff</span>
    <?php else: ?>
    <span class="role-chip role-administrator">Administrator</span>
    <?php endif; ?>
</div>

<div class="stats-grid" style="margin-bottom:1.5rem">
    <div class="stat-card">
        <div class="stat-label">Total Pets</div>
        <div class="stat-value"><?= $stats['total_pets'] ?></div>
    </div>
    <div class="stat-card green">
        <div class="stat-label">Available Pets</div>
        <div class="stat-value"><?= $stats['available_pets'] ?></div>
    </div>
    <div class="stat-card blue">
        <div class="stat-label">Pending Applications</div>
        <div class="stat-value"><?= $stats['pending_applications'] ?></div>
    </div>
    <div class="stat-card">
        <div class="stat-label">Approved Adoptions</div>
        <div class="stat-value"><?= $stats['approved_adoptions'] ?></div>
    </div>
</div>

<div class="card">
    <div class="card-header">
        <span class="card-title">Applications</span>
        <div class="flex gap-2">
            <a href="<?= url('admin/adoption-requests.php') ?>"               class="btn btn-ghost btn-sm <?= $filter === ''         ? 'active' : '' ?>">All</a>
            <a href="<?= url('admin/adoption-requests.php?status=pending') ?>" class="btn btn-ghost btn-sm <?= $filter === 'pending'  ? 'active' : '' ?>">Pending</a>
            <a href="<?= url('admin/adoption-requests.php?status=approved') ?>" class="btn btn-ghost btn-sm <?= $filter === 'approved' ? 'active' : '' ?>">Approved</a>
            <a href="<?= url('admin/adoption-requests.php?status=rejected') ?>" class="btn btn-ghost btn-sm <?= $filter === 'rejected' ? 'active' : '' ?>">Rejected</a>
        </div>
    </div>

    <?php if (empty($applications)): ?>
        <div class="empty-state">
            <div class="empty-icon">📋</div>
            <h3>No applications</h3>
            <p>Applications will appear here when users submit adoption requests.</p>
        </div>
    <?php else: ?>
        <div class="table-wrapper table-responsive-stack">
            <table>
                <thead>
                    <tr>
                        <th>Applicant</th><th>Pet</th><th>Contact</th>
                        <th>Submitted</th><th>Status</th><th></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($applications as $app):
                        $st = formatApplicationStatus($app['status']);
                    ?>
                    <tr>
                        <td>
                            <strong><?= sanitize($app['full_name']) ?></strong><br>
                            <span class="text-sm text-secondary"><?= sanitize($app['email']) ?></span>
                        </td>
                        <td><?= sanitize($app['pet_name']) ?></td>
                        <td class="text-sm"><?= sanitize($app['contact_number']) ?></td>
                        <td class="text-sm text-secondary"><?= timeAgo($app['created_at']) ?></td>
                        <td>
                            <span class="status-badge <?= $st['class'] ?>">
                                <span class="status-dot"></span><?= $st['label'] ?>
                            </span>
                        </td>
                        <td><a href="<?= url('admin/application.php?id=' . $app['id']) ?>" class="btn btn-ghost btn-sm">View</a></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
