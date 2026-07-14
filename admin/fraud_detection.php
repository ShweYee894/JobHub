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
    if ((int) $q1->get_result()->fetch_assoc()['cnt'] > 10) $recalcScore += 20;
    $q1->close();

    $q2 = $conn->prepare('SELECT COUNT(DISTINCT ip_address) AS cnt FROM user_behavior_logs WHERE user_id = ? AND created_at >= DATE_SUB(NOW(), INTERVAL 24 HOUR)');
    $q2->bind_param('i', $recalcUid);
    $q2->execute();
    if ((int) $q2->get_result()->fetch_assoc()['cnt'] > 1) $recalcScore += 15;
    $q2->close();

    $q3 = $conn->prepare("SELECT COUNT(*) AS cnt FROM user_behavior_logs WHERE user_id = ? AND action_type = 'proposal_submit' AND created_at >= DATE_SUB(NOW(), INTERVAL 1 HOUR)");
    $q3->bind_param('i', $recalcUid);
    $q3->execute();
    if ((int) $q3->get_result()->fetch_assoc()['cnt'] > 5) $recalcScore += 25;
    $q3->close();

    $q4 = $conn->prepare("SELECT COUNT(*) AS cnt FROM user_behavior_logs WHERE user_id = ? AND action_type = 'login_failed' AND created_at >= DATE_SUB(NOW(), INTERVAL 24 HOUR)");
    $q4->bind_param('i', $recalcUid);
    $q4->execute();
    $q4Cnt = (int) $q4->get_result()->fetch_assoc()['cnt'];
    $q4->close();
    if ($q4Cnt > 0) $recalcScore += min($q4Cnt * 10, 30);

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
    ['key' => 'dashboard', 'label' => 'Dashboard', 'url' => 'dashboard.php', 'icon' => 'fa-th-large'],
    ['key' => 'users', 'label' => 'Users', 'url' => 'users.php', 'icon' => 'fa-users'],
    ['key' => 'jobs', 'label' => 'Jobs', 'url' => 'jobs.php', 'icon' => 'fa-briefcase'],
    ['key' => 'payments', 'label' => 'Payments', 'url' => 'payments.php', 'icon' => 'fa-credit-card'],
    ['key' => 'fraud', 'label' => 'Fraud', 'url' => 'fraud_detection.php', 'icon' => 'fa-shield-halved'],
    ['key' => 'matching', 'label' => 'AI Matching', 'url' => 'ai_matching.php', 'icon' => 'fa-brain'],
    ['key' => 'settings', 'label' => 'Settings', 'url' => 'settings.php', 'icon' => 'fa-cog'],
];
$pageTitle = 'Fraud Detection';
$pageSubtitle = 'Monitor and manage suspicious platform activity';
$activePage = 'fraud';
$user = ['name' => $adminName, 'profile_image' => $_navUserRow['profile_image'] ?? null];
$unreadCount = 0;
$profileLink = 'profile.php';
require_once __DIR__ . '/../components/layout_start.php';
?>
<script src="/finalproject/assets/js/fraud.js" defer></script>
<style>
    .score-bar {
        transition: width 0.5s ease-in-out;
    }
</style>


<!-- Header -->
<div class="flex items-center justify-between mb-8">
    <div>
        <h1 class="text-2xl font-bold text-gray-900 flex items-center gap-2">
            <i class="fas fa-exclamation-triangle text-red-500"></i> Fraud Detection
        </h1>
        <p class="text-gray-500 text-sm mt-1">Monitor and manage suspicious platform activity</p>
    </div>
    <button onclick="refreshScores()" class="px-4 py-2 bg-blue-600 text-white rounded-lg text-sm font-medium hover:bg-blue-700 transition flex items-center gap-2">
        <i class="fas fa-sync-alt" id="refreshIcon"></i> Recalculate All Scores
    </button>
</div>

<?php display_flash('success'); ?>
<?php display_flash('error'); ?>

<!-- Overview Cards -->
<div class="grid grid-cols-1 md:grid-cols-4 gap-6 mb-8" id="overviewCards">
    <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-6">
        <div class="flex items-center gap-4">
            <div class="w-12 h-12 rounded-xl bg-red-100 flex items-center justify-center">
                <i class="fas fa-flag text-red-600 text-xl"></i>
            </div>
            <div>
                <p class="text-sm text-gray-500">Flagged Users</p>
                <p class="text-2xl font-bold text-gray-900" id="countFlagged"><?= $counts['flagged'] ?></p>
            </div>
        </div>
    </div>
    <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-6">
        <div class="flex items-center gap-4">
            <div class="w-12 h-12 rounded-xl bg-orange-100 flex items-center justify-center">
                <i class="fas fa-exclamation-circle text-orange-600 text-xl"></i>
            </div>
            <div>
                <p class="text-sm text-gray-500">High Risk (70+)</p>
                <p class="text-2xl font-bold text-gray-900" id="countHigh"><?= $counts['high_risk'] ?></p>
            </div>
        </div>
    </div>
    <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-6">
        <div class="flex items-center gap-4">
            <div class="w-12 h-12 rounded-xl bg-yellow-100 flex items-center justify-center">
                <i class="fas fa-exclamation-triangle text-yellow-600 text-xl"></i>
            </div>
            <div>
                <p class="text-sm text-gray-500">Medium Risk (40-69)</p>
                <p class="text-2xl font-bold text-gray-900" id="countMedium"><?= $counts['medium_risk'] ?></p>
            </div>
        </div>
    </div>
    <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-6">
        <div class="flex items-center gap-4">
            <div class="w-12 h-12 rounded-xl bg-purple-100 flex items-center justify-center">
                <i class="fas fa-bolt text-purple-600 text-xl"></i>
            </div>
            <div>
                <p class="text-sm text-gray-500">Suspicious Actions (24h)</p>
                <p class="text-2xl font-bold text-gray-900" id="countActions"><?= $counts['actions_24h'] ?></p>
            </div>
        </div>
    </div>
</div>

<!-- Suspicious Users Table -->
<div class="bg-white rounded-xl shadow-sm border border-gray-200 mb-8">
    <div class="px-6 py-4 border-b border-gray-200">
        <h2 class="text-lg font-semibold text-gray-900 flex items-center gap-2">
            <i class="fas fa-user-shield text-red-500"></i> Suspicious Users
        </h2>
    </div>
    <div class="overflow-x-auto">
        <table class="w-full text-sm">
            <thead class="bg-gray-50 border-b border-gray-200">
                <tr>
                    <th class="text-left px-6 py-3 font-semibold text-gray-600">User</th>
                    <th class="text-left px-6 py-3 font-semibold text-gray-600">Role</th>
                    <th class="text-left px-6 py-3 font-semibold text-gray-600">Fraud Score</th>
                    <th class="text-left px-6 py-3 font-semibold text-gray-600">Status</th>
                    <th class="text-left px-6 py-3 font-semibold text-gray-600">Last Action</th>
                    <th class="text-left px-6 py-3 font-semibold text-gray-600">IPs (24h)</th>
                    <th class="text-left px-6 py-3 font-semibold text-gray-600">Actions</th>
                </tr>
            </thead>
            <tbody id="suspiciousTableBody" class="divide-y divide-gray-100">
                <?php if (empty($suspiciousUsers)): ?>
                    <tr>
                        <td colspan="7" class="px-6 py-12 text-center text-gray-400">
                            <i class="fas fa-shield-alt text-4xl mb-3"></i>
                            <p class="font-medium">No suspicious users found</p>
                            <p class="text-sm">All users appear to be operating normally.</p>
                        </td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($suspiciousUsers as $user): ?>
                        <tr class="hover:bg-gray-50 transition" data-user-id="<?= $user['id'] ?>">
                            <td class="px-6 py-4">
                                <div class="flex items-center gap-3">
                                    <img src="<?= get_profile_image($user['profile_image']) ?>" alt="" class="w-9 h-9 rounded-full object-cover ring-2 ring-gray-200">
                                    <div>
                                        <p class="font-medium text-gray-900"><?= sanitize_string($user['name']) ?></p>
                                        <p class="text-xs text-gray-500"><?= sanitize_string($user['email']) ?></p>
                                    </div>
                                </div>
                            </td>
                            <td class="px-6 py-4">
                                <span class="px-2.5 py-1 rounded-full text-xs font-medium <?= $user['role'] === 'admin' ? 'bg-purple-100 text-purple-700' : ($user['role'] === 'client' ? 'bg-blue-100 text-blue-700' : 'bg-green-100 text-green-700') ?>">
                                    <?= sanitize_string(ucfirst($user['role'])) ?>
                                </span>
                            </td>
                            <td class="px-6 py-4">
                                <div class="flex items-center gap-3">
                                    <div class="w-32 bg-gray-200 rounded-full h-2.5">
                                        <?php
                                        $scoreClass = 'bg-green-500';
                                        if ($user['fraud_score'] > 60)
                                            $scoreClass = 'bg-red-500';
                                        elseif ($user['fraud_score'] > 30)
                                            $scoreClass = 'bg-yellow-500';
                                        ?>
                                        <div class="<?= $scoreClass ?> h-2.5 rounded-full score-bar" style="width: <?= $user['fraud_score'] ?>%"></div>
                                    </div>
                                    <span class="font-bold text-sm <?= $user['fraud_score'] > 60 ? 'text-red-600' : ($user['fraud_score'] > 30 ? 'text-yellow-600' : 'text-green-600') ?>">
                                        <?= $user['fraud_score'] ?>
                                    </span>
                                </div>
                            </td>
                            <td class="px-6 py-4">
                                <?php
                                $statusStyles = [
                                    'active' => 'bg-green-100 text-green-700',
                                    'flagged' => 'bg-red-100 text-red-700',
                                    'suspended' => 'bg-gray-100 text-gray-700',
                                ];
                                $statusStyle = $statusStyles[$user['status']] ?? 'bg-gray-100 text-gray-700';
                                ?>
                                <span class="px-2.5 py-1 rounded-full text-xs font-semibold <?= $statusStyle ?>">
                                    <?= sanitize_string(ucfirst($user['status'])) ?>
                                </span>
                            </td>
                            <td class="px-6 py-4">
                                <?php if ($user['last_action']): ?>
                                    <p class="text-gray-900 font-medium text-xs"><?= sanitize_string($user['last_action']) ?></p>
                                    <p class="text-xs text-gray-400"><?= time_ago($user['last_action_time']) ?></p>
                                <?php else: ?>
                                    <span class="text-gray-400 text-xs">No activity</span>
                                <?php endif; ?>
                            </td>
                            <td class="px-6 py-4">
                                <span class="font-semibold <?= ($user['unique_ips_24h'] ?? 0) > 3 ? 'text-red-600' : 'text-gray-700' ?>">
                                    <?= $user['unique_ips_24h'] ?? 0 ?>
                                </span>
                            </td>
                            <td class="px-6 py-4">
                                <div class="flex items-center gap-2">
                                    <button onclick="viewUserActivity(<?= $user['id'] ?>)" class="p-2 text-blue-600 hover:bg-blue-50 rounded-lg transition" title="View Activity">
                                        <i class="fas fa-eye text-sm"></i>
                                    </button>
                                    <?php if ($user['status'] !== 'flagged'): ?>
                                        <form method="POST" action="/finalproject/admin/fraud_action.php" class="inline" onsubmit="return confirm('Flag this user?')">
                                            <?= csrf_field() ?>
                                            <input type="hidden" name="action" value="flag_user">
                                            <input type="hidden" name="user_id" value="<?= $user['id'] ?>">
                                            <button type="submit" class="p-2 text-yellow-600 hover:bg-yellow-50 rounded-lg transition" title="Flag User">
                                                <i class="fas fa-flag text-sm"></i>
                                            </button>
                                        </form>
                                    <?php else: ?>
                                        <form method="POST" action="/finalproject/admin/fraud_action.php" class="inline" onsubmit="return confirm('Unflag this user and reset score?')">
                                            <?= csrf_field() ?>
                                            <input type="hidden" name="action" value="unflag_user">
                                            <input type="hidden" name="user_id" value="<?= $user['id'] ?>">
                                            <button type="submit" class="p-2 text-green-600 hover:bg-green-50 rounded-lg transition" title="Unflag User">
                                                <i class="fas fa-check-circle text-sm"></i>
                                            </button>
                                        </form>
                                    <?php endif; ?>
                                    <?php if ($user['status'] !== 'suspended'): ?>
                                        <form method="POST" action="/finalproject/admin/fraud_action.php" class="inline" onsubmit="return confirm('SUSPEND this user? This will prevent them from using the platform.')">
                                            <?= csrf_field() ?>
                                            <input type="hidden" name="action" value="suspend_user">
                                            <input type="hidden" name="user_id" value="<?= $user['id'] ?>">
                                            <button type="submit" class="p-2 text-red-600 hover:bg-red-50 rounded-lg transition" title="Suspend User">
                                                <i class="fas fa-ban text-sm"></i>
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

<!-- Activity Log -->
<div class="bg-white rounded-xl shadow-sm border border-gray-200">
    <div class="px-6 py-4 border-b border-gray-200">
        <div class="flex items-center justify-between">
            <h2 class="text-lg font-semibold text-gray-900 flex items-center gap-2">
                <i class="fas fa-list-alt text-blue-500"></i> Recent Suspicious Activity
            </h2>
            <span class="text-sm text-gray-500"><?= number_format($totalLogs) ?> total entries</span>
        </div>
    </div>

    <!-- Filters -->
    <div class="px-6 py-4 border-b border-gray-100 bg-gray-50">
        <form method="GET" class="flex items-center gap-4 flex-wrap" id="filterForm">
            <div class="flex items-center gap-2">
                <label class="text-sm font-medium text-gray-600">Action Type:</label>
                <select name="filter_action" class="text-sm border border-gray-300 rounded-lg px-3 py-2 focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                    <option value="">All Actions</option>
                    <?php foreach ($actionTypes as $at): ?>
                        <option value="<?= sanitize_string($at) ?>" <?= $filterAction === $at ? 'selected' : '' ?>><?= sanitize_string($at) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="flex items-center gap-2">
                <label class="text-sm font-medium text-gray-600">Date:</label>
                <input type="date" name="filter_date" value="<?= sanitize_string($filterDate) ?>" class="text-sm border border-gray-300 rounded-lg px-3 py-2 focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
            </div>
            <button type="submit" class="px-4 py-2 bg-gray-600 text-white rounded-lg text-sm font-medium hover:bg-gray-700 transition">Filter</button>
            <a href="/finalproject/admin/fraud_detection.php" class="px-4 py-2 text-gray-500 text-sm font-medium hover:text-gray-700 transition">Clear</a>
        </form>
    </div>

    <div class="overflow-x-auto">
        <table class="w-full text-sm">
            <thead class="bg-gray-50 border-b border-gray-200">
                <tr>
                    <th class="text-left px-6 py-3 font-semibold text-gray-600">Time</th>
                    <th class="text-left px-6 py-3 font-semibold text-gray-600">User</th>
                    <th class="text-left px-6 py-3 font-semibold text-gray-600">Action Type</th>
                    <th class="text-left px-6 py-3 font-semibold text-gray-600">IP Address</th>
                    <th class="text-left px-6 py-3 font-semibold text-gray-600">Details</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-100">
                <?php if (empty($activityLogs)): ?>
                    <tr>
                        <td colspan="5" class="px-6 py-12 text-center text-gray-400">
                            <i class="fas fa-inbox text-4xl mb-3"></i>
                            <p class="font-medium">No activity logs found</p>
                            <p class="text-sm">Adjust your filters or check back later.</p>
                        </td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($activityLogs as $log): ?>
                        <tr class="hover:bg-gray-50 transition">
                            <td class="px-6 py-3 text-gray-500 text-xs whitespace-nowrap">
                                <?= date('M j, Y H:i', strtotime($log['created_at'])) ?>
                                <br><span class="text-gray-400"><?= time_ago($log['created_at']) ?></span>
                            </td>
                            <td class="px-6 py-3">
                                <p class="font-medium text-gray-900 text-xs"><?= sanitize_string($log['user_name']) ?></p>
                                <p class="text-xs text-gray-400"><?= sanitize_string($log['user_email']) ?></p>
                            </td>
                            <td class="px-6 py-3">
                                <?php
                                $actionColors = [
                                    'login_failed' => 'bg-red-100 text-red-700',
                                    'spam' => 'bg-orange-100 text-orange-700',
                                    'phishing' => 'bg-red-100 text-red-700',
                                    'fake_review' => 'bg-yellow-100 text-yellow-700',
                                    'payment_fraud' => 'bg-red-100 text-red-700',
                                    'account_takeover' => 'bg-red-100 text-red-700',
                                    'suspicious_download' => 'bg-purple-100 text-purple-700',
                                    'proposal_submit' => 'bg-blue-100 text-blue-700',
                                ];
                                $actionColor = $actionColors[$log['action_type']] ?? 'bg-gray-100 text-gray-700';
                                ?>
                                <span class="px-2.5 py-1 rounded-full text-xs font-semibold <?= $actionColor ?>">
                                    <?= sanitize_string($log['action_type']) ?>
                                </span>
                            </td>
                            <td class="px-6 py-3 font-mono text-xs text-gray-600">
                                <?= sanitize_string($log['ip_address']) ?>
                            </td>
                            <td class="px-6 py-3">
                                <?php
                                $payload = json_decode($log['payload'] ?? '{}', true);
                                if (!empty($payload)):
                                ?>
                                    <button onclick="this.nextElementSibling.classList.toggle('hidden')" class="text-blue-600 text-xs hover:underline">
                                        <i class="fas fa-code"></i> View
                                    </button>
                                    <pre class="hidden mt-1 bg-gray-50 border border-gray-200 rounded-lg p-2 text-xs text-gray-600 max-w-xs overflow-auto"><?= sanitize_string(json_encode($payload, JSON_PRETTY_PRINT)) ?></pre>
                                <?php else: ?>
                                    <span class="text-gray-400 text-xs">-</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>

    <!-- Pagination -->
    <?php
    $paginationUrl = '/finalproject/' . $baseQuery . (strpos($baseQuery, '?') !== false ? '&' : '?') . 'page=';
    render_pagination($pagination, $paginationUrl);
    ?>
</div>

<!-- User Activity Modal -->
<div id="activityModal" class="hidden fixed inset-0 bg-black/50 z-50 flex items-center justify-center p-4">
    <div class="bg-white rounded-2xl shadow-2xl w-full max-w-2xl max-h-[80vh] overflow-hidden fade-in">
        <div class="px-6 py-4 border-b border-gray-200 flex items-center justify-between bg-gray-50 rounded-t-2xl">
            <h3 class="text-lg font-semibold text-gray-900 flex items-center gap-2">
                <i class="fas fa-history text-blue-500"></i> User Activity Log
            </h3>
            <button onclick="closeActivityModal()" class="text-gray-400 hover:text-gray-600 text-xl">&times;</button>
        </div>
        <div id="activityModalContent" class="p-6 overflow-y-auto max-h-[60vh]">
            <div class="text-center py-8 text-gray-400">
                <i class="fas fa-spinner fa-spin text-2xl mb-2"></i>
                <p>Loading activity...</p>
            </div>
        </div>
    </div>
</div>

<!-- CSRF token for JS -->
<input type="hidden" id="csrfToken" value="<?= generate_csrf_token() ?>">
<?php require_once __DIR__ . '/../components/layout_end.php'; ?>