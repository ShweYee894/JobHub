<?php
/**
 * chat_api.php – Multi-endpoint AJAX handler for the chat system.
 *
 * Endpoints:
 *   GET  ?action=get_rooms       – List all rooms for current user
 *   GET  ?action=get_messages    – Fetch messages after last_id
 *   POST ?action=send_message    – Send a new message (requires CSRF)
 *   GET  ?action=unread_count    – Total unread count
 */

session_start();
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/helpers.php';

header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    json_response(['success' => false, 'error' => 'Unauthorized'], 401);
}

$userId = (int) $_SESSION['user_id'];
$action = $_GET['action'] ?? $_POST['action'] ?? '';

switch ($action) {

    // ── GET ROOMS ─────────────────────────────────────────────────────
    case 'get_rooms':
        $stmt = $conn->prepare("
            SELECT
                cr.id AS room_id,
                cr.created_at AS room_created,
                j.title AS job_title,
                CASE
                    WHEN ct.client_id = ? THEN cu.name
                    ELSE fu.name
                END AS other_name,
                CASE
                    WHEN ct.client_id = ? THEN cu.profile_image
                    ELSE fu.profile_image
                END AS other_image,
                (
                    SELECT cm.message_text
                    FROM chat_messages cm
                    WHERE cm.room_id = cr.id
                    ORDER BY cm.created_at DESC
                    LIMIT 1
                ) AS last_message,
                (
                    SELECT cm.created_at
                    FROM chat_messages cm
                    WHERE cm.room_id = cr.id
                    ORDER BY cm.created_at DESC
                    LIMIT 1
                ) AS last_message_time,
                (
                    SELECT COUNT(*)
                    FROM chat_messages cm
                    WHERE cm.room_id = cr.id
                      AND cm.sender_id != ?
                      AND cm.is_read = 0
                ) AS unread_count
            FROM chat_rooms cr
            JOIN contracts ct ON cr.contract_id = ct.id
            JOIN jobs j ON ct.job_id = j.id
            LEFT JOIN users cu ON ct.client_id = cu.id
            LEFT JOIN users fu ON ct.freelancer_id = fu.id
            WHERE ct.client_id = ? OR ct.freelancer_id = ?
            ORDER BY last_message_time DESC, cr.created_at DESC
        ");
        $stmt->bind_param('iiii', $userId, $userId, $userId, $userId);
        $stmt->execute();
        $result = $stmt->get_result();
        $rooms = [];
        while ($row = $result->fetch_assoc()) {
            $rooms[] = [
                'room_id'          => (int) $row['room_id'],
                'other_name'       => $row['other_name'] ?? 'Unknown',
                'other_image'      => $row['other_image'] ?? null,
                'job_title'        => $row['job_title'] ?? '',
                'last_message'     => $row['last_message'] ?? null,
                'last_message_time'=> $row['last_message_time'] ?? $row['room_created'],
                'unread_count'     => (int) $row['unread_count'],
            ];
        }
        $stmt->close();
        json_response(['success' => true, 'rooms' => $rooms]);
        break;

    // ── GET MESSAGES ──────────────────────────────────────────────────
    case 'get_messages':
        $roomId = sanitize_int($_GET['room_id'] ?? 0);
        $lastId = sanitize_int($_GET['last_id'] ?? 0);

        if ($roomId <= 0) {
            json_response(['success' => false, 'error' => 'Invalid room_id'], 400);
        }

        // Verify access
        if (!_hasRoomAccess($conn, $roomId, $userId)) {
            json_response(['success' => false, 'error' => 'Access denied'], 403);
        }

        if ($lastId > 0) {
            $stmt = $conn->prepare("
                SELECT cm.id, cm.room_id, cm.sender_id, cm.message_text, cm.created_at, cm.is_read,
                       u.name AS sender_name, u.profile_image AS sender_image
                FROM chat_messages cm
                JOIN users u ON cm.sender_id = u.id
                WHERE cm.room_id = ? AND cm.id > ?
                ORDER BY cm.created_at ASC
            ");
            $stmt->bind_param('ii', $roomId, $lastId);
        } else {
            $stmt = $conn->prepare("
                SELECT cm.id, cm.room_id, cm.sender_id, cm.message_text, cm.created_at, cm.is_read,
                       u.name AS sender_name, u.profile_image AS sender_image
                FROM chat_messages cm
                JOIN users u ON cm.sender_id = u.id
                WHERE cm.room_id = ?
                ORDER BY cm.created_at ASC
            ");
            $stmt->bind_param('i', $roomId);
        }
        $stmt->execute();
        $result = $stmt->get_result();
        $messages = [];
        while ($row = $result->fetch_assoc()) {
            $messages[] = [
                'id'            => (int) $row['id'],
                'sender_id'     => (int) $row['sender_id'],
                'sender_name'   => $row['sender_name'],
                'sender_image'  => $row['sender_image'] ?? null,
                'message_text'  => $row['message_text'],
                'created_at'    => $row['created_at'],
            ];
        }
        $stmt->close();

        // Mark as read
        $ur = $conn->prepare("UPDATE chat_messages SET is_read = 1 WHERE room_id = ? AND sender_id != ? AND is_read = 0");
        $ur->bind_param('ii', $roomId, $userId);
        $ur->execute();
        $ur->close();

        json_response(['success' => true, 'messages' => $messages]);
        break;

    // ── SEND MESSAGE ──────────────────────────────────────────────────
    case 'send_message':
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            json_response(['success' => false, 'error' => 'POST required'], 405);
        }

        if (!verify_csrf_token()) {
            json_response(['success' => false, 'error' => 'Invalid CSRF token'], 403);
        }

        $roomId = sanitize_int($_POST['room_id'] ?? 0);
        $messageText = trim($_POST['message_text'] ?? '');

        if ($roomId <= 0) {
            json_response(['success' => false, 'error' => 'Invalid room_id'], 400);
        }
        if (empty($messageText)) {
            json_response(['success' => false, 'error' => 'Message cannot be empty'], 400);
        }
        if (mb_strlen($messageText) > 5000) {
            json_response(['success' => false, 'error' => 'Message too long (max 5000 characters)'], 400);
        }

        if (!_hasRoomAccess($conn, $roomId, $userId)) {
            json_response(['success' => false, 'error' => 'Access denied'], 403);
        }

        $stmt = $conn->prepare("INSERT INTO chat_messages (room_id, sender_id, message_text, created_at) VALUES (?, ?, ?, NOW())");
        $stmt->bind_param('iis', $roomId, $userId, $messageText);
        $stmt->execute();
        $newId = $stmt->insert_id;
        $stmt->close();

        $senderName = $_SESSION['user_name'] ?? '';
        $senderImage = $_SESSION['profile_image'] ?? null;

        json_response([
            'success' => true,
            'message' => [
                'id'            => (int) $newId,
                'sender_id'     => $userId,
                'sender_name'   => $senderName,
                'sender_image'  => $senderImage,
                'message_text'  => $messageText,
                'created_at'    => date('Y-m-d H:i:s'),
            ]
        ]);
        break;

    // ── UNREAD COUNT ──────────────────────────────────────────────────
    case 'unread_count':
        $stmt = $conn->prepare("
            SELECT COUNT(*) AS cnt
            FROM chat_messages cm
            JOIN chat_rooms cr ON cm.room_id = cr.id
            JOIN contracts ct ON cr.contract_id = ct.id
            WHERE (ct.client_id = ? OR ct.freelancer_id = ?)
              AND cm.sender_id != ?
              AND cm.is_read = 0
        ");
        $stmt->bind_param('iii', $userId, $userId, $userId);
        $stmt->execute();
        $count = (int) $stmt->get_result()->fetch_assoc()['cnt'];
        $stmt->close();
        json_response(['success' => true, 'count' => $count]);
        break;

    default:
        json_response(['success' => false, 'error' => 'Invalid action'], 400);
}

$conn->close();

// ── Helper: Verify Room Access ────────────────────────────────────────
function _hasRoomAccess(mysqli $conn, int $roomId, int $userId): bool {
    $stmt = $conn->prepare("
        SELECT 1 FROM chat_rooms cr
        JOIN contracts ct ON cr.contract_id = ct.id
        WHERE cr.id = ? AND (ct.client_id = ? OR ct.freelancer_id = ?)
        LIMIT 1
    ");
    $stmt->bind_param('iii', $roomId, $userId, $userId);
    $stmt->execute();
    $hasAccess = $stmt->get_result()->num_rows > 0;
    $stmt->close();
    return $hasAccess;
}
