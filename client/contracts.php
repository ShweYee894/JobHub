<?php

/**
 * Client Contracts Dashboard
 * Lists all contracts for the current client with full dashboard features:
 * search, status filters, budget, payments summary, progress, and actions.
 */
$currentPage = 'contracts';
require_once __DIR__ . '/../auth/auth.php';
require_role('client');
require_once __DIR__ . '/../config/db.php';

$userId = $_SESSION['user_id'];

// ── Fetch User Data ───────────────────────────────────────────────────
$uStmt = $conn->prepare('SELECT name, profile_image FROM users WHERE id = ?');
$uStmt->bind_param('i', $userId);
$uStmt->execute();
$user = $uStmt->get_result()->fetch_assoc();
$uStmt->close();

// ── Filters & Search ──────────────────────────────────────────────────
$statusFilter = $_GET['status'] ?? 'all';
$searchQuery = trim($_GET['q'] ?? '');
$page = max(1, intval($_GET['page'] ?? 1));
$perPage = 10;

$allowedStatuses = ['all', 'active', 'completed', 'cancelled', 'disputed'];
if (!in_array($statusFilter, $allowedStatuses))
    $statusFilter = 'all';

$where = 'WHERE c.client_id = ?';
$params = [$userId];
$types = 'i';

if ($statusFilter !== 'all') {
    $where .= ' AND c.status = ?';
    $params[] = $statusFilter;
    $types .= 's';
}

if ($searchQuery !== '') {
    $where .= ' AND (u.name LIKE ? OR j.title LIKE ?)';
    $like = '%' . $searchQuery . '%';
    $params[] = $like;
    $params[] = $like;
    $types .= 'ss';
}

// ── Count ─────────────────────────────────────────────────────────────
$countSql = "SELECT COUNT(*) AS total FROM contracts c
             JOIN jobs j ON c.job_id = j.id
             JOIN freelancers f ON c.freelancer_id = f.user_id
             JOIN users u ON f.user_id = u.id
             $where";
$stmt = $conn->prepare($countSql);
$stmt->bind_param($types, ...$params);
$stmt->execute();
$totalFiltered = (int) $stmt->get_result()->fetch_assoc()['total'];
$stmt->close();

$pagination = paginate($totalFiltered, $perPage, $page);

// ── Fetch Contracts ───────────────────────────────────────────────────
$querySql = "SELECT c.id, c.contract_type, c.status, c.total_budget, c.created_at,
             c.dispute_status, c.cancellation_reason,
             j.title AS job_title, j.budget AS job_budget,
             u.name AS freelancer_name, u.email AS freelancer_email, u.id AS freelancer_user_id,
             (SELECT COUNT(*) FROM milestones m WHERE m.contract_id = c.id) AS milestone_count,
             (SELECT COALESCE(SUM(m.amount), 0) FROM milestones m WHERE m.contract_id = c.id) AS milestone_total,
             (SELECT COUNT(*) FROM milestones m WHERE m.contract_id = c.id AND m.status = 'released') AS released_count,
             (SELECT COALESCE(SUM(p.total_amount), 0) FROM payments p
              JOIN milestones m2 ON p.milestone_id = m2.id
              WHERE m2.contract_id = c.id AND p.status = 'completed') AS total_paid,
             (SELECT cr.id FROM chat_rooms cr WHERE cr.contract_id = c.id LIMIT 1) AS chat_room_id
             FROM contracts c
             JOIN jobs j ON c.job_id = j.id
             JOIN freelancers f ON c.freelancer_id = f.user_id
             JOIN users u ON f.user_id = u.id
             $where
             ORDER BY c.created_at DESC
             LIMIT ? OFFSET ?";

$finalTypes = $types . 'ii';
$finalParams = array_merge($params, [$pagination['per_page'], $pagination['offset']]);

$stmt = $conn->prepare($querySql);
$stmt->bind_param($finalTypes, ...$finalParams);
$stmt->execute();
$contractsResult = $stmt->get_result();
$stmt->close();

$contractColors = [
    'active' => 'bg-emerald-50 text-emerald-600 border border-emerald-200',
    'completed' => 'bg-blue-50 text-blue-600 border border-blue-200',
    'cancelled' => 'bg-gray-100 text-gray-500 border border-gray-200',
    'disputed' => 'bg-red-50 text-red-500 border border-red-200',
];

$contractIcons = [
    'active' => 'circle-check',
    'completed' => 'trophy',
    'cancelled' => 'ban',
    'disputed' => 'triangle-alert',
    'terminated' => 'circle-x',
];

// Build query string base for pagination & search links
$queryParams = [];
if ($statusFilter !== 'all')
    $queryParams['status'] = $statusFilter;
if ($searchQuery !== '')
    $queryParams['q'] = $searchQuery;
$baseQuery = http_build_query($queryParams);
$baseSep = $baseQuery ? '&' : '';

// ── Top Nav Setup ─────────────────────────────────────────────────────
$pageTitle = 'Contracts Dashboard';
$pageSubtitle = 'Manage your contracts, milestones, and payments';
$activePage = 'contracts';
$user = ['name' => $user['name'] ?? 'Client', 'profile_image' => $user['profile_image'] ?? null];
$unreadCount = get_unread_message_count($userId, 'client');
$profileLink = 'profile.php';
require_once __DIR__ . '/../includes/client_topbar.php';
?>

    <?php display_flash('success') ?>
    <?php display_flash('error') ?>

    <!-- ═══ SUMMARY STATS ═══════════════════════════════════ -->
    <?php
    $statsSql = "SELECT
        COUNT(*) AS total_contracts,
        SUM(CASE WHEN c.status = 'active' THEN 1 ELSE 0 END) AS active_count,
        SUM(CASE WHEN c.status = 'completed' THEN 1 ELSE 0 END) AS completed_count,
        SUM(CASE WHEN c.status = 'disputed' THEN 1 ELSE 0 END) AS disputed_count,
        COALESCE(SUM(c.total_budget), 0) AS total_budget_sum
    FROM contracts c WHERE c.client_id = ?";
    $statsStmt = $conn->prepare($statsSql);
    $statsStmt->bind_param('i', $userId);
    $statsStmt->execute();
    $stats = $statsStmt->get_result()->fetch_assoc();
    $statsStmt->close();
    $conn->close();
    ?>
<main class="max-w-7xl mx-auto px-4 sm:px-6 py-8 flex flex-col gap-6">  
    <div class="grid grid-cols-2 lg:grid-cols-5 gap-4 fade-in">
        <div class=" p-4 border-x border-gray-100  dark:bg-gray-800 dark:border-gray-700">
            <div class="flex items-center gap-3">
                <div class="w-10 h-10 rounded-[10px] bg-blue-50 flex items-center justify-center">
                    <i data-lucide="layers" class="w-5 h-5 text-blue-500"></i>
                </div>
                <div>
                    <p class="text-[11px] text-gray-400 font-medium">Total</p>
                    <p class="text-lg font-black text-gray-900 dark:text-white"><?= (int) $stats['total_contracts'] ?></p>
                </div>
            </div>
        </div>
        <div class=" p-4 border-r border-gray-100  dark:bg-gray-800 dark:border-gray-700">
            <div class="flex items-center gap-3">
                <div class="w-10 h-10 rounded-[10px] bg-emerald-50 flex items-center justify-center">
                    <i data-lucide="circle-check" class="w-5 h-5 text-emerald-500"></i>
                </div>
                <div>
                    <p class="text-[11px] text-gray-400 font-medium">Active</p>
                    <p class="text-lg font-black text-emerald-600"><?= (int) $stats['active_count'] ?></p>
                </div>
            </div>
        </div>
        <div class=" p-4 border-r border-gray-100 dark:bg-gray-800 dark:border-gray-700">
            <div class="flex items-center gap-3">
                <div class="w-10 h-10 rounded-[10px] bg-blue-50 flex items-center justify-center">
                    <i data-lucide="trophy" class="w-5 h-5 text-blue-500"></i>
                </div>
                <div>
                    <p class="text-[11px] text-gray-400 font-medium">Completed</p>
                    <p class="text-lg font-black text-blue-600"><?= (int) $stats['completed_count'] ?></p>
                </div>
            </div>
        </div>
        <div class=" p-4 border-r border-gray-100  dark:bg-gray-800 dark:border-gray-700">
            <div class="flex items-center gap-3">
                <div class="w-10 h-10 rounded-[10px] bg-red-50 flex items-center justify-center">
                    <i data-lucide="triangle-alert" class="w-5 h-5 text-red-500"></i>
                </div>
                <div>
                    <p class="text-[11px] text-gray-400 font-medium">Disputed</p>
                    <p class="text-lg font-black text-red-500"><?= (int) $stats['disputed_count'] ?></p>
                </div>
            </div>
        </div>
        <div class="p-4 border-r border-gray-100 col-span-2 lg:col-span-1 dark:bg-gray-800 dark:border-gray-700">
            <div class="flex items-center gap-3">
                <div class="w-10 h-10 rounded-[10px] bg-violet-50 flex items-center justify-center">
                    <i data-lucide="dollar-sign" class="w-5 h-5 text-violet-500"></i>
                </div>
                <div>
                    <p class="text-[11px] text-gray-400 font-medium">Total Budget</p>
                    <p class="text-lg font-black text-gray-900 dark:text-white"><?= format_currency((float) $stats['total_budget_sum']) ?></p>
                </div>
            </div>
        </div>
    </div>

    <!-- ═══ SEARCH & FILTER BAR ════════════════════════════ -->
    <div class="bg-white rounded-2xl p-5 border border-gray-100 shadow-sm fade-in dark:bg-gray-800 dark:border-gray-700" style="animation-delay:.1s">
        <form method="GET" action="" class="flex flex-col sm:flex-row gap-3">
            <div class="flex-1 relative">
                <i data-lucide="search" class="absolute left-3.5 top-1/2 -translate-y-1/2 text-gray-400 w-4 h-4"></i>
                <input
                    type="search"
                    name="q"
                    value="<?= sanitize_string($searchQuery) ?>"
                    placeholder="Search by freelancer name or job title..."
                    class="w-full pl-10 pr-10 py-2.5 rounded-xl border border-gray-200 text-sm bg-gray-50 focus:bg-white focus:outline-none focus:border-blue-500 focus:ring-2 focus:ring-blue-500/10 transition-all dark:bg-gray-700 dark:border-gray-600 dark:text-white"
                />
                <?php if ($searchQuery !== ''): ?>
                    <a href="?status=<?= $statusFilter ?>" class="absolute right-3 top-1/2 -translate-y-1/2 text-gray-400 hover:text-gray-600">
                        <i data-lucide="x" class="w-4 h-4"></i>
                    </a>
                <?php endif; ?>
            </div>
            <button type="submit" class="inline-flex items-center justify-center gap-2 px-5 py-2.5 btn-grad text-white text-xs font-semibold rounded-xl shadow-lg shadow-blue-500/25">
                <i data-lucide="search" class="w-4 h-4"></i> Search
            </button>
        </form>

        <div class="flex flex-wrap gap-2 mt-4 pt-4 border-t border-gray-100 dark:border-gray-700">
            <?php
            $filters = [
                'all' => ['All', 'layers', 'text-gray-600', 'bg-gray-100'],
                'active' => ['Active', 'circle-check', 'text-emerald-600', 'bg-emerald-50'],
                'completed' => ['Completed', 'trophy', 'text-blue-600', 'bg-blue-50'],
                'cancelled' => ['Cancelled', 'ban', 'text-gray-500', 'bg-gray-100'],
                'disputed' => ['Disputed', 'triangle-alert', 'text-red-500', 'bg-red-50'],
                'terminated' => ['Terminated', 'circle-x', 'text-orange-500', 'bg-orange-50'],
            ];
            foreach ($filters as $key => $label):
                $isActive = $statusFilter === $key;
                $linkParams = $key !== 'all' ? '?' . http_build_query(array_filter(['status' => $key, 'q' => $searchQuery])) : '?' . http_build_query(array_filter(['q' => $searchQuery]));
                ?>
                <a href="<?= $linkParams ?>"
                    class="inline-flex items-center gap-1.5 px-4 py-2 rounded-xl text-xs font-semibold transition-all <?= $isActive ? $label[3] . ' ' . $label[2] . ' border border-current/20' : 'text-gray-500 bg-gray-50 hover:bg-gray-100 border border-transparent dark:bg-gray-700 dark:hover:bg-gray-600 dark:text-gray-400' ?>">
                    <i data-lucide="<?= $label[1] ?>" class="w-3 h-3"></i> <?= $label[0] ?>
                </a>
            <?php endforeach; ?>

            <?php if ($searchQuery !== '' || $statusFilter !== 'all'): ?>
                <a href="contracts.php" class="inline-flex items-center gap-1.5 px-4 py-2 rounded-xl text-xs font-semibold text-red-500 bg-red-50 hover:bg-red-100 border border-red-200 transition-all ml-auto">
                    <i data-lucide="x" class="w-3 h-3"></i> Clear All
                </a>
            <?php endif; ?>
        </div>
    </div>

    <!-- ═══ ACTIVE SEARCH INFO ═════════════════════════════ -->
    <?php if ($searchQuery !== '' || $statusFilter !== 'all'): ?>
        <div class="flex items-center gap-2 text-xs text-gray-400 fade-in">
            <i data-lucide="circle-info" class="w-4 h-4"></i>
            <span>
                Showing <span class="font-semibold text-gray-600"><?= $totalFiltered ?></span> result<?= $totalFiltered !== 1 ? 's' : '' ?>
                <?php if ($searchQuery !== ''): ?>
                    for "<span class="font-semibold text-gray-600"><?= sanitize_string($searchQuery) ?></span>"
                <?php endif; ?>
                <?php if ($statusFilter !== 'all'): ?>
                    with status <span class="font-semibold text-gray-600"><?= ucfirst($statusFilter) ?></span>
                <?php endif; ?>
            </span>
        </div>
    <?php endif; ?>

    <!-- ═══ CONTRACTS LIST ═══════════════════════════════════ -->
    <?php if ($contractsResult->num_rows > 0): ?>
        <div class="space-y-4">
            <?php
            while ($c = $contractsResult->fetch_assoc()):
                $milestoneCount = (int) $c['milestone_count'];
                $releasedCount = (int) $c['released_count'];
                $progress = $milestoneCount > 0 ? round(($releasedCount / $milestoneCount) * 100) : 0;
                $totalPaid = (float) $c['total_paid'];
                $totalBudget = (float) $c['total_budget'];
                $paymentPct = $totalBudget > 0 ? round(($totalPaid / $totalBudget) * 100) : 0;
                $chatRoomId = $c['chat_room_id'] ? (int) $c['chat_room_id'] : 0;
                ?>
                <div class=" bg-slate-50 rounded-2xl border border-gray-100 shadow-sm hover:shadow-md transition-all fade-in dark:bg-gray-800 dark:border-gray-700">
                    <div class="p-6">
                        <div class="flex flex-col xl:flex-row xl:items-start gap-4">

                            <!-- ── Contract Info ──────────────────── -->
                            <div class="flex-1 min-w-0">
                                <!-- Title & Status -->
                                <div class="flex flex-wrap items-center gap-2 mb-2">
                                    <a href="contract_detail.php?id=<?= (int) $c['id'] ?>" class="text-base font-bold text-gray-900 hover:text-blue-600 transition-colors dark:text-white dark:hover:text-blue-400">
                                        <?= sanitize_string($c['job_title']) ?>
                                    </a>
                                    <span class="inline-flex items-center gap-1 px-2.5 py-1 rounded-lg text-[11px] font-semibold <?= $contractColors[$c['status']] ?? 'bg-gray-100 text-gray-500 border border-gray-200' ?>">
                                         <i data-lucide="<?= $contractIcons[$c['status']] ?? 'circle' ?>" class="w-3 h-3"></i>
                                        <?= sanitize_string(ucfirst($c['status'])) ?>
                                    </span>
                                    <?php if ($c['contract_type']): ?>
                                        <span class="inline-block px-2 py-0.5 rounded text-[10px] font-medium bg-gray-100 text-gray-500 border border-gray-200">
                                            <?= sanitize_string(ucfirst($c['contract_type'])) ?>
                                        </span>
                                    <?php endif; ?>
                                </div>

                                <!-- Meta Row -->
                                <div class="flex flex-wrap items-center gap-x-4 gap-y-1.5 text-xs text-gray-400 mb-3">
                                    <span class="flex items-center gap-1.5">
                                        <i data-lucide="user" class="w-4 h-4 text-blue-400"></i>
                                        Freelancer: <span class="font-semibold text-gray-700 dark:text-gray-300"><?= sanitize_string($c['freelancer_name']) ?></span>
                                    </span>
                                    <span class="flex items-center gap-1.5">
                                        <i data-lucide="dollar-sign" class="w-4 h-4 text-emerald-500"></i>
                                        Budget: <span class="font-semibold text-gray-700"><?= format_currency($totalBudget) ?></span>
                                    </span>
                                    <span class="flex items-center gap-1.5">
                                        <i data-lucide="credit-card" class="w-4 h-4 text-violet-400"></i>
                                        Paid: <span class="font-semibold <?= $totalPaid >= $totalBudget ? 'text-emerald-600' : 'text-gray-700' ?>"><?= format_currency($totalPaid) ?></span>
                                        <span class="text-gray-300">/</span>
                                        <span class="text-gray-300"><?= $paymentPct ?>%</span>
                                    </span>
                                    <span class="flex items-center gap-1.5">
                                        <i data-lucide="calendar" class="w-4 h-4 text-gray-400"></i>
                                        <?= date('M d, Y', strtotime($c['created_at'])) ?>
                                    </span>
                                </div>

                                <!-- Milestone & Progress Row -->
                                <div class="flex flex-col sm:flex-row sm:items-center gap-3 sm:gap-6">
                                    <!-- Milestone Progress -->
                                    <div class="flex-1 min-w-0">
                                        <?php if ($milestoneCount > 0): ?>
                                            <div class="flex items-center justify-between text-xs mb-1">
                                                <span class="text-gray-400 flex items-center gap-1.5 dark:text-gray-500">
                                                    <i data-lucide="list-checks" class="w-4 h-4 text-violet-400"></i>
                                                    Milestones: <span class="font-semibold text-gray-600 dark:text-gray-300"><?= $releasedCount ?>/<?= $milestoneCount ?></span>
                                                </span>
                                                <span class="font-bold <?= $progress === 100 ? 'text-emerald-600' : 'text-blue-600' ?>"><?= $progress ?>%</span>
                                            </div>
                                            <div class="w-full bg-gray-100 rounded-full h-1 overflow-hidden">
                                                <div class=" h-2 rounded-full transition-all duration-700 <?= $progress === 100 ? 'bg-gradient-to-r from-emerald-400 to-emerald-500' : 'btn-grad' ?>" style="width: <?= $progress ?>%"></div>
                                            </div>
                                        <?php else: ?>
                                            <div class="flex items-center gap-1.5 text-xs text-gray-300">
                                                <i data-lucide="list-checks" class="w-4 h-4"></i>
                                                <span>No milestones</span>
                                            </div>
                                        <?php endif; ?>
                                    </div>

                                    <!-- Payment Progress -->
                                    <div class="sm:w-40 min-w-0">
                                        <?php if ($totalBudget > 0): ?>
                                            <div class="flex items-center justify-between text-xs mb-1">
                                                <span class="text-gray-400">Payments</span>
                                                <span class="font-semibold text-gray-600"><?= $paymentPct ?>%</span>
                                            </div>
                                            <div class="w-full bg-gray-100 rounded-full h-1 overflow-hidden">
                                                <div class="h-2 rounded-full transition-all duration-700 <?= $paymentPct >= 100 ? 'bg-gradient-to-r from-emerald-400 to-emerald-500' : 'bg-gradient-to-r from-violet-400 to-purple-500' ?>" style="width: <?= $paymentPct ?>%"></div>
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>

                            <!-- ── Actions ──────────────────────── -->
                            <div class="flex flex-wrap xl:flex-nowrap items-center gap-2 xl:flex-col xl:items-stretch xl:min-w-[160px]">
                                <a href="contract_detail.php?id=<?= (int) $c['id'] ?>"
                                    class="inline-flex items-center justify-center gap-2 px-4 py-2 btn-grad text-white text-xs font-semibold rounded-xl shadow-lg shadow-blue-500/25">
                                    <i data-lucide="eye" class="w-3 h-3"></i> View Contract
                                </a>

                                <div class="flex gap-2 xl:grid xl:grid-cols-1 xl:gap-2">
                                    <!-- More Actions Dropdown -->
                                    <div class="relative" id="dropdown-<?= (int) $c['id'] ?>">
                                        <button
                                            type="button"
                                            onclick="toggleDropdown(<?= (int) $c['id'] ?>)"
                                            class="action-btn inline-flex items-center justify-center gap-2 px-4 py-2 bg-gray-50 hover:bg-gray-100 border border-gray-200 text-gray-600 text-xs font-semibold rounded-xl transition-all w-full dark:bg-gray-700 dark:hover:bg-gray-600 dark:border-gray-600 dark:text-gray-300">
                                            <i data-lucide="ellipsis-vertical" class="w-3 h-3"></i> Actions
                                        </button>

                                        <div class="contract-actions-menu absolute right-0 bottom-full mb-2 w-52 bg-white rounded-xl border border-gray-100 shadow-xl z-40 py-1.5 dark:bg-gray-800 dark:border-gray-700">
                                            <a href="contract_detail.php?id=<?= (int) $c['id'] ?>#milestones"
                                                class="flex items-center gap-2.5 px-4 py-2.5 text-xs text-gray-600 hover:bg-blue-50 hover:text-blue-600 transition-colors dark:text-gray-400 dark:hover:bg-gray-700 dark:hover:text-blue-400">
                                                <i data-lucide="list-checks" class="w-4 h-4 text-center text-violet-400"></i> View Milestones
                                            </a>

                                            <?php if ($c['status'] === 'active' && $milestoneCount > $releasedCount): ?>
                                                <a href="contract_detail.php?id=<?= (int) $c['id'] ?>#milestones"
                                                    class="flex items-center gap-2.5 px-4 py-2.5 text-xs text-gray-600 hover:bg-emerald-50 hover:text-emerald-600 transition-colors dark:text-gray-400 dark:hover:bg-gray-700 dark:hover:text-emerald-400">
                                                    <i data-lucide="wallet" class="w-4 h-4 text-center text-emerald-400"></i> Fund Milestone
                                                </a>
                                            <?php endif; ?>

                                            <?php if ($chatRoomId > 0): ?>
                                                <a href="messages.php?room=<?= $chatRoomId ?>"
                                                    class="flex items-center gap-2.5 px-4 py-2.5 text-xs text-gray-600 hover:bg-blue-50 hover:text-blue-600 transition-colors dark:text-gray-400 dark:hover:bg-gray-700 dark:hover:text-blue-400">
                                                    <i data-lucide="message-circle" class="w-4 h-4 text-center text-blue-400"></i> Message Freelancer
                                                </a>
                                            <?php endif; ?>

                                            <?php if ($c['status'] === 'active' && $milestoneCount > 0 && $releasedCount === $milestoneCount): ?>
                                                <form method="POST" action="contract_action.php" class="inline" onsubmit="return confirm('Mark this contract as complete? This cannot be undone.')">
                                                    <?= csrf_field() ?>
                                                    <input type="hidden" name="action" value="complete">
                                                    <input type="hidden" name="contract_id" value="<?= (int) $c['id'] ?>">
                                                    <button type="submit"
                                                        class="w-full flex items-center gap-2.5 px-4 py-2.5 text-xs text-gray-600 hover:bg-emerald-50 hover:text-emerald-600 transition-colors dark:text-gray-400 dark:hover:bg-gray-700 dark:hover:text-emerald-400">
                                                        <i data-lucide="check-check" class="w-4 h-4 text-center text-emerald-400"></i> Mark Complete
                                                    </button>
                                                </form>
                                            <?php endif; ?>

                                            <?php if ($c['status'] === 'active'):
                                                $freelancerName = $c['freelancer_name'] ?? 'Freelancer';
                                            ?>
                                                <hr class="my-1.5 border-gray-100">
                                                <form method="POST" action="contract_action.php" class="inline" onsubmit="return confirm('Raise a dispute on this contract? An admin will review.')">
                                                    <?= csrf_field() ?>
                                                    <input type="hidden" name="action" value="dispute">
                                                    <input type="hidden" name="contract_id" value="<?= (int) $c['id'] ?>">
                                                    <button type="submit"
                                                        class="w-full flex items-center gap-2.5 px-4 py-2.5 text-xs text-red-500 hover:bg-red-50 transition-colors">
                                                        <i data-lucide="flag" class="w-4 h-4 text-center text-red-400"></i> Raise Dispute
                                                    </button>
                                                </form>
                                            <?php endif; ?>

                                            <?php if ($c['status'] === 'active' && $c['dispute_status'] === 'open' && !empty($c['cancellation_reason'])): ?>
                                                <hr class="my-1.5 border-gray-100">
                                                <div class="px-4 py-2">
                                                    <p class="text-[10px] text-amber-600 font-semibold mb-1"><i data-lucide="triangle-alert" class="w-3 h-3 mr-1"></i>Termination Requested</p>
                                                    <p class="text-[10px] text-gray-500 mb-2"><?= sanitize_string(truncate($c['cancellation_reason'], 80)) ?></p>
                                                    <div class="flex gap-2">
                                                        <form method="POST" action="contract_action.php" class="flex-1" onsubmit="return confirm('Accept termination? This contract will be closed.')">
                                                            <?= csrf_field() ?>
                                                            <input type="hidden" name="action" value="accept_termination">
                                                            <input type="hidden" name="contract_id" value="<?= (int) $c['id'] ?>">
                                                            <button type="submit" class="w-full px-3 py-1.5 bg-emerald-500 hover:bg-emerald-600 text-white text-[10px] font-semibold rounded-lg transition-all">
                                                                Accept
                                                            </button>
                                                        </form>
                                                        <form method="POST" action="contract_action.php" class="flex-1" onsubmit="return confirm('Reject termination? The freelancer may file a dispute.')">
                                                            <?= csrf_field() ?>
                                                            <input type="hidden" name="action" value="reject_termination">
                                                            <input type="hidden" name="contract_id" value="<?= (int) $c['id'] ?>">
                                                            <button type="submit" class="w-full px-3 py-1.5 bg-red-500 hover:bg-red-600 text-white text-[10px] font-semibold rounded-lg transition-all">
                                                                Reject
                                                            </button>
                                                        </form>
                                                    </div>
                                                </div>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            <?php endwhile; ?>
        </div>

        <!-- ═══ PAGINATION ═══════════════════════════════════════ -->
        <?php if ($pagination['total_pages'] > 1): ?>
            <div class="flex items-center justify-between bg-white rounded-2xl p-4 border border-gray-100 shadow-sm fade-in dark:bg-gray-800 dark:border-gray-700">
                <p class="text-xs text-gray-400">
                    Page <span class="font-semibold text-gray-600"><?= $pagination['current_page'] ?></span> of <span class="font-semibold text-gray-600"><?= $pagination['total_pages'] ?></span>
                    <span class="text-gray-300 mx-1">|</span>
                    <span class="font-semibold text-gray-600"><?= $totalFiltered ?></span> contract<?= $totalFiltered !== 1 ? 's' : '' ?>
                </p>
                <div class="flex items-center gap-1">
                    <?php if ($pagination['has_prev']): ?>
                        <a href="?<?= $baseQuery . $baseSep ?>page=<?= $pagination['current_page'] - 1 ?>" class="w-9 h-9 flex items-center justify-center rounded-xl border border-gray-200 text-gray-500 hover:bg-gray-50 text-sm dark:border-gray-600 dark:text-gray-400 dark:hover:bg-gray-700">
                            <i data-lucide="chevron-left" class="w-4 h-4"></i>
                        </a>
                    <?php endif; ?>
                    <?php for ($i = max(1, $pagination['current_page'] - 2); $i <= min($pagination['total_pages'], $pagination['current_page'] + 2); $i++): ?>
                        <a href="?<?= $baseQuery . $baseSep ?>page=<?= $i ?>" class="w-9 h-9 flex items-center justify-center rounded-xl text-sm font-medium transition-all <?= $i === $pagination['current_page'] ? 'btn-grad text-white shadow-sm' : 'text-gray-500 hover:bg-gray-50 dark:text-gray-400 dark:hover:bg-gray-700' ?>"><?= $i ?></a>
                    <?php endfor; ?>
                    <?php if ($pagination['has_next']): ?>
                        <a href="?<?= $baseQuery . $baseSep ?>page=<?= $pagination['current_page'] + 1 ?>" class="w-9 h-9 flex items-center justify-center rounded-xl border border-gray-200 text-gray-500 hover:bg-gray-50 text-sm dark:border-gray-600 dark:text-gray-400 dark:hover:bg-gray-700">
                            <i data-lucide="chevron-right" class="w-4 h-4"></i>
                        </a>
                    <?php endif; ?>
                </div>
            </div>
        <?php endif; ?>

    <?php else: ?>
        <div class="bg-white rounded-2xl border border-gray-100 shadow-sm fade-in dark:bg-gray-800 dark:border-gray-700">
            <div class="text-center py-16 px-6">
                <div class="w-24 h-24 rounded-3xl bg-gradient-to-br from-blue-50 to-cyan-50 flex items-center justify-center mx-auto mb-6 border border-blue-100">
                    <i data-lucide="handshake" class="w-5 h-5 text-blue-300"></i>
                </div>
                <h3 class="text-xl font-bold text-gray-900 mb-2 dark:text-white">
                    <?= $searchQuery !== '' ? 'No contracts found' : 'No contracts yet' ?>
                </h3>
                <p class="text-sm text-gray-400 mb-6 max-w-md mx-auto">
                    <?php if ($searchQuery !== ''): ?>
                        No contracts match "<span class="font-semibold text-gray-600"><?= sanitize_string($searchQuery) ?></span>"<?= $statusFilter !== 'all' ? ' with status "' . ucfirst($statusFilter) . '"' : '' ?>.
                    <?php elseif ($statusFilter !== 'all'): ?>
                        No contracts match the "<?= ucfirst($statusFilter) ?>" filter.
                    <?php else: ?>
                        Accept a proposal to start your first contract.
                    <?php endif; ?>
                </p>
                <div class="flex items-center justify-center gap-3">
                    <?php if ($searchQuery !== '' || $statusFilter !== 'all'): ?>
                        <a href="contracts.php" class="inline-flex items-center gap-2 px-5 py-2.5 bg-gray-100 hover:bg-gray-200 text-gray-600 font-semibold rounded-xl text-xs transition-all">
                            <i data-lucide="x" class="w-3 h-3"></i> Clear Filters
                        </a>
                    <?php endif; ?>
                    <a href="my_jobs.php" class="btn-grad inline-flex items-center gap-2 text-white font-bold px-6 py-3 rounded-xl text-sm">
                        <i data-lucide="briefcase" class="w-4 h-4"></i> View My Jobs
                    </a>
                </div>
            </div>
        </div>
    <?php endif; ?>
</main>

<style>
    .contract-actions-menu { display: none; }
    .contract-actions-menu.show { display: block; }
</style>
<script>
    function toggleDropdown(id) {
        var wrapper = document.getElementById('dropdown-' + id);
        if (!wrapper) return;
        var menu = wrapper.querySelector('.contract-actions-menu');
        if (!menu) return;
        var isOpen = menu.classList.contains('show');

        document.querySelectorAll('.contract-actions-menu').forEach(function(m) {
            m.classList.remove('show');
        });

        if (!isOpen) {
            menu.classList.add('show');
        }
    }

    document.addEventListener('click', function(e) {
        if (!e.target.closest('[id^="dropdown-"]')) {
            document.querySelectorAll('.contract-actions-menu').forEach(function(m) {
                m.classList.remove('show');
            });
        }
    });

    document.addEventListener('keydown', function(e) {
        if (e.key === 'Escape') {
            document.querySelectorAll('.contract-actions-menu').forEach(function(m) {
                m.classList.remove('show');
            });
        }
    });
</script>
<?php require_once __DIR__ . '/../includes/client_footer.php'; ?>
