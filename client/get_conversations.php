<?php
/**
 * Client Get Conversations Handler
 *
 * Returns all active contracts as conversations for the logged-in client.
 * Uses optimized single query with LEFT JOINs instead of N+1 subqueries.
 *
 * Returns: { success: true, conversations: [...] }
 */
session_start();
header('Content-Type: application/json');

if (!isset($_SESSION['user_id']) || ($_SESSION['user_role'] ?? '') !== 'client') {
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

$clientId = (int) $_SESSION['user_id'];

require_once __DIR__ . '/../config/db.php';

function _chatProfileImage(?string $filename): ?string
{
    if (!$filename) return null;
    $basename = basename($filename);
    if (file_exists(__DIR__ . '/../assets/upload/profiles/' . $basename)) {
        return 'assets/upload/profiles/' . $basename;
    }
    return 'assets/upload/profile.png';
}

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
        cr.created_at AS room_created_at,
        lm.message_text AS last_message,
        lm.created_at AS last_message_time,
        uc.unread_count
    FROM contracts c
    INNER JOIN jobs j ON c.job_id = j.id
    INNER JOIN users u_f ON c.freelancer_id = u_f.id
    LEFT JOIN chat_rooms cr ON c.id = cr.contract_id
    LEFT JOIN (
        SELECT cm.room_id, cm.message_text, cm.created_at
        FROM chat_messages cm
        INNER JOIN (
            SELECT room_id, MAX(created_at) AS max_created
            FROM chat_messages
            GROUP BY room_id
        ) latest ON cm.room_id = latest.room_id AND cm.created_at = latest.max_created
    ) lm ON cr.id = lm.room_id
    LEFT JOIN (
        SELECT room_id, COUNT(*) AS unread_count
        FROM chat_messages
        WHERE sender_id != ? AND is_read = 0
        GROUP BY room_id
    ) uc ON cr.id = uc.room_id
    WHERE c.client_id = ?
    ORDER BY COALESCE(lm.created_at, c.updated_at) DESC
");
$stmt->bind_param('ii', $clientId, $clientId);
$stmt->execute();
$result = $stmt->get_result();
$conversations = [];

while ($row = $result->fetch_assoc()) {
    $isOnline = false;
    if ($row['freelancer_last_active']) {
        $lastActive = strtotime($row['freelancer_last_active']);
        $isOnline = (time() - $lastActive) < 120;
    }

    $conversation = [
        'contract_id'         => (int) $row['contract_id'],
        'total_budget'        => (float) $row['total_budget'],
        'contract_status'     => $row['contract_status'],
        'job_title'           => htmlspecialchars($row['job_title'], ENT_QUOTES, 'UTF-8'),
        'freelancer_id'       => (int) $row['freelancer_id'],
        'freelancer_name'     => htmlspecialchars($row['freelancer_name'], ENT_QUOTES, 'UTF-8'),
        'freelancer_image'    => _chatProfileImage($row['freelancer_image']),
        'freelancer_last_active' => $row['freelancer_last_active'],
        'room_id'             => $row['room_id'] ? (int) $row['room_id'] : null,
        'last_message'        => $row['last_message'] ? htmlspecialchars($row['last_message'], ENT_QUOTES, 'UTF-8') : null,
        'last_message_time'   => $row['last_message_time'],
        'unread_count'        => (int) ($row['unread_count'] ?? 0),
        'is_online'           => $isOnline,
        'last_seen'           => $row['freelancer_last_active']
    ];

    $conversations[] = $conversation;
}

$stmt->close();
$conn->close();

echo json_encode([
    'success'       => true,
    'conversations' => $conversations
]);
