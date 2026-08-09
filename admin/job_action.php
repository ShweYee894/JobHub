<?php

/**
 * Admin Job Actions — Close, Delete (spam), Feature, Archive
 * All actions are POST-only with CSRF verification.
 */
require_once __DIR__ . '/../auth/auth.php';
require_role('admin');
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/helpers.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    set_flash('error', 'Invalid request method.');
    redirect('jobs.php');
}

if (!verify_csrf_token()) {
    set_flash('error', 'Invalid security token.');
    redirect('jobs.php');
}

$action = $_POST['action'] ?? '';
$jobId = sanitize_int($_POST['job_id'] ?? 0);

if ($jobId <= 0) {
    set_flash('error', 'Invalid job ID.');
    redirect('jobs.php');
}

// ── Ensure feature/archive columns exist ────────────────────────────
$colCheck = $conn->query("SHOW COLUMNS FROM jobs LIKE 'is_featured'");
if ($colCheck->num_rows === 0) {
    $conn->query("ALTER TABLE jobs ADD COLUMN is_featured TINYINT(1) NOT NULL DEFAULT 0 AFTER proposal_count");
}
$colCheck->close();

$colCheck2 = $conn->query("SHOW COLUMNS FROM jobs LIKE 'is_archived'");
if ($colCheck2->num_rows === 0) {
    $conn->query("ALTER TABLE jobs ADD COLUMN is_archived TINYINT(1) NOT NULL DEFAULT 0 AFTER is_featured");
}
$colCheck2->close();

// ── Verify job exists ───────────────────────────────────────────────
$check = $conn->prepare('SELECT id, title, status FROM jobs WHERE id = ?');
$check->bind_param('i', $jobId);
$check->execute();
$job = $check->get_result()->fetch_assoc();
$check->close();

if (!$job) {
    set_flash('error', 'Job not found.');
    redirect('jobs.php');
}

$jobTitle = $job['title'];

switch ($action) {

    // ── Close Job (set status to cancelled) ──────────────────────────
    case 'close':
        if ($job['status'] === 'cancelled') {
            set_flash('warning', 'Job is already closed.');
            redirect('jobs.php');
        }
        $stmt = $conn->prepare("UPDATE jobs SET status = 'cancelled', updated_at = NOW() WHERE id = ?");
        $stmt->bind_param('i', $jobId);
        $stmt->execute();
        $stmt->close();
        set_flash('success', 'Job "' . sanitize_string($jobTitle) . '" has been closed.');
        break;

    // ── Delete Spam Job (hard delete) ────────────────────────────────
    case 'delete':
        // Delete related records first (proposals, skills, embeddings)
        $delProp = $conn->prepare("DELETE FROM proposals WHERE job_id = ?");
        $delProp->bind_param('i', $jobId);
        $delProp->execute();
        $delProp->close();

        $delSkills = $conn->prepare("DELETE FROM job_skills WHERE job_id = ?");
        $delSkills->bind_param('i', $jobId);
        $delSkills->execute();
        $delSkills->close();

        $delJob = $conn->prepare("DELETE FROM jobs WHERE id = ?");
        $delJob->bind_param('i', $jobId);
        $delJob->execute();
        $delJob->close();
        set_flash('success', 'Spam job "' . sanitize_string($jobTitle) . '" has been permanently deleted.');
        break;

    // ── Feature / Unfeature Job ──────────────────────────────────────
    case 'feature':
        $current = 0;
        $fs = $conn->prepare('SELECT is_featured FROM jobs WHERE id = ?');
        $fs->bind_param('i', $jobId);
        $fs->execute();
        $fsRow = $fs->get_result()->fetch_assoc();
        $fs->close();
        $current = (int) ($fsRow['is_featured'] ?? 0);

        $newVal = $current ? 0 : 1;
        $us = $conn->prepare('UPDATE jobs SET is_featured = ?, updated_at = NOW() WHERE id = ?');
        $us->bind_param('ii', $newVal, $jobId);
        $us->execute();
        $us->close();

        $label = $newVal ? 'featured' : 'unfeatured';
        set_flash('success', 'Job "' . sanitize_string($jobTitle) . '" has been ' . $label . '.');
        break;

    // ── Archive / Unarchive Job ──────────────────────────────────────
    case 'archive':
        $current = 0;
        $as = $conn->prepare('SELECT is_archived FROM jobs WHERE id = ?');
        $as->bind_param('i', $jobId);
        $as->execute();
        $asRow = $as->get_result()->fetch_assoc();
        $as->close();
        $current = (int) ($asRow['is_archived'] ?? 0);

        $newVal = $current ? 0 : 1;
        $us = $conn->prepare('UPDATE jobs SET is_archived = ?, updated_at = NOW() WHERE id = ?');
        $us->bind_param('ii', $newVal, $jobId);
        $us->execute();
        $us->close();

        $label = $newVal ? 'archived' : 'unarchived';
        set_flash('success', 'Job "' . sanitize_string($jobTitle) . '" has been ' . $label . '.');
        break;

    default:
        set_flash('error', 'Unknown action.');
        break;
}

$conn->close();
redirect('jobs.php');
