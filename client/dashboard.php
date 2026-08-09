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

<main class="min-h-screen">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8 sm:py-12">

        <!-- ═══ HEADER ═══════════════════════════════════════════════ -->
        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4 mb-8">
            <div>
                <h1 class="text-2xl font-semibold text-gray-900 tracking-tight">Welcome back, <?= htmlspecialchars($clientFirst) ?></h1>
                <p class="text-gray-500 text-sm mt-1">Here's what's happening with your projects today.</p>
            </div>
            <a href="post_job.php" class="inline-flex items-center justify-center gap-2 bg-indigo-600 hover:bg-indigo-700 text-white text-sm font-medium px-5 py-2.5 rounded-lg transition-colors shrink-0">
                <i data-lucide="plus" class="w-4 h-4"></i> Post New Job
            </a>
        </div>

        <!-- ═══ STATS GRID ══════════════════════════════════════════ -->
        <div class="grid grid-cols-2 lg:grid-cols-4 gap-4 mb-8">

            <!-- Open -->
            <a href="my_jobs.php?status=open" class="group bg-white rounded-xl border border-gray-200 p-5 no-underline transition-all hover:shadow-sm">
                <div class="flex items-center justify-between mb-3">
                    <div class="w-9 h-9 rounded-lg bg-emerald-50 flex items-center justify-center">
                        <i data-lucide="circle-dot" class="w-4 h-4 text-emerald-600"></i>
                    </div>
                    <!-- <span class="w-2 h-2 rounded-full bg-emerald-400"></span> -->
                </div>
                <p class="text-2xl font-bold text-gray-900 tracking-tight m-0"><?= $openJobs ?></p>
                <p class="text-xs text-gray-500 mt-1 m-0">active listings</p>
            </a>

            <!-- In Progress -->
            <a href="my_jobs.php?status=in_progress" class="group bg-white rounded-xl border border-gray-200 p-5 no-underline transition-all hover:shadow-sm">
                <div class="flex items-center justify-between mb-3">
                    <div class="w-9 h-9 rounded-lg bg-amber-50 flex items-center justify-center">
                        <i data-lucide="loader" class="w-4 h-4 text-amber-600"></i>
                    </div>
                    <!-- <span class="w-2 h-2 rounded-full bg-amber-400"></span> -->
                </div>
                <p class="text-2xl font-bold text-gray-900 tracking-tight m-0"><?= $inProgressJobs ?></p>
                <p class="text-xs text-gray-500 mt-1 m-0">running now</p>
            </a>

            <!-- Completed -->
            <a href="my_jobs.php?status=completed" class="group bg-white rounded-xl border border-gray-200 p-5 no-underline transition-all hover:shadow-sm">
                <div class="flex items-center justify-between mb-3">
                    <div class="w-9 h-9 rounded-lg bg-blue-50 flex items-center justify-center">
                        <i data-lucide="check-circle-2" class="w-4 h-4 text-blue-600"></i>
                    </div>
                    <!-- <span class="w-2 h-2 rounded-full bg-blue-400"></span> -->
                </div>
                <p class="text-2xl font-bold text-gray-900 tracking-tight m-0"><?= $completedJobs ?></p>
                <p class="text-xs text-gray-500 mt-1 m-0">total finished</p>
            </a>

            <!-- Spending -->
            <div class="bg-white rounded-xl border border-gray-200 p-5 transition-all hover:shadow-sm">
                <div class="flex items-center justify-between mb-3">
                    <div class="w-9 h-9 rounded-lg bg-rose-50 flex items-center justify-center">
                        <i data-lucide="wallet" class="w-4 h-4 text-rose-600"></i>
                    </div>
                    <!-- <span class="w-2 h-2 rounded-full bg-rose-400"></span> -->
                </div>
                <p class="text-2xl font-bold text-gray-900 tracking-tight m-0"><?= format_currency($totalSpending) ?></p>
                <p class="text-xs text-gray-500 mt-1 m-0">total spent</p>
            </div>

        </div>

        <!-- ═══ ACTIVE JOBS ═════════════════════════════════════════ -->
        <div class="bg-white rounded-xl border border-gray-200 overflow-hidden mb-8">

            <!-- Tabs + Header -->
            <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4 px-6 py-4 border-b border-gray-100">
                <!-- Underline Tabs -->
                <div class="flex items-center gap-6 overflow-x-auto -mb-px">
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
                        <button onclick="filterJobs('<?= $tab['status'] ?>')" class="filter-tab <?= $tab['active'] ? 'active' : '' ?> inline-flex items-center gap-1.5 pb-3 text-sm font-medium whitespace-nowrap border-b-2 transition-colors <?= $tab['active'] ? 'border-indigo-600 text-indigo-600' : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300' ?>">
                            <?= $tab['label'] ?>
                            <span class="inline-flex items-center justify-center min-w-[18px] h-[18px] px-1 rounded-full text-[10px] font-semibold <?= $tab['active'] ? 'bg-indigo-100 text-indigo-700' : 'bg-gray-100 text-gray-500' ?>"><?= $tab['count'] ?></span>
                        </button>
                    <?php endforeach; ?>
                </div>
                <a href="my_jobs.php" class="inline-flex items-center gap-1 text-sm font-medium text-indigo-600 no-underline hover:text-indigo-700 transition-colors shrink-0">
                    View All Jobs <i data-lucide="arrow-right" class="w-4 h-4"></i>
                </a>
            </div>

            <!-- Job List -->
            <?php if ($recentJobs->num_rows > 0): ?>
                <?php
                while ($job = $recentJobs->fetch_assoc()):
                    $status = $job['status'] ?? 'open';
                    $statusLabel = ucfirst(str_replace('_', ' ', $status));

                    $badgeClass = match ($status) {
                        'open' => 'bg-emerald-50 text-emerald-700',
                        'in_progress' => 'bg-amber-50 text-amber-700',
                        'completed' => 'bg-blue-50 text-blue-700',
                        'cancelled' => 'bg-rose-50 text-rose-700',
                        default => 'bg-gray-100 text-gray-600',
                    };

                    $iconBg = match ($status) {
                        'open' => 'bg-emerald-50',
                        'in_progress' => 'bg-amber-50',
                        'completed' => 'bg-blue-50',
                        default => 'bg-gray-50',
                    };

                    $iconColor = match ($status) {
                        'open' => 'text-emerald-600',
                        'in_progress' => 'text-amber-600',
                        'completed' => 'text-blue-600',
                        default => 'text-gray-400',
                    };

                    $icon = match ($status) {
                        'open' => 'circle-dot',
                        'in_progress' => 'loader',
                        'completed' => 'check-circle-2',
                        default => 'briefcase',
                    };
                    ?>
                    <a href="job_detail.php?id=<?= (int) $job['id'] ?>" class="job-table-row group flex items-center gap-4 px-6 py-4 border-b border-gray-50 last:border-b-0 no-underline hover:bg-gray-50 transition-colors">
                        <div class="w-10 h-10 rounded-lg <?= $iconBg ?> flex items-center justify-center shrink-0">
                            <i data-lucide="<?= $icon ?>" class="w-4 h-4 <?= $iconColor ?>"></i>
                        </div>
                        <div class="flex-1 min-w-0">
                            <p class="text-sm font-medium text-gray-900 m-0 truncate group-hover:text-indigo-600 transition-colors"><?= decode_over_encoded($job['title']) ?></p>
                            <div class="flex items-center gap-2 mt-0.5">
                                <span class="text-xs text-gray-400">Client Job</span>
                                <span class="w-1 h-1 rounded-full bg-gray-300"></span>
                                <span class="text-xs text-gray-400"><?= date('M d, Y', strtotime($job['created_at'])) ?></span>
                            </div>
                        </div>
                        <div class="hidden sm:block text-right mr-2">
                            <p class="text-sm font-semibold text-gray-900 m-0"><?= format_currency((float) $job['budget']) ?></p>
                        </div>
                        <span class="inline-flex items-center px-2.5 py-1 rounded-full text-xs font-medium <?= $badgeClass ?>"><?= $statusLabel ?></span>
                        <i data-lucide="chevron-right" class="w-4 h-4 text-gray-300 shrink-0 group-hover:text-indigo-500 group-hover:translate-x-0.5 transition-all"></i>
                    </a>
                <?php endwhile; ?>
            <?php else: ?>
                <div class="text-center py-16 px-6">
                    <div class="w-12 h-12 rounded-xl bg-gray-100 flex items-center justify-center mx-auto mb-4">
                        <i data-lucide="briefcase" class="w-6 h-6 text-gray-300"></i>
                    </div>
                    <p class="text-gray-600 text-sm font-medium m-0 mb-1">No jobs posted yet</p>
                    <p class="text-gray-400 text-xs m-0 mb-5">Post your first job to start hiring top talent</p>
                    <a href="post_job.php" class="inline-flex items-center gap-2 bg-indigo-600 hover:bg-indigo-700 text-white text-sm font-medium px-5 py-2.5 rounded-lg no-underline transition-colors">
                        <i data-lucide="plus" class="w-4 h-4"></i> Post Your First Job
                    </a>
                </div>
            <?php endif; ?>

        </div>

        <!-- ═══ QUICK ACTIONS ════════════════════════════════════════ -->
        <h3 class="text-sm font-semibold text-gray-900 uppercase tracking-wider mb-4">Quick Actions</h3>
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">

            <!-- Post Job -->
            <a href="post_job.php" class="group bg-white rounded-xl border border-gray-200 p-5 no-underline transition-all hover:bg-gray-50 hover:shadow-sm">
                <div class="w-10 h-10 rounded-lg bg-indigo-50 flex items-center justify-center mb-3 group-hover:bg-indigo-100 transition-colors">
                    <i data-lucide="plus" class="w-5 h-5 text-indigo-600"></i>
                </div>
                <p class="text-sm font-semibold text-gray-900 m-0">Post Job</p>
                <p class="text-xs text-gray-500 mt-0.5 m-0">Hire top talent</p>
            </a>

            <!-- Wallet -->
            <a href="wallet.php" class="group bg-white rounded-xl border border-gray-200 p-5 no-underline transition-all hover:bg-gray-50 hover:shadow-sm">
                <div class="w-10 h-10 rounded-lg bg-emerald-50 flex items-center justify-center mb-3 group-hover:bg-emerald-100 transition-colors">
                    <i data-lucide="wallet" class="w-5 h-5 text-emerald-600"></i>
                </div>
                <p class="text-sm font-semibold text-gray-900 m-0">Wallet</p>
                <p class="text-xs text-gray-500 mt-0.5 m-0"><?= format_currency($walletBalance) ?></p>
            </a>

            <!-- Proposals -->
            <a href="proposals.php" class="group bg-white rounded-xl border border-gray-200 p-5 no-underline transition-all hover:bg-gray-50 hover:shadow-sm">
                <div class="w-10 h-10 rounded-lg bg-violet-50 flex items-center justify-center mb-3 group-hover:bg-violet-100 transition-colors">
                    <i data-lucide="file-text" class="w-5 h-5 text-violet-600"></i>
                </div>
                <p class="text-sm font-semibold text-gray-900 m-0">Proposals</p>
                <p class="text-xs text-gray-500 mt-0.5 m-0"><?= $newProposals ?> pending</p>
            </a>

            <!-- Find Talent -->
            <a href="recommended_freelancers.php" class="group bg-white rounded-xl border border-gray-200 p-5 no-underline transition-all hover:bg-gray-50 hover:shadow-sm">
                <div class="w-10 h-10 rounded-lg bg-cyan-50 flex items-center justify-center mb-3 group-hover:bg-cyan-100 transition-colors">
                    <i data-lucide="sparkles" class="w-5 h-5 text-cyan-600"></i>
                </div>
                <p class="text-sm font-semibold text-gray-900 m-0">Find Talent</p>
                <p class="text-xs text-gray-500 mt-0.5 m-0">AI-powered matches</p>
            </a>

        </div>

    </div>
</main>

<script>
function filterJobs(status) {
    document.querySelectorAll('.filter-tab').forEach(tab => {
        tab.classList.remove('active', 'border-indigo-600', 'text-indigo-600');
        tab.classList.add('border-transparent', 'text-gray-500');
    });
    event.currentTarget.classList.add('active', 'border-indigo-600', 'text-indigo-600');
    event.currentTarget.classList.remove('border-transparent', 'text-gray-500');

    if (status === 'all') {
        document.querySelectorAll('.job-table-row').forEach(row => row.style.display = '');
    } else if (status === 'proposals') {
        window.location.href = 'proposals.php';
        return;
    } else {
        document.querySelectorAll('.job-table-row').forEach(row => {
            const statusEl = row.querySelector('[class*="rounded-full"][class*="font-medium"]');
            if (statusEl) {
                const rowStatus = statusEl.textContent.trim().toLowerCase().replace(' ', '_');
                row.style.display = rowStatus === status ? '' : 'none';
            }
        });
    }
}
</script>
<?php require_once __DIR__ . '/../includes/client_footer.php'; ?>