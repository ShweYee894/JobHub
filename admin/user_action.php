<?php

/**
 * Admin User Action Handler
 * POST only. Actions: suspend, activate, delete. CSRF protected.
 */
require_once __DIR__ . '/../auth/auth.php';
require_role('admin');
require_once __DIR__ . '/../config/db.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    set_flash('error', 'Invalid request method.');
    redirect('users.php');
}

if (!verify_csrf_token()) {
    set_flash('error', 'Invalid security token. Please try again.');
    redirect('users.php');
}

$action = $_POST['action'] ?? '';
$userId = sanitize_int($_POST['user_id'] ?? 0);

if ($userId <= 0) {
    set_flash('error', 'Invalid user ID.');
    redirect('users.php');
}

if (!in_array($action, ['suspend', 'activate', 'delete'])) {
    set_flash('error', 'Invalid action.');
    redirect('users.php');
}

// Prevent admin from acting on themselves
if ($userId === (int) $_SESSION['user_id']) {
    set_flash('error', 'You cannot perform this action on your own account.');
    redirect('users.php');
}

// Verify target user exists
$check = $conn->prepare('SELECT id, role, status, name FROM users WHERE id = ?');
$check->bind_param('i', $userId);
$check->execute();
$target = $check->get_result()->fetch_assoc();
$check->close();

if (!$target) {
    set_flash('error', 'User not found.');
    redirect('users.php');
}

// Prevent suspending/deleting other admins
if ($target['role'] === 'admin' && $action !== 'activate') {
    set_flash('error', 'You cannot ' . $action . ' another admin account.');
    redirect('user_detail.php?id=' . $userId);
}

switch ($action) {
    case 'suspend':
        $stmt = $conn->prepare("UPDATE users SET status = 'suspended' WHERE id = ?");
        $stmt->bind_param('i', $userId);
        $stmt->execute();
        $stmt->close();
        set_flash('success', 'User "' . $target['name'] . '" has been suspended.');
        break;

    case 'activate':
        $stmt = $conn->prepare("UPDATE users SET status = 'active' WHERE id = ?");
        $stmt->bind_param('i', $userId);
        $stmt->execute();
        $stmt->close();
        set_flash('success', 'User "' . $target['name'] . '" has been activated.');
        break;

    case 'delete':
        // Native InnoDB Foreign keys are configured to ON DELETE CASCADE automatically
        $conn->begin_transaction();
        try {
            // Simply delete the user; MySQL handles wiping dependent tables automatically
            $stmt = $conn->prepare('DELETE FROM users WHERE id = ?');
            $stmt->bind_param('i', $userId);
            $stmt->execute();
            $stmt->close();

            $conn->commit();
            set_flash('success', 'User "' . $target['name'] . '" and all their related records have been permanently deleted.');
        } catch (Exception $e) {
            $conn->rollback();
            set_flash('error', 'Failed to delete user: ' . $e->getMessage());
            redirect('users.php');
        }
        break;
}

$conn->close();
redirect('users.php');
