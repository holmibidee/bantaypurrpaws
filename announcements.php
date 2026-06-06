<?php
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/notifications.php';

requireLogin();

if (isAdmin()) {
    header('Location: ' . url('admin/announcements.php'));
    exit;
}
// Staff can view the announcements page

$pageTitle = 'Announcements';
$user      = currentUser();
$items     = getUserAnnouncements((int) $user['id'], 50);

require_once __DIR__ . '/includes/header.php';
?>

<div class="page-header">
    <h2>Announcements</h2>
    <p>Official updates posted by the BantayPurrPaws administration team.</p>
</div>

<div class="card">
    <div class="card-header">
        <span class="card-title"><?= count($items) ?> announcement<?= count($items) === 1 ? '' : 's' ?></span>
    </div>
    <?php if (empty($items)): ?>
        <div class="empty-state">
            <div class="empty-icon">📢</div>
            <h3>No announcements yet</h3>
            <p>When administrators post updates, they will appear here and in your notifications.</p>
        </div>
    <?php else: ?>
        <div class="announcement-list">
            <?php foreach ($items as $item):
                $unread = notificationIsUnread($item);
            ?>
            <article class="announcement-item <?= $unread ? 'unread' : '' ?>">
                <div class="announcement-item-head">
                    <span class="announcement-badge">Announcement</span>
                    <?php if ($unread): ?><span class="announcement-new">New</span><?php endif; ?>
                    <time class="text-sm text-secondary"><?= date('M j, Y — g:i A', strtotime($item['created_at'])) ?></time>
                </div>
                <p class="announcement-message"><?= nl2br(sanitize($item['message'])) ?></p>
                <?php if (!empty($item['link_url']) && $item['link_url'] !== 'announcements.php'): ?>
                <a href="<?= url($item['link_url']) ?>" class="btn btn-ghost btn-sm">Learn more</a>
                <?php endif; ?>
            </article>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</div>

<?php if (!empty($items)): ?>
<script>
document.addEventListener('DOMContentLoaded', function () {
    const ids = <?= json_encode(array_map(static fn ($i) => (int) $i['id'], array_filter($items, static fn ($i) => notificationIsUnread($i)))) ?>;
    if (!ids.length) return;
    ids.forEach(function (id) {
        fetch('<?= url('api/notifications.php') ?>', {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: 'action=mark_read&id=' + id
        });
    });
});
</script>
<?php endif; ?>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
