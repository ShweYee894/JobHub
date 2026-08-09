<?php

/**
 * Admin Wallets — Read-only overview of every user's wallet
 * Display: User, Role, Balance, Deposits, Escrow, Released, Refunds, Withdrawals, Transactions
 */
require_once __DIR__ . '/../auth/auth.php';
require_role('admin');
require_once __DIR__ . '/../config/db.php';

$currentPage = 'wallets';

// ── Filters ─────────────────────────────────────────────────────────
$search = trim($_GET['search'] ?? '');
$roleF = $_GET['role'] ?? '';
$page = max(1, sanitize_int($_GET['page'] ?? 1));
$perPage = 15;

$allowedRoles = ['client', 'freelancer', 'admin'];
if ($roleF && !in_array($roleF, $allowedRoles))
    $roleF = '';

// ── Build query ─────────────────────────────────────────────────────
$where = [];
$params = [];
$types = '';

if ($search !== '') {
    $where[] = '(u.name LIKE ? OR u.email LIKE ?)';
    $searchTerm = "%{$search}%";
    $params[] = $searchTerm;
    $params[] = $searchTerm;
    $types .= 'ss';
}
if ($roleF) {
    $where[] = 'u.role = ?';
    $params[] = $roleF;
    $types .= 's';
}

$whereSql = $where ? 'WHERE ' . implode(' AND ', $where) : '';

// ── Count ───────────────────────────────────────────────────────────
$countSql = "SELECT COUNT(*) AS cnt FROM users u {$whereSql}";
$countStmt = $conn->prepare($countSql);
if ($params)
    $countStmt->bind_param($types, ...$params);
$countStmt->execute();
$totalItems = (int) $countStmt->get_result()->fetch_assoc()['cnt'];
$countStmt->close();

$pagination = paginate($totalItems, $perPage, $page);

// ── Fetch users ─────────────────────────────────────────────────────
$querySql = "SELECT u.id, u.name, u.email, u.profile_image, u.role, u.status, u.wallet_balance, u.wallet_status
             FROM users u {$whereSql}
             ORDER BY u.wallet_balance DESC
             LIMIT ? OFFSET ?";
$queryStmt = $conn->prepare($querySql);
$bindTypes = $types . 'ii';
$bindParams = array_merge($params, [$perPage, $pagination['offset']]);
$queryStmt->bind_param($bindTypes, ...$bindParams);
$queryStmt->execute();
$usersResult = $queryStmt->get_result();
$queryStmt->close();

$userIds = [];
$userMap = [];
while ($row = $usersResult->fetch_assoc()) {
    $userIds[] = (int) $row['id'];
    $userMap[(int) $row['id']] = $row;
}
$usersResult->free();

// ── Batch: aggregate wallet transactions per user ───────────────────
$txAgg = [];
$txCounts = [];

if ($userIds) {
    $idPH = implode(',', array_fill(0, count($userIds), '?'));

    // Sum by type per user
    $as = $conn->prepare("SELECT user_id, type,
                                 SUM(amount) AS total,
                                 COUNT(*) AS cnt
                          FROM wallet_transactions WHERE user_id IN ({$idPH})
                          GROUP BY user_id, type");
    $as->bind_param(str_repeat('i', count($userIds)), ...$userIds);
    $as->execute();
    $asRes = $as->get_result();
    while ($r = $asRes->fetch_assoc()) {
        $uid = (int) $r['user_id'];
        $type = $r['type'];
        if (!isset($txAgg[$uid]))
            $txAgg[$uid] = ['deposit' => 0, 'signup_bonus' => 0, 'escrow_hold' => 0, 'escrow_release' => 0, 'refund' => 0, 'withdrawal' => 0, 'platform_fee' => 0];
        if (!isset($txCounts[$uid]))
            $txCounts[$uid] = 0;

        $txAgg[$uid][$type] = (float) $r['total'];
        $txCounts[$uid] += (int) $r['cnt'];
    }
    $as->close();

    // Total transaction count per user (all types)
    $tc = $conn->prepare("SELECT user_id, COUNT(*) AS cnt FROM wallet_transactions WHERE user_id IN ({$idPH}) GROUP BY user_id");
    $tc->bind_param(str_repeat('i', count($userIds)), ...$userIds);
    $tc->execute();
    $tcRes = $tc->get_result();
    while ($r = $tcRes->fetch_assoc())
        $txCounts[(int) $r['user_id']] = (int) $r['cnt'];
    $tc->close();
}

// ── Platform totals ─────────────────────────────────────────────────
$pt = $conn->query("SELECT
    COALESCE(SUM(wallet_balance), 0) AS total_balance,
    COUNT(*) AS total_users
    FROM users WHERE role != 'admin'");
$platformTotals = $pt->fetch_assoc();

$pt2 = $conn->query('SELECT type, SUM(amount) AS total FROM wallet_transactions GROUP BY type');
$platformTx = [];
while ($r = $pt2->fetch_assoc())
    $platformTx[$r['type']] = (float) $r['total'];

// ── Badge maps ──────────────────────────────────────────────────────
$roleColors = [
    'client' => 'bg-blue-100 text-blue-700 dark:bg-blue-900/30 dark:text-blue-400',
    'freelancer' => 'bg-emerald-100 text-emerald-700 dark:bg-emerald-900/30 dark:text-emerald-400',
    'admin' => 'bg-purple-100 text-purple-700 dark:bg-purple-900/30 dark:text-purple-400',
];

// ── Build base URL ──────────────────────────────────────────────────
$baseUrl = 'wallets.php?';
if ($search !== '')
    $baseUrl .= 'search=' . urlencode($search) . '&';
if ($roleF)
    $baseUrl .= 'role=' . urlencode($roleF) . '&';
$baseUrl = rtrim($baseUrl, '?&');
if (strpos($baseUrl, '&') === false)
    $baseUrl = rtrim($baseUrl, '?');

// ── Admin nav ───────────────────────────────────────────────────────
$_userId = $_SESSION['user_id'];
$sStmt = $conn->prepare('SELECT name, profile_image FROM users WHERE id = ?');
$sStmt->bind_param('i', $_userId);
$sStmt->execute();
$_navUserRow = $sStmt->get_result()->fetch_assoc();
$sStmt->close();
$adminName = $_navUserRow['name'] ?? 'Admin';

$conn->close();

$navItems = [
    ['key' => 'dashboard', 'label' => 'Dashboard', 'url' => 'dashboard.php', 'icon' => 'layout-grid'],
    ['key' => 'users', 'label' => 'Users', 'url' => 'users.php', 'icon' => 'users'],
    ['key' => 'clients', 'label' => 'Clients', 'url' => 'clients.php', 'icon' => 'user'],
    ['key' => 'jobs', 'label' => 'Jobs', 'url' => 'jobs.php', 'icon' => 'briefcase'],
    ['key' => 'categories', 'label' => 'Categories', 'url' => 'categories.php', 'icon' => 'folder-open'],
    ['key' => 'skills', 'label' => 'Skills', 'url' => 'skills.php', 'icon' => 'settings'],
    ['key' => 'payments', 'label' => 'Payments', 'url' => 'payments.php', 'icon' => 'credit-card'],
    ['key' => 'wallets', 'label' => 'Wallets', 'url' => 'wallets.php', 'icon' => 'wallet'],
    ['key' => 'contracts', 'label' => 'Contracts', 'url' => 'contracts.php', 'icon' => 'file-text'],
    ['key' => 'milestones', 'label' => 'Milestones', 'url' => 'milestones.php', 'icon' => 'list-checks'],
    ['key' => 'reviews', 'label' => 'Reviews', 'url' => 'reviews.php', 'icon' => 'star'],
    ['key' => 'disputes', 'label' => 'Disputes', 'url' => 'disputes.php', 'icon' => 'hammer'],
    ['key' => 'fraud', 'label' => 'Fraud', 'url' => 'fraud_detection.php', 'icon' => 'shield'],
    ['key' => 'notifications', 'label' => 'Notifications', 'url' => 'notifications.php', 'icon' => 'bell'],
    ['key' => 'ai_monitor', 'label' => 'AI Monitor', 'url' => 'ai_monitor.php', 'icon' => 'brain'],
    ['key' => 'analytics', 'label' => 'Analytics', 'url' => 'analytics.php', 'icon' => 'pie-chart'],
    ['key' => 'settings', 'label' => 'Settings', 'url' => 'settings.php', 'icon' => 'settings'],
];
$pageTitle = 'Wallets';
$pageSubtitle = number_format($totalItems) . ' user' . ($totalItems !== 1 ? 's' : '') . ' with wallets';
$activePage = 'wallets';
$user = ['name' => $adminName, 'profile_image' => $_navUserRow['profile_image'] ?? null];
$unreadCount = 0;
$profileLink = 'profile.php';
require_once __DIR__ . '/../components/layout_start.php';
?>
    <style>
    .kpi-mini{transition:transform .2s,box-shadow .2s}
    .kpi-mini:hover{transform:translateY(-1px);box-shadow:0 4px 15px rgba(0,0,0,.05)}
    .amt-credit{color:rgb(16 185 129)}
    .amt-debit{color:rgb(239 68 68)}
    .zero-muted{color:#9CA3AF}
    .dark .zero-muted{color:#475569}

    /* Actions Dropdown */
    .actions-dropdown{position:relative}
    .actions-trigger{width:32px;height:32px;display:inline-flex;align-items:center;justify-content:center;border-radius:6px;border:1px solid #E4EBE4;background:#fff;color:#6b7280;cursor:pointer;transition:all .15s}
    .actions-trigger:hover{background:#f9fafb;color:#374151;border-color:#d1d5db}
    .dark .actions-trigger{border-color:#475569;background:#1e293b;color:#94a3b8}
    .dark .actions-trigger:hover{background:#334155;color:#e2e8f0;border-color:#64748b}
    .actions-menu{position:absolute;right:0;top:calc(100% + 4px);min-width:200px;background:#fff;border:1px solid #E4EBE4;border-radius:8px;box-shadow:0 4px 24px rgba(0,0,0,.08);z-index:30;padding:4px;opacity:0;visibility:hidden;transform:translateY(-4px);transition:all .15s ease}
    .actions-menu.open{opacity:1;visibility:visible;transform:translateY(0)}
    .dark .actions-menu{background:#1e293b;border-color:#334155;box-shadow:0 4px 24px rgba(0,0,0,.3)}
    .actions-menu a,.actions-menu button{display:flex;align-items:center;gap:8px;width:100%;padding:8px 10px;border-radius:6px;font-size:13px;font-weight:500;color:#374151;text-decoration:none;border:none;background:none;cursor:pointer;text-align:left;transition:background .1s}
    .dark .actions-menu a,.dark .actions-menu button{color:#e2e8f0}
    .actions-menu a:hover,.actions-menu button:hover{background:#f3f4f6}
    .dark .actions-menu a:hover,.dark .actions-menu button:hover{background:#334155}
    .actions-menu a svg,.actions-menu button svg{width:14px;height:14px;flex-shrink:0;opacity:.6}
    .actions-divider{height:1px;background:#E4EBE4;margin:4px 0}
    .dark .actions-divider{background:#334155}
    .actions-danger{color:#EF4444!important}
    .dark .actions-danger{color:#f87171!important}
    .actions-danger svg{color:#EF4444!important;opacity:1!important}
    .dark .actions-danger svg{color:#f87171!important}
    .actions-danger:hover{background:#FEF2F2!important}
    .dark .actions-danger:hover{background:rgba(239,68,68,.1)!important}
    </style>

    <?php display_flash('success'); ?>
    <?php display_flash('error'); ?>

    <!-- ═══ PLATFORM STAT CARDS ════════════════════════════════════════ -->
    <div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-6 gap-3">
        <div class="kpi-mini fade-in rounded-lg p-4 border border-gray-200 dark:border-slate-700 bg-white dark:bg-slate-800 shadow-sm" style="animation-delay:0s">
            <div class="flex items-center gap-2 mb-2">
                <div class="flex items-center justify-center w-8 h-8 rounded-lg bg-emerald-100 dark:bg-emerald-900/30">
                    <svg class="w-4 h-4 text-emerald-600 dark:text-emerald-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 9V7a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2m2 4h10a2 2 0 002-2v-6a2 2 0 00-2-2H9a2 2 0 00-2 2v6a2 2 0 002 2zm7-5a2 2 0 11-4 0 2 2 0 014 0z"/></svg>
                </div>
                <p class="text-xs font-semibold text-gray-400 dark:text-slate-500 uppercase tracking-wider">Balance</p>
            </div>
            <p class="text-xl font-extrabold text-gray-900 dark:text-white"><?= format_currency((float) $platformTotals['total_balance']) ?></p>
        </div>
        <div class="kpi-mini fade-in rounded-lg p-4 border border-gray-200 dark:border-slate-700 bg-white dark:bg-slate-800 shadow-sm" style="animation-delay:.03s">
            <div class="flex items-center gap-2 mb-2">
                <div class="flex items-center justify-center w-8 h-8 rounded-lg bg-blue-100 dark:bg-blue-900/30">
                    <svg class="w-4 h-4 text-blue-600 dark:text-blue-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z"/></svg>
                </div>
                <p class="text-xs font-semibold text-gray-400 dark:text-slate-500 uppercase tracking-wider">Users</p>
            </div>
            <p class="text-xl font-extrabold text-blue-600 dark:text-blue-400"><?= number_format((int) $platformTotals['total_users']) ?></p>
        </div>
        <div class="kpi-mini fade-in rounded-lg p-4 border border-gray-200 dark:border-slate-700 bg-white dark:bg-slate-800 shadow-sm" style="animation-delay:.06s">
            <div class="flex items-center gap-2 mb-2">
                <div class="flex items-center justify-center w-8 h-8 rounded-lg bg-emerald-100 dark:bg-emerald-900/30">
                    <svg class="w-4 h-4 text-emerald-600 dark:text-emerald-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                </div>
                <p class="text-xs font-semibold text-gray-400 dark:text-slate-500 uppercase tracking-wider">Deposits</p>
            </div>
            <p class="text-xl font-extrabold text-emerald-600 dark:text-emerald-400"><?= format_currency(($platformTx['deposit'] ?? 0) + ($platformTx['signup_bonus'] ?? 0)) ?></p>
        </div>
        <div class="kpi-mini fade-in rounded-lg p-4 border border-gray-200 dark:border-slate-700 bg-white dark:bg-slate-800 shadow-sm" style="animation-delay:.09s">
            <div class="flex items-center gap-2 mb-2">
                <div class="flex items-center justify-center w-8 h-8 rounded-lg bg-amber-100 dark:bg-amber-900/30">
                    <svg class="w-4 h-4 text-amber-600 dark:text-amber-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"/></svg>
                </div>
                <p class="text-xs font-semibold text-gray-400 dark:text-slate-500 uppercase tracking-wider">Escrow</p>
            </div>
            <p class="text-xl font-extrabold text-amber-600 dark:text-amber-400"><?= format_currency($platformTx['escrow_hold'] ?? 0) ?></p>
        </div>
        <div class="kpi-mini fade-in rounded-lg p-4 border border-gray-200 dark:border-slate-700 bg-white dark:bg-slate-800 shadow-sm" style="animation-delay:.12s">
            <div class="flex items-center gap-2 mb-2">
                <div class="flex items-center justify-center w-8 h-8 rounded-lg bg-violet-100 dark:bg-violet-900/30">
                    <svg class="w-4 h-4 text-violet-600 dark:text-violet-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                </div>
                <p class="text-xs font-semibold text-gray-400 dark:text-slate-500 uppercase tracking-wider">Released</p>
            </div>
            <p class="text-xl font-extrabold text-violet-600 dark:text-violet-400"><?= format_currency($platformTx['escrow_release'] ?? 0) ?></p>
        </div>
        <div class="kpi-mini fade-in rounded-lg p-4 border border-gray-200 dark:border-slate-700 bg-white dark:bg-slate-800 shadow-sm" style="animation-delay:.15s">
            <div class="flex items-center gap-2 mb-2">
                <div class="flex items-center justify-center w-8 h-8 rounded-lg bg-red-100 dark:bg-red-900/30">
                    <svg class="w-4 h-4 text-red-600 dark:text-red-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 9V7a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2m2 4h10a2 2 0 002-2v-6a2 2 0 00-2-2H9a2 2 0 00-2 2v6a2 2 0 002 2zm7-5a2 2 0 11-4 0 2 2 0 014 0z"/></svg>
                </div>
                <p class="text-xs font-semibold text-gray-400 dark:text-slate-500 uppercase tracking-wider">Withdrawals</p>
            </div>
            <p class="text-xl font-extrabold text-red-600 dark:text-red-400"><?= format_currency($platformTx['withdrawal'] ?? 0) ?></p>
        </div>
    </div>

    <!-- ═══ SEARCH + WALLET TABLE ══════════════════════════════════════ -->
    <div class="bg-white dark:bg-slate-800 border border-gray-200 dark:border-slate-700 rounded-lg fade-in" style="animation-delay:.18s">
        <!-- Search Toolbar -->
        <div class="p-4 border-b border-gray-100 dark:border-slate-700">
            <form method="GET" action="wallets.php">
                <div class="flex flex-col lg:flex-row gap-3">
                    <div class="flex-1 relative">
                        <i data-lucide="search" class="absolute left-3.5 top-1/2 -translate-y-1/2 text-gray-400 dark:text-slate-500 text-sm"></i>
                        <input type="text" name="search" value="<?= sanitize_string($search) ?>" placeholder="Search by name or email..."
                               class="w-full pl-10 pr-4 py-2 rounded-lg border border-gray-200 dark:border-slate-600 text-sm bg-gray-50 dark:bg-slate-700 text-gray-900 dark:text-white placeholder-gray-400 dark:placeholder-slate-500 focus:outline-none focus:ring-2 focus:ring-blue-500/30 focus:border-blue-400 transition-all">
                    </div>
                    <div class="flex flex-wrap gap-2">
                        <select name="role" class="px-3 py-2 rounded-lg border border-gray-200 dark:border-slate-600 text-sm text-gray-600 dark:text-slate-300 focus:outline-none focus:ring-2 focus:ring-blue-500/30 focus:border-blue-400 bg-gray-50 dark:bg-slate-700">
                            <option value="">All Roles</option>
                            <option value="client" <?= $roleF === 'client' ? 'selected' : '' ?>>Client</option>
                            <option value="freelancer" <?= $roleF === 'freelancer' ? 'selected' : '' ?>>Freelancer</option>
                        </select>
                        <button type="submit" class="px-4 py-2 rounded-lg bg-blue-600 hover:bg-blue-700 text-white text-sm font-semibold transition-colors inline-flex items-center gap-1.5 shadow-lg shadow-blue-500/20">
                            <i data-lucide="filter" class="text-xs"></i> Filter
                        </button>
                        <?php if ($search || $roleF): ?>
                            <a href="wallets.php" class="px-3 py-2 rounded-lg border border-gray-200 dark:border-slate-600 text-sm font-medium text-gray-500 dark:text-slate-400 hover:bg-gray-50 dark:hover:bg-slate-700 transition-colors text-center">
                                <i data-lucide="x" class="mr-1"></i> Clear
                            </a>
                        <?php endif; ?>
                    </div>
                </div>
            </form>
        </div>

        <!-- Table -->
        <?php if (count($userMap) > 0): ?>
        <table class="w-full text-sm">
                <thead>
                    <tr class="bg-gray-50 dark:bg-slate-700/50">
                        <th class="text-left px-3 py-2.5 text-[10px] font-semibold text-gray-500 dark:text-slate-400 uppercase tracking-wider">User</th>
                        <th class="text-left px-3 py-2.5 text-[10px] font-semibold text-gray-500 dark:text-slate-400 uppercase tracking-wider">Role</th>
                        <th class="text-left px-3 py-2.5 text-[10px] font-semibold text-gray-500 dark:text-slate-400 uppercase tracking-wider">Status</th>
                        <th class="text-right px-3 py-2.5 text-[10px] font-semibold text-gray-500 dark:text-slate-400 uppercase tracking-wider">Deposits</th>
                        <th class="text-right px-3 py-2.5 text-[10px] font-semibold text-gray-500 dark:text-slate-400 uppercase tracking-wider">Balance</th>
                        <th class="text-center px-3 py-2.5 text-[10px] font-semibold text-gray-500 dark:text-slate-400 uppercase tracking-wider">Actions</th>
                    </tr>
                </thead>
                <tbody>
                <?php
                foreach ($userMap as $uid => $u):
                    $agg = $txAgg[$uid] ?? ['deposit' => 0, 'escrow_hold' => 0, 'escrow_release' => 0, 'refund' => 0, 'withdrawal' => 0];
                    $txCount = $txCounts[$uid] ?? 0;
                    $depTotal = ($agg['deposit'] ?? 0) + ($agg['signup_bonus'] ?? 0);
                    $escTotal = $agg['escrow_hold'] ?? 0;
                    $relTotal = $agg['escrow_release'] ?? 0;
                    $refTotal = $agg['refund'] ?? 0;
                    $witTotal = $agg['withdrawal'] ?? 0;
                    ?>
                    <tr class="border-b border-gray-50 dark:border-slate-700/50 last:border-0 hover:bg-gray-50/50 dark:hover:bg-slate-700/30 transition-colors">
                        <td class="px-3 py-3">
                            <a href="user_detail.php?id=<?= $uid ?>" class="flex items-center gap-3 no-underline text-inherit">
                                <?php
                                $_wi = $u['profile_image'] ?? '';
                                $_wb = strtolower(basename($_wi));
                                $_wv = $_wi !== '' && $_wi !== null && $_wb !== 'default.png' && $_wb !== 'profile.png';
                                if ($_wv):
                                ?>
                                <img src="<?= sanitize_string(get_profile_image($_wi)) ?>" class="w-9 h-9 rounded-full object-cover border border-gray-100 dark:border-slate-600 flex-shrink-0">
                                <?php else:
                                    $_win = '';
                                    foreach (explode(' ', trim($u['name'] ?? '')) as $_ww) { if ($_ww !== '') $_win .= strtoupper($_ww[0]); }
                                    $_win = substr($_win, 0, 2);
                                ?>
                                <div class="w-9 h-9 rounded-full bg-blue-100 text-blue-600 flex items-center justify-center font-semibold text-[11px] flex-shrink-0 border border-gray-100 dark:border-slate-600"><?= $_win ?></div>
                                <?php endif; ?>
                                <div class="min-w-0">
                                    <p class="font-semibold text-gray-900 dark:text-white truncate text-sm leading-snug"><?= sanitize_string($u['name']) ?></p>
                                    <p class="text-xs text-gray-400 dark:text-slate-500 truncate leading-snug"><?= sanitize_string($u['email']) ?></p>
                                </div>
                            </a>
                        </td>
                        <td class="px-3 py-3">
                            <span class="inline-block px-2 py-0.5 rounded text-xs font-semibold <?= $roleColors[$u['role']] ?? '' ?>"><?= ucfirst($u['role']) ?></span>
                        </td>
                        <td class="px-3 py-3">
                            <?php $isActive = ($u['status'] ?? 'active') === 'active'; ?>
                            <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded text-xs font-semibold <?= $isActive ? 'bg-emerald-100 text-emerald-700 dark:bg-emerald-900/30 dark:text-emerald-400' : 'bg-red-100 text-red-700 dark:bg-red-900/30 dark:text-red-400' ?>">
                                <?= $isActive ? 'Active' : ucfirst($u['status'] ?? 'Active') ?>
                            </span>
                        </td>
                        <td class="px-3 py-3 text-right">
                            <span class="text-sm font-bold text-gray-900 dark:text-white"><?= format_currency($depTotal) ?></span>
                        </td>
                        <td class="px-3 py-3 text-right">
                            <span class="text-sm font-bold text-gray-900 dark:text-white"><?= format_currency((float) $u['wallet_balance']) ?></span>
                        </td>
                        <td class="px-3 py-3">
                            <div class="flex items-center justify-center">
                                <div class="actions-dropdown">
                                    <button type="button" class="actions-trigger" onclick="toggleDropdown(event, this)" aria-haspopup="true" aria-expanded="false">
                                        <svg width="16" height="16" viewBox="0 0 16 16" fill="currentColor"><circle cx="8" cy="3" r="1.5"/><circle cx="8" cy="8" r="1.5"/><circle cx="8" cy="13" r="1.5"/></svg>
                                    </button>
                                    <div class="actions-menu">
                                        <a href="wallet_history.php?user_id=<?= $uid ?>">
                                            <svg fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"/></svg>
                                            View Transactions
                                        </a>
                                        <a href="wallet_history.php?user_id=<?= $uid ?>&export=1">
                                            <svg fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
                                            Download
                                        </a>
                                        <a href="wallet_history.php?user_id=<?= $uid ?>&action=adjust">
                                            <svg fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M12 6v12m6-6H6"/></svg>
                                            Adjust Balance
                                        </a>
                                        <a href="wallet_history.php?user_id=<?= $uid ?>&action=refund">
                                            <svg fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M3 10h10a8 8 0 018 8v2M3 10l6 6m-6-6l6-6"/></svg>
                                            Process Refund
                                        </a>
                                        <div class="actions-divider"></div>
                                        <a href="wallet_history.php?user_id=<?= $uid ?>&action=freeze" class="actions-danger">
                                            <svg fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"/></svg>
                                            Freeze
                                        </a>
                                    </div>
                                </div>
                            </div>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>

        <!-- Pagination inside the card -->
        <?php if ($pagination['total_pages'] > 1): ?>
        <?php $sep = strpos($baseUrl, '?') !== false ? '&' : '?'; ?>
        <div class="flex items-center justify-between px-4 py-3 border-t border-gray-100 dark:border-slate-700">
            <div class="flex items-center gap-2">
                <?php if ($pagination['has_prev']): ?>
                    <a href="<?= $baseUrl . $sep ?>page=<?= $pagination['current_page'] - 1 ?>" class="w-8 h-8 rounded-lg border border-gray-200 dark:border-slate-600 bg-white dark:bg-slate-800 flex items-center justify-center text-gray-500 dark:text-slate-400 hover:bg-gray-50 dark:hover:bg-slate-700 transition-colors">
                        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M15 19l-7-7 7-7"/></svg>
                    </a>
                <?php else: ?>
                    <span class="w-8 h-8 rounded-lg border border-gray-100 dark:border-slate-700 flex items-center justify-center text-gray-200 dark:text-slate-600 cursor-not-allowed">
                        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M15 19l-7-7 7-7"/></svg>
                    </span>
                <?php endif; ?>

                <?php
                $pages = [];
                $total = $pagination['total_pages'];
                $current = $pagination['current_page'];
                if ($total <= 7) {
                    for ($i = 1; $i <= $total; $i++) $pages[] = $i;
                } else {
                    $pages[] = 1;
                    if ($current > 3) $pages[] = '...';
                    $start = max(2, $current - 1);
                    $end = min($total - 1, $current + 1);
                    for ($i = $start; $i <= $end; $i++) $pages[] = $i;
                    if ($current < $total - 2) $pages[] = '...';
                    $pages[] = $total;
                }
                foreach ($pages as $p):
                    if ($p === '...'):
                ?>
                    <span class="w-8 h-8 flex items-center justify-center text-gray-400 dark:text-slate-500 text-xs">...</span>
                <?php else:
                    $active = $p === $current;
                ?>
                    <a href="<?= $baseUrl . $sep ?>page=<?= $p ?>" class="w-8 h-8 rounded-lg border text-xs font-semibold flex items-center justify-center transition-all <?= $active ? 'bg-[#108A00] border-[#108A00] text-white' : 'border-gray-200 dark:border-slate-600 bg-white dark:bg-slate-800 text-gray-600 dark:text-slate-300 hover:bg-gray-50 dark:hover:bg-slate-700' ?>"><?= $p ?></a>
                <?php endif;
                endforeach; ?>

                <?php if ($pagination['has_next']): ?>
                    <a href="<?= $baseUrl . $sep ?>page=<?= $pagination['current_page'] + 1 ?>" class="w-8 h-8 rounded-lg border border-gray-200 dark:border-slate-600 bg-white dark:bg-slate-800 flex items-center justify-center text-gray-500 dark:text-slate-400 hover:bg-gray-50 dark:hover:bg-slate-700 transition-colors">
                        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M9 5l7 7-7 7"/></svg>
                    </a>
                <?php else: ?>
                    <span class="w-8 h-8 rounded-lg border border-gray-100 dark:border-slate-700 flex items-center justify-center text-gray-200 dark:text-slate-600 cursor-not-allowed">
                        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M9 5l7 7-7 7"/></svg>
                    </span>
                <?php endif; ?>
            </div>

            <div class="flex items-center gap-2">
                <span class="text-xs text-gray-400 dark:text-slate-500 font-medium">Go to page</span>
                <form method="GET" action="wallets.php" class="flex items-center">
                    <?php foreach ($_GET as $key => $val): ?>
                        <?php if ($key !== 'page'): ?>
                            <input type="hidden" name="<?= htmlspecialchars($key) ?>" value="<?= htmlspecialchars($val) ?>">
                        <?php endif; ?>
                    <?php endforeach; ?>
                    <input type="number" name="page" value="<?= $pagination['current_page'] ?>" min="1" max="<?= $total ?>" class="w-12 h-8 text-center rounded-lg border border-gray-200 dark:border-slate-600 text-xs font-medium bg-white dark:bg-slate-800 text-gray-900 dark:text-white focus:outline-none focus:ring-2 focus:ring-[#108A00]/30 focus:border-[#108A00]">
                    <button type="submit" class="w-8 h-8 rounded-lg bg-[#108A00] hover:bg-[#0D7500] text-white flex items-center justify-center transition-colors ml-1">
                        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M9 5l7 7-7 7"/></svg>
                    </button>
                </form>
            </div>
        </div>
        <?php endif; ?>
        <?php else: ?>
        <div class="text-center py-16">
            <div class="w-16 h-16 rounded-2xl bg-gray-100 dark:bg-slate-700 flex items-center justify-center mx-auto mb-4">
                <i data-lucide="wallet" class="text-2xl text-gray-300 dark:text-slate-500"></i>
            </div>
            <p class="text-gray-500 dark:text-slate-400 text-sm mb-1">No users found</p>
            <p class="text-gray-400 dark:text-slate-500 text-xs">Try adjusting your search or filters</p>
        </div>
        <?php endif; ?>
    </div>

    <!-- ═══ AJAX DRAWER CONTAINER ═════════════════════════════════════ -->
    <div id="ajaxDrawerRoot"></div>

    <!-- ═══ NOTIFICATION ══════════════════════════════════════════════ -->
    <div id="ajaxNotif" class="adj-notif" style="display:none;position:fixed;top:20px;right:20px;z-index:100;padding:14px 20px;border-radius:10px;display:none;align-items:center;gap:10px;font-size:13px;font-weight:600;box-shadow:0 8px 24px rgba(0,0,0,.12);transform:translateX(120%);transition:transform .35s cubic-bezier(.4,0,.2,1)">
        <i data-lucide="circle-check"></i>
        <span id="ajaxNotifText"></span>
    </div>

    <style>
    /* Drawer overlay */
    .drawer-overlay{position:fixed;inset:0;background:rgba(0,0,0,.4);backdrop-filter:blur(4px);z-index:40;opacity:0;visibility:hidden;transition:all .25s}
    .drawer-overlay.open{opacity:1;visibility:visible}
    /* Drawer panel */
    .drawer-panel{position:fixed;top:0;right:0;bottom:0;width:100%;max-width:56rem;background:#fff;z-index:50;transform:translateX(100%);transition:transform .3s cubic-bezier(.4,0,.2,1);display:flex;flex-direction:column;overflow:hidden}
    html.dark .drawer-panel{background:#0f172a}
    .drawer-panel.open{transform:translateX(0)}
    @media(max-width:640px){.drawer-panel{max-width:100%}}
    /* Drawer header */
    .drawer-header{flex-shrink:0;padding:16px 24px;border-bottom:1px solid #E4EBE4;display:flex;align-items:center;justify-content:space-between;gap:12px}
    html.dark .drawer-header{border-color:#334155}
    /* Drawer body */
    .drawer-body{flex:1;overflow-y:auto;padding:24px}
    /* Close btn */
    .drawer-close{width:36px;height:36px;border-radius:8px;border:1px solid #E4EBE4;background:#fff;display:inline-flex;align-items:center;justify-content:center;cursor:pointer;color:#6b7280;transition:all .15s;flex-shrink:0}
    .drawer-close:hover{background:#f9fafb;color:#374151;border-color:#d1d5db}
    html.dark .drawer-close{border-color:#475569;background:#1e293b;color:#94a3b8}
    html.dark .drawer-close:hover{background:#334155;color:#e2e8f0}
    /* Filter chips */
    .filter-chip{display:inline-flex;align-items:center;gap:4px;padding:6px 12px;border-radius:6px;border:1px solid #E4EBE4;background:#fff;font-size:12px;font-weight:500;color:#6b7280;cursor:pointer;transition:all .15s;text-decoration:none}
    .filter-chip:hover{background:#f9fafb;border-color:#d1d5db}
    html.dark .filter-chip{border-color:#475569;background:#1e293b;color:#94a3b8}
    html.dark .filter-chip:hover{background:#334155;border-color:#64748b}
    .filter-chip.active{background:#108A00;border-color:#108A00;color:#fff}
    html.dark .filter-chip.active{background:#108A00;border-color:#108A00;color:#fff}
    /* TX table */
    .tx-drawer-table th{position:sticky;top:0;background:#F9FAFB;z-index:1}
    html.dark .tx-drawer-table th{background:#1e293b}
    .tx-drawer-table td,.tx-drawer-table th{padding:10px 12px}
    .tx-drawer-table tr{border-bottom:1px solid #f3f4f6;transition:background .1s}
    html.dark .tx-drawer-table tr{border-color:#1e293b}
    .tx-drawer-table tbody tr:hover{background:#f9fafb}
    html.dark .tx-drawer-table tbody tr:hover{background:rgba(30,41,59,.4)}
    /* Status badges */
    .status-badge{display:inline-flex;align-items:center;gap:4px;padding:2px 8px;border-radius:4px;font-size:10px;font-weight:600;text-transform:uppercase;letter-spacing:.03em}
    .status-completed{background:#dcfce7;color:#166534}
    .status-pending{background:#fef9c3;color:#854d0e}
    .status-processing{background:#dbeafe;color:#1e40af}
    .status-failed{background:#fee2e2;color:#991b1b}
    .status-refunded{background:#f3e8ff;color:#6b21a8}
    html.dark .status-completed{background:rgba(34,197,94,.15);color:#4ade80}
    html.dark .status-pending{background:rgba(234,179,8,.15);color:#facc15}
    html.dark .status-processing{background:rgba(59,130,246,.15);color:#60a5fa}
    html.dark .status-failed{background:rgba(239,68,68,.15);color:#f87171}
    html.dark .status-refunded{background:rgba(168,85,247,.15);color:#c084fc}
    /* Amount colors */
    .amt-positive{color:#059669}
    .amt-negative{color:#dc2626}
    html.dark .amt-positive{color:#34d399}
    html.dark .amt-negative{color:#f87171}
    /* Notification */
    .adj-notif{position:fixed;top:20px;right:20px;z-index:100;padding:14px 20px;border-radius:10px;display:flex;align-items:center;gap:10px;font-size:13px;font-weight:600;box-shadow:0 8px 24px rgba(0,0,0,.12);transform:translateX(120%);transition:transform .35s cubic-bezier(.4,0,.2,1)}
    .adj-notif.show{transform:translateX(0)}
    .adj-notif.success{background:#ecfdf5;border:1px solid #a7f3d0;color:#065f46}
    .adj-notif.error{background:#fef2f2;border:1px solid #fecaca;color:#991b1b}
    html.dark .adj-notif.success{background:rgba(16,185,129,.12);border-color:rgba(16,185,129,.25);color:#6ee7b7}
    html.dark .adj-notif.error{background:rgba(239,68,68,.12);border-color:rgba(239,68,68,.25);color:#fca5a5}
    /* Adjust modal overlay */
    .adj-modal-overlay{position:fixed;inset:0;background:rgba(0,0,0,.5);backdrop-filter:blur(4px);z-index:60;display:flex;align-items:center;justify-content:center;opacity:0;visibility:hidden;transition:all .2s}
    .adj-modal-overlay.open{opacity:1;visibility:visible}
    .adj-modal{background:#fff;border-radius:14px;width:90%;max-width:28rem;box-shadow:0 20px 60px rgba(0,0,0,.2);transform:scale(.95) translateY(10px);transition:transform .25s cubic-bezier(.4,0,.2,1)}
    html.dark .adj-modal{background:#1e293b;border:1px solid #334155}
    .adj-modal-overlay.open .adj-modal{transform:scale(1) translateY(0)}
    .adj-modal-header{padding:20px 24px 16px;border-bottom:1px solid #E4EBE4;display:flex;align-items:center;justify-content:space-between}
    html.dark .adj-modal-header{border-color:#334155}
    .adj-modal-header h3{font-size:15px;font-weight:700;color:#111827;margin:0}
    html.dark .adj-modal-header h3{color:#f1f5f9}
    .adj-modal-close{width:32px;height:32px;border-radius:8px;border:1px solid #E4EBE4;background:#fff;display:inline-flex;align-items:center;justify-content:center;cursor:pointer;color:#6b7280;transition:all .15s}
    .adj-modal-close:hover{background:#f9fafb;color:#374151}
    html.dark .adj-modal-close{border-color:#475569;background:#1e293b;color:#94a3b8}
    html.dark .adj-modal-close:hover{background:#334155;color:#e2e8f0}
    .adj-modal-body{padding:20px 24px}
    .adj-modal-footer{padding:16px 24px;border-top:1px solid #E4EBE4;display:flex;justify-content:flex-end;gap:10px}
    html.dark .adj-modal-footer{border-color:#334155}
    .adj-field{margin-bottom:16px}
    .adj-field-label{display:block;font-size:11px;font-weight:600;color:#6b7280;margin-bottom:5px;text-transform:uppercase;letter-spacing:.03em}
    html.dark .adj-field-label{color:#94a3b8}
    .adj-field input[type="number"],.adj-field input[type="text"],.adj-field textarea,.adj-field select{width:100%;padding:9px 12px;border-radius:8px;border:1px solid #d1d5db;font-size:13px;color:#111827;background:#f9fafb;transition:all .15s;outline:none}
    .adj-field input:focus,.adj-field textarea:focus,.adj-field select:focus{border-color:#108A00;box-shadow:0 0 0 3px rgba(16,138,0,.1)}
    html.dark .adj-field input,html.dark .adj-field textarea,html.dark .adj-field select{border-color:#475569;background:#0f172a;color:#e2e8f0}
    html.dark .adj-field input:focus,html.dark .adj-field textarea:focus,html.dark .adj-field select:focus{border-color:#108A00}
    .adj-field textarea{resize:vertical;min-height:60px}
    .adj-type-group{display:flex;gap:8px}
    .adj-type-btn{flex:1;padding:10px;border-radius:8px;border:1px solid #d1d5db;background:#f9fafb;cursor:pointer;text-align:center;font-size:12px;font-weight:600;color:#6b7280;transition:all .15s}
    .adj-type-btn:hover{border-color:#9ca3af}
    .adj-type-btn.selected{border-color:#108A00;background:#ecfdf5;color:#065f46}
    .adj-type-btn.selected.debit{border-color:#dc2626;background:#fef2f2;color:#991b1b}
    html.dark .adj-type-btn{border-color:#475569;background:#0f172a;color:#94a3b8}
    html.dark .adj-type-btn.selected{border-color:#108A00;background:rgba(16,138,0,.12);color:#6ee7b7}
    html.dark .adj-type-btn.selected.debit{border-color:#dc2626;background:rgba(220,38,38,.12);color:#fca5a5}
    .adj-check-row{display:flex;align-items:flex-start;gap:10px;padding:12px;border-radius:8px;background:#f9fafb;border:1px solid #e5e7eb}
    html.dark .adj-check-row{background:#0f172a;border-color:#334155}
    .adj-check-row input[type="checkbox"]{margin-top:2px;width:16px;height:16px;accent-color:#108A00;flex-shrink:0}
    .adj-check-label{font-size:12px;color:#374151;line-height:1.5}
    html.dark .adj-check-label{color:#cbd5e1}
    .adj-balance-preview{margin-top:12px;padding:12px;border-radius:8px;background:#f0fdf4;border:1px solid #bbf7d0;display:none}
    html.dark .adj-balance-preview{background:rgba(16,185,129,.08);border-color:rgba(16,185,129,.2)}
    .adj-balance-preview .preview-row{display:flex;justify-content:space-between;font-size:12px;margin-bottom:4px}
    .adj-balance-preview .preview-label{color:#6b7280;font-weight:500}
    html.dark .adj-balance-preview .preview-label{color:#94a3b8}
    .adj-balance-preview .preview-val{font-weight:700;color:#111827}
    html.dark .adj-balance-preview .preview-val{color:#f1f5f9}
    .adj-balance-preview .preview-val.new{color:#059669}
    /* Modal buttons */
    .btn-adj-cancel{padding:9px 18px;border-radius:8px;border:1px solid #d1d5db;background:#fff;font-size:12px;font-weight:600;color:#374151;cursor:pointer;transition:all .15s}
    .btn-adj-cancel:hover{background:#f9fafb;border-color:#9ca3af}
    html.dark .btn-adj-cancel{border-color:#475569;background:#1e293b;color:#cbd5e1}
    html.dark .btn-adj-cancel:hover{background:#334155}
    .btn-adj-save{padding:9px 18px;border-radius:8px;border:none;background:#d1d5db;font-size:12px;font-weight:600;color:#9ca3af;cursor:not-allowed;transition:all .15s}
    .btn-adj-save.enabled{background:#108A00;color:#fff;cursor:pointer;box-shadow:0 2px 8px rgba(16,138,0,.25)}
    .btn-adj-save.enabled:hover{background:#0d7500}
    /* Refund modal */
    .refund-badge{display:inline-flex;align-items:center;gap:4px;padding:3px 8px;border-radius:5px;font-size:10px;font-weight:700;text-transform:uppercase;letter-spacing:.03em}
    .refund-badge.escrow{background:#fef3c7;color:#92400e}
    .refund-badge.dispute{background:#dbeafe;color:#1e40af}
    html.dark .refund-badge.escrow{background:rgba(251,191,36,.15);color:#fbbf24}
    html.dark .refund-badge.dispute{background:rgba(96,165,250,.15);color:#60a5fa}
    .refund-info-grid{display:grid;grid-template-columns:1fr 1fr;gap:10px;margin-bottom:16px}
    .refund-info-item{padding:10px 12px;border-radius:8px;background:#f9fafb;border:1px solid #e5e7eb}
    html.dark .refund-info-item{background:#0f172a;border-color:#334155}
    .refund-info-label{font-size:9px;font-weight:700;color:#9ca3af;text-transform:uppercase;letter-spacing:.04em;margin-bottom:2px}
    html.dark .refund-info-label{color:#64748b}
    .refund-info-value{font-size:13px;font-weight:600;color:#111827}
    html.dark .refund-info-value{color:#f1f5f9}
    .refund-info-value.amount{color:#059669;font-size:15px;font-weight:800}
    .refund-warning{padding:10px 14px;border-radius:8px;background:#fef3c7;border:1px solid #fde68a;font-size:11px;color:#92400e;font-weight:500;margin-bottom:16px;display:flex;align-items:flex-start;gap:8px}
    html.dark .refund-warning{background:rgba(251,191,36,.08);border-color:rgba(251,191,36,.2);color:#fbbf24}
    .refund-milestone-select{width:100%;padding:10px 12px;border-radius:8px;border:1px solid #d1d5db;font-size:13px;color:#111827;background:#f9fafb;transition:all .15s;outline:none;appearance:none;background-image:url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='12' height='12' fill='%236b7280' viewBox='0 0 16 16'%3E%3Cpath d='M1.646 4.646a.5.5 0 0 1 .708 0L8 10.293l5.646-5.647a.5.5 0 0 1 .708.708l-6 6a.5.5 0 0 1-.708 0l-6-6a.5.5 0 0 1 0-.708z'/%3E%3C/svg%3E");background-repeat:no-repeat;background-position:right 12px center}
    .refund-milestone-select:focus{border-color:#dc2626;box-shadow:0 0 0 3px rgba(220,38,38,.1)}
    html.dark .refund-milestone-select{border-color:#475569;background-color:#0f172a;color:#e2e8f0}
    .btn-refund-cancel{padding:9px 18px;border-radius:8px;border:1px solid #d1d5db;background:#fff;font-size:12px;font-weight:600;color:#374151;cursor:pointer;transition:all .15s}
    .btn-refund-cancel:hover{background:#f9fafb;border-color:#9ca3af}
    html.dark .btn-refund-cancel{border-color:#475569;background:#1e293b;color:#cbd5e1}
    html.dark .btn-refund-cancel:hover{background:#334155}
    .btn-refund-confirm{padding:9px 18px;border-radius:8px;border:none;background:#d1d5db;font-size:12px;font-weight:600;color:#9ca3af;cursor:not-allowed;transition:all .15s}
    .btn-refund-confirm.enabled{background:#dc2626;color:#fff;cursor:pointer;box-shadow:0 2px 8px rgba(220,38,38,.25)}
    .btn-refund-confirm.enabled:hover{background:#b91c1c}
    .refund-disabled-msg{font-size:11px;color:#9ca3af;margin-top:6px}
    /* Freeze/Unfreeze modal */
    .freeze-info-grid{display:grid;grid-template-columns:1fr 1fr;gap:10px;margin-bottom:16px}
    .freeze-info-item{padding:10px 12px;border-radius:8px;background:#f9fafb;border:1px solid #e5e7eb}
    html.dark .freeze-info-item{background:#0f172a;border-color:#334155}
    .freeze-info-label{font-size:9px;font-weight:700;color:#9ca3af;text-transform:uppercase;letter-spacing:.04em;margin-bottom:2px}
    html.dark .freeze-info-label{color:#64748b}
    .freeze-info-value{font-size:13px;font-weight:600;color:#111827}
    html.dark .freeze-info-value{color:#f1f5f9}
    .freeze-type-group{display:flex;gap:8px}
    .freeze-type-btn{flex:1;padding:10px;border-radius:8px;border:1px solid #d1d5db;background:#f9fafb;cursor:pointer;text-align:center;font-size:12px;font-weight:600;color:#6b7280;transition:all .15s}
    .freeze-type-btn:hover{border-color:#9ca3af}
    .freeze-type-btn.selected{border-color:#f59e0b;background:#fffbeb;color:#92400e}
    html.dark .freeze-type-btn{border-color:#475569;background:#0f172a;color:#94a3b8}
    html.dark .freeze-type-btn.selected{border-color:#f59e0b;background:rgba(245,158,11,.12);color:#fbbf24}
    .btn-freeze-cancel{padding:9px 18px;border-radius:8px;border:1px solid #d1d5db;background:#fff;font-size:12px;font-weight:600;color:#374151;cursor:pointer;transition:all .15s}
    .btn-freeze-cancel:hover{background:#f9fafb;border-color:#9ca3af}
    html.dark .btn-freeze-cancel{border-color:#475569;background:#1e293b;color:#cbd5e1}
    html.dark .btn-freeze-cancel:hover{background:#334155}
    .btn-freeze-confirm{padding:9px 18px;border-radius:8px;border:none;background:#d1d5db;font-size:12px;font-weight:600;color:#9ca3af;cursor:not-allowed;transition:all .15s}
    .btn-freeze-confirm.enabled{background:#f59e0b;color:#fff;cursor:pointer;box-shadow:0 2px 8px rgba(245,158,11,.25)}
    .btn-freeze-confirm.enabled:hover{background:#d97706}
    .btn-unfreeze-confirm{padding:9px 18px;border-radius:8px;border:none;background:#d1d5db;font-size:12px;font-weight:600;color:#9ca3af;cursor:not-allowed;transition:all .15s}
    .btn-unfreeze-confirm.enabled{background:#059669;color:#fff;cursor:pointer;box-shadow:0 2px 8px rgba(5,150,105,.25)}
    .btn-unfreeze-confirm.enabled:hover{background:#047857}
    .frozen-banner{padding:10px 14px;border-radius:8px;background:#fef2f2;border:1px solid #fecaca;font-size:11px;color:#991b1b;font-weight:500;margin-bottom:16px;display:flex;align-items:flex-start;gap:8px}
    html.dark .frozen-banner{background:rgba(239,68,68,.08);border-color:rgba(239,68,68,.2);color:#fca5a5}
    /* Loading spinner */
    .drawer-loading{display:flex;align-items:center;justify-content:center;padding:60px 24px;gap:10px;color:#6b7280;font-size:13px}
    .drawer-loading::before{content:'';width:20px;height:20px;border:2px solid #d1d5db;border-top-color:#108A00;border-radius:50%;animation:spin .6s linear infinite}
    @keyframes spin{to{transform:rotate(360deg)}}
    </style>

    <script>
    (function() {
        var drawerRoot = document.getElementById('ajaxDrawerRoot');
        var currentUserId = null;
        var drawerLoaded = false;

        /* ── Close all dropdowns ── */
        function toggleDropdown(e, btn) {
            e.stopPropagation();
            e.preventDefault();
            var menu = btn.nextElementSibling;
            var isOpen = menu.classList.contains('open');
            closeAllDropdowns();
            if (!isOpen) {
                menu.classList.add('open');
                btn.setAttribute('aria-expanded', 'true');
            }
        }

        function closeAllDropdowns() {
            document.querySelectorAll('.actions-menu.open').forEach(function(m) { m.classList.remove('open'); });
            document.querySelectorAll('.actions-trigger[aria-expanded="true"]').forEach(function(b) { b.setAttribute('aria-expanded', 'false'); });
        }

        document.addEventListener('click', closeAllDropdowns);
        document.addEventListener('keydown', function(e) { if (e.key === 'Escape') closeAllDropdowns(); });
        window.toggleDropdown = toggleDropdown;

        /* ── Load drawer via AJAX ── */
        window.openWalletDrawer = function(userId, action) {
            currentUserId = userId;
            closeAllDropdowns();

            // Show loading state
            drawerRoot.innerHTML = '<div class="drawer-overlay open" onclick="closeAjaxDrawer()"></div>' +
                '<div class="drawer-panel open"><div class="drawer-body"><div class="drawer-loading">Loading...</div></div></div>';
            document.body.style.overflow = 'hidden';

            var url = 'wallet_history.php?user_id=' + userId + '&ajax=1';
            if (action) url += '&action=' + action;

            fetch(url)
                .then(function(r) { return r.text(); })
                .then(function(html) {
                    // Extract styles, drawer HTML, modals, and script from the response
                    var parser = new DOMParser();
                    var doc = parser.parseFromString(html, 'text/html');

                    // Collect all styles
                    var styles = '';
                    doc.querySelectorAll('style').forEach(function(s) { styles += s.outerHTML; });

                    // Collect drawer overlay + panel
                    var overlay = doc.querySelector('.drawer-overlay');
                    var panel = doc.querySelector('.drawer-panel');

                    // Collect modals
                    var modals = '';
                    doc.querySelectorAll('.adj-modal-overlay').forEach(function(m) { modals += m.outerHTML; });

                    // Collect notifications
                    var notifs = '';
                    doc.querySelectorAll('.adj-notif').forEach(function(n) { notifs += n.outerHTML; });

                    // Collect script content
                    var scripts = '';
                    doc.querySelectorAll('script').forEach(function(s) {
                        if (s.textContent.indexOf('closeDrawer') !== -1 || s.textContent.indexOf('openAdjustModal') !== -1) {
                            scripts += s.outerHTML;
                        }
                    });

                    // Inject into page
                    var containerHTML = '';
                    if (styles) containerHTML += styles;
                    if (overlay) containerHTML += overlay.outerHTML;
                    if (panel) containerHTML += panel.outerHTML;
                    if (modals) containerHTML += modals;
                    if (notifs) containerHTML += notifs;

                    drawerRoot.innerHTML = containerHTML;
                    if (scripts) {
                        var scriptEl = document.createElement('script');
                        scriptEl.textContent = scripts.replace(/<\/?script>/g, '');
                        drawerRoot.appendChild(scriptEl);
                    }

                    drawerLoaded = true;

                    // Open drawer with animation
                    requestAnimationFrame(function() {
                        var o = drawerRoot.querySelector('.drawer-overlay');
                        var p = drawerRoot.querySelector('.drawer-panel');
                        if (o) o.classList.add('open');
                        if (p) p.classList.add('open');
                    });

                    // Auto-open action modals
                    if (action === 'adjust') {
                        setTimeout(function() { if (window.openAdjustModal) openAdjustModal(); }, 100);
                    } else if (action === 'refund') {
                        setTimeout(function() { if (window.openRefundModal) openRefundModal(); }, 100);
                    } else if (action === 'freeze') {
                        setTimeout(function() { if (window.openFreezeModal) openFreezeModal(); }, 100);
                    }
                })
                .catch(function(err) {
                    drawerRoot.innerHTML = '<div class="drawer-overlay open" onclick="closeAjaxDrawer()"></div>' +
                        '<div class="drawer-panel open"><div class="drawer-body"><div class="text-center py-16"><p class="text-gray-500 text-sm">Failed to load. Please try again.</p></div></div></div>';
                    requestAnimationFrame(function() {
                        var o = drawerRoot.querySelector('.drawer-overlay');
                        var p = drawerRoot.querySelector('.drawer-panel');
                        if (o) o.classList.add('open');
                        if (p) p.classList.add('open');
                    });
                });
        };

        /* ── Close drawer ── */
        window.closeAjaxDrawer = function() {
            var o = drawerRoot.querySelector('.drawer-overlay');
            var p = drawerRoot.querySelector('.drawer-panel');
            if (o) o.classList.remove('open');
            if (p) p.classList.remove('open');
            document.body.style.overflow = '';
            setTimeout(function() { drawerRoot.innerHTML = ''; }, 300);
        };

        /* ── Intercept dropdown link clicks ── */
        document.addEventListener('click', function(e) {
            var link = e.target.closest('.actions-menu a[href*="wallet_history.php"]');
            if (!link) return;
            e.preventDefault();
            var href = link.getAttribute('href');
            var params = new URLSearchParams(href.split('?')[1] || '');
            var uid = params.get('user_id');
            var action = params.get('action');
            if (uid) openWalletDrawer(parseInt(uid), action || null);
        });

        /* ── AJAX form submission ── */
        document.addEventListener('submit', function(e) {
            var form = e.target;
            if (!form.action || form.action.indexOf('wallet_history.php') === -1) return;
            if (!form.querySelector('input[name="adjust_balance"],input[name="process_refund"],input[name="freeze_wallet"],input[name="unfreeze_wallet"]')) return;

            e.preventDefault();
            var formData = new FormData(form);
            var actionUrl = form.action + '&ajax=1';

            fetch(actionUrl, { method: 'POST', body: formData })
                .then(function(r) {
                    if (r.redirected) {
                        // Follow redirect, fetch with ajax=1 to get drawer HTML
                        return fetch(r.url + (r.url.indexOf('?') === -1 ? '?' : '&') + 'ajax=1')
                            .then(function(r2) { return r2.text(); });
                    }
                    return r.text();
                })
                .then(function(html) {
                    // Parse response for notifications and drawer content
                    var parser = new DOMParser();
                    var doc = parser.parseFromString(html, 'text/html');

                    // Check for flash messages
                    var notifEl = doc.querySelector('.adj-notif');
                    var notifText = notifEl ? notifEl.querySelector('span') : null;
                    var notifClass = notifEl ? (notifEl.classList.contains('success') ? 'success' : 'error') : null;

                    if (notifText) {
                        showNotification(notifText.textContent.trim(), notifClass);
                    }

                    // Reload drawer content
                    if (currentUserId) {
                        openWalletDrawer(currentUserId, null);
                    }
                })
                .catch(function() {
                    showNotification('An error occurred. Please try again.', 'error');
                });
        });

        /* ── Show notification ── */
        function showNotification(text, type) {
            var notif = document.getElementById('ajaxNotif');
            var notifText = document.getElementById('ajaxNotifText');
            var icon = notif.querySelector('i');
            if (!notif || !notifText) return;

            notifText.textContent = text;
            notif.className = 'adj-notif ' + (type || 'success');
            icon.className = type === 'error' ? 'lucide lucide-circle-alert' : 'lucide lucide-circle-check';
            notif.style.display = 'flex';
            requestAnimationFrame(function() { notif.classList.add('show'); });
            setTimeout(function() {
                notif.classList.remove('show');
                setTimeout(function() { notif.style.display = 'none'; }, 400);
            }, 4000);
        }

        window.showNotification = showNotification;
    })();
    </script>

<?php require_once __DIR__ . '/../components/layout_end.php'; ?>
