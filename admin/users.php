<?php

/**
 * Admin Users Management
 * Advanced search, filtering, sorting, bulk actions. Actions: View, Suspend/Activate, Delete.
 */
require_once __DIR__ . '/../auth/auth.php';
require_role('admin');
require_once __DIR__ . '/../config/db.php';

$currentPage = 'users';

// ── Filters ─────────────────────────────────────────────────────────
$search = trim($_GET['search'] ?? '');
$roleF = $_GET['role'] ?? '';
$statusF = $_GET['status'] ?? '';
$sort = $_GET['sort'] ?? 'created_at';
$dir = strtoupper($_GET['dir'] ?? 'DESC') === 'ASC' ? 'ASC' : 'DESC';
$page = max(1, sanitize_int($_GET['page'] ?? 1));
$perPage = 15;

$allowedRoles = ['client', 'freelancer', 'admin'];
$allowedStatuses = ['active', 'flagged', 'suspended'];
$allowedSorts = ['name', 'email', 'role', 'status', 'fraud_score', 'wallet_balance', 'created_at'];
if ($roleF && !in_array($roleF, $allowedRoles))
    $roleF = '';
if ($statusF && !in_array($statusF, $allowedStatuses))
    $statusF = '';
if (!in_array($sort, $allowedSorts))
    $sort = 'created_at';

// ── Build query ─────────────────────────────────────────────────────
$where = [];
$params = [];
$types = '';

if ($search !== '') {
    $where[] = '(u.name LIKE ? OR u.email LIKE ? OR u.phone LIKE ?)';
    $searchTerm = "%{$search}%";
    $params[] = $searchTerm;
    $params[] = $searchTerm;
    $params[] = $searchTerm;
    $types .= 'sss';
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
$allowedSortCols = [
    'name' => 'u.name',
    'email' => 'u.email',
    'role' => 'u.role',
    'status' => 'u.status',
    'fraud_score' => 'u.fraud_score',
    'wallet_balance' => 'u.wallet_balance',
    'created_at' => 'u.created_at'
];
$sortCol = $allowedSortCols[$sort] ?? 'u.created_at';

$querySql = "SELECT u.id, u.name, u.email, u.role, u.status, u.fraud_score, u.wallet_balance, u.profile_image, u.created_at
             FROM users u {$whereSql}
             ORDER BY {$sortCol} {$dir}
             LIMIT ? OFFSET ?";
$queryStmt = $conn->prepare($querySql);
$bindTypes = $types . 'ii';
$bindParams = array_merge($params, [$perPage, $pagination['offset']]);
$queryStmt->bind_param($bindTypes, ...$bindParams);
$queryStmt->execute();
$usersResult = $queryStmt->get_result();
$queryStmt->close();

// ── Role/Status counts for stat cards ───────────────────────────────
$rc = $conn->query('SELECT role, COUNT(*) AS cnt FROM users GROUP BY role');
$roleCounts = ['client' => 0, 'freelancer' => 0, 'admin' => 0];
while ($row = $rc->fetch_assoc())
    $roleCounts[$row['role']] = (int) $row['cnt'];

$sc = $conn->query('SELECT status, COUNT(*) AS cnt FROM users GROUP BY status');
$statusCounts = ['active' => 0, 'flagged' => 0, 'suspended' => 0];
while ($row = $sc->fetch_assoc())
    $statusCounts[$row['status']] = (int) $row['cnt'];

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

function fraudColor(int $score): string
{
    if ($score <= 30)
        return 'text-emerald-500';
    if ($score <= 60)
        return 'text-amber-500';
    return 'text-red-500';
}

function fraudBg(int $score): string
{
    if ($score <= 30)
        return 'bg-emerald-50 dark:bg-emerald-900/20';
    if ($score <= 60)
        return 'bg-amber-50 dark:bg-amber-900/20';
    return 'bg-red-50 dark:bg-red-900/20';
}

// Build base URL for pagination/sorting
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

function sortUrl(string $base, string $col, string $currentSort, string $currentDir): string
{
    $newDir = ($currentSort === $col && $currentDir === 'ASC') ? 'DESC' : 'ASC';
    $sep = strpos($base, '?') !== false ? '&' : '?';
    return $base . $sep . 'sort=' . $col . '&dir=' . $newDir;
}

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
$pageTitle = 'Manage Users';
$pageSubtitle = number_format($totalItems) . ' user' . ($totalItems !== 1 ? 's' : '') . ' found';
$activePage = 'users';
$user = ['name' => $adminName, 'profile_image' => $_navUserRow['profile_image'] ?? null];
$unreadCount = 0;
$profileLink = 'profile.php';
require_once __DIR__ . '/../components/layout_start.php';
?>
<style>
    .kpi-mini {
        transition: transform .2s, box-shadow .2s
    }

    .user-actions {
        transition: opacity .15s
    }

    /* Typography - Inter font consistency */
    #filterForm input,
    #filterForm select,
    #filterForm button,
    table td,
    table th,
    .kpi-mini,
    nav a,
    nav span {
        font-family: 'Inter', sans-serif;
    }

    /* Pagination overrides */
    nav.flex.items-center.justify-center.gap-6 {
        justify-content: space-between !important;
        background: #fff;
        border: 1px solid #E4EBE4;
        border-top: none;
        border-radius: 0 0 8px 8px;
        padding: 12px 20px;
        margin-top: 0;
    }

    html.dark nav.flex.items-center.justify-center.gap-6 {
        background: #1e293b;
        border-color: #334155;
    }

    nav.flex.items-center.justify-center.gap-6 .flex.items-center.gap-2 {
        gap: 6px;
    }

    nav.flex.items-center.justify-center.gap-6 a.w-9.h-9,
    nav.flex.items-center.justify-center.gap-6 span.w-9.h-9 {
        width: 32px !important;
        height: 32px !important;
        border-radius: 6px !important;
        border: 1px solid #E4EBE4 !important;
        background: #fff !important;
        color: #6b7280 !important;
        font-size: 12px !important;
        font-weight: 500 !important;
    }

    html.dark nav.flex.items-center.justify-center.gap-6 a.w-9.h-9,
    html.dark nav.flex.items-center.justify-center.gap-6 span.w-9.h-9 {
        border-color: #475569 !important;
        background: #1e293b !important;
        color: #94a3b8 !important;
    }

    nav.flex.items-center.justify-center.gap-6 a.w-9.h-9:hover {
        background: #f9fafb !important;
        color: #374151 !important;
    }

    html.dark nav.flex.items-center.justify-center.gap-6 a.w-9.h-9:hover {
        background: #334155 !important;
        color: #e2e8f0 !important;
    }

    nav.flex.items-center.justify-center.gap-6 a.w-9.h-9.bg-gray-900,
    nav.flex.items-center.justify-center.gap-6 a.w-9.h-9[class*="bg-gray-900"] {
        background: #108A00 !important;
        border-color: #108A00 !important;
        color: #fff !important;
        box-shadow: none !important;
    }

    html.dark nav.flex.items-center.justify-center.gap-6 a.w-9.h-9.bg-gray-900,
    html.dark nav.flex.items-center.justify-center.gap-6 a.w-9.h-9[class*="bg-gray-900"] {
        background: #108A00 !important;
        border-color: #108A00 !important;
        color: #fff !important;
    }

    nav.flex.items-center.justify-center.gap-6 span.w-9.h-9.cursor-not-allowed {
        opacity: 0.4;
    }

    /* ── Fix: force action icons to exact size and kill the -0.125em pull ── */
    .align-actions svg[data-lucide] {
        width: 16px !important;
        height: 16px !important;
        vertical-align: middle !important;
    }
</style>

<?php display_flash('success'); ?>
<?php display_flash('error'); ?>

<!-- ═══ ROLE TABS ═══════════════════════════════════════════════════ -->
<div class="flex items-center space-x-6 border-b border-gray-200 dark:border-slate-700 mb-5">
    <a href="users.php" class="pb-3 text-sm font-medium transition-colors no-underline border-b-2 -mb-px <?= empty($_GET['role']) && empty($_GET['status']) ? 'border-emerald-500 text-emerald-600 dark:text-green-400 dark:border-green-400' : 'border-transparent text-gray-500 dark:text-slate-400 hover:text-gray-700 dark:hover:text-slate-300' ?>">
        Total
        <span class="ml-1.5 inline-flex items-center justify-center min-w-[20px] h-5 px-1.5 rounded-full text-xs font-medium bg-gray-100 dark:bg-slate-700 text-gray-500 dark:text-slate-400"><?= number_format($totalItems) ?></span>
    </a>
    <a href="users.php?role=client" class="pb-3 text-sm font-medium transition-colors no-underline border-b-2 -mb-px <?= $roleF === 'client' ? 'border-emerald-500 text-emerald-600 dark:text-green-400 dark:border-green-400' : 'border-transparent text-gray-500 dark:text-slate-400 hover:text-gray-700 dark:hover:text-slate-300' ?>">
        Clients
        <span class="ml-1.5 inline-flex items-center justify-center min-w-[20px] h-5 px-1.5 rounded-full text-xs font-medium bg-gray-100 dark:bg-slate-700 text-gray-500 dark:text-slate-400"><?= number_format($roleCounts['client']) ?></span>
    </a>
    <a href="users.php?role=freelancer" class="pb-3 text-sm font-medium transition-colors no-underline border-b-2 -mb-px <?= $roleF === 'freelancer' ? 'border-emerald-500 text-emerald-600 dark:text-green-400 dark:border-green-400' : 'border-transparent text-gray-500 dark:text-slate-400 hover:text-gray-700 dark:hover:text-slate-300' ?>">
        Freelancers
        <span class="ml-1.5 inline-flex items-center justify-center min-w-[20px] h-5 px-1.5 rounded-full text-xs font-medium bg-gray-100 dark:bg-slate-700 text-gray-500 dark:text-slate-400"><?= number_format($roleCounts['freelancer']) ?></span>
    </a>
</div>

<!-- ═══ STATUS CHIPS ════════════════════════════════════════════════ -->
<div class="flex items-center gap-3 mb-5">
    <a href="users.php<?= $roleF ? '?role=' . urlencode($roleF) : '' ?>" class="inline-flex items-center px-2.5 py-1 rounded-md text-xs font-medium border transition-colors no-underline <?= $statusF === '' ? 'bg-gray-900 dark:bg-slate-600 text-white border-gray-900 dark:border-slate-600' : 'bg-white dark:bg-slate-800 border-gray-200 dark:border-slate-600 text-gray-600 dark:text-slate-400 hover:bg-gray-50 dark:hover:bg-slate-700' ?>">
        All
    </a>
    <a href="users.php?status=active<?= $roleF ? '&role=' . urlencode($roleF) : '' ?>" class="inline-flex items-center px-2.5 py-1 rounded-md text-xs font-medium border transition-colors no-underline <?= $statusF === 'active' ? 'bg-emerald-50 dark:bg-emerald-900/20 text-emerald-700 dark:text-emerald-400 border-emerald-200 dark:border-emerald-800' : 'bg-white dark:bg-slate-800 border-gray-200 dark:border-slate-600 text-gray-600 dark:text-slate-400 hover:bg-gray-50 dark:hover:bg-slate-700' ?>">
        Active
        <span class="ml-1.5 text-xs opacity-60"><?= number_format($statusCounts['active']) ?></span>
    </a>
    <a href="users.php?status=flagged<?= $roleF ? '&role=' . urlencode($roleF) : '' ?>" class="inline-flex items-center px-2.5 py-1 rounded-md text-xs font-medium border transition-colors no-underline <?= $statusF === 'flagged' ? 'bg-amber-50 dark:bg-amber-900/20 text-amber-700 dark:text-amber-400 border-amber-200 dark:border-amber-800' : 'bg-white dark:bg-slate-800 border-gray-200 dark:border-slate-600 text-gray-600 dark:text-slate-400 hover:bg-gray-50 dark:hover:bg-slate-700' ?>">
        Flagged
        <span class="ml-1.5 text-xs opacity-60"><?= number_format($statusCounts['flagged']) ?></span>
    </a>
    <a href="users.php?status=suspended<?= $roleF ? '&role=' . urlencode($roleF) : '' ?>" class="inline-flex items-center px-2.5 py-1 rounded-md text-xs font-medium border transition-colors no-underline <?= $statusF === 'suspended' ? 'bg-red-50 dark:bg-red-900/20 text-red-700 dark:text-red-400 border-red-200 dark:border-red-800' : 'bg-white dark:bg-slate-800 border-gray-200 dark:border-slate-600 text-gray-600 dark:text-slate-400 hover:bg-gray-50 dark:hover:bg-slate-700' ?>">
        Suspended
        <span class="ml-1.5 text-xs opacity-60"><?= number_format($statusCounts['suspended']) ?></span>
    </a>
</div>

<!-- ═══ UNIFIED CARD: FILTER + TABLE ═══════════════════════════════ -->
<div class="bg-white dark:bg-slate-800 border border-gray-200 dark:border-slate-700 rounded-lg overflow-hidden fade-in" style="animation-delay:.18s">

    <!-- Search & Filter Toolbar -->
    <div class="p-5 border-b border-gray-200 dark:border-slate-700">
        <form method="GET" action="users.php" id="filterForm" class="flex items-center gap-4 w-full">
            <input type="hidden" name="sort" value="<?= sanitize_string($sort) ?>">
            <input type="hidden" name="dir" value="<?= sanitize_string($dir) ?>">

            <!-- Search -->
            <div class="flex-1 relative">
                <i data-lucide="search" class="absolute left-3 top-1/2 -translate-y-1/2 w-4 h-4 text-gray-400 dark:text-slate-500 pointer-events-none"></i>
                <input type="text" name="search" value="<?= sanitize_string($search) ?>" placeholder="Search by name, email, or phone..."
                    class="w-full pl-10 pr-4 py-2.5 bg-gray-50 dark:bg-slate-700/50 border border-gray-200 dark:border-slate-600 rounded-lg text-sm text-gray-900 dark:text-white placeholder-gray-400 dark:placeholder-slate-500 focus:outline-none focus:ring-2 focus:ring-emerald-500/30 focus:border-emerald-500 transition-all">
            </div>

            <!-- Role Dropdown -->
            <select name="role" class="px-3 py-2.5 bg-white dark:bg-slate-800 border border-gray-200 dark:border-slate-600 rounded-lg text-sm text-gray-600 dark:text-slate-300 focus:outline-none focus:ring-2 focus:ring-emerald-500/30 focus:border-emerald-500 transition-all">
                <option value="">All Roles</option>
                <option value="client" <?= $roleF === 'client' ? 'selected' : '' ?>>Client</option>
                <option value="freelancer" <?= $roleF === 'freelancer' ? 'selected' : '' ?>>Freelancer</option>
                <option value="admin" <?= $roleF === 'admin' ? 'selected' : '' ?>>Admin</option>
            </select>

            <!-- Status Dropdown -->
            <select name="status" class="px-3 py-2.5 bg-white dark:bg-slate-800 border border-gray-200 dark:border-slate-600 rounded-lg text-sm text-gray-600 dark:text-slate-300 focus:outline-none focus:ring-2 focus:ring-emerald-500/30 focus:border-emerald-500 transition-all">
                <option value="">All Statuses</option>
                <option value="active" <?= $statusF === 'active' ? 'selected' : '' ?>>Active</option>
                <option value="flagged" <?= $statusF === 'flagged' ? 'selected' : '' ?>>Flagged</option>
                <option value="suspended" <?= $statusF === 'suspended' ? 'selected' : '' ?>>Suspended</option>
            </select>

            <!-- Filter Button -->
            <button type="submit" class="px-5 py-2.5 bg-white dark:bg-slate-800 border border-gray-200 dark:border-slate-600 rounded-lg text-sm font-medium text-gray-700 dark:text-slate-300 hover:bg-gray-50 dark:hover:bg-slate-700 transition-colors inline-flex items-center gap-1.5">
                <i data-lucide="sliders-horizontal" class="w-4 h-4"></i> Filter
            </button>

            <!-- Clear Button -->
            <?php if ($search || $roleF || $statusF): ?>
                <a href="users.php" class="px-4 py-2.5 bg-white dark:bg-slate-800 border border-gray-200 dark:border-slate-600 rounded-lg text-sm font-medium text-gray-500 dark:text-slate-400 hover:bg-gray-50 dark:hover:bg-slate-700 transition-colors inline-flex items-center gap-1.5">
                    <i data-lucide="x" class="w-4 h-4"></i> Clear
                </a>
            <?php endif; ?>
        </form>
    </div>

    <!-- Users Table -->
    <?php if ($usersResult->num_rows > 0): ?>
        <div class="overflow-x-auto">
            <table class="w-full border-collapse text-sm">
                <thead>
                    <tr class="border-b border-gray-200 dark:border-slate-700">
                        <th class="text-left px-5 py-3.5 text-xs font-medium text-gray-400 dark:text-slate-500 uppercase tracking-wider">No.</th>
                        <th class="text-left px-5 py-3.5 text-xs font-medium text-gray-400 dark:text-slate-500 uppercase tracking-wider">User</th>
                        <th class="text-left px-5 py-3.5 text-xs font-medium text-gray-400 dark:text-slate-500 uppercase tracking-wider">Role</th>
                        <th class="text-left px-5 py-3.5 text-xs font-medium text-gray-400 dark:text-slate-500 uppercase tracking-wider">Status</th>
                        <th class="text-center px-5 py-3.5 text-xs font-medium text-gray-400 dark:text-slate-500 uppercase tracking-wider">Fraud</th>
                        <th class="text-right px-5 py-3.5 text-xs font-medium text-gray-400 dark:text-slate-500 uppercase tracking-wider">Wallet</th>
                        <th class="text-right px-5 py-3.5 text-xs font-medium text-gray-400 dark:text-slate-500 uppercase tracking-wider">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php
                    $no = 1;  // Initialize the counter
                    ?>
                    <?php while ($u = $usersResult->fetch_assoc()): ?>
                        <tr class="border-b border-gray-50 dark:border-slate-700/50 last:border-0 hover:bg-gray-50 dark:hover:bg-slate-700/30 transition-colors">

                            <td class="px-4 py-4 align-middle text-xs text-gray-500 dark:text-slate-400 font-medium">
                                <?= $no++; ?>
                            </td>
                            <td class="px-4 py-4 align-middle">
                                <a href="user_detail.php?id=<?= (int) $u['id'] ?>" class="flex items-center gap-3 no-underline text-inherit">
                                    <!-- <img src="<?= sanitize_string(get_profile_image($u['profile_image'])) ?>" class="w-10 h-10 rounded-full object-cover flex-shrink-0">
                                    <div class="min-w-0">
                                        <p class="font-medium text-gray-900 dark:text-white truncate text-xs m-0"><?= sanitize_string($u['name']) ?></p>
                                        <p class="text-xs text-gray-400 dark:text-slate-500 truncate m-0 mt-0.5"><?= sanitize_string($u['email']) ?></p>
                                    </div> -->
                                    <?php if (!empty($u['profile_image']) && $u['profile_image'] !== 'default.png'): ?>
                                        <!-- User has an uploaded profile image -->
                                        <img src="<?= sanitize_string(get_profile_image($u['profile_image'])) ?>" class="w-10 h-10 rounded-full object-cover flex-shrink-0">
                                    <?php else: ?>
                                        <!-- No image: Generate initials from the user's name -->
                                        <?php
                                        $nameParts = explode(' ', trim($u['name']));
                                        $initials = '';
                                        foreach ($nameParts as $part) {
                                            if (!empty($part)) {
                                                $initials .= strtoupper($part[0]);
                                            }
                                        }
                                        // Keep it to a maximum of 2 letters for the avatar
                                        $initials = substr($initials, 0, 2);
                                        ?>
                                        <!-- Avatar Placeholder displaying initials -->
                                        <div class="w-10 h-10 rounded-full flex-shrink-0 flex items-center justify-center bg-blue-100 dark:bg-blue-900/30 text-blue-600 dark:text-blue-400 font-semibold text-sm">
                                            <?= sanitize_string($initials) ?>
                                        </div>
                                    <?php endif; ?>
                                    <div class="min-w-0">
                                        <p class="font-medium text-gray-900 dark:text-white truncate text-xs m-0"><?= sanitize_string($u['name']) ?></p>
                                        <p class="text-xs text-gray-400 dark:text-slate-500 truncate m-0 mt-0.5"><?= sanitize_string($u['email']) ?></p>
                                    </div>
                                </a>
                            </td>
                            <td class="px-4 py-4 align-middle">
                                <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-gray-100 dark:bg-slate-700 text-gray-600 dark:text-slate-400">
                                    <?= sanitize_string(ucfirst($u['role'])) ?>
                                </span>
                            </td>
                            <td class="px-4 py-4 align-middle">
                                <?php if ($u['status'] === 'suspended'): ?>
                                    <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-red-50 dark:bg-red-900/20 text-red-600 dark:text-red-400">
                                        <?= sanitize_string(ucfirst($u['status'])) ?>
                                    </span>
                                <?php else: ?>
                                    <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-emerald-50 dark:bg-emerald-900/20 text-emerald-600 dark:text-emerald-400">
                                        <?= sanitize_string(ucfirst($u['status'])) ?>
                                    </span>
                                <?php endif; ?>
                            </td>
                            <td class="px-4 py-4 align-middle text-center">
                                <?php if ((int) $u['fraud_score'] > 0): ?>
                                    <span class="text-xs font-semibold text-red-600 dark:text-red-400"><?= (int) $u['fraud_score'] ?></span>
                                <?php else: ?>
                                    <span class="text-xs text-gray-400 dark:text-slate-500"><?= (int) $u['fraud_score'] ?></span>
                                <?php endif; ?>
                            </td>
                            <td class="px-4 py-4 align-middle text-right text-gray-700 dark:text-slate-300 text-xs"><?= format_currency((float) $u['wallet_balance']) ?></td>
                            <td class="px-4 py-4 align-middle align-actions">
                                <div class="flexitems-center justify-center gap-2">
                                    <a href="user_detail.php?id=<?= (int) $u['id'] ?>" class="inline-flex items-center justify-center w-10 h-10 rounded-lg bg-blue-50 dark:bg-blue-900/30 text-blue-500 dark:text-blue-400 p-0 hover:bg-blue-100 dark:hover:bg-blue-900/50 transition-colors" title="View Profile">
                                        <i data-lucide="eye" class="w-5 h-5"></i>
                                    </a>
                                    <?php if ($u['status'] !== 'suspended'): ?>
                                        <form method="POST" action="user_action.php" class="inline-flex items-center" onsubmit="return confirm('Suspend this user?')">
                                            <?= csrf_field() ?>
                                            <input type="hidden" name="action" value="suspend">
                                            <input type="hidden" name="user_id" value="<?= (int) $u['id'] ?>">
                                            <button type="submit" class="inline-flex items-center justify-center w-10 h-10 rounded-lg bg-amber-50 dark:bg-amber-900/30 text-amber-500 dark:text-amber-400 p-0 hover:bg-amber-100 dark:hover:bg-amber-900/50 transition-colors" title="Suspend">
                                                <i data-lucide="ban" class="w-5 h-5"></i>
                                            </button>
                                        </form>
                                    <?php else: ?>
                                        <form method="POST" action="user_action.php" class="inline-flex items-center">
                                            <?= csrf_field() ?>
                                            <input type="hidden" name="action" value="activate">
                                            <input type="hidden" name="user_id" value="<?= (int) $u['id'] ?>">
                                            <button type="submit" class="inline-flex items-center justify-center w-10 h-10 rounded-lg bg-emerald-50 dark:bg-emerald-900/30 text-emerald-500 dark:text-emerald-400 p-0 hover:bg-emerald-100 dark:hover:bg-emerald-900/50 transition-colors" title="Activate">
                                                <i data-lucide="check" class="w-5 h-5"></i>
                                            </button>
                                        </form>
                                    <?php endif; ?>
                                    <?php if ((int) $u['id'] !== (int) $_SESSION['user_id']): ?>
                                        <form method="POST" action="user_action.php" class="inline-flex items-center" onsubmit="return confirm('DELETE this user? This cannot be undone.')">
                                            <?= csrf_field() ?>
                                            <input type="hidden" name="action" value="delete">
                                            <input type="hidden" name="user_id" value="<?= (int) $u['id'] ?>">
                                            <button type="submit" class="inline-flex items-center justify-center w-10 h-10 rounded-lg bg-red-50 dark:bg-red-900/30 text-red-500 dark:text-red-400 p-0 hover:bg-red-100 dark:hover:bg-red-900/50 transition-colors" title="Delete">
                                                <i data-lucide="trash-2" class="w-5 h-5"></i>
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
        <div class="text-center py-20">
            <div class="w-16 h-16 rounded-2xl bg-gray-100 dark:bg-slate-700 flex items-center justify-center mx-auto mb-4">
                <i data-lucide="users" class="w-7 h-7 text-gray-300 dark:text-slate-500"></i>
            </div>
            <p class="text-gray-500 dark:text-slate-400 text-sm mb-1 m-0">No users found</p>
            <p class="text-gray-400 dark:text-slate-500 text-xs m-0">Try adjusting your search or filters</p>
        </div>
    <?php endif; ?>

</div>
<script>
    lucide.createIcons();
</script>

<!-- ═══ PAGINATION ═════════════════════════════════════════════════ -->
<?php render_pagination($pagination, $baseUrl); ?>

<!-- ═══ SLIDE-OUT DRAWER ═══════════════════════════════════════════ -->
<!-- Overlay -->
<div id="drawerOverlay" class="fixed inset-0 bg-black/30 backdrop-blur-sm z-40 hidden"></div>

<!-- Drawer -->
<div id="userDrawer" class="fixed right-0 top-0 h-full w-full max-w-md bg-white dark:bg-slate-800 shadow-2xl z-50 translate-x-full transition-transform duration-300 flex flex-col">

    <!-- Header -->
    <div class="flex items-center justify-between px-6 py-4 border-b border-gray-100 dark:border-slate-700 flex-shrink-0">
        <h3 class="text-sm font-semibold text-gray-900 dark:text-white m-0">User Details</h3>
        <button id="closeDrawer" class="w-8 h-8 rounded-lg flex items-center justify-center text-gray-400 dark:text-slate-500 hover:text-gray-600 dark:hover:text-slate-300 hover:bg-gray-100 dark:hover:bg-slate-700 transition-colors">
            <i data-lucide="x" class="w-4 h-4"></i>
        </button>
    </div>

    <!-- Scrollable Body -->
    <div class="flex-1 overflow-y-auto px-6 py-6">

        <!-- User Profile Header -->
        <div class="flex items-center gap-4 mb-6">
            <div class="w-14 h-14 rounded-full bg-gray-200 dark:bg-slate-600 flex-shrink-0 overflow-hidden">
                <img src="" alt="" class="w-full h-full object-cover">
            </div>
            <div class="min-w-0">
                <h4 class="text-sm font-semibold text-gray-900 dark:text-white m-0 truncate">User Name</h4>
                <p class="text-xs text-gray-400 dark:text-slate-500 m-0 mt-0.5 truncate">user@email.com</p>
            </div>
        </div>

        <!-- Info Rows -->
        <div class="space-y-4">
            <div class="flex items-center justify-between py-3 border-b border-gray-50 dark:border-slate-700/50">
                <span class="text-xs text-gray-400 dark:text-slate-500">Role</span>
                <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-gray-100 dark:bg-slate-700 text-gray-600 dark:text-slate-400">Client</span>
            </div>
            <div class="flex items-center justify-between py-3 border-b border-gray-50 dark:border-slate-700/50">
                <span class="text-xs text-gray-400 dark:text-slate-500">Status</span>
                <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-emerald-50 dark:bg-emerald-900/20 text-emerald-600 dark:text-emerald-400">Active</span>
            </div>
            <div class="flex items-center justify-between py-3 border-b border-gray-50 dark:border-slate-700/50">
                <span class="text-xs text-gray-400 dark:text-slate-500">Wallet Balance</span>
                <span class="text-xs font-medium text-gray-900 dark:text-white">$0.00</span>
            </div>
            <div class="flex items-center justify-between py-3 border-b border-gray-50 dark:border-slate-700/50">
                <span class="text-xs text-gray-400 dark:text-slate-500">Fraud Score</span>
                <span class="text-xs font-medium text-gray-400 dark:text-slate-500">0</span>
            </div>
            <div class="flex items-center justify-between py-3 border-b border-gray-50 dark:border-slate-700/50">
                <span class="text-xs text-gray-400 dark:text-slate-500">Phone</span>
                <span class="text-xs text-gray-700 dark:text-slate-300">--</span>
            </div>
            <div class="flex items-center justify-between py-3">
                <span class="text-xs text-gray-400 dark:text-slate-500">Joined</span>
                <span class="text-xs text-gray-700 dark:text-slate-300">--</span>
            </div>
        </div>

    </div>

    <!-- Footer -->
    <div class="px-6 py-4 border-t border-gray-100 dark:border-slate-700 flex-shrink-0 flex items-center gap-3">
        <button class="flex-1 px-4 py-2.5 rounded-lg bg-white dark:bg-slate-700 border border-gray-200 dark:border-slate-600 text-sm font-medium text-gray-700 dark:text-slate-300 hover:bg-gray-50 dark:hover:bg-slate-600 transition-colors inline-flex items-center justify-center gap-2">
            <i data-lucide="message-square" class="w-4 h-4"></i> Message User
        </button>
        <button class="flex-1 px-4 py-2.5 rounded-lg bg-amber-500 hover:bg-amber-600 text-white text-sm font-medium transition-colors inline-flex items-center justify-center gap-2">
            <i data-lucide="ban" class="w-4 h-4"></i> Suspend User
        </button>
    </div>

</div>
<script>
    lucide.createIcons();
</script>

<?php require_once __DIR__ . '/../components/layout_end.php'; ?>