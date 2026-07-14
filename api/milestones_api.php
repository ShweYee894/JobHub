<?php
/**
 * Milestones API (v2)
 * Handles CRUD, submission with data, revision flow, approval with payment, contract completion.
 *
 * GET  ?action=list&contract_id=X   - list milestones for a contract
 * GET  ?action=detail&id=X         - get single milestone
 * POST ?action=create              - create milestone (client only)
 * POST ?action=update              - update milestone (client, pending only)
 * POST ?action=submit              - submit work with GitHub URL, file, note (freelancer, funded_in_escrow only)
 * POST ?action=approve             - approve work + release payment + complete contract (client, submitted only)
 * POST ?action=request_revision    - request changes (client, submitted only)
 * POST ?action=dispute             - dispute milestone
 * POST ?action=resolve_dispute     - admin resolve dispute (release or refund)
 */

session_start();
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../auth/auth.php';

header('Content-Type: application/json');

if (!is_logged_in()) {
    json_response(['success' => false, 'message' => 'Authentication required.'], 401);
}

$userId   = (int) $_SESSION['user_id'];
$userRole = $_SESSION['user_role'] ?? '';
$action   = $_GET['action'] ?? $_POST['action'] ?? '';

// ── Helpers ────────────────────────────────────────────────────────────
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
    $stmt = $conn->prepare('SELECT m.*, c.client_id, c.freelancer_id, c.total_budget, c.id AS cid FROM milestones m JOIN contracts c ON m.contract_id = c.id WHERE m.id = ?');
    $stmt->bind_param('i', $milestoneId);
    $stmt->execute();
    $result = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    return $result;
}

function get_platform_fee_percent(): float {
    global $conn;
    $r = $conn->query("SELECT fee_percent FROM platform_fee_rules WHERE is_active = 1 ORDER BY effective_from DESC LIMIT 1");
    if ($r && $row = $r->fetch_assoc()) {
        return (float) $row['fee_percent'];
    }
    return 10.0;
}

function check_contract_completion(int $contractId): void {
    global $conn;
    $stmt = $conn->prepare('SELECT COUNT(*) AS total, SUM(CASE WHEN status = \'released\' THEN 1 ELSE 0 END) AS released FROM milestones WHERE contract_id = ?');
    $stmt->bind_param('i', $contractId);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    $total    = (int) $row['total'];
    $released = (int) ($row['released'] ?? 0);

    if ($total > 0 && $total === $released) {
        $stmt = $conn->prepare('UPDATE contracts SET status = \'completed\', updated_at = NOW() WHERE id = ? AND status = \'active\'');
        $stmt->bind_param('i', $contractId);
        $stmt->execute();
        $stmt->close();

        $stmt = $conn->prepare('UPDATE jobs SET status = \'completed\', updated_at = NOW() WHERE id = (SELECT job_id FROM contracts WHERE id = ?)');
        $stmt->bind_param('i', $contractId);
        $stmt->execute();
        $stmt->close();

        $stmt = $conn->prepare('UPDATE clients SET total_spent = total_spent + (SELECT COALESCE(SUM(total_amount), 0) FROM payments p JOIN milestones m ON p.milestone_id = m.id WHERE m.contract_id = ? AND p.status = \'completed\') WHERE client_id = (SELECT client_id FROM contracts WHERE id = ?)');
        $stmt->bind_param('ii', $contractId, $contractId);
        $stmt->execute();
        $stmt->close();
    }
}

function handle_file_upload(string $fieldName): ?string {
    if (!isset($_FILES[$fieldName]) || $_FILES[$fieldName]['error'] !== UPLOAD_ERR_OK) {
        return null;
    }
    $file = $_FILES[$fieldName];
    $ext  = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    $allowed = ['zip', 'rar', 'pdf', 'doc', 'docx', 'txt', 'png', 'jpg', 'jpeg', 'gif'];
    if (!in_array($ext, $allowed)) {
        return null;
    }
    if ($file['size'] > 10 * 1024 * 1024) {
        return null;
    }
    $uploadDir = __DIR__ . '/../assets/upload/submissions/';
    if (!is_dir($uploadDir)) {
        mkdir($uploadDir, 0755, true);
    }
    $filename = 'sub_' . bin2hex(random_bytes(8)) . '.' . $ext;
    if (move_uploaded_file($file['tmp_name'], $uploadDir . $filename)) {
        return $filename;
    }
    return null;
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

        $stmt = $conn->prepare('SELECT * FROM milestones WHERE contract_id = ? ORDER BY sort_order ASC, created_at ASC');
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

        if ($userId !== (int) $milestone['client_id'] && $userId !== (int) $milestone['freelancer_id']) {
            json_response(['success' => false, 'message' => 'Access denied.'], 403);
        }

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
        $description = trim($_POST['description'] ?? '');
        $dueDate     = trim($_POST['due_date'] ?? '');

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
            if (!empty($dueDate)) {
                $stmt = $conn->prepare('INSERT INTO milestones (contract_id, title, amount, description, due_date, status, created_at, updated_at) VALUES (?, ?, ?, ?, ?, \'pending\', NOW(), NOW())');
                $stmt->bind_param('isdss', $contractId, $title, $amount, $description, $dueDate);
            } else {
                $stmt = $conn->prepare('INSERT INTO milestones (contract_id, title, amount, description, status, created_at, updated_at) VALUES (?, ?, ?, ?, \'pending\', NOW(), NOW())');
                $stmt->bind_param('isd', $contractId, $title, $amount, $description);
            }
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
    // Now accepts: github_url, submission_file, submission_note
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

        $githubUrl  = trim($_POST['github_url'] ?? '');
        $note       = trim($_POST['submission_note'] ?? '');

        if (empty($githubUrl)) {
            json_response(['success' => false, 'message' => 'GitHub repository URL is required.'], 400);
        }
        if (!preg_match('/^https?:\/\/.+/i', $githubUrl)) {
            json_response(['success' => false, 'message' => 'Please enter a valid URL.'], 400);
        }
        if (strlen($note) > 2000) {
            json_response(['success' => false, 'message' => 'Submission note must be 2000 characters or fewer.'], 400);
        }

        $submissionFile = handle_file_upload('submission_file');

        $conn->begin_transaction();
        try {
            $stmt = $conn->prepare('UPDATE milestones SET status = \'submitted\', submission_github_url = ?, submission_file = ?, submission_note = ?, submission_date = NOW(), updated_at = NOW() WHERE id = ?');
            $stmt->bind_param('sssi', $githubUrl, $submissionFile, $note, $milestoneId);
            $stmt->execute();
            $stmt->close();

            $conn->commit();

            $updated = get_milestone($milestoneId);
            json_response([
                'success'   => true,
                'message'   => 'Work submitted successfully. Awaiting client review.',
                'milestone' => $updated,
            ]);
        } catch (Exception $e) {
            $conn->rollback();
            json_response(['success' => false, 'message' => 'Failed to submit work.'], 500);
        }
        break;

    // ═══════════════════════════════════════════════════════════════════
    // REQUEST REVISION (client only, submitted only)
    // Returns milestone to funded_in_escrow status
    // ═══════════════════════════════════════════════════════════════════
    case 'request_revision':
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            json_response(['success' => false, 'message' => 'POST request required.'], 405);
        }
        if (!verify_csrf_token()) {
            json_response(['success' => false, 'message' => 'Invalid CSRF token.'], 403);
        }
        if ($userRole !== 'client') {
            json_response(['success' => false, 'message' => 'Only clients can request revisions.'], 403);
        }

        $milestoneId = sanitize_int($_POST['milestone_id'] ?? 0);
        $revisionNote = trim($_POST['revision_note'] ?? '');

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
            json_response(['success' => false, 'message' => 'Only submitted milestones can have revisions requested.'], 400);
        }

        $conn->begin_transaction();
        try {
            $stmt = $conn->prepare('UPDATE milestones SET status = \'funded_in_escrow\', submission_github_url = NULL, submission_file = NULL, submission_note = ?, submission_date = NULL, updated_at = NOW() WHERE id = ?');
            $stmt->bind_param('si', $revisionNote, $milestoneId);
            $stmt->execute();
            $stmt->close();

            $conn->commit();

            $updated = get_milestone($milestoneId);
            json_response([
                'success'   => true,
                'message'   => 'Revision requested. The freelancer will be notified.',
                'milestone' => $updated,
            ]);
        } catch (Exception $e) {
            $conn->rollback();
            json_response(['success' => false, 'message' => 'Failed to request revision.'], 500);
        }
        break;

    // ═══════════════════════════════════════════════════════════════════
    // APPROVE work (client only, submitted only)
    // Triggers: payment release, wallet update, contract completion
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
        $feePercent   = get_platform_fee_percent();
        $platformFee  = round($amount * ($feePercent / 100), 2);
        $freelancerNet = round($amount - $platformFee, 2);

        $conn->begin_transaction();
        try {
            $stmt = $conn->prepare('UPDATE milestones SET status = \'released\', updated_at = NOW() WHERE id = ?');
            $stmt->bind_param('i', $milestoneId);
            $stmt->execute();
            $stmt->close();

            $stmt = $conn->prepare('SELECT wallet_balance FROM users WHERE id = ?');
            $stmt->bind_param('i', $freelancerId);
            $stmt->execute();
            $freelancerBalance = (float) $stmt->get_result()->fetch_assoc()['wallet_balance'];
            $stmt->close();

            $stmt = $conn->prepare('UPDATE users SET wallet_balance = wallet_balance + ? WHERE id = ?');
            $stmt->bind_param('di', $freelancerNet, $freelancerId);
            $stmt->execute();
            $stmt->close();

            $stmt = $conn->prepare("UPDATE payments SET status = 'completed' WHERE milestone_id = ?");
            $stmt->bind_param('i', $milestoneId);
            $stmt->execute();
            $stmt->close();

            $newFreelancerBalance = $freelancerBalance + $freelancerNet;
            $stmt = $conn->prepare('INSERT INTO wallet_transactions (user_id, type, amount, balance_after, reference_id, reference_type, description, created_at) VALUES (?, \'escrow_release\', ?, ?, ?, \'milestone\', ?, NOW())');
            $desc = 'Payment received for milestone #' . $milestoneId;
            $stmt->bind_param('iddis', $freelancerId, $freelancerNet, $newFreelancerBalance, $milestoneId, $desc);
            $stmt->execute();
            $stmt->close();

            check_contract_completion((int) $milestone['contract_id']);

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

    // ── RESOLVE DISPUTE (admin only) ──────────────────────────────────
    case 'resolve_dispute':
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            json_response(['success' => false, 'message' => 'POST request required.'], 405);
        }
        if (!verify_csrf_token()) {
            json_response(['success' => false, 'message' => 'Invalid CSRF token.'], 403);
        }
        if ($userRole !== 'admin') {
            json_response(['success' => false, 'message' => 'Admin access required.'], 403);
        }

        $milestoneId = sanitize_int($_POST['milestone_id'] ?? 0);
        $resolution  = $_POST['resolution'] ?? '';
        $adminNote   = trim($_POST['admin_note'] ?? '');

        if ($milestoneId <= 0) {
            json_response(['success' => false, 'message' => 'Invalid milestone ID.'], 400);
        }
        if (!in_array($resolution, ['release', 'refund'])) {
            json_response(['success' => false, 'message' => 'Invalid resolution action.'], 400);
        }

        $milestone = get_milestone($milestoneId);
        if (!$milestone) {
            json_response(['success' => false, 'message' => 'Milestone not found.'], 404);
        }
        if ($milestone['status'] !== 'disputed') {
            json_response(['success' => false, 'message' => 'This milestone is not disputed.'], 400);
        }

        $conn->begin_transaction();
        try {
            if ($resolution === 'release') {
                // Release payment to freelancer
                $amount = (float) $milestone['amount'];
                $feePct = get_platform_fee_percent();
                $platformFee = round($amount * $feePct / 100, 2);
                $freelancerNet = $amount - $platformFee;

                $stmt = $conn->prepare('UPDATE milestones SET status = \'released\', updated_at = NOW() WHERE id = ?');
                $stmt->bind_param('i', $milestoneId);
                $stmt->execute();
                $stmt->close();

                $milestoneTitle = $milestone['title'];
                $stmt = $conn->prepare('INSERT INTO payments (milestone_id, payer_id, payee_id, total_amount, platform_fee, freelancer_net, status, created_at) VALUES (?, ?, ?, ?, ?, ?, \'completed\', NOW())');
                $stmt->bind_param('iiiddd', $milestoneId, $milestone['client_id'], $milestone['freelancer_id'], $amount, $platformFee, $freelancerNet);
                $stmt->execute();
                $stmt->close();

                $paymentId = $conn->insert_id;

                // Credit freelancer wallet
                $freelancerId = (int) $milestone['freelancer_id'];
                $stmt = $conn->prepare('SELECT wallet_balance FROM users WHERE id = ?');
                $stmt->bind_param('i', $freelancerId);
                $stmt->execute();
                $freelancerBalance = (float) $stmt->get_result()->fetch_assoc()['wallet_balance'];
                $stmt->close();

                $newFreelancerBalance = round($freelancerBalance + $freelancerNet, 2);
                $stmt = $conn->prepare('UPDATE users SET wallet_balance = wallet_balance + ? WHERE id = ?');
                $stmt->bind_param('di', $freelancerNet, $freelancerId);
                $stmt->execute();
                $stmt->close();

                $freelancerNetFmt = number_format($freelancerNet, 2, '.', '');
                $stmt = $conn->prepare('INSERT INTO wallet_transactions (user_id, type, amount, balance_after, reference_id, reference_type, description, created_at) VALUES (?, \'escrow_release\', ?, ?, ?, \'payment\', ?, NOW())');
                $desc = 'Milestone payment released (Admin resolution): ' . $milestoneTitle;
                $stmt->bind_param('iddis', $freelancerId, $freelancerNet, $newFreelancerBalance, $paymentId, $desc);
                $stmt->execute();
                $stmt->close();

                // Log to user_behavior_logs
                $payload = json_encode([
                    'milestone_id'   => $milestoneId,
                    'freelancer_id'  => $freelancerId,
                    'gross_amount'   => $amount,
                    'platform_fee'   => $platformFee,
                    'freelancer_net' => $freelancerNet,
                    'old_balance'    => $freelancerBalance,
                    'new_balance'    => $newFreelancerBalance,
                    'resolution'     => 'release',
                ]);
                $ip = get_ip_address();
                $logStmt = $conn->prepare('INSERT INTO user_behavior_logs (user_id, action_type, ip_address, payload, created_at) VALUES (?, \'admin_dispute_release\', ?, ?, NOW())');
                $logStmt->bind_param('iss', $freelancerId, $ip, $payload);
                $logStmt->execute();
                $logStmt->close();

                $stmt = $conn->prepare('UPDATE clients SET total_spent = total_spent + ? WHERE client_id = ?');
                $stmt->bind_param('di', $amount, $milestone['client_id']);
                $stmt->execute();
                $stmt->close();

                check_contract_completion($milestone['cid']);

                $conn->commit();
                json_response(['success' => true, 'message' => 'Dispute resolved. Payment released to freelancer.']);
            } else {
                // Refund client
                $clientId = (int) $milestone['client_id'];
                $amount = (float) $milestone['amount'];

                // Credit client wallet
                $stmt = $conn->prepare('SELECT wallet_balance FROM users WHERE id = ?');
                $stmt->bind_param('i', $clientId);
                $stmt->execute();
                $clientBalance = (float) $stmt->get_result()->fetch_assoc()['wallet_balance'];
                $stmt->close();

                $newClientBalance = round($clientBalance + $amount, 2);
                $stmt = $conn->prepare('UPDATE users SET wallet_balance = wallet_balance + ? WHERE id = ?');
                $stmt->bind_param('di', $amount, $clientId);
                $stmt->execute();
                $stmt->close();

                // Update milestone status to pending
                $stmt = $conn->prepare('UPDATE milestones SET status = \'pending\', updated_at = NOW() WHERE id = ?');
                $stmt->bind_param('i', $milestoneId);
                $stmt->execute();
                $stmt->close();

                // Update payment status to refunded
                $stmt = $conn->prepare("UPDATE payments SET status = 'refunded', refund_amount = ?, refunded_at = NOW() WHERE milestone_id = ? AND status = 'completed'");
                $stmt->bind_param('di', $amount, $milestoneId);
                $stmt->execute();
                $stmt->close();

                // Log wallet transaction
                $stmt = $conn->prepare('INSERT INTO wallet_transactions (user_id, type, amount, balance_after, reference_id, reference_type, description, created_at) VALUES (?, \'refund\', ?, ?, ?, \'milestone\', ?, NOW())');
                $desc = 'Refund for milestone #' . $milestoneId . ' (Dispute resolved)';
                $stmt->bind_param('iddis', $clientId, $amount, $newClientBalance, $milestoneId, $desc);
                $stmt->execute();
                $stmt->close();

                // Log to user_behavior_logs
                $payload = json_encode([
                    'milestone_id' => $milestoneId,
                    'amount'       => $amount,
                    'old_balance'  => $clientBalance,
                    'new_balance'  => $newClientBalance,
                    'resolution'   => 'refund',
                ]);
                $ip = get_ip_address();
                $logStmt = $conn->prepare('INSERT INTO user_behavior_logs (user_id, action_type, ip_address, payload, created_at) VALUES (?, \'admin_dispute_refund\', ?, ?, NOW())');
                $logStmt->bind_param('iss', $clientId, $ip, $payload);
                $logStmt->execute();
                $logStmt->close();

                $conn->commit();
                json_response(['success' => true, 'message' => 'Dispute resolved. Refund of ' . format_currency($amount) . ' returned to client wallet.']);
            }
        } catch (Exception $e) {
            $conn->rollback();
            json_response(['success' => false, 'message' => 'Failed to resolve dispute.'], 500);
        }
        break;

    default:
        json_response(['success' => false, 'message' => 'Invalid action.'], 400);
        break;
}
