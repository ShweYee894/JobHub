<?php
$activePage = 'contracts';
require_once __DIR__ . '/../auth/auth.php';
require_role('freelancer');
require_once __DIR__ . '/../config/db.php';
$userId = $_SESSION['user_id'];
$contractId = intval($_GET['id'] ?? 0);
if ($contractId <= 0) { set_flash('error', 'Invalid contract.'); redirect('contracts.php'); }
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
if (!$contract) { set_flash('error', 'Contract not found.'); redirect('contracts.php'); }
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
$conn->close();
$totalMA = 0;
foreach ($milestones as $m) { $totalMA += (float) $m['amount']; }
$mSC = ['pending'=>'bg-gray-100 text-gray-600 border border-gray-200','funded_in_escrow'=>'bg-amber-50 text-amber-600 border border-amber-200','submitted'=>'bg-blue-50 text-blue-600 border border-blue-200','released'=>'bg-emerald-50 text-emerald-600 border border-emerald-200','disputed'=>'bg-red-50 text-red-500 border border-red-200'];
$mIC = ['pending'=>'fa-clock','funded_in_escrow'=>'fa-shield-halved','submitted'=>'fa-paper-plane','released'=>'fa-check-circle','disputed'=>'fa-exclamation-triangle'];
$cC = ['active'=>'bg-emerald-50 text-emerald-600 border border-emerald-200','completed'=>'bg-blue-50 text-blue-600 border border-blue-200','cancelled'=>'bg-gray-100 text-gray-500 border border-gray-200','disputed'=>'bg-red-50 text-red-500 border border-red-200'];

$navItems = [
    ['key' => 'dashboard', 'label' => 'Dashboard', 'url' => 'dashboard.php', 'icon' => 'fa-th-large'],
    ['key' => 'profile', 'label' => 'Profile', 'url' => 'profile.php', 'icon' => 'fa-user'],
    ['key' => 'browse_jobs', 'label' => 'Browse Jobs', 'url' => 'browse_jobs.php', 'icon' => 'fa-search'],
    ['key' => 'proposals', 'label' => 'Proposals', 'url' => 'proposals.php', 'icon' => 'fa-file-alt'],
    ['key' => 'contracts', 'label' => 'Contracts', 'url' => 'contracts.php', 'icon' => 'fa-handshake'],
    ['key' => 'messages', 'label' => 'Messages', 'url' => 'messages.php', 'icon' => 'fa-comment-dots'],
    ['key' => 'earnings', 'label' => 'Earnings', 'url' => 'earnings.php', 'icon' => 'fa-wallet'],
];
$pageTitle = sanitize_string($contract['job_title']);
$pageSubtitle = 'Contract Details';
$activePage = 'contracts';
$user = ['name' => $user['name'] ?? 'Freelancer', 'profile_image' => $user['profile_image'] ?? null];
$unreadCount = $unreadMessages ?? 0;
$profileLink = 'profile.php';
require_once __DIR__ . '/../components/layout_start.php';
?>
<?= display_flash('success') ?>
<?= display_flash('error') ?>
<div class="bg-white rounded-2xl border border-gray-100 shadow-sm fade-in">
<div class="p-6 sm:p-8">
<div class="flex flex-col sm:flex-row sm:items-start justify-between gap-4 mb-6">
<div class="flex-1">
<div class="flex flex-wrap items-center gap-2 mb-3">
<h2 class="text-xl font-bold text-gray-900"><?= sanitize_string($contract['job_title']) ?></h2>
<span class="inline-block px-3 py-1 rounded-lg text-[11px] font-semibold <?= $cC[$contract['status']] ?? '' ?>"><?= sanitize_string(ucfirst($contract['status'])) ?></span>
</div>
<div class="flex flex-wrap items-center gap-4 text-sm text-gray-400">
<span class="flex items-center gap-1.5"><i class="fas fa-dollar-sign text-emerald-500"></i><span class="font-bold text-gray-700"><?= format_currency((float)$contract['total_budget']) ?></span></span>
<span class="flex items-center gap-1.5"><i class="fas fa-user text-blue-400"></i><?= sanitize_string($contract['client_name']) ?></span>
<span class="flex items-center gap-1.5"><i class="fas fa-calendar text-gray-400"></i>Started <?= date('M d, Y', strtotime($contract['created_at'])) ?></span>
</div>
</div>
</div>
<div class="grid grid-cols-2 sm:grid-cols-3 gap-4">
<div class="bg-gray-50 rounded-xl p-4 border border-gray-100"><p class="text-xs text-gray-400 mb-1">Total Value</p><p class="text-lg font-black text-gray-900"><?= format_currency((float)$contract['total_budget']) ?></p></div>
<div class="bg-gray-50 rounded-xl p-4 border border-gray-100"><p class="text-xs text-gray-400 mb-1">Milestones</p><p class="text-lg font-black text-violet-600"><?= count($milestones) ?></p></div>
<div class="bg-gray-50 rounded-xl p-4 border border-gray-100"><p class="text-xs text-gray-400 mb-1">Your Wallet</p><p class="text-lg font-black text-blue-600"><?= format_currency($walletBalance) ?></p></div>
</div>
</div>
</div>
<div class="bg-white rounded-2xl border border-gray-100 shadow-sm fade-in" style="animation-delay:.1s">
<div class="p-6 sm:p-8">
<div class="flex items-center gap-3 mb-6">
<div class="w-10 h-10 rounded-xl bg-violet-50 flex items-center justify-center"><i class="fas fa-tasks text-violet-500"></i></div>
<div><h3 class="text-base font-bold text-gray-900">Milestones</h3><p class="text-xs text-gray-400"><?= count($milestones) ?> milestone<?= count($milestones) !== 1 ? 's' : '' ?></p></div>
</div>
<?php if (!empty($milestones)): ?>
<div class="space-y-4">
<?php foreach ($milestones as $i => $m): ?>
<div class="border border-gray-100 rounded-xl p-5 hover:bg-gray-50/50 transition-colors" id="milestone-<?= (int)$m['id'] ?>">
<div class="flex flex-col sm:flex-row sm:items-center gap-4">
<div class="flex items-center justify-center w-10 h-10 rounded-xl bg-gradient-to-br from-blue-500 to-cyan-500 flex-shrink-0"><span class="text-white text-sm font-bold"><?= $i + 1 ?></span></div>
<div class="flex-1 min-w-0">
<div class="flex flex-wrap items-center gap-2 mb-1">
<h4 class="text-sm font-bold text-gray-900"><?= sanitize_string($m['title']) ?></h4>
<span class="inline-block px-2.5 py-0.5 rounded-lg text-[10px] font-semibold <?= $mSC[$m['status']] ?? '' ?>"><i class="fas <?= $mIC[$m['status']] ?? 'fa-circle' ?> mr-1 text-[8px]"></i> <?= sanitize_string(ucfirst(str_replace('_', ' ', $m['status']))) ?></span>
</div>
<div class="flex items-center gap-4 text-xs text-gray-400">
<span class="font-bold text-gray-700 text-sm"><?= format_currency((float)$m['amount']) ?></span>
<span><?= date('M d, Y', strtotime($m['created_at'])) ?></span>
</div>
<?php if ($m['status'] === 'funded_in_escrow'): ?>
<p class="text-xs text-amber-600 mt-2"><i class="fas fa-info-circle mr-1"></i>Payment is held in escrow. Submit your work when ready.</p>
<?php endif; ?>
<?php if ($m['status'] === 'submitted'): ?>
<p class="text-xs text-blue-600 mt-2"><i class="fas fa-clock mr-1"></i>Work submitted. Awaiting client approval.</p>
<?php endif; ?>
<?php if ($m['status'] === 'released'): ?>
<p class="text-xs text-emerald-600 mt-2"><i class="fas fa-check-circle mr-1"></i>Payment released to your wallet.</p>
<?php endif; ?>
</div>
<div class="flex flex-wrap gap-2">
<?php if ($m['status'] === 'funded_in_escrow'): ?>
<button onclick="submitMilestone(<?= (int)$m['id'] ?>)" class="inline-flex items-center gap-1.5 px-3 py-1.5 btn-grad text-white text-xs font-semibold rounded-lg shadow-md shadow-blue-500/20"><i class="fas fa-paper-plane text-[9px]"></i> Submit Work</button>
<?php endif; ?>
<?php if ($m['status'] !== 'released' && $m['status'] !== 'disputed' && $m['status'] !== 'pending'): ?>
<button onclick="disputeMilestone(<?= (int)$m['id'] ?>)" class="inline-flex items-center gap-1.5 px-3 py-1.5 bg-red-50 hover:bg-red-100 text-red-500 text-xs font-semibold rounded-lg transition-all border border-red-200"><i class="fas fa-flag text-[9px]"></i> Dispute</button>
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
<p class="text-gray-400 text-xs">The client will add milestones to this contract</p>
</div>
<?php endif; ?>
</div>
</div>

<div id="loadingOverlay" class="fixed inset-0 z-[60] hidden modal-overlay flex items-center justify-center">
<div class="bg-white rounded-2xl p-8 shadow-2xl text-center"><div class="w-12 h-12 border-4 border-blue-200 border-t-blue-600 rounded-full animate-spin mx-auto mb-4"></div><p class="text-sm font-semibold text-gray-700">Processing...</p></div>
</div>

<script>
var CSRF_TOKEN='<?= generate_csrf_token() ?>';
var BASE_URL='/finalproject';
function openModal(id){document.getElementById(id).classList.remove('hidden')}
function closeModal(id){document.getElementById(id).classList.add('hidden')}
function showLoading(){document.getElementById('loadingOverlay').classList.remove('hidden')}
function hideLoading(){document.getElementById('loadingOverlay').classList.add('hidden')}
function showToast(type,message){
var c={success:'bg-emerald-500',error:'bg-red-500',info:'bg-blue-500'};
var ic={success:'fa-check-circle',error:'fa-exclamation-circle',info:'fa-info-circle'};
var t=document.createElement('div');
t.className='fixed top-4 right-4 z-[70] flex items-center gap-3 px-5 py-3 rounded-xl text-white text-sm font-semibold shadow-2xl '+c[type]+' transition-all transform translate-x-full';
t.innerHTML='<i class="fas '+ic[type]+'"></i> '+message;
document.body.appendChild(t);
requestAnimationFrame(function(){t.classList.remove('translate-x-full')});
setTimeout(function(){t.classList.add('translate-x-full');setTimeout(function(){t.remove()},300)},3500);
}
function reloadPage(){setTimeout(function(){location.reload()},1200)}
async function submitMilestone(id){
if(!confirm('Submit work for this milestone? The client will be notified to review.'))return;
showLoading();
try{var fd=new FormData();fd.append('action','submit');fd.append('milestone_id',id);fd.append('csrf_token',CSRF_TOKEN);
var r=await fetch(BASE_URL+'/api/milestones_api.php',{method:'POST',body:fd});var j=await r.json();hideLoading();
if(j.success){showToast('success',j.message);reloadPage()}else{showToast('error',j.message)}}
catch(err){hideLoading();showToast('error','Network error.')}}
async function disputeMilestone(id){
if(!confirm('Dispute this milestone? An admin will review.'))return;showLoading();
try{var fd=new FormData();fd.append('action','dispute');fd.append('milestone_id',id);fd.append('csrf_token',CSRF_TOKEN);
var r=await fetch(BASE_URL+'/api/milestones_api.php',{method:'POST',body:fd});var j=await r.json();hideLoading();
if(j.success){showToast('info',j.message);reloadPage()}else{showToast('error',j.message)}}
catch(err){hideLoading();showToast('error','Network error.')}}
</script>
<?php require_once __DIR__ . '/../components/layout_end.php'; ?>
