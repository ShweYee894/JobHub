<?php
/**
 * Message Service - Handles chat message operations.
 */

class MessageService
{
    private $conn;

    public function __construct($conn)
    {
        $this->conn = $conn;
    }

    /**
     * Get messages for a room with pagination.
     */
    public function getMessages(int $roomId, int $limit = 50, int $offset = 0): array
    {
        try {
            $stmt = $this->conn->prepare("
                SELECT cm.id, cm.room_id, cm.sender_id, cm.message_text, cm.is_read, cm.created_at,
                       u.name AS sender_name, u.profile_image AS sender_image
                FROM chat_messages cm
                JOIN users u ON cm.sender_id = u.id
                WHERE cm.room_id = ?
                ORDER BY cm.created_at DESC
                LIMIT ? OFFSET ?
            ");
            $stmt->bind_param("iii", $roomId, $limit, $offset);
            $stmt->execute();
            $result = $stmt->get_result();

            $messages = [];
            while ($row = $result->fetch_assoc()) {
                $messages[] = [
                    'id' => (int)$row['id'],
                    'room_id' => (int)$row['room_id'],
                    'sender_id' => (int)$row['sender_id'],
                    'sender_name' => $row['sender_name'],
                    'sender_image' => $row['sender_image'],
                    'message_text' => $row['message_text'],
                    'is_read' => (int)$row['is_read'],
                    'created_at' => $row['created_at'],
                ];
            }

            return array_reverse($messages);
        } catch (Exception $e) {
            error_log("MessageService::getMessages error: " . $e->getMessage());
            return [];
        } finally {
            if (isset($stmt)) {
                $stmt->close();
            }
        }
    }

    /**
     * Insert a new message.
     */
    public function insertMessage(int $roomId, int $senderId, string $messageText): ?int
    {
        try {
            $stmt = $this->conn->prepare("
                INSERT INTO chat_messages (room_id, sender_id, message_text, is_read, created_at)
                VALUES (?, ?, ?, 0, NOW())
            ");
            $stmt->bind_param("iis", $roomId, $senderId, $messageText);
            $stmt->execute();

            if ($stmt->insert_id > 0) {
                return (int)$stmt->insert_id;
            }

            return null;
        } catch (Exception $e) {
            error_log("MessageService::insertMessage error: " . $e->getMessage());
            return null;
        } finally {
            if (isset($stmt)) {
                $stmt->close();
            }
        }
    }

    /**
     * Mark messages as read for a specific reader.
     */
    public function markMessagesAsRead(int $roomId, int $readerId): bool
    {
        try {
            $stmt = $this->conn->prepare("
                UPDATE chat_messages 
                SET is_read = 1 
                WHERE room_id = ? AND sender_id != ? AND is_read = 0
            ");
            $stmt->bind_param("ii", $roomId, $readerId);
            $stmt->execute();

            return $stmt->affected_rows >= 0;
        } catch (Exception $e) {
            error_log("MessageService::markMessagesAsRead error: " . $e->getMessage());
            return false;
        } finally {
            if (isset($stmt)) {
                $stmt->close();
            }
        }
    }

    /**
     * Get unread message count for a user in a room.
     */
    public function getUnreadCount(int $roomId, int $userId): int
    {
        try {
            $stmt = $this->conn->prepare("
                SELECT COUNT(*) AS cnt
                FROM chat_messages
                WHERE room_id = ? AND sender_id != ? AND is_read = 0
            ");
            $stmt->bind_param("ii", $roomId, $userId);
            $stmt->execute();
            $result = $stmt->get_result();
            $row = $result->fetch_assoc();

            return (int)$row['cnt'];
        } catch (Exception $e) {
            error_log("MessageService::getUnreadCount error: " . $e->getMessage());
            return 0;
        } finally {
            if (isset($stmt)) {
                $stmt->close();
            }
        }
    }

    /**
     * Get new messages since a given message ID (for polling).
     */
    public function getNewMessages(int $roomId, int $lastMessageId): array
    {
        try {
            $stmt = $this->conn->prepare("
                SELECT cm.id, cm.room_id, cm.sender_id, cm.message_text, cm.is_read, cm.created_at,
                       u.name AS sender_name, u.profile_image AS sender_image
                FROM chat_messages cm
                JOIN users u ON cm.sender_id = u.id
                WHERE cm.room_id = ? AND cm.id > ?
                ORDER BY cm.created_at ASC
            ");
            $stmt->bind_param("ii", $roomId, $lastMessageId);
            $stmt->execute();
            $result = $stmt->get_result();

            $messages = [];
            while ($row = $result->fetch_assoc()) {
                $messages[] = [
                    'id' => (int)$row['id'],
                    'room_id' => (int)$row['room_id'],
                    'sender_id' => (int)$row['sender_id'],
                    'sender_name' => $row['sender_name'],
                    'sender_image' => $row['sender_image'],
                    'message_text' => $row['message_text'],
                    'is_read' => (int)$row['is_read'],
                    'created_at' => $row['created_at'],
                ];
            }

            return $messages;
        } catch (Exception $e) {
            error_log("MessageService::getNewMessages error: " . $e->getMessage());
            return [];
        } finally {
            if (isset($stmt)) {
                $stmt->close();
            }
        }
    }
}
