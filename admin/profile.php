<?php

/**
 * Admin Profile Page
 * View and update admin profile, change password.
 */
require_once __DIR__ . '/../config/helpers.php';
require_once __DIR__ . '/../auth/auth.php';
require_role('admin');
require_once __DIR__ . '/../config/db.php';

$currentPage = 'profile';
$userId = $_SESSION['user_id'];

// ── Handle POST ───────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf_token()) {
        set_flash('error', 'Invalid security token. Please try again.');
        redirect('profile.php');
    }

    $action = $_POST['action'] ?? '';

    // ── Profile Update ─────────────────────────────────────────────
    if ($action === 'profile_update') {
        $name = trim($_POST['name'] ?? '');
        $phone = trim($_POST['phone'] ?? '');

        $stmt = $conn->prepare('SELECT profile_image FROM users WHERE id = ?');
        $stmt->bind_param('i', $userId);
        $stmt->execute();
        $currentProfileImage = $stmt->get_result()->fetch_assoc()['profile_image'];
        $stmt->close();

        $errors = [];

        if (empty($name)) {
            $errors[] = 'Name is required.';
        }
        if (mb_strlen($name) > 100) {
            $errors[] = 'Name must be under 100 characters.';
        }
        if (!empty($phone) && mb_strlen($phone) > 20) {
            $errors[] = 'Phone number must be under 20 characters.';
        }

        $newProfileImage = $currentProfileImage;
        if (isset($_FILES['profile_image']) && $_FILES['profile_image']['error'] === UPLOAD_ERR_OK) {
            $file = $_FILES['profile_image'];
            $allowedMimes = ['image/jpeg', 'image/png', 'image/webp'];
            $finfo = new finfo(FILEINFO_MIME_TYPE);
            $mimeType = $finfo->file($file['tmp_name']);

            if (!in_array($mimeType, $allowedMimes)) {
                $errors[] = 'Profile image must be JPG, PNG, or WebP.';
            } elseif ($file['size'] > 2 * 1024 * 1024) {
                $errors[] = 'Profile image must be under 2MB.';
            } else {
                $extMap = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp'];
                $ext = $extMap[$mimeType] ?? 'jpg';
                $newFilename = 'admin_' . $userId . '_' . bin2hex(random_bytes(8)) . '.' . $ext;
                $uploadDir = __DIR__ . '/../assets/upload/profiles/';

                if (!is_dir($uploadDir)) {
                    mkdir($uploadDir, 0755, true);
                }

                if (move_uploaded_file($file['tmp_name'], $uploadDir . $newFilename)) {
                    if ($currentProfileImage && file_exists(__DIR__ . '/../' . $currentProfileImage)) {
                        @unlink(__DIR__ . '/../' . $currentProfileImage);
                    }
                    $newProfileImage = 'assets/upload/profiles/' . $newFilename;
                } else {
                    $errors[] = 'Failed to upload profile image.';
                }
            }
        }

        if (!empty($errors)) {
            set_flash('error', implode(' ', $errors));
            redirect('profile.php');
        }

        $conn->begin_transaction();

        try {
            $update = $conn->prepare('UPDATE users SET name = ?, phone = ?, profile_image = ?, updated_at = NOW() WHERE id = ?');
            $update->bind_param('sssi', $name, $phone, $newProfileImage, $userId);
            $update->execute();
            $update->close();

            $conn->commit();

            $_SESSION['user_name'] = $name;
            $_SESSION['profile_image'] = $newProfileImage;

            set_flash('success', 'Profile updated successfully.');
            redirect('profile.php');
        } catch (Exception $e) {
            $conn->rollback();
            set_flash('error', 'Update failed: ' . $e->getMessage());
            redirect('profile.php');
        }
    }

    // ── Password Change ────────────────────────────────────────────
    if ($action === 'password_change') {
        $currentPassword = $_POST['current_password'] ?? '';
        $newPassword = $_POST['new_password'] ?? '';
        $confirmPassword = $_POST['confirm_password'] ?? '';

        $errors = [];

        if (empty($currentPassword)) {
            $errors[] = 'Current password is required.';
        }
        if (empty($newPassword)) {
            $errors[] = 'New password is required.';
        }
        if ($newPassword !== $confirmPassword) {
            $errors[] = 'New passwords do not match.';
        }

        $pwErrors = validate_password($newPassword);
        $errors = array_merge($errors, $pwErrors);

        if (empty($errors)) {
            $stmt = $conn->prepare('SELECT password FROM users WHERE id = ?');
            $stmt->bind_param('i', $userId);
            $stmt->execute();
            $row = $stmt->get_result()->fetch_assoc();
            $stmt->close();

            if (!$row || !password_verify($currentPassword, $row['password'])) {
                $errors[] = 'Current password is incorrect.';
            }
        }

        if (!empty($errors)) {
            set_flash('error', implode(' ', $errors));
            redirect('profile.php');
        }

        $hashedPassword = password_hash($newPassword, PASSWORD_DEFAULT);

        $conn->begin_transaction();

        try {
            $updatePw = $conn->prepare('UPDATE users SET password = ?, updated_at = NOW() WHERE id = ?');
            $updatePw->bind_param('si', $hashedPassword, $userId);
            $updatePw->execute();
            $updatePw->close();

            $conn->commit();

            set_flash('success', 'Password changed successfully.');
            redirect('profile.php');
        } catch (Exception $e) {
            $conn->rollback();
            set_flash('error', 'Password change failed: ' . $e->getMessage());
            redirect('profile.php');
        }
    }
}

// ── Fetch User Data ──────────────────────────────────────────────────
$stmt = $conn->prepare('SELECT id, name, email, phone, profile_image, role, status, created_at, updated_at FROM users WHERE id = ?');
$stmt->bind_param('i', $userId);
$stmt->execute();
$user = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$user) {
    set_flash('error', 'User not found.');
    redirect('dashboard.php');
}

// ── Profile Completion ───────────────────────────────────────────────
$completionFields = [
    'name' => !empty($profileData['name']),
    'email' => !empty($profileData['email']),
    'phone' => !empty($profileData['phone']),
    'profile_image' => !empty($profileData['profile_image']) && $profileData['profile_image'] !== 'assets/upload/profile.png',
];
$completedCount = count(array_filter($completionFields));
$totalCount = count($completionFields);
$completionPercent = $totalCount > 0 ? round(($completedCount / $totalCount) * 100) : 0;

$conn->close();

$profileData = $user;

$navItems = [
    ['key' => 'dashboard', 'label' => 'Dashboard', 'url' => 'dashboard.php', 'icon' => 'fa-th-large'],
    ['key' => 'users', 'label' => 'Users', 'url' => 'users.php', 'icon' => 'fa-users'],
    ['key' => 'jobs', 'label' => 'Jobs', 'url' => 'jobs.php', 'icon' => 'fa-briefcase'],
    ['key' => 'payments', 'label' => 'Payments', 'url' => 'payments.php', 'icon' => 'fa-credit-card'],
    ['key' => 'fraud', 'label' => 'Fraud', 'url' => 'fraud_detection.php', 'icon' => 'fa-shield-halved'],
    ['key' => 'matching', 'label' => 'AI Matching', 'url' => 'ai_matching.php', 'icon' => 'fa-brain'],
    ['key' => 'settings', 'label' => 'Settings', 'url' => 'settings.php', 'icon' => 'fa-cog'],
];
$pageTitle = 'Admin Profile';
$pageSubtitle = 'Manage your account settings';
$activePage = 'settings';
$user = ['name' => $profileData['name'], 'profile_image' => $profileData['profile_image'] ?? null];
$unreadCount = 0;
$profileLink = 'profile.php';
require_once __DIR__ . '/../components/layout_start.php';
?>
    <style>
    .btn-grad{background:linear-gradient(135deg,#2563eb,#0ea5e9);transition:all .25s}
    .btn-grad:hover{opacity:.88;transform:translateY(-2px);box-shadow:0 8px 25px rgba(37,99,235,.2)}
    .fld{transition:border-color .2s,box-shadow .2s}
    .fld:focus{outline:none;border-color:#2563eb;box-shadow:0 0 0 3px rgba(37,99,235,.15)}
    .pw-bar{transition:width .3s ease,background-color .3s ease}
    </style>



                <?php display_flash('success'); ?>
                <?php display_flash('error'); ?>

                <!-- ═══ PROFILE HEADER CARD ═══════════════════════════════ -->
                <div class="bg-white rounded-2xl border border-gray-100 shadow-sm overflow-hidden fade-in">
                    <div class="h-28 bg-gradient-to-r from-blue-600 via-blue-500 to-cyan-400 relative"></div>
                    <div class="px-6 pb-6 relative">
                        <div class="flex flex-col sm:flex-row items-end sm:items-end gap-4 -mt-12">
                            <img id="headerAvatar"
                                 src="<?= sanitize_string(get_profile_image($profileData['profile_image'])) ?>"
                                 class="w-24 h-24 rounded-2xl object-cover border-4 border-white shadow-lg flex-shrink-0">
                            <div class="flex-1 pb-1">
                                <h2 class="text-xl font-bold text-gray-900"><?= sanitize_string($profileData['name']) ?></h2>
                                <p class="text-sm text-gray-500 flex items-center gap-2">
                                    <span class="inline-block px-2 py-0.5 rounded text-[10px] font-semibold border bg-purple-50 text-purple-600 border-purple-200">Admin</span>
                                    <span class="text-gray-300">|</span>
                                    <span class="inline-block px-2 py-0.5 rounded text-[10px] font-semibold border bg-emerald-50 text-emerald-600 border-emerald-200"><?= sanitize_string(ucfirst($profileData['status'])) ?></span>
                                </p>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="grid grid-cols-1 2xl:grid-cols-3 gap-6">

                    <!-- ═══ LEFT: PROFILE EDIT ══════════════════════════════ -->
                    <div class="2xl:col-span-2 space-y-6">

                        <!-- Profile Information -->
                        <form action="profile.php" method="POST" enctype="multipart/form-data" id="profileForm" class="bg-white rounded-2xl p-6 border border-gray-100 shadow-sm fade-in" style="animation-delay:.05s">
                            <?= csrf_field() ?>
                            <input type="hidden" name="action" value="profile_update">

                            <h2 class="text-base font-bold text-gray-900 mb-5 flex items-center gap-2">
                                <div class="w-8 h-8 rounded-lg bg-blue-50 flex items-center justify-center">
                                    <i class="fas fa-user text-blue-500 text-sm"></i>
                                </div>
                                Profile Information
                            </h2>

                            <div class="flex flex-col sm:flex-row items-start gap-6">
                                <div class="flex flex-col items-center">
                                    <img id="avatarPreview"
                                         src="<?= sanitize_string(get_profile_image($profileData['profile_image'])) ?>"
                                         class="w-28 h-28 rounded-full object-cover mb-3 border-4 border-gray-100 shadow-sm">
                                    <label class="text-xs text-blue-600 hover:text-blue-700 font-medium cursor-pointer">
                                        <i class="fas fa-camera mr-1"></i> Change Photo
                                        <input type="file" name="profile_image" id="profileImageInput"
                                               accept="image/jpeg,image/png,image/webp" class="hidden">
                                    </label>
                                    <p class="text-[10px] text-gray-400 mt-1">JPG, PNG, WebP. Max 2MB.</p>
                                </div>

                                <div class="flex-1 grid sm:grid-cols-2 gap-4">
                                    <div>
                                        <label class="block text-xs font-semibold text-gray-700 mb-1.5">Full Name <span class="text-red-500">*</span></label>
                                        <input type="text" name="name" required maxlength="100"
                                               value="<?= sanitize_string($profileData['name']) ?>"
                                               class="fld w-full bg-gray-50 border border-gray-200 rounded-xl px-4 py-2.5 text-gray-900 text-sm">
                                    </div>
                                    <div>
                                        <label class="block text-xs font-semibold text-gray-700 mb-1.5">Email</label>
                                         <input type="email" value="<?= sanitize_string($profileData['email']) ?>" readonly
                                               class="w-full bg-gray-50 border border-gray-200 rounded-xl px-4 py-2.5 text-gray-500 text-sm cursor-not-allowed">
                                    </div>
                                    <div class="sm:col-span-2">
                                        <label class="block text-xs font-semibold text-gray-700 mb-1.5">Phone</label>
                                        <input type="tel" name="phone" maxlength="20"
                                               value="<?= sanitize_string($profileData['phone'] ?? '') ?>"
                                               placeholder="+1 (555) 123-4567"
                                               class="fld w-full bg-gray-50 border border-gray-200 rounded-xl px-4 py-2.5 text-gray-900 placeholder-gray-400 text-sm">
                                    </div>
                                </div>
                            </div>

                            <div class="flex items-center justify-end gap-3 mt-6 pt-5 border-t border-gray-100">
                                <button type="submit" id="profileSubmitBtn"
                                        class="btn-grad px-6 py-2.5 text-white font-bold rounded-xl text-sm shadow-lg shadow-blue-500/25 flex items-center gap-2">
                                    <i class="fas fa-save"></i> Save Changes
                                </button>
                            </div>
                        </form>

                        <!-- Password Change -->
                        <form action="profile.php" method="POST" id="passwordForm" class="bg-white rounded-2xl p-6 border border-gray-100 shadow-sm fade-in" style="animation-delay:.1s">
                            <?= csrf_field() ?>
                            <input type="hidden" name="action" value="password_change">

                            <h2 class="text-base font-bold text-gray-900 mb-5 flex items-center gap-2">
                                <div class="w-8 h-8 rounded-lg bg-amber-50 flex items-center justify-center">
                                    <i class="fas fa-lock text-amber-500 text-sm"></i>
                                </div>
                                Change Password
                            </h2>

                            <div class="grid sm:grid-cols-2 gap-4">
                                <div class="sm:col-span-2">
                                    <label class="block text-xs font-semibold text-gray-700 mb-1.5">Current Password <span class="text-red-500">*</span></label>
                                    <div class="relative">
                                        <input type="password" name="current_password" id="currentPassword" required
                                               class="fld w-full bg-gray-50 border border-gray-200 rounded-xl px-4 py-2.5 pr-10 text-gray-900 text-sm"
                                               placeholder="Enter current password">
                                        <button type="button" onclick="togglePwVisibility('currentPassword', this)"
                                                class="absolute right-3 top-1/2 -translate-y-1/2 text-gray-400 hover:text-gray-600">
                                            <i class="fas fa-eye text-sm"></i>
                                        </button>
                                    </div>
                                </div>
                                <div>
                                    <label class="block text-xs font-semibold text-gray-700 mb-1.5">New Password <span class="text-red-500">*</span></label>
                                    <div class="relative">
                                        <input type="password" name="new_password" id="newPassword" required
                                               class="fld w-full bg-gray-50 border border-gray-200 rounded-xl px-4 py-2.5 pr-10 text-gray-900 text-sm"
                                               placeholder="Enter new password">
                                        <button type="button" onclick="togglePwVisibility('newPassword', this)"
                                                class="absolute right-3 top-1/2 -translate-y-1/2 text-gray-400 hover:text-gray-600">
                                            <i class="fas fa-eye text-sm"></i>
                                        </button>
                                    </div>
                                    <!-- Strength Indicator -->
                                    <div class="mt-2">
                                        <div class="flex gap-1.5 mb-1">
                                            <div id="str1" class="h-1.5 flex-1 rounded-full bg-gray-200 overflow-hidden"><div class="pw-bar h-full w-0 rounded-full"></div></div>
                                            <div id="str2" class="h-1.5 flex-1 rounded-full bg-gray-200 overflow-hidden"><div class="pw-bar h-full w-0 rounded-full"></div></div>
                                            <div id="str3" class="h-1.5 flex-1 rounded-full bg-gray-200 overflow-hidden"><div class="pw-bar h-full w-0 rounded-full"></div></div>
                                            <div id="str4" class="h-1.5 flex-1 rounded-full bg-gray-200 overflow-hidden"><div class="pw-bar h-full w-0 rounded-full"></div></div>
                                        </div>
                                        <p id="strengthText" class="text-[11px] text-gray-400 font-medium"></p>
                                    </div>
                                </div>
                                <div>
                                    <label class="block text-xs font-semibold text-gray-700 mb-1.5">Confirm New Password <span class="text-red-500">*</span></label>
                                    <div class="relative">
                                        <input type="password" name="confirm_password" id="confirmPassword" required
                                               class="fld w-full bg-gray-50 border border-gray-200 rounded-xl px-4 py-2.5 pr-10 text-gray-900 text-sm"
                                               placeholder="Confirm new password">
                                        <button type="button" onclick="togglePwVisibility('confirmPassword', this)"
                                                class="absolute right-3 top-1/2 -translate-y-1/2 text-gray-400 hover:text-gray-600">
                                            <i class="fas fa-eye text-sm"></i>
                                        </button>
                                    </div>
                                    <p id="pwMatch" class="text-[11px] mt-1 font-medium hidden"></p>
                                </div>
                            </div>

                            <div class="flex items-center justify-end gap-3 mt-6 pt-5 border-t border-gray-100">
                                <button type="submit" id="pwSubmitBtn"
                                        class="btn-grad px-6 py-2.5 text-white font-bold rounded-xl text-sm shadow-lg shadow-blue-500/25 flex items-center gap-2">
                                    <i class="fas fa-key"></i> Update Password
                                </button>
                            </div>
                        </form>
                    </div>

                    <!-- ═══ RIGHT: SIDEBAR INFO ═════════════════════════════ -->
                    <div class="space-y-6">

                        <!-- Profile Completion -->
                        <div class="bg-white rounded-2xl p-6 border border-gray-100 shadow-sm fade-in" style="animation-delay:.15s">
                            <h3 class="text-sm font-bold text-gray-900 mb-4 flex items-center gap-2">
                                <div class="w-8 h-8 rounded-lg bg-emerald-50 flex items-center justify-center">
                                    <i class="fas fa-chart-line text-emerald-500 text-sm"></i>
                                </div>
                                Profile Completion
                            </h3>

                            <div class="relative mb-3">
                                <div class="w-full h-3 bg-gray-100 rounded-full overflow-hidden">
                                    <div class="h-full rounded-full transition-all duration-500 <?= $completionPercent >= 75 ? 'bg-gradient-to-r from-emerald-500 to-green-400' : ($completionPercent >= 50 ? 'bg-gradient-to-r from-amber-500 to-yellow-400' : 'bg-gradient-to-r from-red-500 to-orange-400') ?>"
                                         style="width: <?= $completionPercent ?>%"></div>
                                </div>
                            </div>
                            <p class="text-center text-2xl font-black <?= $completionPercent >= 75 ? 'text-emerald-600' : ($completionPercent >= 50 ? 'text-amber-600' : 'text-red-600') ?>"><?= $completionPercent ?>%</p>

                            <div class="mt-4 space-y-2">
                                <?php foreach ($completionFields as $field => $done): ?>
                                <div class="flex items-center gap-2 text-xs">
                                    <i class="fas <?= $done ? 'fa-check-circle text-emerald-500' : 'fa-circle text-gray-300' ?> text-[10px]"></i>
                                    <span class="<?= $done ? 'text-gray-700' : 'text-gray-400' ?>"><?= ucfirst(str_replace('_', ' ', $field)) ?></span>
                                </div>
                                <?php endforeach; ?>
                            </div>
                        </div>

                        <!-- Activity Information -->
                        <div class="bg-white rounded-2xl p-6 border border-gray-100 shadow-sm fade-in" style="animation-delay:.2s">
                            <h3 class="text-sm font-bold text-gray-900 mb-4 flex items-center gap-2">
                                <div class="w-8 h-8 rounded-lg bg-violet-50 flex items-center justify-center">
                                    <i class="fas fa-clock text-violet-500 text-sm"></i>
                                </div>
                                Activity Information
                            </h3>

                            <div class="space-y-4">
                                <div class="flex items-start gap-3">
                                    <div class="w-9 h-9 rounded-xl bg-blue-50 flex items-center justify-center flex-shrink-0">
                                        <i class="fas fa-calendar-plus text-blue-500 text-xs"></i>
                                    </div>
                                    <div>
                                        <p class="text-[11px] text-gray-400 font-medium uppercase tracking-wider">Created</p>
                                         <p class="text-sm font-semibold text-gray-900"><?= date('M j, Y', strtotime($profileData['created_at'])) ?></p>
                                         <p class="text-[11px] text-gray-400"><?= time_ago($profileData['created_at']) ?></p>
                                    </div>
                                </div>

                                <div class="border-t border-gray-100"></div>

                                <div class="flex items-start gap-3">
                                    <div class="w-9 h-9 rounded-xl bg-cyan-50 flex items-center justify-center flex-shrink-0">
                                        <i class="fas fa-sync-alt text-cyan-500 text-xs"></i>
                                    </div>
                                    <div>
                                        <p class="text-[11px] text-gray-400 font-medium uppercase tracking-wider">Last Updated</p>
                                         <p class="text-sm font-semibold text-gray-900"><?= date('M j, Y', strtotime($profileData['updated_at'])) ?></p>
                                         <p class="text-[11px] text-gray-400"><?= time_ago($profileData['updated_at']) ?></p>
                                    </div>
                                </div>

                                <div class="border-t border-gray-100"></div>

                                <div class="flex items-start gap-3">
                                    <div class="w-9 h-9 rounded-xl bg-amber-50 flex items-center justify-center flex-shrink-0">
                                        <i class="fas fa-shield-alt text-amber-500 text-xs"></i>
                                    </div>
                                    <div>
                                        <p class="text-[11px] text-gray-400 font-medium uppercase tracking-wider">Account Type</p>
                                        <p class="text-sm font-semibold text-gray-900">Platform Administrator</p>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Quick Stats -->
                        <div class="bg-white rounded-2xl p-6 border border-gray-100 shadow-sm fade-in" style="animation-delay:.25s">
                            <h3 class="text-sm font-bold text-gray-900 mb-4 flex items-center gap-2">
                                <div class="w-8 h-8 rounded-lg bg-rose-50 flex items-center justify-center">
                                    <i class="fas fa-info-circle text-rose-500 text-sm"></i>
                                </div>
                                Account Details
                            </h3>

                            <div class="space-y-3 text-sm">
                                <div class="flex justify-between">
                                    <span class="text-gray-400">User ID</span>
                                     <span class="font-semibold text-gray-900">#<?= (int) $profileData['id'] ?></span>
                                </div>
                                <div class="flex justify-between">
                                    <span class="text-gray-400">Role</span>
                                    <span class="font-semibold text-gray-900">Admin</span>
                                </div>
                                <div class="flex justify-between">
                                    <span class="text-gray-400">Status</span>
                                     <span class="inline-block px-2 py-0.5 rounded text-[10px] font-semibold border bg-emerald-50 text-emerald-600 border-emerald-200"><?= sanitize_string(ucfirst($profileData['status'])) ?></span>
                                </div>
                            </div>
                        </div>

                    </div>
                </div>


    <script>
    // ── Profile Image Preview ──────────────────────────────────────
    document.getElementById('profileImageInput').addEventListener('change', function(e) {
        const file = e.target.files[0];
        if (!file) return;
        if (file.size > 2 * 1024 * 1024) {
            alert('File size must be under 2MB.');
            this.value = '';
            return;
        }
        if (!['image/jpeg', 'image/png', 'image/webp'].includes(file.type)) {
            alert('Only JPG, PNG, WebP allowed.');
            this.value = '';
            return;
        }
        const reader = new FileReader();
        reader.onload = function(ev) {
            document.getElementById('avatarPreview').src = ev.target.result;
            document.getElementById('headerAvatar').src = ev.target.result;
        };
        reader.readAsDataURL(file);
    });

    // ── Password Visibility Toggle ─────────────────────────────────
    function togglePwVisibility(inputId, btn) {
        const input = document.getElementById(inputId);
        const icon = btn.querySelector('i');
        if (input.type === 'password') {
            input.type = 'text';
            icon.classList.replace('fa-eye', 'fa-eye-slash');
        } else {
            input.type = 'password';
            icon.classList.replace('fa-eye-slash', 'fa-eye');
        }
    }

    // ── Password Strength Indicator ────────────────────────────────
    const newPwInput = document.getElementById('newPassword');
    const confirmPwInput = document.getElementById('confirmPassword');
    const strengthText = document.getElementById('strengthText');
    const pwMatch = document.getElementById('pwMatch');

    function getPasswordStrength(pw) {
        let score = 0;
        if (pw.length >= 8) score++;
        if (/[A-Z]/.test(pw) && /[a-z]/.test(pw)) score++;
        if (/[0-9]/.test(pw)) score++;
        if (/[^A-Za-z0-9]/.test(pw)) score++;
        return score;
    }

    newPwInput.addEventListener('input', function() {
        const pw = this.value;
        const strength = getPasswordStrength(pw);
        const colors = ['#ef4444', '#f97316', '#eab308', '#22c55e'];
        const labels = ['', 'Weak', 'Fair', 'Good', 'Strong'];
        const labelColors = ['', 'text-red-500', 'text-orange-500', 'text-yellow-500', 'text-emerald-500'];

        for (let i = 1; i <= 4; i++) {
            const bar = document.querySelector('#str' + i + ' .pw-bar');
            if (i <= strength) {
                bar.style.width = '100%';
                bar.style.backgroundColor = colors[strength - 1];
            } else {
                bar.style.width = '0%';
            }
        }

        strengthText.textContent = pw.length > 0 ? labels[strength] || 'Too short' : '';
        strengthText.className = 'text-[11px] font-medium ' + (labelColors[strength] || 'text-gray-400');

        checkPwMatch();
    });

    confirmPwInput.addEventListener('input', checkPwMatch);

    function checkPwMatch() {
        const pw = newPwInput.value;
        const confirm = confirmPwInput.value;
        if (confirm.length === 0) {
            pwMatch.classList.add('hidden');
            return;
        }
        pwMatch.classList.remove('hidden');
        if (pw === confirm) {
            pwMatch.textContent = 'Passwords match';
            pwMatch.className = 'text-[11px] mt-1 font-medium text-emerald-500';
        } else {
            pwMatch.textContent = 'Passwords do not match';
            pwMatch.className = 'text-[11px] mt-1 font-medium text-red-500';
        }
    }

    // ── Profile Form Submit ────────────────────────────────────────
    document.getElementById('profileForm').addEventListener('submit', function(e) {
        const name = document.querySelector('input[name="name"]').value.trim();
        if (!name) {
            alert('Name is required.');
            e.preventDefault();
            return;
        }
        const btn = document.getElementById('profileSubmitBtn');
        btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Saving...';
        btn.disabled = true;
    });

    // ── Password Form Submit ───────────────────────────────────────
    document.getElementById('passwordForm').addEventListener('submit', function(e) {
        const current = document.getElementById('currentPassword').value;
        const newPw = document.getElementById('newPassword').value;
        const confirm = document.getElementById('confirmPassword').value;

        if (!current) {
            alert('Current password is required.');
            e.preventDefault();
            return;
        }
        if (!newPw) {
            alert('New password is required.');
            e.preventDefault();
            return;
        }
        if (newPw !== confirm) {
            alert('New passwords do not match.');
            e.preventDefault();
            return;
        }
        if (newPw.length < 8) {
            alert('Password must be at least 8 characters.');
            e.preventDefault();
            return;
        }

        const btn = document.getElementById('pwSubmitBtn');
        btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Updating...';
        btn.disabled = true;
    });
    </script>
<?php require_once __DIR__ . '/../components/layout_end.php'; ?>