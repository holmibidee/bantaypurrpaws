<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/users.php';

requireAdminOnly();

$pageTitle     = 'Edit Staff Permissions';
$useSweetAlert = true;

$targetId = (int) ($_GET['id'] ?? 0);
if ($targetId === 0) {
    flash('error', 'No staff member specified.');
    header('Location: ' . url('admin/staff.php'));
    exit;
}

$target = db_select('users', "id=eq.$targetId&limit=1", true);
if (!$target || $target['role'] !== 'staff') {
    flash('error', 'Staff member not found.');
    header('Location: ' . url('admin/staff.php'));
    exit;
}

// All available permissions
$allPermissions = [
    'manage_reports'     => ['label' => 'Manage Rescue Reports',     'desc'  => 'View, update, and close rescue reports.'],
    'manage_pets'        => ['label' => 'Manage Pet Listings',       'desc'  => 'Create, edit, and delete adoptable pet listings.'],
    'review_adoptions'   => ['label' => 'Review Adoption Applications', 'desc' => 'Approve or reject adoption applications.'],
    'view_adoptions'     => ['label' => 'View Adoption Queue',       'desc'  => 'Read-only access to adoption applications.'],
    'manage_users'       => ['label' => 'Manage Regular Users',      'desc'  => 'View and manage regular user accounts.'],
    'post_announcements' => ['label' => 'Post Announcements',        'desc'  => 'Publish site-wide announcements.'],
];

// Parse stored permissions (JSON array or null)
$storedRaw   = $target['staff_permissions'] ?? null;
$stored      = is_string($storedRaw) ? (json_decode($storedRaw, true) ?? []) : (is_array($storedRaw) ? $storedRaw : []);
$hasCustom   = $storedRaw !== null && $storedRaw !== 'null' && $storedRaw !== '[]';

// Handle form POST
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'update_permissions') {
    // Only keep keys that were actually checked AND are valid permission keys
    $submitted = $_POST['perms'] ?? [];
    $valid = [];
    foreach ($allPermissions as $key => $info) {
        if (($submitted[$key] ?? '') === '1') {
            $valid[] = $key;
        }
    }
    $json = json_encode($valid);

    $ok = updateUserFields($targetId, ['staff_permissions' => $json]);
    if ($ok) {
        // Notify the staff member in-app
        require_once __DIR__ . '/../includes/notifications.php';
        createSystemNotification(
            'system',
            'Your account permissions have been updated by an administrator. Please re-login to apply the changes.',
            null,
            null,
            $targetId
        );

        // Force the staff member to re-login by invalidating their session token
        // We store a "permissions_changed_at" timestamp; header.php checks this for staff
        updateUserFields($targetId, ['permissions_changed_at' => date('Y-m-d H:i:s')]);

        flash('success', 'Permissions updated for ' . sanitize($target['full_name']) . '. They will be prompted to re-login.');
    } else {
        flash('error', 'Failed to update permissions. Ensure the staff_permissions column exists (run database/rbac_permissions.sql).');
    }
    header('Location: ' . url('admin/staff-edit.php?id=' . $targetId));
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'reset_permissions') {
    $ok = updateUserFields($targetId, ['staff_permissions' => null]);
    if ($ok) {
        flash('success', 'Permissions reset to role defaults for ' . sanitize($target['full_name']) . '.');
    }
    header('Location: ' . url('admin/staff-edit.php?id=' . $targetId));
    exit;
}

require_once __DIR__ . '/../includes/header.php';
?>

<div class="page-header page-header-row">
    <div class="page-header-text">
        <h2>Staff Permissions — <?= sanitize($target['full_name']) ?></h2>
        <p>Assign granular permissions for this staff account. Leave all unchecked to use role defaults.</p>
    </div>
    <a href="<?= url('admin/staff.php') ?>" class="btn btn-ghost">← Back to Staff</a>
</div>

<?php if ($success = flash('success')): ?>
    <div class="alert alert-success" style="margin-bottom:20px;">✓ <?= sanitize($success) ?></div>
<?php endif; ?>
<?php if ($err = flash('error')): ?>
    <div class="alert alert-error" style="margin-bottom:20px;">✕ <?= sanitize($err) ?></div>
<?php endif; ?>

<!-- Status badge -->
<div class="card" style="margin-bottom:20px;">
    <div class="card-body" style="display:flex;align-items:center;gap:16px;flex-wrap:wrap;">
        <div>
            <p class="text-sm text-secondary" style="margin-bottom:2px;">Permission mode</p>
            <?php if ($hasCustom): ?>
                <span style="display:inline-flex;align-items:center;gap:6px;font-size:0.8rem;font-weight:600;color:#3b82f6;background:rgba(59,130,246,.1);border:1px solid rgba(59,130,246,.25);padding:4px 12px;border-radius:999px;">
                    🛡️ Custom permissions active
                </span>
            <?php else: ?>
                <span style="display:inline-flex;align-items:center;gap:6px;font-size:0.8rem;font-weight:600;color:#78716c;background:var(--surface-2);border:1px solid var(--border);padding:4px 12px;border-radius:999px;">
                    Role defaults (no overrides)
                </span>
            <?php endif; ?>
        </div>
        <?php if ($hasCustom): ?>
        <form method="POST" action="" style="margin-left:auto;">
            <input type="hidden" name="action" value="reset_permissions">
            <button type="submit" class="btn btn-ghost btn-sm" onclick="return confirm('Reset permissions to role defaults?')">
                Reset to defaults
            </button>
        </form>
        <?php endif; ?>
    </div>
</div>

<!-- Permissions form -->
<div class="card">
    <div class="card-header"><span class="card-title">Permission Assignments</span></div>
    <div class="card-body">
        <form method="POST" action="">
            <input type="hidden" name="action" value="update_permissions">
            <div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(300px,1fr));gap:16px;margin-bottom:28px;">
                <?php foreach ($allPermissions as $key => $info): ?>
                <label style="display:flex;align-items:flex-start;gap:12px;padding:16px;border:1.5px solid var(--border);border-radius:12px;cursor:pointer;transition:border-color .2s;"
                       onmouseover="this.style.borderColor='var(--accent)'" onmouseout="this.style.borderColor='var(--border)'">
                    <input type="checkbox" name="perms[<?= $key ?>]" value="1"
                           <?= in_array($key, $stored, true) ? 'checked' : '' ?>
                           style="margin-top:3px;accent-color:var(--accent);width:16px;height:16px;flex-shrink:0;">
                    <div>
                        <div style="font-size:0.875rem;font-weight:600;color:var(--text-primary);margin-bottom:3px;">
                            <?= htmlspecialchars($info['label']) ?>
                        </div>
                        <div style="font-size:0.78rem;color:var(--text-secondary);line-height:1.5;">
                            <?= htmlspecialchars($info['desc']) ?>
                        </div>
                    </div>
                </label>
                <?php endforeach; ?>
            </div>
            <div style="display:flex;gap:10px;align-items:center;">
                <button type="submit" class="btn btn-accent">Save Permissions</button>
                <a href="<?= url('admin/staff.php') ?>" class="btn btn-ghost">Cancel</a>
            </div>
        </form>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
