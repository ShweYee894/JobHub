<?php
/**
 * AJAX: Get Wallet Balance
 * Returns current wallet balance for the logged-in user.
 * GET /ajax/get_wallet_balance.php
 */

session_start();
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/helpers.php';
require_once __DIR__ . '/../auth/auth.php';
require_once __DIR__ . '/../includes/wallet_functions.php';

header('Content-Type: application/json');

if (!is_logged_in()) {
    json_response(['success' => false, 'message' => 'Authentication required.'], 401);
}

$userId = (int) $_SESSION['user_id'];
$balance = get_wallet_balance($conn, $userId);

json_response([
    'success' => true,
    'balance' => $balance,
    'formatted' => format_currency($balance),
]);
