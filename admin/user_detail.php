<?php

/** Admin User Detail — Profile, Wallet, Reviews, Jobs, Contracts, Activity, Actions */
require_once __DIR__ . '/../auth/auth.php';
require_role('admin');
require_once __DIR__ . '/../config/db.php';

$currentPage = 'users';
$userId = sanitize_int($_GET['id'] ?? 0);
if ($userId <= 0) {
    set_flash('error', 'Invalid user ID.');
    redirect('users.php');
}

$stmt = $conn->prepare('SELECT * FROM users WHERE id = ?');
$stmt->bind_param('i', $userId);
$stmt->execute();
$user = $stmt->get_result()->fetch_assoc();
$stmt->close();
if (!$user) {
    set_flash('error', 'User not found.');
    redirect('users.php');
}

// ── Role-specific data ──────────────────────────────────────────────
$clientData = null;
$freelancerData = null;
if ($user['role'] === 'client') {
    $cs = $conn->prepare('SELECT * FROM clients WHERE client_id = ?');
    $cs->bind_param('i', $userId);
    $cs->execute();
    $clientData = $cs->get_result()->fetch_assoc();
    $cs->close();
}
if ($user['role'] === 'freelancer') {
    $fs = $conn->prepare('SELECT * FROM freelancers WHERE user_id = ?');
    $fs->bind_param('i', $userId);
    $fs->execute();
    $freelancerData = $fs->get_result()->fetch_assoc();
    $fs->close();
}

// ── Activity logs ───────────────────────────────────────────────────
$as = $conn->prepare('SELECT action_type, ip_address, payload, created_at FROM user_behavior_logs WHERE user_id = ? ORDER BY created_at DESC LIMIT 20');
$as->bind_param('i', $userId);
$as->execute();
$activityResult = $as->get_result();
$as->close();

// ── Jobs ────────────────────────────────────────────────────────────
$jobsResult = null;
if ($user['role'] === 'client') {
    $js = $conn->prepare('SELECT id, title, budget, status, created_at FROM jobs WHERE client_id = ? ORDER BY created_at DESC LIMIT 10');
    $js->bind_param('i', $userId);
    $js->execute();
    $jobsResult = $js->get_result();
    $js->close();
}

// ── Proposals (freelancer) ──────────────────────────────────────────
$proposalsResult = null;
if ($user['role'] === 'freelancer' && $freelancerData) {
    $ps = $conn->prepare('SELECT p.id, p.amount, p.status, p.created_at, j.title AS job_title FROM proposals p JOIN jobs j ON p.job_id = j.id WHERE p.freelancer_id = ? ORDER BY p.created_at DESC LIMIT 10');
    $ps->bind_param('i', $freelancerData['id']);
    $ps->execute();
    $proposalsResult = $ps->get_result();
    $ps->close();
}

// ── Contracts ───────────────────────────────────────────────────────
$contractsResult = null;
if ($user['role'] === 'client') {
    $cs2 = $conn->prepare('SELECT c.id, c.total_budget, c.status, c.created_at, j.title AS job_title, u2.name AS other_name FROM contracts c JOIN jobs j ON c.job_id = j.id JOIN freelancers f ON c.freelancer_id = f.id JOIN users u2 ON f.user_id = u2.id WHERE c.client_id = ? ORDER BY c.created_at DESC LIMIT 10');
    $cs2->bind_param('i', $userId);
    $cs2->execute();
    $contractsResult = $cs2->get_result();
    $cs2->close();
} elseif ($user['role'] === 'freelancer') {
    $cs3 = $conn->prepare('SELECT c.id, c.total_budget, c.status, c.created_at, j.title AS job_title, u2.name AS other_name FROM contracts c JOIN jobs j ON c.job_id = j.id JOIN clients cl ON c.client_id = cl.client_id JOIN users u2 ON cl.client_id = u2.id WHERE c.freelancer_id = (SELECT id FROM freelancers WHERE user_id = ?) ORDER BY c.created_at DESC LIMIT 10');
    $cs3->bind_param('i', $userId);
    $cs3->execute();
    $contractsResult = $cs3->get_result();
    $cs3->close();
}

// ── Wallet transactions ─────────────────────────────────────────────
$walletResult = null;
$ws = $conn->prepare('SELECT type, amount, balance_after, description, reference_type, created_at FROM wallet_transactions WHERE user_id = ? ORDER BY created_at DESC LIMIT 20');
$ws->bind_param('i', $userId);
$ws->execute();
$walletResult = $ws->get_result();
$ws->close();

// ── Reviews (received) ──────────────────────────────────────────────
$reviewsReceived = null;
$rs = $conn->prepare('SELECT r.id, r.rating, r.comment, r.created_at, u.name AS reviewer_name, u.profile_image AS reviewer_image FROM reviews r JOIN users u ON r.reviewer_id = u.id WHERE r.reviewee_id = ? ORDER BY r.created_at DESC LIMIT 10');
$rs->bind_param('i', $userId);
$rs->execute();
$reviewsReceived = $rs->get_result();
$rs->close();

// ── Reviews (given) ─────────────────────────────────────────────────
$reviewsGiven = null;
$rg = $conn->prepare('SELECT r.id, r.rating, r.comment, r.created_at, u.name AS reviewee_name, u.profile_image AS reviewee_image FROM reviews r JOIN users u ON r.reviewee_id = u.id WHERE r.reviewer_id = ? ORDER BY r.created_at DESC LIMIT 10');
$rg->bind_param('i', $userId);
$rg->execute();
$reviewsGiven = $rg->get_result();
$rg->close();

// ── Avg rating ──────────────────────────────────────────────────────
$avgStmt = $conn->prepare('SELECT AVG(rating) AS avg_rating, COUNT(*) AS total_reviews FROM reviews WHERE reviewee_id = ?');
$avgStmt->bind_param('i', $userId);
$avgStmt->execute();
$avgRow = $avgStmt->get_result()->fetch_assoc();
$avgStmt->close();
$avgRating = round((float) ($avgRow['avg_rating'] ?? 0), 1);
$totalReviews = (int) ($avgRow['total_reviews'] ?? 0);

// ── Fraud score ─────────────────────────────────────────────────────
$fraudScore = 0;
if ($userId > 0) {
    $fscore = 0;

    $fq1 = $conn->prepare('SELECT COUNT(*) AS cnt FROM user_behavior_logs WHERE user_id = ? AND created_at >= DATE_SUB(NOW(), INTERVAL 1 MINUTE)');
    $fq1->bind_param('i', $userId);
    $fq1->execute();
    if ((int) $fq1->get_result()->fetch_assoc()['cnt'] > 10)
        $fscore += 20;
    $fq1->close();

    $fq2 = $conn->prepare('SELECT COUNT(DISTINCT ip_address) AS cnt FROM user_behavior_logs WHERE user_id = ? AND created_at >= DATE_SUB(NOW(), INTERVAL 24 HOUR)');
    $fq2->bind_param('i', $userId);
    $fq2->execute();
    if ((int) $fq2->get_result()->fetch_assoc()['cnt'] > 1)
        $fscore += 15;
    $fq2->close();

    $fq3 = $conn->prepare("SELECT COUNT(*) AS cnt FROM user_behavior_logs WHERE user_id = ? AND action_type = 'proposal_submit' AND created_at >= DATE_SUB(NOW(), INTERVAL 1 HOUR)");
    $fq3->bind_param('i', $userId);
    $fq3->execute();
    if ((int) $fq3->get_result()->fetch_assoc()['cnt'] > 5)
        $fscore += 25;
    $fq3->close();

    $fq4 = $conn->prepare("SELECT COUNT(*) AS cnt FROM user_behavior_logs WHERE user_id = ? AND action_type = 'login_failed' AND created_at >= DATE_SUB(NOW(), INTERVAL 24 HOUR)");
    $fq4->bind_param('i', $userId);
    $fq4->execute();
    $fq4Cnt = (int) $fq4->get_result()->fetch_assoc()['cnt'];
    $fq4->close();
    if ($fq4Cnt > 0)
        $fscore += min($fq4Cnt * 10, 30);

    $ftypes = ['spam', 'phishing', 'fake_review', 'payment_fraud', 'account_takeover', 'suspicious_download'];
    $fph = implode(',', array_fill(0, count($ftypes), '?'));
    $fq5 = $conn->prepare("SELECT COUNT(*) AS cnt FROM user_behavior_logs WHERE user_id = ? AND action_type IN ({$fph}) AND created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)");
    $fq5T = array_merge([$userId], $ftypes);
    $fq5->bind_param(str_repeat('s', count($fq5T)), ...$fq5T);
    $fq5->execute();
    $fscore += (int) $fq5->get_result()->fetch_assoc()['cnt'] * 10;
    $fq5->close();

    $fscore = min($fscore, 100);
    $fraudScore = $fscore;

    $fqUp = $conn->prepare('UPDATE users SET fraud_score = ? WHERE id = ?');
    $fqUp->bind_param('ii', $fscore, $userId);
    $fqUp->execute();
    $fqUp->close();
}

if ($fraudScore <= 30) {
    $fraudColor = 'text-emerald-500 dark:text-emerald-400';
    $fraudBg = 'bg-emerald-50 dark:bg-emerald-900/20';
    $fraudBar = 'bg-emerald-500';
    $fraudLabel = 'Low Risk';
} elseif ($fraudScore <= 60) {
    $fraudColor = 'text-amber-500 dark:text-amber-400';
    $fraudBg = 'bg-amber-50 dark:bg-amber-900/20';
    $fraudBar = 'bg-amber-500';
    $fraudLabel = 'Medium Risk';
} else {
    $fraudColor = 'text-red-500 dark:text-red-400';
    $fraudBg = 'bg-red-50 dark:bg-red-900/20';
    $fraudBar = 'bg-red-500';
    $fraudLabel = 'High Risk';
}

// ── Badge maps ──────────────────────────────────────────────────────
$roleColors = [
    'client' => 'bg-blue-100 text-blue-700 dark:bg-blue-900/30 dark:text-blue-400',
    'freelancer' => 'bg-emerald-100 text-emerald-700 dark:bg-emerald-900/30 dark:text-emerald-400',
    'admin' => 'bg-purple-100 text-purple-700 dark:bg-purple-900/30 dark:text-purple-400',
];
$statusColors = [
    'active' => 'bg-emerald-100 text-emerald-700 dark:bg-emerald-900/30 dark:text-emerald-400',
    'flagged' => 'bg-amber-100 text-amber-700 dark:bg-amber-900/30 dark:text-amber-400',
    'suspended' => 'bg-red-100 text-red-700 dark:bg-red-900/30 dark:text-red-400',
];
$jobStatusColors = [
    'open' => 'bg-emerald-100 text-emerald-700 dark:bg-emerald-900/30 dark:text-emerald-400',
    'in_progress' => 'bg-amber-100 text-amber-700 dark:bg-amber-900/30 dark:text-amber-400',
    'completed' => 'bg-blue-100 text-blue-700 dark:bg-blue-900/30 dark:text-blue-400',
    'closed' => 'bg-gray-100 text-gray-600 dark:bg-slate-700 dark:text-slate-400',
];
$proposalStatusColors = [
    'pending' => 'bg-amber-100 text-amber-700 dark:bg-amber-900/30 dark:text-amber-400',
    'accepted' => 'bg-emerald-100 text-emerald-700 dark:bg-emerald-900/30 dark:text-emerald-400',
    'rejected' => 'bg-red-100 text-red-700 dark:bg-red-900/30 dark:text-red-400',
    'withdrawn' => 'bg-gray-100 text-gray-600 dark:bg-slate-700 dark:text-slate-400',
];
$contractStatusColors = [
    'active' => 'bg-emerald-100 text-emerald-700 dark:bg-emerald-900/30 dark:text-emerald-400',
    'completed' => 'bg-blue-100 text-blue-700 dark:bg-blue-900/30 dark:text-blue-400',
    'pending' => 'bg-amber-100 text-amber-700 dark:bg-amber-900/30 dark:text-amber-400',
    'cancelled' => 'bg-gray-100 text-gray-600 dark:bg-slate-700 dark:text-slate-400',
    'disputed' => 'bg-red-100 text-red-700 dark:bg-red-900/30 dark:text-red-400',
];
$walletTypeColors = [
    'deposit' => 'bg-emerald-100 text-emerald-700 dark:bg-emerald-900/30 dark:text-emerald-400',
    'withdrawal' => 'bg-red-100 text-red-700 dark:bg-red-900/30 dark:text-red-400',
    'escrow_hold' => 'bg-amber-100 text-amber-700 dark:bg-amber-900/30 dark:text-amber-400',
    'escrow_release' => 'bg-blue-100 text-blue-700 dark:bg-blue-900/30 dark:text-blue-400',
    'refund' => 'bg-purple-100 text-purple-700 dark:bg-purple-900/30 dark:text-purple-400',
    'platform_fee' => 'bg-gray-100 text-gray-600 dark:bg-slate-700 dark:text-slate-400',
    'signup_bonus' => 'bg-cyan-100 text-cyan-700 dark:bg-cyan-900/30 dark:text-cyan-400',
];

$activityIcons = [
    'login' => ['icon' => 'log-in', 'color' => 'text-blue-500', 'bg' => 'bg-blue-100 dark:bg-blue-900/30'],
    'register' => ['icon' => 'user-plus', 'color' => 'text-emerald-500', 'bg' => 'bg-emerald-100 dark:bg-emerald-900/30'],
    'job_posted' => ['icon' => 'briefcase', 'color' => 'text-violet-500', 'bg' => 'bg-violet-100 dark:bg-violet-900/30'],
    'proposal_sent' => ['icon' => 'send', 'color' => 'text-cyan-500', 'bg' => 'bg-cyan-100 dark:bg-cyan-900/30'],
    'payment_made' => ['icon' => 'dollar-sign', 'color' => 'text-amber-500', 'bg' => 'bg-amber-100 dark:bg-amber-900/30'],
    'contract_create' => ['icon' => 'file-text', 'color' => 'text-indigo-500', 'bg' => 'bg-indigo-100 dark:bg-indigo-900/30'],
    'review_posted' => ['icon' => 'star', 'color' => 'text-yellow-500', 'bg' => 'bg-yellow-100 dark:bg-yellow-900/30'],
];
$defaultActivity = ['icon' => 'circle', 'color' => 'text-gray-400', 'bg' => 'bg-gray-100 dark:bg-slate-700'];

// ── Active tab ──────────────────────────────────────────────────────
$validTabs = ['overview', 'jobs', 'contracts', 'wallet', 'reviews', 'activity'];
$activeTab = $_GET['tab'] ?? 'overview';
if (!in_array($activeTab, $validTabs))
    $activeTab = 'overview';

$targetUser = $user;

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
$pageTitle = sanitize_string($targetUser['name']) . ' — User Profile';
$pageSubtitle = 'User Profile & Management';
$activePage = 'users';
$user = ['name' => $adminName, 'profile_image' => $_navUserRow['profile_image'] ?? null];
$unreadCount = 0;
$profileLink = 'profile.php';
require_once __DIR__ . '/../components/layout_start.php';
?>
    <style>
    .tab-btn{transition:all .15s}
    .tab-btn.active{color:#2563eb;border-color:#2563eb;font-weight:600}
    .tab-btn:not(.active):hover{color:#475569;background:rgba(0,0,0,.02)}
    .dark .tab-btn:not(.active):hover{color:#94a3b8;background:rgba(255,255,255,.03)}
    .star{transition:transform .1s}
    .star:hover{transform:scale(1.15)}
    .action-card{transition:all .2s}
    .action-card:hover{transform:translateY(-2px);box-shadow:0 8px 25px rgba(0,0,0,.08)}
    .dark .action-card:hover{box-shadow:0 8px 25px rgba(0,0,0,.3)}
    .fraud-bar{transition:width .6s ease}
    </style>

    <?php display_flash('success'); ?>
    <?php display_flash('error'); ?>

    <div class="bg-gray-50 dark:bg-slate-900 rounded-lg p-6">

    <!-- ═══ BACK LINK ══════════════════════════════════════════════════ -->
    <div class="flex items-center justify-between mb-6">
        <a href="users.php" class="inline-flex items-center gap-1.5 text-sm text-gray-500 dark:text-slate-400 hover:text-gray-700 dark:hover:text-slate-200 transition-colors">
            <i data-lucide="arrow-left" class="text-xs"></i> Back to Users
        </a>
    </div>

    <!-- ═══ IDENTITY + DETAILS GRID ════════════════════════════════════ -->
    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6 mb-6">

    <!-- ═══ IDENTITY CARD (LEFT 2/3) ═══════════════════════════════════ -->
    <div class="lg:col-span-2 bg-white dark:bg-slate-800 border border-gray-200 dark:border-slate-700 rounded-lg shadow-sm overflow-hidden fade-in">
        <div class="flex flex-col md:flex-row justify-between items-start md:items-center p-6 gap-6">
            <!-- Left: User Identity -->
            <div class="flex items-center gap-4">
                <?php
                $_ui = $targetUser['profile_image'] ?? '';
                $_ub = strtolower(basename($_ui));
                $_uv = $_ui !== '' && $_ui !== null && $_ub !== 'default.png' && $_ub !== 'profile.png';
                if ($_uv):
                ?>
                <img src="<?= sanitize_string(get_profile_image($_ui)) ?>" class="w-20 h-20 rounded-full object-cover border border-gray-200 dark:border-slate-600">
                <?php else:
                    $_uin = '';
                    foreach (explode(' ', trim($targetUser['name'] ?? '')) as $_w) { if ($_w !== '') $_uin .= strtoupper($_w[0]); }
                    $_uin = substr($_uin, 0, 2);
                ?>
                <div class="w-20 h-20 rounded-full bg-blue-100 text-blue-600 flex items-center justify-center font-semibold text-lg border border-gray-200 dark:border-slate-600"><?= $_uin ?></div>
                <?php endif; ?>
                <div class="flex flex-col">
                    <h2 class="text-xl font-bold text-gray-900 dark:text-white"><?= sanitize_string($targetUser['name']) ?></h2>
                    <p class="text-sm text-gray-500 dark:text-slate-400 mt-0.5"><?= sanitize_string($targetUser['email']) ?></p>
                    <div class="flex items-center gap-2 mt-2">
                        <span class="bg-gray-100 dark:bg-slate-700 text-gray-700 dark:text-slate-300 px-2.5 py-0.5 rounded text-xs font-medium"><?= sanitize_string(ucfirst($targetUser['role'])) ?></span>
                        <?php if ($targetUser['status'] === 'suspended'): ?>
                        <span class="bg-red-50 dark:bg-red-900/20 text-red-700 dark:text-red-400 border border-red-200 dark:border-red-800 px-2.5 py-0.5 rounded-full text-xs font-medium">Suspended</span>
                        <?php elseif ($targetUser['status'] === 'flagged'): ?>
                        <span class="bg-amber-50 dark:bg-amber-900/20 text-amber-700 dark:text-amber-400 border border-amber-200 dark:border-amber-800 px-2.5 py-0.5 rounded-full text-xs font-medium">Flagged</span>
                        <?php else: ?>
                        <span class="bg-emerald-50 dark:bg-emerald-900/20 text-emerald-700 dark:text-emerald-400 border border-emerald-200 dark:border-emerald-800 px-2.5 py-0.5 rounded-full text-xs font-medium">Active</span>
                        <?php endif; ?>
                        <span class="flex items-center gap-1 text-sm text-yellow-500 dark:text-yellow-400 font-medium bg-yellow-50 dark:bg-yellow-900/20 px-2 py-0.5 rounded border border-yellow-100 dark:border-yellow-800">
                            <i data-lucide="star" class="text-xs"></i> <?= $avgRating ?>
                        </span>
                    </div>
                </div>
            </div>

            <!-- Right: Admin Actions -->
            <div class="flex gap-3 w-full md:w-auto">
                <?php if ($targetUser['status'] !== 'active'): ?>
                <form method="POST" action="user_action.php">
                    <?= csrf_field() ?>
                    <input type="hidden" name="action" value="activate">
                    <input type="hidden" name="user_id" value="<?= $userId ?>">
                    <button type="submit" class="px-4 py-2 text-sm font-medium text-emerald-600 dark:text-emerald-400 bg-white dark:bg-slate-800 border border-emerald-600 dark:border-emerald-600 rounded-lg hover:bg-emerald-50 dark:hover:bg-emerald-900/20 transition-colors flex items-center gap-2">
                        <i data-lucide="check" class="text-xs"></i> Activate
                    </button>
                </form>
                <?php else: ?>
                <form method="POST" action="user_action.php" onsubmit="return confirm('Suspend this user?')">
                    <?= csrf_field() ?>
                    <input type="hidden" name="action" value="suspend">
                    <input type="hidden" name="user_id" value="<?= $userId ?>">
                    <button type="submit" class="px-4 py-2 text-sm font-medium text-amber-700 dark:text-amber-400 bg-white dark:bg-slate-800 border border-amber-300 dark:border-amber-700 rounded-lg hover:bg-amber-50 dark:hover:bg-amber-900/20 transition-colors flex items-center gap-2">
                        <i data-lucide="ban" class="text-xs"></i> Suspend
                    </button>
                </form>
                <?php endif; ?>
                <?php if ((int) $userId !== (int) $_SESSION['user_id']): ?>
                <form method="POST" action="user_action.php" onsubmit="return confirm('DELETE this user? This cannot be undone.')">
                    <?= csrf_field() ?>
                    <input type="hidden" name="action" value="delete">
                    <input type="hidden" name="user_id" value="<?= $userId ?>">
                    <button type="submit" class="px-4 py-2 text-sm font-medium text-red-700 dark:text-red-400 bg-white dark:bg-slate-800 border border-red-300 dark:border-red-700 rounded-lg hover:bg-red-50 dark:hover:bg-red-900/20 transition-colors flex items-center gap-2">
                        <i data-lucide="trash-2" class="text-xs"></i> Delete
                    </button>
                </form>
                <?php endif; ?>
            </div>
        </div>

        <!-- KPI Row -->
        <div class="border-t border-gray-100 dark:border-slate-700"></div>
        <div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-5 gap-px bg-gray-100 dark:bg-slate-700">
            <div class="bg-white dark:bg-slate-800 p-4">
                <p class="text-xs font-semibold text-gray-400 dark:text-slate-500 uppercase tracking-wider">Phone</p>
                <p class="text-sm font-semibold text-gray-900 dark:text-white mt-1 text-left"><?= sanitize_string($targetUser['phone'] ?: 'N/A') ?></p>
            </div>
            <div class="bg-white dark:bg-slate-800 p-4">
                <p class="text-xs font-semibold text-gray-400 dark:text-slate-500 uppercase tracking-wider">Wallet</p>
                <p class="text-sm font-bold text-gray-900 dark:text-white mt-1 text-left"><?= format_currency((float) $targetUser['wallet_balance']) ?></p>
            </div>
            <div class="bg-white dark:bg-slate-800 p-4">
                <p class="text-xs font-semibold text-gray-400 dark:text-slate-500 uppercase tracking-wider">Joined</p>
                <p class="text-sm font-semibold text-gray-900 dark:text-white mt-1 text-left"><?= date('M j, Y', strtotime($targetUser['created_at'])) ?></p>
            </div>
            <div class="bg-white dark:bg-slate-800 p-4">
                <p class="text-xs font-semibold text-gray-400 dark:text-slate-500 uppercase tracking-wider">Fraud</p>
                <p class="text-sm font-bold <?= $fraudColor ?> mt-1 text-left"><?= $fraudScore ?>/100</p>
                <div class="w-full bg-gray-200 dark:bg-slate-600 rounded-full h-1.5 mt-1.5">
                    <div class="<?= $fraudBar ?> h-1.5 rounded-full fraud-bar" style="width:<?= $fraudScore ?>%"></div>
                </div>
            </div>
            <div class="bg-white dark:bg-slate-800 p-4">
                <p class="text-xs font-semibold text-gray-400 dark:text-slate-500 uppercase tracking-wider">Risk Level</p>
                <p class="text-sm font-bold <?= $fraudColor ?> mt-1 text-left"><?= $fraudLabel ?></p>
            </div>
        </div>
    </div>

    <!-- ═══ CLIENT DETAILS (RIGHT 1/3) ═════════════════════════════════ -->
    <?php if ($clientData): ?>
    <div class="lg:col-span-1 bg-white dark:bg-slate-800 border border-gray-200 dark:border-slate-700 rounded-lg shadow-sm p-6 fade-in" style="animation-delay:.05s">
        <h3 class="text-sm font-semibold text-gray-900 dark:text-white uppercase tracking-wider mb-5">Client Details</h3>
        <div class="flex flex-col text-sm">
            <?php if ($clientData['company_name']): ?>
            <div class="flex justify-between items-start py-3 border-b border-gray-50 dark:border-slate-700/50 last:border-0">
                <span class="text-gray-500 dark:text-slate-400 min-w-[120px]">Company</span>
                <span class="text-gray-900 dark:text-white font-medium text-right ml-4"><?= sanitize_string($clientData['company_name']) ?></span>
            </div>
            <?php endif; ?>
            <?php if (isset($clientData['total_spent']) && $clientData['total_spent'] > 0): ?>
            <div class="flex justify-between items-start py-3 border-b border-gray-50 dark:border-slate-700/50 last:border-0">
                <span class="text-gray-500 dark:text-slate-400 min-w-[120px]">Total Spent</span>
                <span class="font-semibold text-gray-900 dark:text-white text-right ml-4"><?= format_currency((float) $clientData['total_spent']) ?></span>
            </div>
            <?php endif; ?>
            <?php if ($clientData['industry']): ?>
            <div class="flex justify-between items-start py-3 border-b border-gray-50 dark:border-slate-700/50 last:border-0">
                <span class="text-gray-500 dark:text-slate-400 min-w-[120px]">Industry</span>
                <span class="text-gray-900 dark:text-white font-medium text-right ml-4"><?= sanitize_string($clientData['industry']) ?></span>
            </div>
            <?php endif; ?>
            <?php if ($clientData['company_size']): ?>
            <div class="flex justify-between items-start py-3 border-b border-gray-50 dark:border-slate-700/50 last:border-0">
                <span class="text-gray-500 dark:text-slate-400 min-w-[120px]">Company Size</span>
                <span class="text-gray-900 dark:text-white font-medium text-right ml-4"><?= sanitize_string($clientData['company_size']) ?></span>
            </div>
            <?php endif; ?>
            <?php if (!empty($clientData['tax_id'])): ?>
            <div class="flex justify-between items-start py-3 border-b border-gray-50 dark:border-slate-700/50 last:border-0">
                <span class="text-gray-500 dark:text-slate-400 min-w-[120px]">Tax ID / VAT</span>
                <span class="text-gray-900 dark:text-white font-medium text-right ml-4"><?= sanitize_string($clientData['tax_id']) ?></span>
            </div>
            <?php endif; ?>
            <?php if ($clientData['company_website']): ?>
            <div class="flex justify-between items-start py-3 last:border-0">
                <span class="text-gray-500 dark:text-slate-400 min-w-[120px]">Website</span>
                <a href="<?= sanitize_string($clientData['company_website']) ?>" target="_blank" class="text-emerald-600 dark:text-emerald-400 hover:underline text-right ml-4 truncate max-w-[160px]"><?= sanitize_string($clientData['company_website']) ?></a>
            </div>
            <?php endif; ?>
        </div>
    </div>
    <?php endif; ?>

    <!-- ═══ FREELANCER DETAILS (RIGHT 1/3) ═════════════════════════════ -->
    <?php if ($freelancerData): ?>
    <div class="lg:col-span-1 bg-white dark:bg-slate-800 border border-gray-200 dark:border-slate-700 rounded-lg shadow-sm p-6 fade-in" style="animation-delay:.05s">
        <h3 class="text-sm font-semibold text-gray-900 dark:text-white uppercase tracking-wider mb-5">Freelancer Details</h3>
        <div class="flex flex-col text-sm">
            <?php if ($freelancerData['title']): ?>
            <div class="flex justify-between items-start py-3 border-b border-gray-50 dark:border-slate-700/50 last:border-0">
                <span class="text-gray-500 dark:text-slate-400 min-w-[120px]">Title</span>
                <span class="text-gray-900 dark:text-white font-medium text-right ml-4"><?= sanitize_string($freelancerData['title']) ?></span>
            </div>
            <?php endif; ?>
            <?php if (isset($freelancerData['hourly_rate']) && $freelancerData['hourly_rate'] > 0): ?>
            <div class="flex justify-between items-start py-3 border-b border-gray-50 dark:border-slate-700/50 last:border-0">
                <span class="text-gray-500 dark:text-slate-400 min-w-[120px]">Hourly Rate</span>
                <span class="font-semibold text-gray-900 dark:text-white text-right ml-4"><?= format_currency((float) $freelancerData['hourly_rate']) ?></span>
            </div>
            <?php endif; ?>
            <?php if ($freelancerData['availability']): ?>
            <div class="flex justify-between items-start py-3 border-b border-gray-50 dark:border-slate-700/50 last:border-0">
                <span class="text-gray-500 dark:text-slate-400 min-w-[120px]">Availability</span>
                <span class="text-gray-900 dark:text-white font-medium text-right ml-4"><?= sanitize_string(ucfirst($freelancerData['availability'])) ?></span>
            </div>
            <?php endif; ?>
        </div>
    </div>
    <?php endif; ?>

    </div> <!-- close grid -->

    <!-- ═══ TAB NAVIGATION ═════════════════════════════════════════════ -->
    <?php
    $tabs = [
        ['key' => 'overview', 'label' => 'Overview', 'icon' => 'user'],
        ['key' => 'jobs', 'label' => 'Jobs', 'icon' => 'briefcase'],
        ['key' => 'contracts', 'label' => 'Contracts', 'icon' => 'file-text'],
        ['key' => 'wallet', 'label' => 'Wallet', 'icon' => 'wallet'],
        ['key' => 'reviews', 'label' => 'Reviews', 'icon' => 'star'],
        ['key' => 'activity', 'label' => 'Activity', 'icon' => 'history'],
    ];
    $baseTabUrl = 'user_detail.php?id=' . $userId;
    ?>
    <div class="border-b border-gray-200 dark:border-slate-700 mb-6 px-1">
        <div class="flex space-x-1 overflow-x-auto">
            <?php foreach ($tabs as $t): ?>
            <a href="<?= $baseTabUrl ?>&tab=<?= $t['key'] ?>"
               class="<?= $activeTab === $t['key']
        ? 'px-4 py-3 border-b-2 border-emerald-600 dark:border-emerald-500 text-emerald-600 dark:text-emerald-400 text-sm font-semibold flex items-center gap-2 bg-gray-50/50 dark:bg-slate-700/30 rounded-t-lg'
        : 'px-4 py-3 border-b-2 border-transparent text-gray-600 dark:text-slate-400 hover:text-gray-900 dark:hover:text-slate-200 hover:border-gray-300 dark:hover:border-slate-500 text-sm font-medium flex items-center gap-2 transition-all' ?>">
                <i data-lucide="<?= $t['icon'] ?>" class="text-xs"></i> <?= $t['label'] ?>
            </a>
            <?php endforeach; ?>
        </div>
    </div>

        <div class="px-3">
        <?php if ($activeTab === 'overview'): ?>
            <!-- ═══ OVERVIEW TAB ════════════════════════════════════════ -->
            <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
                <!-- Recent Jobs -->
                <?php if ($jobsResult && $jobsResult->num_rows > 0): ?>
                <div>
                    <h4 class="text-sm font-bold text-gray-900 dark:text-white mb-3"><i data-lucide="briefcase" class="text-blue-500 mr-1.5"></i>Recent Jobs</h4>
                    <div class="space-y-2">
                    <?php while ($job = $jobsResult->fetch_assoc()): ?>
                        <div class="flex items-center justify-between p-3 rounded-xl bg-gray-50 dark:bg-slate-700/50 hover:bg-gray-100 dark:hover:bg-slate-700 transition-colors">
                            <div class="min-w-0 flex-1">
                                <p class="text-sm font-medium text-gray-900 dark:text-white truncate"><?= decode_over_encoded($job['title']) ?></p>
                                <p class="text-xs text-gray-400 dark:text-slate-500"><?= time_ago($job['created_at']) ?></p>
                            </div>
                            <div class="flex items-center gap-2 ml-3">
                                <span class="inline-block px-2 py-0.5 rounded text-xs font-semibold <?= $jobStatusColors[$job['status']] ?? '' ?>"><?= sanitize_string(ucfirst(str_replace('_', ' ', $job['status']))) ?></span>
                                <span class="text-xs font-semibold text-gray-600 dark:text-slate-300"><?= format_currency((float) $job['budget']) ?></span>
                            </div>
                        </div>
                    <?php endwhile; ?>
                    </div>
                </div>
                <?php endif; ?>

                <!-- Recent Proposals -->
                <?php if ($proposalsResult && $proposalsResult->num_rows > 0): ?>
                <div>
                    <h4 class="text-sm font-bold text-gray-900 dark:text-white mb-3"><i data-lucide="send" class="text-cyan-500 mr-1.5"></i>Recent Proposals</h4>
                    <div class="space-y-2">
                    <?php while ($prop = $proposalsResult->fetch_assoc()): ?>
                        <div class="flex items-center justify-between p-3 rounded-xl bg-gray-50 dark:bg-slate-700/50 hover:bg-gray-100 dark:hover:bg-slate-700 transition-colors">
                            <div class="min-w-0 flex-1">
                                <p class="text-sm font-medium text-gray-900 dark:text-white truncate"><?= sanitize_string($prop['job_title']) ?></p>
                                <p class="text-xs text-gray-400 dark:text-slate-500"><?= time_ago($prop['created_at']) ?></p>
                            </div>
                            <div class="flex items-center gap-2 ml-3">
                                <span class="inline-block px-2 py-0.5 rounded text-xs font-semibold <?= $proposalStatusColors[$prop['status']] ?? '' ?>"><?= sanitize_string(ucfirst($prop['status'])) ?></span>
                                <span class="text-xs font-semibold text-gray-600 dark:text-slate-300"><?= format_currency((float) $prop['amount']) ?></span>
                            </div>
                        </div>
                    <?php endwhile; ?>
                    </div>
                </div>
                <?php endif; ?>

                <!-- Recent Contracts -->
                <?php if ($contractsResult && $contractsResult->num_rows > 0): ?>
                <div>
                    <h4 class="text-sm font-bold text-gray-900 dark:text-white mb-3"><i data-lucide="file-text" class="text-indigo-500 mr-1.5"></i>Recent Contracts</h4>
                    <div class="space-y-2">
                    <?php while ($c = $contractsResult->fetch_assoc()): ?>
                        <div class="flex items-center justify-between p-3 rounded-xl bg-gray-50 dark:bg-slate-700/50 hover:bg-gray-100 dark:hover:bg-slate-700 transition-colors">
                            <div class="min-w-0 flex-1">
                                <p class="text-sm font-medium text-gray-900 dark:text-white truncate"><?= sanitize_string($c['job_title']) ?></p>
                                <p class="text-xs text-gray-400 dark:text-slate-500">with <?= sanitize_string($c['other_name']) ?></p>
                            </div>
                            <div class="flex items-center gap-2 ml-3">
                                <span class="inline-block px-2 py-0.5 rounded text-xs font-semibold <?= $contractStatusColors[$c['status']] ?? '' ?>"><?= sanitize_string(ucfirst($c['status'])) ?></span>
                                <span class="text-xs font-semibold text-gray-600 dark:text-slate-300"><?= format_currency((float) $c['total_budget']) ?></span>
                            </div>
                        </div>
                    <?php endwhile; ?>
                    </div>
                </div>
                <?php endif; ?>

                <!-- Recent Reviews -->
                <?php if ($reviewsReceived && $reviewsReceived->num_rows > 0): ?>
                <div>
                    <h4 class="text-sm font-bold text-gray-900 dark:text-white mb-3"><i data-lucide="star" class="text-yellow-500 mr-1.5"></i>Recent Reviews</h4>
                    <div class="space-y-2">
                    <?php while ($rev = $reviewsReceived->fetch_assoc()): ?>
                        <div class="p-3 rounded-xl bg-gray-50 dark:bg-slate-700/50">
                            <div class="flex items-center gap-2 mb-1">
                                <div class="flex">
                                    <?php for ($i = 1; $i <= 5; $i++): ?>
                                    <i data-lucide="star" class="text-xs <?= $i <= $rev['rating'] ? 'text-yellow-400' : 'text-gray-200 dark:text-slate-600' ?>"></i>
                                    <?php endfor; ?>
                                </div>
                                <span class="text-xs text-gray-400 dark:text-slate-500">by <?= sanitize_string($rev['reviewer_name']) ?> &middot; <?= time_ago($rev['created_at']) ?></span>
                            </div>
                            <?php if ($rev['comment']): ?>
                            <p class="text-xs text-gray-600 dark:text-slate-400 mt-1"><?= sanitize_string(mb_substr($rev['comment'], 0, 120)) ?><?= mb_strlen($rev['comment']) > 120 ? '...' : '' ?></p>
                            <?php endif; ?>
                        </div>
                    <?php endwhile; ?>
                    </div>
                </div>
                <?php endif; ?>
            </div>

        <?php elseif ($activeTab === 'jobs'): ?>
            <!-- ═══ JOBS TAB ═══════════════════════════════════════════ -->
            <?php if ($targetUser['role'] === 'client' && $jobsResult && $jobsResult->num_rows > 0): ?>
            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead>
                        <tr class="border-b border-gray-100 dark:border-slate-700">
                            <th class="text-left pb-3 text-xs font-semibold text-gray-400 dark:text-slate-500 uppercase tracking-wider">Job</th>
                            <th class="text-left pb-3 text-xs font-semibold text-gray-400 dark:text-slate-500 uppercase tracking-wider">Status</th>
                            <th class="text-right pb-3 text-xs font-semibold text-gray-400 dark:text-slate-500 uppercase tracking-wider">Budget</th>
                            <th class="text-right pb-3 text-xs font-semibold text-gray-400 dark:text-slate-500 uppercase tracking-wider">Posted</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php while ($job = $jobsResult->fetch_assoc()): ?>
                        <tr class="border-b border-gray-50 dark:border-slate-700/50 last:border-0 hover:bg-gray-50/50 dark:hover:bg-slate-700/30 transition-colors">
                            <td class="py-3 font-medium text-gray-900 dark:text-white"><?= decode_over_encoded($job['title']) ?></td>
                            <td class="py-3"><span class="inline-block px-2.5 py-1 rounded-lg text-xs font-semibold <?= $jobStatusColors[$job['status']] ?? '' ?>"><?= sanitize_string(ucfirst(str_replace('_', ' ', $job['status']))) ?></span></td>
                            <td class="py-3 text-right font-semibold text-gray-700 dark:text-slate-300"><?= format_currency((float) $job['budget']) ?></td>
                            <td class="py-3 text-right text-gray-400 dark:text-slate-500 text-xs"><?= time_ago($job['created_at']) ?></td>
                        </tr>
                    <?php endwhile; ?>
                    </tbody>
                </table>
            </div>
            <?php elseif ($targetUser['role'] === 'freelancer' && $proposalsResult && $proposalsResult->num_rows > 0): ?>
            <h4 class="text-sm font-bold text-gray-900 dark:text-white mb-3">Proposals Submitted</h4>
            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead>
                        <tr class="border-b border-gray-100 dark:border-slate-700">
                            <th class="text-left pb-3 text-xs font-semibold text-gray-400 dark:text-slate-500 uppercase tracking-wider">Job</th>
                            <th class="text-left pb-3 text-xs font-semibold text-gray-400 dark:text-slate-500 uppercase tracking-wider">Status</th>
                            <th class="text-right pb-3 text-xs font-semibold text-gray-400 dark:text-slate-500 uppercase tracking-wider">Amount</th>
                            <th class="text-right pb-3 text-xs font-semibold text-gray-400 dark:text-slate-500 uppercase tracking-wider">Submitted</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php while ($prop = $proposalsResult->fetch_assoc()): ?>
                        <tr class="border-b border-gray-50 dark:border-slate-700/50 last:border-0 hover:bg-gray-50/50 dark:hover:bg-slate-700/30 transition-colors">
                            <td class="py-3 font-medium text-gray-900 dark:text-white"><?= sanitize_string($prop['job_title']) ?></td>
                            <td class="py-3"><span class="inline-block px-2.5 py-1 rounded-lg text-xs font-semibold <?= $proposalStatusColors[$prop['status']] ?? '' ?>"><?= sanitize_string(ucfirst($prop['status'])) ?></span></td>
                            <td class="py-3 text-right font-semibold text-gray-700 dark:text-slate-300"><?= format_currency((float) $prop['amount']) ?></td>
                            <td class="py-3 text-right text-gray-400 dark:text-slate-500 text-xs"><?= time_ago($prop['created_at']) ?></td>
                        </tr>
                    <?php endwhile; ?>
                    </tbody>
                </table>
            </div>
            <?php else: ?>
            <div class="text-center py-12">
                <div class="w-14 h-14 rounded-2xl bg-gray-100 dark:bg-slate-700 flex items-center justify-center mx-auto mb-3">
                    <i data-lucide="briefcase" class="text-xl text-gray-300 dark:text-slate-500"></i>
                </div>
                <p class="text-gray-500 dark:text-slate-400 text-sm">No jobs or proposals found</p>
            </div>
            <?php endif; ?>

        <?php elseif ($activeTab === 'contracts'): ?>
            <!-- ═══ CONTRACTS TAB ══════════════════════════════════════ -->
            <?php if ($contractsResult && $contractsResult->num_rows > 0): ?>
            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead>
                        <tr class="border-b border-gray-100 dark:border-slate-700">
                            <th class="text-left pb-3 text-xs font-semibold text-gray-400 dark:text-slate-500 uppercase tracking-wider">Job</th>
                            <th class="text-left pb-3 text-xs font-semibold text-gray-400 dark:text-slate-500 uppercase tracking-wider">With</th>
                            <th class="text-left pb-3 text-xs font-semibold text-gray-400 dark:text-slate-500 uppercase tracking-wider">Status</th>
                            <th class="text-right pb-3 text-xs font-semibold text-gray-400 dark:text-slate-500 uppercase tracking-wider">Budget</th>
                            <th class="text-right pb-3 text-xs font-semibold text-gray-400 dark:text-slate-500 uppercase tracking-wider">Created</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php while ($c = $contractsResult->fetch_assoc()): ?>
                        <tr class="border-b border-gray-50 dark:border-slate-700/50 last:border-0 hover:bg-gray-50/50 dark:hover:bg-slate-700/30 transition-colors">
                            <td class="py-3 font-medium text-gray-900 dark:text-white"><?= sanitize_string($c['job_title']) ?></td>
                            <td class="py-3 text-gray-600 dark:text-slate-400 text-xs"><?= sanitize_string($c['other_name']) ?></td>
                            <td class="py-3"><span class="inline-block px-2.5 py-1 rounded-lg text-xs font-semibold <?= $contractStatusColors[$c['status']] ?? '' ?>"><?= sanitize_string(ucfirst($c['status'])) ?></span></td>
                            <td class="py-3 text-right font-semibold text-gray-700 dark:text-slate-300"><?= format_currency((float) $c['total_budget']) ?></td>
                            <td class="py-3 text-right text-gray-400 dark:text-slate-500 text-xs"><?= time_ago($c['created_at']) ?></td>
                        </tr>
                    <?php endwhile; ?>
                    </tbody>
                </table>
            </div>
            <?php else: ?>
            <div class="text-center py-12">
                <div class="w-14 h-14 rounded-2xl bg-gray-100 dark:bg-slate-700 flex items-center justify-center mx-auto mb-3">
                    <i data-lucide="file-text" class="text-xl text-gray-300 dark:text-slate-500"></i>
                </div>
                <p class="text-gray-500 dark:text-slate-400 text-sm">No contracts found</p>
            </div>
            <?php endif; ?>

        <?php elseif ($activeTab === 'wallet'): ?>
            <!-- ═══ WALLET TAB ═════════════════════════════════════════ -->
            <div class="flex items-center justify-between mb-4">
                <div>
                    <p class="text-xs text-gray-400 dark:text-slate-500 uppercase tracking-wider font-semibold">Current Balance</p>
                    <p class="text-2xl font-extrabold text-gray-900 dark:text-white"><?= format_currency((float) $targetUser['wallet_balance']) ?></p>
                </div>
            </div>
            <?php if ($walletResult && $walletResult->num_rows > 0): ?>
            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead>
                        <tr class="border-b border-gray-100 dark:border-slate-700">
                            <th class="text-left pb-3 text-xs font-semibold text-gray-400 dark:text-slate-500 uppercase tracking-wider">Type</th>
                            <th class="text-left pb-3 text-xs font-semibold text-gray-400 dark:text-slate-500 uppercase tracking-wider">Description</th>
                            <th class="text-right pb-3 text-xs font-semibold text-gray-400 dark:text-slate-500 uppercase tracking-wider">Amount</th>
                            <th class="text-right pb-3 text-xs font-semibold text-gray-400 dark:text-slate-500 uppercase tracking-wider">Balance After</th>
                            <th class="text-right pb-3 text-xs font-semibold text-gray-400 dark:text-slate-500 uppercase tracking-wider">Date</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php while ($tx = $walletResult->fetch_assoc()): ?>
                        <tr class="border-b border-gray-50 dark:border-slate-700/50 last:border-0 hover:bg-gray-50/50 dark:hover:bg-slate-700/30 transition-colors">
                            <td class="py-3"><span class="inline-block px-2.5 py-1 rounded-lg text-xs font-semibold <?= $walletTypeColors[$tx['type']] ?? '' ?>"><?= sanitize_string(ucfirst(str_replace('_', ' ', $tx['type']))) ?></span></td>
                            <td class="py-3 text-gray-600 dark:text-slate-400 text-xs max-w-[200px] truncate"><?= sanitize_string($tx['description'] ?: '—') ?></td>
                            <td class="py-3 text-right font-semibold <?= in_array($tx['type'], ['deposit', 'escrow_release', 'refund', 'signup_bonus']) ? 'text-emerald-600 dark:text-emerald-400' : 'text-red-600 dark:text-red-400' ?>"><?= in_array($tx['type'], ['deposit', 'escrow_release', 'refund', 'signup_bonus']) ? '+' : '−' ?><?= format_currency(abs((float) $tx['amount'])) ?></td>
                            <td class="py-3 text-right font-semibold text-gray-700 dark:text-slate-300 text-xs"><?= format_currency((float) $tx['balance_after']) ?></td>
                            <td class="py-3 text-right text-gray-400 dark:text-slate-500 text-xs"><?= time_ago($tx['created_at']) ?></td>
                        </tr>
                    <?php endwhile; ?>
                    </tbody>
                </table>
            </div>
            <?php else: ?>
            <div class="text-center py-12">
                <div class="w-14 h-14 rounded-2xl bg-gray-100 dark:bg-slate-700 flex items-center justify-center mx-auto mb-3">
                    <i data-lucide="wallet" class="text-xl text-gray-300 dark:text-slate-500"></i>
                </div>
                <p class="text-gray-500 dark:text-slate-400 text-sm">No transactions found</p>
            </div>
            <?php endif; ?>

        <?php elseif ($activeTab === 'reviews'): ?>
            <!-- ═══ REVIEWS TAB ════════════════════════════════════════ -->
            <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
                <!-- Reviews Received -->
                <div>
                    <h4 class="text-sm font-bold text-gray-900 dark:text-white mb-3"><i data-lucide="star" class="text-yellow-500 mr-1.5"></i>Reviews Received (<?= $totalReviews ?>)</h4>
                    <?php if ($reviewsReceived && $reviewsReceived->num_rows > 0): ?>
                    <div class="space-y-3">
                    <?php while ($rev = $reviewsReceived->fetch_assoc()): ?>
                        <div class="p-4 rounded-xl bg-gray-50 dark:bg-slate-700/50 border border-gray-100 dark:border-slate-600">
                            <div class="flex items-center gap-2 mb-2">
                                <?php
                                $_rvi = $rev['reviewer_image'] ?? '';
                                $_rvb = strtolower(basename($_rvi));
                                $_rvv = $_rvi !== '' && $_rvi !== null && $_rvb !== 'default.png' && $_rvb !== 'profile.png';
                                if ($_rvv):
                                ?>
                                <img src="<?= sanitize_string(get_profile_image($_rvi)) ?>" class="w-7 h-7 rounded-full object-cover">
                                <?php else:
                                    $_rvin = '';
                                    foreach (explode(' ', trim($rev['reviewer_name'] ?? '')) as $_w) { if ($_w !== '') $_rvin .= strtoupper($_w[0]); }
                                    $_rvin = substr($_rvin, 0, 2);
                                ?>
                                <div class="w-7 h-7 rounded-full bg-blue-100 text-blue-600 flex items-center justify-center font-semibold text-xs"><?= $_rvin ?></div>
                                <?php endif; ?>
                                <span class="text-sm font-medium text-gray-900 dark:text-white"><?= sanitize_string($rev['reviewer_name']) ?></span>
                                <div class="flex ml-auto">
                                    <?php for ($i = 1; $i <= 5; $i++): ?>
                                    <i data-lucide="star" class="text-xs <?= $i <= $rev['rating'] ? 'text-yellow-400' : 'text-gray-200 dark:text-slate-600' ?>"></i>
                                    <?php endfor; ?>
                                </div>
                            </div>
                            <?php if ($rev['comment']): ?>
                            <p class="text-sm text-gray-600 dark:text-slate-400"><?= sanitize_string($rev['comment']) ?></p>
                            <?php endif; ?>
                            <p class="text-xs text-gray-400 dark:text-slate-500 mt-2"><?= time_ago($rev['created_at']) ?></p>
                        </div>
                    <?php endwhile; ?>
                    </div>
                    <?php else: ?>
                    <div class="text-center py-8"><p class="text-gray-400 dark:text-slate-500 text-sm">No reviews received</p></div>
                    <?php endif; ?>
                </div>

                <!-- Reviews Given -->
                <div>
                    <h4 class="text-sm font-bold text-gray-900 dark:text-white mb-3"><i data-lucide="pencil" class="text-blue-500 mr-1.5"></i>Reviews Given</h4>
                    <?php if ($reviewsGiven && $reviewsGiven->num_rows > 0): ?>
                    <div class="space-y-3">
                    <?php while ($rev = $reviewsGiven->fetch_assoc()): ?>
                        <div class="p-4 rounded-xl bg-gray-50 dark:bg-slate-700/50 border border-gray-100 dark:border-slate-600">
                            <div class="flex items-center gap-2 mb-2">
                                <?php
                                $_rei = $rev['reviewee_image'] ?? '';
                                $_reb = strtolower(basename($_rei));
                                $_revv = $_rei !== '' && $_rei !== null && $_reb !== 'default.png' && $_reb !== 'profile.png';
                                if ($_revv):
                                ?>
                                <img src="<?= sanitize_string(get_profile_image($_rei)) ?>" class="w-7 h-7 rounded-full object-cover">
                                <?php else:
                                    $_rein = '';
                                    foreach (explode(' ', trim($rev['reviewee_name'] ?? '')) as $_w) { if ($_w !== '') $_rein .= strtoupper($_w[0]); }
                                    $_rein = substr($_rein, 0, 2);
                                ?>
                                <div class="w-7 h-7 rounded-full bg-blue-100 text-blue-600 flex items-center justify-center font-semibold text-xs"><?= $_rein ?></div>
                                <?php endif; ?>
                                <span class="text-sm font-medium text-gray-900 dark:text-white"><?= sanitize_string($rev['reviewee_name']) ?></span>
                                <div class="flex ml-auto">
                                    <?php for ($i = 1; $i <= 5; $i++): ?>
                                    <i data-lucide="star" class="text-xs <?= $i <= $rev['rating'] ? 'text-yellow-400' : 'text-gray-200 dark:text-slate-600' ?>"></i>
                                    <?php endfor; ?>
                                </div>
                            </div>
                            <?php if ($rev['comment']): ?>
                            <p class="text-sm text-gray-600 dark:text-slate-400"><?= sanitize_string($rev['comment']) ?></p>
                            <?php endif; ?>
                            <p class="text-xs text-gray-400 dark:text-slate-500 mt-2"><?= time_ago($rev['created_at']) ?></p>
                        </div>
                    <?php endwhile; ?>
                    </div>
                    <?php else: ?>
                    <div class="text-center py-8"><p class="text-gray-400 dark:text-slate-500 text-sm">No reviews given</p></div>
                    <?php endif; ?>
                </div>
            </div>

        <?php elseif ($activeTab === 'activity'): ?>
            <!-- ═══ ACTIVITY TAB ═══════════════════════════════════════ -->
            <?php if ($activityResult->num_rows > 0): ?>
            <div class="space-y-2">
            <?php
            while ($act = $activityResult->fetch_assoc()):
                $aInfo = $activityIcons[$act['action_type']] ?? $defaultActivity;
                ?>
                <div class="flex items-center gap-3 p-3 rounded-xl hover:bg-gray-50 dark:hover:bg-slate-700/30 transition-colors">
                    <div class="w-9 h-9 rounded-xl <?= $aInfo['bg'] ?> flex items-center justify-center flex-shrink-0">
                        <i data-lucide="<?= $aInfo['icon'] ?>" class="<?= $aInfo['color'] ?> text-xs"></i>
                    </div>
                    <div class="flex-1 min-w-0">
                        <p class="text-sm text-gray-700 dark:text-slate-300"><?= sanitize_string(str_replace('_', ' ', $act['action_type'])) ?></p>
                        <p class="text-xs text-gray-400 dark:text-slate-500 mt-0.5">IP: <?= sanitize_string($act['ip_address']) ?> &middot; <?= time_ago($act['created_at']) ?></p>
                    </div>
                </div>
            <?php endwhile; ?>
            </div>
            <?php else: ?>
            <div class="text-center py-12">
                <div class="w-14 h-14 rounded-2xl bg-gray-100 dark:bg-slate-700 flex items-center justify-center mx-auto mb-3">
                    <i data-lucide="history" class="text-xl text-gray-300 dark:text-slate-500"></i>
                </div>
                <p class="text-gray-500 dark:text-slate-400 text-sm">No activity recorded</p>
            </div>
            <?php endif; ?>

        <?php endif; ?>
        </div>

<?php require_once __DIR__ . '/../components/layout_end.php'; ?>
