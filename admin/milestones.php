<?php
/**
 * Admin Milestone Monitoring Dashboard
 * Overview of all milestones across the platform with filters and dispute management.
 */
require_once __DIR__ . '/../auth/auth.php';
require_role('admin');
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/helpers.php';

$currentPage = 'milestones';

// ── Filters ──────────────────────────────────────────────────────────
$statusF    = $_GET['status'] ?? '';
$clientQ    = trim($_GET['client'] ?? '');
$freelancerQ = trim($_GET['freelancer'] ?? '');
$page       = max(1, sanitize_int($_GET['page'] ?? 1));
$perPage    = 15;

$allowedStatuses = ['pending', 'funded_in_escrow', 'submitted', 'released', 'disputed'];
if ($statusF && !in_array($statusF, $allowedStatuses)) {
    $statusF = '';
}

// ── Statistics ────────────────────────────────────────────────────────
$stats = [];

$r = $conn->query("SELECT COUNT(*) AS cnt FROM milestones");
$stats['total'] = (int) $r->fetch_assoc()['cnt'];

$r = $conn->query("SELECT COUNT(*) AS cnt FROM milestones WHERE status = 'pending'");
$stats['pending'] = (int) $r->fetch_assoc()['cnt'];

$r = $conn->query("SELECT COUNT(*) AS cnt FROM milestones WHERE status = 'funded_in_escrow'");
$stats['funded'] = (int) $r->fetch_assoc()['cnt'];

$r = $conn->query("SELECT COUNT(*) AS cnt FROM milestones WHERE status = 'submitted'");
$stats['submitted'] = (int) $r->fetch_assoc()['cnt'];

$r = $conn->query("SELECT COUNT(*) AS cnt FROM milestones WHERE status = 'released'");
$stats['released'] = (int) $r->fetch_assoc()['cnt'];

$r = $conn->query("SELECT COUNT(*) AS cnt FROM milestones WHERE status = 'disputed'");
$stats['disputed'] = (int) $r->fetch_assoc()['cnt'];

$r = $conn->query("SELECT COALESCE(SUM(amount), 0) AS total FROM milestones WHERE status IN ('funded_in_escrow','submitted')");
$stats['escrow_total'] = (float) $r->fetch_assoc()['total'];

$r = $conn->query("SELECT COALESCE(SUM(amount), 0) AS total FROM milestones WHERE status = 'released'");
$stats['released_total'] = (float) $r->fetch_assoc()['total'];

// ── Build query ───────────────────────────────────────────────────────
$where   = [];
$params  = [];
$bindTypes = '';

if ($statusF !== '') {
    $where[]  = 'm.status = ?';
    $params[] = $statusF;
    $bindTypes .= 's';
}
if ($clientQ !== '') {
    $where[]  = '(cu.name LIKE ? OR cu.email LIKE ?)';
    $like     = '%' . $clientQ . '%';
    $params[] = $like;
    $params[] = $like;
    $bindTypes .= 'ss';
}
if ($freelancerQ !== '') {
    $where[]  = '(fu.name LIKE ? OR fu.email LIKE ?)';
    $like     = '%' . $freelancerQ . '%';
    $params[] = $like;
    $params[] = $like;
    $bindTypes .= 'ss';
}

$whereSql = $where ? 'WHERE ' . implode(' AND ', $where) : '';

$countSql = "SELECT COUNT(*) AS cnt
             FROM milestones m
             JOIN contracts ct ON m.contract_id = ct.id
             JOIN clients cl ON ct.client_id = cl.id
             JOIN users cu ON cl.user_id = cu.id
             JOIN freelancers fr ON ct.freelancer_id = fr.id
             JOIN users fu ON fr.user_id = fu.id
             $whereSql";
$countStmt = $conn->prepare($countSql);
if ($bindTypes) $countStmt->bind_param($bindTypes, ...$params);
$countStmt->execute();
$totalItems = (int) $countStmt->get_result()->fetch_assoc()['cnt'];
$countStmt->close();

$totalPages = max(1, (int) ceil($totalItems / $perPage));
if ($page > $totalPages) $page = $totalPages;
$offset = ($page - 1) * $perPage;

$querySql = "SELECT m.id, m.title, m.amount, m.status, m.due_date, m.submission_date,
                    m.submission_github_url, m.description,
                    ct.id AS contract_id, ct.status AS contract_status,
                    cu.name AS client_name, cu.email AS client_email,
                    fu.name AS freelancer_name, fu.email AS freelancer_email,
                    j.title AS job_title
             FROM milestones m
             JOIN contracts ct ON m.contract_id = ct.id
             JOIN clients cl ON ct.client_id = cl.id
             JOIN users cu ON cl.user_id = cu.id
             JOIN freelancers fr ON ct.freelancer_id = fr.id
             JOIN users fu ON fr.user_id = fu.id
             JOIN jobs j ON ct.job_id = j.id
             $whereSql
             ORDER BY FIELD(m.status, 'disputed', 'submitted', 'funded_in_escrow', 'pending', 'released'), m.created_at DESC
             LIMIT ? OFFSET ?";
$bindTypes .= 'ii';
$params[]   = $perPage;
$params[]   = $offset;

$queryStmt = $conn->prepare($querySql);
$queryStmt->bind_param($bindTypes, ...$params);
$queryStmt->execute();
$milestonesResult = $queryStmt->get_result();
$queryStmt->close();

$milestones = [];
while ($row = $milestonesResult->fetch_assoc()) {
    $milestones[] = $row;
}

// ── Badge map ─────────────────────────────────────────────────────────
$statusColors = [
    'pending'          => 'bg-gray-50 text-gray-600 border-gray-200',
    'funded_in_escrow' => 'bg-blue-50 text-blue-600 border-blue-200',
    'submitted'        => 'bg-amber-50 text-amber-600 border-amber-200',
    'released'         => 'bg-emerald-50 text-emerald-600 border-emerald-200',
    'disputed'         => 'bg-red-50 text-red-600 border-red-200',
];
$statusIcons = [
    'pending'          => 'fa-clock',
    'funded_in_escrow' => 'fa-shield-halved',
    'submitted'        => 'fa-paper-plane',
    'released'         => 'fa-check-circle',
    'disputed'         => 'fa-flag',
];

// ── Pagination base URL ─────────────────────────────────────────────
$baseUrl = 'milestones.php?';
if ($statusF !== '')    $baseUrl .= 'status=' . urlencode($statusF) . '&';
if ($clientQ !== '')    $baseUrl .= 'client=' . urlencode($clientQ) . '&';
if ($freelancerQ !== '') $baseUrl .= 'freelancer=' . urlencode($freelancerQ) . '&';
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
    ['key' => 'fraud', 'label' => 'Fraud', 'url' => 'fraud_detection.php', 'icon' => 'fa-shield-halved'],
    ['key' => 'matching', 'label' => 'AI Matching', 'url' => 'ai_matching.php', 'icon' => 'fa-brain'],
    ['key' => 'settings', 'label' => 'Settings', 'url' => 'settings.php', 'icon' => 'fa-cog'],
];
$pageTitle = 'Milestone Monitoring';
$pageSubtitle = $totalItems . ' milestone' . ($totalItems !== 1 ? 's' : '') . ' across all contracts';
$activePage = 'milestones';
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
    </style>

    <!-- Stats Grid -->
    <div class="grid grid-cols-2 md:grid-cols-4 gap-4 mb-8">
        <div class="stat-card bg-white rounded-2xl p-5 border border-gray-100 shadow-sm">
            <div class="flex items-center gap-3 mb-3">
                <div class="w-10 h-10 rounded-xl bg-blue-100 flex items-center justify-center"><i class="fas fa-tasks text-blue-600"></i></div>
                <span class="text-xs font-semibold text-gray-400 uppercase tracking-wide">Total</span>
            </div>
            <p class="text-2xl font-extrabold text-gray-900"><?= $stats['total'] ?></p>
        </div>
        <div class="stat-card bg-white rounded-2xl p-5 border border-gray-100 shadow-sm">
            <div class="flex items-center gap-3 mb-3">
                <div class="w-10 h-10 rounded-xl bg-amber-100 flex items-center justify-center"><i class="fas fa-paper-plane text-amber-600"></i></div>
                <span class="text-xs font-semibold text-gray-400 uppercase tracking-wide">Awaiting Review</span>
            </div>
            <p class="text-2xl font-extrabold text-gray-900"><?= $stats['submitted'] ?></p>
        </div>
        <div class="stat-card bg-white rounded-2xl p-5 border border-gray-100 shadow-sm">
            <div class="flex items-center gap-3 mb-3">
                <div class="w-10 h-10 rounded-xl bg-emerald-100 flex items-center justify-center"><i class="fas fa-check-circle text-emerald-600"></i></div>
                <span class="text-xs font-semibold text-gray-400 uppercase tracking-wide">Released</span>
            </div>
            <p class="text-2xl font-extrabold text-gray-900"><?= $stats['released'] ?></p>
            <p class="text-xs text-gray-400 mt-1"><?= format_currency($stats['released_total']) ?> paid out</p>
        </div>
        <div class="stat-card bg-white rounded-2xl p-5 border border-gray-100 shadow-sm">
            <div class="flex items-center gap-3 mb-3">
                <div class="w-10 h-10 rounded-xl bg-red-100 flex items-center justify-center"><i class="fas fa-flag text-red-600"></i></div>
                <span class="text-xs font-semibold text-gray-400 uppercase tracking-wide">Disputes</span>
            </div>
            <p class="text-2xl font-extrabold <?= $stats['disputed'] > 0 ? 'text-red-600' : 'text-gray-900' ?>"><?= $stats['disputed'] ?></p>
        </div>
    </div>

    <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mb-8">
        <div class="stat-card bg-white rounded-2xl p-5 border border-gray-100 shadow-sm">
            <div class="flex items-center gap-3 mb-2">
                <div class="w-10 h-10 rounded-xl bg-gray-100 flex items-center justify-center"><i class="fas fa-clock text-gray-500"></i></div>
                <span class="text-xs font-semibold text-gray-400 uppercase tracking-wide">Pending</span>
            </div>
            <p class="text-xl font-extrabold text-gray-900"><?= $stats['pending'] ?></p>
        </div>
        <div class="stat-card bg-white rounded-2xl p-5 border border-gray-100 shadow-sm">
            <div class="flex items-center gap-3 mb-2">
                <div class="w-10 h-10 rounded-xl bg-blue-100 flex items-center justify-center"><i class="fas fa-shield-halved text-blue-500"></i></div>
                <span class="text-xs font-semibold text-gray-400 uppercase tracking-wide">In Escrow</span>
            </div>
            <p class="text-xl font-extrabold text-gray-900"><?= $stats['funded'] ?></p>
            <p class="text-xs text-gray-400 mt-1"><?= format_currency($stats['escrow_total']) ?> held</p>
        </div>
    </div>

    <!-- Filters -->
    <div class="bg-white rounded-2xl border border-gray-100 shadow-sm p-5 mb-6">
        <form method="GET" class="flex flex-wrap gap-3 items-end">
            <div class="flex-1 min-w-[140px]">
                <label class="block text-[11px] font-semibold text-gray-500 uppercase tracking-wide mb-1">Status</label>
                <select name="status" class="w-full px-3 py-2 rounded-xl border border-gray-200 text-sm bg-gray-50 focus:bg-white">
                    <option value="">All Statuses</option>
                    <?php foreach ($allowedStatuses as $s): ?>
                        <option value="<?= $s ?>" <?= $statusF === $s ? 'selected' : '' ?>><?= ucwords(str_replace('_', ' ', $s)) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="flex-1 min-w-[140px]">
                <label class="block text-[11px] font-semibold text-gray-500 uppercase tracking-wide mb-1">Client</label>
                <input type="text" name="client" value="<?= sanitize_string($clientQ) ?>" placeholder="Search client..." class="w-full px-3 py-2 rounded-xl border border-gray-200 text-sm bg-gray-50 focus:bg-white">
            </div>
            <div class="flex-1 min-w-[140px]">
                <label class="block text-[11px] font-semibold text-gray-500 uppercase tracking-wide mb-1">Freelancer</label>
                <input type="text" name="freelancer" value="<?= sanitize_string($freelancerQ) ?>" placeholder="Search freelancer..." class="w-full px-3 py-2 rounded-xl border border-gray-200 text-sm bg-gray-50 focus:bg-white">
            </div>
            <div class="flex gap-2">
                <button type="submit" class="btn-grad px-5 py-2 rounded-xl text-white text-sm font-semibold shadow-lg shadow-blue-500/25"><i class="fas fa-search mr-1.5 text-xs"></i> Filter</button>
                <a href="milestones.php" class="px-4 py-2 rounded-xl border border-gray-200 text-sm font-semibold text-gray-600 hover:bg-gray-50">Clear</a>
            </div>
        </form>
    </div>

    <!-- Milestones Table -->
    <div class="bg-white rounded-2xl border border-gray-100 shadow-sm overflow-hidden mb-6">
        <?php if (empty($milestones)): ?>
            <div class="text-center py-16">
                <div class="w-16 h-16 rounded-2xl bg-gray-100 flex items-center justify-center mx-auto mb-4"><i class="fas fa-tasks text-2xl text-gray-300"></i></div>
                <p class="text-gray-500 font-medium">No milestones found</p>
            </div>
        <?php else: ?>
            <div class="overflow-x-auto">
                <table class="w-full">
                    <thead>
                        <tr class="border-b border-gray-100">
                            <th class="text-left px-5 py-3 text-[11px] font-semibold text-gray-400 uppercase tracking-wide">Milestone</th>
                            <th class="text-left px-5 py-3 text-[11px] font-semibold text-gray-400 uppercase tracking-wide">Contract / Job</th>
                            <th class="text-left px-5 py-3 text-[11px] font-semibold text-gray-400 uppercase tracking-wide">Client</th>
                            <th class="text-left px-5 py-3 text-[11px] font-semibold text-gray-400 uppercase tracking-wide">Freelancer</th>
                            <th class="text-right px-5 py-3 text-[11px] font-semibold text-gray-400 uppercase tracking-wide">Amount</th>
                            <th class="text-center px-5 py-3 text-[11px] font-semibold text-gray-400 uppercase tracking-wide">Status</th>
                            <th class="text-center px-5 py-3 text-[11px] font-semibold text-gray-400 uppercase tracking-wide">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-50">
                        <?php foreach ($milestones as $m): ?>
                            <tr class="hover:bg-gray-50/50 transition-colors">
                                <td class="px-5 py-4">
                                    <p class="text-sm font-bold text-gray-900"><?= sanitize_string($m['title']) ?></p>
                                    <p class="text-[11px] text-gray-400 mt-0.5">ID: #<?= $m['id'] ?></p>
                                    <?php if (!empty($m['submission_github_url'])): ?>
                                        <a href="<?= sanitize_string($m['submission_github_url']) ?>" target="_blank" class="text-[11px] text-blue-500 hover:underline"><i class="fab fa-github mr-1"></i>View Submission</a>
                                    <?php endif; ?>
                                </td>
                                <td class="px-5 py-4">
                                    <p class="text-sm text-gray-700"><?= sanitize_string($m['job_title']) ?></p>
                                    <p class="text-[11px] text-gray-400">Contract #<?= $m['contract_id'] ?></p>
                                </td>
                                <td class="px-5 py-4">
                                    <p class="text-sm text-gray-700"><?= sanitize_string($m['client_name']) ?></p>
                                </td>
                                <td class="px-5 py-4">
                                    <p class="text-sm text-gray-700"><?= sanitize_string($m['freelancer_name']) ?></p>
                                </td>
                                <td class="px-5 py-4 text-right">
                                    <span class="text-sm font-bold text-gray-900"><?= format_currency((float) $m['amount']) ?></span>
                                </td>
                                <td class="px-5 py-4 text-center">
                                    <span class="inline-flex items-center gap-1 px-2.5 py-1 rounded-lg text-[10px] font-semibold border <?= $statusColors[$m['status']] ?? '' ?>">
                                        <i class="fas <?= $statusIcons[$m['status']] ?? 'fa-circle' ?> text-[8px]"></i>
                                        <?= ucwords(str_replace('_', ' ', $m['status'])) ?>
                                    </span>
                                </td>
                                <td class="px-5 py-4 text-center">
                                    <?php if ($m['status'] === 'disputed'): ?>
                                        <button onclick="resolveDispute(<?= (int) $m['id'] ?>)" class="inline-flex items-center gap-1 px-3 py-1.5 bg-emerald-500 hover:bg-emerald-600 text-white text-xs font-semibold rounded-lg transition-all">
                                            <i class="fas fa-gavel text-[9px]"></i> Resolve
                                        </button>
                                    <?php else: ?>
                                        <span class="text-xs text-gray-400">—</span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
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

    <!-- Resolve Dispute Modal -->
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
                        <input type="hidden" name="action" value="resolve_dispute">
                        <input type="hidden" name="milestone_id" id="resolve_milestone_id">
                        <div>
                            <label class="block text-xs font-semibold text-gray-700 mb-1.5">Resolution Action</label>
                            <select name="resolution" id="resolve_resolution" required class="w-full px-4 py-2.5 rounded-xl border border-gray-200 text-sm bg-gray-50 focus:bg-white">
                                <option value="release">Release Payment to Freelancer</option>
                                <option value="refund">Refund Client</option>
                            </select>
                        </div>
                        <div>
                            <label class="block text-xs font-semibold text-gray-700 mb-1.5">Admin Note</label>
                            <textarea name="admin_note" id="resolve_note" rows="3" placeholder="Explain the resolution..." class="w-full px-4 py-2.5 rounded-xl border border-gray-200 text-sm bg-gray-50 focus:bg-white resize-none"></textarea>
                        </div>
                    </div>
                    <div class="px-6 pb-6 flex gap-3">
                        <button type="button" onclick="closeResolveModal()" class="flex-1 px-4 py-2.5 rounded-xl border border-gray-200 text-sm font-semibold text-gray-600 hover:bg-gray-50">Cancel</button>
                        <button type="submit" class="flex-1 btn-grad px-4 py-2.5 rounded-xl text-white text-sm font-semibold shadow-lg shadow-blue-500/25">Confirm Resolution</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script>
    var CSRF_TOKEN = '<?= generate_csrf_token() ?>';

    function resolveDispute(id) {
        document.getElementById('resolve_milestone_id').value = id;
        document.getElementById('resolveModal').classList.remove('hidden');
    }
    function closeResolveModal() {
        document.getElementById('resolveModal').classList.add('hidden');
    }
    async function submitResolve(e) {
        e.preventDefault();
        var fd = new FormData(document.getElementById('resolveForm'));
        fd.append('csrf_token', CSRF_TOKEN);
        try {
            var r = await fetch('/finalproject/api/milestones_api.php', { method: 'POST', body: fd });
            var j = await r.json();
            if (j.success) {
                alert(j.message);
                location.reload();
            } else {
                alert('Error: ' + j.message);
            }
        } catch (err) {
            alert('Network error.');
        }
        return false;
    }
    </script>

<?php require_once __DIR__ . '/../components/layout_end.php'; ?>
