<?php
$page_title = 'Edit Profile';
require_once '../config/helpers.php';
require_once '../auth/auth.php';
require_role('freelancer');
require_once '../config/db.php';

$userId = $_SESSION['user_id'];

$stmt = $conn->prepare('
    SELECT u.id AS user_id, u.name, u.email, u.phone, u.profile_image,
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
$conn->close();

$availabilityOptions = ['Available', 'Busy', 'Unavailable'];

$navItems = [
    ['key' => 'dashboard', 'label' => 'Dashboard', 'url' => 'dashboard.php', 'icon' => 'fa-th-large'],
    ['key' => 'profile', 'label' => 'Profile', 'url' => 'profile.php', 'icon' => 'fa-user'],
    ['key' => 'browse_jobs', 'label' => 'Browse Jobs', 'url' => 'browse_jobs.php', 'icon' => 'fa-search'],
    ['key' => 'proposals', 'label' => 'Proposals', 'url' => 'proposals.php', 'icon' => 'fa-file-alt'],
    ['key' => 'contracts', 'label' => 'Contracts', 'url' => 'contracts.php', 'icon' => 'fa-handshake'],
    ['key' => 'messages', 'label' => 'Messages', 'url' => 'messages.php', 'icon' => 'fa-comment-dots'],
    ['key' => 'earnings', 'label' => 'Earnings', 'url' => 'earnings.php', 'icon' => 'fa-wallet'],
];
$pageTitle = 'Edit Profile';
$pageSubtitle = 'Update your professional information';
$activePage = 'profile';
$user = ['name' => $profile['name'], 'profile_image' => $profile['profile_image']];
$unreadCount = get_unread_message_count($userId, 'freelancer');
$profileLink = 'profile.php';
require_once __DIR__ . '/../components/layout_start.php';
?>

            <?php display_flash('success'); ?>
            <?php display_flash('error'); ?>

            <form action="profile_update.php" method="POST" enctype="multipart/form-data" id="profileForm" class="space-y-6">
                <?= csrf_field() ?>

                <div class="bg-white rounded-2xl p-6 border border-gray-100 shadow-sm fade-in">
                    <h2 class="text-base font-bold text-gray-900 mb-5 flex items-center gap-2">
                        <div class="w-8 h-8 rounded-lg bg-blue-50 flex items-center justify-center">
                            <i class="fas fa-user text-blue-500 text-sm"></i>
                        </div>
                        Basic Information
                    </h2>

                    <div class="flex flex-col sm:flex-row items-start gap-6">
                        <div class="flex flex-col items-center">
                            <img id="avatarPreview"
                                 src="<?= get_profile_image($profile['profile_image']) ?>"
                                 class="w-28 h-28 rounded-full object-cover mb-3 border-4 border-gray-100 shadow-sm">
                            <label class="text-xs text-blue-600 hover:text-blue-700 font-medium cursor-pointer">
                                <i class="fas fa-camera mr-1"></i> Change Photo
                                <input type="file" name="profile_image" id="profileImageInput"
                                       accept="image/jpeg,image/png,image/gif,image/webp" class="hidden">
                            </label>
                            <p class="text-[10px] text-gray-400 mt-1">JPG, PNG, GIF, WebP. Max 5MB.</p>
                        </div>

                        <div class="flex-1 grid sm:grid-cols-2 gap-4">
                            <div>
                                <label class="block text-xs font-semibold text-gray-700 mb-1.5">Full Name <span class="text-red-500">*</span></label>
                                <input type="text" name="name" required maxlength="100"
                                       value="<?= sanitize_string($profile['name']) ?>"
                                       class="fld w-full bg-gray-50 border border-gray-200 rounded-xl px-4 py-2.5 text-gray-900 text-sm">
                            </div>
                            <div>
                                <label class="block text-xs font-semibold text-gray-700 mb-1.5">Email</label>
                                <input type="email" value="<?= sanitize_string($profile['email']) ?>" readonly
                                       class="w-full bg-gray-50 border border-gray-200 rounded-xl px-4 py-2.5 text-gray-500 text-sm cursor-not-allowed">
                            </div>
                            <div>
                                <label class="block text-xs font-semibold text-gray-700 mb-1.5">Phone</label>
                                <input type="tel" name="phone" maxlength="20"
                                       value="<?= sanitize_string($profile['phone'] ?? '') ?>"
                                       placeholder="+1 (555) 123-4567"
                                       class="fld w-full bg-gray-50 border border-gray-200 rounded-xl px-4 py-2.5 text-gray-900 placeholder-gray-400 text-sm">
                            </div>
                        </div>
                    </div>
                </div>

                <div class="bg-white rounded-2xl p-6 border border-gray-100 shadow-sm fade-in" style="animation-delay:.1s">
                    <h2 class="text-base font-bold text-gray-900 mb-5 flex items-center gap-2">
                        <div class="w-8 h-8 rounded-lg bg-cyan-50 flex items-center justify-center">
                            <i class="fas fa-briefcase text-cyan-500 text-sm"></i>
                        </div>
                        Professional Details
                    </h2>

                    <div class="grid sm:grid-cols-2 gap-4">
                        <div>
                            <label class="block text-xs font-semibold text-gray-700 mb-1.5">Professional Title <span class="text-red-500">*</span></label>
                            <input type="text" name="title" required maxlength="100"
                                   value="<?= sanitize_string($profile['title'] ?? '') ?>"
                                   placeholder="e.g. Full Stack Developer"
                                   class="fld w-full bg-gray-50 border border-gray-200 rounded-xl px-4 py-2.5 text-gray-900 placeholder-gray-400 text-sm">
                        </div>
                        <div>
                            <label class="block text-xs font-semibold text-gray-700 mb-1.5">Hourly Rate ($) <span class="text-red-500">*</span></label>
                            <div class="relative">
                                <span class="absolute left-4 top-1/2 -translate-y-1/2 text-gray-400 text-sm">$</span>
                                <input type="number" name="hourly_rate" step="0.01" min="0" max="99999.99" required
                                       value="<?= sanitize_float($profile['hourly_rate'] ?? 0) ?>"
                                       placeholder="0.00"
                                       class="fld w-full bg-gray-50 border border-gray-200 rounded-xl pl-8 pr-4 py-2.5 text-gray-900 text-sm">
                            </div>
                        </div>
                        <div>
                            <label class="block text-xs font-semibold text-gray-700 mb-1.5">Years of Experience <span class="text-red-500">*</span></label>
                            <input type="number" name="years_of_experience" min="0" max="50" required
                                   value="<?= sanitize_int($profile['years_of_experience'] ?? 0) ?>"
                                   class="fld w-full bg-gray-50 border border-gray-200 rounded-xl px-4 py-2.5 text-gray-900 text-sm">
                        </div>
                        <div>
                            <label class="block text-xs font-semibold text-gray-700 mb-1.5">Availability</label>
                            <select name="availability" class="fld w-full bg-gray-50 border border-gray-200 rounded-xl px-4 py-2.5 text-gray-900 text-sm appearance-none cursor-pointer">
                                <?php foreach ($availabilityOptions as $opt): ?>
                                <option value="<?= $opt ?>" <?= ($profile['availability'] ?? '') === $opt ? 'selected' : '' ?>><?= $opt ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="sm:col-span-2">
                            <label class="block text-xs font-semibold text-gray-700 mb-1.5">Portfolio URL</label>
                            <input type="url" name="portfolio_url" maxlength="255"
                                   value="<?= sanitize_string($profile['portfolio_url'] ?? '') ?>"
                                   placeholder="https://yoursite.com"
                                   class="fld w-full bg-gray-50 border border-gray-200 rounded-xl px-4 py-2.5 text-gray-900 placeholder-gray-400 text-sm">
                        </div>
                    </div>

                    <div class="mt-4">
                        <label class="block text-xs font-semibold text-gray-700 mb-1.5">Bio <span class="text-red-500">*</span></label>
                        <textarea name="bio" rows="4" required maxlength="2000"
                                  placeholder="Tell clients about your skills and experience..."
                                  class="fld w-full bg-gray-50 border border-gray-200 rounded-xl px-4 py-2.5 text-gray-900 placeholder-gray-400 text-sm resize-none"><?= sanitize_string($profile['bio'] ?? '') ?></textarea>
                        <p class="text-[11px] text-gray-400 mt-1 text-right"><span id="bioCount">0</span>/2000</p>
                    </div>
                </div>

                <div class="bg-white rounded-2xl p-6 border border-gray-100 shadow-sm fade-in" style="animation-delay:.15s">
                    <h2 class="text-base font-bold text-gray-900 mb-5 flex items-center gap-2">
                        <div class="w-8 h-8 rounded-lg bg-violet-50 flex items-center justify-center">
                            <i class="fas fa-tags text-violet-500 text-sm"></i>
                        </div>
                        Skills
                        <span class="text-xs font-normal text-gray-400 ml-auto" id="skillCount"><?= count($selectedSkills) ?> selected</span>
                    </h2>

                    <div class="space-y-4">
                        <?php foreach ($allSkills as $category => $catSkills): ?>
                        <div>
                            <p class="text-xs font-semibold text-gray-400 uppercase tracking-wider mb-2"><?= sanitize_string($category) ?></p>
                            <div class="grid grid-cols-4 gap-4">
                                <?php foreach ($catSkills as $skill): ?>
                                <label class="skill-tag inline-flex items-center gap-1.5 px-3 py-1.5 bg-gray-50 text-gray-600 text-xs font-medium rounded-lg border border-gray-200 cursor-pointer <?= in_array($skill['id'], $selectedSkills) ? 'selected' : '' ?>"
                                       data-skill-id="<?= $skill['id'] ?>">
                                    <input type="checkbox" name="skills[]" value="<?= $skill['id'] ?>"
                                           class="hidden" <?= in_array($skill['id'], $selectedSkills) ? 'checked' : '' ?>>
                                    <i class="fas fa-check text-[10px] opacity-0 skill-check"></i>
                                    <?= sanitize_string($skill['skill_name']) ?>
                                </label>
                                <?php endforeach; ?>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>

                <div class="bg-white rounded-2xl p-6 border border-gray-100 shadow-sm fade-in" style="animation-delay:.2s">
                    <h2 class="text-base font-bold text-gray-900 mb-5 flex items-center gap-2">
                        <div class="w-8 h-8 rounded-lg bg-emerald-50 flex items-center justify-center">
                            <i class="fas fa-file-alt text-emerald-500 text-sm"></i>
                        </div>
                        Resume
                    </h2>

                    <div class="flex items-start gap-4">
                        <div class="flex-1">
                            <?php if (!empty($profile['resume_file'])): ?>
                            <div class="flex items-center gap-3 p-3 bg-gray-50 rounded-xl border border-gray-200 mb-3">
                                <div class="w-10 h-10 rounded-lg bg-red-50 flex items-center justify-center">
                                    <i class="fas fa-file-pdf text-red-500"></i>
                                </div>
                                <div class="flex-1 min-w-0">
                                    <p class="text-sm font-medium text-gray-900 truncate"><?= sanitize_string(basename($profile['resume_file'])) ?></p>
                                    <p class="text-[11px] text-gray-400">Current resume</p>
                                </div>
                                <a href="../assets/upload/resumes/<?= sanitize_string(basename($profile['resume_file'])) ?>"
                                   target="_blank" class="text-blue-500 hover:text-blue-700 text-xs font-medium">
                                    <i class="fas fa-download"></i>
                                </a>
                            </div>
                            <?php endif; ?>

                            <label class="block">
                                <span class="text-xs font-semibold text-gray-700 mb-1.5 block">
                                    <?= !empty($profile['resume_file']) ? 'Replace Resume' : 'Upload Resume' ?>
                                </span>
                                <input type="file" name="resume_file" accept="application/pdf" id="resumeInput"
                                       class="block w-full text-sm text-gray-500 file:mr-4 file:py-2 file:px-4 file:rounded-xl file:border-0 file:text-sm file:font-semibold file:bg-blue-50 file:text-blue-600 hover:file:bg-blue-100 file:cursor-pointer">
                                <p class="text-[11px] text-gray-400 mt-1">PDF only. Max 10MB.</p>
                            </label>
                        </div>
                    </div>
                </div>

                <div class="flex items-center justify-end gap-3 fade-in" style="animation-delay:.25s">
                    <a href="profile.php" class="px-5 py-2.5 border border-gray-200 hover:border-gray-300 text-gray-600 hover:text-gray-900 rounded-xl text-sm font-medium transition-all">
                        Cancel
                    </a>
                    <button type="submit" id="submitBtn"
                            class="btn-grad px-8 py-2.5 text-white font-bold rounded-xl text-sm shadow-lg shadow-blue-500/25 flex items-center gap-2">
                        <i class="fas fa-save"></i> Save Profile
                    </button>
                </div>
            </form>

<script>
document.querySelectorAll('.skill-tag').forEach(tag => {
    tag.addEventListener('click', function(e) {
        e.preventDefault();
        const checkbox = this.querySelector('input[type="checkbox"]');
        const checkIcon = this.querySelector('.skill-check');
        checkbox.checked = !checkbox.checked;
        this.classList.toggle('selected', checkbox.checked);
        if (checkIcon) checkIcon.style.opacity = checkbox.checked ? '1' : '0';
        updateSkillCount();
    });
});

function updateSkillCount() {
    const count = document.querySelectorAll('.skill-tag.selected').length;
    document.getElementById('skillCount').textContent = count + ' selected';
}

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
});

document.getElementById('resumeInput').addEventListener('change', function(e) {
    const file = e.target.files[0];
    if (!file) return;
    if (file.size > 10 * 1024 * 1024) {
        alert('File size must be under 10MB.');
        this.value = '';
        return;
    }
    if (file.type !== 'application/pdf') {
        alert('Only PDF files allowed.');
        this.value = '';
        return;
    }
});

const bioTextarea = document.querySelector('textarea[name="bio"]');
const bioCount = document.getElementById('bioCount');
function updateBioCount() { bioCount.textContent = bioTextarea.value.length; }
bioTextarea.addEventListener('input', updateBioCount);
updateBioCount();

document.getElementById('profileForm').addEventListener('submit', function(e) {
    const name = document.querySelector('input[name="name"]').value.trim();
    const title = document.querySelector('input[name="title"]').value.trim();
    const bio = document.querySelector('textarea[name="bio"]').value.trim();
    const hourlyRate = parseFloat(document.querySelector('input[name="hourly_rate"]').value);
    const yearsExp = parseInt(document.querySelector('input[name="years_of_experience"]').value);

    if (!name || !title || !bio) {
        alert('Name, Title, and Bio are required.');
        e.preventDefault();
        return;
    }
    if (hourlyRate < 0) {
        alert('Hourly rate cannot be negative.');
        e.preventDefault();
        return;
    }
    if (yearsExp < 0 || yearsExp > 50) {
        alert('Years of experience must be between 0 and 50.');
        e.preventDefault();
        return;
    }

    const btn = document.getElementById('submitBtn');
    btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Saving...';
    btn.disabled = true;
});
</script>
<?php require_once __DIR__ . '/../components/layout_end.php'; ?>
