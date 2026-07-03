<?php

/**
 * Admin Dashboard – FreelanceHub
 * Modern card-based dashboard with top navigation.
 */
require_once __DIR__ . '/../auth/auth.php';
require_role('admin');
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../components/stat_card.php';

$currentPage = 'dashboard';
$userId = $_SESSION['user_id'];

// User Info
$uStmt = $conn->prepare('SELECT name, profile_image FROM users WHERE id = ?');
$uStmt->bind_param('i', $userId);
$uStmt->execute();
$adminUser = $uStmt->get_result()->fetch_assoc();
$uStmt->close();
$adminName = $adminUser['name'] ?? 'Admin';
$adminFirst = explode(' ', $adminName)[0];
$adminAvatar = get_profile_image($adminUser['profile_image'] ?? null);

// User counts by role
$r = $conn->query('SELECT role, COUNT(*) AS cnt FROM users GROUP BY role');
$roleCounts = ['client' => 0, 'freelancer' => 0, 'admin' => 0];
while ($row = $r->fetch_assoc()) $roleCounts[$row['role']] = (int) $row['cnt'];
$totalUsers = array_sum($roleCounts);
$totalFreelancers = $roleCounts['freelancer'];
$totalClients = $roleCounts['client'];

// Job counts
$r = $conn->query('SELECT status, COUNT(*) AS cnt FROM jobs GROUP BY status');
$jobCounts = ['open' => 0, 'in_progress' => 0, 'completed' => 0, 'disputed' => 0, 'cancelled' => 0];
while ($row = $r->fetch_assoc()) $jobCounts[$row['status']] = (int) $row['cnt'];
$activeJobs = $jobCounts['open'];

// Contracts
$r = $conn->query('SELECT status, COUNT(*) AS cnt FROM contracts GROUP BY status');
$contractCounts = [];
while ($row = $r->fetch_assoc()) $contractCounts[$row['status']] = (int) $row['cnt'];
$activeContracts = $contractCounts['active'] ?? 0;

// Revenue
$r = $conn->query("SELECT COALESCE(SUM(platform_fee), 0) AS total_revenue, COALESCE(SUM(total_amount), 0) AS total_volume FROM payments WHERE status = 'completed'");
$payRow = $r->fetch_assoc();
$totalRevenue = (float) $payRow['total_revenue'];

$r = $conn->query("SELECT COUNT(*) AS cnt, COALESCE(SUM(total_amount), 0) AS total FROM payments WHERE status = 'completed' AND DATE(created_at) = CURDATE()");
$todayPay = $r->fetch_assoc();
$paymentsToday = (int) $todayPay['cnt'];
$revenueToday = (float) $todayPay['total'];

// Fraud alerts
$r = $conn->query("SELECT COUNT(*) AS cnt FROM users WHERE status = 'flagged'");
$fraudAlerts = (int) $r->fetch_assoc()['cnt'];

$r = $conn->query('SELECT COUNT(*) AS cnt FROM reviews WHERE rating IS NOT NULL');
$totalReviews = (int) $r->fetch_assoc()['cnt'];

// Monthly Revenue
$revenueChart = [];
$r = $conn->query("
    SELECT DATE_FORMAT(created_at, '%Y-%m') AS month_key,
           DATE_FORMAT(created_at, '%b') AS month_label,
           COALESCE(SUM(platform_fee), 0) AS revenue
    FROM payments WHERE status = 'completed'
      AND created_at >= DATE_SUB(CURDATE(), INTERVAL 6 MONTH)
    GROUP BY month_key, month_label ORDER BY month_key ASC
");
while ($row = $r->fetch_assoc()) $revenueChart[] = $row;

// Monthly Registrations
$userChart = [];
$r = $conn->query("
    SELECT DATE_FORMAT(created_at, '%Y-%m') AS month_key,
           DATE_FORMAT(created_at, '%b') AS month_label,
           COUNT(*) AS cnt
    FROM users WHERE created_at >= DATE_SUB(CURDATE(), INTERVAL 6 MONTH)
    GROUP BY month_key, month_label ORDER BY month_key ASC
");
while ($row = $r->fetch_assoc()) $userChart[] = $row;

// Recent Jobs
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

// Recent Contracts
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

// Recent Payments
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

// Flagged Users
$rrStmt = $conn->prepare('SELECT id, name, email, role, status, created_at FROM users WHERE status = "flagged" ORDER BY created_at DESC LIMIT 5');
$rrStmt->execute();
$flaggedUsers = $rrStmt->get_result();
$rrStmt->close();

// Recent Users
$ruStmt = $conn->prepare('SELECT id, name, email, role, status, profile_image, created_at FROM users ORDER BY created_at DESC LIMIT 6');
$ruStmt->execute();
$recentUsers = $ruStmt->get_result();
$ruStmt->close();

// Activity Timeline
$actStmt = $conn->prepare('
    SELECT ubl.action_type, ubl.created_at, u.name AS user_name, u.id AS user_id
    FROM user_behavior_logs ubl JOIN users u ON ubl.user_id = u.id
    ORDER BY ubl.created_at DESC LIMIT 8
');
$actStmt->execute();
$recentActivity = $actStmt->get_result();
$actStmt->close();

$conn->close();

$revenueLabels = array_column($revenueChart, 'month_label');
$revenueData = array_column($revenueChart, 'revenue');
$userLabels = array_column($userChart, 'month_label');
$userData = array_column($userChart, 'cnt');

$jobStatusBadges = ['open' => 'bdg-emerald', 'in_progress' => 'bdg-orange', 'completed' => 'bdg-blue', 'cancelled' => 'bdg-rose', 'disputed' => 'bdg-rose'];
$paymentStatusBadges = ['completed' => 'bdg-emerald', 'pending' => 'bdg-orange', 'failed' => 'bdg-rose', 'refunded' => 'bdg-gray'];
$roleBadges = ['client' => 'bdg-blue', 'freelancer' => 'bdg-emerald', 'admin' => 'bdg-purple'];
$userStatusBadges = ['active' => 'bdg-emerald', 'flagged' => 'bdg-orange', 'suspended' => 'bdg-rose', 'inactive' => 'bdg-gray'];
$activityIcons = [
    'login' => ['icon' => 'fa-sign-in-alt', 'color' => '#2563EB', 'bg' => '#EEF5FF'],
    'register' => ['icon' => 'fa-user-plus', 'color' => '#059669', 'bg' => '#ECFDF5'],
    'job_posted' => ['icon' => 'fa-briefcase', 'color' => '#7C3AED', 'bg' => '#F5F3FF'],
    'proposal_sent' => ['icon' => 'fa-paper-plane', 'color' => '#0891B2', 'bg' => '#ECFEFF'],
    'payment_made' => ['icon' => 'fa-dollar-sign', 'color' => '#EA580C', 'bg' => '#FFF7ED'],
    'contract_create' => ['icon' => 'fa-file-contract', 'color' => '#2563EB', 'bg' => '#EEF5FF'],
    'review_posted' => ['icon' => 'fa-star', 'color' => '#D97706', 'bg' => '#FFFBEB'],
];
$defaultActivity = ['icon' => 'fa-circle', 'color' => '#94a3b8', 'bg' => '#f1f5f9'];

$navItems = [
    ['key' => 'dashboard', 'label' => 'Dashboard', 'url' => 'dashboard.php', 'icon' => 'fa-th-large'],
    ['key' => 'users', 'label' => 'Users', 'url' => 'users.php', 'icon' => 'fa-users'],
    ['key' => 'jobs', 'label' => 'Jobs', 'url' => 'jobs.php', 'icon' => 'fa-briefcase'],
    ['key' => 'payments', 'label' => 'Payments', 'url' => 'payments.php', 'icon' => 'fa-credit-card'],
    ['key' => 'fraud', 'label' => 'Fraud', 'url' => 'fraud_detection.php', 'icon' => 'fa-shield-halved'],
    ['key' => 'matching', 'label' => 'AI Matching', 'url' => 'ai_matching.php', 'icon' => 'fa-brain'],
    ['key' => 'settings', 'label' => 'Settings', 'url' => 'settings.php', 'icon' => 'fa-cog'],
];
$pageTitle = 'Admin Dashboard';
$pageSubtitle = 'Welcome back, ' . htmlspecialchars($adminFirst) . " — here's what's happening";
$activePage = 'dashboard';
$user = ['name' => $adminName, 'profile_image' => $adminUser['profile_image'] ?? null];
$unreadCount = 0;
$profileLink = 'profile.php';
require_once __DIR__ . '/../components/layout_start.php';
?>


    <?php display_flash('success'); display_flash('error'); display_flash('info'); ?>

    <!-- Welcome Banner -->
    <div class="fade-in relative overflow-hidden rounded-2xl p-8 bg-gradient-to-r from-blue-800 via-blue-600 to-cyan-600">
        <div class="relative z-10 flex flex-wrap items-center justify-between gap-4">
            <div>
                <h2 class="text-2xl font-extrabold text-white m-0">Good <?= (date('H') < 12 ? 'Morning' : (date('H') < 18 ? 'Afternoon' : 'Evening')) ?>, <?= htmlspecialchars($adminFirst) ?></h2>
                <p class="text-white/80 text-sm mt-1"><?= date('l, F j, Y') ?></p>
            </div>
            <span class="inline-flex items-center gap-1.5 px-3.5 py-1.5 rounded-full bg-white/15 text-white text-xs font-semibold backdrop-blur-sm">
                <span class="w-2 h-2 rounded-full bg-green-500"></span>
                Platform Operational
            </span>
        </div>
        <div class="absolute -top-16 -right-10 w-64 h-64 rounded-full bg-white/7 pointer-events-none"></div>
    </div>

    <!-- Stats Cards -->
    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-6 gap-4">
        <?php
        renderStatCard('Total Users', $totalUsers, 'up', '+18%', 'vs last month', 'fa-users', 'blue', 'Users', 'users.php', 0);
        renderStatCard('Freelancers', $totalFreelancers, 'up', '+12%', 'vs last month', 'fa-user-tie', 'emerald', 'Freelancers', 'users.php', 1);
        renderStatCard('Clients', $totalClients, 'up', '+8%', 'vs last month', 'fa-building', 'purple', 'Clients', 'users.php', 2);
        renderStatCard('Active Jobs', $activeJobs, 'up', '+15%', 'vs last month', 'fa-briefcase', 'cyan', 'Jobs', 'jobs.php', 3);
        renderStatCard('Active Contracts', $activeContracts, 'neutral', '', '', 'fa-file-contract', 'orange', 'Contracts', '', 4);
        renderStatCard('Total Revenue', $totalRevenue, 'up', '+24%', 'vs last month', 'fa-dollar-sign', 'rose', 'Revenue', 'payments.php', 5);
        ?>
    </div>

    <!-- Two Column: Charts -->
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
        <div class="dh-card fade-in p-6">
            <div class="flex items-center justify-between mb-5">
                <div>
                    <h4 class="text-sm font-bold text-gray-900 dark:text-white m-0">Monthly Registrations</h4>
                    <p class="text-xs text-slate-400 mt-1">Last 6 months</p>
                </div>
                <div class="w-10 h-10 rounded-xl bg-blue-50 dark:bg-blue-900/30 flex items-center justify-center">
                    <i class="fas fa-chart-line text-blue-600 dark:text-blue-400 text-sm"></i>
                </div>
            </div>
            <div class="relative h-56"><canvas id="usersChart"></canvas></div>
        </div>
        <div class="dh-card fade-in p-6">
            <div class="flex items-center justify-between mb-5">
                <div>
                    <h4 class="text-sm font-bold text-gray-900 dark:text-white m-0">Revenue Trend</h4>
                    <p class="text-xs text-slate-400 mt-1">Monthly platform fees</p>
                </div>
                <div class="w-10 h-10 rounded-xl bg-emerald-50 dark:bg-emerald-900/30 flex items-center justify-center">
                    <i class="fas fa-chart-column text-emerald-600 dark:text-emerald-400 text-sm"></i>
                </div>
            </div>
            <div class="relative h-56"><canvas id="revenueChart"></canvas></div>
        </div>
    </div>

    <!-- Two Column: Jobs + Contracts -->
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
        <!-- Recent Jobs -->
        <div class="dh-card fade-in p-6">
            <div class="flex items-center justify-between mb-5">
                <h4 class="text-sm font-bold text-gray-900 dark:text-white m-0">Latest Jobs</h4>
                <a href="jobs.php" class="text-xs font-semibold text-blue-600 dark:text-blue-400 no-underline hover:underline">View All &rarr;</a>
            </div>
            <?php if ($recentJobs->num_rows > 0): ?>
                <div class="overflow-x-auto">
                    <table class="dtbl">
                        <thead><tr><th>Job</th><th>Client</th><th>Budget</th><th>Status</th></tr></thead>
                        <tbody>
                            <?php while ($job = $recentJobs->fetch_assoc()): ?>
                                <tr>
                                    <td class="font-semibold text-gray-900 dark:text-white"><?= htmlspecialchars($job['title']) ?></td>
                                    <td class="text-slate-400 text-xs"><?= htmlspecialchars($job['client_name']) ?></td>
                                    <td class="font-semibold text-gray-900 dark:text-white text-xs"><?= format_currency((float) $job['budget']) ?></td>
                                    <td><span class="bdg <?= $jobStatusBadges[$job['status']] ?? 'bdg-gray' ?> text-[10px]"><?= ucfirst(str_replace('_', ' ', $job['status'])) ?></span></td>
                                </tr>
                            <?php endwhile; ?>
                        </tbody>
                    </table>
                </div>
            <?php else: ?>
                <div class="text-center py-5"><p class="text-slate-400 text-sm">No jobs yet</p></div>
            <?php endif; ?>
        </div>

        <!-- Recent Contracts -->
        <div class="dh-card fade-in p-6">
            <div class="flex items-center justify-between mb-5">
                <h4 class="text-sm font-bold text-gray-900 dark:text-white m-0">Recent Contracts</h4>
            </div>
            <?php if ($recentContracts->num_rows > 0): ?>
                <div class="overflow-x-auto">
                    <table class="dtbl">
                        <thead><tr><th>Project</th><th>Client</th><th>Freelancer</th><th>Budget</th><th>Status</th></tr></thead>
                        <tbody>
                            <?php while ($contract = $recentContracts->fetch_assoc()): ?>
                                <tr>
                                    <td class="font-semibold text-gray-900 dark:text-white text-xs"><?= htmlspecialchars($contract['job_title']) ?></td>
                                    <td class="text-slate-400 text-xs"><?= htmlspecialchars($contract['client_name']) ?></td>
                                    <td class="text-slate-400 text-xs"><?= htmlspecialchars($contract['freelancer_name']) ?></td>
                                    <td class="font-semibold text-gray-900 dark:text-white text-xs"><?= format_currency((float) $contract['total_budget']) ?></td>
                                    <td><span class="bdg <?= $jobStatusBadges[$contract['status']] ?? 'bdg-gray' ?> text-[10px]"><?= ucfirst($contract['status']) ?></span></td>
                                </tr>
                            <?php endwhile; ?>
                        </tbody>
                    </table>
                </div>
            <?php else: ?>
                <div class="text-center py-5"><p class="text-slate-400 text-sm">No contracts yet</p></div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Two Column: Payments + Reports -->
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
        <!-- Recent Payments -->
        <div class="dh-card fade-in p-6">
            <div class="flex items-center justify-between mb-5">
                <h4 class="text-sm font-bold text-gray-900 dark:text-white m-0">Recent Payments</h4>
                <a href="payments.php" class="text-xs font-semibold text-blue-600 dark:text-blue-400 no-underline hover:underline">View All &rarr;</a>
            </div>
            <?php if ($recentPayments->num_rows > 0): ?>
                <div class="overflow-x-auto">
                    <table class="dtbl">
                        <thead><tr><th>Invoice</th><th>Client</th><th>Freelancer</th><th>Amount</th><th>Fee</th></tr></thead>
                        <tbody>
                            <?php while ($payment = $recentPayments->fetch_assoc()): ?>
                                <tr>
                                    <td class="font-semibold text-blue-600 dark:text-blue-400 text-xs">#INV-<?= str_pad($payment['id'], 5, '0', STR_PAD_LEFT) ?></td>
                                    <td class="text-slate-400 text-xs"><?= htmlspecialchars($payment['client_name']) ?></td>
                                    <td class="text-slate-400 text-xs"><?= htmlspecialchars($payment['freelancer_name']) ?></td>
                                    <td class="font-bold text-gray-900 dark:text-white text-xs"><?= format_currency((float) $payment['total_amount']) ?></td>
                                    <td class="text-emerald-600 dark:text-emerald-400 font-semibold text-xs"><?= format_currency((float) $payment['platform_fee']) ?></td>
                                </tr>
                            <?php endwhile; ?>
                        </tbody>
                    </table>
                </div>
            <?php else: ?>
                <div class="text-center py-5"><p class="text-slate-400 text-sm">No payments yet</p></div>
            <?php endif; ?>
        </div>

        <!-- Pending Reports -->
        <div class="dh-card fade-in p-6">
            <div class="flex items-center justify-between mb-5">
                <h4 class="text-sm font-bold text-gray-900 dark:text-white m-0">Pending Reports</h4>
                <a href="fraud_detection.php" class="text-xs font-semibold text-blue-600 dark:text-blue-400 no-underline hover:underline">View All &rarr;</a>
            </div>
            <?php if ($flaggedUsers->num_rows > 0): ?>
                <div class="flex flex-col gap-2.5">
                    <?php while ($fu = $flaggedUsers->fetch_assoc()): ?>
                        <div class="flex items-center gap-3 p-3 rounded-xl border border-amber-200 dark:border-amber-800 bg-amber-50 dark:bg-amber-900/20">
                            <div class="w-9 h-9 rounded-[10px] bg-amber-100 dark:bg-amber-900/40 flex items-center justify-center shrink-0">
                                <i class="fas fa-exclamation-triangle text-amber-600 dark:text-amber-400 text-sm"></i>
                            </div>
                            <div class="flex-1 min-w-0">
                                <p class="text-[13px] font-semibold text-gray-900 dark:text-white m-0"><?= htmlspecialchars($fu['name']) ?></p>
                                <p class="text-[11px] text-amber-800 dark:text-amber-300 mt-0.5"><?= ucfirst($fu['role']) ?> &middot; Flagged for review</p>
                            </div>
                            <a href="user_detail.php?id=<?= (int) $fu['id'] ?>" class="text-[11px] font-semibold text-blue-600 dark:text-blue-400 no-underline hover:underline">Review &rarr;</a>
                        </div>
                    <?php endwhile; ?>
                </div>
            <?php else: ?>
                <div class="text-center py-5"><p class="text-slate-400 text-sm">No pending reports</p></div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Two Column: Users + Activity -->
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
        <!-- Recent Users -->
        <div class="dh-card fade-in p-6">
            <div class="flex items-center justify-between mb-5">
                <h4 class="text-sm font-bold text-gray-900 dark:text-white m-0">Recent Users</h4>
                <a href="users.php" class="text-xs font-semibold text-blue-600 dark:text-blue-400 no-underline hover:underline">View All &rarr;</a>
            </div>
            <?php if ($recentUsers->num_rows > 0): ?>
                <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-2.5">
                    <?php while ($u = $recentUsers->fetch_assoc()): ?>
                        <a href="user_detail.php?id=<?= (int) $u['id'] ?>" class="flex items-center gap-2.5 p-3 rounded-xl border border-gray-100 dark:border-slate-700 no-underline text-inherit hover:border-blue-200 dark:hover:border-blue-800 transition-colors">
                            <img src="<?= htmlspecialchars(get_profile_image($u['profile_image'])) ?>" class="w-9 h-9 rounded-[10px] object-cover border-2 border-gray-100 dark:border-slate-600" alt="">
                            <div class="flex-1 min-w-0">
                                <p class="text-xs font-semibold text-gray-900 dark:text-white m-0 truncate"><?= htmlspecialchars($u['name']) ?></p>
                                <p class="text-[10px] text-slate-400 mt-0.5"><?= ucfirst($u['role']) ?> &middot; <?= time_ago($u['created_at']) ?></p>
                            </div>
                            <span class="bdg <?= $userStatusBadges[$u['status']] ?? 'bdg-gray' ?> text-[9px] px-1.5 py-0.5"><?= ucfirst($u['status']) ?></span>
                        </a>
                    <?php endwhile; ?>
                </div>
            <?php else: ?>
                <div class="text-center py-5"><p class="text-slate-400 text-sm">No users yet</p></div>
            <?php endif; ?>
        </div>

        <!-- Activity Timeline -->
        <div class="dh-card fade-in p-6">
            <h4 class="text-sm font-bold text-gray-900 dark:text-white m-0 mb-4">Platform Activity</h4>
            <?php if ($recentActivity->num_rows > 0): ?>
                <div>
                    <?php while ($act = $recentActivity->fetch_assoc()):
                        $aInfo = $activityIcons[$act['action_type']] ?? $defaultActivity;
                        $actionLabel = ucwords(str_replace('_', ' ', $act['action_type']));
                    ?>
                        <div class="tl-item">
                            <div class="tl-dot" style="background:<?= $aInfo['bg'] ?>; color:<?= $aInfo['color'] ?>;">
                                <i class="fas <?= $aInfo['icon'] ?>" style="font-size:9px;"></i>
                            </div>
                            <div>
                                <p class="text-[13px] text-slate-600 dark:text-slate-300 m-0">
                                    <a href="user_detail.php?id=<?= (int) $act['user_id'] ?>" class="font-semibold text-gray-900 dark:text-white no-underline"><?= htmlspecialchars($act['user_name']) ?></a>
                                    <?= htmlspecialchars($actionLabel) ?>
                                </p>
                                <p class="text-[11px] text-slate-400 mt-1"><?= time_ago($act['created_at']) ?></p>
                            </div>
                        </div>
                    <?php endwhile; ?>
                </div>
            <?php else: ?>
                <div class="text-center py-5"><p class="text-slate-400 text-sm">No activity yet</p></div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Quick Actions -->
    <div>
        <h3 class="text-base font-bold text-gray-900 dark:text-white m-0 mb-4">Quick Actions</h3>
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
            <a href="users.php" class="qa-card fade-in no-underline text-inherit">
                <div class="w-12 h-12 rounded-2xl bg-blue-50 dark:bg-blue-900/30 text-blue-600 dark:text-blue-400 flex items-center justify-center"><i class="fas fa-users-cog text-lg"></i></div>
                <h5 class="text-sm font-bold text-gray-900 dark:text-white mt-3 mb-1">Manage Users</h5>
                <p class="text-xs text-slate-400 m-0">View and manage</p>
            </a>
            <a href="fraud_detection.php" class="qa-card fade-in no-underline text-inherit">
                <div class="w-12 h-12 rounded-2xl bg-amber-50 dark:bg-amber-900/30 text-amber-600 dark:text-amber-400 flex items-center justify-center"><i class="fas fa-shield-halved text-lg"></i></div>
                <h5 class="text-sm font-bold text-gray-900 dark:text-white mt-3 mb-1">Fraud Detection</h5>
                <p class="text-xs text-slate-400 m-0"><?= $fraudAlerts ?> flagged</p>
            </a>
            <a href="ai_matching.php" class="qa-card fade-in no-underline text-inherit">
                <div class="w-12 h-12 rounded-2xl bg-purple-50 dark:bg-purple-900/30 text-purple-600 dark:text-purple-400 flex items-center justify-center"><i class="fas fa-brain text-lg"></i></div>
                <h5 class="text-sm font-bold text-gray-900 dark:text-white mt-3 mb-1">AI Matching</h5>
                <p class="text-xs text-slate-400 m-0">Configure algorithm</p>
            </a>
            <a href="settings.php" class="qa-card fade-in no-underline text-inherit">
                <div class="w-12 h-12 rounded-2xl bg-cyan-50 dark:bg-cyan-900/30 text-cyan-600 dark:text-cyan-400 flex items-center justify-center"><i class="fas fa-sliders text-lg"></i></div>
                <h5 class="text-sm font-bold text-gray-900 dark:text-white mt-3 mb-1">Settings</h5>
                <p class="text-xs text-slate-400 m-0">Platform config</p>
            </a>
        </div>
    </div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const isDark = document.documentElement.classList.contains('dark');
    const gridColor = isDark ? 'rgba(148,163,184,0.1)' : 'rgba(148,163,184,0.15)';
    const textColor = isDark ? '#94a3b8' : '#64748b';

    new Chart(document.getElementById('usersChart'), {
        type: 'line',
        data: {
            labels: <?= json_encode($userLabels) ?>,
            datasets: [{
                label: 'Users', data: <?= json_encode($userData) ?>,
                borderColor: '#2563eb', backgroundColor: 'rgba(37,99,235,0.06)',
                borderWidth: 2.5, fill: true, tension: 0.4,
                pointBackgroundColor: '#2563eb', pointBorderColor: '#fff', pointBorderWidth: 2, pointRadius: 4, pointHoverRadius: 6
            }]
        },
        options: {
            responsive: true, maintainAspectRatio: false,
            plugins: { legend: { display: false } },
            scales: {
                x: { grid: { color: gridColor }, ticks: { color: textColor, font: { size: 11 } } },
                y: { grid: { color: gridColor }, ticks: { color: textColor, font: { size: 11 } }, beginAtZero: true }
            }
        }
    });

    new Chart(document.getElementById('revenueChart'), {
        type: 'bar',
        data: {
            labels: <?= json_encode($revenueLabels) ?>,
            datasets: [{
                label: 'Revenue', data: <?= json_encode($revenueData) ?>,
                backgroundColor: 'rgba(5,150,105,0.7)', borderColor: '#059669',
                borderWidth: 1.5, borderRadius: 8, borderSkipped: false, barThickness: 28
            }]
        },
        options: {
            responsive: true, maintainAspectRatio: false,
            plugins: { legend: { display: false } },
            scales: {
                x: { grid: { display: false }, ticks: { color: textColor, font: { size: 11 } } },
                y: { grid: { color: gridColor }, ticks: { color: textColor, font: { size: 11 }, callback: v => '$' + v.toLocaleString() }, beginAtZero: true }
            }
        }
    });
});
</script>
<?php require_once __DIR__ . '/../components/layout_end.php'; ?>
