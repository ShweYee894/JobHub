<?php

/**
 * Wallet History Page – Client
 * Shows complete wallet transaction history.
 */
require_once __DIR__ . '/../auth/auth.php';
require_role('client');
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/wallet_functions.php';
require_once __DIR__ . '/../includes/wallet_helpers.php';

$userId = (int) $_SESSION['user_id'];

// Fetch user info
$uStmt = $conn->prepare('SELECT name, profile_image FROM users WHERE id = ?');
$uStmt->bind_param('i', $userId);
$uStmt->execute();
$userData = $uStmt->get_result()->fetch_assoc();
$uStmt->close();

// Pagination
$page = max(1, intval($_GET['page'] ?? 1));
$perPage = 15;
$offset = ($page - 1) * $perPage;

// Get history
$historyData = get_wallet_history($conn, $userId, $perPage, $offset);
$groupedHistory = wallet_group_history_by_date($historyData['history']);
$totalItems = $historyData['total'];
$totalPages = max(1, ceil($totalItems / $perPage));

$balance = get_wallet_balance($conn, $userId);

$pageTitle = 'Wallet History';
$pageSubtitle = 'Complete transaction history';
$activePage = 'wallet';
$user = ['name' => $userData['name'] ?? 'Client', 'profile_image' => $userData['profile_image'] ?? null];
$unreadCount = get_unread_message_count($userId, 'client');
$profileLink = 'profile.php';
require_once __DIR__ . '/../includes/client_topbar.php';
?>

    <?php display_flash('success'); ?>
    <?php display_flash('error'); ?>
<main class="max-w-7xl mx-auto px-4 sm:px-6 py-8 flex flex-col gap-6">  
    <!-- Balance Summary -->
    <div class="flex flex-col sm:flex-row items-start sm:items-center justify-between gap-4">
        <div class="flex items-center gap-4">
            <div class="w-10 h-10 rounded-2xl bg-gradient-to-br from-blue-600 to-teal-500 flex items-center justify-center">
                <i class="fas fa-wallet text-xl text-white"></i>
            </div>
            <div>
                <p class="text-xs text-gray-400 dark:text-slate-500 m-0">Current Balance</p>
                <p class="text-2xl font-black text-gray-900 dark:text-white m-0"><?= wallet_format_currency($balance) ?></p>
            </div>
        </div>
        <div class="flex gap-2">
            <a href="wallet.php" class="inline-flex items-center gap-2 px-4 py-2.5 rounded-xl border border-gray-200 dark:border-slate-600 text-sm font-semibold text-gray-600 dark:text-slate-300 hover:bg-gray-50 dark:hover:bg-slate-700 transition-all no-underline">
                <i class="fas fa-arrow-left text-xs"></i> Back to Wallet
            </a>
            <a href="topup_wallet.php" class="inline-flex items-center gap-2 px-4 py-2.5 rounded-xl bg-gradient-to-r from-blue-600 to-teal-500 text-white text-sm font-bold shadow-lg shadow-blue-500/25 hover:shadow-xl transition-all no-underline">
                <i class="fas fa-plus text-xs"></i> Top Up
            </a>
        </div>
    </div>

    <!-- History List -->
    <div class="dh-card fade-in p-6" style="animation-delay: 0.1s">
        <div class="flex items-center gap-3 mb-5">
            <div class="w-10 h-10 rounded-xl bg-blue-50 dark:bg-blue-900/20 flex items-center justify-center">
                <i class="fas fa-clock-rotate-left text-blue-500"></i>
            </div>
            <div>
                <h3 class="text-base font-bold text-gray-900 dark:text-white m-0">Transaction History</h3>
                <p class="text-xs text-gray-400 dark:text-slate-500 m-0"><?= $totalItems ?> transaction<?= $totalItems !== 1 ? 's' : '' ?></p>
            </div>
        </div>

        <?php if (!empty($groupedHistory)): ?>
            <?php foreach ($groupedHistory as $dateLabel => $items): ?>
                <div class="mb-5">
                    <p class="text-[11px] font-bold uppercase tracking-widest text-gray-400 dark:text-slate-500 mb-3 px-4"><?= htmlspecialchars($dateLabel) ?></p>
                    <div class="divide-y divide-gray-100 dark:divide-slate-700">
                        <?php foreach ($items as $item): ?>
                            <?php render_wallet_history_item($item); ?>
                        <?php endforeach; ?>
                    </div>
                </div>
            <?php endforeach; ?>

            <!-- Pagination -->
            <?php if ($totalPages > 1): ?>
                <div class="flex items-center justify-between mt-6 pt-5 border-t border-gray-100 dark:border-slate-700">
                    <p class="text-xs text-gray-400 dark:text-slate-500">
                        Page <span class="font-semibold text-gray-600 dark:text-slate-300"><?= $page ?></span> of <span class="font-semibold text-gray-600 dark:text-slate-300"><?= $totalPages ?></span>
                    </p>
                    <div class="flex items-center gap-1">
                        <?php if ($page > 1): ?>
                            <a href="?page=<?= $page - 1 ?>" class="w-9 h-9 flex items-center justify-center rounded-xl border border-gray-200 dark:border-slate-600 text-gray-500 dark:text-slate-400 hover:bg-gray-50 dark:hover:bg-slate-700 text-sm no-underline">
                                <i class="fas fa-chevron-left text-xs"></i>
                            </a>
                        <?php endif; ?>
                        <?php for ($i = max(1, $page - 2); $i <= min($totalPages, $page + 2); $i++): ?>
                            <a href="?page=<?= $i ?>" class="w-9 h-9 flex items-center justify-center rounded-xl text-sm font-medium transition-all no-underline <?= $i === $page ? 'bg-gradient-to-r from-blue-600 to-teal-500 text-white shadow-sm' : 'text-gray-500 dark:text-slate-400 hover:bg-gray-50 dark:hover:bg-slate-700' ?>"><?= $i ?></a>
                        <?php endfor; ?>
                        <?php if ($page < $totalPages): ?>
                            <a href="?page=<?= $page + 1 ?>" class="w-9 h-9 flex items-center justify-center rounded-xl border border-gray-200 dark:border-slate-600 text-gray-500 dark:text-slate-400 hover:bg-gray-50 dark:hover:bg-slate-700 text-sm no-underline">
                                <i class="fas fa-chevron-right text-xs"></i>
                            </a>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endif; ?>
        <?php else: ?>
            <div class="text-center py-12">
                <div class="w-16 h-16 rounded-2xl bg-gray-100 dark:bg-slate-700 flex items-center justify-center mx-auto mb-4">
                    <i class="fas fa-receipt text-2xl text-gray-300 dark:text-slate-500"></i>
                </div>
                <p class="text-gray-500 dark:text-slate-400 text-sm font-medium mb-1">No transactions yet</p>
                <p class="text-gray-400 dark:text-slate-500 text-xs">Your wallet history will appear here</p>
            </div>
        <?php endif; ?>
    </div>
</main>
<?php $conn->close(); ?>
<?php require_once __DIR__ . '/../includes/client_footer.php'; ?>
