<?php

/**
 * login_process.php
 * Handles POST submission from login.php
 */
session_start();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: login.php');
    exit;
}

require_once __DIR__ . '/../config/helpers.php';
require_once __DIR__ . '/../config/db.php';

// ── CSRF Verification ──────────────────────────────────────────────────
if (!verify_csrf_token()) {
    $_SESSION['errors'] = ['Invalid security token. Please try again.'];
    header('Location: login.php');
    exit;
}

// ── Collect & sanitise input ────────────────────────────────────────────
$email = trim($_POST['email'] ?? '');
$password = trim($_POST['password'] ?? '');
$remember = isset($_POST['remember']) && $_POST['remember'] === 'on';

// Preserve email for redirect-back
$_SESSION['form_data'] = ['email' => $email];

// ── Validation ──────────────────────────────────────────────────────────
$errors = [];

if (empty($email)) {
    $errors[] = 'Email address is required.';
} elseif (!validate_email($email)) {
    $errors[] = 'Please enter a valid email address.';
}

if (empty($password)) {
    $errors[] = 'Password is required.';
}

if (!empty($errors)) {
    $_SESSION['errors'] = $errors;
    header('Location: login.php');
    exit;
}

// ── Look Up User (prepared statement) ──────────────────────────────────
$stmt = $conn->prepare(
    'SELECT id, name, email, password, role, status, profile_image FROM users WHERE email = ? LIMIT 1'
);
if (!$stmt) {
    $_SESSION['errors'] = ['Database error. Please try again later.'];
    header('Location: login.php');
    exit;
}

$stmt->bind_param('s', $email);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    $stmt->close();
    $_SESSION['errors'] = ['Invalid email or password.'];
    header('Location: login.php');
    exit;
}

$user = $result->fetch_assoc();
$stmt->close();

// ── Verify Password ────────────────────────────────────────────────────
if (!password_verify($password, $user['password'])) {
    $_SESSION['errors'] = ['Invalid email or password.'];
    header('Location: login.php');
    exit;
}

// ── Check Account Status ───────────────────────────────────────────────
if ($user['status'] === 'suspended') {
    $_SESSION['errors'] = ['Your account has been suspended. Please contact support.'];
    header('Location: login.php');
    exit;
}

if ($user['status'] === 'flagged') {
    $_SESSION['flash']['warning'] = 'Your account has been flagged. Some features may be limited.';
}

// ── Regenerate Session ID ──────────────────────────────────────────────
session_regenerate_id(true);

// ── Set Session Variables ──────────────────────────────────────────────
$_SESSION['user_id'] = (int) $user['id'];
$_SESSION['user_name'] = $user['name'];
$_SESSION['user_email'] = $user['email'];
$_SESSION['user_role'] = $user['role'];
$_SESSION['profile_image'] = $user['profile_image'];
$_SESSION['_created'] = time();

// Clear preserved form data
unset($_SESSION['form_data'], $_SESSION['errors']);

// ── Remember Me Cookie ─────────────────────────────────────────────────
if ($remember) {
    $token = generate_token(64);
    $hashed_token = hash('sha256', $token);

    // Store hashed token in session so we can validate later
    $_SESSION['remember_token'] = $hashed_token;

    // Update the user's record with the remember token
    $update_stmt = $conn->prepare('UPDATE users SET remember_token = ? WHERE id = ?');
    if ($update_stmt) {
        $update_stmt->bind_param('si', $hashed_token, $user['id']);
        $update_stmt->execute();
        $update_stmt->close();
    }

    // Set cookie: token lasts 30 days
    $cookie_params = [
        'expires' => time() + (30 * 24 * 60 * 60),
        'path' => '/',
        'domain' => '',
        'secure' => !empty($_SERVER['HTTPS']),
        'httponly' => true,
        'samesite' => 'Lax',
    ];
    setcookie('fh_remember', $token, $cookie_params);
}

// ── Log Login in user_behavior_logs ────────────────────────────────────
$log_stmt = $conn->prepare(
    'INSERT INTO user_behavior_logs (user_id, action_type, ip_address, payload, created_at)
     VALUES (?, "user_login", ?, NULL, NOW())'
);
if ($log_stmt) {
    $ip = get_ip_address();
    $payload = json_encode([
        'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? '',
        'timestamp' => date('c'),
    ]);
    $log_stmt->bind_param('is', $user['id'], $ip);
    $log_stmt->execute();
    $log_stmt->close();
}

// ── Redirect by Role ───────────────────────────────────────────────────
$redirect = match ($user['role']) {
    'admin' => '../admin/dashboard.php',
    'client' => '../client/dashboard.php',
    'freelancer' => '../freelancer/home.php',
    default => '../index.php',
};

header('Location: ' . $redirect);
exit;
