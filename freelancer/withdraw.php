<?php
$activePage = 'earnings';
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

$stmt = $conn->prepare("SELECT COALESCE(SUM(amount), 0) AS total FROM wallet_transactions WHERE user_id = ? AND type = 'withdrawal' AND MONTH(created_at) = MONTH(NOW()) AND YEAR(created_at) = YEAR(NOW())");
$stmt->bind_param('i', $userId);
$stmt->execute();
$monthWithdrawn = (float) $stmt->get_result()->fetch_assoc()['total'];
$stmt->close();

$page = max(1, intval($_GET['page'] ?? 1));
$perPage = 10;
$countSql = "SELECT COUNT(*) AS total FROM wallet_transactions WHERE user_id = ? AND type = 'withdrawal'";
$countStmt = $conn->prepare($countSql);
$countStmt->bind_param('i', $userId);
$countStmt->execute();
$totalWithdrawals = (int) $countStmt->get_result()->fetch_assoc()['total'];
$countStmt->close();
$pagination = paginate($totalWithdrawals, $perPage, $page);
$offset = $pagination['offset'];

$stmt = $conn->prepare("SELECT * FROM wallet_transactions WHERE user_id = ? AND type = 'withdrawal' ORDER BY created_at DESC LIMIT ? OFFSET ?");
$stmt->bind_param('iii', $userId, $perPage, $offset);
$stmt->execute();
$withdrawalsResult = $stmt->get_result();
$stmt->close();
$conn->close();

$errors = [];
$success = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf_token()) {
        $errors[] = 'Invalid security token.';
    } else {
        $amount = sanitize_float($_POST['amount'] ?? 0);
        if ($amount <= 0) {
            $errors[] = 'Amount must be greater than zero.';
        } elseif ($amount > $walletBalance) {
            $errors[] = 'Insufficient wallet balance. Your balance is ' . format_currency($walletBalance) . '.';
        } elseif ($amount < 10) {
            $errors[] = 'Minimum withdrawal amount is $10.00.';
        } else {
            $newBalance = $walletBalance - $amount;
            $conn->begin_transaction();
            try {
                $stmt = $conn->prepare('UPDATE users SET wallet_balance = wallet_balance - ? WHERE id = ? AND wallet_balance >= ?');
                $stmt->bind_param('did', $amount, $userId, $amount);
                $stmt->execute();
                if ($stmt->affected_rows === 0) {
                    throw new Exception('Insufficient balance (concurrent modification).');
                }
                $stmt->close();

                $newBalance = $walletBalance - $amount;
                $desc = 'Withdrawal of ' . format_currency($amount);
                $stmt = $conn->prepare('INSERT INTO wallet_transactions (user_id, type, amount, balance_after, reference_id, reference_type, description, created_at) VALUES (?, \'withdrawal\', ?, ?, NULL, \'wallet\', ?, NOW())');
                $stmt->bind_param('idds', $userId, $amount, $newBalance, $desc);
                $stmt->execute();
                $stmt->close();

                $conn->commit();
                $success = true;
                $walletBalance = $newBalance;
                set_flash('success', 'Withdrawal of ' . format_currency($amount) . ' processed successfully.');
            } catch (Exception $e) {
                $conn->rollback();
                $errors[] = 'Failed to process withdrawal. Please try again.';
            }
        }
    }
}

$pageTitle = 'Withdraw Funds';
$pageSubtitle = 'Transfer earnings to your bank account';
$activePage = 'earnings';
$user = ['name' => $user['name'] ?? 'Freelancer', 'profile_image' => $user['profile_image'] ?? null];
$unreadCount = get_unread_message_count($userId, 'freelancer');
require_once __DIR__ . '/../components/freelancer_header.php';
?>
    <?= display_flash('success') ?>
    <?= display_flash('error') ?>

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
        <!-- Withdraw Form -->
        <div class="lg:col-span-1">
            <div class="bg-white rounded-2xl border border-gray-100 shadow-sm p-6 fade-in dark:bg-gray-800 dark:border-gray-700">
                <div class="flex items-center gap-3 mb-6">
                    <div class="w-10 h-10 rounded-xl bg-blue-50 flex items-center justify-center"><i class="fas fa-wallet text-blue-500"></i></div>
                    <div>
                        <h3 class="text-base font-bold text-gray-900 dark:text-white">Withdraw Funds</h3>
                        <p class="text-xs text-gray-400">Transfer to your bank account</p>
                    </div>
                </div>

                <!-- Balance Card -->
                <div class="bg-gradient-to-r from-blue-600 to-cyan-500 rounded-xl p-5 text-white mb-6">
                    <p class="text-xs text-white/70 uppercase tracking-wider font-semibold mb-1">Available Balance</p>
                    <p class="text-3xl font-black"><?= format_currency($walletBalance) ?></p>
                    <p class="text-xs text-white/60 mt-1">This month: <?= format_currency($monthWithdrawn) ?> withdrawn</p>
                </div>

                <?php if (!empty($errors)): ?>
                    <div class="bg-red-50 text-red-800 border border-red-200 rounded-xl p-4 mb-4 dark:bg-red-900/30 dark:border-red-800 dark:text-red-200">
                        <div class="flex items-start gap-3">
                            <i class="fas fa-exclamation-circle mt-0.5"></i>
                            <div class="text-sm">
                                <?php foreach ($errors as $err): ?>
                                    <p><?= sanitize_string($err) ?></p>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    </div>
                <?php endif; ?>

                <form method="POST" class="space-y-4">
                    <?= csrf_field() ?>
                    <div>
                        <label for="amount" class="block text-sm font-semibold text-gray-700 mb-2 dark:text-gray-300">Withdrawal Amount</label>
                        <div class="relative">
                            <span class="absolute left-4 top-1/2 -translate-y-1/2 text-gray-400 text-sm font-semibold">$</span>
                            <input type="number" id="amount" name="amount" step="0.01" min="10" max="<?= $walletBalance ?>"
                                   class="w-full pl-8 pr-4 py-3 rounded-xl border border-gray-200 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent transition-all bg-gray-50 focus:bg-white dark:bg-gray-700 dark:border-gray-600 dark:text-white"
                                   placeholder="0.00" required>
                        </div>
                        <p class="text-xs text-gray-400 mt-1.5">Minimum: $10.00 | Maximum: <?= format_currency($walletBalance) ?></p>
                    </div>

                    <div class="flex gap-2">
                        <button type="button" onclick="document.getElementById('amount').value='<?= $walletBalance ?>'" class="flex-1 px-3 py-2 bg-gray-100 hover:bg-gray-200 text-gray-600 text-xs font-semibold rounded-lg transition-all dark:bg-gray-700 dark:hover:bg-gray-600 dark:text-gray-300">Max</button>
                        <button type="button" onclick="document.getElementById('amount').value=Math.min(100, <?= $walletBalance ?>)" class="flex-1 px-3 py-2 bg-gray-100 hover:bg-gray-200 text-gray-600 text-xs font-semibold rounded-lg transition-all dark:bg-gray-700 dark:hover:bg-gray-600 dark:text-gray-300">$100</button>
                        <button type="button" onclick="document.getElementById('amount').value=Math.min(500, <?= $walletBalance ?>)" class="flex-1 px-3 py-2 bg-gray-100 hover:bg-gray-200 text-gray-600 text-xs font-semibold rounded-lg transition-all dark:bg-gray-700 dark:hover:bg-gray-600 dark:text-gray-300">$500</button>
                    </div>

                    <button type="submit" class="w-full btn-grad px-6 py-3 text-white rounded-xl text-sm font-bold flex items-center justify-center gap-2" <?= $walletBalance < 10 ? 'disabled style="opacity:0.5;cursor:not-allowed;"' : '' ?>>
                        <i class="fas fa-money-bill-wave text-[10px]"></i> Withdraw Funds
                    </button>
                </form>

                <div class="mt-4 p-3 bg-blue-50 rounded-xl border border-blue-100 dark:bg-blue-900/30 dark:border-blue-800">
                    <p class="text-xs text-blue-600 flex items-start gap-2">
                        <i class="fas fa-info-circle mt-0.5"></i>
                        <span>Withdrawals are processed within 1-3 business days. Funds will be transferred to your registered payment method.</span>
                    </p>
                </div>
            </div>
        </div>

        <!-- Withdrawal History -->
        <div class="lg:col-span-2">
            <div class="bg-white rounded-2xl border border-gray-100 shadow-sm p-6 fade-in dark:bg-gray-800 dark:border-gray-700" style="animation-delay:.1s">
                <div class="flex items-center gap-3 mb-5">
                    <div class="w-10 h-10 rounded-xl bg-violet-50 flex items-center justify-center"><i class="fas fa-history text-violet-500"></i></div>
                    <div>
                        <h2 class="text-base font-bold text-gray-900 dark:text-white">Withdrawal History</h2>
                        <p class="text-xs text-gray-400"><?= $totalWithdrawals ?> withdrawal<?= $totalWithdrawals !== 1 ? 's' : '' ?></p>
                    </div>
                </div>

                <?php if ($withdrawalsResult->num_rows > 0): ?>
                    <div class="space-y-3">
                        <?php while ($w = $withdrawalsResult->fetch_assoc()): ?>
                            <div class="flex items-center gap-4 p-4 rounded-xl bg-gray-50 border border-gray-100 dark:bg-gray-700 dark:border-gray-600">
                                <div class="w-10 h-10 rounded-xl bg-red-50 flex items-center justify-center flex-shrink-0">
                                    <i class="fas fa-arrow-up text-red-500 text-sm"></i>
                                </div>
                                <div class="flex-1 min-w-0">
                                    <p class="text-sm font-semibold text-gray-900 dark:text-white">Withdrawal</p>
                                    <p class="text-[11px] text-gray-400"><?= date('M d, Y h:i A', strtotime($w['created_at'])) ?></p>
                                </div>
                                <div class="text-right flex-shrink-0">
                                    <p class="text-sm font-bold text-red-600">-<?= format_currency((float) $w['amount']) ?></p>
                                    <p class="text-[11px] text-gray-400">Balance: <?= format_currency((float) $w['balance_after']) ?></p>
                                </div>
                            </div>
                        <?php endwhile; ?>
                    </div>

                    <?php if ($pagination['total_pages'] > 1): ?>
                        <div class="flex items-center justify-between mt-5 pt-5 border-t border-gray-100 dark:border-gray-700">
                            <p class="text-xs text-gray-400">Page <span class="font-semibold text-gray-600"><?= $pagination['current_page'] ?></span> of <span class="font-semibold text-gray-600"><?= $pagination['total_pages'] ?></span></p>
                            <div class="flex items-center gap-1">
                                <?php if ($pagination['has_prev']): ?>
                                    <a href="?page=<?= $pagination['current_page'] - 1 ?>" class="w-9 h-9 flex items-center justify-center rounded-xl border border-gray-200 text-gray-500 hover:bg-gray-50 text-sm dark:border-gray-600 dark:text-gray-400 dark:hover:bg-gray-700"><i class="fas fa-chevron-left text-xs"></i></a>
                                <?php endif; ?>
                                <?php for ($i = max(1, $pagination['current_page'] - 2); $i <= min($pagination['total_pages'], $pagination['current_page'] + 2); $i++): ?>
                                    <a href="?page=<?= $i ?>" class="w-9 h-9 flex items-center justify-center rounded-xl text-sm font-medium transition-all <?= $i === $pagination['current_page'] ? 'btn-grad text-white shadow-sm' : 'text-gray-500 hover:bg-gray-50 dark:text-gray-400 dark:hover:bg-gray-700' ?>"><?= $i ?></a>
                                <?php endfor; ?>
                                <?php if ($pagination['has_next']): ?>
                                    <a href="?page=<?= $pagination['current_page'] + 1 ?>" class="w-9 h-9 flex items-center justify-center rounded-xl border border-gray-200 text-gray-500 hover:bg-gray-50 text-sm dark:border-gray-600 dark:text-gray-400 dark:hover:bg-gray-700"><i class="fas fa-chevron-right text-xs"></i></a>
                                <?php endif; ?>
                            </div>
                        </div>
                    <?php endif; ?>
                <?php else: ?>
                    <div class="text-center py-12">
                        <div class="w-16 h-16 rounded-2xl bg-gray-100 flex items-center justify-center mx-auto mb-4"><i class="fas fa-history text-2xl text-gray-300"></i></div>
                        <p class="text-gray-500 text-sm font-medium">No withdrawals yet</p>
                        <p class="text-gray-400 text-xs mt-1">Your withdrawal history will appear here</p>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
<?php require_once __DIR__ . '/../components/freelancer_footer.php'; ?>
