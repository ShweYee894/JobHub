<?php
require_once __DIR__ . '/../auth/auth.php';
require_role('client');
require_once __DIR__ . '/../config/db.php';

$currentPage = 'proposals';
$userId = $_SESSION['user_id'];
$proposalId = sanitize_int($_GET['id'] ?? 0);

$stmt = $conn->prepare('SELECT name, profile_image FROM users WHERE id = ?');
$stmt->bind_param('i', $userId);
$stmt->execute();
$user = $stmt->get_result()->fetch_assoc();
$stmt->close();

if ($proposalId <= 0) {
    set_flash('error', 'Invalid proposal reference.');
    redirect('/jobhub/client/proposals.php');
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
    redirect('/jobhub/client/proposals.php');
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
    WHERE r.reviewee_id = ? AND COALESCE(r.is_hidden, 0) = 0
');
$stmt->bind_param('i', $proposal['freelancer_user_id']);
$stmt->execute();
$ratingData = $stmt->get_result()->fetch_assoc();
$stmt->close();

$proposalColors = [
    'pending' => 'bg-amber-50 text-amber-600 border border-amber-200',
    'accepted' => 'bg-emerald-50 text-emerald-600 border border-emerald-200',
    'rejected' => 'bg-red-50 text-red-500 border border-red-200',
    'withdrawn' => 'bg-gray-100 text-gray-500 border border-gray-200',
];

$statusColors = [
    'open' => 'bg-emerald-50 text-emerald-600 border border-emerald-200',
    'in_progress' => 'bg-blue-50 text-blue-600 border border-blue-200',
    'completed' => 'bg-purple-50 text-purple-600 border border-purple-200',
    'cancelled' => 'bg-gray-100 text-gray-600 border border-gray-200',
    'disputed' => 'bg-red-50 text-red-600 border border-red-200',
];

$pageTitle = 'Review Proposal – JobHub';
$pageSubtitle = 'Review proposal from ' . sanitize_string($proposal['freelancer_name']);
$activePage = 'proposals';
$user = ['name' => $user['name'] ?? 'Client', 'profile_image' => $user['profile_image'] ?? null];
$unreadCount = get_unread_message_count($userId, 'client');
$profileLink = 'profile.php';
require_once __DIR__ . '/../includes/client_topbar.php';
?>
    <?php display_flash('success');
    display_flash('error'); ?>
<main class="max-w-7xl mx-auto px-4 sm:px-6 py-8 min-h-screen">
    <div class="flex flex-col xl:flex-row gap-6">

        <div class="flex-1 min-w-0 space-y-6">

            <div class="bg-white rounded-2xl border border-slate-200/80 shadow-sm p-6 fade-in">
                <div class="flex flex-wrap items-center gap-3 mb-4">
                    <h2 class="text-xl font-bold text-slate-900 tracking-tight"><?= sanitize_string($proposal['job_title']) ?></h2>
                    <span class="inline-flex items-center px-3 py-1 rounded-full text-xs font-medium ring-1 <?= match($proposal['job_status']) { 'open' => 'bg-emerald-50 text-emerald-700 ring-emerald-600/20', 'in_progress' => 'bg-blue-50 text-blue-700 ring-blue-600/20', 'completed' => 'bg-purple-50 text-purple-700 ring-purple-600/20', 'cancelled' => 'bg-slate-100 text-slate-600 ring-slate-500/20', 'disputed' => 'bg-red-50 text-red-700 ring-red-600/20', default => 'bg-slate-100 text-slate-600 ring-slate-500/20' } ?>">
                        <?= ucfirst(str_replace('_', ' ', $proposal['job_status'])) ?>
                    </span>
                </div>
                <div class="p-5 bg-slate-50/80 border border-slate-100 rounded-xl">
                    <p class="text-sm text-slate-600 leading-relaxed whitespace-pre-line line-clamp-6"><?= nl2br(sanitize_string($proposal['job_description'])) ?></p>
                </div>
            </div>

            <div class="bg-white rounded-2xl border border-slate-200/80 shadow-sm p-6 fade-in" style="animation-delay:.1s">
                <div class="flex items-center justify-between mb-5">
                    <h3 class="text-lg font-bold text-slate-900 tracking-tight flex items-center gap-2">
                        <i data-lucide="send" class="w-4 h-4 text-indigo-500"></i> Proposal Details
                    </h3>
                    <span class="inline-flex items-center px-3 py-1 rounded-full text-xs font-medium ring-1 <?= match($proposal['status']) { 'pending' => 'bg-amber-50 text-amber-700 ring-amber-600/20', 'accepted' => 'bg-emerald-50 text-emerald-700 ring-emerald-600/20', 'rejected' => 'bg-red-50 text-red-700 ring-red-600/20', 'withdrawn' => 'bg-slate-100 text-slate-600 ring-slate-500/20', default => 'bg-slate-100 text-slate-600 ring-slate-500/20' } ?>">
                        <?= ucfirst(sanitize_string($proposal['status'])) ?>
                    </span>
                </div>

                <div class="space-y-4">
                    <div class="p-5 bg-slate-50/80 border border-slate-100 rounded-xl">
                        <h4 class="text-xs font-semibold tracking-wider text-slate-400 uppercase mb-3">Cover Letter</h4>
                        <p class="text-sm text-slate-600 leading-relaxed whitespace-pre-line"><?= nl2br(sanitize_string($proposal['proposal_text'])) ?></p>
                    </div>

                    <div class="grid grid-cols-3 gap-4">
                        <div class="bg-slate-50/80 border border-slate-100 rounded-xl p-4 text-center">
                            <p class="text-[11px] font-semibold text-slate-400 uppercase tracking-wider mb-1">Bid Amount</p>
                            <p class="text-xl font-bold text-slate-900"><?= format_currency((float) $proposal['amount']) ?></p>
                        </div>
                        <div class="bg-slate-50/80 border border-slate-100 rounded-xl p-4 text-center">
                            <p class="text-[11px] font-semibold text-slate-400 uppercase tracking-wider mb-1">Job Budget</p>
                            <p class="text-xl font-bold text-slate-900"><?= format_currency((float) $proposal['job_budget']) ?></p>
                        </div>
                        <div class="bg-slate-50/80 border border-slate-100 rounded-xl p-4 text-center">
                            <p class="text-[11px] font-semibold text-slate-400 uppercase tracking-wider mb-1">Difference</p>
                            <?php $diff = (float) $proposal['job_budget'] - (float) $proposal['amount']; ?>
                            <p class="text-xl font-bold <?= $diff >= 0 ? 'text-emerald-600' : 'text-red-500' ?>">
                                <?= $diff >= 0 ? '-' : '+' ?><?= format_currency(abs($diff)) ?>
                            </p>
                        </div>
                    </div>

                    <div class="flex items-center gap-4 text-xs text-slate-400 font-medium">
                        <span class="flex items-center gap-1.5">
                            <i data-lucide="calendar" class="w-3.5 h-3.5"></i>
                            Submitted <?= date('M d, Y \a\t g:i A', strtotime($proposal['created_at'])) ?>
                        </span>
                        <span class="flex items-center gap-1.5">
                            <i data-lucide="clock" class="w-3.5 h-3.5"></i>
                            <?= time_ago($proposal['created_at']) ?>
                        </span>
                    </div>
                </div>

                <?php if ($proposal['status'] === 'pending'): ?>
                <div class="mt-6 pt-5 border-t border-slate-100">
                    <div class="flex flex-wrap gap-3">
                        <form method="POST" action="accept_proposal.php" onsubmit="return confirm('Accept this proposal? This will hire the freelancer and reject all other pending proposals for this job.');">
                            <?= csrf_field() ?>
                            <input type="hidden" name="proposal_id" value="<?= (int) $proposal['id'] ?>">
                            <button type="submit" class="inline-flex items-center gap-2 px-6 py-3 bg-indigo-600 hover:bg-indigo-700 text-white text-sm font-semibold rounded-xl shadow-sm hover:shadow-md transition-all duration-200">
                                 <i data-lucide="check" class="w-4 h-4"></i> Accept Proposal
                            </button>
                        </form>
                        <form method="POST" action="reject_proposal.php" onsubmit="return confirm('Are you sure you want to reject this proposal?');">
                            <?= csrf_field() ?>
                            <input type="hidden" name="proposal_id" value="<?= (int) $proposal['id'] ?>">
                            <button type="submit" class="inline-flex items-center gap-2 px-6 py-3 bg-white hover:bg-red-50 text-red-600 text-sm font-semibold rounded-xl transition-all border border-slate-200 hover:border-red-300">
                                <i data-lucide="x" class="w-4 h-4"></i> Reject Proposal
                            </button>
                        </form>
                    </div>
                </div>
                <?php endif; ?>
                <?php $conn->close(); ?>
            </div>
        </div>

        <div class="w-full xl:w-96 flex-shrink-0 space-y-5">
            <div class="bg-white rounded-2xl border border-slate-200/80 shadow-sm p-6 fade-in" style="animation-delay:.15s">
                <h3 class="text-lg font-bold text-slate-900 tracking-tight mb-4 flex items-center gap-2">
                    <i data-lucide="user" class="w-4 h-4 text-indigo-500"></i> Freelancer Profile
                </h3>

                <div class="flex items-center gap-3 mb-5">
                    <?php
                    $fAvatar = !empty($proposal['freelancer_avatar'])
                        ? '../' . htmlspecialchars($proposal['freelancer_avatar'])
                        : 'https://ui-avatars.com/api/?name=' . urlencode($proposal['freelancer_name']) . '&background=4338CA&color=fff&bold=true&size=80';
                    ?>
                    <img src="<?= $fAvatar ?>" class="w-14 h-14 rounded-full object-cover ring-2 ring-white shadow-sm" alt="Freelancer">
                    <div>
                        <p class="font-bold text-slate-900 text-sm"><?= sanitize_string($proposal['freelancer_name']) ?></p>
                        <p class="text-xs text-slate-400 font-medium"><?= sanitize_string($freelancerProfile['title'] ?? 'Freelancer') ?></p>
                        <?php if ($ratingData['avg_rating']): ?>
                        <div class="flex items-center gap-1 mt-1">
                            <div class="flex items-center gap-0.5">
                                <?php for ($i = 1; $i <= 5; $i++): ?>
                                <i data-lucide="star" class="w-3 h-3 <?= $i <= round($ratingData['avg_rating']) ? 'text-amber-400 fill-amber-400' : 'text-slate-200' ?>"></i>
                                <?php endfor; ?>
                            </div>
                            <span class="text-[10px] text-slate-400 font-medium"><?= number_format($ratingData['avg_rating'], 1) ?> (<?= $ratingData['review_count'] ?>)</span>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>

                <div class="space-y-3 mb-5">
                    <div class="flex items-center justify-between py-2.5 border-b border-slate-100">
                        <span class="text-xs text-slate-400 font-medium">Hourly Rate</span>
                        <span class="text-sm font-bold text-slate-900"><?= format_currency((float) ($freelancerProfile['hourly_rate'] ?? 0)) ?>/hr</span>
                    </div>
                    <div class="flex items-center justify-between py-2.5 border-b border-slate-100">
                        <span class="text-xs text-slate-400 font-medium">Experience</span>
                        <span class="text-sm font-semibold text-slate-700"><?= (int) ($freelancerProfile['years_of_experience'] ?? 0) ?> years</span>
                    </div>
                    <div class="flex items-center justify-between py-2.5">
                        <span class="text-xs text-slate-400 font-medium">Availability</span>
                        <span class="text-xs font-semibold <?= ($freelancerProfile['availability'] ?? '') === 'Available' ? 'text-emerald-600' : 'text-amber-600' ?>">
                            <?= sanitize_string($freelancerProfile['availability'] ?? 'N/A') ?>
                        </span>
                    </div>
                </div>

                <?php if (!empty($freelancerProfile['bio'])): ?>
                <div class="mb-5">
                    <h4 class="text-xs font-semibold tracking-wider text-slate-400 uppercase mb-2">Bio</h4>
                    <p class="text-xs text-slate-600 leading-relaxed line-clamp-4"><?= sanitize_string($freelancerProfile['bio']) ?></p>
                </div>
                <?php endif; ?>

                <?php if (!empty($freelancerSkills)): ?>
                <div>
                    <h4 class="text-xs font-semibold tracking-wider text-slate-400 uppercase mb-2">Skills</h4>
                    <div class="flex flex-wrap gap-1.5">
                        <?php foreach ($freelancerSkills as $sk): ?>
                        <span class="inline-flex items-center px-3 py-1 bg-slate-100 text-slate-700 text-xs font-medium rounded-lg border border-slate-200/60">
                            <?= sanitize_string($sk['skill_name']) ?>
                        </span>
                        <?php endforeach; ?>
                    </div>
                </div>
                <?php endif; ?>
            </div>

            <div class="bg-white rounded-2xl border border-slate-200/80 shadow-sm p-6 fade-in" style="animation-delay:.2s">
                <h3 class="text-lg font-bold text-slate-900 tracking-tight mb-4 flex items-center gap-2">
                    <i data-lucide="receipt" class="w-4 h-4 text-indigo-500"></i> Quick Summary
                </h3>
                <div class="space-y-3">
                    <div class="flex items-center justify-between py-2.5 border-b border-slate-100 px-1">
                        <span class="text-xs text-slate-400 font-medium">Bid Amount</span>
                        <span class="text-sm font-bold text-slate-900"><?= format_currency((float) $proposal['amount']) ?></span>
                    </div>
                    <div class="flex items-center justify-between py-2.5 border-b border-slate-100 px-1">
                        <span class="text-xs text-slate-400 font-medium">Job Budget</span>
                        <span class="text-sm font-bold text-slate-900"><?= format_currency((float) $proposal['job_budget']) ?></span>
                    </div>
                    <div class="flex items-center justify-between py-2.5 border-b border-slate-100 px-1">
                        <span class="text-xs text-slate-400 font-medium">Savings</span>
                        <span class="text-sm font-bold <?= $diff >= 0 ? 'text-emerald-600' : 'text-red-500' ?>">
                            <?= $diff >= 0 ? format_currency($diff) : 'Over budget' ?>
                        </span>
                    </div>
                    <div class="flex items-center justify-between py-2.5 px-1">
                        <span class="text-xs text-slate-400 font-medium">Status</span>
                        <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium ring-1 <?= match($proposal['status']) { 'pending' => 'bg-amber-50 text-amber-700 ring-amber-600/20', 'accepted' => 'bg-emerald-50 text-emerald-700 ring-emerald-600/20', 'rejected' => 'bg-red-50 text-red-700 ring-red-600/20', 'withdrawn' => 'bg-slate-100 text-slate-600 ring-slate-500/20', default => 'bg-slate-100 text-slate-600 ring-slate-500/20' } ?>">
                            <?= ucfirst(sanitize_string($proposal['status'])) ?>
                        </span>
                    </div>
                </div>
            </div>

            <a href="proposals.php" class="flex items-center justify-center gap-2 w-full py-3 bg-white border border-slate-200/80 text-slate-600 hover:border-indigo-300 hover:text-indigo-600 rounded-xl text-sm font-semibold transition-all shadow-sm">
                <i data-lucide="arrow-left" class="w-4 h-4"></i> Back to Proposals
            </a>
        </div>
    </div>
</main>
<?php require_once __DIR__ . '/../includes/client_footer.php'; ?>
