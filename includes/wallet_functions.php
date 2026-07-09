<?php
/**
 * Wallet Core Functions
 * Handles all wallet operations with database transactions.
 * Every wallet modification uses BEGIN, COMMIT, ROLLBACK.
 */

/**
 * Get the current wallet balance for a user.
 */
function get_wallet_balance(mysqli $conn, int $userId): float
{
    $stmt = $conn->prepare('SELECT wallet_balance FROM users WHERE id = ?');
    $stmt->bind_param('i', $userId);
    $stmt->execute();
    $result = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    return $result ? (float) $result['wallet_balance'] : 0.0;
}

/**
 * Top up wallet (Demo Payment).
 * Uses transaction. Logs to user_behavior_logs and wallet_transactions.
 *
 * @return array ['success' => bool, 'message' => string, 'new_balance' => float|null]
 */
function topup_wallet(mysqli $conn, int $userId, float $amount): array
{
    if ($userId <= 0) {
        return ['success' => false, 'message' => 'Invalid user ID.', 'new_balance' => null];
    }

    // Validate amount
    if (!is_numeric($amount) || $amount <= 0) {
        return ['success' => false, 'message' => 'Invalid amount.', 'new_balance' => null];
    }
    if ($amount < 10) {
        return ['success' => false, 'message' => 'Minimum top-up amount is $10.00.', 'new_balance' => null];
    }
    if ($amount > 10000) {
        return ['success' => false, 'message' => 'Maximum top-up amount is $10,000.00.', 'new_balance' => null];
    }
    if ($amount != round($amount, 2)) {
        return ['success' => false, 'message' => 'Amount must have at most 2 decimal places.', 'new_balance' => null];
    }

    $conn->begin_transaction();
    try {
        // Read current balance
        $stmt = $conn->prepare('SELECT wallet_balance FROM users WHERE id = ?');
        $stmt->bind_param('i', $userId);
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        if (!$row) {
            throw new Exception('User not found.');
        }

        $oldBalance = (float) $row['wallet_balance'];
        $newBalance = round($oldBalance + $amount, 2);

        // Update wallet balance
        $stmt = $conn->prepare('UPDATE users SET wallet_balance = ?, updated_at = NOW() WHERE id = ?');
        $stmt->bind_param('di', $newBalance, $userId);
        $stmt->execute();
        if ($stmt->affected_rows === 0) {
            throw new Exception('Failed to update wallet balance.');
        }
        $stmt->close();

        // Insert wallet_transactions record
        $stmt = $conn->prepare("
            INSERT INTO wallet_transactions (user_id, type, amount, balance_after, reference_type, description, created_at)
            VALUES (?, 'deposit', ?, ?, 'wallet', ?, NOW())
        ");
        $desc = 'Wallet top-up via Demo Payment';
        $stmt->bind_param('idds', $userId, $amount, $newBalance, $desc);
        $stmt->execute();
        $stmt->close();

        // Log to user_behavior_logs
        $payload = json_encode([
            'amount'      => $amount,
            'old_balance' => $oldBalance,
            'new_balance' => $newBalance,
            'method'      => 'demo_wallet',
        ]);
        $ip = get_ip_address();
        $stmt = $conn->prepare("
            INSERT INTO user_behavior_logs (user_id, action_type, ip_address, payload, created_at)
            VALUES (?, 'wallet_topup', ?, ?, NOW())
        ");
        $stmt->bind_param('iss', $userId, $ip, $payload);
        $stmt->execute();
        $stmt->close();

        $conn->commit();

        return [
            'success'    => true,
            'message'    => 'Wallet topped up successfully. +' . format_currency($amount),
            'new_balance' => $newBalance,
        ];
    } catch (Exception $e) {
        $conn->rollback();
        return [
            'success'    => false,
            'message'    => $e->getMessage() ?: 'Failed to top up wallet.',
            'new_balance' => null,
        ];
    }
}

/**
 * Fund escrow from client wallet.
 * Deducts from client wallet and updates milestone status.
 *
 * @return array ['success' => bool, 'message' => string, 'new_balance' => float|null]
 */
function fund_escrow_from_wallet(mysqli $conn, int $clientId, int $milestoneId, float $amount): array
{
    $conn->begin_transaction();
    try {
        // Check wallet balance with lock
        $stmt = $conn->prepare('SELECT wallet_balance FROM users WHERE id = ?');
        $stmt->bind_param('i', $clientId);
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        if (!$row) {
            throw new Exception('User not found.');
        }

        $currentBalance = (float) $row['wallet_balance'];
        if ($currentBalance < $amount) {
            throw new Exception('Insufficient wallet balance. You need ' . format_currency($amount) . ' but have ' . format_currency($currentBalance) . '.');
        }

        $newBalance = round($currentBalance - $amount, 2);

        // Deduct from client wallet
        $stmt = $conn->prepare('UPDATE users SET wallet_balance = wallet_balance - ? WHERE id = ? AND wallet_balance >= ?');
        $stmt->bind_param('did', $amount, $clientId, $amount);
        $stmt->execute();
        if ($stmt->affected_rows === 0) {
            throw new Exception('Insufficient balance (concurrent modification).');
        }
        $stmt->close();

        // Update milestone status
        $stmt = $conn->prepare("UPDATE milestones SET status = 'funded_in_escrow', updated_at = NOW() WHERE id = ?");
        $stmt->bind_param('i', $milestoneId);
        $stmt->execute();
        $stmt->close();

        // Log wallet transaction
        $stmt = $conn->prepare("
            INSERT INTO wallet_transactions (user_id, type, amount, balance_after, reference_id, reference_type, description, created_at)
            VALUES (?, 'escrow_hold', ?, ?, ?, 'milestone', ?, NOW())
        ");
        $desc = 'Escrow hold for milestone #' . $milestoneId;
        $stmt->bind_param('iddis', $clientId, $amount, $newBalance, $milestoneId, $desc);
        $stmt->execute();
        $stmt->close();

        // Log to user_behavior_logs
        $payload = json_encode([
            'milestone_id' => $milestoneId,
            'amount'       => $amount,
            'old_balance'  => $currentBalance,
            'new_balance'  => $newBalance,
        ]);
        $ip = get_ip_address();
        $stmt = $conn->prepare("
            INSERT INTO user_behavior_logs (user_id, action_type, ip_address, payload, created_at)
            VALUES (?, 'escrow_fund', ?, ?, NOW())
        ");
        $stmt->bind_param('iss', $clientId, $ip, $payload);
        $stmt->execute();
        $stmt->close();

        $conn->commit();

        return [
            'success'     => true,
            'message'     => 'Milestone funded successfully. ' . format_currency($amount) . ' placed in escrow.',
            'new_balance' => $newBalance,
        ];
    } catch (Exception $e) {
        $conn->rollback();
        return [
            'success'     => false,
            'message'     => $e->getMessage() ?: 'Failed to fund escrow.',
            'new_balance' => null,
        ];
    }
}

/**
 * Release payment to freelancer when milestone is approved.
 * Credits freelancer wallet with net amount after platform fee.
 *
 * @return array ['success' => bool, 'message' => string]
 */
function release_payment_to_freelancer(mysqli $conn, int $milestoneId): array
{
    $conn->begin_transaction();
    try {
        // Get milestone with contract info
        $stmt = $conn->prepare('
            SELECT m.*, c.client_id, c.freelancer_id
            FROM milestones m
            JOIN contracts c ON m.contract_id = c.id
            WHERE m.id = ?
        ');
        $stmt->bind_param('i', $milestoneId);
        $stmt->execute();
        $milestone = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        if (!$milestone) {
            throw new Exception('Milestone not found.');
        }
        if ($milestone['status'] !== 'submitted') {
            throw new Exception('Only submitted milestones can be released.');
        }

        $freelancerId = (int) $milestone['freelancer_id'];
        $amount = (float) $milestone['amount'];
        $feePercent = get_platform_fee_percent();
        $platformFee = round($amount * ($feePercent / 100), 2);
        $freelancerNet = round($amount - $platformFee, 2);

        // Update milestone status
        $stmt = $conn->prepare("UPDATE milestones SET status = 'released', updated_at = NOW() WHERE id = ?");
        $stmt->bind_param('i', $milestoneId);
        $stmt->execute();
        $stmt->close();

        // Get freelancer current balance
        $stmt = $conn->prepare('SELECT wallet_balance FROM users WHERE id = ?');
        $stmt->bind_param('i', $freelancerId);
        $stmt->execute();
        $freelancerBalance = (float) $stmt->get_result()->fetch_assoc()['wallet_balance'];
        $stmt->close();

        // Credit freelancer wallet
        $newFreelancerBalance = round($freelancerBalance + $freelancerNet, 2);
        $stmt = $conn->prepare('UPDATE users SET wallet_balance = wallet_balance + ? WHERE id = ?');
        $stmt->bind_param('di', $freelancerNet, $freelancerId);
        $stmt->execute();
        $stmt->close();

        // Update payment status
        $stmt = $conn->prepare("UPDATE payments SET status = 'completed' WHERE milestone_id = ?");
        $stmt->bind_param('i', $milestoneId);
        $stmt->execute();
        $stmt->close();

        // Log wallet transaction for freelancer
        $stmt = $conn->prepare("
            INSERT INTO wallet_transactions (user_id, type, amount, balance_after, reference_id, reference_type, description, created_at)
            VALUES (?, 'escrow_release', ?, ?, ?, 'milestone', ?, NOW())
        ");
        $desc = 'Payment received for milestone #' . $milestoneId;
        $stmt->bind_param('iddis', $freelancerId, $freelancerNet, $newFreelancerBalance, $milestoneId, $desc);
        $stmt->execute();
        $stmt->close();

        // Log to user_behavior_logs
        $payload = json_encode([
            'milestone_id'    => $milestoneId,
            'freelancer_id'   => $freelancerId,
            'gross_amount'    => $amount,
            'platform_fee'    => $platformFee,
            'freelancer_net'  => $freelancerNet,
            'old_balance'     => $freelancerBalance,
            'new_balance'     => $newFreelancerBalance,
        ]);
        $ip = get_ip_address();
        $stmt = $conn->prepare("
            INSERT INTO user_behavior_logs (user_id, action_type, ip_address, payload, created_at)
            VALUES (?, 'payment_release', ?, ?, NOW())
        ");
        $stmt->bind_param('iss', $freelancerId, $ip, $payload);
        $stmt->execute();
        $stmt->close();

        $conn->commit();

        return [
            'success' => true,
            'message' => 'Payment of ' . format_currency($freelancerNet) . ' released to freelancer.',
        ];
    } catch (Exception $e) {
        $conn->rollback();
        return [
            'success' => false,
            'message' => $e->getMessage() ?: 'Failed to release payment.',
        ];
    }
}

/**
 * Refund milestone to client wallet.
 * Used when dispute is resolved in client's favor.
 *
 * @return array ['success' => bool, 'message' => string]
 */
function refund_milestone_to_client(mysqli $conn, int $milestoneId): array
{
    $conn->begin_transaction();
    try {
        // Get milestone with contract info
        $stmt = $conn->prepare('
            SELECT m.*, c.client_id, c.freelancer_id
            FROM milestones m
            JOIN contracts c ON m.contract_id = c.id
            WHERE m.id = ?
        ');
        $stmt->bind_param('i', $milestoneId);
        $stmt->execute();
        $milestone = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        if (!$milestone) {
            throw new Exception('Milestone not found.');
        }
        if ($milestone['status'] !== 'disputed') {
            throw new Exception('Only disputed milestones can be refunded.');
        }

        $clientId = (int) $milestone['client_id'];
        $amount = (float) $milestone['amount'];

        // Get client current balance
        $stmt = $conn->prepare('SELECT wallet_balance FROM users WHERE id = ?');
        $stmt->bind_param('i', $clientId);
        $stmt->execute();
        $clientBalance = (float) $stmt->get_result()->fetch_assoc()['wallet_balance'];
        $stmt->close();

        // Credit client wallet
        $newClientBalance = round($clientBalance + $amount, 2);
        $stmt = $conn->prepare('UPDATE users SET wallet_balance = wallet_balance + ? WHERE id = ?');
        $stmt->bind_param('di', $amount, $clientId);
        $stmt->execute();
        $stmt->close();

        // Update milestone status to pending
        $stmt = $conn->prepare("UPDATE milestones SET status = 'pending', updated_at = NOW() WHERE id = ?");
        $stmt->bind_param('i', $milestoneId);
        $stmt->execute();
        $stmt->close();

        // Update payment status to refunded
        $stmt = $conn->prepare("UPDATE payments SET status = 'refunded', refund_amount = ?, refunded_at = NOW() WHERE milestone_id = ? AND status = 'completed'");
        $stmt->bind_param('di', $amount, $milestoneId);
        $stmt->execute();
        $stmt->close();

        // Log wallet transaction
        $stmt = $conn->prepare("
            INSERT INTO wallet_transactions (user_id, type, amount, balance_after, reference_id, reference_type, description, created_at)
            VALUES (?, 'refund', ?, ?, ?, 'milestone', ?, NOW())
        ");
        $desc = 'Refund for milestone #' . $milestoneId . ' (Dispute resolved)';
        $stmt->bind_param('iddis', $clientId, $amount, $newClientBalance, $milestoneId, $desc);
        $stmt->execute();
        $stmt->close();

        // Log to user_behavior_logs
        $payload = json_encode([
            'milestone_id' => $milestoneId,
            'amount'       => $amount,
            'old_balance'  => $clientBalance,
            'new_balance'  => $newClientBalance,
            'reason'       => 'dispute_refund',
        ]);
        $ip = get_ip_address();
        $stmt = $conn->prepare("
            INSERT INTO user_behavior_logs (user_id, action_type, ip_address, payload, created_at)
            VALUES (?, 'wallet_refund', ?, ?, NOW())
        ");
        $stmt->bind_param('iss', $clientId, $ip, $payload);
        $stmt->execute();
        $stmt->close();

        $conn->commit();

        return [
            'success' => true,
            'message' => 'Refund of ' . format_currency($amount) . ' returned to client wallet.',
        ];
    } catch (Exception $e) {
        $conn->rollback();
        return [
            'success' => false,
            'message' => $e->getMessage() ?: 'Failed to process refund.',
        ];
    }
}

/**
 * Get wallet statistics for a client.
 */
function get_wallet_stats(mysqli $conn, int $userId): array
{
    // Current balance
    $balance = get_wallet_balance($conn, $userId);

    // Funds in escrow (sum of milestone amounts where status = funded_in_escrow)
    $stmt = $conn->prepare('
        SELECT COALESCE(SUM(m.amount), 0) AS total
        FROM milestones m
        JOIN contracts c ON m.contract_id = c.id
        WHERE c.client_id = ? AND m.status = \'funded_in_escrow\'
    ');
    $stmt->bind_param('i', $userId);
    $stmt->execute();
    $escrowAmount = (float) $stmt->get_result()->fetch_assoc()['total'];
    $stmt->close();

    // Completed payments (total spent)
    $stmt = $conn->prepare("
        SELECT COALESCE(SUM(total_amount), 0) AS total
        FROM payments
        WHERE payer_id = ? AND status = 'completed'
    ");
    $stmt->bind_param('i', $userId);
    $stmt->execute();
    $completedPayments = (float) $stmt->get_result()->fetch_assoc()['total'];
    $stmt->close();

    // Recent top-ups (count of deposits in user_behavior_logs)
    $stmt = $conn->prepare("
        SELECT COUNT(*) AS cnt
        FROM user_behavior_logs
        WHERE user_id = ? AND action_type = 'wallet_topup'
    ");
    $stmt->bind_param('i', $userId);
    $stmt->execute();
    $recentTopUps = (int) $stmt->get_result()->fetch_assoc()['cnt'];
    $stmt->close();

    return [
        'balance'           => $balance,
        'escrow_amount'     => $escrowAmount,
        'completed_payments' => $completedPayments,
        'recent_topups'     => $recentTopUps,
    ];
}

/**
 * Get combined wallet history from multiple sources.
 * Combines: wallet_transactions, payments, user_behavior_logs
 */
function get_wallet_history(mysqli $conn, int $userId, int $limit = 20, int $offset = 0): array
{
    $history = [];

    // 1. From wallet_transactions (deposits, escrow holds, escrow releases, refunds)
    $stmt = $conn->prepare('
        SELECT
            wt.id,
            wt.type,
            wt.amount,
            wt.balance_after,
            wt.description,
            wt.reference_id,
            wt.reference_type,
            wt.created_at,
            \'wallet_transaction\' AS source
        FROM wallet_transactions wt
        WHERE wt.user_id = ?
        ORDER BY wt.created_at DESC
    ');
    $stmt->bind_param('i', $userId);
    $stmt->execute();
    $wtResults = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();

    foreach ($wtResults as $row) {
        $type = $row['type'];
        $amount = (float) $row['amount'];

        // Determine display properties
        switch ($type) {
            case 'deposit':
                $label = 'Wallet Top Up';
                $icon = 'fa-plus-circle';
                $color = 'emerald';
                $direction = 'credit';
                break;
            case 'escrow_hold':
                $label = 'Escrow Funding';
                $icon = 'fa-shield-halved';
                $color = 'amber';
                $direction = 'debit';
                break;
            case 'escrow_release':
                $label = 'Payment Released';
                $icon = 'fa-paper-plane';
                $color = 'blue';
                $direction = 'credit';
                break;
            case 'refund':
                $label = 'Refund';
                $icon = 'fa-undo';
                $color = 'purple';
                $direction = 'credit';
                break;
            case 'credit':
                $label = 'Payment Received';
                $icon = 'fa-check-circle';
                $color = 'emerald';
                $direction = 'credit';
                break;
            case 'withdrawal':
                $label = 'Withdrawal';
                $icon = 'fa-arrow-up';
                $color = 'red';
                $direction = 'debit';
                break;
            default:
                $label = ucfirst(str_replace('_', ' ', $type));
                $icon = 'fa-circle';
                $color = 'gray';
                $direction = 'neutral';
        }

        $history[] = [
            'id'          => $row['id'],
            'label'       => $label,
            'type'        => $type,
            'amount'      => $amount,
            'direction'   => $direction,
            'balance_after' => (float) $row['balance_after'],
            'description' => $row['description'],
            'icon'        => $icon,
            'color'       => $color,
            'date'        => $row['created_at'],
            'source'      => 'wallet_transaction',
        ];
    }

    // Sort by date descending
    usort($history, function ($a, $b) {
        return strtotime($b['date']) - strtotime($a['date']);
    });

    // Apply limit and offset
    $total = count($history);
    $history = array_slice($history, $offset, $limit);

    return [
        'history' => $history,
        'total'   => $total,
    ];
}
