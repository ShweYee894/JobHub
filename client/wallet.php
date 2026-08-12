<?php

/**
 * Client Wallet Page – JobHub
 * Premium FinTech-style wallet with balance card, top-up, and history.
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

// Pagination
$page = max(1, intval($_GET['page'] ?? 1));
$perPage = 20;
$offset = ($page - 1) * $perPage;

// Get wallet history with pagination
$historyData = get_wallet_history($conn, $userId, $perPage, $offset);
$totalItems = $historyData['total'];
$totalPages = max(1, (int) ceil($totalItems / $perPage));
$groupedHistory = wallet_group_history_by_date($historyData['history']);

$wallet_stats = [
    [
        'badge_title' => 'Available Funds',
        'value' => '$' . number_format($stats['balance'], 2),
        'label' => 'Current Balance',
        'subtext' => 'Ready to use',
        'icon' => 'wallet',
        'icon_bg' => 'bg-indigo-50 dark:bg-indigo-900/20',
        'icon_color' => 'text-indigo-600 dark:text-indigo-400',
        'status_label' => 'Active',
        'status_dot' => 'bg-indigo-500',
        'delay' => 0,
    ],
    [
        'badge_title' => 'Held for Milestones',
        'value' => '$' . number_format($stats['escrow_amount'], 2),
        'label' => 'In Escrow',
        'subtext' => 'Security locked',
        'icon' => 'shield-check',
        'icon_bg' => 'bg-amber-50 dark:bg-amber-900/20',
        'icon_color' => 'text-amber-600 dark:text-amber-400',
        'status_label' => 'Escrow Secured',
        'status_dot' => 'bg-amber-500',
        'delay' => 1,
    ],
    [
        'badge_title' => 'Completed Payments',
        'value' => '$' . number_format($stats['completed_payments'], 2),
        'label' => 'Total Spent',
        'subtext' => 'All-time history',
        'icon' => 'check-circle',
        'icon_bg' => 'bg-emerald-50 dark:bg-emerald-900/20',
        'icon_color' => 'text-emerald-600 dark:text-emerald-400',
        'status_label' => 'All Settled',
        'status_dot' => 'bg-emerald-400',
        'delay' => 2,
    ],
    [
        'badge_title' => 'Total Deposits',
        'value' => $stats['recent_topups'],
        'label' => 'Top Ups',
        'subtext' => 'External funding',
        'icon' => 'arrow-down-circle',
        'icon_bg' => 'bg-blue-50 dark:bg-blue-900/20',
        'icon_color' => 'text-blue-600 dark:text-blue-400',
        'status_label' => 'Funded',
        'status_dot' => 'bg-blue-400',
        'delay' => 3,
    ],
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
    /* ══════════════════════════════════════════════════════════════
       Premium Wallet – Stripe / Wise Inspired Fintech Dashboard
       ══════════════════════════════════════════════════════════════ */
    @import url('https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800;900&display=swap');

    .wk-page {
        font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif;
        background: #f7f8fc;
        min-height: 100vh;
    }
    .dark .wk-page { background: #0c101d; }

    /* ── Hero Balance Card ─────────────────────────────────── */
    .wk-hero {
        background: linear-gradient(135deg, #ffffff 0%, #f8faff 100%);
        border: 1px solid #e8ecf4;
        box-shadow: 0 1px 3px rgba(0,0,0,0.04), 0 8px 40px -12px rgba(99,102,241,0.12);
        position: relative;
        overflow: hidden;
    }
    .wk-hero::before {
        content: '';
        position: absolute;
        top: -50%;
        right: -10%;
        width: 350px;
        height: 350px;
        border-radius: 50%;
        background: radial-gradient(circle, rgba(99,102,241,0.06) 0%, transparent 70%);
        pointer-events: none;
    }
    .wk-hero::after {
        content: '';
        position: absolute;
        bottom: -40%;
        left: 20%;
        width: 250px;
        height: 250px;
        border-radius: 50%;
        background: radial-gradient(circle, rgba(16,185,129,0.04) 0%, transparent 70%);
        pointer-events: none;
    }
    .dark .wk-hero {
        background: linear-gradient(135deg, #161b2e 0%, #0f1525 100%);
        border-color: rgba(255,255,255,0.06);
    }

    /* ── Stat Cards ────────────────────────────────────────── */
    .wk-stat {
        background: #fff;
        border: 1px solid #eef1f6;
        border-radius: 16px;
        padding: 20px;
        position: relative;
        overflow: hidden;
        transition: all 0.25s cubic-bezier(0.4, 0, 0.2, 1);
    }
    .wk-stat:hover {
        border-color: rgba(99,102,241,0.15);
        box-shadow: 0 8px 30px -8px rgba(99,102,241,0.1);
        transform: translateY(-2px);
    }
    .dark .wk-stat {
        background: #151b2e;
        border-color: rgba(255,255,255,0.06);
    }
    .dark .wk-stat:hover {
        border-color: rgba(99,102,241,0.25);
        box-shadow: 0 8px 30px -8px rgba(0,0,0,0.3);
    }

    /* Sparkline decoration */
    .wk-spark {
        position: absolute;
        bottom: 0;
        right: 0;
        width: 100px;
        height: 50px;
        opacity: 0.12;
        pointer-events: none;
    }

    /* ── Transaction Section ───────────────────────────────── */
    .wk-txn-section {
        background: #fff;
        border: 1px solid #eef1f6;
        box-shadow: 0 1px 3px rgba(0,0,0,0.04), 0 8px 40px -12px rgba(0,0,0,0.06);
        border-radius: 16px;
    }
    .dark .wk-txn-section {
        background: #151b2e;
        border-color: rgba(255,255,255,0.06);
    }

    /* ── Table Header ──────────────────────────────────────── */
    .wk-table-head {
        display: none;
    }
    @media (min-width: 768px) {
        .wk-table-head {
            display: grid;
            grid-template-columns: 42px 2fr 1fr 110px 100px;
            gap: 0;
            align-items: center;
            column-gap: 14px;
            padding: 10px 32px;
            background: #f9fafb;
            border-bottom: 1px solid #eef1f6;
        }
        .dark .wk-table-head {
            background: rgba(255,255,255,0.02);
            border-bottom-color: rgba(51,65,85,0.3);
        }
    }
    .wk-th {
        font-size: 10px;
        font-weight: 700;
        letter-spacing: 0.08em;
        text-transform: uppercase;
        color: #94a3b8;
        margin: 0;
    }
    .wk-th-right { text-align: right; }

    /* ── Filter Bar ────────────────────────────────────────── */
    .wk-filter {
        background: rgba(248,250,252,0.8);
        backdrop-filter: blur(12px);
        -webkit-backdrop-filter: blur(12px);
        border-top: 1px solid rgba(0,0,0,0.03);
        border-bottom: 1px solid rgba(0,0,0,0.03);
    }
    .dark .wk-filter {
        background: rgba(15,21,37,0.8);
        border-color: rgba(255,255,255,0.04);
    }

    /* ── Segmented Control ─────────────────────────────────── */
    .wk-seg {
        display: inline-flex;
        align-items: center;
        background: #f1f5f9;
        border-radius: 12px;
        padding: 3px;
        gap: 2px;
    }
    .dark .wk-seg { background: #1e293b; }

    .wk-seg-btn {
        padding: 6px 14px;
        border-radius: 9px;
        font-size: 12px;
        font-weight: 600;
        border: none;
        background: transparent;
        color: #64748b;
        cursor: pointer;
        transition: all 0.2s ease;
        white-space: nowrap;
        line-height: 1.4;
        font-family: inherit;
    }
    .wk-seg-btn:hover { color: #334155; background: rgba(255,255,255,0.5); }
    .wk-seg-btn.active {
        background: #fff;
        color: #1e293b;
        box-shadow: 0 1px 4px rgba(0,0,0,0.08);
    }
    .dark .wk-seg-btn { color: #94a3b8; }
    .dark .wk-seg-btn:hover { color: #e2e8f0; background: rgba(255,255,255,0.05); }
    .dark .wk-seg-btn.active { background: #334155; color: #f1f5f9; box-shadow: 0 1px 4px rgba(0,0,0,0.25); }

    /* ── Search ────────────────────────────────────────────── */
    .wk-search-wrap { position: relative; }
    .wk-search-wrap i,
    .wk-search-wrap svg {
        position: absolute; left: 12px; top: 50%; transform: translateY(-50%);
        width: 15px; height: 15px; color: #94a3b8; pointer-events: none;
    }
    .wk-search {
        padding: 7px 12px 7px 34px;
        border-radius: 10px;
        border: 1px solid #e2e8f0;
        background: #fff;
        font-size: 12px;
        font-family: inherit;
        color: #334155;
        outline: none;
        transition: all 0.2s ease;
        width: 200px;
    }
    .wk-search::placeholder { color: #94a3b8; }
    .wk-search:focus {
        border-color: #818cf8;
        box-shadow: 0 0 0 3px rgba(99,102,241,0.08);
    }
    .dark .wk-search { background: #0f172a; border-color: #334155; color: #e2e8f0; }
    .dark .wk-search::placeholder { color: #64748b; }
    .dark .wk-search:focus { border-color: #6366f1; box-shadow: 0 0 0 3px rgba(99,102,241,0.15); }

    /* ── Date Dividers ─────────────────────────────────────── */
    .wk-date-header {
        font-size: 11px;
        font-weight: 700;
        letter-spacing: 0.06em;
        text-transform: uppercase;
        color: #64748b;
        padding: 18px 0 8px;
        margin: 0;
        border-bottom: 1px solid #f1f5f9;
    }
    .dark .wk-date-header { color: #475569; border-bottom-color: rgba(51,65,85,0.3); }

    /* ── Transaction Row ───────────────────────────────────── */
    .wk-txn {
        display: flex;
        align-items: center;
        gap: 12px;
        padding: 14px 0;
        border-bottom: 1px solid #f1f5f9;
        transition: all 0.2s ease;
        text-decoration: none;
        color: inherit;
        margin: 0 -8px;
        padding-left: 8px;
        padding-right: 8px;
        border-radius: 10px;
    }
    .wk-txn:hover { background: #f8fafc; }
    .dark .wk-txn { border-bottom-color: rgba(51,65,85,0.3); }
    .dark .wk-txn:hover { background: rgba(255,255,255,0.02); }
    .wk-txn:last-child { border-bottom: none; }

    @media (min-width: 768px) {
        .wk-txn {
            display: grid;
            grid-template-columns: 42px 2fr 1fr 110px 100px;
            gap: 0;
            align-items: center;
            column-gap: 14px;
        }
    }

    /* ── Icon ──────────────────────────────────────────────── */
    .wk-icon {
        width: 42px; height: 42px; border-radius: 12px;
        display: flex; align-items: center; justify-content: center;
        flex-shrink: 0;
        transition: transform 0.2s ease;
    }
    .wk-txn:hover .wk-icon { transform: scale(1.05); }
    .wk-icon svg, .wk-icon i { width: 18px; height: 18px; }

    /* ── Milestone Pill ────────────────────────────────────── */
    .wk-milestone {
        display: none;
    }
    @media (min-width: 768px) { .wk-milestone { display: flex; align-items: center; } }

    .wk-pill {
        display: inline-flex;
        align-items: center;
        gap: 4px;
        padding: 3px 10px;
        border-radius: 6px;
        font-size: 11px;
        font-weight: 600;
        color: #475569;
        background: #f1f5f9;
        white-space: nowrap;
        max-width: 180px;
        overflow: hidden;
        text-overflow: ellipsis;
    }
    .dark .wk-pill { color: #94a3b8; background: rgba(51,65,85,0.4); }

    /* ── Amount ────────────────────────────────────────────── */
    .wk-amt-cell { text-align: right; }
    .wk-amt {
        font-size: 14px; font-weight: 700;
        font-variant-numeric: tabular-nums;
        white-space: nowrap; text-align: right;
        letter-spacing: -0.01em;
    }

    /* ── Status Badge ──────────────────────────────────────── */
    .wk-status-cell { text-align: right; }
    .wk-badge {
        display: inline-flex;
        align-items: center;
        padding: 4px 12px;
        border-radius: 100px;
        font-size: 11px;
        font-weight: 600;
        white-space: nowrap;
        justify-content: center;
    }

    /* ── Action ────────────────────────────────────────────── */
    .wk-action {
        display: none;
    }
    @media (min-width: 768px) { .wk-action { display: flex; justify-content: flex-end; } }

    .wk-action-link {
        font-size: 11px; font-weight: 500;
        color: #94a3b8; text-decoration: none;
        white-space: nowrap; transition: color 0.15s ease;
    }
    .wk-action-link:hover { color: #4f46e5; }
    .dark .wk-action-link { color: #64748b; }
    .dark .wk-action-link:hover { color: #818cf8; }

    /* ── Description (mobile) ──────────────────────────────── */
    .wk-desc { display: block; }
    @media (min-width: 768px) { .wk-desc { display: none; } }

    /* ── Pagination ────────────────────────────────────────── */
    .wk-page-btn {
        width: 36px; height: 36px;
        display: inline-flex; align-items: center; justify-content: center;
        border-radius: 10px; font-size: 13px; font-weight: 500;
        text-decoration: none; transition: all 0.15s ease;
    }
    .wk-page-btn svg, .wk-page-btn i { width: 16px; height: 16px; }
    .wk-page-btn:hover { transform: translateY(-1px); }

    /* ── Empty State ───────────────────────────────────────── */
    .wk-empty { padding: 80px 24px; text-align: center; }

    /* ── Modal ─────────────────────────────────────────────── */
    .wk-slide { animation: wkSlide 0.3s cubic-bezier(0.16, 1, 0.3, 1) forwards; }
    @keyframes wkSlide {
        from { opacity: 0; transform: translateY(-16px) scale(0.97); }
        to { opacity: 1; transform: translateY(0) scale(1); }
    }
    .wk-overlay {
        background: rgba(0,0,0,0.5);
        backdrop-filter: blur(6px);
        -webkit-backdrop-filter: blur(6px);
    }

    /* ── Quick Amount Buttons ──────────────────────────────── */
    .wk-quick {
        transition: all 0.2s ease;
        border: 1.5px solid #e2e8f0;
        background: #fff;
        color: #334155;
        border-radius: 10px;
        padding: 10px 0;
        font-weight: 700;
        font-size: 13px;
        cursor: pointer;
        text-align: center;
        font-family: inherit;
    }
    .wk-quick:hover { border-color: #4f46e5; color: #4f46e5; background: #eef2ff; }
    .wk-quick.active { border-color: #4f46e5; background: #4f46e5; color: #fff; }
    html.dark .wk-quick { background: #1e293b; border-color: #334155; color: #e2e8f0; }
    html.dark .wk-quick:hover { border-color: #818cf8; color: #818cf8; background: rgba(99,102,241,0.1); }
    html.dark .wk-quick.active { border-color: #6366f1; background: #6366f1; color: #fff; }

    /* ── Payment Methods ───────────────────────────────────── */
    .wk-pm {
        border: 1.5px solid #e2e8f0; border-radius: 12px; padding: 14px 16px;
        cursor: pointer; transition: all 0.2s ease;
        display: flex; align-items: center; gap: 12px;
    }
    .wk-pm:hover { border-color: #c7d2fe; }
    .wk-pm.selected { border-color: #4f46e5; background: #eef2ff; }
    html.dark .wk-pm { border-color: #334155; background: #1e293b; }
    html.dark .wk-pm.selected { border-color: #6366f1; background: rgba(99,102,241,0.1); }

    /* ── Status Pulse ──────────────────────────────────────── */
    @keyframes wkPulse { 0%, 100% { opacity: 0.6; } 50% { opacity: 1; } }
    .wk-dot {
        width: 6px; height: 6px; border-radius: 50%;
        animation: wkPulse 2.5s ease-in-out infinite;
    }

    /* ── Responsive Mobile Date Header ─────────────────────── */
    .wk-mobile-date { display: block; }
    @media (min-width: 768px) { .wk-mobile-date { display: none; } }
</style>

<body class="wk-page">
    <?php display_flash('success'); ?>
    <?php display_flash('error'); ?>

    <main class="max-w-7xl mx-auto px-4 sm:px-6 py-8 flex flex-col gap-5">

        <!-- ═══════════════ Hero Balance Card ═══════════════ -->
        <div class="wk-hero rounded-2xl p-6 sm:p-8 fade-in">
            <div class="relative z-10 flex flex-col sm:flex-row sm:items-center sm:justify-between gap-6">
                <div class="space-y-2">
                    <h1 class="text-xl font-bold text-gray-900 dark:text-white m-0">Wallet & Billing</h1>
                    <p class="text-sm text-slate-400 dark:text-slate-500 m-0">Manage your balance, active escrow holds, and payment history.</p>
                </div>
                <div class="flex items-center gap-3 sm:self-end">
                    <button onclick="openTopUpModal()"
                        class="inline-flex items-center gap-2 px-6 py-3 bg-indigo-600 hover:bg-indigo-700 text-white font-bold rounded-xl active:scale-[0.97] transition-all text-sm shadow-sm shadow-indigo-600/20">
                        <i data-lucide="plus" class="w-4 h-4"></i> Top Up Wallet
                    </button>
                    <a href="wallet_history.php"
                        class="inline-flex items-center gap-2 px-5 py-3 bg-white hover:bg-gray-50 text-gray-600 hover:text-gray-900 font-semibold rounded-xl border border-gray-200 transition-all text-sm dark:bg-slate-800 dark:text-slate-300 dark:border-slate-600 dark:hover:bg-slate-700 dark:hover:text-white">
                        <i data-lucide="history" class="w-4 h-4"></i> View History
                    </a>
                </div>
            </div>
        </div>

        <!-- ═══════════════ Stat Cards ═══════════════ -->
        <div class="grid grid-cols-2 lg:grid-cols-4 gap-4">
            <?php foreach ($wallet_stats as $card): ?>
            <div class="wk-stat fade-in" style="animation-delay: <?= $card['delay'] * 0.08 ?>s">
                <!-- Sparkline SVG decoration -->
                <svg class="wk-spark" viewBox="0 0 100 50" fill="none" xmlns="http://www.w3.org/2000/svg">
                    <path d="M0 40 Q15 35 25 28 T50 20 T75 12 T100 5" stroke="currentColor" stroke-width="2" class="<?= $card['icon_color'] ?>"/>
                    <path d="M0 40 Q15 35 25 28 T50 20 T75 12 T100 5 V50 H0 Z" fill="currentColor" class="<?= $card['icon_color'] ?>" opacity="0.1"/>
                </svg>

                <div class="relative z-10 flex flex-col justify-between gap-4 h-full">
                    <div class="flex items-center justify-between">
                        <div class="w-10 h-10 rounded-xl <?= $card['icon_bg'] ?> flex items-center justify-center">
                            <i data-lucide="<?= $card['icon'] ?>" class="w-5 h-5 <?= $card['icon_color'] ?>"></i>
                        </div>
                        <div class="flex items-center gap-1.5">
                            <span class="wk-dot <?= $card['status_dot'] ?>"></span>
                            <span class="text-[10px] font-bold uppercase tracking-wider <?= $card['icon_color'] ?>"><?= $card['status_label'] ?></span>
                        </div>
                    </div>
                    <div>
                        <p class="text-2xl font-black text-gray-900 dark:text-white tracking-tight m-0 leading-none">
                            <?= $card['value'] ?>
                        </p>
                        <p class="text-xs font-semibold text-slate-400 dark:text-slate-500 mt-1.5 m-0"><?= $card['label'] ?></p>
                    </div>
                    <div class="pt-3 border-t border-gray-100 dark:border-slate-700/50 flex items-center justify-between">
                        <span class="text-[10px] uppercase font-bold text-slate-300 dark:text-slate-600 tracking-wider"><?= $card['badge_title'] ?></span>
                        <span class="text-[11px] font-medium text-slate-500 dark:text-slate-400"><?= $card['subtext'] ?></span>
                    </div>
                </div>
            </div>
            <?php endforeach; ?>
        </div>

        <!-- ═══════════════ Recent Activity ═══════════════ -->
        <div class="wk-txn-section overflow-hidden fade-in" style="animation-delay: 0.15s">
            <!-- Section Header -->
            <div class="px-5 sm:px-6 pt-5 sm:pt-6 pb-3 flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
                <div class="flex items-center gap-2.5">
                    <div class="w-9 h-9 rounded-xl bg-indigo-50 dark:bg-indigo-950/40 flex items-center justify-center">
                        <i data-lucide="activity" class="w-4 h-4 text-indigo-500 dark:text-indigo-400"></i>
                    </div>
                    <div>
                        <h3 class="text-sm font-bold text-gray-900 dark:text-white m-0">Recent Activity</h3>
                        <p class="text-[11px] text-slate-400 dark:text-slate-500 m-0">Your latest wallet transactions</p>
                    </div>
                </div>
                <a href="wallet_history.php"
                   class="inline-flex items-center gap-1 text-[11px] font-semibold text-slate-400 dark:text-slate-500 no-underline hover:text-indigo-600 dark:hover:text-indigo-400 transition-colors">
                    View All <i data-lucide="arrow-right" class="w-3 h-3"></i>
                </a>
            </div>

            <!-- Filter Bar -->
            <div class="wk-filter px-5 sm:px-6 py-3 flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
                <div class="wk-seg">
                    <button class="wk-seg-btn active" onclick="filterTransactions(this, 'all')">All</button>
                    <button class="wk-seg-btn" onclick="filterTransactions(this, 'deposit')">Top Ups</button>
                    <button class="wk-seg-btn" onclick="filterTransactions(this, 'escrow_hold')">Escrow Holds</button>
                    <button class="wk-seg-btn" onclick="filterTransactions(this, 'escrow_release')">Released</button>
                    <button class="wk-seg-btn" onclick="filterTransactions(this, 'refund')">Refunds</button>
                </div>
                <div class="wk-search-wrap">
                    <i data-lucide="search"></i>
                    <input type="text" id="txnSearchInput" class="wk-search" placeholder="Search transactions…" oninput="searchTransactions()">
                </div>
            </div>

            <!-- Table Header (desktop) -->
            <div class="wk-table-head">
                <span class="wk-th"></span>
                <span class="wk-th">Transaction</span>
                <span class="wk-th">Milestone</span>
                <span class="wk-th wk-th-right">Amount</span>
                <span class="wk-th wk-th-right">Status</span>
            </div>

            <!-- Transaction List -->
            <?php if (!empty($groupedHistory)): ?>
                <?php foreach ($groupedHistory as $dateLabel => $items): ?>
                    <div class="wk-date-group" data-date-group="<?= htmlspecialchars($dateLabel) ?>">
                        <div class="px-5 sm:px-8">
                            <p class="wk-date-header wk-mobile-date"><?= htmlspecialchars($dateLabel) ?></p>
                        </div>
                        <div class="px-5 sm:px-8 pb-2">
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
                            <a href="javascript:void(0)" onclick="openDrawer(<?= (int) $item['id'] ?>)" class="wk-txn" data-transaction-type="<?= htmlspecialchars($item['type']) ?>">
                                <!-- Icon -->
                                <div class="wk-icon <?= $iconBg ?>">
                                    <i data-lucide="<?= $txnIcon ?>"></i>
                                </div>

                                <!-- Title + Description (mobile) -->
                                <div class="min-w-0">
                                    <p class="text-[13px] font-semibold text-gray-900 dark:text-white m-0 leading-snug truncate"><?= htmlspecialchars($item['label']) ?></p>
                                    <p class="wk-desc text-[11px] text-slate-400 dark:text-slate-500 mt-0.5 m-0 truncate leading-snug">
                                        <?php if ($milestoneTitle): ?>
                                            <?= htmlspecialchars($milestoneTitle) ?>
                                        <?php else: ?>
                                            <?= htmlspecialchars($item['description'] ?? '') ?>
                                        <?php endif; ?>
                                    </p>
                                </div>

                                <!-- Milestone (desktop) -->
                                <div class="wk-milestone">
                                    <?php if ($milestoneTitle): ?>
                                        <span class="wk-pill" title="<?= htmlspecialchars($milestoneTitle) ?>">
                                            <i data-lucide="flag" class="w-3 h-3 shrink-0"></i>
                                            <?= htmlspecialchars($milestoneTitle) ?>
                                        </span>
                                    <?php elseif ($milestoneId): ?>
                                        <span class="wk-pill">
                                            <i data-lucide="flag" class="w-3 h-3 shrink-0"></i>
                                            Milestone #<?= (int) $milestoneId ?>
                                        </span>
                                    <?php else: ?>
                                        <span class="text-slate-200 dark:text-slate-700">—</span>
                                    <?php endif; ?>
                                </div>

                                <!-- Amount -->
                                <div class="wk-amt-cell">
                                    <span class="wk-amt <?= $dirClass ?>"><?= $prefix ?><?= wallet_format_currency($item['amount']) ?></span>
                                </div>

                                <!-- Badge -->
                                <div class="wk-status-cell">
                                    <span class="wk-badge <?= $badgeClass ?>"><?= $badgeLabel ?></span>
                                </div>
                            </a>
                            <?php endforeach; ?>
                        </div>
                    </div>
                <?php endforeach; ?>

                <!-- Pagination -->
                <?php if ($totalPages > 1): ?>
                    <div class="px-5 sm:px-8 py-5 border-t border-slate-100 dark:border-slate-700/50 flex items-center justify-between">
                        <p class="text-xs text-slate-400 dark:text-slate-500 m-0">
                            Page <span class="font-semibold text-gray-700 dark:text-slate-300"><?= $page ?></span> of <span class="font-semibold text-gray-700 dark:text-slate-300"><?= $totalPages ?></span>
                        </p>
                        <div class="flex items-center gap-1">
                            <?php if ($page > 1): ?>
                                <a href="?page=<?= $page - 1 ?>" class="wk-page-btn border border-gray-200 dark:border-slate-600 text-gray-500 dark:text-slate-400 hover:bg-gray-50 dark:hover:bg-slate-700">
                                    <i data-lucide="chevron-left"></i>
                                </a>
                            <?php endif; ?>
                            <?php for ($i = max(1, $page - 2); $i <= min($totalPages, $page + 2); $i++): ?>
                                <a href="?page=<?= $i ?>" class="wk-page-btn <?= $i === $page ? 'bg-gray-900 text-white border-gray-900 dark:bg-indigo-600 dark:text-white dark:border-indigo-600' : 'border border-gray-200 dark:border-slate-600 text-gray-500 dark:text-slate-400 hover:bg-gray-50 dark:hover:bg-slate-700' ?>"><?= $i ?></a>
                            <?php endfor; ?>
                            <?php if ($page < $totalPages): ?>
                                <a href="?page=<?= $page + 1 ?>" class="wk-page-btn border border-gray-200 dark:border-slate-600 text-gray-500 dark:text-slate-400 hover:bg-gray-50 dark:hover:bg-slate-700">
                                    <i data-lucide="chevron-right"></i>
                                </a>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endif; ?>
            <?php else: ?>
                <div class="wk-empty">
                    <div class="w-16 h-16 rounded-2xl bg-slate-50 dark:bg-slate-700/50 flex items-center justify-center mx-auto mb-4">
                        <i data-lucide="receipt" class="w-7 h-7 text-slate-300 dark:text-slate-500"></i>
                    </div>
                    <p class="text-gray-600 dark:text-slate-400 text-sm font-semibold mb-1 m-0">No transactions yet</p>
                    <p class="text-slate-400 dark:text-slate-500 text-xs m-0">Top up your wallet to get started</p>
                </div>
            <?php endif; ?>
        </div>

        <!-- ═══════════════ Top Up Modal ═══════════════ -->
        <div id="topUpModal" class="fixed inset-0 z-50 hidden">
            <div class="wk-overlay absolute inset-0" onclick="closeTopUpModal()"></div>
            <div class="absolute inset-0 flex items-center justify-center p-4">
                <div class="bg-white dark:bg-slate-800 rounded-2xl shadow-2xl w-full max-w-md relative z-10 wk-slide border border-gray-100 dark:border-slate-700">
                    <!-- Modal Header -->
                    <div class="px-6 pt-6 pb-4 border-b border-gray-100 dark:border-slate-700">
                        <div class="flex items-center justify-between">
                            <div>
                                <h3 class="text-lg font-bold text-gray-900 dark:text-white m-0">Top Up Wallet</h3>
                                <p class="text-xs text-slate-400 dark:text-slate-500 m-0 mt-1">Add funds using Demo Wallet</p>
                            </div>
                            <button onclick="closeTopUpModal()" class="w-9 h-9 rounded-xl bg-gray-100 dark:bg-slate-700 flex items-center justify-center text-gray-400 hover:text-gray-600 dark:hover:text-slate-300 hover:bg-gray-200 dark:hover:bg-slate-600 transition-all">
                                <i data-lucide="x" class="w-4 h-4"></i>
                            </button>
                        </div>
                    </div>

                    <form id="topUpForm" onsubmit="return processTopUp(event)">
                        <div class="px-6 py-5 space-y-5 max-h-[60vh] overflow-y-auto">
                            <!-- Current Balance Display -->
                            <div class="bg-slate-50 dark:bg-slate-700/30 rounded-xl p-4 text-center border border-slate-100 dark:border-slate-600/50">
                                <p class="text-[11px] text-slate-400 dark:text-slate-500 m-0 font-semibold uppercase tracking-wider">Current Balance</p>
                                <p class="text-2xl font-black text-gray-900 dark:text-white m-0 mt-1" id="modalCurrentBalance">$<?= number_format($stats['balance'], 2) ?></p>
                            </div>

                            <!-- Amount Input -->
                            <div>
                                <label class="block text-xs font-bold text-gray-700 dark:text-slate-300 mb-2">Amount</label>
                                <div class="relative">
                                    <span class="absolute left-4 top-1/2 -translate-y-1/2 text-slate-400 dark:text-slate-500 font-bold text-lg">$</span>
                                    <input type="number" name="amount" id="topUpAmount" required min="10" max="10000" step="0.01"
                                        placeholder="0.00"
                                        class="w-full pl-9 pr-4 py-3.5 rounded-xl border border-gray-200 dark:border-slate-600 bg-white dark:bg-slate-700 text-gray-900 dark:text-white text-lg font-bold outline-none focus:border-indigo-500 focus:ring-2 focus:ring-indigo-500/10 transition-all"
                                        oninput="validateTopUpAmount(this)">
                                </div>
                                <p class="text-[11px] text-slate-400 dark:text-slate-500 mt-1.5 m-0">Min: $10.00 — Max: $10,000.00</p>
                            </div>

                            <!-- Quick Amounts -->
                            <div>
                                <label class="block text-xs font-bold text-gray-700 dark:text-slate-300 mb-2">Quick Amount</label>
                                <div class="grid grid-cols-4 gap-2">
                                    <button type="button" class="wk-quick" onclick="setQuickAmount(100)">$100</button>
                                    <button type="button" class="wk-quick" onclick="setQuickAmount(250)">$250</button>
                                    <button type="button" class="wk-quick" onclick="setQuickAmount(500)">$500</button>
                                    <button type="button" class="wk-quick" onclick="setQuickAmount(1000)">$1,000</button>
                                </div>
                            </div>

                            <!-- Payment Method -->
                            <div>
                                <label class="block text-xs font-bold text-gray-700 dark:text-slate-300 mb-2">Payment Method</label>
                                <div class="space-y-2">
                                    <div class="wk-pm selected" onclick="selectPaymentMethod(this, 'demo_wallet')">
                                        <div class="w-5 h-5 rounded-full border-2 border-indigo-500 flex items-center justify-center">
                                            <div class="w-2.5 h-2.5 rounded-full bg-indigo-500"></div>
                                        </div>
                                        <div class="flex items-center gap-2.5 flex-1">
                                            <div class="w-8 h-8 rounded-lg bg-indigo-50 dark:bg-indigo-900/20 flex items-center justify-center">
                                                <i data-lucide="wallet" class="w-4 h-4 text-indigo-600"></i>
                                            </div>
                                            <span class="text-sm font-semibold text-gray-900 dark:text-white">Demo Wallet</span>
                                        </div>
                                        <span class="text-[10px] font-bold text-indigo-600 bg-indigo-50 px-2 py-0.5 rounded-md dark:bg-indigo-900/20 dark:text-indigo-400">Available</span>
                                    </div>
                                    <div class="wk-pm opacity-50 cursor-not-allowed">
                                        <div class="w-5 h-5 rounded-full border-2 border-gray-300 dark:border-slate-600"></div>
                                        <div class="flex items-center gap-2.5 flex-1">
                                            <div class="w-8 h-8 rounded-lg bg-gray-50 dark:bg-slate-700 flex items-center justify-center">
                                                <svg class="w-4 h-4 text-gray-400" fill="currentColor" viewBox="0 0 24 24"><path d="M13.976 9.15c-2.172-.806-3.356-1.426-3.356-2.409 0-.831.683-1.305 1.901-1.305 2.227 0 4.515.858 6.09 1.631l.89-5.494C18.252.975 15.697 0 12.165 0 9.667 0 7.589.654 6.104 1.872 4.56 3.147 3.757 4.992 3.757 7.218c0 4.039 2.467 5.76 6.476 7.219 2.585.92 3.445 1.574 3.445 2.583 0 .98-.84 1.545-2.354 1.545-1.875 0-4.965-.921-6.99-2.109l-.9 5.555C5.175 22.99 8.385 24 11.714 24c2.641 0 4.843-.624 6.328-1.813 1.664-1.305 2.525-3.236 2.525-5.732 0-4.128-2.524-5.851-6.591-7.305z"/></svg>
                                            </div>
                                            <span class="text-sm font-semibold text-gray-500 dark:text-slate-400">Stripe</span>
                                        </div>
                                        <span class="text-[10px] font-bold text-gray-400 bg-gray-100 px-2 py-0.5 rounded-md dark:bg-slate-700 dark:text-slate-500">Soon</span>
                                    </div>
                                    <div class="wk-pm opacity-50 cursor-not-allowed">
                                        <div class="w-5 h-5 rounded-full border-2 border-gray-300 dark:border-slate-600"></div>
                                        <div class="flex items-center gap-2.5 flex-1">
                                            <div class="w-8 h-8 rounded-lg bg-gray-50 dark:bg-slate-700 flex items-center justify-center">
                                                <svg class="w-4 h-4 text-gray-400" fill="currentColor" viewBox="0 0 24 24"><path d="M7.076 21.337H2.47a.641.641 0 0 1-.633-.74L4.944.901C5.026.382 5.474 0 5.998 0h7.46c2.57 0 4.578.543 5.69 1.81 1.01 1.15 1.304 2.42 1.012 4.287-.023.143-.047.288-.077.437-.983 5.05-4.349 6.797-8.647 6.797h-2.19c-.524 0-.968.382-1.05.9l-1.12 7.106zm14.146-14.42a3.35 3.35 0 0 0-.607-.541c-.013.076-.026.175-.041.254-.93 4.778-4.005 7.201-9.138 7.201h-2.19a.563.563 0 0 0-.556.479l-1.187 7.527h-.506l-.24 1.516a.56.56 0 0 0 .554.647h3.882c.46 0 .85-.334.922-.788.06-.26.76-4.852.816-5.09a.932.932 0 0 1 .923-.788h.58c3.76 0 6.705-1.528 7.565-5.946.36-1.847.174-3.388-.777-4.471z"/></svg>
                                            </div>
                                            <span class="text-sm font-semibold text-gray-500 dark:text-slate-400">PayPal</span>
                                        </div>
                                        <span class="text-[10px] font-bold text-gray-400 bg-gray-100 px-2 py-0.5 rounded-md dark:bg-slate-700 dark:text-slate-500">Soon</span>
                                    </div>
                                    <div class="wk-pm opacity-50 cursor-not-allowed">
                                        <div class="w-5 h-5 rounded-full border-2 border-gray-300 dark:border-slate-600"></div>
                                        <div class="flex items-center gap-2.5 flex-1">
                                            <div class="w-8 h-8 rounded-lg bg-gray-50 dark:bg-slate-700 flex items-center justify-center">
                                                <i data-lucide="landmark" class="w-4 h-4 text-gray-400"></i>
                                            </div>
                                            <span class="text-sm font-semibold text-gray-500 dark:text-slate-400">Bank Transfer</span>
                                        </div>
                                        <span class="text-[10px] font-bold text-gray-400 bg-gray-100 px-2 py-0.5 rounded-md dark:bg-slate-700 dark:text-slate-500">Soon</span>
                                    </div>
                                </div>
                                <input type="hidden" name="payment_method" id="paymentMethod" value="demo_wallet">
                            </div>

                            <?= csrf_field() ?>
                        </div>

                        <div class="px-6 pb-6 flex gap-3">
                            <button type="button" onclick="closeTopUpModal()"
                                class="flex-1 px-4 py-3 rounded-xl border border-gray-200 dark:border-slate-600 text-sm font-semibold text-gray-600 dark:text-slate-300 hover:bg-gray-50 dark:hover:bg-slate-700 transition-all">
                                Cancel
                            </button>
                            <button type="submit" id="topUpBtn"
                                class="flex-1 px-4 py-3 rounded-xl bg-indigo-600 hover:bg-indigo-700 text-white text-sm font-bold shadow-lg shadow-indigo-600/25 hover:shadow-xl hover:shadow-indigo-600/30 transition-all disabled:opacity-50 disabled:cursor-not-allowed active:scale-[0.98]">
                                <span id="topUpBtnText">Continue</span>
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        <!-- ═══════════════ Success Modal ═══════════════ -->
        <div id="successModal" class="fixed inset-0 z-50 hidden">
            <div class="wk-overlay absolute inset-0" onclick="closeSuccessModal()"></div>
            <div class="absolute inset-0 flex items-center justify-center p-4">
                <div class="bg-white dark:bg-slate-800 rounded-2xl shadow-2xl w-full max-w-sm relative z-10 wk-slide border border-gray-100 dark:border-slate-700">
                    <div class="p-8 text-center">
                        <div class="w-20 h-20 rounded-full bg-indigo-50 dark:bg-indigo-900/20 flex items-center justify-center mx-auto mb-5">
                            <i data-lucide="check" class="w-10 h-10 text-indigo-600"></i>
                        </div>
                        <h3 class="text-xl font-bold text-gray-900 dark:text-white mb-2 m-0">Wallet Updated!</h3>
                        <p class="text-sm text-slate-500 dark:text-slate-400 mb-1 m-0">
                            <span class="text-indigo-600 dark:text-indigo-400 font-bold" id="successAmount">+$500.00</span>
                        </p>
                        <p class="text-[11px] text-slate-400 dark:text-slate-500 mb-1 m-0 font-semibold uppercase tracking-wider">Current Balance</p>
                        <p class="text-2xl font-black text-gray-900 dark:text-white m-0" id="successBalance">$2,500.00</p>
                    </div>
                    <div class="px-8 pb-8">
                        <button onclick="closeSuccessModal()"
                            class="w-full px-4 py-3 rounded-xl bg-indigo-600 hover:bg-indigo-700 text-white text-sm font-bold shadow-lg shadow-indigo-600/25 hover:shadow-xl transition-all active:scale-[0.98]">
                            Continue
                        </button>
                    </div>
                </div>
            </div>
        </div>

        <!-- ═══════════════ Loading Overlay ═══════════════ -->
        <div id="loadingOverlay" class="fixed inset-0 z-[60] hidden wk-overlay flex items-center justify-center">
            <div class="bg-white dark:bg-slate-800 rounded-2xl p-8 shadow-2xl text-center border border-gray-100 dark:border-slate-700">
                <div class="w-12 h-12 border-4 border-indigo-200 border-t-indigo-600 rounded-full animate-spin mx-auto mb-4"></div>
                <p class="text-sm font-semibold text-gray-700 dark:text-slate-300 m-0">Processing...</p>
            </div>
        </div>

        <!-- ═══════════════ Transaction Detail Drawer ═══════════════ -->
        <div id="txnDrawer" class="fixed inset-0 z-50 hidden" aria-modal="true" role="dialog">
            <div id="txnDrawerBackdrop" class="absolute inset-0 bg-slate-900/50 backdrop-blur-sm transition-opacity" onclick="closeDrawer()"></div>
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
                            <div class="flex justify-between items-center px-3 py-2.5"><span class="text-xs text-slate-400 dark:text-slate-500">Date & Time</span><span id="drawerDate" class="text-xs font-semibold text-slate-900 dark:text-white">—</span></div>
                            <div class="flex justify-between items-center px-3 py-2.5"><span class="text-xs text-slate-400 dark:text-slate-500">Type</span><span id="drawerType" class="text-xs font-semibold text-slate-900 dark:text-white">—</span></div>
                            <div class="flex justify-between items-center px-3 py-2.5"><span class="text-xs text-slate-400 dark:text-slate-500">Balance After</span><span id="drawerBalance" class="text-xs font-semibold text-slate-900 dark:text-white tabular-nums">—</span></div>
                        </div>
                    </div>
                    <!-- Project Info -->
                    <div id="drawerProjectSection" class="hidden">
                        <p class="text-[10px] font-bold uppercase tracking-wider text-slate-400 dark:text-slate-500 mb-2 m-0">Project Info</p>
                        <div class="bg-white dark:bg-slate-800/30 rounded-lg border border-slate-100 dark:border-slate-700/50 divide-y divide-slate-100 dark:divide-slate-700/50">
                            <div id="drawerProjectRow" class="flex justify-between items-center px-3 py-2.5 hidden"><span class="text-xs text-slate-400 dark:text-slate-500">Project</span><span id="drawerProject" class="text-xs font-semibold text-slate-900 dark:text-white text-right max-w-[60%] truncate">—</span></div>
                            <div id="drawerMilestoneRow" class="flex justify-between items-center px-3 py-2.5 hidden"><span class="text-xs text-slate-400 dark:text-slate-500">Milestone</span><span id="drawerMilestone" class="text-xs font-semibold text-slate-900 dark:text-white text-right max-w-[60%] truncate">—</span></div>
                            <div id="drawerMilestoneAmtRow" class="flex justify-between items-center px-3 py-2.5 hidden"><span class="text-xs text-slate-400 dark:text-slate-500">Milestone Amount</span><span id="drawerMilestoneAmt" class="text-xs font-semibold text-slate-900 dark:text-white tabular-nums">—</span></div>
                            <div id="drawerDueDateRow" class="flex justify-between items-center px-3 py-2.5 hidden"><span class="text-xs text-slate-400 dark:text-slate-500">Due Date</span><span id="drawerDueDate" class="text-xs font-semibold text-slate-900 dark:text-white">—</span></div>
                        </div>
                    </div>
                    <!-- Payment Info -->
                    <div id="drawerPaymentSection" class="hidden">
                        <p class="text-[10px] font-bold uppercase tracking-wider text-slate-400 dark:text-slate-500 mb-2 m-0">Payment Info</p>
                        <div class="bg-white dark:bg-slate-800/30 rounded-lg border border-slate-100 dark:border-slate-700/50 divide-y divide-slate-100 dark:divide-slate-700/50">
                            <div class="flex justify-between items-center px-3 py-2.5"><span class="text-xs text-slate-400 dark:text-slate-500">Payment Method</span><span id="drawerPaymentMethod" class="text-xs font-semibold text-slate-900 dark:text-white">—</span></div>
                            <div class="flex justify-between items-center px-3 py-2.5"><span class="text-xs text-slate-400 dark:text-slate-500">Gross Amount</span><span id="drawerGrossAmount" class="text-xs font-semibold text-slate-900 dark:text-white tabular-nums">—</span></div>
                            <div class="flex justify-between items-center px-3 py-2.5"><span class="text-xs text-slate-400 dark:text-slate-500">Platform Fee</span><span id="drawerPlatformFee" class="text-xs font-semibold text-slate-400 dark:text-slate-500 tabular-nums">—</span></div>
                            <div class="flex justify-between items-center px-3 py-2.5"><span class="text-xs text-slate-400 dark:text-slate-500">Freelancer Net</span><span id="drawerFreelancerNet" class="text-xs font-bold text-slate-900 dark:text-white tabular-nums">—</span></div>
                        </div>
                    </div>
                </div>
                <!-- Sticky Footer -->
                <div class="px-5 py-3.5 border-t border-slate-100 dark:border-slate-700/50 bg-white dark:bg-slate-900 flex-shrink-0">
                    <button onclick="downloadReceipt()" class="w-full py-2.5 px-4 rounded-lg bg-indigo-600 hover:bg-indigo-700 text-white text-sm font-semibold transition-colors mb-2">Download Receipt</button>
                    <a href="disputes.php" class="block text-center text-xs font-medium text-slate-400 hover:text-indigo-600 dark:hover:text-indigo-400 transition-colors no-underline">Contact Support</a>
                </div>
            </div>
        </div>

    </main>

<?php $conn->close(); ?>

<script src="/jobhub/assets/js/wallet.js"></script>
<script src="/jobhub/assets/js/receipt.js"></script>
<script>
    var CSRF_TOKEN = '<?= generate_csrf_token() ?>';
    var BASE_URL = '/jobhub';

    function filterTransactions(btn, type) {
        document.querySelectorAll('.wk-seg-btn').forEach(c => c.classList.remove('active'));
        btn.classList.add('active');
        applyFilters();
    }

    function searchTransactions() {
        applyFilters();
    }

    function applyFilters() {
        var activeTab = document.querySelector('.wk-seg-btn.active');
        var type = activeTab ? activeTab.getAttribute('onclick').match(/'([^']+)'/)[1] : 'all';
        var search = (document.getElementById('txnSearchInput').value || '').toLowerCase().trim();

        document.querySelectorAll('[data-transaction-type]').forEach(row => {
            var matchType = (type === 'all') || (row.getAttribute('data-transaction-type') === type);
            var matchSearch = !search || row.textContent.toLowerCase().includes(search);
            row.style.display = (matchType && matchSearch) ? '' : 'none';
        });

        document.querySelectorAll('[data-date-group]').forEach(group => {
            var visibleItems = group.querySelectorAll('[data-transaction-type]:not([style*="display: none"])');
            group.style.display = visibleItems.length === 0 ? 'none' : '';
        });
    }

    // ── Drawer Functions ──────────────────────────────────────
    var currentTxnId = null;
    var currentTxnData = null;

    function openDrawer(txnId) {
        currentTxnId = txnId;
        currentTxnData = null;
        var drawer = document.getElementById('txnDrawer');
        var panel = document.getElementById('txnDrawerPanel');
        var backdrop = document.getElementById('txnDrawerBackdrop');
        drawer.classList.remove('hidden');
        document.body.style.overflow = 'hidden';
        requestAnimationFrame(function() {
            backdrop.style.opacity = '1';
            panel.style.transform = 'translateX(0)';
        });
        fetchTransactionDetails(txnId);
    }

    function closeDrawer() {
        var panel = document.getElementById('txnDrawerPanel');
        var backdrop = document.getElementById('txnDrawerBackdrop');
        panel.style.transform = 'translateX(100%)';
        backdrop.style.opacity = '0';
        setTimeout(function() {
            document.getElementById('txnDrawer').classList.add('hidden');
            document.body.style.overflow = '';
        }, 300);
    }

    function fetchTransactionDetails(txnId) {
        fetch('api/wallet_detail.php?id=' + txnId)
            .then(function(resp) {
                if (!resp.ok) throw new Error('Failed');
                return resp.json();
            })
            .then(function(data) {
                if (data.success) {
                    currentTxnData = data;
                    populateDrawer(data);
                }
            })
            .catch(function() {
                window.location.href = 'wallet_history_detail.php?id=' + txnId;
            });
    }

    function populateDrawer(data) {
        var t = data.transaction;
        var p = data.payment || {};
        var m = data.milestone || {};
        var j = data.job || {};
        var dirClass = t.direction === 'credit' ? 'text-emerald-600 dark:text-emerald-400' : 'text-slate-900 dark:text-white';
        var prefix = t.direction === 'credit' ? '+' : '';
        document.getElementById('drawerHeaderId').textContent = '#' + t.id;
        document.getElementById('drawerAmount').className = 'text-2xl font-bold m-0 tabular-nums ' + dirClass;
        document.getElementById('drawerAmount').textContent = prefix + '$' + parseFloat(t.amount).toFixed(2);
        document.getElementById('drawerLabel').textContent = t.label;
        document.getElementById('drawerDate').textContent = t.dateFormatted;
        document.getElementById('drawerType').textContent = t.label;
        document.getElementById('drawerBalance').textContent = '$' + parseFloat(t.balance_after).toFixed(2);
        var badgeEl = document.getElementById('drawerBadge');
        var badgeClasses = { 'deposit': 'bg-emerald-50 text-emerald-700 border-emerald-200/60 dark:bg-emerald-950/40 dark:text-emerald-400 dark:border-emerald-800/40', 'escrow_hold': 'bg-amber-50 text-amber-700 border-amber-200/60 dark:bg-amber-950/40 dark:text-amber-400 dark:border-amber-800/40', 'escrow_release': 'bg-blue-50 text-blue-700 border-blue-200/60 dark:bg-blue-950/40 dark:text-blue-400 dark:border-blue-800/40', 'refund': 'bg-purple-50 text-purple-700 border-purple-200/60 dark:bg-purple-950/40 dark:text-purple-400 dark:border-purple-800/40', 'credit': 'bg-emerald-50 text-emerald-700 border-emerald-200/60 dark:bg-emerald-950/40 dark:text-emerald-400 dark:border-emerald-800/40', 'withdrawal': 'bg-red-50 text-red-600 border-red-200/60 dark:bg-red-950/40 dark:text-red-400 dark:border-red-800/40' };
        badgeEl.className = 'inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium border ' + (badgeClasses[t.type] || 'bg-slate-100 text-slate-600 border-slate-200/60');
        badgeEl.textContent = t.displayStatus;
        var projSection = document.getElementById('drawerProjectSection');
        if (m.title || j.title) {
            projSection.classList.remove('hidden');
            if (j.title) { document.getElementById('drawerProjectRow').classList.remove('hidden'); document.getElementById('drawerProject').textContent = j.title; }
            if (m.title) { document.getElementById('drawerMilestoneRow').classList.remove('hidden'); document.getElementById('drawerMilestone').textContent = m.title; }
            if (m.amount) { document.getElementById('drawerMilestoneAmtRow').classList.remove('hidden'); document.getElementById('drawerMilestoneAmt').textContent = '$' + parseFloat(m.amount).toFixed(2); }
            if (m.due_date) { document.getElementById('drawerDueDateRow').classList.remove('hidden'); document.getElementById('drawerDueDate').textContent = m.due_date; }
        }
        var paySection = document.getElementById('drawerPaymentSection');
        if (p.payment_method) {
            paySection.classList.remove('hidden');
            document.getElementById('drawerPaymentMethod').textContent = p.payment_method.charAt(0).toUpperCase() + p.payment_method.slice(1);
            document.getElementById('drawerGrossAmount').textContent = '$' + parseFloat(p.total_amount).toFixed(2);
            document.getElementById('drawerPlatformFee').textContent = '-$' + parseFloat(p.platform_fee).toFixed(2);
            document.getElementById('drawerFreelancerNet').textContent = '$' + parseFloat(p.freelancer_net).toFixed(2);
        }
    }

    document.addEventListener('keydown', function(e) {
        if (e.key === 'Escape') closeDrawer();
    });
</script>

<?php require_once __DIR__ . '/../includes/client_footer.php'; ?>
