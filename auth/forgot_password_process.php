<?php
session_start();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: forgot_password.php');
    exit;
}

require_once __DIR__ . '/../config/db.php';

// ── CSRF Verification ──────────────────────────────────────────────────
if (!verify_csrf_token()) {
    set_flash('error', 'Invalid security token. Please try again.');
    header('Location: forgot_password.php');
    exit;
}

// ── Validate Input ──────────────────────────────────────────────────────
$email = trim($_POST['email'] ?? '');

if (empty($email) || !validate_email($email)) {
    set_flash('error', 'Please enter a valid email address.');
    header('Location: forgot_password.php');
    exit;
}

// ── Check if User Exists ────────────────────────────────────────────────
$stmt = $conn->prepare('SELECT id, name, email FROM users WHERE email = ? AND status = "active" LIMIT 1');
if (!$stmt) {
    set_flash('error', 'Database error. Please try again later.');
    header('Location: forgot_password.php');
    exit;
}

$stmt->bind_param('s', $email);
$stmt->execute();
$result = $stmt->get_result();
$user = $result->fetch_assoc();
$stmt->close();

// ── Always show success message to prevent email enumeration ─────────────
// Only generate token if user actually exists
if ($user) {
    // Generate reset token
    $token = generate_token(64);
    $hashed_token = hash('sha256', $token);

    // Store the hashed token in session (acts as our "database" for password resets)
    $_SESSION['reset_token']      = $hashed_token;
    $_SESSION['reset_user_id']    = $user['id'];
    $_SESSION['reset_expires']    = time() + (60 * 60); // 1 hour expiry
    $_SESSION['reset_email']      = $user['email'];

    // Send password reset email
    $reset_link = '/jobhub/auth/reset_password.php?token=' . $token;
    require_once __DIR__ . '/../shared/notification_helper.php';
    notifyPasswordReset($user['email'], $user['name'], $reset_link);

    // For demo purposes, store the plain token in session for the redirect
    $_SESSION['reset_token_plain'] = $token;
}

set_flash('success', 'If an account with that email exists, a password reset link has been sent. Please check your inbox.');
header('Location: forgot_password.php');
exit;
