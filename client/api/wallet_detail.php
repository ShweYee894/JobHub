<?php
/**
 * API: Wallet Transaction Detail
 * Returns JSON for the transaction detail drawer.
 */
header('Content-Type: application/json');

require_once __DIR__ . '/../../auth/auth.php';
require_role('client');
require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../includes/wallet_functions.php';
require_once __DIR__ . '/../../includes/wallet_helpers.php';

$userId = (int) $_SESSION['user_id'];
$txnId  = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);

if (!$txnId || $txnId <= 0) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Invalid transaction ID']);
    exit;
}

$stmt = $conn->prepare('
    SELECT id, user_id, type, amount, balance_after, reference_id, reference_type, description, created_at
    FROM wallet_transactions
    WHERE id = ? AND user_id = ?
    LIMIT 1
');
$stmt->bind_param('ii', $txnId, $userId);
$stmt->execute();
$txn = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$txn) {
    http_response_code(404);
    echo json_encode(['success' => false, 'error' => 'Transaction not found']);
    exit;
}

$label = match ($txn['type']) {
    'deposit'        => 'Wallet Top Up',
    'escrow_hold'    => 'Escrow Funding',
    'escrow_release' => 'Payment Released',
    'refund'         => 'Refund',
    'credit'         => 'Payment Received',
    'withdrawal'     => 'Withdrawal',
    default          => ucfirst(str_replace('_', ' ', $txn['type'])),
};

$direction = match ($txn['type']) {
    'deposit', 'escrow_release', 'refund', 'credit' => 'credit',
    'escrow_hold', 'withdrawal'                      => 'debit',
    default                                          => 'neutral',
};

$displayText = match ($txn['type']) {
    'deposit'        => 'Completed',
    'escrow_hold'    => 'Held',
    'escrow_release' => 'Released',
    'refund'         => 'Refunded',
    'credit'         => 'Completed',
    'withdrawal'     => 'Completed',
    default          => ucfirst(str_replace('_', ' ', $txn['type'])),
};

$payment   = null;
$milestone = null;
$job       = null;

$refId   = $txn['reference_id']   ? (int) $txn['reference_id']   : null;
$refType = $txn['reference_type'] ?? null;

if ($refType === 'milestone' && $refId) {
    $stmt = $conn->prepare('SELECT id, contract_id, title, amount, status, due_date FROM milestones WHERE id = ?');
    $stmt->bind_param('i', $refId);
    $stmt->execute();
    $milestone = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if ($milestone) {
        if ($txn['type'] === 'escrow_hold') {
            $stmt = $conn->prepare('
                SELECT id, total_amount, platform_fee, freelancer_net, payment_method, status, created_at
                FROM payments
                WHERE milestone_id = ? AND ABS(TIMESTAMPDIFF(SECOND, ?, created_at)) < 10
                ORDER BY ABS(TIMESTAMPDIFF(SECOND, ?, created_at)) ASC
                LIMIT 1
            ');
            $stmt->bind_param('iss', $refId, $txn['created_at'], $txn['created_at']);
        } else {
            $stmt = $conn->prepare('
                SELECT id, total_amount, platform_fee, freelancer_net, payment_method, status, created_at
                FROM payments
                WHERE milestone_id = ? AND created_at <= ?
                ORDER BY created_at DESC
                LIMIT 1
            ');
            $stmt->bind_param('is', $refId, $txn['created_at']);
        }
        $stmt->execute();
        $payment = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        if ($payment && $payment['status']) {
            $displayText = ucfirst($payment['status']);
        }
    }

    if ($milestone && $milestone['contract_id']) {
        $cid = (int) $milestone['contract_id'];
        $stmt = $conn->prepare('SELECT id, job_id, client_id, freelancer_id, total_budget, status FROM contracts WHERE id = ?');
        $stmt->bind_param('i', $cid);
        $stmt->execute();
        $contract = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        if ($contract && $contract['job_id']) {
            $jid = (int) $contract['job_id'];
            $stmt = $conn->prepare('SELECT id, title, budget FROM jobs WHERE id = ?');
            $stmt->bind_param('i', $jid);
            $stmt->execute();
            $job = $stmt->get_result()->fetch_assoc();
            $stmt->close();
        }
    }
}

echo json_encode([
    'success' => true,
    'transaction' => [
        'id'            => (int) $txn['id'],
        'type'          => $txn['type'],
        'amount'        => (float) $txn['amount'],
        'balance_after' => (float) $txn['balance_after'],
        'direction'     => $direction,
        'label'         => $label,
        'displayStatus' => $displayText,
        'description'   => $txn['description'],
        'dateFormatted' => date('M j, Y \a\t g:i A', strtotime($txn['created_at'])),
    ],
    'milestone' => $milestone ? [
        'id'        => (int) $milestone['id'],
        'title'     => $milestone['title'],
        'amount'    => (float) $milestone['amount'],
        'status'    => $milestone['status'],
        'due_date'  => $milestone['due_date'] ? date('M j, Y', strtotime($milestone['due_date'])) : null,
    ] : null,
    'payment' => $payment ? [
        'payment_method' => $payment['payment_method'],
        'total_amount'   => (float) $payment['total_amount'],
        'platform_fee'   => (float) $payment['platform_fee'],
        'freelancer_net' => (float) $payment['freelancer_net'],
        'status'         => $payment['status'],
    ] : null,
    'job' => $job ? [
        'id'    => (int) $job['id'],
        'title' => $job['title'],
    ] : null,
]);

$conn->close();
