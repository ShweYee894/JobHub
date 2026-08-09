<?php
/**
 * Admin Reviews Management
 * Read-only view of all reviews with search, filter, hide, and delete.
 */
require_once __DIR__ . '/../auth/auth.php';
require_role('admin');
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../shared/notifications.php';

$currentPage = 'reviews';

// ── Ensure is_hidden column exists ──────────────────────────────────
$chk = $conn->query("SHOW COLUMNS FROM reviews LIKE 'is_hidden'");
if ($chk->num_rows === 0) {
    $conn->query("ALTER TABLE reviews ADD COLUMN is_hidden TINYINT(1) DEFAULT 0 AFTER comment");
}
$chk->close();

// ── Filters ─────────────────────────────────────────────────────────
$search   = trim($_GET['search'] ?? '');
$ratingF = isset($_GET['rating']) ? (int) $_GET['rating'] : 0;
$hideF   = $_GET['hidden'] ?? '';
$page    = max(1, sanitize_int($_GET['page'] ?? 1));
$perPage = 15;

if ($ratingF < 1 || $ratingF > 5) $ratingF = 0;
if ($hideF !== '' && $hideF !== '0' && $hideF !== '1') $hideF = '';

// ── Handle Actions ──────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    if (!verify_csrf_token()) {
        set_flash('error', 'Invalid security token.');
        redirect('reviews.php');
    }

    $reviewId = sanitize_int($_POST['review_id'] ?? 0);
    $act = $_POST['action'];

    if ($reviewId > 0 && in_array($act, ['hide', 'unhide', 'delete'])) {
        if ($act === 'delete') {
            $stmt = $conn->prepare("DELETE FROM reviews WHERE id = ?");
            $stmt->bind_param('i', $reviewId);
            $stmt->execute();
            $stmt->close();
            set_flash('success', 'Review #' . $reviewId . ' deleted.');
        } elseif ($act === 'hide') {
            $stmt = $conn->prepare("UPDATE reviews SET is_hidden = 1 WHERE id = ?");
            $stmt->bind_param('i', $reviewId);
            $stmt->execute();
            $stmt->close();
            set_flash('success', 'Review #' . $reviewId . ' hidden.');
        } elseif ($act === 'unhide') {
            $stmt = $conn->prepare("UPDATE reviews SET is_hidden = 0 WHERE id = ?");
            $stmt->bind_param('i', $reviewId);
            $stmt->execute();
            $stmt->close();
            set_flash('success', 'Review #' . $reviewId . ' visible again.');
        }
    }
    redirect('reviews.php' . ($search ? '?search=' . urlencode($search) : ''));
}

// ── Statistics ──────────────────────────────────────────────────────
$stats = [];
$r = $conn->query('SELECT COUNT(*) AS cnt FROM reviews');
$stats['total'] = (int) $r->fetch_assoc()['cnt'];

$r = $conn->query('SELECT COUNT(*) AS cnt FROM reviews WHERE is_hidden = 1');
$stats['hidden'] = (int) $r->fetch_assoc()['cnt'];

$r = $conn->query('SELECT COUNT(*) AS cnt FROM reviews WHERE rating >= 4');
$stats['positive'] = (int) $r->fetch_assoc()['cnt'];

$r = $conn->query('SELECT COUNT(*) AS cnt FROM reviews WHERE rating <= 2');
$stats['negative'] = (int) $r->fetch_assoc()['cnt'];

$r = $conn->query('SELECT COALESCE(AVG(rating), 0) AS avg FROM reviews');
$stats['avg'] = (float) $r->fetch_assoc()['avg'];

// ── Build Query ─────────────────────────────────────────────────────
$where  = [];
$params = [];
$types  = '';

if ($search !== '') {
    $where[]  = '(rev_c.title LIKE ? OR rev_r.name LIKE ? OR rev_e.name LIKE ? OR r.comment LIKE ?)';
    $s = "%{$search}%";
    $params = array_merge($params, [$s, $s, $s, $s]);
    $types .= 'ssss';
}
if ($ratingF > 0) {
    $where[]  = 'r.rating = ?';
    $params[] = $ratingF;
    $types  .= 'i';
}
if ($hideF !== '') {
    $where[]  = 'r.is_hidden = ?';
    $params[] = (int) $hideF;
    $types  .= 'i';
}

$whereSql = $where ? 'WHERE ' . implode(' AND ', $where) : '';

$countSql = "SELECT COUNT(*) AS cnt
             FROM reviews r
             JOIN contracts rev_c ON r.contract_id = rev_c.id
             JOIN users rev_r ON r.reviewer_id = rev_r.id
             JOIN users rev_e ON r.reviewee_id = rev_e.id
             $whereSql";
$countStmt = $conn->prepare($countSql);
if ($types) $countStmt->bind_param($types, ...$params);
$countStmt->execute();
$totalItems = (int) $countStmt->get_result()->fetch_assoc()['cnt'];
$countStmt->close();

$pagination = paginate($totalItems, $perPage, $page);
$offset = $pagination['offset'];

$querySql = "SELECT r.id, r.rating, r.comment, r.created_at, r.is_hidden,
                    rev_c.id AS contract_id, rev_c.total_budget, rev_c.status AS contract_status,
                    j.title AS job_title,
                    rev_r.name AS reviewer_name, rev_r.email AS reviewer_email, rev_r.profile_image AS reviewer_image,
                    rev_e.name AS reviewee_name, rev_e.email AS reviewee_email, rev_e.profile_image AS reviewee_image
             FROM reviews r
             JOIN contracts rev_c ON r.contract_id = rev_c.id
             JOIN jobs j ON rev_c.job_id = j.id
             JOIN users rev_r ON r.reviewer_id = rev_r.id
             JOIN users rev_e ON r.reviewee_id = rev_e.id
             $whereSql
             ORDER BY r.created_at DESC
             LIMIT ? OFFSET ?";
$bindTypes  = $types . 'ii';
$bindParams = array_merge($params, [$perPage, $offset]);
$queryStmt  = $conn->prepare($querySql);
$queryStmt->bind_param($bindTypes, ...$bindParams);
$queryStmt->execute();
$result = $queryStmt->get_result();
$queryStmt->close();

$reviews = [];
while ($row = $result->fetch_assoc()) {
    $reviews[] = $row;
}

// ── Base URL ────────────────────────────────────────────────────────
$baseUrl = 'reviews.php?';
if ($search !== '') $baseUrl .= 'search=' . urlencode($search) . '&';
if ($ratingF > 0) $baseUrl .= 'rating=' . $ratingF . '&';
if ($hideF !== '') $baseUrl .= 'hidden=' . $hideF . '&';
$baseUrl = rtrim($baseUrl, '?&');
if (strpos($baseUrl, '&') === false) $baseUrl = rtrim($baseUrl, '?');

// ── Layout Setup ────────────────────────────────────────────────────
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
$pageTitle = 'Reviews Management';
$pageSubtitle = number_format($totalItems) . ' review' . ($totalItems !== 1 ? 's' : '');
$activePage = 'reviews';
$user = ['name' => $adminName, 'profile_image' => $_navUserRow['profile_image'] ?? null];
$unreadCount = 0;
$profileLink = 'profile.php';
require_once __DIR__ . '/../components/layout_start.php';
?>
    <style>
    .kpi-mini{transition:transform .2s,box-shadow .2s}
    .kpi-mini:hover{transform:translateY(-1px);box-shadow:0 4px 15px rgba(0,0,0,.05)}
    .comment-cell{display:-webkit-box;-webkit-line-clamp:2;-webkit-box-orient:vertical;overflow:hidden;line-height:1.5}
    .comment-cell:hover{-webkit-line-clamp:unset}

    /* Pagination overrides to match unified card */
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
    </style>

    <?php display_flash('success'); ?>
    <?php display_flash('error'); ?>

    <!-- ═══ KPI CARDS ════════════════════════════════════════════════════ -->
    <div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-5 gap-3">
        <div class="kpi-mini fade-in rounded-lg p-4 border border-[#E4EBE4] dark:border-slate-700 bg-white dark:bg-slate-800 shadow-sm" style="animation-delay:0s">
            <div class="flex items-center gap-2 mb-2">
                <div class="flex items-center justify-center w-8 h-8 rounded-lg bg-amber-100 dark:bg-amber-900/30">
                    <svg class="w-4 h-4 text-amber-500" fill="currentColor" viewBox="0 0 20 20"><path d="M9.049 2.927c.3-.921 1.603-.921 1.902 0l1.07 3.292a1 1 0 00.95.69h3.462c.969 0 1.371 1.24.588 1.81l-2.8 2.034a1 1 0 00-.364 1.118l1.07 3.292c.3.921-.755 1.688-1.54 1.118l-2.8-2.034a1 1 0 00-1.175 0l-2.8 2.034c-.784.57-1.838-.197-1.539-1.118l1.07-3.292a1 1 0 00-.364-1.118L2.98 8.72c-.783-.57-.38-1.81.588-1.81h3.461a1 1 0 00.951-.69l1.07-3.292z"/></svg>
                </div>
                <p class="text-[10px] font-semibold text-gray-400 dark:text-slate-500 uppercase tracking-wider">Avg Rating</p>
            </div>
            <p class="text-xl font-extrabold text-gray-900 dark:text-white"><?= number_format($stats['avg'], 1) ?></p>
        </div>
        <div class="kpi-mini fade-in rounded-lg p-4 border border-[#E4EBE4] dark:border-slate-700 bg-white dark:bg-slate-800 shadow-sm" style="animation-delay:.03s">
            <div class="flex items-center gap-2 mb-2">
                <div class="flex items-center justify-center w-8 h-8 rounded-lg bg-blue-100 dark:bg-blue-900/30">
                    <svg class="w-4 h-4 text-blue-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 12h.01M12 12h.01M16 12h.01M21 12c0 4.418-4.03 8-9 8a9.863 9.863 0 01-4.255-.949L3 20l1.395-3.72C3.512 15.042 3 13.574 3 12c0-4.418 4.03-8 9-8s9 3.582 9 8z"/></svg>
                </div>
                <p class="text-[10px] font-semibold text-gray-400 dark:text-slate-500 uppercase tracking-wider">Total</p>
            </div>
            <p class="text-xl font-extrabold text-blue-600 dark:text-blue-400"><?= number_format($stats['total']) ?></p>
        </div>
        <div class="kpi-mini fade-in rounded-lg p-4 border border-[#E4EBE4] dark:border-slate-700 bg-white dark:bg-slate-800 shadow-sm" style="animation-delay:.06s">
            <div class="flex items-center gap-2 mb-2">
                <div class="flex items-center justify-center w-8 h-8 rounded-lg bg-emerald-100 dark:bg-emerald-900/30">
                    <svg class="w-4 h-4 text-emerald-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M14 10h4.764a2 2 0 011.789 2.894l-3.5 7A2 2 0 0115.263 21h-4.017c-.163 0-.326-.02-.485-.06L7 20m7-10V5a2 2 0 00-2-2h-.095c-.5 0-.905.405-.905.905 0 .714-.211 1.412-.608 2.006L7 11v9m7-10h-2M7 20H5a2 2 0 01-2-2v-6a2 2 0 012-2h2.5"/></svg>
                </div>
                <p class="text-[10px] font-semibold text-gray-400 dark:text-slate-500 uppercase tracking-wider">Positive</p>
            </div>
            <p class="text-xl font-extrabold text-emerald-600 dark:text-emerald-400"><?= number_format($stats['positive']) ?></p>
        </div>
        <div class="kpi-mini fade-in rounded-lg p-4 border border-[#E4EBE4] dark:border-slate-700 bg-white dark:bg-slate-800 shadow-sm" style="animation-delay:.09s">
            <div class="flex items-center gap-2 mb-2">
                <div class="flex items-center justify-center w-8 h-8 rounded-lg bg-red-100 dark:bg-red-900/30">
                    <svg class="w-4 h-4 text-red-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 14H5.236a2 2 0 01-1.789-2.894l3.5-7A2 2 0 018.736 3h4.018a2 2 0 01.485.06l3.76.94m-7 10v5a2 2 0 002 2h.096c.5 0 .905-.405.905-.904 0-.715.211-1.413.608-2.008L17 13V4m-7 10h2m5-10h2a2 2 0 012 2v6a2 2 0 01-2 2h-2.5"/></svg>
                </div>
                <p class="text-[10px] font-semibold text-gray-400 dark:text-slate-500 uppercase tracking-wider">Negative</p>
            </div>
            <p class="text-xl font-extrabold text-red-600 dark:text-red-400"><?= number_format($stats['negative']) ?></p>
        </div>
        <div class="kpi-mini fade-in rounded-lg p-4 border border-[#E4EBE4] dark:border-slate-700 bg-white dark:bg-slate-800 shadow-sm" style="animation-delay:.12s">
            <div class="flex items-center gap-2 mb-2">
                <div class="flex items-center justify-center w-8 h-8 rounded-lg bg-gray-100 dark:bg-slate-700">
                    <svg class="w-4 h-4 text-gray-400 dark:text-slate-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13.875 18.825A10.05 10.05 0 0112 19c-4.478 0-8.268-2.943-9.543-7a9.97 9.97 0 011.563-3.029m5.858.908a3 3 0 114.243 4.243M9.878 9.878l4.242 4.242M9.88 9.88l-3.29-3.29m7.532 7.532l3.29 3.29M3 3l3.59 3.59m0 0A9.953 9.953 0 0112 5c4.478 0 8.268 2.943 9.543 7a10.025 10.025 0 01-4.132 5.411m0 0L21 21"/></svg>
                </div>
                <p class="text-[10px] font-semibold text-gray-400 dark:text-slate-500 uppercase tracking-wider">Hidden</p>
            </div>
            <p class="text-xl font-extrabold text-gray-600 dark:text-slate-400"><?= number_format($stats['hidden']) ?></p>
        </div>
    </div>

    <!-- ═══ UNIFIED CARD: FILTER + TABLE ═════════════════════════════════ -->
    <div class="bg-white dark:bg-slate-800 border border-[#E4EBE4] dark:border-slate-700 rounded-lg overflow-hidden fade-in" style="animation-delay:.15s">

        <!-- Filter Toolbar -->
        <div class="p-5 border-b border-gray-100 dark:border-slate-700">
            <form method="GET" action="reviews.php">
                <div class="flex flex-col lg:flex-row gap-3">
                    <div class="flex-1 relative">
                        <i data-lucide="search" class="absolute left-3.5 top-1/2 -translate-y-1/2 text-gray-400 dark:text-slate-500 text-sm"></i>
                        <input type="text" name="search" value="<?= sanitize_string($search) ?>" placeholder="Search by job, reviewer, reviewee, or comment..."
                               class="w-full pl-10 pr-4 py-2.5 rounded-lg border border-gray-200 dark:border-slate-600 text-sm bg-gray-50 dark:bg-slate-700 text-gray-900 dark:text-white placeholder-gray-400 dark:placeholder-slate-500 focus:outline-none focus:ring-2 focus:ring-blue-500/30 focus:border-blue-400 transition-all">
                    </div>
                    <div class="flex flex-wrap gap-2">
                        <select name="rating" class="px-4 py-2.5 rounded-lg border border-gray-200 dark:border-slate-600 text-sm text-gray-600 dark:text-slate-300 bg-gray-50 dark:bg-slate-700 focus:outline-none focus:ring-2 focus:ring-blue-500/30 focus:border-blue-400">
                            <option value="">All Ratings</option>
                            <?php for ($i = 5; $i >= 1; $i--): ?>
                                <option value="<?= $i ?>" <?= $ratingF === $i ? 'selected' : '' ?>><?= $i ?> Star<?= $i > 1 ? 's' : '' ?></option>
                            <?php endfor; ?>
                        </select>
                        <select name="hidden" class="px-4 py-2.5 rounded-lg border border-gray-200 dark:border-slate-600 text-sm text-gray-600 dark:text-slate-300 bg-gray-50 dark:bg-slate-700 focus:outline-none focus:ring-2 focus:ring-blue-500/30 focus:border-blue-400">
                            <option value="">All Visibility</option>
                            <option value="0" <?= $hideF === '0' ? 'selected' : '' ?>>Visible</option>
                            <option value="1" <?= $hideF === '1' ? 'selected' : '' ?>>Hidden</option>
                        </select>
                        <button type="submit" class="px-5 py-2.5 rounded-lg bg-blue-600 hover:bg-blue-700 text-white text-sm font-semibold transition-colors inline-flex items-center gap-1.5 shadow-lg shadow-blue-500/20">
                            <i data-lucide="filter" class="text-xs"></i> Filter
                        </button>
                        <?php if ($search || $ratingF || $hideF !== ''): ?>
                            <a href="reviews.php" class="px-4 py-2.5 rounded-lg border border-gray-200 dark:border-slate-600 text-sm font-medium text-gray-500 dark:text-slate-400 hover:bg-gray-50 dark:hover:bg-slate-700 transition-colors text-center">
                                <i data-lucide="x" class="mr-1"></i> Clear
                            </a>
                        <?php endif; ?>
                    </div>
                </div>
            </form>
        </div>

        <!-- Reviews Table -->
        <?php if (count($reviews) > 0): ?>
        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead>
                    <tr class="bg-gray-50 dark:bg-slate-700/50">
                        <th class="text-left px-5 py-3 text-[10px] font-semibold text-gray-500 dark:text-slate-400 uppercase tracking-wider">Reviewer</th>
                        <th class="text-left px-5 py-3 text-[10px] font-semibold text-gray-500 dark:text-slate-400 uppercase tracking-wider">Receiver</th>
                        <th class="text-left px-5 py-3 text-[10px] font-semibold text-gray-500 dark:text-slate-400 uppercase tracking-wider">Contract</th>
                        <th class="text-center px-5 py-3 text-[10px] font-semibold text-gray-500 dark:text-slate-400 uppercase tracking-wider">Rating</th>
                        <th class="text-left px-5 py-3 text-[10px] font-semibold text-gray-500 dark:text-slate-400 uppercase tracking-wider">Comment</th>
                        <th class="text-right px-5 py-3 text-[10px] font-semibold text-gray-500 dark:text-slate-400 uppercase tracking-wider">Date</th>
                        <th class="text-center px-5 py-3 text-[10px] font-semibold text-gray-500 dark:text-slate-400 uppercase tracking-wider">Status</th>
                        <th class="text-right px-5 py-3 text-[10px] font-semibold text-gray-500 dark:text-slate-400 uppercase tracking-wider">Actions</th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ($reviews as $rv): ?>
                    <tr class="border-b border-gray-50 dark:border-slate-700/50 last:border-0 hover:bg-gray-50/50 dark:hover:bg-slate-700/30 transition-colors <?= $rv['is_hidden'] ? 'opacity-60' : '' ?>">
                        <!-- Reviewer -->
                        <td class="px-5 py-4">
                            <div class="flex items-center gap-3">
                                <img src="<?= sanitize_string(get_profile_image($rv['reviewer_image'])) ?>" class="w-8 h-8 rounded-full object-cover border-2 border-gray-100 dark:border-slate-600 flex-shrink-0">
                                <div class="min-w-0">
                                    <p class="font-semibold text-gray-900 dark:text-white text-sm truncate"><?= sanitize_string($rv['reviewer_name']) ?></p>
                                    <p class="text-[11px] text-gray-400 dark:text-slate-500 truncate"><?= sanitize_string($rv['reviewer_email']) ?></p>
                                </div>
                            </div>
                        </td>
                        <!-- Receiver -->
                        <td class="px-5 py-4">
                            <div class="flex items-center gap-3">
                                <img src="<?= sanitize_string(get_profile_image($rv['reviewee_image'])) ?>" class="w-8 h-8 rounded-full object-cover border-2 border-gray-100 dark:border-slate-600 flex-shrink-0">
                                <div class="min-w-0">
                                    <p class="font-semibold text-gray-900 dark:text-white text-sm truncate"><?= sanitize_string($rv['reviewee_name']) ?></p>
                                    <p class="text-[11px] text-gray-400 dark:text-slate-500 truncate"><?= sanitize_string($rv['reviewee_email']) ?></p>
                                </div>
                            </div>
                        </td>
                        <!-- Contract -->
                        <td class="px-5 py-4">
                            <span class="inline-flex items-center gap-1 px-2.5 py-1 rounded-lg bg-gray-100 dark:bg-slate-700 text-xs font-medium text-gray-700 dark:text-slate-300 max-w-[180px] truncate" title="<?= sanitize_string($rv['job_title']) ?> (#<?= $rv['contract_id'] ?> &middot; <?= format_currency((float) $rv['total_budget']) ?>)">
                                #<?= $rv['contract_id'] ?> &middot; <?= sanitize_string(mb_strimwidth($rv['job_title'], 0, 25, '...')) ?>
                            </span>
                        </td>
                        <!-- Rating -->
                        <td class="px-5 py-4 text-center">
                            <div class="inline-flex items-center gap-0.5">
                                <?php for ($i = 1; $i <= 5; $i++): ?>
                                    <i data-lucide="star" class="text-[10px] <?= $i <= $rv['rating'] ? 'text-amber-400' : 'text-gray-200 dark:text-slate-600' ?>"></i>
                                <?php endfor; ?>
                                <span class="text-xs font-bold text-gray-700 dark:text-gray-300 ml-1"><?= $rv['rating'] ?></span>
                            </div>
                        </td>
                        <!-- Comment -->
                        <td class="px-5 py-4">
                            <p class="text-sm text-gray-600 dark:text-slate-400 comment-cell max-w-[220px]" title="<?= sanitize_string($rv['comment'] ?? '') ?>"><?= sanitize_string($rv['comment'] ?? '-') ?></p>
                        </td>
                        <!-- Date -->
                        <td class="px-5 py-4 text-right">
                            <span class="text-[11px] text-gray-400 dark:text-slate-500 font-medium"><?= date('M d, Y', strtotime($rv['created_at'])) ?></span>
                        </td>
                        <!-- Status -->
                        <td class="px-5 py-4 text-center">
                            <?php if ($rv['is_hidden']): ?>
                                <span class="inline-flex items-center gap-1 px-2.5 py-1 rounded-lg text-[10px] font-bold bg-gray-100 dark:bg-slate-700 text-gray-600 dark:text-slate-400">
                                    <i data-lucide="eye-off" class="text-[8px]"></i> Hidden
                                </span>
                            <?php else: ?>
                                <span class="inline-flex items-center gap-1 px-2.5 py-1 rounded-lg text-[10px] font-bold bg-emerald-50 dark:bg-emerald-900/30 text-emerald-700 dark:text-emerald-400">
                                    <i data-lucide="eye" class="text-[8px]"></i> Visible
                                </span>
                            <?php endif; ?>
                        </td>
                        <!-- Actions -->
                        <td class="px-5 py-4">
                            <div class="flex items-center justify-end gap-1.5">
                                <!-- View Details -->
                                <button type="button" onclick='openReviewDrawer(<?= json_encode([
                                    'id' => (int) $rv['id'],
                                    'rating' => (int) $rv['rating'],
                                    'comment' => $rv['comment'] ?? '',
                                    'created_at' => $rv['created_at'],
                                    'reviewer_name' => $rv['reviewer_name'],
                                    'reviewer_email' => $rv['reviewer_email'],
                                    'reviewer_image' => get_profile_image($rv['reviewer_image']),
                                    'reviewee_name' => $rv['reviewee_name'],
                                    'reviewee_email' => $rv['reviewee_email'],
                                    'reviewee_image' => get_profile_image($rv['reviewee_image']),
                                    'contract_id' => (int) $rv['contract_id'],
                                    'job_title' => $rv['job_title'],
                                    'total_budget' => (float) $rv['total_budget'],
                                    'is_hidden' => (int) $rv['is_hidden'],
                                ]) ?>)'
                                        class="w-8 h-8 rounded-lg bg-blue-50 dark:bg-blue-900/30 text-blue-500 dark:text-blue-400 flex items-center justify-center hover:bg-blue-100 dark:hover:bg-blue-900/50 transition-colors" title="View Details">
                                    <i data-lucide="eye" class="text-xs"></i>
                                </button>
                                <?php if ($rv['is_hidden']): ?>
                                    <form method="POST" action="reviews.php" class="inline" onsubmit="return confirm('Unhide this review?')">
                                        <?= csrf_field() ?>
                                        <input type="hidden" name="action" value="unhide">
                                        <input type="hidden" name="review_id" value="<?= $rv['id'] ?>">
                                        <button type="submit" class="w-8 h-8 rounded-lg bg-emerald-50 dark:bg-emerald-900/30 text-emerald-500 dark:text-emerald-400 flex items-center justify-center hover:bg-emerald-100 dark:hover:bg-emerald-900/50 transition-colors" title="Unhide">
                                            <i data-lucide="eye" class="text-xs"></i>
                                        </button>
                                    </form>
                                <?php else: ?>
                                    <form method="POST" action="reviews.php" class="inline" onsubmit="return confirm('Hide this review from public view?')">
                                        <?= csrf_field() ?>
                                        <input type="hidden" name="action" value="hide">
                                        <input type="hidden" name="review_id" value="<?= $rv['id'] ?>">
                                        <button type="submit" class="w-8 h-8 rounded-lg bg-amber-50 dark:bg-amber-900/30 text-amber-500 dark:text-amber-400 flex items-center justify-center hover:bg-amber-100 dark:hover:bg-amber-900/50 transition-colors" title="Hide">
                                            <i data-lucide="eye-off" class="text-xs"></i>
                                        </button>
                                    </form>
                                <?php endif; ?>
                                <form method="POST" action="reviews.php" class="inline" onsubmit="return confirm('PERMANENTLY DELETE this review? This cannot be undone.')">
                                    <?= csrf_field() ?>
                                    <input type="hidden" name="action" value="delete">
                                    <input type="hidden" name="review_id" value="<?= $rv['id'] ?>">
                                    <button type="submit" class="w-8 h-8 rounded-lg bg-red-50 dark:bg-red-900/30 text-red-500 dark:text-red-400 flex items-center justify-center hover:bg-red-100 dark:hover:bg-red-900/50 transition-colors" title="Delete">
                                        <i data-lucide="trash-2" class="text-xs"></i>
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
            <div class="w-16 h-16 rounded-2xl bg-gray-100 dark:bg-slate-700 flex items-center justify-center mx-auto mb-4">
                <i data-lucide="star" class="text-2xl text-gray-300 dark:text-slate-500"></i>
            </div>
            <p class="text-gray-500 dark:text-slate-400 text-sm mb-1">No reviews found</p>
            <p class="text-gray-400 dark:text-slate-500 text-xs">Try adjusting your search or filters</p>
        </div>
        <?php endif; ?>
    </div>

    <!-- ═══ PAGINATION ═══════════════════════════════════════════════════ -->
    <?php render_pagination($pagination, $baseUrl); ?>

    <!-- ═══ REVIEW DETAIL DRAWER ═══════════════════════════════════════ -->
    <div id="rvOverlay" class="pd-overlay" onclick="closeReviewDrawer(event)">
        <div class="pd-drawer border-l border-[#E4EBE4] dark:border-slate-700" onclick="event.stopPropagation()">

            <!-- Header -->
            <div class="pd-drawer-header">
                <div class="flex items-start justify-between mb-1">
                    <div>
                        <h2 class="text-lg font-bold text-gray-900 dark:text-white" id="rvTitle">Review #--</h2>
                        <p class="text-xs text-gray-400 dark:text-slate-500 mt-0.5" id="rvTimestamp">--</p>
                    </div>
                    <div class="flex items-center gap-2">
                        <span id="rvStatusBadge" class="inline-block px-2.5 py-0.5 rounded text-[11px] font-semibold"></span>
                        <button onclick="closeReviewDrawer()" class="w-8 h-8 rounded-lg bg-gray-100 dark:bg-slate-700 flex items-center justify-center text-gray-400 dark:text-slate-400 hover:text-gray-600 dark:hover:text-slate-200 hover:bg-gray-200 dark:hover:bg-slate-600 transition-colors" title="Close">
                            <i data-lucide="x" class="text-sm"></i>
                        </button>
                    </div>
                </div>
            </div>

            <!-- Body -->
            <div class="pd-drawer-body">

                <!-- Star Rating -->
                <div class="mb-6">
                    <h3 class="text-[10px] font-semibold text-gray-400 dark:text-slate-500 uppercase tracking-wider mb-3">Rating</h3>
                    <div class="flex items-center gap-3">
                        <div class="inline-flex items-center gap-0.5" id="rvStars"></div>
                        <span class="text-lg font-bold text-gray-900 dark:text-white" id="rvRatingNum">--</span>
                    </div>
                </div>

                <!-- Comment -->
                <div class="mb-6">
                    <h3 class="text-[10px] font-semibold text-gray-400 dark:text-slate-500 uppercase tracking-wider mb-3">Comment</h3>
                    <div class="bg-gray-50 dark:bg-slate-700/50 rounded-lg p-4">
                        <p class="text-sm text-gray-700 dark:text-slate-300 leading-relaxed" id="rvComment">--</p>
                    </div>
                </div>

                <!-- Party Cards -->
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-3 mb-6">
                    <!-- Reviewer -->
                    <div class="border border-[#E4EBE4] dark:border-slate-700 rounded-lg p-4">
                        <p class="text-[10px] font-semibold text-blue-500 dark:text-blue-400 uppercase tracking-wider mb-3">Reviewer</p>
                        <div class="flex items-center gap-3">
                            <img id="rvReviewerAvatar" src="" class="w-10 h-10 rounded-full object-cover border-2 border-gray-100 dark:border-slate-600 flex-shrink-0">
                            <div class="min-w-0">
                                <p class="text-sm font-semibold text-gray-900 dark:text-white truncate" id="rvReviewerName">--</p>
                                <p class="text-[11px] text-gray-400 dark:text-slate-500 truncate" id="rvReviewerEmail">--</p>
                            </div>
                        </div>
                    </div>
                    <!-- Reviewee -->
                    <div class="border border-[#E4EBE4] dark:border-slate-700 rounded-lg p-4">
                        <p class="text-[10px] font-semibold text-emerald-500 dark:text-emerald-400 uppercase tracking-wider mb-3">Receiver</p>
                        <div class="flex items-center gap-3">
                            <img id="rvRevieweeAvatar" src="" class="w-10 h-10 rounded-full object-cover border-2 border-gray-100 dark:border-slate-600 flex-shrink-0">
                            <div class="min-w-0">
                                <p class="text-sm font-semibold text-gray-900 dark:text-white truncate" id="rvRevieweeName">--</p>
                                <p class="text-[11px] text-gray-400 dark:text-slate-500 truncate" id="rvRevieweeEmail">--</p>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Contract Info -->
                <div class="mb-6">
                    <h3 class="text-[10px] font-semibold text-gray-400 dark:text-slate-500 uppercase tracking-wider mb-3">Contract</h3>
                    <div class="bg-gray-50 dark:bg-slate-700/50 rounded-lg p-4 flex items-center justify-between">
                        <div>
                            <p class="text-sm font-semibold text-gray-900 dark:text-white" id="rvJobTitle">--</p>
                            <p class="text-[11px] text-gray-400 dark:text-slate-500" id="rvContractInfo">--</p>
                        </div>
                        <span class="text-lg font-bold text-gray-900 dark:text-white" id="rvBudget">--</span>
                    </div>
                </div>

            </div>

            <!-- Footer Actions -->
            <div class="pd-drawer-footer">
                <div class="flex items-center gap-3">
                    <form method="POST" action="reviews.php" class="flex-1" id="rvUnhideForm" onsubmit="return confirm('Unhide this review?')">
                        <?= csrf_field() ?>
                        <input type="hidden" name="action" value="unhide">
                        <input type="hidden" name="review_id" id="rvFormIdUnhide" value="">
                        <button type="submit" class="w-full inline-flex items-center justify-center gap-2 px-4 py-2.5 rounded-lg border border-emerald-200 dark:border-emerald-800 text-sm font-medium text-emerald-600 dark:text-emerald-400 hover:bg-emerald-50 dark:hover:bg-emerald-900/20 transition-colors">
                            <i data-lucide="eye"></i> Unhide Review
                        </button>
                    </form>
                    <form method="POST" action="reviews.php" class="flex-1" id="rvHideForm" onsubmit="return confirm('Hide this review from public view?')">
                        <?= csrf_field() ?>
                        <input type="hidden" name="action" value="hide">
                        <input type="hidden" name="review_id" id="rvFormIdHide" value="">
                        <button type="submit" class="w-full inline-flex items-center justify-center gap-2 px-4 py-2.5 rounded-lg border border-amber-200 dark:border-amber-800 text-sm font-medium text-amber-600 dark:text-amber-400 hover:bg-amber-50 dark:hover:bg-amber-900/20 transition-colors">
                            <i data-lucide="eye-off"></i> Hide Review
                        </button>
                    </form>
                    <form method="POST" action="reviews.php" class="flex-1" onsubmit="return confirm('PERMANENTLY DELETE this review? This cannot be undone.')">
                        <?= csrf_field() ?>
                        <input type="hidden" name="action" value="delete">
                        <input type="hidden" name="review_id" id="rvFormIdDel" value="">
                        <button type="submit" class="w-full inline-flex items-center justify-center gap-2 px-4 py-2.5 rounded-lg border border-red-200 dark:border-red-800 text-sm font-medium text-red-600 dark:text-red-400 hover:bg-red-50 dark:hover:bg-red-900/20 transition-colors">
                            <i data-lucide="trash-2"></i> Delete Review
                        </button>
                    </form>
                </div>
            </div>

        </div>
    </div>

    <style>
    /* Drawer styles (shared with payments.php) */
    .pd-overlay{position:fixed;inset:0;background:rgba(0,0,0,.4);backdrop-filter:blur(4px);z-index:40;opacity:0;visibility:hidden;transition:all .25s}
    .pd-overlay.open{opacity:1;visibility:visible}
    .pd-drawer{position:fixed;top:0;right:0;bottom:0;width:100%;max-width:450px;background:#fff;z-index:50;transform:translateX(100%);transition:transform .3s cubic-bezier(.4,0,.2,1);display:flex;flex-direction:column;overflow:hidden}
    html.dark .pd-drawer{background:#0f172a}
    .pd-overlay.open .pd-drawer{transform:translateX(0)}
    .pd-drawer-header{padding:24px 24px 0;flex-shrink:0}
    .pd-drawer-body{flex:1;overflow-y:auto;padding:20px 24px}
    .pd-drawer-footer{flex-shrink:0;border-top:1px solid #E4EBE4;padding:16px 24px;background:#fff}
    html.dark .pd-drawer-footer{border-color:#334155;background:#0f172a}
    </style>

    <script>
        let rvCurrent = null;

        function openReviewDrawer(data) {
            rvCurrent = data;

            document.getElementById('rvTitle').textContent = 'Review #' + data.id;
            document.getElementById('rvTimestamp').textContent = new Date(data.created_at).toLocaleDateString('en-US', {
                year: 'numeric', month: 'long', day: 'numeric', hour: '2-digit', minute: '2-digit'
            });
            document.getElementById('rvRatingNum').textContent = data.rating + '.0';
            document.getElementById('rvComment').textContent = data.comment || 'No comment provided.';
            document.getElementById('rvJobTitle').textContent = data.job_title;
            document.getElementById('rvContractInfo').textContent = 'Contract #' + data.contract_id;
            document.getElementById('rvBudget').textContent = '$' + data.total_budget.toFixed(2);

            // Stars
            let starsHtml = '';
            for (let i = 1; i <= 5; i++) {
                starsHtml += '<i data-lucide="star" class="text-sm ' + (i <= data.rating ? 'text-amber-400' : 'text-gray-200 dark:text-slate-600') + '"></i>';
            }
            document.getElementById('rvStars').innerHTML = starsHtml;

            // Status badge
            const badge = document.getElementById('rvStatusBadge');
            if (data.is_hidden) {
                badge.textContent = 'Hidden';
                badge.className = 'inline-block px-2.5 py-0.5 rounded text-[11px] font-semibold bg-gray-100 dark:bg-slate-700 text-gray-600 dark:text-slate-400';
            } else {
                badge.textContent = 'Visible';
                badge.className = 'inline-block px-2.5 py-0.5 rounded text-[11px] font-semibold bg-emerald-50 dark:bg-emerald-900/30 text-emerald-700 dark:text-emerald-400';
            }

            // Avatars & names
            document.getElementById('rvReviewerAvatar').src = data.reviewer_image || '<?= get_profile_image(null) ?>';
            document.getElementById('rvReviewerName').textContent = data.reviewer_name;
            document.getElementById('rvReviewerEmail').textContent = data.reviewer_email;
            document.getElementById('rvRevieweeAvatar').src = data.reviewee_image || '<?= get_profile_image(null) ?>';
            document.getElementById('rvRevieweeName').textContent = data.reviewee_name;
            document.getElementById('rvRevieweeEmail').textContent = data.reviewee_email;

            // Set hidden form IDs
            document.getElementById('rvFormIdUnhide').value = data.id;
            document.getElementById('rvFormIdHide').value = data.id;
            document.getElementById('rvFormIdDel').value = data.id;

            // Toggle hide/unhide forms
            document.getElementById('rvUnhideForm').style.display = data.is_hidden ? '' : 'none';
            document.getElementById('rvHideForm').style.display = data.is_hidden ? 'none' : '';

            // Open
            document.getElementById('rvOverlay').classList.add('open');
            document.body.style.overflow = 'hidden';
        }

        function closeReviewDrawer(e) {
            if (e && e.target !== e.currentTarget) return;
            document.getElementById('rvOverlay').classList.remove('open');
            document.body.style.overflow = '';
            rvCurrent = null;
        }

        document.addEventListener('keydown', function(e) {
            if (e.key === 'Escape') closeReviewDrawer();
        });
    </script>

<?php require_once __DIR__ . '/../components/layout_end.php'; ?>
