<?php
require_once __DIR__ . '/../config/db.php';
header('Content-Type: application/json');

$q       = trim($_GET['q'] ?? '');
$limit   = min(max(intval($_GET['limit'] ?? 5), 1), 20);

if (mb_strlen($q) < 2) {
    echo json_encode(['success' => true, 'results' => []]);
    exit;
}

$safeQ   = '%' . $conn->real_escape_string($q) . '%';
$where   = "COALESCE(j.is_archived, 0) = 0 AND j.status = 'open'";
$params  = [$safeQ, $safeQ, $safeQ];
$types   = 'sss';

$stmt = $conn->prepare("
    SELECT j.id, j.title, j.budget, j.job_type,
           u.name AS client_name
    FROM jobs j
    JOIN clients c ON j.client_id = c.client_id
    JOIN users u   ON c.client_id = u.id
    WHERE $where
      AND (
        LOWER(j.title) LIKE LOWER(?)
        OR LOWER(j.description) LIKE LOWER(?)
        OR j.id IN (
          SELECT js.job_id FROM job_skills js
          INNER JOIN skills s ON js.skill_id = s.id
          WHERE LOWER(s.skill_name) LIKE LOWER(?)
        )
      )
    ORDER BY COALESCE(j.is_featured, 0) DESC, j.created_at DESC
    LIMIT ?
");

$params[] = $limit;
$stmt->bind_param($types . 'i', ...$params);
$stmt->execute();
$rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

$results = array_map(function ($r) {
    $subtitle = '$' . number_format((float)$r['budget'], 0);
    if ($r['job_type']) $subtitle .= ' · ' . ucfirst($r['job_type']);
    return [
        'id'       => (int)$r['id'],
        'title'    => $r['title'],
        'subtitle' => $subtitle . ' · ' . $r['client_name'],
        'image'    => null,
        'url'      => '/jobhub/freelancer/job_detail.php?id=' . $r['id'],
    ];
}, $rows);

echo json_encode(['success' => true, 'results' => $results]);
