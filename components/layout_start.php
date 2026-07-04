<?php

/**
 * Layout Start – Sidebar + Top Header Bar
 *
 * Opens HTML, loads Tailwind/FontAwesome/Chart.js, renders sidebar + top header.
 * Each page includes this at the top, then its content, then layout_end.php.
 *
 * Required variables before include:
 *   $pageTitle (string)
 *   $activePage (string) - current page key
 *   $navItems (array) - [['key','label','url','icon'], ...]
 *   $user (array) - ['name','profile_image']
 *   $unreadCount (int) - notification count
 *   $profileLink (string) - profile page URL
 */
$_layoutPageTitle = $pageTitle ?? 'Dashboard';
$_layoutActive = $activePage ?? 'dashboard';
$_layoutNav = $navItems ?? [];
$_layoutUser = $user ?? ['name' => 'User', 'profile_image' => ''];
$_layoutAvatar = get_profile_image($_layoutUser['profile_image']);
$_layoutUnread = $unreadCount ?? 0;
$_layoutProfile = $profileLink ?? 'profile.php';
?>
<!DOCTYPE html>
<html lang="en" class="scroll-smooth">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width,initial-scale=1.0">
    <title><?= htmlspecialchars($_layoutPageTitle) ?> – JobHub</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800;900&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js"></script>
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
            background: #F4F7FC;
            color: #1e293b;
            margin: 0;
        }

        html.dark body {
            background: #0f172a;
            color: #e2e8f0;
        }

        /* Sidebar */
        .app-sidebar {
            width: 260px;
            min-height: 100vh;
            background: #fff;
            border-right: 1px solid #E5EDF6;
            position: fixed;
            top: 0;
            left: 0;
            z-index: 40;
            transition: transform .3s ease;
            display: flex;
            flex-direction: column;
        }

        html.dark .app-sidebar {
            background: #1e293b;
            border-color: #334155;
        }

        @media (max-width: 1023px) {
            .app-sidebar {
                transform: translateX(-100%);
            }

            .app-sidebar.open {
                transform: translateX(0);
            }
        }

        /* Sidebar overlay */
        .sidebar-overlay {
            display: none;
            position: fixed;
            inset: 0;
            background: rgba(0, 0, 0, .4);
            z-index: 35;
        }

        .sidebar-overlay.show {
            display: block;
        }

        /* Sidebar nav link */
        .snav {
            display: flex;
            align-items: center;
            gap: 12px;
            padding: 10px 16px;
            border-radius: 12px;
            font-size: 13px;
            font-weight: 600;
            color: #64748b;
            text-decoration: none;
            transition: all .2s;
            margin: 2px 12px;
        }

        .snav:hover {
            background: #f1f5f9;
            color: #334155;
        }

        html.dark .snav:hover {
            background: rgba(51, 65, 85, .4);
            color: #e2e8f0;
        }

        .snav.active {
            background: linear-gradient(135deg, #eff6ff, #ecfeff);
            color: #2563EB;
            font-weight: 700;
        }

        html.dark .snav.active {
            background: linear-gradient(135deg, rgba(37, 99, 235, .15), rgba(14, 165, 233, .15));
            color: #60a5fa;
        }

        /* Top header */
        .app-header {
            height: 64px;
            background: rgba(255, 255, 255, .9);
            backdrop-filter: blur(16px);
            border-bottom: 1px solid #E5EDF6;
            position: sticky;
            top: 0;
            z-index: 30;
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 0 24px;
        }

        html.dark .app-header {
            background: rgba(30, 41, 59, .9);
            border-color: #334155;
        }

        /* Main content */
        .app-main {
            margin-left: 260px;
            min-height: 100vh;
        }

        @media (max-width: 1023px) {
            .app-main {
                margin-left: 0;
            }
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

        html.dark .btn-secondary {
            background: #334155;
            border-color: #475569;
            color: #e2e8f0;
        }
    </style>
</head>

<body class="min-h-screen">

    <!-- Sidebar Overlay (mobile) -->
    <div id="sidebarOverlay" class="sidebar-overlay" onclick="toggleSidebar()"></div>

    <!-- Sidebar -->
    <aside id="sidebar" class="app-sidebar">
        <!-- Logo -->
        <div class="p-5 border-b border-gray-100 dark:border-slate-700">
            <a href="../index.php" class="flex items-center gap-2.5 no-underline">
                <img src="../assets/upload/logos/logo.png" alt="Logo" class="w-9 h-9 rounded-xl">
                <span class="text-lg font-extrabold tracking-tight text-gray-900 dark:text-white">Job<span class="text-blue-600">Hub</span></span>
            </a>
        </div>

        <!-- Navigation -->
        <nav class="flex-1 py-4 overflow-y-auto">
            <?php foreach ($_layoutNav as $item): ?>
                <a href="<?= $item['url'] ?>" class="snav <?= $_layoutActive === $item['key'] ? 'active' : '' ?>">
                    <i class="fas <?= $item['icon'] ?> w-5 text-center text-[14px]"></i>
                    <span><?= $item['label'] ?></span>
                </a>
            <?php endforeach; ?>
        </nav>

        <!-- Logout -->
        <div class="p-4 border-t border-gray-100 dark:border-slate-700">
            <a href="../auth/logout.php" class="flex items-center justify-center gap-2 px-4 py-3 rounded-xl text-sm font-semibold bg-red-500 text-white hover:bg-red-600 transition no-underline">
                <i class="fas fa-sign-out-alt"></i> Logout
            </a>
        </div>
    </aside>

    <!-- Main Area -->
    <div class="app-main">

        <!-- Top Header Bar -->
        <header class="app-header">
            <!-- Left: hamburger (mobile) + page title -->
            <div class="flex items-center gap-4">
                <button onclick="toggleSidebar()" class="lg:hidden w-10 h-10 rounded-xl flex items-center justify-center text-gray-500 hover:bg-gray-100 dark:text-slate-400 dark:hover:bg-slate-700">
                    <i class="fas fa-bars text-lg"></i>
                </button>
                <div>
                    <h1 class="text-base font-bold text-gray-900 dark:text-white m-0"><?= htmlspecialchars($_layoutPageTitle) ?></h1>
                </div>
            </div>

            <!-- Right: search, notifications, dark mode, profile -->
            <div class="flex items-center gap-2">
                <!-- Search -->
                <div class="relative hidden md:block">
                    <i class="fas fa-search absolute left-3 top-1/2 -translate-y-1/2 text-gray-400 text-sm pointer-events-none"></i>
                    <input type="text" placeholder="Search..." class="w-56 py-2 pl-9 pr-3 rounded-xl border border-gray-200 bg-gray-50 text-sm text-gray-700 outline-none focus:border-blue-500 focus:ring-2 focus:ring-blue-100 dark:bg-slate-900 dark:border-slate-600 dark:text-slate-300 transition">
                </div>

                <!-- Notifications -->
                <a href="../shared/notifications.php" class="relative w-10 h-10 rounded-xl flex items-center justify-center text-gray-500 hover:bg-gray-100 dark:text-slate-400 dark:hover:bg-slate-700 no-underline transition">
                    <i class="fas fa-bell text-base"></i>
                    <?php if ($_layoutUnread > 0): ?>
                        <span class="absolute top-2 right-2 w-2 h-2 bg-red-500 rounded-full border-2 border-white dark:border-slate-800"></span>
                    <?php endif; ?>
                </a>

                <!-- Dark mode -->
                <button onclick="toggleDarkMode()" class="w-10 h-10 rounded-xl flex items-center justify-center text-gray-500 hover:bg-gray-100 dark:text-slate-400 dark:hover:bg-slate-700 transition" title="Toggle dark mode">
                    <i class="fas fa-moon text-sm" id="darkModeIcon"></i>
                </button>

                <!-- Profile -->
                <a href="<?= htmlspecialchars($_layoutProfile) ?>" class="flex items-center gap-2.5 py-1.5 px-2 rounded-xl hover:bg-gray-100 dark:hover:bg-slate-700 no-underline transition">
                    <img src="<?= htmlspecialchars($_layoutAvatar) ?>" class="w-9 h-9 rounded-xl object-cover border-2 border-gray-100 dark:border-slate-600" alt="Avatar">
                    <span class="text-sm font-semibold text-gray-900 dark:text-white hidden sm:block"><?= htmlspecialchars($_layoutUser['name'] ?? 'User') ?></span>
                </a>
            </div>
        </header>

        <!-- Page Content -->
        <main class="p-6 flex flex-col gap-6">