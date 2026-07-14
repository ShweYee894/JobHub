<?php
/**
 * mark_read.php
 *
 * Marks all unread messages in a room as read for the current user.
 * Called when a user opens a conversation.
 *
 * POST Parameters:
 *   room_id    (int, required) — chat room ID
 *   csrf_token (string, required) — CSRF token
 *
 * Response:
 *   { success: true, marked: int }
 */

session_start();
header('Content-Type: application/json');

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

require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/chat_functions.php';

if (!chat_verify_room_access($conn, $roomId, $userId, $role)) {
    http_response_code(403);
    echo json_encode(['success' => false, 'error' => 'Room not found or access denied']);
    exit;
}

$marked = chat_mark_read($conn, $roomId, $userId);

echo json_encode([
    'success' => true,
    'marked'  => $marked,
]);
