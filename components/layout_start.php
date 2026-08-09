<?php

/**
 * Layout Start – Redesigned Sidebar + Top Header Bar
 * Modern SaaS-style collapsible sidebar with accordion groups.
 */
$_layoutPageTitle = $pageTitle ?? 'Dashboard';
$_layoutActive = $activePage ?? 'dashboard';
$_layoutNav = $navItems ?? [];
$_layoutUser = $user ?? ['name' => 'User', 'profile_image' => ''];
$_layoutAvatar = get_profile_image($_layoutUser['profile_image']);
$_layoutUnread = $unreadCount ?? 0;
$_layoutProfile = $profileLink ?? 'profile.php';

$_layoutWalletBalance = null;
if (isset($_SESSION['user_role']) && $_SESSION['user_role'] === 'client' && isset($_SESSION['user_id'])) {
    if (isset($conn) && $conn instanceof mysqli) {
        $_layoutWStmt = $conn->prepare('SELECT wallet_balance FROM users WHERE id = ?');
        $_layoutWStmt->bind_param('i', $_SESSION['user_id']);
        $_layoutWStmt->execute();
        $_layoutWRow = $_layoutWStmt->get_result()->fetch_assoc();
        $_layoutWStmt->close();
        $_layoutWalletBalance = $_layoutWRow ? (float) $_layoutWRow['wallet_balance'] : 0.0;
    }
}

$_navByKey = [];
foreach ($_layoutNav as $_navItem)
    $_navByKey[$_navItem['key']] = $_navItem;
?>
<!DOCTYPE html>
<html lang="en" class="scroll-smooth">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width,initial-scale=1.0">
    <title><?= htmlspecialchars($_layoutPageTitle) ?> – JobHub</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800;900&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="/jobhub/assets/css/theme.css">
    <script src="https://unpkg.com/lucide@0.344.0/dist/umd/lucide.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js"></script>
    <link rel="stylesheet" href="/jobhub/shared/dark-mode.css">
    <script>
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
                    fontFamily: { inter: ['Inter', 'sans-serif'] },
                    colors: {
                        primary: { DEFAULT: '#2563eb', dark: '#1d4ed8', light: '#3b82f6' },
                        accent: { DEFAULT: '#14b8a6', dark: '#0d9488' },
                        surface: { DEFAULT: '#f8fafc', card: '#ffffff', border: '#e5edf6' }
                    }
                }
            }
        }
    </script>
    <style>
        *, *::before, *::after { box-sizing: border-box; }
        body { font-family: 'Inter', sans-serif; background: #f1f5f9; color: #1e293b; margin: 0; overflow-x: hidden; line-height: 0; }
        html.dark body { background: #0f172a; color: #e2e8f0; }

        /* ═══ SIDEBAR ═══ */
        .app-sidebar {
            width: 260px;
            height: 100vh;
            background: #fff;
            border-right: 1px solid #e2e8f0;
            position: fixed;
            top: 0; left: 0; z-index: 50;
            display: flex; flex-direction: column;
            transition: width .3s cubic-bezier(.4,0,.2,1), transform .3s cubic-bezier(.4,0,.2,1);
            overflow: hidden;
        }
        html.dark .app-sidebar { background: #1e293b; border-color: #334155; }

        .app-sidebar.collapsed { width: 72px; }
        .app-sidebar.collapsed .sidebar-label,
        .app-sidebar.collapsed .sidebar-text,
        .app-sidebar.collapsed .sidebar-group-items,
        .app-sidebar.collapsed .sidebar-logo-text,
        .app-sidebar.collapsed .sidebar-subtitle { display: none; }
        .app-sidebar.collapsed .sidebar-item { justify-content: center; padding-left: 0; padding-right: 0; }
        .app-sidebar.collapsed .sidebar-item i { margin-right: 0; }
        .app-sidebar.collapsed .sidebar-group-header { justify-content: center; padding-left: 0; padding-right: 0; }
        .app-sidebar.collapsed .sidebar-group-header i.group-chevron { display: none; }
        .app-sidebar.collapsed .sidebar-group-header .group-icon { margin-right: 0; }

        @media (max-width: 1023px) {
            .app-sidebar { transform: translateX(-100%); width: 280px; }
            .app-sidebar.mobile-open { transform: translateX(0); }
            .app-sidebar.collapsed { width: 280px; }
            .app-sidebar.collapsed .sidebar-label,
            .app-sidebar.collapsed .sidebar-text,
            .app-sidebar.collapsed .sidebar-group-items,
            .app-sidebar.collapsed .sidebar-logo-text,
            .app-sidebar.collapsed .sidebar-subtitle { display: block; }
            .app-sidebar.collapsed .sidebar-item { justify-content: flex-start; padding-left: 12px; padding-right: 12px; }
            .app-sidebar.collapsed .sidebar-item i { margin-right: 10px; }
        }

        /* Sidebar overlay */
        .sidebar-overlay {
            display: none; position: fixed; inset: 0;
            background: rgba(0,0,0,.45); z-index: 45;
            backdrop-filter: blur(2px);
        }
        .sidebar-overlay.show { display: block; }

        /* Sidebar logo */
        .sidebar-logo { padding: 16px 16px 12px; border-bottom: 1px solid #f1f5f9; display: flex; align-items: center; gap: 10px; min-height: 60px; }
        html.dark .sidebar-logo { border-color: #334155; }

        /* Sidebar nav item */
        .sidebar-item {
            display: flex; align-items: center; gap: 10px;
            padding: 8px 12px; margin: 1px 8px;
            border-left: 2px solid transparent;
            border-radius: 0 8px 8px 0; font-size: 13px; font-weight: 500;
            color: #64748b; text-decoration: none;
            transition: all .15s ease; cursor: pointer; position: relative;
        }
        .sidebar-item:hover { background: #f8fafc; color: #1e293b; }
        html.dark .sidebar-item:hover { background: rgba(51,65,85,.3); color: #e2e8f0; }
        .sidebar-item.active { background: linear-gradient(135deg, #eff6ff, #ecfeff); color: #2563eb; font-weight: 600; border-left-color: #2563eb; }
        html.dark .sidebar-item.active { background: linear-gradient(135deg, rgba(37,99,235,.12), rgba(14,165,233,.12)); color: #60a5fa; border-left-color: #60a5fa; }

        /* Sidebar group */
        .sidebar-group-header {
            display: flex; align-items: center; gap: 10px;
            padding: 8px 12px; margin: 1px 8px;
            border-radius: 8px; font-size: 11px; font-weight: 700;
            color: #94a3b8; text-transform: uppercase; letter-spacing: .06em;
            cursor: pointer; transition: all .15s ease; user-select: none;
        }
        .sidebar-group-header:hover { background: #f8fafc; color: #64748b; }
        html.dark .sidebar-group-header:hover { background: rgba(51,65,85,.3); color: #94a3b8; }
        .sidebar-group-header .group-chevron {
            margin-left: auto; font-size: 10px; transition: transform .2s ease;
        }
        .sidebar-group.open .sidebar-group-header .group-chevron { transform: rotate(90deg); }
        .sidebar-group-items {
            max-height: 0; overflow: hidden;
            transition: max-height .3s cubic-bezier(.4,0,.2,1), opacity .2s ease;
            opacity: 0;
        }
        .sidebar-group.open .sidebar-group-items { max-height: 300px; opacity: 1;padding-left: 8px; }
        .sidebar-group.open .sidebar-group-items .sidebar-item svg,
        .sidebar-group.open .sidebar-group-items .sidebar-item i { color: inherit; }
        .sidebar-group.open .sidebar-group-items .sidebar-item[data-key="users"] svg,
        .sidebar-group.open .sidebar-group-items .sidebar-item[data-key="users"] i { color: #059669 !important; }
        .sidebar-group.open .sidebar-group-items .sidebar-item[data-key="jobs"] svg,
        .sidebar-group.open .sidebar-group-items .sidebar-item[data-key="jobs"] i { color: #7c3aed !important; }
        .sidebar-group.open .sidebar-group-items .sidebar-item[data-key="categories"] svg,
        .sidebar-group.open .sidebar-group-items .sidebar-item[data-key="categories"] i { color: #ea580c !important; }
        .sidebar-group.open .sidebar-group-items .sidebar-item[data-key="skills"] svg,
        .sidebar-group.open .sidebar-group-items .sidebar-item[data-key="skills"] i { color: #0891b2 !important; }
        .sidebar-group.open .sidebar-group-items .sidebar-item[data-key="contracts"] svg,
        .sidebar-group.open .sidebar-group-items .sidebar-item[data-key="contracts"] i { color: #2563eb !important; }
        .sidebar-group.open .sidebar-group-items .sidebar-item[data-key="milestones"] svg,
        .sidebar-group.open .sidebar-group-items .sidebar-item[data-key="milestones"] i { color: #7c3aed !important; }
        .sidebar-group.open .sidebar-group-items .sidebar-item[data-key="wallets"] svg,
        .sidebar-group.open .sidebar-group-items .sidebar-item[data-key="wallets"] i { color: #16a34a !important; }
        .sidebar-group.open .sidebar-group-items .sidebar-item[data-key="payments"] svg,
        .sidebar-group.open .sidebar-group-items .sidebar-item[data-key="payments"] i { color: #ea580c !important; }
        .sidebar-group.open .sidebar-group-items .sidebar-item[data-key="reviews"] svg,
        .sidebar-group.open .sidebar-group-items .sidebar-item[data-key="reviews"] i { color: #d97706 !important; }
        .sidebar-group.open .sidebar-group-items .sidebar-item[data-key="disputes"] svg,
        .sidebar-group.open .sidebar-group-items .sidebar-item[data-key="disputes"] i { color: #dc2626 !important; }
        .sidebar-group.open .sidebar-group-items .sidebar-item[data-key="fraud"] svg,
        .sidebar-group.open .sidebar-group-items .sidebar-item[data-key="fraud"] i { color: #059669 !important; }
        .sidebar-group.open .sidebar-group-items .sidebar-item[data-key="analytics"] svg,
        .sidebar-group.open .sidebar-group-items .sidebar-item[data-key="analytics"] i { color: #8b5cf6 !important; }
        .sidebar-group.open .sidebar-group-items .sidebar-item[data-key="ai_monitor"] svg,
        .sidebar-group.open .sidebar-group-items .sidebar-item[data-key="ai_monitor"] i { color: #7c3aed !important; }
        .sidebar-group.open .sidebar-group-items .sidebar-item[data-key="notifications"] svg,
        .sidebar-group.open .sidebar-group-items .sidebar-item[data-key="notifications"] i { color: #f59e0b !important; }
        .sidebar-group.open .sidebar-group-items .sidebar-item[data-key="settings"] svg,
        .sidebar-group.open .sidebar-group-items .sidebar-item[data-key="settings"] i { color: #0891b2 !important; }
        html.dark .sidebar-group.open .sidebar-group-items .sidebar-item[data-key="users"] svg,
        html.dark .sidebar-group.open .sidebar-group-items .sidebar-item[data-key="users"] i { color: #34d399 !important; }
        html.dark .sidebar-group.open .sidebar-group-items .sidebar-item[data-key="jobs"] svg,
        html.dark .sidebar-group.open .sidebar-group-items .sidebar-item[data-key="jobs"] i { color: #a78bfa !important; }
        html.dark .sidebar-group.open .sidebar-group-items .sidebar-item[data-key="categories"] svg,
        html.dark .sidebar-group.open .sidebar-group-items .sidebar-item[data-key="categories"] i { color: #fb923c !important; }
        html.dark .sidebar-group.open .sidebar-group-items .sidebar-item[data-key="skills"] svg,
        html.dark .sidebar-group.open .sidebar-group-items .sidebar-item[data-key="skills"] i { color: #22d3ee !important; }
        html.dark .sidebar-group.open .sidebar-group-items .sidebar-item[data-key="contracts"] svg,
        html.dark .sidebar-group.open .sidebar-group-items .sidebar-item[data-key="contracts"] i { color: #60a5fa !important; }
        html.dark .sidebar-group.open .sidebar-group-items .sidebar-item[data-key="milestones"] svg,
        html.dark .sidebar-group.open .sidebar-group-items .sidebar-item[data-key="milestones"] i { color: #a78bfa !important; }
        html.dark .sidebar-group.open .sidebar-group-items .sidebar-item[data-key="wallets"] svg,
        html.dark .sidebar-group.open .sidebar-group-items .sidebar-item[data-key="wallets"] i { color: #4ade80 !important; }
        html.dark .sidebar-group.open .sidebar-group-items .sidebar-item[data-key="payments"] svg,
        html.dark .sidebar-group.open .sidebar-group-items .sidebar-item[data-key="payments"] i { color: #fb923c !important; }
        html.dark .sidebar-group.open .sidebar-group-items .sidebar-item[data-key="reviews"] svg,
        html.dark .sidebar-group.open .sidebar-group-items .sidebar-item[data-key="reviews"] i { color: #fbbf24 !important; }
        html.dark .sidebar-group.open .sidebar-group-items .sidebar-item[data-key="disputes"] svg,
        html.dark .sidebar-group.open .sidebar-group-items .sidebar-item[data-key="disputes"] i { color: #f87171 !important; }
        html.dark .sidebar-group.open .sidebar-group-items .sidebar-item[data-key="fraud"] svg,
        html.dark .sidebar-group.open .sidebar-group-items .sidebar-item[data-key="fraud"] i { color: #34d399 !important; }
        html.dark .sidebar-group.open .sidebar-group-items .sidebar-item[data-key="analytics"] svg,
        html.dark .sidebar-group.open .sidebar-group-items .sidebar-item[data-key="analytics"] i { color: #c084fc !important; }
        html.dark .sidebar-group.open .sidebar-group-items .sidebar-item[data-key="ai_monitor"] svg,
        html.dark .sidebar-group.open .sidebar-group-items .sidebar-item[data-key="ai_monitor"] i { color: #a78bfa !important; }
        html.dark .sidebar-group.open .sidebar-group-items .sidebar-item[data-key="notifications"] svg,
        html.dark .sidebar-group.open .sidebar-group-items .sidebar-item[data-key="notifications"] i { color: #fbbf24 !important; }
        html.dark .sidebar-group.open .sidebar-group-items .sidebar-item[data-key="settings"] svg,
        html.dark .sidebar-group.open .sidebar-group-items .sidebar-item[data-key="settings"] i { color: #22d3ee !important; }

        /* Sidebar scrollbar */
        .sidebar-nav::-webkit-scrollbar { width: 4px; }
        .sidebar-nav::-webkit-scrollbar-track { background: transparent; }
        .sidebar-nav::-webkit-scrollbar-thumb { background: #e2e8f0; border-radius: 999px; }
        html.dark .sidebar-nav::-webkit-scrollbar-thumb { background: #475569; }

        /* Divider */
        .sidebar-divider { height: 1px; background: #f1f5f9; margin: 6px 16px; }
        html.dark .sidebar-divider { background: #334155; }

        /* ═══ TOP HEADER ═══ */
        .app-header {
            height: 56px;
            background: rgba(255,255,255,.85);
            backdrop-filter: blur(16px) saturate(180%);
            border-bottom: 1px solid #e2e8f0;
            position: sticky; top: 0; z-index: 40;
            display: flex; align-items: center; justify-content: space-between;
            padding: 0 20px;
            transition: background .2s, border-color .2s;
        }
        html.dark .app-header { background: rgba(30,41,59,.85); border-color: #334155; }

        /* ═══ MAIN CONTENT ═══ */
        .app-main {
            margin-left: 260px;
            min-height: 100vh;
            transition: margin-left .3s cubic-bezier(.4,0,.2,1);
        }
        .app-main.sidebar-collapsed { margin-left: 72px; }
        @media (max-width: 1023px) {
            .app-main, .app-main.sidebar-collapsed { margin-left: 0; }
        }

        /* ═══ COLLAPSE TOGGLE ═══ */
        .sidebar-collapse-btn {
            width: 20px; height: 20px; border-radius: 6px;
            display: flex; align-items: center; justify-content: center;
            background: #f1f5f9; border: 1px solid #e2e8f0;
            color: #64748b; font-size: 9px; cursor: pointer;
            transition: all .2s; position: absolute; right: -10px; top: 50%; transform: translateY(-50%);
            z-index: 10; opacity: 0;
        }
        .app-sidebar:hover .sidebar-collapse-btn { opacity: 1; }
        .sidebar-collapse-btn:hover { background: #e2e8f0; color: #334155; }
        html.dark .sidebar-collapse-btn { background: #334155; border-color: #475569; color: #94a3b8; }
        html.dark .sidebar-collapse-btn:hover { background: #475569; color: #e2e8f0; }
        @media (max-width: 1023px) { .sidebar-collapse-btn { display: none; } }

        /* ═══ EXISTING COMPONENT STYLES ═══ */
        .dh-card { background: #fff; border: 1px solid #e5edf6; border-radius: 20px; box-shadow: 0 4px 20px rgba(15,23,42,.05); transition: box-shadow .2s; }
        .dh-card:hover { box-shadow: 0 8px 30px rgba(15,23,42,.08); }
        html.dark .dh-card { background: #1e293b; border-color: #334155; }

        .stat-card { background: #fff; border: 1px solid #E5EDF6; border-left: 4px solid var(--accent, #2563EB); border-radius: 14px; padding: 16px 18px; transition: all .2s; }
        .stat-card:hover { transform: translateY(-2px); box-shadow: 0 6px 20px rgba(15,23,42,.07); }
        html.dark .stat-card { background: #1e293b; border-color: #334155; border-left-color: var(--accent, #3b82f6); }

        .qa-card { background: #fff; border: 1px solid #E5EDF6; border-radius: 20px; padding: 24px; transition: all .25s; }
        .qa-card:hover { transform: translateY(-3px); box-shadow: 0 12px 32px rgba(15,23,42,.08); }
        html.dark .qa-card { background: #1e293b; border-color: #334155; }

        .bdg { display: inline-flex; align-items: center; padding: 3px 10px; border-radius: 8px; font-size: 11px; font-weight: 600; border: 1px solid transparent; }
        .bdg-blue { background: #EEF5FF; color: #2563EB; border-color: #DBEAFE; }
        .bdg-emerald { background: #ECFDF5; color: #059669; border-color: #D1FAE5; }
        .bdg-purple { background: #F5F3FF; color: #7C3AED; border-color: #EDE9FE; }
        .bdg-orange { background: #FFF7ED; color: #EA580C; border-color: #FED7AA; }
        .bdg-cyan { background: #ECFEFF; color: #0891B2; border-color: #CFFAFE; }
        .bdg-rose { background: #FFF1F2; color: #E11D48; border-color: #FFE4E6; }
        .bdg-gray { background: #f1f5f9; color: #64748b; border-color: #e2e8f0; }
        html.dark .bdg-blue { background: rgba(37,99,235,.12); border-color: rgba(37,99,235,.25); }
        html.dark .bdg-emerald { background: rgba(5,150,105,.12); border-color: rgba(5,150,105,.25); }
        html.dark .bdg-purple { background: rgba(124,58,237,.12); border-color: rgba(124,58,237,.25); }
        html.dark .bdg-orange { background: rgba(234,88,12,.12); border-color: rgba(234,88,12,.25); }
        html.dark .bdg-cyan { background: rgba(8,145,178,.12); border-color: rgba(8,145,178,.25); }
        html.dark .bdg-rose { background: rgba(225,29,72,.12); border-color: rgba(225,29,72,.25); }
        html.dark .bdg-gray { background: rgba(100,116,139,.12); border-color: rgba(100,116,139,.25); }

        .pbar { width: 100%; height: 6px; background: #E5EDF6; border-radius: 999px; overflow: hidden; }
        .pbar-fill { height: 100%; border-radius: 999px; background: linear-gradient(90deg, #2563eb, #3b82f6); }
        .pbar-fill.green { background: linear-gradient(90deg, #059669, #10b981); }
        html.dark .pbar { background: #334155; }

        .dtbl { width: 100%; border-collapse: separate; border-spacing: 0; }
        .dtbl thead th { text-align: left; padding: 0 12px 10px; font-size: 11px; font-weight: 600; color: #94a3b8; text-transform: uppercase; letter-spacing: .04em; border-bottom: 1px solid #E5EDF6; }
        .dtbl tbody td { padding: 12px; font-size: 13px; border-bottom: 1px solid #f1f5f9; }
        .dtbl tbody tr:last-child td { border-bottom: none; }
        .dtbl tbody tr:hover { background: #f8fafc; }
        html.dark .dtbl thead th { border-bottom-color: #334155; color: #64748b; }
        html.dark .dtbl tbody td { border-bottom-color: rgba(51,65,85,.4); }
        html.dark .dtbl tbody tr:hover { background: rgba(51,65,85,.25); }

        .tl-item { position: relative; padding-left: 40px; padding-bottom: 20px; }
        .tl-item::before { content: ''; position: absolute; left: 15px; top: 28px; bottom: 0; width: 2px; background: #E5EDF6; }
        .tl-item:last-child::before { display: none; }
        .tl-dot { position: absolute; left: 6px; top: 4px; width: 20px; height: 20px; border-radius: 50%; display: flex; align-items: center; justify-content: center; font-size: 9px; }
        html.dark .tl-item::before { background: #334155; }

        .gtxt { background: linear-gradient(135deg, #2563eb, #14b8a6, #6366f1); -webkit-background-clip: text; -webkit-text-fill-color: transparent; background-clip: text; }

        @keyframes fadeIn { from { opacity: 0; transform: translateY(10px); } to { opacity: 1; transform: translateY(0); } }
        .fade-in { animation: fadeIn .4s ease forwards; opacity: 0; }

        ::-webkit-scrollbar { width: 6px; height: 6px; }
        ::-webkit-scrollbar-track { background: transparent; }
        ::-webkit-scrollbar-thumb { background: #cbd5e1; border-radius: 999px; }
        html.dark ::-webkit-scrollbar-thumb { background: #475569; }

        .btn-grad { background: linear-gradient(135deg, #2563eb, #14b8a6); color: #ffffff; border: none; border-radius: 999px; padding: 10px 24px; font-weight: 600; font-size: 13px; cursor: pointer; transition: all 0.25s; display: inline-flex; align-items: center; gap: 8px; }
        .btn-grad:hover { opacity: 0.9; transform: translateY(-2px); box-shadow: 0 8px 20px rgba(37,99,235,0.3); }
        html.dark .btn-secondary { background: #334155; border-color: #475569; color: #e2e8f0; }

        .header-search {
            width: 240px; padding: 7px 12px 7px 34px; border-radius: 10px;
            border: 1px solid #e2e8f0; background: #f8fafc; font-size: 13px;
            color: #1e293b; outline: none; transition: all .2s;
        }
        .header-search:focus { border-color: #3b82f6; box-shadow: 0 0 0 3px rgba(59,130,246,.1); background: #fff; }
        html.dark .header-search { background: #0f172a; border-color: #475569; color: #e2e8f0; }
        html.dark .header-search:focus { border-color: #3b82f6; box-shadow: 0 0 0 3px rgba(59,130,246,.15); }

        .header-icon-btn {
            width: 36px; height: 36px; border-radius: 10px;
            display: flex; align-items: center; justify-content: center;
            color: #64748b; transition: all .15s; position: relative; cursor: pointer;
            background: none; border: none;
        }
        .header-icon-btn:hover { background: #f1f5f9; color: #334155; }
        html.dark .header-icon-btn:hover { background: rgba(51,65,85,.4); color: #e2e8f0; }

        .header-avatar {
            display: flex; align-items: center; gap: 8px;
            padding: 4px 8px 4px 4px; border-radius: 10px;
            transition: background .15s; cursor: pointer; text-decoration: none;
        }
        .header-avatar:hover { background: #f1f5f9; }
        html.dark .header-avatar:hover { background: rgba(51,65,85,.4); }
    </style>
</head>

<body class="min-h-screen">

    <!-- Sidebar Overlay (mobile) -->
    <div id="sidebarOverlay" class="sidebar-overlay" onclick="toggleSidebar()"></div>

    <!-- ═══ SIDEBAR ═══ -->
    <aside id="sidebar" class="app-sidebar">
        <!-- Logo -->
        <div class="sidebar-logo">
            <a href="../index.php" class="flex items-center gap-2.5 no-underline flex-shrink-0">
                <img src="../assets/upload/logos/logo.png" alt="Logo" class="w-8 h-8 rounded-lg flex-shrink-0">
                <div class="sidebar-logo-text">
                    <span class="text-[15px] font-extrabold tracking-tight text-gray-900 dark:text-white leading-none">Job<span class="text-blue-600">Hub</span></span>
                    <span class="sidebar-subtitle block text-[9px] text-gray-400 dark:text-slate-500 font-medium mt-0.5">Admin Panel</span>
                </div>
            </a>
        </div>

        <!-- Navigation -->
        <nav class="sidebar-nav flex-1 overflow-y-auto py-3">
            <?php
            $sidebarGroups = [
                ['label' => '', 'items' => ['dashboard']],
                ['label' => 'Management', 'items' => ['users', 'jobs', 'categories', 'skills', 'contracts', 'milestones']],
                ['label' => 'Finance', 'items' => ['wallets', 'payments']],
                ['label' => 'Moderation', 'items' => ['reviews', 'disputes', 'fraud']],
                ['label' => 'Insights', 'items' => ['analytics', 'ai_monitor']],
                ['label' => 'System', 'items' => ['notifications', 'settings']],
            ];
            foreach ($sidebarGroups as $group):
                $isActive = false;
                foreach ($group['items'] as $key) {
                    if ($_layoutActive === $key) {
                        $isActive = true;
                        break;
                    }
                }
                if ($group['label'] === ''):
                    foreach ($group['items'] as $key):
                        if (!isset($_navByKey[$key]))
                            continue;
                        $item = $_navByKey[$key];
                        $lucideIcon = $item['icon'];
                        ?>
                    <a href="<?= $item['url'] ?>" class="sidebar-item <?= $_layoutActive === $key ? 'active' : '' ?>" data-key="<?= $key ?>" title="<?= $item['label'] ?>">
                        <i data-lucide="<?= $lucideIcon ?>" class="w-5 h-5 flex-shrink-0"></i>
                        <span class="sidebar-text"><?= $item['label'] ?></span>
                    </a>
                <?php
                    endforeach;
                    echo '<div class="sidebar-divider"></div>';
                    continue;
                endif;
                ?>
                <div class="sidebar-group <?= $isActive ? 'open' : '' ?>" data-group="<?= $group['label'] ?>">
                    <div class="sidebar-group-header" onclick="toggleGroup(this.parentElement)">
                        <i data-lucide="layers" class="w-5 h-5 flex-shrink-0 group-icon"></i>
                        <span class="sidebar-text"><?= $group['label'] ?></span>
                        <i data-lucide="chevron-right" class="w-4 h-4 flex-shrink-0 group-chevron ml-auto"></i>
                    </div>
                    <div class="sidebar-group-items">
                        <?php
                        foreach ($group['items'] as $key):
                            if (!isset($_navByKey[$key]))
                                continue;
                            $item = $_navByKey[$key];
                            $lucideIcon = $item['icon'];
                            ?>
                            <a href="<?= $item['url'] ?>" class="sidebar-item <?= $_layoutActive === $key ? 'active' : '' ?>" data-key="<?= $key ?>" title="<?= $item['label'] ?>">
                                <i data-lucide="<?= $lucideIcon ?>" class="w-5 h-5 flex-shrink-0"></i>
                                <span class="sidebar-text"><?= $item['label'] ?></span>
                            </a>
                        <?php endforeach; ?>
                    </div>
                </div>
            <?php endforeach; ?>

            <!-- Profile (standalone) -->
            <?php if (isset($_navByKey['profile'])): ?>
            <div class="sidebar-divider"></div>
            <a href="<?= $_navByKey['profile']['url'] ?>" class="sidebar-item <?= $_layoutActive === 'profile' ? 'active' : '' ?>" title="Profile">
                <i data-lucide="circle-user" class="w-5 h-5 flex-shrink-0"></i>
                <span class="sidebar-text">Profile</span>
            </a>
            <?php endif; ?>
        </nav>

        <!-- Sidebar collapse toggle -->
        <button class="sidebar-collapse-btn" onclick="toggleSidebarCollapse()" title="Collapse sidebar">
                <i data-lucide="chevron-left" class="w-4 h-4"></i>
        </button>

        <!-- Logout -->
        <div class="p-2 border-t border-gray-100 dark:border-slate-700">
            <a href="../auth/logout.php" class="sidebar-item text-red-500 hover:bg-red-50 dark:text-red-400 dark:hover:bg-red-900/20" title="Logout">
                <i data-lucide="log-out" class="w-5 h-5 flex-shrink-0"></i>
                <span class="sidebar-text text-xs font-semibold">Logout</span>
            </a>
        </div>
    </aside>

    <!-- ═══ MAIN AREA ═══ -->
    <div id="appMain" class="app-main">

        <!-- ═══ TOP HEADER ═══ -->
        <header class="app-header">
            <!-- Left: hamburger + sidebar collapse + page title -->
            <div class="flex items-center gap-3">
                <button onclick="toggleSidebarCollapse()" class="hidden lg:flex header-icon-btn" title="Toggle sidebar">
                    <i data-lucide="panel-left-close" class="w-4 h-4"></i>
                </button>
                <h1 class="text-sm font-bold text-gray-900 dark:text-white m-0"><?= htmlspecialchars($_layoutPageTitle) ?></h1>
            </div>

            <!-- Right: search, wallet, notifications, dark mode, profile -->
            <div class="flex items-center gap-1.5">
                <!-- Search -->
                <div class="relative hidden md:block">
                    <i data-lucide="search" class="text-base absolute left-3 top-1/2 -translate-y-1/2 text-gray-400 dark:text-slate-500 pointer-events-none"></i>
                    <input type="text" placeholder="Search..." class="header-search">
                </div>

                <?php if ($_layoutWalletBalance !== null): ?>
                <a href="wallet.php" class="hidden sm:flex items-center gap-1.5 px-2.5 py-1.5 rounded-lg bg-gradient-to-r from-blue-50 to-teal-50 border border-blue-100 dark:from-blue-900/20 dark:to-teal-900/20 dark:border-blue-800/30 transition-all no-underline" title="Wallet">
                    <i data-lucide="wallet" class="w-5 h-5 text-blue-600 dark:text-blue-400"></i>
                    <span class="text-xs font-bold text-blue-700 dark:text-blue-300">$<?= number_format($_layoutWalletBalance, 2) ?></span>
                </a>
                <?php endif; ?>

                <!-- Notifications -->
                <a href="../shared/notifications_page.php" class="header-icon-btn" title="Notifications">
                    <i data-lucide="bell" class="w-5 h-5"></i>
                    <?php if ($_layoutUnread > 0): ?>
                    <span class="absolute top-1.5 right-1.5 w-2 h-2 bg-red-500 rounded-full ring-2 ring-white dark:ring-slate-800"></span>
                    <?php endif; ?>
                </a>

                <!-- Dark mode -->
                <button onclick="toggleDarkMode()" class="header-icon-btn" title="Toggle dark mode">
                    <i data-lucide="moon" class="w-5 h-5" id="darkModeIcon"></i>
                </button>

                <!-- Divider -->
                <div class="w-px h-6 bg-gray-200 dark:bg-slate-700 mx-1 hidden sm:block"></div>

                <!-- Profile -->
                <a href="<?= htmlspecialchars($_layoutProfile) ?>" class="header-avatar">
                    <?php
                    $_hfImg = $_layoutUser['profile_image'] ?? '';
                    $_hfBase = strtolower(basename($_hfImg));
                    $_hfValid = $_hfImg !== '' && $_hfImg !== null && $_hfBase !== 'default.png' && $_hfBase !== 'profile.png';
                    if ($_hfValid):
                        ?>
                        <img src="<?= htmlspecialchars($_layoutAvatar) ?>" class="w-8 h-8 rounded-lg object-cover border-2 border-gray-100 dark:border-slate-600" alt="Avatar">
                    <?php
                    else:
                        $_hfInit = '';
                        foreach (explode(' ', trim($_layoutUser['name'] ?? 'User')) as $_w) {
                            if ($_w !== '')
                                $_hfInit .= strtoupper($_w[0]);
                        }
                        $_hfInit = substr($_hfInit, 0, 2);
                        ?>
                        <div class="w-8 h-8 rounded-lg bg-blue-100 text-blue-600 flex items-center justify-center font-semibold text-xs border-2 border-gray-100 dark:border-slate-600"><?= $_hfInit ?></div>
                    <?php endif; ?>
                    <span class="text-xs font-semibold text-gray-700 dark:text-slate-300 hidden lg:block max-w-[100px] truncate"><?= htmlspecialchars($_layoutUser['name'] ?? 'User') ?></span>
                </a>
            </div>
        </header>

        <!-- Page Content -->
        <main class="p-5 flex flex-col gap-5">
<script>
lucide.createIcons();
document.querySelectorAll('svg[data-lucide]').forEach(function(svg) {
    svg.removeAttribute('width');svg.removeAttribute('height');
    svg.style.removeProperty('width');svg.style.removeProperty('height');
    var p = svg.parentElement;
    if (p && p.tagName === 'I') { var fs = window.getComputedStyle(p).fontSize; svg.style.width = fs; svg.style.height = fs; }
});
</script>
