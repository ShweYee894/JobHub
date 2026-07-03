<?php
/**
 * Conversation Service - Handles conversation listing.
 */

class ConversationService
{
    private $conn;
    private $messageService;

    public function __construct($conn)
    {
        $this->conn = $conn;
        $this->messageService = new MessageService($conn);
    }

    /**
     * Get conversations for a client.
     */
    public function getConversationsForClient(int $clientId): array
    {
        try {
            $stmt = $this->conn->prepare("
                SELECT cr.id AS room_id,
                       c.id AS contract_id,
                       c.freelancer_id,
                       u.name AS other_name,
                       u.profile_image AS other_image,
                       u.id AS other_id,
                       j.title AS job_title
                FROM chat_rooms cr
                JOIN contracts c ON cr.contract_id = c.id
                JOIN users u ON c.freelancer_id = u.id
                JOIN jobs j ON c.job_id = j.id
                WHERE c.client_id = ?
                ORDER BY cr.created_at DESC
            ");
            $stmt->bind_param("i", $clientId);
            $stmt->execute();
            $result = $stmt->get_result();

            return $this->buildConversations($result, $clientId);
        } catch (Exception $e) {
            error_log("ConversationService::getConversationsForClient error: " . $e->getMessage());
            return [];
        } finally {
            if (isset($stmt)) {
                $stmt->close();
            }
        }
    }

    /**
     * Get conversations for a freelancer.
     */
    public function getConversationsForFreelancer(int $freelancerId): array
    {
        try {
            $stmt = $this->conn->prepare("
                SELECT cr.id AS room_id,
                       c.id AS contract_id,
                       c.client_id,
                       u.name AS other_name,
                       u.profile_image AS other_image,
                       u.id AS other_id,
                       j.title AS job_title
                FROM chat_rooms cr
                JOIN contracts c ON cr.contract_id = c.id
                JOIN users u ON c.client_id = u.id
                JOIN jobs j ON c.job_id = j.id
                WHERE c.freelancer_id = ?
                ORDER BY cr.created_at DESC
            ");
            $stmt->bind_param("i", $freelancerId);
            $stmt->execute();
            $result = $stmt->get_result();

            return $this->buildConversations($result, $freelancerId);
        } catch (Exception $e) {
            error_log("ConversationService::getConversationsForFreelancer error: " . $e->getMessage());
            return [];
        } finally {
            if (isset($stmt)) {
                $stmt->close();
            }
        }
    }

    /**
     * Build conversation array with last message and unread count.
     */
    private function buildConversations($result, int $currentUserId): array
    {
        $conversations = [];

        while ($row = $result->fetch_assoc()) {
            $roomId = (int)$row['room_id'];

            $lastMessage = $this->getLastMessage($roomId);
            $unreadCount = $this->messageService->getUnreadCount($roomId, $currentUserId);

            $conversations[] = [
                'room_id' => $roomId,
                'contract_id' => (int)$row['contract_id'],
                'other_user' => [
                    'id' => (int)$row['other_id'],
                    'name' => $row['other_name'],
                    'image' => $row['other_image'],
                ],
                'job_title' => $row['job_title'],
                'last_message' => $lastMessage['text'] ?? null,
                'last_message_time' => $lastMessage['time'] ?? null,
                'unread_count' => $unreadCount,
            ];
        }

        return $conversations;
    }

    /**
     * Get last message for a room.
     */
    private function getLastMessage(int $roomId): ?array
    {
        try {
            $stmt = $this->conn->prepare("
                SELECT message_text, created_at
                FROM chat_messages
                WHERE room_id = ?
                ORDER BY created_at DESC
                LIMIT 1
            ");
            $stmt->bind_param("i", $roomId);
            $stmt->execute();
            $result = $stmt->get_result();

            if ($result->num_rows > 0) {
                $row = $result->fetch_assoc();
                return [
                    'text' => $row['message_text'],
                    'time' => $row['created_at'],
                ];
            }

            return null;
        } catch (Exception $e) {
            error_log("ConversationService::getLastMessage error: " . $e->getMessage());
            return null;
        } finally {
            if (isset($stmt)) {
                $stmt->close();
            }
        }
    }
}
