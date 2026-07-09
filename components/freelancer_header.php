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
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js"></script>
    <link rel="icon" type="image/png" sizes="32x32" href="../assets/upload/logos/logo.png">
    <link rel="stylesheet" href="/finalproject/shared/dark-mode.css">
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
                            DEFAULT: '#2563eb',
                            dark: '#1d4ed8',
                            light: '#3b82f6'
                        },
                        accent: {
                            DEFAULT: '#0ea5e9',
                            dark: '#0284c7'
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
            font-family: 'Inter', sans-serif;
            background: #f8fafc;
            color: #1e293b;
            margin: 0;
        }

        html.dark body {
            background: #0f172a;
            color: #e2e8f0;
        }

        .grad-text {
            background: linear-gradient(135deg, #2563eb 0%, #0ea5e9 60%, #6366f1 100%);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
        }

        .btn-grad {
            background: linear-gradient(135deg, #2563eb, #0ea5e9);
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
        }

        .btn-grad:hover {
            opacity: .9;
            transform: translateY(-2px);
            box-shadow: 0 8px 20px rgba(37, 99, 235, .3);
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
            background: linear-gradient(90deg, #2563eb, #0ea5e9);
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
            background: linear-gradient(90deg, #2563eb, #3b82f6);
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
            background: linear-gradient(135deg, #2563eb, #0ea5e9, #6366f1);
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
            border-color: #0ea5e9;
            box-shadow: 0 10px 40px rgba(14, 165, 233, .12);
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
            color: #2563eb;
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
    </style>
</head>

<body class="min-h-screen">

    <!-- ═══════════════════════ TOP NAVBAR ═══════════════════════════ -->
    <nav id="freelancerNav" class="fixed top-0 inset-x-0 z-50 transition-all duration-300 py-3 bg-white/80 backdrop-blur-md border-b border-gray-100">
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
                    <!-- Find Work Dropdown -->
                    <div class="relative" id="findWorkDropdown">
                        <button onclick="toggleDropdown('findWorkDropdown')" class="flex items-center gap-1.5 px-3 py-2 rounded-lg text-sm font-semibold <?= in_array($_fhActive, ['home', 'browse_jobs', 'job_detail', 'recommended_jobs', 'saved_jobs', 'invited_jobs', 'proposals']) ? 'text-blue-600 bg-blue-50' : 'text-gray-600 hover:bg-gray-50 hover:text-gray-900' ?> transition-all">
                            Find Work <i class="fas fa-chevron-down text-[10px] ml-0.5"></i>
                        </button>
                        <div class="dropdown-menu">
                            <a href="../index.php" class="dropdown-item <?= $_fhActive === 'home' ? 'text-blue-600 bg-blue-50' : '' ?>"><i class="fas fa-home"></i> Home</a>
                            <a href="browse_jobs.php" class="dropdown-item <?= $_fhActive === 'browse_jobs' ? 'text-blue-600 bg-blue-50' : '' ?>"><i class="fas fa-search"></i> Browse Jobs</a>
                            <a href="recommended_jobs.php" class="dropdown-item <?= $_fhActive === 'recommended_jobs' ? 'text-blue-600 bg-blue-50' : '' ?>"><i class="fas fa-magic"></i> AI Job Matches</a>
                            <a href="browse_jobs.php?saved=1" class="dropdown-item"><i class="fas fa-bookmark"></i> Saved Jobs</a>
                            <a href="browse_jobs.php?invited=1" class="dropdown-item"><i class="fas fa-envelope-open"></i> Invited Jobs</a>
                            <div class="border-t border-gray-100 my-1"></div>
                            <a href="proposals.php" class="dropdown-item <?= $_fhActive === 'proposals' ? 'text-blue-600 bg-blue-50' : '' ?>"><i class="fas fa-file-alt"></i> My Proposals</a>
                        </div>
                    </div>

                    <!-- Deliver Work Dropdown -->
                    <div class="relative" id="deliverWorkDropdown">
                        <button onclick="toggleDropdown('deliverWorkDropdown')" class="flex items-center gap-1.5 px-3 py-2 rounded-lg text-sm font-semibold <?= in_array($_fhActive, ['contracts', 'contract_detail', 'milestones', 'submitted_work', 'contract_history']) ? 'text-blue-600 bg-blue-50' : 'text-gray-600 hover:bg-gray-50 hover:text-gray-900' ?> transition-all">
                            Deliver Work <i class="fas fa-chevron-down text-[10px] ml-0.5"></i>
                        </button>
                        <div class="dropdown-menu">
                            <a href="contracts.php" class="dropdown-item <?= $_fhActive === 'contracts' ? 'text-blue-600 bg-blue-50' : '' ?>"><i class="fas fa-file-contract"></i> Active Contracts</a>
                            <a href="contract_detail.php" class="dropdown-item"><i class="fas fa-tasks"></i> My Milestones</a>
                            <a href="contracts.php?view=submitted" class="dropdown-item"><i class="fas fa-paper-plane"></i> Submitted Work</a>
                            <a href="contracts.php?view=history" class="dropdown-item"><i class="fas fa-history"></i> Contract History</a>
                        </div>
                    </div>

                    <!-- Manage Finances Dropdown -->
                    <div class="relative" id="financesDropdown">
                        <button onclick="toggleDropdown('financesDropdown')" class="flex items-center gap-1.5 px-3 py-2 rounded-lg text-sm font-semibold <?= in_array($_fhActive, ['earnings', 'payments', 'transactions', 'reviews']) ? 'text-blue-600 bg-blue-50' : 'text-gray-600 hover:bg-gray-50 hover:text-gray-900' ?> transition-all">
                            Manage Finances <i class="fas fa-chevron-down text-[10px] ml-0.5"></i>
                        </button>
                        <div class="dropdown-menu">
                            <a href="earnings.php" class="dropdown-item <?= $_fhActive === 'earnings' ? 'text-blue-600 bg-blue-50' : '' ?>"><i class="fas fa-chart-line"></i> Financial Overview</a>
                            <a href="earnings.php#earnings" class="dropdown-item"><i class="fas fa-dollar-sign"></i> Earnings</a>
                            <a href="earnings.php#transactions" class="dropdown-item"><i class="fas fa-receipt"></i> Transactions</a>
                            <a href="reviews.php" class="dropdown-item <?= $_fhActive === 'reviews' ? 'text-blue-600 bg-blue-50' : '' ?>"><i class="fas fa-star"></i> Reviews</a>
                        </div>
                    </div>

                    <!-- Messages -->
                    <a href="messages.php" class="flex items-center gap-1.5 px-3 py-2 rounded-lg text-sm font-semibold <?= $_fhActive === 'messages' ? 'text-blue-600 bg-blue-50' : 'text-gray-600 hover:bg-gray-50 hover:text-gray-900' ?> transition-all">
                        Messages
                        <?php if ($_fhUnread > 0): ?>
                            <span class="w-5 h-5 bg-red-500 text-white text-[10px] font-bold rounded-full flex items-center justify-center"><?= $_fhUnread > 9 ? '9+' : $_fhUnread ?></span>
                        <?php endif; ?>
                    </a>

                    <!-- Notifications -->
                    <!-- <a href="../shared/notifications.php" class="relative flex items-center gap-1.5 px-3 py-2 rounded-lg text-sm font-semibold text-gray-600 hover:bg-gray-50 hover:text-gray-900 transition-all">
                    <i class="fas fa-bell text-[13px] hidden sm:inline"></i>
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
                    <i class="fas fa-search absolute left-3 top-1/2 -translate-y-1/2 text-gray-400 text-sm pointer-events-none"></i>
                    <input type="text" id="globalSearch" placeholder="Search jobs, skills..." oninput="handleSearch(this.value)"
                        class="w-52 lg:w-64 py-2 pl-9 pr-3 rounded-xl border border-gray-200 bg-gray-50 text-sm text-gray-700 outline-none focus:border-blue-500 focus:ring-2 focus:ring-blue-100 transition-all">
                    <div id="searchResults" class="hidden absolute top-full left-0 right-0 mt-1 bg-white border border-gray-200 rounded-xl shadow-lg z-50 max-h-64 overflow-y-auto"></div>
                </div>

                <!-- Notifications -->
                <a href="../shared/notifications.php" class="relative flex items-center gap-1.5 px-3 py-2 rounded-lg text-sm font-semibold text-gray-600 hover:bg-gray-50 hover:text-gray-900 transition-all">
                    <i class="fas fa-bell text-[13px] hidden sm:inline"></i>
                    <?php if ($_fhUnread > 0): ?>
                        <span class="absolute top-1 right-1 w-2 h-2 bg-red-500 rounded-full"></span>
                    <?php endif; ?>
                </a>

                <!-- Dark Mode Toggle -->
                <button onclick="toggleDarkMode()" class="w-10 h-10 rounded-xl flex items-center justify-center text-gray-500 hover:bg-gray-100 transition-all" title="Toggle dark mode">
                    <i class="fas fa-moon text-sm" id="darkModeIcon"></i>
                </button>

                <!-- Mobile Hamburger -->
                <button onclick="toggleMobileMenu()" class="lg:hidden w-10 h-10 rounded-xl flex items-center justify-center text-gray-500 hover:bg-gray-100">
                    <i class="fas fa-bars text-lg"></i>
                </button>

                <!-- Profile Avatar -->
                <div class="relative hidden lg:block" id="profileDropdown">
                    <button onclick="toggleDropdown('profileDropdown')" class="flex items-center gap-2.5 py-1.5 px-2 rounded-xl hover:bg-gray-100 transition-all">
                        <img src="<?= htmlspecialchars($_fhAvatar) ?>" class="w-9 h-9 rounded-xl object-cover border-2 border-gray-100" alt="Avatar">
                        <span class="text-sm font-semibold text-gray-900 hidden sm:block max-w-[100px] truncate"><?= htmlspecialchars($_fhName) ?></span>
                        <!-- w -->
                    </button>
                    <div class="profile-popup">
                        <div class="p-4 border-b border-gray-100">
                            <p class="text-sm font-bold text-gray-900"><?= htmlspecialchars($_fhName) ?></p>
                            <p class="text-xs text-gray-400 mt-0.5">Freelancer</p>
                        </div>
                        <div class="py-1">
                            <a href="profile.php" class="dropdown-item"><i class="fas fa-user"></i> My Profile</a>
                            <a href="profile_edit.php" class="dropdown-item"><i class="fas fa-portrait"></i> Portfolio</a>
                        </div>
                        <div class="border-t border-gray-100 py-1">
                            <a href="../auth/logout.php" class="dropdown-item text-red-500 hover:bg-red-50"><i class="fas fa-sign-out-alt"></i> Logout</a>
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
                <i class="fas fa-times text-sm"></i>
            </button>
        </div>
        <div class="p-4 space-y-1">
            <p class="text-[10px] font-bold uppercase tracking-widest text-gray-400 px-3 mb-2">Find Work</p>
            <a href="home.php" class="flex items-center gap-3 px-3 py-2.5 rounded-xl text-sm font-semibold <?= $_fhActive === 'home' ? 'bg-blue-50 text-blue-600' : 'text-gray-600 hover:bg-gray-50' ?>"><i class="fas fa-home w-5 text-center text-[13px]"></i> Home</a>
            <a href="browse_jobs.php" class="flex items-center gap-3 px-3 py-2.5 rounded-xl text-sm font-semibold <?= $_fhActive === 'browse_jobs' ? 'bg-blue-50 text-blue-600' : 'text-gray-600 hover:bg-gray-50' ?>"><i class="fas fa-search w-5 text-center text-[13px]"></i> Browse Jobs</a>
            <a href="recommended_jobs.php" class="flex items-center gap-3 px-3 py-2.5 rounded-xl text-sm font-semibold text-gray-600 hover:bg-gray-50"><i class="fas fa-magic w-5 text-center text-[13px]"></i> AI Job Matches</a>
            <a href="proposals.php" class="flex items-center gap-3 px-3 py-2.5 rounded-xl text-sm font-semibold <?= $_fhActive === 'proposals' ? 'bg-blue-50 text-blue-600' : 'text-gray-600 hover:bg-gray-50' ?>"><i class="fas fa-file-alt w-5 text-center text-[13px]"></i> My Proposals</a>

            <p class="text-[10px] font-bold uppercase tracking-widest text-gray-400 px-3 mb-2 mt-4">Deliver Work</p>
            <a href="contracts.php" class="flex items-center gap-3 px-3 py-2.5 rounded-xl text-sm font-semibold <?= $_fhActive === 'contracts' ? 'bg-blue-50 text-blue-600' : 'text-gray-600 hover:bg-gray-50' ?>"><i class="fas fa-file-contract w-5 text-center text-[13px]"></i> Active Contracts</a>

            <p class="text-[10px] font-bold uppercase tracking-widest text-gray-400 px-3 mb-2 mt-4">Finances</p>
            <a href="earnings.php" class="flex items-center gap-3 px-3 py-2.5 rounded-xl text-sm font-semibold <?= $_fhActive === 'earnings' ? 'bg-blue-50 text-blue-600' : 'text-gray-600 hover:bg-gray-50' ?>"><i class="fas fa-wallet w-5 text-center text-[13px]"></i> Earnings</a>
            <a href="reviews.php" class="flex items-center gap-3 px-3 py-2.5 rounded-xl text-sm font-semibold <?= $_fhActive === 'reviews' ? 'bg-blue-50 text-blue-600' : 'text-gray-600 hover:bg-gray-50' ?>"><i class="fas fa-star w-5 text-center text-[13px]"></i> Reviews</a>

            <p class="text-[10px] font-bold uppercase tracking-widest text-gray-400 px-3 mb-2 mt-4">Account</p>
            <a href="messages.php" class="flex items-center gap-3 px-3 py-2.5 rounded-xl text-sm font-semibold <?= $_fhActive === 'messages' ? 'bg-blue-50 text-blue-600' : 'text-gray-600 hover:bg-gray-50' ?>"><i class="fas fa-comment-dots w-5 text-center text-[13px]"></i> Messages <?php if ($_fhUnread > 0): ?><span class="ml-auto w-5 h-5 bg-red-500 text-white text-[10px] font-bold rounded-full flex items-center justify-center"><?= $_fhUnread ?></span><?php endif; ?></a>
            <a href="profile.php" class="flex items-center gap-3 px-3 py-2.5 rounded-xl text-sm font-semibold <?= $_fhActive === 'profile' ? 'bg-blue-50 text-blue-600' : 'text-gray-600 hover:bg-gray-50' ?>"><i class="fas fa-user w-5 text-center text-[13px]"></i> My Profile</a>
            <a href="profile_edit.php" class="flex items-center gap-3 px-3 py-2.5 rounded-xl text-sm font-semibold text-gray-600 hover:bg-gray-50"><i class="fas fa-user-pen w-5 text-center text-[13px]"></i> Edit Profile</a>

            <div class="border-t border-gray-100 mt-4 pt-4">
                <a href="../auth/logout.php" class="flex items-center gap-3 px-3 py-2.5 rounded-xl text-sm font-semibold text-red-500 hover:bg-red-50"><i class="fas fa-sign-out-alt w-5 text-center"></i> Logout</a>
            </div>
        </div>
    </div>

    <!-- Spacer for fixed navbar -->
    <div class="h-[60px]"></div>

    <script>
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
            if (icon) icon.className = document.documentElement.classList.contains('dark') ? 'fas fa-sun text-sm' : 'fas fa-moon text-sm';
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
            html += '<a href="browse_jobs.php?search=' + encodeURIComponent(query) + '" class="flex items-center gap-3 px-3 py-2 rounded-lg text-sm text-gray-700 hover:bg-blue-50 hover:text-blue-600 transition-colors"><i class="fas fa-briefcase text-gray-400 w-4 text-center"></i> Search jobs for "' + query + '"</a>';
            html += '<a href="browse_jobs.php?skills[]=&search=' + encodeURIComponent(query) + '" class="flex items-center gap-3 px-3 py-2 rounded-lg text-sm text-gray-700 hover:bg-blue-50 hover:text-blue-600 transition-colors"><i class="fas fa-tags text-gray-400 w-4 text-center"></i> Search skills for "' + query + '"</a>';
            html += '</div>';
            resultsDiv.innerHTML = html;
            resultsDiv.classList.remove('hidden');
        }
    </script>