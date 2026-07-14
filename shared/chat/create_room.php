<?php
/**
 * create_room.php
 *
 * Creates a chat room for a contract.
 * Guarantees one room per contract via duplicate check + UNIQUE constraint.
 *
 * Can be used in two ways:
 *
 *   1. As an include (after contract creation):
 *      $contractId = 42;
 *      require_once __DIR__ . '/shared/chat/create_room.php';
 *      // $chatRoomId is set on success, null on failure
 *
 *   2. As an AJAX endpoint (POST with csrf_token):
 *      fetch('/finalproject/shared/chat/create_room.php', {
 *          method: 'POST',
 *          body: 'contract_id=42&csrf_token=...'
 *      })
 */

session_start();
header('Content-Type: application/json');

// ── Bootstrap ────────────────────────────────────────────────────────────────
require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/chat_functions.php';

// ── Detect call mode ─────────────────────────────────────────────────────────
$isAjax = isset($_SERVER['HTTP_X_REQUESTED_WITH'])
    && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest';

$isPost = ($_SERVER['REQUEST_METHOD'] === 'POST');

// ── If called as AJAX endpoint ───────────────────────────────────────────────
if ($isAjax || $isPost) {
    // Authentication required
    if (!isset($_SESSION['user_id'])) {
        http_response_code(401);
        echo json_encode(['success' => false, 'error' => 'Unauthorized']);
        exit;
    }

    // CSRF verification
    if (!isset($_POST['csrf_token']) || !isset($_SESSION['csrf_token'])
        || !hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])) {
        http_response_code(403);
        echo json_encode(['success' => false, 'error' => 'Invalid CSRF token']);
        exit;
    }

    // Validate contract_id
    $contractId = (int) ($_POST['contract_id'] ?? 0);
    if ($contractId <= 0) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'Invalid contract ID']);
        exit;
    }

    $userId = (int) $_SESSION['user_id'];
    $role   = $_SESSION['user_role'] ?? '';

    // ── Verify contract ownership ────────────────────────────────────────
    // Admins can create rooms for any contract; clients/freelancers must be on the contract
    if ($role === 'admin') {
        $ownCheck = $conn->prepare(
            'SELECT c.id FROM contracts c WHERE c.id = ? LIMIT 1'
        );
    } elseif ($role === 'client') {
        $ownCheck = $conn->prepare(
            'SELECT c.id FROM contracts c WHERE c.id = ? AND c.client_id = ? LIMIT 1'
        );
    } elseif ($role === 'freelancer') {
        $ownCheck = $conn->prepare(
            'SELECT c.id FROM contracts c WHERE c.id = ? AND c.freelancer_id = ? LIMIT 1'
        );
    } else {
        http_response_code(403);
        echo json_encode(['success' => false, 'error' => 'Invalid role']);
        exit;
    }
    if (!$ownCheck) {
        http_response_code(500);
        echo json_encode(['success' => false, 'error' => 'Database error']);
        exit;
    }

    if ($role === 'admin') {
        $ownCheck->bind_param('i', $contractId);
    } else {
        $ownCheck->bind_param('ii', $contractId, $userId);
    }
    $ownCheck->execute();
    $contract = $ownCheck->get_result()->fetch_assoc();
    $ownCheck->close();

    if (!$contract) {
        http_response_code(403);
        echo json_encode(['success' => false, 'error' => 'Contract not found or access denied']);
        exit;
    }

    // ── Create room (duplicate-safe) ─────────────────────────────────────
    $roomId = chat_create_room($conn, $contractId);

    if ($roomId === false) {
        http_response_code(500);
        echo json_encode(['success' => false, 'error' => 'Failed to create chat room']);
        exit;
    }

    echo json_encode([
        'success'  => true,
        'room_id'  => $roomId,
        'contract_id' => $contractId,
    ]);
    exit;
}

// ── If called as an include (from accept_proposal.php, etc.) ─────────────────
// Requires $conn and $contractId to be set by the caller.
// Sets $chatRoomId on success, null on failure.

if (!isset($conn) || !isset($contractId)) {
    $chatRoomId = null;
    return;
}

$chatRoomId = chat_create_room($conn, (int) $contractId);
