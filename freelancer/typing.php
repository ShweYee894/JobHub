<?php
/**
 * Freelancer Typing Indicator Handler
 * 
 * POST Parameters: csrf_token, room_id
 * Returns: { success: true, typing: {...} }
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

// Upsert typing indicator
$typingStmt = $conn->prepare("
    INSERT INTO typing_indicators (room_id, user_id, is_typing, updated_at)
    VALUES (?, ?, 1, NOW())
    ON DUPLICATE KEY UPDATE is_typing = 1, updated_at = NOW()
");
$typingStmt->bind_param('ii', $roomId, $freelancerId);
$typingStmt->execute();
$typingStmt->close();

$userStmt = $conn->prepare("SELECT name, profile_image FROM users WHERE id = ?");
$userStmt->bind_param('i', $freelancerId);
$userStmt->execute();
$user = $userStmt->get_result()->fetch_assoc();
$userStmt->close();

$conn->close();

echo json_encode([
    'success' => true,
    'typing'  => [
        'user_id'    => $freelancerId,
        'user_name'  => $user['name'] ?? 'Freelancer',
        'user_image' => $user['profile_image'] ?? null,
        'room_id'    => $roomId,
        'timestamp'  => time()
    ]
]);
