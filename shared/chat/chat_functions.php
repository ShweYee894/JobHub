<?php
/**
 * Chat Functions
 *
 * Reusable procedural functions for chat operations.
 * All functions use mysqli prepared statements for SQL injection prevention.
 *
 * Parameters:
 *   $conn   - mysqli connection object (passed explicitly, not global)
 *   $roomId - int, chat room ID (chat_messages.room_id / chat_rooms.id)
 *   $userId - int, current user ID (users.id)
 *   $role   - string, user role ('client' or 'freelancer')
 */


// ── Profile Image Helper ─────────────────────────────────────────────────────
/**
 * Resolves a raw profile image filename from the DB into a usable relative path.
 * Falls back to the default placeholder if the file doesn't exist on disk.
 */
function chat_resolve_profile_image(?string $filename): ?string
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


// ── Room Lookup ──────────────────────────────────────────────────────────────

/**
 * chat_get_room_by_contract
 *
 * Finds the chat room associated with a contract.
 * Each contract has at most one chat room (enforced by UNIQUE constraint).
 *
 * @param  mysqli      $conn       Database connection
 * @param  int         $contractId Contract ID (contracts.id)
 * @return int|null    Room ID on success, null if no room exists
 */
function chat_get_room_by_contract(mysqli $conn, int $contractId): ?int
{
    $stmt = $conn->prepare(
        'SELECT id FROM chat_rooms WHERE contract_id = ? LIMIT 1'
    );
    if (!$stmt) {
        error_log('chat_get_room_by_contract prepare failed: ' . $conn->error);
        return null;
    }

    $stmt->bind_param('i', $contractId);
    $stmt->execute();
    $result = $stmt->get_result();
    $row = $result->fetch_assoc();
    $stmt->close();

    return $row ? (int) $row['id'] : null;
}


// ── Room Creation ────────────────────────────────────────────────────────────

/**
 * chat_create_room
 *
 * Creates a chat room for a contract if one does not already exist.
 * Uses a transaction to prevent race conditions from duplicate inserts.
 *
 * @param  mysqli   $conn       Database connection
 * @param  int      $contractId Contract ID (contracts.id)
 * @return int|false            Room ID on success, false on failure
 */
function chat_create_room(mysqli $conn, int $contractId): int|false
{
    // Check if room already exists
    $existing = chat_get_room_by_contract($conn, $contractId);
    if ($existing !== null) {
        return $existing;
    }

    // Verify the contract exists and is active
    $check = $conn->prepare(
        'SELECT id FROM contracts WHERE id = ? AND status = ? LIMIT 1'
    );
    if (!$check) {
        error_log('chat_create_room check prepare failed: ' . $conn->error);
        return false;
    }

    $active = 'active';
    $check->bind_param('is', $contractId, $active);
    $check->execute();
    $result = $check->get_result();
    $contract = $result->fetch_assoc();
    $check->close();

    if (!$contract) {
        error_log("chat_create_room: contract {$contractId} not found or not active");
        return false;
    }

    // Insert room in a transaction
    $conn->begin_transaction();
    try {
        $stmt = $conn->prepare(
            'INSERT INTO chat_rooms (contract_id, created_at) VALUES (?, NOW())'
        );
        if (!$stmt) {
            throw new Exception('prepare failed: ' . $conn->error);
        }

        $stmt->bind_param('i', $contractId);
        $stmt->execute();

        $roomId = $stmt->insert_id;
        $stmt->close();

        if ($roomId <= 0) {
            throw new Exception('insert_id invalid for contract ' . $contractId);
        }

        $conn->commit();
        return $roomId;
    } catch (Exception $e) {
        $conn->rollback();

        // Duplicate key — another process inserted the room first
        if (str_contains($e->getMessage(), 'Duplicate entry') || $conn->errno === 1062) {
            $retry = chat_get_room_by_contract($conn, $contractId);
            if ($retry !== null) return $retry;
        }

        error_log('chat_create_room failed: ' . $e->getMessage());
        return false;
    }
}


// ── Ownership / Access Verification ──────────────────────────────────────────

/**
 * chat_verify_room_access
 *
 * Checks whether a user has access to a chat room.
 * Access is granted if the user is the client or freelancer
 * on the contract linked to the room.
 *
 * @param  mysqli $conn   Database connection
 * @param  int    $roomId Chat room ID (chat_rooms.id)
 * @param  int    $userId User ID to verify (users.id)
 * @param  string $role   User role ('client', 'freelancer', or 'admin')
 * @return bool           true if user has access, false otherwise
 */
function chat_verify_room_access(mysqli $conn, int $roomId, int $userId, string $role): bool
{
    // Admins have access to all rooms
    if ($role === 'admin') {
        $stmt = $conn->prepare(
            'SELECT id FROM chat_rooms WHERE id = ? LIMIT 1'
        );
        if (!$stmt) {
            error_log('chat_verify_room_access prepare failed: ' . $conn->error);
            return false;
        }
        $stmt->bind_param('i', $roomId);
        $stmt->execute();
        $result = $stmt->get_result();
        $hasAccess = $result->num_rows > 0;
        $stmt->close();
        return $hasAccess;
    }

    // For clients and freelancers, verify ownership via the contract
    if ($role === 'client') {
        $stmt = $conn->prepare(
            'SELECT cr.id
             FROM chat_rooms cr
             JOIN contracts c ON cr.contract_id = c.id
             WHERE cr.id = ? AND c.client_id = ?
             LIMIT 1'
        );
    } else {
        $stmt = $conn->prepare(
            'SELECT cr.id
             FROM chat_rooms cr
             JOIN contracts c ON cr.contract_id = c.id
             WHERE cr.id = ? AND c.freelancer_id = ?
             LIMIT 1'
        );
    }
    if (!$stmt) {
        error_log('chat_verify_room_access prepare failed: ' . $conn->error);
        return false;
    }

    $stmt->bind_param('ii', $roomId, $userId);
    $stmt->execute();
    $result = $stmt->get_result();
    $hasAccess = $result->num_rows > 0;
    $stmt->close();

    return $hasAccess;
}


// ── Get Messages ─────────────────────────────────────────────────────────────

/**
 * chat_get_messages
 *
 * Retrieves messages for a room with pagination.
 * Returns messages in ascending order (oldest first) for display.
 * Each message includes sender name and profile image.
 *
 * @param  mysqli $conn    Database connection
 * @param  int    $roomId  Chat room ID (chat_rooms.id)
 * @param  int    $limit   Maximum number of messages to return (default 50)
 * @param  int    $offset  Number of messages to skip for pagination (default 0)
 * @return array           Array of message associative arrays
 */
function chat_get_messages(mysqli $conn, int $roomId, int $limit = 50, int $offset = 0): array
{
    $stmt = $conn->prepare(
        'SELECT cm.id, cm.room_id, cm.sender_id, cm.message_text,
                cm.is_read, cm.created_at,
                u.name AS sender_name, u.profile_image AS sender_image
         FROM chat_messages cm
         JOIN users u ON cm.sender_id = u.id
         WHERE cm.room_id = ?
         ORDER BY cm.id DESC
         LIMIT ? OFFSET ?'
    );
    if (!$stmt) {
        error_log('chat_get_messages prepare failed: ' . $conn->error);
        return [];
    }

    $stmt->bind_param('iii', $roomId, $limit, $offset);
    $stmt->execute();
    $result = $stmt->get_result();

    $messages = [];
    while ($row = $result->fetch_assoc()) {
        $messages[] = [
            'id'           => (int) $row['id'],
            'room_id'      => (int) $row['room_id'],
            'sender_id'    => (int) $row['sender_id'],
            'sender_name'  => htmlspecialchars($row['sender_name'], ENT_QUOTES, 'UTF-8'),
            'sender_image' => chat_resolve_profile_image($row['sender_image']),
            'message_text' => htmlspecialchars($row['message_text'], ENT_QUOTES, 'UTF-8'),
            'is_read'      => (int) $row['is_read'],
            'created_at'   => $row['created_at'],
        ];
    }
    $stmt->close();

    // Reverse to ascending order (oldest first) for display
    return array_reverse($messages);
}


// ── Insert Message ───────────────────────────────────────────────────────────

/**
 * chat_insert_message
 *
 * Inserts a new message into a chat room.
 * The message text is stored as-is (callers should sanitize before calling).
 * Optional payload supports file attachments as JSON.
 *
 * @param  mysqli    $conn        Database connection
 * @param  int       $roomId      Chat room ID (chat_rooms.id)
 * @param  int       $senderId    Sender user ID (users.id)
 * @param  string    $messageText Message body text
 * @param  array|null $payload    Optional file attachment data
 *                                ['path' => string, 'name' => string, 'size' => int, 'type' => string]
 * @return int|null               Inserted message ID on success, null on failure
 */
function chat_insert_message(mysqli $conn, int $roomId, int $senderId, string $messageText, ?array $payload = null): ?int
{
    $stmt = $conn->prepare(
        'INSERT INTO chat_messages (room_id, sender_id, message_text, is_read, created_at)
         VALUES (?, ?, ?, 0, NOW())'
    );
    if (!$stmt) {
        error_log('chat_insert_message prepare failed: ' . $conn->error);
        return null;
    }

    $stmt->bind_param('iis', $roomId, $senderId, $messageText);
    $stmt->execute();

    $messageId = $stmt->insert_id > 0 ? (int) $stmt->insert_id : null;
    $stmt->close();

    if ($messageId === null) {
        error_log("chat_insert_message: failed to insert into room {$roomId} by sender {$senderId}");
    }

    return $messageId;
}


// ── Latest Message ───────────────────────────────────────────────────────────

/**
 * chat_get_latest_message
 *
 * Returns the most recent message in a chat room.
 * Useful for conversation list previews.
 *
 * @param  mysqli    $conn   Database connection
 * @param  int       $roomId Chat room ID (chat_rooms.id)
 * @return array|null        Message array with text and time, or null if room is empty
 */
function chat_get_latest_message(mysqli $conn, int $roomId): ?array
{
    $stmt = $conn->prepare(
        'SELECT cm.message_text, cm.created_at
         FROM chat_messages cm
         WHERE cm.room_id = ?
         ORDER BY cm.id DESC
         LIMIT 1'
    );
    if (!$stmt) {
        error_log('chat_get_latest_message prepare failed: ' . $conn->error);
        return null;
    }

    $stmt->bind_param('i', $roomId);
    $stmt->execute();
    $result = $stmt->get_result();
    $row = $result->fetch_assoc();
    $stmt->close();

    if (!$row) {
        return null;
    }

    return [
        'text' => $row['message_text'],
        'time' => $row['created_at'],
    ];
}


// ── Unread Count ─────────────────────────────────────────────────────────────

/**
 * chat_get_unread_count
 *
 * Counts unread messages in a room for a specific user.
 * Only counts messages sent by OTHER users (not the current user).
 *
 * @param  mysqli $conn   Database connection
 * @param  int    $roomId Chat room ID (chat_rooms.id)
 * @param  int    $userId User ID to count unread for (users.id)
 * @return int            Number of unread messages
 */
function chat_get_unread_count(mysqli $conn, int $roomId, int $userId): int
{
    $stmt = $conn->prepare(
        'SELECT COUNT(*) AS cnt
         FROM chat_messages
         WHERE room_id = ? AND sender_id != ? AND is_read = 0'
    );
    if (!$stmt) {
        error_log('chat_get_unread_count prepare failed: ' . $conn->error);
        return 0;
    }

    $stmt->bind_param('ii', $roomId, $userId);
    $stmt->execute();
    $result = $stmt->get_result();
    $row = $result->fetch_assoc();
    $stmt->close();

    return $row ? (int) $row['cnt'] : 0;
}


// ── Mark Messages Read ───────────────────────────────────────────────────────

/**
 * chat_mark_read
 *
 * Marks all unread messages from OTHER users in a room as read.
 * Called when a user opens a conversation.
 *
 * @param  mysqli $conn   Database connection
 * @param  int    $roomId Chat room ID (chat_rooms.id)
 * @param  int    $userId User who is reading (users.id)
 * @return int            Number of messages marked as read
 */
function chat_mark_read(mysqli $conn, int $roomId, int $userId): int
{
    $stmt = $conn->prepare(
        'UPDATE chat_messages
         SET is_read = 1
         WHERE room_id = ? AND sender_id != ? AND is_read = 0'
    );
    if (!$stmt) {
        error_log('chat_mark_read prepare failed: ' . $conn->error);
        return 0;
    }

    $stmt->bind_param('ii', $roomId, $userId);
    $stmt->execute();
    $affected = $stmt->affected_rows;
    $stmt->close();

    return $affected;
}


// ── Fetch Single Message ─────────────────────────────────────────────────────

/**
 * chat_get_message_by_id
 *
 * Fetches a single message with sender info.
 * Used after insert to return the complete message to the client.
 *
 * @param  mysqli    $conn      Database connection
 * @param  int       $messageId Message ID (chat_messages.id)
 * @return array|null           Message array, or null if not found
 */
function chat_get_message_by_id(mysqli $conn, int $messageId): ?array
{
    $stmt = $conn->prepare(
        'SELECT cm.id, cm.room_id, cm.sender_id, cm.message_text,
                cm.is_read, cm.created_at,
                u.name AS sender_name, u.profile_image AS sender_image
         FROM chat_messages cm
         JOIN users u ON cm.sender_id = u.id
         WHERE cm.id = ?
         LIMIT 1'
    );
    if (!$stmt) {
        error_log('chat_get_message_by_id prepare failed: ' . $conn->error);
        return null;
    }

    $stmt->bind_param('i', $messageId);
    $stmt->execute();
    $result = $stmt->get_result();
    $row = $result->fetch_assoc();
    $stmt->close();

    if (!$row) {
        return null;
    }

    return [
        'id'           => (int) $row['id'],
        'room_id'      => (int) $row['room_id'],
        'sender_id'    => (int) $row['sender_id'],
        'sender_name'  => htmlspecialchars($row['sender_name'], ENT_QUOTES, 'UTF-8'),
        'sender_image' => chat_resolve_profile_image($row['sender_image']),
        'message_text' => htmlspecialchars($row['message_text'], ENT_QUOTES, 'UTF-8'),
        'is_read'      => (int) $row['is_read'],
        'created_at'   => $row['created_at'],
    ];
}


// ── Update User Activity ─────────────────────────────────────────────────────

/**
 * chat_update_activity
 *
 * Updates the user's last activity timestamp.
 * Used by SSE streams and send-message to keep online status current.
 *
 * @param  mysqli $conn   Database connection
 * @param  int    $userId User ID (users.id)
 * @return bool           true on success, false on failure
 */
function chat_update_activity(mysqli $conn, int $userId): bool
{
    $stmt = $conn->prepare('UPDATE users SET updated_at = NOW() WHERE id = ?');
    if (!$stmt) {
        error_log('chat_update_activity prepare failed: ' . $conn->error);
        return false;
    }

    $stmt->bind_param('i', $userId);
    $success = $stmt->execute();
    $stmt->close();

    return $success;
}
