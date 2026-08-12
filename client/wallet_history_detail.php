<?php
/**
 * Wallet Transaction Detail – Premium Digital Receipt
 * Shows full details for a single wallet_transactions row.
 */
require_once __DIR__ . '/../auth/auth.php';
require_role('client');
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/wallet_functions.php';
require_once __DIR__ . '/../includes/wallet_helpers.php';

$userId = (int) $_SESSION['user_id'];

// ── Validate ID ─────────────────────────────────────────────────────
$txnId = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
if (!$txnId || $txnId <= 0) {
    header('Location: wallet_history.php');
    exit;
}

// ── Fetch wallet_transactions row (ownership enforced via user_id) ──
$stmt = $conn->prepare('
    SELECT id, user_id, type, amount, balance_after, reference_id, reference_type, description, created_at
    FROM wallet_transactions
    WHERE id = ? AND user_id = ?
    LIMIT 1
');
$stmt->bind_param('ii', $txnId, $userId);
$stmt->execute();
$txn = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$txn) {
    header('Location: wallet_history.php');
    exit;
}

// ── Display properties from existing wallet_functions logic ─────────
$label = match ($txn['type']) {
    'deposit'        => 'Wallet Top Up',
    'escrow_hold'    => 'Escrow Funding',
    'escrow_release' => 'Payment Released',
    'refund'         => 'Refund',
    'credit'         => 'Payment Received',
    'withdrawal'     => 'Withdrawal',
    default          => ucfirst(str_replace('_', ' ', $txn['type'])),
};
$icon = match ($txn['type']) {
    'deposit'        => 'plus-circle',
    'escrow_hold'    => 'shield',
    'escrow_release' => 'send',
    'refund'         => 'undo',
    'credit'         => 'check-circle',
    'withdrawal'     => 'arrow-up',
    default          => 'circle',
};
$color = match ($txn['type']) {
    'deposit', 'escrow_release', 'credit' => 'emerald',
    'escrow_hold'                          => 'amber',
    'refund'                               => 'purple',
    'withdrawal'                           => 'red',
    default                                => 'gray',
};
$direction = match ($txn['type']) {
    'deposit', 'escrow_release', 'refund', 'credit' => 'credit',
    'escrow_hold', 'withdrawal'                      => 'debit',
    default                                          => 'neutral',
};

// Status: derive from payments.status when available, else from type
$displayText = match ($txn['type']) {
    'deposit'        => 'Completed',
    'escrow_hold'    => 'Held',
    'escrow_release' => 'Released',
    'refund'         => 'Refunded',
    'credit'         => 'Completed',
    'withdrawal'     => 'Completed',
    default          => ucfirst(str_replace('_', ' ', $txn['type'])),
};

// ── Fetch related data based on reference_type ──────────────────────
$payment   = null;
$milestone = null;
$contract  = null;
$job       = null;

$refId   = $txn['reference_id']   ? (int) $txn['reference_id']   : null;
$refType = $txn['reference_type'] ?? null;

if ($refType === 'milestone' && $refId) {
    // Milestone row
    $stmt = $conn->prepare('SELECT id, contract_id, title, amount, status, due_date FROM milestones WHERE id = ?');
    $stmt->bind_param('i', $refId);
    $stmt->execute();
    $milestone = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    // Payment linked to this milestone
    if ($milestone) {
        if ($txn['type'] === 'escrow_hold') {
            $stmt = $conn->prepare('
                SELECT id, total_amount, platform_fee, freelancer_net, payment_method, status, created_at
                FROM payments
                WHERE milestone_id = ? AND ABS(TIMESTAMPDIFF(SECOND, ?, created_at)) < 10
                ORDER BY ABS(TIMESTAMPDIFF(SECOND, ?, created_at)) ASC
                LIMIT 1
            ');
            $stmt->bind_param('iss', $refId, $txn['created_at'], $txn['created_at']);
        } else {
            $stmt = $conn->prepare('
                SELECT id, total_amount, platform_fee, freelancer_net, payment_method, status, created_at
                FROM payments
                WHERE milestone_id = ? AND created_at <= ?
                ORDER BY created_at DESC
                LIMIT 1
            ');
            $stmt->bind_param('is', $refId, $txn['created_at']);
        }
        $stmt->execute();
        $payment = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        if ($payment && $payment['status']) {
            $displayText = ucfirst($payment['status']);
        }
    }

    // Contract + Job for project context
    if ($milestone && $milestone['contract_id']) {
        $cid = (int) $milestone['contract_id'];
        $stmt = $conn->prepare('SELECT id, job_id, client_id, freelancer_id, total_budget, status FROM contracts WHERE id = ?');
        $stmt->bind_param('i', $cid);
        $stmt->execute();
        $contract = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        if ($contract && $contract['job_id']) {
            $jid = (int) $contract['job_id'];
            $stmt = $conn->prepare('SELECT id, title, budget FROM jobs WHERE id = ?');
            $stmt->bind_param('i', $jid);
            $stmt->execute();
            $job = $stmt->get_result()->fetch_assoc();
            $stmt->close();
        }
    }
}

// ── Page setup ──────────────────────────────────────────────────────
$pageTitle   = 'Transaction Detail';
$pageSubtitle = 'Transaction #' . $txn['id'];
$activePage  = 'wallet';

// Fetch user data (needed for topbar)
$uStmt = $conn->prepare('SELECT name, profile_image FROM users WHERE id = ?');
$uStmt->bind_param('i', $userId);
$uStmt->execute();
$userData = $uStmt->get_result()->fetch_assoc();
$uStmt->close();

$user        = ['name' => $userData['name'] ?? 'Client', 'profile_image' => $userData['profile_image'] ?? null];
$unreadCount = get_unread_message_count($userId, 'client');
$profileLink = 'profile.php';

require_once __DIR__ . '/../includes/client_topbar.php';
?>

<style>
    /* ── Premium Digital Receipt ─────────────────────────────── */
    @import url('https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800;900&display=swap');

    .dr-page {
        font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif;
        background: #f8fafc;
        min-height: 100vh;
    }
    .dark .dr-page { background: #0f172a; }

    /* Receipt Card */
    .dr-card {
        background: #fff;
        border: 1px solid #e2e8f0;
        box-shadow: 0 1px 3px rgba(0,0,0,0.04), 0 8px 32px -8px rgba(0,0,0,0.08);
        overflow: hidden;
    }
    .dark .dr-card {
        background: #1e293b;
        border-color: rgba(255,255,255,0.06);
    }

    /* Amount Header */
    .dr-amount-header {
        padding: 40px 32px 32px;
        text-align: center;
        position: relative;
    }
    .dr-amount-header::after {
        content: '';
        position: absolute;
        bottom: 0;
        left: 32px;
        right: 32px;
        height: 1px;
        background: linear-gradient(90deg, transparent, #e2e8f0, transparent);
    }
    .dark .dr-amount-header::after {
        background: linear-gradient(90deg, transparent, rgba(51,65,85,0.5), transparent);
    }

    /* Icon Circle */
    .dr-icon {
        width: 56px;
        height: 56px;
        border-radius: 16px;
        display: flex;
        align-items: center;
        justify-content: center;
        margin: 0 auto 16px;
    }
    .dr-icon svg, .dr-icon i { width: 24px; height: 24px; }

    /* Detail Row */
    .dr-row {
        display: flex;
        justify-content: space-between;
        align-items: flex-start;
        padding: 14px 0;
        border-bottom: 1px solid #f1f5f9;
    }
    .dark .dr-row { border-bottom-color: rgba(51,65,85,0.3); }
    .dr-row:last-child { border-bottom: none; }

    .dr-label {
        font-size: 12px;
        font-weight: 500;
        color: #94a3b8;
        flex-shrink: 0;
    }
    .dark .dr-label { color: #64748b; }

    .dr-value {
        font-size: 13px;
        font-weight: 600;
        color: #1e293b;
        text-align: right;
        max-width: 65%;
        word-break: break-word;
    }
    .dark .dr-value { color: #e2e8f0; }

    /* Section Header */
    .dr-section {
        font-size: 10px;
        font-weight: 700;
        letter-spacing: 0.1em;
        text-transform: uppercase;
        color: #94a3b8;
        padding-bottom: 10px;
        margin-bottom: 0;
    }
    .dark .dr-section { color: #475569; }

    /* Status Badge */
    .dr-badge {
        display: inline-flex;
        align-items: center;
        padding: 5px 16px;
        border-radius: 100px;
        font-size: 12px;
        font-weight: 600;
        letter-spacing: 0.01em;
    }

    /* Action Buttons */
    .dr-btn {
        flex: 1;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        gap: 8px;
        padding: 14px 20px;
        border-radius: 12px;
        font-size: 13px;
        font-weight: 600;
        text-decoration: none;
        transition: all 0.2s cubic-bezier(0.4, 0, 0.2, 1);
        cursor: pointer;
        border: none;
    }
    .dr-btn:hover { transform: translateY(-1px); }
    .dr-btn:active { transform: translateY(0); }

    @media print {
        .no-print { display: none !important; }
        body { background: #fff !important; }
        .dr-card { box-shadow: none !important; border: 1px solid #e5e7eb !important; }
    }
</style>

<body class="dr-page">
    <?php display_flash('success'); ?>
    <?php display_flash('error'); ?>

    <main class="max-w-2xl mx-auto px-4 sm:px-6 py-8 flex flex-col gap-5">

        <!-- ═══════════════ Breadcrumb ═══════════════ -->
        <a href="wallet_history.php"
           class="inline-flex items-center gap-2 text-sm font-semibold text-slate-400 dark:text-slate-500 hover:text-indigo-600 dark:hover:text-indigo-400 transition-colors no-underline w-fit">
            <i data-lucide="arrow-left" class="w-4 h-4"></i> Back to Wallet History
        </a>

        <!-- ═══════════════ Receipt Card ═══════════════ -->
        <div class="dr-card rounded-2xl fade-in">

            <!-- ── Amount Header ── -->
            <div class="dr-amount-header">
                <?php
                $iconBgMap = [
                    'emerald' => 'bg-emerald-50 text-emerald-600 dark:bg-emerald-950/50 dark:text-emerald-400',
                    'amber'   => 'bg-amber-50 text-amber-600 dark:bg-amber-950/50 dark:text-amber-400',
                    'blue'    => 'bg-blue-50 text-blue-600 dark:bg-blue-950/50 dark:text-blue-400',
                    'purple'  => 'bg-purple-50 text-purple-600 dark:bg-purple-950/50 dark:text-purple-400',
                    'red'     => 'bg-red-50 text-red-500 dark:bg-red-950/50 dark:text-red-400',
                ];
                $iconBg = $iconBgMap[$color] ?? 'bg-slate-100 text-slate-400 dark:bg-slate-700/50 dark:text-slate-400';
                ?>
                <div class="dr-icon <?= $iconBg ?>">
                    <i data-lucide="<?= $icon ?>"></i>
                </div>

                <?php
                $amountClass = $direction === 'credit'
                    ? 'text-emerald-600 dark:text-emerald-400'
                    : ($direction === 'debit' ? 'text-gray-900 dark:text-white' : 'text-gray-900 dark:text-white');
                $prefix = $direction === 'credit' ? '+' : '';
                ?>
                <p class="text-4xl sm:text-5xl font-black <?= $amountClass ?> m-0 tabular-nums tracking-tight">
                    <?= $prefix ?><?= wallet_format_currency($txn['amount']) ?>
                </p>
                <p class="text-sm font-semibold text-slate-500 dark:text-slate-400 mt-2 m-0"><?= htmlspecialchars($label) ?></p>

                <?php
                $badgeMap = [
                    'deposit'        => 'bg-emerald-50 text-emerald-700 dark:bg-emerald-950/40 dark:text-emerald-400',
                    'escrow_hold'    => 'bg-amber-50 text-amber-700 dark:bg-amber-950/40 dark:text-amber-400',
                    'escrow_release' => 'bg-blue-50 text-blue-700 dark:bg-blue-950/40 dark:text-blue-400',
                    'refund'         => 'bg-purple-50 text-purple-700 dark:bg-purple-950/40 dark:text-purple-400',
                    'credit'         => 'bg-emerald-50 text-emerald-700 dark:bg-emerald-950/40 dark:text-emerald-400',
                    'withdrawal'     => 'bg-red-50 text-red-600 dark:bg-red-950/40 dark:text-red-400',
                ];
                $bc = $badgeMap[$txn['type']] ?? 'bg-slate-100 text-slate-600 dark:bg-slate-700/40 dark:text-slate-400';
                ?>
                <span class="dr-badge <?= $bc ?> mt-4"><?= htmlspecialchars($displayText) ?></span>
            </div>

            <!-- ── Transaction Details ── -->
            <div class="px-6 sm:px-8 py-5">
                <p class="dr-section m-0 mb-1">Transaction Details</p>

                <div class="dr-row">
                    <span class="dr-label">Date & Time</span>
                    <span class="dr-value"><?= date('M j, Y \a\t g:i A', strtotime($txn['created_at'])) ?></span>
                </div>
                <div class="dr-row">
                    <span class="dr-label">Transaction ID</span>
                    <span class="dr-value font-mono">#<?= (int) $txn['id'] ?></span>
                </div>
                <div class="dr-row">
                    <span class="dr-label">Type</span>
                    <span class="dr-value"><?= htmlspecialchars($label) ?></span>
                </div>
                <div class="dr-row">
                    <span class="dr-label">Balance After</span>
                    <span class="dr-value tabular-nums"><?= wallet_format_currency($txn['balance_after']) ?></span>
                </div>
                <?php if ($txn['description']): ?>
                <div class="dr-row">
                    <span class="dr-label">Description</span>
                    <span class="dr-value"><?= htmlspecialchars($txn['description']) ?></span>
                </div>
                <?php endif; ?>
            </div>

            <!-- ── Project Info (escrow transactions) ── -->
            <?php if ($milestone): ?>
            <div class="px-6 sm:px-8 py-5 border-t border-slate-100 dark:border-slate-700/50">
                <p class="dr-section m-0 mb-1">Project Info</p>

                <?php if ($job): ?>
                <div class="dr-row">
                    <span class="dr-label">Project</span>
                    <span class="dr-value"><?= htmlspecialchars($job['title']) ?></span>
                </div>
                <?php endif; ?>

                <div class="dr-row">
                    <span class="dr-label">Milestone</span>
                    <span class="dr-value"><?= htmlspecialchars($milestone['title']) ?></span>
                </div>
                <div class="dr-row">
                    <span class="dr-label">Milestone Amount</span>
                    <span class="dr-value tabular-nums"><?= wallet_format_currency($milestone['amount']) ?></span>
                </div>
                <?php if (!empty($milestone['status'])): ?>
                <div class="dr-row">
                    <span class="dr-label">Milestone Status</span>
                    <span class="dr-value"><?= htmlspecialchars(ucfirst(str_replace('_', ' ', $milestone['status']))) ?></span>
                </div>
                <?php endif; ?>
                <?php if (!empty($milestone['due_date'])): ?>
                <div class="dr-row">
                    <span class="dr-label">Due Date</span>
                    <span class="dr-value"><?= date('M j, Y', strtotime($milestone['due_date'])) ?></span>
                </div>
                <?php endif; ?>
            </div>
            <?php endif; ?>

            <!-- ── Payment Info (when payment exists) ── -->
            <?php if ($payment): ?>
            <div class="px-6 sm:px-8 py-5 border-t border-slate-100 dark:border-slate-700/50">
                <p class="dr-section m-0 mb-1">Payment Info</p>

                <div class="dr-row">
                    <span class="dr-label">Payment Method</span>
                    <span class="dr-value">
                        <span class="inline-flex items-center gap-1.5">
                            <i data-lucide="wallet" class="w-3.5 h-3.5 text-indigo-500"></i>
                            <?= htmlspecialchars(ucfirst($payment['payment_method'])) ?>
                        </span>
                    </span>
                </div>
                <div class="dr-row">
                    <span class="dr-label">Gross Amount</span>
                    <span class="dr-value tabular-nums"><?= wallet_format_currency($payment['total_amount']) ?></span>
                </div>
                <div class="dr-row">
                    <span class="dr-label">Platform Fee</span>
                    <span class="dr-value tabular-nums text-slate-400 dark:text-slate-500">−<?= wallet_format_currency($payment['platform_fee']) ?></span>
                </div>
                <div class="dr-row">
                    <span class="dr-label">Freelancer Net</span>
                    <span class="dr-value tabular-nums font-bold"><?= wallet_format_currency($payment['freelancer_net']) ?></span>
                </div>
            </div>
            <?php endif; ?>

            <!-- ── Action Buttons ── -->
            <div class="px-6 sm:px-8 py-6 border-t border-slate-100 dark:border-slate-700/50 no-print">
                <div class="flex flex-col sm:flex-row gap-3">
                    <button onclick="window.print()"
                        class="dr-btn border border-gray-200 dark:border-slate-600 text-gray-700 dark:text-slate-300 bg-white dark:bg-slate-800 hover:bg-gray-50 dark:hover:bg-slate-700">
                        <i data-lucide="download" class="w-4 h-4"></i> Download Receipt
                    </button>
                    <a href="disputes.php"
                        class="dr-btn bg-gray-900 hover:bg-gray-800 text-white shadow-sm dark:bg-indigo-600 dark:hover:bg-indigo-700">
                        <i data-lucide="life-buoy" class="w-4 h-4"></i> Contact Support
                    </a>
                </div>
            </div>
        </div>
    </main>

<?php $conn->close(); ?>
<?php require_once __DIR__ . '/../includes/client_footer.php'; ?>
