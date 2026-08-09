<?php
/**
 * load_history.php
 *
 * Loads chat messages for a room with pagination.
 * Returns messages in ascending order (oldest first).
 * Verifies the user owns the contract linked to the room.
 *
 * GET Parameters:
 *   room_id  (int, required) — chat room ID
 *   offset   (int, optional) — messages to skip (default 0)
 *   limit    (int, optional) — max messages to return (default 50, max 100)
 *
 * Response:
 *   { success: true, messages: [...], total: int, has_more: bool }
 */

session_start();
header('Content-Type: application/json');

// ── Authentication ───────────────────────────────────────────────────────────

if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
    exit;
}

$userId = (int) $_SESSION['user_id'];
$role   = $_SESSION['user_role'] ?? '';

if ($role !== 'client' && $role !== 'freelancer' && $role !== 'admin') {
    http_response_code(403);
    echo json_encode(['success' => false, 'error' => 'Invalid role']);
    exit;
}

// ── Release session lock ────────────────────────────────────────────────────
define('SESSION_CLOSED', true);
session_write_close();

// ── Validate room_id ────────────────────────────────────────────────────────
if (!isset($_GET['room_id']) || !is_numeric($_GET['room_id'])) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Missing or invalid room_id']);
    exit;
}

$roomId = (int) $_GET['room_id'];
if ($roomId <= 0) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Invalid room_id']);
    exit;
}

// ── Validate pagination params ───────────────────────────────────────────────
$offset = isset($_GET['offset']) && is_numeric($_GET['offset']) ? (int) $_GET['offset'] : 0;
$limit  = isset($_GET['limit'])  && is_numeric($_GET['limit'])  ? (int) $_GET['limit']  : 50;

if ($offset < 0) $offset = 0;
if ($limit < 1)  $limit = 50;
if ($limit > 100) $limit = 100;

// ── Bootstrap ────────────────────────────────────────────────────────────────
require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/chat_functions.php';

// ── Verify room ownership ───────────────────────────────────────────────────
if (!chat_verify_room_access($conn, $roomId, $userId, $role)) {
    http_response_code(403);
    echo json_encode(['success' => false, 'error' => 'Room not found or access denied']);
    exit;
}

// ── Fetch messages (ASC — oldest first) ──────────────────────────────────────
// Fetch limit+1 to determine has_more without a separate COUNT query
$fetchLimit = $limit + 1;

$msgStmt = $conn->prepare(
    'SELECT cm.id, cm.room_id, cm.sender_id, cm.message_text,
            cm.is_read, cm.created_at,
            u.name AS sender_name, u.profile_image AS sender_image
     FROM chat_messages cm
     JOIN users u ON cm.sender_id = u.id
     WHERE cm.room_id = ?
     ORDER BY cm.id ASC
     LIMIT ? OFFSET ?'
);
if (!$msgStmt) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Database error']);
    exit;
}

$msgStmt->bind_param('iii', $roomId, $fetchLimit, $offset);
$msgStmt->execute();
$result = $msgStmt->get_result();

$messages = [];
while ($row = $result->fetch_assoc()) {
    $messages[] = [
        'id'           => (int) $row['id'],
        'room_id'      => (int) $row['room_id'],
        'sender_id'    => (int) $row['sender_id'],
        'sender_name'  => $row['sender_name'],
        'sender_image' => chat_resolve_profile_image($row['sender_image']),
        'message_text' => $row['message_text'],
        'is_read'      => (int) $row['is_read'],
        'created_at'   => $row['created_at'],
    ];
}
$msgStmt->close();

// ── Determine has_more ──────────────────────────────────────────────────────
$hasMore = count($messages) > $limit;
if ($hasMore) {
    array_pop($messages); // remove the extra row
}

// ── Response ─────────────────────────────────────────────────────────────────
echo json_encode([
    'success'  => true,
    'messages' => $messages,
    'total'    => $offset + count($messages) + ($hasMore ? 1 : 0),
    'has_more' => $hasMore,
]);
