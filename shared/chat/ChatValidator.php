<?php
/**
 * Chat Validator - Validates chat operations and sanitizes input.
 */

class ChatValidator
{
    private $conn;

    public function __construct($conn)
    {
        $this->conn = $conn;
    }

    /**
     * Validate room access - checks contract ownership.
     */
    public function validateRoomAccess(int $roomId, int $userId, string $role): bool
    {
        try {
            $stmt = $this->conn->prepare("
                SELECT c.client_id, c.freelancer_id 
                FROM chat_rooms cr
                JOIN contracts c ON cr.contract_id = c.id
                WHERE cr.id = ?
            ");
            $stmt->bind_param("i", $roomId);
            $stmt->execute();
            $result = $stmt->get_result();

            if ($result->num_rows === 0) {
                return false;
            }

            $contract = $result->fetch_assoc();

            if ($role === 'client' && (int)$contract['client_id'] === $userId) {
                return true;
            }
            if ($role === 'freelancer' && (int)$contract['freelancer_id'] === $userId) {
                return true;
            }
            if ($role === 'admin') {
                return true;
            }

            return false;
        } catch (Exception $e) {
            error_log("ChatValidator::validateRoomAccess error: " . $e->getMessage());
            return false;
        } finally {
            if (isset($stmt)) {
                $stmt->close();
            }
        }
    }

    /**
     * Validate message text - checks length and content.
     */
    public function validateMessage(string $messageText): array
    {
        $errors = [];
        $trimmed = trim($messageText);

        if (empty($trimmed)) {
            $errors[] = 'Message cannot be empty.';
        }

        if (strlen($trimmed) > 5000) {
            $errors[] = 'Message must not exceed 5000 characters.';
        }

        if (strlen($trimmed) < 1) {
            $errors[] = 'Message must be at least 1 character.';
        }

        return $errors;
    }

    /**
     * Validate room - checks if room exists for a contract.
     */
    public function validateRoom(int $contractId): ?int
    {
        try {
            $stmt = $this->conn->prepare("
                SELECT id FROM chat_rooms WHERE contract_id = ?
            ");
            $stmt->bind_param("i", $contractId);
            $stmt->execute();
            $result = $stmt->get_result();

            if ($result->num_rows > 0) {
                $row = $result->fetch_assoc();
                return (int)$row['id'];
            }

            return null;
        } catch (Exception $e) {
            error_log("ChatValidator::validateRoom error: " . $e->getMessage());
            return null;
        } finally {
            if (isset($stmt)) {
                $stmt->close();
            }
        }
    }

    /**
     * Sanitize message text - XSS prevention.
     */
    public function sanitizeMessage(string $text): string
    {
        $sanitized = htmlspecialchars(trim($text), ENT_QUOTES, 'UTF-8');
        return $sanitized;
    }

    /**
     * Validate contract exists and is active.
     */
    public function validateContract(int $contractId): bool
    {
        try {
            $stmt = $this->conn->prepare("
                SELECT id FROM contracts WHERE id = ? AND status = 'active'
            ");
            $stmt->bind_param("i", $contractId);
            $stmt->execute();
            $result = $stmt->get_result();

            return $result->num_rows > 0;
        } catch (Exception $e) {
            error_log("ChatValidator::validateContract error: " . $e->getMessage());
            return false;
        } finally {
            if (isset($stmt)) {
                $stmt->close();
            }
        }
    }
}
