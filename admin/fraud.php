<?php

/**
 * Admin Fraud Monitoring Dashboard
 * Monitor suspicious users, review fraud scores, take action on flagged accounts.
 */
require_once __DIR__ . '/../auth/auth.php';
require_role('admin');
require_once __DIR__ . '/../config/db.php';

$currentPage = 'fraud';

// ── Filters ─────────────────────────────────────────────────────────
$search = trim($_GET['search'] ?? '');
$roleF = $_GET['role'] ?? '';
$statusF = $_GET['status'] ?? '';
$scoreF = $_GET['score'] ?? '';
$sortF = $_GET['sort'] ?? '';
$page = max(1, sanitize_int($_GET['page'] ?? 1));
$perPage = 15;

$allowedRoles = ['client', 'freelancer', 'admin'];
$allowedStatuses = ['active', 'flagged', 'suspended'];
$allowedScores = ['low', 'medium', 'high', 'critical'];
$allowedSorts = ['score_desc', 'score_asc', 'newest', 'oldest'];

if ($roleF && !in_array($roleF, $allowedRoles))
    $roleF = '';
if ($statusF && !in_array($statusF, $allowedStatuses))
    $statusF = '';
if ($scoreF && !in_array($scoreF, $allowedScores))
    $scoreF = '';
if ($sortF && !in_array($sortF, $allowedSorts))
    $sortF = '';

// ── Dashboard Stats ─────────────────────────────────────────────────
$stats = [];

$stmt = $conn->prepare("SELECT COUNT(*) AS cnt FROM users WHERE status = 'flagged'");
$stmt->execute();
$stats['flagged'] = (int) $stmt->get_result()->fetch_assoc()['cnt'];
$stmt->close();

$stmt = $conn->prepare("SELECT COUNT(*) AS cnt FROM users WHERE status = 'suspended'");
$stmt->execute();
$stats['suspended'] = (int) $stmt->get_result()->fetch_assoc()['cnt'];
$stmt->close();

$stmt = $conn->prepare('SELECT COUNT(*) AS cnt FROM users WHERE fraud_score >= 70');
$stmt->execute();
$stats['high_risk'] = (int) $stmt->get_result()->fetch_assoc()['cnt'];
$stmt->close();

$stmt = $conn->prepare("SELECT COUNT(*) AS cnt FROM user_behavior_logs WHERE DATE(created_at) = CURDATE()");
$stmt->execute();
$stats['logs_today'] = (int) $stmt->get_result()->fetch_assoc()['cnt'];
$stmt->close();

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
if ($statusF) {
    $where[] = 'u.status = ?';
    $params[] = $statusF;
    $types .= 's';
}
if ($scoreF) {
    switch ($scoreF) {
        case 'low':
            $where[] = 'u.fraud_score BETWEEN 0 AND 29';
            break;
        case 'medium':
            $where[] = 'u.fraud_score BETWEEN 30 AND 69';
            break;
        case 'high':
            $where[] = 'u.fraud_score BETWEEN 70 AND 89';
            break;
        case 'critical':
            $where[] = 'u.fraud_score >= 90';
            break;
    }
}

$whereSql = $where ? 'WHERE ' . implode(' AND ', $where) : '';

// ── Count total ─────────────────────────────────────────────────────
$countSql = "SELECT COUNT(*) AS cnt FROM users u {$whereSql}";
$countStmt = $conn->prepare($countSql);
if ($params) {
    $countStmt->bind_param($types, ...$params);
}
$countStmt->execute();
$totalItems = (int) $countStmt->get_result()->fetch_assoc()['cnt'];
$countStmt->close();

$pagination = paginate($totalItems, $perPage, $page);

// ── Sort ────────────────────────────────────────────────────────────
$orderSql = 'ORDER BY u.fraud_score DESC, u.created_at DESC';
switch ($sortF) {
    case 'score_asc':
        $orderSql = 'ORDER BY u.fraud_score ASC';
        break;
    case 'newest':
        $orderSql = 'ORDER BY u.created_at DESC';
        break;
    case 'oldest':
        $orderSql = 'ORDER BY u.created_at ASC';
        break;
    default:
        $orderSql = 'ORDER BY u.fraud_score DESC, u.created_at DESC';
}

// ── Fetch users with last login activity and most recent suspicious action ──
$querySql = "SELECT u.id, u.name, u.email, u.role, u.status, u.fraud_score, u.profile_image, u.created_at,
                    last_login.last_action AS last_login_action,
                    last_login.last_action_time AS last_login_time,
                    last_login.ip_address AS last_ip,
                    suspicious.last_action AS last_suspicious_action,
                    suspicious.last_action_time AS last_suspicious_time
             FROM users u
             LEFT JOIN (
                 SELECT user_id, action_type AS last_action, created_at AS last_action_time, ip_address
                 FROM user_behavior_logs
                 WHERE action_type IN ('login', 'login_failed')
                 ORDER BY created_at DESC
             ) last_login ON last_login.user_id = u.id
             LEFT JOIN (
                 SELECT user_id, action_type AS last_action, created_at AS last_action_time
                 FROM user_behavior_logs
                 WHERE action_type NOT IN ('login', 'login_failed', 'register', 'job_posted', 'proposal_sent', 'payment_made', 'contract_create', 'review_posted')
                 ORDER BY created_at DESC
             ) suspicious ON suspicious.user_id = u.id
             {$whereSql}
             {$orderSql}
             LIMIT ? OFFSET ?";
$queryStmt = $conn->prepare($querySql);
$bindTypes = $types . 'ii';
$bindParams = array_merge($params, [$perPage, $pagination['offset']]);
$queryStmt->bind_param($bindTypes, ...$bindParams);
$queryStmt->execute();
$usersResult = $queryStmt->get_result();
$queryStmt->close();

// ── Badge maps ──────────────────────────────────────────────────────
$roleColors = [
    'client' => 'bg-blue-50 text-blue-600 border-blue-200',
    'freelancer' => 'bg-emerald-50 text-emerald-600 border-emerald-200',
    'admin' => 'bg-purple-50 text-purple-600 border-purple-200',
];

$statusColors = [
    'active' => 'bg-emerald-50 text-emerald-600 border-emerald-200',
    'flagged' => 'bg-yellow-50 text-yellow-600 border-yellow-200',
    'suspended' => 'bg-red-50 text-red-600 border-red-200',
];

function fraudScoreClasses(int $score): array
{
    if ($score >= 80) {
        return ['text-red-600', 'bg-red-50', 'border-red-200'];
    }
    if ($score >= 50) {
        return ['text-yellow-600', 'bg-yellow-50', 'border-yellow-200'];
    }
    return ['text-emerald-600', 'bg-emerald-50', 'border-emerald-200'];
}

function fraudBarColor(int $score): string
{
    if ($score >= 80) return 'bg-red-500';
    if ($score >= 50) return 'bg-yellow-500';
    return 'bg-emerald-500';
}

$actionBadgeColors = [
    'login_failed' => 'bg-red-50 text-red-600 border-red-200',
    'spam' => 'bg-orange-50 text-orange-600 border-orange-200',
    'phishing' => 'bg-red-50 text-red-600 border-red-200',
    'fake_review' => 'bg-yellow-50 text-yellow-600 border-yellow-200',
    'payment_fraud' => 'bg-red-50 text-red-600 border-red-200',
    'account_takeover' => 'bg-red-50 text-red-600 border-red-200',
    'suspicious_download' => 'bg-purple-50 text-purple-600 border-purple-200',
    'admin_flag_user' => 'bg-gray-50 text-gray-600 border-gray-200',
    'admin_suspend_user' => 'bg-gray-50 text-gray-600 border-gray-200',
    'admin_reset_score' => 'bg-gray-50 text-gray-600 border-gray-200',
];

// Build base URL for pagination
$baseUrl = 'fraud.php?';
if ($search !== '') $baseUrl .= 'search=' . urlencode($search) . '&';
if ($roleF) $baseUrl .= 'role=' . urlencode($roleF) . '&';
if ($statusF) $baseUrl .= 'status=' . urlencode($statusF) . '&';
if ($scoreF) $baseUrl .= 'score=' . urlencode($scoreF) . '&';
if ($sortF) $baseUrl .= 'sort=' . urlencode($sortF) . '&';
$baseUrl = rtrim($baseUrl, '?&');
if (strpos($baseUrl, '&') === false) $baseUrl = rtrim($baseUrl, '?');

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
$pageTitle = 'Fraud Monitoring';
$pageSubtitle = 'Monitor suspicious activity and manage flagged accounts';
$activePage = 'fraud';
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
    .score-bar{transition:width .6s ease-in-out}
    </style>



                <?php display_flash('success') ?>
                <?php display_flash('error') ?>
                <?php display_flash('info') ?>

                <!-- ═══ STAT CARDS ═══════════════════════════════════════ -->
                <div class="grid grid-cols-1 sm:grid-cols-2 xl:grid-cols-4 gap-4">

                    <div class="stat-card bg-white rounded-2xl p-5 border border-gray-100 shadow-sm fade-in">
                        <div class="flex items-center justify-between mb-3">
                            <div class="w-11 h-11 rounded-xl bg-yellow-50 flex items-center justify-center">
                                <i class="fas fa-flag text-yellow-500"></i>
                            </div>
                            <span class="text-[10px] font-semibold text-yellow-400 uppercase tracking-wider">Flagged</span>
                        </div>
                        <p class="text-2xl font-black text-gray-900"><?= $stats['flagged'] ?></p>
                        <p class="text-xs text-gray-400 mt-1">Flagged Users</p>
                    </div>

                    <div class="stat-card bg-white rounded-2xl p-5 border border-gray-100 shadow-sm fade-in" style="animation-delay:.05s">
                        <div class="flex items-center justify-between mb-3">
                            <div class="w-11 h-11 rounded-xl bg-red-50 flex items-center justify-center">
                                <i class="fas fa-ban text-red-500"></i>
                            </div>
                            <span class="text-[10px] font-semibold text-red-400 uppercase tracking-wider">Suspended</span>
                        </div>
                        <p class="text-2xl font-black text-gray-900"><?= $stats['suspended'] ?></p>
                        <p class="text-xs text-gray-400 mt-1">Suspended Users</p>
                    </div>

                    <div class="stat-card bg-white rounded-2xl p-5 border border-gray-100 shadow-sm fade-in" style="animation-delay:.1s">
                        <div class="flex items-center justify-between mb-3">
                            <div class="w-11 h-11 rounded-xl bg-orange-50 flex items-center justify-center">
                                <i class="fas fa-exclamation-triangle text-orange-500"></i>
                            </div>
                            <span class="text-[10px] font-semibold text-orange-400 uppercase tracking-wider">High Risk</span>
                        </div>
                        <p class="text-2xl font-black text-gray-900"><?= $stats['high_risk'] ?></p>
                        <p class="text-xs text-gray-400 mt-1">High Risk Accounts</p>
                    </div>

                    <div class="stat-card bg-white rounded-2xl p-5 border border-gray-100 shadow-sm fade-in" style="animation-delay:.15s">
                        <div class="flex items-center justify-between mb-3">
                            <div class="w-11 h-11 rounded-xl bg-violet-50 flex items-center justify-center">
                                <i class="fas fa-clock text-violet-500"></i>
                            </div>
                            <span class="text-[10px] font-semibold text-violet-400 uppercase tracking-wider">Today</span>
                        </div>
                        <p class="text-2xl font-black text-gray-900"><?= $stats['logs_today'] ?></p>
                        <p class="text-xs text-gray-400 mt-1">Fraud Logs Today</p>
                    </div>

                </div>

                <!-- ═══ SEARCH & FILTERS ════════════════════════════════════ -->
                <div class="bg-white rounded-2xl border border-gray-100 shadow-sm p-5 fade-in" style="animation-delay:.2s">
                    <form method="GET" action="fraud.php" class="flex flex-col sm:flex-row gap-3 flex-wrap">
                        <div class="flex-1 relative min-w-[200px]">
                            <i class="fas fa-search absolute left-3.5 top-1/2 -translate-y-1/2 text-gray-400 text-sm"></i>
                            <input type="text" name="search" value="<?= sanitize_string($search) ?>" placeholder="Search by name or email..."
                                   class="w-full pl-10 pr-4 py-2.5 rounded-xl border border-gray-200 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500/30 focus:border-blue-400 transition-all">
                        </div>
                        <select name="role" class="px-4 py-2.5 rounded-xl border border-gray-200 text-sm text-gray-600 focus:outline-none focus:ring-2 focus:ring-blue-500/30 focus:border-blue-400 bg-white">
                            <option value="">All Roles</option>
                            <option value="client" <?= $roleF === 'client' ? 'selected' : '' ?>>Client</option>
                            <option value="freelancer" <?= $roleF === 'freelancer' ? 'selected' : '' ?>>Freelancer</option>
                            <option value="admin" <?= $roleF === 'admin' ? 'selected' : '' ?>>Admin</option>
                        </select>
                        <select name="status" class="px-4 py-2.5 rounded-xl border border-gray-200 text-sm text-gray-600 focus:outline-none focus:ring-2 focus:ring-blue-500/30 focus:border-blue-400 bg-white">
                            <option value="">All Statuses</option>
                            <option value="active" <?= $statusF === 'active' ? 'selected' : '' ?>>Active</option>
                            <option value="flagged" <?= $statusF === 'flagged' ? 'selected' : '' ?>>Flagged</option>
                            <option value="suspended" <?= $statusF === 'suspended' ? 'selected' : '' ?>>Suspended</option>
                        </select>
                        <select name="score" class="px-4 py-2.5 rounded-xl border border-gray-200 text-sm text-gray-600 focus:outline-none focus:ring-2 focus:ring-blue-500/30 focus:border-blue-400 bg-white">
                            <option value="">All Scores</option>
                            <option value="low" <?= $scoreF === 'low' ? 'selected' : '' ?>>Low (0-29)</option>
                            <option value="medium" <?= $scoreF === 'medium' ? 'selected' : '' ?>>Medium (30-69)</option>
                            <option value="high" <?= $scoreF === 'high' ? 'selected' : '' ?>>High (70-89)</option>
                            <option value="critical" <?= $scoreF === 'critical' ? 'selected' : '' ?>>Critical (90+)</option>
                        </select>
                        <select name="sort" class="px-4 py-2.5 rounded-xl border border-gray-200 text-sm text-gray-600 focus:outline-none focus:ring-2 focus:ring-blue-500/30 focus:border-blue-400 bg-white">
                            <option value="">Highest Score First</option>
                            <option value="score_asc" <?= $sortF === 'score_asc' ? 'selected' : '' ?>>Lowest Score First</option>
                            <option value="newest" <?= $sortF === 'newest' ? 'selected' : '' ?>>Newest Accounts</option>
                            <option value="oldest" <?= $sortF === 'oldest' ? 'selected' : '' ?>>Oldest Accounts</option>
                        </select>
                        <button type="submit" class="btn-grad text-white px-6 py-2.5 rounded-xl text-sm font-semibold">
                            <i class="fas fa-filter mr-1.5"></i> Filter
                        </button>
                        <?php if ($search || $roleF || $statusF || $scoreF || $sortF): ?>
                            <a href="fraud.php" class="px-4 py-2.5 rounded-xl border border-gray-200 text-sm font-medium text-gray-500 hover:bg-gray-50 transition-colors text-center">
                                <i class="fas fa-times mr-1"></i> Clear
                            </a>
                        <?php endif; ?>
                    </form>
                </div>

                <!-- ═══ USERS TABLE ══════════════════════════════════════════ -->
                <div class="bg-white rounded-2xl border border-gray-100 shadow-sm overflow-hidden fade-in" style="animation-delay:.25s">
                    <?php if ($usersResult->num_rows > 0): ?>
                    <div class="overflow-x-auto">
                        <table class="w-full text-sm">
                            <thead>
                                <tr class="border-b border-gray-100 bg-gray-50/50">
                                    <th class="text-left py-4 px-6 text-xs font-semibold text-gray-400 uppercase tracking-wider">User</th>
                                    <th class="text-left py-4 px-6 text-xs font-semibold text-gray-400 uppercase tracking-wider">Role</th>
                                    <th class="text-center py-4 px-6 text-xs font-semibold text-gray-400 uppercase tracking-wider">Fraud Score</th>
                                    <th class="text-left py-4 px-6 text-xs font-semibold text-gray-400 uppercase tracking-wider">Status</th>
                                    <th class="text-left py-4 px-6 text-xs font-semibold text-gray-400 uppercase tracking-wider">Last Login Activity</th>
                                    <th class="text-left py-4 px-6 text-xs font-semibold text-gray-400 uppercase tracking-wider">Recent Suspicious Action</th>
                                    <th class="text-left py-4 px-6 text-xs font-semibold text-gray-400 uppercase tracking-wider">IP Address</th>
                                    <th class="text-right py-4 px-6 text-xs font-semibold text-gray-400 uppercase tracking-wider">Joined</th>
                                    <th class="text-right py-4 px-6 text-xs font-semibold text-gray-400 uppercase tracking-wider">Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                            <?php while ($u = $usersResult->fetch_assoc()):
                                $score = (int) $u['fraud_score'];
                                [$scoreText, $scoreBg, $scoreBorder] = fraudScoreClasses($score);
                            ?>
                                <tr class="border-b border-gray-50 last:border-0 hover:bg-gray-50/50 transition-colors">
                                    <td class="py-4 px-6">
                                        <div class="flex items-center gap-3">
                                            <img src="<?= sanitize_string(get_profile_image($u['profile_image'])) ?>" class="w-10 h-10 rounded-full object-cover border border-gray-100 flex-shrink-0">
                                            <div class="min-w-0">
                                                <p class="font-semibold text-gray-900 truncate"><?= sanitize_string($u['name']) ?></p>
                                                <p class="text-[11px] text-gray-400 truncate"><?= sanitize_string($u['email']) ?></p>
                                            </div>
                                        </div>
                                    </td>
                                    <td class="py-4 px-6">
                                        <span class="inline-block px-2.5 py-1 rounded-lg text-[11px] font-semibold border <?= $roleColors[$u['role']] ?? '' ?>">
                                            <?= sanitize_string(ucfirst($u['role'])) ?>
                                        </span>
                                    </td>
                                    <td class="py-4 px-6">
                                        <div class="flex flex-col items-center gap-1.5">
                                            <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-lg text-[11px] font-bold border <?= $scoreBg ?> <?= $scoreText ?> <?= $scoreBorder ?>">
                                                <?= $score ?>
                                            </span>
                                            <div class="w-24 bg-gray-200 rounded-full h-1.5 overflow-hidden">
                                                <div class="<?= fraudBarColor($score) ?> h-1.5 rounded-full score-bar" style="width: <?= min($score, 100) ?>%"></div>
                                            </div>
                                        </div>
                                    </td>
                                    <td class="py-4 px-6">
                                        <span class="inline-block px-2.5 py-1 rounded-lg text-[11px] font-semibold border <?= $statusColors[$u['status']] ?? '' ?>">
                                            <?= sanitize_string(ucfirst($u['status'])) ?>
                                        </span>
                                    </td>
                                    <td class="py-4 px-6">
                                        <?php if ($u['last_login_action']): ?>
                                            <p class="text-xs font-medium text-gray-700"><?= sanitize_string($u['last_login_action']) ?></p>
                                            <p class="text-[11px] text-gray-400 mt-0.5"><?= time_ago($u['last_login_time']) ?></p>
                                        <?php else: ?>
                                            <span class="text-gray-400 text-xs">No login</span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="py-4 px-6">
                                        <?php if ($u['last_suspicious_action']): ?>
                                            <?php $aColor = $actionBadgeColors[$u['last_suspicious_action']] ?? 'bg-gray-50 text-gray-600 border-gray-200'; ?>
                                            <span class="inline-block px-2 py-0.5 rounded text-[10px] font-semibold border <?= $aColor ?>">
                                                <?= sanitize_string($u['last_suspicious_action']) ?>
                                            </span>
                                            <p class="text-[11px] text-gray-400 mt-1"><?= time_ago($u['last_suspicious_time']) ?></p>
                                        <?php else: ?>
                                            <span class="text-gray-400 text-xs">None recorded</span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="py-4 px-6">
                                        <span class="text-xs text-gray-500 font-mono"><?= sanitize_string($u['last_ip'] ?? 'N/A') ?></span>
                                    </td>
                                    <td class="py-4 px-6 text-right text-gray-400 text-xs"><?= time_ago($u['created_at']) ?></td>
                                    <td class="py-4 px-6">
                                        <div class="flex items-center justify-end gap-2">
                                            <a href="user_detail.php?id=<?= (int) $u['id'] ?>" class="w-8 h-8 rounded-lg bg-blue-50 text-blue-500 flex items-center justify-center hover:bg-blue-100 transition-colors" title="View User">
                                                <i class="fas fa-eye text-xs"></i>
                                            </a>
                                            <?php if ($u['status'] !== 'suspended'): ?>
                                            <form method="POST" action="fraud_action.php" class="inline" onsubmit="return confirm('Suspend this user? They will be locked out of the platform.')">
                                                <?= csrf_field() ?>
                                                <input type="hidden" name="action" value="suspend_user">
                                                <input type="hidden" name="user_id" value="<?= (int) $u['id'] ?>">
                                                <button type="submit" class="w-8 h-8 rounded-lg bg-red-50 text-red-500 flex items-center justify-center hover:bg-red-100 transition-colors" title="Suspend Account">
                                                    <i class="fas fa-ban text-xs"></i>
                                                </button>
                                            </form>
                                            <?php else: ?>
                                            <form method="POST" action="fraud_action.php" class="inline" onsubmit="return confirm('Reactivate this account?')">
                                                <?= csrf_field() ?>
                                                <input type="hidden" name="action" value="unflag_user">
                                                <input type="hidden" name="user_id" value="<?= (int) $u['id'] ?>">
                                                <button type="submit" class="w-8 h-8 rounded-lg bg-emerald-50 text-emerald-500 flex items-center justify-center hover:bg-emerald-100 transition-colors" title="Activate Account">
                                                    <i class="fas fa-check text-xs"></i>
                                                </button>
                                            </form>
                                            <?php endif; ?>
                                            <?php if ($score > 0): ?>
                                            <form method="POST" action="fraud_action.php" class="inline" onsubmit="return confirm('Reset fraud score to 0?')">
                                                <?= csrf_field() ?>
                                                <input type="hidden" name="action" value="reset_score">
                                                <input type="hidden" name="user_id" value="<?= (int) $u['id'] ?>">
                                                <button type="submit" class="w-8 h-8 rounded-lg bg-amber-50 text-amber-500 flex items-center justify-center hover:bg-amber-100 transition-colors" title="Reset Fraud Score">
                                                    <i class="fas fa-rotate-right text-xs"></i>
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
                            <i class="fas fa-shield-alt text-2xl text-gray-300"></i>
                        </div>
                        <p class="text-gray-500 text-sm mb-1">No users found</p>
                        <p class="text-gray-400 text-xs">Try adjusting your search or filters</p>
                    </div>
                    <?php endif; ?>
                </div>

                <!-- ═══ PAGINATION ═══════════════════════════════════════════ -->
                <?php render_pagination($pagination, $baseUrl); ?>

<?php require_once __DIR__ . '/../components/layout_end.php'; ?>
