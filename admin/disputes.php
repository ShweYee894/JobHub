<?php
/**
 * Admin Dispute Tickets Dashboard
 * List, filter, view, and resolve dispute tickets.
 */
require_once __DIR__ . '/../auth/auth.php';
require_role('admin');
require_once __DIR__ . '/../config/db.php';

$currentPage = 'disputes';

// ── Filters ──────────────────────────────────────────────────────────
$statusF = $_GET['status'] ?? '';
$page    = max(1, sanitize_int($_GET['page'] ?? 1));
$perPage = 15;

$allowedStatuses = ['open', 'investigating', 'resolved', 'dismissed', 'escalated'];
if ($statusF && !in_array($statusF, $allowedStatuses)) {
    $statusF = '';
}

// ── Statistics ────────────────────────────────────────────────────────
$stats = [];

$r = $conn->query("SELECT COUNT(*) AS cnt FROM dispute_tickets");
$stats['total'] = (int) $r->fetch_assoc()['cnt'];

$r = $conn->query("SELECT COUNT(*) AS cnt FROM dispute_tickets WHERE status = 'open'");
$stats['open'] = (int) $r->fetch_assoc()['cnt'];

$r = $conn->query("SELECT COUNT(*) AS cnt FROM dispute_tickets WHERE status = 'investigating'");
$stats['investigating'] = (int) $r->fetch_assoc()['cnt'];

$r = $conn->query("SELECT COUNT(*) AS cnt FROM dispute_tickets WHERE status = 'escalated'");
$stats['escalated'] = (int) $r->fetch_assoc()['cnt'];

$r = $conn->query("SELECT COUNT(*) AS cnt FROM dispute_tickets WHERE status = 'resolved'");
$stats['resolved'] = (int) $r->fetch_assoc()['cnt'];

$r = $conn->query("SELECT COUNT(*) AS cnt FROM dispute_tickets WHERE status = 'dismissed'");
$stats['dismissed'] = (int) $r->fetch_assoc()['cnt'];

// ── Build query ───────────────────────────────────────────────────────
$where   = [];
$params  = [];
$bindTypes = '';

if ($statusF !== '') {
    $where[]  = 'd.status = ?';
    $params[] = $statusF;
    $bindTypes .= 's';
}

$whereSql = $where ? 'WHERE ' . implode(' AND ', $where) : '';

$countSql = "SELECT COUNT(*) AS cnt FROM dispute_tickets d $whereSql";
$countStmt = $conn->prepare($countSql);
if ($bindTypes) $countStmt->bind_param($bindTypes, ...$params);
$countStmt->execute();
$totalItems = (int) $countStmt->get_result()->fetch_assoc()['cnt'];
$countStmt->close();

$pagination = paginate($totalItems, $perPage, $page);
$offset = $pagination['offset'];

$querySql = "SELECT d.id, d.reason, d.description, d.status, d.resolution, d.created_at, d.updated_at,
                    d.contract_id, d.milestone_id,
                    c.total_budget, c.status AS contract_status,
                    j.title AS job_title,
                    ru.name AS raised_by_name, ru.email AS raised_by_email, ru.profile_image AS raised_by_image,
                    au.name AS against_name, au.email AS against_email, au.profile_image AS against_image,
                    CASE WHEN d.raised_by = c.client_id THEN 'client' ELSE 'freelancer' END AS raised_by_role,
                    CASE WHEN d.against = c.client_id THEN 'client' ELSE 'freelancer' END AS against_role,
                    m.title AS milestone_title, m.amount AS milestone_amount,
                    rv.name AS resolved_by_name
             FROM dispute_tickets d
             JOIN contracts c ON d.contract_id = c.id
             JOIN jobs j ON c.job_id = j.id
             JOIN users ru ON d.raised_by = ru.id
             JOIN users au ON d.against = au.id
             LEFT JOIN milestones m ON d.milestone_id = m.id
             LEFT JOIN users rv ON d.resolved_by = rv.id
             $whereSql
             ORDER BY FIELD(d.status, 'open', 'investigating', 'escalated', 'resolved', 'dismissed'), d.created_at DESC
             LIMIT ? OFFSET ?";
$bindTypes .= 'ii';
$params[]   = $perPage;
$params[]   = $offset;

$queryStmt = $conn->prepare($querySql);
$queryStmt->bind_param($bindTypes, ...$params);
$queryStmt->execute();
$result = $queryStmt->get_result();
$queryStmt->close();

$disputes = [];
while ($row = $result->fetch_assoc()) {
    $disputes[] = $row;
}

// ── Badge maps ────────────────────────────────────────────────────────
$statusColors = [
    'open'          => 'bg-red-50 text-red-600 border-red-200',
    'investigating' => 'bg-amber-50 text-amber-600 border-amber-200',
    'escalated'     => 'bg-purple-50 text-purple-600 border-purple-200',
    'resolved'      => 'bg-emerald-50 text-emerald-600 border-emerald-200',
    'dismissed'     => 'bg-gray-50 text-gray-500 border-gray-200',
];
$statusIcons = [
    'open'          => 'circle-alert',
    'investigating' => 'search',
    'escalated'     => 'arrow-up',
    'resolved'      => 'circle-check',
    'dismissed'     => 'circle-x',
];
$reasonLabels = [
    'non_delivery'  => 'Non-Delivery',
    'quality_issue' => 'Quality Issue',
    'scope_dispute' => 'Scope Dispute',
    'payment_issue' => 'Payment Issue',
    'other'         => 'Other',
];
$reasonColors = [
    'non_delivery'  => 'bg-red-50 text-red-500',
    'quality_issue' => 'bg-amber-50 text-amber-500',
    'scope_dispute' => 'bg-blue-50 text-blue-500',
    'payment_issue' => 'bg-purple-50 text-purple-500',
    'other'         => 'bg-gray-50 text-gray-500',
];

// ── Pagination base URL ─────────────────────────────────────────────
$baseUrl = 'disputes.php?';
if ($statusF !== '') $baseUrl .= 'status=' . urlencode($statusF) . '&';
$baseUrl = rtrim($baseUrl, '?&');
if (strpos($baseUrl, '&') === false) {
    $baseUrl = rtrim($baseUrl, '?');
}

// ── Layout setup ─────────────────────────────────────────────────────
$_userId = $_SESSION['user_id'];
$sStmt = $conn->prepare('SELECT name, profile_image FROM users WHERE id = ?');
$sStmt->bind_param('i', $_userId);
$sStmt->execute();
$_navUserRow = $sStmt->get_result()->fetch_assoc();
$sStmt->close();
$adminName = $_navUserRow['name'] ?? 'Admin';

$conn->close();

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
$pageTitle = 'Dispute Tickets';
$pageSubtitle = $totalItems . ' dispute' . ($totalItems !== 1 ? 's' : '') . ' total';
$activePage = 'disputes';
$user = ['name' => $adminName, 'profile_image' => $_navUserRow['profile_image'] ?? null];
$unreadCount = 0;
$profileLink = 'profile.php';
require_once __DIR__ . '/../components/layout_start.php';
?>
    <style>
    .kpi-mini{transition:transform .2s,box-shadow .2s}
    .kpi-mini:hover{transform:translateY(-1px);box-shadow:0 4px 15px rgba(0,0,0,.05)}

    /* Master-Detail Layout */
    .disp-layout{display:flex;gap:0;min-height:600px}
    .disp-queue{width:38%;min-width:320px;border-right:1px solid #E4EBE4;display:flex;flex-direction:column;overflow:hidden;flex-shrink:0}
    html.dark .disp-queue{border-color:#334155}
    .disp-queue-header{padding:16px;border-bottom:1px solid #E4EBE4;flex-shrink:0}
    html.dark .disp-queue-header{border-color:#334155}
    .disp-queue-list{flex:1;overflow-y:auto;padding:8px}
    .disp-detail{width:62%;display:flex;flex-direction:column;overflow:hidden}
    .disp-detail-scroll{flex:1;overflow-y:auto;padding:24px}

    /* Queue Card */
    .disp-card{border:1px solid #E4EBE4;border-radius:8px;padding:12px 14px;margin-bottom:6px;cursor:pointer;transition:all .15s;background:#fff}
    html.dark .disp-card{border-color:#334155;background:#1e293b}
    .disp-card:hover{border-color:#93c5fd;background:#f9fafb}
    html.dark .disp-card:hover{border-color:#334155;background:#263548}
    .disp-card.active{border-color:#3b82f6;background:#eff6ff;box-shadow:0 0 0 1px #3b82f6}
    html.dark .disp-card.active{border-color:#3b82f6;background:#1e3a5f}

    /* Detail Sections */
    .ds-card{background:#fff;border:1px solid #E4EBE4;border-radius:8px;padding:20px;margin-bottom:16px}
    html.dark .ds-card{background:#1e293b;border-color:#334155}
    .ds-label{font-size:10px;font-weight:600;color:#9ca3af;letter-spacing:.05em;text-transform:uppercase;margin-bottom:8px}

    /* Evidence Grid */
    .evidence-grid{display:grid;grid-template-columns:1fr 1fr;gap:16px}
    @media(max-width:1024px){.evidence-grid{grid-template-columns:1fr}}

    /* Timeline */
    .tl-item{display:flex;gap:12px;padding-bottom:20px;position:relative}
    .tl-item:last-child{padding-bottom:0}
    .tl-dot{width:10px;height:10px;border-radius:50%;border:2px solid;flex-shrink:0;margin-top:4px}
    .tl-line{position:absolute;left:4px;top:14px;bottom:0;width:2px}
    .tl-item:last-child .tl-line{display:none}

    /* Status Pills */
    .st-pill{display:inline-flex;align-items:center;gap:4px;padding:4px 10px;border-radius:6px;font-size:11px;font-weight:600;border:1px solid;cursor:pointer;transition:all .15s}
    .st-pill:hover{opacity:.85}
    .st-pill.active{box-shadow:0 0 0 2px rgba(59,130,246,.3)}

    /* Pagination flush */
    nav.flex.items-center.justify-center.gap-6{justify-content:space-between!important;background:#fff;border:1px solid #E4EBE4;border-top:none;border-radius:0 0 8px 8px;padding:12px 20px;margin-top:0}
    html.dark nav.flex.items-center.justify-center.gap-6{background:#1e293b;border-color:#334155}
    nav.flex.items-center.justify-center.gap-6 .flex.items-center.gap-2{gap:6px}
    nav.flex.items-center.justify-center.gap-6 a.w-9.h-9,nav.flex.items-center.justify-center.gap-6 span.w-9.h-9{width:32px!important;height:32px!important;border-radius:6px!important;border:1px solid #E4EBE4!important;background:#fff!important;color:#6b7280!important;font-size:12px!important;font-weight:500!important}
    html.dark nav.flex.items-center.justify-center.gap-6 a.w-9.h-9,html.dark nav.flex.items-center.justify-center.gap-6 span.w-9.h-9{border-color:#475569!important;background:#1e293b!important;color:#94a3b8!important}
    nav.flex.items-center.justify-center.gap-6 a.w-9.h-9:hover{background:#f9fafb!important;color:#374151!important}
    html.dark nav.flex.items-center.justify-center.gap-6 a.w-9.h-9:hover{background:#334155!important;color:#e2e8f0!important}
    nav.flex.items-center.justify-center.gap-6 a.w-9.h-9.bg-gray-900,nav.flex.items-center.justify-center.gap-6 a.w-9.h-9[class*="bg-gray-900"]{background:#108A00!important;border-color:#108A00!important;color:#fff!important;box-shadow:none!important}
    html.dark nav.flex.items-center.justify-center.gap-6 a.w-9.h-9.bg-gray-900,html.dark nav.flex.items-center.justify-center.gap-6 a.w-9.h-9[class*="bg-gray-900"]{background:#108A00!important;border-color:#108A00!important;color:#fff!important}
    nav.flex.items-center.justify-center.gap-6 span.w-9.h-9.cursor-not-allowed{opacity:.4}
    </style>

    <?php display_flash('success'); ?>
    <?php display_flash('error'); ?>

    <!-- ═══ KPI CARDS ════════════════════════════════════════════════════ -->
    <div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-5 gap-3">
        <div class="kpi-mini fade-in rounded-lg p-4 border border-[#E4EBE4] dark:border-slate-700 bg-white dark:bg-slate-800 shadow-sm" style="animation-delay:0s">
            <div class="flex items-center gap-2 mb-2">
                <div class="flex items-center justify-center w-8 h-8 rounded-lg bg-gray-100 dark:bg-slate-700">
                    <svg class="w-4 h-4 text-gray-500 dark:text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"/></svg>
                </div>
                <p class="text-[10px] font-semibold text-gray-400 dark:text-slate-500 uppercase tracking-wider">Total</p>
            </div>
            <p class="text-xl font-extrabold text-gray-900 dark:text-white"><?= $stats['total'] ?></p>
        </div>
        <div class="kpi-mini fade-in rounded-lg p-4 border border-[#E4EBE4] dark:border-slate-700 bg-white dark:bg-slate-800 shadow-sm" style="animation-delay:.03s">
            <div class="flex items-center gap-2 mb-2">
                <div class="flex items-center justify-center w-8 h-8 rounded-lg bg-red-100 dark:bg-red-900/30">
                    <svg class="w-4 h-4 text-red-500 dark:text-red-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                </div>
                <p class="text-[10px] font-semibold text-gray-400 dark:text-slate-500 uppercase tracking-wider">Open</p>
            </div>
            <p class="text-xl font-extrabold text-red-600 dark:text-red-400"><?= $stats['open'] ?></p>
        </div>
        <div class="kpi-mini fade-in rounded-lg p-4 border border-[#E4EBE4] dark:border-slate-700 bg-white dark:bg-slate-800 shadow-sm" style="animation-delay:.06s">
            <div class="flex items-center gap-2 mb-2">
                <div class="flex items-center justify-center w-8 h-8 rounded-lg bg-amber-100 dark:bg-amber-900/30">
                    <svg class="w-4 h-4 text-amber-500 dark:text-amber-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/></svg>
                </div>
                <p class="text-[10px] font-semibold text-gray-400 dark:text-slate-500 uppercase tracking-wider">Investigating</p>
            </div>
            <p class="text-xl font-extrabold text-amber-600 dark:text-amber-400"><?= $stats['investigating'] ?></p>
        </div>
        <div class="kpi-mini fade-in rounded-lg p-4 border border-[#E4EBE4] dark:border-slate-700 bg-white dark:bg-slate-800 shadow-sm" style="animation-delay:.09s">
            <div class="flex items-center gap-2 mb-2">
                <div class="flex items-center justify-center w-8 h-8 rounded-lg bg-purple-100 dark:bg-purple-900/30">
                    <svg class="w-4 h-4 text-purple-500 dark:text-purple-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 7h8m0 0v8m0-8l-8 8-4-4-6 6"/></svg>
                </div>
                <p class="text-[10px] font-semibold text-gray-400 dark:text-slate-500 uppercase tracking-wider">Escalated</p>
            </div>
            <p class="text-xl font-extrabold text-purple-600 dark:text-purple-400"><?= $stats['escalated'] ?></p>
        </div>
        <div class="kpi-mini fade-in rounded-lg p-4 border border-[#E4EBE4] dark:border-slate-700 bg-white dark:bg-slate-800 shadow-sm" style="animation-delay:.12s">
            <div class="flex items-center gap-2 mb-2">
                <div class="flex items-center justify-center w-8 h-8 rounded-lg bg-emerald-100 dark:bg-emerald-900/30">
                    <svg class="w-4 h-4 text-emerald-500 dark:text-emerald-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                </div>
                <p class="text-[10px] font-semibold text-gray-400 dark:text-slate-500 uppercase tracking-wider">Resolved</p>
            </div>
            <p class="text-xl font-extrabold text-emerald-600 dark:text-emerald-400"><?= $stats['resolved'] ?></p>
        </div>
    </div>

    <!-- ═══ MASTER-DETAIL LAYOUT ══════════════════════════════════════════ -->
    <div class="disp-layout bg-white dark:bg-slate-800 border border-[#E4EBE4] dark:border-slate-700 rounded-lg overflow-hidden fade-in mt-4" style="animation-delay:.15s;overflow-y:auto">

        <!-- ═══ LEFT: DISPUTE QUEUE ═════════════════════════════════════ -->
        <div class="disp-queue">
            <!-- Queue Header: Search + Filter Pills -->
            <div class="disp-queue-header">
                <form method="GET" action="disputes.php" id="dispFilterForm">
                    <div class="relative mb-3">
                        <i data-lucide="search" class="absolute left-3 top-1/2 -translate-y-1/2 text-gray-400 dark:text-slate-500 text-sm"></i>
                        <input type="text" name="search" value="<?= sanitize_string($_GET['search'] ?? '') ?>" placeholder="Search by Dispute #, Contract, Client, or Freelancer..."
                               class="w-full pl-9 pr-4 py-2 rounded-lg border border-gray-200 dark:border-slate-600 text-sm bg-gray-50 dark:bg-slate-700 text-gray-900 dark:text-white placeholder-gray-400 dark:placeholder-slate-500 focus:outline-none focus:ring-2 focus:ring-blue-500/30 focus:border-blue-400 transition-all">
                    </div>
                    <div class="flex flex-wrap gap-1.5">
                        <?php
                        $pillCounts = [
                            '' => $stats['total'],
                            'open' => $stats['open'],
                            'investigating' => $stats['investigating'],
                            'resolved' => $stats['resolved'],
                        ];
                        $pillStyles = [
                            '' => 'bg-gray-100 dark:bg-slate-700 text-gray-600 dark:text-slate-300 border-gray-200 dark:border-slate-600',
                            'open' => 'bg-red-50 dark:bg-red-900/30 text-red-600 dark:text-red-400 border-red-200 dark:border-red-800',
                            'investigating' => 'bg-amber-50 dark:bg-amber-900/30 text-amber-600 dark:text-amber-400 border-amber-200 dark:border-amber-800',
                            'resolved' => 'bg-emerald-50 dark:bg-emerald-900/30 text-emerald-600 dark:text-emerald-400 border-emerald-200 dark:border-emerald-800',
                        ];
                        $pillLabels = ['' => 'All', 'open' => 'Open', 'investigating' => 'Investigating', 'resolved' => 'Resolved'];
                        foreach ($pillCounts as $key => $cnt):
                        ?>
                            <a href="disputes.php<?= $key !== '' ? '?status=' . $key : '' ?>" class="st-pill <?= $statusF === $key || ($statusF === '' && $key === '') ? 'active' : '' ?> <?= $pillStyles[$key] ?>">
                                <?= $pillLabels[$key] ?> (<?= $cnt ?>)
                            </a>
                        <?php endforeach; ?>
                    </div>
                </form>
            </div>

            <!-- Queue List -->
            <div class="disp-queue-list">
                <?php if (empty($disputes)): ?>
                    <div class="text-center py-16">
                        <div class="w-12 h-12 rounded-xl bg-gray-100 dark:bg-slate-700 flex items-center justify-center mx-auto mb-3">
                            <i data-lucide="hammer" class="text-lg text-gray-300 dark:text-slate-500"></i>
                        </div>
                        <p class="text-gray-500 dark:text-slate-400 text-sm">No disputes found</p>
                    </div>
                <?php else: ?>
                    <?php foreach ($disputes as $idx => $d): ?>
                        <?php
                        $did = (int) $d['id'];
                        $sClr = $statusColors[$d['status']] ?? '';
                        $sIcn = $statusIcons[$d['status']] ?? 'circle';
                        ?>
                        <div class="disp-card <?= $idx === 0 ? 'active' : '' ?>" id="qcard-<?= $did ?>" onclick="selectDispute(<?= $did ?>)">
                            <div class="flex items-start justify-between mb-1.5">
                                <div class="flex items-center gap-2">
                                    <span class="text-xs font-bold text-gray-500 dark:text-slate-400">#<?= $did ?></span>
                                    <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded text-[10px] font-bold border <?= $sClr ?>">
                                        <i data-lucide="<?= $sIcn ?>" class="text-[8px]"></i> <?= ucfirst($d['status']) ?>
                                    </span>
                                </div>
                                <span class="text-[10px] text-gray-400 dark:text-slate-500 flex-shrink-0"><?= time_ago($d['created_at']) ?></span>
                            </div>
                            <p class="text-[10px] font-semibold text-gray-400 dark:text-slate-500 mb-1">
                                <span class="<?= $reasonColors[$d['reason']] ?? '' ?> px-1.5 py-0.5 rounded text-[9px]"><?= $reasonLabels[$d['reason']] ?? $d['reason'] ?></span>
                            </p>
                            <p class="text-sm font-semibold text-gray-900 dark:text-white truncate mb-1"><?= sanitize_string($d['job_title']) ?></p>
                            <div class="flex items-center justify-between">
                                <span class="text-[10px] text-gray-400 dark:text-slate-500 truncate max-w-[65%]">
                                    <?= sanitize_string($d['raised_by_name']) ?> <span class="text-gray-300 dark:text-slate-600">vs</span> <?= sanitize_string($d['against_name']) ?>
                                </span>
                                <span class="text-xs font-bold text-gray-700 dark:text-gray-300"><?= format_currency((float) $d['milestone_amount'] ?? (float) $d['total_budget']) ?></span>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>

            <!-- Queue Pagination -->
            <div class="flex-shrink-0 border-t border-gray-100 dark:border-slate-700 px-4 py-2">
                <?php render_pagination($pagination, $baseUrl); ?>
            </div>
        </div>

        <!-- ═══ RIGHT: CASE DETAIL WORKSPACE ════════════════════════════ -->
        <div class="disp-detail" id="detailWorkspace">
            <?php if (!empty($disputes)): ?>
                <?php $d = $disputes[0]; $did = (int) $d['id']; ?>
                <div id="detailContent" data-dispute-id="<?= $did ?>">
                    <!-- Detail Header Bar -->
                    <div class="px-6 py-4 border-b border-gray-100 dark:border-slate-700 flex items-center justify-between flex-shrink-0">
                        <div class="flex items-center gap-3 min-w-0">
                            <h2 class="text-base font-bold text-gray-900 dark:text-white truncate">Dispute #<?= $did ?></h2>
                            <?php $sClr = $statusColors[$d['status']] ?? ''; $sIcn = $statusIcons[$d['status']] ?? 'circle'; ?>
                            <span class="inline-flex items-center gap-1 px-2.5 py-0.5 rounded text-[11px] font-semibold border <?= $sClr ?>">
                                <i data-lucide="<?= $sIcn ?>" class="text-[9px]"></i> <?= ucfirst($d['status']) ?>
                            </span>
                        </div>
                        <div class="flex items-center gap-2 flex-shrink-0">
                            <span class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg bg-amber-50 dark:bg-amber-900/20 border border-amber-200 dark:border-amber-800 text-xs font-bold text-amber-700 dark:text-amber-400">
                                <i data-lucide="lock" class="text-[10px]"></i> Held in Escrow: <?= format_currency((float) ($d['milestone_amount'] ?? 0)) ?>
                            </span>
                            <a href="contracts.php?id=<?= $d['contract_id'] ?>" class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg border border-gray-200 dark:border-slate-600 text-xs font-medium text-gray-600 dark:text-slate-300 hover:bg-gray-50 dark:hover:bg-slate-700 transition-colors">
                                Contract #<?= $d['contract_id'] ?> &middot; <?= format_currency((float) $d['total_budget']) ?>
                            </a>
                        </div>
                    </div>

                    <div class="disp-detail-scroll">

                        <!-- Evidence Comparison Grid -->
                        <div class="evidence-grid mb-4">
                            <!-- Raised By Party -->
                            <div class="ds-card">
                                <div class="flex items-center gap-2 mb-3">
                                    <div class="w-2 h-2 rounded-full bg-blue-500"></div>
                                    <p class="ds-label mb-0"><?= $d['raised_by_role'] === 'client' ? 'Client Claim' : 'Freelancer Claim' ?></p>
                                </div>
                                <div class="flex items-center gap-3 mb-3 pb-3 border-b border-gray-100 dark:border-slate-600/50">
                                    <img src="<?= get_profile_image($d['raised_by_image']) ?>" class="w-9 h-9 rounded-full object-cover border-2 border-gray-100 dark:border-slate-600 flex-shrink-0">
                                    <div class="min-w-0">
                                        <p class="text-sm font-semibold text-gray-900 dark:text-white truncate"><?= sanitize_string($d['raised_by_name']) ?></p>
                                        <p class="text-[11px] text-gray-400 dark:text-slate-500 truncate"><?= sanitize_string($d['raised_by_email']) ?></p>
                                    </div>
                                    <span class="ml-auto text-[9px] font-bold px-2 py-0.5 rounded bg-blue-100 dark:bg-blue-900/30 text-blue-600 dark:text-blue-400 flex-shrink-0">Raised Dispute</span>
                                </div>
                                <p class="text-sm text-gray-700 dark:text-slate-300 leading-relaxed"><?= nl2br(sanitize_string($d['description'])) ?></p>
                            </div>

                            <!-- Against Party -->
                            <div class="ds-card">
                                <div class="flex items-center gap-2 mb-3">
                                    <div class="w-2 h-2 rounded-full bg-emerald-500"></div>
                                    <p class="ds-label mb-0"><?= $d['against_role'] === 'freelancer' ? 'Freelancer Response' : 'Client Response' ?></p>
                                </div>
                                <div class="flex items-center gap-3 mb-3 pb-3 border-b border-gray-100 dark:border-slate-600/50">
                                    <img src="<?= get_profile_image($d['against_image']) ?>" class="w-9 h-9 rounded-full object-cover border-2 border-gray-100 dark:border-slate-600 flex-shrink-0">
                                    <div class="min-w-0">
                                        <p class="text-sm font-semibold text-gray-900 dark:text-white truncate"><?= sanitize_string($d['against_name']) ?></p>
                                        <p class="text-[11px] text-gray-400 dark:text-slate-500 truncate"><?= sanitize_string($d['against_email']) ?></p>
                                    </div>
                                    <span class="ml-auto text-[9px] font-bold px-2 py-0.5 rounded bg-amber-100 dark:bg-amber-900/30 text-amber-600 dark:text-amber-400 flex-shrink-0">Against</span>
                                </div>
                                <p class="text-sm text-gray-700 dark:text-slate-300 leading-relaxed italic">
                                    <?= !empty($d['resolution']) ? nl2br(sanitize_string($d['resolution'])) : '<span class="text-gray-400 dark:text-slate-500 not-italic">No response submitted yet.</span>' ?>
                                </p>
                            </div>
                        </div>

                        <!-- Milestone & Reason -->
                        <div class="grid grid-cols-2 gap-4 mb-4">
                            <?php if (!empty($d['milestone_id'])): ?>
                            <div class="ds-card">
                                <p class="ds-label">Milestone</p>
                                <p class="text-sm font-semibold text-gray-900 dark:text-white"><?= sanitize_string($d['milestone_title'] ?? 'Milestone #' . $d['milestone_id']) ?></p>
                                <p class="text-xs text-blue-600 dark:text-blue-400 font-bold mt-1"><?= format_currency((float) ($d['milestone_amount'] ?? 0)) ?></p>
                            </div>
                            <?php endif; ?>
                            <div class="ds-card">
                                <p class="ds-label">Dispute Reason</p>
                                <span class="inline-flex items-center px-2.5 py-1 rounded-lg text-xs font-bold <?= $reasonColors[$d['reason']] ?? '' ?> border border-current/10">
                                    <?= $reasonLabels[$d['reason']] ?? $d['reason'] ?>
                                </span>
                            </div>
                        </div>

                        <!-- Resolution Summary -->
                        <?php if (!empty($d['resolution'])): ?>
                        <div class="ds-card border-emerald-200 dark:border-emerald-800/50 bg-emerald-50/50 dark:bg-emerald-900/10 mb-4">
                            <div class="flex items-center gap-2 mb-2">
                                <svg class="w-4 h-4 text-emerald-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                                <p class="ds-label mb-0 text-emerald-600 dark:text-emerald-400">Resolution Summary</p>
                            </div>
                            <p class="text-sm text-gray-700 dark:text-slate-300 leading-relaxed mb-2"><?= nl2br(sanitize_string($d['resolution'])) ?></p>
                            <?php if (!empty($d['resolved_by_name'])): ?>
                                <p class="text-[11px] text-emerald-600 dark:text-emerald-400 font-semibold">
                                    <i data-lucide="shield-user" class="mr-1"></i> Resolved by <?= sanitize_string($d['resolved_by_name']) ?> &middot; <?= time_ago($d['updated_at']) ?>
                                </p>
                            <?php endif; ?>
                        </div>
                        <?php endif; ?>

                        <!-- Audit Trail & Timeline -->
                        <div class="ds-card mb-4">
                            <p class="ds-label mb-4">Audit Trail</p>
                            <div class="relative">
                                <?php
                                $timeline = [
                                    'open' => ['label' => 'Dispute Opened', 'icon' => 'circle-alert', 'color' => 'text-red-500', 'dot' => 'border-red-400 bg-red-50'],
                                    'investigating' => ['label' => 'Under Investigation', 'icon' => 'search', 'color' => 'text-amber-500', 'dot' => 'border-amber-400 bg-amber-50'],
                                    'escalated' => ['label' => 'Escalated to Admin', 'icon' => 'arrow-up', 'color' => 'text-purple-500', 'dot' => 'border-purple-400 bg-purple-50'],
                                    'resolved' => ['label' => 'Dispute Resolved', 'icon' => 'circle-check', 'color' => 'text-emerald-500', 'dot' => 'border-emerald-400 bg-emerald-50'],
                                    'dismissed' => ['label' => 'Dispute Dismissed', 'icon' => 'circle-x', 'color' => 'text-gray-400', 'dot' => 'border-gray-300 bg-gray-50'],
                                ];
                                $statusOrder = ['open', 'investigating', 'escalated', 'resolved', 'dismissed'];
                                $currentIdx = array_search($d['status'], $statusOrder);
                                if ($currentIdx === false) $currentIdx = 0;
                                foreach ($statusOrder as $idx => $st):
                                    $t = $timeline[$st];
                                    $reached = $idx <= $currentIdx;
                                    $isCurrent = $idx === $currentIdx;
                                ?>
                                <div class="tl-item">
                                    <div class="tl-dot <?= $reached ? $t['dot'] : 'border-gray-200 bg-white dark:bg-slate-800 dark:border-slate-600' ?> <?= $isCurrent ? 'ring-2 ring-offset-1 ring-blue-300 dark:ring-blue-700' : '' ?>"></div>
                                    <?php if ($idx < 4): ?>
                                        <div class="tl-line <?= $reached ? 'bg-gray-200 dark:bg-slate-600' : 'bg-gray-100 dark:bg-slate-700' ?>"></div>
                                    <?php endif; ?>
                                    <div class="min-w-0">
                                        <p class="text-xs font-bold <?= $reached ? $t['color'] : 'text-gray-300 dark:text-slate-600' ?> <?= $isCurrent ? '' : 'opacity-50' ?>"><?= $t['label'] ?></p>
                                        <?php if ($isCurrent): ?>
                                            <p class="text-[10px] text-gray-400 dark:text-slate-500 mt-0.5"><?= time_ago($d['updated_at']) ?></p>
                                        <?php endif; ?>
                                    </div>
                                </div>
                                <?php endforeach; ?>
                            </div>
                        </div>

                        <!-- Admin Action Footer -->
                        <?php if (in_array($d['status'], ['open', 'investigating', 'escalated'])): ?>
                        <div class="ds-card mb-4">
                            <p class="ds-label mb-3">Admin Actions</p>
                            <div class="flex flex-wrap gap-2">
                                <button onclick="changeStatus(<?= $did ?>, 'investigating')" class="inline-flex items-center gap-1.5 px-3 py-2 rounded-lg bg-amber-500 hover:bg-amber-600 text-white text-xs font-semibold transition-colors shadow-sm">
                                    <i data-lucide="search" class="text-[10px]"></i> Investigate
                                </button>
                                <button onclick="changeStatus(<?= $did ?>, 'escalated')" class="inline-flex items-center gap-1.5 px-3 py-2 rounded-lg bg-purple-500 hover:bg-purple-600 text-white text-xs font-semibold transition-colors shadow-sm">
                                    <i data-lucide="arrow-up" class="text-[10px]"></i> Escalate
                                </button>
                                <button onclick="openActionModal(<?= $did ?>, 'resolve')" class="inline-flex items-center gap-1.5 px-3 py-2 rounded-lg bg-emerald-500 hover:bg-emerald-600 text-white text-xs font-semibold transition-colors shadow-sm">
                                    <i data-lucide="check" class="text-[10px]"></i> Release to Freelancer
                                </button>
                                <button onclick="openActionModal(<?= $did ?>, 'dismiss')" class="inline-flex items-center gap-1.5 px-3 py-2 rounded-lg bg-red-500 hover:bg-red-600 text-white text-xs font-semibold transition-colors shadow-sm">
                                    <i data-lucide="undo" class="text-[10px]"></i> Refund Client
                                </button>
                                <button onclick="openActionModal(<?= $did ?>, 'resolve')" class="inline-flex items-center gap-1.5 px-3 py-2 rounded-lg border border-gray-200 dark:border-slate-600 bg-white dark:bg-slate-700 text-gray-700 dark:text-slate-300 text-xs font-semibold hover:bg-gray-50 dark:hover:bg-slate-600 transition-colors">
                                    <i data-lucide="divide" class="text-[10px]"></i> Split Escrow
                                </button>
                                <button onclick="changeStatus(<?= $did ?>, 'investigating')" class="inline-flex items-center gap-1.5 px-3 py-2 rounded-lg border border-gray-200 dark:border-slate-600 bg-white dark:bg-slate-700 text-gray-700 dark:text-slate-300 text-xs font-semibold hover:bg-gray-50 dark:hover:bg-slate-600 transition-colors">
                                    <i data-lucide="mail" class="text-[10px]"></i> Request Info
                                </button>
                            </div>
                        </div>
                        <?php endif; ?>

                    </div>
                </div>
            <?php else: ?>
                <div class="flex-1 flex items-center justify-center py-16">
                    <div class="text-center">
                        <div class="w-16 h-16 rounded-2xl bg-gray-100 dark:bg-slate-700 flex items-center justify-center mx-auto mb-4">
                            <i data-lucide="hammer" class="text-2xl text-gray-300 dark:text-slate-500"></i>
                        </div>
                        <p class="text-gray-500 dark:text-slate-400 font-medium mb-1">No disputes found</p>
                        <p class="text-gray-400 dark:text-slate-500 text-xs">Try adjusting your filters</p>
                    </div>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- ═══ ACTION MODAL (Resolve / Dismiss) ═══════════════════════════ -->
    <div id="actionModal" class="fixed inset-0 z-50 hidden">
        <div class="absolute inset-0 bg-black/40 backdrop-blur-sm" onclick="closeActionModal()"></div>
        <div class="absolute inset-0 flex items-center justify-center p-4">
            <div class="bg-white dark:bg-slate-800 rounded-2xl shadow-2xl w-full max-w-lg relative z-10 overflow-hidden" id="actionModalPanel">
                <div class="px-6 py-5 border-b border-gray-100 dark:border-slate-700" id="actionModalHeader">
                    <div class="flex items-center justify-between">
                        <div class="flex items-center gap-3">
                            <div class="w-10 h-10 rounded-xl flex items-center justify-center" id="actionModalIcon"></div>
                            <div>
                                <h3 class="text-lg font-bold text-gray-900 dark:text-white" id="actionModalTitle">Action</h3>
                                <p class="text-xs text-gray-400 dark:text-slate-500" id="actionModalSubtitle">Dispute #--</p>
                            </div>
                        </div>
                        <button onclick="closeActionModal()" class="w-8 h-8 rounded-lg bg-gray-100 dark:bg-slate-700 flex items-center justify-center text-gray-400 dark:text-slate-400 hover:text-gray-600 dark:hover:text-slate-200 transition-colors">
                            <i data-lucide="x" class="text-sm"></i>
                        </button>
                    </div>
                </div>
                <form id="actionForm" onsubmit="return submitAction(event)">
                    <div class="p-6 space-y-4">
                        <input type="hidden" name="action" id="actionType" value="resolve">
                        <input type="hidden" name="dispute_id" id="actionDisputeId">
                        <div>
                            <label class="block text-xs font-bold text-gray-700 dark:text-slate-300 mb-1.5" id="actionLabel">Resolution Note</label>
                            <textarea name="resolution" id="actionNote" required rows="4" placeholder="Explain the decision..." class="w-full px-4 py-2.5 rounded-xl border border-gray-200 dark:border-slate-600 text-sm bg-gray-50 dark:bg-slate-700 text-gray-900 dark:text-white focus:bg-white dark:focus:bg-slate-600 focus:ring-2 focus:ring-blue-500/30 focus:border-blue-400 resize-none transition-all" minlength="10"></textarea>
                            <p class="text-[10px] text-gray-400 dark:text-slate-500 mt-1">Minimum 10 characters</p>
                        </div>
                    </div>
                    <div class="px-6 pb-6 flex gap-3">
                        <button type="button" onclick="closeActionModal()" class="flex-1 px-4 py-2.5 rounded-xl border border-gray-200 dark:border-slate-600 text-sm font-semibold text-gray-600 dark:text-slate-300 hover:bg-gray-50 dark:hover:bg-slate-700 transition-colors">Cancel</button>
                        <button type="submit" class="flex-1 px-4 py-2.5 rounded-xl text-white text-sm font-semibold shadow-lg transition-colors" id="actionSubmitBtn">Confirm</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script>
    var CSRF_TOKEN = '<?= generate_csrf_token() ?>';
    var disputesData = <?= json_encode($disputes) ?>;

    // ── Select Dispute (Master-Detail) ───────────────────────────────
    function selectDispute(id) {
        // Update active card
        document.querySelectorAll('.disp-card').forEach(function(c) { c.classList.remove('active'); });
        var card = document.getElementById('qcard-' + id);
        if (card) card.classList.add('active');

        // Find dispute data
        var d = null;
        for (var i = 0; i < disputesData.length; i++) {
            if (parseInt(disputesData[i].id) === id) { d = disputesData[i]; break; }
        }
        if (!d) return;

        var isOpen = ['open', 'investigating', 'escalated'].indexOf(d.status) !== -1;
        var statusColors = <?= json_encode($statusColors) ?>;
        var statusIcons = <?= json_encode($statusIcons) ?>;
        var reasonLabels = <?= json_encode($reasonLabels) ?>;
        var reasonColors = <?= json_encode($reasonColors) ?>;
        var sClr = statusColors[d.status] || '';
        var sIcn = statusIcons[d.status] || 'circle';

        var timeline = {
            open: {label:'Dispute Opened',icon:'circle-alert',color:'text-red-500',dot:'border-red-400 bg-red-50'},
            investigating: {label:'Under Investigation',icon:'search',color:'text-amber-500',dot:'border-amber-400 bg-amber-50'},
            escalated: {label:'Escalated to Admin',icon:'arrow-up',color:'text-purple-500',dot:'border-purple-400 bg-purple-50'},
            resolved: {label:'Dispute Resolved',icon:'circle-check',color:'text-emerald-500',dot:'border-emerald-400 bg-emerald-50'},
            dismissed: {label:'Dispute Dismissed',icon:'circle-x',color:'text-gray-400',dot:'border-gray-300 bg-gray-50'}
        };
        var statusOrder = ['open','investigating','escalated','resolved','dismissed'];
        var currentIdx = statusOrder.indexOf(d.status);
        if (currentIdx === -1) currentIdx = 0;

        var msAmount = parseFloat(d.milestone_amount) || 0;
        var reasonLabel = reasonLabels[d.reason] || d.reason;
        var reasonClr = reasonColors[d.reason] || 'bg-gray-50 text-gray-500';

        // Timeline HTML
        var tlHtml = '';
        for (var si = 0; si < statusOrder.length; si++) {
            var st = statusOrder[si];
            var t = timeline[st];
            var reached = si <= currentIdx;
            var isCurrent = si === currentIdx;
            tlHtml += '<div class="tl-item">';
            tlHtml += '<div class="tl-dot ' + (reached ? t.dot : 'border-gray-200 bg-white dark:bg-slate-800 dark:border-slate-600') + (isCurrent ? ' ring-2 ring-offset-1 ring-blue-300 dark:ring-blue-700' : '') + '"></div>';
            if (si < 4) tlHtml += '<div class="tl-line ' + (reached ? 'bg-gray-200 dark:bg-slate-600' : 'bg-gray-100 dark:bg-slate-700') + '"></div>';
            tlHtml += '<div class="min-w-0"><p class="text-xs font-bold ' + (reached ? t.color : 'text-gray-300 dark:text-slate-600') + (isCurrent ? '' : ' opacity-50') + '">' + t.label + '</p>';
            if (isCurrent) tlHtml += '<p class="text-[10px] text-gray-400 dark:text-slate-500 mt-0.5">' + timeAgo(d.updated_at) + '</p>';
            tlHtml += '</div></div>';
        }

        var html = '';
        // Header
        html += '<div class="px-6 py-4 border-b border-gray-100 dark:border-slate-700 flex items-center justify-between flex-shrink-0">';
        html += '<div class="flex items-center gap-3 min-w-0">';
        html += '<h2 class="text-base font-bold text-gray-900 dark:text-white truncate">Dispute #' + d.id + '</h2>';
        html += '<span class="inline-flex items-center gap-1 px-2.5 py-0.5 rounded text-[11px] font-semibold border ' + sClr + '"><i data-lucide="' + sIcn + '" class="text-[9px]"></i> ' + d.status.charAt(0).toUpperCase() + d.status.slice(1) + '</span>';
        html += '</div>';
        html += '<div class="flex items-center gap-2 flex-shrink-0">';
        html += '<span class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg bg-amber-50 dark:bg-amber-900/20 border border-amber-200 dark:border-amber-800 text-xs font-bold text-amber-700 dark:text-amber-400"><i data-lucide="lock" class="text-[10px]"></i> Held in Escrow: $' + msAmount.toFixed(2) + '</span>';
        html += '<a href="contracts.php?id=' + d.contract_id + '" class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg border border-gray-200 dark:border-slate-600 text-xs font-medium text-gray-600 dark:text-slate-300 hover:bg-gray-50 dark:hover:bg-slate-700 transition-colors">Contract #' + d.contract_id + ' &middot; $' + parseFloat(d.total_budget).toFixed(2) + '</a>';
        html += '</div></div>';

        // Scrollable body
        html += '<div class="disp-detail-scroll">';

        // Evidence grid
        html += '<div class="evidence-grid mb-4">';
        // Client claim
        html += '<div class="ds-card"><div class="flex items-center gap-2 mb-3"><div class="w-2 h-2 rounded-full bg-blue-500"></div><p class="ds-label mb-0">Client Claim</p></div>';
        html += '<div class="flex items-center gap-3 mb-3 pb-3 border-b border-gray-100 dark:border-slate-600/50">';
        html += '<img src="' + d.raised_by_image + '" class="w-9 h-9 rounded-full object-cover border-2 border-gray-100 dark:border-slate-600 flex-shrink-0">';
        html += '<div class="min-w-0"><p class="text-sm font-semibold text-gray-900 dark:text-white truncate">' + escHtml(d.raised_by_name) + '</p><p class="text-[11px] text-gray-400 dark:text-slate-500 truncate">' + escHtml(d.raised_by_email) + '</p></div>';
        html += '<span class="ml-auto text-[9px] font-bold px-2 py-0.5 rounded bg-blue-100 dark:bg-blue-900/30 text-blue-600 dark:text-blue-400 flex-shrink-0">Raised Dispute</span>';
        html += '</div>';
        html += '<p class="text-sm text-gray-700 dark:text-slate-300 leading-relaxed">' + escHtml(d.description || '-') + '</p></div>';
        // Freelancer response
        html += '<div class="ds-card"><div class="flex items-center gap-2 mb-3"><div class="w-2 h-2 rounded-full bg-emerald-500"></div><p class="ds-label mb-0">Freelancer Response</p></div>';
        html += '<div class="flex items-center gap-3 mb-3 pb-3 border-b border-gray-100 dark:border-slate-600/50">';
        html += '<img src="' + d.against_image + '" class="w-9 h-9 rounded-full object-cover border-2 border-gray-100 dark:border-slate-600 flex-shrink-0">';
        html += '<div class="min-w-0"><p class="text-sm font-semibold text-gray-900 dark:text-white truncate">' + escHtml(d.against_name) + '</p><p class="text-[11px] text-gray-400 dark:text-slate-500 truncate">' + escHtml(d.against_email) + '</p></div>';
        html += '<span class="ml-auto text-[9px] font-bold px-2 py-0.5 rounded bg-amber-100 dark:bg-amber-900/30 text-amber-600 dark:text-amber-400 flex-shrink-0">Against</span>';
        html += '</div>';
        html += '<p class="text-sm text-gray-700 dark:text-slate-300 leading-relaxed italic">' + (d.resolution ? escHtml(d.resolution) : '<span class="text-gray-400 dark:text-slate-500 not-italic">No response submitted yet.</span>') + '</p></div>';
        html += '</div>';

        // Milestone & Reason
        html += '<div class="grid grid-cols-2 gap-4 mb-4">';
        if (d.milestone_id) {
            html += '<div class="ds-card"><p class="ds-label">Milestone</p><p class="text-sm font-semibold text-gray-900 dark:text-white">' + escHtml(d.milestone_title || 'Milestone #' + d.milestone_id) + '</p><p class="text-xs text-blue-600 dark:text-blue-400 font-bold mt-1">$' + msAmount.toFixed(2) + '</p></div>';
        }
        html += '<div class="ds-card"><p class="ds-label">Dispute Reason</p><span class="inline-flex items-center px-2.5 py-1 rounded-lg text-xs font-bold ' + reasonClr + ' border border-current/10">' + reasonLabel + '</span></div>';
        html += '</div>';

        // Resolution
        if (d.resolution) {
            html += '<div class="ds-card border-emerald-200 dark:border-emerald-800/50 bg-emerald-50/50 dark:bg-emerald-900/10 mb-4">';
            html += '<div class="flex items-center gap-2 mb-2"><svg class="w-4 h-4 text-emerald-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg><p class="ds-label mb-0 text-emerald-600 dark:text-emerald-400">Resolution Summary</p></div>';
            html += '<p class="text-sm text-gray-700 dark:text-slate-300 leading-relaxed mb-2">' + escHtml(d.resolution) + '</p>';
            if (d.resolved_by_name) html += '<p class="text-[11px] text-emerald-600 dark:text-emerald-400 font-semibold"><i data-lucide="shield-user" class="mr-1"></i> Resolved by ' + escHtml(d.resolved_by_name) + ' &middot; ' + timeAgo(d.updated_at) + '</p>';
            html += '</div>';
        }

        // Timeline
        html += '<div class="ds-card mb-4"><p class="ds-label mb-4">Audit Trail</p><div class="relative">' + tlHtml + '</div></div>';

        // Admin actions
        if (isOpen) {
            html += '<div class="ds-card mb-4"><p class="ds-label mb-3">Admin Actions</p><div class="flex flex-wrap gap-2">';
            html += '<button onclick="changeStatus(' + d.id + ',\'investigating\')" class="inline-flex items-center gap-1.5 px-3 py-2 rounded-lg bg-amber-500 hover:bg-amber-600 text-white text-xs font-semibold transition-colors shadow-sm"><i data-lucide="search" class="text-[10px]"></i> Investigate</button>';
            html += '<button onclick="changeStatus(' + d.id + ',\'escalated\')" class="inline-flex items-center gap-1.5 px-3 py-2 rounded-lg bg-purple-500 hover:bg-purple-600 text-white text-xs font-semibold transition-colors shadow-sm"><i data-lucide="arrow-up" class="text-[10px]"></i> Escalate</button>';
            html += '<button onclick="openActionModal(' + d.id + ',\'resolve\')" class="inline-flex items-center gap-1.5 px-3 py-2 rounded-lg bg-emerald-500 hover:bg-emerald-600 text-white text-xs font-semibold transition-colors shadow-sm"><i data-lucide="check" class="text-[10px]"></i> Release to Freelancer</button>';
            html += '<button onclick="openActionModal(' + d.id + ',\'dismiss\')" class="inline-flex items-center gap-1.5 px-3 py-2 rounded-lg bg-red-500 hover:bg-red-600 text-white text-xs font-semibold transition-colors shadow-sm"><i data-lucide="undo" class="text-[10px]"></i> Refund Client</button>';
            html += '<button onclick="openActionModal(' + d.id + ',\'resolve\')" class="inline-flex items-center gap-1.5 px-3 py-2 rounded-lg border border-gray-200 dark:border-slate-600 bg-white dark:bg-slate-700 text-gray-700 dark:text-slate-300 text-xs font-semibold hover:bg-gray-50 dark:hover:bg-slate-600 transition-colors"><i data-lucide="divide" class="text-[10px]"></i> Split Escrow</button>';
            html += '<button onclick="changeStatus(' + d.id + ',\'investigating\')" class="inline-flex items-center gap-1.5 px-3 py-2 rounded-lg border border-gray-200 dark:border-slate-600 bg-white dark:bg-slate-700 text-gray-700 dark:text-slate-300 text-xs font-semibold hover:bg-gray-50 dark:hover:bg-slate-600 transition-colors"><i data-lucide="mail" class="text-[10px]"></i> Request Info</button>';
            html += '</div></div>';
        }

        html += '</div>';

        document.getElementById('detailContent').innerHTML = html;
        document.getElementById('detailContent').setAttribute('data-dispute-id', id);
    }

    function escHtml(s) {
        if (!s) return '';
        var d = document.createElement('div');
        d.appendChild(document.createTextNode(s));
        return d.innerHTML;
    }

    function timeAgo(dateStr) {
        var diff = (Date.now() - new Date(dateStr).getTime()) / 1000;
        if (diff < 60) return 'just now';
        if (diff < 3600) return Math.floor(diff / 60) + 'm ago';
        if (diff < 86400) return Math.floor(diff / 3600) + 'h ago';
        if (diff < 2592000) return Math.floor(diff / 86400) + 'd ago';
        return Math.floor(diff / 2592000) + 'mo ago';
    }

    // ── Quick Status Change ──────────────────────────────────────────
    async function changeStatus(id, status) {
        var labels = { investigating: 'Investigating', escalated: 'Escalated', resolved: 'Resolved', dismissed: 'Dismissed' };
        if (!confirm('Change dispute status to "' + (labels[status] || status) + '"?')) return;
        try {
            var fd = new FormData();
            fd.append('action', 'update_status');
            fd.append('dispute_id', id);
            fd.append('status', status);
            fd.append('csrf_token', CSRF_TOKEN);
            var r = await fetch('/jobhub/api/dispute_api.php', { method: 'POST', body: fd });
            var j = await r.json();
            if (j.success) { location.reload(); } else { alert('Error: ' + j.message); }
        } catch (e) { alert('Network error.'); }
    }

    // ── Action Modal (Resolve / Dismiss) ─────────────────────────────
    function openActionModal(id, type) {
        var isResolve = type === 'resolve';
        document.getElementById('actionDisputeId').value = id;
        document.getElementById('actionType').value = isResolve ? 'resolve' : 'dismiss';
        document.getElementById('actionNote').value = '';
        document.getElementById('actionNote').required = true;

        var header = document.getElementById('actionModalHeader');
        var icon = document.getElementById('actionModalIcon');
        var title = document.getElementById('actionModalTitle');
        var subtitle = document.getElementById('actionModalSubtitle');
        var label = document.getElementById('actionLabel');
        var btn = document.getElementById('actionSubmitBtn');
        var note = document.getElementById('actionNote');

        subtitle.textContent = 'Dispute #' + id;

        if (isResolve) {
            header.className = 'px-6 py-5 border-b border-emerald-100 dark:border-emerald-800/30 bg-emerald-50/50 dark:bg-emerald-900/10';
            icon.className = 'w-10 h-10 rounded-xl bg-emerald-100 dark:bg-emerald-900/30 flex items-center justify-center';
            icon.innerHTML = '<i data-lucide="check" class="text-emerald-600 dark:text-emerald-400"></i>';
            title.textContent = 'Resolve Dispute';
            label.textContent = 'Resolution Note';
            note.placeholder = 'Explain how this dispute was resolved...';
            btn.className = 'flex-1 px-4 py-2.5 rounded-xl bg-emerald-500 hover:bg-emerald-600 text-white text-sm font-semibold shadow-lg shadow-emerald-500/25 transition-colors';
            btn.textContent = 'Confirm Resolution';
        } else {
            header.className = 'px-6 py-5 border-b border-red-100 dark:border-red-800/30 bg-red-50/50 dark:bg-red-900/10';
            icon.className = 'w-10 h-10 rounded-xl bg-red-100 dark:bg-red-900/30 flex items-center justify-center';
            icon.innerHTML = '<i data-lucide="x" class="text-red-600 dark:text-red-400"></i>';
            title.textContent = 'Dismiss Dispute';
            label.textContent = 'Dismissal Reason';
            note.placeholder = 'Explain why this dispute is being dismissed...';
            btn.className = 'flex-1 px-4 py-2.5 rounded-xl bg-red-500 hover:bg-red-600 text-white text-sm font-semibold shadow-lg shadow-red-500/25 transition-colors';
            btn.textContent = 'Confirm Dismissal';
        }

        document.getElementById('actionModal').classList.remove('hidden');
        document.body.style.overflow = 'hidden';
    }

    function closeActionModal() {
        document.getElementById('actionModal').classList.add('hidden');
        document.body.style.overflow = '';
    }

    async function submitAction(e) {
        e.preventDefault();
        var note = document.getElementById('actionNote').value.trim();
        if (note.length < 10) { alert('Note must be at least 10 characters.'); return false; }

        try {
            var fd = new FormData(document.getElementById('actionForm'));
            fd.append('csrf_token', CSRF_TOKEN);
            var r = await fetch('/jobhub/api/dispute_api.php', { method: 'POST', body: fd });
            var j = await r.json();
            if (j.success) { location.reload(); } else { alert('Error: ' + j.message); }
        } catch (err) { alert('Network error.'); }
        return false;
    }

    document.addEventListener('keydown', function(e) {
        if (e.key === 'Escape') closeActionModal();
    });
    </script>

<?php require_once __DIR__ . '/../components/layout_end.php'; ?>
