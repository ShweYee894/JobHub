<?php

/**
 * Top Up Wallet Page – Standalone
 * Alternative top-up page with full form.
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

$balance = get_wallet_balance($conn, $userId);

$pageTitle = 'Top Up Wallet';
$pageSubtitle = 'Add funds to your wallet';
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
        padding: 12px 20px;
        font-weight: 600;
        font-size: 15px;
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
    }
    html.dark .quick-amount-btn.active {
        border-color: #3b82f6;
        background: #3b82f6;
        color: white;
    }
</style>

    <?php display_flash('success'); ?>
    <?php display_flash('error'); ?>
<main class="max-w-7xl mx-auto px-4 sm:px-6 py-8">
    <div class="max-w-lg mx-auto">
        <!-- Balance Card -->
        <div class="bg-white/60 border border-gray-200 rounded-2xl p-4 text-center mb-6">
            <i data-lucide="wallet" class="w-8 h-8 text-blue-600 dark:text-blue-400 mx-auto mb-4"></i>
            <p class="text-sm text-gray-400 dark:text-slate-500 m-0">Current Balance</p>
            <p class="text-3xl font-black text-gray-900 dark:text-white m-0 mt-1" id="currentBalanceDisplay">
                <?= wallet_format_currency($balance) ?>
            </p>
        </div>

        <!-- Top Up Form -->
        <div class="dh-card p-6">
            <h3 class="text-lg font-bold text-gray-900 dark:text-white mb-5">Add Funds</h3>
            <form action="process_topup.php" method="POST">
                <?= csrf_field() ?>

                <div class="space-y-5">
                    <!-- Amount -->
                    <div>
                        <label class="block text-xs font-semibold text-gray-700 dark:text-slate-300 mb-2">Amount</label>
                        <div class="relative">
                            <span class="absolute left-4 top-1/2 -translate-y-1/2 text-gray-400 dark:text-slate-500 font-bold text-lg">$</span>
                            <input type="number" name="amount" required min="10" max="10000" step="0.01"
                                placeholder="0.00"
                                class="w-full pl-9 pr-4 py-3 rounded-xl border border-gray-200 dark:border-slate-600 bg-white dark:bg-slate-700 text-gray-900 dark:text-white text-lg font-bold outline-none focus:border-blue-500 focus:ring-2 focus:ring-blue-100 dark:focus:ring-blue-900/30 transition-all">
                        </div>
                        <p class="text-[11px] text-gray-400 dark:text-slate-500 mt-1.5 m-0">Min: $10.00 — Max: $10,000.00</p>
                    </div>

                    <!-- Quick Amounts -->
                    <div>
                        <label class="block text-xs font-semibold text-gray-700 dark:text-slate-300 mb-2">Quick Amount</label>
                        <div class="grid grid-cols-4 gap-2">
                            <button type="button" class="quick-amount-btn" onclick="selectQuick(this, 100)">$100</button>
                            <button type="button" class="quick-amount-btn" onclick="selectQuick(this, 250)">$250</button>
                            <button type="button" class="quick-amount-btn" onclick="selectQuick(this, 500)">$500</button>
                            <button type="button" class="quick-amount-btn" onclick="selectQuick(this, 1000)">$1,000</button>
                        </div>
                    </div>

                    <!-- Payment Method -->
                    <div>
                        <label class="block text-xs font-semibold text-gray-700 dark:text-slate-300 mb-2">Payment Method</label>
                        <div class="space-y-2">
                            <label class="flex items-center gap-3 p-3 rounded-xl border-2 border-blue-500 bg-blue-50 dark:bg-blue-900/20 cursor-pointer">
                                <input type="radio" name="payment_method" value="demo_wallet" checked class="accent-blue-600">
                                <i data-lucide="wallet" class="w-4 h-4 text-blue-500"></i>
                                <span class="text-sm font-semibold text-gray-900 dark:text-white">Demo Wallet</span>
                                <span class="ml-auto text-[10px] font-bold text-emerald-600 bg-emerald-50 px-2 py-0.5 rounded-md dark:bg-emerald-900/20 dark:text-emerald-400">Available</span>
                            </label>
                            <div class="flex items-center gap-3 p-3 rounded-xl border border-gray-200 dark:border-slate-600 opacity-50">
                                <input type="radio" disabled class="accent-gray-400">
                                <svg class="w-4 h-4 text-gray-400" fill="currentColor" viewBox="0 0 24 24"><path d="M13.976 9.15c-2.172-.806-3.356-1.426-3.356-2.409 0-.831.683-1.305 1.901-1.305 2.227 0 4.515.858 6.09 1.631l.89-5.494C18.252.975 15.697 0 12.165 0 9.667 0 7.589.654 6.104 1.872 4.56 3.147 3.757 4.992 3.757 7.218c0 4.039 2.467 5.76 6.476 7.219 2.585.92 3.445 1.574 3.445 2.583 0 .98-.84 1.545-2.354 1.545-1.875 0-4.965-.921-6.99-2.109l-.9 5.555C5.175 22.99 8.385 24 11.714 24c2.641 0 4.843-.624 6.328-1.813 1.664-1.305 2.525-3.236 2.525-5.732 0-4.128-2.524-5.851-6.591-7.305z"/></svg>
                                <span class="text-sm font-semibold text-gray-500 dark:text-slate-400">Stripe</span>
                                <span class="ml-auto text-[10px] font-bold text-gray-400 bg-gray-100 px-2 py-0.5 rounded-md dark:bg-slate-700 dark:text-slate-500">Coming Soon</span>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="flex gap-3 mt-6">
                    <a href="wallet.php" class="flex-1 px-4 py-3 rounded-xl border border-gray-200 dark:border-slate-600 text-sm font-semibold text-gray-600 dark:text-slate-300 hover:bg-gray-50 dark:hover:bg-slate-700 transition-all text-center no-underline">
                        Cancel
                    </a>
                    <button type="submit" class="flex-1 px-4 py-3 rounded-xl bg-indigo-600 hover:bg-indigo-700 text-white text-sm font-bold transition-all">
                        <i data-lucide="plus" class="w-4 h-4 inline-block mr-1 -mt-0.5"></i>Top Up
                    </button>
                </div>
            </form>
        </div>
    </div>
</main>
<?php $conn->close(); ?>

<script>
function selectQuick(btn, amount) {
    document.querySelectorAll('.quick-amount-btn').forEach(function(b) { b.classList.remove('active'); });
    btn.classList.add('active');
    btn.closest('form').querySelector('input[name="amount"]').value = amount.toFixed(2);
}
</script>

<?php require_once __DIR__ . '/../includes/client_footer.php'; ?>
