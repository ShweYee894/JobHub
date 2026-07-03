<?php
/**
 * Online Status API
 * 
 * Returns the online status of a user based on their last activity timestamp.
 * A user is considered "online" if active within the last 2 minutes.
 * 
 * GET Parameters: user_id
 * Returns: { success: true, is_online: bool, last_seen: string }
 */
session_start();
header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

if (!isset($_GET['user_id']) || !is_numeric($_GET['user_id'])) {
    http_response_code(400);
    echo json_encode(['error' => 'Invalid user_id']);
    exit;
}

$targetUserId = (int) $_GET['user_id'];

require_once __DIR__ . '/../config/db.php';

$stmt = $conn->prepare("SELECT updated_at FROM users WHERE id = ?");
$stmt->bind_param('i', $targetUserId);
$stmt->execute();
$result = $stmt->get_result();
$user = $result->fetch_assoc();
$stmt->close();
$conn->close();

if (!$user) {
    http_response_code(404);
    echo json_encode(['error' => 'User not found']);
    exit;
}

$lastActive = strtotime($user['updated_at']);
$isOnline = (time() - $lastActive) < 120;

echo json_encode([
    'success'   => true,
    'is_online' => $isOnline,
    'last_seen' => $user['updated_at']
]);
