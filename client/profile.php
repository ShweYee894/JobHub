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

$stmt = $conn->prepare('SELECT COUNT(*) AS cnt FROM reviews WHERE reviewee_id = ? AND COALESCE(is_hidden, 0) = 0');
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

$profileData = $user;

$pageTitle = 'My Profile';
$pageSubtitle = 'Manage your personal and company information';
$activePage = 'profile';
$user = ['name' => $profileData['name'] ?? 'Client', 'profile_image' => $profileData['profile_image'] ?? null];
$unreadCount = get_unread_message_count($userId, 'client');
$profileLink = 'profile.php';
require_once __DIR__ . '/../includes/client_topbar.php';
?>

    <?php display_flash('success'); ?>
    <?php display_flash('error'); ?>
    <?php display_flash('info'); ?>

    <?php if (!empty($errors)): ?>
        <div class="bg-red-50 text-red-800 border border-red-200 rounded-xl p-4 dark:bg-red-900/30 dark:text-red-200 dark:border-red-800">
            <div class="flex items-center gap-2 mb-2">
                <i data-lucide="circle-alert" class="w-4 h-4"></i>
                <span class="font-semibold text-sm">Please fix the following errors:</span>
            </div>
            <ul class="list-disc list-inside text-sm space-y-1">
                <?php foreach ($errors as $err): ?>
                    <li><?= sanitize_string($err) ?></li>
                <?php endforeach; ?>
            </ul>
        </div>
    <?php endif; ?>
    <?php $conn->close(); ?>
<main class="min-h-screen bg-slate-50 dark:bg-slate-900 py-8">
    <!-- ═══ PROFILE SUMMARY CARD ═════════════════════════════════════ -->
    <div class="max-w-7xl mx-auto px-4 sm:px-6">
        <div class="bg-white rounded-2xl border border-slate-200 shadow-sm p-6 sm:p-8 fade-in dark:bg-slate-800 dark:border-slate-700" style="animation-delay:.2s">
            <div class="flex flex-col sm:flex-row items-center gap-6">
                <!-- Avatar -->
                <div class="relative group flex-shrink-0">
                    <img id="profilePreview"
                         src="<?= get_profile_image($profileData['profile_image']) ?>"
                         class="w-24 h-24 rounded-full border-4 border-white dark:border-slate-800 object-cover shadow-lg"
                         alt="Profile">
                    <label for="profileImageInput" class="absolute inset-0 rounded-full bg-black/40 flex items-center justify-center opacity-0 group-hover:opacity-100 transition-opacity cursor-pointer">
                        <i data-lucide="camera" class="text-white w-5 h-5"></i>
                    </label>
                </div>
                <!-- Info -->
                <div class="flex-1 text-center sm:text-left">
                    <h2 class="text-xl font-bold text-slate-900 dark:text-white"><?= sanitize_string($profileData['name']) ?></h2>
                    <p class="text-sm text-slate-500 dark:text-slate-400 mt-1"><?= sanitize_string($profileData['email']) ?></p>
                    <div class="flex items-center justify-center sm:justify-start gap-2 mt-3">
                        <span class="inline-flex items-center gap-1 px-2.5 py-1 rounded-lg bg-indigo-50 text-indigo-600 text-xs font-semibold border border-indigo-200 dark:bg-indigo-900/30 dark:text-indigo-400 dark:border-indigo-800">
                            <i data-lucide="crown" class="w-3 h-3"></i> Client
                        </span>
                        <a href="wallet.php" class="inline-flex items-center gap-1 px-2.5 py-1 rounded-lg bg-emerald-50 text-emerald-600 text-xs font-semibold border border-emerald-200 hover:bg-emerald-100 transition-colors dark:bg-emerald-900/30 dark:text-emerald-400 dark:border-emerald-800 dark:hover:bg-emerald-900/50">
                            <i data-lucide="wallet" class="w-3 h-3"></i> <?= format_currency((float) $profileData['wallet_balance']) ?>
                        </a>
                    </div>
                </div>
                <!-- Stats -->
                <div class="flex items-center gap-6 sm:gap-8">
                    <div class="text-center">
                        <p class="text-lg font-bold text-slate-900 dark:text-white"><?= $statsJobsPosted ?></p>
                        <p class="text-xs text-slate-500 dark:text-slate-400">Jobs Posted</p>
                    </div>
                    <div class="w-px h-8 bg-slate-200 dark:bg-slate-700"></div>
                    <div class="text-center">
                        <p class="text-lg font-bold text-slate-900 dark:text-white"><?= $statsContracts ?></p>
                        <p class="text-xs text-slate-500 dark:text-slate-400">Contracts</p>
                    </div>
                    <div class="w-px h-8 bg-slate-200 dark:bg-slate-700"></div>
                    <div class="text-center">
                        <p class="text-lg font-bold text-slate-900 dark:text-white"><?= $statsReviews ?></p>
                        <p class="text-xs text-slate-500 dark:text-slate-400">Reviews</p>
                    </div>
                </div>
            </div>
        </div>

        <!-- ═══ FORM GRID ═══════════════════════════════════════════════ -->
        <form method="POST" enctype="multipart/form-data" class="grid grid-cols-1 lg:grid-cols-2 gap-8 mt-8 pb-12">
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="update_profile">

            <!-- ── Personal Information ──────────────────────────────── -->
            <div class="bg-white rounded-2xl border border-slate-200 shadow-sm overflow-hidden fade-in dark:bg-slate-800 dark:border-slate-700" style="animation-delay:.25s">
                <div class="px-6 py-4 border-b border-slate-100 dark:border-slate-700">
                    <h3 class="text-sm font-semibold text-slate-900 dark:text-white flex items-center gap-2">
                        <i data-lucide="user" class="w-4 h-4 text-indigo-500"></i> Personal Information
                    </h3>
                </div>
                <div class="p-6 space-y-5">
                    <input type="file" id="profileImageInput" name="profile_image" accept="image/jpeg,image/jpg,image/png,image/webp" class="hidden" onchange="previewImage(this, 'profilePreview')">

                    <div>
                        <label class="block text-xs font-semibold text-slate-500 dark:text-slate-400 mb-1.5 uppercase tracking-wide">Full Name <span class="text-red-400">*</span></label>
                        <input type="text" name="name" value="<?= sanitize_string($profileData['name']) ?>" required
                               class="w-full px-4 py-2.5 rounded-xl border border-slate-200 bg-slate-50 text-sm text-slate-900 focus:bg-white focus:border-indigo-400 focus:ring-2 focus:ring-indigo-100 outline-none transition-all dark:border-slate-600 dark:bg-slate-700 dark:text-white dark:focus:bg-slate-600 dark:focus:border-indigo-500 dark:focus:ring-indigo-800">
                    </div>

                    <div>
                        <label class="block text-xs font-semibold text-slate-500 dark:text-slate-400 mb-1.5 uppercase tracking-wide">Email Address</label>
                        <input type="email" value="<?= sanitize_string($profileData['email']) ?>" readonly
                               class="w-full px-4 py-2.5 rounded-xl border border-slate-200 bg-slate-100 text-sm text-slate-500 cursor-not-allowed dark:border-slate-600 dark:bg-slate-700 dark:text-slate-400">
                    </div>

                    <div>
                        <label class="block text-xs font-semibold text-slate-500 dark:text-slate-400 mb-1.5 uppercase tracking-wide">Phone Number</label>
                        <input type="tel" name="phone" value="<?= sanitize_string($profileData['phone'] ?? '') ?>"
                               placeholder="+1 (555) 000-0000"
                               class="w-full px-4 py-2.5 rounded-xl border border-slate-200 bg-slate-50 text-sm text-slate-900 focus:bg-white focus:border-indigo-400 focus:ring-2 focus:ring-indigo-100 outline-none transition-all dark:border-slate-600 dark:bg-slate-700 dark:text-white dark:focus:bg-slate-600 dark:focus:border-indigo-500 dark:focus:ring-indigo-800">
                    </div>

                    <div>
                        <label class="block text-xs font-semibold text-slate-500 dark:text-slate-400 mb-1.5 uppercase tracking-wide">Wallet Balance</label>
                        <div class="relative">
                            <span class="absolute inset-y-0 left-0 flex items-center pl-3.5 text-slate-400 dark:text-slate-500"><i data-lucide="wallet" class="w-4 h-4"></i></span>
                            <input type="text" value="<?= format_currency((float) $profileData['wallet_balance']) ?>" readonly
                                   class="w-full pl-10 pr-4 py-2.5 rounded-xl border border-slate-200 bg-slate-100 text-sm text-slate-500 cursor-not-allowed dark:border-slate-600 dark:bg-slate-700 dark:text-slate-400">
                        </div>
                    </div>
                </div>
            </div>

            <!-- ── Company Information ───────────────────────────────── -->
            <div class="bg-white rounded-2xl border border-slate-200 shadow-sm overflow-hidden fade-in dark:bg-slate-800 dark:border-slate-700" style="animation-delay:.3s">
                <div class="px-6 py-4 border-b border-slate-100 dark:border-slate-700">
                    <h3 class="text-sm font-semibold text-slate-900 dark:text-white flex items-center gap-2">
                        <i data-lucide="building" class="w-4 h-4 text-cyan-500"></i> Company Information
                    </h3>
                </div>
                <div class="p-6 space-y-5">
                    <div>
                        <label class="block text-xs font-semibold text-slate-500 dark:text-slate-400 mb-2 uppercase tracking-wide">Company Logo</label>
                        <div class="flex items-center gap-4">
                            <div class="relative group flex-shrink-0">
                                <?php
                                $companyLogoFile = $client['company_logo'] ?? null;
                                $companyLogoPath = $companyLogoFile ? __DIR__ . '/../assets/upload/profiles/' . basename($companyLogoFile) : null;
                                $companyLogoExists = $companyLogoPath && file_exists($companyLogoPath);
                                ?>
                                <?php if ($companyLogoExists): ?>
                                    <img id="logoPreview"
                                         src="/jobhub/assets/upload/profiles/<?= sanitize_string(basename($companyLogoFile)) ?>"
                                         class="w-16 h-16 rounded-xl border border-slate-200 dark:border-slate-600 object-cover"
                                         alt="Company Logo">
                                <?php else: ?>
                                    <div id="logoPreview" class="w-16 h-16 rounded-xl border border-dashed border-slate-300 dark:border-slate-600 bg-slate-50 dark:bg-slate-700/50 flex items-center justify-center">
                                        <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" class="text-slate-400 dark:text-slate-500"><path d="M14.5 4h-5L7 7H4a2 2 0 0 0-2 2v9a2 2 0 0 0 2 2h16a2 2 0 0 0 2-2V9a2 2 0 0 0-2-2h-3l-2.5-3z"/><circle cx="12" cy="13" r="3"/></svg>
                                    </div>
                                <?php endif; ?>
                            </div>
                            <div class="flex-1">
                                <input type="file" id="logoInput" name="company_logo" accept="image/jpeg,image/jpg,image/png,image/webp" class="hidden" onchange="previewImage(this, 'logoPreview')">
                                <label for="logoInput" class="inline-flex items-center gap-2 px-4 py-2 rounded-xl border border-slate-200 bg-slate-50 text-sm text-slate-600 hover:bg-slate-100 cursor-pointer transition-colors dark:border-slate-600 dark:bg-slate-700 dark:text-slate-300 dark:hover:bg-slate-600">
                                    <i data-lucide="upload" class="w-4 h-4"></i> Choose Logo
                                </label>
                                <p class="text-[11px] text-slate-400 dark:text-slate-500 mt-1">JPG, PNG, WebP. Max 2MB.</p>
                            </div>
                        </div>
                    </div>

                    <div>
                        <label class="block text-xs font-semibold text-slate-500 dark:text-slate-400 mb-1.5 uppercase tracking-wide">Company Name</label>
                        <input type="text" name="company_name" value="<?= sanitize_string($client['company_name'] ?? '') ?>"
                               placeholder="Your Company LLC"
                               class="w-full px-4 py-2.5 rounded-xl border border-slate-200 bg-slate-50 text-sm text-slate-900 focus:bg-white focus:border-indigo-400 focus:ring-2 focus:ring-indigo-100 outline-none transition-all dark:border-slate-600 dark:bg-slate-700 dark:text-white dark:focus:bg-slate-600 dark:focus:border-indigo-500 dark:focus:ring-indigo-800">
                    </div>

                    <div>
                        <label class="block text-xs font-semibold text-slate-500 dark:text-slate-400 mb-1.5 uppercase tracking-wide">Industry</label>
                        <input type="text" name="industry" value="<?= sanitize_string($client['industry'] ?? '') ?>"
                               placeholder="e.g. Technology, Healthcare, Finance"
                               class="w-full px-4 py-2.5 rounded-xl border border-slate-200 bg-slate-50 text-sm text-slate-900 focus:bg-white focus:border-indigo-400 focus:ring-2 focus:ring-indigo-100 outline-none transition-all dark:border-slate-600 dark:bg-slate-700 dark:text-white dark:focus:bg-slate-600 dark:focus:border-indigo-500 dark:focus:ring-indigo-800">
                    </div>

                    <div>
                        <label class="block text-xs font-semibold text-slate-500 dark:text-slate-400 mb-1.5 uppercase tracking-wide">Company Website</label>
                        <input type="url" name="company_website" value="<?= sanitize_string($client['company_website'] ?? '') ?>"
                               placeholder="https://example.com"
                               class="w-full px-4 py-2.5 rounded-xl border border-slate-200 bg-slate-50 text-sm text-slate-900 focus:bg-white focus:border-indigo-400 focus:ring-2 focus:ring-indigo-100 outline-none transition-all dark:border-slate-600 dark:bg-slate-700 dark:text-white dark:focus:bg-slate-600 dark:focus:border-indigo-500 dark:focus:ring-indigo-800">
                    </div>

                    <div>
                        <label class="block text-xs font-semibold text-slate-500 dark:text-slate-400 mb-1.5 uppercase tracking-wide">Company Size</label>
                        <div class="relative">
                            <select name="company_size"
                                    class="w-full px-4 py-2.5 rounded-xl border border-slate-200 bg-slate-50 text-sm text-slate-900 focus:bg-white focus:border-indigo-400 focus:ring-2 focus:ring-indigo-100 outline-none transition-all appearance-none dark:border-slate-600 dark:bg-slate-700 dark:text-white dark:focus:bg-slate-600 dark:focus:border-indigo-500 dark:focus:ring-indigo-800">
                                <option value="">Select size</option>
                                <option value="Startup" <?= ($client['company_size'] ?? '') === 'Startup' ? 'selected' : '' ?>>Startup (1-10)</option>
                                <option value="Small" <?= ($client['company_size'] ?? '') === 'Small' ? 'selected' : '' ?>>Small (11-50)</option>
                                <option value="Medium" <?= ($client['company_size'] ?? '') === 'Medium' ? 'selected' : '' ?>>Medium (51-200)</option>
                                <option value="Large" <?= ($client['company_size'] ?? '') === 'Large' ? 'selected' : '' ?>>Large (200+)</option>
                            </select>
                            <span class="absolute inset-y-0 right-0 flex items-center pr-3 text-slate-400 pointer-events-none dark:text-slate-500"><i data-lucide="chevron-down" class="w-4 h-4"></i></span>
                        </div>
                    </div>

                    <div>
                        <label class="block text-xs font-semibold text-slate-500 dark:text-slate-400 mb-1.5 uppercase tracking-wide">Total Spending</label>
                        <input type="text" value="<?= format_currency((float) ($client['total_spent'] ?? 0)) ?>" readonly
                               class="w-full px-4 py-2.5 rounded-xl border border-slate-200 bg-slate-100 text-sm text-slate-500 cursor-not-allowed dark:border-slate-600 dark:bg-slate-700 dark:text-slate-400">
                    </div>
                </div>
            </div>

            <!-- ── Save Button (Full Width) ──────────────────────────── -->
            <div class="lg:col-span-2 fade-in" style="animation-delay:.35s">
                <div class="bg-white rounded-2xl border border-slate-200 shadow-sm px-6 py-4 flex flex-col sm:flex-row items-center justify-between gap-4 dark:bg-slate-800 dark:border-slate-700">
                    <p class="text-sm text-slate-400 dark:text-slate-500">
                        <i data-lucide="circle-info" class="w-4 h-4 mr-1 inline-block"></i> Changes will be reflected across the platform.
                    </p>
                    <button type="submit"
                            class="inline-flex items-center gap-2 bg-indigo-600 hover:bg-indigo-700 text-white text-sm font-semibold px-8 py-3 rounded-xl shadow-lg shadow-indigo-500/25 transition-colors">
                        <i data-lucide="save" class="w-4 h-4"></i> Save Changes
                    </button>
                </div>
            </div>
        </form>

        <!-- ═══ CHANGE PASSWORD ═══════════════════════════════════════ -->
        <form method="POST" class="fade-in pb-12" style="animation-delay:.4s">
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="change_password">

            <div class="bg-white rounded-2xl border border-slate-200 shadow-sm overflow-hidden dark:bg-slate-800 dark:border-slate-700">
                <div class="px-6 py-4 border-b border-slate-100 dark:border-slate-700">
                    <h3 class="text-sm font-semibold text-slate-900 dark:text-white flex items-center gap-2">
                        <i data-lucide="lock" class="w-4 h-4 text-rose-500"></i> Change Password
                    </h3>
                </div>
                <div class="p-6">
                    <div class="grid grid-cols-1 sm:grid-cols-3 gap-5">
                        <div>
                            <label class="block text-xs font-semibold text-slate-500 dark:text-slate-400 mb-1.5 uppercase tracking-wide">Current Password <span class="text-red-400">*</span></label>
                            <div class="relative">
                                <input type="password" name="current_password" required autocomplete="current-password"
                                       class="w-full px-4 pr-10 py-2.5 rounded-xl border border-slate-200 bg-slate-50 text-sm text-slate-900 focus:bg-white focus:border-indigo-400 focus:ring-2 focus:ring-indigo-100 outline-none transition-all dark:border-slate-600 dark:bg-slate-700 dark:text-white dark:focus:bg-slate-600 dark:focus:border-indigo-500 dark:focus:ring-indigo-800">
                                <button type="button" onclick="togglePassword(this)" class="absolute inset-y-0 right-0 flex items-center pr-3 text-slate-400 hover:text-slate-600 dark:text-slate-500 dark:hover:text-slate-300">
                                    <i data-lucide="eye" class="w-4 h-4"></i>
                                </button>
                            </div>
                        </div>

                        <div>
                            <label class="block text-xs font-semibold text-slate-500 dark:text-slate-400 mb-1.5 uppercase tracking-wide">New Password <span class="text-red-400">*</span></label>
                            <div class="relative">
                                <input type="password" name="new_password" required autocomplete="new-password"
                                       class="w-full px-4 pr-10 py-2.5 rounded-xl border border-slate-200 bg-slate-50 text-sm text-slate-900 focus:bg-white focus:border-indigo-400 focus:ring-2 focus:ring-indigo-100 outline-none transition-all dark:border-slate-600 dark:bg-slate-700 dark:text-white dark:focus:bg-slate-600 dark:focus:border-indigo-500 dark:focus:ring-indigo-800">
                                <button type="button" onclick="togglePassword(this)" class="absolute inset-y-0 right-0 flex items-center pr-3 text-slate-400 hover:text-slate-600 dark:text-slate-500 dark:hover:text-slate-300">
                                    <i data-lucide="eye" class="w-4 h-4"></i>
                                </button>
                            </div>
                            <p class="text-[11px] text-slate-400 dark:text-slate-500 mt-1">Min 8 chars, upper, lower, number.</p>
                        </div>

                        <div>
                            <label class="block text-xs font-semibold text-slate-500 dark:text-slate-400 mb-1.5 uppercase tracking-wide">Confirm Password <span class="text-red-400">*</span></label>
                            <div class="relative">
                                <input type="password" name="confirm_password" required autocomplete="new-password"
                                       class="w-full px-4 pr-10 py-2.5 rounded-xl border border-slate-200 bg-slate-50 text-sm text-slate-900 focus:bg-white focus:border-indigo-400 focus:ring-2 focus:ring-indigo-100 outline-none transition-all dark:border-slate-600 dark:bg-slate-700 dark:text-white dark:focus:bg-slate-600 dark:focus:border-indigo-500 dark:focus:ring-indigo-800">
                                <button type="button" onclick="togglePassword(this)" class="absolute inset-y-0 right-0 flex items-center pr-3 text-slate-400 hover:text-slate-600 dark:text-slate-500 dark:hover:text-slate-300">
                                    <i data-lucide="eye" class="w-4 h-4"></i>
                                </button>
                            </div>
                        </div>
                    </div>

                    <div class="mt-6 flex justify-end">
                        <button type="submit"
                                class="inline-flex items-center gap-2 px-6 py-2.5 rounded-xl border-2 border-rose-200 text-rose-600 text-sm font-semibold hover:bg-rose-50 transition-colors dark:border-rose-800 dark:text-rose-400 dark:hover:bg-rose-900/30">
                            <i data-lucide="shield" class="w-4 h-4"></i> Update Password
                        </button>
                    </div>
                </div>
            </div>
        </form>
    </div>
</main>
<script>
function previewImage(input, previewId) {
    if (input.files && input.files[0]) {
        const reader = new FileReader();
        reader.onload = function(e) {
            const el = document.getElementById(previewId);
            if (el.tagName === 'DIV') {
                const img = document.createElement('img');
                img.id = previewId;
                img.src = e.target.result;
                img.className = el.className;
                img.alt = el.getAttribute('alt') || 'Preview';
                el.parentNode.replaceChild(img, el);
            } else {
                el.src = e.target.result;
            }
        };
        reader.readAsDataURL(input.files[0]);
    }
}

function togglePassword(btn) {
    const input = btn.parentElement.querySelector('input');
    const icon = btn.querySelector('i');
    if (input.type === 'password') {
        input.type = 'text';
        icon.setAttribute('data-lucide', 'eye-off'); lucide.createIcons();;
    } else {
        input.type = 'password';
        icon.setAttribute('data-lucide', 'eye'); lucide.createIcons();;
    }
}
</script>
<?php require_once __DIR__ . '/../includes/client_footer.php'; ?>
