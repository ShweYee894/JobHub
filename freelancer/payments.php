<?php
$activePage = 'payments';
require_once __DIR__ . '/../auth/auth.php';
require_role('freelancer');
require_once __DIR__ . '/../config/db.php';

$userId = $_SESSION['user_id'];

$uStmt = $conn->prepare('SELECT name, email, profile_image, wallet_balance FROM users WHERE id = ?');
$uStmt->bind_param('i', $userId);
$uStmt->execute();
$user = $uStmt->get_result()->fetch_assoc();
$uStmt->close();

$walletBalance = (float) $user['wallet_balance'];

$s1 = $conn->prepare("SELECT COALESCE(SUM(freelancer_net), 0) AS total FROM payments WHERE payee_id = ? AND status = 'completed'");
$s1->bind_param('i', $userId);
$s1->execute();
$totalEarnings = (float) $s1->get_result()->fetch_assoc()['total'];
$s1->close();

$s2 = $conn->prepare("SELECT COALESCE(SUM(freelancer_net), 0) AS total FROM payments WHERE payee_id = ? AND status = 'completed' AND MONTH(created_at) = MONTH(NOW()) AND YEAR(created_at) = YEAR(NOW())");
$s2->bind_param('i', $userId);
$s2->execute();
$monthEarnings = (float) $s2->get_result()->fetch_assoc()['total'];
$s2->close();

$s3 = $conn->prepare("SELECT COALESCE(SUM(platform_fee), 0) AS total FROM payments WHERE payee_id = ? AND status = 'completed'");
$s3->bind_param('i', $userId);
$s3->execute();
$totalFees = (float) $s3->get_result()->fetch_assoc()['total'];
$s3->close();

$page = max(1, intval($_GET['page'] ?? 1));
$perPage = 10;
$countSql = 'SELECT COUNT(*) AS total FROM payments WHERE payee_id = ?';
$countStmt = $conn->prepare($countSql);
$countStmt->bind_param('i', $userId);
$countStmt->execute();
$totalPayments = (int) $countStmt->get_result()->fetch_assoc()['total'];
$countStmt->close();
$pagination = paginate($totalPayments, $perPage, $page);
$offset = $pagination['offset'];

$stmt = $conn->prepare('
    SELECT p.*, m.title AS milestone_title, u.name AS payer_name
    FROM payments p
    JOIN milestones m ON p.milestone_id = m.id
    JOIN users u ON p.payer_id = u.id
    WHERE p.payee_id = ?
    ORDER BY p.created_at DESC
    LIMIT ? OFFSET ?
');
$stmt->bind_param('iii', $userId, $perPage, $offset);
$stmt->execute();
$paymentsResult = $stmt->get_result();
$stmt->close();
$conn->close();

$paymentColors = [
    'completed'  => 'bg-emerald-50 text-emerald-600 border border-emerald-200',
    'pending'    => 'bg-amber-50 text-amber-600 border border-amber-200',
    'processing' => 'bg-blue-50 text-blue-600 border border-blue-200',
    'failed'     => 'bg-red-50 text-red-500 border border-red-200',
    'refunded'   => 'bg-gray-100 text-gray-500 border border-gray-200',
];

$pageTitle = 'Payments';
$pageSubtitle = 'Track your earnings and payment history';
$activePage = 'earnings';
$user = ['name' => $user['name'] ?? 'Freelancer', 'profile_image' => $user['profile_image'] ?? null];
$unreadCount = get_unread_message_count($userId, 'freelancer');
require_once __DIR__ . '/../components/freelancer_header.php';
?>
    <?php display_flash('success') ?>
    <?php display_flash('error') ?>

    <div class="grid grid-cols-1 sm:grid-cols-2 xl:grid-cols-4 gap-4">
        <div class="stat-card bg-white rounded-2xl p-5 border border-gray-100 shadow-sm fade-in">
            <div class="flex items-center justify-between mb-3">
                <div class="w-11 h-11 rounded-xl bg-blue-50 flex items-center justify-center"><i class="fas fa-wallet text-blue-500"></i></div>
                <span class="text-[10px] font-semibold text-blue-500 uppercase tracking-wider">Balance</span>
            </div>
            <p class="text-2xl font-black text-gray-900"><?= format_currency($walletBalance) ?></p>
            <p class="text-xs text-gray-400 mt-1">Wallet Balance</p>
        </div>
        <div class="stat-card bg-white rounded-2xl p-5 border border-gray-100 shadow-sm fade-in" style="animation-delay:.05s">
            <div class="flex items-center justify-between mb-3">
                <div class="w-11 h-11 rounded-xl bg-emerald-50 flex items-center justify-center"><i class="fas fa-dollar-sign text-emerald-500"></i></div>
                <span class="text-[10px] font-semibold text-emerald-500 uppercase tracking-wider">Total</span>
            </div>
            <p class="text-2xl font-black text-gray-900"><?= format_currency($totalEarnings) ?></p>
            <p class="text-xs text-gray-400 mt-1">Total Earned</p>
        </div>
        <div class="stat-card bg-white rounded-2xl p-5 border border-gray-100 shadow-sm fade-in" style="animation-delay:.1s">
            <div class="flex items-center justify-between mb-3">
                <div class="w-11 h-11 rounded-xl bg-violet-50 flex items-center justify-center"><i class="fas fa-calendar-check text-violet-500"></i></div>
                <span class="text-[10px] font-semibold text-violet-500 uppercase tracking-wider">This Month</span>
            </div>
            <p class="text-2xl font-black text-gray-900"><?= format_currency($monthEarnings) ?></p>
            <p class="text-xs text-gray-400 mt-1">Monthly Earnings</p>
        </div>
        <div class="stat-card bg-white rounded-2xl p-5 border border-gray-100 shadow-sm fade-in" style="animation-delay:.15s">
            <div class="flex items-center justify-between mb-3">
                <div class="w-11 h-11 rounded-xl bg-red-50 flex items-center justify-center"><i class="fas fa-coins text-red-500"></i></div>
                <span class="text-[10px] font-semibold text-red-500 uppercase tracking-wider">Fees</span>
            </div>
            <p class="text-2xl font-black text-gray-900"><?= format_currency($totalFees) ?></p>
            <p class="text-xs text-gray-400 mt-1">Platform Fees</p>
        </div>
    </div>

    <div class="bg-white rounded-2xl border border-gray-100 shadow-sm fade-in" style="animation-delay:.2s">
        <div class="p-6">
            <div class="flex items-center justify-between mb-5">
                <div class="flex items-center gap-3">
                    <div class="w-10 h-10 rounded-xl bg-blue-50 flex items-center justify-center"><i class="fas fa-receipt text-blue-500"></i></div>
                    <div><h2 class="text-base font-bold text-gray-900">Payment History</h2><p class="text-xs text-gray-400"><?= $totalPayments ?> payment<?= $totalPayments !== 1 ? 's' : '' ?></p></div>
                </div>
                <a href="withdraw.php" class="btn-grad inline-flex items-center gap-2 text-white text-xs font-semibold px-4 py-2 rounded-xl shadow-lg shadow-blue-500/25">
                    <i class="fas fa-money-bill-wave text-[10px]"></i> Withdraw Funds
                </a>
            </div>

            <?php if ($paymentsResult->num_rows > 0): ?>
                <div class="overflow-x-auto">
                    <table class="w-full text-sm">
                        <thead><tr class="border-b border-gray-100">
                            <th class="text-left py-3 px-3 text-xs font-semibold text-gray-400 uppercase tracking-wider">Milestone</th>
                            <th class="text-left py-3 px-3 text-xs font-semibold text-gray-400 uppercase tracking-wider">From</th>
                            <th class="text-left py-3 px-3 text-xs font-semibold text-gray-400 uppercase tracking-wider">Gross</th>
                            <th class="text-left py-3 px-3 text-xs font-semibold text-gray-400 uppercase tracking-wider">Fee</th>
                            <th class="text-left py-3 px-3 text-xs font-semibold text-gray-400 uppercase tracking-wider">Net</th>
                            <th class="text-left py-3 px-3 text-xs font-semibold text-gray-400 uppercase tracking-wider">Status</th>
                            <th class="text-right py-3 px-3 text-xs font-semibold text-gray-400 uppercase tracking-wider">Date</th>
                        </tr></thead>
                        <tbody>
                            <?php while ($pay = $paymentsResult->fetch_assoc()): ?>
                                <tr class="border-b border-gray-50 hover:bg-gray-50 transition-colors">
                                    <td class="py-3 px-3"><span class="font-medium text-gray-900"><?= sanitize_string($pay['milestone_title']) ?></span></td>
                                    <td class="py-3 px-3 text-gray-500"><?= sanitize_string($pay['payer_name']) ?></td>
                                    <td class="py-3 px-3 font-semibold text-gray-700"><?= format_currency((float) $pay['total_amount']) ?></td>
                                    <td class="py-3 px-3 text-red-400">-<?= format_currency((float) $pay['platform_fee']) ?></td>
                                    <td class="py-3 px-3 font-bold text-emerald-600"><?= format_currency((float) $pay['freelancer_net']) ?></td>
                                    <td class="py-3 px-3"><span class="inline-block px-2.5 py-1 rounded-lg text-[11px] font-semibold <?= $paymentColors[$pay['status']] ?? 'bg-gray-100 text-gray-500 border border-gray-200' ?>"><?= sanitize_string(ucfirst($pay['status'])) ?></span></td>
                                    <td class="py-3 px-3 text-right text-gray-400 text-xs"><?= date('M d, Y', strtotime($pay['created_at'])) ?></td>
                                </tr>
                            <?php endwhile; ?>
                        </tbody>
                    </table>
                </div>

                <?php if ($pagination['total_pages'] > 1): ?>
                    <div class="flex items-center justify-between mt-5 pt-5 border-t border-gray-100">
                        <p class="text-xs text-gray-400">Page <span class="font-semibold text-gray-600"><?= $pagination['current_page'] ?></span> of <span class="font-semibold text-gray-600"><?= $pagination['total_pages'] ?></span></p>
                        <div class="flex items-center gap-1">
                            <?php if ($pagination['has_prev']): ?>
                                <a href="?page=<?= $pagination['current_page'] - 1 ?>" class="w-9 h-9 flex items-center justify-center rounded-xl border border-gray-200 text-gray-500 hover:bg-gray-50 text-sm"><i class="fas fa-chevron-left text-xs"></i></a>
                            <?php endif; ?>
                            <?php for ($i = max(1, $pagination['current_page'] - 2); $i <= min($pagination['total_pages'], $pagination['current_page'] + 2); $i++): ?>
                                <a href="?page=<?= $i ?>" class="w-9 h-9 flex items-center justify-center rounded-xl text-sm font-medium transition-all <?= $i === $pagination['current_page'] ? 'btn-grad text-white shadow-sm' : 'text-gray-500 hover:bg-gray-50' ?>"><?= $i ?></a>
                            <?php endfor; ?>
                            <?php if ($pagination['has_next']): ?>
                                <a href="?page=<?= $pagination['current_page'] + 1 ?>" class="w-9 h-9 flex items-center justify-center rounded-xl border border-gray-200 text-gray-500 hover:bg-gray-50 text-sm"><i class="fas fa-chevron-right text-xs"></i></a>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endif; ?>
            <?php else: ?>
                <div class="text-center py-12">
                    <div class="w-16 h-16 rounded-2xl bg-gray-100 flex items-center justify-center mx-auto mb-4"><i class="fas fa-receipt text-2xl text-gray-300"></i></div>
                    <p class="text-gray-500 text-sm font-medium">No payments yet</p>
                    <p class="text-gray-400 text-xs mt-1">Complete milestones to receive your first payment</p>
                </div>
            <?php endif; ?>
        </div>
    </div>
<?php require_once __DIR__ . '/../components/freelancer_footer.php'; ?>
