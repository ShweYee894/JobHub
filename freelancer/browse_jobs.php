<?php
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../auth/auth.php';
require_role('freelancer');

$userId = $_SESSION['user_id'];

$stmt = $conn->prepare('SELECT name, profile_image FROM users WHERE id = ?');
$stmt->bind_param('i', $userId);
$stmt->execute();
$user = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (
    $_SERVER['REQUEST_METHOD'] === 'POST' &&
    ($_POST['action'] ?? '') === 'submit_proposal'
) {
    $jobId = intval($_POST['job_id'] ?? 0);
    $amount = floatval($_POST['amount'] ?? 0);
    $proposal = trim($_POST['proposal_text'] ?? '');

    // Validation
    if ($jobId <= 0) {
        set_flash('error', 'Invalid job.');
        redirect('/finalproject/freelancer/browse_jobs.php');
    }

    if ($amount <= 0) {
        set_flash('error', 'Please enter a valid bid amount.');
        redirect('/finalproject/freelancer/job_detail.php?id=' . $jobId);
    }

    if (strlen($proposal) < 20) {
        set_flash('error', 'Proposal must contain at least 20 characters.');
        redirect('/finalproject/freelancer/job_detail.php?id=' . $jobId);
    }

    // Check duplicate proposal
    $check = $conn->prepare('
        SELECT id
        FROM proposals
        WHERE job_id = ?
        AND freelancer_id = ?
    ');

    $check->bind_param('ii', $jobId, $userId);
    $check->execute();
    $exists = $check->get_result()->fetch_assoc();
    $check->close();

    if ($exists) {
        set_flash('error', 'You have already submitted a proposal.');
        redirect('/finalproject/freelancer/job_detail.php?id=' . $jobId);
    }

    // Verify job exists and is open
    $jobCheck = $conn->prepare('SELECT id, status FROM jobs WHERE id = ?');
    $jobCheck->bind_param('i', $jobId);
    $jobCheck->execute();
    $jobRow = $jobCheck->get_result()->fetch_assoc();
    $jobCheck->close();
    if (!$jobRow) {
        set_flash('error', 'Job not found.');
        redirect('/finalproject/freelancer/browse_jobs.php');
    }
    if ($jobRow['status'] !== 'open') {
        set_flash('error', 'This job is no longer accepting proposals.');
        redirect('/finalproject/freelancer/job_detail.php?id=' . $jobId);
    }

    // Insert proposal
    $stmt = $conn->prepare("
        INSERT INTO proposals
        (
            job_id,
            freelancer_id,
            amount,
            proposal_text,
            status,
            created_at
        )
        VALUES
        (?, ?, ?, ?, 'pending', NOW())
    ");

    $stmt->bind_param(
        'iids',
        $jobId,
        $userId,
        $amount,
        $proposal
    );

    if ($stmt->execute()) {
        set_flash('success', 'Proposal submitted successfully.');
    } else {
        set_flash('error', 'Failed to submit proposal.');
    }

    $stmt->close();

    redirect('/finalproject/freelancer/job_detail.php?id=' . $jobId);
}

// ── Sanitize filters ──────────────────────────────────────────────────────
$search = trim($_GET['search'] ?? '');
$budgetMin = max(0, floatval($_GET['budget_min'] ?? 0));
$budgetMax = max(0, floatval($_GET['budget_max'] ?? 0));
$statusFilter = $_GET['status'] ?? 'open';
$skillIds = array_filter(array_map('intval', $_GET['skills'] ?? []));
$datePosted = $_GET['date_posted'] ?? 'all';
$sortBy = $_GET['sort'] ?? 'newest';
$page = max(1, intval($_GET['page'] ?? 1));
$perPage = 12;

$allowedStatuses = ['open', 'in_progress', 'completed', 'disputed', 'cancelled', 'all'];
if (!in_array($statusFilter, $allowedStatuses))
    $statusFilter = 'open';

$allowedSort = ['newest', 'oldest', 'budget_high', 'budget_low'];
if (!in_array($sortBy, $allowedSort))
    $sortBy = 'newest';

$allowedDates = ['all', '24h', 'week', 'month'];
if (!in_array($datePosted, $allowedDates))
    $datePosted = 'all';

// ── Build query conditions ────────────────────────────────────────────────
$where = [];
$params = [];
$types = '';

if ($statusFilter !== 'all') {
    $where[] = 'j.status = ?';
    $params[] = $statusFilter;
    $types .= 's';
} else {
    $where[] = "j.status != 'cancelled'";
}

if ($budgetMin > 0) {
    $where[] = 'j.budget >= ?';
    $params[] = $budgetMin;
    $types .= 'd';
}

if ($budgetMax > 0) {
    $where[] = 'j.budget <= ?';
    $params[] = $budgetMax;
    $types .= 'd';
}

if ($search !== '') {
    $safeSearch = preg_replace('/[^\w\s\-\+]/', '', $search);
    $safeSearch = trim($safeSearch);
    if ($safeSearch !== '') {
        $searchTerms = implode(' ', array_map(function ($t) {
            return '+' . $t;
        }, explode(' ', $safeSearch)));
        $where[] = 'MATCH(j.title, j.description) AGAINST(? IN BOOLEAN MODE)';
        $params[] = $searchTerms;
        $types .= 's';
    }
}

$dateCondition = '';
if ($datePosted !== 'all') {
    $intervals = ['24h' => '-1 DAY', 'week' => '-1 WEEK', 'month' => '-1 MONTH'];
    $dateCondition = $intervals[$datePosted];
}

if (!empty($skillIds)) {
    $placeholders = implode(',', array_fill(0, count($skillIds), '?'));
    $where[] = "j.id IN (SELECT js.job_id FROM job_skills js WHERE js.skill_id IN ($placeholders) GROUP BY js.job_id HAVING COUNT(DISTINCT js.skill_id) = " . count($skillIds) . ')';
    $params = array_merge($params, $skillIds);
    $types .= str_repeat('i', count($skillIds));
}

$allSkills = [];
$skillResult = $conn->query('SELECT id, skill_name, category FROM skills ORDER BY category, skill_name');
while ($sk = $skillResult->fetch_assoc()) {
    $allSkills[$sk['id']] = $sk;
}

$skillColors = [
    'Frontend' => ['bg' => 'bg-blue-50', 'text' => 'text-blue-600', 'border' => 'border-blue-100'],
    'Backend' => ['bg' => 'bg-emerald-50', 'text' => 'text-emerald-600', 'border' => 'border-emerald-100'],
    'Database' => ['bg' => 'bg-amber-50', 'text' => 'text-amber-600', 'border' => 'border-amber-100'],
    'DevOps' => ['bg' => 'bg-violet-50', 'text' => 'text-violet-600', 'border' => 'border-violet-100'],
    'Design' => ['bg' => 'bg-pink-50', 'text' => 'text-pink-600', 'border' => 'border-pink-100'],
    'Mobile' => ['bg' => 'bg-cyan-50', 'text' => 'text-cyan-600', 'border' => 'border-cyan-100'],
    'Data Science' => ['bg' => 'bg-rose-50', 'text' => 'text-rose-600', 'border' => 'border-rose-100'],
    'General' => ['bg' => 'bg-gray-50', 'text' => 'text-gray-600', 'border' => 'border-gray-100'],
];

$defaultColor = ['bg' => 'bg-gray-50', 'text' => 'text-gray-600', 'border' => 'border-gray-100'];

function getSkillColor(string $category, array $skillColors, array $default): array
{
    return $skillColors[$category] ?? $default;
}

// ── Count total ───────────────────────────────────────────────────────────
$whereSQL = implode(' AND ', $where);
$countSql = "SELECT COUNT(DISTINCT j.id) AS total FROM jobs j WHERE $whereSQL";
if ($dateCondition !== '') {
    $countSql .= " AND j.created_at >= DATE_ADD(NOW(), INTERVAL $dateCondition)";
}

$countStmt = $conn->prepare($countSql);
if ($types !== '') {
    $countStmt->bind_param($types, ...$params);
}
$countStmt->execute();
$totalFiltered = $countStmt->get_result()->fetch_assoc()['total'];
$countStmt->close();

$pagination = paginate($totalFiltered, $perPage, $page);

// ── Sort order ────────────────────────────────────────────────────────────
$orderMap = [
    'newest' => 'j.created_at DESC',
    'oldest' => 'j.created_at ASC',
    'budget_high' => 'j.budget DESC',
    'budget_low' => 'j.budget ASC',
];
$orderBy = $orderMap[$sortBy] ?? 'j.created_at DESC';

// ── Fetch jobs ────────────────────────────────────────────────────────────
$querySql = "SELECT j.id, j.title, j.description, j.budget, j.status, j.created_at,
             u.name AS client_name,
             (SELECT COUNT(*) FROM proposals WHERE job_id = j.id) AS proposal_count
             FROM jobs j
              JOIN clients c ON j.client_id = c.client_id
             JOIN users u ON c.client_id = u.id
             WHERE $whereSQL";
if ($dateCondition !== '') {
    $querySql .= " AND j.created_at >= DATE_ADD(NOW(), INTERVAL $dateCondition)";
}
$querySql .= " ORDER BY $orderBy LIMIT ? OFFSET ?";

$finalTypes = $types . 'ii';
$finalParams = array_merge($params, [$pagination['per_page'], $pagination['offset']]);

$stmt = $conn->prepare($querySql);
$stmt->bind_param($finalTypes, ...$finalParams);
$stmt->execute();
$jobsResult = $stmt->get_result();
$stmt->close();

$jobIds = [];
$jobs = [];
while ($row = $jobsResult->fetch_assoc()) {
    $jobIds[] = $row['id'];
    $row['skills'] = [];
    $row['has_proposed'] = false;
    $jobs[$row['id']] = $row;
}

if (!empty($jobIds)) {
    $jidPlaceholders = implode(',', array_fill(0, count($jobIds), '?'));
    $jidTypes = str_repeat('i', count($jobIds));

    $skillStmt = $conn->prepare(
        "SELECT js.job_id, s.id AS skill_id, s.skill_name, s.category
         FROM job_skills js
         JOIN skills s ON js.skill_id = s.id
         WHERE js.job_id IN ($jidPlaceholders)
         ORDER BY s.skill_name"
    );
    $skillStmt->bind_param($jidTypes, ...$jobIds);
    $skillStmt->execute();
    $skillRes = $skillStmt->get_result();
    while ($sr = $skillRes->fetch_assoc()) {
        if (isset($jobs[$sr['job_id']])) {
            $jobs[$sr['job_id']]['skills'][] = $sr;
        }
    }
    $skillStmt->close();

    $propStmt = $conn->prepare(
        "SELECT job_id FROM proposals WHERE job_id IN ($jidPlaceholders) AND freelancer_id = ?"
    );
    $propParams = array_merge($jobIds, [$userId]);
    $propStmt->bind_param($jidTypes . 'i', ...$propParams);
    $propStmt->execute();
    $propRes = $propStmt->get_result();
    while ($pr = $propRes->fetch_assoc()) {
        if (isset($jobs[$pr['job_id']])) {
            $jobs[$pr['job_id']]['has_proposed'] = true;
        }
    }
    $propStmt->close();
}

// ── Build base URL for pagination ─────────────────────────────────────────
function buildBaseUrl(array $overrides = []): string
{
    $params = array_merge($_GET, $overrides);
    unset($params['page']);
    $qs = http_build_query(array_filter($params, fn($v) => $v !== '' && $v !== null));
    return '/finalproject/freelancer/browse_jobs.php' . ($qs ? '?' . $qs : '');
}

$baseUrl = buildBaseUrl();

$statusColors = [
    'open' => 'bg-emerald-50 text-emerald-600 border border-emerald-200',
    'in_progress' => 'bg-blue-50 text-blue-600 border border-blue-200',
    'completed' => 'bg-gray-100 text-gray-600 border border-gray-200',
    'disputed' => 'bg-red-50 text-red-600 border border-red-200',
    'cancelled' => 'bg-gray-100 text-gray-500 border border-gray-200',
];

$pageTitle = 'Browse Jobs';
$pageSubtitle = 'Find new opportunities and submit proposals';
$activePage = 'browse_jobs';
$user = ['name' => $user['name'] ?? 'Freelancer', 'profile_image' => $user['profile_image'] ?? null];
$unreadCount = get_unread_message_count($userId, 'freelancer');
require_once __DIR__ . '/../components/freelancer_header.php';
$conn->close();

?>

                <form method="GET" id="filterForm" class="space-y-6 max-w-7xl mx-auto px-4 sm:px-6 py-8">

                    <!-- ═══ SEARCH BAR ═══════════════════════════════════ -->
                    <div class="bg-white rounded-2xl p-5 border border-gray-100 shadow-sm fade-in dark:bg-gray-800 dark:border-gray-700">
                        <div class="flex flex-col sm:flex-row gap-3">
                            <div class="relative flex-1">
                                <i class="fas fa-search absolute left-4 top-1/2 -translate-y-1/2 text-gray-400 text-sm"></i>
                                <input type="text" name="search" value="<?= sanitize_string($search) ?>"
                                    placeholder="Search jobs by title, description, or skills..."
                                    class="fld w-full bg-gray-50 border border-gray-200 rounded-xl pl-11 pr-4 py-3 text-sm text-gray-900 placeholder-gray-400">
                            </div>
                            <button type="submit" class="btn-grad px-8 py-3 text-white text-sm font-semibold rounded-xl flex items-center justify-center gap-2 shadow-sm shadow-blue-500/25">
                                <i class="fas fa-search text-xs"></i> Search
                            </button>
                            <?php if ($search !== '' || $budgetMin > 0 || $budgetMax > 0 || $statusFilter !== 'open' || $datePosted !== 'all' || !empty($skillIds)): ?>
                                <a href="browse_jobs.php" class="px-5 py-3 border border-gray-200 text-gray-600 hover:text-gray-900 rounded-xl text-sm font-medium transition-all flex items-center justify-center gap-2">
                                    <i class="fas fa-times text-xs"></i> Clear All
                                </a>
                            <?php endif; ?>
                        </div>
                    </div>

                    <div class="flex gap-6">

                        <!-- ═══ FILTER SIDEBAR ════════════════════════════ -->
                        <aside id="filterPanel" class="hidden lg:block w-72 flex-shrink-0 space-y-4">

                            <!-- Status Filter -->
                            <div class="bg-white rounded-2xl p-5 border border-gray-100 shadow-sm fade-in dark:bg-gray-800 dark:border-gray-700">
                                <h3 class="text-sm font-bold text-gray-900 mb-3 flex items-center gap-2 dark:text-white">
                                    <i class="fas fa-circle-dot text-blue-500 text-xs"></i> Status
                                </h3>
                                <div class="space-y-2">
                                    <?php
                                    $statusOptions = [
                                        'open' => ['Open Jobs', 'fa-circle-check', 'text-emerald-500'],
                                        'all' => ['All Statuses', 'fa-layer-group', 'text-gray-500'],
                                        'in_progress' => ['In Progress', 'fa-spinner', 'text-blue-500'],
                                        'completed' => ['Completed', 'fa-check-double', 'text-gray-400'],
                                        'disputed' => ['Disputed', 'fa-exclamation-triangle', 'text-red-500'],
                                    ];
                                    foreach ($statusOptions as $val => $info):
                                        ?>
                                        <label class="flex items-center gap-2.5 cursor-pointer group p-2 rounded-lg hover:bg-gray-50 transition-colors dark:hover:bg-gray-700 <?= $statusFilter === $val ? 'bg-blue-50' : '' ?>">
                                            <input type="radio" name="status" value="<?= $val ?>" <?= $statusFilter === $val ? 'checked' : '' ?>
                                                class="w-4 h-4 text-blue-600 border-gray-300 focus:ring-blue-500" onchange="this.form.submit()">
                                            <i class="fas <?= $info[1] ?> <?= $info[2] ?> text-xs"></i>
                                            <span class="text-sm <?= $statusFilter === $val ? 'font-semibold text-gray-900 dark:text-white' : 'text-gray-600' ?> group-hover:text-gray-900 transition-colors"><?= $info[0] ?></span>
                                        </label>
                                    <?php endforeach; ?>
                                </div>
                            </div>

                            <!-- Budget Range -->
                            <div class="bg-white rounded-2xl p-5 border border-gray-100 shadow-sm fade-in dark:bg-gray-800 dark:border-gray-700" style="animation-delay:.1s">
                                <h3 class="text-sm font-bold text-gray-900 mb-3 flex items-center gap-2 dark:text-white">
                                    <i class="fas fa-dollar-sign text-emerald-500 text-xs"></i> Budget Range
                                </h3>
                                <div class="flex items-center gap-2">
                                    <div class="relative flex-1">
                                        <span class="absolute left-3 top-1/2 -translate-y-1/2 text-gray-400 text-xs">$</span>
                                        <input type="number" name="budget_min" value="<?= $budgetMin > 0 ? $budgetMin : '' ?>"
                                            placeholder="Min" min="0" step="1"
                                            class="fld w-full bg-gray-50 border border-gray-200 rounded-xl pl-7 pr-3 py-2.5 text-sm text-gray-900 placeholder-gray-400">
                                    </div>
                                    <span class="text-gray-300 font-medium">–</span>
                                    <div class="relative flex-1">
                                        <span class="absolute left-3 top-1/2 -translate-y-1/2 text-gray-400 text-xs">$</span>
                                        <input type="number" name="budget_max" value="<?= $budgetMax > 0 ? $budgetMax : '' ?>"
                                            placeholder="Max" min="0" step="1"
                                            class="fld w-full bg-gray-50 border border-gray-200 rounded-xl pl-7 pr-3 py-2.5 text-sm text-gray-900 placeholder-gray-400">
                                    </div>
                                </div>
                                <button type="submit" class="mt-3 w-full py-2 bg-gray-100 hover:bg-gray-200 text-gray-700 text-xs font-semibold rounded-lg transition-colors dark:bg-gray-700 dark:hover:bg-gray-600 dark:text-gray-300">
                                    Apply Budget
                                </button>
                            </div>

                            <!-- Skills Filter (grouped by category) -->
                            <div class="bg-white rounded-2xl p-5 border border-gray-100 shadow-sm fade-in dark:bg-gray-800 dark:border-gray-700" style="animation-delay:.2s">
                                <h3 class="text-sm font-bold text-gray-900 mb-3 flex items-center gap-2 dark:text-white">
                                    <i class="fas fa-tags text-violet-500 text-xs"></i> Skills
                                </h3>
                                <div class="space-y-4 max-h-80 overflow-y-auto pr-1 custom-scrollbar">
                                    <?php
                                    $grouped = [];
                                    foreach ($allSkills as $skill) {
                                        $cat = $skill['category'] ?: 'General';
                                        $grouped[$cat][] = $skill;
                                    }
                                    foreach ($grouped as $category => $skills):
                                        $col = getSkillColor($category, $skillColors, $defaultColor);
                                        ?>
                                        <div>
                                            <p class="text-[10px] font-bold uppercase tracking-wider <?= $col['text'] ?> mb-2"><?= sanitize_string($category) ?></p>
                                            <div class="space-y-1.5">
                                                <?php foreach ($skills as $skill): ?>
                                                    <label class="flex items-center gap-2 cursor-pointer group p-1.5 rounded-lg hover:bg-gray-50 transition-colors">
                                                        <input type="checkbox" name="skills[]" value="<?= $skill['id'] ?>"
                                                            <?= in_array($skill['id'], $skillIds) ? 'checked' : '' ?>
                                                            class="w-3.5 h-3.5 text-blue-600 border-gray-300 rounded focus:ring-blue-500">
                                                        <span class="text-xs text-gray-600 group-hover:text-gray-900 transition-colors"><?= sanitize_string($skill['skill_name']) ?></span>
                                                    </label>
                                                <?php endforeach; ?>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                                <button type="submit" class="mt-3 w-full py-2 bg-gray-100 hover:bg-gray-200 text-gray-700 text-xs font-semibold rounded-lg transition-colors">
                                    Apply Skills
                                </button>
                            </div>

                            <!-- Date Posted -->
                            <div class="bg-white rounded-2xl p-5 border border-gray-100 shadow-sm fade-in dark:bg-gray-800 dark:border-gray-700" style="animation-delay:.3s">
                                <h3 class="text-sm font-bold text-gray-900 mb-3 flex items-center gap-2 dark:text-white">
                                    <i class="fas fa-calendar text-cyan-500 text-xs"></i> Date Posted
                                </h3>
                                <div class="space-y-2">
                                    <?php
                                    $dateOptions = [
                                        'all' => 'All Time',
                                        '24h' => 'Last 24 Hours',
                                        'week' => 'Last Week',
                                        'month' => 'Last Month',
                                    ];
                                    foreach ($dateOptions as $val => $label):
                                        ?>
                                        <label class="flex items-center gap-2.5 cursor-pointer group p-2 rounded-lg hover:bg-gray-50 transition-colors <?= $datePosted === $val ? 'bg-blue-50' : '' ?>">
                                            <input type="radio" name="date_posted" value="<?= $val ?>" <?= $datePosted === $val ? 'checked' : '' ?>
                                                class="w-4 h-4 text-blue-600 border-gray-300 focus:ring-blue-500" onchange="this.form.submit()">
                                            <span class="text-sm <?= $datePosted === $val ? 'font-semibold text-gray-900' : 'text-gray-600' ?> group-hover:text-gray-900 transition-colors"><?= $label ?></span>
                                        </label>
                                    <?php endforeach; ?>
                                </div>
                            </div>

                            <!-- Hidden fields to preserve state -->
                            <input type="hidden" name="sort" value="<?= sanitize_string($sortBy) ?>">
                        </aside>

                        <!-- Mobile filter drawer -->
                        <div id="mobileFilter" class="fixed inset-y-0 left-0 z-40 w-80 bg-white shadow-2xl transform -translate-x-full lg:hidden overflow-y-auto dark:bg-gray-800">
                            <div class="p-5 border-b border-gray-100 flex items-center justify-between sticky top-0 bg-white z-10 dark:bg-gray-800 dark:border-gray-700">
                                <h2 class="text-lg font-bold text-gray-900">Filters</h2>
                                <button onclick="document.getElementById('mobileFilter').classList.add('translate-x-full')" class="w-8 h-8 rounded-lg bg-gray-100 flex items-center justify-center text-gray-400 hover:text-gray-600">
                                    <i class="fas fa-times text-sm"></i>
                                </button>
                            </div>
                            <div class="p-5 space-y-4">
                                <!-- Status (mobile) -->
                                <div>
                                    <h3 class="text-sm font-bold text-gray-900 mb-3">Status</h3>
                                    <div class="space-y-2">
                                        <?php foreach ($statusOptions as $val => $info): ?>
                                            <label class="flex items-center gap-2.5 cursor-pointer p-2 rounded-lg hover:bg-gray-50">
                                                <input type="radio" name="status" value="<?= $val ?>" <?= $statusFilter === $val ? 'checked' : '' ?>
                                                    class="w-4 h-4 text-blue-600 border-gray-300 focus:ring-blue-500">
                                                <span class="text-sm text-gray-600"><?= $info[0] ?></span>
                                            </label>
                                        <?php endforeach; ?>
                                    </div>
                                </div>
                                <!-- Budget (mobile) -->
                                <div>
                                    <h3 class="text-sm font-bold text-gray-900 mb-3">Budget Range</h3>
                                    <div class="flex items-center gap-2">
                                        <input type="number" name="budget_min" value="<?= $budgetMin > 0 ? $budgetMin : '' ?>" placeholder="Min" min="0"
                                            class="fld flex-1 bg-gray-50 border border-gray-200 rounded-xl px-3 py-2.5 text-sm">
                                        <span class="text-gray-300">–</span>
                                        <input type="number" name="budget_max" value="<?= $budgetMax > 0 ? $budgetMax : '' ?>" placeholder="Max" min="0"
                                            class="fld flex-1 bg-gray-50 border border-gray-200 rounded-xl px-3 py-2.5 text-sm">
                                    </div>
                                </div>
                                <!-- Skills (mobile) -->
                                <div>
                                    <h3 class="text-sm font-bold text-gray-900 mb-3">Skills</h3>
                                    <div class="space-y-3 max-h-60 overflow-y-auto">
                                        <?php foreach ($grouped as $category => $skills): ?>
                                            <div>
                                                <p class="text-[10px] font-bold uppercase tracking-wider text-gray-400 mb-1.5"><?= sanitize_string($category) ?></p>
                                                <?php foreach ($skills as $skill): ?>
                                                    <label class="flex items-center gap-2 cursor-pointer p-1">
                                                        <input type="checkbox" name="skills[]" value="<?= $skill['id'] ?>" <?= in_array($skill['id'], $skillIds) ? 'checked' : '' ?>
                                                            class="w-3.5 h-3.5 text-blue-600 border-gray-300 rounded focus:ring-blue-500">
                                                        <span class="text-xs text-gray-600"><?= sanitize_string($skill['skill_name']) ?></span>
                                                    </label>
                                                <?php endforeach; ?>
                                            </div>
                                        <?php endforeach; ?>
                                    </div>
                                </div>
                                <!-- Date (mobile) -->
                                <div>
                                    <h3 class="text-sm font-bold text-gray-900 mb-3">Date Posted</h3>
                                    <div class="space-y-2">
                                        <?php foreach ($dateOptions as $val => $label): ?>
                                            <label class="flex items-center gap-2.5 cursor-pointer p-2 rounded-lg hover:bg-gray-50">
                                                <input type="radio" name="date_posted" value="<?= $val ?>" <?= $datePosted === $val ? 'checked' : '' ?>
                                                    class="w-4 h-4 text-blue-600 border-gray-300 focus:ring-blue-500">
                                                <span class="text-sm text-gray-600"><?= $label ?></span>
                                            </label>
                                        <?php endforeach; ?>
                                    </div>
                                </div>
                                <input type="hidden" name="sort" value="<?= sanitize_string($sortBy) ?>">
                                <input type="hidden" name="search" value="<?= sanitize_string($search) ?>">
                                <button type="submit" class="w-full btn-grad py-3 text-white text-sm font-semibold rounded-xl">Apply Filters</button>
                            </div>
                        </div>

                        <!-- ═══ JOB LISTING ═══════════════════════════════ -->
                        <div class="flex-1 min-w-0 space-y-5">

                            <!-- Results bar -->
                            <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3 fade-in">
                                <p class="text-sm text-gray-500">
                                    <span class="font-bold text-gray-900"><?= number_format($totalFiltered) ?></span> job<?= $totalFiltered !== 1 ? 's' : '' ?> found
                                    <?php if ($search !== ''): ?>
                                        for "<span class="font-semibold text-blue-600"><?= sanitize_string($search) ?></span>"
                                    <?php endif; ?>
                                </p>
                                <div class="flex items-center gap-2">
                                    <label class="text-xs text-gray-500 font-medium">Sort:</label>
                                    <select name="sort" onchange="this.form.submit()"
                                        class="appearance-none fld bg-white border border-gray-200 rounded-xl px-3 py-2 text-xs text-gray-700 cursor-pointer pr-8"
                                        style="background-image: url('data:image/svg+xml;utf8,<svg xmlns=\'http://www.w3.org/2000/svg\' fill=\'none\' viewBox=\'0 0 20 20\'><path stroke=\'%236b7280\' stroke-linecap=\'round\' stroke-linejoin=\'round\' stroke-width=\'1.5\' d=\'M6 8l4 4 4-4\'/></svg>'); background-position: right 0.5rem center; background-repeat: no-repeat; background-size: 1.5em 1.5em; padding-right: 2.5rem;">
                                        <option value="newest" <?= $sortBy === 'newest' ? 'selected' : '' ?>>Newest First</option>
                                        <option value="oldest" <?= $sortBy === 'oldest' ? 'selected' : '' ?>>Oldest First</option>
                                        <option value="budget_high" <?= $sortBy === 'budget_high' ? 'selected' : '' ?>>Budget: High to Low</option>
                                        <option value="budget_low" <?= $sortBy === 'budget_low' ? 'selected' : '' ?>>Budget: Low to High</option>
                                    </select>
                                </div>
                            </div>

                            <?php if (!empty($jobs)): ?>
                                <div class="space-y-4">
                                    <?php foreach ($jobs as $index => $job): ?>
                                        <div class="job-card bg-white rounded-2xl border border-gray-100 shadow-sm fade-in dark:bg-gray-800 dark:border-gray-700" style="animation-delay:<?= 0.05 + ($index * 0.04) ?>s">
                                            <div class="p-6">
                                                <div class="flex flex-col lg:flex-row lg:items-start gap-4">
                                                    <div class="flex-1 min-w-0">
                                                        <div class="flex flex-wrap items-center gap-2 mb-2">
                                                            <a href="job_detail.php?id=<?= $job['id'] ?>" class="text-base font-bold text-gray-900 hover:text-blue-600 transition-colors dark:text-white dark:hover:text-blue-400">
                                                                <?= sanitize_string($job['title']) ?>
                                                            </a>
                                                            <span class="inline-block px-2.5 py-1 rounded-lg text-[11px] font-semibold <?= $statusColors[$job['status']] ?? $statusColors['open'] ?>">
                                                                <?= ucfirst(str_replace('_', ' ', $job['status'])) ?>
                                                            </span>
                                                        </div>

                                                        <p class="text-sm text-gray-500 leading-relaxed mb-3 line-clamp-2">
                                                            <?= sanitize_string(mb_strimwidth($job['description'], 0, 120, '...')) ?>
                                                        </p>

                                                        <?php if (!empty($job['skills'])): ?>
                                                            <div class="flex flex-wrap gap-1.5 mb-3">
                                                                <?php
                                                                foreach ($job['skills'] as $skill):
                                                                    $col = getSkillColor($skill['category'] ?? 'General', $skillColors, $defaultColor);
                                                                    ?>
                                                                    <span class="skill-tag inline-flex items-center px-2.5 py-1 <?= $col['bg'] ?> <?= $col['text'] ?> text-[11px] font-medium rounded-lg border <?= $col['border'] ?>">
                                                                        <?= sanitize_string($skill['skill_name']) ?>
                                                                    </span>
                                                                <?php endforeach; ?>
                                                            </div>
                                                        <?php endif; ?>

                                                        <div class="flex flex-wrap items-center gap-4 text-xs text-gray-400">
                                                            <span class="flex items-center gap-1.5">
                                                                <i class="fas fa-dollar-sign text-emerald-500"></i>
                                                                <span class="font-bold text-gray-900 dark:text-white"><?= format_currency($job['budget']) ?></span>
                                                            </span>
                                                            <span class="flex items-center gap-1.5">
                                                                <i class="fas fa-clock text-blue-400"></i>
                                                                <?= time_ago($job['created_at']) ?>
                                                            </span>
                                                            <span class="flex items-center gap-1.5">
                                                                <i class="fas fa-file-alt text-violet-400"></i>
                                                                <span class="font-semibold text-gray-600"><?= $job['proposal_count'] ?></span> proposal<?= $job['proposal_count'] !== 1 ? 's' : '' ?>
                                                            </span>
                                                            <span class="flex items-center gap-1.5">
                                                                <i class="fas fa-user text-gray-400"></i>
                                                                <?= sanitize_string($job['client_name']) ?>
                                                            </span>
                                                        </div>
                                                    </div>

                                                    <div class="flex flex-wrap lg:flex-nowrap items-center gap-2 lg:flex-col lg:items-stretch lg:min-w-[150px]">
                                                        <a href="job_detail.php?id=<?= $job['id'] ?>"
                                                            class="inline-flex items-center justify-center gap-2 px-4 py-2.5 border border-gray-200 text-gray-700 hover:border-blue-300 hover:text-blue-600 text-xs font-semibold rounded-xl transition-all dark:border-gray-600 dark:text-gray-300 dark:hover:border-blue-500 dark:hover:text-blue-400">
                                                            <i class="fas fa-eye text-[10px]"></i> View Details
                                                        </a>
                                                        <?php if ($job['has_proposed']): ?>
                                                            <span class="inline-flex items-center justify-center gap-2 px-4 py-2.5 bg-gray-100 text-gray-500 text-xs font-semibold rounded-xl">
                                                                <i class="fas fa-check text-[10px]"></i> Applied
                                                            </span>
                                                        <?php else: ?>
                                                            <button type="button" onclick="openProposalModal(<?= $job['id'] ?>, '<?= sanitize_string(addslashes($job['title'])) ?>', <?= $job['budget'] ?>)"
                                                                class="inline-flex items-center justify-center gap-2 px-4 py-2.5 btn-grad text-white text-xs font-semibold rounded-xl shadow-sm shadow-blue-500/25">
                                                                <i class="fas fa-paper-plane text-[10px]"></i> Apply Now
                                                            </button>
                                                        <?php endif; ?>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                </div>

                                <!-- ═══ PAGINATION ═══════════════════════════════════ -->
                                <?php if ($pagination['total_pages'] > 1): ?>
                                    <div class="flex items-center justify-between bg-white rounded-2xl p-4 border border-gray-100 shadow-sm fade-in dark:bg-gray-800 dark:border-gray-700">
                                        <p class="text-xs text-gray-400">
                                            Page <span class="font-semibold text-gray-600"><?= $pagination['current_page'] ?></span>
                                            of <span class="font-semibold text-gray-600"><?= $pagination['total_pages'] ?></span>
                                            <span class="text-gray-300 mx-1">|</span>
                                            <span class="font-semibold text-gray-600"><?= number_format($totalFiltered) ?></span> total
                                        </p>
                                        <div class="flex items-center gap-1">
                                            <?php if ($pagination['has_prev']): ?>
                                                <a href="<?= $baseUrl ?>&page=<?= $pagination['current_page'] - 1 ?>"
                                                    class="w-9 h-9 flex items-center justify-center rounded-xl border border-gray-200 text-gray-500 hover:bg-gray-50 text-sm transition-all dark:border-gray-600 dark:text-gray-400 dark:hover:bg-gray-700">
                                                    <i class="fas fa-chevron-left text-xs"></i>
                                                </a>
                                            <?php endif; ?>

                                            <?php
                                            $startPage = max(1, $pagination['current_page'] - 2);
                                            $endPage = min($pagination['total_pages'], $pagination['current_page'] + 2);
                                            if ($startPage > 1):
                                                ?>
                                                <a href="<?= $baseUrl ?>&page=1" class="w-9 h-9 flex items-center justify-center rounded-xl text-sm font-medium text-gray-500 hover:bg-gray-50 transition-all dark:text-gray-400 dark:hover:bg-gray-700">1</a>
                                                <?php if ($startPage > 2): ?><span class="text-gray-300 px-1">...</span><?php endif; ?>
                                            <?php endif; ?>

                                            <?php for ($i = $startPage; $i <= $endPage; $i++): ?>
                                                <a href="<?= $baseUrl ?>&page=<?= $i ?>"
                                                    class="w-9 h-9 flex items-center justify-center rounded-xl text-sm font-medium transition-all <?= $i === $pagination['current_page'] ? 'btn-grad text-white shadow-sm' : 'text-gray-500 hover:bg-gray-50 dark:text-gray-400 dark:hover:bg-gray-700' ?>">
                                                    <?= $i ?>
                                                </a>
                                            <?php endfor; ?>

                                            <?php if ($endPage < $pagination['total_pages']): ?>
                                                <?php if ($endPage < $pagination['total_pages'] - 1): ?><span class="text-gray-300 px-1">...</span><?php endif; ?>
                                                <a href="<?= $baseUrl ?>&page=<?= $pagination['total_pages'] ?>" class="w-9 h-9 flex items-center justify-center rounded-xl text-sm font-medium text-gray-500 hover:bg-gray-50 transition-all dark:text-gray-400 dark:hover:bg-gray-700"><?= $pagination['total_pages'] ?></a>
                                            <?php endif; ?>

                                            <?php if ($pagination['has_next']): ?>
                                                <a href="<?= $baseUrl ?>&page=<?= $pagination['current_page'] + 1 ?>"
                                                    class="w-9 h-9 flex items-center justify-center rounded-xl border border-gray-200 text-gray-500 hover:bg-gray-50 text-sm transition-all dark:border-gray-600 dark:text-gray-400 dark:hover:bg-gray-700">
                                                    <i class="fas fa-chevron-right text-xs"></i>
                                                </a>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                <?php endif; ?>

                            <?php else: ?>
                                <!-- ═══ EMPTY STATE ═════════════════════════════════ -->
                                <div class="bg-white rounded-2xl border border-gray-100 shadow-sm fade-in dark:bg-gray-800 dark:border-gray-700">
                                    <div class="text-center py-16 px-6">
                                        <div class="w-24 h-24 rounded-3xl bg-gradient-to-br from-blue-50 to-cyan-50 flex items-center justify-center mx-auto mb-6 border border-blue-100">
                                            <i class="fas fa-search text-4xl text-blue-300"></i>
                                        </div>
                                        <h3 class="text-xl font-bold text-gray-900 mb-2 dark:text-white">No jobs found</h3>
                                        <p class="text-sm text-gray-400 mb-6 max-w-md mx-auto">
                                            <?= ($search !== '' || $budgetMin > 0 || $budgetMax > 0 || $statusFilter !== 'open' || $datePosted !== 'all' || !empty($skillIds))
                                                ? 'Try adjusting your filters or search terms to find more opportunities.'
                                                : 'Check back later for new job postings.' ?>
                                        </p>
                                        <?php if ($search !== '' || $budgetMin > 0 || $budgetMax > 0 || $statusFilter !== 'open' || $datePosted !== 'all' || !empty($skillIds)): ?>
                                            <a href="browse_jobs.php" class="btn-grad inline-flex items-center gap-2 text-white font-bold px-6 py-3 rounded-xl text-sm">
                                                <i class="fas fa-times text-xs"></i> Clear All Filters
                                            </a>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            <?php endif; ?>

                        </div>
                    </div>
                </form>

    <!-- ═══ PROPOSAL MODAL ═══════════════════════════════════════════════════ -->
    <div id="proposalModal" class="fixed inset-0 z-50 hidden">
        <div class="absolute inset-0 bg-black/50 backdrop-blur-sm" onclick="closeProposalModal()"></div>
        <div class="flex items-center justify-center min-h-screen p-4">
            <div class="bg-white rounded-2xl p-8 max-w-lg w-full shadow-2xl relative z-10 fade-in dark:bg-gray-800">
                <div class="flex items-center justify-between mb-6">
                    <div>
                        <h3 class="text-lg font-bold text-gray-900 dark:text-white">Submit Proposal</h3>
                        <p class="text-xs text-gray-400 mt-1">for <span id="modalJobTitle" class="font-semibold text-gray-600"></span></p>
                    </div>
                    <button onclick="closeProposalModal()" class="w-8 h-8 rounded-lg bg-gray-100 flex items-center justify-center text-gray-400 hover:text-gray-600 transition-colors dark:bg-gray-700">
                        <i class="fas fa-times text-sm"></i>
                    </button>
                </div>

                <form method="POST" action="/finalproject/freelancer/browse_jobs.php" class="space-y-4">
                    <input type="hidden" name="action" value="submit_proposal">
                    <input type="hidden" name="job_id" id="modalJobId" value="">

                    <div>
                        <label class="block text-xs font-semibold text-gray-700 mb-1.5">Your Bid Amount ($) <span class="text-red-500">*</span></label>
                        <div class="relative">
                            <div class="absolute left-4 top-1/2 -translate-y-1/2 flex items-center justify-center w-6 h-6 rounded-lg bg-emerald-100 dark:bg-emerald-900/30">
                                <i class="fas fa-dollar-sign text-emerald-600 text-xs"></i>
                            </div>
                            <input type="number" name="amount" step="0.01" min="0.01" required id="modalBudget"
                                placeholder="0.00"
                                class="fld w-full bg-gray-50 border border-gray-200 rounded-xl pl-12 pr-4 py-2.5 text-sm text-gray-900 placeholder-gray-400 dark:bg-gray-700 dark:border-gray-600 dark:text-white">
                        </div>
                        <p class="text-[11px] text-gray-400 mt-1">Job budget: <span id="modalBudgetDisplay" class="font-semibold text-gray-600">$0.00</span></p>
                    </div>

                    <div>
                        <label class="block text-xs font-semibold text-gray-700 mb-1.5">Proposal Message <span class="text-red-500">*</span></label>
                        <textarea name="proposal_text" rows="6" required
                            placeholder="Explain why you're the best fit for this job. Mention relevant experience, your approach, and timeline..."
                            class="fld w-full bg-gray-50 border border-gray-200 rounded-xl px-4 py-2.5 text-sm text-gray-900 placeholder-gray-400 resize-none dark:bg-gray-700 dark:border-gray-600 dark:text-white"></textarea>
                        <p class="text-[11px] text-gray-400 mt-1">Minimum 20 characters</p>
                    </div>

                    <div class="flex gap-3 pt-2">
                        <button type="button" onclick="closeProposalModal()"
                            class="flex-1 px-5 py-3 border border-gray-200 hover:border-gray-300 text-gray-600 rounded-xl text-sm font-semibold transition-all dark:border-gray-600 dark:text-gray-400">
                            Cancel
                        </button>
                        <button type="submit"
                            class="flex-1 px-5 py-3 btn-grad text-white rounded-xl text-sm font-semibold shadow-lg shadow-blue-500/25 flex items-center justify-center gap-2">
                            <i class="fas fa-paper-plane text-xs"></i> Submit Proposal
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script>
        function openProposalModal(jobId, jobTitle, budget) {
            document.getElementById('modalJobId').value = jobId;
            document.getElementById('modalJobTitle').textContent = jobTitle;
            document.getElementById('modalBudgetDisplay').textContent = '$' + budget.toLocaleString('en-US', {
                minimumFractionDigits: 2,
                maximumFractionDigits: 2
            });
            document.getElementById('proposalModal').classList.remove('hidden');
            document.body.style.overflow = 'hidden';
        }

        function closeProposalModal() {
            document.getElementById('proposalModal').classList.add('hidden');
            document.body.style.overflow = '';
        }

        document.addEventListener('keydown', function(e) {
            if (e.key === 'Escape') closeProposalModal();
        });
    </script>
<?php require_once __DIR__ . '/../components/freelancer_footer.php'; ?>