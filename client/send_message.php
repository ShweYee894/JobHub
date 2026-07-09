<?php
/**
 * Client Send Message Handler
 * 
 * Validates session, verifies contract ownership, sanitizes input,
 * inserts message into chat_messages, and returns the new message as JSON.
 * 
 * POST Parameters: csrf_token, room_id, message_text
 * Returns: { success: true, message: {...} }
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

// Validate message text
if (!isset($_POST['message_text'])) {
    http_response_code(400);
    echo json_encode(['error' => 'Message text is required']);
    exit;
}

$messageText = trim($_POST['message_text']);

// Allow empty text for file-only messages
if ($messageText === '') {
    $messageText = '';
}

// Reject messages exceeding 5000 characters
if ($messageText !== '' && mb_strlen($messageText) > 5000) {
    http_response_code(400);
    echo json_encode(['error' => 'Message too long (max 5000 characters)']);
    exit;
}

// Strip any HTML tags to prevent XSS injection
$messageText = strip_tags($messageText);

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

// Insert the message
$payloadJson = null;
if (isset($_POST['payload']) && !empty($_POST['payload'])) {
    $payloadData = json_decode($_POST['payload'], true);
    if ($payloadData && isset($payloadData['path'])) {
        $payloadJson = json_encode($payloadData);
    }
}

$insStmt = $conn->prepare("
    INSERT INTO chat_messages (room_id, sender_id, message_text, is_read, created_at, payload)
    VALUES (?, ?, ?, 0, NOW(), ?)
");
$insStmt->bind_param('iiss', $roomId, $clientId, $messageText, $payloadJson);

if (!$insStmt->execute()) {
    $insStmt->close();
    http_response_code(500);
    echo json_encode(['error' => 'Failed to send message']);
    exit;
}

$newMessageId = (int) $insStmt->insert_id;
$insStmt->close();

// Update user's last activity timestamp for online status
$updateActivity = $conn->prepare("UPDATE users SET updated_at = NOW() WHERE id = ?");
$updateActivity->bind_param('i', $clientId);
$updateActivity->execute();
$updateActivity->close();

// Fetch the complete message with sender info
$stmtGet = $conn->prepare("
    SELECT
        cm.id,
        cm.sender_id,
        cm.message_text,
        cm.is_read,
        cm.created_at,
        cm.payload,
        u.name AS sender_name,
        u.profile_image AS sender_image
    FROM chat_messages cm
    INNER JOIN users u ON cm.sender_id = u.id
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
