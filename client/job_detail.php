<?php
session_start();
require_once '../config/db.php';
require_once '../config/helpers.php';

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

$stmt = $conn->prepare('SELECT j.*, u.name AS client_name FROM jobs j JOIN users u ON j.client_id = u.id WHERE j.id = ?');
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
    'open' => 'bg-emerald-50 text-emerald-600 border border-emerald-200',
    'in_progress' => 'bg-blue-50 text-blue-600 border border-blue-200',
    'completed' => 'bg-purple-50 text-purple-600 border border-purple-200',
    'cancelled' => 'bg-gray-100 text-gray-600 border border-gray-200',
    'disputed' => 'bg-red-50 text-red-600 border border-red-200',
];

$proposalStatusColors = [
    'pending' => 'bg-amber-50 text-amber-600 border border-amber-200',
    'accepted' => 'bg-emerald-50 text-emerald-600 border border-emerald-200',
    'rejected' => 'bg-red-50 text-red-500 border border-red-200',
];

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
$pageTitle = 'Job Details';
$pageSubtitle = $isOwner ? 'View and manage job proposals' : 'Review job details and submit a proposal';
$activePage = 'my_jobs';
$user = ['name' => $user['name'] ?? 'Client', 'profile_image' => $user['profile_image'] ?? null];
$unreadCount = get_unread_message_count($userId, 'client');
$profileLink = 'profile.php';
require_once __DIR__ . '/../components/layout_start.php';
?>
    <?php display_flash('success');
    display_flash('error'); ?>

    <div class="bg-white rounded-2xl border border-gray-100 shadow-sm fade-in">
        <div class="p-6 sm:p-8">
            <div class="flex flex-col sm:flex-row sm:items-start justify-between gap-4 mb-6">
                <div class="flex-1">
                    <div class="flex flex-wrap items-center gap-2 mb-3">
                        <a href="<?= $isClient ? 'my_jobs.php' : '../freelancer/browse_jobs.php' ?>" class="text-gray-400 hover:text-gray-600 transition-colors text-sm"><i class="fas fa-arrow-left"></i></a>
                        <h2 class="text-xl font-bold text-gray-900"><?= sanitize_string($job['title']) ?></h2>
                        <span class="inline-block px-3 py-1 rounded-lg text-[11px] font-semibold <?= $statusColors[$job['status']] ?? '' ?>"><?= ucfirst(str_replace('_', ' ', sanitize_string($job['status']))) ?></span>
                    </div>
                    <div class="flex flex-wrap items-center gap-4 text-sm text-gray-400">
                        <span class="flex items-center gap-1.5"><i class="fas fa-dollar-sign text-emerald-500"></i><span class="font-bold text-gray-700 text-base"><?= format_currency($job['budget']) ?></span></span>
                        <span class="flex items-center gap-1.5"><i class="fas fa-calendar text-blue-400"></i>Posted <?= date('M d, Y', strtotime($job['created_at'])) ?></span>
                        <span class="flex items-center gap-1.5"><i class="fas fa-user text-gray-400"></i><?= sanitize_string($job['client_name']) ?></span>
                        <span class="flex items-center gap-1.5"><i class="fas fa-file-alt text-violet-400"></i><span class="font-semibold text-gray-600"><?= $proposalCount ?></span> proposal<?= $proposalCount !== 1 ? 's' : '' ?></span>
                    </div>
                </div>
                <div class="flex gap-2">
                    <?php if ($isOwner && $job['status'] === 'open'): ?>
                        <a href="edit_job.php?id=<?= $jobId ?>" class="inline-flex items-center gap-2 px-4 py-2 bg-gray-50 hover:bg-gray-100 text-gray-600 text-xs font-semibold rounded-xl transition-all border border-gray-200"><i class="fas fa-pen text-[10px]"></i> Edit</a>
                    <?php endif; ?>
                    <?php if ($isOwner): ?>
                        <a href="job_detail.php?id=<?= $jobId ?>" class="inline-flex items-center gap-2 px-4 py-2 bg-blue-50 hover:bg-blue-100 text-blue-600 text-xs font-semibold rounded-xl transition-all"><i class="fas fa-file-alt text-[10px]"></i> View Proposals</a>
                    <?php endif; ?>
                    <?php if ($isFreelancer && $job['status'] === 'open' && !$hasProposal): ?>
                        <a href="../freelancer/submit_proposal.php?job_id=<?= $jobId ?>" class="btn-grad inline-flex items-center gap-2 px-5 py-2 text-white text-xs font-semibold rounded-xl shadow-lg shadow-blue-500/25"><i class="fas fa-paper-plane text-[10px]"></i> Submit Proposal</a>
                    <?php elseif ($isFreelancer && $hasProposal): ?>
                        <span class="inline-flex items-center gap-2 px-4 py-2 bg-emerald-50 text-emerald-600 text-xs font-semibold rounded-xl border border-emerald-200"><i class="fas fa-check text-[10px]"></i> Proposal Submitted</span>
                    <?php endif; ?>
                </div>
            </div>

            <div class="mb-6">
                <h3 class="text-sm font-bold text-gray-700 uppercase tracking-wider mb-3">Description</h3>
                <div class="bg-gray-50 rounded-xl p-5 border border-gray-100">
                    <p class="text-sm text-gray-600 leading-relaxed whitespace-pre-line"><?= nl2br(sanitize_string($job['description'])) ?></p>
                </div>
            </div>

            <?php if (!empty($jobSkills)): ?>
                <div>
                    <h3 class="text-sm font-bold text-gray-700 uppercase tracking-wider mb-3">Required Skills</h3>
                    <div class="flex flex-wrap gap-2">
                        <?php foreach ($jobSkills as $sk): ?>
                            <span class="inline-flex items-center gap-1.5 px-3 py-1.5 bg-blue-50 text-blue-600 text-xs font-medium rounded-lg border border-blue-100">
                                <i class="fas fa-tag text-[9px]"></i><?= sanitize_string($sk['skill_name']) ?>
                                <span class="text-[10px] text-blue-400 font-normal">(<?= sanitize_string($sk['category']) ?>)</span>
                            </span>
                        <?php endforeach; ?>
                    </div>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <?php if ($isOwner && !empty($proposals)): ?>
        <div class="bg-white rounded-2xl border border-gray-100 shadow-sm fade-in" style="animation-delay:.2s">
            <div class="p-6 sm:p-8">
                <div class="flex items-center gap-3 mb-6">
                    <div class="w-10 h-10 rounded-xl bg-violet-50 flex items-center justify-center"><i class="fas fa-file-alt text-violet-500"></i></div>
                    <div>
                        <h2 class="text-base font-bold text-gray-900">Proposals</h2>
                        <p class="text-xs text-gray-400"><?= count($proposals) ?> proposal<?= count($proposals) !== 1 ? 's' : '' ?> received</p>
                    </div>
                </div>
                <div class="space-y-4">
                    <?php foreach ($proposals as $p): ?>
                        <div class="border border-gray-100 rounded-xl p-5 hover:bg-gray-50/50 transition-colors">
                            <div class="flex flex-col sm:flex-row sm:items-start gap-4">
                                <div class="flex-1 min-w-0">
                                    <div class="flex flex-wrap items-center gap-2 mb-1">
                                        <h4 class="text-sm font-bold text-gray-900"><?= sanitize_string($p['freelancer_name']) ?></h4>
                                        <span class="inline-block px-2.5 py-0.5 rounded-lg text-[10px] font-semibold <?= $proposalStatusColors[$p['status']] ?? '' ?>"><?= ucfirst(sanitize_string($p['status'])) ?></span>
                                    </div>
                                    <div class="flex flex-wrap items-center gap-4 text-xs text-gray-500 mb-3">
                                        <span class="flex items-center gap-1"><i class="fas fa-dollar-sign text-emerald-500"></i><span class="font-bold text-gray-700"><?= format_currency($p['amount']) ?></span></span>
                                        <span class="flex items-center gap-1"><i class="fas fa-clock text-gray-400"></i><?= date('M d, Y', strtotime($p['created_at'])) ?></span>
                                    </div>
                                    <div class="bg-gray-50 rounded-lg p-4 border border-gray-100">
                                        <p class="text-sm text-gray-600 leading-relaxed whitespace-pre-line"><?= nl2br(sanitize_string($p['proposal_text'])) ?></p>
                                    </div>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
    <?php elseif ($isOwner && empty($proposals)): ?>
        <div class="bg-white rounded-2xl border border-gray-100 shadow-sm fade-in" style="animation-delay:.2s">
            <div class="text-center py-12">
                <div class="w-16 h-16 rounded-2xl bg-gray-100 flex items-center justify-center mx-auto mb-4"><i class="fas fa-inbox text-2xl text-gray-300"></i></div>
                <p class="text-gray-500 text-sm font-medium">No proposals yet</p>
                <p class="text-gray-400 text-xs mt-1">Proposals from freelancers will appear here</p>
            </div>
        </div>
    <?php endif; ?>
    <?php $conn->close(); ?>
<?php require_once __DIR__ . '/../components/layout_end.php'; ?>
