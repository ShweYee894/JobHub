<?php

/** Admin Job Detail — Read-only view of a single job with proposals, client, skills, and contract info */
require_once __DIR__ . '/../auth/auth.php';
require_role('admin');
require_once __DIR__ . '/../config/db.php';

$currentPage = 'jobs';
$jobId = sanitize_int($_GET['id'] ?? 0);
if ($jobId <= 0) {
    set_flash('error', 'Invalid job ID.');
    redirect('jobs.php');
}

// ── Fetch job with client info ───────────────────────────────────────
$js = $conn->prepare('SELECT j.*,
                             cu.id AS client_user_id, cu.name AS client_name, cu.email AS client_email, cu.profile_image AS client_image, cu.wallet_balance,
                             cl.company_name, cl.company_website, cl.industry, cl.company_size
                      FROM jobs j
                      JOIN clients cl ON j.client_id = cl.client_id
                      JOIN users cu ON cl.client_id = cu.id
                      WHERE j.id = ?');
$js->bind_param('i', $jobId);
$js->execute();
$job = $js->get_result()->fetch_assoc();
$js->close();

$jobNotFound = !$job;

if ($jobNotFound) {
    // ── Admin nav (for 404 state) ──────────────────────────────────
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
        ['key' => 'clients', 'label' => 'Clients', 'url' => 'clients.php', 'icon' => 'user-check'],
        ['key' => 'jobs', 'label' => 'Jobs', 'url' => 'jobs.php', 'icon' => 'briefcase'],
        ['key' => 'categories', 'label' => 'Categories', 'url' => 'categories.php', 'icon' => 'folder-open'],
        ['key' => 'skills', 'label' => 'Skills', 'url' => 'skills.php', 'icon' => 'settings'],
        ['key' => 'payments', 'label' => 'Payments', 'url' => 'payments.php', 'icon' => 'credit-card'],
        ['key' => 'wallets', 'label' => 'Wallets', 'url' => 'wallets.php', 'icon' => 'wallet'],
        ['key' => 'contracts', 'label' => 'Contracts', 'url' => 'contracts.php', 'icon' => 'file-text'],
        ['key' => 'milestones', 'label' => 'Milestones', 'url' => 'milestones.php', 'icon' => 'list-checks'],
        ['key' => 'reviews', 'label' => 'Reviews', 'url' => 'reviews.php', 'icon' => 'star'],
        ['key' => 'disputes', 'label' => 'Disputes', 'url' => 'disputes.php', 'icon' => 'scale'],
        ['key' => 'fraud', 'label' => 'Fraud', 'url' => 'fraud_detection.php', 'icon' => 'shield'],
        ['key' => 'notifications', 'label' => 'Notifications', 'url' => 'notifications.php', 'icon' => 'bell'],
        ['key' => 'ai_monitor', 'label' => 'AI Monitor', 'url' => 'ai_monitor.php', 'icon' => 'brain'],
        ['key' => 'analytics', 'label' => 'Analytics', 'url' => 'analytics.php', 'icon' => 'chart-pie'],
        ['key' => 'settings', 'label' => 'Settings', 'url' => 'settings.php', 'icon' => 'settings'],
    ];
    $pageTitle = 'Job Not Found';
    $pageSubtitle = '404 Error';
    $activePage = 'jobs';
    $user = ['name' => $adminName, 'profile_image' => $_navUserRow['profile_image'] ?? null];
    $unreadCount = 0;
    $profileLink = 'profile.php';
    require_once __DIR__ . '/../components/layout_start.php';
    ?>

    <div class="flex items-center justify-between mb-2">
        <a href="jobs.php" class="inline-flex items-center gap-1.5 text-sm text-gray-500 dark:text-slate-400 hover:text-gray-700 dark:hover:text-slate-200 transition-colors">
            <i data-lucide="arrow-left" class="text-xs"></i> Back to Jobs
        </a>
    </div>

    <!-- 404 CARD -->
    <div class="bg-white dark:bg-slate-800 rounded-2xl border border-gray-100 dark:border-slate-700 shadow-sm overflow-hidden fade-in">
        <div class="h-24 bg-gradient-to-r from-red-400/60 via-orange-400/70 to-amber-400/60"></div>
        <div class="px-6 pb-8 text-center">
            <div class="w-20 h-20 rounded-2xl bg-white dark:bg-slate-800 border-4 border-white dark:border-slate-800 shadow-lg flex items-center justify-center mx-auto -mt-10">
                <i data-lucide="triangle-alert" class="text-3xl text-orange-400"></i>
            </div>
            <h2 class="text-2xl font-extrabold text-gray-900 dark:text-white mt-4">404</h2>
            <p class="text-sm font-semibold text-gray-500 dark:text-slate-400 mt-1">Job Not Found</p>
            <p class="text-xs text-gray-400 dark:text-slate-500 mt-3 max-w-md mx-auto">The job you are looking for may have been removed, deleted, or does not exist. Please check the job ID and try again.</p>
            <div class="flex items-center justify-center gap-3 mt-6">
                <a href="jobs.php" class="inline-flex items-center gap-2 px-5 py-2.5 bg-gradient-to-r from-emerald-700 to-emerald-500 hover:from-emerald-800 hover:to-emerald-600 text-white text-xs font-semibold rounded-xl shadow-lg shadow-green-500/25 transition-all">
                    <i data-lucide="briefcase" class="text-xs"></i> Browse Jobs
                </a>
                <a href="dashboard.php" class="inline-flex items-center gap-2 px-5 py-2.5 bg-gray-100 dark:bg-slate-700 hover:bg-gray-200 dark:hover:bg-slate-600 text-gray-600 dark:text-slate-300 text-xs font-semibold rounded-xl transition-all">
                    <i data-lucide="layout-grid" class="text-xs"></i> Dashboard
                </a>
            </div>
        </div>
    </div>

<?php
    require_once __DIR__ . '/../components/layout_end.php';
    exit;
}

// ── Fetch skills ─────────────────────────────────────────────────────
$ss = $conn->prepare('SELECT s.id, s.skill_name, s.category FROM skills s JOIN job_skills js ON s.id = js.skill_id WHERE js.job_id = ? ORDER BY s.category, s.skill_name');
$ss->bind_param('i', $jobId);
$ss->execute();
$skillsRes = $ss->get_result();
$jobSkills = [];
while ($sk = $skillsRes->fetch_assoc())
    $jobSkills[] = $sk;
$ss->close();

// ── Fetch proposals ──────────────────────────────────────────────────
$ps = $conn->prepare("SELECT p.id, p.proposal_text, p.amount, p.status, p.created_at,
                             u.id AS freelancer_user_id, u.name AS freelancer_name, u.email AS freelancer_email, u.profile_image AS freelancer_image,
                             fl.title AS freelancer_title, fl.hourly_rate, fl.years_of_experience
                      FROM proposals p
                      JOIN users u ON p.freelancer_id = u.id
                      LEFT JOIN freelancers fl ON u.id = fl.user_id
                      WHERE p.job_id = ?
                      ORDER BY CASE p.status WHEN 'pending' THEN 0 WHEN 'accepted' THEN 1 WHEN 'rejected' THEN 2 ELSE 3 END,
                               p.created_at DESC");
$ps->bind_param('i', $jobId);
$ps->execute();
$proposalsResult = $ps->get_result();
$proposals = [];
while ($pr = $proposalsResult->fetch_assoc())
    $proposals[] = $pr;
$ps->close();

// ── Fetch contract (if any) ──────────────────────────────────────────
$cs = $conn->prepare('SELECT c.id, c.total_budget, c.status, c.contract_type, c.created_at,
                             u_fl.name AS freelancer_name, u_fl.id AS freelancer_user_id
                      FROM contracts c
                      JOIN users u_fl ON c.freelancer_id = u_fl.id
                      WHERE c.job_id = ?
                      LIMIT 1');
$cs->bind_param('i', $jobId);
$cs->execute();
$contract = $cs->get_result()->fetch_assoc();
$cs->close();

// ── Fetch milestones & escrow for contract ──────────────────────────
$contractMilestones = [];
$escrowTotal = 0.0;
$releasedTotal = 0.0;
if ($contract) {
    $ms = $conn->prepare('SELECT id, title, amount, status FROM milestones WHERE contract_id = ? ORDER BY created_at ASC');
    $ms->bind_param('i', $contract['id']);
    $ms->execute();
    $msRes = $ms->get_result();
    while ($row = $msRes->fetch_assoc()) {
        $contractMilestones[] = $row;
        if (in_array($row['status'], ['funded_in_escrow', 'submitted']))
            $escrowTotal += (float) $row['amount'];
        if ($row['status'] === 'released')
            $releasedTotal += (float) $row['amount'];
    }
    $ms->close();
}

// ── Fetch reviews related to this job (via contract) ──────────────────
$reviews = [];
if ($contract) {
    $rv = $conn->prepare('SELECT r.id, r.rating, r.comment, r.created_at, u.name AS reviewer_name, u.profile_image AS reviewer_image
                          FROM reviews r JOIN users u ON r.reviewer_id = u.id
                          WHERE r.contract_id = ?
                          ORDER BY r.created_at DESC');
    $rv->bind_param('i', $contract['id']);
    $rv->execute();
    $rvRes = $rv->get_result();
    while ($row = $rvRes->fetch_assoc())
        $reviews[] = $row;
    $rv->close();
}

// ── Stats ────────────────────────────────────────────────────────────
$totalProposals = count($proposals);
$pendingProposals = count(array_filter($proposals, fn($p) => $p['status'] === 'pending'));
$acceptedProposals = count(array_filter($proposals, fn($p) => $p['status'] === 'accepted'));
$rejectedProposals = count(array_filter($proposals, fn($p) => $p['status'] === 'rejected'));

$bidAmounts = array_map(fn($p) => (float) $p['amount'], $proposals);
$bestBid = $bidAmounts ? min($bidAmounts) : 0;
$highestBid = $bidAmounts ? max($bidAmounts) : 0;
$averageBid = $bidAmounts ? array_sum($bidAmounts) / count($bidAmounts) : 0;

// ── Badge maps ───────────────────────────────────────────────────────
$jobStatusColors = [
    'open' => 'bg-emerald-50 text-emerald-700 dark:bg-emerald-900/20 dark:text-emerald-400',
    'in_progress' => 'bg-blue-50 text-blue-700 dark:bg-blue-900/20 dark:text-blue-400',
    'completed' => 'bg-gray-100 text-gray-600 dark:bg-slate-700 dark:text-slate-400',
    'cancelled' => 'bg-red-50 text-red-700 dark:bg-red-900/20 dark:text-red-400',
    'disputed' => 'bg-orange-50 text-orange-700 dark:bg-orange-900/20 dark:text-orange-400',
];
$typeLabels = ['hourly' => 'Hourly', 'fixed' => 'Fixed'];
$typeColors = [
    'hourly' => 'bg-violet-100 text-violet-700 dark:bg-violet-900/30 dark:text-violet-400',
    'fixed' => 'bg-cyan-100 text-cyan-700 dark:bg-cyan-900/30 dark:text-cyan-400',
];
$levelLabels = ['entry' => 'Entry', 'intermediate' => 'Intermediate', 'expert' => 'Expert'];
$levelColors = [
    'entry' => 'bg-teal-100 text-teal-700 dark:bg-teal-900/30 dark:text-teal-400',
    'intermediate' => 'bg-blue-100 text-blue-700 dark:bg-blue-900/30 dark:text-blue-400',
    'expert' => 'bg-purple-100 text-purple-700 dark:bg-purple-900/30 dark:text-purple-400',
];
$proposalStatusColors = [
    'pending' => 'bg-amber-100 text-amber-700 dark:bg-amber-900/30 dark:text-amber-400',
    'accepted' => 'bg-emerald-100 text-emerald-700 dark:bg-emerald-900/30 dark:text-emerald-400',
    'rejected' => 'bg-red-100 text-red-700 dark:bg-red-900/30 dark:text-red-400',
];
$contractStatusColors = [
    'active' => 'bg-emerald-100 text-emerald-700 dark:bg-emerald-900/30 dark:text-emerald-400',
    'completed' => 'bg-blue-100 text-blue-700 dark:bg-blue-900/30 dark:text-blue-400',
    'disputed' => 'bg-red-100 text-red-700 dark:bg-red-900/30 dark:text-red-400',
    'terminated' => 'bg-gray-100 text-gray-600 dark:bg-slate-700 dark:text-slate-400',
];

// ── Admin nav ────────────────────────────────────────────────────────
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
    ['key' => 'clients', 'label' => 'Clients', 'url' => 'clients.php', 'icon' => 'user-check'],
    ['key' => 'jobs', 'label' => 'Jobs', 'url' => 'jobs.php', 'icon' => 'briefcase'],
    ['key' => 'categories', 'label' => 'Categories', 'url' => 'categories.php', 'icon' => 'folder-open'],
    ['key' => 'skills', 'label' => 'Skills', 'url' => 'skills.php', 'icon' => 'settings'],
    ['key' => 'payments', 'label' => 'Payments', 'url' => 'payments.php', 'icon' => 'credit-card'],
    ['key' => 'wallets', 'label' => 'Wallets', 'url' => 'wallets.php', 'icon' => 'wallet'],
    ['key' => 'contracts', 'label' => 'Contracts', 'url' => 'contracts.php', 'icon' => 'file-text'],
    ['key' => 'milestones', 'label' => 'Milestones', 'url' => 'milestones.php', 'icon' => 'list-checks'],
    ['key' => 'reviews', 'label' => 'Reviews', 'url' => 'reviews.php', 'icon' => 'star'],
    ['key' => 'disputes', 'label' => 'Disputes', 'url' => 'disputes.php', 'icon' => 'scale'],
    ['key' => 'fraud', 'label' => 'Fraud', 'url' => 'fraud_detection.php', 'icon' => 'shield'],
    ['key' => 'notifications', 'label' => 'Notifications', 'url' => 'notifications.php', 'icon' => 'bell'],
    ['key' => 'ai_monitor', 'label' => 'AI Monitor', 'url' => 'ai_monitor.php', 'icon' => 'brain'],
    ['key' => 'analytics', 'label' => 'Analytics', 'url' => 'analytics.php', 'icon' => 'chart-pie'],
    ['key' => 'settings', 'label' => 'Settings', 'url' => 'settings.php', 'icon' => 'settings'],
];
$pageTitle = 'Job #' . $jobId . ' — ' . sanitize_string(mb_substr($job['title'], 0, 40));
$pageSubtitle = 'Job Details';
$activePage = 'jobs';
$user = ['name' => $adminName, 'profile_image' => $_navUserRow['profile_image'] ?? null];
$unreadCount = 0;
$profileLink = 'profile.php';
require_once __DIR__ . '/../components/layout_start.php';
?>

    <?php display_flash('success'); ?>
    <?php display_flash('error'); ?>

    <div class="bg-gray-50 dark:bg-slate-900 rounded-lg p-6">

    <!-- ═══ BACK LINK ══════════════════════════════════════════════════ -->
    <div class="flex items-center justify-between mb-6">
        <a href="jobs.php" class="inline-flex items-center gap-1.5 text-sm text-gray-500 dark:text-slate-400 hover:text-gray-700 dark:hover:text-slate-200 transition-colors">
            <i data-lucide="arrow-left" class="text-xs"></i> Back to Jobs
        </a>
    </div>

    <!-- ═══ MAIN GRID ═════════════════════════════════════════════════ -->
    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">

        <!-- ═══ LEFT COLUMN ══════════════════════════════════════════ -->
        <div class="lg:col-span-2 space-y-6">

            <!-- ═══ JOB HEADER ═══════════════════════════════════════ -->
            <div class="bg-white dark:bg-slate-800 border border-gray-200 dark:border-slate-700 rounded-lg shadow-sm mb-6 fade-in">
                <!-- Top: Title & Meta -->
                <div class="p-6">
                    <h2 class="text-2xl font-bold text-gray-900 dark:text-white mb-3"><i data-lucide="briefcase" class="text-violet-500 mr-2"></i><?= decode_over_encoded($job['title']) ?></h2>
                    <div class="flex flex-wrap items-center gap-2 mb-2">
                        <?php
                        $statusLabel = ucfirst(str_replace('_', ' ', $job['status']));
                        $statusBadgeClass = match ($job['status']) {
                            'open' => 'bg-green-50 text-green-700 border border-green-200 dark:bg-green-900/20 dark:text-green-400 dark:border-green-800',
                            'in_progress' => 'bg-blue-50 text-blue-700 border border-blue-200 dark:bg-blue-900/20 dark:text-blue-400 dark:border-blue-800',
                            'completed' => 'bg-gray-100 text-gray-600 border border-gray-200 dark:bg-slate-700 dark:text-slate-400 dark:border-slate-600',
                            'cancelled' => 'bg-red-50 text-red-700 border border-red-200 dark:bg-red-900/20 dark:text-red-400 dark:border-red-800',
                            'disputed' => 'bg-orange-50 text-orange-700 border border-orange-200 dark:bg-orange-900/20 dark:text-orange-400 dark:border-orange-800',
                            default => 'bg-gray-100 text-gray-600 border border-gray-200',
                        };
                        ?>
                        <span class="px-2.5 py-0.5 rounded-full text-xs font-medium <?= $statusBadgeClass ?>"><?= $statusLabel ?></span>
                        <span class="bg-gray-100 dark:bg-slate-700 text-gray-600 dark:text-slate-400 px-2.5 py-0.5 rounded-full text-xs font-medium"><?= $typeLabels[$job['job_type']] ?? ucfirst($job['job_type']) ?></span>
                        <span class="bg-gray-100 dark:bg-slate-700 text-gray-600 dark:text-slate-400 px-2.5 py-0.5 rounded-full text-xs font-medium"><?= $levelLabels[$job['experience_level']] ?? ucfirst($job['experience_level']) ?></span>
                        <?php if (!empty($job['is_featured'])): ?>
                        <span class="bg-yellow-50 dark:bg-yellow-900/20 text-yellow-700 dark:text-yellow-400 border border-yellow-200 dark:border-yellow-800 px-2.5 py-0.5 rounded-full text-xs font-medium flex items-center gap-1"><i data-lucide="star" class="text-xs"></i> Featured</span>
                        <?php endif; ?>
                        <?php if (!empty($job['is_archived'])): ?>
                        <span class="bg-gray-100 dark:bg-slate-700 text-gray-600 dark:text-slate-400 px-2.5 py-0.5 rounded-full text-xs font-medium flex items-center gap-1"><i data-lucide="archive" class="text-xs"></i> Archived</span>
                        <?php endif; ?>
                    </div>
                    <p class="text-sm text-gray-500 dark:text-slate-400 mb-6">Posted <?= date('M j, Y \a\t g:i A', strtotime($job['created_at'])) ?></p>
                </div>

                <!-- Divider -->
                <div class="border-t border-gray-100 dark:border-slate-700"></div>

                <!-- Bottom: Metrics Row -->
                <div class="grid grid-cols-2 md:grid-cols-4 divide-y md:divide-y-0 md:divide-x divide-gray-100 dark:divide-slate-700 bg-gray-50/50 dark:bg-slate-700/20 rounded-b-lg">
                    <div class="p-4 flex flex-col">
                        <span class="text-xs font-medium text-gray-500 dark:text-slate-400 uppercase tracking-wider mb-1">Budget</span>
                        <span class="text-xl font-semibold text-gray-900 dark:text-white"><?= format_currency((float) $job['budget']) ?></span>
                    </div>
                    <div class="p-4 flex flex-col">
                        <span class="text-xs font-medium text-gray-500 dark:text-slate-400 uppercase tracking-wider mb-1">Proposals</span>
                        <span class="text-xl font-semibold text-gray-900 dark:text-white"><?= $totalProposals ?></span>
                    </div>
                    <div class="p-4 flex flex-col">
                        <span class="text-xs font-medium text-gray-500 dark:text-slate-400 uppercase tracking-wider mb-1">Pending</span>
                        <span class="text-xl font-semibold text-gray-900 dark:text-white"><?= $pendingProposals ?></span>
                    </div>
                    <div class="p-4 flex flex-col">
                        <span class="text-xs font-medium text-gray-500 dark:text-slate-400 uppercase tracking-wider mb-1">Accepted</span>
                        <span class="text-xl font-semibold text-gray-900 dark:text-white"><?= $acceptedProposals ?></span>
                    </div>
                </div>
            </div>

    <!-- ═══ PROPOSAL SUMMARY ══════════════════════════════════════════ -->
    <div class="bg-white dark:bg-slate-800 border border-gray-200 dark:border-slate-700 rounded-lg shadow-sm p-6 mb-6 fade-in" style="animation-delay:.03s">
        <h3 class="text-base font-semibold text-gray-900 dark:text-white mb-4"><i data-lucide="bar-chart" class="text-violet-500 mr-2"></i>Proposal Summary</h3>
        <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
            <div class="border border-gray-100 dark:border-slate-700 rounded-lg p-3 bg-gray-50/30 dark:bg-slate-700/20">
                <p class="text-xs text-gray-500 dark:text-slate-400 uppercase mb-1">Total</p>
                <p class="text-lg font-semibold text-gray-900 dark:text-white"><?= $totalProposals ?></p>
            </div>
            <div class="border border-gray-100 dark:border-slate-700 rounded-lg p-3 bg-gray-50/30 dark:bg-slate-700/20">
                <p class="text-xs text-gray-500 dark:text-slate-400 uppercase mb-1">Pending</p>
                <p class="text-lg font-semibold text-gray-900 dark:text-white"><?= $pendingProposals ?></p>
            </div>
            <div class="border border-gray-100 dark:border-slate-700 rounded-lg p-3 bg-gray-50/30 dark:bg-slate-700/20">
                <p class="text-xs text-gray-500 dark:text-slate-400 uppercase mb-1">Accepted</p>
                <p class="text-lg font-semibold text-gray-900 dark:text-white"><?= $acceptedProposals ?></p>
            </div>
            <div class="border border-gray-100 dark:border-slate-700 rounded-lg p-3 bg-gray-50/30 dark:bg-slate-700/20">
                <p class="text-xs text-gray-500 dark:text-slate-400 uppercase mb-1">Rejected</p>
                <p class="text-lg font-semibold text-red-600 dark:text-red-400"><?= $rejectedProposals ?></p>
            </div>
            <div class="border border-gray-100 dark:border-slate-700 rounded-lg p-3 bg-gray-50/30 dark:bg-slate-700/20">
                <p class="text-xs text-gray-500 dark:text-slate-400 uppercase mb-1">Best Bid</p>
                <p class="text-lg font-semibold text-gray-900 dark:text-white"><?= $totalProposals > 0 ? format_currency($bestBid) : '—' ?></p>
            </div>
            <div class="border border-gray-100 dark:border-slate-700 rounded-lg p-3 bg-gray-50/30 dark:bg-slate-700/20">
                <p class="text-xs text-gray-500 dark:text-slate-400 uppercase mb-1">Highest Bid</p>
                <p class="text-lg font-semibold text-gray-900 dark:text-white"><?= $totalProposals > 0 ? format_currency($highestBid) : '—' ?></p>
            </div>
            <div class="border border-gray-100 dark:border-slate-700 rounded-lg p-3 bg-gray-50/30 dark:bg-slate-700/20">
                <p class="text-xs text-gray-500 dark:text-slate-400 uppercase mb-1">Avg Bid</p>
                <p class="text-lg font-semibold text-gray-900 dark:text-white"><?= $totalProposals > 0 ? format_currency($averageBid) : '—' ?></p>
            </div>
        </div>
    </div>

        <!-- ═══ JOB DETAILS + CLIENT ═════════════════════════════════════ -->
            <!-- Description & Skills -->
            <div class="bg-white dark:bg-slate-800 border border-gray-200 dark:border-slate-700 rounded-lg shadow-sm p-6 mb-6 fade-in" style="animation-delay:.05s">
                <h3 class="text-base font-semibold text-gray-900 dark:text-white mb-4"><i data-lucide="align-left" class="text-blue-500 mr-2"></i>Description</h3>
                <p class="text-sm text-gray-700 dark:text-slate-300 leading-relaxed mb-6 whitespace-pre-line"><?= nl2br(sanitize_string($job['description'])) ?></p>

                <?php if (!empty($jobSkills)): ?>
                <h3 class="text-base font-semibold text-gray-900 dark:text-white mb-4"><i data-lucide="tags" class="text-emerald-500 mr-2"></i>Required Skills</h3>
                <div class="flex flex-wrap gap-2">
                    <?php foreach ($jobSkills as $sk): ?>
                    <span class="inline-flex items-center gap-1.5 px-3 py-1 bg-white dark:bg-slate-800 border border-gray-200 dark:border-slate-600 text-gray-700 dark:text-slate-300 rounded-full text-sm font-medium">
                        <?= sanitize_string($sk['skill_name']) ?>
                        <span class="text-xs text-gray-400 dark:text-slate-500 font-normal">(<?= sanitize_string($sk['category']) ?>)</span>
                    </span>
                    <?php endforeach; ?>
                </div>
                <?php endif; ?>
            </div>

            <!-- ═══ RELATED PROPOSALS ═══════════════════════════════════ -->
            <div class="bg-white dark:bg-slate-800 border border-gray-200 dark:border-slate-700 rounded-lg shadow-sm fade-in" style="animation-delay:.14s">
                <div class="p-6">
                    <h3 class="text-base font-bold text-gray-900 dark:text-white mb-1"><i data-lucide="send" class="text-violet-500 mr-2"></i>Related Proposals (<?= $totalProposals ?>)</h3>
                </div>
                <div class="border-t border-gray-100 dark:border-slate-700"></div>
                <div class="p-6 pt-5">
                <?php if ($totalProposals > 0): ?>
                    <div class="overflow-x-auto">
                        <table class="w-full text-sm">
                            <thead>
                                <tr class="border-b border-gray-100 dark:border-slate-700">
                                    <th class="text-left pb-3 text-xs font-medium text-gray-500 dark:text-slate-400 uppercase tracking-wider">Freelancer</th>
                                    <th class="text-right pb-3 text-xs font-medium text-gray-500 dark:text-slate-400 uppercase tracking-wider">Bid</th>
                                    <th class="text-center pb-3 text-xs font-medium text-gray-500 dark:text-slate-400 uppercase tracking-wider">Status</th>
                                    <th class="text-right pb-3 text-xs font-medium text-gray-500 dark:text-slate-400 uppercase tracking-wider">Submitted</th>
                                    <th class="text-center pb-3 text-xs font-medium text-gray-500 dark:text-slate-400 uppercase tracking-wider">Action</th>
                                </tr>
                            </thead>
                            <tbody>
                            <?php foreach ($proposals as $p): ?>
                                <tr class="border-b border-gray-50 dark:border-slate-700/50 last:border-0 hover:bg-gray-50/50 dark:hover:bg-slate-700/30 transition-colors">
                                    <td class="py-3.5">
                                        <a href="user_detail.php?id=<?= (int) $p['freelancer_user_id'] ?>" class="flex items-center gap-3 no-underline text-inherit">
                                            <?php
                                            $_fi = $p['freelancer_image'] ?? '';
                                            $_fb = strtolower(basename($_fi));
                                            $_fv = $_fi !== '' && $_fi !== null && $_fb !== 'default.png' && $_fb !== 'profile.png';
                                            if ($_fv):
                                            ?>
                                            <img src="<?= sanitize_string(get_profile_image($_fi)) ?>" class="w-9 h-9 rounded-full object-cover border border-gray-100 dark:border-slate-600 flex-shrink-0">
                                            <?php else:
                                                $_fin = '';
                                                foreach (explode(' ', trim($p['freelancer_name'] ?? '')) as $_w) { if ($_w !== '') $_fin .= strtoupper($_w[0]); }
                                                $_fin = substr($_fin, 0, 2);
                                            ?>
                                            <div class="w-9 h-9 rounded-full bg-blue-100 text-blue-600 flex items-center justify-center font-semibold text-xs flex-shrink-0 border border-gray-100 dark:border-slate-600"><?= $_fin ?></div>
                                            <?php endif; ?>
                                            <div class="min-w-0">
                                                <p class="text-sm font-semibold text-gray-900 dark:text-white truncate"><?= sanitize_string($p['freelancer_name']) ?></p>
                                                <?php if ($p['freelancer_title']): ?>
                                                <p class="text-xs text-gray-400 dark:text-slate-500 truncate"><?= sanitize_string($p['freelancer_title']) ?></p>
                                                <?php endif; ?>
                                            </div>
                                        </a>
                                    </td>
                                    <td class="py-3.5 text-right">
                                        <span class="text-sm font-bold text-gray-900 dark:text-white"><?= format_currency((float) $p['amount']) ?></span>
                                        <?php if ($p['hourly_rate']): ?>
                                        <p class="text-xs text-gray-400 dark:text-slate-500">$<?= number_format((float) $p['hourly_rate'], 2) ?>/hr</p>
                                        <?php endif; ?>
                                    </td>
                                    <td class="py-3.5 text-center">
                                        <span class="inline-block px-2.5 py-1 rounded-full text-xs font-medium <?= $proposalStatusColors[$p['status']] ?? '' ?>"><?= ucfirst($p['status']) ?></span>
                                    </td>
                                    <td class="py-3.5 text-right text-gray-500 dark:text-slate-400 text-xs whitespace-nowrap"><?= time_ago($p['created_at']) ?></td>
                                    <td class="py-3.5 text-center">
                                        <button type="button" onclick="document.getElementById('proposalModal<?= $p['id'] ?>').classList.remove('hidden')" class="inline-flex items-center gap-1.5 px-3 py-1.5 bg-gray-50 dark:bg-slate-700 text-gray-600 dark:text-slate-400 text-xs font-medium rounded-lg border border-gray-100 dark:border-slate-600 hover:bg-gray-100 dark:hover:bg-slate-600 transition-colors">
                                            <i data-lucide="eye" class="text-xs"></i> View
                                        </button>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php else: ?>
                    <div class="text-center py-8">
                        <div class="w-14 h-14 rounded-lg bg-gray-100 dark:bg-slate-700 flex items-center justify-center mx-auto mb-3">
                            <i data-lucide="inbox" class="text-xl text-gray-300 dark:text-slate-500"></i>
                        </div>
                        <p class="text-gray-500 dark:text-slate-400 text-sm font-medium">No proposals yet</p>
                        <p class="text-gray-400 dark:text-slate-500 text-xs mt-1">Proposals from freelancers will appear here</p>
                    </div>
                <?php endif; ?>
                </div>
            </div>

        </div>

        <!-- ═══ RIGHT COLUMN ═════════════════════════════════════════ -->
        <div class="space-y-6">

            <!-- Client Information -->
            <div class="bg-white dark:bg-slate-800 border border-gray-200 dark:border-slate-700 rounded-lg shadow-sm p-6 mb-6 fade-in" style="animation-delay:.05s">
                <h3 class="text-sm font-semibold text-gray-900 dark:text-white uppercase tracking-wider mb-5">Client Information</h3>

                <!-- Client Profile -->
                <div class="flex items-center gap-4 mb-5">
                    <?php
                    $_ci = $job['client_image'] ?? '';
                    $_cb = strtolower(basename($_ci));
                    $_cv = $_ci !== '' && $_ci !== null && $_cb !== 'default.png' && $_cb !== 'profile.png';
                    if ($_cv):
                    ?>
                    <img src="<?= sanitize_string(get_profile_image($_ci)) ?>" class="w-12 h-12 rounded-full object-cover bg-gray-100 dark:bg-slate-700 border border-gray-200 dark:border-slate-600">
                    <?php else:
                        $_cin = '';
                        foreach (explode(' ', trim($job['client_name'] ?? '')) as $_w) { if ($_w !== '') $_cin .= strtoupper($_w[0]); }
                        $_cin = substr($_cin, 0, 2);
                    ?>
                    <div class="w-12 h-12 rounded-full bg-blue-100 text-blue-600 flex items-center justify-center font-semibold text-xs border border-gray-200 dark:border-slate-600"><?= $_cin ?></div>
                    <?php endif; ?>
                    <div class="flex flex-col">
                        <p class="text-base font-semibold text-gray-900 dark:text-white"><?= sanitize_string($job['client_name']) ?></p>
                        <p class="text-sm text-gray-500 dark:text-slate-400"><?= sanitize_string($job['client_email']) ?></p>
                    </div>
                </div>

                <!-- Divider -->
                <hr class="border-t border-gray-100 dark:border-slate-700 mb-2">

                <!-- Client Details List -->
                <div class="flex flex-col text-sm">
                    <?php if ($job['company_name']): ?>
                    <div class="flex justify-between items-start py-3 border-b border-gray-50 dark:border-slate-700/50 last:border-0">
                        <span class="text-gray-500 dark:text-slate-400 min-w-[120px]">Company</span>
                        <span class="text-gray-900 dark:text-white font-medium text-right ml-4"><?= sanitize_string($job['company_name']) ?></span>
                    </div>
                    <?php endif; ?>
                    <?php if ($job['industry']): ?>
                    <div class="flex justify-between items-start py-3 border-b border-gray-50 dark:border-slate-700/50 last:border-0">
                        <span class="text-gray-500 dark:text-slate-400 min-w-[120px]">Industry</span>
                        <span class="text-gray-900 dark:text-white font-medium text-right ml-4"><?= sanitize_string($job['industry']) ?></span>
                    </div>
                    <?php endif; ?>
                    <?php if ($job['company_size']): ?>
                    <div class="flex justify-between items-start py-3 border-b border-gray-50 dark:border-slate-700/50 last:border-0">
                        <span class="text-gray-500 dark:text-slate-400 min-w-[120px]">Company Size</span>
                        <span class="text-gray-900 dark:text-white font-medium text-right ml-4"><?= sanitize_string($job['company_size']) ?></span>
                    </div>
                    <?php endif; ?>
                    <?php if ($job['wallet_balance'] !== null): ?>
                    <div class="flex justify-between items-start py-3 border-b border-gray-50 dark:border-slate-700/50 last:border-0">
                        <span class="text-gray-500 dark:text-slate-400 min-w-[120px]">Wallet Balance</span>
                        <span class="font-semibold text-gray-900 dark:text-white text-right ml-4"><?= format_currency((float) $job['wallet_balance']) ?></span>
                    </div>
                    <?php endif; ?>
                    <?php if ($job['company_website']): ?>
                    <div class="flex justify-between items-start py-3 last:border-0">
                        <span class="text-gray-500 dark:text-slate-400 min-w-[120px]">Website</span>
                        <a href="<?= sanitize_string($job['company_website']) ?>" target="_blank" class="text-emerald-600 dark:text-emerald-400 hover:underline text-right ml-4 truncate max-w-[160px]"><?= sanitize_string($job['company_website']) ?></a>
                    </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Job Information -->
            <div class="bg-white dark:bg-slate-800 border border-gray-200 dark:border-slate-700 rounded-lg shadow-sm p-6 mb-6 fade-in" style="animation-delay:.08s">
                <h3 class="text-sm font-semibold text-gray-900 dark:text-white uppercase tracking-wider mb-4">Job Information</h3>
                <div class="space-y-3">
                    <div class="flex justify-between text-sm">
                        <span class="text-gray-500 dark:text-slate-400">Category</span>
                        <span class="font-medium text-gray-900 dark:text-white"><?= sanitize_string($job['category'] ?: 'N/A') ?></span>
                    </div>
                    <div class="flex justify-between text-sm">
                        <span class="text-gray-500 dark:text-slate-400">Duration</span>
                        <span class="font-medium text-gray-900 dark:text-white"><?= sanitize_string($job['project_duration'] ?: 'N/A') ?></span>
                    </div>
                    <div class="flex justify-between text-sm">
                        <span class="text-gray-500 dark:text-slate-400">Max Freelancers</span>
                        <span class="font-medium text-gray-900 dark:text-white"><?= (int) $job['max_freelancers'] ?></span>
                    </div>
                    <div class="flex justify-between text-sm">
                        <span class="text-gray-500 dark:text-slate-400">Deadline</span>
                        <span class="font-medium text-gray-900 dark:text-white"><?= $job['deadline'] ? date('M j, Y', strtotime($job['deadline'])) : 'N/A' ?></span>
                    </div>
                    <div class="flex justify-between text-sm">
                        <span class="text-gray-500 dark:text-slate-400">Last Updated</span>
                        <span class="font-medium text-gray-900 dark:text-white"><?= time_ago($job['updated_at']) ?></span>
                    </div>
                    <div class="flex justify-between text-sm">
                        <span class="text-gray-500 dark:text-slate-400">Job ID</span>
                        <span class="font-mono font-medium text-gray-900 dark:text-white">#<?= $jobId ?></span>
                    </div>
                </div>
            </div>

            <!-- Admin Actions -->
            <div class="bg-gray-50 dark:bg-slate-800/50 border border-gray-200 dark:border-slate-700 rounded-lg shadow-sm p-6 fade-in" style="animation-delay:.11s">
                <h3 class="text-sm font-semibold text-gray-900 dark:text-white uppercase tracking-wider mb-5">Admin Actions</h3>
                <div class="flex flex-col gap-4">

                    <!-- View Client Profile -->
                    <div class="flex flex-col gap-1">
                        <a href="user_detail.php?id=<?= (int) $job['client_user_id'] ?>" class="w-full px-4 py-2 text-sm font-medium text-gray-700 dark:text-slate-300 bg-white dark:bg-slate-800 border border-gray-300 dark:border-slate-600 rounded-lg hover:bg-gray-100 dark:hover:bg-slate-700 transition-colors flex justify-center items-center gap-2">
                            <i data-lucide="user-check" class="text-xs text-gray-400 dark:text-slate-500"></i> View Client Profile
                        </a>
                        <p class="text-xs text-gray-500 dark:text-slate-400">View full history and wallet balance</p>
                    </div>

                    <?php if ($contract): ?>
                    <!-- View Contract -->
                    <div class="flex flex-col gap-1">
                        <a href="contract_detail.php?id=<?= (int) $contract['id'] ?>" class="w-full px-4 py-2 text-sm font-medium text-gray-700 dark:text-slate-300 bg-white dark:bg-slate-800 border border-gray-300 dark:border-slate-600 rounded-lg hover:bg-gray-100 dark:hover:bg-slate-700 transition-colors flex justify-center items-center gap-2">
                            <i data-lucide="file-text" class="text-xs text-gray-400 dark:text-slate-500"></i> View Contract
                        </a>
                        <p class="text-xs text-gray-500 dark:text-slate-400">Review milestones and escrow status</p>
                    </div>
                    <?php endif; ?>

                    <?php if ($job['status'] !== 'cancelled'): ?>
                    <!-- Close Job -->
                    <div class="flex flex-col gap-1">
                        <form method="POST" action="job_action.php" onsubmit="return confirm('Close this job? It will be set to cancelled status.')">
                            <?= csrf_field() ?>
                            <input type="hidden" name="action" value="close">
                            <input type="hidden" name="job_id" value="<?= $jobId ?>">
                            <button type="submit" class="w-full px-4 py-2 text-sm font-medium text-gray-700 dark:text-slate-300 bg-white dark:bg-slate-800 border border-gray-300 dark:border-slate-600 rounded-lg hover:bg-gray-100 dark:hover:bg-slate-700 transition-colors flex justify-center items-center gap-2">
                                <i data-lucide="lock" class="text-xs text-gray-400 dark:text-slate-500"></i> Close Job
                            </button>
                        </form>
                        <p class="text-xs text-gray-500 dark:text-slate-400">Prevents new proposals and marks as closed</p>
                    </div>
                    <?php endif; ?>

                    <!-- Feature / Unfeature Job -->
                    <div class="flex flex-col gap-1">
                        <form method="POST" action="job_action.php" onsubmit="return confirm('<?= $job['is_featured'] ? 'Remove this job from featured?' : 'Feature this job on the homepage?' ?>')">
                            <?= csrf_field() ?>
                            <input type="hidden" name="action" value="feature">
                            <input type="hidden" name="job_id" value="<?= $jobId ?>">
                            <button type="submit" class="w-full px-4 py-2 text-sm font-medium text-amber-700 dark:text-amber-400 bg-white dark:bg-slate-800 border border-amber-300 dark:border-amber-700 rounded-lg hover:bg-amber-50 dark:hover:bg-amber-900/20 transition-colors flex justify-center items-center gap-2">
                                <i data-lucide="star" class="text-xs"></i> <?= $job['is_featured'] ? 'Unfeature Job' : 'Feature Job' ?>
                            </button>
                        </form>
                        <p class="text-xs text-gray-500 dark:text-slate-400"><?= $job['is_featured'] ? 'Removes priority placement in search' : 'Gives priority placement in search results' ?></p>
                    </div>

                    <!-- Delete as Spam -->
                    <div class="flex flex-col gap-1">
                        <form method="POST" action="job_action.php" onsubmit="return confirm('PERMANENTLY DELETE this spam job? This cannot be undone.')">
                            <?= csrf_field() ?>
                            <input type="hidden" name="action" value="delete">
                            <input type="hidden" name="job_id" value="<?= $jobId ?>">
                            <button type="submit" class="w-full px-4 py-2 text-sm font-medium text-red-700 dark:text-red-400 bg-white dark:bg-slate-800 border border-red-300 dark:border-red-700 rounded-lg hover:bg-red-50 dark:hover:bg-red-900/20 transition-colors flex justify-center items-center gap-2">
                                <i data-lucide="trash-2" class="text-xs"></i> Delete as Spam
                            </button>
                        </form>
                        <p class="text-xs text-gray-500 dark:text-slate-400">Permanently removes this listing and flags the user</p>
                    </div>

                </div>
            </div>

        </div>
    </div> <!-- close main grid -->

    <!-- ═══ PROPOSAL MODALS ═══════════════════════════════════════════ -->
    <?php foreach ($proposals as $p): ?>
    <div id="proposalModal<?= $p['id'] ?>" class="hidden fixed inset-0 z-50 flex items-center justify-center p-4">
        <div class="absolute inset-0 bg-black/50 dark:bg-black/70" onclick="this.parentElement.classList.add('hidden')"></div>
        <div class="relative bg-white dark:bg-slate-800 rounded-lg border border-gray-200 dark:border-slate-700 shadow-xl w-full max-w-lg max-h-[80vh] overflow-y-auto">
            <div class="p-6">
                <div class="flex items-center justify-between mb-4">
                    <h3 class="text-base font-bold text-gray-900 dark:text-white">Proposal Details</h3>
                    <button type="button" onclick="this.closest('.fixed').classList.add('hidden')" class="w-8 h-8 rounded-lg bg-gray-100 dark:bg-slate-700 flex items-center justify-center text-gray-400 dark:text-slate-500 hover:bg-gray-200 dark:hover:bg-slate-600 transition-colors">
                        <i data-lucide="x" class="text-xs"></i>
                    </button>
                </div>
                <div class="border-t border-gray-100 dark:border-slate-700 mb-4"></div>
                <div class="flex items-center gap-3 mb-4">
                    <?php
                    $_mi = $p['freelancer_image'] ?? '';
                    $_mb = strtolower(basename($_mi));
                    $_mv = $_mi !== '' && $_mi !== null && $_mb !== 'default.png' && $_mb !== 'profile.png';
                    if ($_mv):
                    ?>
                    <img src="<?= sanitize_string(get_profile_image($_mi)) ?>" class="w-12 h-12 rounded-full object-cover border-2 border-gray-100 dark:border-slate-600">
                    <?php else:
                        $_min = '';
                        foreach (explode(' ', trim($p['freelancer_name'] ?? '')) as $_w) { if ($_w !== '') $_min .= strtoupper($_w[0]); }
                        $_min = substr($_min, 0, 2);
                    ?>
                    <div class="w-12 h-12 rounded-full bg-blue-100 text-blue-600 flex items-center justify-center font-semibold text-xs border-2 border-gray-100 dark:border-slate-600"><?= $_min ?></div>
                    <?php endif; ?>
                    <div>
                        <a href="user_detail.php?id=<?= (int) $p['freelancer_user_id'] ?>" class="text-sm font-bold text-gray-900 dark:text-white hover:underline no-underline text-inherit"><?= sanitize_string($p['freelancer_name']) ?></a>
                        <p class="text-xs text-gray-400 dark:text-slate-500"><?= sanitize_string($p['freelancer_title'] ?: 'Freelancer') ?></p>
                    </div>
                    <span class="ml-auto inline-block px-2.5 py-1 rounded-full text-xs font-medium <?= $proposalStatusColors[$p['status']] ?? '' ?>"><?= ucfirst($p['status']) ?></span>
                </div>
                <div class="grid grid-cols-3 gap-3 mb-4">
                    <div class="bg-gray-50 dark:bg-slate-700/50 rounded-lg p-3">
                        <p class="text-xs font-medium text-gray-400 dark:text-slate-500 uppercase tracking-wider">Bid Amount</p>
                        <p class="text-sm font-bold text-gray-900 dark:text-white mt-0.5"><?= format_currency((float) $p['amount']) ?></p>
                    </div>
                    <?php if ($p['hourly_rate']): ?>
                    <div class="bg-gray-50 dark:bg-slate-700/50 rounded-lg p-3">
                        <p class="text-xs font-medium text-gray-400 dark:text-slate-500 uppercase tracking-wider">Hourly Rate</p>
                        <p class="text-sm font-bold text-gray-900 dark:text-white mt-0.5">$<?= number_format((float) $p['hourly_rate'], 2) ?>/hr</p>
                    </div>
                    <?php endif; ?>
                    <?php if ($p['years_of_experience']): ?>
                    <div class="bg-gray-50 dark:bg-slate-700/50 rounded-lg p-3">
                        <p class="text-xs font-medium text-gray-400 dark:text-slate-500 uppercase tracking-wider">Experience</p>
                        <p class="text-sm font-bold text-gray-900 dark:text-white mt-0.5"><?= (int) $p['years_of_experience'] ?> yrs</p>
                    </div>
                    <?php endif; ?>
                </div>
                <div class="mb-4">
                    <p class="text-xs font-medium text-gray-400 dark:text-slate-500 uppercase tracking-wider mb-2">Proposal</p>
                    <div class="bg-gray-50 dark:bg-slate-700/50 rounded-lg p-4 border border-gray-100 dark:border-slate-600">
                        <p class="text-sm text-gray-600 dark:text-slate-300 leading-relaxed whitespace-pre-line"><?= nl2br(sanitize_string($p['proposal_text'])) ?></p>
                    </div>
                </div>
                <p class="text-xs text-gray-400 dark:text-slate-500">Submitted <?= date('M j, Y \a\t g:i A', strtotime($p['created_at'])) ?></p>
            </div>
        </div>
    </div>
    <?php endforeach; ?>

    <!-- ═══ REVIEWS ═══════════════════════════════════════════════════ -->
    <?php if (!empty($reviews)): ?>
    <div class="bg-white dark:bg-slate-800 border border-gray-200 dark:border-slate-700 rounded-lg shadow-sm mb-6 fade-in" style="animation-delay:.17s">
        <div class="p-6">
            <h3 class="text-base font-bold text-gray-900 dark:text-white mb-1"><i data-lucide="star" class="text-yellow-500 mr-2"></i>Reviews (<?= count($reviews) ?>)</h3>
        </div>
        <div class="border-t border-gray-100 dark:border-slate-700"></div>
        <div class="p-6 pt-5">
            <div class="space-y-3">
            <?php foreach ($reviews as $r): ?>
                <div class="border border-gray-100 dark:border-slate-700 rounded-lg p-4">
                    <div class="flex items-center gap-2 mb-2">
                        <?php
                        $_ri = $r['reviewer_image'] ?? '';
                        $_rbb = strtolower(basename($_ri));
                        $_rv = $_ri !== '' && $_ri !== null && $_rbb !== 'default.png' && $_rbb !== 'profile.png';
                        if ($_rv):
                        ?>
                        <img src="<?= sanitize_string(get_profile_image($_ri)) ?>" class="w-7 h-7 rounded-full object-cover">
                        <?php else:
                            $_rin = '';
                            foreach (explode(' ', trim($r['reviewer_name'] ?? '')) as $_w) { if ($_w !== '') $_rin .= strtoupper($_w[0]); }
                            $_rin = substr($_rin, 0, 2);
                        ?>
                        <div class="w-7 h-7 rounded-full bg-blue-100 text-blue-600 flex items-center justify-center font-semibold text-xs"><?= $_rin ?></div>
                        <?php endif; ?>
                        <span class="text-sm font-medium text-gray-900 dark:text-white"><?= sanitize_string($r['reviewer_name']) ?></span>
                        <div class="flex ml-auto">
                            <?php for ($i = 1; $i <= 5; $i++): ?>
                            <i data-lucide="star" class="text-xs <?= $i <= $r['rating'] ? 'text-yellow-400' : 'text-gray-200 dark:text-slate-600' ?>"></i>
                            <?php endfor; ?>
                        </div>
                        <span class="text-xs text-gray-400 dark:text-slate-500"><?= time_ago($r['created_at']) ?></span>
                    </div>
                    <?php if ($r['comment']): ?>
                    <p class="text-sm text-gray-600 dark:text-slate-400"><?= sanitize_string($r['comment']) ?></p>
                    <?php endif; ?>
                </div>
            <?php endforeach; ?>
            </div>
        </div>
    </div>
    <?php endif; ?>

    </div>

<?php require_once __DIR__ . '/../components/layout_end.php'; ?>
