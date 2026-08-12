<?php

/**
 * Wallet History Page – Client
 * Premium fintech ledger with date grouping and transaction rows.
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

<style>
    @import url('https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800;900&display=swap');

    *, *::before, *::after { box-sizing: border-box; }
    .wh-page {
        font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif;
        background: #f7f8fc;
        min-height: 100vh;
        color: #1e293b;
        -webkit-font-smoothing: antialiased;
    }
    .dark .wh-page { background: #0c101d; color: #e2e8f0; }

    /* ── Balance Card ────────────────────────────────────── */
    .wh-hero {
        position: relative;
    }

    /* ── Ledger Card ─────────────────────────────────────── */
    .wh-ledger {
        background: #ffffff;
        border: 1px solid #e8ecf1;
        border-radius: 20px;
        box-shadow: 0 1px 2px rgba(0,0,0,0.04), 0 4px 16px -2px rgba(0,0,0,0.06);
        overflow: hidden;
    }
    .dark .wh-ledger {
        background: #141927;
        border-color: rgba(255,255,255,0.06);
        box-shadow: 0 1px 2px rgba(0,0,0,0.2), 0 4px 16px -2px rgba(0,0,0,0.3);
    }

    /* ── Table Header ────────────────────────────────────── */
    .wh-table-head {
        display: none;
    }
    @media (min-width: 768px) {
        .wh-table-head {
            display: grid;
            grid-template-columns: 44px 1fr 150px 110px 90px;
            gap: 0;
            align-items: center;
            column-gap: 16px;
            padding: 0 32px;
            height: 44px;
            background: #f9fafb;
            border-bottom: 1px solid #eef1f6;
        }
        .dark .wh-table-head {
            background: rgba(255,255,255,0.02);
            border-bottom-color: rgba(51,65,85,0.3);
        }
    }
    .wh-th {
        font-size: 10px;
        font-weight: 700;
        letter-spacing: 0.08em;
        text-transform: uppercase;
        color: #94a3b8;
        margin: 0;
        user-select: none;
    }
    .wh-th-right { text-align: right; }

    /* ── Date Group Header ───────────────────────────────── */
    .wh-date-label {
        font-size: 11px;
        font-weight: 600;
        letter-spacing: 0.06em;
        text-transform: uppercase;
        color: #94a3b8;
        padding: 20px 0 8px;
        margin: 0;
    }
    .dark .wh-date-label { color: #475569; }

    /* ── Transaction Row ─────────────────────────────────── */
    .wh-txn {
        display: flex;
        align-items: center;
        gap: 14px;
        padding: 12px 0;
        border-bottom: 1px solid #f1f5f9;
        text-decoration: none;
        color: inherit;
        transition: background 0.15s ease;
        cursor: pointer;
        margin: 0 -12px;
        padding-left: 12px;
        padding-right: 12px;
        border-radius: 12px;
    }
    .wh-txn:last-child { border-bottom: none; }
    .wh-txn:hover { background: #f8fafc; }
    .dark .wh-txn { border-bottom-color: rgba(51,65,85,0.25); }
    .dark .wh-txn:hover { background: rgba(255,255,255,0.02); }

    @media (min-width: 768px) {
        .wh-txn {
            display: grid;
            grid-template-columns: 44px 1fr 150px 110px 90px;
            gap: 0;
            align-items: center;
            column-gap: 16px;
        }
    }

    /* ── Icon Box ────────────────────────────────────────── */
    .wh-icon {
        width: 44px;
        height: 44px;
        border-radius: 13px;
        display: flex;
        align-items: center;
        justify-content: center;
        flex-shrink: 0;
        transition: transform 0.2s ease;
    }
    .wh-txn:hover .wh-icon { transform: scale(1.05); }
    .wh-icon svg, .wh-icon i { width: 18px; height: 18px; }

    /* ── Project Name (Primary) ──────────────────────────── */
    .wh-project {
        font-size: 13px;
        font-weight: 600;
        color: #1e293b;
        margin: 0;
        line-height: 1.3;
        white-space: nowrap;
        overflow: hidden;
        text-overflow: ellipsis;
    }
    .dark .wh-project { color: #f1f5f9; }

    /* ── Transaction Type (Secondary) ────────────────────── */
    .wh-txn-type {
        font-size: 11px;
        font-weight: 500;
        color: #94a3b8;
        margin: 2px 0 0;
        line-height: 1.3;
        white-space: nowrap;
        overflow: hidden;
        text-overflow: ellipsis;
    }
    .dark .wh-txn-type { color: #64748b; }

    /* ── Milestone Tag (desktop) ─────────────────────────── */
    .wh-milestone-wrap { display: none; }
    @media (min-width: 768px) {
        .wh-milestone-wrap { display: flex; align-items: center; }
    }
    .wh-milestone-pill {
        display: inline-flex;
        align-items: center;
        gap: 5px;
        padding: 4px 10px;
        border-radius: 8px;
        font-size: 11px;
        font-weight: 600;
        color: #64748b;
        background: #f1f5f9;
        white-space: nowrap;
        max-width: 180px;
        overflow: hidden;
        text-overflow: ellipsis;
    }
    .dark .wh-milestone-pill { color: #94a3b8; background: rgba(51,65,85,0.35); }

    /* ── Amount ──────────────────────────────────────────── */
    .wh-amt-wrap { text-align: right; }
    .wh-amt {
        font-size: 14px;
        font-weight: 700;
        font-variant-numeric: tabular-nums;
        white-space: nowrap;
        letter-spacing: -0.01em;
    }

    /* ── Status Badge ────────────────────────────────────── */
    .wh-badge-wrap { text-align: right; }
    .wh-badge {
        display: inline-flex;
        align-items: center;
        padding: 4px 12px;
        border-radius: 100px;
        font-size: 11px;
        font-weight: 600;
        line-height: 1;
        white-space: nowrap;
        letter-spacing: 0.01em;
    }

    /* ── Pagination ──────────────────────────────────────── */
    .wh-pg {
        width: 36px;
        height: 36px;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        border-radius: 10px;
        font-size: 13px;
        font-weight: 500;
        text-decoration: none;
        transition: all 0.15s ease;
        border: 1px solid transparent;
        color: inherit;
    }
    .wh-pg svg, .wh-pg i { width: 16px; height: 16px; }
    .wh-pg:hover { transform: translateY(-1px); }

    /* ── Empty State ─────────────────────────────────────── */
    .wh-empty {
        padding: 80px 24px;
        text-align: center;
    }

    /* ── Fade In ─────────────────────────────────────────── */
    @keyframes fadeUp {
        from { opacity: 0; transform: translateY(12px); }
        to { opacity: 1; transform: translateY(0); }
    }
    .wh-fade-in {
        animation: fadeUp 0.4s ease-out both;
    }
</style>

<body class="wh-page">
    <?php display_flash('success'); ?>
    <?php display_flash('error'); ?>

    <main class="max-w-7xl mx-auto px-4 sm:px-6 py-8 flex flex-col gap-6">

        <!-- ═══════════════ Balance Card ═══════════════ -->
        <div class="wh-hero wh-fade-in p-6 sm:p-8 rounded-2xl border border-gray-200 dark:border-slate-700 bg-white dark:bg-slate-800">
            <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-5">
                <!-- Left: Balance Data -->
                <div>
                    <div class="flex items-center gap-2 mb-2">
                        <i data-lucide="wallet" class="w-4 h-4 text-slate-400"></i>
                        <span class="text-sm font-medium text-slate-400">Total Balance</span>
                    </div>
                    <p class="text-3xl sm:text-4xl font-semibold text-gray-900 dark:text-white m-0" style="font-variant-numeric: tabular-nums;">
                        <?= wallet_format_currency($balance) ?>
                    </p>
                </div>

                <!-- Right: Actions -->
                <div class="flex items-center gap-3 sm:flex-shrink-0">
                    <a href="wallet.php"
                       class="inline-flex items-center gap-2 px-5 py-3 bg-white hover:bg-gray-50 text-gray-600 hover:text-gray-900 font-semibold rounded-xl border border-gray-200 transition-all text-sm dark:bg-slate-800 dark:text-slate-300 dark:border-slate-600 dark:hover:bg-slate-700 dark:hover:text-white no-underline">
                        <i data-lucide="arrow-left" class="w-4 h-4"></i> Back to Wallet
                    </a>
                    <a href="topup_wallet.php"
                       class="inline-flex items-center gap-2 px-5 py-3 rounded-xl bg-indigo-600 hover:bg-indigo-700 text-white font-bold transition-all text-sm shadow-sm shadow-indigo-600/20 no-underline">
                        <i data-lucide="plus" class="w-4 h-4"></i> Top Up
                    </a>
                </div>
            </div>
        </div>

        <!-- ═══════════════ Transaction Ledger ═══════════════ -->
        <div class="wh-ledger wh-fade-in" style="animation-delay: 0.08s">
            <!-- Section Header -->
            <div class="px-6 sm:px-8 pt-6 pb-4 flex items-center justify-between">
                <div class="flex items-center gap-3">
                    <div class="w-9 h-9 rounded-xl bg-indigo-50 dark:bg-indigo-950/40 flex items-center justify-center">
                        <i data-lucide="clock" class="w-4 h-4 text-indigo-500 dark:text-indigo-400"></i>
                    </div>
                    <div>
                        <h3 class="text-sm font-bold text-gray-900 dark:text-white m-0">Transaction History</h3>
                        <p class="text-[11px] text-slate-400 dark:text-slate-500 m-0"><?= $totalItems ?> transaction<?= $totalItems !== 1 ? 's' : '' ?></p>
                    </div>
                </div>
            </div>

            <!-- Table Header (desktop) -->
            <div class="wh-table-head">
                <span class="wh-th"></span>
                <span class="wh-th">Transaction</span>
                <span class="wh-th">Milestone</span>
                <span class="wh-th wh-th-right">Amount</span>
                <span class="wh-th wh-th-right">Status</span>
            </div>

            <?php if (!empty($groupedHistory)): ?>
                <div class="px-6 sm:px-8 pb-2">
                    <?php foreach ($groupedHistory as $dateLabel => $items): ?>
                        <div class="wh-date-group">
                            <p class="wh-date-label"><?= htmlspecialchars($dateLabel) ?></p>
                            <?php foreach ($items as $item):
                                $dirClass = $item['direction'] === 'credit'
                                    ? 'text-emerald-600 dark:text-emerald-400'
                                    : ($item['direction'] === 'debit' ? 'text-gray-900 dark:text-slate-100' : 'text-gray-600 dark:text-gray-400');
                                $prefix = $item['direction'] === 'credit' ? '+' : '';

                                $iconBg = match ($item['color']) {
                                    'emerald' => 'bg-emerald-50 text-emerald-600 dark:bg-emerald-950/50 dark:text-emerald-400',
                                    'amber'   => 'bg-amber-50 text-amber-600 dark:bg-amber-950/50 dark:text-amber-400',
                                    'blue'    => 'bg-blue-50 text-blue-600 dark:bg-blue-950/50 dark:text-blue-400',
                                    'purple'  => 'bg-purple-50 text-purple-600 dark:bg-purple-950/50 dark:text-purple-400',
                                    'red'     => 'bg-red-50 text-red-500 dark:bg-red-950/50 dark:text-red-400',
                                    default   => 'bg-slate-100 text-slate-400 dark:bg-slate-700/50 dark:text-slate-400',
                                };

                                $badgeClass = match ($item['type']) {
                                    'deposit'        => 'bg-emerald-50 text-emerald-700 dark:bg-emerald-950/40 dark:text-emerald-400',
                                    'escrow_hold'    => 'bg-amber-50 text-amber-700 dark:bg-amber-950/40 dark:text-amber-400',
                                    'escrow_release' => 'bg-blue-50 text-blue-700 dark:bg-blue-950/40 dark:text-blue-400',
                                    'refund'         => 'bg-purple-50 text-purple-700 dark:bg-purple-950/40 dark:text-purple-400',
                                    'credit'         => 'bg-emerald-50 text-emerald-700 dark:bg-emerald-950/40 dark:text-emerald-400',
                                    'withdrawal'     => 'bg-red-50 text-red-600 dark:bg-red-950/40 dark:text-red-400',
                                    default          => 'bg-slate-100 text-slate-600 dark:bg-slate-700/40 dark:text-slate-400',
                                };

                                $badgeLabel = match ($item['type']) {
                                    'deposit'        => 'Deposit',
                                    'escrow_hold'    => 'Held',
                                    'escrow_release' => 'Released',
                                    'refund'         => 'Refund',
                                    'credit'         => 'Credit',
                                    'withdrawal'     => 'Withdrawal',
                                    default          => ucfirst(str_replace('_', ' ', $item['type'])),
                                };

                                $txnIcon = wallet_type_icon($item['type']);
                                $milestoneTitle = $item['milestone_title'] ?? null;
                                $milestoneId = $item['reference_id'] ?? null;
                            ?>
                            <a href="javascript:void(0)" onclick="openDrawer(<?= (int) $item['id'] ?>)" class="wh-txn" data-transaction-type="<?= htmlspecialchars($item['type']) ?>">
                                <!-- Icon -->
                                <div class="wh-icon <?= $iconBg ?>">
                                    <i data-lucide="<?= $txnIcon ?>"></i>
                                </div>

                                <!-- Transaction Name + Milestone -->
                                <div class="min-w-0">
                                    <p class="wh-project truncate"><?= htmlspecialchars($item['label']) ?></p>
                                    <?php if ($milestoneTitle): ?>
                                        <p class="wh-txn-type truncate" title="<?= htmlspecialchars($milestoneTitle) ?>"><?= htmlspecialchars($milestoneTitle) ?></p>
                                    <?php elseif ($milestoneId): ?>
                                        <p class="wh-txn-type truncate">Milestone #<?= (int) $milestoneId ?></p>
                                    <?php else: ?>
                                        <p class="wh-txn-type truncate"><?= htmlspecialchars($item['description'] ?? '') ?></p>
                                    <?php endif; ?>
                                </div>

                                <!-- Milestone (desktop) -->
                                <div class="wh-milestone-wrap min-w-0">
                                    <?php if ($milestoneTitle): ?>
                                        <span class="wh-milestone-pill" title="<?= htmlspecialchars($milestoneTitle) ?>">
                                            <i data-lucide="flag" class="w-3 h-3 shrink-0"></i>
                                            <?= htmlspecialchars($milestoneTitle) ?>
                                        </span>
                                    <?php elseif ($milestoneId): ?>
                                        <span class="wh-milestone-pill">
                                            <i data-lucide="flag" class="w-3 h-3 shrink-0"></i>
                                            Milestone #<?= (int) $milestoneId ?>
                                        </span>
                                    <?php else: ?>
                                        <span class="text-slate-200 dark:text-slate-700">—</span>
                                    <?php endif; ?>
                                </div>

                                <!-- Amount -->
                                <div class="wh-amt-wrap">
                                    <span class="wh-amt <?= $dirClass ?>"><?= $prefix ?><?= wallet_format_currency($item['amount']) ?></span>
                                </div>

                                <!-- Badge -->
                                <div class="wh-badge-wrap">
                                    <span class="wh-badge <?= $badgeClass ?>"><?= $badgeLabel ?></span>
                                </div>
                            </a>
                            <?php endforeach; ?>
                        </div>
                    <?php endforeach; ?>
                </div>

                <!-- Pagination -->
                <?php if ($totalPages > 1): ?>
                    <div class="px-6 sm:px-8 py-5 border-t border-slate-100 dark:border-slate-700/50 flex items-center justify-between">
                        <p class="text-xs text-slate-400 dark:text-slate-500 m-0">
                            Page <span class="font-semibold text-gray-700 dark:text-slate-300"><?= $page ?></span> of <span class="font-semibold text-gray-700 dark:text-slate-300"><?= $totalPages ?></span>
                        </p>
                        <div class="flex items-center gap-1">
                            <?php if ($page > 1): ?>
                                <a href="?page=<?= $page - 1 ?>" class="wh-pg border border-gray-200 dark:border-slate-600 text-gray-500 dark:text-slate-400 hover:bg-gray-50 dark:hover:bg-slate-700">
                                    <i data-lucide="chevron-left"></i>
                                </a>
                            <?php endif; ?>
                            <?php for ($i = max(1, $page - 2); $i <= min($totalPages, $page + 2); $i++): ?>
                                <a href="?page=<?= $i ?>" class="wh-pg <?= $i === $page ? 'bg-gray-900 text-white border-gray-900 dark:bg-indigo-600 dark:text-white dark:border-indigo-600' : 'border border-gray-200 dark:border-slate-600 text-gray-500 dark:text-slate-400 hover:bg-gray-50 dark:hover:bg-slate-700' ?>"><?= $i ?></a>
                            <?php endfor; ?>
                            <?php if ($page < $totalPages): ?>
                                <a href="?page=<?= $page + 1 ?>" class="wh-pg border border-gray-200 dark:border-slate-600 text-gray-500 dark:text-slate-400 hover:bg-gray-50 dark:hover:bg-slate-700">
                                    <i data-lucide="chevron-right"></i>
                                </a>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endif; ?>
            <?php else: ?>
                <div class="wh-empty">
                    <div class="w-16 h-16 rounded-2xl bg-slate-50 dark:bg-slate-700/50 flex items-center justify-center mx-auto mb-4">
                        <i data-lucide="receipt" class="w-7 h-7 text-slate-300 dark:text-slate-500"></i>
                    </div>
                    <p class="text-gray-600 dark:text-slate-400 text-sm font-semibold mb-1 m-0">No transactions yet</p>
                    <p class="text-slate-400 dark:text-slate-500 text-xs m-0">Your wallet history will appear here</p>
                </div>
            <?php endif; ?>
        </div>
    </main>

    <!-- ═══════════════ Transaction Detail Drawer ═══════════════ -->
    <div id="txnDrawer" class="fixed inset-0 z-50 hidden" aria-modal="true" role="dialog">
        <!-- Backdrop -->
        <div id="txnDrawerBackdrop" class="absolute inset-0 bg-slate-900/50 backdrop-blur-sm transition-opacity" onclick="closeDrawer()"></div>

        <!-- Drawer Panel -->
        <div id="txnDrawerPanel" class="absolute inset-y-0 right-0 w-full max-w-md bg-white dark:bg-slate-900 shadow-2xl flex flex-col transform transition-transform duration-300 translate-x-full">
            <!-- Header -->
            <div class="flex items-center justify-between px-5 py-3.5 border-b border-slate-100 dark:border-slate-700/50 flex-shrink-0">
                <div class="flex items-center gap-2">
                    <h2 class="text-sm font-bold text-gray-900 dark:text-white m-0">Transaction Details</h2>
                    <span id="drawerHeaderId" class="text-xs font-normal text-slate-400 dark:text-slate-500">#—</span>
                </div>
                <button onclick="closeDrawer()" class="w-7 h-7 rounded-md flex items-center justify-center text-slate-400 hover:text-gray-900 hover:bg-slate-100 dark:hover:text-white dark:hover:bg-slate-800 transition-colors">
                    <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/></svg>
                </button>
            </div>

            <!-- Scrollable Body -->
            <div id="txnDrawerBody" class="flex-1 overflow-y-auto px-5 py-4 space-y-4">
                <!-- Compact Hero -->
                <div class="bg-slate-50/80 dark:bg-slate-800/50 rounded-xl p-4 border border-slate-100 dark:border-slate-700/50">
                    <p id="drawerAmount" class="text-2xl font-bold text-slate-900 dark:text-white m-0 tabular-nums">$0.00</p>
                    <div class="flex items-center gap-2 mt-1.5">
                        <span id="drawerLabel" class="text-sm text-slate-500 dark:text-slate-400 font-medium">Transaction</span>
                        <span id="drawerBadge" class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium bg-emerald-50 text-emerald-700 border border-emerald-200/60 dark:bg-emerald-950/40 dark:text-emerald-400 dark:border-emerald-800/40">Completed</span>
                    </div>
                </div>

                <!-- Transaction Info -->
                <div>
                    <p class="text-[10px] font-bold uppercase tracking-wider text-slate-400 dark:text-slate-500 mb-2 m-0">Transaction Info</p>
                    <div class="bg-white dark:bg-slate-800/30 rounded-lg border border-slate-100 dark:border-slate-700/50 divide-y divide-slate-100 dark:divide-slate-700/50">
                        <div class="flex justify-between items-center px-3 py-2.5">
                            <span class="text-xs text-slate-400 dark:text-slate-500">Date & Time</span>
                            <span id="drawerDate" class="text-xs font-semibold text-slate-900 dark:text-white">—</span>
                        </div>
                        <div class="flex justify-between items-center px-3 py-2.5">
                            <span class="text-xs text-slate-400 dark:text-slate-500">Type</span>
                            <span id="drawerType" class="text-xs font-semibold text-slate-900 dark:text-white">—</span>
                        </div>
                        <div class="flex justify-between items-center px-3 py-2.5">
                            <span class="text-xs text-slate-400 dark:text-slate-500">Balance After</span>
                            <span id="drawerBalance" class="text-xs font-semibold text-slate-900 dark:text-white tabular-nums">—</span>
                        </div>
                    </div>
                </div>

                <!-- Project Info -->
                <div id="drawerProjectSection" class="hidden">
                    <p class="text-[10px] font-bold uppercase tracking-wider text-slate-400 dark:text-slate-500 mb-2 m-0">Project Info</p>
                    <div class="bg-white dark:bg-slate-800/30 rounded-lg border border-slate-100 dark:border-slate-700/50 divide-y divide-slate-100 dark:divide-slate-700/50">
                        <div id="drawerProjectRow" class="flex justify-between items-center px-3 py-2.5 hidden">
                            <span class="text-xs text-slate-400 dark:text-slate-500">Project</span>
                            <span id="drawerProject" class="text-xs font-semibold text-slate-900 dark:text-white text-right max-w-[60%] truncate">—</span>
                        </div>
                        <div id="drawerMilestoneRow" class="flex justify-between items-center px-3 py-2.5 hidden">
                            <span class="text-xs text-slate-400 dark:text-slate-500">Milestone</span>
                            <span id="drawerMilestone" class="text-xs font-semibold text-slate-900 dark:text-white text-right max-w-[60%] truncate">—</span>
                        </div>
                        <div id="drawerMilestoneAmtRow" class="flex justify-between items-center px-3 py-2.5 hidden">
                            <span class="text-xs text-slate-400 dark:text-slate-500">Milestone Amount</span>
                            <span id="drawerMilestoneAmt" class="text-xs font-semibold text-slate-900 dark:text-white tabular-nums">—</span>
                        </div>
                        <div id="drawerDueDateRow" class="flex justify-between items-center px-3 py-2.5 hidden">
                            <span class="text-xs text-slate-400 dark:text-slate-500">Due Date</span>
                            <span id="drawerDueDate" class="text-xs font-semibold text-slate-900 dark:text-white">—</span>
                        </div>
                    </div>
                </div>

                <!-- Payment Info -->
                <div id="drawerPaymentSection" class="hidden">
                    <p class="text-[10px] font-bold uppercase tracking-wider text-slate-400 dark:text-slate-500 mb-2 m-0">Payment Info</p>
                    <div class="bg-white dark:bg-slate-800/30 rounded-lg border border-slate-100 dark:border-slate-700/50 divide-y divide-slate-100 dark:divide-slate-700/50">
                        <div class="flex justify-between items-center px-3 py-2.5">
                            <span class="text-xs text-slate-400 dark:text-slate-500">Payment Method</span>
                            <span id="drawerPaymentMethod" class="text-xs font-semibold text-slate-900 dark:text-white">—</span>
                        </div>
                        <div class="flex justify-between items-center px-3 py-2.5">
                            <span class="text-xs text-slate-400 dark:text-slate-500">Gross Amount</span>
                            <span id="drawerGrossAmount" class="text-xs font-semibold text-slate-900 dark:text-white tabular-nums">—</span>
                        </div>
                        <div class="flex justify-between items-center px-3 py-2.5">
                            <span class="text-xs text-slate-400 dark:text-slate-500">Platform Fee</span>
                            <span id="drawerPlatformFee" class="text-xs font-semibold text-slate-400 dark:text-slate-500 tabular-nums">—</span>
                        </div>
                        <div class="flex justify-between items-center px-3 py-2.5">
                            <span class="text-xs text-slate-400 dark:text-slate-500">Freelancer Net</span>
                            <span id="drawerFreelancerNet" class="text-xs font-bold text-slate-900 dark:text-white tabular-nums">—</span>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Sticky Footer -->
            <div class="px-5 py-3.5 border-t border-slate-100 dark:border-slate-700/50 bg-white dark:bg-slate-900 flex-shrink-0">
                <button onclick="downloadReceipt()" class="w-full py-2.5 px-4 rounded-lg bg-indigo-600 hover:bg-indigo-700 text-white text-sm font-semibold transition-colors mb-2">
                    Download Receipt
                </button>
                <a href="disputes.php" class="block text-center text-xs font-medium text-slate-400 hover:text-indigo-600 dark:hover:text-indigo-400 transition-colors no-underline">
                    Contact Support
                </a>
            </div>
        </div>
    </div>

    <script src="/jobhub/assets/js/receipt.js"></script>
    <script>
    let currentTxnId = null;
    let currentTxnData = null;

    function openDrawer(txnId) {
        currentTxnId = txnId;
        currentTxnData = null;
        const drawer = document.getElementById('txnDrawer');
        const panel = document.getElementById('txnDrawerPanel');
        const backdrop = document.getElementById('txnDrawerBackdrop');

        drawer.classList.remove('hidden');
        document.body.style.overflow = 'hidden';

        requestAnimationFrame(() => {
            backdrop.style.opacity = '1';
            panel.style.transform = 'translateX(0)';
        });

        fetchTransactionDetails(txnId);
    }

    function closeDrawer() {
        const panel = document.getElementById('txnDrawerPanel');
        const backdrop = document.getElementById('txnDrawerBackdrop');

        panel.style.transform = 'translateX(100%)';
        backdrop.style.opacity = '0';

        setTimeout(() => {
            document.getElementById('txnDrawer').classList.add('hidden');
            document.body.style.overflow = '';
        }, 300);
    }

    async function fetchTransactionDetails(txnId) {
        try {
            const resp = await fetch('api/wallet_detail.php?id=' + txnId);
            if (!resp.ok) throw new Error('Failed to fetch');
            const data = await resp.json();
            if (data.success) {
                currentTxnData = data;
                populateDrawer(data);
            }
        } catch (e) {
            window.location.href = 'wallet_history_detail.php?id=' + txnId;
        }
    }

    function populateDrawer(data) {
        const t = data.transaction;
        const p = data.payment || {};
        const m = data.milestone || {};
        const j = data.job || {};

        const dirClass = t.direction === 'credit'
            ? 'text-emerald-600 dark:text-emerald-400'
            : 'text-slate-900 dark:text-white';
        const prefix = t.direction === 'credit' ? '+' : '';

        document.getElementById('drawerHeaderId').textContent = '#' + t.id;
        document.getElementById('drawerAmount').className = 'text-2xl font-bold m-0 tabular-nums ' + dirClass;
        document.getElementById('drawerAmount').textContent = prefix + '$' + parseFloat(t.amount).toFixed(2);
        document.getElementById('drawerLabel').textContent = t.label;
        document.getElementById('drawerDate').textContent = t.dateFormatted;
        document.getElementById('drawerType').textContent = t.label;
        document.getElementById('drawerBalance').textContent = '$' + parseFloat(t.balance_after).toFixed(2);

        const badgeEl = document.getElementById('drawerBadge');
        const badgeClasses = {
            'deposit': 'bg-emerald-50 text-emerald-700 border-emerald-200/60 dark:bg-emerald-950/40 dark:text-emerald-400 dark:border-emerald-800/40',
            'escrow_hold': 'bg-amber-50 text-amber-700 border-amber-200/60 dark:bg-amber-950/40 dark:text-amber-400 dark:border-amber-800/40',
            'escrow_release': 'bg-blue-50 text-blue-700 border-blue-200/60 dark:bg-blue-950/40 dark:text-blue-400 dark:border-blue-800/40',
            'refund': 'bg-purple-50 text-purple-700 border-purple-200/60 dark:bg-purple-950/40 dark:text-purple-400 dark:border-purple-800/40',
            'credit': 'bg-emerald-50 text-emerald-700 border-emerald-200/60 dark:bg-emerald-950/40 dark:text-emerald-400 dark:border-emerald-800/40',
            'withdrawal': 'bg-red-50 text-red-600 border-red-200/60 dark:bg-red-950/40 dark:text-red-400 dark:border-red-800/40'
        };
        badgeEl.className = 'inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium border ' + (badgeClasses[t.type] || 'bg-slate-100 text-slate-600 border-slate-200/60');
        badgeEl.textContent = t.displayStatus;

        const projSection = document.getElementById('drawerProjectSection');
        if (m.title || j.title) {
            projSection.classList.remove('hidden');
            if (j.title) {
                document.getElementById('drawerProjectRow').classList.remove('hidden');
                document.getElementById('drawerProject').textContent = j.title;
            }
            if (m.title) {
                document.getElementById('drawerMilestoneRow').classList.remove('hidden');
                document.getElementById('drawerMilestone').textContent = m.title;
            }
            if (m.amount) {
                document.getElementById('drawerMilestoneAmtRow').classList.remove('hidden');
                document.getElementById('drawerMilestoneAmt').textContent = '$' + parseFloat(m.amount).toFixed(2);
            }
            if (m.due_date) {
                document.getElementById('drawerDueDateRow').classList.remove('hidden');
                document.getElementById('drawerDueDate').textContent = m.due_date;
            }
        }

        const paySection = document.getElementById('drawerPaymentSection');
        if (p.payment_method) {
            paySection.classList.remove('hidden');
            document.getElementById('drawerPaymentMethod').textContent = p.payment_method.charAt(0).toUpperCase() + p.payment_method.slice(1);
            document.getElementById('drawerGrossAmount').textContent = '$' + parseFloat(p.total_amount).toFixed(2);
            document.getElementById('drawerPlatformFee').textContent = '-$' + parseFloat(p.platform_fee).toFixed(2);
            document.getElementById('drawerFreelancerNet').textContent = '$' + parseFloat(p.freelancer_net).toFixed(2);
        }
    }

    document.addEventListener('keydown', (e) => {
        if (e.key === 'Escape') closeDrawer();
    });
    </script>

<?php $conn->close(); ?>
<?php require_once __DIR__ . '/../includes/client_footer.php'; ?>
