<?php
/**
 * Job Search API - AJAX endpoint for live search/filtering
 * Returns JSON with jobs array and pagination info
 */
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../auth/auth.php';
require_role('freelancer');

header('Content-Type: application/json');

$userId = $_SESSION['user_id'];

// ── Sanitize parameters ───────────────────────────────────────────────────
$search       = trim($_GET['q'] ?? '');
$budgetMin    = max(0, floatval($_GET['budget_min'] ?? 0));
$budgetMax    = max(0, floatval($_GET['budget_max'] ?? 0));
$statusFilter = $_GET['status'] ?? 'open';
$page         = max(1, intval($_GET['page'] ?? 1));
$perPage      = 12;

$allowedStatuses = ['open', 'in_progress', 'completed', 'disputed', 'cancelled', 'all'];
if (!in_array($statusFilter, $allowedStatuses)) $statusFilter = 'open';

$skillIds = [];
if (!empty($_GET['skills'])) {
    $raw = is_array($_GET['skills']) ? $_GET['skills'] : explode(',', $_GET['skills']);
    $skillIds = array_filter(array_map('intval', $raw));
}

// ── Build WHERE conditions ────────────────────────────────────────────────
$where  = [];
$params = [];
$types  = '';

if ($statusFilter !== 'all') {
    $where[]  = 'j.status = ?';
    $params[] = $statusFilter;
    $types  .= 's';
} else {
    $where[] = "j.status != 'cancelled'";
}

if ($budgetMin > 0) {
    $where[]  = 'j.budget >= ?';
    $params[] = $budgetMin;
    $types  .= 'd';
}

if ($budgetMax > 0) {
    $where[]  = 'j.budget <= ?';
    $params[] = $budgetMax;
    $types  .= 'd';
}

if ($search !== '') {
    $safeSearch = preg_replace('/[^\w\s\-\+]/', '', $search);
    $safeSearch = trim($safeSearch);
    if ($safeSearch !== '') {
        $searchTerms = implode(' ', array_map(function($t) { return '+' . $t; }, explode(' ', $safeSearch)));
        $where[]  = "MATCH(j.title, j.description) AGAINST(? IN BOOLEAN MODE)";
        $params[] = $searchTerms;
        $types  .= 's';
    }
}

if (!empty($skillIds)) {
    $placeholders = implode(',', array_fill(0, count($skillIds), '?'));
    $where[]      = "j.id IN (SELECT js.job_id FROM job_skills js WHERE js.skill_id IN ($placeholders) GROUP BY js.job_id HAVING COUNT(DISTINCT js.skill_id) = " . count($skillIds) . ")";
    $params       = array_merge($params, $skillIds);
    $types       .= str_repeat('i', count($skillIds));
}

$whereSQL = implode(' AND ', $where);

// ── Count total ───────────────────────────────────────────────────────────
$countSql = "SELECT COUNT(DISTINCT j.id) AS total FROM jobs j WHERE $whereSQL";
$countStmt = $conn->prepare($countSql);
if ($types !== '') {
    $countStmt->bind_param($types, ...$params);
}
$countStmt->execute();
$totalFiltered = $countStmt->get_result()->fetch_assoc()['total'];
$countStmt->close();

$pagination = paginate($totalFiltered, $perPage, $page);

// ── Fetch jobs ────────────────────────────────────────────────────────────
$querySql = "SELECT j.id, j.title, j.description, j.budget, j.status, j.created_at,
             u.name AS client_name,
             (SELECT COUNT(*) FROM proposals WHERE job_id = j.id) AS proposal_count
             FROM jobs j
              JOIN clients c ON j.client_id = c.client_id
             JOIN users u ON c.client_id = u.id
             WHERE $whereSQL
             ORDER BY j.created_at DESC
             LIMIT ? OFFSET ?";

$finalTypes  = $types . 'ii';
$finalParams = array_merge($params, [$pagination['per_page'], $pagination['offset']]);

$stmt = $conn->prepare($querySql);
$stmt->bind_param($finalTypes, ...$finalParams);
$stmt->execute();
$jobsResult = $stmt->get_result();
$stmt->close();

$jobIds = [];
$jobsData = [];
while ($row = $jobsResult->fetch_assoc()) {
    $jobIds[] = $row['id'];
    $row['skills'] = [];
    $row['has_proposed'] = false;
    $jobsData[$row['id']] = $row;
}

// ── Enrich with skills and proposal status ────────────────────────────────
if (!empty($jobIds)) {
    $jidPlaceholders = implode(',', array_fill(0, count($jobIds), '?'));
    $jidTypes = str_repeat('i', count($jobIds));

    $skillStmt = $conn->prepare(
        "SELECT js.job_id, s.skill_name, s.category
         FROM job_skills js
         JOIN skills s ON js.skill_id = s.id
         WHERE js.job_id IN ($jidPlaceholders)
         ORDER BY s.skill_name"
    );
    $skillStmt->bind_param($jidTypes, ...$jobIds);
    $skillStmt->execute();
    $skillRes = $skillStmt->get_result();
    while ($sr = $skillRes->fetch_assoc()) {
        if (isset($jobsData[$sr['job_id']])) {
            $jobsData[$sr['job_id']]['skills'][] = [
                'name'     => $sr['skill_name'],
                'category' => $sr['category'],
            ];
        }
    }
    $skillStmt->close();

    $propStmt = $conn->prepare(
        "SELECT job_id FROM proposals WHERE job_id IN ($jidPlaceholders) AND freelancer_id = ?"
    );
    $propParams = array_merge($jobIds, [$userId]);
    $propStmt->bind_param($jidTypes . 'i', ...$propParams);
    $propStmt->execute();
    $propRes = $propStmt->get_result();
    while ($pr = $propRes->fetch_assoc()) {
        if (isset($jobsData[$pr['job_id']])) {
            $jobsData[$pr['job_id']]['has_proposed'] = true;
        }
    }
    $propStmt->close();
}

$conn->close();

// ── Format output ─────────────────────────────────────────────────────────
$output = [
    'jobs' => array_values($jobsData),
    'pagination' => $pagination,
    'total' => $totalFiltered,
    'filters' => [
        'search'    => $search,
        'budget_min' => $budgetMin,
        'budget_max' => $budgetMax,
        'status'    => $statusFilter,
        'skills'    => array_values($skillIds),
    ],
];

json_response($output);
