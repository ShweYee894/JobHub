<?php
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../auth/auth.php';
require_role('freelancer');

$userId = $_SESSION['user_id'];
$jobId = intval($_GET['id'] ?? 0);

if ($jobId <= 0) {
    redirect('/jobhub/freelancer/browse_jobs.php');
}

$stmt = $conn->prepare('SELECT name, profile_image FROM users WHERE id = ?');
$stmt->bind_param('i', $userId);
$stmt->execute();
$user = $stmt->get_result()->fetch_assoc();
$stmt->close();

// ── Fetch job ─────────────────────────────────────────────────────────────
$stmt = $conn->prepare('
    SELECT j.id, j.title, j.description, j.budget, j.status, j.created_at, j.updated_at,
           j.deadline, j.job_type, j.experience_level, j.category,
           u.name AS client_name, u.created_at AS client_joined, u.id AS client_user_id,
           c.client_id AS client_fk, c.company_logo
    FROM jobs j
    JOIN clients c ON j.client_id = c.client_id
    JOIN users u ON c.client_id = u.id
    WHERE j.id = ?
');
$stmt->bind_param('i', $jobId);
$stmt->execute();
$job = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$job) {
    set_flash('error', 'Job not found.');
    redirect('/jobhub/freelancer/browse_jobs.php');
}

// ── Fetch job skills ──────────────────────────────────────────────────────
$stmt = $conn->prepare('
    SELECT s.id, s.skill_name, s.category
    FROM skills s
    JOIN job_skills js ON s.id = js.skill_id
    WHERE js.job_id = ?
    ORDER BY s.category, s.skill_name
');
$stmt->bind_param('i', $jobId);
$stmt->execute();
$jobSkills = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

// ── Check if freelancer already applied ───────────────────────────────────
$stmt = $conn->prepare('SELECT id, amount, created_at FROM proposals WHERE job_id = ? AND freelancer_id = ?');
$stmt->bind_param('ii', $jobId, $userId);
$stmt->execute();
$existingProposal = $stmt->get_result()->fetch_assoc();
$stmt->close();
$hasApplied = (bool) $existingProposal;

// ── Client stats ──────────────────────────────────────────────────────────
$clientId = $job['client_user_id'];

$stmt = $conn->prepare('SELECT COUNT(*) AS total FROM jobs WHERE client_id = ?');
$stmt->bind_param('i', $clientId);
$stmt->execute();
$clientJobsPosted = $stmt->get_result()->fetch_assoc()['total'];
$stmt->close();

$stmt = $conn->prepare("SELECT COALESCE(SUM(total_amount), 0) AS total FROM payments WHERE payer_id = ? AND status = 'completed'");
$stmt->bind_param('i', $clientId);
$stmt->execute();
$clientTotalSpent = $stmt->get_result()->fetch_assoc()['total'];
$stmt->close();

// ── Client profile image ──────────────────────────────────────────────────
$stmt = $conn->prepare('SELECT profile_image FROM users WHERE id = ?');
$stmt->bind_param('i', $clientId);
$stmt->execute();
$clientUser = $stmt->get_result()->fetch_assoc();
$clientImage = $clientUser['profile_image'] ?? null;
$stmt->close();

// ── Related jobs (same skills, exclude current, limit 3) ──────────────────
$relatedJobs = [];
if (!empty($jobSkills)) {
    $skillIds = array_column($jobSkills, 'id');
    $placeholders = implode(',', array_fill(0, count($skillIds), '?'));
    $types = str_repeat('i', count($skillIds)) . 'i';

    $relStmt = $conn->prepare("
        SELECT DISTINCT j2.id, j2.title, j2.budget, j2.status, j2.created_at,
               (SELECT COUNT(*) FROM job_skills js2 WHERE js2.job_id = j2.id AND js2.skill_id IN ($placeholders)) AS skill_match
        FROM jobs j2
        JOIN job_skills js ON j2.id = js.job_id
        WHERE js.skill_id IN ($placeholders)
          AND j2.id != ?
          AND j2.status = 'open'
        ORDER BY skill_match DESC, j2.created_at DESC
        LIMIT 3
    ");
    $relParams = array_merge($skillIds, $skillIds, [$jobId]);
    // Dynamically create the type string ('i' for each integer in the merged array)
    $types = str_repeat('i', count($relParams));
    $relStmt->bind_param($types, ...$relParams);
    $relStmt->execute();
    $relatedResult = $relStmt->get_result();
    while ($rj = $relatedResult->fetch_assoc()) {
        $rj['skills'] = [];
        $relatedJobs[] = $rj;
    }
    $relStmt->close();

    if (!empty($relatedJobs)) {
        $rjIds = array_column($relatedJobs, 'id');
        $rjPlaceholders = implode(',', array_fill(0, count($rjIds), '?'));
        $rjStmt = $conn->prepare("
            SELECT js.job_id, s.skill_name
            FROM job_skills js
            JOIN skills s ON js.skill_id = s.id
            WHERE js.job_id IN ($rjPlaceholders)
        ");
        $rjStmt->bind_param(str_repeat('i', count($rjIds)), ...$rjIds);
        $rjStmt->execute();
        $rjRes = $rjStmt->get_result();
        while ($rs = $rjRes->fetch_assoc()) {
            foreach ($relatedJobs as &$rj) {
                if ($rj['id'] == $rs['job_id']) {
                    $rj['skills'][] = $rs['skill_name'];
                }
            }
        }
        $rjStmt->close();
    }
}

$skillColors = [
    'Frontend' => ['bg' => 'bg-blue-50', 'text' => 'text-blue-600', 'border' => 'border-blue-100'],
    'Backend' => ['bg' => 'bg-emerald-50', 'text' => 'text-emerald-600', 'border' => 'border-emerald-100'],
    'Database' => ['bg' => 'bg-amber-50', 'text' => 'text-amber-600', 'border' => 'border-amber-100'],
    'DevOps' => ['bg' => 'bg-violet-50', 'text' => 'text-violet-600', 'border' => 'border-violet-100'],
    'Design' => ['bg' => 'bg-pink-50', 'text' => 'text-pink-600', 'border' => 'border-pink-100'],
    'Mobile' => ['bg' => 'bg-cyan-50', 'text' => 'text-cyan-600', 'border' => 'border-cyan-100'],
    'Data Science' => ['bg' => 'bg-rose-50', 'text' => 'text-rose-600', 'border' => 'border-rose-100'],
    'General' => ['bg' => 'bg-gray-50', 'text' => 'text-gray-600', 'border' => 'border-gray-100'],
];
$defaultColor = ['bg' => 'bg-gray-50', 'text' => 'text-gray-600', 'border' => 'border-gray-100'];

$statusColors = [
    'open' => 'bg-emerald-50 text-emerald-600 border border-emerald-200',
    'in_progress' => 'bg-blue-50 text-blue-600 border border-blue-200',
    'completed' => 'bg-gray-100 text-gray-600 border border-gray-200',
    'disputed' => 'bg-red-50 text-red-600 border border-red-200',
    'cancelled' => 'bg-gray-100 text-gray-500 border border-gray-200',
];

$pageTitle = decode_over_encoded($job['title']);
$activePage = 'browse_jobs';
$user = ['name' => $user['name'] ?? 'Freelancer', 'profile_image' => $user['profile_image'] ?? null];
$unreadCount = get_unread_message_count($userId, 'freelancer');
require_once __DIR__ . '/../components/freelancer_header.php';
?>

<?php display_flash('success') ?>
<?php display_flash('error') ?>
<div class="max-w-7xl mx-auto px-4 sm:px-6 py-8">
    <div class="flex flex-col xl:flex-row gap-6">

        <!-- ═══ MAIN JOB DETAILS ═══════════════════════════ -->
        <div class="flex-1 min-w-0 space-y-6">

            <!-- Job Header Card -->
            <div class="bg-white rounded-2xl border border-gray-100 shadow-sm p-6 fade-in">
                <div class="flex flex-wrap items-center gap-2 mb-4">
                    <h2 class="text-xl font-extrabold text-gray-900"><a href="browse_jobs.php"><i data-lucide="arrow-left" class="text-sm pr-2"></i></a><?= decode_over_encoded($job['title']) ?></h2>
                    <span class="inline-block px-3 py-1 rounded-lg text-xs font-bold <?= $statusColors[$job['status']] ?? $statusColors['open'] ?>">
                        <?= ucfirst(str_replace('_', ' ', $job['status'])) ?>
                    </span>
                </div>

                <div class="flex flex-wrap items-center gap-5 text-sm text-gray-500 mb-5">
                    <span class="flex items-center gap-2">
                        <div class="w-8 h-8 rounded-lg bg-emerald-50 flex items-center justify-center">
                            <i data-lucide="dollar-sign" class="text-emerald-500 text-xs"></i>
                        </div>
                        <div>
                            <p class="text-[10px] text-gray-400 uppercase tracking-wider">Budget</p>
                            <p class="font-bold text-gray-900"><?= format_currency($job['budget']) ?></p>
                        </div>
                    </span>
                    <span class="flex items-center gap-2">
                        <div class="w-8 h-8 rounded-lg bg-indigo-50 flex items-center justify-center">
                            <i data-lucide="clock" class="text-indigo-500 text-xs"></i>
                        </div>
                        <div>
                            <p class="text-[10px] text-gray-400 uppercase tracking-wider">Posted</p>
                            <p class="font-semibold text-gray-700"><?= time_ago($job['created_at']) ?></p>
                        </div>
                    </span>
                    <span class="flex items-center gap-2">
                        <div class="w-8 h-8 rounded-lg bg-violet-50 flex items-center justify-center">
                            <i data-lucide="calendar" class="text-violet-500 text-xs"></i>
                        </div>
                        <div>
                            <p class="text-[10px] text-gray-400 uppercase tracking-wider">Deadline</p>
                            <p class="font-semibold text-gray-700"><?= $job['deadline'] ? date('M d, Y', strtotime($job['deadline'])) : 'Open' ?></p>
                        </div>
                    </span>
                    <?php if (!empty($job['job_type'])): ?>
                    <span class="flex items-center gap-2">
                        <div class="w-8 h-8 rounded-lg bg-cyan-50 flex items-center justify-center">
                            <i data-lucide="briefcase" class="text-cyan-500 text-xs"></i>
                        </div>
                        <div>
                            <p class="text-[10px] text-gray-400 uppercase tracking-wider">Job Type</p>
                            <p class="font-semibold text-gray-700"><?= ucfirst($job['job_type']) ?></p>
                        </div>
                    </span>
                    <?php endif; ?>
                    <?php if (!empty($job['experience_level'])): ?>
                    <span class="flex items-center gap-2">
                        <div class="w-8 h-8 rounded-lg bg-amber-50 flex items-center justify-center">
                            <i data-lucide="layers" class="text-amber-500 text-xs"></i>
                        </div>
                        <div>
                            <p class="text-[10px] text-gray-400 uppercase tracking-wider">Experience</p>
                            <p class="font-semibold text-gray-700"><?= ucfirst($job['experience_level']) ?></p>
                        </div>
                    </span>
                    <?php endif; ?>
                </div>

                <!-- Skills -->
                <?php if (!empty($jobSkills)): ?>
                    <div class="mb-5">
                        <h4 class="text-xs font-bold text-gray-400 uppercase tracking-wider mb-2">Required Skills</h4>
                        <div class="flex flex-wrap gap-2">
                            <?php
                            foreach ($jobSkills as $skill):
                                $col = $skillColors[$skill['category']] ?? $defaultColor;
                                ?>
                                <span class="inline-flex items-center gap-1.5 px-3 py-1.5 <?= $col['bg'] ?> <?= $col['text'] ?> text-xs font-semibold rounded-lg border <?= $col['border'] ?>">
                                    <i data-lucide="tag" class="text-[8px] opacity-60"></i>
                                    <?= sanitize_string($skill['skill_name']) ?>
                                </span>
                            <?php endforeach; ?>
                        </div>
                    </div>
                <?php endif; ?>

                <!-- Description -->
                <div>
                    <h4 class="text-xs font-bold text-gray-400 uppercase tracking-wider mb-3">Project Description</h4>
                    <div class="bg-gray-50 rounded-xl p-5 border border-gray-100">
                        <p class="text-sm text-gray-700 leading-relaxed whitespace-pre-line"><?= nl2br(sanitize_string($job['description'])) ?></p>
                    </div>
                </div>
            </div>
            <?php $conn->close(); ?>
            <!-- ═══ PROPOSAL SECTION ═══════════════════════════ -->
            <?php if ($job['status'] === 'open'): ?>
                <div class="bg-white rounded-2xl border border-gray-100 shadow-sm p-6 fade-in" style="animation-delay:.1s">
                    <?php if ($hasApplied): ?>
                        <div class="flex items-center gap-4 p-5 bg-gray-50 rounded-xl border border-gray-200">
                            <div class="w-12 h-12 rounded-xl bg-indigo-100 flex items-center justify-center flex-shrink-0">
                                <i data-lucide="circle-check" class="text-indigo-500 text-xl"></i>
                            </div>
                            <div class="flex-1">
                                <h4 class="font-bold text-gray-900 text-sm">You've Already Applied</h4>
                                <p class="text-xs text-gray-500 mt-0.5">
                                    Submitted <?= time_ago($existingProposal['created_at']) ?>
                                    <span class="text-gray-300 mx-1">|</span>
                                    Bid: <span class="font-semibold text-gray-700"><?= format_currency($existingProposal['amount']) ?></span>
                                </p>
                            </div>
                            <a href="proposals.php" class="text-xs text-indigo-600 hover:text-indigo-700 font-semibold transition-colors">
                                View Proposal <i data-lucide="arrow-right" class="ml-1 text-[9px]"></i>
                            </a>
                        </div>
                    <?php else: ?>
                        <h3 class="text-base font-bold text-gray-900 mb-4 flex items-center gap-2">
                            <i data-lucide="send" class="text-indigo-500 text-sm"></i> Submit Your Proposal
                        </h3>
                        <form method="POST" action="/jobhub/freelancer/browse_jobs.php" class="space-y-4">
                            <input type="hidden" name="action" value="submit_proposal">
                            <input type="hidden" name="job_id" value="<?= $job['id'] ?>">

                            <div>
                                <label class="block text-xs font-semibold text-gray-700 mb-1.5">Your Bid Amount ($) <span class="text-red-500">*</span></label>
                                <div class="relative">
                                    <div class="absolute left-4 top-1/2 -translate-y-1/2 flex items-center justify-center w-6 h-6 rounded-lg bg-emerald-100">
                                        <i data-lucide="dollar-sign" class="text-emerald-600 text-xs"></i>
                                    </div>
                                    <input type="number" name="amount" step="0.01" min="0.01" required
                                        placeholder="0.00" max="<?= $job['budget'] * 2 ?>"
                                        class="fld w-full bg-gray-50 border border-gray-200 rounded-xl pl-12 pr-4 py-3 text-sm text-gray-900 placeholder-gray-400">
                                </div>
                                <p class="text-[11px] text-gray-400 mt-1">Client budget: <span class="font-semibold text-gray-600"><?= format_currency($job['budget']) ?></span></p>
                            </div>

                            <div>
                                <label class="block text-xs font-semibold text-gray-700 mb-1.5">Proposal Message <span class="text-red-500">*</span></label>
                                <textarea name="proposal_text" rows="6" required
                                    placeholder="Explain why you're the best fit for this project. Mention relevant experience, your approach, estimated timeline..."
                                    class="fld w-full bg-gray-50 border border-gray-200 rounded-xl px-4 py-3 text-sm text-gray-900 placeholder-gray-400 resize-none"></textarea>
                                <p class="text-[11px] text-gray-400 mt-1">Minimum 20 characters</p>
                            </div>

                            <button type="submit" class="w-full btn-grad py-3.5 text-white text-sm font-bold rounded-xl shadow-lg shadow-indigo-500/25 flex items-center justify-center gap-2">
                                <i data-lucide="send" class="text-xs"></i> Submit Proposal
                            </button>
                        </form>
                    <?php endif; ?>
                </div>
            <?php endif; ?>

        </div>

        <!-- ═══ SIDEBAR ═══════════════════════════════════════ -->
        <div class="w-full xl:w-80 flex-shrink-0 space-y-5">

            <!-- Client Info Card -->
            <div class="bg-white rounded-2xl border border-gray-100 shadow-sm p-6 fade-in" style="animation-delay:.15s">
                <h3 class="text-sm font-bold text-gray-900 mb-4 flex items-center gap-2">
                    <i data-lucide="user" class="text-indigo-500 text-xs"></i> About the Client
                </h3>

                <div class="flex items-center gap-3 mb-5">
                    <?php
                    $clientAvatar = get_profile_image($clientImage);
                    $hasRealImage = $clientImage && $clientAvatar !== '/jobhub/assets/upload/profile.png';
                    $clientName = $job['client_name'] ?? 'C';
                    $clientInitials = strtoupper(substr($clientName, 0, 1));
                    ?>
                    <?php if ($hasRealImage): ?>
                        <img src="<?= htmlspecialchars($clientAvatar) ?>" class="w-12 h-12 rounded-xl object-cover border-2 border-gray-100" alt="Client">
                    <?php else: ?>
                        <div class="w-12 h-12 rounded-xl bg-indigo-100 text-indigo-600 flex items-center justify-center font-bold text-sm border-2 border-indigo-200/50">
                            <?= $clientInitials ?>
                        </div>
                    <?php endif; ?>
                    <div>
                        <p class="font-bold text-gray-900 text-sm"><?= sanitize_string($job['client_name']) ?></p>
                        <p class="text-[11px] text-gray-400">Member since <?= date('M Y', strtotime($job['client_joined'])) ?></p>
                    </div>
                </div>

                <div class="grid grid-cols-2 gap-3">
                    <div class="bg-gray-50 rounded-xl p-3 text-center border border-gray-100">
                        <p class="text-lg font-black text-gray-900"><?= $clientJobsPosted ?></p>
                        <p class="text-[10px] text-gray-400 uppercase tracking-wider font-semibold">Jobs Posted</p>
                    </div>
                    <div class="bg-gray-50 rounded-xl p-3 text-center border border-gray-100">
                        <p class="text-lg font-black text-gray-900"><?= format_currency($clientTotalSpent) ?></p>
                        <p class="text-[10px] text-gray-400 uppercase tracking-wider font-semibold">Total Spent</p>
                    </div>
                </div>
            </div>

            <!-- Job Summary Card -->
            <div class="bg-white rounded-2xl border border-gray-100 shadow-sm p-6 fade-in" style="animation-delay:.2s">
                <h3 class="text-sm font-bold text-gray-900 mb-4 flex items-center gap-2">
                    <i data-lucide="receipt" class="text-violet-500 text-xs"></i> Job Summary
                </h3>
                <div class="space-y-3">
                    <div class="flex items-center justify-between py-2 border-b border-gray-50">
                        <span class="text-xs text-gray-500">Budget</span>
                        <span class="text-sm font-bold text-gray-900"><?= format_currency($job['budget']) ?></span>
                    </div>
                    <!-- <div class="flex items-center justify-between py-2 border-b border-gray-50">
                                    <span class="text-xs text-gray-500">Status</span>
                                    <span class="text-xs font-bold <?= $statusColors[$job['status']] ?> px-2 py-0.5 rounded-md">
                                        <?= ucfirst(str_replace('_', ' ', $job['status'])) ?>
                                    </span>
                                </div> -->

                    <div class="flex items-center justify-between py-2 border-b border-gray-50">
                        <span class="text-xs text-gray-500">Status</span>
                        <!-- Added a fallback default color string if the key is empty -->
                        <span class="text-xs font-bold <?= $statusColors[$job['status'] ?? ''] ?? 'bg-gray-100 text-gray-600' ?> px-2 py-0.5 rounded-md">
                            <?= ucfirst(str_replace('_', ' ', $job['status'] ?? 'unknown')) ?>
                        </span>
                    </div>
                    <div class="flex items-center justify-between py-2 border-b border-gray-50">
                        <span class="text-xs text-gray-500">Posted</span>
                        <span class="text-xs font-semibold text-gray-700"><?= time_ago($job['created_at']) ?></span>
                    </div>
                    <div class="flex items-center justify-between py-2 border-b border-gray-50">
                        <span class="text-xs text-gray-500">Deadline</span>
                        <span class="text-xs font-semibold text-gray-700"><?= $job['deadline'] ? date('M d, Y', strtotime($job['deadline'])) : 'Open' ?></span>
                    </div>
                    <?php if (!empty($job['job_type'])): ?>
                    <div class="flex items-center justify-between py-2 border-b border-gray-50">
                        <span class="text-xs text-gray-500">Job Type</span>
                        <span class="text-xs font-semibold text-gray-700"><?= ucfirst($job['job_type']) ?></span>
                    </div>
                    <?php endif; ?>
                    <?php if (!empty($job['experience_level'])): ?>
                    <div class="flex items-center justify-between py-2 border-b border-gray-50">
                        <span class="text-xs text-gray-500">Experience</span>
                        <span class="text-xs font-semibold text-gray-700"><?= ucfirst($job['experience_level']) ?></span>
                    </div>
                    <?php endif; ?>
                    <div class="flex items-center justify-between py-2">
                        <span class="text-xs text-gray-500">Skills Required</span>
                        <span class="text-xs font-bold text-gray-900"><?= count($jobSkills) ?></span>
                    </div>
                </div>
            </div>

            <!-- ═══ RELATED JOBS ═══════════════════════════════ -->
            <?php if (!empty($relatedJobs)): ?>
                <div class="bg-white rounded-2xl border border-gray-100 shadow-sm p-6 fade-in" style="animation-delay:.25s">
                    <h3 class="text-sm font-bold text-gray-900 mb-4 flex items-center gap-2">
                        <i data-lucide="layers" class="text-cyan-500 text-xs"></i> Related Jobs
                    </h3>
                    <div class="space-y-3">
                        <?php foreach ($relatedJobs as $rj): ?>
                            <a href="job_detail.php?id=<?= $rj['id'] ?>" class="block p-3 rounded-xl border border-gray-100 hover:border-indigo-200 hover:bg-indigo-50/50 transition-all group">
                                <h4 class="text-sm font-bold text-gray-900 group-hover:text-indigo-600 transition-colors line-clamp-1 mb-1">
                                    <?= sanitize_string($rj['title']) ?>
                                </h4>
                                <div class="flex items-center gap-3 text-[11px] text-gray-400">
                                    <span class="flex items-center gap-1">
                                        <i data-lucide="dollar-sign" class="text-emerald-500"></i>
                                        <span class="font-semibold text-gray-600"><?= format_currency($rj['budget']) ?></span>
                                    </span>
                                    <span class="flex items-center gap-1">
                                        <i data-lucide="clock" class="text-indigo-400"></i>
                                        <?= time_ago($rj['created_at']) ?>
                                    </span>
                                </div>
                                <?php if (!empty($rj['skills'])): ?>
                                    <div class="flex flex-wrap gap-1 mt-2">
                                        <?php foreach (array_slice($rj['skills'], 0, 3) as $rs): ?>
                                            <span class="px-2 py-0.5 bg-gray-100 text-gray-500 text-[10px] font-medium rounded-md"><?= sanitize_string($rs) ?></span>
                                        <?php endforeach; ?>
                                    </div>
                                <?php endif; ?>
                            </a>
                        <?php endforeach; ?>
                    </div>
                </div>
            <?php endif; ?>

            <!-- Back link -->
            <a href="browse_jobs.php" class="flex items-center justify-center gap-2 w-full py-3 border border-gray-200 text-gray-600 hover:border-indigo-300 hover:text-indigo-600 rounded-xl text-sm font-semibold transition-all">
                <i data-lucide="arrow-left" class="text-xs"></i> Back to Browse Jobs
            </a>

        </div>
    </div>

</div>
<script src="/jobhub/shared/dark-toggle.js"></script>
<?php require_once __DIR__ . '/../components/freelancer_footer.php'; ?>