<?php
session_start();
header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

$userId = (int) $_SESSION['user_id'];

require_once __DIR__ . '/../config/db.php';

$stmt = $conn->prepare("
    SELECT COUNT(*) AS total_unread
    FROM chat_messages cm
    INNER JOIN chat_rooms cr ON cm.room_id = cr.id
    INNER JOIN contracts c ON cr.contract_id = c.id
    WHERE (c.client_id = ? OR c.freelancer_id = ?)
    AND cm.sender_id != ? AND cm.is_read = 0
");
$stmt->bind_param('iii', $userId, $userId, $userId);
$stmt->execute();
$result = $stmt->get_result();
$row = $result->fetch_assoc();
$totalUnread = (int) ($row['total_unread'] ?? 0);
$stmt->close();
$conn->close();

echo json_encode([
    'success'      => true,
    'unread_count' => $totalUnread
]);
