<?php
/**
 * Admin Test Email
 * Send a test email to verify SMTP configuration.
 */
require_once __DIR__ . '/../auth/auth.php';
require_role('admin');
require_once __DIR__ . '/../config/db.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect('settings.php');
}

if (!verify_csrf_token()) {
    if (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && $_SERVER['HTTP_X_REQUESTED_WITH'] === 'XMLHttpRequest') {
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'message' => 'Invalid security token.']);
        exit;
    }
    set_flash('error', 'Invalid security token.');
    redirect('settings.php');
}

$toEmail = trim($_POST['test_email'] ?? '');

if (empty($toEmail) || !filter_var($toEmail, FILTER_VALIDATE_EMAIL)) {
    if (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && $_SERVER['HTTP_X_REQUESTED_WITH'] === 'XMLHttpRequest') {
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'message' => 'Please enter a valid email address.']);
        exit;
    }
    set_flash('error', 'Please enter a valid email address.');
    redirect('settings.php');
}

require_once __DIR__ . '/../shared/EmailNotificationService.php';
$emailService = new EmailNotificationService($conn);
$result = $emailService->sendTestEmail($toEmail);

if (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && $_SERVER['HTTP_X_REQUESTED_WITH'] === 'XMLHttpRequest') {
    header('Content-Type: application/json');
    echo json_encode($result);
    exit;
}

if ($result['success']) {
    set_flash('success', 'Test email sent successfully to ' . htmlspecialchars($toEmail) . '.');
} else {
    set_flash('error', 'Failed to send test email: ' . htmlspecialchars($result['message']));
}

redirect('settings.php');
