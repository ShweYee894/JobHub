<?php
session_start();
require_once '../config/db.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: post_job.php');
    exit();
}

if (!verify_csrf_token()) {
    set_flash('error', 'Invalid security token. Please try again.');
    header('Location: post_job.php');
    exit();
}

if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'client') {
    header('Location: ../auth/login.php');
    exit();
}

$clientId = intval($_SESSION['user_id']);
$title = sanitize_string(trim($_POST['title'] ?? ''));
$description = trim($_POST['description'] ?? '');
$budget = sanitize_float($_POST['budget'] ?? 0);
$skills = $_POST['skills'] ?? [];
$jobId = intval($_POST['job_id'] ?? 0);

// Additional fields from actual schema
$jobType = sanitize_string($_POST['job_type'] ?? 'fixed');
$experienceLevel = sanitize_string($_POST['experience_level'] ?? 'intermediate');
$projectDuration = sanitize_string($_POST['project_duration'] ?? '');
$category = sanitize_string($_POST['category'] ?? '');
$deadline = sanitize_string($_POST['deadline'] ?? '');
$maxFreelancers = intval($_POST['max_freelancers'] ?? 1);

// Validate job type
if (!in_array($jobType, ['hourly', 'fixed'])) $jobType = 'fixed';
if (!in_array($experienceLevel, ['entry', 'intermediate', 'expert'])) $experienceLevel = 'intermediate';
if ($maxFreelancers < 1) $maxFreelancers = 1;

$errors = [];
if (strlen($title) < 10) $errors[] = 'Title must be at least 10 characters long.';
if (strlen($title) > 255) $errors[] = 'Title must not exceed 255 characters.';
if (strlen($description) < 50) $errors[] = 'Description must be at least 50 characters long.';

// For hourly jobs, compute budget from rate * hours
if ($jobType === 'hourly') {
    $hourlyRate = sanitize_float($_POST['hourly_rate'] ?? 0);
    $estimatedHours = intval($_POST['estimated_hours'] ?? 0);
    if ($hourlyRate <= 0) $errors[] = 'Hourly rate must be greater than 0.';
    if ($estimatedHours <= 0) $errors[] = 'Estimated hours must be greater than 0.';
    $budget = $hourlyRate * $estimatedHours;
}

if ($jobType === 'fixed' && $budget <= 0) $errors[] = 'Budget must be a valid amount greater than 0.';
if ($jobType === 'hourly' && $budget <= 0) $errors[] = 'Total budget (rate × hours) must be greater than 0.';
if (empty($skills) || !is_array($skills)) $errors[] = 'You must select at least one skill requirement.';

$skillIds = [];
if (!empty($skills)) {
    foreach ($skills as $sid) {
        $sidInt = intval($sid);
        if ($sidInt > 0) $skillIds[] = $sidInt;
    }
    if (empty($skillIds)) $errors[] = 'You must select at least one valid skill.';
}

if (!empty($errors)) {
    $_SESSION['job_errors'] = $errors;
    $_SESSION['job_form'] = $_POST;
    if ($jobId > 0) {
        header('Location: edit_job.php?id=' . $jobId);
    } else {
        header('Location: post_job.php');
    }
    exit();
}

function generate_embedding_vector(string $title, string $description, array $skillIds): string {
    $allText = strtolower($title . ' ' . $description);
    $allText = preg_replace('/[^a-z0-9\s]/', ' ', $allText);
    $words = array_values(array_filter(explode(' ', $allText), function($w) { return strlen($w) > 2; }));
    $words = array_unique($words);
    $titleText = strtolower($title);
    $titleText = preg_replace('/[^a-z0-9\s]/', ' ', $titleText);
    $titleWords = array_values(array_filter(explode(' ', $titleText), function($w) { return strlen($w) > 2; }));
    $titleWords = array_unique($titleWords);
    $embedding = [
        'keywords' => array_values(array_slice($words, 0, 50)),
        'skill_ids' => $skillIds,
        'word_count' => count($words),
        'title_words' => array_values(array_slice($titleWords, 0, 20))
    ];
    return json_encode($embedding);
}

$conn->begin_transaction();
try {
    $embeddingJson = generate_embedding_vector($title, $description, $skillIds);
    $deadlineVal = !empty($deadline) ? $deadline : null;

    if ($jobId > 0) {
        $check = $conn->prepare('SELECT id FROM jobs WHERE id = ? AND client_id = ?');
        $check->bind_param('ii', $jobId, $clientId);
        $check->execute();
        if ($check->get_result()->num_rows === 0) {
            $check->close();
            throw new Exception('Job not found or access denied.');
        }
        $check->close();

        $stmt = $conn->prepare("UPDATE jobs SET title = ?, description = ?, budget = ?, job_type = ?, experience_level = ?, project_duration = ?, category = ?, deadline = ?, max_freelancers = ?, embedding_vector = ?, updated_at = NOW() WHERE id = ? AND client_id = ?");
        $stmt->bind_param('ssdsssssisii', $title, $description, $budget, $jobType, $experienceLevel, $projectDuration, $category, $deadlineVal, $maxFreelancers, $embeddingJson, $jobId, $clientId);
        $stmt->execute();
        $stmt->close();

        $del = $conn->prepare('DELETE FROM job_skills WHERE job_id = ?');
        $del->bind_param('i', $jobId);
        $del->execute();
        $del->close();
    } else {
        $stmt = $conn->prepare("INSERT INTO jobs (client_id, title, description, budget, job_type, experience_level, project_duration, category, deadline, max_freelancers, status, embedding_vector, created_at, updated_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'open', ?, NOW(), NOW())");
        $stmt->bind_param('issdsssssis', $clientId, $title, $description, $budget, $jobType, $experienceLevel, $projectDuration, $category, $deadlineVal, $maxFreelancers, $embeddingJson);
        $stmt->execute();
        $jobId = $conn->insert_id;
        $stmt->close();
    }

    if (!empty($skillIds)) {
        $placeholders = implode(',', array_fill(0, count($skillIds), '?'));
        $valStmt = $conn->prepare("SELECT id FROM skills WHERE id IN ($placeholders)");
        $valStmt->bind_param(str_repeat('i', count($skillIds)), ...$skillIds);
        $valStmt->execute();
        $validResult = $valStmt->get_result();
        $validIds = [];
        while ($row = $validResult->fetch_assoc()) {
            $validIds[] = (int) $row['id'];
        }
        $valStmt->close();
        $skillIds = array_intersect($skillIds, $validIds);
        $skillIds = array_values($skillIds);

        if (!empty($skillIds)) {
            $skillStmt = $conn->prepare('INSERT INTO job_skills (job_id, skill_id) VALUES (?, ?)');
            foreach ($skillIds as $sidInt) {
                $skillStmt->bind_param('ii', $jobId, $sidInt);
                $skillStmt->execute();
            }
            $skillStmt->close();
        }
    }

    $conn->commit();

    if (isset($_POST['job_id']) && intval($_POST['job_id']) > 0) {
        set_flash('success', 'Job updated successfully!');
    } else {
        set_flash('success', 'Your job post is now active on the marketplace!');
    }
    header('Location: my_jobs.php');
    exit();
} catch (Exception $e) {
    $conn->rollback();
    set_flash('error', 'Database error: ' . $e->getMessage());
    if ($jobId > 0 && isset($_POST['job_id']) && intval($_POST['job_id']) > 0) {
        header('Location: edit_job.php?id=' . intval($_POST['job_id']));
    } else {
        header('Location: post_job.php');
    }
    exit();
}
