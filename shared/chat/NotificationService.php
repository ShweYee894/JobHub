<?php
/**
 * Notification Service - Handles chat notifications.
 */

class NotificationService
{
    private $conn;

    public function __construct($conn)
    {
        $this->conn = $conn;
        $this->ensureTableExists();
    }

    /**
     * Ensure notifications table exists.
     */
    private function ensureTableExists(): void
    {
        try {
            $sql = "
                CREATE TABLE IF NOT EXISTS chat_notifications (
                    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                    user_id INT UNSIGNED NOT NULL,
                    type VARCHAR(50) NOT NULL,
                    message TEXT NOT NULL,
                    data JSON DEFAULT NULL,
                    is_read TINYINT(1) DEFAULT 0,
                    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
                    INDEX idx_user_read (user_id, is_read)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
            ";
            $this->conn->query($sql);
        } catch (Exception $e) {
            error_log("NotificationService::ensureTableExists error: " . $e->getMessage());
        }
    }

    /**
     * Create a new notification.
     */
    public function createNotification(int $userId, string $type, string $message, ?array $data = null): ?int
    {
        try {
            $jsonData = $data ? json_encode($data) : null;

            $stmt = $this->conn->prepare("
                INSERT INTO chat_notifications (user_id, type, message, data, is_read, created_at)
                VALUES (?, ?, ?, ?, 0, NOW())
            ");
            $stmt->bind_param("isss", $userId, $type, $message, $jsonData);
            $stmt->execute();

            if ($stmt->insert_id > 0) {
                return (int)$stmt->insert_id;
            }

            return null;
        } catch (Exception $e) {
            error_log("NotificationService::createNotification error: " . $e->getMessage());
            return null;
        } finally {
            if (isset($stmt)) {
                $stmt->close();
            }
        }
    }

    /**
     * Get unread notifications for a user.
     */
    public function getUnreadNotifications(int $userId): array
    {
        try {
            $stmt = $this->conn->prepare("
                SELECT id, user_id, type, message, data, is_read, created_at
                FROM chat_notifications
                WHERE user_id = ? AND is_read = 0
                ORDER BY created_at DESC
            ");
            $stmt->bind_param("i", $userId);
            $stmt->execute();
            $result = $stmt->get_result();

            $notifications = [];
            while ($row = $result->fetch_assoc()) {
                $notifications[] = [
                    'id' => (int)$row['id'],
                    'user_id' => (int)$row['user_id'],
                    'type' => $row['type'],
                    'message' => $row['message'],
                    'data' => $row['data'] ? json_decode($row['data'], true) : null,
                    'is_read' => (int)$row['is_read'],
                    'created_at' => $row['created_at'],
                ];
            }

            return $notifications;
        } catch (Exception $e) {
            error_log("NotificationService::getUnreadNotifications error: " . $e->getMessage());
            return [];
        } finally {
            if (isset($stmt)) {
                $stmt->close();
            }
        }
    }

    /**
     * Mark a single notification as read.
     */
    public function markNotificationRead(int $notificationId): bool
    {
        try {
            $stmt = $this->conn->prepare("
                UPDATE chat_notifications SET is_read = 1 WHERE id = ?
            ");
            $stmt->bind_param("i", $notificationId);
            $stmt->execute();

            $success = $stmt->affected_rows >= 0;
            $stmt->close();

            return $success;
        } catch (Exception $e) {
            error_log("NotificationService::markNotificationRead error: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Mark all notifications as read for a user.
     */
    public function markAllRead(int $userId): bool
    {
        try {
            $stmt = $this->conn->prepare("
                UPDATE chat_notifications SET is_read = 1 WHERE user_id = ? AND is_read = 0
            ");
            $stmt->bind_param("i", $userId);
            $stmt->execute();

            $success = $stmt->affected_rows >= 0;
            $stmt->close();

            return $success;
        } catch (Exception $e) {
            error_log("NotificationService::markAllRead error: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Get unread notification count for a user.
     */
    public function getUnreadCount(int $userId): int
    {
        try {
            $stmt = $this->conn->prepare("
                SELECT COUNT(*) AS cnt
                FROM chat_notifications
                WHERE user_id = ? AND is_read = 0
            ");
            $stmt->bind_param("i", $userId);
            $stmt->execute();
            $result = $stmt->get_result();
            $row = $result->fetch_assoc();

            return (int)$row['cnt'];
        } catch (Exception $e) {
            error_log("NotificationService::getUnreadCount error: " . $e->getMessage());
            return 0;
        } finally {
            if (isset($stmt)) {
                $stmt->close();
            }
        }
    }
}
