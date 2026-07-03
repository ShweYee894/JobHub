<?php
/**
 * chat_poll.php – Lightweight polling endpoint for new messages.
 * GET ?room_id=X&last_id=X  → returns new messages since last_id as JSON.
 */

session_start();
require_once __DIR__ . '/../config/db.php';

header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    json_response(['success' => false, 'error' => 'Unauthorized'], 401);
}

$userId = (int) $_SESSION['user_id'];
$roomId = (int) ($_GET['room_id'] ?? 0);
$lastId = (int) ($_GET['last_id'] ?? 0);

if ($roomId <= 0) {
    json_response(['success' => false, 'error' => 'Invalid room_id'], 400);
}

// Verify access
$stmt = $conn->prepare("
    SELECT 1 FROM chat_rooms cr
    JOIN contracts ct ON cr.contract_id = ct.id
    WHERE cr.id = ? AND (ct.client_id = ? OR ct.freelancer_id = ?)
    LIMIT 1
");
$stmt->bind_param('iii', $roomId, $userId, $userId);
$stmt->execute();
if ($stmt->get_result()->num_rows === 0) {
    $stmt->close();
    $conn->close();
    json_response(['success' => false, 'error' => 'Access denied'], 403);
}
$stmt->close();

// Fetch new messages
$stmt = $conn->prepare("
    SELECT cm.id, cm.room_id, cm.sender_id, cm.message_text, cm.created_at, cm.is_read,
           u.name AS sender_name, u.profile_image AS sender_image
    FROM chat_messages cm
    JOIN users u ON cm.sender_id = u.id
    WHERE cm.room_id = ? AND cm.id > ?
    ORDER BY cm.created_at ASC
");
$stmt->bind_param('ii', $roomId, $lastId);
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

// Mark incoming messages as read
if (count($messages) > 0) {
    $ur = $conn->prepare("UPDATE chat_messages SET is_read = 1 WHERE room_id = ? AND sender_id != ? AND is_read = 0");
    $ur->bind_param('ii', $roomId, $userId);
    $ur->execute();
    $ur->close();
}

$conn->close();
json_response(['success' => true, 'messages' => $messages]);
