<?php
$page_title = 'Profile Settings';
require_once '../auth/auth.php';
require_role('freelancer');
require_once '../config/db.php';

$userId = $_SESSION['user_id'];

$stmt = $conn->prepare('
    SELECT u.id AS user_id, u.name, u.email, u.phone, u.profile_image, u.created_at AS member_since,
           f.id AS freelancer_id, f.title, f.bio, f.hourly_rate, f.portfolio_url,
           f.resume_file, f.years_of_experience, f.availability, f.skills_vector
    FROM users u
    JOIN freelancers f ON u.id = f.user_id
    WHERE u.id = ?
');
$stmt->bind_param('i', $userId);
$stmt->execute();
$profile = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$profile) {
    set_flash('error', 'Profile not found.');
    redirect('dashboard.php');
}

$freelancerId = $profile['freelancer_id'];

$stmtSkills = $conn->prepare('SELECT skill_id FROM freelancer_skills WHERE freelancer_id = ?');
$stmtSkills->bind_param('i', $freelancerId);
$stmtSkills->execute();
$skillResult = $stmtSkills->get_result();
$selectedSkills = [];
while ($row = $skillResult->fetch_assoc()) {
    $selectedSkills[] = (int) $row['skill_id'];
}
$stmtSkills->close();

$stmtAllSkills = $conn->query('SELECT id, skill_name, category FROM skills ORDER BY category, skill_name');
$allSkills = [];
while ($row = $stmtAllSkills->fetch_assoc()) {
    $allSkills[$row['category']][] = $row;
}

// Fetch stats
$stmtJobs = $conn->prepare("SELECT COUNT(*) AS total FROM contracts WHERE freelancer_id = ? AND status = 'completed'");
$stmtJobs->bind_param('i', $freelancerId);
$stmtJobs->execute();
$completedJobs = $stmtJobs->get_result()->fetch_assoc()['total'];
$stmtJobs->close();

$stmtEarnings = $conn->prepare("SELECT COALESCE(SUM(total_amount), 0) AS total FROM payments WHERE payee_id = ? AND status = 'completed'");
$stmtEarnings->bind_param('i', $userId);
$stmtEarnings->execute();
$totalEarnings = $stmtEarnings->get_result()->fetch_assoc()['total'];
$stmtEarnings->close();

$totalHours = 0;

$stmtRating = $conn->prepare('SELECT COALESCE(AVG(rating), 0) AS avg_rating, COUNT(*) AS review_count FROM reviews WHERE reviewee_id = ?');
$stmtRating->bind_param('i', $userId);
$stmtRating->execute();
$ratingData = $stmtRating->get_result()->fetch_assoc();
$avgRating = round($ratingData['avg_rating'], 1);
$reviewCount = $ratingData['review_count'];
$stmtRating->close();

// Fetch user skill names for sidebar
$stmtUserSkills = $conn->prepare('
    SELECT s.skill_name
    FROM freelancer_skills fs
    JOIN skills s ON fs.skill_id = s.id
    WHERE fs.freelancer_id = ?
    ORDER BY s.skill_name
');
$stmtUserSkills->bind_param('i', $freelancerId);
$stmtUserSkills->execute();
$userSkillsResult = $stmtUserSkills->get_result();
$userSkillNames = [];
while ($row = $userSkillsResult->fetch_assoc()) {
    $userSkillNames[] = $row['skill_name'];
}
$stmtUserSkills->close();

$availabilityOptions = ['Available', 'Busy', 'Unavailable'];

$pageTitle = 'Profile Settings';
$pageSubtitle = 'Update your professional information';
$activePage = 'profile';
$user = ['name' => $profile['name'], 'profile_image' => $profile['profile_image']];
$unreadCount = get_unread_message_count($userId, 'freelancer');
require_once __DIR__ . '/../components/freelancer_header.php';
?>

<style>
    .pe-header-card {
        background: #fff;
        border: 1px solid #e5edf6;
        border-radius: 16px;
        box-shadow: 0 2px 12px rgba(15, 23, 42, .04);
        padding: 28px 32px;
    }

    .pe-avatar-wrap {
        position: relative;
        width: 96px;
        height: 96px;
        flex-shrink: 0;
    }

    .pe-avatar-img {
        width: 96px;
        height: 96px;
        border-radius: 50%;
        object-fit: cover;
        border: 3px solid #fff;
        box-shadow: 0 2px 12px rgba(0, 0, 0, .08);
    }

    .pe-avatar-edit {
        position: absolute;
        bottom: 2px;
        right: 2px;
        width: 28px;
        height: 28px;
        border-radius: 50%;
        background: #fff;
        border: 2px solid #e2e8f0;
        display: flex;
        align-items: center;
        justify-content: center;
        cursor: pointer;
        transition: all .15s;
        box-shadow: 0 1px 4px rgba(0, 0, 0, .1);
    }

    .pe-avatar-edit:hover {
        border-color: #2563eb;
        background: #eff6ff;
    }

    .pe-verified-badge {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        width: 20px;
        height: 20px;
        border-radius: 50%;
        background: #2563eb;
        color: #fff;
        font-size: 10px;
        margin-left: 4px;
        flex-shrink: 0;
    }

    .pe-available-pill {
        display: inline-flex;
        align-items: center;
        gap: 6px;
        padding: 4px 14px;
        background: #ecfdf5;
        border: 1px solid #a7f3d0;
        border-radius: 20px;
        font-size: 12px;
        font-weight: 600;
        color: #059669;
    }

    .pe-available-dot {
        width: 7px;
        height: 7px;
        border-radius: 50%;
        background: #059669;
    }

    .pe-success-bar {
        height: 6px;
        border-radius: 3px;
        background: #e2e8f0;
        overflow: hidden;
    }

    .pe-success-fill {
        height: 100%;
        border-radius: 3px;
        background: linear-gradient(90deg, #059669, #10b981);
    }

    .pe-expert-badge {
        display: inline-flex;
        align-items: center;
        gap: 6px;
        padding: 4px 12px;
        background: #fffbeb;
        border: 1px solid #fde68a;
        border-radius: 20px;
        font-size: 11px;
        font-weight: 700;
        color: #b45309;
        letter-spacing: 0.04em;
    }

    .pe-btn-outline {
        display: inline-flex;
        align-items: center;
        gap: 8px;
        padding: 10px 22px;
        border: 2px solid #2563eb;
        border-radius: 10px;
        font-size: 13px;
        font-weight: 700;
        color: #2563eb;
        background: #fff;
        cursor: pointer;
        transition: all .15s;
        text-decoration: none;
    }

    .pe-btn-outline:hover {
        background: #eff6ff;
        border-color: #1d4ed8;
    }

    .pe-btn-green {
        display: inline-flex;
        align-items: center;
        gap: 8px;
        padding: 10px 22px;
        border: none;
        border-radius: 10px;
        font-size: 13px;
        font-weight: 700;
        color: #fff;
        background: #059669;
        cursor: pointer;
        transition: all .15s;
        text-decoration: none;
    }

    .pe-btn-green:hover {
        background: #047857;
    }

    .pe-sidebar-card {
        background: #fff;
        border: 1px solid #e5edf6;
        border-radius: 16px;
        box-shadow: 0 2px 12px rgba(15, 23, 42, .04);
        padding: 24px;
    }

    .pe-section-label {
        font-size: 12px;
        font-weight: 700;
        color: #64748b;
        text-transform: uppercase;
        letter-spacing: 0.05em;
        margin-bottom: 12px;
    }

    .pe-edit-inline {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        width: 28px;
        height: 28px;
        border-radius: 50%;
        border: 1px solid #e2e8f0;
        background: #fff;
        color: #94a3b8;
        cursor: pointer;
        transition: all .15s;
        flex-shrink: 0;
        font-size: 11px;
    }

    .pe-edit-inline:hover {
        border-color: #2563eb;
        color: #2563eb;
        background: #eff6ff;
    }

    .pe-stat-box {
        text-align: center;
        padding: 12px 0;
        border-bottom: 1px solid #f1f5f9;
    }

    .pe-stat-box:last-child {
        border-bottom: none;
    }

    .pe-stat-val {
        font-size: 20px;
        font-weight: 800;
        color: #0f172a;
        line-height: 1.2;
    }

    .pe-stat-label {
        font-size: 11px;
        color: #94a3b8;
        font-weight: 500;
        margin-top: 2px;
    }

    .pe-nav-item {
        display: flex;
        align-items: center;
        gap: 10px;
        padding: 10px 14px;
        border-radius: 10px;
        font-size: 13px;
        font-weight: 500;
        color: #475569;
        cursor: pointer;
        transition: all .15s;
        text-decoration: none;
    }

    .pe-nav-item:hover {
        background: #f8fafc;
        color: #2563eb;
    }

    .pe-nav-item.active {
        background: #eff6ff;
        color: #2563eb;
        font-weight: 600;
    }

    .pe-nav-item i {
        width: 18px;
        text-align: center;
        font-size: 14px;
        color: #94a3b8;
    }

    .pe-nav-item.active i {
        color: #2563eb;
    }

    .pe-field-group {
        background: #fff;
        border: 1px solid #e5edf6;
        border-radius: 16px;
        box-shadow: 0 2px 12px rgba(15, 23, 42, .04);
        padding: 24px;
        margin-bottom: 16px;
    }

    .pe-field-row {
        display: flex;
        align-items: center;
        justify-content: space-between;
        padding: 10px 0;
        border-bottom: 1px solid #f1f5f9;
    }

    .pe-field-row:last-child {
        border-bottom: none;
    }

    .pe-field-title {
        font-size: 14px;
        font-weight: 700;
        color: #0f172a;
    }

    .pe-field-sub {
        font-size: 12px;
        color: #94a3b8;
        margin-top: 2px;
    }

    .pe-field-value {
        font-size: 14px;
        color: #334155;
        font-weight: 500;
    }

    .pe-bio-text {
        font-size: 13px;
        color: #475569;
        line-height: 1.7;
        white-space: pre-line;
    }

    .pe-modal-overlay {
        display: none;
        position: fixed;
        inset: 0;
        background: rgba(0, 0, 0, .4);
        z-index: 50;
        align-items: center;
        justify-content: center;
        backdrop-filter: blur(2px);
    }

    .pe-modal-overlay.active {
        display: flex;
    }

    .pe-modal-box {
        background: #fff;
        border-radius: 16px;
        box-shadow: 0 25px 60px rgba(0, 0, 0, .2);
        width: 100%;
        max-width: 520px;
        max-height: 90vh;
        overflow-y: auto;
        padding: 28px;
    }

    .pe-modal-title {
        font-size: 16px;
        font-weight: 700;
        color: #0f172a;
        margin-bottom: 20px;
    }

    .pe-input {
        width: 100%;
        padding: 10px 14px;
        border: 1px solid #e2e8f0;
        border-radius: 10px;
        font-size: 13px;
        color: #0f172a;
        background: #f8fafc;
        transition: all .15s;
        outline: none;
    }

    .pe-input:focus {
        border-color: #2563eb;
        background: #fff;
        box-shadow: 0 0 0 3px rgba(37, 99, 235, .1);
    }

    .pe-textarea {
        width: 100%;
        padding: 12px 14px;
        border: 1px solid #e2e8f0;
        border-radius: 10px;
        font-size: 13px;
        color: #0f172a;
        background: #f8fafc;
        transition: all .15s;
        outline: none;
        resize: vertical;
        min-height: 100px;
        line-height: 1.6;
    }

    .pe-textarea:focus {
        border-color: #2563eb;
        background: #fff;
        box-shadow: 0 0 0 3px rgba(37, 99, 235, .1);
    }

    .pe-btn-primary {
        display: inline-flex;
        align-items: center;
        gap: 8px;
        padding: 10px 24px;
        border: none;
        border-radius: 10px;
        font-size: 13px;
        font-weight: 700;
        color: #fff;
        background: linear-gradient(135deg, #2563eb 0%, #0ea5e9 100%);
        cursor: pointer;
        transition: all .15s;
        box-shadow: 0 2px 8px rgba(37, 99, 235, .25);
    }

    .pe-btn-primary:hover {
        box-shadow: 0 4px 16px rgba(37, 99, 235, .35);
        transform: translateY(-1px);
    }

    .pe-btn-ghost {
        display: inline-flex;
        align-items: center;
        gap: 8px;
        padding: 10px 24px;
        border: 1px solid #e2e8f0;
        border-radius: 10px;
        font-size: 13px;
        font-weight: 600;
        color: #64748b;
        background: #fff;
        cursor: pointer;
        transition: all .15s;
    }

    .pe-btn-ghost:hover {
        border-color: #cbd5e1;
        color: #334155;
    }

    .pe-skill-pill {
        display: inline-flex;
        align-items: center;
        gap: 4px;
        padding: 5px 12px;
        background: #f8fafc;
        border: 1px solid #e2e8f0;
        border-radius: 20px;
        font-size: 12px;
        font-weight: 500;
        color: #475569;
    }

    .pe-consult-card {
        background: linear-gradient(135deg, #eff6ff 0%, #ecfeff 100%);
        border: 1px solid #e0f2fe;
        border-radius: 16px;
        padding: 24px;
    }
</style>

<div class="max-w-7xl mx-auto px-4 sm:px-6 py-8">

    <?php display_flash('success'); ?>
    <?php display_flash('error'); ?>

    <!-- ═══════════════ HEADER CARD ═══════════════ -->
    <div class="pe-header-card mb-8 fade-in">
        <div class="flex flex-col sm:flex-row items-start gap-5">
            <!-- Avatar -->
            <div class="pe-avatar-wrap flex-shrink-0">
                <img id="avatarPreview"
                    src="<?= get_profile_image($profile['profile_image']) . '?v=' . time() ?>"
                    alt="<?= sanitize_string($profile['name']) ?>"
                    class="pe-avatar-img">
                <label for="profileImageInput" class="pe-avatar-edit" title="Change photo">
                    <i class="fas fa-pen text-[10px]"></i>
                </label>
                <input type="file" name="profile_image" id="profileImageInput"
                    accept="image/jpeg,image/png,image/gif,image/webp" class="hidden">
            </div>

            <!-- Name + Info -->
            <div class="flex-1 min-w-0">
                <div class="flex items-center gap-2 mb-1 flex-wrap">
                    <h1 class="text-xl font-extrabold text-gray-900"><?= sanitize_string($profile['name']) ?></h1>
                    <span class="pe-verified-badge" title="Verified"><i class="fas fa-check"></i></span>
                </div>
                <div class="flex items-center gap-2 text-sm text-gray-500 mb-3">
                    <i class="fas fa-map-marker-alt text-gray-400 text-xs"></i>
                    <span><?= date('g:i a') ?> local time</span>
                </div>
                <div class="flex items-center gap-3 flex-wrap mb-4">
                    <span class="pe-available-pill">
                        <span class="pe-available-dot"></span> Available now
                    </span>
                    <button onclick="openModal('availabilityModal')" class="pe-edit-inline" title="Edit availability">
                        <i class="fas fa-pen"></i>
                    </button>
                </div>
                <div class="flex items-center gap-4 flex-wrap">
                    <div class="flex-1 min-w-[120px] max-w-[180px]">
                        <div class="flex items-center justify-between mb-1">
                            <span class="text-xs font-bold text-gray-900">100%</span>
                        </div>
                        <div class="pe-success-bar">
                            <div class="pe-success-fill" style="width: 100%"></div>
                        </div>
                        <span class="text-[10px] text-gray-400 mt-0.5 block">Job Success</span>
                    </div>
                    <span class="pe-expert-badge">
                        <i class="fas fa-shield-alt text-[10px]"></i> EXPERT-VETTED
                    </span>
                </div>
            </div>

            <!-- Right Buttons -->
            <div class="flex items-center gap-3 flex-shrink-0">
                <a href="profile.php" class="pe-btn-outline">
                    <i class="fas fa-eye text-xs"></i> See Public View
                </a>
                <!-- <span class="pe-btn-green">
                    <i class="fas fa-cog text-xs"></i> Profile Settings
                </span> -->
            </div>
        </div>
    </div>

    <!-- ═══════════════ MAIN LAYOUT ═══════════════ -->
    <div class="flex flex-col lg:flex-row gap-6">

        <!-- LEFT SIDEBAR -->
        <div class="w-full lg:w-64 flex-shrink-0 space-y-6">
            <!-- View Profile -->
            <div class="pe-sidebar-card">
                <a href="profile.php" class="pe-nav-item active">
                    <i class="fas fa-user"></i> <?php echo sanitize_string($profile['name']) ?>
                    <button onclick="openModal('nameModal'); event.preventDefault(); event.stopPropagation();" class="pe-edit-inline ml-auto" title="Edit name">
                        <i class="fas fa-pen"></i>
                    </button>
                </a>
            </div>

            <!-- Profile Categories -->
            <div class="pe-sidebar-card">
                <div class="space-y-1">
                    <?php if (!empty($userSkillNames)): ?>
                        <?php foreach (array_slice($userSkillNames, 0, 3) as $skillName): ?>
                            <a href="browse_jobs.php?search=<?= urlencode($skillName) ?>" class="pe-nav-item">
                                <?= sanitize_string($skillName) ?>
                            </a>
                        <?php endforeach; ?>
                        <?php if (count($userSkillNames) > 3): ?>
                            <a href="browse_jobs.php" class="pe-nav-item">
                                All work <i class="fas fa-chevron-right ml-auto text-xs"></i>
                            </a>
                        <?php endif; ?>
                    <?php else: ?>
                        <a href="browse_jobs.php" class="pe-nav-item">
                            All work <i class="fas fa-chevron-right ml-auto text-xs"></i>
                        </a>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Stats -->
            <div class="pe-sidebar-card">
                <div class="pe-stat-box">
                    <div class="pe-stat-val"><?= format_currency($totalEarnings) ?></div>
                    <div class="pe-stat-label">Total Earnings</div>
                </div>
                <div class="pe-stat-box">
                    <div class="pe-stat-val"><?= number_format($completedJobs) ?></div>
                    <div class="pe-stat-label">Total Jobs</div>
                </div>
                <div class="pe-stat-box">
                    <div class="pe-stat-val"><?= number_format($totalHours) ?></div>
                    <div class="pe-stat-label">Total Hours</div>
                </div>
            </div>

            <!-- Video Introduction -->
            <!-- <div class="pe-sidebar-card">
                <div class="flex items-center justify-between mb-3">
                    <span class="pe-section-label mb-0">Video introduction</span>
                    <button class="pe-edit-inline" title="Add video"><i class="fas fa-plus text-[10px]"></i></button>
                </div>
                <p class="text-[11px] text-gray-400">Add a video to stand out.</p>
            </div> -->

            <!-- Phone -->
            <div class="pe-sidebar-card">
                <span class="pe-section-label">Phone</span>
                <div class="flex items-center gap-2 text-sm text-gray-600">
                    <i class="fa-solid fa-phone text-gray-400 text-xs"></i>
                    <span><?= sanitize_string($profile['phone'] ?? 'Not set') ?></span>
                </div>
            </div>
        </div>

        <!-- MAIN CONTENT -->
        <div class="flex-1 min-w-0 space-y-0">

            <!-- Title Row -->
            <div class="pe-field-group" style="border-radius: 16px 16px 0 0; margin-bottom: 0;">
                <div class="pe-field-row" style="border-bottom: none; padding: 0;">
                    <div class="flex-1">
                        <div class="pe-field-title text-lg"><?= sanitize_string($profile['title'] ?: 'Your Professional Title') ?></div>
                    </div>
                    <div class="flex items-center gap-2 flex-shrink-0">
                        <span class="text-lg font-bold text-gray-900"><?= format_currency($profile['hourly_rate'] ?? 0) ?>/hr</span>
                        <button onclick="openModal('titleModal')" class="pe-edit-inline" title="Edit title & rate">
                            <i class="fas fa-pen"></i>
                        </button>
                        <button onclick="openModal('linkModal')" class="pe-edit-inline" title="Portfolio link">
                            <i class="fas fa-link"></i>
                        </button>
                    </div>
                </div>
            </div>

            <!-- Bio / About -->
            <div class="pe-field-group" style="border-radius: 0 0 16px 16px; border-top: 1px solid #f1f5f9; margin-top: -1px;">
                <div class="flex items-start justify-between gap-3 mb-3">
                    <div class="flex-1">
                        <?php if (!empty($profile['bio'])): ?>
                            <p class="pe-bio-text"><?= nl2br(sanitize_string($profile['bio'])) ?></p>
                        <?php else: ?>
                            <p class="text-sm text-gray-400 italic">Add a bio to tell clients about your skills and experience.</p>
                        <?php endif; ?>
                    </div>
                    <button onclick="openModal('bioModal')" class="pe-edit-inline mt-0.5" title="Edit bio">
                        <i class="fas fa-pen"></i>
                    </button>
                </div>
            </div>

            <!-- Consultation Card -->
            <!-- <div class="pe-consult-card ">
                <div class="flex items-center justify-between">
                    <div>
                        <h3 class="text-base font-bold text-gray-900 mb-1">Consultation</h3>
                        <p class="text-sm text-gray-500 max-w-md">Meet more clients through one-on-one virtual consultations. Choose when you're available to meet and set your rate, then clients can come to you.</p>
                    </div>
                    <button class="pe-btn-outline flex-shrink-0" style="border-color: #059669; color: #059669;">
                        Set Up a Consultation
                    </button>
                </div>
            </div> -->
            <div class="bg-white rounded-2xl p-6 border border-gray-100 shadow-sm mb-6 fade-in">
                <div class="flex items-center gap-3 mb-4">
                    <div class="w-10 h-10 rounded-xl bg-blue-50 flex items-center justify-center text-blue-600 flex-shrink-0">
                        <i class="fas fa-paper-plane text-base"></i>
                    </div>
                    <div>
                        <h3 class="text-sm font-bold text-gray-900">Interested in working together?</h3>
                        <p class="text-xs text-gray-400 mt-0.5">Send a direct job invitation or project inquiry.</p>
                    </div>
                </div>

                <p class="text-xs text-gray-600 mb-5 leading-relaxed">
                    Invite this freelancer to bid on your active project listings. They will review your requirements and respond within 24 hours.
                </p>

                <?php
                // If the logged-in user's ID matches the profile ID, they own it!
                $isOwner = ($_SESSION['user_id'] == $profile['user_id']);
                if (!$isOwner):
                    ?>
                    <a href="/finalproject/client/invite_jobs.php?freelancer_id=<?= $profile['freelancer_id'] ?>"
                        class="w-full inline-flex items-center justify-center gap-2 px-4 py-3 btn-grad text-white text-xs font-bold rounded-xl shadow-lg shadow-blue-500/25 hover:opacity-95 transition-all">
                        <i class="fas fa-envelope text-[11px]"></i> Invite to Job
                    </a>
                <?php else: ?>
                    <div class="w-full text-center py-2 bg-gray-50 text-gray-400 text-xs font-medium rounded-xl border border-dashed border-gray-200">
                        Visible to clients visiting your profile
                    </div>
                <?php endif; ?>
            </div>

            <!-- Skills Section -->
            <?php if (!empty($skills)): ?>
                <div class="mt-6">
                    <div class="flex items-center justify-between mb-3">
                        <h3 class="pe-section-label mb-0">Skills</h3>
                        <button onclick="openModal('skillsModal')" class="pe-edit-inline" title="Edit skills">
                            <i class="fas fa-pen"></i>
                        </button>
                    </div>
                    <div class="flex flex-wrap gap-2">
                        <?php foreach ($skills as $s): ?>
                            <span class="pe-skill-pill"><?= sanitize_string($s['skill_name']) ?></span>
                        <?php endforeach; ?>
                    </div>
                </div>
            <?php endif; ?>

            <!-- Full Edit Form (hidden, submits to profile_update.php) -->
            <form action="profile_update.php" method="POST" enctype="multipart/form-data" id="profileForm" class="hidden">
                <?= csrf_field() ?>
                <input type="hidden" name="name" id="fieldName" value="<?= sanitize_string($profile['name']) ?>">
                <input type="hidden" name="title" id="fieldTitle" value="<?= sanitize_string($profile['title'] ?? '') ?>">
                <input type="hidden" name="hourly_rate" id="fieldRate" value="<?= sanitize_float($profile['hourly_rate'] ?? 0) ?>">
                <input type="hidden" name="bio" id="fieldBio" value="<?= sanitize_string($profile['bio'] ?? '') ?>">
                <input type="hidden" name="availability" id="fieldAvailability" value="<?= sanitize_string($profile['availability'] ?? 'Available') ?>">
                <input type="hidden" name="years_of_experience" id="fieldYears" value="<?= sanitize_int($profile['years_of_experience'] ?? 0) ?>">
                <input type="hidden" name="portfolio_url" id="fieldPortfolio" value="<?= sanitize_string($profile['portfolio_url'] ?? '') ?>">
                <input type="hidden" name="phone" id="fieldPhone" value="<?= sanitize_string($profile['phone'] ?? '') ?>">
                <?php foreach ($selectedSkills as $sid): ?>
                    <input type="hidden" name="skills[]" value="<?= $sid ?>" class="skill-hidden-input">
                <?php endforeach; ?>
            </form>

            <!-- Bottom Action -->
            <div class="flex items-center justify-between mt-8 pt-6 border-t border-gray-100">
                <a href="profile.php" class="pe-btn-ghost">
                    <i class="fas fa-arrow-left text-xs"></i> Back to Profile
                </a>
                <button onclick="document.getElementById('profileForm').submit();" class="pe-btn-primary">
                    <i class="fas fa-save text-xs"></i> Save All Changes
                </button>
            </div>
        </div>
    </div>
</div>

<!-- ═══════════════ MODALS ═══════════════ -->

<!-- Name Modal -->
<div id="nameModal" class="pe-modal-overlay" onclick="if(event.target===this)closeModal('nameModal')">
    <div class="pe-modal-box">
        <div class="flex items-center justify-between mb-5">
            <h3 class="pe-modal-title mb-0">Edit Name</h3>
            <button onclick="closeModal('nameModal')" class="w-8 h-8 rounded-lg hover:bg-gray-100 flex items-center justify-center text-gray-400"><i class="fas fa-times text-sm"></i></button>
        </div>
        <label class="block text-xs font-semibold text-gray-700 mb-1.5">Full Name</label>
        <input type="text" id="modalName" value="<?= sanitize_string($profile['name']) ?>" maxlength="100" class="pe-input mb-5">
        <div class="flex justify-end gap-3">
            <button onclick="closeModal('nameModal')" class="pe-btn-ghost">Cancel</button>
            <button onclick="saveName()" class="pe-btn-primary">Save</button>
        </div>
    </div>
</div>

<!-- Title & Rate Modal -->
<div id="titleModal" class="pe-modal-overlay" onclick="if(event.target===this)closeModal('titleModal')">
    <div class="pe-modal-box">
        <div class="flex items-center justify-between mb-5">
            <h3 class="pe-modal-title mb-0">Edit Title & Hourly Rate</h3>
            <button onclick="closeModal('titleModal')" class="w-8 h-8 rounded-lg hover:bg-gray-100 flex items-center justify-center text-gray-400"><i class="fas fa-times text-sm"></i></button>
        </div>
        <label class="block text-xs font-semibold text-gray-700 mb-1.5">Professional Title</label>
        <input type="text" id="modalTitle" value="<?= sanitize_string($profile['title'] ?? '') ?>" maxlength="100" class="pe-input mb-4">
        <label class="block text-xs font-semibold text-gray-700 mb-1.5">Hourly Rate ($)</label>
        <input type="number" id="modalRate" value="<?= sanitize_float($profile['hourly_rate'] ?? 0) ?>" step="0.01" min="0" max="99999.99" class="pe-input mb-4">
        <label class="block text-xs font-semibold text-gray-700 mb-1.5">Years of Experience</label>
        <input type="number" id="modalYears" value="<?= sanitize_int($profile['years_of_experience'] ?? 0) ?>" min="0" max="50" class="pe-input mb-5">
        <div class="flex justify-end gap-3">
            <button onclick="closeModal('titleModal')" class="pe-btn-ghost">Cancel</button>
            <button onclick="saveTitle()" class="pe-btn-primary">Save</button>
        </div>
    </div>
</div>

<!-- Bio Modal -->
<div id="bioModal" class="pe-modal-overlay" onclick="if(event.target===this)closeModal('bioModal')">
    <div class="pe-modal-box">
        <div class="flex items-center justify-between mb-5">
            <h3 class="pe-modal-title mb-0">Edit Bio</h3>
            <button onclick="closeModal('bioModal')" class="w-8 h-8 rounded-lg hover:bg-gray-100 flex items-center justify-center text-gray-400"><i class="fas fa-times text-sm"></i></button>
        </div>
        <label class="block text-xs font-semibold text-gray-700 mb-1.5">About You</label>
        <textarea id="modalBio" rows="6" maxlength="2000" class="pe-textarea mb-2"><?= sanitize_string($profile['bio'] ?? '') ?></textarea>
        <p class="text-[11px] text-gray-400 text-right mb-5"><span id="modalBioCount">0</span>/2000</p>
        <div class="flex justify-end gap-3">
            <button onclick="closeModal('bioModal')" class="pe-btn-ghost">Cancel</button>
            <button onclick="saveBio()" class="pe-btn-primary">Save</button>
        </div>
    </div>
</div>

<!-- Availability Modal -->
<div id="availabilityModal" class="pe-modal-overlay" onclick="if(event.target===this)closeModal('availabilityModal')">
    <div class="pe-modal-box">
        <div class="flex items-center justify-between mb-5">
            <h3 class="pe-modal-title mb-0">Edit Availability</h3>
            <button onclick="closeModal('availabilityModal')" class="w-8 h-8 rounded-lg hover:bg-gray-100 flex items-center justify-center text-gray-400"><i class="fas fa-times text-sm"></i></button>
        </div>
        <label class="block text-xs font-semibold text-gray-700 mb-1.5">Status</label>
        <select id="modalAvailability" class="pe-input mb-5">
            <?php foreach ($availabilityOptions as $opt): ?>
                <option value="<?= $opt ?>" <?= ($profile['availability'] ?? '') === $opt ? 'selected' : '' ?>><?= $opt ?></option>
            <?php endforeach; ?>
        </select>
        <div class="flex justify-end gap-3">
            <button onclick="closeModal('availabilityModal')" class="pe-btn-ghost">Cancel</button>
            <button onclick="saveAvailability()" class="pe-btn-primary">Save</button>
        </div>
    </div>
</div>

<!-- Skills Modal -->
<div id="skillsModal" class="pe-modal-overlay" onclick="if(event.target===this)closeModal('skillsModal')">
    <div class="pe-modal-box" style="max-width: 600px;">
        <div class="flex items-center justify-between mb-5">
            <h3 class="pe-modal-title mb-0">Edit Skills</h3>
            <button onclick="closeModal('skillsModal')" class="w-8 h-8 rounded-lg hover:bg-gray-100 flex items-center justify-center text-gray-400"><i class="fas fa-times text-sm"></i></button>
        </div>
        <div class="space-y-4 max-h-[50vh] overflow-y-auto mb-5">
            <?php foreach ($allSkills as $category => $catSkills): ?>
                <div>
                    <p class="text-xs font-semibold text-gray-400 uppercase tracking-wider mb-2"><?= sanitize_string($category) ?></p>
                    <div class="flex flex-wrap gap-2">
                        <?php foreach ($catSkills as $skill): ?>
                            <label class="modal-skill-tag pe-skill-pill cursor-pointer transition-all <?= in_array($skill['id'], $selectedSkills) ? 'selected' : '' ?>"
                                data-skill-id="<?= $skill['id'] ?>" style="<?= in_array($skill['id'], $selectedSkills) ? 'background:#eff6ff;border-color:#bfdbfe;color:#2563eb;' : '' ?>">
                                <input type="checkbox" name="modal_skills[]" value="<?= $skill['id'] ?>" class="hidden" <?= in_array($skill['id'], $selectedSkills) ? 'checked' : '' ?>>
                                <?= sanitize_string($skill['skill_name']) ?>
                            </label>
                        <?php endforeach; ?>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
        <div class="flex justify-end gap-3">
            <button onclick="closeModal('skillsModal')" class="pe-btn-ghost">Cancel</button>
            <button onclick="saveSkills()" class="pe-btn-primary">Save</button>
        </div>
    </div>
</div>

<!-- Portfolio Link Modal -->
<div id="linkModal" class="pe-modal-overlay" onclick="if(event.target===this)closeModal('linkModal')">
    <div class="pe-modal-box">
        <div class="flex items-center justify-between mb-5">
            <h3 class="pe-modal-title mb-0">Portfolio URL</h3>
            <button onclick="closeModal('linkModal')" class="w-8 h-8 rounded-lg hover:bg-gray-100 flex items-center justify-center text-gray-400"><i class="fas fa-times text-sm"></i></button>
        </div>
        <label class="block text-xs font-semibold text-gray-700 mb-1.5">Website / Portfolio</label>
        <input type="url" id="modalPortfolio" value="<?= sanitize_string($profile['portfolio_url'] ?? '') ?>" placeholder="https://yoursite.com" maxlength="255" class="pe-input mb-5">
        <div class="flex justify-end gap-3">
            <button onclick="closeModal('linkModal')" class="pe-btn-ghost">Cancel</button>
            <button onclick="savePortfolio()" class="pe-btn-primary">Save</button>
        </div>
    </div>
</div>

<script>
    // ── Modal Helpers ──────────────────────────────────────────────
    function openModal(id) {
        document.getElementById(id).classList.add('active');
        document.body.style.overflow = 'hidden';
    }

    function closeModal(id) {
        document.getElementById(id).classList.remove('active');
        document.body.style.overflow = '';
    }

    // ── Save Functions (update hidden fields + UI) ────────────────
    function saveName() {
        const val = document.getElementById('modalName').value.trim();
        if (!val) {
            alert('Name is required.');
            return;
        }
        document.getElementById('fieldName').value = val;
        document.querySelector('.pe-header-card h1').textContent = val;
        closeModal('nameModal');
        markDirty();
    }

    function saveTitle() {
        const title = document.getElementById('modalTitle').value.trim();
        const rate = parseFloat(document.getElementById('modalRate').value) || 0;
        const years = parseInt(document.getElementById('modalYears').value) || 0;
        if (!title) {
            alert('Title is required.');
            return;
        }
        document.getElementById('fieldTitle').value = title;
        document.getElementById('fieldRate').value = rate.toFixed(2);
        document.getElementById('fieldYears').value = years;
        document.querySelector('.pe-field-title').textContent = title;
        document.querySelector('.pe-field-group .text-lg.font-bold').textContent = '$' + rate.toFixed(2) + '/hr';
        closeModal('titleModal');
        markDirty();
    }

    function saveBio() {
        const val = document.getElementById('modalBio').value.trim();
        if (!val) {
            alert('Bio is required.');
            return;
        }
        document.getElementById('fieldBio').value = val;
        const bioEl = document.querySelector('.pe-bio-text');
        if (bioEl) {
            bioEl.textContent = val;
        } else {
            const container = document.querySelector('.pe-field-group .flex-1');
            container.innerHTML = '<p class="pe-bio-text">' + val.replace(/\n/g, '<br>') + '</p>';
        }
        closeModal('bioModal');
        markDirty();
    }

    function saveAvailability() {
        const val = document.getElementById('modalAvailability').value;
        document.getElementById('fieldAvailability').value = val;
        const pill = document.querySelector('.pe-available-pill');
        pill.innerHTML = '<span class="pe-available-dot"></span> ' + val;
        closeModal('availabilityModal');
        markDirty();
    }

    function savePortfolio() {
        const val = document.getElementById('modalPortfolio').value.trim();
        document.getElementById('fieldPortfolio').value = val;
        closeModal('linkModal');
        markDirty();
    }

    function saveSkills() {
        const checked = document.querySelectorAll('.modal-skill-tag input:checked');
        const container = document.querySelector('.skill-hidden-input')?.parentElement;
        // Remove all existing hidden skill inputs
        document.querySelectorAll('.skill-hidden-input').forEach(el => el.remove());
        // Add new ones
        const form = document.getElementById('profileForm');
        checked.forEach(cb => {
            const inp = document.createElement('input');
            inp.type = 'hidden';
            inp.name = 'skills[]';
            inp.value = cb.value;
            inp.className = 'skill-hidden-input';
            form.appendChild(inp);
        });
        // Update sidebar skill display
        const skillsContainer = document.querySelector('.pe-sidebar-card .space-y-1');
        // Update skills pills in main content
        const mainSkills = document.querySelector('.flex.flex-wrap.gap-2');
        if (mainSkills) {
            mainSkills.innerHTML = '';
            checked.forEach(cb => {
                const label = cb.closest('label');
                const span = document.createElement('span');
                span.className = 'pe-skill-pill';
                span.textContent = label.textContent.trim();
                mainSkills.appendChild(span);
            });
        }
        closeModal('skillsModal');
        markDirty();
    }

    // ── Modal Skill Tags Toggle ──────────────────────────────────
    document.querySelectorAll('.modal-skill-tag').forEach(tag => {
        tag.addEventListener('click', function(e) {
            e.preventDefault();
            const cb = this.querySelector('input[type="checkbox"]');
            cb.checked = !cb.checked;
            this.classList.toggle('selected', cb.checked);
            if (cb.checked) {
                this.style.background = '#eff6ff';
                this.style.borderColor = '#bfdbfe';
                this.style.color = '#2563eb';
            } else {
                this.style.background = '';
                this.style.borderColor = '';
                this.style.color = '';
            }
        });
    });

    // ── Bio Character Count ──────────────────────────────────────
    const bioModal = document.getElementById('modalBio');
    const bioCount = document.getElementById('modalBioCount');
    if (bioModal && bioCount) {
        bioModal.addEventListener('input', () => bioCount.textContent = bioModal.value.length);
        bioCount.textContent = bioModal.value.length;
    }

    // ── Profile Image Preview ────────────────────────────────────
    document.getElementById('profileImageInput').addEventListener('change', function(e) {
        const file = e.target.files[0];
        if (!file) return;
        if (file.size > 5 * 1024 * 1024) {
            alert('File size must be under 5MB.');
            this.value = '';
            return;
        }
        if (!['image/jpeg', 'image/png', 'image/gif', 'image/webp'].includes(file.type)) {
            alert('Only JPG, PNG, GIF, WebP allowed.');
            this.value = '';
            return;
        }
        const reader = new FileReader();
        reader.onload = function(ev) {
            document.getElementById('avatarPreview').src = ev.target.result;
        };
        reader.readAsDataURL(file);
        markDirty();
    });

    // ── Dirty Tracking & Auto-Save on Submit ─────────────────────
    let isDirty = false;

    function markDirty() {
        isDirty = true;
    }

    document.getElementById('profileForm').addEventListener('submit', function(e) {
        const btn = document.querySelector('.pe-btn-primary:last-of-type');
        if (btn) {
            btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Saving...';
            btn.disabled = true;
        }
    });

    window.addEventListener('beforeunload', function(e) {
        if (isDirty) {
            e.preventDefault();
            e.returnValue = '';
        }
    });
</script>
<?php $conn->close(); ?>
<?php require_once __DIR__ . '/../components/freelancer_footer.php'; ?>