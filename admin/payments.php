<?php

/**
 * Admin Payment Monitoring Dashboard
 * Statistics, filtered payment table, charts, export, and action modals.
 */
require_once __DIR__ . '/../auth/auth.php';
require_role('admin');
require_once __DIR__ . '/../config/db.php';

$currentPage = 'payments';

// ── Filters ──────────────────────────────────────────────────────────
$dateFrom = trim($_GET['date_from'] ?? '');
$dateTo = trim($_GET['date_to'] ?? '');
$statusF = $_GET['status'] ?? '';
$clientQ = trim($_GET['client'] ?? '');
$freelancerQ = trim($_GET['freelancer'] ?? '');
$amountMin = $_GET['amount_min'] ?? '';
$amountMax = $_GET['amount_max'] ?? '';
$page = max(1, sanitize_int($_GET['page'] ?? 1));
$perPage = 12;

$allowedStatuses = ['pending', 'processing', 'completed', 'refunded'];
if ($statusF && !in_array($statusF, $allowedStatuses)) {
    $statusF = '';
}
if ($amountMin !== '' && !is_numeric($amountMin)) {
    $amountMin = '';
}
if ($amountMax !== '' && !is_numeric($amountMax)) {
    $amountMax = '';
}

// ── Statistics ────────────────────────────────────────────────────────
$stats = [];

$r = $conn->query("SELECT COALESCE(SUM(platform_fee), 0) AS total FROM payments WHERE status = 'completed'");
$stats['total_revenue'] = (float) $r->fetch_assoc()['total'];

$r = $conn->query("SELECT COUNT(*) AS cnt FROM payments WHERE status = 'completed'");
$stats['completed_count'] = (int) $r->fetch_assoc()['cnt'];

$r = $conn->query("SELECT COUNT(*) AS cnt FROM payments WHERE status = 'pending'");
$stats['pending_count'] = (int) $r->fetch_assoc()['cnt'];

$r = $conn->query("SELECT COUNT(*) AS cnt FROM payments WHERE status = 'refunded'");
$stats['refunded_count'] = (int) $r->fetch_assoc()['cnt'];

$r = $conn->query("SELECT COALESCE(SUM(platform_fee), 0) AS total FROM payments WHERE status = 'completed'");
$stats['fees_collected'] = (float) $r->fetch_assoc()['total'];

$r = $conn->query("SELECT COALESCE(SUM(freelancer_net), 0) AS total FROM payments WHERE status = 'completed'");
$stats['freelancer_earnings'] = (float) $r->fetch_assoc()['total'];

$r = $conn->query('SELECT COUNT(*) AS cnt FROM payments');
$stats['total_payments'] = (int) $r->fetch_assoc()['cnt'];

$r = $conn->query("SELECT COALESCE(SUM(total_amount), 0) AS total FROM payments WHERE status = 'completed'");
$stats['gross_volume'] = (float) $r->fetch_assoc()['total'];

// ── Build WHERE ───────────────────────────────────────────────────────
$where = [];
$params = [];
$types = '';

if ($dateFrom !== '') {
    $where[] = 'p.created_at >= ?';
    $params[] = $dateFrom . ' 00:00:00';
    $types .= 's';
}
if ($dateTo !== '') {
    $where[] = 'p.created_at <= ?';
    $params[] = $dateTo . ' 23:59:59';
    $types .= 's';
}
if ($statusF !== '') {
    $where[] = 'p.status = ?';
    $params[] = $statusF;
    $types .= 's';
}
if ($clientQ !== '') {
    $where[] = '(uc.name LIKE ? OR uc.email LIKE ?)';
    $cSearch = "%{$clientQ}%";
    $params[] = $cSearch;
    $params[] = $cSearch;
    $types .= 'ss';
}
if ($freelancerQ !== '') {
    $where[] = '(uf.name LIKE ? OR uf.email LIKE ?)';
    $fSearch = "%{$freelancerQ}%";
    $params[] = $fSearch;
    $params[] = $fSearch;
    $types .= 'ss';
}
if ($amountMin !== '') {
    $where[] = 'p.total_amount >= ?';
    $params[] = (float) $amountMin;
    $types .= 'd';
}
if ($amountMax !== '') {
    $where[] = 'p.total_amount <= ?';
    $params[] = (float) $amountMax;
    $types .= 'd';
}

$whereSql = $where ? 'WHERE ' . implode(' AND ', $where) : '';

// ── Count ─────────────────────────────────────────────────────────────
$countSql = "SELECT COUNT(*) AS cnt
             FROM payments p
             JOIN milestones m ON p.milestone_id = m.id
             JOIN users uc ON p.payer_id = uc.id
             JOIN users uf ON p.payee_id = uf.id
             {$whereSql}";
$countStmt = $conn->prepare($countSql);
if ($params) {
    $countStmt->bind_param($types, ...$params);
}
$countStmt->execute();
$totalItems = (int) $countStmt->get_result()->fetch_assoc()['cnt'];
$countStmt->close();

$pagination = paginate($totalItems, $perPage, $page);

// ── Fetch Payments ────────────────────────────────────────────────────
$querySql = "SELECT p.id, p.total_amount, p.platform_fee, p.freelancer_net, p.status, p.created_at,
                    m.title AS milestone_title,
                    uc.name AS client_name, uc.email AS client_email, uc.profile_image AS client_image,
                    uf.name AS freelancer_name, uf.email AS freelancer_email, uf.profile_image AS freelancer_image
             FROM payments p
             JOIN milestones m ON p.milestone_id = m.id
             JOIN users uc ON p.payer_id = uc.id
             JOIN users uf ON p.payee_id = uf.id
             {$whereSql}
             ORDER BY p.created_at DESC
             LIMIT ? OFFSET ?";
$queryStmt = $conn->prepare($querySql);
$bindTypes = $types . 'ii';
$bindParams = array_merge($params, [$perPage, $pagination['offset']]);
$queryStmt->bind_param($bindTypes, ...$bindParams);
$queryStmt->execute();
$paymentsResult = $queryStmt->get_result();
$queryStmt->close();

// ── Monthly Revenue (last 12 months) for chart ───────────────────────
$chartSql = "SELECT DATE_FORMAT(p.created_at, '%Y-%m') AS month_key,
                    DATE_FORMAT(p.created_at, '%b %Y') AS month_label,
                    COALESCE(SUM(p.platform_fee), 0) AS revenue,
                    COUNT(*) AS cnt
             FROM payments p
             WHERE p.status = 'completed'
               AND p.created_at >= DATE_SUB(CURDATE(), INTERVAL 12 MONTH)
             GROUP BY month_key, month_label
             ORDER BY month_key ASC";
$chartResult = $conn->query($chartSql);
$chartLabels = [];
$chartRevenue = [];
$chartCounts = [];
while ($row = $chartResult->fetch_assoc()) {
    $chartLabels[] = $row['month_label'];
    $chartRevenue[] = (float) $row['revenue'];
    $chartCounts[] = (int) $row['cnt'];
}

// ── Status Distribution for pie chart ────────────────────────────────
$pieSql = "SELECT status, COUNT(*) AS cnt FROM payments GROUP BY status ORDER BY FIELD(status, 'completed','pending','processing','refunded')";
$pieResult = $conn->query($pieSql);
$pieLabels = [];
$pieData = [];
while ($row = $pieResult->fetch_assoc()) {
    $pieLabels[] = ucfirst($row['status']);
    $pieData[] = (int) $row['cnt'];
}

// ── Badge map ─────────────────────────────────────────────────────────
$statusColors = [
    'completed' => 'bg-emerald-50 dark:bg-emerald-900/30 text-emerald-600 dark:text-emerald-400 border-emerald-200 dark:border-emerald-800',
    'pending' => 'bg-amber-50 dark:bg-amber-900/30 text-amber-600 dark:text-amber-400 border-amber-200 dark:border-amber-800',
    'processing' => 'bg-blue-50 dark:bg-blue-900/30 text-blue-600 dark:text-blue-400 border-blue-200 dark:border-blue-800',
    'refunded' => 'bg-red-50 dark:bg-red-900/30 text-red-600 dark:text-red-400 border-red-200 dark:border-red-800',
];

// ── Base URL for pagination ──────────────────────────────────────────
$baseUrl = 'payments.php?';
if ($dateFrom !== '')
    $baseUrl .= 'date_from=' . urlencode($dateFrom) . '&';
if ($dateTo !== '')
    $baseUrl .= 'date_to=' . urlencode($dateTo) . '&';
if ($statusF !== '')
    $baseUrl .= 'status=' . urlencode($statusF) . '&';
if ($clientQ !== '')
    $baseUrl .= 'client=' . urlencode($clientQ) . '&';
if ($freelancerQ !== '')
    $baseUrl .= 'freelancer=' . urlencode($freelancerQ) . '&';
if ($amountMin !== '')
    $baseUrl .= 'amount_min=' . urlencode($amountMin) . '&';
if ($amountMax !== '')
    $baseUrl .= 'amount_max=' . urlencode($amountMax) . '&';
$baseUrl = rtrim($baseUrl, '?&');
if (strpos($baseUrl, '&') === false) {
    $baseUrl = rtrim($baseUrl, '?');
}

// Collect current filter values for the export hidden form
$filterParams = [
    'date_from' => $dateFrom,
    'date_to' => $dateTo,
    'status' => $statusF,
    'client' => $clientQ,
    'freelancer' => $freelancerQ,
    'amount_min' => $amountMin,
    'amount_max' => $amountMax,
];

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
$pageTitle = 'Payment Monitoring';
$pageSubtitle = $totalItems . ' payment' . ($totalItems !== 1 ? 's' : '') . ' found';
$activePage = 'payments';
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

    /* Payment Detail Drawer */
    .pd-overlay{position:fixed;inset:0;background:rgba(0,0,0,.4);backdrop-filter:blur(4px);z-index:40;opacity:0;visibility:hidden;transition:all .25s}
    .pd-overlay.open{opacity:1;visibility:visible}
    .pd-drawer{position:fixed;top:0;right:0;bottom:0;width:100%;max-width:450px;background:#fff;z-index:50;transform:translateX(100%);transition:transform .3s cubic-bezier(.4,0,.2,1);display:flex;flex-direction:column;overflow:hidden}
    html.dark .pd-drawer{background:#0f172a}
    .pd-overlay.open .pd-drawer{transform:translateX(0)}
    .pd-drawer-header{padding:24px 24px 0;flex-shrink:0}
    .pd-drawer-body{flex:1;overflow-y:auto;padding:20px 24px}
    .pd-drawer-footer{flex-shrink:0;border-top:1px solid #E4EBE4;padding:16px 24px;background:#fff}
    html.dark .pd-drawer-footer{border-color:#334155;background:#0f172a}
    .pd-receipt{background:#F9FAFB;border-radius:10px;padding:20px}
    html.dark .pd-receipt{background:#1e293b}
    .pd-party-card{border:1px solid #E4EBE4;border-radius:10px;padding:16px}
    html.dark .pd-party-card{border-color:#334155}

    /* Pagination overrides to match unified card */
    nav.flex.items-center.justify-center.gap-6 {
        justify-content: space-between !important;
        background: #fff;
        border: 1px solid #E4EBE4;
        border-top: none;
        border-radius: 0 0 8px 8px;
        padding: 12px 20px;
        margin-top: 0;
    }
    html.dark nav.flex.items-center.justify-center.gap-6 {
        background: #1e293b;
        border-color: #334155;
    }
    nav.flex.items-center.justify-center.gap-6 .flex.items-center.gap-2 {
        gap: 6px;
    }
    nav.flex.items-center.justify-center.gap-6 a.w-9.h-9,
    nav.flex.items-center.justify-center.gap-6 span.w-9.h-9 {
        width: 32px !important;
        height: 32px !important;
        border-radius: 6px !important;
        border: 1px solid #E4EBE4 !important;
        background: #fff !important;
        color: #6b7280 !important;
        font-size: 12px !important;
        font-weight: 500 !important;
    }
    html.dark nav.flex.items-center.justify-center.gap-6 a.w-9.h-9,
    html.dark nav.flex.items-center.justify-center.gap-6 span.w-9.h-9 {
        border-color: #475569 !important;
        background: #1e293b !important;
        color: #94a3b8 !important;
    }
    nav.flex.items-center.justify-center.gap-6 a.w-9.h-9:hover {
        background: #f9fafb !important;
        color: #374151 !important;
    }
    html.dark nav.flex.items-center.justify-center.gap-6 a.w-9.h-9:hover {
        background: #334155 !important;
        color: #e2e8f0 !important;
    }
    nav.flex.items-center.justify-center.gap-6 a.w-9.h-9.bg-gray-900,
    nav.flex.items-center.justify-center.gap-6 a.w-9.h-9[class*="bg-gray-900"] {
        background: #108A00 !important;
        border-color: #108A00 !important;
        color: #fff !important;
        box-shadow: none !important;
    }
    html.dark nav.flex.items-center.justify-center.gap-6 a.w-9.h-9.bg-gray-900,
    html.dark nav.flex.items-center.justify-center.gap-6 a.w-9.h-9[class*="bg-gray-900"] {
        background: #108A00 !important;
        border-color: #108A00 !important;
        color: #fff !important;
    }
    nav.flex.items-center.justify-center.gap-6 span.w-9.h-9.cursor-not-allowed {
        opacity: 0.4;
    }
    </style>

    <?php display_flash('success'); ?>
    <?php display_flash('error'); ?>
    <?php display_flash('info'); ?>

    <!-- ═══ KPI CARDS ════════════════════════════════════════════════════ -->
    <div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-6 gap-3">
        <div class="kpi-mini fade-in rounded-lg p-4 border border-gray-200 dark:border-slate-700 bg-white dark:bg-slate-800 shadow-sm" style="animation-delay:0s">
            <div class="flex items-center gap-2 mb-2">
                <div class="flex items-center justify-center w-8 h-8 rounded-lg bg-emerald-100 dark:bg-emerald-900/30">
                    <svg class="w-4 h-4 text-emerald-600 dark:text-emerald-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                </div>
                <p class="text-xs font-semibold text-gray-400 dark:text-slate-500 uppercase tracking-wider">Revenue</p>
            </div>
            <p class="text-xl font-extrabold text-gray-900 dark:text-white"><?= format_currency($stats['total_revenue']) ?></p>
        </div>
        <div class="kpi-mini fade-in rounded-lg p-4 border border-gray-200 dark:border-slate-700 bg-white dark:bg-slate-800 shadow-sm" style="animation-delay:.03s">
            <div class="flex items-center gap-2 mb-2">
                <div class="flex items-center justify-center w-8 h-8 rounded-lg bg-blue-100 dark:bg-blue-900/30">
                    <svg class="w-4 h-4 text-blue-600 dark:text-blue-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                </div>
                <p class="text-xs font-semibold text-gray-400 dark:text-slate-500 uppercase tracking-wider">Completed</p>
            </div>
            <p class="text-xl font-extrabold text-blue-600 dark:text-blue-400"><?= number_format($stats['completed_count']) ?></p>
        </div>
        <div class="kpi-mini fade-in rounded-lg p-4 border border-gray-200 dark:border-slate-700 bg-white dark:bg-slate-800 shadow-sm" style="animation-delay:.06s">
            <div class="flex items-center gap-2 mb-2">
                <div class="flex items-center justify-center w-8 h-8 rounded-lg bg-amber-100 dark:bg-amber-900/30">
                    <svg class="w-4 h-4 text-amber-600 dark:text-amber-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                </div>
                <p class="text-xs font-semibold text-gray-400 dark:text-slate-500 uppercase tracking-wider">Pending</p>
            </div>
            <p class="text-xl font-extrabold text-amber-600 dark:text-amber-400"><?= number_format($stats['pending_count']) ?></p>
        </div>
        <div class="kpi-mini fade-in rounded-lg p-4 border border-gray-200 dark:border-slate-700 bg-white dark:bg-slate-800 shadow-sm" style="animation-delay:.09s">
            <div class="flex items-center gap-2 mb-2">
                <div class="flex items-center justify-center w-8 h-8 rounded-lg bg-red-100 dark:bg-red-900/30">
                    <svg class="w-4 h-4 text-red-600 dark:text-red-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 10h10a8 8 0 018 8v2M3 10l6 6m-6-6l6-6"/></svg>
                </div>
                <p class="text-xs font-semibold text-gray-400 dark:text-slate-500 uppercase tracking-wider">Refunds</p>
            </div>
            <p class="text-xl font-extrabold text-red-600 dark:text-red-400"><?= number_format($stats['refunded_count']) ?></p>
        </div>
        <div class="kpi-mini fade-in rounded-lg p-4 border border-gray-200 dark:border-slate-700 bg-white dark:bg-slate-800 shadow-sm" style="animation-delay:.12s">
            <div class="flex items-center gap-2 mb-2">
                <div class="flex items-center justify-center w-8 h-8 rounded-lg bg-violet-100 dark:bg-violet-900/30">
                    <svg class="w-4 h-4 text-violet-600 dark:text-violet-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 9V7a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2m2 4h10a2 2 0 002-2v-6a2 2 0 00-2-2H9a2 2 0 00-2 2v6a2 2 0 002 2zm7-5a2 2 0 11-4 0 2 2 0 014 0z"/></svg>
                </div>
                <p class="text-xs font-semibold text-gray-400 dark:text-slate-500 uppercase tracking-wider">Fees</p>
            </div>
            <p class="text-xl font-extrabold text-violet-600 dark:text-violet-400"><?= format_currency($stats['fees_collected']) ?></p>
        </div>
        <div class="kpi-mini fade-in rounded-lg p-4 border border-gray-200 dark:border-slate-700 bg-white dark:bg-slate-800 shadow-sm" style="animation-delay:.15s">
            <div class="flex items-center gap-2 mb-2">
                <div class="flex items-center justify-center w-8 h-8 rounded-lg bg-cyan-100 dark:bg-cyan-900/30">
                    <svg class="w-4 h-4 text-cyan-600 dark:text-cyan-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0z"/></svg>
                </div>
                <p class="text-xs font-semibold text-gray-400 dark:text-slate-500 uppercase tracking-wider">Freelancers</p>
            </div>
            <p class="text-xl font-extrabold text-cyan-600 dark:text-cyan-400"><?= format_currency($stats['freelancer_earnings']) ?></p>
        </div>
    </div>

    <!-- ═══ SEARCH + PAYMENTS TABLE ══════════════════════════════════════ -->
    <div class="bg-white dark:bg-slate-800 border border-gray-200 dark:border-slate-700 rounded-lg overflow-hidden fade-in" style="animation-delay:.18s">
        <!-- Search Toolbar -->
        <div class="p-5 border-b border-gray-100 dark:border-slate-700">
            <form method="GET" action="payments.php">
                <div class="flex flex-col lg:flex-row gap-3">
                    <div class="flex-1 relative">
                        <i data-lucide="search" class="absolute left-3.5 top-1/2 -translate-y-1/2 text-gray-400 dark:text-slate-500 text-sm"></i>
                        <input type="text" name="client" value="<?= sanitize_string($clientQ) ?>" placeholder="Search client name or email..."
                               class="w-full pl-10 pr-4 py-2.5 rounded-lg border border-gray-200 dark:border-slate-600 text-sm bg-gray-50 dark:bg-slate-700 text-gray-900 dark:text-white placeholder-gray-400 dark:placeholder-slate-500 focus:outline-none focus:ring-2 focus:ring-blue-500/30 focus:border-blue-400 transition-all">
                    </div>
                    <div class="flex-1 relative">
                        <i data-lucide="monitor" class="absolute left-3.5 top-1/2 -translate-y-1/2 text-gray-400 dark:text-slate-500 text-sm"></i>
                        <input type="text" name="freelancer" value="<?= sanitize_string($freelancerQ) ?>" placeholder="Search freelancer name or email..."
                               class="w-full pl-10 pr-4 py-2.5 rounded-lg border border-gray-200 dark:border-slate-600 text-sm bg-gray-50 dark:bg-slate-700 text-gray-900 dark:text-white placeholder-gray-400 dark:placeholder-slate-500 focus:outline-none focus:ring-2 focus:ring-blue-500/30 focus:border-blue-400 transition-all">
                    </div>
                    <div class="flex flex-wrap gap-2">
                        <select name="status" class="px-4 py-2.5 rounded-lg border border-gray-200 dark:border-slate-600 text-sm text-gray-600 dark:text-slate-300 focus:outline-none focus:ring-2 focus:ring-blue-500/30 focus:border-blue-400 bg-gray-50 dark:bg-slate-700">
                            <option value="">All Statuses</option>
                            <option value="pending" <?= $statusF === 'pending' ? 'selected' : '' ?>>Pending</option>
                            <option value="processing" <?= $statusF === 'processing' ? 'selected' : '' ?>>Processing</option>
                            <option value="completed" <?= $statusF === 'completed' ? 'selected' : '' ?>>Completed</option>
                            <option value="refunded" <?= $statusF === 'refunded' ? 'selected' : '' ?>>Refunded</option>
                        </select>
                        <input type="date" name="date_from" value="<?= sanitize_string($dateFrom) ?>" placeholder="From"
                               class="px-3 py-2.5 rounded-lg border border-gray-200 dark:border-slate-600 text-sm bg-gray-50 dark:bg-slate-700 text-gray-900 dark:text-white focus:outline-none focus:ring-2 focus:ring-blue-500/30 focus:border-blue-400 transition-all">
                        <input type="date" name="date_to" value="<?= sanitize_string($dateTo) ?>" placeholder="To"
                               class="px-3 py-2.5 rounded-lg border border-gray-200 dark:border-slate-600 text-sm bg-gray-50 dark:bg-slate-700 text-gray-900 dark:text-white focus:outline-none focus:ring-2 focus:ring-blue-500/30 focus:border-blue-400 transition-all">
                        <input type="number" name="amount_min" value="<?= sanitize_string($amountMin) ?>" placeholder="Min $" step="0.01" min="0"
                               class="w-24 px-3 py-2.5 rounded-lg border border-gray-200 dark:border-slate-600 text-sm bg-gray-50 dark:bg-slate-700 text-gray-900 dark:text-white placeholder-gray-400 dark:placeholder-slate-500 focus:outline-none focus:ring-2 focus:ring-blue-500/30 focus:border-blue-400 transition-all">
                        <input type="number" name="amount_max" value="<?= sanitize_string($amountMax) ?>" placeholder="Max $" step="0.01" min="0"
                               class="w-24 px-3 py-2.5 rounded-lg border border-gray-200 dark:border-slate-600 text-sm bg-gray-50 dark:bg-slate-700 text-gray-900 dark:text-white placeholder-gray-400 dark:placeholder-slate-500 focus:outline-none focus:ring-2 focus:ring-blue-500/30 focus:border-blue-400 transition-all">
                        <button type="submit" class="px-5 py-2.5 rounded-lg bg-blue-600 hover:bg-blue-700 text-white text-sm font-semibold transition-colors inline-flex items-center gap-1.5 shadow-lg shadow-blue-500/20">
                            <i data-lucide="filter" class="text-xs"></i> Filter
                        </button>
                        <?php if ($dateFrom || $dateTo || $statusF || $clientQ || $freelancerQ || $amountMin || $amountMax): ?>
                            <a href="payments.php" class="px-4 py-2.5 rounded-lg border border-gray-200 dark:border-slate-600 text-sm font-medium text-gray-500 dark:text-slate-400 hover:bg-gray-50 dark:hover:bg-slate-700 transition-colors text-center">
                                <i data-lucide="x" class="mr-1"></i> Clear
                            </a>
                        <?php endif; ?>
                    </div>
                </div>
                <div class="flex items-center gap-2 mt-3 pt-3 border-t border-gray-100 dark:border-slate-700/50">
                    <button type="button" onclick="exportCSV()" class="inline-flex items-center gap-2 px-4 py-2 rounded-lg border border-gray-200 dark:border-slate-600 text-sm font-medium text-gray-600 dark:text-slate-300 hover:bg-gray-50 dark:hover:bg-slate-700 transition-colors">
                        <i data-lucide="file-spreadsheet" class="text-emerald-500"></i> Export Excel
                    </button>
                    <button type="button" onclick="exportPDF()" class="inline-flex items-center gap-2 px-4 py-2 rounded-lg border border-gray-200 dark:border-slate-600 text-sm font-medium text-gray-600 dark:text-slate-300 hover:bg-gray-50 dark:hover:bg-slate-700 transition-colors">
                        <i data-lucide="file-text" class="text-red-500"></i> Export PDF
                    </button>
                </div>
            </form>
        </div>

        <!-- Table -->
        <?php if ($paymentsResult->num_rows > 0): ?>
        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead>
                    <tr class="bg-gray-50 dark:bg-slate-700/50">
                        <!-- <th class="text-left px-5 py-3 text-xs font-semibold text-gray-500 dark:text-slate-400 uppercase tracking-wider">Payment</th> -->
                        <th class="text-left px-5 py-3 text-xs font-semibold text-gray-500 dark:text-slate-400 uppercase tracking-wider">Milestone</th>
                        <th class="text-left px-5 py-3 text-xs font-semibold text-gray-500 dark:text-slate-400 uppercase tracking-wider">Client</th>
                        <th class="text-left px-5 py-3 text-xs font-semibold text-gray-500 dark:text-slate-400 uppercase tracking-wider">Freelancer</th>
                        <th class="text-right px-5 py-3 text-xs font-semibold text-gray-500 dark:text-slate-400 uppercase tracking-wider">Total</th>
                        <th class="text-right px-5 py-3 text-xs font-semibold text-gray-500 dark:text-slate-400 uppercase tracking-wider">Fee</th>
                        <th class="text-right px-5 py-3 text-xs font-semibold text-gray-500 dark:text-slate-400 uppercase tracking-wider">Net</th>
                        <th class="text-center px-5 py-3 text-xs font-semibold text-gray-500 dark:text-slate-400 uppercase tracking-wider">Status</th>
                        <th class="text-right px-5 py-3 text-xs font-semibold text-gray-500 dark:text-slate-400 uppercase tracking-wider">Date</th>
                        <th class="text-right px-5 py-3 text-xs font-semibold text-gray-500 dark:text-slate-400 uppercase tracking-wider">Actions</th>
                    </tr>
                </thead>
                <tbody>
                <?php while ($p = $paymentsResult->fetch_assoc()): ?>
                    <tr class="border-b border-gray-50 dark:border-slate-700/50 last:border-0 hover:bg-gray-50/50 dark:hover:bg-slate-700/30 transition-colors" data-payment-id="<?= (int) $p['id'] ?>">
                        <!-- <td class="px-5 py-4">
                            <span class="font-mono text-xs font-bold text-gray-700 dark:text-gray-300">#<?= (int) $p['id'] ?></span>
                        </td> -->
                        <td class="px-5 py-4">
                            <span class="font-medium text-gray-900 dark:text-white line-clamp-1 text-sm"><?= sanitize_string($p['milestone_title']) ?></span>
                        </td>
                        <td class="px-5 py-4">
                            <div class="flex items-center gap-3">
                                <?php
                                $_pci = $p['client_image'] ?? '';
                                $_pcb = strtolower(basename($_pci));
                                $_pcv = $_pci !== '' && $_pci !== null && $_pcb !== 'default.png' && $_pcb !== 'profile.png';
                                if ($_pcv):
                                    ?>
                                <img src="<?= sanitize_string(get_profile_image($_pci)) ?>" class="w-8 h-8 rounded-full object-cover border-2 border-gray-100 dark:border-slate-600 flex-shrink-0">
                                <?php
                                else:
                                    $_pcin = '';
                                    foreach (explode(' ', trim($p['client_name'] ?? '')) as $_w) {
                                        if ($_w !== '')
                                            $_pcin .= strtoupper($_w[0]);
                                    }
                                    $_pcin = substr($_pcin, 0, 2);
                                ?>
                                <div class="w-8 h-8 rounded-full bg-blue-100 text-blue-600 flex items-center justify-center font-semibold text-xs flex-shrink-0 border-2 border-gray-100 dark:border-slate-600"><?= $_pcin ?></div>
                                <?php endif; ?>
                                <div class="min-w-0">
                                    <p class="font-semibold text-gray-900 dark:text-white truncate text-sm"><?= sanitize_string($p['client_name']) ?></p>
                                    <p class="text-xs text-gray-400 dark:text-slate-500 truncate"><?= sanitize_string($p['client_email']) ?></p>
                                </div>
                            </div>
                        </td>
                        <td class="px-5 py-4">
                            <div class="flex items-center gap-3">
                                <?php
                                $_pfi = $p['freelancer_image'] ?? '';
                                $_pfb = strtolower(basename($_pfi));
                                $_pfv = $_pfi !== '' && $_pfi !== null && $_pfb !== 'default.png' && $_pfb !== 'profile.png';
                                if ($_pfv):
                                    ?>
                                <img src="<?= sanitize_string(get_profile_image($_pfi)) ?>" class="w-8 h-8 rounded-full object-cover border-2 border-gray-100 dark:border-slate-600 flex-shrink-0">
                                <?php
                                else:
                                    $_pfin = '';
                                    foreach (explode(' ', trim($p['freelancer_name'] ?? '')) as $_w) {
                                        if ($_w !== '')
                                            $_pfin .= strtoupper($_w[0]);
                                    }
                                    $_pfin = substr($_pfin, 0, 2);
                                ?>
                                <div class="w-8 h-8 rounded-full bg-blue-100 text-blue-600 flex items-center justify-center font-semibold text-xs flex-shrink-0 border-2 border-gray-100 dark:border-slate-600"><?= $_pfin ?></div>
                                <?php endif; ?>
                                <div class="min-w-0">
                                    <p class="font-semibold text-gray-900 dark:text-white truncate text-sm"><?= sanitize_string($p['freelancer_name']) ?></p>
                                    <p class="text-xs text-gray-400 dark:text-slate-500 truncate"><?= sanitize_string($p['freelancer_email']) ?></p>
                                </div>
                            </div>
                        </td>
                        <td class="px-5 py-4 text-right">
                            <span class="text-sm font-bold text-gray-900 dark:text-white"><?= format_currency((float) $p['total_amount']) ?></span>
                        </td>
                        <td class="px-5 py-4 text-right">
                            <span class="text-sm font-semibold text-amber-600 dark:text-amber-400"><?= format_currency((float) $p['platform_fee']) ?></span>
                        </td>
                        <td class="px-5 py-4 text-right">
                            <span class="text-sm font-semibold text-emerald-600 dark:text-emerald-400"><?= format_currency((float) $p['freelancer_net']) ?></span>
                        </td>
                        <td class="px-5 py-4 text-center">
                            <?php
                            $statusDarkMap = [
                                'completed' => 'bg-emerald-100 text-emerald-700 dark:bg-emerald-900/30 dark:text-emerald-400',
                                'pending' => 'bg-amber-100 text-amber-700 dark:bg-amber-900/30 dark:text-amber-400',
                                'processing' => 'bg-blue-100 text-blue-700 dark:bg-blue-900/30 dark:text-blue-400',
                                'refunded' => 'bg-red-100 text-red-700 dark:bg-red-900/30 dark:text-red-400',
                            ];
                            ?>
                            <span class="inline-block px-2.5 py-0.5 rounded text-xs font-semibold <?= $statusDarkMap[$p['status']] ?? 'bg-gray-100 text-gray-600 dark:bg-slate-700 dark:text-slate-400' ?>">
                                <?= sanitize_string(ucfirst($p['status'])) ?>
                            </span>
                        </td>
                        <td class="px-5 py-4 text-right text-gray-400 dark:text-slate-500 text-xs"><?= time_ago($p['created_at']) ?></td>
                        <td class="px-5 py-4">
                            <div class="flex items-center justify-end gap-2">
                                <button type="button" onclick='openDetailModal(<?= json_encode([
            'id' => (int) $p['id'],
            'milestone' => $p['milestone_title'],
            'client_name' => $p['client_name'],
            'client_email' => $p['client_email'],
            'client_image' => get_profile_image($p['client_image']),
            'freelancer_name' => $p['freelancer_name'],
            'freelancer_email' => $p['freelancer_email'],
            'freelancer_image' => get_profile_image($p['freelancer_image']),
            'total_amount' => (float) $p['total_amount'],
            'platform_fee' => (float) $p['platform_fee'],
            'freelancer_net' => (float) $p['freelancer_net'],
            'status' => $p['status'],
            'created_at' => $p['created_at'],
        ]) ?>)'
                                        class="w-8 h-8 rounded-lg bg-blue-50 dark:bg-blue-900/30 text-blue-500 dark:text-blue-400 flex items-center justify-center hover:bg-blue-100 dark:hover:bg-blue-900/50 transition-colors" title="View Details">
                                    <i data-lucide="eye" class="text-xs"></i>
                                </button>
                                <?php if ($p['status'] === 'pending'): ?>
                                <form method="POST" action="payment_action.php" class="inline" onsubmit="return confirm('Mark this payment as processing?')">
                                    <?= csrf_field() ?>
                                    <input type="hidden" name="action" value="processing">
                                    <input type="hidden" name="payment_id" value="<?= (int) $p['id'] ?>">
                                    <button type="submit" class="w-8 h-8 rounded-lg bg-blue-50 dark:bg-blue-900/30 text-blue-500 dark:text-blue-400 flex items-center justify-center hover:bg-blue-100 dark:hover:bg-blue-900/50 transition-colors" title="Mark Processing">
                                        <i data-lucide="settings" class="text-xs"></i>
                                    </button>
                                </form>
                                <?php endif; ?>
                                <?php if ($p['status'] === 'processing'): ?>
                                <form method="POST" action="payment_action.php" class="inline" onsubmit="return confirm('Mark this payment as completed?')">
                                    <?= csrf_field() ?>
                                    <input type="hidden" name="action" value="completed">
                                    <input type="hidden" name="payment_id" value="<?= (int) $p['id'] ?>">
                                    <button type="submit" class="w-8 h-8 rounded-lg bg-emerald-50 dark:bg-emerald-900/30 text-emerald-500 dark:text-emerald-400 flex items-center justify-center hover:bg-emerald-100 dark:hover:bg-emerald-900/50 transition-colors" title="Mark Completed">
                                        <i data-lucide="check" class="text-xs"></i>
                                    </button>
                                </form>
                                <?php endif; ?>
                                <?php if (in_array($p['status'], ['pending', 'processing'])): ?>
                                <form method="POST" action="payment_action.php" class="inline" onsubmit="return confirm('Are you sure you want to REFUND this payment? This cannot be undone.')">
                                    <?= csrf_field() ?>
                                    <input type="hidden" name="action" value="refunded">
                                    <input type="hidden" name="payment_id" value="<?= (int) $p['id'] ?>">
                                    <button type="submit" class="w-8 h-8 rounded-lg bg-red-50 dark:bg-red-900/30 text-red-500 dark:text-red-400 flex items-center justify-center hover:bg-red-100 dark:hover:bg-red-900/50 transition-colors" title="Refund">
                                        <i data-lucide="undo" class="text-xs"></i>
                                    </button>
                                </form>
                                <?php endif; ?>
                            </div>
                        </td>
                    </tr>
                <?php endwhile; ?>
                </tbody>
            </table>
        </div>
        <?php else: ?>
        <div class="text-center py-16">
            <div class="w-16 h-16 rounded-2xl bg-gray-100 dark:bg-slate-700 flex items-center justify-center mx-auto mb-4">
                <i data-lucide="credit-card" class="text-2xl text-gray-300 dark:text-slate-500"></i>
            </div>
            <p class="text-gray-500 dark:text-slate-400 text-sm mb-1">No payments found</p>
            <p class="text-gray-400 dark:text-slate-500 text-xs">Try adjusting your search or filters</p>
        </div>
        <?php endif; ?>
    </div>

    <!-- ═══ PAGINATION ═══════════════════════════════════════════════════ -->
    <?php render_pagination($pagination, $baseUrl); ?>


    <!-- ═══ PAYMENT DETAIL DRAWER ══════════════════════════════════════ -->
    <div id="pdOverlay" class="pd-overlay" onclick="closePaymentDrawer(event)">
        <div class="pd-drawer border-l border-gray-200 dark:border-slate-700" onclick="event.stopPropagation()">

            <!-- Header -->
            <div class="pd-drawer-header">
                <div class="flex items-start justify-between mb-1">
                    <div>
                        <h2 class="text-lg font-bold text-gray-900 dark:text-white" id="pdPaymentId">#--</h2>
                        <p class="text-xs text-gray-400 dark:text-slate-500 mt-0.5" id="pdTimestamp">--</p>
                    </div>
                    <div class="flex items-center gap-2">
                        <span id="pdStatusBadge" class="inline-block px-2.5 py-0.5 rounded text-xs font-semibold"></span>
                        <button onclick="closePaymentDrawer()" class="w-8 h-8 rounded-lg bg-gray-100 dark:bg-slate-700 flex items-center justify-center text-gray-400 dark:text-slate-400 hover:text-gray-600 dark:hover:text-slate-200 hover:bg-gray-200 dark:hover:bg-slate-600 transition-colors" title="Close">
                            <i data-lucide="x" class="text-sm"></i>
                        </button>
                    </div>
                </div>
                <div class="mt-3 pb-4 border-b border-gray-100 dark:border-slate-700">
                    <p class="text-xs text-gray-400 dark:text-slate-500">Milestone</p>
                    <p class="text-sm font-semibold text-gray-900 dark:text-white mt-0.5" id="pdMilestone">--</p>
                </div>
            </div>

            <!-- Body -->
            <div class="pd-drawer-body">

                <!-- Financial Breakdown Receipt -->
                <div class="mb-6">
                    <h3 class="text-xs font-semibold text-gray-400 dark:text-slate-500 uppercase tracking-wider mb-3">Financial Breakdown</h3>
                    <div class="pd-receipt">
                        <div class="flex items-center justify-between py-2.5 border-b border-gray-200 dark:border-slate-600/50">
                            <span class="text-sm text-gray-600 dark:text-slate-400">Total Amount</span>
                            <span class="text-sm font-bold text-gray-900 dark:text-white" id="pdTotal">$0.00</span>
                        </div>
                        <div class="flex items-center justify-between py-2.5 border-b border-gray-200 dark:border-slate-600/50">
                            <span class="text-sm text-gray-600 dark:text-slate-400">Platform Fee</span>
                            <span class="text-sm font-bold text-amber-600 dark:text-amber-400" id="pdFee">$0.00</span>
                        </div>
                        <div class="flex items-center justify-between py-2.5">
                            <span class="text-sm font-semibold text-gray-900 dark:text-white">Freelancer Net</span>
                            <span class="text-sm font-bold text-emerald-600 dark:text-emerald-400" id="pdNet">$0.00</span>
                        </div>
                    </div>
                </div>

                <!-- Party Cards -->
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-3 mb-6">
                    <!-- Client -->
                    <div class="pd-party-card">
                        <p class="text-xs font-semibold text-blue-500 dark:text-blue-400 uppercase tracking-wider mb-3">Client</p>
                        <div class="flex items-center gap-3">
                            <img id="pdClientAvatar" src="" class="w-10 h-10 rounded-full object-cover border-2 border-gray-100 dark:border-slate-600 flex-shrink-0">
                            <div class="min-w-0">
                                <p class="text-sm font-semibold text-gray-900 dark:text-white truncate" id="pdClientName">--</p>
                                <p class="text-xs text-gray-400 dark:text-slate-500 truncate" id="pdClientEmail">--</p>
                            </div>
                        </div>
                    </div>
                    <!-- Freelancer -->
                    <div class="pd-party-card">
                        <p class="text-xs font-semibold text-emerald-500 dark:text-emerald-400 uppercase tracking-wider mb-3">Freelancer</p>
                        <div class="flex items-center gap-3">
                            <img id="pdFreelancerAvatar" src="" class="w-10 h-10 rounded-full object-cover border-2 border-gray-100 dark:border-slate-600 flex-shrink-0">
                            <div class="min-w-0">
                                <p class="text-sm font-semibold text-gray-900 dark:text-white truncate" id="pdFreelancerName">--</p>
                                <p class="text-xs text-gray-400 dark:text-slate-500 truncate" id="pdFreelancerEmail">--</p>
                            </div>
                        </div>
                    </div>
                </div>

            </div>

            <!-- Footer Actions -->
            <div class="pd-drawer-footer">
                <div class="flex items-center gap-3">
                    <button type="button" onclick="exportPDF()" class="flex-1 inline-flex items-center justify-center gap-2 px-4 py-2.5 rounded-lg border border-gray-200 dark:border-slate-600 text-sm font-medium text-gray-700 dark:text-slate-300 hover:bg-gray-50 dark:hover:bg-slate-700 transition-colors">
                        <i data-lucide="file-text" class="text-gray-400 dark:text-slate-500"></i> Download Invoice
                    </button>
                    <button type="button" onclick="issueRefundFromDrawer()" id="pdRefundBtn" class="flex-1 inline-flex items-center justify-center gap-2 px-4 py-2.5 rounded-lg border border-red-200 dark:border-red-800 text-sm font-medium text-red-600 dark:text-red-400 hover:bg-red-50 dark:hover:bg-red-900/20 transition-colors">
                        <i data-lucide="undo"></i> Issue Refund
                    </button>
                </div>
            </div>

        </div>
    </div>

    <!-- ═══ EXPORT FORMS (hidden) ══════════════════════════════════════ -->
    <form id="exportCSVForm" method="POST" action="payment_action.php" class="hidden">
        <?= csrf_field() ?>
        <input type="hidden" name="action" value="export_csv">
        <?php foreach ($filterParams as $k => $v): ?>
            <input type="hidden" name="<?= sanitize_string($k) ?>" value="<?= sanitize_string($v) ?>">
        <?php endforeach; ?>
    </form>
    <form id="exportPDFForm" method="POST" action="payment_action.php" class="hidden">
        <?= csrf_field() ?>
        <input type="hidden" name="action" value="export_pdf">
        <?php foreach ($filterParams as $k => $v): ?>
            <input type="hidden" name="<?= sanitize_string($k) ?>" value="<?= sanitize_string($v) ?>">
        <?php endforeach; ?>
    </form>

    <script>
        // ── Payment Detail Drawer ───────────────────────────────────────
        const pdStatusColors = {
            completed: 'bg-emerald-100 text-emerald-700 dark:bg-emerald-900/30 dark:text-emerald-400',
            pending: 'bg-amber-100 text-amber-700 dark:bg-amber-900/30 dark:text-amber-400',
            processing: 'bg-blue-100 text-blue-700 dark:bg-blue-900/30 dark:text-blue-400',
            refunded: 'bg-red-100 text-red-700 dark:bg-red-900/30 dark:text-red-400'
        };

        let pdCurrentPayment = null;

        function openDetailModal(data) {
            pdCurrentPayment = data;

            document.getElementById('pdPaymentId').textContent = '#' + data.id;
            document.getElementById('pdTimestamp').textContent = new Date(data.created_at).toLocaleDateString('en-US', {
                year: 'numeric', month: 'long', day: 'numeric', hour: '2-digit', minute: '2-digit'
            });
            document.getElementById('pdMilestone').textContent = data.milestone;
            document.getElementById('pdTotal').textContent = '$' + data.total_amount.toFixed(2);
            document.getElementById('pdFee').textContent = '$' + data.platform_fee.toFixed(2);
            document.getElementById('pdNet').textContent = '$' + data.freelancer_net.toFixed(2);

            document.getElementById('pdClientName').textContent = data.client_name;
            document.getElementById('pdClientEmail').textContent = data.client_email;
            document.getElementById('pdClientAvatar').src = data.client_image || '<?= get_profile_image(null) ?>';
            document.getElementById('pdFreelancerName').textContent = data.freelancer_name;
            document.getElementById('pdFreelancerEmail').textContent = data.freelancer_email;
            document.getElementById('pdFreelancerAvatar').src = data.freelancer_image || '<?= get_profile_image(null) ?>';

            // Set badge
            const badge = document.getElementById('pdStatusBadge');
            badge.textContent = data.status.charAt(0).toUpperCase() + data.status.slice(1);
            badge.className = 'inline-block px-2.5 py-0.5 rounded text-xs font-semibold ' + (pdStatusColors[data.status] || 'bg-gray-100 text-gray-600 dark:bg-slate-700 dark:text-slate-400');

            // Show/hide refund button based on status
            const refundBtn = document.getElementById('pdRefundBtn');
            if (['pending', 'processing'].includes(data.status)) {
                refundBtn.style.display = '';
            } else {
                refundBtn.style.display = 'none';
            }

            // Open drawer
            document.getElementById('pdOverlay').classList.add('open');
            document.body.style.overflow = 'hidden';
        }

        function closePaymentDrawer(e) {
            if (e && e.target !== e.currentTarget) return;
            document.getElementById('pdOverlay').classList.remove('open');
            document.body.style.overflow = '';
            pdCurrentPayment = null;
        }

        function issueRefundFromDrawer() {
            if (!pdCurrentPayment) return;
            if (!confirm('Are you sure you want to REFUND this payment? This cannot be undone.')) return;

            const form = document.createElement('form');
            form.method = 'POST';
            form.action = 'payment_action.php';
            form.innerHTML = `
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="refunded">
                <input type="hidden" name="payment_id" value="${pdCurrentPayment.id}">
            `;
            document.body.appendChild(form);
            form.submit();
        }

        document.addEventListener('keydown', function(e) {
            if (e.key === 'Escape') closePaymentDrawer();
        });

        // ── Export Functions ─────────────────────────────────────────────
        function exportCSV() {
            document.getElementById('exportCSVForm').submit();
        }

        function exportPDF() {
            var btn = document.querySelector('button[onclick="exportPDF()"]');
            var origHTML = btn.innerHTML;
            btn.innerHTML = '<i data-lucide="loader" class="animate-spin text-red-500"></i> Generating...';
            btn.disabled = true;

            var form = document.getElementById('exportPDFForm');
            var formData = new FormData(form);

            fetch('payment_action.php', {
                method: 'POST',
                body: formData
            })
            .then(function(response) {
                if (!response.ok) throw new Error('Server error');
                return response.text();
            })
            .then(function(html) {
                var blob = new Blob([html], { type: 'text/html' });
                var blobUrl = URL.createObjectURL(blob);

                var iframe = document.createElement('iframe');
                iframe.style.cssText = 'position:fixed;width:1px;height:1px;top:-9999px;left:-9999px;opacity:0;pointer-events:none';
                iframe.src = blobUrl;
                document.body.appendChild(iframe);

                var done = false;
                function onMsg(e) {
                    if (e.data === 'pdf_done' && !done) {
                        done = true;
                        window.removeEventListener('message', onMsg);
                        setTimeout(function() {
                            document.body.removeChild(iframe);
                            URL.revokeObjectURL(blobUrl);
                        }, 1000);
                        btn.innerHTML = origHTML;
                        btn.disabled = false;
                        showExportNotification('PDF downloaded successfully!', 'success');
                    }
                }
                window.addEventListener('message', onMsg);

                setTimeout(function() {
                    if (!done) {
                        done = true;
                        window.removeEventListener('message', onMsg);
                        try { document.body.removeChild(iframe); } catch(e) {}
                        URL.revokeObjectURL(blobUrl);
                        btn.innerHTML = origHTML;
                        btn.disabled = false;
                        showExportNotification('PDF download timed out. Please try again.', 'error');
                    }
                }, 30000);
            })
            .catch(function() {
                btn.innerHTML = origHTML;
                btn.disabled = false;
                showExportNotification('Error generating PDF. Please try again.', 'error');
            });
        }

        function showExportNotification(text, type) {
            var existing = document.getElementById('exportNotif');
            if (existing) existing.remove();

            var div = document.createElement('div');
            div.id = 'exportNotif';
            div.style.cssText = 'position:fixed;top:20px;right:20px;z-index:9999;padding:14px 20px;border-radius:10px;display:flex;align-items:center;gap:10px;font-size:13px;font-weight:600;box-shadow:0 8px 24px rgba(0,0,0,.12);transform:translateX(120%);transition:transform .35s cubic-bezier(.4,0,.2,1)';

            if (type === 'success') {
                div.style.background = '#ECFDF5';
                div.style.border = '1px solid #A7F3D0';
                div.style.color = '#065F46';
                div.innerHTML = '<svg width="18" height="18" fill="none" stroke="#10b981" viewBox="0 0 24 24" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>' + text;
            } else {
                div.style.background = '#FEF2F2';
                div.style.border = '1px solid #FECACA';
                div.style.color = '#991B1B';
                div.innerHTML = '<svg width="18" height="18" fill="none" stroke="#ef4444" viewBox="0 0 24 24" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>' + text;
            }

            document.body.appendChild(div);
            requestAnimationFrame(function() {
                requestAnimationFrame(function() { div.style.transform = 'translateX(0)'; });
            });
            setTimeout(function() {
                div.style.transform = 'translateX(120%)';
                setTimeout(function() { div.remove(); }, 400);
            }, 4000);
        }
    </script>
<?php require_once __DIR__ . '/../components/layout_end.php'; ?>
