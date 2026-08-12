<?php
$currentPage = 'payment_history';
require_once __DIR__ . '/../auth/auth.php';
require_role('client');
require_once __DIR__ . '/../config/db.php';
$userId = $_SESSION['user_id'];

if (isset($_GET['export']) && $_GET['export'] === 'csv') {
    $exportStmt = $conn->prepare('
        SELECT p.total_amount, p.platform_fee, p.status, p.created_at, m.title AS milestone_title, u.name AS payee_name
        FROM payments p
        JOIN milestones m ON p.milestone_id = m.id
        JOIN users u ON p.payee_id = u.id
        WHERE p.payer_id = ?
        ORDER BY p.created_at DESC
    ');
    $exportStmt->bind_param('i', $userId);
    $exportStmt->execute();
    $exportResult = $exportStmt->get_result();
    $exportStmt->close();

    $statusLabels = [
        'completed'  => 'Completed',
        'pending'    => 'Pending',
        'processing' => 'Processing',
        'held'       => 'Held in Escrow',
        'failed'     => 'Failed',
        'refunded'   => 'Refunded',
    ];

    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="payment_history_' . date('Y-m-d') . '.csv"');

    $fp = fopen('php://output', 'w');
    fputcsv($fp, ['Milestone', 'Freelancer', 'Amount', 'Fee', 'Status', 'Date']);
    while ($row = $exportResult->fetch_assoc()) {
        $statusKey = strtolower(trim($row['status']));
        fputcsv($fp, [
            $row['milestone_title'],
            $row['payee_name'],
            number_format((float)$row['total_amount'], 2),
            number_format((float)$row['platform_fee'], 2),
            $statusLabels[$statusKey] ?? ucfirst($row['status']),
            date('M d, Y', strtotime($row['created_at'])),
        ]);
    }
    fclose($fp);
    $conn->close();
    exit;
}
$uStmt = $conn->prepare('SELECT name, profile_image, wallet_balance FROM users WHERE id = ?');
$uStmt->bind_param('i', $userId);
$uStmt->execute();
$user = $uStmt->get_result()->fetch_assoc();
$uStmt->close();
$wStmt = $conn->prepare('SELECT wallet_balance FROM users WHERE id = ?');
$wStmt->bind_param('i', $userId);
$wStmt->execute();
$walletBalance = (float) $wStmt->get_result()->fetch_assoc()['wallet_balance'];
$wStmt->close();
$s1 = $conn->prepare("SELECT COALESCE(SUM(total_amount), 0) AS total FROM payments WHERE payer_id = ? AND status = 'completed'");
$s1->bind_param('i', $userId);
$s1->execute();
$totalSpent = (float) $s1->get_result()->fetch_assoc()['total'];
$s1->close();
$s2 = $conn->prepare("SELECT COALESCE(SUM(total_amount), 0) AS total FROM payments WHERE payer_id = ? AND status = 'completed' AND MONTH(created_at) = MONTH(NOW()) AND YEAR(created_at) = YEAR(NOW())");
$s2->bind_param('i', $userId);
$s2->execute();
$monthSpent = (float) $s2->get_result()->fetch_assoc()['total'];
$s2->close();
$s3 = $conn->prepare("SELECT COALESCE(SUM(platform_fee), 0) AS total FROM payments WHERE payer_id = ? AND status = 'completed'");
$s3->bind_param('i', $userId);
$s3->execute();
$totalFees = (float) $s3->get_result()->fetch_assoc()['total'];
$s3->close();
$page = max(1, intval($_GET['page'] ?? 1));
$perPage = 10;
$countSql = 'SELECT COUNT(*) AS total FROM payments WHERE payer_id = ?';
$countStmt = $conn->prepare($countSql);
$countStmt->bind_param('i', $userId);
$countStmt->execute();
$totalPayments = (int) $countStmt->get_result()->fetch_assoc()['total'];
$countStmt->close();
$pagination = paginate($totalPayments, $perPage, $page);
$offset = $pagination['offset'];
$stmt = $conn->prepare('
    SELECT p.*, m.title AS milestone_title, u.name AS payee_name
    FROM payments p
    JOIN milestones m ON p.milestone_id = m.id
    JOIN users u ON p.payee_id = u.id
    WHERE p.payer_id = ?
    ORDER BY p.created_at DESC
    LIMIT ? OFFSET ?
');
$stmt->bind_param('iii', $userId, $perPage, $offset);
$stmt->execute();
$paymentsResult = $stmt->get_result();
$stmt->close();

$paymentStyles = [
    'completed'   => 'color: #059669; background: #ecfdf5; border: 1px solid #a7f3d0;',
    'pending'     => 'color: #d97706; background: #fffbeb; border: 1px solid #fde68a;',
    'processing'  => 'color: #2563eb; background: #eff6ff; border: 1px solid #bfdbfe;',
    'held'        => 'color: #7c3aed; background: #f5f3ff; border: 1px solid #ddd6fe;',
    'failed'      => 'color: #dc2626; background: #fef2f2; border: 1px solid #fecaca;',
    'refunded'    => 'color: #6b7280; background: #f9fafb; border: 1px solid #e5e7eb;',
];
$defaultPaymentStyle = 'color: #6b7280; background: #f9fafb; border: 1px solid #e5e7eb;';

$pageTitle = 'Payment History';
$pageSubtitle = 'Track your spending and transactions';
$activePage = 'payment_history';
$user = ['name' => $user['name'] ?? 'Client', 'profile_image' => $user['profile_image'] ?? null];
$unreadCount = get_unread_message_count($userId, 'client');
$profileLink = 'profile.php';
require_once __DIR__ . '/../includes/client_topbar.php';
?>

<style>
    /* ── Premium Payment History ──────────────────────────────── */
    .stat-card {
        background: #fff;
        border: 1px solid rgba(0,0,0,0.06);
        box-shadow: 0 1px 3px rgba(0,0,0,0.04);
        transition: all 0.25s cubic-bezier(0.4, 0, 0.2, 1);
    }
    .stat-card:hover {
        border-color: rgba(99,102,241,0.15);
        box-shadow: 0 4px 16px -2px rgba(99,102,241,0.06);
    }
    .dark .stat-card {
        background: #1e293b;
        border-color: rgba(255,255,255,0.06);
    }
    .dark .stat-card:hover {
        border-color: rgba(99,102,241,0.25);
    }

    .table-section {
        background: #fff;
        border: 1px solid rgba(0,0,0,0.06);
        box-shadow: 0 1px 3px rgba(0,0,0,0.04);
    }
    .dark .table-section {
        background: #1e293b;
        border-color: rgba(255,255,255,0.06);
    }

    .txn-row {
        transition: background 0.15s ease;
    }
    .txn-row:hover {
        background: #f8fafc;
    }
    .dark .txn-row:hover {
        background: rgba(255,255,255,0.03);
    }

    .filter-chip {
        padding: 5px 12px;
        border-radius: 999px;
        font-size: 11px;
        font-weight: 600;
        border: 1px solid #e2e8f0;
        background: #fff;
        color: #64748b;
        cursor: pointer;
        transition: all 0.2s ease;
        white-space: nowrap;
    }
    .filter-chip:hover {
        border-color: #c7d2fe;
        color: #4f46e5;
        background: #eef2ff;
    }
    .filter-chip.active {
        background: #4f46e5;
        color: #fff;
        border-color: #4f46e5;
    }
    .dark .filter-chip {
        background: #1e293b;
        border-color: #334155;
        color: #94a3b8;
    }
    .dark .filter-chip:hover {
        border-color: #6366f1;
        color: #818cf8;
        background: rgba(99,102,241,0.1);
    }
    .dark .filter-chip.active {
        background: #6366f1;
        border-color: #6366f1;
        color: #fff;
    }

    .empty-card {
        background: #fff;
        border: 1px solid rgba(0,0,0,0.06);
        box-shadow: 0 1px 3px rgba(0,0,0,0.04);
    }
    .dark .empty-card {
        background: #1e293b;
        border-color: rgba(255,255,255,0.06);
    }
</style>

<?php display_flash('success'); ?>
<?php display_flash('error'); ?>

<main class="max-w-7xl mx-auto px-4 sm:px-6 py-8 flex flex-col gap-6">

    <!-- ═══════════════ Stat Cards ═══════════════ -->
    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
        <!-- Total Spent -->
        <div class="stat-card rounded-2xl p-5 fade-in">
            <div class="flex items-center justify-between mb-3">
                <i data-lucide="trending-up" class="w-6 h-6 text-red-500"></i>
                <span class="text-[10px] font-bold text-red-500 bg-red-50 dark:bg-red-900/20 px-2 py-0.5 rounded-md uppercase tracking-wider">All Time</span>
            </div>
            <p class="text-2xl font-black text-gray-900 dark:text-white m-0 tracking-tight"><?= format_currency($totalSpent) ?></p>
            <p class="text-xs font-semibold text-gray-400 dark:text-slate-500 mt-1 m-0">Total Spent</p>
        </div>

        <!-- This Month -->
        <div class="stat-card rounded-2xl p-5 fade-in" style="animation-delay:.05s">
            <div class="flex items-center justify-between mb-3">
                <i data-lucide="calendar-check" class="w-6 h-6 text-blue-500"></i>
                <span class="text-[10px] font-bold text-blue-500 bg-blue-50 dark:bg-blue-900/20 px-2 py-0.5 rounded-md uppercase tracking-wider">This Month</span>
            </div>
            <p class="text-2xl font-black text-gray-900 dark:text-white m-0 tracking-tight"><?= format_currency($monthSpent) ?></p>
            <p class="text-xs font-semibold text-gray-400 dark:text-slate-500 mt-1 m-0">Monthly Spending</p>
        </div>

        <!-- Wallet Balance -->
        <div class="stat-card rounded-2xl p-5 fade-in" style="animation-delay:.1s">
            <div class="flex items-center justify-between mb-3">
                <i data-lucide="wallet" class="w-6 h-6 text-indigo-500"></i>
                <span class="text-[10px] font-bold text-indigo-500 bg-indigo-50 dark:bg-indigo-900/20 px-2 py-0.5 rounded-md uppercase tracking-wider">Available</span>
            </div>
            <p class="text-2xl font-black text-gray-900 dark:text-white m-0 tracking-tight"><?= format_currency($walletBalance) ?></p>
            <p class="text-xs font-semibold text-gray-400 dark:text-slate-500 mt-1 m-0">Wallet Balance</p>
        </div>

        <!-- Platform Fees -->
        <div class="stat-card rounded-2xl p-5 fade-in" style="animation-delay:.15s">
            <div class="flex items-center justify-between mb-3">
                <i data-lucide="receipt" class="w-6 h-6 text-gray-400"></i>
                <span class="text-[10px] font-bold text-gray-400 bg-gray-100 dark:bg-slate-700 px-2 py-0.5 rounded-md uppercase tracking-wider">Fees</span>
            </div>
            <p class="text-2xl font-black text-gray-900 dark:text-white m-0 tracking-tight"><?= format_currency($totalFees) ?></p>
            <p class="text-xs font-semibold text-gray-400 dark:text-slate-500 mt-1 m-0">Platform Fees Paid</p>
        </div>
    </div>

    <!-- ═══════════════ Transaction Table ═══════════════ -->
    <div class="table-section rounded-2xl overflow-hidden fade-in" style="animation-delay:.2s">
        <!-- Header -->
        <div class="px-6 pt-6 pb-4 flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
            <div class="flex items-center gap-3">
                <i data-lucide="activity" class="w-5 h-5 text-indigo-600"></i>
                <div>
                    <h2 class="text-base font-bold text-gray-900 dark:text-white m-0">Payment History & Ledger</h2>
                    <p class="text-xs text-gray-400 dark:text-slate-500 m-0"><?= $totalPayments ?> transaction<?= $totalPayments !== 1 ? 's' : '' ?> total</p>
                </div>
            </div>
            <div class="flex items-center gap-2">
                <a href="?export=csv" class="inline-flex items-center gap-1.5 px-3.5 py-2 bg-gray-100 hover:bg-gray-200 dark:bg-slate-700 dark:hover:bg-slate-600 text-gray-600 dark:text-slate-300 text-xs font-semibold rounded-lg transition-all">
                    <i data-lucide="upload" class="w-3.5 h-3.5"></i> Export
                </a>
            </div>
        </div>

        <!-- Filter Chips -->
        <div class="px-6 pb-4 flex items-center gap-2 overflow-x-auto scrollbar-hide">
            <button class="filter-chip active" onclick="filterPayments(this, 'all')">All</button>
            <button class="filter-chip" onclick="filterPayments(this, 'held')">Held in Escrow</button>
            <button class="filter-chip" onclick="filterPayments(this, 'completed')">Completed</button>
            <button class="filter-chip" onclick="filterPayments(this, 'pending')">Pending</button>
            <button class="filter-chip" onclick="filterPayments(this, 'processing')">Processing</button>
            <button class="filter-chip" onclick="filterPayments(this, 'refunded')">Refunded</button>
        </div>

        <?php if ($paymentsResult->num_rows > 0): ?>
            <!-- Table -->
            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead>
                        <tr class="border-y border-gray-100 dark:border-slate-700/50">
                            <th class="text-left py-3 px-6 text-[10px] font-bold text-gray-400 dark:text-slate-500 uppercase tracking-wider">Milestone</th>
                            <th class="text-left py-3 px-6 text-[10px] font-bold text-gray-400 dark:text-slate-500 uppercase tracking-wider">Freelancer</th>
                            <th class="text-right py-3 px-6 text-[10px] font-bold text-gray-400 dark:text-slate-500 uppercase tracking-wider">Amount</th>
                            <th class="text-right py-3 px-6 text-[10px] font-bold text-gray-400 dark:text-slate-500 uppercase tracking-wider">Fee</th>
                            <th class="text-center py-3 px-6 text-[10px] font-bold text-gray-400 dark:text-slate-500 uppercase tracking-wider">Status</th>
                            <th class="text-right py-3 px-6 text-[10px] font-bold text-gray-400 dark:text-slate-500 uppercase tracking-wider">Date</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php while ($pay = $paymentsResult->fetch_assoc()): ?>
                            <tr class="txn-row border-b border-gray-50 dark:border-slate-700/30" data-payment-status="<?= htmlspecialchars($pay['status']) ?>">
                                <td class="py-3.5 px-6">
                                    <span class="font-semibold text-gray-900 dark:text-white text-sm"><?= sanitize_string($pay['milestone_title']) ?></span>
                                </td>
                                <td class="py-3.5 px-6">
                                    <span class="text-gray-500 dark:text-slate-400 text-sm"><?= sanitize_string($pay['payee_name']) ?></span>
                                </td>
                                <td class="py-3.5 px-6 text-right">
                                    <span class="font-bold text-gray-900 dark:text-white"><?= format_currency((float) $pay['total_amount']) ?></span>
                                </td>
                                <td class="py-3.5 px-6 text-right">
                                    <span class="text-gray-400 dark:text-slate-500 text-xs"><?= format_currency((float) $pay['platform_fee']) ?></span>
                                </td>
                                <td class="py-3.5 px-6 text-center">
                                    <?php
                                        $rawStatus = trim($pay['status'] ?? '');
                                        $statusKey = strtolower($rawStatus);
                                        $statusLabels = [
                                            'completed'  => 'Completed',
                                            'pending'    => 'Pending',
                                            'processing' => 'Processing',
                                            'held'       => 'Held in Escrow',
                                            'failed'     => 'Failed',
                                            'refunded'   => 'Refunded',
                                        ];
                                        $statusLabel = $statusLabels[$statusKey] ?? ucfirst($rawStatus ?: 'Unknown');
                                        $statusStyle = $paymentStyles[$statusKey] ?? $defaultPaymentStyle;
                                    ?>
                                    <span class="inline-block px-2.5 py-1 rounded-lg text-[11px] font-semibold" style="<?= $statusStyle ?>">
                                        <?= htmlspecialchars($statusLabel) ?>
                                    </span>
                                </td>
                                <td class="py-3.5 px-6 text-right">
                                    <span class="text-xs text-gray-400 dark:text-slate-500"><?= date('M d, Y', strtotime($pay['created_at'])) ?></span>
                                </td>
                            </tr>
                        <?php endwhile; ?>
                    </tbody>
                </table>
            </div>

            <!-- Pagination -->
            <?php if ($pagination['total_pages'] > 1): ?>
                <div class="px-6 py-4 border-t border-gray-100 dark:border-slate-700/50 flex items-center justify-between">
                    <p class="text-xs text-gray-400 dark:text-slate-500 m-0">
                        Page <span class="font-semibold text-gray-600 dark:text-slate-300"><?= $pagination['current_page'] ?></span> of <span class="font-semibold text-gray-600 dark:text-slate-300"><?= $pagination['total_pages'] ?></span>
                    </p>
                    <div class="flex items-center gap-1">
                        <?php if ($pagination['has_prev']): ?>
                            <a href="?page=<?= $pagination['current_page'] - 1 ?>" class="w-9 h-9 flex items-center justify-center rounded-xl border border-gray-200 dark:border-slate-600 text-gray-500 dark:text-slate-400 hover:bg-gray-50 dark:hover:bg-slate-700 text-sm transition-all">
                                <i data-lucide="chevron-left" class="w-4 h-4"></i>
                            </a>
                        <?php endif; ?>
                        <?php for ($i = max(1, $pagination['current_page'] - 2); $i <= min($pagination['total_pages'], $pagination['current_page'] + 2); $i++): ?>
                            <a href="?page=<?= $i ?>" class="w-9 h-9 flex items-center justify-center rounded-xl text-sm font-medium transition-all <?= $i === $pagination['current_page'] ? 'bg-indigo-600 text-white shadow-sm shadow-indigo-600/20' : 'text-gray-500 dark:text-slate-400 hover:bg-gray-50 dark:hover:bg-slate-700' ?>"><?= $i ?></a>
                        <?php endfor; ?>
                        <?php if ($pagination['has_next']): ?>
                            <a href="?page=<?= $pagination['current_page'] + 1 ?>" class="w-9 h-9 flex items-center justify-center rounded-xl border border-gray-200 dark:border-slate-600 text-gray-500 dark:text-slate-400 hover:bg-gray-50 dark:hover:bg-slate-700 text-sm transition-all">
                                <i data-lucide="chevron-right" class="w-4 h-4"></i>
                            </a>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endif; ?>

        <?php else: ?>
            <!-- Empty State -->
            <div class="text-center py-16 px-6">
                <i data-lucide="receipt" class="w-10 h-10 text-gray-300 dark:text-slate-500 mx-auto mb-4"></i>
                <p class="text-gray-500 dark:text-slate-400 text-sm font-semibold mb-1 m-0">No transactions yet</p>
                <p class="text-gray-400 dark:text-slate-500 text-xs m-0">Fund milestones to see your payment history here</p>
            </div>
        <?php endif; ?>
    </div>
</main>

<?php $conn->close(); ?>

<script>
function filterPayments(btn, status) {
    document.querySelectorAll('.filter-chip').forEach(c => c.classList.remove('active'));
    btn.classList.add('active');
    document.querySelectorAll('[data-payment-status]').forEach(row => {
        if (status === 'all' || row.getAttribute('data-payment-status') === status) {
            row.style.display = '';
        } else {
            row.style.display = 'none';
        }
    });
}
</script>

<?php require_once __DIR__ . '/../includes/client_footer.php'; ?>
