<?php

/**
 * Admin Settings – Complete platform configuration
 * Tabs: General, Payment, Registration, Security, Jobs, Proposals, Notifications, AI, Email, Appearance, System
 */
require_once __DIR__ . '/../auth/auth.php';
require_role('admin');
require_once __DIR__ . '/../config/db.php';

function format_bytes(float $bytes, int $precision = 2): string
{
    $units = ['B', 'KB', 'MB', 'GB', 'TB'];
    $bytes = max($bytes, 0);
    $pow = floor(($bytes ? log($bytes) : 0) / log(1024));
    $pow = min($pow, count($units) - 1);
    $bytes /= (1 << (10 * $pow));
    return round($bytes, $precision) . ' ' . $units[$pow];
}

$currentPage = 'settings';

$settingsFile = __DIR__ . '/../config/platform_settings.json';
$uploadDir = __DIR__ . '/../assets/upload/settings';

if (!is_dir($uploadDir)) {
    mkdir($uploadDir, 0755, true);
}

function load_settings(string $file): array
{
    if (file_exists($file)) {
        $json = file_get_contents($file);
        $data = json_decode($json, true);
        return is_array($data) ? $data : [];
    }
    return [];
}

function save_settings(string $file, array $data): bool
{
    return file_put_contents($file, json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE)) !== false;
}

function upload_image(string $fieldName, string $uploadDir): ?string
{
    if (!isset($_FILES[$fieldName]) || $_FILES[$fieldName]['error'] !== UPLOAD_ERR_OK) {
        return null;
    }
    $file = $_FILES[$fieldName];
    $allowed = ['image/jpeg', 'image/png', 'image/gif', 'image/webp', 'image/svg+xml'];
    if (!in_array($file['type'], $allowed)) {
        return null;
    }
    if ($file['size'] > 2 * 1024 * 1024) {
        return null;
    }
    $ext = pathinfo($file['name'], PATHINFO_EXTENSION);
    $name = $fieldName . '_' . time() . '_' . bin2hex(random_bytes(8)) . '.' . $ext;
    if (move_uploaded_file($file['tmp_name'], $uploadDir . '/' . $name)) {
        return $name;
    }
    return null;
}

$defaults = [
    'platform_name' => 'JobHub',
    'platform_email' => 'admin@freelancehub.com',
    'support_email' => 'support@freelancehub.com',
    'platform_phone' => '',
    'platform_address' => '',
    'platform_logo' => '',
    'platform_favicon' => '',
    'platform_fee_percentage' => 10,
    'default_wallet_balance' => 0,
    'escrow_enabled' => 1,
    'registration_enabled' => 1,
    'freelancer_registration' => 1,
    'client_registration' => 1,
    'session_timeout' => 30,
    'password_min_length' => 8,
    'password_require_uppercase' => 1,
    'password_require_number' => 1,
    'two_factor_enabled' => 0,
    'maintenance_mode' => 0,
    'smtp_enabled' => 0,
    'dark_mode' => 0,
    'theme_color' => '#2563eb',
    'job_budget_min' => 50,
    'job_budget_max' => 50000,
    'job_duration_options' => '1 week,2 weeks,1 month,3 months,6 months',
    'max_proposals_per_job' => 50,
    'proposal_expiry_days' => 14,
    'auto_close_jobs_days' => 30,
    'email_notifications' => 1,
    'proposal_notifications' => 1,
    'payment_notifications' => 1,
    'dispute_notifications' => 1,
    'marketing_emails' => 0,
    'ai_matching_enabled' => 1,
    'ai_embedding_model' => 'text-embedding-ada-002',
    'ai_auto_match' => 0,
    'ai_min_similarity' => 70,
    'smtp_host' => '',
    'smtp_port' => 587,
    'smtp_username' => '',
    'smtp_password' => '',
    'smtp_encryption' => 'tls',
];

$settings = array_merge($defaults, load_settings($settingsFile));

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf_token()) {
        set_flash('error', 'Invalid security token. Please try again.');
        redirect('settings.php');
    }

    $settings['platform_name'] = sanitize_string($_POST['platform_name'] ?? '');
    $settings['platform_email'] = sanitize_string($_POST['platform_email'] ?? '');
    $settings['support_email'] = sanitize_string($_POST['support_email'] ?? '');
    $settings['platform_phone'] = sanitize_string($_POST['platform_phone'] ?? '');
    $settings['platform_address'] = sanitize_string($_POST['platform_address'] ?? '');

    $settings['platform_fee_percentage'] = max(0, min(100, (float) ($_POST['platform_fee_percentage'] ?? 10)));
    $settings['default_wallet_balance'] = max(0, (float) ($_POST['default_wallet_balance'] ?? 0));
    $settings['escrow_enabled'] = (($_POST['escrow_enabled'] ?? '0') === '1') ? 1 : 0;

    $settings['registration_enabled'] = (($_POST['registration_enabled'] ?? '0') === '1') ? 1 : 0;
    $settings['freelancer_registration'] = (($_POST['freelancer_registration'] ?? '0') === '1') ? 1 : 0;
    $settings['client_registration'] = (($_POST['client_registration'] ?? '0') === '1') ? 1 : 0;

    $settings['session_timeout'] = max(5, min(1440, (int) ($_POST['session_timeout'] ?? 30)));
    $settings['password_min_length'] = max(4, min(64, (int) ($_POST['password_min_length'] ?? 8)));
    $settings['password_require_uppercase'] = (($_POST['password_require_uppercase'] ?? '0') === '1') ? 1 : 0;
    $settings['password_require_number'] = (($_POST['password_require_number'] ?? '0') === '1') ? 1 : 0;
    $settings['two_factor_enabled'] = (($_POST['two_factor_enabled'] ?? '0') === '1') ? 1 : 0;
    $settings['maintenance_mode'] = (($_POST['maintenance_mode'] ?? '0') === '1') ? 1 : 0;

    $settings['job_budget_min'] = max(0, (float) ($_POST['job_budget_min'] ?? 50));
    $settings['job_budget_max'] = max(0, (float) ($_POST['job_budget_max'] ?? 50000));
    $settings['job_duration_options'] = sanitize_string($_POST['job_duration_options'] ?? '');

    $settings['max_proposals_per_job'] = max(1, min(500, (int) ($_POST['max_proposals_per_job'] ?? 50)));
    $settings['proposal_expiry_days'] = max(1, min(90, (int) ($_POST['proposal_expiry_days'] ?? 14)));
    $settings['auto_close_jobs_days'] = max(1, min(365, (int) ($_POST['auto_close_jobs_days'] ?? 30)));

    $settings['email_notifications'] = (($_POST['email_notifications'] ?? '0') === '1') ? 1 : 0;
    $settings['proposal_notifications'] = (($_POST['proposal_notifications'] ?? '0') === '1') ? 1 : 0;
    $settings['payment_notifications'] = (($_POST['payment_notifications'] ?? '0') === '1') ? 1 : 0;
    $settings['dispute_notifications'] = (($_POST['dispute_notifications'] ?? '0') === '1') ? 1 : 0;
    $settings['marketing_emails'] = (($_POST['marketing_emails'] ?? '0') === '1') ? 1 : 0;

    $settings['ai_matching_enabled'] = (($_POST['ai_matching_enabled'] ?? '0') === '1') ? 1 : 0;
    $settings['ai_embedding_model'] = sanitize_string($_POST['ai_embedding_model'] ?? 'text-embedding-ada-002');
    $settings['ai_auto_match'] = (($_POST['ai_auto_match'] ?? '0') === '1') ? 1 : 0;
    $settings['ai_min_similarity'] = max(0, min(100, (int) ($_POST['ai_min_similarity'] ?? 70)));

    $settings['smtp_enabled'] = (($_POST['smtp_enabled'] ?? '0') === '1') ? 1 : 0;
    $settings['smtp_host'] = sanitize_string($_POST['smtp_host'] ?? '');
    $settings['smtp_port'] = max(1, min(65535, (int) ($_POST['smtp_port'] ?? 587)));
    $settings['smtp_username'] = sanitize_string($_POST['smtp_username'] ?? '');
    $settings['smtp_password'] = $_POST['smtp_password'] ?? '';
    $settings['smtp_encryption'] = sanitize_string($_POST['smtp_encryption'] ?? 'tls');

    $settings['dark_mode'] = (($_POST['dark_mode'] ?? '0') === '1') ? 1 : 0;
    $settings['theme_color'] = sanitize_string($_POST['theme_color'] ?? '#2563eb');

    if (!empty($_FILES['platform_logo']['name'])) {
        $logo = upload_image('platform_logo', $uploadDir);
        if ($logo) {
            $settings['platform_logo'] = $logo;
        } else {
            set_flash('error', 'Logo upload failed. Use JPG, PNG, GIF, or WebP (max 2MB).');
            redirect('settings.php');
        }
    }

    if (!empty($_FILES['platform_favicon']['name'])) {
        $favicon = upload_image('platform_favicon', $uploadDir);
        if ($favicon) {
            $settings['platform_favicon'] = $favicon;
        } else {
            set_flash('error', 'Favicon upload failed. Use JPG, PNG, GIF, or WebP (max 2MB).');
            redirect('settings.php');
        }
    }

    if (save_settings($settingsFile, $settings)) {
        set_flash('success', 'Settings saved successfully.');
    } else {
        set_flash('error', 'Failed to save settings. Check file permissions.');
    }
    redirect('settings.php');
}

$settings = array_merge($defaults, load_settings($settingsFile));

$phpVersion = phpversion();
$mysqlVersion = '';
$r = $conn->query('SELECT VERSION() AS v');
if ($r) {
    $mysqlVersion = $r->fetch_assoc()['v'] ?? '';
}
$serverSoftware = $_SERVER['SERVER_SOFTWARE'] ?? 'N/A';

$diskTotal = @disk_total_space(__DIR__ . '/../..');
$diskFree = @disk_free_space(__DIR__ . '/../..');
$diskUsed = $diskTotal ? $diskTotal - $diskFree : 0;
$diskPct = $diskTotal ? round(($diskUsed / $diskTotal) * 100, 1) : 0;

$logoUrl = $settings['platform_logo'] ? '/jobhub/assets/upload/settings/' . htmlspecialchars($settings['platform_logo']) : '';
$faviconUrl = $settings['platform_favicon'] ? '/jobhub/assets/upload/settings/' . htmlspecialchars($settings['platform_favicon']) : '';

$_userId = $_SESSION['user_id'];
$sStmt = $conn->prepare('SELECT name, profile_image FROM users WHERE id = ?');
$sStmt->bind_param('i', $_userId);
$sStmt->execute();
$_navUserRow = $sStmt->get_result()->fetch_assoc();
$sStmt->close();
$adminName = $_navUserRow['name'] ?? 'Admin';

$conn->close();

$navItems = [
    ['key' => 'dashboard', 'label' => 'Dashboard', 'url' => 'dashboard.php', 'icon' => 'layout-grid'],
    ['key' => 'users', 'label' => 'Users', 'url' => 'users.php', 'icon' => 'users'],
    ['key' => 'clients', 'label' => 'Clients', 'url' => 'clients.php', 'icon' => 'user'],
    ['key' => 'jobs', 'label' => 'Jobs', 'url' => 'jobs.php', 'icon' => 'briefcase'],
    ['key' => 'categories', 'label' => 'Categories', 'url' => 'categories.php', 'icon' => 'folder-open'],
    ['key' => 'skills', 'label' => 'Skills', 'url' => 'skills.php', 'icon' => 'settings'],
    ['key' => 'payments', 'label' => 'Payments', 'url' => 'payments.php', 'icon' => 'credit-card'],
    ['key' => 'wallets', 'label' => 'Wallets', 'url' => 'wallets.php', 'icon' => 'wallet'],
    ['key' => 'contracts', 'label' => 'Contracts', 'url' => 'contracts.php', 'icon' => 'file-text'],
    ['key' => 'milestones', 'label' => 'Milestones', 'url' => 'milestones.php', 'icon' => 'list-checks'],
    ['key' => 'reviews', 'label' => 'Reviews', 'url' => 'reviews.php', 'icon' => 'star'],
    ['key' => 'disputes', 'label' => 'Disputes', 'url' => 'disputes.php', 'icon' => 'hammer'],
    ['key' => 'fraud', 'label' => 'Fraud', 'url' => 'fraud_detection.php', 'icon' => 'shield'],
    ['key' => 'notifications', 'label' => 'Notifications', 'url' => 'notifications.php', 'icon' => 'bell'],
    ['key' => 'email_logs', 'label' => 'Email Logs', 'url' => 'email_logs.php', 'icon' => 'mail'],
    ['key' => 'ai_monitor', 'label' => 'AI Monitor', 'url' => 'ai_monitor.php', 'icon' => 'brain'],
    ['key' => 'analytics', 'label' => 'Analytics', 'url' => 'analytics.php', 'icon' => 'pie-chart'],
    ['key' => 'settings', 'label' => 'Settings', 'url' => 'settings.php', 'icon' => 'settings'],
];
$pageTitle = 'Platform Settings';
$pageSubtitle = 'Configure your platform preferences';
$activePage = 'settings';
$user = ['name' => $adminName, 'profile_image' => $_navUserRow['profile_image'] ?? null];
$unreadCount = 0;
$profileLink = 'profile.php';
require_once __DIR__ . '/../components/layout_start.php';
?>
    <?php if ($settings['platform_favicon']): ?>
    <link rel="icon" type="image/x-icon" href="<?= $faviconUrl ?>" />
    <?php endif; ?>

    <style>
        /* ── Sidebar Nav ──────────────────────────────────────────── */
        .settings-nav-item {
            display: flex;
            align-items: center;
            gap: 10px;
            padding: 9px 14px;
            border-radius: 10px;
            font-size: 13px;
            font-weight: 500;
            color: #64748b;
            cursor: pointer;
            transition: all .15s;
            border: none;
            background: none;
            width: 100%;
            text-align: left;
            position: relative;
        }
        .settings-nav-item:hover { background: #f8fafc; color: #334155; }
        .dark .settings-nav-item:hover { background: rgba(51,65,85,.3); color: #e2e8f0; }
        .settings-nav-item.active { background: #eff6ff; color: #2563eb; font-weight: 600; }
        .dark .settings-nav-item.active { background: rgba(37,99,235,.15); color: #60a5fa; }
        .settings-nav-item.active::before {
            content: '';
            position: absolute;
            left: 0;
            top: 50%;
            transform: translateY(-50%);
            width: 3px;
            height: 20px;
            background: #2563eb;
            border-radius: 0 4px 4px 0;
        }
        .dark .settings-nav-item.active::before { background: #60a5fa; }
        .settings-nav-item i { width: 18px; text-align: center; font-size: 13px; }

        /* ── Upload Dropzones ─────────────────────────────────────── */
        .upload-zone {
            border: 2px dashed #cbd5e1;
            border-radius: 14px;
            padding: 24px 16px;
            text-align: center;
            cursor: pointer;
            transition: all .2s;
            background: #f8fafc;
            position: relative;
        }
        .upload-zone:hover, .upload-zone.dragover { border-color: #3b82f6; background: #eff6ff; }
        .dark .upload-zone { background: #1e293b; border-color: #475569; }
        .dark .upload-zone:hover, .dark .upload-zone.dragover { border-color: #3b82f6; background: rgba(37,99,235,.08); }
        .upload-zone input[type=file] { position: absolute; inset: 0; opacity: 0; cursor: pointer; z-index: 1; }

        /* ── Toggle Switch ────────────────────────────────────────── */
        .toggle-switch {
            position: relative;
            width: 40px;
            height: 22px;
            background: #cbd5e1;
            border-radius: 11px;
            cursor: pointer;
            transition: background .2s;
            flex-shrink: 0;
        }
        .toggle-switch.active { background: #2563eb; }
        .toggle-switch::after {
            content: '';
            position: absolute;
            top: 2px;
            left: 2px;
            width: 18px;
            height: 18px;
            background: #fff;
            border-radius: 50%;
            transition: transform .2s;
            box-shadow: 0 1px 3px rgba(0,0,0,.15);
        }
        .toggle-switch.active::after { transform: translateX(18px); }

        /* ── Section Cards ────────────────────────────────────────── */
        .setting-section-card {
            background: #fff;
            border: 1px solid #f1f5f9;
            border-radius: 14px;
            padding: 24px;
            transition: box-shadow .2s;
        }
        .setting-section-card:hover { box-shadow: 0 2px 12px rgba(0,0,0,.03); }
        .dark .setting-section-card { background: #1e293b; border-color: #334155; }
        .dark .setting-section-card:hover { box-shadow: 0 2px 12px rgba(0,0,0,.2); }

        /* ── Form Fields ──────────────────────────────────────────── */
        .field-label { font-size: 12px; font-weight: 600; color: #475569; margin-bottom: 6px; display: block; }
        .dark .field-label { color: #94a3b8; }
        .field-input {
            width: 100%;
            padding: 9px 12px;
            border-radius: 10px;
            border: 1px solid #e2e8f0;
            font-size: 13px;
            background: #fff;
            color: #1e293b;
            transition: border-color .15s, box-shadow .15s;
            outline: none;
        }
        .field-input:hover { border-color: #cbd5e1; }
        .field-input:focus { border-color: #3b82f6; box-shadow: 0 0 0 3px rgba(59,130,246,.12); }
        .dark .field-input { background: #0f172a; border-color: #475569; color: #e2e8f0; }
        .dark .field-input:hover { border-color: #64748b; }
        .dark .field-input:focus { border-color: #3b82f6; box-shadow: 0 0 0 3px rgba(59,130,246,.2); }
        .field-input::placeholder { color: #94a3b8; }
        textarea.field-input { resize: vertical; min-height: 58px; }

        /* ── Tabs ─────────────────────────────────────────────────── */
        .tab-content-section { display: none; }
        .tab-content-section.active { display: block; }

        /* ── Toggle Row Text ──────────────────────────────────────── */
        .toggle-row-title {
            font-size: 13px;
            font-weight: 600;
            color: #0F172A;
            line-height: 1.4;
        }
        .dark .toggle-row-title { color: #f1f5f9; }
        .toggle-row-desc {
            font-size: 11px;
            color: #64748B;
            margin-top: 4px;
            line-height: 1.5;
        }
        .dark .toggle-row-desc { color: #94a3b8; }
    </style>

    <?php display_flash('success'); ?>
    <?php display_flash('error'); ?>
    <?php display_flash('info'); ?>

    <form method="POST" enctype="multipart/form-data" id="settingsForm">
        <?= csrf_field(); ?>

        <!-- ═══ PAGE HEADER ════════════════════════════════════════════ -->
        <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3 mb-5 fade-in" style="animation-delay:.05s">
            <div>
                <h1 class="text-lg font-bold text-gray-900 dark:text-white">Platform Settings</h1>
                <p class="text-[11px] text-gray-400 dark:text-slate-500 mt-0.5">Configure your platform preferences and branding</p>
            </div>
            <div class="flex items-center gap-2.5 flex-shrink-0">
                <button type="button" onclick="resetForm()" class="inline-flex items-center gap-1.5 px-4 py-2 rounded-xl text-xs font-semibold text-slate-600 dark:text-slate-400 border border-slate-200 dark:border-slate-600 hover:bg-slate-50 dark:hover:bg-slate-700 transition">
                    <i data-lucide="rotate-ccw" style="width:13px;height:13px;"></i> Reset
                </button>
                <button type="submit" class="px-5 py-2 rounded-xl bg-gray-900 dark:bg-blue-600 text-white text-xs font-semibold shadow-sm hover:bg-gray-800 dark:hover:bg-blue-700 transition inline-flex items-center gap-1.5">
                    <i data-lucide="check" class="text-[10px]"></i>Save Changes
                </button>
            </div>
        </div>

        <!-- ═══ MAIN LAYOUT: SIDEBAR + CONTENT ════════════════════════ -->
        <div class="flex flex-col lg:flex-row gap-5 fade-in" style="animation-delay:.1s">

            <!-- ── LEFT SIDEBAR NAV ─────────────────────────────────── -->
            <aside class="w-full lg:w-[220px] flex-shrink-0">
                <div class="bg-white dark:bg-slate-800 rounded-xl border border-gray-100 dark:border-slate-700 shadow-sm p-2 lg:sticky lg:top-24">
                    <nav class="flex lg:flex-col gap-0.5 overflow-x-auto lg:overflow-x-visible">
                        <button type="button" onclick="showTab('general')" class="settings-nav-item active whitespace-nowrap" data-tab="general">
                            <i data-lucide="settings"></i>General
                        </button>
                        <button type="button" onclick="showTab('payment')" class="settings-nav-item whitespace-nowrap" data-tab="payment">
                            <i data-lucide="credit-card"></i>Payment
                        </button>
                        <button type="button" onclick="showTab('registration')" class="settings-nav-item whitespace-nowrap" data-tab="registration">
                            <i data-lucide="user-plus"></i>Registration
                        </button>
                        <button type="button" onclick="showTab('security')" class="settings-nav-item whitespace-nowrap" data-tab="security">
                            <i data-lucide="shield"></i>Security
                        </button>
                        <button type="button" onclick="showTab('jobs')" class="settings-nav-item whitespace-nowrap" data-tab="jobs">
                            <i data-lucide="briefcase"></i>Jobs
                        </button>
                        <button type="button" onclick="showTab('proposals')" class="settings-nav-item whitespace-nowrap" data-tab="proposals">
                            <i data-lucide="send"></i>Proposals
                        </button>
                        <button type="button" onclick="showTab('notifications')" class="settings-nav-item whitespace-nowrap" data-tab="notifications">
                            <i data-lucide="bell"></i>Notifications
                        </button>
                        <button type="button" onclick="showTab('ai')" class="settings-nav-item whitespace-nowrap" data-tab="ai">
                            <i data-lucide="brain"></i>AI Settings
                        </button>
                        <button type="button" onclick="showTab('email')" class="settings-nav-item whitespace-nowrap" data-tab="email">
                            <i data-lucide="mail"></i>Email
                        </button>
                        <!-- <button type="button" onclick="showTab('appearance')" class="settings-nav-item whitespace-nowrap" data-tab="appearance">
                            <i data-lucide="palette"></i>Appearance
                        </button> -->
                        <button type="button" onclick="showTab('system')" class="settings-nav-item whitespace-nowrap" data-tab="system">
                            <i data-lucide="server"></i>System
                        </button>
                    </nav>
                </div>
            </aside>

            <!-- ── CONTENT AREA ─────────────────────────────────────── -->
            <div class="flex-1 min-w-0">

                <!-- ═══ 1. GENERAL SETTINGS ═══════════════════════════════════ -->
                <div id="tab-general" class="tab-content-section active">
                    <!-- Section Header -->
                    <div class="flex items-start justify-between mb-5">
                        <div>
                            <h2 class="text-sm font-bold text-gray-900 dark:text-white">General Settings</h2>
                            <p class="text-[11px] text-gray-400 dark:text-slate-500 mt-0.5">Manage basic platform identity, contact info, and branding assets.</p>
                        </div>
                    </div>

                    <!-- Section 1: Platform Information -->
                    <div class="setting-section-card mb-4">
                        <div class="flex items-center gap-2.5 mb-4">
                            <div class="w-8 h-8 rounded-lg bg-blue-50 dark:bg-blue-900/30 flex items-center justify-center flex-shrink-0">
                                <i data-lucide="building-2" class="text-blue-500 text-xs"></i>
                            </div>
                            <div>
                                <h3 class="text-xs font-bold text-gray-900 dark:text-white">Platform Information</h3>
                                <p class="text-[10px] text-gray-400 dark:text-slate-500">Core identity and contact details</p>
                            </div>
                        </div>
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                            <div>
                                <label class="field-label">Platform Name</label>
                                <input type="text" name="platform_name" value="<?= htmlspecialchars($settings['platform_name']) ?>" class="field-input" placeholder="e.g. JobHub">
                            </div>
                            <div>
                                <label class="field-label">Platform Support Email</label>
                                <input type="email" name="support_email" value="<?= htmlspecialchars($settings['support_email']) ?>" class="field-input" placeholder="support@example.com">
                            </div>
                            <div>
                                <label class="field-label">System Admin Email</label>
                                <input type="email" name="platform_email" value="<?= htmlspecialchars($settings['platform_email']) ?>" class="field-input" placeholder="admin@example.com">
                            </div>
                            <div>
                                <label class="field-label">Contact Phone Number</label>
                                <input type="text" name="platform_phone" value="<?= htmlspecialchars($settings['platform_phone']) ?>" class="field-input" placeholder="+1 (555) 000-0000">
                            </div>
                        </div>
                    </div>

                    <!-- Section 2: Physical Address -->
                    <div class="setting-section-card mb-4">
                        <div class="flex items-center gap-2.5 mb-4">
                            <div class="w-8 h-8 rounded-lg bg-emerald-50 dark:bg-emerald-900/30 flex items-center justify-center flex-shrink-0">
                                <i data-lucide="map-pin" class="text-emerald-500 text-xs"></i>
                            </div>
                            <div>
                                <h3 class="text-xs font-bold text-gray-900 dark:text-white">Physical Address</h3>
                                <p class="text-[10px] text-gray-400 dark:text-slate-500">Registered business address</p>
                            </div>
                        </div>
                        <div>
                            <label class="field-label">Full Address</label>
                            <textarea name="platform_address" rows="2" class="field-input" placeholder="123 Main St, City, State, ZIP"><?= htmlspecialchars($settings['platform_address']) ?></textarea>
                        </div>
                    </div>

                    <!-- Section 3: Branding & Assets -->
                    <div class="setting-section-card">
                        <div class="flex items-center gap-2.5 mb-4">
                            <div class="w-8 h-8 rounded-lg bg-violet-50 dark:bg-violet-900/30 flex items-center justify-center flex-shrink-0">
                                <i data-lucide="palette" class="text-violet-500 text-xs"></i>
                            </div>
                            <div>
                                <h3 class="text-xs font-bold text-gray-900 dark:text-white">Branding & Assets</h3>
                                <p class="text-[10px] text-gray-400 dark:text-slate-500">Upload platform logo </p>
                            </div>
                        </div>
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-5">
                            <!-- Logo Upload -->
                            <div>
                                <label class="field-label mb-2 block">Platform Logo</label>
                                <div class="upload-zone" id="logoDropZone">
                                    <input type="file" name="platform_logo" accept="image/*" onchange="previewLogo(this)">
                                    <div id="logoPreviewEmpty">
                                        <div class="w-14 h-14 rounded-xl bg-gray-100 dark:bg-slate-700 flex items-center justify-center mx-auto mb-3">
                                            <?php if ($logoUrl): ?>
                                            <img src="<?= $logoUrl ?>" class="w-full h-full object-contain rounded-xl" alt="Logo">
                                            <?php else: ?>
                                            <i data-lucide="upload" class="text-gray-300 dark:text-slate-500 text-xl"></i>
                                            <?php endif; ?>
                                        </div>
                                        <p class="text-[11px] font-semibold text-gray-600 dark:text-slate-300 mb-1">
                                            <?php if ($logoUrl): ?>Change logo<?php else: ?>Drop logo here or click to upload<?php endif; ?>
                                        </p>
                                        <p class="text-[10px] text-gray-400 dark:text-slate-500">PNG, JPG, SVG, WebP — max 2 MB</p>
                                    </div>
                                    <div id="logoPreviewActive" class="hidden">
                                        <img id="logoPreviewImg" class="w-20 h-20 object-contain rounded-xl mx-auto mb-2" alt="Preview">
                                        <p class="text-[11px] font-semibold text-blue-600 dark:text-blue-400">New logo selected</p>
                                        <p class="text-[10px] text-gray-400 dark:text-slate-500">Click to change</p>
                                    </div>
                                </div>
                            </div>
                            <!-- Favicon Upload -->
                            <!-- <div>
                                <label class="field-label mb-2 block">Favicon</label>
                                <div class="upload-zone" id="faviconDropZone">
                                    <input type="file" name="platform_favicon" accept="image/*" onchange="previewFavicon(this)">
                                    <div id="faviconPreviewEmpty">
                                        <div class="w-10 h-10 rounded-lg bg-gray-100 dark:bg-slate-700 flex items-center justify-center mx-auto mb-2">
                                            <?php if ($faviconUrl): ?>
                                            <img src="<?= $faviconUrl ?>" class="w-full h-full object-contain rounded-lg" alt="Favicon">
                                            <?php else: ?>
                                            <i data-lucide="star" class="text-gray-300 dark:text-slate-500 text-sm"></i>
                                            <?php endif; ?>
                                        </div>
                                        <p class="text-[11px] font-semibold text-gray-600 dark:text-slate-300 mb-1">
                                            <?php if ($faviconUrl): ?>Change favicon<?php else: ?>Drop favicon here or click<?php endif; ?>
                                        </p>
                                        <p class="text-[10px] text-gray-400 dark:text-slate-500">32×32 px recommended</p>
                                    </div>
                                    <div id="faviconPreviewActive" class="hidden">
                                        <img id="faviconPreviewImg" class="w-10 h-10 object-contain rounded-lg mx-auto mb-1" alt="Preview">
                                        <p class="text-[11px] font-semibold text-blue-600 dark:text-blue-400">New favicon</p>
                                        <p class="text-[10px] text-gray-400 dark:text-slate-500">Click to change</p>
                                    </div>
                                </div>
                            </div> -->
                        </div>
                    </div>
                </div>

                <!-- ═══ 2. PAYMENT SETTINGS ═══════════════════════════════════ -->
                <div id="tab-payment" class="tab-content-section">
                    <div class="flex items-start justify-between mb-5">
                        <div>
                            <h2 class="text-sm font-bold text-gray-900 dark:text-white">Payment Settings</h2>
                            <p class="text-[11px] text-gray-400 dark:text-slate-500 mt-0.5">Configure fees, wallets, and escrow behavior.</p>
                        </div>
                    </div>
                    <div class="setting-section-card">
                        <div class="flex items-center gap-2.5 mb-4">
                            <div class="w-8 h-8 rounded-lg bg-emerald-50 dark:bg-emerald-900/30 flex items-center justify-center flex-shrink-0">
                                <i data-lucide="credit-card" class="text-emerald-500 text-xs"></i>
                            </div>
                            <div>
                                <h3 class="text-xs font-bold text-gray-900 dark:text-white">Payment Configuration</h3>
                                <p class="text-[10px] text-gray-400 dark:text-slate-500">Transaction fees and wallet defaults</p>
                            </div>
                        </div>
                        <div class="space-y-4">
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                <div>
                                    <label class="field-label">Platform Fee (%)</label>
                                    <input type="number" name="platform_fee_percentage" value="<?= htmlspecialchars($settings['platform_fee_percentage']) ?>" min="0" max="100" step="0.5" class="field-input">
                                    <p class="text-[10px] text-gray-400 dark:text-slate-500 mt-1">Percentage charged on each transaction</p>
                                </div>
                                <div>
                                    <label class="field-label">Default Wallet Balance ($)</label>
                                    <input type="number" name="default_wallet_balance" value="<?= htmlspecialchars($settings['default_wallet_balance']) ?>" min="0" step="0.01" class="field-input">
                                    <p class="text-[10px] text-gray-400 dark:text-slate-500 mt-1">Starting balance for new users</p>
                                </div>
                            </div>
                            <div class="flex items-center justify-between p-3.5 bg-gray-50 dark:bg-slate-700/50 rounded-xl">
                                <div class="min-w-0 flex-1 mr-4">
                                    <p class="text-[13px] font-semibold text-gray-900 dark:text-white leading-snug">Escrow System</p>
                                    <p class="text-[11px] text-gray-400 dark:text-slate-500 mt-1 leading-relaxed">Hold payment in escrow until work is approved</p>
                                </div>
                                <div class="toggle-switch <?= $settings['escrow_enabled'] ? 'active' : '' ?>" onclick="toggleSwitch(this)">
                                    <input type="hidden" name="escrow_enabled" value="0">
                                    <input type="checkbox" name="escrow_enabled" value="1" class="hidden" <?= $settings['escrow_enabled'] ? 'checked' : '' ?>>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- ═══ 3. REGISTRATION SETTINGS ═══════════════════════════════════ -->
                <div id="tab-registration" class="tab-content-section">
                    <div class="flex items-start justify-between mb-5">
                        <div>
                            <h2 class="text-sm font-bold text-gray-900 dark:text-white">Registration Settings</h2>
                            <p class="text-[11px] text-gray-400 dark:text-slate-500 mt-0.5">Control who can register on the platform.</p>
                        </div>
                    </div>
                    <div class="setting-section-card">
                        <div class="flex items-center gap-2.5 mb-4">
                            <div class="w-8 h-8 rounded-lg bg-violet-50 dark:bg-violet-900/30 flex items-center justify-center flex-shrink-0">
                                <i data-lucide="user-plus" class="text-violet-500 text-xs"></i>
                            </div>
                            <div>
                                <h3 class="text-xs font-bold text-gray-900 dark:text-white">Registration Controls</h3>
                                <p class="text-[10px] text-gray-400 dark:text-slate-500">Enable or disable user sign-ups</p>
                            </div>
                        </div>
                        <div class="space-y-3">
                            <div class="flex items-center justify-between p-3.5 bg-gray-50 dark:bg-slate-700/50 rounded-xl">
                                <div class="min-w-0 flex-1 mr-4">
                                    <p class="text-[13px] font-semibold text-gray-900 dark:text-white leading-snug">Enable Registration</p>
                                    <p class="text-[11px] text-gray-400 dark:text-slate-500 mt-1 leading-relaxed">Allow new users to register on the platform</p>
                                </div>
                                <div class="toggle-switch <?= $settings['registration_enabled'] ? 'active' : '' ?>" onclick="toggleSwitch(this)">
                                    <input type="hidden" name="registration_enabled" value="0">
                                    <input type="checkbox" name="registration_enabled" value="1" class="hidden" <?= $settings['registration_enabled'] ? 'checked' : '' ?>>
                                </div>
                            </div>
                            <div class="flex items-center justify-between p-3.5 bg-gray-50 dark:bg-slate-700/50 rounded-xl">
                                <div class="min-w-0 flex-1 mr-4">
                                    <p class="text-[13px] font-semibold text-gray-900 dark:text-white leading-snug">Freelancer Registration</p>
                                    <p class="text-[11px] text-gray-400 dark:text-slate-500 mt-1 leading-relaxed">Allow new freelancers to sign up</p>
                                </div>
                                <div class="toggle-switch <?= $settings['freelancer_registration'] ? 'active' : '' ?>" onclick="toggleSwitch(this)">
                                    <input type="hidden" name="freelancer_registration" value="0">
                                    <input type="checkbox" name="freelancer_registration" value="1" class="hidden" <?= $settings['freelancer_registration'] ? 'checked' : '' ?>>
                                </div>
                            </div>
                            <div class="flex items-center justify-between p-3.5 bg-gray-50 dark:bg-slate-700/50 rounded-xl">
                                <div class="min-w-0 flex-1 mr-4">
                                    <p class="text-[13px] font-semibold text-gray-900 dark:text-white leading-snug">Client Registration</p>
                                    <p class="text-[11px] text-gray-400 dark:text-slate-500 mt-1 leading-relaxed">Allow new clients to sign up</p>
                                </div>
                                <div class="toggle-switch <?= $settings['client_registration'] ? 'active' : '' ?>" onclick="toggleSwitch(this)">
                                    <input type="hidden" name="client_registration" value="0">
                                    <input type="checkbox" name="client_registration" value="1" class="hidden" <?= $settings['client_registration'] ? 'checked' : '' ?>>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- ═══ 4. SECURITY SETTINGS ═══════════════════════════════════ -->
                <div id="tab-security" class="tab-content-section">
                    <div class="flex items-start justify-between mb-5">
                        <div>
                            <h2 class="text-sm font-bold text-gray-900 dark:text-white">Security Settings</h2>
                            <p class="text-[11px] text-gray-400 dark:text-slate-500 mt-0.5">Session timeout, password policy, and security options.</p>
                        </div>
                    </div>
                    <div class="setting-section-card">
                        <div class="flex items-center gap-2.5 mb-4">
                            <div class="w-8 h-8 rounded-lg bg-amber-50 dark:bg-amber-900/30 flex items-center justify-center flex-shrink-0">
                                <i data-lucide="shield" class="text-amber-500 text-xs"></i>
                            </div>
                            <div>
                                <h3 class="text-xs font-bold text-gray-900 dark:text-white">Security Controls</h3>
                                <p class="text-[10px] text-gray-400 dark:text-slate-500">Session, passwords, and authentication</p>
                            </div>
                        </div>
                        <div class="space-y-3">
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-3">
                                <div>
                                    <label class="field-label">Session Timeout (minutes)</label>
                                    <input type="number" name="session_timeout" value="<?= htmlspecialchars($settings['session_timeout']) ?>" min="5" max="1440" class="field-input">
                                    <p class="text-[10px] text-gray-400 dark:text-slate-500 mt-1">Auto-logout after inactivity (5–1440 min)</p>
                                </div>
                                <div>
                                    <label class="field-label">Min Password Length</label>
                                    <input type="number" name="password_min_length" value="<?= htmlspecialchars($settings['password_min_length']) ?>" min="4" max="64" class="field-input">
                                </div>
                            </div>
                            <div class="flex items-center justify-between p-3.5 bg-gray-50 dark:bg-slate-700/50 rounded-xl">
                                <div class="min-w-0 flex-1 mr-4">
                                    <p class="text-[13px] font-semibold text-gray-900 dark:text-white leading-snug">Require Uppercase in Password</p>
                                    <p class="text-[11px] text-gray-400 dark:text-slate-500 mt-1 leading-relaxed">Passwords must contain at least one uppercase letter</p>
                                </div>
                                <div class="toggle-switch <?= $settings['password_require_uppercase'] ? 'active' : '' ?>" onclick="toggleSwitch(this)">
                                    <input type="hidden" name="password_require_uppercase" value="0">
                                    <input type="checkbox" name="password_require_uppercase" value="1" class="hidden" <?= $settings['password_require_uppercase'] ? 'checked' : '' ?>>
                                </div>
                            </div>
                            <div class="flex items-center justify-between p-3.5 bg-gray-50 dark:bg-slate-700/50 rounded-xl">
                                <div class="min-w-0 flex-1 mr-4">
                                    <p class="text-[13px] font-semibold text-gray-900 dark:text-white leading-snug">Require Number in Password</p>
                                    <p class="text-[11px] text-gray-400 dark:text-slate-500 mt-1 leading-relaxed">Passwords must contain at least one number</p>
                                </div>
                                <div class="toggle-switch <?= $settings['password_require_number'] ? 'active' : '' ?>" onclick="toggleSwitch(this)">
                                    <input type="hidden" name="password_require_number" value="0">
                                    <input type="checkbox" name="password_require_number" value="1" class="hidden" <?= $settings['password_require_number'] ? 'checked' : '' ?>>
                                </div>
                            </div>
                            <div class="flex items-center justify-between p-3.5 bg-gray-50 dark:bg-slate-700/50 rounded-xl">
                                <div class="min-w-0 flex-1 mr-4">
                                    <p class="text-[13px] font-semibold text-gray-900 dark:text-white leading-snug">Two-Factor Authentication</p>
                                    <p class="text-[11px] text-gray-400 dark:text-slate-500 mt-1 leading-relaxed">Require 2FA for admin accounts (ready for implementation)</p>
                                </div>
                                <div class="toggle-switch <?= $settings['two_factor_enabled'] ? 'active' : '' ?>" onclick="toggleSwitch(this)">
                                    <input type="hidden" name="two_factor_enabled" value="0">
                                    <input type="checkbox" name="two_factor_enabled" value="1" class="hidden" <?= $settings['two_factor_enabled'] ? 'checked' : '' ?>>
                                </div>
                            </div>
                            <div class="flex items-center justify-between p-3.5 bg-red-50 dark:bg-red-900/20 rounded-xl border border-red-200 dark:border-red-800/30">
                                <div class="min-w-0 flex-1 mr-4">
                                    <p class="text-[13px] font-semibold text-red-700 dark:text-red-400 leading-snug">Maintenance Mode</p>
                                    <p class="text-[11px] text-red-500/70 mt-1 leading-relaxed">Put the platform in maintenance mode (only admins can access)</p>
                                </div>
                                <div class="toggle-switch <?= $settings['maintenance_mode'] ? 'active' : '' ?>" onclick="toggleSwitch(this)">
                                    <input type="hidden" name="maintenance_mode" value="0">
                                    <input type="checkbox" name="maintenance_mode" value="1" class="hidden" <?= $settings['maintenance_mode'] ? 'checked' : '' ?>>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- ═══ 5. JOB SETTINGS ═══════════════════════════════════ -->
                <div id="tab-jobs" class="tab-content-section">
                    <div class="flex items-start justify-between mb-5">
                        <div>
                            <h2 class="text-sm font-bold text-gray-900 dark:text-white">Job Settings</h2>
                            <p class="text-[11px] text-gray-400 dark:text-slate-500 mt-0.5">Budget ranges, duration options, and auto-close rules.</p>
                        </div>
                    </div>
                    <div class="setting-section-card">
                        <div class="flex items-center gap-2.5 mb-4">
                            <div class="w-8 h-8 rounded-lg bg-cyan-50 dark:bg-cyan-900/30 flex items-center justify-center flex-shrink-0">
                                <i data-lucide="briefcase" class="text-cyan-500 text-xs"></i>
                            </div>
                            <div>
                                <h3 class="text-xs font-bold text-gray-900 dark:text-white">Job Limits</h3>
                                <p class="text-[10px] text-gray-400 dark:text-slate-500">Budget ranges and job configuration</p>
                            </div>
                        </div>
                        <div class="space-y-4">
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                <div>
                                    <label class="field-label">Min Budget ($)</label>
                                    <input type="number" name="job_budget_min" value="<?= htmlspecialchars($settings['job_budget_min']) ?>" min="0" step="1" class="field-input">
                                </div>
                                <div>
                                    <label class="field-label">Max Budget ($)</label>
                                    <input type="number" name="job_budget_max" value="<?= htmlspecialchars($settings['job_budget_max']) ?>" min="0" step="1" class="field-input">
                                </div>
                            </div>
                            <div>
                                <label class="field-label">Duration Options (comma-separated)</label>
                                <input type="text" name="job_duration_options" value="<?= htmlspecialchars($settings['job_duration_options']) ?>" class="field-input" placeholder="1 week,2 weeks,1 month,3 months">
                                <p class="text-[10px] text-gray-400 dark:text-slate-500 mt-1">Available duration choices for job posting</p>
                            </div>
                            <div>
                                <label class="field-label">Auto-Close Jobs After (days)</label>
                                <input type="number" name="auto_close_jobs_days" value="<?= htmlspecialchars($settings['auto_close_jobs_days']) ?>" min="1" max="365" class="field-input">
                                <p class="text-[10px] text-gray-400 dark:text-slate-500 mt-1">Automatically close jobs with no activity after this period</p>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- ═══ 6. PROPOSAL SETTINGS ═══════════════════════════════════ -->
                <div id="tab-proposals" class="tab-content-section">
                    <div class="flex items-start justify-between mb-5">
                        <div>
                            <h2 class="text-sm font-bold text-gray-900 dark:text-white">Proposal Settings</h2>
                            <p class="text-[11px] text-gray-400 dark:text-slate-500 mt-0.5">Control proposal limits and expiry behavior.</p>
                        </div>
                    </div>
                    <div class="setting-section-card">
                        <div class="flex items-center gap-2.5 mb-4">
                            <div class="w-8 h-8 rounded-lg bg-indigo-50 dark:bg-indigo-900/30 flex items-center justify-center flex-shrink-0">
                                <i data-lucide="send" class="text-indigo-500 text-xs"></i>
                            </div>
                            <div>
                                <h3 class="text-xs font-bold text-gray-900 dark:text-white">Proposal Limits</h3>
                                <p class="text-[10px] text-gray-400 dark:text-slate-500">Control proposal behavior</p>
                            </div>
                        </div>
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                            <div>
                                <label class="field-label">Max Proposals Per Job</label>
                                <input type="number" name="max_proposals_per_job" value="<?= htmlspecialchars($settings['max_proposals_per_job']) ?>" min="1" max="500" class="field-input">
                                <p class="text-[10px] text-gray-400 dark:text-slate-500 mt-1">Maximum number of proposals a job can receive</p>
                            </div>
                            <div>
                                <label class="field-label">Proposal Expiry (days)</label>
                                <input type="number" name="proposal_expiry_days" value="<?= htmlspecialchars($settings['proposal_expiry_days']) ?>" min="1" max="90" class="field-input">
                                <p class="text-[10px] text-gray-400 dark:text-slate-500 mt-1">Proposals auto-expire after this many days</p>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- ═══ 7. NOTIFICATION SETTINGS ═══════════════════════════════════ -->
                <div id="tab-notifications" class="tab-content-section">
                    <div class="flex items-start justify-between mb-5">
                        <div>
                            <h2 class="text-sm font-bold text-gray-900 dark:text-white">Notification Settings</h2>
                            <p class="text-[11px] text-gray-400 dark:text-slate-500 mt-0.5">Configure which notifications are sent to users.</p>
                        </div>
                    </div>
                    <div class="setting-section-card">
                        <div class="flex items-center gap-2.5 mb-4">
                            <div class="w-8 h-8 rounded-lg bg-pink-50 dark:bg-pink-900/30 flex items-center justify-center flex-shrink-0">
                                <i data-lucide="bell" class="text-pink-500 text-xs"></i>
                            </div>
                            <div>
                                <h3 class="text-xs font-bold text-gray-900 dark:text-white">Notification Toggles</h3>
                                <p class="text-[10px] text-gray-400 dark:text-slate-500">Enable or disable notification channels</p>
                            </div>
                        </div>
                        <div class="space-y-3">
                            <div class="flex items-center justify-between p-3.5 bg-gray-50 dark:bg-slate-700/50 rounded-xl">
                                <div class="min-w-0 flex-1 mr-4">
                                    <p class="text-[13px] font-semibold text-gray-900 dark:text-white leading-snug">Email Notifications</p>
                                    <p class="text-[11px] text-gray-400 dark:text-slate-500 mt-1 leading-relaxed">Send email notifications for important events</p>
                                </div>
                                <div class="toggle-switch <?= $settings['email_notifications'] ? 'active' : '' ?>" onclick="toggleSwitch(this)">
                                    <input type="hidden" name="email_notifications" value="0">
                                    <input type="checkbox" name="email_notifications" value="1" class="hidden" <?= $settings['email_notifications'] ? 'checked' : '' ?>>
                                </div>
                            </div>
                            <div class="flex items-center justify-between p-3.5 bg-gray-50 dark:bg-slate-700/50 rounded-xl">
                                <div class="min-w-0 flex-1 mr-4">
                                    <p class="text-[13px] font-semibold text-gray-900 dark:text-white leading-snug">Proposal Notifications</p>
                                    <p class="text-[11px] text-gray-400 dark:text-slate-500 mt-1 leading-relaxed">Notify clients when they receive new proposals</p>
                                </div>
                                <div class="toggle-switch <?= $settings['proposal_notifications'] ? 'active' : '' ?>" onclick="toggleSwitch(this)">
                                    <input type="hidden" name="proposal_notifications" value="0">
                                    <input type="checkbox" name="proposal_notifications" value="1" class="hidden" <?= $settings['proposal_notifications'] ? 'checked' : '' ?>>
                                </div>
                            </div>
                            <div class="flex items-center justify-between p-3.5 bg-gray-50 dark:bg-slate-700/50 rounded-xl">
                                <div class="min-w-0 flex-1 mr-4">
                                    <p class="text-[13px] font-semibold text-gray-900 dark:text-white leading-snug">Payment Notifications</p>
                                    <p class="text-[11px] text-gray-400 dark:text-slate-500 mt-1 leading-relaxed">Notify users on payment events (deposits, releases, refunds)</p>
                                </div>
                                <div class="toggle-switch <?= $settings['payment_notifications'] ? 'active' : '' ?>" onclick="toggleSwitch(this)">
                                    <input type="hidden" name="payment_notifications" value="0">
                                    <input type="checkbox" name="payment_notifications" value="1" class="hidden" <?= $settings['payment_notifications'] ? 'checked' : '' ?>>
                                </div>
                            </div>
                            <div class="flex items-center justify-between p-3.5 bg-gray-50 dark:bg-slate-700/50 rounded-xl">
                                <div class="min-w-0 flex-1 mr-4">
                                    <p class="text-[13px] font-semibold text-gray-900 dark:text-white leading-snug">Dispute Notifications</p>
                                    <p class="text-[11px] text-gray-400 dark:text-slate-500 mt-1 leading-relaxed">Notify admins and parties when disputes are opened/updated</p>
                                </div>
                                <div class="toggle-switch <?= $settings['dispute_notifications'] ? 'active' : '' ?>" onclick="toggleSwitch(this)">
                                    <input type="hidden" name="dispute_notifications" value="0">
                                    <input type="checkbox" name="dispute_notifications" value="1" class="hidden" <?= $settings['dispute_notifications'] ? 'checked' : '' ?>>
                                </div>
                            </div>
                            <div class="flex items-center justify-between p-3.5 bg-gray-50 dark:bg-slate-700/50 rounded-xl">
                                <div class="min-w-0 flex-1 mr-4">
                                    <p class="text-[13px] font-semibold text-gray-900 dark:text-white leading-snug">Marketing Emails</p>
                                    <p class="text-[11px] text-gray-400 dark:text-slate-500 mt-1 leading-relaxed">Send promotional and marketing emails to users</p>
                                </div>
                                <div class="toggle-switch <?= $settings['marketing_emails'] ? 'active' : '' ?>" onclick="toggleSwitch(this)">
                                    <input type="hidden" name="marketing_emails" value="0">
                                    <input type="checkbox" name="marketing_emails" value="1" class="hidden" <?= $settings['marketing_emails'] ? 'checked' : '' ?>>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- ═══ 8. AI SETTINGS ═══════════════════════════════════ -->
                <div id="tab-ai" class="tab-content-section">
                    <div class="flex items-start justify-between mb-5">
                        <div>
                            <h2 class="text-sm font-bold text-gray-900 dark:text-white">AI Settings</h2>
                            <p class="text-[11px] text-gray-400 dark:text-slate-500 mt-0.5">Configure AI matching and embedding behavior.</p>
                        </div>
                    </div>
                    <div class="setting-section-card">
                        <div class="flex items-center gap-2.5 mb-4">
                            <div class="w-8 h-8 rounded-lg bg-purple-50 dark:bg-purple-900/30 flex items-center justify-center flex-shrink-0">
                                <i data-lucide="brain" class="text-purple-500 text-xs"></i>
                            </div>
                            <div>
                                <h3 class="text-xs font-bold text-gray-900 dark:text-white">AI Matching Engine</h3>
                                <p class="text-[10px] text-gray-400 dark:text-slate-500">Embedding model and similarity thresholds</p>
                            </div>
                        </div>
                        <div class="space-y-4">
                            <div class="flex items-center justify-between p-3.5 bg-gray-50 dark:bg-slate-700/50 rounded-xl">
                                <div class="min-w-0 flex-1 mr-4">
                                    <p class="text-[13px] font-semibold text-gray-900 dark:text-white leading-snug">AI Matching Enabled</p>
                                    <p class="text-[11px] text-gray-400 dark:text-slate-500 mt-1 leading-relaxed">Enable AI-powered job-freelancer matching</p>
                                </div>
                                <div class="toggle-switch <?= $settings['ai_matching_enabled'] ? 'active' : '' ?>" onclick="toggleSwitch(this)">
                                    <input type="hidden" name="ai_matching_enabled" value="0">
                                    <input type="checkbox" name="ai_matching_enabled" value="1" class="hidden" <?= $settings['ai_matching_enabled'] ? 'checked' : '' ?>>
                                </div>
                            </div>
                            <div class="flex items-center justify-between p-3.5 bg-gray-50 dark:bg-slate-700/50 rounded-xl">
                                <div class="min-w-0 flex-1 mr-4">
                                    <p class="text-[13px] font-semibold text-gray-900 dark:text-white leading-snug">Auto-Match</p>
                                    <p class="text-[11px] text-gray-400 dark:text-slate-500 mt-1 leading-relaxed">Automatically suggest matches when jobs are posted</p>
                                </div>
                                <div class="toggle-switch <?= $settings['ai_auto_match'] ? 'active' : '' ?>" onclick="toggleSwitch(this)">
                                    <input type="hidden" name="ai_auto_match" value="0">
                                    <input type="checkbox" name="ai_auto_match" value="1" class="hidden" <?= $settings['ai_auto_match'] ? 'checked' : '' ?>>
                                </div>
                            </div>
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                <div>
                                    <label class="field-label">Embedding Model</label>
                                    <select name="ai_embedding_model" class="field-input">
                                        <option value="text-embedding-ada-002" <?= $settings['ai_embedding_model'] === 'text-embedding-ada-002' ? 'selected' : '' ?>>OpenAI Ada-002</option>
                                        <option value="text-embedding-3-small" <?= $settings['ai_embedding_model'] === 'text-embedding-3-small' ? 'selected' : '' ?>>OpenAI Embedding-3-Small</option>
                                        <option value="text-embedding-3-large" <?= $settings['ai_embedding_model'] === 'text-embedding-3-large' ? 'selected' : '' ?>>OpenAI Embedding-3-Large</option>
                                        <option value="local" <?= $settings['ai_embedding_model'] === 'local' ? 'selected' : '' ?>>Local Model</option>
                                    </select>
                                </div>
                                <div>
                                    <label class="field-label">Min Similarity Score (%)</label>
                                    <input type="number" name="ai_min_similarity" value="<?= htmlspecialchars($settings['ai_min_similarity']) ?>" min="0" max="100" class="field-input">
                                    <p class="text-[10px] text-gray-400 dark:text-slate-500 mt-1">Minimum cosine similarity to show a match</p>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- ═══ 9. EMAIL/SMTP SETTINGS ═══════════════════════════════════ -->
                <div id="tab-email" class="tab-content-section">
                    <div class="flex items-start justify-between mb-5">
                        <div>
                            <h2 class="text-sm font-bold text-gray-900 dark:text-white">Email / SMTP Settings</h2>
                            <p class="text-[11px] text-gray-400 dark:text-slate-500 mt-0.5">Configure outgoing email server for notifications.</p>
                        </div>
                    </div>
                    <div class="setting-section-card">
                        <div class="flex items-center gap-2.5 mb-4">
                            <div class="w-8 h-8 rounded-lg bg-teal-50 dark:bg-teal-900/30 flex items-center justify-center flex-shrink-0">
                                <i data-lucide="mail" class="text-teal-500 text-xs"></i>
                            </div>
                            <div>
                                <h3 class="text-xs font-bold text-gray-900 dark:text-white">SMTP Configuration</h3>
                                <p class="text-[10px] text-gray-400 dark:text-slate-500">Outgoing mail server settings</p>
                            </div>
                        </div>
                        <div class="space-y-4">
                            <div class="flex items-center justify-between p-3.5 bg-gray-50 dark:bg-slate-700/50 rounded-xl">
                                <div class="min-w-0 flex-1 mr-4">
                                    <p class="text-[13px] font-semibold text-gray-900 dark:text-white leading-snug">Enable SMTP</p>
                                    <p class="text-[11px] text-gray-400 dark:text-slate-500 mt-1 leading-relaxed">Use SMTP server instead of PHP mail()</p>
                                </div>
                                <div class="toggle-switch <?= $settings['smtp_enabled'] ? 'active' : '' ?>" onclick="toggleSwitch(this)">
                                    <input type="hidden" name="smtp_enabled" value="0">
                                    <input type="checkbox" name="smtp_enabled" value="1" class="hidden" <?= $settings['smtp_enabled'] ? 'checked' : '' ?>>
                                </div>
                            </div>
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                <div>
                                    <label class="field-label">SMTP Host</label>
                                    <input type="text" name="smtp_host" value="<?= htmlspecialchars($settings['smtp_host']) ?>" class="field-input" placeholder="smtp.gmail.com">
                                </div>
                                <div>
                                    <label class="field-label">SMTP Port</label>
                                    <input type="number" name="smtp_port" value="<?= htmlspecialchars($settings['smtp_port']) ?>" min="1" max="65535" class="field-input">
                                </div>
                            </div>
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                <div>
                                    <label class="field-label">Username</label>
                                    <input type="text" name="smtp_username" value="<?= htmlspecialchars($settings['smtp_username']) ?>" class="field-input">
                                </div>
                                <div>
                                    <label class="field-label">Password</label>
                                    <input type="password" name="smtp_password" value="<?= htmlspecialchars($settings['smtp_password']) ?>" class="field-input" placeholder="Leave blank to keep current">
                                </div>
                            </div>
                            <div>
                                <label class="field-label">Encryption</label>
                                <select name="smtp_encryption" class="field-input">
                                    <option value="tls" <?= $settings['smtp_encryption'] === 'tls' ? 'selected' : '' ?>>TLS</option>
                                    <option value="ssl" <?= $settings['smtp_encryption'] === 'ssl' ? 'selected' : '' ?>>SSL</option>
                                    <option value="none" <?= $settings['smtp_encryption'] === 'none' ? 'selected' : '' ?>>None</option>
                                </select>
                            </div>
                            <!-- Test Email -->
                            <div class="flex items-center gap-3 p-3.5 bg-gray-50 dark:bg-slate-700/50 rounded-xl mt-2">
                                <div class="flex-1">
                                    <p class="text-[13px] font-semibold text-gray-900 dark:text-white">Send Test Email</p>
                                    <p class="text-[11px] text-gray-400 dark:text-slate-500 mt-0.5">Verify your SMTP configuration works.</p>
                                </div>
                                <div class="flex items-center gap-2">
                                    <input type="email" id="testEmailInput" placeholder="test@example.com"
                                           class="px-3 py-1.5 rounded-lg border border-gray-200 dark:border-slate-600 bg-white dark:bg-slate-800 text-sm w-56">
                                    <button type="button" onclick="sendTestEmail()" id="testEmailBtn"
                                            class="px-4 py-1.5 rounded-lg bg-indigo-600 hover:bg-indigo-700 text-white text-sm font-semibold transition-colors whitespace-nowrap">
                                        Send Test
                                    </button>
                                </div>
                            </div>
                            <!-- Email Logs Link -->
                            <div class="mt-3">
                                <a href="email_logs.php" class="inline-flex items-center gap-1.5 text-[12px] text-indigo-600 dark:text-indigo-400 hover:underline font-medium">
                                    <i data-lucide="scroll-text" class="w-3.5 h-3.5"></i> View Email Logs
                                </a>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- ═══ 10. APPEARANCE SETTINGS ═══════════════════════════════════ -->
                <!-- <div id="tab-appearance" class="tab-content-section">
                    <div class="flex items-start justify-between mb-5">
                        <div>
                            <h2 class="text-sm font-bold text-gray-900 dark:text-white">Appearance</h2>
                            <p class="text-[11px] text-gray-400 dark:text-slate-500 mt-0.5">Customize the look and feel of the admin dashboard.</p>
                        </div>
                    </div>
                    <div class="setting-section-card">
                        <div class="flex items-center gap-2.5 mb-4">
                            <div class="w-8 h-8 rounded-lg bg-rose-50 dark:bg-rose-900/30 flex items-center justify-center flex-shrink-0">
                                <i data-lucide="palette" class="text-rose-500 text-xs"></i>
                            </div>
                            <div>
                                <h3 class="text-xs font-bold text-gray-900 dark:text-white">Theme Settings</h3>
                                <p class="text-[10px] text-gray-400 dark:text-slate-500">Dark mode and accent colors</p>
                            </div>
                        </div>
                        <div class="space-y-4">
                            <div class="flex items-center justify-between p-3.5 bg-gray-50 dark:bg-slate-700/50 rounded-xl">
                                <div>
                                    <p class="text-[13px] font-semibold text-gray-900 dark:text-white">Dark Mode</p>
                                    <p class="text-[11px] text-gray-400 dark:text-slate-500">Enable dark mode for the admin dashboard</p>
                                </div>
                                <div class="toggle-switch <?= $settings['dark_mode'] ? 'active' : '' ?>" onclick="toggleSwitch(this)">
                                    <input type="hidden" name="dark_mode" value="0">
                                    <input type="checkbox" name="dark_mode" value="1" class="hidden" <?= $settings['dark_mode'] ? 'checked' : '' ?>>
                                </div>
                            </div>
                            <div>
                                <label class="field-label mb-2 block">Theme Color</label>
                                <div class="flex items-center gap-4">
                                    <input type="color" name="theme_color" value="<?= htmlspecialchars($settings['theme_color']) ?>" class="w-12 h-12 rounded-xl border-2 border-gray-200 dark:border-slate-600 cursor-pointer p-0.5">
                                    <div class="flex gap-2">
                                        <?php foreach (['#2563eb', '#7c3aed', '#059669', '#ea580c', '#dc2626', '#0891b2', '#ca8a04', '#db2777'] as $color): ?>
                                        <button type="button" onclick="document.querySelector('input[name=theme_color]').value='<?= $color ?>'" class="w-8 h-8 rounded-full border-2 border-gray-100 dark:border-slate-600 hover:scale-110 transition-transform <?= $settings['theme_color'] === $color ? 'ring-2 ring-offset-2 ring-blue-500' : '' ?>" style="background:<?= $color ?>"></button>
                                        <?php endforeach; ?>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div> -->

                <!-- ═══ 11. SYSTEM INFORMATION ═══════════════════════════════════ -->
                <div id="tab-system" class="tab-content-section">
                    <div class="flex items-start justify-between mb-5">
                        <div>
                            <h2 class="text-sm font-bold text-gray-900 dark:text-white">System Information</h2>
                            <p class="text-[11px] text-gray-400 dark:text-slate-500 mt-0.5">Server and environment details.</p>
                        </div>
                    </div>
                    <div class="setting-section-card">
                        <div class="flex items-center gap-2.5 mb-4">
                            <div class="w-8 h-8 rounded-lg bg-slate-100 dark:bg-slate-700 flex items-center justify-center flex-shrink-0">
                                <i data-lucide="server" class="text-slate-500 text-xs"></i>
                            </div>
                            <div>
                                <h3 class="text-xs font-bold text-gray-900 dark:text-white">Server Environment</h3>
                                <p class="text-[10px] text-gray-400 dark:text-slate-500">Runtime and infrastructure details</p>
                            </div>
                        </div>
                        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-3 mb-4">
                            <div class="flex items-center gap-3 p-3 bg-gray-50 dark:bg-slate-700/50 rounded-xl">
                                <div class="w-9 h-9 rounded-lg bg-purple-50 dark:bg-purple-900/30 flex items-center justify-center"><i data-lucide="file-code" class=""></i></div>
                                <div><p class="text-[10px] text-gray-400 dark:text-slate-500">PHP</p><p class="text-xs font-bold text-gray-900 dark:text-white"><?= sanitize_string($phpVersion) ?></p></div>
                            </div>
                            <div class="flex items-center gap-3 p-3 bg-gray-50 dark:bg-slate-700/50 rounded-xl">
                                <div class="w-9 h-9 rounded-lg bg-blue-50 dark:bg-blue-900/30 flex items-center justify-center"><i data-lucide="database" class="text-blue-500 text-sm"></i></div>
                                <div><p class="text-[10px] text-gray-400 dark:text-slate-500">MySQL</p><p class="text-xs font-bold text-gray-900 dark:text-white"><?= sanitize_string($mysqlVersion) ?></p></div>
                            </div>
                            <div class="flex items-center gap-3 p-3 bg-gray-50 dark:bg-slate-700/50 rounded-xl">
                                <div class="w-9 h-9 rounded-lg bg-emerald-50 dark:bg-emerald-900/30 flex items-center justify-center"><i data-lucide="server" class="text-emerald-500 text-sm"></i></div>
                                <div><p class="text-[10px] text-gray-400 dark:text-slate-500">Server</p><p class="text-xs font-bold text-gray-900 dark:text-white truncate max-w-[120px]" title="<?= sanitize_string($serverSoftware) ?>"><?= sanitize_string($serverSoftware) ?></p></div>
                            </div>
                            <div class="flex items-center gap-3 p-3 bg-gray-50 dark:bg-slate-700/50 rounded-xl">
                                <div class="w-9 h-9 rounded-lg bg-amber-50 dark:bg-amber-900/30 flex items-center justify-center"><i data-lucide="hard-drive" class="text-amber-500 text-sm"></i></div>
                                <div><p class="text-[10px] text-gray-400 dark:text-slate-500">Disk</p><p class="text-xs font-bold text-gray-900 dark:text-white"><?= $diskUsed ? format_bytes($diskUsed) . ' / ' . format_bytes($diskTotal) : 'N/A' ?></p></div>
                            </div>
                        </div>
                        <?php if ($diskTotal): ?>
                        <div class="mb-4">
                            <div class="w-full bg-gray-100 dark:bg-slate-700 rounded-full h-2">
                                <div class="h-2 rounded-full transition-all duration-500 <?= $diskPct > 90 ? 'bg-red-500' : ($diskPct > 70 ? 'bg-amber-500' : 'bg-blue-500') ?>" style="width:<?= $diskPct ?>%"></div>
                            </div>
                            <p class="text-[10px] text-gray-400 dark:text-slate-500 mt-1"><?= $diskPct ?>% disk used</p>
                        </div>
                        <?php endif; ?>
                        <div class="grid grid-cols-2 gap-3 text-xs">
                            <div class="flex justify-between p-2.5 bg-gray-50 dark:bg-slate-700/50 rounded-lg">
                                <span class="text-gray-400 dark:text-slate-500">Settings File</span>
                                <span class="font-mono text-gray-600 dark:text-slate-300">platform_settings.json</span>
                            </div>
                            <div class="flex justify-between p-2.5 bg-gray-50 dark:bg-slate-700/50 rounded-lg">
                                <span class="text-gray-400 dark:text-slate-500">File Size</span>
                                <span class="font-mono text-gray-600 dark:text-slate-300"><?= $settingsFile && file_exists($settingsFile) ? format_bytes(filesize($settingsFile)) : 'N/A' ?></span>
                            </div>
                            <div class="flex justify-between p-2.5 bg-gray-50 dark:bg-slate-700/50 rounded-lg">
                                <span class="text-gray-400 dark:text-slate-500">Last Modified</span>
                                <span class="font-mono text-gray-600 dark:text-slate-300"><?= $settingsFile && file_exists($settingsFile) ? date('M j, H:i', filemtime($settingsFile)) : 'N/A' ?></span>
                            </div>
                            <div class="flex justify-between p-2.5 bg-gray-50 dark:bg-slate-700/50 rounded-lg">
                                <span class="text-gray-400 dark:text-slate-500">Upload Dir</span>
                                <span class="font-mono text-gray-600 dark:text-slate-300">assets/upload/settings/</span>
                            </div>
                        </div>
                    </div>
                </div>

            </div><!-- /content area -->
        </div><!-- /main layout -->
    </form>

    <script>
    // ── Tab Navigation ───────────────────────────────────────────────
    function showTab(tabName) {
        document.querySelectorAll('.tab-content-section').forEach(function(el) {
            el.classList.remove('active');
        });
        document.querySelectorAll('.settings-nav-item').forEach(function(el) {
            el.classList.remove('active');
        });
        var tabEl = document.getElementById('tab-' + tabName);
        if (tabEl) tabEl.classList.add('active');
        var btn = document.querySelector('[data-tab="' + tabName + '"]');
        if (btn) btn.classList.add('active');
    }

    // ── Toggle Switch ────────────────────────────────────────────────
    function toggleSwitch(el) {
        var checkbox = el.querySelector('input[type=checkbox]');
        checkbox.checked = !checkbox.checked;
        el.classList.toggle('active');
    }

    // ── Form Reset ───────────────────────────────────────────────────
    function resetForm() {
        if (confirm('Reset all fields to their saved values?')) {
            window.location.reload();
        }
    }

    // ── Logo Preview ─────────────────────────────────────────────────
    function previewLogo(input) {
        if (input.files && input.files[0]) {
            var reader = new FileReader();
            reader.onload = function(e) {
                document.getElementById('logoPreviewImg').src = e.target.result;
                document.getElementById('logoPreviewEmpty').classList.add('hidden');
                document.getElementById('logoPreviewActive').classList.remove('hidden');
            };
            reader.readAsDataURL(input.files[0]);
        }
    }

    // ── Favicon Preview ──────────────────────────────────────────────
    function previewFavicon(input) {
        if (input.files && input.files[0]) {
            var reader = new FileReader();
            reader.onload = function(e) {
                document.getElementById('faviconPreviewImg').src = e.target.result;
                document.getElementById('faviconPreviewEmpty').classList.add('hidden');
                document.getElementById('faviconPreviewActive').classList.remove('hidden');
            };
            reader.readAsDataURL(input.files[0]);
        }
    }

    // ── Send Test Email (AJAX) ────────────────────────────────────────
    function sendTestEmail() {
        var email = document.getElementById('testEmailInput').value.trim();
        if (!email) {
            alert('Please enter a valid email address.');
            return;
        }
        var btn = document.getElementById('testEmailBtn');
        btn.disabled = true;
        btn.textContent = 'Sending...';
        var formData = new FormData();
        formData.append('test_email', email);
        formData.append('action', 'test_email');
        formData.append('csrf_token', document.querySelector('input[name="csrf_token"]').value);
        var xhr = new XMLHttpRequest();
        xhr.open('POST', 'test_email.php', true);
        xhr.setRequestHeader('X-Requested-With', 'XMLHttpRequest');
        xhr.onload = function() {
            btn.disabled = false;
            btn.textContent = 'Send Test';
            try {
                var resp = JSON.parse(xhr.responseText);
                alert(resp.success ? 'Test email sent successfully to ' + email + '. Check your inbox.' : 'Failed: ' + resp.message);
            } catch(e) {
                alert('Test email sent to ' + email + '. Check your inbox.');
            }
        };
        xhr.onerror = function() {
            btn.disabled = false;
            btn.textContent = 'Send Test';
            alert('Failed to send test email. Check SMTP settings.');
        };
        xhr.send(formData);
    }

    // ── Drag-and-Drop Visual Feedback ────────────────────────────────
    document.querySelectorAll('.upload-zone').forEach(function(zone) {
        zone.addEventListener('dragover', function(e) {
            e.preventDefault();
            zone.classList.add('dragover');
        });
        zone.addEventListener('dragleave', function() {
            zone.classList.remove('dragover');
        });
        zone.addEventListener('drop', function() {
            zone.classList.remove('dragover');
        });
    });
    </script>
<?php require_once __DIR__ . '/../components/layout_end.php'; ?>
