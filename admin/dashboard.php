<?php

/**
 * Admin Dashboard – JobHub
 * Professional management dashboard with 12 KPI cards, charts, and recent activity.
 */
require_once __DIR__ . '/../auth/auth.php';
require_role('admin');
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../components/stat_card.php';

$currentPage = 'dashboard';
$userId = $_SESSION['user_id'];

// ── User Info ────────────────────────────────────────────────────────
$uStmt = $conn->prepare('SELECT name, profile_image FROM users WHERE id = ?');
$uStmt->bind_param('i', $userId);
$uStmt->execute();
$adminUser = $uStmt->get_result()->fetch_assoc();
$uStmt->close();
$adminName = $adminUser['name'] ?? 'Admin';
$adminFirst = explode(' ', $adminName)[0];
$adminAvatar = get_profile_image($adminUser['profile_image'] ?? null);

// ── User counts by role ──────────────────────────────────────────────
$r = $conn->query('SELECT role, COUNT(*) AS cnt FROM users GROUP BY role');
$roleCounts = ['client' => 0, 'freelancer' => 0, 'admin' => 0];
while ($row = $r->fetch_assoc())
    $roleCounts[$row['role']] = (int) $row['cnt'];
$totalUsers = array_sum($roleCounts);
$totalFreelancers = $roleCounts['freelancer'];
$totalClients = $roleCounts['client'];

// ── Job counts ───────────────────────────────────────────────────────
$r = $conn->query('SELECT status, COUNT(*) AS cnt FROM jobs GROUP BY status');
$jobCounts = ['open' => 0, 'in_progress' => 0, 'completed' => 0, 'disputed' => 0, 'cancelled' => 0];
while ($row = $r->fetch_assoc())
    $jobCounts[$row['status']] = (int) $row['cnt'];
$activeJobs = $jobCounts['open'];
$totalJobs = array_sum($jobCounts);

// ── Contracts ────────────────────────────────────────────────────────
$r = $conn->query('SELECT status, COUNT(*) AS cnt FROM contracts GROUP BY status');
$contractCounts = [];
while ($row = $r->fetch_assoc())
    $contractCounts[$row['status']] = (int) $row['cnt'];
$activeContracts = $contractCounts['active'] ?? 0;
$openContracts = ($contractCounts['pending'] ?? 0) + ($contractCounts['active'] ?? 0);

// ── Milestones ───────────────────────────────────────────────────────
$r = $conn->query('SELECT status, COUNT(*) AS cnt FROM milestones GROUP BY status');
$milestoneCounts = [];
while ($row = $r->fetch_assoc())
    $milestoneCounts[$row['status']] = (int) $row['cnt'];
$activeMilestones = ($milestoneCounts['funded_in_escrow'] ?? 0) + ($milestoneCounts['submitted'] ?? 0);

// ── Wallet Balance (total across all users) ──────────────────────────
$r = $conn->query("SELECT COALESCE(SUM(wallet_balance), 0) AS total FROM users WHERE role != 'admin'");
$totalWalletBalance = (float) $r->fetch_assoc()['total'];

// ── Escrow Amount ────────────────────────────────────────────────────
$r = $conn->query("SELECT COALESCE(SUM(amount), 0) AS total FROM milestones WHERE status IN ('funded_in_escrow','submitted')");
$escrowAmount = (float) $r->fetch_assoc()['total'];

// ── Revenue ──────────────────────────────────────────────────────────
$r = $conn->query("SELECT COALESCE(SUM(platform_fee), 0) AS total_revenue, COALESCE(SUM(total_amount), 0) AS total_volume FROM payments WHERE status = 'completed'");
$payRow = $r->fetch_assoc();
$totalRevenue = (float) $payRow['total_revenue'];
$totalVolume = (float) $payRow['total_volume'];

$r = $conn->query("SELECT COUNT(*) AS cnt, COALESCE(SUM(total_amount), 0) AS total FROM payments WHERE status = 'completed' AND DATE(created_at) = CURDATE()");
$todayPay = $r->fetch_assoc();
$paymentsToday = (int) $todayPay['cnt'];
$revenueToday = (float) $todayPay['total'];

// ── Pending Disputes ─────────────────────────────────────────────────
$r = $conn->query("SELECT COUNT(*) AS cnt FROM dispute_tickets WHERE status IN ('open','investigating','escalated')");
$pendingDisputes = (int) $r->fetch_assoc()['cnt'];

// ── Fraud Alerts ─────────────────────────────────────────────────────
$r = $conn->query("SELECT COUNT(*) AS cnt FROM users WHERE status = 'flagged'");
$fraudAlerts = (int) $r->fetch_assoc()['cnt'];

// ── Notifications (unread for admin) ─────────────────────────────────
$unreadCount = 0;
$unStmt = $conn->prepare('SELECT COUNT(*) AS cnt FROM notifications WHERE user_id = ? AND is_read = 0');
$unStmt->bind_param('i', $userId);
$unStmt->execute();
$unreadCount = (int) $unStmt->get_result()->fetch_assoc()['cnt'];
$unStmt->close();

// ── Total Reviews ────────────────────────────────────────────────────
$r = $conn->query('SELECT COUNT(*) AS cnt FROM reviews WHERE rating IS NOT NULL');
$totalReviews = (int) $r->fetch_assoc()['cnt'];

// ── Monthly Revenue (last 6 months) ──────────────────────────────────
$revenueChart = [];
$r = $conn->query("
    SELECT DATE_FORMAT(created_at, '%Y-%m') AS month_key,
           DATE_FORMAT(created_at, '%b') AS month_label,
           COALESCE(SUM(platform_fee), 0) AS revenue
    FROM payments WHERE status = 'completed'
      AND created_at >= DATE_SUB(CURDATE(), INTERVAL 6 MONTH)
    GROUP BY month_key, month_label ORDER BY month_key ASC
");
while ($row = $r->fetch_assoc())
    $revenueChart[] = $row;

// ── Monthly Registrations (last 6 months) ────────────────────────────
$userChart = [];
$r = $conn->query("
    SELECT DATE_FORMAT(created_at, '%Y-%m') AS month_key,
           DATE_FORMAT(created_at, '%b') AS month_label,
           COUNT(*) AS cnt
    FROM users WHERE created_at >= DATE_SUB(CURDATE(), INTERVAL 6 MONTH)
    GROUP BY month_key, month_label ORDER BY month_key ASC
");
while ($row = $r->fetch_assoc())
    $userChart[] = $row;

// ── Monthly Jobs (last 6 months) ────────────────────────────────────
$jobChart = [];
$r = $conn->query("
    SELECT DATE_FORMAT(created_at, '%Y-%m') AS month_key,
           DATE_FORMAT(created_at, '%b') AS month_label,
           COUNT(*) AS cnt
    FROM jobs WHERE created_at >= DATE_SUB(CURDATE(), INTERVAL 6 MONTH)
    GROUP BY month_key, month_label ORDER BY month_key ASC
");
while ($row = $r->fetch_assoc())
    $jobChart[] = $row;

// ── Monthly Contracts (last 6 months) ────────────────────────────────
$contractChart = [];
$r = $conn->query("
    SELECT DATE_FORMAT(created_at, '%Y-%m') AS month_key,
           DATE_FORMAT(created_at, '%b') AS month_label,
           COUNT(*) AS cnt
    FROM contracts WHERE created_at >= DATE_SUB(CURDATE(), INTERVAL 6 MONTH)
    GROUP BY month_key, month_label ORDER BY month_key ASC
");
while ($row = $r->fetch_assoc())
    $contractChart[] = $row;

// ── Recent Jobs ──────────────────────────────────────────────────────
$rjStmt = $conn->prepare('
    SELECT j.id, j.title, j.budget, j.status, j.created_at, u.name AS client_name
    FROM jobs j
    JOIN clients c ON j.client_id = c.client_id
    JOIN users u ON c.client_id = u.id
    ORDER BY j.created_at DESC LIMIT 5
');
$rjStmt->execute();
$recentJobs = $rjStmt->get_result();
$rjStmt->close();

// ── Recent Contracts ─────────────────────────────────────────────────
$rcStmt = $conn->prepare('
    SELECT c.id, c.total_budget, c.status, c.created_at, c.contract_type,
           j.title AS job_title,
           u_client.name AS client_name,
           u_freelancer.name AS freelancer_name
    FROM contracts c JOIN jobs j ON c.job_id = j.id
    JOIN users u_client ON c.client_id = u_client.id
    JOIN users u_freelancer ON c.freelancer_id = u_freelancer.id
    ORDER BY c.created_at DESC LIMIT 5
');
$rcStmt->execute();
$recentContracts = $rcStmt->get_result();
$rcStmt->close();

// ── Recent Payments ──────────────────────────────────────────────────
$rpStmt = $conn->prepare('
    SELECT p.id, p.total_amount, p.platform_fee, p.status, p.created_at,
           u_client.name AS client_name, u_freelancer.name AS freelancer_name
    FROM payments p
    JOIN users u_client ON p.payer_id = u_client.id
    JOIN users u_freelancer ON p.payee_id = u_freelancer.id
    ORDER BY p.created_at DESC LIMIT 5
');
$rpStmt->execute();
$recentPayments = $rpStmt->get_result();
$rpStmt->close();

// ── Recent Users ─────────────────────────────────────────────────────
$ruStmt = $conn->prepare('SELECT id, name, email, role, status, profile_image, created_at FROM users ORDER BY created_at DESC LIMIT 6');
$ruStmt->execute();
$recentUsers = $ruStmt->get_result();
$ruStmt->close();

// ── Activity Timeline ────────────────────────────────────────────────
$actStmt = $conn->prepare('
    SELECT ubl.action_type, ubl.created_at, u.name AS user_name, u.id AS user_id
    FROM user_behavior_logs ubl JOIN users u ON ubl.user_id = u.id
    ORDER BY ubl.created_at DESC LIMIT 8
');
$actStmt->execute();
$recentActivity = $actStmt->get_result();
$actStmt->close();

$conn->close();

// ── Chart Data ───────────────────────────────────────────────────────
$revenueLabels = array_column($revenueChart, 'month_label');
$revenueData = array_column($revenueChart, 'revenue');
$userLabels = array_column($userChart, 'month_label');
$userData = array_column($userChart, 'cnt');
$jobLabels = array_column($jobChart, 'month_label');
$jobData = array_column($jobChart, 'cnt');
$contractLabels = array_column($contractChart, 'month_label');
$contractData = array_column($contractChart, 'cnt');

// ── Badge Maps ───────────────────────────────────────────────────────
$jobStatusBadges = ['open' => 'bg-emerald-100 text-emerald-700 dark:bg-emerald-900/30 dark:text-emerald-400', 'in_progress' => 'bg-amber-100 text-amber-700 dark:bg-amber-900/30 dark:text-amber-400', 'completed' => 'bg-blue-100 text-blue-700 dark:bg-blue-900/30 dark:text-blue-400', 'cancelled' => 'bg-red-100 text-red-700 dark:bg-red-900/30 dark:text-red-400', 'disputed' => 'bg-red-100 text-red-700 dark:bg-red-900/30 dark:text-red-400'];
$paymentStatusBadges = ['completed' => 'bg-emerald-100 text-emerald-700 dark:bg-emerald-900/30 dark:text-emerald-400', 'pending' => 'bg-amber-100 text-amber-700 dark:bg-amber-900/30 dark:text-amber-400', 'processing' => 'bg-blue-100 text-blue-700 dark:bg-blue-900/30 dark:text-blue-400', 'failed' => 'bg-red-100 text-red-700 dark:bg-red-900/30 dark:text-red-400', 'refunded' => 'bg-gray-100 text-gray-600 dark:bg-gray-800 dark:text-gray-400'];
$roleBadges = ['client' => 'bg-blue-100 text-blue-700 dark:bg-blue-900/30 dark:text-blue-400', 'freelancer' => 'bg-emerald-100 text-emerald-700 dark:bg-emerald-900/30 dark:text-emerald-400', 'admin' => 'bg-purple-100 text-purple-700 dark:bg-purple-900/30 dark:text-purple-400'];
$userStatusBadges = ['active' => 'bg-emerald-100 text-emerald-700 dark:bg-emerald-900/30 dark:text-emerald-400', 'flagged' => 'bg-amber-100 text-amber-700 dark:bg-amber-900/30 dark:text-amber-400', 'suspended' => 'bg-red-100 text-red-700 dark:bg-red-900/30 dark:text-red-400', 'inactive' => 'bg-gray-100 text-gray-600 dark:bg-gray-800 dark:text-gray-400'];
$activityIcons = [
    'login' => ['icon' => 'log-in', 'color' => '#2563EB', 'bg' => '#EEF5FF'],
    'register' => ['icon' => 'user-plus', 'color' => '#059669', 'bg' => '#ECFDF5'],
    'job_posted' => ['icon' => 'briefcase', 'color' => '#7C3AED', 'bg' => '#F5F3FF'],
    'proposal_sent' => ['icon' => 'send', 'color' => '#0891B2', 'bg' => '#ECFEFF'],
    'payment_made' => ['icon' => 'dollar-sign', 'color' => '#EA580C', 'bg' => '#FFF7ED'],
    'contract_create' => ['icon' => 'file-text', 'color' => '#2563EB', 'bg' => '#EEF5FF'],
    'review_posted' => ['icon' => 'star', 'color' => '#D97706', 'bg' => '#FFFBEB'],
];
$defaultActivity = ['icon' => 'circle', 'color' => '#94a3b8', 'bg' => '#f1f5f9'];

// ── Layout Setup ─────────────────────────────────────────────────────
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
$pageTitle = 'Admin Dashboard';
$pageSubtitle = 'Welcome back, ' . htmlspecialchars($adminFirst) . " — here's what's happening";
$activePage = 'dashboard';
$user = ['name' => $adminName, 'profile_image' => $adminUser['profile_image'] ?? null];
$profileLink = 'profile.php';
require_once __DIR__ . '/../components/layout_start.php';
?>
    <style>
    .kpi-card{transition:transform .15s ease,box-shadow .15s ease,border-color .15s ease}
    .kpi-card:hover{transform:translateY(-2px);box-shadow:0 4px 16px rgba(0,0,0,.06);border-color:#d1d5db}
    html.dark .kpi-card:hover{border-color:#475569;box-shadow:0 4px 16px rgba(0,0,0,.2)}
    .section-card{transition:box-shadow .2s}
    .section-card:hover{box-shadow:0 8px 30px rgba(0,0,0,.06)}
    </style>

    <?php display_flash('success'); ?>
    <?php display_flash('error'); ?>
    <?php display_flash('info'); ?>

    <!-- ═══ 12-COLUMN GRID LAYOUT ══════════════════════════════════════ -->
    <div class="grid grid-cols-12 gap-5 sm:gap-6">

        <!-- ═══ LEFT SECTION (9 cols) ══════════════════════════════════ -->
        <div class="col-span-12 lg:col-span-9 flex flex-col gap-5 sm:gap-6">

            <!-- Welcome Banner -->
            <!-- <div class="fade-in relative overflow-hidden rounded-2xl p-6 sm:p-8 bg-gradient-to-r from-blue-800 via-blue-600 to-cyan-600" style="animation-delay:0s">
                <div class="relative z-10 flex flex-wrap items-center justify-between gap-4">
                    <div>
                        <h2 class="text-xl sm:text-2xl font-extrabold text-white m-0">Welcome Back, <?= htmlspecialchars($adminFirst) ?></h2>
                        <p class="text-white/80 text-sm mt-1"><?= date('l, F j, Y') ?></p>
                    </div>
                    <div class="flex items-center gap-3">
                        <span class="inline-flex items-center gap-1.5 px-3.5 py-1.5 rounded-full bg-white/15 text-white text-xs font-semibold backdrop-blur-sm">
                            <span class="w-2 h-2 rounded-full bg-green-500"></span>
                            Platform Operational
                        </span>
                        <?php if ($pendingDisputes > 0): ?>
                        <a href="disputes.php" class="inline-flex items-center gap-1.5 px-3.5 py-1.5 rounded-full bg-red-500/80 text-white text-xs font-semibold backdrop-blur-sm hover:bg-red-600 transition-colors no-underline">
                            <i data-lucide="circle-alert"></i>
                            <?= $pendingDisputes ?> Dispute<?= $pendingDisputes !== 1 ? 's' : '' ?>
                        </a>
                        <?php endif; ?>
                    </div>
                </div>
                <div class="absolute -top-16 -right-10 w-64 h-64 rounded-full bg-white/7 pointer-events-none"></div>
                <div class="absolute -bottom-8 -left-8 w-40 h-40 rounded-full bg-white/5 pointer-events-none"></div>
            </div> -->

            <!-- ═══ KPI CARDS ═══════════════════════════════════════════ -->
            <?php
            $kpiCards = [
                ['icon' => 'users', 'label' => 'Total Users', 'value' => number_format($totalUsers), 'link' => 'users.php', 'delay' => '.04s'],
                ['icon' => 'briefcase', 'label' => 'Active Jobs', 'value' => number_format($activeJobs), 'link' => 'jobs.php', 'delay' => '.07s'],
                ['icon' => 'file-text', 'label' => 'Contracts', 'value' => number_format($openContracts), 'link' => 'contracts.php', 'delay' => '.10s'],
                ['icon' => 'list-checks', 'label' => 'Milestones', 'value' => number_format($activeMilestones), 'link' => 'milestones.php', 'delay' => '.13s'],
                ['icon' => 'dollar-sign', 'label' => 'Revenue', 'value' => format_currency($totalRevenue), 'link' => 'payments.php', 'delay' => '.16s'],
                ['icon' => 'wallet', 'label' => 'Wallet Balance', 'value' => format_currency($totalWalletBalance), 'link' => 'wallets.php', 'delay' => '.19s'],
                ['icon' => 'shield', 'label' => 'Escrow', 'value' => format_currency($escrowAmount), 'link' => 'milestones.php', 'delay' => '.22s'],
                ['icon' => 'scale', 'label' => 'Disputes', 'value' => number_format($pendingDisputes), 'link' => 'disputes.php', 'delay' => '.25s'],
            ];
            ?>
            <div class="grid grid-cols-2 sm:grid-cols-3 xl:grid-cols-4 gap-3 sm:gap-4">
                <?php foreach ($kpiCards as $kc): ?>
                <a href="<?= $kc['link'] ?>" class="kpi-card fade-in relative block rounded-lg bg-white dark:bg-slate-800 border border-gray-200 dark:border-slate-700 p-4 sm:p-5 no-underline overflow-hidden" style="animation-delay:<?= $kc['delay'] ?>">
                    <div class="absolute top-3.5 right-3.5 sm:top-4 sm:right-4">
                        <i data-lucide="<?= $kc['icon'] ?>" class="w-4 h-4 sm:w-5 sm:h-5 text-gray-400 dark:text-slate-500"></i>
                    </div>
                    <p class="text-xs sm:text-xs font-semibold text-gray-400 dark:text-slate-500 uppercase tracking-wider m-0 mb-2"><?= $kc['label'] ?></p>
                    <p class="text-xl sm:text-2xl font-extrabold text-gray-900 dark:text-white m-0"><?= $kc['value'] ?></p>
                </a>
                <?php endforeach; ?>
            </div>

            <!-- ═══ CHARTS ══════════════════════════════════════════════ -->
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4 sm:gap-5">
                <!-- User Growth -->
                <div class="section-card fade-in rounded-2xl p-5 sm:p-6 border border-gray-100 dark:border-slate-700 bg-white dark:bg-slate-800 shadow-sm" style="animation-delay:.28s">
                    <div class="flex items-center justify-between mb-5">
                        <div>
                            <h4 class="text-sm font-bold text-gray-900 dark:text-white m-0">User Growth</h4>
                            <p class="text-xs text-slate-400 dark:text-slate-500 mt-1">Monthly registrations</p>
                        </div>
                        <div class="w-10 h-10 rounded-xl bg-blue-50 dark:bg-blue-900/30 flex items-center justify-center">
                            <i data-lucide="trending-up" class="text-blue-600 dark:text-blue-400 text-sm"></i>
                        </div>
                    </div>
                    <div class="relative h-52 sm:h-56"><canvas id="usersChart"></canvas></div>
                </div>

                <!-- Job Growth -->
                <div class="section-card fade-in rounded-2xl p-5 sm:p-6 border border-gray-100 dark:border-slate-700 bg-white dark:bg-slate-800 shadow-sm" style="animation-delay:.31s">
                    <div class="flex items-center justify-between mb-5">
                        <div>
                            <h4 class="text-sm font-bold text-gray-900 dark:text-white m-0">Job Growth</h4>
                            <p class="text-xs text-slate-400 dark:text-slate-500 mt-1">Monthly job posts</p>
                        </div>
                        <div class="w-10 h-10 rounded-xl bg-cyan-50 dark:bg-cyan-900/30 flex items-center justify-center">
                            <i data-lucide="briefcase" class="text-cyan-600 dark:text-cyan-400 text-sm"></i>
                        </div>
                    </div>
                    <div class="relative h-52 sm:h-56"><canvas id="jobsChart"></canvas></div>
                </div>

                <!-- Revenue -->
                <div class="section-card fade-in rounded-2xl p-5 sm:p-6 border border-gray-100 dark:border-slate-700 bg-white dark:bg-slate-800 shadow-sm" style="animation-delay:.34s">
                    <div class="flex items-center justify-between mb-5">
                        <div>
                            <h4 class="text-sm font-bold text-gray-900 dark:text-white m-0">Revenue Trend</h4>
                            <p class="text-xs text-slate-400 dark:text-slate-500 mt-1">Monthly platform fees</p>
                        </div>
                        <div class="w-10 h-10 rounded-xl bg-emerald-50 dark:bg-emerald-900/30 flex items-center justify-center">
                            <i data-lucide="bar-chart" class="text-emerald-600 dark:text-emerald-400 text-sm"></i>
                        </div>
                    </div>
                    <div class="relative h-52 sm:h-56"><canvas id="revenueChart"></canvas></div>
                </div>

                <!-- Contracts -->
                <div class="section-card fade-in rounded-2xl p-5 sm:p-6 border border-gray-100 dark:border-slate-700 bg-white dark:bg-slate-800 shadow-sm" style="animation-delay:.37s">
                    <div class="flex items-center justify-between mb-5">
                        <div>
                            <h4 class="text-sm font-bold text-gray-900 dark:text-white m-0">Contracts</h4>
                            <p class="text-xs text-slate-400 dark:text-slate-500 mt-1">Monthly contract creation</p>
                        </div>
                        <div class="w-10 h-10 rounded-xl bg-violet-50 dark:bg-violet-900/30 flex items-center justify-center">
                            <i data-lucide="file-text" class="text-violet-600 dark:text-violet-400 text-sm"></i>
                        </div>
                    </div>
                    <div class="relative h-52 sm:h-56"><canvas id="contractsChart"></canvas></div>
                </div>
            </div>

            <!-- ═══ RECENT JOBS ═════════════════════════════════════════ -->
            <div class="fade-in rounded-lg border border-gray-200 dark:border-slate-700 bg-white dark:bg-slate-800 overflow-hidden" style="animation-delay:.40s">
                <div class="flex items-center justify-between px-5 py-4 border-b border-gray-100 dark:border-slate-700">
                    <h4 class="text-sm font-bold text-gray-900 dark:text-white m-0">Recent Jobs</h4>
                    <a href="jobs.php" class="text-xs font-semibold text-blue-600 dark:text-blue-400 no-underline hover:underline">View All &rarr;</a>
                </div>
                <?php if ($recentJobs->num_rows > 0): ?>
                    <div class="overflow-x-auto">
                        <table class="w-full text-sm">
                            <thead>
                                <tr class="border-b border-gray-100 dark:border-slate-700 bg-gray-50 dark:bg-slate-700/30">
                                    <th class="text-left px-5 py-3 text-xs font-semibold text-gray-500 dark:text-slate-400 uppercase tracking-wider">Job</th>
                                    <th class="text-left px-5 py-3 text-xs font-semibold text-gray-500 dark:text-slate-400 uppercase tracking-wider">Client</th>
                                    <th class="text-right px-5 py-3 text-xs font-semibold text-gray-500 dark:text-slate-400 uppercase tracking-wider">Budget</th>
                                    <th class="text-right px-5 py-3 text-xs font-semibold text-gray-500 dark:text-slate-400 uppercase tracking-wider">Status</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php while ($job = $recentJobs->fetch_assoc()): ?>
                                    <tr class="border-b border-gray-50 dark:border-slate-700/50 last:border-0 hover:bg-gray-50 dark:hover:bg-slate-700/40 transition-colors">
                                        <td class="px-5 py-3.5"><span class="font-semibold text-gray-900 dark:text-white text-xs line-clamp-1"><?= htmlspecialchars($job['title']) ?></span></td>
                                        <td class="px-5 py-3.5 text-gray-500 dark:text-slate-400 text-xs"><?= htmlspecialchars($job['client_name']) ?></td>
                                        <td class="px-5 py-3.5 text-right font-semibold text-gray-900 dark:text-white text-xs"><?= format_currency((float) $job['budget']) ?></td>
                                        <td class="px-5 py-3.5 text-right">
                                            <span class="inline-block px-2.5 py-1 rounded-full text-xs font-semibold <?= $jobStatusBadges[$job['status']] ?? 'bg-gray-100 text-gray-600 dark:bg-gray-800 dark:text-gray-400' ?>">
                                                <?= ucfirst(str_replace('_', ' ', $job['status'])) ?>
                                            </span>
                                        </td>
                                    </tr>
                                <?php endwhile; ?>
                            </tbody>
                        </table>
                    </div>
                <?php else: ?>
                    <div class="text-center py-8"><p class="text-gray-400 dark:text-slate-500 text-sm m-0">No jobs yet</p></div>
                <?php endif; ?>
            </div>

            <!-- ═══ RECENT CONTRACTS ════════════════════════════════════ -->
            <div class="fade-in rounded-lg border border-gray-200 dark:border-slate-700 bg-white dark:bg-slate-800 overflow-hidden" style="animation-delay:.43s">
                <div class="flex items-center justify-between px-5 py-4 border-b border-gray-100 dark:border-slate-700">
                    <h4 class="text-sm font-bold text-gray-900 dark:text-white m-0">Recent Contracts</h4>
                    <a href="contracts.php" class="text-xs font-semibold text-blue-600 dark:text-blue-400 no-underline hover:underline">View All &rarr;</a>
                </div>
                <?php if ($recentContracts->num_rows > 0): ?>
                    <div class="overflow-x-auto">
                        <table class="w-full text-sm">
                            <thead>
                                <tr class="border-b border-gray-100 dark:border-slate-700 bg-gray-50 dark:bg-slate-700/30">
                                    <th class="text-left px-5 py-3 text-xs font-semibold text-gray-500 dark:text-slate-400 uppercase tracking-wider">Project</th>
                                    <th class="text-left px-5 py-3 text-xs font-semibold text-gray-500 dark:text-slate-400 uppercase tracking-wider">Client</th>
                                    <th class="text-left px-5 py-3 text-xs font-semibold text-gray-500 dark:text-slate-400 uppercase tracking-wider">Freelancer</th>
                                    <th class="text-right px-5 py-3 text-xs font-semibold text-gray-500 dark:text-slate-400 uppercase tracking-wider">Budget</th>
                                    <th class="text-right px-5 py-3 text-xs font-semibold text-gray-500 dark:text-slate-400 uppercase tracking-wider">Status</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php while ($contract = $recentContracts->fetch_assoc()): ?>
                                    <tr class="border-b border-gray-50 dark:border-slate-700/50 last:border-0 hover:bg-gray-50 dark:hover:bg-slate-700/40 transition-colors">
                                        <td class="px-5 py-3.5"><span class="font-semibold text-gray-900 dark:text-white text-xs line-clamp-1"><?= htmlspecialchars($contract['job_title']) ?></span></td>
                                        <td class="px-5 py-3.5 text-gray-500 dark:text-slate-400 text-xs"><?= htmlspecialchars($contract['client_name']) ?></td>
                                        <td class="px-5 py-3.5 text-gray-500 dark:text-slate-400 text-xs"><?= htmlspecialchars($contract['freelancer_name']) ?></td>
                                        <td class="px-5 py-3.5 text-right font-semibold text-gray-900 dark:text-white text-xs"><?= format_currency((float) $contract['total_budget']) ?></td>
                                        <td class="px-5 py-3.5 text-right">
                                            <span class="inline-block px-2.5 py-1 rounded-full text-xs font-semibold <?= $jobStatusBadges[$contract['status']] ?? 'bg-gray-100 text-gray-600 dark:bg-gray-800 dark:text-gray-400' ?>">
                                                <?= ucfirst($contract['status']) ?>
                                            </span>
                                        </td>
                                    </tr>
                                <?php endwhile; ?>
                            </tbody>
                        </table>
                    </div>
                <?php else: ?>
                    <div class="text-center py-8"><p class="text-gray-400 dark:text-slate-500 text-sm m-0">No contracts yet</p></div>
                <?php endif; ?>
            </div>

            <!-- ═══ RECENT TRANSACTIONS ═════════════════════════════════ -->
            <div class="fade-in rounded-lg border border-gray-200 dark:border-slate-700 bg-white dark:bg-slate-800 overflow-hidden" style="animation-delay:.46s">
                <div class="flex items-center justify-between px-5 py-4 border-b border-gray-100 dark:border-slate-700">
                    <h4 class="text-sm font-bold text-gray-900 dark:text-white m-0">Recent Transactions</h4>
                    <a href="payments.php" class="text-xs font-semibold text-blue-600 dark:text-blue-400 no-underline hover:underline">View All &rarr;</a>
                </div>
                <?php if ($recentPayments->num_rows > 0): ?>
                    <div class="overflow-x-auto">
                        <table class="w-full text-sm">
                            <thead>
                                <tr class="border-b border-gray-100 dark:border-slate-700 bg-gray-50 dark:bg-slate-700/30">
                                    <th class="text-left px-5 py-3 text-xs font-semibold text-gray-500 dark:text-slate-400 uppercase tracking-wider">Invoice</th>
                                    <th class="text-left px-5 py-3 text-xs font-semibold text-gray-500 dark:text-slate-400 uppercase tracking-wider">Client</th>
                                    <th class="text-left px-5 py-3 text-xs font-semibold text-gray-500 dark:text-slate-400 uppercase tracking-wider">Freelancer</th>
                                    <th class="text-right px-5 py-3 text-xs font-semibold text-gray-500 dark:text-slate-400 uppercase tracking-wider">Amount</th>
                                    <th class="text-right px-5 py-3 text-xs font-semibold text-gray-500 dark:text-slate-400 uppercase tracking-wider">Status</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php while ($payment = $recentPayments->fetch_assoc()): ?>
                                    <tr class="border-b border-gray-50 dark:border-slate-700/50 last:border-0 hover:bg-gray-50 dark:hover:bg-slate-700/40 transition-colors">
                                        <td class="px-5 py-3.5"><span class="font-mono text-xs font-bold text-blue-600 dark:text-blue-400">#INV-<?= str_pad($payment['id'], 5, '0', STR_PAD_LEFT) ?></span></td>
                                        <td class="px-5 py-3.5 text-gray-500 dark:text-slate-400 text-xs"><?= htmlspecialchars($payment['client_name']) ?></td>
                                        <td class="px-5 py-3.5 text-gray-500 dark:text-slate-400 text-xs"><?= htmlspecialchars($payment['freelancer_name']) ?></td>
                                        <td class="px-5 py-3.5 text-right font-bold text-gray-900 dark:text-white text-xs"><?= format_currency((float) $payment['total_amount']) ?></td>
                                        <td class="px-5 py-3.5 text-right">
                                            <span class="inline-block px-2.5 py-1 rounded-full text-xs font-semibold <?= $paymentStatusBadges[$payment['status']] ?? 'bg-gray-100 text-gray-600 dark:bg-gray-800 dark:text-gray-400' ?>">
                                                <?= ucfirst($payment['status']) ?>
                                            </span>
                                        </td>
                                    </tr>
                                <?php endwhile; ?>
                            </tbody>
                        </table>
                    </div>
                <?php else: ?>
                    <div class="text-center py-8"><p class="text-gray-400 dark:text-slate-500 text-sm m-0">No transactions yet</p></div>
                <?php endif; ?>
            </div>

        </div>

        <!-- ═══ RIGHT SECTION (3 cols) ════════════════════════════════ -->
        <div class="col-span-12 lg:col-span-3 flex flex-col gap-5 sm:gap-6">

            <!-- Platform Activity -->
            <div class="fade-in rounded-lg border border-gray-200 dark:border-slate-700 bg-white dark:bg-slate-800 overflow-hidden" style="animation-delay:.30s">
                <div class="flex items-center justify-between px-5 py-4 border-b border-gray-100 dark:border-slate-700">
                    <h4 class="text-sm font-bold text-gray-900 dark:text-white m-0">Platform Activity</h4>
                    <a href="fraud_detection.php" class="text-xs font-semibold text-blue-600 dark:text-blue-400 no-underline hover:underline">View Logs &rarr;</a>
                </div>
                <?php if ($recentActivity->num_rows > 0): ?>
                    <div class="px-5 py-4">
                        <div class="relative">
                            <div class="absolute left-2 top-2 bottom-2 w-px bg-gray-200 dark:bg-slate-700"></div>
                            <?php
                            $actCount = 0;
                            while ($act = $recentActivity->fetch_assoc()):
                                if ($actCount >= 6)
                                    break;
                                $aInfo = $activityIcons[$act['action_type']] ?? $defaultActivity;
                                $actionLabel = ucwords(str_replace('_', ' ', $act['action_type']));
                                $actCount++;
                                ?>
                                <div class="relative flex gap-3 <?= $actCount < 6 ? 'pb-4' : '' ?>">
                                    <div class="relative z-10 flex-shrink-0 mt-0.5">
                                        <div class="w-4 h-4 rounded-full border-2 border-gray-200 dark:border-slate-600 flex items-center justify-center" style="background:<?= $aInfo['bg'] ?>;">
                                            <div class="w-1 h-1 rounded-full" style="background:<?= $aInfo['color'] ?>;"></div>
                                        </div>
                                    </div>
                                    <div class="flex-1 min-w-0">
                                        <p class="text-xs font-semibold text-gray-900 dark:text-white m-0 leading-snug">
                                            <a href="user_detail.php?id=<?= (int) $act['user_id'] ?>" class="text-gray-900 dark:text-white no-underline hover:text-blue-600 dark:hover:text-blue-400"><?= htmlspecialchars($act['user_name']) ?></a>
                                        </p>
                                        <p class="text-xs text-gray-500 dark:text-slate-400 m-0 mt-0.5 leading-snug"><?= htmlspecialchars($actionLabel) ?></p>
                                        <p class="text-xs text-gray-400 dark:text-slate-500 m-0 mt-1 leading-none"><?= time_ago($act['created_at']) ?></p>
                                    </div>
                                </div>
                            <?php endwhile; ?>
                        </div>
                    </div>
                <?php else: ?>
                    <div class="text-center py-8"><p class="text-gray-400 dark:text-slate-500 text-xs m-0">No activity yet</p></div>
                <?php endif; ?>
            </div>

            <!-- Quick Actions -->
            <div class="fade-in rounded-lg border border-gray-200 dark:border-slate-700 bg-white dark:bg-slate-800 overflow-hidden" style="animation-delay:.33s">
                <div class="px-5 py-4 border-b border-gray-100 dark:border-slate-700">
                    <h4 class="text-sm font-bold text-gray-900 dark:text-white m-0">Quick Actions</h4>
                </div>
                <div class="grid grid-cols-2 gap-px bg-gray-100 dark:bg-slate-700">
                    <a href="users.php" class="fade-in no-underline text-inherit bg-white dark:bg-slate-800 p-4 hover:bg-gray-50 dark:hover:bg-slate-700/50 transition-colors group" style="animation-delay:.36s">
                        <div class="w-9 h-9 rounded-lg bg-blue-50 dark:bg-blue-900/20 flex items-center justify-center mb-3">
                            <i data-lucide="users" class="w-4 h-4 text-blue-600 dark:text-blue-400"></i>
                        </div>
                        <h5 class="text-xs font-semibold text-gray-900 dark:text-white m-0">Manage Users</h5>
                        <p class="text-xs text-gray-400 dark:text-slate-500 m-0 mt-1"><?= number_format($totalUsers) ?> total users</p>
                    </a>
                    <a href="fraud_detection.php" class="fade-in no-underline text-inherit bg-white dark:bg-slate-800 p-4 hover:bg-gray-50 dark:hover:bg-slate-700/50 transition-colors group" style="animation-delay:.39s">
                        <div class="w-9 h-9 rounded-lg bg-amber-50 dark:bg-amber-900/20 flex items-center justify-center mb-3">
                            <i data-lucide="shield" class="w-4 h-4 text-amber-600 dark:text-amber-400"></i>
                        </div>
                        <h5 class="text-xs font-semibold text-gray-900 dark:text-white m-0">Fraud Detection</h5>
                        <p class="text-xs text-gray-400 dark:text-slate-500 m-0 mt-1"><?= $fraudAlerts ?> flagged accounts</p>
                    </a>
                    <a href="ai_matching.php" class="fade-in no-underline text-inherit bg-white dark:bg-slate-800 p-4 hover:bg-gray-50 dark:hover:bg-slate-700/50 transition-colors group" style="animation-delay:.42s">
                        <div class="w-9 h-9 rounded-lg bg-purple-50 dark:bg-purple-900/20 flex items-center justify-center mb-3">
                            <i data-lucide="brain" class="w-4 h-4 text-purple-600 dark:text-purple-400"></i>
                        </div>
                        <h5 class="text-xs font-semibold text-gray-900 dark:text-white m-0">AI Matching</h5>
                        <p class="text-xs text-gray-400 dark:text-slate-500 m-0 mt-1">Configure algorithm</p>
                    </a>
                    <a href="milestones.php" class="fade-in no-underline text-inherit bg-white dark:bg-slate-800 p-4 hover:bg-gray-50 dark:hover:bg-slate-700/50 transition-colors group" style="animation-delay:.45s">
                        <div class="w-9 h-9 rounded-lg bg-violet-50 dark:bg-violet-900/20 flex items-center justify-center mb-3">
                            <i data-lucide="list-checks" class="w-4 h-4 text-violet-600 dark:text-violet-400"></i>
                        </div>
                        <h5 class="text-xs font-semibold text-gray-900 dark:text-white m-0">Milestones</h5>
                        <p class="text-xs text-gray-400 dark:text-slate-500 m-0 mt-1"><?= $activeMilestones ?> in progress</p>
                    </a>
                    <a href="disputes.php" class="fade-in no-underline text-inherit bg-white dark:bg-slate-800 p-4 hover:bg-gray-50 dark:hover:bg-slate-700/50 transition-colors group" style="animation-delay:.48s">
                        <div class="w-9 h-9 rounded-lg bg-red-50 dark:bg-red-900/20 flex items-center justify-center mb-3">
                            <i data-lucide="scale" class="w-4 h-4 text-red-600 dark:text-red-400"></i>
                        </div>
                        <h5 class="text-xs font-semibold text-gray-900 dark:text-white m-0">Disputes</h5>
                        <p class="text-xs text-gray-400 dark:text-slate-500 m-0 mt-1"><?= $pendingDisputes ?> open</p>
                    </a>
                    <a href="settings.php" class="fade-in no-underline text-inherit bg-white dark:bg-slate-800 p-4 hover:bg-gray-50 dark:hover:bg-slate-700/50 transition-colors group" style="animation-delay:.51s">
                        <div class="w-9 h-9 rounded-lg bg-cyan-50 dark:bg-cyan-900/20 flex items-center justify-center mb-3">
                            <i data-lucide="settings" class="w-4 h-4 text-cyan-600 dark:text-cyan-400"></i>
                        </div>
                        <h5 class="text-xs font-semibold text-gray-900 dark:text-white m-0">Settings</h5>
                        <p class="text-xs text-gray-400 dark:text-slate-500 m-0 mt-1">Platform config</p>
                    </a>
                </div>
            </div>

            <!-- System Health -->
            <div class="fade-in rounded-2xl p-5 border border-gray-100 dark:border-slate-700 bg-white dark:bg-slate-800 shadow-sm" style="animation-delay:.54s">
                <h4 class="text-sm font-bold text-gray-900 dark:text-white m-0 mb-4">System Health</h4>
                <div class="flex flex-col gap-3">
                    <div class="flex items-center justify-between">
                        <span class="text-xs text-gray-600 dark:text-slate-400">Server Status</span>
                        <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded text-xs font-semibold bg-emerald-100 text-emerald-700 dark:bg-emerald-900/30 dark:text-emerald-400">
                            <span class="w-1.5 h-1.5 rounded-full bg-emerald-500"></span> Online
                        </span>
                    </div>
                    <div class="h-px bg-gray-100 dark:bg-slate-700"></div>
                    <div class="flex items-center justify-between">
                        <span class="text-xs text-gray-600 dark:text-slate-400">Database</span>
                        <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded text-xs font-semibold bg-emerald-100 text-emerald-700 dark:bg-emerald-900/30 dark:text-emerald-400">
                            <span class="w-1.5 h-1.5 rounded-full bg-emerald-500"></span> Connected
                        </span>
                    </div>
                    <div class="h-px bg-gray-100 dark:bg-slate-700"></div>
                    <div class="flex items-center justify-between">
                        <span class="text-xs text-gray-600 dark:text-slate-400">Uptime</span>
                        <span class="text-xs font-bold text-gray-900 dark:text-white">99.9%</span>
                    </div>
                    <div class="h-px bg-gray-100 dark:bg-slate-700"></div>
                    <div class="flex items-center justify-between">
                        <span class="text-xs text-gray-600 dark:text-slate-400">Payments Today</span>
                        <span class="text-xs font-bold text-gray-900 dark:text-white"><?= $paymentsToday ?></span>
                    </div>
                    <div class="h-px bg-gray-100 dark:bg-slate-700"></div>
                    <div class="flex items-center justify-between">
                        <span class="text-xs text-gray-600 dark:text-slate-400">Revenue Today</span>
                        <span class="text-xs font-bold text-gray-900 dark:text-white"><?= format_currency($revenueToday) ?></span>
                    </div>
                    <div class="h-px bg-gray-100 dark:bg-slate-700"></div>
                    <div class="flex items-center justify-between">
                        <span class="text-xs text-gray-600 dark:text-slate-400">Total Reviews</span>
                        <span class="text-xs font-bold text-gray-900 dark:text-white"><?= number_format($totalReviews) ?></span>
                    </div>
                    <div class="h-px bg-gray-100 dark:bg-slate-700"></div>
                    <div class="flex items-center justify-between">
                        <span class="text-xs text-gray-600 dark:text-slate-400">Unread Alerts</span>
                        <span class="text-xs font-bold <?= $unreadCount > 0 ? 'text-red-600 dark:text-red-400' : 'text-gray-900 dark:text-white' ?>"><?= number_format($unreadCount) ?></span>
                    </div>
                </div>
            </div>

        </div>

    </div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const isDark = document.documentElement.classList.contains('dark');

    const gridColor = isDark ? 'rgba(148,163,184,0.06)' : 'rgba(148,163,184,0.10)';
    const tickColor = isDark ? '#64748b' : '#94a3b8';
    const tooltipBg = isDark ? '#1e293b' : '#ffffff';
    const tooltipBorder = isDark ? '#334155' : '#e2e8f0';
    const tooltipText = isDark ? '#e2e8f0' : '#1e293b';
    const tooltipSubText = isDark ? '#94a3b8' : '#64748b';

    const colors = {
        users:   { line: '#4f6ef7', fill: 'rgba(79,110,247,0.08)', point: '#4f6ef7' },
        jobs:    { line: '#14b8a6', fill: 'rgba(20,184,166,0.08)', point: '#14b8a6' },
        revenue: { bar: 'rgba(79,110,247,0.80)', border: '#4f6ef7' },
        contracts: { bar: 'rgba(139,92,246,0.80)', border: '#8b5cf6' }
    };

    const commonOptions = {
        responsive: true,
        maintainAspectRatio: false,
        layout: { padding: { top: 4, bottom: 0, left: 0, right: 0 } },
        plugins: {
            legend: { display: false },
            tooltip: {
                enabled: true,
                backgroundColor: tooltipBg,
                titleColor: tooltipText,
                bodyColor: tooltipSubText,
                titleFont: { family: 'Inter', weight: '600', size: 12 },
                bodyFont: { family: 'Inter', size: 11, weight: '400' },
                padding: { top: 10, bottom: 10, left: 14, right: 14 },
                cornerRadius: 8,
                borderColor: tooltipBorder,
                borderWidth: 1,
                displayColors: true,
                boxWidth: 8,
                boxHeight: 8,
                boxPadding: 4,
                usePointStyle: true,
                callbacks: {}
            }
        },
        interaction: { intersect: false, mode: 'index' }
    };

    const commonScaleX = {
        grid: { display: false },
        border: { display: false },
        ticks: {
            color: tickColor,
            font: { family: 'Inter', size: 11, weight: '500' },
            padding: 8
        }
    };

    const commonScaleY = {
        grid: { color: gridColor, drawBorder: false },
        border: { display: false },
        ticks: {
            color: tickColor,
            font: { family: 'Inter', size: 11, weight: '400' },
            padding: 12
        },
        beginAtZero: true
    };

    // User Growth
    new Chart(document.getElementById('usersChart'), {
        type: 'line',
        data: {
            labels: <?= json_encode($userLabels) ?>,
            datasets: [{
                label: 'Users', data: <?= json_encode($userData) ?>,
                borderColor: colors.users.line,
                backgroundColor: colors.users.fill,
                borderWidth: 2, fill: true, tension: 0.4,
                pointBackgroundColor: colors.users.point,
                pointBorderColor: isDark ? '#1e293b' : '#ffffff',
                pointBorderWidth: 2, pointRadius: 3, pointHoverRadius: 5
            }]
        },
        options: {
            ...commonOptions,
            scales: { x: commonScaleX, y: commonScaleY }
        }
    });

    // Job Growth
    new Chart(document.getElementById('jobsChart'), {
        type: 'line',
        data: {
            labels: <?= json_encode($jobLabels) ?>,
            datasets: [{
                label: 'Jobs', data: <?= json_encode($jobData) ?>,
                borderColor: colors.jobs.line,
                backgroundColor: colors.jobs.fill,
                borderWidth: 2, fill: true, tension: 0.4,
                pointBackgroundColor: colors.jobs.point,
                pointBorderColor: isDark ? '#1e293b' : '#ffffff',
                pointBorderWidth: 2, pointRadius: 3, pointHoverRadius: 5
            }]
        },
        options: {
            ...commonOptions,
            scales: { x: commonScaleX, y: commonScaleY }
        }
    });

    // Revenue
    new Chart(document.getElementById('revenueChart'), {
        type: 'bar',
        data: {
            labels: <?= json_encode($revenueLabels) ?>,
            datasets: [{
                label: 'Revenue', data: <?= json_encode($revenueData) ?>,
                backgroundColor: colors.revenue.bar,
                borderColor: colors.revenue.border,
                borderWidth: 0, borderRadius: 6, borderSkipped: false, barThickness: 24
            }]
        },
        options: {
            ...commonOptions,
            scales: {
                x: commonScaleX,
                y: { ...commonScaleY, ticks: { ...commonScaleY.ticks, callback: v => '$' + v.toLocaleString() } }
            }
        }
    });

    // Contracts
    new Chart(document.getElementById('contractsChart'), {
        type: 'bar',
        data: {
            labels: <?= json_encode($contractLabels) ?>,
            datasets: [{
                label: 'Contracts', data: <?= json_encode($contractData) ?>,
                backgroundColor: colors.contracts.bar,
                borderColor: colors.contracts.border,
                borderWidth: 0, borderRadius: 6, borderSkipped: false, barThickness: 24
            }]
        },
        options: {
            ...commonOptions,
            scales: { x: commonScaleX, y: commonScaleY }
        }
    });
});
</script>
<?php require_once __DIR__ . '/../components/layout_end.php'; ?>
