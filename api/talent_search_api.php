<?php
require_once __DIR__ . '/../config/db.php';
header('Content-Type: application/json');

$q     = trim($_GET['q'] ?? '');
$limit = min(max(intval($_GET['limit'] ?? 5), 1), 20);

if (mb_strlen($q) < 2) {
    echo json_encode(['success' => true, 'results' => []]);
    exit;
}

$safeQ  = '%' . $conn->real_escape_string($q) . '%';
$params = [$safeQ, $safeQ, $safeQ];
$types  = 'sss';

$stmt = $conn->prepare("
    SELECT u.id, u.name, u.profile_image,
           f.title AS job_title,
           f.hourly_rate, f.years_of_experience, f.availability
    FROM users u
    JOIN freelancers f ON f.user_id = u.id
    WHERE u.role = 'freelancer'
      AND u.status = 'active'
      AND (
        LOWER(u.name) LIKE LOWER(?)
        OR LOWER(f.title) LIKE LOWER(?)
        OR f.user_id IN (
          SELECT fs.freelancer_id FROM freelancer_skills fs
          INNER JOIN skills s ON fs.skill_id = s.id
          WHERE LOWER(s.skill_name) LIKE LOWER(?)
        )
      )
    ORDER BY u.name ASC
    LIMIT ?
");

$params[] = $limit;
$stmt->bind_param($types . 'i', ...$params);
$stmt->execute();
$rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

$results = array_map(function ($r) {
    $subtitle = $r['job_title'];
    if ($r['hourly_rate'] > 0) $subtitle .= ' · $' . number_format((float)$r['hourly_rate'], 0) . '/hr';
    $subtitle .= ' · ' . $r['availability'];
    return [
        'id'       => (int)$r['id'],
        'title'    => $r['name'],
        'subtitle' => $subtitle,
        'image'    => $r['profile_image'] ? '/jobhub/' . ltrim($r['profile_image'], '/') : null,
        'url'      => '/jobhub/freelancer/profile.php?user_id=' . $r['id'],
    ];
}, $rows);

echo json_encode(['success' => true, 'results' => $results]);
