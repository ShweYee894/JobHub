<?php
/**
 * Process Top Up – Form Handler
 * Handles wallet top-up form submission and redirects back.
 */
require_once __DIR__ . '/../auth/auth.php';
require_role('client');
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/wallet_functions.php';

$userId = (int) $_SESSION['user_id'];

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect('wallet.php');
}

if (!verify_csrf_token()) {
    set_flash('error', 'Invalid CSRF token.');
    redirect('wallet.php');
}

$amount = sanitize_float($_POST['amount'] ?? 0);
$paymentMethod = $_POST['payment_method'] ?? 'demo_wallet';

if ($paymentMethod !== 'demo_wallet') {
    set_flash('error', 'Only Demo Wallet payment is currently available.');
    redirect('wallet.php');
}

$result = topup_wallet($conn, $userId, $amount);

if ($result['success']) {
    set_flash('success', $result['message']);
} else {
    set_flash('error', $result['message']);
}

redirect('wallet.php');
