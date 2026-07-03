<?php
session_start();
header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
    exit;
}

if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

$userId = (int) $_SESSION['user_id'];

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

if (!isset($_POST['message_text']) || trim($_POST['message_text']) === '') {
    http_response_code(400);
    echo json_encode(['error' => 'Message cannot be empty']);
    exit;
}

$messageText = trim($_POST['message_text']);

if (mb_strlen($messageText) > 5000) {
    http_response_code(400);
    echo json_encode(['error' => 'Message too long (max 5000 characters)']);
    exit;
}

$messageText = strip_tags($messageText);

require_once __DIR__ . '/../config/db.php';

$stmt = $conn->prepare("
    SELECT cr.id FROM chat_rooms cr
    INNER JOIN contracts c ON cr.contract_id = c.id
    WHERE cr.id = ? AND (c.client_id = ? OR c.freelancer_id = ?)
");
$stmt->bind_param('iii', $roomId, $userId, $userId);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    $stmt->close();
    http_response_code(403);
    echo json_encode(['error' => 'Room not found or access denied']);
    exit;
}
$stmt->close();

$insStmt = $conn->prepare("
    INSERT INTO chat_messages (room_id, sender_id, message_text, is_read, created_at)
    VALUES (?, ?, ?, 0, NOW())
");
$insStmt->bind_param('iis', $roomId, $userId, $messageText);

if (!$insStmt->execute()) {
    $insStmt->close();
    http_response_code(500);
    echo json_encode(['error' => 'Failed to send message']);
    exit;
}

$newMessageId = (int) $insStmt->insert_id;
$insStmt->close();

$updateAct = $conn->prepare("UPDATE users SET updated_at = NOW() WHERE id = ?");
$updateAct->bind_param('i', $userId);
$updateAct->execute();
$updateAct->close();

$stmtGet = $conn->prepare("
    SELECT cm.id, cm.sender_id, cm.message_text, cm.is_read, cm.created_at,
           u.name AS sender_name, u.profile_image AS sender_image
    FROM chat_messages cm
    INNER JOIN users u ON cm.sender_id = u.id
    WHERE cm.id = ?
");
$stmtGet->bind_param('i', $newMessageId);
$stmtGet->execute();
$msgResult = $stmtGet->get_result();
$newMessage = null;

if ($row = $msgResult->fetch_assoc()) {
    $newMessage = [
        'id'            => (int) $row['id'],
        'sender_id'     => (int) $row['sender_id'],
        'sender_name'   => htmlspecialchars($row['sender_name'], ENT_QUOTES, 'UTF-8'),
        'sender_image'  => $row['sender_image'] ? htmlspecialchars($row['sender_image'], ENT_QUOTES, 'UTF-8') : null,
        'message_text'  => htmlspecialchars($row['message_text'], ENT_QUOTES, 'UTF-8'),
        'is_read'       => (int) $row['is_read'],
        'created_at'    => $row['created_at'],
    ];
}
$stmtGet->close();
$conn->close();

echo json_encode([
    'success' => true,
    'message' => $newMessage
]);
