<?php
$page_title = 'Profile Settings';
require_once '../auth/auth.php';
require_role('freelancer');
require_once '../config/db.php';

$userId = $_SESSION['user_id'];

$stmt = $conn->prepare('
    SELECT u.id AS user_id, u.name, u.email, u.phone, u.profile_image, u.created_at AS member_since,
           f.id AS freelancer_id, f.title, f.bio, f.hourly_rate, f.portfolio_url, f.social_links,
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

$stmtRating = $conn->prepare('SELECT COALESCE(AVG(rating), 0) AS avg_rating, COUNT(*) AS review_count FROM reviews WHERE reviewee_id = ? AND COALESCE(is_hidden, 0) = 0');
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

    .chip-container {
        display: flex;
        flex-wrap: wrap;
        gap: 6px;
        padding: 8px 12px;
        border: 1px solid #e2e8f0;
        border-radius: 10px;
        background: #f8fafc;
        min-height: 44px;
        cursor: text;
        transition: all .15s;
    }
    .chip-container:focus-within {
        border-color: #2563eb;
        background: #fff;
        box-shadow: 0 0 0 3px rgba(37, 99, 235, .1);
    }
    .chip-tag {
        display: inline-flex;
        align-items: center;
        gap: 4px;
        padding: 4px 10px;
        background: #eff6ff;
        border: 1px solid #bfdbfe;
        border-radius: 20px;
        font-size: 12px;
        font-weight: 500;
        color: #2563eb;
        animation: chipIn .15s ease;
    }
    @keyframes chipIn {
        from { transform: scale(0.8); opacity: 0; }
        to { transform: scale(1); opacity: 1; }
    }
    .chip-tag .chip-remove {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        width: 16px;
        height: 16px;
        border-radius: 50%;
        background: transparent;
        color: #2563eb;
        cursor: pointer;
        font-size: 10px;
        transition: all .15s;
        border: none;
        padding: 0;
        line-height: 1;
    }
    .chip-tag .chip-remove:hover {
        background: #2563eb;
        color: #fff;
    }
    .chip-search {
        flex: 1;
        min-width: 120px;
        border: none;
        outline: none;
        background: transparent;
        font-size: 13px;
        color: #0f172a;
        padding: 4px 0;
    }
    .chip-search::placeholder {
        color: #94a3b8;
    }
    .chip-clear-btn {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        width: 20px;
        height: 20px;
        border-radius: 50%;
        border: none;
        background: #cbd5e1;
        color: #fff;
        font-size: 14px;
        cursor: pointer;
        flex-shrink: 0;
        transition: all .15s;
        padding: 0;
        line-height: 1;
    }
    .chip-clear-btn:hover {
        background: #94a3b8;
    }
    .chip-dropdown {
        position: absolute;
        top: 100%;
        left: 0;
        right: 0;
        background: #fff;
        border: 1px solid #e2e8f0;
        border-radius: 10px;
        box-shadow: 0 8px 24px rgba(0,0,0,.12);
        max-height: 220px;
        overflow-y: auto;
        z-index: 10;
        display: none;
        margin-top: 4px;
    }
    .chip-dropdown.active {
        display: block;
    }
    .chip-dropdown-item {
        padding: 8px 14px;
        font-size: 13px;
        color: #334155;
        cursor: pointer;
        transition: background .1s;
    }
    .chip-dropdown-item:hover {
        background: #eff6ff;
        color: #2563eb;
    }
    .chip-dropdown-item.selected {
        color: #94a3b8;
        cursor: default;
    }
    .chip-dropdown-item.selected:hover {
        background: transparent;
        color: #94a3b8;
    }
    .chip-dropdown-category {
        padding: 6px 14px 2px;
        font-size: 10px;
        font-weight: 700;
        color: #94a3b8;
        text-transform: uppercase;
        letter-spacing: 0.05em;
    }
    .chip-wrapper {
        position: relative;
    }
    .chip-limit {
        font-size: 11px;
        color: #94a3b8;
        margin-top: 6px;
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
                <?php $_peHasImage = !empty($profile['profile_image']) && file_exists(__DIR__ . '/../assets/upload/profiles/' . basename($profile['profile_image'])); ?>
                <img id="avatarPreview"
                    src="<?= $_peHasImage ? get_profile_image($profile['profile_image']) . '?v=' . time() : '' ?>"
                    alt="<?= sanitize_string($profile['name']) ?>"
                    class="pe-avatar-img"<?= $_peHasImage ? '' : ' style="display:none"' ?>>
                <?php if (!$_peHasImage): ?>
                <div id="avatarPlaceholder" class="pe-avatar-img bg-[#E8EDFF] flex items-center justify-center">
                    <svg xmlns="http://www.w3.org/2000/svg" width="32" height="32" viewBox="0 0 24 24" fill="none" stroke="#94a3b8" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><path d="M14.5 4h-5L7 7H4a2 2 0 0 0-2 2v9a2 2 0 0 0 2 2h16a2 2 0 0 0 2-2V9a2 2 0 0 0-2-2h-3l-2.5-3z"/><circle cx="12" cy="13" r="3"/></svg>
                </div>
                <?php endif; ?>
                <label for="profileImageInput" class="pe-avatar-edit" title="Change photo">
                    <i data-lucide="pencil" class="w-3 h-3"></i>
                </label>
                <input type="file" name="profile_image" id="profileImageInput"
                    accept="image/jpeg,image/png,image/gif,image/webp" class="hidden">
            </div>

            <!-- Name + Info -->
            <div class="flex-1 min-w-0">
                <div class="flex items-center gap-2 mb-1 flex-wrap">
                    <h1 class="text-xl font-extrabold text-gray-900"><?= sanitize_string($profile['name']) ?></h1>
                    <span class="pe-verified-badge" title="Verified"><i data-lucide="check" class="w-3 h-3"></i></span>
                </div>
                <div class="flex items-center gap-2 text-sm text-gray-500 mb-3">
                    <i data-lucide="map-pin" class="w-3 h-3 text-gray-400"></i>
                    <span><?= date('g:i a') ?> local time</span>
                </div>
                <div class="flex items-center gap-3 flex-wrap mb-4">
                    <?php
                    $avail = $profile['availability'] ?? 'Available';
                    $availStyles = [
                        'Available' => 'background:#ecfdf5;border-color:#a7f3d0;color:#059669;',
                        'Busy' => 'background:#fffbeb;border-color:#fde68a;color:#b45309;',
                        'Unavailable' => 'background:#fef2f2;border-color:#fecaca;color:#dc2626;',
                    ];
                    $availDotColors = [
                        'Available' => '#059669',
                        'Busy' => '#b45309',
                        'Unavailable' => '#dc2626',
                    ];
                    $pillStyle = $availStyles[$avail] ?? $availStyles['Available'];
                    $dotColor = $availDotColors[$avail] ?? '#059669';
                    ?>
                    <span class="pe-available-pill" style="<?= $pillStyle ?>">
                        <span class="pe-available-dot" style="background:<?= $dotColor ?>;width:7px;height:7px;border-radius:50%;"></span> <?= sanitize_string($avail) ?>
                    </span>
                    <button onclick="openModal('availabilityModal')" class="pe-edit-inline" title="Edit availability">
                        <i data-lucide="pencil" class="w-3 h-3"></i>
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
                        <i data-lucide="shield" class="w-3 h-3"></i> EXPERT-VETTED
                    </span>
                </div>
            </div>

            <!-- Right Buttons -->
            <div class="flex items-center gap-3 flex-shrink-0">
                <a href="profile.php" class="pe-btn-outline">
                    <i data-lucide="eye" class="w-4 h-4"></i> See Public View
                </a>
                <!-- <span class="pe-btn-green">
                    <i data-lucide="settings" class="w-4 h-4"></i> Profile Settings
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
                    <i data-lucide="user" class="w-4 h-4"></i> <?php echo sanitize_string($profile['name']) ?>
                    <button onclick="openModal('nameModal'); event.preventDefault(); event.stopPropagation();" class="pe-edit-inline ml-auto" title="Edit name">
                        <i data-lucide="pencil" class="w-3 h-3"></i>
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
                                All work <i data-lucide="chevron-right" class="w-3 h-3 ml-auto"></i>
                            </a>
                        <?php endif; ?>
                    <?php else: ?>
                        <a href="browse_jobs.php" class="pe-nav-item">
                            All work <i data-lucide="chevron-right" class="w-3 h-3 ml-auto"></i>
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
                    <button class="pe-edit-inline" title="Add video"><i data-lucide="plus" class="w-3 h-3"></i></button>
                </div>
                <p class="text-[11px] text-gray-400">Add a video to stand out.</p>
            </div> -->

            <!-- Phone -->
            <div class="pe-sidebar-card">
                <div class="flex items-center justify-between mb-2">
                    <span class="pe-section-label mb-0">Phone</span>
                    <button onclick="openModal('phoneModal')" class="pe-edit-inline" title="Edit phone">
                        <i data-lucide="pencil" class="w-3 h-3"></i>
                    </button>
                </div>
                <div class="flex items-center gap-2 text-sm text-gray-600">
                    <i data-lucide="phone" class="w-3 h-3 text-gray-400"></i>
                    <span id="phoneDisplay"><?= sanitize_string($profile['phone'] ?? 'Not set') ?></span>
                </div>
            </div>

            <!-- Social Links -->
            <div class="pe-sidebar-card">
                <div class="flex items-center justify-between mb-2">
                    <span class="pe-section-label mb-0">Social Links</span>
                    <button onclick="openModal('linkModal')" class="pe-edit-inline" title="Edit social links">
                        <i data-lucide="pencil" class="w-3 h-3"></i>
                    </button>
                </div>
                <div class="space-y-2">
                    <?php if (!empty($socialLinks['website'])): ?>
                        <div class="flex items-center gap-2 text-sm text-gray-600">
                            <i data-lucide="globe" class="w-3 h-3 text-gray-400 flex-shrink-0"></i>
                            <span class="truncate" id="socialWebsiteDisplay"><?= sanitize_string($socialLinks['website']) ?></span>
                        </div>
                    <?php endif; ?>
                    <?php if (!empty($socialLinks['github'])): ?>
                        <div class="flex items-center gap-2 text-sm text-gray-600">
                            <svg xmlns="http://www.w3.org/2000/svg" width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="text-gray-400 flex-shrink-0"><path d="M15 22v-4a4.8 4.8 0 0 0-1-3.5c3 0 6-2 6-5.5.08-1.25-.27-2.48-1-3.5.28-1.15.28-2.35 0-3.5 0 0-1 0-3 1.5-2.64-.5-5.36-.5-8 0C6 2 5 2 5 2c-.3 1.15-.3 2.35 0 3.5A5.403 5.403 0 0 0 4 9c0 3.5 3 5.5 6 5.5-.39.49-.68 1.05-.85 1.65-.17.6-.22 1.23-.15 1.85v4"/><path d="M9 18c-4.51 2-5-2-7-2"/></svg>
                            <span class="truncate" id="socialGithubDisplay"><?= sanitize_string($socialLinks['github']) ?></span>
                        </div>
                    <?php endif; ?>
                    <?php if (!empty($socialLinks['linkedin'])): ?>
                        <div class="flex items-center gap-2 text-sm text-gray-600">
                            <svg xmlns="http://www.w3.org/2000/svg" width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="text-gray-400 flex-shrink-0"><path d="M16 8a6 6 0 0 1 6 6v7h-4v-7a2 2 0 0 0-2-2 2 2 0 0 0-2 2v7h-4v-7a6 6 0 0 1 6-6z"/><rect width="4" height="12" x="2" y="9"/><circle cx="4" cy="4" r="2"/></svg>
                            <span class="truncate" id="socialLinkedinDisplay"><?= sanitize_string($socialLinks['linkedin']) ?></span>
                        </div>
                    <?php endif; ?>
                    <?php if (empty($socialLinks['website']) && empty($socialLinks['github']) && empty($socialLinks['linkedin'])): ?>
                        <p class="text-[11px] text-gray-400">Add your website, GitHub, or LinkedIn.</p>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- MAIN CONTENT -->
        <div class="flex-1 min-w-0 space-y-5">

            <!-- Title + Bio Card -->
            <div class="pe-field-group">
                <!-- Title Row -->
                <div class="pe-field-row" style="padding: 0;">
                    <div class="flex-1">
                        <div class="pe-field-title text-lg"><?= sanitize_string($profile['title'] ?: 'Your Professional Title') ?></div>
                    </div>
                    <div class="flex items-center gap-2 flex-shrink-0">
                        <span class="text-lg font-bold text-gray-900"><?= format_currency($profile['hourly_rate'] ?? 0) ?>/hr</span>
                        <button onclick="openModal('titleModal')" class="pe-edit-inline" title="Edit title & rate">
                            <i data-lucide="pencil" class="w-3 h-3"></i>
                        </button>
                        <button onclick="openModal('linkModal')" class="pe-edit-inline" title="Portfolio link">
                            <i data-lucide="link" class="w-3 h-3"></i>
                        </button>
                    </div>
                </div>

                <!-- Bio / About -->
                <div class="flex items-start justify-between gap-3" style="padding-top: 12px; border-top: 1px solid #f1f5f9;">
                    <div class="flex-1">
                        <?php if (!empty($profile['bio'])): ?>
                            <p class="pe-bio-text"><?= nl2br(sanitize_string($profile['bio'])) ?></p>
                        <?php else: ?>
                            <p class="text-sm text-gray-400 italic">Add a bio to tell clients about your skills and experience.</p>
                        <?php endif; ?>
                    </div>
                    <button onclick="openModal('bioModal')" class="pe-edit-inline mt-0.5" title="Edit bio">
                        <i data-lucide="pencil" class="w-3 h-3"></i>
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
            <div class="bg-white rounded-2xl p-6 border border-gray-100 shadow-sm fade-in">
                <div class="flex items-center gap-3 mb-4">
                    <div class="w-10 h-10 rounded-xl bg-indigo-50 flex items-center justify-center text-indigo-600 flex-shrink-0">
                        <i data-lucide="send" class="w-5 h-5"></i>
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
                    <a href="/jobhub/client/invite_jobs.php?freelancer_id=<?= $profile['freelancer_id'] ?>"
                        class="w-full inline-flex items-center justify-center gap-2 px-4 py-3 btn-grad text-white text-xs font-bold rounded-xl shadow-lg shadow-indigo-500/25 hover:opacity-95 transition-all">
                        <i data-lucide="mail" class="w-3 h-3"></i> Invite to Job
                    </a>
                <?php else: ?>
                    <div class="w-full text-center py-2 bg-gray-50 text-gray-400 text-xs font-medium rounded-xl border border-dashed border-gray-200">
                        Visible to clients visiting your profile
                    </div>
                <?php endif; ?>
            </div>

            <!-- Skills Section -->
            <div class="pe-field-group">
                <div class="flex items-center justify-between mb-3">
                    <h3 class="pe-section-label mb-0">Skills</h3>
                    <button onclick="openModal('skillsModal')" class="pe-edit-inline" title="Edit skills">
                        <i data-lucide="pencil" class="w-3 h-3"></i>
                    </button>
                </div>
                <?php if (!empty($userSkillNames)): ?>
                    <div class="flex flex-wrap gap-2 skills-display">
                        <?php foreach ($userSkillNames as $skillName): ?>
                            <span class="pe-skill-pill"><?= sanitize_string($skillName) ?></span>
                        <?php endforeach; ?>
                    </div>
                <?php else: ?>
                    <div class="skills-display">
                        <p class="text-sm text-gray-400 italic">No skills added yet. Click the edit button to add your skills.</p>
                    </div>
                <?php endif; ?>
            </div>

            <!-- Password Section -->
            <div class="pe-field-group">
                <div class="flex items-center justify-between">
                    <div>
                        <div class="pe-field-title">Password</div>
                        <div class="pe-field-sub">Update your account password</div>
                    </div>
                    <button onclick="openModal('passwordModal')" class="pe-edit-inline" title="Change password">
                        <i data-lucide="pencil" class="w-3 h-3"></i>
                    </button>
                </div>
            </div>

            <!-- Full Edit Form (hidden, submits to profile_update.php) -->
            <form action="profile_update.php" method="POST" enctype="multipart/form-data" id="profileForm" class="hidden">
                <?= csrf_field() ?>
                <input type="hidden" name="name" id="fieldName" value="<?= sanitize_string($profile['name']) ?>">
                <input type="hidden" name="title" id="fieldTitle" value="<?= sanitize_string($profile['title'] ?? '') ?>">
                <input type="hidden" name="hourly_rate" id="fieldRate" value="<?= sanitize_float($profile['hourly_rate'] ?? 0) ?>">
                <input type="hidden" name="bio" id="fieldBio" value="<?= sanitize_string($profile['bio'] ?? '') ?>">
                <input type="hidden" name="availability" id="fieldAvailability" value="<?= sanitize_string($profile['availability'] ?? 'Available') ?>">
                <input type="hidden" name="years_of_experience" id="fieldYears" value="<?= sanitize_int($profile['years_of_experience'] ?? 0) ?>">
                <input type="hidden" name="social_links_website" id="fieldSocialWebsite" value="<?= sanitize_string($socialLinks['website']) ?>">
                <input type="hidden" name="social_links_github" id="fieldSocialGithub" value="<?= sanitize_string($socialLinks['github']) ?>">
                <input type="hidden" name="social_links_linkedin" id="fieldSocialLinkedin" value="<?= sanitize_string($socialLinks['linkedin']) ?>">
                <input type="hidden" name="phone" id="fieldPhone" value="<?= sanitize_string($profile['phone'] ?? '') ?>">
                <?php foreach ($selectedSkills as $sid): ?>
                    <input type="hidden" name="skills[]" value="<?= $sid ?>" class="skill-hidden-input">
                <?php endforeach; ?>
            </form>

            <!-- Password Change Form (separate) -->
            <form action="profile_update.php" method="POST" id="passwordForm" class="hidden">
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="password_change">
                <input type="hidden" name="current_password" id="fieldCurrentPassword" value="">
                <input type="hidden" name="new_password" id="fieldNewPassword" value="">
                <input type="hidden" name="confirm_password" id="fieldConfirmPassword" value="">
            </form>

            <!-- Bottom Action -->
            <div class="flex items-center justify-between mt-8 pt-6 border-t border-gray-100">
                <a href="profile.php" class="pe-btn-ghost">
                    <i data-lucide="arrow-left" class="w-4 h-4"></i> Back to Profile
                </a>
                <button onclick="document.getElementById('profileForm').submit();" class="pe-btn-primary">
                    <i data-lucide="save" class="w-4 h-4"></i> Save All Changes
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
            <button onclick="closeModal('nameModal')" class="w-8 h-8 rounded-lg hover:bg-gray-100 flex items-center justify-center text-gray-400"><i data-lucide="x" class="w-4 h-4"></i></button>
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
            <button onclick="closeModal('titleModal')" class="w-8 h-8 rounded-lg hover:bg-gray-100 flex items-center justify-center text-gray-400"><i data-lucide="x" class="w-4 h-4"></i></button>
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
            <button onclick="closeModal('bioModal')" class="w-8 h-8 rounded-lg hover:bg-gray-100 flex items-center justify-center text-gray-400"><i data-lucide="x" class="w-4 h-4"></i></button>
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
            <button onclick="closeModal('availabilityModal')" class="w-8 h-8 rounded-lg hover:bg-gray-100 flex items-center justify-center text-gray-400"><i data-lucide="x" class="w-4 h-4"></i></button>
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
            <button onclick="closeModal('skillsModal')" class="w-8 h-8 rounded-lg hover:bg-gray-100 flex items-center justify-center text-gray-400"><i data-lucide="x" class="w-4 h-4"></i></button>
        </div>
        <label class="block text-xs font-semibold text-gray-700 mb-1.5">Your skills</label>
        <div class="chip-wrapper">
            <div class="chip-container" id="chipContainer" onclick="document.getElementById('chipSearch').focus()">
                <input type="text" id="chipSearch" class="chip-search" placeholder="Search skills" autocomplete="off">
                <button type="button" id="chipClearBtn" class="chip-clear-btn" onclick="clearChipSearch()" style="display:none;">&otimes;</button>
            </div>
            <div class="chip-dropdown" id="chipDropdown"></div>
        </div>
        <p class="chip-limit">Maximum 20 skills.</p>
        <div class="flex justify-end gap-3 mt-5">
            <button onclick="closeModal('skillsModal')" class="pe-btn-ghost">Cancel</button>
            <button onclick="saveSkills()" class="pe-btn-primary">Save</button>
        </div>
    </div>
</div>

<!-- Social Links Modal -->
<div id="linkModal" class="pe-modal-overlay" onclick="if(event.target===this)closeModal('linkModal')">
    <div class="pe-modal-box">
        <div class="flex items-center justify-between mb-5">
            <h3 class="pe-modal-title mb-0">Social Links</h3>
            <button onclick="closeModal('linkModal')" class="w-8 h-8 rounded-lg hover:bg-gray-100 flex items-center justify-center text-gray-400"><i data-lucide="x" class="w-4 h-4"></i></button>
        </div>
        <label class="block text-xs font-semibold text-gray-700 mb-1.5">Website / Portfolio</label>
        <input type="url" id="modalSocialWebsite" value="<?= sanitize_string($socialLinks['website']) ?>" placeholder="https://yoursite.com" maxlength="255" class="pe-input mb-4">
        <label class="block text-xs font-semibold text-gray-700 mb-1.5">GitHub</label>
        <input type="url" id="modalSocialGithub" value="<?= sanitize_string($socialLinks['github']) ?>" placeholder="https://github.com/username" maxlength="255" class="pe-input mb-4">
        <label class="block text-xs font-semibold text-gray-700 mb-1.5">LinkedIn</label>
        <input type="url" id="modalSocialLinkedin" value="<?= sanitize_string($socialLinks['linkedin']) ?>" placeholder="https://linkedin.com/in/username" maxlength="255" class="pe-input mb-5">
        <div class="flex justify-end gap-3">
            <button onclick="closeModal('linkModal')" class="pe-btn-ghost">Cancel</button>
            <button onclick="saveSocialLinks()" class="pe-btn-primary">Save</button>
        </div>
    </div>
</div>

<!-- Phone Modal -->
<div id="phoneModal" class="pe-modal-overlay" onclick="if(event.target===this)closeModal('phoneModal')">
    <div class="pe-modal-box">
        <div class="flex items-center justify-between mb-5">
            <h3 class="pe-modal-title mb-0">Edit Phone Number</h3>
            <button onclick="closeModal('phoneModal')" class="w-8 h-8 rounded-lg hover:bg-gray-100 flex items-center justify-center text-gray-400"><i data-lucide="x" class="w-4 h-4"></i></button>
        </div>
        <label class="block text-xs font-semibold text-gray-700 mb-1.5">Phone Number</label>
        <input type="tel" id="modalPhone" value="<?= sanitize_string($profile['phone'] ?? '') ?>" placeholder="+1 (555) 123-4567" maxlength="20" class="pe-input mb-5">
        <div class="flex justify-end gap-3">
            <button onclick="closeModal('phoneModal')" class="pe-btn-ghost">Cancel</button>
            <button onclick="savePhone()" class="pe-btn-primary">Save</button>
        </div>
    </div>
</div>

<!-- Password Modal -->
<div id="passwordModal" class="pe-modal-overlay" onclick="if(event.target===this)closeModal('passwordModal')">
    <div class="pe-modal-box">
        <div class="flex items-center justify-between mb-5">
            <h3 class="pe-modal-title mb-0">Change Password</h3>
            <button onclick="closeModal('passwordModal')" class="w-8 h-8 rounded-lg hover:bg-gray-100 flex items-center justify-center text-gray-400"><i data-lucide="x" class="w-4 h-4"></i></button>
        </div>

        <label class="block text-xs font-semibold text-gray-700 mb-1.5">Current Password</label>
        <div class="relative mb-4">
            <input type="password" id="modalCurrentPassword" placeholder="Enter current password" autocomplete="current-password" class="pe-input pr-10">
            <button type="button" onclick="togglePassword('modalCurrentPassword', this)" class="absolute right-3 top-1/2 -translate-y-1/2 text-gray-400 hover:text-gray-600">
                <i data-lucide="eye" class="w-4 h-4"></i>
            </button>
        </div>

        <label class="block text-xs font-semibold text-gray-700 mb-1.5">New Password</label>
        <div class="relative mb-1">
            <input type="password" id="modalNewPassword" placeholder="Enter new password" autocomplete="new-password" class="pe-input pr-10" oninput="checkPasswordStrength()">
            <button type="button" onclick="togglePassword('modalNewPassword', this)" class="absolute right-3 top-1/2 -translate-y-1/2 text-gray-400 hover:text-gray-600">
                <i data-lucide="eye" class="w-4 h-4"></i>
            </button>
        </div>
        <div class="flex gap-1 mb-1 mt-2">
            <div id="pwdStr1" class="h-1 flex-1 rounded-full bg-gray-200"></div>
            <div id="pwdStr2" class="h-1 flex-1 rounded-full bg-gray-200"></div>
            <div id="pwdStr3" class="h-1 flex-1 rounded-full bg-gray-200"></div>
            <div id="pwdStr4" class="h-1 flex-1 rounded-full bg-gray-200"></div>
        </div>
        <p id="pwdStrText" class="text-[11px] text-gray-400 mb-4">&nbsp;</p>
        <p id="modalPwdErrors" class="text-[11px] text-red-500 mb-3 hidden"></p>

        <label class="block text-xs font-semibold text-gray-700 mb-1.5">Confirm New Password</label>
        <div class="relative mb-1">
            <input type="password" id="modalConfirmPassword" placeholder="Repeat new password" autocomplete="new-password" class="pe-input pr-10" oninput="checkPasswordMatch()">
            <button type="button" onclick="togglePassword('modalConfirmPassword', this)" class="absolute right-3 top-1/2 -translate-y-1/2 text-gray-400 hover:text-gray-600">
                <i data-lucide="eye" class="w-4 h-4"></i>
            </button>
        </div>
        <p id="modalMatchMsg" class="text-[11px] mb-5">&nbsp;</p>

        <div class="flex justify-end gap-3">
            <button onclick="closeModal('passwordModal')" class="pe-btn-ghost">Cancel</button>
            <button onclick="savePassword()" class="pe-btn-primary">Update Password</button>
        </div>
    </div>
</div>

<script>
    // ── Skill Data for Chip Input ──────────────────────────────────
    <?php
    $flatSkills = [];
    foreach ($allSkills as $category => $catSkills) {
        foreach ($catSkills as $s) {
            $flatSkills[] = ['id' => (int) $s['id'], 'name' => $s['skill_name'], 'category' => $category];
        }
    }
    ?>
    const allSkillsData = <?= json_encode($flatSkills) ?>;
    const selectedSkillIds = <?= json_encode($selectedSkills) ?>;
    const MAX_SKILLS = 20;

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
        const styles = {
            'Available':   'background:#ecfdf5;border-color:#a7f3d0;color:#059669;',
            'Busy':        'background:#fffbeb;border-color:#fde68a;color:#b45309;',
            'Unavailable': 'background:#fef2f2;border-color:#fecaca;color:#dc2626;'
        };
        const dotColors = {
            'Available':   '#059669',
            'Busy':        '#b45309',
            'Unavailable': '#dc2626'
        };
        pill.setAttribute('style', styles[val] || styles['Available']);
        pill.innerHTML = '<span class="pe-available-dot" style="background:' + (dotColors[val] || '#059669') + ';width:7px;height:7px;border-radius:50%;"></span> ' + val;
        closeModal('availabilityModal');
        markDirty();
    }

    function saveSocialLinks() {
        document.getElementById('fieldSocialWebsite').value = document.getElementById('modalSocialWebsite').value.trim();
        document.getElementById('fieldSocialGithub').value = document.getElementById('modalSocialGithub').value.trim();
        document.getElementById('fieldSocialLinkedin').value = document.getElementById('modalSocialLinkedin').value.trim();
        closeModal('linkModal');
        markDirty();
    }

    function savePhone() {
        const val = document.getElementById('modalPhone').value.trim();
        if (val && val.length > 20) {
            alert('Phone number must be under 20 characters.');
            return;
        }
        document.getElementById('fieldPhone').value = val;
        document.getElementById('phoneDisplay').textContent = val || 'Not set';
        closeModal('phoneModal');
        markDirty();
    }

    function savePassword() {
        const current = document.getElementById('modalCurrentPassword').value;
        const newPwd = document.getElementById('modalNewPassword').value;
        const confirm = document.getElementById('modalConfirmPassword').value;
        const errEl = document.getElementById('modalPwdErrors');

        errEl.classList.add('hidden');
        errEl.textContent = '';

        const errors = [];
        if (!current) errors.push('Current password is required.');
        if (!newPwd) errors.push('New password is required.');
        if (newPwd !== confirm) errors.push('New passwords do not match.');
        if (newPwd.length < 8) errors.push('Password must be at least 8 characters.');
        if (!/[A-Z]/.test(newPwd)) errors.push('Password must contain an uppercase letter.');
        if (!/[a-z]/.test(newPwd)) errors.push('Password must contain a lowercase letter.');
        if (!/[0-9]/.test(newPwd)) errors.push('Password must contain a number.');

        if (errors.length > 0) {
            errEl.textContent = errors.join(' ');
            errEl.classList.remove('hidden');
            return;
        }

        document.getElementById('fieldCurrentPassword').value = current;
        document.getElementById('fieldNewPassword').value = newPwd;
        document.getElementById('fieldConfirmPassword').value = confirm;
        closeModal('passwordModal');
        document.getElementById('modalCurrentPassword').value = '';
        document.getElementById('modalNewPassword').value = '';
        document.getElementById('modalConfirmPassword').value = '';
        document.getElementById('modalMatchMsg').innerHTML = '&nbsp;';
        resetPwdStrength();
        isDirty = false;
        document.getElementById('passwordForm').submit();
    }

    function togglePassword(inputId, btn) {
        const f = document.getElementById(inputId);
        const icon = btn.querySelector('i');
        if (f.type === 'password') {
            f.type = 'text';
            icon.setAttribute('data-lucide', 'eye-off');
        } else {
            f.type = 'password';
            icon.setAttribute('data-lucide', 'eye');
        }
        lucide.createIcons();
    }

    function checkPasswordStrength() {
        const pwd = document.getElementById('modalNewPassword').value;
        let score = 0;
        if (pwd.length >= 8) score++;
        if (/[A-Z]/.test(pwd) && /[a-z]/.test(pwd)) score++;
        if (/[0-9]/.test(pwd)) score++;
        if (/[^A-Za-z0-9]/.test(pwd)) score++;

        const colors = ['#ef4444', '#f97316', '#eab308', '#22c55e'];
        const labels = ['Weak', 'Fair', 'Good', 'Strong'];
        const bars = ['pwdStr1', 'pwdStr2', 'pwdStr3', 'pwdStr4'];
        const text = document.getElementById('pwdStrText');

        bars.forEach((id, i) => {
            const bar = document.getElementById(id);
            if (i < score) {
                bar.style.background = colors[score - 1];
            } else {
                bar.style.background = '#e2e8f0';
            }
        });

        if (pwd.length === 0) {
            text.innerHTML = '&nbsp;';
            resetPwdStrength();
        } else {
            text.textContent = labels[score - 1] || 'Too short';
            text.style.color = colors[score - 1] || '#ef4444';
        }
    }

    function resetPwdStrength() {
        ['pwdStr1', 'pwdStr2', 'pwdStr3', 'pwdStr4'].forEach(id => {
            document.getElementById(id).style.background = '#e2e8f0';
        });
        document.getElementById('pwdStrText').innerHTML = '&nbsp;';
    }

    function checkPasswordMatch() {
        const newPwd = document.getElementById('modalNewPassword').value;
        const confirm = document.getElementById('modalConfirmPassword').value;
        const msg = document.getElementById('modalMatchMsg');

        if (!confirm) {
            msg.innerHTML = '&nbsp;';
            return;
        }
        if (newPwd === confirm) {
            msg.textContent = 'Passwords match';
            msg.style.color = '#059669';
        } else {
            msg.textContent = 'Passwords do not match';
            msg.style.color = '#dc2626';
        }
    }

    function saveSkills() {
        document.querySelectorAll('.skill-hidden-input').forEach(el => el.remove());
        const form = document.getElementById('profileForm');
        chipSelectedIds.forEach(id => {
            const inp = document.createElement('input');
            inp.type = 'hidden';
            inp.name = 'skills[]';
            inp.value = id;
            inp.className = 'skill-hidden-input';
            form.appendChild(inp);
        });
        const mainSkills = document.querySelector('.skills-display');
        if (mainSkills) {
            mainSkills.innerHTML = '';
            if (chipSelectedIds.length === 0) {
                mainSkills.innerHTML = '<p class="text-sm text-gray-400 italic">No skills added yet. Click the edit button to add your skills.</p>';
            } else {
                chipSelectedIds.forEach(id => {
                    const skill = allSkillsData.find(s => s.id === id);
                    if (skill) {
                        const span = document.createElement('span');
                        span.className = 'pe-skill-pill';
                        span.textContent = skill.name;
                        mainSkills.appendChild(span);
                    }
                });
            }
        }
        closeModal('skillsModal');
        isDirty = false;
        form.submit();
    }

    // ── Chip Skills Input ──────────────────────────────────────────
    let chipSelectedIds = [...selectedSkillIds];
    const chipContainer = document.getElementById('chipContainer');
    const chipSearch = document.getElementById('chipSearch');
    const chipDropdown = document.getElementById('chipDropdown');
    const chipClearBtn = document.getElementById('chipClearBtn');

    function renderChips() {
        chipContainer.querySelectorAll('.chip-tag').forEach(el => el.remove());
        chipSelectedIds.forEach(id => {
            const skill = allSkillsData.find(s => s.id === id);
            if (!skill) return;
            const tag = document.createElement('span');
            tag.className = 'chip-tag';
            tag.innerHTML = skill.name + ' <button type="button" class="chip-remove" onclick="removeChip(' + id + ')">&times;</button>';
            chipContainer.insertBefore(tag, chipSearch);
        });
    }

    function addChip(id) {
        if (chipSelectedIds.length >= MAX_SKILLS) {
            alert('Maximum 20 skills allowed.');
            return;
        }
        if (chipSelectedIds.includes(id)) return;
        chipSelectedIds.push(id);
        chipSearch.value = '';
        renderChips();
        filterDropdown('');
        chipSearch.focus();
    }

    function removeChip(id) {
        chipSelectedIds = chipSelectedIds.filter(i => i !== id);
        renderChips();
        filterDropdown(chipSearch.value);
    }

    function filterDropdown(query) {
        const q = query.toLowerCase().trim();
        let html = '';

        const matches = allSkillsData.filter(skill => {
            const matchName = !q || skill.name.toLowerCase().includes(q);
            return matchName;
        });

        if (matches.length === 0) {
            html = '<div class="chip-dropdown-item" style="cursor:default;color:#94a3b8;">No skills found</div>';
        } else {
            matches.forEach(skill => {
                const isSelected = chipSelectedIds.includes(skill.id);
                html += '<div class="chip-dropdown-item' + (isSelected ? ' selected' : '') + '" '
                    + (isSelected ? '' : 'onclick="addChip(' + skill.id + ')"') + '>'
                    + skill.name + (isSelected ? ' <span class="text-[10px] ml-1">&#10003;</span>' : '')
                    + '</div>';
            });
        }

        chipDropdown.innerHTML = html;
        chipDropdown.classList.add('active');
    }

    chipSearch.addEventListener('input', function() {
        chipClearBtn.style.display = this.value ? 'inline-flex' : 'none';
        filterDropdown(this.value);
    });

    chipSearch.addEventListener('focus', function() {
        chipClearBtn.style.display = this.value ? 'inline-flex' : 'none';
        filterDropdown(this.value);
    });

    function clearChipSearch() {
        chipSearch.value = '';
        chipClearBtn.style.display = 'none';
        filterDropdown('');
        chipSearch.focus();
    }

    document.addEventListener('click', function(e) {
        if (!e.target.closest('.chip-wrapper')) {
            chipDropdown.classList.remove('active');
        }
    });

    renderChips();

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
            var ap = document.getElementById('avatarPreview');
            ap.src = ev.target.result;
            ap.style.display = '';
            var ph = document.getElementById('avatarPlaceholder');
            if (ph) ph.style.display = 'none';
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
        isDirty = false;
        const btn = document.querySelector('.pe-btn-primary:last-of-type');
        if (btn && !btn.disabled) {
            btn.innerHTML = '<i data-lucide="loader" class="w-4 h-4 animate-spin"></i> Saving...';
            btn.disabled = true;
            lucide.createIcons();
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