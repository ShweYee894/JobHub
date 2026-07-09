<?php
/**
 * Client Payments - Redirects to payment_history.php
 */
require_once __DIR__ . '/../auth/auth.php';
require_role('client');
header('Location: payment_history.php');
exit;
