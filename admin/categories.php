<?php

/**
 * Admin Categories Management
 * Manage skill categories (derived from skills table). Read-only on jobs.
 */
require_once __DIR__ . '/../auth/auth.php';
require_role('admin');
require_once __DIR__ . '/../config/db.php';

$currentPage = 'categories';

// ── Handle Actions ──────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    if (!verify_csrf_token()) {
        set_flash('error', 'Invalid security token.');
        redirect('categories.php');
    }

    $act = $_POST['action'];

    if ($act === 'create') {
        $name = trim($_POST['name'] ?? '');
        if ($name === '') {
            set_flash('error', 'Category name is required.');
            redirect('categories.php');
        }
        $check = $conn->prepare('SELECT COUNT(*) AS cnt FROM skills WHERE category = ?');
        $check->bind_param('s', $name);
        $check->execute();
        if ($check->get_result()->fetch_assoc()['cnt'] > 0) {
            $check->close();
            set_flash('error', 'Category already exists.');
            redirect('categories.php');
        }
        $check->close();
        $ins = $conn->prepare('INSERT INTO skills (skill_name, category) VALUES (?, ?)');
        $placeholder = strtolower(str_replace(' ', '_', $name)) . '_general';
        $ins->bind_param('ss', $placeholder, $name);
        $ins->execute();
        $ins->close();
        set_flash('success', 'Category "' . $name . '" created.');
        redirect('categories.php');
    }

    if ($act === 'update') {
        $oldName = trim($_POST['old_name'] ?? '');
        $newName = trim($_POST['new_name'] ?? '');
        if ($oldName === '' || $newName === '') {
            set_flash('error', 'Both fields are required.');
            redirect('categories.php');
        }
        if ($oldName !== $newName) {
            $dup = $conn->prepare('SELECT COUNT(*) AS cnt FROM skills WHERE category = ?');
            $dup->bind_param('s', $newName);
            $dup->execute();
            if ($dup->get_result()->fetch_assoc()['cnt'] > 0) {
                $dup->close();
                set_flash('error', 'Target category already exists.');
                redirect('categories.php');
            }
            $dup->close();
        }
        $upd = $conn->prepare('UPDATE skills SET category = ? WHERE category = ?');
        $upd->bind_param('ss', $newName, $oldName);
        $upd->execute();
        $upd->close();
        set_flash('success', 'Category renamed to "' . $newName . '".');
        redirect('categories.php');
    }

    if ($act === 'delete') {
        $name = trim($_POST['name'] ?? '');
        if ($name !== '') {
            $del = $conn->prepare('DELETE FROM skills WHERE category = ?');
            $del->bind_param('s', $name);
            $del->execute();
            $del->close();
            set_flash('success', 'Category "' . $name . '" and its skills deleted.');
        }
        redirect('categories.php');
    }
}

// ── Filters ─────────────────────────────────────────────────────────
$search = trim($_GET['search'] ?? '');
$page = max(1, sanitize_int($_GET['page'] ?? 1));
$perPage = 15;

// ── Query Categories ────────────────────────────────────────────────
$where = '';
$params = [];
$types = '';
if ($search !== '') {
    $where = 'WHERE s.category LIKE ?';
    $s = "%{$search}%";
    $params[] = $s;
    $types = 's';
}

$countSql = "SELECT COUNT(DISTINCT s.category) AS cnt FROM skills s $where";
$countStmt = $conn->prepare($countSql);
if ($types)
    $countStmt->bind_param($types, ...$params);
$countStmt->execute();
$totalItems = (int) $countStmt->get_result()->fetch_assoc()['cnt'];
$countStmt->close();

$pagination = paginate($totalItems, $perPage, $page);
$offset = $pagination['offset'];

$querySql = "SELECT s.category,
                    COUNT(s.id) AS skill_count,
                    (SELECT COUNT(DISTINCT fs.freelancer_id) FROM freelancer_skills fs JOIN skills fs2 ON fs.skill_id = fs2.id WHERE fs2.category = s.category) AS user_count
             FROM skills s
             $where
             GROUP BY s.category
             ORDER BY s.category ASC
             LIMIT ? OFFSET ?";
$bindTypes = $types . 'ii';
$bindParams = array_merge($params, [$perPage, $offset]);
$queryStmt = $conn->prepare($querySql);
$queryStmt->bind_param($bindTypes, ...$bindParams);
$queryStmt->execute();
$result = $queryStmt->get_result();
$queryStmt->close();

$categories = [];
while ($row = $result->fetch_assoc()) {
    $categories[] = $row;
}

// ── Stats ───────────────────────────────────────────────────────────
$r = $conn->query('SELECT COUNT(DISTINCT category) AS cnt FROM skills');
$totalCategories = (int) $r->fetch_assoc()['cnt'];

$r = $conn->query('SELECT COUNT(*) AS cnt FROM skills');
$totalSkills = (int) $r->fetch_assoc()['cnt'];

$baseUrl = 'categories.php';
if ($search !== '')
    $baseUrl .= '?search=' . urlencode($search);

// ── Layout Setup ────────────────────────────────────────────────────
$_userId = $_SESSION['user_id'];
$sStmt2 = $conn->prepare('SELECT name, profile_image FROM users WHERE id = ?');
$sStmt2->bind_param('i', $_userId);
$sStmt2->execute();
$_navUserRow = $sStmt2->get_result()->fetch_assoc();
$sStmt2->close();
$adminName = $_navUserRow['name'] ?? 'Admin';

$navItems = [
    ['key' => 'dashboard', 'label' => 'Dashboard', 'url' => 'dashboard.php', 'icon' => 'layout-grid'],
    ['key' => 'users', 'label' => 'Users', 'url' => 'users.php', 'icon' => 'users'],
    ['key' => 'clients', 'label' => 'Clients', 'url' => 'clients.php', 'icon' => 'user-check'],
    ['key' => 'jobs', 'label' => 'Jobs', 'url' => 'jobs.php', 'icon' => 'briefcase'],
    ['key' => 'categories', 'label' => 'Categories', 'url' => 'categories.php', 'icon' => 'folder-open'],
    ['key' => 'skills', 'label' => 'Skills', 'url' => 'skills.php', 'icon' => 'settings'],
    ['key' => 'payments', 'label' => 'Payments', 'url' => 'payments.php', 'icon' => 'credit-card'],
    ['key' => 'wallets', 'label' => 'Wallets', 'url' => 'wallets.php', 'icon' => 'wallet'],
    ['key' => 'contracts', 'label' => 'Contracts', 'url' => 'contracts.php', 'icon' => 'file-text'],
    ['key' => 'milestones', 'label' => 'Milestones', 'url' => 'milestones.php', 'icon' => 'list-checks'],
    ['key' => 'reviews', 'label' => 'Reviews', 'url' => 'reviews.php', 'icon' => 'star'],
    ['key' => 'disputes', 'label' => 'Disputes', 'url' => 'disputes.php', 'icon' => 'scale'],
    ['key' => 'fraud', 'label' => 'Fraud', 'url' => 'fraud_detection.php', 'icon' => 'shield'],
    ['key' => 'notifications', 'label' => 'Notifications', 'url' => 'notifications.php', 'icon' => 'bell'],
    ['key' => 'ai_monitor', 'label' => 'AI Monitor', 'url' => 'ai_monitor.php', 'icon' => 'brain'],
    ['key' => 'analytics', 'label' => 'Analytics', 'url' => 'analytics.php', 'icon' => 'chart-pie'],
    ['key' => 'settings', 'label' => 'Settings', 'url' => 'settings.php', 'icon' => 'settings'],
];
$pageTitle = 'Categories';
$pageSubtitle = number_format($totalCategories) . ' categories';
$activePage = 'categories';
$user = ['name' => $adminName, 'profile_image' => $_navUserRow['profile_image'] ?? null];
$unreadCount = 0;
$profileLink = 'profile.php';
require_once __DIR__ . '/../components/layout_start.php';
$conn->close();
?>
    <style>
    .job-actions{transition:opacity .15s}
    .filter-panel{max-height:0;overflow:hidden;transition:max-height .3s ease}
    .filter-panel.open{max-height:500px}

    /* Pagination overrides */
    nav.flex.items-center.justify-center.gap-6 a,
    nav.flex.items-center.justify-center.gap-6 > div a {
        width: 2rem !important;
        height: 2rem !important;
        border-radius: 0.375rem !important;
        border: 1px solid #E4EBE4 !important;
        background: white !important;
        font-size: 0.75rem !important;
        font-weight: 500 !important;
        color: #6B7280 !important;
        transition: all 0.15s !important;
    }
    nav.flex.items-center.justify-center.gap-6 a:hover,
    nav.flex.items-center.justify-center.gap-6 > div a:hover {
        background: #F9FAFB !important;
        border-color: #D1D5DB !important;
    }
    nav.flex.items-center.justify-center.gap-6 a[class*="bg-gray-900"],
    nav.flex.items-center.justify-center.gap-6 > div a[class*="bg-gray-900"] {
        background: #108A00 !important;
        border-color: #108A00 !important;
        color: white !important;
        box-shadow: 0 1px 3px rgba(16,138,0,0.2) !important;
    }
    nav.flex.items-center.justify-center.gap-6 span.w-9,
    nav.flex.items-center.justify-center.gap-6 > div span.w-9 {
        width: 2rem !important;
        height: 2rem !important;
        border-radius: 0.375rem !important;
    }
    </style>

    <?php display_flash('success'); ?>
    <?php display_flash('error'); ?>

    <!-- ═══ STAT CARDS ════════════════════════════════════════════════ -->
    <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mb-6 fade-in">
        <div class="bg-white dark:bg-slate-800 border border-[#E4EBE4] dark:border-slate-700 rounded-lg p-5 shadow-sm flex flex-col transition-all duration-200 hover:shadow-sm hover:-translate-y-0.5">
            <div class="flex items-start justify-between">
                <span class="text-xs font-medium text-gray-500 dark:text-slate-400 uppercase tracking-wider">Total Categories</span>
                <i data-lucide="folder-open" class="w-5 h-5 text-gray-400 dark:text-slate-500" stroke-width="1.5"></i>
            </div>
            <p class="text-3xl font-semibold text-gray-900 dark:text-white mt-1"><?= number_format($totalCategories) ?></p>
        </div>
        <div class="bg-white dark:bg-slate-800 border border-[#E4EBE4] dark:border-slate-700 rounded-lg p-5 shadow-sm flex flex-col transition-all duration-200 hover:shadow-sm hover:-translate-y-0.5">
            <div class="flex items-start justify-between">
                <span class="text-xs font-medium text-gray-500 dark:text-slate-400 uppercase tracking-wider">Total Skills</span>
                <i data-lucide="cog" class="w-5 h-5 text-gray-400 dark:text-slate-500" stroke-width="1.5"></i>
            </div>
            <p class="text-3xl font-semibold text-gray-900 dark:text-white mt-1"><?= number_format($totalSkills) ?></p>
        </div>
        <div class="bg-white dark:bg-slate-800 border border-[#E4EBE4] dark:border-slate-700 rounded-lg p-5 shadow-sm flex flex-col transition-all duration-200 hover:shadow-sm hover:-translate-y-0.5">
            <div class="flex items-start justify-between">
                <span class="text-xs font-medium text-gray-500 dark:text-slate-400 uppercase tracking-wider">Avg Skills/Cat</span>
                <i data-lucide="calculator" class="w-5 h-5 text-gray-400 dark:text-slate-500" stroke-width="1.5"></i>
            </div>
            <p class="text-3xl font-semibold text-gray-900 dark:text-white mt-1"><?= $totalCategories > 0 ? number_format($totalSkills / $totalCategories, 1) : '0' ?></p>
        </div>
    </div>

    <!-- ═══ SEARCH + ADD ════════════════════════════════════════════════ -->
    <div class="bg-white dark:bg-slate-800 border border-[#E4EBE4] dark:border-slate-700 rounded-t-lg p-5 mb-0 fade-in">
        <div class="flex flex-col sm:flex-row gap-4 items-stretch sm:items-center">
            <form method="GET" action="categories.php" class="flex-1 flex gap-4">
                <div class="flex-1 relative">
                    <i data-lucide="search" class="absolute left-3 top-1/2 -translate-y-1/2 w-4 h-4 text-gray-400 dark:text-slate-500" stroke-width="1.5"></i>
                    <input type="text" name="search" value="<?= sanitize_string($search) ?>" placeholder="Search categories..."
                           class="w-full pl-10 pr-4 py-2.5 rounded-lg border border-[#E4EBE4] dark:border-slate-600 text-sm bg-gray-50 dark:bg-slate-700 text-gray-900 dark:text-white placeholder-gray-400 dark:placeholder-slate-500 focus:outline-none focus:ring-2 focus:ring-[#108A00]/30 focus:border-[#108A00] transition-all">
                </div>
                <button type="submit" class="px-4 py-2.5 rounded-lg border border-[#E4EBE4] dark:border-slate-600 text-sm font-medium text-gray-600 dark:text-slate-300 hover:bg-gray-50 dark:hover:bg-slate-700 transition-colors inline-flex items-center gap-1.5 bg-gray-50 dark:bg-slate-700">
                    <i data-lucide="filter" class="w-4 h-4" stroke-width="1.5"></i> Filter
                </button>
            </form>
            <button onclick="document.getElementById('addModal').classList.remove('hidden')" class="px-4 py-2.5 rounded-lg bg-[#108A00] hover:bg-[#0d7300] text-white text-sm font-medium transition-colors inline-flex items-center gap-1.5">
                <i data-lucide="plus" class="w-4 h-4" stroke-width="1.5"></i> Add Category
            </button>
        </div>
    </div>

    <!-- ═══ CATEGORIES TABLE ══════════════════════════════════════════════ -->
    <div class="bg-white dark:bg-slate-800 rounded-b-lg border border-[#E4EBE4] dark:border-slate-700 border-t-0 shadow-sm overflow-hidden fade-in" style="animation-delay:.1s">
        <?php if (count($categories) > 0): ?>
        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead>
                    <tr class="border-b border-[#E4EBE4] dark:border-slate-700 bg-gray-50 dark:bg-slate-700/30">
                        <th class="text-left py-3 px-5 text-[11px] font-medium text-gray-500 dark:text-slate-400 uppercase tracking-wider">Category Name</th>
                        <th class="text-center py-3 px-5 text-[11px] font-medium text-gray-500 dark:text-slate-400 uppercase tracking-wider">Skills</th>
                        <th class="text-center py-3 px-5 text-[11px] font-medium text-gray-500 dark:text-slate-400 uppercase tracking-wider">Users</th>
                        <th class="text-right py-3 px-5 text-[11px] font-medium text-gray-500 dark:text-slate-400 uppercase tracking-wider">Actions</th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ($categories as $cat): ?>
                    <tr class="border-b border-gray-100 dark:border-slate-700/50 last:border-0 hover:bg-[#F9FBF9] dark:hover:bg-slate-700/30 transition-colors">
                        <td class="py-3.5 px-5">
                            <div class="flex items-center gap-3">
                                <div class="w-9 h-9 rounded-lg bg-gray-100 dark:bg-slate-700 flex items-center justify-center flex-shrink-0">
                                    <i data-lucide="folder" class="w-4 h-4 text-gray-500 dark:text-slate-400" stroke-width="1.5"></i>
                                </div>
                                <span class="font-medium text-gray-900 dark:text-white leading-snug"><?= sanitize_string($cat['category']) ?></span>
                            </div>
                        </td>
                        <td class="py-3.5 px-5 text-center">
                            <span class="inline-flex items-center justify-center min-w-[26px] h-6 px-2 rounded-md bg-gray-100 dark:bg-slate-700 text-gray-700 dark:text-slate-300 text-xs font-medium"><?= $cat['skill_count'] ?></span>
                        </td>
                        <td class="py-3.5 px-5 text-center">
                            <span class="inline-flex items-center justify-center min-w-[26px] h-6 px-2 rounded-md bg-gray-100 dark:bg-slate-700 text-gray-700 dark:text-slate-300 text-xs font-medium"><?= $cat['user_count'] ?></span>
                        </td>
                        <td class="py-3.5 px-5">
                            <div class="job-actions flex items-center justify-end gap-1">
                                <button onclick='openEditModal(<?= json_encode($cat['category']) ?>)' class="w-8 h-8 rounded-lg bg-gray-50 dark:bg-slate-700 text-gray-500 dark:text-slate-400 flex items-center justify-center hover:bg-gray-100 dark:hover:bg-slate-600 transition-colors" title="Edit">
                                    <i data-lucide="pencil" class="w-4 h-4" stroke-width="1.5"></i>
                                </button>
                                <form method="POST" action="categories.php" class="inline" onsubmit="return confirm('Delete category <?= sanitize_string(addslashes($cat['category'])) ?>? All skills in this category will be removed.')">
                                    <?= csrf_field() ?>
                                    <input type="hidden" name="action" value="delete">
                                    <input type="hidden" name="name" value="<?= sanitize_string($cat['category']) ?>">
                                    <button type="submit" class="w-8 h-8 rounded-lg bg-gray-50 dark:bg-slate-700 text-gray-500 dark:text-slate-400 flex items-center justify-center hover:bg-red-50 dark:hover:bg-red-900/20 hover:text-red-500 dark:hover:text-red-400 transition-colors" title="Delete">
                                        <i data-lucide="trash-2" class="w-4 h-4" stroke-width="1.5"></i>
                                    </button>
                                </form>
                            </div>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php else: ?>
        <div class="text-center py-16">
            <div class="w-14 h-14 rounded-xl bg-gray-100 dark:bg-slate-700 flex items-center justify-center mx-auto mb-3">
                <i data-lucide="folder-open" class="w-6 h-6 text-gray-300 dark:text-slate-500" stroke-width="1.5"></i>
            </div>
            <p class="text-gray-500 dark:text-slate-400 text-sm mb-1">No categories found</p>
            <p class="text-gray-400 dark:text-slate-500 text-xs">Try adjusting your search</p>
        </div>
        <?php endif; ?>
    </div>

    <?php render_pagination($pagination, $baseUrl); ?>

    <!-- ═══ ADD MODAL ══════════════════════════════════════════════════════ -->
    <div id="addModal" class="hidden fixed inset-0 z-50 flex items-center justify-center p-4">
        <div class="absolute inset-0 bg-black/50" onclick="document.getElementById('addModal').classList.add('hidden')"></div>
        <div class="relative bg-white dark:bg-slate-800 rounded-lg shadow-2xl w-full max-w-md p-6 border border-[#E4EBE4] dark:border-slate-700">
            <h3 class="text-lg font-semibold text-gray-900 dark:text-white mb-4">Add New Category</h3>
            <form method="POST" action="categories.php">
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="create">
                <input type="text" name="name" required placeholder="e.g. Web Development"
                    class="w-full px-4 py-2.5 rounded-lg border border-[#E4EBE4] dark:border-slate-600 text-sm bg-gray-50 dark:bg-slate-700 text-gray-900 dark:text-white placeholder-gray-400 dark:placeholder-slate-500 focus:outline-none focus:ring-2 focus:ring-[#108A00]/30 focus:border-[#108A00] mb-4">
                <div class="flex justify-end gap-2">
                    <button type="button" onclick="document.getElementById('addModal').classList.add('hidden')" class="px-4 py-2 rounded-lg border border-[#E4EBE4] dark:border-slate-600 text-sm font-medium text-gray-500 dark:text-slate-400 hover:bg-gray-50 dark:hover:bg-slate-700 transition-colors">Cancel</button>
                    <button type="submit" class="px-5 py-2 rounded-lg bg-[#108A00] hover:bg-[#0d7300] text-white text-sm font-medium transition-colors">Create</button>
                </div>
            </form>
        </div>
    </div>

    <!-- ═══ EDIT MODAL ══════════════════════════════════════════════════════ -->
    <div id="editModal" class="hidden fixed inset-0 z-50 flex items-center justify-center p-4">
        <div class="absolute inset-0 bg-black/50" onclick="document.getElementById('editModal').classList.add('hidden')"></div>
        <div class="relative bg-white dark:bg-slate-800 rounded-lg shadow-2xl w-full max-w-md p-6 border border-[#E4EBE4] dark:border-slate-700">
            <h3 class="text-lg font-semibold text-gray-900 dark:text-white mb-4">Rename Category</h3>
            <form method="POST" action="categories.php">
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="update">
                <input type="hidden" name="old_name" id="editOldName">
                <input type="text" name="new_name" id="editNewName" required
                    class="w-full px-4 py-2.5 rounded-lg border border-[#E4EBE4] dark:border-slate-600 text-sm bg-gray-50 dark:bg-slate-700 text-gray-900 dark:text-white placeholder-gray-400 dark:placeholder-slate-500 focus:outline-none focus:ring-2 focus:ring-[#108A00]/30 focus:border-[#108A00] mb-4">
                <div class="flex justify-end gap-2">
                    <button type="button" onclick="document.getElementById('editModal').classList.add('hidden')" class="px-4 py-2 rounded-lg border border-[#E4EBE4] dark:border-slate-600 text-sm font-medium text-gray-500 dark:text-slate-400 hover:bg-gray-50 dark:hover:bg-slate-700 transition-colors">Cancel</button>
                    <button type="submit" class="px-5 py-2 rounded-lg bg-[#108A00] hover:bg-[#0d7300] text-white text-sm font-medium transition-colors">Save</button>
                </div>
            </form>
        </div>
    </div>

    <script>
    function openEditModal(name) {
        document.getElementById('editOldName').value = name;
        document.getElementById('editNewName').value = name;
        document.getElementById('editModal').classList.remove('hidden');
    }
    </script>

<?php require_once __DIR__ . '/../components/layout_end.php'; ?>
