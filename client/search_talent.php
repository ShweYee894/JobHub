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

// ── Helper: extract scalar from GET ────────────────────────────────────────
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
$rateMin = max(0, floatval(scalar_param($_GET['rate_min'] ?? 0)));
$rateMax = max(0, floatval(scalar_param($_GET['rate_max'] ?? 0)));
$availabilityFilter = scalar_param($_GET['availability'] ?? '');
$experienceFilter = scalar_param($_GET['experience'] ?? '');
$skillIds = array_filter(array_map('intval', $_GET['skills'] ?? []));
$sortBy = scalar_param($_GET['sort'] ?? '', 'newest');
$page = max(1, intval($_GET['page'] ?? 1));
$perPage = 12;

$allowedAvailability = ['', 'Available', 'Busy', 'Unavailable'];
if (!in_array($availabilityFilter, $allowedAvailability))
    $availabilityFilter = '';

$allowedExperience = ['', 'entry', 'intermediate', 'expert'];
if (!in_array($experienceFilter, $allowedExperience))
    $experienceFilter = '';

$allowedSort = ['newest', 'rate_high', 'rate_low', 'rating'];
if (!in_array($sortBy, $allowedSort))
    $sortBy = 'newest';

// ── Build query conditions ────────────────────────────────────────────────
$where = ['u.role = ?', 'u.status = ?'];
$params = ['freelancer', 'active'];
$types = 'ss';

if ($availabilityFilter !== '') {
    $where[] = 'f.availability = ?';
    $params[] = $availabilityFilter;
    $types .= 's';
}

if ($experienceFilter !== '') {
    $where[] = 'f.years_of_experience >= ?';
    switch ($experienceFilter) {
        case 'entry':
            $params[] = 0;
            break;
        case 'intermediate':
            $params[] = 3;
            break;
        case 'expert':
            $params[] = 6;
            break;
    }
    $types .= 'i';
}

if ($rateMin > 0) {
    $where[] = 'f.hourly_rate >= ?';
    $params[] = $rateMin;
    $types .= 'd';
}

if ($rateMax > 0) {
    $where[] = 'f.hourly_rate <= ?';
    $params[] = $rateMax;
    $types .= 'd';
}

if ($search !== '') {
    $safeSearch = '%' . $search . '%';
    $where[] = '(LOWER(u.name) LIKE LOWER(?) OR LOWER(f.title) LIKE LOWER(?) OR f.user_id IN (SELECT fs.freelancer_id FROM freelancer_skills fs INNER JOIN skills s ON fs.skill_id = s.id WHERE LOWER(s.skill_name) LIKE LOWER(?)))';
    $params[] = $safeSearch;
    $params[] = $safeSearch;
    $params[] = $safeSearch;
    $types .= 'sss';
}

if (!empty($skillIds)) {
    $placeholders = implode(',', array_fill(0, count($skillIds), '?'));
    $where[] = "f.user_id IN (SELECT fs.freelancer_id FROM freelancer_skills fs WHERE fs.skill_id IN ($placeholders) GROUP BY fs.freelancer_id HAVING COUNT(DISTINCT fs.skill_id) = " . count($skillIds) . ')';
    $params = array_merge($params, $skillIds);
    $types .= str_repeat('i', count($skillIds));
}

// ── Load all skills ───────────────────────────────────────────────────────
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
$countSql = "SELECT COUNT(DISTINCT f.user_id) AS total FROM freelancers f JOIN users u ON f.user_id = u.id WHERE $whereSQL";

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
    'newest' => 'u.created_at DESC',
    'rate_high' => 'f.hourly_rate DESC',
    'rate_low' => 'f.hourly_rate ASC',
    'rating' => 'f.completed_jobs DESC, f.total_earnings DESC',
];
$orderBy = $orderMap[$sortBy] ?? 'u.created_at DESC';

// ── Fetch freelancers ─────────────────────────────────────────────────────
$querySql = "SELECT f.user_id, u.name, u.profile_image, u.created_at,
             f.title, f.hourly_rate, f.years_of_experience, f.total_earnings,
             f.completed_jobs, f.availability, f.bio
             FROM freelancers f
             JOIN users u ON f.user_id = u.id
             WHERE $whereSQL
             ORDER BY $orderBy
             LIMIT ? OFFSET ?";

$finalTypes = $types . 'ii';
$finalParams = array_merge($params, [$pagination['per_page'], $pagination['offset']]);

$stmt = $conn->prepare($querySql);
$stmt->bind_param($finalTypes, ...$finalParams);
$stmt->execute();
$freelancersResult = $stmt->get_result();
$stmt->close();

$freelancerUserIds = [];
$freelancers = [];
while ($row = $freelancersResult->fetch_assoc()) {
    $freelancerUserIds[] = $row['user_id'];
    $row['skills'] = [];
    $row['avg_rating'] = 0;
    $freelancers[$row['user_id']] = $row;
}

// ── Fetch skills for each freelancer ──────────────────────────────────────
if (!empty($freelancerUserIds)) {
    $uidPlaceholders = implode(',', array_fill(0, count($freelancerUserIds), '?'));
    $uidTypes = str_repeat('i', count($freelancerUserIds));

    $skillStmt = $conn->prepare(
        "SELECT fs.freelancer_id, s.id AS skill_id, s.skill_name, s.category
         FROM freelancer_skills fs
         JOIN skills s ON fs.skill_id = s.id
         WHERE fs.freelancer_id IN ($uidPlaceholders)
         ORDER BY s.skill_name"
    );
    $skillStmt->bind_param($uidTypes, ...$freelancerUserIds);
    $skillStmt->execute();
    $skillRes = $skillStmt->get_result();
    while ($sr = $skillRes->fetch_assoc()) {
        if (isset($freelancers[$sr['freelancer_id']])) {
            $freelancers[$sr['freelancer_id']]['skills'][] = $sr;
        }
    }
    $skillStmt->close();

    // Fetch average rating
    $rateStmt = $conn->prepare(
        "SELECT reviewee_id, AVG(rating) AS avg_rating
         FROM reviews
         WHERE reviewee_id IN ($uidPlaceholders)
         GROUP BY reviewee_id"
    );
    $rateStmt->bind_param($uidTypes, ...$freelancerUserIds);
    $rateStmt->execute();
    $rateRes = $rateStmt->get_result();
    while ($rr = $rateRes->fetch_assoc()) {
        if (isset($freelancers[$rr['reviewee_id']])) {
            $freelancers[$rr['reviewee_id']]['avg_rating'] = round($rr['avg_rating'], 1);
        }
    }
    $rateStmt->close();
}

// ── Build base URL for pagination ─────────────────────────────────────────
function buildBaseUrl(array $overrides = []): string
{
    $params = array_merge($_GET, $overrides);
    unset($params['page']);
    $qs = http_build_query(array_filter($params, fn($v) => $v !== '' && $v !== null));
    return '/jobhub/client/search_talent.php' . ($qs ? '?' . $qs : '');
}

$baseUrl = buildBaseUrl();

$availabilityColors = [
    'Available' => 'bg-emerald-50 text-emerald-600 border border-emerald-200',
    'Busy' => 'bg-amber-50 text-amber-600 border border-amber-200',
    'Unavailable' => 'bg-red-50 text-red-600 border border-red-200',
];

$pageTitle = 'Search Talent';
$activePage = 'search_talent';
$user = ['name' => $user['name'] ?? 'Client', 'profile_image' => $user['profile_image'] ?? null];
$unreadCount = get_unread_message_count($userId, 'client');
require_once __DIR__ . '/../includes/client_topbar.php';
$conn->close();

?>

                <form method="GET" id="filterForm" class="space-y-6 max-w-7xl mx-auto px-4 sm:px-6 py-8">

                    <!-- SEARCH BAR -->
                    <div class="bg-white rounded-[10px] p-5 border border-[#E5E8EB] fade-in">
                        <div class="flex flex-col sm:flex-row gap-3">
                            <div class="relative flex-1">
                                <i data-lucide="search" class="w-4 h-4 absolute left-4 top-1/2 -translate-y-1/2 text-[#9CA3AF]"></i>
                                <input type="text" name="search" value="<?= sanitize_string($search) ?>"
                                    placeholder="Search freelancers by name, title, or skills..."
                                    class="fld w-full bg-[#F9FAFB] border border-[#E5E8EB] rounded-[10px] pl-11 pr-4 py-3 text-[15px] text-[#1A1A2E] placeholder-[#9CA3AF] font-medium tracking-wide focus:ring-2 focus:ring-[#4338CA] focus:border-[#4338CA] outline-none transition-all">
                            </div>
                            <button type="submit" class="bg-[#4338CA] hover:bg-[#3730A3] px-8 py-3 text-white text-[15px] font-semibold rounded-[10px] flex items-center justify-center gap-2 transition-all tracking-wide">
                                <i data-lucide="search" class="w-3 h-3"></i> Search
                            </button>
                            <?php if ($search !== '' || $rateMin > 0 || $rateMax > 0 || $availabilityFilter !== '' || $experienceFilter !== '' || !empty($skillIds)): ?>
                                <a href="search_talent.php" class="px-5 py-3 border border-[#E5E8EB] text-[#6B7280] hover:text-[#1A1A2E] hover:border-[#D1D5DB] rounded-[10px] text-[15px] font-medium transition-all flex items-center justify-center gap-2">
                                    <i data-lucide="x" class="w-3 h-3"></i> Clear All
                                </a>
                            <?php endif; ?>
                        </div>
                    </div>

                    <div class="flex gap-6">

                        <!-- FILTER SIDEBAR -->
                        <aside id="filterPanel" class="hidden lg:block w-72 flex-shrink-0 space-y-4">

                            <!-- Availability Filter -->
                            <div class="bg-white rounded-[10px] p-5 border border-[#E5E8EB] fade-in">
                                <h3 class="text-[15px] font-bold text-[#1A1A2E] mb-3 flex items-center gap-2 tracking-wide">
                                    <i data-lucide="circle-dot" class="w-4 h-4 text-[#6B7280]"></i> Availability
                                </h3>
                                <div class="space-y-1">
                                    <?php
                                    $availabilityOptions = [
                                        '' => ['All Freelancers', 'layers', 'text-[#6B7280]'],
                                        'Available' => ['Available', 'circle-check', 'text-[#16A34A]'],
                                        'Busy' => ['Busy', 'clock', 'text-[#D97706]'],
                                        'Unavailable' => ['Unavailable', 'ban', 'text-[#DC2626]'],
                                    ];
                                    foreach ($availabilityOptions as $val => $info):
                                        ?>
                                        <label class="flex items-center gap-2.5 cursor-pointer group p-2.5 rounded-[10px] hover:bg-[#F9FAFB] transition-colors <?= $availabilityFilter === $val ? 'bg-[#EEF2FF]' : '' ?>">
                                            <input type="radio" name="availability" value="<?= $val ?>" <?= $availabilityFilter === $val ? 'checked' : '' ?>
                                                class="w-4 h-4 text-[#4338CA] border-gray-300 focus:ring-[#4338CA]" onchange="this.form.submit()">
                                            <i data-lucide="<?= $info[1] ?>" class="<?= $info[2] ?> w-4 h-4"></i>
                                            <span class="text-[15px] <?= $availabilityFilter === $val ? 'font-semibold text-[#1A1A2E]' : 'text-[#6B7280]' ?> group-hover:text-[#1A1A2E] transition-colors tracking-wide"><?= $info[0] ?></span>
                                        </label>
                                    <?php endforeach; ?>
                                </div>
                            </div>

                            <!-- Hourly Rate Range -->
                            <div class="bg-white rounded-[10px] p-5 border border-[#E5E8EB] fade-in" style="animation-delay:.1s">
                                <h3 class="text-[15px] font-bold text-[#1A1A2E] mb-3 flex items-center gap-2 tracking-wide">
                                    <i data-lucide="dollar-sign" class="w-4 h-4 text-[#6B7280]"></i> Hourly Rate
                                </h3>
                                <div class="flex items-center gap-2">
                                    <div class="relative flex-1">
                                        <span class="absolute left-3 top-1/2 -translate-y-1/2 text-[#9CA3AF] text-xs">$</span>
                                        <input type="number" name="rate_min" value="<?= $rateMin > 0 ? $rateMin : '' ?>"
                                            placeholder="Min" min="0" step="1"
                                            class="fld w-full bg-[#F9FAFB] border border-[#E5E8EB] rounded-[10px] pl-7 pr-3 py-2.5 text-[15px] text-[#1A1A2E] placeholder-[#9CA3AF] font-medium focus:ring-2 focus:ring-[#4338CA] focus:border-[#4338CA] outline-none transition-all">
                                    </div>
                                    <span class="text-[#D1D5DB] font-medium">–</span>
                                    <div class="relative flex-1">
                                        <span class="absolute left-3 top-1/2 -translate-y-1/2 text-[#9CA3AF] text-xs">$</span>
                                        <input type="number" name="rate_max" value="<?= $rateMax > 0 ? $rateMax : '' ?>"
                                            placeholder="Max" min="0" step="1"
                                            class="fld w-full bg-[#F9FAFB] border border-[#E5E8EB] rounded-[10px] pl-7 pr-3 py-2.5 text-[15px] text-[#1A1A2E] placeholder-[#9CA3AF] font-medium focus:ring-2 focus:ring-[#4338CA] focus:border-[#4338CA] outline-none transition-all">
                                    </div>
                                </div>
                                <button type="submit" class="mt-3 w-full py-2.5 bg-[#F9FAFB] hover:bg-[#F3F4F6] text-[#6B7280] text-[13px] font-semibold rounded-[10px] transition-colors border border-[#E5E8EB]">
                                    Apply Rate
                                </button>
                            </div>

                            <!-- Experience Level -->
                            <div class="bg-white rounded-[10px] p-5 border border-[#E5E8EB] fade-in" style="animation-delay:.15s">
                                <h3 class="text-[15px] font-bold text-[#1A1A2E] mb-3 flex items-center gap-2 tracking-wide">
                                    <i data-lucide="layers" class="w-4 h-4 text-[#6B7280]"></i> Experience
                                </h3>
                                <div class="space-y-1">
                                    <?php
                                    $experienceOptions = [
                                        '' => 'All Levels',
                                        'entry' => 'Entry Level',
                                        'intermediate' => 'Intermediate',
                                        'expert' => 'Expert',
                                    ];
                                    foreach ($experienceOptions as $val => $label):
                                        ?>
                                        <label class="flex items-center gap-2.5 cursor-pointer group p-2.5 rounded-[10px] hover:bg-[#F9FAFB] transition-colors <?= $experienceFilter === $val ? 'bg-[#EEF2FF]' : '' ?>">
                                            <input type="radio" name="experience" value="<?= $val ?>" <?= $experienceFilter === $val ? 'checked' : '' ?>
                                                class="w-4 h-4 text-[#4338CA] border-gray-300 focus:ring-[#4338CA]" onchange="this.form.submit()">
                                            <span class="text-[15px] <?= $experienceFilter === $val ? 'font-semibold text-[#1A1A2E]' : 'text-[#6B7280]' ?> group-hover:text-[#1A1A2E] transition-colors tracking-wide"><?= $label ?></span>
                                        </label>
                                    <?php endforeach; ?>
                                </div>
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
                                <!-- Availability (mobile) -->
                                <div>
                                    <h3 class="text-[15px] font-bold text-[#1A1A2E] mb-3 tracking-wide">Availability</h3>
                                    <div class="space-y-1">
                                        <?php foreach ($availabilityOptions as $val => $info): ?>
                                            <label class="flex items-center gap-2.5 cursor-pointer p-2.5 rounded-[10px] hover:bg-[#F9FAFB]">
                                                <input type="radio" name="availability" value="<?= $val ?>" <?= $availabilityFilter === $val ? 'checked' : '' ?>
                                                    class="w-4 h-4 text-[#4338CA] border-gray-300 focus:ring-[#4338CA]">
                                                <span class="text-[15px] text-[#6B7280] tracking-wide"><?= $info[0] ?></span>
                                            </label>
                                        <?php endforeach; ?>
                                    </div>
                                </div>
                                <!-- Hourly Rate (mobile) -->
                                <div>
                                    <h3 class="text-[15px] font-bold text-[#1A1A2E] mb-3 tracking-wide">Hourly Rate</h3>
                                    <div class="flex items-center gap-2">
                                        <input type="number" name="rate_min" value="<?= $rateMin > 0 ? $rateMin : '' ?>" placeholder="Min" min="0"
                                            class="fld flex-1 bg-[#F9FAFB] border border-[#E5E8EB] rounded-[10px] px-3 py-2.5 text-[15px] font-medium">
                                        <span class="text-[#D1D5DB]">–</span>
                                        <input type="number" name="rate_max" value="<?= $rateMax > 0 ? $rateMax : '' ?>" placeholder="Max" min="0"
                                            class="fld flex-1 bg-[#F9FAFB] border border-[#E5E8EB] rounded-[10px] px-3 py-2.5 text-[15px] font-medium">
                                    </div>
                                </div>
                                <!-- Experience (mobile) -->
                                <div>
                                    <h3 class="text-[15px] font-bold text-[#1A1A2E] mb-3 tracking-wide">Experience</h3>
                                    <div class="space-y-1">
                                        <?php foreach ($experienceOptions as $val => $label): ?>
                                            <label class="flex items-center gap-2.5 cursor-pointer p-2.5 rounded-[10px] hover:bg-[#F9FAFB]">
                                                <input type="radio" name="experience" value="<?= $val ?>" <?= $experienceFilter === $val ? 'checked' : '' ?>
                                                    class="w-4 h-4 text-[#4338CA] border-gray-300 focus:ring-[#4338CA]">
                                                <span class="text-[15px] text-[#6B7280] tracking-wide"><?= $label ?></span>
                                            </label>
                                        <?php endforeach; ?>
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
                                <input type="hidden" name="sort" value="<?= sanitize_string($sortBy) ?>">
                                <input type="hidden" name="search" value="<?= sanitize_string($search) ?>">
                                <button type="submit" class="w-full bg-[#4338CA] hover:bg-[#3730A3] py-3 text-white text-[15px] font-semibold rounded-[10px] transition-all tracking-wide">Apply Filters</button>
                            </div>
                        </div>

                        <!-- FREELANCER LISTING -->
                        <div class="flex-1 min-w-0 space-y-5 lg:pl-8">

                            <!-- Results bar -->
                            <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3 fade-in">
                                <p class="text-[15px] text-[#6B7280]">
                                    <span class="font-bold text-[#1A1A2E]"><?= number_format($totalFiltered) ?></span> freelancer<?= $totalFiltered !== 1 ? 's' : '' ?> found
                                    <?php if ($search !== ''): ?>
                                        for "<span class="font-semibold text-[#4338CA]"><?= sanitize_string($search) ?></span>"
                                    <?php endif; ?>
                                </p>
                                <div class="flex items-center gap-2">
                                    <label class="text-[13px] text-[#9CA3AF] font-medium">Sort:</label>
                                    <select name="sort" onchange="this.form.submit()"
                                        class="appearance-none fld bg-white border border-[#E5E8EB] rounded-[10px] px-3 py-2 text-[13px] text-[#6B7280] cursor-pointer pr-8 font-medium focus:ring-2 focus:ring-[#4338CA] focus:border-[#4338CA] outline-none transition-all"
                                        style="background-image: url('data:image/svg+xml;utf8,<svg xmlns=\'http://www.w3.org/2000/svg\' fill=\'none\' viewBox=\'0 0 20 20\'><path stroke=\'%236b7280\' stroke-linecap=\'round\' stroke-linejoin=\'round\' stroke-width=\'1.5\' d=\'M6 8l4 4 4-4\'/></svg>'); background-position: right 0.5rem center; background-repeat: no-repeat; background-size: 1.5em 1.5em; padding-right: 2.5rem;">
                                        <option value="newest" <?= $sortBy === 'newest' ? 'selected' : '' ?>>Newest</option>
                                        <option value="rate_high" <?= $sortBy === 'rate_high' ? 'selected' : '' ?>>Rate: High to Low</option>
                                        <option value="rate_low" <?= $sortBy === 'rate_low' ? 'selected' : '' ?>>Rate: Low to High</option>
                                        <option value="rating" <?= $sortBy === 'rating' ? 'selected' : '' ?>>Top Rated</option>
                                    </select>
                                </div>
                            </div>

                            <?php if (!empty($freelancers)): ?>
                                <div class="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-3 gap-5">
                                    <?php foreach ($freelancers as $index => $fl): ?>
                                        <a href="/jobhub/freelancer/profile.php?id=<?= $fl['user_id'] ?>"
                                            class="bg-white rounded-[10px] border border-[#E5E8EB] p-5 hover:shadow-lg hover:border-[#4338CA]/20 transition-all fade-in flex flex-col"
                                            style="animation-delay:<?= 0.05 + ($index * 0.04) ?>s">
                                            <!-- Header: Avatar + Name + Title -->
                                            <div class="flex items-start gap-3.5 mb-3">
                                                <img src="<?= get_profile_image($fl['profile_image']) ?>"
                                                    alt="<?= sanitize_string($fl['name']) ?>"
                                                    class="w-12 h-12 rounded-[10px] object-cover border border-[#E5E8EB] flex-shrink-0">
                                                <div class="min-w-0 flex-1">
                                                    <h3 class="text-[15px] font-bold text-[#1A1A2E] truncate hover:text-[#4338CA] transition-colors">
                                                        <?= sanitize_string($fl['name']) ?>
                                                    </h3>
                                                    <p class="text-[13px] text-[#6B7280] truncate">
                                                        <?= sanitize_string($fl['title']) ?>
                                                    </p>
                                                </div>
                                            </div>

                                            <!-- Rate + Availability -->
                                            <div class="flex items-center justify-between mb-3">
                                                <p class="text-lg font-bold text-[#1A1A2E]">
                                                    $<?= number_format($fl['hourly_rate'], 0) ?>
                                                    <span class="text-[12px] font-medium text-[#9CA3AF]">/hr</span>
                                                </p>
                                                <span class="inline-flex items-center px-2.5 py-1 rounded-lg text-[11px] font-semibold <?= $availabilityColors[$fl['availability']] ?? 'bg-gray-100 text-gray-600 border border-gray-200' ?>">
                                                    <?= sanitize_string($fl['availability']) ?>
                                                </span>
                                            </div>

                                            <!-- Stats -->
                                            <div class="flex items-center gap-4 text-[12px] text-[#6B7280] mb-3">
                                                <span class="flex items-center gap-1">
                                                    <i data-lucide="briefcase" class="w-3 h-3 text-[#9CA3AF]"></i>
                                                    <?= $fl['completed_jobs'] ?> jobs
                                                </span>
                                                <span class="flex items-center gap-1">
                                                    <i data-lucide="coins" class="w-3 h-3 text-[#9CA3AF]"></i>
                                                    <?= format_currency($fl['total_earnings']) ?>
                                                </span>
                                                <?php if ($fl['avg_rating'] > 0): ?>
                                                <span class="flex items-center gap-1">
                                                    <i data-lucide="star" class="w-3 h-3 text-amber-400"></i>
                                                    <?= $fl['avg_rating'] ?>
                                                </span>
                                                <?php endif; ?>
                                                <span class="flex items-center gap-1">
                                                    <i data-lucide="clock" class="w-3 h-3 text-[#9CA3AF]"></i>
                                                    <?= $fl['years_of_experience'] ?>yr
                                                </span>
                                            </div>

                                            <!-- Bio -->
                                            <?php if (!empty($fl['bio'])): ?>
                                            <p class="text-[13px] text-[#6B7280] leading-relaxed mb-3 line-clamp-2">
                                                <?= sanitize_string(mb_strimwidth($fl['bio'], 0, 120, '...')) ?>
                                            </p>
                                            <?php endif; ?>

                                            <!-- Skills -->
                                            <?php if (!empty($fl['skills'])): ?>
                                            <div class="flex flex-wrap gap-1.5 mt-auto pt-1">
                                                <?php foreach (array_slice($fl['skills'], 0, 4) as $sk): ?>
                                                    <?php $sc = getSkillColor($sk['category'], $skillColors, $defaultColor); ?>
                                                    <span class="inline-block px-2.5 py-1 rounded-lg text-[11px] font-medium <?= $sc['bg'] ?> <?= $sc['text'] ?> border <?= $sc['border'] ?>">
                                                        <?= sanitize_string($sk['skill_name']) ?>
                                                    </span>
                                                <?php endforeach; ?>
                                                <?php if (count($fl['skills']) > 4): ?>
                                                    <span class="inline-block px-2.5 py-1 rounded-lg text-[11px] font-medium bg-gray-100 text-gray-500">+<?= count($fl['skills']) - 4 ?> more</span>
                                                <?php endif; ?>
                                            </div>
                                            <?php endif; ?>
                                        </a>
                                    <?php endforeach; ?>
                                </div>

                                <!-- PAGINATION -->
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
                                <!-- EMPTY STATE -->
                                <div class="bg-white rounded-[10px] border border-[#E5E8EB] fade-in">
                                    <div class="text-center py-16 px-6">
                                        <div class="w-20 h-20 rounded-[10px] bg-[#F5F7F9] flex items-center justify-center mx-auto mb-5">
                                            <i data-lucide="users" class="w-8 h-8 text-gray-300"></i>
                                        </div>
                                        <h3 class="text-lg font-bold text-gray-900 mb-2">No freelancers found</h3>
                                        <p class="text-sm text-gray-400 mb-6 max-w-md mx-auto">
                                            <?= ($search !== '' || $rateMin > 0 || $rateMax > 0 || $availabilityFilter !== '' || $experienceFilter !== '' || !empty($skillIds))
                                                ? 'Try adjusting your filters or search terms to find the right talent.'
                                                : 'No freelancers are available at the moment.' ?>
                                        </p>
                                        <?php if ($search !== '' || $rateMin > 0 || $rateMax > 0 || $availabilityFilter !== '' || $experienceFilter !== '' || !empty($skillIds)): ?>
                                            <a href="search_talent.php" class="inline-flex items-center gap-2 bg-[#4338CA] hover:bg-[#3730A3] text-white font-bold px-6 py-3 rounded-[10px] text-sm transition-all">
                                                <i data-lucide="x" class="w-3 h-3"></i> Clear All Filters
                                            </a>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            <?php endif; ?>

                        </div>
                    </div>

                    <!-- Mobile filter toggle button -->
                    <button type="button" onclick="document.getElementById('mobileFilter').classList.remove('translate-x-full')"
                        class="fixed bottom-6 left-6 z-30 lg:hidden w-12 h-12 bg-[#4338CA] hover:bg-[#3730A3] text-white rounded-[10px] shadow-lg flex items-center justify-center transition-all">
                        <i data-lucide="sliders-horizontal" class="w-4 h-4"></i>
                    </button>

                </form>

<?php require_once __DIR__ . '/../includes/client_footer.php'; ?>
