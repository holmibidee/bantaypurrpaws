<?php
/**
 * Shared notification bell + dropdown (include from header on all app pages).
 */
require_once __DIR__ . '/notifications.php';

$notifCount = 0;
$notifs     = [];

try {
    $notifCount = getUnreadNotificationCountForSessionUser();
    $notifs     = getNotificationsForSessionUser(6);
} catch (Throwable $e) {
    error_log('notification-bell: ' . $e->getMessage());
}

$notifFallback = function_exists('dashboardHomeUrl') ? dashboardHomeUrl() : url('dashboard.php');
?>
<div class="notification-wrap">
    <button type="button" class="notification-bell" id="notificationBell" aria-label="Notifications" aria-expanded="false" aria-controls="notificationDropdown">
        <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
            <path d="M18 8A6 6 0 0 0 6 8c0 7-3 9-3 9h18s-3-2-3-9"/>
            <path d="M13.73 21a2 2 0 0 1-3.46 0"/>
        </svg>
        <?php if ($notifCount > 0): ?>
            <span class="notification-count notif-badge"><?= $notifCount > 99 ? '99+' : $notifCount ?></span>
        <?php endif; ?>
    </button>
    <div class="notification-dropdown" id="notificationDropdown" role="menu">
        <div class="notification-dropdown-header">
            <span class="notif-header-title">Notifications</span>
            <?php if ($notifCount > 0): ?>
            <button type="button" class="notif-mark-all" onclick="headerMarkAllRead()">Mark all read</button>
            <?php endif; ?>
        </div>
        <?php if (empty($notifs)): ?>
            <div class="notification-empty">No notifications yet.</div>
        <?php else: ?>
            <?php foreach ($notifs as $n):
                $notifLink = !empty($n['link_url']) ? url($n['link_url']) : $notifFallback;
                $icon      = notificationIcon($n['notification_type'] ?? 'system');
                $unread    = notificationIsUnread($n);
            ?>
                <a href="<?= $notifLink ?>"
                   class="notification-item <?= $unread ? 'unread' : '' ?>"
                   role="menuitem"
                   data-notification-id="<?= (int) $n['id'] ?>"
                   onclick="headerMarkRead(<?= (int) $n['id'] ?>, this)">
                    <span class="notification-item-icon"><?= html_entity_decode($icon, ENT_QUOTES | ENT_HTML5, 'UTF-8') ?></span>
                    <span class="notification-item-body">
                        <?= sanitize($n['message']) ?>
                        <time><?= timeAgo($n['created_at']) ?></time>
                    </span>
                </a>
            <?php endforeach; ?>
            <a href="<?= url('notifications.php') ?>" class="notification-item notif-view-all">
                View all notifications &rarr;
            </a>
        <?php endif; ?>
    </div>
</div>
