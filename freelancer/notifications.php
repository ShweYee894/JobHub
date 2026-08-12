<?php
/**
 * Freelancer Notifications Page
 * Follows the exact same pattern as freelancer/home.php
 */
session_start();
require_once __DIR__ . '/../config/db.php';

if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'freelancer') {
    header('Location: ../auth/login.php');
    exit();
}

$userId = $_SESSION['user_id'];

$stmt = $conn->prepare('SELECT name, profile_image FROM users WHERE id = ?');
$stmt->bind_param('i', $userId);
$stmt->execute();
$user = $stmt->get_result()->fetch_assoc();
$stmt->close();

$freelancerName = $user['name'] ?? 'Freelancer';
$unreadMessages = get_unread_message_count($userId, 'freelancer');

$pageTitle = 'Notifications';
$activePage = 'notifications';
$userData = ['name' => $freelancerName, 'profile_image' => $user['profile_image'] ?? null];
$unreadCount = $unreadMessages;
$basePath = '../';
require_once __DIR__ . '/../components/freelancer_header.php';
?>

<?php require_once __DIR__ . '/../shared/notifications_page.php'; ?>

<?php require_once __DIR__ . '/../components/freelancer_footer.php'; ?>
