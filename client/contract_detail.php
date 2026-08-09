<?php
$currentPage = 'contracts';
require_once __DIR__ . '/../auth/auth.php';
require_role('client');
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
$stmt = $conn->prepare('SELECT c.*, j.title AS job_title, j.budget AS job_budget, j.description AS job_desc, u.name AS freelancer_name, u.email AS freelancer_email FROM contracts c JOIN jobs j ON c.job_id = j.id JOIN freelancers f ON c.freelancer_id = f.user_id JOIN users u ON f.user_id = u.id WHERE c.id = ? AND c.client_id = ?');
$stmt->bind_param('ii', $contractId, $userId);
$stmt->execute();
$contract = $stmt->get_result()->fetch_assoc();
$stmt->close();
if (!$contract) {
    set_flash('error', 'Contract not found.');
    redirect('contracts.php');
}
$mStmt = $conn->prepare('SELECT * FROM milestones WHERE contract_id = ? ORDER BY created_at ASC');
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
$budgetRem = (float) $contract['total_budget'] - $totalMA;
$mSC = ['pending' => 'bg-gray-100 text-gray-600 border border-gray-200', 'funded_in_escrow' => 'bg-amber-50 text-amber-600 border border-amber-200', 'submitted' => 'bg-blue-50 text-blue-600 border border-blue-200', 'released' => 'bg-emerald-50 text-emerald-600 border border-emerald-200', 'disputed' => 'bg-red-50 text-red-500 border border-red-200'];
$mIC = ['pending' => 'clock', 'funded_in_escrow' => 'shield', 'submitted' => 'send', 'released' => 'circle-check', 'disputed' => 'triangle-alert'];
$cC = ['active' => 'bg-emerald-50 text-emerald-600 border border-emerald-200', 'completed' => 'bg-blue-50 text-blue-600 border border-blue-200', 'cancelled' => 'bg-gray-100 text-gray-500 border border-gray-200', 'disputed' => 'bg-red-50 text-red-500 border border-red-200'];

$pageTitle = sanitize_string($contract['job_title']) . ' - Contract Details';
$pageSubtitle = 'Manage milestones and payments';
$activePage = 'contracts';
$user = ['name' => $user['name'] ?? 'Client', 'profile_image' => $user['profile_image'] ?? null];
$unreadCount = get_unread_message_count($userId, 'client');
$profileLink = 'profile.php';
require_once __DIR__ . '/../includes/client_topbar.php';
?>
    <?php display_flash('success') ?>
    <?php display_flash('error') ?>
<main class="max-w-7xl mx-auto px-4 sm:px-6 py-8 flex flex-col gap-6">  
    <div class="bg-white dark:bg-slate-800 border border-[#E4EBE4] dark:border-slate-700 rounded-lg shadow-sm overflow-hidden fade-in">
        <div class="p-6">
            <div class="flex flex-col sm:flex-row sm:items-start justify-between gap-4 mb-5">
                <div class="flex-1">
                    <div class="flex flex-wrap items-center gap-2 mb-2">
                        <a href="contracts.php" class="text-gray-400 hover:text-gray-600 dark:text-slate-400 dark:hover:text-slate-200 transition-colors" title="Back to Contracts"><i data-lucide="arrow-left" class="w-4 h-4"></i></a>
                        <h2 class="text-xl font-bold text-gray-900 dark:text-white"><?= sanitize_string($contract['job_title']) ?></h2>
                        <span class="inline-block px-2.5 py-0.5 rounded text-[11px] font-semibold <?= $cC[$contract['status']] ?? '' ?>"><?= sanitize_string(ucfirst($contract['status'])) ?></span>
                    </div>
                    <div class="flex flex-wrap items-center gap-4 text-sm text-gray-400 dark:text-slate-400">
                        <span class="flex items-center gap-1.5"><i data-lucide="dollar-sign" class="w-4 h-4 text-emerald-500"></i><span class="font-bold text-gray-700 dark:text-slate-300"><?= format_currency((float) $contract['total_budget']) ?></span></span>
                        <span class="flex items-center gap-1.5"><i data-lucide="user" class="w-4 h-4 text-blue-400"></i><?= sanitize_string($contract['freelancer_name']) ?></span>
                        <span class="flex items-center gap-1.5"><i data-lucide="calendar" class="w-4 h-4 text-gray-400"></i>Started <?= date('M d, Y', strtotime($contract['created_at'])) ?></span>
                    </div>
                </div>
            </div>
            <div class="border-t border-gray-100 dark:border-slate-700"></div>
            <div class="grid grid-cols-2 sm:grid-cols-4 gap-px bg-gray-100 dark:bg-slate-700">
                <div class="bg-white dark:bg-slate-800 p-4">
                    <p class="text-[10px] font-semibold text-gray-400 dark:text-slate-500 uppercase tracking-wider">Total Budget</p>
                    <p class="text-sm font-bold text-gray-900 dark:text-white mt-1 text-left"><?= format_currency((float) $contract['total_budget']) ?></p>
                </div>
                <div class="bg-white dark:bg-slate-800 p-4">
                    <p class="text-[10px] font-semibold text-gray-400 dark:text-slate-500 uppercase tracking-wider">Allocated</p>
                    <p class="text-sm font-bold text-amber-600 dark:text-amber-400 mt-1 text-left"><?= format_currency($totalMA) ?></p>
                </div>
                <div class="bg-white dark:bg-slate-800 p-4">
                    <p class="text-[10px] font-semibold text-gray-400 dark:text-slate-500 uppercase tracking-wider">Remaining</p>
                    <p class="text-sm font-bold <?= $budgetRem >= 0 ? 'text-emerald-600 dark:text-emerald-400' : 'text-red-600 dark:text-red-400' ?> mt-1 text-left"><?= format_currency($budgetRem) ?></p>
                </div>
                <div class="bg-white dark:bg-slate-800 p-4">
                    <p class="text-[10px] font-semibold text-gray-400 dark:text-slate-500 uppercase tracking-wider">Your Wallet</p>
                    <p class="text-sm font-bold text-blue-600 dark:text-blue-400 mt-1 text-left"><?= format_currency($walletBalance) ?></p>
                </div>
            </div>
            <div class="flex flex-wrap items-center gap-3 mt-4">
                <a href="contract_print.php?id=<?= $contractId ?>" target="_blank" class="inline-flex items-center gap-2 px-4 py-2 bg-gradient-to-r from-[#3a5a40] to-[#588157] hover:from-[#2e4a33] hover:to-[#4a7049] text-white text-xs font-semibold rounded-xl shadow-lg shadow-green-500/25 transition-all">
                    <i data-lucide="file-text" class="w-4 h-4"></i> Official Contract
                </a>
                <?php if ($contract['status'] === 'active' || $contract['status'] === 'disputed'): ?>
                <button onclick="openDisputeModal()" class="inline-flex items-center gap-1.5 px-3 py-1.5 bg-red-50 hover:bg-red-100 text-red-500 text-xs font-semibold rounded-lg transition-all border border-red-200"><i data-lucide="triangle-alert" class="w-4 h-4"></i> File Dispute</button>
                <?php endif; ?>
            </div>
        </div>
    </div>
    <?php if ($latestDispute && in_array($latestDispute['status'], ['resolved', 'dismissed'])): ?>
    <div class="bg-white dark:bg-slate-800 rounded-2xl border border-gray-100 dark:border-slate-700 shadow-sm fade-in" style="animation-delay:.05s">
        <div class="p-6 sm:p-8">
            <div class="flex items-center gap-3 mb-5">
                <div class="w-10 h-10 rounded-xl <?= $latestDispute['status'] === 'resolved' ? 'bg-emerald-50 dark:bg-emerald-900/30' : 'bg-amber-50 dark:bg-amber-900/30' ?> flex items-center justify-center">
                    <i data-lucide="<?= $latestDispute['status'] === 'resolved' ? 'circle-check' : 'circle-x' ?>" class="w-5 h-5 <?= $latestDispute['status'] === 'resolved' ? 'text-emerald-500' : 'text-amber-500' ?>"></i>
                </div>
                <div>
                    <h3 class="text-base font-bold text-gray-900">Dispute <?= ucfirst($latestDispute['status']) ?></h3>
                    <p class="text-xs text-gray-400">Decision by <?= sanitize_string($latestDispute['resolved_by_name'] ?? 'Admin') ?> &middot; <?= date('M d, Y \a\t g:i A', strtotime($latestDispute['updated_at'])) ?></p>
                </div>
            </div>
            <div class="bg-gray-50 dark:bg-slate-700/50 rounded-xl p-4 border border-gray-100 dark:border-slate-600">
                <p class="flex items-center gap-1.5 text-[10px] font-bold text-gray-400 dark:text-slate-500 uppercase tracking-wider mb-2">
                    <i data-lucide="help-circle" class="w-4 h-4"></i> Reason
                </p>
                <span class="inline-block px-3 py-1 rounded-lg text-xs font-semibold bg-gray-100 dark:bg-slate-600 text-gray-700 dark:text-gray-300 mb-3">
                    <?= sanitize_string(ucfirst(str_replace('_', ' ', $latestDispute['reason']))) ?>
                </span>
                <p class="flex items-center gap-1.5 text-[10px] font-bold text-gray-400 dark:text-slate-500 uppercase tracking-wider mb-2">
                    <i data-lucide="clipboard-list" class="w-4 h-4"></i> <?= ((int) $latestDispute['raised_by'] === $userId) ? 'Your Description' : 'Freelancer Description' ?>
                </p>
                <p class="text-sm text-gray-700 dark:text-gray-300 leading-relaxed mb-3"><?= nl2br(sanitize_string($latestDispute['description'])) ?></p>
                <?php if (!empty($latestDispute['resolution'])): ?>
                <div class="border-t border-gray-200 dark:border-slate-600 pt-3 mt-3">
                    <p class="flex items-center gap-1.5 text-[10px] font-bold text-gray-400 dark:text-slate-500 uppercase tracking-wider mb-2">
                        <i data-lucide="shield" class="w-4 h-4"></i> Admin Decision
                    </p>
                    <p class="text-sm text-gray-700 dark:text-gray-300 leading-relaxed"><?= nl2br(sanitize_string($latestDispute['resolution'])) ?></p>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
    <?php endif; ?>
    <div class="bg-white dark:bg-slate-800 rounded-2xl border border-gray-100 dark:border-slate-700 shadow-sm fade-in" style="animation-delay:.1s">
        <div class="p-6 sm:p-8">
            <div class="flex items-center justify-between mb-6">
                <div class="flex items-center gap-3">
                    <div class="w-10 h-10 rounded-xl bg-violet-50 dark:bg-violet-900/30 flex items-center justify-center"><i data-lucide="list-checks" class="w-5 h-5 text-violet-500"></i></div>
                    <div>
                        <h3 class="text-base font-bold text-gray-900">Milestones</h3>
                        <p class="text-xs text-gray-400"><?= count($milestones) ?> milestone<?= count($milestones) !== 1 ? 's' : '' ?></p>
                    </div>
                </div>
                <?php if ($contract['status'] === 'active' && $budgetRem > 0): ?>
                    <button onclick="openModal('createModal')" class="btn-grad inline-flex items-center gap-2 text-white text-xs font-semibold px-4 py-2 rounded-xl shadow-lg shadow-blue-500/25"><i data-lucide="plus" class="w-4 h-4"></i> Add Milestone</button>
                <?php endif; ?>
            </div>
            <?php if (!empty($milestones)): ?>
                <div class="space-y-4">
                    <?php foreach ($milestones as $i => $m): ?>
                        <div class="border border-gray-100 rounded-xl p-5 hover:bg-gray-50/50 transition-colors" id="milestone-<?= (int) $m['id'] ?>">
                            <div class="flex flex-col sm:flex-row sm:items-center gap-4">
                                <div class="flex items-center justify-center w-10 h-10 rounded-xl bg-gradient-to-br from-blue-500 to-cyan-500 flex-shrink-0"><span class="text-white text-sm font-bold"><?= $i + 1 ?></span></div>
                                <div class="flex-1 min-w-0">
                                    <div class="flex flex-wrap items-center gap-2 mb-1">
                                        <h4 class="text-sm font-bold text-gray-900"><?= sanitize_string($m['title']) ?></h4>
                                         <span class="flex px-2.5 py-0.5 rounded-lg text-[10px] font-semibold <?= $mSC[$m['status']] ?? '' ?>"><i data-lucide="<?= $mIC[$m['status']] ?? 'circle' ?>" class="mr-1 w-3 h-3"></i> <?= sanitize_string(ucfirst(str_replace('_', ' ', $m['status']))) ?></span>
                                    </div>
                                    <?php if (!empty($m['description'])): ?>
                                        <p class="text-xs text-gray-500 dark:text-slate-400 mb-1.5 leading-relaxed"><?= nl2br(sanitize_string($m['description'])) ?></p>
                                    <?php endif; ?>
                                    <div class="flex items-center gap-4 text-xs text-gray-400">
                                        <span class="font-bold text-gray-700 text-sm"><?= format_currency((float) $m['amount']) ?></span>
                                        <span><?= date('M d, Y', strtotime($m['created_at'])) ?></span>
                                        <?php if (!empty($m['due_date'])): ?>
                                            <span class="flex items-center gap-1 <?php
            $dueDate = new DateTime($m['due_date']);
            $now = new DateTime();
            $diff = $now->diff($dueDate);
            if ($dueDate < $now && !in_array($m['status'], ['released', 'disputed'])) {
                echo 'text-red-500 font-semibold';
            } elseif ($diff->days <= 3 && !in_array($m['status'], ['released', 'disputed'])) {
                echo 'text-amber-500 font-semibold';
            } else {
                echo 'text-gray-400';
            }
?>"><i data-lucide="calendar" class="w-4 h-4"></i> Due: <?= date('M d, Y', strtotime($m['due_date'])) ?></span>
                                        <?php endif; ?>
                                    </div>
                                    <?php if (in_array($m['status'], ['submitted', 'released', 'disputed']) && !empty($m['submission_github_url'])): ?>
                                        <div class="mt-3 p-3 bg-gray-50 rounded-lg border border-gray-100">
                                            <p class="text-[10px] font-semibold text-gray-500 uppercase tracking-wide mb-2">Submission Details</p>
                                            <div class="space-y-1.5">
                                                <div class="flex items-center gap-2 text-xs">
                                                    <svg class="w-4 h-4 text-gray-400" fill="currentColor" viewBox="0 0 24 24"><path d="M12 0c-6.626 0-12 5.373-12 12 0 5.302 3.438 9.8 8.207 11.387.599.111.793-.261.793-.577v-2.234c-3.338.726-4.033-1.416-4.033-1.416-.546-1.387-1.333-1.756-1.333-1.756-1.089-.745.083-.729.083-.729 1.205.084 1.839 1.237 1.839 1.237 1.07 1.834 2.807 1.304 3.492.997.107-.775.418-1.305.762-1.604-2.665-.305-5.467-1.334-5.467-5.931 0-1.311.469-2.381 1.236-3.221-.124-.303-.535-1.524.117-3.176 0 0 1.008-.322 3.301 1.23.957-.266 1.983-.399 3.003-.404 1.02.005 2.047.138 3.006.404 2.291-1.552 3.297-1.23 3.297-1.23.653 1.653.242 2.874.118 3.176.77.84 1.235 1.911 1.235 3.221 0 4.609-2.807 5.624-5.479 5.921.43.372.823 1.102.823 2.222v3.293c0 .319.192.694.801.576 4.765-1.589 8.199-6.086 8.199-11.386 0-6.627-5.373-12-12-12z"/></svg>
                                                    <a href="<?= sanitize_string($m['submission_github_url']) ?>" target="_blank" rel="noopener" class="text-blue-600 hover:underline font-medium"><?= sanitize_string($m['submission_github_url']) ?></a>
                                                </div>
                                                <?php if (!empty($m['submission_note'])): ?>
                                                    <div class="flex items-start gap-2 text-xs text-gray-600">
                                                        <i data-lucide="message-square" class="w-4 h-4 text-gray-400 mt-0.5"></i>
                                                        <span><?= nl2br(sanitize_string($m['submission_note'])) ?></span>
                                                    </div>
                                                <?php endif; ?>
                                                <?php if (!empty($m['submission_file'])): ?>
                                                    <div class="flex items-center gap-2 text-xs">
                                                        <i data-lucide="paperclip" class="w-4 h-4 text-gray-400"></i>
                                                        <a href="../assets/upload/submissions/<?= sanitize_string($m['submission_file']) ?>" target="_blank" class="text-blue-600 hover:underline font-medium">View attached file</a>
                                                    </div>
                                                <?php endif; ?>
                                                <div class="flex items-center gap-2 text-[11px] text-gray-400">
                                                    <i data-lucide="clock" class="w-4 h-4"></i>
                                                    <span>Submitted <?= time_ago($m['submission_date']) ?></span>
                                                </div>
                                            </div>
                                        </div>
                                    <?php endif; ?>
                                </div>
                                <div class="flex flex-wrap gap-2">
                                    <?php if ($m['status'] === 'pending'): ?>
                                        <button onclick="openEditModal(<?= (int) $m['id'] ?>, '<?= sanitize_string(addslashes($m['title'])) ?>', <?= (float) $m['amount'] ?>, '<?= $m['due_date'] ?? '' ?>')" data-description="<?= htmlspecialchars($m['description'] ?? '', ENT_QUOTES) ?>" class="edit-btn inline-flex items-center gap-1.5 px-3 py-1.5 bg-gray-100 hover:bg-gray-200 text-gray-600 text-xs font-semibold rounded-lg transition-all"><i data-lucide="pencil" class="w-4 h-4"></i> Edit</button>
                                        <button onclick="fundMilestone(<?= (int) $m['id'] ?>, <?= (float) $m['amount'] ?>)" class="inline-flex items-center gap-1.5 px-3 py-1.5 btn-grad text-white text-xs font-semibold rounded-lg shadow-md shadow-blue-500/20"><i data-lucide="wallet" class="w-4 h-4"></i> Fund Escrow</button>
                                    <?php endif; ?>
                                    <?php if ($m['status'] === 'submitted'): ?>
                                        <button onclick="approveMilestone(<?= (int) $m['id'] ?>)" class="inline-flex items-center gap-1.5 px-3 py-1.5 bg-emerald-500 hover:bg-emerald-600 text-white text-xs font-semibold rounded-lg transition-all shadow-md shadow-emerald-500/20"><i data-lucide="check" class="w-4 h-4"></i> Approve &amp; Release</button>
                                        <button onclick="requestRevision(<?= (int) $m['id'] ?>)" class="inline-flex items-center gap-1.5 px-3 py-1.5 bg-amber-500 hover:bg-amber-600 text-white text-xs font-semibold rounded-lg transition-all shadow-md shadow-amber-500/20"><i data-lucide="undo" class="w-4 h-4"></i> Request Changes</button>
                                    <?php endif; ?>
                                    <?php if ($m['status'] !== 'released' && $m['status'] !== 'disputed'): ?>
                                        <button onclick="disputeMilestone(<?= (int) $m['id'] ?>)" class="inline-flex items-center gap-1.5 px-3 py-1.5 bg-red-50 hover:bg-red-100 text-red-500 text-xs font-semibold rounded-lg transition-all border border-red-200"><i data-lucide="flag" class="w-4 h-4"></i> Dispute</button>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php else: ?>
                <div class="text-center py-12">
                    <div class="w-16 h-16 rounded-2xl bg-gray-100 flex items-center justify-center mx-auto mb-4"><i data-lucide="list-checks" class="w-5 h-5 text-gray-300"></i></div>
                    <p class="text-gray-500 text-sm font-medium mb-1">No milestones yet</p>
                    <p class="text-gray-400 text-xs">Break down the project into manageable milestones</p>
                </div>
            <?php endif; ?>
        </div>
    </div>

<div id="createModal" class="fixed inset-0 z-50 hidden">
    <div class="modal-overlay absolute inset-0" onclick="closeModal('createModal')"></div>
    <div class="absolute inset-0 flex items-start justify-center pt-24 p-4">
        <div class="bg-white dark:bg-slate-800 rounded-2xl shadow-2xl w-full max-w-md relative z-10 slide-down">
            <div class="p-6 border-b border-gray-100 dark:border-slate-700">
                <div class="flex items-center justify-between">
                    <h3 class="text-lg font-bold text-gray-900 dark:text-white">Add Milestone</h3><button onclick="closeModal('createModal')" class="w-8 h-8 rounded-lg bg-gray-100 dark:bg-slate-700 flex items-center justify-center text-gray-400 hover:text-gray-600 dark:hover:text-slate-200"><i data-lucide="x" class="w-4 h-4"></i></button>
                </div>
            </div>
            <form id="createMilestoneForm" onsubmit="return createMilestone(event)">
                <div class="p-6 space-y-4">
                    <?= csrf_field() ?>
                    <input type="hidden" name="action" value="create">
                    <input type="hidden" name="contract_id" value="<?= $contractId ?>">
                    <div><label class="block text-xs font-semibold text-gray-700 dark:text-slate-300 mb-1.5">Milestone Title</label><input type="text" name="title" required maxlength="255" placeholder="e.g., Homepage Design" class="fld w-full px-4 py-2.5 rounded-xl border border-gray-200 dark:border-slate-600 text-sm bg-gray-50 dark:bg-slate-700 focus:bg-white dark:focus:bg-slate-600 dark:text-white"></div>
                    <div><label class="block text-xs font-semibold text-gray-700 dark:text-slate-300 mb-1.5">Amount ($)</label><input type="number" name="amount" required step="0.01" min="0.01" max="<?= round($budgetRem, 2) ?>" placeholder="0.00" class="fld w-full px-4 py-2.5 rounded-xl border border-gray-200 dark:border-slate-600 text-sm bg-gray-50 dark:bg-slate-700 focus:bg-white dark:focus:bg-slate-600 dark:text-white">
                        <p class="text-[11px] text-gray-400 mt-1">Remaining budget: <?= format_currency($budgetRem) ?></p>
                    </div>
                    <div><label class="block text-xs font-semibold text-gray-700 dark:text-slate-300 mb-1.5">Due Date (optional)</label><input type="date" name="due_date" class="fld w-full px-4 py-2.5 rounded-xl border border-gray-200 dark:border-slate-600 text-sm bg-gray-50 dark:bg-slate-700 focus:bg-white dark:focus:bg-slate-600 dark:text-white"></div>
                    <div><label class="block text-xs font-semibold text-gray-700 dark:text-slate-300 mb-1.5">Description (optional)</label><textarea name="description" rows="3" maxlength="1000" placeholder="Describe the deliverables for this milestone..." class="fld w-full px-4 py-2.5 rounded-xl border border-gray-200 dark:border-slate-600 text-sm bg-gray-50 dark:bg-slate-700 focus:bg-white dark:focus:bg-slate-600 dark:text-white resize-none"></textarea></div>
                </div>
                <div class="px-6 pb-6 flex gap-3">
                    <button type="button" onclick="closeModal('createModal')" class="flex-1 px-4 py-2.5 rounded-xl border border-gray-200 dark:border-slate-600 text-sm font-semibold text-gray-600 dark:text-slate-300 hover:bg-gray-50 dark:hover:bg-slate-700">Cancel</button>
                    <button type="submit" class="flex-1 btn-grad items-center justify-center px-4 py-2.5 rounded-xl text-white text-sm font-semibold shadow-lg shadow-blue-500/25 ">Create Milestone</button>
                </div>
            </form>
        </div>
    </div>
</div>

<div id="editModal" class="fixed inset-0 z-50 hidden">
    <div class="modal-overlay absolute inset-0" onclick="closeModal('editModal')"></div>
    <div class="absolute inset-0 flex items-center justify-center p-4">
        <div class="bg-white dark:bg-slate-800 rounded-2xl shadow-2xl w-full max-w-md relative z-10 slide-down">
            <div class="p-6 border-b border-gray-100 dark:border-slate-700">
                <div class="flex items-center justify-between">
                    <h3 class="text-lg font-bold text-gray-900 dark:text-white">Edit Milestone</h3><button onclick="closeModal('editModal')" class="w-8 h-8 rounded-lg bg-gray-100 dark:bg-slate-700 flex items-center justify-center text-gray-400 hover:text-gray-600 dark:hover:text-slate-200"><i data-lucide="x" class="w-4 h-4"></i></button>
                </div>
            </div>
            <form id="editMilestoneForm" onsubmit="return updateMilestone(event)">
                <div class="p-6 space-y-4">
                    <?= csrf_field() ?>
                    <input type="hidden" name="action" value="update">
                    <input type="hidden" name="milestone_id" id="edit_milestone_id">
                    <div><label class="block text-xs font-semibold text-gray-700 dark:text-slate-300 mb-1.5">Milestone Title</label><input type="text" name="title" id="edit_title" required maxlength="255" class="fld w-full px-4 py-2.5 rounded-xl border border-gray-200 dark:border-slate-600 text-sm bg-gray-50 dark:bg-slate-700 focus:bg-white dark:focus:bg-slate-600 dark:text-white"></div>
                    <div><label class="block text-xs font-semibold text-gray-700 dark:text-slate-300 mb-1.5">Amount ($)</label><input type="number" name="amount" id="edit_amount" required step="0.01" min="0.01" class="fld w-full px-4 py-2.5 rounded-xl border border-gray-200 dark:border-slate-600 text-sm bg-gray-50 dark:bg-slate-700 focus:bg-white dark:focus:bg-slate-600 dark:text-white"></div>
                    <div><label class="block text-xs font-semibold text-gray-700 dark:text-slate-300 mb-1.5">Due Date (optional)</label><input type="date" name="due_date" id="edit_due_date" class="fld w-full px-4 py-2.5 rounded-xl border border-gray-200 dark:border-slate-600 text-sm bg-gray-50 dark:bg-slate-700 focus:bg-white dark:focus:bg-slate-600 dark:text-white"></div>
                    <div><label class="block text-xs font-semibold text-gray-700 dark:text-slate-300 mb-1.5">Description (optional)</label><textarea name="description" id="edit_description" rows="3" maxlength="1000" placeholder="Describe the deliverables for this milestone..." class="fld w-full px-4 py-2.5 rounded-xl border border-gray-200 dark:border-slate-600 text-sm bg-gray-50 dark:bg-slate-700 focus:bg-white dark:focus:bg-slate-600 dark:text-white resize-none"></textarea></div>
                </div>
                <div class="px-6 pb-6 flex gap-3">
                    <button type="button" onclick="closeModal('editModal')" class="flex-1 px-4 py-2.5 rounded-xl border border-gray-200 dark:border-slate-600 text-sm font-semibold text-gray-600 dark:text-slate-300 hover:bg-gray-50 dark:hover:bg-slate-700">Cancel</button>
                    <button type="submit" class="flex-1 flex justify-center items-center btn-grad px-4 py-2.5 rounded-xl text-white text-sm font-semibold shadow-lg shadow-blue-500/25">Update Milestone</button>
                </div>
            </form>
        </div>
    </div>
</div>
<?php $conn->close(); ?>
<div id="fundModal" class="fixed inset-0 z-50 hidden">
    <div class="modal-overlay absolute inset-0" onclick="closeModal('fundModal')"></div>
    <div class="absolute inset-0 flex items-center justify-center p-4">
        <div class="bg-white dark:bg-slate-800 rounded-2xl shadow-2xl w-full max-w-sm relative z-10 slide-down">
            <div class="p-6 text-center">
                <div class="w-16 h-16 rounded-2xl bg-amber-100 dark:bg-amber-900/30 flex items-center justify-center mx-auto mb-4"><i data-lucide="shield" class="w-5 h-5 text-amber-600"></i></div>
                <h3 class="text-lg font-bold text-gray-900 dark:text-white mb-2">Fund Escrow</h3>
                <p class="text-sm text-gray-500 dark:text-slate-400 mb-1">Place <span id="fundAmount" class="font-bold text-gray-900 dark:text-white"></span> in escrow?</p>
                <p class="text-xs text-gray-400 dark:text-slate-500">Deducted from your wallet and held until milestone completion.</p>
            </div>
            <div class="px-6 pb-6 flex gap-3">
                <button onclick="closeModal('fundModal')" class="flex-1 px-4 py-2.5 rounded-xl border border-gray-200 dark:border-slate-600 text-sm font-semibold text-gray-600 dark:text-slate-300 hover:bg-gray-50 dark:hover:bg-slate-700">Cancel</button>
                <button onclick="confirmFund()" id="fundConfirmBtn" class="flex-1 flex justify-center items-center btn-grad px-4 py-2.5 rounded-xl text-white text-sm font-semibold shadow-lg shadow-blue-500/25"><span id="fundBtnText">Confirm Fund</span></button>
            </div>
        </div>
    </div>
</div>

<div id="approveModal" class="fixed inset-0 z-50 hidden">
    <div class="modal-overlay absolute inset-0" onclick="closeModal('approveModal')"></div>
    <div class="absolute inset-0 flex items-center justify-center p-4">
        <div class="bg-white dark:bg-slate-800 rounded-2xl shadow-2xl w-full max-w-sm relative z-10 slide-down">
            <div class="p-6 text-center">
                <div class="w-16 h-16 rounded-2xl bg-emerald-100 dark:bg-emerald-900/30 flex items-center justify-center mx-auto mb-4"><i data-lucide="circle-check" class="w-5 h-5 text-emerald-600"></i></div>
                <h3 class="text-lg font-bold text-gray-900 dark:text-white mb-2">Approve &amp; Release Payment</h3>
                <p class="text-sm text-gray-500 dark:text-slate-400">This will release payment to the freelancer. Cannot be undone.</p>
            </div>
            <div class="px-6 pb-6 flex gap-3">
                <button onclick="closeModal('approveModal')" class="flex-1 px-4 py-2.5 rounded-xl border border-gray-200 dark:border-slate-600 text-sm font-semibold text-gray-600 dark:text-slate-300 hover:bg-gray-50 dark:hover:bg-slate-700">Cancel</button>
                <button onclick="confirmApprove()" id="approveConfirmBtn" class="flex-1 px-4 py-2.5 rounded-xl bg-emerald-500 hover:bg-emerald-600 text-white text-sm font-semibold shadow-lg shadow-emerald-500/25"><span id="approveBtnText">Approve</span></button>
            </div>
        </div>
    </div>
</div>

<div id="revisionModal" class="fixed inset-0 z-50 hidden">
    <div class="modal-overlay absolute inset-0" onclick="closeModal('revisionModal')"></div>
    <div class="absolute inset-0 flex items-center justify-center p-4">
        <div class="bg-white dark:bg-slate-800 rounded-2xl shadow-2xl w-full max-w-md relative z-10 slide-down">
            <div class="p-6 border-b border-gray-100 dark:border-slate-700">
                <div class="flex items-center justify-between">
                    <h3 class="text-lg font-bold text-gray-900 dark:text-white">Request Changes</h3>
                    <button onclick="closeModal('revisionModal')" class="w-8 h-8 rounded-lg bg-gray-100 dark:bg-slate-700 flex items-center justify-center text-gray-400 hover:text-gray-600 dark:hover:text-slate-200"><i data-lucide="x" class="w-4 h-4"></i></button>
                </div>
            </div>
            <form id="revisionForm" onsubmit="return submitRevision(event)">
                <div class="p-6 space-y-4">
                    <?= csrf_field() ?>
                    <input type="hidden" name="action" value="request_revision">
                    <input type="hidden" name="milestone_id" id="revision_milestone_id">
                    <div>
                        <label class="block text-xs font-semibold text-gray-700 dark:text-slate-300 mb-1.5">Reason for Revision</label>
                        <textarea name="revision_note" id="revision_note" required rows="4" placeholder="Describe what needs to be changed..." class="fld w-full px-4 py-2.5 rounded-xl border border-gray-200 dark:border-slate-600 text-sm bg-gray-50 dark:bg-slate-700 focus:bg-white dark:focus:bg-slate-600 dark:text-white resize-none"></textarea>
                    </div>
                </div>
                <div class="px-6 pb-6 flex gap-3">
                    <button type="button" onclick="closeModal('revisionModal')" class="flex-1 px-4 py-2.5 rounded-xl border border-gray-200 dark:border-slate-600 text-sm font-semibold text-gray-600 dark:text-slate-300 hover:bg-gray-50 dark:hover:bg-slate-700">Cancel</button>
                    <button type="submit" class="flex-1 px-4 py-2.5 rounded-xl bg-amber-500 hover:bg-amber-600 text-white text-sm font-semibold shadow-lg shadow-amber-500/25"><span id="revisionBtnText">Send Revision</span></button>
                </div>
            </form>
        </div>
    </div>
</div>

<div id="loadingOverlay" class="fixed inset-0 z-[60] hidden modal-overlay flex items-center justify-center">
    <div class="bg-white dark:bg-slate-800 rounded-2xl p-8 shadow-2xl text-center">
        <div class="w-12 h-12 border-4 border-blue-200 border-t-blue-600 rounded-full animate-spin mx-auto mb-4"></div>
        <p class="text-sm font-semibold text-gray-700 dark:text-slate-200">Processing...</p>
    </div>
</div>

<div id="disputeModal" class="fixed inset-0 z-50 hidden">
    <div class="modal-overlay absolute inset-0 bg-black/40" onclick="closeDisputeModal()"></div>
    <div class="absolute inset-0 flex items-center justify-center p-4">
        <div class="bg-white dark:bg-slate-800 rounded-2xl shadow-2xl w-full max-w-md relative z-10 slide-down">
            <div class="p-6 border-b border-gray-100 dark:border-slate-700">
                <div class="flex items-center justify-between">
                    <h3 class="text-lg font-bold text-gray-900 dark:text-white">File a Dispute</h3>
                    <button onclick="closeDisputeModal()" class="w-8 h-8 rounded-lg bg-gray-100 dark:bg-slate-700 flex items-center justify-center text-gray-400 hover:text-gray-600 dark:hover:text-slate-200"><i data-lucide="x" class="w-4 h-4"></i></button>
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
                        <textarea name="description" required rows="4" minlength="20" maxlength="5000" placeholder="Provide a detailed description of the issue (min 20 characters)..." class="fld w-full px-4 py-2.5 rounded-xl border border-gray-200 dark:border-slate-600 text-sm bg-gray-50 dark:bg-slate-700 focus:bg-white dark:focus:bg-slate-600 dark:text-white resize-none"></textarea>
                    </div>
                </div>
                <div class="px-6 pb-6 flex gap-3">
                    <button type="button" onclick="closeDisputeModal()" class="flex-1 px-4 py-2.5 rounded-xl border border-gray-200 dark:border-slate-600 text-sm font-semibold text-gray-600 dark:text-slate-300 hover:bg-gray-50 dark:hover:bg-slate-700">Cancel</button>
                    <button type="submit" class="flex-1 px-4 py-2.5 rounded-xl bg-red-500 hover:bg-red-600 text-white text-sm font-semibold shadow-lg shadow-red-500/25">Submit Dispute</button>
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
</main>
<script>
    var CSRF_TOKEN = '<?= generate_csrf_token() ?>';
    var BASE_URL = '/jobhub';
    var currentFundMilestoneId = null,
        currentApproveMilestoneId = null;

    function openModal(id) {
        document.getElementById(id).classList.remove('hidden')
    }

    function closeModal(id) {
        document.getElementById(id).classList.add('hidden')
    }

    function showLoading() {
        document.getElementById('loadingOverlay').classList.remove('hidden')
    }

    function hideLoading() {
        document.getElementById('loadingOverlay').classList.add('hidden')
    }

    function openEditModal(id, title, amount, dueDate) {
        document.getElementById('edit_milestone_id').value = id;
        document.getElementById('edit_title').value = title;
        document.getElementById('edit_amount').value = amount;
        document.getElementById('edit_due_date').value = dueDate || '';
        var desc = '';
        var btn = document.querySelector('.edit-btn[onclick*="' + id + '"]');
        if (btn) desc = btn.getAttribute('data-description') || '';
        document.getElementById('edit_description').value = desc;
        openModal('editModal')
    }

    function showToast(type, message) {
        var c = {
            success: 'bg-emerald-500',
            error: 'bg-red-500',
            info: 'bg-blue-500'
        };
        var ic = {
            success: 'circle-check',
            error: 'circle-alert',
            info: 'circle-info'
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
    async function createMilestone(e) {
        e.preventDefault();
        var data = new FormData(e.target);
        showLoading();
        try {
            var r = await fetch(BASE_URL + '/api/milestones_api.php', {
                method: 'POST',
                body: data
            });
            var j = await r.json();
            hideLoading();
            if (j.success) {
                showToast('success', j.message);
                closeModal('createModal');
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
    async function updateMilestone(e) {
        e.preventDefault();
        var data = new FormData(e.target);
        showLoading();
        try {
            var r = await fetch(BASE_URL + '/api/milestones_api.php', {
                method: 'POST',
                body: data
            });
            var j = await r.json();
            hideLoading();
            if (j.success) {
                showToast('success', j.message);
                closeModal('editModal');
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

    function fundMilestone(id, amount) {
        currentFundMilestoneId = id;
        document.getElementById('fundAmount').textContent = '$' + amount.toFixed(2);
        openModal('fundModal')
    }
    async function confirmFund() {
        var btn = document.getElementById('fundConfirmBtn'),
            txt = document.getElementById('fundBtnText');
        btn.disabled = true;
        txt.textContent = 'Funding...';
        showLoading();
        try {
            var fd = new FormData();
            fd.append('action', 'fund');
            fd.append('milestone_id', currentFundMilestoneId);
            fd.append('csrf_token', CSRF_TOKEN);
            var r = await fetch(BASE_URL + '/api/payments_api.php', {
                method: 'POST',
                body: fd
            });
            var j = await r.json();
            hideLoading();
            if (j.success) {
                showToast('success', j.message);
                closeModal('fundModal');
                reloadPage()
            } else {
                showToast('error', j.message)
            }
        } catch (err) {
            hideLoading();
            showToast('error', 'Network error.')
        }
        btn.disabled = false;
        txt.textContent = 'Confirm Fund'
    }

    function approveMilestone(id) {
        currentApproveMilestoneId = id;
        openModal('approveModal')
    }
    async function confirmApprove() {
        var btn = document.getElementById('approveConfirmBtn'),
            txt = document.getElementById('approveBtnText');
        btn.disabled = true;
        txt.textContent = 'Approving...';
        showLoading();
        try {
            var fd = new FormData();
            fd.append('action', 'approve');
            fd.append('milestone_id', currentApproveMilestoneId);
            fd.append('csrf_token', CSRF_TOKEN);
            var r = await fetch(BASE_URL + '/api/milestones_api.php', {
                method: 'POST',
                body: fd
            });
            var j = await r.json();
            hideLoading();
            if (j.success) {
                showToast('success', j.message);
                closeModal('approveModal');
                reloadPage()
            } else {
                showToast('error', j.message)
            }
        } catch (err) {
            hideLoading();
            showToast('error', 'Network error.')
        }
        btn.disabled = false;
        txt.textContent = 'Approve'
    }

    var currentRevisionMilestoneId = null;
    function requestRevision(id) {
        currentRevisionMilestoneId = id;
        document.getElementById('revision_milestone_id').value = id;
        document.getElementById('revision_note').value = '';
        openModal('revisionModal')
    }
    async function submitRevision(e) {
        e.preventDefault();
        var btn = document.querySelector('#revisionForm button[type="submit"]'),
            txt = document.getElementById('revisionBtnText');
        btn.disabled = true;
        txt.textContent = 'Sending...';
        showLoading();
        try {
            var fd = new FormData(document.getElementById('revisionForm'));
            fd.append('csrf_token', CSRF_TOKEN);
            var r = await fetch(BASE_URL + '/api/milestones_api.php', {
                method: 'POST',
                body: fd
            });
            var j = await r.json();
            hideLoading();
            if (j.success) {
                showToast('success', j.message);
                closeModal('revisionModal');
                reloadPage()
            } else {
                showToast('error', j.message)
            }
        } catch (err) {
            hideLoading();
            showToast('error', 'Network error.')
        }
        btn.disabled = false;
        txt.textContent = 'Send Revision';
        return false
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
        document.getElementById('disputeModal').classList.remove('hidden');
    }
    function closeDisputeModal() {
        document.getElementById('disputeModal').classList.add('hidden');
    }
    async function submitDispute(e) {
        e.preventDefault();
        var fd = new FormData(document.getElementById('disputeForm'));
        fd.append('csrf_token', CSRF_TOKEN);
        showLoading();
        try {
            var r = await fetch(BASE_URL + '/api/dispute_api.php', { method: 'POST', body: fd });
            var j = await r.json();
            hideLoading();
            if (j.success) {
                showToast('success', j.message);
                closeDisputeModal();
                reloadPage();
            } else {
                showToast('error', j.message);
            }
        } catch (err) {
            hideLoading();
            showToast('error', 'Network error.');
        }
        return false;
    }
</script>
<?php require_once __DIR__ . '/../includes/client_footer.php'; ?>
