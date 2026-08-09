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

$search        = trim(scalar_param($_GET['search'] ?? ''));
$rateMin       = max(0, floatval(scalar_param($_GET['rate_min'] ?? 0)));
$rateMax       = max(0, floatval(scalar_param($_GET['rate_max'] ?? 0)));
$skillIds      = array_filter(array_map('intval', $_GET['skills'] ?? []));
$availability  = scalar_param($_GET['availability'] ?? '', 'all');
$experienceMin = max(0, intval(scalar_param($_GET['experience_min'] ?? 0)));
$experienceMax = max(0, intval(scalar_param($_GET['experience_max'] ?? 0)));
$sortBy        = scalar_param($_GET['sort'] ?? '', 'newest');
$page          = max(1, intval($_GET['page'] ?? 1));
$perPage       = 12;

$allowedSort        = ['newest', 'rate_high', 'rate_low', 'rating'];
$allowedAvail       = ['all', 'Available', 'Busy', 'Unavailable'];
if (!in_array($sortBy, $allowedSort))        $sortBy = 'newest';
if (!in_array($availability, $allowedAvail)) $availability = 'all';

// ── Build query conditions ────────────────────────────────────────────────
$where  = [];
$params = [];
$types  = '';

$where[] = "u.role = 'freelancer'";
$where[] = "u.status = 'active'";

if ($rateMin > 0) {
    $where[]  = 'f.hourly_rate >= ?';
    $params[] = $rateMin;
    $types   .= 'd';
}
if ($rateMax > 0) {
    $where[]  = 'f.hourly_rate <= ?';
    $params[] = $rateMax;
    $types   .= 'd';
}
if ($experienceMin > 0) {
    $where[]  = 'f.years_of_experience >= ?';
    $params[] = $experienceMin;
    $types   .= 'i';
}
if ($experienceMax > 0) {
    $where[]  = 'f.years_of_experience <= ?';
    $params[] = $experienceMax;
    $types   .= 'i';
}
if ($availability !== 'all') {
    $where[]  = 'f.availability = ?';
    $params[] = $availability;
    $types   .= 's';
}
if ($search !== '') {
    $safeSearch = '%' . $search . '%';
    $where[]   = '(LOWER(u.name) LIKE LOWER(?) OR LOWER(f.title) LIKE LOWER(?) OR f.id IN (SELECT fs.freelancer_id FROM freelancer_skills fs INNER JOIN skills s ON fs.skill_id = s.id WHERE LOWER(s.skill_name) LIKE LOWER(?)))';
    $params[]  = $safeSearch;
    $params[]  = $safeSearch;
    $params[]  = $safeSearch;
    $types    .= 'sss';
}
if (!empty($skillIds)) {
    $placeholders = implode(',', array_fill(0, count($skillIds), '?'));
    $where[]      = "f.id IN (SELECT fs.freelancer_id FROM freelancer_skills fs WHERE fs.skill_id IN ($placeholders) GROUP BY fs.freelancer_id HAVING COUNT(DISTINCT fs.skill_id) = " . count($skillIds) . ')';
    $params       = array_merge($params, $skillIds);
    $types       .= str_repeat('i', count($skillIds));
}

// ── All skills for filter sidebar ─────────────────────────────────────────
$allSkills = [];
$skillResult = $conn->query('SELECT id, skill_name, category FROM skills ORDER BY category, skill_name');
while ($sk = $skillResult->fetch_assoc()) {
    $allSkills[$sk['id']] = $sk;
}

// ── Count total ───────────────────────────────────────────────────────────
$whereSQL = implode(' AND ', $where);
$countSql = "SELECT COUNT(DISTINCT f.id) AS total
             FROM users u
             JOIN freelancers f ON u.id = f.user_id
             LEFT JOIN (
                 SELECT reviewee_id, ROUND(AVG(rating),1) AS avg_rating, COUNT(*) AS review_count
                 FROM reviews GROUP BY reviewee_id
             ) r ON u.id = r.reviewee_id
             WHERE $whereSQL";
$countStmt = $conn->prepare($countSql);
if ($types !== '') $countStmt->bind_param($types, ...$params);
$countStmt->execute();
$totalFiltered = $countStmt->get_result()->fetch_assoc()['total'];
$countStmt->close();

$pagination = paginate($totalFiltered, $perPage, $page);

$orderMap = [
    'newest'   => 'u.created_at DESC',
    'rate_high' => 'f.hourly_rate DESC',
    'rate_low'  => 'f.hourly_rate ASC',
    'rating'   => 'COALESCE(r.avg_rating,0) DESC, r.review_count DESC',
];
$orderBy = $orderMap[$sortBy] ?? 'u.created_at DESC';

// ── Fetch freelancers ─────────────────────────────────────────────────────
$querySql = "SELECT u.id, u.name, u.profile_image, u.created_at,
                    f.id AS freelancer_id, f.title, f.hourly_rate, f.years_of_experience,
                    f.availability, f.completed_jobs, f.total_earnings,
                    COALESCE(r.avg_rating,0) AS avg_rating,
                    COALESCE(r.review_count,0) AS review_count
             FROM users u
             JOIN freelancers f ON u.id = f.user_id
             LEFT JOIN (
                 SELECT reviewee_id, ROUND(AVG(rating),1) AS avg_rating, COUNT(*) AS review_count
                 FROM reviews GROUP BY reviewee_id
             ) r ON u.id = r.reviewee_id
             WHERE $whereSQL
             ORDER BY $orderBy
             LIMIT ? OFFSET ?";

$finalTypes  = $types . 'ii';
$finalParams = array_merge($params, [$pagination['per_page'], $pagination['offset']]);
$stmt = $conn->prepare($querySql);
$stmt->bind_param($finalTypes, ...$finalParams);
$stmt->execute();
$flResult = $stmt->get_result();
$stmt->close();

$flIds      = [];
$freelancers = [];
while ($row = $flResult->fetch_assoc()) {
    $flIds[]              = $row['id'];
    $row['skills']        = [];
    $freelancers[$row['id']] = $row;
}

if (!empty($flIds)) {
    $flPlaceholders = implode(',', array_fill(0, count($flIds), '?'));
    $flTypes        = str_repeat('i', count($flIds));
    $skStmt = $conn->prepare("SELECT fs.freelancer_id, s.skill_name, s.category FROM freelancer_skills fs JOIN skills s ON fs.skill_id = s.id WHERE fs.freelancer_id IN ($flPlaceholders) ORDER BY s.skill_name");
    $skStmt->bind_param($flTypes, ...$flIds);
    $skStmt->execute();
    $skRes = $skStmt->get_result();
    while ($sr = $skRes->fetch_assoc()) {
        if (isset($freelancers[$sr['freelancer_id']])) {
            $freelancers[$sr['freelancer_id']]['skills'][] = $sr;
        }
    }
    $skStmt->close();
}

function buildBaseUrl(array $overrides = []): string
{
    $params = array_merge($_GET, $overrides);
    unset($params['page']);
    $qs = http_build_query(array_filter($params, fn($v) => $v !== '' && $v !== null));
    return '/jobhub/browse_freelancers.php' . ($qs ? '?' . $qs : '');
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
    <title>Browse Freelancers – JobHub</title>
    <meta name="description" content="Find top freelancers on JobHub. Browse professionals by skill, rate, and availability."/>
    <link rel="stylesheet" href="/jobhub/assets/css/theme.css">
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://unpkg.com/lucide@0.344.0/dist/umd/lucide.min.js"></script>
    <link rel="icon" type="image/png" sizes="32x32" href="assets/upload/logos/logo.png">
    <script>
    tailwind.config = {
        theme: {
            extend: {
                fontFamily: {
                    sans: ['Inter', 'system-ui', '-apple-system', 'Segoe UI', 'Roboto', 'Helvetica Neue', 'sans-serif'],
                    heading: ['Inter', 'system-ui', 'sans-serif'],
                    serif: ['Inter', 'Georgia', 'serif'],
                },
                colors: {
                    primary: { DEFAULT: '#4338CA', dark: '#3730A3', light: '#6366F1' },
                    charcoal: { DEFAULT: '#1A1D23', light: '#2D3039' },
                    surface: { DEFAULT: '#FDFDFD', alt: '#F5F5F4' },
                },
                boxShadow: {
                    'premium': '0 4px 6px -1px rgba(0, 0, 0, 0.05), 0 10px 15px -3px rgba(0, 0, 0, 0.05)',
                    'premium-hover': '0 20px 40px -12px rgba(67, 56, 202, 0.12)',
                    'premium-lg': '0 10px 25px -5px rgba(0, 0, 0, 0.04), 0 20px 40px -10px rgba(0, 0, 0, 0.04)',
                },
            }
        }
    }
    </script>
    <style>
    *,*::before,*::after{font-family:'Inter',system-ui,-apple-system,'Segoe UI',Roboto,'Helvetica Neue',Arial,sans-serif}
    body{background:#f9fafa;color:#374151;-webkit-font-smoothing:antialiased;-moz-osx-font-smoothing:grayscale}
    h1,h2,h3,h4,h5,h6{font-family:'Inter',system-ui,sans-serif;font-weight:700;letter-spacing:-0.025em}
    .grad-text{background:linear-gradient(135deg,#4338CA 0%,#6366F1 60%,#818CF8 100%);-webkit-background-clip:text;-webkit-text-fill-color:transparent;background-clip:text}
    .nav-link{position:relative}
    .nav-link::after{content:'';position:absolute;bottom:-2px;left:0;width:0;height:2px;background:linear-gradient(90deg,#4338CA,#6366F1);transition:width .3s}
    .nav-link:hover::after{width:100%}
    .btn-primary{background:#4338CA;transition:all .25s;color:#fff;border-radius:8px}
    .btn-primary:hover{background:#3730A3;transform:translateY(-1px);box-shadow:0 4px 12px rgba(67,56,202,.25)}
    .btn-outline{border:1px solid #E5E7EB;background:#fff;color:#374151;transition:all .25s;border-radius:8px}
    .btn-outline:hover{border-color:#4338CA;background:#F5F3FF;color:#4338CA}
    .reveal{opacity:0;transform:translateY(30px);transition:opacity .7s ease,transform .7s ease}
    .reveal.visible{opacity:1;transform:translateY(0)}
    #navbar.scrolled{background:rgba(253,253,253,.96);backdrop-filter:blur(16px);border-bottom:1px solid #E8E8E8}
    #mobile-menu{transition:max-height .35s ease,opacity .3s ease;max-height:0;opacity:0;overflow:hidden}
    #mobile-menu.open{max-height:600px;opacity:1}
    .profile-popup{display:none;position:absolute;top:calc(100% + 8px);right:0;background:#fff;border:1px solid #E8E8E8;border-radius:12px;box-shadow:0 20px 60px rgba(0,0,0,.1);min-width:220px;z-index:50;overflow:hidden}
    .profile-popup.show{display:block}
    .input-elegant{border:1px solid #E5E7EB;border-radius:8px;transition:border-color .25s,box-shadow .25s}
    .input-elegant:focus{border-color:#4338CA;outline:none;box-shadow:0 0 0 3px rgba(67,56,202,.1)}
    ::selection{background:#4338CA;color:#fff}
    .label-tag{font-family:'Inter',sans-serif;font-size:10px;font-weight:600;letter-spacing:.12em;text-transform:uppercase;color:#9CA3AF}
    .custom-scrollbar::-webkit-scrollbar{width:4px}
    .custom-scrollbar::-webkit-scrollbar-track{background:transparent}
    .custom-scrollbar::-webkit-scrollbar-thumb{background:#E5E7EB;border-radius:4px}
    .fade-in{animation:fadeUp .6s ease forwards;opacity:0}
    @keyframes fadeUp{0%{opacity:0;transform:translateY(24px)}100%{opacity:1;transform:translateY(0)}}

    .filter-card{background:#fff;border:1px solid #F0F0F0;border-radius:12px;padding:16px;box-shadow:0 1px 3px rgba(0,0,0,.02);transition:all .3s ease}
    .filter-card:hover{box-shadow:0 4px 12px rgba(0,0,0,.04)}
    .filter-section-toggle{cursor:pointer;display:flex;align-items:center;justify-content:space-between;width:100%;padding:0;user-select:none}
    .filter-section-toggle .chevron{transition:transform .25s ease}
    .filter-section-toggle.collapsed .chevron{transform:rotate(-90deg)}
    .filter-section-body{overflow:hidden;transition:max-height .3s ease,opacity .25s ease;max-height:2000px;opacity:1}
    .filter-section-body.collapsed{max-height:0;opacity:0}

    .skill-search-input{width:100%;padding:8px 12px;border:1px solid #E5E7EB;border-radius:8px;font-size:13px;outline:none;transition:border-color .2s}
    .skill-search-input:focus{border-color:#4338CA;box-shadow:0 0 0 2px rgba(67,56,202,.08)}
    .skill-search-input::placeholder{color:#9CA3AF}

    .fl-listing{background:#fff;border:1px solid #F0F0F0;border-radius:16px;padding:28px;transition:all .35s ease;box-shadow:0 1px 3px rgba(0,0,0,.02)}
    .fl-listing:hover{box-shadow:0 12px 32px -8px rgba(67,56,202,.08);border-color:#E0E0F0;transform:translateY(-2px)}
    .fl-listing+.fl-listing{margin-top:12px}

    .skill-pill{display:inline-flex;align-items:center;padding:4px 12px;border-radius:100px;font-size:12px;font-weight:500;background:#F3F4F6;color:#4B5563;border:1px solid #F0F0F0;transition:all .2s ease}
    .skill-pill:hover{background:#EEF2FF;color:#4338CA;border-color:#C7D2FE}

    .active-filter-chip{display:inline-flex;align-items:center;gap:6px;padding:6px 14px;border-radius:100px;font-size:12px;font-weight:600;background:#EEF2FF;color:#4338CA;border:1px solid #C7D2FE;transition:all .2s}
    .active-filter-chip:hover{background:#E0E7FF}
    .active-filter-chip .remove{cursor:pointer;opacity:.6;transition:opacity .2s}
    .active-filter-chip .remove:hover{opacity:1}

    .range-slider{-webkit-appearance:none;appearance:none;width:100%;height:4px;border-radius:4px;background:#E5E7EB;outline:none;transition:all .2s}
    .range-slider::-webkit-slider-thumb{-webkit-appearance:none;appearance:none;width:18px;height:18px;border-radius:50%;background:#4338CA;cursor:pointer;border:3px solid #fff;box-shadow:0 1px 4px rgba(67,56,202,.3);transition:all .2s}
    .range-slider::-webkit-slider-thumb:hover{transform:scale(1.15);box-shadow:0 2px 8px rgba(67,56,202,.4)}
    .range-slider::-moz-range-thumb{width:18px;height:18px;border-radius:50%;background:#4338CA;cursor:pointer;border:3px solid #fff;box-shadow:0 1px 4px rgba(67,56,202,.3)}

    .custom-radio{position:relative;cursor:pointer;display:flex;align-items:center;gap:10px;padding:8px 10px;border-radius:8px;transition:all .2s}
    .custom-radio:hover{background:#F9FAFB}
    .custom-radio input[type="radio"]{position:absolute;opacity:0;width:1px;height:1px;margin:0;pointer-events:none}
    .custom-radio .radio-dot{position:relative;width:16px;height:16px;border:2px solid #D1D5DB;border-radius:50%;transition:all .2s;flex-shrink:0}
    .custom-radio .radio-dot::after{content:'';position:absolute;top:50%;left:50%;transform:translate(-50%,-50%);width:6px;height:6px;background:#fff;border-radius:50%;opacity:0;transition:opacity .2s}
    label.custom-radio:has(input:checked) .radio-dot,
    label.custom-radio.is-checked .radio-dot{border-color:#4338CA;background:#4338CA}
    label.custom-radio:has(input:checked) .radio-dot::after,
    label.custom-radio.is-checked .radio-dot::after{opacity:1}
    label.custom-radio:has(input:checked) .label-text,
    label.custom-radio.is-checked .label-text{font-weight:600;color:#1A1D23}

    .custom-checkbox{position:relative;cursor:pointer;display:flex;align-items:center;gap:10px;padding:7px 10px;border-radius:8px;transition:all .2s}
    .custom-checkbox:hover{background:#F9FAFB}
    .custom-checkbox input[type="checkbox"]{position:absolute;opacity:0;width:1px;height:1px;margin:0;pointer-events:none}
    .custom-checkbox .chk-box{position:relative;width:16px;height:16px;border:2px solid #D1D5DB;border-radius:4px;transition:all .2s;flex-shrink:0}
    .custom-checkbox .chk-box::after{content:'';position:absolute;top:1px;left:4px;width:5px;height:9px;border:solid #fff;border-width:0 2px 2px 0;transform:rotate(45deg);opacity:0;transition:opacity .2s}
    label.custom-checkbox:has(input:checked) .chk-box,
    label.custom-checkbox.is-checked .chk-box{border-color:#4338CA;background:#4338CA}
    label.custom-checkbox:has(input:checked) .chk-box::after,
    label.custom-checkbox.is-checked .chk-box::after{opacity:1}
    label.custom-checkbox:has(input:checked) .label-text,
    label.custom-checkbox.is-checked .label-text{font-weight:600;color:#1A1D23}

    .search-card{background:#fff;border:1px solid #F0F0F0;border-radius:12px;padding:16px;box-shadow:0 1px 3px rgba(0,0,0,.02)}
    .sort-select{appearance:none;background:#fff;border:1px solid #E5E7EB;border-radius:10px;padding:10px 40px 10px 14px;font-size:13px;font-weight:500;color:#6B7280;cursor:pointer;outline:none;transition:all .2s;background-image:url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' fill='none' viewBox='0 0 20 20'%3E%3Cpath stroke='%236b7280' stroke-linecap='round' stroke-linejoin='round' stroke-width='1.5' d='M6 8l4 4 4-4'/%3E%3C/svg%3E");background-position:right 12px center;background-repeat:no-repeat;background-size:1.2em}
    .sort-select:focus{border-color:#4338CA;box-shadow:0 0 0 2px rgba(67,56,202,.08)}

    .pagination-btn{display:flex;align-items:center;justify-content:center;width:36px;height:36px;border-radius:10px;font-size:13px;font-weight:500;border:1px solid #E5E7EB;color:#6B7280;transition:all .2s;background:#fff}
    .pagination-btn:hover{background:#F9FAFB;border-color:#D1D5DB}
    .pagination-btn.active{background:#4338CA;color:#fff;border-color:#4338CA;box-shadow:0 2px 8px rgba(67,56,202,.25)}
    </style>
</head>
<body>

  <!-- Scroll Progress -->
  <div id="progress" class="fixed top-0 left-0 h-[2px] z-[9999] transition-[width] duration-100" style="background:linear-gradient(90deg,#4338CA,#6366F1,#818CF8);width:0"></div>

  <!-- ═══════════════════════ 1. NAVBAR ═══════════════════════════════ -->
  <nav id="navbar" class="fixed top-0 inset-x-0 z-50 transition-all duration-300 py-4 bg-surface/90 backdrop-blur-md">
    <div class="max-w-[1320px] mx-auto px-6 flex items-center justify-between">
      <div class="flex items-center gap-14">
        <!-- Logo -->
        <a href="#" class="flex items-center gap-2 group shrink-0">
          <img src="assets/upload/logos/logo.png" alt="Logo" class="w-[34px] h-[34px] rounded-lg">
          <span class="text-lg font-bold tracking-tight font-serif">
            <span class="text-charcoal">Job</span><span class="text-[#4338CA]">Hub</span>
          </span>
        </a>

        <!-- Desktop links -->
        <div class="hidden lg:flex items-center gap-8">
          <a href="/jobhub/index.php" class="nav-link text-[13px] font-medium text-gray-500 hover:text-charcoal transition-colors tracking-wide">Home</a>
          <a href="/jobhub/browse_jobs.php" class="nav-link text-[13px] font-medium text-gray-500 hover:text-charcoal transition-colors tracking-wide">Find Work</a>
          <a href="/jobhub/browse_freelancers.php" class="nav-link text-[13px] font-semibold text-[#4338CA] transition-colors tracking-wide">Find Freelancers</a>
          <a href="/jobhub/index.php#howitworks" class="nav-link text-[13px] font-medium text-gray-500 hover:text-charcoal transition-colors tracking-wide">How It Works</a>
          <a href="/jobhub/index.php#about" class="nav-link text-[13px] font-medium text-gray-500 hover:text-charcoal transition-colors tracking-wide">About Us</a>
          <a href="/jobhub/index.php#pricing" class="nav-link text-[13px] font-medium text-gray-500 hover:text-charcoal transition-colors tracking-wide">Pricing</a>
        </div>
      </div>

      <div class="flex gap-3 items-center">
        <!--Search with Dropdown-->
        <div class="relative flex items-center bg-surface border border-gray-200 overflow-visible" style="border-radius:4px">
          <div class="flex items-center gap-2 px-3 py-2">
            <i data-lucide="search" class="w-3 h-3 text-gray-400"></i>
            <input id="nav-search" type="text" placeholder="Search"
              class="bg-transparent w-32 sm:w-44 text-charcoal placeholder-gray-400 outline-none text-[13px]" />
          </div>
          <div class="w-px h-5 bg-gray-200"></div>
          <button id="navSearchDropdownBtn" type="button" class="flex items-center gap-1.5 px-3 py-2 cursor-pointer hover:bg-gray-50 transition-colors">
            <span id="navSearchType" class="text-[12px] font-medium text-gray-600">Talent</span>
            <i data-lucide="chevron-down" class="w-2 h-2 text-gray-400"></i>
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
                <a href="freelancer/profile.php" class="flex items-center gap-3 px-4 py-2.5 text-[13px] text-gray-600 hover:bg-gray-50 transition-colors"><i data-lucide="user" class="w-4 h-4 text-center"></i> My Profile</a>
                <?php endif; ?>
              </div>
              <div class="border-t border-gray-100 py-1">
                <a href="auth/logout.php" class="flex items-center gap-3 px-4 py-2.5 text-[13px] text-red-500 hover:bg-red-50 transition-colors"><i data-lucide="log-out" class="w-4 h-4 text-center"></i> Logout</a>
              </div>
            </div>
          </div>
          <?php else: ?>
          <a href="auth/login.php" class="text-[13px] font-medium text-gray-500 hover:text-charcoal transition-colors px-4 py-2">Log In</a>
            <a href="auth/register.php" class="btn-primary text-[13px] font-semibold px-5 py-2">Sign Up</a>
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
        <a href="/jobhub/browse_jobs.php" class="hover:text-charcoal py-2 px-3 rounded hover:bg-gray-50 transition-colors">Find Work</a>
        <a href="/jobhub/browse_freelancers.php" class="text-[#4338CA] font-semibold py-2 px-3 rounded bg-[#EEF2FF] transition-colors">Find Freelancers</a>
        <a href="/jobhub/index.php#howitworks" class="hover:text-charcoal py-2 px-3 rounded hover:bg-gray-50 transition-colors">How It Works</a>
        <a href="/jobhub/index.php#about" class="hover:text-charcoal py-2 px-3 rounded hover:bg-gray-50 transition-colors">About Us</a>
        <a href="/jobhub/index.php#pricing" class="hover:text-charcoal py-2 px-3 rounded hover:bg-gray-50 transition-colors">Pricing</a>
        <hr class="border-gray-100 my-1" />
        <?php if ($_ixLoggedIn): ?>
        <a href="<?= $_ixDash ?>" class="flex items-center gap-3 hover:text-charcoal py-2 px-3 rounded hover:bg-gray-50 transition-colors">
          <img src="<?= htmlspecialchars($_ixAvatar) ?>" class="w-7 h-7 rounded-full object-cover border border-gray-200" alt="Avatar">
          <span class="font-semibold"><?= htmlspecialchars($_ixName) ?></span>
        </a>
        <a href="auth/logout.php" class="hover:text-red-500 py-2 px-3 rounded hover:bg-red-50 transition-colors text-red-500"><i data-lucide="log-out" class="w-4 h-4 mr-2"></i>Logout</a>
        <?php else: ?>
        <a href="auth/login.php" class="hover:text-charcoal py-2 px-3 rounded hover:bg-gray-50 transition-colors">Log In</a>
        <a href="auth/register.php" class="btn-primary text-white text-center py-2 px-3 mt-1">Sign Up</a>
        <?php endif; ?>
      </div>
    </div>
  </nav>

<main class="pt-24 pb-16">
    <form method="GET" id="filterForm">

        <!-- Search Bar Section -->
        <div class="max-w-7xl mx-auto px-4 sm:px-6 pt-6 pb-2">
            <div class="search-card fade-in">
                <div class="flex flex-col sm:flex-row gap-2.5">
                    <div class="relative flex-1">
                        <i data-lucide="search" class="absolute left-3.5 top-1/2 -translate-y-1/2 text-gray-400 w-4 h-4"></i>
                        <input type="text" name="search" value="<?= sanitize_string($search) ?>"
                            placeholder="Search by name, skill, or job title..."
                            class="w-full bg-gray-50 border border-gray-200 rounded-lg pl-10 pr-4 py-2.5 text-sm text-gray-900 placeholder-gray-400 font-medium focus:ring-2 focus:ring-primary focus:border-primary outline-none transition-all">
                    </div>
                    <button type="submit" class="bg-primary hover:bg-primary-dark px-6 py-2.5 text-white text-sm font-semibold rounded-lg flex items-center justify-center gap-2 transition-all shadow-sm hover:shadow-md">
                        <i data-lucide="search" class="w-3.5 h-3.5"></i> Search
                    </button>
                    <?php if ($search !== '' || $rateMin > 0 || $rateMax > 0 || $availability !== 'all' || !empty($skillIds) || $experienceMin > 0 || $experienceMax > 0): ?>
                        <a href="/jobhub/browse_freelancers.php" class="px-5 py-2.5 border border-gray-200 text-gray-500 hover:text-gray-700 hover:border-gray-300 rounded-lg text-sm font-medium transition-all flex items-center justify-center gap-2 bg-white">
                            <i data-lucide="x" class="w-3.5 h-3.5"></i> Clear All
                        </a>
                    <?php endif; ?>
                </div>

                <!-- Active Filter Chips -->
                <?php if ($search !== '' || $rateMin > 0 || $rateMax > 0 || $availability !== 'all' || !empty($skillIds) || $experienceMin > 0 || $experienceMax > 0): ?>
                <div class="flex flex-wrap items-center gap-2 mt-4 pt-4 border-t border-gray-100">
                    <span class="text-xs font-semibold text-gray-400 uppercase tracking-wider">Active:</span>
                    <?php if ($search !== ''): ?>
                        <span class="active-filter-chip">
                            Search: <?= sanitize_string($search) ?>
                            <a href="<?= buildBaseUrl(['search' => '']) ?>" class="remove"><i data-lucide="x" class="w-3 h-3"></i></a>
                        </span>
                    <?php endif; ?>
                    <?php if ($rateMin > 0 || $rateMax > 0): ?>
                        <span class="active-filter-chip">
                            Rate: $<?= $rateMin > 0 ? $rateMin : '0' ?> – $<?= $rateMax > 0 ? $rateMax : '∞' ?>
                            <a href="<?= buildBaseUrl(['rate_min' => '', 'rate_max' => '']) ?>" class="remove"><i data-lucide="x" class="w-3 h-3"></i></a>
                        </span>
                    <?php endif; ?>
                    <?php if ($experienceMin > 0 || $experienceMax > 0): ?>
                        <span class="active-filter-chip">
                            Experience: <?= $experienceMin > 0 ? $experienceMin : '0' ?>–<?= $experienceMax > 0 ? $experienceMax : '∞' ?> yrs
                            <a href="<?= buildBaseUrl(['experience_min' => '', 'experience_max' => '']) ?>" class="remove"><i data-lucide="x" class="w-3 h-3"></i></a>
                        </span>
                    <?php endif; ?>
                    <?php if ($availability !== 'all'): ?>
                        <span class="active-filter-chip">
                            <?= htmlspecialchars($availability) ?>
                            <a href="<?= buildBaseUrl(['availability' => 'all']) ?>" class="remove"><i data-lucide="x" class="w-3 h-3"></i></a>
                        </span>
                    <?php endif; ?>
                    <?php foreach ($skillIds as $sid): ?>
                        <?php if (isset($allSkills[$sid])): ?>
                        <span class="active-filter-chip">
                            <?= sanitize_string($allSkills[$sid]['skill_name']) ?>
                            <a href="<?= buildBaseUrl(['skills' => array_diff($skillIds, [$sid])]) ?>" class="remove"><i data-lucide="x" class="w-3 h-3"></i></a>
                        </span>
                        <?php endif; ?>
                    <?php endforeach; ?>
                </div>
                <?php endif; ?>
            </div>
        </div>

        <div class="max-w-7xl mx-auto px-4 sm:px-6 mt-6">
            <div class="flex gap-8">
                <!-- ═══════ Filter Sidebar ═══════ -->
                <aside id="filterPanel" class="hidden lg:block w-72 flex-shrink-0 space-y-4">

                    <!-- Hourly Rate Range -->
                    <div class="filter-card fade-in">
                        <button type="button" class="filter-section-toggle" onclick="toggleFilter(this)">
                            <h3 class="text-sm font-bold text-gray-900 flex items-center gap-2.5">
                                <span class="w-7 h-7 rounded-lg bg-blue-50 flex items-center justify-center flex-shrink-0"><i data-lucide="dollar-sign" class="w-3.5 h-3.5 text-primary"></i></span>
                                Hourly Rate
                            </h3>
                            <i data-lucide="chevron-down" class="w-4 h-4 text-gray-400 chevron"></i>
                        </button>
                        <div class="filter-section-body mt-3">
                            <div class="space-y-3">
                                <div class="flex items-center justify-between text-xs font-semibold text-gray-500">
                                    <span>$0</span>
                                    <span>$200+</span>
                                </div>
                                <div class="flex items-center gap-3">
                                    <input type="number" name="rate_min" value="<?= $rateMin > 0 ? $rateMin : '' ?>" placeholder="Min" min="0" max="200"
                                        class="w-full bg-gray-50 border border-gray-200 rounded-lg px-3 py-2.5 text-sm font-medium focus:ring-2 focus:ring-primary focus:border-primary outline-none transition-all">
                                    <span class="text-gray-300 font-medium">–</span>
                                    <input type="number" name="rate_max" value="<?= $rateMax > 0 ? $rateMax : '' ?>" placeholder="Max" min="0" max="200"
                                        class="w-full bg-gray-50 border border-gray-200 rounded-lg px-3 py-2.5 text-sm font-medium focus:ring-2 focus:ring-primary focus:border-primary outline-none transition-all">
                                </div>
                            </div>
                            <button type="submit" class="mt-3 w-full py-2 bg-gray-50 hover:bg-gray-100 text-gray-600 text-xs font-semibold rounded-lg transition-colors border border-gray-200">Apply Rate</button>
                        </div>
                    </div>

                    <!-- Availability -->
                    <div class="filter-card fade-in" style="animation-delay:.05s">
                        <button type="button" class="filter-section-toggle" onclick="toggleFilter(this)">
                            <h3 class="text-sm font-bold text-gray-900 flex items-center gap-2.5">
                                <span class="w-7 h-7 rounded-lg bg-emerald-50 flex items-center justify-center flex-shrink-0"><i data-lucide="clock" class="w-3.5 h-3.5 text-emerald-600"></i></span>
                                Availability
                            </h3>
                            <i data-lucide="chevron-down" class="w-4 h-4 text-gray-400 chevron"></i>
                        </button>
                        <div class="filter-section-body mt-3">
                            <div class="space-y-1">
                                <?php foreach (['all' => 'All', 'Available' => 'Available', 'Busy' => 'Busy', 'Unavailable' => 'Unavailable'] as $val => $label): ?>
                                <label class="custom-radio <?= $availability === $val ? '!bg-indigo-50' : '' ?>">
                                    <input type="radio" name="availability" value="<?= $val ?>" <?= $availability === $val ? 'checked' : '' ?> onchange="this.form.submit()">
                                    <span class="radio-dot"></span>
                                    <span class="label-text text-sm text-gray-600"><?= $label ?></span>
                                    <?php if ($val !== 'all'): ?>
                                        <span class="ml-auto">
                                            <?php if ($val === 'Available'): ?>
                                                <span class="w-2 h-2 rounded-full bg-emerald-500 inline-block"></span>
                                            <?php elseif ($val === 'Busy'): ?>
                                                <span class="w-2 h-2 rounded-full bg-amber-400 inline-block"></span>
                                            <?php else: ?>
                                                <span class="w-2 h-2 rounded-full bg-gray-400 inline-block"></span>
                                            <?php endif; ?>
                                        </span>
                                    <?php endif; ?>
                                </label>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    </div>

                    <!-- Experience Level -->
                    <div class="filter-card fade-in" style="animation-delay:.1s">
                        <button type="button" class="filter-section-toggle" onclick="toggleFilter(this)">
                            <h3 class="text-sm font-bold text-gray-900 flex items-center gap-2.5">
                                <span class="w-7 h-7 rounded-lg bg-amber-50 flex items-center justify-center flex-shrink-0"><i data-lucide="briefcase" class="w-3.5 h-3.5 text-amber-600"></i></span>
                                Experience
                            </h3>
                            <i data-lucide="chevron-down" class="w-4 h-4 text-gray-400 chevron"></i>
                        </button>
                        <div class="filter-section-body mt-3">
                            <div class="space-y-3">
                                <div class="flex items-center justify-between text-xs font-semibold text-gray-500">
                                    <span>0 years</span>
                                    <span>20+ years</span>
                                </div>
                                <div class="flex items-center gap-3">
                                    <input type="number" name="experience_min" value="<?= $experienceMin > 0 ? $experienceMin : '' ?>" placeholder="Min" min="0" max="50"
                                        class="w-full bg-gray-50 border border-gray-200 rounded-lg px-3 py-2.5 text-sm font-medium focus:ring-2 focus:ring-primary focus:border-primary outline-none transition-all">
                                    <span class="text-gray-300 font-medium">–</span>
                                    <input type="number" name="experience_max" value="<?= $experienceMax > 0 ? $experienceMax : '' ?>" placeholder="Max" min="0" max="50"
                                        class="w-full bg-gray-50 border border-gray-200 rounded-lg px-3 py-2.5 text-sm font-medium focus:ring-2 focus:ring-primary focus:border-primary outline-none transition-all">
                                </div>
                            </div>
                            <button type="submit" class="mt-3 w-full py-2 bg-gray-50 hover:bg-gray-100 text-gray-600 text-xs font-semibold rounded-lg transition-colors border border-gray-200">Apply Experience</button>
                        </div>
                    </div>

                    <!-- Skills (Collapsible Accordion with Search) -->
                    <div class="filter-card fade-in" style="animation-delay:.15s">
                        <button type="button" class="filter-section-toggle" onclick="toggleFilter(this)">
                            <h3 class="text-sm font-bold text-gray-900 flex items-center gap-2.5">
                                <span class="w-7 h-7 rounded-lg bg-violet-50 flex items-center justify-center flex-shrink-0"><i data-lucide="tags" class="w-3.5 h-3.5 text-violet-600"></i></span>
                                Skills
                                <?php if (!empty($skillIds)): ?>
                                    <span class="ml-1 w-5 h-5 rounded-full bg-primary text-white text-[10px] font-bold flex items-center justify-center"><?= count($skillIds) ?></span>
                                <?php endif; ?>
                            </h3>
                            <i data-lucide="chevron-down" class="w-4 h-4 text-gray-400 chevron"></i>
                        </button>
                        <div class="filter-section-body mt-3">
                            <!-- Skill Search -->
                            <div class="relative mb-3">
                                <i data-lucide="search" class="absolute left-3 top-1/2 -translate-y-1/2 w-3.5 h-3.5 text-gray-400"></i>
                                <input type="text" id="skillSearch" placeholder="Search skills..."
                                    class="skill-search-input pl-9"
                                    oninput="filterSkills(this.value)">
                            </div>
                            <div class="space-y-4 max-h-72 overflow-y-auto pr-1 custom-scrollbar" id="skillsList">
                                <?php
                                $grouped = [];
                                foreach ($allSkills as $skill) { $grouped[$skill['category'] ?: 'General'][] = $skill; }
                                foreach ($grouped as $category => $skills):
                                ?>
                                <div class="skill-group" data-category="<?= sanitize_string($category) ?>">
                                    <p class="text-[10px] font-bold uppercase tracking-wider text-gray-400 mb-2 px-1"><?= sanitize_string($category) ?></p>
                                    <div class="space-y-0.5">
                                        <?php foreach ($skills as $skill): ?>
                                        <label class="custom-checkbox skill-item" data-name="<?= strtolower($skill['skill_name']) ?>">
                                            <input type="checkbox" name="skills[]" value="<?= $skill['id'] ?>" <?= in_array($skill['id'], $skillIds) ? 'checked' : '' ?>>
                                            <span class="chk-box"></span>
                                            <span class="label-text text-sm text-gray-500"><?= sanitize_string($skill['skill_name']) ?></span>
                                        </label>
                                        <?php endforeach; ?>
                                    </div>
                                </div>
                                <?php endforeach; ?>
                            </div>
                            <button type="submit" class="mt-3 w-full py-2 bg-gray-50 hover:bg-gray-100 text-gray-600 text-xs font-semibold rounded-lg transition-colors border border-gray-200">Apply Skills</button>
                        </div>
                    </div>

                    <input type="hidden" name="sort" value="<?= sanitize_string($sortBy) ?>">
                </aside>

                <!-- ═══════ Freelancer Listings ═══════ -->
                <div class="flex-1 min-w-0">
                    <!-- Results Header -->
                    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3 mb-5 fade-in">
                        <div>
                            <p class="text-[15px] text-gray-500">
                                <span class="font-bold text-gray-900"><?= number_format($totalFiltered) ?></span> freelancer<?= $totalFiltered !== 1 ? 's' : '' ?> found
                                <?php if ($search !== ''): ?>
                                    for "<span class="font-semibold text-primary"><?= sanitize_string($search) ?></span>"
                                <?php endif; ?>
                            </p>
                        </div>
                        <select name="sort" onchange="this.form.submit()" class="sort-select">
                            <option value="newest" <?= $sortBy === 'newest' ? 'selected' : '' ?>>Newest First</option>
                            <option value="rate_high" <?= $sortBy === 'rate_high' ? 'selected' : '' ?>>Rate: High to Low</option>
                            <option value="rate_low" <?= $sortBy === 'rate_low' ? 'selected' : '' ?>>Rate: Low to High</option>
                            <option value="rating" <?= $sortBy === 'rating' ? 'selected' : '' ?>>Highest Rated</option>
                        </select>
                    </div>

                    <?php if (!empty($freelancers)): ?>
                        <div class="space-y-3">
                        <?php foreach ($freelancers as $index => $fl): ?>
                            <div class="fl-listing fade-in" style="animation-delay:<?= 0.05 + ($index * 0.04) ?>s">
                                <div class="flex flex-col sm:flex-row gap-5">
                                    <!-- Avatar -->
                                    <div class="flex-shrink-0">
                                        <div class="relative">
                                            <img src="<?= get_profile_image($fl['profile_image']) ?>" alt="<?= sanitize_string($fl['name']) ?>" class="w-16 h-16 rounded-2xl object-cover border-2 border-white shadow-md">
                                            <?php if ($fl['availability'] === 'Available'): ?>
                                                <span class="absolute -bottom-1 -right-1 w-4 h-4 bg-emerald-500 rounded-full border-2 border-white"></span>
                                            <?php elseif ($fl['availability'] === 'Busy'): ?>
                                                <span class="absolute -bottom-1 -right-1 w-4 h-4 bg-amber-400 rounded-full border-2 border-white"></span>
                                            <?php else: ?>
                                                <span class="absolute -bottom-1 -right-1 w-4 h-4 bg-gray-400 rounded-full border-2 border-white"></span>
                                            <?php endif; ?>
                                        </div>
                                    </div>

                                    <!-- Content -->
                                    <div class="flex-1 min-w-0">
                                        <div class="flex flex-col sm:flex-row sm:items-start justify-between gap-3">
                                            <div class="flex-1 min-w-0">
                                                <div class="flex items-center gap-2 mb-1">
                                                    <a href="/jobhub/freelancer/profile.php?user_id=<?= $fl['id'] ?>" class="text-lg font-bold text-gray-900 hover:text-primary transition-colors truncate"><?= sanitize_string($fl['name']) ?></a>
                                                    <?php if ($fl['availability'] === 'Available'): ?>
                                                        <span class="inline-flex items-center gap-1.5 px-2.5 py-0.5 rounded-full text-[11px] font-semibold bg-emerald-50 text-emerald-700 border border-emerald-100 flex-shrink-0">
                                                            <span class="w-1.5 h-1.5 rounded-full bg-emerald-500"></span>
                                                            Available
                                                        </span>
                                                    <?php endif; ?>
                                                </div>
                                                <p class="text-sm text-gray-500 mb-2"><?= sanitize_string($fl['title'] ?: 'Freelancer') ?></p>
                                            </div>

                                            <!-- Rate & Experience (Right aligned) -->
                                            <div class="flex items-center gap-4 flex-shrink-0">
                                                <div class="text-right">
                                                    <div class="text-lg font-bold text-gray-900">$<?= number_format($fl['hourly_rate'], 0) ?><span class="text-sm font-normal text-gray-400">/hr</span></div>
                                                </div>
                                            </div>
                                        </div>

                                        <!-- Stats Row -->
                                        <div class="flex flex-wrap items-center gap-x-5 gap-y-2 mb-3 text-sm text-gray-500">
                                            <?php if ($fl['years_of_experience'] > 0): ?>
                                                <span class="flex items-center gap-1.5">
                                                    <i data-lucide="briefcase" class="w-3.5 h-3.5 text-gray-400"></i>
                                                    <?= $fl['years_of_experience'] ?> yr<?= $fl['years_of_experience'] !== 1 ? 's' : '' ?> experience
                                                </span>
                                            <?php endif; ?>
                                            <?php if ($fl['completed_jobs'] > 0): ?>
                                                <span class="flex items-center gap-1.5">
                                                    <i data-lucide="check-circle" class="w-3.5 h-3.5 text-gray-400"></i>
                                                    <?= $fl['completed_jobs'] ?> job<?= $fl['completed_jobs'] !== 1 ? 's' : '' ?> completed
                                                </span>
                                            <?php endif; ?>
                                            <?php if ($fl['review_count'] > 0): ?>
                                                <span class="flex items-center gap-1.5">
                                                    <svg class="w-3.5 h-3.5 text-amber-400 fill-current" viewBox="0 0 24 24"><path d="M12 2l3.09 6.26L22 9.27l-5 4.87 1.18 6.88L12 17.77l-6.18 3.25L7 14.14 2 9.27l6.91-1.01L12 2z"/></svg>
                                                    <span class="font-semibold text-gray-700"><?= number_format($fl['avg_rating'], 1) ?></span>
                                                    <span class="text-gray-400">(<?= $fl['review_count'] ?>)</span>
                                                </span>
                                            <?php endif; ?>
                                        </div>

                                        <!-- Skills -->
                                        <?php if (!empty($fl['skills'])): ?>
                                        <div class="flex flex-wrap gap-1.5 mb-4">
                                            <?php foreach (array_slice($fl['skills'], 0, 8) as $sk): ?>
                                                <span class="skill-pill"><?= sanitize_string($sk['skill_name']) ?></span>
                                            <?php endforeach; ?>
                                            <?php if (count($fl['skills']) > 8): ?>
                                                <span class="skill-pill !bg-primary/5 !text-primary !border-primary/20">+<?= count($fl['skills']) - 8 ?> more</span>
                                            <?php endif; ?>
                                        </div>
                                        <?php endif; ?>

                                        <!-- View Profile -->
                                        <a href="/jobhub/freelancer/profile.php?user_id=<?= $fl['id'] ?>" class="inline-flex items-center justify-center gap-2 px-5 py-2.5 border border-gray-200 text-gray-600 hover:border-primary hover:text-primary hover:bg-primary/5 text-xs font-semibold rounded-xl transition-all">
                                            View Profile
                                            <i data-lucide="arrow-right" class="w-3.5 h-3.5"></i>
                                        </a>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                        </div>

                        <!-- Pagination -->
                        <?php if ($pagination['total_pages'] > 1): ?>
                        <div class="flex items-center justify-between mt-8 fade-in">
                            <p class="text-xs text-gray-400">Page <span class="font-semibold text-gray-600"><?= $pagination['current_page'] ?></span> of <span class="font-semibold text-gray-600"><?= $pagination['total_pages'] ?></span></p>
                            <div class="flex items-center gap-1.5">
                                <?php if ($pagination['has_prev']): ?>
                                    <a href="<?= $baseUrl ?>&page=<?= $pagination['current_page'] - 1 ?>" class="pagination-btn"><i data-lucide="chevron-left" class="w-4 h-4"></i></a>
                                <?php endif; ?>
                                <?php
                                $startPage = max(1, $pagination['current_page'] - 2);
                                $endPage   = min($pagination['total_pages'], $pagination['current_page'] + 2);
                                for ($i = $startPage; $i <= $endPage; $i++): ?>
                                    <a href="<?= $baseUrl ?>&page=<?= $i ?>" class="pagination-btn <?= $i === $pagination['current_page'] ? 'active' : '' ?>"><?= $i ?></a>
                                <?php endfor; ?>
                                <?php if ($pagination['has_next']): ?>
                                    <a href="<?= $baseUrl ?>&page=<?= $pagination['current_page'] + 1 ?>" class="pagination-btn"><i data-lucide="chevron-right" class="w-4 h-4"></i></a>
                                <?php endif; ?>
                            </div>
                        </div>
                        <?php endif; ?>

                    <?php else: ?>
                        <!-- Empty State -->
                        <div class="bg-white rounded-2xl border border-gray-100 fade-in">
                            <div class="text-center py-20 px-8">
                                <div class="w-20 h-20 rounded-2xl bg-gray-50 flex items-center justify-center mx-auto mb-6">
                                    <i data-lucide="users" class="w-8 h-8 text-gray-300"></i>
                                </div>
                                <h3 class="text-xl font-bold text-gray-900 mb-2">No freelancers found</h3>
                                <p class="text-sm text-gray-400 mb-8 max-w-md mx-auto leading-relaxed">
                                    <?= ($search !== '' || $rateMin > 0 || $rateMax > 0 || $availability !== 'all' || !empty($skillIds) || $experienceMin > 0 || $experienceMax > 0)
                                        ? 'Try adjusting your filters or search terms to find what you\'re looking for.'
                                        : 'Check back later for new freelancers joining the platform.' ?>
                                </p>
                                <?php if ($search !== '' || $rateMin > 0 || $rateMax > 0 || $availability !== 'all' || !empty($skillIds) || $experienceMin > 0 || $experienceMax > 0): ?>
                                    <a href="/jobhub/browse_freelancers.php" class="inline-flex items-center gap-2 bg-primary hover:bg-primary-dark text-white font-semibold px-6 py-3 rounded-xl text-sm transition-all shadow-sm hover:shadow-md">
                                        <i data-lucide="x" class="w-4 h-4"></i> Clear All Filters
                                    </a>
                                <?php endif; ?>
                            </div>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </form>
</main>

  <footer id="footer" class="border-t border-gray-200 bg-surface pt-20 pb-8 px-6">
    <div class="max-w-[1320px] mx-auto">
      <div class="grid sm:grid-cols-2 lg:grid-cols-5 gap-12 mb-16">

        <!-- Brand -->
        <div class="lg:col-span-2">
          <a href="index.php" class="flex items-center gap-2 group shrink-0 mb-5">
            <img src="assets/upload/logos/logo.png" alt="Logo" class="w-[30px] h-[30px] rounded-lg">
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
            <li><a href="#about" class="hover:text-charcoal transition-colors">About Us</a></li>
            <li><a href="careers.php" class="hover:text-charcoal transition-colors">Careers</a></li>
            <li><a href="blog.php" class="hover:text-charcoal transition-colors">Blog</a></li>
            <li><a href="press.php" class="hover:text-charcoal transition-colors">Press</a></li>
            <li><a href="#pricing" class="hover:text-charcoal transition-colors">Pricing</a></li>
          </ul>
        </div>

        <!-- Support -->
        <div>
          <h4 class="text-charcoal font-semibold text-[11px] uppercase tracking-widest mb-5">Support</h4>
          <ul class="space-y-3 text-gray-400 text-[12px]">
            <li><a href="faq.php" class="hover:text-charcoal transition-colors">FAQ</a></li>
            <li><a href="contact.php" class="hover:text-charcoal transition-colors">Contact Us</a></li>
            <li><a href="privacy.php" class="hover:text-charcoal transition-colors">Privacy Policy</a></li>
            <li><a href="terms.php" class="hover:text-charcoal transition-colors">Terms &amp; Conditions</a></li>
            <li><a href="dispute.php" class="hover:text-charcoal transition-colors">Dispute Resolution</a></li>
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
            <form action="../actions/newsletter_process.php" method="POST" class="flex gap-2">
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
        <p>&copy; 2026 JobHub. All rights reserved.</p>
        <div class="flex gap-5">
          <a href="privacy.php" class="hover:text-charcoal transition-colors">Privacy</a>
          <a href="terms.php" class="hover:text-charcoal transition-colors">Terms</a>
          <a href="faq.php" class="hover:text-charcoal transition-colors">FAQ</a>
        </div>
      </div>
    </div>
  </footer>

  <!-- Back to Top -->
  <button id="back-top" class="fixed bottom-6 right-6 w-10 h-10 bg-charcoal text-white flex items-center justify-center hidden hover:bg-charcoal-light transition-all z-50" onclick="window.scrollTo({top:0,behavior:'smooth'})" style="border-radius:4px">
    <i data-lucide="chevron-up" class="w-3 h-3"></i>
  </button>

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

    // Filter section toggle
    function toggleFilter(btn) {
      btn.classList.toggle('collapsed');
      const body = btn.nextElementSibling;
      body.classList.toggle('collapsed');
    }

    // JS fallback: toggle checked class for custom radio/checkbox (for browsers without :has())
    document.querySelectorAll('.custom-radio input, .custom-checkbox input').forEach(input => {
      input.addEventListener('change', function() {
        this.closest('.custom-radio, .custom-checkbox').classList.toggle('is-checked', this.checked);
      });
      // Initialize on load
      if (input.checked) {
        input.closest('.custom-radio, .custom-checkbox').classList.add('is-checked');
      }
    });

    // Skill search filter
    function filterSkills(query) {
      const q = query.toLowerCase().trim();
      const groups = document.querySelectorAll('.skill-group');
      groups.forEach(group => {
        const items = group.querySelectorAll('.skill-item');
        let visibleCount = 0;
        items.forEach(item => {
          const name = item.dataset.name || '';
          const match = !q || name.includes(q);
          item.style.display = match ? '' : 'none';
          if (match) visibleCount++;
        });
        group.style.display = visibleCount > 0 ? '' : 'none';
      });
    }

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
