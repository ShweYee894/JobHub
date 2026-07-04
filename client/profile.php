<?php

/**
 * Client Profile Page
 * Edit personal info, company info, profile/company images, and change password.
 */

// ── Auth & Config ─────────────────────────────────────────────────────
require_once __DIR__ . '/../auth/auth.php';
require_role('client');
require_once __DIR__ . '/../config/db.php';

$currentPage = 'profile';
$userId = $_SESSION['user_id'];
$errors = [];
$success = false;

// ── Upload Directory ──────────────────────────────────────────────────
$uploadDir = __DIR__ . '/../assets/upload/profiles/';
if (!is_dir($uploadDir)) {
    mkdir($uploadDir, 0755, true);
}

// ── Fetch User & Client Data ──────────────────────────────────────────
$stmt = $conn->prepare('SELECT id, name, email, phone, profile_image, wallet_balance FROM users WHERE id = ?');
$stmt->bind_param('i', $userId);
$stmt->execute();
$user = $stmt->get_result()->fetch_assoc();
$stmt->close();

$stmt = $conn->prepare('SELECT id, company_logo, company_name, company_website, industry, company_size, total_spent FROM clients WHERE client_id = ?');
$stmt->bind_param('i', $userId);
$stmt->execute();
$client = $stmt->get_result()->fetch_assoc();
$stmt->close();

// ── Statistics ────────────────────────────────────────────────────────
$stmt = $conn->prepare('SELECT COUNT(*) AS cnt FROM jobs WHERE client_id = ?');
$stmt->bind_param('i', $userId);
$stmt->execute();
$statsJobsPosted = (int) $stmt->get_result()->fetch_assoc()['cnt'];
$stmt->close();

$stmt = $conn->prepare('SELECT COUNT(*) AS cnt FROM contracts WHERE client_id = ?');
$stmt->bind_param('i', $userId);
$stmt->execute();
$statsContracts = (int) $stmt->get_result()->fetch_assoc()['cnt'];
$stmt->close();

$stmt = $conn->prepare("SELECT COUNT(*) AS cnt FROM contracts WHERE client_id = ? AND status = 'completed'");
$stmt->bind_param('i', $userId);
$stmt->execute();
$statsCompleted = (int) $stmt->get_result()->fetch_assoc()['cnt'];
$stmt->close();

$stmt = $conn->prepare('SELECT COUNT(*) AS cnt FROM reviews WHERE reviewee_id = ?');
$stmt->bind_param('i', $userId);
$stmt->execute();
$statsReviews = (int) $stmt->get_result()->fetch_assoc()['cnt'];
$stmt->close();

// ── Helper: delete old file ──────────────────────────────────────────
function deleteOldImage(?string $filename): void
{
    if ($filename) {
        $path = __DIR__ . '/../assets/upload/profiles/' . $filename;
        if (file_exists($path)) {
            unlink($path);
        }
    }
}

// ── Helper: validate & move uploaded image ───────────────────────────
function handleImageUpload(array $file, string $prefix): ?string
{
    $allowed = ['image/jpeg', 'image/jpg', 'image/png', 'image/webp'];
    $maxSize = 2 * 1024 * 1024;

    if ($file['error'] !== UPLOAD_ERR_OK) {
        return null;
    }

    if (!in_array($file['type'], $allowed, true)) {
        global $errors;
        $errors[] = str_replace('_', ' ', $prefix) . ' must be JPG, JPEG, PNG, or WebP.';
        return null;
    }

    if ($file['size'] > $maxSize) {
        global $errors;
        $errors[] = str_replace('_', ' ', $prefix) . ' must be less than 2MB.';
        return null;
    }

    $ext = pathinfo($file['name'], PATHINFO_EXTENSION);
    $name = $prefix . '_' . $userId . '_' . time() . '.' . strtolower($ext);
    $dest = __DIR__ . '/../assets/upload/profiles/' . $name;

    if (move_uploaded_file($file['tmp_name'], $dest)) {
        return $name;
    }

    global $errors;
    $errors[] = 'Failed to upload ' . str_replace('_', ' ', $prefix) . '.';
    return null;
}

// ── Handle POST ───────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf_token()) {
        $errors[] = 'Invalid security token. Please try again.';
    }

    $action = $_POST['action'] ?? '';

    // ── Profile Update ────────────────────────────────────────────────
    if ($action === 'update_profile' && empty($errors)) {
        $name = trim($_POST['name'] ?? '');
        $phone = trim($_POST['phone'] ?? '');
        $companyName = trim($_POST['company_name'] ?? '');
        $industry = trim($_POST['industry'] ?? '');
        $companyWebsite = trim($_POST['company_website'] ?? '');
        $companySize = $_POST['company_size'] ?? '';

        if (empty($name)) {
            $errors[] = 'Name is required.';
        }
        if (!empty($companyWebsite) && !filter_var($companyWebsite, FILTER_VALIDATE_URL)) {
            $errors[] = 'Invalid company website URL.';
        }
        $allowedSizes = ['Startup', 'Small', 'Medium', 'Large'];
        if (!empty($companySize) && !in_array($companySize, $allowedSizes, true)) {
            $errors[] = 'Invalid company size.';
        }

        // Handle profile image upload
        $newProfileImage = null;
        if (!empty($_FILES['profile_image']['name'])) {
            $newProfileImage = handleImageUpload($_FILES['profile_image'], 'profile');
        }

        // Handle company logo upload
        $newCompanyLogo = null;
        if (!empty($_FILES['company_logo']['name'])) {
            $newCompanyLogo = handleImageUpload($_FILES['company_logo'], 'logo');
        }

        if (empty($errors)) {
            $conn->begin_transaction();
            try {
                // Update users table
                if ($newProfileImage) {
                    deleteOldImage($user['profile_image']);
                    $uStmt = $conn->prepare('UPDATE users SET name = ?, phone = ?, profile_image = ?, updated_at = NOW() WHERE id = ?');
                    $uStmt->bind_param('sssi', $name, $phone, $newProfileImage, $userId);
                } else {
                    $uStmt = $conn->prepare('UPDATE users SET name = ?, phone = ?, updated_at = NOW() WHERE id = ?');
                    $uStmt->bind_param('ssi', $name, $phone, $userId);
                }
                $uStmt->execute();
                $uStmt->close();

                // Update clients table
                if ($newCompanyLogo) {
                    deleteOldImage($client['company_logo'] ?? null);
                    $cStmt = $conn->prepare('UPDATE clients SET company_name = ?, company_website = ?, industry = ?, company_size = ?, company_logo = ?, updated_at = NOW() WHERE client_id = ?');
                    $cStmt->bind_param('sssssi', $companyName, $companyWebsite, $industry, $companySize, $newCompanyLogo, $userId);
                } else {
                    $cStmt = $conn->prepare('UPDATE clients SET company_name = ?, company_website = ?, industry = ?, company_size = ?, updated_at = NOW() WHERE client_id = ?');
                    $cStmt->bind_param('ssssi', $companyName, $companyWebsite, $industry, $companySize, $userId);
                }
                $cStmt->execute();
                $cStmt->close();

                $conn->commit();

                // Refresh session
                $_SESSION['user_name'] = $name;
                if ($newProfileImage) {
                    $_SESSION['profile_image'] = $newProfileImage;
                }

                set_flash('success', 'Profile updated successfully.');
                redirect('profile.php');
            } catch (Exception $e) {
                $conn->rollback();
                $errors[] = 'Failed to update profile. Please try again.';
            }
        }
    }

    // ── Password Change ───────────────────────────────────────────────
    if ($action === 'change_password' && empty($errors)) {
        $currentPassword = $_POST['current_password'] ?? '';
        $newPassword = $_POST['new_password'] ?? '';
        $confirmPassword = $_POST['confirm_password'] ?? '';

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
            // Verify current password
            $pwStmt = $conn->prepare('SELECT password FROM users WHERE id = ?');
            $pwStmt->bind_param('i', $userId);
            $pwStmt->execute();
            $hash = $pwStmt->get_result()->fetch_assoc()['password'];
            $pwStmt->close();

            if (!password_verify($currentPassword, $hash)) {
                $errors[] = 'Current password is incorrect.';
            } else {
                $newHash = password_hash($newPassword, PASSWORD_DEFAULT);
                $upStmt = $conn->prepare('UPDATE users SET password = ?, updated_at = NOW() WHERE id = ?');
                $upStmt->bind_param('si', $newHash, $userId);
                $upStmt->execute();
                $upStmt->close();

                set_flash('success', 'Password changed successfully.');
                redirect('profile.php');
            }
        }
    }
}

$conn->close();

$profileData = $user;

$navItems = [
    ['key' => 'dashboard', 'label' => 'Dashboard', 'url' => 'dashboard.php', 'icon' => 'fa-th-large'],
    ['key' => 'my_jobs', 'label' => 'My Jobs', 'url' => 'my_jobs.php', 'icon' => 'fa-briefcase'],
    ['key' => 'post_job', 'label' => 'Post a Job', 'url' => 'post_job.php', 'icon' => 'fa-plus-circle'],
    ['key' => 'proposals', 'label' => 'Proposals', 'url' => 'proposals.php', 'icon' => 'fa-file-alt'],
    ['key' => 'recommended_freelancers', 'label' => 'Find Freelancers', 'url' => 'recommended_freelancers.php', 'icon' => 'fa-search'],
    ['key' => 'contracts', 'label' => 'Contracts', 'url' => 'contracts.php', 'icon' => 'fa-handshake'],
    ['key' => 'reviews', 'label' => 'Reviews', 'url' => 'reviews.php', 'icon' => 'fa-star'],
    ['key' => 'payment_history', 'label' => 'Payments', 'url' => 'payment_history.php', 'icon' => 'fa-credit-card'],
    ['key' => 'messages', 'label' => 'Messages', 'url' => 'messages.php', 'icon' => 'fa-comment-dots'],
];
$pageTitle = 'My Profile';
$pageSubtitle = 'Manage your personal and company information';
$activePage = 'profile';
$user = ['name' => $profileData['name'] ?? 'Client', 'profile_image' => $profileData['profile_image'] ?? null];
$unreadCount = get_unread_message_count($userId, 'client');
$profileLink = 'profile.php';
require_once __DIR__ . '/../components/layout_start.php';
?>

    <?php display_flash('success'); ?>
    <?php display_flash('error'); ?>
    <?php display_flash('info'); ?>

    <?php if (!empty($errors)): ?>
        <div class="bg-red-50 text-red-800 border border-red-200 rounded-xl p-4">
            <div class="flex items-center gap-2 mb-2">
                <i class="fas fa-exclamation-circle"></i>
                <span class="font-semibold text-sm">Please fix the following errors:</span>
            </div>
            <ul class="list-disc list-inside text-sm space-y-1">
                <?php foreach ($errors as $err): ?>
                    <li><?= sanitize_string($err) ?></li>
                <?php endforeach; ?>
            </ul>
        </div>
    <?php endif; ?>

    <!-- ═══ PROFILE HEADER ═════════════════════════════════════════════ -->
    <div class="bg-white rounded-2xl border border-gray-100 shadow-sm overflow-hidden fade-in" style="animation-delay:.2s">
        <div class="h-32 bg-gradient-to-r from-blue-600 via-blue-500 to-cyan-400"></div>
        <div class="px-6 pb-6">
            <div class="flex flex-col sm:flex-row items-center sm:items-end gap-4 -mt-12">
                <div class="relative group">
                    <img id="profilePreview"
                         src="<?= get_profile_image($profileData['profile_image']) ?>"
                         class="w-24 h-24 rounded-2xl border-4 border-white object-cover shadow-lg"
                         alt="Profile">
                    <label for="profileImageInput" class="absolute inset-0 rounded-2xl bg-black/40 flex items-center justify-center opacity-0 group-hover:opacity-100 transition-opacity cursor-pointer">
                        <i class="fas fa-camera text-white text-lg"></i>
                    </label>
                </div>
                <div class="sm:flex-1 text-center sm:text-left sm:pb-1">
                    <h2 class="text-xl font-bold text-gray-900"><?= sanitize_string($profileData['name']) ?></h2>
                    <p class="text-sm text-gray-400"><?= sanitize_string($profileData['email']) ?></p>
                </div>
                <div class="flex items-center gap-2 pb-1">
                    <span class="px-3 py-1 rounded-lg bg-blue-50 text-blue-600 text-xs font-semibold border border-blue-200">
                        <i class="fas fa-crown mr-1"></i>Client
                    </span>
                    <span class="px-3 py-1 rounded-lg bg-emerald-50 text-emerald-600 text-xs font-semibold border border-emerald-200">
                        <?= format_currency((float) $profileData['wallet_balance']) ?> wallet
                    </span>
                </div>
            </div>
        </div>
    </div>

    <!-- ═══ TWO-COLUMN LAYOUT ══════════════════════════════════════════ -->
    <form method="POST" enctype="multipart/form-data" class="grid grid-cols-1 2xl:grid-cols-2 gap-6">
        <?= csrf_field() ?>
        <input type="hidden" name="action" value="update_profile">

        <!-- ── Personal Information ─────────────────────────────────── -->
        <div class="bg-white rounded-2xl border border-gray-100 shadow-sm fade-in" style="animation-delay:.25s">
            <div class="p-6 border-b border-gray-100">
                <h3 class="text-base font-bold text-gray-900 flex items-center gap-2">
                    <i class="fas fa-user text-blue-500"></i> Personal Information
                </h3>
            </div>
            <div class="p-6 space-y-5">
                <input type="file" id="profileImageInput" name="profile_image" accept="image/jpeg,image/jpg,image/png,image/webp" class="hidden" onchange="previewImage(this, 'profilePreview')">

                <div>
                    <label class="block text-sm font-semibold text-gray-700 mb-1.5">Full Name <span class="text-red-400">*</span></label>
                    <div class="relative">
                        <span class="absolute inset-y-0 left-0 flex items-center pl-3 text-gray-400"><i class="fas fa-user text-sm"></i></span>
                        <input type="text" name="name" value="<?= sanitize_string($profileData['name']) ?>" required
                               class="w-full pl-10 pr-4 py-2.5 rounded-xl border border-gray-200 bg-gray-50 text-sm focus:bg-white focus:border-blue-400 focus:ring-2 focus:ring-blue-100 outline-none transition-all">
                    </div>
                </div>

                <div>
                    <label class="block text-sm font-semibold text-gray-700 mb-1.5">Email Address</label>
                    <div class="relative">
                        <span class="absolute inset-y-0 left-0 flex items-center pl-3 text-gray-400"><i class="fas fa-envelope text-sm"></i></span>
                        <input type="email" value="<?= sanitize_string($profileData['email']) ?>" readonly
                               class="w-full pl-10 pr-4 py-2.5 rounded-xl border border-gray-200 bg-gray-100 text-sm text-gray-500 cursor-not-allowed">
                    </div>
                </div>

                <div>
                    <label class="block text-sm font-semibold text-gray-700 mb-1.5">Phone Number</label>
                    <div class="relative">
                        <span class="absolute inset-y-0 left-0 flex items-center pl-3 text-gray-400"><i class="fas fa-phone text-sm"></i></span>
                        <input type="tel" name="phone" value="<?= sanitize_string($profileData['phone'] ?? '') ?>"
                               placeholder="+1 (555) 000-0000"
                               class="w-full pl-10 pr-4 py-2.5 rounded-xl border border-gray-200 bg-gray-50 text-sm focus:bg-white focus:border-blue-400 focus:ring-2 focus:ring-blue-100 outline-none transition-all">
                    </div>
                </div>

                <div>
                    <label class="block text-sm font-semibold text-gray-700 mb-1.5">Wallet Balance</label>
                    <div class="relative">
                        <span class="absolute inset-y-0 left-0 flex items-center pl-3 text-gray-400"><i class="fas fa-wallet text-sm"></i></span>
                        <input type="text" value="<?= format_currency((float) $profileData['wallet_balance']) ?>" readonly
                               class="w-full pl-10 pr-4 py-2.5 rounded-xl border border-gray-200 bg-gray-100 text-sm text-gray-500 cursor-not-allowed">
                    </div>
                </div>
            </div>
        </div>

        <!-- ── Company Information ──────────────────────────────────── -->
        <div class="bg-white rounded-2xl border border-gray-100 shadow-sm fade-in" style="animation-delay:.3s">
            <div class="p-6 border-b border-gray-100">
                <h3 class="text-base font-bold text-gray-900 flex items-center gap-2">
                    <i class="fas fa-building text-cyan-500"></i> Company Information
                </h3>
            </div>
            <div class="p-6 space-y-5">
                <div>
                    <label class="block text-sm font-semibold text-gray-700 mb-1.5">Company Logo</label>
                    <div class="flex items-center gap-4">
                        <div class="relative group flex-shrink-0">
                            <img id="logoPreview"
                                 src="<?= get_profile_image($client['company_logo'] ?? null) ?>"
                                 class="w-16 h-16 rounded-xl border border-gray-200 object-cover"
                                 alt="Company Logo">
                            <label for="logoInput" class="absolute inset-0 rounded-xl bg-black/40 flex items-center justify-center opacity-0 group-hover:opacity-100 transition-opacity cursor-pointer">
                                <i class="fas fa-camera text-white text-sm"></i>
                            </label>
                        </div>
                        <div class="flex-1">
                            <input type="file" id="logoInput" name="company_logo" accept="image/jpeg,image/jpg,image/png,image/webp" class="hidden" onchange="previewImage(this, 'logoPreview')">
                            <label for="logoInput" class="inline-flex items-center gap-2 px-4 py-2 rounded-xl border border-gray-200 bg-gray-50 text-sm text-gray-600 hover:bg-gray-100 cursor-pointer transition-colors">
                                <i class="fas fa-upload text-xs"></i> Choose Logo
                            </label>
                            <p class="text-[11px] text-gray-400 mt-1">JPG, PNG, WebP. Max 2MB.</p>
                        </div>
                    </div>
                </div>

                <div>
                    <label class="block text-sm font-semibold text-gray-700 mb-1.5">Company Name</label>
                    <div class="relative">
                        <span class="absolute inset-y-0 left-0 flex items-center pl-3 text-gray-400"><i class="fas fa-building text-sm"></i></span>
                        <input type="text" name="company_name" value="<?= sanitize_string($client['company_name'] ?? '') ?>"
                               placeholder="Your Company LLC"
                               class="w-full pl-10 pr-4 py-2.5 rounded-xl border border-gray-200 bg-gray-50 text-sm focus:bg-white focus:border-blue-400 focus:ring-2 focus:ring-blue-100 outline-none transition-all">
                    </div>
                </div>

                <div>
                    <label class="block text-sm font-semibold text-gray-700 mb-1.5">Industry</label>
                    <div class="relative">
                        <span class="absolute inset-y-0 left-0 flex items-center pl-3 text-gray-400"><i class="fas fa-industry text-sm"></i></span>
                        <input type="text" name="industry" value="<?= sanitize_string($client['industry'] ?? '') ?>"
                               placeholder="e.g. Technology, Healthcare, Finance"
                               class="w-full pl-10 pr-4 py-2.5 rounded-xl border border-gray-200 bg-gray-50 text-sm focus:bg-white focus:border-blue-400 focus:ring-2 focus:ring-blue-100 outline-none transition-all">
                    </div>
                </div>

                <div>
                    <label class="block text-sm font-semibold text-gray-700 mb-1.5">Company Website</label>
                    <div class="relative">
                        <span class="absolute inset-y-0 left-0 flex items-center pl-3 text-gray-400"><i class="fas fa-globe text-sm"></i></span>
                        <input type="url" name="company_website" value="<?= sanitize_string($client['company_website'] ?? '') ?>"
                               placeholder="https://example.com"
                               class="w-full pl-10 pr-4 py-2.5 rounded-xl border border-gray-200 bg-gray-50 text-sm focus:bg-white focus:border-blue-400 focus:ring-2 focus:ring-blue-100 outline-none transition-all">
                    </div>
                </div>

                <div>
                    <label class="block text-sm font-semibold text-gray-700 mb-1.5">Company Size</label>
                    <div class="relative">
                        <span class="absolute inset-y-0 left-0 flex items-center pl-3 text-gray-400"><i class="fas fa-users text-sm"></i></span>
                        <select name="company_size"
                                class="w-full pl-10 pr-4 py-2.5 rounded-xl border border-gray-200 bg-gray-50 text-sm focus:bg-white focus:border-blue-400 focus:ring-2 focus:ring-blue-100 outline-none transition-all appearance-none">
                            <option value="">Select size</option>
                            <option value="Startup" <?= ($client['company_size'] ?? '') === 'Startup' ? 'selected' : '' ?>>Startup (1-10)</option>
                            <option value="Small" <?= ($client['company_size'] ?? '') === 'Small' ? 'selected' : '' ?>>Small (11-50)</option>
                            <option value="Medium" <?= ($client['company_size'] ?? '') === 'Medium' ? 'selected' : '' ?>>Medium (51-200)</option>
                            <option value="Large" <?= ($client['company_size'] ?? '') === 'Large' ? 'selected' : '' ?>>Large (200+)</option>
                        </select>
                        <span class="absolute inset-y-0 right-0 flex items-center pr-3 text-gray-400 pointer-events-none"><i class="fas fa-chevron-down text-xs"></i></span>
                    </div>
                </div>

                <div>
                    <label class="block text-sm font-semibold text-gray-700 mb-1.5">Total Spending</label>
                    <div class="relative">
                        <span class="absolute inset-y-0 left-0 flex items-center pl-3 text-gray-400"><i class="fas fa-dollar-sign text-sm"></i></span>
                        <input type="text" value="<?= format_currency((float) ($client['total_spent'] ?? 0)) ?>" readonly
                               class="w-full pl-10 pr-4 py-2.5 rounded-xl border border-gray-200 bg-gray-100 text-sm text-gray-500 cursor-not-allowed">
                    </div>
                </div>
            </div>
        </div>

        <div class="2xl:col-span-2 fade-in" style="animation-delay:.35s">
            <div class="bg-white rounded-2xl border border-gray-100 shadow-sm p-6 flex flex-col sm:flex-row items-center justify-between gap-4">
                <p class="text-sm text-gray-400">
                    <i class="fas fa-info-circle mr-1"></i> Changes will be reflected across the platform.
                </p>
                <button type="submit"
                        class="btn-grad inline-flex items-center gap-2 text-white text-sm font-semibold px-8 py-3 rounded-xl shadow-lg shadow-blue-500/25">
                    <i class="fas fa-save"></i> Save Changes
                </button>
            </div>
        </div>
    </form>

    <!-- ═══ CHANGE PASSWORD ══════════════════════════════════════════ -->
    <form method="POST" class="fade-in" style="animation-delay:.4s">
        <?= csrf_field() ?>
        <input type="hidden" name="action" value="change_password">

        <div class="bg-white rounded-2xl border border-gray-100 shadow-sm overflow-hidden">
            <div class="p-6 border-b border-gray-100 flex items-center justify-between">
                <h3 class="text-base font-bold text-gray-900 flex items-center gap-2">
                    <i class="fas fa-lock text-red-500"></i> Change Password
                </h3>
            </div>
            <div class="p-6">
                <div class="grid grid-cols-1 sm:grid-cols-3 gap-5">
                    <div>
                        <label class="block text-sm font-semibold text-gray-700 mb-1.5">Current Password <span class="text-red-400">*</span></label>
                        <div class="relative">
                            <span class="absolute inset-y-0 left-0 flex items-center pl-3 text-gray-400"><i class="fas fa-key text-sm"></i></span>
                            <input type="password" name="current_password" required autocomplete="current-password"
                                   class="w-full pl-10 pr-10 py-2.5 rounded-xl border border-gray-200 bg-gray-50 text-sm focus:bg-white focus:border-blue-400 focus:ring-2 focus:ring-blue-100 outline-none transition-all">
                            <button type="button" onclick="togglePassword(this)" class="absolute inset-y-0 right-0 flex items-center pr-3 text-gray-400 hover:text-gray-600">
                                <i class="fas fa-eye text-sm"></i>
                            </button>
                        </div>
                    </div>

                    <div>
                        <label class="block text-sm font-semibold text-gray-700 mb-1.5">New Password <span class="text-red-400">*</span></label>
                        <div class="relative">
                            <span class="absolute inset-y-0 left-0 flex items-center pl-3 text-gray-400"><i class="fas fa-lock text-sm"></i></span>
                            <input type="password" name="new_password" required autocomplete="new-password"
                                   class="w-full pl-10 pr-10 py-2.5 rounded-xl border border-gray-200 bg-gray-50 text-sm focus:bg-white focus:border-blue-400 focus:ring-2 focus:ring-blue-100 outline-none transition-all">
                            <button type="button" onclick="togglePassword(this)" class="absolute inset-y-0 right-0 flex items-center pr-3 text-gray-400 hover:text-gray-600">
                                <i class="fas fa-eye text-sm"></i>
                            </button>
                        </div>
                        <p class="text-[11px] text-gray-400 mt-1">Min 8 chars, upper, lower, number.</p>
                    </div>

                    <div>
                        <label class="block text-sm font-semibold text-gray-700 mb-1.5">Confirm Password <span class="text-red-400">*</span></label>
                        <div class="relative">
                            <span class="absolute inset-y-0 left-0 flex items-center pl-3 text-gray-400"><i class="fas fa-lock text-sm"></i></span>
                            <input type="password" name="confirm_password" required autocomplete="new-password"
                                   class="w-full pl-10 pr-10 py-2.5 rounded-xl border border-gray-200 bg-gray-50 text-sm focus:bg-white focus:border-blue-400 focus:ring-2 focus:ring-blue-100 outline-none transition-all">
                            <button type="button" onclick="togglePassword(this)" class="absolute inset-y-0 right-0 flex items-center pr-3 text-gray-400 hover:text-gray-600">
                                <i class="fas fa-eye text-sm"></i>
                            </button>
                        </div>
                    </div>
                </div>

                <div class="mt-5 flex justify-end">
                    <button type="submit"
                            class="inline-flex items-center gap-2 px-6 py-2.5 rounded-xl border-2 border-red-200 text-red-600 text-sm font-semibold hover:bg-red-50 transition-colors">
                        <i class="fas fa-shield-alt"></i> Update Password
                    </button>
                </div>
            </div>
        </div>
    </form>

<script>
function previewImage(input, previewId) {
    if (input.files && input.files[0]) {
        const reader = new FileReader();
        reader.onload = function(e) {
            document.getElementById(previewId).src = e.target.result;
        };
        reader.readAsDataURL(input.files[0]);
    }
}

function togglePassword(btn) {
    const input = btn.parentElement.querySelector('input');
    const icon = btn.querySelector('i');
    if (input.type === 'password') {
        input.type = 'text';
        icon.classList.replace('fa-eye', 'fa-eye-slash');
    } else {
        input.type = 'password';
        icon.classList.replace('fa-eye-slash', 'fa-eye');
    }
}
</script>
<?php require_once __DIR__ . '/../components/layout_end.php'; ?>
