<?php
/**
 * Freelancer Contract Actions
 * Handles contract-level actions initiated by freelancers.
 */

require_once __DIR__ . '/../auth/auth.php';
require_role('freelancer');
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/helpers.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    json_response(['success' => false, 'message' => 'POST request required.'], 405);
}

if (!verify_csrf_token()) {
    json_response(['success' => false, 'message' => 'Invalid CSRF token.'], 403);
}

$userId = (int) $_SESSION['user_id'];
$action = $_POST['action'] ?? '';
$contractId = sanitize_int($_POST['contract_id'] ?? 0);

if ($contractId <= 0) {
    json_response(['success' => false, 'message' => 'Invalid contract ID.'], 400);
}

// Fetch contract
$stmt = $conn->prepare('SELECT id, status, dispute_status, client_id, freelancer_id FROM contracts WHERE id = ? AND freelancer_id = ?');
$stmt->bind_param('ii', $contractId, $userId);
$stmt->execute();
$contract = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$contract) {
    json_response(['success' => false, 'message' => 'Contract not found or access denied.'], 404);
}

if ($action === 'request_termination') {
    if ($contract['status'] !== 'active') {
        json_response(['success' => false, 'message' => 'Only active contracts can be terminated.'], 400);
    }
    if ($contract['dispute_status'] === 'open') {
        json_response(['success' => false, 'message' => 'There is already a pending termination request for this contract.'], 400);
    }

    $reason = trim($_POST['reason'] ?? '');
    if (empty($reason) || strlen($reason) < 20) {
        json_response(['success' => false, 'message' => 'Reason must be at least 20 characters.'], 400);
    }
    if (strlen($reason) > 500) {
        json_response(['success' => false, 'message' => 'Reason must be 500 characters or fewer.'], 400);
    }

    $stmt = $conn->prepare('UPDATE contracts SET cancellation_reason = ?, cancelled_by = ?, dispute_status = "open", updated_at = NOW() WHERE id = ?');
    $stmt->bind_param('sii', $reason, $userId, $contractId);
    $stmt->execute();
    $stmt->close();

    // Notify client
    $ns = new PlatformNotificationService($conn);
    $ns->create(
        $contract['client_id'],
        'termination_request',
        'Termination Requested',
        'A freelancer has requested to terminate contract #' . $contractId . '. Reason: ' . substr($reason, 0, 100) . '...',
        '/jobhub/client/contracts.php'
    );

    json_response([
        'success' => true,
        'message' => 'Termination request submitted. The client will be notified.',
    ]);
} else {
    json_response(['success' => false, 'message' => 'Invalid action.'], 400);
}

$conn->close();
