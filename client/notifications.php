<?php
/**
 * Client Notifications Page
 * Follows the exact same pattern as client/dashboard.php
 */
require_once __DIR__ . '/../auth/auth.php';
require_role('client');
require_once __DIR__ . '/../config/db.php';

$userId = $_SESSION['user_id'];

$stmt = $conn->prepare('SELECT name, profile_image FROM users WHERE id = ?');
$stmt->bind_param('i', $userId);
$stmt->execute();
$user = $stmt->get_result()->fetch_assoc();
$stmt->close();

$clientName = $user['name'] ?? 'Client';
$unreadMessages = get_unread_message_count($userId, 'client');

$pageTitle = 'Notifications';
$activePage = 'notifications';
$user = ['name' => $clientName, 'profile_image' => $user['profile_image'] ?? null];
$unreadCount = $unreadMessages;
$basePath = '../';
require_once __DIR__ . '/../includes/client_topbar.php';
?>

<?php require_once __DIR__ . '/../shared/notifications_page.php'; ?>

<?php require_once __DIR__ . '/../includes/client_footer.php'; ?>
