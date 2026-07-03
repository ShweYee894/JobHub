<?php
/**
 * Freelancer Chat SSE Stream
 * 
 * Server-Sent Events endpoint for real-time message streaming.
 * GET Parameters: room_id, last_id (optional)
 * Events: connected, message, typing, heartbeat
 */
session_start();

if (!isset($_SESSION['user_id']) || ($_SESSION['user_role'] ?? '') !== 'freelancer') {
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

$freelancerId = (int) $_SESSION['user_id'];

if (!isset($_GET['room_id']) || !is_numeric($_GET['room_id'])) {
    http_response_code(400);
    echo json_encode(['error' => 'Invalid room_id']);
    exit;
}

$roomId = (int) $_GET['room_id'];
$lastId = isset($_GET['last_id']) && is_numeric($_GET['last_id']) ? (int) $_GET['last_id'] : 0;

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

ignore_user_abort(false);
header('Content-Type: text/event-stream');
header('Cache-Control: no-cache');
header('Connection: keep-alive');
header('X-Accel-Buffering: no');
set_time_limit(0);

echo "event: connected\ndata: {\"status\":\"connected\",\"room_id\":$roomId}\n\n";
flush();

$updateAct = $conn->prepare("UPDATE users SET updated_at = NOW() WHERE id = ?");
$updateAct->bind_param('i', $freelancerId);
$updateAct->execute();
$updateAct->close();

$heartbeatCount = 0;

while (true) {
    if (connection_aborted()) break;

    // Poll for new messages
    $msgStmt = $conn->prepare("
        SELECT cm.id, cm.sender_id, cm.message_text, cm.is_read, cm.created_at, cm.payload,
               u.name AS sender_name, u.profile_image AS sender_image
        FROM chat_messages cm
        JOIN users u ON cm.sender_id = u.id
        WHERE cm.room_id = ? AND cm.id > ?
        ORDER BY cm.created_at ASC
    ");
    $msgStmt->bind_param('ii', $roomId, $lastId);
    $msgStmt->execute();
    $msgResult = $msgStmt->get_result();

    if ($msgResult->num_rows > 0) {
        while ($row = $msgResult->fetch_assoc()) {
            $payload = null;
            if (!empty($row['payload'])) {
                $decoded = json_decode($row['payload'], true);
                $payload = $decoded ?: null;
            }

            $message = [
                'id'            => (int) $row['id'],
                'sender_id'     => (int) $row['sender_id'],
                'sender_name'   => htmlspecialchars($row['sender_name'], ENT_QUOTES, 'UTF-8'),
                'sender_image'  => $row['sender_image'] ? htmlspecialchars($row['sender_image'], ENT_QUOTES, 'UTF-8') : null,
                'message_text'  => htmlspecialchars($row['message_text'], ENT_QUOTES, 'UTF-8'),
                'is_read'       => (int) $row['is_read'],
                'created_at'    => $row['created_at'],
                'payload'       => $payload
            ];

            echo "id: " . $row['id'] . "\n";
            echo "event: message\n";
            echo "data: " . json_encode($message) . "\n\n";
            flush();

            $lastId = (int) $row['id'];
        }
    }
    $msgStmt->close();

    // Check typing indicators from client
    $typingStmt = $conn->prepare("
        SELECT ti.user_id, u.name AS user_name
        FROM typing_indicators ti
        JOIN users u ON ti.user_id = u.id
        WHERE ti.room_id = ? AND ti.user_id != ? AND ti.is_typing = 1
        AND TIMESTAMPDIFF(SECOND, ti.updated_at, NOW()) < 10
    ");
    $typingStmt->bind_param('ii', $roomId, $freelancerId);
    $typingStmt->execute();
    $typingResult = $typingStmt->get_result();

    if ($typingRow = $typingResult->fetch_assoc()) {
        echo "event: typing\n";
        echo "data: " . json_encode([
            'user_id'   => (int) $typingRow['user_id'],
            'user_name' => htmlspecialchars($typingRow['user_name'], ENT_QUOTES, 'UTF-8'),
            'is_typing' => true
        ]) . "\n\n";
        flush();
    } else {
        echo "event: typing\n";
        echo "data: " . json_encode(['is_typing' => false]) . "\n\n";
        flush();
    }
    $typingStmt->close();

    $heartbeatCount++;
    if ($heartbeatCount >= 5) {
        echo "event: heartbeat\ndata: {\"time\":" . time() . "}\n\n";
        flush();
        $heartbeatCount = 0;

        $updateAct = $conn->prepare("UPDATE users SET updated_at = NOW() WHERE id = ?");
        $updateAct->bind_param('i', $freelancerId);
        $updateAct->execute();
        $updateAct->close();
    }

    sleep(2);
}

$conn->close();
