<?php
$page_title = 'Contracts';
require_once __DIR__ . '/../auth/auth.php';
require_role('freelancer');
require_once __DIR__ . '/../config/db.php';

$userId = $_SESSION['user_id'];

$stmt = $conn->prepare('SELECT name, profile_image FROM users WHERE id = ?');
$stmt->bind_param('i', $userId);
$stmt->execute();
$user = $stmt->get_result()->fetch_assoc();
$stmt->close();

$statusFilter = $_GET['status'] ?? 'all';
$page = max(1, intval($_GET['page'] ?? 1));
$perPage = 10;

$allowedStatuses = ['all', 'active', 'completed', 'disputed', 'terminated'];
if (!in_array($statusFilter, $allowedStatuses))
    $statusFilter = 'all';

$where = 'WHERE c.freelancer_id = ?';
$params = [$userId];
$types = 'i';

if ($statusFilter !== 'all') {
    $where .= ' AND c.status = ?';
    $params[] = $statusFilter;
    $types .= 's';
}

$countSql = "SELECT COUNT(*) AS total FROM contracts c $where";
$stmt = $conn->prepare($countSql);
$stmt->bind_param($types, ...$params);
$stmt->execute();
$totalFiltered = (int) $stmt->get_result()->fetch_assoc()['total'];
$stmt->close();

$pagination = paginate($totalFiltered, $perPage, $page);

$querySql = "SELECT c.id, c.contract_type, c.total_budget, c.status, c.created_at,
             j.title AS job_title, j.id AS job_id,
             u.name AS client_name, u.profile_image AS client_image, u.id AS client_id
             FROM contracts c
             JOIN jobs j ON c.job_id = j.id
             JOIN users u ON c.client_id = u.id
             $where
             ORDER BY c.created_at DESC
             LIMIT ? OFFSET ?";

$finalTypes = $types . 'ii';
$finalParams = array_merge($params, [$perPage, $pagination['offset']]);

$stmt = $conn->prepare($querySql);
$stmt->bind_param($finalTypes, ...$finalParams);
$stmt->execute();
$contractsResult = $stmt->get_result();
$stmt->close();

$contractIds = [];
$contractsData = [];
while ($row = $contractsResult->fetch_assoc()) {
    $contractIds[] = (int) $row['id'];
    $contractsData[$row['id']] = $row;
}

$milestoneData = [];
$paymentData = [];
$clientRatings = [];

if (!empty($contractIds)) {
    $inClause = implode(',', array_fill(0, count($contractIds), '?'));

    $mStmt = $conn->prepare("SELECT contract_id, COUNT(*) AS total_milestones,
        SUM(CASE WHEN status IN ('released','submitted') THEN 1 ELSE 0 END) AS completed_milestones,
        SUM(CASE WHEN status = 'released' THEN amount ELSE 0 END) AS released_amount,
        SUM(CASE WHEN status = 'funded_in_escrow' THEN amount ELSE 0 END) AS escrow_amount
        FROM milestones WHERE contract_id IN ($inClause) GROUP BY contract_id");
    $mStmt->bind_param(str_repeat('i', count($contractIds)), ...$contractIds);
    $mStmt->execute();
    $mResult = $mStmt->get_result();
    while ($mr = $mResult->fetch_assoc()) {
        $milestoneData[$mr['contract_id']] = $mr;
    }
    $mStmt->close();

    $pStmt = $conn->prepare("SELECT m.contract_id,
        SUM(CASE WHEN p.status = 'completed' THEN p.freelancer_net ELSE 0 END) AS paid_to_date
        FROM payments p JOIN milestones m ON p.milestone_id = m.id
        WHERE m.contract_id IN ($inClause) GROUP BY m.contract_id");
    $pStmt->bind_param(str_repeat('i', count($contractIds)), ...$contractIds);
    $pStmt->execute();
    $pResult = $pStmt->get_result();
    while ($pr = $pResult->fetch_assoc()) {
        $paymentData[$pr['contract_id']] = $pr;
    }
    $pStmt->close();

    $clientIds = array_unique(array_column($contractsData, 'client_id'));
    if (!empty($clientIds)) {
        $cInClause = implode(',', array_fill(0, count($clientIds), '?'));
        $rStmt = $conn->prepare("SELECT reviewee_id, ROUND(AVG(rating),1) AS avg_rating
            FROM reviews WHERE reviewee_id IN ($cInClause) GROUP BY reviewee_id");
        $rStmt->bind_param(str_repeat('i', count($clientIds)), ...$clientIds);
        $rStmt->execute();
        $rResult = $rStmt->get_result();
        while ($rr = $rResult->fetch_assoc()) {
            $clientRatings[$rr['reviewee_id']] = $rr['avg_rating'];
        }
        $rStmt->close();
    }
}

$statusColors = [
    'active' => 'bg-emerald-50 text-emerald-700',
    'completed' => 'bg-emerald-50 text-emerald-700',
    'disputed' => 'bg-rose-50 text-rose-700',
    'terminated' => 'bg-slate-100 text-slate-600',
];

$statusIcons = [
    'active' => 'circle-dot',
    'completed' => 'circle-check',
    'disputed' => 'triangle-alert',
    'terminated' => 'circle-x',
];

function buildQueryString(array $overrides = []): string
{
    $params = array_merge($_GET, $overrides);
    return http_build_query($params);
}

$pageTitle = 'Contracts';
$pageSubtitle = 'View and manage your contracts';
$activePage = 'contracts';
$user = ['name' => $user['name'] ?? 'Freelancer', 'profile_image' => $user['profile_image'] ?? null];
$unreadCount = get_unread_message_count($userId, 'freelancer');
require_once __DIR__ . '/../components/freelancer_header.php';
?>
<?php display_flash('success') ?>
<?php display_flash('error') ?>

<div class="space-y-6 max-w-7xl mx-auto px-4 sm:px-6 py-8">
    <!-- STATUS FILTER -->
    <div class="fade-in">
        <div class="flex items-center gap-1 border-b border-gray-200 dark:border-slate-700">
            <?php
            $filters = [
                'all' => ['All', 'layers'],
                'active' => ['Active', 'circle-dot'],
                'completed' => ['Completed', 'circle-check'],
                'disputed' => ['Disputed', 'triangle-alert'],
                'terminated' => ['Terminated', 'ban'],
            ];
            foreach ($filters as $key => $label):
                $isActive = $statusFilter === $key;
            ?>
                <a href="?status=<?= $key ?>"
                    class="relative px-5 py-3 text-sm font-medium transition-colors <?= $isActive ? 'text-[#4338CA]' : 'text-gray-500 hover:text-gray-800 dark:text-slate-400 dark:hover:text-white' ?>">
                    <span class="flex items-center gap-1.5">
                        <i data-lucide="<?= $label[1] ?>" class="w-3 h-3"></i> <?= $label[0] ?>
                    </span>
                    <?php if ($isActive): ?>
                        <span class="absolute bottom-0 left-0 right-0 h-[2px] bg-[#4338CA] rounded-t-full"></span>
                    <?php endif; ?>
                </a>
            <?php endforeach; ?>
        </div>
    </div>

    <!-- CONTRACTS LIST -->
    <?php if (!empty($contractsData)): ?>
        <div class="space-y-4">
            <?php foreach ($contractsData as $c):
                $cId = (int) $c['id'];
                $md = $milestoneData[$cId] ?? ['total_milestones' => 0, 'completed_milestones' => 0, 'released_amount' => 0, 'escrow_amount' => 0];
                $pd = $paymentData[$cId] ?? ['paid_to_date' => 0];
                $totalM = (int) $md['total_milestones'];
                $completedM = (int) $md['completed_milestones'];
                $progress = $totalM > 0 ? round(($completedM / $totalM) * 100) : 0;
                $inEscrow = (float) ($c['total_budget'] - $md['released_amount'] - $pd['paid_to_date']);
                $inEscrow = max(0, $inEscrow);
                $rating = $clientRatings[$c['client_id']] ?? null;
                $clientAvatar = get_profile_image($c['client_image'] ?? null);
                $statusLabel = ucfirst(htmlspecialchars($c['status']));
            ?>
                <div class="bg-white rounded-xl border border-[#EAECF0] shadow-sm hover:shadow-md transition-all duration-300 fade-in overflow-hidden">
                    <!-- Top Section -->
                    <div class="p-5 pb-4">
                        <div class="flex items-start gap-4">
                            <img src="<?= htmlspecialchars($clientAvatar) ?>" alt="<?= sanitize_string($c['client_name']) ?>"
                                class="w-12 h-12 rounded-full object-cover flex-shrink-0 ring-2 ring-gray-100">
                            <div class="flex-1 min-w-0">
                                <div class="flex items-center gap-2.5 flex-wrap mb-1">
                                    <h3 class="text-[15px] font-semibold text-gray-900 truncate"><?= sanitize_string($c['job_title']) ?></h3>
                                    <span class="inline-flex items-center gap-1 px-2.5 py-1 rounded-full text-[10px] font-semibold tracking-wide uppercase <?= $statusColors[$c['status']] ?? 'bg-slate-100 text-slate-600' ?>">
                                        <span class="w-1.5 h-1.5 rounded-full bg-current"></span>
                                        <?= $statusLabel ?>
                                    </span>
                                </div>
                                <p class="text-[13px] text-gray-500">
                                    Client: <?= sanitize_string($c['client_name']) ?>
                                    <?php if ($rating): ?>
                                        <span class="text-amber-500">★ <?= number_format((float) $rating, 1) ?></span>
                                    <?php endif; ?>
                                    <span class="text-gray-300 mx-1">•</span>
                                    <?= ucfirst(htmlspecialchars($c['contract_type'])) ?>
                                    <span class="text-gray-300 mx-1">•</span>
                                    Started <?= time_ago($c['created_at']) ?>
                                </p>
                            </div>
                            <div class="relative">
                                <button onclick="event.preventDefault();event.stopPropagation();document.getElementById('menu-<?= $cId ?>').classList.toggle('hidden')" class="w-8 h-8 flex items-center justify-center rounded-lg hover:bg-gray-100 transition-colors text-gray-400">
                                    <i data-lucide="ellipsis-vertical" class="w-4 h-4"></i>
                                </button>
                                <div id="menu-<?= $cId ?>" class="hidden absolute right-0 top-10 bg-white rounded-xl shadow-lg border border-gray-100 py-1.5 w-44 z-50">
                                    <a href="contract_detail.php?id=<?= $cId ?>" class="flex items-center gap-2.5 px-4 py-2.5 text-sm text-gray-700 hover:bg-gray-50 transition-colors">
                                        <i data-lucide="eye" class="w-4 h-4 text-gray-400"></i> View Details
                                    </a>
                                    <a href="messages.php?contract_id=<?= $cId ?>" class="flex items-center gap-2.5 px-4 py-2.5 text-sm text-gray-700 hover:bg-gray-50 transition-colors">
                                        <i data-lucide="message-circle" class="w-4 h-4 text-gray-400"></i> Message
                                    </a>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Milestone Progress -->
                    <?php if ($totalM > 0): ?>
                    <div class="px-5 pb-4">
                        <div class="bg-gray-50 rounded-xl p-4">
                            <div class="flex items-center justify-between mb-2">
                                <p class="text-[13px] font-medium text-gray-700">
                                    Milestone <?= $completedM ?> of <?= $totalM ?>:
                                    <?php if ($completedM == $totalM): ?>
                                        <span class="text-emerald-600">Completed</span>
                                    <?php else: ?>
                                        <span class="text-amber-600">In Progress</span>
                                    <?php endif; ?>
                                </p>
                                <span class="text-[13px] font-semibold text-gray-900"><?= $progress ?>%</span>
                            </div>
                            <div class="w-full h-2 bg-gray-200 rounded-full overflow-hidden">
                                <div class="h-full rounded-full transition-all duration-500 <?= $progress == 100 ? 'bg-emerald-500' : 'bg-emerald-400' ?>"
                                    style="width: <?= $progress ?>%"></div>
                            </div>
                        </div>
                    </div>
                    <?php endif; ?>

                    <!-- Bottom Section: Financials & Actions -->
                    <div class="px-5 py-4 border-t border-[#EAECF0] bg-gray-50/50 flex flex-col sm:flex-row sm:items-center justify-between gap-4">
                        <div class="flex items-center gap-6 sm:gap-8">
                            <div>
                                <p class="text-[10px] font-semibold text-gray-400 uppercase tracking-wider mb-0.5">Paid to Date</p>
                                <p class="text-[15px] font-bold text-gray-900"><?= format_currency((float) $pd['paid_to_date']) ?></p>
                            </div>
                            <div>
                                <p class="text-[10px] font-semibold text-gray-400 uppercase tracking-wider mb-0.5">In Escrow</p>
                                <p class="text-[15px] font-bold text-gray-900"><?= format_currency($inEscrow) ?></p>
                            </div>
                            <div>
                                <p class="text-[10px] font-semibold text-gray-400 uppercase tracking-wider mb-0.5">Total Budget</p>
                                <p class="text-[15px] font-bold text-gray-900"><?= format_currency((float) $c['total_budget']) ?></p>
                            </div>
                        </div>
                        <div class="flex items-center gap-2.5 flex-shrink-0">
                            <a href="messages.php?contract_id=<?= $cId ?>"
                                class="inline-flex items-center gap-1.5 px-4 py-2.5 rounded-xl border border-gray-200 text-sm font-medium text-gray-700 hover:bg-gray-100 transition-colors">
                                <i data-lucide="message-circle" class="w-4 h-4"></i> Message
                            </a>
                            <a href="contract_detail.php?id=<?= $cId ?>&action=submit"
                                class="inline-flex items-center gap-1.5 px-5 py-2.5 rounded-xl bg-[#4338CA] hover:bg-indigo-700 text-white text-sm font-semibold shadow-sm transition-all duration-200 active:scale-95">
                                Submit Work
                            </a>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>

        <!-- PAGINATION -->
        <?php
        $baseUrl = '?status=' . urlencode($statusFilter);
        render_pagination($pagination, $baseUrl);
        ?>

    <?php else: ?>
        <div class="bg-white dark:bg-slate-800 rounded-2xl shadow-[0_1px_3px_rgba(0,0,0,.04),0_8px_24px_rgba(0,0,0,.03)] fade-in">
            <div class="text-center py-20 px-6">
                <div class="w-20 h-20 rounded-2xl bg-indigo-50 dark:bg-indigo-900/20 flex items-center justify-center mx-auto mb-6">
                    <i data-lucide="handshake" class="w-8 h-8 text-indigo-300 dark:text-indigo-500/50"></i>
                </div>
                <h3 class="text-lg font-semibold text-gray-900 dark:text-white mb-2">No contracts yet</h3>
                <p class="text-sm text-gray-400 dark:text-slate-500 mb-8 max-w-sm mx-auto leading-relaxed">
                    <?= $statusFilter !== 'all' ? 'No contracts match this filter.' : 'Submit proposals to jobs and get hired to start your first contract.' ?>
                </p>
                <a href="browse_jobs.php" class="btn-grad inline-flex items-center gap-2 text-white font-semibold px-6 py-3 rounded-xl text-sm">
                    <i data-lucide="search" class="w-4 h-4"></i> Browse Jobs
                </a>
            </div>
        </div>
    <?php endif; ?>
</div>
<?php $conn->close(); ?>
<script>
document.addEventListener('click', function(e) {
    document.querySelectorAll('[id^="menu-"]').forEach(function(menu) {
        if (!menu.contains(e.target) && !e.target.closest('[onclick*="menu-"]')) {
            menu.classList.add('hidden');
        }
    });
});
</script>
<?php require_once __DIR__ . '/../components/freelancer_footer.php'; ?>