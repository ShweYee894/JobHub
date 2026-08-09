<?php
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../auth/auth.php';
require_role('client');
require_once __DIR__ . '/../config/helpers.php';

$userId = $_SESSION['user_id'];

$stmt = $conn->prepare('SELECT name, profile_image FROM users WHERE id = ?');
$stmt->bind_param('i', $userId);
$stmt->execute();
$user = $stmt->get_result()->fetch_assoc();
$stmt->close();

// ── Helper: extract scalar from GET (mobile drawer duplicates cause arrays)
function scalar_param($value, $default = '') {
    if (is_array($value)) {
        foreach ($value as $v) {
            if ($v !== '' && $v !== null) return $v;
        }
        return $default;
    }
    return $value ?? $default;
}

// ── Sanitize filters ──────────────────────────────────────────────────────
$search = trim(scalar_param($_GET['search'] ?? ''));
$budgetMin = max(0, floatval(scalar_param($_GET['budget_min'] ?? 0)));
$budgetMax = max(0, floatval(scalar_param($_GET['budget_max'] ?? 0)));
$statusFilter = scalar_param($_GET['status'] ?? '', 'all');
$skillIds = array_filter(array_map('intval', $_GET['skills'] ?? []));
$datePosted = scalar_param($_GET['date_posted'] ?? '', 'all');
$sortBy = scalar_param($_GET['sort'] ?? '', 'newest');
$page = max(1, intval($_GET['page'] ?? 1));
$perPage = 12;

$allowedStatuses = ['open', 'in_progress', 'completed', 'cancelled', 'all'];
if (!in_array($statusFilter, $allowedStatuses))
    $statusFilter = 'all';

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

$where[] = 'c.client_id = ?';
$params[] = $userId;
$types .= 'i';

if ($statusFilter !== 'all') {
    $where[] = 'j.status = ?';
    $params[] = $statusFilter;
    $types .= 's';
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
    $safeSearch = '%' . $search . '%';
    $where[] = '(LOWER(j.title) LIKE LOWER(?) OR LOWER(j.description) LIKE LOWER(?) OR j.id IN (SELECT js.job_id FROM job_skills js INNER JOIN skills s ON js.skill_id = s.id WHERE LOWER(s.skill_name) LIKE LOWER(?)))';
    $params[] = $safeSearch;
    $params[] = $safeSearch;
    $params[] = $safeSearch;
    $types .= 'sss';
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
$countSql = "SELECT COUNT(DISTINCT j.id) AS total FROM jobs j JOIN clients c ON j.client_id = c.client_id WHERE $whereSQL";
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
             j.job_type, j.experience_level, j.category, j.is_featured,
             (SELECT COUNT(*) FROM proposals WHERE job_id = j.id) AS proposal_count
             FROM jobs j
             JOIN clients c ON j.client_id = c.client_id
             WHERE $whereSQL";
if ($dateCondition !== '') {
    $querySql .= " AND j.created_at >= DATE_ADD(NOW(), INTERVAL $dateCondition)";
}
$querySql .= " ORDER BY COALESCE(j.is_featured, 0) DESC, $orderBy LIMIT ? OFFSET ?";

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
}

// ── Build base URL for pagination ─────────────────────────────────────────
function buildBaseUrl(array $overrides = []): string
{
    $params = array_merge($_GET, $overrides);
    unset($params['page']);
    $qs = http_build_query(array_filter($params, fn($v) => $v !== '' && $v !== null));
    return '/jobhub/client/browse_jobs.php' . ($qs ? '?' . $qs : '');
}

$baseUrl = buildBaseUrl();

$statusColors = [
    'open' => 'bg-emerald-50 text-emerald-600 border border-emerald-200',
    'in_progress' => 'bg-blue-50 text-blue-600 border border-blue-200',
    'completed' => 'bg-gray-100 text-gray-600 border border-gray-200',
    'cancelled' => 'bg-gray-100 text-gray-500 border border-gray-200',
];

$pageTitle = 'My Jobs';
$activePage = 'browse_jobs';
$user = ['name' => $user['name'] ?? 'Client', 'profile_image' => $user['profile_image'] ?? null];
$unreadCount = get_unread_message_count($userId, 'client');
require_once __DIR__ . '/../includes/client_topbar.php';
$conn->close();

?>

                <form method="GET" id="filterForm" class="space-y-6 max-w-7xl mx-auto px-4 sm:px-6 py-8">

                    <!-- ═══ SEARCH BAR ═══════════════════════════════════ -->
                    <div class="bg-white rounded-[10px] p-5 border border-[#E5E8EB] fade-in">
                        <div class="flex flex-col sm:flex-row gap-3">
                            <div class="relative flex-1">
                                <i data-lucide="search" class="w-4 h-4 absolute left-4 top-1/2 -translate-y-1/2 text-[#9CA3AF]"></i>
                                <input type="text" name="search" value="<?= sanitize_string($search) ?>"
                                    placeholder="Search your jobs by title, description, or skills..."
                                    class="fld w-full bg-[#F9FAFB] border border-[#E5E8EB] rounded-[10px] pl-11 pr-4 py-3 text-[15px] text-[#1A1A2E] placeholder-[#9CA3AF] font-medium tracking-wide focus:ring-2 focus:ring-[#4338CA] focus:border-[#4338CA] outline-none transition-all">
                            </div>
                            <button type="submit" class="bg-[#4338CA] hover:bg-[#3730A3] px-8 py-3 text-white text-[15px] font-semibold rounded-[10px] flex items-center justify-center gap-2 transition-all tracking-wide">
                                <i data-lucide="search" class="w-3 h-3"></i> Search
                            </button>
                            <?php if ($search !== '' || $budgetMin > 0 || $budgetMax > 0 || $statusFilter !== 'all' || $datePosted !== 'all' || !empty($skillIds)): ?>
                                <a href="browse_jobs.php" class="px-5 py-3 border border-[#E5E8EB] text-[#6B7280] hover:text-[#1A1A2E] hover:border-[#D1D5DB] rounded-[10px] text-[15px] font-medium transition-all flex items-center justify-center gap-2">
                                    <i data-lucide="x" class="w-3 h-3"></i> Clear All
                                </a>
                            <?php endif; ?>
                        </div>
                    </div>

                    <div class="flex gap-6">

                        <!-- ═══ FILTER SIDEBAR ════════════════════════════ -->
                        <aside id="filterPanel" class="hidden lg:block w-72 flex-shrink-0 space-y-4">

                            <!-- Status Filter -->
                            <div class="bg-white rounded-[10px] p-5 border border-[#E5E8EB] fade-in">
                                <h3 class="text-[15px] font-bold text-[#1A1A2E] mb-3 flex items-center gap-2 tracking-wide">
                                    <i data-lucide="circle-dot" class="w-4 h-4 text-[#6B7280]"></i> Status
                                </h3>
                                <div class="space-y-1">
                                    <?php
                                    $statusOptions = [
                                        'all' => ['All Statuses', 'layers', 'text-[#6B7280]'],
                                        'open' => ['Open Jobs', 'circle-check', 'text-[#16A34A]'],
                                        'in_progress' => ['In Progress', 'loader', 'text-[#2563EB]'],
                                        'completed' => ['Completed', 'check-check', 'text-[#9CA3AF]'],
                                        'cancelled' => ['Cancelled', 'x-circle', 'text-[#DC2626]'],
                                    ];
                                    foreach ($statusOptions as $val => $info):
                                        ?>
                                        <label class="flex items-center gap-2.5 cursor-pointer group p-2.5 rounded-[10px] hover:bg-[#F9FAFB] transition-colors <?= $statusFilter === $val ? 'bg-[#EEF2FF]' : '' ?>">
                                            <input type="radio" name="status" value="<?= $val ?>" <?= $statusFilter === $val ? 'checked' : '' ?>
                                                class="w-4 h-4 text-[#4338CA] border-gray-300 focus:ring-[#4338CA]" onchange="this.form.submit()">
                                            <i data-lucide="<?= $info[1] ?>" class="<?= $info[2] ?> w-4 h-4"></i>
                                            <span class="text-[15px] <?= $statusFilter === $val ? 'font-semibold text-[#1A1A2E]' : 'text-[#6B7280]' ?> group-hover:text-[#1A1A2E] transition-colors tracking-wide"><?= $info[0] ?></span>
                                        </label>
                                    <?php endforeach; ?>
                                </div>
                            </div>

                            <!-- Budget Range -->
                            <div class="bg-white rounded-[10px] p-5 border border-[#E5E8EB] fade-in" style="animation-delay:.1s">
                                <h3 class="text-[15px] font-bold text-[#1A1A2E] mb-3 flex items-center gap-2 tracking-wide">
                                    <i data-lucide="dollar-sign" class="w-4 h-4 text-[#6B7280]"></i> Budget Range
                                </h3>
                                <div class="flex items-center gap-2">
                                    <div class="relative flex-1">
                                        <span class="absolute left-3 top-1/2 -translate-y-1/2 text-[#9CA3AF] text-xs">$</span>
                                        <input type="number" name="budget_min" value="<?= $budgetMin > 0 ? $budgetMin : '' ?>"
                                            placeholder="Min" min="0" step="1"
                                            class="fld w-full bg-[#F9FAFB] border border-[#E5E8EB] rounded-[10px] pl-7 pr-3 py-2.5 text-[15px] text-[#1A1A2E] placeholder-[#9CA3AF] font-medium focus:ring-2 focus:ring-[#4338CA] focus:border-[#4338CA] outline-none transition-all">
                                    </div>
                                    <span class="text-[#D1D5DB] font-medium">–</span>
                                    <div class="relative flex-1">
                                        <span class="absolute left-3 top-1/2 -translate-y-1/2 text-[#9CA3AF] text-xs">$</span>
                                        <input type="number" name="budget_max" value="<?= $budgetMax > 0 ? $budgetMax : '' ?>"
                                            placeholder="Max" min="0" step="1"
                                            class="fld w-full bg-[#F9FAFB] border border-[#E5E8EB] rounded-[10px] pl-7 pr-3 py-2.5 text-[15px] text-[#1A1A2E] placeholder-[#9CA3AF] font-medium focus:ring-2 focus:ring-[#4338CA] focus:border-[#4338CA] outline-none transition-all">
                                    </div>
                                </div>
                                <button type="submit" class="mt-3 w-full py-2.5 bg-[#F9FAFB] hover:bg-[#F3F4F6] text-[#6B7280] text-[13px] font-semibold rounded-[10px] transition-colors border border-[#E5E8EB]">
                                    Apply Budget
                                </button>
                            </div>

                            <!-- Skills Filter (grouped by category) -->
                            <div class="bg-white rounded-[10px] p-5 border border-[#E5E8EB] fade-in" style="animation-delay:.2s">
                                <h3 class="text-[15px] font-bold text-[#1A1A2E] mb-3 flex items-center gap-2 tracking-wide">
                                    <i data-lucide="tags" class="w-4 h-4 text-[#6B7280]"></i> Skills
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
                                            <p class="text-[10px] font-bold uppercase tracking-wider text-[#9CA3AF] mb-2"><?= sanitize_string($category) ?></p>
                                            <div class="space-y-0.5">
                                                <?php foreach ($skills as $skill): ?>
                                                    <label class="flex items-center gap-2 cursor-pointer group p-2 rounded-[10px] hover:bg-[#F9FAFB] transition-colors">
                                                        <input type="checkbox" name="skills[]" value="<?= $skill['id'] ?>"
                                                            <?= in_array($skill['id'], $skillIds) ? 'checked' : '' ?>
                                                            class="w-3.5 h-3.5 text-[#4338CA] border-gray-300 rounded focus:ring-[#4338CA]">
                                                        <span class="text-[15px] text-[#6B7280] group-hover:text-[#1A1A2E] transition-colors tracking-wide"><?= sanitize_string($skill['skill_name']) ?></span>
                                                    </label>
                                                <?php endforeach; ?>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                                <button type="submit" class="mt-3 w-full py-2.5 bg-[#F9FAFB] hover:bg-[#F3F4F6] text-[#6B7280] text-[13px] font-semibold rounded-[10px] transition-colors border border-[#E5E8EB]">
                                    Apply Skills
                                </button>
                            </div>

                            <!-- Date Posted -->
                            <div class="bg-white rounded-[10px] p-5 border border-[#E5E8EB] fade-in" style="animation-delay:.3s">
                                <h3 class="text-[15px] font-bold text-[#1A1A2E] mb-3 flex items-center gap-2 tracking-wide">
                                    <i data-lucide="calendar" class="w-4 h-4 text-[#6B7280]"></i> Date Posted
                                </h3>
                                <div class="space-y-1">
                                    <?php
                                    $dateOptions = [
                                        'all' => 'All Time',
                                        '24h' => 'Last 24 Hours',
                                        'week' => 'Last Week',
                                        'month' => 'Last Month',
                                    ];
                                    foreach ($dateOptions as $val => $label):
                                        ?>
                                        <label class="flex items-center gap-2.5 cursor-pointer group p-2.5 rounded-[10px] hover:bg-[#F9FAFB] transition-colors <?= $datePosted === $val ? 'bg-[#EEF2FF]' : '' ?>">
                                            <input type="radio" name="date_posted" value="<?= $val ?>" <?= $datePosted === $val ? 'checked' : '' ?>
                                                class="w-4 h-4 text-[#4338CA] border-gray-300 focus:ring-[#4338CA]" onchange="this.form.submit()">
                                            <span class="text-[15px] <?= $datePosted === $val ? 'font-semibold text-[#1A1A2E]' : 'text-[#6B7280]' ?> group-hover:text-[#1A1A2E] transition-colors tracking-wide"><?= $label ?></span>
                                        </label>
                                    <?php endforeach; ?>
                                </div>
                            </div>

                            <!-- Hidden fields to preserve state -->
                            <input type="hidden" name="sort" value="<?= sanitize_string($sortBy) ?>">
                        </aside>

                        <!-- Mobile filter drawer -->
                        <div id="mobileFilter" class="fixed inset-y-0 left-0 z-40 w-80 bg-white shadow-2xl transform -translate-x-full lg:hidden overflow-y-auto">
                            <div class="p-5 border-b border-[#E5E8EB] flex items-center justify-between sticky top-0 bg-white z-10">
                                <h2 class="text-lg font-bold text-[#1A1A2E] tracking-wide">Filters</h2>
                                <button onclick="document.getElementById('mobileFilter').classList.add('translate-x-full')" class="w-8 h-8 rounded-[10px] bg-[#F5F7F9] flex items-center justify-center text-[#9CA3AF] hover:text-[#1A1A2E] transition-colors">
                                    <i data-lucide="x" class="w-4 h-4"></i>
                                </button>
                            </div>
                            <div class="p-5 space-y-4">
                                <!-- Status (mobile) -->
                                <div>
                                    <h3 class="text-[15px] font-bold text-[#1A1A2E] mb-3 tracking-wide">Status</h3>
                                    <div class="space-y-1">
                                        <?php foreach ($statusOptions as $val => $info): ?>
                                            <label class="flex items-center gap-2.5 cursor-pointer p-2.5 rounded-[10px] hover:bg-[#F9FAFB]">
                                                <input type="radio" name="status" value="<?= $val ?>" <?= $statusFilter === $val ? 'checked' : '' ?>
                                                    class="w-4 h-4 text-[#4338CA] border-gray-300 focus:ring-[#4338CA]">
                                                <span class="text-[15px] text-[#6B7280] tracking-wide"><?= $info[0] ?></span>
                                            </label>
                                        <?php endforeach; ?>
                                    </div>
                                </div>
                                <!-- Budget (mobile) -->
                                <div>
                                    <h3 class="text-[15px] font-bold text-[#1A1A2E] mb-3 tracking-wide">Budget Range</h3>
                                    <div class="flex items-center gap-2">
                                        <input type="number" name="budget_min" value="<?= $budgetMin > 0 ? $budgetMin : '' ?>" placeholder="Min" min="0"
                                            class="fld flex-1 bg-[#F9FAFB] border border-[#E5E8EB] rounded-[10px] px-3 py-2.5 text-[15px] font-medium">
                                        <span class="text-[#D1D5DB]">–</span>
                                        <input type="number" name="budget_max" value="<?= $budgetMax > 0 ? $budgetMax : '' ?>" placeholder="Max" min="0"
                                            class="fld flex-1 bg-[#F9FAFB] border border-[#E5E8EB] rounded-[10px] px-3 py-2.5 text-[15px] font-medium">
                                    </div>
                                </div>
                                <!-- Skills (mobile) -->
                                <div>
                                    <h3 class="text-[15px] font-bold text-[#1A1A2E] mb-3 tracking-wide">Skills</h3>
                                    <div class="space-y-0.5 max-h-60 overflow-y-auto">
                                        <?php foreach ($grouped as $category => $skills): ?>
                                            <div>
                                                <p class="text-[10px] font-bold uppercase tracking-wider text-[#9CA3AF] mb-1.5"><?= sanitize_string($category) ?></p>
                                                <?php foreach ($skills as $skill): ?>
                                                    <label class="flex items-center gap-2 cursor-pointer p-2 rounded-[10px] hover:bg-[#F9FAFB]">
                                                        <input type="checkbox" name="skills[]" value="<?= $skill['id'] ?>" <?= in_array($skill['id'], $skillIds) ? 'checked' : '' ?>
                                                            class="w-3.5 h-3.5 text-[#4338CA] border-gray-300 rounded focus:ring-[#4338CA]">
                                                        <span class="text-[15px] text-[#6B7280] tracking-wide"><?= sanitize_string($skill['skill_name']) ?></span>
                                                    </label>
                                                <?php endforeach; ?>
                                            </div>
                                        <?php endforeach; ?>
                                    </div>
                                </div>
                                <!-- Date (mobile) -->
                                <div>
                                    <h3 class="text-[15px] font-bold text-[#1A1A2E] mb-3 tracking-wide">Date Posted</h3>
                                    <div class="space-y-1">
                                        <?php foreach ($dateOptions as $val => $label): ?>
                                            <label class="flex items-center gap-2.5 cursor-pointer p-2.5 rounded-[10px] hover:bg-[#F9FAFB]">
                                                <input type="radio" name="date_posted" value="<?= $val ?>" <?= $datePosted === $val ? 'checked' : '' ?>
                                                    class="w-4 h-4 text-[#4338CA] border-gray-300 focus:ring-[#4338CA]">
                                                <span class="text-[15px] text-[#6B7280] tracking-wide"><?= $label ?></span>
                                            </label>
                                        <?php endforeach; ?>
                                    </div>
                                </div>
                                <input type="hidden" name="sort" value="<?= sanitize_string($sortBy) ?>">
                                <input type="hidden" name="search" value="<?= sanitize_string($search) ?>">
                                <button type="submit" class="w-full bg-[#4338CA] hover:bg-[#3730A3] py-3 text-white text-[15px] font-semibold rounded-[10px] transition-all tracking-wide">Apply Filters</button>
                            </div>
                        </div>

                        <!-- ═══ JOB LISTING ═══════════════════════════════ -->
                        <div class="flex-1 min-w-0 space-y-5 lg:pl-8">

                            <!-- Results bar -->
                            <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3 fade-in">
                                <p class="text-[15px] text-[#6B7280]">
                                    <span class="font-bold text-[#1A1A2E]"><?= number_format($totalFiltered) ?></span> job<?= $totalFiltered !== 1 ? 's' : '' ?> found
                                    <?php if ($search !== ''): ?>
                                        for "<span class="font-semibold text-[#4338CA]"><?= sanitize_string($search) ?></span>"
                                    <?php endif; ?>
                                </p>
                                <div class="flex items-center gap-2">
                                    <label class="text-[13px] text-[#9CA3AF] font-medium">Sort:</label>
                                    <select name="sort" onchange="this.form.submit()"
                                        class="appearance-none fld bg-white border border-[#E5E8EB] rounded-[10px] px-3 py-2 text-[13px] text-[#6B7280] cursor-pointer pr-8 font-medium focus:ring-2 focus:ring-[#4338CA] focus:border-[#4338CA] outline-none transition-all"
                                        style="background-image: url('data:image/svg+xml;utf8,<svg xmlns=\'http://www.w3.org/2000/svg\' fill=\'none\' viewBox=\'0 0 20 20\'><path stroke=\'%236b7280\' stroke-linecap=\'round\' stroke-linejoin=\'round\' stroke-width=\'1.5\' d=\'M6 8l4 4 4-4\'/></svg>'); background-position: right 0.5rem center; background-repeat: no-repeat; background-size: 1.5em 1.5em; padding-right: 2.5rem;">
                                        <option value="newest" <?= $sortBy === 'newest' ? 'selected' : '' ?>>Newest First</option>
                                        <option value="oldest" <?= $sortBy === 'oldest' ? 'selected' : '' ?>>Oldest First</option>
                                        <option value="budget_high" <?= $sortBy === 'budget_high' ? 'selected' : '' ?>>Budget: High to Low</option>
                                        <option value="budget_low" <?= $sortBy === 'budget_low' ? 'selected' : '' ?>>Budget: Low to High</option>
                                    </select>
                                </div>
                            </div>

                            <?php if (!empty($jobs)): ?>
                                <?php
                                $jobTypeLabels = ['hourly' => 'Hourly', 'fixed' => 'Fixed'];
                                $levelLabels = ['entry' => 'Entry level', 'intermediate' => 'Intermediate', 'expert' => 'Expert'];
                                ?>
                                <div class="space-y-0">
                                    <?php foreach ($jobs as $index => $job): ?>
                                        <div class="bg-white py-5 px-4 -mx-4 rounded-[10px] hover:bg-gray-50 transition-colors fade-in <?= $index > 0 ? 'border-t border-gray-200' : '' ?>" style="animation-delay:<?= 0.05 + ($index * 0.04) ?>s">
                                            <div class="flex items-start justify-between mb-2">
                                                <p class="text-xs text-gray-400">
                                                    Posted <?= time_ago($job['created_at']) ?>
                                                    <span class="mx-1">•</span>
                                                    Proposals: <?= $job['proposal_count'] > 50 ? '50+' : $job['proposal_count'] ?>
                                                </p>
                                                <span class="inline-flex items-center px-2.5 py-1 rounded-lg text-[11px] font-semibold <?= $statusColors[$job['status']] ?? 'bg-gray-100 text-gray-600 border border-gray-200' ?>">
                                                    <?= ucfirst(str_replace('_', ' ', $job['status'])) ?>
                                                </span>
                                            </div>

                                            <a href="job_detail.php?id=<?= $job['id'] ?>" class="block text-lg font-bold text-gray-900 hover:text-[#4338CA] transition-colors mb-1.5">
                                                <?= decode_over_encoded($job['title']) ?>
                                            </a>

                                            <p class="text-sm text-gray-500 mb-3">
                                                <?php if (!empty($job['job_type'])): ?>
                                                    <?= sanitize_string($jobTypeLabels[$job['job_type']] ?? ucfirst($job['job_type'])) ?>:
                                                    <?= format_currency($job['budget']) ?>
                                                <?php else: ?>
                                                    <?= format_currency($job['budget']) ?>
                                                <?php endif; ?>
                                                <?php if (!empty($job['experience_level'])): ?>
                                                    - <?= sanitize_string($levelLabels[$job['experience_level']] ?? ucfirst($job['experience_level'])) ?>
                                                <?php endif; ?>
                                            </p>

                                            <p class="text-sm text-gray-600 leading-relaxed mb-4 line-clamp-3">
                                                <?= sanitize_string(mb_strimwidth($job['description'], 0, 250, '...')) ?>
                                            </p>

                                            <?php if (!empty($job['skills'])): ?>
                                            <div class="flex flex-wrap gap-2 mb-4">
                                                <?php foreach (array_slice($job['skills'], 0, 6) as $sk): ?>
                                                    <span class="inline-block px-3 py-1.5 rounded-lg text-xs font-medium bg-gray-100 text-gray-700">
                                                        <?= sanitize_string($sk['skill_name']) ?>
                                                    </span>
                                                <?php endforeach; ?>
                                                <?php if (count($job['skills']) > 6): ?>
                                                    <span class="inline-block px-3 py-1.5 rounded-lg text-xs font-medium bg-gray-100 text-gray-500">+<?= count($job['skills']) - 6 ?> more</span>
                                                <?php endif; ?>
                                            </div>
                                            <?php endif; ?>

                                            <div class="flex items-center gap-3 mt-1">
                                                <a href="job_detail.php?id=<?= $job['id'] ?>"
                                                    class="inline-flex items-center justify-center gap-2 px-5 py-2.5 border border-gray-300 text-gray-700 hover:border-[#4338CA] hover:text-[#4338CA] text-xs font-semibold rounded-[10px] transition-all">
                                                    View Details
                                                </a>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                </div>

                                <!-- ═══ PAGINATION ═══════════════════════════════════ -->
                                <?php if ($pagination['total_pages'] > 1): ?>
                                    <div class="flex items-center justify-between bg-white rounded-[10px] p-4 border border-[#E5E8EB] fade-in">
                                        <p class="text-xs text-gray-400">
                                            Page <span class="font-semibold text-gray-600"><?= $pagination['current_page'] ?></span>
                                            of <span class="font-semibold text-gray-600"><?= $pagination['total_pages'] ?></span>
                                            <span class="text-gray-300 mx-1">|</span>
                                            <span class="font-semibold text-gray-600"><?= number_format($totalFiltered) ?></span> total
                                        </p>
                                        <div class="flex items-center gap-1">
                                            <?php if ($pagination['has_prev']): ?>
                                                <a href="<?= $baseUrl ?>&page=<?= $pagination['current_page'] - 1 ?>"
                                                    class="w-9 h-9 flex items-center justify-center rounded-[10px] border border-gray-200 text-gray-500 hover:bg-gray-50 text-sm transition-all">
                                                    <i data-lucide="chevron-left" class="w-4 h-4"></i>
                                                </a>
                                            <?php endif; ?>

                                            <?php
                                            $startPage = max(1, $pagination['current_page'] - 2);
                                            $endPage = min($pagination['total_pages'], $pagination['current_page'] + 2);
                                            if ($startPage > 1):
                                                ?>
                                                <a href="<?= $baseUrl ?>&page=1" class="w-9 h-9 flex items-center justify-center rounded-[10px] text-sm font-medium text-gray-500 hover:bg-gray-50 transition-all">1</a>
                                                <?php if ($startPage > 2): ?><span class="text-gray-300 px-1">...</span><?php endif; ?>
                                            <?php endif; ?>

                                            <?php for ($i = $startPage; $i <= $endPage; $i++): ?>
                                                <a href="<?= $baseUrl ?>&page=<?= $i ?>"
                                                    class="w-9 h-9 flex items-center justify-center rounded-[10px] text-sm font-medium transition-all <?= $i === $pagination['current_page'] ? 'bg-[#4338CA] text-white shadow-sm' : 'text-gray-500 hover:bg-gray-50' ?>">
                                                    <?= $i ?>
                                                </a>
                                            <?php endfor; ?>

                                            <?php if ($endPage < $pagination['total_pages']): ?>
                                                <?php if ($endPage < $pagination['total_pages'] - 1): ?><span class="text-gray-300 px-1">...</span><?php endif; ?>
                                                <a href="<?= $baseUrl ?>&page=<?= $pagination['total_pages'] ?>" class="w-9 h-9 flex items-center justify-center rounded-[10px] text-sm font-medium text-gray-500 hover:bg-gray-50 transition-all"><?= $pagination['total_pages'] ?></a>
                                            <?php endif; ?>

                                            <?php if ($pagination['has_next']): ?>
                                                <a href="<?= $baseUrl ?>&page=<?= $pagination['current_page'] + 1 ?>"
                                                    class="w-9 h-9 flex items-center justify-center rounded-[10px] border border-gray-200 text-gray-500 hover:bg-gray-50 text-sm transition-all">
                                                    <i data-lucide="chevron-right" class="w-4 h-4"></i>
                                                </a>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                <?php endif; ?>

                            <?php else: ?>
                                <!-- ═══ EMPTY STATE ═════════════════════════════════ -->
                                <div class="bg-white rounded-[10px] border border-[#E5E8EB] fade-in">
                                    <div class="text-center py-16 px-6">
                                        <div class="w-20 h-20 rounded-[10px] bg-[#F5F7F9] flex items-center justify-center mx-auto mb-5">
                                            <i data-lucide="briefcase" class="w-10 h-10 text-gray-300"></i>
                                        </div>
                                        <h3 class="text-lg font-bold text-gray-900 mb-2">No jobs found</h3>
                                        <p class="text-sm text-gray-400 mb-6 max-w-md mx-auto">
                                            <?= ($search !== '' || $budgetMin > 0 || $budgetMax > 0 || $statusFilter !== 'all' || $datePosted !== 'all' || !empty($skillIds))
                                                ? 'Try adjusting your filters or search terms to find your posted jobs.'
                                                : 'You haven\'t posted any jobs yet. Start by posting your first job!' ?>
                                        </p>
                                        <?php if ($search !== '' || $budgetMin > 0 || $budgetMax > 0 || $statusFilter !== 'all' || $datePosted !== 'all' || !empty($skillIds)): ?>
                                            <a href="browse_jobs.php" class="inline-flex items-center gap-2 bg-[#4338CA] hover:bg-[#3730A3] text-white font-bold px-6 py-3 rounded-[10px] text-sm transition-all">
                                                <i data-lucide="x" class="w-3 h-3"></i> Clear All Filters
                                            </a>
                                        <?php else: ?>
                                            <a href="post_job.php" class="inline-flex items-center gap-2 bg-[#4338CA] hover:bg-[#3730A3] text-white font-bold px-6 py-3 rounded-[10px] text-sm transition-all">
                                                <i data-lucide="plus" class="w-3 h-3"></i> Post a Job
                                            </a>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            <?php endif; ?>

                        </div>
                    </div>
                </form>

    <script>
    function openMobileFilter() {
        document.getElementById('mobileFilter').classList.remove('-translate-x-full');
    }
    </script>

<?php require_once __DIR__ . '/../includes/client_footer.php'; ?>
