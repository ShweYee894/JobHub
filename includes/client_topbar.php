<?php

/**
 * Client Top Navigation Bar
 * Modern Upwork/Fiverr-style top navigation with dropdowns.
 * Replaces the old sidebar layout for all client pages.
 *
 * Required variables before include:
 *   $pageTitle (string)
 *   $activePage (string) - current page key
 *   $user (array) - ['name', 'profile_image']
 *   $unreadCount (int)
 */
$_ctbTitle = $pageTitle ?? 'Dashboard';
$_ctbActive = $activePage ?? 'dashboard';
$_ctbUser = $user ?? ['name' => 'Client', 'profile_image' => ''];
$_ctbAvatar = get_profile_image($_ctbUser['profile_image'] ?? null);
$_ctbUnread = $unreadCount ?? 0;
$_ctbName = $_ctbUser['name'] ?? 'Client';

// Fetch wallet balance for clients
$_ctbWalletBalance = null;
if (isset($_SESSION['user_role']) && $_SESSION['user_role'] === 'client' && isset($_SESSION['user_id'])) {
    if (isset($conn) && $conn instanceof mysqli) {
        $_ctbWStmt = $conn->prepare('SELECT wallet_balance FROM users WHERE id = ?');
        $_ctbWStmt->bind_param('i', $_SESSION['user_id']);
        $_ctbWStmt->execute();
        $_ctbWRow = $_ctbWStmt->get_result()->fetch_assoc();
        $_ctbWStmt->close();
        $_ctbWalletBalance = $_ctbWRow ? (float) $_ctbWRow['wallet_balance'] : 0.0;
    }
}
?>
<!DOCTYPE html>
<html lang="en" class="scroll-smooth">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width,initial-scale=1.0">
    <title><?= htmlspecialchars($_ctbTitle) ?> – JobHub</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800;900&display=swap" rel="stylesheet">
    <script src="https://unpkg.com/lucide@0.344.0/dist/umd/lucide.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js"></script>
    <link rel="icon" type="image/png" sizes="32x32" href="../assets/upload/logos/logo.png">
    <link rel="stylesheet" href="/jobhub/shared/dark-mode.css">
    <script>
        tailwind.config = {
            darkMode: 'class',
            theme: {
                extend: {
                    fontFamily: {
                        inter: ['Inter', 'sans-serif']
                    },
                    colors: {
                        primary: {
                            DEFAULT: '#2563eb',
                            dark: '#1d4ed8',
                            light: '#3b82f6'
                        },
                        accent: {
                            DEFAULT: '#14b8a6',
                            dark: '#0d9488'
                        },
                        surface: {
                            DEFAULT: '#f8fafc',
                            card: '#ffffff',
                            border: '#e5edf6'
                        }
                    }
                }
            }
        }
    </script>
    <style>
        *,
        *::before,
        *::after {
            box-sizing: border-box;
        }

        body {
            font-family: 'Inter', sans-serif;
            /* background: #F4F7FC;
            color: #1e293b; */
            margin: 0;
        }

        html.dark body {
            background: #0f172a;
            color: #e2e8f0;
        }

        /* Dashboard card */
        .dh-card {
            background: #fff;
            border: 1px solid #E5EDF6;
            border-radius: 20px;
            box-shadow: 0 4px 20px rgba(15, 23, 42, .05);
            transition: box-shadow .2s;
        }

        .dh-card:hover {
            box-shadow: 0 8px 30px rgba(15, 23, 42, .08);
        }

        html.dark .dh-card {
            background: #1e293b;
            border-color: #334155;
        }

        /* Stat card */
        .stat-card {
            background: #fff;
            border: 1px solid #E5EDF6;
            border-left: 4px solid var(--accent, #2563EB);
            border-radius: 14px;
            padding: 16px 18px;
            transition: all .2s;
        }

        .stat-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(15, 23, 42, .07);
        }

        html.dark .stat-card {
            background: #1e293b;
            border-color: #334155;
            border-left-color: var(--accent, #3b82f6);
        }

        /* Quick action */
        .qa-card {
            background: #fff;
            border: 1px solid #E5EDF6;
            border-radius: 20px;
            padding: 24px;
            transition: all .25s;
        }

        .qa-card:hover {
            transform: translateY(-3px);
            box-shadow: 0 12px 32px rgba(15, 23, 42, .08);
        }

        html.dark .qa-card {
            background: #1e293b;
            border-color: #334155;
        }

        /* Badge */
        .bdg {
            display: inline-flex;
            align-items: center;
            padding: 3px 10px;
            border-radius: 8px;
            font-size: 11px;
            font-weight: 600;
            border: 1px solid transparent;
        }

        .bdg-blue {
            background: #EEF5FF;
            color: #2563EB;
            border-color: #DBEAFE;
        }

        .bdg-emerald {
            background: #ECFDF5;
            color: #059669;
            border-color: #D1FAE5;
        }

        .bdg-purple {
            background: #F5F3FF;
            color: #7C3AED;
            border-color: #EDE9FE;
        }

        .bdg-orange {
            background: #FFF7ED;
            color: #EA580C;
            border-color: #FED7AA;
        }

        .bdg-cyan {
            background: #ECFEFF;
            color: #0891B2;
            border-color: #CFFAFE;
        }

        .bdg-rose {
            background: #FFF1F2;
            color: #E11D48;
            border-color: #FFE4E6;
        }

        .bdg-gray {
            background: #f1f5f9;
            color: #64748b;
            border-color: #e2e8f0;
        }

        html.dark .bdg-blue {
            background: rgba(37, 99, 235, .12);
            border-color: rgba(37, 99, 235, .25);
        }

        html.dark .bdg-emerald {
            background: rgba(5, 150, 105, .12);
            border-color: rgba(5, 150, 105, .25);
        }

        html.dark .bdg-purple {
            background: rgba(124, 58, 237, .12);
            border-color: rgba(124, 58, 237, .25);
        }

        html.dark .bdg-orange {
            background: rgba(234, 88, 12, .12);
            border-color: rgba(234, 88, 12, .25);
        }

        html.dark .bdg-cyan {
            background: rgba(8, 145, 178, .12);
            border-color: rgba(8, 145, 178, .25);
        }

        html.dark .bdg-rose {
            background: rgba(225, 29, 72, .12);
            border-color: rgba(225, 29, 72, .25);
        }

        html.dark .bdg-gray {
            background: rgba(100, 116, 139, .12);
            border-color: rgba(100, 116, 139, .25);
        }

        /* Progress bar */
        .pbar {
            width: 100%;
            height: 6px;
            background: #E5EDF6;
            border-radius: 999px;
            overflow: hidden;
        }

        .pbar-fill {
            height: 100%;
            border-radius: 999px;
            background: linear-gradient(90deg, #2563eb, #3b82f6);
        }

        .pbar-fill.green {
            background: linear-gradient(90deg, #059669, #10b981);
        }

        html.dark .pbar {
            background: #334155;
        }

        /* Data table */
        .dtbl {
            width: 100%;
            border-collapse: separate;
            border-spacing: 0;
        }

        .dtbl thead th {
            text-align: left;
            padding: 0 12px 10px;
            font-size: 11px;
            font-weight: 600;
            color: #94a3b8;
            text-transform: uppercase;
            letter-spacing: .04em;
            border-bottom: 1px solid #E5EDF6;
        }

        .dtbl tbody td {
            padding: 12px;
            font-size: 13px;
            border-bottom: 1px solid #f1f5f9;
        }

        .dtbl tbody tr:last-child td {
            border-bottom: none;
        }

        .dtbl tbody tr:hover {
            background: #f8fafc;
        }

        html.dark .dtbl thead th {
            border-bottom-color: #334155;
            color: #64748b;
        }

        html.dark .dtbl tbody td {
            border-bottom-color: rgba(51, 65, 85, .4);
        }

        html.dark .dtbl tbody tr:hover {
            background: rgba(51, 65, 85, .25);
        }

        /* Timeline */
        .tl-item {
            position: relative;
            padding-left: 40px;
            padding-bottom: 20px;
        }

        .tl-item::before {
            content: '';
            position: absolute;
            left: 15px;
            top: 28px;
            bottom: 0;
            width: 2px;
            background: #E5EDF6;
        }

        .tl-item:last-child::before {
            display: none;
        }

        .tl-dot {
            position: absolute;
            left: 6px;
            top: 4px;
            width: 20px;
            height: 20px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 9px;
        }

        html.dark .tl-item::before {
            background: #334155;
        }

        /* Gradient text */
        .gtxt {
            background: linear-gradient(135deg, #2563eb, #14b8a6, #6366f1);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
        }

        .grad-text {
            background: linear-gradient(135deg, #2563eb 0%, #14b8a6 60%, #6366f1 100%);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
        }

        /* Fade in */
        @keyframes fadeIn {
            from {
                opacity: 0;
                transform: translateY(10px);
            }

            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        .fade-in {
            animation: fadeIn .4s ease forwards;
            opacity: 0;
        }

        /* Scrollbar */
        ::-webkit-scrollbar {
            width: 6px;
            height: 6px;
        }

        ::-webkit-scrollbar-track {
            background: transparent;
        }

        ::-webkit-scrollbar-thumb {
            background: #cbd5e1;
            border-radius: 999px;
        }

        html.dark ::-webkit-scrollbar-thumb {
            background: #475569;
        }

        .btn-grad {
            background: linear-gradient(135deg, #2563eb, #14b8a6);
            color: #ffffff;
            border: none;
            border-radius: 999px;
            padding: 10px 24px;
            font-weight: 600;
            font-size: 13px;
            cursor: pointer;
            transition: all 0.25s;
            display: inline-flex;
            align-items: center;
            gap: 8px;
        }

        .btn-grad:hover {
            opacity: 0.9;
            transform: translateY(-2px);
            box-shadow: 0 8px 20px rgba(37, 99, 235, 0.3);
        }

        /* Dropdown */
        .dropdown-menu {
            display: none;
            position: absolute;
            top: calc(100% + 8px);
            left: 0;
            background: #fff;
            border: 1px solid #e2e8f0;
            border-radius: 16px;
            box-shadow: 0 20px 60px rgba(15, 23, 42, .15);
            min-width: 220px;
            z-index: 50;
            overflow: hidden;
        }

        .dropdown-menu.show {
            display: block;
        }

        html.dark .dropdown-menu {
            background: #1e293b;
            border-color: #334155;
        }

        .dropdown-item {
            display: flex;
            align-items: center;
            gap: 10px;
            padding: 10px 16px;
            font-size: 13px;
            font-weight: 500;
            color: #475569;
            text-decoration: none;
            transition: all .15s;
        }

        html.dark .dropdown-item {
            color: #94a3b8;
        }

        .dropdown-item:hover {
            background: #f8fafc;
            color: #2563eb;
        }

        html.dark .dropdown-item:hover {
            background: rgba(51, 65, 85, .4);
            color: #60a5fa;
        }

        .dropdown-item i {
            width: 16px;
            text-align: center;
            font-size: 13px;
        }

        /* Profile popup */
        .profile-popup {
            display: none;
            position: absolute;
            top: calc(100% + 8px);
            right: 0;
            background: #fff;
            border: 1px solid #e2e8f0;
            border-radius: 16px;
            box-shadow: 0 20px 60px rgba(15, 23, 42, .15);
            min-width: 200px;
            z-index: 50;
            overflow: hidden;
        }

        .profile-popup.show {
            display: block;
        }

        html.dark .profile-popup {
            background: #1e293b;
            border-color: #334155;
        }

        /* Navbar scroll */
        #clientNav.scrolled {
            background: rgba(255, 255, 255, .95);
            backdrop-filter: blur(16px);
            box-shadow: 0 2px 20px rgba(0, 0, 0, .08);
        }

        html.dark #clientNav.scrolled {
            background: rgba(30, 41, 59, .95);
        }

        html.dark #clientNav {
            background: rgba(30, 41, 59, .8);
            border-color: #334155;
        }

        /* Stars */
        .stars {
            color: #f59e0b;
        }

        /* Active dropdown parent */
        .nav-active {
            color: #2563eb !important;
            background: #eff6ff !important;
        }

        html.dark .nav-active {
            color: #60a5fa !important;
            background: rgba(59, 130, 246, .15) !important;
        }
    </style>
</head>

<body class="min-h-screen">

    <!-- ═══════════════════════ TOP NAVBAR ═══════════════════════════ -->
    <nav id="clientNav" class="fixed top-0 inset-x-0 z-50 transition-all duration-300 py-2  backdrop-blur-md  border-gray-100">
        <div class="w-full mx-auto px-4 sm:px-6 flex items-center justify-between">
            <!-- Left: Logo + Nav Links -->
            <div class="flex items-center gap-6">
                <!-- Logo -->
                <a href="../index.php" class="flex items-center gap-1.5 group shrink-0">
                    <img src="../assets/upload/logos/logo.png" alt="Logo" class="w-[36px] h-[36px] rounded-xl">
                    <span class="text-lg font-extrabold tracking-tight">
                        <span class="text-gray-900 dark:text-white">Job</span><span class="grad-text">Hub</span>
                    </span>
                </a>

                <!-- Desktop Nav Links -->
                <div class="hidden lg:flex items-center gap-1">

                    <!-- Home -->
                    <a href="../index.php" class="flex items-center gap-1.5 px-3 py-2 rounded-lg text-sm font-semibold <?= $_ctbActive === 'home' ? 'nav-active' : 'text-gray-600 dark:text-slate-400 hover:bg-gray-50 dark:hover:bg-slate-800 hover:text-gray-900 dark:hover:text-white' ?> transition-all">
                        Home
                    </a>

                    <!-- Dashboard -->
                    <a href="dashboard.php" class="flex items-center gap-1.5 px-3 py-2 rounded-lg text-sm font-semibold <?= $_ctbActive === 'dashboard' ? 'nav-active' : 'text-gray-600 dark:text-slate-400 hover:bg-gray-50 dark:hover:bg-slate-800 hover:text-gray-900 dark:hover:text-white' ?> transition-all">
                        Dashboard
                    </a>

                    <!-- My Jobs Dropdown -->
                    <div class="relative" id="myJobsDropdown">
                        <button onmouseover="toggleDropdown('myJobsDropdown')" class="flex items-center gap-1.5 px-3 py-2 rounded-lg text-sm font-semibold <?= in_array($_ctbActive, ['my_jobs', 'post_job', 'proposals', 'job_detail', 'proposal_detail', 'invite_jobs']) ? 'nav-active' : 'text-gray-600 dark:text-slate-400 hover:bg-gray-50 dark:hover:bg-slate-800 hover:text-gray-900 dark:hover:text-white' ?> transition-all">
                            My Jobs <i data-lucide="chevron-down" class="w-4 h-4 ml-0.5"></i>
                        </button>
                        <div class="dropdown-menu">
                            <a href="my_jobs.php" class="dropdown-item <?= $_ctbActive === 'my_jobs' ? 'text-blue-600 bg-blue-50 dark:bg-blue-900/20 dark:text-blue-400' : '' ?>"><i data-lucide="briefcase" class="w-4 h-4"></i> My Jobs</a>
                            <a href="post_job.php" class="dropdown-item <?= $_ctbActive === 'post_job' ? 'text-blue-600 bg-blue-50 dark:bg-blue-900/20 dark:text-blue-400' : '' ?>"><i data-lucide="plus" class="w-4 h-4"></i> Post a Job</a>
                            <a href="proposals.php" class="dropdown-item <?= $_ctbActive === 'proposals' ? 'text-blue-600 bg-blue-50 dark:bg-blue-900/20 dark:text-blue-400' : '' ?>"><i data-lucide="file-text" class="w-4 h-4"></i> Proposals</a>
                            <a href="invite_jobs.php" class="dropdown-item <?= $_ctbActive === 'invite_jobs' ? 'text-blue-600 bg-blue-50 dark:bg-blue-900/20 dark:text-blue-400' : '' ?>"><i data-lucide="send" class="w-4 h-4"></i> Invite to Job</a>
                        </div>
                    </div>

                    <!-- Find Talent -->
                    <a href="recommended_freelancers.php" class="flex items-center gap-1.5 px-3 py-2 rounded-lg text-sm font-semibold <?= $_ctbActive === 'recommended_freelancers' ? 'nav-active' : 'text-gray-600 dark:text-slate-400 hover:bg-gray-50 dark:hover:bg-slate-800 hover:text-gray-900 dark:hover:text-white' ?> transition-all">
                        Find Talent
                    </a>

                    <!-- Work Management Dropdown -->
                    <div class="relative" id="workDropdown">
                        <button onmouseover="toggleDropdown('workDropdown')" class="flex items-center gap-1.5 px-3 py-2 rounded-lg text-sm font-semibold <?= in_array($_ctbActive, ['contracts', 'contract_detail', 'messages', 'reviews']) ? 'nav-active' : 'text-gray-600 dark:text-slate-400 hover:bg-gray-50 dark:hover:bg-slate-800 hover:text-gray-900 dark:hover:text-white' ?> transition-all">
                            Work Management <i data-lucide="chevron-down" class="w-4 h-4 ml-0.5"></i>
                        </button>
                        <div class="dropdown-menu">
                            <a href="contracts.php" class="dropdown-item <?= $_ctbActive === 'contracts' ? 'text-blue-600 bg-blue-50 dark:bg-blue-900/20 dark:text-blue-400' : '' ?>"><i data-lucide="file-text" class="w-4 h-4"></i> Contracts</a>
                            <a href="messages.php" class="dropdown-item <?= $_ctbActive === 'messages' ? 'text-blue-600 bg-blue-50 dark:bg-blue-900/20 dark:text-blue-400' : '' ?>"><i data-lucide="message-circle" class="w-4 h-4"></i> Messages <?php if ($_ctbUnread > 0): ?><span class="ml-auto w-5 h-5 bg-red-500 text-white text-[10px] font-bold rounded-full flex items-center justify-center"><?= $_ctbUnread > 9 ? '9+' : $_ctbUnread ?></span><?php endif; ?></a>
                            <a href="reviews.php" class="dropdown-item <?= $_ctbActive === 'reviews' ? 'text-blue-600 bg-blue-50 dark:bg-blue-900/20 dark:text-blue-400' : '' ?>"><i data-lucide="star" class="w-4 h-4"></i> Reviews</a>
                        </div>
                    </div>

                    <!-- Finances Dropdown -->
                    <div class="relative" id="financesDropdown">
                        <button onmouseover="toggleDropdown('financesDropdown')" class="flex items-center gap-1.5 px-3 py-2 rounded-lg text-sm font-semibold <?= in_array($_ctbActive, ['payment_history', 'wallet', 'wallet_history']) ? 'nav-active' : 'text-gray-600 dark:text-slate-400 hover:bg-gray-50 dark:hover:bg-slate-800 hover:text-gray-900 dark:hover:text-white' ?> transition-all">
                            Finances <i data-lucide="chevron-down" class="w-4 h-4 ml-0.5"></i>
                        </button>
                        <div class="dropdown-menu">
                            <a href="payment_history.php" class="dropdown-item <?= $_ctbActive === 'payment_history' ? 'text-blue-600 bg-blue-50 dark:bg-blue-900/20 dark:text-blue-400' : '' ?>"><i data-lucide="credit-card" class="w-4 h-4"></i> Payments</a>
                            <a href="wallet.php" class="dropdown-item <?= $_ctbActive === 'wallet' ? 'text-blue-600 bg-blue-50 dark:bg-blue-900/20 dark:text-blue-400' : '' ?>"><i data-lucide="wallet" class="w-4 h-4"></i> Wallet</a>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Right: Search, Wallet, Notifications, Dark Mode, Profile -->
            <div class="flex items-center gap-2">
                <!-- Search -->
                <div class="relative hidden md:block">
                    <i data-lucide="search" class="w-4 h-4 absolute left-3 top-1/2 -translate-y-1/2 text-gray-400 pointer-events-none"></i>
                    <input type="text" id="globalSearch" placeholder="Search jobs, freelancers..." oninput="handleSearch(this.value)"
                        class="w-52 lg:w-64 py-2 pl-9 pr-3 rounded-xl border border-gray-200 bg-gray-50 text-sm text-gray-700 outline-none focus:border-blue-500 focus:ring-2 focus:ring-blue-100 dark:bg-slate-900 dark:border-slate-600 dark:text-slate-300 transition-all">
                    <div id="searchResults" class="hidden absolute top-full left-0 right-0 mt-1 bg-white dark:bg-slate-800 border border-gray-200 dark:border-slate-600 rounded-xl shadow-lg z-50 max-h-64 overflow-y-auto"></div>
                </div>

                <?php if ($_ctbWalletBalance !== null): ?>
                    <!-- Wallet Balance -->
                    <a href="wallet.php" class="flex items-center gap-2 px-3 py-2 rounded-xl bg-gradient-to-r from-blue-50 to-teal-50 border border-blue-100 hover:from-blue-100 hover:to-teal-100 dark:from-blue-900/20 dark:to-teal-900/20 dark:border-blue-800/30 dark:hover:from-blue-900/30 dark:hover:to-teal-900/30 transition-all no-underline" title="View Wallet">
                        <i data-lucide="wallet" class="w-5 h-5 text-blue-600 dark:text-blue-400"></i>
                        <span class="text-sm font-bold text-blue-700 dark:text-blue-300" id="navWalletBalance">$<?= number_format($_ctbWalletBalance, 2) ?></span>
                    </a>
                <?php endif; ?>

                <!-- Notifications -->
                <a href="../shared/notifications_page.php" class="relative flex items-center gap-1.5 px-3 py-2 rounded-lg text-sm font-semibold text-gray-600 dark:text-slate-400 hover:bg-gray-50 dark:hover:bg-slate-800 hover:text-gray-900 dark:hover:text-white transition-all">
                    <i data-lucide="bell" class="w-4 h-4 hidden sm:inline"></i>
                    <?php if ($_ctbUnread > 0): ?>
                        <span class="absolute top-1 right-1 w-2 h-2 bg-red-500 rounded-full"></span>
                    <?php endif; ?>
                </a>

                <!-- Dark Mode Toggle -->
                <button onclick="toggleDarkMode()" class="w-10 h-10 rounded-xl flex items-center justify-center text-gray-500 hover:bg-gray-100 dark:text-slate-400 dark:hover:bg-slate-800 transition-all" title="Toggle dark mode">
                    <i data-lucide="moon" class="w-5 h-5" id="darkModeIcon"></i>
                </button>

                <!-- Mobile Hamburger -->
                <button onclick="toggleMobileMenu()" class="lg:hidden w-10 h-10 rounded-xl flex items-center justify-center text-gray-500 hover:bg-gray-100 dark:hover:bg-slate-800">
                    <i data-lucide="menu" class="w-6 h-6"></i>
                </button>

                <!-- Profile Avatar -->
                <div class="relative hidden lg:block" id="profileDropdown">
                    <button onclick="toggleDropdown('profileDropdown')" class="flex items-center gap-2.5 py-1.5 px-2 rounded-xl hover:bg-gray-100 dark:hover:bg-slate-800 transition-all">
                        <img src="<?= htmlspecialchars($_ctbAvatar) ?>" class="w-9 h-9 rounded-xl object-cover border-2 border-gray-100 dark:border-slate-600" alt="Avatar">
                        <!-- <span class="text-sm font-semibold text-gray-900 dark:text-white hidden sm:block max-w-[100px] truncate"><?= htmlspecialchars($_ctbName) ?></span> -->
                         <div>
                            <p class="text-sm font-bold text-gray-900 dark:text-white"><?= htmlspecialchars($_ctbName) ?></p>
                            <p class="text-xs text-gray-400 dark:text-slate-500 mt-0.5">Client</p>
                        </div>
                    </button>
                    <div class="profile-popup">
                        <div class="p-4 border-b border-gray-100 dark:border-slate-700">
                            <p class="text-sm font-bold text-gray-900 dark:text-white"><?= htmlspecialchars($_ctbName) ?></p>
                            <p class="text-xs text-gray-400 dark:text-slate-500 mt-0.5">Client</p>
                        </div>
                        <div class="py-1">
                            <a href="profile.php" class="dropdown-item"><i data-lucide="user" class="w-4 h-4"></i> My Profile</a>
                        </div>

                        <div class="border-t border-gray-100 dark:border-slate-700 py-1">
                            <a href="../auth/logout.php" class="dropdown-item text-red-500 hover:bg-red-50 dark:hover:bg-red-900/20"><i data-lucide="log-out" class="w-4 h-4"></i> Logout</a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </nav>

    <!-- Mobile Menu -->
    <div id="mobileOverlay" class="fixed inset-0 bg-black/40 z-40 hidden lg:hidden" onclick="toggleMobileMenu()"></div>
    <div id="mobileMenu" class="fixed top-0 left-0 w-80 h-full bg-white dark:bg-slate-800 z-50 transform -translate-x-full transition-transform duration-300 lg:hidden overflow-y-auto shadow-2xl">
        <div class="p-5 border-b border-gray-100 dark:border-slate-700 flex items-center justify-between">
            <a href="../index.php" class="flex items-center gap-2">
                <img src="../assets/upload/logos/logo.png" alt="Logo" class="w-8 h-8 rounded-lg">
                <span class="text-lg font-extrabold"><span class="text-gray-900 dark:text-white">Job</span><span class="grad-text">Hub</span></span>
            </a>
            <button onclick="toggleMobileMenu()" class="w-8 h-8 rounded-lg bg-gray-100 dark:bg-slate-700 flex items-center justify-center text-gray-400">
                <i data-lucide="x" class="w-4 h-4"></i>
            </button>
        </div>
        <div class="p-4 space-y-1">
            <p class="text-[10px] font-bold uppercase tracking-widest text-gray-400 dark:text-slate-500 px-3 mb-2">Main</p>
            <a href="dashboard.php" class="flex items-center gap-3 px-3 py-2.5 rounded-xl text-sm font-semibold <?= $_ctbActive === 'dashboard' ? 'bg-blue-50 dark:bg-blue-900/20 text-blue-600 dark:text-blue-400' : 'text-gray-600 dark:text-slate-400 hover:bg-gray-50 dark:hover:bg-slate-700' ?>"><i data-lucide="layout-grid" class="w-5 h-5"></i> Dashboard</a>

            <p class="text-[10px] font-bold uppercase tracking-widest text-gray-400 dark:text-slate-500 px-3 mb-2 mt-4">My Jobs</p>
            <a href="my_jobs.php" class="flex items-center gap-3 px-3 py-2.5 rounded-xl text-sm font-semibold <?= $_ctbActive === 'my_jobs' ? 'bg-blue-50 dark:bg-blue-900/20 text-blue-600 dark:text-blue-400' : 'text-gray-600 dark:text-slate-400 hover:bg-gray-50 dark:hover:bg-slate-700' ?>"><i data-lucide="briefcase" class="w-5 h-5"></i> My Jobs</a>
            <a href="post_job.php" class="flex items-center gap-3 px-3 py-2.5 rounded-xl text-sm font-semibold <?= $_ctbActive === 'post_job' ? 'bg-blue-50 dark:bg-blue-900/20 text-blue-600 dark:text-blue-400' : 'text-gray-600 dark:text-slate-400 hover:bg-gray-50 dark:hover:bg-slate-700' ?>"><i data-lucide="plus" class="w-5 h-5"></i> Post a Job</a>
            <a href="proposals.php" class="flex items-center gap-3 px-3 py-2.5 rounded-xl text-sm font-semibold <?= $_ctbActive === 'proposals' ? 'bg-blue-50 dark:bg-blue-900/20 text-blue-600 dark:text-blue-400' : 'text-gray-600 dark:text-slate-400 hover:bg-gray-50 dark:hover:bg-slate-700' ?>"><i data-lucide="file-text" class="w-5 h-5"></i> Proposals</a>
            <a href="invite_jobs.php" class="flex items-center gap-3 px-3 py-2.5 rounded-xl text-sm font-semibold <?= $_ctbActive === 'invite_jobs' ? 'bg-blue-50 dark:bg-blue-900/20 text-blue-600 dark:text-blue-400' : 'text-gray-600 dark:text-slate-400 hover:bg-gray-50 dark:hover:bg-slate-700' ?>"><i data-lucide="send" class="w-5 h-5"></i> Invite to Job</a>

            <p class="text-[10px] font-bold uppercase tracking-widest text-gray-400 dark:text-slate-500 px-3 mb-2 mt-4">Find Talent</p>
            <a href="recommended_freelancers.php" class="flex items-center gap-3 px-3 py-2.5 rounded-xl text-sm font-semibold <?= $_ctbActive === 'recommended_freelancers' ? 'bg-blue-50 dark:bg-blue-900/20 text-blue-600 dark:text-blue-400' : 'text-gray-600 dark:text-slate-400 hover:bg-gray-50 dark:hover:bg-slate-700' ?>"><i data-lucide="search" class="w-5 h-5"></i> Find Freelancers</a>

            <p class="text-[10px] font-bold uppercase tracking-widest text-gray-400 dark:text-slate-500 px-3 mb-2 mt-4">Work</p>
            <a href="contracts.php" class="flex items-center gap-3 px-3 py-2.5 rounded-xl text-sm font-semibold <?= $_ctbActive === 'contracts' ? 'bg-blue-50 dark:bg-blue-900/20 text-blue-600 dark:text-blue-400' : 'text-gray-600 dark:text-slate-400 hover:bg-gray-50 dark:hover:bg-slate-700' ?>"><i data-lucide="file-text" class="w-5 h-5"></i> Contracts</a>
            <a href="messages.php" class="flex items-center gap-3 px-3 py-2.5 rounded-xl text-sm font-semibold <?= $_ctbActive === 'messages' ? 'bg-blue-50 dark:bg-blue-900/20 text-blue-600 dark:text-blue-400' : 'text-gray-600 dark:text-slate-400 hover:bg-gray-50 dark:hover:bg-slate-700' ?>"><i data-lucide="message-circle" class="w-5 h-5"></i> Messages <?php if ($_ctbUnread > 0): ?><span class="ml-auto w-5 h-5 bg-red-500 text-white text-[10px] font-bold rounded-full flex items-center justify-center"><?= $_ctbUnread ?></span><?php endif; ?></a>
            <a href="reviews.php" class="flex items-center gap-3 px-3 py-2.5 rounded-xl text-sm font-semibold <?= $_ctbActive === 'reviews' ? 'bg-blue-50 dark:bg-blue-900/20 text-blue-600 dark:text-blue-400' : 'text-gray-600 dark:text-slate-400 hover:bg-gray-50 dark:hover:bg-slate-700' ?>"><i data-lucide="star" class="w-5 h-5"></i> Reviews</a>

            <p class="text-[10px] font-bold uppercase tracking-widest text-gray-400 dark:text-slate-500 px-3 mb-2 mt-4">Finances</p>
            <a href="payment_history.php" class="flex items-center gap-3 px-3 py-2.5 rounded-xl text-sm font-semibold <?= $_ctbActive === 'payment_history' ? 'bg-blue-50 dark:bg-blue-900/20 text-blue-600 dark:text-blue-400' : 'text-gray-600 dark:text-slate-400 hover:bg-gray-50 dark:hover:bg-slate-700' ?>"><i data-lucide="credit-card" class="w-5 h-5"></i> Payments</a>
            <a href="wallet.php" class="flex items-center gap-3 px-3 py-2.5 rounded-xl text-sm font-semibold <?= $_ctbActive === 'wallet' ? 'bg-blue-50 dark:bg-blue-900/20 text-blue-600 dark:text-blue-400' : 'text-gray-600 dark:text-slate-400 hover:bg-gray-50 dark:hover:bg-slate-700' ?>"><i data-lucide="wallet" class="w-5 h-5"></i> Wallet</a>

            <p class="text-[10px] font-bold uppercase tracking-widest text-gray-400 dark:text-slate-500 px-3 mb-2 mt-4">Account</p>
            <a href="profile.php" class="flex items-center gap-3 px-3 py-2.5 rounded-xl text-sm font-semibold <?= $_ctbActive === 'profile' ? 'bg-blue-50 dark:bg-blue-900/20 text-blue-600 dark:text-blue-400' : 'text-gray-600 dark:text-slate-400 hover:bg-gray-50 dark:hover:bg-slate-700' ?>"><i data-lucide="user" class="w-5 h-5"></i> My Profile</a>

            <div class="border-t border-gray-100 dark:border-slate-700 mt-4 pt-4">
                <a href="../auth/logout.php" class="flex items-center gap-3 px-3 py-2.5 rounded-xl text-sm font-semibold text-red-500 hover:bg-red-50 dark:hover:bg-red-900/20"><i data-lucide="log-out" class="w-5 h-5"></i> Logout</a>
            </div>
        </div>
    </div>

    <!-- Spacer for fixed navbar -->
    <div class="h-[72px]"></div>

    <script>
        function fixIcons() {
            lucide.createIcons();
            document.querySelectorAll('svg[data-lucide]').forEach(function(svg) {
                svg.removeAttribute('width');svg.removeAttribute('height');
                svg.style.removeProperty('width');svg.style.removeProperty('height');
                var p = svg.parentElement;
                if (p && p.tagName === 'I') { var fs = window.getComputedStyle(p).fontSize; svg.style.width = fs; svg.style.height = fs; }
            });
        }

        // ── Dropdown toggles ──
        function toggleDropdown(id) {
            const dd = document.getElementById(id);
            const menu = dd.querySelector('.dropdown-menu') || dd.querySelector('.profile-popup');
            if (!menu) return;
            const isOpen = menu.classList.contains('show');
            closeAllDropdowns();
            if (!isOpen) menu.classList.add('show');
        }

        function closeAllDropdowns() {
            document.querySelectorAll('.dropdown-menu, .profile-popup').forEach(m => m.classList.remove('show'));
        }

        document.addEventListener('click', function(e) {
            if (!e.target.closest('[id$="Dropdown"]')) closeAllDropdowns();
            const searchBox = document.getElementById('globalSearch');
            const results = document.getElementById('searchResults');
            if (searchBox && results && !searchBox.contains(e.target) && !results.contains(e.target)) {
                results.classList.add('hidden');
            }
        });

        // ── ESC closes menus ──
        document.addEventListener('keydown', function(e) {
            if (e.key === 'Escape') {
                closeAllDropdowns();
                var mm = document.getElementById('mobileMenu');
                var mo = document.getElementById('mobileOverlay');
                if (mm && !mm.classList.contains('-translate-x-full')) {
                    mm.classList.add('-translate-x-full');
                    mo.classList.add('hidden');
                }
            }
        });

        // ── Mobile menu ──
        function toggleMobileMenu() {
            document.getElementById('mobileMenu').classList.toggle('-translate-x-full');
            document.getElementById('mobileOverlay').classList.toggle('hidden');
        }

        // ── Dark mode ──
        function toggleDarkMode() {
            document.documentElement.classList.toggle('dark');
            localStorage.setItem('fh-dark-mode', document.documentElement.classList.contains('dark') ? '1' : '0');
            const icon = document.getElementById('darkModeIcon');
            if (icon) {
                const isDark = document.documentElement.classList.contains('dark');
                icon.setAttribute('data-lucide', isDark ? 'sun' : 'moon');
                fixIcons();
            }
        }

        // ── Apply saved dark mode ──
        (function() {
            var dark = localStorage.getItem('fh-dark-mode');
            if (dark === '1' || (!dark && window.matchMedia('(prefers-color-scheme: dark)').matches)) {
                document.documentElement.classList.add('dark');
                var icon = document.getElementById('darkModeIcon');
                if (icon) { icon.setAttribute('data-lucide', 'sun'); fixIcons(); }
            }
        })();

        // ── Scroll navbar ──
        window.addEventListener('scroll', () => {
            document.getElementById('clientNav').classList.toggle('scrolled', window.scrollY > 20);
        });

        // ── Global search ──
        function handleSearch(query) {
            const resultsDiv = document.getElementById('searchResults');
            if (!resultsDiv) return;
            query = query.trim().toLowerCase();
            if (query.length < 2) {
                resultsDiv.classList.add('hidden');
                return;
            }
            let html = '<div class="p-3 space-y-1">';
            html += '<a href="my_jobs.php?search=' + encodeURIComponent(query) + '" class="flex items-center gap-3 px-3 py-2 rounded-lg text-sm text-gray-700 dark:text-slate-300 hover:bg-blue-50 dark:hover:bg-slate-700 hover:text-blue-600 transition-colors"><svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="text-gray-400"><path d="M16 20V4a2 2 0 0 0-2-2h-4a2 2 0 0 0-2 2v16"></path><rect width="20" height="14" x="2" y="6" rx="2"></rect></svg> Search jobs for "' + query + '"</a>';
            html += '<a href="recommended_freelancers.php?search=' + encodeURIComponent(query) + '" class="flex items-center gap-3 px-3 py-2 rounded-lg text-sm text-gray-700 dark:text-slate-300 hover:bg-blue-50 dark:hover:bg-slate-700 hover:text-blue-600 transition-colors"><svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="text-gray-400"><path d="M19 21v-2a4 4 0 0 0-4-4H9a4 4 0 0 0-4 4v2"></path><circle cx="12" cy="7" r="4"></circle></svg> Search freelancers for "' + query + '"</a>';
            html += '</div>';
            resultsDiv.innerHTML = html;
            resultsDiv.classList.remove('hidden');
        }

        // ── Keyboard navigation for dropdowns ──
        document.querySelectorAll('[id$="Dropdown"] > button').forEach(function(btn) {
            btn.addEventListener('keydown', function(e) {
                if (e.key === 'Enter' || e.key === ' ') {
                    e.preventDefault();
                    btn.click();
                }
            });
        });
    </script>
    <script>fixIcons();</script>