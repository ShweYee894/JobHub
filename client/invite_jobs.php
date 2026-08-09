<?php
$page_title = 'Invite Freelancer';
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../auth/auth.php';
require_once __DIR__ . '/../shared/notification_helper.php';
require_role('client');

$clientId = $_SESSION['user_id'];

// ── Fetch client info ────────────────────────────────────────────────────
$stmt = $conn->prepare('SELECT name, profile_image FROM users WHERE id = ?');
$stmt->bind_param('i', $clientId);
$stmt->execute();
$clientUser = $stmt->get_result()->fetch_assoc();
$stmt->close();

// ── Handle form submission ───────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'send_invitation') {
    if (!verify_csrf_token()) {
        set_flash('error', 'Invalid security token. Please try again.');
        redirect('/jobhub/client/invite_jobs.php');
    }

    $freelancerUserId = intval($_POST['freelancer_id'] ?? 0);
    $jobId = intval($_POST['job_id'] ?? 0);
    $message = trim($_POST['message'] ?? '');

    // Validation
    if ($freelancerUserId <= 0) {
        set_flash('error', 'Please select a freelancer.');
        redirect('/jobhub/client/invite_jobs.php');
    }
    if ($jobId <= 0) {
        set_flash('error', 'Please select a job.');
        redirect('/jobhub/client/invite_jobs.php');
    }
    if (strlen($message) < 10) {
        set_flash('error', 'Please enter a message (at least 10 characters).');
        redirect('/jobhub/client/invite_jobs.php?freelancer_id=' . $freelancerUserId);
    }

    // Verify job belongs to client and is open
    $jobCheck = $conn->prepare('SELECT id, title, status FROM jobs WHERE id = ? AND client_id = ?');
    $jobCheck->bind_param('ii', $jobId, $clientId);
    $jobCheck->execute();
    $jobRow = $jobCheck->get_result()->fetch_assoc();
    $jobCheck->close();

    if (!$jobRow) {
        set_flash('error', 'Job not found or you do not own it.');
        redirect('/jobhub/client/invite_jobs.php');
    }
    if ($jobRow['status'] !== 'open') {
        set_flash('error', 'This job is no longer open.');
        redirect('/jobhub/client/invite_jobs.php');
    }

    // Verify freelancer exists and is active
    $flCheck = $conn->prepare('SELECT u.id, u.name, u.status FROM users u JOIN freelancers f ON u.id = f.user_id WHERE u.id = ?');
    $flCheck->bind_param('i', $freelancerUserId);
    $flCheck->execute();
    $flRow = $flCheck->get_result()->fetch_assoc();
    $flCheck->close();

    if (!$flRow) {
        set_flash('error', 'Freelancer not found.');
        redirect('/jobhub/client/invite_jobs.php');
    }
    if ($flRow['status'] !== 'active') {
        set_flash('error', 'Cannot invite suspended or flagged users.');
        redirect('/jobhub/client/invite_jobs.php');
    }
    if ($freelancerUserId === $clientId) {
        set_flash('error', 'You cannot invite yourself.');
        redirect('/jobhub/client/invite_jobs.php');
    }

    // Check for duplicate invitation (using notifications table)
    $dupCheck = $conn->prepare(
        "SELECT id FROM notifications WHERE user_id = ? AND type = 'job_invitation' AND link LIKE ? AND is_read = 0 LIMIT 1"
    );
    $linkPattern = "%job_id={$jobId}%";
    $dupCheck->bind_param('is', $freelancerUserId, $linkPattern);
    $dupCheck->execute();
    $existing = $dupCheck->get_result()->fetch_assoc();
    $dupCheck->close();

    if ($existing) {
        set_flash('error', 'You have already invited this freelancer to this job.');
        redirect('/jobhub/client/invite_jobs.php?freelancer_id=' . $freelancerUserId);
    }

    // Also check if freelancer already has a proposal for this job
    $propCheck = $conn->prepare('SELECT id FROM proposals WHERE job_id = ? AND freelancer_id = ?');
    $propCheck->bind_param('ii', $jobId, $freelancerUserId);
    $propCheck->execute();
    $hasProposal = $propCheck->get_result()->fetch_assoc();
    $propCheck->close();

    if ($hasProposal) {
        set_flash('error', 'This freelancer has already submitted a proposal for this job.');
        redirect('/jobhub/client/invite_jobs.php?freelancer_id=' . $freelancerUserId);
    }

    // Create invitation via notifications table
    $link = "/jobhub/freelancer/invitation_action.php?job_id={$jobId}&client_id={$clientId}";
    $notifService = getNotificationService();
    $notifId = $notifService->create(
        $freelancerUserId,
        'job_invitation',
        'New Job Invitation',
        $message,
        $link
    );

    if ($notifId) {
        set_flash('success', "Invitation sent to {$flRow['name']} successfully!");
    } else {
        set_flash('error', 'Failed to send invitation. Please try again.');
    }

    redirect('/jobhub/client/invite_jobs.php');
}

// ── Fetch freelancer profile if ID provided ──────────────────────────────
$freelancerId = intval($_GET['freelancer_id'] ?? 0);
$freelancer = null;
$freelancerSkills = [];

if ($freelancerId > 0) {
    $flStmt = $conn->prepare(
        "SELECT u.id AS user_id, u.name, u.email, u.profile_image, u.created_at,
                f.id AS freelancer_id, f.title, f.bio, f.hourly_rate, f.portfolio_url, f.social_links,
                f.years_of_experience, f.availability, f.completed_jobs, f.total_earnings
         FROM users u
         JOIN freelancers f ON u.id = f.user_id
         WHERE u.id = ? AND u.role = 'freelancer' AND u.status = 'active'"
    );
    $flStmt->bind_param('i', $freelancerId);
    $flStmt->execute();
    $freelancer = $flStmt->get_result()->fetch_assoc();
    $flStmt->close();

    // Parse social_links JSON (fallback to legacy portfolio_url)
    if ($freelancer) {
        $freelancer['social_links_parsed'] = ['website' => '', 'github' => '', 'linkedin' => ''];
        if (!empty($freelancer['social_links'])) {
            $decoded = json_decode($freelancer['social_links'], true);
            if (is_array($decoded)) {
                $freelancer['social_links_parsed']['website'] = $decoded['website'] ?? '';
                $freelancer['social_links_parsed']['github'] = $decoded['github'] ?? '';
                $freelancer['social_links_parsed']['linkedin'] = $decoded['linkedin'] ?? '';
            }
        } elseif (!empty($freelancer['portfolio_url'])) {
            $freelancer['social_links_parsed']['website'] = $freelancer['portfolio_url'];
        }
    }

    if ($freelancer) {
        // Fetch skills
        $skStmt = $conn->prepare(
            'SELECT s.skill_name, s.category
             FROM freelancer_skills fs
             JOIN skills s ON fs.skill_id = s.id
             WHERE fs.freelancer_id = ?
             ORDER BY s.category, s.skill_name'
        );
        $skStmt->bind_param('i', $freelancer['freelancer_id']);
        $skStmt->execute();
        $skRes = $skStmt->get_result();
        while ($sk = $skRes->fetch_assoc()) {
            $freelancerSkills[] = $sk;
        }
        $skStmt->close();

        // Fetch rating
        $ratStmt = $conn->prepare(
            'SELECT COALESCE(AVG(rating), 0) AS avg_rating, COUNT(*) AS review_count
             FROM reviews WHERE reviewee_id = ? AND COALESCE(is_hidden, 0) = 0'
        );
        $ratStmt->bind_param('i', $freelancerId);
        $ratStmt->execute();
        $ratData = $ratStmt->get_result()->fetch_assoc();
        $ratStmt->close();
        $freelancer['avg_rating'] = round($ratData['avg_rating'], 1);
        $freelancer['review_count'] = $ratData['review_count'];
    }
}

// ── Fetch client's open jobs ─────────────────────────────────────────────
$openJobs = [];
$jobsStmt = $conn->prepare(
    "SELECT j.id, j.title, j.budget, j.job_type, j.status, j.proposal_count, j.created_at, j.category,
            (SELECT GROUP_CONCAT(s.skill_name SEPARATOR ', ')
             FROM job_skills js JOIN skills s ON js.skill_id = s.id
             WHERE js.job_id = j.id) AS skills_list
     FROM jobs j
     WHERE j.client_id = ? AND j.status = 'open'
     ORDER BY j.created_at DESC"
);
$jobsStmt->bind_param('i', $clientId);
$jobsStmt->execute();
$jobsRes = $jobsStmt->get_result();
while ($jRow = $jobsRes->fetch_assoc()) {
    $openJobs[] = $jRow;
}
$jobsStmt->close();

// ── Search freelancers ───────────────────────────────────────────────────
$searchQuery = trim($_GET['search'] ?? '');
$filterSkill = $_GET['skill'] ?? '';
$filterAvailability = $_GET['availability'] ?? '';
$filterMinRate = max(0, floatval($_GET['min_rate'] ?? 0));
$filterMaxRate = max(0, floatval($_GET['max_rate'] ?? 0));
$filterExperience = $_GET['experience'] ?? '';
$filterMinRating = max(0, floatval($_GET['min_rating'] ?? 0));

$flWhere = ["u.role = 'freelancer'", "u.status = 'active'"];
$flParams = [];
$flTypes = '';

if ($searchQuery !== '') {
    $safeSearch = '%' . preg_replace('/[^\w\s]/', '', $searchQuery) . '%';
    $flWhere[] = '(u.name LIKE ? OR f.title LIKE ?)';
    $flParams[] = $safeSearch;
    $flParams[] = $safeSearch;
    $flTypes .= 'ss';
}

if ($filterAvailability !== '' && in_array($filterAvailability, ['Available', 'Busy', 'Unavailable'])) {
    $flWhere[] = 'f.availability = ?';
    $flParams[] = $filterAvailability;
    $flTypes .= 's';
}

if ($filterMinRate > 0) {
    $flWhere[] = 'f.hourly_rate >= ?';
    $flParams[] = $filterMinRate;
    $flTypes .= 'd';
}

if ($filterMaxRate > 0) {
    $flWhere[] = 'f.hourly_rate <= ?';
    $flParams[] = $filterMaxRate;
    $flTypes .= 'd';
}

if ($filterExperience !== '' && in_array($filterExperience, ['entry', 'intermediate', 'expert'])) {
    $expMap = ['entry' => [0, 2], 'intermediate' => [3, 5], 'expert' => [6, 99]];
    $expRange = $expMap[$filterExperience];
    $flWhere[] = 'f.years_of_experience BETWEEN ? AND ?';
    $flParams[] = $expRange[0];
    $flParams[] = $expRange[1];
    $flTypes .= 'ii';
}

if ($filterMinRating > 0) {
    $flWhere[] = 'u.id IN (SELECT reviewee_id FROM reviews WHERE COALESCE(is_hidden, 0) = 0 GROUP BY reviewee_id HAVING AVG(rating) >= ?)';
    $flParams[] = $filterMinRating;
    $flTypes .= 'd';
}

if ($filterSkill !== '') {
    $flWhere[] = 'u.id IN (SELECT fs.freelancer_id FROM freelancer_skills fs JOIN skills s ON fs.skill_id = s.id WHERE s.skill_name LIKE ?)';
    $flParams[] = '%' . $filterSkill . '%';
    $flTypes .= 's';
}

$flWhereSQL = implode(' AND ', $flWhere);

$flSql = "SELECT u.id AS user_id, u.name, u.profile_image,
                 f.title, f.hourly_rate, f.availability, f.years_of_experience, f.completed_jobs,
                 COALESCE((SELECT AVG(rating) FROM reviews WHERE reviewee_id = u.id AND COALESCE(is_hidden, 0) = 0), 0) AS avg_rating,
                 COALESCE((SELECT COUNT(*) FROM reviews WHERE reviewee_id = u.id AND COALESCE(is_hidden, 0) = 0), 0) AS review_count
          FROM users u
          JOIN freelancers f ON u.id = f.user_id
          WHERE $flWhereSQL
          ORDER BY avg_rating DESC, f.completed_jobs DESC
          LIMIT 20";

$flStmt = $conn->prepare($flSql);
if (!empty($flParams)) {
    $flStmt->bind_param($flTypes, ...$flParams);
}
$flStmt->execute();
$freelancers = [];
$flRes = $flStmt->get_result();
while ($fl = $flRes->fetch_assoc()) {
    $freelancers[] = $fl;
}
$flStmt->close();

// ── Fetch all skills for filter ──────────────────────────────────────────
$allSkills = [];
$skResult = $conn->query('SELECT DISTINCT skill_name FROM skills ORDER BY skill_name');
while ($sk = $skResult->fetch_assoc()) {
    $allSkills[] = $sk['skill_name'];
}

$currentPage = 'invite_jobs';
$user = ['name' => $clientUser['name'] ?? 'Client', 'profile_image' => $clientUser['profile_image'] ?? null];
$unreadCount = get_unread_message_count($clientId, 'client');
require_once __DIR__ . '/../includes/client_topbar.php';
$conn->close();
?>

<main class="min-h-screen ">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8 sm:py-12">

        <!-- Page Header -->
        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4 mb-8">
            <div>
                <nav class="flex items-center gap-1.5 text-xs text-gray-400 mb-3">
                    <a href="dashboard.php" class="hover:text-gray-600 transition-colors">Dashboard</a>
                    <i data-lucide="chevron-right" class="w-3 h-3"></i>
                    <span class="text-gray-900 font-medium">Invite Freelancer</span>
                </nav>
                <h1 class="text-2xl font-semibold text-gray-900 tracking-tight">Invite Freelancer</h1>
                <p class="text-sm text-gray-500 mt-1">Send a direct job invitation. Select a freelancer and choose from your open jobs.</p>
            </div>
        </div>

        <?php display_flash('success'); ?>
        <?php display_flash('error'); ?>

        <?php if (empty($openJobs)): ?>
            <!-- No Open Jobs -->
            <div class="bg-white rounded-xl border border-gray-200 p-12 text-center">
                <div class="w-12 h-12 rounded-xl bg-amber-50 flex items-center justify-center mx-auto mb-4">
                    <i data-lucide="triangle-alert" class="w-6 h-6 text-amber-500"></i>
                </div>
                <h3 class="text-base font-semibold text-gray-900 mb-1">No Open Jobs</h3>
                <p class="text-sm text-gray-500 mb-5">You need at least one open job to send invitations.</p>
                <a href="post_job.php" class="inline-flex items-center gap-2 px-5 py-2.5 bg-indigo-600 hover:bg-indigo-700 text-white text-sm font-medium rounded-lg transition-colors">
                    <i data-lucide="plus" class="w-4 h-4"></i> Post a Job
                </a>
            </div>
        <?php else: ?>

            <!-- ═══ FILTER BAR ════════════════════════════════════════ -->
            <div class="bg-white rounded-xl border border-gray-200 p-4 mb-6">
                <form method="GET" class="space-y-3">
                    <input type="hidden" name="freelancer_id" value="<?= $freelancerId ?>">

                    <!-- Search Row -->
                    <div class="flex gap-3">
                        <div class="relative flex-1">
                            <i data-lucide="search" class="w-4 h-4 absolute left-3 top-1/2 -translate-y-1/2 text-gray-400"></i>
                            <input type="text" name="search" value="<?= sanitize_string($searchQuery) ?>"
                                placeholder="Search by name or title..."
                                class="w-full bg-gray-50 border border-gray-200 rounded-lg pl-10 pr-4 py-2.5 text-sm text-gray-900 placeholder-gray-400 focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 outline-none transition-all">
                        </div>
                        <button type="submit" class="px-5 py-2.5 bg-indigo-600 hover:bg-indigo-700 text-white text-sm font-medium rounded-lg transition-colors shrink-0">
                            Search
                        </button>
                    </div>

                    <!-- Filter Pills -->
                    <div class="flex flex-wrap items-center gap-2">
                        <select name="skill" class="bg-gray-50 border border-gray-200 rounded-lg px-3 py-2 text-xs text-gray-600 focus:ring-2 focus:ring-indigo-500 outline-none cursor-pointer">
                            <option value="">All Skills</option>
                            <?php foreach ($allSkills as $sk): ?>
                                <option value="<?= sanitize_string($sk) ?>" <?= $filterSkill === $sk ? 'selected' : '' ?>><?= sanitize_string($sk) ?></option>
                            <?php endforeach; ?>
                        </select>

                        <select name="availability" class="bg-gray-50 border border-gray-200 rounded-lg px-3 py-2 text-xs text-gray-600 focus:ring-2 focus:ring-indigo-500 outline-none cursor-pointer">
                            <option value="">Availability</option>
                            <option value="Available" <?= $filterAvailability === 'Available' ? 'selected' : '' ?>>Available</option>
                            <option value="Busy" <?= $filterAvailability === 'Busy' ? 'selected' : '' ?>>Busy</option>
                            <option value="Unavailable" <?= $filterAvailability === 'Unavailable' ? 'selected' : '' ?>>Unavailable</option>
                        </select>

                        <select name="experience" class="bg-gray-50 border border-gray-200 rounded-lg px-3 py-2 text-xs text-gray-600 focus:ring-2 focus:ring-indigo-500 outline-none cursor-pointer">
                            <option value="">Experience</option>
                            <option value="entry" <?= $filterExperience === 'entry' ? 'selected' : '' ?>>Entry (0-2 yrs)</option>
                            <option value="intermediate" <?= $filterExperience === 'intermediate' ? 'selected' : '' ?>>Intermediate (3-5 yrs)</option>
                            <option value="expert" <?= $filterExperience === 'expert' ? 'selected' : '' ?>>Expert (6+ yrs)</option>
                        </select>

                        <select name="min_rating" class="bg-gray-50 border border-gray-200 rounded-lg px-3 py-2 text-xs text-gray-600 focus:ring-2 focus:ring-indigo-500 outline-none cursor-pointer">
                            <option value="">Rating</option>
                            <option value="4" <?= $filterMinRating == 4 ? 'selected' : '' ?>>4+ Stars</option>
                            <option value="3" <?= $filterMinRating == 3 ? 'selected' : '' ?>>3+ Stars</option>
                            <option value="2" <?= $filterMinRating == 2 ? 'selected' : '' ?>>2+ Stars</option>
                        </select>

                        <div class="flex items-center gap-1.5">
                            <input type="number" name="min_rate" value="<?= $filterMinRate > 0 ? $filterMinRate : '' ?>" placeholder="Min $" min="0" step="5"
                                class="w-20 bg-gray-50 border border-gray-200 rounded-lg px-2.5 py-2 text-xs text-gray-600 focus:ring-2 focus:ring-indigo-500 outline-none">
                            <span class="text-gray-300 text-xs">—</span>
                            <input type="number" name="max_rate" value="<?= $filterMaxRate > 0 ? $filterMaxRate : '' ?>" placeholder="Max $" min="0" step="5"
                                class="w-20 bg-gray-50 border border-gray-200 rounded-lg px-2.5 py-2 text-xs text-gray-600 focus:ring-2 focus:ring-indigo-500 outline-none">
                        </div>
                    </div>
                </form>
            </div>

            <!-- ═══ SPLIT WORKSPACE ══════════════════════════════════ -->
            <div class="flex flex-col lg:flex-row gap-6">

                <!-- ─── LEFT PANE: Candidate List (35%) ──────────── -->
                <div class="w-full lg:w-[35%] shrink-0">
                    <div class="bg-white rounded-xl border border-gray-200 overflow-hidden">
                        <div class="px-5 py-4 border-b border-gray-100">
                            <div class="flex items-center justify-between">
                                <h3 class="text-sm font-semibold text-gray-900">Candidates</h3>
                                <span class="text-xs text-gray-400"><?= count($freelancers) ?> found</span>
                            </div>
                        </div>

                        <?php if (!empty($freelancers)): ?>
                            <div class="divide-y divide-gray-50 max-h-[640px] overflow-y-auto">
                                <?php
                                foreach ($freelancers as $fl):
                                    $isSelected = ($fl['user_id'] == $freelancerId);
                                    $availColor = $fl['availability'] === 'Available' ? 'bg-emerald-50 text-emerald-700' : ($fl['availability'] === 'Busy' ? 'bg-amber-50 text-amber-700' : 'bg-gray-100 text-gray-500');
                                    ?>
                                    <a href="?freelancer_id=<?= $fl['user_id'] ?><?= $searchQuery ? '&search=' . urlencode($searchQuery) : '' ?>"
                                        class="flex items-center gap-3 px-5 py-4 transition-all <?= $isSelected ? 'bg-indigo-50/50 border-l-4 border-indigo-600' : 'border-l-4 border-transparent hover:bg-gray-50' ?>">
                                        <img src="<?= get_profile_image($fl['profile_image']) ?>" class="w-10 h-10 rounded-full object-cover ring-2 ring-gray-100 flex-shrink-0">
                                        <div class="flex-1 min-w-0">
                                            <p class="text-sm font-medium text-gray-900 truncate"><?= sanitize_string($fl['name']) ?></p>
                                            <p class="text-xs text-gray-500 truncate"><?= sanitize_string($fl['title'] ?? 'Freelancer') ?></p>
                                            <div class="flex items-center gap-2 mt-1">
                                                <span class="text-xs font-semibold text-indigo-600">$<?= number_format($fl['hourly_rate'], 0) ?>/hr</span>
                                                <span class="text-[10px] <?= $availColor ?> px-1.5 py-0.5 rounded-full font-medium"><?= $fl['availability'] ?></span>
                                                <?php if ($fl['avg_rating'] > 0): ?>
                                                    <span class="text-xs text-amber-500 flex items-center gap-0.5"><i data-lucide="star" class="w-3 h-3 fill-amber-400"></i><?= number_format($fl['avg_rating'], 1) ?></span>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                        <?php if ($isSelected): ?>
                                            <i data-lucide="check-circle-2" class="w-4 h-4 text-indigo-600 shrink-0"></i>
                                        <?php endif; ?>
                                    </a>
                                <?php endforeach; ?>
                            </div>
                        <?php else: ?>
                            <div class="p-10 text-center">
                                <i data-lucide="users" class="w-8 h-8 text-gray-300 mx-auto mb-3"></i>
                                <p class="text-sm text-gray-500">No freelancers match your filters.</p>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- ─── RIGHT PANE: Detail & Invitation (65%) ────── -->
                <div class="flex-1 min-w-0 space-y-6">

                    <?php if ($freelancer): ?>

                        <!-- Freelancer Profile Header -->
                        <div class="bg-white rounded-xl border border-gray-200 overflow-hidden">
                            <div class="bg-gradient-to-r from-indigo-600 to-indigo-500 px-6 py-5">
                                <div class="flex items-center gap-4">
                                    <img src="<?= get_profile_image($freelancer['profile_image']) ?>" class="w-16 h-16 rounded-full object-cover ring-4 ring-white/20 shadow-lg">
                                    <div class="flex-1 min-w-0">
                                        <h2 class="text-lg font-semibold text-white"><?= sanitize_string($freelancer['name']) ?></h2>
                                        <p class="text-indigo-200 text-sm"><?= sanitize_string($freelancer['title'] ?? 'Freelancer') ?></p>
                                    </div>
                                    <span class="inline-flex items-center gap-1.5 px-3 py-1 rounded-full text-xs font-medium <?= $freelancer['availability'] === 'Available' ? 'bg-emerald-100 text-emerald-700' : 'bg-amber-100 text-amber-700' ?>">
                                        <span class="w-1.5 h-1.5 rounded-full <?= $freelancer['availability'] === 'Available' ? 'bg-emerald-500' : 'bg-amber-500' ?>"></span>
                                        <?= $freelancer['availability'] ?>
                                    </span>
                                </div>
                            </div>

                            <div class="px-6 py-5">
                                <!-- Stats Row -->
                                <div class="grid grid-cols-2 sm:grid-cols-4 gap-4 mb-5">
                                    <div class="text-center p-3 bg-gray-50 rounded-lg">
                                        <p class="text-lg font-bold text-gray-900">$<?= number_format($freelancer['hourly_rate'], 0) ?></p>
                                        <p class="text-[10px] text-gray-500 font-medium uppercase tracking-wider">Hourly Rate</p>
                                    </div>
                                    <div class="text-center p-3 bg-gray-50 rounded-lg">
                                        <div class="flex items-center justify-center gap-1">
                                            <p class="text-lg font-bold text-gray-900"><?= $freelancer['avg_rating'] > 0 ? number_format($freelancer['avg_rating'], 1) : '—' ?></p>
                                            <?php if ($freelancer['avg_rating'] > 0): ?><i data-lucide="star" class="w-4 h-4 text-amber-400 fill-amber-400"></i><?php endif; ?>
                                        </div>
                                        <p class="text-[10px] text-gray-500 font-medium uppercase tracking-wider"><?= $freelancer['review_count'] ?> reviews</p>
                                    </div>
                                    <div class="text-center p-3 bg-gray-50 rounded-lg">
                                        <p class="text-lg font-bold text-gray-900"><?= $freelancer['completed_jobs'] ?? 0 ?></p>
                                        <p class="text-[10px] text-gray-500 font-medium uppercase tracking-wider">Completed</p>
                                    </div>
                                    <div class="text-center p-3 bg-gray-50 rounded-lg">
                                        <p class="text-lg font-bold text-gray-900"><?= $freelancer['years_of_experience'] ?> yr</p>
                                        <p class="text-[10px] text-gray-500 font-medium uppercase tracking-wider">Experience</p>
                                    </div>
                                </div>

                                <!-- Top Skills -->
                                <?php if (!empty($freelancerSkills)): ?>
                                    <div class="mb-5">
                                        <p class="text-[10px] font-semibold uppercase tracking-wider text-gray-400 mb-2.5">Top Skills</p>
                                        <div class="flex flex-wrap gap-1.5">
                                            <?php foreach (array_slice($freelancerSkills, 0, 8) as $sk): ?>
                                                <span class="inline-flex items-center px-2.5 py-1 bg-indigo-50 text-indigo-700 rounded-md text-xs font-medium"><?= sanitize_string($sk['skill_name']) ?></span>
                                            <?php endforeach; ?>
                                        </div>
                                    </div>
                                <?php endif; ?>

                                <!-- Actions -->
                                <div class="flex items-center gap-3">
                                    <?php if (!empty($freelancer['social_links_parsed']['website'])): ?>
                                        <a href="<?= sanitize_string($freelancer['social_links_parsed']['website']) ?>" target="_blank" class="inline-flex items-center gap-1.5 text-xs font-medium text-gray-500 hover:text-indigo-600 transition-colors">
                                            <i data-lucide="external-link" class="w-3.5 h-3.5"></i> Portfolio
                                        </a>
                                    <?php endif; ?>
                                    <a href="/jobhub/freelancer/profile.php?id=<?= $freelancer['user_id'] ?>" target="_blank" class="inline-flex items-center gap-1.5 text-xs font-medium text-gray-500 hover:text-indigo-600 transition-colors">
                                        <i data-lucide="user" class="w-3.5 h-3.5"></i> View Full Profile
                                    </a>
                                </div>
                            </div>
                        </div>

                        <!-- Invitation Form -->
                        <form method="POST" action="/jobhub/client/invite_jobs.php" class="bg-white rounded-xl border border-gray-200 p-6 space-y-5">
                            <?= csrf_field() ?>
                            <input type="hidden" name="action" value="send_invitation">
                            <input type="hidden" name="freelancer_id" value="<?= $freelancer['user_id'] ?>">

                            <div class="flex items-center gap-2.5 mb-1">
                                <div class="w-8 h-8 rounded-lg bg-indigo-50 flex items-center justify-center">
                                    <i data-lucide="send" class="w-4 h-4 text-indigo-600"></i>
                                </div>
                                <h3 class="text-sm font-semibold text-gray-900">Send Invitation</h3>
                            </div>

                            <!-- Job Selection -->
                            <div>
                                <label class="block text-xs font-semibold text-gray-600 mb-2.5">Select a Job <span class="text-red-500">*</span></label>
                                <div class="space-y-2 max-h-60 overflow-y-auto pr-1">
                                    <?php
                                    foreach ($openJobs as $job):
                                        $jobTypeLabel = $job['job_type'] === 'hourly' ? 'Hourly' : 'Fixed';
                                        $jobTypeColor = $job['job_type'] === 'hourly' ? 'bg-violet-50 text-violet-700' : 'bg-cyan-50 text-cyan-700';
                                        ?>
                                        <label class="flex items-start gap-3 p-3.5 rounded-lg border border-gray-200 hover:border-indigo-300 hover:bg-indigo-50/30 cursor-pointer transition-all group">
                                            <input type="radio" name="job_id" value="<?= $job['id'] ?>" required
                                                class="mt-0.5 w-4 h-4 text-indigo-600 border-gray-300 focus:ring-indigo-500">
                                            <div class="flex-1 min-w-0">
                                                <p class="text-sm font-medium text-gray-900 truncate group-hover:text-indigo-700 transition-colors"><?= decode_over_encoded($job['title']) ?></p>
                                                <div class="flex items-center gap-2 mt-1">
                                                    <span class="text-xs font-semibold text-gray-900">$<?= number_format($job['budget'], 0) ?></span>
                                                    <span class="inline-flex items-center px-1.5 py-0.5 rounded text-[10px] font-semibold <?= $jobTypeColor ?>"><?= $jobTypeLabel ?></span>
                                                    <span class="text-xs text-gray-400"><?= $job['proposal_count'] ?> proposals</span>
                                                </div>
                                                <?php if (!empty($job['skills_list'])): ?>
                                                    <p class="text-[11px] text-gray-400 mt-1.5 truncate"><?= sanitize_string($job['skills_list']) ?></p>
                                                <?php endif; ?>
                                            </div>
                                        </label>
                                    <?php endforeach; ?>
                                </div>
                            </div>

                            <!-- Message -->
                            <div>
                                <label class="block text-xs font-semibold text-gray-600 mb-2">Message <span class="text-red-500">*</span></label>
                                <textarea name="message" rows="5" required
                                    placeholder="Hello,&#10;&#10;I reviewed your profile and think you are a great fit for this project. I'd like to invite you to submit a proposal.&#10;&#10;Looking forward to working with you!"
                                    class="w-full bg-gray-50 border border-gray-200 rounded-lg px-4 py-3 placeholder:text-xs text-gray-900 placeholder-gray-400 focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 outline-none resize-none transition-all"></textarea>
                                <p class="text-[10px] text-gray-400 mt-1.5">Minimum 10 characters</p>
                            </div>

                            <!-- Submit -->
                            <button type="submit" class="w-full py-3 bg-indigo-600 hover:bg-indigo-700 text-white text-sm font-medium rounded-lg transition-all flex items-center justify-center gap-2 shadow-sm shadow-indigo-200 hover:shadow-md hover:shadow-indigo-300">
                                <i data-lucide="send" class="w-4 h-4"></i> Send Invitation
                            </button>
                        </form>

                    <?php else: ?>

                        <!-- No Freelancer Selected -->
                        <div class="bg-white rounded-xl border border-gray-200 p-16 text-center">
                            <div class="w-14 h-14 rounded-xl bg-indigo-50 flex items-center justify-center mx-auto mb-4">
                                <i data-lucide="user-plus" class="w-7 h-7 text-indigo-400"></i>
                            </div>
                            <h3 class="text-base font-semibold text-gray-900 mb-1">Select a Freelancer</h3>
                            <p class="text-sm text-gray-500">Choose a candidate from the list to view their profile and send an invitation.</p>
                        </div>

                    <?php endif; ?>

                </div>
            </div>

        <?php endif; ?>

    </div>
</main>

<?php require_once __DIR__ . '/../includes/client_footer.php'; ?>
