<?php
$page_title = 'Freelancer Profile';
require_once '../auth/auth.php';
require_once '../config/db.php';

$viewerId = $_SESSION['user_id'] ?? null;
$viewerRole = $_SESSION['user_role'] ?? null;

$viewUserId = sanitize_int($_GET['id'] ?? 0);

if ($viewUserId <= 0 && $viewerId) {
    $viewUserId = $viewerId;
} elseif ($viewUserId <= 0) {
    redirect('/finalproject/auth/login.php');
}

$stmt = $conn->prepare('
    SELECT u.id AS user_id, u.name, u.email, u.profile_image, u.phone, u.created_at AS member_since,
           f.id AS freelancer_id, f.title, f.bio, f.hourly_rate, f.portfolio_url,
           f.resume_file, f.years_of_experience, f.availability, f.skills_vector, f.updated_at
    FROM users u
    JOIN freelancers f ON u.id = f.user_id
    WHERE u.id = ?
');
$stmt->bind_param('i', $viewUserId);
$stmt->execute();
$profile = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$profile) {
    redirect('/finalproject/');
}

$freelancerId = $profile['freelancer_id'];
$isOwner = ($viewerId == $viewUserId);

$stmtSkills = $conn->prepare('
    SELECT s.id, s.skill_name, s.category
    FROM freelancer_skills fs
    JOIN skills s ON fs.skill_id = s.id
    WHERE fs.freelancer_id = ?
    ORDER BY s.category, s.skill_name
');
$stmtSkills->bind_param('i', $freelancerId);
$stmtSkills->execute();
$skillsResult = $stmtSkills->get_result();
$skills = [];
while ($row = $skillsResult->fetch_assoc()) {
    $skills[] = $row;
}
$stmtSkills->close();

$stmtJobs = $conn->prepare("
    SELECT COUNT(*) AS total
    FROM contracts c
    WHERE c.freelancer_id = ? AND c.status = 'completed'
");
$stmtJobs->bind_param('i', $viewUserId);
$stmtJobs->execute();
$completedJobs = $stmtJobs->get_result()->fetch_assoc()['total'];
$stmtJobs->close();

$stmtEarnings = $conn->prepare("
    SELECT COALESCE(SUM(total_amount), 0) AS total
    FROM payments
    WHERE payee_id = ? AND status = 'completed'
");
$stmtEarnings->bind_param('i', $viewUserId);
$stmtEarnings->execute();
$totalEarnings = $stmtEarnings->get_result()->fetch_assoc()['total'];
$stmtEarnings->close();

$stmtRating = $conn->prepare('
    SELECT COALESCE(AVG(rating), 0) AS avg_rating, COUNT(*) AS review_count
    FROM reviews
    WHERE reviewee_id = ?
');
$stmtRating->bind_param('i', $viewUserId);
$stmtRating->execute();
$ratingData = $stmtRating->get_result()->fetch_assoc();
$avgRating = round($ratingData['avg_rating'], 1);
$reviewCount = $ratingData['review_count'];
$stmtRating->close();

$stmtReviews = $conn->prepare('
    SELECT r.rating, r.comment, r.created_at,
           u.name AS reviewer_name, u.profile_image AS reviewer_image
    FROM reviews r
    JOIN users u ON r.reviewer_id = u.id
    WHERE r.reviewee_id = ?
    ORDER BY r.created_at DESC
    LIMIT 5
');
$stmtReviews->bind_param('i', $viewUserId);
$stmtReviews->execute();
$reviewsResult = $stmtReviews->get_result();
$reviews = [];
while ($row = $reviewsResult->fetch_assoc()) {
    $reviews[] = $row;
}
$stmtReviews->close();

// Similar freelancers (same skills, different user)
$similarFreelancers = [];
if (!empty($skills)) {
    $skillIds = array_column($skills, 'id');
    $placeholders = implode(',', array_fill(0, count($skillIds), '?'));
    $simStmt = $conn->prepare("
        SELECT DISTINCT u.id, u.name, u.profile_image, f.title, f.hourly_rate,
               COALESCE(AVG(r.rating), 0) AS avg_rating
        FROM users u
        JOIN freelancers f ON u.id = f.user_id
        LEFT JOIN reviews r ON r.reviewee_id = u.id
        WHERE u.id != ? AND u.role = 'freelancer'
        AND f.id IN (SELECT fs.freelancer_id FROM freelancer_skills fs WHERE fs.skill_id IN ($placeholders))
        GROUP BY u.id
        ORDER BY avg_rating DESC
        LIMIT 3
    ");
    $simParams = array_merge([$viewUserId], $skillIds);
    $simTypes = 'i' . str_repeat('i', count($skillIds));
    $simStmt->bind_param($simTypes, ...$simParams);
    $simStmt->execute();
    $simResult = $simStmt->get_result();
    while ($sr = $simResult->fetch_assoc()) {
        $similarFreelancers[] = $sr;
    }
    $simStmt->close();
}

$profileFields = [
    !empty($profile['title']),
    !empty($profile['bio']),
    !empty($profile['hourly_rate']),
    !empty($profile['years_of_experience']),
    !empty($profile['portfolio_url']),
    count($skills) > 0,
];
$completedFields = count(array_filter($profileFields));
$totalFields = count($profileFields);
$completionPct = $totalFields > 0 ? round(($completedFields / $totalFields) * 100) : 0;

$pageTitle = 'Freelancer Profile';
$pageSubtitle = 'Public professional profile';
$activePage = 'profile';
$unreadCount = get_unread_message_count($viewerId, $viewerRole ?? 'freelancer');

// Set $user for header: use logged-in user's own data, not the profile being viewed
if ($viewerRole === 'client' && $viewerId) {
    $_viewerStmt = $conn->prepare('SELECT name, profile_image FROM users WHERE id = ?');
    $_viewerStmt->bind_param('i', $viewerId);
    $_viewerStmt->execute();
    $_viewerRow = $_viewerStmt->get_result()->fetch_assoc();
    $_viewerStmt->close();
    $user = ['name' => $_viewerRow['name'] ?? 'Client', 'profile_image' => $_viewerRow['profile_image'] ?? null];
} elseif ($viewerRole === 'freelancer' && $viewerId) {
    $_viewerStmt = $conn->prepare('SELECT name, profile_image FROM users WHERE id = ?');
    $_viewerStmt->bind_param('i', $viewerId);
    $_viewerStmt->execute();
    $_viewerRow = $_viewerStmt->get_result()->fetch_assoc();
    $_viewerStmt->close();
    $user = ['name' => $_viewerRow['name'] ?? 'Freelancer', 'profile_image' => $_viewerRow['profile_image'] ?? null];
} else {
    $user = ['name' => 'Guest', 'profile_image' => null];
}

// Show appropriate header based on viewer's role
if ($viewerRole === 'freelancer') {
    require_once __DIR__ . '/../components/freelancer_header.php';
} elseif ($viewerRole === 'client') {
    require_once __DIR__ . '/../includes/client_topbar.php';
} else {
    // Simple public header for clients and guests
    $_base = '/finalproject';
?>
<!DOCTYPE html>
<html lang="en" class="scroll-smooth">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width,initial-scale=1.0">
    <title><?= sanitize_string($pageTitle) ?> – JobHub</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800;900&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <link rel="icon" type="image/png" sizes="32x32" href="/finalproject/assets/upload/logos/logo.png">
    <link rel="stylesheet" href="/finalproject/shared/dark-mode.css">
    <script src="/finalproject/shared/dark-toggle.js"></script>
    <script>
    tailwind.config={theme:{extend:{fontFamily:{inter:['Inter','sans-serif']},colors:{primary:{DEFAULT:'#2563eb',dark:'#1d4ed8',light:'#3b82f6'},accent:{DEFAULT:'#0ea5e9',dark:'#0284c7'},surface:{DEFAULT:'#f8fafc',card:'#ffffff',border:'#e2e8f0'}}}}}
    </script>
    <style>
    *{font-family:'Inter',sans-serif}
    body{background:#f8fafc;color:#1e293b}
    .grad-text{background:linear-gradient(135deg,#2563eb 0%,#0ea5e9 60%,#6366f1 100%);-webkit-background-clip:text;-webkit-text-fill-color:transparent;background-clip:text}
    .btn-grad{background:linear-gradient(135deg,#2563eb,#0ea5e9);transition:opacity .25s,transform .2s}
    .btn-grad:hover{opacity:.88;transform:translateY(-2px)}
    </style>
</head>
<body>
    <nav class="fixed top-0 left-0 right-0 z-50 bg-white/85 backdrop-blur-xl border-b border-gray-100">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="flex items-center justify-between h-16">
                <a href="/finalproject/index.php" class="flex items-center gap-2">
                    <div class="w-9 h-9 rounded-xl bg-gradient-to-br from-blue-600 to-cyan-500 flex items-center justify-center shadow-lg shadow-blue-500/25">
                        <i class="fas fa-bolt text-white text-sm"></i>
                    </div>
                    <span class="text-xl font-extrabold text-gray-900">Job<span class="grad-text">Hub</span></span>
                </a>
                <div class="flex items-center gap-3">
                    <button onclick="toggleDarkMode()" class="w-9 h-9 rounded-xl bg-gray-100 dark:bg-gray-800 flex items-center justify-center text-gray-500 dark:text-gray-400 hover:bg-gray-200 dark:hover:bg-gray-700 transition-colors">
                        <i class="fas fa-moon text-sm dark:hidden"></i>
                        <i class="fas fa-sun text-sm hidden dark:inline"></i>
                    </button>
                    <?php if (is_logged_in()): ?>
                        <a href="<?= get_dashboard_url(current_role()) ?>" class="text-sm font-semibold text-gray-700 hover:text-gray-900 transition-colors">
                            <i class="fas fa-th-large mr-1"></i> Dashboard
                        </a>
                    <?php else: ?>
                        <a href="/finalproject/auth/login.php" class="text-sm font-semibold text-gray-700 hover:text-gray-900">Log In</a>
                        <a href="/finalproject/auth/register.php" class="btn-grad text-white text-sm font-semibold px-5 py-2 rounded-xl shadow-lg shadow-blue-500/20">Sign Up</a>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </nav>
    <div class="h-16"></div>
<?php
}
?>

<style>
    .profile-header-card {
        background: #fff;
        border: 1px solid #e5edf6;
        border-radius: 16px;
        box-shadow: 0 2px 12px rgba(15, 23, 42, .04);
        padding: 32px 40px;
    }

    .profile-avatar-lg {
        width: 140px;
        height: 140px;
        border-radius: 50%;
        object-fit: cover;
        border: 4px solid #fff;
        box-shadow: 0 4px 20px rgba(0, 0, 0, .1);
    }

    .profile-tab {
        padding: 12px 28px;
        font-size: 14px;
        font-weight: 600;
        color: #64748b;
        border-bottom: 2px solid transparent;
        cursor: pointer;
        transition: all .2s;
        background: none;
        border-top: none;
        border-left: none;
        border-right: none;
    }

    .profile-tab:hover {
        color: #2563eb;
    }

    .profile-tab.active {
        color: #2563eb;
        border-bottom-color: #2563eb;
    }

    .profile-sidebar-card {
        background: #fff;
        border: 1px solid #e5edf6;
        border-radius: 16px;
        box-shadow: 0 2px 12px rgba(15, 23, 42, .04);
        padding: 24px;
    }

    .profile-section-title {
        font-size: 12px;
        font-weight: 700;
        color: #64748b;
        text-transform: uppercase;
        letter-spacing: 0.06em;
        margin-bottom: 16px;
    }

    .website-link {
        display: flex;
        align-items: center;
        gap: 10px;
        padding: 10px 0;
        color: #475569;
        font-size: 13px;
        font-weight: 500;
        text-decoration: none;
        border-bottom: 1px solid #f1f5f9;
        transition: color .15s;
    }

    .website-link:last-child {
        border-bottom: none;
    }

    .website-link:hover {
        color: #2563eb;
    }

    .website-link i {
        width: 20px;
        text-align: center;
        color: #94a3b8;
        font-size: 14px;
    }

    .skill-tag-pill {
        display: inline-flex;
        align-items: center;
        padding: 6px 16px;
        background: #f8fafc;
        border: 1px solid #e2e8f0;
        border-radius: 20px;
        font-size: 13px;
        font-weight: 500;
        color: #475569;
        transition: all .15s;
        cursor: default;
    }

    .skill-tag-pill:hover {
        background: #eff6ff;
        border-color: #bfdbfe;
        color: #2563eb;
    }

    .social-icon {
        width: 36px;
        height: 36px;
        border-radius: 10px;
        display: flex;
        align-items: center;
        justify-content: center;
        color: #94a3b8;
        border: 1px solid #e2e8f0;
        transition: all .15s;
        text-decoration: none;
    }

    .social-icon:hover {
        color: #2563eb;
        border-color: #bfdbfe;
        background: #eff6ff;
    }

    .social-icon.github:hover {
        color: #333;
        border-color: #d1d5db;
        background: #f3f4f6;
    }

    .social-icon.twitter:hover {
        color: #1da1f2;
        border-color: #bae6fd;
        background: #f0f9ff;
    }

    .social-icon.linkedin:hover {
        color: #0a66c2;
        border-color: #bfdbfe;
        background: #eff6ff;
    }

    .social-icon.facebook:hover {
        color: #1877f2;
        border-color: #bfdbfe;
        background: #eff6ff;
    }

    .back-link {
        display: inline-flex;
        align-items: center;
        gap: 6px;
        font-size: 13px;
        font-weight: 600;
        color: #64748b;
        text-decoration: none;
        margin-bottom: 20px;
        transition: color .15s;
    }

    .back-link:hover {
        color: #2563eb;
    }

    .promo-card {
        background: linear-gradient(135deg, #eff6ff 0%, #ecfeff 100%);
        border: 1px solid #e0f2fe;
        border-radius: 16px;
        padding: 28px 24px;
        text-align: center;
    }

    .similar-card {
        display: flex;
        align-items: center;
        gap: 12px;
        padding: 12px 0;
        border-bottom: 1px solid #f1f5f9;
    }

    .similar-card:last-child {
        border-bottom: none;
    }
</style>

<div class="max-w-7xl mx-auto px-4 sm:px-6 py-8">

    <!-- Back Link -->
    <?php if ($viewerRole === 'freelancer'): ?>
        <a href="home.php" class="back-link">
    <?php elseif ($viewerRole === 'client'): ?>
        <a href="/finalproject/client/dashboard.php" class="back-link">
    <?php else: ?>
        <a href="/finalproject/index.php" class="back-link">
    <?php endif; ?>
        <i class="fas fa-chevron-left text-xs"></i> Back to Home
    </a>

    <?php display_flash('success'); ?>
    <?php display_flash('error'); ?>

    <!-- ═══════════════ PROFILE HEADER CARD ═══════════════ -->
    <div class="profile-header-card mb-8 fade-in">
        <div class="flex flex-col lg:flex-row lg:items-center gap-8">
            <!-- Left: Avatar -->
            <div class="flex-shrink-0">
                <img src="<?= get_profile_image($profile['profile_image']) . '?v=' . time() ?>"
                    alt="<?= sanitize_string($profile['name']) ?>"
                    class="profile-avatar-lg mx-auto lg:mx-0">
            </div>

            <!-- Center: Name, Title, Buttons -->
            <div class="flex-1 min-w-0 text-center lg:text-left">
                <div class="flex flex-col lg:flex-row lg:items-center gap-2 lg:gap-6 mb-2">
                    <h1 class="text-2xl font-extrabold text-gray-900"><?= sanitize_string($profile['name']) ?></h1>
                    <span class="text-xl font-bold text-gray-700"><?= format_currency($profile['hourly_rate'] ?? 0) ?>/hr</span>
                </div>
                <?php if (!empty($profile['title'])): ?>
                    <p class="text-sm text-gray-500 mb-1"><?= sanitize_string($profile['title']) ?></p>
                <?php endif; ?>
                <div class="flex items-center gap-2 justify-center lg:justify-start mb-5">
                    <div class="w-5 h-5 rounded bg-blue-500 flex items-center justify-center">
                        <i class="fas fa-briefcase text-white text-[9px]"></i>
                    </div>
                    <span class="text-sm font-medium text-blue-600">JobHub Freelancer</span>
                </div>
                <div class="flex items-center gap-3 justify-center lg:justify-start">
                    <?php if ($isOwner): ?>
                        <a href="profile_edit.php" class="inline-flex items-center gap-2 px-6 py-2.5 bg-emerald-500 hover:bg-emerald-600 text-white text-sm font-semibold rounded-lg transition-all shadow-sm">
                            <i class="fas fa-edit text-xs"></i> Edit Profile
                        </a>
                        <?php if (!empty($profile['resume_file'])): ?>
                            <a href="../assets/upload/resumes/<?= sanitize_string(basename($profile['resume_file'])) ?>" target="_blank" class="inline-flex items-center gap-2 px-6 py-2.5 border-2 border-gray-200 hover:border-blue-300 text-gray-600 hover:text-blue-600 text-sm font-semibold rounded-lg transition-all">
                                <i class="fas fa-file-pdf text-xs"></i> Resume
                            </a>
                        <?php endif; ?>
                    <?php elseif (is_logged_in() && $viewerRole === 'client'): ?>
                        <a href="../client/messages.php?freelancer=<?= $viewUserId ?>" class="inline-flex items-center gap-2 px-6 py-2.5 bg-emerald-500 hover:bg-emerald-600 text-white text-sm font-semibold rounded-lg transition-all shadow-sm">
                            <i class="fas fa-paper-plane text-xs"></i> Contact
                        </a>
                        <?php if (!empty($profile['resume_file'])): ?>
                            <a href="../assets/upload/resumes/<?= sanitize_string(basename($profile['resume_file'])) ?>" target="_blank" class="inline-flex items-center gap-2 px-6 py-2.5 border-2 border-gray-200 hover:border-blue-300 text-gray-600 hover:text-blue-600 text-sm font-semibold rounded-lg transition-all">
                                <i class="fas fa-file-pdf text-xs"></i> Resume
                            </a>
                        <?php endif; ?>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Right: Details -->
            <div class="flex-shrink-0">
                <div class="space-y-3 text-sm">
                    <div class="flex items-center gap-3">
                        <span class="text-gray-400 w-28">Availability:</span>
                        <span class="font-semibold text-gray-700"><?= sanitize_string($profile['availability'] ?? 'Not set') ?></span>
                        <?php if (($profile['availability'] ?? '') === 'Available'): ?>
                            <span class="px-2 py-0.5 bg-emerald-100 text-emerald-700 text-[10px] font-bold rounded-full uppercase">available</span>
                        <?php endif; ?>
                    </div>
                    <?php if ($profile['years_of_experience'] > 0): ?>
                        <div class="flex items-center gap-3">
                            <span class="text-gray-400 w-28">Experience:</span>
                            <span class="font-semibold text-gray-700"><?= $profile['years_of_experience'] ?> year<?= $profile['years_of_experience'] != 1 ? 's' : '' ?></span>
                        </div>
                    <?php endif; ?>
                    <div class="flex items-center gap-3">
                        <span class="text-gray-400 w-28">Member since:</span>
                        <span class="font-semibold text-gray-700"><?= date('M Y', strtotime($profile['member_since'])) ?></span>
                    </div>
                    <div class="flex items-center gap-3">
                        <span class="text-gray-400 w-28">Rating:</span>
                        <span class="font-semibold text-gray-700">
                            <i class="fas fa-star text-amber-400 text-xs"></i>
                            <?= $avgRating > 0 ? number_format($avgRating, 1) : '—' ?>
                            <span class="text-gray-400 font-normal">(<?= $reviewCount ?>)</span>
                        </span>
                    </div>
                </div>
                <!-- Social Icons -->
                <!-- <div class="flex items-center gap-2 mt-5 justify-center lg:justify-start">
                    <?php if (!empty($profile['portfolio_url'])): ?>
                        <a href="<?= sanitize_string($profile['portfolio_url']) ?>" target="_blank" rel="noopener" class="social-icon" title="Portfolio"><i class="fas fa-globe"></i></a>
                    <?php endif; ?>
                    <a href="#" class="social-icon github" title="GitHub"><i class="fab fa-github"></i></a>
                    <a href="#" class="social-icon facebook" title="Facebook"><i class="fab fa-facebook-f"></i></a>
                    <a href="#" class="social-icon linkedin" title="LinkedIn"><i class="fab fa-linkedin-in"></i></a>
                    <?php if (!empty($profile['portfolio_url'])): ?>
                        <a href="<?= sanitize_string($profile['portfolio_url']) ?>" target="_blank" rel="noopener" class="social-icon" title="Website"><i class="fas fa-link"></i></a>
                    <?php endif; ?>
                </div> -->
            </div>
        </div>
    </div>

    <!-- ═══════════════ TABS ═══════════════ -->
    <div class="border-b border-gray-200 mb-8">
        <div class="flex gap-0">
            <button onclick="switchProfileTab('profile')" id="ptab-profile" class="profile-tab active">Profile</button>
            <button onclick="switchProfileTab('resume')" id="ptab-resume" class="profile-tab">CV/Resume</button>
        </div>
    </div>

    <!-- ═══════════════ PROFILE TAB CONTENT ═══════════════ -->
    <div id="ptab-panel-profile" class="profile-panel">
        <div class="flex flex-col lg:flex-row gap-8">

            <!-- Left Sidebar -->
            <div class="w-full lg:w-64 flex-shrink-0 space-y-6">
                <!-- Websites -->
                <div class="profile-sidebar-card">
                    <h3 class="profile-section-title">Websites</h3>
                    <?php if (!empty($profile['portfolio_url'])): ?>
                        <a href="<?= sanitize_string($profile['portfolio_url']) ?>" target="_blank" rel="noopener" class="website-link">
                            <i class="fas fa-globe"></i> Portfolio
                        </a>
                    <?php endif; ?>
                    <a href="#" class="website-link">
                        <i class="fab fa-github"></i> Github
                    </a>
                    <a href="#" class="website-link">
                        <i class="fab fa-facebook-f"></i> Facebook
                    </a>
                    <a href="#" class="website-link">
                        <i class="fab fa-linkedin-in"></i> LinkedIn
                    </a>
                    <a href="#" class="website-link">
                        <i class="fas fa-link"></i> Website
                    </a>
                </div>
            </div>

            <!-- Main Content -->
            <div class="flex-1 min-w-0 space-y-8">
                <!-- About -->
                <?php if (!empty($profile['bio'])): ?>
                    <div>
                        <h3 class="profile-section-title mb-4">About</h3>
                        <p class="text-sm text-gray-600 leading-relaxed whitespace-pre-line"><?= nl2br(sanitize_string($profile['bio'])) ?></p>
                    </div>
                <?php else: ?>
                    <div>
                        <h3 class="profile-section-title mb-4">About</h3>
                        <p class="text-sm text-gray-400 italic">No bio provided yet.</p>
                    </div>
                <?php endif; ?>

                <!-- Skills -->
                <?php if (!empty($skills)): ?>
                    <div>
                        <h3 class="profile-section-title mb-4">Skills</h3>
                        <div class="flex flex-wrap gap-2">
                            <?php foreach ($skills as $s): ?>
                                <span class="skill-tag-pill"><?= sanitize_string($s['skill_name']) ?></span>
                            <?php endforeach; ?>
                        </div>
                    </div>
                <?php endif; ?>

                <!-- Reviews Section -->
                <?php if (!empty($reviews) || $reviewCount > 0): ?>
                    <div>
                        <div class="flex items-center justify-between mb-4">
                            <h3 class="profile-section-title mb-0">Reviews</h3>
                            <?php if ($reviewCount > 5): ?>
                                <a href="reviews.php" class="text-xs text-blue-600 hover:text-blue-700 font-medium transition-colors">
                                    View All +<?= $reviewCount - 5 ?> more <i class="fas fa-arrow-right ml-1"></i>
                                </a>
                            <?php endif; ?>
                        </div>

                        <?php if (!empty($reviews)): ?>
                            <div class="space-y-4">
                                <?php foreach ($reviews as $review): ?>
                                    <div class="p-4 rounded-xl bg-gray-50 border border-gray-100">
                                        <div class="flex items-start gap-3">
                                            <img src="<?= get_profile_image($review['reviewer_image']) ?>"
                                                class="w-10 h-10 rounded-full object-cover flex-shrink-0" alt="">
                                            <div class="flex-1 min-w-0">
                                                <div class="flex items-center justify-between mb-1">
                                                    <p class="text-sm font-semibold text-gray-900"><?= sanitize_string($review['reviewer_name']) ?></p>
                                                    <span class="text-[11px] text-gray-400"><?= time_ago($review['created_at']) ?></span>
                                                </div>
                                                <div class="flex items-center gap-0.5 mb-2">
                                                    <?php for ($i = 1; $i <= 5; $i++): ?>
                                                        <i class="fas fa-star text-xs <?= $i <= $review['rating'] ? 'text-amber-400' : 'text-gray-200' ?>"></i>
                                                    <?php endfor; ?>
                                                    <span class="text-xs font-semibold text-gray-600 ml-1"><?= $review['rating'] ?>/5</span>
                                                </div>
                                                <?php if (!empty($review['comment'])): ?>
                                                    <p class="text-sm text-gray-600 leading-relaxed"><?= nl2br(sanitize_string($review['comment'])) ?></p>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php else: ?>
                            <div class="text-center py-8 bg-gray-50 rounded-xl">
                                <p class="text-sm text-gray-400">No reviews yet.</p>
                            </div>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>
            </div>

            <!-- Right Sidebar -->
            <div class="bg-white rounded-2xl p-6 border border-gray-100 shadow-sm mb-6 w-60 fade-in">
                <div class="w-10 h-10 rounded-xl bg-amber-50 flex items-center justify-center text-amber-500 mb-3">
                    <i class="fas fa-envelope-open-text text-base"></i>
                </div>
                <h3 class="text-sm font-bold text-gray-900 mb-1">Direct Recruitment</h3>
                <p class="text-xs text-gray-500 mb-4 leading-relaxed">
                    Have an active job opening that matches this freelancer's skillset? Submit a proposal directly to their dashboard.
                </p>

                <?php if (!($isOwner ?? false)): ?>
                    <a href="/finalproject/client/invite_jobs.php?freelancer_id=<?= $profile['freelancer_id'] ?? 0 ?>"
                        class="w-full inline-flex items-center justify-center gap-2 px-4 py-2.5 bg-gray-900 hover:bg-gray-800 text-white text-xs font-bold rounded-xl transition-all shadow-sm">
                        <i class="fas fa-paper-plane text-[10px]"></i> Send Job Offer
                    </a>
                <?php else: ?>
                    <div class="w-full text-center py-2 bg-gray-50 text-gray-400 text-[11px] font-medium rounded-xl border border-dashed border-gray-200">
                        Clients see your hiring form here
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- ═══════════════ RESUME TAB CONTENT ═══════════════ -->
    <div id="ptab-panel-resume" class="profile-panel hidden">
        <div class="profile-sidebar-card max-w-2xl">
            <?php if (!empty($profile['resume_file'])): ?>
                <div class="flex items-center gap-4 p-5 bg-gray-50 rounded-xl border border-gray-200">
                    <div class="w-14 h-14 rounded-xl bg-red-100 flex items-center justify-center flex-shrink-0">
                        <i class="fas fa-file-pdf text-red-500 text-xl"></i>
                    </div>
                    <div class="flex-1 min-w-0">
                        <p class="text-sm font-bold text-gray-900 truncate"><?= sanitize_string(basename($profile['resume_file'])) ?></p>
                        <p class="text-xs text-gray-400 mt-0.5">PDF Resume</p>
                    </div>
                    <a href="../assets/upload/resumes/<?= sanitize_string(basename($profile['resume_file'])) ?>"
                        target="_blank" class="inline-flex items-center gap-2 px-5 py-2.5 bg-blue-600 hover:bg-blue-700 text-white text-sm font-semibold rounded-lg transition-all flex-shrink-0">
                        <i class="fas fa-download text-xs"></i> Download
                    </a>
                </div>
                <!-- PDF Preview -->
                <div class="mt-6 rounded-xl overflow-hidden border border-gray-200" style="height: 600px;">
                    <iframe src="../assets/upload/resumes/<?= sanitize_string(basename($profile['resume_file'])) ?>"
                        class="w-full h-full" frameborder="0"></iframe>
                </div>
            <?php else: ?>
                <div class="text-center py-16">
                    <div class="w-20 h-20 rounded-2xl bg-gray-100 flex items-center justify-center mx-auto mb-4">
                        <i class="fas fa-file-pdf text-3xl text-gray-300"></i>
                    </div>
                    <h4 class="text-lg font-bold text-gray-900 mb-2">No Resume Uploaded</h4>
                    <p class="text-sm text-gray-400 mb-5">Upload your resume to showcase your qualifications to clients.</p>
                    <?php if ($isOwner): ?>
                        <a href="profile_edit.php" class="inline-flex items-center gap-2 px-6 py-2.5 btn-grad text-white font-semibold rounded-lg text-sm">
                            <i class="fas fa-upload text-xs"></i> Upload Resume
                        </a>
                    <?php endif; ?>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- ═══════════════ PROFILE COMPLETION BANNER ═══════════════ -->
    <?php if ($isOwner && $completionPct < 100): ?>
        <div class="mt-8 bg-gradient-to-r from-blue-50 to-cyan-50 rounded-2xl p-6 border border-blue-100 fade-in">
            <div class="flex items-center gap-4">
                <div class="w-12 h-12 rounded-xl bg-blue-100 flex items-center justify-center flex-shrink-0">
                    <i class="fas fa-magic text-blue-500 text-lg"></i>
                </div>
                <div class="flex-1">
                    <h4 class="text-sm font-bold text-gray-900 mb-1">Complete Your Profile (<?= $completionPct ?>%)</h4>
                    <p class="text-xs text-gray-500">A complete profile gets 3x more job invitations from clients.</p>
                </div>
                <a href="profile_edit.php" class="inline-flex items-center gap-2 px-5 py-2.5 btn-grad text-white text-sm font-semibold rounded-xl shadow-lg shadow-blue-500/25 flex-shrink-0">
                    <i class="fas fa-arrow-right text-xs"></i> Complete
                </a>
            </div>
        </div>
    <?php endif; ?>
</div>

<script>
    function switchProfileTab(tab) {
        document.querySelectorAll('.profile-panel').forEach(p => p.classList.add('hidden'));
        document.querySelectorAll('.profile-tab').forEach(t => t.classList.remove('active'));
        document.getElementById('ptab-panel-' + tab).classList.remove('hidden');
        document.getElementById('ptab-' + tab).classList.add('active');
    }
</script>

<?php $conn->close(); ?>
<?php require_once __DIR__ . '/../components/freelancer_footer.php'; ?>