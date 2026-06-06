<?php
/**
 * BantayPurrPaws — Notifications Helper (MySQL)
 */

require_once __DIR__ . '/db.php';
require_once __DIR__ . '/paths.php';
require_once __DIR__ . '/logger.php';

// ── Schema ─────────────────────────────────────────────────

/**
 * Ensure notifications table supports announcements, reports, and nullable application_id.
 */
function ensureNotificationSchema(): void {
    static $checked = false;
    if ($checked) {
        return;
    }
    $checked = true;

    try {
        $pdo      = getDB();
        $existing = [];
        foreach ($pdo->query('SHOW COLUMNS FROM `notifications`')->fetchAll(PDO::FETCH_ASSOC) as $col) {
            $existing[$col['Field']] = $col;
        }

        if (!isset($existing['user_id'])) {
            $pdo->exec(
                'ALTER TABLE `notifications`
                 ADD COLUMN `user_id` INT NULL DEFAULT NULL AFTER `application_id`,
                 ADD CONSTRAINT `fk_notif_user` FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE SET NULL'
            );
        }

        if (!isset($existing['notification_type'])) {
            $pdo->exec(
                "ALTER TABLE `notifications`
                 ADD COLUMN `notification_type` VARCHAR(32) NOT NULL DEFAULT 'adoption' AFTER `user_id`"
            );
        }

        if (!isset($existing['link_url'])) {
            $pdo->exec(
                'ALTER TABLE `notifications`
                 ADD COLUMN `link_url` VARCHAR(512) DEFAULT NULL AFTER `message`'
            );
        }

        if (isset($existing['application_id']) && strtoupper($existing['application_id']['Null'] ?? '') === 'NO') {
            $pdo->exec('ALTER TABLE `notifications` MODIFY COLUMN `application_id` INT NULL DEFAULT NULL');
        }
    } catch (Throwable $e) {
        bpp_log('notifications', 'error', 'ensureNotificationSchema failed.', ['error' => $e->getMessage()]);
    }
}

// ── Role-aware filtering ────────────────────────────────────

function isAnnouncementNotification(array $row): bool {
    return ($row['notification_type'] ?? '') === 'announcement';
}

function isAdminOperationalNotification(array $row): bool {
    return in_array($row['notification_type'] ?? '', ['adoption', 'report'], true);
}

/**
 * Notifications visible in the header/API for the current session.
 */
function getNotificationsForSessionUser(int $limit = 20): array {
    ensureNotificationSchema();
    startSession();
    $role   = $_SESSION['role'] ?? 'user';
    $userId = (int) ($_SESSION['user_id'] ?? 0);

    if ($role === 'user') {
        $rows = db_select(
            'notifications',
            'user_id=eq.' . $userId . '&order=created_at.desc&limit=' . min($limit * 2, 100)
        );
    } else {
        $rows = db_select(
            'notifications',
            'notification_type=in.(adoption,report)&order=created_at.desc&limit=' . min($limit * 3, 150)
        );
    }

    return enrichNotificationRows(array_slice($rows, 0, $limit));
}

function getUnreadNotificationCountForSessionUser(): int {
    $rows = getNotificationsForSessionUser(100);
    $unread = 0;
    foreach ($rows as $row) {
        if (notificationIsUnread($row)) {
            $unread++;
        }
    }
    return $unread;
}

/** @deprecated Use getUnreadNotificationCountForSessionUser() */
function getUnreadNotificationCount(?int $userId = null): int {
    return getUnreadNotificationCountForSessionUser();
}

/** @deprecated Use getNotificationsForSessionUser() */
function getRecentNotifications(int $limit = 20, ?int $userId = null): array {
    return getNotificationsForSessionUser($limit);
}

function enrichNotificationRows(array $rows): array {
    foreach ($rows as &$row) {
        if (empty($row['link_url'])) {
            $type = $row['notification_type'] ?? 'system';
            if ($type === 'adoption' && !empty($row['application_id'])) {
                $row['link_url'] = 'admin/application.php?id=' . $row['application_id'];
            } elseif ($type === 'report') {
                $row['link_url'] = 'admin/reports.php';
            } elseif ($type === 'announcement') {
                $row['link_url'] = 'announcements.php';
            }
        }
    }
    unset($row);
    return $rows;
}

// ── Create notifications ──────────────────────────────────

function createSystemNotification(
    string  $type,
    string  $message,
    ?string $linkUrl       = null,
    ?int    $applicationId = null,
    ?int    $userId        = null
): bool {
    ensureNotificationSchema();

    try {
        $data = [
            'user_id'           => $userId,
            'notification_type' => $type,
            'message'           => mb_substr(trim($message), 0, 255),
            'link_url'          => $linkUrl,
            'is_read'           => 0,
        ];
        if ($applicationId !== null) {
            $data['application_id'] = $applicationId;
        }

        $row = db_insert('notifications', $data);
        if (!$row) {
            bpp_log('notifications', 'error', 'Failed to insert notification.', [
                'type'    => $type,
                'message' => $message,
            ]);
            return false;
        }

        bpp_log('notifications', 'info', 'Notification created.', [
            'type' => $type,
            'id'   => $row['id'] ?? null,
        ]);
        return true;
    } catch (Throwable $e) {
        bpp_log('notifications', 'error', 'createSystemNotification exception.', [
            'type'  => $type,
            'error' => $e->getMessage(),
        ]);
        return false;
    }
}

function createReportNotification(int $reportId, string $reportCode, string $reporterName): bool {
    return createSystemNotification(
        'report',
        trim($reporterName) . ' submitted rescue report ' . trim($reportCode),
        'admin/reports.php?q=' . urlencode($reportCode),
        null,
        null
    );
}

/**
 * Post an announcement to every end-user account (not admin/staff).
 *
 * @return array{notifications: int, emails: int, errors: string[]}
 */
function createAnnouncement(string $message, ?string $linkUrl = null): array {
    ensureNotificationSchema();
    require_once __DIR__ . '/sensitive-data.php';
    require_once __DIR__ . '/mailer.php';

    $message = trim($message);
    if ($message === '') {
        return ['notifications' => 0, 'emails' => 0, 'errors' => ['Message is empty.']];
    }

    // Include both users AND staff in announcements
    $users = db_select('users', 'role=in.(user,staff)&order=id.asc');
    if ($users === []) {
        bpp_log('announcements', 'warning', 'No user/staff accounts found for announcement.');
        return ['notifications' => 0, 'emails' => 0, 'errors' => ['no_users']];
    }

    $linkUrl       = $linkUrl ?: 'announcements.php';
    $notifCount    = 0;
    $emailCount    = 0;
    $errors        = [];

    foreach ($users as $user) {
        $userId = (int) $user['id'];
        $row    = db_insert('notifications', [
            'application_id'    => null,
            'user_id'           => $userId,
            'notification_type' => 'announcement',
            'message'           => mb_substr($message, 0, 255),
            'link_url'          => $linkUrl,
            'is_read'           => 0,
        ]);

        if ($row) {
            $notifCount++;
        } else {
            $errors[] = 'notification_user_' . $userId;
            bpp_log('announcements', 'error', 'Failed to insert announcement notification.', ['user_id' => $userId]);
        }

        $hydrated = hydrateUserSensitiveFields($user);
        $email    = $hydrated['email'] ?? '';
        $name     = $hydrated['full_name'] ?? 'User';

        if ($email !== '' && filter_var($email, FILTER_VALIDATE_EMAIL)) {
            if (sendAnnouncementEmail($email, $name, $message, $linkUrl)) {
                $emailCount++;
            } else {
                $errors[] = 'email_user_' . $userId;
            }
        } else {
            $errors[] = 'invalid_email_user_' . $userId;
        }
    }

    bpp_log('announcements', 'info', 'Announcement posted.', [
        'notifications' => $notifCount,
        'emails'        => $emailCount,
        'user_total'    => count($users),
        'errors'        => count($errors),
    ]);

    return ['notifications' => $notifCount, 'emails' => $emailCount, 'errors' => $errors];
}

/**
 * All announcements for a specific user (announcements page).
 */
function getUserAnnouncements(int $userId, int $limit = 50): array {
    ensureNotificationSchema();
    return enrichNotificationRows(
        db_select(
            'notifications',
            'user_id=eq.' . $userId
            . '&notification_type=eq.announcement'
            . '&order=created_at.desc'
            . '&limit=' . $limit
        ) ?: []
    );
}

// ── Mark as read ──────────────────────────────────────────

function notificationBelongsToSessionUser(array $row): bool {
    startSession();
    $role   = $_SESSION['role'] ?? 'user';
    $userId = (int) ($_SESSION['user_id'] ?? 0);

    // Users and staff can mark their own notifications as read
    if (in_array($role, ['user', 'staff'], true)) {
        return (int) ($row['user_id'] ?? 0) === $userId;
    }

    return isAdminOperationalNotification($row);
}

function markNotificationRead(int $notificationId): bool {
    ensureNotificationSchema();
    $row = db_select('notifications', 'id=eq.' . $notificationId . '&limit=1', true);
    if (!$row || !notificationBelongsToSessionUser($row)) {
        bpp_log('notifications', 'warning', 'Unauthorized mark read attempt.', ['id' => $notificationId]);
        return false;
    }

    db_update('notifications', ['is_read' => 1], 'id=eq.' . $notificationId);
    return true;
}

function markAllNotificationsReadForSessionUser(): void {
    ensureNotificationSchema();
    startSession();
    $role   = $_SESSION['role'] ?? 'user';
    $userId = (int) ($_SESSION['user_id'] ?? 0);

    if ($role === 'user') {
        db_update('notifications', ['is_read' => 1], 'user_id=eq.' . $userId);
        return;
    }

    $rows = db_select('notifications', 'notification_type=in.(adoption,report)&is_read=eq.false');
    foreach ($rows as $row) {
        markNotificationRead((int) $row['id']);
    }
}

/** @deprecated */
function markAllNotificationsRead(?int $userId = null): void {
    markAllNotificationsReadForSessionUser();
}

function markNotificationsReadForApplication(int $applicationId): void {
    db_update('notifications', ['is_read' => 1], 'application_id=eq.' . $applicationId);
}

function notificationIcon(string $type): string {
    return match ($type) {
        'adoption'     => '&#128062;',
        'report'       => '&#128680;',
        'announcement' => '&#128227;',
        'otp'          => '&#128272;',
        default        => '&#128276;',
    };
}

function notificationIsUnread(array $row): bool {
    return empty($row['is_read']) || $row['is_read'] === '0' || $row['is_read'] === false;
}

function countRegisteredEndUsers(): int {
    return db_count('users', 'role=in.(user,staff)');
}
