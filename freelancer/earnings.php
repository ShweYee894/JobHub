<?php
$activePage = 'earnings';
require_once __DIR__ . '/../auth/auth.php';
require_role('freelancer');
require_once __DIR__ . '/../config/db.php';
$userId = $_SESSION['user_id'];
$uStmt = $conn->prepare('SELECT name, profile_image FROM users WHERE id = ?');
$uStmt->bind_param('i', $userId);
$uStmt->execute();
$user = $uStmt->get_result()->fetch_assoc();
$uStmt->close();
$wStmt = $conn->prepare('SELECT wallet_balance FROM users WHERE id = ?');
$wStmt->bind_param('i', $userId);
$wStmt->execute();
$walletBalance = (float) $wStmt->get_result()->fetch_assoc()['wallet_balance'];
$wStmt->close();
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
$s4 = $conn->prepare("SELECT COALESCE(SUM(p.freelancer_net), 0) AS total FROM payments p JOIN milestones m ON p.milestone_id = m.id WHERE p.payee_id = ? AND m.status = 'funded_in_escrow'");
$s4->bind_param('i', $userId);
$s4->execute();
$pendingRelease = (float) $s4->get_result()->fetch_assoc()['total'];
$s4->close();
// --- FILTER LOGIC ---
$filterDate = $_GET['date_range'] ?? '';
$filterType = $_GET['tx_type'] ?? '';
$filterClient = $_GET['client'] ?? '';
$filterContract = $_GET['contract'] ?? '';
$hasFilters = $filterDate !== '' || $filterType !== '' || $filterClient !== '' || $filterContract !== '';

// Date range presets
$dateRanges = [
    '' => ['label' => 'All time', 'sub' => ''],
    'this_week' => ['label' => 'This week', 'sub' => ''],
    'last_week' => ['label' => 'Last week', 'sub' => ''],
    'this_month' => ['label' => 'This month', 'sub' => ''],
    'last_month' => ['label' => 'Last month', 'sub' => ''],
    'this_year' => ['label' => 'This year', 'sub' => ''],
    'last_year' => ['label' => 'Last year', 'sub' => ''],
];

function getDateRange($key)
{
    $now = new DateTime();
    switch ($key) {
        case 'this_week':
            $start = (clone $now)->modify('monday this week')->setTime(0, 0, 0);
            $end = (clone $now);
            return [$start->format('Y-m-d 00:00:00'), $end->format('Y-m-d 23:59:59')];
        case 'last_week':
            $start = (clone $now)->modify('monday last week')->setTime(0, 0, 0);
            $end = (clone $now)->modify('sunday last week')->setTime(23, 59, 59);
            return [$start->format('Y-m-d 00:00:00'), $end->format('Y-m-d 23:59:59')];
        case 'this_month':
            $start = (clone $now)->modify('first day of this month')->setTime(0, 0, 0);
            $end = (clone $now);
            return [$start->format('Y-m-d 00:00:00'), $end->format('Y-m-d 23:59:59')];
        case 'last_month':
            $start = (clone $now)->modify('first day of last month')->setTime(0, 0, 0);
            $end = (clone $now)->modify('last day of last month')->setTime(23, 59, 59);
            return [$start->format('Y-m-d 00:00:00'), $end->format('Y-m-d 23:59:59')];
        case 'this_year':
            $start = (clone $now)->modify('january 1 this year')->setTime(0, 0, 0);
            $end = (clone $now);
            return [$start->format('Y-m-d 00:00:00'), $end->format('Y-m-d 23:59:59')];
        case 'last_year':
            $start = (clone $now)->modify('january 1 last year')->setTime(0, 0, 0);
            $end = (clone $now)->modify('december 31 last year')->setTime(23, 59, 59);
            return [$start->format('Y-m-d 00:00:00'), $end->format('Y-m-d 23:59:59')];
        default:
            return [null, null];
    }
}

function formatDateRangeLabel($key)
{
    $ranges = [
        'this_week' => 'This week',
        'last_week' => 'Last week',
        'this_month' => 'This month',
        'last_month' => 'Last month',
        'this_year' => 'This year',
        'last_year' => 'Last year',
    ];
    return $ranges[$key] ?? $key;
}

// Build dynamic WHERE
$where = ['p.payee_id = ?'];
$params = [$userId];
$types = 'i';

if ($filterDate !== '' && array_key_exists($filterDate, $dateRanges)) {
    [$dStart, $dEnd] = getDateRange($filterDate);
    if ($dStart && $dEnd) {
        $where[] = 'p.created_at BETWEEN ? AND ?';
        $params[] = $dStart;
        $params[] = $dEnd;
        $types .= 'ss';
    }
}

if ($filterType !== '' && in_array($filterType, ['completed', 'pending', 'escrow'])) {
    if ($filterType === 'escrow') {
        $where[] = "m.status = 'funded_in_escrow'";
    } else {
        $where[] = 'p.status = ?';
        $params[] = $filterType;
        $types .= 's';
    }
}

if ($filterClient !== '' && ctype_digit($filterClient)) {
    $where[] = 'p.payer_id = ?';
    $params[] = (int) $filterClient;
    $types .= 'i';
}

if ($filterContract !== '' && ctype_digit($filterContract)) {
    $where[] = 'm.contract_id = ?';
    $params[] = (int) $filterContract;
    $types .= 'i';
}

$whereClause = implode(' AND ', $where);

// Fetch distinct clients for filter
$clientStmt = $conn->prepare('SELECT DISTINCT u.id, u.name FROM payments p JOIN users u ON p.payer_id = u.id WHERE p.payee_id = ? ORDER BY u.name');
$clientStmt->bind_param('i', $userId);
$clientStmt->execute();
$clientsResult = $clientStmt->get_result();
$clientsList = [];
while ($cl = $clientsResult->fetch_assoc()) {
    $clientsList[] = $cl;
}
$clientStmt->close();

// Fetch distinct contracts for filter
$contractStmt = $conn->prepare('SELECT DISTINCT m.contract_id, m.title FROM payments p JOIN milestones m ON p.milestone_id = m.id WHERE p.payee_id = ? ORDER BY m.title');
$contractStmt->bind_param('i', $userId);
$contractStmt->execute();
$contractsResult = $contractStmt->get_result();
$contractsList = [];
while ($ct = $contractsResult->fetch_assoc()) {
    $contractsList[] = $ct;
}
$contractStmt->close();

// Compute filtered totals
$totSql = "SELECT COALESCE(SUM(p.freelancer_net),0) AS earnings, COALESCE(SUM(p.platform_fee),0) AS fees FROM payments p JOIN milestones m ON p.milestone_id = m.id WHERE $whereClause AND p.status = 'completed'";
$totStmt = $conn->prepare($totSql);
$totStmt->bind_param($types, ...$params);
$totStmt->execute();
$totRow = $totStmt->get_result()->fetch_assoc();
$totStmt->close();
$filteredEarnings = (float) $totRow['earnings'];
$filteredFees = (float) $totRow['fees'];
$filteredNet = $filteredEarnings;

// Count filtered for pagination
$countSql = "SELECT COUNT(*) AS total FROM payments p JOIN milestones m ON p.milestone_id = m.id WHERE $whereClause";
$countStmt = $conn->prepare($countSql);
$countStmt->bind_param($types, ...$params);
$countStmt->execute();
$totalPayments = (int) $countStmt->get_result()->fetch_assoc()['total'];
$countStmt->close();

// CSV download
if (isset($_GET['download']) && $_GET['download'] === 'csv') {
    header('Content-Type: text/csv');
    header('Content-Disposition: attachment; filename="transactions_' . date('Y-m-d') . '.csv"');
    $echo = fopen('php://output', 'w');
    fputcsv($echo, ['Milestone', 'Client', 'Gross', 'Fee', 'Net', 'Status', 'Date']);
    $dlSql = "SELECT p.*, m.title AS milestone_title, u.name AS payer_name FROM payments p JOIN milestones m ON p.milestone_id = m.id JOIN users u ON p.payer_id = u.id WHERE $whereClause ORDER BY p.created_at DESC";
    $dlStmt = $conn->prepare($dlSql);
    $dlStmt->bind_param($types, ...$params);
    $dlStmt->execute();
    $dlResult = $dlStmt->get_result();
    while ($r = $dlResult->fetch_assoc()) {
        fputcsv($echo, [
            $r['milestone_title'],
            $r['payer_name'],
            number_format((float) $r['total_amount'], 2),
            number_format((float) $r['platform_fee'], 2),
            number_format((float) $r['freelancer_net'], 2),
            ucfirst($r['status']),
            date('M d, Y', strtotime($r['created_at']))
        ]);
    }
    $dlStmt->close();
    fclose($echo);
    exit;
}

// Pagination
$page = max(1, intval($_GET['page'] ?? 1));
$perPage = 10;
$pagination = paginate($totalPayments, $perPage, $page);
$offset = $pagination['offset'];

$stmt = $conn->prepare("
    SELECT p.*, m.title AS milestone_title, u.name AS payer_name, u.profile_image AS payer_image
    FROM payments p
    JOIN milestones m ON p.milestone_id = m.id
    JOIN users u ON p.payer_id = u.id
    WHERE $whereClause
    ORDER BY p.created_at DESC
    LIMIT ? OFFSET ?
");
$allParams = array_merge($params, [$perPage, $offset]);
$allTypes = $types . 'ii';
$stmt->bind_param($allTypes, ...$allParams);
$stmt->execute();
$paymentsResult = $stmt->get_result();
$stmt->close();

// Build active filter chips
$activeChips = [];
if ($filterDate !== '') {
    $activeChips[] = ['key' => 'date_range', 'val' => $filterDate, 'label' => formatDateRangeLabel($filterDate)];
}
if ($filterType !== '') {
    $activeChips[] = ['key' => 'tx_type', 'val' => $filterType, 'label' => ucfirst($filterType)];
}
if ($filterClient !== '') {
    $clName = 'Client';
    foreach ($clientsList as $cl) {
        if ((string) $cl['id'] === $filterClient) {
            $clName = $cl['name'];
            break;
        }
    }
    $activeChips[] = ['key' => 'client', 'val' => $filterClient, 'label' => $clName];
}
if ($filterContract !== '') {
    $activeChips[] = ['key' => 'contract', 'val' => $filterContract, 'label' => 'Contract #' . $filterContract];
}

// Helper to build URL preserving other filters
function filterUrl($excludeKey = '', $excludeVal = '')
{
    $params = $_GET;
    unset($params['page'], $params['download']);
    if ($excludeKey !== '') {
        unset($params[$excludeKey]);
    }
    return '?' . http_build_query($params);
}

function filterUrlWith($key, $val)
{
    $params = $_GET;
    unset($params['page'], $params['download']);
    $params[$key] = $val;
    return '?' . http_build_query($params);
}

$pageTitle = 'Earnings';
$pageSubtitle = 'Track your income and payment history';
$activePage = 'earnings';
$user = ['name' => $user['name'] ?? 'Freelancer', 'profile_image' => $user['profile_image'] ?? null];
$unreadCount = get_unread_message_count($userId, 'freelancer');
require_once __DIR__ . '/../components/freelancer_header.php';
?>
<style>
    * { font-family:'Inter',system-ui,-apple-system,sans-serif; }
    .ov-col { flex:1; min-width:0; }
    .ov-label { display:flex; align-items:center; gap:5px; font-size:13px; color:#6B7280; margin:0 0 6px; font-weight:500; }
    .ov-val { font-size:28px; font-weight:700; color:#1A1A2E; margin:0; font-variant-numeric:tabular-nums; line-height:1.2; }
    .ov-sub { font-size:12px; color:#9CA3AF; margin:4px 0 0; }
    .tx-card { background:#fff; border:1px solid #E5E8EB; border-radius:10px; padding:24px; flex:1; min-width:0; }
    .tx-card-head { display:flex; align-items:flex-start; justify-content:space-between; margin-bottom:8px; }
    .tx-card-title { font-size:14px; font-weight:600; color:#1A1A2E; margin:0; }
    .tx-card-icon { width:32px; height:32px; display:flex; align-items:center; justify-content:center; color:#9CA3AF; flex-shrink:0; }
    .tx-card-val { font-size:24px; font-weight:700; color:#1A1A2E; margin:0 0 4px; font-variant-numeric:tabular-nums; }
    .tx-card-note { font-size:12px; color:#9CA3AF; margin:0; }
    .tx-card-desc { font-size:12px; color:#6B7280; margin:0 0 10px; line-height:1.5; }
    .tx-card-link { font-size:13px; color:#1A1A2E; font-weight:600; text-decoration:underline; text-underline-offset:2px; }
    .tx-card-link:hover { color:#108A00; }
    .btn-w { display:inline-flex; align-items:center; gap:6px; padding:8px 18px; border:1px solid #D1D5DB; border-radius:8px; background:#fff; color:#1A1A2E; font-size:13px; font-weight:600; cursor:pointer; transition:all .15s ease; }
    .btn-w:hover { border-color:#9CA3AF; background:#F9FAFB; }
    .filter-row { display:flex; flex-wrap:wrap; gap:16px; align-items:flex-end; }
    .filter-group { display:flex; flex-direction:column; gap:5px; }
    .filter-group label { font-size:12px; font-weight:600; color:#1A1A2E; }
    .filter-group select, .filter-group input { padding:8px 12px; border:1px solid #D1D5DB; border-radius:8px; font-size:13px; color:#374151; background:#fff; min-width:150px; appearance:auto; }
    .filter-group select:focus, .filter-group input:focus { outline:none; border-color:#108A00; }
    .qf-row { display:flex; flex-wrap:wrap; gap:8px; align-items:center; }
    .qf-label { font-size:12px; color:#6B7280; font-weight:500; }
    .qf-chip { display:inline-flex; align-items:center; padding:5px 14px; border:1px solid #D1D5DB; border-radius:20px; background:#fff; color:#374151; font-size:12px; font-weight:500; cursor:pointer; transition:all .12s ease; text-decoration:none; }
    .qf-chip:hover, .qf-chip.active { border-color:#1A1A2E; color:#1A1A2E; background:#F3F4F6; }
    .ft-bar { display:flex; align-items:center; gap:16px; padding:12px 16px; background:#F9FAFB; border:1px solid #E5E8EB; border-radius:8px; }
    .ft-bar-label { font-size:13px; font-weight:600; color:#1A1A2E; white-space:nowrap; padding-right:16px; border-right:1px solid #E5E8EB; }
    .ft-bar-text { font-size:13px; color:#6B7280; }
    .empty-state { text-align:center; padding:60px 24px; }
    .empty-icon { width:80px; height:80px; margin:0 auto 20px; display:flex; align-items:center; justify-content:center; }
    .empty-title { font-size:20px; font-weight:700; color:#1A1A2E; margin:0 0 8px; }
    .note-text { font-size:12px; color:#9CA3AF; margin:32px 0 0; }
</style>

<div style="background:#fff;min-height:100vh">
<div class="max-w-7xl mx-auto px-5 sm:px-8 py-10 sm:py-14">

    <!-- OVERVIEW SECTION -->
    <h1 class="text-[28px] font-bold m-0" style="color:#1A1A2E;margin-bottom:28px">Overview</h1>

    <div class="flex flex-col sm:flex-row gap-6 sm:gap-0" style="padding-bottom:28px;border-bottom:1px solid #E5E8EB;margin-bottom:0">
        <div class="ov-col">
            <p class="ov-label">Work in progress <i data-lucide="circle-info" class="text-[11px]" style="color:#9CA3AF;cursor:help"></i></p>
            <p class="ov-val"><?= format_currency(0) ?></p>
        </div>
        <div class="ov-col">
            <p class="ov-label">In review <i data-lucide="circle-info" class="text-[11px]" style="color:#9CA3AF;cursor:help"></i></p>
            <p class="ov-val"><?= format_currency(0) ?></p>
        </div>
        <div class="ov-col">
            <p class="ov-label">Pending <i data-lucide="circle-info" class="text-[11px]" style="color:#9CA3AF;cursor:help"></i></p>
            <p class="ov-val"><?= format_currency($pendingRelease) ?></p>
        </div>
        <div class="ov-col">
            <p class="ov-label">Available <i data-lucide="circle-info" class="text-[11px]" style="color:#9CA3AF;cursor:help"></i></p>
            <p class="ov-val"><?= format_currency($walletBalance) ?></p>
            <p class="ov-sub">Last payment: <?= format_currency($totalEarnings) ?></p>
        </div>
    </div>

    <!-- EMPTY STATE (Overview) -->
    <?php if ($totalPayments == 0 && $pendingRelease == 0): ?>
    <div style="padding:80px 0;text-align:center">
        <p class="text-[17px] m-0" style="color:#9CA3AF">You have no work in progress</p>
    </div>
    <?php endif; ?>

    <p class="note-text">Note: this report is updated every hour.</p>

    <!-- TRANSACTIONS SECTION -->
    <h1 class="text-[28px] font-bold m-0" style="color:#1A1A2E;margin-top:48px;margin-bottom:24px">Transactions</h1>

    <!-- Transaction Cards -->
    <div class="flex flex-col sm:flex-row gap-4" style="margin-bottom:28px">
        <!-- Pending Earnings -->
        <div class="tx-card">
            <div class="tx-card-head">
                <p class="tx-card-title">Pending earnings</p>
                <div class="tx-card-icon"><i data-lucide="hourglass"></i></div>
            </div>
            <p class="tx-card-val"><?= format_currency($pendingRelease) ?></p>
            <p class="tx-card-note">No pending transactions</p>
        </div>

        <!-- Withdrawal Schedule -->
        <div class="tx-card">
            <div class="tx-card-head">
                <p class="tx-card-title">Withdrawal schedule</p>
                <div class="tx-card-icon"><i data-lucide="history"></i></div>
            </div>
            <p class="tx-card-desc">You will be able to set up a withdrawal schedule once you've added a withdrawal method.</p>
            <a href="withdraw.php" class="tx-card-link">Add withdrawal method</a>
        </div>

        <!-- Available Balance -->
        <div class="tx-card">
            <div class="tx-card-head">
                <p class="tx-card-title">Available balance</p>
                <div class="tx-card-icon"><i data-lucide="dollar-sign"></i></div>
            </div>
            <p class="tx-card-val"><?= format_currency($walletBalance) ?></p>
            <div style="margin-top:12px">
                <a href="withdraw.php" class="btn-w">Withdrawals</a>
            </div>
        </div>
    </div>

    <!-- Filters -->
    <form method="GET" id="filterForm">
    <div class="filter-row" style="margin-bottom:12px">
        <!-- Date Range -->
        <div class="filter-group" style="position:relative;min-width:200px">
            <label>Date range</label>
            <div class="dropdown-trigger" onclick="toggleFilterDropdown('dateDropdown')" style="display:flex;align-items:center;justify-content:space-between;padding:8px 12px;border:1px solid #D1D5DB;border-radius:8px;font-size:13px;color:#374151;background:#fff;cursor:pointer;min-width:200px">
                <span><?= $filterDate !== '' ? $dateRanges[$filterDate]['label'] : 'All time' ?></span>
                <i data-lucide="calendar" class="text-[11px]" style="color:#9CA3AF"></i>
            </div>
            <div id="dateDropdown" class="dropdown-menu" style="display:none;position:absolute;top:100%;left:0;right:0;margin-top:4px;background:#fff;border:1px solid #E5E7EB;border-radius:10px;box-shadow:0 8px 24px rgba(0,0,0,.12);z-index:50;overflow:hidden">
                <?php foreach ($dateRanges as $dk => $dv): ?>
                    <a href="<?= filterUrlWith('date_range', $dk) ?>" style="display:block;padding:10px 14px;font-size:13px;color:<?= $filterDate === $dk ? '#111827' : '#374151' ?>;text-decoration:none;background:<?= $filterDate === $dk ? '#F3F4F6' : '#fff' ?>;<?= $filterDate === $dk ? 'font-weight:600' : '' ?>;transition:background .1s" onmouseover="this.style.background='#F9FAFB'" onmouseout="this.style.background='<?= $filterDate === $dk ? '#F3F4F6' : '#fff' ?>'">
                        <?= $dv['label'] ?>
                    </a>
                <?php endforeach; ?>
            </div>
        </div>

        <!-- Transaction Type -->
        <div class="filter-group">
            <label>Transaction type</label>
            <select onchange="applyFilter('tx_type', this.value)" style="min-width:140px">
                <option value="" <?= $filterType === '' ? 'selected' : '' ?>>All types</option>
                <option value="completed" <?= $filterType === 'completed' ? 'selected' : '' ?>>Earnings</option>
                <option value="escrow" <?= $filterType === 'escrow' ? 'selected' : '' ?>>Pending</option>
            </select>
        </div>

        <!-- Client -->
        <div class="filter-group" style="position:relative;min-width:160px">
            <label>Client</label>
            <div class="dropdown-trigger" onclick="toggleFilterDropdown('clientDropdown')" style="display:flex;align-items:center;justify-content:space-between;padding:8px 12px;border:1px solid #D1D5DB;border-radius:8px;font-size:13px;color:#374151;background:#fff;cursor:pointer;min-width:160px">
                <span><?= $filterClient !== '' ? htmlspecialchars($clName ?? 'Client') : 'All clients' ?></span>
                <i data-lucide="chevron-down" class="text-[10px]" style="color:#9CA3AF"></i>
            </div>
            <div id="clientDropdown" class="dropdown-menu" style="display:none;position:absolute;top:100%;left:0;right:0;margin-top:4px;background:#fff;border:1px solid #E5E7EB;border-radius:10px;box-shadow:0 8px 24px rgba(0,0,0,.12);z-index:50;overflow:hidden">
                <div style="padding:8px">
                    <input type="text" placeholder="Search" oninput="filterClientSearch(this.value)" style="width:100%;padding:7px 10px;border:1px solid #E5E7EB;border-radius:6px;font-size:12px;outline:none;box-sizing:border-box">
                </div>
                <a href="<?= filterUrl('client') ?>" style="display:flex;align-items:center;gap:8px;padding:8px 14px;font-size:13px;color:#374151;text-decoration:none;background:<?= $filterClient === '' ? '#F3F4F6' : '#fff' ?>" onmouseover="this.style.background='#F9FAFB'" onmouseout="this.style.background='<?= $filterClient === '' ? '#F3F4F6' : '#fff' ?>'">
                    <?php if ($filterClient === ''): ?><i data-lucide="check" class="text-[10px]" style="color:#108A00"></i><?php else: ?><span style="width:10px"></span><?php endif; ?>
                    All clients
                </a>
                <div id="clientList">
                <?php foreach ($clientsList as $cl): ?>
                    <a href="<?= filterUrlWith('client', $cl['id']) ?>" class="client-item" style="display:flex;align-items:center;gap:8px;padding:8px 14px;font-size:13px;color:#374151;text-decoration:none;background:<?= (string) $filterClient === (string) $cl['id'] ? '#F3F4F6' : '#fff' ?>" onmouseover="this.style.background='#F9FAFB'" onmouseout="this.style.background='<?= (string) $filterClient === (string) $cl['id'] ? '#F3F4F6' : '#fff' ?>'">
                        <?php if ((string) $filterClient === (string) $cl['id']): ?><i data-lucide="check" class="text-[10px]" style="color:#108A00"></i><?php else: ?><span style="width:10px"></span><?php endif; ?>
                        <?= htmlspecialchars($cl['name']) ?>
                    </a>
                <?php endforeach; ?>
                </div>
            </div>
        </div>

        <!-- Contract -->
        <div class="filter-group" style="position:relative;min-width:160px">
            <label>Contract</label>
            <div class="dropdown-trigger" onclick="toggleFilterDropdown('contractDropdown')" style="display:flex;align-items:center;justify-content:space-between;padding:8px 12px;border:1px solid #D1D5DB;border-radius:8px;font-size:13px;color:#374151;background:#fff;cursor:pointer;min-width:160px">
                <span><?= $filterContract !== '' ? 'Contract #' . $filterContract : 'All contracts' ?></span>
                <i data-lucide="chevron-down" class="text-[10px]" style="color:#9CA3AF"></i>
            </div>
            <div id="contractDropdown" class="dropdown-menu" style="display:none;position:absolute;top:100%;left:0;right:0;margin-top:4px;background:#fff;border:1px solid #E5E7EB;border-radius:10px;box-shadow:0 8px 24px rgba(0,0,0,.12);z-index:50;overflow:hidden">
                <a href="<?= filterUrl('contract') ?>" style="display:flex;align-items:center;gap:8px;padding:8px 14px;font-size:13px;color:#374151;text-decoration:none;background:<?= $filterContract === '' ? '#F3F4F6' : '#fff' ?>" onmouseover="this.style.background='#F9FAFB'" onmouseout="this.style.background='<?= $filterContract === '' ? '#F3F4F6' : '#fff' ?>'">
                    <?php if ($filterContract === ''): ?><i data-lucide="check" class="text-[10px]" style="color:#108A00"></i><?php else: ?><span style="width:10px"></span><?php endif; ?>
                    All contracts
                </a>
                <?php foreach ($contractsList as $ct): ?>
                    <a href="<?= filterUrlWith('contract', $ct['contract_id']) ?>" style="display:flex;align-items:center;gap:8px;padding:8px 14px;font-size:13px;color:#374151;text-decoration:none;background:<?= (string) $filterContract === (string) $ct['contract_id'] ? '#F3F4F6' : '#fff' ?>" onmouseover="this.style.background='#F9FAFB'" onmouseout="this.style.background='<?= (string) $filterContract === (string) $ct['contract_id'] ? '#F3F4F6' : '#fff' ?>">
                        <?php if ((string) $filterContract === (string) $ct['contract_id']): ?><i data-lucide="check" class="text-[10px]" style="color:#108A00"></i><?php else: ?><span style="width:10px"></span><?php endif; ?>
                        <?= htmlspecialchars($ct['title'] ?: 'Contract #' . $ct['contract_id']) ?>
                    </a>
                <?php endforeach; ?>
            </div>
        </div>

        <!-- Download -->
        <div style="margin-left:auto;position:relative">
            <div class="dropdown-trigger" onclick="toggleFilterDropdown('downloadDropdown')" style="display:flex;align-items:center;gap:6px;padding:8px 12px;border:1px solid #D1D5DB;border-radius:8px;font-size:13px;font-weight:500;color:#6B7280;background:#fff;cursor:pointer">
                Select download <i data-lucide="chevron-down" class="text-[10px]"></i>
            </div>
            <div id="downloadDropdown" class="dropdown-menu" style="display:none;position:absolute;top:100%;right:0;margin-top:4px;background:#fff;border:1px solid #E5E7EB;border-radius:10px;box-shadow:0 8px 24px rgba(0,0,0,.12);z-index:50;overflow:hidden;min-width:160px">
                <a href="<?= filterUrlWith('download', 'csv') ?>" style="display:flex;align-items:center;gap:8px;padding:10px 14px;font-size:13px;color:#374151;text-decoration:none" onmouseover="this.style.background='#F9FAFB'" onmouseout="this.style.background='#fff'">
                    <i data-lucide="file-spreadsheet" class="text-[12px]" style="color:#9CA3AF"></i> CSV
                </a>
            </div>
        </div>
    </div>
    </form>

    <!-- Active Filter Chips -->
    <?php if ($hasFilters): ?>
    <div class="qf-row" style="margin-bottom:16px">
        <a href="<?= filterUrl() ?>" style="display:inline-flex;align-items:center;gap:5px;padding:5px 12px;border-radius:20px;font-size:12px;font-weight:500;text-decoration:none;background:#FEE2E2;color:#DC2626;border:1px solid #FECACA">
            <i data-lucide="x" class="text-[9px]"></i> Clear filter (<?= count($activeChips) ?>)
        </a>
        <?php foreach ($activeChips as $chip): ?>
            <span style="display:inline-flex;align-items:center;gap:5px;padding:5px 12px;border-radius:20px;font-size:12px;font-weight:500;background:#F3F4F6;color:#374151;border:1px solid #E5E7EB">
                <?= htmlspecialchars($chip['label']) ?>
                <a href="<?= filterUrl($chip['key'], $chip['val']) ?>" style="color:#9CA3AF;text-decoration:none;margin-left:2px"><i data-lucide="x" class="text-[9px]"></i></a>
            </span>
        <?php endforeach; ?>
    </div>
    <?php endif; ?>

    <!-- Filtered Totals Bar -->
    <div class="ft-bar" style="margin-bottom:24px">
        <span class="ft-bar-label">Filtered totals</span>
        <div style="display:flex;align-items:center;gap:20px;flex-wrap:wrap">
            <span style="font-size:13px;color:#6B7280">Earnings: <i data-lucide="circle-info" class="text-[10px]" style="color:#9CA3AF;cursor:help"></i> <strong style="color:#111827"><?= format_currency($filteredEarnings) ?></strong></span>
            <span style="font-size:13px;color:#6B7280">Fees: <i data-lucide="circle-info" class="text-[10px]" style="color:#9CA3AF;cursor:help"></i> <strong style="color:#111827"><?= format_currency($filteredFees) ?></strong></span>
            <span style="font-size:13px;color:#6B7280">Net total: <i data-lucide="circle-info" class="text-[10px]" style="color:#9CA3AF;cursor:help"></i> <strong style="color:#111827"><?= format_currency($filteredNet) ?></strong></span>
        </div>
    </div>

    <!-- Transaction Table / Empty State -->
    <?php if ($paymentsResult->num_rows > 0): ?>
        <div style="background:#F9FAFB;border-radius:12px;overflow:hidden;margin-bottom:16px">
            <div class="overflow-x-auto">
                <table class="tbl w-full">
                    <thead>
                        <tr>
                            <th class="text-left px-6 py-3 text-[11px] font-semibold uppercase tracking-wider" style="color:#6B7280;border-bottom:1px solid #E5E8EB">Milestone</th>
                            <th class="text-left px-6 py-3 text-[11px] font-semibold uppercase tracking-wider" style="color:#6B7280;border-bottom:1px solid #E5E8EB">Client</th>
                            <th class="text-right px-6 py-3 text-[11px] font-semibold uppercase tracking-wider" style="color:#6B7280;border-bottom:1px solid #E5E8EB">Gross</th>
                            <th class="text-right px-6 py-3 text-[11px] font-semibold uppercase tracking-wider" style="color:#6B7280;border-bottom:1px solid #E5E8EB">Fee</th>
                            <th class="text-right px-6 py-3 text-[11px] font-semibold uppercase tracking-wider" style="color:#6B7280;border-bottom:1px solid #E5E8EB">Net</th>
                            <th class="text-left px-6 py-3 text-[11px] font-semibold uppercase tracking-wider" style="color:#6B7280;border-bottom:1px solid #E5E8EB">Status</th>
                            <th class="text-right px-6 py-3 text-[11px] font-semibold uppercase tracking-wider" style="color:#6B7280;border-bottom:1px solid #E5E8EB">Date</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php
                        while ($pay = $paymentsResult->fetch_assoc()):
                            $payerImg = get_profile_image($pay['payer_image'] ?? null);
                            $payerHasImg = !empty($pay['payer_image']) && $payerImg !== '/jobhub/assets/upload/profile.png';
                            $payerInitial = strtoupper(substr($pay['payer_name'], 0, 1));
                            $isCompleted = $pay['status'] === 'completed';
                            ?>
                            <tr style="border-bottom:1px solid #E5E8EB">
                                <td class="px-6 py-3.5">
                                    <p class="text-[13px] font-semibold m-0" style="color:#1A1A2E"><?= sanitize_string($pay['milestone_title']) ?></p>
                                    <!-- <p class="text-[11px] mt-0.5 m-0" style="color:#9CA3AF">#<?= $pay['milestone_id'] ?></p> -->
                                </td>
                                <td class="px-6 py-3.5">
                                    <div class="flex items-center gap-2.5">
                                        <?php if ($payerHasImg): ?>
                                            <img src="<?= htmlspecialchars($payerImg) ?>" class="w-7 h-7 rounded-full object-cover" alt="">
                                        <?php else: ?>
                                            <div class="w-7 h-7 rounded-full flex items-center justify-center text-[11px] font-semibold" style="background:#E5E8EB;color:#6B7280"><?= $payerInitial ?></div>
                                        <?php endif; ?>
                                        <span class="text-[13px] font-medium" style="color:#1A1A2E"><?= sanitize_string($pay['payer_name']) ?></span>
                                    </div>
                                </td>
                                <td class="px-6 py-3.5 text-right text-[13px]" style="color:#374151;font-variant-numeric:tabular-nums"><?= format_currency((float) $pay['total_amount']) ?></td>
                                <td class="px-6 py-3.5 text-right text-[13px]" style="color:#9CA3AF;font-variant-numeric:tabular-nums">-<?= format_currency((float) $pay['platform_fee']) ?></td>
                                <td class="px-6 py-3.5 text-right text-[13px] font-semibold" style="color:<?= $isCompleted ? '#16A34A' : '#1A1A2E' ?>;font-variant-numeric:tabular-nums"><?= format_currency((float) $pay['freelancer_net']) ?></td>
                                <td class="px-6 py-3.5">
                                    <?php if ($isCompleted): ?>
                                        <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-[11px] font-medium" style="background:#DCFCE7;color:#16A34A">Completed</span>
                                    <?php else: ?>
                                        <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-[11px] font-medium" style="background:#F3F4F6;color:#6B7280"><?= sanitize_string(ucfirst($pay['status'])) ?></span>
                                    <?php endif; ?>
                                </td>
                                <td class="px-6 py-3.5 text-right text-[13px]" style="color:#9CA3AF;font-variant-numeric:tabular-nums"><?= date('M d, Y', strtotime($pay['created_at'])) ?></td>
                            </tr>
                        <?php endwhile; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <?php if ($pagination['total_pages'] > 1): ?>
            <div class="flex flex-col sm:flex-row items-center justify-between gap-3 px-2 py-3">
                <p class="text-[13px] mb-0" style="color:#9CA3AF">Page <span class="font-medium" style="color:#6B7280"><?= $pagination['current_page'] ?></span> of <span class="font-medium" style="color:#6B7280"><?= $pagination['total_pages'] ?></span></p>
                <div class="flex items-center gap-1">
                    <?php if ($pagination['has_prev']): ?>
                        <a href="<?= filterUrlWith('page', $pagination['current_page'] - 1) ?>" class="w-8 h-8 flex items-center justify-center rounded-lg text-[12px]" style="border:1px solid #D1D5DB;color:#6B7280"><i data-lucide="chevron-left" class="text-[10px]"></i></a>
                    <?php endif; ?>
                    <?php for ($i = max(1, $pagination['current_page'] - 2); $i <= min($pagination['total_pages'], $pagination['current_page'] + 2); $i++): ?>
                        <a href="<?= filterUrlWith('page', $i) ?>" class="w-8 h-8 flex items-center justify-center rounded-lg text-[13px] font-medium" style="<?= $i === $pagination['current_page'] ? 'background:#108A00;color:#fff' : 'color:#6B7280;border:1px solid #E5E8EB' ?>"><?= $i ?></a>
                    <?php endfor; ?>
                    <?php if ($pagination['has_next']): ?>
                        <a href="<?= filterUrlWith('page', $pagination['current_page'] + 1) ?>" class="w-8 h-8 flex items-center justify-center rounded-lg text-[12px]" style="border:1px solid #D1D5DB;color:#6B7280"><i data-lucide="chevron-right" class="text-[10px]"></i></a>
                    <?php endif; ?>
                </div>
            </div>
        <?php endif; ?>

    <?php else: ?>
        <div class="empty-state">
            <div class="empty-icon">
                <?php if ($hasFilters): ?>
                <svg width="72" height="72" viewBox="0 0 72 72" fill="none">
                    <rect x="8" y="16" width="56" height="48" rx="6" fill="#E5E8EB"/>
                    <path d="M8 28h56" stroke="#D1D5DB" stroke-width="1"/>
                    <rect x="20" y="36" width="20" height="14" rx="3" fill="#108A00"/>
                    <path d="M44 40h12" stroke="#108A00" stroke-width="2" stroke-linecap="round"/>
                    <path d="M44 46h8" stroke="#108A00" stroke-width="2" stroke-linecap="round"/>
                    <circle cx="52" cy="20" r="6" fill="#108A00" opacity=".15"/>
                    <path d="M50 18l1.5 1.5L54 17" stroke="#108A00" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/>
                </svg>
                <?php else: ?>
                <svg width="72" height="72" viewBox="0 0 72 72" fill="none">
                    <rect x="8" y="16" width="56" height="48" rx="6" fill="#E5E8EB"/>
                    <path d="M8 28h56" stroke="#D1D5DB" stroke-width="1"/>
                    <rect x="20" y="36" width="20" height="14" rx="3" fill="#108A00"/>
                    <path d="M44 40h12" stroke="#108A00" stroke-width="2" stroke-linecap="round"/>
                    <path d="M44 46h8" stroke="#108A00" stroke-width="2" stroke-linecap="round"/>
                    <circle cx="52" cy="20" r="6" fill="#108A00" opacity=".15"/>
                    <path d="M50 18l1.5 1.5L54 17" stroke="#108A00" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/>
                </svg>
                <?php endif; ?>
            </div>
            <?php if ($hasFilters): ?>
                <p class="empty-title">No transactions based on filters.</p>
                <a href="<?= filterUrl() ?>" style="font-size:14px;color:#108A00;font-weight:600;text-decoration:underline;text-underline-offset:2px">Clear filters</a>
            <?php else: ?>
                <p class="empty-title">No transactions yet.</p>
            <?php endif; ?>
        </div>
    <?php endif; ?>

</div>
</div>

<script>
function toggleFilterDropdown(id) {
    document.querySelectorAll('#dateDropdown, #clientDropdown, #contractDropdown, #downloadDropdown').forEach(function(el) {
        if (el.id !== id) el.style.display = 'none';
    });
    var dd = document.getElementById(id);
    dd.style.display = dd.style.display === 'none' ? 'block' : 'none';
}
document.addEventListener('click', function(e) {
    if (!e.target.closest('.dropdown-trigger') && !e.target.closest('.dropdown-menu')) {
        document.querySelectorAll('#dateDropdown, #clientDropdown, #contractDropdown, #downloadDropdown').forEach(function(el) { el.style.display = 'none'; });
    }
});
function applyFilter(key, val) {
    var url = new URL(window.location.href);
    url.searchParams.delete('page');
    if (val) { url.searchParams.set(key, val); } else { url.searchParams.delete(key); }
    window.location.href = url.toString();
}
function filterClientSearch(q) {
    var items = document.querySelectorAll('.client-item');
    q = q.toLowerCase();
    items.forEach(function(el) {
        el.style.display = el.textContent.toLowerCase().indexOf(q) > -1 ? '' : 'none';
    });
}
</script>
<?php $conn->close(); ?>
<?php require_once __DIR__ . '/../components/freelancer_footer.php'; ?>