<?php

/**
 * Admin Skills Management
 * CRUD for skills with category, usage count, search, pagination.
 */
require_once __DIR__ . '/../auth/auth.php';
require_role('admin');
require_once __DIR__ . '/../config/db.php';

$currentPage = 'skills';

// ── Handle Actions ──────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    if (!verify_csrf_token()) {
        set_flash('error', 'Invalid security token.');
        redirect('skills.php');
    }

    $act = $_POST['action'];

    if ($act === 'create') {
        $name = trim($_POST['skill_name'] ?? '');
        $category = trim($_POST['category'] ?? '');
        if ($name === '' || $category === '') {
            set_flash('error', 'All fields are required.');
            redirect('skills.php');
        }
        $check = $conn->prepare('SELECT COUNT(*) AS cnt FROM skills WHERE skill_name = ?');
        $check->bind_param('s', $name);
        $check->execute();
        if ($check->get_result()->fetch_assoc()['cnt'] > 0) {
            $check->close();
            set_flash('error', 'Skill already exists.');
            redirect('skills.php');
        }
        $check->close();
        $ins = $conn->prepare('INSERT INTO skills (skill_name, category) VALUES (?, ?)');
        $ins->bind_param('ss', $name, $category);
        $ins->execute();
        $ins->close();
        set_flash('success', 'Skill "' . $name . '" created.');
        redirect('skills.php');
    }

    if ($act === 'update') {
        $id = sanitize_int($_POST['skill_id'] ?? 0);
        $name = trim($_POST['skill_name'] ?? '');
        $category = trim($_POST['category'] ?? '');
        if ($id <= 0 || $name === '' || $category === '') {
            set_flash('error', 'All fields are required.');
            redirect('skills.php');
        }
        $upd = $conn->prepare('UPDATE skills SET skill_name = ?, category = ? WHERE id = ?');
        $upd->bind_param('ssi', $name, $category, $id);
        $upd->execute();
        $upd->close();
        set_flash('success', 'Skill updated.');
        redirect('skills.php');
    }

    if ($act === 'delete') {
        $id = sanitize_int($_POST['skill_id'] ?? 0);
        if ($id > 0) {
            $del = $conn->prepare('DELETE FROM skills WHERE id = ?');
            $del->bind_param('i', $id);
            $del->execute();
            $del->close();
            set_flash('success', 'Skill deleted.');
        }
        redirect('skills.php');
    }
}

// ── Filters ─────────────────────────────────────────────────────────
$search = trim($_GET['search'] ?? '');
$catFilter = trim($_GET['category'] ?? '');
$page = max(1, sanitize_int($_GET['page'] ?? 1));
$perPage = 15;

// ── Get categories for dropdown ──────────────────────────────────────
$catResult = $conn->query('SELECT DISTINCT category FROM skills ORDER BY category');
$allCategories = [];
while ($row = $catResult->fetch_assoc())
    $allCategories[] = $row['category'];

// ── Query ────────────────────────────────────────────────────────────
$where = [];
$params = [];
$types = '';

if ($search !== '') {
    $where[] = 's.skill_name LIKE ?';
    $params[] = "%{$search}%";
    $types .= 's';
}
if ($catFilter !== '') {
    $where[] = 's.category = ?';
    $params[] = $catFilter;
    $types .= 's';
}

$whereSql = $where ? 'WHERE ' . implode(' AND ', $where) : '';

$countSql = "SELECT COUNT(*) AS cnt FROM skills s $whereSql";
$countStmt = $conn->prepare($countSql);
if ($types)
    $countStmt->bind_param($types, ...$params);
$countStmt->execute();
$totalItems = (int) $countStmt->get_result()->fetch_assoc()['cnt'];
$countStmt->close();

$pagination = paginate($totalItems, $perPage, $page);
$offset = $pagination['offset'];

$querySql = "SELECT s.id, s.skill_name, s.category, s.created_at,
                    (SELECT COUNT(*) FROM freelancer_skills fs WHERE fs.skill_id = s.id) AS usage_count
             FROM skills s
             $whereSql
             ORDER BY s.skill_name ASC
             LIMIT ? OFFSET ?";
$bindTypes = $types . 'ii';
$bindParams = array_merge($params, [$perPage, $offset]);
$queryStmt = $conn->prepare($querySql);
$queryStmt->bind_param($bindTypes, ...$bindParams);
$queryStmt->execute();
$result = $queryStmt->get_result();
$queryStmt->close();

$skills = [];
while ($row = $result->fetch_assoc()) {
    $skills[] = $row;
}

// ── Stats ───────────────────────────────────────────────────────────
$r = $conn->query('SELECT COUNT(*) AS cnt FROM skills');
$totalSkills = (int) $r->fetch_assoc()['cnt'];

$r = $conn->query('SELECT COUNT(DISTINCT category) AS cnt FROM skills');
$totalCategories = (int) $r->fetch_assoc()['cnt'];

$r = $conn->query('SELECT COUNT(*) AS cnt FROM freelancer_skills');
$totalUsage = (int) $r->fetch_assoc()['cnt'];

$baseUrl = 'skills.php?';
if ($search !== '')
    $baseUrl .= 'search=' . urlencode($search) . '&';
if ($catFilter !== '')
    $baseUrl .= 'category=' . urlencode($catFilter) . '&';
$baseUrl = rtrim($baseUrl, '?&');
if (strpos($baseUrl, '&') === false)
    $baseUrl = rtrim($baseUrl, '?');

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
$pageTitle = 'Skills';
$pageSubtitle = number_format($totalSkills) . ' skills across ' . number_format($totalCategories) . ' categories';
$activePage = 'skills';
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
                <span class="text-xs font-medium text-gray-500 dark:text-slate-400 uppercase tracking-wider">Total Skills</span>
                <i data-lucide="cog" class="w-5 h-5 text-gray-400 dark:text-slate-500" stroke-width="1.5"></i>
            </div>
            <p class="text-3xl font-semibold text-gray-900 dark:text-white mt-1"><?= number_format($totalSkills) ?></p>
        </div>
        <div class="bg-white dark:bg-slate-800 border border-[#E4EBE4] dark:border-slate-700 rounded-lg p-5 shadow-sm flex flex-col transition-all duration-200 hover:shadow-sm hover:-translate-y-0.5">
            <div class="flex items-start justify-between">
                <span class="text-xs font-medium text-gray-500 dark:text-slate-400 uppercase tracking-wider">Categories</span>
                <i data-lucide="folder-open" class="w-5 h-5 text-gray-400 dark:text-slate-500" stroke-width="1.5"></i>
            </div>
            <p class="text-3xl font-semibold text-gray-900 dark:text-white mt-1"><?= number_format($totalCategories) ?></p>
        </div>
        <div class="bg-white dark:bg-slate-800 border border-[#E4EBE4] dark:border-slate-700 rounded-lg p-5 shadow-sm flex flex-col transition-all duration-200 hover:shadow-sm hover:-translate-y-0.5">
            <div class="flex items-start justify-between">
                <span class="text-xs font-medium text-gray-500 dark:text-slate-400 uppercase tracking-wider">Total Assignments</span>
                <i data-lucide="link" class="w-5 h-5 text-gray-400 dark:text-slate-500" stroke-width="1.5"></i>
            </div>
            <p class="text-3xl font-semibold text-gray-900 dark:text-white mt-1"><?= number_format($totalUsage) ?></p>
        </div>
    </div>

    <!-- ═══ SEARCH + FILTERS + ADD ══════════════════════════════════════ -->
    <div class="bg-white dark:bg-slate-800 border border-[#E4EBE4] dark:border-slate-700 rounded-t-lg p-5 mb-0 fade-in">
        <form method="GET" action="skills.php">
            <div class="flex flex-col lg:flex-row gap-4 items-stretch lg:items-center">
                <div class="flex-1 relative">
                    <i data-lucide="search" class="absolute left-3 top-1/2 -translate-y-1/2 w-4 h-4 text-gray-400 dark:text-slate-500" stroke-width="1.5"></i>
                    <input type="text" name="search" value="<?= sanitize_string($search) ?>" placeholder="Search skills..."
                           class="w-full pl-10 pr-4 py-2.5 rounded-lg border border-[#E4EBE4] dark:border-slate-600 text-sm bg-gray-50 dark:bg-slate-700 text-gray-900 dark:text-white placeholder-gray-400 dark:placeholder-slate-500 focus:outline-none focus:ring-2 focus:ring-[#108A00]/30 focus:border-[#108A00] transition-all">
                </div>
                <select name="category" class="px-3 py-2.5 rounded-lg border border-[#E4EBE4] dark:border-slate-600 text-sm text-gray-600 dark:text-slate-300 focus:outline-none focus:ring-2 focus:ring-[#108A00]/30 focus:border-[#108A00] bg-gray-50 dark:bg-slate-700">
                    <option value="">All Categories</option>
                    <?php foreach ($allCategories as $cat): ?>
                        <option value="<?= sanitize_string($cat) ?>" <?= $catFilter === $cat ? 'selected' : '' ?>><?= sanitize_string($cat) ?></option>
                    <?php endforeach; ?>
                </select>
                <button type="submit" class="px-4 py-2.5 rounded-lg border border-[#E4EBE4] dark:border-slate-600 text-sm font-medium text-gray-600 dark:text-slate-300 hover:bg-gray-50 dark:hover:bg-slate-700 transition-colors inline-flex items-center gap-1.5 bg-gray-50 dark:bg-slate-700">
                    <i data-lucide="filter" class="w-4 h-4" stroke-width="1.5"></i> Filter
                </button>
                <button type="button" onclick="document.getElementById('addModal').classList.remove('hidden')" class="px-4 py-2.5 rounded-lg bg-[#108A00] hover:bg-[#0d7300] text-white text-sm font-medium transition-colors inline-flex items-center gap-1.5">
                    <i data-lucide="plus" class="w-4 h-4" stroke-width="1.5"></i> Add Skill
                </button>
            </div>
        </form>
    </div>

    <!-- ═══ SKILLS TABLE ════════════════════════════════════════════════ -->
    <div class="bg-white dark:bg-slate-800 rounded-b-lg border border-[#E4EBE4] dark:border-slate-700 border-t-0 shadow-sm overflow-hidden fade-in" style="animation-delay:.1s">
        <?php if (count($skills) > 0): ?>
        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead>
                    <tr class="border-b border-[#E4EBE4] dark:border-slate-700 bg-gray-50 dark:bg-slate-700/30">
                        <th class="text-left py-3 px-5 text-[11px] font-medium text-gray-500 dark:text-slate-400 uppercase tracking-wider">Skill Name</th>
                        <th class="text-left py-3 px-5 text-[11px] font-medium text-gray-500 dark:text-slate-400 uppercase tracking-wider">Category</th>
                        <th class="text-center py-3 px-5 text-[11px] font-medium text-gray-500 dark:text-slate-400 uppercase tracking-wider">Usage</th>
                        <th class="text-right py-3 px-5 text-[11px] font-medium text-gray-500 dark:text-slate-400 uppercase tracking-wider">Actions</th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ($skills as $sk): ?>
                    <tr class="border-b border-gray-100 dark:border-slate-700/50 last:border-0 hover:bg-[#F9FBF9] dark:hover:bg-slate-700/30 transition-colors">
                        <td class="py-3.5 px-5">
                            <div class="flex items-center gap-3">
                                <div class="w-8 h-8 rounded-lg bg-gray-100 dark:bg-slate-700 flex items-center justify-center flex-shrink-0">
                                    <i data-lucide="cog" class="w-4 h-4 text-gray-500 dark:text-slate-400" stroke-width="1.5"></i>
                                </div>
                                <span class="font-medium text-gray-900 dark:text-white leading-snug"><?= sanitize_string($sk['skill_name']) ?></span>
                            </div>
                        </td>
                        <td class="py-3.5 px-5">
                            <span class="inline-flex items-center px-2 py-0.5 rounded-md text-[10px] font-normal bg-emerald-50 text-emerald-700 dark:bg-emerald-900/20 dark:text-emerald-400 border border-emerald-100 dark:border-emerald-800/30 whitespace-nowrap"><?= sanitize_string($sk['category']) ?></span>
                        </td>
                        <td class="py-3.5 px-5 text-center">
                            <div class="flex items-center justify-center gap-2">
                                <span class="inline-flex items-center justify-center min-w-[26px] h-6 px-2 rounded-md bg-gray-100 dark:bg-slate-700 text-gray-700 dark:text-slate-300 text-xs font-medium"><?= $sk['usage_count'] ?></span>
                                <?php if ($sk['usage_count'] > 0): ?>
                                    <div class="w-16 h-1.5 rounded-full bg-gray-100 dark:bg-slate-700 overflow-hidden">
                                        <div class="h-full rounded-full bg-[#108A00]" style="width: <?= min(100, ($sk['usage_count'] / max(1, $totalSkills)) * 100) ?>%"></div>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </td>
                        <td class="py-3.5 px-5">
                            <div class="job-actions flex items-center justify-end gap-1">
                                <button onclick='openEditModal(<?= json_encode($sk) ?>)' class="w-8 h-8 rounded-lg bg-gray-50 dark:bg-slate-700 text-gray-500 dark:text-slate-400 flex items-center justify-center hover:bg-gray-100 dark:hover:bg-slate-600 transition-colors" title="Edit">
                                    <i data-lucide="pencil" class="w-4 h-4" stroke-width="1.5"></i>
                                </button>
                                <form method="POST" action="skills.php" class="inline" onsubmit="return confirm('Delete this skill?')">
                                    <?= csrf_field() ?>
                                    <input type="hidden" name="action" value="delete">
                                    <input type="hidden" name="skill_id" value="<?= $sk['id'] ?>">
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
                <i data-lucide="cog" class="w-6 h-6 text-gray-300 dark:text-slate-500" stroke-width="1.5"></i>
            </div>
            <p class="text-gray-500 dark:text-slate-400 text-sm mb-1">No skills found</p>
            <p class="text-gray-400 dark:text-slate-500 text-xs">Try adjusting your search</p>
        </div>
        <?php endif; ?>
    </div>

    <?php render_pagination($pagination, $baseUrl); ?>

    <!-- ═══ ADD MODAL ══════════════════════════════════════════════════════ -->
    <div id="addModal" class="hidden fixed inset-0 z-50 flex items-center justify-center p-4">
        <div class="absolute inset-0 bg-black/50" onclick="document.getElementById('addModal').classList.add('hidden')"></div>
        <div class="relative bg-white dark:bg-slate-800 rounded-lg shadow-2xl w-full max-w-md p-6 border border-[#E4EBE4] dark:border-slate-700">
            <h3 class="text-lg font-semibold text-gray-900 dark:text-white mb-4">Add New Skill</h3>
            <form method="POST" action="skills.php">
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="create">
                <div class="mb-3">
                    <label class="block text-[11px] font-medium text-gray-500 dark:text-slate-400 uppercase tracking-wider mb-1.5">Skill Name</label>
                    <input type="text" name="skill_name" required placeholder="e.g. JavaScript"
                        class="w-full px-4 py-2.5 rounded-lg border border-[#E4EBE4] dark:border-slate-600 text-sm bg-gray-50 dark:bg-slate-700 text-gray-900 dark:text-white placeholder-gray-400 dark:placeholder-slate-500 focus:outline-none focus:ring-2 focus:ring-[#108A00]/30 focus:border-[#108A00]">
                </div>
                <div class="mb-4">
                    <label class="block text-[11px] font-medium text-gray-500 dark:text-slate-400 uppercase tracking-wider mb-1.5">Category</label>
                    <input type="text" name="category" required placeholder="e.g. Web Development" list="categoryList"
                        class="w-full px-4 py-2.5 rounded-lg border border-[#E4EBE4] dark:border-slate-600 text-sm bg-gray-50 dark:bg-slate-700 text-gray-900 dark:text-white placeholder-gray-400 dark:placeholder-slate-500 focus:outline-none focus:ring-2 focus:ring-[#108A00]/30 focus:border-[#108A00]">
                    <datalist id="categoryList">
                        <?php foreach ($allCategories as $cat): ?>
                            <option value="<?= sanitize_string($cat) ?>">
                        <?php endforeach; ?>
                    </datalist>
                </div>
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
            <h3 class="text-lg font-semibold text-gray-900 dark:text-white mb-4">Edit Skill</h3>
            <form method="POST" action="skills.php">
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="update">
                <input type="hidden" name="skill_id" id="editSkillId">
                <div class="mb-3">
                    <label class="block text-[11px] font-medium text-gray-500 dark:text-slate-400 uppercase tracking-wider mb-1.5">Skill Name</label>
                    <input type="text" name="skill_name" id="editSkillName" required
                        class="w-full px-4 py-2.5 rounded-lg border border-[#E4EBE4] dark:border-slate-600 text-sm bg-gray-50 dark:bg-slate-700 text-gray-900 dark:text-white focus:outline-none focus:ring-2 focus:ring-[#108A00]/30 focus:border-[#108A00]">
                </div>
                <div class="mb-4">
                    <label class="block text-[11px] font-medium text-gray-500 dark:text-slate-400 uppercase tracking-wider mb-1.5">Category</label>
                    <input type="text" name="category" id="editCategory" required list="editCategoryList"
                        class="w-full px-4 py-2.5 rounded-lg border border-[#E4EBE4] dark:border-slate-600 text-sm bg-gray-50 dark:bg-slate-700 text-gray-900 dark:text-white focus:outline-none focus:ring-2 focus:ring-[#108A00]/30 focus:border-[#108A00]">
                    <datalist id="editCategoryList">
                        <?php foreach ($allCategories as $cat): ?>
                            <option value="<?= sanitize_string($cat) ?>">
                        <?php endforeach; ?>
                    </datalist>
                </div>
                <div class="flex justify-end gap-2">
                    <button type="button" onclick="document.getElementById('editModal').classList.add('hidden')" class="px-4 py-2 rounded-lg border border-[#E4EBE4] dark:border-slate-600 text-sm font-medium text-gray-500 dark:text-slate-400 hover:bg-gray-50 dark:hover:bg-slate-700 transition-colors">Cancel</button>
                    <button type="submit" class="px-5 py-2 rounded-lg bg-[#108A00] hover:bg-[#0d7300] text-white text-sm font-medium transition-colors">Save</button>
                </div>
            </form>
        </div>
    </div>

    <script>
    function openEditModal(skill) {
        document.getElementById('editSkillId').value = skill.id;
        document.getElementById('editSkillName').value = skill.skill_name;
        document.getElementById('editCategory').value = skill.category;
        document.getElementById('editModal').classList.remove('hidden');
    }
    </script>

<?php require_once __DIR__ . '/../components/layout_end.php'; ?>
