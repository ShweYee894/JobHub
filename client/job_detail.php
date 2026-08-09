<?php
session_start();
require_once '../config/db.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: ../auth/login.php');
    exit();
}

$userId = $_SESSION['user_id'];
$userRole = $_SESSION['user_role'];
$isClient = ($userRole === 'client');
$isFreelancer = ($userRole === 'freelancer');

$stmt = $conn->prepare('SELECT name, profile_image FROM users WHERE id = ?');
$stmt->bind_param('i', $userId);
$stmt->execute();
$user = $stmt->get_result()->fetch_assoc();
$stmt->close();

$jobId = intval($_GET['id'] ?? 0);
if ($jobId <= 0) {
    header('Location: ' . ($isClient ? 'my_jobs.php' : '../freelancer/dashboard.php'));
    exit();
}

$stmt = $conn->prepare('SELECT j.*, COALESCE(cl.company_name, u.name) AS client_name FROM jobs j JOIN clients cl ON j.client_id = cl.client_id JOIN users u ON cl.client_id = u.id WHERE j.id = ?');
$stmt->bind_param('i', $jobId);
$stmt->execute();
$job = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$job) {
    set_flash('error', 'Job not found.');
    header('Location: ' . ($isClient ? 'my_jobs.php' : '../freelancer/dashboard.php'));
    exit();
}

$isOwner = $isClient && ($job['client_id'] == $userId);

$stmt = $conn->prepare('SELECT s.id, s.skill_name, s.category FROM skills s JOIN job_skills js ON s.id = js.skill_id WHERE js.job_id = ? ORDER BY s.category, s.skill_name');
$stmt->bind_param('i', $jobId);
$stmt->execute();
$skillsRes = $stmt->get_result();
$jobSkills = [];
while ($sk = $skillsRes->fetch_assoc()) {
    $jobSkills[] = $sk;
}
$stmt->close();

$ps = $conn->prepare('SELECT COUNT(*) AS cnt FROM proposals WHERE job_id = ?');
$ps->bind_param('i', $jobId);
$ps->execute();
$proposalCount = $ps->get_result()->fetch_assoc()['cnt'];
$ps->close();

$hasProposal = false;
if ($isFreelancer) {
    $hp = $conn->prepare('SELECT COUNT(*) AS cnt FROM proposals WHERE job_id = ? AND freelancer_id = ?');
    $hp->bind_param('ii', $jobId, $userId);
    $hp->execute();
    $hasProposal = $hp->get_result()->fetch_assoc()['cnt'] > 0;
    $hp->close();
}

$proposals = [];
if ($isOwner) {
    $pp = $conn->prepare("
        SELECT p.id, p.proposal_text, p.amount, p.status, p.created_at,
               u.name AS freelancer_name, u.email AS freelancer_email
        FROM proposals p
        JOIN users u ON p.freelancer_id = u.id
        WHERE p.job_id = ?
        ORDER BY CASE p.status WHEN 'pending' THEN 0 WHEN 'accepted' THEN 1 WHEN 'rejected' THEN 2 ELSE 3 END, p.created_at DESC
    ");
    $pp->bind_param('i', $jobId);
    $pp->execute();
    $ppRes = $pp->get_result();
    while ($pr = $ppRes->fetch_assoc()) {
        $proposals[] = $pr;
    }
    $pp->close();
}

$statusColors = [
    'open' => 'bg-emerald-50 text-emerald-600 border border-emerald-200 dark:bg-emerald-900/20 dark:text-emerald-400 dark:border-emerald-800',
    'in_progress' => 'bg-blue-50 text-blue-600 border border-blue-200 dark:bg-blue-900/20 dark:text-blue-400 dark:border-blue-800',
    'completed' => 'bg-purple-50 text-purple-600 border border-purple-200 dark:bg-purple-900/20 dark:text-purple-400 dark:border-purple-800',
    'cancelled' => 'bg-gray-100 text-gray-600 border border-gray-200 dark:bg-gray-700 dark:text-gray-400 dark:border-gray-600',
    'disputed' => 'bg-red-50 text-red-600 border border-red-200 dark:bg-red-900/20 dark:text-red-400 dark:border-red-800',
];

$proposalStatusColors = [
    'pending' => 'bg-amber-50 text-amber-600 border border-amber-200 dark:bg-amber-900/20 dark:text-amber-400 dark:border-amber-800',
    'accepted' => 'bg-emerald-50 text-emerald-600 border border-emerald-200 dark:bg-emerald-900/20 dark:text-emerald-400 dark:border-emerald-800',
    'rejected' => 'bg-red-50 text-red-500 border border-red-200 dark:bg-red-900/20 dark:text-red-400 dark:border-red-800',
];

$pageTitle = 'Job Details';
$pageSubtitle = $isOwner ? 'View and manage job proposals' : 'Review job details and submit a proposal';
$activePage = 'my_jobs';
$user = ['name' => $user['name'] ?? 'Client', 'profile_image' => $user['profile_image'] ?? null];
$unreadCount = get_unread_message_count($userId, 'client');
$profileLink = 'profile.php';
require_once __DIR__ . '/../includes/client_topbar.php';
?>
<?php display_flash('success');
display_flash('error'); ?>

<main class="max-w-7xl mx-auto py-8 px-4">

    <!-- Back Navigation -->
    <a href="<?= $isClient ? 'my_jobs.php' : '../freelancer/browse_jobs.php' ?>" class="inline-flex items-center gap-1.5 text-sm text-zinc-500 hover:text-zinc-900 transition-colors mb-6 group">
        <i data-lucide="arrow-left" class="w-4 h-4 group-hover:-translate-x-0.5 transition-transform"></i>
        Back to Jobs
    </a>

    <!-- Title + Action Buttons Row -->
    <div class="flex flex-col md:flex-row md:items-start justify-between gap-6 mb-8">
        <!-- Project Title Section -->
        <div class="flex-1 min-w-0">
            <h1 class="text-2xl sm:text-3xl font-bold text-zinc-900 tracking-tight leading-snug"><?= decode_over_encoded($job['title']) ?></h1>
            <div class="flex items-center gap-3 mt-3">
                <span class="inline-flex items-center gap-1.5 px-2.5 py-0.5 rounded-full text-xs font-medium <?= $job['status'] === 'open' ? 'bg-emerald-100 text-emerald-700' : ($job['status'] === 'in_progress' ? 'bg-blue-100 text-blue-700' : ($job['status'] === 'completed' ? 'bg-purple-100 text-purple-700' : 'bg-zinc-100 text-zinc-600')) ?>">
                    <span class="w-1.5 h-1.5 rounded-full <?= $job['status'] === 'open' ? 'bg-emerald-500' : ($job['status'] === 'in_progress' ? 'bg-blue-500' : ($job['status'] === 'completed' ? 'bg-purple-500' : 'bg-zinc-400')) ?>"></span>
                    <?= ucfirst(str_replace('_', ' ', sanitize_string($job['status']))) ?>
                </span>
                <?php if (!empty($job['is_featured'])): ?>
                    <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-xs font-medium bg-amber-100 text-amber-700"><i data-lucide="star" class="w-3 h-3 fill-amber-400 text-amber-400"></i>Featured</span>
                <?php endif; ?>
                <span class="text-sm text-zinc-500">Posted <?= date('M d, Y', strtotime($job['created_at'])) ?></span>
            </div>
        </div>

        <!-- Action Buttons -->
        <div class="flex flex-row md:flex-col gap-3 w-full md:w-auto shrink-0">
            <?php if ($isOwner && $job['status'] === 'open'): ?>
                <a href="edit_job.php?id=<?= $jobId ?>" class="w-full md:w-44 inline-flex items-center justify-center gap-2 px-4 py-2 bg-white border border-zinc-200 text-zinc-700 text-sm font-medium rounded-lg hover:bg-zinc-50 shadow-sm transition-colors">
                    <i data-lucide="pencil" class="w-4 h-4"></i> Edit Job
                </a>
                <a href="job_detail.php?id=<?= $jobId ?>" class="w-full md:w-44 inline-flex items-center justify-center gap-2 px-4 py-2 bg-indigo-600 text-white text-sm font-medium rounded-lg hover:bg-indigo-700 shadow-sm transition-colors">
                    <i data-lucide="file-text" class="w-4 h-4"></i> View Proposals
                </a>
            <?php elseif ($isOwner): ?>
                <a href="job_detail.php?id=<?= $jobId ?>" class="w-full md:w-44 inline-flex items-center justify-center gap-2 px-4 py-2 bg-indigo-600 text-white text-sm font-medium rounded-lg hover:bg-indigo-700 shadow-sm transition-colors">
                    <i data-lucide="file-text" class="w-4 h-4"></i> View Proposals
                </a>
            <?php endif; ?>
            <?php if ($isFreelancer && $job['status'] === 'open' && !$hasProposal): ?>
                <a href="../freelancer/submit_proposal.php?job_id=<?= $jobId ?>" class="w-full md:w-44 inline-flex items-center justify-center gap-2 px-4 py-2 bg-indigo-600 text-white text-sm font-medium rounded-lg hover:bg-indigo-700 shadow-sm transition-colors">
                    <i data-lucide="send" class="w-4 h-4"></i> Submit Proposal
                </a>
            <?php elseif ($isFreelancer && $hasProposal): ?>
                <div class="w-full md:w-44 inline-flex items-center justify-center gap-2 px-4 py-2 bg-emerald-50 text-emerald-700 text-sm font-medium rounded-lg border border-emerald-200">
                    <i data-lucide="check-circle-2" class="w-4 h-4"></i> Submitted
                </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- 2-Column Grid -->
    <div class="grid grid-cols-1 lg:grid-cols-3 gap-8">

        <!-- ═══════════════ LEFT COLUMN (Main Content) ═══════════════ -->
        <div class="lg:col-span-2 space-y-6">

            <!-- Description Card -->
            <div class="bg-white rounded-xl border border-zinc-100 shadow-sm p-6 sm:p-8 fade-in" style="animation-delay:.05s">
                <div class="flex items-center gap-2.5 mb-5">
                    <div class="w-8 h-8 rounded-lg bg-zinc-100 flex items-center justify-center"><i data-lucide="file-text" class="w-4 h-4 text-zinc-500"></i></div>
                    <h2 class="text-sm font-semibold tracking-wider text-zinc-500 uppercase">Description</h2>
                </div>
                <div class="text-sm text-zinc-700 leading-relaxed whitespace-pre-line"><?= nl2br(sanitize_string($job['description'])) ?></div>
            </div>

            <!-- Required Skills (Mobile — shown here, duplicated in sidebar for desktop) -->
            <?php if (!empty($jobSkills)): ?>
                <div class="bg-white rounded-xl border border-zinc-100 shadow-sm p-6 sm:p-8 lg:hidden fade-in" style="animation-delay:.1s">
                    <div class="flex items-center gap-2.5 mb-5">
                        <div class="w-8 h-8 rounded-lg bg-zinc-100 flex items-center justify-center"><i data-lucide="layers" class="w-4 h-4 text-zinc-500"></i></div>
                        <h2 class="text-sm font-semibold tracking-wider text-zinc-500 uppercase">Required Skills</h2>
                    </div>
                    <div class="flex flex-wrap gap-2">
                        <?php foreach ($jobSkills as $sk): ?>
                            <span class="inline-flex items-center gap-1.5 px-3 py-1.5 bg-zinc-100 text-zinc-700 rounded-full text-sm font-medium hover:bg-zinc-200 transition-colors">
                                <?= sanitize_string($sk['skill_name']) ?>
                            </span>
                        <?php endforeach; ?>
                    </div>
                </div>
            <?php endif; ?>

            <!-- Proposals Section -->
            <div class="fade-in" style="animation-delay:.15s">
                <?php if ($isOwner && !empty($proposals)): ?>
                    <div class="bg-white rounded-xl border border-zinc-100 shadow-sm p-6 sm:p-8">
                        <div class="flex items-center gap-2.5 mb-6">
                            <div class="w-8 h-8 rounded-lg bg-indigo-50 flex items-center justify-center"><i data-lucide="file-text" class="w-4 h-4 text-indigo-600"></i></div>
                            <div>
                                <h2 class="text-sm font-semibold tracking-wider text-zinc-500 uppercase">Proposals</h2>
                                <p class="text-xs text-zinc-400 mt-0.5"><?= count($proposals) ?> proposal<?= count($proposals) !== 1 ? 's' : '' ?> received</p>
                            </div>
                        </div>
                        <div class="space-y-3">
                            <?php foreach ($proposals as $p): ?>
                                <div class="border border-zinc-100 rounded-xl p-5 hover:border-zinc-200 transition-colors">
                                    <div class="flex flex-col sm:flex-row sm:items-start gap-4">
                                        <div class="flex-1 min-w-0">
                                            <div class="flex flex-wrap items-center gap-2 mb-2">
                                                <h4 class="text-sm font-bold text-zinc-900"><?= sanitize_string($p['freelancer_name']) ?></h4>
                                                <span class="inline-flex items-center px-2 py-0.5 rounded-full text-[10px] font-semibold <?= $proposalStatusColors[$p['status']] ?? '' ?>"><?= ucfirst(sanitize_string($p['status'])) ?></span>
                                            </div>
                                            <div class="flex flex-wrap items-center gap-4 text-xs text-zinc-400 mb-3">
                                                <span class="flex items-center gap-1"><i data-lucide="dollar-sign" class="w-3.5 h-3.5 text-emerald-500"></i><span class="font-bold text-zinc-700"><?= format_currency($p['amount']) ?></span></span>
                                                <span class="flex items-center gap-1"><i data-lucide="clock" class="w-3.5 h-3.5 text-zinc-300"></i><?= date('M d, Y', strtotime($p['created_at'])) ?></span>
                                            </div>
                                            <div class="bg-zinc-50 rounded-lg p-4 border border-zinc-100">
                                                <p class="text-sm text-zinc-600 leading-relaxed whitespace-pre-line"><?= nl2br(sanitize_string($p['proposal_text'])) ?></p>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                <?php elseif ($isOwner && empty($proposals)): ?>
                    <div class="bg-white rounded-xl border border-zinc-100 shadow-sm p-6 sm:p-8">
                        <div class="flex items-center gap-2.5 mb-6">
                            <div class="w-8 h-8 rounded-lg bg-zinc-100 flex items-center justify-center"><i data-lucide="inbox" class="w-4 h-4 text-zinc-400"></i></div>
                            <h2 class="text-sm font-semibold tracking-wider text-zinc-500 uppercase">Proposals</h2>
                        </div>
                        <div class="border-2 border-dashed border-zinc-200 rounded-xl py-12 px-6 text-center">
                            <div class="w-14 h-14 rounded-2xl bg-zinc-100 flex items-center justify-center mx-auto mb-4">
                                <i data-lucide="inbox" class="w-7 h-7 text-zinc-300"></i>
                            </div>
                            <p class="text-sm font-medium text-zinc-500 mb-1">No proposals yet</p>
                            <p class="text-xs text-zinc-400">Proposals from freelancers will appear here.</p>
                        </div>
                    </div>
                <?php endif; ?>
            </div>

        </div>

        <!-- ═══════════════ RIGHT COLUMN (Sticky Sidebar) ═══════════════ -->
        <div class="lg:col-span-1">
            <div class="lg:sticky lg:top-24 space-y-5">

                <!-- Job Details Card -->
                <div class="bg-white rounded-xl border border-zinc-100 shadow-md p-5 fade-in" style="animation-delay:.2s">
                    <h3 class="text-sm font-semibold tracking-wider text-zinc-500 uppercase mb-4">Job Details</h3>
                    <div class="space-y-4">
                        <!-- Budget -->
                        <div class="flex items-center gap-3">
                            <div class="w-9 h-9 rounded-lg bg-emerald-50 flex items-center justify-center flex-shrink-0">
                                <i data-lucide="dollar-sign" class="w-4 h-4 text-emerald-600"></i>
                            </div>
                            <div>
                                <p class="text-xs text-zinc-400">Budget</p>
                                <p class="text-sm font-bold text-zinc-900"><?= format_currency($job['budget']) ?></p>
                            </div>
                        </div>
                        <!-- Job Type -->
                        <div class="flex items-center gap-3">
                            <div class="w-9 h-9 rounded-lg bg-blue-50 flex items-center justify-center flex-shrink-0">
                                <i data-lucide="<?= $job['job_type'] === 'hourly' ? 'clock' : 'target' ?>" class="w-4 h-4 text-blue-600"></i>
                            </div>
                            <div>
                                <p class="text-xs text-zinc-400">Rate</p>
                                <p class="text-sm font-bold text-zinc-900"><?= $job['job_type'] === 'hourly' ? 'Hourly' : 'Fixed Price' ?></p>
                            </div>
                        </div>
                        <!-- Experience -->
                        <?php if (!empty($job['experience_level'])): ?>
                            <div class="flex items-center gap-3">
                                <div class="w-9 h-9 rounded-lg bg-violet-50 flex items-center justify-center flex-shrink-0">
                                    <i data-lucide="bar-chart-2" class="w-4 h-4 text-violet-600"></i>
                                </div>
                                <div>
                                    <p class="text-xs text-zinc-400">Experience</p>
                                    <p class="text-sm font-bold text-zinc-900"><?= ucfirst(sanitize_string($job['experience_level'])) ?></p>
                                </div>
                            </div>
                        <?php endif; ?>
                        <!-- Due Date -->
                        <?php if (!empty($job['deadline'])): ?>
                            <div class="flex items-center gap-3">
                                <div class="w-9 h-9 rounded-lg bg-amber-50 flex items-center justify-center flex-shrink-0">
                                    <i data-lucide="calendar" class="w-4 h-4 text-amber-600"></i>
                                </div>
                                <div>
                                    <p class="text-xs text-zinc-400">Due Date</p>
                                    <p class="text-sm font-bold text-zinc-900"><?= date('M d, Y', strtotime($job['deadline'])) ?></p>
                                </div>
                            </div>
                        <?php endif; ?>
                        <!-- Client -->
                        <div class="flex items-center gap-3">
                            <div class="w-9 h-9 rounded-lg bg-zinc-100 flex items-center justify-center flex-shrink-0">
                                <i data-lucide="user" class="w-4 h-4 text-zinc-500"></i>
                            </div>
                            <div>
                                <p class="text-xs text-zinc-400">Client</p>
                                <p class="text-sm font-bold text-zinc-900"><?= sanitize_string($job['client_name']) ?></p>
                            </div>
                        </div>
                        <!-- Proposals Count -->
                        <div class="flex items-center gap-3">
                            <div class="w-9 h-9 rounded-lg bg-indigo-50 flex items-center justify-center flex-shrink-0">
                                <i data-lucide="users" class="w-4 h-4 text-indigo-600"></i>
                            </div>
                            <div>
                                <p class="text-xs text-zinc-400">Proposals</p>
                                <p class="text-sm font-bold text-zinc-900"><?= $proposalCount ?></p>
                            </div>
                        </div>
                        <!-- Category -->
                        <?php if (!empty($job['category'])): ?>
                            <div class="flex items-center gap-3">
                                <div class="w-9 h-9 rounded-lg bg-pink-50 flex items-center justify-center flex-shrink-0">
                                    <i data-lucide="layers" class="w-4 h-4 text-pink-600"></i>
                                </div>
                                <div>
                                    <p class="text-xs text-zinc-400">Category</p>
                                    <p class="text-sm font-bold text-zinc-900"><?= sanitize_string($job['category']) ?></p>
                                </div>
                            </div>
                        <?php endif; ?>
                        <!-- Duration -->
                        <?php if (!empty($job['project_duration'])): ?>
                            <div class="flex items-center gap-3">
                                <div class="w-9 h-9 rounded-lg bg-teal-50 flex items-center justify-center flex-shrink-0">
                                    <i data-lucide="timer" class="w-4 h-4 text-teal-600"></i>
                                </div>
                                <div>
                                    <p class="text-xs text-zinc-400">Duration</p>
                                    <p class="text-sm font-bold text-zinc-900"><?= sanitize_string($job['project_duration']) ?></p>
                                </div>
                            </div>
                        <?php endif; ?>
                    </div>

                    <!-- Divider -->
                    <div class="border-t border-zinc-100 my-5"></div>

                    <!-- Required Skills -->
                    <?php if (!empty($jobSkills)): ?>
                        <h3 class="text-sm font-semibold tracking-wider text-zinc-500 uppercase mb-3">Required Skills</h3>
                        <div class="flex flex-wrap gap-2">
                            <?php foreach ($jobSkills as $sk): ?>
                                <span class="inline-flex items-center px-3 py-1 bg-zinc-100 text-zinc-700 rounded-full text-sm font-medium hover:bg-zinc-200 transition-colors">
                                    <?= sanitize_string($sk['skill_name']) ?>
                                </span>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>

            </div>
        </div>

    </div>
</main>

<?php $conn->close(); ?>
<?php require_once __DIR__ . '/../includes/client_footer.php'; ?>