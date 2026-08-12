<?php
/**
 * Dispute Tickets API
 * Handles dispute creation, listing, viewing, resolution, and evidence management.
 *
 * POST ?action=create           - Create a new dispute ticket (with optional file uploads)
 * GET  ?action=list             - List disputes (admin: all, user: own)
 * GET  ?action=detail&id=X      - Get dispute details
 * POST ?action=update_status    - Admin: update dispute status
 * POST ?action=resolve          - Admin: resolve dispute with resolution
 * POST ?action=dismiss          - Admin: dismiss dispute
 * POST ?action=upload_evidence  - Upload additional evidence files
 * POST ?action=request_evidence - Admin: request evidence from a party
 * POST ?action=respond_request  - Respond to admin evidence request
 * GET  ?action=download_evidence&id=X - Download evidence file
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

// ── File Upload Handler ──────────────────────────────────────────────────
function handle_evidence_upload(array $file): ?array
{
    if ($file['error'] !== UPLOAD_ERR_OK) {
        return null;
    }

    $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    $allowed = ['pdf', 'doc', 'docx', 'txt', 'png', 'jpg', 'jpeg', 'gif', 'zip', 'rar'];
    if (!in_array($ext, $allowed)) {
        return null;
    }
    if ($file['size'] > 10 * 1024 * 1024) {
        return null;
    }

    $uploadDir = __DIR__ . '/../assets/upload/disputes/';
    if (!is_dir($uploadDir)) {
        mkdir($uploadDir, 0755, true);
    }

    $storedFilename = 'evd_' . bin2hex(random_bytes(8)) . '.' . $ext;
    if (move_uploaded_file($file['tmp_name'], $uploadDir . $storedFilename)) {
        $finfo = new finfo(FILEINFO_MIME_TYPE);
        $realMime = $finfo->file($uploadDir . $storedFilename);
        return [
            'original_filename' => $file['name'],
            'stored_filename'   => $storedFilename,
            'file_size'         => $file['size'],
            'mime_type'         => $realMime ?: ($file['type'] ?: 'application/octet-stream'),
        ];
    }
    return null;
}

switch ($action) {

    // ── CREATE DISPUTE ─────────────────────────────────────────────────
    case 'create':
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            json_response(['success' => false, 'message' => 'POST request required.'], 405);
        }
        if (!verify_csrf_token()) {
            json_response(['success' => false, 'message' => 'Invalid CSRF token.'], 403);
        }

        $contractId  = sanitize_int($_POST['contract_id'] ?? 0);
        $milestoneId = sanitize_int($_POST['milestone_id'] ?? 0);
        $reason      = $_POST['reason'] ?? '';
        $description = trim($_POST['description'] ?? '');

        if ($contractId <= 0) {
            json_response(['success' => false, 'message' => 'Invalid contract ID.'], 400);
        }
        $allowedReasons = ['non_delivery', 'quality_issue', 'scope_dispute', 'payment_issue', 'other'];
        if (!in_array($reason, $allowedReasons)) {
            json_response(['success' => false, 'message' => 'Invalid dispute reason.'], 400);
        }
        if (empty($description) || strlen($description) < 20) {
            json_response(['success' => false, 'message' => 'Description must be at least 20 characters.'], 400);
        }
        if (strlen($description) > 5000) {
            json_response(['success' => false, 'message' => 'Description must be 5000 characters or fewer.'], 400);
        }

        // Verify contract exists and user is part of it
        $stmt = $conn->prepare('SELECT id, client_id, freelancer_id, status FROM contracts WHERE id = ?');
        $stmt->bind_param('i', $contractId);
        $stmt->execute();
        $contract = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        if (!$contract) {
            json_response(['success' => false, 'message' => 'Contract not found.'], 404);
        }

        $isClient     = ((int) $contract['client_id'] === $userId);
        $isFreelancer = ((int) $contract['freelancer_id'] === $userId);

        if (!$isClient && !$isFreelancer) {
            json_response(['success' => false, 'message' => 'You are not part of this contract.'], 403);
        }

        if ($contract['status'] === 'completed' || $contract['status'] === 'cancelled') {
            json_response(['success' => false, 'message' => 'Cannot dispute a completed or cancelled contract.'], 400);
        }

        // Check for existing open dispute on this contract
        $stmt = $conn->prepare('SELECT id FROM dispute_tickets WHERE contract_id = ? AND status IN ("open", "investigating", "escalated")');
        $stmt->bind_param('i', $contractId);
        $stmt->execute();
        $existing = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        if ($existing) {
            json_response(['success' => false, 'message' => 'There is already an open dispute for this contract.'], 409);
        }

        // Verify milestone belongs to this contract (if provided)
        $milestone = null;
        if ($milestoneId > 0) {
            $stmt = $conn->prepare('SELECT id, status, title FROM milestones WHERE id = ? AND contract_id = ?');
            $stmt->bind_param('ii', $milestoneId, $contractId);
            $stmt->execute();
            $milestone = $stmt->get_result()->fetch_assoc();
            $stmt->close();

            if (!$milestone) {
                json_response(['success' => false, 'message' => 'Milestone not found for this contract.'], 404);
            }
            if ($milestone['status'] === 'disputed') {
                json_response(['success' => false, 'message' => 'This milestone already has an active dispute.'], 409);
            }
        }

        // Handle evidence file uploads
        $evidenceFiles = [];
        if (!empty($_FILES['evidence_files']['name'][0])) {
            $fileCount = min(count($_FILES['evidence_files']['name']), 5);
            for ($i = 0; $i < $fileCount; $i++) {
                $fileData = [
                    'name' => $_FILES['evidence_files']['name'][$i],
                    'type' => $_FILES['evidence_files']['type'][$i],
                    'tmp_name' => $_FILES['evidence_files']['tmp_name'][$i],
                    'error' => $_FILES['evidence_files']['error'][$i],
                    'size' => $_FILES['evidence_files']['size'][$i],
                ];
                $uploaded = handle_evidence_upload($fileData);
                if ($uploaded) {
                    $evidenceFiles[] = $uploaded;
                }
            }
        }

        $againstId = $isClient ? (int) $contract['freelancer_id'] : (int) $contract['client_id'];
        $milestoneParam = $milestoneId > 0 ? $milestoneId : null;
        $evidenceJson = !empty($evidenceFiles) ? json_encode($evidenceFiles) : null;

        $conn->begin_transaction();
        try {
            $stmt = $conn->prepare(
                'INSERT INTO dispute_tickets (contract_id, milestone_id, raised_by, against, reason, description, evidence_files, status, created_at)
                 VALUES (?, ?, ?, ?, ?, ?, ?, "open", NOW())'
            );
            $stmt->bind_param('iiissss', $contractId, $milestoneParam, $userId, $againstId, $reason, $description, $evidenceJson);
            $stmt->execute();
            $disputeId = $stmt->insert_id;
            $stmt->close();

            // Update milestone status to disputed
            if ($milestoneId > 0) {
                $stmt = $conn->prepare('UPDATE milestones SET status = "disputed", updated_at = NOW() WHERE id = ?');
                $stmt->bind_param('i', $milestoneId);
                $stmt->execute();
                $stmt->close();
            }

            // Update contract dispute status
            $stmt = $conn->prepare('UPDATE contracts SET dispute_status = "open", updated_at = NOW() WHERE id = ?');
            $stmt->bind_param('i', $contractId);
            $stmt->execute();
            $stmt->close();

            // Notify the other party about the dispute
            require_once __DIR__ . '/../shared/notification_helper.php';
            $disputerName = $isClient ? 'A client' : 'A freelancer';
            $stmtUser = $conn->prepare('SELECT name FROM users WHERE id = ?');
            $stmtUser->bind_param('i', $userId);
            $stmtUser->execute();
            $disputerName = $stmtUser->get_result()->fetch_assoc()['name'] ?? $disputerName;
            $stmtUser->close();

            $stmtJob = $conn->prepare('SELECT j.title FROM contracts c JOIN jobs j ON c.job_id = j.id WHERE c.id = ?');
            $stmtJob->bind_param('i', $contractId);
            $stmtJob->execute();
            $jobTitle = $stmtJob->get_result()->fetch_assoc()['title'] ?? 'Contract';
            $stmtJob->close();

            notifyDisputeOpened($againstId, $disputerName, $jobTitle, $contractId, $milestoneId);

            // Notify all admins
            $adminStmt = $conn->prepare('SELECT id FROM users WHERE role = "admin"');
            $adminStmt->execute();
            $adminResult = $adminStmt->get_result();
            while ($admin = $adminResult->fetch_assoc()) {
                if ((int) $admin['id'] !== $userId) {
                    notifyDisputeOpened((int) $admin['id'], $disputerName, $jobTitle, $contractId, $milestoneId);
                }
            }
            $adminStmt->close();

            $conn->commit();
            json_response([
                'success'    => true,
                'message'    => 'Dispute ticket created successfully. Our team will review it shortly.',
                'dispute_id' => $disputeId,
                'files_uploaded' => count($evidenceFiles),
            ]);
        } catch (Exception $e) {
            $conn->rollback();
            json_response(['success' => false, 'message' => 'Failed to create dispute ticket.'], 500);
        }
        break;

    // ── LIST DISPUTES ──────────────────────────────────────────────────
    case 'list':
        $page    = max(1, sanitize_int($_GET['page'] ?? 1));
        $perPage = 15;
        $statusF = $_GET['status'] ?? '';
        $offset  = ($page - 1) * $perPage;

        $allowedStatuses = ['open', 'investigating', 'resolved', 'dismissed', 'escalated'];
        if ($statusF && !in_array($statusF, $allowedStatuses)) {
            $statusF = '';
        }

        $where  = [];
        $params = [];
        $types  = '';

        if ($userRole !== 'admin') {
            $where[]  = '(d.raised_by = ? OR d.against = ?)';
            $params[] = $userId;
            $params[] = $userId;
            $types  .= 'ii';
        }
        if ($statusF !== '') {
            $where[]  = 'd.status = ?';
            $params[] = $statusF;
            $types  .= 's';
        }

        $whereSql = $where ? 'WHERE ' . implode(' AND ', $where) : '';

        // Count
        $countSql = "SELECT COUNT(*) AS cnt FROM dispute_tickets d $whereSql";
        $countStmt = $conn->prepare($countSql);
        if ($types) $countStmt->bind_param($types, ...$params);
        $countStmt->execute();
        $totalItems = (int) $countStmt->get_result()->fetch_assoc()['cnt'];
        $countStmt->close();
        $totalPages = max(1, (int) ceil($totalItems / $perPage));

        // Fetch
        $querySql = "SELECT d.*, 
                            c.total_budget, c.status AS contract_status,
                            j.title AS job_title,
                            ru.name AS raised_by_name, ru.profile_image AS raised_by_image,
                            au.name AS against_name, au.profile_image AS against_image,
                            mu.name AS milestone_title,
                            rv.name AS resolved_by_name
                     FROM dispute_tickets d
                     JOIN contracts c ON d.contract_id = c.id
                     JOIN jobs j ON c.job_id = j.id
                     JOIN users ru ON d.raised_by = ru.id
                     JOIN users au ON d.against = au.id
                     LEFT JOIN milestones m ON d.milestone_id = m.id
                     LEFT JOIN users mu ON m.title IS NOT NULL AND 1 = 0
                     LEFT JOIN users rv ON d.resolved_by = rv.id
                     $whereSql
                     ORDER BY FIELD(d.status, 'open', 'investigating', 'escalated', 'resolved', 'dismissed'), d.created_at DESC
                     LIMIT ? OFFSET ?";
        $types .= 'ii';
        $params[] = $perPage;
        $params[] = $offset;

        $queryStmt = $conn->prepare($querySql);
        $queryStmt->bind_param($types, ...$params);
        $queryStmt->execute();
        $result = $queryStmt->get_result();
        $queryStmt->close();

        $disputes = [];
        while ($row = $result->fetch_assoc()) {
            $disputes[] = $row;
        }

        json_response([
            'success'     => true,
            'disputes'    => $disputes,
            'total_items' => $totalItems,
            'total_pages' => $totalPages,
            'page'        => $page,
        ]);
        break;

    // ── DETAIL ─────────────────────────────────────────────────────────
    case 'detail':
        $disputeId = sanitize_int($_GET['id'] ?? 0);
        if ($disputeId <= 0) {
            json_response(['success' => false, 'message' => 'Invalid dispute ID.'], 400);
        }

        $stmt = $conn->prepare(
            'SELECT d.*, 
                    c.total_budget, c.status AS contract_status, c.client_id, c.freelancer_id,
                    j.title AS job_title,
                    ru.name AS raised_by_name, ru.email AS raised_by_email, ru.profile_image AS raised_by_image,
                    au.name AS against_name, au.email AS against_email, au.profile_image AS against_image,
                    m.title AS milestone_title, m.amount AS milestone_amount, m.status AS milestone_status,
                    rv.name AS resolved_by_name
             FROM dispute_tickets d
             JOIN contracts c ON d.contract_id = c.id
             JOIN jobs j ON c.job_id = j.id
             JOIN users ru ON d.raised_by = ru.id
             JOIN users au ON d.against = au.id
             LEFT JOIN milestones m ON d.milestone_id = m.id
             LEFT JOIN users rv ON d.resolved_by = rv.id
             WHERE d.id = ?'
        );
        $stmt->bind_param('i', $disputeId);
        $stmt->execute();
        $dispute = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        if (!$dispute) {
            json_response(['success' => false, 'message' => 'Dispute not found.'], 404);
        }

        // Non-admins can only view their own disputes
        if ($userRole !== 'admin' && (int) $dispute['raised_by'] !== $userId && (int) $dispute['against'] !== $userId) {
            json_response(['success' => false, 'message' => 'Access denied.'], 403);
        }

        json_response(['success' => true, 'dispute' => $dispute]);
        break;

    // ── UPDATE STATUS (admin) ──────────────────────────────────────────
    case 'update_status':
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            json_response(['success' => false, 'message' => 'POST request required.'], 405);
        }
        if (!verify_csrf_token()) {
            json_response(['success' => false, 'message' => 'Invalid CSRF token.'], 403);
        }
        if ($userRole !== 'admin') {
            json_response(['success' => false, 'message' => 'Admin access required.'], 403);
        }

        $disputeId = sanitize_int($_POST['dispute_id'] ?? 0);
        $newStatus = $_POST['status'] ?? '';
        $allowed   = ['open', 'investigating', 'resolved', 'dismissed', 'escalated'];

        if ($disputeId <= 0 || !in_array($newStatus, $allowed)) {
            json_response(['success' => false, 'message' => 'Invalid parameters.'], 400);
        }

        $stmt = $conn->prepare('SELECT id, status, contract_id FROM dispute_tickets WHERE id = ?');
        $stmt->bind_param('i', $disputeId);
        $stmt->execute();
        $dispute = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        if (!$dispute) {
            json_response(['success' => false, 'message' => 'Dispute not found.'], 404);
        }

        $stmt = $conn->prepare('UPDATE dispute_tickets SET status = ?, updated_at = NOW() WHERE id = ?');
        $stmt->bind_param('si', $newStatus, $disputeId);
        $stmt->execute();
        $stmt->close();

        // Update contract dispute_status
        $statusMap = ['resolved' => 'resolved', 'dismissed' => 'resolved', 'escalated' => 'escalated'];
        $contractDisputeStatus = $statusMap[$newStatus] ?? 'open';
        $stmt = $conn->prepare('UPDATE contracts SET dispute_status = ?, updated_at = NOW() WHERE id = ?');
        $stmt->bind_param('si', $contractDisputeStatus, $dispute['contract_id']);
        $stmt->execute();
        $stmt->close();

        json_response(['success' => true, 'message' => 'Dispute status updated to ' . ucfirst($newStatus) . '.']);
        break;

    // ── RESOLVE (admin) ────────────────────────────────────────────────
    case 'resolve':
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            json_response(['success' => false, 'message' => 'POST request required.'], 405);
        }
        if (!verify_csrf_token()) {
            json_response(['success' => false, 'message' => 'Invalid CSRF token.'], 403);
        }
        if ($userRole !== 'admin') {
            json_response(['success' => false, 'message' => 'Admin access required.'], 403);
        }

        $disputeId  = sanitize_int($_POST['dispute_id'] ?? 0);
        $resolution = trim($_POST['resolution'] ?? '');

        if ($disputeId <= 0) {
            json_response(['success' => false, 'message' => 'Invalid dispute ID.'], 400);
        }
        if (empty($resolution) || strlen($resolution) < 10) {
            json_response(['success' => false, 'message' => 'Resolution must be at least 10 characters.'], 400);
        }

        $stmt = $conn->prepare('SELECT id, status, contract_id, milestone_id, raised_by, against FROM dispute_tickets WHERE id = ?');
        $stmt->bind_param('i', $disputeId);
        $stmt->execute();
        $dispute = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        if (!$dispute) {
            json_response(['success' => false, 'message' => 'Dispute not found.'], 404);
        }
        if ($dispute['status'] === 'resolved' || $dispute['status'] === 'dismissed') {
            json_response(['success' => false, 'message' => 'This dispute has already been resolved.'], 400);
        }

        $stmt = $conn->prepare('SELECT j.title AS job_title, c.client_id, c.freelancer_id FROM contracts c JOIN jobs j ON c.job_id = j.id WHERE c.id = ?');
        $stmt->bind_param('i', $dispute['contract_id']);
        $stmt->execute();
        $contractInfo = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        $adminStmt = $conn->prepare('SELECT name FROM users WHERE id = ?');
        $adminStmt->bind_param('i', $userId);
        $adminStmt->execute();
        $adminRow = $adminStmt->get_result()->fetch_assoc();
        $adminStmt->close();
        $adminName = $adminRow['name'] ?? 'Admin';
        $contractTitle = $contractInfo['job_title'] ?? 'Contract #' . $dispute['contract_id'];

        $conn->begin_transaction();
        try {
            $stmt = $conn->prepare(
                'UPDATE dispute_tickets SET status = "resolved", resolution = ?, resolved_by = ?, updated_at = NOW() WHERE id = ?'
            );
            $stmt->bind_param('sii', $resolution, $userId, $disputeId);
            $stmt->execute();
            $stmt->close();

            $stmt = $conn->prepare('UPDATE contracts SET status = "active", dispute_status = "resolved", updated_at = NOW() WHERE id = ?');
            $stmt->bind_param('i', $dispute['contract_id']);
            $stmt->execute();
            $stmt->close();

            if (!empty($dispute['milestone_id'])) {
                $stmt = $conn->prepare('UPDATE milestones SET status = "pending", updated_at = NOW() WHERE id = ? AND status = "disputed"');
                $stmt->bind_param('i', $dispute['milestone_id']);
                $stmt->execute();
                $stmt->close();
            } else {
                $stmt = $conn->prepare('UPDATE milestones SET status = "pending", updated_at = NOW() WHERE contract_id = ? AND status = "disputed"');
                $stmt->bind_param('i', $dispute['contract_id']);
                $stmt->execute();
                $stmt->close();
            }

            $conn->commit();
        } catch (Exception $e) {
            $conn->rollback();
            json_response(['success' => false, 'message' => 'Failed to resolve dispute.'], 500);
        }

        require_once __DIR__ . '/../shared/notification_helper.php';
        $parties = [$dispute['raised_by'], $dispute['against']];
        foreach ($parties as $partyId) {
            if ((int)$partyId !== $userId) {
                notifyDisputeResolved((int)$partyId, $adminName, $contractTitle, (int)$dispute['contract_id'], $resolution, (int) ($dispute['milestone_id'] ?? 0));
            }
        }

        json_response(['success' => true, 'message' => 'Dispute resolved successfully.']);
        break;

    // ── DISMISS (admin) ────────────────────────────────────────────────
    case 'dismiss':
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            json_response(['success' => false, 'message' => 'POST request required.'], 405);
        }
        if (!verify_csrf_token()) {
            json_response(['success' => false, 'message' => 'Invalid CSRF token.'], 403);
        }
        if ($userRole !== 'admin') {
            json_response(['success' => false, 'message' => 'Admin access required.'], 403);
        }

        $disputeId = sanitize_int($_POST['dispute_id'] ?? 0);
        $resolution = trim($_POST['resolution'] ?? '');

        if ($disputeId <= 0) {
            json_response(['success' => false, 'message' => 'Invalid dispute ID.'], 400);
        }
        if (empty($resolution) || strlen($resolution) < 10) {
            json_response(['success' => false, 'message' => 'Dismissal reason must be at least 10 characters.'], 400);
        }

        $stmt = $conn->prepare('SELECT id, status, contract_id, milestone_id, raised_by, against FROM dispute_tickets WHERE id = ?');
        $stmt->bind_param('i', $disputeId);
        $stmt->execute();
        $dispute = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        if (!$dispute) {
            json_response(['success' => false, 'message' => 'Dispute not found.'], 404);
        }
        if ($dispute['status'] === 'resolved' || $dispute['status'] === 'dismissed') {
            json_response(['success' => false, 'message' => 'This dispute has already been closed.'], 400);
        }

        $stmt = $conn->prepare('SELECT j.title AS job_title FROM contracts c JOIN jobs j ON c.job_id = j.id WHERE c.id = ?');
        $stmt->bind_param('i', $dispute['contract_id']);
        $stmt->execute();
        $contractInfo = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        $adminStmt = $conn->prepare('SELECT name FROM users WHERE id = ?');
        $adminStmt->bind_param('i', $userId);
        $adminStmt->execute();
        $adminRow = $adminStmt->get_result()->fetch_assoc();
        $adminStmt->close();
        $adminName = $adminRow['name'] ?? 'Admin';
        $contractTitle = $contractInfo['job_title'] ?? 'Contract #' . $dispute['contract_id'];

        $conn->begin_transaction();
        try {
            $stmt = $conn->prepare(
                'UPDATE dispute_tickets SET status = "dismissed", resolution = ?, resolved_by = ?, updated_at = NOW() WHERE id = ?'
            );
            $stmt->bind_param('sii', $resolution, $userId, $disputeId);
            $stmt->execute();
            $stmt->close();

            $stmt = $conn->prepare('UPDATE contracts SET status = "active", dispute_status = "resolved", updated_at = NOW() WHERE id = ?');
            $stmt->bind_param('i', $dispute['contract_id']);
            $stmt->execute();
            $stmt->close();

            if (!empty($dispute['milestone_id'])) {
                $stmt = $conn->prepare('UPDATE milestones SET status = "pending", updated_at = NOW() WHERE id = ? AND status = "disputed"');
                $stmt->bind_param('i', $dispute['milestone_id']);
                $stmt->execute();
                $stmt->close();
            } else {
                $stmt = $conn->prepare('UPDATE milestones SET status = "pending", updated_at = NOW() WHERE contract_id = ? AND status = "disputed"');
                $stmt->bind_param('i', $dispute['contract_id']);
                $stmt->execute();
                $stmt->close();
            }

            $conn->commit();
        } catch (Exception $e) {
            $conn->rollback();
            json_response(['success' => false, 'message' => 'Failed to dismiss dispute.'], 500);
        }

        require_once __DIR__ . '/../shared/notification_helper.php';
        $parties = [$dispute['raised_by'], $dispute['against']];
        foreach ($parties as $partyId) {
            if ((int)$partyId !== $userId) {
                notifyDisputeDismissed((int)$partyId, $adminName, $contractTitle, (int)$dispute['contract_id'], $resolution, (int) ($dispute['milestone_id'] ?? 0));
            }
        }

        json_response(['success' => true, 'message' => 'Dispute dismissed successfully.']);
        break;

    // ── UPLOAD EVIDENCE (additional files after dispute created) ───────
    case 'upload_evidence':
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            json_response(['success' => false, 'message' => 'POST request required.'], 405);
        }
        if (!verify_csrf_token()) {
            json_response(['success' => false, 'message' => 'Invalid CSRF token.'], 403);
        }

        $disputeId = sanitize_int($_POST['dispute_id'] ?? 0);
        if ($disputeId <= 0) {
            json_response(['success' => false, 'message' => 'Invalid dispute ID.'], 400);
        }

        // Verify dispute exists and user is party to it
        $stmt = $conn->prepare('SELECT id, raised_by, against, status FROM dispute_tickets WHERE id = ?');
        $stmt->bind_param('i', $disputeId);
        $stmt->execute();
        $dispute = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        if (!$dispute) {
            json_response(['success' => false, 'message' => 'Dispute not found.'], 404);
        }
        if ($userRole !== 'admin' && (int) $dispute['raised_by'] !== $userId && (int) $dispute['against'] !== $userId) {
            json_response(['success' => false, 'message' => 'Access denied.'], 403);
        }
        if (in_array($dispute['status'], ['resolved', 'dismissed'])) {
            json_response(['success' => false, 'message' => 'Cannot add evidence to a closed dispute.'], 400);
        }

        if (empty($_FILES['evidence_files']['name'][0])) {
            json_response(['success' => false, 'message' => 'No files uploaded.'], 400);
        }

        // Get existing evidence files
        $stmt = $conn->prepare('SELECT evidence_files FROM dispute_tickets WHERE id = ?');
        $stmt->bind_param('i', $disputeId);
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        $existingFiles = json_decode($row['evidence_files'] ?? '[]', true) ?: [];

        // Upload new files
        $newFiles = [];
        $fileCount = min(count($_FILES['evidence_files']['name']), 5 - count($existingFiles));
        for ($i = 0; $i < $fileCount; $i++) {
            $fileData = [
                'name' => $_FILES['evidence_files']['name'][$i],
                'type' => $_FILES['evidence_files']['type'][$i],
                'tmp_name' => $_FILES['evidence_files']['tmp_name'][$i],
                'error' => $_FILES['evidence_files']['error'][$i],
                'size' => $_FILES['evidence_files']['size'][$i],
            ];
            $uploaded = handle_evidence_upload($fileData);
            if ($uploaded) {
                $uploaded['uploaded_by'] = $userId;
                $uploaded['uploaded_at'] = date('Y-m-d H:i:s');
                $newFiles[] = $uploaded;
            }
        }

        if (empty($newFiles)) {
            json_response(['success' => false, 'message' => 'No valid files were uploaded. Check file types and size limits.'], 400);
        }

        $allFiles = array_merge($existingFiles, $newFiles);
        $evidenceJson = json_encode($allFiles);

        $stmt = $conn->prepare('UPDATE dispute_tickets SET evidence_files = ?, updated_at = NOW() WHERE id = ?');
        $stmt->bind_param('si', $evidenceJson, $disputeId);
        $stmt->execute();
        $stmt->close();

        json_response([
            'success' => true,
            'message' => count($newFiles) . ' file(s) uploaded successfully.',
            'files' => $newFiles,
            'total_files' => count($allFiles),
        ]);
        break;

    // ── REQUEST EVIDENCE (admin) ───────────────────────────────────────
    case 'request_evidence':
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            json_response(['success' => false, 'message' => 'POST request required.'], 405);
        }
        if (!verify_csrf_token()) {
            json_response(['success' => false, 'message' => 'Invalid CSRF token.'], 403);
        }
        if ($userRole !== 'admin') {
            json_response(['success' => false, 'message' => 'Admin access required.'], 403);
        }

        $disputeId     = sanitize_int($_POST['dispute_id'] ?? 0);
        $targetUserId  = sanitize_int($_POST['target_user_id'] ?? 0);
        $requestNote   = trim($_POST['note'] ?? '');

        if ($disputeId <= 0 || $targetUserId <= 0) {
            json_response(['success' => false, 'message' => 'Invalid parameters.'], 400);
        }
        if (empty($requestNote) || strlen($requestNote) < 10) {
            json_response(['success' => false, 'message' => 'Request note must be at least 10 characters.'], 400);
        }

        // Verify dispute exists and target is party to it
        $stmt = $conn->prepare('SELECT id, contract_id, milestone_id, raised_by, against, status FROM dispute_tickets WHERE id = ?');
        $stmt->bind_param('i', $disputeId);
        $stmt->execute();
        $dispute = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        if (!$dispute) {
            json_response(['success' => false, 'message' => 'Dispute not found.'], 404);
        }
        if ((int) $dispute['raised_by'] !== $targetUserId && (int) $dispute['against'] !== $targetUserId) {
            json_response(['success' => false, 'message' => 'Target user is not a party to this dispute.'], 400);
        }

        $stmt = $conn->prepare(
            'UPDATE dispute_tickets SET evidence_request_note = ?, evidence_request_target = ?, evidence_request_fulfilled = 0, updated_at = NOW() WHERE id = ?'
        );
        $stmt->bind_param('sii', $requestNote, $targetUserId, $disputeId);
        $stmt->execute();
        $stmt->close();

        // Notify the target user
        require_once __DIR__ . '/../shared/notification_helper.php';
        $adminStmt = $conn->prepare('SELECT name FROM users WHERE id = ?');
        $adminStmt->bind_param('i', $userId);
        $adminStmt->execute();
        $adminRow = $adminStmt->get_result()->fetch_assoc();
        $adminStmt->close();
        $adminName = $adminRow['name'] ?? 'Admin';

        $stmtJob = $conn->prepare('SELECT j.title FROM contracts c JOIN jobs j ON c.job_id = j.id WHERE c.id = ?');
        $stmtJob->bind_param('i', $dispute['contract_id']);
        $stmtJob->execute();
        $jobTitle = $stmtJob->get_result()->fetch_assoc()['title'] ?? 'Contract';
        $stmtJob->close();

        notifyEvidenceRequested($targetUserId, $adminName, $jobTitle, (int) $dispute['contract_id'], $requestNote, (int) ($dispute['milestone_id'] ?? 0));

        json_response(['success' => true, 'message' => 'Evidence request sent successfully.']);
        break;

    // ── RESPOND TO EVIDENCE REQUEST ────────────────────────────────────
    case 'respond_request':
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            json_response(['success' => false, 'message' => 'POST request required.'], 405);
        }
        if (!verify_csrf_token()) {
            json_response(['success' => false, 'message' => 'Invalid CSRF token.'], 403);
        }

        $disputeId = sanitize_int($_POST['dispute_id'] ?? 0);
        if ($disputeId <= 0) {
            json_response(['success' => false, 'message' => 'Invalid dispute ID.'], 400);
        }

        // Verify dispute exists, has open request, and user is the target
        $stmt = $conn->prepare('SELECT id, raised_by, against, evidence_request_target, evidence_request_fulfilled, status FROM dispute_tickets WHERE id = ?');
        $stmt->bind_param('i', $disputeId);
        $stmt->execute();
        $dispute = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        if (!$dispute) {
            json_response(['success' => false, 'message' => 'Dispute not found.'], 404);
        }
        if (empty($dispute['evidence_request_target'])) {
            json_response(['success' => false, 'message' => 'No pending evidence request.'], 400);
        }
        if ((int) $dispute['evidence_request_target'] !== $userId) {
            json_response(['success' => false, 'message' => 'You are not the target of this evidence request.'], 403);
        }
        if ($dispute['evidence_request_fulfilled']) {
            json_response(['success' => false, 'message' => 'You have already responded to this request.'], 400);
        }

        if (empty($_FILES['evidence_files']['name'][0])) {
            json_response(['success' => false, 'message' => 'No files uploaded.'], 400);
        }

        // Get existing evidence files
        $stmt = $conn->prepare('SELECT evidence_files FROM dispute_tickets WHERE id = ?');
        $stmt->bind_param('i', $disputeId);
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        $existingFiles = json_decode($row['evidence_files'] ?? '[]', true) ?: [];

        // Upload new files
        $newFiles = [];
        $fileCount = min(count($_FILES['evidence_files']['name']), 5 - count($existingFiles));
        for ($i = 0; $i < $fileCount; $i++) {
            $fileData = [
                'name' => $_FILES['evidence_files']['name'][$i],
                'type' => $_FILES['evidence_files']['type'][$i],
                'tmp_name' => $_FILES['evidence_files']['tmp_name'][$i],
                'error' => $_FILES['evidence_files']['error'][$i],
                'size' => $_FILES['evidence_files']['size'][$i],
            ];
            $uploaded = handle_evidence_upload($fileData);
            if ($uploaded) {
                $uploaded['uploaded_by'] = $userId;
                $uploaded['uploaded_at'] = date('Y-m-d H:i:s');
                $uploaded['context'] = 'evidence_request_response';
                $newFiles[] = $uploaded;
            }
        }

        if (empty($newFiles)) {
            json_response(['success' => false, 'message' => 'No valid files were uploaded.'], 400);
        }

        $allFiles = array_merge($existingFiles, $newFiles);
        $evidenceJson = json_encode($allFiles);

        $conn->begin_transaction();
        try {
            $stmt = $conn->prepare(
                'UPDATE dispute_tickets SET evidence_files = ?, evidence_request_fulfilled = 1, updated_at = NOW() WHERE id = ?'
            );
            $stmt->bind_param('si', $evidenceJson, $disputeId);
            $stmt->execute();
            $stmt->close();

            $conn->commit();
        } catch (Exception $e) {
            $conn->rollback();
            json_response(['success' => false, 'message' => 'Failed to submit response.'], 500);
        }

        // Notify admins
        require_once __DIR__ . '/../shared/notification_helper.php';
        $uploaderStmt = $conn->prepare('SELECT name FROM users WHERE id = ?');
        $uploaderStmt->bind_param('i', $userId);
        $uploaderStmt->execute();
        $uploaderName = $uploaderStmt->get_result()->fetch_assoc()['name'] ?? 'User';
        $uploaderStmt->close();

        $stmtJob = $conn->prepare('SELECT j.title FROM contracts c JOIN jobs j ON c.job_id = j.id WHERE c.id = (SELECT contract_id FROM dispute_tickets WHERE id = ?)');
        $stmtJob->bind_param('i', $disputeId);
        $stmtJob->execute();
        $jobTitle = $stmtJob->get_result()->fetch_assoc()['title'] ?? 'Contract';
        $stmtJob->close();

        $adminStmt = $conn->prepare('SELECT id FROM users WHERE role = "admin"');
        $adminStmt->execute();
        $adminResult = $adminStmt->get_result();
        while ($admin = $adminResult->fetch_assoc()) {
            notifyEvidenceSubmitted((int) $admin['id'], $uploaderName, $jobTitle, $disputeId);
        }
        $adminStmt->close();

        json_response([
            'success' => true,
            'message' => 'Evidence submitted successfully.',
            'files' => $newFiles,
        ]);
        break;

    // ── DOWNLOAD EVIDENCE ──────────────────────────────────────────────
    case 'download_evidence':
        $evidenceId = sanitize_int($_GET['id'] ?? 0);
        if ($evidenceId <= 0) {
            json_response(['success' => false, 'message' => 'Invalid evidence ID.'], 400);
        }

        // Evidence files are stored directly in dispute_tickets.evidence_files JSON
        // The "id" parameter here is actually the dispute_id
        $disputeId = $evidenceId;
        $fileIndex = sanitize_int($_GET['index'] ?? 0);

        $stmt = $conn->prepare('SELECT id, raised_by, against, evidence_files FROM dispute_tickets WHERE id = ?');
        $stmt->bind_param('i', $disputeId);
        $stmt->execute();
        $dispute = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        if (!$dispute) {
            json_response(['success' => false, 'message' => 'Dispute not found.'], 404);
        }
        if ($userRole !== 'admin' && (int) $dispute['raised_by'] !== $userId && (int) $dispute['against'] !== $userId) {
            json_response(['success' => false, 'message' => 'Access denied.'], 403);
        }

        $files = json_decode($dispute['evidence_files'] ?? '[]', true) ?: [];
        if ($fileIndex < 0 || $fileIndex >= count($files)) {
            json_response(['success' => false, 'message' => 'File not found.'], 404);
        }

        $file = $files[$fileIndex];
        $storedFilename = $file['stored_filename'] ?? '';

        // Security: validate filename
        if (preg_match('/[^a-z0-9_.]/', $storedFilename) || strpos($storedFilename, '..') !== false) {
            json_response(['success' => false, 'message' => 'Invalid file.'], 400);
        }

        $filePath = __DIR__ . '/../assets/upload/disputes/' . $storedFilename;
        if (!file_exists($filePath)) {
            json_response(['success' => false, 'message' => 'File not found on server.'], 404);
        }

        $originalName = $file['original_filename'] ?? 'evidence_file';
        $mimeType = $file['mime_type'] ?: 'application/octet-stream';

        header('Content-Type: ' . $mimeType);
        header('Content-Disposition: attachment; filename="' . $originalName . '"');
        header('Content-Length: ' . filesize($filePath));
        header('Cache-Control: no-cache, must-revalidate');
        readfile($filePath);
        exit;

    default:
        json_response(['success' => false, 'message' => 'Invalid action.'], 400);
        break;
}
