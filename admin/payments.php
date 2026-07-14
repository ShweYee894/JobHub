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
$dateFrom   = trim($_GET['date_from'] ?? '');
$dateTo     = trim($_GET['date_to'] ?? '');
$statusF    = $_GET['status'] ?? '';
$clientQ    = trim($_GET['client'] ?? '');
$freelancerQ = trim($_GET['freelancer'] ?? '');
$amountMin  = $_GET['amount_min'] ?? '';
$amountMax  = $_GET['amount_max'] ?? '';
$page       = max(1, sanitize_int($_GET['page'] ?? 1));
$perPage    = 12;

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

$r = $conn->query("SELECT COUNT(*) AS cnt FROM payments");
$stats['total_payments'] = (int) $r->fetch_assoc()['cnt'];

$r = $conn->query("SELECT COALESCE(SUM(total_amount), 0) AS total FROM payments WHERE status = 'completed'");
$stats['gross_volume'] = (float) $r->fetch_assoc()['total'];

// ── Build WHERE ───────────────────────────────────────────────────────
$where   = [];
$params  = [];
$types   = '';

if ($dateFrom !== '') {
    $where[]  = 'p.created_at >= ?';
    $params[] = $dateFrom . ' 00:00:00';
    $types   .= 's';
}
if ($dateTo !== '') {
    $where[]  = 'p.created_at <= ?';
    $params[] = $dateTo . ' 23:59:59';
    $types   .= 's';
}
if ($statusF !== '') {
    $where[]  = 'p.status = ?';
    $params[] = $statusF;
    $types   .= 's';
}
if ($clientQ !== '') {
    $where[]  = '(uc.name LIKE ? OR uc.email LIKE ?)';
    $cSearch  = "%{$clientQ}%";
    $params[] = $cSearch;
    $params[] = $cSearch;
    $types   .= 'ss';
}
if ($freelancerQ !== '') {
    $where[]  = '(uf.name LIKE ? OR uf.email LIKE ?)';
    $fSearch  = "%{$freelancerQ}%";
    $params[] = $fSearch;
    $params[] = $fSearch;
    $types   .= 'ss';
}
if ($amountMin !== '') {
    $where[]  = 'p.total_amount >= ?';
    $params[] = (float) $amountMin;
    $types   .= 'd';
}
if ($amountMax !== '') {
    $where[]  = 'p.total_amount <= ?';
    $params[] = (float) $amountMax;
    $types   .= 'd';
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
                    uc.name AS client_name, uc.email AS client_email,
                    uf.name AS freelancer_name, uf.email AS freelancer_email
             FROM payments p
             JOIN milestones m ON p.milestone_id = m.id
             JOIN users uc ON p.payer_id = uc.id
             JOIN users uf ON p.payee_id = uf.id
             {$whereSql}
             ORDER BY p.created_at DESC
             LIMIT ? OFFSET ?";
$queryStmt = $conn->prepare($querySql);
$bindTypes  = $types . 'ii';
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
$chartCounts  = [];
while ($row = $chartResult->fetch_assoc()) {
    $chartLabels[]  = $row['month_label'];
    $chartRevenue[] = (float) $row['revenue'];
    $chartCounts[]  = (int) $row['cnt'];
}

// ── Status Distribution for pie chart ────────────────────────────────
$pieSql = "SELECT status, COUNT(*) AS cnt FROM payments GROUP BY status ORDER BY FIELD(status, 'completed','pending','processing','refunded')";
$pieResult = $conn->query($pieSql);
$pieLabels = [];
$pieData   = [];
while ($row = $pieResult->fetch_assoc()) {
    $pieLabels[] = ucfirst($row['status']);
    $pieData[]   = (int) $row['cnt'];
}

// ── Badge map ─────────────────────────────────────────────────────────
$statusColors = [
    'completed'  => 'bg-emerald-50 text-emerald-600 border-emerald-200',
    'pending'    => 'bg-amber-50 text-amber-600 border-amber-200',
    'processing' => 'bg-blue-50 text-blue-600 border-blue-200',
    'refunded'   => 'bg-red-50 text-red-600 border-red-200',
];

// ── Base URL for pagination ──────────────────────────────────────────
$baseUrl = 'payments.php?';
if ($dateFrom !== '')  $baseUrl .= 'date_from=' . urlencode($dateFrom) . '&';
if ($dateTo !== '')    $baseUrl .= 'date_to=' . urlencode($dateTo) . '&';
if ($statusF !== '')   $baseUrl .= 'status=' . urlencode($statusF) . '&';
if ($clientQ !== '')   $baseUrl .= 'client=' . urlencode($clientQ) . '&';
if ($freelancerQ !== '') $baseUrl .= 'freelancer=' . urlencode($freelancerQ) . '&';
if ($amountMin !== '') $baseUrl .= 'amount_min=' . urlencode($amountMin) . '&';
if ($amountMax !== '') $baseUrl .= 'amount_max=' . urlencode($amountMax) . '&';
$baseUrl = rtrim($baseUrl, '?&');
if (strpos($baseUrl, '&') === false) {
    $baseUrl = rtrim($baseUrl, '?');
}

// Collect current filter values for the export hidden form
$filterParams = [
    'date_from'  => $dateFrom,
    'date_to'    => $dateTo,
    'status'     => $statusF,
    'client'     => $clientQ,
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
    ['key' => 'dashboard', 'label' => 'Dashboard', 'url' => 'dashboard.php', 'icon' => 'fa-th-large'],
    ['key' => 'users', 'label' => 'Users', 'url' => 'users.php', 'icon' => 'fa-users'],
    ['key' => 'jobs', 'label' => 'Jobs', 'url' => 'jobs.php', 'icon' => 'fa-briefcase'],
    ['key' => 'payments', 'label' => 'Payments', 'url' => 'payments.php', 'icon' => 'fa-credit-card'],
    ['key' => 'fraud', 'label' => 'Fraud', 'url' => 'fraud_detection.php', 'icon' => 'fa-shield-halved'],
    ['key' => 'matching', 'label' => 'AI Matching', 'url' => 'ai_matching.php', 'icon' => 'fa-brain'],
    ['key' => 'settings', 'label' => 'Settings', 'url' => 'settings.php', 'icon' => 'fa-cog'],
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
    .btn-grad{background:linear-gradient(135deg,#2563eb,#0ea5e9);transition:opacity .25s,transform .2s}
    .btn-grad:hover{opacity:.88;transform:translateY(-2px)}
    .stat-card{transition:transform .2s,box-shadow .2s}
    .stat-card:hover{transform:translateY(-3px);box-shadow:0 8px 30px rgba(0,0,0,.06)}
    .modal-overlay{opacity:0;pointer-events:none;transition:opacity .25s ease}
    .modal-overlay.active{opacity:1;pointer-events:auto}
    .modal-panel{transform:scale(.95) translateY(10px);transition:transform .25s ease}
    .modal-overlay.active .modal-panel{transform:scale(1) translateY(0)}
    </style>



                <?php display_flash('success') ?>
                <?php display_flash('error') ?>
                <?php display_flash('info') ?>

                <!-- ═══ STAT CARDS ═══════════════════════════════════════ -->
                <div class="grid grid-cols-1 sm:grid-cols-2 xl:grid-cols-4 2xl:grid-cols-4 gap-4">

                    <div class="stat-card bg-white rounded-2xl p-5 border border-gray-100 shadow-sm fade-in">
                        <div class="flex items-center justify-between mb-3">
                            <div class="w-11 h-11 rounded-xl bg-violet-50 flex items-center justify-center">
                                <i class="fas fa-dollar-sign text-violet-500"></i>
                            </div>
                            <span class="text-[10px] font-semibold text-gray-400 uppercase tracking-wider">Revenue</span>
                        </div>
                        <p class="text-2xl font-black text-gray-900"><?= format_currency($stats['total_revenue']) ?></p>
                        <p class="text-xs text-gray-400 mt-1">Total Revenue</p>
                    </div>

                    <div class="stat-card bg-white rounded-2xl p-5 border border-gray-100 shadow-sm fade-in" style="animation-delay:.05s">
                        <div class="flex items-center justify-between mb-3">
                            <div class="w-11 h-11 rounded-xl bg-emerald-50 flex items-center justify-center">
                                <i class="fas fa-check-circle text-emerald-500"></i>
                            </div>
                            <span class="text-[10px] font-semibold text-emerald-400 uppercase tracking-wider">Done</span>
                        </div>
                        <p class="text-2xl font-black text-gray-900"><?= number_format($stats['completed_count']) ?></p>
                        <p class="text-xs text-gray-400 mt-1">Completed Payments</p>
                    </div>

                    <div class="stat-card bg-white rounded-2xl p-5 border border-gray-100 shadow-sm fade-in" style="animation-delay:.1s">
                        <div class="flex items-center justify-between mb-3">
                            <div class="w-11 h-11 rounded-xl bg-amber-50 flex items-center justify-center">
                                <i class="fas fa-clock text-amber-500"></i>
                            </div>
                            <span class="text-[10px] font-semibold text-amber-400 uppercase tracking-wider">Pending</span>
                        </div>
                        <p class="text-2xl font-black text-gray-900"><?= number_format($stats['pending_count']) ?></p>
                        <p class="text-xs text-gray-400 mt-1">Pending Payments</p>
                    </div>

                    <div class="stat-card bg-white rounded-2xl p-5 border border-gray-100 shadow-sm fade-in" style="animation-delay:.15s">
                        <div class="flex items-center justify-between mb-3">
                            <div class="w-11 h-11 rounded-xl bg-red-50 flex items-center justify-center">
                                <i class="fas fa-undo text-red-500"></i>
                            </div>
                            <span class="text-[10px] font-semibold text-red-400 uppercase tracking-wider">Refunds</span>
                        </div>
                        <p class="text-2xl font-black text-gray-900"><?= number_format($stats['refunded_count']) ?></p>
                        <p class="text-xs text-gray-400 mt-1">Refunded Payments</p>
                    </div>

                    <div class="stat-card bg-white rounded-2xl p-5 border border-gray-100 shadow-sm fade-in" style="animation-delay:.2s">
                        <div class="flex items-center justify-between mb-3">
                            <div class="w-11 h-11 rounded-xl bg-blue-50 flex items-center justify-center">
                                <i class="fas fa-coins text-blue-500"></i>
                            </div>
                            <span class="text-[10px] font-semibold text-blue-400 uppercase tracking-wider">Fees</span>
                        </div>
                        <p class="text-2xl font-black text-gray-900"><?= format_currency($stats['fees_collected']) ?></p>
                        <p class="text-xs text-gray-400 mt-1">Platform Fees Collected</p>
                    </div>

                    <div class="stat-card bg-white rounded-2xl p-5 border border-gray-100 shadow-sm fade-in" style="animation-delay:.25s">
                        <div class="flex items-center justify-between mb-3">
                            <div class="w-11 h-11 rounded-xl bg-cyan-50 flex items-center justify-center">
                                <i class="fas fa-wallet text-cyan-500"></i>
                            </div>
                            <span class="text-[10px] font-semibold text-cyan-400 uppercase tracking-wider">Freelancers</span>
                        </div>
                        <p class="text-2xl font-black text-gray-900"><?= format_currency($stats['freelancer_earnings']) ?></p>
                        <p class="text-xs text-gray-400 mt-1">Freelancer Earnings</p>
                    </div>

                    <div class="stat-card bg-white rounded-2xl p-5 border border-gray-100 shadow-sm fade-in" style="animation-delay:.3s">
                        <div class="flex items-center justify-between mb-3">
                            <div class="w-11 h-11 rounded-xl bg-indigo-50 flex items-center justify-center">
                                <i class="fas fa-receipt text-indigo-500"></i>
                            </div>
                            <span class="text-[10px] font-semibold text-indigo-400 uppercase tracking-wider">Volume</span>
                        </div>
                        <p class="text-2xl font-black text-gray-900"><?= format_currency($stats['gross_volume']) ?></p>
                        <p class="text-xs text-gray-400 mt-1">Gross Volume</p>
                    </div>

                    <div class="stat-card bg-white rounded-2xl p-5 border border-gray-100 shadow-sm fade-in" style="animation-delay:.35s">
                        <div class="flex items-center justify-between mb-3">
                            <div class="w-11 h-11 rounded-xl bg-emerald-50 flex items-center justify-center">
                                <i class="fas fa-layer-group text-emerald-500"></i>
                            </div>
                            <span class="text-[10px] font-semibold text-emerald-400 uppercase tracking-wider">Total</span>
                        </div>
                        <p class="text-2xl font-black text-gray-900"><?= number_format($stats['total_payments']) ?></p>
                        <p class="text-xs text-gray-400 mt-1">Total Transactions</p>
                    </div>

                </div>

                <!-- ═══ CHARTS ════════════════════════════════════════════ -->
                <div class="grid grid-cols-1 2xl:grid-cols-3 gap-6 fade-in" style="animation-delay:.4s">

                    <!-- Monthly Revenue Bar Chart -->
                    <div class="2xl:col-span-2 bg-white rounded-2xl border border-gray-100 shadow-sm p-6">
                        <div class="flex items-center justify-between mb-6">
                            <h2 class="text-base font-bold text-gray-900">Monthly Revenue</h2>
                            <span class="text-[11px] text-gray-400">Last 12 months</span>
                        </div>
                        <div class="relative" style="height:280px">
                            <canvas id="revenueChart"></canvas>
                        </div>
                    </div>

                    <!-- Payment Status Pie Chart -->
                    <div class="bg-white rounded-2xl border border-gray-100 shadow-sm p-6">
                        <div class="flex items-center justify-between mb-6">
                            <h2 class="text-base font-bold text-gray-900">Status Distribution</h2>
                        </div>
                        <div class="relative flex items-center justify-center" style="height:280px">
                            <canvas id="statusPieChart"></canvas>
                        </div>
                    </div>

                </div>

                <!-- ═══ SEARCH & FILTERS ════════════════════════════════════ -->
                <div class="bg-white rounded-2xl border border-gray-100 shadow-sm p-5 fade-in" style="animation-delay:.45s">
                    <form method="GET" action="payments.php" class="space-y-4">
                        <div class="flex flex-col sm:flex-row gap-3">
                            <div class="flex-1 relative">
                                <i class="fas fa-user absolute left-3.5 top-1/2 -translate-y-1/2 text-gray-400 text-sm"></i>
                                <input type="text" name="client" value="<?= sanitize_string($clientQ) ?>" placeholder="Search client name or email..."
                                       class="w-full pl-10 pr-4 py-2.5 rounded-xl border border-gray-200 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500/30 focus:border-blue-400 transition-all">
                            </div>
                            <div class="flex-1 relative">
                                <i class="fas fa-laptop-code absolute left-3.5 top-1/2 -translate-y-1/2 text-gray-400 text-sm"></i>
                                <input type="text" name="freelancer" value="<?= sanitize_string($freelancerQ) ?>" placeholder="Search freelancer name or email..."
                                       class="w-full pl-10 pr-4 py-2.5 rounded-xl border border-gray-200 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500/30 focus:border-blue-400 transition-all">
                            </div>
                            <select name="status" class="px-4 py-2.5 rounded-xl border border-gray-200 text-sm text-gray-600 focus:outline-none focus:ring-2 focus:ring-blue-500/30 focus:border-blue-400 bg-white">
                                <option value="">All Statuses</option>
                                <option value="pending" <?= $statusF === 'pending' ? 'selected' : '' ?>>Pending</option>
                                <option value="processing" <?= $statusF === 'processing' ? 'selected' : '' ?>>Processing</option>
                                <option value="completed" <?= $statusF === 'completed' ? 'selected' : '' ?>>Completed</option>
                                <option value="refunded" <?= $statusF === 'refunded' ? 'selected' : '' ?>>Refunded</option>
                            </select>
                        </div>
                        <div class="flex flex-col sm:flex-row gap-3">
                            <div class="flex-1">
                                <label class="block text-[11px] font-semibold text-gray-400 uppercase tracking-wider mb-1">Date From</label>
                                <input type="date" name="date_from" value="<?= sanitize_string($dateFrom) ?>"
                                       class="w-full px-4 py-2.5 rounded-xl border border-gray-200 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500/30 focus:border-blue-400 transition-all">
                            </div>
                            <div class="flex-1">
                                <label class="block text-[11px] font-semibold text-gray-400 uppercase tracking-wider mb-1">Date To</label>
                                <input type="date" name="date_to" value="<?= sanitize_string($dateTo) ?>"
                                       class="w-full px-4 py-2.5 rounded-xl border border-gray-200 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500/30 focus:border-blue-400 transition-all">
                            </div>
                            <div class="flex-1">
                                <label class="block text-[11px] font-semibold text-gray-400 uppercase tracking-wider mb-1">Min Amount</label>
                                <input type="number" name="amount_min" value="<?= sanitize_string($amountMin) ?>" placeholder="0.00" step="0.01" min="0"
                                       class="w-full px-4 py-2.5 rounded-xl border border-gray-200 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500/30 focus:border-blue-400 transition-all">
                            </div>
                            <div class="flex-1">
                                <label class="block text-[11px] font-semibold text-gray-400 uppercase tracking-wider mb-1">Max Amount</label>
                                <input type="number" name="amount_max" value="<?= sanitize_string($amountMax) ?>" placeholder="0.00" step="0.01" min="0"
                                       class="w-full px-4 py-2.5 rounded-xl border border-gray-200 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500/30 focus:border-blue-400 transition-all">
                            </div>
                        </div>
                        <div class="flex flex-col sm:flex-row items-start sm:items-center gap-3 pt-1">
                            <button type="submit" class="btn-grad text-white px-6 py-2.5 rounded-xl text-sm font-semibold">
                                <i class="fas fa-filter mr-1.5"></i> Apply Filters
                            </button>
                            <?php if ($dateFrom || $dateTo || $statusF || $clientQ || $freelancerQ || $amountMin || $amountMax): ?>
                                <a href="payments.php" class="px-4 py-2.5 rounded-xl border border-gray-200 text-sm font-medium text-gray-500 hover:bg-gray-50 transition-colors text-center">
                                    <i class="fas fa-times mr-1"></i> Clear All
                                </a>
                            <?php endif; ?>
                            <div class="flex-1"></div>
                            <div class="flex items-center gap-2">
                                <button type="button" onclick="exportCSV()" class="inline-flex items-center gap-2 px-4 py-2.5 rounded-xl border border-gray-200 text-sm font-medium text-gray-600 hover:bg-gray-50 transition-colors">
                                    <i class="fas fa-file-excel text-emerald-500"></i> Export Excel
                                </button>
                                <button type="button" onclick="exportPDF()" class="inline-flex items-center gap-2 px-4 py-2.5 rounded-xl border border-gray-200 text-sm font-medium text-gray-600 hover:bg-gray-50 transition-colors">
                                    <i class="fas fa-file-invoice text-red-500"></i> Payment Report
                                </button>
                            </div>
                        </div>
                    </form>
                </div>

                <!-- ═══ PAYMENTS TABLE ══════════════════════════════════════ -->
                <div class="bg-white rounded-2xl border border-gray-100 shadow-sm overflow-hidden fade-in" style="animation-delay:.5s">
                    <?php if ($paymentsResult->num_rows > 0): ?>
                    <div class="overflow-x-auto">
                        <table class="w-full text-sm">
                            <thead>
                                <tr class="border-b border-gray-100 bg-gray-50/50">
                                    <th class="text-left py-4 px-6 text-xs font-semibold text-gray-400 uppercase tracking-wider">Payment</th>
                                    <th class="text-left py-4 px-6 text-xs font-semibold text-gray-400 uppercase tracking-wider">Milestone</th>
                                    <th class="text-left py-4 px-6 text-xs font-semibold text-gray-400 uppercase tracking-wider">Client</th>
                                    <th class="text-left py-4 px-6 text-xs font-semibold text-gray-400 uppercase tracking-wider">Freelancer</th>
                                    <th class="text-right py-4 px-6 text-xs font-semibold text-gray-400 uppercase tracking-wider">Total</th>
                                    <th class="text-right py-4 px-6 text-xs font-semibold text-gray-400 uppercase tracking-wider">Fee</th>
                                    <th class="text-right py-4 px-6 text-xs font-semibold text-gray-400 uppercase tracking-wider">Net</th>
                                    <th class="text-center py-4 px-6 text-xs font-semibold text-gray-400 uppercase tracking-wider">Status</th>
                                    <th class="text-right py-4 px-6 text-xs font-semibold text-gray-400 uppercase tracking-wider">Date</th>
                                    <th class="text-right py-4 px-6 text-xs font-semibold text-gray-400 uppercase tracking-wider">Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                            <?php while ($p = $paymentsResult->fetch_assoc()): ?>
                                <tr class="border-b border-gray-50 last:border-0 hover:bg-gray-50/50 transition-colors" data-payment-id="<?= (int) $p['id'] ?>">
                                    <td class="py-4 px-6">
                                        <span class="font-mono text-xs font-bold text-gray-700">#<?= (int) $p['id'] ?></span>
                                    </td>
                                    <td class="py-4 px-6">
                                        <span class="font-medium text-gray-900 line-clamp-1"><?= sanitize_string($p['milestone_title']) ?></span>
                                    </td>
                                    <td class="py-4 px-6">
                                        <p class="font-medium text-gray-900 truncate"><?= sanitize_string($p['client_name']) ?></p>
                                        <p class="text-[11px] text-gray-400 truncate"><?= sanitize_string($p['client_email']) ?></p>
                                    </td>
                                    <td class="py-4 px-6">
                                        <p class="font-medium text-gray-900 truncate"><?= sanitize_string($p['freelancer_name']) ?></p>
                                        <p class="text-[11px] text-gray-400 truncate"><?= sanitize_string($p['freelancer_email']) ?></p>
                                    </td>
                                    <td class="py-4 px-6 text-right font-bold text-gray-900"><?= format_currency((float) $p['total_amount']) ?></td>
                                    <td class="py-4 px-6 text-right text-amber-600 font-medium"><?= format_currency((float) $p['platform_fee']) ?></td>
                                    <td class="py-4 px-6 text-right text-emerald-600 font-medium"><?= format_currency((float) $p['freelancer_net']) ?></td>
                                    <td class="py-4 px-6 text-center">
                                        <span class="inline-block px-2.5 py-1 rounded-lg text-[11px] font-semibold border <?= $statusColors[$p['status']] ?? 'bg-gray-50 text-gray-600 border-gray-200' ?>">
                                            <?= sanitize_string(ucfirst($p['status'])) ?>
                                        </span>
                                    </td>
                                    <td class="py-4 px-6 text-right text-gray-400 text-xs"><?= time_ago($p['created_at']) ?></td>
                                    <td class="py-4 px-6">
                                        <div class="flex items-center justify-end gap-2">
                                            <!-- View Details -->
                                            <button type="button" onclick='openDetailModal(<?= json_encode([
                                                'id' => (int) $p['id'],
                                                'milestone' => $p['milestone_title'],
                                                'client_name' => $p['client_name'],
                                                'client_email' => $p['client_email'],
                                                'freelancer_name' => $p['freelancer_name'],
                                                'freelancer_email' => $p['freelancer_email'],
                                                'total_amount' => (float) $p['total_amount'],
                                                'platform_fee' => (float) $p['platform_fee'],
                                                'freelancer_net' => (float) $p['freelancer_net'],
                                                'status' => $p['status'],
                                                'created_at' => $p['created_at'],
                                            ]) ?>)'
                                                    class="w-8 h-8 rounded-lg bg-blue-50 text-blue-500 flex items-center justify-center hover:bg-blue-100 transition-colors" title="View Details">
                                                <i class="fas fa-eye text-xs"></i>
                                            </button>
                                            <?php if ($p['status'] === 'pending'): ?>
                                            <form method="POST" action="payment_action.php" class="inline" onsubmit="return confirm('Mark this payment as processing?')">
                                                <?= csrf_field() ?>
                                                <input type="hidden" name="action" value="processing">
                                                <input type="hidden" name="payment_id" value="<?= (int) $p['id'] ?>">
                                                <button type="submit" class="w-8 h-8 rounded-lg bg-blue-50 text-blue-500 flex items-center justify-center hover:bg-blue-100 transition-colors" title="Mark Processing">
                                                    <i class="fas fa-cog text-xs"></i>
                                                </button>
                                            </form>
                                            <?php endif; ?>
                                            <?php if ($p['status'] === 'processing'): ?>
                                            <form method="POST" action="payment_action.php" class="inline" onsubmit="return confirm('Mark this payment as completed?')">
                                                <?= csrf_field() ?>
                                                <input type="hidden" name="action" value="completed">
                                                <input type="hidden" name="payment_id" value="<?= (int) $p['id'] ?>">
                                                <button type="submit" class="w-8 h-8 rounded-lg bg-emerald-50 text-emerald-500 flex items-center justify-center hover:bg-emerald-100 transition-colors" title="Mark Completed">
                                                    <i class="fas fa-check text-xs"></i>
                                                </button>
                                            </form>
                                            <?php endif; ?>
                                            <?php if (in_array($p['status'], ['pending', 'processing'])): ?>
                                            <form method="POST" action="payment_action.php" class="inline" onsubmit="return confirm('Are you sure you want to REFUND this payment? This cannot be undone.')">
                                                <?= csrf_field() ?>
                                                <input type="hidden" name="action" value="refunded">
                                                <input type="hidden" name="payment_id" value="<?= (int) $p['id'] ?>">
                                                <button type="submit" class="w-8 h-8 rounded-lg bg-red-50 text-red-500 flex items-center justify-center hover:bg-red-100 transition-colors" title="Refund">
                                                    <i class="fas fa-undo text-xs"></i>
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
                        <div class="w-16 h-16 rounded-2xl bg-gray-100 flex items-center justify-center mx-auto mb-4">
                            <i class="fas fa-credit-card text-2xl text-gray-300"></i>
                        </div>
                        <p class="text-gray-500 text-sm mb-1">No payments found</p>
                        <p class="text-gray-400 text-xs">Try adjusting your search or filters</p>
                    </div>
                    <?php endif; ?>
                </div>

                <!-- ═══ PAGINATION ═══════════════════════════════════════════ -->
                <?php render_pagination($pagination, $baseUrl); ?>


    <!-- ═══ PAYMENT DETAIL MODAL ═══════════════════════════════════════ -->
    <div id="detailModal" class="modal-overlay fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/40" onclick="closeDetailModal(event)">
        <div class="modal-panel bg-white rounded-2xl shadow-2xl w-full max-w-lg overflow-hidden" onclick="event.stopPropagation()">
            <div class="flex items-center justify-between p-6 pb-0">
                <h3 class="text-lg font-bold text-gray-900">Payment Details</h3>
                <button onclick="closeDetailModal()" class="w-8 h-8 rounded-lg bg-gray-100 flex items-center justify-center text-gray-400 hover:text-gray-600 transition-colors">
                    <i class="fas fa-times text-sm"></i>
                </button>
            </div>
            <div class="p-6">
                <div class="flex items-center justify-between mb-6">
                    <div>
                        <p class="text-xs text-gray-400 uppercase tracking-wider font-semibold">Payment</p>
                        <p class="text-xl font-black text-gray-900" id="modalPaymentId">#--</p>
                    </div>
                    <span id="modalStatusBadge" class="inline-block px-3 py-1.5 rounded-lg text-xs font-semibold border"></span>
                </div>

                <div class="grid grid-cols-2 gap-4 mb-6">
                    <div class="bg-gray-50 rounded-xl p-4">
                        <p class="text-[11px] text-gray-400 uppercase tracking-wider font-semibold mb-1">Milestone</p>
                        <p class="text-sm font-semibold text-gray-900" id="modalMilestone">--</p>
                    </div>
                    <div class="bg-gray-50 rounded-xl p-4">
                        <p class="text-[11px] text-gray-400 uppercase tracking-wider font-semibold mb-1">Date</p>
                        <p class="text-sm font-semibold text-gray-900" id="modalDate">--</p>
                    </div>
                </div>

                <div class="grid grid-cols-2 gap-4 mb-6">
                    <div class="bg-blue-50 rounded-xl p-4">
                        <p class="text-[11px] text-blue-400 uppercase tracking-wider font-semibold mb-1">Client</p>
                        <p class="text-sm font-semibold text-gray-900" id="modalClientName">--</p>
                        <p class="text-[11px] text-gray-400" id="modalClientEmail">--</p>
                    </div>
                    <div class="bg-emerald-50 rounded-xl p-4">
                        <p class="text-[11px] text-emerald-400 uppercase tracking-wider font-semibold mb-1">Freelancer</p>
                        <p class="text-sm font-semibold text-gray-900" id="modalFreelancerName">--</p>
                        <p class="text-[11px] text-gray-400" id="modalFreelancerEmail">--</p>
                    </div>
                </div>

                <div class="grid grid-cols-3 gap-4">
                    <div class="bg-gray-50 rounded-xl p-4 text-center">
                        <p class="text-[11px] text-gray-400 uppercase tracking-wider font-semibold mb-1">Total Amount</p>
                        <p class="text-lg font-black text-gray-900" id="modalTotal">$0.00</p>
                    </div>
                    <div class="bg-amber-50 rounded-xl p-4 text-center">
                        <p class="text-[11px] text-amber-400 uppercase tracking-wider font-semibold mb-1">Platform Fee</p>
                        <p class="text-lg font-black text-amber-600" id="modalFee">$0.00</p>
                    </div>
                    <div class="bg-emerald-50 rounded-xl p-4 text-center">
                        <p class="text-[11px] text-emerald-400 uppercase tracking-wider font-semibold mb-1">Freelancer Net</p>
                        <p class="text-lg font-black text-emerald-600" id="modalNet">$0.00</p>
                    </div>
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
        // ── Detail Modal ────────────────────────────────────────────────
        const statusBadgeColors = {
            completed: 'bg-emerald-50 text-emerald-600 border-emerald-200',
            pending: 'bg-amber-50 text-amber-600 border-amber-200',
            processing: 'bg-blue-50 text-blue-600 border-blue-200',
            refunded: 'bg-red-50 text-red-600 border-red-200'
        };

        function openDetailModal(data) {
            document.getElementById('modalPaymentId').textContent = '#' + data.id;
            document.getElementById('modalMilestone').textContent = data.milestone;
            document.getElementById('modalDate').textContent = new Date(data.created_at).toLocaleDateString('en-US', {
                year: 'numeric',
                month: 'long',
                day: 'numeric',
                hour: '2-digit',
                minute: '2-digit'
            });
            document.getElementById('modalClientName').textContent = data.client_name;
            document.getElementById('modalClientEmail').textContent = data.client_email;
            document.getElementById('modalFreelancerName').textContent = data.freelancer_name;
            document.getElementById('modalFreelancerEmail').textContent = data.freelancer_email;
            document.getElementById('modalTotal').textContent = '$' + data.total_amount.toFixed(2);
            document.getElementById('modalFee').textContent = '$' + data.platform_fee.toFixed(2);
            document.getElementById('modalNet').textContent = '$' + data.freelancer_net.toFixed(2);

            const badge = document.getElementById('modalStatusBadge');
            badge.textContent = data.status.charAt(0).toUpperCase() + data.status.slice(1);
            badge.className = 'inline-block px-3 py-1.5 rounded-lg text-xs font-semibold border ' + (statusBadgeColors[data.status] || 'bg-gray-50 text-gray-600 border-gray-200');

            document.getElementById('detailModal').classList.add('active');
            document.body.style.overflow = 'hidden';
        }

        function closeDetailModal(e) {
            if (e && e.target !== e.currentTarget) return;
            document.getElementById('detailModal').classList.remove('active');
            document.body.style.overflow = '';
        }

        document.addEventListener('keydown', function(e) {
            if (e.key === 'Escape') closeDetailModal();
        });

        // ── Export Functions ─────────────────────────────────────────────
        function exportCSV() {
            document.getElementById('exportCSVForm').submit();
        }

        function exportPDF() {
            document.getElementById('exportPDFForm').submit();
        }

        // ── Charts ──────────────────────────────────────────────────────
        const chartLabels = <?= json_encode($chartLabels) ?>;
        const chartRevenue = <?= json_encode($chartRevenue) ?>;
        const pieLabels = <?= json_encode($pieLabels) ?>;
        const pieData = <?= json_encode($pieData) ?>;

        // Monthly Revenue Bar Chart
        if (chartLabels.length > 0) {
            const ctxBar = document.getElementById('revenueChart').getContext('2d');
            new Chart(ctxBar, {
                type: 'bar',
                data: {
                    labels: chartLabels,
                    datasets: [{
                        label: 'Platform Revenue',
                        data: chartRevenue,
                        backgroundColor: 'rgba(37, 99, 235, 0.8)',
                        borderColor: 'rgba(37, 99, 235, 1)',
                        borderWidth: 1,
                        borderRadius: 6,
                        borderSkipped: false,
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: {
                            display: false
                        },
                        tooltip: {
                            backgroundColor: '#1e293b',
                            titleFont: {
                                family: 'Inter'
                            },
                            bodyFont: {
                                family: 'Inter'
                            },
                            padding: 12,
                            cornerRadius: 8,
                            callbacks: {
                                label: function(context) {
                                    return '$' + context.parsed.y.toLocaleString(undefined, {
                                        minimumFractionDigits: 2,
                                        maximumFractionDigits: 2
                                    });
                                }
                            }
                        }
                    },
                    scales: {
                        x: {
                            grid: {
                                display: false
                            },
                            ticks: {
                                font: {
                                    family: 'Inter',
                                    size: 11
                                },
                                color: '#94a3b8'
                            }
                        },
                        y: {
                            grid: {
                                color: '#f1f5f9'
                            },
                            ticks: {
                                font: {
                                    family: 'Inter',
                                    size: 11
                                },
                                color: '#94a3b8',
                                callback: function(value) {
                                    return '$' + value.toLocaleString();
                                }
                            },
                            beginAtZero: true
                        }
                    }
                }
            });
        }

        // Status Pie Chart
        if (pieLabels.length > 0) {
            const ctxPie = document.getElementById('statusPieChart').getContext('2d');
            const pieColors = {
                'Completed': '#10b981',
                'Pending': '#f59e0b',
                'Processing': '#3b82f6',
                'Refunded': '#ef4444'
            };
            new Chart(ctxPie, {
                type: 'doughnut',
                data: {
                    labels: pieLabels,
                    datasets: [{
                        data: pieData,
                        backgroundColor: pieLabels.map(l => pieColors[l] || '#94a3b8'),
                        borderWidth: 0,
                        spacing: 2
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    cutout: '65%',
                    plugins: {
                        legend: {
                            position: 'bottom',
                            labels: {
                                font: {
                                    family: 'Inter',
                                    size: 12
                                },
                                padding: 16,
                                usePointStyle: true,
                                pointStyleWidth: 10
                            }
                        },
                        tooltip: {
                            backgroundColor: '#1e293b',
                            titleFont: {
                                family: 'Inter'
                            },
                            bodyFont: {
                                family: 'Inter'
                            },
                            padding: 12,
                            cornerRadius: 8
                        }
                    }
                }
            });
        }
    </script>
<?php require_once __DIR__ . '/../components/layout_end.php'; ?>
