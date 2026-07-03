<?php
/**
 * logout.php
 * Securely destroys the session, clears remember-me cookie, and redirects.
 */
session_start();

require_once __DIR__ . '/../config/helpers.php';
require_once __DIR__ . '/../config/db.php';

// ── Remove remember token from DB if present ───────────────────────────
if (isset($_SESSION['user_id']) && isset($_SESSION['remember_token'])) {
    $stmt = $conn->prepare('UPDATE users SET remember_token = NULL WHERE id = ?');
    if ($stmt) {
        $stmt->bind_param('i', $_SESSION['user_id']);
        $stmt->execute();
        $stmt->close();
    }
}

// ── Clear remember-me cookie ───────────────────────────────────────────
if (isset($_COOKIE['fh_remember'])) {
    $params = [
        'expires'  => time() - 3600,
        'path'     => '/',
        'domain'   => '',
        'secure'   => !empty($_SERVER['HTTPS']),
        'httponly'  => true,
        'samesite' => 'Lax',
    ];
    setcookie('fh_remember', '', $params);
}

// ── Clear all session variables ─────────────────────────────────────────
$_SESSION = [];

// ── Delete session cookie ───────────────────────────────────────────────
if (ini_get('session.use_cookies')) {
    $params = session_get_cookie_params();
    setcookie(
        session_name(),
        '',
        time() - 42000,
        $params['path'],
        $params['domain'],
        $params['secure'],
        $params['httponly']
    );
}

// ── Destroy session ─────────────────────────────────────────────────────
session_destroy();

// ── Start fresh session for flash message ───────────────────────────────
session_start();
set_flash('success', 'You have been logged out successfully.');

header('Location: login.php');
exit;
