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
$mIC = ['pending' => 'fa-clock', 'funded_in_escrow' => 'fa-shield-halved', 'submitted' => 'fa-paper-plane', 'released' => 'fa-check-circle', 'disputed' => 'fa-exclamation-triangle'];
$cC = ['active' => 'bg-emerald-50 text-emerald-600 border border-emerald-200', 'completed' => 'bg-blue-50 text-blue-600 border border-blue-200', 'cancelled' => 'bg-gray-100 text-gray-500 border border-gray-200', 'disputed' => 'bg-red-50 text-red-500 border border-red-200'];

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
$pageTitle = sanitize_string($contract['job_title']) . ' - Contract Details';
$pageSubtitle = 'Manage milestones and payments';
$activePage = 'contracts';
$user = ['name' => $user['name'] ?? 'Client', 'profile_image' => $user['profile_image'] ?? null];
$unreadCount = $unreadMessages ?? 0;
$profileLink = 'profile.php';
require_once __DIR__ . '/../components/layout_start.php';
?>
    <?php display_flash('success') ?>
    <?php display_flash('error') ?>
    <div class="bg-white rounded-2xl border border-gray-100 shadow-sm fade-in">
        <div class="p-6 sm:p-8">
            <div class="flex flex-col sm:flex-row sm:items-start justify-between gap-4 mb-6">
                <div class="flex-1">
                    <div class="flex flex-wrap items-center gap-2 mb-3">
                        <h2 class="text-xl font-bold text-gray-900"><?= sanitize_string($contract['job_title']) ?></h2>
                        <span class="inline-block px-3 py-1 rounded-lg text-[11px] font-semibold <?= $cC[$contract['status']] ?? '' ?>"><?= sanitize_string(ucfirst($contract['status'])) ?></span>
                    </div>
                    <div class="flex flex-wrap items-center gap-4 text-sm text-gray-400">
                        <span class="flex items-center gap-1.5"><i class="fas fa-dollar-sign text-emerald-500"></i><span class="font-bold text-gray-700"><?= format_currency((float) $contract['total_budget']) ?></span></span>
                        <span class="flex items-center gap-1.5"><i class="fas fa-user text-blue-400"></i><?= sanitize_string($contract['freelancer_name']) ?></span>
                        <span class="flex items-center gap-1.5"><i class="fas fa-calendar text-gray-400"></i>Started <?= date('M d, Y', strtotime($contract['created_at'])) ?></span>
                    </div>
                </div>
            </div>
            <div class="grid grid-cols-2 sm:grid-cols-4 gap-4">
                <div class="bg-gray-50 rounded-xl p-4 border border-gray-100">
                    <p class="text-xs text-gray-400 mb-1">Total Budget</p>
                    <p class="text-lg font-black text-gray-900"><?= format_currency((float) $contract['total_budget']) ?></p>
                </div>
                <div class="bg-gray-50 rounded-xl p-4 border border-gray-100">
                    <p class="text-xs text-gray-400 mb-1">Allocated</p>
                    <p class="text-lg font-black text-amber-600"><?= format_currency($totalMA) ?></p>
                </div>
                <div class="bg-gray-50 rounded-xl p-4 border border-gray-100">
                    <p class="text-xs text-gray-400 mb-1">Remaining</p>
                    <p class="text-lg font-black <?= $budgetRem >= 0 ? 'text-emerald-600' : 'text-red-600' ?>"><?= format_currency($budgetRem) ?></p>
                </div>
                <div class="bg-gray-50 rounded-xl p-4 border border-gray-100">
                    <p class="text-xs text-gray-400 mb-1">Your Wallet</p>
                    <p class="text-lg font-black text-blue-600"><?= format_currency($walletBalance) ?></p>
                </div>
            </div>
        </div>
    </div>
    <div class="bg-white rounded-2xl border border-gray-100 shadow-sm fade-in" style="animation-delay:.1s">
        <div class="p-6 sm:p-8">
            <div class="flex items-center justify-between mb-6">
                <div class="flex items-center gap-3">
                    <div class="w-10 h-10 rounded-xl bg-violet-50 flex items-center justify-center"><i class="fas fa-tasks text-violet-500"></i></div>
                    <div>
                        <h3 class="text-base font-bold text-gray-900">Milestones</h3>
                        <p class="text-xs text-gray-400"><?= count($milestones) ?> milestone<?= count($milestones) !== 1 ? 's' : '' ?></p>
                    </div>
                </div>
                <?php if ($contract['status'] === 'active' && $budgetRem > 0): ?>
                    <button onclick="openModal('createModal')" class="btn-grad inline-flex items-center gap-2 text-white text-xs font-semibold px-4 py-2 rounded-xl shadow-lg shadow-blue-500/25"><i class="fas fa-plus text-[10px]"></i> Add Milestone</button>
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
                                        <span class="inline-block px-2.5 py-0.5 rounded-lg text-[10px] font-semibold <?= $mSC[$m['status']] ?? '' ?>"><i class="fas <?= $mIC[$m['status']] ?? 'fa-circle' ?> mr-1 text-[8px]"></i> <?= sanitize_string(ucfirst(str_replace('_', ' ', $m['status']))) ?></span>
                                    </div>
                                    <div class="flex items-center gap-4 text-xs text-gray-400">
                                        <span class="font-bold text-gray-700 text-sm"><?= format_currency((float) $m['amount']) ?></span>
                                        <span><?= date('M d, Y', strtotime($m['created_at'])) ?></span>
                                    </div>
                                </div>
                                <div class="flex flex-wrap gap-2">
                                    <?php if ($m['status'] === 'pending'): ?>
                                        <button onclick="openEditModal(<?= (int) $m['id'] ?>, '<?= sanitize_string(addslashes($m['title'])) ?>', <?= (float) $m['amount'] ?>)" class="inline-flex items-center gap-1.5 px-3 py-1.5 bg-gray-100 hover:bg-gray-200 text-gray-600 text-xs font-semibold rounded-lg transition-all"><i class="fas fa-pen text-[9px]"></i> Edit</button>
                                        <button onclick="fundMilestone(<?= (int) $m['id'] ?>, <?= (float) $m['amount'] ?>)" class="inline-flex items-center gap-1.5 px-3 py-1.5 btn-grad text-white text-xs font-semibold rounded-lg shadow-md shadow-blue-500/20"><i class="fas fa-wallet text-[9px]"></i> Fund Escrow</button>
                                    <?php endif; ?>
                                    <?php if ($m['status'] === 'submitted'): ?>
                                        <button onclick="approveMilestone(<?= (int) $m['id'] ?>)" class="inline-flex items-center gap-1.5 px-3 py-1.5 bg-emerald-500 hover:bg-emerald-600 text-white text-xs font-semibold rounded-lg transition-all shadow-md shadow-emerald-500/20"><i class="fas fa-check text-[9px]"></i> Approve &amp; Release</button>
                                    <?php endif; ?>
                                    <?php if ($m['status'] !== 'released' && $m['status'] !== 'disputed'): ?>
                                        <button onclick="disputeMilestone(<?= (int) $m['id'] ?>)" class="inline-flex items-center gap-1.5 px-3 py-1.5 bg-red-50 hover:bg-red-100 text-red-500 text-xs font-semibold rounded-lg transition-all border border-red-200"><i class="fas fa-flag text-[9px]"></i> Dispute</button>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php else: ?>
                <div class="text-center py-12">
                    <div class="w-16 h-16 rounded-2xl bg-gray-100 flex items-center justify-center mx-auto mb-4"><i class="fas fa-tasks text-2xl text-gray-300"></i></div>
                    <p class="text-gray-500 text-sm font-medium mb-1">No milestones yet</p>
                    <p class="text-gray-400 text-xs">Break down the project into manageable milestones</p>
                </div>
            <?php endif; ?>
        </div>
    </div>

<div id="createModal" class="fixed inset-0 z-50 hidden">
    <div class="modal-overlay absolute inset-0" onclick="closeModal('createModal')"></div>
    <div class="absolute inset-0 flex items-center justify-center p-4">
        <div class="bg-white rounded-2xl shadow-2xl w-full max-w-md relative z-10 slide-down">
            <div class="p-6 border-b border-gray-100">
                <div class="flex items-center justify-between">
                    <h3 class="text-lg font-bold text-gray-900">Add Milestone</h3><button onclick="closeModal('createModal')" class="w-8 h-8 rounded-lg bg-gray-100 flex items-center justify-center text-gray-400 hover:text-gray-600"><i class="fas fa-times text-sm"></i></button>
                </div>
            </div>
            <form id="createMilestoneForm" onsubmit="return createMilestone(event)">
                <div class="p-6 space-y-4">
                    <?= csrf_field() ?>
                    <input type="hidden" name="action" value="create">
                    <input type="hidden" name="contract_id" value="<?= $contractId ?>">
                    <div><label class="block text-xs font-semibold text-gray-700 mb-1.5">Milestone Title</label><input type="text" name="title" required maxlength="255" placeholder="e.g., Homepage Design" class="fld w-full px-4 py-2.5 rounded-xl border border-gray-200 text-sm bg-gray-50 focus:bg-white"></div>
                    <div><label class="block text-xs font-semibold text-gray-700 mb-1.5">Amount ($)</label><input type="number" name="amount" required step="0.01" min="0.01" max="<?= $budgetRem ?>" placeholder="0.00" class="fld w-full px-4 py-2.5 rounded-xl border border-gray-200 text-sm bg-gray-50 focus:bg-white">
                        <p class="text-[11px] text-gray-400 mt-1">Remaining budget: <?= format_currency($budgetRem) ?></p>
                    </div>
                </div>
                <div class="px-6 pb-6 flex gap-3">
                    <button type="button" onclick="closeModal('createModal')" class="flex-1 px-4 py-2.5 rounded-xl border border-gray-200 text-sm font-semibold text-gray-600 hover:bg-gray-50">Cancel</button>
                    <button type="submit" class="flex-1 btn-grad px-4 py-2.5 rounded-xl text-white text-sm font-semibold shadow-lg shadow-blue-500/25">Create Milestone</button>
                </div>
            </form>
        </div>
    </div>
</div>

<div id="editModal" class="fixed inset-0 z-50 hidden">
    <div class="modal-overlay absolute inset-0" onclick="closeModal('editModal')"></div>
    <div class="absolute inset-0 flex items-center justify-center p-4">
        <div class="bg-white rounded-2xl shadow-2xl w-full max-w-md relative z-10 slide-down">
            <div class="p-6 border-b border-gray-100">
                <div class="flex items-center justify-between">
                    <h3 class="text-lg font-bold text-gray-900">Edit Milestone</h3><button onclick="closeModal('editModal')" class="w-8 h-8 rounded-lg bg-gray-100 flex items-center justify-center text-gray-400 hover:text-gray-600"><i class="fas fa-times text-sm"></i></button>
                </div>
            </div>
            <form id="editMilestoneForm" onsubmit="return updateMilestone(event)">
                <div class="p-6 space-y-4">
                    <?= csrf_field() ?>
                    <input type="hidden" name="action" value="update">
                    <input type="hidden" name="milestone_id" id="edit_milestone_id">
                    <div><label class="block text-xs font-semibold text-gray-700 mb-1.5">Milestone Title</label><input type="text" name="title" id="edit_title" required maxlength="255" class="fld w-full px-4 py-2.5 rounded-xl border border-gray-200 text-sm bg-gray-50 focus:bg-white"></div>
                    <div><label class="block text-xs font-semibold text-gray-700 mb-1.5">Amount ($)</label><input type="number" name="amount" id="edit_amount" required step="0.01" min="0.01" class="fld w-full px-4 py-2.5 rounded-xl border border-gray-200 text-sm bg-gray-50 focus:bg-white"></div>
                </div>
                <div class="px-6 pb-6 flex gap-3">
                    <button type="button" onclick="closeModal('editModal')" class="flex-1 px-4 py-2.5 rounded-xl border border-gray-200 text-sm font-semibold text-gray-600 hover:bg-gray-50">Cancel</button>
                    <button type="submit" class="flex-1 btn-grad px-4 py-2.5 rounded-xl text-white text-sm font-semibold shadow-lg shadow-blue-500/25">Update Milestone</button>
                </div>
            </form>
        </div>
    </div>
</div>

<div id="fundModal" class="fixed inset-0 z-50 hidden">
    <div class="modal-overlay absolute inset-0" onclick="closeModal('fundModal')"></div>
    <div class="absolute inset-0 flex items-center justify-center p-4">
        <div class="bg-white rounded-2xl shadow-2xl w-full max-w-sm relative z-10 slide-down">
            <div class="p-6 text-center">
                <div class="w-16 h-16 rounded-2xl bg-amber-100 flex items-center justify-center mx-auto mb-4"><i class="fas fa-shield-halved text-2xl text-amber-600"></i></div>
                <h3 class="text-lg font-bold text-gray-900 mb-2">Fund Escrow</h3>
                <p class="text-sm text-gray-500 mb-1">Place <span id="fundAmount" class="font-bold text-gray-900"></span> in escrow?</p>
                <p class="text-xs text-gray-400">Deducted from your wallet and held until milestone completion.</p>
            </div>
            <div class="px-6 pb-6 flex gap-3">
                <button onclick="closeModal('fundModal')" class="flex-1 px-4 py-2.5 rounded-xl border border-gray-200 text-sm font-semibold text-gray-600 hover:bg-gray-50">Cancel</button>
                <button onclick="confirmFund()" id="fundConfirmBtn" class="flex-1 btn-grad px-4 py-2.5 rounded-xl text-white text-sm font-semibold shadow-lg shadow-blue-500/25"><span id="fundBtnText">Confirm Fund</span></button>
            </div>
        </div>
    </div>
</div>

<div id="approveModal" class="fixed inset-0 z-50 hidden">
    <div class="modal-overlay absolute inset-0" onclick="closeModal('approveModal')"></div>
    <div class="absolute inset-0 flex items-center justify-center p-4">
        <div class="bg-white rounded-2xl shadow-2xl w-full max-w-sm relative z-10 slide-down">
            <div class="p-6 text-center">
                <div class="w-16 h-16 rounded-2xl bg-emerald-100 flex items-center justify-center mx-auto mb-4"><i class="fas fa-check-circle text-2xl text-emerald-600"></i></div>
                <h3 class="text-lg font-bold text-gray-900 mb-2">Approve &amp; Release Payment</h3>
                <p class="text-sm text-gray-500">This will release payment to the freelancer. Cannot be undone.</p>
            </div>
            <div class="px-6 pb-6 flex gap-3">
                <button onclick="closeModal('approveModal')" class="flex-1 px-4 py-2.5 rounded-xl border border-gray-200 text-sm font-semibold text-gray-600 hover:bg-gray-50">Cancel</button>
                <button onclick="confirmApprove()" id="approveConfirmBtn" class="flex-1 px-4 py-2.5 rounded-xl bg-emerald-500 hover:bg-emerald-600 text-white text-sm font-semibold shadow-lg shadow-emerald-500/25"><span id="approveBtnText">Approve</span></button>
            </div>
        </div>
    </div>
</div>

<div id="loadingOverlay" class="fixed inset-0 z-[60] hidden modal-overlay flex items-center justify-center">
    <div class="bg-white rounded-2xl p-8 shadow-2xl text-center">
        <div class="w-12 h-12 border-4 border-blue-200 border-t-blue-600 rounded-full animate-spin mx-auto mb-4"></div>
        <p class="text-sm font-semibold text-gray-700">Processing...</p>
    </div>
</div>

<script>
    var CSRF_TOKEN = '<?= generate_csrf_token() ?>';
    var BASE_URL = '/finalproject';
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

    function openEditModal(id, title, amount) {
        document.getElementById('edit_milestone_id').value = id;
        document.getElementById('edit_title').value = title;
        document.getElementById('edit_amount').value = amount;
        openModal('editModal')
    }

    function showToast(type, message) {
        var c = {
            success: 'bg-emerald-500',
            error: 'bg-red-500',
            info: 'bg-blue-500'
        };
        var ic = {
            success: 'fa-check-circle',
            error: 'fa-exclamation-circle',
            info: 'fa-info-circle'
        };
        var t = document.createElement('div');
        t.className = 'fixed top-4 right-4 z-[70] flex items-center gap-3 px-5 py-3 rounded-xl text-white text-sm font-semibold shadow-2xl ' + c[type] + ' transition-all transform translate-x-full';
        t.innerHTML = '<i class="fas ' + ic[type] + '"></i> ' + message;
        document.body.appendChild(t);
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
        data.append('csrf_token', CSRF_TOKEN);
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
        data.append('csrf_token', CSRF_TOKEN);
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
    async function disputeMilestone(id) {
        if (!confirm('Dispute this milestone? An admin will review.')) return;
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
</script>
<?php require_once __DIR__ . '/../components/layout_end.php'; ?>
