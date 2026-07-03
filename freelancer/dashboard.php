<?php

/**
 * Freelancer Dashboard – FreelanceHub
 * Modern card-based dashboard with top navigation.
 * Uses Tailwind CSS utility classes. No external CSS files.
 */
session_start();
require_once __DIR__ . '/../config/db.php';

if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'freelancer') {
    header('Location: ../auth/login.php');
    exit();
}

require_once __DIR__ . '/../components/stat_card.php';

$userId = $_SESSION['user_id'];

// User Info
$stmt = $conn->prepare('SELECT name, profile_image FROM users WHERE id = ?');
$stmt->bind_param('i', $userId);
$stmt->execute();
$user = $stmt->get_result()->fetch_assoc();
$stmt->close();
$freelancerName = $user['name'] ?? 'Freelancer';
$freelancerFirst = explode(' ', $freelancerName)[0];
$freelancerAvatar = get_profile_image($user['profile_image'] ?? null);

// Freelancer Info
$stmt = $conn->prepare('SELECT id, title, hourly_rate, years_of_experience, availability FROM freelancers WHERE user_id = ?');
$stmt->bind_param('i', $userId);
$stmt->execute();
$freelancer = $stmt->get_result()->fetch_assoc();
$stmt->close();
$freelancerId = $freelancer['id'] ?? 0;

// Proposal status counts
$stmt = $conn->prepare('SELECT status, COUNT(*) AS cnt FROM proposals WHERE freelancer_id = ? GROUP BY status');
$stmt->bind_param('i', $freelancerId);
$stmt->execute();
$proposalCounts = ['pending' => 0, 'accepted' => 0, 'rejected' => 0, 'withdrawn' => 0];
while ($row = $stmt->get_result()->fetch_assoc())
    $proposalCounts[$row['status']] = (int) $row['cnt'];
$stmt->close();
$totalProposals = array_sum($proposalCounts);

// Active contracts
$stmt = $conn->prepare("SELECT COUNT(*) AS total FROM contracts WHERE freelancer_id = ? AND status = 'active'");
$stmt->bind_param('i', $userId);
$stmt->execute();
$activeContracts = $stmt->get_result()->fetch_assoc()['total'];
$stmt->close();

// Total earnings
$stmt = $conn->prepare("SELECT COALESCE(SUM(total_amount), 0) AS total FROM payments WHERE payee_id = ? AND status = 'completed'");
$stmt->bind_param('i', $userId);
$stmt->execute();
$totalEarnings = $stmt->get_result()->fetch_assoc()['total'];
$stmt->close();

// Pending payments
$stmt = $conn->prepare("SELECT COALESCE(SUM(c.total_budget), 0) AS pending FROM contracts c WHERE c.freelancer_id = ? AND c.status = 'active'");
$stmt->bind_param('i', $userId);
$stmt->execute();
$pendingPayments = (float) $stmt->get_result()->fetch_assoc()['pending'];
$stmt->close();

// Available jobs
$stmt = $conn->prepare("SELECT COUNT(*) AS total FROM jobs WHERE status = 'open'");
$stmt->execute();
$availableJobs = $stmt->get_result()->fetch_assoc()['total'];
$stmt->close();

// Average rating
$stmt = $conn->prepare('SELECT COALESCE(AVG(rating), 0) AS avg_rating FROM reviews WHERE reviewee_id = ?');
$stmt->bind_param('i', $userId);
$stmt->execute();
$avgRating = round((float) $stmt->get_result()->fetch_assoc()['avg_rating'], 1);
$stmt->close();

// Unread messages
$stmt = $conn->prepare('SELECT COUNT(*) AS cnt FROM chat_messages cm JOIN chat_rooms cr ON cm.room_id = cr.id JOIN contracts c ON cr.contract_id = c.id WHERE c.freelancer_id = ? AND cm.sender_id != ? AND cm.is_read = 0');
$stmt->bind_param('ii', $userId, $userId);
$stmt->execute();
$unreadMessages = (int) $stmt->get_result()->fetch_assoc()['cnt'];
$stmt->close();

// This month earnings
$stmt = $conn->prepare("SELECT COALESCE(SUM(total_amount), 0) AS total FROM payments WHERE payee_id = ? AND status = 'completed' AND MONTH(created_at) = MONTH(CURDATE()) AND YEAR(created_at) = YEAR(CURDATE())");
$stmt->bind_param('i', $userId);
$stmt->execute();
$monthEarnings = (float) $stmt->get_result()->fetch_assoc()['total'];
$stmt->close();

// Profile completion
$profFields = 0;
$profTotal = 7;
if (!empty($user['name'])) $profFields++;
if (!empty($user['profile_image'])) $profFields++;
if (!empty($freelancer['title'])) $profFields++;
if (!empty($freelancer['hourly_rate'])) $profFields++;
if (!empty($freelancer['availability'])) $profFields++;
$fpStmt = $conn->prepare('SELECT bio FROM freelancers WHERE user_id = ?');
$fpStmt->bind_param('i', $userId);
$fpStmt->execute();
$fpData = $fpStmt->get_result()->fetch_assoc();
$fpStmt->close();
if (!empty($fpData['bio'])) $profFields++;
$profileCompletion = min(100, round(($profFields / $profTotal) * 100));

// Recommended Jobs
$stmt = $conn->prepare('
    SELECT j.id, j.title, j.budget, j.description, j.created_at, u.name AS client_name
    FROM jobs j
    JOIN clients cl ON j.client_id = cl.client_id
    JOIN users u ON cl.client_id = u.id
    WHERE j.status = "open"
    ORDER BY j.created_at DESC LIMIT 5
');
$stmt->execute();
$recommendedJobs = $stmt->get_result();
$stmt->close();

// Recent Proposals
$stmt = $conn->prepare('
    SELECT p.id, p.status, p.amount, p.job_id, p.created_at, j.title AS job_title
    FROM proposals p JOIN jobs j ON p.job_id = j.id
    WHERE p.freelancer_id = ? ORDER BY p.created_at DESC LIMIT 5
');
$stmt->bind_param('i', $freelancerId);
$stmt->execute();
$recentProposals = $stmt->get_result();
$stmt->close();

// Active Contracts
$stmt = $conn->prepare('
    SELECT c.id, c.total_budget, c.status, c.contract_type, c.created_at,
           j.title AS job_title, u.name AS client_name,
           (SELECT COUNT(*) FROM milestones m WHERE m.contract_id = c.id) AS total_milestones,
           (SELECT COUNT(*) FROM milestones m WHERE m.contract_id = c.id AND m.status = "released") AS completed_milestones
    FROM contracts c JOIN jobs j ON c.job_id = j.id JOIN users u ON c.client_id = u.id
    WHERE c.freelancer_id = ? ORDER BY c.created_at DESC LIMIT 5
');
$stmt->bind_param('i', $userId);
$stmt->execute();
$activeContractsList = $stmt->get_result();
$stmt->close();

// Monthly Earnings Chart
$earningsChart = [];
$stmt = $conn->prepare("
    SELECT DATE_FORMAT(created_at, '%Y-%m') AS month_key,
           DATE_FORMAT(created_at, '%b') AS month_label,
           COALESCE(SUM(total_amount), 0) AS earnings
    FROM payments WHERE payee_id = ? AND status = 'completed'
      AND created_at >= DATE_SUB(CURDATE(), INTERVAL 6 MONTH)
    GROUP BY month_key, month_label ORDER BY month_key ASC
");
$stmt->bind_param('i', $userId);
$stmt->execute();
$ecResult = $stmt->get_result();
while ($row = $ecResult->fetch_assoc()) $earningsChart[] = $row;
$stmt->close();
$earningsLabels = array_column($earningsChart, 'month_label');
$earningsData = array_column($earningsChart, 'earnings');

// Upcoming Deadlines
$stmt = $conn->prepare('
    SELECT m.title AS milestone_name, m.amount, m.status AS milestone_status, j.title AS job_title
    FROM milestones m JOIN contracts c ON m.contract_id = c.id JOIN jobs j ON c.job_id = j.id
    WHERE c.freelancer_id = ? AND m.status NOT IN ("released", "disputed") ORDER BY m.created_at ASC LIMIT 5
');
$stmt->bind_param('i', $userId);
$stmt->execute();
$upcomingDeadlines = $stmt->get_result();
$stmt->close();

// Recent Reviews
$stmt = $conn->prepare('
    SELECT r.rating, r.comment, r.created_at, u.name AS reviewer_name, u.profile_image AS reviewer_image
    FROM reviews r JOIN users u ON r.reviewer_id = u.id WHERE r.reviewee_id = ? ORDER BY r.created_at DESC LIMIT 3
');
$stmt->bind_param('i', $userId);
$stmt->execute();
$recentReviews = $stmt->get_result();
$stmt->close();

// Recent Messages
$stmt = $conn->prepare('
    SELECT cm.message_text, cm.created_at, u.name AS sender_name, u.profile_image AS sender_image
    FROM chat_messages cm JOIN chat_rooms cr ON cm.room_id = cr.id JOIN contracts c ON cr.contract_id = c.id
    JOIN users u ON cm.sender_id = u.id WHERE c.freelancer_id = ? AND cm.sender_id != ? ORDER BY cm.created_at DESC LIMIT 5
');
$stmt->bind_param('ii', $userId, $userId);
$stmt->execute();
$recentMessages = $stmt->get_result();
$stmt->close();

// Profile Performance
$stmt = $conn->prepare('SELECT COUNT(*) AS completed FROM contracts WHERE freelancer_id = ? AND status = "completed"');
$stmt->bind_param('i', $userId);
$stmt->execute();
$completedContracts = $stmt->get_result()->fetch_assoc()['completed'];
$stmt->close();

$stmt = $conn->prepare('SELECT COUNT(*) AS total FROM contracts WHERE freelancer_id = ?');
$stmt->bind_param('i', $userId);
$stmt->execute();
$totalContracts = $stmt->get_result()->fetch_assoc()['total'];
$stmt->close();
$jobSuccessScore = $totalContracts > 0 ? round(($completedContracts / $totalContracts) * 100) : 0;

$stmt = $conn->prepare('SELECT COUNT(*) AS cnt FROM user_behavior_logs WHERE user_id = ? AND action_type = "profile_view"');
$stmt->bind_param('i', $userId);
$stmt->execute();
$profileViews = $stmt->get_result()->fetch_assoc()['cnt'];
$stmt->close();

$stmt = $conn->prepare('SELECT COUNT(*) AS cnt FROM user_behavior_logs WHERE user_id = ? AND action_type = "job_viewed"');
$stmt->bind_param('i', $userId);
$stmt->execute();
$jobsViewed = $stmt->get_result()->fetch_assoc()['cnt'];
$stmt->close();
$responseRate = $jobsViewed > 0 ? min(100, round(($totalProposals / $jobsViewed) * 100)) : 0;

$conn->close();

$proposalStatusBadges = ['pending' => 'bdg-orange', 'accepted' => 'bdg-emerald', 'rejected' => 'bdg-rose', 'withdrawn' => 'bdg-gray'];
$contractStatusBadges = ['active' => 'bdg-emerald', 'completed' => 'bdg-blue', 'pending' => 'bdg-orange', 'cancelled' => 'bdg-gray', 'disputed' => 'bdg-rose'];
$milestoneBadges = ['pending' => 'bdg-orange', 'funded_in_escrow' => 'bdg-cyan', 'submitted' => 'bdg-blue'];

// Navigation items
$navItems = [
    ['key' => 'dashboard', 'label' => 'Dashboard', 'url' => 'dashboard.php', 'icon' => 'fa-th-large'],
    ['key' => 'profile', 'label' => 'Profile', 'url' => 'profile.php', 'icon' => 'fa-user'],
    ['key' => 'browse_jobs', 'label' => 'Browse Jobs', 'url' => 'browse_jobs.php', 'icon' => 'fa-search'],
    ['key' => 'proposals', 'label' => 'Proposals', 'url' => 'proposals.php', 'icon' => 'fa-file-alt'],
    ['key' => 'contracts', 'label' => 'Contracts', 'url' => 'contracts.php', 'icon' => 'fa-handshake'],
    ['key' => 'messages', 'label' => 'Messages', 'url' => 'messages.php', 'icon' => 'fa-comment-dots'],
    ['key' => 'earnings', 'label' => 'Earnings', 'url' => 'earnings.php', 'icon' => 'fa-wallet'],
];
$pageTitle = 'Freelancer Dashboard';
$pageSubtitle = 'Welcome back, ' . htmlspecialchars($freelancerFirst) . " — here's your overview";
$activePage = 'dashboard';
$user = ['name' => $freelancerName, 'profile_image' => $user['profile_image'] ?? null];
$unreadCount = $unreadMessages;
$profileLink = 'profile.php';
require_once __DIR__ . '/../components/layout_start.php';
?>

    <?php display_flash('success'); display_flash('error'); display_flash('info'); ?>

    <!-- Welcome Banner -->
    <div class="fade-in rounded-[20px] p-8 relative overflow-hidden" style="background:linear-gradient(135deg, #059669 0%, #10b981 40%, #2563eb 100%);">
        <div class="relative z-10 flex items-center justify-between flex-wrap gap-4">
            <div>
                <h2 class="text-2xl font-extrabold text-white m-0">Good <?= (date('H') < 12 ? 'Morning' : (date('H') < 18 ? 'Afternoon' : 'Evening')) ?>, <?= htmlspecialchars($freelancerFirst) ?> 👋</h2>
                <p class="text-sm text-white/80 mt-1">Let's find great work and grow your freelancing career.</p>
            </div>
            <div class="flex items-center gap-3 flex-wrap">
                <div class="bg-white/15 backdrop-blur-sm rounded-[14px] px-4 py-3 text-white">
                    <p class="text-[11px] m-0 mb-1 opacity-80">Profile Completion</p>
                    <div class="flex items-center gap-2">
                        <div class="w-20 h-1.5 bg-white/20 rounded-full overflow-hidden">
                            <div class="h-full bg-green-400 rounded-full" style="width:<?= $profileCompletion ?>%;"></div>
                        </div>
                        <span class="text-xs font-bold"><?= $profileCompletion ?>%</span>
                    </div>
                </div>
                <div class="bg-white/15 backdrop-blur-sm rounded-[14px] px-4 py-3 text-white text-center">
                    <p class="text-[11px] m-0 mb-0.5 opacity-80">Rating</p>
                    <p class="text-lg font-extrabold m-0"><i class="fas fa-star text-xs text-amber-400"></i> <?= $avgRating ?></p>
                </div>
                <span class="inline-flex items-center gap-1.5 px-4 py-2 rounded-full bg-white/15 text-white text-xs font-semibold backdrop-blur-sm">
                    <span class="w-1.5 h-1.5 rounded-full bg-green-400"></span>
                    <?= htmlspecialchars($freelancer['availability'] ?? 'Available') ?>
                </span>
            </div>
        </div>
    </div>

    <!-- Stats Cards -->
    <div>
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4">
            <?php
            renderStatCard('Available Jobs', $availableJobs, 'up', '+15%', 'vs last week', 'fa-briefcase', 'blue', 'Jobs', 'browse_jobs.php', 0);
            renderStatCard('Submitted Proposals', $totalProposals, 'up', '+8%', 'vs last month', 'fa-file-signature', 'emerald', 'Proposals', 'proposals.php', 1);
            renderStatCard('Active Contracts', $activeContracts, 'neutral', '', '', 'fa-file-contract', 'purple', 'Contracts', 'contracts.php', 2);
            renderStatCard('Pending Payments', $pendingPayments, 'neutral', '', '', 'fa-clock', 'orange', 'Pending', 'earnings.php', 3);
            renderStatCard('This Month', $monthEarnings, 'up', '+22%', 'vs last month', 'fa-dollar-sign', 'cyan', 'Earned', 'earnings.php', 4);
            renderStatCard('Unread Messages', $unreadMessages, 'neutral', '', '', 'fa-comments', 'rose', 'Messages', 'messages.php', 5);
            ?>
        </div>
    </div>

    <!-- Recommended Jobs -->
    <div>
        <div class="flex items-center justify-between mb-4">
            <h3 class="text-base font-bold text-gray-900 dark:text-white m-0">Recommended Jobs</h3>
            <a href="browse_jobs.php" class="text-xs font-semibold text-blue-600 no-underline">Browse All &rarr;</a>
        </div>
        <?php if ($recommendedJobs->num_rows > 0): ?>
            <div class="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-3 gap-4">
                <?php while ($job = $recommendedJobs->fetch_assoc()): ?>
                    <div class="dh-card fade-in p-5 flex flex-col">
                        <p class="text-sm font-bold text-gray-900 dark:text-white m-0 mb-1"><?= htmlspecialchars($job['title']) ?></p>
                        <p class="text-xs text-slate-400 m-0 mb-2.5">by <?= htmlspecialchars($job['client_name']) ?></p>
                        <p class="text-xs text-slate-500 dark:text-slate-400 m-0 mb-3.5 line-clamp-2"><?= htmlspecialchars($job['description'] ?? '') ?></p>
                        <div class="mt-auto flex items-center justify-between">
                            <span class="text-base font-extrabold text-gray-900 dark:text-white"><?= format_currency((float) $job['budget']) ?></span>
                            <a href="job_detail.php?id=<?= (int) $job['id'] ?>" class="bg-blue-600 hover:bg-blue-700 text-white text-xs font-semibold px-4 py-2 rounded-xl transition no-underline">Apply</a>
                        </div>
                        <p class="text-[11px] text-slate-400 mt-2 m-0"><i class="fas fa-clock mr-1"></i><?= time_ago($job['created_at']) ?></p>
                    </div>
                <?php endwhile; ?>
            </div>
        <?php else: ?>
            <div class="dh-card p-6 text-center">
                <i class="fas fa-briefcase text-4xl text-slate-300 mb-3 block"></i>
                <p class="text-slate-500 text-sm m-0 mb-3">No jobs available right now</p>
                <a href="browse_jobs.php" class="bg-gradient-to-r from-blue-600 to-blue-500 text-white text-xs font-semibold px-5 py-2.5 rounded-xl no-underline inline-block">Browse Jobs</a>
            </div>
        <?php endif; ?>
    </div>

    <!-- Two Column: Proposals + Contracts -->
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
        <!-- Recent Proposals -->
        <div class="dh-card fade-in p-6">
            <div class="flex items-center justify-between mb-5">
                <h4 class="text-sm font-bold text-gray-900 dark:text-white m-0">Recent Proposals</h4>
                <a href="proposals.php" class="text-xs font-semibold text-blue-600 no-underline">View All &rarr;</a>
            </div>
            <?php if ($recentProposals->num_rows > 0): ?>
                <div class="overflow-x-auto">
                    <table class="dtbl">
                        <thead><tr><th>Job Title</th><th>Bid</th><th>Status</th><th class="text-right">When</th></tr></thead>
                        <tbody>
                            <?php while ($proposal = $recentProposals->fetch_assoc()): ?>
                                <tr>
                                    <td><a href="proposal_detail.php?id=<?= (int) $proposal['id'] ?>" class="font-semibold text-gray-900 dark:text-white no-underline"><?= htmlspecialchars($proposal['job_title']) ?></a></td>
                                    <td class="font-semibold text-gray-900 dark:text-white"><?= format_currency((float) $proposal['amount']) ?></td>
                                    <td><span class="bdg <?= $proposalStatusBadges[$proposal['status']] ?? 'bdg-gray' ?>"><?= ucfirst($proposal['status']) ?></span></td>
                                    <td class="text-right text-slate-400 text-xs"><?= time_ago($proposal['created_at']) ?></td>
                                </tr>
                            <?php endwhile; ?>
                        </tbody>
                    </table>
                </div>
            <?php else: ?>
                <div class="text-center py-6"><p class="text-slate-400 text-sm">No proposals yet</p></div>
            <?php endif; ?>
        </div>

        <!-- Active Contracts -->
        <div class="dh-card fade-in p-6">
            <div class="flex items-center justify-between mb-5">
                <h4 class="text-sm font-bold text-gray-900 dark:text-white m-0">Active Contracts</h4>
                <a href="contracts.php" class="text-xs font-semibold text-blue-600 no-underline">View All &rarr;</a>
            </div>
            <?php if ($activeContractsList->num_rows > 0): ?>
                <div class="flex flex-col gap-3">
                    <?php while ($contract = $activeContractsList->fetch_assoc()):
                        $milestonePct = $contract['total_milestones'] > 0 ? round(($contract['completed_milestones'] / $contract['total_milestones']) * 100) : 0;
                    ?>
                        <div class="p-3.5 rounded-xl border border-gray-100 dark:border-slate-700 hover:shadow-md transition">
                            <div class="flex items-start justify-between mb-2">
                                <div class="flex-1 min-w-0">
                                    <p class="text-[13px] font-bold text-gray-900 dark:text-white m-0 truncate"><?= htmlspecialchars($contract['job_title']) ?></p>
                                    <p class="text-[11px] text-slate-400 m-0 mt-0.5">Client: <?= htmlspecialchars($contract['client_name']) ?></p>
                                </div>
                                <span class="bdg <?= $contractStatusBadges[$contract['status']] ?? 'bdg-gray' ?> flex-shrink-0"><?= ucfirst($contract['status']) ?></span>
                            </div>
                            <div class="mb-2">
                                <div class="flex justify-between mb-0.5">
                                    <span class="text-[10px] text-slate-400">Progress</span>
                                    <span class="text-[10px] font-semibold text-gray-900 dark:text-white"><?= $milestonePct ?>%</span>
                                </div>
                                <div class="pbar"><div class="pbar-fill green" style="width:<?= $milestonePct ?>%;"></div></div>
                            </div>
                            <div class="flex items-center justify-between">
                                <span class="text-xs font-bold text-gray-900 dark:text-white"><?= format_currency((float) $contract['total_budget']) ?></span>
                                <a href="messages.php?contract=<?= (int) $contract['id'] ?>" class="text-[11px] font-semibold text-cyan-600 no-underline">💬 Message</a>
                            </div>
                        </div>
                    <?php endwhile; ?>
                </div>
            <?php else: ?>
                <div class="text-center py-6"><p class="text-slate-400 text-sm">No active contracts</p></div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Earnings Overview -->
    <div class="dh-card fade-in p-6">
        <div class="flex items-center justify-between mb-5">
            <h4 class="text-sm font-bold text-gray-900 dark:text-white m-0">Earnings Overview</h4>
            <a href="earnings.php" class="text-xs font-semibold text-blue-600 no-underline">View Details &rarr;</a>
        </div>
        <div class="grid grid-cols-1 sm:grid-cols-3 gap-4 mb-5">
            <div class="p-4 rounded-[14px] bg-emerald-50 dark:bg-emerald-900/20 text-center">
                <p class="text-[11px] text-emerald-600 dark:text-emerald-400 m-0 mb-1 font-semibold">Total Earnings</p>
                <p class="text-xl font-extrabold text-gray-900 dark:text-white m-0"><?= format_currency($totalEarnings) ?></p>
            </div>
            <div class="p-4 rounded-[14px] bg-orange-50 dark:bg-orange-900/20 text-center">
                <p class="text-[11px] text-orange-600 dark:text-orange-400 m-0 mb-1 font-semibold">Pending</p>
                <p class="text-xl font-extrabold text-gray-900 dark:text-white m-0"><?= format_currency($pendingPayments) ?></p>
            </div>
            <div class="p-4 rounded-[14px] bg-blue-50 dark:bg-blue-900/20 text-center">
                <p class="text-[11px] text-blue-600 dark:text-blue-400 m-0 mb-1 font-semibold">This Month</p>
                <p class="text-xl font-extrabold text-gray-900 dark:text-white m-0"><?= format_currency($monthEarnings) ?></p>
            </div>
        </div>
        <div class="relative h-48">
            <canvas id="earningsChart"></canvas>
        </div>
    </div>

    <!-- Two Column: Milestones + Reviews -->
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
        <!-- Pending Milestones -->
        <div class="dh-card fade-in p-6">
            <h4 class="text-sm font-bold text-gray-900 dark:text-white m-0 mb-4">Pending Milestones</h4>
            <?php if ($upcomingDeadlines->num_rows > 0): ?>
                <div class="overflow-x-auto">
                    <table class="dtbl">
                        <thead><tr><th>Project</th><th>Milestone</th><th>Amount</th><th>Status</th></tr></thead>
                        <tbody>
                            <?php while ($dl = $upcomingDeadlines->fetch_assoc()): ?>
                                <tr>
                                    <td class="font-semibold text-gray-900 dark:text-white max-w-[120px] truncate"><?= htmlspecialchars($dl['job_title']) ?></td>
                                    <td class="text-slate-500 dark:text-slate-400"><?= htmlspecialchars($dl['milestone_name']) ?></td>
                                    <td class="font-semibold text-gray-900 dark:text-white"><?= format_currency((float) $dl['amount']) ?></td>
                                    <td><span class="bdg <?= $milestoneBadges[$dl['milestone_status']] ?? 'bdg-gray' ?>"><?= ucfirst(str_replace('_', ' ', $dl['milestone_status'])) ?></span></td>
                                </tr>
                            <?php endwhile; ?>
                        </tbody>
                    </table>
                </div>
            <?php else: ?>
                <div class="text-center py-5"><p class="text-slate-400 text-sm">No pending milestones</p></div>
            <?php endif; ?>
        </div>

        <!-- Recent Reviews -->
        <div>
            <div class="flex items-center justify-between mb-3">
                <h4 class="text-sm font-bold text-gray-900 dark:text-white m-0">Recent Reviews</h4>
                <a href="reviews.php" class="text-xs font-semibold text-blue-600 no-underline">View All &rarr;</a>
            </div>
            <?php if ($recentReviews->num_rows > 0): ?>
                <div class="flex flex-col gap-3">
                    <?php while ($review = $recentReviews->fetch_assoc()): ?>
                        <div class="dh-card fade-in p-4">
                            <div class="flex items-center gap-2.5 mb-2">
                                <img src="<?= htmlspecialchars(get_profile_image($review['reviewer_image'])) ?>" class="w-9 h-9 rounded-[10px] object-cover border-2 border-gray-100 dark:border-slate-600" alt="">
                                <div>
                                    <p class="text-xs font-semibold text-gray-900 dark:text-white m-0"><?= htmlspecialchars($review['reviewer_name']) ?></p>
                                    <div class="flex gap-0.5 mt-0.5">
                                        <?php for ($i = 1; $i <= 5; $i++): ?>
                                            <i class="fas fa-star text-[10px]" style="color:<?= $i <= $review['rating'] ? '#f59e0b' : '#e2e8f0' ?>;"></i>
                                        <?php endfor; ?>
                                    </div>
                                </div>
                                <span class="ml-auto text-[11px] text-slate-400"><?= time_ago($review['created_at']) ?></span>
                            </div>
                            <p class="text-xs text-slate-500 dark:text-slate-400 m-0 line-clamp-2"><?= htmlspecialchars($review['comment'] ?? 'No comment') ?></p>
                        </div>
                    <?php endwhile; ?>
                </div>
            <?php else: ?>
                <div class="dh-card p-5 text-center"><p class="text-slate-400 text-sm">No reviews yet</p></div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Two Column: Messages + Performance -->
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
        <!-- Recent Messages -->
        <div class="dh-card fade-in p-6">
            <div class="flex items-center justify-between mb-4">
                <h4 class="text-sm font-bold text-gray-900 dark:text-white m-0">Recent Messages</h4>
                <a href="messages.php" class="text-xs font-semibold text-blue-600 no-underline">Open Chat &rarr;</a>
            </div>
            <?php if ($recentMessages->num_rows > 0): ?>
                <div class="flex flex-col gap-2.5">
                    <?php while ($msg = $recentMessages->fetch_assoc()): ?>
                        <a href="messages.php" class="flex items-center gap-3 p-3 rounded-xl no-underline text-inherit hover:bg-gray-50 dark:hover:bg-slate-700/50 transition">
                            <img src="<?= htmlspecialchars(get_profile_image($msg['sender_image'])) ?>" class="w-10 h-10 rounded-[10px] object-cover border-2 border-gray-100 dark:border-slate-600 flex-shrink-0" alt="">
                            <div class="flex-1 min-w-0">
                                <p class="text-[13px] font-semibold text-gray-900 dark:text-white m-0"><?= htmlspecialchars($msg['sender_name']) ?></p>
                                <p class="text-xs text-slate-400 m-0 mt-0.5 truncate"><?= htmlspecialchars($msg['message_text']) ?></p>
                            </div>
                            <span class="text-[11px] text-slate-400 flex-shrink-0 whitespace-nowrap"><?= time_ago($msg['created_at']) ?></span>
                        </a>
                    <?php endwhile; ?>
                </div>
            <?php else: ?>
                <div class="text-center py-5"><p class="text-slate-400 text-sm">No messages yet</p></div>
            <?php endif; ?>
        </div>

        <!-- Profile Performance -->
        <div class="dh-card fade-in p-6">
            <h4 class="text-sm font-bold text-gray-900 dark:text-white m-0 mb-4">Profile Performance</h4>
            <div class="grid grid-cols-2 gap-3">
                <div class="p-4 rounded-[14px] bg-emerald-50 dark:bg-emerald-900/20 text-center">
                    <i class="fas fa-trophy text-emerald-600 text-xl mb-2 block"></i>
                    <p class="text-[22px] font-extrabold text-gray-900 dark:text-white m-0"><?= $jobSuccessScore ?>%</p>
                    <p class="text-[11px] text-slate-400 mt-1 m-0">Job Success</p>
                </div>
                <div class="p-4 rounded-[14px] bg-blue-50 dark:bg-blue-900/20 text-center">
                    <i class="fas fa-eye text-blue-600 text-xl mb-2 block"></i>
                    <p class="text-[22px] font-extrabold text-gray-900 dark:text-white m-0"><?= number_format($profileViews) ?></p>
                    <p class="text-[11px] text-slate-400 mt-1 m-0">Profile Views</p>
                </div>
                <div class="p-4 rounded-[14px] bg-purple-50 dark:bg-purple-900/20 text-center">
                    <i class="fas fa-paper-plane text-purple-600 text-xl mb-2 block"></i>
                    <p class="text-[22px] font-extrabold text-gray-900 dark:text-white m-0"><?= number_format($totalProposals) ?></p>
                    <p class="text-[11px] text-slate-400 mt-1 m-0">Proposals Sent</p>
                </div>
                <div class="p-4 rounded-[14px] bg-orange-50 dark:bg-orange-900/20 text-center">
                    <i class="fas fa-bolt text-orange-600 text-xl mb-2 block"></i>
                    <p class="text-[22px] font-extrabold text-gray-900 dark:text-white m-0"><?= $responseRate ?>%</p>
                    <p class="text-[11px] text-slate-400 mt-1 m-0">Response Rate</p>
                </div>
            </div>
        </div>
    </div>

    <!-- Quick Actions -->
    <div>
        <h3 class="text-base font-bold text-gray-900 dark:text-white m-0 mb-4">Quick Actions</h3>
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
            <a href="browse_jobs.php" class="qa-card fade-in no-underline">
                <div class="w-11 h-11 rounded-xl flex items-center justify-center bg-blue-50 dark:bg-blue-900/30 text-blue-600"><i class="fas fa-search"></i></div>
                <h5 class="text-sm font-bold text-gray-900 dark:text-white mt-3 mb-1">Browse Jobs</h5>
                <p class="text-xs text-slate-400 m-0">Find new opportunities</p>
            </a>
            <a href="proposals.php" class="qa-card fade-in no-underline">
                <div class="w-11 h-11 rounded-xl flex items-center justify-center bg-emerald-50 dark:bg-emerald-900/30 text-emerald-600"><i class="fas fa-file-alt"></i></div>
                <h5 class="text-sm font-bold text-gray-900 dark:text-white mt-3 mb-1">My Proposals</h5>
                <p class="text-xs text-slate-400 m-0">Track submissions</p>
            </a>
            <a href="earnings.php" class="qa-card fade-in no-underline">
                <div class="w-11 h-11 rounded-xl flex items-center justify-center bg-purple-50 dark:bg-purple-900/30 text-purple-600"><i class="fas fa-wallet"></i></div>
                <h5 class="text-sm font-bold text-gray-900 dark:text-white mt-3 mb-1">Withdraw Earnings</h5>
                <p class="text-xs text-slate-400 m-0">Cash out balance</p>
            </a>
            <a href="profile.php" class="qa-card fade-in no-underline">
                <div class="w-11 h-11 rounded-xl flex items-center justify-center bg-orange-50 dark:bg-orange-900/30 text-orange-600"><i class="fas fa-user-pen"></i></div>
                <h5 class="text-sm font-bold text-gray-900 dark:text-white mt-3 mb-1">Edit Profile</h5>
                <p class="text-xs text-slate-400 m-0">Update info</p>
            </a>
        </div>
    </div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const ctx = document.getElementById('earningsChart');
    if (!ctx) return;
    const isDark = document.documentElement.classList.contains('dark');
    const gridColor = isDark ? 'rgba(148,163,184,0.1)' : 'rgba(148,163,184,0.15)';
    const textColor = isDark ? '#94a3b8' : '#64748b';
    new Chart(ctx, {
        type: 'line',
        data: {
            labels: <?= json_encode($earningsLabels) ?>,
            datasets: [{
                label: 'Earnings',
                data: <?= json_encode($earningsData) ?>,
                borderColor: '#059669',
                backgroundColor: 'rgba(5,150,105,0.06)',
                borderWidth: 2.5,
                fill: true,
                tension: 0.4,
                pointBackgroundColor: '#059669',
                pointBorderColor: '#fff',
                pointBorderWidth: 2,
                pointRadius: 4,
                pointHoverRadius: 6
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: { legend: { display: false } },
            scales: {
                x: { grid: { color: gridColor }, ticks: { color: textColor, font: { size: 11 } } },
                y: { grid: { color: gridColor }, ticks: { color: textColor, font: { size: 11 }, callback: v => '$' + v.toLocaleString() }, beginAtZero: true }
            }
        }
    });
});
</script>
<?php require_once __DIR__ . '/../components/layout_end.php'; ?>
