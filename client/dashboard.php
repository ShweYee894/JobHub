<?php

/**
 * Client Dashboard – JobHub
 * Modern card-based dashboard with top navigation. Tailwind CSS only.
 */
require_once __DIR__ . '/../auth/auth.php';
require_role('client');
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../components/stat_card.php';

$currentPage = 'dashboard';
$userId = $_SESSION['user_id'];

// User Info
$uStmt = $conn->prepare('SELECT name, profile_image FROM users WHERE id = ?');
$uStmt->bind_param('i', $userId);
$uStmt->execute();
$user = $uStmt->get_result()->fetch_assoc();
$uStmt->close();

$clientName = $user['name'] ?? 'Client';
$clientFirst = explode(' ', $clientName)[0];
$clientAvatar = get_profile_image($user['profile_image'] ?? null);

// Job status counts
$s1 = $conn->prepare('SELECT status, COUNT(*) AS cnt FROM jobs WHERE client_id = ? GROUP BY status');
$s1->bind_param('i', $userId);
$s1->execute();
$s1Result = $s1->get_result();
$jobStatusCounts = [];
while ($row = $s1Result->fetch_assoc())
    $jobStatusCounts[$row['status']] = (int) $row['cnt'];
$s1Result->free();
$s1->close();

$totalJobs = array_sum($jobStatusCounts);
$openJobs = $jobStatusCounts['open'] ?? 0;
$inProgressJobs = $jobStatusCounts['in_progress'] ?? 0;
$completedJobs = $jobStatusCounts['completed'] ?? 0;

// Active contracts
$s5 = $conn->prepare("SELECT COUNT(*) AS cnt FROM contracts WHERE client_id = ? AND status = 'active'");
$s5->bind_param('i', $userId);
$s5->execute();
$activeContracts = (int) $s5->get_result()->fetch_assoc()['cnt'];
$s5->close();

// Total spending
$s6 = $conn->prepare("SELECT COALESCE(SUM(total_amount), 0) AS total FROM payments WHERE payer_id = ? AND status = 'completed'");
$s6->bind_param('i', $userId);
$s6->execute();
$totalSpending = (float) $s6->get_result()->fetch_assoc()['total'];
$s6->close();

// Unread messages
$umStmt = $conn->prepare('SELECT COUNT(*) AS cnt FROM chat_messages cm JOIN chat_rooms cr ON cm.room_id = cr.id JOIN contracts c ON cr.contract_id = c.id WHERE c.client_id = ? AND cm.sender_id != ? AND cm.is_read = 0');
$umStmt->bind_param('ii', $userId, $userId);
$umStmt->execute();
$unreadMessages = (int) $umStmt->get_result()->fetch_assoc()['cnt'];
$umStmt->close();

// New proposals received
$npStmt = $conn->prepare("SELECT COUNT(*) AS cnt FROM proposals p JOIN jobs j ON p.job_id = j.id WHERE j.client_id = ? AND p.status = 'pending'");
$npStmt->bind_param('i', $userId);
$npStmt->execute();
$newProposals = (int) $npStmt->get_result()->fetch_assoc()['cnt'];
$npStmt->close();

// Chart data
$chartLabels = ['Open', 'In Progress', 'Completed'];
$chartData = [$openJobs, $inProgressJobs, $completedJobs];

// Recent Jobs
$rjStmt = $conn->prepare('SELECT id, title, status, budget, created_at FROM jobs WHERE client_id = ? ORDER BY created_at DESC LIMIT 5');
$rjStmt->bind_param('i', $userId);
$rjStmt->execute();
$recentJobs = $rjStmt->get_result();
$rjStmt->close();

// Recent Proposals
$rpStmt = $conn->prepare('
    SELECT p.id, p.amount, p.status AS proposal_status, p.created_at,
           j.title AS job_title, j.id AS job_id, u.name AS freelancer_name
    FROM proposals p JOIN jobs j ON p.job_id = j.id
    JOIN freelancers f ON p.freelancer_id = f.id JOIN users u ON f.user_id = u.id
    WHERE j.client_id = ? ORDER BY p.created_at DESC LIMIT 5
');
$rpStmt->bind_param('i', $userId);
$rpStmt->execute();
$recentProposals = $rpStmt->get_result();
$rpStmt->close();

// Recent Contracts
$rcStmt = $conn->prepare('
    SELECT c.id, c.total_budget, c.status, c.contract_type, c.created_at,
           j.title AS job_title, u.name AS freelancer_name
    FROM contracts c JOIN jobs j ON c.job_id = j.id
    JOIN freelancers f ON c.freelancer_id = f.user_id JOIN users u ON f.user_id = u.id
    WHERE c.client_id = ? ORDER BY c.created_at DESC LIMIT 5
');
$rcStmt->bind_param('i', $userId);
$rcStmt->execute();
$recentContracts = $rcStmt->get_result();
$rcStmt->close();

// Recent Messages
$rmStmt = $conn->prepare('
    SELECT cm.message_text, cm.created_at, u.name AS sender_name
    FROM chat_messages cm JOIN chat_rooms cr ON cm.room_id = cr.id
    JOIN contracts c ON cr.contract_id = c.id JOIN users u ON cm.sender_id = u.id
    WHERE c.client_id = ? AND cm.sender_id != ? ORDER BY cm.created_at DESC LIMIT 5
');
$rmStmt->bind_param('ii', $userId, $userId);
$rmStmt->execute();
$recentMessages = $rmStmt->get_result();
$rmStmt->close();

$conn->close();

$jobStatusColors = [
    'open' => 'bg-emerald-50 text-emerald-600 border-emerald-200 dark:bg-emerald-900/20 dark:text-emerald-400 dark:border-emerald-800',
    'in_progress' => 'bg-amber-50 text-amber-600 border-amber-200 dark:bg-amber-900/20 dark:text-amber-400 dark:border-amber-800',
    'completed' => 'bg-blue-50 text-blue-600 border-blue-200 dark:bg-blue-900/20 dark:text-blue-400 dark:border-blue-800',
    'closed' => 'bg-gray-50 text-gray-600 border-gray-200 dark:bg-gray-800 dark:text-gray-400 dark:border-gray-700',
    'cancelled' => 'bg-red-50 text-red-600 border-red-200 dark:bg-red-900/20 dark:text-red-400 dark:border-red-800',
];
$proposalStatusColors = [
    'pending' => 'bg-amber-50 text-amber-600 border-amber-200 dark:bg-amber-900/20 dark:text-amber-400 dark:border-amber-800',
    'accepted' => 'bg-emerald-50 text-emerald-600 border-emerald-200 dark:bg-emerald-900/20 dark:text-emerald-400 dark:border-emerald-800',
    'rejected' => 'bg-red-50 text-red-600 border-red-200 dark:bg-red-900/20 dark:text-red-400 dark:border-red-800',
    'withdrawn' => 'bg-gray-50 text-gray-600 border-gray-200 dark:bg-gray-800 dark:text-gray-400 dark:border-gray-700',
];
$contractStatusColors = [
    'active' => 'bg-emerald-50 text-emerald-600 border-emerald-200 dark:bg-emerald-900/20 dark:text-emerald-400 dark:border-emerald-800',
    'completed' => 'bg-blue-50 text-blue-600 border-blue-200 dark:bg-blue-900/20 dark:text-blue-400 dark:border-blue-800',
    'pending' => 'bg-amber-50 text-amber-600 border-amber-200 dark:bg-amber-900/20 dark:text-amber-400 dark:border-amber-800',
    'cancelled' => 'bg-gray-50 text-gray-600 border-gray-200 dark:bg-gray-800 dark:text-gray-400 dark:border-gray-700',
];

// Navigation items
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
$pageTitle = 'Client Dashboard';
$pageSubtitle = 'Welcome back, ' . htmlspecialchars($clientFirst) . " — here's your overview";
$activePage = 'dashboard';
$user = ['name' => $clientName, 'profile_image' => $user['profile_image'] ?? null];
$unreadCount = $unreadMessages;
$profileLink = 'profile.php';
require_once __DIR__ . '/../components/layout_start.php';
?>

    <?php display_flash('success');
    display_flash('error');
    display_flash('info'); ?>

    <!-- Welcome Banner -->
    <div class="fade-in relative overflow-hidden rounded-[20px] p-3 ">
        <div class="relative z-10">
            <h2 class="text-2xl font-extrabold text-gray-700 m-0">Welcome back, <?= htmlspecialchars($clientFirst) ?> 👋</h2>
            <p class="text-gray-500 text-sm mt-1.5 m-0">Manage your projects and find top talent.</p>
        </div>
        <div class="absolute -top-[60px] -right-[40px] w-[260px] h-[260px] rounded-full bg-white/7"></div>
        <div class="absolute -bottom-[40px] left-[30%] w-[180px] h-[180px] rounded-full bg-white/4"></div>
    </div>

    <!-- Stats Cards -->
    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-4">
        <?php
        renderStatCard('Open Jobs', $openJobs, 'up', '+18%', 'vs last month', 'fa-briefcase', 'blue', 'Jobs', 'my_jobs.php', 0);
        renderStatCard('Active Contracts', $activeContracts, 'neutral', '', '', 'fa-file-contract', 'emerald', 'Contracts', 'contracts.php', 1);
        renderStatCard('Total Spending', $totalSpending, 'up', '+8%', 'vs last month', 'fa-dollar-sign', 'purple', 'Spent', 'payment_history.php', 2);
        renderStatCard('Pending Proposals', $newProposals, 'up', '+12%', 'vs last week', 'fa-file-signature', 'orange', 'Proposals', 'proposals.php', 3);
        renderStatCard('Unread Messages', $unreadMessages, 'neutral', '', '', 'fa-comments', 'cyan', 'Messages', 'messages.php', 4);
        renderStatCard('Completed', $completedJobs, 'up', '+5%', 'vs last month', 'fa-check-circle', 'rose', 'Projects', '', 5);
        ?>
    </div>

    <!-- Two Column: Jobs + Contracts -->
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
        <!-- Recent Jobs -->
        <div class="dh-card fade-in p-6">
            <div class="flex items-center justify-between mb-5">
                <h4 class="text-sm font-bold text-slate-900 dark:text-white m-0">Recent Jobs</h4>
                <a href="my_jobs.php" class="text-xs font-semibold text-blue-600 no-underline hover:underline">View All →</a>
            </div>
            <?php if ($recentJobs->num_rows > 0): ?>
                <div class="overflow-x-auto">
                    <table class="dtbl">
                        <thead><tr><th>Job</th><th>Status</th><th class="text-right">Budget</th></tr></thead>
                        <tbody>
                            <?php while ($job = $recentJobs->fetch_assoc()): ?>
                                <tr>
                                    <td>
                                        <a href="job_detail.php?id=<?= (int) $job['id'] ?>" class="font-semibold text-slate-900 dark:text-white no-underline hover:underline"><?= sanitize_string($job['title']) ?></a>
                                        <p class="text-[11px] text-slate-400 mt-0.5 m-0"><?= time_ago($job['created_at']) ?></p>
                                    </td>
                                    <td><span class="bdg <?= $jobStatusColors[$job['status']] ?? '' ?>"><?= sanitize_string(ucfirst(str_replace('_', ' ', $job['status']))) ?></span></td>
                                    <td class="text-right font-semibold text-slate-900 dark:text-white"><?= format_currency((float) $job['budget']) ?></td>
                                </tr>
                            <?php endwhile; ?>
                        </tbody>
                    </table>
                </div>
            <?php else: ?>
                <div class="text-center py-6">
                    <i class="fas fa-briefcase text-4xl text-slate-300 dark:text-slate-600 mb-3 block"></i>
                    <p class="text-slate-500 dark:text-slate-400 text-[13px] m-0 mb-3">No jobs posted yet</p>
                    <a href="post_job.php" class="btn-grad text-xs text-white px-4 py-2 rounded-[10px] no-underline inline-block">Post a Job</a>
                </div>
            <?php endif; ?>
        </div>

        <!-- Recent Contracts -->
        <div class="dh-card fade-in p-6">
            <div class="flex items-center justify-between mb-5">
                <h4 class="text-sm font-bold text-slate-900 dark:text-white m-0">Recent Contracts</h4>
                <a href="contracts.php" class="text-xs font-semibold text-blue-600 no-underline hover:underline">View All →</a>
            </div>
            <?php if ($recentContracts->num_rows > 0): ?>
                <div class="flex flex-col gap-2.5">
                    <?php while ($contract = $recentContracts->fetch_assoc()): ?>
                        <div class="p-3.5 rounded-xl border border-slate-100 dark:border-slate-700 flex items-center gap-3 transition-all hover:shadow-md dark:bg-slate-800">
                            <div class="w-10 h-10 rounded-[10px] bg-gradient-to-br from-blue-600 to-cyan-500 flex items-center justify-center shrink-0">
                                <i class="fas fa-handshake text-white text-sm"></i>
                            </div>
                            <div class="flex-1 min-w-0">
                                <p class="text-[13px] font-semibold text-slate-900 dark:text-white m-0 truncate"><?= sanitize_string($contract['job_title']) ?></p>
                                <p class="text-[11px] text-slate-400 mt-0.5 m-0"><?= sanitize_string($contract['freelancer_name']) ?> · <?= sanitize_string(ucfirst($contract['contract_type'])) ?></p>
                            </div>
                            <div class="text-right shrink-0">
                                <p class="text-[13px] font-bold text-slate-900 dark:text-white m-0"><?= format_currency((float) $contract['total_budget']) ?></p>
                                <span class="bdg text-[10px] <?= $contractStatusColors[$contract['status']] ?? '' ?>"><?= sanitize_string(ucfirst($contract['status'])) ?></span>
                            </div>
                        </div>
                    <?php endwhile; ?>
                </div>
            <?php else: ?>
                <div class="text-center py-6"><p class="text-slate-400 text-[13px]">No active contracts</p></div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Two Column: Proposals + Messages -->
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
        <!-- Recent Proposals -->
        <div class="dh-card fade-in p-6">
            <div class="flex items-center justify-between mb-5">
                <h4 class="text-sm font-bold text-slate-900 dark:text-white m-0">Recent Proposals</h4>
                <a href="proposals.php" class="text-xs font-semibold text-blue-600 no-underline hover:underline">View All →</a>
            </div>
            <?php if ($recentProposals->num_rows > 0): ?>
                <div class="overflow-x-auto">
                    <table class="dtbl">
                        <thead><tr><th>Freelancer</th><th>Job</th><th>Status</th><th class="text-right">Amount</th></tr></thead>
                        <tbody>
                            <?php while ($prop = $recentProposals->fetch_assoc()): ?>
                                <tr>
                                    <td>
                                        <div class="flex items-center gap-2">
                                            <div class="w-7 h-7 rounded-lg bg-gradient-to-br from-blue-600 to-cyan-500 flex items-center justify-center shrink-0">
                                                <span class="text-white text-[10px] font-bold"><?= strtoupper(substr($prop['freelancer_name'], 0, 1)) ?></span>
                                            </div>
                                            <span class="font-semibold text-slate-900 dark:text-white text-xs"><?= sanitize_string($prop['freelancer_name']) ?></span>
                                        </div>
                                    </td>
                                    <td><a href="job_detail.php?id=<?= (int) $prop['job_id'] ?>" class="text-slate-500 dark:text-slate-400 no-underline text-xs hover:underline"><?= sanitize_string($prop['job_title']) ?></a></td>
                                    <td><span class="bdg <?= $proposalStatusColors[$prop['proposal_status']] ?? '' ?>"><?= sanitize_string(ucfirst($prop['proposal_status'])) ?></span></td>
                                    <td class="text-right font-semibold text-slate-900 dark:text-white"><?= format_currency((float) $prop['amount']) ?></td>
                                </tr>
                            <?php endwhile; ?>
                        </tbody>
                    </table>
                </div>
            <?php else: ?>
                <div class="text-center py-6"><p class="text-slate-400 text-[13px]">No proposals received yet</p></div>
            <?php endif; ?>
        </div>

        <!-- Recent Messages -->
        <div class="dh-card fade-in p-6">
            <div class="flex items-center justify-between mb-5">
                <h4 class="text-sm font-bold text-slate-900 dark:text-white m-0">Recent Messages</h4>
                <a href="messages.php" class="text-xs font-semibold text-blue-600 no-underline hover:underline">Open Chat →</a>
            </div>
            <?php if ($recentMessages->num_rows > 0): ?>
                <div class="flex flex-col gap-2.5">
                    <?php while ($msg = $recentMessages->fetch_assoc()): ?>
                        <a href="messages.php" class="flex items-center gap-3 p-3 rounded-xl text-no-underline text-inherit transition-all hover:bg-slate-50 dark:hover:bg-slate-800 no-underline">
                            <div class="w-10 h-10 rounded-[10px] bg-gradient-to-br from-purple-600 to-pink-500 flex items-center justify-center shrink-0">
                                <span class="text-white text-xs font-bold"><?= strtoupper(substr($msg['sender_name'], 0, 1)) ?></span>
                            </div>
                            <div class="flex-1 min-w-0">
                                <p class="text-[13px] font-semibold text-slate-900 dark:text-white m-0"><?= htmlspecialchars($msg['sender_name']) ?></p>
                                <p class="text-xs text-slate-400 mt-0.5 m-0 truncate"><?= htmlspecialchars($msg['message_text']) ?></p>
                            </div>
                            <span class="text-[11px] text-slate-400 shrink-0"><?= time_ago($msg['created_at']) ?></span>
                        </a>
                    <?php endwhile; ?>
                </div>
            <?php else: ?>
                <div class="text-center py-6"><p class="text-slate-400 text-[13px]">No messages yet</p></div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Quick Actions -->
    <div>
        <h3 class="text-base font-bold text-slate-900 dark:text-white m-0 mb-4">Quick Actions</h3>
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
            <a href="post_job.php" class="qa-card fade-in no-underline block">
                <div class="w-12 h-12 rounded-2xl bg-blue-50 dark:bg-blue-900/20 text-blue-600 dark:text-blue-400 flex items-center justify-center text-lg mb-3">
                    <i class="fas fa-plus-circle"></i>
                </div>
                <h5 class="text-sm font-bold text-slate-900 dark:text-white mt-3 mb-1">Post a Job</h5>
                <p class="text-xs text-slate-400 m-0">Hire new talent</p>
            </a>
            <a href="proposals.php" class="qa-card fade-in no-underline block">
                <div class="w-12 h-12 rounded-2xl bg-emerald-50 dark:bg-emerald-900/20 text-emerald-600 dark:text-emerald-400 flex items-center justify-center text-lg mb-3">
                    <i class="fas fa-file-alt"></i>
                </div>
                <h5 class="text-sm font-bold text-slate-900 dark:text-white mt-3 mb-1">Review Proposals</h5>
                <p class="text-xs text-slate-400 m-0"><?= $newProposals ?> pending</p>
            </a>
            <a href="recommended_freelancers.php" class="qa-card fade-in no-underline block">
                <div class="w-12 h-12 rounded-2xl bg-purple-50 dark:bg-purple-900/20 text-purple-600 dark:text-purple-400 flex items-center justify-center text-lg mb-3">
                    <i class="fas fa-brain"></i>
                </div>
                <h5 class="text-sm font-bold text-slate-900 dark:text-white mt-3 mb-1">AI Matches</h5>
                <p class="text-xs text-slate-400 m-0">Find top talent</p>
            </a>
            <a href="profile.php" class="qa-card fade-in no-underline block">
                <div class="w-12 h-12 rounded-2xl bg-orange-50 dark:bg-orange-900/20 text-orange-600 dark:text-orange-400 flex items-center justify-center text-lg mb-3">
                    <i class="fas fa-user-pen"></i>
                </div>
                <h5 class="text-sm font-bold text-slate-900 dark:text-white mt-3 mb-1">Edit Profile</h5>
                <p class="text-xs text-slate-400 m-0">Update info</p>
            </a>
        </div>
    </div>

<?php require_once __DIR__ . '/../components/layout_end.php'; ?>
