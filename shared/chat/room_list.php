<?php
/**
 * room_list.php
 *
 * Returns the conversation list for the logged-in user (client or freelancer).
 * Single optimized query with subqueries — no N+1.
 *
 * Response shape:
 * {
 *   "success": true,
 *   "conversations": [
 *     {
 *       "room_id": 5,
 *       "contract_id": 12,
 *       "other_user": {
 *         "id": 42,
 *         "name": "John Doe",
 *         "image": "assets/upload/profiles/abc.jpg",
 *         "is_online": true,
 *         "last_seen": "2026-07-08 10:30:00"
 *       },
 *       "job_title": "Website Redesign",
 *       "last_message": "Thanks for the update!",
 *       "last_message_time": "2026-07-08 10:30:00",
 *       "unread_count": 3,
 *       "contract_status": "active",
 *       "budget": 500.00
 *     }
 *   ]
 * }
 */

session_start();
header('Content-Type: application/json');

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

// ── Bootstrap ────────────────────────────────────────────────────────────────
require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/chat_functions.php';


// ── Profile image helper ─────────────────────────────────────────────────────
// Checks if the file exists on disk, falls back to default placeholder.
function _roomListProfileImage(?string $filename): ?string
{
    if (!$filename) {
        return null;
    }
    $basename = basename($filename);
    if (file_exists(__DIR__ . '/../../assets/upload/profiles/' . $basename)) {
        return 'assets/upload/profiles/' . $basename;
    }
    return 'assets/upload/profile.png';
}


// ── Determine which column to filter on ──────────────────────────────────────
$otherField  = ($role === 'client') ? 'c.freelancer_id' : 'c.client_id';
$filterField = ($role === 'client') ? 'c.client_id'     : 'c.freelancer_id';


// ── Single optimized query ───────────────────────────────────────────────────
// Subquery 1 (lm): last message per room — single table scan with MAX(id)
// Subquery 2 (uc): unread count per room — single table scan with COUNT
// Both join via LEFT JOIN so rooms with no messages still appear.
$stmt = $conn->prepare("
    SELECT
        c.id            AS contract_id,
        c.total_budget,
        c.status        AS contract_status,
        j.title         AS job_title,

        u.id            AS other_id,
        u.name          AS other_name,
        u.profile_image AS other_image,
        u.updated_at    AS other_last_active,

        cr.id           AS room_id,

        lm.message_text AS last_message,
        lm.created_at   AS last_message_time,

        uc.unread_count

    FROM contracts c
    JOIN jobs j       ON c.job_id = j.id
    JOIN users u      ON {$otherField} = u.id
    LEFT JOIN chat_rooms cr ON cr.contract_id = c.id

    -- last message per room (single scan)
    LEFT JOIN (
        SELECT cm1.room_id, cm1.message_text, cm1.created_at
        FROM chat_messages cm1
        INNER JOIN (
            SELECT room_id, MAX(id) AS max_id
            FROM chat_messages
            GROUP BY room_id
        ) cm2 ON cm1.id = cm2.max_id
    ) lm ON lm.room_id = cr.id

    -- unread count per room (single scan)
    LEFT JOIN (
        SELECT room_id, COUNT(*) AS unread_count
        FROM chat_messages
        WHERE sender_id != ? AND is_read = 0
        GROUP BY room_id
    ) uc ON uc.room_id = cr.id

    WHERE {$filterField} = ?
    ORDER BY COALESCE(lm.created_at, c.updated_at) DESC
");

$stmt->bind_param('ii', $userId, $userId);
$stmt->execute();
$result = $stmt->get_result();


// ── Build response ───────────────────────────────────────────────────────────
$conversations = [];

while ($row = $result->fetch_assoc()) {
    // Skip contracts that don't have a chat room yet
    if (empty($row['room_id'])) {
        continue;
    }

    // Online check: active within last 120 seconds
    $isOnline = false;
    if (!empty($row['other_last_active'])) {
        $lastActive = strtotime($row['other_last_active']);
        $isOnline   = (time() - $lastActive) < 120;
    }

    $conversations[] = [
        'room_id'       => $row['room_id'] ? (int) $row['room_id'] : null,
        'contract_id'   => (int) $row['contract_id'],
        'other_user'    => [
            'id'         => (int) $row['other_id'],
            'name'       => htmlspecialchars($row['other_name'], ENT_QUOTES, 'UTF-8'),
            'image'      => _roomListProfileImage($row['other_image']),
            'is_online'  => $isOnline,
            'last_seen'  => $row['other_last_active'],
        ],
        'job_title'         => htmlspecialchars($row['job_title'], ENT_QUOTES, 'UTF-8'),
        'last_message'      => $row['last_message']
            ? htmlspecialchars($row['last_message'], ENT_QUOTES, 'UTF-8')
            : null,
        'last_message_time' => $row['last_message_time'],
        'unread_count'      => (int) ($row['unread_count'] ?? 0),
        'contract_status'   => $row['contract_status'],
        'budget'            => (float) $row['total_budget'],
    ];
}

$stmt->close();

echo json_encode([
    'success'       => true,
    'conversations' => $conversations,
]);
