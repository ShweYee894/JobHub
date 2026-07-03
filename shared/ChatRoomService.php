<?php

/**
 * ChatRoomService
 * Handles automatic chat room creation tied to contracts.
 * Uses transactions and duplicate-checking to ensure data integrity.
 */

class ChatRoomService
{
    private $conn;

    public function __construct($conn)
    {
        $this->conn = $conn;
    }

    /**
     * Create a chat room for a contract if one does not already exist.
     * Uses a transaction; rolls back on failure.
     * Returns the room_id on success, or false on failure.
     */
    public function createRoomForContract(int $contractId): int|false
    {
        $existing = $this->getRoomByContract($contractId);
        if ($existing !== null) {
            return $existing;
        }

        $this->conn->begin_transaction();
        try {
            $stmt = $this->conn->prepare(
                'INSERT INTO chat_rooms (contract_id) VALUES (?)'
            );
            $stmt->bind_param('i', $contractId);
            $stmt->execute();
            $roomId = $stmt->insert_id;
            $stmt->close();

            if ($roomId <= 0) {
                throw new Exception('Failed to insert chat room.');
            }

            $this->conn->commit();
            return $roomId;
        } catch (Exception $e) {
            $this->conn->rollback();
            error_log("ChatRoomService::createRoomForContract failed: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Get the room_id for a contract, or null if none exists.
     */
    public function getRoomByContract(int $contractId): ?int
    {
        $stmt = $this->conn->prepare(
            'SELECT id FROM chat_rooms WHERE contract_id = ? LIMIT 1'
        );
        $stmt->bind_param('i', $contractId);
        $stmt->execute();
        $result = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        return $result ? (int) $result['id'] : null;
    }

    /**
     * Ensure a room exists for the given contract.
     * Returns the room_id, creating a new room if necessary.
     */
    public function ensureRoomExists(int $contractId): int|false
    {
        $roomId = $this->getRoomByContract($contractId);
        if ($roomId !== null) {
            return $roomId;
        }
        return $this->createRoomForContract($contractId);
    }

    /**
     * Get full room details for a contract.
     */
    public function getRoomDetails(int $contractId): ?array
    {
        $stmt = $this->conn->prepare(
            'SELECT cr.id AS room_id, cr.contract_id, cr.created_at,
                    c.client_id, c.freelancer_id, c.status AS contract_status,
                    j.title AS job_title
             FROM chat_rooms cr
             JOIN contracts c ON cr.contract_id = c.id
             JOIN jobs j ON c.job_id = j.id
             WHERE cr.contract_id = ?
             LIMIT 1'
        );
        $stmt->bind_param('i', $contractId);
        $stmt->execute();
        $result = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        return $result ?: null;
    }
}
