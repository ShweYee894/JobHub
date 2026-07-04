<?php
/**
 * Dispute Tickets API
 * Handles dispute creation, listing, viewing, and resolution.
 *
 * POST ?action=create           - Create a new dispute ticket
 * GET  ?action=list             - List disputes (admin: all, user: own)
 * GET  ?action=detail&id=X      - Get dispute details
 * POST ?action=update_status    - Admin: update dispute status
 * POST ?action=resolve          - Admin: resolve dispute with resolution
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

        $againstId = $isClient ? (int) $contract['freelancer_id'] : (int) $contract['client_id'];
        $milestoneParam = $milestoneId > 0 ? $milestoneId : null;

        $conn->begin_transaction();
        try {
            $stmt = $conn->prepare(
                'INSERT INTO dispute_tickets (contract_id, milestone_id, raised_by, against, reason, description, status, created_at)
                 VALUES (?, ?, ?, ?, ?, ?, "open", NOW())'
            );
            $stmt->bind_param('iiisss', $contractId, $milestoneParam, $userId, $againstId, $reason, $description);
            $stmt->execute();
            $disputeId = $stmt->insert_id;
            $stmt->close();

            // Update contract dispute status
            $stmt = $conn->prepare('UPDATE contracts SET dispute_status = "open", updated_at = NOW() WHERE id = ?');
            $stmt->bind_param('i', $contractId);
            $stmt->execute();
            $stmt->close();

            $conn->commit();
            json_response([
                'success'    => true,
                'message'    => 'Dispute ticket created successfully. Our team will review it shortly.',
                'dispute_id' => $disputeId,
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
        $contractDisputeStatus = $newStatus === 'resolved' ? 'resolved' : ($newStatus === 'escalated' ? 'escalated' : 'open');
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
        $actionType = $_POST['resolution_action'] ?? '';

        if ($disputeId <= 0) {
            json_response(['success' => false, 'message' => 'Invalid dispute ID.'], 400);
        }
        if (empty($resolution) || strlen($resolution) < 10) {
            json_response(['success' => false, 'message' => 'Resolution must be at least 10 characters.'], 400);
        }

        $stmt = $conn->prepare('SELECT id, status, contract_id, milestone_id FROM dispute_tickets WHERE id = ?');
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

        $conn->begin_transaction();
        try {
            $stmt = $conn->prepare(
                'UPDATE dispute_tickets SET status = "resolved", resolution = ?, resolved_by = ?, updated_at = NOW() WHERE id = ?'
            );
            $stmt->bind_param('sii', $resolution, $userId, $disputeId);
            $stmt->execute();
            $stmt->close();

            $stmt = $conn->prepare('UPDATE contracts SET dispute_status = "resolved", updated_at = NOW() WHERE id = ?');
            $stmt->bind_param('i', $dispute['contract_id']);
            $stmt->execute();
            $stmt->close();

            $conn->commit();
            json_response(['success' => true, 'message' => 'Dispute resolved successfully.']);
        } catch (Exception $e) {
            $conn->rollback();
            json_response(['success' => false, 'message' => 'Failed to resolve dispute.'], 500);
        }
        break;

    default:
        json_response(['success' => false, 'message' => 'Invalid action.'], 400);
        break;
}
