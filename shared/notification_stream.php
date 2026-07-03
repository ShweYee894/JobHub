<?php

/**
 * notification_stream.php
 * Server-Sent Events endpoint for real-time notifications.
 *
 * Usage: connect with an EventSource that passes no user_id parameter;
 *        the session user_id is used automatically.
 *
 * GET /finalproject/shared/notification_stream.php
 */

header('Content-Type: text/event-stream');
header('Cache-Control: no-cache');
header('Connection: keep-alive');
header('X-Accel-Buffering: no');

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (empty($_SESSION['user_id'])) {
    http_response_code(401);
    echo "event: error\ndata: {\"message\":\"Unauthorized\"}\n\n";
    exit;
}

require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/notifications.php';

$userId = (int) $_SESSION['user_id'];
$notificationService = new PlatformNotificationService($conn);

$lastId = 0;
$heartbeatInterval = 15;
$lastHeartbeat = time();

// Set a long execution timeout for SSE
if (function_exists('set_time_limit')) {
    set_time_limit(0);
}

// Ignore user abort so we can send the disconnect event
ignore_user_abort(false);

while (true) {
    if (connection_aborted()) {
        break;
    }

    $now = time();

    // Fetch notifications newer than the last seen
    $stmt = $conn->prepare(
        'SELECT id, type, title, message, link, is_read, created_at
         FROM notifications
         WHERE user_id = ? AND id > ?
         ORDER BY created_at DESC'
    );
    $stmt->bind_param('ii', $userId, $lastId);
    $stmt->execute();
    $result = $stmt->get_result();

    while ($row = $result->fetch_assoc()) {
        $data = json_encode([
            'id'         => (int) $row['id'],
            'type'       => $row['type'],
            'title'      => $row['title'],
            'message'    => $row['message'],
            'link'       => $row['link'],
            'is_read'    => (int) $row['is_read'],
            'created_at' => $row['created_at'],
        ]);

        echo "id: {$row['id']}\n";
        echo "event: notification\n";
        echo "data: $data\n\n";

        if ((int) $row['id'] > $lastId) {
            $lastId = (int) $row['id'];
        }

        if (ob_get_level() > 0) {
            ob_flush();
        }
        flush();
    }
    $stmt->close();

    // Send heartbeat every $heartbeatInterval seconds
    if ($now - $lastHeartbeat >= $heartbeatInterval) {
        $unread = $notificationService->getUnreadCount($userId);
        $heartbeatData = json_encode(['unread_count' => $unread, 'time' => date('c')]);

        echo "event: heartbeat\n";
        echo "data: $heartbeatData\n\n";

        if (ob_get_level() > 0) {
            ob_flush();
        }
        flush();

        $lastHeartbeat = $now;
    }

    sleep(3);
}
