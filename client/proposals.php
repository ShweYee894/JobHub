<?php
require_once __DIR__ . '/../auth/auth.php';
require_role('client');
require_once __DIR__ . '/../config/db.php';

$currentPage = 'proposals';
$userId = $_SESSION['user_id'];

$jobFilter = sanitize_int($_GET['job'] ?? 0);
$statusFilter = $_GET['status'] ?? 'all';
$sort = $_GET['sort'] ?? 'newest';
$page = max(1, sanitize_int($_GET['page'] ?? 1));
$perPage = 10;

$allowedStatuses = ['all', 'pending', 'accepted', 'rejected'];
if (!in_array($statusFilter, $allowedStatuses))
    $statusFilter = 'all';

$allowedSorts = ['newest', 'oldest', 'amount_high', 'amount_low'];
if (!in_array($sort, $allowedSorts))
    $sort = 'newest';

$where = 'WHERE j.client_id = ?';
$params = [$userId];
$types = 'i';

if ($jobFilter > 0) {
    $where .= ' AND p.job_id = ?';
    $params[] = $jobFilter;
    $types .= 'i';
}

if ($statusFilter !== 'all') {
    $where .= ' AND p.status = ?';
    $params[] = $statusFilter;
    $types .= 's';
}

$countSql = "SELECT COUNT(*) AS total FROM proposals p JOIN jobs j ON p.job_id = j.id $where";
$stmt = $conn->prepare($countSql);
$stmt->bind_param($types, ...$params);
$stmt->execute();
$totalFiltered = (int) $stmt->get_result()->fetch_assoc()['total'];
$stmt->close();

$pagination = paginate($totalFiltered, $perPage, $page);

$sortClause = match ($sort) {
    'amount_high' => 'p.amount DESC',
    'amount_low' => 'p.amount ASC',
    'oldest' => 'p.created_at ASC',
    default => 'p.created_at DESC',
};

$querySql = "SELECT p.id, p.amount, p.status, p.proposal_text, p.created_at,
             j.id AS job_id, j.title AS job_title, j.budget AS job_budget,
             u.id AS freelancer_user_id, u.name AS freelancer_name, u.profile_image AS freelancer_avatar
             FROM proposals p
             JOIN jobs j ON p.job_id = j.id
             JOIN users u ON p.freelancer_id = u.id
             $where
             ORDER BY $sortClause
             LIMIT ? OFFSET ?";

$finalTypes = $types . 'ii';
$finalParams = array_merge($params, [$perPage, $pagination['offset']]);

$stmt = $conn->prepare($querySql);
$stmt->bind_param($finalTypes, ...$finalParams);
$stmt->execute();
$proposals = $stmt->get_result();
$stmt->close();

$stmt = $conn->prepare('SELECT id, title FROM jobs WHERE client_id = ? ORDER BY title ASC');
$stmt->bind_param('i', $userId);
$stmt->execute();
$clientJobs = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

$proposalColors = [
    'pending' => 'bg-amber-50 text-amber-600 border border-amber-200 dark:bg-amber-900/20 dark:text-amber-400 dark:border-amber-800',
    'accepted' => 'bg-emerald-50 text-emerald-600 border border-emerald-200 dark:bg-emerald-900/20 dark:text-emerald-400 dark:border-emerald-800',
    'rejected' => 'bg-red-50 text-red-500 border border-red-200 dark:bg-red-900/20 dark:text-red-400 dark:border-red-800',
];

function buildQueryString(array $overrides = []): string
{
    $params = array_merge($_GET, $overrides);
    return http_build_query($params);
}

$pageTitle = 'Proposals Received';
$pageSubtitle = 'Review and manage proposals from freelancers';
$activePage = 'proposals';
$user = ['name' => $user['name'] ?? 'Client', 'profile_image' => $user['profile_image'] ?? null];
$unreadCount = get_unread_message_count($userId, 'client');
$profileLink = 'profile.php';
require_once __DIR__ . '/../includes/client_topbar.php';
?>
    <?php display_flash('success');
    display_flash('error'); ?>
<main class="max-w-7xl mx-auto px-4 sm:px-6 py-8">
    <div class=" mb-6">
        <form method="GET" class="flex flex-col sm:flex-row gap-3">
            <div class="relative flex-1">
                <i class="fas fa-briefcase absolute left-4 top-1/2 -translate-y-1/2 text-gray-400 text-sm"></i>
                <select name="job" class="appearance-none w-full bg-gray-50 border border-gray-200 rounded-xl pl-11 pr-10 py-2.5 text-sm text-gray-700 focus:outline-none focus:border-blue-500 focus:ring-2 focus:ring-blue-500/10 transition-all cursor-pointer dark:bg-gray-700 dark:border-gray-600 dark:text-gray-200 dark:focus:border-blue-400">
                    <option value="0">All Jobs</option>
                    <?php foreach ($clientJobs as $cj): ?>
                        <option value="<?= (int) $cj['id'] ?>" <?= $jobFilter == $cj['id'] ? 'selected' : '' ?>>
                            <?= sanitize_string($cj['title']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
                <i class="fas fa-chevron-down absolute right-4 top-1/2 -translate-y-1/2 text-gray-400 text-xs pointer-events-none"></i>
            </div>
            <div class="relative">
                <i class="fas fa-filter absolute left-4 top-1/2 -translate-y-1/2 text-gray-400 text-sm"></i>
                <select name="status" class="appearance-none bg-gray-50 border border-gray-200 rounded-xl pl-11 pr-10 py-2.5 text-sm text-gray-700 focus:outline-none focus:border-blue-500 focus:ring-2 focus:ring-blue-500/10 transition-all cursor-pointer dark:bg-gray-700 dark:border-gray-600 dark:text-gray-200 dark:focus:border-blue-400">
                    <option value="all" <?= $statusFilter === 'all' ? 'selected' : '' ?>>All Status</option>
                    <option value="pending" <?= $statusFilter === 'pending' ? 'selected' : '' ?>>Pending</option>
                    <option value="accepted" <?= $statusFilter === 'accepted' ? 'selected' : '' ?>>Accepted</option>
                    <option value="rejected" <?= $statusFilter === 'rejected' ? 'selected' : '' ?>>Rejected</option>
                </select>
                <i class="fas fa-chevron-down absolute right-4 top-1/2 -translate-y-1/2 text-gray-400 text-xs pointer-events-none"></i>
            </div>
            <div class="relative">
                <i class="fas fa-sort absolute left-4 top-1/2 -translate-y-1/2 text-gray-400 text-sm"></i>
                <select name="sort" class="appearance-none bg-gray-50 border border-gray-200 rounded-xl pl-11 pr-10 py-2.5 text-sm text-gray-700 focus:outline-none focus:border-blue-500 focus:ring-2 focus:ring-blue-500/10 transition-all cursor-pointer dark:bg-gray-700 dark:border-gray-600 dark:text-gray-200 dark:focus:border-blue-400">
                    <option value="newest" <?= $sort === 'newest' ? 'selected' : '' ?>>Newest First</option>
                    <option value="oldest" <?= $sort === 'oldest' ? 'selected' : '' ?>>Oldest First</option>
                    <option value="amount_high" <?= $sort === 'amount_high' ? 'selected' : '' ?>>Amount: High to Low</option>
                    <option value="amount_low" <?= $sort === 'amount_low' ? 'selected' : '' ?>>Amount: Low to High</option>
                </select>
                <i class="fas fa-chevron-down absolute right-4 top-1/2 -translate-y-1/2 text-gray-400 text-xs pointer-events-none"></i>
            </div>
            <button type="submit" class="btn-grad px-6 py-2.5 text-white text-sm font-semibold rounded-xl flex items-center gap-2 justify-center">
                <i class="fas fa-search text-xs"></i> Filter
            </button>
            <?php if ($jobFilter > 0 || $statusFilter !== 'all' || $sort !== 'newest'): ?>
                <a href="proposals.php" class="px-4 py-2.5 border border-gray-200 text-gray-600 hover:text-gray-900 hover:border-gray-300 rounded-xl text-sm font-medium transition-all flex items-center gap-2 justify-center dark:border-gray-600 dark:text-gray-400 dark:hover:text-white dark:hover:border-gray-500">
                    <i class="fas fa-times text-xs"></i> Clear
                </a>
            <?php endif; ?>
        </form>
    </div>

    <?php if ($proposals->num_rows > 0): ?>
        <div class="space-y-4">
            <?php while ($p = $proposals->fetch_assoc()): ?>
                <div class="bg-white rounded-2xl border border-gray-100 shadow-sm hover:shadow-md transition-all fade-in dark:bg-gray-800 dark:border-gray-700">
                    <div class="p-6">
                        <div class="flex flex-col lg:flex-row lg:items-start gap-4">
                            <div class="flex-shrink-0">
                                <?php
                                $fAvatar = !empty($p['freelancer_avatar'])
                                    ? '../' . htmlspecialchars($p['freelancer_avatar'])
                                    : 'https://ui-avatars.com/api/?name=' . urlencode($p['freelancer_name']) . '&background=2563eb&color=fff&bold=true';
                                ?>
                                <img src="<?= $fAvatar ?>" class="w-12 h-12 rounded-xl object-cover border-2 border-gray-100" alt="Freelancer">
                            </div>

                            <div class="flex-1 min-w-0">
                                <div class="flex flex-wrap items-center gap-2 mb-2">
                                    <a href="proposal_detail.php?id=<?= (int) $p['id'] ?>" class="text-base font-bold text-gray-900 hover:text-blue-600 transition-colors dark:text-white dark:hover:text-blue-400">
                                        <?= sanitize_string($p['freelancer_name']) ?>
                                    </a>
                                    <span class="inline-block px-2.5 py-1 rounded-lg text-[11px] font-semibold <?= $proposalColors[$p['status']] ?? '' ?>">
                                        <?= ucfirst(sanitize_string($p['status'])) ?>
                                    </span>
                                </div>

                                <p class="text-sm text-gray-500 mb-1 dark:text-gray-400">
                                    Re: <a href="job_detail.php?id=<?= (int) $p['job_id'] ?>" class="font-medium text-gray-700 hover:text-blue-600 transition-colors dark:text-gray-300 dark:hover:text-blue-400"><?= sanitize_string($p['job_title']) ?></a>
                                </p>

                                <div class="flex flex-wrap items-center gap-4 text-xs text-gray-400 mb-3 dark:text-gray-500">
                                    <span class="flex items-center gap-1.5">
                                        <i class="fas fa-dollar-sign text-emerald-500"></i>
                                        Bid: <span class="font-semibold text-gray-700 dark:text-gray-200"><?= format_currency((float) $p['amount']) ?></span>
                                    </span>
                                    <span class="flex items-center gap-1.5">
                                        <i class="fas fa-tag text-blue-400"></i>
                                        Job budget: <?= format_currency((float) $p['job_budget']) ?>
                                    </span>
                                    <span class="flex items-center gap-1.5">
                                        <i class="fas fa-calendar text-gray-400"></i>
                                        <?= time_ago($p['created_at']) ?>
                                    </span>
                                </div>

                                <div class="bg-gray-50 rounded-xl p-4 border border-gray-100 dark:bg-gray-700/50 dark:border-gray-600">
                                    <p class="text-sm text-gray-600 leading-relaxed whitespace-pre-line line-clamp-2 dark:text-gray-300"><?= sanitize_string($p['proposal_text']) ?></p>
                                </div>
                            </div>

                            <div class="flex flex-wrap lg:flex-nowrap items-center gap-2 lg:flex-col lg:items-stretch lg:min-w-[140px]">
                                <a href="proposal_detail.php?id=<?= (int) $p['id'] ?>"
                                    class="inline-flex items-center gap-2 px-4 py-2 bg-blue-50 hover:bg-blue-100 text-blue-600 text-xs font-semibold rounded-xl transition-all dark:bg-blue-900/20 dark:hover:bg-blue-900/30 dark:text-blue-400">
                                    <i class="fas fa-eye text-[10px]"></i> View Details
                                </a>
                                <?php if ($p['status'] === 'pending'): ?>
                                    <a href="proposal_detail.php?id=<?= (int) $p['id'] ?>"
                                        class="inline-flex items-center gap-2 px-4 py-2 bg-emerald-50 hover:bg-emerald-100 text-emerald-600 text-xs font-semibold rounded-xl transition-all dark:bg-emerald-900/20 dark:hover:bg-emerald-900/30 dark:text-emerald-400">
                                        <i class="fas fa-check text-[10px]"></i> Review
                                    </a>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>
            <?php endwhile; ?>
        </div>

        <?php if ($pagination['total_pages'] > 1): ?>
            <div class="flex items-center justify-between bg-white rounded-2xl p-4 border border-gray-100 shadow-sm fade-in dark:bg-gray-800 dark:border-gray-700">
                <p class="text-xs text-gray-400 dark:text-gray-500">
                    Showing <span class="font-semibold text-gray-600 dark:text-gray-300"><?= $pagination['offset'] + 1 ?></span>-<span class="font-semibold text-gray-600 dark:text-gray-300"><?= min($pagination['offset'] + $perPage, $totalFiltered) ?></span> of <span class="font-semibold text-gray-600 dark:text-gray-300"><?= $totalFiltered ?></span> proposals
                </p>
                <div class="flex items-center gap-1">
                    <?php if ($pagination['has_prev']): ?>
                        <a href="?<?= buildQueryString(['page' => $pagination['current_page'] - 1]) ?>" class="w-9 h-9 flex items-center justify-center rounded-xl border border-gray-200 text-gray-500 hover:bg-gray-50 text-sm dark:border-gray-600 dark:text-gray-400 dark:hover:bg-gray-700">
                            <i class="fas fa-chevron-left text-xs"></i>
                        </a>
                    <?php endif; ?>
                    <?php $conn->close(); ?>
                    <?php
                    $startPage = max(1, $pagination['current_page'] - 2);
                    $endPage = min($pagination['total_pages'], $pagination['current_page'] + 2);
                    for ($i = $startPage; $i <= $endPage; $i++):
                        ?>
                        <a href="?<?= buildQueryString(['page' => $i]) ?>" class="w-9 h-9 flex items-center justify-center rounded-xl text-sm font-medium transition-all <?= $i === $pagination['current_page'] ? 'btn-grad text-white shadow-sm' : 'text-gray-500 hover:bg-gray-50 dark:text-gray-400 dark:hover:bg-gray-700' ?>"><?= $i ?></a>
                    <?php endfor; ?>
                    <?php if ($pagination['has_next']): ?>
                        <a href="?<?= buildQueryString(['page' => $pagination['current_page'] + 1]) ?>" class="w-9 h-9 flex items-center justify-center rounded-xl border border-gray-200 text-gray-500 hover:bg-gray-50 text-sm dark:border-gray-600 dark:text-gray-400 dark:hover:bg-gray-700">
                            <i class="fas fa-chevron-right text-xs"></i>
                        </a>
                    <?php endif; ?>
                </div>
            </div>
        <?php endif; ?>

    <?php else: ?>
        <div class="bg-white rounded-2xl border border-gray-100 shadow-sm fade-in dark:bg-gray-800 dark:border-gray-700">
            <div class="text-center py-16 px-6">
                <div class="w-24 h-24 rounded-3xl bg-gradient-to-br from-blue-50 to-cyan-50 flex items-center justify-center mx-auto mb-6 border border-blue-100 dark:from-blue-900/20 dark:to-cyan-900/20 dark:border-blue-800">
                    <i class="fas fa-file-alt text-4xl text-blue-300 dark:text-blue-500"></i>
                </div>
                <h3 class="text-xl font-bold text-gray-900 mb-2 dark:text-white">No proposals found</h3>
                <p class="text-sm text-gray-400 mb-6 max-w-md mx-auto dark:text-gray-500">
                    <?php if ($jobFilter > 0 || $statusFilter !== 'all'): ?>
                        No proposals match your current filters. Try adjusting your search.
                    <?php else: ?>
                        Post a job and wait for freelancers to submit their proposals.
                    <?php endif; ?>
                </p>
                <?php if ($jobFilter > 0 || $statusFilter !== 'all'): ?>
                    <a href="proposals.php" class="btn-grad inline-flex items-center gap-2 text-white font-bold px-6 py-3 rounded-xl text-sm">
                        <i class="fas fa-times text-xs"></i> Clear Filters
                    </a>
                <?php else: ?>
                    <a href="post_job.php" class="btn-grad inline-flex items-center gap-2 text-white font-bold px-6 py-3 rounded-xl text-sm">
                        <i class="fas fa-plus text-xs"></i> Post a Job
                    </a>
                <?php endif; ?>
            </div>
        </div>
    <?php endif; ?>
</main>
<?php require_once __DIR__ . '/../includes/client_footer.php'; ?>
