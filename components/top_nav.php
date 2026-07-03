<?php
/**
 * Modern Top Navigation – Pure Tailwind CSS
 * No external CSS files required. All styles via Tailwind + minimal inline <style>.
 *
 * Expects:
 *   $user (assoc array: name, profile_image)
 *   $activePage (string) – current page key
 *   $navItems (array) – optional override
 *   $pageTitle, $pageSubtitle, $unreadCount, $profileLink
 */
$_navUser = $user ?? ['name' => 'User', 'profile_image' => ''];
$_navAvatar = get_profile_image($_navUser['profile_image']);
$_navActive = $activePage ?? 'dashboard';
$_navUnread = $unreadCount ?? 0;
$_navProfile = $profileLink ?? 'profile.php';
$_navItems = $navItems ?? [];
?>
<!DOCTYPE html>
<html lang="en" class="scroll-smooth">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width,initial-scale=1.0">
    <title><?= htmlspecialchars($pageTitle ?? 'Dashboard') ?> – FreelanceHub</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800;900&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js"></script>
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
        body { font-family: 'Inter', sans-serif; background: #F4F7FC; color: #1e293b; }
        html.dark body { background: #0f172a; color: #e2e8f0; }

        /* Dashboard card */
        .dh-card { background: #fff; border: 1px solid #E5EDF6; border-radius: 20px; box-shadow: 0 4px 20px rgba(15,23,42,.05); transition: box-shadow .2s; }
        .dh-card:hover { box-shadow: 0 8px 30px rgba(15,23,42,.08); }
        html.dark .dh-card { background: #1e293b; border-color: #334155; }

        /* Stat card */
        .stat-card { background: #fff; border: 1px solid #E5EDF6; border-left: 4px solid var(--accent, #2563EB); border-radius: 14px; padding: 16px 18px; transition: all .2s; }
        .stat-card:hover { transform: translateY(-2px); box-shadow: 0 6px 20px rgba(15,23,42,.07); }
        html.dark .stat-card { background: #1e293b; border-color: #334155; border-left-color: var(--accent, #3b82f6); }

        /* Quick action */
        .qa-card { background: #fff; border: 1px solid #E5EDF6; border-radius: 20px; padding: 24px; transition: all .25s; }
        .qa-card:hover { transform: translateY(-3px); box-shadow: 0 12px 32px rgba(15,23,42,.08); }
        html.dark .qa-card { background: #1e293b; border-color: #334155; }

        /* Badge */
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

        /* Progress bar */
        .pbar { width: 100%; height: 6px; background: #E5EDF6; border-radius: 999px; overflow: hidden; }
        .pbar-fill { height: 100%; border-radius: 999px; background: linear-gradient(90deg,#2563eb,#3b82f6); }
        .pbar-fill.green { background: linear-gradient(90deg,#059669,#10b981); }
        html.dark .pbar { background: #334155; }

        /* Data table */
        .dtbl { width: 100%; border-collapse: separate; border-spacing: 0; }
        .dtbl thead th { text-align: left; padding: 0 12px 10px; font-size: 11px; font-weight: 600; color: #94a3b8; text-transform: uppercase; letter-spacing: .04em; border-bottom: 1px solid #E5EDF6; }
        .dtbl tbody td { padding: 12px; font-size: 13px; border-bottom: 1px solid #f1f5f9; }
        .dtbl tbody tr:last-child td { border-bottom: none; }
        .dtbl tbody tr:hover { background: #f8fafc; }
        html.dark .dtbl thead th { border-bottom-color: #334155; color: #64748b; }
        html.dark .dtbl tbody td { border-bottom-color: rgba(51,65,85,.4); }
        html.dark .dtbl tbody tr:hover { background: rgba(51,65,85,.25); }

        /* Timeline */
        .tl-item { position: relative; padding-left: 40px; padding-bottom: 20px; }
        .tl-item::before { content:''; position: absolute; left: 15px; top: 28px; bottom: 0; width: 2px; background: #E5EDF6; }
        .tl-item:last-child::before { display: none; }
        .tl-dot { position: absolute; left: 6px; top: 4px; width: 20px; height: 20px; border-radius: 50%; display: flex; align-items: center; justify-content: center; font-size: 9px; }
        html.dark .tl-item::before { background: #334155; }

        /* Nav link */
        .nav-lnk { transition: all .2s; border-radius: 10px; padding: 8px 14px; font-size: 13px; font-weight: 600; color: #64748b; text-decoration: none; display: flex; align-items: center; gap: 7px; white-space: nowrap; }
        .nav-lnk:hover { background: #EEF5FF; color: #2563EB; }
        .nav-lnk.active { background: #2563EB; color: #fff; box-shadow: 0 2px 8px rgba(37,99,235,.3); }

        /* Gradient text */
        .gtxt { background: linear-gradient(135deg,#2563eb,#14b8a6,#6366f1); -webkit-background-clip: text; -webkit-text-fill-color: transparent; background-clip: text; }

        /* Fade in */
        @keyframes fadeIn { from { opacity:0; transform: translateY(10px); } to { opacity:1; transform: translateY(0); } }
        .fade-in { animation: fadeIn .4s ease forwards; opacity: 0; }

        /* Scrollbar */
        ::-webkit-scrollbar { width: 6px; height: 6px; }
        ::-webkit-scrollbar-track { background: transparent; }
        ::-webkit-scrollbar-thumb { background: #cbd5e1; border-radius: 999px; }
        html.dark ::-webkit-scrollbar-thumb { background: #475569; }

        /* Mobile nav */
        .mob-nav { display: none; position: fixed; top: 0; left: 0; right: 0; background: #fff; z-index: 50; padding: 16px; border-bottom: 1px solid #E5EDF6; transform: translateY(-100%); transition: transform .3s; }
        .mob-nav.show { transform: translateY(0); }
        .mob-overlay { display: none; position: fixed; inset: 0; background: rgba(0,0,0,.4); z-index: 40; }
        @media (max-width: 1023px) { .mob-nav { display: block; } }
    </style>
</head>
<body class="min-h-screen">

<!-- Top Nav -->
<nav class="bg-white border-b border-gray-100 sticky top-0 z-30 dark:bg-slate-800 dark:border-slate-700">
    <div class="max-w-[1400px] mx-auto px-6 flex items-center justify-between h-16">
        <!-- Left -->
        <div class="flex items-center gap-8">
            <a href="../index.php" class="flex items-center gap-2.5 no-underline">
                <div class="w-9 h-9 rounded-xl bg-gradient-to-br from-blue-600 to-purple-600 flex items-center justify-center shadow-lg shadow-blue-500/25">
                    <i class="fas fa-bolt text-white text-sm"></i>
                </div>
                <span class="text-lg font-extrabold tracking-tight text-gray-900 dark:text-white">Freelance<span class="text-blue-600">Hub</span></span>
            </a>
            <div class="hidden lg:flex items-center gap-1">
                <?php foreach ($_navItems as $item): ?>
                    <a href="<?= $item['url'] ?>" class="nav-lnk <?= $_navActive === $item['key'] ? 'active' : '' ?>">
                        <i class="fas <?= $item['icon'] ?> text-[13px]"></i>
                        <?= $item['label'] ?>
                    </a>
                <?php endforeach; ?>
            </div>
        </div>
        <!-- Right -->
        <div class="flex items-center gap-2">
            <button onclick="toggleMobileNav()" class="lg:hidden w-10 h-10 rounded-xl flex items-center justify-center text-gray-500 hover:bg-gray-100 dark:hover:bg-slate-700">
                <i class="fas fa-bars text-lg"></i>
            </button>
            <!-- Search -->
            <div class="relative hidden md:block">
                <i class="fas fa-search absolute left-3 top-1/2 -translate-y-1/2 text-gray-400 text-sm pointer-events-none"></i>
                <input type="text" placeholder="Search..." class="w-56 py-2 pl-9 pr-3 rounded-xl border border-gray-200 bg-gray-50 text-sm text-gray-700 outline-none focus:border-blue-500 focus:ring-2 focus:ring-blue-100 dark:bg-slate-900 dark:border-slate-600 dark:text-slate-300 transition">
            </div>
            <!-- Notifications -->
            <a href="../shared/notifications.php" class="relative w-10 h-10 rounded-xl flex items-center justify-center text-gray-500 hover:bg-gray-100 dark:text-slate-400 dark:hover:bg-slate-700 no-underline">
                <i class="fas fa-bell text-base"></i>
                <?php if ($_navUnread > 0): ?>
                    <span class="absolute top-2 right-2 w-2 h-2 bg-red-500 rounded-full border-2 border-white dark:border-slate-800"></span>
                <?php endif; ?>
            </a>
            <!-- Dark mode -->
            <button onclick="toggleDarkMode()" class="w-10 h-10 rounded-xl flex items-center justify-center text-gray-500 hover:bg-gray-100 dark:text-slate-400 dark:hover:bg-slate-700" title="Toggle dark mode">
                <i class="fas fa-moon text-sm" id="darkModeIcon"></i>
            </button>
            <!-- Profile -->
            <a href="<?= htmlspecialchars($_navProfile) ?>" class="flex items-center gap-2.5 py-1.5 px-2 rounded-xl hover:bg-gray-100 dark:hover:bg-slate-700 no-underline transition">
                <img src="<?= htmlspecialchars($_navAvatar) ?>" class="w-9 h-9 rounded-xl object-cover border-2 border-gray-100 dark:border-slate-600" alt="Avatar">
                <span class="text-sm font-semibold text-gray-900 dark:text-white hidden sm:block"><?= htmlspecialchars($_navUser['name'] ?? 'User') ?></span>
            </a>
        </div>
    </div>
</nav>

<!-- Mobile Nav -->
<div id="mobOverlay" class="mob-overlay" onclick="toggleMobileNav()"></div>
<div id="mobNav" class="mob-nav dark:bg-slate-800 dark:border-slate-700">
    <div class="flex items-center justify-between mb-4">
        <span class="text-base font-bold text-gray-900 dark:text-white">Menu</span>
        <button onclick="toggleMobileNav()" class="w-9 h-9 rounded-xl bg-gray-100 dark:bg-slate-700 flex items-center justify-center">
            <i class="fas fa-times text-gray-500 dark:text-slate-400"></i>
        </button>
    </div>
    <div class="flex flex-col gap-1">
        <?php foreach ($_navItems as $item): ?>
            <a href="<?= $item['url'] ?>" class="flex items-center gap-3 px-4 py-3 rounded-xl text-sm font-semibold <?= $_navActive === $item['key'] ? 'bg-blue-600 text-white' : 'text-gray-600 bg-gray-50 dark:bg-slate-700 dark:text-slate-300' ?>">
                <i class="fas <?= $item['icon'] ?> w-5 text-center"></i>
                <?= $item['label'] ?>
            </a>
        <?php endforeach; ?>
        <a href="../auth/logout.php" class="flex items-center gap-3 px-4 py-3 rounded-xl text-sm font-semibold text-red-600 bg-red-50 dark:bg-red-900/20 dark:text-red-400 mt-2">
            <i class="fas fa-sign-out-alt w-5 text-center"></i> Logout
        </a>
    </div>
</div>

<script>
function toggleMobileNav() {
    document.getElementById('mobNav').classList.toggle('show');
    document.getElementById('mobOverlay').style.display =
        document.getElementById('mobNav').classList.contains('show') ? 'block' : 'none';
}
function toggleDarkMode() {
    document.documentElement.classList.toggle('dark');
    const icon = document.getElementById('darkModeIcon');
    if (icon) icon.className = document.documentElement.classList.contains('dark') ? 'fas fa-sun text-sm' : 'fas fa-moon text-sm';
}
</script>
<script src="/finalproject/shared/dark-toggle.js"></script>
