<?php
/**
 * Client Mark Messages Read Handler
 * 
 * Automatically marks received messages as read when the conversation is opened.
 * Only updates messages that belong to other users (not the logged-in client).
 * 
 * POST Parameters: csrf_token, room_id
 * Returns: { success: true, marked_count: int }
 */
session_start();
header('Content-Type: application/json');

// Only accept POST requests
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
    exit;
}

// Verify session authentication
if (!isset($_SESSION['user_id']) || ($_SESSION['user_role'] ?? '') !== 'client') {
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

$clientId = (int) $_SESSION['user_id'];

// Verify CSRF token
if (!isset($_POST['csrf_token']) || !isset($_SESSION['csrf_token']) ||
    !hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])) {
    http_response_code(403);
    echo json_encode(['error' => 'Invalid CSRF token']);
    exit;
}

// Validate room_id
if (!isset($_POST['room_id']) || !is_numeric($_POST['room_id'])) {
    http_response_code(400);
    echo json_encode(['error' => 'Invalid room_id parameter']);
    exit;
}
$roomId = (int) $_POST['room_id'];

// Database connection
require_once __DIR__ . '/../config/db.php';

// Verify the room belongs to a contract owned by this client
$stmt = $conn->prepare("
    SELECT cr.id
    FROM chat_rooms cr
    INNER JOIN contracts c ON cr.contract_id = c.id
    WHERE cr.id = ? AND c.client_id = ?
");
$stmt->bind_param('ii', $roomId, $clientId);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    $stmt->close();
    http_response_code(403);
    echo json_encode(['error' => 'Room not found or access denied']);
    exit;
}
$stmt->close();

// Mark messages as read (only messages sent by others)
$updateStmt = $conn->prepare("
    UPDATE chat_messages
    SET is_read = 1
    WHERE room_id = ? AND sender_id != ? AND is_read = 0
");
$updateStmt->bind_param('ii', $roomId, $clientId);

if (!$updateStmt->execute()) {
    $updateStmt->close();
    http_response_code(500);
    echo json_encode(['error' => 'Failed to mark messages as read']);
    exit;
}

$affectedRows = $updateStmt->affected_rows;
$updateStmt->close();
$conn->close();

echo json_encode([
    'success'      => true,
    'message'      => 'Messages marked as read',
    'marked_count' => $affectedRows
]);
