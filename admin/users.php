<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/users.php';

requireCanManageAccounts();

$pageTitle     = 'Manage User Accounts';
$useSweetAlert = true;
$me            = currentUser();
$isSiteAdmin   = isAdministrator();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'promote_user' && $isSiteAdmin) {
        $targetId = (int) ($_POST['user_id'] ?? 0);
        $result   = promoteUserToStaff($targetId, $me);
        flash($result['ok'] ? 'success' : 'error', $result['ok'] ? 'User promoted to staff successfully.' : $result['error']);

        header('Location: ' . url('admin/users.php'));
        exit;
    }

    if ($action === 'delete_user' && $isSiteAdmin) {
        $targetId = (int) ($_POST['user_id'] ?? 0);
        $target   = getUserById($targetId);

        if (!$target || ($target['role'] ?? '') !== 'user') {
            flash('error', 'Only regular user accounts can be deleted from this page.');
        } else {
            $result = deleteUserAccount($targetId, $me);
            flash($result['ok'] ? 'success' : 'error', $result['ok'] ? 'User account deleted successfully.' : $result['error']);
        }

        header('Location: ' . url('admin/users.php'));
        exit;
    }
}

$users   = array_map('hydrateUserSensitiveFields', db_select('users', 'role=eq.user&order=created_at.desc'));
$reports = db_select('rescue_reports', 'select=reporter_id');

$reportCounts = [];
foreach ($reports as $r) {
    $rid = $r['reporter_id'];
    $reportCounts[$rid] = ($reportCounts[$rid] ?? 0) + 1;
}

$users = array_map(function ($u) use ($reportCounts) {
    $u['report_count'] = $reportCounts[$u['id']] ?? 0;
    return $u;
}, $users);

require_once __DIR__ . '/../includes/header.php';
?>

<div class="page-header">
    <h2>Manage User Accounts</h2>
    <p>View and manage registered reporter accounts.</p>
</div>

<div class="card">
    <div class="card-header">
        <span class="card-title"><?= count($users) ?> Registered Users</span>
    </div>
    <div class="table-wrapper table-responsive-stack">
        <table>
            <thead>
                <tr>
                    <th>Name</th><th>Email</th><th>Reports</th><th>Joined</th>
                    <?php if ($isSiteAdmin): ?>
                    <th>Actions</th>
                    <?php endif; ?>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($users)): ?>
                <tr>
                    <td colspan="<?= $isSiteAdmin ? 5 : 4 ?>" class="text-secondary text-center" style="padding:2rem;">No user accounts yet.</td>
                </tr>
                <?php else: ?>
                <?php foreach ($users as $u): ?>
                <tr>
                    <td>
                        <div class="flex items-center gap-2">
                            <?php $pic = userHasProfilePicture($u) ? profilePictureUrl($u) : ''; ?>
                            <?php if ($pic): ?>
                                <img src="<?= $pic ?>" alt="" style="width:28px;height:28px;border-radius:50%;object-fit:cover;flex-shrink:0;">
                            <?php else: ?>
                                <div class="user-avatar" style="width:28px;height:28px;font-size:0.7rem;flex-shrink:0;">
                                    <?= strtoupper(substr($u['full_name'], 0, 2)) ?>
                                </div>
                            <?php endif; ?>
                            <?= sanitize($u['full_name']) ?>
                        </div>
                    </td>
                    <td class="text-secondary"><?= sanitize($u['email']) ?></td>
                    <td class="text-secondary"><?= $u['report_count'] ?></td>
                    <td class="text-secondary text-sm"><?= date('M j, Y', strtotime($u['created_at'])) ?></td>

                    <?php if ($isSiteAdmin): ?>
                    <td>
                        <div class="flex items-center gap-2">
                            <?php if ((int)$u['id'] !== (int)$me['id']): ?>

                            <form method="POST" action="" class="delete-user-form" style="display:inline;">
                                <input type="hidden" name="action" value="delete_user">
                                <input type="hidden" name="user_id" value="<?= (int) $u['id'] ?>">
                                <button type="button" class="btn btn-ghost btn-sm btn-delete-user"
                                        data-name="<?= sanitize($u['full_name']) ?>">Delete</button>
                            </form>
                            <?php else: ?>
                            <span class="text-secondary text-sm" style="font-style:italic;">You</span>
                            <?php endif; ?>
                        </div>
                    </td>
                    <?php endif; ?>
                </tr>
                <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<script>
document.querySelectorAll('.btn-promote-user').forEach(function (btn) {
    btn.addEventListener('click', function () {
        const form = btn.closest('form');
        const name = btn.dataset.name || 'this user';
        const message = 'Promote ' + name + ' to staff? They will gain staff access to the admin area.';
        if (typeof Swal !== 'undefined') {
            Swal.fire({
                title: 'Promote to staff?',
                text: message,
                icon: 'question',
                showCancelButton: true,
                confirmButtonColor: '#7c6f5b',
                cancelButtonColor: '#78716c',
                confirmButtonText: 'Promote'
            }).then(function (r) { if (r.isConfirmed) form.submit(); });
        } else if (confirm(message)) {
            form.submit();
        }
    });
});

document.querySelectorAll('.btn-delete-user').forEach(function (btn) {
    btn.addEventListener('click', function () {
        const form = btn.closest('form');
        const message = 'Are you sure you want to delete this user account?';
        if (typeof Swal !== 'undefined') {
            Swal.fire({
                title: 'Delete user account?',
                text: message,
                icon: 'warning',
                showCancelButton: true,
                confirmButtonColor: '#ef4444',
                cancelButtonColor: '#78716c',
                confirmButtonText: 'Delete'
            }).then(function (r) { if (r.isConfirmed) form.submit(); });
        } else if (confirm(message)) {
            form.submit();
        }
    });
});
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
