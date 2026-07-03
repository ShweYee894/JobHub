<?php
/**
 * Freelancer Mark Messages Read Handler
 * 
 * POST Parameters: csrf_token, room_id
 * Returns: { success: true, marked_read: int }
 */
session_start();
header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
    exit;
}

if (!isset($_SESSION['user_id']) || ($_SESSION['user_role'] ?? '') !== 'freelancer') {
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

$freelancerId = (int) $_SESSION['user_id'];

if (!isset($_POST['csrf_token']) || !isset($_SESSION['csrf_token']) ||
    !hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])) {
    http_response_code(403);
    echo json_encode(['error' => 'Invalid CSRF token']);
    exit;
}

if (!isset($_POST['room_id']) || !is_numeric($_POST['room_id'])) {
    http_response_code(400);
    echo json_encode(['error' => 'Invalid room_id']);
    exit;
}
$roomId = (int) $_POST['room_id'];

require_once __DIR__ . '/../config/db.php';

$stmt = $conn->prepare("
    SELECT cr.id FROM chat_rooms cr
    JOIN contracts c ON cr.contract_id = c.id
    WHERE cr.id = ? AND c.freelancer_id = ?
");
$stmt->bind_param('ii', $roomId, $freelancerId);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    $stmt->close();
    http_response_code(403);
    echo json_encode(['error' => 'Room not found or access denied']);
    exit;
}
$stmt->close();

$updateStmt = $conn->prepare("
    UPDATE chat_messages SET is_read = 1 WHERE room_id = ? AND sender_id != ? AND is_read = 0
");
$updateStmt->bind_param('ii', $roomId, $freelancerId);
$updateStmt->execute();
$affectedRows = $updateStmt->affected_rows;
$updateStmt->close();
$conn->close();

echo json_encode(['success' => true, 'marked_read' => $affectedRows]);
