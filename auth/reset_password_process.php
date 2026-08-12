<?php
session_start();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: reset_password.php');
    exit;
}

require_once __DIR__ . '/../config/db.php';

// ── CSRF Verification ──────────────────────────────────────────────────
if (!verify_csrf_token()) {
    set_flash('error', 'Invalid security token. Please try again.');
    header('Location: reset_password.php');
    exit;
}

// ── Validate reset session ──────────────────────────────────────────────
if (!isset($_SESSION['reset_token']) || !isset($_SESSION['reset_expires']) || time() >= $_SESSION['reset_expires']) {
    set_flash('error', 'Your password reset session has expired. Please request a new link.');
    header('Location: forgot_password.php');
    exit;
}

// ── Validate token from form ────────────────────────────────────────────
$form_token = trim($_POST['token'] ?? '');
if (empty($form_token)) {
    set_flash('error', 'Invalid reset token. Please try again.');
    header('Location: forgot_password.php');
    exit;
}

$form_token_hash = hash('sha256', $form_token);
if (!hash_equals($_SESSION['reset_token'], $form_token_hash)) {
    set_flash('error', 'Invalid reset token. Please request a new link.');
    header('Location: forgot_password.php');
    exit;
}

// ── Validate password ───────────────────────────────────────────────────
$password         = $_POST['password'] ?? '';
$confirm_password = $_POST['confirm_password'] ?? '';

$errors = [];

if (empty($password)) {
    $errors[] = 'Password is required.';
} else {
    $pwd_errors = validate_password($password);
    $errors = array_merge($errors, $pwd_errors);
}

if ($password !== $confirm_password) {
    $errors[] = 'Passwords do not match.';
}

if (!empty($errors)) {
    set_flash('error', implode(' ', $errors));
    header('Location: reset_password.php');
    exit;
}

// ── Hash new password and update ────────────────────────────────────────
$hashed_password = password_hash($password, PASSWORD_DEFAULT);
$user_id = (int) $_SESSION['reset_user_id'];

$stmt = $conn->prepare('UPDATE users SET password = ? WHERE id = ? AND status = "active"');
if (!$stmt) {
    set_flash('error', 'Database error. Please try again later.');
    header('Location: reset_password.php');
    exit;
}

$stmt->bind_param('si', $hashed_password, $user_id);
$stmt->execute();

if ($stmt->affected_rows > 0) {
    $stmt->close();

    // Clear reset session data
    unset($_SESSION['reset_token'], $_SESSION['reset_token_plain'],
          $_SESSION['reset_user_id'], $_SESSION['reset_expires'], $_SESSION['reset_email']);

    // Log the password reset in user_behavior_logs
    $log_stmt = $conn->prepare(
        'INSERT INTO user_behavior_logs (user_id, action_type, ip_address, payload, created_at)
         VALUES (?, "password_reset", ?, NULL, NOW())'
    );
    if ($log_stmt) {
        $ip = get_ip_address();
        $log_stmt->bind_param('is', $user_id, $ip);
        $log_stmt->execute();
        $log_stmt->close();
    }

    // Send password changed security alert
    require_once __DIR__ . '/../shared/notification_helper.php';
    $user_stmt = $conn->prepare('SELECT name FROM users WHERE id = ?');
    $user_stmt->bind_param('i', $user_id);
    $user_stmt->execute();
    $user_name = $user_stmt->get_result()->fetch_assoc()['name'] ?? '';
    $user_stmt->close();
    notifyPasswordChanged($user_id, $user_name);

    set_flash('success', 'Your password has been reset successfully. You can now sign in.');
    header('Location: login.php');
    exit;
} else {
    $stmt->close();
    set_flash('error', 'Failed to update password. Please try again.');
    header('Location: reset_password.php');
    exit;
}
