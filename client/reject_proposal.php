<?php
require_once __DIR__ . '/../auth/auth.php';
require_role('client');
require_once __DIR__ . '/../config/db.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect('/jobhub/client/proposals.php');
}

if (!verify_csrf_token()) {
    set_flash('error', 'Invalid security token. Please try again.');
    redirect('/jobhub/client/proposals.php');
}

$proposalId = sanitize_int($_POST['proposal_id'] ?? 0);
$userId = $_SESSION['user_id'];

if ($proposalId <= 0) {
    set_flash('error', 'Invalid proposal reference.');
    redirect('/jobhub/client/proposals.php');
}

$stmt = $conn->prepare('
    SELECT p.id, p.status
    FROM proposals p
    JOIN jobs j ON p.job_id = j.id
    WHERE p.id = ? AND j.client_id = ?
');
$stmt->bind_param('ii', $proposalId, $userId);
$stmt->execute();
$proposal = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$proposal) {
    set_flash('error', 'Proposal not found or access denied.');
    redirect('/jobhub/client/proposals.php');
}

if ($proposal['status'] !== 'pending') {
    set_flash('error', 'Only pending proposals can be rejected.');
    redirect('/jobhub/client/proposal_detail.php?id=' . $proposalId);
}

$upd = $conn->prepare("UPDATE proposals SET status = 'rejected' WHERE id = ? AND status = 'pending'");
$upd->bind_param('i', $proposalId);
$upd->execute();
$upd->close();

if ($upd->affected_rows > 0) {
    set_flash('success', 'Proposal has been rejected.');
} else {
    set_flash('error', 'Unable to reject proposal. It may have already been processed.');
}

redirect('/jobhub/client/proposal_detail.php?id=' . $proposalId);
