<?php

/**
 * Admin Wallet History — Read-only slide-over drawer for per-user transaction history
 * Shows wallet owner info, filters, and full transaction list
 */
require_once __DIR__ . '/../auth/auth.php';
require_role('admin');
require_once __DIR__ . '/../config/db.php';

$currentPage = 'wallets';

// ── Validate user_id ────────────────────────────────────────────────
$userId = sanitize_int($_GET['user_id'] ?? 0);
if ($userId <= 0) {
    $_SESSION['flash_error'] = 'Invalid user ID.';
    header('Location: wallets.php');
    exit;
}

// ── Fetch user info ─────────────────────────────────────────────────
$uStmt = $conn->prepare('SELECT id, name, email, profile_image, role, wallet_balance, wallet_status FROM users WHERE id = ?');
$uStmt->bind_param('i', $userId);
$uStmt->execute();
$target = $uStmt->get_result()->fetch_assoc();
$uStmt->close();

if (!$target) {
    $_SESSION['flash_error'] = 'User not found.';
    header('Location: wallets.php');
    exit;
}

// ── Filters ─────────────────────────────────────────────────────────
$search = trim($_GET['search'] ?? '');
$typeF = $_GET['type'] ?? '';
$dateFrom = $_GET['date_from'] ?? '';
$dateTo = $_GET['date_to'] ?? '';
$page = max(1, sanitize_int($_GET['page'] ?? 1));
$perPage = 15;

$allowedTypes = ['deposit', 'withdrawal', 'escrow_hold', 'escrow_release', 'refund', 'platform_fee', 'signup_bonus'];
if ($typeF && !in_array($typeF, $allowedTypes))
    $typeF = '';

// ── Build query ─────────────────────────────────────────────────────
$where = ['wt.user_id = ?'];
$params = [$userId];
$types = 'i';

if ($search !== '') {
    $where[] = '(wt.description LIKE ? OR CAST(wt.id AS CHAR) LIKE ?)';
    $searchTerm = "%{$search}%";
    $params[] = $searchTerm;
    $params[] = $searchTerm;
    $types .= 'ss';
}
if ($typeF) {
    $where[] = 'wt.type = ?';
    $params[] = $typeF;
    $types .= 's';
}
if ($dateFrom !== '') {
    $where[] = 'wt.created_at >= ?';
    $params[] = $dateFrom . ' 00:00:00';
    $types .= 's';
}
if ($dateTo !== '') {
    $where[] = 'wt.created_at <= ?';
    $params[] = $dateTo . ' 23:59:59';
    $types .= 's';
}

$whereSql = implode(' AND ', $where);

// ── Count ───────────────────────────────────────────────────────────
$countSql = "SELECT COUNT(*) AS cnt FROM wallet_transactions wt WHERE {$whereSql}";
$countStmt = $conn->prepare($countSql);
$countStmt->bind_param($types, ...$params);
$countStmt->execute();
$totalTx = (int) $countStmt->get_result()->fetch_assoc()['cnt'];
$countStmt->close();

$pagination = paginate($totalTx, $perPage, $page);
$offset = $pagination['offset'];

// ── Aggregate stats (all time, no filters) ──────────────────────────
$asStmt = $conn->prepare('SELECT type, SUM(amount) AS total FROM wallet_transactions WHERE user_id = ? GROUP BY type');
$asStmt->bind_param('i', $userId);
$asStmt->execute();
$asRes = $asStmt->get_result();
$agg = ['deposit' => 0, 'signup_bonus' => 0, 'escrow_hold' => 0, 'escrow_release' => 0, 'refund' => 0, 'withdrawal' => 0, 'platform_fee' => 0];
while ($r = $asRes->fetch_assoc()) {
    $agg[$r['type']] = (float) $r['total'];
}
$asStmt->close();

// ── Fetch transactions (with payment status join) ───────────────────
$txSql = "SELECT wt.id, wt.type, wt.amount, wt.balance_after, wt.reference_id, wt.reference_type, wt.description, wt.created_at,
                 p.status AS payment_status
          FROM wallet_transactions wt
          LEFT JOIN payments p ON wt.reference_type = 'payment' AND wt.reference_id = p.id
          WHERE {$whereSql}
          ORDER BY wt.created_at DESC
          LIMIT ? OFFSET ?";

$txParams = array_merge($params, [$perPage, $offset]);
$txTypes = $types . 'ii';

$txStmt = $conn->prepare($txSql);
$txStmt->bind_param($txTypes, ...$txParams);
$txStmt->execute();
$txResult = $txStmt->get_result();
$txStmt->close();

$transactions = [];
while ($tx = $txResult->fetch_assoc()) {
    $transactions[] = $tx;
}
$txResult->free();

// ── Type labels ─────────────────────────────────────────────────────
$typeLabels = [
    'deposit' => ['Deposit', 'text-emerald-600 dark:text-emerald-400', 'bg-emerald-50 dark:bg-emerald-900/30', 'arrow-down'],
    'signup_bonus' => ['Bonus', 'text-cyan-600 dark:text-cyan-400', 'bg-cyan-50 dark:bg-cyan-900/30', 'gift'],
    'escrow_hold' => ['Escrow Fund', 'text-amber-600 dark:text-amber-400', 'bg-amber-50 dark:bg-amber-900/30', 'lock'],
    'escrow_release' => ['Escrow Release', 'text-violet-600 dark:text-violet-400', 'bg-violet-50 dark:bg-violet-900/30', 'unlock'],
    'refund' => ['Refund', 'text-teal-600 dark:text-teal-400', 'bg-teal-50 dark:bg-teal-900/30', 'rotate-ccw'],
    'withdrawal' => ['Withdrawal', 'text-red-600 dark:text-red-400', 'bg-red-50 dark:bg-red-900/30', 'arrow-up'],
    'platform_fee' => ['Platform Fee', 'text-orange-600 dark:text-orange-400', 'bg-orange-50 dark:bg-orange-900/30', 'percent'],
];

$roleColors = [
    'client' => 'bg-blue-100 text-blue-700 dark:bg-blue-900/30 dark:text-blue-400',
    'freelancer' => 'bg-emerald-100 text-emerald-700 dark:bg-emerald-900/30 dark:text-emerald-400',
    'admin' => 'bg-purple-100 text-purple-700 dark:bg-purple-900/30 dark:text-purple-400',
];

// ── Build base URL for pagination ───────────────────────────────────
$baseUrl = 'wallet_history.php?user_id=' . $userId;
if ($search !== '')
    $baseUrl .= '&search=' . urlencode($search);
if ($typeF)
    $baseUrl .= '&type=' . urlencode($typeF);
if ($dateFrom !== '')
    $baseUrl .= '&date_from=' . urlencode($dateFrom);
if ($dateTo !== '')
    $baseUrl .= '&date_to=' . urlencode($dateTo);

// ── Admin nav ───────────────────────────────────────────────────────
$_userId = $_SESSION['user_id'];
$sStmt = $conn->prepare('SELECT name, profile_image FROM users WHERE id = ?');
$sStmt->bind_param('i', $_userId);
$sStmt->execute();
$_navUserRow = $sStmt->get_result()->fetch_assoc();
$sStmt->close();
$adminName = $_navUserRow['name'] ?? 'Admin';

// ── Fetch refund-eligible milestones (escrow held OR dispute resolved) ──
$refundable = [];
// 1. Milestones with escrow held (funded_in_escrow) where this user is client
$reStmt = $conn->prepare('SELECT m.id, m.title, m.amount, m.status, c.id AS contract_id, j.title AS contract_title,
                                 c.client_id, c.freelancer_id, u.name AS client_name
                          FROM milestones m
                          JOIN contracts c ON m.contract_id = c.id
                          JOIN jobs j ON c.job_id = j.id
                          JOIN users u ON c.client_id = u.id
                          WHERE m.status = ? AND c.client_id = ?
                          ORDER BY m.id DESC');
$fundedStatus = 'funded_in_escrow';
$reStmt->bind_param('si', $fundedStatus, $userId);
$reStmt->execute();
$reRes = $reStmt->get_result();
while ($rr = $reRes->fetch_assoc()) {
    $rr['eligible_reason'] = 'escrow_held';
    $refundable[] = $rr;
}
$reStmt->close();

// 2. Disputed milestones where dispute resolved in client's favor
$rdStmt = $conn->prepare('SELECT m.id, m.title, m.amount, m.status, c.id AS contract_id, j.title AS contract_title,
                                 c.client_id, c.freelancer_id, u.name AS client_name
                          FROM milestones m
                          JOIN contracts c ON m.contract_id = c.id
                          JOIN jobs j ON c.job_id = j.id
                          JOIN users u ON c.client_id = u.id
                          JOIN dispute_tickets dt ON dt.milestone_id = m.id
                          WHERE m.status = ? AND dt.status = ? AND c.client_id = ?
                          GROUP BY m.id
                          ORDER BY m.id DESC');
$disputedStatus = 'disputed';
$resolvedStatus = 'resolved';
$rdStmt->bind_param('ssi', $disputedStatus, $resolvedStatus, $userId);
$rdStmt->execute();
$rdRes = $rdStmt->get_result();
while ($rr = $rdRes->fetch_assoc()) {
    $rr['eligible_reason'] = 'dispute_resolved';
    $refundable[] = $rr;
}
$rdStmt->close();

// ═══ EXPORT: Wallet Statement PDF ═══════════════════════════════════
if (isset($_GET['export']) && (int) $_GET['export'] === 1) {

    // Fetch user
    $euStmt = $conn->prepare('SELECT id, name, email, profile_image, role, wallet_balance FROM users WHERE id = ?');
    $euStmt->bind_param('i', $userId);
    $euStmt->execute();
    $eu = $euStmt->get_result()->fetch_assoc();
    $euStmt->close();

    // Re-fetch aggregate stats
    $eaStmt = $conn->prepare('SELECT type, SUM(amount) AS total FROM wallet_transactions WHERE user_id = ? GROUP BY type');
    $eaStmt->bind_param('i', $userId);
    $eaStmt->execute();
    $eaRes = $eaStmt->get_result();
    $eaAgg = ['deposit' => 0, 'signup_bonus' => 0, 'escrow_hold' => 0, 'escrow_release' => 0, 'refund' => 0, 'withdrawal' => 0, 'platform_fee' => 0];
    while ($er = $eaRes->fetch_assoc()) {
        $eaAgg[$er['type']] = (float) $er['total'];
    }
    $eaStmt->close();

    // Fetch ALL transactions (no pagination)
    $exStmt = $conn->prepare('SELECT wt.id, wt.type, wt.amount, wt.balance_after, wt.reference_id, wt.reference_type, wt.description, wt.created_at
                              FROM wallet_transactions wt
                              WHERE wt.user_id = ?
                              ORDER BY wt.created_at DESC');
    $exStmt->bind_param('i', $userId);
    $exStmt->execute();
    $exResult = $exStmt->get_result();
    $exTransactions = [];
    while ($etx = $exResult->fetch_assoc()) { $exTransactions[] = $etx; }
    $exResult->free();
    $exStmt->close();

    $totalDeposits = $eaAgg['deposit'] + $eaAgg['signup_bonus'];
    $totalEscrow   = $eaAgg['escrow_hold'];
    $totalEarnings = $eaAgg['escrow_release'];
    $totalWithdrawals = $eaAgg['withdrawal'];
    $generatedDate = date('M d, Y \a\t h:i A');
    $safeName = preg_replace('/[^a-zA-Z0-9_\-]/', '_', $eu['name']);
    ?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Wallet Statement — <?= sanitize_string($eu['name']) ?></title>
<style>
        @import url('https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap');
        *{margin:0;padding:0;box-sizing:border-box}
        body{font-family:'Inter',sans-serif;background:#f4f7f4;color:#1e293b;-webkit-print-color-adjust:exact;print-color-adjust:exact}

        .statement-page{max-width:800px;margin:0 auto;padding:40px 32px;background:#fff}
        @media print{.statement-page{margin:0;padding:24px 20px}}

        /* ── Top Bar (non-print) ─────────────────── */
        .top-bar{max-width:800px;margin:0 auto 20px;padding:16px 32px;display:flex;align-items:center;justify-content:space-between;gap:12px}
        .top-bar-title{font-size:14px;font-weight:600;color:#374151}
        .btn-group{display:flex;gap:8px}
        .btn{display:inline-flex;align-items:center;gap:6px;padding:8px 16px;border-radius:8px;font-size:12px;font-weight:600;cursor:pointer;border:1px solid #d1d5db;background:#fff;color:#374151;transition:all .15s;text-decoration:none}
        .btn:hover{background:#f9fafb}
        .btn-primary{background:#108A00;border-color:#108A00;color:#fff}
        .btn-primary:hover{background:#0d7500}
        @media print{.top-bar{display:none}}

        /* ── Header ──────────────────────────────── */
        .stmt-header{display:flex;align-items:center;gap:16px;padding-bottom:24px;border-bottom:2px solid #108A00;margin-bottom:24px}
        .stmt-logo{width:52px;height:52px;border-radius:12px;object-fit:cover;border:2px solid #E4EBE4}
        .stmt-header-text h1{font-size:18px;font-weight:800;color:#111827;margin-bottom:2px;letter-spacing:-.02em}
        .stmt-header-text .subtitle{font-size:12px;color:#6b7280;font-weight:500}

        /* ── Owner Info ──────────────────────────── */
        .owner-section{display:grid;grid-template-columns:1fr 1fr 1fr;gap:20px;padding:16px 20px;background:#F9FAFB;border:1px solid #E4EBE4;border-radius:10px;margin-bottom:24px}
        .owner-field-label{font-size:9px;font-weight:700;color:#9ca3af;text-transform:uppercase;letter-spacing:.06em;margin-bottom:3px}
        .owner-field-value{font-size:13px;font-weight:600;color:#111827}
        .role-badge{display:inline-block;padding:2px 8px;border-radius:4px;font-size:10px;font-weight:700;text-transform:uppercase}
        .role-badge.client{background:#dbeafe;color:#1e40af}
        .role-badge.freelancer{background:#dcfce7;color:#166534}
        .role-badge.admin{background:#f3e8ff;color:#6b21a8}

        /* ── Summary Grid ────────────────────────── */
        .summary-title{font-size:13px;font-weight:700;color:#111827;margin-bottom:10px;display:flex;align-items:center;gap:6px}
        .summary-title i{color:#108A00;font-size:12px}
        .summary-grid{display:grid;grid-template-columns:repeat(5,1fr);gap:1px;background:#E4EBE4;border:1px solid #E4EBE4;border-radius:10px;overflow:hidden;margin-bottom:28px}
        .summary-cell{background:#fff;padding:14px 12px;text-align:center}
        .summary-cell .s-label{font-size:9px;font-weight:700;color:#9ca3af;text-transform:uppercase;letter-spacing:.04em;margin-bottom:4px}
        .summary-cell .s-value{font-size:15px;font-weight:800;color:#111827}
        .summary-cell .s-value.positive{color:#059669}
        .summary-cell .s-value.negative{color:#dc2626}
        .summary-cell .s-value.neutral{color:#111827}

        /* ── Transactions Table ──────────────────── */
        .tx-title{font-size:13px;font-weight:700;color:#111827;margin-bottom:10px;display:flex;align-items:center;gap:6px}
        .tx-title i{color:#108A00;font-size:12px}
        .tx-table{width:100%;border-collapse:collapse;border:1px solid #E4EBE4;border-radius:10px;overflow:hidden;margin-bottom:24px}
        .tx-table th{background:#F3F4F6;padding:10px 14px;text-align:left;font-size:9px;font-weight:700;color:#6b7280;text-transform:uppercase;letter-spacing:.04em;border-bottom:1px solid #E4EBE4}
        .tx-table td{padding:10px 14px;font-size:12px;color:#374151;border-bottom:1px solid #f3f4f6}
        .tx-table tbody tr:last-child td{border-bottom:none}
        .tx-table .amt-pos{color:#059669;font-weight:700}
        .tx-table .amt-neg{color:#dc2626;font-weight:700}
        .tx-table .balance-col{font-weight:600;color:#111827}
        .tx-table .desc-col{color:#6b7280;font-size:11px}
        .tx-table .date-col{color:#9ca3af;font-size:11px;white-space:nowrap}

        /* Empty state */
        .empty-state{text-align:center;padding:48px 24px;margin-bottom:24px}
        .empty-state i{font-size:36px;color:#d1d5db;margin-bottom:12px}
        .empty-state p{font-size:13px;color:#6b7280;font-weight:500}

        /* ── Footer ──────────────────────────────── */
        .stmt-footer{padding-top:16px;border-top:1px solid #E4EBE4;display:flex;justify-content:space-between;align-items:center;font-size:10px;color:#9ca3af;font-weight:500}
    </style>
</head>
<body>

<div class="top-bar">
    <a href="wallet_history.php?user_id=<?= $userId ?>" class="btn"><i data-lucide="arrow-left"></i> Back</a>
    <span class="top-bar-title">Wallet Statement — <?= sanitize_string($eu['name']) ?></span>
    <div class="btn-group">
        <button class="btn" onclick="window.print()"><i data-lucide="printer"></i> Print</button>
        <button class="btn btn-primary" id="pdfBtn" onclick="downloadPDF()"><i data-lucide="download"></i> <span id="pdfBtnText">Download PDF</span></button>
    </div>
</div>

<div class="statement-page" id="stmtPage">

    <!-- ═══ HEADER ═══════════════════════════════════════════════════════ -->
    <div class="stmt-header">
        <img src="../assets/upload/logos/logo.png" alt="JobHub" class="stmt-logo">
        <div class="stmt-header-text">
            <h1>Wallet Statement</h1>
            <p class="subtitle">JobHub Freelance Platform &bull; Generated <?= $generatedDate ?></p>
        </div>
    </div>

    <!-- ═══ OWNER INFO ═══════════════════════════════════════════════════ -->
    <div class="owner-section">
        <div>
            <div class="owner-field-label">Wallet Owner</div>
            <div class="owner-field-value"><?= sanitize_string($eu['name']) ?></div>
        </div>
        <div>
            <div class="owner-field-label">Role</div>
            <div class="owner-field-value"><span class="role-badge <?= $eu['role'] ?>"><?= ucfirst($eu['role']) ?></span></div>
        </div>
        <div>
            <div class="owner-field-label">Email</div>
            <div class="owner-field-value"><?= sanitize_string($eu['email']) ?></div>
        </div>
    </div>

    <!-- ═══ WALLET SUMMARY ═══════════════════════════════════════════════ -->
    <div class="summary-title"><i data-lucide="bar-chart"></i> Wallet Summary</div>
    <div class="summary-grid">
        <div class="summary-cell">
            <div class="s-label">Current Balance</div>
            <div class="s-value neutral"><?= format_currency((float) $eu['wallet_balance']) ?></div>
        </div>
        <div class="summary-cell">
            <div class="s-label">Total Deposits</div>
            <div class="s-value positive"><?= format_currency($totalDeposits) ?></div>
        </div>
        <div class="summary-cell">
            <div class="s-label">Total Escrow</div>
            <div class="s-value neutral"><?= format_currency($totalEscrow) ?></div>
        </div>
        <div class="summary-cell">
            <div class="s-label">Total Earnings</div>
            <div class="s-value positive"><?= format_currency($totalEarnings) ?></div>
        </div>
        <div class="summary-cell">
            <div class="s-label">Total Withdrawals</div>
            <div class="s-value negative"><?= format_currency($totalWithdrawals) ?></div>
        </div>
    </div>

    <!-- ═══ TRANSACTION HISTORY ══════════════════════════════════════════ -->
    <div class="tx-title"><i data-lucide="arrow-left-right"></i> Transaction History</div>

    <?php if (count($exTransactions) > 0): ?>
    <table class="tx-table">
        <thead>
            <tr>
                <th>Date</th>
                <th>Description</th>
                <th style="text-align:right">Amount</th>
                <th style="text-align:right">Balance</th>
            </tr>
        </thead>
        <tbody>
        <?php foreach ($exTransactions as $tx):
            $isPos = in_array($tx['type'], ['deposit', 'signup_bonus', 'refund', 'escrow_release']);
            $typeLabel = ucfirst(str_replace('_', ' ', $tx['type']));
            $desc = $tx['description'] ?: $typeLabel;
            if ($tx['reference_id'] && $tx['reference_type']) {
                $desc .= ' (' . ucfirst($tx['reference_type']) . ' #' . $tx['reference_id'] . ')';
            }
        ?>
            <tr>
                <td class="date-col"><?= date('M d, Y', strtotime($tx['created_at'])) ?></td>
                <td class="desc-col"><?= sanitize_string($desc) ?></td>
                <td style="text-align:right" class="<?= $isPos ? 'amt-pos' : 'amt-neg' ?>"><?= ($isPos ? '+' : '−') . format_currency(abs((float) $tx['amount'])) ?></td>
                <td style="text-align:right" class="balance-col"><?= format_currency((float) $tx['balance_after']) ?></td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
    <?php else: ?>
    <div class="empty-state">
        <i data-lucide="receipt"></i>
        <p>No transactions recorded for this wallet.</p>
    </div>
    <?php endif; ?>

    <!-- ═══ FOOTER ═══════════════════════════════════════════════════════ -->
    <div class="stmt-footer">
        <span>Generated by Admin &bull; JobHub Freelance Platform</span>
        <span><?= $generatedDate ?></span>
    </div>

</div><!-- /statement-page -->

<script src="https://cdnjs.cloudflare.com/ajax/libs/html2pdf.js/0.10.1/html2pdf.bundle.min.js"></script>
<script>
function downloadPDF() {
    var btn = document.getElementById('pdfBtn');
    var txt = document.getElementById('pdfBtnText');
    btn.disabled = true;
    txt.textContent = 'Generating...';

    var element = document.getElementById('stmtPage');
    var opt = {
        margin: 0,
        filename: 'WalletStatement_<?= $safeName ?>_<?= date('Y-m-d') ?>.pdf',
        image: { type: 'jpeg', quality: 0.98 },
        html2canvas: { scale: 2, useCORS: true, letterRendering: true },
        jsPDF: { unit: 'mm', format: 'a4', orientation: 'portrait' },
        pagebreak: { mode: ['avoid-all', 'css', 'legacy'] }
    };

    html2pdf().set(opt).from(element).save().then(function() {
        btn.disabled = false;
        txt.textContent = 'Download PDF';
    }).catch(function() {
        btn.disabled = false;
        txt.textContent = 'Download PDF';
        alert('PDF generation failed. Please use Print instead.');
    });
}

/* Auto-generate PDF on load */
window.addEventListener('load', function() {
    setTimeout(function() { downloadPDF(); }, 500);
});
</script>
</body>
</html>
<?php
    exit;
}
// ═══ END EXPORT ═══════════════════════════════════════════════════════

// ═══ ADJUST BALANCE HANDLER ══════════════════════════════════════════
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['adjust_balance'])) {
    $adjustType   = $_POST['adjust_type'] ?? '';
    $adjustAmount = (float) ($_POST['adjust_amount'] ?? 0);
    $adjustReason = trim($_POST['adjust_reason'] ?? '');
    $adjustNotes  = trim($_POST['adjust_notes'] ?? '');
    $confirmAdj   = isset($_POST['confirm_adjust']);

    $adjustError = '';

    if (!$confirmAdj) {
        $adjustError = 'Please confirm the adjustment by ticking the checkbox.';
    } elseif (!in_array($adjustType, ['credit', 'debit'])) {
        $adjustError = 'Invalid adjustment type.';
    } elseif ($adjustAmount <= 0) {
        $adjustError = 'Amount must be greater than zero.';
    } elseif (empty($adjustReason)) {
        $adjustError = 'Reason is required.';
    }

    if (empty($adjustError)) {
        // Fetch current balance
        $abStmt = $conn->prepare('SELECT wallet_balance FROM users WHERE id = ?');
        $abStmt->bind_param('i', $userId);
        $abStmt->execute();
        $curBalance = (float) $abStmt->get_result()->fetch_assoc()['wallet_balance'];
        $abStmt->close();

        $newBalance = $adjustType === 'credit' ? $curBalance + $adjustAmount : $curBalance - $adjustAmount;

        if ($newBalance < 0) {
            $adjustError = 'Insufficient balance. Current balance is ' . format_currency($curBalance) . '. Cannot debit ' . format_currency($adjustAmount) . '.';
        }
    }

    if (empty($adjustError)) {
        $txType = $adjustType === 'credit' ? 'deposit' : 'withdrawal';
        $txDesc = 'Manual ' . $adjustType . ' by admin';
        if ($adjustReason) {
            $txDesc .= ': ' . $adjustReason;
        }
        if ($adjustNotes) {
            $txDesc .= ' [' . $adjustNotes . ']';
        }

        $conn->begin_transaction();
        try {
            // Insert wallet transaction
            $itStmt = $conn->prepare('INSERT INTO wallet_transactions (user_id, type, amount, balance_after, reference_id, reference_type, description) VALUES (?, ?, ?, ?, NULL, NULL, ?)');
            $itStmt->bind_param('isdds', $userId, $txType, $adjustAmount, $newBalance, $txDesc);
            $itStmt->execute();
            if ($itStmt->affected_rows === 0) throw new Exception('Failed to insert transaction.');
            $itStmt->close();

            // Update wallet balance
            $ubStmt = $conn->prepare('UPDATE users SET wallet_balance = ? WHERE id = ?');
            $ubStmt->bind_param('di', $newBalance, $userId);
            $ubStmt->execute();
            if ($ubStmt->affected_rows === 0 && $conn->affected_rows === 0) throw new Exception('Failed to update wallet balance.');
            $ubStmt->close();

            $conn->commit();
            $_SESSION['flash_success'] = 'Balance adjusted successfully. ' . ucfirst($adjustType) . ' of ' . format_currency($adjustAmount) . ' recorded.';
        } catch (Exception $e) {
            $conn->rollback();
            $adjustError = 'Adjustment failed. Please try again.';
        }
    }

    if (!empty($adjustError)) {
        $_SESSION['flash_error'] = $adjustError;
    }

    header('Location: wallet_history.php?user_id=' . $userId);
    exit;
}
// ═══ END ADJUST BALANCE HANDLER ══════════════════════════════════════

// ═══ PROCESS REFUND HANDLER ══════════════════════════════════════════
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['process_refund'])) {
    $refundMilestoneId = sanitize_int($_POST['refund_milestone_id'] ?? 0);
    $refundReason      = trim($_POST['refund_reason'] ?? '');
    $confirmRefund     = isset($_POST['confirm_refund']);

    $refundError = '';

    if (!$confirmRefund) {
        $refundError = 'Please confirm the refund by ticking the checkbox.';
    } elseif ($refundMilestoneId <= 0) {
        $refundError = 'Invalid milestone selected.';
    } elseif (empty($refundReason)) {
        $refundError = 'Refund reason is required.';
    }

    if (empty($refundError)) {
        // Fetch milestone + contract info
        $rmStmt = $conn->prepare('SELECT m.id, m.title, m.amount, m.status, m.contract_id,
                                         c.client_id, c.freelancer_id, j.title AS contract_title
                                  FROM milestones m
                                  JOIN contracts c ON m.contract_id = c.id
                                  JOIN jobs j ON c.job_id = j.id
                                  WHERE m.id = ?');
        $rmStmt->bind_param('i', $refundMilestoneId);
        $rmStmt->execute();
        $rmRow = $rmStmt->get_result()->fetch_assoc();
        $rmStmt->close();

        if (!$rmRow) {
            $refundError = 'Milestone not found.';
        } else {
            $clientId    = (int) $rmRow['client_id'];
            $amount      = (float) $rmRow['amount'];
            $msStatus    = $rmRow['status'];
            $isEscrowHeld = ($msStatus === 'funded_in_escrow');
            $isDisputeResolved = false;

            // Check if dispute resolved in client's favor
            if ($msStatus === 'disputed') {
                $dtStmt = $conn->prepare('SELECT id FROM dispute_tickets WHERE milestone_id = ? AND status = ? LIMIT 1');
                $resolved = 'resolved';
                $dtStmt->bind_param('is', $refundMilestoneId, $resolved);
                $dtStmt->execute();
                $isDisputeResolved = $dtStmt->get_result()->num_rows > 0;
                $dtStmt->close();
            }

            if (!$isEscrowHeld && !$isDisputeResolved) {
                $refundError = 'This milestone is not eligible for refund. Escrow must be held or dispute must be resolved in client\'s favor.';
            }

            // Prevent duplicate refunds
            if (empty($refundError)) {
                $dupStmt = $conn->prepare('SELECT id FROM wallet_transactions WHERE user_id = ? AND type = ? AND reference_id = ? AND reference_type = ? LIMIT 1');
                $refType = 'milestone';
                $refTypeStr = 'milestone';
                $dupType = 'refund';
                $dupStmt->bind_param('issi', $clientId, $dupType, $refundMilestoneId, $refTypeStr);
                $dupStmt->execute();
                if ($dupStmt->get_result()->num_rows > 0) {
                    $refundError = 'A refund has already been processed for this milestone.';
                }
                $dupStmt->close();
            }
        }
    }

    if (empty($refundError)) {
        $clientId   = (int) $rmRow['client_id'];
        $amount     = (float) $rmRow['amount'];
        $contractId = (int) $rmRow['contract_id'];

        // Fetch client current balance
        $cbStmt = $conn->prepare('SELECT wallet_balance FROM users WHERE id = ?');
        $cbStmt->bind_param('i', $clientId);
        $cbStmt->execute();
        $clientBalance = (float) $cbStmt->get_result()->fetch_assoc()['wallet_balance'];
        $cbStmt->close();

        $newClientBalance = round($clientBalance + $amount, 2);

        $conn->begin_transaction();
        try {
            // 1. Credit client wallet
            $cuStmt = $conn->prepare('UPDATE users SET wallet_balance = ? WHERE id = ?');
            $cuStmt->bind_param('di', $newClientBalance, $clientId);
            $cuStmt->execute();
            if ($cuStmt->affected_rows === 0 && $conn->affected_rows === 0) throw new Exception('Failed to update client wallet.');
            $cuStmt->close();

            // 2. Create wallet transaction (refund)
            $wtDesc = 'Refund for milestone #' . $refundMilestoneId . ': ' . $refundReason;
            $wtStmt = $conn->prepare('INSERT INTO wallet_transactions (user_id, type, amount, balance_after, reference_id, reference_type, description) VALUES (?, ?, ?, ?, ?, ?, ?)');
            $wtType = 'refund';
            $refTypeVal = 'milestone';
            $wtStmt->bind_param('isddiss', $clientId, $wtType, $amount, $newClientBalance, $refundMilestoneId, $refTypeVal, $wtDesc);
            $wtStmt->execute();
            if ($wtStmt->affected_rows === 0) throw new Exception('Failed to create wallet transaction.');
            $wtStmt->close();

            // 3. Update milestone status
            $msStmt = $conn->prepare('UPDATE milestones SET status = ?, updated_at = NOW() WHERE id = ?');
            $newMsStatus = 'pending';
            $msStmt->bind_param('si', $newMsStatus, $refundMilestoneId);
            $msStmt->execute();
            $msStmt->close();

            // 4. Update existing payment record (if exists)
            $payStmt = $conn->prepare("UPDATE payments SET status = 'refunded', refund_amount = ?, refund_reason = ?, refunded_at = NOW() WHERE milestone_id = ? AND status IN ('pending','processing','completed')");
            $payStmt->bind_param('dsi', $amount, $refundReason, $refundMilestoneId);
            $payStmt->execute();
            $payStmt->close();

            // If no payment record exists, create one for audit trail
            if ($conn->affected_rows === 0) {
                $fpStmt = $conn->prepare('INSERT INTO payments (milestone_id, payer_id, payee_id, total_amount, platform_fee, freelancer_net, status, refund_amount, refund_reason, refunded_at, payment_method) VALUES (?, ?, ?, ?, 0.00, 0.00, ?, ?, ?, NOW(), ?)');
                $payStatus = 'refunded';
                $payMethod = 'wallet';
                $fpStmt->bind_param('iiidsss', $refundMilestoneId, $clientId, $rmRow['freelancer_id'], $amount, $payStatus, $amount, $refundReason, $payMethod);
                $fpStmt->execute();
                $fpStmt->close();
            }

            // 5. Log admin action
            $logPayload = json_encode([
                'milestone_id'    => $refundMilestoneId,
                'contract_id'     => $contractId,
                'amount'          => $amount,
                'client_id'       => $clientId,
                'old_balance'     => $clientBalance,
                'new_balance'     => $newClientBalance,
                'reason'          => $refundReason,
                'milestone_status'=> $rmRow['status'],
            ]);
            $ip = get_ip_address();
            $logStmt = $conn->prepare('INSERT INTO user_behavior_logs (user_id, action_type, ip_address, payload, created_at) VALUES (?, ?, ?, ?, NOW())');
            $logType = 'admin_wallet_refund';
            $logStmt->bind_param('siss', $clientId, $logType, $ip, $logPayload);
            $logStmt->execute();
            $logStmt->close();

            $conn->commit();
            $_SESSION['flash_success'] = 'Refund of ' . format_currency($amount) . ' processed successfully. Funds returned to client wallet.';
        } catch (Exception $e) {
            $conn->rollback();
            $refundError = 'Refund processing failed. Please try again.';
        }
    }

    if (!empty($refundError)) {
        $_SESSION['flash_error'] = $refundError;
    }

    header('Location: wallet_history.php?user_id=' . $userId);
    exit;
}
// ═══ END PROCESS REFUND HANDLER ══════════════════════════════════════

// ═══ FREEZE WALLET HANDLER ═══════════════════════════════════════════
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['freeze_wallet'])) {
    $freezeReason  = trim($_POST['freeze_reason'] ?? '');
    $freezeType    = $_POST['freeze_type'] ?? 'permanent';
    $freezeNotes   = trim($_POST['freeze_notes'] ?? '');
    $confirmFreeze = isset($_POST['confirm_freeze']);

    $freezeError = '';

    if (!$confirmFreeze) {
        $freezeError = 'Please confirm the wallet freeze by ticking the checkbox.';
    } elseif (empty($freezeReason)) {
        $freezeError = 'Reason is required.';
    } elseif (!in_array($freezeType, ['permanent', 'temporary'])) {
        $freezeError = 'Invalid freeze type.';
    }

    if (empty($freezeError)) {
        // Check current wallet status
        $fsStmt = $conn->prepare('SELECT wallet_status FROM users WHERE id = ?');
        $fsStmt->bind_param('i', $userId);
        $fsStmt->execute();
        $curStatus = $fsStmt->get_result()->fetch_assoc()['wallet_status'];
        $fsStmt->close();

        if ($curStatus === 'frozen') {
            $freezeError = 'Wallet is already frozen.';
        }
    }

    if (empty($freezeError)) {
        $conn->begin_transaction();
        try {
            // Update wallet status to frozen
            $fuStmt = $conn->prepare("UPDATE users SET wallet_status = 'frozen' WHERE id = ?");
            $fuStmt->bind_param('i', $userId);
            $fuStmt->execute();
            if ($fuStmt->affected_rows === 0 && $conn->affected_rows === 0) throw new Exception('Failed to freeze wallet.');
            $fuStmt->close();

            // Log to wallet_transactions (read-only informational record)
            $fzDesc = 'Wallet frozen by admin';
            if ($freezeType === 'temporary') {
                $fzDesc .= ' (temporary)';
            }
            $fzDesc .= ': ' . $freezeReason;
            if ($freezeNotes) {
                $fzDesc .= ' [' . $freezeNotes . ']';
            }
            $fzAmt = 0.00;
            $fzBal = (float) $target['wallet_balance'];
            $fzStmt = $conn->prepare('INSERT INTO wallet_transactions (user_id, type, amount, balance_after, reference_id, reference_type, description) VALUES (?, ?, ?, ?, NULL, NULL, ?)');
            $fzType = 'platform_fee';
            $fzStmt->bind_param('isdds', $userId, $fzType, $fzAmt, $fzBal, $fzDesc);
            $fzStmt->execute();
            $fzStmt->close();

            // Log admin action
            $logPayload = json_encode([
                'user_id'        => $userId,
                'action'         => 'freeze_wallet',
                'freeze_type'    => $freezeType,
                'reason'         => $freezeReason,
                'admin_notes'    => $freezeNotes,
                'wallet_balance' => $fzBal,
            ]);
            $ip = get_ip_address();
            $logStmt = $conn->prepare('INSERT INTO user_behavior_logs (user_id, action_type, ip_address, payload, created_at) VALUES (?, ?, ?, ?, NOW())');
            $logType = 'admin_wallet_freeze';
            $logStmt->bind_param('siss', $userId, $logType, $ip, $logPayload);
            $logStmt->execute();
            $logStmt->close();

            $conn->commit();
            $_SESSION['flash_success'] = 'Wallet has been frozen successfully. Deposits, escrow funding, and withdrawals are now disabled.';
        } catch (Exception $e) {
            $conn->rollback();
            $freezeError = 'Failed to freeze wallet. Please try again.';
        }
    }

    if (!empty($freezeError)) {
        $_SESSION['flash_error'] = $freezeError;
    }

    header('Location: wallet_history.php?user_id=' . $userId);
    exit;
}
// ═══ END FREEZE WALLET HANDLER ═══════════════════════════════════════

// ═══ UNFREEZE WALLET HANDLER ═════════════════════════════════════════
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['unfreeze_wallet'])) {
    $confirmUnfreeze = isset($_POST['confirm_unfreeze']);
    $unfreezeReason  = trim($_POST['unfreeze_reason'] ?? '');

    $unfreezeError = '';

    if (!$confirmUnfreeze) {
        $unfreezeError = 'Please confirm the wallet unfreeze by ticking the checkbox.';
    } elseif (empty($unfreezeReason)) {
        $unfreezeError = 'Reason is required.';
    }

    if (empty($unfreezeError)) {
        // Check current wallet status
        $usStmt = $conn->prepare('SELECT wallet_status FROM users WHERE id = ?');
        $usStmt->bind_param('i', $userId);
        $usStmt->execute();
        $curStatus = $usStmt->get_result()->fetch_assoc()['wallet_status'];
        $usStmt->close();

        if ($curStatus !== 'frozen') {
            $unfreezeError = 'Wallet is not currently frozen.';
        }
    }

    if (empty($unfreezeError)) {
        $conn->begin_transaction();
        try {
            // Update wallet status to active
            $uuStmt = $conn->prepare("UPDATE users SET wallet_status = 'active' WHERE id = ?");
            $uuStmt->bind_param('i', $userId);
            $uuStmt->execute();
            if ($uuStmt->affected_rows === 0 && $conn->affected_rows === 0) throw new Exception('Failed to unfreeze wallet.');
            $uuStmt->close();

            // Log to wallet_transactions
            $uzDesc = 'Wallet unfrozen by admin: ' . $unfreezeReason;
            $uzAmt = 0.00;
            $uzBal = (float) $target['wallet_balance'];
            $uzStmt = $conn->prepare('INSERT INTO wallet_transactions (user_id, type, amount, balance_after, reference_id, reference_type, description) VALUES (?, ?, ?, ?, NULL, NULL, ?)');
            $uzType = 'platform_fee';
            $uzStmt->bind_param('isdds', $userId, $uzType, $uzAmt, $uzBal, $uzDesc);
            $uzStmt->execute();
            $uzStmt->close();

            // Log admin action
            $logPayload = json_encode([
                'user_id'        => $userId,
                'action'         => 'unfreeze_wallet',
                'reason'         => $unfreezeReason,
                'wallet_balance' => $uzBal,
            ]);
            $ip = get_ip_address();
            $logStmt = $conn->prepare('INSERT INTO user_behavior_logs (user_id, action_type, ip_address, payload, created_at) VALUES (?, ?, ?, ?, NOW())');
            $logType = 'admin_wallet_unfreeze';
            $logStmt->bind_param('siss', $userId, $logType, $ip, $logPayload);
            $logStmt->execute();
            $logStmt->close();

            $conn->commit();
            $_SESSION['flash_success'] = 'Wallet has been unfrozen successfully. Deposits, escrow funding, and withdrawals are now restored.';
        } catch (Exception $e) {
            $conn->rollback();
            $unfreezeError = 'Failed to unfreeze wallet. Please try again.';
        }
    }

    if (!empty($unfreezeError)) {
        $_SESSION['flash_error'] = $unfreezeError;
    }

    header('Location: wallet_history.php?user_id=' . $userId);
    exit;
}
// ═══ END UNFREEZE WALLET HANDLER ═════════════════════════════════════

$navItems = [
    ['key' => 'dashboard', 'label' => 'Dashboard', 'url' => 'dashboard.php', 'icon' => 'layout-grid'],
    ['key' => 'users', 'label' => 'Users', 'url' => 'users.php', 'icon' => 'users'],
    ['key' => 'clients', 'label' => 'Clients', 'url' => 'clients.php', 'icon' => 'user'],
    ['key' => 'jobs', 'label' => 'Jobs', 'url' => 'jobs.php', 'icon' => 'briefcase'],
    ['key' => 'categories', 'label' => 'Categories', 'url' => 'categories.php', 'icon' => 'folder-open'],
    ['key' => 'skills', 'label' => 'Skills', 'url' => 'skills.php', 'icon' => 'settings'],
    ['key' => 'payments', 'label' => 'Payments', 'url' => 'payments.php', 'icon' => 'credit-card'],
    ['key' => 'wallets', 'label' => 'Wallets', 'url' => 'wallets.php', 'icon' => 'wallet'],
    ['key' => 'contracts', 'label' => 'Contracts', 'url' => 'contracts.php', 'icon' => 'file-text'],
    ['key' => 'milestones', 'label' => 'Milestones', 'url' => 'milestones.php', 'icon' => 'list-checks'],
    ['key' => 'reviews', 'label' => 'Reviews', 'url' => 'reviews.php', 'icon' => 'star'],
    ['key' => 'disputes', 'label' => 'Disputes', 'url' => 'disputes.php', 'icon' => 'hammer'],
    ['key' => 'fraud', 'label' => 'Fraud', 'url' => 'fraud_detection.php', 'icon' => 'shield'],
    ['key' => 'notifications', 'label' => 'Notifications', 'url' => 'notifications.php', 'icon' => 'bell'],
    ['key' => 'ai_monitor', 'label' => 'AI Monitor', 'url' => 'ai_monitor.php', 'icon' => 'brain'],
    ['key' => 'analytics', 'label' => 'Analytics', 'url' => 'analytics.php', 'icon' => 'pie-chart'],
    ['key' => 'settings', 'label' => 'Settings', 'url' => 'settings.php', 'icon' => 'settings'],
];
$pageTitle = 'Wallet History — ' . sanitize_string($target['name']);
$pageSubtitle = 'Transaction history';
$activePage = 'wallets';
$user = ['name' => $adminName, 'profile_image' => $_navUserRow['profile_image'] ?? null];
$unreadCount = 0;
$profileLink = 'profile.php';

// ═══ AJAX MODE ═══════════════════════════════════════════════════════
$isAjax = (isset($_GET['ajax']) && (int) $_GET['ajax'] === 1);
// ═════════════════════════════════════════════════════════════════════

if (!$isAjax) {
    require_once __DIR__ . '/../components/layout_start.php';
}
?>

<style>
/* Drawer overlay */
.drawer-overlay{position:fixed;inset:0;background:rgba(0,0,0,.4);backdrop-filter:blur(4px);z-index:40;opacity:0;visibility:hidden;transition:all .25s}
.drawer-overlay.open{opacity:1;visibility:visible}
/* Drawer panel */
.drawer-panel{position:fixed;top:0;right:0;bottom:0;width:100%;max-width:56rem;background:#fff;z-index:50;transform:translateX(100%);transition:transform .3s cubic-bezier(.4,0,.2,1);display:flex;flex-direction:column;overflow:hidden}
html.dark .drawer-panel{background:#0f172a}
.drawer-panel.open{transform:translateX(0)}
@media(max-width:640px){.drawer-panel{max-width:100%}}
/* Drawer header */
.drawer-header{flex-shrink:0;padding:16px 24px;border-bottom:1px solid #E4EBE4;display:flex;align-items:center;justify-content:space-between;gap:12px}
html.dark .drawer-header{border-color:#334155}
/* Drawer body */
.drawer-body{flex:1;overflow-y:auto;padding:24px}
/* Close btn */
.drawer-close{width:36px;height:36px;border-radius:8px;border:1px solid #E4EBE4;background:#fff;display:inline-flex;align-items:center;justify-content:center;cursor:pointer;color:#6b7280;transition:all .15s;flex-shrink:0}
.drawer-close:hover{background:#f9fafb;color:#374151;border-color:#d1d5db}
html.dark .drawer-close{border-color:#475569;background:#1e293b;color:#94a3b8}
html.dark .drawer-close:hover{background:#334155;color:#e2e8f0}
/* Filter chips */
.filter-chip{display:inline-flex;align-items:center;gap:4px;padding:6px 12px;border-radius:6px;border:1px solid #E4EBE4;background:#fff;font-size:12px;font-weight:500;color:#6b7280;cursor:pointer;transition:all .15s;text-decoration:none}
.filter-chip:hover{background:#f9fafb;border-color:#d1d5db}
html.dark .filter-chip{border-color:#475569;background:#1e293b;color:#94a3b8}
html.dark .filter-chip:hover{background:#334155;border-color:#64748b}
.filter-chip.active{background:#108A00;border-color:#108A00;color:#fff}
html.dark .filter-chip.active{background:#108A00;border-color:#108A00;color:#fff}
/* TX table */
.tx-drawer-table th{position:sticky;top:0;background:#F9FAFB;z-index:1}
html.dark .tx-drawer-table th{background:#1e293b}
.tx-drawer-table td,.tx-drawer-table th{padding:10px 12px}
.tx-drawer-table tr{border-bottom:1px solid #f3f4f6;transition:background .1s}
html.dark .tx-drawer-table tr{border-color:#1e293b}
.tx-drawer-table tbody tr:hover{background:#f9fafb}
html.dark .tx-drawer-table tbody tr:hover{background:rgba(30,41,59,.4)}
/* Status badges */
.status-badge{display:inline-flex;align-items:center;gap:4px;padding:2px 8px;border-radius:4px;font-size:10px;font-weight:600;text-transform:uppercase;letter-spacing:.03em}
.status-completed{background:#dcfce7;color:#166534}
.status-pending{background:#fef9c3;color:#854d0e}
.status-processing{background:#dbeafe;color:#1e40af}
.status-failed{background:#fee2e2;color:#991b1b}
.status-refunded{background:#f3e8ff;color:#6b21a8}
html.dark .status-completed{background:rgba(34,197,94,.15);color:#4ade80}
html.dark .status-pending{background:rgba(234,179,8,.15);color:#facc15}
html.dark .status-processing{background:rgba(59,130,246,.15);color:#60a5fa}
html.dark .status-failed{background:rgba(239,68,68,.15);color:#f87171}
html.dark .status-refunded{background:rgba(168,85,247,.15);color:#c084fc}
/* Amount colors */
.amt-positive{color:#059669}
.amt-negative{color:#dc2626}
html.dark .amt-positive{color:#34d399}
html.dark .amt-negative{color:#f87171}
/* Notification */
.adj-notif{position:fixed;top:20px;right:20px;z-index:100;padding:14px 20px;border-radius:10px;display:flex;align-items:center;gap:10px;font-size:13px;font-weight:600;box-shadow:0 8px 24px rgba(0,0,0,.12);transform:translateX(120%);transition:transform .35s cubic-bezier(.4,0,.2,1)}
.adj-notif.show{transform:translateX(0)}
.adj-notif.success{background:#ecfdf5;border:1px solid #a7f3d0;color:#065f46}
.adj-notif.error{background:#fef2f2;border:1px solid #fecaca;color:#991b1b}
html.dark .adj-notif.success{background:rgba(16,185,129,.12);border-color:rgba(16,185,129,.25);color:#6ee7b7}
html.dark .adj-notif.error{background:rgba(239,68,68,.12);border-color:rgba(239,68,68,.25);color:#fca5a5}
/* Adjust modal overlay */
.adj-modal-overlay{position:fixed;inset:0;background:rgba(0,0,0,.5);backdrop-filter:blur(4px);z-index:60;display:flex;align-items:center;justify-content:center;opacity:0;visibility:hidden;transition:all .2s}
.adj-modal-overlay.open{opacity:1;visibility:visible}
.adj-modal{background:#fff;border-radius:14px;width:90%;max-width:28rem;box-shadow:0 20px 60px rgba(0,0,0,.2);transform:scale(.95) translateY(10px);transition:transform .25s cubic-bezier(.4,0,.2,1)}
html.dark .adj-modal{background:#1e293b;border:1px solid #334155}
.adj-modal-overlay.open .adj-modal{transform:scale(1) translateY(0)}
.adj-modal-header{padding:20px 24px 16px;border-bottom:1px solid #E4EBE4;display:flex;align-items:center;justify-content:space-between}
html.dark .adj-modal-header{border-color:#334155}
.adj-modal-header h3{font-size:15px;font-weight:700;color:#111827;margin:0}
html.dark .adj-modal-header h3{color:#f1f5f9}
.adj-modal-close{width:32px;height:32px;border-radius:8px;border:1px solid #E4EBE4;background:#fff;display:inline-flex;align-items:center;justify-content:center;cursor:pointer;color:#6b7280;transition:all .15s}
.adj-modal-close:hover{background:#f9fafb;color:#374151}
html.dark .adj-modal-close{border-color:#475569;background:#1e293b;color:#94a3b8}
html.dark .adj-modal-close:hover{background:#334155;color:#e2e8f0}
.adj-modal-body{padding:20px 24px}
.adj-modal-footer{padding:16px 24px;border-top:1px solid #E4EBE4;display:flex;justify-content:flex-end;gap:10px}
html.dark .adj-modal-footer{border-color:#334155}
.adj-field{margin-bottom:16px}
.adj-field-label{display:block;font-size:11px;font-weight:600;color:#6b7280;margin-bottom:5px;text-transform:uppercase;letter-spacing:.03em}
html.dark .adj-field-label{color:#94a3b8}
.adj-field input[type="number"],.adj-field input[type="text"],.adj-field textarea,.adj-field select{width:100%;padding:9px 12px;border-radius:8px;border:1px solid #d1d5db;font-size:13px;color:#111827;background:#f9fafb;transition:all .15s;outline:none}
.adj-field input:focus,.adj-field textarea:focus,.adj-field select:focus{border-color:#108A00;box-shadow:0 0 0 3px rgba(16,138,0,.1)}
html.dark .adj-field input,html.dark .adj-field textarea,html.dark .adj-field select{border-color:#475569;background:#0f172a;color:#e2e8f0}
html.dark .adj-field input:focus,html.dark .adj-field textarea:focus,html.dark .adj-field select:focus{border-color:#108A00}
.adj-field textarea{resize:vertical;min-height:60px}
.adj-type-group{display:flex;gap:8px}
.adj-type-btn{flex:1;padding:10px;border-radius:8px;border:1px solid #d1d5db;background:#f9fafb;cursor:pointer;text-align:center;font-size:12px;font-weight:600;color:#6b7280;transition:all .15s}
.adj-type-btn:hover{border-color:#9ca3af}
.adj-type-btn.selected{border-color:#108A00;background:#ecfdf5;color:#065f46}
.adj-type-btn.selected.debit{border-color:#dc2626;background:#fef2f2;color:#991b1b}
html.dark .adj-type-btn{border-color:#475569;background:#0f172a;color:#94a3b8}
html.dark .adj-type-btn.selected{border-color:#108A00;background:rgba(16,138,0,.12);color:#6ee7b7}
html.dark .adj-type-btn.selected.debit{border-color:#dc2626;background:rgba(220,38,38,.12);color:#fca5a5}
.adj-check-row{display:flex;align-items:flex-start;gap:10px;padding:12px;border-radius:8px;background:#f9fafb;border:1px solid #e5e7eb}
html.dark .adj-check-row{background:#0f172a;border-color:#334155}
.adj-check-row input[type="checkbox"]{margin-top:2px;width:16px;height:16px;accent-color:#108A00;flex-shrink:0}
.adj-check-label{font-size:12px;color:#374151;line-height:1.5}
html.dark .adj-check-label{color:#cbd5e1}
.adj-balance-preview{margin-top:12px;padding:12px;border-radius:8px;background:#f0fdf4;border:1px solid #bbf7d0;display:none}
html.dark .adj-balance-preview{background:rgba(16,185,129,.08);border-color:rgba(16,185,129,.2)}
.adj-balance-preview .preview-row{display:flex;justify-content:space-between;font-size:12px;margin-bottom:4px}
.adj-balance-preview .preview-label{color:#6b7280;font-weight:500}
html.dark .adj-balance-preview .preview-label{color:#94a3b8}
.adj-balance-preview .preview-val{font-weight:700;color:#111827}
html.dark .adj-balance-preview .preview-val{color:#f1f5f9}
.adj-balance-preview .preview-val.new{color:#059669}
/* Modal buttons */
.btn-adj-cancel{padding:9px 18px;border-radius:8px;border:1px solid #d1d5db;background:#fff;font-size:12px;font-weight:600;color:#374151;cursor:pointer;transition:all .15s}
.btn-adj-cancel:hover{background:#f9fafb;border-color:#9ca3af}
html.dark .btn-adj-cancel{border-color:#475569;background:#1e293b;color:#cbd5e1}
html.dark .btn-adj-cancel:hover{background:#334155}
.btn-adj-save{padding:9px 18px;border-radius:8px;border:none;background:#d1d5db;font-size:12px;font-weight:600;color:#9ca3af;cursor:not-allowed;transition:all .15s}
.btn-adj-save.enabled{background:#108A00;color:#fff;cursor:pointer;box-shadow:0 2px 8px rgba(16,138,0,.25)}
.btn-adj-save.enabled:hover{background:#0d7500}
/* Refund modal */
.refund-badge{display:inline-flex;align-items:center;gap:4px;padding:3px 8px;border-radius:5px;font-size:10px;font-weight:700;text-transform:uppercase;letter-spacing:.03em}
.refund-badge.escrow{background:#fef3c7;color:#92400e}
.refund-badge.dispute{background:#dbeafe;color:#1e40af}
html.dark .refund-badge.escrow{background:rgba(251,191,36,.15);color:#fbbf24}
html.dark .refund-badge.dispute{background:rgba(96,165,250,.15);color:#60a5fa}
.refund-info-grid{display:grid;grid-template-columns:1fr 1fr;gap:10px;margin-bottom:16px}
.refund-info-item{padding:10px 12px;border-radius:8px;background:#f9fafb;border:1px solid #e5e7eb}
html.dark .refund-info-item{background:#0f172a;border-color:#334155}
.refund-info-label{font-size:9px;font-weight:700;color:#9ca3af;text-transform:uppercase;letter-spacing:.04em;margin-bottom:2px}
html.dark .refund-info-label{color:#64748b}
.refund-info-value{font-size:13px;font-weight:600;color:#111827}
html.dark .refund-info-value{color:#f1f5f9}
.refund-info-value.amount{color:#059669;font-size:15px;font-weight:800}
.refund-warning{padding:10px 14px;border-radius:8px;background:#fef3c7;border:1px solid #fde68a;font-size:11px;color:#92400e;font-weight:500;margin-bottom:16px;display:flex;align-items:flex-start;gap:8px}
html.dark .refund-warning{background:rgba(251,191,36,.08);border-color:rgba(251,191,36,.2);color:#fbbf24}
.refund-milestone-select{width:100%;padding:10px 12px;border-radius:8px;border:1px solid #d1d5db;font-size:13px;color:#111827;background:#f9fafb;transition:all .15s;outline:none;appearance:none;background-image:url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='12' height='12' fill='%236b7280' viewBox='0 0 16 16'%3E%3Cpath d='M1.646 4.646a.5.5 0 0 1 .708 0L8 10.293l5.646-5.647a.5.5 0 0 1 .708.708l-6 6a.5.5 0 0 1-.708 0l-6-6a.5.5 0 0 1 0-.708z'/%3E%3C/svg%3E");background-repeat:no-repeat;background-position:right 12px center}
.refund-milestone-select:focus{border-color:#dc2626;box-shadow:0 0 0 3px rgba(220,38,38,.1)}
html.dark .refund-milestone-select{border-color:#475569;background-color:#0f172a;color:#e2e8f0}
.btn-refund-cancel{padding:9px 18px;border-radius:8px;border:1px solid #d1d5db;background:#fff;font-size:12px;font-weight:600;color:#374151;cursor:pointer;transition:all .15s}
.btn-refund-cancel:hover{background:#f9fafb;border-color:#9ca3af}
html.dark .btn-refund-cancel{border-color:#475569;background:#1e293b;color:#cbd5e1}
html.dark .btn-refund-cancel:hover{background:#334155}
.btn-refund-confirm{padding:9px 18px;border-radius:8px;border:none;background:#d1d5db;font-size:12px;font-weight:600;color:#9ca3af;cursor:not-allowed;transition:all .15s}
.btn-refund-confirm.enabled{background:#dc2626;color:#fff;cursor:pointer;box-shadow:0 2px 8px rgba(220,38,38,.25)}
.btn-refund-confirm.enabled:hover{background:#b91c1c}
.refund-disabled-msg{font-size:11px;color:#9ca3af;margin-top:6px}
/* Freeze/Unfreeze modal */
.freeze-info-grid{display:grid;grid-template-columns:1fr 1fr;gap:10px;margin-bottom:16px}
.freeze-info-item{padding:10px 12px;border-radius:8px;background:#f9fafb;border:1px solid #e5e7eb}
html.dark .freeze-info-item{background:#0f172a;border-color:#334155}
.freeze-info-label{font-size:9px;font-weight:700;color:#9ca3af;text-transform:uppercase;letter-spacing:.04em;margin-bottom:2px}
html.dark .freeze-info-label{color:#64748b}
.freeze-info-value{font-size:13px;font-weight:600;color:#111827}
html.dark .freeze-info-value{color:#f1f5f9}
.freeze-type-group{display:flex;gap:8px}
.freeze-type-btn{flex:1;padding:10px;border-radius:8px;border:1px solid #d1d5db;background:#f9fafb;cursor:pointer;text-align:center;font-size:12px;font-weight:600;color:#6b7280;transition:all .15s}
.freeze-type-btn:hover{border-color:#9ca3af}
.freeze-type-btn.selected{border-color:#f59e0b;background:#fffbeb;color:#92400e}
html.dark .freeze-type-btn{border-color:#475569;background:#0f172a;color:#94a3b8}
html.dark .freeze-type-btn.selected{border-color:#f59e0b;background:rgba(245,158,11,.12);color:#fbbf24}
.btn-freeze-cancel{padding:9px 18px;border-radius:8px;border:1px solid #d1d5db;background:#fff;font-size:12px;font-weight:600;color:#374151;cursor:pointer;transition:all .15s}
.btn-freeze-cancel:hover{background:#f9fafb;border-color:#9ca3af}
html.dark .btn-freeze-cancel{border-color:#475569;background:#1e293b;color:#cbd5e1}
html.dark .btn-freeze-cancel:hover{background:#334155}
.btn-freeze-confirm{padding:9px 18px;border-radius:8px;border:none;background:#d1d5db;font-size:12px;font-weight:600;color:#9ca3af;cursor:not-allowed;transition:all .15s}
.btn-freeze-confirm.enabled{background:#f59e0b;color:#fff;cursor:pointer;box-shadow:0 2px 8px rgba(245,158,11,.25)}
.btn-freeze-confirm.enabled:hover{background:#d97706}
.btn-unfreeze-confirm{padding:9px 18px;border-radius:8px;border:none;background:#d1d5db;font-size:12px;font-weight:600;color:#9ca3af;cursor:not-allowed;transition:all .15s}
.btn-unfreeze-confirm.enabled{background:#059669;color:#fff;cursor:pointer;box-shadow:0 2px 8px rgba(5,150,105,.25)}
.btn-unfreeze-confirm.enabled:hover{background:#047857}
.frozen-banner{padding:10px 14px;border-radius:8px;background:#fef2f2;border:1px solid #fecaca;font-size:11px;color:#991b1b;font-weight:500;margin-bottom:16px;display:flex;align-items:flex-start;gap:8px}
html.dark .frozen-banner{background:rgba(239,68,68,.08);border-color:rgba(239,68,68,.2);color:#fca5a5}
</style>

<!-- ═══ DRAWER OVERLAY ═══════════════════════════════════════════════ -->
<div id="drawerOverlay" class="drawer-overlay" onclick="closeDrawer()"></div>

<!-- ═══ DRAWER PANEL ═════════════════════════════════════════════════ -->
<div id="drawerPanel" class="drawer-panel">

    <!-- Drawer Header -->
    <div class="drawer-header">
        <div class="flex items-center gap-3 min-w-0">
            <div class="w-10 h-10 rounded-xl <?= $target['wallet_status'] === 'frozen' ? 'bg-red-50 dark:bg-red-900/30' : 'bg-blue-50 dark:bg-blue-900/30' ?> flex items-center justify-center flex-shrink-0">
                <?php if ($target['wallet_status'] === 'frozen'): ?>
                    <svg class="w-5 h-5 text-red-500 dark:text-red-400" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"/></svg>
                <?php else: ?>
                    <svg class="w-5 h-5 text-blue-600 dark:text-blue-400" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M3 10h18M7 15h1m4 0h1m-7 4h12a3 3 0 003-3V8a3 3 0 00-3-3H6a3 3 0 00-3 3v8a3 3 0 003 3z"/></svg>
                <?php endif; ?>
            </div>
            <div class="min-w-0">
                <div class="flex items-center gap-2">
                    <h2 class="text-base font-bold text-gray-900 dark:text-white truncate">Transaction History</h2>
                    <?php if ($target['wallet_status'] === 'frozen'): ?>
                        <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded text-[9px] font-bold uppercase tracking-wider bg-red-100 text-red-700 dark:bg-red-900/30 dark:text-red-400">
                            <svg class="w-2.5 h-2.5" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM8.707 7.293a1 1 0 00-1.414 1.414L8.586 10l-1.293 1.293a1 1 0 101.414 1.414L10 11.414l1.293 1.293a1 1 0 001.414-1.414L11.414 10l1.293-1.293a1 1 0 00-1.414-1.414L10 8.586 8.707 7.293z" clip-rule="evenodd"/></svg>
                            Wallet Frozen
                        </span>
                    <?php endif; ?>
                </div>
                <p class="text-xs text-gray-400 dark:text-slate-500 truncate"><?= sanitize_string($target['name']) ?>'s wallet</p>
            </div>
        </div>
        <div class="flex items-center gap-2 flex-shrink-0">
            <button type="button" onclick="openAdjustModal()" class="px-3 py-1.5 rounded-lg bg-[#108A00] hover:bg-[#0d7500] text-white text-xs font-semibold transition-colors inline-flex items-center gap-1.5">
                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M12 6v12m6-6H6"/></svg>
                Adjust
            </button>
            <?php if (count($refundable) > 0): ?>
            <button type="button" onclick="openRefundModal()" class="px-3 py-1.5 rounded-lg bg-red-500 hover:bg-red-600 text-white text-xs font-semibold transition-colors inline-flex items-center gap-1.5">
                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M3 10h10a8 8 0 018 8v2M3 10l6 6m-6-6l6-6"/></svg>
                Refund
            </button>
            <?php endif; ?>
            <?php if ($target['wallet_status'] === 'frozen'): ?>
            <button type="button" onclick="openUnfreezeModal()" class="px-3 py-1.5 rounded-lg bg-emerald-500 hover:bg-emerald-600 text-white text-xs font-semibold transition-colors inline-flex items-center gap-1.5">
                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M8 11V7a4 4 0 118 0m-4 8v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2z"/></svg>
                Unfreeze
            </button>
            <?php else: ?>
            <button type="button" onclick="openFreezeModal()" class="px-3 py-1.5 rounded-lg bg-amber-500 hover:bg-amber-600 text-white text-xs font-semibold transition-colors inline-flex items-center gap-1.5">
                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"/></svg>
                Freeze
            </button>
            <?php endif; ?>
            <button type="button" class="drawer-close" onclick="closeDrawer()" title="Close">
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M18 6L6 18M6 6l12 12"/></svg>
            </button>
        </div>
    </div>

    <!-- Drawer Body -->
    <div class="drawer-body">

        <!-- ═══ OWNER INFO ═════════════════════════════════════════════ -->
        <div class="flex flex-col sm:flex-row items-start sm:items-center gap-4 mb-6">
            <img src="<?= sanitize_string(get_profile_image($target['profile_image'])) ?>" class="w-14 h-14 rounded-xl object-cover border-2 border-gray-100 dark:border-slate-700 flex-shrink-0">
            <div class="flex-1 min-w-0">
                <div class="flex items-center gap-2 mb-1">
                    <h3 class="text-lg font-bold text-gray-900 dark:text-white truncate"><?= sanitize_string($target['name']) ?></h3>
                    <span class="inline-block px-2 py-0.5 rounded text-[10px] font-semibold <?= $roleColors[$target['role']] ?? '' ?>"><?= ucfirst($target['role']) ?></span>
                    <?php if ($target['wallet_status'] === 'frozen'): ?>
                        <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded text-[9px] font-bold uppercase tracking-wider bg-red-100 text-red-700 dark:bg-red-900/30 dark:text-red-400">
                            <svg class="w-2.5 h-2.5" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM8.707 7.293a1 1 0 00-1.414 1.414L8.586 10l-1.293 1.293a1 1 0 101.414 1.414L10 11.414l1.293 1.293a1 1 0 001.414-1.414L11.414 10l1.293-1.293a1 1 0 00-1.414-1.414L10 8.586 8.707 7.293z" clip-rule="evenodd"/></svg>
                            Frozen
                        </span>
                    <?php endif; ?>
                </div>
                <p class="text-xs text-gray-400 dark:text-slate-500 truncate"><?= sanitize_string($target['email']) ?></p>
            </div>
            <div class="text-left sm:text-right flex-shrink-0">
                <p class="text-[10px] font-semibold text-gray-400 dark:text-slate-500 uppercase tracking-wider">Balance</p>
                <p class="text-xl font-extrabold text-gray-900 dark:text-white"><?= format_currency((float) $target['wallet_balance']) ?></p>
            </div>
        </div>

        <!-- ═══ FILTERS ════════════════════════════════════════════════ -->
        <form method="GET" action="wallet_history.php" id="filterForm" class="mb-5">
            <input type="hidden" name="user_id" value="<?= $userId ?>">

            <!-- Search -->
            <div class="relative mb-3">
                <svg class="absolute left-3 top-1/2 -translate-y-1/2 w-4 h-4 text-gray-400 dark:text-slate-500 pointer-events-none" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/></svg>
                <input type="text" name="search" value="<?= sanitize_string($search) ?>" placeholder="Search transactions..."
                    class="w-full pl-10 pr-4 py-2.5 rounded-lg border border-gray-200 dark:border-slate-600 text-sm bg-gray-50 dark:bg-slate-700 text-gray-900 dark:text-white placeholder-gray-400 dark:placeholder-slate-500 focus:outline-none focus:ring-2 focus:ring-[#108A00]/30 focus:border-[#108A00] transition-all">
            </div>

            <!-- Type Filter Chips -->
            <div class="flex flex-wrap gap-2 mb-3">
                <a href="<?= $userId ? 'wallet_history.php?user_id='.$userId : '#' ?>" class="filter-chip <?= $typeF === '' ? 'active' : '' ?>">All Types</a>
                <?php foreach ($allowedTypes as $t): ?>
                    <a href="wallet_history.php?user_id=<?= $userId ?>&type=<?= $t ?><?= $search ? '&search='.urlencode($search) : '' ?>" class="filter-chip <?= $typeF === $t ? 'active' : '' ?>"><?= ucfirst(str_replace('_', ' ', $t)) ?></a>
                <?php endforeach; ?>
            </div>

            <!-- Date Range -->
            <div class="flex flex-col sm:flex-row gap-2">
                <div class="flex-1">
                    <label class="block text-[10px] font-semibold text-gray-400 dark:text-slate-500 uppercase tracking-wider mb-1">From</label>
                    <input type="date" name="date_from" value="<?= sanitize_string($dateFrom) ?>" class="w-full px-3 py-2 rounded-lg border border-gray-200 dark:border-slate-600 text-sm bg-gray-50 dark:bg-slate-700 text-gray-900 dark:text-white focus:outline-none focus:ring-2 focus:ring-[#108A00]/30 focus:border-[#108A00] transition-all">
                </div>
                <div class="flex-1">
                    <label class="block text-[10px] font-semibold text-gray-400 dark:text-slate-500 uppercase tracking-wider mb-1">To</label>
                    <input type="date" name="date_to" value="<?= sanitize_string($dateTo) ?>" class="w-full px-3 py-2 rounded-lg border border-gray-200 dark:border-slate-600 text-sm bg-gray-50 dark:bg-slate-700 text-gray-900 dark:text-white focus:outline-none focus:ring-2 focus:ring-[#108A00]/30 focus:border-[#108A00] transition-all">
                </div>
                <div class="flex items-end gap-2">
                    <button type="submit" class="px-4 py-2 rounded-lg bg-[#108A00] hover:bg-[#0d7500] text-white text-sm font-semibold transition-colors inline-flex items-center gap-1.5">
                        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M3 4a1 1 0 011-1h16a1 1 0 011 1v2.586a1 1 0 01-.293.707l-6.414 6.414a1 1 0 00-.293.707V17l-4 4v-6.586a1 1 0 00-.293-.707L3.293 7.293A1 1 0 013 6.586V4z"/></svg>
                        Filter
                    </button>
                    <?php if ($search || $typeF || $dateFrom || $dateTo): ?>
                        <a href="wallet_history.php?user_id=<?= $userId ?>" class="px-4 py-2 rounded-lg border border-gray-200 dark:border-slate-600 text-sm font-medium text-gray-500 dark:text-slate-400 hover:bg-gray-50 dark:hover:bg-slate-700 transition-colors text-center">
                            Clear
                        </a>
                    <?php endif; ?>
                </div>
            </div>
        </form>

        <!-- ═══ RESULTS COUNT ══════════════════════════════════════════ -->
        <div class="flex items-center justify-between mb-3">
            <p class="text-xs text-gray-400 dark:text-slate-500">
                Showing <span class="font-semibold text-gray-700 dark:text-slate-300"><?= count($transactions) ?></span> of <span class="font-semibold text-gray-700 dark:text-slate-300"><?= number_format($totalTx) ?></span> transactions
            </p>
        </div>

        <!-- ═══ TRANSACTION TABLE ══════════════════════════════════════ -->
        <?php if (count($transactions) > 0): ?>
        <div class="border border-gray-100 dark:border-slate-700 rounded-lg overflow-hidden mb-5">
            <div class="overflow-x-auto">
                <table class="w-full text-sm tx-drawer-table">
                    <thead>
                        <tr class="border-b border-gray-100 dark:border-slate-700">
                            <th class="text-left text-[10px] font-semibold text-gray-500 dark:text-slate-400 uppercase tracking-wider">ID</th>
                            <th class="text-left text-[10px] font-semibold text-gray-500 dark:text-slate-400 uppercase tracking-wider">Type</th>
                            <th class="text-right text-[10px] font-semibold text-gray-500 dark:text-slate-400 uppercase tracking-wider">Amount</th>
                            <th class="text-right text-[10px] font-semibold text-gray-500 dark:text-slate-400 uppercase tracking-wider">Balance After</th>
                            <th class="text-left text-[10px] font-semibold text-gray-500 dark:text-slate-400 uppercase tracking-wider">Reference</th>
                            <th class="text-center text-[10px] font-semibold text-gray-500 dark:text-slate-400 uppercase tracking-wider">Status</th>
                            <th class="text-right text-[10px] font-semibold text-gray-500 dark:text-slate-400 uppercase tracking-wider">Date</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($transactions as $tx):
                        $lbl = $typeLabels[$tx['type']] ?? ['Unknown', 'text-gray-600 dark:text-slate-400', 'bg-gray-50 dark:bg-slate-700', 'circle'];
                        $isPositive = in_array($tx['type'], ['deposit', 'signup_bonus', 'refund', 'escrow_release']);

                        // Derive status
                        $statusClass = 'status-completed';
                        $statusLabel = 'Completed';
                        if ($tx['reference_type'] === 'payment' && $tx['payment_status']) {
                            $pStatus = $tx['payment_status'];
                            $statusLabel = ucfirst($pStatus);
                            $statusMap = [
                                'pending' => 'status-pending',
                                'processing' => 'status-processing',
                                'completed' => 'status-completed',
                                'failed' => 'status-failed',
                                'refunded' => 'status-refunded',
                            ];
                            $statusClass = $statusMap[$pStatus] ?? 'status-completed';
                        }

                        // Reference display
                        $refDisplay = '—';
                        if ($tx['reference_id'] && $tx['reference_type']) {
                            $refDisplay = ucfirst($tx['reference_type']) . ' #' . $tx['reference_id'];
                        }
                    ?>
                        <tr>
                            <td class="text-gray-500 dark:text-slate-500 text-xs font-mono">#<?= $tx['id'] ?></td>
                            <td>
                                <div class="flex items-center gap-2">
                                    <div class="w-7 h-7 rounded-lg <?= $lbl[2] ?> flex items-center justify-center flex-shrink-0">
                                        <i data-lucide="<?= $lbl[3] ?>" class="text-[10px] <?= $lbl[1] ?>"></i>
                                    </div>
                                    <span class="font-medium text-gray-900 dark:text-white text-xs whitespace-nowrap"><?= $lbl[0] ?></span>
                                </div>
                            </td>
                            <td class="text-right">
                                <span class="text-xs font-bold <?= $isPositive ? 'amt-positive' : 'amt-negative' ?>"><?= ($isPositive ? '+' : '−') . format_currency(abs((float) $tx['amount'])) ?></span>
                            </td>
                            <td class="text-right">
                                <span class="text-xs font-semibold text-gray-700 dark:text-slate-300"><?= format_currency((float) $tx['balance_after']) ?></span>
                            </td>
                            <td>
                                <span class="text-xs text-gray-500 dark:text-slate-400 whitespace-nowrap"><?= $refDisplay ?></span>
                            </td>
                            <td class="text-center">
                                <span class="status-badge <?= $statusClass ?>"><?= $statusLabel ?></span>
                            </td>
                            <td class="text-right">
                                <span class="text-[11px] text-gray-400 dark:text-slate-500 font-medium whitespace-nowrap"><?= date('M d, Y', strtotime($tx['created_at'])) ?></span>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- ═══ PAGINATION ═════════════════════════════════════════════ -->
        <?php render_pagination($pagination, $baseUrl); ?>

        <?php else: ?>
        <!-- ═══ EMPTY STATE ════════════════════════════════════════════ -->
        <div class="text-center py-16">
            <div class="w-16 h-16 rounded-2xl bg-gray-100 dark:bg-slate-700 flex items-center justify-center mx-auto mb-4">
                <svg class="w-7 h-7 text-gray-300 dark:text-slate-500" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="1.5"><path stroke-linecap="round" stroke-linejoin="round" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"/></svg>
            </div>
            <p class="text-gray-500 dark:text-slate-400 text-sm font-medium mb-1">No transactions found</p>
            <p class="text-gray-400 dark:text-slate-500 text-xs">Try adjusting your search or filters</p>
        </div>
        <?php endif; ?>

    </div><!-- /drawer-body -->
</div><!-- /drawer-panel -->

<!-- ═══ ADJUST BALANCE MODAL ═══════════════════════════════════════ -->
<div id="adjModalOverlay" class="adj-modal-overlay" onclick="if(event.target===this)closeAdjustModal()">
    <div class="adj-modal">
        <div class="adj-modal-header">
            <h3>Adjust Wallet Balance</h3>
            <button type="button" class="adj-modal-close" onclick="closeAdjustModal()">
                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M18 6L6 18M6 6l12 12"/></svg>
            </button>
        </div>
        <form method="POST" action="wallet_history.php?user_id=<?= $userId ?>" id="adjForm">
        <input type="hidden" name="adjust_balance" value="1">
        <div class="adj-modal-body">
            <!-- Adjustment Type -->
            <div class="adj-field">
                <label class="adj-field-label">Adjustment Type</label>
                <div class="adj-type-group">
                    <div class="adj-type-btn selected" data-type="credit" onclick="selectAdjType(this,'credit')">
                        <i data-lucide="arrow-down" style="margin-right:4px;color:#059669"></i> Credit
                    </div>
                    <div class="adj-type-btn" data-type="debit" onclick="selectAdjType(this,'debit')">
                        <i data-lucide="arrow-up" style="margin-right:4px;color:#dc2626"></i> Debit
                    </div>
                </div>
                <input type="hidden" name="adjust_type" id="adjTypeInput" value="credit">
            </div>

            <!-- Amount -->
            <div class="adj-field">
                <label class="adj-field-label" for="adjAmount">Amount (<?= format_currency(0) ?>)</label>
                <input type="number" name="adjust_amount" id="adjAmount" min="0.01" step="0.01" placeholder="0.00" required oninput="updateBalancePreview()">
            </div>

            <!-- Reason -->
            <div class="adj-field">
                <label class="adj-field-label" for="adjReason">Reason *</label>
                <input type="text" name="adjust_reason" id="adjReason" placeholder="e.g. Compensation for service issue" required>
            </div>

            <!-- Admin Notes -->
            <div class="adj-field">
                <label class="adj-field-label" for="adjNotes">Admin Notes (optional)</label>
                <textarea name="adjust_notes" id="adjNotes" rows="2" placeholder="Internal notes..."></textarea>
            </div>

            <!-- Confirmation -->
            <div class="adj-check-row">
                <input type="checkbox" name="confirm_adjust" id="adjConfirm">
                <label class="adj-check-label" for="adjConfirm">I confirm this balance adjustment is accurate and authorized.</label>
            </div>

            <!-- Balance Preview -->
            <div class="adj-balance-preview" id="adjPreview">
                <div class="preview-row">
                    <span class="preview-label">Current Balance</span>
                    <span class="preview-val"><?= format_currency((float) $target['wallet_balance']) ?></span>
                </div>
                <div class="preview-row">
                    <span class="preview-label">Adjustment</span>
                    <span class="preview-val" id="adjPreviewAmt">+<?= format_currency(0) ?></span>
                </div>
                <div class="preview-row" style="margin-top:4px;padding-top:4px;border-top:1px solid #d1d5db">
                    <span class="preview-label" style="font-weight:700">New Balance</span>
                    <span class="preview-val new" id="adjPreviewNew"><?= format_currency((float) $target['wallet_balance']) ?></span>
                </div>
            </div>
        </div>
        <div class="adj-modal-footer">
            <button type="button" class="btn-adj-cancel" onclick="closeAdjustModal()">Cancel</button>
            <button type="submit" class="btn-adj-save" id="adjSubmitBtn" disabled>Save Adjustment</button>
        </div>
        </form>
    </div>
</div>

<!-- ═══ NOTIFICATION ═════════════════════════════════════════════════ -->
<?php if (isset($_SESSION['flash_success'])): ?>
<div id="adjNotif" class="adj-notif success" style="display:none">
    <i data-lucide="circle-check"></i>
    <span><?= sanitize_string($_SESSION['flash_success']) ?></span>
</div>
<?php unset($_SESSION['flash_success']); endif; ?>
<?php if (isset($_SESSION['flash_error'])): ?>
<div id="adjNotif" class="adj-notif error" style="display:none">
    <i data-lucide="circle-alert"></i>
    <span><?= sanitize_string($_SESSION['flash_error']) ?></span>
</div>
<?php unset($_SESSION['flash_error']); endif; ?>

<!-- ═══ PROCESS REFUND MODAL ═════════════════════════════════════════ -->
<div id="refundModalOverlay" class="adj-modal-overlay" onclick="if(event.target===this)closeRefundModal()">
    <div class="adj-modal" style="max-width:32rem">
        <div class="adj-modal-header">
            <h3 style="display:flex;align-items:center;gap:8px">
                <svg class="w-5 h-5 text-red-500" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M3 10h10a8 8 0 018 8v2M3 10l6 6m-6-6l6-6"/></svg>
                Process Refund
            </h3>
            <button type="button" class="adj-modal-close" onclick="closeRefundModal()">
                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M18 6L6 18M6 6l12 12"/></svg>
            </button>
        </div>
        <form method="POST" action="wallet_history.php?user_id=<?= $userId ?>" id="refundForm">
        <input type="hidden" name="process_refund" value="1">
        <div class="adj-modal-body">
            <?php if (count($refundable) > 0): ?>

            <div class="refund-warning">
                <svg class="w-4 h-4 flex-shrink-0 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-2.5L13.732 4.5c-.77-.833-2.694-.833-3.464 0L3.34 16.5c-.77.833.192 2.5 1.732 2.5z"/></svg>
                <span>This action is irreversible. The refund amount will be credited to the client's wallet.</span>
            </div>

            <!-- Milestone Selection -->
            <div class="adj-field">
                <label class="adj-field-label">Select Milestone to Refund</label>
                <select name="refund_milestone_id" id="refundMilestoneSelect" class="refund-milestone-select" onchange="updateRefundInfo()" required>
                    <option value="">— Select a milestone —</option>
                    <?php foreach ($refundable as $rf): ?>
                        <option value="<?= $rf['id'] ?>"
                            data-contract="<?= sanitize_string($rf['contract_title']) ?>"
                            data-milestone="<?= sanitize_string($rf['title']) ?>"
                            data-amount="<?= (float) $rf['amount'] ?>"
                            data-client="<?= sanitize_string($rf['client_name']) ?>"
                            data-reason="<?= $rf['eligible_reason'] ?>">
                            <?= sanitize_string($rf['contract_title']) ?> → <?= sanitize_string($rf['title']) ?> (<?= format_currency((float) $rf['amount']) ?>)
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <!-- Refund Details (populated by JS) -->
            <div id="refundDetails" style="display:none">
                <div class="refund-info-grid">
                    <div class="refund-info-item">
                        <div class="refund-info-label">Contract</div>
                        <div class="refund-info-value" id="riContract">—</div>
                    </div>
                    <div class="refund-info-item">
                        <div class="refund-info-label">Milestone</div>
                        <div class="refund-info-value" id="riMilestone">—</div>
                    </div>
                    <div class="refund-info-item">
                        <div class="refund-info-label">Escrow Amount</div>
                        <div class="refund-info-value amount" id="riAmount">—</div>
                    </div>
                    <div class="refund-info-item">
                        <div class="refund-info-label">Recipient (Client)</div>
                        <div class="refund-info-value" id="riClient">—</div>
                    </div>
                </div>

                <div class="adj-field">
                    <label class="adj-field-label">Refund Amount</label>
                    <input type="text" id="riRefundAmt" readonly style="background:#f0fdf4;border-color:#bbf7d0;color:#059669;font-weight:700;font-size:15px">
                </div>

                <div class="adj-field">
                    <label class="adj-field-label" for="refundReason">Reason *</label>
                    <input type="text" name="refund_reason" id="refundReason" placeholder="e.g. Work not delivered, quality issue..." required oninput="validateRefundForm()">
                </div>

                <div class="adj-check-row">
                    <input type="checkbox" name="confirm_refund" id="refundConfirm" onchange="validateRefundForm()">
                    <label class="adj-check-label" for="refundConfirm">I confirm this refund is authorized and the escrowed amount should be returned to the client.</label>
                </div>
            </div>

            <?php else: ?>
            <div class="text-center py-8">
                <div class="w-14 h-14 rounded-2xl bg-gray-100 dark:bg-slate-700 flex items-center justify-center mx-auto mb-3">
                    <svg class="w-6 h-6 text-gray-300 dark:text-slate-500" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="1.5"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                </div>
                <p class="text-gray-500 dark:text-slate-400 text-sm font-medium mb-1">No refundable milestones</p>
                <p class="text-gray-400 dark:text-slate-500 text-xs">No escrow held or dispute resolved in client's favor for this user.</p>
            </div>
            <?php endif; ?>
        </div>
        <?php if (count($refundable) > 0): ?>
        <div class="adj-modal-footer">
            <button type="button" class="btn-refund-cancel" onclick="closeRefundModal()">Cancel</button>
            <button type="submit" class="btn-refund-confirm" id="refundSubmitBtn" disabled>Refund</button>
        </div>
        <?php else: ?>
        <div class="adj-modal-footer">
            <button type="button" class="btn-refund-cancel" onclick="closeRefundModal()">Close</button>
        </div>
        <?php endif; ?>
        </form>
    </div>
</div>

<!-- ═══ FREEZE WALLET MODAL ══════════════════════════════════════════ -->
<div id="freezeModalOverlay" class="adj-modal-overlay" onclick="if(event.target===this)closeFreezeModal()">
    <div class="adj-modal" style="max-width:28rem">
        <div class="adj-modal-header">
            <h3 style="display:flex;align-items:center;gap:8px">
                <svg class="w-5 h-5 text-amber-500" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"/></svg>
                Freeze Wallet
            </h3>
            <button type="button" class="adj-modal-close" onclick="closeFreezeModal()">
                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M18 6L6 18M6 6l12 12"/></svg>
            </button>
        </div>
        <form method="POST" action="wallet_history.php?user_id=<?= $userId ?>" id="freezeForm">
        <input type="hidden" name="freeze_wallet" value="1">
        <div class="adj-modal-body">
            <div class="frozen-banner">
                <svg class="w-4 h-4 flex-shrink-0 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-2.5L13.732 4.5c-.77-.833-2.694-.833-3.464 0L3.34 16.5c-.77.833.192 2.5 1.732 2.5z"/></svg>
                <span>Freezing this wallet will <strong>disable deposits, escrow funding, and withdrawals</strong>. Balance and transaction history remain visible.</span>
            </div>

            <div class="freeze-info-grid">
                <div class="freeze-info-item">
                    <div class="freeze-info-label">User Name</div>
                    <div class="freeze-info-value"><?= sanitize_string($target['name']) ?></div>
                </div>
                <div class="freeze-info-item">
                    <div class="freeze-info-label">Role</div>
                    <div class="freeze-info-value"><?= ucfirst($target['role']) ?></div>
                </div>
                <div class="freeze-info-item">
                    <div class="freeze-info-label">Wallet Balance</div>
                    <div class="freeze-info-value" style="color:#059669;font-size:15px;font-weight:800"><?= format_currency((float) $target['wallet_balance']) ?></div>
                </div>
                <div class="freeze-info-item">
                    <div class="freeze-info-label">Current Status</div>
                    <div class="freeze-info-value" style="color:#059669">Active</div>
                </div>
            </div>

            <!-- Freeze Type -->
            <div class="adj-field">
                <label class="adj-field-label">Duration</label>
                <div class="freeze-type-group">
                    <div class="freeze-type-btn selected" data-type="permanent" onclick="selectFreezeType(this,'permanent')">
                        <i data-lucide="lock" style="margin-right:4px;color:#f59e0b"></i> Permanent
                    </div>
                    <div class="freeze-type-btn" data-type="temporary" onclick="selectFreezeType(this,'temporary')">
                        <i data-lucide="clock" style="margin-right:4px;color:#f59e0b"></i> Temporary
                    </div>
                </div>
                <input type="hidden" name="freeze_type" id="freezeTypeInput" value="permanent">
            </div>

            <!-- Reason -->
            <div class="adj-field">
                <label class="adj-field-label" for="freezeReason">Reason *</label>
                <input type="text" name="freeze_reason" id="freezeReason" placeholder="e.g. Fraud investigation, policy violation..." required oninput="validateFreezeForm()">
            </div>

            <!-- Admin Notes -->
            <div class="adj-field">
                <label class="adj-field-label" for="freezeNotes">Admin Notes (optional)</label>
                <textarea name="freeze_notes" id="freezeNotes" rows="2" placeholder="Internal notes..."></textarea>
            </div>

            <!-- Confirmation -->
            <div class="adj-check-row">
                <input type="checkbox" name="confirm_freeze" id="freezeConfirm" onchange="validateFreezeForm()">
                <label class="adj-check-label" for="freezeConfirm">I confirm this wallet should be frozen. All financial transactions will be blocked.</label>
            </div>
        </div>
        <div class="adj-modal-footer">
            <button type="button" class="btn-freeze-cancel" onclick="closeFreezeModal()">Cancel</button>
            <button type="submit" class="btn-freeze-confirm" id="freezeSubmitBtn" disabled>Freeze Wallet</button>
        </div>
        </form>
    </div>
</div>

<!-- ═══ UNFREEZE WALLET MODAL ════════════════════════════════════════ -->
<div id="unfreezeModalOverlay" class="adj-modal-overlay" onclick="if(event.target===this)closeUnfreezeModal()">
    <div class="adj-modal" style="max-width:28rem">
        <div class="adj-modal-header">
            <h3 style="display:flex;align-items:center;gap:8px">
                <svg class="w-5 h-5 text-emerald-500" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M8 11V7a4 4 0 118 0m-4 8v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2z"/></svg>
                Unfreeze Wallet
            </h3>
            <button type="button" class="adj-modal-close" onclick="closeUnfreezeModal()">
                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M18 6L6 18M6 6l12 12"/></svg>
            </button>
        </div>
        <form method="POST" action="wallet_history.php?user_id=<?= $userId ?>" id="unfreezeForm">
        <input type="hidden" name="unfreeze_wallet" value="1">
        <div class="adj-modal-body">
            <div style="padding:10px 14px;border-radius:8px;background:#ecfdf5;border:1px solid #a7f3d0;font-size:11px;color:#065f46;font-weight:500;margin-bottom:16px;display:flex;align-items:flex-start;gap:8px">
                <svg class="w-4 h-4 flex-shrink-0 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                <span>Unfreezing will <strong>restore deposits, withdrawals, and escrow funding</strong> for this wallet.</span>
            </div>

            <div class="freeze-info-grid">
                <div class="freeze-info-item">
                    <div class="freeze-info-label">User Name</div>
                    <div class="freeze-info-value"><?= sanitize_string($target['name']) ?></div>
                </div>
                <div class="freeze-info-item">
                    <div class="freeze-info-label">Role</div>
                    <div class="freeze-info-value"><?= ucfirst($target['role']) ?></div>
                </div>
                <div class="freeze-info-item">
                    <div class="freeze-info-label">Wallet Balance</div>
                    <div class="freeze-info-value" style="color:#059669;font-size:15px;font-weight:800"><?= format_currency((float) $target['wallet_balance']) ?></div>
                </div>
                <div class="freeze-info-item">
                    <div class="freeze-info-label">Current Status</div>
                    <div class="freeze-info-value" style="color:#dc2626;font-weight:700">Frozen</div>
                </div>
            </div>

            <!-- Reason -->
            <div class="adj-field">
                <label class="adj-field-label" for="unfreezeReason">Reason *</label>
                <input type="text" name="unfreeze_reason" id="unfreezeReason" placeholder="e.g. Investigation complete, cleared..." required oninput="validateUnfreezeForm()">
            </div>

            <!-- Confirmation -->
            <div class="adj-check-row">
                <input type="checkbox" name="confirm_unfreeze" id="unfreezeConfirm" onchange="validateUnfreezeForm()">
                <label class="adj-check-label" for="unfreezeConfirm">I confirm this wallet should be unfrozen and all financial operations restored.</label>
            </div>
        </div>
        <div class="adj-modal-footer">
            <button type="button" class="btn-freeze-cancel" onclick="closeUnfreezeModal()">Cancel</button>
            <button type="submit" class="btn-unfreeze-confirm" id="unfreezeSubmitBtn" disabled>Unfreeze Wallet</button>
        </div>
        </form>
    </div>
</div>

<script>
(function() {
    var overlay = document.getElementById('drawerOverlay');
    var panel = document.getElementById('drawerPanel');

    /* Open drawer on load */
    requestAnimationFrame(function() {
        overlay.classList.add('open');
        panel.classList.add('open');
    });

    window.closeDrawer = function() {
        overlay.classList.remove('open');
        panel.classList.remove('open');
        setTimeout(function() {
            window.location.href = 'wallets.php';
        }, 300);
    };

    /* Close on Escape */
    document.addEventListener('keydown', function(e) {
        if (e.key === 'Escape') {
            var freezeModal = document.getElementById('freezeModalOverlay');
            var unfreezeModal = document.getElementById('unfreezeModalOverlay');
            var refundModal = document.getElementById('refundModalOverlay');
            var adjModal = document.getElementById('adjModalOverlay');
            if (freezeModal && freezeModal.classList.contains('open')) {
                closeFreezeModal();
            } else if (unfreezeModal && unfreezeModal.classList.contains('open')) {
                closeUnfreezeModal();
            } else if (refundModal && refundModal.classList.contains('open')) {
                closeRefundModal();
            } else if (adjModal && adjModal.classList.contains('open')) {
                closeAdjustModal();
            } else {
                closeDrawer();
            }
        }
    });

    /* ── Adjust Balance Modal ── */
    var curBalance = <?= (float) $target['wallet_balance'] ?>;
    var currentAdjType = 'credit';

    window.openAdjustModal = function() {
        document.getElementById('adjModalOverlay').classList.add('open');
        document.body.style.overflow = 'hidden';
    };

    window.closeAdjustModal = function() {
        document.getElementById('adjModalOverlay').classList.remove('open');
        document.body.style.overflow = '';
    };

    window.selectAdjType = function(el, type) {
        document.querySelectorAll('.adj-type-btn').forEach(function(b) { b.classList.remove('selected'); });
        el.classList.add('selected');
        document.getElementById('adjTypeInput').value = type;
        currentAdjType = type;
        updateBalancePreview();
    };

    window.updateBalancePreview = function() {
        var amt = parseFloat(document.getElementById('adjAmount').value) || 0;
        var preview = document.getElementById('adjPreview');
        var previewAmt = document.getElementById('adjPreviewAmt');
        var previewNew = document.getElementById('adjPreviewNew');

        if (amt > 0) {
            preview.style.display = 'block';
            var isCredit = currentAdjType === 'credit';
            previewAmt.textContent = (isCredit ? '+' : '−') + '<?= format_currency(0) ?>'.replace('0.00', amt.toFixed(2));
            previewAmt.className = 'preview-val ' + (isCredit ? 'new' : '');
            previewAmt.style.color = isCredit ? '#059669' : '#dc2626';
            var newBal = isCredit ? curBalance + amt : curBalance - amt;
            previewNew.textContent = '<?= format_currency(0) ?>'.replace('0.00', newBal.toFixed(2));
            previewNew.style.color = newBal < 0 ? '#dc2626' : '#059669';
        } else {
            preview.style.display = 'none';
        }
        validateAdjForm();
    };

    function validateAdjForm() {
        var amt = parseFloat(document.getElementById('adjAmount').value) || 0;
        var reason = document.getElementById('adjReason').value.trim();
        var confirm = document.getElementById('adjConfirm').checked;
        var btn = document.getElementById('adjSubmitBtn');
        var valid = amt > 0 && reason !== '' && confirm;
        btn.disabled = !valid;
        btn.className = valid ? 'btn-adj-save enabled' : 'btn-adj-save';
    }

    document.getElementById('adjConfirm').addEventListener('change', validateAdjForm);
    document.getElementById('adjReason').addEventListener('input', validateAdjForm);
    document.getElementById('adjAmount').addEventListener('input', updateBalancePreview);

    /* Auto-open modal if action=adjust */
    if (window.location.search.indexOf('action=adjust') !== -1) {
        openAdjustModal();
    }

    /* ── Process Refund Modal ── */
    window.openRefundModal = function() {
        document.getElementById('refundModalOverlay').classList.add('open');
        document.body.style.overflow = 'hidden';
    };

    window.closeRefundModal = function() {
        document.getElementById('refundModalOverlay').classList.remove('open');
        document.body.style.overflow = '';
    };

    window.updateRefundInfo = function() {
        var sel = document.getElementById('refundMilestoneSelect');
        var details = document.getElementById('refundDetails');
        var opt = sel.options[sel.selectedIndex];

        if (!opt || !opt.value) {
            details.style.display = 'none';
            document.getElementById('refundSubmitBtn').disabled = true;
            document.getElementById('refundSubmitBtn').className = 'btn-refund-confirm';
            return;
        }

        var contract = opt.getAttribute('data-contract');
        var milestone = opt.getAttribute('data-milestone');
        var amount = parseFloat(opt.getAttribute('data-amount'));
        var client = opt.getAttribute('data-client');
        var reason = opt.getAttribute('data-reason');

        document.getElementById('riContract').textContent = contract;
        document.getElementById('riMilestone').textContent = milestone;
        document.getElementById('riAmount').textContent = '<?= format_currency(0) ?>'.replace('0.00', amount.toFixed(2));
        document.getElementById('riClient').textContent = client;
        document.getElementById('riRefundAmt').value = '<?= format_currency(0) ?>'.replace('0.00', amount.toFixed(2));

        details.style.display = 'block';
        validateRefundForm();
    };

    window.validateRefundForm = function() {
        var sel = document.getElementById('refundMilestoneSelect');
        var reason = document.getElementById('refundReason').value.trim();
        var confirm = document.getElementById('refundConfirm').checked;
        var btn = document.getElementById('refundSubmitBtn');
        var valid = sel.value !== '' && reason !== '' && confirm;
        btn.disabled = !valid;
        btn.className = valid ? 'btn-refund-confirm enabled' : 'btn-refund-confirm';
    };

    var refundConfirmEl = document.getElementById('refundConfirm');
    if (refundConfirmEl) refundConfirmEl.addEventListener('change', validateRefundForm);
    var refundReasonEl = document.getElementById('refundReason');
    if (refundReasonEl) refundReasonEl.addEventListener('input', validateRefundForm);

    /* Auto-open refund modal if action=refund */
    if (window.location.search.indexOf('action=refund') !== -1) {
        openRefundModal();
    }

    /* ── Freeze Wallet Modal ── */
    window.openFreezeModal = function() {
        document.getElementById('freezeModalOverlay').classList.add('open');
        document.body.style.overflow = 'hidden';
    };

    window.closeFreezeModal = function() {
        document.getElementById('freezeModalOverlay').classList.remove('open');
        document.body.style.overflow = '';
    };

    window.selectFreezeType = function(el, type) {
        document.querySelectorAll('.freeze-type-btn').forEach(function(b) { b.classList.remove('selected'); });
        el.classList.add('selected');
        document.getElementById('freezeTypeInput').value = type;
    };

    window.validateFreezeForm = function() {
        var reason = document.getElementById('freezeReason').value.trim();
        var confirm = document.getElementById('freezeConfirm').checked;
        var btn = document.getElementById('freezeSubmitBtn');
        var valid = reason !== '' && confirm;
        btn.disabled = !valid;
        btn.className = valid ? 'btn-freeze-confirm enabled' : 'btn-freeze-confirm';
    };

    var freezeConfirmEl = document.getElementById('freezeConfirm');
    if (freezeConfirmEl) freezeConfirmEl.addEventListener('change', validateFreezeForm);
    var freezeReasonEl = document.getElementById('freezeReason');
    if (freezeReasonEl) freezeReasonEl.addEventListener('input', validateFreezeForm);

    /* Auto-open freeze modal if action=freeze */
    if (window.location.search.indexOf('action=freeze') !== -1) {
        openFreezeModal();
    }

    /* ── Unfreeze Wallet Modal ── */
    window.openUnfreezeModal = function() {
        document.getElementById('unfreezeModalOverlay').classList.add('open');
        document.body.style.overflow = 'hidden';
    };

    window.closeUnfreezeModal = function() {
        document.getElementById('unfreezeModalOverlay').classList.remove('open');
        document.body.style.overflow = '';
    };

    window.validateUnfreezeForm = function() {
        var reason = document.getElementById('unfreezeReason').value.trim();
        var confirm = document.getElementById('unfreezeConfirm').checked;
        var btn = document.getElementById('unfreezeSubmitBtn');
        var valid = reason !== '' && confirm;
        btn.disabled = !valid;
        btn.className = valid ? 'btn-unfreeze-confirm enabled' : 'btn-unfreeze-confirm';
    };

    var unfreezeConfirmEl = document.getElementById('unfreezeConfirm');
    if (unfreezeConfirmEl) unfreezeConfirmEl.addEventListener('change', validateUnfreezeForm);
    var unfreezeReasonEl = document.getElementById('unfreezeReason');
    if (unfreezeReasonEl) unfreezeReasonEl.addEventListener('input', validateUnfreezeForm);

    /* Show notification */
    var notif = document.getElementById('adjNotif');
    if (notif) {
        notif.style.display = 'flex';
        requestAnimationFrame(function() { notif.classList.add('show'); });
        setTimeout(function() {
            notif.classList.remove('show');
            setTimeout(function() { notif.style.display = 'none'; }, 400);
        }, 4000);
    }
})();
</script>

<?php if (!$isAjax): ?>
<?php require_once __DIR__ . '/../components/layout_end.php'; ?>
<?php endif; ?>
