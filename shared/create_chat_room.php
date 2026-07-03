<?php

/**
 * create_chat_room.php
 * Include this file after a contract is created to auto-create a chat room.
 *
 * Usage:
 *   // $conn and $contractId must already be set
 *   require_once __DIR__ . '/../shared/create_chat_room.php';
 *
 * Sets $chatRoomId on success, or leaves it null on failure.
 */

if (!isset($conn) || !isset($contractId)) {
    return;
}

require_once __DIR__ . '/ChatRoomService.php';

$chatRoomService = new ChatRoomService($conn);
$chatRoomId = $chatRoomService->ensureRoomExists((int) $contractId);
