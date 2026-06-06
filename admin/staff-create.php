<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/users.php';
require_once __DIR__ . '/../includes/mailer.php';

requireCanManageAccounts();

$pageTitle     = 'Invite Staff Member';
$useSweetAlert = true;

// ── All available permissions (shared with staff-edit) ──
$allPermissions = [
    'manage_reports'     => ['label' => 'Manage Rescue Reports',        'desc' => 'View, update, and close rescue reports.'],
    'manage_pets'        => ['label' => 'Manage Pet Listings',          'desc' => 'Create, edit, and delete adoptable pet listings.'],
    'review_adoptions'   => ['label' => 'Review Adoption Applications', 'desc' => 'Approve or reject adoption applications.'],
    'view_adoptions'     => ['label' => 'View Adoption Queue',          'desc' => 'Read-only access to adoption applications.'],
    'manage_users'       => ['label' => 'Manage Regular Users',         'desc' => 'View and manage regular user accounts.'],
    'post_announcements' => ['label' => 'Post Announcements',           'desc' => 'Publish site-wide announcements.'],
];

// ── Handle invite form POST ──────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'send_invite') {
    $email       = trim(strtolower($_POST['email'] ?? ''));
    $selectedRaw = array_keys(array_filter($_POST['perms'] ?? [], fn($v) => $v === '1'));
    $permissions = array_values(array_filter($selectedRaw, fn($k) => isset($allPermissions[$k])));

    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        flash('error', 'Please enter a valid email address.');
        header('Location: ' . url('admin/staff-create.php'));
        exit;
    }
    if (emailExists($email)) {
        flash('error', 'An account with that email already exists.');
        header('Location: ' . url('admin/staff-create.php'));
        exit;
    }

    // Create invite token
    $token     = bin2hex(random_bytes(32));
    $expiresAt = date('Y-m-d H:i:s', time() + 86400); // 24 hours

    $inserted = db_insert('staff_invites', [
        'email'      => $email,
        'token'      => $token,
        'permissions'=> json_encode($permissions),
        'expires_at' => $expiresAt,
        'used'       => 0,
        'created_by' => (int) currentUser()['id'],
    ]);

    if (!$inserted) {
        flash('error', 'Could not create invitation. Please ensure the migration SQL has been run (sql/otp_purposes_migration.sql).');
        header('Location: ' . url('admin/staff-create.php'));
        exit;
    }

    $setupLink = absolute_url('staff-setup.php?token=' . urlencode($token));
    $sent      = sendStaffInviteEmail($email, $permissions, $setupLink);

    if ($sent) {
        flash('success', 'Invitation email sent to ' . sanitize($email) . '.');
    } else {
        flash('warning', 'Invite created but email could not be sent. Share this setup link manually: ' . $setupLink);
    }

    header('Location: ' . url('admin/staff.php'));
    exit;
}

require_once __DIR__ . '/../includes/header.php';
?>

<div class="page-header">
    <h2>Invite Staff Member</h2>
    <p>Enter the staff member's email address. They'll receive a link to complete their own account setup.</p>
</div>

<?php if ($err = flash('error')): ?>
    <div class="alert alert-error" style="margin-bottom:20px;">✕ <?= sanitize($err) ?></div>
<?php endif; ?>

<div class="card" style="max-width:700px;">
    <div class="card-header"><span class="card-title">Staff Invitation</span></div>
    <div class="card-body">
        <form method="POST" action="">
            <input type="hidden" name="action" value="send_invite">

            <div class="form-group">
                <label class="form-label" for="invite_email">Staff Email Address <span class="req">*</span></label>
                <input type="email" id="invite_email" name="email" class="form-control" required
                       placeholder="staff@example.com"
                       value="<?= sanitize($_POST['email'] ?? '') ?>">
                <p class="text-sm text-secondary" style="margin-top:6px;">
                    An invitation link will be sent to this address. The staff member will set their own name, username, and password.
                </p>
            </div>

            <div class="form-group" style="margin-top:1.5rem;">
                <label class="form-label">Permissions</label>
                <p class="text-sm text-secondary" style="margin-bottom:12px;">Select which modules this staff member can access.</p>
                <div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(290px,1fr));gap:12px;">
                    <?php foreach ($allPermissions as $key => $info): ?>
                    <label style="display:flex;align-items:flex-start;gap:12px;padding:14px;border:1.5px solid var(--border);border-radius:12px;cursor:pointer;transition:border-color .2s;"
                           onmouseover="this.style.borderColor='var(--accent)'" onmouseout="this.style.borderColor='var(--border)'">
                        <input type="checkbox" name="perms[<?= $key ?>]" value="1"
                               style="margin-top:3px;accent-color:var(--accent);width:16px;height:16px;flex-shrink:0;">
                        <div>
                            <div style="font-size:0.875rem;font-weight:600;color:var(--text-primary);margin-bottom:3px;"><?= htmlspecialchars($info['label']) ?></div>
                            <div style="font-size:0.78rem;color:var(--text-secondary);line-height:1.5;"><?= htmlspecialchars($info['desc']) ?></div>
                        </div>
                    </label>
                    <?php endforeach; ?>
                </div>
            </div>

            <div style="display:flex;gap:10px;align-items:center;margin-top:1.5rem;">
                <button type="submit" class="btn btn-accent">Send Invitation</button>
                <a href="<?= url('admin/staff.php') ?>" class="btn btn-ghost">Cancel</a>
            </div>
        </form>
    </div>
</div>


<?php require_once __DIR__ . '/../includes/footer.php'; ?>
