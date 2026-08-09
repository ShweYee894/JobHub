<?php

/** Admin Contract Detail — Read-only view of a single contract */
require_once __DIR__ . '/../auth/auth.php';
require_role('admin');
require_once __DIR__ . '/../config/db.php';

$currentPage = 'contracts';
$contractId = sanitize_int($_GET['id'] ?? 0);
if ($contractId <= 0) {
    set_flash('error', 'Invalid contract ID.');
    redirect('contracts.php');
}

// ── Fetch contract with all joins ───────────────────────────────────
$cs = $conn->prepare('SELECT c.id, c.contract_type, c.total_budget, c.status, c.created_at, c.updated_at,
                             cu.id AS client_user_id, cu.name AS client_name, cu.profile_image AS client_image, cu.email AS client_email,
                             cl.company_name,
                             fu.id AS freelancer_user_id, fu.name AS freelancer_name, fu.profile_image AS freelancer_image, fu.email AS freelancer_email,
                             fl.title AS freelancer_title,
                             j.id AS job_id, j.title AS job_title, j.budget AS job_budget
                      FROM contracts c
                      JOIN users cu ON c.client_id = cu.id
                      LEFT JOIN clients cl ON c.client_id = cl.client_id
                      JOIN users fu ON c.freelancer_id = fu.id
                      LEFT JOIN freelancers fl ON c.freelancer_id = fl.user_id
                      JOIN jobs j ON c.job_id = j.id
                      WHERE c.id = ?');
$cs->bind_param('i', $contractId);
$cs->execute();
$contract = $cs->get_result()->fetch_assoc();
$cs->close();

if (!$contract) {
    set_flash('error', 'Contract not found.');
    redirect('contracts.php');
}

// ── Fetch milestones ────────────────────────────────────────────────
$ms = $conn->prepare('SELECT id, title, amount, status, created_at FROM milestones WHERE contract_id = ? ORDER BY created_at ASC');
$ms->bind_param('i', $contractId);
$ms->execute();
$milestonesResult = $ms->get_result();
$ms->close();

$milestones = [];
while ($row = $milestonesResult->fetch_assoc())
    $milestones[] = $row;

// ── Fetch payments ──────────────────────────────────────────────────
$milestoneIds = array_column($milestones, 'id');
$payments = [];
if ($milestoneIds) {
    $idPH = implode(',', array_fill(0, count($milestoneIds), '?'));
    $ps = $conn->prepare("SELECT p.id, p.milestone_id, p.total_amount, p.platform_fee, p.freelancer_net, p.status, p.created_at,
                                 m.title AS milestone_title
                          FROM payments p JOIN milestones m ON p.milestone_id = m.id
                          WHERE p.milestone_id IN ({$idPH}) ORDER BY p.created_at DESC");
    $ps->bind_param(str_repeat('i', count($milestoneIds)), ...$milestoneIds);
    $ps->execute();
    $paymentsResult = $ps->get_result();
    while ($row = $paymentsResult->fetch_assoc())
        $payments[] = $row;
    $ps->close();
}

// ── Escrow total ────────────────────────────────────────────────────
$escrowTotal = 0.0;
foreach ($milestones as $m) {
    if (in_array($m['status'], ['funded_in_escrow', 'submitted']))
        $escrowTotal += (float) $m['amount'];
}

// ── Stats ───────────────────────────────────────────────────────────
$totalMs = count($milestones);
$releasedMs = count(array_filter($milestones, fn($m) => $m['status'] === 'released'));
$pendingMs = count(array_filter($milestones, fn($m) => $m['status'] === 'pending'));
$fundedMs = count(array_filter($milestones, fn($m) => $m['status'] === 'funded_in_escrow'));
$totalPayments = count($payments);
$completedPayments = count(array_filter($payments, fn($p) => $p['status'] === 'completed'));

// ── Badge maps ──────────────────────────────────────────────────────
$contractStatusColors = [
    'active' => 'bg-emerald-100 text-emerald-700 dark:bg-emerald-900/30 dark:text-emerald-400',
    'completed' => 'bg-blue-100 text-blue-700 dark:bg-blue-900/30 dark:text-blue-400',
    'disputed' => 'bg-red-100 text-red-700 dark:bg-red-900/30 dark:text-red-400',
    'terminated' => 'bg-gray-100 text-gray-600 dark:bg-slate-700 dark:text-slate-400',
];
$milestoneStatusColors = [
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
$pageTitle = 'Contract #' . $contractId;
$pageSubtitle = 'Contract Details';
$activePage = 'contracts';
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
        <!-- <div></div> -->
         <!-- Back to Contracts -->
            <a href="contracts.php" class="py-2 text-sm font-medium text-gray-700 flex justify-center items-center gap-2">
                <i data-lucide="arrow-left" class="text-xs"></i> Back to Contracts
            </a>
        <a href="contract_print.php?id=<?= $contractId ?>" target="_blank" class="inline-flex items-center gap-2 px-4 py-2 bg-[#108A00] hover:bg-[#0d7500] dark:bg-emerald-600 dark:hover:bg-emerald-700 text-white text-xs font-semibold rounded-lg transition-colors">
            <i data-lucide="file-text"></i> Official Contract
        </a>
    </div>

    <!-- ═══ MAIN GRID ═══════════════════════════════════════════════════ -->
    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">

        <!-- ═══ LEFT COLUMN: HEADER CARD ═══════════════════════════════ -->
        <div class="lg:col-span-2 space-y-6">
            <!-- ═══ CONTRACT OVERVIEW ═══════════════════════════════════ -->
            <div class="bg-white dark:bg-slate-800 border border-[#E4EBE4] dark:border-slate-700 rounded-lg shadow-sm overflow-hidden fade-in">
                <div class="p-6">
                    <div class="flex flex-col sm:flex-row sm:items-start justify-between gap-4 mb-5">
                        <div class="flex-1">
                            <div class="flex flex-wrap items-center gap-2 mb-2">
                                <h2 class="text-xl font-bold text-gray-900 dark:text-white">Contract</h2>
                                <span class="inline-block px-2.5 py-0.5 rounded text-[11px] font-semibold bg-emerald-100 text-emerald-700 dark:bg-emerald-900/30 dark:text-emerald-400"><?= ucfirst($contract['status']) ?></span>
                                <span class="inline-block px-2.5 py-0.5 rounded text-[11px] font-semibold bg-blue-100 text-blue-700 dark:bg-blue-900/30 dark:text-blue-400"><?= ucfirst($contract['contract_type']) ?></span>
                            </div>
                            <div class="flex flex-wrap items-center gap-4 text-sm text-gray-400 dark:text-slate-400">
                                <span class="flex items-center gap-1.5"><i data-lucide="link" class="text-[#108A00]"></i>for <a href="../client/job_detail.php?id=<?= (int) $contract['job_id'] ?>" class="font-bold text-gray-700 dark:text-slate-300 hover:text-[#108A00] dark:hover:text-emerald-400 transition-colors"><?= sanitize_string($contract['job_title']) ?></a></span>
                            </div>
                        </div>
                    </div>
                    <div class="border-t border-gray-100 dark:border-slate-700"></div>
                    <div class="grid grid-cols-2 sm:grid-cols-4 gap-px bg-gray-100 dark:bg-slate-700">
                        <div class="bg-white dark:bg-slate-800 p-4">
                            <p class="text-[10px] font-semibold text-gray-400 dark:text-slate-500 uppercase tracking-wider">Budget</p>
                            <p class="text-sm font-bold text-gray-900 dark:text-white mt-1 text-left"><?= format_currency((float) $contract['total_budget']) ?></p>
                        </div>
                        <div class="bg-white dark:bg-slate-800 p-4">
                            <p class="text-[10px] font-semibold text-gray-400 dark:text-slate-500 uppercase tracking-wider">In Escrow</p>
                            <p class="text-sm font-bold text-gray-900 dark:text-white mt-1 text-left"><?= format_currency($escrowTotal) ?></p>
                        </div>
                        <div class="bg-white dark:bg-slate-800 p-4">
                            <p class="text-[10px] font-semibold text-gray-400 dark:text-slate-500 uppercase tracking-wider">Milestones</p>
                            <p class="text-sm font-bold text-gray-900 dark:text-white mt-1 text-left"><?= $releasedMs ?>/<?= $totalMs ?></p>
                        </div>
                        <div class="bg-white dark:bg-slate-800 p-4">
                            <p class="text-[10px] font-semibold text-gray-400 dark:text-slate-500 uppercase tracking-wider">Payments</p>
                            <p class="text-sm font-bold text-gray-900 dark:text-white mt-1 text-left"><?= $completedPayments ?>/<?= $totalPayments ?></p>
                        </div>
                    </div>
                </div>
            </div>

            <!-- ═══ MILESTONES ═════════════════════════════════════════ -->
            <div class="bg-white dark:bg-slate-800 border border-gray-100 dark:border-slate-700 rounded-lg shadow-sm hover:shadow-md transition-all duration-200 fade-in" style="animation-delay:.11s">
                <div class="px-6 py-4 border-b border-gray-100 dark:border-slate-700">
                    <h3 class="text-sm font-semibold text-gray-900 dark:text-white">Milestones (<?= $totalMs ?>)</h3>
                </div>
                <?php if ($totalMs > 0): ?>
                    <div class="overflow-x-auto">
                        <table class="w-full min-w-full text-sm text-left">
                            <thead>
                                <tr class="bg-gray-50 dark:bg-slate-700/50">
                                    <th class="px-5 py-3 text-xs font-semibold text-gray-500 dark:text-slate-400 uppercase">Title</th>
                                    <th class="px-5 py-3 text-xs font-semibold text-gray-500 dark:text-slate-400 uppercase text-right">Amount</th>
                                    <th class="px-5 py-3 text-xs font-semibold text-gray-500 dark:text-slate-400 uppercase text-center">Status</th>
                                    <th class="px-5 py-3 text-xs font-semibold text-gray-500 dark:text-slate-400 uppercase text-right">Created</th>
                                    <th class="px-5 py-3 text-xs font-semibold text-gray-500 dark:text-slate-400 uppercase text-right">Action</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($milestones as $m): ?>
                                    <tr class="border-b border-gray-50 dark:border-slate-700/50 last:border-0 hover:bg-gray-50 dark:hover:bg-slate-700/30 transition-colors">
                                        <td class="px-5 py-4 font-medium text-gray-900 dark:text-white"><?= sanitize_string($m['title']) ?></td>
                                        <td class="px-5 py-4 text-right font-semibold text-gray-700 dark:text-slate-300"><?= format_currency((float) $m['amount']) ?></td>
                                        <td class="px-5 py-4 text-center">
                                            <?php
                                            $mStatusClass = match ($m['status']) {
                                                'pending' => 'bg-gray-100 text-gray-600 dark:bg-slate-700 dark:text-slate-400',
                                                'funded_in_escrow' => 'bg-amber-50 text-amber-700 border border-amber-200 dark:bg-amber-900/20 dark:text-amber-400 dark:border-amber-800',
                                                'submitted' => 'bg-blue-50 text-blue-700 border border-blue-200 dark:bg-blue-900/20 dark:text-blue-400 dark:border-blue-800',
                                                'released' => 'bg-green-50 text-green-700 border border-green-200 dark:bg-green-900/20 dark:text-green-400 dark:border-green-800',
                                                'disputed' => 'bg-red-50 text-red-700 border border-red-200 dark:bg-red-900/20 dark:text-red-400 dark:border-red-800',
                                                default => 'bg-gray-100 text-gray-600 dark:bg-slate-700 dark:text-slate-400',
                                            };
                                            ?>
                                            <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium <?= $mStatusClass ?>"><?= ucfirst(str_replace('_', ' ', $m['status'])) ?></span>
                                        </td>
                                        <td class="px-5 py-4 text-right text-gray-400 dark:text-slate-500 text-xs"><?= time_ago($m['created_at']) ?></td>
                                        <td class="px-5 py-4 text-right">
                                            <?php if ($m['status'] === 'disputed'): ?>
                                                <a href="milestone_detail.php?id=<?= (int) $m['id'] ?>" class="inline-flex items-center gap-1.5 px-3 py-1.5 bg-red-50 dark:bg-red-900/20 text-red-600 dark:text-red-400 hover:bg-red-100 dark:hover:bg-red-900/40 rounded-lg text-[11px] font-semibold transition-colors">
                                                    <i data-lucide="shield-alert" class="w-3 h-3"></i> Resolve
                                                </a>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php else: ?>
                    <div class="text-center py-10">
                        <i data-lucide="list-checks" class="text-gray-300 dark:text-slate-600 text-2xl mb-2"></i>
                        <p class="text-gray-400 dark:text-slate-500 text-sm">No milestones yet</p>
                    </div>
                <?php endif; ?>
            </div>

            <!-- ═══ PAYMENTS ═════════════════════════════════════════════ -->
            <div class="bg-white rounded-lg border border-gray-100 shadow-sm fade-in" style="animation-delay:.14s">
                <div class="px-6 py-4 border-b border-gray-100 flex items-center justify-between">
                    <h3 class="text-sm font-semibold text-gray-900">Payment History (<?= $totalPayments ?>)</h3>
                    <a href="payments.php?contract_id=<?= $contractId ?>" class="inline-flex items-center gap-1.5 px-3 py-1.5 text-xs font-medium text-[#108A00] bg-green-50 hover:bg-green-100 rounded-lg transition-colors">
                        <i data-lucide="arrow-right" class="text-[10px]"></i> View Transactions
                    </a>
                </div>
                <?php if ($totalPayments > 0): ?>
                    <div class="overflow-x-auto">
                        <table class="w-full min-w-full text-sm text-left">
                            <thead>
                                <tr class="bg-gray-50">
                                    <th class="px-5 py-3 text-xs font-semibold text-gray-500 uppercase">Milestone</th>
                                    <th class="px-5 py-3 text-xs font-semibold text-gray-500 uppercase text-right">Amount</th>
                                    <th class="px-5 py-3 text-xs font-semibold text-gray-500 uppercase text-right">Fee</th>
                                    <th class="px-5 py-3 text-xs font-semibold text-gray-500 uppercase text-right">Net</th>
                                    <th class="px-5 py-3 text-xs font-semibold text-gray-500 uppercase text-center">Status</th>
                                    <th class="px-5 py-3 text-xs font-semibold text-gray-500 uppercase text-right">Date</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($payments as $p): ?>
                                    <tr class="border-b border-gray-50 last:border-0 hover:bg-gray-50 transition-colors">
                                        <td class="px-5 py-4 font-medium text-gray-900 text-xs"><?= sanitize_string($p['milestone_title']) ?></td>
                                        <td class="px-5 py-4 text-right font-semibold text-gray-700"><?= format_currency((float) $p['total_amount']) ?></td>
                                        <td class="px-5 py-4 text-right text-gray-400 text-xs"><?= format_currency((float) $p['platform_fee']) ?></td>
                                        <td class="px-5 py-4 text-right font-semibold text-emerald-600 text-xs"><?= format_currency((float) $p['freelancer_net']) ?></td>
                                        <td class="px-5 py-4 text-center"><span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium <?= $paymentStatusColors[$p['status']] ?? '' ?>"><?= ucfirst($p['status']) ?></span></td>
                                        <td class="px-5 py-4 text-right text-gray-400 text-xs"><?= time_ago($p['created_at']) ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php else: ?>
                    <div class="text-center py-10">
                        <i data-lucide="credit-card" class="text-gray-300 text-2xl mb-2"></i>
                        <p class="text-gray-400 text-sm mb-3">No payments yet</p>
                        <p class="text-gray-300 text-xs">Payments will appear here once milestones are completed.</p>
                    </div>
                <?php endif; ?>
            </div>

        </div>

        <!-- ═══ RIGHT COLUMN ═══════════════════════════════════════════ -->
        <div class="space-y-6">
            <!-- Client Information -->
            <div class="bg-white rounded-lg border border-gray-100 shadow-sm p-5 fade-in" style="animation-delay:.05s">
                <h3 class="text-xs font-semibold text-gray-400 uppercase tracking-wider mb-4">Client</h3>
                <a href="user_detail.php?id=<?= (int) $contract['client_user_id'] ?>" class="flex items-center gap-3 no-underline text-inherit">
                    <img src="<?= sanitize_string(get_profile_image($contract['client_image'])) ?>" class="w-11 h-11 rounded-full object-cover border border-gray-200">
                    <div class="min-w-0">
                        <p class="text-sm font-semibold text-gray-900 truncate"><?= sanitize_string($contract['client_name']) ?></p>
                        <p class="text-xs text-gray-400 truncate"><?= sanitize_string($contract['company_name'] ?? '') ?></p>
                    </div>
                </a>
                <div class="mt-4 pt-4 border-t border-gray-100 space-y-2">
                    <div class="flex items-center gap-2 text-xs text-gray-500">
                        <i data-lucide="mail" class="text-gray-300 w-4"></i>
                        <span class="truncate"><?= sanitize_string($contract['client_email']) ?></span>
                    </div>
                </div>
                <a href="user_detail.php?id=<?= (int) $contract['client_user_id'] ?>" class="mt-4 w-full inline-flex items-center justify-center gap-1.5 px-4 py-2 text-xs font-medium text-gray-700 bg-gray-50 hover:bg-gray-100 rounded-lg transition-colors">
                    <i data-lucide="user" class="text-[10px]"></i> View Profile
                </a>
            </div>

            <!-- Freelancer Information -->
            <div class="bg-white rounded-lg border border-gray-100 shadow-sm p-5 fade-in" style="animation-delay:.08s">
                <h3 class="text-xs font-semibold text-gray-400 uppercase tracking-wider mb-4">Freelancer</h3>
                <a href="user_detail.php?id=<?= (int) $contract['freelancer_user_id'] ?>" class="flex items-center gap-3 no-underline text-inherit">
                    <img src="<?= sanitize_string(get_profile_image($contract['freelancer_image'])) ?>" class="w-11 h-11 rounded-full object-cover border border-gray-200">
                    <div class="min-w-0">
                        <p class="text-sm font-semibold text-gray-900 truncate"><?= sanitize_string($contract['freelancer_name']) ?></p>
                        <p class="text-xs text-gray-400 truncate"><?= sanitize_string($contract['freelancer_title'] ?? '') ?></p>
                    </div>
                </a>
                <div class="mt-4 pt-4 border-t border-gray-100 space-y-2">
                    <div class="flex items-center gap-2 text-xs text-gray-500">
                        <i data-lucide="mail" class="text-gray-300 w-4"></i>
                        <span class="truncate"><?= sanitize_string($contract['freelancer_email']) ?></span>
                    </div>
                </div>
                <a href="user_detail.php?id=<?= (int) $contract['freelancer_user_id'] ?>" class="mt-4 w-full inline-flex items-center justify-center gap-1.5 px-4 py-2 text-xs font-medium text-gray-700 bg-gray-50 hover:bg-gray-100 rounded-lg transition-colors">
                    <i data-lucide="user" class="text-[10px]"></i> View Profile
                </a>
            </div>
        </div>

    </div> <!-- close main grid -->

</div> <!-- close background wrapper -->

<?php require_once __DIR__ . '/../components/layout_end.php'; ?>