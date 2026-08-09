<?php
/**
 * sse.php
 *
 * Server-Sent Events endpoint for real-time chat.
 * Streams new messages and typing indicators for a single room.
 * Works for both client and freelancer — role detected from session.
 *
 * GET Parameters:
 *   room_id  (int, required) — chat room ID
 *   last_id  (int, optional) — last known message ID (for reconnection, default 0)
 *
 * Events:
 *   connected  — { status, room_id }
 *   message    — { id, sender_id, sender_name, ... }
 *   typing     — { user_id, user_name, is_typing }
 *   heartbeat  — { time }
 */

session_start();

// ── Authentication ───────────────────────────────────────────────────────────

if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

$userId = (int) $_SESSION['user_id'];
$role   = $_SESSION['user_role'] ?? '';

if ($role !== 'client' && $role !== 'freelancer') {
    http_response_code(403);
    echo json_encode(['error' => 'Invalid role']);
    exit;
}
if (!isset($_GET['room_id']) || !is_numeric($_GET['room_id'])) {
    http_response_code(400);
    echo json_encode(['error' => 'Missing or invalid room_id']);
    exit;
}

$roomId = (int) $_GET['room_id'];
if ($roomId <= 0) {
    http_response_code(400);
    echo json_encode(['error' => 'Invalid room_id']);
    exit;
}

// ── Validate last_id (reconnection support) ──────────────────────────────────
$lastId = isset($_GET['last_id']) && is_numeric($_GET['last_id'])
    ? (int) $_GET['last_id']
    : 0;

// ── Bootstrap ────────────────────────────────────────────────────────────────
require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/chat_functions.php';

// ── Verify room ownership ───────────────────────────────────────────────────
if (!chat_verify_room_access($conn, $roomId, $userId, $role)) {
    http_response_code(403);
    echo json_encode(['error' => 'Room not found or access denied']);
    exit;
}

// ── Release session lock ────────────────────────────────────────────────────
// All session data is captured. Close the session file so other AJAX
// requests (send_message, load_history, mark_read) don't block on it.
session_write_close();

// ── SSE headers ──────────────────────────────────────────────────────────────
ignore_user_abort(true);
header('Content-Type: text/event-stream');
header('Cache-Control: no-cache');
header('Connection: keep-alive');
header('X-Accel-Buffering: no');
header('Access-Control-Allow-Origin: http://localhost');
set_time_limit(0);

// ── Send initial connected event ─────────────────────────────────────────────
echo "event: connected\ndata: " . json_encode([
    'status'  => 'connected',
    'room_id' => $roomId,
]) . "\n\n";
flush();

// ── Update user activity on connect ──────────────────────────────────────────
chat_update_activity($conn, $userId);

// ── State tracking ───────────────────────────────────────────────────────────
$heartbeatCount   = 0;
$startTime        = time();
$maxLifetime      = 1800; // 30 minutes max connection
$heartbeatEvery   = 5;    // heartbeat every 5 cycles (10 seconds)
$cycleSleep       = 2;    // seconds between polls

// ── Main loop ────────────────────────────────────────────────────────────────
while (true) {

    // ── Check if client disconnected ────────────────────────────────────
    if (connection_aborted()) {
        break;
    }

    // ── Check max connection lifetime ───────────────────────────────────
    if ((time() - $startTime) >= $maxLifetime) {
        echo "event: disconnected\ndata: " . json_encode([
            'reason' => 'max_lifetime',
        ]) . "\n\n";
        flush();
        break;
    }

    // ── Fetch new messages (id > last_id) ───────────────────────────────
    //    This prevents duplicates: only messages newer than what the
    //    client already has are streamed.
    $msgStmt = $conn->prepare(
        'SELECT cm.id, cm.sender_id, cm.message_text, cm.is_read,
                cm.created_at,
                u.name AS sender_name, u.profile_image AS sender_image
         FROM chat_messages cm
         JOIN users u ON cm.sender_id = u.id
         WHERE cm.room_id = ? AND cm.id > ?
         ORDER BY cm.id ASC'
    );

    if (!$msgStmt) {
        // Reconnect on query failure
        sleep($cycleSleep);
        continue;
    }

    $msgStmt->bind_param('ii', $roomId, $lastId);
    $msgStmt->execute();
    $msgResult = $msgStmt->get_result();

    // Collect new messages
    $newMessages = [];
    while ($row = $msgResult->fetch_assoc()) {
        $newMessages[] = [
            'id'           => (int) $row['id'],
            'sender_id'    => (int) $row['sender_id'],
            'sender_name'  => htmlspecialchars($row['sender_name'], ENT_QUOTES, 'UTF-8'),
            'sender_image' => chat_resolve_profile_image($row['sender_image']),
            'message_text' => htmlspecialchars($row['message_text'], ENT_QUOTES, 'UTF-8'),
            'is_read'      => (int) $row['is_read'],
            'created_at'   => $row['created_at'],
        ];
    }
    $msgStmt->close();

    // ── Stream new messages ──────────────────────────────────────────────
    if (!empty($newMessages)) {
        foreach ($newMessages as $msg) {
            echo "event: message\n";
            echo "data: " . json_encode($msg) . "\n\n";

            // Advance lastId — prevents re-sending on next cycle
            if ($msg['id'] > $lastId) {
                $lastId = $msg['id'];
            }
        }
        flush();
    }

    // ── Heartbeat ───────────────────────────────────────────────────────
    $heartbeatCount++;
    if ($heartbeatCount >= $heartbeatEvery) {
        echo "event: heartbeat\n";
        echo "data: " . json_encode(['time' => time()]) . "\n\n";
        flush();

        $heartbeatCount = 0;

        // Update activity on heartbeat (keeps online status current)
        chat_update_activity($conn, $userId);
    }

    // ── Sleep between polls ─────────────────────────────────────────────
    sleep($cycleSleep);
}
