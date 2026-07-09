<?php

/**
 * Client Wallet Page – JobHub
 * Modern FinTech-style wallet with balance card, top-up, and history.
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

// Get wallet stats
$stats = get_wallet_stats($conn, $userId);

// Get wallet history (last 20 items)
$historyData = get_wallet_history($conn, $userId, 20, 0);
$groupedHistory = wallet_group_history_by_date($historyData['history']);

$wallet_stats = [
    [
        'badge_title' => 'Available Funds',
        'value' => '$' . number_format($stats['balance'], 2),
        'label' => 'Current Balance',
        'subtext' => 'Ready to use',
        'percentage' => 100,  // Visual bar full capacity metric
        'badge_bg' => 'bg-blue-600',
        'bar_color' => 'bg-blue-600',
        'delay' => 0
    ],
    [
        'badge_title' => 'Held for Milestones',
        'value' => '$' . number_format($stats['escrow_amount'], 2),
        'label' => 'In Escrow',
        'subtext' => 'Security locked',
        'percentage' => 65,  // Example status percentage metric matching image_c42726.png styling
        'badge_bg' => 'bg-amber-500',
        'bar_color' => 'bg-amber-500',
        'delay' => 1
    ],
    [
        'badge_title' => 'Completed Payments',
        'value' => '$' . number_format($stats['completed_payments'], 2),
        'label' => 'Total Spent',
        'subtext' => 'All-time history',
        'percentage' => 85,
        'badge_bg' => 'bg-emerald-600',
        'bar_color' => 'bg-emerald-600',
        'delay' => 2
    ],
    [
        'badge_title' => 'Total Deposits',
        'value' => '$' . number_format($stats['recent_topups'], 2),
        'label' => 'Top Ups',
        'subtext' => 'External funding',
        'percentage' => 45,
        'badge_bg' => 'bg-sky-500',
        'bar_color' => 'bg-sky-500',
        'delay' => 3
    ]
];

$pageTitle = 'My Wallet';
$pageSubtitle = 'Manage your funds and transactions';
$activePage = 'wallet';
$user = ['name' => $userData['name'] ?? 'Client', 'profile_image' => $userData['profile_image'] ?? null];
$unreadCount = get_unread_message_count($userId, 'client');
$profileLink = 'profile.php';
require_once __DIR__ . '/../includes/client_topbar.php';
?>

<style>
    .quick-amount-btn {
        transition: all 0.2s;
        border: 2px solid #e5e7eb;
        background: white;
        color: #374151;
        border-radius: 12px;
        padding: 10px 20px;
        font-weight: 600;
        font-size: 14px;
        cursor: pointer;
    }

    .quick-amount-btn:hover {
        border-color: #2563eb;
        color: #2563eb;
        background: #eff6ff;
    }

    .quick-amount-btn.active {
        border-color: #2563eb;
        background: #2563eb;
        color: white;
    }

    html.dark .quick-amount-btn {
        background: #1e293b;
        border-color: #334155;
        color: #e2e8f0;
    }

    html.dark .quick-amount-btn:hover {
        border-color: #3b82f6;
        color: #60a5fa;
        background: rgba(59, 130, 246, 0.1);
    }

    html.dark .quick-amount-btn.active {
        border-color: #3b82f6;
        background: #3b82f6;
        color: white;
    }

    .payment-method-option {
        border: 2px solid #e5e7eb;
        border-radius: 12px;
        padding: 14px 16px;
        cursor: pointer;
        transition: all 0.2s;
        display: flex;
        align-items: center;
        gap: 12px;
    }

    .payment-method-option:hover {
        border-color: #93c5fd;
    }

    .payment-method-option.selected {
        border-color: #2563eb;
        background: #eff6ff;
    }

    html.dark .payment-method-option {
        border-color: #334155;
        background: #1e293b;
    }

    html.dark .payment-method-option.selected {
        border-color: #3b82f6;
        background: rgba(59, 130, 246, 0.1);
    }

    .slide-down {
        animation: slideDown 0.3s ease forwards;
    }

    @keyframes slideDown {
        from {
            opacity: 0;
            transform: translateY(-20px) scale(0.95);
        }

        to {
            opacity: 1;
            transform: translateY(0) scale(1);
        }
    }

    .modal-overlay {
        background: rgba(0, 0, 0, 0.5);
        backdrop-filter: blur(4px);
    }
</style>

<?php display_flash('success'); ?>
<?php display_flash('error'); ?>
<main class="max-w-7xl mx-auto px-4 sm:px-6 py-8 flex flex-col gap-6">
    <!-- Wallet Hero Card -->
    <div class="relative overflow-hidden rounded-2xl bg-white border border-slate-200/80 p-6 text-slate-800 shadow-sm shadow-slate-100 dark:bg-slate-900 dark:border-slate-800 dark:text-white dark:shadow-xl dark:shadow-slate-950/20 fade-in">

        <!-- Adaptive Backdrop Blur Elements -->
        <!-- PALETTE UPDATED FROM image_c489b1.png WITH A DIRECTIONAL bg-gradient-to-bl -->
        <div class="absolute -top-12 -right-12 w-48 h-48 rounded-full opacity-30 dark:opacity-20 blur-2xl pointer-events-none bg-gradient-to-bl from-sky-400 via-sky-200 to-sky-50"></div>

        <div class="absolute -bottom-10 left-6 w-32 h-32 rounded-full opacity-15 dark:opacity-10 blur-2xl pointer-events-none bg-gradient-to-bl from-sky-300 to-transparent"></div>
        <div class="relative z-10 flex flex-col sm:flex-row sm:items-center sm:justify-between gap-6">

            <div class="space-y-3">
                <div class="flex items-center gap-3">
                    <div class="w-10 h-10 rounded-xl bg-blue-50/60 border border-blue-100 flex items-center justify-center shadow-inner dark:bg-white/10 dark:border-white/10">
                        <i class="fas fa-wallet text-sm text-blue-600 dark:text-teal-400"></i>
                    </div>
                    <div>
                        <p class="text-slate-500 dark:text-slate-400 text-xs font-semibold tracking-wide uppercase m-0">Available Balance</p>
                        <p class="text-slate-400 dark:text-slate-500 text-[11px] m-0 mt-0.5">Updated just now</p>
                    </div>
                </div>
                <p class="text-2xl sm:text-3xl font-black tracking-tight text-slate-900 dark:text-white m-0" id="walletBalance">
                    $<?= number_format($stats['balance'], 2) ?>
                </p>
            </div>

            <div class="flex items-center gap-2.5 sm:self-end">
                <button onclick="openTopUpModal()"
                    class="inline-flex items-center gap-2 px-4 py-2.5 bg-blue-600 text-white font-bold rounded-xl hover:bg-blue-700 dark:hover:bg-blue-500 active:scale-[0.98] transition-all shadow-sm shadow-blue-600/10 text-xs">
                    <i class="fas fa-plus text-[10px]"></i> Top Up Wallet
                </button>
                <a href="wallet_history.php"
                    class="inline-flex items-center gap-2 px-4 py-2.5 bg-slate-50 text-slate-600 font-semibold rounded-xl border border-slate-200 hover:bg-slate-100 hover:text-slate-800 dark:bg-slate-800 dark:text-slate-300 dark:border-slate-700 dark:hover:bg-slate-700/60 dark:hover:text-white transition-all text-xs">
                    <i class="fas fa-clock-rotate-left text-[10px]"></i> View History
                </a>
            </div>

        </div>
    </div>

    <!-- Stats Cards Grid Wrapper -->
    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
        <?php
        // Map your wallet variables into the clean structural design mapping from image_c42726.png
        foreach ($wallet_stats as $card):
            ?>
            <!-- Re-designed Card Container matching image_c42726.png -->
            <div class="bg-white rounded-2xl border border-gray-100 p-5 shadow-sm flex flex-col justify-between space-y-4 fade-in dark:bg-gray-800 dark:border-gray-700"
                style="animation-delay: <?= $card['delay'] * 0.1 ?>s">

                <div class="space-y-3">
                    <!-- 1. Top Full-Width Color Header Badge -->
                    <div class="<?= $card['badge_bg'] ?> text-white text-center text-xs font-bold py-2 px-4 rounded-xl tracking-wide uppercase shadow-xs">
                        <?= $card['badge_title'] ?>
                    </div>

                    <!-- 2. Main Large Value Display -->
                    <div class="pt-1">
                        <h4 class="text-xl font-black text-gray-900 dark:text-white tracking-tight leading-none">
                            <?= $card['value'] ?>
                        </h4>
                        <p class="text-xs font-semibold text-gray-400 mt-1.5 dark:text-gray-500">
                            <?= $card['label'] ?>
                        </p>
                    </div>
                </div>

                <!-- 3. Bottom Columns Metrics & Progress Bar Layer Split -->
                <div class="pt-3 border-t border-gray-50 flex items-end justify-between gap-4 dark:border-gray-700/50">
                    <!-- Left Details -->
                    <div class="flex flex-col min-w-0">
                        <span class="text-[10px] uppercase font-bold text-gray-300 tracking-wider dark:text-gray-600">Status</span>
                        <span class="text-xs font-medium text-gray-600 truncate dark:text-gray-300 mt-0.5"><?= $card['subtext'] ?></span>
                    </div>

                    <!-- Right Percentage Status Metric with custom colored mini progress bar -->
                    <div class="flex flex-col items-end flex-shrink-0 w-24">
                        <span class="text-xs font-extrabold text-gray-900 dark:text-white mb-1.5 leading-none"><?= $card['percentage'] ?>%</span>
                        <div class="w-full bg-gray-100 rounded-full h-2 overflow-hidden dark:bg-gray-700 shadow-inner">
                            <div class="<?= $card['bar_color'] ?> h-full rounded-full transition-all duration-500"
                                style="width: <?= $card['percentage'] ?>%"></div>
                        </div>
                    </div>
                </div>

            </div>
        <?php endforeach; ?>
    </div>

    <!-- Recent Activity -->
    <div class="dh-card fade-in p-6" style="animation-delay: 0.15s">
        <div class="flex items-center justify-between mb-5">
            <div class="flex items-center gap-3">
                <div class="w-10 h-10 rounded-xl bg-blue-50 flex items-center justify-center dark:bg-blue-900/20">
                    <i class="fas fa-clock-rotate-left text-blue-500"></i>
                </div>
                <div>
                    <h3 class="text-base font-bold text-gray-900 dark:text-white m-0">Recent Activity</h3>
                    <p class="text-xs text-gray-400 dark:text-slate-500 m-0">Your latest wallet transactions</p>
                </div>
            </div>
            <a href="wallet_history.php" class="text-xs font-semibold text-blue-600 no-underline hover:underline">View All →</a>
        </div>

        <?php if (!empty($groupedHistory)): ?>
            <?php foreach ($groupedHistory as $dateLabel => $items): ?>
                <div class="mb-4">
                    <p class="text-[11px] font-bold uppercase tracking-widest text-gray-400 dark:text-slate-500 mb-2 px-4"><?= htmlspecialchars($dateLabel) ?></p>
                    <div class="divide-y divide-gray-100 dark:divide-slate-700">
                        <?php foreach ($items as $item): ?>
                            <?php render_wallet_history_item($item); ?>
                        <?php endforeach; ?>
                    </div>
                </div>
            <?php endforeach; ?>
        <?php else: ?>
            <div class="text-center py-12">
                <div class="w-16 h-16 rounded-2xl bg-gray-100 dark:bg-slate-700 flex items-center justify-center mx-auto mb-4">
                    <i class="fas fa-receipt text-2xl text-gray-300 dark:text-slate-500"></i>
                </div>
                <p class="text-gray-500 dark:text-slate-400 text-sm font-medium mb-1">No transactions yet</p>
                <p class="text-gray-400 dark:text-slate-500 text-xs">Top up your wallet to get started</p>
            </div>
        <?php endif; ?>
    </div>

    <!-- Top Up Modal -->
    <div id="topUpModal" class="fixed inset-0 z-50  hidden ">
        <div class="modal-overlay absolute inset-0" onclick="closeTopUpModal()"></div>
        <div class="absolute inset-0 flex items-center justify-center p-4">
            <div class="bg-white dark:bg-slate-800 rounded-2xl shadow-2xl w-full max-w-md relative z-10 slide-down">
                <div class="p-6 border-b border-gray-100 dark:border-slate-700">
                    <div class="flex items-center justify-between">
                        <div>
                            <h3 class="text-lg font-bold text-gray-900 dark:text-white m-0">Top Up Wallet</h3>
                            <p class="text-xs text-gray-400 dark:text-slate-500 m-0 mt-1">Add funds using Demo Wallet</p>
                        </div>
                        <button onclick="closeTopUpModal()" class="w-8 h-8 rounded-lg bg-gray-100 dark:bg-slate-700 flex items-center justify-center text-gray-400 hover:text-gray-600 dark:hover:text-slate-300">
                            <i class="fas fa-times text-sm"></i>
                        </button>
                    </div>
                </div>

                <form id="topUpForm" onsubmit="return processTopUp(event)">
                    <div class="p-4 space-y-5 max-h-[60vh] overflow-y-auto border-b border-gray-100 dark:border-slate-700">
                        <!-- Current Balance Display -->
                        <div class="bg-gray-50 dark:bg-slate-700/50 rounded-xl p-4 text-center">
                            <p class="text-xs text-gray-400 dark:text-slate-500 m-0">Current Balance</p>
                            <p class="text-2xl font-black text-gray-900 dark:text-white m-0 mt-1" id="modalCurrentBalance">$<?= number_format($stats['balance'], 2) ?></p>
                        </div>

                        <!-- Amount Input -->
                        <div>
                            <label class="block text-xs font-semibold text-gray-700 dark:text-slate-300 mb-2">Amount</label>
                            <div class="relative">
                                <span class="absolute left-4 top-1/2 -translate-y-1/2 text-gray-400 dark:text-slate-500 font-bold text-lg">$</span>
                                <input type="number" name="amount" id="topUpAmount" required min="10" max="10000" step="0.01"
                                    placeholder="0.00"
                                    class="w-full pl-9 pr-4 py-3 rounded-xl border border-gray-200 dark:border-slate-600 bg-white dark:bg-slate-700 text-gray-900 dark:text-white text-lg font-bold outline-none focus:border-blue-500 focus:ring-2 focus:ring-blue-100 dark:focus:ring-blue-900/30 transition-all"
                                    oninput="validateTopUpAmount(this)">
                            </div>
                            <p class="text-[11px] text-gray-400 dark:text-slate-500 mt-1.5 m-0">Min: $10.00 — Max: $10,000.00</p>
                        </div>

                        <!-- Quick Amounts -->
                        <div>
                            <label class="block text-xs font-semibold text-gray-700 dark:text-slate-300 mb-2">Quick Amount</label>
                            <div class="grid grid-cols-4 gap-2">
                                <button type="button" class="quick-amount-btn" onclick="setQuickAmount(100)">$100</button>
                                <button type="button" class="quick-amount-btn" onclick="setQuickAmount(250)">$250</button>
                                <button type="button" class="quick-amount-btn" onclick="setQuickAmount(500)">$500</button>
                                <button type="button" class="quick-amount-btn" onclick="setQuickAmount(1000)">$1,000</button>
                            </div>
                        </div>

                        <!-- Payment Method -->
                        <div>
                            <label class="block text-xs font-semibold text-gray-700 dark:text-slate-300 mb-2">Payment Method</label>
                            <div class="space-y-2">
                                <div class="payment-method-option selected" onclick="selectPaymentMethod(this, 'demo_wallet')">
                                    <div class="w-5 h-5 rounded-full border-2 border-blue-500 flex items-center justify-center">
                                        <div class="w-2.5 h-2.5 rounded-full bg-blue-500"></div>
                                    </div>
                                    <div class="flex items-center gap-2 flex-1">
                                        <i class="fas fa-wallet text-blue-500"></i>
                                        <span class="text-sm font-semibold text-gray-900 dark:text-white">Demo Wallet</span>
                                    </div>
                                    <span class="text-[10px] font-bold text-emerald-600 bg-emerald-50 px-2 py-0.5 rounded-md dark:bg-emerald-900/20 dark:text-emerald-400">Available</span>
                                </div>
                                <div class="payment-method-option opacity-50 cursor-not-allowed">
                                    <div class="w-5 h-5 rounded-full border-2 border-gray-300 dark:border-slate-600"></div>
                                    <div class="flex items-center gap-2 flex-1">
                                        <i class="fab fa-stripe text-gray-400"></i>
                                        <span class="text-sm font-semibold text-gray-500 dark:text-slate-400">Stripe</span>
                                    </div>
                                    <span class="text-[10px] font-bold text-gray-400 bg-gray-100 px-2 py-0.5 rounded-md dark:bg-slate-700 dark:text-slate-500">Coming Soon</span>
                                </div>
                                <div class="payment-method-option opacity-50 cursor-not-allowed">
                                    <div class="w-5 h-5 rounded-full border-2 border-gray-300 dark:border-slate-600"></div>
                                    <div class="flex items-center gap-2 flex-1">
                                        <i class="fab fa-paypal text-gray-400"></i>
                                        <span class="text-sm font-semibold text-gray-500 dark:text-slate-400">PayPal</span>
                                    </div>
                                    <span class="text-[10px] font-bold text-gray-400 bg-gray-100 px-2 py-0.5 rounded-md dark:bg-slate-700 dark:text-slate-500">Coming Soon</span>
                                </div>
                                <div class="payment-method-option opacity-50 cursor-not-allowed">
                                    <div class="w-5 h-5 rounded-full border-2 border-gray-300 dark:border-slate-600"></div>
                                    <div class="flex items-center gap-2 flex-1">
                                        <i class="fas fa-university text-gray-400"></i>
                                        <span class="text-sm font-semibold text-gray-500 dark:text-slate-400">Bank Transfer</span>
                                    </div>
                                    <span class="text-[10px] font-bold text-gray-400 bg-gray-100 px-2 py-0.5 rounded-md dark:bg-slate-700 dark:text-slate-500">Coming Soon</span>
                                </div>
                            </div>
                            <input type="hidden" name="payment_method" id="paymentMethod" value="demo_wallet">
                        </div>

                        <?= csrf_field() ?>
                    </div>

                    <div class="px-6 pb-6 flex gap-3">
                        <button type="button" onclick="closeTopUpModal()" class="flex-1 px-4 py-3 rounded-xl border border-gray-200 dark:border-slate-600 text-sm font-semibold text-gray-600 dark:text-slate-300 hover:bg-gray-50 dark:hover:bg-slate-700 transition-all">
                            Cancel
                        </button>
                        <button type="submit" id="topUpBtn" class="flex-1 px-4 py-3 rounded-xl bg-gradient-to-r from-blue-600 to-teal-500 text-white text-sm font-bold shadow-lg shadow-blue-500/25 hover:shadow-xl hover:shadow-blue-500/30 transition-all disabled:opacity-50 disabled:cursor-not-allowed">
                            <span id="topUpBtnText">Continue</span>
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Success Modal -->
    <div id="successModal" class="fixed inset-0 z-50 hidden">
        <div class="modal-overlay absolute inset-0" onclick="closeSuccessModal()"></div>
        <div class="absolute inset-0 flex items-center justify-center p-4">
            <div class="bg-white dark:bg-slate-800 rounded-2xl shadow-2xl w-full max-w-sm relative z-10 slide-down">
                <div class="p-8 text-center">
                    <div class="w-20 h-20 rounded-full bg-emerald-100 dark:bg-emerald-900/30 flex items-center justify-center mx-auto mb-5">
                        <i class="fas fa-check text-3xl text-emerald-500"></i>
                    </div>
                    <h3 class="text-xl font-bold text-gray-900 dark:text-white mb-2">Wallet Updated!</h3>
                    <p class="text-sm text-gray-500 dark:text-slate-400 mb-1">
                        <span class="text-emerald-600 dark:text-emerald-400 font-bold" id="successAmount">+$500.00</span>
                    </p>
                    <p class="text-xs text-gray-400 dark:text-slate-500 mb-1">Current Balance</p>
                    <p class="text-2xl font-black text-gray-900 dark:text-white" id="successBalance">$2,500.00</p>
                </div>
                <div class="px-8 pb-8">
                    <button onclick="closeSuccessModal()" class="w-full px-4 py-3 rounded-xl bg-gradient-to-r from-blue-600 to-teal-500 text-white text-sm font-bold shadow-lg shadow-blue-500/25 hover:shadow-xl transition-all">
                        Continue
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- Loading Overlay -->
    <div id="loadingOverlay" class="fixed inset-0 z-[60] hidden modal-overlay flex items-center justify-center">
        <div class="bg-white dark:bg-slate-800 rounded-2xl p-8 shadow-2xl text-center">
            <div class="w-12 h-12 border-4 border-blue-200 border-t-blue-600 rounded-full animate-spin mx-auto mb-4"></div>
            <p class="text-sm font-semibold text-gray-700 dark:text-slate-300">Processing...</p>
        </div>
    </div>
</main>
<?php $conn->close(); ?>

<script src="/finalproject/assets/js/wallet.js"></script>
<script>
    var CSRF_TOKEN = '<?= generate_csrf_token() ?>';
    var BASE_URL = '/finalproject';
</script>

<?php require_once __DIR__ . '/../includes/client_footer.php'; ?>