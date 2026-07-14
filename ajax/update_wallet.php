<?php
/**
 * AJAX: Update Wallet (Top Up)
 * Processes demo wallet top-up.
 * POST /ajax/update_wallet.php
 */

session_start();
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../auth/auth.php';
require_once __DIR__ . '/../includes/wallet_functions.php';

header('Content-Type: application/json');

if (!is_logged_in()) {
    json_response(['success' => false, 'message' => 'Authentication required.'], 401);
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    json_response(['success' => false, 'message' => 'POST request required.'], 405);
}

if (!verify_csrf_token()) {
    json_response(['success' => false, 'message' => 'Invalid CSRF token.'], 403);
}

$userId = (int) $_SESSION['user_id'];
$userRole = $_SESSION['user_role'] ?? '';

// Only clients can top up
if ($userRole !== 'client') {
    json_response(['success' => false, 'message' => 'Only clients can top up their wallet.'], 403);
}

$amount = sanitize_float($_POST['amount'] ?? 0);

// Validate amount
if (!is_numeric($amount) || $amount <= 0) {
    json_response(['success' => false, 'message' => 'Please enter a valid amount.'], 400);
}
if ($amount < 10) {
    json_response(['success' => false, 'message' => 'Minimum top-up amount is $10.00.'], 400);
}
if ($amount > 10000) {
    json_response(['success' => false, 'message' => 'Maximum top-up amount is $10,000.00.'], 400);
}

// Check payment method
$paymentMethod = $_POST['payment_method'] ?? 'demo_wallet';
if ($paymentMethod !== 'demo_wallet') {
    json_response(['success' => false, 'message' => 'Only Demo Wallet payment is currently available.'], 400);
}

$result = topup_wallet($conn, $userId, $amount);

if ($result['success']) {
    json_response([
        'success'     => true,
        'message'     => $result['message'],
        'new_balance' => $result['new_balance'],
        'formatted'   => format_currency($result['new_balance']),
        'topup_amount' => $amount,
    ]);
} else {
    json_response(['success' => false, 'message' => $result['message']], 400);
}
