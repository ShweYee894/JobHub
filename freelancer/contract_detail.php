<?php
$activePage = 'contracts';
require_once __DIR__ . '/../auth/auth.php';
require_role('freelancer');
require_once __DIR__ . '/../config/db.php';
$userId = $_SESSION['user_id'];
$contractId = intval($_GET['id'] ?? 0);
if ($contractId <= 0) {
    set_flash('error', 'Invalid contract.');
    redirect('contracts.php');
}
$uStmt = $conn->prepare('SELECT name, profile_image FROM users WHERE id = ?');
$uStmt->bind_param('i', $userId);
$uStmt->execute();
$user = $uStmt->get_result()->fetch_assoc();
$uStmt->close();
$stmt = $conn->prepare('SELECT c.*, j.title AS job_title, j.budget AS job_budget, j.description AS job_desc, u.name AS client_name, u.email AS client_email FROM contracts c JOIN jobs j ON c.job_id = j.id JOIN users u ON c.client_id = u.id WHERE c.id = ? AND c.freelancer_id = ?');
$stmt->bind_param('ii', $contractId, $userId);
$stmt->execute();
$contract = $stmt->get_result()->fetch_assoc();
$stmt->close();
if (!$contract) {
    set_flash('error', 'Contract not found.');
    redirect('contracts.php');
}
$mStmt = $conn->prepare('SELECT * FROM milestones WHERE contract_id = ? ORDER BY sort_order ASC, created_at ASC');
$mStmt->bind_param('i', $contractId);
$mStmt->execute();
$milestones = $mStmt->get_result()->fetch_all(MYSQLI_ASSOC);
$mStmt->close();
$dStmt = $conn->prepare('SELECT d.*, u.name AS resolved_by_name FROM dispute_tickets d LEFT JOIN users u ON d.resolved_by = u.id WHERE d.contract_id = ? ORDER BY d.created_at DESC LIMIT 1');
$dStmt->bind_param('i', $contractId);
$dStmt->execute();
$latestDispute = $dStmt->get_result()->fetch_assoc();
$dStmt->close();
$wStmt = $conn->prepare('SELECT wallet_balance FROM users WHERE id = ?');
$wStmt->bind_param('i', $userId);
$wStmt->execute();
$walletBalance = (float) $wStmt->get_result()->fetch_assoc()['wallet_balance'];
$wStmt->close();

$totalMA = 0;
foreach ($milestones as $m) {
    $totalMA += (float) $m['amount'];
}
$mSC = ['pending' => 'bg-slate-100 text-slate-600', 'funded_in_escrow' => 'bg-amber-50 text-amber-700', 'submitted' => 'bg-indigo-50 text-indigo-700', 'released' => 'bg-emerald-50 text-emerald-700', 'disputed' => 'bg-rose-50 text-rose-700'];
$mIC = ['pending' => 'clock', 'funded_in_escrow' => 'shield', 'submitted' => 'send', 'released' => 'circle-check', 'disputed' => 'triangle-alert'];
$cC = ['active' => 'bg-emerald-50 text-emerald-700', 'completed' => 'bg-indigo-50 text-indigo-700', 'cancelled' => 'bg-slate-100 text-slate-600', 'disputed' => 'bg-rose-50 text-rose-700'];

$pageTitle = sanitize_string($contract['job_title']);
$pageSubtitle = 'Contract Details';
$activePage = 'contracts';
$user = ['name' => $user['name'] ?? 'Freelancer', 'profile_image' => $user['profile_image'] ?? null];
$unreadCount = get_unread_message_count($userId, 'freelancer');
require_once __DIR__ . '/../components/freelancer_header.php';
?>
<?php display_flash('success') ?>
<?php display_flash('error') ?>
<div class="space-y-6 max-w-7xl mx-auto px-4 sm:px-6 py-8">
    <div class="bg-white dark:bg-slate-800 rounded-2xl shadow-[0_1px_3px_rgba(0,0,0,.04),0_8px_24px_rgba(0,0,0,.03)] overflow-hidden fade-in">
        <div class="p-6 sm:p-8">
            <!-- Top Row: Title + Actions -->
            <div class="flex flex-col sm:flex-row sm:items-start justify-between gap-4 mb-6">
                <div class="flex-1 min-w-0">
                    <div class="flex items-center gap-2 mb-3">
                        <a href="contracts.php" class="w-8 h-8 rounded-lg bg-gray-50 dark:bg-slate-700 flex items-center justify-center text-gray-400 hover:text-gray-700 dark:hover:text-white hover:bg-gray-100 dark:hover:bg-slate-600 transition-colors flex-shrink-0">
                            <i data-lucide="arrow-left" class="text-xs"></i>
                        </a>
                        <span class="inline-flex items-center gap-1 px-2.5 py-1 rounded-full text-[10px] font-semibold tracking-wide uppercase <?= $cC[$contract['status']] ?? 'bg-slate-100 text-slate-600' ?>">
                            <?= sanitize_string(ucfirst($contract['status'])) ?>
                        </span>
                    </div>
                    <h1 class="text-2xl sm:text-3xl font-bold text-gray-900 dark:text-white leading-tight tracking-tight">
                        <?= sanitize_string($contract['job_title']) ?>
                    </h1>
                </div>
                <div class="flex flex-wrap items-center gap-2 flex-shrink-0">
                    <a href="contract_print.php?id=<?= $contractId ?>" class="inline-flex items-center gap-2 px-4 py-2.5 bg-[#4338CA] hover:bg-[#3730A3] text-white text-xs font-semibold rounded-xl transition-colors">
                        <i data-lucide="file-text" class="text-[10px]"></i> Official Contract
                    </a>
                    <?php if ($contract['status'] === 'active' && $contract['dispute_status'] === 'none'): ?>
                    <button onclick="openTerminateModal()" class="inline-flex items-center gap-1.5 px-3.5 py-2.5 bg-white dark:bg-slate-700 hover:bg-gray-50 dark:hover:bg-slate-600 text-gray-600 dark:text-slate-300 text-xs font-semibold rounded-xl transition-colors border border-gray-200 dark:border-slate-600">
                        <i data-lucide="circle-x" class="text-[10px] text-gray-400"></i> Terminate
                    </button>
                    <?php endif; ?>
                    <?php if ($contract['status'] === 'active' || $contract['status'] === 'disputed'): ?>
                    <button onclick="openDisputeModal()" class="inline-flex items-center gap-1.5 px-3.5 py-2.5 bg-white dark:bg-slate-700 hover:bg-gray-50 dark:hover:bg-slate-600 text-gray-600 dark:text-slate-300 text-xs font-semibold rounded-xl transition-colors border border-gray-200 dark:border-slate-600">
                        <i data-lucide="hammer" class="text-[10px] text-gray-400"></i> File Dispute
                    </button>
                    <?php endif; ?>
                    <?php if ($contract['dispute_status'] === 'open' && (int) $contract['cancelled_by'] === $userId): ?>
                    <span class="inline-flex items-center gap-1.5 px-3.5 py-2.5 bg-amber-50 dark:bg-amber-900/20 text-amber-600 dark:text-amber-400 text-xs font-semibold rounded-xl border border-amber-200 dark:border-amber-800">
                        <i data-lucide="clock" class="text-[10px]"></i> Termination Pending
                    </span>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Metadata Row -->
            <div class="flex flex-wrap items-center gap-x-6 gap-y-2 text-sm text-gray-400 dark:text-slate-400 mb-6">
                <span class="flex items-center gap-2">
                    <!-- <i data-lucide="dollar-sign" class="text-[11px] text-emerald-400"></i> -->
                    <span class="font-semibold text-gray-700 dark:text-slate-300"><?= format_currency((float) $contract['total_budget']) ?></span>
                </span>
                <span class="flex items-center gap-2">
                    <i data-lucide="user" class="text-[11px] text-gray-300 dark:text-slate-500"></i>
                    <span class="text-gray-500 dark:text-slate-400"><?= sanitize_string($contract['client_name']) ?></span>
                </span>
                <span class="flex items-center gap-2">
                    <i data-lucide="calendar" class="text-[11px] text-gray-300 dark:text-slate-500"></i>
                    <span class="text-gray-400 dark:text-slate-500">Started <?= date('M d, Y', strtotime($contract['created_at'])) ?></span>
                </span>
            </div>

            <!-- Key Stats -->
            <div class="bg-gray-50 dark:bg-slate-700/30 rounded-2xl p-5 sm:p-6">
                <div class="grid grid-cols-3 gap-6">
                    <div class="text-center sm:text-left">
                        <p class="text-[10px] font-semibold text-gray-400 dark:text-slate-500 uppercase tracking-wider mb-1">Total Value</p>
                        <p class="text-xl font-bold text-gray-900 dark:text-white tracking-tight"><?= format_currency((float) $contract['total_budget']) ?></p>
                    </div>
                    <div class="text-center sm:text-left border-x border-gray-200/60 dark:border-slate-600/40 px-6">
                        <p class="text-[10px] font-semibold text-gray-400 dark:text-slate-500 uppercase tracking-wider mb-1">Milestones</p>
                        <p class="text-xl font-bold text-gray-900 dark:text-white tracking-tight"><?= count($milestones) ?></p>
                    </div>
                    <div class="text-center sm:text-left">
                        <p class="text-[10px] font-semibold text-gray-400 dark:text-slate-500 uppercase tracking-wider mb-1">Your Wallet</p>
                        <p class="text-xl font-bold text-gray-900 dark:text-white tracking-tight"><?= format_currency($walletBalance) ?></p>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <?php if ($latestDispute && in_array($latestDispute['status'], ['resolved', 'dismissed'])): ?>
    <div class="relative bg-white dark:bg-slate-800 rounded-2xl shadow-[0_1px_3px_rgba(0,0,0,.04),0_8px_24px_rgba(0,0,0,.03)] overflow-hidden fade-in" style="animation-delay:.05s">
        <!-- Accent left border -->
        <div class="absolute left-0 top-0 bottom-0 w-1 <?= $latestDispute['status'] === 'resolved' ? 'bg-emerald-400' : 'bg-amber-400' ?>"></div>

        <div class="p-6 sm:p-8 pl-7 sm:pl-9">
            <div class="flex items-center gap-3 mb-6">
                <div class="w-10 h-10 rounded-xl <?= $latestDispute['status'] === 'resolved' ? 'bg-emerald-50 dark:bg-emerald-900/30' : 'bg-amber-50 dark:bg-amber-900/30' ?> flex items-center justify-center">
                    <i data-lucide="<?= $latestDispute['status'] === 'resolved' ? 'circle-check' : 'circle-x' ?>" class="<?= $latestDispute['status'] === 'resolved' ? 'text-emerald-500' : 'text-amber-500' ?>"></i>
                </div>
                <div>
                    <h3 class="text-base font-semibold text-gray-900 dark:text-white">Dispute <?= ucfirst($latestDispute['status']) ?></h3>
                    <p class="text-xs text-gray-400 dark:text-slate-500">Decision by <?= sanitize_string($latestDispute['resolved_by_name'] ?? 'Admin') ?> &middot; <?= date('M d, Y \a\t g:i A', strtotime($latestDispute['updated_at'])) ?></p>
                </div>
            </div>

            <div class="space-y-5">
                <!-- Reason -->
                <div>
                    <p class="text-[10px] font-bold text-gray-400 dark:text-slate-500 uppercase tracking-wider mb-2">Reason</p>
                    <span class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-full text-xs font-semibold bg-gray-100 dark:bg-slate-600 text-gray-700 dark:text-gray-300">
                        <?= sanitize_string(ucfirst(str_replace('_', ' ', $latestDispute['reason']))) ?>
                    </span>
                </div>

                <!-- Description Block -->
                <div class="bg-gray-50 dark:bg-slate-700/30 rounded-xl p-5 border border-gray-100 dark:border-slate-600/50">
                    <p class="text-[10px] font-bold text-gray-400 dark:text-slate-500 uppercase tracking-wider mb-3">
                        <i data-lucide="clipboard-list" class="mr-1"></i> <?= $latestDispute['raised_by'] == $userId ? 'Your' : 'Client' ?> Description
                    </p>
                    <p class="text-sm text-gray-600 dark:text-slate-300 leading-relaxed"><?= nl2br(sanitize_string($latestDispute['description'])) ?></p>
                </div>

                <?php if (!empty($latestDispute['resolution'])): ?>
                <!-- Decision Block -->
                <div class="bg-<?= $latestDispute['status'] === 'resolved' ? 'emerald' : 'amber' ?>-50/50 dark:bg-<?= $latestDispute['status'] === 'resolved' ? 'emerald' : 'amber' ?>-900/10 rounded-xl p-5 border border-<?= $latestDispute['status'] === 'resolved' ? 'emerald' : 'amber' ?>-100 dark:border-<?= $latestDispute['status'] === 'resolved' ? 'emerald' : 'amber' ?>-800/30">
                    <p class="text-[10px] font-bold text-<?= $latestDispute['status'] === 'resolved' ? 'emerald' : 'amber' ?>-600 dark:text-<?= $latestDispute['status'] === 'resolved' ? 'emerald' : 'amber' ?>-400 uppercase tracking-wider mb-3">
                        <i data-lucide="hammer" class="mr-1"></i> Admin Decision
                    </p>
                    <p class="text-sm text-gray-600 dark:text-slate-300 leading-relaxed"><?= nl2br(sanitize_string($latestDispute['resolution'])) ?></p>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
    <?php endif; ?>
    <div class="bg-white dark:bg-slate-800 rounded-2xl shadow-[0_1px_3px_rgba(0,0,0,.04),0_8px_24px_rgba(0,0,0,.03)] fade-in" style="animation-delay:.1s">
        <div class="p-6 sm:p-8">
            <div class="flex items-center gap-3 mb-8">
                <div class="w-10 h-10 rounded-xl bg-indigo-50 dark:bg-indigo-900/30 flex items-center justify-center">
                    <i data-lucide="list-checks" class="text-indigo-500"></i>
                </div>
                <div>
                    <h3 class="text-base font-semibold text-gray-900 dark:text-white">Milestones</h3>
                    <p class="text-xs text-gray-400 dark:text-slate-500"><?= count($milestones) ?> milestone<?= count($milestones) !== 1 ? 's' : '' ?></p>
                </div>
            </div>
            <?php if (!empty($milestones)): ?>
                <div class="relative">
                    <!-- Timeline connecting line -->
                    <div class="absolute left-5 top-0 bottom-0 w-px bg-gray-200 dark:bg-slate-700"></div>

                    <div class="space-y-0">
                        <?php foreach ($milestones as $i => $m): ?>
                            <?php
                            $isReleased = $m['status'] === 'released';
                            $isDisputed = $m['status'] === 'disputed';
                            $isSubmitted = $m['status'] === 'submitted';
                            $isPending = $m['status'] === 'pending';
                            $isFunded = $m['status'] === 'funded_in_escrow';
                            ?>
                            <div class="relative flex gap-5 pb-8 last:pb-0" id="milestone-<?= (int) $m['id'] ?>">
                                <!-- Timeline indicator -->
                                <div class="relative z-10 flex-shrink-0">
                                    <?php if ($isReleased): ?>
                                        <div class="w-10 h-10 rounded-full bg-emerald-50 dark:bg-emerald-900/30 flex items-center justify-center ring-4 ring-white dark:ring-slate-800">
                                            <i data-lucide="check" class="text-emerald-500 text-sm"></i>
                                        </div>
                                    <?php elseif ($isDisputed): ?>
                                        <div class="w-10 h-10 rounded-full bg-rose-50 dark:bg-rose-900/30 flex items-center justify-center ring-4 ring-white dark:ring-slate-800">
                                            <i data-lucide="triangle-alert" class="text-rose-500 text-sm"></i>
                                        </div>
                                    <?php elseif ($isSubmitted): ?>
                                        <div class="w-10 h-10 rounded-full bg-indigo-50 dark:bg-indigo-900/30 flex items-center justify-center ring-4 ring-white dark:ring-slate-800">
                                            <i data-lucide="send" class="text-indigo-500 text-sm"></i>
                                        </div>
                                    <?php elseif ($isFunded): ?>
                                        <div class="w-10 h-10 rounded-full bg-amber-50 dark:bg-amber-900/30 flex items-center justify-center ring-4 ring-white dark:ring-slate-800">
                                            <i data-lucide="shield" class="text-amber-500 text-sm"></i>
                                        </div>
                                    <?php else: ?>
                                        <div class="w-10 h-10 rounded-full bg-gray-100 dark:bg-slate-700 flex items-center justify-center ring-4 ring-white dark:ring-slate-800">
                                            <span class="text-sm font-semibold text-gray-400 dark:text-slate-500"><?= $i + 1 ?></span>
                                        </div>
                                    <?php endif; ?>
                                </div>

                                <!-- Milestone card -->
                                <div class="flex-1 bg-gray-50/80 dark:bg-slate-700/20 rounded-2xl p-6 border border-gray-100 dark:border-slate-600/50 hover:bg-gray-50 dark:hover:bg-slate-700/30 transition-colors">
                                    <div class="flex flex-col sm:flex-row sm:items-start gap-4">
                                        <div class="flex-1 min-w-0">
                                            <div class="flex flex-wrap items-center gap-2.5 mb-2">
                                                <h4 class="text-[15px] font-semibold text-gray-900 dark:text-white"><?= sanitize_string($m['title']) ?></h4>
                                                <span class="inline-flex items-center gap-1 px-2.5 py-1 rounded-full text-[10px] font-semibold tracking-wide uppercase <?= $mSC[$m['status']] ?? 'bg-slate-100 text-slate-600' ?>">
                                                    <i data-lucide="<?= $mIC[$m['status']] ?? 'circle' ?>" class="text-[8px]"></i>
                                                    <?= sanitize_string(ucfirst(str_replace('_', ' ', $m['status']))) ?>
                                                </span>
                                            </div>
                                            <div class="flex flex-wrap items-center gap-4 text-xs text-gray-400 dark:text-slate-500">
                                                <span class="font-semibold text-gray-700 dark:text-slate-300 text-sm"><?= format_currency((float) $m['amount']) ?></span>
                                                <span><?= date('M d, Y', strtotime($m['created_at'])) ?></span>
                                                <?php if (!empty($m['due_date'])): ?>
                                                    <span class="flex items-center gap-1 <?php
            $dueDate = new DateTime($m['due_date']);
            $now = new DateTime();
            $diff = $now->diff($dueDate);
            if ($dueDate < $now && !in_array($m['status'], ['released', 'disputed'])) {
                echo 'text-rose-500 font-semibold';
            } elseif ($diff->days <= 3 && !in_array($m['status'], ['released', 'disputed'])) {
                echo 'text-amber-500 font-semibold';
            } else {
                echo 'text-gray-400 dark:text-slate-500';
            }
            ?>"><i data-lucide="calendar"></i> Due: <?= date('M d, Y', strtotime($m['due_date'])) ?></span>
                                                <?php endif; ?>
                                            </div>
                                            <?php if (!empty($m['description'])): ?>
                                                <p class="text-xs text-gray-500 dark:text-slate-400 mt-2 leading-relaxed"><?= nl2br(sanitize_string($m['description'])) ?></p>
                                            <?php endif; ?>
                                            <?php if ($m['status'] === 'funded_in_escrow'): ?>
                                                <p class="text-xs text-amber-600 dark:text-amber-400 mt-3 flex items-center gap-1.5"><i data-lucide="info"></i>Payment is held in escrow. Submit your work when ready.</p>
                                            <?php endif; ?>
                                            <?php if ($m['status'] === 'submitted'): ?>
                                                <div class="mt-4 p-4 bg-indigo-50/50 dark:bg-indigo-900/20 rounded-xl border border-indigo-100 dark:border-indigo-800/30">
                                                    <p class="text-xs font-semibold text-indigo-700 dark:text-indigo-400 mb-2 flex items-center gap-1.5"><i data-lucide="check-check"></i>Work Submitted</p>
                                                    <?php if (!empty($m['submission_github_url'])): ?>
                                                        <p class="text-xs text-indigo-600 dark:text-indigo-400 flex items-center gap-1.5 mb-1.5">
                                                            <svg class="w-4 h-4" fill="currentColor" viewBox="0 0 24 24"><path d="M12 0c-6.626 0-12 5.373-12 12 0 5.302 3.438 9.8 8.207 11.387.599.111.793-.261.793-.577v-2.234c-3.338.726-4.033-1.416-4.033-1.416-.546-1.387-1.333-1.756-1.333-1.756-1.089-.745.083-.729.083-.729 1.205.084 1.839 1.237 1.839 1.237 1.07 1.834 2.807 1.304 3.492.997.107-.775.418-1.305.762-1.604-2.665-.305-5.467-1.334-5.467-5.931 0-1.311.469-2.381 1.236-3.221-.124-.303-.535-1.524.117-3.176 0 0 1.008-.322 3.301 1.23.957-.266 1.983-.399 3.003-.404 1.02.005 2.047.138 3.006.404 2.291-1.552 3.297-1.23 3.297-1.23.653 1.653.242 2.874.118 3.176.77.84 1.235 1.911 1.235 3.221 0 4.609-2.807 5.624-5.479 5.921.43.372.823 1.102.823 2.222v3.293c0 .319.192.694.801.576 4.765-1.589 8.199-6.086 8.199-11.386 0-6.627-5.373-12-12-12z"/></svg>
                                                            <a href="<?= sanitize_string($m['submission_github_url']) ?>" target="_blank" rel="noopener" class="hover:underline truncate max-w-md"><?= sanitize_string($m['submission_github_url']) ?></a>
                                                        </p>
                                                    <?php endif; ?>
                                                    <?php if (!empty($m['submission_note'])): ?>
                                                        <p class="text-xs text-gray-600 dark:text-slate-300 mt-1.5 leading-relaxed"><?= nl2br(sanitize_string($m['submission_note'])) ?></p>
                                                    <?php endif; ?>
                                                    <?php if ($m['submission_date']): ?>
                                                        <p class="text-[11px] text-gray-400 dark:text-slate-500 mt-2"><i data-lucide="clock" class="mr-1"></i>Submitted <?= time_ago($m['submission_date']) ?></p>
                                                    <?php endif; ?>
                                                </div>
                                                <p class="text-xs text-indigo-600 dark:text-indigo-400 mt-2 flex items-center gap-1.5"><i data-lucide="clock"></i>Awaiting client review.</p>
                                            <?php endif; ?>
                                            <?php if ($m['status'] === 'released'): ?>
                                                <p class="text-xs text-emerald-600 dark:text-emerald-400 mt-3 flex items-center gap-1.5"><i data-lucide="circle-check"></i>Payment released to your wallet.</p>
                                            <?php endif; ?>
                                            <?php if ($m['status'] === 'disputed'): ?>
                                                <p class="text-xs text-rose-600 dark:text-rose-400 mt-3 flex items-center gap-1.5"><i data-lucide="triangle-alert"></i>This milestone is under dispute.</p>
                                            <?php endif; ?>
                                        </div>
                                        <div class="flex flex-wrap gap-2 flex-shrink-0">
                                            <?php if ($m['status'] === 'funded_in_escrow'): ?>
                                                <a href="submit_milestone.php?id=<?= (int) $m['id'] ?>" class="inline-flex items-center gap-1.5 px-4 py-2 btn-grad text-white text-xs font-semibold rounded-xl">
                                                    <i data-lucide="send" class="text-[10px]"></i> Submit Work
                                                </a>
                                            <?php endif; ?>
                                            <?php if ($m['status'] !== 'released' && $m['status'] !== 'disputed' && $m['status'] !== 'pending'): ?>
                                                <button onclick="disputeMilestone(<?= (int) $m['id'] ?>)" class="inline-flex items-center gap-1.5 px-3.5 py-2 bg-white dark:bg-slate-700 hover:bg-gray-50 dark:hover:bg-slate-600 text-gray-600 dark:text-slate-300 text-xs font-semibold rounded-xl transition-colors border border-gray-200 dark:border-slate-600">
                                                    <i data-lucide="flag" class="text-[10px] text-gray-400"></i> Dispute
                                                </button>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            <?php else: ?>
                <div class="text-center py-16">
                    <div class="w-16 h-16 rounded-2xl bg-gray-50 dark:bg-slate-700 flex items-center justify-center mx-auto mb-4">
                        <i data-lucide="list-checks" class="text-2xl text-gray-300 dark:text-slate-600"></i>
                    </div>
                    <p class="text-gray-500 dark:text-slate-400 text-sm font-medium mb-1">No milestones yet</p>
                    <p class="text-gray-400 dark:text-slate-500 text-xs">The client will add milestones to this contract</p>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <div id="loadingOverlay" class="fixed inset-0 z-[60] hidden modal-overlay flex items-center justify-center">
        <div class="bg-white dark:bg-slate-800 rounded-2xl p-8 shadow-2xl text-center">
            <div class="w-12 h-12 border-4 border-indigo-200 border-t-[#4338CA] rounded-full animate-spin mx-auto mb-4"></div>
            <p class="text-sm font-semibold text-gray-700 dark:text-slate-200">Processing...</p>
        </div>
    </div>

    <div id="disputeModal" class="fixed inset-0 z-50 hidden">
        <div class="modal-overlay absolute inset-0 bg-black/40" onclick="closeDisputeModal()"></div>
        <div class="absolute inset-0 flex items-center justify-center p-4">
            <div class="bg-white dark:bg-slate-800 rounded-2xl shadow-2xl w-full max-w-md relative z-10" style="transform:scale(.95) translateY(10px);transition:transform .25s ease">
                <div class="p-6 border-b border-gray-100 dark:border-slate-700">
                    <div class="flex items-center justify-between">
                        <h3 class="text-lg font-bold text-gray-900 dark:text-white">File a Dispute</h3>
                        <button onclick="closeDisputeModal()" class="w-8 h-8 rounded-lg bg-gray-100 dark:bg-slate-700 flex items-center justify-center text-gray-400 hover:text-gray-600 dark:hover:text-slate-200"><i data-lucide="x" class="text-sm"></i></button>
                    </div>
                </div>
                <form id="disputeForm" onsubmit="return submitDispute(event)">
                    <div class="p-6 space-y-4">
                        <?= csrf_field() ?>
                        <input type="hidden" name="action" value="create">
                        <input type="hidden" name="contract_id" value="<?= (int) $contract['id'] ?>">
                        <div>
                            <label class="block text-xs font-semibold text-gray-700 dark:text-slate-300 mb-1.5">Related Milestone (optional)</label>
                            <select name="milestone_id" class="w-full px-4 py-2.5 rounded-xl border border-gray-200 dark:border-slate-600 text-sm bg-gray-50 dark:bg-slate-700 focus:bg-white dark:focus:bg-slate-600 dark:text-white">
                                <option value="">None - Contract-level dispute</option>
                                <?php foreach ($milestones as $m): ?>
                                    <option value="<?= (int) $m['id'] ?>"><?= sanitize_string($m['title']) ?> (<?= format_currency((float) $m['amount']) ?>)</option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div>
                            <label class="block text-xs font-semibold text-gray-700 dark:text-slate-300 mb-1.5">Reason</label>
                            <select name="reason" required class="w-full px-4 py-2.5 rounded-xl border border-gray-200 dark:border-slate-600 text-sm bg-gray-50 dark:bg-slate-700 focus:bg-white dark:focus:bg-slate-600 dark:text-white">
                                <option value="">Select a reason...</option>
                                <option value="non_delivery">Non-Delivery</option>
                                <option value="quality_issue">Quality Issue</option>
                                <option value="scope_dispute">Scope Dispute</option>
                                <option value="payment_issue">Payment Issue</option>
                                <option value="other">Other</option>
                            </select>
                        </div>
                        <div>
                            <label class="block text-xs font-semibold text-gray-700 dark:text-slate-300 mb-1.5">Description</label>
                            <textarea name="description" required rows="4" minlength="20" maxlength="5000" placeholder="Provide a detailed description of the issue (min 20 characters)..." class="w-full px-4 py-2.5 rounded-xl border border-gray-200 dark:border-slate-600 text-sm bg-gray-50 dark:bg-slate-700 focus:bg-white dark:focus:bg-slate-600 dark:text-white resize-none"></textarea>
                        </div>
                    </div>
                    <div class="px-6 pb-6 flex gap-3">
                        <button type="button" onclick="closeDisputeModal()" class="flex-1 px-4 py-2.5 rounded-xl border border-gray-200 text-sm font-semibold text-gray-600 hover:bg-gray-50">Cancel</button>
                        <button type="submit" class="flex-1 px-4 py-2.5 rounded-xl bg-red-500 hover:bg-red-600 text-white text-sm font-semibold shadow-lg shadow-red-500/25">Submit Dispute</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <div id="terminateModal" class="fixed inset-0 z-50 hidden">
        <div class="modal-overlay absolute inset-0 bg-black/40" onclick="closeTerminateModal()"></div>
        <div class="absolute inset-0 flex items-center justify-center p-4">
            <div class="bg-white dark:bg-slate-800 rounded-2xl shadow-2xl w-full max-w-md relative z-10" style="transform:scale(.95) translateY(10px);transition:transform .25s ease">
                <div class="p-6 border-b border-gray-100 dark:border-slate-700">
                    <div class="flex items-center justify-between">
                        <h3 class="text-lg font-bold text-gray-900 dark:text-white">Request Contract Termination</h3>
                        <button onclick="closeTerminateModal()" class="w-8 h-8 rounded-lg bg-gray-100 dark:bg-slate-700 flex items-center justify-center text-gray-400 hover:text-gray-600 dark:hover:text-slate-200"><i data-lucide="x" class="text-sm"></i></button>
                    </div>
                </div>
                <form id="terminateForm" onsubmit="return submitTermination(event)">
                    <div class="p-6 space-y-4">
                        <?= csrf_field() ?>
                        <input type="hidden" name="action" value="request_termination">
                        <input type="hidden" name="contract_id" value="<?= (int) $contract['id'] ?>">
                        <div class="bg-amber-50 dark:bg-amber-900/20 rounded-xl p-4 border border-amber-200 dark:border-amber-800">
                            <div class="flex items-start gap-3">
                                <i data-lucide="triangle-alert" class="text-amber-500 mt-0.5"></i>
                                <div class="text-xs text-amber-700 dark:text-amber-400">
                                    <p class="font-semibold mb-1">Important:</p>
                                    <p>Requesting termination will notify the client. They can accept or reject your request. If rejected, you may file a dispute.</p>
                                </div>
                            </div>
                        </div>
                        <div>
                            <label class="block text-xs font-semibold text-gray-700 dark:text-slate-300 mb-1.5">Reason for Termination</label>
                            <textarea name="reason" required rows="4" minlength="20" maxlength="500" placeholder="Explain why you want to terminate this contract (min 20 characters)..." class="w-full px-4 py-2.5 rounded-xl border border-gray-200 dark:border-slate-600 text-sm bg-gray-50 dark:bg-slate-700 focus:bg-white dark:focus:bg-slate-600 dark:text-white resize-none"></textarea>
                        </div>
                    </div>
                    <div class="px-6 pb-6 flex gap-3">
                        <button type="button" onclick="closeTerminateModal()" class="flex-1 px-4 py-2.5 rounded-xl border border-gray-200 text-sm font-semibold text-gray-600 hover:bg-gray-50">Cancel</button>
                        <button type="submit" class="flex-1 px-4 py-2.5 rounded-xl bg-amber-500 hover:bg-amber-600 text-white text-sm font-semibold shadow-lg shadow-amber-500/25">Submit Request</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <div id="disputeConfirmModal" class="fixed inset-0 z-50 hidden">
        <div class="modal-overlay absolute inset-0 bg-black/40" onclick="closeDisputeConfirmModal()"></div>
        <div class="absolute inset-0 flex items-center justify-center p-4">
            <div class="bg-white dark:bg-slate-800 rounded-2xl shadow-2xl w-full max-w-sm relative z-10" style="transform:scale(.95) translateY(10px);transition:transform .25s ease">
                <div class="p-6 text-center">
                    <div class="w-14 h-14 rounded-2xl bg-rose-100 dark:bg-rose-900/30 flex items-center justify-center mx-auto mb-4">
                        <i data-lucide="flag" class="text-rose-500"></i>
                    </div>
                    <h3 class="text-base font-bold text-gray-900 dark:text-white mb-2">Dispute Milestone</h3>
                    <p class="text-sm text-gray-500 dark:text-slate-400">Are you sure you want to dispute this milestone? An admin will review.</p>
                </div>
                <div class="px-6 pb-6 flex gap-3">
                    <button onclick="closeDisputeConfirmModal()" class="flex-1 px-4 py-2.5 rounded-xl border border-gray-200 dark:border-slate-600 text-sm font-semibold text-gray-600 dark:text-slate-300 hover:bg-gray-50 dark:hover:bg-slate-700 transition-colors">Cancel</button>
                    <button onclick="confirmDispute()" id="disputeConfirmBtn" class="flex-1 px-4 py-2.5 rounded-xl bg-rose-500 hover:bg-rose-600 text-white text-sm font-semibold shadow-lg shadow-rose-500/25 transition-colors"><span id="disputeConfirmBtnText">Confirm Dispute</span></button>
                </div>
            </div>
        </div>
    </div>
</div>
<script>
    var CSRF_TOKEN = '<?= generate_csrf_token() ?>';
    var BASE_URL = '/jobhub';

    function showLoading() {
        document.getElementById('loadingOverlay').classList.remove('hidden')
    }

    function hideLoading() {
        document.getElementById('loadingOverlay').classList.add('hidden')
    }

    function showToast(type, message) {
        var c = {
            success: 'bg-emerald-500',
            error: 'bg-red-500',
            info: 'bg-[#4338CA]'
        };
        var ic = {
            success: 'circle-check',
            error: 'circle-alert',
            info: 'info'
        };
        var t = document.createElement('div');
        t.className = 'fixed top-4 right-4 z-[70] flex items-center gap-3 px-5 py-3 rounded-xl text-white text-sm font-semibold shadow-2xl ' + c[type] + ' transition-all transform translate-x-full';
        t.innerHTML = '<i data-lucide="' + ic[type] + '"></i> ' + message;
        document.body.appendChild(t);
        lucide.createIcons();
        requestAnimationFrame(function() {
            t.classList.remove('translate-x-full')
        });
        setTimeout(function() {
            t.classList.add('translate-x-full');
            setTimeout(function() {
                t.remove()
            }, 300)
        }, 3500);
    }

    function reloadPage() {
        setTimeout(function() {
            location.reload()
        }, 1200)
    }
    var pendingDisputeMilestoneId = null;

    function disputeMilestone(id) {
        pendingDisputeMilestoneId = id;
        document.getElementById('disputeConfirmModal').classList.remove('hidden');
        lucide.createIcons();
    }

    function closeDisputeConfirmModal() {
        document.getElementById('disputeConfirmModal').classList.add('hidden');
        pendingDisputeMilestoneId = null;
    }

    async function confirmDispute() {
        var id = pendingDisputeMilestoneId;
        if (!id) return;
        closeDisputeConfirmModal();
        showLoading();
        try {
            var fd = new FormData();
            fd.append('action', 'dispute');
            fd.append('milestone_id', id);
            fd.append('csrf_token', CSRF_TOKEN);
            var r = await fetch(BASE_URL + '/api/milestones_api.php', {
                method: 'POST',
                body: fd
            });
            var j = await r.json();
            hideLoading();
            if (j.success) {
                showToast('info', j.message);
                reloadPage()
            } else {
                showToast('error', j.message)
            }
        } catch (err) {
            hideLoading();
            showToast('error', 'Network error.')
        }
    }

    function openDisputeModal() {
        document.getElementById('disputeModal').classList.remove('hidden')
    }

    function closeDisputeModal() {
        document.getElementById('disputeModal').classList.add('hidden')
    }
    async function submitDispute(e) {
        e.preventDefault();
        var fd = new FormData(document.getElementById('disputeForm'));
        fd.append('csrf_token', CSRF_TOKEN);
        showLoading();
        try {
            var r = await fetch(BASE_URL + '/api/dispute_api.php', {
                method: 'POST',
                body: fd
            });
            var j = await r.json();
            hideLoading();
            if (j.success) {
                showToast('success', j.message);
                closeDisputeModal();
                reloadPage()
            } else {
                showToast('error', j.message)
            }
        } catch (err) {
            hideLoading();
            showToast('error', 'Network error.')
        }
        return false
    }

    function openTerminateModal() {
        document.getElementById('terminateModal').classList.remove('hidden')
    }

    function closeTerminateModal() {
        document.getElementById('terminateModal').classList.add('hidden')
    }

    async function submitTermination(e) {
        e.preventDefault();
        var fd = new FormData(document.getElementById('terminateForm'));
        fd.append('csrf_token', CSRF_TOKEN);
        showLoading();
        try {
            var r = await fetch(BASE_URL + '/freelancer/contract_action.php', {
                method: 'POST',
                body: fd
            });
            var j = await r.json();
            hideLoading();
            if (j.success) {
                showToast('success', j.message);
                closeTerminateModal();
                reloadPage()
            } else {
                showToast('error', j.message)
            }
        } catch (err) {
            hideLoading();
            showToast('error', 'Network error.')
        }
        return false
    }
</script>
<?php $conn->close(); ?>
<?php require_once __DIR__ . '/../components/freelancer_footer.php'; ?>