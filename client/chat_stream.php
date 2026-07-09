<?php
/**
 * Client Chat SSE Stream
 *
 * Server-Sent Events endpoint that continuously monitors chat_messages
 * for new records in the selected room and streams them to the browser.
 *
 * GET Parameters: room_id, last_id (optional)
 * Events: connected, message, typing, heartbeat
 */
session_start();

if (!isset($_SESSION['user_id']) || ($_SESSION['user_role'] ?? '') !== 'client') {
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

$clientId = (int) $_SESSION['user_id'];

if (!isset($_GET['room_id']) || !is_numeric($_GET['room_id'])) {
    http_response_code(400);
    echo json_encode(['error' => 'Invalid room_id parameter']);
    exit;
}

$roomId = (int) $_GET['room_id'];
$lastId = isset($_GET['last_id']) && is_numeric($_GET['last_id']) ? (int) $_GET['last_id'] : 0;

require_once __DIR__ . '/../config/db.php';

$stmt = $conn->prepare("
    SELECT cr.id FROM chat_rooms cr
    INNER JOIN contracts c ON cr.contract_id = c.id
    WHERE cr.id = ? AND c.client_id = ?
");
$stmt->bind_param('ii', $roomId, $clientId);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    $stmt->close();
    http_response_code(403);
    echo json_encode(['error' => 'Room not found or access denied']);
    exit;
}
$stmt->close();

ignore_user_abort(true);
header('Content-Type: text/event-stream');
header('Cache-Control: no-cache');
header('Connection: keep-alive');
header('X-Accel-Buffering: no');
header('Access-Control-Allow-Origin: *');
set_time_limit(0);

echo "event: connected\ndata: {\"status\":\"connected\",\"room_id\":$roomId}\n\n";
flush();

$updateAct = $conn->prepare("UPDATE users SET updated_at = NOW() WHERE id = ?");
$updateAct->bind_param('i', $clientId);
$updateAct->execute();
$updateAct->close();

$heartbeatCount = 0;
$lastTypingState = false;

while (true) {
    if (connection_aborted()) break;

    $msgStmt = $conn->prepare("
        SELECT cm.id, cm.sender_id, cm.message_text, cm.is_read, cm.created_at, cm.payload,
               u.name AS sender_name, u.profile_image AS sender_image
        FROM chat_messages cm
        INNER JOIN users u ON cm.sender_id = u.id
        WHERE cm.room_id = ? AND cm.id > ?
        ORDER BY cm.id ASC
    ");
    $msgStmt->bind_param('ii', $roomId, $lastId);
    $msgStmt->execute();
    $msgResult = $msgStmt->get_result();

    $newMessages = [];
    while ($row = $msgResult->fetch_assoc()) {
        $payload = null;
        if (!empty($row['payload'])) {
            $decoded = json_decode($row['payload'], true);
            $payload = $decoded ?: null;
        }

        $newMessages[] = [
            'id'           => (int) $row['id'],
            'sender_id'    => (int) $row['sender_id'],
            'sender_name'  => htmlspecialchars($row['sender_name'], ENT_QUOTES, 'UTF-8'),
            'sender_image' => $row['sender_image'] ? htmlspecialchars($row['sender_image'], ENT_QUOTES, 'UTF-8') : null,
            'message_text' => htmlspecialchars($row['message_text'], ENT_QUOTES, 'UTF-8'),
            'is_read'      => (int) $row['is_read'],
            'created_at'   => $row['created_at'],
            'payload'      => $payload
        ];
    }
    $msgStmt->close();

    if (!empty($newMessages)) {
        foreach ($newMessages as $msg) {
            echo "event: message\n";
            echo "data: " . json_encode($msg) . "\n\n";
            $lastId = $msg['id'];
        }
        flush();
    }

    $typingStmt = $conn->prepare("
        SELECT ti.user_id, u.name AS user_name
        FROM typing_indicators ti
        INNER JOIN users u ON ti.user_id = u.id
        WHERE ti.room_id = ? AND ti.user_id != ? AND ti.is_typing = 1
        AND TIMESTAMPDIFF(SECOND, ti.updated_at, NOW()) < 10
    ");
    $typingStmt->bind_param('ii', $roomId, $clientId);
    $typingStmt->execute();
    $typingResult = $typingStmt->get_result();
    $typingRow = $typingResult->fetch_assoc();
    $typingStmt->close();

    $isTyping = ($typingRow !== null);
    if ($isTyping !== $lastTypingState) {
        if ($isTyping) {
            echo "event: typing\n";
            echo "data: " . json_encode([
                'user_id'   => (int) $typingRow['user_id'],
                'user_name' => htmlspecialchars($typingRow['user_name'], ENT_QUOTES, 'UTF-8'),
                'is_typing' => true
            ]) . "\n\n";
        } else {
            echo "event: typing\n";
            echo "data: {\"is_typing\":false}\n\n";
        }
        flush();
        $lastTypingState = $isTyping;
    }

    $heartbeatCount++;
    if ($heartbeatCount >= 5) {
        echo "event: heartbeat\ndata: {\"time\":" . time() . "}\n\n";
        flush();
        $heartbeatCount = 0;

        $updateAct = $conn->prepare("UPDATE users SET updated_at = NOW() WHERE id = ?");
        $updateAct->bind_param('i', $clientId);
        $updateAct->execute();
        $updateAct->close();
    }

    sleep(2);
}

$conn->close();
