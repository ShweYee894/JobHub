<?php
session_start();
require_once '../config/db.php';
require_once '../config/helpers.php';

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

$search = trim($_GET['search'] ?? '');
$statusFilter = $_GET['status'] ?? 'all';
$page = max(1, intval($_GET['page'] ?? 1));
$perPage = 10;

$allowedStatuses = ['all', 'open', 'in_progress', 'completed', 'disputed', 'cancelled'];
if (!in_array($statusFilter, $allowedStatuses))
    $statusFilter = 'all';

$where = 'WHERE j.client_id = ?';
$params = [$userId];
$types = 'i';

if ($statusFilter !== 'all') {
    $where .= ' AND j.status = ?';
    $params[] = $statusFilter;
    $types .= 's';
}
if ($search !== '') {
    $where .= ' AND (j.title LIKE ? OR j.description LIKE ?)';
    $params[] = "%$search%";
    $params[] = "%$search%";
    $types .= 'ss';
}

$countSql = "SELECT COUNT(*) AS total FROM jobs j $where";
$stmt = $conn->prepare($countSql);
$stmt->bind_param($types, ...$params);
$stmt->execute();
$totalFiltered = $stmt->get_result()->fetch_assoc()['total'];
$stmt->close();

$pagination = paginate($totalFiltered, $perPage, $page);

$querySql = "SELECT j.id, j.title, j.description, j.budget, j.status, j.created_at
             FROM jobs j $where
             ORDER BY j.created_at DESC
             LIMIT ? OFFSET ?";
$finalTypes = $types . 'ii';
$finalParams = array_merge($params, [$perPage, $pagination['offset']]);
$stmt = $conn->prepare($querySql);
$stmt->bind_param($finalTypes, ...$finalParams);
$stmt->execute();
$jobsResult = $stmt->get_result();
$stmt->close();

$jobs = [];
while ($row = $jobsResult->fetch_assoc()) {
    $jid = $row['id'];

    $ps = $conn->prepare('SELECT COUNT(*) AS cnt FROM proposals WHERE job_id = ?');
    $ps->bind_param('i', $jid);
    $ps->execute();
    $row['proposal_count'] = $ps->get_result()->fetch_assoc()['cnt'];
    $ps->close();

    $ss = $conn->prepare('SELECT s.skill_name FROM skills s JOIN job_skills js ON s.id = js.skill_id WHERE js.job_id = ? ORDER BY s.skill_name');
    $ss->bind_param('i', $jid);
    $ss->execute();
    $skillsRes = $ss->get_result();
    $row['skills'] = [];
    while ($sk = $skillsRes->fetch_assoc()) {
        $row['skills'][] = $sk['skill_name'];
    }
    $ss->close();

    $jobs[] = $row;
}

$statusColors = [
    'open' => 'bg-emerald-50 text-emerald-600 border border-emerald-200',
    'in_progress' => 'bg-blue-50 text-blue-600 border border-blue-200',
    'completed' => 'bg-purple-50 text-purple-600 border border-purple-200',
    'cancelled' => 'bg-gray-100 text-gray-600 border border-gray-200',
    'disputed' => 'bg-red-50 text-red-600 border border-red-200',
];

function buildQueryString(array $overrides = []): string
{
    $params = array_merge($_GET, $overrides);
    return http_build_query($params);
}

// $conn->close();

$navItems = [
    ['key' => 'dashboard', 'label' => 'Dashboard', 'url' => 'dashboard.php', 'icon' => 'fa-th-large'],
    ['key' => 'my_jobs', 'label' => 'My Jobs', 'url' => 'my_jobs.php', 'icon' => 'fa-briefcase'],
    ['key' => 'post_job', 'label' => 'Post a Job', 'url' => 'post_job.php', 'icon' => 'fa-plus-circle'],
    ['key' => 'proposals', 'label' => 'Proposals', 'url' => 'proposals.php', 'icon' => 'fa-file-alt'],
    ['key' => 'recommended_freelancers', 'label' => 'Find Freelancers', 'url' => 'recommended_freelancers.php', 'icon' => 'fa-search'],
    ['key' => 'contracts', 'label' => 'Contracts', 'url' => 'contracts.php', 'icon' => 'fa-handshake'],
    ['key' => 'reviews', 'label' => 'Reviews', 'url' => 'reviews.php', 'icon' => 'fa-star'],
    ['key' => 'payment_history', 'label' => 'Payments', 'url' => 'payment_history.php', 'icon' => 'fa-credit-card'],
    ['key' => 'messages', 'label' => 'Messages', 'url' => 'messages.php', 'icon' => 'fa-comment-dots'],
];
$pageTitle = 'My Jobs';
$pageSubtitle = 'Manage and track all your posted jobs';
$activePage = 'my_jobs';
$user = ['name' => $user['name'] ?? 'Client', 'profile_image' => $user['profile_image'] ?? null];
$unreadCount = get_unread_message_count($userId, 'client');
$profileLink = 'profile.php';
require_once __DIR__ . '/../components/layout_start.php';
?>
    <?php display_flash('success');
    display_flash('error'); ?>

    <div class="grid grid-cols-1 sm:grid-cols-2 xl:grid-cols-4 gap-4">
        <?php
        $stats = [
            ['label' => 'Total Jobs', 'key' => 'total', 'icon' => 'fa-briefcase', 'color' => 'blue'],
            ['label' => 'Open Jobs', 'key' => 'open', 'icon' => 'fa-folder-open', 'color' => 'emerald'],
            ['label' => 'In Progress', 'key' => 'in_progress', 'icon' => 'fa-spinner', 'color' => 'blue'],
            ['label' => 'Completed', 'key' => 'completed', 'icon' => 'fa-check-circle', 'color' => 'purple'],
        ];
        foreach ($stats as $i => $stat):
            $cnt = 0;
            if ($stat['key'] === 'total') {
                $cnt = $totalFiltered;
            } else {
                $sc = $conn->prepare('SELECT COUNT(*) AS c FROM jobs WHERE client_id = ? AND status = ?');
                $sc->bind_param('is', $userId, $stat['key']);
                $sc->execute();
                $cnt = $sc->get_result()->fetch_assoc()['c'];
                $sc->close();
            }
            ?>
            <div class="stat-card bg-white rounded-2xl p-5 border border-gray-100 shadow-sm fade-in" style="animation-delay:<?= $i * 0.1 ?>s">
                <div class="flex items-center justify-between mb-3">
                    <div class="w-11 h-11 rounded-xl bg-<?= $stat['color'] ?>-50 flex items-center justify-center"><i class="fas <?= $stat['icon'] ?> text-<?= $stat['color'] ?>-500"></i></div>
                </div>
                <p class="text-2xl font-black text-gray-900"><?= $cnt ?></p>
                <p class="text-xs text-gray-400 mt-1"><?= $stat['label'] ?></p>
            </div>
        <?php
        endforeach;
        ?>
    </div>

    <div class="bg-white rounded-2xl p-5 border border-gray-100 shadow-sm fade-in" style="animation-delay:.35s">
        <form method="GET" class="flex flex-col sm:flex-row gap-3">
            <div class="relative flex-1">
                <i class="fas fa-search absolute left-4 top-1/2 -translate-y-1/2 text-gray-400 text-sm"></i>
                <input type="text" name="search" value="<?= sanitize_string($search) ?>" placeholder="Search jobs by title..." class="w-full bg-gray-50 border border-gray-200 rounded-xl pl-11 pr-4 py-2.5 text-sm text-gray-900 placeholder-gray-400 focus:outline-none focus:border-blue-500 focus:ring-2 focus:ring-blue-500/10 transition-all">
            </div>
            <div class="relative">
                <i class="fas fa-filter absolute left-4 top-1/2 -translate-y-1/2 text-gray-400 text-sm"></i>
                <select name="status" class="appearance-none bg-gray-50 border border-gray-200 rounded-xl pl-11 pr-10 py-2.5 text-sm text-gray-700 focus:outline-none focus:border-blue-500 focus:ring-2 focus:ring-blue-500/10 transition-all cursor-pointer">
                    <option value="all" <?= $statusFilter === 'all' ? 'selected' : '' ?>>All Status</option>
                    <option value="open" <?= $statusFilter === 'open' ? 'selected' : '' ?>>Open</option>
                    <option value="in_progress" <?= $statusFilter === 'in_progress' ? 'selected' : '' ?>>In Progress</option>
                    <option value="completed" <?= $statusFilter === 'completed' ? 'selected' : '' ?>>Completed</option>
                    <option value="cancelled" <?= $statusFilter === 'cancelled' ? 'selected' : '' ?>>Cancelled</option>
                    <option value="disputed" <?= $statusFilter === 'disputed' ? 'selected' : '' ?>>Disputed</option>
                </select>
                <i class="fas fa-chevron-down absolute right-4 top-1/2 -translate-y-1/2 text-gray-400 text-xs pointer-events-none"></i>
            </div>
            <button type="submit" class="btn-grad px-6 py-2.5 text-white text-sm font-semibold rounded-xl flex items-center gap-2 justify-center"><i class="fas fa-search text-xs"></i> Search</button>
            <?php if ($search !== '' || $statusFilter !== 'all'): ?>
                <a href="my_jobs.php" class="px-4 py-2.5 border border-gray-200 text-gray-600 hover:text-gray-900 hover:border-gray-300 rounded-xl text-sm font-medium transition-all flex items-center gap-2 justify-center"><i class="fas fa-times text-xs"></i> Clear</a>
            <?php endif; ?>
        </form>
    </div>

    <?php if (!empty($jobs)): ?>
        <div class="space-y-4">
            <?php foreach ($jobs as $index => $job): ?>
                <div class="bg-white rounded-2xl border border-gray-100 shadow-sm hover:shadow-md transition-all fade-in" style="animation-delay:<?= 0.4 + ($index * 0.05) ?>s">
                    <div class="p-6">
                        <div class="flex flex-col lg:flex-row lg:items-start gap-4">
                            <div class="flex-1 min-w-0">
                                <div class="flex flex-wrap items-center gap-2 mb-2">
                                    <a href="job_detail.php?id=<?= $job['id'] ?>" class="text-base font-bold text-gray-900 hover:text-blue-600 transition-colors"><?= sanitize_string($job['title']) ?></a>
                                    <span class="inline-block px-2.5 py-1 rounded-lg text-[11px] font-semibold <?= $statusColors[$job['status']] ?? 'bg-gray-100 text-gray-600 border border-gray-200' ?>"><?= ucfirst(str_replace('_', ' ', sanitize_string($job['status']))) ?></span>
                                </div>
                                <p class="text-sm text-gray-500 leading-relaxed mb-3 line-clamp-2"><?= sanitize_string(mb_strimwidth($job['description'], 0, 150, '...')) ?></p>
                                <?php if (!empty($job['skills'])): ?>
                                    <div class="flex flex-wrap gap-1.5 mb-3">
                                        <?php foreach ($job['skills'] as $skill): ?>
                                            <span class="inline-block px-2.5 py-1 bg-blue-50 text-blue-600 text-[11px] font-medium rounded-lg border border-blue-100"><?= sanitize_string($skill) ?></span>
                                        <?php endforeach; ?>
                                    </div>
                                <?php endif; ?>
                                <div class="flex flex-wrap items-center gap-4 text-xs text-gray-400">
                                    <span class="flex items-center gap-1.5"><i class="fas fa-dollar-sign text-emerald-500"></i><span class="font-semibold text-gray-700"><?= format_currency($job['budget']) ?></span></span>
                                    <span class="flex items-center gap-1.5"><i class="fas fa-calendar text-blue-400"></i><?= date('M d, Y', strtotime($job['created_at'])) ?></span>
                                    <span class="flex items-center gap-1.5"><i class="fas fa-file-alt text-violet-400"></i><span class="font-semibold text-gray-600"><?= $job['proposal_count'] ?></span> proposal<?= $job['proposal_count'] !== 1 ? 's' : '' ?></span>
                                </div>
                            </div>
                            <div class="flex flex-wrap lg:flex-nowrap items-center gap-2 lg:flex-col lg:items-stretch">
                                <a href="job_detail.php?id=<?= $job['id'] ?>" class="inline-flex items-center gap-2 px-4 py-2 bg-blue-50 hover:bg-blue-100 text-blue-600 text-xs font-semibold rounded-xl transition-all"><i class="fas fa-eye text-[10px]"></i> View Details</a>
                                <a href="edit_job.php?id=<?= $job['id'] ?>" class="inline-flex items-center gap-2 px-4 py-2 bg-gray-50 hover:bg-gray-100 text-gray-600 text-xs font-semibold rounded-xl transition-all"><i class="fas fa-pen text-[10px]"></i> Edit Job</a>
                                <?php if ($job['status'] === 'open'): ?>
                                    <a href="job_detail.php?id=<?= $job['id'] ?>" class="inline-flex items-center gap-2 px-4 py-2 bg-emerald-50 hover:bg-emerald-100 text-emerald-600 text-xs font-semibold rounded-xl transition-all"><i class="fas fa-file-alt text-[10px]"></i> View Proposals</a>
                                <?php endif; ?>
                                <?php if ($job['status'] === 'open' || $job['status'] === 'cancelled'): ?>
                                    <form method="POST" action="delete_job.php" class="inline" onsubmit="return confirm('Are you sure you want to delete this job?');">
                                        <?= csrf_field() ?>
                                        <input type="hidden" name="job_id" value="<?= $job['id'] ?>">
                                        <button type="submit" class="inline-flex items-center gap-2 px-4 py-2 bg-red-50 hover:bg-red-100 text-red-500 text-xs font-semibold rounded-xl transition-all"><i class="fas fa-trash text-[10px]"></i> Delete</button>
                                    </form>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>

        <?php if ($pagination['total_pages'] > 1): ?>
            <div class="flex items-center justify-between bg-white rounded-2xl p-4 border border-gray-100 shadow-sm fade-in">
                <p class="text-xs text-gray-400">Showing <span class="font-semibold text-gray-600"><?= $pagination['offset'] + 1 ?></span>-<span class="font-semibold text-gray-600"><?= min($pagination['offset'] + $perPage, $totalFiltered) ?></span> of <span class="font-semibold text-gray-600"><?= $totalFiltered ?></span> jobs</p>
                <div class="flex items-center gap-1">
                    <?php if ($pagination['has_prev']): ?>
                        <a href="?<?= buildQueryString(['page' => $page - 1]) ?>" class="w-9 h-9 flex items-center justify-center rounded-xl border border-gray-200 text-gray-500 hover:bg-gray-50 transition-all text-sm"><i class="fas fa-chevron-left text-xs"></i></a>
                    <?php endif; ?>
                    <?php
                    $startPage = max(1, $page - 2);
                    $endPage = min($pagination['total_pages'], $page + 2);
                    for ($i = $startPage; $i <= $endPage; $i++):
                        ?>
                        <a href="?<?= buildQueryString(['page' => $i]) ?>" class="w-9 h-9 flex items-center justify-center rounded-xl text-sm font-medium transition-all <?= $i === $page ? 'btn-grad text-white shadow-sm' : 'text-gray-500 hover:bg-gray-50' ?>"><?= $i ?></a>
                    <?php endfor; ?>
                    <?php if ($pagination['has_next']): ?>
                        <a href="?<?= buildQueryString(['page' => $page + 1]) ?>" class="w-9 h-9 flex items-center justify-center rounded-xl border border-gray-200 text-gray-500 hover:bg-gray-50 transition-all text-sm"><i class="fas fa-chevron-right text-xs"></i></a>
                    <?php endif; ?>
                </div>
            </div>
        <?php endif; ?>

    <?php else: ?>
        <div class="bg-white rounded-2xl border border-gray-100 shadow-sm fade-in" style="animation-delay:.4s">
            <div class="text-center py-16 px-6">
                <div class="w-24 h-24 rounded-3xl bg-gradient-to-br from-blue-50 to-cyan-50 flex items-center justify-center mx-auto mb-6 border border-blue-100"><i class="fas fa-briefcase text-4xl text-blue-300"></i></div>
                <h3 class="text-xl font-bold text-gray-900 mb-2">No jobs posted yet</h3>
                <p class="text-sm text-gray-400 mb-8 max-w-md mx-auto">Start building your team by posting your first job.</p>
                <a href="post_job.php" class="btn-grad inline-flex items-center gap-2.5 text-white font-bold px-8 py-3.5 rounded-xl text-sm shadow-lg shadow-blue-500/25"><i class="fas fa-plus text-xs"></i> Post Your First Job</a>
            </div>
        </div>
    <?php endif; ?>
<?php require_once __DIR__ . '/../components/layout_end.php'; ?>
<?php $conn->close(); ?>
