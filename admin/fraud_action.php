<?php
/**
 * Fraud Action Handler
 * POST-only endpoint for admin fraud management actions.
 *
 * Actions: flag_user, unflag_user, suspend_user, reset_score
 */

require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../auth/auth.php';
require_role('admin');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    set_flash('error', 'Invalid request method.');
    redirect('/jobhub/admin/fraud_detection.php');
}

if (!verify_csrf_token()) {
    set_flash('error', 'Invalid CSRF token. Please try again.');
    redirect('/jobhub/admin/fraud_detection.php');
}

$action = $_POST['action'] ?? '';
$userId = sanitize_int($_POST['user_id'] ?? 0);

if ($userId <= 0) {
    set_flash('error', 'Invalid user ID.');
    redirect('/jobhub/admin/fraud_detection.php');
}

// Verify user exists
$stmt = $conn->prepare('SELECT id, name, email, status, fraud_score FROM users WHERE id = ?');
$stmt->bind_param('i', $userId);
$stmt->execute();
$user = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$user) {
    set_flash('error', 'User not found.');
    redirect('/jobhub/admin/fraud_detection.php');
}

$adminId = (int) $_SESSION['user_id'];

switch ($action) {

    case 'flag_user':
        $stmt = $conn->prepare("UPDATE users SET status = 'flagged' WHERE id = ? AND status != 'suspended'");
        $stmt->bind_param('i', $userId);
        $stmt->execute();
        $stmt->close();

        // Log admin action
        $payload = json_encode(['admin_id' => $adminId, 'action' => 'flag_user', 'target_user' => $userId]);
        $ip = get_ip_address();
        $stmt = $conn->prepare('INSERT INTO user_behavior_logs (user_id, action_type, ip_address, payload, created_at) VALUES (?, ?, ?, ?, NOW())');
        $logAction = 'admin_flag_user';
        $stmt->bind_param('isss', $userId, $logAction, $ip, $payload);
        $stmt->execute();
        $stmt->close();

        set_flash('success', "User {$user['name']} has been flagged.");
        break;

    case 'unflag_user':
        $stmt = $conn->prepare("UPDATE users SET status = 'active', fraud_score = 0 WHERE id = ?");
        $stmt->bind_param('i', $userId);
        $stmt->execute();
        $stmt->close();

        // Log admin action
        $payload = json_encode(['admin_id' => $adminId, 'action' => 'unflag_user', 'target_user' => $userId]);
        $ip = get_ip_address();
        $stmt = $conn->prepare('INSERT INTO user_behavior_logs (user_id, action_type, ip_address, payload, created_at) VALUES (?, ?, ?, ?, NOW())');
        $logAction = 'admin_unflag_user';
        $stmt->bind_param('isss', $userId, $logAction, $ip, $payload);
        $stmt->execute();
        $stmt->close();

        set_flash('success', "User {$user['name']} has been unflagged and score reset.");
        break;

    case 'suspend_user':
        $stmt = $conn->prepare("UPDATE users SET status = 'suspended' WHERE id = ?");
        $stmt->bind_param('i', $userId);
        $stmt->execute();
        $stmt->close();

        // Log admin action
        $payload = json_encode(['admin_id' => $adminId, 'action' => 'suspend_user', 'target_user' => $userId]);
        $ip = get_ip_address();
        $stmt = $conn->prepare('INSERT INTO user_behavior_logs (user_id, action_type, ip_address, payload, created_at) VALUES (?, ?, ?, ?, NOW())');
        $logAction = 'admin_suspend_user';
        $stmt->bind_param('isss', $userId, $logAction, $ip, $payload);
        $stmt->execute();
        $stmt->close();

        set_flash('success', "User {$user['name']} has been suspended.");
        break;

    case 'reset_score':
        $stmt = $conn->prepare("UPDATE users SET fraud_score = 0 WHERE id = ?");
        $stmt->bind_param('i', $userId);
        $stmt->execute();
        $stmt->close();

        // Log admin action
        $payload = json_encode(['admin_id' => $adminId, 'action' => 'reset_score', 'target_user' => $userId, 'previous_score' => $user['fraud_score']]);
        $ip = get_ip_address();
        $stmt = $conn->prepare('INSERT INTO user_behavior_logs (user_id, action_type, ip_address, payload, created_at) VALUES (?, ?, ?, ?, NOW())');
        $logAction = 'admin_reset_score';
        $stmt->bind_param('isss', $userId, $logAction, $ip, $payload);
        $stmt->execute();
        $stmt->close();

        set_flash('success', "Fraud score for {$user['name']} has been reset to 0.");
        break;

    default:
        set_flash('error', 'Invalid action.');
        break;
}

redirect('/jobhub/admin/fraud_detection.php');
