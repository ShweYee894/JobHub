<?php

/**
 * auth.php
 * Session authentication guard.
 *
 * Include this file at the top of any page that requires a logged-in user.
 * It starts the session (if not already started), checks for a valid session,
 * and provides helper functions for role-based access control.
 */

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// ── Regenerate session ID periodically to prevent fixation ──────────────
if (!isset($_SESSION['_created'])) {
    $_SESSION['_created'] = time();
} elseif (time() - $_SESSION['_created'] > 1800) {
    // 30-minute rotation
    session_regenerate_id(true);
    $_SESSION['_created'] = time();
}

// ── Remember Me Auto-Login Check ────────────────────────────────────────
if (!isset($_SESSION['user_id']) && isset($_COOKIE['fh_remember'])) {
    require_once __DIR__ . '/../config/db.php';

    $token = $_COOKIE['fh_remember'];
    $hashed_token = hash('sha256', $token);

    $stmt = $conn->prepare(
        'SELECT id, name, email, role, status, profile_image
         FROM users WHERE remember_token = ? AND status = "active" LIMIT 1'
    );
    if ($stmt) {
        $stmt->bind_param('s', $hashed_token);
        $stmt->execute();
        $result = $stmt->get_result();
        $user = $result->fetch_assoc();
        $stmt->close();

        if ($user) {
            session_regenerate_id(true);

            $_SESSION['user_id']       = (int) $user['id'];
            $_SESSION['user_name']     = $user['name'];
            $_SESSION['user_email']    = $user['email'];
            $_SESSION['user_role']     = $user['role'];
            $_SESSION['profile_image'] = $user['profile_image'];
            $_SESSION['_created']      = time();
        } else {
            // Token invalid or user inactive — clear cookie
            $params = session_get_cookie_params();
            setcookie('fh_remember', '', [
                'expires'  => time() - 3600,
                'path'     => '/',
                'domain'   => '',
                'secure'   => !empty($_SERVER['HTTPS']),
                'httponly'  => true,
                'samesite' => 'Lax',
            ]);
        }
    }
}

/**
 * Require the user to be authenticated.
 * Redirects to login page if no valid session exists.
 */
function require_login(): void
{
    if (!isset($_SESSION['user_id'])) {
        $_SESSION['errors'] = ['Please log in to access that page.'];
        header('Location: /finalproject/auth/login.php');
        exit;
    }
}

/**
 * Require a specific role.
 * Redirects to the user's own dashboard if the role doesn't match.
 *
 * @param string|array $allowed  Single role string or array of allowed roles.
 */
function require_role(string|array $allowed): void
{
    require_login();

    $allowed = (array) $allowed;
    if (!in_array($_SESSION['user_role'], $allowed, true)) {
        header('Location: ' . get_dashboard_url($_SESSION['user_role']));
        exit;
    }
}

/**
 * Return the dashboard URL for a given role.
 */
function get_dashboard_url(string $role): string
{
    return match ($role) {
        'admin'      => '/finalproject/admin/dashboard.php',
        'client'     => '/finalproject/client/dashboard.php',
        'freelancer' => '/finalproject/freelancer/home.php',
        default      => '/finalproject/auth/login.php',
    };
}

/**
 * Check if the current user is logged in.
 */
function is_logged_in(): bool
{
    return isset($_SESSION['user_id']);
}

/**
 * Get current user's role (or null if not logged in).
 */
function current_role(): ?string
{
    return $_SESSION['user_role'] ?? null;
}

/**
 * Get the full current user data from session.
 *
 * @return array|null Associative array of user data or null if not logged in.
 */
function current_user(): ?array
{
    if (!is_logged_in()) {
        return null;
    }
    return [
        'id'            => $_SESSION['user_id'] ?? null,
        'name'          => $_SESSION['user_name'] ?? '',
        'email'         => $_SESSION['user_email'] ?? '',
        'role'          => $_SESSION['user_role'] ?? '',
        'profile_image' => $_SESSION['profile_image'] ?? null,
    ];
}

/**
 * Get user initials from the user's name (e.g., "John Doe" → "JD").
 *
 * @param string|null $name  Full name. If null, uses session name.
 * @return string 1-2 character initials.
 */
function get_user_initials(?string $name = null): string
{
    $name = $name ?? ($_SESSION['user_name'] ?? '');
    if (empty(trim($name))) {
        return '?';
    }
    $parts = preg_split('/\s+/', trim($name));
    if (count($parts) >= 2) {
        return strtoupper(mb_substr($parts[0], 0, 1) . mb_substr(end($parts), 0, 1));
    }
    return strtoupper(mb_substr($parts[0], 0, 2));
}
