<?php
/**
 * Contracts API Endpoint
 * GET: List contracts with filters (JSON response)
 * GET: Contract detail with milestones by ?id=X
 * Used by chat module, dashboards, and other AJAX callers.
 */

if (session_status() === PHP_SESSION_NONE) session_start();
require_once __DIR__ . '/../config/db.php';

header('Content-Type: application/json');

$userId = $_SESSION['user_id'] ?? null;
$userRole = $_SESSION['user_role'] ?? null;

if (!$userId) {
    json_response(['error' => 'Unauthorized'], 401);
}

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    json_response(['error' => 'Method not allowed'], 405);
}

if (isset($_GET['id']) && is_numeric($_GET['id'])) {
    $contractId = (int) $_GET['id'];

    if ($userRole === 'freelancer') {
        $stmt = $conn->prepare('
            SELECT c.*, j.title AS job_title, j.description AS job_description,
                   uc.name AS client_name, uc.profile_image AS client_image, uc.email AS client_email
            FROM contracts c
            JOIN jobs j ON c.job_id = j.id
            JOIN users uc ON c.client_id = uc.id
            WHERE c.id = ? AND c.freelancer_id = ?
        ');
        $stmt->bind_param('ii', $contractId, $userId);
    } elseif ($userRole === 'client') {
        $stmt = $conn->prepare('
            SELECT c.*, j.title AS job_title, j.description AS job_description,
                   uf.name AS freelancer_name, uf.profile_image AS freelancer_image, uf.email AS freelancer_email
            FROM contracts c
            JOIN jobs j ON c.job_id = j.id
            JOIN freelancers f ON c.freelancer_id = f.id
            JOIN users uf ON f.user_id = uf.id
            WHERE c.id = ? AND c.client_id = ?
        ');
        $stmt->bind_param('ii', $contractId, $userId);
    } else {
        json_response(['error' => 'Forbidden'], 403);
    }

    $stmt->execute();
    $contract = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if (!$contract) {
        json_response(['error' => 'Contract not found'], 404);
    }

    $mStmt = $conn->prepare('SELECT * FROM milestones WHERE contract_id = ? ORDER BY created_at ASC');
    $mStmt->bind_param('i', $contractId);
    $mStmt->execute();
    $mResult = $mStmt->get_result();
    $mStmt->close();

    $milestones = [];
    $totalMilestones = 0;
    while ($m = $mResult->fetch_assoc()) {
        $totalMilestones += (float) $m['amount'];
        $milestones[] = $m;
    }

    $conn->close();

    json_response([
        'contract' => $contract,
        'milestones' => $milestones,
        'total_milestones_value' => $totalMilestones,
        'remaining_budget' => (float) $contract['total_budget'] - $totalMilestones,
    ]);
}

// ── List Contracts ────────────────────────────────────────────────────

$status = $_GET['status'] ?? 'all';
$page = max(1, intval($_GET['page'] ?? 1));
$perPage = min(50, max(1, intval($_GET['per_page'] ?? 10)));

$allowedStatuses = ['all', 'active', 'completed', 'disputed', 'terminated'];
if (!in_array($status, $allowedStatuses)) $status = 'all';

if ($userRole === 'freelancer') {
    $where = 'WHERE c.freelancer_id = ?';
    $params = [$userId];
    $types = 'i';
} elseif ($userRole === 'client') {
    $where = 'WHERE c.client_id = ?';
    $params = [$userId];
    $types = 'i';
} else {
    json_response(['error' => 'Forbidden'], 403);
}

if ($status !== 'all') {
    $where .= " AND c.status = ?";
    $params[] = $status;
    $types .= 's';
}

$countStmt = $conn->prepare("SELECT COUNT(*) AS total FROM contracts c $where");
$countStmt->bind_param($types, ...$params);
$countStmt->execute();
$total = (int) $countStmt->get_result()->fetch_assoc()['total'];
$countStmt->close();

$totalPages = max(1, ceil($total / $perPage));
$page = min($page, $totalPages);
$offset = ($page - 1) * $perPage;

if ($userRole === 'freelancer') {
    $query = "SELECT c.id, c.contract_type, c.total_budget, c.status, c.created_at,
              j.title AS job_title,
              u.name AS client_name, u.profile_image AS client_image
              FROM contracts c
              JOIN jobs j ON c.job_id = j.id
              JOIN users u ON c.client_id = u.id
              $where
              ORDER BY c.created_at DESC
              LIMIT ? OFFSET ?";
} else {
    $query = "SELECT c.id, c.contract_type, c.total_budget, c.status, c.created_at,
              j.title AS job_title,
              u.name AS freelancer_name, u.profile_image AS freelancer_image
              FROM contracts c
              JOIN jobs j ON c.job_id = j.id
              JOIN freelancers f ON c.freelancer_id = f.id
              JOIN users u ON f.user_id = u.id
              $where
              ORDER BY c.created_at DESC
              LIMIT ? OFFSET ?";
}

$finalTypes = $types . 'ii';
$finalParams = array_merge($params, [$perPage, $offset]);

$stmt = $conn->prepare($query);
$stmt->bind_param($finalTypes, ...$finalParams);
$stmt->execute();
$result = $stmt->get_result();
$stmt->close();

$contracts = [];
while ($row = $result->fetch_assoc()) {
    $contracts[] = $row;
}

$conn->close();

json_response([
    'contracts' => $contracts,
    'pagination' => [
        'total'       => $total,
        'per_page'    => $perPage,
        'current_page'=> $page,
        'total_pages' => $totalPages,
        'has_prev'    => $page > 1,
        'has_next'    => $page < $totalPages,
    ],
]);
