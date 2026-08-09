<?php
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../auth/auth.php';
require_role('admin');

$currentPage = 'fraud';

// ── Auto-recalculate fraud scores before counting ──────────────────────
$recalcStmt = $conn->prepare('SELECT DISTINCT user_id FROM user_behavior_logs');
$recalcStmt->execute();
$recalcResult = $recalcStmt->get_result();
$recalcUserIds = [];
while ($recalcRow = $recalcResult->fetch_assoc()) {
    $recalcUserIds[] = (int) $recalcRow['user_id'];
}
$recalcStmt->close();

foreach ($recalcUserIds as $recalcUid) {
    $recalcScore = 0;

    $q1 = $conn->prepare('SELECT COUNT(*) AS cnt FROM user_behavior_logs WHERE user_id = ? AND created_at >= DATE_SUB(NOW(), INTERVAL 1 MINUTE)');
    $q1->bind_param('i', $recalcUid);
    $q1->execute();
    if ((int) $q1->get_result()->fetch_assoc()['cnt'] > 10)
        $recalcScore += 20;
    $q1->close();

    $q2 = $conn->prepare('SELECT COUNT(DISTINCT ip_address) AS cnt FROM user_behavior_logs WHERE user_id = ? AND created_at >= DATE_SUB(NOW(), INTERVAL 24 HOUR)');
    $q2->bind_param('i', $recalcUid);
    $q2->execute();
    if ((int) $q2->get_result()->fetch_assoc()['cnt'] > 1)
        $recalcScore += 15;
    $q2->close();

    $q3 = $conn->prepare("SELECT COUNT(*) AS cnt FROM user_behavior_logs WHERE user_id = ? AND action_type = 'proposal_submit' AND created_at >= DATE_SUB(NOW(), INTERVAL 1 HOUR)");
    $q3->bind_param('i', $recalcUid);
    $q3->execute();
    if ((int) $q3->get_result()->fetch_assoc()['cnt'] > 5)
        $recalcScore += 25;
    $q3->close();

    $q4 = $conn->prepare("SELECT COUNT(*) AS cnt FROM user_behavior_logs WHERE user_id = ? AND action_type = 'login_failed' AND created_at >= DATE_SUB(NOW(), INTERVAL 24 HOUR)");
    $q4->bind_param('i', $recalcUid);
    $q4->execute();
    $q4Cnt = (int) $q4->get_result()->fetch_assoc()['cnt'];
    $q4->close();
    if ($q4Cnt > 0)
        $recalcScore += min($q4Cnt * 10, 30);

    $flaggedTypes = ['spam', 'phishing', 'fake_review', 'payment_fraud', 'account_takeover', 'suspicious_download'];
    $placeholders = implode(',', array_fill(0, count($flaggedTypes), '?'));
    $q5 = $conn->prepare("SELECT COUNT(*) AS cnt FROM user_behavior_logs WHERE user_id = ? AND action_type IN ({$placeholders}) AND created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)");
    $q5Types = array_merge([$recalcUid], $flaggedTypes);
    $q5->bind_param(str_repeat('s', count($q5Types)), ...$q5Types);
    $q5->execute();
    $recalcScore += (int) $q5->get_result()->fetch_assoc()['cnt'] * 10;
    $q5->close();

    $recalcScore = min($recalcScore, 100);

    $qUp = $conn->prepare('UPDATE users SET fraud_score = ? WHERE id = ?');
    $qUp->bind_param('ii', $recalcScore, $recalcUid);
    $qUp->execute();
    $qUp->close();

    if ($recalcScore >= 70) {
        $qFl = $conn->prepare("UPDATE users SET status = 'flagged' WHERE id = ? AND status = 'active'");
        $qFl->bind_param('i', $recalcUid);
        $qFl->execute();
        $qFl->close();
    }
}

// ── Overview Counts ───────────────────────────────────────────────────
$counts = [];

// Total flagged users
$stmt = $conn->prepare("SELECT COUNT(*) AS cnt FROM users WHERE status = 'flagged'");
$stmt->execute();
$counts['flagged'] = (int) $stmt->get_result()->fetch_assoc()['cnt'];
$stmt->close();

// High-risk users (score >= 70)
$stmt = $conn->prepare('SELECT COUNT(*) AS cnt FROM users WHERE fraud_score >= 70');
$stmt->execute();
$counts['high_risk'] = (int) $stmt->get_result()->fetch_assoc()['cnt'];
$stmt->close();

// Medium-risk users (score 40-69)
$stmt = $conn->prepare('SELECT COUNT(*) AS cnt FROM users WHERE fraud_score BETWEEN 40 AND 69');
$stmt->execute();
$counts['medium_risk'] = (int) $stmt->get_result()->fetch_assoc()['cnt'];
$stmt->close();

// Suspicious actions in last 24h
$stmt = $conn->prepare("SELECT COUNT(*) AS cnt FROM user_behavior_logs WHERE created_at >= DATE_SUB(NOW(), INTERVAL 24 HOUR) AND action_type IN ('login_failed','spam','phishing','fake_review','payment_fraud','account_takeover','suspicious_download')");
$stmt->execute();
$counts['actions_24h'] = (int) $stmt->get_result()->fetch_assoc()['cnt'];
$stmt->close();

// ── Suspicious Users ──────────────────────────────────────────────────
$stmt = $conn->prepare(
    "SELECT u.id, u.name, u.email, u.role, u.status, u.fraud_score, u.profile_image, u.created_at,
            (SELECT COUNT(DISTINCT bl.ip_address) FROM user_behavior_logs bl WHERE bl.user_id = u.id AND bl.created_at >= DATE_SUB(NOW(), INTERVAL 24 HOUR)) AS unique_ips_24h,
            (SELECT bl2.action_type FROM user_behavior_logs bl2 WHERE bl2.user_id = u.id ORDER BY bl2.created_at DESC LIMIT 1) AS last_action,
            (SELECT bl3.created_at FROM user_behavior_logs bl3 WHERE bl3.user_id = u.id ORDER BY bl3.created_at DESC LIMIT 1) AS last_action_time
     FROM users u
     WHERE u.fraud_score >= 50 OR u.status = 'flagged'
     ORDER BY u.fraud_score DESC, u.created_at DESC
     LIMIT 50"
);
$stmt->execute();
$suspiciousUsers = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

// ── Recent Suspicious Activity Logs ───────────────────────────────────
$filterAction = trim($_GET['filter_action'] ?? '');
$filterDate = trim($_GET['filter_date'] ?? '');

$whereLogs = [];
$paramsLogs = [];
$typesLogs = '';

if (!empty($filterAction)) {
    $whereLogs[] = 'bl.action_type = ?';
    $paramsLogs[] = $filterAction;
    $typesLogs .= 's';
}
if (!empty($filterDate)) {
    $whereLogs[] = 'DATE(bl.created_at) = ?';
    $paramsLogs[] = $filterDate;
    $typesLogs .= 's';
}

$whereLogClause = !empty($whereLogs) ? 'WHERE ' . implode(' AND ', $whereLogs) : '';

$page = max(1, intval($_GET['page'] ?? 1));
$perPage = 15;

$countSql = "SELECT COUNT(*) AS total FROM user_behavior_logs bl {$whereLogClause}";
$stmt = $conn->prepare($countSql);
if (!empty($paramsLogs)) {
    $stmt->bind_param($typesLogs, ...$paramsLogs);
}
$stmt->execute();
$totalLogs = (int) $stmt->get_result()->fetch_assoc()['total'];
$stmt->close();

$pagination = paginate($totalLogs, $perPage, $page);

$dataSql = "SELECT bl.*, u.name AS user_name, u.email AS user_email
            FROM user_behavior_logs bl
            JOIN users u ON bl.user_id = u.id
            {$whereLogClause}
            ORDER BY bl.created_at DESC
            LIMIT ? OFFSET ?";
$allParams = array_merge($paramsLogs, [$perPage, $pagination['offset']]);
$allTypes = $typesLogs . 'ii';
$stmt = $conn->prepare($dataSql);
$stmt->bind_param($allTypes, ...$allParams);
$stmt->execute();
$activityLogs = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

// Get unique action types for filter dropdown
$stmt = $conn->prepare('SELECT DISTINCT action_type FROM user_behavior_logs ORDER BY action_type');
$stmt->execute();
$actionTypes = [];
$result = $stmt->get_result();
while ($row = $result->fetch_assoc()) {
    $actionTypes[] = $row['action_type'];
}
$stmt->close();

$baseQuery = 'admin/fraud_detection.php';
if (!empty($filterAction))
    $baseQuery .= '&filter_action=' . urlencode($filterAction);
if (!empty($filterDate))
    $baseQuery .= '&filter_date=' . urlencode($filterDate);

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
$pageTitle = 'Fraud Detection';
$pageSubtitle = 'Monitor and manage suspicious platform activity';
$activePage = 'fraud';
$user = ['name' => $adminName, 'profile_image' => $_navUserRow['profile_image'] ?? null];
$unreadCount = 0;
$profileLink = 'profile.php';
require_once __DIR__ . '/../components/layout_start.php';
?>
<script src="/jobhub/assets/js/fraud.js" defer></script>
<style>
    .score-bar { transition: width 0.6s cubic-bezier(.4,0,.2,1); }
    .fraud-card { transition: transform 0.15s ease, box-shadow 0.15s ease; }
    .fraud-card:hover { transform: translateY(-1px); box-shadow: 0 4px 12px rgba(0,0,0,.06); }
    .log-row:hover { background: #F9FAFB; }
    .dark .log-row:hover { background: rgba(51,65,85,.25); }
    .copy-ip { cursor: pointer; opacity: 0; transition: opacity .15s; }
    td:hover .copy-ip { opacity: 1; }
    .pill-filter { transition: background .15s, color .15s; }
    .pill-filter:hover { background: #E5E7EB; color: #111827; }
    .pill-filter.active { background: #111827; color: #fff; }
    .dark .pill-filter:hover { background: #475569; color: #f1f5f9; }
    .dark .pill-filter.active { background: #3B82F6; color: #fff; }
    @keyframes pulse-badge { 0%,100%{opacity:1} 50%{opacity:.6} }
    .pulse-live { animation: pulse-badge 2s ease-in-out infinite; }

    /* ── Risk Drawer Slide-Over ─────────────────────────────────────────── */
    .risk-drawer-backdrop {
        position: fixed; inset: 0; z-index: 90;
        background: rgba(0,0,0,0);
        pointer-events: none;
        transition: background .3s ease;
    }
    .risk-drawer-backdrop.open {
        background: rgba(0,0,0,0.4);
        pointer-events: auto;
    }
    .risk-drawer {
        position: fixed; top: 0; right: 0; bottom: 0;
        width: 480px; max-width: 100vw;
        z-index: 100;
        transform: translateX(100%);
        transition: transform .32s cubic-bezier(.16,1,.3,1);
        display: flex; flex-direction: column;
        background: #fff;
    }
    .dark .risk-drawer { background: #0f172a; }
    .risk-drawer.open { transform: translateX(0); }
    .risk-drawer-body { flex: 1; overflow-y: auto; overscroll-behavior: contain; }
    .risk-drawer-body::-webkit-scrollbar { width: 5px; }
    .risk-drawer-body::-webkit-scrollbar-track { background: transparent; }
    .risk-drawer-body::-webkit-scrollbar-thumb { background: #d1d5db; border-radius: 999px; }
    .dark .risk-drawer-body::-webkit-scrollbar-thumb { background: #334155; }
    .risk-factor-item { transition: background .12s; }
    .risk-factor-item:hover { background: #f9fafb; }
    .dark .risk-factor-item:hover { background: rgba(51,65,85,.2); }
    .drawer-action-btn { transition: transform .1s, box-shadow .15s; }
    .drawer-action-btn:active { transform: scale(.97); }
    @keyframes drawer-shimmer { 0%{background-position:-468px 0} 100%{background-position:468px 0} }
    .drawer-skeleton {
        background: #e5e7eb; background-image: linear-gradient(to right, #e5e7eb 0%, #f3f4f6 50%, #e5e7eb 100%);
        background-size: 800px 100%; animation: drawer-shimmer 1.5s infinite linear;
        border-radius: 6px;
    }
    .dark .drawer-skeleton { background: #1e293b; background-image: linear-gradient(to right, #1e293b 0%, #334155 50%, #1e293b 100%); }
</style>

<!-- Flash Messages -->
<?php display_flash('success'); ?>
<?php display_flash('error'); ?>

<!-- ═══════════════════════════════════════════════════════════════════════
     SECTION 1 — TOP RISK METRIC CARDS
     ═══════════════════════════════════════════════════════════════════════ -->
<div class="flex items-center justify-between mb-6">
    <div>
        <h1 class="text-xl font-bold text-gray-900 dark:text-white tracking-tight">Fraud Detection Hub</h1>
        <p class="text-gray-500 dark:text-slate-400 text-xs mt-0.5">Real-time risk monitoring & compliance overview</p>
    </div>
    <button onclick="refreshScores()" class="inline-flex items-center gap-2 px-4 py-2 bg-blue-600 hover:bg-blue-700 text-white text-sm font-semibold rounded-lg shadow-sm transition">
        <i data-lucide="refresh-cw" class="text-xs" id="refreshIcon"></i> Recalculate All Scores
    </button>
</div>

<div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4 mb-6" id="overviewCards">

    <!-- Flagged Users -->
    <div class="fraud-card bg-white dark:bg-slate-800 rounded-xl border border-gray-200 dark:border-slate-700 p-4 flex items-center justify-between">
        <div>
            <p class="text-[11px] font-semibold uppercase tracking-wider text-gray-500 dark:text-slate-400">Flagged Users</p>
            <p class="text-2xl font-extrabold text-gray-900 dark:text-white mt-1" id="countFlagged"><?= $counts['flagged'] ?></p>
        </div>
        <span class="flex-shrink-0 w-10 h-10 rounded-lg bg-red-50 dark:bg-red-900/20 flex items-center justify-center">
            <i data-lucide="flag" class="text-red-500 text-sm"></i>
        </span>
    </div>

    <!-- High Risk (70+) -->
    <div class="fraud-card bg-white dark:bg-slate-800 rounded-xl border border-gray-200 dark:border-slate-700 p-4 flex items-center justify-between">
        <div>
            <p class="text-[11px] font-semibold uppercase tracking-wider text-gray-500 dark:text-slate-400">High Risk (70+)</p>
            <p class="text-2xl font-extrabold text-gray-900 dark:text-white mt-1" id="countHigh"><?= $counts['high_risk'] ?></p>
        </div>
        <span class="flex-shrink-0 w-10 h-10 rounded-lg flex items-center justify-center" style="background:#FEE2E2">
            <i data-lucide="circle-alert" class="text-sm" style="color:#991B1B"></i>
        </span>
    </div>

    <!-- Medium Risk (40–69) -->
    <div class="fraud-card bg-white dark:bg-slate-800 rounded-xl border border-gray-200 dark:border-slate-700 p-4 flex items-center justify-between">
        <div>
            <p class="text-[11px] font-semibold uppercase tracking-wider text-gray-500 dark:text-slate-400">Medium Risk (40–69)</p>
            <p class="text-2xl font-extrabold text-gray-900 dark:text-white mt-1" id="countMedium"><?= $counts['medium_risk'] ?></p>
        </div>
        <span class="flex-shrink-0 w-10 h-10 rounded-lg flex items-center justify-center" style="background:#FEF3C7">
            <i data-lucide="triangle-alert" class="text-sm" style="color:#92400E"></i>
        </span>
    </div>

    <!-- Suspicious Actions (24h) -->
    <div class="fraud-card bg-white dark:bg-slate-800 rounded-xl border border-gray-200 dark:border-slate-700 p-4 flex items-center justify-between">
        <div>
            <p class="text-[11px] font-semibold uppercase tracking-wider text-gray-500 dark:text-slate-400">Suspicious Actions (24h)</p>
            <p class="text-2xl font-extrabold text-gray-900 dark:text-white mt-1" id="countActions"><?= $counts['actions_24h'] ?></p>
        </div>
        <span class="flex-shrink-0 w-10 h-10 rounded-lg flex items-center justify-center" style="background:#F3E8FF">
            <i data-lucide="zap" class="text-sm" style="color:#6B21A8"></i>
        </span>
    </div>
</div>

<!-- ═══════════════════════════════════════════════════════════════════════
     SECTION 2 — FLAGGED & HIGH-RISK ACCOUNTS
     ═══════════════════════════════════════════════════════════════════════ -->
<div class="bg-white dark:bg-slate-800 rounded-xl border border-gray-200 dark:border-slate-700 shadow-sm mb-6">
    <!-- Card Header -->
    <div class="px-5 py-3.5 border-b border-gray-100 dark:border-slate-700 flex items-center justify-between">
        <h2 class="text-sm font-bold text-gray-900 dark:text-white flex items-center gap-2">
            <span class="text-base">🛡️</span> Flagged &amp; High-Risk Accounts
        </h2>
        <span class="text-[11px] font-medium text-gray-400 dark:text-slate-500 bg-gray-100 dark:bg-slate-700 px-2 py-0.5 rounded-full">
            <?= count($suspiciousUsers) ?> user<?= count($suspiciousUsers) !== 1 ? 's' : '' ?>
        </span>
    </div>

    <div class="overflow-x-auto">
        <table class="w-full text-sm">
            <thead>
                <tr class="border-b border-gray-100 dark:border-slate-700">
                    <th class="text-left px-5 py-2.5 text-[11px] font-bold uppercase tracking-wider text-gray-500 dark:text-slate-400">User</th>
                    <th class="text-left px-5 py-2.5 text-[11px] font-bold uppercase tracking-wider text-gray-500 dark:text-slate-400">Role</th>
                    <th class="text-left px-5 py-2.5 text-[11px] font-bold uppercase tracking-wider text-gray-500 dark:text-slate-400">Fraud Score</th>
                    <th class="text-left px-5 py-2.5 text-[11px] font-bold uppercase tracking-wider text-gray-500 dark:text-slate-400">Status</th>
                    <th class="text-left px-5 py-2.5 text-[11px] font-bold uppercase tracking-wider text-gray-500 dark:text-slate-400">Last Action</th>
                    <th class="text-left px-5 py-2.5 text-[11px] font-bold uppercase tracking-wider text-gray-500 dark:text-slate-400">IPs (24h)</th>
                    <th class="text-left px-5 py-2.5 text-[11px] font-bold uppercase tracking-wider text-gray-500 dark:text-slate-400">Actions</th>
                </tr>
            </thead>
            <tbody id="suspiciousTableBody" class="divide-y divide-gray-50 dark:divide-slate-700/50">
                <?php if (empty($suspiciousUsers)): ?>
                    <tr>
                        <td colspan="7" class="px-5 py-16">
                            <div class="flex flex-col items-center justify-center text-center rounded-xl py-12 px-8" style="background:#F9FAFB">
                                <div class="w-14 h-14 rounded-full bg-gray-100 dark:bg-slate-700 flex items-center justify-center mb-4">
                                    <i data-lucide="shield" class="text-2xl text-gray-300 dark:text-slate-500"></i>
                                </div>
                                <p class="text-sm font-semibold text-gray-700 dark:text-slate-300">No flagged or high-risk accounts</p>
                                <p class="text-xs text-gray-400 dark:text-slate-500 mt-1 max-w-xs">All users are operating within normal risk thresholds. Alerts will appear here when anomalies are detected.</p>
                            </div>
                        </td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($suspiciousUsers as $user): ?>
                        <?php
                            $fs = (int) $user['fraud_score'];
                            if ($fs >= 70) {
                                $scoreBg = '#FEE2E2'; $scoreText = '#991B1B'; $scoreBar = 'bg-red-500';
                            } elseif ($fs >= 40) {
                                $scoreBg = '#FEF3C7'; $scoreText = '#92400E'; $scoreBar = 'bg-amber-500';
                            } else {
                                $scoreBg = '#ECFDF5'; $scoreText = '#065F46'; $scoreBar = 'bg-emerald-500';
                            }
                        ?>
                        <tr class="log-row transition" data-user-id="<?= $user['id'] ?>">
                            <!-- User -->
                            <td class="px-5 py-3">
                                <div class="flex items-center gap-3">
                                    <img src="<?= get_profile_image($user['profile_image']) ?>" alt="" class="w-8 h-8 rounded-full object-cover ring-2 ring-gray-100 dark:ring-slate-700">
                                    <div class="min-w-0">
                                        <p class="font-semibold text-gray-900 dark:text-white text-xs leading-tight truncate"><?= sanitize_string($user['name']) ?></p>
                                        <p class="text-[11px] text-gray-400 dark:text-slate-500 truncate"><?= sanitize_string($user['email']) ?></p>
                                    </div>
                                </div>
                            </td>
                            <!-- Role -->
                            <td class="px-5 py-3">
                                <span class="inline-flex px-2 py-0.5 rounded text-[11px] font-semibold uppercase tracking-wide <?= $user['role'] === 'admin' ? 'bg-purple-50 dark:bg-purple-900/20 text-purple-700 dark:text-purple-400' : ($user['role'] === 'client' ? 'bg-blue-50 dark:bg-blue-900/20 text-blue-700 dark:text-blue-400' : 'bg-emerald-50 dark:bg-emerald-900/20 text-emerald-700 dark:text-emerald-400') ?>">
                                    <?= sanitize_string(ucfirst($user['role'])) ?>
                                </span>
                            </td>
                            <!-- Fraud Score Pill -->
                            <td class="px-5 py-3">
                                <div class="flex items-center gap-2.5">
                                    <span class="inline-flex items-center justify-center min-w-[52px] px-2 py-0.5 rounded-md text-xs font-bold" style="background:<?= $scoreBg ?>;color:<?= $scoreText ?>">
                                        <?= $fs ?>/100
                                    </span>
                                    <div class="w-20 bg-gray-100 dark:bg-slate-700 rounded-full h-1.5 overflow-hidden">
                                        <div class="<?= $scoreBar ?> h-1.5 rounded-full score-bar" style="width:<?= $fs ?>%"></div>
                                    </div>
                                </div>
                            </td>
                            <!-- Status -->
                            <td class="px-5 py-3">
                                <?php
                                $statusStyles = [
                                    'active'   => 'bg-emerald-50 dark:bg-emerald-900/20 text-emerald-700 dark:text-emerald-400',
                                    'flagged'  => 'bg-red-50 dark:bg-red-900/20 text-red-700 dark:text-red-400',
                                    'suspended'=> 'bg-gray-100 dark:bg-slate-700 text-gray-600 dark:text-slate-400',
                                ];
                                $sStyle = $statusStyles[$user['status']] ?? $statusStyles['active'];
                                ?>
                                <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded text-[11px] font-semibold uppercase tracking-wide <?= $sStyle ?>">
                                    <?php if ($user['status'] === 'flagged'): ?><i data-lucide="circle" class="text-[6px]"></i><?php endif; ?>
                                    <?= sanitize_string(ucfirst($user['status'])) ?>
                                </span>
                            </td>
                            <!-- Last Action -->
                            <td class="px-5 py-3">
                                <?php if ($user['last_action']): ?>
                                    <p class="text-gray-800 dark:text-slate-200 text-xs font-medium font-mono truncate max-w-[140px]"><?= sanitize_string($user['last_action']) ?></p>
                                    <p class="text-[11px] text-gray-400 dark:text-slate-500 mt-0.5"><?= time_ago($user['last_action_time']) ?></p>
                                <?php else: ?>
                                    <span class="text-gray-300 dark:text-slate-600 text-xs">—</span>
                                <?php endif; ?>
                            </td>
                            <!-- IPs 24h -->
                            <td class="px-5 py-3">
                                <?php $ipCount = (int) ($user['unique_ips_24h'] ?? 0); ?>
                                <span class="inline-flex items-center justify-center min-w-[28px] px-1.5 py-0.5 rounded text-xs font-bold <?= $ipCount > 3 ? 'bg-red-50 dark:bg-red-900/20 text-red-600 dark:text-red-400' : ($ipCount > 1 ? 'bg-amber-50 dark:bg-amber-900/20 text-amber-600 dark:text-amber-400' : 'bg-gray-50 dark:bg-slate-700 text-gray-600 dark:text-slate-400') ?>">
                                    <?= $ipCount ?>
                                </span>
                            </td>
                            <!-- Actions -->
                            <td class="px-5 py-3">
                                <div class="flex items-center gap-1">
                                    <button onclick="viewUserActivity(<?= $user['id'] ?>)" class="p-1.5 text-blue-600 dark:text-blue-400 hover:bg-blue-50 dark:hover:bg-blue-900/20 rounded-md transition" title="View Activity Log">
                                        <i data-lucide="eye" class="text-xs"></i>
                                    </button>
                                    <?php if ($user['status'] !== 'flagged'): ?>
                                        <form method="POST" action="/jobhub/admin/fraud_action.php" class="inline" onsubmit="return confirm('Flag this user?')">
                                            <?= csrf_field() ?>
                                            <input type="hidden" name="action" value="flag_user">
                                            <input type="hidden" name="user_id" value="<?= $user['id'] ?>">
                                            <button type="submit" class="p-1.5 text-amber-600 dark:text-amber-400 hover:bg-amber-50 dark:hover:bg-amber-900/20 rounded-md transition" title="Flag User">
                                                <i data-lucide="flag" class="text-xs"></i>
                                            </button>
                                        </form>
                                    <?php else: ?>
                                        <form method="POST" action="/jobhub/admin/fraud_action.php" class="inline" onsubmit="return confirm('Unflag this user and reset score?')">
                                            <?= csrf_field() ?>
                                            <input type="hidden" name="action" value="unflag_user">
                                            <input type="hidden" name="user_id" value="<?= $user['id'] ?>">
                                            <button type="submit" class="p-1.5 text-emerald-600 dark:text-emerald-400 hover:bg-emerald-50 dark:hover:bg-emerald-900/20 rounded-md transition" title="Unflag User">
                                                <i data-lucide="circle-check" class="text-xs"></i>
                                            </button>
                                        </form>
                                    <?php endif; ?>
                                    <?php if ($user['status'] !== 'suspended'): ?>
                                        <form method="POST" action="/jobhub/admin/fraud_action.php" class="inline" onsubmit="return confirm('SUSPEND this user? This will prevent them from using the platform.')">
                                            <?= csrf_field() ?>
                                            <input type="hidden" name="action" value="suspend_user">
                                            <input type="hidden" name="user_id" value="<?= $user['id'] ?>">
                                            <button type="submit" class="p-1.5 text-red-600 dark:text-red-400 hover:bg-red-50 dark:hover:bg-red-900/20 rounded-md transition" title="Suspend User">
                                                <i data-lucide="ban" class="text-xs"></i>
                                            </button>
                                        </form>
                                    <?php endif; ?>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- ═══════════════════════════════════════════════════════════════════════
     SECTION 3 — REAL-TIME ACTIVITY LOG
     ═══════════════════════════════════════════════════════════════════════ -->
<div class="bg-white dark:bg-slate-800 rounded-xl border border-gray-200 dark:border-slate-700 shadow-sm">
    <!-- Card Header -->
    <div class="px-5 py-3.5 border-b border-gray-100 dark:border-slate-700 flex items-center justify-between">
        <h2 class="text-sm font-bold text-gray-900 dark:text-white flex items-center gap-2">
            <span class="text-base">⚡</span> Real-Time Activity Log
            <span class="inline-flex items-center gap-1 ml-1">
                <span class="w-1.5 h-1.5 rounded-full bg-emerald-400 pulse-live"></span>
            </span>
        </h2>
        <span class="text-[11px] font-medium text-gray-400 dark:text-slate-500 bg-gray-100 dark:bg-slate-700 px-2 py-0.5 rounded-full">
            <?= number_format($totalLogs) ?> entries
        </span>
    </div>

    <!-- Unified Filter Toolbar -->
    <div class="px-5 py-3 border-b border-gray-100 dark:border-slate-700 bg-gray-50/70 dark:bg-slate-700/20">
        <form method="GET" id="filterForm" class="flex items-center gap-3 flex-wrap">
            <!-- Search Input -->
            <div class="relative flex-1 min-w-[220px]">
                <span class="absolute left-3 top-1/2 -translate-y-1/2 text-gray-400 dark:text-slate-500 text-xs"><i data-lucide="search"></i></span>
                <input type="text" name="filter_search" value="<?= sanitize_string($_GET['filter_search'] ?? '') ?>"
                       placeholder="Search by user, IP address, or action..."
                       class="w-full pl-8 pr-3 py-2 text-xs bg-white dark:bg-slate-700 border border-gray-200 dark:border-slate-600 rounded-lg text-gray-900 dark:text-white placeholder-gray-400 dark:placeholder-slate-500 focus:ring-2 focus:ring-blue-500 focus:border-blue-500 outline-none transition">
            </div>

            <!-- Action Type Filter Pills -->
            <div class="flex items-center gap-1.5 flex-wrap">
                <a href="/jobhub/admin/fraud_detection.php<?= !empty($filterDate) ? '?filter_date='.urlencode($filterDate) : '' ?>"
                   class="pill-filter inline-flex px-2.5 py-1 rounded-full text-[11px] font-semibold border border-gray-200 dark:border-slate-600 <?= empty($filterAction) ? 'active' : 'text-gray-600 dark:text-slate-400' ?>">
                    All
                </a>
                <?php
                $pillColors = [
                    'login_failed'     => 'border-red-200 text-red-600 dark:border-red-800 dark:text-red-400',
                    'spam'             => 'border-orange-200 text-orange-600 dark:border-orange-800 dark:text-orange-400',
                    'phishing'         => 'border-red-300 text-red-700 dark:border-red-700 dark:text-red-300',
                    'fake_review'      => 'border-amber-200 text-amber-600 dark:border-amber-800 dark:text-amber-400',
                    'proposal_submit'  => 'border-blue-200 text-blue-600 dark:border-blue-800 dark:text-blue-400',
                    'wallet_topup'     => 'border-emerald-200 text-emerald-600 dark:border-emerald-800 dark:text-emerald-400',
                ];
                $fixedPills = ['login_failed','fake_review','phishing','proposal_submit','spam','wallet_topup'];
                foreach ($fixedPills as $pill):
                    $pillHref = '/jobhub/admin/fraud_detection.php?filter_action=' . urlencode($pill) . (!empty($filterDate) ? '&filter_date=' . urlencode($filterDate) : '');
                    $pillCls = $pillColors[$pill] ?? 'border-gray-200 text-gray-600 dark:border-slate-600 dark:text-slate-400';
                    $activeCls = ($filterAction === $pill) ? 'active' : '';
                ?>
                    <a href="<?= $pillHref ?>"
                       class="pill-filter inline-flex px-2.5 py-1 rounded-full text-[11px] font-semibold border <?= $pillCls ?> <?= $activeCls ?>">
                        <?= sanitize_string($pill) ?>
                    </a>
                <?php endforeach; ?>
            </div>

            <!-- Date Range + Buttons -->
            <div class="flex items-center gap-2 ml-auto">
                <input type="date" name="filter_date" value="<?= sanitize_string($filterDate) ?>"
                       class="text-xs border border-gray-200 dark:border-slate-600 rounded-lg px-3 py-2 bg-white dark:bg-slate-700 text-gray-900 dark:text-white focus:ring-2 focus:ring-blue-500 focus:border-blue-500 outline-none">
                <button type="submit" class="px-3 py-2 bg-blue-600 hover:bg-blue-700 text-white text-xs font-semibold rounded-lg transition shadow-sm">
                    <i data-lucide="filter" class="mr-1"></i> Filter
                </button>
                <a href="/jobhub/admin/fraud_detection.php" class="px-3 py-2 text-gray-500 dark:text-slate-400 text-xs font-medium hover:text-gray-700 dark:hover:text-slate-200 border border-gray-200 dark:border-slate-600 rounded-lg transition hover:bg-gray-50 dark:hover:bg-slate-700">
                    Clear
                </a>
            </div>
        </form>
    </div>

    <!-- Log Table -->
    <div class="overflow-x-auto">
        <table class="w-full text-sm">
            <thead>
                <tr class="border-b border-gray-100 dark:border-slate-700" style="background:#F9FAFB">
                    <th class="text-left px-5 py-2.5 text-[11px] font-bold uppercase tracking-wider text-gray-500 dark:text-slate-400">Timestamp</th>
                    <th class="text-left px-5 py-2.5 text-[11px] font-bold uppercase tracking-wider text-gray-500 dark:text-slate-400">User</th>
                    <th class="text-left px-5 py-2.5 text-[11px] font-bold uppercase tracking-wider text-gray-500 dark:text-slate-400">Action Type</th>
                    <th class="text-left px-5 py-2.5 text-[11px] font-bold uppercase tracking-wider text-gray-500 dark:text-slate-400">IP Address</th>
                    <th class="text-left px-5 py-2.5 text-[11px] font-bold uppercase tracking-wider text-gray-500 dark:text-slate-400">Actions</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-50 dark:divide-slate-700/50">
                <?php if (empty($activityLogs)): ?>
                    <tr>
                        <td colspan="5" class="px-5 py-16">
                            <div class="flex flex-col items-center justify-center text-center rounded-xl py-12 px-8" style="background:#F9FAFB">
                                <div class="w-14 h-14 rounded-full bg-gray-100 dark:bg-slate-700 flex items-center justify-center mb-4">
                                    <i data-lucide="inbox" class="text-2xl text-gray-300 dark:text-slate-500"></i>
                                </div>
                                <p class="text-sm font-semibold text-gray-700 dark:text-slate-300">No activity logs found</p>
                                <p class="text-xs text-gray-400 dark:text-slate-500 mt-1">Adjust your filters or check back later.</p>
                            </div>
                        </td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($activityLogs as $log): ?>
                        <?php
                            $actionType = $log['action_type'];
                            $badgeMap = [
                                'login_failed'     => 'bg-red-50 dark:bg-red-900/20 text-red-700 dark:text-red-400 border border-red-200 dark:border-red-800',
                                'phishing'         => 'bg-red-100 dark:bg-red-900/30 text-red-800 dark:text-red-300 border border-red-300 dark:border-red-700',
                                'payment_fraud'    => 'bg-red-50 dark:bg-red-900/20 text-red-700 dark:text-red-400 border border-red-200 dark:border-red-800',
                                'account_takeover' => 'bg-red-50 dark:bg-red-900/20 text-red-700 dark:text-red-400 border border-red-200 dark:border-red-800',
                                'spam'             => 'bg-orange-50 dark:bg-orange-900/20 text-orange-700 dark:text-orange-400 border border-orange-200 dark:border-orange-800',
                                'fake_review'      => 'bg-amber-50 dark:bg-amber-900/20 text-amber-700 dark:text-amber-400 border border-amber-200 dark:border-amber-800',
                                'suspicious_download' => 'bg-purple-50 dark:bg-purple-900/20 text-purple-700 dark:text-purple-400 border border-purple-200 dark:border-purple-800',
                                'proposal_submit'  => 'bg-blue-50 dark:bg-blue-900/20 text-blue-700 dark:text-blue-400 border border-blue-200 dark:border-blue-800',
                                'wallet_topup'     => 'bg-emerald-50 dark:bg-emerald-900/20 text-emerald-700 dark:text-emerald-400 border border-emerald-200 dark:border-emerald-800',
                            ];
                            $badgeClass = $badgeMap[$actionType] ?? 'bg-gray-50 dark:bg-slate-700 text-gray-600 dark:text-slate-400 border border-gray-200 dark:border-slate-600';
                        ?>
                        <tr class="log-row transition">
                            <!-- Timestamp -->
                            <td class="px-5 py-3 whitespace-nowrap">
                                <p class="text-xs text-gray-700 dark:text-slate-300 font-medium"><?= date('M j, Y', strtotime($log['created_at'])) ?></p>
                                <p class="text-[11px] text-gray-400 dark:text-slate-500 font-mono"><?= date('H:i:s', strtotime($log['created_at'])) ?></p>
                                <p class="text-[10px] text-gray-400 dark:text-slate-500 mt-0.5"><?= time_ago($log['created_at']) ?></p>
                            </td>
                            <!-- User -->
                            <td class="px-5 py-3">
                                <p class="text-xs font-semibold text-gray-900 dark:text-white truncate max-w-[140px]"><?= sanitize_string($log['user_name']) ?></p>
                                <p class="text-[11px] text-gray-400 dark:text-slate-500 truncate max-w-[140px]"><?= sanitize_string($log['user_email']) ?></p>
                            </td>
                            <!-- Action Type Badge -->
                            <td class="px-5 py-3">
                                <span class="inline-flex px-2 py-0.5 rounded-md text-[11px] font-bold font-mono <?= $badgeClass ?>">
                                    <?= sanitize_string($actionType) ?>
                                </span>
                            </td>
                            <!-- IP Address -->
                            <td class="px-5 py-3">
                                <div class="flex items-center gap-1.5 group">
                                    <code class="text-xs font-mono text-gray-700 dark:text-slate-300 bg-gray-50 dark:bg-slate-700 px-1.5 py-0.5 rounded border border-gray-100 dark:border-slate-600">
                                        <?= sanitize_string($log['ip_address']) ?>
                                    </code>
                                    <button onclick="navigator.clipboard.writeText('<?= sanitize_string($log['ip_address']) ?>');this.innerHTML='<i data-lucide=\'check\' class=\'text-emerald-500\'></i>';setTimeout(()=>this.innerHTML='<i data-lucide=\'copy\' class=\'text-gray-300 dark:text-slate-600\'></i>',1200)"
                                            class="copy-ip p-0.5 rounded hover:bg-gray-100 dark:hover:bg-slate-600 transition" title="Copy IP">
                                        <i data-lucide="copy" class="text-[10px] text-gray-300 dark:text-slate-600"></i>
                                    </button>
                                </div>
                            </td>
                            <!-- Actions (View Payload) -->
                            <td class="px-5 py-3">
                                <?php
                                $payload = json_decode($log['payload'] ?? '{}', true);
                                if (!empty($payload)):
                                ?>
                                    <button onclick="this.nextElementSibling.classList.toggle('hidden')" class="inline-flex items-center gap-1 px-2 py-1 text-[11px] font-semibold text-blue-600 dark:text-blue-400 bg-blue-50 dark:bg-blue-900/20 hover:bg-blue-100 dark:hover:bg-blue-900/30 rounded-md transition border border-blue-100 dark:border-blue-800">
                                        <i data-lucide="eye" class="text-[10px]"></i> View Payload
                                    </button>
                                    <pre class="hidden mt-2 bg-gray-50 dark:bg-slate-700/50 border border-gray-200 dark:border-slate-600 rounded-lg p-3 text-[11px] font-mono text-gray-600 dark:text-slate-400 max-w-sm overflow-auto leading-relaxed"><?= sanitize_string(json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)) ?></pre>
                                <?php else: ?>
                                    <span class="text-gray-300 dark:text-slate-600 text-[11px]">—</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>

    <!-- Pagination -->
    <div class="px-5 py-3 border-t border-gray-100 dark:border-slate-700 bg-gray-50/50 dark:bg-slate-700/10">
        <?php
        $paginationUrl = '/jobhub/' . $baseQuery . (strpos($baseQuery, '?') !== false ? '&' : '?') . 'page=';
        render_pagination($pagination, $paginationUrl);
        ?>
    </div>
</div>

<!-- ═══════════════════════════════════════════════════════════════════════
     RISK DETAIL DRAWER — Slide-Over Panel
     ═══════════════════════════════════════════════════════════════════════ -->
<div id="riskDrawerBackdrop" class="risk-drawer-backdrop" onclick="closeRiskDrawer()"></div>
<div id="riskDrawer" class="risk-drawer shadow-2xl" role="dialog" aria-label="Risk Detail Drawer">

    <!-- ─── Drawer Header ─────────────────────────────────────────────── -->
    <div class="flex-shrink-0 border-b border-gray-100 dark:border-slate-700">
        <div class="px-5 pt-5 pb-4">
            <div class="flex items-start justify-between gap-3">
                <!-- User Info -->
                <div class="flex items-center gap-3 min-w-0">
                    <div id="drawerAvatarWrap" class="relative flex-shrink-0">
                        <img id="drawerAvatar" src="" alt="" class="w-11 h-11 rounded-full object-cover ring-2 ring-gray-100 dark:ring-slate-700">
                        <span id="drawerStatusDot" class="absolute -bottom-0.5 -right-0.5 w-3.5 h-3.5 rounded-full border-2 border-white dark:border-slate-800"></span>
                    </div>
                    <div class="min-w-0">
                        <div class="flex items-center gap-2 flex-wrap">
                            <h3 id="drawerUserName" class="text-sm font-bold text-gray-900 dark:text-white truncate">—</h3>
                            <span id="drawerRoleBadge" class="inline-flex px-1.5 py-0.5 rounded text-[10px] font-bold uppercase tracking-wider">—</span>
                        </div>
                        <p id="drawerUserEmail" class="text-[11px] text-gray-400 dark:text-slate-500 truncate mt-0.5">—</p>
                    </div>
                </div>
                <!-- Score Pill + Close -->
                <div class="flex items-center gap-2 flex-shrink-0">
                    <span id="drawerScorePill" class="inline-flex items-center gap-1 px-2.5 py-1 rounded-lg text-xs font-bold">
                        <span id="drawerScoreValue">—</span>
                        <span id="drawerScoreLabel" class="text-[10px] font-semibold opacity-80">/ 100</span>
                    </span>
                    <button onclick="closeRiskDrawer()" class="w-8 h-8 rounded-lg flex items-center justify-center text-gray-400 dark:text-slate-500 hover:bg-gray-100 dark:hover:bg-slate-700 hover:text-gray-600 dark:hover:text-slate-300 transition" title="Close">
                        <i data-lucide="x" class="text-sm"></i>
                    </button>
                </div>
            </div>
        </div>
        <!-- Mini Risk Bar -->
        <div class="px-5 pb-4">
            <div class="w-full bg-gray-100 dark:bg-slate-700 rounded-full h-2 overflow-hidden">
                <div id="drawerScoreBar" class="h-2 rounded-full transition-all duration-700 ease-out" style="width:0%"></div>
            </div>
        </div>
    </div>

    <!-- ─── Drawer Scrollable Body ─────────────────────────────────────── -->
    <div class="risk-drawer-body">

        <!-- Loading Skeleton (shown initially, hidden after data loads) -->
        <div id="drawerSkeleton" class="px-5 py-6 space-y-5">
            <div class="space-y-2.5">
                <div class="drawer-skeleton h-3 w-32 rounded"></div>
                <div class="drawer-skeleton h-3 w-48 rounded"></div>
                <div class="drawer-skeleton h-3 w-40 rounded"></div>
            </div>
            <div class="border-t border-gray-100 dark:border-slate-700 pt-4 space-y-2.5">
                <div class="drawer-skeleton h-3 w-28 rounded"></div>
                <div class="grid grid-cols-2 gap-3">
                    <div class="drawer-skeleton h-14 rounded-lg"></div>
                    <div class="drawer-skeleton h-14 rounded-lg"></div>
                    <div class="drawer-skeleton h-14 rounded-lg"></div>
                    <div class="drawer-skeleton h-14 rounded-lg"></div>
                </div>
            </div>
        </div>

        <!-- Actual Content (hidden initially, shown after data loads) -->
        <div id="drawerContent" class="hidden px-5 py-5 space-y-5">

            <!-- ── Risk Factor Breakdown ──────────────────────────────────── -->
            <div>
                <h4 class="text-[11px] font-bold uppercase tracking-wider text-gray-400 dark:text-slate-500 mb-3 flex items-center gap-1.5">
                    <i data-lucide="triangle-alert" class="text-amber-500 text-[10px]"></i> Risk Factor Breakdown
                </h4>
                <div id="drawerRiskFactors" class="rounded-xl border border-gray-100 dark:border-slate-700 divide-y divide-gray-50 dark:divide-slate-700/50 overflow-hidden">
                    <!-- Populated by JS -->
                </div>
            </div>

            <!-- ── Device & Network Intelligence ──────────────────────────── -->
            <div>
                <h4 class="text-[11px] font-bold uppercase tracking-wider text-gray-400 dark:text-slate-500 mb-3 flex items-center gap-1.5">
                    <i data-lucide="network" class="text-blue-500 text-[10px]"></i> Device &amp; Network Intelligence
                </h4>
                <div class="grid grid-cols-2 gap-3" id="drawerNetIntel">
                    <!-- IP Address -->
                    <div class="rounded-xl border border-gray-100 dark:border-slate-700 p-3 bg-gray-50/50 dark:bg-slate-700/20">
                        <p class="text-[10px] font-bold uppercase tracking-wider text-gray-400 dark:text-slate-500 mb-1">IP Address</p>
                        <div class="flex items-center gap-1.5">
                            <code id="drawerIP" class="text-xs font-mono font-semibold text-gray-800 dark:text-slate-200 truncate">—</code>
                            <button id="drawerIPCopy" onclick="copyDrawerIP()" class="flex-shrink-0 p-1 rounded hover:bg-gray-200 dark:hover:bg-slate-600 transition" title="Copy IP">
                                <i data-lucide="copy" class="text-[10px] text-gray-400 dark:text-slate-500"></i>
                            </button>
                        </div>
                    </div>
                    <!-- Location -->
                    <div class="rounded-xl border border-gray-100 dark:border-slate-700 p-3 bg-gray-50/50 dark:bg-slate-700/20">
                        <p class="text-[10px] font-bold uppercase tracking-wider text-gray-400 dark:text-slate-500 mb-1">Location</p>
                        <p id="drawerLocation" class="text-xs font-semibold text-gray-800 dark:text-slate-200">—</p>
                    </div>
                    <!-- Device / User Agent -->
                    <div class="rounded-xl border border-gray-100 dark:border-slate-700 p-3 bg-gray-50/50 dark:bg-slate-700/20">
                        <p class="text-[10px] font-bold uppercase tracking-wider text-gray-400 dark:text-slate-500 mb-1">Device / User Agent</p>
                        <p id="drawerDevice" class="text-xs font-semibold text-gray-800 dark:text-slate-200 truncate" title="">—</p>
                    </div>
                    <!-- Associated Accounts -->
                    <div class="rounded-xl border border-gray-100 dark:border-slate-700 p-3 bg-gray-50/50 dark:bg-slate-700/20">
                        <p class="text-[10px] font-bold uppercase tracking-wider text-gray-400 dark:text-slate-500 mb-1">Associated Accounts</p>
                        <p id="drawerAssociated" class="text-xs font-semibold text-gray-800 dark:text-slate-200">—</p>
                    </div>
                </div>
            </div>

            <!-- ── Recent Activity Timeline (last 5 entries) ──────────────── -->
            <div>
                <h4 class="text-[11px] font-bold uppercase tracking-wider text-gray-400 dark:text-slate-500 mb-3 flex items-center gap-1.5">
                    <i data-lucide="history" class="text-purple-500 text-[10px]"></i> Recent Activity
                </h4>
                <div id="drawerTimeline" class="space-y-0 rounded-xl border border-gray-100 dark:border-slate-700 divide-y divide-gray-50 dark:divide-slate-700/50 overflow-hidden">
                    <!-- Populated by JS -->
                </div>
            </div>
        </div>
    </div>

    <!-- ─── Drawer Footer: Security Actions ─────────────────────────────── -->
    <div class="flex-shrink-0 border-t border-gray-100 dark:border-slate-700 bg-gray-50/80 dark:bg-slate-800/80 backdrop-blur-sm px-5 py-4">
        <input type="hidden" id="drawerCsrfToken" value="<?= generate_csrf_token() ?>">
        <input type="hidden" id="drawerUserId" value="">
        <div class="grid grid-cols-2 gap-2">
            <!-- Suspend User -->
            <button id="drawerBtnSuspend" onclick="drawerAction('suspend_user')" class="drawer-action-btn inline-flex items-center justify-center gap-1.5 px-3 py-2.5 rounded-xl text-xs font-semibold border transition" style="background:#FEF2F2;color:#991B1B;border-color:#FECACA">
                <i data-lucide="ban" class="text-[11px]"></i> Suspend User
            </button>
            <!-- Freeze Wallet -->
            <button id="drawerBtnFreeze" onclick="drawerAction('freeze_wallet')" class="drawer-action-btn inline-flex items-center justify-center gap-1.5 px-3 py-2.5 rounded-xl text-xs font-semibold border transition" style="background:#EFF6FF;color:#1E40AF;border-color:#BFDBFE">
                <i data-lucide="lock" class="text-[11px]"></i> Freeze Wallet
            </button>
            <!-- Require Re-Verification -->
            <button id="drawerBtnReverify" onclick="drawerAction('require_reverification')" class="drawer-action-btn inline-flex items-center justify-center gap-1.5 px-3 py-2.5 rounded-xl text-xs font-semibold border transition" style="background:#FFFBEB;color:#92400E;border-color:#FDE68A">
                <i data-lucide="mail-open" class="text-[11px]"></i> Require Re-Verification
            </button>
            <!-- Dismiss Flag / Mark Safe -->
            <button id="drawerBtnDismiss" onclick="drawerAction('unflag_user')" class="drawer-action-btn inline-flex items-center justify-center gap-1.5 px-3 py-2.5 rounded-xl text-xs font-semibold border transition" style="background:#ECFDF5;color:#065F46;border-color:#A7F3D0">
                <i data-lucide="shield-check" class="text-[11px]"></i> Dismiss Flag
            </button>
        </div>
    </div>
</div>

<!-- CSRF token for JS -->
<input type="hidden" id="csrfToken" value="<?= generate_csrf_token() ?>">
<?php require_once __DIR__ . '/../components/layout_end.php'; ?>