<?php
/**
 * One-time fix: Reset contracts and milestones for already-resolved/dismissed disputes
 * that still have contract.status = 'disputed'.
 *
 * DELETE this file after running it.
 */
require_once __DIR__ . '/../auth/auth.php';
require_role('admin');
require_once __DIR__ . '/../config/db.php';

$fixed = 0;

$stmt = $conn->prepare(
    'SELECT d.id, d.contract_id, d.milestone_id, d.status AS dispute_status
     FROM dispute_tickets d
     JOIN contracts c ON d.contract_id = c.id
     WHERE d.status IN ("resolved", "dismissed") AND c.status = "disputed"'
);
$stmt->execute();
$stale = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

if (empty($stale)) {
    set_flash('success', 'No stale disputes found. All resolved disputes already have correct contract status.');
    redirect('disputes.php');
}

$conn->begin_transaction();
try {
    foreach ($stale as $row) {
        $contractId = (int) $row['contract_id'];
        $milestoneId = !empty($row['milestone_id']) ? (int) $row['milestone_id'] : null;

        // Reset contract status to active
        $u = $conn->prepare('UPDATE contracts SET status = "active", dispute_status = "resolved", updated_at = NOW() WHERE id = ?');
        $u->bind_param('i', $contractId);
        $u->execute();
        $u->close();

        // Reset disputed milestones
        if ($milestoneId) {
            $m = $conn->prepare('UPDATE milestones SET status = "pending", updated_at = NOW() WHERE id = ? AND status = "disputed"');
            $m->bind_param('i', $milestoneId);
            $m->execute();
            $m->close();
        } else {
            $m = $conn->prepare('UPDATE milestones SET status = "pending", updated_at = NOW() WHERE contract_id = ? AND status = "disputed"');
            $m->bind_param('i', $contractId);
            $m->execute();
            $m->close();
        }

        $fixed++;
    }
    $conn->commit();
    set_flash('success', "Fixed {$fixed} resolved dispute(s). Contracts and milestones have been reset to active/pending.");
} catch (Exception $e) {
    $conn->rollback();
    set_flash('error', 'Failed to fix disputes: ' . $e->getMessage());
}

redirect('disputes.php');
