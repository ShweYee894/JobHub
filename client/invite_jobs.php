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
        redirect('/finalproject/client/invite_jobs.php');
    }

    $freelancerUserId = intval($_POST['freelancer_id'] ?? 0);
    $jobId = intval($_POST['job_id'] ?? 0);
    $message = trim($_POST['message'] ?? '');

    // Validation
    if ($freelancerUserId <= 0) {
        set_flash('error', 'Please select a freelancer.');
        redirect('/finalproject/client/invite_jobs.php');
    }
    if ($jobId <= 0) {
        set_flash('error', 'Please select a job.');
        redirect('/finalproject/client/invite_jobs.php');
    }
    if (strlen($message) < 10) {
        set_flash('error', 'Please enter a message (at least 10 characters).');
        redirect('/finalproject/client/invite_jobs.php?freelancer_id=' . $freelancerUserId);
    }

    // Verify job belongs to client and is open
    $jobCheck = $conn->prepare('SELECT id, title, status FROM jobs WHERE id = ? AND client_id = ?');
    $jobCheck->bind_param('ii', $jobId, $clientId);
    $jobCheck->execute();
    $jobRow = $jobCheck->get_result()->fetch_assoc();
    $jobCheck->close();

    if (!$jobRow) {
        set_flash('error', 'Job not found or you do not own it.');
        redirect('/finalproject/client/invite_jobs.php');
    }
    if ($jobRow['status'] !== 'open') {
        set_flash('error', 'This job is no longer open.');
        redirect('/finalproject/client/invite_jobs.php');
    }

    // Verify freelancer exists and is active
    $flCheck = $conn->prepare('SELECT u.id, u.name, u.status FROM users u JOIN freelancers f ON u.id = f.user_id WHERE u.id = ?');
    $flCheck->bind_param('i', $freelancerUserId);
    $flCheck->execute();
    $flRow = $flCheck->get_result()->fetch_assoc();
    $flCheck->close();

    if (!$flRow) {
        set_flash('error', 'Freelancer not found.');
        redirect('/finalproject/client/invite_jobs.php');
    }
    if ($flRow['status'] !== 'active') {
        set_flash('error', 'Cannot invite suspended or flagged users.');
        redirect('/finalproject/client/invite_jobs.php');
    }
    if ($freelancerUserId === $clientId) {
        set_flash('error', 'You cannot invite yourself.');
        redirect('/finalproject/client/invite_jobs.php');
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
        redirect('/finalproject/client/invite_jobs.php?freelancer_id=' . $freelancerUserId);
    }

    // Also check if freelancer already has a proposal for this job
    $propCheck = $conn->prepare('SELECT id FROM proposals WHERE job_id = ? AND freelancer_id = ?');
    $propCheck->bind_param('ii', $jobId, $freelancerUserId);
    $propCheck->execute();
    $hasProposal = $propCheck->get_result()->fetch_assoc();
    $propCheck->close();

    if ($hasProposal) {
        set_flash('error', 'This freelancer has already submitted a proposal for this job.');
        redirect('/finalproject/client/invite_jobs.php?freelancer_id=' . $freelancerUserId);
    }

    // Create invitation via notifications table
    $link = "/finalproject/freelancer/invitation_action.php?job_id={$jobId}&client_id={$clientId}";
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

    redirect('/finalproject/client/invite_jobs.php');
}

// ── Fetch freelancer profile if ID provided ──────────────────────────────
$freelancerId = intval($_GET['freelancer_id'] ?? 0);
$freelancer = null;
$freelancerSkills = [];

if ($freelancerId > 0) {
    $flStmt = $conn->prepare(
        "SELECT u.id AS user_id, u.name, u.email, u.profile_image, u.created_at,
                f.id AS freelancer_id, f.title, f.bio, f.hourly_rate, f.portfolio_url,
                f.years_of_experience, f.availability, f.completed_jobs, f.total_earnings
         FROM users u
         JOIN freelancers f ON u.id = f.user_id
         WHERE u.id = ? AND u.role = 'freelancer' AND u.status = 'active'"
    );
    $flStmt->bind_param('i', $freelancerId);
    $flStmt->execute();
    $freelancer = $flStmt->get_result()->fetch_assoc();
    $flStmt->close();

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
             FROM reviews WHERE reviewee_id = ?'
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
    $flWhere[] = 'u.id IN (SELECT reviewee_id FROM reviews GROUP BY reviewee_id HAVING AVG(rating) >= ?)';
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
                 COALESCE((SELECT AVG(rating) FROM reviews WHERE reviewee_id = u.id), 0) AS avg_rating,
                 COALESCE((SELECT COUNT(*) FROM reviews WHERE reviewee_id = u.id), 0) AS review_count
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

<main class="max-w-7xl mx-auto px-4 sm:px-6 py-8">

    <!-- Breadcrumb -->
    <nav class="flex items-center gap-2 text-sm text-gray-400 mb-6">
        <a href="dashboard.php" class="hover:text-gray-600 transition-colors">Dashboard</a>
        <i class="fas fa-chevron-right text-[10px]"></i>
        <span class="text-gray-900 font-semibold">Invite Freelancer</span>
    </nav>

    <?php display_flash('success'); ?>
    <?php display_flash('error'); ?>

    <!-- Page Header -->
    <div class="mb-8">
        <h1 class="text-2xl font-extrabold text-gray-900">Invite <span class="text-blue-600">Freelancer</span></h1>
        <p class="text-sm text-gray-500 mt-1">Send a direct job invitation to a freelancer. Select one of your open jobs and send a personalized message.</p>
    </div>

    <?php if (empty($openJobs)): ?>
        <!-- No Open Jobs -->
        <div class="bg-white rounded-2xl p-12 border border-gray-100 shadow-sm text-center">
            <div class="w-16 h-16 rounded-2xl bg-amber-50 flex items-center justify-center mx-auto mb-4">
                <i class="fas fa-exclamation-triangle text-2xl text-amber-400"></i>
            </div>
            <h3 class="text-lg font-bold text-gray-900 mb-2">No Open Jobs</h3>
            <p class="text-sm text-gray-400 mb-5">You need at least one open job to send invitations.</p>
            <a href="post_job.php" class="inline-flex items-center gap-2 px-6 py-3 bg-blue-600 hover:bg-blue-700 text-white text-sm font-semibold rounded-xl transition-colors">
                <i class="fas fa-plus text-xs"></i> Post a Job
            </a>
        </div>
    <?php else: ?>
        <div class="flex flex-col lg:flex-row gap-6">

            <!-- ═══ LEFT COLUMN: Freelancer Search + Selection ═══════════ -->
            <div class="flex-1 min-w-0 space-y-6">

                <!-- Search & Filters -->
                <div class="bg-white rounded-2xl p-5 border border-gray-100 shadow-sm">
                    <form method="GET" class="space-y-4">
                        <input type="hidden" name="freelancer_id" value="<?= $freelancerId ?>">

                        <!-- Search Bar -->
                        <div class="flex gap-3">
                            <div class="relative flex-1">
                                <i class="fas fa-search absolute left-3 top-1/2 -translate-y-1/2 text-gray-400 text-sm"></i>
                                <input type="text" name="search" value="<?= sanitize_string($searchQuery) ?>"
                                    placeholder="Search freelancers by name or title..."
                                    class="w-full bg-gray-50 border border-gray-200 rounded-xl pl-10 pr-4 py-2.5 text-sm text-gray-900 placeholder-gray-400 focus:ring-2 focus:ring-blue-500 focus:border-blue-500 outline-none transition-all">
                            </div>
                            <button type="submit" class="px-5 py-2.5 bg-blue-600 hover:bg-blue-700 text-white text-sm font-semibold rounded-xl transition-colors">
                                <i class="fas fa-search text-xs"></i>
                            </button>
                        </div>

                        <!-- Filters Row -->
                        <div class="flex flex-wrap gap-3">
                            <select name="skill" class="bg-gray-50 border border-gray-200 rounded-lg px-3 py-2 text-xs text-gray-700 focus:ring-2 focus:ring-blue-500 outline-none">
                                <option value="">All Skills</option>
                                <?php foreach ($allSkills as $sk): ?>
                                    <option value="<?= sanitize_string($sk) ?>" <?= $filterSkill === $sk ? 'selected' : '' ?>><?= sanitize_string($sk) ?></option>
                                <?php endforeach; ?>
                            </select>

                            <select name="availability" class="bg-gray-50 border border-gray-200 rounded-lg px-3 py-2 text-xs text-gray-700 focus:ring-2 focus:ring-blue-500 outline-none">
                                <option value="">Any Availability</option>
                                <option value="Available" <?= $filterAvailability === 'Available' ? 'selected' : '' ?>>Available</option>
                                <option value="Busy" <?= $filterAvailability === 'Busy' ? 'selected' : '' ?>>Busy</option>
                                <option value="Unavailable" <?= $filterAvailability === 'Unavailable' ? 'selected' : '' ?>>Unavailable</option>
                            </select>

                            <select name="experience" class="bg-gray-50 border border-gray-200 rounded-lg px-3 py-2 text-xs text-gray-700 focus:ring-2 focus:ring-blue-500 outline-none">
                                <option value="">Any Experience</option>
                                <option value="entry" <?= $filterExperience === 'entry' ? 'selected' : '' ?>>Entry (0-2 yrs)</option>
                                <option value="intermediate" <?= $filterExperience === 'intermediate' ? 'selected' : '' ?>>Intermediate (3-5 yrs)</option>
                                <option value="expert" <?= $filterExperience === 'expert' ? 'selected' : '' ?>>Expert (6+ yrs)</option>
                            </select>

                            <select name="min_rating" class="bg-gray-50 border border-gray-200 rounded-lg px-3 py-2 text-xs text-gray-700 focus:ring-2 focus:ring-blue-500 outline-none">
                                <option value="">Any Rating</option>
                                <option value="4" <?= $filterMinRating == 4 ? 'selected' : '' ?>>4+ Stars</option>
                                <option value="3" <?= $filterMinRating == 3 ? 'selected' : '' ?>>3+ Stars</option>
                                <option value="2" <?= $filterMinRating == 2 ? 'selected' : '' ?>>2+ Stars</option>
                            </select>

                            <div class="flex items-center gap-1">
                                <input type="number" name="min_rate" value="<?= $filterMinRate > 0 ? $filterMinRate : '' ?>" placeholder="Min $" min="0" step="5"
                                    class="w-20 bg-gray-50 border border-gray-200 rounded-lg px-2 py-2 text-xs text-gray-700 focus:ring-2 focus:ring-blue-500 outline-none">
                                <span class="text-gray-300">-</span>
                                <input type="number" name="max_rate" value="<?= $filterMaxRate > 0 ? $filterMaxRate : '' ?>" placeholder="Max $" min="0" step="5"
                                    class="w-20 bg-gray-50 border border-gray-200 rounded-lg px-2 py-2 text-xs text-gray-700 focus:ring-2 focus:ring-blue-500 outline-none">
                            </div>
                        </div>
                    </form>
                </div>

                <!-- Freelancer List -->
                <div class="bg-white rounded-2xl border border-gray-100 shadow-sm">
                    <div class="p-5 border-b border-gray-100">
                        <h3 class="text-sm font-bold text-gray-900">
                            <i class="fas fa-users text-blue-500 mr-2"></i>Select Freelancer
                            <span class="text-gray-400 font-normal ml-2">(<?= count($freelancers) ?> found)</span>
                        </h3>
                    </div>

                    <?php if (!empty($freelancers)): ?>
                        <div class="divide-y divide-gray-50 max-h-[600px] overflow-y-auto">
                            <?php
                            foreach ($freelancers as $fl):
                                $isSelected = ($fl['user_id'] == $freelancerId);
                                $availColor = $fl['availability'] === 'Available' ? 'text-emerald-600 bg-emerald-50' : ($fl['availability'] === 'Busy' ? 'text-amber-600 bg-amber-50' : 'text-gray-500 bg-gray-100');
                                ?>
                                <a href="?freelancer_id=<?= $fl['user_id'] ?><?= $searchQuery ? '&search=' . urlencode($searchQuery) : '' ?>"
                                    class="flex items-center gap-4 p-4 hover:bg-blue-50/50 transition-all <?= $isSelected ? 'bg-blue-50 border-l-4 border-blue-500' : '' ?>">
                                    <img src="<?= get_profile_image($fl['profile_image']) ?>" class="w-12 h-12 rounded-full object-cover border-2 border-gray-100 flex-shrink-0">
                                    <div class="flex-1 min-w-0">
                                        <p class="font-bold text-gray-900 text-sm"><?= sanitize_string($fl['name']) ?></p>
                                        <p class="text-xs text-gray-500 truncate"><?= sanitize_string($fl['title'] ?? 'Freelancer') ?></p>
                                        <div class="flex items-center gap-3 mt-1">
                                            <span class="text-xs font-semibold text-blue-600">$<?= number_format($fl['hourly_rate'], 0) ?>/hr</span>
                                            <span class="text-xs <?= $availColor ?> px-2 py-0.5 rounded-full font-medium"><?= $fl['availability'] ?></span>
                                            <?php if ($fl['avg_rating'] > 0): ?>
                                                <span class="text-xs text-amber-500"><i class="fas fa-star text-[10px]"></i> <?= number_format($fl['avg_rating'], 1) ?></span>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                    <?php if ($isSelected): ?>
                                        <i class="fas fa-check-circle text-blue-500 text-lg"></i>
                                    <?php endif; ?>
                                </a>
                            <?php endforeach; ?>
                        </div>
                    <?php else: ?>
                        <div class="p-8 text-center">
                            <i class="fas fa-user-slash text-3xl text-gray-300 mb-3"></i>
                            <p class="text-sm text-gray-400">No freelancers found matching your criteria.</p>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- ═══ RIGHT COLUMN: Selected Freelancer + Job Selection ═══ -->
            <div class="w-full lg:w-96 space-y-6">

                <?php if ($freelancer): ?>
                    <!-- Freelancer Profile Card -->
                    <div class="bg-white rounded-2xl border border-gray-100 shadow-sm overflow-hidden">
                        <div class="bg-gradient-to-r from-blue-600 to-cyan-500 p-6 text-center">
                            <img src="<?= get_profile_image($freelancer['profile_image']) ?>" class="w-20 h-20 rounded-full object-cover border-4 border-white mx-auto mb-3 shadow-lg">
                            <h3 class="text-lg font-bold text-white"><?= sanitize_string($freelancer['name']) ?></h3>
                            <p class="text-blue-100 text-sm"><?= sanitize_string($freelancer['title'] ?? 'Freelancer') ?></p>
                        </div>
                        <div class="p-5 space-y-4">
                            <div class="grid grid-cols-2 gap-3">
                                <div class="bg-gray-50 rounded-xl p-3 text-center">
                                    <p class="text-lg font-black text-gray-900">$<?= number_format($freelancer['hourly_rate'], 0) ?></p>
                                    <p class="text-[10px] text-gray-400 font-medium">Hourly Rate</p>
                                </div>
                                <div class="bg-gray-50 rounded-xl p-3 text-center">
                                    <p class="text-lg font-black text-gray-900"><?= $freelancer['avg_rating'] > 0 ? number_format($freelancer['avg_rating'], 1) : '—' ?></p>
                                    <p class="text-[10px] text-gray-400 font-medium">Rating (<?= $freelancer['review_count'] ?>)</p>
                                </div>
                                <div class="bg-gray-50 rounded-xl p-3 text-center">
                                    <p class="text-lg font-black text-gray-900"><?= $freelancer['completed_jobs'] ?? 0 ?></p>
                                    <p class="text-[10px] text-gray-400 font-medium">Completed</p>
                                </div>
                                <div class="bg-gray-50 rounded-xl p-3 text-center">
                                    <p class="text-lg font-black text-gray-900"><?= $freelancer['years_of_experience'] ?>yr</p>
                                    <p class="text-[10px] text-gray-400 font-medium">Experience</p>
                                </div>
                            </div>

                            <div class="flex items-center gap-2">
                                <span class="text-xs text-gray-400">Availability:</span>
                                <span class="text-xs font-semibold <?= $freelancer['availability'] === 'Available' ? 'text-emerald-600' : 'text-amber-600' ?>">
                                    <?= $freelancer['availability'] ?>
                                </span>
                            </div>

                            <?php if (!empty($freelancerSkills)): ?>
                                <div>
                                    <p class="text-[10px] font-bold uppercase tracking-wider text-gray-400 mb-2">Top Skills</p>
                                    <div class="flex flex-wrap gap-1.5">
                                        <?php foreach (array_slice($freelancerSkills, 0, 6) as $sk): ?>
                                            <span class="px-2 py-0.5 bg-blue-50 text-blue-600 rounded text-[10px] font-semibold"><?= sanitize_string($sk['skill_name']) ?></span>
                                        <?php endforeach; ?>
                                    </div>
                                </div>
                            <?php endif; ?>

                            <?php if (!empty($freelancer['portfolio_url'])): ?>
                                <a href="<?= sanitize_string($freelancer['portfolio_url']) ?>" target="_blank" class="flex items-center gap-2 text-xs text-blue-600 hover:text-blue-700 font-medium">
                                    <i class="fas fa-external-link-alt text-[10px]"></i> View Portfolio
                                </a>
                            <?php endif; ?>

                            <a href="/finalproject/freelancer/profile.php?id=<?= $freelancer['user_id'] ?>" target="_blank" class="block text-center py-2 border border-gray-200 rounded-xl text-xs font-semibold text-gray-600 hover:border-blue-300 hover:text-blue-600 transition-all">
                                <i class="fas fa-user mr-1"></i> View Full Profile
                            </a>
                        </div>
                    </div>

                    <!-- Invitation Form -->
                    <form method="POST" action="/finalproject/client/invite_jobs.php" class="bg-white rounded-2xl border border-gray-100 shadow-sm p-5 space-y-4">
                        <?= csrf_field() ?>
                        <input type="hidden" name="action" value="send_invitation">
                        <input type="hidden" name="freelancer_id" value="<?= $freelancer['user_id'] ?>">

                        <h3 class="text-sm font-bold text-gray-900">
                            <i class="fas fa-paper-plane text-blue-500 mr-2"></i>Send Invitation
                        </h3>

                        <!-- Job Selection -->
                        <div>
                            <label class="block text-xs font-semibold text-gray-700 mb-2">Select a Job <span class="text-red-500">*</span></label>
                            <div class="space-y-2 max-h-60 overflow-y-auto">
                                <?php
                                foreach ($openJobs as $job):
                                    $jobTypeLabel = $job['job_type'] === 'hourly' ? 'Hourly' : 'Fixed';
                                    $jobTypeColor = $job['job_type'] === 'hourly' ? 'text-purple-600' : 'text-cyan-600';
                                    ?>
                                    <label class="flex items-start gap-3 p-3 rounded-xl border border-gray-200 hover:border-blue-300 hover:bg-blue-50/50 cursor-pointer transition-all">
                                        <input type="radio" name="job_id" value="<?= $job['id'] ?>" required
                                            class="mt-0.5 w-4 h-4 text-blue-600 border-gray-300 focus:ring-blue-500">
                                        <div class="flex-1 min-w-0">
                                            <p class="text-sm font-bold text-gray-900 truncate"><?= sanitize_string($job['title']) ?></p>
                                            <div class="flex items-center gap-2 mt-1">
                                                <span class="text-xs font-semibold text-gray-900">$<?= number_format($job['budget'], 0) ?></span>
                                                <span class="text-xs <?= $jobTypeColor ?> font-medium">· <?= $jobTypeLabel ?></span>
                                                <span class="text-xs text-gray-400">· <?= $job['proposal_count'] ?> proposals</span>
                                            </div>
                                            <?php if (!empty($job['skills_list'])): ?>
                                                <p class="text-[10px] text-gray-400 mt-1 truncate"><?= sanitize_string($job['skills_list']) ?></p>
                                            <?php endif; ?>
                                        </div>
                                    </label>
                                <?php endforeach; ?>
                            </div>
                        </div>

                        <!-- Message -->
                        <div>
                            <label class="block text-xs font-semibold text-gray-700 mb-1.5">Message <span class="text-red-500">*</span></label>
                            <textarea name="message" rows="5" required
                                placeholder="Hello,&#10;&#10;I reviewed your profile and think you are a great fit for this project. I'd like to invite you to submit a proposal.&#10;&#10;Looking forward to working with you!"
                                class="w-full bg-gray-50 border border-gray-200 rounded-xl px-4 py-2.5 text-sm text-gray-900 placeholder-gray-400 focus:ring-2 focus:ring-blue-500 focus:border-blue-500 outline-none resize-none transition-all"></textarea>
                            <p class="text-[10px] text-gray-400 mt-1">Minimum 10 characters</p>
                        </div>

                        <!-- Submit -->
                        <button type="submit" class="w-full py-3 bg-blue-600 hover:bg-blue-700 text-white text-sm font-semibold rounded-xl transition-all flex items-center justify-center gap-2 shadow-lg shadow-blue-500/25">
                            <i class="fas fa-paper-plane text-xs"></i> Send Invitation
                        </button>
                    </form>

                <?php else: ?>
                    <!-- No Freelancer Selected -->
                    <div class="bg-white rounded-2xl border border-gray-100 shadow-sm p-12 text-center">
                        <div class="w-16 h-16 rounded-2xl bg-blue-50 flex items-center justify-center mx-auto mb-4">
                            <i class="fas fa-user-plus text-2xl text-blue-300"></i>
                        </div>
                        <h3 class="text-lg font-bold text-gray-900 mb-2">Select a Freelancer</h3>
                        <p class="text-sm text-gray-400">Choose a freelancer from the list to view their profile and send an invitation.</p>
                    </div>
                <?php endif; ?>

            </div>
        </div>
    <?php endif; ?>
</main>

<?php require_once __DIR__ . '/../includes/client_footer.php'; ?>
