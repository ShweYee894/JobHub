<?php
/**
 * Client Typing Indicator Handler
 * 
 * Records that the client is typing in a specific room.
 * Uses the typing_indicators table with upsert (INSERT ... ON DUPLICATE KEY UPDATE).
 * The typing indicator auto-expires after 10 seconds.
 * 
 * POST Parameters: csrf_token, room_id
 * Returns: { success: true, typing: {...} }
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

// Verify room access
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

// Ensure typing_indicators table exists
$conn->query("CREATE TABLE IF NOT EXISTS typing_indicators (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    room_id INT UNSIGNED NOT NULL,
    user_id INT UNSIGNED NOT NULL,
    is_typing TINYINT(1) DEFAULT 0,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY unique_room_user (room_id, user_id),
    FOREIGN KEY (room_id) REFERENCES chat_rooms(id) ON DELETE CASCADE,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

// Upsert typing indicator
$typingStmt = $conn->prepare("
    INSERT INTO typing_indicators (room_id, user_id, is_typing, updated_at)
    VALUES (?, ?, 1, NOW())
    ON DUPLICATE KEY UPDATE is_typing = 1, updated_at = NOW()
");
$typingStmt->bind_param('ii', $roomId, $clientId);
$typingStmt->execute();
$typingStmt->close();

// Get user info for response
$userStmt = $conn->prepare("SELECT name, profile_image FROM users WHERE id = ?");
$userStmt->bind_param('i', $clientId);
$userStmt->execute();
$user = $userStmt->get_result()->fetch_assoc();
$userStmt->close();

$conn->close();

echo json_encode([
    'success' => true,
    'typing'  => [
        'user_id'    => $clientId,
        'user_name'  => $user['name'] ?? 'Client',
        'user_image' => $user['profile_image'] ?? null,
        'room_id'    => $roomId,
        'timestamp'  => time()
    ]
]);
