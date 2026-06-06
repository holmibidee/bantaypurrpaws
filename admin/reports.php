<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/sensitive-data.php';
requireCanManageReports();

$pageTitle = 'Rescue Reports';

$filterStatus = $_GET['status'] ?? '';
$search       = sanitize($_GET['q'] ?? '');

// ← CHANGED: replaced PDO JOIN query with two db_select() calls + array_filter()
$filters = 'order=created_at.desc';
if ($filterStatus) {
    $filters .= '&status=eq.' . urlencode($filterStatus);
}

$allReports = db_select('rescue_reports', $filters);

// Search filter (code, reporter_name, location)
if ($search) {
    $s = strtolower($search);
    $allReports = array_filter($allReports, function ($r) use ($s) {
        return str_contains(strtolower($r['report_code'] ?? ''), $s)
            || str_contains(strtolower($r['reporter_name'] ?? ''), $s)
            || str_contains(strtolower($r['location'] ?? ''), $s);
    });
}

// Attach reporter full names via lookup
$userIds   = array_unique(array_column($allReports, 'reporter_id'));
$usersById = [];
if (!empty($userIds)) {
    $userRows = db_select('users', 'id=in.(' . implode(',', $userIds) . ')&select=id,full_name');
    foreach ($userRows as $u) $usersById[$u['id']] = $u['full_name'];
}

$reports = array_map(function ($r) use ($usersById) {
    $r = hydrateRescueReport($r);
    $r['reporter_full'] = $usersById[$r['reporter_id']] ?? 'Unknown';
    return $r;
}, array_values($allReports));

require_once __DIR__ . '/../includes/header.php';
?>

<div class="dashboard-hero" style="margin-bottom:24px;padding:22px 28px">
    <div>
        <p class="page-eyebrow">Rescue</p>
        <h2 style="font-size:1.45rem;margin:0">Rescue reports</h2>
        <p style="margin-top:6px;margin-bottom:0"><?= isAdministrator()
            ? 'Approve or reject pending reports and oversee all rescue activity.'
            : 'Update progress on approved reports and monitor active rescues.' ?></p>
    </div>
    <span class="role-chip <?= roleBadgeClass() ?>"><?= roleLabel() ?></span>
</div>

<div class="page-header">
    <h2>All Rescue Reports</h2>
    <p>Review and manage all submitted reports.</p>
</div>

<div class="card mb-6">
    <div class="card-body" style="padding: 16px 24px;">
        <form method="GET" action="" class="flex gap-3 flex-wrap items-center filter-bar">
            <input type="text" name="q" class="form-control filter-search"
                   placeholder="Search code, name, location…" value="<?= sanitize($search) ?>">
            <select name="status" class="form-control filter-select">
                <option value="">All Statuses</option>
                <option value="submitted"  <?= $filterStatus === 'submitted'  ? 'selected' : '' ?>>Submitted</option>
                <option value="in_progress" <?= $filterStatus === 'in_progress' ? 'selected' : '' ?>>In Progress</option>
                <option value="rescued"     <?= $filterStatus === 'rescued'     ? 'selected' : '' ?>>Rescued</option>
                <option value="failed"      <?= $filterStatus === 'failed'      ? 'selected' : '' ?>>Failed</option>
            </select>
            <button type="submit" class="btn btn-primary">Filter</button>
            <?php if ($filterStatus || $search): ?>
                <a href="<?= url('admin/reports.php') ?>" class="btn btn-ghost">Clear</a>
            <?php endif; ?>
            <span class="text-sm text-secondary filter-result-count">
                <?= count($reports) ?> result<?= count($reports) !== 1 ? 's' : '' ?>
            </span>
        </form>
    </div>
</div>

<div class="card">
    <?php if (empty($reports)): ?>
        <div class="empty-state">
            <div class="empty-icon">🔍</div>
            <h3>No reports found</h3>
            <p>Try adjusting your search or filter criteria.</p>
        </div>
    <?php else: ?>
        <div class="table-wrapper table-responsive-stack">
            <table>
                <thead>
                    <tr>
                        <th>Code</th><th>Reporter</th><th>Contact</th>
                        <th>Animal</th><th>Location</th><th>Status</th>
                        <th>Submitted</th><th></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($reports as $r):
                        $status = formatStatus($r['status']);
                    ?>
                    <tr>
                        <td><span class="report-code"><?= sanitize($r['report_code']) ?></span></td>
                        <td><?= sanitize($r['reporter_full']) ?></td>
                        <td class="text-secondary text-sm"><?= sanitize($r['contact_number']) ?></td>
                        <td><?= sanitize($r['animal_type'] ?: '—') ?></td>
                        <td style="max-width:160px;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;">
                            <?= sanitize($r['location']) ?>
                        </td>
                        <td>
                            <span class="status-badge <?= $status['class'] ?>">
                                <span class="status-dot"></span><?= $status['label'] ?>
                            </span>
                        </td>
                        <td class="text-secondary text-sm"><?= timeAgo($r['created_at']) ?></td>
                        <td><a href="<?= url('view-report.php?id=' . $r['id']) ?>" class="btn btn-ghost btn-sm">Manage</a></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
