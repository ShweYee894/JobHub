<?php

/**
 * Admin Analytics – Modern SaaS Dashboard
 * Grouped KPI cards + daily trend charts with sparklines.
 */
require_once __DIR__ . '/../auth/auth.php';
require_role('admin');
require_once __DIR__ . '/../config/db.php';

$currentPage = 'analytics';
$userId = $_SESSION['user_id'];

// ── Date Range Selection ───────────────────────────────────────────
$range = $_GET['range'] ?? '30d';
if (!in_array($range, ['today', '7d', '30d']))
    $range = '30d';
$rangeDays = match ($range) {
    'today' => 0,
    '7d' => 6,
    default => 29
};
$rangeLabel = match ($range) {
    'today' => 'Today',
    '7d' => 'Last 7 Days',
    default => 'Last 30 Days'
};

// ── User Info ────────────────────────────────────────────────────────
$uStmt = $conn->prepare('SELECT name, profile_image FROM users WHERE id = ?');
$uStmt->bind_param('i', $userId);
$uStmt->execute();
$adminUser = $uStmt->get_result()->fetch_assoc();
$uStmt->close();
$adminName = $adminUser['name'] ?? 'Admin';

// ══════════════════════════════════════════════════════════════════════
// KPI QUERIES
// ══════════════════════════════════════════════════════════════════════

function q(mysqli $c, string $sql): array
{
    return $c->query($sql)->fetch_assoc();
}

function qi(mysqli $c, string $sql): int
{
    return (int) q($c, $sql)['cnt'];
}

// ── User Activity ────────────────────────────────────────────────────
$kpiRangeRegistrations = qi($conn, "SELECT COUNT(*) AS cnt FROM users WHERE created_at >= DATE_SUB(CURDATE(), INTERVAL {$rangeDays} DAY)");
$kpiRangeRegistrationsPrev = qi($conn, 'SELECT COUNT(*) AS cnt FROM users WHERE created_at >= DATE_SUB(CURDATE(), INTERVAL ' . ($rangeDays * 2) . " DAY) AND created_at < DATE_SUB(CURDATE(), INTERVAL {$rangeDays} DAY)");
$kpiTotalUsers = qi($conn, 'SELECT COUNT(*) AS cnt FROM users');

$kpiRangeLogins = qi($conn, "SELECT COUNT(DISTINCT user_id) AS cnt FROM user_behavior_logs WHERE action_type = 'login' AND created_at >= DATE_SUB(CURDATE(), INTERVAL {$rangeDays} DAY)");
$kpiRangeLoginsPrev = qi($conn, "SELECT COUNT(DISTINCT user_id) AS cnt FROM user_behavior_logs WHERE action_type = 'login' AND created_at >= DATE_SUB(CURDATE(), INTERVAL " . ($rangeDays * 2) . " DAY) AND created_at < DATE_SUB(CURDATE(), INTERVAL {$rangeDays} DAY)");

$kpiOnlineUsers = qi($conn, 'SELECT COUNT(*) AS cnt FROM users WHERE last_login_at >= DATE_SUB(NOW(), INTERVAL 15 MINUTE)');
$kpiOnlineUsersY = qi($conn, 'SELECT COUNT(*) AS cnt FROM users WHERE last_login_at >= DATE_SUB(DATE_SUB(NOW(), INTERVAL 15 MINUTE), INTERVAL 1 DAY) AND last_login_at < DATE_SUB(NOW(), INTERVAL 15 MINUTE)');

// ── Marketplace Activity ─────────────────────────────────────────────
$kpiRangeJobs = qi($conn, "SELECT COUNT(*) AS cnt FROM jobs WHERE created_at >= DATE_SUB(CURDATE(), INTERVAL {$rangeDays} DAY)");
$kpiRangeJobsPrev = qi($conn, 'SELECT COUNT(*) AS cnt FROM jobs WHERE created_at >= DATE_SUB(CURDATE(), INTERVAL ' . ($rangeDays * 2) . " DAY) AND created_at < DATE_SUB(CURDATE(), INTERVAL {$rangeDays} DAY)");
$kpiTotalJobs = qi($conn, 'SELECT COUNT(*) AS cnt FROM jobs');

$kpiRangeContracts = qi($conn, "SELECT COUNT(*) AS cnt FROM contracts WHERE created_at >= DATE_SUB(CURDATE(), INTERVAL {$rangeDays} DAY)");
$kpiRangeContractsPrev = qi($conn, 'SELECT COUNT(*) AS cnt FROM contracts WHERE created_at >= DATE_SUB(CURDATE(), INTERVAL ' . ($rangeDays * 2) . " DAY) AND created_at < DATE_SUB(CURDATE(), INTERVAL {$rangeDays} DAY)");
$kpiTotalContracts = qi($conn, 'SELECT COUNT(*) AS cnt FROM contracts');

$kpiRangeMilestones = qi($conn, "SELECT COUNT(*) AS cnt FROM milestones WHERE created_at >= DATE_SUB(CURDATE(), INTERVAL {$rangeDays} DAY)");
$kpiRangeMilestonesPrev = qi($conn, 'SELECT COUNT(*) AS cnt FROM milestones WHERE created_at >= DATE_SUB(CURDATE(), INTERVAL ' . ($rangeDays * 2) . " DAY) AND created_at < DATE_SUB(CURDATE(), INTERVAL {$rangeDays} DAY)");
$kpiTotalMilestones = qi($conn, 'SELECT COUNT(*) AS cnt FROM milestones');

$kpiRangeReviews = qi($conn, "SELECT COUNT(*) AS cnt FROM reviews WHERE created_at >= DATE_SUB(CURDATE(), INTERVAL {$rangeDays} DAY)");
$kpiRangeReviewsPrev = qi($conn, 'SELECT COUNT(*) AS cnt FROM reviews WHERE created_at >= DATE_SUB(CURDATE(), INTERVAL ' . ($rangeDays * 2) . " DAY) AND created_at < DATE_SUB(CURDATE(), INTERVAL {$rangeDays} DAY)");
$kpiTotalReviews = qi($conn, 'SELECT COUNT(*) AS cnt FROM reviews');

// ── Financial Overview ───────────────────────────────────────────────
$kpiRangeRevenue = (float) q($conn, "SELECT COALESCE(SUM(platform_fee),0) AS cnt FROM payments WHERE status='completed' AND created_at >= DATE_SUB(CURDATE(), INTERVAL {$rangeDays} DAY)")['cnt'];
$kpiRangeRevenuePrev = (float) q($conn, "SELECT COALESCE(SUM(platform_fee),0) AS cnt FROM payments WHERE status='completed' AND created_at >= DATE_SUB(CURDATE(), INTERVAL " . ($rangeDays * 2) . " DAY) AND created_at < DATE_SUB(CURDATE(), INTERVAL {$rangeDays} DAY)")['cnt'];
$kpiRevenueTotal = (float) q($conn, "SELECT COALESCE(SUM(platform_fee),0) AS cnt FROM payments WHERE status='completed'")['cnt'];

$kpiRangeEscrow = (float) q($conn, "SELECT COALESCE(SUM(amount),0) AS cnt FROM milestones WHERE status='funded_in_escrow' AND created_at >= DATE_SUB(CURDATE(), INTERVAL {$rangeDays} DAY)")['cnt'];
$kpiRangeEscrowPrev = (float) q($conn, "SELECT COALESCE(SUM(amount),0) AS cnt FROM milestones WHERE status='funded_in_escrow' AND created_at >= DATE_SUB(CURDATE(), INTERVAL " . ($rangeDays * 2) . " DAY) AND created_at < DATE_SUB(CURDATE(), INTERVAL {$rangeDays} DAY)")['cnt'];
$kpiEscrowTotal = (float) q($conn, "SELECT COALESCE(SUM(amount),0) AS cnt FROM milestones WHERE status='funded_in_escrow'")['cnt'];

$kpiRangeTopups = (float) q($conn, "SELECT COALESCE(SUM(amount),0) AS cnt FROM wallet_transactions WHERE type='topup' AND created_at >= DATE_SUB(CURDATE(), INTERVAL {$rangeDays} DAY)")['cnt'];
$kpiRangeTopupsPrev = (float) q($conn, "SELECT COALESCE(SUM(amount),0) AS cnt FROM wallet_transactions WHERE type='topup' AND created_at >= DATE_SUB(CURDATE(), INTERVAL " . ($rangeDays * 2) . " DAY) AND created_at < DATE_SUB(CURDATE(), INTERVAL {$rangeDays} DAY)")['cnt'];
$kpiTopupsTotal = (float) q($conn, "SELECT COALESCE(SUM(amount),0) AS cnt FROM wallet_transactions WHERE type='topup'")['cnt'];

$kpiRangeCommission = $kpiRangeRevenue;
$kpiRangeCommissionPrev = $kpiRangeRevenuePrev;
$kpiCommissionTotal = $kpiRevenueTotal;

// ── Sparklines (last 7 days) ────────────────────────────────────────
function spark(mysqli $c, string $table, string $col = 'created_at'): array
{
    $r = $c->query("SELECT DATE($col) AS d, COUNT(*) AS c FROM $table WHERE $col >= DATE_SUB(CURDATE(), INTERVAL 6 DAY) GROUP BY d ORDER BY d");
    $map = [];
    while ($row = $r->fetch_assoc())
        $map[$row['d']] = (float) $row['c'];
    $out = [];
    for ($i = 6; $i >= 0; $i--) {
        $d = date('Y-m-d', strtotime("-{$i} days"));
        $out[] = $map[$d] ?? 0;
    }
    return $out;
}

function sparkCustom(mysqli $c, string $sql): array
{
    $r = $c->query($sql);
    $map = [];
    while ($row = $r->fetch_assoc())
        $map[$row['d']] = (float) $row['c'];
    $out = [];
    for ($i = 6; $i >= 0; $i--) {
        $d = date('Y-m-d', strtotime("-{$i} days"));
        $out[] = $map[$d] ?? 0;
    }
    return $out;
}

$sReg = spark($conn, 'users');
$sLogin = sparkCustom($conn, "SELECT DATE(created_at) AS d, COUNT(DISTINCT user_id) AS c FROM user_behavior_logs WHERE action_type='login' AND created_at >= DATE_SUB(CURDATE(), INTERVAL 6 DAY) GROUP BY d ORDER BY d");
$sJobs = spark($conn, 'jobs');
$sContracts = spark($conn, 'contracts');
$sMile = spark($conn, 'milestones');
$sRev = sparkCustom($conn, "SELECT DATE(created_at) AS d, COALESCE(SUM(platform_fee),0) AS c FROM payments WHERE status='completed' AND created_at >= DATE_SUB(CURDATE(), INTERVAL 6 DAY) GROUP BY d ORDER BY d");
$sEsc = sparkCustom($conn, "SELECT DATE(created_at) AS d, COALESCE(SUM(amount),0) AS c FROM milestones WHERE status='funded_in_escrow' AND created_at >= DATE_SUB(CURDATE(), INTERVAL 6 DAY) GROUP BY d ORDER BY d");
$sTop = sparkCustom($conn, "SELECT DATE(created_at) AS d, COALESCE(SUM(amount),0) AS c FROM wallet_transactions WHERE type='topup' AND created_at >= DATE_SUB(CURDATE(), INTERVAL 6 DAY) GROUP BY d ORDER BY d");
$sComm = $sRev;
$sRev2 = $sRev;

// ══════════════════════════════════════════════════════════════════════
// KPI CARD GROUPS
// ══════════════════════════════════════════════════════════════════════

function pctChange(int|float $today, int|float $yest): array
{
    $change = $yest > 0 ? round((($today - $yest) / $yest) * 100, 1) : ($today > 0 ? 100.0 : 0.0);
    return ['change' => $change, 'up' => $change >= 0];
}

$pc1 = pctChange($kpiRangeRegistrations, $kpiRangeRegistrationsPrev);
$pc2 = pctChange($kpiRangeLogins, $kpiRangeLoginsPrev);
$pc3 = pctChange($kpiOnlineUsers, $kpiOnlineUsersY);
$pc4 = pctChange($kpiRangeJobs, $kpiRangeJobsPrev);
$pc5 = pctChange($kpiRangeContracts, $kpiRangeContractsPrev);
$pc6 = pctChange($kpiRangeMilestones, $kpiRangeMilestonesPrev);
$pc7 = pctChange($kpiRangeReviews, $kpiRangeReviewsPrev);
$pc8 = pctChange($kpiRangeRevenue, $kpiRangeRevenuePrev);
$pc9 = pctChange($kpiRangeEscrow, $kpiRangeEscrowPrev);
$pc10 = pctChange($kpiRangeTopups, $kpiRangeTopupsPrev);
$pc11 = pctChange($kpiRangeCommission, $kpiRangeCommissionPrev);

$kpiGroups = [
    [
        'title' => 'User Activity',
        'icon' => 'users',
        'color' => 'blue',
        'cards' => [
            ['label' => 'Registrations', 'value' => $kpiTotalUsers, 'today' => $kpiRangeRegistrations, 'pc' => $pc1, 'color' => '#3b82f6', 'icon' => 'user-plus', 'spark' => $sReg, 'fmt' => 'num'],
            ['label' => 'Logins', 'value' => $kpiRangeLogins, 'today' => $kpiRangeLogins, 'pc' => $pc2, 'color' => '#6366f1', 'icon' => 'log-in', 'spark' => $sLogin, 'fmt' => 'num'],
            ['label' => 'Online Users', 'value' => $kpiOnlineUsers, 'today' => $kpiOnlineUsers, 'pc' => $pc3, 'color' => '#10b981', 'icon' => 'circle', 'spark' => null, 'fmt' => 'num', 'dot' => true],
        ]
    ],
    [
        'title' => 'Marketplace Activity',
        'icon' => 'briefcase',
        'color' => 'cyan',
        'cards' => [
            ['label' => 'Jobs Posted', 'value' => $kpiTotalJobs, 'today' => $kpiRangeJobs, 'pc' => $pc4, 'color' => '#06b6d4', 'icon' => 'briefcase', 'spark' => $sJobs, 'fmt' => 'num'],
            ['label' => 'Contracts Created', 'value' => $kpiTotalContracts, 'today' => $kpiRangeContracts, 'pc' => $pc5, 'color' => '#8b5cf6', 'icon' => 'file-text', 'spark' => $sContracts, 'fmt' => 'num'],
            ['label' => 'Milestones', 'value' => $kpiTotalMilestones, 'today' => $kpiRangeMilestones, 'pc' => $pc6, 'color' => '#f59e0b', 'icon' => 'flag', 'spark' => $sMile, 'fmt' => 'num'],
            ['label' => 'Reviews', 'value' => $kpiTotalReviews, 'today' => $kpiRangeReviews, 'pc' => $pc7, 'color' => '#f97316', 'icon' => 'star', 'spark' => null, 'fmt' => 'num'],
        ]
    ],
    [
        'title' => 'Financial Overview',
        'icon' => 'trending-up',
        'color' => 'emerald',
        'cards' => [
            ['label' => 'Revenue', 'value' => $kpiRevenueTotal, 'today' => $kpiRangeRevenue, 'pc' => $pc8, 'color' => '#10b981', 'icon' => 'dollar-sign', 'spark' => $sRev, 'fmt' => 'cur'],
            ['label' => 'Escrow', 'value' => $kpiEscrowTotal, 'today' => $kpiRangeEscrow, 'pc' => $pc9, 'color' => '#f59e0b', 'icon' => 'shield', 'spark' => $sEsc, 'fmt' => 'cur'],
            ['label' => 'Wallet Top-ups', 'value' => $kpiTopupsTotal, 'today' => $kpiRangeTopups, 'pc' => $pc10, 'color' => '#14b8a6', 'icon' => 'wallet', 'spark' => $sTop, 'fmt' => 'cur'],
            ['label' => 'Commission', 'value' => $kpiCommissionTotal, 'today' => $kpiRangeCommission, 'pc' => $pc11, 'color' => '#8b5cf6', 'icon' => 'percent', 'spark' => $sComm, 'fmt' => 'cur'],
        ]
    ],
];

// ── Top Clients (for Performance Rankings) ──────────────────────────
$r = $conn->query("SELECT u.name, COUNT(j.id) AS cnt FROM users u JOIN jobs j ON j.client_id=u.id WHERE u.role='client' GROUP BY u.id ORDER BY cnt DESC LIMIT 1");
$topClientRow = $r->fetch_assoc();
$topClientName = $topClientRow['name'] ?? '—';
$topClientJobs = (int) ($topClientRow['cnt'] ?? 0);

$r = $conn->query("SELECT u.name, COUNT(c.id) AS cnt FROM users u JOIN contracts c ON c.freelancer_id=u.id AND c.status='completed' WHERE u.role='freelancer' GROUP BY u.id ORDER BY cnt DESC LIMIT 1");
$topFlRow = $r->fetch_assoc();
$topFlName = $topFlRow['name'] ?? '—';
$topFlContracts = (int) ($topFlRow['cnt'] ?? 0);

$r = $conn->query('SELECT category, COUNT(*) AS cnt FROM jobs GROUP BY category ORDER BY cnt DESC LIMIT 1');
$topCatRow = $r->fetch_assoc();
$topCatName = $topCatRow['category'] ?? '—';
$topCatJobs = (int) ($topCatRow['cnt'] ?? 0);

// ── Platform Health ─────────────────────────────────────────────────
$healthCards = [
    ['label' => 'Active Users', 'value' => qi($conn, "SELECT COUNT(*) AS cnt FROM users WHERE status='active'"), 'color' => '#10b981', 'icon' => 'user-check'],
    ['label' => 'Flagged Users', 'value' => qi($conn, "SELECT COUNT(*) AS cnt FROM users WHERE status='flagged'"), 'color' => '#f59e0b', 'icon' => 'user-x'],
    ['label' => 'Open Jobs', 'value' => qi($conn, "SELECT COUNT(*) AS cnt FROM jobs WHERE status='open'"), 'color' => '#3b82f6', 'icon' => 'briefcase'],
    ['label' => 'Active Contracts', 'value' => qi($conn, "SELECT COUNT(*) AS cnt FROM contracts WHERE status='active'"), 'color' => '#8b5cf6', 'icon' => 'file-text'],
    ['label' => 'Completed Contracts', 'value' => qi($conn, "SELECT COUNT(*) AS cnt FROM contracts WHERE status='completed'"), 'color' => '#10b981', 'icon' => 'circle-check'],
    ['label' => 'Pending Payments', 'value' => qi($conn, "SELECT COUNT(*) AS cnt FROM payments WHERE status='pending'"), 'color' => '#f59e0b', 'icon' => 'hourglass'],
];

// ══════════════════════════════════════════════════════════════════════
// DAILY CHART QUERIES (dynamic range)
// ══════════════════════════════════════════════════════════════════════

$dayLabels = [];
$dayKeys = [];
for ($i = $rangeDays; $i >= 0; $i--) {
    $dayLabels[] = date('M j', strtotime("-{$i} days"));
    $dayKeys[] = date('Y-m-d', strtotime("-{$i} days"));
}

function dailyFill(mysqli $c, string $sql, array $keys): array
{
    $r = $c->query($sql);
    $map = [];
    while ($row = $r->fetch_assoc())
        $map[$row['d']] = (float) ($row['c'] ?? 0);
    $out = [];
    foreach ($keys as $k)
        $out[] = $map[$k] ?? 0;
    return $out;
}

$jsDayLabels = json_encode($dayLabels);
$jsUserClients = json_encode(dailyFill($conn, "SELECT DATE(created_at) AS d, SUM(role='client') AS c FROM users WHERE created_at>=DATE_SUB(CURDATE(),INTERVAL {$rangeDays} DAY) GROUP BY d ORDER BY d", $dayKeys));
$jsUserFreelancers = json_encode(dailyFill($conn, "SELECT DATE(created_at) AS d, SUM(role='freelancer') AS c FROM users WHERE created_at>=DATE_SUB(CURDATE(),INTERVAL {$rangeDays} DAY) GROUP BY d ORDER BY d", $dayKeys));
$jsJobTrend = json_encode(dailyFill($conn, "SELECT DATE(created_at) AS d, COUNT(*) AS c FROM jobs WHERE created_at>=DATE_SUB(CURDATE(),INTERVAL {$rangeDays} DAY) GROUP BY d ORDER BY d", $dayKeys));
$jsRevenueDaily = json_encode(dailyFill($conn, "SELECT DATE(created_at) AS d, COALESCE(SUM(platform_fee),0) AS c FROM payments WHERE status='completed' AND created_at>=DATE_SUB(CURDATE(),INTERVAL {$rangeDays} DAY) GROUP BY d ORDER BY d", $dayKeys));
$jsEscrowFunded = json_encode(dailyFill($conn, "SELECT DATE(created_at) AS d, COALESCE(SUM(CASE WHEN status='funded_in_escrow' THEN amount ELSE 0 END),0) AS c FROM milestones WHERE created_at>=DATE_SUB(CURDATE(),INTERVAL {$rangeDays} DAY) GROUP BY d ORDER BY d", $dayKeys));
$jsEscrowReleased = json_encode(dailyFill($conn, "SELECT DATE(created_at) AS d, COALESCE(SUM(CASE WHEN status='completed' THEN amount ELSE 0 END),0) AS c FROM milestones WHERE created_at>=DATE_SUB(CURDATE(),INTERVAL {$rangeDays} DAY) GROUP BY d ORDER BY d", $dayKeys));
$jsWalletDaily = json_encode(dailyFill($conn, "SELECT DATE(created_at) AS d, COALESCE(SUM(amount),0) AS c FROM wallet_transactions WHERE type='topup' AND created_at>=DATE_SUB(CURDATE(),INTERVAL {$rangeDays} DAY) GROUP BY d ORDER BY d", $dayKeys));
$jsContractsActive = json_encode(dailyFill($conn, "SELECT DATE(created_at) AS d, SUM(status='active') AS c FROM contracts WHERE created_at>=DATE_SUB(CURDATE(),INTERVAL {$rangeDays} DAY) GROUP BY d ORDER BY d", $dayKeys));
$jsContractsCompleted = json_encode(dailyFill($conn, "SELECT DATE(created_at) AS d, SUM(status='completed') AS c FROM contracts WHERE created_at>=DATE_SUB(CURDATE(),INTERVAL {$rangeDays} DAY) GROUP BY d ORDER BY d", $dayKeys));
$jsContractsCancelled = json_encode(dailyFill($conn, "SELECT DATE(created_at) AS d, SUM(status='cancelled') AS c FROM contracts WHERE created_at>=DATE_SUB(CURDATE(),INTERVAL {$rangeDays} DAY) GROUP BY d ORDER BY d", $dayKeys));
$jsMilestonesCreated = json_encode(dailyFill($conn, "SELECT DATE(created_at) AS d, SUM(status='pending') AS c FROM milestones WHERE created_at>=DATE_SUB(CURDATE(),INTERVAL {$rangeDays} DAY) GROUP BY d ORDER BY d", $dayKeys));
$jsMilestonesFunded = json_encode(dailyFill($conn, "SELECT DATE(created_at) AS d, SUM(status='funded_in_escrow') AS c FROM milestones WHERE created_at>=DATE_SUB(CURDATE(),INTERVAL {$rangeDays} DAY) GROUP BY d ORDER BY d", $dayKeys));
$jsMilestonesSubmitted = json_encode(dailyFill($conn, "SELECT DATE(created_at) AS d, SUM(status='submitted') AS c FROM milestones WHERE created_at>=DATE_SUB(CURDATE(),INTERVAL {$rangeDays} DAY) GROUP BY d ORDER BY d", $dayKeys));
$jsMilestonesApproved = json_encode(dailyFill($conn, "SELECT DATE(created_at) AS d, SUM(status='completed') AS c FROM milestones WHERE created_at>=DATE_SUB(CURDATE(),INTERVAL {$rangeDays} DAY) GROUP BY d ORDER BY d", $dayKeys));
$jsMilestonesReleased = json_encode(dailyFill($conn, "SELECT DATE(created_at) AS d, SUM(status='released') AS c FROM milestones WHERE created_at>=DATE_SUB(CURDATE(),INTERVAL {$rangeDays} DAY) GROUP BY d ORDER BY d", $dayKeys));

$r = $conn->query("SELECT u.name, COUNT(j.id) AS job_count FROM users u JOIN jobs j ON j.client_id=u.id WHERE u.role='client' GROUP BY u.id ORDER BY job_count DESC LIMIT 10");
$topClients = [];
while ($row = $r->fetch_assoc())
    $topClients[] = $row;
$r = $conn->query("SELECT u.name, COUNT(c.id) AS contracts_done FROM users u JOIN contracts c ON c.freelancer_id=u.id AND c.status='completed' WHERE u.role='freelancer' GROUP BY u.id ORDER BY contracts_done DESC LIMIT 10");
$topFreelancers = [];
while ($row = $r->fetch_assoc())
    $topFreelancers[] = $row;

$conn->close();

$jsTopClientsNames = json_encode(array_column($topClients, 'name'));
$jsTopClientsJobs = json_encode(array_map('intval', array_column($topClients, 'job_count')));
$jsTopFreelancersNames = json_encode(array_column($topFreelancers, 'name'));
$jsTopFreelancersContracts = json_encode(array_map('intval', array_column($topFreelancers, 'contracts_done')));

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
$pageTitle = 'Platform Analytics';
$pageSubtitle = 'Trends & insights for the last 30 days';
$activePage = 'analytics';
$user = ['name' => $adminName, 'profile_image' => $adminUser['profile_image'] ?? null];
$unreadCount = 0;
$profileLink = 'profile.php';
require_once __DIR__ . '/../components/layout_start.php';
?>
<style>
    /* ── Analytics Dashboard Styles ─────────────────────────────────────── */
    .analytics-kpi {
        transition: transform .15s ease, box-shadow .15s ease;
    }

    .analytics-kpi:hover {
        transform: translateY(-2px);
        box-shadow: 0 6px 20px rgba(0, 0, 0, .05);
    }

    .dark .analytics-kpi:hover {
        box-shadow: 0 6px 20px rgba(0, 0, 0, .2);
    }

    .chart-tab {
        transition: background .15s, color .15s, box-shadow .15s;
    }

    .chart-tab:hover {
        background: #F3F4F6;
        color: #111827;
    }

    .chart-tab.active {
        background: #111827;
        color: #fff;
        box-shadow: 0 1px 3px rgba(0, 0, 0, .12);
    }

    .dark .chart-tab:hover {
        background: #475569;
        color: #f1f5f9;
    }

    .dark .chart-tab.active {
        background: #3B82F6;
        color: #fff;
        box-shadow: 0 1px 3px rgba(59, 130, 246, .3);
    }

    .health-row {
        transition: background .12s;
    }

    .health-row:hover {
        background: #F9FAFB;
    }

    .dark .health-row:hover {
        background: rgba(51, 65, 85, .2);
    }

    .performer-row {
        transition: background .12s;
    }

    .performer-row:hover {
        background: #F9FAFB;
    }

    .dark .performer-row:hover {
        background: rgba(51, 65, 85, .2);
    }

    .date-btn {
        transition: background .12s, color .12s;
    }

    .date-btn:hover {
        background: #E5E7EB;
        color: #111827;
    }

    .date-btn.active {
        background: #111827;
        color: #fff;
    }

    .dark .date-btn:hover {
        background: #475569;
        color: #f1f5f9;
    }

    .dark .date-btn.active {
        background: #3B82F6;
        color: #fff;
    }
</style>

<!-- ═══════════════════════════════════════════════════════════════════════
     SECTION 1 — HEADER & GLOBAL DATE RANGE BAR
     ═══════════════════════════════════════════════════════════════════════ -->
<div class="flex items-center justify-between mb-6 flex-wrap gap-3">
    <div>
        <h1 class="text-xl font-bold text-gray-900 dark:text-white tracking-tight">Platform Analytics</h1>
        <p class="text-gray-500 dark:text-slate-400 text-xs mt-0.5">Performance overview &amp; insights — <?= $rangeLabel ?></p>
    </div>
    <div class="flex items-center gap-2 flex-wrap">
        <!-- Date Range Buttons -->
        <div class="inline-flex items-center bg-gray-100 dark:bg-slate-700 rounded-lg p-0.5">
            <a href="?range=today" class="date-btn no-underline px-3 py-1.5 rounded-md text-[11px] font-semibold <?= $range === 'today' ? 'active' : 'text-gray-600 dark:text-slate-400' ?>">Today</a>
            <a href="?range=7d" class="date-btn no-underline px-3 py-1.5 rounded-md text-[11px] font-semibold <?= $range === '7d' ? 'active' : 'text-gray-600 dark:text-slate-400' ?>">Last 7 Days</a>
            <a href="?range=30d" class="date-btn no-underline px-3 py-1.5 rounded-md text-[11px] font-semibold <?= $range === '30d' ? 'active' : 'text-gray-600 dark:text-slate-400' ?>">Last 30 Days</a>
        </div>
        <!-- Export PDF -->
        <button class="inline-flex items-center gap-2 px-3 py-1.5 text-xs font-semibold text-gray-700 bg-white border border-gray-300 rounded-lg hover:bg-gray-50">
            <svg class="w-4 h-4 text-gray-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4" />
            </svg>
            Export PDF
        </button>
    </div>
</div>

<!-- ═══════════════════════════════════════════════════════════════════════
     SECTION 2 — TOP METRIC CARDS (5-Card Row)
     ═══════════════════════════════════════════════════════════════════════ -->
<?php
$pcRevenue = pctChange($kpiRangeRevenue, $kpiRangeRevenuePrev);
$pcUsers = pctChange($kpiOnlineUsers, $kpiOnlineUsersY);
$pcJobs = pctChange($kpiRangeJobs, $kpiRangeJobsPrev);
$pcContracts = pctChange($kpiRangeContracts, $kpiRangeContractsPrev);
$pcEscrow = pctChange($kpiRangeEscrow, $kpiRangeEscrowPrev);

$topMetrics = [
    ['label' => 'Gross Revenue', 'value' => '$' . number_format($kpiRevenueTotal, 2), 'pc' => $pcRevenue, 'icon' => 'dollar-sign', 'color' => '#10B981', 'bg' => '#ECFDF5'],
    ['label' => 'Active Users', 'value' => number_format($kpiTotalUsers), 'pc' => $pcUsers, 'icon' => 'users', 'color' => '#3B82F6', 'bg' => '#EFF6FF'],
    ['label' => 'Open Jobs', 'value' => number_format($healthCards[2]['value']), 'pc' => $pcJobs, 'icon' => 'briefcase', 'color' => '#06B6D4', 'bg' => '#ECFEFF'],
    ['label' => 'Contracts Created', 'value' => number_format($kpiTotalContracts), 'pc' => $pcContracts, 'icon' => 'file-text', 'color' => '#8B5CF6', 'bg' => '#F5F3FF'],
    ['label' => 'Escrow Balance', 'value' => '$' . number_format($kpiEscrowTotal, 2), 'pc' => $pcEscrow, 'icon' => 'shield', 'color' => '#F59E0B', 'bg' => '#FFFBEB'],
];
?>

<div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-5 gap-4 mb-6">
    <?php
    foreach ($topMetrics as $mi => $m):
        $pc = $m['pc'];
        $trendColor = $pc['up'] ? 'text-emerald-600 dark:text-emerald-400' : 'text-red-500 dark:text-red-400';
        $trendIcon = $pc['up'] ? 'arrow-up' : 'arrow-down';
        $trendVal = abs($pc['change']);
        ?>
        <div class="analytics-kpi bg-white dark:bg-slate-800 rounded-xl border border-gray-200 dark:border-slate-700 p-4 flex flex-col justify-between">
            <div class="flex items-center justify-between mb-3">
                <span class="text-[11px] font-semibold uppercase tracking-wider text-gray-500 dark:text-slate-400"><?= $m['label'] ?></span>
                <span class="w-8 h-8 rounded-lg flex items-center justify-center flex-shrink-0" style="background:<?= $m['bg'] ?>">
                    <i data-lucide="<?= $m['icon'] ?>" class="text-[11px]" style="color:<?= $m['color'] ?>"></i>
                </span>
            </div>
            <p class="text-xl font-extrabold text-gray-900 dark:text-white leading-tight"><?= $m['value'] ?></p>
        </div>
    <?php endforeach; ?>
</div>

<!-- ═══════════════════════════════════════════════════════════════════════
     SECTION 3 — TWO-COLUMN DASHBOARD GRID
     ═══════════════════════════════════════════════════════════════════════ -->
<div class="grid grid-cols-1 lg:grid-cols-12 gap-5">

    <!-- ─── LEFT COLUMN (70%) ───────────────────────────────────────────── -->
    <div class="lg:col-span-8 space-y-5">

        <!-- Master Interactive Chart Card -->
        <div class="bg-white dark:bg-slate-800 rounded-2xl border border-gray-100 dark:border-slate-700 shadow-xs overflow-hidden">
            <!-- Card Header with Tabs -->
            <div class="px-5 py-3.5 border-b border-gray-100 dark:border-slate-700 flex items-center justify-between flex-wrap gap-2">
                <div class="flex items-center gap-1 bg-gray-100 dark:bg-slate-700/60 rounded-xl p-1">
                    <button onclick="switchChart('financial')" class="chart-tab active px-3.5 py-1.5 rounded-lg text-[11px] font-semibold flex items-center gap-1.5" data-chart="financial">
                        <span class="text-xs">💰</span> Financials
                    </button>
                    <button onclick="switchChart('marketplace')" class="chart-tab px-3.5 py-1.5 rounded-lg text-[11px] font-semibold text-gray-500 dark:text-slate-400 flex items-center gap-1.5" data-chart="marketplace">
                        <span class="text-xs">💼</span> Marketplace
                    </button>
                    <button onclick="switchChart('users')" class="chart-tab px-3.5 py-1.5 rounded-lg text-[11px] font-semibold text-gray-500 dark:text-slate-400 flex items-center gap-1.5" data-chart="users">
                        <span class="text-xs">👥</span> Users
                    </button>
                </div>
                <span class="text-[10px] font-medium text-gray-400 dark:text-slate-500"><?= $rangeLabel ?></span>
            </div>
            <!-- Chart Canvas -->
            <div class="p-5" style="height:340px">
                <canvas id="masterChart"></canvas>
            </div>
        </div>
    </div>

    <!-- ─── RIGHT COLUMN (30%) ──────────────────────────────────────────── -->
    <div class="lg:col-span-4 space-y-5">

        <!-- Platform Health & Top Performers — Combined Sidebar Card -->
        <div class="bg-white dark:bg-slate-800 rounded-2xl border border-gray-100 dark:border-slate-700 shadow-xs overflow-hidden">

            <!-- Health Section Header -->
            <div class="px-5 py-3.5 border-b border-gray-100 dark:border-slate-700">
                <h3 class="text-sm font-bold text-gray-900 dark:text-white flex items-center gap-2">
                    <span class="w-7 h-7 rounded-lg bg-emerald-50 dark:bg-emerald-900/20 flex items-center justify-center"><i data-lucide="heart-pulse" class="text-emerald-500 text-[11px]"></i></span>
                    Platform Health
                </h3>
            </div>

            <!-- Health Metrics List -->
            <div class="divide-y divide-gray-50 dark:divide-slate-700/50">
                <?php
                $healthShow = [
                    ['label' => 'Active Users', 'value' => $healthCards[0]['value'], 'color' => '#10B981', 'icon' => 'user-check'],
                    ['label' => 'Open Jobs', 'value' => $healthCards[2]['value'], 'color' => '#3B82F6', 'icon' => 'briefcase'],
                    ['label' => 'Active Contracts', 'value' => $healthCards[3]['value'], 'color' => '#8B5CF6', 'icon' => 'file-text'],
                    ['label' => 'Completed Contracts', 'value' => $healthCards[4]['value'], 'color' => '#10B981', 'icon' => 'circle-check'],
                    ['label' => 'Flagged Users', 'value' => $healthCards[1]['value'], 'color' => '#EF4444', 'icon' => 'user-x'],
                ];
                foreach ($healthShow as $h):
                    ?>
                    <div class="health-row flex items-center justify-between px-5 py-3">
                        <div class="flex items-center gap-2.5">
                            <span class="w-7 h-7 rounded-lg flex items-center justify-center flex-shrink-0" style="background:<?= $h['color'] ?>10">
                                <i data-lucide="<?= $h['icon'] ?>" class="text-[10px]" style="color:<?= $h['color'] ?>"></i>
                            </span>
                            <span class="text-xs font-medium text-gray-700 dark:text-slate-300"><?= $h['label'] ?></span>
                        </div>
                        <span class="inline-flex items-center justify-center min-w-[36px] px-2 py-1 rounded-lg text-xs font-bold" style="background:<?= $h['color'] ?>10;color:<?= $h['color'] ?>">
                            <?= number_format($h['value']) ?>
                        </span>
                    </div>
                <?php endforeach; ?>
            </div>

            <!-- Divider -->
            <div class="border-t border-gray-100 dark:border-slate-700"></div>

            <!-- Top Performers Section Header -->
            <div class="px-5 py-3.5 border-b border-gray-100 dark:border-slate-700">
                <h3 class="text-sm font-bold text-gray-900 dark:text-white flex items-center gap-2">
                    <span class="w-7 h-7 rounded-lg bg-amber-50 dark:bg-amber-900/20 flex items-center justify-center"><i data-lucide="trophy" class="text-amber-500 text-[11px]"></i></span>
                    Top Performers
                </h3>
            </div>

            <!-- Performers List -->
            <div class="divide-y divide-gray-50 dark:divide-slate-700/50">

                <!-- Top Client -->
                <div class="performer-row flex items-center gap-3 px-5 py-3.5">
                    <div class="w-9 h-9 rounded-full bg-gradient-to-br from-purple-500 to-indigo-600 flex items-center justify-center text-white font-bold text-xs flex-shrink-0 shadow-sm">
                        <?= strtoupper(substr($topClientName, 0, 1)) ?>
                    </div>
                    <div class="flex-1 min-w-0">
                        <p class="text-[10px] font-bold text-gray-400 dark:text-slate-500 uppercase tracking-wider">Top Client</p>
                        <p class="text-xs font-semibold text-gray-900 dark:text-white truncate mt-0.5"><?= htmlspecialchars($topClientName) ?></p>
                    </div>
                    <div class="text-right flex-shrink-0">
                        <p class="text-sm font-extrabold text-gray-900 dark:text-white leading-none"><?= $topClientJobs ?></p>
                        <p class="text-[10px] text-gray-400 dark:text-slate-500 mt-0.5">jobs</p>
                    </div>
                </div>

                <!-- Top Freelancer -->
                <div class="performer-row flex items-center gap-3 px-5 py-3.5">
                    <div class="w-9 h-9 rounded-full bg-gradient-to-br from-orange-500 to-red-500 flex items-center justify-center text-white font-bold text-xs flex-shrink-0 shadow-sm">
                        <?= strtoupper(substr($topFlName, 0, 1)) ?>
                    </div>
                    <div class="flex-1 min-w-0">
                        <p class="text-[10px] font-bold text-gray-400 dark:text-slate-500 uppercase tracking-wider">Top Freelancer</p>
                        <p class="text-xs font-semibold text-gray-900 dark:text-white truncate mt-0.5"><?= htmlspecialchars($topFlName) ?></p>
                    </div>
                    <div class="text-right flex-shrink-0">
                        <p class="text-sm font-extrabold text-gray-900 dark:text-white leading-none"><?= $topFlContracts ?></p>
                        <p class="text-[10px] text-gray-400 dark:text-slate-500 mt-0.5">contracts</p>
                    </div>
                </div>

                <!-- Most Active Category -->
                <div class="performer-row flex items-center gap-3 px-5 py-3.5">
                    <div class="w-9 h-9 rounded-full bg-gradient-to-br from-cyan-500 to-blue-500 flex items-center justify-center text-white flex-shrink-0 shadow-sm">
                        <i data-lucide="folder" class="text-[11px]"></i>
                    </div>
                    <div class="flex-1 min-w-0">
                        <p class="text-[10px] font-bold text-gray-400 dark:text-slate-500 uppercase tracking-wider">Most Active Category</p>
                        <p class="text-xs font-semibold text-gray-900 dark:text-white truncate mt-0.5"><?= htmlspecialchars($topCatName) ?></p>
                    </div>
                    <div class="text-right flex-shrink-0">
                        <p class="text-sm font-extrabold text-gray-900 dark:text-white leading-none"><?= $topCatJobs ?></p>
                        <p class="text-[10px] text-gray-400 dark:text-slate-500 mt-0.5">jobs</p>
                    </div>
                </div>

            </div>
        </div>
    </div>
</div>

<script>
    document.addEventListener('DOMContentLoaded', function() {
        // ═══ THEME DETECTION ═══════════════════════════════════════════════
        var isDark = document.documentElement.classList.contains('dark') || window.matchMedia('(prefers-color-scheme: dark)').matches;
        var gridColor = isDark ? 'rgba(148,163,184,0.08)' : 'rgba(0,0,0,0.04)';
        var textColor = isDark ? '#94a3b8' : '#64748b';
        var tooltipBg = isDark ? '#1e293b' : '#ffffff';
        var tooltipBdr = isDark ? '#334155' : '#e2e8f0';

        // ═══ CHART.JS GLOBAL DEFAULTS ═════════════════════════════════════
        Chart.defaults.color = textColor;
        Chart.defaults.borderColor = gridColor;
        Chart.defaults.font.family = "'Inter', system-ui, sans-serif";
        Chart.defaults.font.size = 11;
        Chart.defaults.plugins.legend.labels.usePointStyle = true;
        Chart.defaults.plugins.legend.labels.pointStyleWidth = 8;
        Chart.defaults.plugins.legend.labels.padding = 16;
        Chart.defaults.plugins.tooltip.backgroundColor = tooltipBg;
        Chart.defaults.plugins.tooltip.titleColor = isDark ? '#f1f5f9' : '#1e293b';
        Chart.defaults.plugins.tooltip.bodyColor = isDark ? '#cbd5e1' : '#475569';
        Chart.defaults.plugins.tooltip.borderColor = tooltipBdr;
        Chart.defaults.plugins.tooltip.borderWidth = 1;
        Chart.defaults.plugins.tooltip.cornerRadius = 8;
        Chart.defaults.plugins.tooltip.padding = {
            top: 10,
            bottom: 10,
            left: 14,
            right: 14
        };
        Chart.defaults.elements.line.tension = 0.4;
        Chart.defaults.elements.point.radius = 0;
        Chart.defaults.elements.point.hoverRadius = 5;
        Chart.defaults.elements.point.hoverBorderWidth = 2;

        var dayLabels = <?= $jsDayLabels ?>;

        // ═══ CHART DATASETS ═══════════════════════════════════════════════
        var chartDefs = {
            financial: {
                labels: dayLabels,
                datasets: [{
                        label: 'Revenue',
                        data: <?= $jsRevenueDaily ?>,
                        borderColor: '#10b981',
                        backgroundColor: 'rgba(16,185,129,0.12)',
                        borderWidth: 2,
                        fill: true,
                        pointBackgroundColor: '#10b981'
                    },
                    {
                        label: 'Escrow Funded',
                        data: <?= $jsEscrowFunded ?>,
                        borderColor: '#f59e0b',
                        backgroundColor: 'rgba(245,158,11,0.10)',
                        borderWidth: 2,
                        fill: true,
                        pointBackgroundColor: '#f59e0b'
                    },
                    {
                        label: 'Wallet Top-ups',
                        data: <?= $jsWalletDaily ?>,
                        borderColor: '#14b8a6',
                        backgroundColor: 'rgba(20,184,166,0.10)',
                        borderWidth: 2,
                        fill: true,
                        pointBackgroundColor: '#14b8a6'
                    }
                ],
                yFmt: function(v) {
                    return '$' + v.toLocaleString();
                }
            },
            marketplace: {
                labels: dayLabels,
                datasets: [{
                        label: 'Jobs Posted',
                        data: <?= $jsJobTrend ?>,
                        borderColor: '#06b6d4',
                        backgroundColor: 'rgba(6,182,212,0.12)',
                        borderWidth: 2,
                        fill: true,
                        pointBackgroundColor: '#06b6d4'
                    },
                    {
                        label: 'Contracts Created',
                        data: <?= $jsContractsActive ?>,
                        borderColor: '#8b5cf6',
                        backgroundColor: 'rgba(139,92,246,0.10)',
                        borderWidth: 2,
                        fill: true,
                        pointBackgroundColor: '#8b5cf6'
                    },
                    {
                        label: 'Milestones Approved',
                        data: <?= $jsMilestonesApproved ?>,
                        borderColor: '#10b981',
                        backgroundColor: 'rgba(16,185,129,0.10)',
                        borderWidth: 2,
                        fill: true,
                        pointBackgroundColor: '#10b981'
                    }
                ],
                yFmt: null
            },
            users: {
                labels: dayLabels,
                datasets: [{
                        label: 'Daily Registrations',
                        data: <?= $jsUserClients ?>,
                        borderColor: '#6366f1',
                        backgroundColor: 'rgba(99,102,241,0.12)',
                        borderWidth: 2,
                        fill: true,
                        pointBackgroundColor: '#6366f1'
                    },
                    {
                        label: 'Active Logins',
                        data: <?= $jsUserFreelancers ?>,
                        borderColor: '#10b981',
                        backgroundColor: 'rgba(16,185,129,0.10)',
                        borderWidth: 2,
                        fill: true,
                        pointBackgroundColor: '#10b981'
                    }
                ],
                yFmt: null
            }
        };

        // ═══ MASTER CHART ═════════════════════════════════════════════════
        var masterChart = null;

        function renderMasterChart(key) {
            var el = document.getElementById('masterChart');
            if (!el) return;
            if (masterChart) masterChart.destroy();

            var def = chartDefs[key];

            var yScale = {
                beginAtZero: true,
                grid: {
                    color: 'rgba(229, 231, 235, 0.5)',
                    borderDash: [4, 4],
                    drawBorder: false
                },
                border: {
                    display: false
                },
                ticks: {
                    maxTicksLimit: 5,
                    callback: function(v) { return '$' + v.toLocaleString(); }
                }
            };
            if (def.yFmt) yScale.ticks.callback = def.yFmt;

            masterChart = new Chart(el, {
                type: 'line',
                data: {
                    labels: def.labels,
                    datasets: def.datasets
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    interaction: {
                        mode: 'index',
                        intersect: false
                    },
                    plugins: {
                        legend: {
                            position: 'top',
                            labels: {
                                usePointStyle: true,
                                pointStyleWidth: 8,
                                padding: 16
                            }
                        }
                    },
                    scales: {
                        y: yScale,
                        x: {
                            grid: {
                                display: false
                            },
                            ticks: {
                                maxTicksLimit: 10,
                                maxRotation: 0
                            }
                        }
                    }
                }
            });
        }

        // Render default tab
        renderMasterChart('financial');

        // ═══ TAB SWITCHER ═════════════════════════════════════════════════
        window.switchChart = function(key) {
            document.querySelectorAll('.chart-tab').forEach(function(btn) {
                btn.classList.remove('active');
                btn.classList.add('text-gray-500', 'dark:text-slate-400');
            });
            var activeBtn = document.querySelector('[data-chart="' + key + '"]');
            if (activeBtn) {
                activeBtn.classList.add('active');
                activeBtn.classList.remove('text-gray-500', 'dark:text-slate-400');
            }
            renderMasterChart(key);
        };
    });
</script>
<?php require_once __DIR__ . '/../components/layout_end.php'; ?>