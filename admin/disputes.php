<?php
/**
 * Admin Dispute Tickets Dashboard
 * List, filter, view, and resolve dispute tickets.
 */
require_once __DIR__ . '/../auth/auth.php';
require_role('admin');
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/helpers.php';

$currentPage = 'disputes';

// ── Filters ──────────────────────────────────────────────────────────
$statusF = $_GET['status'] ?? '';
$page    = max(1, sanitize_int($_GET['page'] ?? 1));
$perPage = 15;

$allowedStatuses = ['open', 'investigating', 'resolved', 'dismissed', 'escalated'];
if ($statusF && !in_array($statusF, $allowedStatuses)) {
    $statusF = '';
}

// ── Statistics ────────────────────────────────────────────────────────
$stats = [];

$r = $conn->query("SELECT COUNT(*) AS cnt FROM dispute_tickets");
$stats['total'] = (int) $r->fetch_assoc()['cnt'];

$r = $conn->query("SELECT COUNT(*) AS cnt FROM dispute_tickets WHERE status = 'open'");
$stats['open'] = (int) $r->fetch_assoc()['cnt'];

$r = $conn->query("SELECT COUNT(*) AS cnt FROM dispute_tickets WHERE status = 'investigating'");
$stats['investigating'] = (int) $r->fetch_assoc()['cnt'];

$r = $conn->query("SELECT COUNT(*) AS cnt FROM dispute_tickets WHERE status = 'escalated'");
$stats['escalated'] = (int) $r->fetch_assoc()['cnt'];

$r = $conn->query("SELECT COUNT(*) AS cnt FROM dispute_tickets WHERE status = 'resolved'");
$stats['resolved'] = (int) $r->fetch_assoc()['cnt'];

$r = $conn->query("SELECT COUNT(*) AS cnt FROM dispute_tickets WHERE status = 'dismissed'");
$stats['dismissed'] = (int) $r->fetch_assoc()['cnt'];

// ── Build query ───────────────────────────────────────────────────────
$where   = [];
$params  = [];
$bindTypes = '';

if ($statusF !== '') {
    $where[]  = 'd.status = ?';
    $params[] = $statusF;
    $bindTypes .= 's';
}

$whereSql = $where ? 'WHERE ' . implode(' AND ', $where) : '';

$countSql = "SELECT COUNT(*) AS cnt FROM dispute_tickets d $whereSql";
$countStmt = $conn->prepare($countSql);
if ($bindTypes) $countStmt->bind_param($bindTypes, ...$params);
$countStmt->execute();
$totalItems = (int) $countStmt->get_result()->fetch_assoc()['cnt'];
$countStmt->close();

$totalPages = max(1, (int) ceil($totalItems / $perPage));
if ($page > $totalPages) $page = $totalPages;
$offset = ($page - 1) * $perPage;

$querySql = "SELECT d.id, d.reason, d.description, d.status, d.resolution, d.created_at, d.updated_at,
                    d.contract_id, d.milestone_id,
                    c.total_budget, c.status AS contract_status,
                    j.title AS job_title,
                    ru.name AS raised_by_name, ru.email AS raised_by_email, ru.profile_image AS raised_by_image,
                    au.name AS against_name, au.email AS against_email, au.profile_image AS against_image,
                    m.title AS milestone_title, m.amount AS milestone_amount,
                    rv.name AS resolved_by_name
             FROM dispute_tickets d
             JOIN contracts c ON d.contract_id = c.id
             JOIN jobs j ON c.job_id = j.id
             JOIN users ru ON d.raised_by = ru.id
             JOIN users au ON d.against = au.id
             LEFT JOIN milestones m ON d.milestone_id = m.id
             LEFT JOIN users rv ON d.resolved_by = rv.id
             $whereSql
             ORDER BY FIELD(d.status, 'open', 'investigating', 'escalated', 'resolved', 'dismissed'), d.created_at DESC
             LIMIT ? OFFSET ?";
$bindTypes .= 'ii';
$params[]   = $perPage;
$params[]   = $offset;

$queryStmt = $conn->prepare($querySql);
$queryStmt->bind_param($bindTypes, ...$params);
$queryStmt->execute();
$result = $queryStmt->get_result();
$queryStmt->close();

$disputes = [];
while ($row = $result->fetch_assoc()) {
    $disputes[] = $row;
}

// ── Badge maps ────────────────────────────────────────────────────────
$statusColors = [
    'open'          => 'bg-red-50 text-red-600 border-red-200',
    'investigating' => 'bg-amber-50 text-amber-600 border-amber-200',
    'escalated'     => 'bg-purple-50 text-purple-600 border-purple-200',
    'resolved'      => 'bg-emerald-50 text-emerald-600 border-emerald-200',
    'dismissed'     => 'bg-gray-50 text-gray-500 border-gray-200',
];
$statusIcons = [
    'open'          => 'fa-exclamation-circle',
    'investigating' => 'fa-search',
    'escalated'     => 'fa-arrow-up',
    'resolved'      => 'fa-check-circle',
    'dismissed'     => 'fa-times-circle',
];
$reasonLabels = [
    'non_delivery'  => 'Non-Delivery',
    'quality_issue' => 'Quality Issue',
    'scope_dispute' => 'Scope Dispute',
    'payment_issue' => 'Payment Issue',
    'other'         => 'Other',
];
$reasonColors = [
    'non_delivery'  => 'bg-red-50 text-red-500',
    'quality_issue' => 'bg-amber-50 text-amber-500',
    'scope_dispute' => 'bg-blue-50 text-blue-500',
    'payment_issue' => 'bg-purple-50 text-purple-500',
    'other'         => 'bg-gray-50 text-gray-500',
];

// ── Pagination base URL ─────────────────────────────────────────────
$baseUrl = 'disputes.php?';
if ($statusF !== '') $baseUrl .= 'status=' . urlencode($statusF) . '&';
$baseUrl = rtrim($baseUrl, '?&');
if (strpos($baseUrl, '&') === false) {
    $baseUrl = rtrim($baseUrl, '?');
}

// ── Layout setup ─────────────────────────────────────────────────────
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
    ['key' => 'milestones', 'label' => 'Milestones', 'url' => 'milestones.php', 'icon' => 'fa-tasks'],
    ['key' => 'disputes', 'label' => 'Disputes', 'url' => 'disputes.php', 'icon' => 'fa-gavel'],
    ['key' => 'fraud', 'label' => 'Fraud', 'url' => 'fraud_detection.php', 'icon' => 'fa-shield-halved'],
    ['key' => 'matching', 'label' => 'AI Matching', 'url' => 'ai_matching.php', 'icon' => 'fa-brain'],
    ['key' => 'settings', 'label' => 'Settings', 'url' => 'settings.php', 'icon' => 'fa-cog'],
];
$pageTitle = 'Dispute Tickets';
$pageSubtitle = $totalItems . ' dispute' . ($totalItems !== 1 ? 's' : '') . ' total';
$activePage = 'disputes';
$user = ['name' => $adminName, 'profile_image' => $_navUserRow['profile_image'] ?? null];
$unreadCount = 0;
$profileLink = 'profile.php';
require_once __DIR__ . '/../components/layout_start.php';
?>
    <style>
    .btn-grad{background:linear-gradient(135deg,#2563eb,#0ea5e9);transition:opacity .25s,transform .2s}
    .btn-grad:hover{opacity:.88;transform:translateY(-2px)}
    .stat-card{transition:transform .2s,box-shadow .2s}
    .stat-card:hover{transform:translateY(-3px);box-shadow:0 8px 30px rgba(0,0,0,.06)}
    .detail-panel{max-height:0;overflow:hidden;transition:max-height .3s ease}
    .detail-panel.open{max-height:2000px}
    </style>

    <!-- Stats Grid -->
    <div class="grid grid-cols-2 md:grid-cols-5 gap-4 mb-8">
        <div class="stat-card bg-white rounded-2xl p-5 border border-gray-100 shadow-sm">
            <div class="flex items-center gap-3 mb-3">
                <div class="w-10 h-10 rounded-xl bg-gray-100 flex items-center justify-center"><i class="fas fa-gavel text-gray-600"></i></div>
                <span class="text-xs font-semibold text-gray-400 uppercase tracking-wide">Total</span>
            </div>
            <p class="text-2xl font-extrabold text-gray-900"><?= $stats['total'] ?></p>
        </div>
        <div class="stat-card bg-white rounded-2xl p-5 border border-gray-100 shadow-sm">
            <div class="flex items-center gap-3 mb-3">
                <div class="w-10 h-10 rounded-xl bg-red-100 flex items-center justify-center"><i class="fas fa-exclamation-circle text-red-600"></i></div>
                <span class="text-xs font-semibold text-gray-400 uppercase tracking-wide">Open</span>
            </div>
            <p class="text-2xl font-extrabold text-red-600"><?= $stats['open'] ?></p>
        </div>
        <div class="stat-card bg-white rounded-2xl p-5 border border-gray-100 shadow-sm">
            <div class="flex items-center gap-3 mb-3">
                <div class="w-10 h-10 rounded-xl bg-amber-100 flex items-center justify-center"><i class="fas fa-search text-amber-600"></i></div>
                <span class="text-xs font-semibold text-gray-400 uppercase tracking-wide">Investigating</span>
            </div>
            <p class="text-2xl font-extrabold text-amber-600"><?= $stats['investigating'] ?></p>
        </div>
        <div class="stat-card bg-white rounded-2xl p-5 border border-gray-100 shadow-sm">
            <div class="flex items-center gap-3 mb-3">
                <div class="w-10 h-10 rounded-xl bg-purple-100 flex items-center justify-center"><i class="fas fa-arrow-up text-purple-600"></i></div>
                <span class="text-xs font-semibold text-gray-400 uppercase tracking-wide">Escalated</span>
            </div>
            <p class="text-2xl font-extrabold text-purple-600"><?= $stats['escalated'] ?></p>
        </div>
        <div class="stat-card bg-white rounded-2xl p-5 border border-gray-100 shadow-sm">
            <div class="flex items-center gap-3 mb-3">
                <div class="w-10 h-10 rounded-xl bg-emerald-100 flex items-center justify-center"><i class="fas fa-check-circle text-emerald-600"></i></div>
                <span class="text-xs font-semibold text-gray-400 uppercase tracking-wide">Resolved</span>
            </div>
            <p class="text-2xl font-extrabold text-emerald-600"><?= $stats['resolved'] ?></p>
        </div>
    </div>

    <!-- Filters -->
    <div class="bg-white rounded-2xl border border-gray-100 shadow-sm p-5 mb-6">
        <form method="GET" class="flex flex-wrap gap-3 items-end">
            <div class="flex-1 min-w-[160px]">
                <label class="block text-[11px] font-semibold text-gray-500 uppercase tracking-wide mb-1">Status</label>
                <select name="status" class="w-full px-3 py-2 rounded-xl border border-gray-200 text-sm bg-gray-50 focus:bg-white">
                    <option value="">All Statuses</option>
                    <?php foreach ($allowedStatuses as $s): ?>
                        <option value="<?= $s ?>" <?= $statusF === $s ? 'selected' : '' ?>><?= ucfirst($s) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="flex gap-2">
                <button type="submit" class="btn-grad px-5 py-2 rounded-xl text-white text-sm font-semibold shadow-lg shadow-blue-500/25"><i class="fas fa-search mr-1.5 text-xs"></i> Filter</button>
                <a href="disputes.php" class="px-4 py-2 rounded-xl border border-gray-200 text-sm font-semibold text-gray-600 hover:bg-gray-50">Clear</a>
            </div>
        </form>
    </div>

    <!-- Disputes List -->
    <div class="space-y-4 mb-6">
        <?php if (empty($disputes)): ?>
            <div class="bg-white rounded-2xl border border-gray-100 shadow-sm text-center py-16">
                <div class="w-16 h-16 rounded-2xl bg-gray-100 flex items-center justify-center mx-auto mb-4"><i class="fas fa-gavel text-2xl text-gray-300"></i></div>
                <p class="text-gray-500 font-medium">No dispute tickets found</p>
            </div>
        <?php else: ?>
            <?php foreach ($disputes as $d): ?>
                <div class="bg-white rounded-2xl border border-gray-100 shadow-sm overflow-hidden" id="dispute-<?= (int) $d['id'] ?>">
                    <!-- Header -->
                    <div class="p-5 cursor-pointer hover:bg-gray-50/50 transition-colors" onclick="toggleDisputeDetail(<?= (int) $d['id'] ?>)">
                        <div class="flex flex-col sm:flex-row sm:items-center gap-4">
                            <div class="flex-1 min-w-0">
                                <div class="flex flex-wrap items-center gap-2 mb-1.5">
                                    <span class="text-sm font-bold text-gray-900">#<?= $d['id'] ?></span>
                                    <span class="inline-flex items-center gap-1 px-2.5 py-0.5 rounded-lg text-[10px] font-semibold border <?= $statusColors[$d['status']] ?? '' ?>">
                                        <i class="fas <?= $statusIcons[$d['status']] ?? 'fa-circle' ?> text-[8px]"></i>
                                        <?= ucfirst($d['status']) ?>
                                    </span>
                                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-lg text-[10px] font-semibold <?= $reasonColors[$d['reason']] ?? '' ?>">
                                        <?= $reasonLabels[$d['reason']] ?? $d['reason'] ?>
                                    </span>
                                </div>
                                <p class="text-sm text-gray-700 font-medium mb-1"><?= sanitize_string($d['job_title']) ?></p>
                                <div class="flex items-center gap-4 text-xs text-gray-400">
                                    <span><i class="fas fa-user mr-1"></i><?= sanitize_string($d['raised_by_name']) ?> vs <?= sanitize_string($d['against_name']) ?></span>
                                    <span><i class="fas fa-dollar-sign mr-1"></i><?= format_currency((float) $d['total_budget']) ?></span>
                                    <span><i class="fas fa-clock mr-1"></i><?= time_ago($d['created_at']) ?></span>
                                </div>
                            </div>
                            <div class="flex items-center gap-2">
                                <?php if ($d['status'] === 'open'): ?>
                                    <button onclick="event.stopPropagation();updateDisputeStatus(<?= (int) $d['id'] ?>, 'investigating')" class="px-3 py-1.5 bg-amber-500 hover:bg-amber-600 text-white text-xs font-semibold rounded-lg transition-all">Investigate</button>
                                <?php endif; ?>
                                <?php if (in_array($d['status'], ['open', 'investigating', 'escalated'])): ?>
                                    <button onclick="event.stopPropagation();openResolveModal(<?= (int) $d['id'] ?>)" class="px-3 py-1.5 bg-emerald-500 hover:bg-emerald-600 text-white text-xs font-semibold rounded-lg transition-all">Resolve</button>
                                <?php endif; ?>
                                <i class="fas fa-chevron-down text-gray-400 text-xs transition-transform dispute-chevron-<?= (int) $d['id'] ?>"></i>
                            </div>
                        </div>
                    </div>
                    <!-- Detail Panel -->
                    <div class="detail-panel" id="detail-<?= (int) $d['id'] ?>">
                        <div class="px-5 pb-5 border-t border-gray-100 pt-4">
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                                <!-- Left: Description & Milestone -->
                                <div class="space-y-4">
                                    <div>
                                        <h4 class="text-xs font-semibold text-gray-500 uppercase tracking-wide mb-2">Dispute Description</h4>
                                        <p class="text-sm text-gray-700 bg-gray-50 rounded-xl p-4"><?= nl2br(sanitize_string($d['description'])) ?></p>
                                    </div>
                                    <?php if (!empty($d['milestone_id'])): ?>
                                        <div>
                                            <h4 class="text-xs font-semibold text-gray-500 uppercase tracking-wide mb-2">Related Milestone</h4>
                                            <div class="bg-blue-50 rounded-xl p-4">
                                                <p class="text-sm font-bold text-gray-900"><?= sanitize_string($d['milestone_title'] ?? 'Milestone #' . $d['milestone_id']) ?></p>
                                                <p class="text-xs text-gray-500 mt-1"><?= format_currency((float) ($d['milestone_amount'] ?? 0)) ?></p>
                                            </div>
                                        </div>
                                    <?php endif; ?>
                                    <?php if (!empty($d['resolution'])): ?>
                                        <div>
                                            <h4 class="text-xs font-semibold text-gray-500 uppercase tracking-wide mb-2">Resolution</h4>
                                            <div class="bg-emerald-50 rounded-xl p-4">
                                                <p class="text-sm text-gray-700"><?= nl2br(sanitize_string($d['resolution'])) ?></p>
                                                <?php if (!empty($d['resolved_by_name'])): ?>
                                                    <p class="text-xs text-gray-400 mt-2">Resolved by <?= sanitize_string($d['resolved_by_name']) ?></p>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                    <?php endif; ?>
                                </div>
                                <!-- Right: Parties & Contract -->
                                <div class="space-y-4">
                                    <div>
                                        <h4 class="text-xs font-semibold text-gray-500 uppercase tracking-wide mb-2">Raised By</h4>
                                        <div class="flex items-center gap-3 bg-gray-50 rounded-xl p-3">
                                            <img src="<?= get_profile_image($d['raised_by_image']) ?>" class="w-9 h-9 rounded-full object-cover border border-gray-200" alt="">
                                            <div>
                                                <p class="text-sm font-semibold text-gray-900"><?= sanitize_string($d['raised_by_name']) ?></p>
                                                <p class="text-[11px] text-gray-400"><?= sanitize_string($d['raised_by_email']) ?></p>
                                            </div>
                                        </div>
                                    </div>
                                    <div>
                                        <h4 class="text-xs font-semibold text-gray-500 uppercase tracking-wide mb-2">Against</h4>
                                        <div class="flex items-center gap-3 bg-gray-50 rounded-xl p-3">
                                            <img src="<?= get_profile_image($d['against_image']) ?>" class="w-9 h-9 rounded-full object-cover border border-gray-200" alt="">
                                            <div>
                                                <p class="text-sm font-semibold text-gray-900"><?= sanitize_string($d['against_name']) ?></p>
                                                <p class="text-[11px] text-gray-400"><?= sanitize_string($d['against_email']) ?></p>
                                            </div>
                                        </div>
                                    </div>
                                    <div>
                                        <h4 class="text-xs font-semibold text-gray-500 uppercase tracking-wide mb-2">Contract Info</h4>
                                        <div class="bg-gray-50 rounded-xl p-3 space-y-1">
                                            <div class="flex justify-between text-xs"><span class="text-gray-500">Contract</span><span class="font-semibold text-gray-700">#<?= $d['contract_id'] ?></span></div>
                                            <div class="flex justify-between text-xs"><span class="text-gray-500">Budget</span><span class="font-semibold text-gray-700"><?= format_currency((float) $d['total_budget']) ?></span></div>
                                            <div class="flex justify-between text-xs"><span class="text-gray-500">Status</span><span class="font-semibold text-gray-700"><?= ucfirst($d['contract_status']) ?></span></div>
                                            <div class="flex justify-between text-xs"><span class="text-gray-500">Created</span><span class="font-semibold text-gray-700"><?= date('M d, Y', strtotime($d['created_at'])) ?></span></div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>

    <!-- Pagination -->
    <?php if ($totalPages > 1): ?>
        <div class="flex items-center justify-between">
            <p class="text-sm text-gray-500">
                Showing <span class="font-semibold"><?= $offset + 1 ?>–<?= min($offset + $perPage, $totalItems) ?></span> of <span class="font-semibold"><?= $totalItems ?></span>
            </p>
            <div class="flex gap-1.5">
                <?php if ($page > 1): ?>
                    <a href="<?= $baseUrl ?>&page=<?= $page - 1 ?>" class="px-3 py-1.5 rounded-lg border border-gray-200 text-sm font-medium text-gray-600 hover:bg-gray-50"><i class="fas fa-chevron-left text-xs"></i></a>
                <?php endif; ?>
                <?php
                $startPage = max(1, $page - 2);
                $endPage   = min($totalPages, $page + 2);
                for ($i = $startPage; $i <= $endPage; $i++):
                ?>
                    <a href="<?= $baseUrl ?>&page=<?= $i ?>" class="px-3 py-1.5 rounded-lg text-sm font-medium <?= $i === $page ? 'btn-grad text-white shadow-md shadow-blue-500/20' : 'border border-gray-200 text-gray-600 hover:bg-gray-50' ?>"><?= $i ?></a>
                <?php endfor; ?>
                <?php if ($page < $totalPages): ?>
                    <a href="<?= $baseUrl ?>&page=<?= $page + 1 ?>" class="px-3 py-1.5 rounded-lg border border-gray-200 text-sm font-medium text-gray-600 hover:bg-gray-50"><i class="fas fa-chevron-right text-xs"></i></a>
                <?php endif; ?>
            </div>
        </div>
    <?php endif; ?>

    <!-- Resolve Modal -->
    <div id="resolveModal" class="fixed inset-0 z-50 hidden">
        <div class="modal-overlay absolute inset-0 bg-black/40" onclick="closeResolveModal()"></div>
        <div class="absolute inset-0 flex items-center justify-center p-4">
            <div class="bg-white rounded-2xl shadow-2xl w-full max-w-md relative z-10" style="transform:scale(.95) translateY(10px);transition:transform .25s ease">
                <div class="p-6 border-b border-gray-100">
                    <div class="flex items-center justify-between">
                        <h3 class="text-lg font-bold text-gray-900">Resolve Dispute</h3>
                        <button onclick="closeResolveModal()" class="w-8 h-8 rounded-lg bg-gray-100 flex items-center justify-center text-gray-400 hover:text-gray-600"><i class="fas fa-times text-sm"></i></button>
                    </div>
                </div>
                <form id="resolveForm" onsubmit="return submitResolve(event)">
                    <div class="p-6 space-y-4">
                        <input type="hidden" name="action" value="resolve">
                        <input type="hidden" name="dispute_id" id="resolve_dispute_id">
                        <div>
                            <label class="block text-xs font-semibold text-gray-700 mb-1.5">Resolution Note</label>
                            <textarea name="resolution" id="resolve_resolution" required rows="4" placeholder="Explain how this dispute was resolved..." class="w-full px-4 py-2.5 rounded-xl border border-gray-200 text-sm bg-gray-50 focus:bg-white resize-none" minlength="10"></textarea>
                        </div>
                    </div>
                    <div class="px-6 pb-6 flex gap-3">
                        <button type="button" onclick="closeResolveModal()" class="flex-1 px-4 py-2.5 rounded-xl border border-gray-200 text-sm font-semibold text-gray-600 hover:bg-gray-50">Cancel</button>
                        <button type="submit" class="flex-1 px-4 py-2.5 rounded-xl bg-emerald-500 hover:bg-emerald-600 text-white text-sm font-semibold shadow-lg shadow-emerald-500/25">Confirm Resolution</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script>
    var CSRF_TOKEN = '<?= generate_csrf_token() ?>';

    function toggleDisputeDetail(id) {
        var panel = document.getElementById('detail-' + id);
        var chevron = document.querySelector('.dispute-chevron-' + id);
        if (panel.classList.contains('open')) {
            panel.classList.remove('open');
            chevron.style.transform = '';
        } else {
            panel.classList.add('open');
            chevron.style.transform = 'rotate(180deg)';
        }
    }

    async function updateDisputeStatus(id, status) {
        if (!confirm('Mark this dispute as "' + status.charAt(0).toUpperCase() + status.slice(1) + '"?')) return;
        try {
            var fd = new FormData();
            fd.append('action', 'update_status');
            fd.append('dispute_id', id);
            fd.append('status', status);
            fd.append('csrf_token', CSRF_TOKEN);
            var r = await fetch('/finalproject/api/dispute_api.php', { method: 'POST', body: fd });
            var j = await r.json();
            if (j.success) { location.reload(); } else { alert('Error: ' + j.message); }
        } catch (e) { alert('Network error.'); }
    }

    function openResolveModal(id) {
        document.getElementById('resolve_dispute_id').value = id;
        document.getElementById('resolve_resolution').value = '';
        document.getElementById('resolveModal').classList.remove('hidden');
    }
    function closeResolveModal() {
        document.getElementById('resolveModal').classList.add('hidden');
    }
    async function submitResolve(e) {
        e.preventDefault();
        try {
            var fd = new FormData(document.getElementById('resolveForm'));
            fd.append('csrf_token', CSRF_TOKEN);
            var r = await fetch('/finalproject/api/dispute_api.php', { method: 'POST', body: fd });
            var j = await r.json();
            if (j.success) { location.reload(); } else { alert('Error: ' + j.message); }
        } catch (err) { alert('Network error.'); }
        return false;
    }
    </script>

<?php require_once __DIR__ . '/../components/layout_end.php'; ?>
