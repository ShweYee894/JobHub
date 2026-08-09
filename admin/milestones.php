<?php

/**
 * Admin Milestones — Read-only monitoring of all milestones
 * Display: Contract, Milestone, Amount, Status, Submission, Approval, Released Payment, Timeline
 */
require_once __DIR__ . '/../auth/auth.php';
require_role('admin');
require_once __DIR__ . '/../config/db.php';

$currentPage = 'milestones';

// ── Filters ─────────────────────────────────────────────────────────
$search = trim($_GET['search'] ?? '');
$statusF = $_GET['status'] ?? '';
$page = max(1, sanitize_int($_GET['page'] ?? 1));
$perPage = 15;

$allowedStatuses = ['pending', 'funded_in_escrow', 'submitted', 'released', 'disputed'];
if ($statusF && !in_array($statusF, $allowedStatuses))
    $statusF = '';

// ── Build query ─────────────────────────────────────────────────────
$where = [];
$params = [];
$types = '';

if ($search !== '') {
    $where[] = '(m.title LIKE ? OR cu.name LIKE ? OR fu.name LIKE ? OR j.title LIKE ?)';
    $searchTerm = "%{$search}%";
    $params[] = $searchTerm;
    $params[] = $searchTerm;
    $params[] = $searchTerm;
    $params[] = $searchTerm;
    $types .= 'ssss';
}
if ($statusF) {
    $where[] = 'm.status = ?';
    $params[] = $statusF;
    $types .= 's';
}

$whereSql = $where ? 'WHERE ' . implode(' AND ', $where) : '';

// ── Count ───────────────────────────────────────────────────────────
$countSql = "SELECT COUNT(*) AS cnt FROM milestones m
             JOIN contracts ct ON m.contract_id = ct.id
             JOIN users cu ON ct.client_id = cu.id
             JOIN users fu ON ct.freelancer_id = fu.id
             JOIN jobs j ON ct.job_id = j.id
             {$whereSql}";
$countStmt = $conn->prepare($countSql);
if ($params)
    $countStmt->bind_param($types, ...$params);
$countStmt->execute();
$totalItems = (int) $countStmt->get_result()->fetch_assoc()['cnt'];
$countStmt->close();

$pagination = paginate($totalItems, $perPage, $page);

// ── Fetch milestones ────────────────────────────────────────────────
$querySql = "SELECT m.id, m.title, m.amount, m.status, m.due_date, m.submission_date,
                    m.submission_github_url, m.submission_file, m.submission_note, m.description, m.created_at,
                    ct.id AS contract_id, ct.status AS contract_status,
                    cu.id AS client_user_id, cu.name AS client_name, cu.profile_image AS client_image,
                    fu.id AS freelancer_user_id, fu.name AS freelancer_name, fu.profile_image AS freelancer_image,
                    j.title AS job_title
             FROM milestones m
             JOIN contracts ct ON m.contract_id = ct.id
             JOIN users cu ON ct.client_id = cu.id
             JOIN users fu ON ct.freelancer_id = fu.id
             JOIN jobs j ON ct.job_id = j.id
             {$whereSql}
             ORDER BY FIELD(m.status, 'disputed', 'submitted', 'funded_in_escrow', 'pending', 'released'), m.created_at DESC
             LIMIT ? OFFSET ?";
$queryStmt = $conn->prepare($querySql);
$bindTypes = $types . 'ii';
$bindParams = array_merge($params, [$perPage, $pagination['offset']]);
$queryStmt->bind_param($bindTypes, ...$bindParams);
$queryStmt->execute();
$milestonesResult = $queryStmt->get_result();
$queryStmt->close();

$milestoneMap = [];
$milestoneIds = [];
while ($row = $milestonesResult->fetch_assoc()) {
    $milestoneIds[] = (int) $row['id'];
    $milestoneMap[(int) $row['id']] = $row;
}
$milestonesResult->free();

// ── Batch: payments for visible milestones ───────────────────────────
$paymentsMap = [];
if ($milestoneIds) {
    $idPH = implode(',', array_fill(0, count($milestoneIds), '?'));
    $ps = $conn->prepare("SELECT milestone_id, total_amount, platform_fee, freelancer_net, status, created_at
                          FROM payments WHERE milestone_id IN ({$idPH}) ORDER BY created_at DESC");
    $ps->bind_param(str_repeat('i', count($milestoneIds)), ...$milestoneIds);
    $ps->execute();
    $psRes = $ps->get_result();
    while ($r = $psRes->fetch_assoc()) {
        $mid = (int) $r['milestone_id'];
        if (!isset($paymentsMap[$mid]))
            $paymentsMap[$mid] = [];
        $paymentsMap[$mid][] = $r;
    }
    $ps->close();
}

// ── Badge maps ──────────────────────────────────────────────────────
$statusColors = [
    'pending' => 'bg-gray-100 text-gray-600 dark:bg-slate-700 dark:text-slate-400',
    'funded_in_escrow' => 'bg-amber-100 text-amber-700 dark:bg-amber-900/30 dark:text-amber-400',
    'submitted' => 'bg-violet-100 text-violet-700 dark:bg-violet-900/30 dark:text-violet-400',
    'released' => 'bg-emerald-100 text-emerald-700 dark:bg-emerald-900/30 dark:text-emerald-400',
    'disputed' => 'bg-red-100 text-red-700 dark:bg-red-900/30 dark:text-red-400',
];
$statusLabels = [
    'pending' => 'Pending',
    'funded_in_escrow' => 'In Escrow',
    'submitted' => 'Submitted',
    'released' => 'Released',
    'disputed' => 'Disputed',
];

function approvalState(string $msStatus): array
{
    $map = [
        'pending' => ['label' => 'Awaiting Funding', 'class' => 'bg-gray-100 text-gray-500 dark:bg-slate-700 dark:text-slate-400', 'icon' => 'clock'],
        'funded_in_escrow' => ['label' => 'Awaiting Submission', 'class' => 'bg-amber-100 text-amber-600 dark:bg-amber-900/30 dark:text-amber-400', 'icon' => 'hourglass'],
        'submitted' => ['label' => 'Awaiting Approval', 'class' => 'bg-violet-100 text-violet-600 dark:bg-violet-900/30 dark:text-violet-400', 'icon' => 'loader'],
        'released' => ['label' => 'Approved', 'class' => 'bg-emerald-100 text-emerald-600 dark:bg-emerald-900/30 dark:text-emerald-400', 'icon' => 'circle-check'],
        'disputed' => ['label' => 'Under Review', 'class' => 'bg-red-100 text-red-600 dark:bg-red-900/30 dark:text-red-400', 'icon' => 'triangle-alert'],
    ];
    return $map[$msStatus] ?? $map['pending'];
}

$paymentStatusColors = [
    'pending' => 'bg-amber-100 text-amber-700 dark:bg-amber-900/30 dark:text-amber-400',
    'processing' => 'bg-cyan-100 text-cyan-700 dark:bg-cyan-900/30 dark:text-cyan-400',
    'completed' => 'bg-emerald-100 text-emerald-700 dark:bg-emerald-900/30 dark:text-emerald-400',
    'failed' => 'bg-red-100 text-red-700 dark:bg-red-900/30 dark:text-red-400',
    'refunded' => 'bg-purple-100 text-purple-700 dark:bg-purple-900/30 dark:text-purple-400',
];

// ── Build base URL ──────────────────────────────────────────────────
$baseUrl = 'milestones.php?';
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
$pageTitle = 'Milestones';
$pageSubtitle = number_format($totalItems) . ' milestone' . ($totalItems !== 1 ? 's' : '') . ' across all contracts';
$activePage = 'milestones';
$user = ['name' => $adminName, 'profile_image' => $_navUserRow['profile_image'] ?? null];
$unreadCount = 0;
$profileLink = 'profile.php';
require_once __DIR__ . '/../components/layout_start.php';
?>
    <style>
    .tl-step{display:flex;align-items:center;gap:4px}
    .tl-dot{width:8px;height:8px;border-radius:50%;flex-shrink:0}
    .tl-line{width:12px;height:2px;flex-shrink:0;border-radius:1px}
    .job-actions{transition:opacity .15s}

    /* Pagination overrides */
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

    <!-- ═══ SEARCH & FILTER ════════════════════════════════════════════ -->
    <div class="bg-white dark:bg-slate-800 border border-[#E4EBE4] dark:border-slate-700 rounded-t-lg p-5 mb-0 fade-in">
        <form method="GET" action="milestones.php">
            <div class="flex flex-col lg:flex-row gap-4 items-stretch lg:items-center">
                <div class="flex-1 relative">
                    <i data-lucide="search" class="absolute left-3 top-1/2 -translate-y-1/2 w-4 h-4 text-gray-400 dark:text-slate-500" stroke-width="1.5"></i>
                    <input type="text" name="search" value="<?= sanitize_string($search) ?>" placeholder="Search by milestone, client, freelancer, or job..."
                           class="w-full pl-10 pr-4 py-2.5 rounded-lg border border-[#E4EBE4] dark:border-slate-600 text-sm bg-gray-50 dark:bg-slate-700 text-gray-900 dark:text-white placeholder-gray-400 dark:placeholder-slate-500 focus:outline-none focus:ring-2 focus:ring-[#108A00]/30 focus:border-[#108A00] transition-all">
                </div>
                <div class="flex flex-wrap items-center gap-2">
                    <select name="status" class="px-3 py-2.5 rounded-lg border border-[#E4EBE4] dark:border-slate-600 text-sm text-gray-600 dark:text-slate-300 focus:outline-none focus:ring-2 focus:ring-[#108A00]/30 focus:border-[#108A00] bg-gray-50 dark:bg-slate-700">
                        <option value="">All Statuses</option>
                        <?php foreach ($allowedStatuses as $s): ?>
                        <option value="<?= $s ?>" <?= $statusF === $s ? 'selected' : '' ?>><?= str_replace('_', ' ', ucfirst($s)) ?></option>
                        <?php endforeach; ?>
                    </select>
                    <?php if ($search || $statusF): ?>
                        <a href="milestones.php" class="px-3 py-2.5 rounded-lg border border-[#E4EBE4] dark:border-slate-600 text-sm font-medium text-gray-500 dark:text-slate-400 hover:bg-gray-50 dark:hover:bg-slate-700 transition-colors inline-flex items-center gap-1.5">
                            <i data-lucide="x" class="w-4 h-4" stroke-width="1.5"></i> Clear
                        </a>
                    <?php endif; ?>
                </div>
            </div>
        </form>
    </div>

    <!-- ═══ MILESTONES TABLE ═══════════════════════════════════════════ -->
    <div class="bg-white dark:bg-slate-800 rounded-b-lg border border-[#E4EBE4] dark:border-slate-700 border-t-0 shadow-sm overflow-hidden fade-in" style="animation-delay:.1s">
        <?php if (count($milestoneMap) > 0): ?>
        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead>
                    <tr class="border-b border-[#E4EBE4] dark:border-slate-700 bg-gray-50 dark:bg-slate-700/30">
                        <th class="text-left py-3 px-5 text-[11px] font-medium text-gray-500 dark:text-slate-400 uppercase tracking-wider">Contract</th>
                        <th class="text-left py-3 px-5 text-[11px] font-medium text-gray-500 dark:text-slate-400 uppercase tracking-wider">Milestone</th>
                        <th class="text-right py-3 px-5 text-[11px] font-medium text-gray-500 dark:text-slate-400 uppercase tracking-wider">Amount</th>
                        <th class="text-center py-3 px-5 text-[11px] font-medium text-gray-500 dark:text-slate-400 uppercase tracking-wider">Status</th>
                        <th class="text-center py-3 px-5 text-[11px] font-medium text-gray-500 dark:text-slate-400 uppercase tracking-wider">Due Date</th>
                        <th class="text-center py-3 px-5 text-[11px] font-medium text-gray-500 dark:text-slate-400 uppercase tracking-wider">Submission</th>
                        <th class="text-center py-3 px-5 text-[11px] font-medium text-gray-500 dark:text-slate-400 uppercase tracking-wider">Approval</th>
                        <th class="text-right py-3 px-5 text-[11px] font-medium text-gray-500 dark:text-slate-400 uppercase tracking-wider">Payment</th>
                        <th class="text-center py-3 px-5 text-[11px] font-medium text-gray-500 dark:text-slate-400 uppercase tracking-wider min-w-[200px]">Timeline</th>
                        <th class="text-right py-3 px-5 text-[11px] font-medium text-gray-500 dark:text-slate-400 uppercase tracking-wider">Actions</th>
                    </tr>
                </thead>
                <tbody>
                <?php
                foreach ($milestoneMap as $mid => $m):
                    $pays = $paymentsMap[$mid] ?? [];
                    $hasPayment = count($pays) > 0;
                    $completedPayment = null;
                    foreach ($pays as $p) {
                        if ($p['status'] === 'completed') {
                            $completedPayment = $p;
                            break;
                        }
                    }
                    if (!$completedPayment && $hasPayment)
                        $completedPayment = $pays[0];

                    $appr = approvalState($m['status']);
                    $hasSubmission = !empty($m['submission_date']);
                    ?>
                    <tr class="border-b border-gray-100 dark:border-slate-700/50 last:border-0 hover:bg-[#F9FBF9] dark:hover:bg-slate-700/30 transition-colors">
                        <td class="py-3.5 px-5">
                            <a href="contract_detail.php?id=<?= (int) $m['contract_id'] ?>" class="no-underline text-inherit">
                                <!-- <p class="text-xs font-medium text-blue-600 dark:text-blue-400 hover:underline leading-snug">#<?= $m['contract_id'] ?></p> -->
                                <p class="text-[14px] text-gray-900 dark:text-slate-400 max-w-[140px] truncate leading-snug"><?= sanitize_string($m['job_title']) ?></p>
                            </a>
                        </td>
                        <td class="py-3.5 px-5">
                            <p class="text-xs font-medium text-gray-900 dark:text-white leading-snug"><?= sanitize_string($m['title']) ?></p>
                            <!-- <p class="text-[10px] text-gray-500 dark:text-slate-400 leading-snug">ID: #<?= $mid ?></p> -->
                        </td>
                        <td class="py-3.5 px-5 text-right">
                            <span class="text-sm font-medium text-gray-900 dark:text-white"><?= format_currency((float) $m['amount']) ?></span>
                        </td>
                        <td class="py-3.5 px-5 text-center">
                            <span class="inline-flex items-center px-2.5 py-1 rounded-full text-[11px] font-normal <?= $statusColors[$m['status']] ?? '' ?>"><?= $statusLabels[$m['status']] ?? $m['status'] ?></span>
                        </td>
                        <td class="py-3.5 px-5 text-center">
                            <?php if (!empty($m['due_date'])): ?>
                                <p class="text-[11px] font-medium <?php
                        $dueDate = new DateTime($m['due_date']);
                        $now = new DateTime();
                        if ($dueDate < $now && !in_array($m['status'], ['released', 'disputed'])) {
                            echo 'text-red-500';
                        } else {
                            echo 'text-gray-700 dark:text-slate-300';
                        }
                        ?>"><?= date('M j, Y', strtotime($m['due_date'])) ?></p>
                                <?php if ($dueDate < $now && !in_array($m['status'], ['released', 'disputed'])): ?>
                                    <p class="text-[9px] text-red-400"><i data-lucide="alert-circle" class="w-3 h-3 inline" stroke-width="2"></i> Overdue</p>
                                <?php endif; ?>
                            <?php else: ?>
                                <span class="text-[10px] text-gray-300 dark:text-slate-600">—</span>
                            <?php endif; ?>
                        </td>
                        <td class="py-3.5 px-5 text-center">
                            <?php if ($hasSubmission): ?>
                            <div>
                                <p class="text-[11px] font-medium text-emerald-600 dark:text-emerald-400"><?= date('M j', strtotime($m['submission_date'])) ?></p>
                                <?php if ($m['submission_github_url']): ?>
                                <a href="<?= sanitize_string($m['submission_github_url']) ?>" target="_blank" class="text-[10px] text-blue-500 hover:underline"><i data-lucide="github" class="w-3 h-3 inline" stroke-width="2"></i> Link</a>
                                <?php elseif ($m['submission_file']): ?>
                                <span class="text-[10px] text-gray-500 dark:text-slate-400"><i data-lucide="paperclip" class="w-3 h-3 inline" stroke-width="2"></i> File</span>
                                <?php endif; ?>
                            </div>
                            <?php else: ?>
                            <span class="text-[10px] text-gray-300 dark:text-slate-600">—</span>
                            <?php endif; ?>
                        </td>
                        <td class="py-3.5 px-5 text-center">
                            <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-md text-[10px] font-normal <?= $appr['class'] ?>">
                                <?= $appr['label'] ?>
                            </span>
                        </td>
                        <td class="py-3.5 px-5 text-right">
                            <?php if ($completedPayment && $completedPayment['status'] === 'completed'): ?>
                            <div>
                                <p class="text-xs font-medium text-emerald-600 dark:text-emerald-400 leading-snug"><?= format_currency((float) $completedPayment['freelancer_net']) ?></p>
                                <p class="text-[9px] text-gray-500 dark:text-slate-400 leading-snug">net · <?= date('M j', strtotime($completedPayment['created_at'])) ?></p>
                            </div>
                            <?php elseif ($hasPayment): ?>
                            <div>
                                <span class="inline-flex items-center px-2 py-0.5 rounded-md text-[9px] font-normal <?= $paymentStatusColors[$completedPayment['status']] ?? '' ?>"><?= ucfirst($completedPayment['status']) ?></span>
                                <p class="text-[9px] text-gray-500 dark:text-slate-400 mt-0.5 leading-snug"><?= format_currency((float) $completedPayment['total_amount']) ?></p>
                            </div>
                            <?php else: ?>
                            <span class="text-[10px] text-gray-300 dark:text-slate-600">—</span>
                            <?php endif; ?>
                        </td>
                        <td class="py-3.5 px-5">
                            <?php
                            $steps = [
                                ['label' => 'Created', 'done' => true, 'color' => 'bg-blue-500'],
                                ['label' => 'Funded', 'done' => in_array($m['status'], ['funded_in_escrow', 'submitted', 'released']), 'color' => 'bg-amber-500'],
                                ['label' => 'Submitted', 'done' => in_array($m['status'], ['submitted', 'released']), 'color' => 'bg-violet-500'],
                                ['label' => 'Released', 'done' => $m['status'] === 'released', 'color' => 'bg-[#108A00]'],
                            ];
                            if ($m['status'] === 'disputed')
                                $steps[3] = ['label' => 'Disputed', 'done' => true, 'color' => 'bg-red-500'];
                            ?>
                            <div class="flex items-center justify-center gap-0">
                                <?php foreach ($steps as $i => $step): ?>
                                <div class="tl-step" title="<?= $step['label'] ?>">
                                    <div class="tl-dot <?= $step['done'] ? $step['color'] : 'bg-gray-200 dark:bg-slate-600' ?>"></div>
                                    <?php if ($i < count($steps) - 1): ?>
                                    <div class="tl-line <?= $step['done'] && $steps[$i + 1]['done'] ? $steps[$i + 1]['color'] : 'bg-gray-200 dark:bg-slate-600' ?>"></div>
                                    <?php endif; ?>
                                </div>
                                <?php endforeach; ?>
                            </div>
                        </td>
                        <td class="py-3.5 px-5">
                            <div class="job-actions flex items-center justify-end">
                                <!-- <a href="milestone_detail.php?id=<?= $mid ?>" class="w-8 h-8 rounded-lg bg-gray-50 dark:bg-slate-700 text-gray-500 dark:text-slate-400 flex items-center justify-center hover:bg-gray-100 dark:hover:bg-slate-600 transition-colors" title="View Details">
                                    <i data-lucide="eye" class="w-4 h-4" stroke-width="1.5"></i>
                                </a> -->
                                <a href="milestone_detail.php?id=<?= $mid ?>" class="inline-flex items-center gap-1.5 px-2 py-1.5 rounded-lg bg-blue-50 text-blue-600 hover:bg-blue-100 transition-colors text-[11px] font-medium dark:bg-blue-900/30 dark:text-blue-400 dark:hover:bg-blue-900/50">
                                        <span>View</span>
                                        <!-- <i data-lucide="arrow-right" class="w-3.5 h-3.5"></i> -->
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
                <i data-lucide="list-checks" class="w-6 h-6 text-gray-300 dark:text-slate-500" stroke-width="1.5"></i>
            </div>
            <p class="text-gray-500 dark:text-slate-400 text-sm mb-1">No milestones found</p>
            <p class="text-gray-400 dark:text-slate-500 text-xs">Try adjusting your search or filters</p>
        </div>
        <?php endif; ?>
    </div>

    <!-- ═══ PAGINATION ═════════════════════════════════════════════════ -->
    <?php render_pagination($pagination, $baseUrl); ?>

<?php require_once __DIR__ . '/../components/layout_end.php'; ?>
