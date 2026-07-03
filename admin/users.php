<?php

/**
 * Admin Users Management
 * List, search, filter, paginate all users. Actions: View, Suspend/Activate, Delete.
 */
require_once __DIR__ . '/../auth/auth.php';
require_role('admin');
require_once __DIR__ . '/../config/db.php';

$currentPage = 'users';

// ── Filters ─────────────────────────────────────────────────────────
$search = trim($_GET['search'] ?? '');
$roleF = $_GET['role'] ?? '';
$statusF = $_GET['status'] ?? '';
$page = max(1, sanitize_int($_GET['page'] ?? 1));
$perPage = 15;

$allowedRoles = ['client', 'freelancer', 'admin'];
$allowedStatuses = ['active', 'flagged', 'suspended'];
if ($roleF && !in_array($roleF, $allowedRoles))
    $roleF = '';
if ($statusF && !in_array($statusF, $allowedStatuses))
    $statusF = '';

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

// ── Fetch users ─────────────────────────────────────────────────────
$querySql = "SELECT u.id, u.name, u.email, u.role, u.status, u.fraud_score, u.wallet_balance, u.profile_image, u.created_at
             FROM users u {$whereSql}
             ORDER BY u.created_at DESC
             LIMIT ? OFFSET ?";
$queryStmt = $conn->prepare($querySql);
$bindTypes = $types . 'ii';
$bindParams = array_merge($params, [$perPage, $pagination['offset']]);
$queryStmt->bind_param($bindTypes, ...$bindParams);
$queryStmt->execute();
$usersResult = $queryStmt->get_result();
$queryStmt->close();

// $conn->close();

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

function fraudColor(int $score): string
{
    if ($score <= 30)
        return 'text-emerald-500';
    if ($score <= 60)
        return 'text-yellow-500';
    return 'text-red-500';
}

// Build base URL for pagination
$baseUrl = 'users.php?';
if ($search !== '')
    $baseUrl .= 'search=' . urlencode($search) . '&';
if ($roleF)
    $baseUrl .= 'role=' . urlencode($roleF) . '&';
if ($statusF)
    $baseUrl .= 'status=' . urlencode($statusF) . '&';
$baseUrl = rtrim($baseUrl, '?&');
if (strpos($baseUrl, '&') === false)
    $baseUrl = rtrim($baseUrl, '?');

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
$pageTitle = 'Manage Users';
$pageSubtitle = $totalItems . ' user' . ($totalItems !== 1 ? 's' : '') . ' found';
$activePage = 'users';
$user = ['name' => $adminName, 'profile_image' => $_navUserRow['profile_image'] ?? null];
$unreadCount = 0;
$profileLink = 'profile.php';
require_once __DIR__ . '/../components/layout_start.php';
?>
    <style>
    .btn-grad{background:linear-gradient(135deg,#2563eb,#0ea5e9);transition:opacity .25s,transform .2s}
    .btn-grad:hover{opacity:.88;transform:translateY(-2px)}
    </style>



            <?php display_flash('success') ?>
            <?php display_flash('error') ?>

            <!-- ═══ SEARCH & FILTERS ════════════════════════════════════ -->
            <div class="bg-white rounded-2xl border border-gray-100 shadow-sm p-5 fade-in">
                <form method="GET" action="users.php" class="flex flex-col sm:flex-row gap-3">
                    <div class="flex-1 relative">
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
                    <button type="submit" class="btn-grad text-white px-6 py-2.5 rounded-xl text-sm font-semibold">
                        <i class="fas fa-filter mr-1.5"></i> Filter
                    </button>
                    <?php if ($search || $roleF || $statusF): ?>
                        <a href="users.php" class="px-4 py-2.5 rounded-xl border border-gray-200 text-sm font-medium text-gray-500 hover:bg-gray-50 transition-colors text-center">
                            <i class="fas fa-times mr-1"></i> Clear
                        </a>
                    <?php endif; ?>
                </form>
            </div>

            <!-- ═══ USERS TABLE ══════════════════════════════════════════ -->
            <div class="bg-white rounded-2xl border border-gray-100 shadow-sm overflow-hidden fade-in" style="animation-delay:.1s">
                <?php if ($usersResult->num_rows > 0): ?>
                <div class="overflow-x-auto">
                    <table class="w-full text-sm">
                        <thead>
                            <tr class="border-b border-gray-100 bg-gray-50/50">
                                <th class="text-left py-4 px-6 text-xs font-semibold text-gray-400 uppercase tracking-wider">User</th>
                                <th class="text-left py-4 px-6 text-xs font-semibold text-gray-400 uppercase tracking-wider">Role</th>
                                <th class="text-left py-4 px-6 text-xs font-semibold text-gray-400 uppercase tracking-wider">Status</th>
                                <th class="text-center py-4 px-6 text-xs font-semibold text-gray-400 uppercase tracking-wider">Fraud Score</th>
                                <th class="text-right py-4 px-6 text-xs font-semibold text-gray-400 uppercase tracking-wider">Wallet</th>
                                <th class="text-right py-4 px-6 text-xs font-semibold text-gray-400 uppercase tracking-wider">Joined</th>
                                <th class="text-right py-4 px-6 text-xs font-semibold text-gray-400 uppercase tracking-wider">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                        <?php while ($u = $usersResult->fetch_assoc()): ?>
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
                                    <span class="inline-block px-2.5 py-1 rounded-lg text-[11px] font-semibold border <?= $statusColors[$u['status']] ?? '' ?>">
                                        <?= sanitize_string(ucfirst($u['status'])) ?>
                                    </span>
                                </td>
                                <td class="py-4 px-6 text-center">
                                    <span class="font-bold <?= fraudColor((int) $u['fraud_score']) ?>"><?= (int) $u['fraud_score'] ?></span>
                                </td>
                                <td class="py-4 px-6 text-right font-semibold text-gray-700"><?= format_currency((float) $u['wallet_balance']) ?></td>
                                <td class="py-4 px-6 text-right text-gray-400 text-xs"><?= time_ago($u['created_at']) ?></td>
                                <td class="py-4 px-6">
                                    <div class="flex items-center justify-end gap-2">
                                        <a href="user_detail.php?id=<?= (int) $u['id'] ?>" class="w-8 h-8 rounded-lg bg-blue-50 text-blue-500 flex items-center justify-center hover:bg-blue-100 transition-colors" title="View">
                                            <i class="fas fa-eye text-xs"></i>
                                        </a>
                                        <?php if ($u['status'] !== 'suspended'): ?>
                                        <form method="POST" action="user_action.php" class="inline" onsubmit="return confirm('Suspend this user?')">
                                            <?= csrf_field() ?>
                                            <input type="hidden" name="action" value="suspend">
                                            <input type="hidden" name="user_id" value="<?= (int) $u['id'] ?>">
                                            <button type="submit" class="w-8 h-8 rounded-lg bg-yellow-50 text-yellow-500 flex items-center justify-center hover:bg-yellow-100 transition-colors" title="Suspend">
                                                <i class="fas fa-ban text-xs"></i>
                                            </button>
                                        </form>
                                        <?php else: ?>
                                        <form method="POST" action="user_action.php" class="inline">
                                            <?= csrf_field() ?>
                                            <input type="hidden" name="action" value="activate">
                                            <input type="hidden" name="user_id" value="<?= (int) $u['id'] ?>">
                                            <button type="submit" class="w-8 h-8 rounded-lg bg-emerald-50 text-emerald-500 flex items-center justify-center hover:bg-emerald-100 transition-colors" title="Activate">
                                                <i class="fas fa-check text-xs"></i>
                                            </button>
                                        </form>
                                        <?php endif; ?>
                                        <?php if ((int) $u['id'] !== (int) $_SESSION['user_id']): ?>
                                        <form method="POST" action="user_action.php" class="inline" onsubmit="return confirm('Are you sure you want to DELETE this user? This cannot be undone.')">
                                            <?= csrf_field() ?>
                                            <input type="hidden" name="action" value="delete">
                                            <input type="hidden" name="user_id" value="<?= (int) $u['id'] ?>">
                                            <button type="submit" class="w-8 h-8 rounded-lg bg-red-50 text-red-500 flex items-center justify-center hover:bg-red-100 transition-colors" title="Delete">
                                                <i class="fas fa-trash text-xs"></i>
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
                        <i class="fas fa-users text-2xl text-gray-300"></i>
                    </div>
                    <p class="text-gray-500 text-sm mb-1">No users found</p>
                    <p class="text-gray-400 text-xs">Try adjusting your search or filters</p>
                </div>
                <?php endif; ?>
            </div>

            <!-- ═══ PAGINATION ═══════════════════════════════════════════ -->
            <?php render_pagination($pagination, $baseUrl); ?>

<?php require_once __DIR__ . '/../components/layout_end.php'; ?>
