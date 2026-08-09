<?php
/**
 * Admin Notifications Management
 * Send broadcast/system/maintenance notifications, view history.
 */
require_once __DIR__ . '/../auth/auth.php';
require_role('admin');
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../shared/notifications.php';

$currentPage = 'notifications';

// ── Get counts ──────────────────────────────────────────────────────
$countStmt = $conn->prepare('SELECT COUNT(*) AS cnt FROM notifications WHERE is_read = 0');
$countStmt->execute();
$unreadCount = (int) $countStmt->get_result()->fetch_assoc()['cnt'];
$countStmt->close();

$totalStmt = $conn->prepare('SELECT COUNT(*) AS cnt FROM notifications');
$totalStmt->execute();
$totalNotifications = (int) $totalStmt->get_result()->fetch_assoc()['cnt'];
$totalStmt->close();

$unreadUsersStmt = $conn->prepare('SELECT COUNT(DISTINCT user_id) AS cnt FROM notifications WHERE is_read = 0');
$unreadUsersStmt->execute();
$unreadUsersCount = (int) $unreadUsersStmt->get_result()->fetch_assoc()['cnt'];
$unreadUsersStmt->close();

// ── Handle Send Action ──────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'send_notification') {
    if (!verify_csrf_token()) {
        set_flash('error', 'Invalid security token.');
        redirect('notifications.php');
    }

    $notifType = sanitize_string($_POST['notif_type'] ?? '');
    $title     = trim($_POST['title'] ?? '');
    $message   = trim($_POST['message'] ?? '');
    $target    = sanitize_string($_POST['target'] ?? 'all');

    if (empty($title) || empty($message) || empty($notifType)) {
        set_flash('error', 'All fields are required.');
        redirect('notifications.php');
    }

    $notifier = new PlatformNotificationService($conn);

    // Get target user IDs
    $whereClause = '';
    if ($target === 'clients') {
        $whereClause = " WHERE u.role = 'client'";
    } elseif ($target === 'freelancers') {
        $whereClause = " WHERE u.role = 'freelancer'";
    } elseif ($target === 'admins') {
        $whereClause = " WHERE u.role = 'admin'";
    } else {
        $whereClause = '';
    }

    $userQuery = "SELECT u.id FROM users u" . $whereClause;
    $userResult = $conn->query($userQuery);

    $sentCount = 0;
    if ($userResult) {
        while ($userRow = $userResult->fetch_assoc()) {
            $notifier->create((int) $userRow['id'], $notifType, $title, $message, null);
            $sentCount++;
        }
    }

    set_flash('success', "Notification sent to {$sentCount} user" . ($sentCount !== 1 ? 's' : '') . ".");
    redirect('notifications.php');
}

// ── Handle Mark Read / Delete Actions ───────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && in_array($_POST['action'] ?? '', ['mark_read', 'mark_all_read', 'delete_notification'])) {
    if (!verify_csrf_token()) {
        set_flash('error', 'Invalid security token.');
        redirect('notifications.php');
    }

    $act = $_POST['action'];
    $notifier = new PlatformNotificationService($conn);

    if ($act === 'mark_all_read') {
        $conn->query('UPDATE notifications SET is_read = 1 WHERE is_read = 0');
        set_flash('success', 'All notifications marked as read.');
    } elseif ($act === 'mark_read') {
        $nid = sanitize_int($_POST['notification_id'] ?? 0);
        if ($nid > 0) {
            $stmt = $conn->prepare('UPDATE notifications SET is_read = 1 WHERE id = ?');
            $stmt->bind_param('i', $nid);
            $stmt->execute();
            $stmt->close();
        }
        set_flash('success', 'Notification marked as read.');
    } elseif ($act === 'delete_notification') {
        $nid = sanitize_int($_POST['notification_id'] ?? 0);
        if ($nid > 0) {
            $stmt = $conn->prepare('DELETE FROM notifications WHERE id = ?');
            $stmt->bind_param('i', $nid);
            $stmt->execute();
            $stmt->close();
        }
        set_flash('success', 'Notification deleted.');
    }
    redirect('notifications.php');
}

// ── Pagination & History ────────────────────────────────────────────
$page    = max(1, sanitize_int($_GET['page'] ?? 1));
$perPage = 20;
$totalItems = $totalNotifications;
$pagination = paginate($totalItems, $perPage, $page);
$offset = $pagination['offset'];

$histStmt = $conn->prepare('SELECT n.*, u.name AS user_name, u.role AS user_role
                            FROM notifications n
                            JOIN users u ON n.user_id = u.id
                            ORDER BY n.created_at DESC
                            LIMIT ? OFFSET ?');
$histStmt->bind_param('ii', $perPage, $offset);
$histStmt->execute();
$history = $histStmt->get_result();
$histStmt->close();

$baseUrl = 'notifications.php';

// ── Computed Metrics ────────────────────────────────────────────────
$readRate = $totalNotifications > 0 ? round((($totalNotifications - $unreadCount) / $totalNotifications) * 100) : 0;

// ── Layout Setup ────────────────────────────────────────────────────
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
    ['key' => 'ai_monitor', 'label' => 'AI Monitor', 'url' => 'ai_monitor.php', 'icon' => 'brain'],
    ['key' => 'analytics', 'label' => 'Analytics', 'url' => 'analytics.php', 'icon' => 'pie-chart'],
    ['key' => 'settings', 'label' => 'Settings', 'url' => 'settings.php', 'icon' => 'settings'],
];
$pageTitle = 'Notifications Center';
$pageSubtitle = 'Send system notifications and view history';
$activePage = 'notifications';
$user = ['name' => $adminName, 'profile_image' => $_navUserRow['profile_image'] ?? null];
$profileLink = 'profile.php';
require_once __DIR__ . '/../components/layout_start.php';
?>
    <style>
        .drawer-overlay { opacity: 0; pointer-events: none; transition: opacity .3s ease; }
        .drawer-overlay.open { opacity: 1; pointer-events: auto; }
        .drawer-panel { transform: translateX(100%); transition: transform .3s cubic-bezier(.4,0,.2,1); }
        .drawer-panel.open { transform: translateX(0); }
        .type-seg input:checked + label { background: #111827; color: #fff; }
        .dark .type-seg input:checked + label { background: #3B82F6; color: #fff; }
        .audience-pill input:checked + label { background: #111827; color: #fff; }
        .dark .audience-pill input:checked + label { background: #3B82F6; color: #fff; }
        .action-menu { display: none; }
        .action-menu.open { display: block; }
        .notif-row:hover .action-trigger { opacity: 1; }
        .action-trigger { opacity: 0; transition: opacity .15s; }
    </style>

    <?php display_flash('success'); ?>
    <?php display_flash('error'); ?>

    <!-- ═══════════════════════════════════════════════════════════════════════
         SECTION 1 — HEADER
         ═══════════════════════════════════════════════════════════════════════ -->
    <div class="flex items-center justify-between mb-5 flex-wrap gap-3">
        <div>
            <div class="flex items-center gap-2 text-[10px] text-gray-400 dark:text-slate-500 mb-1">
                <span>System</span>
                <i data-lucide="chevron-right" class="text-[7px]"></i>
                <span class="text-gray-700 dark:text-slate-300 font-semibold">Notifications</span>
            </div>
            <div class="flex items-center gap-3">
                <h1 class="text-xl font-bold text-gray-900 dark:text-white tracking-tight">Notifications Center</h1>
                <?php if ($unreadCount > 0): ?>
                <span class="inline-flex items-center justify-center min-w-[20px] h-5 px-1.5 rounded-full bg-red-500 text-white text-[10px] font-bold"><?= $unreadCount ?></span>
                <?php endif; ?>
            </div>
        </div>
        <div class="flex items-center gap-2">
            <form method="POST" action="notifications.php" class="inline" onsubmit="return confirm('Mark all notifications as read?')">
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="mark_all_read">
                <button type="submit" class="inline-flex items-center gap-1.5 px-3.5 py-2 bg-white dark:bg-slate-700 border border-gray-200 dark:border-slate-600 text-gray-700 dark:text-slate-300 text-[11px] font-semibold rounded-lg hover:bg-gray-50 dark:hover:bg-slate-600 transition shadow-sm">
                    <i data-lucide="check-check" class="text-[10px]"></i> Mark All Read
                </button>
            </form>
            <button onclick="openSendDrawer()" class="inline-flex items-center gap-1.5 px-3.5 py-2 bg-gray-900 dark:bg-slate-700 hover:bg-gray-800 dark:hover:bg-slate-600 text-white text-[11px] font-semibold rounded-lg shadow-sm transition">
                <i data-lucide="plus" class="text-[10px]"></i> Send Notification
            </button>
        </div>
    </div>

    <!-- ═══════════════════════════════════════════════════════════════════════
         SECTION 2 — KPI METRICS BAR
         ═══════════════════════════════════════════════════════════════════════ -->
    <?php
    $kpiCards = [
        [
            'label' => 'Total Sent',
            'value' => number_format($totalNotifications),
            'icon' => 'send',
            'color' => '#3B82F6',
            'bg' => '#EFF6FF',
        ],
        [
            'label' => 'Unread',
            'value' => number_format($unreadCount),
            'icon' => 'mail',
            'color' => '#EF4444',
            'bg' => '#FEF2F2',
            'highlight' => true,
        ],
        [
            'label' => 'Unread Users',
            'value' => number_format($unreadUsersCount),
            'icon' => 'users',
            'color' => '#F59E0B',
            'bg' => '#FFFBEB',
        ],
        [
            'label' => 'Read Rate',
            'value' => $readRate . '%',
            'icon' => 'bar-chart',
            'color' => '#10B981',
            'bg' => '#ECFDF5',
            'progress' => $readRate,
        ],
    ];
    ?>
    <div class="grid grid-cols-2 sm:grid-cols-4 gap-3 mb-5">
        <?php foreach ($kpiCards as $i => $k): ?>
        <div class="bg-white dark:bg-slate-800 rounded-xl border border-gray-200 dark:border-slate-700 p-3.5 flex flex-col justify-between" style="animation-delay:<?= $i * 0.03 ?>s">
            <div class="flex items-center justify-between mb-2">
                <span class="text-[10px] font-semibold uppercase tracking-wider text-gray-500 dark:text-slate-400"><?= $k['label'] ?></span>
                <span class="w-7 h-7 rounded-lg flex items-center justify-center flex-shrink-0" style="background:<?= $k['bg'] ?>">
                    <i data-lucide="<?= $k['icon'] ?>" class="text-[10px]" style="color:<?= $k['color'] ?>"></i>
                </span>
            </div>
            <p class="text-xl font-extrabold leading-tight <?= !empty($k['highlight']) ? 'text-red-600 dark:text-red-400' : 'text-gray-900 dark:text-white' ?>"><?= $k['value'] ?></p>
            <?php if (!empty($k['progress'])): ?>
            <div class="w-full h-1.5 rounded-full bg-gray-100 dark:bg-slate-700 mt-2 overflow-hidden">
                <div class="h-full rounded-full bg-emerald-500" style="width: <?= $k['progress'] ?>%"></div>
            </div>
            <?php endif; ?>
        </div>
        <?php endforeach; ?>
    </div>

    <!-- ═══════════════════════════════════════════════════════════════════════
         SECTION 3 — FILTER & SEARCH BAR
         ═══════════════════════════════════════════════════════════════════════ -->
    <div class="bg-white dark:bg-slate-800 rounded-xl border border-gray-200 dark:border-slate-700 p-4 mb-5">
        <div class="flex flex-col sm:flex-row items-stretch sm:items-center gap-3">
            <!-- Search -->
            <div class="relative flex-1">
                <i data-lucide="search" class="absolute left-3 top-1/2 -translate-y-1/2 text-gray-400 dark:text-slate-500 text-[11px]"></i>
                <input type="text" id="notifSearch" placeholder="Search notification content or user..." onkeyup="filterNotifications()"
                    class="w-full pl-9 pr-4 py-2 rounded-lg border border-gray-200 dark:border-slate-600 text-xs bg-gray-50 dark:bg-slate-700 text-gray-900 dark:text-white placeholder-gray-400 dark:placeholder-slate-500 focus:outline-none focus:ring-2 focus:ring-blue-500/30 focus:border-blue-400">
            </div>
            <!-- Filter: Type -->
            <select id="filterType" onchange="filterNotifications()" class="px-3 py-2 rounded-lg border border-gray-200 dark:border-slate-600 text-xs bg-gray-50 dark:bg-slate-700 text-gray-700 dark:text-slate-300 focus:outline-none focus:ring-2 focus:ring-blue-500/30">
                <option value="">All Types</option>
                <option value="broadcast">Broadcast</option>
                <option value="system_announcement">Announce</option>
                <option value="maintenance">Maintenance</option>
                <option value="system_alert">System Alert</option>
                <option value="dispute">Dispute</option>
            </select>
            <!-- Filter: Audience -->
            <select id="filterAudience" onchange="filterNotifications()" class="px-3 py-2 rounded-lg border border-gray-200 dark:border-slate-600 text-xs bg-gray-50 dark:bg-slate-700 text-gray-700 dark:text-slate-300 focus:outline-none focus:ring-2 focus:ring-blue-500/30">
                <option value="">All Users</option>
                <option value="client">Clients</option>
                <option value="freelancer">Freelancers</option>
                <option value="admin">Admins</option>
            </select>
            <!-- Filter: Status -->
            <select id="filterStatus" onchange="filterNotifications()" class="px-3 py-2 rounded-lg border border-gray-200 dark:border-slate-600 text-xs bg-gray-50 dark:bg-slate-700 text-gray-700 dark:text-slate-300 focus:outline-none focus:ring-2 focus:ring-blue-500/30">
                <option value="">All Status</option>
                <option value="unread">Unread</option>
                <option value="read">Read</option>
            </select>
        </div>
    </div>

    <!-- ═══════════════════════════════════════════════════════════════════════
         SECTION 4 — NOTIFICATION HISTORY TABLE
         ═══════════════════════════════════════════════════════════════════════ -->
    <div class="bg-white dark:bg-slate-800 rounded-xl border border-gray-200 dark:border-slate-700 shadow-xs overflow-hidden">
        <?php if ($history->num_rows > 0): ?>
        <!-- Table Header -->
        <div class="hidden sm:grid grid-cols-12 gap-4 px-5 py-3 border-b border-gray-100 dark:border-slate-700 bg-gray-50/50 dark:bg-slate-700/20">
            <div class="col-span-5 text-[10px] font-semibold text-gray-400 dark:text-slate-500 uppercase tracking-wider">Notification</div>
            <div class="col-span-2 text-[10px] font-semibold text-gray-400 dark:text-slate-500 uppercase tracking-wider">Recipient</div>
            <div class="col-span-2 text-[10px] font-semibold text-gray-400 dark:text-slate-500 uppercase tracking-wider">Type</div>
            <div class="col-span-2 text-[10px] font-semibold text-gray-400 dark:text-slate-500 uppercase tracking-wider">Time</div>
            <div class="col-span-1 text-[10px] font-semibold text-gray-400 dark:text-slate-500 uppercase tracking-wider text-right">Status</div>
        </div>

        <!-- Table Rows -->
        <div id="notifTableBody" class="divide-y divide-gray-50 dark:divide-slate-700/50">
            <?php while ($n = $history->fetch_assoc()):
                $typeIcon = match($n['type']) {
                    'broadcast' => 'megaphone',
                    'system_announcement' => 'megaphone',
                    'maintenance' => 'wrench',
                    'payment' => 'credit-card',
                    'contract' => 'file-text',
                    'dispute' => 'hammer',
                    'review' => 'star',
                    'milestone' => 'list-checks',
                    'system_alert' => 'triangle-alert',
                    default => 'bell',
                };
                $typeColor = match($n['type']) {
                    'broadcast' => 'blue',
                    'system_announcement' => 'purple',
                    'maintenance' => 'amber',
                    'payment' => 'emerald',
                    'contract' => 'indigo',
                    'dispute' => 'red',
                    'review' => 'yellow',
                    'milestone' => 'cyan',
                    'system_alert' => 'orange',
                    default => 'gray',
                };
                $typeLabel = match($n['type']) {
                    'broadcast' => 'Broadcast',
                    'system_announcement' => 'Announce',
                    'maintenance' => 'Maintenance',
                    'system_alert' => 'System Alert',
                    'dispute' => 'Dispute',
                    default => ucfirst(str_replace('_', ' ', $n['type'])),
                };
            ?>
            <div class="notif-row grid grid-cols-1 sm:grid-cols-12 gap-2 sm:gap-4 px-5 py-3.5 hover:bg-gray-50/50 dark:hover:bg-slate-700/30 transition-colors items-center <?= $n['is_read'] ? '' : 'bg-blue-50/30 dark:bg-blue-900/5' ?>"
                 data-type="<?= htmlspecialchars($n['type']) ?>"
                 data-audience="<?= htmlspecialchars($n['user_role']) ?>"
                 data-status="<?= $n['is_read'] ? 'read' : 'unread' ?>"
                 data-search="<?= strtolower(sanitize_string($n['title'] . ' ' . $n['message'] . ' ' . $n['user_name'])) ?>">

                <!-- Col 1: Notification Info -->
                <div class="col-span-5 flex items-start gap-3 min-w-0">
                    <span class="w-8 h-8 rounded-lg flex items-center justify-center flex-shrink-0 mt-0.5 bg-<?= $typeColor ?>-50 dark:bg-<?= $typeColor ?>-900/20">
                        <i data-lucide="<?= $typeIcon ?>" class="text-<?= $typeColor ?>-500 text-[11px]"></i>
                    </span>
                    <div class="min-w-0 flex-1">
                        <div class="flex items-center gap-2">
                            <p class="text-xs font-bold text-gray-900 dark:text-white truncate"><?= sanitize_string($n['title']) ?></p>
                            <?php if (!$n['is_read']): ?>
                                <span class="w-1.5 h-1.5 rounded-full bg-blue-500 flex-shrink-0"></span>
                            <?php endif; ?>
                        </div>
                        <p class="text-[11px] text-gray-500 dark:text-slate-400 truncate mt-0.5"><?= sanitize_string($n['message']) ?></p>
                    </div>
                </div>

                <!-- Col 2: Recipient -->
                <div class="col-span-2">
                    <div class="flex items-center gap-1.5">
                        <div class="w-5 h-5 rounded-full bg-gray-100 dark:bg-slate-700 flex items-center justify-center flex-shrink-0">
                            <i data-lucide="user" class="text-[8px] text-gray-400 dark:text-slate-500"></i>
                        </div>
                        <div class="min-w-0">
                            <p class="text-[11px] font-semibold text-gray-900 dark:text-white truncate"><?= sanitize_string($n['user_name']) ?></p>
                            <p class="text-[9px] text-gray-400 dark:text-slate-500 capitalize"><?= $n['user_role'] ?></p>
                        </div>
                    </div>
                </div>

                <!-- Col 3: Type Badge -->
                <div class="col-span-2">
                    <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded text-[9px] font-bold bg-<?= $typeColor ?>-50 dark:bg-<?= $typeColor ?>-900/20 text-<?= $typeColor ?>-600 dark:text-<?= $typeColor ?>-400">
                        <?= $typeLabel ?>
                    </span>
                </div>

                <!-- Col 4: Timestamp -->
                <div class="col-span-2">
                    <p class="text-[11px] text-gray-500 dark:text-slate-400"><?= date('M d, g:ia', strtotime($n['created_at'])) ?></p>
                </div>

                <!-- Col 5: Status + Actions -->
                <div class="col-span-1 flex items-center justify-end gap-1.5">
                    <span class="inline-flex items-center px-2 py-0.5 rounded text-[9px] font-bold <?= $n['is_read'] ? 'bg-gray-100 dark:bg-slate-700 text-gray-500 dark:text-slate-400' : 'bg-blue-50 dark:bg-blue-900/20 text-blue-600 dark:text-blue-400' ?>">
                        <?= $n['is_read'] ? 'Read' : 'Unread' ?>
                    </span>
                    <!-- Action Menu Trigger -->
                    <div class="relative">
                        <button onclick="toggleActionMenu(event, this)" class="action-trigger w-6 h-6 rounded-md flex items-center justify-center text-gray-400 hover:text-gray-600 dark:hover:text-slate-300 hover:bg-gray-100 dark:hover:bg-slate-700 transition">
                            <i data-lucide="ellipsis-vertical" class="text-[10px]"></i>
                        </button>
                        <div class="action-menu absolute right-0 top-full mt-1 w-36 bg-white dark:bg-slate-800 rounded-lg border border-gray-200 dark:border-slate-600 shadow-lg z-20 py-1">
                            <?php if (!$n['is_read']): ?>
                            <form method="POST" action="notifications.php">
                                <?= csrf_field() ?>
                                <input type="hidden" name="action" value="mark_read">
                                <input type="hidden" name="notification_id" value="<?= $n['id'] ?>">
                                <button type="submit" class="w-full text-left px-3 py-2 text-[11px] text-gray-700 dark:text-slate-300 hover:bg-gray-50 dark:hover:bg-slate-700 flex items-center gap-2">
                                    <i data-lucide="check" class="text-[9px] text-emerald-500"></i> Mark as Read
                                </button>
                            </form>
                            <?php endif; ?>
                            <form method="POST" action="notifications.php" onsubmit="return confirm('Delete this notification?')">
                                <?= csrf_field() ?>
                                <input type="hidden" name="action" value="delete_notification">
                                <input type="hidden" name="notification_id" value="<?= $n['id'] ?>">
                                <button type="submit" class="w-full text-left px-3 py-2 text-[11px] text-red-600 dark:text-red-400 hover:bg-red-50 dark:hover:bg-red-900/10 flex items-center gap-2">
                                    <i data-lucide="trash-2" class="text-[9px]"></i> Delete
                                </button>
                            </form>
                        </div>
                    </div>
                </div>
            </div>
            <?php endwhile; ?>
        </div>

        <!-- Pagination -->
        <div class="px-5 py-3 border-t border-gray-100 dark:border-slate-700 flex items-center justify-between bg-gray-50/30 dark:bg-slate-700/10">
            <p class="text-[10px] text-gray-400 dark:text-slate-500">
                Showing <?= $pagination['offset'] + 1 ?>–<?= min($pagination['offset'] + $perPage, $totalItems) ?> of <?= number_format($totalItems) ?>
            </p>
            <div class="flex items-center gap-1">
                <?php if ($pagination['has_prev']): ?>
                <a href="?page=<?= $pagination['current_page'] - 1 ?>" class="w-7 h-7 rounded-md flex items-center justify-center text-[11px] font-semibold border border-gray-200 dark:border-slate-600 text-gray-600 dark:text-slate-400 hover:bg-gray-100 dark:hover:bg-slate-700 transition">
                    <i data-lucide="chevron-left" class="text-[8px]"></i>
                </a>
                <?php endif; ?>
                <?php
                $startPage = max(1, $page - 2);
                $endPage = min($pagination['total_pages'], $page + 2);
                for ($p = $startPage; $p <= $endPage; $p++):
                ?>
                <a href="?page=<?= $p ?>" class="w-7 h-7 rounded-md flex items-center justify-center text-[11px] font-semibold transition <?= $p === $page ? 'bg-gray-900 dark:bg-blue-600 text-white' : 'border border-gray-200 dark:border-slate-600 text-gray-600 dark:text-slate-400 hover:bg-gray-100 dark:hover:bg-slate-700' ?>">
                    <?= $p ?>
                </a>
                <?php endfor; ?>
                <?php if ($pagination['has_next']): ?>
                <a href="?page=<?= $pagination['current_page'] + 1 ?>" class="w-7 h-7 rounded-md flex items-center justify-center text-[11px] font-semibold border border-gray-200 dark:border-slate-600 text-gray-600 dark:text-slate-400 hover:bg-gray-100 dark:hover:bg-slate-700 transition">
                    <i data-lucide="chevron-right" class="text-[8px]"></i>
                </a>
                <?php endif; ?>
            </div>
        </div>

        <?php else: ?>
        <!-- Empty State -->
        <div class="text-center py-16">
            <div class="w-14 h-14 rounded-2xl bg-gray-100 dark:bg-slate-700 flex items-center justify-center mx-auto mb-3">
                <i data-lucide="bell-off" class="text-xl text-gray-300 dark:text-slate-500"></i>
            </div>
            <p class="text-sm font-semibold text-gray-700 dark:text-slate-300 mb-1">No notifications yet</p>
            <p class="text-[11px] text-gray-400 dark:text-slate-500">Send your first notification to get started</p>
        </div>
        <?php endif; ?>
    </div>

    <!-- ═══════════════════════════════════════════════════════════════════════
         SLIDE-OVER DRAWER — Send Notification
         ═══════════════════════════════════════════════════════════════════════ -->
    <div id="sendDrawer" class="fixed inset-0 z-50" style="display:none">
        <!-- Backdrop -->
        <div id="drawerBackdrop" class="drawer-overlay absolute inset-0 bg-black/40 backdrop-blur-sm" onclick="closeSendDrawer()"></div>
        <!-- Panel -->
        <div id="drawerPanel" class="drawer-panel absolute right-0 top-0 h-full w-full max-w-lg bg-white dark:bg-slate-800 shadow-2xl flex flex-col">
            <!-- Drawer Header -->
            <div class="px-6 py-4 border-b border-gray-100 dark:border-slate-700 flex items-center justify-between flex-shrink-0">
                <h3 class="text-sm font-bold text-gray-900 dark:text-white flex items-center gap-2">
                    <span class="w-7 h-7 rounded-lg bg-blue-50 dark:bg-blue-900/20 flex items-center justify-center"><i data-lucide="send" class="text-blue-500 text-[11px]"></i></span>
                    Send Notification
                </h3>
                <button onclick="closeSendDrawer()" class="w-7 h-7 rounded-lg flex items-center justify-center text-gray-400 hover:text-gray-600 dark:hover:text-slate-300 hover:bg-gray-100 dark:hover:bg-slate-700 transition">
                    <i data-lucide="x" class="text-sm"></i>
                </button>
            </div>

            <!-- Drawer Body -->
            <div class="flex-1 overflow-y-auto p-6">
                <form method="POST" action="notifications.php" id="drawerNotifForm">
                    <?= csrf_field() ?>
                    <input type="hidden" name="action" value="send_notification">

                    <!-- Type Selector -->
                    <div class="mb-5">
                        <label class="block text-[10px] font-semibold text-gray-400 dark:text-slate-500 uppercase tracking-wider mb-2.5">Notification Type</label>
                        <div class="flex rounded-lg border border-gray-200 dark:border-slate-600 overflow-hidden">
                            <?php
                            $drawerTypes = [
                                ['val' => 'broadcast', 'icon' => 'megaphone', 'label' => 'Broadcast'],
                                ['val' => 'system_announcement', 'icon' => 'megaphone', 'label' => 'Announce'],
                                ['val' => 'maintenance', 'icon' => 'wrench', 'label' => 'Maintenance'],
                            ];
                            foreach ($drawerTypes as $dt):
                            ?>
                            <div class="type-seg flex-1">
                                <input type="radio" name="notif_type" value="<?= $dt['val'] ?>" id="drawer_type_<?= $dt['val'] ?>" class="hidden" <?= $dt['val'] === 'broadcast' ? 'checked' : '' ?>>
                                <label for="drawer_type_<?= $dt['val'] ?>" class="flex items-center justify-center gap-1.5 py-2.5 cursor-pointer text-[11px] font-semibold text-gray-600 dark:text-slate-400 hover:bg-gray-50 dark:hover:bg-slate-700 transition-all border-r border-gray-200 dark:border-slate-600 last:border-r-0">
                                    <i data-lucide="<?= $dt['icon'] ?>" class="text-[10px]"></i> <?= $dt['label'] ?>
                                </label>
                            </div>
                            <?php endforeach; ?>
                        </div>
                    </div>

                    <!-- Target Audience -->
                    <div class="mb-5">
                        <label class="block text-[10px] font-semibold text-gray-400 dark:text-slate-500 uppercase tracking-wider mb-2.5">Target Audience</label>
                        <div class="flex flex-wrap gap-2">
                            <?php
                            $drawerTargets = [
                                ['val' => 'all', 'label' => 'All Users'],
                                ['val' => 'clients', 'label' => 'Clients Only'],
                                ['val' => 'freelancers', 'label' => 'Freelancers Only'],
                                ['val' => 'admins', 'label' => 'Admins Only'],
                            ];
                            foreach ($drawerTargets as $drt):
                            ?>
                            <div class="audience-pill">
                                <input type="radio" name="target" value="<?= $drt['val'] ?>" id="drawer_target_<?= $drt['val'] ?>" class="hidden" <?= $drt['val'] === 'all' ? 'checked' : '' ?>>
                                <label for="drawer_target_<?= $drt['val'] ?>" class="inline-flex items-center px-3 py-1.5 rounded-lg border border-gray-200 dark:border-slate-600 cursor-pointer text-[11px] font-semibold text-gray-600 dark:text-slate-400 hover:bg-gray-50 dark:hover:bg-slate-700 transition-all">
                                    <?= $drt['label'] ?>
                                </label>
                            </div>
                            <?php endforeach; ?>
                        </div>
                    </div>

                    <!-- Title -->
                    <div class="mb-4">
                        <label class="block text-[10px] font-semibold text-gray-400 dark:text-slate-500 uppercase tracking-wider mb-1.5">Title</label>
                        <input type="text" name="title" required placeholder="e.g. Platform Update v2.0"
                            class="w-full px-4 py-2.5 rounded-xl border border-gray-200 dark:border-slate-600 text-xs bg-gray-50 dark:bg-slate-700 text-gray-900 dark:text-white placeholder-gray-400 dark:placeholder-slate-500 focus:outline-none focus:ring-2 focus:ring-blue-500/30 focus:border-blue-400">
                    </div>

                    <!-- Message -->
                    <div class="mb-2">
                        <label class="block text-[10px] font-semibold text-gray-400 dark:text-slate-500 uppercase tracking-wider mb-1.5">Message</label>
                        <textarea name="message" required rows="5" placeholder="Write your notification message here..."
                            class="w-full px-4 py-2.5 rounded-xl border border-gray-200 dark:border-slate-600 text-xs bg-gray-50 dark:bg-slate-700 text-gray-900 dark:text-white placeholder-gray-400 dark:placeholder-slate-500 focus:outline-none focus:ring-2 focus:ring-blue-500/30 focus:border-blue-400 resize-none"></textarea>
                    </div>
                </form>
            </div>

            <!-- Drawer Footer -->
            <div class="px-6 py-4 border-t border-gray-100 dark:border-slate-700 flex items-center justify-end gap-2.5 flex-shrink-0 bg-gray-50/50 dark:bg-slate-700/20">
                <button onclick="closeSendDrawer()" class="px-4 py-2 rounded-xl text-xs font-semibold text-gray-600 dark:text-slate-400 hover:bg-gray-100 dark:hover:bg-slate-700 transition">
                    Cancel
                </button>
                <button onclick="submitDrawerForm()" class="px-5 py-2 rounded-xl bg-gray-900 dark:bg-blue-600 hover:bg-gray-800 dark:hover:bg-blue-700 text-white text-xs font-semibold transition shadow-sm inline-flex items-center gap-1.5">
                    <i data-lucide="send" class="text-[10px]"></i> Send Notification
                </button>
            </div>
        </div>
    </div>

    <!-- ═══ JAVASCRIPT ═══════════════════════════════════════════════════════ -->
    <script>
    // ── Drawer Controls ───────────────────────────────────────────────
    function openSendDrawer() {
        document.getElementById('sendDrawer').style.display = 'block';
        requestAnimationFrame(function() {
            document.getElementById('drawerBackdrop').classList.add('open');
            document.getElementById('drawerPanel').classList.add('open');
        });
        document.body.style.overflow = 'hidden';
    }

    function closeSendDrawer() {
        document.getElementById('drawerBackdrop').classList.remove('open');
        document.getElementById('drawerPanel').classList.remove('open');
        setTimeout(function() {
            document.getElementById('sendDrawer').style.display = 'none';
            document.body.style.overflow = '';
        }, 300);
    }

    function submitDrawerForm() {
        var form = document.getElementById('drawerNotifForm');
        if (form.checkValidity()) {
            if (confirm('Send this notification to selected audience?')) {
                form.submit();
            }
        } else {
            form.reportValidity();
        }
    }

    // ── Action Menu ───────────────────────────────────────────────────
    function toggleActionMenu(e, btn) {
        e.stopPropagation();
        // Close all other menus
        document.querySelectorAll('.action-menu.open').forEach(function(m) { m.classList.remove('open'); });
        var menu = btn.parentElement.querySelector('.action-menu');
        menu.classList.toggle('open');
    }

    // Close menus on outside click
    document.addEventListener('click', function() {
        document.querySelectorAll('.action-menu.open').forEach(function(m) { m.classList.remove('open'); });
    });

    // ── Client-Side Filtering ─────────────────────────────────────────
    function filterNotifications() {
        var search = document.getElementById('notifSearch').value.toLowerCase();
        var type = document.getElementById('filterType').value;
        var audience = document.getElementById('filterAudience').value;
        var status = document.getElementById('filterStatus').value;
        var rows = document.querySelectorAll('.notif-row');
        rows.forEach(function(row) {
            var matchSearch = !search || (row.getAttribute('data-search') || '').indexOf(search) !== -1;
            var matchType = !type || row.getAttribute('data-type') === type;
            var matchAudience = !audience || row.getAttribute('data-audience') === audience;
            var matchStatus = !status || row.getAttribute('data-status') === status;
            row.style.display = (matchSearch && matchType && matchAudience && matchStatus) ? '' : 'none';
        });
    }

    // ── Escape key closes drawer ──────────────────────────────────────
    document.addEventListener('keydown', function(e) {
        if (e.key === 'Escape') closeSendDrawer();
    });
    </script>

<?php require_once __DIR__ . '/../components/layout_end.php'; ?>
