<?php

/**
 * Admin Contracts — Read-only listing of all contracts
 * Display: Contract, Client, Freelancer, Job, Milestones, Escrow, Payment Status, Completion Status, Created Date
 */
require_once __DIR__ . '/../auth/auth.php';
require_role('admin');
require_once __DIR__ . '/../config/db.php';

$currentPage = 'contracts';

// ── Filters ─────────────────────────────────────────────────────────
$search = trim($_GET['search'] ?? '');
$statusF = $_GET['status'] ?? '';
$page = max(1, sanitize_int($_GET['page'] ?? 1));
$perPage = 15;

$allowedStatuses = ['active', 'completed', 'disputed', 'terminated'];
if ($statusF && !in_array($statusF, $allowedStatuses))
    $statusF = '';

// ── Build query ─────────────────────────────────────────────────────
$where = [];
$params = [];
$types = '';

if ($search !== '') {
    $where[] = '(cu.name LIKE ? OR fu.name LIKE ? OR j.title LIKE ? OR cl.company_name LIKE ?)';
    $searchTerm = "%{$search}%";
    $params[] = $searchTerm;
    $params[] = $searchTerm;
    $params[] = $searchTerm;
    $params[] = $searchTerm;
    $types .= 'ssss';
}
if ($statusF) {
    $where[] = 'c.status = ?';
    $params[] = $statusF;
    $types .= 's';
}

$whereSql = $where ? 'WHERE ' . implode(' AND ', $where) : '';

// ── Count ───────────────────────────────────────────────────────────
$countSql = "SELECT COUNT(*) AS cnt FROM contracts c
             JOIN users cu ON c.client_id = cu.id
             LEFT JOIN clients cl ON c.client_id = cl.client_id
             JOIN users fu ON c.freelancer_id = fu.id
             JOIN jobs j ON c.job_id = j.id
             {$whereSql}";
$countStmt = $conn->prepare($countSql);
if ($params)
    $countStmt->bind_param($types, ...$params);
$countStmt->execute();
$totalItems = (int) $countStmt->get_result()->fetch_assoc()['cnt'];
$countStmt->close();

$pagination = paginate($totalItems, $perPage, $page);

// ── Fetch contracts ─────────────────────────────────────────────────
$querySql = "SELECT c.id, c.contract_type, c.total_budget, c.status, c.created_at,
                    cu.id AS client_user_id, cu.name AS client_name, cu.profile_image AS client_image,
                    cl.company_name,
                    fu.id AS freelancer_user_id, fu.name AS freelancer_name, fu.profile_image AS freelancer_image,
                    fl.title AS freelancer_title,
                    j.id AS job_id, j.title AS job_title
             FROM contracts c
             JOIN users cu ON c.client_id = cu.id
             LEFT JOIN clients cl ON c.client_id = cl.client_id
             JOIN users fu ON c.freelancer_id = fu.id
             LEFT JOIN freelancers fl ON c.freelancer_id = fl.user_id
             JOIN jobs j ON c.job_id = j.id
             {$whereSql}
             ORDER BY c.created_at DESC
             LIMIT ? OFFSET ?";
$queryStmt = $conn->prepare($querySql);
$bindTypes = $types . 'ii';
$bindParams = array_merge($params, [$perPage, $pagination['offset']]);
$queryStmt->bind_param($bindTypes, ...$bindParams);
$queryStmt->execute();
$contractsResult = $queryStmt->get_result();
$queryStmt->close();

// ── Batch: milestones for visible contracts ──────────────────────────
$contractIds = [];
$contractMap = [];
while ($row = $contractsResult->fetch_assoc()) {
    $contractIds[] = (int) $row['id'];
    $contractMap[(int) $row['id']] = $row;
}
$contractsResult->free();

$milestoneStats = [];
$escrowAmounts = [];

if ($contractIds) {
    $idPlaceholders = implode(',', array_fill(0, count($contractIds), '?'));

    // Milestone counts and escrow per contract
    $ms = $conn->prepare("SELECT contract_id,
                                 COUNT(*) AS total,
                                 SUM(status = 'released') AS released,
                                 SUM(status = 'pending') AS pending_ms,
                                 SUM(status = 'funded_in_escrow') AS funded,
                                 SUM(status = 'submitted') AS submitted,
                                 SUM(status = 'disputed') AS disputed_ms,
                                 SUM(CASE WHEN status IN ('funded_in_escrow','submitted') THEN amount ELSE 0 END) AS escrow_amount
                          FROM milestones WHERE contract_id IN ({$idPlaceholders}) GROUP BY contract_id");
    $ms->bind_param(str_repeat('i', count($contractIds)), ...$contractIds);
    $ms->execute();
    $msRes = $ms->get_result();
    while ($r = $msRes->fetch_assoc()) {
        $cid = (int) $r['contract_id'];
        $milestoneStats[$cid] = [
            'total' => (int) $r['total'],
            'released' => (int) $r['released'],
            'pending_ms' => (int) $r['pending_ms'],
            'funded' => (int) $r['funded'],
            'submitted' => (int) $r['submitted'],
            'disputed_ms' => (int) $r['disputed_ms'],
        ];
        $escrowAmounts[$cid] = (float) $r['escrow_amount'];
    }
    $ms->close();

    // Payment status per contract (via milestones -> payments)
    $ps = $conn->prepare("SELECT m.contract_id,
                                 COUNT(p.id) AS total_payments,
                                 SUM(p.status = 'completed') AS completed_payments,
                                 SUM(p.status = 'pending') AS pending_payments,
                                 SUM(p.status = 'processing') AS processing_payments,
                                 SUM(p.status = 'failed') AS failed_payments
                          FROM milestones m
                          LEFT JOIN payments p ON m.id = p.milestone_id
                          WHERE m.contract_id IN ({$idPlaceholders})
                          GROUP BY m.contract_id");
    $ps->bind_param(str_repeat('i', count($contractIds)), ...$contractIds);
    $ps->execute();
    $psRes = $ps->get_result();
    while ($r = $psRes->fetch_assoc()) {
        $cid = (int) $r['contract_id'];
        $total = (int) $r['total_payments'];
        $completed = (int) $r['completed_payments'];
        $pending = (int) $r['pending_payments'];
        $processing = (int) $r['processing_payments'];
        $failed = (int) $r['failed_payments'];

        if ($total === 0) {
            $paymentStatus = 'no_payments';
        } elseif ($completed === $total) {
            $paymentStatus = 'completed';
        } elseif ($failed > 0) {
            $paymentStatus = 'failed';
        } elseif ($processing > 0) {
            $paymentStatus = 'processing';
        } elseif ($pending > 0 && $completed > 0) {
            $paymentStatus = 'partial';
        } else {
            $paymentStatus = 'pending';
        }

        if (!isset($milestoneStats[$cid]))
            $milestoneStats[$cid] = ['total' => 0, 'released' => 0, 'pending_ms' => 0, 'funded' => 0, 'submitted' => 0, 'disputed_ms' => 0];
        $milestoneStats[$cid]['payment_status'] = $paymentStatus;
    }
    $ps->close();
}

// ── Badge maps ──────────────────────────────────────────────────────
$contractStatusColors = [
    'active' => 'bg-emerald-100 text-emerald-700 dark:bg-emerald-900/30 dark:text-emerald-400',
    'completed' => 'bg-blue-100 text-blue-700 dark:bg-blue-900/30 dark:text-blue-400',
    'disputed' => 'bg-red-100 text-red-700 dark:bg-red-900/30 dark:text-red-400',
    'terminated' => 'bg-gray-100 text-gray-600 dark:bg-slate-700 dark:text-slate-400',
];
$paymentStatusMap = [
    'no_payments' => ['label' => 'No Payments', 'class' => 'bg-gray-100 text-gray-500 dark:bg-slate-700 dark:text-slate-400'],
    'pending' => ['label' => 'Pending', 'class' => 'bg-amber-100 text-amber-700 dark:bg-amber-900/30 dark:text-amber-400'],
    'partial' => ['label' => 'Partial', 'class' => 'bg-violet-100 text-violet-700 dark:bg-violet-900/30 dark:text-violet-400'],
    'processing' => ['label' => 'Processing', 'class' => 'bg-cyan-100 text-cyan-700 dark:bg-cyan-900/30 dark:text-cyan-400'],
    'completed' => ['label' => 'Completed', 'class' => 'bg-emerald-100 text-emerald-700 dark:bg-emerald-900/30 dark:text-emerald-400'],
    'failed' => ['label' => 'Failed', 'class' => 'bg-red-100 text-red-700 dark:bg-red-900/30 dark:text-red-400'],
];

// ── Build base URL ──────────────────────────────────────────────────
$baseUrl = 'contracts.php?';
if ($search !== '')
    $baseUrl .= 'search=' . urlencode($search) . '&';
if ($statusF)
    $baseUrl .= 'status=' . urlencode($statusF) . '&';
$baseUrl = rtrim($baseUrl, '?&');
if (strpos($baseUrl, '&') === false)
    $baseUrl = rtrim($baseUrl, '?');

// ── Admin nav ───────────────────────────────────────────────────────
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
$pageTitle = 'Contracts';
$pageSubtitle = number_format($totalItems) . ' contract' . ($totalItems !== 1 ? 's' : '') . ' found';
$activePage = 'contracts';
$user = ['name' => $adminName, 'profile_image' => $_navUserRow['profile_image'] ?? null];
$unreadCount = 0;
$profileLink = 'profile.php';
require_once __DIR__ . '/../components/layout_start.php';
?>
<style>
    .job-actions {
        transition: opacity .15s
    }

    /* Pagination overrides */
    nav.flex.items-center.justify-center.gap-6 a,
    nav.flex.items-center.justify-center.gap-6>div a {
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
    nav.flex.items-center.justify-center.gap-6>div a:hover {
        background: #F9FAFB !important;
        border-color: #D1D5DB !important;
    }

    nav.flex.items-center.justify-center.gap-6 a[class*="bg-gray-900"],
    nav.flex.items-center.justify-center.gap-6>div a[class*="bg-gray-900"] {
        background: #108A00 !important;
        border-color: #108A00 !important;
        color: white !important;
        box-shadow: 0 1px 3px rgba(16, 138, 0, 0.2) !important;
    }

    nav.flex.items-center.justify-center.gap-6 span.w-9,
    nav.flex.items-center.justify-center.gap-6>div span.w-9 {
        width: 2rem !important;
        height: 2rem !important;
        border-radius: 0.375rem !important;
    }
</style>

<?php display_flash('success'); ?>
<?php display_flash('error'); ?>

<!-- ═══ SEARCH & FILTER ════════════════════════════════════════════ -->
<div class="bg-white dark:bg-slate-800 border border-[#E4EBE4] dark:border-slate-700 rounded-t-lg p-5 mb-0 fade-in">
    <form method="GET" action="contracts.php">
        <div class="flex flex-col lg:flex-row gap-4 items-stretch lg:items-center">
            <div class="flex-1 relative">
                <i data-lucide="search" class="absolute left-3 top-1/2 -translate-y-1/2 w-4 h-4 text-gray-400 dark:text-slate-500" stroke-width="1.5"></i>
                <input type="text" name="search" value="<?= sanitize_string($search) ?>" placeholder="Search by client, freelancer, job, or company..."
                    class="w-full pl-10 pr-4 py-2.5 rounded-lg border border-[#E4EBE4] dark:border-slate-600 text-sm bg-gray-50 dark:bg-slate-700 text-gray-900 dark:text-white placeholder-gray-400 dark:placeholder-slate-500 focus:outline-none focus:ring-2 focus:ring-[#108A00]/30 focus:border-[#108A00] transition-all">
            </div>
            <div class="flex flex-wrap items-center gap-2">
                <select name="status" class="px-3 py-2.5 rounded-lg border border-[#E4EBE4] dark:border-slate-600 text-sm text-gray-600 dark:text-slate-300 focus:outline-none focus:ring-2 focus:ring-[#108A00]/30 focus:border-[#108A00] bg-gray-50 dark:bg-slate-700">
                    <option value="">All Statuses</option>
                    <option value="active" <?= $statusF === 'active' ? 'selected' : '' ?>>Active</option>
                    <option value="completed" <?= $statusF === 'completed' ? 'selected' : '' ?>>Completed</option>
                    <option value="disputed" <?= $statusF === 'disputed' ? 'selected' : '' ?>>Disputed</option>
                    <option value="terminated" <?= $statusF === 'terminated' ? 'selected' : '' ?>>Terminated</option>
                </select>
                <?php if ($search || $statusF): ?>
                    <a href="contracts.php" class="px-3 py-2.5 rounded-lg border border-[#E4EBE4] dark:border-slate-600 text-sm font-medium text-gray-500 dark:text-slate-400 hover:bg-gray-50 dark:hover:bg-slate-700 transition-colors inline-flex items-center gap-1.5">
                        <i data-lucide="x" class="w-4 h-4" stroke-width="1.5"></i> Clear
                    </a>
                <?php endif; ?>
            </div>
        </div>
    </form>
</div>

<!-- ═══ CONTRACTS TABLE ════════════════════════════════════════════ -->
<div class="bg-white dark:bg-slate-800 rounded-b-lg border border-[#E4EBE4] dark:border-slate-700 border-t-0 shadow-sm overflow-hidden fade-in" style="animation-delay:.1s">
    <?php if (count($contractMap) > 0): ?>
        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead>
                    <tr class="border-b border-[#E4EBE4] dark:border-slate-700 bg-gray-50 dark:bg-slate-700/30">
                        <th class="text-left py-2.5 px-3 text-[10px] font-medium text-gray-500 dark:text-slate-400 uppercase tracking-wider">Type</th>
                        <th class="text-left py-2.5 px-3 text-[10px] font-medium text-gray-500 dark:text-slate-400 uppercase tracking-wider">Client</th>
                        <th class="text-left py-2.5 px-3 text-[10px] font-medium text-gray-500 dark:text-slate-400 uppercase tracking-wider">Freelancer</th>
                        <th class="text-left py-2.5 px-3 text-[10px] font-medium text-gray-500 dark:text-slate-400 uppercase tracking-wider">Job</th>
                        <th class="text-center py-2.5 px-3 text-[10px] font-medium text-gray-500 dark:text-slate-400 uppercase tracking-wider">Milestones</th>
                        <th class="text-right py-2.5 px-3 text-[10px] font-medium text-gray-500 dark:text-slate-400 uppercase tracking-wider">Escrow</th>
                        <th class="text-center py-2.5 px-3 text-[10px] font-medium text-gray-500 dark:text-slate-400 uppercase tracking-wider">Payment</th>
                        <th class="text-center py-2.5 px-3 text-[10px] font-medium text-gray-500 dark:text-slate-400 uppercase tracking-wider">Completion</th>
                        <th class="text-right py-2.5 px-3 text-[10px] font-medium text-gray-500 dark:text-slate-400 uppercase tracking-wider">Created</th>
                        <th class="text-center py-2.5 px-3 text-[10px] font-medium text-gray-500 dark:text-slate-400 uppercase tracking-wider">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php
                    foreach ($contractMap as $cid => $ct):
                        $ms = $milestoneStats[$cid] ?? null;
                        $escrow = $escrowAmounts[$cid] ?? 0.0;
                        $totalMs = $ms ? $ms['total'] : 0;
                        $releasedMs = $ms ? $ms['released'] : 0;
                        $payStatus = $ms['payment_status'] ?? 'no_payments';
                        $payInfo = $paymentStatusMap[$payStatus] ?? $paymentStatusMap['no_payments'];

                        $completionPct = $totalMs > 0 ? round(($releasedMs / $totalMs) * 100) : 0;
                        if ($totalMs === 0) {
                            $completionLabel = 'No Milestones';
                            $completionClass = 'bg-gray-100 text-gray-600 dark:bg-slate-700 dark:text-slate-400';
                        } elseif ($completionPct === 100) {
                            $completionLabel = 'Complete';
                            $completionClass = 'bg-emerald-50 text-emerald-700 dark:bg-emerald-900/20 dark:text-emerald-400';
                        } else {
                            $completionLabel = $releasedMs . '/' . $totalMs;
                            $completionClass = 'bg-blue-50 text-blue-700 dark:bg-blue-900/20 dark:text-blue-400';
                        }
                        ?>
                        <tr class="border-b border-gray-100 dark:border-slate-700/50 last:border-0 hover:bg-[#F9FBF9] dark:hover:bg-slate-700/30 transition-colors">
                            <td class="py-2.5 px-3">
                                <span class="inline-flex items-center px-1.5 py-0.5 rounded text-[10px] font-normal bg-gray-100 dark:bg-slate-700 text-gray-700 dark:text-slate-400"><?= ucfirst($ct['contract_type']) ?></span>
                            </td>
                            <td class="py-2.5 px-3">
                                <a href="user_detail.php?id=<?= (int) $ct['client_user_id'] ?>" class="flex items-center gap-2 no-underline text-inherit">
                                    <img src="<?= sanitize_string(get_profile_image($ct['client_image'])) ?>" class="w-7 h-7 rounded-full object-cover border border-gray-200 dark:border-slate-600 flex-shrink-0">
                                    <div class="min-w-0">
                                        <p class="text-xs font-medium text-gray-900 dark:text-white truncate leading-snug"><?= sanitize_string($ct['client_name']) ?></p>
                                        <?php if ($ct['company_name']): ?>
                                            <p class="text-[10px] text-gray-500 dark:text-slate-400 truncate leading-snug"><?= sanitize_string($ct['company_name']) ?></p>
                                        <?php endif; ?>
                                    </div>
                                </a>
                            </td>
                            <td class="py-2.5 px-3">
                                <a href="user_detail.php?id=<?= (int) $ct['freelancer_user_id'] ?>" class="flex items-center gap-2 no-underline text-inherit">
                                    <img src="<?= sanitize_string(get_profile_image($ct['freelancer_image'])) ?>" class="w-7 h-7 rounded-full object-cover border border-gray-200 dark:border-slate-600 flex-shrink-0">
                                    <div class="min-w-0">
                                        <p class="text-xs font-medium text-gray-900 dark:text-white truncate leading-snug"><?= sanitize_string($ct['freelancer_name']) ?></p>
                                        <?php if ($ct['freelancer_title']): ?>
                                            <p class="text-[10px] text-gray-500 dark:text-slate-400 truncate leading-snug"><?= sanitize_string($ct['freelancer_title']) ?></p>
                                        <?php endif; ?>
                                    </div>
                                </a>
                            </td>
                            <td class="py-2.5 px-3 max-w-[140px]">
                                <a href="job_details.php?id=<?= (int) $ct['job_id'] ?>" class="text-[11px] font-medium text-blue-600 dark:text-blue-400 hover:underline truncate block"><?= sanitize_string($ct['job_title']) ?></a>
                            </td>
                            <td class="py-2.5 px-3 text-center">
                                <?php if ($totalMs > 0): ?>
                                    <div class="flex flex-col items-center gap-0.5">
                                        <span class="text-[11px] font-medium text-gray-700 dark:text-slate-300"><?= $releasedMs ?>/<?= $totalMs ?></span>
                                        <div class="w-12 bg-gray-200 dark:bg-slate-600 rounded-full h-1">
                                            <div class="bg-[#108A00] h-1 rounded-full transition-all" style="width:<?= $completionPct ?>%"></div>
                                        </div>
                                    </div>
                                <?php else: ?>
                                    <span class="text-[10px] text-gray-300 dark:text-slate-600">—</span>
                                <?php endif; ?>
                            </td>
                            <td class="py-2.5 px-3 text-right">
                                <?php if ($escrow > 0): ?>
                                    <span class="text-xs font-medium text-amber-600 dark:text-amber-400"><?= format_currency($escrow) ?></span>
                                <?php else: ?>
                                    <span class="text-[10px] text-gray-300 dark:text-slate-600">—</span>
                                <?php endif; ?>
                            </td>
                            <td class="py-2.5 px-3 text-center">
                                <span class="inline-flex items-center px-2 py-0.5 rounded-full text-[10px] font-normal <?= $payInfo['class'] ?>"><?= $payInfo['label'] ?></span>
                            </td>
                            <td class="py-2.5 px-3 text-center">
                                <span class="inline-flex items-center px-2 py-0.5 rounded-full text-[10px] font-normal <?= $completionClass ?>"><?= $completionLabel ?></span>
                            </td>
                            <td class="py-2.5 px-3 text-right text-gray-500 dark:text-slate-400 text-[10px] whitespace-nowrap"><?= time_ago($ct['created_at']) ?></td>
                            <td class="py-2.5 px-3">
                                <div class="flex items-center justify-center">
                                    <a href="contract_detail.php?id=<?= $cid ?>" class="inline-flex items-center gap-1 px-2 py-1 rounded bg-blue-50 text-blue-600 hover:bg-blue-100 transition-colors text-[10px] font-medium dark:bg-blue-900/30 dark:text-blue-400 dark:hover:bg-blue-900/50">
                                        View
                                    </a>
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
                <i data-lucide="file-text" class="w-6 h-6 text-gray-300 dark:text-slate-500" stroke-width="1.5"></i>
            </div>
            <p class="text-gray-500 dark:text-slate-400 text-sm mb-1">No contracts found</p>
            <p class="text-gray-400 dark:text-slate-500 text-xs">Try adjusting your search or filters</p>
        </div>
    <?php endif; ?>
</div>

<!-- ═══ PAGINATION ═════════════════════════════════════════════════ -->
<?php render_pagination($pagination, $baseUrl); ?>

<?php require_once __DIR__ . '/../components/layout_end.php'; ?>