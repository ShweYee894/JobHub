<?php
/**
 * Typing Service - Handles typing indicators.
 */

class TypingService
{
    private $conn;

    public function __construct($conn)
    {
        $this->conn = $conn;
        $this->ensureTableExists();
    }

    /**
     * Ensure typing_indicators table exists.
     */
    private function ensureTableExists(): void
    {
        try {
            $sql = "
                CREATE TABLE IF NOT EXISTS typing_indicators (
                    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                    room_id INT UNSIGNED NOT NULL,
                    user_id INT UNSIGNED NOT NULL,
                    is_typing TINYINT(1) DEFAULT 0,
                    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                    UNIQUE KEY unique_room_user (room_id, user_id),
                    FOREIGN KEY (room_id) REFERENCES chat_rooms(id) ON DELETE CASCADE,
                    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
            ";
            $this->conn->query($sql);
        } catch (Exception $e) {
            error_log("TypingService::ensureTableExists error: " . $e->getMessage());
        }
    }

    /**
     * Set typing status for a user in a room.
     */
    public function setTyping(int $roomId, int $userId, bool $isTyping): bool
    {
        try {
            $typing = $isTyping ? 1 : 0;

            $stmt = $this->conn->prepare("
                INSERT INTO typing_indicators (room_id, user_id, is_typing, updated_at)
                VALUES (?, ?, ?, NOW())
                ON DUPLICATE KEY UPDATE is_typing = ?, updated_at = NOW()
            ");
            $stmt->bind_param("iiii", $roomId, $userId, $typing, $typing);
            $stmt->execute();

            return true;
        } catch (Exception $e) {
            error_log("TypingService::setTyping error: " . $e->getMessage());
            return false;
        } finally {
            if (isset($stmt)) {
                $stmt->close();
            }
        }
    }

    /**
     * Get typing status for users in a room, excluding a specific user.
     */
    public function getTypingStatus(int $roomId, int $excludeUserId): array
    {
        try {
            $stmt = $this->conn->prepare("
                SELECT ti.user_id, u.name, ti.is_typing, ti.updated_at
                FROM typing_indicators ti
                JOIN users u ON ti.user_id = u.id
                WHERE ti.room_id = ? AND ti.user_id != ? AND ti.is_typing = 1
                  AND ti.updated_at > DATE_SUB(NOW(), INTERVAL 5 SECOND)
            ");
            $stmt->bind_param("ii", $roomId, $excludeUserId);
            $stmt->execute();
            $result = $stmt->get_result();

            $typers = [];
            while ($row = $result->fetch_assoc()) {
                $typers[] = [
                    'user_id' => (int)$row['user_id'],
                    'name' => $row['name'],
                    'is_typing' => (int)$row['is_typing'],
                    'updated_at' => $row['updated_at'],
                ];
            }

            return $typers;
        } catch (Exception $e) {
            error_log("TypingService::getTypingStatus error: " . $e->getMessage());
            return [];
        } finally {
            if (isset($stmt)) {
                $stmt->close();
            }
        }
    }

    /**
     * Clear stale typing indicators (older than 10 seconds).
     */
    public function clearStaleIndicators(): bool
    {
        try {
            $stmt = $this->conn->prepare("
                UPDATE typing_indicators 
                SET is_typing = 0 
                WHERE is_typing = 1 AND updated_at < DATE_SUB(NOW(), INTERVAL 10 SECOND)
            ");
            $stmt->execute();
            $stmt->close();

            return true;
        } catch (Exception $e) {
            error_log("TypingService::clearStaleIndicators error: " . $e->getMessage());
            return false;
        }
    }
}
