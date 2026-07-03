<?php
require_once __DIR__ . '/../auth/auth.php';
require_role('client');
require_once __DIR__ . '/../config/db.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect('/finalproject/client/contracts.php');
}

if (!verify_csrf_token()) {
    set_flash('error', 'Invalid security token.');
    redirect('/finalproject/client/contracts.php');
}

$userId = $_SESSION['user_id'];
$action = $_POST['action'] ?? '';
$milestoneId = isset($_POST['milestone_id']) ? sanitize_int($_POST['milestone_id']) : 0;
$contractId  = isset($_POST['contract_id']) ? sanitize_int($_POST['contract_id']) : 0;

// ── Contract-level actions (complete, dispute) ────────────────────────
if (in_array($action, ['complete', 'dispute'], true)) {
    if ($contractId <= 0) {
        set_flash('error', 'Invalid request.');
        redirect('/finalproject/client/contracts.php');
    }

    $stmt = $conn->prepare('SELECT id, status FROM contracts WHERE id = ? AND client_id = ?');
    $stmt->bind_param('ii', $contractId, $userId);
    $stmt->execute();
    $contract = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if (!$contract) {
        set_flash('error', 'Contract not found or access denied.');
        redirect('/finalproject/client/contracts.php');
    }

    if ($action === 'complete') {
        if ($contract['status'] !== 'active') {
            set_flash('error', 'Only active contracts can be marked as complete.');
            redirect('/finalproject/client/contracts.php');
        }

        $upd = $conn->prepare('UPDATE contracts SET status = "completed", updated_at = NOW() WHERE id = ?');
        $upd->bind_param('i', $contractId);
        $upd->execute();
        $upd->close();

        set_flash('success', 'Contract marked as completed!');
    } elseif ($action === 'dispute') {
        if ($contract['status'] !== 'active') {
            set_flash('error', 'Only active contracts can be disputed.');
            redirect('/finalproject/client/contracts.php');
        }

        $upd = $conn->prepare('UPDATE contracts SET status = "disputed", updated_at = NOW() WHERE id = ?');
        $upd->bind_param('i', $contractId);
        $upd->execute();
        $upd->close();

        set_flash('warning', 'Dispute raised. An admin will review this contract.');
    }

    $conn->close();
    redirect('/finalproject/client/contracts.php');
}

// ── Milestone-level actions ───────────────────────────────────────────
if ($milestoneId <= 0 || empty($action)) {
    set_flash('error', 'Invalid request.');
    redirect('/finalproject/client/contracts.php');
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
    redirect('/finalproject/client/contracts.php');
}

$contractId = $milestone['contract_id'];

switch ($action) {
    case 'approve':
        if ($milestone['status'] !== 'submitted') {
            set_flash('error', 'This milestone cannot be approved in its current state.');
            redirect('/finalproject/client/contract_detail.php?id=' . $contractId);
        }

        $stmt = $conn->prepare('UPDATE milestones SET status = "released", updated_at = NOW() WHERE id = ?');
        $stmt->bind_param('i', $milestoneId);
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
        }

        set_flash('success', 'Milestone approved and released!');
        break;

    case 'reject':
        if ($milestone['status'] !== 'submitted') {
            set_flash('error', 'This milestone cannot be rejected in its current state.');
            redirect('/finalproject/client/contract_detail.php?id=' . $contractId);
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
            redirect('/finalproject/client/contract_detail.php?id=' . $contractId);
        }

        $stmt = $conn->prepare('UPDATE milestones SET status = "funded_in_escrow", updated_at = NOW() WHERE id = ?');
        $stmt->bind_param('i', $milestoneId);
        $stmt->execute();
        $stmt->close();

        set_flash('success', 'Milestone funded in escrow!');
        break;

    default:
        set_flash('error', 'Unknown action.');
        break;
}

$conn->close();
redirect('/finalproject/client/contract_detail.php?id=' . $contractId);
