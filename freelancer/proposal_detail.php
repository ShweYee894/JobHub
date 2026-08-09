<?php
require_once __DIR__ . '/../auth/auth.php';
require_role('freelancer');
require_once __DIR__ . '/../config/db.php';

$userId = $_SESSION['user_id'];
$proposalId = sanitize_int($_GET['id'] ?? 0);

if ($proposalId <= 0) {
    set_flash('error', 'Invalid proposal reference.');
    redirect('/jobhub/freelancer/proposals.php');
}

$stmt = $conn->prepare('SELECT name, profile_image FROM users WHERE id = ?');
$stmt->bind_param('i', $userId);
$stmt->execute();
$user = $stmt->get_result()->fetch_assoc();
$stmt->close();

$stmt = $conn->prepare('
    SELECT p.id, p.job_id, p.freelancer_id, p.proposal_text, p.amount, p.status, p.created_at, p.updated_at,
           j.title AS job_title, j.description AS job_description, j.budget AS job_budget,
           j.status AS job_status, j.client_id, j.created_at AS job_created_at
    FROM proposals p
    JOIN jobs j ON p.job_id = j.id
    WHERE p.id = ?
');
$stmt->bind_param('i', $proposalId);
$stmt->execute();
$proposal = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$proposal || $proposal['freelancer_id'] != $userId) {
    set_flash('error', 'Proposal not found or access denied.');
    redirect('/jobhub/freelancer/proposals.php');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'withdraw') {
    if (!verify_csrf_token()) {
        set_flash('error', 'Invalid security token.');
        redirect('/jobhub/freelancer/proposal_detail.php?id=' . $proposalId);
    }

    if ($proposal['status'] === 'pending') {
        $upd = $conn->prepare("UPDATE proposals SET status = 'withdrawn' WHERE id = ? AND freelancer_id = ? AND status = 'pending'");
        $upd->bind_param('ii', $proposalId, $userId);
        $upd->execute();

        if ($upd->affected_rows > 0) {
            $upd->close();
            set_flash('success', 'Your proposal has been withdrawn.');
            redirect('/jobhub/freelancer/proposals.php');
        } else {
            set_flash('error', 'Unable to withdraw proposal.');
        }
    } else {
        set_flash('error', 'Only pending proposals can be withdrawn.');
    }
    redirect('/jobhub/freelancer/proposal_detail.php?id=' . $proposalId);
}

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

$pageTitle = 'Proposal Details';
$pageSubtitle = 'View proposal details';
$activePage = 'proposals';
$user = ['name' => $user['name'] ?? 'Freelancer', 'profile_image' => $user['profile_image'] ?? null];
$unreadCount = get_unread_message_count($userId, 'freelancer');
require_once __DIR__ . '/../components/freelancer_header.php';
?>
<?php display_flash('success');
display_flash('error'); ?>
<div class="space-y-6 max-w-7xl mx-auto px-4 sm:px-6 py-8">
    <div class="flex flex-col xl:flex-row gap-6">

        <div class="flex-1 min-w-0 space-y-6">

            <div class="bg-white rounded-2xl border border-gray-100 shadow-sm p-6 fade-in" style="animation-delay:.1s">
                <div class="flex items-center justify-between mb-5">
                    <h3 class="text-base font-bold text-gray-900 flex items-center gap-2">
                        <i data-lucide="send" class="text-indigo-500 text-sm"></i> Your Proposal
                    </h3>
                    <span class="inline-block px-3 py-1 rounded-lg text-[11px] font-bold <?= $proposalColors[$proposal['status']] ?? '' ?>">
                        <?= ucfirst(sanitize_string($proposal['status'])) ?>
                    </span>
                </div>

                <div class="space-y-4">
                    <div class="bg-gray-50 rounded-xl p-5 border border-gray-100">
                        <h4 class="text-xs font-bold text-gray-400 uppercase tracking-wider mb-3">Proposal Message</h4>
                        <p class="text-sm text-gray-700 leading-relaxed whitespace-pre-line "><?= nl2br(sanitize_string($proposal['proposal_text'])) ?></p>
                    </div>

                    <div class="grid grid-cols-2 gap-4">
                        <div class="bg-gray-50 rounded-xl p-4 border border-gray-100 text-center">
                            <p class="text-[10px] text-gray-400 uppercase tracking-wider font-semibold mb-1">Your Bid</p>
                            <p class="text-xl font-black text-gray-900"><?= format_currency((float) $proposal['amount']) ?></p>
                        </div>
                        <div class="bg-gray-50 rounded-xl p-4 border border-gray-100 text-center">
                            <p class="text-[10px] text-gray-400 uppercase tracking-wider font-semibold mb-1">Job Budget</p>
                            <p class="text-xl font-black text-gray-900"><?= format_currency((float) $proposal['job_budget']) ?></p>
                        </div>
                    </div>

                    <div class="flex items-center gap-4 text-xs text-gray-400">
                        <span class="flex items-center gap-1.5">
                            <i data-lucide="calendar" class="text-indigo-400"></i>
                            Submitted <?= date('M d, Y \a\t g:i A', strtotime($proposal['created_at'])) ?>
                        </span>
                        <span class="flex items-center gap-1.5">
                            <i data-lucide="clock" class="text-gray-400"></i>
                            <?= time_ago($proposal['created_at']) ?>
                        </span>
                    </div>
                </div>

                <?php if ($proposal['status'] === 'pending'): ?>
                    <div class="mt-6 pt-5 border-t border-gray-100">
                        <p class="text-xs text-gray-500 mb-3">Need to change your proposal? Edit it or withdraw and submit a new one.</p>
                        <div class="flex flex-wrap items-center gap-3">
                            <a href="edit_proposal.php?id=<?= (int) $proposal['id'] ?>"
                               class="inline-flex items-center gap-2 px-5 py-2.5 bg-indigo-50 hover:bg-indigo-100 text-indigo-600 text-xs font-semibold rounded-xl transition-all border border-indigo-200">
                                <i data-lucide="pencil" class="text-[10px]"></i> Edit Proposal
                            </a>
                            <form method="POST" onsubmit="return confirm('Are you sure you want to withdraw this proposal? This action cannot be undone.');">
                                <?= csrf_field() ?>
                                <input type="hidden" name="action" value="withdraw">
                                <button type="submit" class="inline-flex items-center gap-2 px-5 py-2.5 bg-red-50 hover:bg-red-100 text-red-500 text-xs font-semibold rounded-xl transition-all border border-red-200">
                                    <i data-lucide="undo" class="text-[10px]"></i> Withdraw Proposal
                                </button>
                            </form>
                        </div>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <div class="w-full xl:w-80 flex-shrink-0 space-y-5">

            <div class="bg-white rounded-2xl border border-gray-100 shadow-sm p-6 fade-in">
                <div class="flex flex-wrap items-center gap-2 mb-4">
                    <h2 class="text-xl font-bold text-gray-900"><?= sanitize_string($proposal['job_title']) ?></h2>
                    <span class="inline-block px-3 py-1 rounded-lg text-[11px] font-semibold <?= $statusColors[$proposal['job_status']] ?? '' ?>">
                        <?= ucfirst(str_replace('_', ' ', $proposal['job_status'])) ?>
                    </span>
                </div>
                <div class="bg-gray-50 rounded-xl p-5 border border-gray-100">
                    <p class="text-sm text-gray-700 leading-relaxed whitespace-pre-line"><?= nl2br(sanitize_string($proposal['job_description'])) ?></p>
                </div>
            </div>
            <div class="bg-white rounded-2xl border border-gray-100 shadow-sm p-6 fade-in" style="animation-delay:.15s">
                <h3 class="text-sm font-bold text-gray-900 mb-4 flex items-center gap-2">
                    <i data-lucide="receipt" class="text-violet-500 text-xs"></i> Bid Summary
                </h3>
                <div class="space-y-3">
                    <div class="flex items-center justify-between py-2 border-b border-gray-50">
                        <span class="text-xs text-gray-500">Your Bid</span>
                        <span class="text-sm font-bold text-gray-900"><?= format_currency((float) $proposal['amount']) ?></span>
                    </div>
                    <div class="flex items-center justify-between py-2 border-b border-gray-50">
                        <span class="text-xs text-gray-500">Job Budget</span>
                        <span class="text-sm font-bold text-gray-900"><?= format_currency((float) $proposal['job_budget']) ?></span>
                    </div>
                    <div class="flex items-center justify-between py-2 border-b border-gray-50">
                        <span class="text-xs text-gray-500">Difference</span>
                        <?php $diff = (float) $proposal['job_budget'] - (float) $proposal['amount']; ?>
                        <span class="text-sm font-bold <?= $diff >= 0 ? 'text-emerald-600' : 'text-red-500' ?>">
                            <?= $diff >= 0 ? '-' : '+' ?><?= format_currency(abs($diff)) ?>
                        </span>
                    </div>
                    <div class="flex items-center justify-between py-2 border-b border-gray-50">
                        <span class="text-xs text-gray-500">Status</span>
                        <span class="text-xs font-bold px-2 py-0.5 rounded-md <?= $proposalColors[$proposal['status']] ?? '' ?>">
                            <?= ucfirst(sanitize_string($proposal['status'])) ?>
                        </span>
                    </div>
                    <div class="flex items-center justify-between py-2">
                        <span class="text-xs text-gray-500">Submitted</span>
                        <span class="text-xs font-semibold text-gray-700"><?= date('M d, Y', strtotime($proposal['created_at'])) ?></span>
                    </div>
                </div>
            </div>

            <div class="bg-white rounded-2xl border border-gray-100 shadow-sm p-6 fade-in" style="animation-delay:.2s">
                <h3 class="text-sm font-bold text-gray-900 mb-4 flex items-center gap-2">
                    <i data-lucide="info" class="text-indigo-500 text-xs"></i> Status Guide
                </h3>
                <ul class="space-y-3">
                    <li class="flex items-start gap-2.5 text-xs text-gray-600">
                        <span class="w-2 h-2 rounded-full bg-amber-400 mt-1.5 flex-shrink-0"></span>
                        <span><strong>Pending</strong> – Awaiting client review</span>
                    </li>
                    <li class="flex items-start gap-2.5 text-xs text-gray-600">
                        <span class="w-2 h-2 rounded-full bg-emerald-400 mt-1.5 flex-shrink-0"></span>
                        <span><strong>Accepted</strong> – Client hired you</span>
                    </li>
                    <li class="flex items-start gap-2.5 text-xs text-gray-600">
                        <span class="w-2 h-2 rounded-full bg-red-400 mt-1.5 flex-shrink-0"></span>
                        <span><strong>Rejected</strong> – Not selected</span>
                    </li>
                    <li class="flex items-start gap-2.5 text-xs text-gray-600">
                        <span class="w-2 h-2 rounded-full bg-gray-400 mt-1.5 flex-shrink-0"></span>
                        <span><strong>Withdrawn</strong> – You withdrew your proposal</span>
                    </li>
                </ul>
            </div>

            <a href="proposals.php" class="flex items-center justify-center gap-2 w-full py-3 border border-gray-200 text-gray-600 hover:border-indigo-300 hover:text-indigo-600 rounded-xl text-sm font-semibold transition-all">
                <i data-lucide="arrow-left" class="text-xs"></i> Back to Proposals
            </a>
        </div>
    </div>
</div>
<?php $conn->close(); ?>
<?php require_once __DIR__ . '/../components/freelancer_footer.php'; ?>