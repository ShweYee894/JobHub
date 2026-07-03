<?php
/**
 * Chat Service - Main service class that orchestrates chat operations.
 */

require_once __DIR__ . '/ChatValidator.php';
require_once __DIR__ . '/MessageService.php';
require_once __DIR__ . '/ConversationService.php';
require_once __DIR__ . '/TypingService.php';
require_once __DIR__ . '/NotificationService.php';

class ChatService
{
    private $conn;
    private $validator;
    private $messageService;
    private $conversationService;
    private $typingService;
    private $notificationService;

    public function __construct($conn)
    {
        $this->conn = $conn;
        $this->validator = new ChatValidator($conn);
        $this->messageService = new MessageService($conn);
        $this->conversationService = new ConversationService($conn);
        $this->typingService = new TypingService($conn);
        $this->notificationService = new NotificationService($conn);
    }

    /**
     * Get all conversations for a user.
     */
    public function getConversationList(int $userId, string $role): array
    {
        try {
            if ($role === 'client') {
                return $this->conversationService->getConversationsForClient($userId);
            } elseif ($role === 'freelancer') {
                return $this->conversationService->getConversationsForFreelancer($userId);
            }

            return [];
        } catch (Exception $e) {
            error_log("ChatService::getConversationList error: " . $e->getMessage());
            return [];
        }
    }

    /**
     * Load messages for a room.
     */
    public function loadMessages(int $roomId, int $userId, int $limit = 50, int $offset = 0): array
    {
        try {
            return $this->messageService->getMessages($roomId, $limit, $offset);
        } catch (Exception $e) {
            error_log("ChatService::loadMessages error: " . $e->getMessage());
            return [];
        }
    }

    /**
     * Send a message.
     */
    public function sendMessage(int $roomId, int $senderId, string $messageText): array
    {
        try {
            $errors = $this->validator->validateMessage($messageText);
            if (!empty($errors)) {
                return ['success' => false, 'errors' => $errors];
            }

            $sanitizedText = $this->validator->sanitizeMessage($messageText);
            $messageId = $this->messageService->insertMessage($roomId, $senderId, $sanitizedText);

            if ($messageId === null) {
                return ['success' => false, 'errors' => ['Failed to send message.']];
            }

            $message = $this->messageService->getMessages($roomId, 1, 0);
            $sentMessage = end($message) ?: null;

            $this->createMessageNotification($roomId, $senderId, $sanitizedText);

            return [
                'success' => true,
                'message_id' => $messageId,
                'message' => $sentMessage,
            ];
        } catch (Exception $e) {
            error_log("ChatService::sendMessage error: " . $e->getMessage());
            return ['success' => false, 'errors' => ['An unexpected error occurred.']];
        }
    }

    /**
     * Mark messages as read.
     */
    public function markRead(int $roomId, int $userId): bool
    {
        try {
            return $this->messageService->markMessagesAsRead($roomId, $userId);
        } catch (Exception $e) {
            error_log("ChatService::markRead error: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Get unread message count.
     */
    public function getUnreadCount(int $roomId, int $userId): int
    {
        try {
            return $this->messageService->getUnreadCount($roomId, $userId);
        } catch (Exception $e) {
            error_log("ChatService::getUnreadCount error: " . $e->getMessage());
            return 0;
        }
    }

    /**
     * Create a chat room for a contract.
     */
    public function createRoom(int $contractId): ?int
    {
        try {
            $existingRoom = $this->validator->validateRoom($contractId);
            if ($existingRoom !== null) {
                return $existingRoom;
            }

            if (!$this->validator->validateContract($contractId)) {
                return null;
            }

            $stmt = $this->conn->prepare("
                INSERT INTO chat_rooms (contract_id, created_at) VALUES (?, NOW())
            ");
            $stmt->bind_param("i", $contractId);
            $stmt->execute();

            $roomId = $stmt->insert_id > 0 ? (int)$stmt->insert_id : null;
            $stmt->close();

            return $roomId;
        } catch (Exception $e) {
            error_log("ChatService::createRoom error: " . $e->getMessage());
            return null;
        }
    }

    /**
     * Validate user access to a room.
     */
    public function validateAccess(int $roomId, int $userId, string $role): bool
    {
        try {
            return $this->validator->validateRoomAccess($roomId, $userId, $role);
        } catch (Exception $e) {
            error_log("ChatService::validateAccess error: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Set typing indicator.
     */
    public function setTyping(int $roomId, int $userId, bool $isTyping): bool
    {
        try {
            return $this->typingService->setTyping($roomId, $userId, $isTyping);
        } catch (Exception $e) {
            error_log("ChatService::setTyping error: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Get typing status for a room.
     */
    public function getTypingStatus(int $roomId, int $excludeUserId): array
    {
        try {
            return $this->typingService->getTypingStatus($roomId, $excludeUserId);
        } catch (Exception $e) {
            error_log("ChatService::getTypingStatus error: " . $e->getMessage());
            return [];
        }
    }

    /**
     * Get new messages since a given ID (for polling).
     */
    public function getNewMessages(int $roomId, int $lastMessageId): array
    {
        try {
            return $this->messageService->getNewMessages($roomId, $lastMessageId);
        } catch (Exception $e) {
            error_log("ChatService::getNewMessages error: " . $e->getMessage());
            return [];
        }
    }

    /**
     * Get unread notification count.
     */
    public function getNotificationCount(int $userId): int
    {
        try {
            return $this->notificationService->getUnreadCount($userId);
        } catch (Exception $e) {
            error_log("ChatService::getNotificationCount error: " . $e->getMessage());
            return 0;
        }
    }

    /**
     * Get total unread count across all rooms for a user.
     */
    public function getTotalUnreadCount(int $userId, string $role): int
    {
        try {
            $conversations = $this->getConversationList($userId, $role);
            $total = 0;

            foreach ($conversations as $conversation) {
                $total += $conversation['unread_count'];
            }

            return $total;
        } catch (Exception $e) {
            error_log("ChatService::getTotalUnreadCount error: " . $e->getMessage());
            return 0;
        }
    }

    /**
     * Create a notification for a new message.
     */
    private function createMessageNotification(int $roomId, int $senderId, string $messageText): void
    {
        try {
            $stmt = $this->conn->prepare("
                SELECT c.client_id, c.freelancer_id, j.title
                FROM chat_rooms cr
                JOIN contracts c ON cr.contract_id = c.id
                JOIN jobs j ON c.job_id = j.id
                WHERE cr.id = ?
            ");
            $stmt->bind_param("i", $roomId);
            $stmt->execute();
            $result = $stmt->get_result();

            if ($result->num_rows === 0) {
                return;
            }

            $room = $result->fetch_assoc();
            $stmt->close();

            $recipientId = ((int)$room['client_id'] === $senderId)
                ? (int)$room['freelancer_id']
                : (int)$room['client_id'];

            $truncatedMessage = strlen($messageText) > 100
                ? substr($messageText, 0, 100) . '...'
                : $messageText;

            $senderStmt = $this->conn->prepare("SELECT name FROM users WHERE id = ?");
            $senderStmt->bind_param("i", $senderId);
            $senderStmt->execute();
            $senderResult = $senderStmt->get_result();
            $sender = $senderResult->fetch_assoc();
            $senderStmt->close();

            $senderName = $sender['name'] ?? 'Someone';
            $notificationMessage = "$senderName sent a message in \"{$room['title']}\"";

            $this->notificationService->createNotification(
                $recipientId,
                'new_message',
                $notificationMessage,
                [
                    'room_id' => $roomId,
                    'sender_id' => $senderId,
                    'sender_name' => $senderName,
                    'job_title' => $room['title'],
                    'preview' => $truncatedMessage,
                ]
            );
        } catch (Exception $e) {
            error_log("ChatService::createMessageNotification error: " . $e->getMessage());
        }
    }
}
