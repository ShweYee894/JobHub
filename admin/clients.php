<?php

/**
 * Admin Clients — Read-only view of all clients with stats
 */
require_once __DIR__ . '/../auth/auth.php';
require_role('admin');
require_once __DIR__ . '/../config/db.php';

$currentPage = 'clients';

// ── Filters ─────────────────────────────────────────────────────────
$search = trim($_GET['search'] ?? '');
$statusF = $_GET['status'] ?? '';
$industryF = trim($_GET['industry'] ?? '');
$sort = $_GET['sort'] ?? 'created_at';
$dir = strtoupper($_GET['dir'] ?? 'DESC') === 'ASC' ? 'ASC' : 'DESC';
$page = max(1, sanitize_int($_GET['page'] ?? 1));
$perPage = 15;

$allowedStatuses = ['active', 'flagged', 'suspended'];
$allowedSorts = ['name', 'company_name', 'total_spent', 'wallet_balance', 'total_jobs', 'avg_rating', 'created_at'];
if ($statusF && !in_array($statusF, $allowedStatuses)) $statusF = '';
if (!in_array($sort, $allowedSorts)) $sort = 'created_at';

// ── Build query ─────────────────────────────────────────────────────
$where = [];
$params = [];
$types = '';

if ($search !== '') {
    $where[] = '(u.name LIKE ? OR u.email LIKE ? OR c.company_name LIKE ?)';
    $searchTerm = "%{$search}%";
    $params[] = $searchTerm;
    $params[] = $searchTerm;
    $params[] = $searchTerm;
    $types .= 'sss';
}
if ($statusF) {
    $where[] = 'u.status = ?';
    $params[] = $statusF;
    $types .= 's';
}
if ($industryF !== '') {
    $where[] = 'c.industry = ?';
    $params[] = $industryF;
    $types .= 's';
}

$whereSql = $where ? 'WHERE ' . implode(' AND ', $where) : '';

// ── Distinct industries for filter ──────────────────────────────────
$indResult = $conn->query("SELECT DISTINCT industry FROM clients WHERE industry IS NOT NULL AND industry != '' ORDER BY industry");
$industries = [];
while ($indRow = $indResult->fetch_assoc()) $industries[] = $indRow['industry'];

// ── Count total ─────────────────────────────────────────────────────
$countSql = "SELECT COUNT(*) AS cnt FROM clients c JOIN users u ON c.client_id = u.id {$whereSql}";
$countStmt = $conn->prepare($countSql);
if ($params) $countStmt->bind_param($types, ...$params);
$countStmt->execute();
$totalItems = (int) $countStmt->get_result()->fetch_assoc()['cnt'];
$countStmt->close();

$pagination = paginate($totalItems, $perPage, $page);

// ── Fetch clients ───────────────────────────────────────────────────
$allowedSortCols = [
    'name' => 'u.name', 'company_name' => 'c.company_name', 'total_spent' => 'c.total_spent',
    'wallet_balance' => 'u.wallet_balance', 'total_jobs' => 'c.total_jobs', 'created_at' => 'c.created_at',
];
$sortCol = $allowedSortCols[$sort] ?? 'c.created_at';
// avg_rating not in DB column, sort by total_spent as proxy when selected
if ($sort === 'avg_rating') $sortCol = 'c.total_spent';

$querySql = "SELECT u.id, u.name, u.email, u.phone, u.profile_image, u.status, u.fraud_score, u.wallet_balance, u.created_at AS user_joined,
                    c.id AS client_detail_id, c.company_name, c.company_logo, c.industry, c.company_size, c.total_spent, c.total_jobs, c.created_at AS client_joined
             FROM clients c
             JOIN users u ON c.client_id = u.id
             {$whereSql}
             ORDER BY {$sortCol} {$dir}
             LIMIT ? OFFSET ?";
$queryStmt = $conn->prepare($querySql);
$bindTypes = $types . 'ii';
$bindParams = array_merge($params, [$perPage, $pagination['offset']]);
$queryStmt->bind_param($bindTypes, ...$bindParams);
$queryStmt->execute();
$clientsResult = $queryStmt->get_result();
$queryStmt->close();

// ── Bulk stats ──────────────────────────────────────────────────────
$statsStmt = $conn->query("SELECT u.status, COUNT(*) AS cnt FROM clients c JOIN users u ON c.client_id = u.id GROUP BY u.status");
$statusCounts = ['active' => 0, 'flagged' => 0, 'suspended' => 0];
while ($row = $statsStmt->fetch_assoc()) $statusCounts[$row['status']] = (int) $row['cnt'];
$statsStmt->close();

$totalSpentStmt = $conn->query("SELECT COALESCE(SUM(c.total_spent), 0) AS total FROM clients c JOIN users u ON c.client_id = u.id");
$platformTotalSpent = (float) $totalSpentStmt->fetch_assoc()['total'];
$totalSpentStmt->close();

$totalJobsStmt = $conn->query("SELECT COALESCE(SUM(c.total_jobs), 0) AS total FROM clients c");
$platformTotalJobs = (int) $totalJobsStmt->fetch_assoc()['total'];
$totalJobsStmt->close();

// ── Batch: contracts count, reviews avg per visible client ──────────
$clientIds = [];
$clientIdMap = [];
while ($row = $clientsResult->fetch_assoc()) {
    $clientIds[] = (int) $row['id'];
    $clientIdMap[(int) $row['id']] = $row;
}
$clientsResult->free();

$contractCounts = [];
$reviewStats = [];
$disputeCounts = [];

if ($clientIds) {
    $idPlaceholders = implode(',', array_fill(0, count($clientIds), '?'));

    // Contracts count per client (client_id in contracts = users.id)
    $ccStmt = $conn->prepare("SELECT client_id, COUNT(*) AS cnt FROM contracts WHERE client_id IN ({$idPlaceholders}) GROUP BY client_id");
    $ccTypes = str_repeat('i', count($clientIds));
    $ccStmt->bind_param($ccTypes, ...$clientIds);
    $ccStmt->execute();
    $ccRes = $ccStmt->get_result();
    while ($r = $ccRes->fetch_assoc()) $contractCounts[(int) $r['client_id']] = (int) $r['cnt'];
    $ccStmt->close();

    // Reviews avg per client (reviewee_id = users.id)
    $rvStmt = $conn->prepare("SELECT reviewee_id, AVG(rating) AS avg_rating, COUNT(*) AS total FROM reviews WHERE reviewee_id IN ({$idPlaceholders}) GROUP BY reviewee_id");
    $rvStmt->bind_param($ccTypes, ...$clientIds);
    $rvStmt->execute();
    $rvRes = $rvStmt->get_result();
    while ($r = $rvRes->fetch_assoc()) $reviewStats[(int) $r['reviewee_id']] = ['avg' => round((float) $r['avg_rating'], 1), 'total' => (int) $r['total']];
    $rvStmt->close();

    // Disputes raised against client
    $dpStmt = $conn->prepare("SELECT `against`, COUNT(*) AS cnt FROM dispute_tickets WHERE `against` IN ({$idPlaceholders}) GROUP BY `against`");
    $dpStmt->bind_param($ccTypes, ...$clientIds);
    $dpStmt->execute();
    $dpRes = $dpStmt->get_result();
    while ($r = $dpRes->fetch_assoc()) $disputeCounts[(int) $r['against']] = (int) $r['cnt'];
    $dpStmt->close();
}

// ── Badge maps ──────────────────────────────────────────────────────
$statusColors = [
    'active' => 'bg-emerald-100 text-emerald-700 dark:bg-emerald-900/30 dark:text-emerald-400',
    'flagged' => 'bg-amber-100 text-amber-700 dark:bg-amber-900/30 dark:text-amber-400',
    'suspended' => 'bg-red-100 text-red-700 dark:bg-red-900/30 dark:text-red-400',
];
$sizeColors = [
    'Startup' => 'bg-cyan-100 text-cyan-700 dark:bg-cyan-900/30 dark:text-cyan-400',
    'Small' => 'bg-blue-100 text-blue-700 dark:bg-blue-900/30 dark:text-blue-400',
    'Medium' => 'bg-violet-100 text-violet-700 dark:bg-violet-900/30 dark:text-violet-400',
    'Large' => 'bg-amber-100 text-amber-700 dark:bg-amber-900/30 dark:text-amber-400',
];

function fraudColor(int $score): string
{
    if ($score <= 30) return 'text-emerald-500 dark:text-emerald-400';
    if ($score <= 60) return 'text-amber-500 dark:text-amber-400';
    return 'text-red-500 dark:text-red-400';
}

// Build base URL for pagination/sorting
$baseUrl = 'clients.php?';
if ($search !== '') $baseUrl .= 'search=' . urlencode($search) . '&';
if ($statusF) $baseUrl .= 'status=' . urlencode($statusF) . '&';
if ($industryF !== '') $baseUrl .= 'industry=' . urlencode($industryF) . '&';
$baseUrl = rtrim($baseUrl, '?&');
if (strpos($baseUrl, '&') === false) $baseUrl = rtrim($baseUrl, '?');

function sortUrl(string $base, string $col, string $currentSort, string $currentDir): string
{
    $newDir = ($currentSort === $col && $currentDir === 'ASC') ? 'DESC' : 'ASC';
    $sep = strpos($base, '?') !== false ? '&' : '?';
    return $base . $sep . 'sort=' . $col . '&dir=' . $newDir;
}

function sortIcon(string $col, string $currentSort, string $currentDir): string
{
    if ($col !== $currentSort) return '<i data-lucide="arrow-up-down" class="text-gray-300 dark:text-slate-600 text-[10px] ml-1"></i>';
    $icon = $currentDir === 'ASC' ? 'arrow-up' : 'arrow-down';
    return '<i data-lucide="' . $icon . '" class="text-blue-500 text-[10px] ml-1"></i>';
}

// ── Admin nav setup ─────────────────────────────────────────────────
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
$pageTitle = 'Clients';
$pageSubtitle = number_format($totalItems) . ' client' . ($totalItems !== 1 ? 's' : '') . ' found';
$activePage = 'clients';
$user = ['name' => $adminName, 'profile_image' => $_navUserRow['profile_image'] ?? null];
$unreadCount = 0;
$profileLink = 'profile.php';
require_once __DIR__ . '/../components/layout_start.php';
?>
    <style>
    .kpi-mini{transition:transform .2s,box-shadow .2s}
    .kpi-mini:hover{transform:translateY(-1px);box-shadow:0 4px 15px rgba(0,0,0,.05)}
    .client-row:hover .client-actions{opacity:1}
    .client-actions{opacity:0;transition:opacity .15s}
    </style>

    <?php display_flash('success'); ?>
    <?php display_flash('error'); ?>

    <!-- ═══ STAT CARDS ══════════════════════════════════════════════════ -->
    <div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-6 gap-3">
        <a href="clients.php" class="kpi-mini fade-in block rounded-xl p-4 border border-gray-100 dark:border-slate-700 bg-white dark:bg-slate-800 shadow-sm no-underline text-inherit" style="animation-delay:0s">
            <p class="text-[10px] font-semibold text-gray-400 dark:text-slate-500 uppercase tracking-wider mb-1">Total Clients</p>
            <p class="text-xl font-extrabold text-gray-900 dark:text-white"><?= number_format($totalItems) ?></p>
        </a>
        <a href="clients.php?status=active" class="kpi-mini fade-in block rounded-xl p-4 border <?= $statusF === 'active' ? 'border-emerald-400 dark:border-emerald-500 bg-emerald-50 dark:bg-emerald-900/20' : 'border-gray-100 dark:border-slate-700 bg-white dark:bg-slate-800' ?> shadow-sm no-underline text-inherit" style="animation-delay:.03s">
            <p class="text-[10px] font-semibold text-gray-400 dark:text-slate-500 uppercase tracking-wider mb-1">Active</p>
            <p class="text-xl font-extrabold text-emerald-600 dark:text-emerald-400"><?= number_format($statusCounts['active']) ?></p>
        </a>
        <a href="clients.php?status=flagged" class="kpi-mini fade-in block rounded-xl p-4 border <?= $statusF === 'flagged' ? 'border-amber-400 dark:border-amber-500 bg-amber-50 dark:bg-amber-900/20' : 'border-gray-100 dark:border-slate-700 bg-white dark:bg-slate-800' ?> shadow-sm no-underline text-inherit" style="animation-delay:.06s">
            <p class="text-[10px] font-semibold text-gray-400 dark:text-slate-500 uppercase tracking-wider mb-1">Flagged</p>
            <p class="text-xl font-extrabold text-amber-600 dark:text-amber-400"><?= number_format($statusCounts['flagged']) ?></p>
        </a>
        <a href="clients.php?status=suspended" class="kpi-mini fade-in block rounded-xl p-4 border <?= $statusF === 'suspended' ? 'border-red-400 dark:border-red-500 bg-red-50 dark:bg-red-900/20' : 'border-gray-100 dark:border-slate-700 bg-white dark:bg-slate-800' ?> shadow-sm no-underline text-inherit" style="animation-delay:.09s">
            <p class="text-[10px] font-semibold text-gray-400 dark:text-slate-500 uppercase tracking-wider mb-1">Suspended</p>
            <p class="text-xl font-extrabold text-red-600 dark:text-red-400"><?= number_format($statusCounts['suspended']) ?></p>
        </a>
        <div class="kpi-mini fade-in rounded-xl p-4 border border-gray-100 dark:border-slate-700 bg-white dark:bg-slate-800 shadow-sm" style="animation-delay:.12s">
            <p class="text-[10px] font-semibold text-gray-400 dark:text-slate-500 uppercase tracking-wider mb-1">Platform Spend</p>
            <p class="text-xl font-extrabold text-violet-600 dark:text-violet-400"><?= format_currency($platformTotalSpent) ?></p>
        </div>
        <div class="kpi-mini fade-in rounded-xl p-4 border border-gray-100 dark:border-slate-700 bg-white dark:bg-slate-800 shadow-sm" style="animation-delay:.15s">
            <p class="text-[10px] font-semibold text-gray-400 dark:text-slate-500 uppercase tracking-wider mb-1">Total Jobs Posted</p>
            <p class="text-xl font-extrabold text-blue-600 dark:text-blue-400"><?= number_format($platformTotalJobs) ?></p>
        </div>
    </div>

    <!-- ═══ SEARCH & FILTERS ══════════════════════════════════════════ -->
    <div class="bg-white dark:bg-slate-800 rounded-2xl border border-gray-100 dark:border-slate-700 shadow-sm p-5 fade-in" style="animation-delay:.18s">
        <form method="GET" action="clients.php" id="filterForm">
            <input type="hidden" name="sort" value="<?= sanitize_string($sort) ?>">
            <input type="hidden" name="dir" value="<?= sanitize_string($dir) ?>">
            <div class="flex flex-col lg:flex-row gap-3">
                <div class="flex-1 relative">
                    <i data-lucide="search" class="absolute left-3.5 top-1/2 -translate-y-1/2 text-gray-400 dark:text-slate-500 text-sm"></i>
                    <input type="text" name="search" value="<?= sanitize_string($search) ?>" placeholder="Search by name, email, or company..."
                           class="w-full pl-10 pr-4 py-2.5 rounded-xl border border-gray-200 dark:border-slate-600 text-sm bg-gray-50 dark:bg-slate-700 text-gray-900 dark:text-white placeholder-gray-400 dark:placeholder-slate-500 focus:outline-none focus:ring-2 focus:ring-blue-500/30 focus:border-blue-400 transition-all">
                </div>
                <div class="flex flex-wrap gap-2">
                    <select name="status" class="px-4 py-2.5 rounded-xl border border-gray-200 dark:border-slate-600 text-sm text-gray-600 dark:text-slate-300 focus:outline-none focus:ring-2 focus:ring-blue-500/30 focus:border-blue-400 bg-gray-50 dark:bg-slate-700">
                        <option value="">All Statuses</option>
                        <option value="active" <?= $statusF === 'active' ? 'selected' : '' ?>>Active</option>
                        <option value="flagged" <?= $statusF === 'flagged' ? 'selected' : '' ?>>Flagged</option>
                        <option value="suspended" <?= $statusF === 'suspended' ? 'selected' : '' ?>>Suspended</option>
                    </select>
                    <select name="industry" class="px-4 py-2.5 rounded-xl border border-gray-200 dark:border-slate-600 text-sm text-gray-600 dark:text-slate-300 focus:outline-none focus:ring-2 focus:ring-blue-500/30 focus:border-blue-400 bg-gray-50 dark:bg-slate-700">
                        <option value="">All Industries</option>
                        <?php foreach ($industries as $ind): ?>
                        <option value="<?= sanitize_string($ind) ?>" <?= $industryF === $ind ? 'selected' : '' ?>><?= sanitize_string($ind) ?></option>
                        <?php endforeach; ?>
                    </select>
                    <button type="submit" class="px-5 py-2.5 rounded-xl bg-blue-600 hover:bg-blue-700 text-white text-sm font-semibold transition-colors inline-flex items-center gap-1.5 shadow-lg shadow-blue-500/20">
                        <i data-lucide="filter" class="text-xs"></i> Filter
                    </button>
                    <?php if ($search || $statusF || $industryF): ?>
                        <a href="clients.php" class="px-4 py-2.5 rounded-xl border border-gray-200 dark:border-slate-600 text-sm font-medium text-gray-500 dark:text-slate-400 hover:bg-gray-50 dark:hover:bg-slate-700 transition-colors text-center">
                            <i data-lucide="x" class="mr-1"></i> Clear
                        </a>
                    <?php endif; ?>
                </div>
            </div>
        </form>
    </div>

    <!-- ═══ CLIENTS TABLE ══════════════════════════════════════════════ -->
    <div class="bg-white dark:bg-slate-800 rounded-2xl border border-gray-100 dark:border-slate-700 shadow-sm overflow-hidden fade-in" style="animation-delay:.21s">
        <?php if (count($clientIdMap) > 0): ?>
        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead>
                    <tr class="border-b border-gray-100 dark:border-slate-700 bg-gray-50/50 dark:bg-slate-700/30">
                        <th class="text-left py-4 px-4 text-[10px] font-semibold text-gray-400 dark:text-slate-500 uppercase tracking-wider cursor-pointer hover:text-gray-600 dark:hover:text-slate-300 transition-colors" onclick="window.location.href='<?= sortUrl($baseUrl, 'name', $sort, $dir) ?>'">Client <?= sortIcon('name', $sort, $dir) ?></th>
                        <th class="text-left py-4 px-3 text-[10px] font-semibold text-gray-400 dark:text-slate-500 uppercase tracking-wider cursor-pointer hover:text-gray-600 dark:hover:text-slate-300 transition-colors" onclick="window.location.href='<?= sortUrl($baseUrl, 'company_name', $sort, $dir) ?>'">Company <?= sortIcon('company_name', $sort, $dir) ?></th>
                        <th class="text-left py-4 px-3 text-[10px] font-semibold text-gray-400 dark:text-slate-500 uppercase tracking-wider">Status</th>
                        <th class="text-center py-4 px-3 text-[10px] font-semibold text-gray-400 dark:text-slate-500 uppercase tracking-wider cursor-pointer hover:text-gray-600 dark:hover:text-slate-300 transition-colors" onclick="window.location.href='<?= sortUrl($baseUrl, 'total_spent', $sort, $dir) ?>'">Spent <?= sortIcon('total_spent', $sort, $dir) ?></th>
                        <th class="text-center py-4 px-3 text-[10px] font-semibold text-gray-400 dark:text-slate-500 uppercase tracking-wider cursor-pointer hover:text-gray-600 dark:hover:text-slate-300 transition-colors" onclick="window.location.href='<?= sortUrl($baseUrl, 'total_jobs', $sort, $dir) ?>'">Jobs <?= sortIcon('total_jobs', $sort, $dir) ?></th>
                        <th class="text-center py-4 px-3 text-[10px] font-semibold text-gray-400 dark:text-slate-500 uppercase tracking-wider">Contracts</th>
                        <th class="text-center py-4 px-3 text-[10px] font-semibold text-gray-400 dark:text-slate-500 uppercase tracking-wider cursor-pointer hover:text-gray-600 dark:hover:text-slate-300 transition-colors" onclick="window.location.href='<?= sortUrl($baseUrl, 'avg_rating', $sort, $dir) ?>'">Rating <?= sortIcon('avg_rating', $sort, $dir) ?></th>
                        <th class="text-center py-4 px-3 text-[10px] font-semibold text-gray-400 dark:text-slate-500 uppercase tracking-wider">Disputes</th>
                        <th class="text-right py-4 px-3 text-[10px] font-semibold text-gray-400 dark:text-slate-500 uppercase tracking-wider cursor-pointer hover:text-gray-600 dark:hover:text-slate-300 transition-colors" onclick="window.location.href='<?= sortUrl($baseUrl, 'wallet_balance', $sort, $dir) ?>'">Wallet <?= sortIcon('wallet_balance', $sort, $dir) ?></th>
                        <th class="text-right py-4 px-3 text-[10px] font-semibold text-gray-400 dark:text-slate-500 uppercase tracking-wider cursor-pointer hover:text-gray-600 dark:hover:text-slate-300 transition-colors" onclick="window.location.href='<?= sortUrl($baseUrl, 'created_at', $sort, $dir) ?>'">Joined <?= sortIcon('created_at', $sort, $dir) ?></th>
                        <th class="text-right py-4 px-3 text-[10px] font-semibold text-gray-400 dark:text-slate-500 uppercase tracking-wider">Actions</th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ($clientIdMap as $cid => $cl): ?>
                    <?php
                    $rev = $reviewStats[$cid] ?? null;
                    $avgRating = $rev ? $rev['avg'] : 0;
                    $totalReviews = $rev ? $rev['total'] : 0;
                    $contracts = $contractCounts[$cid] ?? 0;
                    $disputes = $disputeCounts[$cid] ?? 0;
                    ?>
                    <tr class="client-row border-b border-gray-50 dark:border-slate-700/50 last:border-0 hover:bg-gray-50/50 dark:hover:bg-slate-700/30 transition-colors">
                        <td class="py-4 px-4">
                            <a href="user_detail.php?id=<?= (int) $cl['id'] ?>" class="flex items-center gap-3 no-underline text-inherit">
                                <img src="<?= sanitize_string(get_profile_image($cl['profile_image'])) ?>" class="w-10 h-10 rounded-full object-cover border-2 border-gray-100 dark:border-slate-600 flex-shrink-0">
                                <div class="min-w-0">
                                    <p class="font-semibold text-gray-900 dark:text-white truncate text-sm"><?= sanitize_string($cl['name']) ?></p>
                                    <p class="text-[11px] text-gray-400 dark:text-slate-500 truncate"><?= sanitize_string($cl['email']) ?></p>
                                </div>
                            </a>
                        </td>
                        <td class="py-4 px-3">
                            <div class="min-w-0">
                                <p class="text-sm font-medium text-gray-900 dark:text-white truncate"><?= sanitize_string($cl['company_name'] ?: 'N/A') ?></p>
                                <?php if ($cl['industry']): ?>
                                <p class="text-[10px] text-gray-400 dark:text-slate-500 mt-0.5"><?= sanitize_string($cl['industry']) ?></p>
                                <?php endif; ?>
                            </div>
                        </td>
                        <td class="py-4 px-3">
                            <span class="inline-block px-2.5 py-1 rounded-lg text-[11px] font-semibold <?= $statusColors[$cl['status']] ?? '' ?>">
                                <?= sanitize_string(ucfirst($cl['status'])) ?>
                            </span>
                        </td>
                        <td class="py-4 px-3 text-center">
                            <span class="text-sm font-bold text-violet-600 dark:text-violet-400"><?= format_currency((float) $cl['total_spent']) ?></span>
                        </td>
                        <td class="py-4 px-3 text-center">
                            <span class="inline-flex items-center justify-center w-8 h-8 rounded-lg bg-blue-50 dark:bg-blue-900/30 text-blue-600 dark:text-blue-400 text-xs font-bold"><?= (int) $cl['total_jobs'] ?></span>
                        </td>
                        <td class="py-4 px-3 text-center">
                            <span class="inline-flex items-center justify-center w-8 h-8 rounded-lg bg-indigo-50 dark:bg-indigo-900/30 text-indigo-600 dark:text-indigo-400 text-xs font-bold"><?= $contracts ?></span>
                        </td>
                        <td class="py-4 px-3 text-center">
                            <?php if ($totalReviews > 0): ?>
                            <div class="flex items-center justify-center gap-1">
                                <i data-lucide="star" class="text-yellow-400 text-[10px]"></i>
                                <span class="text-sm font-bold text-gray-900 dark:text-white"><?= $avgRating ?></span>
                                <span class="text-[10px] text-gray-400 dark:text-slate-500">(<?= $totalReviews ?>)</span>
                            </div>
                            <?php else: ?>
                            <span class="text-xs text-gray-300 dark:text-slate-600">—</span>
                            <?php endif; ?>
                        </td>
                        <td class="py-4 px-3 text-center">
                            <?php if ($disputes > 0): ?>
                            <span class="inline-flex items-center justify-center w-8 h-8 rounded-lg bg-red-50 dark:bg-red-900/30 text-red-600 dark:text-red-400 text-xs font-bold"><?= $disputes ?></span>
                            <?php else: ?>
                            <span class="text-xs text-gray-300 dark:text-slate-600">0</span>
                            <?php endif; ?>
                        </td>
                        <td class="py-4 px-3 text-right font-semibold text-gray-700 dark:text-slate-300 text-xs"><?= format_currency((float) $cl['wallet_balance']) ?></td>
                        <td class="py-4 px-3 text-right text-gray-400 dark:text-slate-500 text-xs"><?= time_ago($cl['client_joined']) ?></td>
                        <td class="py-4 px-3">
                            <div class="client-actions flex items-center justify-end">
                                <a href="user_detail.php?id=<?= (int) $cl['id'] ?>" class="w-8 h-8 rounded-lg bg-blue-50 dark:bg-blue-900/30 text-blue-500 dark:text-blue-400 flex items-center justify-center hover:bg-blue-100 dark:hover:bg-blue-900/50 transition-colors" title="View Details">
                                    <i data-lucide="eye" class="text-xs"></i>
                                </a>
                            </div>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php else: ?>
        <div class="text-center py-16">
            <div class="w-16 h-16 rounded-2xl bg-gray-100 dark:bg-slate-700 flex items-center justify-center mx-auto mb-4">
                <i data-lucide="user" class="text-2xl text-gray-300 dark:text-slate-500"></i>
            </div>
            <p class="text-gray-500 dark:text-slate-400 text-sm mb-1">No clients found</p>
            <p class="text-gray-400 dark:text-slate-500 text-xs">Try adjusting your search or filters</p>
        </div>
        <?php endif; ?>
    </div>

    <!-- ═══ PAGINATION ═════════════════════════════════════════════════ -->
    <?php render_pagination($pagination, $baseUrl); ?>

<?php require_once __DIR__ . '/../components/layout_end.php'; ?>
