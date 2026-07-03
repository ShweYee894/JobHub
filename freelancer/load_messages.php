<?php
/**
 * Freelancer Load Messages Handler
 * 
 * Loads all messages in a chat room, ordered by created_at ASC.
 * Verifies the logged-in freelancer belongs to the contract.
 * Automatically marks received messages as read.
 * 
 * GET Parameters: room_id
 * Returns: { success: true, messages: [...] }
 */
session_start();
header('Content-Type: application/json');

if (!isset($_SESSION['user_id']) || ($_SESSION['user_role'] ?? '') !== 'freelancer') {
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

$freelancerId = (int) $_SESSION['user_id'];

if (!isset($_GET['room_id']) || !is_numeric($_GET['room_id'])) {
    http_response_code(400);
    echo json_encode(['error' => 'Invalid room_id']);
    exit;
}
$roomId = (int) $_GET['room_id'];

require_once __DIR__ . '/../config/db.php';

$stmt = $conn->prepare("
    SELECT cr.id
    FROM chat_rooms cr
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

$msgStmt = $conn->prepare("
    SELECT cm.id, cm.sender_id, cm.message_text, cm.is_read, cm.created_at, cm.payload,
           u.name AS sender_name, u.profile_image AS sender_image
    FROM chat_messages cm
    JOIN users u ON cm.sender_id = u.id
    WHERE cm.room_id = ?
    ORDER BY cm.created_at ASC
");
$msgStmt->bind_param('i', $roomId);
$msgStmt->execute();
$msgResult = $msgStmt->get_result();
$messages = [];

while ($row = $msgResult->fetch_assoc()) {
    $payload = null;
    if (!empty($row['payload'])) {
        $decoded = json_decode($row['payload'], true);
        $payload = $decoded ?: null;
    }

    $messages[] = [
        'id'            => (int) $row['id'],
        'sender_id'     => (int) $row['sender_id'],
        'sender_name'   => htmlspecialchars($row['sender_name'], ENT_QUOTES, 'UTF-8'),
        'sender_image'  => $row['sender_image'] ? htmlspecialchars($row['sender_image'], ENT_QUOTES, 'UTF-8') : null,
        'message_text'  => htmlspecialchars($row['message_text'], ENT_QUOTES, 'UTF-8'),
        'is_read'       => (int) $row['is_read'],
        'created_at'    => $row['created_at'],
        'payload'       => $payload
    ];
}
$msgStmt->close();

// Mark as read
$updateStmt = $conn->prepare("
    UPDATE chat_messages SET is_read = 1 WHERE room_id = ? AND sender_id != ? AND is_read = 0
");
$updateStmt->bind_param('ii', $roomId, $freelancerId);
$updateStmt->execute();
$updateStmt->close();

$conn->close();

echo json_encode(['success' => true, 'messages' => $messages]);
