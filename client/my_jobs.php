<?php
session_start();
require_once '../config/db.php';

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

$querySql = "SELECT j.id, j.title, j.description, j.budget, j.status, j.created_at, j.is_featured, j.is_archived, j.job_type, j.category, j.project_duration, j.experience_level
             FROM jobs j $where
             ORDER BY COALESCE(j.is_featured, 0) DESC, j.created_at DESC
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

$pageTitle = 'My Jobs';
$pageSubtitle = 'Manage and track all your posted jobs';
$activePage = 'my_jobs';
$user = ['name' => $user['name'] ?? 'Client', 'profile_image' => $user['profile_image'] ?? null];
$unreadCount = get_unread_message_count($userId, 'client');
$profileLink = 'profile.php';
require_once __DIR__ . '/../includes/client_topbar.php';
?>
<?php display_flash('success');
display_flash('error'); ?>

<main class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8 sm:py-12">

    <!-- Page Header -->
    <div class="mb-8">
        <h1 class="text-2xl font-semibold text-gray-900 tracking-tight">My Jobs</h1>
        <p class="text-sm text-gray-500 mt-1">Manage and track all your posted jobs.</p>
    </div>

    <!-- ═══ STATS GRID ══════════════════════════════════════════════ -->
    <div class="grid grid-cols-2 lg:grid-cols-4 gap-4 mb-8">
        <?php
        $stats = [
            [
                'label' => 'Total Jobs',
                'key' => 'total',
                'icon' => 'briefcase',
                'iconBg' => 'bg-indigo-50',
                'iconColor' => 'text-indigo-600',
                'link' => 'my_jobs.php',
            ],
            [
                'label' => 'Open Jobs',
                'key' => 'open',
                'icon' => 'folder-open',
                'iconBg' => 'bg-emerald-50',
                'iconColor' => 'text-emerald-600',
                'link' => 'my_jobs.php?status=open',
            ],
            [
                'label' => 'In Progress',
                'key' => 'in_progress',
                'icon' => 'loader',
                'iconBg' => 'bg-amber-50',
                'iconColor' => 'text-amber-600',
                'link' => 'my_jobs.php?status=in_progress',
            ],
            [
                'label' => 'Completed',
                'key' => 'completed',
                'icon' => 'check-circle-2',
                'iconBg' => 'bg-blue-50',
                'iconColor' => 'text-blue-600',
                'link' => 'my_jobs.php?status=completed',
            ],
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
            <a href="<?= $stat['link'] ?>" class="group bg-white rounded-xl border border-gray-200 p-5 no-underline transition-all hover:shadow-sm">
                <div class="flex items-center justify-between mb-3">
                    <div class="w-9 h-9 rounded-lg <?= $stat['iconBg'] ?> flex items-center justify-center">
                        <i data-lucide="<?= $stat['icon'] ?>" class="w-4 h-4 <?= $stat['iconColor'] ?>"></i>
                    </div>
                    <i data-lucide="arrow-right" class="w-4 h-4 text-gray-300 group-hover:text-indigo-500 group-hover:translate-x-0.5 transition-all"></i>
                </div>
                <p class="text-2xl font-bold text-gray-900 tracking-tight m-0"><?= $cnt ?></p>
                <p class="text-xs text-gray-500 mt-1 m-0"><?= $stat['label'] ?></p>
            </a>
        <?php endforeach; ?>
    </div>

    <!-- ═══ SEARCH & FILTER ════════════════════════════════════════ -->
    <div class="bg-white rounded-xl border border-gray-200 p-4 mb-6">
        <form method="GET" class="flex flex-col sm:flex-row gap-3">
            <div class="relative flex-1">
                <i data-lucide="search" class="w-4 h-4 absolute left-3.5 top-1/2 -translate-y-1/2 text-gray-400"></i>
                <input type="text" name="search" value="<?= sanitize_string($search) ?>" placeholder="Search jobs by title..."
                    class="w-full bg-gray-50 border border-gray-200 rounded-lg pl-10 pr-4 py-2.5 text-sm text-gray-900 placeholder-gray-400 focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 outline-none transition-all">
            </div>
            <div class="relative sm:w-48">
                <i data-lucide="sliders-horizontal" class="w-4 h-4 absolute left-3.5 top-1/2 -translate-y-1/2 text-gray-400"></i>
                <select name="status" class="appearance-none w-full bg-gray-50 border border-gray-200 rounded-lg pl-10 pr-9 py-2.5 text-sm text-gray-600 focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 outline-none cursor-pointer transition-all">
                    <option value="all" <?= $statusFilter === 'all' ? 'selected' : '' ?>>All Status</option>
                    <option value="open" <?= $statusFilter === 'open' ? 'selected' : '' ?>>Open</option>
                    <option value="in_progress" <?= $statusFilter === 'in_progress' ? 'selected' : '' ?>>In Progress</option>
                    <option value="completed" <?= $statusFilter === 'completed' ? 'selected' : '' ?>>Completed</option>
                    <option value="cancelled" <?= $statusFilter === 'cancelled' ? 'selected' : '' ?>>Cancelled</option>
                    <option value="disputed" <?= $statusFilter === 'disputed' ? 'selected' : '' ?>>Disputed</option>
                </select>
                <i data-lucide="chevron-down" class="w-4 h-4 absolute right-3 top-1/2 -translate-y-1/2 text-gray-400 pointer-events-none"></i>
            </div>
            <button type="submit" class="px-6 py-2.5 bg-indigo-600 hover:bg-indigo-700 text-white text-sm font-medium rounded-lg transition-colors shrink-0 flex items-center gap-2">
                <i data-lucide="search" class="w-4 h-4"></i> Search
            </button>
            <?php if ($search !== '' || $statusFilter !== 'all'): ?>
                <a href="my_jobs.php" class="px-4 py-2.5 border border-gray-200 text-gray-600 hover:text-gray-900 hover:border-gray-300 rounded-lg text-sm font-medium transition-colors flex items-center gap-1.5 shrink-0">
                    <i data-lucide="x" class="w-4 h-4"></i> Clear
                </a>
            <?php endif; ?>
        </form>
    </div>

    <!-- ═══ JOB LIST ═══════════════════════════════════════════════ -->
    <?php if (!empty($jobs)): ?>
        <div class="space-y-3 mb-6">
            <?php foreach ($jobs as $index => $job):
                $badgeClass = match($job['status']) {
                    'open' => 'bg-emerald-50 text-emerald-700',
                    'in_progress' => 'bg-blue-50 text-blue-700',
                    'completed' => 'bg-purple-50 text-purple-700',
                    'cancelled' => 'bg-gray-100 text-gray-600',
                    'disputed' => 'bg-red-50 text-red-700',
                    default => 'bg-gray-100 text-gray-600',
                };
                ?>
                <div class="bg-white rounded-xl border border-gray-200 transition-all hover:shadow-sm hover:border-gray-300">
                    <div class="p-5 sm:p-6">
                        <div class="flex flex-col lg:flex-row lg:items-start gap-5">

                            <!-- Job Info -->
                            <div class="flex-1 min-w-0">
                                <!-- Title Row -->
                                <div class="flex flex-wrap items-center gap-2 mb-2">
                                    <a href="job_detail.php?id=<?= $job['id'] ?>" class="text-base font-semibold text-gray-900 hover:text-indigo-600 transition-colors"><?= decode_over_encoded($job['title']) ?></a>
                                    <?php if (!empty($job['is_featured'])): ?>
                                        <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-[10px] font-semibold bg-amber-50 text-amber-700"><i data-lucide="star" class="w-3 h-3 fill-amber-400"></i> Featured</span>
                                    <?php endif; ?>
                                    <?php if (!empty($job['is_archived'])): ?>
                                        <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-[10px] font-semibold bg-gray-100 text-gray-500"><i data-lucide="archive" class="w-3 h-3"></i> Archived</span>
                                    <?php endif; ?>
                                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium <?= $badgeClass ?>"><?= ucfirst(str_replace('_', ' ', sanitize_string($job['status']))) ?></span>
                                </div>

                                <!-- Description -->
                                <p class="text-sm text-gray-500 leading-relaxed mb-3 line-clamp-2"><?= sanitize_string(mb_strimwidth($job['description'], 0, 150, '...')) ?></p>

                                <!-- Skills -->
                                <?php if (!empty($job['skills'])): ?>
                                    <div class="flex flex-wrap gap-1.5 mb-3">
                                        <?php foreach (array_slice($job['skills'], 0, 5) as $skill): ?>
                                            <span class="inline-flex items-center px-2 py-0.5 bg-gray-100 text-gray-600 rounded text-xs font-medium"><?= sanitize_string($skill) ?></span>
                                        <?php endforeach; ?>
                                        <?php if (count($job['skills']) > 5): ?>
                                            <span class="inline-flex items-center px-2 py-0.5 bg-gray-100 text-gray-400 rounded text-xs font-medium">+<?= count($job['skills']) - 5 ?></span>
                                        <?php endif; ?>
                                    </div>
                                <?php endif; ?>

                                <!-- Metadata Row -->
                                <div class="flex flex-wrap items-center gap-x-4 gap-y-1.5 text-xs text-gray-400">
                                    <span class="flex items-center gap-1"><i data-lucide="dollar-sign" class="w-3.5 h-3.5 text-emerald-500"></i><span class="font-semibold text-gray-700"><?= format_currency($job['budget']) ?></span></span>
                                    <span class="flex items-center gap-1"><i data-lucide="<?= $job['job_type'] === 'hourly' ? 'clock' : 'target' ?>" class="w-3.5 h-3.5 text-gray-400"></i><?= $job['job_type'] === 'hourly' ? 'Hourly' : 'Fixed' ?></span>
                                    <?php if (!empty($job['experience_level'])): ?>
                                        <span class="flex items-center gap-1"><i data-lucide="bar-chart-2" class="w-3.5 h-3.5 text-gray-400"></i><?= ucfirst(sanitize_string($job['experience_level'])) ?></span>
                                    <?php endif; ?>
                                    <?php if (!empty($job['category'])): ?>
                                        <span class="flex items-center gap-1"><i data-lucide="layers" class="w-3.5 h-3.5 text-gray-400"></i><?= sanitize_string($job['category']) ?></span>
                                    <?php endif; ?>
                                    <span class="flex items-center gap-1"><i data-lucide="calendar" class="w-3.5 h-3.5 text-gray-400"></i><?= date('M d, Y', strtotime($job['created_at'])) ?></span>
                                    <span class="flex items-center gap-1"><i data-lucide="file-text" class="w-3.5 h-3.5 text-gray-400"></i><?= $job['proposal_count'] ?> proposal<?= $job['proposal_count'] !== 1 ? 's' : '' ?></span>
                                </div>
                            </div>

                            <!-- Action Buttons -->
                            <div class="flex flex-wrap lg:flex-nowrap items-center gap-2 lg:flex-col lg:items-stretch shrink-0 lg:w-40">
                                <a href="job_detail.php?id=<?= $job['id'] ?>" class="w-full inline-flex items-center justify-center gap-1.5 px-4 py-2 bg-indigo-600 hover:bg-indigo-700 text-white text-xs font-medium rounded-lg transition-colors">
                                    <i data-lucide="eye" class="w-3.5 h-3.5"></i> View Details
                                </a>
                                <a href="edit_job.php?id=<?= $job['id'] ?>" class="w-full inline-flex items-center justify-center gap-1.5 px-4 py-2 bg-white border border-gray-200 text-gray-600 hover:text-gray-900 hover:border-gray-300 text-xs font-medium rounded-lg transition-colors">
                                    <i data-lucide="pencil" class="w-3.5 h-3.5"></i> Edit
                                </a>
                                <?php if ($job['status'] === 'open'): ?>
                                    <a href="job_detail.php?id=<?= $job['id'] ?>" class="w-full inline-flex items-center justify-center gap-1.5 px-4 py-2 bg-white border border-gray-200 text-gray-600 hover:text-gray-900 hover:border-gray-300 text-xs font-medium rounded-lg transition-colors">
                                        <i data-lucide="file-text" class="w-3.5 h-3.5"></i> Proposals
                                    </a>
                                <?php endif; ?>
                                <?php if ($job['status'] === 'open' || $job['status'] === 'cancelled'): ?>
                                    <form method="POST" action="delete_job.php" class="w-full inline" onsubmit="return confirm('Are you sure you want to delete this job?');">
                                        <?= csrf_field() ?>
                                        <input type="hidden" name="job_id" value="<?= $job['id'] ?>">
                                        <button type="submit" class="w-full inline-flex items-center justify-center gap-1.5 px-4 py-2 bg-white border border-gray-200 text-red-500 hover:text-red-700 hover:border-red-300 text-xs font-medium rounded-lg transition-colors">
                                            <i data-lucide="trash-2" class="w-3.5 h-3.5"></i> Delete
                                        </button>
                                    </form>
                                <?php endif; ?>
                            </div>

                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>

        <!-- Pagination -->
        <?php if ($pagination['total_pages'] > 1): ?>
            <div class="flex items-center justify-between bg-white rounded-xl p-4 border border-gray-200">
                <p class="text-xs text-gray-500">Showing <span class="font-medium text-gray-700"><?= $pagination['offset'] + 1 ?></span>–<span class="font-medium text-gray-700"><?= min($pagination['offset'] + $perPage, $totalFiltered) ?></span> of <span class="font-medium text-gray-700"><?= $totalFiltered ?></span> jobs</p>
                <div class="flex items-center gap-1">
                    <?php if ($pagination['has_prev']): ?>
                        <a href="?<?= buildQueryString(['page' => $page - 1]) ?>" class="w-8 h-8 flex items-center justify-center rounded-lg border border-gray-200 text-gray-500 hover:bg-gray-50 transition-colors text-sm">
                            <i data-lucide="chevron-left" class="w-4 h-4"></i>
                        </a>
                    <?php endif; ?>
                    <?php
                    $startPage = max(1, $page - 2);
                    $endPage = min($pagination['total_pages'], $page + 2);
                    for ($i = $startPage; $i <= $endPage; $i++):
                        ?>
                        <a href="?<?= buildQueryString(['page' => $i]) ?>" class="w-8 h-8 flex items-center justify-center rounded-lg text-sm font-medium transition-colors <?= $i === $page ? 'bg-indigo-600 text-white' : 'text-gray-600 hover:bg-gray-50' ?>"><?= $i ?></a>
                    <?php endfor; ?>
                    <?php if ($pagination['has_next']): ?>
                        <a href="?<?= buildQueryString(['page' => $page + 1]) ?>" class="w-8 h-8 flex items-center justify-center rounded-lg border border-gray-200 text-gray-500 hover:bg-gray-50 transition-colors text-sm">
                            <i data-lucide="chevron-right" class="w-4 h-4"></i>
                        </a>
                    <?php endif; ?>
                </div>
            </div>
        <?php endif; ?>

    <?php else: ?>

        <!-- Empty State -->
        <div class="bg-white rounded-xl border border-gray-200 p-12 text-center">
            <div class="w-14 h-14 rounded-xl bg-indigo-50 flex items-center justify-center mx-auto mb-4">
                <i data-lucide="briefcase" class="w-7 h-7 text-indigo-400"></i>
            </div>
            <h3 class="text-base font-semibold text-gray-900 mb-1">No jobs posted yet</h3>
            <p class="text-sm text-gray-500 mb-5 max-w-sm mx-auto">Start building your team by posting your first job.</p>
            <a href="post_job.php" class="inline-flex items-center gap-2 bg-indigo-600 hover:bg-indigo-700 text-white text-sm font-medium px-5 py-2.5 rounded-lg transition-colors">
                <i data-lucide="plus" class="w-4 h-4"></i> Post Your First Job
            </a>
        </div>

    <?php endif; ?>

</main>

<?php require_once __DIR__ . '/../includes/client_footer.php'; ?>
<?php $conn->close(); ?>