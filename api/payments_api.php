<?php
/**
 * Payments API
 * Handles escrow funding, payment release, and payment history.
 * POST ?action=fund    - Fund a milestone (client puts money in escrow)
 * POST ?action=release - Release payment to freelancer (when milestone approved)
 * GET  ?action=history  - Payment history for current user
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

switch ($action) {

    // ═══════════════════════════════════════════════════════════════════
    // FUND a milestone — client puts money in escrow
    // ═══════════════════════════════════════════════════════════════════
    case 'fund':
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            json_response(['success' => false, 'message' => 'POST request required.'], 405);
        }
        if (!verify_csrf_token()) {
            json_response(['success' => false, 'message' => 'Invalid CSRF token.'], 403);
        }
        if ($userRole !== 'client') {
            json_response(['success' => false, 'message' => 'Only clients can fund milestones.'], 403);
        }

        $milestoneId = sanitize_int($_POST['milestone_id'] ?? 0);
        if ($milestoneId <= 0) {
            json_response(['success' => false, 'message' => 'Invalid milestone ID.'], 400);
        }

        // Fetch milestone with contract info
        $stmt = $conn->prepare('
            SELECT m.*, c.client_id, c.freelancer_id, c.total_budget
            FROM milestones m
            JOIN contracts c ON m.contract_id = c.id
            WHERE m.id = ?
        ');
        $stmt->bind_param('i', $milestoneId);
        $stmt->execute();
        $milestone = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        if (!$milestone) {
            json_response(['success' => false, 'message' => 'Milestone not found.'], 404);
        }
        if ((int) $milestone['client_id'] !== $userId) {
            json_response(['success' => false, 'message' => 'Access denied.'], 403);
        }
        if ($milestone['status'] !== 'pending') {
            json_response(['success' => false, 'message' => 'Only pending milestones can be funded.'], 400);
        }

        $amount       = (float) $milestone['amount'];
        $freelancerId = (int) $milestone['freelancer_id'];

        // Check client wallet balance
        $stmt = $conn->prepare('SELECT wallet_balance FROM users WHERE id = ?');
        $stmt->bind_param('i', $userId);
        $stmt->execute();
        $wallet = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        if ((float) $wallet['wallet_balance'] < $amount) {
            json_response([
                'success' => false,
                'message' => 'Insufficient wallet balance. You need ' . format_currency($amount) . ' but have ' . format_currency((float) $wallet['wallet_balance']) . '.',
            ], 400);
        }

        $platformFee  = round($amount * 0.10, 2);
        $freelancerNet = round($amount * 0.90, 2);

        $conn->begin_transaction();
        try {
            // a. Deduct from client wallet
            $stmt = $conn->prepare('UPDATE users SET wallet_balance = wallet_balance - ? WHERE id = ? AND wallet_balance >= ?');
            $stmt->bind_param('did', $amount, $userId, $amount);
            $stmt->execute();
            if ($stmt->affected_rows === 0) {
                throw new Exception('Insufficient balance (concurrent modification).');
            }
            $stmt->close();

            // b. Update milestone status
            $stmt = $conn->prepare('UPDATE milestones SET status = \'funded_in_escrow\', updated_at = NOW() WHERE id = ?');
            $stmt->bind_param('i', $milestoneId);
            $stmt->execute();
            $stmt->close();

            // c. Insert payment record
            $stmt = $conn->prepare('
                INSERT INTO payments (milestone_id, payer_id, payee_id, total_amount, platform_fee, freelancer_net, status, created_at)
                VALUES (?, ?, ?, ?, ?, ?, \'completed\', NOW())
            ');
            $stmt->bind_param('iiiddd', $milestoneId, $userId, $freelancerId, $amount, $platformFee, $freelancerNet);
            $stmt->execute();
            $paymentId = (int) $stmt->insert_id;
            $stmt->close();

            // d. Log wallet transaction for audit trail
            $newBalance = (float) $wallet['wallet_balance'] - $amount;
            $stmt = $conn->prepare('
                INSERT INTO wallet_transactions (user_id, type, amount, balance_after, reference_id, reference_type, description, created_at)
                VALUES (?, \'escrow_hold\', ?, ?, ?, \'milestone\', ?, NOW())
            ');
            $desc = 'Escrow hold for milestone #' . $milestoneId . ' (Payment #' . $paymentId . ')';
            $stmt->bind_param('iddis', $userId, $amount, $newBalance, $milestoneId, $desc);
            $stmt->execute();
            $stmt->close();

            $conn->commit();

            json_response([
                'success'        => true,
                'message'        => 'Milestone funded successfully. ' . format_currency($amount) . ' placed in escrow.',
                'payment'        => [
                    'milestone_id'   => $milestoneId,
                    'total_amount'   => $amount,
                    'platform_fee'   => $platformFee,
                    'freelancer_net' => $freelancerNet,
                ],
                'new_wallet_balance' => $newBalance,
            ]);
        } catch (Exception $e) {
            $conn->rollback();
            json_response(['success' => false, 'message' => $e->getMessage() ?: 'Failed to fund milestone.'], 500);
        }
        break;

    // ═══════════════════════════════════════════════════════════════════
    // RELEASE payment to freelancer (when milestone is approved)
    // ═══════════════════════════════════════════════════════════════════
    case 'release':
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            json_response(['success' => false, 'message' => 'POST request required.'], 405);
        }
        if (!verify_csrf_token()) {
            json_response(['success' => false, 'message' => 'Invalid CSRF token.'], 403);
        }
        if ($userRole !== 'client') {
            json_response(['success' => false, 'message' => 'Only clients can release payments.'], 403);
        }

        $milestoneId = sanitize_int($_POST['milestone_id'] ?? 0);
        if ($milestoneId <= 0) {
            json_response(['success' => false, 'message' => 'Invalid milestone ID.'], 400);
        }

        // Fetch milestone with contract info
        $stmt = $conn->prepare('
            SELECT m.*, c.client_id, c.freelancer_id
            FROM milestones m
            JOIN contracts c ON m.contract_id = c.id
            WHERE m.id = ?
        ');
        $stmt->bind_param('i', $milestoneId);
        $stmt->execute();
        $milestone = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        if (!$milestone) {
            json_response(['success' => false, 'message' => 'Milestone not found.'], 404);
        }
        if ((int) $milestone['client_id'] !== $userId) {
            json_response(['success' => false, 'message' => 'Access denied.'], 403);
        }
        if ($milestone['status'] !== 'submitted') {
            json_response(['success' => false, 'message' => 'Only submitted milestones can have payments released.'], 400);
        }

        $freelancerId = (int) $milestone['freelancer_id'];
        $amount       = (float) $milestone['amount'];
        $freelancerNet = round($amount * 0.90, 2);

        $conn->begin_transaction();
        try {
            // a. Update milestone status to released
            $stmt = $conn->prepare('UPDATE milestones SET status = \'released\', updated_at = NOW() WHERE id = ?');
            $stmt->bind_param('i', $milestoneId);
            $stmt->execute();
            $stmt->close();

            // b. Get freelancer current balance before update
            $stmt = $conn->prepare('SELECT wallet_balance FROM users WHERE id = ?');
            $stmt->bind_param('i', $freelancerId);
            $stmt->execute();
            $freelancerBalance = (float) $stmt->get_result()->fetch_assoc()['wallet_balance'];
            $stmt->close();

            // c. Add to freelancer wallet
            $stmt = $conn->prepare('UPDATE users SET wallet_balance = wallet_balance + ? WHERE id = ?');
            $stmt->bind_param('di', $freelancerNet, $freelancerId);
            $stmt->execute();
            $stmt->close();

            // d. Update payment status
            $stmt = $conn->prepare("UPDATE payments SET status = 'completed' WHERE milestone_id = ?");
            $stmt->bind_param('i', $milestoneId);
            $stmt->execute();
            $stmt->close();

            // e. Log wallet transaction for freelancer (escrow release)
            $newFreelancerBalance = $freelancerBalance + $freelancerNet;
            $stmt = $conn->prepare('
                INSERT INTO wallet_transactions (user_id, type, amount, balance_after, reference_id, reference_type, description, created_at)
                VALUES (?, \'escrow_release\', ?, ?, ?, \'milestone\', ?, NOW())
            ');
            $desc = 'Payment received for milestone #' . $milestoneId;
            $stmt->bind_param('iddis', $freelancerId, $freelancerNet, $newFreelancerBalance, $milestoneId, $desc);
            $stmt->execute();
            $stmt->close();

            // f. Log platform fee transaction
            $stmt = $conn->prepare('
                INSERT INTO wallet_transactions (user_id, type, amount, balance_after, reference_id, reference_type, description, created_at)
                VALUES (0, \'platform_fee\', ?, 0, ?, \'milestone\', ?, NOW())
            ');
            $feeDesc = 'Platform fee from milestone #' . $milestoneId;
            $stmt->bind_param('dis', $platformFee, $milestoneId, $feeDesc);
            $stmt->execute();
            $stmt->close();

            $conn->commit();

            json_response([
                'success'        => true,
                'message'        => 'Payment of ' . format_currency($freelancerNet) . ' released to freelancer.',
                'freelancer_net' => $freelancerNet,
            ]);
        } catch (Exception $e) {
            $conn->rollback();
            json_response(['success' => false, 'message' => 'Failed to release payment.'], 500);
        }
        break;

    // ═══════════════════════════════════════════════════════════════════
    // PAYMENT HISTORY for current user
    // ═══════════════════════════════════════════════════════════════════
    case 'history':
        $page    = max(1, intval($_GET['page'] ?? 1));
        $perPage = 10;
        $offset  = ($page - 1) * $perPage;

        // Count total
        $stmt = $conn->prepare('SELECT COUNT(*) AS total FROM payments WHERE payer_id = ? OR payee_id = ?');
        $stmt->bind_param('ii', $userId, $userId);
        $stmt->execute();
        $totalPayments = (int) $stmt->get_result()->fetch_assoc()['total'];
        $stmt->close();

        $pagination = paginate($totalPayments, $perPage, $page);

        // Fetch payments with milestone title and other party name
        $stmt = $conn->prepare("
            SELECT p.*,
                   m.title AS milestone_title,
                   CASE
                       WHEN p.payer_id = ? THEN u2.name
                       ELSE u1.name
                   END AS other_party_name,
                   CASE
                       WHEN p.payer_id = ? THEN 'paid'
                       ELSE 'received'
                   END AS direction
            FROM payments p
            JOIN milestones m ON p.milestone_id = m.id
            JOIN users u1 ON p.payer_id = u1.id
            JOIN users u2 ON p.payee_id = u2.id
            WHERE p.payer_id = ? OR p.payee_id = ?
            ORDER BY p.created_at DESC
            LIMIT ? OFFSET ?
        ");
        $stmt->bind_param('iiiiii', $userId, $userId, $userId, $userId, $perPage, $offset);
        $stmt->execute();
        $payments = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        $stmt->close();

        json_response([
            'success'    => true,
            'payments'   => $payments,
            'pagination' => $pagination,
        ]);
        break;

    default:
        json_response(['success' => false, 'message' => 'Invalid action.'], 400);
        break;
}
