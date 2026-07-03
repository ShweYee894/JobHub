<?php
$currentPage = 'payment_history';
require_once __DIR__ . '/../auth/auth.php';
require_role('client');
require_once __DIR__ . '/../config/db.php';

$userId = $_SESSION['user_id'];

// ── Fetch Client Info ────────────────────────────────────────────────
$uStmt = $conn->prepare('SELECT name, email, profile_image, wallet_balance FROM users WHERE id = ?');
$uStmt->bind_param('i', $userId);
$uStmt->execute();
$user = $uStmt->get_result()->fetch_assoc();
$uStmt->close();

$walletBalance = (float) $user['wallet_balance'];

// ── Filters ──────────────────────────────────────────────────────────
$filterStatus   = $_GET['status']   ?? '';
$filterDateFrom = $_GET['date_from'] ?? '';
$filterDateTo   = $_GET['date_to']   ?? '';
$filterSearch   = trim($_GET['search'] ?? '');

// ── Stat: Total Spent ───────────────────────────────────────────────
$s1 = $conn->prepare("SELECT COALESCE(SUM(total_amount), 0) AS total FROM payments WHERE payer_id = ? AND status = 'completed'");
$s1->bind_param('i', $userId);
$s1->execute();
$totalSpent = (float) $s1->get_result()->fetch_assoc()['total'];
$s1->close();

// ── Stat: Escrow (pending milestones amount) ────────────────────────
$s2 = $conn->prepare("SELECT COALESCE(SUM(total_amount), 0) AS total FROM payments WHERE payer_id = ? AND status IN ('pending','processing')");
$s2->bind_param('i', $userId);
$s2->execute();
$escrowAmount = (float) $s2->get_result()->fetch_assoc()['total'];
$s2->close();

// ── Stat: Released (completed) ──────────────────────────────────────
$s3 = $conn->prepare("SELECT COALESCE(SUM(total_amount), 0) AS total FROM payments WHERE payer_id = ? AND status = 'completed'");
$s3->bind_param('i', $userId);
$s3->execute();
$releasedAmount = (float) $s3->get_result()->fetch_assoc()['total'];
$s3->close();

// ── Stat: Pending Payments ──────────────────────────────────────────
$s4 = $conn->prepare("SELECT COUNT(*) AS cnt FROM payments WHERE payer_id = ? AND status = 'pending'");
$s4->bind_param('i', $userId);
$s4->execute();
$pendingCount = (int) $s4->get_result()->fetch_assoc()['cnt'];
$s4->close();

// ── Stat: Refunds ───────────────────────────────────────────────────
$s5 = $conn->prepare("SELECT COALESCE(SUM(total_amount), 0) AS total, COUNT(*) AS cnt FROM payments WHERE payer_id = ? AND status = 'refunded'");
$s5->bind_param('i', $userId);
$s5->execute();
$refundRow = $s5->get_result()->fetch_assoc();
$refundTotal = (float) $refundRow['total'];
$refundCount = (int) $refundRow['cnt'];
$s5->close();

// ── Build Dynamic Query ──────────────────────────────────────────────
$conditions = ['p.payer_id = ?'];
$types      = 'i';
$params     = [$userId];

if ($filterStatus !== '' && in_array($filterStatus, ['pending','processing','completed','failed','refunded'])) {
    $conditions[] = 'p.status = ?';
    $types      .= 's';
    $params[]    = $filterStatus;
}
if ($filterDateFrom !== '' && preg_match('/^\d{4}-\d{2}-\d{2}$/', $filterDateFrom)) {
    $conditions[] = 'p.created_at >= ?';
    $types      .= 's';
    $params[]    = $filterDateFrom . ' 00:00:00';
}
if ($filterDateTo !== '' && preg_match('/^\d{4}-\d{2}-\d{2}$/', $filterDateTo)) {
    $conditions[] = 'p.created_at <= ?';
    $types      .= 's';
    $params[]    = $filterDateTo . ' 23:59:59';
}
if ($filterSearch !== '') {
    $conditions[] = 'u.name LIKE ?';
    $types      .= 's';
    $params[]    = '%' . $filterSearch . '%';
}

$whereClause = implode(' AND ', $conditions);

// ── Pagination ───────────────────────────────────────────────────────
$page    = max(1, intval($_GET['page'] ?? 1));
$perPage = 10;

$countSql = "SELECT COUNT(*) AS total FROM payments p JOIN users u ON p.payee_id = u.id WHERE {$whereClause}";
$countStmt = $conn->prepare($countSql);
$countStmt->bind_param($types, ...$params);
$countStmt->execute();
$totalPayments = (int) $countStmt->get_result()->fetch_assoc()['total'];
$countStmt->close();

$pagination = paginate($totalPayments, $perPage, $page);
$offset = $pagination['offset'];

// ── Fetch Payments ───────────────────────────────────────────────────
$dataTypes  = $types . 'ii';
$dataParams = array_merge($params, [$perPage, $offset]);

$sql = "
    SELECT p.*, m.title AS milestone_title, u.name AS payee_name, u.email AS payee_email
    FROM payments p
    JOIN milestones m ON p.milestone_id = m.id
    JOIN users u ON p.payee_id = u.id
    WHERE {$whereClause}
    ORDER BY p.created_at DESC
    LIMIT ? OFFSET ?
";
$stmt = $conn->prepare($sql);
$stmt->bind_param($dataTypes, ...$dataParams);
$stmt->execute();
$paymentsResult = $stmt->get_result();
$stmt->close();

// ── Timeline: Recent 5 ──────────────────────────────────────────────
$tSql = "
    SELECT p.*, m.title AS milestone_title, u.name AS payee_name
    FROM payments p
    JOIN milestones m ON p.milestone_id = m.id
    JOIN users u ON p.payee_id = u.id
    WHERE p.payer_id = ?
    ORDER BY p.created_at DESC
    LIMIT 5
";
$tStmt = $conn->prepare($tSql);
$tStmt->bind_param('i', $userId);
$tStmt->execute();
$timelineResult = $tStmt->get_result();
$tStmt->close();

// ── All payments for invoice (JSON for JS) ──────────────────────────
$allSql = "
    SELECT p.id, p.total_amount, p.platform_fee, p.status, p.created_at,
           m.title AS milestone_title, u.name AS payee_name
    FROM payments p
    JOIN milestones m ON p.milestone_id = m.id
    JOIN users u ON p.payee_id = u.id
    WHERE p.payer_id = ?
    ORDER BY p.created_at DESC
";
$allStmt = $conn->prepare($allSql);
$allStmt->bind_param('i', $userId);
$allStmt->execute();
$allPaymentsResult = $allStmt->get_result();
$allPaymentsData = [];
while ($row = $allPaymentsResult->fetch_assoc()) {
    $allPaymentsData[] = $row;
}
$allStmt->close();

// ── Status Badge Colors ─────────────────────────────────────────────
$paymentColors = [
    'completed'  => 'bg-emerald-50 text-emerald-600 border border-emerald-200',
    'pending'    => 'bg-amber-50 text-amber-600 border border-amber-200',
    'processing' => 'bg-blue-50 text-blue-600 border border-blue-200',
    'failed'     => 'bg-red-50 text-red-500 border border-red-200',
    'refunded'   => 'bg-gray-100 text-gray-500 border border-gray-200',
];

$paymentIcons = [
    'completed'  => 'fa-check-circle',
    'pending'    => 'fa-clock',
    'processing' => 'fa-spinner fa-spin',
    'failed'     => 'fa-times-circle',
    'refunded'   => 'fa-undo',
];

$timelineColors = [
    'completed'  => 'bg-emerald-500',
    'pending'    => 'bg-amber-500',
    'processing' => 'bg-blue-500',
    'failed'     => 'bg-red-500',
    'refunded'   => 'bg-gray-400',
];

$conn->close();

$navItems = [
    ['key' => 'dashboard', 'label' => 'Dashboard', 'url' => 'dashboard.php', 'icon' => 'fa-th-large'],
    ['key' => 'my_jobs', 'label' => 'My Jobs', 'url' => 'my_jobs.php', 'icon' => 'fa-briefcase'],
    ['key' => 'post_job', 'label' => 'Post a Job', 'url' => 'post_job.php', 'icon' => 'fa-plus-circle'],
    ['key' => 'proposals', 'label' => 'Proposals', 'url' => 'proposals.php', 'icon' => 'fa-file-alt'],
    ['key' => 'contracts', 'label' => 'Contracts', 'url' => 'contracts.php', 'icon' => 'fa-handshake'],
    ['key' => 'payment_history', 'label' => 'Payments', 'url' => 'payment_history.php', 'icon' => 'fa-credit-card'],
    ['key' => 'messages', 'label' => 'Messages', 'url' => 'messages.php', 'icon' => 'fa-comment-dots'],
];
$pageTitle = 'Payments';
$pageSubtitle = 'Track your spending, invoices, and transaction history';
$activePage = 'payment_history';
$user = ['name' => $user['name'] ?? 'Client', 'profile_image' => $user['profile_image'] ?? null];
$unreadCount = 0;
$profileLink = 'profile.php';
require_once __DIR__ . '/../components/layout_start.php';
?>
                <?php display_flash('success') ?>
                <?php display_flash('error') ?>

                <!-- ═══ STAT CARDS ═══════════════════════════════════════ -->
                <div class="grid grid-cols-1 sm:grid-cols-2 xl:grid-cols-3 2xl:grid-cols-6 gap-4">

                    <!-- Wallet Balance -->
                    <div class="stat-card bg-white rounded-2xl p-5 border border-gray-100 shadow-sm fade-in">
                        <div class="flex items-center justify-between mb-3">
                            <div class="w-11 h-11 rounded-xl bg-violet-50 flex items-center justify-center">
                                <i class="fas fa-wallet text-violet-500"></i>
                            </div>
                            <span class="text-[10px] font-semibold text-violet-500 uppercase tracking-wider">Balance</span>
                        </div>
                        <p class="text-2xl font-black text-gray-900"><?= format_currency($walletBalance) ?></p>
                        <p class="text-xs text-gray-400 mt-1">Wallet Balance</p>
                    </div>

                    <!-- Total Spent -->
                    <div class="stat-card bg-white rounded-2xl p-5 border border-gray-100 shadow-sm fade-in" style="animation-delay:.05s">
                        <div class="flex items-center justify-between mb-3">
                            <div class="w-11 h-11 rounded-xl bg-red-50 flex items-center justify-center">
                                <i class="fas fa-arrow-up text-red-500"></i>
                            </div>
                            <span class="text-[10px] font-semibold text-red-500 uppercase tracking-wider">Spent</span>
                        </div>
                        <p class="text-2xl font-black text-gray-900"><?= format_currency($totalSpent) ?></p>
                        <p class="text-xs text-gray-400 mt-1">Total Spent</p>
                    </div>

                    <!-- Escrow -->
                    <div class="stat-card bg-white rounded-2xl p-5 border border-gray-100 shadow-sm fade-in" style="animation-delay:.1s">
                        <div class="flex items-center justify-between mb-3">
                            <div class="w-11 h-11 rounded-xl bg-amber-50 flex items-center justify-center">
                                <i class="fas fa-lock text-amber-500"></i>
                            </div>
                            <span class="text-[10px] font-semibold text-amber-500 uppercase tracking-wider">Escrow</span>
                        </div>
                        <p class="text-2xl font-black text-gray-900"><?= format_currency($escrowAmount) ?></p>
                        <p class="text-xs text-gray-400 mt-1">In Escrow</p>
                    </div>

                    <!-- Released -->
                    <div class="stat-card bg-white rounded-2xl p-5 border border-gray-100 shadow-sm fade-in" style="animation-delay:.15s">
                        <div class="flex items-center justify-between mb-3">
                            <div class="w-11 h-11 rounded-xl bg-emerald-50 flex items-center justify-center">
                                <i class="fas fa-check-double text-emerald-500"></i>
                            </div>
                            <span class="text-[10px] font-semibold text-emerald-500 uppercase tracking-wider">Released</span>
                        </div>
                        <p class="text-2xl font-black text-gray-900"><?= format_currency($releasedAmount) ?></p>
                        <p class="text-xs text-gray-400 mt-1">Released Payments</p>
                    </div>

                    <!-- Pending Count -->
                    <div class="stat-card bg-white rounded-2xl p-5 border border-gray-100 shadow-sm fade-in" style="animation-delay:.2s">
                        <div class="flex items-center justify-between mb-3">
                            <div class="w-11 h-11 rounded-xl bg-blue-50 flex items-center justify-center">
                                <i class="fas fa-hourglass-half text-blue-500"></i>
                            </div>
                            <span class="text-[10px] font-semibold text-blue-500 uppercase tracking-wider">Pending</span>
                        </div>
                        <p class="text-2xl font-black text-gray-900"><?= $pendingCount ?></p>
                        <p class="text-xs text-gray-400 mt-1">Pending Payments</p>
                    </div>

                    <!-- Refunds -->
                    <div class="stat-card bg-white rounded-2xl p-5 border border-gray-100 shadow-sm fade-in" style="animation-delay:.25s">
                        <div class="flex items-center justify-between mb-3">
                            <div class="w-11 h-11 rounded-xl bg-gray-50 flex items-center justify-center">
                                <i class="fas fa-undo text-gray-400"></i>
                            </div>
                            <span class="text-[10px] font-semibold text-gray-400 uppercase tracking-wider">Refunds</span>
                        </div>
                        <p class="text-2xl font-black text-gray-900"><?= $refundCount ?></p>
                        <p class="text-xs text-gray-400 mt-1"><?= format_currency($refundTotal) ?> refunded</p>
                    </div>
                </div>

                <!-- ═══ TWO-COLUMN: TIMELINE + FILTERS ══════════════════ -->
                <div class="grid grid-cols-1 2xl:grid-cols-3 gap-6">

                    <!-- ── Payment Timeline ───────────────────────────── -->
                    <div class="2xl:col-span-1 bg-white rounded-2xl border border-gray-100 shadow-sm fade-in" style="animation-delay:.3s">
                        <div class="p-6 pb-4">
                            <div class="flex items-center gap-3 mb-5">
                                <div class="w-10 h-10 rounded-xl bg-blue-50 flex items-center justify-center">
                                    <i class="fas fa-stream text-blue-500"></i>
                                </div>
                                <div>
                                    <h2 class="text-base font-bold text-gray-900">Recent Activity</h2>
                                    <p class="text-xs text-gray-400">Last 5 transactions</p>
                                </div>
                            </div>

                            <?php if ($timelineResult->num_rows > 0): ?>
                                <div class="space-y-0">
                                    <?php while ($t = $timelineResult->fetch_assoc()): ?>
                                        <div class="timeline-item timeline-line flex gap-4 pb-5 relative">
                                            <div class="w-8 h-8 rounded-full flex items-center justify-center flex-shrink-0 z-10 <?= $timelineColors[$t['status']] ?? 'bg-gray-400' ?>">
                                                <i class="fas <?= $paymentIcons[$t['status']] ?? 'fa-circle' ?> text-white text-[10px]"></i>
                                            </div>
                                            <div class="flex-1 min-w-0 pt-0.5">
                                                <div class="flex items-center justify-between gap-2">
                                                    <p class="text-sm font-semibold text-gray-900 truncate"><?= sanitize_string($t['milestone_title']) ?></p>
                                                    <span class="text-xs font-bold text-gray-700 flex-shrink-0"><?= format_currency((float) $t['total_amount']) ?></span>
                                                </div>
                                                <div class="flex items-center justify-between mt-1">
                                                    <p class="text-[11px] text-gray-400">
                                                        <i class="fas fa-user mr-1"></i><?= sanitize_string($t['payee_name']) ?>
                                                    </p>
                                                    <span class="inline-block px-2 py-0.5 rounded text-[10px] font-semibold <?= $paymentColors[$t['status']] ?? 'bg-gray-100 text-gray-500 border border-gray-200' ?>">
                                                        <?= sanitize_string(ucfirst($t['status'])) ?>
                                                    </span>
                                                </div>
                                                <p class="text-[10px] text-gray-300 mt-1"><?= time_ago($t['created_at']) ?></p>
                                            </div>
                                        </div>
                                    <?php endwhile; ?>
                                </div>
                            <?php else: ?>
                                <div class="text-center py-8">
                                    <div class="w-12 h-12 rounded-2xl bg-gray-100 flex items-center justify-center mx-auto mb-3">
                                        <i class="fas fa-stream text-lg text-gray-300"></i>
                                    </div>
                                    <p class="text-gray-400 text-xs">No transactions yet</p>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>

                    <!-- ── Filters Panel ──────────────────────────────── -->
                    <div class="2xl:col-span-2 bg-white rounded-2xl border border-gray-100 shadow-sm fade-in" style="animation-delay:.35s">
                        <div class="p-6">
                            <div class="flex items-center gap-3 mb-5">
                                <div class="w-10 h-10 rounded-xl bg-cyan-50 flex items-center justify-center">
                                    <i class="fas fa-filter text-cyan-500"></i>
                                </div>
                                <div>
                                    <h2 class="text-base font-bold text-gray-900">All Transactions</h2>
                                    <p class="text-xs text-gray-400"><?= $totalPayments ?> transaction<?= $totalPayments !== 1 ? 's' : '' ?> found</p>
                                </div>
                            </div>

                            <!-- Filter Form -->
                            <form method="GET" class="mb-6">
                                <div class="grid grid-cols-1 sm:grid-cols-2 xl:grid-cols-4 gap-4">
                                    <!-- Search -->
                                    <div class="xl:col-span-2">
                                        <label class="block text-[11px] font-semibold text-gray-400 uppercase tracking-wider mb-1.5">Search Freelancer</label>
                                        <div class="relative">
                                            <i class="fas fa-search absolute left-3 top-1/2 -translate-y-1/2 text-gray-300 text-xs"></i>
                                            <input type="text" name="search" value="<?= sanitize_string($filterSearch) ?>"
                                                   placeholder="Search by freelancer name..."
                                                   class="w-full pl-9 pr-4 py-2.5 text-sm border border-gray-200 rounded-xl focus:outline-none focus:ring-2 focus:ring-blue-500/20 focus:border-blue-400 transition-all bg-gray-50 hover:bg-white">
                                        </div>
                                    </div>

                                    <!-- Status -->
                                    <div>
                                        <label class="block text-[11px] font-semibold text-gray-400 uppercase tracking-wider mb-1.5">Status</label>
                                        <select name="status" class="w-full px-4 py-2.5 text-sm border border-gray-200 rounded-xl focus:outline-none focus:ring-2 focus:ring-blue-500/20 focus:border-blue-400 transition-all bg-gray-50 hover:bg-white appearance-none">
                                            <option value="">All Status</option>
                                            <option value="pending" <?= $filterStatus === 'pending' ? 'selected' : '' ?>>Pending</option>
                                            <option value="processing" <?= $filterStatus === 'processing' ? 'selected' : '' ?>>Processing</option>
                                            <option value="completed" <?= $filterStatus === 'completed' ? 'selected' : '' ?>>Completed</option>
                                            <option value="failed" <?= $filterStatus === 'failed' ? 'selected' : '' ?>>Failed</option>
                                            <option value="refunded" <?= $filterStatus === 'refunded' ? 'selected' : '' ?>>Refunded</option>
                                        </select>
                                    </div>

                                    <!-- Date From -->
                                    <div>
                                        <label class="block text-[11px] font-semibold text-gray-400 uppercase tracking-wider mb-1.5">From Date</label>
                                        <input type="date" name="date_from" value="<?= sanitize_string($filterDateFrom) ?>"
                                               class="w-full px-4 py-2.5 text-sm border border-gray-200 rounded-xl focus:outline-none focus:ring-2 focus:ring-blue-500/20 focus:border-blue-400 transition-all bg-gray-50 hover:bg-white">
                                    </div>
                                </div>

                                <div class="grid grid-cols-1 sm:grid-cols-2 xl:grid-cols-4 gap-4 mt-4">
                                    <!-- Date To -->
                                    <div>
                                        <label class="block text-[11px] font-semibold text-gray-400 uppercase tracking-wider mb-1.5">To Date</label>
                                        <input type="date" name="date_to" value="<?= sanitize_string($filterDateTo) ?>"
                                               class="w-full px-4 py-2.5 text-sm border border-gray-200 rounded-xl focus:outline-none focus:ring-2 focus:ring-blue-500/20 focus:border-blue-400 transition-all bg-gray-50 hover:bg-white">
                                    </div>

                                    <div class="flex items-end gap-3 xl:col-span-3">
                                        <button type="submit" class="btn-grad inline-flex items-center gap-2 text-white text-xs font-semibold px-5 py-2.5 rounded-xl">
                                            <i class="fas fa-search text-[10px]"></i> Apply Filters
                                        </button>
                                        <?php if ($filterStatus !== '' || $filterDateFrom !== '' || $filterDateTo !== '' || $filterSearch !== ''): ?>
                                            <a href="payments.php" class="inline-flex items-center gap-2 text-xs font-semibold text-gray-500 hover:text-red-500 px-4 py-2.5 rounded-xl border border-gray-200 hover:border-red-200 hover:bg-red-50 transition-all">
                                                <i class="fas fa-times text-[10px]"></i> Clear
                                            </a>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </form>

                            <!-- ═══ PAYMENTS TABLE ════════════════════════════ -->
                            <?php if ($paymentsResult->num_rows > 0): ?>
                                <div class="overflow-x-auto">
                                    <table class="w-full text-sm">
                                        <thead>
                                            <tr class="border-b border-gray-100">
                                                <th class="text-left py-3 px-3 text-xs font-semibold text-gray-400 uppercase tracking-wider">Payment ID</th>
                                                <th class="text-left py-3 px-3 text-xs font-semibold text-gray-400 uppercase tracking-wider">Milestone</th>
                                                <th class="text-left py-3 px-3 text-xs font-semibold text-gray-400 uppercase tracking-wider">Freelancer</th>
                                                <th class="text-right py-3 px-3 text-xs font-semibold text-gray-400 uppercase tracking-wider">Amount</th>
                                                <th class="text-right py-3 px-3 text-xs font-semibold text-gray-400 uppercase tracking-wider">Fee</th>
                                                <th class="text-right py-3 px-3 text-xs font-semibold text-gray-400 uppercase tracking-wider">Net</th>
                                                <th class="text-center py-3 px-3 text-xs font-semibold text-gray-400 uppercase tracking-wider">Status</th>
                                                <th class="text-right py-3 px-3 text-xs font-semibold text-gray-400 uppercase tracking-wider">Date</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php while ($pay = $paymentsResult->fetch_assoc()): ?>
                                                <tr class="border-b border-gray-50 hover:bg-gray-50 transition-colors">
                                                    <td class="py-3 px-3">
                                                        <span class="text-xs font-mono text-gray-500">#<?= (int) $pay['id'] ?></span>
                                                    </td>
                                                    <td class="py-3 px-3">
                                                        <span class="font-medium text-gray-900 line-clamp-1 max-w-[160px]"><?= sanitize_string($pay['milestone_title']) ?></span>
                                                    </td>
                                                    <td class="py-3 px-3">
                                                        <div class="flex items-center gap-2">
                                                            <div class="w-7 h-7 rounded-full bg-gradient-to-br from-blue-500 to-cyan-500 flex items-center justify-center flex-shrink-0">
                                                                <span class="text-white text-[10px] font-bold"><?= strtoupper(substr($pay['payee_name'], 0, 1)) ?></span>
                                                            </div>
                                                            <span class="text-gray-600 text-xs"><?= sanitize_string($pay['payee_name']) ?></span>
                                                        </div>
                                                    </td>
                                                    <td class="py-3 px-3 text-right font-bold text-gray-700"><?= format_currency((float) $pay['total_amount']) ?></td>
                                                    <td class="py-3 px-3 text-right text-gray-400 text-xs"><?= format_currency((float) $pay['platform_fee']) ?></td>
                                                    <td class="py-3 px-3 text-right font-semibold text-gray-600 text-xs"><?= format_currency((float) $pay['freelancer_net']) ?></td>
                                                    <td class="py-3 px-3 text-center">
                                                        <span class="inline-block px-2.5 py-1 rounded-lg text-[11px] font-semibold <?= $paymentColors[$pay['status']] ?? 'bg-gray-100 text-gray-500 border border-gray-200' ?>">
                                                            <?= sanitize_string(ucfirst($pay['status'])) ?>
                                                        </span>
                                                    </td>
                                                    <td class="py-3 px-3 text-right text-gray-400 text-xs">
                                                        <span title="<?= date('M d, Y h:i A', strtotime($pay['created_at'])) ?>"><?= date('M d, Y', strtotime($pay['created_at'])) ?></span>
                                                    </td>
                                                </tr>
                                            <?php endwhile; ?>
                                        </tbody>
                                    </table>
                                </div>

                                <!-- Pagination -->
                                <?php if ($pagination['total_pages'] > 1): ?>
                                    <div class="flex items-center justify-between mt-5 pt-5 border-t border-gray-100">
                                        <p class="text-xs text-gray-400">
                                            Page <span class="font-semibold text-gray-600"><?= $pagination['current_page'] ?></span>
                                            of <span class="font-semibold text-gray-600"><?= $pagination['total_pages'] ?></span>
                                            <span class="text-gray-300 mx-1">|</span>
                                            <span class="font-semibold text-gray-600"><?= $totalPayments ?></span> total
                                        </p>
                                        <div class="flex items-center gap-1">
                                            <?php
                                            $queryParams = $_GET;
                                            unset($queryParams['page']);
                                            $qs = http_build_query($queryParams);
                    $base = $qs ? "?{$qs}&" : '?';
                                            ?>
                                            <?php if ($pagination['has_prev']): ?>
                                                <a href="<?= $base ?>page=<?= $pagination['current_page'] - 1 ?>"
                                                   class="w-9 h-9 flex items-center justify-center rounded-xl border border-gray-200 text-gray-500 hover:bg-gray-50 text-sm transition-all">
                                                    <i class="fas fa-chevron-left text-xs"></i>
                                                </a>
                                            <?php endif; ?>
                                            <?php for ($i = max(1, $pagination['current_page'] - 2); $i <= min($pagination['total_pages'], $pagination['current_page'] + 2); $i++): ?>
                                                <a href="<?= $base ?>page=<?= $i ?>"
                                                   class="w-9 h-9 flex items-center justify-center rounded-xl text-sm font-medium transition-all <?= $i === $pagination['current_page'] ? 'btn-grad text-white shadow-sm' : 'text-gray-500 hover:bg-gray-50' ?>">
                                                    <?= $i ?>
                                                </a>
                                            <?php endfor; ?>
                                            <?php if ($pagination['has_next']): ?>
                                                <a href="<?= $base ?>page=<?= $pagination['current_page'] + 1 ?>"
                                                   class="w-9 h-9 flex items-center justify-center rounded-xl border border-gray-200 text-gray-500 hover:bg-gray-50 text-sm transition-all">
                                                    <i class="fas fa-chevron-right text-xs"></i>
                                                </a>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                <?php endif; ?>
                            <?php else: ?>
                                <div class="text-center py-12">
                                    <div class="w-16 h-16 rounded-2xl bg-gray-100 flex items-center justify-center mx-auto mb-4">
                                        <i class="fas fa-receipt text-2xl text-gray-300"></i>
                                    </div>
                                    <p class="text-gray-500 text-sm font-medium">No transactions found</p>
                                    <p class="text-gray-400 text-xs mt-1">
                                        <?php if ($filterStatus !== '' || $filterDateFrom !== '' || $filterDateTo !== '' || $filterSearch !== ''): ?>
                                            Try adjusting your filters or <a href="payments.php" class="text-blue-500 hover:underline">clear all filters</a>
                                        <?php else: ?>
                                            Fund milestones to see your payment history here
                                        <?php endif; ?>
                                    </p>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>

    <!-- ═══ INVOICE MODAL ════════════════════════════════════════════ -->
    <div id="invoiceModal" class="fixed inset-0 bg-black/50 z-50 hidden items-center justify-center p-4">
        <div class="bg-white rounded-2xl shadow-2xl w-full max-w-3xl max-h-[90vh] overflow-hidden">
            <div class="flex items-center justify-between p-6 border-b border-gray-100">
                <div class="flex items-center gap-3">
                    <div class="w-10 h-10 rounded-xl bg-blue-50 flex items-center justify-center">
                        <i class="fas fa-file-invoice text-blue-500"></i>
                    </div>
                    <div>
                        <h3 class="text-base font-bold text-gray-900">Payment Invoice</h3>
                        <p class="text-xs text-gray-400">Complete transaction summary</p>
                    </div>
                </div>
                <button onclick="closeInvoiceModal()" class="w-9 h-9 rounded-xl bg-gray-100 flex items-center justify-center text-gray-400 hover:text-gray-600 hover:bg-gray-200 transition-all">
                    <i class="fas fa-times text-sm"></i>
                </button>
            </div>
            <div class="p-6 overflow-y-auto max-h-[calc(90vh-140px)]" id="invoiceContent">
                <div class="text-center py-8">
                    <div class="w-12 h-12 rounded-2xl bg-gray-100 flex items-center justify-center mx-auto mb-3">
                        <i class="fas fa-file-invoice text-lg text-gray-300"></i>
                    </div>
                    <p class="text-gray-400 text-xs">Loading invoice data...</p>
                </div>
            </div>
            <div class="p-6 border-t border-gray-100 flex items-center justify-end gap-3">
                <button onclick="printInvoice()" class="inline-flex items-center gap-2 text-xs font-semibold text-gray-600 px-4 py-2.5 rounded-xl border border-gray-200 hover:bg-gray-50 transition-all">
                    <i class="fas fa-print"></i> Print
                </button>
                <button onclick="downloadInvoiceHTML()" class="btn-grad inline-flex items-center gap-2 text-white text-xs font-semibold px-5 py-2.5 rounded-xl">
                    <i class="fas fa-download text-[10px]"></i> Download HTML
                </button>
            </div>
        </div>
    </div>

    <script>
        function openInvoiceModal() {
            var modal = document.getElementById('invoiceModal');
            modal.classList.remove('hidden');
            modal.classList.add('flex');
            buildInvoice();
        }

        function closeInvoiceModal() {
            var modal = document.getElementById('invoiceModal');
            modal.classList.add('hidden');
            modal.classList.remove('flex');
        }

        function buildInvoice() {
            var rows = <?= json_encode($allPaymentsData ?? []) ?>;
            var clientName = <?= json_encode(sanitize_string($user['name'])) ?>;
            var clientEmail = <?= json_encode(sanitize_string($user['email'])) ?>;
            var walletBal = <?= json_encode(format_currency($walletBalance)) ?>;
            var totalSp = <?= json_encode(format_currency($totalSpent)) ?>;
            var escrowAmt = <?= json_encode(format_currency($escrowAmount)) ?>;
            var releasedAmt = <?= json_encode(format_currency($releasedAmount)) ?>;
            var now = new Date();
            var dateStr = now.toLocaleDateString('en-US', { year:'numeric', month:'long', day:'numeric' });
            var invoiceNo = 'INV-' + now.getFullYear() + '-' + String(now.getMonth()+1).padStart(2,'0') + '-' + String(now.getDate()).padStart(2,'0');

            var tableRows = '';
            var total = 0;
            if (rows.length > 0) {
                for (var i = 0; i < rows.length; i++) {
                    var r = rows[i];
                    var amt = parseFloat(r.total_amount);
                    total += amt;
                    var statusColors = {
                        'completed':'color:#059669;background:#ecfdf5;padding:2px 8px;border-radius:6px;font-size:11px;font-weight:600;',
                        'pending':'color:#d97706;background:#fffbeb;padding:2px 8px;border-radius:6px;font-size:11px;font-weight:600;',
                        'processing':'color:#2563eb;background:#eff6ff;padding:2px 8px;border-radius:6px;font-size:11px;font-weight:600;',
                        'failed':'color:#dc2626;background:#fef2f2;padding:2px 8px;border-radius:6px;font-size:11px;font-weight:600;',
                        'refunded':'color:#6b7280;background:#f9fafb;padding:2px 8px;border-radius:6px;font-size:11px;font-weight:600;'
                    };
                    tableRows += '<tr style="border-bottom:1px solid #f1f5f9;">'
                        + '<td style="padding:10px 12px;font-size:13px;color:#64748b;font-family:monospace;">#' + r.id + '</td>'
                        + '<td style="padding:10px 12px;font-size:13px;color:#1e293b;font-weight:500;">' + r.milestone_title + '</td>'
                        + '<td style="padding:10px 12px;font-size:13px;color:#64748b;">' + r.payee_name + '</td>'
                        + '<td style="padding:10px 12px;font-size:13px;color:#1e293b;font-weight:700;text-align:right;">$' + amt.toFixed(2) + '</td>'
                        + '<td style="padding:10px 12px;font-size:13px;color:#94a3b8;text-align:right;">$' + parseFloat(r.platform_fee).toFixed(2) + '</td>'
                        + '<td style="padding:10px 12px;text-align:center;"><span style="' + (statusColors[r.status]||'') + '">' + r.status.charAt(0).toUpperCase() + r.status.slice(1) + '</span></td>'
                        + '<td style="padding:10px 12px;font-size:12px;color:#94a3b8;text-align:right;">' + new Date(r.created_at).toLocaleDateString('en-US',{month:'short',day:'numeric',year:'numeric'}) + '</td>'
                        + '</tr>';
                }
            }

            var html = '<div id="printableInvoice" style="font-family:Inter,sans-serif;">'
                + '<div style="display:flex;justify-content:space-between;align-items:flex-start;margin-bottom:30px;">'
                + '<div><div style="font-size:22px;font-weight:800;color:#1e293b;">Freelance<span style="color:#2563eb;">Hub</span></div>'
                + '<div style="font-size:12px;color:#94a3b8;margin-top:4px;">Payment Invoice</div></div>'
                + '<div style="text-align:right;"><div style="font-size:13px;font-weight:700;color:#1e293b;">' + invoiceNo + '</div>'
                + '<div style="font-size:12px;color:#94a3b8;margin-top:2px;">' + dateStr + '</div></div></div>'
                + '<div style="display:grid;grid-template-columns:1fr 1fr;gap:20px;margin-bottom:30px;">'
                + '<div style="background:#f8fafc;padding:16px;border-radius:12px;border:1px solid #e2e8f0;">'
                + '<div style="font-size:10px;font-weight:700;color:#94a3b8;text-transform:uppercase;letter-spacing:1px;margin-bottom:8px;">Client</div>'
                + '<div style="font-size:14px;font-weight:700;color:#1e293b;">' + clientName + '</div>'
                + '<div style="font-size:12px;color:#64748b;margin-top:2px;">' + clientEmail + '</div></div>'
                + '<div style="background:#f8fafc;padding:16px;border-radius:12px;border:1px solid #e2e8f0;">'
                + '<div style="font-size:10px;font-weight:700;color:#94a3b8;text-transform:uppercase;letter-spacing:1px;margin-bottom:8px;">Summary</div>'
                + '<div style="display:grid;grid-template-columns:1fr 1fr;gap:8px;">'
                + '<div><div style="font-size:10px;color:#94a3b8;">Wallet</div><div style="font-size:13px;font-weight:700;color:#1e293b;">' + walletBal + '</div></div>'
                + '<div><div style="font-size:10px;color:#94a3b8;">Total Spent</div><div style="font-size:13px;font-weight:700;color:#dc2626;">' + totalSp + '</div></div>'
                + '<div><div style="font-size:10px;color:#94a3b8;">In Escrow</div><div style="font-size:13px;font-weight:700;color:#d97706;">' + escrowAmt + '</div></div>'
                + '<div><div style="font-size:10px;color:#94a3b8;">Released</div><div style="font-size:13px;font-weight:700;color:#059669;">' + releasedAmt + '</div></div>'
                + '</div></div></div>'
                + '<table style="width:100%;border-collapse:collapse;margin-bottom:20px;">'
                + '<thead><tr style="border-bottom:2px solid #e2e8f0;">'
                + '<th style="padding:10px 12px;text-align:left;font-size:10px;font-weight:700;color:#94a3b8;text-transform:uppercase;letter-spacing:1px;">ID</th>'
                + '<th style="padding:10px 12px;text-align:left;font-size:10px;font-weight:700;color:#94a3b8;text-transform:uppercase;letter-spacing:1px;">Milestone</th>'
                + '<th style="padding:10px 12px;text-align:left;font-size:10px;font-weight:700;color:#94a3b8;text-transform:uppercase;letter-spacing:1px;">Freelancer</th>'
                + '<th style="padding:10px 12px;text-align:right;font-size:10px;font-weight:700;color:#94a3b8;text-transform:uppercase;letter-spacing:1px;">Amount</th>'
                + '<th style="padding:10px 12px;text-align:right;font-size:10px;font-weight:700;color:#94a3b8;text-transform:uppercase;letter-spacing:1px;">Fee</th>'
                + '<th style="padding:10px 12px;text-align:center;font-size:10px;font-weight:700;color:#94a3b8;text-transform:uppercase;letter-spacing:1px;">Status</th>'
                + '<th style="padding:10px 12px;text-align:right;font-size:10px;font-weight:700;color:#94a3b8;text-transform:uppercase;letter-spacing:1px;">Date</th>'
                + '</tr></thead><tbody>' + (tableRows || '<tr><td colspan="7" style="padding:20px;text-align:center;color:#94a3b8;font-size:13px;">No transactions recorded</td></tr>') + '</tbody></table>'
                + '<div style="border-top:2px solid #e2e8f0;padding-top:16px;text-align:right;">'
                + '<div style="font-size:12px;color:#94a3b8;">Total Transactions</div>'
                + '<div style="font-size:20px;font-weight:800;color:#1e293b;">$' + total.toFixed(2) + '</div></div>'
                + '<div style="margin-top:30px;padding:16px;background:#f8fafc;border-radius:12px;border:1px solid #e2e8f0;text-align:center;">'
                + '<div style="font-size:11px;color:#94a3b8;">This invoice was generated from FreelanceHub on ' + dateStr + '</div>'
                + '<div style="font-size:10px;color:#cbd5e1;margin-top:4px;">FreelanceHub - Trusted Freelance Marketplace</div></div></div>';

            document.getElementById('invoiceContent').innerHTML = html;
        }

        function printInvoice() {
            var content = document.getElementById('printableInvoice');
            if (!content) return;
            var win = window.open('', '_blank');
            win.document.write('<!DOCTYPE html><html><head><title>Invoice</title>'
                + '<link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800;900&display=swap" rel="stylesheet" />'
                + '<style>*{margin:0;padding:0;box-sizing:border-box;}body{font-family:Inter,sans-serif;padding:40px;background:#fff;color:#1e293b;}@media print{body{padding:20px;}}</style>'
                + '</head><body>' + content.innerHTML + '<script>window.onload=function(){window.print();window.close();}<\/script></body></html>');
            win.document.close();
        }

        function downloadInvoiceHTML() {
            var content = document.getElementById('printableInvoice');
            if (!content) return;
            var html = '<!DOCTYPE html><html><head><title>FreelanceHub Invoice</title>'
                + '<link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800;900&display=swap" rel="stylesheet" />'
                + '<style>body{font-family:Inter,sans-serif;padding:40px;max-width:900px;margin:0 auto;background:#fff;color:#1e293b;}</style>'
                + '</head><body>' + content.innerHTML + '</body></html>';
            var blob = new Blob([html], { type: 'text/html' });
            var url = URL.createObjectURL(blob);
            var a = document.createElement('a');
            a.href = url;
            a.download = 'FreelanceHub-Invoice-' + new Date().toISOString().slice(0,10) + '.html';
            document.body.appendChild(a);
            a.click();
            document.body.removeChild(a);
            URL.revokeObjectURL(url);
        }

        // Close modal on backdrop click
        document.getElementById('invoiceModal').addEventListener('click', function(e) {
            if (e.target === this) closeInvoiceModal();
        });

        // Close modal on Escape
        document.addEventListener('keydown', function(e) {
            if (e.key === 'Escape') closeInvoiceModal();
        });
    </script>
<?php require_once __DIR__ . '/../components/layout_end.php'; ?>
