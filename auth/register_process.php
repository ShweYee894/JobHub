<?php
session_start();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: register.php');
    exit;
}

require_once __DIR__ . '/../config/db.php';

// ── CSRF Verification ──────────────────────────────────────────────────
if (!verify_csrf_token()) {
    $_SESSION['errors'] = ['Invalid security token. Please try again.'];
    header('Location: register.php');
    exit;
}

// ── Collect & sanitise input ────────────────────────────────────────────
$name = trim($_POST['name'] ?? '');
$email = trim($_POST['email'] ?? '');
$password = $_POST['password'] ?? '';
$confirm_password = $_POST['confirm_password'] ?? '';
$role = $_POST['role'] ?? '';

// ── Preserve form data for redirect-back ───────────────────────────────
$_SESSION['form_data'] = [
    'name' => $name,
    'email' => $email,
    'role' => $role,
];

// ── Validation ──────────────────────────────────────────────────────────
$errors = [];

if (empty($name)) {
    $errors[] = 'Full name is required.';
} elseif (strlen($name) < 2 || strlen($name) > 150) {
    $errors[] = 'Name must be between 2 and 150 characters.';
}

if (empty($email)) {
    $errors[] = 'Email address is required.';
} elseif (!validate_email($email)) {
    $errors[] = 'Please enter a valid email address.';
}

$allowed_roles = ['client', 'freelancer'];
if (!in_array($role, $allowed_roles, true)) {
    $errors[] = 'Please select a valid role (Client or Freelancer).';
}

// Password validation
if (empty($password)) {
    $errors[] = 'Password is required.';
} else {
    $pwd_errors = validate_password($password);
    $errors = array_merge($errors, $pwd_errors);
}

if ($password !== $confirm_password) {
    $errors[] = 'Passwords do not match.';
}

// Bail early if validation failed
if (!empty($errors)) {
    $_SESSION['errors'] = $errors;
    header('Location: register.php');
    exit;
}

// ── Email Uniqueness Check (prepared statement) ─────────────────────────
$stmt = $conn->prepare('SELECT id FROM users WHERE email = ? LIMIT 1');
if (!$stmt) {
    $_SESSION['errors'] = ['Database error. Please try again later.'];
    header('Location: register.php');
    exit;
}
$stmt->bind_param('s', $email);
$stmt->execute();
$stmt->store_result();

if ($stmt->num_rows > 0) {
    $stmt->close();
    $_SESSION['errors'] = ['An account with that email address already exists. Please log in or use a different email.'];
    header('Location: register.php');
    exit;
}
$stmt->close();

// ── Hash Password ───────────────────────────────────────────────────────
$hashed_password = password_hash($password, PASSWORD_DEFAULT);

// ── TRANSACTION: Insert users -> clients / freelancers ──────────────────
$conn->begin_transaction();

try {
    // 1. Insert into users table
    $phone = null;  // Optional, not in form
    $stmt = $conn->prepare(
        "INSERT INTO users (name, email, phone, password, role, status, created_at)
         VALUES (?, ?, ?, ?, ?, 'active', NOW())"
    );
    if (!$stmt) {
        throw new RuntimeException('Users insert failed: ' . $conn->error);
    }
    $stmt->bind_param('sssss', $name, $email, $phone, $hashed_password, $role);
    $stmt->execute();
    $user_id = (int) $conn->insert_id;
    $stmt->close();

    if ($user_id === 0) {
        throw new RuntimeException('Failed to create user account.');
    }

    // 2. Insert into role-specific table
    if ($role === 'client') {
        $stmt = $conn->prepare(
            'INSERT INTO clients (client_id, company_name, industry, company_size, total_spent, created_at)
             VALUES (?, ?, ?, NULL, 0.00, NOW())'
        );
        if (!$stmt) {
            throw new RuntimeException('Clients insert failed: ' . $conn->error);
        }
        $cn = null;
        $ind = null;
        $stmt->bind_param('iss', $user_id, $cn, $ind);
        $stmt->execute();
        $stmt->close();
    } elseif ($role === 'freelancer') {
        $stmt = $conn->prepare(
            'INSERT INTO freelancers (user_id, title, hourly_rate, years_of_experience, created_at)
             VALUES (?, ?, ?, 0, NOW())'
        );
        if (!$stmt) {
            throw new RuntimeException('Freelancers insert failed: ' . $conn->error);
        }
        $title = '';
        $hr = 0.0;
        $stmt->bind_param('isd', $user_id, $title, $hr);
        $stmt->execute();
        $stmt->close();
    }

    // 3. Log registration in user_behavior_logs
    $log_stmt = $conn->prepare(
        'INSERT INTO user_behavior_logs (user_id, action_type, ip_address, payload, created_at)
         VALUES (?, "user_register", ?, NULL, NOW())'
    );
    if ($log_stmt) {
        $ip = get_ip_address();
        $log_stmt->bind_param('is', $user_id, $ip);
        $log_stmt->execute();
        $log_stmt->close();
    }

    // ── Commit ───────────────────────────────────────────────────────────
    $conn->commit();

    // ── Send welcome email (non-blocking) ─────────────────────────────────
    try {
        require_once __DIR__ . '/../shared/notification_helper.php';
        notifyWelcomeEmail($user_id, $name, $role);
    } catch (Exception $emailErr) {
        error_log('[JobHub] Welcome email failed: ' . $emailErr->getMessage());
    }

    // ── Set session variables ─────────────────────────────────────────────
    $_SESSION['user_id'] = $user_id;
    $_SESSION['user_name'] = $name;
    $_SESSION['user_email'] = $email;
    $_SESSION['user_role'] = $role;
    $_SESSION['profile_image'] = null;

    // Clear preserved data
    unset($_SESSION['form_data'], $_SESSION['errors']);

    // ── Redirect by role ──────────────────────────────────────────────────
    $redirect = match ($role) {
        'client' => '../client/dashboard.php',
        'freelancer' => '../freelancer/dashboard.php',
        'admin' => '../admin/dashboard.php',
        default => '../index.php',
    };
    header('Location: ' . $redirect);
    exit;
} catch (Exception $e) {
    $conn->rollback();
    error_log('[JobHub] Registration error: ' . $e->getMessage());

    $_SESSION['errors'] = ['An error occurred during registration. Please try again.'];
    header('Location: register.php');
    exit;
}
