<?php

/**
 * Freelancer Top Navigation Header
 * Modern Upwork/Fiverr-style top navigation with dropdowns.
 *
 * Required variables:
 *   $pageTitle (string)
 *   $activePage (string) - current page key
 *   $user (array) - ['name', 'profile_image']
 *   $unreadCount (int)
 */
$_fhTitle = $pageTitle ?? 'Dashboard';
$_fhActive = $activePage ?? 'home';
$_fhUser = $user ?? ['name' => 'Freelancer', 'profile_image' => ''];
$_fhAvatar = get_profile_image($_fhUser['profile_image'] ?? null);
$_fhUnread = $unreadCount ?? 0;
$_fhName = $_fhUser['name'] ?? 'Freelancer';
?>
<!DOCTYPE html>
<html lang="en" class="scroll-smooth">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width,initial-scale=1.0">
    <title><?= htmlspecialchars($_fhTitle) ?> – JobHub</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800;900&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="/jobhub/assets/css/theme.css">
    <script src="https://unpkg.com/lucide@0.344.0/dist/umd/lucide.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js"></script>
    <link rel="icon" type="image/png" sizes="32x32" href="../assets/upload/logos/logo.png">
    <link rel="stylesheet" href="/jobhub/shared/dark-mode.css">
    <script>
        // Apply saved dark mode preference immediately (before paint)
        (function() {
            var dark = localStorage.getItem('fh-dark-mode');
            if (dark === '1' || dark === 'dark' || (!dark && window.matchMedia('(prefers-color-scheme: dark)').matches)) {
                document.documentElement.classList.add('dark');
            }
        })();
    </script>
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
                            DEFAULT: '#4338CA',
                            dark: '#3730A3',
                            light: '#6366F1'
                        },
                        accent: {
                            DEFAULT: '#6366F1',
                            dark: '#4F46E5'
                        },
                        surface: {
                            DEFAULT: '#f8fafc',
                            card: '#ffffff',
                            border: '#e2e8f0'
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
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            margin: 0;
            -webkit-font-smoothing: antialiased;
            -moz-osx-font-smoothing: grayscale;
            letter-spacing: 0.01em;
        }

        html.dark body {
            background: #0f172a;
            color: #e2e8f0;
        }

        h1, h2, h3, h4, h5, h6 {
            letter-spacing: -0.02em;
            font-feature-settings: 'cv11', 'ss01';
        }

        .grad-text {
            background: linear-gradient(135deg, #4338CA 0%, #6366F1 60%, #818CF8 100%);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
        }

        .btn-grad {
            background: #4338CA;
            color: #fff;
            border: none;
            border-radius: 12px;
            padding: 10px 24px;
            font-weight: 600;
            font-size: 13px;
            cursor: pointer;
            transition: all .25s;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            letter-spacing: 0.02em;
        }

        .btn-grad:hover {
            background: #3730A3;
            transform: translateY(-1px);
        }

        /* Nav link hover */
        .nav-link {
            position: relative;
        }

        .nav-link::after {
            content: '';
            position: absolute;
            bottom: -2px;
            left: 0;
            width: 0;
            height: 2px;
            background: linear-gradient(90deg, #4338CA, #6366F1);
            transition: width .3s ease;
        }

        .nav-link:hover::after {
            width: 100%;
        }

        /* Dashboard card */
        .dh-card {
            background: #fff;
            border: 1px solid #e2e8f0;
            border-radius: 20px;
            box-shadow: 0 1px 3px rgba(0, 0, 0, .05);
            transition: box-shadow .2s, transform .2s;
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
            border: 1px solid #e2e8f0;
            border-radius: 16px;
            padding: 20px;
            transition: all .2s;
        }

        .stat-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(15, 23, 42, .07);
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

        /* Progress bar */
        .pbar {
            width: 100%;
            height: 6px;
            background: #e2e8f0;
            border-radius: 999px;
            overflow: hidden;
        }

        .pbar-fill {
            height: 100%;
            border-radius: 999px;
            background: linear-gradient(90deg, #4338CA, #6366F1);
        }

        .pbar-fill.green {
            background: linear-gradient(90deg, #059669, #10b981);
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
            border-bottom: 1px solid #e2e8f0;
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

        /* Gradient text */
        .gtxt {
            background: linear-gradient(135deg, #4338CA, #6366F1, #818CF8);
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

        /* Job card hover */
        .job-card {
            transition: all .3s ease;
        }

        .job-card:hover {
            transform: translateY(-4px);
            border-color: #6366F1;
            box-shadow: 0 20px 50px -12px rgba(99, 102, 241, .08);
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

        .dropdown-item:hover {
            background: #f8fafc;
            color: #4338CA;
        }

        .dropdown-item i,
        .dropdown-item svg {
            width: 16px;
            height: 16px;
            flex-shrink: 0;
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

        /* Navbar scroll */
        #freelancerNav.scrolled {
            background: rgba(255, 255, 255, .95);
            backdrop-filter: blur(16px);
            box-shadow: 0 2px 20px rgba(0, 0, 0, .08);
        }

        /* Stars */
        .stars {
            color: #f59e0b;
        }

        /* ═══ LUCIDE ICON SIZING FIX ═══════════════════════════════════════ */
        svg[data-lucide] {
            width: 1em !important;
            height: 1em !important;
            font-size: inherit !important;
            flex-shrink: 0;
        }
        i[data-lucide] {
            display: inline-flex;
            align-items: center;
            line-height: 0;
        }
    </style>
</head>

<body class="min-h-screen">

    <!-- ═══════════════════════ TOP NAVBAR ═══════════════════════════ -->
    <nav id="freelancerNav" class="fixed top-0 inset-x-0 z-50 transition-all duration-300 py-2 bg-white/80 backdrop-blur-md border-b border-gray-100">
        <div class="w-full mx-auto px-4 sm:px-6 flex items-center justify-between">
            <!-- Left: Logo + Nav Links -->
            <div class="flex items-center gap-8">
                <!-- Logo -->
                <a href="../index.php" class="flex items-center gap-1.5 group shrink-0">
                    <img src="../assets/upload/logos/logo.png" alt="Logo" class="w-[36px] h-[36px] rounded-xl">
                    <span class="text-lg font-extrabold tracking-tight">
                        <span class="text-gray-900">Job</span><span class="grad-text">Hub</span>
                    </span>
                </a>

                <!-- Desktop Nav Links -->
                <div class="hidden lg:flex items-center gap-1">

                    <a href="../index.php" class="flex items-center gap-1.5 px-3 py-2 rounded-lg text-sm font-semibold <?= $_fhActive === 'dashboard' ? 'text-[#4338CA] bg-indigo-50' : 'text-gray-600 hover:bg-gray-50 hover:text-gray-900' ?> transition-all">
                        Home</a>

                    <a href="home.php" class="flex items-center gap-1.5 px-3 py-2 rounded-lg text-sm font-semibold <?= $_fhActive === 'home' ? 'text-[#4338CA] bg-indigo-50' : 'text-gray-600 hover:bg-gray-50 hover:text-gray-900' ?> transition-all">
                        Dashboard</a>
                    <!-- Find Work Dropdown -->
                    <div class="relative" id="findWorkDropdown">
                        <button onclick="toggleDropdown('findWorkDropdown')" class="flex items-center gap-1.5 px-3 py-2 rounded-lg text-sm font-semibold <?= in_array($_fhActive, ['browse_jobs', 'job_detail', 'recommended_jobs', 'saved_jobs', 'invited_jobs', 'proposals']) ? 'text-[#4338CA] bg-indigo-50' : 'text-gray-600 hover:bg-gray-50 hover:text-gray-900' ?> transition-all">
                            Find Work <i data-lucide="chevron-down" class="w-4 h-4 ml-0.5"></i>
                        </button>
                        <div class="dropdown-menu">
                            <a href="browse_jobs.php" class="dropdown-item <?= $_fhActive === 'browse_jobs' ? 'text-[#4338CA] bg-indigo-50' : '' ?>"><i data-lucide="search" class="text-base"></i> Browse Jobs</a>
                            <a href="home.php#best" class="dropdown-item "><i data-lucide="wand-2" class="text-base"></i> AI Job Matches</a>
                            <a href="home.php#saved" class="dropdown-item"><i data-lucide="bookmark" class="text-base"></i> Saved Jobs</a>
                            <a href="home.php#invited" class="dropdown-item"><i data-lucide="mail-open" class="text-base"></i> Invited Jobs</a>
                            <div class="border-t border-gray-100 my-1"></div>
                            <a href="proposals.php" class="dropdown-item <?= $_fhActive === 'proposals' ? 'text-[#4338CA] bg-indigo-50' : '' ?>"><i data-lucide="file-text" class="text-base"></i> My Proposals</a>
                        </div>
                    </div>

                    <!-- Deliver Work Dropdown -->
                    <div class="relative" id="deliverWorkDropdown">
                        <button onclick="toggleDropdown('deliverWorkDropdown')" class="flex items-center gap-1.5 px-3 py-2 rounded-lg text-sm font-semibold <?= in_array($_fhActive, ['contracts', 'contract_detail', 'milestones', 'submitted_work', 'contract_history']) ? 'text-[#4338CA] bg-indigo-50' : 'text-gray-600 hover:bg-gray-50 hover:text-gray-900' ?> transition-all">
                            Deliver Work <i data-lucide="chevron-down" class="w-4 h-4 ml-0.5"></i>
                        </button>
                        <div class="dropdown-menu">
                            <a href="contracts.php?status=active" class="dropdown-item <?= $_fhActive === 'contracts' ? 'text-[#4338CA] bg-indigo-50' : '' ?>"><i data-lucide="file-text" class="text-base"></i> Active Contracts</a>
                            <!-- <a href="contract_detail.php" class="dropdown-item"><i data-lucide="list-checks" class="text-base"></i> My Milestones</a> -->
                            <a href="contracts.php?status=completed" class="dropdown-item"><i data-lucide="send" class="text-base"></i> Submitted Work</a>
                            <a href="contracts.php?view=history" class="dropdown-item"><i data-lucide="history" class="text-base"></i> Contract History</a>
                        </div>
                    </div>

                    <!-- Manage Finances Dropdown -->
                    <div class="relative" id="financesDropdown">
                        <button onclick="toggleDropdown('financesDropdown')" class="flex items-center gap-1.5 px-3 py-2 rounded-lg text-sm font-semibold <?= in_array($_fhActive, ['earnings', 'payments', 'transactions', 'reviews']) ? 'text-[#4338CA] bg-indigo-50' : 'text-gray-600 hover:bg-gray-50 hover:text-gray-900' ?> transition-all">
                            Manage Finances <i data-lucide="chevron-down" class="w-4 h-4 ml-0.5"></i>
                        </button>
                        <div class="dropdown-menu">
                            <a href="earnings.php" class="dropdown-item <?= $_fhActive === 'earnings' ? 'text-[#4338CA] bg-indigo-50' : '' ?>"><i data-lucide="trending-up" class="text-base"></i> Financial Overview</a>
                            <!-- <a href="earnings.php#earnings" class="dropdown-item"><i data-lucide="dollar-sign" class="text-base"></i> Earnings</a>
                            <a href="earnings.php#transactions" class="dropdown-item"><i data-lucide="receipt" class="text-base"></i> Transactions</a> -->
                            <a href="reviews.php" class="dropdown-item <?= $_fhActive === 'reviews' ? 'text-[#4338CA] bg-indigo-50' : '' ?>"><i data-lucide="star" class="text-base"></i> Reviews</a>
                        </div>
                    </div>

                    <!-- Messages -->
                    <a href="messages.php" class="flex items-center gap-1.5 px-3 py-2 rounded-lg text-sm font-semibold <?= $_fhActive === 'messages' ? 'text-[#4338CA] bg-indigo-50' : 'text-gray-600 hover:bg-gray-50 hover:text-gray-900' ?> transition-all">
                        Messages
                        <?php if ($_fhUnread > 0): ?>
                            <span class="w-5 h-5 bg-red-500 text-white text-[10px] font-bold rounded-full flex items-center justify-center"><?= $_fhUnread > 9 ? '9+' : $_fhUnread ?></span>
                        <?php endif; ?>
                    </a>

                    <!-- Notifications -->
                    <!-- <a href="../shared/notifications.php" class="relative flex items-center gap-1.5 px-3 py-2 rounded-lg text-sm font-semibold text-gray-600 hover:bg-gray-50 hover:text-gray-900 transition-all">
                    <i data-lucide="bell" class="text-base hidden sm:inline"></i>
                    <?php if ($_fhUnread > 0): ?>
                        <span class="absolute top-1 right-1 w-2 h-2 bg-red-500 rounded-full"></span>
                    <?php endif; ?>
                </a> -->
                </div>
            </div>

            <!-- Right: Search, Dark Mode, Profile -->
            <div class="flex items-center gap-2">
                <!-- Search -->
                <div class="relative hidden md:block">
                    <i data-lucide="search" class="text-base absolute left-3 top-1/2 -translate-y-1/2 text-gray-400 pointer-events-none"></i>
                    <input type="text" id="globalSearch" placeholder="Search jobs, skills..." oninput="handleSearch(this.value)"
                        class="w-52 lg:w-64 py-2 pl-9 pr-3 rounded-xl border border-gray-200 bg-gray-50 text-sm text-gray-700 outline-none focus:border-[#4338CA] focus:ring-2 focus:ring-indigo-100 transition-all">
                    <div id="searchResults" class="hidden absolute top-full left-0 right-0 mt-1 bg-white border border-gray-200 rounded-xl shadow-lg z-50 max-h-64 overflow-y-auto"></div>
                </div>

                <!-- Notifications -->
                <a href="../shared/notifications_page.php" class="relative flex items-center gap-1.5 px-3 py-2 rounded-lg text-sm font-semibold text-gray-600 hover:bg-gray-50 hover:text-gray-900 transition-all">
                    <i data-lucide="bell" class="text-base hidden sm:inline"></i>
                    <?php if ($_fhUnread > 0): ?>
                        <span class="absolute top-1 right-1 w-2 h-2 bg-red-500 rounded-full"></span>
                    <?php endif; ?>
                </a>

                <!-- Dark Mode Toggle -->
                <button onclick="toggleDarkMode()" class="w-10 h-10 rounded-xl flex items-center justify-center text-gray-500 hover:bg-gray-100 transition-all" title="Toggle dark mode">
                    <i data-lucide="moon" class="text-base" id="darkModeIcon"></i>
                </button>

                <!-- Mobile Hamburger -->
                <button onclick="toggleMobileMenu()" class="lg:hidden w-10 h-10 rounded-xl flex items-center justify-center text-gray-500 hover:bg-gray-100">
                    <i data-lucide="menu" class="text-xl"></i>
                </button>

                <!-- Profile Avatar -->
                <div class="relative hidden lg:block" id="profileDropdown">
                    <button onclick="toggleDropdown('profileDropdown')" class="flex items-center gap-2.5 py-1.5 px-2 rounded-xl hover:bg-gray-100 transition-all">
                        <img src="<?= htmlspecialchars($_fhAvatar) ?>" class="w-9 h-9 rounded-xl object-cover border-2 border-gray-100" alt="Avatar">
                        <!-- <p class="text-sm font-semibold text-gray-900 hidden sm:block max-w-[100px] truncate"><?= htmlspecialchars($_fhName) ?></p> -->
                        <div>
                            <p class="text-sm font-bold text-gray-900"><?= htmlspecialchars($_fhName) ?></p>
                            <p class="text-xs text-gray-400 mt-0.5">Freelancer</p>
                        </div>
                    </button>
                    <div class="profile-popup">
                        <div class="p-4 border-b border-gray-100">
                            <p class="text-sm font-bold text-gray-900"><?= htmlspecialchars($_fhName) ?></p>
                            <p class="text-xs text-gray-400 mt-0.5">Freelancer</p>
                        </div>
                        <div class="py-1">
                            <a href="profile.php" class="dropdown-item"><i data-lucide="user" class="text-base"></i> My Profile</a>
                            <a href="profile_edit.php" class="dropdown-item"><i data-lucide="image" class="text-base"></i> Portfolio</a>
                        </div>
                        <div class="border-t border-gray-100 py-1">
                            <a href="../auth/logout.php" class="flex items-center gap-3 px-4 py-2.5 text-sm text-red-500 hover:bg-red-50 transition-colors"><i data-lucide="log-out" class="text-base"></i> Logout</a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </nav>

    <!-- Mobile Menu -->
    <div id="mobileOverlay" class="fixed inset-0 bg-black/40 z-40 hidden lg:hidden" onclick="toggleMobileMenu()"></div>
    <div id="mobileMenu" class="fixed top-0 left-0 w-80 h-full bg-white z-50 transform -translate-x-full transition-transform duration-300 lg:hidden overflow-y-auto shadow-2xl">
        <div class="p-5 border-b border-gray-100 flex items-center justify-between">
            <a href="../index.php" class="flex items-center gap-2">
                <img src="../assets/upload/logos/logo.png" alt="Logo" class="w-8 h-8 rounded-lg">
                <span class="text-lg font-extrabold"><span class="text-gray-900">Job</span><span class="grad-text">Hub</span></span>
            </a>
            <button onclick="toggleMobileMenu()" class="w-8 h-8 rounded-lg bg-gray-100 flex items-center justify-center text-gray-400">
                <i data-lucide="x" class="text-base"></i>
            </button>
        </div>
        <div class="p-4 space-y-1">
            <a href="home.php" class="flex items-center gap-3 px-3 py-2.5 rounded-xl text-sm font-semibold <?= $_fhActive === 'home' ? 'bg-indigo-50 text-[#4338CA]' : 'text-gray-600 hover:bg-gray-50' ?>"><i data-lucide="home" class="text-xl"></i> Home</a>
            <p class="text-[10px] font-bold uppercase tracking-widest text-gray-400 px-3 mb-2">Find Work</p>
            <!-- <a href="home.php" class="flex items-center gap-3 px-3 py-2.5 rounded-xl text-sm font-semibold <?= $_fhActive === 'home' ? 'bg-blue-50 text-blue-600' : 'text-gray-600 hover:bg-gray-50' ?>"><i data-lucide="home" class="text-xl"></i> Home</a> -->
            <a href="browse_jobs.php" class="flex items-center gap-3 px-3 py-2.5 rounded-xl text-sm font-semibold <?= $_fhActive === 'browse_jobs' ? 'bg-indigo-50 text-[#4338CA]' : 'text-gray-600 hover:bg-gray-50' ?>"><i data-lucide="search" class="text-xl"></i> Browse Jobs</a>
            <a href="recommended_jobs.php" class="flex items-center gap-3 px-3 py-2.5 rounded-xl text-sm font-semibold <?= $_fhActive === 'recommended_jobs' ? 'bg-indigo-50 text-[#4338CA]' : 'text-gray-600 hover:bg-gray-50' ?>"><i data-lucide="wand-2" class="text-xl"></i> AI Job Matches</a>
            <a href="proposals.php" class="flex items-center gap-3 px-3 py-2.5 rounded-xl text-sm font-semibold <?= $_fhActive === 'proposals' ? 'bg-indigo-50 text-[#4338CA]' : 'text-gray-600 hover:bg-gray-50' ?>"><i data-lucide="file-text" class="text-xl"></i> My Proposals</a>

            <p class="text-[10px] font-bold uppercase tracking-widest text-gray-400 px-3 mb-2 mt-4">Deliver Work</p>
            <a href="contracts.php" class="flex items-center gap-3 px-3 py-2.5 rounded-xl text-sm font-semibold <?= $_fhActive === 'contracts' ? 'bg-indigo-50 text-[#4338CA]' : 'text-gray-600 hover:bg-gray-50' ?>"><i data-lucide="file-text" class="text-xl"></i> Active Contracts</a>

            <p class="text-[10px] font-bold uppercase tracking-widest text-gray-400 px-3 mb-2 mt-4">Finances</p>
            <a href="earnings.php" class="flex items-center gap-3 px-3 py-2.5 rounded-xl text-sm font-semibold <?= $_fhActive === 'earnings' ? 'bg-indigo-50 text-[#4338CA]' : 'text-gray-600 hover:bg-gray-50' ?>"><i data-lucide="wallet" class="text-xl"></i> Earnings</a>
            <a href="reviews.php" class="flex items-center gap-3 px-3 py-2.5 rounded-xl text-sm font-semibold <?= $_fhActive === 'reviews' ? 'bg-indigo-50 text-[#4338CA]' : 'text-gray-600 hover:bg-gray-50' ?>"><i data-lucide="star" class="text-xl"></i> Reviews</a>

            <p class="text-[10px] font-bold uppercase tracking-widest text-gray-400 px-3 mb-2 mt-4">Account</p>
            <a href="messages.php" class="flex items-center gap-3 px-3 py-2.5 rounded-xl text-sm font-semibold <?= $_fhActive === 'messages' ? 'bg-indigo-50 text-[#4338CA]' : 'text-gray-600 hover:bg-gray-50' ?>"><i data-lucide="message-circle" class="text-xl"></i> Messages <?php if ($_fhUnread > 0): ?><span class="ml-auto w-5 h-5 bg-red-500 text-white text-[10px] font-bold rounded-full flex items-center justify-center"><?= $_fhUnread ?></span><?php endif; ?></a>
            <a href="profile.php" class="flex items-center gap-3 px-3 py-2.5 rounded-xl text-sm font-semibold <?= $_fhActive === 'profile' ? 'bg-indigo-50 text-[#4338CA]' : 'text-gray-600 hover:bg-gray-50' ?>"><i data-lucide="user" class="text-xl"></i> My Profile</a>
            <a href="profile_edit.php" class="flex items-center gap-3 px-3 py-2.5 rounded-xl text-sm font-semibold text-gray-600 hover:bg-gray-50"><i data-lucide="pencil" class="text-xl"></i> Edit Profile</a>

            <div class="border-t border-gray-100 mt-4 pt-4">
                <a href="../auth/logout.php" class="flex items-center gap-3 px-3 py-2.5 rounded-xl text-sm font-semibold text-red-500 hover:bg-red-50"><i data-lucide="log-out" class="text-xl"></i> Logout</a>
            </div>
        </div>
    </div>

    <!-- Spacer for fixed navbar -->
    <div class="h-[60px]"></div>

    <script>
        function fixIcons() {
            lucide.createIcons();
            document.querySelectorAll('svg[data-lucide]').forEach(function(svg) {
                svg.removeAttribute('width');
                svg.removeAttribute('height');
                svg.style.removeProperty('width');
                svg.style.removeProperty('height');
                var p = svg.parentElement;
                if (p && p.tagName === 'I') {
                    var fs = window.getComputedStyle(p).fontSize;
                    svg.style.width = fs;
                    svg.style.height = fs;
                }
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
                icon.setAttribute('data-lucide', document.documentElement.classList.contains('dark') ? 'sun' : 'moon');
                fixIcons();
            }
        }

        // ── Scroll navbar ──
        window.addEventListener('scroll', () => {
            document.getElementById('freelancerNav').classList.toggle('scrolled', window.scrollY > 20);
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
            // Simple client-side search on visible elements
            let html = '<div class="p-3 space-y-1">';
            html += '<a href="browse_jobs.php?search=' + encodeURIComponent(query) + '" class="flex items-center gap-3 px-3 py-2 rounded-lg text-sm text-gray-700 hover:bg-indigo-50 hover:text-[#4338CA] transition-colors"><i data-lucide="briefcase" class="text-base text-gray-400"></i> Search jobs for "' + query + '"</a>';
            html += '<a href="browse_jobs.php?skills[]=&search=' + encodeURIComponent(query) + '" class="flex items-center gap-3 px-3 py-2 rounded-lg text-sm text-gray-700 hover:bg-indigo-50 hover:text-[#4338CA] transition-colors"><i data-lucide="tags" class="text-base text-gray-400"></i> Search skills for "' + query + '"</a>';
            html += '</div>';
            resultsDiv.innerHTML = html;
            resultsDiv.classList.remove('hidden');
            fixIcons();
        }

        fixIcons();
    </script>
