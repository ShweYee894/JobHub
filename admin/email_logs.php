<?php
/**
 * Admin Email Logs
 * View email sending history from user_behavior_logs.
 */
require_once __DIR__ . '/../auth/auth.php';
require_role('admin');
require_once __DIR__ . '/../config/db.php';

$currentPage = 'email_logs';

$countStmt = $conn->prepare('SELECT COUNT(*) AS cnt FROM notifications WHERE is_read = 0');
$countStmt->execute();
$unreadCount = (int) $countStmt->get_result()->fetch_assoc()['cnt'];
$countStmt->close();

$filterType = $_GET['type'] ?? '';
$filterStatus = $_GET['status'] ?? '';
$search = trim($_GET['q'] ?? '');
$page = max(1, intval($_GET['page'] ?? 1));
$perPage = 20;

$where = "action_type IN ('email_sent', 'email_failed')";
$params = [];
$types = '';

if (!empty($filterType)) {
    $where .= " AND JSON_EXTRACT(payload, '$.notification_type') = ?";
    $params[] = $filterType;
    $types .= 's';
}

if ($filterStatus === 'sent') {
    $where .= " AND JSON_EXTRACT(payload, '$.success') = 1";
} elseif ($filterStatus === 'failed') {
    $where .= " AND JSON_EXTRACT(payload, '$.success') = 0";
}

if (!empty($search)) {
    $where .= " AND (JSON_UNQUOTE(JSON_EXTRACT(payload, '$.to')) LIKE ? OR JSON_UNQUOTE(JSON_EXTRACT(payload, '$.subject')) LIKE ?)";
    $searchParam = "%{$search}%";
    $params[] = $searchParam;
    $params[] = $searchParam;
    $types .= 'ss';
}

$countSql = "SELECT COUNT(*) AS total FROM user_behavior_logs WHERE {$where}";
$countStmt = $conn->prepare($countSql);
if (!empty($types)) {
    $countStmt->bind_param($types, ...$params);
}
$countStmt->execute();
$totalItems = (int) $countStmt->get_result()->fetch_assoc()['total'];
$countStmt->close();

$totalPages = max(1, ceil($totalItems / $perPage));
if ($page > $totalPages) $page = $totalPages;
$offset = ($page - 1) * $perPage;

$sql = "SELECT * FROM user_behavior_logs WHERE {$where} ORDER BY created_at DESC LIMIT ? OFFSET ?";
$stmt = $conn->prepare($sql);
$params[] = $perPage;
$params[] = $offset;
$types .= 'ii';
$stmt->bind_param($types, ...$params);
$stmt->execute();
$logs = $stmt->get_result();
$stmt->close();

$statsStmt = $conn->prepare("SELECT action_type, COUNT(*) AS cnt FROM user_behavior_logs WHERE action_type IN ('email_sent', 'email_failed') GROUP BY action_type");
$statsStmt->execute();
$statsResult = $statsStmt->get_result();
$stats = ['email_sent' => 0, 'email_failed' => 0];
while ($row = $statsResult->fetch_assoc()) {
    $stats[$row['action_type']] = (int) $row['cnt'];
}
$statsStmt->close();
$totalEmails = $stats['email_sent'] + $stats['email_failed'];
$successRate = $totalEmails > 0 ? round(($stats['email_sent'] / $totalEmails) * 100, 1) : 0;

$pageTitle = 'Email Logs';
$activePage = 'email_logs';
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
$user = ['name' => $_SESSION['user_name'] ?? 'Admin', 'profile_image' => $_SESSION['profile_image'] ?? null];
$profileLink = 'profile.php';
require_once __DIR__ . '/../components/layout_start.php';
?>

<main class="p-6">
    <div class="flex items-center justify-between mb-6">
        <div>
            <h1 class="text-xl font-bold text-gray-900 dark:text-white">Email Logs</h1>
            <p class="text-sm text-gray-500 dark:text-slate-400 mt-1">Monitor email delivery status and troubleshoot failures.</p>
        </div>
        <a href="settings.php" class="inline-flex items-center gap-2 px-4 py-2 rounded-xl bg-indigo-600 hover:bg-indigo-700 text-white text-sm font-semibold transition-colors">
            <i data-lucide="settings" class="w-4 h-4"></i> Email Settings
        </a>
    </div>

    <div class="grid grid-cols-1 sm:grid-cols-4 gap-4 mb-6">
        <div class="bg-white dark:bg-slate-800 rounded-xl border border-gray-200 dark:border-slate-700 p-4">
            <p class="text-xs font-semibold text-gray-500 dark:text-slate-400 uppercase tracking-wide">Total Emails</p>
            <p class="text-2xl font-bold text-gray-900 dark:text-white mt-1"><?= number_format($totalEmails) ?></p>
        </div>
        <div class="bg-white dark:bg-slate-800 rounded-xl border border-gray-200 dark:border-slate-700 p-4">
            <p class="text-xs font-semibold text-gray-500 dark:text-slate-400 uppercase tracking-wide">Sent</p>
            <p class="text-2xl font-bold text-emerald-600 dark:text-emerald-400 mt-1"><?= number_format($stats['email_sent']) ?></p>
        </div>
        <div class="bg-white dark:bg-slate-800 rounded-xl border border-gray-200 dark:border-slate-700 p-4">
            <p class="text-xs font-semibold text-gray-500 dark:text-slate-400 uppercase tracking-wide">Failed</p>
            <p class="text-2xl font-bold text-red-600 dark:text-red-400 mt-1"><?= number_format($stats['email_failed']) ?></p>
        </div>
        <div class="bg-white dark:bg-slate-800 rounded-xl border border-gray-200 dark:border-slate-700 p-4">
            <p class="text-xs font-semibold text-gray-500 dark:text-slate-400 uppercase tracking-wide">Success Rate</p>
            <p class="text-2xl font-bold text-indigo-600 dark:text-indigo-400 mt-1"><?= $successRate ?>%</p>
        </div>
    </div>

    <div class="bg-white dark:bg-slate-800 rounded-xl border border-gray-200 dark:border-slate-700 p-4 mb-6">
        <form method="GET" class="flex flex-wrap items-center gap-3">
            <div class="flex-1 min-w-[200px]">
                <div class="relative">
                    <i data-lucide="search" class="absolute left-3 top-1/2 -translate-y-1/2 w-4 h-4 text-gray-400"></i>
                    <input type="text" name="q" value="<?= htmlspecialchars($search) ?>" placeholder="Search by email or subject..."
                           class="w-full pl-10 pr-4 py-2 rounded-lg border border-gray-200 dark:border-slate-600 bg-gray-50 dark:bg-slate-700 text-sm text-gray-900 dark:text-white focus:outline-none focus:ring-2 focus:ring-indigo-100 dark:focus:ring-indigo-800">
                </div>
            </div>
            <select name="type" class="px-3 py-2 rounded-lg border border-gray-200 dark:border-slate-600 bg-gray-50 dark:bg-slate-700 text-sm text-gray-700 dark:text-gray-300">
                <option value="">All Types</option>
                <option value="welcome" <?= $filterType === 'welcome' ? 'selected' : '' ?>>Welcome</option>
                <option value="password_reset" <?= $filterType === 'password_reset' ? 'selected' : '' ?>>Password Reset</option>
                <option value="security" <?= $filterType === 'security' ? 'selected' : '' ?>>Security</option>
                <option value="proposal" <?= $filterType === 'proposal' ? 'selected' : '' ?>>Proposal</option>
                <option value="contract" <?= $filterType === 'contract' ? 'selected' : '' ?>>Contract</option>
                <option value="milestone" <?= $filterType === 'milestone' ? 'selected' : '' ?>>Milestone</option>
                <option value="payment" <?= $filterType === 'payment' ? 'selected' : '' ?>>Payment</option>
                <option value="dispute" <?= $filterType === 'dispute' ? 'selected' : '' ?>>Dispute</option>
                <option value="test" <?= $filterType === 'test' ? 'selected' : '' ?>>Test</option>
            </select>
            <select name="status" class="px-3 py-2 rounded-lg border border-gray-200 dark:border-slate-600 bg-gray-50 dark:bg-slate-700 text-sm text-gray-700 dark:text-gray-300">
                <option value="">All Status</option>
                <option value="sent" <?= $filterStatus === 'sent' ? 'selected' : '' ?>>Sent</option>
                <option value="failed" <?= $filterStatus === 'failed' ? 'selected' : '' ?>>Failed</option>
            </select>
            <button type="submit" class="px-4 py-2 rounded-lg bg-indigo-600 hover:bg-indigo-700 text-white text-sm font-semibold transition-colors">Filter</button>
            <?php if (!empty($search) || !empty($filterType) || !empty($filterStatus)): ?>
                <a href="email_logs.php" class="px-4 py-2 rounded-lg border border-gray-200 dark:border-slate-600 text-gray-600 dark:text-gray-400 text-sm hover:bg-gray-50 dark:hover:bg-slate-700 transition-colors">Clear</a>
            <?php endif; ?>
        </form>
    </div>

    <div class="bg-white dark:bg-slate-800 rounded-xl border border-gray-200 dark:border-slate-700 overflow-hidden">
        <?php if ($logs->num_rows > 0): ?>
            <div class="overflow-x-auto">
                <table class="w-full">
                    <thead>
                        <tr class="border-b border-gray-100 dark:border-slate-700">
                            <th class="text-left px-4 py-3 text-xs font-semibold text-gray-500 dark:text-slate-400 uppercase tracking-wide">Status</th>
                            <th class="text-left px-4 py-3 text-xs font-semibold text-gray-500 dark:text-slate-400 uppercase tracking-wide">Recipient</th>
                            <th class="text-left px-4 py-3 text-xs font-semibold text-gray-500 dark:text-slate-400 uppercase tracking-wide">Subject</th>
                            <th class="text-left px-4 py-3 text-xs font-semibold text-gray-500 dark:text-slate-400 uppercase tracking-wide">Type</th>
                            <th class="text-left px-4 py-3 text-xs font-semibold text-gray-500 dark:text-slate-400 uppercase tracking-wide">Sent At</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-50 dark:divide-slate-700">
                        <?php while ($log = $logs->fetch_assoc()): ?>
                            <?php
                            $payload = json_decode($log['payload'], true) ?? [];
                            $isSuccess = !empty($payload['success']);
                            $recipient = $payload['to'] ?? '-';
                            $subject = $payload['subject'] ?? '-';
                            $notifType = $payload['notification_type'] ?? '-';
                            $errorMsg = $payload['error_message'] ?? null;
                            ?>
                            <tr class="hover:bg-gray-50 dark:hover:bg-slate-700/50">
                                <td class="px-4 py-3">
                                    <?php if ($isSuccess): ?>
                                        <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full bg-emerald-50 text-emerald-700 text-xs font-semibold dark:bg-emerald-900/30 dark:text-emerald-400">
                                            <span class="w-1.5 h-1.5 rounded-full bg-emerald-500"></span> Sent
                                        </span>
                                    <?php else: ?>
                                        <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full bg-red-50 text-red-700 text-xs font-semibold dark:bg-red-900/30 dark:text-red-400" title="<?= htmlspecialchars($errorMsg ?? '') ?>">
                                            <span class="w-1.5 h-1.5 rounded-full bg-red-500"></span> Failed
                                        </span>
                                    <?php endif; ?>
                                </td>
                                <td class="px-4 py-3 text-sm text-gray-700 dark:text-gray-300"><?= htmlspecialchars($recipient) ?></td>
                                <td class="px-4 py-3 text-sm text-gray-900 dark:text-white font-medium max-w-xs truncate"><?= htmlspecialchars($subject) ?></td>
                                <td class="px-4 py-3">
                                    <span class="inline-flex px-2 py-0.5 rounded-md bg-gray-100 text-gray-600 text-xs font-medium dark:bg-slate-700 dark:text-gray-300"><?= htmlspecialchars($notifType) ?></span>
                                </td>
                                <td class="px-4 py-3 text-sm text-gray-500 dark:text-slate-400"><?= date('M d, Y H:i', strtotime($log['created_at'])) ?></td>
                            </tr>
                        <?php endwhile; ?>
                    </tbody>
                </table>
            </div>

            <?php if ($totalPages > 1): ?>
                <div class="flex items-center justify-between px-4 py-3 border-t border-gray-100 dark:border-slate-700">
                    <p class="text-sm text-gray-500 dark:text-slate-400">Page <?= $page ?> of <?= $totalPages ?> (<?= number_format($totalItems) ?> total)</p>
                    <div class="flex items-center gap-1">
                        <?php if ($page > 1): ?>
                            <a href="?<?= http_build_query(array_merge($_GET, ['page' => $page - 1])) ?>" class="px-3 py-1.5 rounded-lg border border-gray-200 dark:border-slate-600 text-sm text-gray-600 dark:text-gray-400 hover:bg-gray-50 dark:hover:bg-slate-700">Prev</a>
                        <?php endif; ?>
                        <?php if ($page < $totalPages): ?>
                            <a href="?<?= http_build_query(array_merge($_GET, ['page' => $page + 1])) ?>" class="px-3 py-1.5 rounded-lg border border-gray-200 dark:border-slate-600 text-sm text-gray-600 dark:text-gray-400 hover:bg-gray-50 dark:hover:bg-slate-700">Next</a>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endif; ?>
        <?php else: ?>
            <div class="text-center py-16">
                <div class="w-16 h-16 rounded-2xl bg-gray-100 dark:bg-slate-700 flex items-center justify-center mx-auto mb-4">
                    <i data-lucide="mail" class="w-8 h-8 text-gray-300 dark:text-slate-500"></i>
                </div>
                <p class="text-gray-500 dark:text-slate-400 font-medium">No email logs found</p>
                <p class="text-sm text-gray-400 dark:text-slate-500 mt-1">Emails will appear here once notifications are sent.</p>
            </div>
        <?php endif; ?>
    </div>
</main>

<script>lucide.createIcons();</script>
<?php require_once __DIR__ . '/../components/layout_end.php'; ?>
