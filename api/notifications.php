<?php
/**
 * BantayPurrPaws — Notifications API
 */

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/notifications.php';

header('Content-Type: application/json');
requireLogin();

$action = $_GET['action'] ?? $_POST['action'] ?? 'list';

try {
    switch ($action) {

        case 'count':
            echo json_encode([
                'success' => true,
                'count'   => getUnreadNotificationCountForSessionUser(),
            ]);
            break;

        case 'list':
            $limit = min((int) ($_GET['limit'] ?? 20), 50);
            $rows  = getNotificationsForSessionUser($limit);

            $items = array_map(static function ($row) {
                $type = $row['notification_type'] ?? 'system';
                $typeIcons = [
                    'adoption'     => '🐾',
                    'report'       => '🚨',
                    'announcement' => '📢',
                    'system'       => '🔔',
                    'otp'          => '🔐',
                ];
                return [
                    'id'       => $row['id'],
                    'type'     => $type,
                    'icon'     => $typeIcons[$type] ?? '🔔',
                    'message'  => $row['message'],
                    'link_url' => $row['link_url'] ?? null,
                    'is_read'  => !notificationIsUnread($row),
                    'time_ago' => timeAgo($row['created_at']),
                    'created'  => $row['created_at'],
                ];
            }, $rows);

            echo json_encode(['success' => true, 'notifications' => $items]);
            break;

        case 'mark_read':
            $id = (int) ($_POST['id'] ?? 0);
            $ok = $id > 0 && markNotificationRead($id);
            echo json_encode(['success' => $ok]);
            break;

        case 'mark_all_read':
            markAllNotificationsReadForSessionUser();
            echo json_encode(['success' => true]);
            break;

        default:
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'Unknown action.']);
    }

} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Server error.']);
}
