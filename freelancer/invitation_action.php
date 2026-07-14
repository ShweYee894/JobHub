<?php
/**
 * Freelancer Invitation Action
 * Handles accepting or declining job invitations from clients.
 * 
 * Accept → marks notification read, redirects to submit proposal page
 * Decline → marks notification read, deletes it, redirects back to home
 */
session_start();
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../shared/notification_helper.php';

if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'freelancer') {
    header('Location: ../auth/login.php');
    exit();
}

$userId       = $_SESSION['user_id'];
$jobId        = isset($_GET['job_id']) ? (int) $_GET['job_id'] : 0;
$action       = $_GET['action'] ?? '';
$clientId     = isset($_GET['client_id']) ? (int) $_GET['client_id'] : 0;

if ($jobId <= 0 || !in_array($action, ['accept', 'decline', 'view'])) {
    set_flash('error', 'Invalid invitation parameters.');
    header('Location: home.php');
    exit();
}

// Fetch the notification by job_id and link pattern
$notifLink = "%job_id={$jobId}%";
$stmt = $conn->prepare("SELECT id, link, message, is_read FROM notifications WHERE user_id = ? AND type = 'job_invitation' AND link LIKE ? ORDER BY created_at DESC LIMIT 1");
$stmt->bind_param('is', $userId, $notifLink);
$stmt->execute();
$notification = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$notification) {
    set_flash('error', 'Invitation not found or has been removed.');
    header('Location: home.php');
    exit();
}

$invitationId = (int) $notification['id'];

// Parse client_id from link if not provided
if ($clientId <= 0 && preg_match('/client_id=(\d+)/', $notification['link'], $m)) {
    $clientId = (int) $m[1];
}

// Verify the job exists and is open
if ($jobId > 0) {
    $jStmt = $conn->prepare("SELECT id, title, client_id, status FROM jobs WHERE id = ?");
    $jStmt->bind_param('i', $jobId);
    $jStmt->execute();
    $job = $jStmt->get_result()->fetch_assoc();
    $jStmt->close();

    if (!$job) {
        set_flash('error', 'Job not found.');
        header('Location: home.php');
        exit();
    }

    if ($job['status'] !== 'open') {
        set_flash('error', 'This job is no longer open for proposals.');
        header('Location: home.php');
        exit();
    }

    // Use job's client_id if not parsed from link
    if ($clientId <= 0) {
        $clientId = (int) $job['client_id'];
    }
}

// Get freelancer_id for this user
$fStmt = $conn->prepare("SELECT id FROM freelancers WHERE user_id = ?");
$fStmt->bind_param('i', $userId);
$fStmt->execute();
$freelancerId = (int) $fStmt->get_result()->fetch_assoc()['id'];
$fStmt->close();

switch ($action) {
    case 'view':
        // Mark as read and redirect to job detail page
        if (!$notification['is_read']) {
            $uStmt = $conn->prepare("UPDATE notifications SET is_read = 1 WHERE id = ?");
            $uStmt->bind_param('i', $invitationId);
            $uStmt->execute();
            $uStmt->close();
        }
        if ($jobId > 0) {
            header("Location: job_detail.php?id={$jobId}");
        } else {
            header('Location: home.php');
        }
        exit();

    case 'accept':
        // Check if already has a proposal for this job
        $pStmt = $conn->prepare("SELECT COUNT(*) AS cnt FROM proposals WHERE freelancer_id = ? AND job_id = ?");
        $pStmt->bind_param('ii', $freelancerId, $jobId);
        $pStmt->execute();
        $hasProposal = (int) $pStmt->get_result()->fetch_assoc()['cnt'] > 0;
        $pStmt->close();

        if ($hasProposal) {
            set_flash('info', 'You have already submitted a proposal for this job.');
            header("Location: job_detail.php?id={$jobId}");
            exit();
        }

        // Mark invitation as read
        $uStmt = $conn->prepare("UPDATE notifications SET is_read = 1 WHERE id = ?");
        $uStmt->bind_param('i', $invitationId);
        $uStmt->execute();
        $uStmt->close();

        // Notify client that invitation was accepted
        $fNameStmt = $conn->prepare("SELECT name FROM users WHERE id = ?");
        $fNameStmt->bind_param('i', $userId);
        $fNameStmt->execute();
        $freelancerName = $fNameStmt->get_result()->fetch_assoc()['name'] ?? 'Freelancer';
        $fNameStmt->close();

        if ($clientId > 0 && $jobId > 0 && !empty($job['title'])) {
            notifyInvitationAccepted($clientId, $freelancerName, $job['title'], $jobId);
        }

        set_flash('success', 'Invitation accepted! You can now submit your proposal.');
        header("Location: submit_proposal.php?job_id={$jobId}");
        exit();

    case 'decline':
        // Delete the notification
        $dStmt = $conn->prepare("DELETE FROM notifications WHERE id = ?");
        $dStmt->bind_param('i', $invitationId);
        $dStmt->execute();
        $dStmt->close();

        // Notify client that invitation was declined
        $fNameStmt2 = $conn->prepare("SELECT name FROM users WHERE id = ?");
        $fNameStmt2->bind_param('i', $userId);
        $fNameStmt2->execute();
        $freelancerName2 = $fNameStmt2->get_result()->fetch_assoc()['name'] ?? 'Freelancer';
        $fNameStmt2->close();

        if ($clientId > 0 && $jobId > 0 && !empty($job['title'])) {
            notifyInvitationDeclined($clientId, $freelancerName2, $job['title'], $jobId);
        }

        set_flash('info', 'Invitation declined.');
        header('Location: home.php');
        exit();
}
