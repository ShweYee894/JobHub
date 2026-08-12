<?php

/**
 * notifications_api.php
 * AJAX endpoint for the notification bell dropdown.
 *
 * GET  ?action=list&limit=15          – fetch notifications + unread count
 * POST ?action=mark_read&id=<id>      – mark single notification read
 * POST ?action=mark_all_read          – mark all as read
 */

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

header('Content-Type: application/json');

if (empty($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/notifications.php';

$userId = (int) $_SESSION['user_id'];

// ── Release session lock ────────────────────────────────────────────────────
define('SESSION_CLOSED', true);
session_write_close();

$ns = new PlatformNotificationService($conn);

$action = $_GET['action'] ?? $_POST['action'] ?? '';

switch ($action) {
    case 'list':
        $limit = min(50, max(1, (int) ($_GET['limit'] ?? 15)));
        $offset = max(0, (int) ($_GET['offset'] ?? 0));
        $notifications = $ns->getForUser($userId, $limit, $offset);
        $unreadCount = $ns->getUnreadCount($userId);

        echo json_encode([
            'notifications'  => $notifications,
            'unread_count'   => $unreadCount,
        ]);
        break;

    case 'mark_read':
        $id = (int) ($_GET['id'] ?? 0);
        if ($id > 0) {
            $ns->markRead($id, $userId);
        }
        echo json_encode(['success' => true]);
        break;

    case 'mark_all_read':
        $ns->markAllRead($userId);
        echo json_encode(['success' => true]);
        break;

    case 'mark_unread':
        $id = (int) ($_POST['id'] ?? 0);
        if ($id > 0) {
            $ns->markUnread($id, $userId);
        }
        echo json_encode(['success' => true]);
        break;

    case 'delete':
        $id = (int) ($_POST['id'] ?? 0);
        if ($id > 0) {
            $ns->delete($id, $userId);
        }
        echo json_encode(['success' => true]);
        break;

    case 'unread_count':
        echo json_encode(['unread_count' => $ns->getUnreadCount($userId)]);
        break;

    default:
        http_response_code(400);
        echo json_encode(['error' => 'Invalid action']);
        break;
}
