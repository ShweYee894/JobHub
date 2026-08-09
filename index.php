<?php
require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/auth/auth.php';

// ── Fetch 6 featured open jobs with skills ───────────────────────────────
$_featuredJobs = [];
$_featStmt = $conn->prepare(
  "SELECT j.id, j.title, j.description, j.budget, j.job_type, j.category, j.status,
            j.proposal_count, j.created_at,
            c.company_name
     FROM jobs j
     JOIN clients c ON j.client_id = c.client_id
     WHERE j.status = 'open'
     ORDER BY j.created_at DESC
     LIMIT 6"
);
$_featStmt->execute();
$_featResult = $_featStmt->get_result();
while ($_fRow = $_featResult->fetch_assoc()) {
  $_fRow['skills'] = [];
  $_featuredJobs[$_fRow['id']] = $_fRow;
}
$_featStmt->close();

if (!empty($_featuredJobs)) {
  $_fids = array_keys($_featuredJobs);
  $_fph = implode(',', array_fill(0, count($_fids), '?'));
  $_ftypes = str_repeat('i', count($_fids));
  $_fSkillStmt = $conn->prepare(
    "SELECT js.job_id, s.skill_name, s.category
         FROM job_skills js
         JOIN skills s ON js.skill_id = s.id
         WHERE js.job_id IN ($_fph)
         ORDER BY s.skill_name"
  );
  $_fSkillStmt->bind_param($_ftypes, ...$_fids);
  $_fSkillStmt->execute();
  $_fSkillRes = $_fSkillStmt->get_result();
  while ($_fsRow = $_fSkillRes->fetch_assoc()) {
    if (isset($_featuredJobs[$_fsRow['job_id']])) {
      $_featuredJobs[$_fsRow['job_id']]['skills'][] = $_fsRow;
    }
  }
  $_fSkillStmt->close();
}

// ── Category icon map for job cards ───────────────────────────────────────
$_catIcons = [
  'web' => ['icon' => 'code', 'color' => 'blue'],
  'mobile' => ['icon' => 'smartphone', 'color' => 'cyan'],
  'design' => ['icon' => 'paintbrush', 'color' => 'pink'],
  'graphic' => ['icon' => 'palette', 'color' => 'pink'],
  'ai' => ['icon' => 'brain', 'color' => 'violet'],
  'ml' => ['icon' => 'brain', 'color' => 'violet'],
  'data' => ['icon' => 'pie-chart', 'color' => 'emerald'],
  'devops' => ['icon' => 'cloud', 'color' => 'orange'],
  'cloud' => ['icon' => 'cloud', 'color' => 'orange'],
  'security' => ['icon' => 'shield', 'color' => 'red'],
  'cyber' => ['icon' => 'shield', 'color' => 'red'],
  'database' => ['icon' => 'database', 'color' => 'teal'],
  'game' => ['icon' => 'gamepad-2', 'color' => 'yellow'],
  'ecommerce' => ['icon' => 'shopping-cart', 'color' => 'blue'],
  'saas' => ['icon' => 'cloud', 'color' => 'indigo'],
  'api' => ['icon' => 'plug', 'color' => 'green'],
  'automation' => ['icon' => 'bot', 'color' => 'sky'],
];
$_defaultCatIcon = ['icon' => 'briefcase', 'color' => 'blue'];

function _getCatIcon(?string $cat, array $map, array $def): array
{
  if (!$cat)
    return $def;
  $lower = strtolower($cat);
  foreach ($map as $key => $val) {
    if (strpos($lower, $key) !== false)
      return $val;
  }
  return $def;
}

// ── Fetch 4 top freelancers with skills and ratings ──────────────────────
$_topFreelancers = [];
$_tfStmt = $conn->prepare(
  "SELECT u.id, u.name, u.profile_image,
            f.title, f.hourly_rate, f.availability,
            COALESCE(r.avg_rating, 0) AS avg_rating,
            COALESCE(r.review_count, 0) AS review_count
     FROM users u
     JOIN freelancers f ON u.id = f.user_id
     LEFT JOIN (
         SELECT reviewee_id,
                ROUND(AVG(rating), 1) AS avg_rating,
                COUNT(*) AS review_count
         FROM reviews
         GROUP BY reviewee_id
     ) r ON u.id = r.reviewee_id
     WHERE u.role = 'freelancer' AND u.status = 'active'
     ORDER BY r.avg_rating DESC, r.review_count DESC, f.years_of_experience DESC
     LIMIT 4"
);
$_tfStmt->execute();
$_tfResult = $_tfStmt->get_result();
while ($_tfRow = $_tfResult->fetch_assoc()) {
  $_tfRow['skills'] = [];
  $_topFreelancers[$_tfRow['id']] = $_tfRow;
}
$_tfStmt->close();

if (!empty($_topFreelancers)) {
  $_tfids = array_keys($_topFreelancers);
  $_tfph = implode(',', array_fill(0, count($_tfids), '?'));
  $_tftypes = str_repeat('i', count($_tfids));
  $_tfSkillStmt = $conn->prepare(
    "SELECT fs.freelancer_id, s.skill_name
         FROM freelancer_skills fs
         JOIN skills s ON fs.skill_id = s.id
         WHERE fs.freelancer_id IN ($_tfph)
         ORDER BY s.skill_name"
  );
  $_tfSkillStmt->bind_param($_tftypes, ...$_tfids);
  $_tfSkillStmt->execute();
  $_tfSkillRes = $_tfSkillStmt->get_result();
  while ($_tfsRow = $_tfSkillRes->fetch_assoc()) {
    if (isset($_topFreelancers[$_tfsRow['freelancer_id']])) {
      $_topFreelancers[$_tfsRow['freelancer_id']]['skills'][] = $_tfsRow['skill_name'];
    }
  }
  $_tfSkillStmt->close();
}

$_ixLoggedIn = isset($_SESSION['user_id']);
$_ixAvatar = $_ixLoggedIn ? get_profile_image($_SESSION['profile_image'] ?? null) : '';
$_ixName = $_ixLoggedIn ? ($_SESSION['user_name'] ?? 'User') : '';
$_ixRole = $_ixLoggedIn ? ($_SESSION['user_role'] ?? '') : '';
$_ixDash = match ($_ixRole) {
  'admin' => 'admin/dashboard.php',
  'client' => 'client/dashboard.php',
  'freelancer' => 'freelancer/home.php',
  default => 'index.php'
};

// ── Fetch real reviews for testimonials ────────────────────────────────
$_reviewsStmt = $conn->prepare(
  "SELECT r.id, r.rating, r.comment, r.created_at,
            u.name AS reviewer_name, u.profile_image AS reviewer_avatar,
            u.role AS reviewer_role
     FROM reviews r
     JOIN users u ON r.reviewer_id = u.id
     WHERE r.comment IS NOT NULL AND r.comment != ''
     ORDER BY r.created_at DESC
     LIMIT 12"
);
$_reviewsStmt->execute();
$_reviewsResult = $_reviewsStmt->get_result();
$_testimonials = [];
while ($_revRow = $_reviewsResult->fetch_assoc()) {
  $_testimonials[] = $_revRow;
}
$_reviewsStmt->close();

// ── Average rating & total count ───────────────────────────────────────
$_ratingRow = $conn->query('SELECT ROUND(AVG(rating),1) AS avg_rating, COUNT(*) AS total_reviews FROM reviews')->fetch_assoc();
$_avgRating = $_ratingRow['avg_rating'] ?? 0;
$_totalReviews = $_ratingRow['total_reviews'] ?? 0;
?>
<!DOCTYPE html>
<html lang="en" class="scroll-smooth">
<head>
  <meta charset="UTF-8"/>
  <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
  <title>JobHub – Hire Top Freelancers & Find Great Work</title>
  <meta name="description" content="JobHub connects talented freelancers with clients worldwide. Post a job, receive proposals, and get your project done by the best professionals."/>
  <link rel="stylesheet" href="assets/css/theme.css">
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
            accent: { DEFAULT: '#6366F1', dark: '#4F46E5' },
            charcoal: { DEFAULT: '#1A1D23', light: '#2D3039', lighter: '#3F4350' },
            surface: { DEFAULT: '#FDFDFD', alt: '#F5F5F4', warm: '#FAF9F7' },
          },
          animation: {
            'fade-up': 'fadeUp .6s ease forwards',
            'float': 'float 3s ease-in-out infinite',
          },
          keyframes: {
            fadeUp: { '0%': { opacity: 0, transform: 'translateY(24px)' }, '100%': { opacity: 1, transform: 'translateY(0)' } },
            float: { '0%,100%': { transform: 'translateY(0)' }, '50%': { transform: 'translateY(-10px)' } },
          }
        }
      }
    }
  </script>
  <style>
    body{background:#FDFDFD;color:#374151}
    h1,h2,h3,.serif{font-family:'Plus Jakarta Sans','Inter',system-ui,sans-serif;color:#001e00;font-weight:700;letter-spacing:-0.025em}
    .heading-serif{font-family:'Playfair Display',Georgia,serif}
    .grad-text{background:linear-gradient(135deg,#4338CA 0%,#6366F1 60%,#818CF8 100%);-webkit-background-clip:text;-webkit-text-fill-color:transparent;background-clip:text}
    .nav-link{position:relative}
    .nav-link::after{content:'';position:absolute;bottom:-2px;left:0;width:0;height:2px;background:linear-gradient(90deg,#4338CA,#6366F1);transition:width .3s}
    .nav-link:hover::after{width:100%}
    .cat-card{transition:all .35s ease;border:1px solid #E8E8E8}
    .cat-card:hover{transform:translateY(-4px);border-color:#4338CA;box-shadow:0 16px 48px -12px rgba(67,56,202,.08)}
    .job-card{transition:all .35s ease;border:1px solid #E8E8E8}
    .job-card:hover{transform:translateY(-3px);border-color:#6366F1;box-shadow:0 16px 48px -12px rgba(99,102,241,.08)}
    /* ── Freelancer Card – Premium Upwork-style ─────────────────────── */
    .fl-card{
      position:relative;
      border:1px solid #E2E8F0;
      border-radius:12px;
      background:#fff;
      padding:28px 24px 22px;
      display:flex;
      flex-direction:column;
      transition:transform .32s ease, box-shadow .32s ease, border-color .32s ease;
      cursor:pointer;
    }
    .fl-card:hover{
      transform:translateY(-6px);
      border-color:#C7D2FE;
      box-shadow:0 20px 50px -12px rgba(99,102,241,.13), 0 8px 24px -8px rgba(99,102,241,.06);
    }
    .fl-card:hover .fl-card-cta svg{transform:translateX(3px)}
    .fl-card-cta{transition:color .2s ease}
    .fl-badge{font-size:10px;font-weight:700;letter-spacing:.08em;text-transform:uppercase;padding:3px 8px;border-radius:6px;line-height:1.4}
    .fl-skill-tag{
      display:inline-flex;align-items:center;
      font-size:11px;font-weight:500;
      color:#475569;background:#F1F5F9;
      border:1px solid #E2E8F0;
      padding:4px 10px;border-radius:20px;
      transition:all .2s ease;white-space:nowrap;
    }
    .fl-skill-tag:hover{background:#EEF2FF;border-color:#C7D2FE;color:#4338CA}
    .fl-progress-ring{position:relative;width:36px;height:36px;flex-shrink:0}
    .fl-progress-ring svg{transform:rotate(-90deg)}
    .fl-progress-ring .ring-bg{fill:none;stroke:#E2E8F0;stroke-width:3}
    .fl-progress-ring .ring-fg{fill:none;stroke:#10B981;stroke-width:3;stroke-linecap:round;transition:stroke-dashoffset .8s ease}
    .fl-progress-ring .ring-label{
      position:absolute;inset:0;display:flex;align-items:center;justify-content:center;
      font-size:9px;font-weight:700;color:#065F46;
    }
    .btn-primary{background:#4338CA;transition:all .25s;color:#fff;border-radius:4px}
    .btn-primary:hover{background:#3730A3;transform:translateY(-1px)}
    .btn-outline{border:1px solid #D0D0D0;background:#fff;color:#1A1D23;transition:all .25s;border-radius:4px}
    .btn-outline:hover{border-color:#4338CA;background:#F5F3FF}
    .testi-card{transition:all .35s ease;border:1px solid #E8E8E8}
    .testi-card:hover{border-color:#818CF8;transform:translateY(-3px)}
    .reveal{opacity:0;transform:translateY(30px);transition:opacity .7s ease,transform .7s ease}
    .reveal.visible{opacity:1;transform:translateY(0)}
    #navbar.scrolled{background:rgba(253,253,253,.96);backdrop-filter:blur(16px);border-bottom:1px solid #E8E8E8}
    #mobile-menu{transition:max-height .35s ease,opacity .3s ease;max-height:0;opacity:0;overflow:hidden}
    #mobile-menu.open{max-height:600px;opacity:1}
    .tab-btn.active{background:#4338CA;color:#fff;box-shadow:0 4px 14px rgba(67,56,202,0.35)}
    .profile-popup{display:none;position:absolute;top:calc(100% + 8px);right:0;background:#fff;border:1px solid #E8E8E8;border-radius:8px;box-shadow:0 20px 60px rgba(0,0,0,.08);min-width:220px;z-index:50;overflow:hidden}
    .profile-popup.show{display:block}
    .input-elegant{border:1px solid #E8E8E8;border-radius:4px;transition:border-color .25s}
    .input-elegant:focus{border-color:#4338CA;outline:none}
    ::selection{background:#4338CA;color:#fff}
    .section-divider{width:40px;height:1px;background:#4338CA;margin:0 auto}
    .label-tag{font-family:'Inter',sans-serif;font-size:10px;font-weight:600;letter-spacing:.12em;text-transform:uppercase;color:#9CA3AF}
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
          <a href="#hero" class="nav-link text-[13px] font-medium text-gray-500 hover:text-charcoal transition-colors tracking-wide">Home</a>
          <a href="#jobs" class="nav-link text-[13px] font-medium text-gray-500 hover:text-charcoal transition-colors tracking-wide">Find Work</a>
          <a href="#freelancers" class="nav-link text-[13px] font-medium text-gray-500 hover:text-charcoal transition-colors tracking-wide">Find Freelancers</a>
          <a href="#howitworks" class="nav-link text-[13px] font-medium text-gray-500 hover:text-charcoal transition-colors tracking-wide">How It Works</a>
          <a href="#about" class="nav-link text-[13px] font-medium text-gray-500 hover:text-charcoal transition-colors tracking-wide">About Us</a>
          <a href="#pricing" class="nav-link text-[13px] font-medium text-gray-500 hover:text-charcoal transition-colors tracking-wide">Pricing</a>
        </div>
      </div>

      <div class="flex gap-3 items-center">
        <!--Search with Dropdown-->
        <div class="relative flex items-center bg-surface border border-gray-200 overflow-visible" style="border-radius:4px">
          <div class="flex items-center gap-2 px-3 py-2">
            <i data-lucide="search" class="w-3.5 h-3.5 text-gray-400"></i>
            <input id="nav-search" type="text" placeholder="Search"
              class="bg-transparent w-32 sm:w-44 text-charcoal placeholder-gray-400 outline-none text-[13px]" />
          </div>
          <div class="w-px h-5 bg-gray-200"></div>
          <button id="navSearchDropdownBtn" type="button" class="flex items-center gap-1.5 px-3 py-2 cursor-pointer hover:bg-gray-50 transition-colors">
            <span id="navSearchType" class="text-[12px] font-medium text-gray-600">Talent</span>
            <i data-lucide="chevron-down" class="w-2.5 h-2.5 text-gray-400"></i>
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
                <a href="<?= $_ixDash ?>" class="flex items-center gap-3 px-4 py-2.5 text-[13px] text-gray-600 hover:bg-gray-50 transition-colors">                <i data-lucide="layout-grid" class="w-4 h-4"></i> Dashboard</a>
                <?php if ($_ixRole === 'freelancer'): ?>
                <a href="freelancer/profile.php" class="flex items-center gap-3 px-4 py-2.5 text-[13px] text-gray-600 hover:bg-gray-50 transition-colors">                <i data-lucide="user" class="w-4 h-4"></i> My Profile</a>
                <?php endif; ?>
              </div>
              <div class="border-t border-gray-100 py-1">
                <a href="auth/logout.php" class="flex items-center gap-3 px-4 py-2.5 text-[13px] text-red-500 hover:bg-red-50 transition-colors">                <i data-lucide="log-out" class="w-4 h-4"></i> Logout</a>
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
        <a href="#hero" class="hover:text-charcoal py-2 px-3 rounded hover:bg-gray-50 transition-colors">Home</a>
        <a href="#jobs" class="hover:text-charcoal py-2 px-3 rounded hover:bg-gray-50 transition-colors">Find Work</a>
        <a href="#freelancers" class="hover:text-charcoal py-2 px-3 rounded hover:bg-gray-50 transition-colors">Find Freelancers</a>
        <a href="#howitworks" class="hover:text-charcoal py-2 px-3 rounded hover:bg-gray-50 transition-colors">How It Works</a>
        <a href="#about" class="hover:text-charcoal py-2 px-3 rounded hover:bg-gray-50 transition-colors">About Us</a>
        <a href="#pricing" class="hover:text-charcoal py-2 px-3 rounded hover:bg-gray-50 transition-colors">Pricing</a>
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

  <!-- ═══════════════════════ 2. HERO ══════════════════════════════════ -->
  <section id="hero" class="relative min-h-[88vh] flex items-center overflow-hidden pt-32 bg-surface">
    <!-- Subtle grid pattern -->
    <div class="absolute inset-0 opacity-[0.015]" style="background-image:radial-gradient(circle,#1A1D23 1px,transparent 1px);background-size:32px 32px;"></div>

    <div class="relative z-10 max-w-[1320px] mx-auto px-6 w-full">
      <div class="grid lg:grid-cols-2 gap-16 lg:gap-20 items-center">

        <!-- Left Column - Content -->
        <div class="text-center lg:text-left">

          <!-- Eyebrow -->
          <div class="flex items-center gap-3 justify-center lg:justify-start mb-8">
            <div class="w-8 h-[1px] bg-[#4338CA]"></div>
            <span class="label-tag">The Future of Freelancing</span>
          </div>

          <!-- Headline -->
          <h1 class="text-4xl sm:text-5xl lg:text-[3.5rem] font-serif font-medium leading-[1.15] mb-8 text-charcoal tracking-tight">
            Find the Perfect
            <br/>
            <span class="text-[#4338CA]">Freelancer</span>
            <br/>for Your Project
          </h1>

          <!-- Subtitle -->
          <p class="text-[15px] text-gray-500 max-w-lg mb-12 leading-relaxed mx-auto lg:mx-0">
            Connect with top-tier professionals across 50+ skills. Post your project, receive proposals in hours, and hire with confidence.
          </p>

          <!-- Search Bar -->
          <div class="relative flex items-center max-w-xl mx-auto lg:mx-0 mb-12 bg-surface border border-gray-200 overflow-visible" style="border-radius:4px">
            <div class="flex items-center gap-2.5 px-4 py-3 flex-1">
            <i data-lucide="search" class="text-gray-400 w-3.5 h-3.5"></i>
              <input id="hero-search" type="text" placeholder="Search for talent..."
                class="bg-transparent flex-1 text-charcoal placeholder-gray-400 outline-none text-[13px]" />
            </div>
            <div class="w-px h-6 bg-gray-200"></div>
            <button id="heroSearchDropdownBtn" type="button" class="flex items-center gap-1.5 px-4 py-3 cursor-pointer hover:bg-gray-50 transition-colors">
              <span id="heroSearchType" class="text-[12px] font-medium text-gray-600">Talent</span>
            <i data-lucide="chevron-down" class="text-gray-400 w-2.5 h-2.5"></i>
            </button>
            <div id="heroSearchDropdown" class="hidden absolute top-full left-0 right-0 mt-1 bg-white border border-gray-200 rounded-md shadow-lg z-50 overflow-hidden">
              <button type="button" class="w-full text-left px-4 py-2.5 text-[13px] text-gray-600 hover:bg-gray-50 transition-colors font-medium" data-type="talent" data-placeholder="Search for talent...">
                Talent
              </button>
              <button type="button" class="w-full text-left px-4 py-2.5 text-[13px] text-gray-600 hover:bg-gray-50 transition-colors font-medium" data-type="jobs" data-placeholder="Search for jobs...">
                Jobs
              </button>
            </div>
          </div>

          <!-- CTA Buttons -->
          <div class="flex flex-col sm:flex-row items-center gap-4 justify-center lg:justify-start">
            <a href="auth/register.php?role=client" class="btn-primary font-semibold text-[13px] tracking-wide text-white px-8 py-3.5 inline-flex items-center justify-center gap-2.5">
              <span class="text-[11px]">+</span>
              Post a Project
            </a>
            <a href="freelancer/browse_jobs.php" class="btn-outline font-medium text-[13px] tracking-wide px-8 py-3.5 inline-flex items-center justify-center gap-2.5">
              Explore Talent
              <i data-lucide="arrow-right" class="w-3 h-3 group-hover:translate-x-1 transition-transform duration-200"></i>
            </a>
          </div>
        </div>

        <!-- Right Column - Visual -->
        <div class="hidden lg:block relative w-full max-w-xl mx-auto">
          <img src="assets/upload/logos/freelancer.png" alt="Freelancer" class="w-full h-auto object-contain block mx-auto" style="filter:grayscale(20%) contrast(1.05)">

          <!-- Left Side Badge ($4,500 Deposited) -->
          <div class="absolute top-[45%] left-0 bg-surface border border-gray-200 p-3.5 flex items-center gap-3 max-w-[210px] transform -translate-y-1/2" style="border-radius:6px;box-shadow:0 16px 48px -12px rgba(0,0,0,.06)">
            <div class="w-8 h-8 rounded flex items-center justify-center shrink-0 bg-gray-50">
              <i data-lucide="credit-card" class="w-3.5 h-3.5 text-[#4338CA]"></i>
            </div>
            <div>
              <p class="font-semibold text-charcoal text-[11px] tracking-wide leading-none mb-1">
                $4,500 Deposited
              </p>
              <p class="text-gray-400 text-[9px] leading-tight font-medium tracking-wide">
                Escrow secured for frontend build
              </p>
            </div>
          </div>

          <!-- Bottom Right Card (Find your next big contract) -->
          <div class="absolute bottom-8 -right-4 bg-surface border border-gray-200 p-4 max-w-[230px]" style="border-radius:6px;box-shadow:0 16px 48px -12px rgba(0,0,0,.06)">
            <div class="flex flex-col gap-2.5">
              <div class="flex items-center gap-2.5">
                <div class="w-6 h-6 rounded flex items-center justify-center shrink-0 bg-gray-50">
                  <i data-lucide="briefcase" class="w-2.5 h-2.5 text-[#4338CA]"></i>
                </div>
                <h4 class="font-semibold text-charcoal text-[11px] tracking-wide leading-tight">
                  Find your next big contract
                </h4>
              </div>
              <p class="text-gray-400 text-[10px] leading-relaxed pl-[34px]">
                Connect with top global companies hiring now.
              </p>
              <div class="pl-[34px] pt-2 flex flex-col gap-1.5 border-t border-gray-100 mt-0.5">
                <span class="inline-flex items-center gap-1.5 text-[9px] font-medium text-gray-500 tracking-wide">
                  <span class="w-1 h-1 rounded-full bg-[#4338CA]"></span>
                  Paid via milestones
                </span>
              </div>
            </div>
          </div>

        </div>

      </div>
    </div>
  </section>

  <!-- ═══════════════════════ 3. TRUST BAR ═════════════════════════════ -->
  <section class="py-16 px-6 bg-surface border-y border-gray-100">
    <div class="max-w-[1320px] mx-auto">
      <div class="flex flex-col md:flex-row items-center justify-center gap-10 md:gap-20">
        <div class="flex items-center gap-4">
          <div class="w-10 h-10 rounded flex items-center justify-center bg-gray-50 border border-gray-100"><i data-lucide="user-check" class="w-4 h-4 text-[#4338CA]"></i></div>
          <div><p class="text-xl font-serif font-medium text-charcoal">5,000+</p><p class="text-[10px] text-gray-400 font-medium tracking-wider uppercase">Verified Pros</p></div>
        </div>
        <div class="hidden md:block w-px h-8 bg-gray-200"></div>
        <div class="flex items-center gap-4">
          <div class="w-10 h-10 rounded flex items-center justify-center bg-gray-50 border border-gray-100"><i data-lucide="folder-check" class="w-4 h-4 text-[#4338CA]"></i></div>
          <div><p class="text-xl font-serif font-medium text-charcoal">12,000+</p><p class="text-[10px] text-gray-400 font-medium tracking-wider uppercase">Projects Completed</p></div>
        </div>
        <div class="hidden md:block w-px h-8 bg-gray-200"></div>
        <div class="flex items-center gap-4">
          <div class="w-10 h-10 rounded flex items-center justify-center bg-gray-50 border border-gray-100"><i data-lucide="smile" class="w-4 h-4 text-[#4338CA]"></i></div>
          <div><p class="text-xl font-serif font-medium text-charcoal">99%</p><p class="text-[10px] text-gray-400 font-medium tracking-wider uppercase">Client Satisfaction</p></div>
        </div>
      </div>
    </div>
  </section>

  <!-- ═══════════════════════ 4. CATEGORIES ════════════════════════════ -->
  <section id="categories" class="py-20 px-4 reveal">
    <div class="max-w-7xl mx-auto">
      <div class="text-center mb-12">
        <span class="text-[11px] font-bold uppercase tracking-widest text-blue-600/70 bg-blue-50 px-3 py-1 rounded-full">Browse Skills</span>
        <h2 class="text-3xl sm:text-4xl font-extrabold mt-4 mb-3 text-gray-900">Popular <span class="grad-text">Categories</span></h2>
        <p class="text-gray-500 max-w-lg mx-auto text-sm">From web development to cutting-edge AI — find the expertise you need.</p>
      </div>

      <div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-4 xl:grid-cols-5 gap-4">
        <div class="cat-card bg-white rounded-2xl p-5 cursor-pointer text-center group border border-gray-100 shadow-sm">
          <div class="w-10 h-10 rounded-xl flex items-center justify-center bg-slate-100/80 border border-slate-200/50 mx-auto mb-3 group-hover:bg-indigo-50 group-hover:border-indigo-200/60 transition-colors"><i data-lucide="code" class="w-4 h-4 text-slate-600 group-hover:text-indigo-600 transition-colors"></i></div>
          <h3 class="font-bold text-gray-900 text-[13px] mb-0.5">Web Development</h3>
          <p class="text-gray-400 text-[11px]">1,240 jobs</p>
        </div>
        <div class="cat-card bg-white rounded-2xl p-5 cursor-pointer text-center group border border-gray-100 shadow-sm">
          <div class="w-10 h-10 rounded-xl flex items-center justify-center bg-slate-100/80 border border-slate-200/50 mx-auto mb-3 group-hover:bg-indigo-50 group-hover:border-indigo-200/60 transition-colors"><i data-lucide="smartphone" class="w-4 h-4 text-slate-600 group-hover:text-indigo-600 transition-colors"></i></div>
          <h3 class="font-bold text-gray-900 text-[13px] mb-0.5">Mobile Dev</h3>
          <p class="text-gray-400 text-[11px]">820 jobs</p>
        </div>
        <div class="cat-card bg-white rounded-2xl p-5 cursor-pointer text-center group border border-gray-100 shadow-sm">
          <div class="w-10 h-10 rounded-xl flex items-center justify-center bg-slate-100/80 border border-slate-200/50 mx-auto mb-3 group-hover:bg-indigo-50 group-hover:border-indigo-200/60 transition-colors"><i data-lucide="palette" class="w-4 h-4 text-slate-600 group-hover:text-indigo-600 transition-colors"></i></div>
          <h3 class="font-bold text-gray-900 text-[13px] mb-0.5">Graphic Design</h3>
          <p class="text-gray-400 text-[11px]">940 jobs</p>
        </div>
        <div class="cat-card bg-white rounded-2xl p-5 cursor-pointer text-center group border border-gray-100 shadow-sm">
          <div class="w-10 h-10 rounded-xl flex items-center justify-center bg-slate-100/80 border border-slate-200/50 mx-auto mb-3 group-hover:bg-indigo-50 group-hover:border-indigo-200/60 transition-colors"><i data-lucide="brain" class="w-4 h-4 text-slate-600 group-hover:text-indigo-600 transition-colors"></i></div>
          <h3 class="font-bold text-gray-900 text-[13px] mb-0.5">AI / ML</h3>
          <p class="text-gray-400 text-[11px]">560 jobs</p>
        </div>
        <div class="cat-card bg-white rounded-2xl p-5 cursor-pointer text-center group border border-gray-100 shadow-sm">
          <div class="w-10 h-10 rounded-xl flex items-center justify-center bg-slate-100/80 border border-slate-200/50 mx-auto mb-3 group-hover:bg-indigo-50 group-hover:border-indigo-200/60 transition-colors"><i data-lucide="pie-chart" class="w-4 h-4 text-slate-600 group-hover:text-indigo-600 transition-colors"></i></div>
          <h3 class="font-bold text-gray-900 text-[13px] mb-0.5">Data Science</h3>
          <p class="text-gray-400 text-[11px]">430 jobs</p>
        </div>
        <div class="cat-card bg-white rounded-2xl p-5 cursor-pointer text-center group border border-gray-100 shadow-sm">
          <div class="w-10 h-10 rounded-xl flex items-center justify-center bg-slate-100/80 border border-slate-200/50 mx-auto mb-3 group-hover:bg-indigo-50 group-hover:border-indigo-200/60 transition-colors"><i data-lucide="shield" class="w-4 h-4 text-slate-600 group-hover:text-indigo-600 transition-colors"></i></div>
          <h3 class="font-bold text-gray-900 text-[13px] mb-0.5">Cyber Security</h3>
          <p class="text-gray-400 text-[11px]">310 jobs</p>
        </div>
        <div class="cat-card bg-white rounded-2xl p-5 cursor-pointer text-center group border border-gray-100 shadow-sm">
          <div class="w-10 h-10 rounded-xl flex items-center justify-center bg-slate-100/80 border border-slate-200/50 mx-auto mb-3 group-hover:bg-indigo-50 group-hover:border-indigo-200/60 transition-colors"><i data-lucide="cloud" class="w-4 h-4 text-slate-600 group-hover:text-indigo-600 transition-colors"></i></div>
          <h3 class="font-bold text-gray-900 text-[13px] mb-0.5">DevOps & Cloud</h3>
          <p class="text-gray-400 text-[11px]">275 jobs</p>
        </div>
        <div class="cat-card bg-white rounded-2xl p-5 cursor-pointer text-center group border border-gray-100 shadow-sm">
          <div class="w-10 h-10 rounded-xl flex items-center justify-center bg-slate-100/80 border border-slate-200/50 mx-auto mb-3 group-hover:bg-indigo-50 group-hover:border-indigo-200/60 transition-colors"><i data-lucide="database" class="w-4 h-4 text-slate-600 group-hover:text-indigo-600 transition-colors"></i></div>
          <h3 class="font-bold text-gray-900 text-[13px] mb-0.5">Database & SQL</h3>
          <p class="text-gray-400 text-[11px]">380 jobs</p>
        </div>
        <div class="cat-card bg-white rounded-2xl p-5 cursor-pointer text-center group border border-gray-100 shadow-sm">
          <div class="w-10 h-10 rounded-xl flex items-center justify-center bg-slate-100/80 border border-slate-200/50 mx-auto mb-3 group-hover:bg-indigo-50 group-hover:border-indigo-200/60 transition-colors"><i data-lucide="bot" class="w-4 h-4 text-slate-600 group-hover:text-indigo-600 transition-colors"></i></div>
          <h3 class="font-bold text-gray-900 text-[13px] mb-0.5">Automation / RPA</h3>
          <p class="text-gray-400 text-[11px]">198 jobs</p>
        </div>
        <div class="cat-card bg-white rounded-2xl p-5 cursor-pointer text-center group border border-gray-100 shadow-sm">
          <div class="w-10 h-10 rounded-xl flex items-center justify-center bg-slate-100/80 border border-slate-200/50 mx-auto mb-3 group-hover:bg-indigo-50 group-hover:border-indigo-200/60 transition-colors"><i data-lucide="gamepad-2" class="w-4 h-4 text-slate-600 group-hover:text-indigo-600 transition-colors"></i></div>
          <h3 class="font-bold text-gray-900 text-[13px] mb-0.5">Game Dev</h3>
          <p class="text-gray-400 text-[11px]">150 jobs</p>
        </div>
      </div>
    </div>
  </section>

  <!-- ═══════════════════════ 5. FEATURED JOBS ═════════════════════════ -->
  <section id="jobs" class="py-28 px-6 reveal" style="background:#FAFAFA">
    <div class="max-w-7xl mx-auto px-4">
      <div class="flex flex-col sm:flex-row sm:items-end justify-between mb-16 gap-6">
        <div>
          <span class="label-tag">Latest Listings</span>
          <h2 class="text-3xl sm:text-4xl font-serif font-medium mt-4 text-charcoal">Featured Jobs</h2>
        </div>
        <a href="freelancer/browse_jobs.php" class="bg-indigo-600 hover:bg-indigo-700 text-white font-medium text-sm px-5 py-2.5 rounded-xl shadow-sm transition-all duration-200 active:scale-95 inline-flex items-center gap-2">Browse All Jobs <i data-lucide="arrow-right" class="w-3 h-3"></i></a>
      </div>

      <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-8">
        <?php if (!empty($_featuredJobs)): ?>
          <?php
          foreach ($_featuredJobs as $_fj):
            $_ci = _getCatIcon($_fj['category'], $_catIcons, $_defaultCatIcon);
            $_budgetDisp = $_fj['job_type'] === 'hourly' ? '$' . number_format($_fj['budget'], 0) . '/hr' : '$' . number_format($_fj['budget'], 0);
            $_typeLabel = $_fj['job_type'] === 'hourly' ? 'Hourly' : 'Fixed Price';
            ?>
          <div class="bg-white rounded-2xl border border-slate-200/80 shadow-[0_4px_20px_rgba(0,0,0,0.03)] hover:shadow-[0_12px_30px_rgba(0,0,0,0.08)] hover:-translate-y-1.5 transition-all duration-300 p-7 flex flex-col justify-between group">
            <!-- Header: Icon + Status -->
            <div>
              <div class="flex items-start justify-between mb-5">
                <div class="w-10 h-10 rounded-xl flex items-center justify-center bg-slate-100/80 border border-slate-200/50">
                  <i data-lucide="<?php echo $_ci['icon']; ?>" class="text-slate-600 w-4 h-4"></i>
                </div>
                <span class="inline-flex items-center gap-1.5 px-3 py-1 rounded-full text-xs font-semibold bg-emerald-50 text-emerald-700 border border-emerald-200/60"><span class="w-1.5 h-1.5 rounded-full bg-emerald-500 inline-block"></span>Open</span>
              </div>

              <!-- Title -->
              <h3 class="text-lg font-bold text-slate-900 line-clamp-1 mb-2"><?php echo sanitize_string($_fj['title']); ?></h3>

              <!-- Description -->
              <p class="text-sm text-slate-500 line-clamp-2 mt-2 leading-relaxed font-normal"><?php echo sanitize_string(mb_strimwidth($_fj['description'], 0, 120, '...')); ?></p>

              <!-- Skill Pills -->
              <?php if (!empty($_fj['skills'])): ?>
                <div class="flex flex-wrap gap-2 my-5">
                  <?php foreach (array_slice($_fj['skills'], 0, 3) as $_sk): ?>
                    <span class="text-xs font-medium text-slate-600 bg-slate-100/80 hover:bg-slate-200/80 px-3 py-1.5 rounded-lg border border-slate-200/50 transition-colors"><?php echo sanitize_string($_sk['skill_name']); ?></span>
                  <?php endforeach; ?>
                </div>
              <?php endif; ?>
            </div>

            <!-- Footer -->
            <div class="mt-auto">
              <!-- Main Row: Budget + Apply Button -->
              <div class="flex items-end justify-between">
                <div class="flex flex-col">
                  <span class="text-[10px] text-slate-400 font-bold tracking-widest uppercase mb-1">Budget</span>
                  <div class="flex items-center gap-2">
                    <span class="text-2xl font-black text-slate-900"><?php echo $_budgetDisp; ?></span>
                    <span class="text-xs bg-slate-100 text-slate-600 px-2 py-0.5 rounded-md"><?php echo $_typeLabel; ?></span>
                  </div>
                </div>
                <a href="freelancer/job_detail.php?id=<?php echo $_fj['id']; ?>" class="btn-primary font-semibold text-[13px] tracking-wide text-white px-6 py-2.5 inline-flex items-center justify-center">Apply Now</a>
              </div>
              <!-- Divider -->
              <div class="border-t border-slate-100 mt-5 pt-4">
                <!-- Metadata Row -->
                <p class="text-xs text-slate-400 flex items-center gap-1.5">
                  <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                  <?php echo time_ago($_fj['created_at']); ?> &middot; <?php echo $_fj['proposal_count']; ?> proposals
                </p>
              </div>
            </div>
          </div>
          <?php endforeach; ?>
        <?php else: ?>
          <div class="col-span-full text-center py-24">
            <div class="w-16 h-16 rounded-2xl flex items-center justify-center bg-slate-100 border border-slate-200/80 mx-auto mb-5"><i data-lucide="briefcase" class="w-5 h-5 text-slate-300"></i></div>
            <h3 class="text-lg font-bold text-slate-900 mb-2">No jobs posted yet</h3>
            <p class="text-sm text-slate-500 mb-6">Be the first to post a job and find great talent.</p>
            <a href="auth/register.php?role=client" class="bg-slate-900 hover:bg-indigo-600 text-white font-medium text-sm px-6 py-3 rounded-xl shadow-sm transition-all duration-200 active:scale-95 inline-block">Post a Job</a>
          </div>
        <?php endif; ?>
      </div>
    </div>
  </section>

  <!-- ═══════════════════════ 6. TOP FREELANCERS ═══════════════════════ -->
  <section id="freelancers" class="py-24 px-6 reveal bg-surface">
    <div class="max-w-[1320px] mx-auto">
      <div class="text-center mb-16">
        <span class="label-tag">Expert Talent</span>
        <h2 class="text-3xl sm:text-4xl font-serif font-medium mt-4 mb-4 text-charcoal">Top Freelancers</h2>
        <div class="section-divider mb-4"></div>
        <p class="text-gray-500 max-w-md mx-auto text-[13px] leading-relaxed">Handpicked professionals with proven track records and top client satisfaction.</p>
      </div>

      <div class="grid sm:grid-cols-2 lg:grid-cols-4 gap-6">
        <?php if (!empty($_topFreelancers)): ?>
          <?php
          foreach ($_topFreelancers as $_tf):
            $_isTopRated = $_tf['avg_rating'] >= 4.8 && $_tf['review_count'] >= 50;
            $_profileImg = get_profile_image($_tf['profile_image']);
            $_jobSuccess = min(100, (int) round($_tf['avg_rating'] * 20));
            $_circumference = 2 * M_PI * 14;
            $_offset = $_circumference - ($_jobSuccess / 100) * $_circumference;
            ?>
          <a href="freelancer/profile.php?id=<?php echo $_tf['id']; ?>" class="fl-card group no-underline">

            <!-- ── Top Row: Avatar · Name/Title · Hourly Rate ────────── -->
            <div class="flex items-start gap-4 mb-4">

              <!-- Avatar + availability dot -->
              <div class="relative flex-shrink-0">
                <img
                  src="<?php echo $_profileImg; ?>"
                  alt="<?php echo sanitize_string($_tf['name']); ?>"
                  class="w-14 h-14 rounded-full object-cover ring-2 ring-white shadow-sm"
                />
                <?php if ($_tf['availability'] === 'Available'): ?>
                  <span class="absolute -bottom-0.5 -right-0.5 w-3.5 h-3.5 bg-emerald-500 rounded-full border-2 border-white" title="Available"></span>
                <?php elseif ($_tf['availability'] === 'Busy'): ?>
                  <span class="absolute -bottom-0.5 -right-0.5 w-3.5 h-3.5 bg-yellow-400 rounded-full border-2 border-white" title="Busy"></span>
                <?php else: ?>
                  <span class="absolute -bottom-0.5 -right-0.5 w-3.5 h-3.5 bg-gray-400 rounded-full border-2 border-white" title="Offline"></span>
                <?php endif; ?>
              </div>

              <!-- Name & Title -->
              <div class="flex-1 min-w-0">
                <div class="flex items-center gap-2">
                  <h3 class="text-[15px] font-bold text-gray-900 truncate leading-tight">
                    <?php echo sanitize_string($_tf['name']); ?>
                  </h3>
                  <?php if ($_isTopRated): ?>
                    <span class="fl-badge bg-amber-50 text-amber-700 border border-amber-200/60 shrink-0">
                      <i data-lucide="crown" class="w-2.5 h-2.5 mr-0.5 text-amber-500 inline-block align-[-2px]"></i>Top
                    </span>
                  <?php endif; ?>
                </div>
                <p class="text-[12.5px] text-gray-500 mt-0.5 truncate leading-snug">
                  <?php echo sanitize_string($_tf['title'] ?? 'Freelancer'); ?>
                </p>
              </div>

              <!-- Hourly Rate -->
              <div class="text-right flex-shrink-0">
                <span class="text-lg font-extrabold text-gray-900 leading-none">
                  $<?php echo number_format($_tf['hourly_rate'], 0); ?>
                </span>
                <span class="block text-[10px] font-medium text-gray-400 tracking-wide">/hour</span>
              </div>
            </div>

            <!-- ── Trust Signals ─────────────────────────────────────── -->
            <div class="flex items-center gap-4 py-3.5 border-t border-b border-gray-100 mb-4">

              <!-- Job Success Score (circular ring) — derived from avg_rating -->
              <div class="flex items-center gap-2.5">
                <div class="fl-progress-ring">
                  <svg width="36" height="36" viewBox="0 0 36 36">
                    <circle class="ring-bg" cx="18" cy="18" r="14"/>
                    <circle class="ring-fg" cx="18" cy="18" r="14"
                      stroke-dasharray="<?php echo number_format($_circumference, 2); ?>"
                      stroke-dashoffset="<?php echo number_format($_offset, 2); ?>"/>
                  </svg>
                  <span class="ring-label"><?php echo $_jobSuccess; ?>%</span>
                </div>
                <div>
                  <p class="text-[11px] font-semibold text-gray-700 leading-tight">Job Success</p>
                  <p class="text-[10px] text-gray-400 leading-tight mt-0.5">
                    ★ <?php echo number_format($_tf['avg_rating'], 1); ?> avg rating
                  </p>
                </div>
              </div>

              <!-- Separator -->
              <div class="w-px h-8 bg-gray-100"></div>

              <!-- Reviews — from review_count -->
              <div class="flex items-center gap-2.5">
                <div class="w-8 h-8 rounded-lg bg-indigo-50 flex items-center justify-center flex-shrink-0">
                  <i data-lucide="message-square" class="w-3.5 h-3.5 text-indigo-600"></i>
                </div>
                <div>
                  <p class="text-[11px] font-semibold text-gray-700 leading-tight"><?php echo number_format($_tf['review_count']); ?> review<?php echo $_tf['review_count'] !== 1 ? 's' : ''; ?></p>
                  <p class="text-[10px] text-gray-400 leading-tight mt-0.5">
                    $<?php echo number_format($_tf['hourly_rate'], 0); ?>/hr
                  </p>
                </div>
              </div>
            </div>

            <!-- ── Skill Tags ───────────────────────────────────────── -->
            <?php if (!empty($_tf['skills'])): ?>
              <div class="flex flex-wrap gap-1.5 mb-4">
                <?php foreach (array_slice($_tf['skills'], 0, 4) as $_sk): ?>
                  <span class="fl-skill-tag"><?php echo sanitize_string($_sk); ?></span>
                <?php endforeach; ?>
              </div>
            <?php endif; ?>

            <!-- ── Footer: View Profile CTA ─────────────────────────── -->
            <div class="mt-auto pt-1 flex items-center justify-between">
              <span class="text-[12px] font-semibold text-indigo-600 group-hover:text-indigo-700 transition-colors inline-flex items-center gap-1.5 fl-card-cta">
                View Profile
                <svg class="w-3 h-3 transition-transform duration-250 group-hover:translate-x-0.5" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M9 5l7 7-7 7"/></svg>
              </span>
            </div>

          </a>
          <?php endforeach; ?>
        <?php else: ?>
          <div class="col-span-full text-center py-24">
            <div class="w-16 h-16 rounded-2xl flex items-center justify-center bg-slate-100 border border-slate-200/80 mx-auto mb-5"><i data-lucide="users" class="w-5 h-5 text-slate-300"></i></div>
            <h3 class="text-lg font-bold text-slate-900 mb-2">No freelancers yet</h3>
            <p class="text-sm text-slate-500 mb-6">Be the first to join as a freelancer.</p>
            <a href="auth/register.php?role=freelancer" class="btn-primary inline-block text-white font-semibold px-6 py-3 text-[12px]">Join as Freelancer</a>
          </div>
        <?php endif; ?>
      </div>

      <div class="text-center mt-12">
        <a href="browse_freelancers.php" class="btn-outline inline-flex items-center gap-2 text-gray-500 hover:text-charcoal font-medium px-6 py-3 text-[12px]">
          Browse All Freelancers <i data-lucide="arrow-right" class="w-3 h-3"></i>
        </a>
      </div>
    </div>
  </section>

  <!-- ═══════════════════════ 7. HOW IT WORKS ══════════════════════════ -->
  <section id="howitworks" class="py-20 px-4 reveal bg-gray-50">
    <div class="max-w-7xl mx-auto">
      <div class="text-center mb-12">
        <span class="text-[11px] font-bold uppercase tracking-widest text-green-600/70 bg-green-50 px-3 py-1 rounded-full">Process</span>
        <h2 class="text-3xl sm:text-4xl font-extrabold mt-4 mb-3 text-gray-900">How It <span class="grad-text">Works</span></h2>
        <p class="text-gray-500 max-w-lg mx-auto text-sm">A streamlined process for both clients and freelancers.</p>
      </div>

      <!-- Tabs -->
      <div class="flex justify-center mb-10">
        <div class="bg-white rounded-2xl p-1 flex gap-2 shadow-sm border border-gray-100">
          <button id="tab-client" onclick="switchTab('client')" class="tab-btn active font-medium text-sm px-8 py-3 rounded-xl transition-all flex items-center justify-center gap-2"><i data-lucide="user" class="w-4 h-4"></i>For Clients</button>
          <button id="tab-freelancer" onclick="switchTab('freelancer')" class="tab-btn font-medium text-sm px-8 py-3 rounded-xl text-gray-400 hover:text-gray-900 transition-all flex items-center justify-center gap-2"><i data-lucide="monitor" class="w-4 h-4"></i>For Freelancers</button>
        </div>
      </div>

      <!-- Client Steps -->
      <div id="panel-client" class="grid sm:grid-cols-2 lg:grid-cols-4 gap-5">
        <div class="bg-white rounded-2xl p-6 text-center relative border border-gray-100 shadow-sm">
          <div class="absolute -top-3 left-1/2 -translate-x-1/2 w-7 h-7 rounded-full bg-blue-600 flex items-center justify-center text-[11px] font-black text-white">1</div>
          <div class="w-10 h-10 rounded-xl flex items-center justify-center bg-slate-100/80 border border-slate-200/50 mx-auto mb-4 mt-2"><i data-lucide="circle-plus" class="w-4 h-4 text-slate-600"></i></div>
          <h3 class="font-bold text-gray-900 text-[14px] mb-1.5">Post a Job</h3>
          <p class="text-gray-500 text-[12px] leading-relaxed">Describe your project, set your budget, and specify the skills you need.</p>
        </div>
        <div class="bg-white rounded-2xl p-6 text-center relative border border-gray-100 shadow-sm">
          <div class="absolute -top-3 left-1/2 -translate-x-1/2 w-7 h-7 rounded-full bg-cyan-500 flex items-center justify-center text-[11px] font-black text-white">2</div>
          <div class="w-10 h-10 rounded-xl flex items-center justify-center bg-slate-100/80 border border-slate-200/50 mx-auto mb-4 mt-2"><i data-lucide="inbox" class="w-4 h-4 text-slate-600"></i></div>
          <h3 class="font-bold text-gray-900 text-[14px] mb-1.5">Receive Proposals</h3>
          <p class="text-gray-500 text-[12px] leading-relaxed">Freelancers submit proposals with their approach and bid.</p>
        </div>
        <div class="bg-white rounded-2xl p-6 text-center relative border border-gray-100 shadow-sm">
          <div class="absolute -top-3 left-1/2 -translate-x-1/2 w-7 h-7 rounded-full bg-purple-500 flex items-center justify-center text-[11px] font-black text-white">3</div>
          <div class="w-10 h-10 rounded-xl flex items-center justify-center bg-slate-100/80 border border-slate-200/50 mx-auto mb-4 mt-2"><i data-lucide="handshake" class="w-4 h-4 text-slate-600"></i></div>
          <h3 class="font-bold text-gray-900 text-[14px] mb-1.5">Hire Freelancer</h3>
          <p class="text-gray-500 text-[12px] leading-relaxed">Chat with candidates, compare bids, and hire your ideal freelancer.</p>
        </div>
        <div class="bg-white rounded-2xl p-6 text-center relative border border-gray-100 shadow-sm">
          <div class="absolute -top-3 left-1/2 -translate-x-1/2 w-7 h-7 rounded-full bg-green-500 flex items-center justify-center text-[11px] font-black text-white">4</div>
          <div class="w-10 h-10 rounded-xl flex items-center justify-center bg-slate-100/80 border border-slate-200/50 mx-auto mb-4 mt-2"><i data-lucide="send" class="w-4 h-4 text-slate-600"></i></div>
          <h3 class="font-bold text-gray-900 text-[14px] mb-1.5">Release Payment</h3>
          <p class="text-gray-500 text-[12px] leading-relaxed">Funds sit in secure escrow. Release payment after you approve deliverables.</p>
        </div>
      </div>

      <!-- Freelancer Steps -->
      <div id="panel-freelancer" class="grid sm:grid-cols-2 lg:grid-cols-4 gap-5 hidden">
        <div class="bg-white rounded-2xl p-6 text-center relative border border-gray-100 shadow-sm">
          <div class="absolute -top-3 left-1/2 -translate-x-1/2 w-7 h-7 rounded-full bg-blue-600 flex items-center justify-center text-[11px] font-black text-white">1</div>
          <div class="w-10 h-10 rounded-xl flex items-center justify-center bg-slate-100/80 border border-slate-200/50 mx-auto mb-4 mt-2"><i data-lucide="id-card" class="w-4 h-4 text-slate-600"></i></div>
          <h3 class="font-bold text-gray-900 text-[14px] mb-1.5">Create Profile</h3>
          <p class="text-gray-500 text-[12px] leading-relaxed">Showcase your skills, portfolio, and hourly rate.</p>
        </div>
        <div class="bg-white rounded-2xl p-6 text-center relative border border-gray-100 shadow-sm">
          <div class="absolute -top-3 left-1/2 -translate-x-1/2 w-7 h-7 rounded-full bg-cyan-500 flex items-center justify-center text-[11px] font-black text-white">2</div>
          <div class="w-10 h-10 rounded-xl flex items-center justify-center bg-slate-100/80 border border-slate-200/50 mx-auto mb-4 mt-2"><i data-lucide="file-text" class="w-4 h-4 text-slate-600"></i></div>
          <h3 class="font-bold text-gray-900 text-[14px] mb-1.5">Submit Proposal</h3>
          <p class="text-gray-500 text-[12px] leading-relaxed">Browse jobs and submit a personalised proposal with your bid.</p>
        </div>
        <div class="bg-white rounded-2xl p-6 text-center relative border border-gray-100 shadow-sm">
          <div class="absolute -top-3 left-1/2 -translate-x-1/2 w-7 h-7 rounded-full bg-purple-500 flex items-center justify-center text-[11px] font-black text-white">3</div>
          <div class="w-10 h-10 rounded-xl flex items-center justify-center bg-slate-100/80 border border-slate-200/50 mx-auto mb-4 mt-2"><i data-lucide="monitor" class="w-4 h-4 text-slate-600"></i></div>
          <h3 class="font-bold text-gray-900 text-[14px] mb-1.5">Complete Project</h3>
          <p class="text-gray-500 text-[12px] leading-relaxed">Work with the client via chat and milestones to deliver results.</p>
        </div>
        <div class="bg-white rounded-2xl p-6 text-center relative border border-gray-100 shadow-sm">
          <div class="absolute -top-3 left-1/2 -translate-x-1/2 w-7 h-7 rounded-full bg-green-500 flex items-center justify-center text-[11px] font-black text-white">4</div>
          <div class="w-10 h-10 rounded-xl flex items-center justify-center bg-slate-100/80 border border-slate-200/50 mx-auto mb-4 mt-2"><i data-lucide="wallet" class="w-4 h-4 text-slate-600"></i></div>
          <h3 class="font-bold text-gray-900 text-[14px] mb-1.5">Get Paid</h3>
          <p class="text-gray-500 text-[12px] leading-relaxed">Funds released to your wallet instantly upon client approval.</p>
        </div>
      </div>
    </div>
  </section>

  <!-- ═══════════════════════ 8. TESTIMONIALS ══════════════════════════ -->
  <section id="testimonials" class="py-24 px-6 reveal bg-surface overflow-hidden">
    <div class="max-w-[1320px] mx-auto">
      <div class="text-center mb-8">
        <span class="label-tag">Reviews</span>
        <h2 class="text-3xl sm:text-4xl font-serif font-medium mt-4 mb-4 text-charcoal">What People Say</h2>
        <div class="section-divider mb-4"></div>
        <p class="text-gray-500 max-w-md mx-auto text-[13px] leading-relaxed">Real reviews from verified clients and freelancers.</p>
      </div>
      <div class="flex items-center justify-center gap-3 mb-12">
        <span class="text-xl font-serif font-medium text-charcoal"><?= $_avgRating ?>/5</span>
        <div class="flex items-center gap-0.5">
          <?php for ($sv = 1; $sv <= 5; $sv++): ?>
            <svg viewBox="0 0 24 24" class="w-3.5 h-3.5 <?= $sv <= round($_avgRating) ? 'text-[#4338CA]' : 'text-gray-200' ?> fill-current"><path d="M12 2l3.09 6.26L22 9.27l-5 4.87 1.18 6.88L12 17.77l-6.18 3.25L7 14.14 2 9.27l6.91-1.01L12 2z"/></svg>
          <?php endfor; ?>
        </div>
        <span class="text-[11px] font-medium text-gray-400 tracking-wide">Based on <?= number_format($_totalReviews) ?> reviews</span>
      </div>

      <div class="flex gap-6 items-start">
        <div class="hidden lg:flex flex-col flex-shrink-0 w-56 pt-4">
          <svg class="w-8 h-8 text-gray-200 mb-3" viewBox="0 0 24 24" fill="currentColor"><path d="M4.583 17.321C3.553 16.227 3 15 3 13.011c0-3.5 2.457-6.637 6.03-8.188l.893 1.378c-3.335 1.804-3.987 4.145-4.247 5.621.537-.278 1.24-.375 1.929-.311C9.591 11.69 11 13.166 11 15c0 1.933-1.567 3.5-3.5 3.5-1.193 0-2.31-.567-2.917-1.179zM16.583 17.321C15.553 16.227 15 15 15 13.011c0-3.5 2.457-6.637 6.03-8.188l.893 1.378c-3.335 1.804-3.987 4.145-4.247 5.621.537-.278 1.24-.375 1.929-.311C21.591 11.69 23 13.166 23 15c0 1.933-1.567 3.5-3.5 3.5-1.193 0-2.31-.567-2.917-1.179z"/></svg>
          <h2 class="text-xl font-serif font-medium text-charcoal leading-tight mb-3">What our<br>customers<br>are saying</h2>
          <div class="flex items-center gap-2 mt-2">
            <button onclick="scrollTestimonials(-1)" class="w-8 h-8 rounded border border-gray-200 flex items-center justify-center text-gray-400 hover:text-charcoal hover:border-gray-300 transition-colors"><i data-lucide="arrow-left" class="w-3 h-3"></i></button>
            <button onclick="scrollTestimonials(1)" class="w-8 h-8 rounded bg-charcoal flex items-center justify-center text-white hover:bg-charcoal-light transition-colors"><i data-lucide="arrow-right" class="w-3 h-3"></i></button>
          </div>
        </div>

        <div class="flex-1 overflow-hidden">
          <div id="testimonialTrack" class="flex gap-4 overflow-x-auto scroll-smooth pb-4 snap-x snap-mandatory" style="scrollbar-width:none;-ms-overflow-style:none">
            <style>#testimonialTrack::-webkit-scrollbar{display:none}</style>
            <?php if (empty($_testimonials)): ?>
              <p class="text-gray-400 text-[13px] py-10 text-center w-full">No reviews yet. Be the first to leave one!</p>
            <?php else: ?>
              <?php
              foreach ($_testimonials as $_ti => $_t):
                $_stars = (int) $_t['rating'];
                $_initials = strtoupper(mb_substr($_t['reviewer_name'], 0, 1));
                $_ago = time_ago($_t['created_at']);
                $_roleLabel = $_t['reviewer_role'] === 'client' ? 'Client' : 'Freelancer';
                $_colors = ['from-blue-500 to-cyan-500', 'from-pink-500 to-purple-500', 'from-green-500 to-teal-500', 'from-orange-500 to-red-500', 'from-cyan-500 to-blue-500', 'from-violet-500 to-purple-700'];
                $_clr = $_colors[$_ti % count($_colors)];
                ?>
              <div class="testi-card flex-shrink-0 w-72 bg-surface p-5 snap-start flex flex-col justify-between">
                <p class="text-gray-500 text-[12px] leading-relaxed mb-5">"<?= htmlspecialchars($_t['comment']) ?>"</p>
                <div class="flex items-center gap-0.5 mb-4">
                  <?php for ($_s = 1; $_s <= 5; $_s++): ?>
                    <svg class="w-3 h-3 <?= $_s <= $_stars ? 'text-[#4338CA]' : 'text-gray-200' ?> fill-current" viewBox="0 0 24 24"><path d="M12 2l3.09 6.26L22 9.27l-5 4.87 1.18 6.88L12 17.77l-6.18 3.25L7 14.14 2 9.27l6.91-1.01L12 2z"/></svg>
                  <?php endfor; ?>
                </div>
                <div class="flex items-center gap-3 border-t border-gray-100 pt-3">
                  <?php if ($_t['reviewer_avatar']): ?>
                    <img src="<?= htmlspecialchars(get_profile_image($_t['reviewer_avatar'])) ?>" class="w-8 h-8 rounded-full object-cover" alt="">
                  <?php else: ?>
                    <div class="w-8 h-8 rounded-full bg-gradient-to-br <?= $_clr ?> flex items-center justify-center text-white font-semibold text-[9px]"><?= $_initials ?></div>
                  <?php endif; ?>
                  <div>
                    <p class="font-medium text-charcoal text-[12px]"><?= htmlspecialchars($_t['reviewer_name']) ?></p>
                    <p class="text-gray-400 text-[10px] tracking-wide"><?= $_roleLabel ?> &middot; <?= $_ago ?></p>
                  </div>
                </div>
              </div>
              <?php endforeach; ?>
            <?php endif; ?>
          </div>
        </div>
      </div>

      <div class="flex lg:hidden items-center justify-center gap-3 mt-6">
        <button onclick="scrollTestimonials(-1)" class="w-8 h-8 rounded border border-gray-200 flex items-center justify-center text-gray-400 hover:text-charcoal transition-colors"><i data-lucide="arrow-left" class="w-3 h-3"></i></button>
        <button onclick="scrollTestimonials(1)" class="w-8 h-8 rounded bg-charcoal flex items-center justify-center text-white hover:bg-charcoal-light transition-colors"><i data-lucide="arrow-right" class="w-3 h-3"></i></button>
      </div>
    </div>
  </section>

  <!-- ═══════════════════════ 9. ABOUT ═════════════════════════════════ -->
  <section id="about" class="py-20 px-4 reveal" style="background:#FAFAFA">
    <div class="max-w-[1320px] mx-auto">
      <div class="text-center mb-16">
        <span class="label-tag">Our Story</span>
        <h2 class="text-3xl sm:text-4xl font-serif font-medium mt-4 mb-4 text-charcoal">About JobHub</h2>
        <div class="section-divider"></div>
      </div>
      <div class="grid grid-cols-1 md:grid-cols-3 gap-8">

        <!-- Card 1: Who We Are -->
        <div class="bg-white rounded-2xl border border-slate-200/80 p-8 shadow-[0_4px_20px_rgba(0,0,0,0.03)] hover:shadow-xl hover:-translate-y-1 transition-all duration-300 flex flex-col justify-between">
          <div>
            <div class="w-12 h-12 bg-indigo-50 text-indigo-600 rounded-xl flex items-center justify-center mb-6">
              <i data-lucide="users" class="w-4 h-4"></i>
            </div>
            <h3 class="text-lg font-bold text-slate-900 mb-3">Who We Are</h3>
            <p class="text-slate-500 leading-relaxed text-sm">JobHub was founded in 2023 by a team of engineers who experienced the friction of freelancing first-hand. We built the most transparent, secure, and talent-rich marketplace in the world.</p>
          </div>
          <div class="border-t border-slate-100 pt-6 mt-6">
            <div class="flex justify-between">
              <div class="text-center">
                <p class="text-2xl font-black text-slate-900">50+</p>
                <p class="text-[10px] font-bold text-slate-400 tracking-widest uppercase mt-1">Team Members</p>
              </div>
              <div class="text-center">
                <p class="text-2xl font-black text-slate-900">40+</p>
                <p class="text-[10px] font-bold text-slate-400 tracking-widest uppercase mt-1">Countries</p>
              </div>
              <div class="text-center">
                <p class="text-2xl font-black text-slate-900">2023</p>
                <p class="text-[10px] font-bold text-slate-400 tracking-widest uppercase mt-1">Founded</p>
              </div>
            </div>
          </div>
        </div>

        <!-- Card 2: Our Mission -->
        <div class="bg-white rounded-2xl border border-slate-200/80 p-8 shadow-[0_4px_20px_rgba(0,0,0,0.03)] hover:shadow-xl hover:-translate-y-1 transition-all duration-300 flex flex-col justify-between">
          <div>
            <div class="w-12 h-12 bg-indigo-50 text-indigo-600 rounded-xl flex items-center justify-center mb-6">
              <i data-lucide="rocket" class="w-4 h-4"></i>
            </div>
            <h3 class="text-lg font-bold text-slate-900 mb-3">Our Mission</h3>
            <p class="text-slate-500 leading-relaxed text-sm">Democratise work — giving every skilled individual access to great projects and fair pay, regardless of geography. Talent is equally distributed; opportunity is not.</p>
          </div>
          <ul class="space-y-3.5 mt-6">
            <li class="flex items-center gap-2.5">
              <div class="w-5 h-5 rounded-full bg-emerald-50 text-emerald-600 border border-emerald-200/60 flex items-center justify-center flex-shrink-0">
                <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 13l4 4L19 7"/></svg>
              </div>
              <span class="text-sm font-medium text-slate-700">Zero barriers to entry</span>
            </li>
            <li class="flex items-center gap-2.5">
              <div class="w-5 h-5 rounded-full bg-emerald-50 text-emerald-600 border border-emerald-200/60 flex items-center justify-center flex-shrink-0">
                <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 13l4 4L19 7"/></svg>
              </div>
              <span class="text-sm font-medium text-slate-700">Fair & transparent fees</span>
            </li>
            <li class="flex items-center gap-2.5">
              <div class="w-5 h-5 rounded-full bg-emerald-50 text-emerald-600 border border-emerald-200/60 flex items-center justify-center flex-shrink-0">
                <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 13l4 4L19 7"/></svg>
              </div>
              <span class="text-sm font-medium text-slate-700">Global opportunity for all</span>
            </li>
          </ul>
        </div>

        <!-- Card 3: Why Choose Us -->
        <div class="bg-white rounded-2xl border border-slate-200/80 p-8 shadow-[0_4px_20px_rgba(0,0,0,0.03)] hover:shadow-xl hover:-translate-y-1 transition-all duration-300 flex flex-col justify-between">
          <div>
            <div class="w-12 h-12 bg-indigo-50 text-indigo-600 rounded-xl flex items-center justify-center mb-6">
              <i data-lucide="star" class="w-4 h-4"></i>
            </div>
            <h3 class="text-lg font-bold text-slate-900 mb-3">Why Choose Us</h3>
          </div>
          <div class="space-y-5 mt-4">
            <div class="flex items-start gap-3.5">
              <div class="w-9 h-9 rounded-lg bg-slate-50 border border-slate-100 flex-shrink-0 flex items-center justify-center">
                <i data-lucide="shield" class="w-3.5 h-3.5 text-indigo-600"></i>
              </div>
              <div>
                <p class="text-sm font-bold text-slate-900">Escrow Protection</p>
                <p class="text-xs text-slate-500 mt-0.5">Funds secured until work approved</p>
              </div>
            </div>
            <div class="flex items-start gap-3.5">
              <div class="w-9 h-9 rounded-lg bg-slate-50 border border-slate-100 flex-shrink-0 flex items-center justify-center">
                <i data-lucide="brain" class="w-3.5 h-3.5 text-indigo-600"></i>
              </div>
              <div>
                <p class="text-sm font-bold text-slate-900">AI-Powered Matching</p>
                <p class="text-xs text-slate-500 mt-0.5">Smart skill-to-job recommendations</p>
              </div>
            </div>
            <div class="flex items-start gap-3.5">
              <div class="w-9 h-9 rounded-lg bg-slate-50 border border-slate-100 flex-shrink-0 flex items-center justify-center">
                <i data-lucide="user-check" class="w-3.5 h-3.5 text-indigo-600"></i>
              </div>
              <div>
                <p class="text-sm font-bold text-slate-900">Verified Profiles</p>
                <p class="text-xs text-slate-500 mt-0.5">ID & skill verification on all pros</p>
              </div>
            </div>
            <div class="flex items-start gap-3.5">
              <div class="w-9 h-9 rounded-lg bg-slate-50 border border-slate-100 flex-shrink-0 flex items-center justify-center">
                <i data-lucide="headphones" class="w-3.5 h-3.5 text-indigo-600"></i>
              </div>
              <div>
                <p class="text-sm font-bold text-slate-900">24/7 Support</p>
                <p class="text-xs text-slate-500 mt-0.5">Live dispute resolution team</p>
              </div>
            </div>
          </div>
        </div>

      </div>
    </div>
  </section>

  <!-- ═══════════════════════ 10. PRICING ══════════════════════════════ -->
  <section id="pricing" class="py-28 px-6 reveal" style="background:#FAFAFA">
    <div class="max-w-[1200px] mx-auto">
      <div class="text-center mb-20">
        <span class="label-tag">Transparent</span>
        <h2 class="text-3xl sm:text-4xl font-serif font-medium mt-4 mb-4 text-charcoal">Simple Pricing</h2>
        <div class="section-divider mb-4"></div>
        <p class="text-gray-500 max-w-md mx-auto text-[13px] leading-relaxed">No hidden fees. Post free, pay only when you hire.</p>
      </div>

      <div class="grid sm:grid-cols-3 gap-8 lg:gap-12">

        <!-- Starter Card -->
        <div class="bg-white rounded-xl border border-gray-200 p-10 text-center transition-all duration-300 hover:-translate-y-1" style="box-shadow:0 8px 40px -12px rgba(0,0,0,.06)">
          <p class="label-tag mb-4">Starter</p>
          <p class="text-[32px] font-serif font-medium text-charcoal mb-1">Starter</p>
          <div class="flex items-baseline justify-center gap-0.5 mb-8">
            <span class="text-4xl font-serif font-medium text-charcoal">$10</span>
            <span class="text-[13px] text-gray-400 font-normal">/mo</span>
          </div>
          <a href="auth/register.php" class="block w-full btn-outline text-charcoal font-semibold py-3 text-[13px] mb-10">Get Started</a>
          <ul class="space-y-4 text-left text-[13px] text-gray-500">
            <li class="flex items-center gap-3"><span class="w-5 h-5 rounded-full bg-[#4338CA]/10 flex items-center justify-center flex-shrink-0"><i data-lucide="check" class="w-2 h-2 text-[#4338CA]"></i></span> Starter starting</li>
            <li class="flex items-center gap-3"><span class="w-5 h-5 rounded-full bg-[#4338CA]/10 flex items-center justify-center flex-shrink-0"><i data-lucide="check" class="w-2 h-2 text-[#4338CA]"></i></span> Frequentative contacts</li>
            <li class="flex items-center gap-3"><span class="w-5 h-5 rounded-full bg-[#4338CA]/10 flex items-center justify-center flex-shrink-0"><i data-lucide="check" class="w-2 h-2 text-[#4338CA]"></i></span> Device totallower</li>
            <li class="flex items-center gap-3"><span class="w-5 h-5 rounded-full bg-[#4338CA]/10 flex items-center justify-center flex-shrink-0"><i data-lucide="check" class="w-2 h-2 text-[#4338CA]"></i></span> Customized allocation</li>
          </ul>
        </div>

        <!-- Pro Card -->
        <div class="bg-white rounded-xl border border-gray-200 p-10 text-center transition-all duration-300 hover:-translate-y-2 relative" style="box-shadow:0 20px 60px -15px rgba(67,56,202,.12);transform:translateY(-8px)">
          <div class="absolute -top-px left-0 right-0 h-[3px] bg-[#4338CA] rounded-t-xl"></div>
          <p class="label-tag mb-4 text-[#4338CA]">Pro</p>
          <p class="text-[32px] font-serif font-medium text-charcoal mb-1">Pro</p>
          <div class="flex items-baseline justify-center gap-0.5 mb-8">
            <span class="text-4xl font-serif font-medium text-charcoal">$130</span>
            <span class="text-[13px] text-gray-400 font-normal">/mo</span>
          </div>
          <a href="auth/register.php?plan=pro" class="block w-full btn-primary text-white font-semibold py-3 text-[13px] mb-10">Go Pro</a>
          <ul class="space-y-4 text-left text-[13px] text-gray-500">
            <li class="flex items-center gap-3"><span class="w-5 h-5 rounded-full bg-[#4338CA]/10 flex items-center justify-center flex-shrink-0"><i data-lucide="check" class="w-2 h-2 text-[#4338CA]"></i></span> AI automate Anodet</li>
            <li class="flex items-center gap-3"><span class="w-5 h-5 rounded-full bg-[#4338CA]/10 flex items-center justify-center flex-shrink-0"><i data-lucide="check" class="w-2 h-2 text-[#4338CA]"></i></span> AI stylized approached Robot</li>
            <li class="flex items-center gap-3"><span class="w-5 h-5 rounded-full bg-[#4338CA]/10 flex items-center justify-center flex-shrink-0"><i data-lucide="check" class="w-2 h-2 text-[#4338CA]"></i></span> Prioritized headset support</li>
            <li class="flex items-center gap-3"><span class="w-5 h-5 rounded-full bg-[#4338CA]/10 flex items-center justify-center flex-shrink-0"><i data-lucide="check" class="w-2 h-2 text-[#4338CA]"></i></span> Compand motor support</li>
            <li class="flex items-center gap-3"><span class="w-5 h-5 rounded-full bg-[#4338CA]/10 flex items-center justify-center flex-shrink-0"><i data-lucide="check" class="w-2 h-2 text-[#4338CA]"></i></span> Prioritze communication</li>
            <li class="flex items-center gap-3"><span class="w-5 h-5 rounded-full bg-[#4338CA]/10 flex items-center justify-center flex-shrink-0"><i data-lucide="check" class="w-2 h-2 text-[#4338CA]"></i></span> Customized Intep matching</li>
          </ul>
        </div>

        <!-- Enterprise Card -->
        <div class="bg-white rounded-xl border border-gray-200 p-10 text-center transition-all duration-300 hover:-translate-y-1" style="box-shadow:0 8px 40px -12px rgba(0,0,0,.06)">
          <p class="label-tag mb-4">Enterprise</p>
          <p class="text-[32px] font-serif font-medium text-charcoal mb-1">Enterprise</p>
          <div class="flex items-baseline justify-center gap-0.5 mb-8">
            <span class="text-4xl font-serif font-medium text-charcoal">$200</span>
            <span class="text-[13px] text-gray-400 font-normal">/mo</span>
          </div>
          <a href="contact.php" class="block w-full btn-outline text-charcoal font-semibold py-3 text-[13px] mb-10">Contact Sales</a>
          <ul class="space-y-4 text-left text-[13px] text-gray-500">
            <li class="flex items-center gap-3"><span class="w-5 h-5 rounded-full bg-[#4338CA]/10 flex items-center justify-center flex-shrink-0"><i data-lucide="check" class="w-2 h-2 text-[#4338CA]"></i></span> Contact Sales</li>
            <li class="flex items-center gap-3"><span class="w-5 h-5 rounded-full bg-[#4338CA]/10 flex items-center justify-center flex-shrink-0"><i data-lucide="check" class="w-2 h-2 text-[#4338CA]"></i></span> Customize contacts</li>
            <li class="flex items-center gap-3"><span class="w-5 h-5 rounded-full bg-[#4338CA]/10 flex items-center justify-center flex-shrink-0"><i data-lucide="check" class="w-2 h-2 text-[#4338CA]"></i></span> Entier platform support</li>
            <li class="flex items-center gap-3"><span class="w-5 h-5 rounded-full bg-[#4338CA]/10 flex items-center justify-center flex-shrink-0"><i data-lucide="check" class="w-2 h-2 text-[#4338CA]"></i></span> Priority utilization</li>
            <li class="flex items-center gap-3"><span class="w-5 h-5 rounded-full bg-[#4338CA]/10 flex items-center justify-center flex-shrink-0"><i data-lucide="check" class="w-2 h-2 text-[#4338CA]"></i></span> Customized securities</li>
            <li class="flex items-center gap-3"><span class="w-5 h-5 rounded-full bg-[#4338CA]/10 flex items-center justify-center flex-shrink-0"><i data-lucide="check" class="w-2 h-2 text-[#4338CA]"></i></span> Website inarch support</li>
          </ul>
        </div>

      </div>
    </div>
  </section>

  <!-- ═══════════════════════ 11. CTA BANNER ═══════════════════════════ -->
  <section class="py-24 px-6 reveal">
    <div class="max-w-4xl mx-auto text-center">
      <div class="p-12 sm:p-16 relative overflow-hidden" style="background:linear-gradient(135deg,#4338CA,#6366F1)">
        <div class="absolute inset-0 opacity-[0.03]" style="background-image:radial-gradient(circle,white 1px,transparent 1px);background-size:24px 24px"></div>
        <div class="relative z-10">
          <h2 class="text-2xl sm:text-3xl font-serif font-medium text-white mb-4">Ready to Get Started?</h2>
          <p class="text-gray-300 text-[13px] mb-10 max-w-lg mx-auto leading-relaxed">Join thousands of professionals and businesses already using JobHub to build the future of work.</p>
          <div class="flex flex-col sm:flex-row items-center gap-4 justify-center">
            <a href="auth/register.php?role=client" class="font-semibold text-[13px] tracking-wide text-charcoal px-8 py-3.5 bg-white hover:bg-gray-100 transition-all inline-flex items-center justify-center gap-2.5" style="border-radius:4px">
              <span class="text-[11px]">+</span>
              Post a Project
            </a>
            <a href="auth/register.php?role=freelancer" class="font-medium text-[13px] tracking-wide text-white px-8 py-3.5 bg-white/10 border border-white/20 hover:bg-white/20 hover:border-white/30 transition-all inline-flex items-center justify-center gap-2.5" style="border-radius:4px">
              Join as Freelancer
              <i data-lucide="arrow-right" class="w-3 h-3 group-hover:translate-x-1 transition-transform duration-200"></i>
            </a>
          </div>
        </div>
      </div>
    </div>
  </section>

  <!-- ═══════════════════════ 12. FOOTER ════════════════════════════════ -->
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
      checkReveal();
    });

    // Mobile hamburger
    document.getElementById('hamburger').addEventListener('click', () => {
      document.getElementById('mobile-menu').classList.toggle('open');
    });

    // Tab switcher
    function switchTab(tab) {
      ['client', 'freelancer'].forEach(t => {
        document.getElementById('panel-' + t).classList.add('hidden');
        document.getElementById('tab-' + t).classList.remove('active');
        document.getElementById('tab-' + t).classList.add('text-gray-400');
      });
      document.getElementById('panel-' + tab).classList.remove('hidden');
      document.getElementById('tab-' + tab).classList.add('active');
      document.getElementById('tab-' + tab).classList.remove('text-gray-400');
    }

    // Scroll reveal
    const reveals = document.querySelectorAll('.reveal');
    function checkReveal() {
      reveals.forEach(el => {
        if (el.getBoundingClientRect().top < window.innerHeight - 80) el.classList.add('visible');
      });
    }
    checkReveal();

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
    setupSearchDropdown('heroSearchDropdown', 'heroSearchDropdownBtn', 'hero-search', 'heroSearchType');

    // Hero search
    document.getElementById('hero-search').addEventListener('keydown', function(e) {
      if (e.key === 'Enter') {
        const q = this.value.trim();
        const type = this.dataset.searchType || 'talent';
        if (q) {
          if (type === 'talent') {
            window.location.href = 'browse_freelancers.php?search=' + encodeURIComponent(q);
          } else {
            window.location.href = 'browse_jobs.php?search=' + encodeURIComponent(q);
          }
        }
      }
    });

    // Nav search
    document.getElementById('nav-search').addEventListener('keydown', e => {
      if (e.key === 'Enter') {
        const q = e.target.value.trim();
        const type = e.target.dataset.searchType || 'talent';
        if (q) window.location.href = (type === 'talent' ? 'browse_freelancers.php?search=' : 'browse_jobs.php?search=') + encodeURIComponent(q);
      }
    });

    // Testimonial scroll
    function scrollTestimonials(dir) {
      const track = document.getElementById('testimonialTrack');
      if (!track) return;
      const card = track.querySelector('.testi-card');
      track.scrollBy({ left: dir * (card ? card.offsetWidth + 16 : 300), behavior: 'smooth' });
    }

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
<?php $conn->close(); ?>
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
