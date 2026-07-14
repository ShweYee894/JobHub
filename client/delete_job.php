<?php
session_start();
require_once '../config/db.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: my_jobs.php');
    exit();
}

if (!verify_csrf_token()) {
    set_flash('error', 'Invalid security token. Please try again.');
    header('Location: my_jobs.php');
    exit();
}

if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'client') {
    header('Location: ../auth/login.php');
    exit();
}

$clientId = intval($_SESSION['user_id']);
$jobId = intval($_POST['job_id'] ?? 0);

if ($jobId <= 0) {
    set_flash('error', 'Invalid job ID.');
    header('Location: my_jobs.php');
    exit();
}

$check = $conn->prepare('SELECT id, status FROM jobs WHERE id = ? AND client_id = ?');
$check->bind_param('ii', $jobId, $clientId);
$check->execute();
$job = $check->get_result()->fetch_assoc();
$check->close();

if (!$job) {
    set_flash('error', 'Job not found or access denied.');
    header('Location: my_jobs.php');
    exit();
}

$hasProposals = false;
$pc = $conn->prepare('SELECT COUNT(*) AS cnt FROM proposals WHERE job_id = ?');
$pc->bind_param('i', $jobId);
$pc->execute();
$proposalCount = $pc->get_result()->fetch_assoc()['cnt'];
$pc->close();

$hasContract = false;
$cc = $conn->prepare("SELECT COUNT(*) AS cnt FROM contracts WHERE job_id = ? AND status = 'active'");
$cc->bind_param('i', $jobId);
$cc->execute();
$contractCount = $cc->get_result()->fetch_assoc()['cnt'];
$cc->close();

$conn->begin_transaction();
try {
    if ($proposalCount > 0 || $contractCount > 0) {
        $upd = $conn->prepare("UPDATE jobs SET status = 'cancelled', updated_at = NOW() WHERE id = ?");
        $upd->bind_param('i', $jobId);
        $upd->execute();
        $upd->close();
        set_flash('success', 'Job has been cancelled. Active proposals/contracts are preserved.');
    } else {
        $s1 = $conn->prepare('DELETE FROM job_skills WHERE job_id = ?');
        $s1->bind_param('i', $jobId);
        $s1->execute();
        $s1->close();

        $s2 = $conn->prepare('DELETE FROM proposals WHERE job_id = ?');
        $s2->bind_param('i', $jobId);
        $s2->execute();
        $s2->close();

        $s3 = $conn->prepare('DELETE FROM jobs WHERE id = ?');
        $s3->bind_param('i', $jobId);
        $s3->execute();
        $s3->close();

        set_flash('success', 'Job has been permanently deleted.');
    }
    $conn->commit();
} catch (Exception $e) {
    $conn->rollback();
    set_flash('error', 'Failed to delete job. Please try again.');
}

header('Location: my_jobs.php');
exit();
