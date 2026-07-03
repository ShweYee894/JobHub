<?php

/**
 * Freelancer Sidebar Component
 * Reusable sidebar for all freelancer pages.
 * Requires: $user (assoc array: name, profile_image), $activePage (string)
 */
$_sidebarUser = $user ?? ['name' => 'Freelancer', 'profile_image' => ''];
$_sidebarAvatar = get_profile_image($_sidebarUser['profile_image']);

$_sidebarPages = [
    'dashboard' => ['url' => 'dashboard.php', 'icon' => 'fa-th-large', 'label' => 'Dashboard'],
    'profile' => ['url' => 'profile.php', 'icon' => 'fa-user', 'label' => 'Profile'],
    'browse_jobs' => ['url' => 'browse_jobs.php', 'icon' => 'fa-search', 'label' => 'Browse Jobs'],
    'proposals' => ['url' => 'proposals.php', 'icon' => 'fa-file-alt', 'label' => 'My Proposals'],
    'contracts' => ['url' => 'contracts.php', 'icon' => 'fa-handshake', 'label' => 'Contracts'],
    'reviews' => ['url' => 'reviews.php', 'icon' => 'fa-star', 'label' => 'Reviews'],
    'earnings' => ['url' => 'earnings.php', 'icon' => 'fa-wallet', 'label' => 'Earnings'],
    'recommended_jobs' => ['url' => 'recommended_jobs.php', 'icon' => 'fa-brain', 'label' => 'Recommended Jobs'],
    'messages' => ['url' => 'messages.php', 'icon' => 'fa-comment-dots', 'label' => 'Messages'],
];

$_activePage = $activePage ?? 'dashboard';
?>

<aside id="sidebar" class="w-64 bg-white border-r border-gray-100 flex flex-col fixed h-full z-30 transition-transform duration-300 lg:translate-x-0 -translate-x-full" data-open="false">
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

    <nav class="flex-1 py-4 px-3 space-y-1">
        <?php foreach ($_sidebarPages as $key => $item): ?>
        <a href="<?= $item['url'] ?>"
           class="sidebar-link flex items-center gap-3 px-4 py-3 rounded-xl text-sm font-medium <?= $_activePage === $key ? 'active' : 'text-gray-500' ?>">
            <i class="fas <?= $item['icon'] ?> w-5 text-center"></i>
            <span><?= $item['label'] ?></span>
        </a>
        <?php endforeach; ?>
    </nav>
    
    <div class="p-4 border-t border-gray-100 ">
        <!--  -->
         <a href="../auth/logout.php" class="rounded-lg p-3 text-white  bg-red-500 dark:bg-slate-700 flex items-center justify-center">
            <i class="fas fa-sign-out-alt text-base px-2"></i> Logout
        </a>
    </div>
</aside>

<div id="sidebar-overlay" class="fixed inset-0 bg-black/30 z-20 hidden lg:hidden" onclick="toggleSidebar()"></div>
