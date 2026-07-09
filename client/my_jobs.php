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
<main class="max-w-7xl mx-auto px-4 sm:px-6 py-8 flex flex-col gap-6">
    <div class="grid grid-cols-1 sm:grid-cols-2 xl:grid-cols-4 gap-4">
        <?php
        // Updated stats array configuration with explicit Tailwind classes matching image.png colors
        $stats = [
            [
                'label' => 'Total Jobs',
                'key' => 'total',
                'icon' => 'fa-briefcase',
                'bg' => 'bg-emerald-50/60 dark:bg-emerald-950/20',
                'border' => 'border-emerald-200 dark:border-emerald-800',
                'text' => 'text-emerald-700 dark:text-emerald-400',
                'footer_text' => 'text-emerald-600 dark:text-emerald-400'
            ],
            [
                'label' => 'Open Jobs',
                'key' => 'open',
                'icon' => 'fa-folder-open',
                'bg' => 'bg-indigo-50/60 dark:bg-indigo-950/20',
                'border' => 'border-indigo-200 dark:border-indigo-800',
                'text' => 'text-indigo-700 dark:text-indigo-400',
                'footer_text' => 'text-indigo-600 dark:text-indigo-400'
            ],
            [
                'label' => 'In Progress',
                'key' => 'in_progress',
                'icon' => 'fa-spinner',
                'bg' => 'bg-amber-50/60 dark:bg-amber-950/20',
                'border' => 'border-amber-200 dark:border-amber-800',
                'text' => 'text-amber-700 dark:text-amber-400',
                'footer_text' => 'text-amber-600 dark:text-amber-400'
            ],
            [
                'label' => 'Completed',
                'key' => 'completed',
                'icon' => 'fa-check-circle',
                'bg' => 'bg-sky-50/60 dark:bg-sky-950/20',
                'border' => 'border-sky-200 dark:border-sky-800',
                'text' => 'text-sky-700 dark:text-sky-400',
                'footer_text' => 'text-sky-600 dark:text-sky-400'
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
            <div class="stat-card <?= $stat['bg'] ?> <?= $stat['border'] ?> rounded-2xl border shadow-sm flex flex-col justify-between overflow-hidden fade-in" style="animation-delay:<?= $i * 0.1 ?>s">

                <!-- Main Content: Horizontal Alignment -->
                <div class="p-5 flex items-center gap-4">
                    <!-- White square icon box with subtle border -->
                    <div class="w-12 h-12 rounded-xl bg-white border border-gray-100 flex items-center justify-center flex-shrink-0 shadow-sm dark:bg-gray-800 dark:border-gray-700">
                        <i class="fas <?= $stat['icon'] ?> text-lg <?= $stat['text'] ?>"></i>
                    </div>

                    <!-- Stat Numbers and Label Stacked -->
                    <div class="flex flex-col">
                        <span class="text-2xl font-black text-gray-900 dark:text-white leading-tight"><?= $cnt ?></span>
                        <span class="text-xs text-gray-500 font-medium dark:text-gray-400 mt-0.5"><?= $stat['label'] ?></span>
                    </div>
                </div>

                <!-- Footer: Bottom Action Ribbon with Border -->
                <div class="border-t border-black/5 dark:border-white/5 px-5 py-2.5 flex items-center justify-between bg-black/[0.01] dark:bg-white/[0.01]">
                    <span class="text-xs font-bold <?= $stat['footer_text'] ?>">See Details</span>
                    <i class="fas fa-arrow-right text-xs cursor-pointer <?= $stat['text'] ?> opacity-80"></i>
                </div>

            </div>
        <?php
        endforeach;
        ?>
    </div>

    <div>
        <form method="GET" class="flex flex-col sm:flex-row gap-3">
            <div class="relative flex-1">
                <i class="fas fa-search absolute left-4 top-1/2 -translate-y-1/2 text-gray-400 text-sm"></i>
                <input type="text" name="search" value="<?= sanitize_string($search) ?>" placeholder="Search jobs by title..." class="w-full bg-gray-50 border border-gray-200 rounded-xl pl-11 pr-4 py-2.5 text-sm text-gray-900 placeholder-gray-400 focus:outline-none focus:border-blue-500 focus:ring-2 focus:ring-blue-500/10 transition-all dark:bg-gray-700 dark:border-gray-600 dark:text-white dark:placeholder-gray-400">
            </div>
            <div class="relative">
                <i class="fas fa-filter absolute left-4 top-1/2 -translate-y-1/2 text-gray-400 text-sm"></i>
                <select name="status" class="appearance-none bg-gray-50 border border-gray-200 rounded-xl pl-11 pr-10 py-2.5 text-sm text-gray-700 focus:outline-none focus:border-blue-500 focus:ring-2 focus:ring-blue-500/10 transition-all cursor-pointer dark:bg-gray-700 dark:border-gray-600 dark:text-gray-300">
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
                <a href="my_jobs.php" class="px-4 py-2.5 border border-gray-200 text-gray-600 hover:text-gray-900 hover:border-gray-300 rounded-xl text-sm font-medium transition-all flex items-center gap-2 justify-center dark:border-gray-600 dark:text-gray-400 dark:hover:text-white dark:hover:border-gray-500"><i class="fas fa-times text-xs"></i> Clear</a>
            <?php endif; ?>
        </form>
    </div>

    <?php if (!empty($jobs)): ?>
        <div class="space-y-4">
            <?php foreach ($jobs as $index => $job): ?>
                <div class="bg-white rounded-2xl border border-gray-100 shadow-sm hover:shadow-md transition-all fade-in dark:bg-gray-800 dark:border-gray-700" style="animation-delay:<?= 0.4 + ($index * 0.05) ?>s">
                    <div class="p-6">
                        <div class="flex flex-col lg:flex-row lg:items-start gap-4">
                            <div class="flex-1 min-w-0">
                                <div class="flex flex-wrap items-center gap-2 mb-2">
                                    <a href="job_detail.php?id=<?= $job['id'] ?>" class="text-base font-bold text-gray-900 hover:text-blue-600 transition-colors dark:text-white dark:hover:text-blue-400"><?= sanitize_string($job['title']) ?></a>
                                    <span class="inline-block px-2.5 py-1 rounded-lg text-[11px] font-semibold <?= $statusColors[$job['status']] ?? 'bg-gray-100 text-gray-600 border border-gray-200' ?>"><?= ucfirst(str_replace('_', ' ', sanitize_string($job['status']))) ?></span>
                                </div>
                                <p class="text-sm text-gray-500 leading-relaxed mb-3 line-clamp-2 dark:text-gray-400"><?= sanitize_string(mb_strimwidth($job['description'], 0, 150, '...')) ?></p>
                                <?php if (!empty($job['skills'])): ?>
                                    <div class="flex flex-wrap gap-1.5 mb-3">
                                        <?php foreach ($job['skills'] as $skill): ?>
                                            <span class="inline-block px-2.5 py-1 bg-blue-50 text-blue-600 text-[11px] font-medium rounded-lg border border-blue-100 dark:bg-blue-900/30 dark:text-blue-400 dark:border-blue-800"><?= sanitize_string($skill) ?></span>
                                        <?php endforeach; ?>
                                    </div>
                                <?php endif; ?>
                                <div class="flex flex-wrap items-center gap-4 text-xs text-gray-400">
                                    <span class="flex items-center gap-1.5"><i class="fas fa-dollar-sign text-emerald-500"></i><span class="font-semibold text-gray-700 dark:text-gray-300"><?= format_currency($job['budget']) ?></span></span>
                                    <span class="flex items-center gap-1.5"><i class="fas fa-calendar text-blue-400"></i><?= date('M d, Y', strtotime($job['created_at'])) ?></span>
                                    <span class="flex items-center gap-1.5"><i class="fas fa-file-alt text-violet-400"></i><span class="font-semibold text-gray-600 dark:text-gray-400"><?= $job['proposal_count'] ?></span> proposal<?= $job['proposal_count'] !== 1 ? 's' : '' ?></span>
                                </div>
                            </div>
                            <div class="flex flex-wrap lg:flex-nowrap items-center gap-2 lg:flex-col lg:items-stretch">
                                <a href="job_detail.php?id=<?= $job['id'] ?>" class="inline-flex items-center gap-2 px-4 py-2 bg-blue-50 hover:bg-blue-100 text-blue-600 text-xs font-semibold rounded-xl transition-all dark:bg-blue-900/30 dark:hover:bg-blue-900/50 dark:text-blue-400"><i class="fas fa-eye text-[10px]"></i> View Details</a>
                                <a href="edit_job.php?id=<?= $job['id'] ?>" class="inline-flex items-center gap-2 px-4 py-2 bg-gray-50 hover:bg-gray-100 text-gray-600 text-xs font-semibold rounded-xl transition-all dark:bg-gray-700 dark:hover:bg-gray-600 dark:text-gray-400"><i class="fas fa-pen text-[10px]"></i> Edit Job</a>
                                <?php if ($job['status'] === 'open'): ?>
                                    <a href="job_detail.php?id=<?= $job['id'] ?>" class="inline-flex items-center gap-2 px-4 py-2 bg-emerald-50 hover:bg-emerald-100 text-emerald-600 text-xs font-semibold rounded-xl transition-all dark:bg-emerald-900/30 dark:hover:bg-emerald-900/50 dark:text-emerald-400"><i class="fas fa-file-alt text-[10px]"></i> View Proposals</a>
                                <?php endif; ?>
                                <?php if ($job['status'] === 'open' || $job['status'] === 'cancelled'): ?>
                                    <form method="POST" action="delete_job.php" class="inline" onsubmit="return confirm('Are you sure you want to delete this job?');">
                                        <?= csrf_field() ?>
                                        <input type="hidden" name="job_id" value="<?= $job['id'] ?>">
                                        <button type="submit" class="inline-flex items-center gap-2 px-4 py-2 bg-red-50 hover:bg-red-100 text-red-500 text-xs font-semibold rounded-xl transition-all dark:bg-red-900/30 dark:hover:bg-red-900/50 dark:text-red-400"><i class="fas fa-trash text-[10px]"></i> Delete</button>
                                    </form>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>

        <?php if ($pagination['total_pages'] > 1): ?>
            <div class="flex items-center justify-between bg-white rounded-2xl p-4 border border-gray-100 shadow-sm fade-in dark:bg-gray-800 dark:border-gray-700">
                <p class="text-xs text-gray-400">Showing <span class="font-semibold text-gray-600 dark:text-gray-300"><?= $pagination['offset'] + 1 ?></span>-<span class="font-semibold text-gray-600 dark:text-gray-300"><?= min($pagination['offset'] + $perPage, $totalFiltered) ?></span> of <span class="font-semibold text-gray-600 dark:text-gray-300"><?= $totalFiltered ?></span> jobs</p>
                <div class="flex items-center gap-1">
                    <?php if ($pagination['has_prev']): ?>
                        <a href="?<?= buildQueryString(['page' => $page - 1]) ?>" class="w-9 h-9 flex items-center justify-center rounded-xl border border-gray-200 text-gray-500 hover:bg-gray-50 transition-all text-sm dark:border-gray-600 dark:text-gray-400 dark:hover:bg-gray-700"><i class="fas fa-chevron-left text-xs"></i></a>
                    <?php endif; ?>
                    <?php
                    $startPage = max(1, $page - 2);
                    $endPage = min($pagination['total_pages'], $page + 2);
                    for ($i = $startPage; $i <= $endPage; $i++):
                        ?>
                        <a href="?<?= buildQueryString(['page' => $i]) ?>" class="w-9 h-9 flex items-center justify-center rounded-xl text-sm font-medium transition-all <?= $i === $page ? 'btn-grad text-white shadow-sm' : 'text-gray-500 hover:bg-gray-50' ?>"><?= $i ?></a>
                    <?php endfor; ?>
                    <?php if ($pagination['has_next']): ?>
                        <a href="?<?= buildQueryString(['page' => $page + 1]) ?>" class="w-9 h-9 flex items-center justify-center rounded-xl border border-gray-200 text-gray-500 hover:bg-gray-50 transition-all text-sm dark:border-gray-600 dark:text-gray-400 dark:hover:bg-gray-700"><i class="fas fa-chevron-right text-xs"></i></a>
                    <?php endif; ?>
                </div>
            </div>
        <?php endif; ?>

    <?php else: ?>
        <div class="bg-white rounded-2xl border border-gray-100 shadow-sm fade-in dark:bg-gray-800 dark:border-gray-700" style="animation-delay:.4s">
            <div class="text-center py-16 px-6">
                <div class="w-24 h-24 rounded-3xl bg-gradient-to-br from-blue-50 to-cyan-50 flex items-center justify-center mx-auto mb-6 border border-blue-100 dark:from-blue-900/30 dark:to-cyan-900/30 dark:border-blue-800"><i class="fas fa-briefcase text-4xl text-blue-300 dark:text-blue-500"></i></div>
                <h3 class="text-xl font-bold text-gray-900 dark:text-white mb-2">No jobs posted yet</h3>
                <p class="text-sm text-gray-400 mb-8 max-w-md mx-auto">Start building your team by posting your first job.</p>
                <a href="post_job.php" class="btn-grad inline-flex items-center gap-2.5 text-white font-bold px-8 py-3.5 rounded-xl text-sm shadow-lg shadow-blue-500/25"><i class="fas fa-plus text-xs"></i> Post Your First Job</a>
            </div>
        </div>
    <?php endif; ?>
</main>
<?php require_once __DIR__ . '/../includes/client_footer.php'; ?>
<?php $conn->close(); ?>