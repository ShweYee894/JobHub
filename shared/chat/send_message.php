<?php
/**
 * send_message.php
 *
 * Sends a chat message. Returns the complete message as JSON.
 * Designed for Fetch API — all responses are JSON.
 *
 * POST Parameters:
 *   room_id      (int, required)    — chat room ID
 *   message_text (string, required) — message body (max 5000 chars)
 *   payload      (string, optional) — JSON string for file attachment
 *   csrf_token   (string, required) — CSRF token
 *
 * Response:
 *   { success: true, message: { id, room_id, sender_id, ... } }
 */

session_start();
header('Content-Type: application/json');

// ── POST only ────────────────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'Method not allowed']);
    exit;
}

// ── Authentication ───────────────────────────────────────────────────────────

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

// ── CSRF verification ────────────────────────────────────────────────────────
if (!isset($_POST['csrf_token']) || !isset($_SESSION['csrf_token'])
    || !hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])) {
    http_response_code(403);
    echo json_encode(['success' => false, 'error' => 'Invalid CSRF token']);
    exit;
}

// ── Release session lock ────────────────────────────────────────────────────
// All session data needed for auth/CSRF is captured. Close the session file
// so SSE (sse.php) and other AJAX requests don't block on it.
define('SESSION_CLOSED', true);
session_write_close();

// ── Validate room_id ────────────────────────────────────────────────────────
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

// ── Validate message_text ────────────────────────────────────────────────────
if (!isset($_POST['message_text'])) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Message text is required']);
    exit;
}

$messageText = trim($_POST['message_text']);

// Allow empty text only if a file payload is attached
$hasPayload = isset($_POST['payload']) && !empty($_POST['payload']);
if ($messageText === '' && !$hasPayload) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Message cannot be empty']);
    exit;
}

// Length check
if (mb_strlen($messageText) > 5000) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Message too long (max 5000 characters)']);
    exit;
}

// Sanitize: strip HTML tags, trim whitespace
$messageText = strip_tags($messageText);

// ── Validate payload (if present) ────────────────────────────────────────────
$payload = null;
if ($hasPayload) {
    $payload = json_decode($_POST['payload'], true);

    if (!is_array($payload)) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'Invalid payload format']);
        exit;
    }

    // Required fields for file attachment
    if (!isset($payload['path']) || !isset($payload['name']) || !isset($payload['type'])) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'Payload missing required fields (path, name, type)']);
        exit;
    }

    // Path traversal protection — reject .. and invalid prefixes
    if (strpos($payload['path'], '..') !== false) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'Invalid file path']);
        exit;
    }

    // Sanitize payload values — strip_tags for storage, escape on output
    $payload['path'] = filter_var($payload['path'], FILTER_SANITIZE_URL);
    $payload['name'] = strip_tags($payload['name']);
    $payload['type'] = strip_tags($payload['type']);
    if (isset($payload['size'])) {
        $payload['size'] = (int) $payload['size'];
    }
}

// ── Bootstrap ────────────────────────────────────────────────────────────────
require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/chat_functions.php';
require_once __DIR__ . '/../notification_helper.php';

// ── Verify room ownership ───────────────────────────────────────────────────
if (!chat_verify_room_access($conn, $roomId, $userId, $role)) {
    http_response_code(403);
    echo json_encode(['success' => false, 'error' => 'Room not found or access denied']);
    exit;
}

// ── Insert message ───────────────────────────────────────────────────────────
$messageId = chat_insert_message($conn, $roomId, $userId, $messageText, $payload);

if ($messageId === null) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Failed to send message']);
    exit;
}

// ── Send notification to recipient ────────────────────────────────────────────
$senderName = $_SESSION['user_name'] ?? 'Someone';

try {
    $recipientStmt = $conn->prepare(
        'SELECT c.client_id, c.freelancer_id, uc.role AS client_role, uf.role AS freelancer_role
         FROM chat_rooms cr
         JOIN contracts c ON cr.contract_id = c.id
         JOIN users uc ON c.client_id = uc.id
         JOIN users uf ON c.freelancer_id = uf.id
         WHERE cr.id = ? LIMIT 1'
    );
    if ($recipientStmt) {
        $recipientStmt->bind_param('i', $roomId);
        $recipientStmt->execute();
        $recipientRow = $recipientStmt->get_result()->fetch_assoc();
        $recipientStmt->close();

        if ($recipientRow) {
            $recipientId = ($userId === (int) $recipientRow['client_id'])
                ? (int) $recipientRow['freelancer_id']
                : (int) $recipientRow['client_id'];
            $recipientRole = ($userId === (int) $recipientRow['client_id'])
                ? $recipientRow['freelancer_role']
                : $recipientRow['client_role'];

            notifyNewMessage($recipientId, $senderName, $roomId, $recipientRole);
        }
    }
} catch (\Throwable $e) {
    // Notification failure must not break the message response
    error_log('send_message notify error: ' . $e->getMessage());
}

// ── Response ─────────────────────────────────────────────────────────────────
// Build the message directly from session data — avoids a redundant DB round-trip.
$senderImage = chat_resolve_profile_image($_SESSION['profile_image'] ?? null);

echo json_encode([
    'success' => true,
    'message' => [
        'id'           => $messageId,
        'room_id'      => $roomId,
        'sender_id'    => $userId,
        'sender_name'  => $senderName,
        'sender_image' => $senderImage,
        'message_text' => $messageText,
        'is_read'      => 0,
        'created_at'   => date('Y-m-d H:i:s'),
    ],
]);
