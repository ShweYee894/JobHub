<?php

/**
 * Freelancer Dispute History
 * Shows all disputes raised by or against the freelancer.
 */
$activePage = 'disputes';
require_once __DIR__ . '/../auth/auth.php';
require_role('freelancer');
require_once __DIR__ . '/../config/db.php';
$userId = $_SESSION['user_id'];

$page = max(1, sanitize_int($_GET['page'] ?? 1));
$perPage = 10;
$offset = ($page - 1) * $perPage;
$statusF = $_GET['status'] ?? '';
$allowedStatuses = ['open', 'investigating', 'resolved', 'dismissed', 'escalated'];
if ($statusF && !in_array($statusF, $allowedStatuses)) {
    $statusF = '';
}

$where = ['d.raised_by = ? OR d.against = ?'];
$params = [$userId, $userId];
$types = 'ii';

if ($statusF !== '') {
    $where[] = 'd.status = ?';
    $params[] = $statusF;
    $types .= 's';
}

$whereSql = 'WHERE ' . implode(' AND ', $where);

$countStmt = $conn->prepare("SELECT COUNT(*) AS cnt FROM dispute_tickets d $whereSql");
$countStmt->bind_param($types, ...$params);
$countStmt->execute();
$totalItems = (int) $countStmt->get_result()->fetch_assoc()['cnt'];
$countStmt->close();
$totalPages = max(1, (int) ceil($totalItems / $perPage));

// Add param for CASE WHEN d.raised_by = ? in SELECT clause (not needed by count query)
array_unshift($params, $userId);
$types = 'i' . $types;

$querySql = "SELECT d.id, d.raised_by, d.reason, d.description, d.status, d.resolution, d.created_at, d.updated_at,
                    d.contract_id, d.milestone_id,
                    c.total_budget, c.status AS contract_status,
                    j.title AS job_title,
                    ru.name AS raised_by_name, ru.profile_image AS raised_by_image,
                    au.name AS against_name, au.profile_image AS against_image,
                    CASE WHEN d.raised_by = ? THEN 'freelancer' ELSE 'client' END AS raised_by_role,
                    m.title AS milestone_title, m.amount AS milestone_amount, m.status AS milestone_status,
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
$params[] = $perPage;
$params[] = $offset;
$types .= 'ii';

$queryStmt = $conn->prepare($querySql);
$queryStmt->bind_param($types, ...$params);
$queryStmt->execute();
$result = $queryStmt->get_result();
$queryStmt->close();

$disputes = [];
while ($row = $result->fetch_assoc()) {
    $disputes[] = $row;
}

$statusColors = [
    'open' => 'bg-red-50 text-red-600 border border-red-200',
    'investigating' => 'bg-amber-50 text-amber-600 border border-amber-200',
    'escalated' => 'bg-purple-50 text-purple-600 border border-purple-200',
    'resolved' => 'bg-emerald-50 text-emerald-600 border border-emerald-200',
    'dismissed' => 'bg-gray-100 text-gray-500 border border-gray-200',
];
$reasonLabels = [
    'non_delivery' => 'Non-Delivery',
    'quality_issue' => 'Quality Issue',
    'scope_dispute' => 'Scope Dispute',
    'payment_issue' => 'Payment Issue',
    'other' => 'Other',
];
$clientReasonLabels = [
    'non_delivery' => 'Work Not Completed',
    'quality_issue' => 'Quality Issue',
    'scope_dispute' => 'Requirements Not Met',
    'payment_issue' => 'Payment Issue',
    'other' => 'Other',
];
$freelancerReasonLabels = [
    'non_delivery' => 'Work Rejected Unfairly',
    'quality_issue' => 'Additional Work Requested',
    'scope_dispute' => 'Scope Changed',
    'payment_issue' => 'Payment Not Released',
    'other' => 'Other',
];

$pageTitle = 'My Disputes';
$pageSubtitle = $totalItems . ' dispute' . ($totalItems !== 1 ? 's' : '');
$activePage = 'disputes';
$uStmt = $conn->prepare('SELECT name, profile_image FROM users WHERE id = ?');
$uStmt->bind_param('i', $userId);
$uStmt->execute();
$_uRow = $uStmt->get_result()->fetch_assoc();
$uStmt->close();
$user = ['name' => $_uRow['name'] ?? 'Freelancer', 'profile_image' => $_uRow['profile_image'] ?? null];
$unreadCount = get_unread_message_count($userId, 'freelancer');
require_once __DIR__ . '/../components/freelancer_header.php';
?>
<?php display_flash('success'); ?>
<?php display_flash('error'); ?>

<div class="space-y-6 max-w-7xl mx-auto px-4 sm:px-6 py-8">
    <!-- Header -->
    <div class="mb-6">
        <h1 class="text-xl font-bold text-gray-900 dark:text-white">My Disputes</h1>
        <p class="text-sm text-gray-500 dark:text-slate-400 mt-1">View all disputes you've raised or been involved in</p>
    </div>

    <!-- Status Filter Tabs -->
    <div class="flex flex-wrap gap-2 mb-6">
        <a href="disputes.php" class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg text-xs font-semibold transition-all <?= $statusF === '' ? 'bg-gray-900 text-white' : 'bg-gray-100 text-gray-600 hover:bg-gray-200 dark:bg-slate-700 dark:text-slate-300 dark:hover:bg-slate-600' ?>">
            All (<?= $totalItems ?>)
        </a>
        <?php
        $pillCounts = [];
        $tmpStmt = $conn->prepare('SELECT status, COUNT(*) AS cnt FROM dispute_tickets WHERE raised_by = ? OR against = ? GROUP BY status');
        $tmpStmt->bind_param('ii', $userId, $userId);
        $tmpStmt->execute();
        $tmpResult = $tmpStmt->get_result();
        while ($tmpRow = $tmpResult->fetch_assoc()) {
            $pillCounts[$tmpRow['status']] = (int) $tmpRow['cnt'];
        }
        $tmpStmt->close();
        foreach (['open', 'investigating', 'resolved'] as $s):
            $cnt = $pillCounts[$s] ?? 0;
            ?>
        <a href="disputes.php?status=<?= $s ?>" class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg text-xs font-semibold transition-all <?= $statusF === $s ? 'bg-gray-900 text-white' : 'bg-gray-100 text-gray-600 hover:bg-gray-200 dark:bg-slate-700 dark:text-slate-300 dark:hover:bg-slate-600' ?>">
            <?= ucfirst($s) ?> (<?= $cnt ?>)
        </a>
        <?php endforeach; ?>
    </div>

    <!-- Disputes List -->
    <?php if (!empty($disputes)): ?>
        <div class="space-y-4">
            <?php foreach ($disputes as $d): ?>
                <?php
                $did = (int) $d['id'];
                $sClr = $statusColors[$d['status']] ?? '';
                $isMine = ((int) $d['raised_by'] === $userId);
                ?>
                <div class="bg-white dark:bg-slate-800 border border-gray-200 dark:border-slate-700 rounded-xl shadow-sm overflow-hidden fade-in">
                    <!-- Header -->
                    <div class="px-6 py-4 border-b border-gray-100 dark:border-slate-700 flex flex-col sm:flex-row sm:items-center justify-between gap-3">
                        <div class="flex items-center gap-3 min-w-0">
                            <span class="text-sm font-bold text-gray-900 dark:text-white">#<?= $did ?></span>
                            <span class="inline-flex items-center gap-1 px-2.5 py-0.5 rounded text-[11px] font-semibold <?= $sClr ?>">
                                <?= ucfirst($d['status']) ?>
                            </span>
                            <?php if ($isMine): ?>
                                <span class="text-[10px] font-semibold px-2 py-0.5 rounded bg-blue-100 text-blue-600 dark:bg-blue-900/30 dark:text-blue-400">You Raised</span>
                            <?php else: ?>
                                <span class="text-[10px] font-semibold px-2 py-0.5 rounded bg-amber-100 text-amber-600 dark:bg-amber-900/30 dark:text-amber-400">Against You</span>
                            <?php endif; ?>
                        </div>
                        <span class="text-xs text-gray-400 dark:text-slate-500"><?= date('M d, Y \a\t g:i A', strtotime($d['created_at'])) ?></span>
                    </div>

                    <div class="p-6">
                        <!-- Job & Milestone Info -->
                        <div class="flex flex-wrap items-center gap-4 text-sm text-gray-500 dark:text-slate-400 mb-4">
                            <span class="flex items-center gap-1.5">
                                <i data-lucide="briefcase" class="text-[11px] text-gray-400"></i>
                                <?= sanitize_string($d['job_title']) ?>
                            </span>
                            <?php if (!empty($d['milestone_title'])): ?>
                                <span class="flex items-center gap-1.5">
                                    <i data-lucide="list-checks" class="text-[11px] text-violet-500"></i>
                                    <?= sanitize_string($d['milestone_title']) ?>
                                    <span class="font-semibold text-gray-700 dark:text-slate-300">(<?= format_currency((float) $d['milestone_amount']) ?>)</span>
                                </span>
                            <?php endif; ?>
                            <a href="contract_detail.php?id=<?= (int) $d['contract_id'] ?>" class="flex items-center gap-1.5 text-blue-600 hover:text-blue-700 dark:text-blue-400 dark:hover:text-blue-300 transition-colors">
                                <i data-lucide="link" class="text-[11px]"></i>
                                Contract #<?= (int) $d['contract_id'] ?>
                            </a>
                        </div>

                        <!-- Reason & Description -->
                        <div class="bg-gray-50/80 dark:bg-slate-700/20 rounded-xl p-5 border border-gray-100 dark:border-slate-600/50 mb-4">
                            <div class="flex items-center gap-2 mb-3">
                                <span class="text-[10px] font-bold text-gray-400 dark:text-slate-500 uppercase tracking-wider">Reason</span>
                                <span class="inline-flex items-center px-2 py-0.5 rounded-full text-[10px] font-semibold bg-gray-100 dark:bg-slate-600 text-gray-700 dark:text-gray-300">
                                    <?php
                                    $reasonKey = $d['reason'];
                                    if ($d['raised_by_role'] === 'client') {
                                        echo $clientReasonLabels[$reasonKey] ?? $reasonLabels[$reasonKey] ?? $reasonKey;
                                    } else {
                                        echo $freelancerReasonLabels[$reasonKey] ?? $reasonLabels[$reasonKey] ?? $reasonKey;
                                    }
                                    ?>
                                </span>
                            </div>
                            <p class="text-sm text-gray-600 dark:text-slate-300 leading-relaxed"><?= nl2br(sanitize_string($d['description'])) ?></p>
                        </div>

                        <!-- Milestone Status (if applicable) -->
                        <?php if (!empty($d['milestone_title'])): ?>
                            <div class="flex items-center gap-2 mb-4 text-xs">
                                <span class="text-gray-400 dark:text-slate-500">Milestone Status:</span>
                                <span class="inline-flex items-center px-2 py-0.5 rounded-full text-[10px] font-semibold <?= $d['milestone_status'] === 'disputed' ? 'bg-rose-50 text-rose-600 border border-rose-200' : 'bg-slate-100 text-slate-600 border border-slate-200' ?>">
                                    <?= ucfirst(str_replace('_', ' ', $d['milestone_status'])) ?>
                                </span>
                            </div>
                        <?php endif; ?>

                        <!-- Admin Resolution (if resolved/dismissed) -->
                        <?php if (!empty($d['resolution'])): ?>
                            <div class="bg-emerald-50/50 dark:bg-emerald-900/10 rounded-xl p-5 border border-emerald-200 dark:border-emerald-800/30">
                                <div class="flex items-center gap-2 mb-3">
                                    <div class="w-8 h-8 rounded-lg bg-emerald-100 dark:bg-emerald-900/30 flex items-center justify-center">
                                        <i data-lucide="shield-check" class="text-emerald-500"></i>
                                    </div>
                                    <div>
                                        <span class="text-[10px] font-bold text-emerald-600 dark:text-emerald-400 uppercase tracking-wider">Admin Decision</span>
                                        <?php if (!empty($d['resolved_by_name'])): ?>
                                            <span class="text-[10px] text-emerald-500 dark:text-emerald-400 ml-1">by <?= sanitize_string($d['resolved_by_name']) ?></span>
                                        <?php endif; ?>
                                    </div>
                                </div>
                                <p class="text-sm text-gray-600 dark:text-slate-300 leading-relaxed"><?= nl2br(sanitize_string($d['resolution'])) ?></p>
                                <p class="text-[10px] text-gray-400 dark:text-slate-500 mt-2"><?= date('M d, Y \a\t g:i A', strtotime($d['updated_at'])) ?></p>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>

        <!-- Pagination -->
        <?php if ($totalPages > 1): ?>
            <div class="flex items-center justify-center gap-2 mt-8">
                <?php if ($page > 1): ?>
                    <a href="disputes.php?page=<?= $page - 1 ?><?= $statusF ? '&status=' . $statusF : '' ?>" class="px-3 py-2 rounded-lg border border-gray-200 dark:border-slate-600 text-sm font-medium text-gray-600 dark:text-slate-300 hover:bg-gray-50 dark:hover:bg-slate-700 transition-colors">
                        <i data-lucide="chevron-left" class="w-4 h-4"></i>
                    </a>
                <?php endif; ?>
                <span class="text-sm text-gray-500 dark:text-slate-400">Page <?= $page ?> of <?= $totalPages ?></span>
                <?php if ($page < $totalPages): ?>
                    <a href="disputes.php?page=<?= $page + 1 ?><?= $statusF ? '&status=' . $statusF : '' ?>" class="px-3 py-2 rounded-lg border border-gray-200 dark:border-slate-600 text-sm font-medium text-gray-600 dark:text-slate-300 hover:bg-gray-50 dark:hover:bg-slate-700 transition-colors">
                        <i data-lucide="chevron-right" class="w-4 h-4"></i>
                    </a>
                <?php endif; ?>
            </div>
        <?php endif; ?>
    <?php else: ?>
        <div class="bg-white dark:bg-slate-800 border border-gray-200 dark:border-slate-700 rounded-xl shadow-sm p-12 text-center">
            <div class="w-16 h-16 rounded-2xl bg-gray-100 dark:bg-slate-700 flex items-center justify-center mx-auto mb-4">
                <i data-lucide="shield" class="w-5 h-5 text-gray-300 dark:text-slate-500"></i>
            </div>
            <p class="text-gray-500 dark:text-slate-400 text-sm font-medium mb-1">No disputes found</p>
            <p class="text-gray-400 dark:text-slate-500 text-xs">Disputes will appear here when you or a client raises one</p>
        </div>
    <?php endif; ?>
    <?php $conn->close(); ?>
</div>

<script>
lucide.createIcons();
</script>
<?php require_once __DIR__ . '/../components/freelancer_footer.php'; ?>
