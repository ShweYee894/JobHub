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
    SELECT p.id, p.job_id, p.freelancer_id, p.proposal_text, p.amount, p.status, p.created_at,
           j.title AS job_title, j.description AS job_description, j.budget AS job_budget,
           j.status AS job_status
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

if ($proposal['status'] !== 'pending') {
    set_flash('error', 'Only pending proposals can be edited.');
    redirect('/jobhub/freelancer/proposal_detail.php?id=' . $proposalId);
}

$pageTitle = 'Edit Proposal';
$pageSubtitle = 'Update your proposal for this job';
$activePage = 'proposals';
$user = ['name' => $user['name'] ?? 'Freelancer', 'profile_image' => $user['profile_image'] ?? null];
$unreadCount = get_unread_message_count($userId, 'freelancer');
require_once __DIR__ . '/../components/freelancer_header.php';
?>
            <?php display_flash('success'); display_flash('error'); ?>

            <div class="flex flex-col xl:flex-row gap-6 max-w-7xl mx-auto px-4 sm:px-6 py-8">

                <div class="flex-1 min-w-0 space-y-6">

                    <div class="bg-white rounded-2xl border border-gray-100 shadow-sm p-6 fade-in">
                        <div class="flex items-center gap-3 mb-4">
                            <div class="w-10 h-10 rounded-xl bg-indigo-50 flex items-center justify-center">
                                <i data-lucide="briefcase" class="text-indigo-500 text-sm"></i>
                            </div>
                            <div>
                                <h2 class="text-base font-bold text-gray-900"><?= sanitize_string($proposal['job_title']) ?></h2>
                                <p class="text-xs text-gray-400">Job Budget: <span class="font-semibold text-gray-600"><?= format_currency((float)$proposal['job_budget']) ?></span></p>
                            </div>
                        </div>
                        <div class="bg-gray-50 rounded-xl p-4 border border-gray-100">
                            <p class="text-sm text-gray-600 leading-relaxed whitespace-pre-line line-clamp-4"><?= nl2br(sanitize_string($proposal['job_description'])) ?></p>
                        </div>
                    </div>

                    <div class="bg-white rounded-2xl border border-gray-100 shadow-sm p-6 fade-in" style="animation-delay:.1s">
                        <h3 class="text-base font-bold text-gray-900 mb-5 flex items-center gap-2">
                            <i data-lucide="pencil" class="text-indigo-500 text-sm"></i> Edit Your Proposal
                        </h3>
                        <form method="POST" action="update_proposal.php" class="space-y-5">
                            <?= csrf_field() ?>
                            <input type="hidden" name="proposal_id" value="<?= (int) $proposalId ?>">

                            <div>
                                <label class="block text-xs font-semibold text-gray-700 mb-1.5">Bid Amount ($) <span class="text-red-500">*</span></label>
                                <div class="relative">
                                    <div class="absolute left-4 top-1/2 -translate-y-1/2 flex items-center justify-center w-6 h-6 rounded-lg bg-emerald-100">
                                        <i data-lucide="dollar-sign" class="text-emerald-600 text-xs"></i>
                                    </div>
                                    <input type="number" name="amount" step="0.01" min="0.01" required
                                        value="<?= sanitize_string($proposal['amount']) ?>"
                                        placeholder="0.00"
                                        class="fld w-full bg-gray-50 border border-gray-200 rounded-xl pl-12 pr-4 py-3 text-sm text-gray-900 placeholder-gray-400">
                                </div>
                                <p class="text-[11px] text-gray-400 mt-1">Client budget: <span class="font-semibold text-gray-600"><?= format_currency((float)$proposal['job_budget']) ?></span></p>
                            </div>

                            <div>
                                <label class="block text-xs font-semibold text-gray-700 mb-1.5">Proposal Message <span class="text-red-500">*</span></label>
                                <textarea name="proposal_text" rows="8" required
                                    placeholder="Explain why you're the best fit for this project. Mention relevant experience, your approach, estimated timeline..."
                                    class="fld w-full bg-gray-50 border border-gray-200 rounded-xl px-4 py-3 text-sm text-gray-900 placeholder-gray-400 resize-none"><?= sanitize_string($proposal['proposal_text']) ?></textarea>
                                <p class="text-[11px] text-gray-400 mt-1">Minimum 20 characters</p>
                            </div>

                            <div class="flex gap-3 pt-2">
                                <button type="submit" class="flex-1 btn-grad py-3.5 text-white text-sm font-bold rounded-xl shadow-lg shadow-indigo-500/25 flex items-center justify-center gap-2">
                                    <i data-lucide="save" class="text-xs"></i> Save Changes
                                </button>
                                <a href="proposal_detail.php?id=<?= (int) $proposalId ?>" class="px-6 py-3.5 border border-gray-200 text-gray-600 hover:border-gray-300 rounded-xl text-sm font-semibold transition-all flex items-center justify-center gap-2">
                                    Cancel
                                </a>
                            </div>
                        </form>
                    </div>
                </div>

                <div class="w-full xl:w-80 flex-shrink-0 space-y-5">
                    <div class="bg-white rounded-2xl border border-gray-100 shadow-sm p-6 fade-in" style="animation-delay:.15s">
                        <h3 class="text-sm font-bold text-gray-900 mb-4 flex items-center gap-2">
                            <i data-lucide="receipt" class="text-violet-500 text-xs"></i> Current Bid Summary
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
                                <span class="text-xs text-gray-500">Status</span>
                                <span class="text-xs font-bold px-2 py-0.5 rounded-md bg-amber-50 text-amber-600 border border-amber-200">Pending</span>
                            </div>
                            <div class="flex items-center justify-between py-2">
                                <span class="text-xs text-gray-500">Submitted</span>
                                <span class="text-xs font-semibold text-gray-700"><?= date('M d, Y', strtotime($proposal['created_at'])) ?></span>
                            </div>
                        </div>
                    </div>

                    <div class="bg-white rounded-2xl border border-gray-100 shadow-sm p-6 fade-in" style="animation-delay:.2s">
                        <h3 class="text-sm font-bold text-gray-900 mb-4 flex items-center gap-2">
                            <i data-lucide="lightbulb" class="text-amber-500 text-xs"></i> Tips for Editing
                        </h3>
                        <ul class="space-y-3">
                            <li class="flex items-start gap-2.5 text-xs text-gray-600">
                                <i data-lucide="circle-check" class="text-emerald-500 mt-0.5 text-[10px]"></i>
                                <span>Update your bid to stay competitive</span>
                            </li>
                            <li class="flex items-start gap-2.5 text-xs text-gray-600">
                                <i data-lucide="circle-check" class="text-emerald-500 mt-0.5 text-[10px]"></i>
                                <span>Refine your cover letter based on job details</span>
                            </li>
                            <li class="flex items-start gap-2.5 text-xs text-gray-600">
                                <i data-lucide="circle-check" class="text-emerald-500 mt-0.5 text-[10px]"></i>
                                <span>Add specific timeline and deliverables</span>
                            </li>
                        </ul>
                    </div>

                    <a href="proposal_detail.php?id=<?= (int) $proposalId ?>" class="flex items-center justify-center gap-2 w-full py-3 border border-gray-200 text-gray-600 hover:border-indigo-300 hover:text-indigo-600 rounded-xl text-sm font-semibold transition-all">
                        <i data-lucide="arrow-left" class="text-xs"></i> Back to Proposal
                    </a>
                </div>
            </div>
<?php $conn->close(); ?>
<?php require_once __DIR__ . '/../components/freelancer_footer.php'; ?>
