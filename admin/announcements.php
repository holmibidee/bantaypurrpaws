<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/notifications.php';
require_once __DIR__ . '/../includes/logger.php';

requireCanPostAnnouncements();

$pageTitle = 'Announcements';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $message = trim($_POST['message'] ?? '');
    $linkUrl = trim($_POST['link_url'] ?? '');

    if ($message === '') {
        flash('error', 'Please enter an announcement message.');
    } else {
        try {
            $result = createAnnouncement($message, $linkUrl !== '' ? $linkUrl : null);
            $count  = (int) ($result['notifications'] ?? 0);
            $emails = (int) ($result['emails'] ?? 0);

            if ($count > 0) {
                $msg = "Announcement delivered to {$count} account(s)";
                if ($emails > 0) {
                    $msg .= " and emailed to {$emails} recipient(s)";
                } elseif (!empty($result['errors'])) {
                    $msg .= '. Some emails could not be sent — check logs/app.log for details.';
                }
                $msg .= '. Administrators will not receive in-app notifications (they post them).';
                flash('success', $msg);
            } elseif (in_array('no_users', $result['errors'] ?? [], true)) {
                flash('error', 'No user accounts are registered to receive this announcement.');
            } else {
                $registered = countRegisteredEndUsers();
                bpp_log('announcements', 'error', 'Announcement failed for all users.', [
                    'registered_users' => $registered,
                    'errors'           => $result['errors'] ?? [],
                ]);
                if ($registered > 0) {
                    flash('error', 'Could not deliver the announcement. The notifications table may need updating — check logs/app.log.');
                } else {
                    flash('error', 'No user accounts are registered to receive this announcement.');
                }
            }
            header('Location: ' . url('admin/announcements.php'));
            exit;
        } catch (Throwable $e) {
            bpp_log('announcements', 'error', 'Announcement post exception.', ['error' => $e->getMessage()]);
            flash('error', 'Could not post announcement. Please try again.');
        }
    }
}

require_once __DIR__ . '/../includes/header.php';
?>

<div class="dashboard-hero">
    <div>
        <p class="page-eyebrow">System</p>
        <h2>Announcements</h2>
        <p>Post updates for all registered accounts (users and staff). Each recipient receives an in-app notification and an email copy.</p>
    </div>
    <span class="role-chip role-administrator">Administrator</span>
</div>

<div class="card" style="max-width:640px">
    <div class="card-header">
        <span class="card-title">New announcement</span>
    </div>
    <div class="card-body">
        <form method="POST" action="">
            <div class="form-group">
                <label class="form-label" for="message">Message <span class="req">*</span></label>
                <textarea id="message" name="message" class="form-control" rows="4" required
                          placeholder="e.g. Shelter hours updated this weekend…"><?= sanitize($_POST['message'] ?? '') ?></textarea>
            </div>
            <div class="form-group">
                <label class="form-label" for="link_url">Link (optional)</label>
                <input type="text" id="link_url" name="link_url" class="form-control"
                       placeholder="adoption.php or announcements.php"
                       value="<?= sanitize($_POST['link_url'] ?? '') ?>">
                <p class="text-sm text-muted mt-2">Relative path within the site. Users can also read all posts on their Announcements page.</p>
            </div>
            <button type="submit" class="btn btn-accent">Post to all users &amp; staff</button>
        </form>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
