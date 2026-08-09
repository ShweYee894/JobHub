<?php
/**
 * Search Conversations API
 * 
 * Server-side search for conversations by user name, company name,
 * freelancer title, or job title. Returns matching conversations.
 * 
 * GET Parameters: q (search query)
 * Returns: { success: true, conversations: [...] }
 */
session_start();
header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

$userId = (int) $_SESSION['user_id'];
$role = $_SESSION['user_role'] ?? '';
$query = isset($_GET['q']) ? trim($_GET['q']) : '';

if ($query === '') {
    echo json_encode(['success' => true, 'conversations' => []]);
    exit;
}

// ── Release session lock ────────────────────────────────────────────────────
define('SESSION_CLOSED', true);
session_write_close();

require_once __DIR__ . '/../config/db.php';

$searchParam = "%$query%";

if ($role === 'client') {
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
        LEFT JOIN freelancers f ON f.user_id = u_f.id
        WHERE c.client_id = ?
        AND (u_f.name LIKE ? OR j.title LIKE ? OR f.title LIKE ? OR c.client_id IN (
            SELECT cl.client_id FROM clients cl WHERE cl.company_name LIKE ?
        ))
        ORDER BY c.updated_at DESC
    ");
    $stmt->bind_param('issss', $userId, $searchParam, $searchParam, $searchParam, $searchParam);
} else {
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
        INNER JOIN jobs j ON c.job_id = j.id
        INNER JOIN users u_c ON c.client_id = u_c.id
        LEFT JOIN chat_rooms cr ON cr.contract_id = c.id
        LEFT JOIN clients cl ON cl.client_id = u_c.id
        WHERE c.freelancer_id = ?
        AND (u_c.name LIKE ? OR j.title LIKE ? OR cl.company_name LIKE ?)
        ORDER BY c.updated_at DESC
    ");
    $stmt->bind_param('isss', $userId, $searchParam, $searchParam, $searchParam);
}

$stmt->execute();
$result = $stmt->get_result();
$conversations = [];

while ($row = $result->fetch_assoc()) {
    $otherName = $role === 'client' ? $row['freelancer_name'] : $row['client_name'];
    $otherImage = $role === 'client' ? $row['freelancer_image'] : $row['client_image'];
    $lastActive = $role === 'client' ? $row['freelancer_last_active'] : $row['client_last_active'];

    $isOnline = false;
    if ($lastActive) {
        $isOnline = (time() - strtotime($lastActive)) < 120;
    }

    $conv = [
        'contract_id'   => (int) $row['contract_id'],
        'room_id'       => $row['room_id'] ? (int) $row['room_id'] : null,
        'other_name'    => htmlspecialchars($otherName, ENT_QUOTES, 'UTF-8'),
        'other_image'   => $otherImage ? htmlspecialchars($otherImage, ENT_QUOTES, 'UTF-8') : null,
        'job_title'     => htmlspecialchars($row['job_title'], ENT_QUOTES, 'UTF-8'),
        'last_message'  => null,
        'last_message_time' => null,
        'unread_count'  => 0,
        'is_online'     => $isOnline
    ];

    if ($row['room_id']) {
        $roomId = (int) $row['room_id'];

        $msgStmt = $conn->prepare("
            SELECT message_text, created_at FROM chat_messages
            WHERE room_id = ? ORDER BY created_at DESC LIMIT 1
        ");
        $msgStmt->bind_param('i', $roomId);
        $msgStmt->execute();
        $msgRes = $msgStmt->get_result();
        if ($msgRow = $msgRes->fetch_assoc()) {
            $conv['last_message'] = htmlspecialchars($msgRow['message_text'], ENT_QUOTES, 'UTF-8');
            $conv['last_message_time'] = $msgRow['created_at'];
        }
        $msgStmt->close();
    }

    $conversations[] = $conv;
}

$stmt->close();
$conn->close();

echo json_encode(['success' => true, 'conversations' => $conversations]);
