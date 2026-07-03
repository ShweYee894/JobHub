<?php

/**
 * Admin Jobs Management
 * List all jobs with client name, budget, status, proposals count, search/filter, pagination.
 */
require_once __DIR__ . '/../auth/auth.php';
require_role('admin');
require_once __DIR__ . '/../config/db.php';

$currentPage = 'jobs';

$search = trim($_GET['search'] ?? '');
$statusF = $_GET['status'] ?? '';
$page = max(1, sanitize_int($_GET['page'] ?? 1));
$perPage = 15;

$allowedStatuses = ['open', 'in_progress', 'completed', 'closed', 'cancelled', 'disputed'];
if ($statusF && !in_array($statusF, $allowedStatuses))
    $statusF = '';

$where = [];
$params = [];
$types = '';

if ($search !== '') {
    $where[] = 'j.title LIKE ?';
    $params[] = "%{$search}%";
    $types .= 's';
}
if ($statusF) {
    $where[] = 'j.status = ?';
    $params[] = $statusF;
    $types .= 's';
}

$whereSql = $where ? 'WHERE ' . implode(' AND ', $where) : '';

$countSql = "SELECT COUNT(*) AS cnt FROM jobs j {$whereSql}";
$countStmt = $conn->prepare($countSql);
if ($params) {
    $countStmt->bind_param($types, ...$params);
}
$countStmt->execute();
$totalItems = (int) $countStmt->get_result()->fetch_assoc()['cnt'];
$countStmt->close();

$pagination = paginate($totalItems, $perPage, $page);

$querySql = "SELECT j.id, j.title, j.budget, j.status, j.created_at,
                    u.name AS client_name,
                    (SELECT COUNT(*) FROM proposals WHERE job_id = j.id) AS proposal_count
             FROM jobs j
              JOIN clients c ON j.client_id = c.client_id
             JOIN users u ON c.client_id = u.id
             {$whereSql}
             ORDER BY j.created_at DESC
             LIMIT ? OFFSET ?";
$queryStmt = $conn->prepare($querySql);
$bindTypes = $types . 'ii';
$bindParams = array_merge($params, [$perPage, $pagination['offset']]);
$queryStmt->bind_param($bindTypes, ...$bindParams);
$queryStmt->execute();
$jobsResult = $queryStmt->get_result();
$queryStmt->close();

// $conn->close();

$jobStatusColors = [
    'open' => 'bg-emerald-50 text-emerald-600 border-emerald-200',
    'in_progress' => 'bg-amber-50 text-amber-600 border-amber-200',
    'completed' => 'bg-blue-50 text-blue-600 border-blue-200',
    'closed' => 'bg-gray-50 text-gray-600 border-gray-200',
    'cancelled' => 'bg-red-50 text-red-600 border-red-200',
    'disputed' => 'bg-red-50 text-red-600 border-red-200',
];

$baseUrl = 'jobs.php?';
if ($search !== '')
    $baseUrl .= 'search=' . urlencode($search) . '&';
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
$pageTitle = 'Manage Jobs';
$pageSubtitle = $totalItems . ' job' . ($totalItems !== 1 ? 's' : '') . ' found';
$activePage = 'jobs';
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

            <!-- Search & Filter -->
            <div class="bg-white rounded-2xl border border-gray-100 shadow-sm p-5 fade-in">
                <form method="GET" action="jobs.php" class="flex flex-col sm:flex-row gap-3">
                    <div class="flex-1 relative">
                        <i class="fas fa-search absolute left-3.5 top-1/2 -translate-y-1/2 text-gray-400 text-sm"></i>
                        <input type="text" name="search" value="<?= sanitize_string($search) ?>" placeholder="Search jobs by title..."
                               class="w-full pl-10 pr-4 py-2.5 rounded-xl border border-gray-200 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500/30 focus:border-blue-400 transition-all">
                    </div>
                    <select name="status" class="px-4 py-2.5 rounded-xl border border-gray-200 text-sm text-gray-600 focus:outline-none focus:ring-2 focus:ring-blue-500/30 focus:border-blue-400 bg-white">
                        <option value="">All Statuses</option>
                        <option value="open" <?= $statusF === 'open' ? 'selected' : '' ?>>Open</option>
                        <option value="in_progress" <?= $statusF === 'in_progress' ? 'selected' : '' ?>>In Progress</option>
                        <option value="completed" <?= $statusF === 'completed' ? 'selected' : '' ?>>Completed</option>
                        <option value="closed" <?= $statusF === 'closed' ? 'selected' : '' ?>>Closed</option>
                        <option value="cancelled" <?= $statusF === 'cancelled' ? 'selected' : '' ?>>Cancelled</option>
                        <option value="disputed" <?= $statusF === 'disputed' ? 'selected' : '' ?>>Disputed</option>
                    </select>
                    <button type="submit" class="btn-grad text-white px-6 py-2.5 rounded-xl text-sm font-semibold">
                        <i class="fas fa-filter mr-1.5"></i> Filter
                    </button>
                    <?php if ($search || $statusF): ?>
                        <a href="jobs.php" class="px-4 py-2.5 rounded-xl border border-gray-200 text-sm font-medium text-gray-500 hover:bg-gray-50 transition-colors text-center">
                            <i class="fas fa-times mr-1"></i> Clear
                        </a>
                    <?php endif; ?>
                </form>
            </div>

            <!-- Jobs Table -->
            <div class="bg-white rounded-2xl border border-gray-100 shadow-sm overflow-hidden fade-in" style="animation-delay:.1s">
                <?php if ($jobsResult->num_rows > 0): ?>
                <div class="overflow-x-auto">
                    <table class="w-full text-sm">
                        <thead>
                            <tr class="border-b border-gray-100 bg-gray-50/50">
                                <th class="text-left py-4 px-6 text-xs font-semibold text-gray-400 uppercase tracking-wider">Job</th>
                                <th class="text-left py-4 px-6 text-xs font-semibold text-gray-400 uppercase tracking-wider">Client</th>
                                <th class="text-center py-4 px-6 text-xs font-semibold text-gray-400 uppercase tracking-wider">Proposals</th>
                                <th class="text-left py-4 px-6 text-xs font-semibold text-gray-400 uppercase tracking-wider">Status</th>
                                <th class="text-right py-4 px-6 text-xs font-semibold text-gray-400 uppercase tracking-wider">Budget</th>
                                <th class="text-right py-4 px-6 text-xs font-semibold text-gray-400 uppercase tracking-wider">Posted</th>
                            </tr>
                        </thead>
                        <tbody>
                        <?php while ($job = $jobsResult->fetch_assoc()): ?>
                            <tr class="border-b border-gray-50 last:border-0 hover:bg-gray-50/50 transition-colors">
                                <td class="py-4 px-6">
                                    <span class="font-semibold text-gray-900 line-clamp-1"><?= sanitize_string($job['title']) ?></span>
                                </td>
                                <td class="py-4 px-6">
                                    <span class="text-gray-600 text-xs"><?= sanitize_string($job['client_name']) ?></span>
                                </td>
                                <td class="py-4 px-6 text-center">
                                    <span class="inline-flex items-center justify-center w-8 h-8 rounded-full bg-blue-50 text-blue-600 text-xs font-bold"><?= (int) $job['proposal_count'] ?></span>
                                </td>
                                <td class="py-4 px-6">
                                    <span class="inline-block px-2.5 py-1 rounded-lg text-[11px] font-semibold border <?= $jobStatusColors[$job['status']] ?? 'bg-gray-50 text-gray-600 border-gray-200' ?>">
                                        <?= sanitize_string(ucfirst(str_replace('_', ' ', $job['status']))) ?>
                                    </span>
                                </td>
                                <td class="py-4 px-6 text-right font-semibold text-gray-700"><?= format_currency((float) $job['budget']) ?></td>
                                <td class="py-4 px-6 text-right text-gray-400 text-xs"><?= time_ago($job['created_at']) ?></td>
                            </tr>
                        <?php endwhile; ?>
                        </tbody>
                    </table>
                </div>
                <?php else: ?>
                <div class="text-center py-16">
                    <div class="w-16 h-16 rounded-2xl bg-gray-100 flex items-center justify-center mx-auto mb-4">
                        <i class="fas fa-briefcase text-2xl text-gray-300"></i>
                    </div>
                    <p class="text-gray-500 text-sm mb-1">No jobs found</p>
                    <p class="text-gray-400 text-xs">Try adjusting your search or filters</p>
                </div>
                <?php endif; ?>
            </div>

            <?php render_pagination($pagination, $baseUrl); ?>
<?php require_once __DIR__ . '/../components/layout_end.php'; ?>
