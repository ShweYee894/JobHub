<?php
/**
 * Milestones API
 * Handles CRUD operations for milestones within contracts.
 * GET  ?action=list&contract_id=X   - list milestones for a contract
 * GET  ?action=detail&id=X         - get single milestone
 * POST ?action=create              - create milestone (client only)
 * POST ?action=update              - update milestone (client, pending only)
 * POST ?action=submit              - submit work (freelancer, funded_in_escrow only)
 * POST ?action=approve             - approve work (client, submitted only)
 * POST ?action=dispute             - dispute milestone
 */

session_start();
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/helpers.php';
require_once __DIR__ . '/../auth/auth.php';

header('Content-Type: application/json');

if (!is_logged_in()) {
    json_response(['success' => false, 'message' => 'Authentication required.'], 401);
}

$userId   = (int) $_SESSION['user_id'];
$userRole = $_SESSION['user_role'] ?? '';
$action   = $_GET['action'] ?? $_POST['action'] ?? '';

// ── Helper: verify contract ownership ──────────────────────────────────
function get_contract(int $contractId, int $userId, string $role): ?array {
    global $conn;
    if ($role === 'client') {
        $stmt = $conn->prepare('SELECT * FROM contracts WHERE id = ? AND client_id = ?');
        $stmt->bind_param('ii', $contractId, $userId);
    } else {
        $stmt = $conn->prepare('SELECT * FROM contracts WHERE id = ? AND freelancer_id = ?');
        $stmt->bind_param('ii', $contractId, $userId);
    }
    $stmt->execute();
    $result = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    return $result;
}

function get_milestone(int $milestoneId): ?array {
    global $conn;
    $stmt = $conn->prepare('SELECT m.*, c.client_id, c.freelancer_id, c.total_budget FROM milestones m JOIN contracts c ON m.contract_id = c.id WHERE m.id = ?');
    $stmt->bind_param('i', $milestoneId);
    $stmt->execute();
    $result = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    return $result;
}

switch ($action) {

    // ═══════════════════════════════════════════════════════════════════
    // LIST milestones for a contract
    // ═══════════════════════════════════════════════════════════════════
    case 'list':
        $contractId = sanitize_int($_GET['contract_id'] ?? 0);
        if ($contractId <= 0) {
            json_response(['success' => false, 'message' => 'Invalid contract ID.'], 400);
        }

        $contract = get_contract($contractId, $userId, $userRole);
        if (!$contract) {
            json_response(['success' => false, 'message' => 'Contract not found or access denied.'], 404);
        }

        $stmt = $conn->prepare('SELECT * FROM milestones WHERE contract_id = ? ORDER BY created_at ASC');
        $stmt->bind_param('i', $contractId);
        $stmt->execute();
        $milestones = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        $stmt->close();

        $totalAmount = 0;
        foreach ($milestones as $m) {
            $totalAmount += (float) $m['amount'];
        }

        json_response([
            'success'       => true,
            'milestones'    => $milestones,
            'contract'      => $contract,
            'total_amount'  => $totalAmount,
            'budget_remaining' => (float) $contract['total_budget'] - $totalAmount,
        ]);
        break;

    // ═══════════════════════════════════════════════════════════════════
    // DETAIL single milestone
    // ═══════════════════════════════════════════════════════════════════
    case 'detail':
        $milestoneId = sanitize_int($_GET['id'] ?? 0);
        if ($milestoneId <= 0) {
            json_response(['success' => false, 'message' => 'Invalid milestone ID.'], 400);
        }

        $milestone = get_milestone($milestoneId);
        if (!$milestone) {
            json_response(['success' => false, 'message' => 'Milestone not found.'], 404);
        }

        // Verify user is part of this contract
        if ($userId !== (int) $milestone['client_id'] && $userId !== (int) $milestone['freelancer_id']) {
            json_response(['success' => false, 'message' => 'Access denied.'], 403);
        }

        // Fetch associated payment if exists
        $payment = null;
        $pStmt = $conn->prepare('SELECT p.*, u.name AS payer_name, u2.name AS payee_name FROM payments p JOIN users u ON p.payer_id = u.id JOIN users u2 ON p.payee_id = u2.id WHERE p.milestone_id = ?');
        $pStmt->bind_param('i', $milestoneId);
        $pStmt->execute();
        $payment = $pStmt->get_result()->fetch_assoc();
        $pStmt->close();

        json_response([
            'success'    => true,
            'milestone'  => $milestone,
            'payment'    => $payment,
        ]);
        break;

    // ═══════════════════════════════════════════════════════════════════
    // CREATE milestone (client only)
    // ═══════════════════════════════════════════════════════════════════
    case 'create':
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            json_response(['success' => false, 'message' => 'POST request required.'], 405);
        }
        if (!verify_csrf_token()) {
            json_response(['success' => false, 'message' => 'Invalid CSRF token.'], 403);
        }
        if ($userRole !== 'client') {
            json_response(['success' => false, 'message' => 'Only clients can create milestones.'], 403);
        }

        $contractId  = sanitize_int($_POST['contract_id'] ?? 0);
        $title       = trim($_POST['title'] ?? '');
        $amount      = sanitize_float($_POST['amount'] ?? 0);

        if ($contractId <= 0) {
            json_response(['success' => false, 'message' => 'Invalid contract ID.'], 400);
        }
        if (empty($title) || strlen($title) > 255) {
            json_response(['success' => false, 'message' => 'Title is required (max 255 chars).'], 400);
        }
        if ($amount <= 0) {
            json_response(['success' => false, 'message' => 'Amount must be greater than 0.'], 400);
        }

        $contract = get_contract($contractId, $userId, 'client');
        if (!$contract) {
            json_response(['success' => false, 'message' => 'Contract not found or access denied.'], 404);
        }

        // Check total milestones don't exceed contract budget
        $stmt = $conn->prepare('SELECT COALESCE(SUM(amount), 0) AS total FROM milestones WHERE contract_id = ?');
        $stmt->bind_param('i', $contractId);
        $stmt->execute();
        $existingTotal = (float) $stmt->get_result()->fetch_assoc()['total'];
        $stmt->close();

        if ($existingTotal + $amount > (float) $contract['total_budget']) {
            json_response([
                'success' => false,
                'message' => 'Total milestones (' . format_currency($existingTotal + $amount) . ') would exceed contract budget (' . format_currency($contract['total_budget']) . ').',
            ], 400);
        }

        $conn->begin_transaction();
        try {
            $stmt = $conn->prepare('INSERT INTO milestones (contract_id, title, amount, status, created_at, updated_at) VALUES (?, ?, ?, \'pending\', NOW(), NOW())');
            $stmt->bind_param('isd', $contractId, $title, $amount);
            $stmt->execute();
            $newId = $conn->insert_id;
            $stmt->close();

            $conn->commit();

            $milestone = get_milestone($newId);
            json_response([
                'success'   => true,
                'message'   => 'Milestone created successfully.',
                'milestone' => $milestone,
            ], 201);
        } catch (Exception $e) {
            $conn->rollback();
            json_response(['success' => false, 'message' => 'Failed to create milestone.'], 500);
        }
        break;

    // ═══════════════════════════════════════════════════════════════════
    // UPDATE milestone (client only, only if pending)
    // ═══════════════════════════════════════════════════════════════════
    case 'update':
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            json_response(['success' => false, 'message' => 'POST request required.'], 405);
        }
        if (!verify_csrf_token()) {
            json_response(['success' => false, 'message' => 'Invalid CSRF token.'], 403);
        }
        if ($userRole !== 'client') {
            json_response(['success' => false, 'message' => 'Only clients can update milestones.'], 403);
        }

        $milestoneId = sanitize_int($_POST['milestone_id'] ?? 0);
        $title       = trim($_POST['title'] ?? '');
        $amount      = sanitize_float($_POST['amount'] ?? 0);

        if ($milestoneId <= 0) {
            json_response(['success' => false, 'message' => 'Invalid milestone ID.'], 400);
        }

        $milestone = get_milestone($milestoneId);
        if (!$milestone) {
            json_response(['success' => false, 'message' => 'Milestone not found.'], 404);
        }
        if ((int) $milestone['client_id'] !== $userId) {
            json_response(['success' => false, 'message' => 'Access denied.'], 403);
        }
        if ($milestone['status'] !== 'pending') {
            json_response(['success' => false, 'message' => 'Only pending milestones can be updated.'], 400);
        }

        if (empty($title) || strlen($title) > 255) {
            json_response(['success' => false, 'message' => 'Title is required (max 255 chars).'], 400);
        }
        if ($amount <= 0) {
            json_response(['success' => false, 'message' => 'Amount must be greater than 0.'], 400);
        }

        // Check budget with the new amount replacing the old one
        $stmt = $conn->prepare('SELECT COALESCE(SUM(amount), 0) AS total FROM milestones WHERE contract_id = ? AND id != ?');
        $stmt->bind_param('ii', $milestone['contract_id'], $milestoneId);
        $stmt->execute();
        $otherTotal = (float) $stmt->get_result()->fetch_assoc()['total'];
        $stmt->close();

        if ($otherTotal + $amount > (float) $milestone['total_budget']) {
            json_response([
                'success' => false,
                'message' => 'Total milestones would exceed contract budget.',
            ], 400);
        }

        $conn->begin_transaction();
        try {
            $stmt = $conn->prepare('UPDATE milestones SET title = ?, amount = ?, updated_at = NOW() WHERE id = ?');
            $stmt->bind_param('sdi', $title, $amount, $milestoneId);
            $stmt->execute();
            $stmt->close();

            $conn->commit();

            $updated = get_milestone($milestoneId);
            json_response([
                'success'   => true,
                'message'   => 'Milestone updated successfully.',
                'milestone' => $updated,
            ]);
        } catch (Exception $e) {
            $conn->rollback();
            json_response(['success' => false, 'message' => 'Failed to update milestone.'], 500);
        }
        break;

    // ═══════════════════════════════════════════════════════════════════
    // SUBMIT work (freelancer only, funded_in_escrow only)
    // ═══════════════════════════════════════════════════════════════════
    case 'submit':
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            json_response(['success' => false, 'message' => 'POST request required.'], 405);
        }
        if (!verify_csrf_token()) {
            json_response(['success' => false, 'message' => 'Invalid CSRF token.'], 403);
        }
        if ($userRole !== 'freelancer') {
            json_response(['success' => false, 'message' => 'Only freelancers can submit work.'], 403);
        }

        $milestoneId = sanitize_int($_POST['milestone_id'] ?? 0);
        if ($milestoneId <= 0) {
            json_response(['success' => false, 'message' => 'Invalid milestone ID.'], 400);
        }

        $milestone = get_milestone($milestoneId);
        if (!$milestone) {
            json_response(['success' => false, 'message' => 'Milestone not found.'], 404);
        }
        if ((int) $milestone['freelancer_id'] !== $userId) {
            json_response(['success' => false, 'message' => 'Access denied.'], 403);
        }
        if ($milestone['status'] !== 'funded_in_escrow') {
            json_response(['success' => false, 'message' => 'Milestone must be funded before submission.'], 400);
        }

        $conn->begin_transaction();
        try {
            $stmt = $conn->prepare('UPDATE milestones SET status = \'submitted\', updated_at = NOW() WHERE id = ?');
            $stmt->bind_param('i', $milestoneId);
            $stmt->execute();
            $stmt->close();

            $conn->commit();

            $updated = get_milestone($milestoneId);
            json_response([
                'success'   => true,
                'message'   => 'Work submitted successfully. Awaiting client approval.',
                'milestone' => $updated,
            ]);
        } catch (Exception $e) {
            $conn->rollback();
            json_response(['success' => false, 'message' => 'Failed to submit work.'], 500);
        }
        break;

    // ═══════════════════════════════════════════════════════════════════
    // APPROVE work (client only, submitted only) — triggers payment release
    // ═══════════════════════════════════════════════════════════════════
    case 'approve':
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            json_response(['success' => false, 'message' => 'POST request required.'], 405);
        }
        if (!verify_csrf_token()) {
            json_response(['success' => false, 'message' => 'Invalid CSRF token.'], 403);
        }
        if ($userRole !== 'client') {
            json_response(['success' => false, 'message' => 'Only clients can approve work.'], 403);
        }

        $milestoneId = sanitize_int($_POST['milestone_id'] ?? 0);
        if ($milestoneId <= 0) {
            json_response(['success' => false, 'message' => 'Invalid milestone ID.'], 400);
        }

        $milestone = get_milestone($milestoneId);
        if (!$milestone) {
            json_response(['success' => false, 'message' => 'Milestone not found.'], 404);
        }
        if ((int) $milestone['client_id'] !== $userId) {
            json_response(['success' => false, 'message' => 'Access denied.'], 403);
        }
        if ($milestone['status'] !== 'submitted') {
            json_response(['success' => false, 'message' => 'Only submitted milestones can be approved.'], 400);
        }

        $freelancerId = (int) $milestone['freelancer_id'];
        $amount       = (float) $milestone['amount'];
        $platformFee  = round($amount * 0.10, 2);
        $freelancerNet = round($amount * 0.90, 2);

        $conn->begin_transaction();
        try {
            // Update milestone status
            $stmt = $conn->prepare('UPDATE milestones SET status = \'released\', updated_at = NOW() WHERE id = ?');
            $stmt->bind_param('i', $milestoneId);
            $stmt->execute();
            $stmt->close();

            // Get freelancer current balance before update
            $stmt = $conn->prepare('SELECT wallet_balance FROM users WHERE id = ?');
            $stmt->bind_param('i', $freelancerId);
            $stmt->execute();
            $freelancerBalance = (float) $stmt->get_result()->fetch_assoc()['wallet_balance'];
            $stmt->close();

            // Add to freelancer wallet
            $stmt = $conn->prepare('UPDATE users SET wallet_balance = wallet_balance + ? WHERE id = ?');
            $stmt->bind_param('di', $freelancerNet, $freelancerId);
            $stmt->execute();
            $stmt->close();

            // Update payment record
            $stmt = $conn->prepare("UPDATE payments SET status = 'completed' WHERE milestone_id = ?");
            $stmt->bind_param('i', $milestoneId);
            $stmt->execute();
            $stmt->close();

            // Log wallet transaction for freelancer (escrow release)
            $newFreelancerBalance = $freelancerBalance + $freelancerNet;
            $stmt = $conn->prepare('
                INSERT INTO wallet_transactions (user_id, type, amount, balance_after, reference_id, reference_type, description, created_at)
                VALUES (?, \'escrow_release\', ?, ?, ?, \'milestone\', ?, NOW())
            ');
            $desc = 'Payment received for milestone #' . $milestoneId;
            $stmt->bind_param('iddis', $freelancerId, $freelancerNet, $newFreelancerBalance, $milestoneId, $desc);
            $stmt->execute();
            $stmt->close();

            // Log platform fee transaction
            $stmt = $conn->prepare('
                INSERT INTO wallet_transactions (user_id, type, amount, balance_after, reference_id, reference_type, description, created_at)
                VALUES (0, \'platform_fee\', ?, 0, ?, \'milestone\', ?, NOW())
            ');
            $feeDesc = 'Platform fee from milestone #' . $milestoneId;
            $stmt->bind_param('dis', $platformFee, $milestoneId, $feeDesc);
            $stmt->execute();
            $stmt->close();

            $conn->commit();

            $updated = get_milestone($milestoneId);
            json_response([
                'success'        => true,
                'message'        => 'Work approved. Payment of ' . format_currency($freelancerNet) . ' released to freelancer.',
                'milestone'      => $updated,
                'freelancer_net' => $freelancerNet,
                'platform_fee'   => $platformFee,
            ]);
        } catch (Exception $e) {
            $conn->rollback();
            json_response(['success' => false, 'message' => 'Failed to approve work.'], 500);
        }
        break;

    // ═══════════════════════════════════════════════════════════════════
    // DISPUTE milestone
    // ═══════════════════════════════════════════════════════════════════
    case 'dispute':
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            json_response(['success' => false, 'message' => 'POST request required.'], 405);
        }
        if (!verify_csrf_token()) {
            json_response(['success' => false, 'message' => 'Invalid CSRF token.'], 403);
        }

        $milestoneId = sanitize_int($_POST['milestone_id'] ?? 0);
        if ($milestoneId <= 0) {
            json_response(['success' => false, 'message' => 'Invalid milestone ID.'], 400);
        }

        $milestone = get_milestone($milestoneId);
        if (!$milestone) {
            json_response(['success' => false, 'message' => 'Milestone not found.'], 404);
        }
        if ($userId !== (int) $milestone['client_id'] && $userId !== (int) $milestone['freelancer_id']) {
            json_response(['success' => false, 'message' => 'Access denied.'], 403);
        }
        if ($milestone['status'] === 'released' || $milestone['status'] === 'disputed') {
            json_response(['success' => false, 'message' => 'This milestone cannot be disputed in its current state.'], 400);
        }

        $conn->begin_transaction();
        try {
            $stmt = $conn->prepare('UPDATE milestones SET status = \'disputed\', updated_at = NOW() WHERE id = ?');
            $stmt->bind_param('i', $milestoneId);
            $stmt->execute();
            $stmt->close();

            $conn->commit();

            $updated = get_milestone($milestoneId);
            json_response([
                'success'   => true,
                'message'   => 'Milestone has been disputed. Admin will review.',
                'milestone' => $updated,
            ]);
        } catch (Exception $e) {
            $conn->rollback();
            json_response(['success' => false, 'message' => 'Failed to dispute milestone.'], 500);
        }
        break;

    default:
        json_response(['success' => false, 'message' => 'Invalid action.'], 400);
        break;
}
