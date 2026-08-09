<?php
/**
 * Toggle Saved Job (AJAX endpoint)
 * POST with job_id - saves or unsaves a job for the logged-in freelancer.
 */
session_start();
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../auth/auth.php';

header('Content-Type: application/json');

if (!is_logged_in() || ($_SESSION['user_role'] ?? '') !== 'freelancer') {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Authentication required.']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'POST required.']);
    exit;
}

$userId = (int) $_SESSION['user_id'];
$jobId  = (int) ($_POST['job_id'] ?? 0);

if ($jobId <= 0) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Invalid job ID.']);
    exit;
}

// Ensure table exists
$conn->query("CREATE TABLE IF NOT EXISTS saved_jobs (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    freelancer_id INT UNSIGNED NOT NULL,
    job_id INT UNSIGNED NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (freelancer_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (job_id) REFERENCES jobs(id) ON DELETE CASCADE,
    UNIQUE KEY unique_save (freelancer_id, job_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

// Check if already saved
$check = $conn->prepare('SELECT id FROM saved_jobs WHERE freelancer_id = ? AND job_id = ?');
$check->bind_param('ii', $userId, $jobId);
$check->execute();
$existing = $check->get_result()->fetch_assoc();
$check->close();

if ($existing) {
    // Unsave
    $del = $conn->prepare('DELETE FROM saved_jobs WHERE freelancer_id = ? AND job_id = ?');
    $del->bind_param('ii', $userId, $jobId);
    $del->execute();
    $del->close();
    echo json_encode(['success' => true, 'saved' => false, 'message' => 'Job removed from saved list.']);
} else {
    // Save
    $ins = $conn->prepare('INSERT INTO saved_jobs (freelancer_id, job_id) VALUES (?, ?)');
    $ins->bind_param('ii', $userId, $jobId);
    $ins->execute();
    $ins->close();
    echo json_encode(['success' => true, 'saved' => true, 'message' => 'Job saved successfully.']);
}

$conn->close();
