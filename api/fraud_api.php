<?php
/**
 * Fraud Detection API
 * Handles fraud score calculation, suspicious user listing, activity logs, and action logging.
 *
 * GET ?action=log              - Log a user action
 * GET ?action=calculate_score  - Calculate fraud score for a user
 * GET ?action=recalculate_all  - Batch recalculate all users' fraud scores
 * GET ?action=suspicious       - List suspicious users
 * GET ?action=activity_log     - List recent behavior logs
 */

session_start();
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../auth/auth.php';

header('Content-Type: application/json');

$action = $_GET['action'] ?? '';

switch ($action) {

    // ═══════════════════════════════════════════════════════════════════
    // LOG a user action
    // ═══════════════════════════════════════════════════════════════════
    case 'log':
        $userId     = sanitize_int($_GET['user_id'] ?? $_SESSION['user_id'] ?? 0);
        $actionType = trim($_GET['action_type'] ?? '');
        $payload    = $_GET['payload'] ?? '{}';
        $ipAddress  = get_ip_address();

        if ($userId <= 0) {
            json_response(['success' => false, 'message' => 'Invalid user ID.'], 400);
        }
        if (empty($actionType)) {
            json_response(['success' => false, 'message' => 'action_type is required.'], 400);
        }

        // Verify user exists
        $userCheck = $conn->prepare('SELECT id FROM users WHERE id = ?');
        $userCheck->bind_param('i', $userId);
        $userCheck->execute();
        if (!$userCheck->get_result()->fetch_assoc()) {
            $userCheck->close();
            json_response(['success' => false, 'message' => 'User not found.'], 404);
        }
        $userCheck->close();

        $stmt = $conn->prepare(
            'INSERT INTO user_behavior_logs (user_id, action_type, ip_address, payload, created_at) VALUES (?, ?, ?, ?, NOW())'
        );
        $stmt->bind_param('isss', $userId, $actionType, $ipAddress, $payload);
        if ($stmt->execute()) {
            json_response(['success' => true, 'message' => 'Action logged.', 'log_id' => $stmt->insert_id]);
        } else {
            json_response(['success' => false, 'message' => 'Failed to log action.'], 500);
        }
        $stmt->close();
        break;

    // ═══════════════════════════════════════════════════════════════════
    // CALCULATE fraud score for a user
    // ═══════════════════════════════════════════════════════════════════
    case 'calculate_score':
        if (!is_logged_in()) {
            json_response(['success' => false, 'message' => 'Authentication required.'], 401);
        }
        $userId = sanitize_int($_GET['user_id'] ?? 0);
        if ($userId <= 0) {
            json_response(['success' => false, 'message' => 'Invalid user ID.'], 400);
        }

        $score   = 0;
        $reasons = [];

        // 1. Rapid actions: more than 10 actions in the last 1 minute
        $stmt = $conn->prepare(
            'SELECT COUNT(*) AS cnt FROM user_behavior_logs WHERE user_id = ? AND created_at >= DATE_SUB(NOW(), INTERVAL 1 MINUTE)'
        );
        $stmt->bind_param('i', $userId);
        $stmt->execute();
        $rapidCount = (int) $stmt->get_result()->fetch_assoc()['cnt'];
        $stmt->close();
        if ($rapidCount > 10) {
            $score   += 20;
            $reasons[] = "Rapid actions: {$rapidCount} actions in the last minute (+20)";
        }

        // 2. Multiple IP addresses in last 24 hours
        $stmt = $conn->prepare(
            'SELECT COUNT(DISTINCT ip_address) AS ip_count FROM user_behavior_logs WHERE user_id = ? AND created_at >= DATE_SUB(NOW(), INTERVAL 24 HOUR)'
        );
        $stmt->bind_param('i', $userId);
        $stmt->execute();
        $ipCount = (int) $stmt->get_result()->fetch_assoc()['ip_count'];
        $stmt->close();
        if ($ipCount > 1) {
            $score   += 15;
            $reasons[] = "Multiple IPs: {$ipCount} unique IPs in 24h (+15)";
        }

        // 3. Rapid proposal submissions: more than 5 in 1 hour
        $stmt = $conn->prepare(
            "SELECT COUNT(*) AS cnt FROM user_behavior_logs WHERE user_id = ? AND action_type = 'proposal_submit' AND created_at >= DATE_SUB(NOW(), INTERVAL 1 HOUR)"
        );
        $stmt->bind_param('i', $userId);
        $stmt->execute();
        $proposalCount = (int) $stmt->get_result()->fetch_assoc()['cnt'];
        $stmt->close();
        if ($proposalCount > 5) {
            $score   += 25;
            $reasons[] = "Rapid proposals: {$proposalCount} submissions in 1 hour (+25)";
        }

        // 4. Failed login attempts
        $stmt = $conn->prepare(
            "SELECT COUNT(*) AS cnt FROM user_behavior_logs WHERE user_id = ? AND action_type = 'login_failed' AND created_at >= DATE_SUB(NOW(), INTERVAL 24 HOUR)"
        );
        $stmt->bind_param('i', $userId);
        $stmt->execute();
        $failedLogins = (int) $stmt->get_result()->fetch_assoc()['cnt'];
        $stmt->close();
        if ($failedLogins > 0) {
            $points = min($failedLogins * 10, 30);
            $score   += $points;
            $reasons[] = "Failed logins: {$failedLogins} in 24h (+{$points})";
        }

        // 5. Flagged action types: +10 each
        $flaggedTypes = ['spam', 'phishing', 'fake_review', 'payment_fraud', 'account_takeover', 'suspicious_download'];
        $placeholders = implode(',', array_fill(0, count($flaggedTypes), '?'));
        $stmt = $conn->prepare(
            "SELECT action_type, COUNT(*) AS cnt FROM user_behavior_logs WHERE user_id = ? AND action_type IN ({$placeholders}) AND created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY) GROUP BY action_type"
        );
        $types = array_merge([$userId], $flaggedTypes);
        $stmt->bind_param(str_repeat('s', count($types)), ...$types);
        $stmt->execute();
        $flaggedResult = $stmt->get_result();
        while ($row = $flaggedResult->fetch_assoc()) {
            $points = (int) $row['cnt'] * 10;
            $score   += $points;
            $reasons[] = "Flagged action '{$row['action_type']}': {$row['cnt']} occurrences (+{$points})";
        }
        $stmt->close();

        // Cap score at 100
        $score = min($score, 100);

        // Update user fraud score
        $stmt = $conn->prepare('UPDATE users SET fraud_score = ? WHERE id = ?');
        $stmt->bind_param('ii', $score, $userId);
        $stmt->execute();
        $stmt->close();

        // Auto-flag if score >= 70
        if ($score >= 70) {
            $stmt = $conn->prepare("UPDATE users SET status = 'flagged' WHERE id = ? AND status = 'active'");
            $stmt->bind_param('i', $userId);
            $stmt->execute();
            if ($stmt->affected_rows > 0) {
                $reasons[] = 'User auto-flagged (score >= 70)';
            }
            $stmt->close();
        }

        json_response([
            'success' => true,
            'user_id' => $userId,
            'score'   => $score,
            'reasons' => $reasons,
        ]);
        break;

    // ═══════════════════════════════════════════════════════════════════
    // SUSPICIOUS users list
    // ═══════════════════════════════════════════════════════════════════
    case 'suspicious':
        if (!is_logged_in()) {
            json_response(['success' => false, 'message' => 'Authentication required.'], 401);
        }

        $stmt = $conn->prepare(
            "SELECT u.id, u.name, u.email, u.role, u.status, u.fraud_score, u.profile_image, u.created_at,
                    (SELECT COUNT(DISTINCT bl.ip_address) FROM user_behavior_logs bl WHERE bl.user_id = u.id AND bl.created_at >= DATE_SUB(NOW(), INTERVAL 24 HOUR)) AS unique_ips_24h,
                    (SELECT bl2.action_type FROM user_behavior_logs bl2 WHERE bl2.user_id = u.id ORDER BY bl2.created_at DESC LIMIT 1) AS last_action,
                    (SELECT bl3.created_at FROM user_behavior_logs bl3 WHERE bl3.user_id = u.id ORDER BY bl3.created_at DESC LIMIT 1) AS last_action_time
             FROM users u
             WHERE u.fraud_score >= 50 OR u.status = 'flagged'
             ORDER BY u.fraud_score DESC, u.created_at DESC"
        );
        $stmt->execute();
        $users = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        $stmt->close();

        json_response(['success' => true, 'users' => $users]);
        break;

    // ═══════════════════════════════════════════════════════════════════
    // ACTIVITY LOG with filtering and pagination
    // ═══════════════════════════════════════════════════════════════════
    case 'activity_log':
        if (!is_logged_in()) {
            json_response(['success' => false, 'message' => 'Authentication required.'], 401);
        }

        $filterUserId   = sanitize_int($_GET['user_id'] ?? 0);
        $filterAction   = trim($_GET['action_type'] ?? '');
        $filterDateFrom = trim($_GET['date_from'] ?? '');
        $filterDateTo   = trim($_GET['date_to'] ?? '');
        $page           = max(1, intval($_GET['page'] ?? 1));
        $perPage        = 20;

        $where  = [];
        $params = [];
        $types  = '';

        if ($filterUserId > 0) {
            $where[]  = 'bl.user_id = ?';
            $params[] = $filterUserId;
            $types   .= 'i';
        }
        if (!empty($filterAction)) {
            $where[]  = 'bl.action_type = ?';
            $params[] = $filterAction;
            $types   .= 's';
        }
        if (!empty($filterDateFrom)) {
            $where[]  = 'bl.created_at >= ?';
            $params[] = $filterDateFrom . ' 00:00:00';
            $types   .= 's';
        }
        if (!empty($filterDateTo)) {
            $where[]  = 'bl.created_at <= ?';
            $params[] = $filterDateTo . ' 23:59:59';
            $types   .= 's';
        }

        $whereClause = !empty($where) ? 'WHERE ' . implode(' AND ', $where) : '';

        // Count
        $countSql = "SELECT COUNT(*) AS total FROM user_behavior_logs bl {$whereClause}";
        $stmt = $conn->prepare($countSql);
        if (!empty($params)) {
            $stmt->bind_param($types, ...$params);
        }
        $stmt->execute();
        $total = (int) $stmt->get_result()->fetch_assoc()['total'];
        $stmt->close();

        $pagination = paginate($total, $perPage, $page);

        // Fetch
        $dataSql = "SELECT bl.*, u.name AS user_name, u.email AS user_email, u.profile_image
                    FROM user_behavior_logs bl
                    JOIN users u ON bl.user_id = u.id
                    {$whereClause}
                    ORDER BY bl.created_at DESC
                    LIMIT ? OFFSET ?";
        $allParams   = array_merge($params, [$perPage, $pagination['offset']]);
        $allTypes    = $types . 'ii';
        $stmt = $conn->prepare($dataSql);
        $stmt->bind_param($allTypes, ...$allParams);
        $stmt->execute();
        $logs = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        $stmt->close();

        json_response([
            'success'    => true,
            'logs'       => $logs,
            'pagination' => $pagination,
        ]);
        break;

    // ═══════════════════════════════════════════════════════════════════
    // RECALCULATE ALL user fraud scores (batch)
    // ═══════════════════════════════════════════════════════════════════
    case 'recalculate_all':
        if (!is_logged_in()) {
            json_response(['success' => false, 'message' => 'Authentication required.'], 401);
        }

        // Get all users who have any behavior logs
        $stmt = $conn->prepare('SELECT DISTINCT user_id FROM user_behavior_logs');
        $stmt->execute();
        $result = $stmt->get_result();
        $userIds = [];
        while ($row = $result->fetch_assoc()) {
            $userIds[] = (int) $row['user_id'];
        }
        $stmt->close();

        $updated = 0;
        $flagged = 0;

        foreach ($userIds as $uid) {
            $score = 0;

            // 1. Rapid actions: >10 in last 1 minute
            $stmt = $conn->prepare(
                'SELECT COUNT(*) AS cnt FROM user_behavior_logs WHERE user_id = ? AND created_at >= DATE_SUB(NOW(), INTERVAL 1 MINUTE)'
            );
            $stmt->bind_param('i', $uid);
            $stmt->execute();
            $rapidCount = (int) $stmt->get_result()->fetch_assoc()['cnt'];
            $stmt->close();
            if ($rapidCount > 10) $score += 20;

            // 2. Multiple IPs in 24h
            $stmt = $conn->prepare(
                'SELECT COUNT(DISTINCT ip_address) AS ip_count FROM user_behavior_logs WHERE user_id = ? AND created_at >= DATE_SUB(NOW(), INTERVAL 24 HOUR)'
            );
            $stmt->bind_param('i', $uid);
            $stmt->execute();
            $ipCount = (int) $stmt->get_result()->fetch_assoc()['ip_count'];
            $stmt->close();
            if ($ipCount > 1) $score += 15;

            // 3. Rapid proposals: >5 in 1 hour
            $stmt = $conn->prepare(
                "SELECT COUNT(*) AS cnt FROM user_behavior_logs WHERE user_id = ? AND action_type = 'proposal_submit' AND created_at >= DATE_SUB(NOW(), INTERVAL 1 HOUR)"
            );
            $stmt->bind_param('i', $uid);
            $stmt->execute();
            $proposalCount = (int) $stmt->get_result()->fetch_assoc()['cnt'];
            $stmt->close();
            if ($proposalCount > 5) $score += 25;

            // 4. Failed logins in 24h
            $stmt = $conn->prepare(
                "SELECT COUNT(*) AS cnt FROM user_behavior_logs WHERE user_id = ? AND action_type = 'login_failed' AND created_at >= DATE_SUB(NOW(), INTERVAL 24 HOUR)"
            );
            $stmt->bind_param('i', $uid);
            $stmt->execute();
            $failedLogins = (int) $stmt->get_result()->fetch_assoc()['cnt'];
            $stmt->close();
            if ($failedLogins > 0) $score += min($failedLogins * 10, 30);

            // 5. Flagged action types in 7 days
            $flaggedTypes = ['spam', 'phishing', 'fake_review', 'payment_fraud', 'account_takeover', 'suspicious_download'];
            $placeholders = implode(',', array_fill(0, count($flaggedTypes), '?'));
            $stmt = $conn->prepare(
                "SELECT COUNT(*) AS cnt FROM user_behavior_logs WHERE user_id = ? AND action_type IN ({$placeholders}) AND created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)"
            );
            $types = array_merge([$uid], $flaggedTypes);
            $stmt->bind_param(str_repeat('s', count($types)), ...$types);
            $stmt->execute();
            $flaggedCount = (int) $stmt->get_result()->fetch_assoc()['cnt'];
            $stmt->close();
            $score += $flaggedCount * 10;

            $score = min($score, 100);

            $stmt = $conn->prepare('UPDATE users SET fraud_score = ? WHERE id = ?');
            $stmt->bind_param('ii', $score, $uid);
            $stmt->execute();
            $stmt->close();
            $updated++;

            // Auto-flag if score >= 70
            if ($score >= 70) {
                $stmt = $conn->prepare("UPDATE users SET status = 'flagged' WHERE id = ? AND status = 'active'");
                $stmt->bind_param('i', $uid);
                $stmt->execute();
                if ($stmt->affected_rows > 0) $flagged++;
                $stmt->close();
            }
        }

        json_response([
            'success'  => true,
            'message'  => "Recalculated {$updated} users. {$flagged} auto-flagged.",
            'updated'  => $updated,
            'flagged'  => $flagged,
        ]);
        break;

    default:
        json_response(['success' => false, 'message' => 'Invalid action.'], 400);
        break;
}
