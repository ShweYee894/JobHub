<?php
require_once __DIR__ . '/../auth/auth.php';
require_role('client');
require_once __DIR__ . '/../config/db.php';

if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'client') {
    header('Location: ../auth/login.php');
    exit();
}

$userId = $_SESSION['user_id'];

$stmt = $conn->prepare('SELECT name, profile_image FROM users WHERE id = ?');
$stmt->bind_param('i', $userId);
$stmt->execute();
$user = $stmt->get_result()->fetch_assoc();
$stmt->close();

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
    'withdrawn' => 'bg-gray-100 text-gray-500 border border-gray-200 dark:bg-gray-800 dark:text-gray-400 dark:border-gray-700',
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
    <!-- ═══ PAGE HEADER ════════════════════════════════════════ -->
    <div class="mb-8">
        <h1 class="text-2xl font-bold text-gray-900 dark:text-white mb-1">Proposals Received</h1>
        <p class="text-sm text-gray-400 dark:text-gray-500">Review and manage proposals from freelancers</p>
    </div>

    <!-- ═══ FILTER BAR ═════════════════════════════════════════ -->
    <div class="bg-white rounded-2xl p-5 border border-gray-100 shadow-sm fade-in dark:bg-gray-800 dark:border-gray-700 mb-6">
        <form method="GET" class="flex flex-col sm:flex-row gap-3">
            <div class="relative flex-1 min-w-0">
                <i data-lucide="briefcase" class="w-4 h-4 absolute left-3.5 top-1/2 -translate-y-1/2 text-gray-400 pointer-events-none"></i>
                <select name="job" class="appearance-none w-full bg-gray-50 border border-gray-200 rounded-xl pl-10 pr-10 py-2.5 text-sm text-gray-700 focus:outline-none focus:border-indigo-500 focus:ring-2 focus:ring-indigo-500/10 transition-all cursor-pointer dark:bg-gray-700 dark:border-gray-600 dark:text-gray-200 dark:focus:border-indigo-400">
                    <option value="0">All Jobs</option>
                    <?php foreach ($clientJobs as $cj): ?>
                        <option value="<?= (int) $cj['id'] ?>" <?= $jobFilter == $cj['id'] ? 'selected' : '' ?>>
                            <?= sanitize_string($cj['title']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
                <i data-lucide="chevron-down" class="w-4 h-4 absolute right-3.5 top-1/2 -translate-y-1/2 text-gray-400 pointer-events-none"></i>
            </div>

            <div class="relative sm:w-48">
                <i data-lucide="sliders-horizontal" class="w-4 h-4 absolute left-3.5 top-1/2 -translate-y-1/2 text-gray-400 pointer-events-none"></i>
                <select name="status" class="appearance-none w-full bg-gray-50 border border-gray-200 rounded-xl pl-10 pr-10 py-2.5 text-sm text-gray-700 focus:outline-none focus:border-indigo-500 focus:ring-2 focus:ring-indigo-500/10 transition-all cursor-pointer dark:bg-gray-700 dark:border-gray-600 dark:text-gray-200 dark:focus:border-indigo-400">
                    <option value="all" <?= $statusFilter === 'all' ? 'selected' : '' ?>>All Status</option>
                    <option value="pending" <?= $statusFilter === 'pending' ? 'selected' : '' ?>>Pending</option>
                    <option value="accepted" <?= $statusFilter === 'accepted' ? 'selected' : '' ?>>Accepted</option>
                    <option value="rejected" <?= $statusFilter === 'rejected' ? 'selected' : '' ?>>Rejected</option>
                </select>
                <i data-lucide="chevron-down" class="w-4 h-4 absolute right-3.5 top-1/2 -translate-y-1/2 text-gray-400 pointer-events-none"></i>
            </div>

            <div class="relative sm:w-52">
                <i data-lucide="arrow-up-down" class="w-4 h-4 absolute left-3.5 top-1/2 -translate-y-1/2 text-gray-400 pointer-events-none"></i>
                <select name="sort" class="appearance-none w-full bg-gray-50 border border-gray-200 rounded-xl pl-10 pr-10 py-2.5 text-sm text-gray-700 focus:outline-none focus:border-indigo-500 focus:ring-2 focus:ring-indigo-500/10 transition-all cursor-pointer dark:bg-gray-700 dark:border-gray-600 dark:text-gray-200 dark:focus:border-indigo-400">
                    <option value="newest" <?= $sort === 'newest' ? 'selected' : '' ?>>Newest First</option>
                    <option value="oldest" <?= $sort === 'oldest' ? 'selected' : '' ?>>Oldest First</option>
                    <option value="amount_high" <?= $sort === 'amount_high' ? 'selected' : '' ?>>Amount: High to Low</option>
                    <option value="amount_low" <?= $sort === 'amount_low' ? 'selected' : '' ?>>Amount: Low to High</option>
                </select>
                <i data-lucide="chevron-down" class="w-4 h-4 absolute right-3.5 top-1/2 -translate-y-1/2 text-gray-400 pointer-events-none"></i>
            </div>

            <button type="submit" class="inline-flex items-center justify-center gap-2 px-5 py-2.5 bg-indigo-600 hover:bg-indigo-700 text-white text-xs font-semibold rounded-xl shadow-lg shadow-indigo-500/25 transition-all duration-200">
                <i data-lucide="search" class="w-4 h-4"></i> Filter
            </button>

            <?php if ($jobFilter > 0 || $statusFilter !== 'all' || $sort !== 'newest'): ?>
                <a href="proposals.php" class="inline-flex items-center gap-1.5 px-4 py-2.5 border border-red-200 text-red-500 hover:bg-red-50 hover:border-red-300 rounded-xl text-xs font-semibold transition-all dark:border-red-800 dark:text-red-400 dark:hover:bg-red-900/20">
                    <i data-lucide="x" class="w-3.5 h-3.5"></i> Clear
                </a>
            <?php endif; ?>
        </form>
    </div>

    <!-- ═══ RESULTS COUNT ══════════════════════════════════════ -->
    <?php if ($proposals->num_rows > 0): ?>
        <div class="flex items-center justify-between mb-5 px-1">
            <p class="text-xs text-gray-400 dark:text-gray-500">
                Showing <span class="font-semibold text-gray-600 dark:text-gray-300"><?= $pagination['offset'] + 1 ?></span>–<span class="font-semibold text-gray-600 dark:text-gray-300"><?= min($pagination['offset'] + $perPage, $totalFiltered) ?></span> of <span class="font-semibold text-gray-600 dark:text-gray-300"><?= $totalFiltered ?></span> proposal<?= $totalFiltered !== 1 ? 's' : '' ?>
            </p>
        </div>
    <?php endif; ?>

    <!-- ═══ PROPOSAL CARDS ═════════════════════════════════════ -->
    <?php if ($proposals->num_rows > 0): ?>
        <div class="space-y-4">
            <?php while ($p = $proposals->fetch_assoc()): ?>
                <?php
                $fAvatar = !empty($p['freelancer_avatar'])
                    ? '../' . htmlspecialchars($p['freelancer_avatar'])
                    : 'https://ui-avatars.com/api/?name=' . urlencode($p['freelancer_name']) . '&background=4338CA&color=fff&bold=true&font-size=0.45';
                ?>
                <div class="bg-white rounded-2xl border border-gray-100 shadow-sm hover:shadow-md transition-all duration-300 fade-in dark:bg-gray-800 dark:border-gray-700 dark:hover:border-gray-600">
                    <div class="p-6">

                        <!-- Zone A: Freelancer Header -->
                        <div class="flex items-center justify-between mb-4">
                            <div class="flex items-center gap-3">
                                <img src="<?= $fAvatar ?>" class="w-10 h-10 rounded-full object-cover ring-2 ring-white dark:ring-gray-800" alt="<?= sanitize_string($p['freelancer_name']) ?>">
                                <div>
                                    <div class="flex items-center gap-2">
                                        <span class="text-sm font-semibold text-gray-900 dark:text-white"><?= sanitize_string($p['freelancer_name']) ?></span>
                                        <span class="inline-flex items-center px-2 py-0.5 rounded-full text-[10px] font-semibold <?= $proposalColors[$p['status']] ?? '' ?>">
                                            <?= ucfirst(sanitize_string($p['status'])) ?>
                                        </span>
                                    </div>
                                    <div class="flex items-center gap-1.5 mt-0.5">
                                        <div class="flex items-center gap-0.5">
                                            <i data-lucide="star" class="w-3 h-3 text-amber-400 fill-amber-400"></i>
                                            <i data-lucide="star" class="w-3 h-3 text-amber-400 fill-amber-400"></i>
                                            <i data-lucide="star" class="w-3 h-3 text-amber-400 fill-amber-400"></i>
                                            <i data-lucide="star" class="w-3 h-3 text-amber-400 fill-amber-400"></i>
                                            <i data-lucide="star" class="w-3 h-3 text-amber-400 fill-amber-400"></i>
                                        </div>
                                        <span class="text-[11px] text-gray-400 dark:text-gray-500">4.8</span>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Zone B: Job Context -->
                        <div class="flex items-center gap-2 mb-4 pb-4 border-b border-gray-100 dark:border-gray-700">
                            <i data-lucide="briefcase" class="w-3.5 h-3.5 text-indigo-400"></i>
                            <a href="job_detail.php?id=<?= (int) $p['job_id'] ?>" class="text-sm font-medium text-gray-600 hover:text-indigo-600 transition-colors dark:text-gray-400 dark:hover:text-indigo-400">
                                <?= sanitize_string($p['job_title']) ?>
                            </a>
                        </div>

                        <!-- Zone C: Financial Details -->
                        <div class="flex items-center gap-6 mb-4">
                            <div class="flex items-center gap-2">
                                <div class="w-8 h-8 rounded-lg bg-emerald-50 flex items-center justify-center dark:bg-emerald-900/20">
                                    <i data-lucide="dollar-sign" class="w-4 h-4 text-emerald-500"></i>
                                </div>
                                <div>
                                    <p class="text-[10px] text-gray-400 dark:text-gray-500 uppercase tracking-wider font-medium">Bid Amount</p>
                                    <p class="text-lg font-bold text-gray-900 dark:text-white leading-tight"><?= format_currency((float) $p['amount']) ?></p>
                                </div>
                            </div>
                            <div class="w-px h-8 bg-gray-100 dark:bg-gray-700"></div>
                            <div class="flex items-center gap-2">
                                <div class="w-8 h-8 rounded-lg bg-blue-50 flex items-center justify-center dark:bg-blue-900/20">
                                    <i data-lucide="tag" class="w-4 h-4 text-blue-400"></i>
                                </div>
                                <div>
                                    <p class="text-[10px] text-gray-400 dark:text-gray-500 uppercase tracking-wider font-medium">Job Budget</p>
                                    <p class="text-sm font-semibold text-gray-600 dark:text-gray-300 leading-tight"><?= format_currency((float) $p['job_budget']) ?></p>
                                </div>
                            </div>
                        </div>

                        <!-- Zone D: Proposal Excerpt -->
                        <div class="bg-gray-50 rounded-xl p-4 border border-gray-100 dark:bg-gray-700/50 dark:border-gray-600 mb-5">
                            <p class="text-sm text-gray-500 leading-relaxed line-clamp-2 dark:text-gray-400"><?= sanitize_string($p['proposal_text']) ?></p>
                        </div>

                    </div>

                    <!-- Zone E: Card Footer -->
                    <div class="flex items-center justify-between border-t border-gray-100 dark:border-gray-700 px-6 py-4">
                        <span class="text-xs text-gray-400 dark:text-gray-500 flex items-center gap-1.5">
                            <i data-lucide="clock" class="w-3 h-3"></i>
                            Submitted <?= time_ago($p['created_at']) ?>
                        </span>
                        <div class="flex items-center gap-2">
                            <a href="proposal_detail.php?id=<?= (int) $p['id'] ?>" class="inline-flex items-center gap-1.5 px-4 py-2 bg-indigo-600 hover:bg-indigo-700 text-white text-xs font-semibold rounded-lg shadow-sm hover:shadow-md transition-all duration-200">
                                Review Proposal
                                <i data-lucide="arrow-right" class="w-3.5 h-3.5"></i>
                            </a>

                        </div>
                    </div>

                </div>
            <?php endwhile; ?>
        </div>

        <!-- ═══ PAGINATION ══════════════════════════════════════ -->
        <?php if ($pagination['total_pages'] > 1): ?>
            <div class="flex items-center justify-between bg-white rounded-2xl p-4 border border-gray-100 shadow-sm fade-in mt-6 dark:bg-gray-800 dark:border-gray-700">
                <p class="text-xs text-gray-400 dark:text-gray-500">
                    Page <span class="font-semibold text-gray-600 dark:text-gray-300"><?= $pagination['current_page'] ?></span> of <span class="font-semibold text-gray-600 dark:text-gray-300"><?= $pagination['total_pages'] ?></span>
                </p>
                <div class="flex items-center gap-1">
                    <?php if ($pagination['has_prev']): ?>
                        <a href="?<?= buildQueryString(['page' => $pagination['current_page'] - 1]) ?>" class="w-9 h-9 flex items-center justify-center rounded-xl border border-gray-200 text-gray-500 hover:bg-gray-50 text-sm transition-all dark:border-gray-600 dark:text-gray-400 dark:hover:bg-gray-700">
                            <i data-lucide="chevron-left" class="w-4 h-4"></i>
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
                        <a href="?<?= buildQueryString(['page' => $pagination['current_page'] + 1]) ?>" class="w-9 h-9 flex items-center justify-center rounded-xl border border-gray-200 text-gray-500 hover:bg-gray-50 text-sm transition-all dark:border-gray-600 dark:text-gray-400 dark:hover:bg-gray-700">
                            <i data-lucide="chevron-right" class="w-4 h-4"></i>
                        </a>
                    <?php endif; ?>
                </div>
            </div>
        <?php endif; ?>

    <?php else: ?>
        <!-- ═══ EMPTY STATE ═════════════════════════════════════ -->
        <div class="bg-white rounded-2xl border border-gray-100 shadow-sm fade-in dark:bg-gray-800 dark:border-gray-700">
            <div class="text-center py-20 px-6">
                <div class="w-20 h-20 rounded-2xl bg-gradient-to-br from-indigo-50 to-blue-50 flex items-center justify-center mx-auto mb-6 border border-indigo-100 dark:from-indigo-900/20 dark:to-blue-900/20 dark:border-indigo-800">
                    <i data-lucide="inbox" class="w-9 h-9 text-indigo-300 dark:text-indigo-500"></i>
                </div>
                <h3 class="text-xl font-bold text-gray-900 mb-2 dark:text-white">No proposals found</h3>
                <p class="text-sm text-gray-400 mb-8 max-w-sm mx-auto dark:text-gray-500 leading-relaxed">
                    <?php if ($jobFilter > 0 || $statusFilter !== 'all'): ?>
                        No proposals match your current filters. Try adjusting your search criteria or clearing filters.
                    <?php else: ?>
                        Post a job and wait for talented freelancers to submit their proposals.
                    <?php endif; ?>
                </p>
                <?php if ($jobFilter > 0 || $statusFilter !== 'all'): ?>
                    <a href="proposals.php" class="inline-flex items-center gap-2 px-6 py-3 btn-grad text-white font-semibold rounded-xl text-sm shadow-lg shadow-indigo-500/25 transition-all">
                        <i data-lucide="x" class="w-4 h-4"></i> Clear All Filters
                    </a>
                <?php else: ?>
                    <a href="post_job.php" class="inline-flex items-center gap-2 px-6 py-3 btn-grad text-white font-semibold rounded-xl text-sm shadow-lg shadow-indigo-500/25 transition-all">
                        <i data-lucide="plus" class="w-4 h-4"></i> Post a Job
                    </a>
                <?php endif; ?>
            </div>
        </div>
    <?php endif; ?>


</main>
<?php require_once __DIR__ . '/../includes/client_footer.php'; ?>
