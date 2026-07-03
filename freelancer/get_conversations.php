<?php
/**
 * Freelancer Get Conversations Handler
 * 
 * Returns all active contracts as conversations for the logged-in freelancer.
 * Includes client info, last message preview, unread count, and online status.
 * 
 * Returns: { success: true, conversations: [...] }
 */
session_start();
header('Content-Type: application/json');

if (!isset($_SESSION['user_id']) || ($_SESSION['user_role'] ?? '') !== 'freelancer') {
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

$freelancerId = (int) $_SESSION['user_id'];

require_once __DIR__ . '/../config/db.php';

$stmt = $conn->prepare("
    SELECT
        c.id AS contract_id,
        c.total_budget,
        c.status AS contract_status,
        j.title AS job_title,
        u_c.id AS client_id,
        u_c.name AS client_name,
        u_c.profile_image AS client_image,
        u_c.updated_at AS client_last_active,
        cr.id AS room_id,
        cr.created_at AS room_created_at
    FROM contracts c
    JOIN jobs j ON c.job_id = j.id
    JOIN users u_c ON c.client_id = u_c.id
    LEFT JOIN chat_rooms cr ON cr.contract_id = c.id
    WHERE c.freelancer_id = ?
    ORDER BY c.updated_at DESC
");
$stmt->bind_param('i', $freelancerId);
$stmt->execute();
$result = $stmt->get_result();
$conversations = [];

while ($row = $result->fetch_assoc()) {
    $isOnline = false;
    if ($row['client_last_active']) {
        $lastActive = strtotime($row['client_last_active']);
        $isOnline = (time() - $lastActive) < 120;
    }

    $conversation = [
        'contract_id'         => (int) $row['contract_id'],
        'room_id'             => $row['room_id'] ? (int) $row['room_id'] : null,
        'client_name'         => htmlspecialchars($row['client_name'], ENT_QUOTES, 'UTF-8'),
        'client_image'        => $row['client_image'] ? htmlspecialchars($row['client_image'], ENT_QUOTES, 'UTF-8') : null,
        'client_last_active'  => $row['client_last_active'],
        'job_title'           => htmlspecialchars($row['job_title'], ENT_QUOTES, 'UTF-8'),
        'last_message'        => null,
        'last_message_time'   => null,
        'unread_count'        => 0,
        'contract_status'     => $row['contract_status'],
        'total_budget'        => (float) $row['total_budget'],
        'is_online'           => $isOnline
    ];

    if ($row['room_id']) {
        $roomId = (int) $row['room_id'];

        $msgStmt = $conn->prepare("
            SELECT message_text, created_at FROM chat_messages
            WHERE room_id = ? ORDER BY created_at DESC LIMIT 1
        ");
        $msgStmt->bind_param('i', $roomId);
        $msgStmt->execute();
        $msgResult = $msgStmt->get_result();
        if ($msgRow = $msgResult->fetch_assoc()) {
            $conversation['last_message'] = htmlspecialchars($msgRow['message_text'], ENT_QUOTES, 'UTF-8');
            $conversation['last_message_time'] = $msgRow['created_at'];
        }
        $msgStmt->close();

        $unreadStmt = $conn->prepare("
            SELECT COUNT(*) AS unread_count FROM chat_messages
            WHERE room_id = ? AND sender_id != ? AND is_read = 0
        ");
        $unreadStmt->bind_param('ii', $roomId, $freelancerId);
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

echo json_encode(['success' => true, 'conversations' => $conversations]);
