<?php
/**
 * Client Get Conversations Handler
 * 
 * Returns all active contracts as conversations for the logged-in client.
 * Includes freelancer info, last message preview, unread count, and online status.
 * Sorted by latest message descending.
 * 
 * Returns: { success: true, conversations: [...] }
 */
session_start();
header('Content-Type: application/json');

// Verify session authentication
if (!isset($_SESSION['user_id']) || ($_SESSION['user_role'] ?? '') !== 'client') {
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

$clientId = (int) $_SESSION['user_id'];

// Database connection
require_once __DIR__ . '/../config/db.php';

// Fetch all contracts with chat rooms for this client
$stmt = $conn->prepare("
    SELECT
        c.id AS contract_id,
        c.total_budget,
        c.status AS contract_status,
        j.title AS job_title,
        u_f.id AS freelancer_id,
        u_f.name AS freelancer_name,
        u_f.profile_image AS freelancer_image,
        u_f.updated_at AS freelancer_last_active,
        cr.id AS room_id,
        cr.created_at AS room_created_at
    FROM contracts c
    INNER JOIN jobs j ON c.job_id = j.id
    INNER JOIN users u_f ON c.freelancer_id = u_f.id
    LEFT JOIN chat_rooms cr ON c.id = cr.contract_id
    WHERE c.client_id = ?
    ORDER BY c.updated_at DESC
");
$stmt->bind_param('i', $clientId);
$stmt->execute();
$result = $stmt->get_result();
$conversations = [];

while ($row = $result->fetch_assoc()) {
    $conversation = [
        'contract_id'         => (int) $row['contract_id'],
        'total_budget'        => (float) $row['total_budget'],
        'contract_status'     => $row['contract_status'],
        'job_title'           => htmlspecialchars($row['job_title'], ENT_QUOTES, 'UTF-8'),
        'freelancer_id'       => (int) $row['freelancer_id'],
        'freelancer_name'     => htmlspecialchars($row['freelancer_name'], ENT_QUOTES, 'UTF-8'),
        'freelancer_image'    => $row['freelancer_image'] ? htmlspecialchars($row['freelancer_image'], ENT_QUOTES, 'UTF-8') : null,
        'freelancer_last_active' => $row['freelancer_last_active'],
        'room_id'             => $row['room_id'] ? (int) $row['room_id'] : null,
        'last_message'        => null,
        'last_message_time'   => null,
        'unread_count'        => 0,
        'is_online'           => false
    ];

    // Determine online status (active within last 2 minutes)
    if ($row['freelancer_last_active']) {
        $lastActive = strtotime($row['freelancer_last_active']);
        $conversation['is_online'] = (time() - $lastActive) < 120;
    }

    if ($row['room_id']) {
        $roomId = (int) $row['room_id'];

        // Get last message preview
        $msgStmt = $conn->prepare("
            SELECT message_text, created_at
            FROM chat_messages
            WHERE room_id = ?
            ORDER BY created_at DESC
            LIMIT 1
        ");
        $msgStmt->bind_param('i', $roomId);
        $msgStmt->execute();
        $msgResult = $msgStmt->get_result();
        if ($msgRow = $msgResult->fetch_assoc()) {
            $conversation['last_message'] = htmlspecialchars($msgRow['message_text'], ENT_QUOTES, 'UTF-8');
            $conversation['last_message_time'] = $msgRow['created_at'];
        }
        $msgStmt->close();

        // Get unread count
        $unreadStmt = $conn->prepare("
            SELECT COUNT(*) AS unread_count
            FROM chat_messages
            WHERE room_id = ? AND sender_id != ? AND is_read = 0
        ");
        $unreadStmt->bind_param('ii', $roomId, $clientId);
        $unreadStmt->execute();
        $unreadResult = $unreadStmt->get_result();
        if ($unreadRow = $unreadResult->fetch_assoc()) {
            $conversation['unread_count'] = (int) $unreadRow['unread_count'];
        }
        $unreadStmt->close();
    }

    $conversations[] = $conversation;
}

$stmt->close();
$conn->close();

echo json_encode([
    'success'       => true,
    'conversations' => $conversations
]);
