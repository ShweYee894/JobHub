<?php

/**
 * Notifications System
 * Unified notification service for all platform and chat notifications.
 * Uses the single `notifications` table.
 */

// ── Schema ──────────────────────────────────────────────────────────────

/**
 * Notifications table schema (for reference):
 *
 * CREATE TABLE IF NOT EXISTS notifications (
 *     id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 *     user_id INT UNSIGNED NOT NULL,
 *     type VARCHAR(50) NOT NULL,
 *     title VARCHAR(255) NOT NULL,
 *     message TEXT NOT NULL,
 *     link VARCHAR(255) DEFAULT NULL,
 *     is_read TINYINT(1) DEFAULT 0,
 *     created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
 *     FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
 *     INDEX idx_user_read (user_id, is_read),
 *     INDEX idx_user_type (user_id, type),
 *     INDEX idx_user_created (user_id, created_at)
 * ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
 */

// ── PlatformNotificationService ────────────────────────────────────────

class PlatformNotificationService
{
    private $conn;

    public function __construct($conn)
    {
        $this->conn = $conn;
    }

    /**
     * Create a notification for a user.
     */
    public function create(
        int $userId,
        string $type,
        string $title,
        string $message,
        ?string $link = null
    ): int|false {
        $stmt = $this->conn->prepare(
            'INSERT INTO notifications (user_id, type, title, message, link)
             VALUES (?, ?, ?, ?, ?)'
        );
        $stmt->bind_param('issss', $userId, $type, $title, $message, $link);
        $stmt->execute();
        $id = $stmt->insert_id;
        $stmt->close();

        return $id > 0 ? $id : false;
    }

    /**
     * Get notifications for a user, most recent first.
     */
    public function getForUser(int $userId, int $limit = 20, int $offset = 0): array
    {
        $stmt = $this->conn->prepare(
            'SELECT id, type, title, message, link, is_read, created_at
             FROM notifications
             WHERE user_id = ?
             ORDER BY created_at DESC
             LIMIT ? OFFSET ?'
        );
        $stmt->bind_param('iii', $userId, $limit, $offset);
        $stmt->execute();
        $result = $stmt->get_result();
        $notifications = [];
        while ($row = $result->fetch_assoc()) {
            $notifications[] = $row;
        }
        $stmt->close();

        return $notifications;
    }

    /**
     * Get count of unread notifications for a user.
     */
    public function getUnreadCount(int $userId): int
    {
        $stmt = $this->conn->prepare(
            'SELECT COUNT(*) AS cnt FROM notifications WHERE user_id = ? AND is_read = 0'
        );
        $stmt->bind_param('i', $userId);
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        return (int) ($row['cnt'] ?? 0);
    }

    /**
     * Mark a single notification as read.
     */
    public function markRead(int $id, int $userId): bool
    {
        $stmt = $this->conn->prepare(
            'UPDATE notifications SET is_read = 1 WHERE id = ? AND user_id = ?'
        );
        $stmt->bind_param('ii', $id, $userId);
        $stmt->execute();
        $affected = $stmt->affected_rows;
        $stmt->close();

        return $affected > 0;
    }

    /**
     * Mark all notifications for a user as read.
     */
    public function markAllRead(int $userId): int
    {
        $stmt = $this->conn->prepare(
            'UPDATE notifications SET is_read = 1 WHERE user_id = ? AND is_read = 0'
        );
        $stmt->bind_param('i', $userId);
        $stmt->execute();
        $affected = $stmt->affected_rows;
        $stmt->close();

        return $affected;
    }

    /**
     * Delete notifications older than a given number of days.
     */
    public function deleteOld(int $days = 90): int
    {
        $stmt = $this->conn->prepare(
            'DELETE FROM notifications WHERE created_at < DATE_SUB(NOW(), INTERVAL ? DAY)'
        );
        $stmt->bind_param('i', $days);
        $stmt->execute();
        $affected = $stmt->affected_rows;
        $stmt->close();

        return $affected;
    }
}
