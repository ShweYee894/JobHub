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
    redirect('/jobhub/auth/login.php');
}

$stmt = $conn->prepare('
    SELECT u.id AS user_id, u.name, u.email, u.profile_image, u.phone, u.created_at AS member_since,
           f.id AS freelancer_id, f.title, f.bio, f.hourly_rate, f.portfolio_url, f.social_links,
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
    redirect('/jobhub/');
}

$freelancerId = $profile['freelancer_id'];
$isOwner = ($viewerId == $viewUserId);

// Parse social_links JSON (fallback to legacy portfolio_url)
$socialLinks = ['website' => '', 'github' => '', 'linkedin' => ''];
if (!empty($profile['social_links'])) {
    $decoded = json_decode($profile['social_links'], true);
    if (is_array($decoded)) {
        $socialLinks['website'] = $decoded['website'] ?? '';
        $socialLinks['github'] = $decoded['github'] ?? '';
        $socialLinks['linkedin'] = $decoded['linkedin'] ?? '';
    }
} elseif (!empty($profile['portfolio_url'])) {
    $socialLinks['website'] = $profile['portfolio_url'];
}

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
    WHERE reviewee_id = ? AND COALESCE(is_hidden, 0) = 0
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
    WHERE r.reviewee_id = ? AND COALESCE(r.is_hidden, 0) = 0
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
        LEFT JOIN reviews r ON r.reviewee_id = u.id AND COALESCE(r.is_hidden, 0) = 0
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
    !empty($socialLinks['website']) || !empty($socialLinks['github']) || !empty($socialLinks['linkedin']),
    count($skills) > 0,
];
$completedFields = count(array_filter($profileFields));
$totalFields = count($profileFields);
$completionPct = $totalFields > 0 ? round(($completedFields / $totalFields) * 100) : 0;

$pageTitle = 'Freelancer Profile';
$pageSubtitle = 'Public professional profile';
$activePage = 'profile';
$unreadCount = $viewerId ? get_unread_message_count($viewerId, $viewerRole ?? 'freelancer') : 0;

// Set $user for header: use logged-in user's own data, not the profile being viewed
if ($viewerId) {
    $_viewerStmt = $conn->prepare('SELECT name, profile_image FROM users WHERE id = ?');
    $_viewerStmt->bind_param('i', $viewerId);
    $_viewerStmt->execute();
    $_viewerRow = $_viewerStmt->get_result()->fetch_assoc();
    $_viewerStmt->close();
    $user = ['name' => $_viewerRow['name'] ?? 'User', 'profile_image' => $_viewerRow['profile_image'] ?? null];
} else {
    $user = ['name' => 'Guest', 'profile_image' => null];
}

// Use client topbar for client viewers, freelancer header for guests/freelancers
if ($viewerRole === 'client') {
    require_once __DIR__ . '/../includes/client_topbar.php';
} else {
    require_once __DIR__ . '/../components/freelancer_header.php';
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
        <a href="/jobhub/client/dashboard.php" class="back-link">
    <?php else: ?>
        <a href="/jobhub/index.php" class="back-link">
    <?php endif; ?>
        <i data-lucide="chevron-left" class="w-4 h-4"></i> Back to Home
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
                    <div class="w-5 h-5 rounded bg-indigo-500 flex items-center justify-center">
                        <i data-lucide="briefcase" class="w-3 h-3 text-white"></i>
                    </div>
                    <span class="text-sm font-medium text-indigo-600">JobHub Freelancer</span>
                </div>
                <div class="flex items-center gap-3 justify-center lg:justify-start">
                    <?php if ($isOwner): ?>
                        <a href="profile_edit.php" class="inline-flex items-center gap-2 px-6 py-2.5 bg-emerald-500 hover:bg-emerald-600 text-white text-sm font-semibold rounded-lg transition-all shadow-sm">
                            <i data-lucide="pencil" class="w-3 h-3"></i> Edit Profile
                        </a>
                        <?php if (!empty($profile['resume_file'])): ?>
                            <a href="../assets/upload/resumes/<?= sanitize_string(basename($profile['resume_file'])) ?>" target="_blank" class="inline-flex items-center gap-2 px-6 py-2.5 border-2 border-gray-200 hover:border-indigo-300 text-gray-600 hover:text-indigo-600 text-sm font-semibold rounded-lg transition-all">
                                <i data-lucide="file-text" class="w-3 h-3"></i> Resume
                            </a>
                        <?php endif; ?>
                    <?php elseif (is_logged_in() && $viewerRole === 'client'): ?>
                        <a href="../client/messages.php?freelancer=<?= $viewUserId ?>" class="inline-flex items-center gap-2 px-6 py-2.5 bg-emerald-500 hover:bg-emerald-600 text-white text-sm font-semibold rounded-lg transition-all shadow-sm">
                            <i data-lucide="send" class="w-3 h-3"></i> Contact
                        </a>
                        <?php if (!empty($profile['resume_file'])): ?>
                            <a href="../assets/upload/resumes/<?= sanitize_string(basename($profile['resume_file'])) ?>" target="_blank" class="inline-flex items-center gap-2 px-6 py-2.5 border-2 border-gray-200 hover:border-indigo-300 text-gray-600 hover:text-indigo-600 text-sm font-semibold rounded-lg transition-all">
                                <i data-lucide="file-text" class="w-3 h-3"></i> Resume
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
                            <i data-lucide="star" class="w-3 h-3 text-amber-400"></i>
                            <?= $avgRating > 0 ? number_format($avgRating, 1) : '—' ?>
                            <span class="text-gray-400 font-normal">(<?= $reviewCount ?>)</span>
                        </span>
                    </div>
                </div>
                <!-- Social Icons -->
                <!-- <div class="flex items-center gap-2 mt-5 justify-center lg:justify-start">
                    <?php if (!empty($profile['portfolio_url'])): ?>
                        <a href="<?= sanitize_string($profile['portfolio_url']) ?>" target="_blank" rel="noopener" class="social-icon" title="Portfolio"><i data-lucide="globe" class="w-4 h-4"></i></a>
                    <?php endif; ?>
                    <a href="#" class="social-icon github" title="GitHub"><svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M15 22v-4a4.8 4.8 0 0 0-1-3.5c3 0 6-2 6-5.5.08-1.25-.27-2.48-1-3.5.28-1.15.28-2.35 0-3.5 0 0-1 0-3 1.5-2.64-.5-5.36-.5-8 0C6 2 5 2 5 2c-.3 1.15-.3 2.35 0 3.5A5.403 5.403 0 0 0 4 9c0 3.5 3 5.5 6 5.5-.39.49-.68 1.05-.85 1.65-.17.6-.22 1.23-.15 1.85v4"/><path d="M9 18c-4.51 2-5-2-7-2"/></svg></a>
                    <a href="#" class="social-icon facebook" title="Facebook"><svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M18 2h-3a5 5 0 0 0-5 5v3H7v4h3v8h4v-8h3l1-4h-4V7a1 1 0 0 1 1-1h3z"/></svg></a>
                    <a href="#" class="social-icon linkedin" title="LinkedIn"><svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M16 8a6 6 0 0 1 6 6v7h-4v-7a2 2 0 0 0-2-2 2 2 0 0 0-2 2v7h-4v-7a6 6 0 0 1 6-6z"/><rect width="4" height="12" x="2" y="9"/><circle cx="4" cy="4" r="2"/></svg></a>
                    <?php if (!empty($profile['portfolio_url'])): ?>
                        <a href="<?= sanitize_string($profile['portfolio_url']) ?>" target="_blank" rel="noopener" class="social-icon" title="Website"><i data-lucide="link" class="w-4 h-4"></i></a>
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
                                <a href="reviews.php" class="text-xs text-indigo-600 hover:text-indigo-700 font-medium transition-colors">
                                    View All +<?= $reviewCount - 5 ?> more <i data-lucide="arrow-right" class="w-3 h-3 ml-1"></i>
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
                                                        <i data-lucide="star" class="w-3 h-3 <?= $i <= $review['rating'] ? 'text-amber-400' : 'text-gray-200' ?>"></i>
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
            <div class="w-60 space-y-5 flex-shrink-0 fade-in">
                <!-- Websites Card -->
                <div class="profile-sidebar-card">
                    <h4 class="profile-section-title">Websites</h4>
                    <?php if (!empty($socialLinks['website'])): ?>
                        <a href="<?= sanitize_string($socialLinks['website']) ?>" target="_blank" rel="noopener" class="website-link">
                            <i data-lucide="globe" class="w-5 h-5"></i> Website
                        </a>
                    <?php endif; ?>
                    <?php if (!empty($socialLinks['github'])): ?>
                        <a href="<?= sanitize_string($socialLinks['github']) ?>" target="_blank" rel="noopener" class="website-link">
                            <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="lucide"><path d="M15 22v-4a4.8 4.8 0 0 0-1-3.5c3 0 6-2 6-5.5.08-1.25-.27-2.48-1-3.5.28-1.15.28-2.35 0-3.5 0 0-1 0-3 1.5-2.64-.5-5.36-.5-8 0C6 2 5 2 5 2c-.3 1.15-.3 2.35 0 3.5A5.403 5.403 0 0 0 4 9c0 3.5 3 5.5 6 5.5-.39.49-.68 1.05-.85 1.65-.17.6-.22 1.23-.15 1.85v4"/><path d="M9 18c-4.51 2-5-2-7-2"/></svg> GitHub
                        </a>
                    <?php endif; ?>
                    <?php if (!empty($socialLinks['linkedin'])): ?>
                        <a href="<?= sanitize_string($socialLinks['linkedin']) ?>" target="_blank" rel="noopener" class="website-link">
                            <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="lucide"><path d="M16 8a6 6 0 0 1 6 6v7h-4v-7a2 2 0 0 0-2-2 2 2 0 0 0-2 2v7h-4v-7a6 6 0 0 1 6-6z"/><rect width="4" height="12" x="2" y="9"/><circle cx="4" cy="4" r="2"/></svg> LinkedIn
                        </a>
                    <?php endif; ?>
                </div>

                <!-- Direct Recruitment Card -->
                <div class="profile-sidebar-card">
                    <div class="w-10 h-10 rounded-xl bg-amber-50 flex items-center justify-center text-amber-500 mb-3">
                        <i data-lucide="mail-open" class="w-5 h-5"></i>
                    </div>
                    <h3 class="text-sm font-bold text-gray-900 mb-1">Direct Recruitment</h3>
                <p class="text-xs text-gray-500 mb-4 leading-relaxed">
                    Have an active job opening that matches this freelancer's skillset? Submit a proposal directly to their dashboard.
                </p>

                <?php if (!($isOwner ?? false)): ?>
                    <a href="/jobhub/client/invite_jobs.php?freelancer_id=<?= $profile['freelancer_id'] ?? 0 ?>"
                        class="w-full inline-flex items-center justify-center gap-2 px-4 py-2.5 bg-gray-900 hover:bg-gray-800 text-white text-xs font-bold rounded-xl transition-all shadow-sm">
                        <i data-lucide="send" class="w-3 h-3"></i> Send Job Offer
                    </a>
                <?php else: ?>
                    <div class="w-full text-center py-2 bg-gray-50 text-gray-400 text-[11px] font-medium rounded-xl border border-dashed border-gray-200">
                        Clients see your hiring form here
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
    </div>

    <!-- ═══════════════ RESUME TAB CONTENT ═══════════════ -->
    <div id="ptab-panel-resume" class="profile-panel hidden">
        <div class="profile-sidebar-card max-w-2xl">
            <?php if (!empty($profile['resume_file'])): ?>
                <div class="flex items-center gap-4 p-5 bg-gray-50 rounded-xl border border-gray-200">
                    <div class="w-14 h-14 rounded-xl bg-red-100 flex items-center justify-center flex-shrink-0">
                        <i data-lucide="file-text" class="w-8 h-8 text-red-500"></i>
                    </div>
                    <div class="flex-1 min-w-0">
                        <p class="text-sm font-bold text-gray-900 truncate"><?= sanitize_string(basename($profile['resume_file'])) ?></p>
                        <p class="text-xs text-gray-400 mt-0.5">PDF Resume</p>
                    </div>
                    <a href="../assets/upload/resumes/<?= sanitize_string(basename($profile['resume_file'])) ?>"
                        target="_blank" class="inline-flex items-center gap-2 px-5 py-2.5 bg-indigo-600 hover:bg-indigo-700 text-white text-sm font-semibold rounded-lg transition-all flex-shrink-0">
                        <i data-lucide="download" class="w-4 h-4"></i> Download
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
                        <i data-lucide="file-text" class="w-12 h-12 text-gray-300"></i>
                    </div>
                    <h4 class="text-lg font-bold text-gray-900 mb-2">No Resume Uploaded</h4>
                    <p class="text-sm text-gray-400 mb-5">Upload your resume to showcase your qualifications to clients.</p>
                    <?php if ($isOwner): ?>
                        <a href="profile_edit.php" class="inline-flex items-center gap-2 px-6 py-2.5 btn-grad text-white font-semibold rounded-lg text-sm">
                            <i data-lucide="upload" class="w-4 h-4"></i> Upload Resume
                        </a>
                    <?php endif; ?>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- ═══════════════ PROFILE COMPLETION BANNER ═══════════════ -->
    <?php if ($isOwner && $completionPct < 100): ?>
        <div class="mt-8 bg-gradient-to-r from-indigo-50 to-indigo-50 rounded-2xl p-6 border border-indigo-100 fade-in">
            <div class="flex items-center gap-4">
                <div class="w-12 h-12 rounded-xl bg-indigo-100 flex items-center justify-center flex-shrink-0">
                    <i data-lucide="sparkles" class="w-6 h-6 text-indigo-500"></i>
                </div>
                <div class="flex-1">
                    <h4 class="text-sm font-bold text-gray-900 mb-1">Complete Your Profile (<?= $completionPct ?>%)</h4>
                    <p class="text-xs text-gray-500">A complete profile gets 3x more job invitations from clients.</p>
                </div>
                <a href="profile_edit.php" class="inline-flex items-center gap-2 px-5 py-2.5 btn-grad text-white text-sm font-semibold rounded-xl shadow-lg shadow-indigo-500/25 flex-shrink-0">
                    <i data-lucide="arrow-right" class="w-4 h-4"></i> Complete
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
<script>lucide.createIcons();</script>

<?php $conn->close(); ?>
<?php require_once __DIR__ . '/../components/freelancer_footer.php'; ?>