<?php
require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/auth/auth.php';
require_once __DIR__ . '/config/helpers.php';

// ── Sanitize filters ──────────────────────────────────────────────────────
function scalar_param($value, $default = '')
{
    if (is_array($value)) {
        foreach ($value as $v) {
            if ($v !== '' && $v !== null) return $v;
        }
        return $default;
    }
    return $value ?? $default;
}

$search    = trim(scalar_param($_GET['search'] ?? ''));
$budgetMin = max(0, floatval(scalar_param($_GET['budget_min'] ?? 0)));
$budgetMax = max(0, floatval(scalar_param($_GET['budget_max'] ?? 0)));
$skillIds  = array_filter(array_map('intval', $_GET['skills'] ?? []));
$datePosted = scalar_param($_GET['date_posted'] ?? '', 'all');
$sortBy    = scalar_param($_GET['sort'] ?? '', 'newest');
$page      = max(1, intval($_GET['page'] ?? 1));
$perPage   = 12;

$allowedSort   = ['newest', 'oldest', 'budget_high', 'budget_low'];
$allowedDates  = ['all', '24h', 'week', 'month'];
if (!in_array($sortBy, $allowedSort))        $sortBy = 'newest';
if (!in_array($datePosted, $allowedDates))   $datePosted = 'all';

// ── Build query conditions ────────────────────────────────────────────────
$where   = [];
$params  = [];
$types   = '';

$where[] = "j.status = 'open'";
$where[] = 'COALESCE(j.is_archived, 0) = 0';

if ($budgetMin > 0) {
    $where[]  = 'j.budget >= ?';
    $params[] = $budgetMin;
    $types   .= 'd';
}
if ($budgetMax > 0) {
    $where[]  = 'j.budget <= ?';
    $params[] = $budgetMax;
    $types   .= 'd';
}
if ($search !== '') {
    $safeSearch = '%' . $search . '%';
    $where[]  = '(LOWER(j.title) LIKE LOWER(?) OR LOWER(j.description) LIKE LOWER(?) OR j.id IN (SELECT js.job_id FROM job_skills js INNER JOIN skills s ON js.skill_id = s.id WHERE LOWER(s.skill_name) LIKE LOWER(?)))';
    $params[] = $safeSearch;
    $params[] = $safeSearch;
    $params[] = $safeSearch;
    $types   .= 'sss';
}
$dateCondition = '';
if ($datePosted !== 'all') {
    $intervals = ['24h' => '-1 DAY', 'week' => '-1 WEEK', 'month' => '-1 MONTH'];
    $dateCondition = $intervals[$datePosted];
}
if (!empty($skillIds)) {
    $placeholders = implode(',', array_fill(0, count($skillIds), '?'));
    $where[]      = "j.id IN (SELECT js.job_id FROM job_skills js WHERE js.skill_id IN ($placeholders) GROUP BY js.job_id HAVING COUNT(DISTINCT js.skill_id) = " . count($skillIds) . ')';
    $params       = array_merge($params, $skillIds);
    $types       .= str_repeat('i', count($skillIds));
}

// ── All skills for filter sidebar ─────────────────────────────────────────
$allSkills = [];
$skillResult = $conn->query('SELECT id, skill_name, category FROM skills ORDER BY category, skill_name');
while ($sk = $skillResult->fetch_assoc()) {
    $allSkills[$sk['id']] = $sk;
}

$skillColors = [
    'Frontend' => ['bg' => 'bg-blue-50', 'text' => 'text-blue-600'],
    'Backend'  => ['bg' => 'bg-emerald-50', 'text' => 'text-emerald-600'],
    'Database' => ['bg' => 'bg-amber-50', 'text' => 'text-amber-600'],
    'DevOps'   => ['bg' => 'bg-violet-50', 'text' => 'text-violet-600'],
    'Design'   => ['bg' => 'bg-pink-50', 'text' => 'text-pink-600'],
    'Mobile'   => ['bg' => 'bg-cyan-50', 'text' => 'text-cyan-600'],
];
$defaultColor = ['bg' => 'bg-gray-50', 'text' => 'text-gray-600'];

// ── Count total ───────────────────────────────────────────────────────────
$whereSQL  = implode(' AND ', $where);
$countSql  = "SELECT COUNT(DISTINCT j.id) AS total FROM jobs j WHERE $whereSQL";
if ($dateCondition !== '') $countSql .= " AND j.created_at >= DATE_ADD(NOW(), INTERVAL $dateCondition)";
$countStmt = $conn->prepare($countSql);
if ($types !== '') $countStmt->bind_param($types, ...$params);
$countStmt->execute();
$totalFiltered = $countStmt->get_result()->fetch_assoc()['total'];
$countStmt->close();

$pagination = paginate($totalFiltered, $perPage, $page);

$orderMap = [
    'newest'      => 'j.created_at DESC',
    'oldest'      => 'j.created_at ASC',
    'budget_high' => 'j.budget DESC',
    'budget_low'  => 'j.budget ASC',
];
$orderBy = $orderMap[$sortBy] ?? 'j.created_at DESC';

// ── Fetch jobs ────────────────────────────────────────────────────────────
$querySql = "SELECT j.id, j.title, j.description, j.budget, j.status, j.created_at,
             j.job_type, j.experience_level, j.category, j.is_featured,
             u.name AS client_name, c.company_name,
             (SELECT COUNT(*) FROM proposals WHERE job_id = j.id) AS proposal_count
             FROM jobs j
             JOIN clients c ON j.client_id = c.client_id
             JOIN users u   ON c.client_id = u.id
             WHERE $whereSQL";
if ($dateCondition !== '') $querySql .= " AND j.created_at >= DATE_ADD(NOW(), INTERVAL $dateCondition)";
$querySql .= " ORDER BY COALESCE(j.is_featured, 0) DESC, $orderBy LIMIT ? OFFSET ?";

$finalTypes  = $types . 'ii';
$finalParams = array_merge($params, [$pagination['per_page'], $pagination['offset']]);
$stmt = $conn->prepare($querySql);
$stmt->bind_param($finalTypes, ...$finalParams);
$stmt->execute();
$jobsResult = $stmt->get_result();
$stmt->close();

$jobIds = [];
$jobs   = [];
while ($row = $jobsResult->fetch_assoc()) {
    $jobIds[]             = $row['id'];
    $row['skills']        = [];
    $jobs[$row['id']]     = $row;
}

if (!empty($jobIds)) {
    $jidPlaceholders = implode(',', array_fill(0, count($jobIds), '?'));
    $jidTypes        = str_repeat('i', count($jobIds));
    $skillStmt = $conn->prepare("SELECT js.job_id, s.skill_name, s.category FROM job_skills js JOIN skills s ON js.skill_id = s.id WHERE js.job_id IN ($jidPlaceholders) ORDER BY s.skill_name");
    $skillStmt->bind_param($jidTypes, ...$jobIds);
    $skillStmt->execute();
    $skillRes = $skillStmt->get_result();
    while ($sr = $skillRes->fetch_assoc()) {
        if (isset($jobs[$sr['job_id']])) $jobs[$sr['job_id']]['skills'][] = $sr;
    }
    $skillStmt->close();
}

function buildBaseUrl(array $overrides = []): string
{
    $params = array_merge($_GET, $overrides);
    unset($params['page']);
    $qs = http_build_query(array_filter($params, fn($v) => $v !== '' && $v !== null));
    return '/jobhub/browse_jobs.php' . ($qs ? '?' . $qs : '');
}
$baseUrl = buildBaseUrl();

$conn->close();

$_ixLoggedIn = isset($_SESSION['user_id']);
$_ixAvatar = $_ixLoggedIn ? get_profile_image($_SESSION['profile_image'] ?? null) : '';
$_ixName = $_ixLoggedIn ? ($_SESSION['user_name'] ?? 'User') : '';
$_ixRole = $_ixLoggedIn ? ($_SESSION['user_role'] ?? '') : '';
$_ixDash = match ($_ixRole) {
  'admin' => '/jobhub/admin/dashboard.php',
  'client' => '/jobhub/client/dashboard.php',
  'freelancer' => '/jobhub/freelancer/home.php',
  default => '/jobhub/index.php'
};
?>
<!DOCTYPE html>
<html lang="en" class="scroll-smooth">
<head>
    <meta charset="UTF-8"/>
    <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
    <title>Browse Jobs – JobHub</title>
    <meta name="description" content="Find open jobs on JobHub. Browse opportunities by skill, budget, and category."/>
    <link rel="stylesheet" href="/jobhub/assets/css/theme.css">
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://unpkg.com/lucide@0.344.0/dist/umd/lucide.min.js"></script>
    <link rel="icon" type="image/png" sizes="32x32" href="assets/upload/logos/logo.png">
    <script>
    tailwind.config = {
        theme: {
            extend: {
                fontFamily: {
                    sans: ['Inter', 'Plus Jakarta Sans', 'system-ui', '-apple-system', 'Segoe UI', 'Roboto', 'sans-serif'],
                    heading: ['"Plus Jakarta Sans"', 'Inter', 'system-ui', 'sans-serif'],
                    inter: ['Inter', 'sans-serif'],
                    serif: ['"Playfair Display"', 'Georgia', 'serif'],
                },
                colors: {
                    primary: { DEFAULT: '#4338CA', dark: '#3730A3', light: '#6366F1' },
                    charcoal: { DEFAULT: '#1A1D23', light: '#2D3039' },
                    surface: { DEFAULT: '#FDFDFD', alt: '#F5F5F4' },
                },
            }
        }
    }
    </script>
    <style>
    body{background:#FDFDFD;color:#374151}
    h1,h2,h3,.serif{font-family:'Plus Jakarta Sans','Inter',system-ui,sans-serif;color:#001e00;font-weight:700;letter-spacing:-0.025em}
    .btn-primary{background:#4338CA;transition:all .25s;color:#fff;border-radius:4px}
    .btn-primary:hover{background:#3730A3;transform:translateY(-1px)}
    .fade-in{animation:fadeUp .6s ease forwards;opacity:0}
    @keyframes fadeUp{0%{opacity:0;transform:translateY(24px)}100%{opacity:1;transform:translateY(0)}}
    .custom-scrollbar::-webkit-scrollbar{width:4px}
    .custom-scrollbar::-webkit-scrollbar-track{background:transparent}
    .custom-scrollbar::-webkit-scrollbar-thumb{background:#E5E8EB;border-radius:4px}
    .nav-link{position:relative}
    .nav-link::after{content:'';position:absolute;bottom:-2px;left:0;width:0;height:2px;background:linear-gradient(90deg,#4338CA,#6366F1);transition:width .3s}
    .nav-link:hover::after{width:100%}
    #navbar.scrolled{background:rgba(253,253,253,.96);backdrop-filter:blur(16px);border-bottom:1px solid #E8E8E8}
    #mobile-menu{transition:max-height .35s ease,opacity .3s ease;max-height:0;opacity:0;overflow:hidden}
    #mobile-menu.open{max-height:600px;opacity:1}
    .profile-popup{display:none;position:absolute;top:calc(100% + 8px);right:0;background:#fff;border:1px solid #E8E8E8;border-radius:8px;box-shadow:0 20px 60px rgba(0,0,0,.08);min-width:220px;z-index:50;overflow:hidden}
    .profile-popup.show{display:block}
    .input-elegant{border:1px solid #E8E8E8;border-radius:4px;transition:border-color .25s}
    .input-elegant:focus{border-color:#4338CA;outline:none}
    ::selection{background:#4338CA;color:#fff}
    </style>
</head>
<body>

<!-- Scroll Progress -->
<div id="progress" class="fixed top-0 left-0 h-[2px] z-[9999] transition-[width] duration-100" style="background:linear-gradient(90deg,#4338CA,#6366F1,#818CF8);width:0"></div>

<!-- ═══ NAVBAR ═════════════════════════════════════════════════════════════ -->
<nav id="navbar" class="fixed top-0 inset-x-0 z-50 transition-all duration-300 py-4 bg-surface/90 backdrop-blur-md">
    <div class="max-w-[1320px] mx-auto px-6 flex items-center justify-between">
      <div class="flex items-center gap-14">
        <!-- Logo -->
        <a href="/jobhub/index.php" class="flex items-center gap-2 group shrink-0">
          <img src="/jobhub/assets/upload/logos/logo.png" alt="Logo" class="w-[34px] h-[34px] rounded-lg">
          <span class="text-lg font-bold tracking-tight font-serif">
            <span class="text-charcoal">Job</span><span class="text-[#4338CA]">Hub</span>
          </span>
        </a>

        <!-- Desktop links -->
        <div class="hidden lg:flex items-center gap-8">
          <a href="/jobhub/index.php" class="nav-link text-[13px] font-medium text-gray-500 hover:text-charcoal transition-colors tracking-wide">Home</a>
          <a href="/jobhub/browse_jobs.php" class="nav-link text-[13px] font-semibold text-[#4338CA] transition-colors tracking-wide">Find Work</a>
          <a href="/jobhub/browse_freelancers.php" class="nav-link text-[13px] font-medium text-gray-500 hover:text-charcoal transition-colors tracking-wide">Find Freelancers</a>
          <a href="/jobhub/index.php#howitworks" class="nav-link text-[13px] font-medium text-gray-500 hover:text-charcoal transition-colors tracking-wide">How It Works</a>
          <a href="/jobhub/index.php#about" class="nav-link text-[13px] font-medium text-gray-500 hover:text-charcoal transition-colors tracking-wide">About Us</a>
          <a href="/jobhub/index.php#pricing" class="nav-link text-[13px] font-medium text-gray-500 hover:text-charcoal transition-colors tracking-wide">Pricing</a>
        </div>
      </div>

      <div class="flex gap-3 items-center">
        <!--Search with Dropdown-->
        <div class="relative flex items-center bg-surface border border-gray-200 overflow-visible" style="border-radius:4px">
          <div class="flex items-center gap-2 px-3 py-2">
            <i data-lucide="search" class="w-4 h-4 text-gray-400"></i>
            <input id="nav-search" type="text" placeholder="Search"
              class="bg-transparent w-32 sm:w-44 text-charcoal placeholder-gray-400 outline-none text-[13px]" />
          </div>
          <div class="w-px h-5 bg-gray-200"></div>
          <button id="navSearchDropdownBtn" type="button" class="flex items-center gap-1.5 px-3 py-2 cursor-pointer hover:bg-gray-50 transition-colors">
            <span id="navSearchType" class="text-[12px] font-medium text-gray-600">Talent</span>
            <i data-lucide="chevron-down" class="w-3 h-3 text-gray-400"></i>
          </button>
          <div id="navSearchDropdown" class="hidden absolute top-full left-0 right-0 mt-1 bg-white border border-gray-200 rounded-md shadow-lg z-50 overflow-hidden">
            <button type="button" class="w-full text-left px-4 py-2.5 text-[13px] text-gray-600 hover:bg-gray-50 transition-colors font-medium" data-type="talent" data-placeholder="Search for talent...">
              Talent
            </button>
            <button type="button" class="w-full text-left px-4 py-2.5 text-[13px] text-gray-600 hover:bg-gray-50 transition-colors font-medium" data-type="jobs" data-placeholder="Search for jobs...">
              Jobs
            </button>
          </div>
        </div>

        <!-- Auth buttons / Profile -->
        <div class="hidden lg:flex items-center gap-3">
          <?php if ($_ixLoggedIn): ?>
          <div class="relative" id="navProfileDropdown">
            <button onclick="document.getElementById('navProfileDropdown').querySelector('.profile-popup').classList.toggle('show')" class="flex items-center gap-2.5 py-1 px-2 rounded hover:bg-gray-50 transition-all">
              <img src="<?= htmlspecialchars($_ixAvatar) ?>" class="w-8 h-8 rounded-full object-cover border border-gray-200" alt="Avatar">
            </button>
            <div class="profile-popup">
              <div class="p-4 border-b border-gray-100">
                <p class="text-[13px] font-semibold text-charcoal"><?= htmlspecialchars($_ixName) ?></p>
                <p class="text-[11px] text-gray-400 mt-0.5 capitalize tracking-wide"><?= htmlspecialchars($_ixRole) ?></p>
              </div>
              <div class="py-1">
                <a href="<?= $_ixDash ?>" class="flex items-center gap-3 px-4 py-2.5 text-[13px] text-gray-600 hover:bg-gray-50 transition-colors"><i data-lucide="layout-grid" class="w-4 h-4 text-center"></i> Dashboard</a>
                <?php if ($_ixRole === 'freelancer'): ?>
                <a href="/jobhub/freelancer/profile.php" class="flex items-center gap-3 px-4 py-2.5 text-[13px] text-gray-600 hover:bg-gray-50 transition-colors"><i data-lucide="user" class="w-4 h-4 text-center"></i> My Profile</a>
                <?php endif; ?>
              </div>
              <div class="border-t border-gray-100 py-1">
                <a href="/jobhub/auth/logout.php" class="flex items-center gap-3 px-4 py-2.5 text-[13px] text-red-500 hover:bg-red-50 transition-colors"><i data-lucide="log-out" class="w-4 h-4 text-center"></i> Logout</a>
              </div>
            </div>
          </div>
          <?php else: ?>
          <a href="/jobhub/auth/login.php" class="text-[13px] font-medium text-gray-500 hover:text-charcoal transition-colors px-4 py-2">Log In</a>
            <a href="/jobhub/auth/register.php" class="btn-primary text-[13px] font-semibold px-5 py-2">Sign Up</a>
          <?php endif; ?>
        </div>
      </div>
      <!-- Hamburger -->
      <button id="hamburger" class="lg:hidden text-gray-500 hover:text-charcoal p-2" aria-label="Toggle menu">
        <i data-lucide="menu" class="w-5 h-5"></i>
      </button>
    </div>

    <!-- Mobile Menu -->
    <div id="mobile-menu" class="lg:hidden bg-white mx-4 mt-2 border border-gray-200" style="border-radius:6px">
      <div class="flex flex-col gap-1 p-4 text-[13px] font-medium text-gray-500">
        <a href="/jobhub/index.php" class="hover:text-charcoal py-2 px-3 rounded hover:bg-gray-50 transition-colors">Home</a>
        <a href="/jobhub/browse_jobs.php" class="text-[#4338CA] font-semibold py-2 px-3 rounded hover:bg-gray-50 transition-colors">Find Work</a>
        <a href="/jobhub/browse_freelancers.php" class="hover:text-charcoal py-2 px-3 rounded hover:bg-gray-50 transition-colors">Find Freelancers</a>
        <a href="/jobhub/index.php#howitworks" class="hover:text-charcoal py-2 px-3 rounded hover:bg-gray-50 transition-colors">How It Works</a>
        <a href="/jobhub/index.php#about" class="hover:text-charcoal py-2 px-3 rounded hover:bg-gray-50 transition-colors">About Us</a>
        <a href="/jobhub/index.php#pricing" class="hover:text-charcoal py-2 px-3 rounded hover:bg-gray-50 transition-colors">Pricing</a>
        <hr class="border-gray-100 my-1" />
        <?php if ($_ixLoggedIn): ?>
        <a href="<?= $_ixDash ?>" class="flex items-center gap-3 hover:text-charcoal py-2 px-3 rounded hover:bg-gray-50 transition-colors">
          <img src="<?= htmlspecialchars($_ixAvatar) ?>" class="w-7 h-7 rounded-full object-cover border border-gray-200" alt="Avatar">
          <span class="font-semibold"><?= htmlspecialchars($_ixName) ?></span>
        </a>
        <a href="/jobhub/auth/logout.php" class="hover:text-red-500 py-2 px-3 rounded hover:bg-red-50 transition-colors text-red-500"><i data-lucide="log-out" class="w-4 h-4 mr-2"></i>Logout</a>
        <?php else: ?>
        <a href="/jobhub/auth/login.php" class="hover:text-charcoal py-2 px-3 rounded hover:bg-gray-50 transition-colors">Log In</a>
        <a href="/jobhub/auth/register.php" class="btn-primary text-white text-center py-2 px-3 mt-1">Sign Up</a>
        <?php endif; ?>
      </div>
    </div>
</nav>

<main class="pt-24 pb-16">
    <form method="GET" id="filterForm" class="space-y-6 max-w-7xl mx-auto px-4 sm:px-6 py-8">

        <!-- Search bar -->
        <div class="bg-white rounded-[10px] p-5 border border-[#E5E8EB] fade-in">
            <div class="flex flex-col sm:flex-row gap-3">
                <div class="relative flex-1">
                    <i data-lucide="search" class="w-4 h-4 absolute left-4 top-1/2 -translate-y-1/2 text-[#9CA3AF]"></i>
                    <input type="text" name="search" value="<?= sanitize_string($search) ?>"
                        placeholder="Search jobs by title, description, or skills..."
                        class="w-full bg-[#F9FAFB] border border-[#E5E8EB] rounded-[10px] pl-11 pr-4 py-3 text-[15px] text-[#1A1A2E] placeholder-[#9CA3AF] font-medium focus:ring-2 focus:ring-[#4338CA] focus:border-[#4338CA] outline-none transition-all">
                </div>
                <button type="submit" class="bg-[#4338CA] hover:bg-[#3730A3] px-8 py-3 text-white text-[15px] font-semibold rounded-[10px] flex items-center justify-center gap-2 transition-all">
                    <i data-lucide="search" class="w-4 h-4"></i> Search
                </button>
                <?php if ($search !== '' || $budgetMin > 0 || $budgetMax > 0 || $datePosted !== 'all' || !empty($skillIds)): ?>
                    <a href="/jobhub/browse_jobs.php" class="px-5 py-3 border border-[#E5E8EB] text-[#6B7280] hover:text-[#1A1A2E] hover:border-[#D1D5DB] rounded-[10px] text-[15px] font-medium transition-all flex items-center justify-center gap-2">
                        <i data-lucide="x" class="w-4 h-4"></i> Clear All
                    </a>
                <?php endif; ?>
            </div>
        </div>

        <div class="flex gap-6">
            <!-- Filter sidebar -->
            <aside id="filterPanel" class="hidden lg:block w-72 flex-shrink-0 space-y-4">
                <!-- Budget Range -->
                <div class="bg-white rounded-[10px] p-5 border border-[#E5E8EB] fade-in">
                    <h3 class="text-[15px] font-bold text-[#1A1A2E] mb-3 flex items-center gap-2"><i data-lucide="dollar-sign" class="w-4 h-4 text-[#6B7280]"></i> Budget Range</h3>
                    <div class="flex items-center gap-2">
                        <div class="relative flex-1">
                            <span class="absolute left-3 top-1/2 -translate-y-1/2 text-[#9CA3AF] text-xs">$</span>
                            <input type="number" name="budget_min" value="<?= $budgetMin > 0 ? $budgetMin : '' ?>" placeholder="Min" min="0"
                                class="w-full bg-[#F9FAFB] border border-[#E5E8EB] rounded-[10px] pl-7 pr-3 py-2.5 text-[15px] font-medium focus:ring-2 focus:ring-[#4338CA] outline-none transition-all">
                        </div>
                        <span class="text-[#D1D5DB]">–</span>
                        <div class="relative flex-1">
                            <span class="absolute left-3 top-1/2 -translate-y-1/2 text-[#9CA3AF] text-xs">$</span>
                            <input type="number" name="budget_max" value="<?= $budgetMax > 0 ? $budgetMax : '' ?>" placeholder="Max" min="0"
                                class="w-full bg-[#F9FAFB] border border-[#E5E8EB] rounded-[10px] pl-7 pr-3 py-2.5 text-[15px] font-medium focus:ring-2 focus:ring-[#4338CA] outline-none transition-all">
                        </div>
                    </div>
                    <button type="submit" class="mt-3 w-full py-2.5 bg-[#F9FAFB] hover:bg-[#F3F4F6] text-[#6B7280] text-[13px] font-semibold rounded-[10px] transition-colors border border-[#E5E8EB]">Apply Budget</button>
                </div>

                <!-- Skills -->
                <div class="bg-white rounded-[10px] p-5 border border-[#E5E8EB] fade-in" style="animation-delay:.1s">
                    <h3 class="text-[15px] font-bold text-[#1A1A2E] mb-3 flex items-center gap-2"><i data-lucide="tags" class="w-4 h-4 text-[#6B7280]"></i> Skills</h3>
                    <div class="space-y-4 max-h-80 overflow-y-auto pr-1 custom-scrollbar">
                        <?php
                        $grouped = [];
                        foreach ($allSkills as $skill) { $grouped[$skill['category'] ?: 'General'][] = $skill; }
                        foreach ($grouped as $category => $skills):
                        ?>
                        <div>
                            <p class="text-[10px] font-bold uppercase tracking-wider text-[#9CA3AF] mb-2"><?= sanitize_string($category) ?></p>
                            <div class="space-y-0.5">
                                <?php foreach ($skills as $skill): ?>
                                <label class="flex items-center gap-2 cursor-pointer group p-2 rounded-[10px] hover:bg-[#F9FAFB] transition-colors">
                                    <input type="checkbox" name="skills[]" value="<?= $skill['id'] ?>" <?= in_array($skill['id'], $skillIds) ? 'checked' : '' ?>
                                        class="w-3.5 h-3.5 text-[#4338CA] border-gray-300 rounded focus:ring-[#4338CA]">
                                    <span class="text-[15px] text-[#6B7280] group-hover:text-[#1A1A2E] transition-colors"><?= sanitize_string($skill['skill_name']) ?></span>
                                </label>
                                <?php endforeach; ?>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                    <button type="submit" class="mt-3 w-full py-2.5 bg-[#F9FAFB] hover:bg-[#F3F4F6] text-[#6B7280] text-[13px] font-semibold rounded-[10px] transition-colors border border-[#E5E8EB]">Apply Skills</button>
                </div>

                <!-- Date Posted -->
                <div class="bg-white rounded-[10px] p-5 border border-[#E5E8EB] fade-in" style="animation-delay:.2s">
                    <h3 class="text-[15px] font-bold text-[#1A1A2E] mb-3 flex items-center gap-2"><i data-lucide="calendar" class="w-4 h-4 text-[#6B7280]"></i> Date Posted</h3>
                    <div class="space-y-1">
                        <?php foreach (['all' => 'All Time', '24h' => 'Last 24 Hours', 'week' => 'Last Week', 'month' => 'Last Month'] as $val => $label): ?>
                        <label class="flex items-center gap-2.5 cursor-pointer group p-2.5 rounded-[10px] hover:bg-[#F9FAFB] transition-colors <?= $datePosted === $val ? 'bg-[#EEF2FF]' : '' ?>">
                            <input type="radio" name="date_posted" value="<?= $val ?>" <?= $datePosted === $val ? 'checked' : '' ?>
                                class="w-4 h-4 text-[#4338CA] border-gray-300 focus:ring-[#4338CA]" onchange="this.form.submit()">
                            <span class="text-[15px] <?= $datePosted === $val ? 'font-semibold text-[#1A1A2E]' : 'text-[#6B7280]' ?> group-hover:text-[#1A1A2E] transition-colors"><?= $label ?></span>
                        </label>
                        <?php endforeach; ?>
                    </div>
                </div>
                <input type="hidden" name="sort" value="<?= sanitize_string($sortBy) ?>">
            </aside>

            <!-- Job listings -->
            <div class="flex-1 min-w-0 space-y-5 lg:pl-8">
                <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3 fade-in">
                    <p class="text-[15px] text-[#6B7280]">
                        <span class="font-bold text-[#1A1A2E]"><?= number_format($totalFiltered) ?></span> job<?= $totalFiltered !== 1 ? 's' : '' ?> found
                        <?php if ($search !== ''): ?>
                            for "<span class="font-semibold text-[#4338CA]"><?= sanitize_string($search) ?></span>"
                        <?php endif; ?>
                    </p>
                    <select name="sort" onchange="this.form.submit()"
                        class="appearance-none bg-white border border-[#E5E8EB] rounded-[10px] px-3 py-2 text-[13px] text-[#6B7280] cursor-pointer font-medium focus:ring-2 focus:ring-[#4338CA] outline-none transition-all"
                        style="background-image:url('data:image/svg+xml;utf8,<svg xmlns=%27http://www.w3.org/2000/svg%27 fill=%27none%27 viewBox=%270 0 20 20%27><path stroke=%236b7280%27 stroke-linecap=%27round%27 stroke-linejoin=%27round%27 stroke-width=%271.5%27 d=%27M6 8l4 4 4-4%27/></svg>');background-position:right 0.5rem center;background-repeat:no-repeat;background-size:1.5em 1.5em;padding-right:2.5rem;">
                        <option value="newest" <?= $sortBy === 'newest' ? 'selected' : '' ?>>Newest First</option>
                        <option value="oldest" <?= $sortBy === 'oldest' ? 'selected' : '' ?>>Oldest First</option>
                        <option value="budget_high" <?= $sortBy === 'budget_high' ? 'selected' : '' ?>>Budget: High to Low</option>
                        <option value="budget_low" <?= $sortBy === 'budget_low' ? 'selected' : '' ?>>Budget: Low to High</option>
                    </select>
                </div>

                <?php if (!empty($jobs)): ?>
                    <?php
                    $jobTypeLabels = ['hourly' => 'Hourly', 'fixed' => 'Fixed'];
                    $levelLabels   = ['entry' => 'Entry', 'intermediate' => 'Intermediate', 'expert' => 'Expert'];
                    ?>
                    <div class="space-y-0">
                    <?php foreach ($jobs as $index => $job): ?>
                        <div class="bg-white py-5 px-4 -mx-4 rounded-[10px] hover:bg-gray-50 transition-colors fade-in <?= $index > 0 ? 'border-t border-gray-200' : '' ?>" style="animation-delay:<?= 0.05 + ($index * 0.04) ?>s">
                            <p class="text-xs text-gray-400 mb-2">Posted <?= time_ago($job['created_at']) ?> · Proposals: <?= $job['proposal_count'] > 50 ? '50+' : $job['proposal_count'] ?></p>
                            <a href="/jobhub/freelancer/job_detail.php?id=<?= $job['id'] ?>" class="block text-lg font-bold text-gray-900 hover:text-[#4338CA] transition-colors mb-1.5"><?= decode_over_encoded($job['title']) ?></a>
                            <p class="text-sm text-gray-500 mb-3">
                                <?php if (!empty($job['job_type'])): ?>
                                    <?= sanitize_string($jobTypeLabels[$job['job_type']] ?? ucfirst($job['job_type'])) ?>: <?= format_currency($job['budget']) ?>
                                <?php else: ?>
                                    <?= format_currency($job['budget']) ?>
                                <?php endif; ?>
                                <?php if (!empty($job['experience_level'])): ?> – <?= sanitize_string($levelLabels[$job['experience_level']] ?? ucfirst($job['experience_level'])) ?><?php endif; ?>
                            </p>
                            <p class="text-sm text-gray-600 leading-relaxed mb-4 line-clamp-3"><?= sanitize_string(mb_strimwidth($job['description'], 0, 250, '...')) ?></p>
                            <?php if (!empty($job['skills'])): ?>
                            <div class="flex flex-wrap gap-2 mb-4">
                                <?php foreach (array_slice($job['skills'], 0, 6) as $sk): ?>
                                    <span class="inline-block px-3 py-1.5 rounded-lg text-xs font-medium bg-gray-100 text-gray-700"><?= sanitize_string($sk['skill_name']) ?></span>
                                <?php endforeach; ?>
                                <?php if (count($job['skills']) > 6): ?>
                                    <span class="inline-block px-3 py-1.5 rounded-lg text-xs font-medium bg-gray-100 text-gray-500">+<?= count($job['skills']) - 6 ?> more</span>
                                <?php endif; ?>
                            </div>
                            <?php endif; ?>
                            <a href="/jobhub/freelancer/job_detail.php?id=<?= $job['id'] ?>" class="inline-flex items-center justify-center gap-2 px-5 py-2.5 border border-gray-300 text-gray-700 hover:border-[#4338CA] hover:text-[#4338CA] text-xs font-semibold rounded-[10px] transition-all">View Details</a>
                        </div>
                    <?php endforeach; ?>
                    </div>

                    <?php if ($pagination['total_pages'] > 1): ?>
                    <div class="flex items-center justify-between bg-white rounded-[10px] p-4 border border-[#E5E8EB] fade-in">
                        <p class="text-xs text-gray-400">Page <span class="font-semibold text-gray-600"><?= $pagination['current_page'] ?></span> of <span class="font-semibold text-gray-600"><?= $pagination['total_pages'] ?></span></p>
                        <div class="flex items-center gap-1">
                            <?php if ($pagination['has_prev']): ?>
                                <a href="<?= $baseUrl ?>&page=<?= $pagination['current_page'] - 1 ?>" class="w-9 h-9 flex items-center justify-center rounded-[10px] border border-gray-200 text-gray-500 hover:bg-gray-50 text-sm transition-all"><i data-lucide="chevron-left" class="w-4 h-4"></i></a>
                            <?php endif; ?>
                            <?php
                            $startPage = max(1, $pagination['current_page'] - 2);
                            $endPage   = min($pagination['total_pages'], $pagination['current_page'] + 2);
                            for ($i = $startPage; $i <= $endPage; $i++): ?>
                                <a href="<?= $baseUrl ?>&page=<?= $i ?>" class="w-9 h-9 flex items-center justify-center rounded-[10px] text-sm font-medium transition-all <?= $i === $pagination['current_page'] ? 'bg-[#4338CA] text-white shadow-sm' : 'text-gray-500 hover:bg-gray-50' ?>"><?= $i ?></a>
                            <?php endfor; ?>
                            <?php if ($pagination['has_next']): ?>
                                <a href="<?= $baseUrl ?>&page=<?= $pagination['current_page'] + 1 ?>" class="w-9 h-9 flex items-center justify-center rounded-[10px] border border-gray-200 text-gray-500 hover:bg-gray-50 text-sm transition-all"><i data-lucide="chevron-right" class="w-4 h-4"></i></a>
                            <?php endif; ?>
                        </div>
                    </div>
                    <?php endif; ?>

                <?php else: ?>
                    <div class="bg-white rounded-[10px] border border-[#E5E8EB] fade-in">
                        <div class="text-center py-16 px-6">
                            <div class="w-20 h-20 rounded-[10px] bg-[#F5F7F9] flex items-center justify-center mx-auto mb-5"><i data-lucide="search" class="w-8 h-8 text-gray-300"></i></div>
                            <h3 class="text-lg font-bold text-gray-900 mb-2">No jobs found</h3>
                            <p class="text-sm text-gray-400 mb-6 max-w-md mx-auto">
                                <?= ($search !== '' || $budgetMin > 0 || $budgetMax > 0 || $datePosted !== 'all' || !empty($skillIds))
                                    ? 'Try adjusting your filters or search terms.'
                                    : 'Check back later for new job postings.' ?>
                            </p>
                            <?php if ($search !== '' || $budgetMin > 0 || $budgetMax > 0 || $datePosted !== 'all' || !empty($skillIds)): ?>
                                <a href="/jobhub/browse_jobs.php" class="inline-flex items-center gap-2 bg-[#4338CA] hover:bg-[#3730A3] text-white font-bold px-6 py-3 rounded-[10px] text-sm transition-all"><i data-lucide="x" class="w-4 h-4"></i> Clear All Filters</a>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </form>
</main>

<!-- ═══════════════════════ FOOTER ═══════════════════════════════════════ -->
<footer id="footer" class="border-t border-gray-200 bg-surface pt-20 pb-8 px-6">
    <div class="max-w-[1320px] mx-auto">
      <div class="grid sm:grid-cols-2 lg:grid-cols-5 gap-12 mb-16">

        <!-- Brand -->
        <div class="lg:col-span-2">
          <a href="/jobhub/index.php" class="flex items-center gap-2 group shrink-0 mb-5">
            <img src="/jobhub/assets/upload/logos/logo.png" alt="Logo" class="w-[30px] h-[30px] rounded-lg">
            <span class="text-lg font-bold tracking-tight font-serif">
              <span class="text-charcoal">Job</span><span class="text-[#4338CA]">Hub</span>
            </span>
          </a>
          <p class="text-gray-400 text-[12px] leading-relaxed mb-6 max-w-xs">The world's most trusted marketplace for top freelancers and innovative clients. Work smarter, together.</p>
          <div class="flex gap-2">
            <a href="#" class="w-8 h-8 bg-gray-50 rounded flex items-center justify-center text-gray-400 hover:text-charcoal hover:bg-gray-100 transition-all border border-gray-100"><svg class="w-3 h-3" fill="currentColor" viewBox="0 0 24 24"><path d="M18.244 2.25h3.308l-7.227 8.26 8.502 11.24H16.17l-5.214-6.817L4.99 21.75H1.68l7.73-8.835L1.254 2.25H8.08l4.713 6.231zm-1.161 17.52h1.833L7.084 4.126H5.117z"/></svg></a>
            <a href="#" class="w-8 h-8 bg-gray-50 rounded flex items-center justify-center text-gray-400 hover:text-charcoal hover:bg-gray-100 transition-all border border-gray-100"><svg class="w-3 h-3" fill="currentColor" viewBox="0 0 24 24"><path d="M20.447 20.452h-3.554v-5.569c0-1.328-.027-3.037-1.852-3.037-1.853 0-2.136 1.445-2.136 2.939v5.667H9.351V9h3.414v1.561h.046c.477-.9 1.637-1.85 3.37-1.85 3.601 0 4.267 2.37 4.267 5.455v6.286zM5.337 7.433c-1.144 0-2.063-.926-2.063-2.065 0-1.138.92-2.063 2.063-2.063 1.14 0 2.064.925 2.064 2.063 0 1.139-.925 2.065-2.064 2.065zm1.782 13.019H3.555V9h3.564v11.452zM22.225 0H1.771C.792 0 0 .774 0 1.729v20.542C0 23.227.792 24 1.771 24h20.451C23.2 24 24 23.227 24 22.271V1.729C24 .774 23.2 0 22.222 0h.003z"/></svg></a>
            <a href="#" class="w-8 h-8 bg-gray-50 rounded flex items-center justify-center text-gray-400 hover:text-charcoal hover:bg-gray-100 transition-all border border-gray-100"><svg class="w-3 h-3" fill="currentColor" viewBox="0 0 24 24"><path d="M12 2.163c3.204 0 3.584.012 4.85.07 3.252.148 4.771 1.691 4.919 4.919.058 1.265.069 1.645.069 4.849 0 3.205-.012 3.584-.069 4.849-.149 3.225-1.664 4.771-4.919 4.919-1.266.058-1.644.07-4.85.07-3.204 0-3.584-.012-4.849-.07-3.26-.149-4.771-1.699-4.919-4.92-.058-1.265-.07-1.644-.07-4.849 0-3.204.013-3.583.07-4.849.149-3.227 1.664-4.771 4.919-4.919 1.266-.057 1.645-.069 4.849-.069zM12 0C8.741 0 8.333.014 7.053.072 2.695.272.273 2.69.073 7.052.014 8.333 0 8.741 0 12c0 3.259.014 3.668.072 4.948.2 4.358 2.618 6.78 6.98 6.98C8.333 23.986 8.741 24 12 24c3.259 0 3.668-.014 4.948-.072 4.354-.2 6.782-2.618 6.979-6.98.059-1.28.073-1.689.073-4.948 0-3.259-.014-3.667-.072-4.947-.196-4.354-2.617-6.78-6.979-6.98C15.668.014 15.259 0 12 0zm0 5.838a6.162 6.162 0 100 12.324 6.162 6.162 0 000-12.324zM12 16a4 4 0 110-8 4 4 0 010 8zm6.406-11.845a1.44 1.44 0 100 2.881 1.44 1.44 0 000-2.881z"/></svg></a>
            <a href="#" class="w-8 h-8 bg-gray-50 rounded flex items-center justify-center text-gray-400 hover:text-charcoal hover:bg-gray-100 transition-all border border-gray-100"><svg class="w-3 h-3" fill="currentColor" viewBox="0 0 24 24"><path d="M12 0c-6.626 0-12 5.373-12 12 0 5.302 3.438 9.8 8.207 11.387.599.111.793-.261.793-.577v-2.234c-3.338.726-4.033-1.416-4.033-1.416-.546-1.387-1.333-1.756-1.333-1.756-1.089-.745.083-.729.083-.729 1.205.084 1.839 1.237 1.839 1.237 1.07 1.834 2.807 1.304 3.492.997.107-.775.418-1.305.762-1.604-2.665-.305-5.467-1.334-5.467-5.931 0-1.311.469-2.381 1.236-3.221-.124-.303-.535-1.524.117-3.176 0 0 1.008-.322 3.301 1.23.957-.266 1.983-.399 3.003-.404 1.02.005 2.047.138 3.006.404 2.291-1.552 3.297-1.23 3.297-1.23.653 1.653.242 2.874.118 3.176.77.84 1.235 1.911 1.235 3.221 0 4.609-2.807 5.624-5.479 5.921.43.372.823 1.102.823 2.222v3.293c0 .319.192.694.801.576 4.765-1.589 8.199-6.086 8.199-11.386 0-6.627-5.373-12-12-12z"/></svg></a>
            <a href="#" class="w-8 h-8 bg-gray-50 rounded flex items-center justify-center text-gray-400 hover:text-red-500 hover:bg-red-50 transition-all border border-gray-100"><svg class="w-3 h-3" fill="currentColor" viewBox="0 0 24 24"><path d="M23.498 6.186a3.016 3.016 0 0 0-2.122-2.136C19.505 3.545 12 3.545 12 3.545s-7.505 0-9.377.505A3.017 3.017 0 0 0 .502 6.186C0 8.07 0 12 0 12s0 3.93.502 5.814a3.016 3.016 0 0 0 2.122 2.136c1.871.505 9.376.505 9.376.505s7.505 0 9.377-.505a3.015 3.015 0 0 0 2.122-2.136C24 15.93 24 12 24 12s0-3.93-.502-5.814zM9.545 15.568V8.432L15.818 12l-6.273 3.568z"/></svg></a>
          </div>
        </div>

        <!-- Company -->
        <div>
          <h4 class="text-charcoal font-semibold text-[11px] uppercase tracking-widest mb-5">Company</h4>
          <ul class="space-y-3 text-gray-400 text-[12px]">
            <li><a href="/jobhub/index.php#about" class="hover:text-charcoal transition-colors">About Us</a></li>
            <li><a href="/jobhub/careers.php" class="hover:text-charcoal transition-colors">Careers</a></li>
            <li><a href="/jobhub/blog.php" class="hover:text-charcoal transition-colors">Blog</a></li>
            <li><a href="/jobhub/press.php" class="hover:text-charcoal transition-colors">Press</a></li>
            <li><a href="/jobhub/index.php#pricing" class="hover:text-charcoal transition-colors">Pricing</a></li>
          </ul>
        </div>

        <!-- Support -->
        <div>
          <h4 class="text-charcoal font-semibold text-[11px] uppercase tracking-widest mb-5">Support</h4>
          <ul class="space-y-3 text-gray-400 text-[12px]">
            <li><a href="/jobhub/faq.php" class="hover:text-charcoal transition-colors">FAQ</a></li>
            <li><a href="/jobhub/contact.php" class="hover:text-charcoal transition-colors">Contact Us</a></li>
            <li><a href="/jobhub/privacy.php" class="hover:text-charcoal transition-colors">Privacy Policy</a></li>
            <li><a href="/jobhub/terms.php" class="hover:text-charcoal transition-colors">Terms &amp; Conditions</a></li>
            <li><a href="/jobhub/dispute.php" class="hover:text-charcoal transition-colors">Dispute Resolution</a></li>
          </ul>
        </div>

        <!-- Contact -->
        <div>
          <h4 class="text-charcoal font-semibold text-[11px] uppercase tracking-widest mb-5">Contact</h4>
          <ul class="space-y-3 text-gray-400 text-[12px]">
            <li class="flex items-start gap-2"><i data-lucide="mail" class="w-3 h-3 text-[#4338CA] mt-0.5"></i><a href="mailto:hello@freelancehub.io" class="hover:text-charcoal transition-colors">hello@freelancehub.io</a></li>
            <li class="flex items-start gap-2"><i data-lucide="phone" class="w-3 h-3 text-[#4338CA] mt-0.5"></i><span>+95 9 757 889806</span></li>
            <li class="flex items-start gap-2"><i data-lucide="map-pin" class="w-3 h-3 text-[#4338CA] mt-0.5"></i><span>Myanmar, Yangon 11041</span></li>
          </ul>
          <div class="mt-6">
            <p class="text-gray-500 text-[10px] font-semibold mb-2 tracking-wide uppercase">Stay in the loop</p>
            <form action="/jobhub/actions/newsletter_process.php" method="POST" class="flex gap-2">
              <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?? ''; ?>">
              <input type="email" name="email" placeholder="your@email.com" required
                class="flex-1 bg-surface border border-gray-200 input-elegant px-3 py-2 text-[11px] text-charcoal placeholder-gray-400" />
              <button type="submit" class="btn-primary text-white text-[11px] font-semibold px-3 py-2">
                <i data-lucide="send" class="w-3 h-3"></i>
              </button>
            </form>
          </div>

        </div>
      </div>

      <div class="border-t border-gray-100 pt-6 flex flex-col sm:flex-row items-center justify-between gap-3 text-gray-400 text-[11px]">
        <p>&copy; <?= date('Y') ?> JobHub. All rights reserved.</p>
        <div class="flex gap-5">
          <a href="/jobhub/privacy.php" class="hover:text-charcoal transition-colors">Privacy</a>
          <a href="/jobhub/terms.php" class="hover:text-charcoal transition-colors">Terms</a>
          <a href="/jobhub/faq.php" class="hover:text-charcoal transition-colors">FAQ</a>
        </div>
      </div>
    </div>
</footer>

<!-- Back to Top -->
<button id="back-top" class="fixed bottom-6 right-6 w-10 h-10 bg-charcoal text-white flex items-center justify-center hidden hover:bg-charcoal-light transition-all z-50" onclick="window.scrollTo({top:0,behavior:'smooth'})" style="border-radius:4px">
    <i data-lucide="chevron-up" class="w-3 h-3"></i>
</button>

<!-- ═══════════════════════ JAVASCRIPT ═══════════════════════════════ -->
<script>
    // Scroll progress & navbar
    const progress = document.getElementById('progress');
    const navbar = document.getElementById('navbar');
    const backTop = document.getElementById('back-top');

    window.addEventListener('scroll', () => {
      const scrolled = (window.scrollY / (document.body.scrollHeight - window.innerHeight)) * 100;
      progress.style.width = scrolled + '%';
      navbar.classList.toggle('scrolled', window.scrollY > 50);
      backTop.classList.toggle('hidden', window.scrollY < 400);
      backTop.classList.toggle('flex', window.scrollY >= 400);
    });

    // Mobile hamburger
    document.getElementById('hamburger').addEventListener('click', () => {
      document.getElementById('mobile-menu').classList.toggle('open');
    });

    // Search dropdowns
    function setupSearchDropdown(dropdownId, btnId, inputId, typeSpanId) {
      const dropdown = document.getElementById(dropdownId);
      const btn = document.getElementById(btnId);
      const input = document.getElementById(inputId);
      const typeSpan = document.getElementById(typeSpanId);
      btn.addEventListener('click', e => { e.stopPropagation(); dropdown.classList.toggle('hidden'); });
      dropdown.querySelectorAll('button').forEach(opt => {
        opt.addEventListener('click', () => {
          typeSpan.textContent = opt.dataset.type === 'talent' ? 'Talent' : 'Jobs';
          input.placeholder = opt.dataset.placeholder;
          input.dataset.searchType = opt.dataset.type;
          dropdown.classList.add('hidden');
          input.focus();
        });
      });
      document.addEventListener('click', () => dropdown.classList.add('hidden'));
    }
    setupSearchDropdown('navSearchDropdown', 'navSearchDropdownBtn', 'nav-search', 'navSearchType');

    // Nav search
    document.getElementById('nav-search').addEventListener('keydown', e => {
      if (e.key === 'Enter') {
        const q = e.target.value.trim();
        const type = e.target.dataset.searchType || 'talent';
        if (q) window.location.href = (type === 'talent' ? '/jobhub/browse_freelancers.php?search=' : '/jobhub/browse_jobs.php?search=') + encodeURIComponent(q);
      }
    });

    // Close mobile menu on link click
    document.querySelectorAll('#mobile-menu a').forEach(a => {
      a.addEventListener('click', () => document.getElementById('mobile-menu').classList.remove('open'));
    });

    // Profile dropdown close on outside click
    document.addEventListener('click', e => {
      const pd = document.getElementById('navProfileDropdown');
      if (pd && !pd.contains(e.target)) {
        const popup = pd.querySelector('.profile-popup');
        if (popup) popup.classList.remove('show');
      }
    });
</script>
<script>
function fixIcons() {
    lucide.createIcons();
    document.querySelectorAll('svg[data-lucide]').forEach(function(svg) {
        svg.removeAttribute('width');svg.removeAttribute('height');
        svg.style.removeProperty('width');svg.style.removeProperty('height');
        var p = svg.parentElement;
        if (p && p.tagName === 'I') { var fs = window.getComputedStyle(p).fontSize; svg.style.width = fs; svg.style.height = fs; }
    });
}
fixIcons();
</script>
</body>
</html>
