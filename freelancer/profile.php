<?php
$page_title = 'Freelancer Profile';
require_once '../config/helpers.php';
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
$stmtJobs->bind_param('i', $freelancerId);
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

$availabilityColors = [
    'Available' => 'bg-emerald-50 text-emerald-600 border border-emerald-200',
    'Busy' => 'bg-amber-50 text-amber-600 border border-amber-200',
    'Unavailable' => 'bg-red-50 text-red-500 border border-red-200',
];

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

$navItems = [
    ['key' => 'dashboard', 'label' => 'Dashboard', 'url' => 'dashboard.php', 'icon' => 'fa-th-large'],
    ['key' => 'profile', 'label' => 'Profile', 'url' => 'profile.php', 'icon' => 'fa-user'],
    ['key' => 'browse_jobs', 'label' => 'Browse Jobs', 'url' => 'browse_jobs.php', 'icon' => 'fa-search'],
    ['key' => 'proposals', 'label' => 'Proposals', 'url' => 'proposals.php', 'icon' => 'fa-file-alt'],
    ['key' => 'contracts', 'label' => 'Contracts', 'url' => 'contracts.php', 'icon' => 'fa-handshake'],
    ['key' => 'messages', 'label' => 'Messages', 'url' => 'messages.php', 'icon' => 'fa-comment-dots'],
    ['key' => 'earnings', 'label' => 'Earnings', 'url' => 'earnings.php', 'icon' => 'fa-wallet'],
];
$pageTitle = 'Freelancer Profile';
$pageSubtitle = 'Public professional profile';
$activePage = 'profile';
$user = ['name' => $profile['name'], 'profile_image' => $profile['profile_image']];
$unreadCount = get_unread_message_count($viewerId, 'freelancer');
$profileLink = 'profile.php';
require_once __DIR__ . '/../components/layout_start.php';
?>

                <?php if ($isOwner): ?>
                <div class="flex justify-end">
                    <a href="profile_edit.php" class="btn-grad inline-flex items-center gap-2 text-white text-sm font-semibold px-5 py-2.5 rounded-xl shadow-lg shadow-blue-500/25">
                        <i class="fas fa-edit text-xs"></i> Edit Profile
                    </a>
                </div>
                <?php elseif (is_logged_in() && $viewerRole === 'client'): ?>
                <div class="flex justify-end">
                    <a href="../client/messages.php?freelancer=<?= $viewUserId ?>" class="btn-grad inline-flex items-center gap-2 text-white text-sm font-semibold px-5 py-2.5 rounded-xl shadow-lg shadow-blue-500/25">
                        <i class="fas fa-paper-plane text-xs"></i> Contact
                    </a>
                </div>
                <?php endif; ?>

                <?php display_flash('success'); ?>
                <?php display_flash('error'); ?>

                <div class="bg-white rounded-2xl border border-gray-100 shadow-sm overflow-hidden fade-in">
                    <div class="h-32 bg-gradient-to-r from-blue-600 via-cyan-500 to-indigo-500 relative">
                        <div class="absolute inset-0 bg-[url('data:image/svg+xml;base64,PHN2ZyB3aWR0aD0iNjAiIGhlaWdodD0iNjAiIHZpZXdCb3g9IjAgMCA2MCA2MCIgeG1sbnM9Imh0dHA6Ly93d3cudzMub3JnLzIwMDAvc3ZnIj48ZyBmaWxsPSJub25lIiBmaWxsLXJ1bGU9ImV2ZW5vZGQiPjxnIGZpbGw9IiNmZmYiIGZpbGwtb3BhY2l0eT0iLjA1Ij48cGF0aCBkPSJNMzYgMzRoMnYyaC0yem0wLTRoMnYyaC0yem0tNCA0aDJ2MmgtMnptMC00aDJ2MmgtMnoiLz48L2c+PC9nPjwvc3ZnPg==')] opacity-40"></div>
                    </div>
                    <div class="px-8 pb-8 -mt-16 relative">
                        <div class="flex flex-col sm:flex-row items-start gap-6">
                            <img src="<?= get_profile_image($profile['profile_image']) . '?v=' . time() ?>"
                                alt="<?= sanitize_string($profile['name']) ?>"
                                class="w-32 h-32 rounded-full object-cover border-4 border-white shadow-lg">


                            <div class="flex-1 pt-2">
                                <div class="flex flex-wrap items-center gap-3 mb-2">
                                    <h2 class="text-2xl font-extrabold text-gray-900"><?= sanitize_string($profile['name']) ?></h2>
                                    <span class="inline-flex items-center gap-1.5 px-3 py-1 rounded-lg text-xs font-semibold <?= $availabilityColors[$profile['availability']] ?? 'bg-gray-100 text-gray-500 border border-gray-200' ?>">
                                        <span class="w-1.5 h-1.5 rounded-full <?= $profile['availability'] === 'Available' ? 'bg-emerald-500' : ($profile['availability'] === 'Busy' ? 'bg-amber-500' : 'bg-red-500') ?>"></span>
                                        <?= sanitize_string($profile['availability']) ?>
                                    </span>
                                </div>
                                <?php if (!empty($profile['title'])): ?>
                                    <p class="text-gray-600 font-medium mb-3"><?= sanitize_string($profile['title']) ?></p>
                                <?php endif; ?>
                                <div class="flex flex-wrap items-center gap-4 text-sm text-gray-400">
                                    <?php if ($profile['years_of_experience'] > 0): ?>
                                        <span class="flex items-center gap-1.5">
                                            <i class="fas fa-briefcase text-blue-400"></i>
                                            <?= $profile['years_of_experience'] ?> year<?= $profile['years_of_experience'] != 1 ? 's' : '' ?> experience
                                        </span>
                                    <?php endif; ?>
                                    <span class="flex items-center gap-1.5">
                                        <i class="fas fa-calendar text-gray-400"></i>
                                        Member since <?= date('M Y', strtotime($profile['member_since'])) ?>
                                    </span>
                                    <?php if (!empty($profile['portfolio_url'])): ?>
                                        <a href="<?= sanitize_string($profile['portfolio_url']) ?>" target="_blank" rel="noopener noreferrer"
                                            class="flex items-center gap-1.5 text-blue-500 hover:text-blue-700 transition-colors">
                                            <i class="fas fa-globe"></i> Portfolio
                                            <i class="fas fa-external-link-alt text-[9px]"></i>
                                        </a>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4 fade-in" style="animation-delay:.1s">
                    <div class="stat-card bg-white rounded-2xl p-5 border border-gray-100 shadow-sm">
                        <div class="flex items-center justify-between mb-3">
                            <div class="w-11 h-11 rounded-xl bg-emerald-50 flex items-center justify-center">
                                <i class="fas fa-dollar-sign text-emerald-500"></i>
                            </div>
                            <span class="text-[10px] font-semibold text-emerald-500 uppercase tracking-wider">Rate</span>
                        </div>
                        <p class="text-2xl font-black text-gray-900"><?= format_currency($profile['hourly_rate'] ?? 0) ?></p>
                        <p class="text-xs text-gray-400 mt-1">Per Hour</p>
                    </div>

                    <div class="stat-card bg-white rounded-2xl p-5 border border-gray-100 shadow-sm" style="animation-delay:.15s">
                        <div class="flex items-center justify-between mb-3">
                            <div class="w-11 h-11 rounded-xl bg-blue-50 flex items-center justify-center">
                                <i class="fas fa-check-double text-blue-500"></i>
                            </div>
                            <span class="text-[10px] font-semibold text-blue-500 uppercase tracking-wider">Done</span>
                        </div>
                        <p class="text-2xl font-black text-gray-900"><?= $completedJobs ?></p>
                        <p class="text-xs text-gray-400 mt-1">Completed Jobs</p>
                    </div>

                    <div class="stat-card bg-white rounded-2xl p-5 border border-gray-100 shadow-sm" style="animation-delay:.2s">
                        <div class="flex items-center justify-between mb-3">
                            <div class="w-11 h-11 rounded-xl bg-amber-50 flex items-center justify-center">
                                <i class="fas fa-star text-amber-500"></i>
                            </div>
                            <span class="text-[10px] font-semibold text-amber-500 uppercase tracking-wider">Rating</span>
                        </div>
                        <p class="text-2xl font-black text-gray-900"><?= $avgRating > 0 ? number_format($avgRating, 1) : '—' ?></p>
                        <p class="text-xs text-gray-400 mt-1"><?= $reviewCount ?> review<?= $reviewCount != 1 ? 's' : '' ?></p>
                    </div>

                    <div class="stat-card bg-white rounded-2xl p-5 border border-gray-100 shadow-sm" style="animation-delay:.25s">
                        <div class="flex items-center justify-between mb-3">
                            <div class="w-11 h-11 rounded-xl bg-violet-50 flex items-center justify-center">
                                <i class="fas fa-wallet text-violet-500"></i>
                            </div>
                            <span class="text-[10px] font-semibold text-violet-500 uppercase tracking-wider">Total</span>
                        </div>
                        <p class="text-2xl font-black text-gray-900"><?= format_currency($totalEarnings) ?></p>
                        <p class="text-xs text-gray-400 mt-1">Total Earnings</p>
                    </div>
                </div>

                <?php if (!empty($profile['bio'])): ?>
                    <div class="bg-white rounded-2xl p-6 border border-gray-100 shadow-sm fade-in" style="animation-delay:.3s">
                        <h3 class="text-base font-bold text-gray-900 mb-4 flex items-center gap-2">
                            <div class="w-8 h-8 rounded-lg bg-blue-50 flex items-center justify-center">
                                <i class="fas fa-user text-blue-500 text-sm"></i>
                            </div>
                            About
                        </h3>
                        <p class="text-sm text-gray-600 leading-relaxed whitespace-pre-line"><?= nl2br(sanitize_string($profile['bio'])) ?></p>
                    </div>
                <?php endif; ?>

                <?php if (!empty($skills)): ?>
                    <div class="bg-white rounded-2xl p-6 border border-gray-100 shadow-sm fade-in" style="animation-delay:.35s">
                        <h3 class="text-base font-bold text-gray-900 mb-4 flex items-center gap-2">
                            <div class="w-8 h-8 rounded-lg bg-violet-50 flex items-center justify-center">
                                <i class="fas fa-tags text-violet-500 text-sm"></i>
                            </div>
                            Skills
                        </h3>
                        <?php
                        $grouped = [];
                        foreach ($skills as $s) {
                            $grouped[$s['category']][] = $s;
                        }
                        foreach ($grouped as $category => $catSkills):
                            ?>
                            <div class="mb-4 last:mb-0">
                                <p class="text-xs font-semibold text-gray-400 uppercase tracking-wider mb-2"><?= sanitize_string($category) ?></p>
                                <div class="flex flex-wrap gap-2">
                                    <?php foreach ($catSkills as $s): ?>
                                        <span class="inline-flex items-center px-3 py-1.5 bg-blue-50 text-blue-600 text-xs font-medium rounded-lg border border-blue-100 hover:bg-blue-100 transition-colors">
                                            <?= sanitize_string($s['skill_name']) ?>
                                        </span>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>

                <?php if (!empty($reviews) || $reviewCount > 0): ?>
                    <div class="bg-white rounded-2xl p-6 border border-gray-100 shadow-sm fade-in" style="animation-delay:.4s">
                        <div class="flex items-center justify-between mb-5">
                            <h3 class="text-base font-bold text-gray-900 flex items-center gap-2">
                                <div class="w-8 h-8 rounded-lg bg-amber-50 flex items-center justify-center">
                                    <i class="fas fa-star text-amber-500 text-sm"></i>
                                </div>
                                Reviews
                                <?php if ($reviewCount > 0): ?>
                                    <span class="text-xs font-normal text-gray-400 ml-1">
                                        (<?= $avgRating ?> average &middot; <?= $reviewCount ?> review<?= $reviewCount !== 1 ? 's' : '' ?>)
                                    </span>
                                <?php endif; ?>
                            </h3>
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
                                                        <i class="fas fa-star text-xs <?= $i <= $review['rating'] ? 'star-filled' : 'star-empty' ?>"></i>
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
                            <div class="text-center py-6">
                                <p class="text-sm text-gray-400">No reviews yet.</p>
                            </div>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>

                <?php if ($isOwner && $completionPct < 100): ?>
                    <div class="bg-gradient-to-r from-blue-50 to-cyan-50 rounded-2xl p-6 border border-blue-100 fade-in" style="animation-delay:.45s">
                        <div class="flex items-center gap-4">
                            <div class="w-12 h-12 rounded-xl bg-blue-100 flex items-center justify-center flex-shrink-0">
                                <i class="fas fa-magic text-blue-500 text-lg"></i>
                            </div>
                            <div class="flex-1">
                                <h4 class="text-sm font-bold text-gray-900 mb-1">Complete Your Profile (<?= $completionPct ?>%)</h4>
                                <p class="text-xs text-gray-500">A complete profile gets 3x more job invitations from clients.</p>
                            </div>
                            <a href="profile_edit.php" class="btn-grad inline-flex items-center gap-2 text-white text-sm font-semibold px-5 py-2.5 rounded-xl shadow-lg shadow-blue-500/25 flex-shrink-0">
                                <i class="fas fa-arrow-right text-xs"></i> Complete
                            </a>
                        </div>
                    </div>
                <?php endif; ?>
<?php $conn->close(); ?>
<?php require_once __DIR__ . '/../components/layout_end.php'; ?>