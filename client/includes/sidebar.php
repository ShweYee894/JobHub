<?php

/**
 * Client Sidebar Layout – Reusable include for all client pages.
 * Expects: $conn (db), $_SESSION vars, $currentPage (string) set BEFORE including.
 */

// 1. Ensure the session is running
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// 2. Ensure the database connection exists
if (!isset($conn)) {
    // Adjust this path to point directly to your database configuration file
    require_once __DIR__ . '/../../config/db.php';
}

if (!isset($currentPage))
    $currentPage = 'dashboard';

$_userId = $_SESSION['user_id'];

$sStmt = $conn->prepare('SELECT name, profile_image FROM users WHERE id = ?');
$sStmt->bind_param('i', $_userId);
$sStmt->execute();
$_sideUser = $sStmt->get_result()->fetch_assoc();
$sStmt->close();

$_sideName = $_sideUser['name'] ?? 'Client';
$_sideFirst = explode(' ', $_sideName)[0];
$_sideAvatar = get_profile_image($_sideUser['profile_image'] ?? null);

$_navItems = [
    ['page' => 'dashboard', 'label' => 'Dashboard', 'icon' => 'fa-th-large', 'href' => 'dashboard.php'],
    ['page' => 'my_jobs', 'label' => 'My Jobs', 'icon' => 'fa-briefcase', 'href' => 'my_jobs.php'],
    ['page' => 'post_job', 'label' => 'Post a Job', 'icon' => 'fa-plus-circle', 'href' => 'post_job.php'],
    ['page' => 'proposals', 'label' => 'Proposals', 'icon' => 'fa-file-alt', 'href' => 'proposals.php'],
    ['page' => 'contracts', 'label' => 'Contracts', 'icon' => 'fa-handshake', 'href' => 'contracts.php'],
    ['page' => 'payment_history', 'label' => 'Payments', 'icon' => 'fa-credit-card', 'href' => 'payment_history.php'],
    ['page' => 'recommended_freelancers', 'label' => 'AI Matches', 'icon' => 'fa-brain', 'href' => 'recommended_freelancers.php'],
    ['page' => 'messages', 'label' => 'Messages', 'icon' => 'fa-comment-dots', 'href' => 'messages.php'],
    // ['page' => 'profile', 'label' => 'Profile', 'icon' => 'fa-user', 'href' => 'profile.php'],
];
?>

<!-- ═══ SIDEBAR ═══════════════════════════════════════════════ -->
<aside id="sidebar" class="w-64 bg-white border-r border-gray-100 flex flex-col fixed h-full z-30 transition-transform duration-300 lg:translate-x-0 -translate-x-full" data-open="false">

    <!-- Logo -->
    <div class="p-5 border-b border-gray-100">
        <a href="../index.php" class="inline-flex items-center gap-2.5">
            <div class="w-9 h-9 rounded-xl btn-grad flex items-center justify-center shadow-lg shadow-blue-500/25">
                <i class="fas fa-bolt text-white text-sm"></i>
            </div>
            <span class="text-lg font-extrabold tracking-tight">
                <span class="text-gray-900">Freelance</span><span class="grad-text">Hub</span>
            </span>
        </a>
    </div>

    <!-- Navigation -->
    <nav class="flex-1 py-4 px-3 space-y-1 overflow-y-auto">
        <?php foreach ($_navItems as $item): ?>
            <a href="<?= $item['href'] ?>"
                class="sidebar-link flex items-center gap-3 px-4 py-3 rounded-xl text-sm font-medium <?= $currentPage === $item['page'] ? 'active' : 'text-gray-500' ?>">
                <i class="fas <?= $item['icon'] ?> w-5 text-center"></i>
                <span><?= $item['label'] ?></span>
                <?php if ($item['page'] === 'messages'): ?>
                    <span class="ml-auto bg-red-50 text-red-500 text-[10px] font-bold px-2 py-0.5 rounded-full">3</span>
                <?php endif; ?>
            </a>
        <?php endforeach; ?>
    </nav>

    <!-- User Profile -->
    <div class="p-4 border-t border-gray-100">
        <!-- <a href="profile.php" class="flex items-center gap-3 p-2 rounded-xl hover:bg-gray-50 transition-colors">
            <img src="<?= sanitize_string($_sideAvatar) ?>" class="w-9 h-9 rounded-full object-cover border-2 border-gray-100" alt="Avatar">
            <div class="flex-1 min-w-0">
                <p class="text-sm font-semibold text-gray-900 truncate"><?= sanitize_string($_sideName) ?></p>
                <p class="text-[11px] text-gray-400">Client</p>
            </div>
        </a> -->
        <a href="../auth/logout.php" class="flex items-center justify-center gap-3 px-4 py-3 rounded-xl text-sm font-medium bg-red-600 text-white ">
            <i class="fas fa-sign-out-alt w-5 text-center"></i>
            <span class="text-white">Logout</span>
        </a>
    </div>
</aside>

<!-- Mobile overlay -->
<div id="sidebar-overlay" class="fixed inset-0 bg-black/30 z-20 hidden lg:hidden" onclick="toggleSidebar()"></div>

<script>
    function toggleSidebar() {
        const sidebar = document.getElementById('sidebar');
        const overlay = document.getElementById('sidebar-overlay');
        const isOpen = sidebar.dataset.open === 'true';
        if (isOpen) {
            sidebar.classList.add('-translate-x-full');
            overlay.classList.add('hidden');
            sidebar.dataset.open = 'false';
        } else {
            sidebar.classList.remove('-translate-x-full');
            overlay.classList.remove('hidden');
            sidebar.dataset.open = 'true';
        }
    }
</script>