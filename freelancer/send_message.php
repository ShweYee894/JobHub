<?php
/**
 * Freelancer Send Message Handler
 * 
 * Validates session, verifies contract ownership, sanitizes input,
 * inserts message into chat_messages, and returns the new message as JSON.
 * 
 * POST Parameters: csrf_token, room_id, message_text
 * Returns: { success: true, message: {...} }
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

// Verify room access via freelancer contract ownership
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

// Insert message
$insStmt = $conn->prepare("
    INSERT INTO chat_messages (room_id, sender_id, message_text, is_read, created_at)
    VALUES (?, ?, ?, 0, NOW())
");
$insStmt->bind_param('iis', $roomId, $freelancerId, $messageText);

if (!$insStmt->execute()) {
    $insStmt->close();
    http_response_code(500);
    echo json_encode(['error' => 'Failed to send message']);
    exit;
}

$newMessageId = (int) $insStmt->insert_id;
$insStmt->close();

// Update last activity
$updateAct = $conn->prepare("UPDATE users SET updated_at = NOW() WHERE id = ?");
$updateAct->bind_param('i', $freelancerId);
$updateAct->execute();
$updateAct->close();

// Fetch complete message
$stmtGet = $conn->prepare("
    SELECT cm.id, cm.sender_id, cm.message_text, cm.is_read, cm.created_at, cm.payload,
           u.name AS sender_name, u.profile_image AS sender_image
    FROM chat_messages cm
    JOIN users u ON cm.sender_id = u.id
    WHERE cm.id = ?
");
$stmtGet->bind_param('i', $newMessageId);
$stmtGet->execute();
$msgResult = $stmtGet->get_result();
$newMessage = null;

if ($row = $msgResult->fetch_assoc()) {
    $payload = null;
    if (!empty($row['payload'])) {
        $decoded = json_decode($row['payload'], true);
        $payload = $decoded ?: null;
    }

    $newMessage = [
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
$stmtGet->close();
$conn->close();

echo json_encode([
    'success' => true,
    'message' => $newMessage
]);
