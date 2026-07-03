<?php
require_once __DIR__ . '/../auth/auth.php';
require_role('client');
require_once __DIR__ . '/../config/db.php';

$currentPage = 'proposals';
$userId = $_SESSION['user_id'];
$proposalId = sanitize_int($_GET['id'] ?? 0);

if ($proposalId <= 0) {
    set_flash('error', 'Invalid proposal reference.');
    redirect('/finalproject/client/proposals.php');
}

$stmt = $conn->prepare('
    SELECT p.id, p.amount, p.status, p.proposal_text, p.created_at,
           j.id AS job_id, j.title AS job_title, j.description AS job_description,
           j.budget AS job_budget, j.status AS job_status, j.client_id,
           u.id AS freelancer_user_id, u.name AS freelancer_name,
           u.profile_image AS freelancer_avatar, u.email AS freelancer_email
    FROM proposals p
    JOIN jobs j ON p.job_id = j.id
    JOIN users u ON p.freelancer_id = u.id
    WHERE p.id = ?
');
$stmt->bind_param('i', $proposalId);
$stmt->execute();
$proposal = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$proposal || $proposal['client_id'] != $userId) {
    set_flash('error', 'Proposal not found or access denied.');
    redirect('/finalproject/client/proposals.php');
}

$stmt = $conn->prepare('
    SELECT f.title, f.hourly_rate, f.years_of_experience, f.availability, f.bio
    FROM freelancers f
    WHERE f.user_id = ?
');
$stmt->bind_param('i', $proposal['freelancer_user_id']);
$stmt->execute();
$freelancerProfile = $stmt->get_result()->fetch_assoc();
$stmt->close();

$freelancerSkills = [];
$stmt = $conn->prepare('
    SELECT s.skill_name, s.category
    FROM skills s
    JOIN freelancer_skills fs ON s.id = fs.skill_id
    JOIN freelancers f ON fs.freelancer_id = f.id
    WHERE f.user_id = ?
    ORDER BY s.category, s.skill_name
');
$stmt->bind_param('i', $proposal['freelancer_user_id']);
$stmt->execute();
$skillsResult = $stmt->get_result();
while ($sk = $skillsResult->fetch_assoc()) {
    $freelancerSkills[] = $sk;
}
$stmt->close();

$stmt = $conn->prepare('
    SELECT AVG(r.rating) AS avg_rating, COUNT(r.id) AS review_count
    FROM reviews r
    WHERE r.reviewee_id = ?
');
$stmt->bind_param('i', $proposal['freelancer_user_id']);
$stmt->execute();
$ratingData = $stmt->get_result()->fetch_assoc();
$stmt->close();

$proposalColors = [
    'pending' => 'bg-amber-50 text-amber-600 border border-amber-200',
    'accepted' => 'bg-emerald-50 text-emerald-600 border border-emerald-200',
    'rejected' => 'bg-red-50 text-red-500 border border-red-200',
];

$statusColors = [
    'open' => 'bg-emerald-50 text-emerald-600 border border-emerald-200',
    'in_progress' => 'bg-blue-50 text-blue-600 border border-blue-200',
    'completed' => 'bg-purple-50 text-purple-600 border border-purple-200',
    'cancelled' => 'bg-gray-100 text-gray-600 border border-gray-200',
    'disputed' => 'bg-red-50 text-red-600 border border-red-200',
];

$conn->close();

$navItems = [
    ['key' => 'dashboard', 'label' => 'Dashboard', 'url' => 'dashboard.php', 'icon' => 'fa-th-large'],
    ['key' => 'my_jobs', 'label' => 'My Jobs', 'url' => 'my_jobs.php', 'icon' => 'fa-briefcase'],
    ['key' => 'post_job', 'label' => 'Post a Job', 'url' => 'post_job.php', 'icon' => 'fa-plus-circle'],
    ['key' => 'proposals', 'label' => 'Proposals', 'url' => 'proposals.php', 'icon' => 'fa-file-alt'],
    ['key' => 'contracts', 'label' => 'Contracts', 'url' => 'contracts.php', 'icon' => 'fa-handshake'],
    ['key' => 'payment_history', 'label' => 'Payments', 'url' => 'payment_history.php', 'icon' => 'fa-credit-card'],
    ['key' => 'messages', 'label' => 'Messages', 'url' => 'messages.php', 'icon' => 'fa-comment-dots'],
];
$pageTitle = 'Review Proposal – FreelanceHub';
$pageSubtitle = 'Review proposal from ' . sanitize_string($proposal['freelancer_name']);
$activePage = 'proposals';
$user = ['name' => $user['name'] ?? 'Client', 'profile_image' => $user['profile_image'] ?? null];
$unreadCount = $unreadMessages ?? 0;
$profileLink = 'profile.php';
require_once __DIR__ . '/../components/layout_start.php';
?>
    <?php display_flash('success');
    display_flash('error'); ?>

    <div class="flex flex-col xl:flex-row gap-6">

        <div class="flex-1 min-w-0 space-y-6">

            <div class="bg-white rounded-2xl border border-gray-100 shadow-sm p-6 fade-in">
                <div class="flex flex-wrap items-center gap-2 mb-4">
                    <h2 class="text-xl font-bold text-gray-900"><?= sanitize_string($proposal['job_title']) ?></h2>
                    <span class="inline-block px-3 py-1 rounded-lg text-[11px] font-semibold <?= $statusColors[$proposal['job_status']] ?? '' ?>">
                        <?= ucfirst(str_replace('_', ' ', $proposal['job_status'])) ?>
                    </span>
                </div>
                <div class="bg-gray-50 rounded-xl p-5 border border-gray-100">
                    <p class="text-sm text-gray-700 leading-relaxed whitespace-pre-line line-clamp-6"><?= nl2br(sanitize_string($proposal['job_description'])) ?></p>
                </div>
            </div>

            <div class="bg-white rounded-2xl border border-gray-100 shadow-sm p-6 fade-in" style="animation-delay:.1s">
                <div class="flex items-center justify-between mb-5">
                    <h3 class="text-base font-bold text-gray-900 flex items-center gap-2">
                        <i class="fas fa-paper-plane text-blue-500 text-sm"></i> Proposal Details
                    </h3>
                    <span class="inline-block px-3 py-1 rounded-lg text-[11px] font-bold <?= $proposalColors[$proposal['status']] ?? '' ?>">
                        <?= ucfirst(sanitize_string($proposal['status'])) ?>
                    </span>
                </div>

                <div class="space-y-4">
                    <div class="bg-gray-50 rounded-xl p-5 border border-gray-100">
                        <h4 class="text-xs font-bold text-gray-400 uppercase tracking-wider mb-3">Cover Letter</h4>
                        <p class="text-sm text-gray-700 leading-relaxed whitespace-pre-line"><?= nl2br(sanitize_string($proposal['proposal_text'])) ?></p>
                    </div>

                    <div class="grid grid-cols-3 gap-4">
                        <div class="bg-gray-50 rounded-xl p-4 border border-gray-100 text-center">
                            <p class="text-[10px] text-gray-400 uppercase tracking-wider font-semibold mb-1">Bid Amount</p>
                            <p class="text-xl font-black text-gray-900"><?= format_currency((float) $proposal['amount']) ?></p>
                        </div>
                        <div class="bg-gray-50 rounded-xl p-4 border border-gray-100 text-center">
                            <p class="text-[10px] text-gray-400 uppercase tracking-wider font-semibold mb-1">Job Budget</p>
                            <p class="text-xl font-black text-gray-900"><?= format_currency((float) $proposal['job_budget']) ?></p>
                        </div>
                        <div class="bg-gray-50 rounded-xl p-4 border border-gray-100 text-center">
                            <p class="text-[10px] text-gray-400 uppercase tracking-wider font-semibold mb-1">Difference</p>
                            <?php $diff = (float) $proposal['job_budget'] - (float) $proposal['amount']; ?>
                            <p class="text-xl font-black <?= $diff >= 0 ? 'text-emerald-600' : 'text-red-500' ?>">
                                <?= $diff >= 0 ? '-' : '+' ?><?= format_currency(abs($diff)) ?>
                            </p>
                        </div>
                    </div>

                    <div class="flex items-center gap-4 text-xs text-gray-400">
                        <span class="flex items-center gap-1.5">
                            <i class="fas fa-calendar text-blue-400"></i>
                            Submitted <?= date('M d, Y \a\t g:i A', strtotime($proposal['created_at'])) ?>
                        </span>
                        <span class="flex items-center gap-1.5">
                            <i class="fas fa-clock text-gray-400"></i>
                            <?= time_ago($proposal['created_at']) ?>
                        </span>
                    </div>
                </div>

                <?php if ($proposal['status'] === 'pending'): ?>
                <div class="mt-6 pt-5 border-t border-gray-100">
                    <div class="flex flex-wrap gap-3">
                        <form method="POST" action="accept_proposal.php" onsubmit="return confirm('Accept this proposal? This will hire the freelancer and reject all other pending proposals for this job.');">
                            <?= csrf_field() ?>
                            <input type="hidden" name="proposal_id" value="<?= (int) $proposal['id'] ?>">
                            <button type="submit" class="inline-flex items-center gap-2 px-6 py-3 btn-grad text-white text-sm font-bold rounded-xl shadow-lg shadow-blue-500/25">
                                <i class="fas fa-check text-xs"></i> Accept Proposal
                            </button>
                        </form>
                        <form method="POST" action="reject_proposal.php" onsubmit="return confirm('Are you sure you want to reject this proposal?');">
                            <?= csrf_field() ?>
                            <input type="hidden" name="proposal_id" value="<?= (int) $proposal['id'] ?>">
                            <button type="submit" class="inline-flex items-center gap-2 px-6 py-3 bg-red-50 hover:bg-red-100 text-red-500 text-sm font-bold rounded-xl transition-all border border-red-200">
                                <i class="fas fa-times text-xs"></i> Reject Proposal
                            </button>
                        </form>
                    </div>
                </div>
                <?php endif; ?>
            </div>
        </div>

        <div class="w-full xl:w-96 flex-shrink-0 space-y-5">
            <div class="bg-white rounded-2xl border border-gray-100 shadow-sm p-6 fade-in" style="animation-delay:.15s">
                <h3 class="text-sm font-bold text-gray-900 mb-4 flex items-center gap-2">
                    <i class="fas fa-user-tie text-blue-500 text-xs"></i> Freelancer Profile
                </h3>

                <div class="flex items-center gap-3 mb-5">
                    <?php
                    $fAvatar = !empty($proposal['freelancer_avatar'])
                        ? '../' . htmlspecialchars($proposal['freelancer_avatar'])
                        : 'https://ui-avatars.com/api/?name=' . urlencode($proposal['freelancer_name']) . '&background=2563eb&color=fff&bold=true&size=80';
                    ?>
                    <img src="<?= $fAvatar ?>" class="w-14 h-14 rounded-xl object-cover border-2 border-gray-100" alt="Freelancer">
                    <div>
                        <p class="font-bold text-gray-900 text-sm"><?= sanitize_string($proposal['freelancer_name']) ?></p>
                        <p class="text-[11px] text-gray-400"><?= sanitize_string($freelancerProfile['title'] ?? 'Freelancer') ?></p>
                        <?php if ($ratingData['avg_rating']): ?>
                        <div class="flex items-center gap-1 mt-1">
                            <div class="flex items-center gap-0.5">
                                <?php for ($i = 1; $i <= 5; $i++): ?>
                                <i class="fas fa-star text-[10px] <?= $i <= round($ratingData['avg_rating']) ? 'text-amber-400' : 'text-gray-200' ?>"></i>
                                <?php endfor; ?>
                            </div>
                            <span class="text-[10px] text-gray-400"><?= number_format($ratingData['avg_rating'], 1) ?> (<?= $ratingData['review_count'] ?>)</span>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>

                <div class="space-y-3 mb-5">
                    <div class="flex items-center justify-between py-2 border-b border-gray-50">
                        <span class="text-xs text-gray-500">Hourly Rate</span>
                        <span class="text-sm font-bold text-gray-900"><?= format_currency((float) ($freelancerProfile['hourly_rate'] ?? 0)) ?>/hr</span>
                    </div>
                    <div class="flex items-center justify-between py-2 border-b border-gray-50">
                        <span class="text-xs text-gray-500">Experience</span>
                        <span class="text-sm font-semibold text-gray-700"><?= (int) ($freelancerProfile['years_of_experience'] ?? 0) ?> years</span>
                    </div>
                    <div class="flex items-center justify-between py-2">
                        <span class="text-xs text-gray-500">Availability</span>
                        <span class="text-xs font-semibold <?= ($freelancerProfile['availability'] ?? '') === 'Available' ? 'text-emerald-600' : 'text-amber-600' ?>">
                            <?= sanitize_string($freelancerProfile['availability'] ?? 'N/A') ?>
                        </span>
                    </div>
                </div>

                <?php if (!empty($freelancerProfile['bio'])): ?>
                <div class="mb-5">
                    <h4 class="text-xs font-bold text-gray-400 uppercase tracking-wider mb-2">Bio</h4>
                    <p class="text-xs text-gray-600 leading-relaxed line-clamp-4"><?= sanitize_string($freelancerProfile['bio']) ?></p>
                </div>
                <?php endif; ?>

                <?php if (!empty($freelancerSkills)): ?>
                <div>
                    <h4 class="text-xs font-bold text-gray-400 uppercase tracking-wider mb-2">Skills</h4>
                    <div class="flex flex-wrap gap-1.5">
                        <?php foreach ($freelancerSkills as $sk): ?>
                        <span class="inline-flex items-center gap-1 px-2.5 py-1 bg-blue-50 text-blue-600 text-[11px] font-medium rounded-lg border border-blue-100">
                            <?= sanitize_string($sk['skill_name']) ?>
                        </span>
                        <?php endforeach; ?>
                    </div>
                </div>
                <?php endif; ?>
            </div>

            <div class="bg-white rounded-2xl border border-gray-100 shadow-sm p-6 fade-in" style="animation-delay:.2s">
                <h3 class="text-sm font-bold text-gray-900 mb-4 flex items-center gap-2">
                    <i class="fas fa-receipt text-violet-500 text-xs"></i> Quick Summary
                </h3>
                <div class="space-y-3">
                    <div class="flex items-center justify-between py-2 border-b border-gray-50">
                        <span class="text-xs text-gray-500">Bid Amount</span>
                        <span class="text-sm font-bold text-gray-900"><?= format_currency((float) $proposal['amount']) ?></span>
                    </div>
                    <div class="flex items-center justify-between py-2 border-b border-gray-50">
                        <span class="text-xs text-gray-500">Job Budget</span>
                        <span class="text-sm font-bold text-gray-900"><?= format_currency((float) $proposal['job_budget']) ?></span>
                    </div>
                    <div class="flex items-center justify-between py-2 border-b border-gray-50">
                        <span class="text-xs text-gray-500">Savings</span>
                        <span class="text-sm font-bold <?= $diff >= 0 ? 'text-emerald-600' : 'text-red-500' ?>">
                            <?= $diff >= 0 ? format_currency($diff) : 'Over budget' ?>
                        </span>
                    </div>
                    <div class="flex items-center justify-between py-2">
                        <span class="text-xs text-gray-500">Status</span>
                        <span class="text-xs font-bold px-2 py-0.5 rounded-md <?= $proposalColors[$proposal['status']] ?? '' ?>">
                            <?= ucfirst(sanitize_string($proposal['status'])) ?>
                        </span>
                    </div>
                </div>
            </div>

            <a href="proposals.php" class="flex items-center justify-center gap-2 w-full py-3 border border-gray-200 text-gray-600 hover:border-blue-300 hover:text-blue-600 rounded-xl text-sm font-semibold transition-all">
                <i class="fas fa-arrow-left text-xs"></i> Back to Proposals
            </a>
        </div>
    </div>
<?php require_once __DIR__ . '/../components/layout_end.php'; ?>
