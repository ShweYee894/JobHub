<?php
if (!isset($currentPage))
    $currentPage = '';
$_userId = $_SESSION['user_id'];

$sStmt = $conn->prepare('SELECT name, profile_image FROM users WHERE id = ?');
$sStmt->bind_param('i', $_userId);
$sStmt->execute();
$_sideUser = $sStmt->get_result()->fetch_assoc();
$sStmt->close();

$_sideName = $_sideUser['name'] ?? 'Client';
$_sideFirst = explode(' ', $_sideName)[0];
$_sideAvatar = get_profile_image($_sideUser['profile_image'] ?? null);
?>
<aside id="sidebar" class="w-64 bg-white border-r border-gray-100 flex flex-col fixed h-full z-30 transition-transform duration-300 lg:translate-x-0 -translate-x-full" data-open="false">
    <div class="p-6 border-b border-gray-100">
        <h2 class="text-xl font-bold text-blue-600 flex items-center gap-2">
            <a href="/finalproject/index.php">
                <i class="fas fa-shield-alt text-blue-700"></i> Admin Panel
            </a>
        </h2>
    </div>
    <nav class="p-4 space-y-1 flex-1">
        <a href="/finalproject/admin/dashboard.php" class="flex items-center gap-3 px-4 py-3 rounded-lg text-sm font-medium transition <?= $currentPage === 'dashboard' ? 'bg-blue-600 text-white' : 'hover:bg-gray-800 hover:text-white' ?>">
            <i class="fas fa-tachometer-alt w-5 text-center"></i> Dashboard
        </a>
        <a href="/finalproject/admin/users.php" class="flex items-center gap-3 px-4 py-3 rounded-lg text-sm font-medium transition <?= $currentPage === 'users' ? 'bg-blue-600 text-white' : 'hover:bg-gray-800 hover:text-white' ?>">
            <i class="fas fa-users w-5 text-center"></i> Users
        </a>
        <a href="/finalproject/admin/jobs.php" class="flex items-center gap-3 px-4 py-3 rounded-lg text-sm font-medium transition <?= $currentPage === 'jobs' ? 'bg-blue-600 text-white' : 'hover:bg-gray-800 hover:text-white' ?>">
            <i class="fas fa-briefcase w-5 text-center"></i> Jobs
        </a>
        <a href="/finalproject/admin/payments.php" class="flex items-center gap-3 px-4 py-3 rounded-lg text-sm font-medium transition <?= $currentPage === 'payments' ? 'bg-blue-600 text-white' : 'hover:bg-gray-800 hover:text-white' ?>">
            <i class="fas fa-credit-card w-5 text-center"></i> Payments
        </a>
        <a href="/finalproject/admin/fraud_detection.php" class="flex items-center gap-3 px-4 py-3 rounded-lg text-sm font-medium transition <?= $currentPage === 'fraud' ? 'bg-blue-600 text-white' : 'hover:bg-gray-800 hover:text-white' ?>">
            <i class="fas fa-exclamation-triangle w-5 text-center"></i> Fraud Detection
        </a>
        <a href="/finalproject/admin/ai_matching.php" class="flex items-center gap-3 px-4 py-3 rounded-lg text-sm font-medium transition <?= $currentPage === 'matching' ? 'bg-blue-600 text-white' : 'hover:bg-gray-800 hover:text-white' ?>">
            <i class="fas fa-brain w-5 text-center"></i> AI Matching
        </a>
        <a href="/finalproject/admin/settings.php" class="flex items-center gap-3 px-4 py-3 rounded-lg text-sm font-medium transition <?= $currentPage === 'settings' ? 'bg-blue-600 text-white' : 'hover:bg-gray-800 hover:text-white' ?>">
            <i class="fas fa-cog w-5 text-center"></i> Settings
        </a>
    </nav>

    <!-- User Profile -->
    <div class="p-4 border-t border-gray-100 ">
        <!-- <a href="profile.php" class="flex items-center gap-3 p-2 rounded-xl hover:bg-gray-50 transition-colors">
            <img src="<?= sanitize_string($_sideAvatar) ?>" class="w-9 h-9 rounded-full object-cover border-2 border-gray-100 " alt="Avatar">
            <div class="flex-1 min-w-0">
                <p class="text-sm font-semibold text-gray-900 truncate"><?= sanitize_string($_sideName) ?></p>
                <p class="text-[11px] text-gray-400">Admin</p>
            </div>
            <i class="fas fa-ellipsis-vertical text-gray-500 text-xs"></i>
        </a> -->
         <a href="../auth/logout.php" class="flex items-center justify-center gap-3 px-4 py-3 rounded-xl text-sm font-medium bg-red-600 text-white ">
            <i class="fas fa-sign-out-alt w-5 text-center"></i>
            <span class="text-white">Logout</span>
        </a>
    </div>
</aside>