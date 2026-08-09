<?php
require_once __DIR__ . '/../auth/auth.php';
require_role('client');
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/wallet_functions.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect('/jobhub/client/contracts.php');
}

if (!verify_csrf_token()) {
    set_flash('error', 'Invalid security token.');
    redirect('/jobhub/client/contracts.php');
}

$userId = $_SESSION['user_id'];
$action = $_POST['action'] ?? '';
$milestoneId = isset($_POST['milestone_id']) ? sanitize_int($_POST['milestone_id']) : 0;
$contractId  = isset($_POST['contract_id']) ? sanitize_int($_POST['contract_id']) : 0;

// ── Contract-level actions (complete, dispute, accept_termination, reject_termination) ────────────────────────
if (in_array($action, ['complete', 'dispute', 'accept_termination', 'reject_termination'], true)) {
    if ($contractId <= 0) {
        set_flash('error', 'Invalid request.');
        redirect('/jobhub/client/contracts.php');
    }

    $stmt = $conn->prepare('SELECT id, status, dispute_status, cancellation_reason, cancelled_by, freelancer_id FROM contracts WHERE id = ? AND client_id = ?');
    $stmt->bind_param('ii', $contractId, $userId);
    $stmt->execute();
    $contract = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if (!$contract) {
        set_flash('error', 'Contract not found or access denied.');
        redirect('/jobhub/client/contracts.php');
    }

    if ($action === 'complete') {
        if ($contract['status'] !== 'active') {
            set_flash('error', 'Only active contracts can be marked as complete.');
            redirect('/jobhub/client/contracts.php');
        }

        $upd = $conn->prepare('UPDATE contracts SET status = "completed", updated_at = NOW() WHERE id = ?');
        $upd->bind_param('i', $contractId);
        $upd->execute();
        $upd->close();

        increment_freelancer_completed_jobs($conn, (int) $contract['freelancer_id']);

        set_flash('success', 'Contract marked as completed!');
    } elseif ($action === 'dispute') {
        if ($contract['status'] !== 'active') {
            set_flash('error', 'Only active contracts can be disputed.');
            redirect('/jobhub/client/contracts.php');
        }

        $upd = $conn->prepare('UPDATE contracts SET status = "disputed", updated_at = NOW() WHERE id = ?');
        $upd->bind_param('i', $contractId);
        $upd->execute();
        $upd->close();

        set_flash('warning', 'Dispute raised. An admin will review this contract.');
    } elseif ($action === 'accept_termination') {
        if ($contract['status'] !== 'active') {
            set_flash('error', 'Only active contracts can be terminated.');
            redirect('/jobhub/client/contracts.php');
        }
        if ($contract['dispute_status'] !== 'open' || empty($contract['cancellation_reason'])) {
            set_flash('error', 'No pending termination request for this contract.');
            redirect('/jobhub/client/contracts.php');
        }

        $upd = $conn->prepare('UPDATE contracts SET status = "terminated", dispute_status = "resolved", updated_at = NOW() WHERE id = ?');
        $upd->bind_param('i', $contractId);
        $upd->execute();
        $upd->close();

        // Cancel associated job if it's still active
        $jobUpd = $conn->prepare('UPDATE jobs SET status = "cancelled", updated_at = NOW() WHERE id = (SELECT job_id FROM contracts WHERE id = ?) AND status = "in_progress"');
        $jobUpd->bind_param('i', $contractId);
        $jobUpd->execute();
        $jobUpd->close();

        set_flash('success', 'Termination accepted. Contract has been closed.');
    } elseif ($action === 'reject_termination') {
        if ($contract['status'] !== 'active') {
            set_flash('error', 'Only active contracts can have termination requests.');
            redirect('/jobhub/client/contracts.php');
        }
        if ($contract['dispute_status'] !== 'open' || empty($contract['cancellation_reason'])) {
            set_flash('error', 'No pending termination request for this contract.');
            redirect('/jobhub/client/contracts.php');
        }

        $upd = $conn->prepare('UPDATE contracts SET dispute_status = "resolved", updated_at = NOW() WHERE id = ?');
        $upd->bind_param('i', $contractId);
        $upd->execute();
        $upd->close();

        set_flash('warning', 'Termination rejected. The freelancer may file a dispute.');
    }

    $conn->close();
    redirect('/jobhub/client/contracts.php');
}

// ── Milestone-level actions ───────────────────────────────────────────
if ($milestoneId <= 0 || empty($action)) {
    set_flash('error', 'Invalid request.');
    redirect('/jobhub/client/contracts.php');
}

$stmt = $conn->prepare('
    SELECT m.id, m.contract_id, m.status, m.amount,
           c.client_id, c.status AS contract_status
    FROM milestones m
    JOIN contracts c ON m.contract_id = c.id
    WHERE m.id = ? AND c.client_id = ?
');
$stmt->bind_param('ii', $milestoneId, $userId);
$stmt->execute();
$milestone = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$milestone) {
    set_flash('error', 'Milestone not found or access denied.');
    redirect('/jobhub/client/contracts.php');
}

$contractId = $milestone['contract_id'];

switch ($action) {
    case 'approve':
        if ($milestone['status'] !== 'submitted') {
            set_flash('error', 'This milestone cannot be approved in its current state.');
            redirect('/jobhub/client/contract_detail.php?id=' . $contractId);
        }

        $stmt = $conn->prepare('SELECT wallet_balance FROM users WHERE id = (SELECT freelancer_id FROM contracts WHERE id = ?)');
        $stmt->bind_param('i', $contractId);
        $stmt->execute();
        $freelancerId = 0;
        $freelancerBalance = 0.0;
        $result = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        $stmt = $conn->prepare('SELECT freelancer_id FROM contracts WHERE id = ?');
        $stmt->bind_param('i', $contractId);
        $stmt->execute();
        $freelancerRow = $stmt->get_result()->fetch_assoc();
        $freelancerId = (int) $freelancerRow['freelancer_id'];
        $stmt->close();

        $stmt = $conn->prepare('SELECT wallet_balance FROM users WHERE id = ?');
        $stmt->bind_param('i', $freelancerId);
        $stmt->execute();
        $freelancerBalance = (float) $stmt->get_result()->fetch_assoc()['wallet_balance'];
        $stmt->close();

        $amount = (float) $milestone['amount'];
        $feePercent = 10.0;
        $feeStmt = $conn->prepare('SELECT fee_percent FROM platform_fee_rules WHERE is_active = 1 ORDER BY effective_from DESC LIMIT 1');
        $feeStmt->execute();
        $feeRow = $feeStmt->get_result()->fetch_assoc();
        if ($feeRow) { $feePercent = (float) $feeRow['fee_percent']; }
        $feeStmt->close();
        $platformFee = round($amount * ($feePercent / 100), 2);
        $freelancerNet = round($amount - $platformFee, 2);

        $conn->begin_transaction();
        try {
            $stmt = $conn->prepare('UPDATE milestones SET status = "released", updated_at = NOW() WHERE id = ?');
            $stmt->bind_param('i', $milestoneId);
            $stmt->execute();
            $stmt->close();

            $newFreelancerBalance = $freelancerBalance + $freelancerNet;
            $stmt = $conn->prepare('UPDATE users SET wallet_balance = wallet_balance + ? WHERE id = ?');
            $stmt->bind_param('di', $freelancerNet, $freelancerId);
            $stmt->execute();
            $stmt->close();

            $stmt = $conn->prepare('UPDATE payments SET status = "completed" WHERE milestone_id = ?');
            $stmt->bind_param('i', $milestoneId);
            $stmt->execute();
            $stmt->close();

            increment_freelancer_earnings($conn, $freelancerId, $freelancerNet);

            $stmt = $conn->prepare('INSERT INTO wallet_transactions (user_id, type, amount, balance_after, reference_id, reference_type, description, created_at) VALUES (?, "escrow_release", ?, ?, ?, "milestone", ?, NOW())');
            $desc = 'Payment received for milestone #' . $milestoneId;
            $stmt->bind_param('iddis', $freelancerId, $freelancerNet, $newFreelancerBalance, $milestoneId, $desc);
            $stmt->execute();
            $stmt->close();

            $stmt = $conn->prepare('UPDATE clients SET total_spent = total_spent + ? WHERE client_id = ?');
            $stmt->bind_param('di', $amount, $userId);
            $stmt->execute();
            $stmt->close();

            $allReleased = true;
            $check = $conn->prepare('SELECT status FROM milestones WHERE contract_id = ?');
            $check->bind_param('i', $contractId);
            $check->execute();
            $allMilestones = $check->get_result();
            $check->close();
            while ($row = $allMilestones->fetch_assoc()) {
                if ($row['status'] !== 'released') {
                    $allReleased = false;
                    break;
                }
            }

            if ($allReleased) {
                $upd = $conn->prepare('UPDATE contracts SET status = "completed", updated_at = NOW() WHERE id = ?');
                $upd->bind_param('i', $contractId);
                $upd->execute();
                $upd->close();

                increment_freelancer_completed_jobs($conn, $freelancerId);

                $jobUpd = $conn->prepare('UPDATE jobs SET status = "completed", updated_at = NOW() WHERE id = (SELECT job_id FROM contracts WHERE id = ?)');
                $jobUpd->bind_param('i', $contractId);
                $jobUpd->execute();
                $jobUpd->close();
            }

            $conn->commit();

            // Notify freelancer about milestone approval and payment release
            require_once __DIR__ . '/../shared/notification_helper.php';
            $stmtMilestone = $conn->prepare('SELECT title FROM milestones WHERE id = ?');
            $stmtMilestone->bind_param('i', $milestoneId);
            $stmtMilestone->execute();
            $milestoneTitle = $stmtMilestone->get_result()->fetch_assoc()['title'] ?? 'Milestone';
            $stmtMilestone->close();
            notifyMilestoneApproved($freelancerId, $milestoneTitle, $amount, $contractId);
            notifyPaymentReleased($freelancerId, $freelancerNet, $contractId);

            set_flash('success', 'Milestone approved and payment released to freelancer!');
        } catch (Exception $e) {
            $conn->rollback();
            set_flash('error', 'Failed to approve milestone.');
        }
        break;

    case 'reject':
        if ($milestone['status'] !== 'submitted') {
            set_flash('error', 'This milestone cannot be rejected in its current state.');
            redirect('/jobhub/client/contract_detail.php?id=' . $contractId);
        }

        $stmt = $conn->prepare('UPDATE milestones SET status = "disputed", updated_at = NOW() WHERE id = ?');
        $stmt->bind_param('i', $milestoneId);
        $stmt->execute();
        $stmt->close();

        $upd = $conn->prepare('UPDATE contracts SET status = "disputed", updated_at = NOW() WHERE id = ?');
        $upd->bind_param('i', $contractId);
        $upd->execute();
        $upd->close();

        set_flash('warning', 'Milestone work rejected. Contract marked as disputed.');
        break;

    case 'fund_escrow':
        if ($milestone['status'] !== 'pending') {
            set_flash('error', 'This milestone cannot be funded in its current state.');
            redirect('/jobhub/client/contract_detail.php?id=' . $contractId);
        }

        $amount = (float) $milestone['amount'];
        $stmt = $conn->prepare('SELECT wallet_balance FROM users WHERE id = ?');
        $stmt->bind_param('i', $userId);
        $stmt->execute();
        $walletBalance = (float) $stmt->get_result()->fetch_assoc()['wallet_balance'];
        $stmt->close();

        if ($walletBalance < $amount) {
            set_flash('error', 'Insufficient wallet balance. You need ' . format_currency($amount) . ' but have ' . format_currency($walletBalance) . '.');
            redirect('/jobhub/client/contract_detail.php?id=' . $contractId);
        }

        $conn->begin_transaction();
        try {
            $stmt = $conn->prepare('UPDATE users SET wallet_balance = wallet_balance - ? WHERE id = ? AND wallet_balance >= ?');
            $stmt->bind_param('did', $amount, $userId, $amount);
            $stmt->execute();
            if ($stmt->affected_rows === 0) {
                throw new Exception('Insufficient balance (concurrent modification).');
            }
            $stmt->close();

            $stmt = $conn->prepare('UPDATE milestones SET status = "funded_in_escrow", updated_at = NOW() WHERE id = ?');
            $stmt->bind_param('i', $milestoneId);
            $stmt->execute();
            $stmt->close();

            $newBalance = $walletBalance - $amount;
            $stmt = $conn->prepare('INSERT INTO wallet_transactions (user_id, type, amount, balance_after, reference_id, reference_type, description, created_at) VALUES (?, "escrow_hold", ?, ?, ?, "milestone", ?, NOW())');
            $desc = 'Escrow hold for milestone #' . $milestoneId;
            $stmt->bind_param('iddis', $userId, $amount, $newBalance, $milestoneId, $desc);
            $stmt->execute();
            $stmt->close();

            $conn->commit();

            // Notify freelancer about milestone funding
            require_once __DIR__ . '/../shared/notification_helper.php';
            $stmtFreelancer = $conn->prepare('SELECT freelancer_id, job_id FROM contracts WHERE id = ?');
            $stmtFreelancer->bind_param('i', $contractId);
            $stmtFreelancer->execute();
            $contractInfo = $stmtFreelancer->get_result()->fetch_assoc();
            $stmtFreelancer->close();
            if ($contractInfo) {
                $stmtMilestone = $conn->prepare('SELECT title FROM milestones WHERE id = ?');
                $stmtMilestone->bind_param('i', $milestoneId);
                $stmtMilestone->execute();
                $milestoneTitle = $stmtMilestone->get_result()->fetch_assoc()['title'] ?? 'Milestone';
                $stmtMilestone->close();
                notifyMilestoneFunded($contractInfo['freelancer_id'], $milestoneTitle, $amount, $contractId);
            }

            set_flash('success', 'Milestone funded in escrow! ' . format_currency($amount) . ' deducted from wallet.');
        } catch (Exception $e) {
            $conn->rollback();
            set_flash('error', 'Failed to fund milestone.');
        }
        break;

    default:
        set_flash('error', 'Unknown action.');
        break;
}

$conn->close();
redirect('/jobhub/client/contract_detail.php?id=' . $contractId);
