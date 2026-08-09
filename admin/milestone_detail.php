<?php

/**
 * Admin Milestone Detail — Read-only view of a single milestone
 */
require_once __DIR__ . '/../auth/auth.php';
require_role('admin');
require_once __DIR__ . '/../config/db.php';

$currentPage = 'milestones';
$msId = sanitize_int($_GET['id'] ?? 0);
if ($msId <= 0) {
    set_flash('error', 'Invalid milestone ID.');
    redirect('milestones.php');
}

// ── Fetch milestone with all joins ──────────────────────────────────
$ms = $conn->prepare("SELECT m.id, m.title, m.amount, m.status, m.due_date, m.description,
                             m.submission_github_url, m.submission_file, m.submission_note, m.submission_date,
                             m.created_at, m.updated_at,
                             ct.id AS contract_id, ct.total_budget AS contract_budget, ct.status AS contract_status, ct.contract_type,
                             cu.id AS client_user_id, cu.name AS client_name, cu.profile_image AS client_image, cu.email AS client_email,
                             fu.id AS freelancer_user_id, fu.name AS freelancer_name, fu.profile_image AS freelancer_image, fu.email AS freelancer_email,
                             j.id AS job_id, j.title AS job_title
                      FROM milestones m
                      JOIN contracts ct ON m.contract_id = ct.id
                      JOIN users cu ON ct.client_id = cu.id
                      JOIN users fu ON ct.freelancer_id = fu.id
                      JOIN jobs j ON ct.job_id = j.id
                      WHERE m.id = ?");
$ms->bind_param('i', $msId);
$ms->execute();
$milestone = $ms->get_result()->fetch_assoc();
$ms->close();

if (!$milestone) {
    set_flash('error', 'Milestone not found.');
    redirect('milestones.php');
}

// ── Fetch payments ──────────────────────────────────────────────────
$ps = $conn->prepare("SELECT id, total_amount, platform_fee, freelancer_net, status, created_at FROM payments WHERE milestone_id = ? ORDER BY created_at DESC");
$ps->bind_param('i', $msId);
$ps->execute();
$paymentsResult = $ps->get_result();
$ps->close();
$payments = [];
while ($row = $paymentsResult->fetch_assoc()) $payments[] = $row;

// ── Badge maps ──────────────────────────────────────────────────────
$statusColors = [
    'pending' => 'bg-gray-100 text-gray-600 dark:bg-slate-700 dark:text-slate-400',
    'funded_in_escrow' => 'bg-amber-100 text-amber-700 dark:bg-amber-900/30 dark:text-amber-400',
    'submitted' => 'bg-violet-100 text-violet-700 dark:bg-violet-900/30 dark:text-violet-400',
    'released' => 'bg-emerald-100 text-emerald-700 dark:bg-emerald-900/30 dark:text-emerald-400',
    'disputed' => 'bg-red-100 text-red-700 dark:bg-red-900/30 dark:text-red-400',
];
$paymentStatusColors = [
    'pending' => 'bg-amber-100 text-amber-700 dark:bg-amber-900/30 dark:text-amber-400',
    'processing' => 'bg-cyan-100 text-cyan-700 dark:bg-cyan-900/30 dark:text-cyan-400',
    'completed' => 'bg-emerald-100 text-emerald-700 dark:bg-emerald-900/30 dark:text-emerald-400',
    'failed' => 'bg-red-100 text-red-700 dark:bg-red-900/30 dark:text-red-400',
    'refunded' => 'bg-purple-100 text-purple-700 dark:bg-purple-900/30 dark:text-purple-400',
];

// ── Admin nav ───────────────────────────────────────────────────────
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
    ['key' => 'clients', 'label' => 'Clients', 'url' => 'clients.php', 'icon' => 'user'],
    ['key' => 'jobs', 'label' => 'Jobs', 'url' => 'jobs.php', 'icon' => 'briefcase'],
    ['key' => 'categories', 'label' => 'Categories', 'url' => 'categories.php', 'icon' => 'folder-open'],
    ['key' => 'skills', 'label' => 'Skills', 'url' => 'skills.php', 'icon' => 'settings'],
    ['key' => 'payments', 'label' => 'Payments', 'url' => 'payments.php', 'icon' => 'credit-card'],
    ['key' => 'wallets', 'label' => 'Wallets', 'url' => 'wallets.php', 'icon' => 'wallet'],
    ['key' => 'contracts', 'label' => 'Contracts', 'url' => 'contracts.php', 'icon' => 'file-text'],
    ['key' => 'milestones', 'label' => 'Milestones', 'url' => 'milestones.php', 'icon' => 'list-checks'],
    ['key' => 'reviews', 'label' => 'Reviews', 'url' => 'reviews.php', 'icon' => 'star'],
    ['key' => 'disputes', 'label' => 'Disputes', 'url' => 'disputes.php', 'icon' => 'hammer'],
    ['key' => 'fraud', 'label' => 'Fraud', 'url' => 'fraud_detection.php', 'icon' => 'shield'],
    ['key' => 'notifications', 'label' => 'Notifications', 'url' => 'notifications.php', 'icon' => 'bell'],
    ['key' => 'ai_monitor', 'label' => 'AI Monitor', 'url' => 'ai_monitor.php', 'icon' => 'brain'],
    ['key' => 'analytics', 'label' => 'Analytics', 'url' => 'analytics.php', 'icon' => 'pie-chart'],
    ['key' => 'settings', 'label' => 'Settings', 'url' => 'settings.php', 'icon' => 'settings'],
];
$pageTitle = 'Milestone #' . $msId;
$pageSubtitle = 'Milestone Details';
$activePage = 'milestones';
$user = ['name' => $adminName, 'profile_image' => $_navUserRow['profile_image'] ?? null];
$unreadCount = 0;
$profileLink = 'profile.php';
require_once __DIR__ . '/../components/layout_start.php';
?>

    <?php display_flash('success'); ?>
    <?php display_flash('error'); ?>

<div class="bg-[#F4F7F4] dark:bg-slate-900 rounded-lg p-6">

    <!-- Top Bar -->
    <div class="flex items-center justify-between mb-6">
        <a href="milestones.php" class="py-2 text-sm font-medium text-gray-700 flex justify-center items-center gap-2">
            <i data-lucide="arrow-left" class="text-xs"></i> Back to Milestones
        </a>
        <a href="contract_detail.php?id=<?= (int) $milestone['contract_id'] ?>" class="inline-flex items-center gap-2 px-4 py-2 bg-[#108A00] hover:bg-[#0d7500] dark:bg-emerald-600 dark:hover:bg-emerald-700 text-white text-xs font-semibold rounded-lg transition-colors">
            <i data-lucide="file-text"></i> View Contract
        </a>
    </div>

    <!-- ═══ MAIN GRID ═══════════════════════════════════════════════════ -->
    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">

        <!-- ═══ LEFT COLUMN ═══════════════════════════════════════════ -->
        <div class="lg:col-span-2 space-y-6">

            <!-- ═══ MILESTONE OVERVIEW ═════════════════════════════════ -->
            <div class="bg-white dark:bg-slate-800 border border-[#E4EBE4] dark:border-slate-700 rounded-lg shadow-sm overflow-hidden fade-in">
                <div class="p-6">
                    <div class="flex flex-col sm:flex-row sm:items-start justify-between gap-4 mb-5">
                        <div class="flex-1">
                            <div class="flex flex-wrap items-center gap-2 mb-2">
                                <h2 class="text-xl font-bold text-gray-900 dark:text-white"><?= sanitize_string($milestone['title']) ?></h2>
                                <span class="inline-block px-2.5 py-0.5 rounded text-[11px] font-semibold <?= $statusColors[$milestone['status']] ?? '' ?>"><?= str_replace('_', ' ', ucfirst($milestone['status'])) ?></span>
                            </div>
                            <div class="flex flex-wrap items-center gap-4 text-sm text-gray-400 dark:text-slate-400">
                                <span class="flex items-center gap-1.5"><i data-lucide="link" class="text-blue-500"></i>Contract <a href="contract_detail.php?id=<?= (int) $milestone['contract_id'] ?>" class="font-bold text-gray-700 dark:text-slate-300 hover:text-blue-600 dark:hover:text-blue-400 transition-colors">#<?= $milestone['contract_id'] ?></a></span>
                                <span class="flex items-center gap-1.5"><i data-lucide="briefcase" class="text-gray-400"></i><?= sanitize_string($milestone['job_title']) ?></span>
                            </div>
                        </div>
                    </div>
                    <div class="border-t border-gray-100 dark:border-slate-700"></div>
                    <div class="grid grid-cols-2 sm:grid-cols-4 gap-px bg-gray-100 dark:bg-slate-700">
                        <div class="bg-white dark:bg-slate-800 p-4">
                            <p class="text-[10px] font-semibold text-gray-400 dark:text-slate-500 uppercase tracking-wider">Amount</p>
                            <p class="text-sm font-bold text-gray-900 dark:text-white mt-1 text-left"><?= format_currency((float) $milestone['amount']) ?></p>
                        </div>
                        <div class="bg-white dark:bg-slate-800 p-4">
                            <p class="text-[10px] font-semibold text-gray-400 dark:text-slate-500 uppercase tracking-wider">Due Date</p>
                            <p class="text-sm font-bold text-gray-900 dark:text-white mt-1 text-left"><?= $milestone['due_date'] ? date('M j, Y', strtotime($milestone['due_date'])) : '—' ?></p>
                        </div>
                        <div class="bg-white dark:bg-slate-800 p-4">
                            <p class="text-[10px] font-semibold text-gray-400 dark:text-slate-500 uppercase tracking-wider">Created</p>
                            <p class="text-sm font-bold text-gray-900 dark:text-white mt-1 text-left"><?= date('M j, Y', strtotime($milestone['created_at'])) ?></p>
                        </div>
                        <div class="bg-white dark:bg-slate-800 p-4">
                            <p class="text-[10px] font-semibold text-gray-400 dark:text-slate-500 uppercase tracking-wider">Contract Budget</p>
                            <p class="text-sm font-bold text-gray-900 dark:text-white mt-1 text-left"><?= format_currency((float) $milestone['contract_budget']) ?></p>
                        </div>
                    </div>
                </div>
            </div>

            <!-- ═══ DESCRIPTION ════════════════════════════════════════ -->
            <?php if ($milestone['description']): ?>
            <div class="bg-white dark:bg-slate-800 border border-[#E4EBE4] dark:border-slate-700 rounded-lg shadow-sm fade-in" style="animation-delay:.1s">
                <div class="px-6 py-4 border-b border-gray-100 dark:border-slate-700">
                    <h3 class="text-sm font-semibold text-gray-900 dark:text-white"><i data-lucide="align-left" class="text-gray-400 mr-1.5"></i>Description</h3>
                </div>
                <div class="p-6">
                    <p class="text-sm text-gray-600 dark:text-slate-400 whitespace-pre-wrap"><?= sanitize_string($milestone['description']) ?></p>
                </div>
            </div>
            <?php endif; ?>

            <!-- ═══ SUBMISSION ═════════════════════════════════════════ -->
            <div class="bg-white dark:bg-slate-800 border border-[#E4EBE4] dark:border-slate-700 rounded-lg shadow-sm overflow-hidden fade-in" style="animation-delay:.13s">
                <div class="px-6 py-4 border-b border-gray-100 dark:border-slate-700">
                    <h3 class="text-sm font-semibold text-gray-900 dark:text-white"><i data-lucide="upload" class="text-violet-500 mr-1.5"></i>Submission</h3>
                </div>
                <div class="p-6">
                <?php if ($milestone['submission_date']): ?>
                    <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
                        <div class="bg-gray-50 dark:bg-slate-700/50 rounded-lg p-4">
                            <p class="text-[10px] font-semibold text-gray-400 dark:text-slate-500 uppercase tracking-wider">Submitted</p>
                            <p class="text-sm font-bold text-gray-900 dark:text-white mt-1 text-left"><?= date('M j, Y g:i A', strtotime($milestone['submission_date'])) ?></p>
                        </div>
                        <div class="bg-gray-50 dark:bg-slate-700/50 rounded-lg p-4">
                            <p class="text-[10px] font-semibold text-gray-400 dark:text-slate-500 uppercase tracking-wider">GitHub</p>
                            <?php if ($milestone['submission_github_url']): ?>
                            <a href="<?= sanitize_string($milestone['submission_github_url']) ?>" target="_blank" class="text-sm font-medium text-blue-600 dark:text-blue-400 hover:underline mt-1 block truncate"><i data-lucide="github" class=""></i><?= sanitize_string($milestone['submission_github_url']) ?></a>
                            <?php else: ?>
                            <p class="text-sm text-gray-400 dark:text-slate-500 mt-1">—</p>
                            <?php endif; ?>
                        </div>
                        <div class="bg-gray-50 dark:bg-slate-700/50 rounded-lg p-4">
                            <p class="text-[10px] font-semibold text-gray-400 dark:text-slate-500 uppercase tracking-wider">File</p>
                            <?php if ($milestone['submission_file']): ?>
                            <p class="text-sm font-medium text-gray-900 dark:text-white mt-1 truncate"><i data-lucide="paperclip" class="mr-1 text-gray-400"></i><?= sanitize_string($milestone['submission_file']) ?></p>
                            <?php else: ?>
                            <p class="text-sm text-gray-400 dark:text-slate-500 mt-1">—</p>
                            <?php endif; ?>
                        </div>
                    </div>
                    <?php if ($milestone['submission_note']): ?>
                    <div class="mt-4 p-4 bg-gray-50 dark:bg-slate-700/50 rounded-lg">
                        <p class="text-[10px] font-semibold text-gray-400 dark:text-slate-500 uppercase tracking-wider mb-1">Note</p>
                        <p class="text-sm text-gray-600 dark:text-slate-400 whitespace-pre-wrap"><?= sanitize_string($milestone['submission_note']) ?></p>
                    </div>
                    <?php endif; ?>
                <?php else: ?>
                    <div class="text-center py-6"><p class="text-gray-400 dark:text-slate-500 text-sm">No submission yet</p></div>
                <?php endif; ?>
                </div>
            </div>

            <!-- ═══ DISPUTE RESOLUTION ══════════════════════════════════ -->
            <?php if ($milestone['status'] === 'disputed'): ?>
            <div class="bg-white dark:bg-slate-800 border border-red-200 dark:border-red-800/50 rounded-lg shadow-sm overflow-hidden fade-in" style="animation-delay:.14s">
                <div class="px-6 py-4 border-b border-red-100 dark:border-red-800/50 bg-red-50/50 dark:bg-red-900/10">
                    <h3 class="text-sm font-semibold text-red-700 dark:text-red-400"><i data-lucide="shield-alert" class="text-red-500 mr-1.5"></i>Dispute Resolution</h3>
                </div>
                <div class="p-6">
                    <div class="flex items-center gap-3 mb-5 p-4 bg-red-50 dark:bg-red-900/10 border border-red-100 dark:border-red-800/30 rounded-lg">
                        <div class="w-10 h-10 rounded-xl bg-red-100 dark:bg-red-900/30 flex items-center justify-center flex-shrink-0">
                            <i data-lucide="triangle-alert" class="w-5 h-5 text-red-500"></i>
                        </div>
                        <div>
                            <p class="text-sm font-semibold text-red-700 dark:text-red-400">This milestone is under dispute</p>
                            <p class="text-xs text-red-500 dark:text-red-400/70">Review the submission and choose to release payment to the freelancer or refund the client.</p>
                        </div>
                    </div>

                    <div class="grid grid-cols-2 gap-4 mb-5">
                        <div class="bg-gray-50 dark:bg-slate-700/50 rounded-lg p-4">
                            <p class="text-[10px] font-semibold text-gray-400 dark:text-slate-500 uppercase tracking-wider">Amount at Stake</p>
                            <p class="text-lg font-extrabold text-gray-900 dark:text-white mt-1"><?= format_currency((float) $milestone['amount']) ?></p>
                        </div>
                        <div class="bg-gray-50 dark:bg-slate-700/50 rounded-lg p-4">
                            <p class="text-[10px] font-semibold text-gray-400 dark:text-slate-500 uppercase tracking-wider">Disputed Since</p>
                            <p class="text-sm font-bold text-gray-900 dark:text-white mt-1"><?= time_ago($milestone['updated_at']) ?></p>
                        </div>
                    </div>

                    <div class="flex flex-col sm:flex-row gap-3">
                        <button onclick="openResolveModal('release')" class="flex-1 inline-flex items-center justify-center gap-2 px-5 py-3 bg-emerald-500 hover:bg-emerald-600 text-white text-sm font-semibold rounded-xl shadow-lg shadow-emerald-500/25 transition-colors">
                            <i data-lucide="check-circle" class="w-4 h-4"></i> Release to Freelancer
                        </button>
                        <button onclick="openResolveModal('refund')" class="flex-1 inline-flex items-center justify-center gap-2 px-5 py-3 bg-red-500 hover:bg-red-600 text-white text-sm font-semibold rounded-xl shadow-lg shadow-red-500/25 transition-colors">
                            <i data-lucide="rotate-ccw" class="w-4 h-4"></i> Refund to Client
                        </button>
                    </div>
                </div>
            </div>

            <!-- Resolve Confirmation Modal -->
            <div id="resolveModal" class="fixed inset-0 z-50 hidden">
                <div class="modal-overlay absolute inset-0 bg-black/40" onclick="closeResolveModal()"></div>
                <div class="absolute inset-0 flex items-center justify-center p-4">
                    <div class="bg-white dark:bg-slate-800 rounded-2xl shadow-2xl w-full max-w-md relative z-10">
                        <div class="p-6 text-center">
                            <div id="resolveModalIcon" class="w-14 h-14 rounded-2xl flex items-center justify-center mx-auto mb-4"></div>
                            <h3 id="resolveModalTitle" class="text-base font-bold text-gray-900 dark:text-white mb-2"></h3>
                            <p id="resolveModalDesc" class="text-sm text-gray-500 dark:text-slate-400"></p>
                            <p id="resolveModalAmount" class="text-lg font-extrabold text-gray-900 dark:text-white mt-3"></p>
                        </div>
                        <div class="px-6 pb-6 flex gap-3">
                            <button onclick="closeResolveModal()" class="flex-1 px-4 py-2.5 rounded-xl border border-gray-200 dark:border-slate-600 text-sm font-semibold text-gray-600 dark:text-slate-300 hover:bg-gray-50 dark:hover:bg-slate-700 transition-colors">Cancel</button>
                            <button onclick="confirmResolve()" id="resolveConfirmBtn" class="flex-1 px-4 py-2.5 rounded-xl text-white text-sm font-semibold shadow-lg transition-colors">
                                <span id="resolveConfirmBtnText">Confirm</span>
                            </button>
                        </div>
                    </div>
                </div>
            </div>
            <?php endif; ?>

            <!-- ═══ PAYMENTS ═══════════════════════════════════════════ -->
            <div class="bg-white dark:bg-slate-800 border border-[#E4EBE4] dark:border-slate-700 rounded-lg shadow-sm overflow-hidden fade-in" style="animation-delay:.16s">
                <div class="px-6 py-4 border-b border-gray-100 dark:border-slate-700">
                    <h3 class="text-sm font-semibold text-gray-900 dark:text-white"><i data-lucide="credit-card" class="text-blue-500 mr-1.5"></i>Payments (<?= count($payments) ?>)</h3>
                </div>
                <div class="p-6">
                <?php if ($payments): ?>
                    <div class="overflow-x-auto">
                        <table class="w-full min-w-full text-sm text-left">
                            <thead>
                                <tr class="bg-gray-50 dark:bg-slate-700/50">
                                    <th class="px-5 py-3 text-xs font-semibold text-gray-500 dark:text-slate-400 uppercase">Amount</th>
                                    <th class="px-5 py-3 text-xs font-semibold text-gray-500 dark:text-slate-400 uppercase text-right">Fee</th>
                                    <th class="px-5 py-3 text-xs font-semibold text-gray-500 dark:text-slate-400 uppercase text-right">Net</th>
                                    <th class="px-5 py-3 text-xs font-semibold text-gray-500 dark:text-slate-400 uppercase text-center">Status</th>
                                    <th class="px-5 py-3 text-xs font-semibold text-gray-500 dark:text-slate-400 uppercase text-right">Date</th>
                                </tr>
                            </thead>
                            <tbody>
                            <?php foreach ($payments as $p): ?>
                                <tr class="border-b border-gray-50 dark:border-slate-700/50 last:border-0 hover:bg-gray-50 dark:hover:bg-slate-700/30 transition-colors">
                                    <td class="px-5 py-4 font-medium text-gray-900 dark:text-white"><?= format_currency((float) $p['total_amount']) ?></td>
                                    <td class="px-5 py-4 text-right text-gray-400 dark:text-slate-500 text-xs"><?= format_currency((float) $p['platform_fee']) ?></td>
                                    <td class="px-5 py-4 text-right font-semibold text-emerald-600 dark:text-emerald-400"><?= format_currency((float) $p['freelancer_net']) ?></td>
                                    <td class="px-5 py-4 text-center"><span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium <?= $paymentStatusColors[$p['status']] ?? '' ?>"><?= ucfirst($p['status']) ?></span></td>
                                    <td class="px-5 py-4 text-right text-gray-400 dark:text-slate-500 text-xs"><?= time_ago($p['created_at']) ?></td>
                                </tr>
                            <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php else: ?>
                    <div class="text-center py-10">
                        <i data-lucide="credit-card" class="text-gray-300 dark:text-slate-600 text-2xl mb-2"></i>
                        <p class="text-gray-400 dark:text-slate-500 text-sm">No payments yet</p>
                    </div>
                <?php endif; ?>
                </div>
            </div>

        </div>

        <!-- ═══ RIGHT COLUMN ═══════════════════════════════════════════ -->
        <div class="space-y-6">
            <!-- Client Information -->
            <div class="bg-white rounded-lg border border-gray-100 shadow-sm p-5 fade-in" style="animation-delay:.05s">
                <h3 class="text-xs font-semibold text-gray-400 uppercase tracking-wider mb-4">Client</h3>
                <a href="user_detail.php?id=<?= (int) $milestone['client_user_id'] ?>" class="flex items-center gap-3 no-underline text-inherit">
                    <img src="<?= sanitize_string(get_profile_image($milestone['client_image'])) ?>" class="w-11 h-11 rounded-full object-cover border border-gray-200">
                    <div class="min-w-0">
                        <p class="text-sm font-semibold text-gray-900 truncate"><?= sanitize_string($milestone['client_name']) ?></p>
                        <p class="text-xs text-gray-400 truncate"><?= sanitize_string($milestone['client_email']) ?></p>
                    </div>
                </a>
                <a href="user_detail.php?id=<?= (int) $milestone['client_user_id'] ?>" class="mt-4 w-full inline-flex items-center justify-center gap-1.5 px-4 py-2 text-xs font-medium text-gray-700 bg-gray-50 hover:bg-gray-100 rounded-lg transition-colors">
                    <i data-lucide="user" class="text-[10px]"></i> View Profile
                </a>
            </div>

            <!-- Freelancer Information -->
            <div class="bg-white rounded-lg border border-gray-100 shadow-sm p-5 fade-in" style="animation-delay:.08s">
                <h3 class="text-xs font-semibold text-gray-400 uppercase tracking-wider mb-4">Freelancer</h3>
                <a href="user_detail.php?id=<?= (int) $milestone['freelancer_user_id'] ?>" class="flex items-center gap-3 no-underline text-inherit">
                    <img src="<?= sanitize_string(get_profile_image($milestone['freelancer_image'])) ?>" class="w-11 h-11 rounded-full object-cover border border-gray-200">
                    <div class="min-w-0">
                        <p class="text-sm font-semibold text-gray-900 truncate"><?= sanitize_string($milestone['freelancer_name']) ?></p>
                        <p class="text-xs text-gray-400 truncate"><?= sanitize_string($milestone['freelancer_email']) ?></p>
                    </div>
                </a>
                <a href="user_detail.php?id=<?= (int) $milestone['freelancer_user_id'] ?>" class="mt-4 w-full inline-flex items-center justify-center gap-1.5 px-4 py-2 text-xs font-medium text-gray-700 bg-gray-50 hover:bg-gray-100 rounded-lg transition-colors">
                    <i data-lucide="user" class="text-[10px]"></i> View Profile
                </a>
            </div>
        </div>

    </div> <!-- close main grid -->

</div> <!-- close background wrapper -->

<?php if ($milestone['status'] === 'disputed'): ?>
<script>
    var CSRF_TOKEN = '<?= generate_csrf_token() ?>';
    var BASE_URL = '/jobhub';
    var MILESTONE_ID = <?= (int) $milestone['id'] ?>;
    var MILESTONE_AMOUNT = <?= json_encode(format_currency((float) $milestone['amount'])) ?>;
    var pendingResolution = null;

    function openResolveModal(resolution) {
        pendingResolution = resolution;
        var modal = document.getElementById('resolveModal');
        var icon = document.getElementById('resolveModalIcon');
        var title = document.getElementById('resolveModalTitle');
        var desc = document.getElementById('resolveModalDesc');
        var amount = document.getElementById('resolveModalAmount');
        var btn = document.getElementById('resolveConfirmBtn');
        var btnText = document.getElementById('resolveConfirmBtnText');

        if (resolution === 'release') {
            icon.className = 'w-14 h-14 rounded-2xl bg-emerald-100 dark:bg-emerald-900/30 flex items-center justify-center mx-auto mb-4';
            icon.innerHTML = '<i data-lucide="check-circle" class="text-emerald-500 w-7 h-7"></i>';
            title.textContent = 'Release Payment to Freelancer?';
            desc.textContent = 'The milestone payment will be released to the freelancer\'s wallet. This action cannot be undone.';
            amount.textContent = MILESTONE_AMOUNT;
            btn.className = 'flex-1 px-4 py-2.5 rounded-xl bg-emerald-500 hover:bg-emerald-600 text-white text-sm font-semibold shadow-lg shadow-emerald-500/25 transition-colors';
            btnText.textContent = 'Release Payment';
        } else {
            icon.className = 'w-14 h-14 rounded-2xl bg-red-100 dark:bg-red-900/30 flex items-center justify-center mx-auto mb-4';
            icon.innerHTML = '<i data-lucide="rotate-ccw" class="text-red-500 w-7 h-7"></i>';
            title.textContent = 'Refund Client?';
            desc.textContent = 'The full milestone amount will be returned to the client\'s wallet. This action cannot be undone.';
            amount.textContent = MILESTONE_AMOUNT;
            btn.className = 'flex-1 px-4 py-2.5 rounded-xl bg-red-500 hover:bg-red-600 text-white text-sm font-semibold shadow-lg shadow-red-500/25 transition-colors';
            btnText.textContent = 'Refund Client';
        }

        modal.classList.remove('hidden');
        lucide.createIcons();
    }

    function closeResolveModal() {
        document.getElementById('resolveModal').classList.add('hidden');
        pendingResolution = null;
    }

    function showLoading() {
        var overlay = document.createElement('div');
        overlay.id = 'loadingOverlay';
        overlay.className = 'fixed inset-0 z-[60] bg-white/70 dark:bg-slate-900/70 flex items-center justify-center';
        overlay.innerHTML = '<div class="w-10 h-10 border-4 border-indigo-500 border-t-transparent rounded-full animate-spin"></div>';
        document.body.appendChild(overlay);
    }

    function hideLoading() {
        var overlay = document.getElementById('loadingOverlay');
        if (overlay) overlay.remove();
    }

    function showToast(type, message) {
        var c = { success: 'bg-emerald-500', error: 'bg-red-500', info: 'bg-[#4338CA]' };
        var ic = { success: 'circle-check', error: 'circle-alert', info: 'info' };
        var t = document.createElement('div');
        t.className = 'fixed top-4 right-4 z-[70] flex items-center gap-3 px-5 py-3 rounded-xl text-white text-sm font-semibold shadow-2xl ' + c[type] + ' transition-all transform translate-x-full';
        t.innerHTML = '<i data-lucide="' + ic[type] + '"></i> ' + message;
        document.body.appendChild(t);
        lucide.createIcons();
        requestAnimationFrame(function() { t.classList.remove('translate-x-full') });
        setTimeout(function() {
            t.classList.add('translate-x-full');
            setTimeout(function() { t.remove() }, 300);
        }, 3500);
    }

    async function confirmResolve() {
        if (!pendingResolution) return;
        var resolution = pendingResolution;
        closeResolveModal();
        showLoading();
        try {
            var fd = new FormData();
            fd.append('action', 'resolve_dispute');
            fd.append('milestone_id', MILESTONE_ID);
            fd.append('resolution', resolution);
            fd.append('csrf_token', CSRF_TOKEN);
            var r = await fetch(BASE_URL + '/api/milestones_api.php', { method: 'POST', body: fd });
            var j = await r.json();
            hideLoading();
            if (j.success) {
                showToast('success', j.message);
                setTimeout(function() { location.reload() }, 1200);
            } else {
                showToast('error', j.message);
            }
        } catch (err) {
            hideLoading();
            showToast('error', 'Network error. Please try again.');
        }
    }
</script>
<?php endif; ?>

<?php require_once __DIR__ . '/../components/layout_end.php'; ?>
