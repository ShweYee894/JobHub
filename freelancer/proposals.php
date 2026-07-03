<?php
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
$page = max(1, sanitize_int($_GET['page'] ?? 1));
$perPage = 10;

$allowedStatuses = ['all', 'pending', 'accepted', 'rejected'];
if (!in_array($statusFilter, $allowedStatuses))
    $statusFilter = 'all';

$where = 'WHERE p.freelancer_id = ?';
$params = [$userId];
$types = 'i';

if ($statusFilter !== 'all') {
    $where .= ' AND p.status = ?';
    $params[] = $statusFilter;
    $types .= 's';
}

$countSql = "SELECT COUNT(*) AS total FROM proposals p $where";
$stmt = $conn->prepare($countSql);
$stmt->bind_param($types, ...$params);
$stmt->execute();
$totalFiltered = (int) $stmt->get_result()->fetch_assoc()['total'];
$stmt->close();

$pagination = paginate($totalFiltered, $perPage, $page);

$querySql = "SELECT p.id, p.proposal_text, p.amount, p.status, p.created_at,
             j.id AS job_id, j.title AS job_title, j.budget AS job_budget, j.status AS job_status,
             u.name AS client_name
             FROM proposals p
             JOIN jobs j ON p.job_id = j.id
             JOIN users u ON j.client_id = u.id
             $where
             ORDER BY p.created_at DESC
             LIMIT ? OFFSET ?";

$finalTypes = $types . 'ii';
$finalParams = array_merge($params, [$perPage, $pagination['offset']]);

$stmt = $conn->prepare($querySql);
$stmt->bind_param($finalTypes, ...$finalParams);
$stmt->execute();
$proposals = $stmt->get_result();
$stmt->close();

$conn->close();

$proposalColors = [
    'pending' => 'bg-amber-50 text-amber-600 border border-amber-200',
    'accepted' => 'bg-emerald-50 text-emerald-600 border border-emerald-200',
    'rejected' => 'bg-red-50 text-red-500 border border-red-200',
];

function buildQueryString(array $overrides = []): string
{
    $params = array_merge($_GET, $overrides);
    return http_build_query($params);
}

$page_title = 'My Proposals – FreelanceHub';

$navItems = [
    ['key' => 'dashboard', 'label' => 'Dashboard', 'url' => 'dashboard.php', 'icon' => 'fa-th-large'],
    ['key' => 'profile', 'label' => 'Profile', 'url' => 'profile.php', 'icon' => 'fa-user'],
    ['key' => 'browse_jobs', 'label' => 'Browse Jobs', 'url' => 'browse_jobs.php', 'icon' => 'fa-search'],
    ['key' => 'proposals', 'label' => 'Proposals', 'url' => 'proposals.php', 'icon' => 'fa-file-alt'],
    ['key' => 'contracts', 'label' => 'Contracts', 'url' => 'contracts.php', 'icon' => 'fa-handshake'],
    ['key' => 'messages', 'label' => 'Messages', 'url' => 'messages.php', 'icon' => 'fa-comment-dots'],
    ['key' => 'earnings', 'label' => 'Earnings', 'url' => 'earnings.php', 'icon' => 'fa-wallet'],
];
$pageTitle = 'My Proposals';
$pageSubtitle = 'Track all your submitted proposals';
$activePage = 'proposals';
$user = ['name' => $user['name'] ?? 'Freelancer', 'profile_image' => $user['profile_image'] ?? null];
$unreadCount = $unreadMessages ?? 0;
$profileLink = 'profile.php';
require_once __DIR__ . '/../components/layout_start.php';
?>
    <?php display_flash('success');
    display_flash('error'); ?>

            <div class="bg-white rounded-2xl p-5 border border-gray-100 shadow-sm fade-in">
                <div class="flex flex-wrap gap-2">
                    <?php
                    $filters = [
                        'all' => ['All', 'fa-layer-group'],
                        'pending' => ['Pending', 'fa-hourglass-half'],
                        'accepted' => ['Accepted', 'fa-check-circle'],
                        'rejected' => ['Rejected', 'fa-times-circle'],
                    ];
                    $filterStyles = [
                        'all' => 'bg-gray-100 text-gray-600',
                        'pending' => 'bg-amber-50 text-amber-600',
                        'accepted' => 'bg-emerald-50 text-emerald-600',
                        'rejected' => 'bg-red-50 text-red-500',
                    ];
                    foreach ($filters as $key => $label):
                        $isActive = $statusFilter === $key;
                        ?>
                    <a href="?status=<?= $key ?>"
                       class="inline-flex items-center gap-1.5 px-4 py-2 rounded-xl text-xs font-semibold transition-all <?= $isActive ? $filterStyles[$key] . ' border border-current/20' : 'text-gray-500 bg-gray-50 hover:bg-gray-100 border border-transparent' ?>">
                        <i class="fas <?= $label[1] ?> text-[10px]"></i> <?= $label[0] ?>
                    </a>
                    <?php endforeach; ?>
                </div>
            </div>

            <?php if ($proposals->num_rows > 0): ?>
            <div class="space-y-4">
                <?php while ($p = $proposals->fetch_assoc()): ?>
                <div class="bg-white rounded-2xl border border-gray-100 shadow-sm hover:shadow-md transition-all fade-in">
                    <div class="p-6">
                        <div class="flex flex-col lg:flex-row lg:items-start gap-4">
                            <div class="flex-1 min-w-0">
                                <div class="flex flex-wrap items-center gap-2 mb-2">
                                    <a href="proposal_detail.php?id=<?= (int) $p['id'] ?>" class="text-base font-bold text-gray-900 hover:text-blue-600 transition-colors">
                                        <?= sanitize_string($p['job_title']) ?>
                                    </a>
                                    <span class="inline-block px-2.5 py-1 rounded-lg text-[11px] font-semibold <?= $proposalColors[$p['status']] ?? '' ?>">
                                        <?= ucfirst(sanitize_string($p['status'])) ?>
                                    </span>
                                </div>

                                <div class="flex flex-wrap items-center gap-4 text-xs text-gray-400 mb-3">
                                    <span class="flex items-center gap-1.5">
                                        <i class="fas fa-dollar-sign text-emerald-500"></i>
                                        Your bid: <span class="font-semibold text-gray-700"><?= format_currency((float) $p['amount']) ?></span>
                                    </span>
                                    <span class="flex items-center gap-1.5">
                                        <i class="fas fa-tag text-blue-400"></i>
                                        Job budget: <?= format_currency((float) $p['job_budget']) ?>
                                    </span>
                                    <span class="flex items-center gap-1.5">
                                        <i class="fas fa-user text-gray-400"></i>
                                        <?= sanitize_string($p['client_name']) ?>
                                    </span>
                                    <span class="flex items-center gap-1.5">
                                        <i class="fas fa-calendar text-gray-400"></i>
                                        <?= time_ago($p['created_at']) ?>
                                    </span>
                                </div>

                                <div class="bg-gray-50 rounded-xl p-4 border border-gray-100">
                                    <p class="text-sm text-gray-600 leading-relaxed whitespace-pre-line line-clamp-3"><?= sanitize_string($p['proposal_text']) ?></p>
                                </div>
                            </div>

                            <div class="flex flex-wrap lg:flex-nowrap items-center gap-2 lg:flex-col lg:items-stretch lg:min-w-[140px]">
                                <a href="proposal_detail.php?id=<?= (int) $p['id'] ?>"
                                   class="inline-flex items-center gap-2 px-4 py-2 bg-blue-50 hover:bg-blue-100 text-blue-600 text-xs font-semibold rounded-xl transition-all">
                                    <i class="fas fa-eye text-[10px]"></i> View Details
                                </a>
                                <?php if ($p['status'] === 'accepted'): ?>
                                <a href="contracts.php"
                                   class="inline-flex items-center gap-2 px-4 py-2 bg-emerald-50 hover:bg-emerald-100 text-emerald-600 text-xs font-semibold rounded-xl transition-all">
                                    <i class="fas fa-handshake text-[10px]"></i> View Contract
                                </a>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>
                <?php endwhile; ?>
            </div>

            <?php if ($pagination['total_pages'] > 1): ?>
            <div class="flex items-center justify-between bg-white rounded-2xl p-4 border border-gray-100 shadow-sm fade-in">
                <p class="text-xs text-gray-400">
                    Showing <span class="font-semibold text-gray-600"><?= $pagination['offset'] + 1 ?></span>-<span class="font-semibold text-gray-600"><?= min($pagination['offset'] + $perPage, $totalFiltered) ?></span> of <span class="font-semibold text-gray-600"><?= $totalFiltered ?></span> proposals
                </p>
                <div class="flex items-center gap-1">
                    <?php if ($pagination['has_prev']): ?>
                    <a href="?<?= buildQueryString(['page' => $pagination['current_page'] - 1]) ?>" class="w-9 h-9 flex items-center justify-center rounded-xl border border-gray-200 text-gray-500 hover:bg-gray-50 text-sm">
                        <i class="fas fa-chevron-left text-xs"></i>
                    </a>
                    <?php endif; ?>
                    <?php
                    $startPage = max(1, $pagination['current_page'] - 2);
                    $endPage = min($pagination['total_pages'], $pagination['current_page'] + 2);
                    for ($i = $startPage; $i <= $endPage; $i++):
                        ?>
                    <a href="?<?= buildQueryString(['page' => $i]) ?>" class="w-9 h-9 flex items-center justify-center rounded-xl text-sm font-medium transition-all <?= $i === $pagination['current_page'] ? 'btn-grad text-white shadow-sm' : 'text-gray-500 hover:bg-gray-50' ?>"><?= $i ?></a>
                    <?php endfor; ?>
                    <?php if ($pagination['has_next']): ?>
                    <a href="?<?= buildQueryString(['page' => $pagination['current_page'] + 1]) ?>" class="w-9 h-9 flex items-center justify-center rounded-xl border border-gray-200 text-gray-500 hover:bg-gray-50 text-sm">
                        <i class="fas fa-chevron-right text-xs"></i>
                    </a>
                    <?php endif; ?>
                </div>
            </div>
            <?php endif; ?>

            <?php else: ?>
            <div class="bg-white rounded-2xl border border-gray-100 shadow-sm fade-in">
                <div class="text-center py-16 px-6">
                    <div class="w-24 h-24 rounded-3xl bg-gradient-to-br from-violet-50 to-purple-50 flex items-center justify-center mx-auto mb-6 border border-violet-100">
                        <i class="fas fa-file-alt text-4xl text-violet-300"></i>
                    </div>
                    <h3 class="text-xl font-bold text-gray-900 mb-2">No proposals yet</h3>
                    <p class="text-sm text-gray-400 mb-6 max-w-md mx-auto">Browse available jobs and submit your first proposal to start earning.</p>
                    <a href="browse_jobs.php" class="btn-grad inline-flex items-center gap-2 text-white font-bold px-6 py-3 rounded-xl text-sm">
                        <i class="fas fa-search text-xs"></i> Browse Jobs
                    </a>
                </div>
            </div>
            <?php endif; ?>

<?php require_once __DIR__ . '/../components/layout_end.php'; ?>
