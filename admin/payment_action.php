<?php
/**
 * Payment Action Handler
 * POST-only endpoint for admin payment management actions.
 *
 * Actions: processing, completed, refunded, export_csv, export_pdf
 */

require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../auth/auth.php';
require_role('admin');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    set_flash('error', 'Invalid request method.');
    redirect('/finalproject/admin/payments.php');
}

if (!verify_csrf_token()) {
    set_flash('error', 'Invalid CSRF token. Please try again.');
    redirect('/finalproject/admin/payments.php');
}

$action    = trim($_POST['action'] ?? '');
$paymentId = sanitize_int($_POST['payment_id'] ?? 0);

// ═══════════════════════════════════════════════════════════════════
// EXPORT CSV
// ═══════════════════════════════════════════════════════════════════
if ($action === 'export_csv') {
    $where  = [];
    $params = [];
    $types  = '';

    $dateFrom      = trim($_POST['date_from'] ?? '');
    $dateTo        = trim($_POST['date_to'] ?? '');
    $statusF       = trim($_POST['status'] ?? '');
    $clientQ       = trim($_POST['client'] ?? '');
    $freelancerQ   = trim($_POST['freelancer'] ?? '');
    $amountMin     = $_POST['amount_min'] ?? '';
    $amountMax     = $_POST['amount_max'] ?? '';

    if ($dateFrom !== '') { $where[] = 'p.created_at >= ?'; $params[] = $dateFrom . ' 00:00:00'; $types .= 's'; }
    if ($dateTo !== '')   { $where[] = 'p.created_at <= ?'; $params[] = $dateTo . ' 23:59:59'; $types .= 's'; }
    if ($statusF !== '')  { $where[] = 'p.status = ?'; $params[] = $statusF; $types .= 's'; }
    if ($clientQ !== '')  { $where[] = '(uc.name LIKE ? OR uc.email LIKE ?)'; $c = "%{$clientQ}%"; $params[] = $c; $params[] = $c; $types .= 'ss'; }
    if ($freelancerQ !== '') { $where[] = '(uf.name LIKE ? OR uf.email LIKE ?)'; $f = "%{$freelancerQ}%"; $params[] = $f; $params[] = $f; $types .= 'ss'; }
    if ($amountMin !== '') { $where[] = 'p.total_amount >= ?'; $params[] = (float) $amountMin; $types .= 'd'; }
    if ($amountMax !== '') { $where[] = 'p.total_amount <= ?'; $params[] = (float) $amountMax; $types .= 'd'; }

    $whereSql = $where ? 'WHERE ' . implode(' AND ', $where) : '';

    $sql = "SELECT p.id, p.total_amount, p.platform_fee, p.freelancer_net, p.status, p.created_at,
                   m.title AS milestone_title,
                   uc.name AS client_name, uc.email AS client_email,
                   uf.name AS freelancer_name, uf.email AS freelancer_email
            FROM payments p
            JOIN milestones m ON p.milestone_id = m.id
            JOIN users uc ON p.payer_id = uc.id
            JOIN users uf ON p.payee_id = uf.id
            {$whereSql}
            ORDER BY p.created_at DESC";

    $stmt = $conn->prepare($sql);
    if ($params) {
        $stmt->bind_param($types, ...$params);
    }
    $stmt->execute();
    $result = $stmt->get_result();
    $stmt->close();

    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="payments_export_' . date('Y-m-d') . '.csv"');

    $output = fopen('php://output', 'w');
    fprintf($output, chr(0xEF).chr(0xBB).chr(0xBF)); // UTF-8 BOM
    fputcsv($output, ['Payment ID', 'Milestone', 'Client', 'Client Email', 'Freelancer', 'Freelancer Email', 'Total Amount', 'Platform Fee', 'Freelancer Net', 'Status', 'Date']);

    while ($row = $result->fetch_assoc()) {
        fputcsv($output, [
            $row['id'],
            $row['milestone_title'],
            $row['client_name'],
            $row['client_email'],
            $row['freelancer_name'],
            $row['freelancer_email'],
            number_format((float) $row['total_amount'], 2),
            number_format((float) $row['platform_fee'], 2),
            number_format((float) $row['freelancer_net'], 2),
            ucfirst($row['status']),
            $row['created_at'],
        ]);
    }

    fclose($output);
    exit;
}

// ═══════════════════════════════════════════════════════════════════
// EXPORT PDF (HTML-based print)
// ═══════════════════════════════════════════════════════════════════
if ($action === 'export_pdf') {
    $where  = [];
    $params = [];
    $types  = '';

    $dateFrom      = trim($_POST['date_from'] ?? '');
    $dateTo        = trim($_POST['date_to'] ?? '');
    $statusF       = trim($_POST['status'] ?? '');
    $clientQ       = trim($_POST['client'] ?? '');
    $freelancerQ   = trim($_POST['freelancer'] ?? '');
    $amountMin     = $_POST['amount_min'] ?? '';
    $amountMax     = $_POST['amount_max'] ?? '';

    if ($dateFrom !== '') { $where[] = 'p.created_at >= ?'; $params[] = $dateFrom . ' 00:00:00'; $types .= 's'; }
    if ($dateTo !== '')   { $where[] = 'p.created_at <= ?'; $params[] = $dateTo . ' 23:59:59'; $types .= 's'; }
    if ($statusF !== '')  { $where[] = 'p.status = ?'; $params[] = $statusF; $types .= 's'; }
    if ($clientQ !== '')  { $where[] = '(uc.name LIKE ? OR uc.email LIKE ?)'; $c = "%{$clientQ}%"; $params[] = $c; $params[] = $c; $types .= 'ss'; }
    if ($freelancerQ !== '') { $where[] = '(uf.name LIKE ? OR uf.email LIKE ?)'; $f = "%{$freelancerQ}%"; $params[] = $f; $params[] = $f; $types .= 'ss'; }
    if ($amountMin !== '') { $where[] = 'p.total_amount >= ?'; $params[] = (float) $amountMin; $types .= 'd'; }
    if ($amountMax !== '') { $where[] = 'p.total_amount <= ?'; $params[] = (float) $amountMax; $types .= 'd'; }

    $whereSql = $where ? 'WHERE ' . implode(' AND ', $where) : '';

    $sql = "SELECT p.id, p.total_amount, p.platform_fee, p.freelancer_net, p.status, p.created_at,
                   m.title AS milestone_title,
                   uc.name AS client_name,
                   uf.name AS freelancer_name
            FROM payments p
            JOIN milestones m ON p.milestone_id = m.id
            JOIN users uc ON p.payer_id = uc.id
            JOIN users uf ON p.payee_id = uf.id
            {$whereSql}
            ORDER BY p.created_at DESC";

    $stmt = $conn->prepare($sql);
    if ($params) {
        $stmt->bind_param($types, ...$params);
    }
    $stmt->execute();
    $result = $stmt->get_result();
    $stmt->close();

    $statusColors = [
        'completed'  => '#10b981',
        'pending'    => '#f59e0b',
        'processing' => '#3b82f6',
        'refunded'   => '#ef4444',
    ];

    $rows = '';
    $totalAmount = 0;
    $totalFees   = 0;
    while ($row = $result->fetch_assoc()) {
        $totalAmount += (float) $row['total_amount'];
        $totalFees   += (float) $row['platform_fee'];
        $color = $statusColors[$row['status']] ?? '#6b7280';
        $rows .= "<tr>
            <td style='padding:8px 12px;border-bottom:1px solid #e5e7eb;font-weight:600'>#" . (int) $row['id'] . "</td>
            <td style='padding:8px 12px;border-bottom:1px solid #e5e7eb'>" . htmlspecialchars($row['milestone_title']) . "</td>
            <td style='padding:8px 12px;border-bottom:1px solid #e5e7eb'>" . htmlspecialchars($row['client_name']) . "</td>
            <td style='padding:8px 12px;border-bottom:1px solid #e5e7eb'>" . htmlspecialchars($row['freelancer_name']) . "</td>
            <td style='padding:8px 12px;border-bottom:1px solid #e5e7eb;text-align:right;font-weight:600'>\$" . number_format((float) $row['total_amount'], 2) . "</td>
            <td style='padding:8px 12px;border-bottom:1px solid #e5e7eb;text-align:right;color:#d97706'>\$" . number_format((float) $row['platform_fee'], 2) . "</td>
            <td style='padding:8px 12px;border-bottom:1px solid #e5e7eb;text-align:right;color:#059669'>\$" . number_format((float) $row['freelancer_net'], 2) . "</td>
            <td style='padding:8px 12px;border-bottom:1px solid #e5e7eb;text-align:center'><span style='background:{$color}20;color:{$color};padding:2px 10px;border-radius:6px;font-size:11px;font-weight:600'>" . ucfirst($row['status']) . "</span></td>
            <td style='padding:8px 12px;border-bottom:1px solid #e5e7eb;text-align:right;font-size:12px;color:#6b7280'>" . date('M j, Y', strtotime($row['created_at'])) . "</td>
        </tr>";
    }

    $filters = [];
    if ($dateFrom)    $filters[] = "From: {$dateFrom}";
    if ($dateTo)      $filters[] = "To: {$dateTo}";
    if ($statusF)     $filters[] = "Status: " . ucfirst($statusF);
    if ($clientQ)     $filters[] = "Client: {$clientQ}";
    if ($freelancerQ) $filters[] = "Freelancer: {$freelancerQ}";
    $filterStr = $filters ? implode(' | ', $filters) : 'All payments';

    echo "<!DOCTYPE html><html><head><meta charset='utf-8'>
    <title>Payments Report - " . date('M j, Y') . "</title>
    <style>
        * { margin:0; padding:0; box-sizing:border-box; }
        body { font-family: 'Segoe UI', Arial, sans-serif; padding:40px; color:#1e293b; }
        .header { text-align:center; margin-bottom:30px; }
        .header h1 { font-size:24px; font-weight:800; color:#1e293b; }
        .header p { font-size:12px; color:#64748b; margin-top:4px; }
        table { width:100%; border-collapse:collapse; margin-top:20px; }
        th { padding:10px 12px; text-align:left; font-size:10px; text-transform:uppercase; letter-spacing:0.05em; color:#64748b; border-bottom:2px solid #e5e7eb; background:#f8fafc; }
        td { font-size:13px; }
        .summary { display:flex; gap:20px; justify-content:center; margin:20px 0; }
        .summary-box { background:#f8fafc; border:1px solid #e5e7eb; border-radius:10px; padding:12px 24px; text-align:center; }
        .summary-box .label { font-size:10px; text-transform:uppercase; color:#64748b; letter-spacing:0.05em; }
        .summary-box .value { font-size:20px; font-weight:800; margin-top:4px; }
        .footer { text-align:center; margin-top:30px; font-size:11px; color:#94a3b8; }
        @media print { body { padding:20px; } }
    </style></head><body>
    <div class='header'>
        <h1>JobHub Payment Report</h1>
        <p>Generated: " . date('F j, Y \a\t g:i A') . " | Filters: " . htmlspecialchars($filterStr) . "</p>
    </div>
    <div class='summary'>
        <div class='summary-box'><div class='label'>Total Amount</div><div class='value' style='color:#1e293b'>\$" . number_format($totalAmount, 2) . "</div></div>
        <div class='summary-box'><div class='label'>Platform Fees</div><div class='value' style='color:#d97706'>\$" . number_format($totalFees, 2) . "</div></div>
        <div class='summary-box'><div class='label'>Net to Freelancers</div><div class='value' style='color:#059669'>\$" . number_format($totalAmount - $totalFees, 2) . "</div></div>
    </div>
    <table>
        <thead><tr>
            <th>ID</th><th>Milestone</th><th>Client</th><th>Freelancer</th>
            <th style='text-align:right'>Total</th><th style='text-align:right'>Fee</th><th style='text-align:right'>Net</th>
            <th style='text-align:center'>Status</th><th style='text-align:right'>Date</th>
        </tr></thead>
        <tbody>{$rows}</tbody>
    </table>
    <div class='footer'>JobHub Freelancer Marketplace &mdash; Confidential Payment Report</div>
    </body></html>";
    exit;
}

// ═══════════════════════════════════════════════════════════════════
// STATUS CHANGE ACTIONS (processing, completed, refunded)
// ═══════════════════════════════════════════════════════════════════
if (!in_array($action, ['processing', 'completed', 'refunded'])) {
    set_flash('error', 'Invalid action.');
    redirect('/finalproject/admin/payments.php');
}

if ($paymentId <= 0) {
    set_flash('error', 'Invalid payment ID.');
    redirect('/finalproject/admin/payments.php');
}

// Fetch payment
$stmt = $conn->prepare(
    'SELECT p.id, p.total_amount, p.platform_fee, p.freelancer_net, p.status, p.payee_id,
            m.title AS milestone_title,
            uc.name AS client_name, uf.name AS freelancer_name
     FROM payments p
     JOIN milestones m ON p.milestone_id = m.id
     JOIN users uc ON p.payer_id = uc.id
     JOIN users uf ON p.payee_id = uf.id
     WHERE p.id = ? LIMIT 1'
);
$stmt->bind_param('i', $paymentId);
$stmt->execute();
$payment = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$payment) {
    set_flash('error', 'Payment not found.');
    redirect('/finalproject/admin/payments.php');
}

$adminId   = (int) $_SESSION['user_id'];
$oldStatus = $payment['status'];

// Validate transition
$validTransitions = [
    'processing' => ['pending'],
    'completed'  => ['processing'],
    'refunded'   => ['pending', 'processing'],
];

if (!in_array($oldStatus, $validTransitions[$action] ?? [])) {
    set_flash('error', "Cannot change payment from '{$oldStatus}' to '{$action}'.");
    redirect('/finalproject/admin/payments.php');
}

$conn->begin_transaction();

try {
    if ($action === 'refunded') {
        // Refund: update status + refund fields
        $stmt = $conn->prepare(
            "UPDATE payments SET status = 'refunded', refund_amount = total_amount, refund_reason = 'Admin refund', refunded_at = NOW() WHERE id = ?"
        );
        $stmt->bind_param('i', $paymentId);
        $stmt->execute();
        $stmt->close();

        // Credit back to client wallet
        $stmt = $conn->prepare('UPDATE users SET wallet_balance = wallet_balance + ? WHERE id = ?');
        $stmt->bind_param('di', $payment['total_amount'], $payment['payee_id']);
        // Actually refund goes to payer (client), not payee (freelancer)
        $stmt->close();

        // Credit back to client (payer) wallet
        $payerStmt = $conn->prepare('SELECT payer_id FROM payments WHERE id = ?');
        $payerStmt->bind_param('i', $paymentId);
        $payerStmt->execute();
        $payerRow = $payerStmt->get_result()->fetch_assoc();
        $payerStmt->close();

        $creditStmt = $conn->prepare('UPDATE users SET wallet_balance = wallet_balance + ? WHERE id = ?');
        $refundAmount = (float) $payment['total_amount'];
        $creditStmt->bind_param('di', $refundAmount, $payerRow['payer_id']);
        $creditStmt->execute();
        $creditStmt->close();

        // Deduct from freelancer wallet
        $deductStmt = $conn->prepare('UPDATE users SET wallet_balance = GREATEST(wallet_balance - ?, 0) WHERE id = ?');
        $deductStmt->bind_param('di', $payment['freelancer_net'], $payment['payee_id']);
        $deductStmt->execute();
        $deductStmt->close();

    } else {
        // processing or completed
        $newStatus = $action;
        $stmt = $conn->prepare("UPDATE payments SET status = ? WHERE id = ?");
        $stmt->bind_param('si', $newStatus, $paymentId);
        $stmt->execute();
        $stmt->close();

        // If completed, credit freelancer wallet
        if ($newStatus === 'completed') {
            $creditStmt = $conn->prepare('UPDATE users SET wallet_balance = wallet_balance + ? WHERE id = ?');
            $creditStmt->bind_param('di', $payment['freelancer_net'], $payment['payee_id']);
            $creditStmt->execute();
            $creditStmt->close();
        }
    }

    // Log the admin action
    $payload = json_encode([
        'admin_id'     => $adminId,
        'payment_id'   => $paymentId,
        'old_status'   => $oldStatus,
        'new_status'   => $action,
        'amount'       => $payment['total_amount'],
    ]);
    $ip = get_ip_address();
    $logStmt = $conn->prepare(
        "INSERT INTO user_behavior_logs (user_id, action_type, ip_address, payload, created_at) VALUES (?, 'admin_payment_action', ?, ?, NOW())"
    );
    $logStmt->bind_param('iss', $adminId, $ip, $payload);
    $logStmt->execute();
    $logStmt->close();

    $conn->commit();

    $flashMsg = match($action) {
        'processing' => "Payment #{$paymentId} marked as processing.",
        'completed'  => "Payment #{$paymentId} completed. \$" . number_format((float) $payment['freelancer_net'], 2) . " credited to {$payment['freelancer_name']}.",
        'refunded'   => "Payment #{$paymentId} refunded. \$" . number_format((float) $payment['total_amount'], 2) . " returned to {$payment['client_name']}.",
    };
    set_flash('success', $flashMsg);

} catch (Exception $e) {
    $conn->rollback();
    set_flash('error', 'Failed to process payment action: ' . $e->getMessage());
}

redirect('/finalproject/admin/payments.php');
