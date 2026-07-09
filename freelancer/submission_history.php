<?php
$activePage = 'contracts';
require_once __DIR__ . '/../auth/auth.php';
require_role('freelancer');
require_once __DIR__ . '/../config/db.php';

$userId = $_SESSION['user_id'];

$uStmt = $conn->prepare('SELECT name, profile_image FROM users WHERE id = ?');
$uStmt->bind_param('i', $userId);
$uStmt->execute();
$user = $uStmt->get_result()->fetch_assoc();
$uStmt->close();

$statusFilter = $_GET['status'] ?? 'all';
$page = max(1, intval($_GET['page'] ?? 1));
$perPage = 10;

$allowedStatuses = ['all', 'submitted', 'released', 'disputed'];
if (!in_array($statusFilter, $allowedStatuses)) {
    $statusFilter = 'all';
}

$where = 'WHERE c.freelancer_id = ? AND m.status IN (\'submitted\', \'released\', \'disputed\')';
$params = [$userId];
$types = 'i';

if ($statusFilter !== 'all') {
    $where .= ' AND m.status = ?';
    $params[] = $statusFilter;
    $types .= 's';
}

$countSql = "SELECT COUNT(*) AS total FROM milestones m JOIN contracts c ON m.contract_id = c.id $where";
$countStmt = $conn->prepare($countSql);
$countStmt->bind_param($types, ...$params);
$countStmt->execute();
$totalFiltered = (int) $countStmt->get_result()->fetch_assoc()['total'];
$countStmt->close();

$pagination = paginate($totalFiltered, $perPage, $page);

$querySql = "SELECT m.*, c.id AS contract_id, c.status AS contract_status,
             j.title AS job_title, u.name AS client_name
             FROM milestones m
             JOIN contracts c ON m.contract_id = c.id
             JOIN jobs j ON c.job_id = j.id
             JOIN users u ON c.client_id = u.id
             $where
             ORDER BY m.submission_date DESC
             LIMIT ? OFFSET ?";

$finalTypes = $types . 'ii';
$finalParams = array_merge($params, [$perPage, $pagination['offset']]);

$stmt = $conn->prepare($querySql);
$stmt->bind_param($finalTypes, ...$finalParams);
$stmt->execute();
$submissionsResult = $stmt->get_result();
$stmt->close();

$conn->close();

$statusColors = [
    'submitted' => 'bg-blue-50 text-blue-600 border border-blue-200',
    'released'  => 'bg-emerald-50 text-emerald-600 border border-emerald-200',
    'disputed'  => 'bg-red-50 text-red-500 border border-red-200',
];
$statusIcons = [
    'submitted' => 'fa-paper-plane',
    'released'  => 'fa-check-circle',
    'disputed'  => 'fa-exclamation-triangle',
];

$pageTitle = 'Submission History';
$pageSubtitle = 'Track all your milestone submissions';
$activePage = 'contracts';
$user = ['name' => $user['name'] ?? 'Freelancer', 'profile_image' => $user['profile_image'] ?? null];
$unreadCount = get_unread_message_count($userId, 'freelancer');
require_once __DIR__ . '/../components/freelancer_header.php';
?>
    <?php display_flash('success') ?>
    <?php display_flash('error') ?>

    <!-- Status Filters -->
    <div class="bg-white dark:bg-slate-800 rounded-2xl p-5 border border-gray-100 dark:border-slate-700 shadow-sm fade-in">
        <div class="flex flex-wrap gap-2">
            <?php
            $filters = [
                'all'       => ['All', 'fa-layer-group', 'text-gray-600', 'bg-gray-100'],
                'submitted' => ['Awaiting Review', 'fa-paper-plane', 'text-blue-600', 'bg-blue-50'],
                'released'  => ['Approved', 'fa-check-circle', 'text-emerald-600', 'bg-emerald-50'],
                'disputed'  => ['Disputed', 'fa-exclamation-triangle', 'text-red-500', 'bg-red-50'],
            ];
            foreach ($filters as $key => $label):
                $isActive = $statusFilter === $key;
                $linkParams = $key !== 'all' ? '?' . http_build_query(['status' => $key]) : '';
            ?>
                <a href="?<?= $linkParams ?>"
                   class="inline-flex items-center gap-1.5 px-4 py-2 rounded-xl text-xs font-semibold transition-all <?= $isActive ? $label[3] . ' ' . $label[2] . ' border border-current/20' : 'text-gray-500 bg-gray-50 hover:bg-gray-100 border border-transparent' ?>">
                    <i class="fas <?= $label[1] ?> text-[10px]"></i> <?= $label[0] ?>
                </a>
            <?php endforeach; ?>
        </div>
    </div>

    <!-- Submissions List -->
    <?php if ($submissionsResult->num_rows > 0): ?>
        <div class="space-y-4">
            <?php while ($s = $submissionsResult->fetch_assoc()): ?>
                <div class="bg-white dark:bg-slate-800 rounded-2xl border border-gray-100 dark:border-slate-700 shadow-sm hover:shadow-md transition-all fade-in">
                    <div class="p-6">
                        <div class="flex flex-col sm:flex-row sm:items-start gap-4">
                            <div class="flex items-center justify-center w-12 h-12 rounded-xl bg-gradient-to-br from-blue-500 to-cyan-500 flex-shrink-0">
                                <i class="fas fa-file-code text-white"></i>
                            </div>
                            <div class="flex-1 min-w-0">
                                <div class="flex flex-wrap items-center gap-2 mb-1">
                                    <a href="contract_detail.php?id=<?= (int) $s['contract_id'] ?>" class="text-sm font-bold text-gray-900 hover:text-blue-600 transition-colors">
                                        <?= sanitize_string($s['title']) ?>
                                    </a>
                                    <span class="inline-flex items-center gap-1 px-2.5 py-1 rounded-lg text-[10px] font-semibold <?= $statusColors[$s['status']] ?? '' ?>">
                                        <i class="fas <?= $statusIcons[$s['status']] ?? 'fa-circle' ?> text-[8px]"></i>
                                        <?= ucfirst($s['status']) ?>
                                    </span>
                                </div>
                                <div class="flex flex-wrap items-center gap-x-4 gap-y-1 text-xs text-gray-400 mb-2">
                                    <span class="flex items-center gap-1.5">
                                        <i class="fas fa-briefcase text-blue-400"></i>
                                        <?= sanitize_string($s['job_title']) ?>
                                    </span>
                                    <span class="flex items-center gap-1.5">
                                        <i class="fas fa-user text-violet-400"></i>
                                        <?= sanitize_string($s['client_name']) ?>
                                    </span>
                                    <span class="flex items-center gap-1.5">
                                        <i class="fas fa-dollar-sign text-emerald-500"></i>
                                        <span class="font-semibold text-gray-700"><?= format_currency((float) $s['amount']) ?></span>
                                    </span>
                                </div>

                                <?php if (!empty($s['submission_github_url'])): ?>
                                    <div class="flex items-center gap-2 mb-2">
                                        <i class="fab fa-github text-gray-400 text-xs"></i>
                                        <a href="<?= sanitize_string($s['submission_github_url']) ?>" target="_blank" rel="noopener" class="text-xs text-blue-600 hover:text-blue-700 hover:underline truncate max-w-md">
                                            <?= sanitize_string($s['submission_github_url']) ?>
                                        </a>
                                    </div>
                                <?php endif; ?>

                                <?php if (!empty($s['submission_note'])): ?>
                                    <div class="bg-gray-50 rounded-lg p-3 border border-gray-100 mt-2">
                                        <p class="text-[11px] text-gray-400 font-semibold mb-1">Submission Note</p>
                                        <p class="text-xs text-gray-600 line-clamp-2"><?= nl2br(sanitize_string($s['submission_note'])) ?></p>
                                    </div>
                                <?php endif; ?>

                                <div class="flex items-center gap-4 text-[11px] text-gray-400 mt-2">
                                    <?php if ($s['submission_date']): ?>
                                        <span class="flex items-center gap-1">
                                            <i class="fas fa-clock"></i>
                                            Submitted <?= time_ago($s['submission_date']) ?>
                                        </span>
                                    <?php endif; ?>
                                    <span class="flex items-center gap-1">
                                        <i class="fas fa-calendar"></i>
                                        Created <?= date('M d, Y', strtotime($s['created_at'])) ?>
                                    </span>
                                </div>
                            </div>

                            <a href="contract_detail.php?id=<?= (int) $s['contract_id'] ?>" class="inline-flex items-center gap-1.5 px-3 py-1.5 bg-gray-100 hover:bg-gray-200 text-gray-600 text-xs font-semibold rounded-lg transition-all flex-shrink-0">
                                <i class="fas fa-eye text-[9px]"></i> View
                            </a>
                        </div>
                    </div>
                </div>
            <?php endwhile; ?>
        </div>

        <!-- Pagination -->
        <?php if ($pagination['total_pages'] > 1): ?>
            <div class="flex items-center justify-between bg-white dark:bg-slate-800 rounded-2xl p-4 border border-gray-100 dark:border-slate-700 shadow-sm fade-in">
                <p class="text-xs text-gray-400">
                    Page <span class="font-semibold text-gray-600"><?= $pagination['current_page'] ?></span> of <span class="font-semibold text-gray-600"><?= $pagination['total_pages'] ?></span>
                    <span class="text-gray-300 mx-1">|</span>
                    <span class="font-semibold text-gray-600"><?= $totalFiltered ?></span> submission<?= $totalFiltered !== 1 ? 's' : '' ?>
                </p>
                <div class="flex items-center gap-1">
                    <?php if ($pagination['has_prev']): ?>
                        <a href="?status=<?= $statusFilter ?>&page=<?= $pagination['current_page'] - 1 ?>" class="w-9 h-9 flex items-center justify-center rounded-xl border border-gray-200 text-gray-500 hover:bg-gray-50 text-sm">
                            <i class="fas fa-chevron-left text-xs"></i>
                        </a>
                    <?php endif; ?>
                    <?php for ($i = max(1, $pagination['current_page'] - 2); $i <= min($pagination['total_pages'], $pagination['current_page'] + 2); $i++): ?>
                        <a href="?status=<?= $statusFilter ?>&page=<?= $i ?>" class="w-9 h-9 flex items-center justify-center rounded-xl text-sm font-medium transition-all <?= $i === $pagination['current_page'] ? 'btn-grad text-white shadow-sm' : 'text-gray-500 hover:bg-gray-50' ?>"><?= $i ?></a>
                    <?php endfor; ?>
                    <?php if ($pagination['has_next']): ?>
                        <a href="?status=<?= $statusFilter ?>&page=<?= $pagination['current_page'] + 1 ?>" class="w-9 h-9 flex items-center justify-center rounded-xl border border-gray-200 text-gray-500 hover:bg-gray-50 text-sm">
                            <i class="fas fa-chevron-right text-xs"></i>
                        </a>
                    <?php endif; ?>
                </div>
            </div>
        <?php endif; ?>

    <?php else: ?>
        <div class="bg-white dark:bg-slate-800 rounded-2xl border border-gray-100 dark:border-slate-700 shadow-sm fade-in">
            <div class="text-center py-16 px-6">
                <div class="w-24 h-24 rounded-3xl bg-gradient-to-br from-blue-50 to-cyan-50 dark:from-blue-900/20 dark:to-cyan-900/20 flex items-center justify-center mx-auto mb-6 border border-blue-100 dark:border-blue-800">
                    <i class="fas fa-paper-plane text-4xl text-blue-300"></i>
                </div>
                <h3 class="text-xl font-bold text-gray-900 dark:text-white mb-2">
                    <?= $statusFilter !== 'all' ? 'No submissions found' : 'No submissions yet' ?>
                </h3>
                <p class="text-sm text-gray-400 mb-6 max-w-md mx-auto">
                    <?php if ($statusFilter !== 'all'): ?>
                        No submissions match this filter.
                    <?php else: ?>
                        Submit work on your funded milestones to see them here.
                    <?php endif; ?>
                </p>
                <a href="contracts.php" class="btn-grad inline-flex items-center gap-2 text-white font-bold px-6 py-3 rounded-xl text-sm">
                    <i class="fas fa-handshake text-xs"></i> View Contracts
                </a>
            </div>
        </div>
    <?php endif; ?>

<?php require_once __DIR__ . '/../components/freelancer_footer.php'; ?>
