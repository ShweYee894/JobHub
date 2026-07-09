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
$uStmt = $conn->prepare('SELECT name, profile_image, wallet_balance FROM users WHERE id = ?');
$uStmt->bind_param('i', $userId);
$uStmt->execute();
$user = $uStmt->get_result()->fetch_assoc();
$uStmt->close();

$clientName = $user['name'] ?? 'Client';
$clientFirst = explode(' ', $clientName)[0];
$clientAvatar = get_profile_image($user['profile_image'] ?? null);
$walletBalance = (float) ($user['wallet_balance'] ?? 0);

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
    JOIN users u ON p.freelancer_id = u.id
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
    JOIN users u ON c.freelancer_id = u.id
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

$pageTitle = 'Client Dashboard';
$pageSubtitle = 'Welcome back, ' . htmlspecialchars($clientFirst) . " — here's your overview";
$activePage = 'dashboard';
$user = ['name' => $clientName, 'profile_image' => $user['profile_image'] ?? null];
$unreadCount = $unreadMessages;
$profileLink = 'profile.php';
require_once __DIR__ . '/../includes/client_topbar.php';
?>

<?php display_flash('success');
display_flash('error');
display_flash('info'); ?>
<?php $conn->close(); ?>
<main class="max-w-7xl mx-auto px-4 sm:px-6 py-8">
    <!-- Page Header -->
    <div class="flex items-center justify-between mb-6">
        <div>
            <h2 class="text-2xl font-extrabold text-slate-800 dark:text-white m-0">My Jobs</h2>
            <p class="text-sm text-slate-400 dark:text-slate-500 mt-1 m-0">General &rsaquo; All Jobs</p>
        </div>
        <a href="post_job.php" class="inline-flex items-center gap-2 bg-gradient-to-r from-orange-500 to-amber-500 hover:from-orange-600 hover:to-amber-600 text-white text-sm font-semibold px-5 py-2.5 rounded-xl no-underline shadow-md hover:shadow-lg transition-all">
            <i class="fas fa-plus text-xs"></i> Create Job
        </a>
    </div>

    <!-- Stats Cards - Invoice Style -->
    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4 mb-6">
        <!-- Open Jobs -->
        <a href="my_jobs.php?status=open" class="stat-invoice fade-in block rounded-2xl bg-gradient-to-br from-emerald-50 to-emerald-100/60 dark:from-emerald-900/25 dark:to-emerald-800/15 border border-emerald-200/60 dark:border-emerald-700/30 p-4 no-underline transition-all hover:shadow-md hover:-translate-y-0.5">
            <div class="flex items-center gap-3">
                <div class="w-11 h-11 rounded-full bg-emerald-500 flex items-center justify-center shrink-0">
                    <i class="fas fa-check text-white text-sm"></i>
                </div>
                <div class="flex-1 min-w-0">
                    <p class="text-xs font-semibold text-emerald-600 dark:text-emerald-400 m-0 uppercase tracking-wide">Open</p>
                    <div class="flex items-baseline gap-2 mt-0.5">
                        <span class="text-2xl font-extrabold text-emerald-700 dark:text-emerald-300"><?= $openJobs ?></span>
                        <span class="text-[11px] text-emerald-500/70 dark:text-emerald-400/50">Jobs</span>
                    </div>
                </div>
            </div>
        </a>

        <!-- In Progress -->
        <a href="my_jobs.php?status=in_progress" class="stat-invoice fade-in block rounded-2xl bg-gradient-to-br from-amber-50 to-amber-100/60 dark:from-amber-900/25 dark:to-amber-800/15 border border-amber-200/60 dark:border-amber-700/30 p-4 no-underline transition-all hover:shadow-md hover:-translate-y-0.5">
            <div class="flex items-center gap-3">
                <div class="w-11 h-11 rounded-full bg-amber-500 flex items-center justify-center shrink-0">
                    <i class="fas fa-clock text-white text-sm"></i>
                </div>
                <div class="flex-1 min-w-0">
                    <p class="text-xs font-semibold text-amber-600 dark:text-amber-400 m-0 uppercase tracking-wide">In Progress</p>
                    <div class="flex items-baseline gap-2 mt-0.5">
                        <span class="text-2xl font-extrabold text-amber-700 dark:text-amber-300"><?= $inProgressJobs ?></span>
                        <span class="text-[11px] text-amber-500/70 dark:text-amber-400/50">Jobs</span>
                    </div>
                </div>
            </div>
        </a>

        <!-- Completed -->
        <a href="my_jobs.php?status=completed" class="stat-invoice fade-in block rounded-2xl bg-gradient-to-br from-rose-50 to-rose-100/60 dark:from-rose-900/25 dark:to-rose-800/15 border border-rose-200/60 dark:border-rose-700/30 p-4 no-underline transition-all hover:shadow-md hover:-translate-y-0.5">
            <div class="flex items-center gap-3">
                <div class="w-11 h-11 rounded-full bg-rose-500 flex items-center justify-center shrink-0">
                    <i class="fas fa-bell text-white text-sm"></i>
                </div>
                <div class="flex-1 min-w-0">
                    <p class="text-xs font-semibold text-rose-600 dark:text-rose-400 m-0 uppercase tracking-wide">Completed</p>
                    <div class="flex items-baseline gap-2 mt-0.5">
                        <span class="text-2xl font-extrabold text-rose-700 dark:text-rose-300"><?= $completedJobs ?></span>
                        <span class="text-[11px] text-rose-500/70 dark:text-rose-400/50">Jobs</span>
                    </div>
                </div>
            </div>
        </a>

        <!-- Total Value -->
        <div class="stat-invoice fade-in rounded-2xl bg-gradient-to-br from-blue-50 to-blue-100/60 dark:from-blue-900/25 dark:to-blue-800/15 border border-blue-200/60 dark:border-blue-700/30 p-4 transition-all hover:shadow-md hover:-translate-y-0.5">
            <div class="flex items-center gap-3">
                <div class="w-11 h-11 rounded-full bg-blue-500 flex items-center justify-center shrink-0">
                    <i class="fas fa-file-alt text-white text-sm"></i>
                </div>
                <div class="flex-1 min-w-0">
                    <p class="text-xs font-semibold text-blue-600 dark:text-blue-400 m-0 uppercase tracking-wide">Spending</p>
                    <div class="flex items-baseline gap-2 mt-0.5">
                        <span class="text-2xl font-extrabold text-blue-700 dark:text-blue-300"><?= format_currency($totalSpending) ?></span>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Filter Tabs -->
    <div class="fade-in mb-6">
        <div class="flex items-center gap-1 p-1 bg-slate-100 dark:bg-slate-800 rounded-xl overflow-x-auto">
            <?php
            $filterTabs = [
                ['label' => 'All', 'count' => $totalJobs, 'status' => 'all', 'active' => true],
                ['label' => 'Open', 'count' => $openJobs, 'status' => 'open', 'active' => false],
                ['label' => 'In Progress', 'count' => $inProgressJobs, 'status' => 'in_progress', 'active' => false],
                ['label' => 'Completed', 'count' => $completedJobs, 'status' => 'completed', 'active' => false],
                ['label' => 'Proposals', 'count' => $newProposals, 'status' => 'proposals', 'active' => false],
            ];
            foreach ($filterTabs as $tab):
            ?>
                <button onclick="filterJobs('<?= $tab['status'] ?>')" class="filter-tab <?= $tab['active'] ? 'active' : '' ?> flex items-center gap-2 px-4 py-2.5 rounded-lg text-sm font-semibold whitespace-nowrap transition-all <?= $tab['active'] ? 'bg-white dark:bg-slate-700 text-slate-900 dark:text-white shadow-sm' : 'text-slate-500 dark:text-slate-400 hover:text-slate-700 dark:hover:text-slate-300' ?>">
                    <?= $tab['label'] ?>
                    <span class="inline-flex items-center justify-center min-w-[22px] h-[22px] px-1.5 rounded-full text-[10px] font-bold <?= $tab['active'] ? 'bg-slate-900 dark:bg-white text-white dark:text-slate-900' : 'bg-slate-200 dark:bg-slate-600 text-slate-600 dark:text-slate-300' ?>"><?= $tab['count'] ?></span>
                </button>
            <?php endforeach; ?>
        </div>
    </div>

    <!-- Jobs Table - Invoice Style -->
    <div class="fade-in">
        <div class="bg-white dark:bg-slate-800 rounded-2xl border border-slate-100 dark:border-slate-700/50 overflow-hidden">
            <!-- Table Header -->
            <div class="hidden lg:grid lg:grid-cols-12 gap-4 px-6 py-3.5 bg-slate-50 dark:bg-slate-800/50 border-b border-slate-100 dark:border-slate-700/50">
                <div class="col-span-5 text-xs font-bold text-slate-400 dark:text-slate-500 uppercase tracking-wider">Job</div>
                <div class="col-span-2 text-xs font-bold text-slate-400 dark:text-slate-500 uppercase tracking-wider">Created</div>
                <div class="col-span-1 text-xs font-bold text-slate-400 dark:text-slate-500 uppercase tracking-wider">Budget</div>
                <div class="col-span-1 text-xs font-bold text-slate-400 dark:text-slate-500 uppercase tracking-wider text-center">Proposals</div>
                <div class="col-span-2 text-xs font-bold text-slate-400 dark:text-slate-500 uppercase tracking-wider text-right">Status</div>
            </div>

            <!-- Table Body -->
            <?php if ($recentJobs->num_rows > 0): ?>
                <?php while ($job = $recentJobs->fetch_assoc()):
                    $status = $job['status'] ?? 'open';
                    $statusLabel = ucfirst(str_replace('_', ' ', $status));
                ?>
                    <a href="job_detail.php?id=<?= (int) $job['id'] ?>" class="job-table-row grid grid-cols-1 lg:grid-cols-12 gap-2 lg:gap-4 px-6 py-4 border-b border-slate-50 dark:border-slate-700/30 no-underline hover:bg-slate-50/50 dark:hover:bg-slate-700/20 transition-colors">
                        <!-- Job Info -->
                        <div class="col-span-5 flex items-center gap-3">
                            <div class="w-10 h-10 rounded-xl bg-gradient-to-br from-blue-500 to-indigo-500 flex items-center justify-center shrink-0">
                                <i class="fas fa-briefcase text-white text-sm"></i>
                            </div>
                            <div class="min-w-0">
                                <p class="text-sm font-bold text-slate-900 dark:text-white m-0 truncate"><?= sanitize_string($job['title']) ?></p>
                                <p class="text-[11px] text-slate-400 dark:text-slate-500 m-0 mt-0.5">Client Job</p>
                            </div>
                        </div>
                        <!-- Created -->
                        <div class="col-span-2 flex items-center">
                            <span class="text-sm text-slate-500 dark:text-slate-400"><?= date('m/d/y', strtotime($job['created_at'])) ?></span>
                        </div>
                        <!-- Budget -->
                        <div class="col-span-1 flex items-center">
                            <span class="text-sm font-bold text-slate-900 dark:text-white"><?= format_currency((float) $job['budget']) ?></span>
                        </div>
                        <!-- Proposals -->
                        <div class="col-span-1 flex items-center justify-center">
                            <span class="text-sm text-slate-500 dark:text-slate-400">&mdash;</span>
                        </div>
                        <!-- Status -->
                        <div class="col-span-2 flex items-center justify-end">
                            <span class="inline-flex items-center px-3 py-1 rounded-lg text-xs font-bold <?= $jobStatusColors[$job['status']] ?? '' ?>"><?= $statusLabel ?></span>
                        </div>
                    </a>
                <?php endwhile; ?>
            <?php else: ?>
                <div class="text-center py-16 px-6">
                    <div class="w-16 h-16 rounded-full bg-slate-100 dark:bg-slate-700 flex items-center justify-center mx-auto mb-4">
                        <i class="fas fa-briefcase text-2xl text-slate-300 dark:text-slate-500"></i>
                    </div>
                    <p class="text-slate-500 dark:text-slate-400 text-sm m-0 mb-4">No jobs posted yet</p>
                    <a href="post_job.php" class="inline-flex items-center gap-2 bg-gradient-to-r from-orange-500 to-amber-500 text-white text-sm font-semibold px-5 py-2.5 rounded-xl no-underline">Post Your First Job</a>
                </div>
            <?php endif; ?>
        </div>

        <!-- Pagination -->
        <?php if ($recentJobs->num_rows > 0): ?>
            <div class="flex items-center justify-between mt-4 px-2">
                <span class="text-sm text-slate-400 dark:text-slate-500">Showing <?= $recentJobs->num_rows ?> of <?= $totalJobs ?> jobs</span>
                <a href="my_jobs.php" class="text-sm font-semibold text-blue-600 dark:text-blue-400 no-underline hover:underline">View All &rarr;</a>
            </div>
        <?php endif; ?>
    </div>

    <!-- Quick Actions - Modern Action Bar -->
    <div class="fade-in mt-8">
        <div class="flex items-center gap-3 mb-4">
            <div class="w-8 h-8 rounded-lg bg-gradient-to-br from-violet-500 to-purple-500 flex items-center justify-center">
                <i class="fas fa-bolt text-white text-xs"></i>
            </div>
            <h3 class="text-base font-bold text-slate-900 dark:text-white m-0">Quick Actions</h3>
        </div>
        <div class="grid grid-cols-2 sm:grid-cols-4 gap-3">
            <a href="post_job.php" class="action-bar-item group flex items-center gap-3 px-4 py-3.5 rounded-xl border border-slate-100 dark:border-slate-700/50 bg-white dark:bg-slate-800 no-underline hover:border-orange-300 dark:hover:border-orange-600 hover:bg-orange-50/50 dark:hover:bg-orange-900/10 transition-all">
                <div class="w-10 h-10 rounded-xl bg-gradient-to-br from-orange-400 to-amber-400 flex items-center justify-center shrink-0 group-hover:scale-110 transition-transform">
                    <i class="fas fa-plus text-white text-sm"></i>
                </div>
                <div>
                    <p class="text-sm font-bold text-slate-900 dark:text-white m-0">Post Job</p>
                    <p class="text-[11px] text-slate-400 dark:text-slate-500 m-0">Hire talent</p>
                </div>
            </a>
            <a href="wallet.php" class="action-bar-item group flex items-center gap-3 px-4 py-3.5 rounded-xl border border-slate-100 dark:border-slate-700/50 bg-white dark:bg-slate-800 no-underline hover:border-blue-300 dark:hover:border-blue-600 hover:bg-blue-50/50 dark:hover:bg-blue-900/10 transition-all">
                <div class="w-10 h-10 rounded-xl bg-gradient-to-br from-blue-400 to-cyan-400 flex items-center justify-center shrink-0 group-hover:scale-110 transition-transform">
                    <i class="fas fa-wallet text-white text-sm"></i>
                </div>
                <div>
                    <p class="text-sm font-bold text-slate-900 dark:text-white m-0">Wallet</p>
                    <p class="text-[11px] text-slate-400 dark:text-slate-500 m-0"><?= format_currency($walletBalance) ?></p>
                </div>
            </a>
            <a href="proposals.php" class="action-bar-item group flex items-center gap-3 px-4 py-3.5 rounded-xl border border-slate-100 dark:border-slate-700/50 bg-white dark:bg-slate-800 no-underline hover:border-purple-300 dark:hover:border-purple-600 hover:bg-purple-50/50 dark:hover:bg-purple-900/10 transition-all">
                <div class="w-10 h-10 rounded-xl bg-gradient-to-br from-purple-400 to-pink-400 flex items-center justify-center shrink-0 group-hover:scale-110 transition-transform">
                    <i class="fas fa-file-alt text-white text-sm"></i>
                </div>
                <div>
                    <p class="text-sm font-bold text-slate-900 dark:text-white m-0">Proposals</p>
                    <p class="text-[11px] text-slate-400 dark:text-slate-500 m-0"><?= $newProposals ?> pending</p>
                </div>
            </a>
            <a href="recommended_freelancers.php" class="action-bar-item group flex items-center gap-3 px-4 py-3.5 rounded-xl border border-slate-100 dark:border-slate-700/50 bg-white dark:bg-slate-800 no-underline hover:border-emerald-300 dark:hover:border-emerald-600 hover:bg-emerald-50/50 dark:hover:bg-emerald-900/10 transition-all">
                <div class="w-10 h-10 rounded-xl bg-gradient-to-br from-emerald-400 to-teal-400 flex items-center justify-center shrink-0 group-hover:scale-110 transition-transform">
                    <i class="fas fa-search text-white text-sm"></i>
                </div>
                <div>
                    <p class="text-sm font-bold text-slate-900 dark:text-white m-0">Find Talent</p>
                    <p class="text-[11px] text-slate-400 dark:text-slate-500 m-0">AI matches</p>
                </div>
            </a>
        </div>
    </div>

    <script>
    function filterJobs(status) {
        document.querySelectorAll('.filter-tab').forEach(tab => {
            tab.classList.remove('active');
            tab.classList.remove('bg-white', 'dark:bg-slate-700', 'text-slate-900', 'dark:text-white', 'shadow-sm');
            tab.classList.add('text-slate-500', 'dark:text-slate-400');
        });
        event.currentTarget.classList.add('active');
        event.currentTarget.classList.add('bg-white', 'dark:bg-slate-700', 'text-slate-900', 'dark:text-white', 'shadow-sm');
        event.currentTarget.classList.remove('text-slate-500', 'dark:text-slate-400');

        if (status === 'all') {
            document.querySelectorAll('.job-table-row').forEach(row => row.style.display = '');
        } else if (status === 'proposals') {
            window.location.href = 'proposals.php';
            return;
        } else {
            document.querySelectorAll('.job-table-row').forEach(row => {
                const statusEl = row.querySelector('[class*="rounded-lg"][class*="font-bold"]');
                if (statusEl) {
                    const rowStatus = statusEl.textContent.trim().toLowerCase().replace(' ', '_');
                    row.style.display = rowStatus === status ? '' : 'none';
                }
            });
        }
    }
    </script>
</main>
<?php require_once __DIR__ . '/../includes/client_footer.php'; ?>