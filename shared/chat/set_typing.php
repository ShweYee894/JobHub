<?php
/**
 * set_typing.php
 *
 * Sets or clears the typing indicator for a user in a room.
 * The SSE stream reads this and broadcasts to the other participant.
 *
 * POST Parameters:
 *   room_id    (int, required)    — chat room ID
 *   is_typing  (int, required)    — 1 = typing, 0 = stopped
 *   csrf_token (string, required) — CSRF token
 *
 * Response:
 *   { success: true }
 */

session_start();
header('Content-Type: application/json');

require_once __DIR__ . '/../../config/helpers.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'Method not allowed']);
    exit;
}

if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
    exit;
}

$userId = (int) $_SESSION['user_id'];
$role   = $_SESSION['user_role'] ?? '';

if ($role !== 'client' && $role !== 'freelancer') {
    http_response_code(403);
    echo json_encode(['success' => false, 'error' => 'Invalid role']);
    exit;
}

if (!isset($_POST['csrf_token']) || !isset($_SESSION['csrf_token'])
    || !hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])) {
    http_response_code(403);
    echo json_encode(['success' => false, 'error' => 'Invalid CSRF token']);
    exit;
}

if (!isset($_POST['room_id']) || !is_numeric($_POST['room_id'])) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Missing or invalid room_id']);
    exit;
}

$roomId = (int) $_POST['room_id'];
if ($roomId <= 0) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Invalid room_id']);
    exit;
}

$isTyping = isset($_POST['is_typing']) ? (int) $_POST['is_typing'] : 0;
$isTyping = $isTyping ? 1 : 0;

require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/chat_functions.php';

if (!chat_verify_room_access($conn, $roomId, $userId, $role)) {
    http_response_code(403);
    echo json_encode(['success' => false, 'error' => 'Room not found or access denied']);
    exit;
}

// Upsert typing indicator
$stmt = $conn->prepare(
    'INSERT INTO typing_indicators (room_id, user_id, is_typing, updated_at)
     VALUES (?, ?, ?, NOW())
     ON DUPLICATE KEY UPDATE is_typing = ?, updated_at = NOW()'
);
if (!$stmt) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Database error']);
    exit;
}

$stmt->bind_param('iiii', $roomId, $userId, $isTyping, $isTyping);
$stmt->execute();
$stmt->close();

echo json_encode(['success' => true]);
