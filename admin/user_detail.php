<?php
require_once __DIR__ . '/../auth/auth.php';
require_role('admin');
require_once __DIR__ . '/../config/db.php';

$currentPage = 'users';
$userId = sanitize_int($_GET['id'] ?? 0);
if ($userId <= 0) {
    set_flash('error', 'Invalid user ID.');
    redirect('users.php');
}

$stmt = $conn->prepare('SELECT * FROM users WHERE id = ?');
$stmt->bind_param('i', $userId);
$stmt->execute();
$user = $stmt->get_result()->fetch_assoc();
$stmt->close();
if (!$user) {
    set_flash('error', 'User not found.');
    redirect('users.php');
}

$clientData = null;
$freelancerData = null;
if ($user['role'] === 'client') {
    $cs = $conn->prepare('SELECT * FROM clients WHERE client_id = ?');
    $cs->bind_param('i', $userId);
    $cs->execute();
    $clientData = $cs->get_result()->fetch_assoc();
    $cs->close();
}
if ($user['role'] === 'freelancer') {
    $fs = $conn->prepare('SELECT * FROM freelancers WHERE user_id = ?');
    $fs->bind_param('i', $userId);
    $fs->execute();
    $freelancerData = $fs->get_result()->fetch_assoc();
    $fs->close();
}

$as = $conn->prepare('SELECT action_type, ip_address, payload, created_at FROM user_behavior_logs WHERE user_id = ? ORDER BY created_at DESC LIMIT 15');
$as->bind_param('i', $userId);
$as->execute();
$activityResult = $as->get_result();
$as->close();

$jobsResult = null;
if ($user['role'] === 'client') {
    $js = $conn->prepare('SELECT id, title, budget, status, created_at FROM jobs WHERE client_id = ? ORDER BY created_at DESC LIMIT 10');
    $js->bind_param('i', $userId);
    $js->execute();
    $jobsResult = $js->get_result();
    $js->close();
}

$contractsResult = null;
if ($user['role'] === 'client') {
    $cs2 = $conn->prepare('SELECT c.id, c.total_budget, c.status, c.created_at, j.title AS job_title, u2.name AS other_name FROM contracts c JOIN jobs j ON c.job_id = j.id JOIN freelancers f ON c.freelancer_id = f.id JOIN users u2 ON f.user_id = u2.id WHERE c.client_id = ? ORDER BY c.created_at DESC LIMIT 10');
    $cs2->bind_param('i', $userId);
    $cs2->execute();
    $contractsResult = $cs2->get_result();
    $cs2->close();
} elseif ($user['role'] === 'freelancer') {
    $cs3 = $conn->prepare('SELECT c.id, c.total_budget, c.status, c.created_at, j.title AS job_title, u2.name AS other_name FROM contracts c JOIN jobs j ON c.job_id = j.id JOIN clients cl ON c.client_id = cl.client_id JOIN users u2 ON cl.client_id = u2.id WHERE c.freelancer_id = (SELECT id FROM freelancers WHERE user_id = ?) ORDER BY c.created_at DESC LIMIT 10');
    $cs3->bind_param('i', $userId);
    $cs3->execute();
    $contractsResult = $cs3->get_result();
    $cs3->close();
}

$proposalsResult = null;
if ($user['role'] === 'freelancer' && $freelancerData) {
    $ps = $conn->prepare('SELECT p.id, p.amount, p.status, p.created_at, j.title AS job_title FROM proposals p JOIN jobs j ON p.job_id = j.id WHERE p.freelancer_id = ? ORDER BY p.created_at DESC LIMIT 10');
    $ps->bind_param('i', $freelancerData['id']);
    $ps->execute();
    $proposalsResult = $ps->get_result();
    $ps->close();
}

// $conn->close();

$roleColors = ['client' => 'bg-blue-50 text-blue-600 border-blue-200', 'freelancer' => 'bg-emerald-50 text-emerald-600 border-emerald-200', 'admin' => 'bg-purple-50 text-purple-600 border-purple-200'];
$statusColors = ['active' => 'bg-emerald-50 text-emerald-600 border-emerald-200', 'flagged' => 'bg-yellow-50 text-yellow-600 border-yellow-200', 'suspended' => 'bg-red-50 text-red-600 border-red-200'];
$jobStatusColors = ['open' => 'bg-emerald-50 text-emerald-600 border-emerald-200', 'in_progress' => 'bg-amber-50 text-amber-600 border-amber-200', 'completed' => 'bg-blue-50 text-blue-600 border-blue-200', 'closed' => 'bg-gray-50 text-gray-600 border-gray-200'];
$proposalStatusColors = ['pending' => 'bg-amber-50 text-amber-600 border-amber-200', 'accepted' => 'bg-emerald-50 text-emerald-600 border-emerald-200', 'rejected' => 'bg-red-50 text-red-600 border-red-200', 'withdrawn' => 'bg-gray-50 text-gray-600 border-gray-200'];
$contractStatusColors = ['active' => 'bg-emerald-50 text-emerald-600 border-emerald-200', 'completed' => 'bg-blue-50 text-blue-600 border-blue-200', 'pending' => 'bg-amber-50 text-amber-600 border-amber-200', 'cancelled' => 'bg-gray-50 text-gray-600 border-gray-200', 'disputed' => 'bg-red-50 text-red-600 border-red-200'];

$fraudScore = 0;

// Auto-recalculate this user's fraud score on load
if ($userId > 0) {
    $fscore = 0;

    $fq1 = $conn->prepare('SELECT COUNT(*) AS cnt FROM user_behavior_logs WHERE user_id = ? AND created_at >= DATE_SUB(NOW(), INTERVAL 1 MINUTE)');
    $fq1->bind_param('i', $userId);
    $fq1->execute();
    if ((int) $fq1->get_result()->fetch_assoc()['cnt'] > 10) $fscore += 20;
    $fq1->close();

    $fq2 = $conn->prepare('SELECT COUNT(DISTINCT ip_address) AS cnt FROM user_behavior_logs WHERE user_id = ? AND created_at >= DATE_SUB(NOW(), INTERVAL 24 HOUR)');
    $fq2->bind_param('i', $userId);
    $fq2->execute();
    if ((int) $fq2->get_result()->fetch_assoc()['cnt'] > 1) $fscore += 15;
    $fq2->close();

    $fq3 = $conn->prepare("SELECT COUNT(*) AS cnt FROM user_behavior_logs WHERE user_id = ? AND action_type = 'proposal_submit' AND created_at >= DATE_SUB(NOW(), INTERVAL 1 HOUR)");
    $fq3->bind_param('i', $userId);
    $fq3->execute();
    if ((int) $fq3->get_result()->fetch_assoc()['cnt'] > 5) $fscore += 25;
    $fq3->close();

    $fq4 = $conn->prepare("SELECT COUNT(*) AS cnt FROM user_behavior_logs WHERE user_id = ? AND action_type = 'login_failed' AND created_at >= DATE_SUB(NOW(), INTERVAL 24 HOUR)");
    $fq4->bind_param('i', $userId);
    $fq4->execute();
    $fq4Cnt = (int) $fq4->get_result()->fetch_assoc()['cnt'];
    $fq4->close();
    if ($fq4Cnt > 0) $fscore += min($fq4Cnt * 10, 30);

    $ftypes = ['spam', 'phishing', 'fake_review', 'payment_fraud', 'account_takeover', 'suspicious_download'];
    $fph = implode(',', array_fill(0, count($ftypes), '?'));
    $fq5 = $conn->prepare("SELECT COUNT(*) AS cnt FROM user_behavior_logs WHERE user_id = ? AND action_type IN ({$fph}) AND created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)");
    $fq5T = array_merge([$userId], $ftypes);
    $fq5->bind_param(str_repeat('s', count($fq5T)), ...$fq5T);
    $fq5->execute();
    $fscore += (int) $fq5->get_result()->fetch_assoc()['cnt'] * 10;
    $fq5->close();

    $fscore = min($fscore, 100);
    $fraudScore = $fscore;

    $fqUp = $conn->prepare('UPDATE users SET fraud_score = ? WHERE id = ?');
    $fqUp->bind_param('ii', $fscore, $userId);
    $fqUp->execute();
    $fqUp->close();
}
if ($fraudScore <= 30) {
    $fraudColor = 'text-emerald-500';
    $fraudBg = 'bg-emerald-50';
    $fraudLabel = 'Low Risk';
} elseif ($fraudScore <= 60) {
    $fraudColor = 'text-yellow-500';
    $fraudBg = 'bg-yellow-50';
    $fraudLabel = 'Medium Risk';
} else {
    $fraudColor = 'text-red-500';
    $fraudBg = 'bg-red-50';
    $fraudLabel = 'High Risk';
}

$activityIcons = ['login' => ['icon' => 'fa-sign-in-alt', 'color' => 'text-blue-500', 'bg' => 'bg-blue-50'], 'register' => ['icon' => 'fa-user-plus', 'color' => 'text-emerald-500', 'bg' => 'bg-emerald-50'], 'job_posted' => ['icon' => 'fa-briefcase', 'color' => 'text-violet-500', 'bg' => 'bg-violet-50'], 'proposal_sent' => ['icon' => 'fa-paper-plane', 'color' => 'text-cyan-500', 'bg' => 'bg-cyan-50'], 'payment_made' => ['icon' => 'fa-dollar-sign', 'color' => 'text-amber-500', 'bg' => 'bg-amber-50'], 'contract_create' => ['icon' => 'fa-file-contract', 'color' => 'text-indigo-500', 'bg' => 'bg-indigo-50'], 'review_posted' => ['icon' => 'fa-star', 'color' => 'text-yellow-500', 'bg' => 'bg-yellow-50']];
$defaultActivity = ['icon' => 'fa-circle', 'color' => 'text-gray-400', 'bg' => 'bg-gray-50'];

$targetUser = $user;

$_userId = $_SESSION['user_id'];
$sStmt = $conn->prepare('SELECT name, profile_image FROM users WHERE id = ?');
$sStmt->bind_param('i', $_userId);
$sStmt->execute();
$_navUserRow = $sStmt->get_result()->fetch_assoc();
$sStmt->close();
$adminName = $_navUserRow['name'] ?? 'Admin';

$conn->close();

$navItems = [
    ['key' => 'dashboard', 'label' => 'Dashboard', 'url' => 'dashboard.php', 'icon' => 'fa-th-large'],
    ['key' => 'users', 'label' => 'Users', 'url' => 'users.php', 'icon' => 'fa-users'],
    ['key' => 'jobs', 'label' => 'Jobs', 'url' => 'jobs.php', 'icon' => 'fa-briefcase'],
    ['key' => 'payments', 'label' => 'Payments', 'url' => 'payments.php', 'icon' => 'fa-credit-card'],
    ['key' => 'fraud', 'label' => 'Fraud', 'url' => 'fraud_detection.php', 'icon' => 'fa-shield-halved'],
    ['key' => 'matching', 'label' => 'AI Matching', 'url' => 'ai_matching.php', 'icon' => 'fa-brain'],
    ['key' => 'settings', 'label' => 'Settings', 'url' => 'settings.php', 'icon' => 'fa-cog'],
];
$pageTitle = sanitize_string($targetUser['name']) . ' - User Profile';
$pageSubtitle = 'User Profile & Management';
$activePage = 'users';
$user = ['name' => $adminName, 'profile_image' => $_navUserRow['profile_image'] ?? null];
$unreadCount = 0;
$profileLink = 'profile.php';
require_once __DIR__ . '/../components/layout_start.php';
?>
    <style>
    .btn-grad{background:linear-gradient(135deg,#2563eb,#0ea5e9);transition:opacity .25s,transform .2s}
    .btn-grad:hover{opacity:.88;transform:translateY(-2px)}
    </style>


            <?php display_flash('success') ?>
            <?php display_flash('error') ?>

            <!-- Profile Card -->
            <div class="bg-white rounded-2xl border border-gray-100 shadow-sm overflow-hidden fade-in">
                <div class="h-32 bg-gradient-to-r from-blue-600 via-cyan-500 to-indigo-600"></div>
                <div class="px-6 pb-6">
                    <div class="flex flex-col sm:flex-row items-start sm:items-end gap-4 -mt-12">
                        <img src="<?= sanitize_string(get_profile_image($targetUser['profile_image'])) ?>" class="w-24 h-24 rounded-2xl object-cover border-4 border-white shadow-lg">
                        <div class="flex-1">
                            <div class="flex items-center gap-2 flex-wrap">
                                <h2 class="text-xl font-bold text-gray-900"><?= sanitize_string($targetUser['name']) ?></h2>
                                <span class="inline-block px-2.5 py-1 rounded-lg text-[11px] font-semibold border <?= $roleColors[$targetUser['role']] ?? '' ?>"><?= sanitize_string(ucfirst($targetUser['role'])) ?></span>
                                <span class="inline-block px-2.5 py-1 rounded-lg text-[11px] font-semibold border <?= $statusColors[$targetUser['status']] ?? '' ?>"><?= sanitize_string(ucfirst($targetUser['status'])) ?></span>
                            </div>
                            <p class="text-sm text-gray-500 mt-1"><?= sanitize_string($targetUser['email']) ?></p>
                        </div>
                    </div>
                    <div class="grid grid-cols-2 sm:grid-cols-4 gap-4 mt-6">
                        <div class="bg-gray-50 rounded-xl p-4">
                            <p class="text-[10px] font-semibold text-gray-400 uppercase tracking-wider">Phone</p>
                             <p class="text-sm font-semibold text-gray-900 mt-1"><?= sanitize_string($targetUser['phone'] ?: 'N/A') ?></p>
                        </div>
                        <div class="bg-gray-50 rounded-xl p-4">
                            <p class="text-[10px] font-semibold text-gray-400 uppercase tracking-wider">Wallet Balance</p>
                            <p class="text-sm font-semibold text-gray-900 mt-1"><?= format_currency((float) $targetUser['wallet_balance']) ?></p>
                        </div>
                        <div class="bg-gray-50 rounded-xl p-4">
                            <p class="text-[10px] font-semibold text-gray-400 uppercase tracking-wider">Joined</p>
                            <p class="text-sm font-semibold text-gray-900 mt-1"><?= date('M j, Y', strtotime($targetUser['created_at'])) ?></p>
                        </div>
                        <div class="<?= $fraudBg ?> rounded-xl p-4">
                            <p class="text-[10px] font-semibold text-gray-400 uppercase tracking-wider">Fraud Score</p>
                            <p class="text-sm font-bold <?= $fraudColor ?> mt-1"><?= $fraudScore ?>/100 - <?= $fraudLabel ?></p>
                        </div>
                    </div>
                </div>
            </div>

            <?php if ($clientData): ?>
            <div class="bg-white rounded-2xl border border-gray-100 shadow-sm p-6 fade-in" style="animation-delay:.05s">
                <h3 class="text-base font-bold text-gray-900 mb-4"><i class="fas fa-user-tie text-blue-500 mr-2"></i>Client Details</h3>
                <div class="grid grid-cols-2 sm:grid-cols-3 gap-4">
                    <div class="bg-blue-50 rounded-xl p-4">
                        <p class="text-[10px] font-semibold text-gray-400 uppercase tracking-wider">Company</p>
                        <p class="text-sm font-semibold text-gray-900 mt-1"><?= sanitize_string($clientData['company_name'] ?: 'N/A') ?></p>
                    </div>
                    <div class="bg-blue-50 rounded-xl p-4">
                        <p class="text-[10px] font-semibold text-gray-400 uppercase tracking-wider">Total Spent</p>
                        <p class="text-sm font-bold text-blue-600 mt-1"><?= format_currency((float) ($clientData['total_spent'] ?? 0)) ?></p>
                    </div>
                </div>
            </div>
            <?php endif; ?>

            <?php if ($freelancerData): ?>
            <div class="bg-white rounded-2xl border border-gray-100 shadow-sm p-6 fade-in" style="animation-delay:.05s">
                <h3 class="text-base font-bold text-gray-900 mb-4"><i class="fas fa-laptop-code text-emerald-500 mr-2"></i>Freelancer Details</h3>
                <div class="grid grid-cols-2 sm:grid-cols-4 gap-4">
                    <div class="bg-emerald-50 rounded-xl p-4">
                        <p class="text-[10px] font-semibold text-gray-400 uppercase tracking-wider">Title</p>
                        <p class="text-sm font-semibold text-gray-900 mt-1"><?= sanitize_string($freelancerData['title'] ?: 'N/A') ?></p>
                    </div>
                    <div class="bg-emerald-50 rounded-xl p-4">
                        <p class="text-[10px] font-semibold text-gray-400 uppercase tracking-wider">Hourly Rate</p>
                        <p class="text-sm font-bold text-emerald-600 mt-1"><?= format_currency((float) ($freelancerData['hourly_rate'] ?? 0)) ?></p>
                    </div>
                    <div class="bg-emerald-50 rounded-xl p-4">
                        <p class="text-[10px] font-semibold text-gray-400 uppercase tracking-wider">Availability</p>
                        <p class="text-sm font-semibold text-gray-900 mt-1"><?= sanitize_string(ucfirst($freelancerData['availability'] ?? 'N/A')) ?></p>
                    </div>
                </div>
            </div>
            <?php endif; ?>

            <?php if ($jobsResult && $jobsResult->num_rows > 0): ?>
            <div class="bg-white rounded-2xl border border-gray-100 shadow-sm overflow-hidden fade-in" style="animation-delay:.1s">
                <div class="p-6 pb-0"><h3 class="text-base font-bold text-gray-900"><i class="fas fa-briefcase text-blue-500 mr-2"></i>Jobs Posted (<?= $jobsResult->num_rows ?>)</h3></div>
                <div class="p-6">
                    <div class="overflow-x-auto">
                        <table class="w-full text-sm">
                            <thead><tr class="border-b border-gray-100"><th class="text-left pb-3 text-xs font-semibold text-gray-400 uppercase tracking-wider">Job</th><th class="text-left pb-3 text-xs font-semibold text-gray-400 uppercase tracking-wider">Status</th><th class="text-right pb-3 text-xs font-semibold text-gray-400 uppercase tracking-wider">Budget</th><th class="text-right pb-3 text-xs font-semibold text-gray-400 uppercase tracking-wider">Posted</th></tr></thead>
                            <tbody>
                            <?php while ($job = $jobsResult->fetch_assoc()): ?>
                            <tr class="border-b border-gray-50 last:border-0 hover:bg-gray-50 transition-colors">
                                <td class="py-3"><span class="font-medium text-gray-900"><?= sanitize_string($job['title']) ?></span></td>
                                <td class="py-3"><span class="inline-block px-2.5 py-1 rounded-lg text-[11px] font-semibold border <?= $jobStatusColors[$job['status']] ?? '' ?>"><?= sanitize_string(ucfirst(str_replace('_', ' ', $job['status']))) ?></span></td>
                                <td class="py-3 text-right font-semibold text-gray-700"><?= format_currency((float) $job['budget']) ?></td>
                                <td class="py-3 text-right text-gray-400 text-xs"><?= time_ago($job['created_at']) ?></td>
                            </tr>
                            <?php endwhile; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
            <?php endif; ?>

            <?php if ($proposalsResult && $proposalsResult->num_rows > 0): ?>
            <div class="bg-white rounded-2xl border border-gray-100 shadow-sm overflow-hidden fade-in" style="animation-delay:.1s">
                <div class="p-6 pb-0"><h3 class="text-base font-bold text-gray-900"><i class="fas fa-paper-plane text-cyan-500 mr-2"></i>Proposals (<?= $proposalsResult->num_rows ?>)</h3></div>
                <div class="p-6">
                    <div class="overflow-x-auto">
                        <table class="w-full text-sm">
                            <thead><tr class="border-b border-gray-100"><th class="text-left pb-3 text-xs font-semibold text-gray-400 uppercase tracking-wider">Job</th><th class="text-left pb-3 text-xs font-semibold text-gray-400 uppercase tracking-wider">Status</th><th class="text-right pb-3 text-xs font-semibold text-gray-400 uppercase tracking-wider">Amount</th><th class="text-right pb-3 text-xs font-semibold text-gray-400 uppercase tracking-wider">Submitted</th></tr></thead>
                            <tbody>
                            <?php while ($prop = $proposalsResult->fetch_assoc()): ?>
                            <tr class="border-b border-gray-50 last:border-0 hover:bg-gray-50 transition-colors">
                                <td class="py-3"><span class="font-medium text-gray-900"><?= sanitize_string($prop['job_title']) ?></span></td>
                                <td class="py-3"><span class="inline-block px-2.5 py-1 rounded-lg text-[11px] font-semibold border <?= $proposalStatusColors[$prop['status']] ?? '' ?>"><?= sanitize_string(ucfirst($prop['status'])) ?></span></td>
                                <td class="py-3 text-right font-semibold text-gray-700"><?= format_currency((float) $prop['amount']) ?></td>
                                <td class="py-3 text-right text-gray-400 text-xs"><?= time_ago($prop['created_at']) ?></td>
                            </tr>
                            <?php endwhile; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
            <?php endif; ?>

            <?php if ($contractsResult && $contractsResult->num_rows > 0): ?>
            <div class="bg-white rounded-2xl border border-gray-100 shadow-sm overflow-hidden fade-in" style="animation-delay:.15s">
                <div class="p-6 pb-0"><h3 class="text-base font-bold text-gray-900"><i class="fas fa-file-contract text-indigo-500 mr-2"></i>Contracts (<?= $contractsResult->num_rows ?>)</h3></div>
                <div class="p-6">
                    <div class="overflow-x-auto">
                        <table class="w-full text-sm">
                            <thead><tr class="border-b border-gray-100"><th class="text-left pb-3 text-xs font-semibold text-gray-400 uppercase tracking-wider">Job</th><th class="text-left pb-3 text-xs font-semibold text-gray-400 uppercase tracking-wider">With</th><th class="text-left pb-3 text-xs font-semibold text-gray-400 uppercase tracking-wider">Status</th><th class="text-right pb-3 text-xs font-semibold text-gray-400 uppercase tracking-wider">Budget</th></tr></thead>
                            <tbody>
                            <?php while ($c = $contractsResult->fetch_assoc()): ?>
                            <tr class="border-b border-gray-50 last:border-0 hover:bg-gray-50 transition-colors">
                                <td class="py-3"><span class="font-medium text-gray-900"><?= sanitize_string($c['job_title']) ?></span></td>
                                <td class="py-3 text-gray-600 text-xs"><?= sanitize_string($c['other_name']) ?></td>
                                <td class="py-3"><span class="inline-block px-2.5 py-1 rounded-lg text-[11px] font-semibold border <?= $contractStatusColors[$c['status']] ?? '' ?>"><?= sanitize_string(ucfirst($c['status'])) ?></span></td>
                                <td class="py-3 text-right font-semibold text-gray-700"><?= format_currency((float) $c['total_budget']) ?></td>
                            </tr>
                            <?php endwhile; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
            <?php endif; ?>

            <!-- Recent Activity -->
            <div class="bg-white rounded-2xl border border-gray-100 shadow-sm fade-in" style="animation-delay:.2s">
                <div class="p-6 pb-0"><h3 class="text-base font-bold text-gray-900"><i class="fas fa-history text-gray-400 mr-2"></i>Recent Activity</h3></div>
                <div class="p-6">
                <?php if ($activityResult->num_rows > 0): ?>
                    <div class="space-y-3">
                    <?php
                    while ($act = $activityResult->fetch_assoc()):
                        $aInfo = $activityIcons[$act['action_type']] ?? $defaultActivity;
                        ?>
                        <div class="flex items-center gap-3 p-3 rounded-xl hover:bg-gray-50 transition-colors">
                            <div class="w-9 h-9 rounded-xl <?= $aInfo['bg'] ?> flex items-center justify-center flex-shrink-0">
                                <i class="fas <?= $aInfo['icon'] ?> <?= $aInfo['color'] ?> text-xs"></i>
                            </div>
                            <div class="flex-1 min-w-0">
                                <p class="text-sm text-gray-700"><?= sanitize_string(str_replace('_', ' ', $act['action_type'])) ?></p>
                                <p class="text-[11px] text-gray-400 mt-0.5">IP: <?= sanitize_string($act['ip_address']) ?> &middot; <?= time_ago($act['created_at']) ?></p>
                            </div>
                        </div>
                    <?php endwhile; ?>
                    </div>
                <?php else: ?>
                    <div class="text-center py-8"><p class="text-gray-500 text-sm">No activity recorded</p></div>
                <?php endif; ?>
                </div>
            </div>
<?php require_once __DIR__ . '/../components/layout_end.php'; ?>
