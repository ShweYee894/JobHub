<?php

/**
 * Admin Settings
 * Platform settings management with JSON file storage.
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

    $settings['smtp_enabled'] = (($_POST['smtp_enabled'] ?? '0') === '1') ? 1 : 0;

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

$logoUrl = $settings['platform_logo'] ? '/finalproject/assets/upload/settings/' . htmlspecialchars($settings['platform_logo']) : '';
$faviconUrl = $settings['platform_favicon'] ? '/finalproject/assets/upload/settings/' . htmlspecialchars($settings['platform_favicon']) : '';

$_userId = $_SESSION['user_id'];
$sStmt = $conn->prepare('SELECT name, profile_image FROM users WHERE id = ?');
$sStmt->bind_param('i', $_userId);
$sStmt->execute();
$_navUserRow = $sStmt->get_result()->fetch_assoc();
$sStmt->close();
$adminName = $_navUserRow['name'] ?? 'Admin';

$conn->close();

$navItems = [
    ['key' => 'dashboard', 'label' => 'Dashboard', 'url' => 'dashboard.php', 'icon' => 'fa-th-large'],
    ['key' => 'users', 'label' => 'Users', 'url' => 'users.php', 'icon' => 'fa-users'],
    ['key' => 'jobs', 'label' => 'Jobs', 'url' => 'jobs.php', 'icon' => 'fa-briefcase'],
    ['key' => 'payments', 'label' => 'Payments', 'url' => 'payments.php', 'icon' => 'fa-credit-card'],
    ['key' => 'fraud', 'label' => 'Fraud', 'url' => 'fraud_detection.php', 'icon' => 'fa-shield-halved'],
    ['key' => 'matching', 'label' => 'AI Matching', 'url' => 'ai_matching.php', 'icon' => 'fa-brain'],
    ['key' => 'settings', 'label' => 'Settings', 'url' => 'settings.php', 'icon' => 'fa-cog'],
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
    .toggle-switch{position:relative;width:44px;height:24px;background:#cbd5e1;border-radius:12px;cursor:pointer;transition:background .2s}
    .toggle-switch.active{background:#2563eb}
    .toggle-switch::after{content:'';position:absolute;top:2px;left:2px;width:20px;height:20px;background:#fff;border-radius:50%;transition:transform .2s;box-shadow:0 1px 3px rgba(0,0,0,.15)}
    .toggle-switch.active::after{transform:translateX(20px)}
    .btn-grad{background:linear-gradient(135deg,#2563eb,#0ea5e9);transition:opacity .25s,transform .2s}
    .btn-grad:hover{opacity:.88;transform:translateY(-2px)}
    </style>



                <?php display_flash('success'); ?>
                <?php display_flash('error'); ?>
                <?php display_flash('info'); ?>

                <form method="POST" enctype="multipart/form-data" id="settingsForm">
                    <?= csrf_field(); ?>

                    <!-- ═══ TAB NAVIGATION ═══════════════════════════════════ -->
                    <div class="bg-white rounded-2xl border border-gray-100 shadow-sm p-1 fade-in inline-flex flex-wrap gap-1">
                        <button type="button" onclick="showTab('general')" class="tab-btn active px-4 py-2.5 rounded-xl text-sm font-medium transition-all" data-tab="general">
                            <i class="fas fa-cog mr-2"></i>General
                        </button>
                        <button type="button" onclick="showTab('payment')" class="tab-btn px-4 py-2.5 rounded-xl text-sm font-medium transition-all text-gray-500 hover:bg-gray-50" data-tab="payment">
                            <i class="fas fa-credit-card mr-2"></i>Payment
                        </button>
                        <button type="button" onclick="showTab('registration')" class="tab-btn px-4 py-2.5 rounded-xl text-sm font-medium transition-all text-gray-500 hover:bg-gray-50" data-tab="registration">
                            <i class="fas fa-user-plus mr-2"></i>Registration
                        </button>
                        <button type="button" onclick="showTab('security')" class="tab-btn px-4 py-2.5 rounded-xl text-sm font-medium transition-all text-gray-500 hover:bg-gray-50" data-tab="security">
                            <i class="fas fa-shield-alt mr-2"></i>Security
                        </button>
                        <button type="button" onclick="showTab('email')" class="tab-btn px-4 py-2.5 rounded-xl text-sm font-medium transition-all text-gray-500 hover:bg-gray-50" data-tab="email">
                            <i class="fas fa-envelope mr-2"></i>Email
                        </button>
                        <button type="button" onclick="showTab('appearance')" class="tab-btn px-4 py-2.5 rounded-xl text-sm font-medium transition-all text-gray-500 hover:bg-gray-50" data-tab="appearance">
                            <i class="fas fa-palette mr-2"></i>Appearance
                        </button>
                        <button type="button" onclick="showTab('system')" class="tab-btn px-4 py-2.5 rounded-xl text-sm font-medium transition-all text-gray-500 hover:bg-gray-50" data-tab="system">
                            <i class="fas fa-server mr-2"></i>System
                        </button>
                    </div>

                    <!-- ═══ GENERAL SETTINGS ═══════════════════════════════════ -->
                    <div id="tab-general" class="tab-content fade-in" style="animation-delay:.05s">
                        <div class="bg-white rounded-2xl border border-gray-100 shadow-sm overflow-hidden">
                            <div class="p-6 border-b border-gray-100">
                                <h2 class="text-base font-bold text-gray-900 flex items-center gap-2">
                                    <i class="fas fa-cog text-blue-500"></i> General Settings
                                </h2>
                                <p class="text-xs text-gray-400 mt-1">Basic platform configuration</p>
                            </div>
                            <div class="p-6 space-y-5">
                                <!-- Platform Name -->
                                <div>
                                    <label class="block text-sm font-semibold text-gray-700 mb-1.5">Platform Name</label>
                                    <input type="text" name="platform_name" value="<?= htmlspecialchars($settings['platform_name']) ?>"
                                        class="w-full px-4 py-2.5 rounded-xl border border-gray-200 text-sm focus:ring-2 focus:ring-blue-500 focus:border-transparent outline-none transition-all">
                                </div>

                                <!-- Logo & Favicon -->
                                <div class="grid grid-cols-1 md:grid-cols-2 gap-5">
                                    <div>
                                        <label class="block text-sm font-semibold text-gray-700 mb-1.5">Platform Logo</label>
                                        <div class="flex items-center gap-4">
                                            <div class="w-16 h-16 rounded-xl border-2 border-dashed border-gray-200 flex items-center justify-center bg-gray-50 overflow-hidden flex-shrink-0">
                                                <?php if ($logoUrl): ?>
                                                <img src="<?= $logoUrl ?>" class="w-full h-full object-contain p-1" alt="Logo">
                                                <?php else: ?>
                                                <i class="fas fa-image text-gray-300 text-xl"></i>
                                                <?php endif; ?>
                                            </div>
                                            <div class="flex-1">
                                                <input type="file" name="platform_logo" accept="image/*"
                                                    class="w-full text-sm text-gray-500 file:mr-3 file:py-2 file:px-4 file:rounded-lg file:border-0 file:text-sm file:font-semibold file:bg-blue-50 file:text-blue-600 hover:file:bg-blue-100 file:cursor-pointer">
                                                <p class="text-[11px] text-gray-400 mt-1">JPG, PNG, GIF, WebP (max 2MB)</p>
                                            </div>
                                        </div>
                                    </div>
                                    <div>
                                        <label class="block text-sm font-semibold text-gray-700 mb-1.5">Favicon</label>
                                        <div class="flex items-center gap-4">
                                            <div class="w-10 h-10 rounded-lg border-2 border-dashed border-gray-200 flex items-center justify-center bg-gray-50 overflow-hidden flex-shrink-0">
                                                <?php if ($faviconUrl): ?>
                                                <img src="<?= $faviconUrl ?>" class="w-full h-full object-contain p-0.5" alt="Favicon">
                                                <?php else: ?>
                                                <i class="fas fa-star text-gray-300 text-sm"></i>
                                                <?php endif; ?>
                                            </div>
                                            <div class="flex-1">
                                                <input type="file" name="platform_favicon" accept="image/*"
                                                    class="w-full text-sm text-gray-500 file:mr-3 file:py-2 file:px-4 file:rounded-lg file:border-0 file:text-sm file:font-semibold file:bg-blue-50 file:text-blue-600 hover:file:bg-blue-100 file:cursor-pointer">
                                                <p class="text-[11px] text-gray-400 mt-1">JPG, PNG, GIF, WebP (max 2MB)</p>
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                <!-- Emails -->
                                <div class="grid grid-cols-1 md:grid-cols-2 gap-5">
                                    <div>
                                        <label class="block text-sm font-semibold text-gray-700 mb-1.5">Platform Email</label>
                                        <input type="email" name="platform_email" value="<?= htmlspecialchars($settings['platform_email']) ?>"
                                            class="w-full px-4 py-2.5 rounded-xl border border-gray-200 text-sm focus:ring-2 focus:ring-blue-500 focus:border-transparent outline-none transition-all">
                                    </div>
                                    <div>
                                        <label class="block text-sm font-semibold text-gray-700 mb-1.5">Support Email</label>
                                        <input type="email" name="support_email" value="<?= htmlspecialchars($settings['support_email']) ?>"
                                            class="w-full px-4 py-2.5 rounded-xl border border-gray-200 text-sm focus:ring-2 focus:ring-blue-500 focus:border-transparent outline-none transition-all">
                                    </div>
                                </div>

                                <!-- Phone & Address -->
                                <div class="grid grid-cols-1 md:grid-cols-2 gap-5">
                                    <div>
                                        <label class="block text-sm font-semibold text-gray-700 mb-1.5">Platform Phone</label>
                                        <input type="text" name="platform_phone" value="<?= htmlspecialchars($settings['platform_phone']) ?>"
                                            class="w-full px-4 py-2.5 rounded-xl border border-gray-200 text-sm focus:ring-2 focus:ring-blue-500 focus:border-transparent outline-none transition-all">
                                    </div>
                                    <div>
                                        <label class="block text-sm font-semibold text-gray-700 mb-1.5">Platform Address</label>
                                        <input type="text" name="platform_address" value="<?= htmlspecialchars($settings['platform_address']) ?>"
                                            class="w-full px-4 py-2.5 rounded-xl border border-gray-200 text-sm focus:ring-2 focus:ring-blue-500 focus:border-transparent outline-none transition-all">
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- ═══ PAYMENT SETTINGS ═══════════════════════════════════ -->
                    <div id="tab-payment" class="tab-content hidden fade-in">
                        <div class="bg-white rounded-2xl border border-gray-100 shadow-sm overflow-hidden">
                            <div class="p-6 border-b border-gray-100">
                                <h2 class="text-base font-bold text-gray-900 flex items-center gap-2">
                                    <i class="fas fa-credit-card text-emerald-500"></i> Payment Settings
                                </h2>
                                <p class="text-xs text-gray-400 mt-1">Configure fees, wallets, and escrow</p>
                            </div>
                            <div class="p-6 space-y-5">
                                <div class="grid grid-cols-1 md:grid-cols-2 gap-5">
                                    <div>
                                        <label class="block text-sm font-semibold text-gray-700 mb-1.5">Platform Fee (%)</label>
                                        <input type="number" name="platform_fee_percentage" value="<?= htmlspecialchars($settings['platform_fee_percentage']) ?>"
                                            min="0" max="100" step="0.5"
                                            class="w-full px-4 py-2.5 rounded-xl border border-gray-200 text-sm focus:ring-2 focus:ring-blue-500 focus:border-transparent outline-none transition-all">
                                        <p class="text-[11px] text-gray-400 mt-1">Percentage charged on each transaction</p>
                                    </div>
                                    <div>
                                        <label class="block text-sm font-semibold text-gray-700 mb-1.5">Default Wallet Balance ($)</label>
                                        <input type="number" name="default_wallet_balance" value="<?= htmlspecialchars($settings['default_wallet_balance']) ?>"
                                            min="0" step="0.01"
                                            class="w-full px-4 py-2.5 rounded-xl border border-gray-200 text-sm focus:ring-2 focus:ring-blue-500 focus:border-transparent outline-none transition-all">
                                        <p class="text-[11px] text-gray-400 mt-1">Starting balance for new users</p>
                                    </div>
                                </div>
                                <div class="flex items-center justify-between p-4 bg-gray-50 rounded-xl">
                                    <div>
                                        <p class="text-sm font-semibold text-gray-900">Escrow System</p>
                                        <p class="text-xs text-gray-400">Hold payment in escrow until work is approved</p>
                                    </div>
                                    <div class="toggle-switch <?= $settings['escrow_enabled'] ? 'active' : '' ?>" onclick="toggleSwitch(this)" data-field="escrow_enabled">
                                        <input type="hidden" name="escrow_enabled" value="0">
                                        <input type="checkbox" name="escrow_enabled" value="1" class="hidden" <?= $settings['escrow_enabled'] ? 'checked' : '' ?>>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- ═══ REGISTRATION SETTINGS ═══════════════════════════════════ -->
                    <div id="tab-registration" class="tab-content hidden fade-in">
                        <div class="bg-white rounded-2xl border border-gray-100 shadow-sm overflow-hidden">
                            <div class="p-6 border-b border-gray-100">
                                <h2 class="text-base font-bold text-gray-900 flex items-center gap-2">
                                    <i class="fas fa-user-plus text-violet-500"></i> Registration Settings
                                </h2>
                                <p class="text-xs text-gray-400 mt-1">Control who can register on the platform</p>
                            </div>
                            <div class="p-6 space-y-3">
                                <div class="flex items-center justify-between p-4 bg-gray-50 rounded-xl">
                                    <div>
                                        <p class="text-sm font-semibold text-gray-900">Enable Registration</p>
                                        <p class="text-xs text-gray-400">Allow new users to register on the platform</p>
                                    </div>
                                    <div class="toggle-switch <?= $settings['registration_enabled'] ? 'active' : '' ?>" onclick="toggleSwitch(this)" data-field="registration_enabled">
                                        <input type="hidden" name="registration_enabled" value="0">
                                        <input type="checkbox" name="registration_enabled" value="1" class="hidden" <?= $settings['registration_enabled'] ? 'checked' : '' ?>>
                                    </div>
                                </div>
                                <div class="flex items-center justify-between p-4 bg-gray-50 rounded-xl">
                                    <div>
                                        <p class="text-sm font-semibold text-gray-900">Freelancer Registration</p>
                                        <p class="text-xs text-gray-400">Allow new freelancers to sign up</p>
                                    </div>
                                    <div class="toggle-switch <?= $settings['freelancer_registration'] ? 'active' : '' ?>" onclick="toggleSwitch(this)" data-field="freelancer_registration">
                                        <input type="hidden" name="freelancer_registration" value="0">
                                        <input type="checkbox" name="freelancer_registration" value="1" class="hidden" <?= $settings['freelancer_registration'] ? 'checked' : '' ?>>
                                    </div>
                                </div>
                                <div class="flex items-center justify-between p-4 bg-gray-50 rounded-xl">
                                    <div>
                                        <p class="text-sm font-semibold text-gray-900">Client Registration</p>
                                        <p class="text-xs text-gray-400">Allow new clients to sign up</p>
                                    </div>
                                    <div class="toggle-switch <?= $settings['client_registration'] ? 'active' : '' ?>" onclick="toggleSwitch(this)" data-field="client_registration">
                                        <input type="hidden" name="client_registration" value="0">
                                        <input type="checkbox" name="client_registration" value="1" class="hidden" <?= $settings['client_registration'] ? 'checked' : '' ?>>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- ═══ SECURITY SETTINGS ═══════════════════════════════════ -->
                    <div id="tab-security" class="tab-content hidden fade-in">
                        <div class="bg-white rounded-2xl border border-gray-100 shadow-sm overflow-hidden">
                            <div class="p-6 border-b border-gray-100">
                                <h2 class="text-base font-bold text-gray-900 flex items-center gap-2">
                                    <i class="fas fa-shield-alt text-amber-500"></i> Security Settings
                                </h2>
                                <p class="text-xs text-gray-400 mt-1">Session, password policy, and security features</p>
                            </div>
                            <div class="p-6 space-y-5">
                                <div class="grid grid-cols-1 md:grid-cols-2 gap-5">
                                    <div>
                                        <label class="block text-sm font-semibold text-gray-700 mb-1.5">Session Timeout (minutes)</label>
                                        <input type="number" name="session_timeout" value="<?= htmlspecialchars($settings['session_timeout']) ?>"
                                            min="5" max="1440"
                                            class="w-full px-4 py-2.5 rounded-xl border border-gray-200 text-sm focus:ring-2 focus:ring-blue-500 focus:border-transparent outline-none transition-all">
                                        <p class="text-[11px] text-gray-400 mt-1">Auto-logout after inactivity (5–1440 min)</p>
                                    </div>
                                    <div>
                                        <label class="block text-sm font-semibold text-gray-700 mb-1.5">Min Password Length</label>
                                        <input type="number" name="password_min_length" value="<?= htmlspecialchars($settings['password_min_length']) ?>"
                                            min="4" max="64"
                                            class="w-full px-4 py-2.5 rounded-xl border border-gray-200 text-sm focus:ring-2 focus:ring-blue-500 focus:border-transparent outline-none transition-all">
                                    </div>
                                </div>
                                <div class="flex items-center justify-between p-4 bg-gray-50 rounded-xl">
                                    <div>
                                        <p class="text-sm font-semibold text-gray-900">Require Uppercase in Password</p>
                                        <p class="text-xs text-gray-400">Passwords must contain at least one uppercase letter</p>
                                    </div>
                                    <div class="toggle-switch <?= $settings['password_require_uppercase'] ? 'active' : '' ?>" onclick="toggleSwitch(this)" data-field="password_require_uppercase">
                                        <input type="hidden" name="password_require_uppercase" value="0">
                                        <input type="checkbox" name="password_require_uppercase" value="1" class="hidden" <?= $settings['password_require_uppercase'] ? 'checked' : '' ?>>
                                    </div>
                                </div>
                                <div class="flex items-center justify-between p-4 bg-gray-50 rounded-xl">
                                    <div>
                                        <p class="text-sm font-semibold text-gray-900">Require Number in Password</p>
                                        <p class="text-xs text-gray-400">Passwords must contain at least one number</p>
                                    </div>
                                    <div class="toggle-switch <?= $settings['password_require_number'] ? 'active' : '' ?>" onclick="toggleSwitch(this)" data-field="password_require_number">
                                        <input type="hidden" name="password_require_number" value="0">
                                        <input type="checkbox" name="password_require_number" value="1" class="hidden" <?= $settings['password_require_number'] ? 'checked' : '' ?>>
                                    </div>
                                </div>
                                <div class="flex items-center justify-between p-4 bg-gray-50 rounded-xl">
                                    <div>
                                        <p class="text-sm font-semibold text-gray-900">Two-Factor Authentication</p>
                                        <p class="text-xs text-gray-400">Require 2FA for admin accounts (ready for implementation)</p>
                                    </div>
                                    <div class="toggle-switch <?= $settings['two_factor_enabled'] ? 'active' : '' ?>" onclick="toggleSwitch(this)" data-field="two_factor_enabled">
                                        <input type="hidden" name="two_factor_enabled" value="0">
                                        <input type="checkbox" name="two_factor_enabled" value="1" class="hidden" <?= $settings['two_factor_enabled'] ? 'checked' : '' ?>>
                                    </div>
                                </div>
                                <div class="flex items-center justify-between p-4 bg-red-50 rounded-xl border border-red-100">
                                    <div>
                                        <p class="text-sm font-semibold text-red-900">Maintenance Mode</p>
                                        <p class="text-xs text-red-600/70">Put the platform in maintenance mode (only admins can access)</p>
                                    </div>
                                    <div class="toggle-switch <?= $settings['maintenance_mode'] ? 'active' : '' ?>" onclick="toggleSwitch(this)" data-field="maintenance_mode">
                                        <input type="hidden" name="maintenance_mode" value="0">
                                        <input type="checkbox" name="maintenance_mode" value="1" class="hidden" <?= $settings['maintenance_mode'] ? 'checked' : '' ?>>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- ═══ EMAIL SETTINGS ═══════════════════════════════════ -->
                    <div id="tab-email" class="tab-content hidden fade-in">
                        <div class="bg-white rounded-2xl border border-gray-100 shadow-sm overflow-hidden">
                            <div class="p-6 border-b border-gray-100">
                                <h2 class="text-base font-bold text-gray-900 flex items-center gap-2">
                                    <i class="fas fa-envelope text-cyan-500"></i> Email Settings
                                </h2>
                                <p class="text-xs text-gray-400 mt-1">Configure email delivery method</p>
                            </div>
                            <div class="p-6 space-y-5">
                                <div class="flex items-center justify-between p-4 bg-gray-50 rounded-xl">
                                    <div>
                                        <p class="text-sm font-semibold text-gray-900">Enable SMTP</p>
                                        <p class="text-xs text-gray-400">Use SMTP server for outgoing emails instead of PHP mail()</p>
                                    </div>
                                    <div class="toggle-switch <?= $settings['smtp_enabled'] ? 'active' : '' ?>" onclick="toggleSwitch(this)" data-field="smtp_enabled">
                                        <input type="hidden" name="smtp_enabled" value="0">
                                        <input type="checkbox" name="smtp_enabled" value="1" class="hidden" <?= $settings['smtp_enabled'] ? 'checked' : '' ?>>
                                    </div>
                                </div>
                                <div class="p-4 bg-amber-50 rounded-xl border border-amber-100">
                                    <div class="flex items-start gap-3">
                                        <i class="fas fa-info-circle text-amber-500 mt-0.5"></i>
                                        <div>
                                            <p class="text-sm font-semibold text-amber-900">SMTP Configuration</p>
                                            <p class="text-xs text-amber-700/80 mt-1">SMTP host, port, username, and password should be configured in <code class="bg-amber-100 px-1.5 py-0.5 rounded text-[11px]">config/mail.php</code>. This toggle enables or disables SMTP usage.</p>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- ═══ APPEARANCE SETTINGS ═══════════════════════════════════ -->
                    <div id="tab-appearance" class="tab-content hidden fade-in">
                        <div class="bg-white rounded-2xl border border-gray-100 shadow-sm overflow-hidden">
                            <div class="p-6 border-b border-gray-100">
                                <h2 class="text-base font-bold text-gray-900 flex items-center gap-2">
                                    <i class="fas fa-palette text-pink-500"></i> Appearance
                                </h2>
                                <p class="text-xs text-gray-400 mt-1">Customize the look and feel</p>
                            </div>
                            <div class="p-6 space-y-5">
                                <div class="flex items-center justify-between p-4 bg-gray-50 rounded-xl">
                                    <div>
                                        <p class="text-sm font-semibold text-gray-900">Dark Mode</p>
                                        <p class="text-xs text-gray-400">Enable dark mode for the admin dashboard</p>
                                    </div>
                                    <div class="toggle-switch <?= $settings['dark_mode'] ? 'active' : '' ?>" onclick="toggleSwitch(this)" data-field="dark_mode">
                                        <input type="hidden" name="dark_mode" value="0">
                                        <input type="checkbox" name="dark_mode" value="1" class="hidden" <?= $settings['dark_mode'] ? 'checked' : '' ?>>
                                    </div>
                                </div>
                                <div>
                                    <label class="block text-sm font-semibold text-gray-700 mb-2">Theme Color</label>
                                    <div class="flex items-center gap-4">
                                        <input type="color" name="theme_color" value="<?= htmlspecialchars($settings['theme_color']) ?>"
                                            class="w-12 h-12 rounded-xl border-2 border-gray-200 cursor-pointer p-0.5">
                                        <div class="flex gap-2">
                                            <?php foreach (['#2563eb', '#7c3aed', '#059669', '#ea580c', '#dc2626', '#0891b2', '#ca8a04', '#db2777'] as $color): ?>
                                            <button type="button" onclick="document.querySelector('input[name=theme_color]').value='<?= $color ?>' && this.parentElement.querySelectorAll('button').forEach(b=>b.classList.remove('ring-2','ring-offset-2','ring-blue-500')) && this.classList.add('ring-2','ring-offset-2','ring-blue-500')"
                                                class="w-8 h-8 rounded-full border-2 border-gray-100 hover:scale-110 transition-transform <?= $settings['theme_color'] === $color ? 'ring-2 ring-offset-2 ring-blue-500' : '' ?>"
                                                style="background:<?= $color ?>"></button>
                                            <?php endforeach; ?>
                                        </div>
                                    </div>
                                    <p class="text-[11px] text-gray-400 mt-2">Select a color or use the color picker</p>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- ═══ SYSTEM INFORMATION ═══════════════════════════════════ -->
                    <div id="tab-system" class="tab-content hidden fade-in">
                        <div class="bg-white rounded-2xl border border-gray-100 shadow-sm overflow-hidden">
                            <div class="p-6 border-b border-gray-100">
                                <h2 class="text-base font-bold text-gray-900 flex items-center gap-2">
                                    <i class="fas fa-server text-indigo-500"></i> System Information
                                </h2>
                                <p class="text-xs text-gray-400 mt-1">Server and environment details</p>
                            </div>
                            <div class="p-6">
                                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                    <div class="flex items-center gap-3 p-4 bg-gray-50 rounded-xl">
                                        <div class="w-10 h-10 rounded-lg bg-purple-50 flex items-center justify-center flex-shrink-0">
                                            <i class="fab fa-php text-purple-500"></i>
                                        </div>
                                        <div>
                                            <p class="text-xs text-gray-400">PHP Version</p>
                                            <p class="text-sm font-bold text-gray-900"><?= sanitize_string($phpVersion) ?></p>
                                        </div>
                                    </div>
                                    <div class="flex items-center gap-3 p-4 bg-gray-50 rounded-xl">
                                        <div class="w-10 h-10 rounded-lg bg-blue-50 flex items-center justify-center flex-shrink-0">
                                            <i class="fas fa-database text-blue-500"></i>
                                        </div>
                                        <div>
                                            <p class="text-xs text-gray-400">MySQL Version</p>
                                            <p class="text-sm font-bold text-gray-900"><?= sanitize_string($mysqlVersion) ?></p>
                                        </div>
                                    </div>
                                    <div class="flex items-center gap-3 p-4 bg-gray-50 rounded-xl">
                                        <div class="w-10 h-10 rounded-lg bg-emerald-50 flex items-center justify-center flex-shrink-0">
                                            <i class="fas fa-server text-emerald-500"></i>
                                        </div>
                                        <div>
                                            <p class="text-xs text-gray-400">Server Software</p>
                                            <p class="text-sm font-bold text-gray-900"><?= sanitize_string($serverSoftware) ?></p>
                                        </div>
                                    </div>
                                    <div class="flex items-center gap-3 p-4 bg-gray-50 rounded-xl">
                                        <div class="w-10 h-10 rounded-lg bg-amber-50 flex items-center justify-center flex-shrink-0">
                                            <i class="fas fa-hdd text-amber-500"></i>
                                        </div>
                                        <div>
                                            <p class="text-xs text-gray-400">Disk Usage</p>
                                            <p class="text-sm font-bold text-gray-900"><?= $diskUsed ? format_bytes($diskUsed) . ' / ' . format_bytes($diskTotal) . ' (' . $diskPct . '%)' : 'N/A' ?></p>
                                        </div>
                                    </div>
                                </div>

                                <?php if ($diskTotal): ?>
                                <div class="mt-4">
                                    <div class="w-full bg-gray-100 rounded-full h-2.5">
                                        <div class="h-2.5 rounded-full transition-all duration-500 <?= $diskPct > 90 ? 'bg-red-500' : ($diskPct > 70 ? 'bg-amber-500' : 'bg-blue-500') ?>" style="width:<?= $diskPct ?>%"></div>
                                    </div>
                                </div>
                                <?php endif; ?>

                                <div class="mt-6 p-4 bg-gray-50 rounded-xl">
                                    <h3 class="text-sm font-bold text-gray-900 mb-3">Settings File</h3>
                                    <div class="grid grid-cols-1 md:grid-cols-2 gap-3 text-xs">
                                        <div class="flex justify-between p-2 bg-white rounded-lg">
                                            <span class="text-gray-500">File Path</span>
                                            <span class="font-mono text-gray-700">config/platform_settings.json</span>
                                        </div>
                                        <div class="flex justify-between p-2 bg-white rounded-lg">
                                            <span class="text-gray-500">File Size</span>
                                            <span class="font-mono text-gray-700"><?= $settingsFile && file_exists($settingsFile) ? format_bytes(filesize($settingsFile)) : 'N/A' ?></span>
                                        </div>
                                        <div class="flex justify-between p-2 bg-white rounded-lg">
                                            <span class="text-gray-500">Last Modified</span>
                                            <span class="font-mono text-gray-700"><?= $settingsFile && file_exists($settingsFile) ? date('M j, Y H:i', filemtime($settingsFile)) : 'N/A' ?></span>
                                        </div>
                                        <div class="flex justify-between p-2 bg-white rounded-lg">
                                            <span class="text-gray-500">Upload Directory</span>
                                            <span class="font-mono text-gray-700">assets/upload/settings/</span>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- ═══ SAVE BUTTON ═══════════════════════════════════════ -->
                    <div class="flex justify-end gap-3 fade-in" style="animation-delay:.5s">
                        <a href="dashboard.php" class="px-6 py-2.5 rounded-xl border border-gray-200 text-sm font-semibold text-gray-600 hover:bg-gray-50 transition-colors">
                            Cancel
                        </a>
                        <button type="submit" class="btn-grad px-8 py-2.5 rounded-xl text-white text-sm font-bold shadow-lg shadow-blue-500/25">
                            <i class="fas fa-save mr-2"></i>Save Settings
                        </button>
                    </div>

                </form>


    <script>
        function showTab(tabName) {
            document.querySelectorAll('.tab-content').forEach(el => el.classList.add('hidden'));
            document.querySelectorAll('.tab-btn').forEach(el => {
                el.classList.remove('active', 'bg-blue-600', 'text-white');
                el.classList.add('text-gray-500');
            });

            const tabEl = document.getElementById('tab-' + tabName);
            if (tabEl) {
                tabEl.classList.remove('hidden');
            }

            const btn = document.querySelector(`[data-tab="${tabName}"]`);
            if (btn) {
                btn.classList.add('active', 'bg-blue-600', 'text-white');
                btn.classList.remove('text-gray-500');
            }
        }

        function toggleSwitch(el) {
            const checkbox = el.querySelector('input[type=checkbox]');
            checkbox.checked = !checkbox.checked;
            el.classList.toggle('active');
        }

        showTab('general');
    </script>
<?php require_once __DIR__ . '/../components/layout_end.php'; ?>
