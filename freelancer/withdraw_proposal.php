<?php
require_once __DIR__ . '/../auth/auth.php';
require_role('freelancer');
require_once __DIR__ . '/../config/db.php';

$userId = $_SESSION['user_id'];

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect('/jobhub/freelancer/proposals.php');
}

if (!verify_csrf_token()) {
    set_flash('error', 'Invalid security token. Please try again.');
    redirect('/jobhub/freelancer/proposals.php');
}

$proposalId = sanitize_int($_POST['proposal_id'] ?? 0);

if ($proposalId <= 0) {
    set_flash('error', 'Invalid proposal reference.');
    redirect('/jobhub/freelancer/proposals.php');
}

$stmt = $conn->prepare('SELECT id, freelancer_id, status FROM proposals WHERE id = ?');
$stmt->bind_param('i', $proposalId);
$stmt->execute();
$proposal = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$proposal || $proposal['freelancer_id'] != $userId) {
    set_flash('error', 'Proposal not found or access denied.');
    redirect('/jobhub/freelancer/proposals.php');
}

if ($proposal['status'] !== 'pending') {
    set_flash('error', 'Only pending proposals can be withdrawn.');
    redirect('/jobhub/freelancer/proposal_detail.php?id=' . $proposalId);
}

$upd = $conn->prepare("UPDATE proposals SET status = 'withdrawn' WHERE id = ? AND freelancer_id = ? AND status = 'pending'");
$upd->bind_param('ii', $proposalId, $userId);
$upd->execute();

if ($upd->affected_rows > 0) {
    $upd->close();
    set_flash('success', 'Your proposal has been withdrawn.');
} else {
    $upd->close();
    set_flash('error', 'Unable to withdraw proposal. It may have already been processed.');
}

redirect('/jobhub/freelancer/proposals.php');
