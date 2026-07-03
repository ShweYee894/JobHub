<?php
/**
 * Accept Proposal Handler
 * 
 * Accepts a pending proposal, rejects all other pending proposals for the same job,
 * updates job status to in_progress, and creates a contract record.
 * Uses a database transaction to ensure atomicity.
 * 
 * Flow:
 *   1. Validate CSRF token and proposal_id
 *   2. Verify proposal exists, is pending, and belongs to client's job
 *   3. Begin transaction
 *   4. Mark proposal as accepted
 *   5. Reject all other pending proposals for the same job
 *   6. Update job status to in_progress
 *   7. Check for duplicate contract (same job_id)
 *   8. Insert contract with proposal_id, job_id, client_id, freelancer_id, total_budget
 *   9. Create chat room for the contract
 *  10. Send notification to freelancer
 *  11. Commit transaction
 *  12. Redirect to client contracts page
 */

require_once __DIR__ . '/../auth/auth.php';
require_role('client');
require_once __DIR__ . '/../config/db.php';

// ── Only accept POST requests ──────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect('/finalproject/client/proposals.php');
}

// ── CSRF verification ──────────────────────────────────────────────────
if (!verify_csrf_token()) {
    set_flash('error', 'Invalid security token. Please try again.');
    redirect('/finalproject/client/proposals.php');
}

// ── Validate proposal_id ──────────────────────────────────────────────
$proposalId = sanitize_int($_POST['proposal_id'] ?? 0);
$userId = $_SESSION['user_id'];

if ($proposalId <= 0) {
    set_flash('error', 'Invalid proposal reference.');
    redirect('/finalproject/client/proposals.php');
}

// ── Fetch proposal + verify it belongs to client's job ─────────────────
$stmt = $conn->prepare('
    SELECT p.id, p.job_id, p.freelancer_id, p.amount, p.status
    FROM proposals p
    JOIN jobs j ON p.job_id = j.id
    WHERE p.id = ? AND j.client_id = ?
');
$stmt->bind_param('ii', $proposalId, $userId);
$stmt->execute();
$proposal = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$proposal) {
    set_flash('error', 'Proposal not found or access denied.');
    redirect('/finalproject/client/proposals.php');
}

if ($proposal['status'] !== 'pending') {
    set_flash('error', 'Only pending proposals can be accepted.');
    redirect('/finalproject/client/proposal_detail.php?id=' . $proposalId);
}

$jobId = $proposal['job_id'];
$freelancerId = $proposal['freelancer_id'];
$budget = $proposal['amount'];

// ── Debug logging ─────────────────────────────────────────────────────
$debugLog = [
    'timestamp'     => date('Y-m-d H:i:s'),
    'proposal_id'   => $proposalId,
    'job_id'        => $jobId,
    'client_id'     => $userId,
    'freelancer_id' => $freelancerId,
    'budget'        => $budget,
    'session_uid'   => $_SESSION['user_id'],
];
error_log('[ACCEPT_PROPOSAL] ' . json_encode($debugLog));

// ── Begin transaction ─────────────────────────────────────────────────
$conn->begin_transaction();

try {
    // 1. Mark the accepted proposal
    $upd1 = $conn->prepare("UPDATE proposals SET status = 'accepted' WHERE id = ? AND status = 'pending'");
    $upd1->bind_param('i', $proposalId);
    $upd1->execute();
    if ($upd1->affected_rows === 0) {
        throw new Exception('Proposal is no longer pending.');
    }
    $upd1->close();

    // 2. Reject all other pending proposals for the same job
    $upd2 = $conn->prepare("UPDATE proposals SET status = 'rejected' WHERE job_id = ? AND id != ? AND status = 'pending'");
    $upd2->bind_param('ii', $jobId, $proposalId);
    $upd2->execute();
    $upd2->close();

    // 3. Update job status to in_progress
    $upd3 = $conn->prepare("UPDATE jobs SET status = 'in_progress' WHERE id = ? AND status = 'open'");
    $upd3->bind_param('i', $jobId);
    $upd3->execute();
    if ($upd3->affected_rows === 0) {
        // Job may already be in_progress; check if it exists
        $chk = $conn->prepare("SELECT status FROM jobs WHERE id = ?");
        $chk->bind_param('i', $jobId);
        $chk->execute();
        $jobRow = $chk->get_result()->fetch_assoc();
        $chk->close();
        if (!$jobRow || $jobRow['status'] !== 'in_progress') {
            throw new Exception('Job is not in a valid state for proposal acceptance.');
        }
    }
    $upd3->close();

    // 4. Duplicate protection: check if a contract already exists for this job
    $dupChk = $conn->prepare("SELECT id FROM contracts WHERE job_id = ?");
    $dupChk->bind_param('i', $jobId);
    $dupChk->execute();
    $existingContract = $dupChk->get_result()->fetch_assoc();
    $dupChk->close();

    if ($existingContract) {
        // Contract already exists for this job — skip insert, use existing
        $contractId = (int) $existingContract['id'];
        error_log('[ACCEPT_PROPOSAL] Duplicate contract detected for job_id=' . $jobId . ', using existing contract_id=' . $contractId);
    } else {
        // 5. Insert the contract (including proposal_id as required by schema)
        $contractType = 'fixed';
        $contractStatus = 'active';
        $ins1 = $conn->prepare(
            'INSERT INTO contracts (proposal_id, job_id, client_id, freelancer_id, contract_type, total_budget, status)
             VALUES (?, ?, ?, ?, ?, ?, ?)'
        );
        $ins1->bind_param('iiiisss', $proposalId, $jobId, $userId, $freelancerId, $contractType, $budget, $contractStatus);
        $ins1->execute();
        $contractId = (int) $ins1->insert_id;
        $ins1->close();

        error_log('[ACCEPT_PROPOSAL] New contract created: contract_id=' . $contractId);
    }

    // 6. Create chat room for the contract
    require_once __DIR__ . '/../shared/ChatRoomService.php';
    $chatRoomService = new ChatRoomService($conn);
    $chatRoomService->createRoomForContract($contractId);

    // 7. Notify the freelancer
    require_once __DIR__ . '/../shared/notification_helper.php';
    $clientName = $_SESSION['user_name'] ?? 'A client';
    notifyProposalAccepted($freelancerId, $clientName, $contractId);

    // 8. Commit the transaction
    $conn->commit();

    error_log('[ACCEPT_PROPOSAL] Transaction committed successfully. contract_id=' . $contractId);

    set_flash('success', 'Proposal accepted! A contract has been created and the freelancer has been notified.');
    redirect('/finalproject/client/contracts.php');

} catch (Exception $e) {
    $conn->rollback();
    error_log('[ACCEPT_PROPOSAL] Transaction failed: ' . $e->getMessage());
    set_flash('error', 'An error occurred while accepting the proposal. Please try again.');
    redirect('/finalproject/client/proposal_detail.php?id=' . $proposalId);
}
