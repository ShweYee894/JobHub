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
             j.title AS job_title,
             u.name AS client_name, u.profile_image AS client_image
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

$statusColors = [
    'active' => 'bg-blue-50 text-blue-600 border border-blue-200',
    'completed' => 'bg-green-50 text-green-600 border border-green-200',
    'disputed' => 'bg-red-50 text-red-500 border border-red-200',
    'terminated' => 'bg-gray-100 text-gray-500 border border-gray-200',
];

$statusIcons = [
    'active' => 'fa-spinner fa-spin',
    'completed' => 'fa-check-circle',
    'disputed' => 'fa-exclamation-triangle',
    'terminated' => 'fa-ban',
];

function buildQueryString(array $overrides = []): string
{
    $params = array_merge($_GET, $overrides);
    return http_build_query($params);
}

$navItems = [
    ['key' => 'dashboard', 'label' => 'Dashboard', 'url' => 'dashboard.php', 'icon' => 'fa-th-large'],
    ['key' => 'profile', 'label' => 'Profile', 'url' => 'profile.php', 'icon' => 'fa-user'],
    ['key' => 'browse_jobs', 'label' => 'Browse Jobs', 'url' => 'browse_jobs.php', 'icon' => 'fa-search'],
    ['key' => 'proposals', 'label' => 'Proposals', 'url' => 'proposals.php', 'icon' => 'fa-file-alt'],
    ['key' => 'contracts', 'label' => 'Contracts', 'url' => 'contracts.php', 'icon' => 'fa-handshake'],
    ['key' => 'messages', 'label' => 'Messages', 'url' => 'messages.php', 'icon' => 'fa-comment-dots'],
    ['key' => 'earnings', 'label' => 'Earnings', 'url' => 'earnings.php', 'icon' => 'fa-wallet'],
];
$pageTitle = 'Contracts';
$pageSubtitle = 'View and manage your contracts';
$activePage = 'contracts';
$user = ['name' => $user['name'] ?? 'Freelancer', 'profile_image' => $user['profile_image'] ?? null];
$unreadCount = get_unread_message_count($userId, 'freelancer');
$profileLink = 'profile.php';
require_once __DIR__ . '/../components/layout_start.php';
?>
    <?php display_flash('success') ?>
    <?php display_flash('error') ?>

            <!-- STATUS FILTER -->
            <div class="bg-white rounded-2xl p-5 border border-gray-100 shadow-sm fade-in">
                <div class="flex flex-wrap gap-2">
                    <?php
                    $filters = [
                        'all' => ['All', 'fa-layer-group', 'text-gray-600', 'bg-gray-100'],
                        'active' => ['Active', 'fa-check-circle', 'text-blue-600', 'bg-blue-50'],
                        'completed' => ['Completed', 'fa-trophy', 'text-green-600', 'bg-green-50'],
                        'disputed' => ['Disputed', 'fa-exclamation-triangle', 'text-red-500', 'bg-red-50'],
                        'terminated' => ['Terminated', 'fa-ban', 'text-gray-500', 'bg-gray-100'],
                    ];
                    foreach ($filters as $key => $label):
                        $isActive = $statusFilter === $key;
                        ?>
                    <a href="?status=<?= $key ?>"
                       class="inline-flex items-center gap-1.5 px-4 py-2 rounded-xl text-xs font-semibold transition-all <?= $isActive ? $label[3] . ' ' . $label[2] . ' border border-current/20' : 'text-gray-500 bg-gray-50 hover:bg-gray-100 border border-transparent' ?>">
                        <i class="fas <?= $label[1] ?> text-[10px]"></i> <?= $label[0] ?>
                    </a>
                    <?php endforeach; ?>
                </div>
            </div>

            <!-- CONTRACTS LIST -->
            <?php if ($contractsResult->num_rows > 0): ?>
            <div class="space-y-4">
                <?php while ($c = $contractsResult->fetch_assoc()): ?>
                <a href="contract_detail.php?id=<?= (int) $c['id'] ?>"
                   class="block bg-white rounded-2xl border border-gray-100 shadow-sm hover:shadow-md hover:border-blue-200 transition-all fade-in">
                    <div class="p-6">
                        <div class="flex flex-col lg:flex-row lg:items-center gap-4">
                            <div class="flex items-center gap-4 flex-1 min-w-0">
                                <div class="w-12 h-12 rounded-xl bg-gradient-to-br from-blue-500 to-cyan-500 flex items-center justify-center flex-shrink-0">
                                    <i class="fas fa-handshake text-white"></i>
                                </div>
                                <div class="flex-1 min-w-0">
                                    <div class="flex flex-wrap items-center gap-2 mb-1">
                                        <h3 class="text-base font-bold text-gray-900 truncate"><?= sanitize_string($c['job_title']) ?></h3>
                                        <span class="inline-block px-2.5 py-1 rounded-lg text-[11px] font-semibold <?= $statusColors[$c['status']] ?? 'bg-gray-100 text-gray-500 border border-gray-200' ?>">
                                            <i class="fas <?= $statusIcons[$c['status']] ?? 'fa-circle' ?> mr-1 text-[9px]"></i>
                                            <?= ucfirst(sanitize_string($c['status'])) ?>
                                        </span>
                                    </div>
                                    <div class="flex flex-wrap items-center gap-4 text-xs text-gray-400">
                                        <span class="flex items-center gap-1.5">
                                            <i class="fas fa-user text-blue-400"></i>
                                            <span class="font-medium text-gray-600"><?= sanitize_string($c['client_name']) ?></span>
                                        </span>
                                        <span class="flex items-center gap-1.5">
                                            <i class="fas fa-file-contract text-violet-400"></i>
                                            <?= ucfirst(sanitize_string($c['contract_type'])) ?>
                                        </span>
                                        <span class="flex items-center gap-1.5">
                                            <i class="fas fa-calendar text-gray-400"></i>
                                            <?= time_ago($c['created_at']) ?>
                                        </span>
                                    </div>
                                </div>
                            </div>
                            <div class="flex items-center gap-4 lg:gap-6 flex-shrink-0">
                                <div class="text-right">
                                    <p class="text-lg font-black text-gray-900"><?= format_currency((float) $c['total_budget']) ?></p>
                                    <p class="text-[11px] text-gray-400">Total Budget</p>
                                </div>
                                <i class="fas fa-chevron-right text-gray-300"></i>
                            </div>
                        </div>
                    </div>
                </a>
                <?php endwhile; ?>
            </div>

            <!-- PAGINATION -->
            <?php
            $baseUrl = '?status=' . urlencode($statusFilter);
            render_pagination($pagination, $baseUrl);
            ?>

            <?php else: ?>
            <div class="bg-white rounded-2xl border border-gray-100 shadow-sm fade-in">
                <div class="text-center py-16 px-6">
                    <div class="w-24 h-24 rounded-3xl bg-gradient-to-br from-blue-50 to-cyan-50 flex items-center justify-center mx-auto mb-6 border border-blue-100">
                        <i class="fas fa-handshake text-4xl text-blue-300"></i>
                    </div>
                    <h3 class="text-xl font-bold text-gray-900 mb-2">No contracts yet</h3>
                    <p class="text-sm text-gray-400 mb-6 max-w-md mx-auto">
                        <?= $statusFilter !== 'all' ? 'No contracts match this filter.' : 'Submit proposals to jobs and get hired to start your first contract.' ?>
                    </p>
                    <a href="browse_jobs.php" class="btn-grad inline-flex items-center gap-2 text-white font-bold px-6 py-3 rounded-xl text-sm">
                        <i class="fas fa-search text-xs"></i> Browse Jobs
                    </a>
                </div>
            </div>
            <?php endif; ?>
<?php $conn->close(); ?>
<?php require_once __DIR__ . '/../components/layout_end.php'; ?>
