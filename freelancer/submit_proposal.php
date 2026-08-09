<?php
require_once __DIR__ . '/../auth/auth.php';
require_role('freelancer');
require_once __DIR__ . '/../config/db.php';

$userId = $_SESSION['user_id'];

$stmt = $conn->prepare('SELECT name, profile_image FROM users WHERE id = ?');
$stmt->bind_param('i', $userId);
$stmt->execute();
$user = $stmt->get_result()->fetch_assoc();
$stmt->close();

$jobId = sanitize_int($_GET['job_id'] ?? $_POST['job_id'] ?? 0);

if ($jobId <= 0) {
    set_flash('error', 'Invalid job reference.');
    redirect('/jobhub/freelancer/browse_jobs.php');
}

$stmt = $conn->prepare('SELECT id, title, description, budget, status, created_at FROM jobs WHERE id = ?');
$stmt->bind_param('i', $jobId);
$stmt->execute();
$job = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$job || $job['status'] !== 'open') {
    set_flash('error', 'This job is no longer available for proposals.');
    redirect('/jobhub/freelancer/browse_jobs.php');
}

$stmt = $conn->prepare('SELECT id FROM proposals WHERE job_id = ? AND freelancer_id = ?');
$stmt->bind_param('ii', $jobId, $userId);
$stmt->execute();
$existing = $stmt->get_result()->fetch_assoc();
$stmt->close();

if ($existing) {
    set_flash('warning', 'You have already submitted a proposal for this job.');
    redirect('/jobhub/freelancer/proposal_detail.php?id=' . $existing['id']);
}

$errors = [];
$proposalText = '';
$amount = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf_token()) {
        $errors[] = 'Invalid security token. Please try again.';
    } else {
        $proposalText = trim($_POST['proposal_text'] ?? '');
        $amount = trim($_POST['amount'] ?? '');

        if ($proposalText === '') {
            $errors[] = 'Proposal message is required.';
        } elseif (strlen($proposalText) < 20) {
            $errors[] = 'Proposal message must be at least 20 characters.';
        }

        $amountFloat = sanitize_float($amount);
        if ($amountFloat <= 0) {
            $errors[] = 'Bid amount must be greater than zero.';
        } elseif ($amountFloat > 999999.99) {
            $errors[] = 'Bid amount is too large.';
        }

        if (empty($errors)) {
            $conn->begin_transaction();

            try {
                $ins = $conn->prepare(
                    'INSERT INTO proposals (job_id, freelancer_id, proposal_text, amount, status) VALUES (?, ?, ?, ?, ?)'
                );
                $status = 'pending';
                $ins->bind_param('iisss', $jobId, $userId, $proposalText, $amountFloat, $status);
                $ins->execute();

                if ($ins->affected_rows > 0) {
                    $newProposalId = $ins->insert_id;
                    $conn->commit();
                    $ins->close();

                    // Notify the job owner about the new proposal
                    require_once __DIR__ . '/../shared/notification_helper.php';
                    $stmtOwner = $conn->prepare('SELECT client_id FROM jobs WHERE id = ?');
                    $stmtOwner->bind_param('i', $jobId);
                    $stmtOwner->execute();
                    $owner = $stmtOwner->get_result()->fetch_assoc();
                    $stmtOwner->close();
                    if ($owner) {
                        notifyNewProposal($owner['client_id'], $job['title'], $jobId);
                    }

                    set_flash('success', 'Your proposal has been submitted successfully!');
                    redirect('/jobhub/freelancer/proposal_detail.php?id=' . $newProposalId);
                } else {
                    throw new Exception('Failed to submit proposal.');
                }
            } catch (Exception $e) {
                $conn->rollback();
                $errors[] = 'A database error occurred. You may have already submitted a proposal for this job.';
            }
            if (isset($ins)) $ins->close();
        }
    }
}

$statusColors = [
    'open'        => 'bg-emerald-50 text-emerald-600 border border-emerald-200',
    'in_progress' => 'bg-blue-50 text-blue-600 border border-blue-200',
    'completed'   => 'bg-purple-50 text-purple-600 border border-purple-200',
    'cancelled'   => 'bg-gray-100 text-gray-600 border border-gray-200',
    'disputed'    => 'bg-red-50 text-red-600 border border-red-200',
];

$pageTitle = 'Submit Proposal';
$pageSubtitle = 'Submit your proposal for this job';
$activePage = 'browse_jobs';
$user = ['name' => $user['name'] ?? 'Freelancer', 'profile_image' => $user['profile_image'] ?? null];
$unreadCount = get_unread_message_count($userId, 'freelancer');
require_once __DIR__ . '/../components/freelancer_header.php';
?>
            <?php display_flash('success'); display_flash('error'); display_flash('warning'); ?>

            <?php if (!empty($errors)): ?>
            <div class="rounded-2xl p-4 bg-red-50 border border-red-200 mb-6 fade-in">
                <div class="flex items-start gap-3">
                    <div class="w-10 h-10 rounded-xl bg-red-100 flex items-center justify-center flex-shrink-0">
                        <i data-lucide="circle-alert" class="text-red-500 text-lg"></i>
                    </div>
                    <div>
                        <p class="font-bold text-red-700 text-sm">Please fix the following errors:</p>
                        <ul class="mt-2 space-y-1">
                            <?php foreach ($errors as $err): ?>
                            <li class="text-red-600/80 text-xs flex items-center gap-1.5">
                                <i data-lucide="circle-x" class="text-[10px]"></i> <?= sanitize_string($err) ?>
                            </li>
                            <?php endforeach; ?>
                        </ul>
                    </div>
                </div>
            </div>
            <?php endif; ?>

            <div class="flex flex-col xl:flex-row gap-6">

                <div class="flex-1 min-w-0 space-y-6">

                    <div class="bg-white rounded-2xl border border-gray-100 shadow-sm p-6 fade-in">
                        <div class="flex items-center gap-3 mb-4">
                            <div class="w-10 h-10 rounded-xl bg-indigo-50 flex items-center justify-center">
                                <i data-lucide="briefcase" class="text-indigo-500 text-sm"></i>
                            </div>
                            <div>
                                <h2 class="text-base font-bold text-gray-900"><?= decode_over_encoded($job['title']) ?></h2>
                                <p class="text-xs text-gray-400">Job Budget: <span class="font-semibold text-gray-600"><?= format_currency((float)$job['budget']) ?></span></p>
                            </div>
                        </div>
                        <div class="bg-gray-50 rounded-xl p-4 border border-gray-100">
                            <p class="text-sm text-gray-600 leading-relaxed whitespace-pre-line line-clamp-4"><?= nl2br(sanitize_string($job['description'])) ?></p>
                        </div>
                    </div>

                    <div class="bg-white rounded-2xl border border-gray-100 shadow-sm p-6 fade-in" style="animation-delay:.1s">
                        <h3 class="text-base font-bold text-gray-900 mb-5 flex items-center gap-2">
                            <i data-lucide="send" class="text-indigo-500 text-sm"></i> Your Proposal
                        </h3>
                        <form method="POST" class="space-y-5">
                            <?= csrf_field() ?>
                            <input type="hidden" name="job_id" value="<?= (int)$jobId ?>">

                            <div>
                                <label class="block text-xs font-semibold text-gray-700 mb-1.5">Bid Amount ($) <span class="text-red-500">*</span></label>
                                <div class="relative">
                                    <div class="absolute left-4 top-1/2 -translate-y-1/2 flex items-center justify-center w-6 h-6 rounded-lg bg-emerald-100">
                                        <i data-lucide="dollar-sign" class="text-emerald-600 text-xs"></i>
                                    </div>
                                    <input type="number" name="amount" step="0.01" min="0.01" required
                                        value="<?= sanitize_string($amount) ?>"
                                        placeholder="0.00"
                                        class="fld w-full bg-gray-50 border border-gray-200 rounded-xl pl-12 pr-4 py-3 text-sm text-gray-900 placeholder-gray-400">
                                </div>
                                <p class="text-[11px] text-gray-400 mt-1">Client budget: <span class="font-semibold text-gray-600"><?= format_currency((float)$job['budget']) ?></span></p>
                            </div>

                            <div>
                                <label class="block text-xs font-semibold text-gray-700 mb-1.5">Proposal Message <span class="text-red-500">*</span></label>
                                <textarea name="proposal_text" rows="8" required
                                    placeholder="Explain why you're the best fit for this project. Mention relevant experience, your approach, estimated timeline..."
                                    class="fld w-full bg-gray-50 border border-gray-200 rounded-xl px-4 py-3 text-sm text-gray-900 placeholder-gray-400 resize-none"><?= sanitize_string($proposalText) ?></textarea>
                                <p class="text-[11px] text-gray-400 mt-1">Minimum 20 characters</p>
                            </div>

                            <div class="flex gap-3 pt-2">
                                <button type="submit" class="flex-1 btn-grad py-3.5 text-white text-sm font-bold rounded-xl shadow-lg shadow-indigo-500/25 flex items-center justify-center gap-2">
                                    <i data-lucide="send" class="text-xs"></i> Submit Proposal
                                </button>
                                <a href="browse_jobs.php" class="px-6 py-3.5 border border-gray-200 text-gray-600 hover:border-gray-300 rounded-xl text-sm font-semibold transition-all flex items-center justify-center gap-2">
                                    Cancel
                                </a>
                            </div>
                        </form>
                    </div>
                </div>

                <div class="w-full xl:w-80 flex-shrink-0 space-y-5">
                    <div class="bg-white rounded-2xl border border-gray-100 shadow-sm p-6 fade-in" style="animation-delay:.15s">
                        <h3 class="text-sm font-bold text-gray-900 mb-4 flex items-center gap-2">
                            <i data-lucide="receipt" class="text-violet-500 text-xs"></i> Job Summary
                        </h3>
                        <div class="space-y-3">
                            <div class="flex items-center justify-between py-2 border-b border-gray-50">
                                <span class="text-xs text-gray-500">Budget</span>
                                <span class="text-sm font-bold text-gray-900"><?= format_currency((float)$job['budget']) ?></span>
                            </div>
                            <div class="flex items-center justify-between py-2 border-b border-gray-50">
                                <span class="text-xs text-gray-500">Status</span>
                                <span class="text-xs font-bold px-2 py-0.5 rounded-md <?= $statusColors[$job['status']] ?? '' ?>">
                                    <?= ucfirst(str_replace('_', ' ', $job['status'])) ?>
                                </span>
                            </div>
                            <div class="flex items-center justify-between py-2">
                                <span class="text-xs text-gray-500">Posted</span>
                                <span class="text-xs font-semibold text-gray-700"><?= time_ago($job['created_at']) ?></span>
                            </div>
                        </div>
                    </div>

                    <div class="bg-white rounded-2xl border border-gray-100 shadow-sm p-6 fade-in" style="animation-delay:.2s">
                        <h3 class="text-sm font-bold text-gray-900 mb-4 flex items-center gap-2">
                            <i data-lucide="lightbulb" class="text-amber-500 text-xs"></i> Tips for a Winning Proposal
                        </h3>
                        <ul class="space-y-3">
                            <li class="flex items-start gap-2.5 text-xs text-gray-600">
                                <i data-lucide="circle-check" class="text-emerald-500 mt-0.5 text-[10px]"></i>
                                <span>Address the client's specific needs</span>
                            </li>
                            <li class="flex items-start gap-2.5 text-xs text-gray-600">
                                <i data-lucide="circle-check" class="text-emerald-500 mt-0.5 text-[10px]"></i>
                                <span>Showcase relevant past work</span>
                            </li>
                            <li class="flex items-start gap-2.5 text-xs text-gray-600">
                                <i data-lucide="circle-check" class="text-emerald-500 mt-0.5 text-[10px]"></i>
                                <span>Provide a clear timeline</span>
                            </li>
                            <li class="flex items-start gap-2.5 text-xs text-gray-600">
                                <i data-lucide="circle-check" class="text-emerald-500 mt-0.5 text-[10px]"></i>
                                <span>Be competitive but fair with pricing</span>
                            </li>
                        </ul>
                    </div>

                    <a href="browse_jobs.php" class="flex items-center justify-center gap-2 w-full py-3 border border-gray-200 text-gray-600 hover:border-indigo-300 hover:text-indigo-600 rounded-xl text-sm font-semibold transition-all">
                        <i data-lucide="arrow-left" class="text-xs"></i> Back to Browse Jobs
                    </a>
                </div>
            </div>
<?php require_once __DIR__ . '/../components/freelancer_footer.php'; ?>
