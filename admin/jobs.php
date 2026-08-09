<?php

/**
 * Admin Jobs Management — Full-featured read-only listing with actions
 * Display: Job, Client, Budget, Skills, Category, Status, Proposals, Created Date
 * Actions: View Details, Close, Delete (spam), Feature
 */
require_once __DIR__ . '/../auth/auth.php';
require_role('admin');
require_once __DIR__ . '/../config/db.php';

$currentPage = 'jobs';

// ── Ensure feature column exists ─────────────────────────────────────
$colCheck = $conn->query("SHOW COLUMNS FROM jobs LIKE 'is_featured'");
if ($colCheck->num_rows === 0) {
    $conn->query('ALTER TABLE jobs ADD COLUMN is_featured TINYINT(1) NOT NULL DEFAULT 0 AFTER proposal_count');
}
$colCheck->close();

// ── Filters ─────────────────────────────────────────────────────────
$search = trim($_GET['search'] ?? '');
$statusF = $_GET['status'] ?? '';
$categoryF = trim($_GET['category'] ?? '');
$typeF = $_GET['type'] ?? '';
$levelF = $_GET['level'] ?? '';
$featuredF = isset($_GET['featured']) ? (int) $_GET['featured'] : -1;
$sort = $_GET['sort'] ?? 'created_at';
$dir = strtoupper($_GET['dir'] ?? 'DESC') === 'ASC' ? 'ASC' : 'DESC';
$page = max(1, sanitize_int($_GET['page'] ?? 1));
$perPage = 15;

$allowedStatuses = ['open', 'in_progress', 'completed', 'disputed', 'cancelled'];
$allowedTypes = ['hourly', 'fixed'];
$allowedLevels = ['entry', 'intermediate', 'expert'];
$allowedSorts = ['title', 'budget', 'proposal_count', 'category', 'created_at'];
if ($statusF && !in_array($statusF, $allowedStatuses))
    $statusF = '';
if ($typeF && !in_array($typeF, $allowedTypes))
    $typeF = '';
if ($levelF && !in_array($levelF, $allowedLevels))
    $levelF = '';
if (!in_array($sort, $allowedSorts))
    $sort = 'created_at';

// ── Distinct categories for filter ──────────────────────────────────
$catResult = $conn->query("SELECT DISTINCT category FROM jobs WHERE category IS NOT NULL AND category != '' ORDER BY category");
$categories = [];
while ($catRow = $catResult->fetch_assoc())
    $categories[] = $catRow['category'];
$catResult->close();

// ── Build query ─────────────────────────────────────────────────────
$where = [];
$params = [];
$types = '';

if ($search !== '') {
    $where[] = '(j.title LIKE ? OR u.name LIKE ?)';
    $searchTerm = "%{$search}%";
    $params[] = $searchTerm;
    $params[] = $searchTerm;
    $types .= 'ss';
}
if ($statusF) {
    $where[] = 'j.status = ?';
    $params[] = $statusF;
    $types .= 's';
}
if ($categoryF !== '') {
    $where[] = 'j.category = ?';
    $params[] = $categoryF;
    $types .= 's';
}
if ($typeF) {
    $where[] = 'j.job_type = ?';
    $params[] = $typeF;
    $types .= 's';
}
if ($levelF) {
    $where[] = 'j.experience_level = ?';
    $params[] = $levelF;
    $types .= 's';
}
if ($featuredF === 1) {
    $where[] = 'j.is_featured = 1';
} elseif ($featuredF === 0) {
    $where[] = 'j.is_featured = 0';
}

$whereSql = $where ? 'WHERE ' . implode(' AND ', $where) : '';

// ── Count ───────────────────────────────────────────────────────────
$countSql = "SELECT COUNT(*) AS cnt FROM jobs j JOIN clients c ON j.client_id = c.client_id JOIN users u ON c.client_id = u.id {$whereSql}";
$countStmt = $conn->prepare($countSql);
if ($params)
    $countStmt->bind_param($types, ...$params);
$countStmt->execute();
$totalItems = (int) $countStmt->get_result()->fetch_assoc()['cnt'];
$countStmt->close();

$pagination = paginate($totalItems, $perPage, $page);

// ── Fetch jobs ──────────────────────────────────────────────────────
$allowedSortCols = [
    'title' => 'j.title',
    'budget' => 'j.budget',
    'category' => 'j.category',
    'created_at' => 'j.created_at',
];
$sortCol = $allowedSortCols[$sort] ?? 'j.created_at';
// proposal_count sort uses subquery
if ($sort === 'proposal_count') {
    $sortCol = '(SELECT COUNT(*) FROM proposals WHERE job_id = j.id)';
}

$querySql = "SELECT j.id, j.title, j.budget, j.job_type, j.experience_level, j.category, j.status,
                    j.is_featured, j.proposal_count, j.created_at,
                    u.id AS user_id, u.name AS client_name, u.profile_image AS client_image,
                    c.company_name
             FROM jobs j
             JOIN clients c ON j.client_id = c.client_id
             JOIN users u ON c.client_id = u.id
             {$whereSql}
             ORDER BY {$sortCol} {$dir}
             LIMIT ? OFFSET ?";
$queryStmt = $conn->prepare($querySql);
$bindTypes = $types . 'ii';
$bindParams = array_merge($params, [$perPage, $pagination['offset']]);
$queryStmt->bind_param($bindTypes, ...$bindParams);
$queryStmt->execute();
$jobsResult = $queryStmt->get_result();
$queryStmt->close();

// ── Batch: skills for visible jobs ──────────────────────────────────
$jobIds = [];
$jobMap = [];
while ($row = $jobsResult->fetch_assoc()) {
    $jobIds[] = (int) $row['id'];
    $jobMap[(int) $row['id']] = $row;
}
$jobsResult->free();

$skillsMap = [];
if ($jobIds) {
    $idPlaceholders = implode(',', array_fill(0, count($jobIds), '?'));
    $ss = $conn->prepare("SELECT js.job_id, s.skill_name FROM job_skills js JOIN skills s ON js.skill_id = s.id WHERE js.job_id IN ({$idPlaceholders}) ORDER BY s.skill_name");
    $ss->bind_param(str_repeat('i', count($jobIds)), ...$jobIds);
    $ss->execute();
    $ssRes = $ss->get_result();
    while ($r = $ssRes->fetch_assoc()) {
        $skillsMap[(int) $r['job_id']][] = $r['skill_name'];
    }
    $ss->close();
}

// ── Batch: actual proposal counts (fresh) ───────────────────────────
$proposalCounts = [];
if ($jobIds) {
    $idPlaceholders = implode(',', array_fill(0, count($jobIds), '?'));
    $pc = $conn->prepare("SELECT job_id, COUNT(*) AS cnt FROM proposals WHERE job_id IN ({$idPlaceholders}) GROUP BY job_id");
    $pc->bind_param(str_repeat('i', count($jobIds)), ...$jobIds);
    $pc->execute();
    $pcRes = $pc->get_result();
    while ($r = $pcRes->fetch_assoc())
        $proposalCounts[(int) $r['job_id']] = (int) $r['cnt'];
    $pc->close();
}

// ── Badge maps ──────────────────────────────────────────────────────
$jobStatusColors = [
    'open' => 'bg-emerald-50 text-emerald-700 dark:bg-emerald-900/20 dark:text-emerald-400',
    'in_progress' => 'bg-blue-50 text-blue-700 dark:bg-blue-900/20 dark:text-blue-400',
    'completed' => 'bg-gray-100 text-gray-600 dark:bg-slate-700 dark:text-slate-400',
    'cancelled' => 'bg-red-50 text-red-700 dark:bg-red-900/20 dark:text-red-400',
    'disputed' => 'bg-orange-50 text-orange-700 dark:bg-orange-900/20 dark:text-orange-400',
];
$typeLabels = ['hourly' => 'Hourly', 'fixed' => 'Fixed'];
$typeColors = [
    'hourly' => 'bg-violet-100 text-violet-700 dark:bg-violet-900/30 dark:text-violet-400',
    'fixed' => 'bg-cyan-100 text-cyan-700 dark:bg-cyan-900/30 dark:text-cyan-400',
];
$levelLabels = ['entry' => 'Entry', 'intermediate' => 'Intermediate', 'expert' => 'Expert'];
$levelColors = [
    'entry' => 'bg-teal-100 text-teal-700 dark:bg-teal-900/30 dark:text-teal-400',
    'intermediate' => 'bg-blue-100 text-blue-700 dark:bg-blue-900/30 dark:text-blue-400',
    'expert' => 'bg-purple-100 text-purple-700 dark:bg-purple-900/30 dark:text-purple-400',
];

// ── Build base URL ──────────────────────────────────────────────────
$baseUrl = 'jobs.php?';
if ($search !== '')
    $baseUrl .= 'search=' . urlencode($search) . '&';
if ($statusF)
    $baseUrl .= 'status=' . urlencode($statusF) . '&';
if ($categoryF !== '')
    $baseUrl .= 'category=' . urlencode($categoryF) . '&';
if ($typeF)
    $baseUrl .= 'type=' . urlencode($typeF) . '&';
if ($levelF)
    $baseUrl .= 'level=' . urlencode($levelF) . '&';
if ($featuredF >= 0)
    $baseUrl .= 'featured=' . $featuredF . '&';
$baseUrl = rtrim($baseUrl, '?&');
if (strpos($baseUrl, '&') === false)
    $baseUrl = rtrim($baseUrl, '?');

function sortUrl(string $base, string $col, string $currentSort, string $currentDir): string
{
    $newDir = ($currentSort === $col && $currentDir === 'ASC') ? 'DESC' : 'ASC';
    $sep = strpos($base, '?') !== false ? '&' : '?';
    return $base . $sep . 'sort=' . $col . '&dir=' . $newDir;
}

// ── Admin nav ───────────────────────────────────────────────────────
$_userId = $_SESSION['user_id'];
$sStmt = $conn->prepare('SELECT name, profile_image FROM users WHERE id = ?');
$sStmt->bind_param('i', $_userId);
$sStmt->execute();
$_navUserRow = $sStmt->get_result()->fetch_assoc();
$sStmt->close();
$adminName = $_navUserRow['name'] ?? 'Admin';

// ── Summary stats ────────────────────────────────────────────────────
$disputedCount = (int) $conn->query("SELECT COUNT(*) AS cnt FROM jobs WHERE status = 'disputed'")->fetch_assoc()['cnt'];
$pendingModerationCount = (int) $conn->query("SELECT COUNT(*) AS cnt FROM jobs WHERE status = 'open' AND proposal_count = 0")->fetch_assoc()['cnt'];

$conn->close();

$navItems = [
    ['key' => 'dashboard', 'label' => 'Dashboard', 'url' => 'dashboard.php', 'icon' => 'layout-grid'],
    ['key' => 'users', 'label' => 'Users', 'url' => 'users.php', 'icon' => 'users'],
    ['key' => 'clients', 'label' => 'Clients', 'url' => 'clients.php', 'icon' => 'user-check'],
    ['key' => 'jobs', 'label' => 'Jobs', 'url' => 'jobs.php', 'icon' => 'briefcase'],
    ['key' => 'categories', 'label' => 'Categories', 'url' => 'categories.php', 'icon' => 'folder-open'],
    ['key' => 'skills', 'label' => 'Skills', 'url' => 'skills.php', 'icon' => 'settings'],
    ['key' => 'payments', 'label' => 'Payments', 'url' => 'payments.php', 'icon' => 'credit-card'],
    ['key' => 'wallets', 'label' => 'Wallets', 'url' => 'wallets.php', 'icon' => 'wallet'],
    ['key' => 'contracts', 'label' => 'Contracts', 'url' => 'contracts.php', 'icon' => 'file-text'],
    ['key' => 'milestones', 'label' => 'Milestones', 'url' => 'milestones.php', 'icon' => 'list-checks'],
    ['key' => 'reviews', 'label' => 'Reviews', 'url' => 'reviews.php', 'icon' => 'star'],
    ['key' => 'disputes', 'label' => 'Disputes', 'url' => 'disputes.php', 'icon' => 'scale'],
    ['key' => 'fraud', 'label' => 'Fraud', 'url' => 'fraud_detection.php', 'icon' => 'shield'],
    ['key' => 'notifications', 'label' => 'Notifications', 'url' => 'notifications.php', 'icon' => 'bell'],
    ['key' => 'ai_monitor', 'label' => 'AI Monitor', 'url' => 'ai_monitor.php', 'icon' => 'brain'],
    ['key' => 'analytics', 'label' => 'Analytics', 'url' => 'analytics.php', 'icon' => 'chart-pie'],
    ['key' => 'settings', 'label' => 'Settings', 'url' => 'settings.php', 'icon' => 'settings'],
];

// Generate initials from the admin's name
$profileName = '';
if (!empty($adminName)) {
    $nameParts = explode(' ', trim($adminName));
    foreach ($nameParts as $part) {
        if (!empty($part)) {
            $profileName .= strtoupper($part[0]);
        }
    }
    $profileName = substr($profileName, 0, 2);  // Limit to 2 letters (e.g., "John Doe" -> "JD")
}

$pageTitle = 'Manage Jobs';
$pageSubtitle = number_format($totalItems) . ' job' . ($totalItems !== 1 ? 's' : '') . ' found';
$activePage = 'jobs';
$user = ['name' => $adminName, 'profile_image' => $_navUserRow['profile_image'] ?? $profileName];
$unreadCount = 0;
$profileLink = 'profile.php';
require_once __DIR__ . '/../components/layout_start.php';
?>
    <style>
    .job-actions{transition:opacity .15s}
    .filter-panel{max-height:0;overflow:hidden;transition:max-height .3s ease}
    .filter-panel.open{max-height:500px}

    /* Pagination overrides — 32x32 buttons, rounded-md, green active */
    nav.flex.items-center.justify-center.gap-6 a,
    nav.flex.items-center.justify-center.gap-6 > div a {
        width: 2rem !important;
        height: 2rem !important;
        border-radius: 0.375rem !important;
        border: 1px solid #E4EBE4 !important;
        background: white !important;
        font-size: 0.75rem !important;
        font-weight: 500 !important;
        color: #6B7280 !important;
        transition: all 0.15s !important;
    }
    nav.flex.items-center.justify-center.gap-6 a:hover,
    nav.flex.items-center.justify-center.gap-6 > div a:hover {
        background: #F9FAFB !important;
        border-color: #D1D5DB !important;
    }
    nav.flex.items-center.justify-center.gap-6 a[class*="bg-gray-900"],
    nav.flex.items-center.justify-center.gap-6 > div a[class*="bg-gray-900"] {
        background: #108A00 !important;
        border-color: #108A00 !important;
        color: white !important;
        box-shadow: 0 1px 3px rgba(16,138,0,0.2) !important;
    }
    nav.flex.items-center.justify-center.gap-6 span.w-9,
    nav.flex.items-center.justify-center.gap-6 > div span.w-9 {
        width: 2rem !important;
        height: 2rem !important;
        border-radius: 0.375rem !important;
    }
    </style>

    <?php display_flash('success'); ?>
    <?php display_flash('error'); ?>
    <?php display_flash('warning'); ?>

    <!-- ═══ SUMMARY STATISTICS ═══════════════════════════════════════ -->
    <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mb-6 fade-in">
        <div class="bg-white border border-gray-200 rounded-lg p-4 shadow-sm hover:-translate-y-0.5 hover:shadow transition-all duration-200">
            <div class="flex items-start justify-between">
                <span class="text-xs font-medium text-gray-500 dark:text-slate-400 uppercase tracking-wider">Total Jobs</span>
                <i data-lucide="briefcase" class="w-5 h-5 text-gray-400 dark:text-slate-500" stroke-width="1.5"></i>
            </div>
            <p class="text-3xl font-semibold text-gray-900 dark:text-white mt-1"><?= number_format($totalItems) ?></p>
        </div>
        <div class="bg-white dark:bg-slate-800 border border-gray-200 dark:border-slate-700 rounded-lg p-5 shadow-sm flex flex-col transition-all duration-200 hover:shadow-sm hover:-translate-y-0.5">
            <div class="flex items-start justify-between">
                <span class="text-xs font-medium text-gray-500 dark:text-slate-400 uppercase tracking-wider">Disputed Jobs</span>
                <i data-lucide="shield-alert" class="w-5 h-5 text-gray-400 dark:text-slate-500" stroke-width="1.5"></i>
            </div>
            <p class="text-3xl font-semibold text-gray-900 dark:text-white mt-1"><?= number_format($disputedCount) ?></p>
        </div>
        <div class="bg-white dark:bg-slate-800 border border-gray-200 dark:border-slate-700 rounded-lg p-5 shadow-sm flex flex-col transition-all duration-200 hover:shadow-sm hover:-translate-y-0.5">
            <div class="flex items-start justify-between">
                <span class="text-xs font-medium text-gray-500 dark:text-slate-400 uppercase tracking-wider">Pending Moderation</span>
                <i data-lucide="dollar-sign" class="w-5 h-5 text-gray-400 dark:text-slate-500" stroke-width="1.5"></i>
            </div>
            <p class="text-3xl font-semibold text-orange-700 dark:text-orange-400 mt-1"><?= number_format($pendingModerationCount) ?></p>
        </div>
    </div>

    <!-- ═══ SEARCH & QUICK FILTERS ═══════════════════════════════════ -->
    <div class="bg-white dark:bg-slate-800 border border-gray-200 dark:border-slate-700 rounded-t-lg p-5 mb-0 fade-in">
        <form method="GET" action="jobs.php" id="filterForm">
            <input type="hidden" name="sort" value="<?= sanitize_string($sort) ?>">
            <input type="hidden" name="dir" value="<?= sanitize_string($dir) ?>">
            <div class="flex flex-col lg:flex-row gap-4 items-stretch lg:items-center">
                <!-- Search Input -->
                <div class="flex-1 relative">
                    <i data-lucide="search" class="absolute left-3 top-1/2 -translate-y-1/2 w-4 h-4 text-gray-400 dark:text-slate-500" stroke-width="1.5"></i>
                    <input type="text" name="search" value="<?= sanitize_string($search) ?>" placeholder="Search by job title or client name..."
                           class="w-full pl-10 pr-4 py-2.5 rounded-lg border border-gray-200 dark:border-slate-600 text-sm bg-gray-50 dark:bg-slate-700 text-gray-900 dark:text-white placeholder-gray-400 dark:placeholder-slate-500 focus:outline-none focus:ring-2 focus:ring-emerald-500/30 focus:border-emerald-500 transition-all">
                </div>
                <!-- Filter Controls -->
                <div class="flex flex-wrap items-center gap-2">
                    <select name="status" class="px-3 py-2.5 rounded-lg border border-gray-200 dark:border-slate-600 text-sm text-gray-600 dark:text-slate-300 focus:outline-none focus:ring-2 focus:ring-emerald-500/30 focus:border-emerald-500 bg-gray-50 dark:bg-slate-700">
                        <option value="">All Statuses</option>
                        <?php foreach ($allowedStatuses as $s): ?>
                        <option value="<?= $s ?>" <?= $statusF === $s ? 'selected' : '' ?>><?= ucfirst(str_replace('_', ' ', $s)) ?></option>
                        <?php endforeach; ?>
                    </select>
                    <select name="type" class="px-3 py-2.5 rounded-lg border border-gray-200 dark:border-slate-600 text-sm text-gray-600 dark:text-slate-300 focus:outline-none focus:ring-2 focus:ring-emerald-500/30 focus:border-emerald-500 bg-gray-50 dark:bg-slate-700">
                        <option value="">All Types</option>
                        <option value="fixed" <?= $typeF === 'fixed' ? 'selected' : '' ?>>Fixed</option>
                        <option value="hourly" <?= $typeF === 'hourly' ? 'selected' : '' ?>>Hourly</option>
                    </select>
                    <button type="button" onclick="toggleAdvanced()" class="px-3 py-2.5 rounded-lg border border-gray-200 dark:border-slate-600 text-sm font-medium text-gray-600 dark:text-slate-300 hover:bg-gray-50 dark:hover:bg-slate-700 transition-colors inline-flex items-center gap-1.5 bg-gray-50 dark:bg-slate-700">
                        <i data-lucide="sliders-horizontal" class="w-4 h-4" stroke-width="1.5"></i> Advanced
                        <i id="advChevron" data-lucide="chevron-down" class="w-3 h-3 transition-transform" stroke-width="2"></i>
                    </button>
                    <?php if ($search || $statusF || $categoryF || $typeF || $levelF || $featuredF >= 0): ?>
                        <a href="jobs.php" class="px-3 py-2.5 rounded-lg border border-gray-200 dark:border-slate-600 text-sm font-medium text-gray-500 dark:text-slate-400 hover:bg-gray-50 dark:hover:bg-slate-700 transition-colors inline-flex items-center gap-1.5">
                            <i data-lucide="x" class="w-4 h-4" stroke-width="1.5"></i> Clear
                        </a>
                    <?php endif; ?>
                    <?php if ($categoryF || $levelF || $featuredF >= 0): ?>
                    <span class="text-xs bg-blue-100 dark:bg-blue-900/30 text-blue-600 dark:text-blue-400 px-2 py-0.5 rounded-full font-semibold">Active</span>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Advanced Filters Panel -->
            <div id="advancedPanel" class="filter-panel <?= ($categoryF || $levelF || $featuredF >= 0) ? 'open' : '' ?>">
                <div class="grid grid-cols-1 sm:grid-cols-3 gap-4 mt-4 pt-4 border-t border-gray-200 dark:border-slate-700">
                    <div>
                        <label class="text-xs font-semibold text-gray-400 dark:text-slate-500 uppercase tracking-wider mb-1.5 block">Category</label>
                        <select name="category" class="w-full px-3 py-2.5 rounded-lg border border-gray-200 dark:border-slate-600 text-sm text-gray-600 dark:text-slate-300 focus:outline-none focus:ring-2 focus:ring-emerald-500/30 focus:border-emerald-500 bg-gray-50 dark:bg-slate-700">
                            <option value="">All Categories</option>
                            <?php foreach ($categories as $cat): ?>
                            <option value="<?= sanitize_string($cat) ?>" <?= $categoryF === $cat ? 'selected' : '' ?>><?= sanitize_string($cat) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div>
                        <label class="text-xs font-semibold text-gray-400 dark:text-slate-500 uppercase tracking-wider mb-1.5 block">Experience Level</label>
                        <select name="level" class="w-full px-3 py-2.5 rounded-lg border border-gray-200 dark:border-slate-600 text-sm text-gray-600 dark:text-slate-300 focus:outline-none focus:ring-2 focus:ring-emerald-500/30 focus:border-emerald-500 bg-gray-50 dark:bg-slate-700">
                            <option value="">All Levels</option>
                            <option value="entry" <?= $levelF === 'entry' ? 'selected' : '' ?>>Entry</option>
                            <option value="intermediate" <?= $levelF === 'intermediate' ? 'selected' : '' ?>>Intermediate</option>
                            <option value="expert" <?= $levelF === 'expert' ? 'selected' : '' ?>>Expert</option>
                        </select>
                    </div>
                    <div>
                        <label class="text-xs font-semibold text-gray-400 dark:text-slate-500 uppercase tracking-wider mb-1.5 block">Featured</label>
                        <select name="featured" class="w-full px-3 py-2.5 rounded-lg border border-gray-200 dark:border-slate-600 text-sm text-gray-600 dark:text-slate-300 focus:outline-none focus:ring-2 focus:ring-emerald-500/30 focus:border-emerald-500 bg-gray-50 dark:bg-slate-700">
                            <option value="-1">All Jobs</option>
                            <option value="1" <?= $featuredF === 1 ? 'selected' : '' ?>>Featured Only</option>
                            <option value="0" <?= $featuredF === 0 ? 'selected' : '' ?>>Non-Featured Only</option>
                        </select>
                    </div>
                </div>
            </div>
        </form>
    </div>

    <!-- ═══ JOBS TABLE ════════════════════════════════════════════════ -->
    <div class="bg-white dark:bg-slate-800 rounded-b-lg border border-gray-200 dark:border-slate-700 border-t-0 shadow-sm overflow-hidden fade-in" style="animation-delay:.1s">
        <?php if (count($jobMap) > 0): ?>
        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead>
                    <tr class="border-b border-gray-200 dark:border-slate-700 bg-gray-50 dark:bg-slate-700/30">
                        <th class="text-left py-3 px-5 text-xs font-medium text-gray-500 dark:text-slate-400 uppercase tracking-wider">No.</th>
                        <th class="text-left py-3 px-5 text-xs font-medium text-gray-500 dark:text-slate-400 uppercase tracking-wider cursor-pointer hover:text-gray-700 dark:hover:text-slate-200 transition-colors" onclick="window.location.href='<?= sortUrl($baseUrl, 'title', $sort, $dir) ?>'">Job</th>
                        <th class="text-left py-3 px-5 text-xs font-medium text-gray-500 dark:text-slate-400 uppercase tracking-wider">Client</th>
                        <th class="text-right py-3 px-5 text-xs font-medium text-gray-500 dark:text-slate-400 uppercase tracking-wider cursor-pointer hover:text-gray-700 dark:hover:text-slate-200 transition-colors" onclick="window.location.href='<?= sortUrl($baseUrl, 'budget', $sort, $dir) ?>'">Budget</th>
                        <th class="text-left py-3 px-5 text-xs font-medium text-gray-500 dark:text-slate-400 uppercase tracking-wider">Skills</th>
                        <!-- <th class="text-left py-3 px-5 text-xs font-medium text-gray-500 dark:text-slate-400 uppercase tracking-wider cursor-pointer hover:text-gray-700 dark:hover:text-slate-200 transition-colors" onclick="window.location.href='<?= sortUrl($baseUrl, 'category', $sort, $dir) ?>'">Category</th> -->
                        <th class="text-left py-3 px-5 text-xs font-medium text-gray-500 dark:text-slate-400 uppercase tracking-wider">Status</th>
                        <th class="text-center py-3 px-5 text-xs font-medium text-gray-500 dark:text-slate-400 uppercase tracking-wider cursor-pointer hover:text-gray-700 dark:hover:text-slate-200 transition-colors" onclick="window.location.href='<?= sortUrl($baseUrl, 'proposal_count', $sort, $dir) ?>'">Proposals</th>
                        <th class="text-right py-3 px-5 text-xs font-medium text-gray-500 dark:text-slate-400 uppercase tracking-wider cursor-pointer hover:text-gray-700 dark:hover:text-slate-200 transition-colors" onclick="window.location.href='<?= sortUrl($baseUrl, 'created_at', $sort, $dir) ?>'">Posted</th>
                        <th class="text-right py-3 px-5 text-xs font-medium text-gray-500 dark:text-slate-400 uppercase tracking-wider">Actions</th>
                    </tr>
                </thead>
                <tbody>
                <?php $index = 0; // Initialize a counter before the loop ?>
                <?php
                foreach ($jobMap as $jid => $job):
                    $skills = $skillsMap[$jid] ?? [];
                    $pCount = $proposalCounts[$jid] ?? (int) $job['proposal_count'];
                    $index++;
                    ?>
                    <tr class="job-row border-b border-gray-100 dark:border-slate-700/50 last:border-0 hover:bg-gray-50 dark:hover:bg-slate-700/30 transition-colors">
                        <td class="py-3.5 px-5 max-w-[280px]"><?= $index ?></td>
                        <td class="py-3.5 px-5 max-w-[280px]">
                            <div class="flex items-center gap-2">
                                <?php if ($job['is_featured']): ?>
                                <i data-lucide="star" class="w-3.5 h-3.5 text-yellow-400 flex-shrink-0" title="Featured" stroke-width="2"></i>
                                <?php endif; ?>
                                <div class="min-w-0">
                                    <p class="font-medium text-gray-900 dark:text-white truncate text-sm leading-snug"><?= decode_over_encoded($job['title']) ?></p>
                                    <div class="flex items-center gap-1.5 mt-1">
                                        <span class="inline-flex items-center px-1.5 py-0.5 rounded text-xs font-normal bg-gray-100 dark:bg-slate-700 text-gray-600 dark:text-slate-400"><?= $typeLabels[$job['job_type']] ?? $job['job_type'] ?></span>
                                        <span class="inline-flex items-center px-1.5 py-0.5 rounded text-xs font-normal bg-gray-100 dark:bg-slate-700 text-gray-600 dark:text-slate-400"><?= $levelLabels[$job['experience_level']] ?? $job['experience_level'] ?></span>
                                    </div>
                                </div>
                            </div>
                        </td>
                        <td class="py-3.5 px-5">
                            <a href="user_detail.php?id=<?= (int) $job['user_id'] ?>" class="flex items-center gap-2.5 no-underline text-inherit">
                                <?php
                                $ci = $job['client_image'] ?? '';
                                $ciBase = strtolower(basename($ci));
                                $hasValidImage = $ci !== '' && $ci !== null && $ciBase !== 'default.png' && $ciBase !== 'profile.png' && file_exists(__DIR__ . '/../assets/upload/profiles/' . basename($ci));
                                if ($hasValidImage):
                                ?>
                                    <img src="<?= sanitize_string(get_profile_image($ci)) ?>" class="w-8 h-8 rounded-full object-cover border border-gray-200 dark:border-slate-600 flex-shrink-0">
                                <?php else:
                                    $ciInit = '';
                                    foreach (explode(' ', trim($job['client_name'] ?? '')) as $w) {
                                        if ($w !== '') $ciInit .= strtoupper($w[0]);
                                    }
                                    $ciInit = substr($ciInit, 0, 2);
                                ?>
                                    <div class="w-8 h-8 rounded-full bg-blue-100 text-blue-600 flex items-center justify-center font-semibold text-xs flex-shrink-0"><?= $ciInit ?></div>
                                <?php endif; ?>
                                <div class="min-w-0">
                                    <p class="text-sm font-normal text-gray-900 dark:text-white truncate leading-snug"><?= sanitize_string($job['client_name']) ?></p>
                                    <?php if ($job['company_name']): ?>
                                    <p class="text-xs text-gray-500 dark:text-slate-400 truncate leading-snug"><?= sanitize_string($job['company_name']) ?></p>
                                    <?php endif; ?>
                                </div>
                            </a>
                        </td>
                        <td class="py-3.5 px-5 text-right">
                            <span class="text-sm font-medium text-gray-900 dark:text-white"><?= format_currency((float) $job['budget']) ?></span>
                        </td>
                        <td class="py-3.5 px-5 max-w-[200px] align-middle">
                            <div class="flex flex-wrap gap-1 items-center">
                                <?php if ($skills): ?>
                                    <?php foreach (array_slice($skills, 0, 2) as $sk): ?>
                                    <span class="inline-flex items-center px-2 py-0.5 rounded-md text-xs font-normal bg-emerald-50 text-emerald-700 dark:bg-emerald-900/20 dark:text-emerald-400 border border-emerald-100 dark:border-emerald-800/30 whitespace-nowrap"><?= sanitize_string($sk) ?></span>
                                    <?php endforeach; ?>
                                    <?php if (count($skills) > 2): ?>
                                    <span class="inline-flex items-center px-2 py-0.5 rounded-md text-xs font-normal bg-gray-100 dark:bg-slate-700 text-gray-500 dark:text-slate-400 whitespace-nowrap">+<?= count($skills) - 2 ?> more</span>
                                    <?php endif; ?>
                                <?php else: ?>
                                    <span class="text-xs text-gray-300 dark:text-slate-600">—</span>
                                <?php endif; ?>
                            </div>
                        </td>
                        <!-- <td class="py-3.5 px-5">
                            <?php if ($job['category']): ?>
                            <span class="text-xs font-normal text-gray-600 dark:text-slate-400"><?= sanitize_string($job['category']) ?></span>
                            <?php else: ?>
                            <span class="text-xs text-gray-300 dark:text-slate-600">—</span>
                            <?php endif; ?>
                        </td> -->
                        <td class="py-3.5 px-5">
                            <span class="inline-flex items-center px-2.5 py-1 rounded-full text-xs font-normal <?= $jobStatusColors[$job['status']] ?? '' ?>">
                                <?= sanitize_string(ucfirst(str_replace('_', ' ', $job['status']))) ?>
                            </span>
                        </td>
                        <td class="py-3.5 px-5 text-center">
                            <span class="inline-flex items-center justify-center min-w-[26px] h-6 px-2 rounded-md bg-gray-100 dark:bg-slate-700 text-gray-700 dark:text-slate-300 text-xs font-medium"><?= $pCount ?></span>
                        </td>
                        <td class="py-3.5 px-5 text-right text-gray-500 dark:text-slate-400 text-xs whitespace-nowrap"><?= time_ago($job['created_at']) ?></td>
                        <td class="py-3.5 px-5">
                            <div class="job-actions flex items-center justify-end gap-1">
                                <a href="job_details.php?id=<?= $jid ?>" class="w-8 h-8 rounded-lg bg-blue-50 dark:bg-blue-900/30 text-blue-500 dark:text-blue-400 flex items-center justify-center hover:bg-blue-100 dark:hover:bg-blue-900/50 transition-colors" title="View Details">
                                    <i data-lucide="eye" class="w-4 h-4" stroke-width="1.5"></i>
                                </a>
                                <?php if ($job['status'] !== 'cancelled'): ?>
                                <form method="POST" action="job_action.php" class="inline" onsubmit="return confirm('Close this job? It will be set to cancelled status.')">
                                    <?= csrf_field() ?>
                                    <input type="hidden" name="action" value="close">
                                    <input type="hidden" name="job_id" value="<?= $jid ?>">
                                    <button type="submit" class="w-8 h-8 rounded-lg bg-amber-50 dark:bg-amber-900/30 text-amber-500 dark:text-amber-400 flex items-center justify-center hover:bg-amber-100 dark:hover:bg-amber-900/50 transition-colors" title="Close Job">
                                        <i data-lucide="lock" class="w-4 h-4" stroke-width="1.5"></i>
                                    </button>
                                </form>
                                <?php endif; ?>
                                <form method="POST" action="job_action.php" class="inline" onsubmit="return confirm('Feature this job on the homepage?')">
                                    <?= csrf_field() ?>
                                    <input type="hidden" name="action" value="feature">
                                    <input type="hidden" name="job_id" value="<?= $jid ?>">
                                    <button type="submit" class="w-8 h-8 rounded-lg bg-yellow-50 dark:bg-yellow-900/20 text-yellow-600 dark:text-yellow-400 flex items-center justify-center hover:bg-yellow-100 dark:hover:bg-yellow-900/30 transition-colors" title="<?= $job['is_featured'] ? 'Unfeature' : 'Feature' ?>">
                                        <i data-lucide="star" class="w-4 h-4" stroke-width="1.5"></i>
                                    </button>
                                </form>
                                <form method="POST" action="job_action.php" class="inline" onsubmit="return confirm('PERMANENTLY DELETE this spam job? This cannot be undone.')">
                                    <?= csrf_field() ?>
                                    <input type="hidden" name="action" value="delete">
                                    <input type="hidden" name="job_id" value="<?= $jid ?>">
                                    <button type="submit" class="w-8 h-8 rounded-lg bg-red-50 dark:bg-red-900/30 text-red-500 dark:text-red-400 flex items-center justify-center hover:bg-red-100 dark:hover:bg-red-900/50 transition-colors" title="Delete Spam">
                                        <i data-lucide="trash-2" class="w-4 h-4" stroke-width="1.5"></i>
                                    </button>
                                </form>
                            </div>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php else: ?>
        <div class="text-center py-16">
            <div class="w-14 h-14 rounded-xl bg-gray-100 dark:bg-slate-700 flex items-center justify-center mx-auto mb-3">
                <i data-lucide="briefcase" class="w-6 h-6 text-gray-300 dark:text-slate-500" stroke-width="1.5"></i>
            </div>
            <p class="text-gray-500 dark:text-slate-400 text-sm mb-1">No jobs found</p>
            <p class="text-gray-400 dark:text-slate-500 text-xs">Try adjusting your search or filters</p>
        </div>
        <?php endif; ?>
    </div>

    <!-- ═══ PAGINATION ═════════════════════════════════════════════════ -->
    <?php render_pagination($pagination, $baseUrl); ?>

<script>
function toggleAdvanced() {
    const panel = document.getElementById('advancedPanel');
    const chevron = document.getElementById('advChevron');
    panel.classList.toggle('open');
    if (chevron) {
        chevron.style.transform = panel.classList.contains('open') ? 'rotate(180deg)' : '';
    }
}
</script>
<?php require_once __DIR__ . '/../components/layout_end.php'; ?>
