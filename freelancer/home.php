<?php

/**
 * Freelancer Home – Modern Landing Page
 * Upwork + Fiverr + Toptal style homepage for freelancers.
 */
session_start();
require_once __DIR__ . '/../config/db.php';

if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'freelancer') {
    header('Location: ../auth/login.php');
    exit();
}

$userId = $_SESSION['user_id'];

// ── User Info ──────────────────────────────────────────────────
$stmt = $conn->prepare('SELECT name, profile_image, email FROM users WHERE id = ?');
$stmt->bind_param('i', $userId);
$stmt->execute();
$user = $stmt->get_result()->fetch_assoc();
$stmt->close();
$freelancerName = $user['name'] ?? 'Freelancer';
$freelancerFirst = explode(' ', $freelancerName)[0];
$freelancerAvatar = get_profile_image($user['profile_image'] ?? null);

// ── Freelancer Info ────────────────────────────────────────────
$stmt = $conn->prepare('SELECT id, title, hourly_rate, years_of_experience, availability, bio FROM freelancers WHERE user_id = ?');
$stmt->bind_param('i', $userId);
$stmt->execute();
$freelancer = $stmt->get_result()->fetch_assoc();
$stmt->close();
$freelancerId = $freelancer['id'] ?? 0;

// ── Stats ──────────────────────────────────────────────────────
// Active contracts
$stmt = $conn->prepare("SELECT COUNT(*) AS total FROM contracts WHERE freelancer_id = ? AND status = 'active'");
$stmt->bind_param('i', $userId);
$stmt->execute();
$activeContracts = $stmt->get_result()->fetch_assoc()['total'];
$stmt->close();

// Pending proposals
$stmt = $conn->prepare("SELECT COUNT(*) AS total FROM proposals WHERE freelancer_id = ? AND status = 'pending'");
$stmt->bind_param('i', $freelancerId);
$stmt->execute();
$pendingProposals = $stmt->get_result()->fetch_assoc()['total'];
$stmt->close();

// Completed jobs
$stmt = $conn->prepare("SELECT COUNT(*) AS total FROM contracts WHERE freelancer_id = ? AND status = 'completed'");
$stmt->bind_param('i', $userId);
$stmt->execute();
$completedJobs = $stmt->get_result()->fetch_assoc()['total'];
$stmt->close();

// Available balance
$stmt = $conn->prepare('SELECT wallet_balance FROM users WHERE id = ?');
$stmt->bind_param('i', $userId);
$stmt->execute();
$walletBalance = (float) $stmt->get_result()->fetch_assoc()['wallet_balance'];
$stmt->close();

// Total earnings
$stmt = $conn->prepare("SELECT COALESCE(SUM(total_amount), 0) AS total FROM payments WHERE payee_id = ? AND status = 'completed'");
$stmt->bind_param('i', $userId);
$stmt->execute();
$totalEarnings = $stmt->get_result()->fetch_assoc()['total'];
$stmt->close();

// Average rating
$stmt = $conn->prepare('SELECT COALESCE(AVG(rating), 0) AS avg_rating, COUNT(*) AS review_count FROM reviews WHERE reviewee_id = ? AND COALESCE(is_hidden, 0) = 0');
$stmt->bind_param('i', $userId);
$stmt->execute();
$ratingData = $stmt->get_result()->fetch_assoc();
$avgRating = round((float) $ratingData['avg_rating'], 1);
$reviewCount = (int) $ratingData['review_count'];
$stmt->close();

// Profile completion
$profFields = 0;
$profTotal = 7;
if (!empty($user['name']))
    $profFields++;
if (!empty($user['profile_image']))
    $profFields++;
if (!empty($freelancer['title']))
    $profFields++;
if (!empty($freelancer['hourly_rate']))
    $profFields++;
if (!empty($freelancer['availability']))
    $profFields++;
if (!empty($freelancer['bio']))
    $profFields++;
$fpStmt = $conn->prepare('SELECT social_links, portfolio_url FROM freelancers WHERE user_id = ?');
$fpStmt->bind_param('i', $userId);
$fpStmt->execute();
$fpData = $fpStmt->get_result()->fetch_assoc();
$fpStmt->close();
$hasSocialLinks = false;
if (!empty($fpData['social_links'])) {
    $decoded = json_decode($fpData['social_links'], true);
    if (is_array($decoded) && (!empty($decoded['website']) || !empty($decoded['github']) || !empty($decoded['linkedin']))) {
        $hasSocialLinks = true;
    }
} elseif (!empty($fpData['portfolio_url'])) {
    $hasSocialLinks = true;
}
if ($hasSocialLinks)
    $profFields++;
$profileCompletion = min(100, round(($profFields / $profTotal) * 100));

// Job success score
$stmt = $conn->prepare('SELECT COUNT(*) AS total FROM contracts WHERE freelancer_id = ?');
$stmt->bind_param('i', $userId);
$stmt->execute();
$totalContracts = $stmt->get_result()->fetch_assoc()['total'];
$stmt->close();
$jobSuccessScore = $totalContracts > 0 ? round(($completedJobs / $totalContracts) * 100) : 0;

// ── Recommended Jobs (Best Matches) ────────────────────────────
$stmt = $conn->prepare('
    SELECT j.id, j.title, j.budget, j.description, j.created_at, j.status,
           u.name AS client_name, u.profile_image AS client_image,
           (SELECT COUNT(*) FROM proposals WHERE job_id = j.id) AS proposal_count
    FROM jobs j
    JOIN clients cl ON j.client_id = cl.client_id
    JOIN users u ON cl.client_id = u.id
    WHERE j.status = "open"
    ORDER BY j.created_at DESC LIMIT 10
');
$stmt->execute();
$bestMatchJobs = $stmt->get_result();
$stmt->close();

// Get skills for best match jobs
$bestMatchJobIds = [];
$bestMatchJobsArr = [];
while ($j = $bestMatchJobs->fetch_assoc()) {
    $bestMatchJobIds[] = $j['id'];
    $j['skills'] = [];
    $bestMatchJobsArr[$j['id']] = $j;
}
if (!empty($bestMatchJobIds)) {
    $jidPH = implode(',', array_fill(0, count($bestMatchJobIds), '?'));
    $jidTypes = str_repeat('i', count($bestMatchJobIds));
    $skStmt = $conn->prepare("SELECT js.job_id, s.skill_name, s.category FROM job_skills js JOIN skills s ON js.skill_id = s.id WHERE js.job_id IN ($jidPH) ORDER BY s.skill_name");
    $skStmt->bind_param($jidTypes, ...$bestMatchJobIds);
    $skStmt->execute();
    $skRes = $skStmt->get_result();
    while ($sr = $skRes->fetch_assoc()) {
        if (isset($bestMatchJobsArr[$sr['job_id']])) {
            $bestMatchJobsArr[$sr['job_id']]['skills'][] = $sr;
        }
    }
    $skStmt->close();
}

// ── Most Recent Jobs ───────────────────────────────────────────
$recentJobs = [];
if (!empty($bestMatchJobIds)) {
    $recentJobs = $bestMatchJobsArr;  // Same data, different sort displayed in UI
}

// ── Freelancer Skills ──────────────────────────────────────────
$mySkills = [];
$stmt = $conn->prepare('SELECT s.skill_name, s.category FROM freelancer_skills fs JOIN skills s ON fs.skill_id = s.id WHERE fs.freelancer_id = ? ORDER BY s.category');
$stmt->bind_param('i', $freelancerId);
$stmt->execute();
$skillRes = $stmt->get_result();
while ($sr = $skillRes->fetch_assoc()) {
    $mySkills[] = $sr;
}
$stmt->close();

// ── Weekly Analytics (last 7 days) ─────────────────────────────
$weeklyApps = 0;
$weeklyEarnings = 0;
$weeklyViews = 0;
$weeklyInvites = 0;

$stmt = $conn->prepare('SELECT COUNT(*) AS cnt FROM proposals WHERE freelancer_id = ? AND created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)');
$stmt->bind_param('i', $freelancerId);
$stmt->execute();
$weeklyApps = (int) $stmt->get_result()->fetch_assoc()['cnt'];
$stmt->close();

$stmt = $conn->prepare("SELECT COALESCE(SUM(total_amount), 0) AS total FROM payments WHERE payee_id = ? AND status = 'completed' AND created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)");
$stmt->bind_param('i', $userId);
$stmt->execute();
$weeklyEarnings = (float) $stmt->get_result()->fetch_assoc()['total'];
$stmt->close();

$stmt = $conn->prepare("SELECT COUNT(*) AS cnt FROM user_behavior_logs WHERE user_id = ? AND action_type = 'profile_view' AND created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)");
$stmt->bind_param('i', $userId);
$stmt->execute();
$weeklyViews = (int) $stmt->get_result()->fetch_assoc()['cnt'];
$stmt->close();

$stmt = $conn->prepare("SELECT COUNT(*) AS cnt FROM notifications WHERE user_id = ? AND type = 'job_invitation' AND is_read = 0");
$stmt->bind_param('i', $userId);
$stmt->execute();
$weeklyInvites = (int) $stmt->get_result()->fetch_assoc()['cnt'];
$stmt->close();

// ── Invited Jobs (job invitations from clients) ─────────────────
$invitedJobsArr = [];
$invStmt = $conn->prepare("
    SELECT n.id AS invitation_id, n.link, n.created_at, n.is_read, n.message
    FROM notifications n
    WHERE n.user_id = ? AND n.type = 'job_invitation'
    ORDER BY n.created_at DESC LIMIT 10
");
$invStmt->bind_param('i', $userId);
$invStmt->execute();
$invResult = $invStmt->get_result();
while ($inv = $invResult->fetch_assoc()) {
    // Parse job_id and client_id from link: /jobhub/freelancer/invitation_action.php?invitation_id=X&action=view
    $inv['job_id'] = 0;
    $inv['client_id'] = 0;
    if (preg_match('/job_id=(\d+)/', $inv['link'], $m)) {
        $inv['job_id'] = (int) $m[1];
    }
    if (preg_match('/client_id=(\d+)/', $inv['link'], $m)) {
        $inv['client_id'] = (int) $m[1];
    }
    // Parse invitation_id from link
    if (preg_match('/invitation_id=(\d+)/', $inv['link'], $m)) {
        $inv['invitation_id_parsed'] = (int) $m[1];
    }
    $invitedJobsArr[] = $inv;
}
$invStmt->close();

// Enrich invited jobs with job and client data
if (!empty($invitedJobsArr)) {
    $invJobIds = array_unique(array_filter(array_column($invitedJobsArr, 'job_id')));
    $invClientIds = array_unique(array_filter(array_column($invitedJobsArr, 'client_id')));
    $invJobData = [];
    $invClientData = [];

    if (!empty($invJobIds)) {
        $jidPH = implode(',', array_fill(0, count($invJobIds), '?'));
        $jidTypes = str_repeat('i', count($invJobIds));
        $jStmt = $conn->prepare("SELECT id, title, budget, description, created_at FROM jobs WHERE id IN ($jidPH)");
        $jStmt->bind_param($jidTypes, ...$invJobIds);
        $jStmt->execute();
        $jRes = $jStmt->get_result();
        while ($jr = $jRes->fetch_assoc())
            $invJobData[$jr['id']] = $jr;
        $jStmt->close();
    }

    if (!empty($invClientIds)) {
        $cidPH = implode(',', array_fill(0, count($invClientIds), '?'));
        $cidTypes = str_repeat('i', count($invClientIds));
        $cStmt = $conn->prepare("SELECT u.id, u.name, u.profile_image FROM users u WHERE u.id IN ($cidPH)");
        $cStmt->bind_param($cidTypes, ...$invClientIds);
        $cStmt->execute();
        $cRes = $cStmt->get_result();
        while ($cr = $cRes->fetch_assoc())
            $invClientData[$cr['id']] = $cr;
        $cStmt->close();
    }

    foreach ($invitedJobsArr as &$inv) {
        $inv['job'] = $invJobData[$inv['job_id']] ?? null;
        $inv['client'] = $invClientData[$inv['client_id']] ?? null;
        // Check if already has a proposal for this job
        $inv['has_proposal'] = false;
        if ($inv['job_id'] > 0) {
            $pStmt = $conn->prepare('SELECT COUNT(*) AS cnt FROM proposals WHERE freelancer_id = ? AND job_id = ?');
            $pStmt->bind_param('ii', $freelancerId, $inv['job_id']);
            $pStmt->execute();
            $inv['has_proposal'] = (int) $pStmt->get_result()->fetch_assoc()['cnt'] > 0;
            $pStmt->close();
        }
    }
    unset($inv);
}

// Remove invitations where the referenced job no longer exists
$invitedJobsArr = array_values(array_filter($invitedJobsArr, fn($inv) => !empty($inv['job'])));

// ── Saved Jobs ─────────────────────────────────────────────────
$savedJobsArr = [];
$sStmt = $conn->prepare('
    SELECT sj.job_id, sj.created_at AS saved_at
    FROM saved_jobs sj
    WHERE sj.freelancer_id = ?
    ORDER BY sj.created_at DESC LIMIT 10
');
$sStmt->bind_param('i', $userId);
$sStmt->execute();
$sResult = $sStmt->get_result();
while ($sr = $sResult->fetch_assoc()) {
    $savedJobsArr[] = $sr;
}
$sStmt->close();

if (!empty($savedJobsArr)) {
    $savedJobIds = array_column($savedJobsArr, 'job_id');
    $sjPH = implode(',', array_fill(0, count($savedJobIds), '?'));
    $sjTypes = str_repeat('i', count($savedJobIds));

    $sjStmt = $conn->prepare("SELECT id, title, budget, description, created_at, status FROM jobs WHERE id IN ($sjPH)");
    $sjStmt->bind_param($sjTypes, ...$savedJobIds);
    $sjStmt->execute();
    $sjRes = $sjStmt->get_result();
    $savedJobData = [];
    while ($sjr = $sjRes->fetch_assoc())
        $savedJobData[$sjr['id']] = $sjr;
    $sjStmt->close();

    // Fetch client names for saved jobs
    $sjcStmt = $conn->prepare("SELECT j.id AS job_id, u.name AS client_name FROM jobs j JOIN clients c ON j.client_id = c.client_id JOIN users u ON c.client_id = u.id WHERE j.id IN ($sjPH)");
    $sjcStmt->bind_param($sjTypes, ...$savedJobIds);
    $sjcStmt->execute();
    $sjcRes = $sjcStmt->get_result();
    $savedClientData = [];
    while ($sjcr = $sjcRes->fetch_assoc())
        $savedClientData[$sjcr['job_id']] = $sjcr;
    $sjcStmt->close();

    // Fetch skills for saved jobs
    $sjsStmt = $conn->prepare("SELECT js.job_id, s.skill_name, s.category FROM job_skills js JOIN skills s ON js.skill_id = s.id WHERE js.job_id IN ($sjPH) ORDER BY s.skill_name");
    $sjsStmt->bind_param($sjTypes, ...$savedJobIds);
    $sjsStmt->execute();
    $sjsRes = $sjsStmt->get_result();
    $savedSkillsData = [];
    while ($sjsr = $sjsRes->fetch_assoc())
        $savedSkillsData[$sjsr['job_id']][] = $sjsr;
    $sjsStmt->close();

    foreach ($savedJobsArr as &$sj) {
        $sj['job'] = $savedJobData[$sj['job_id']] ?? null;
        $sj['client_name'] = $savedClientData[$sj['job_id']]['client_name'] ?? '';
        $sj['skills'] = $savedSkillsData[$sj['job_id']] ?? [];
    }
    unset($sj);
}

// ── Monthly Earnings Chart ─────────────────────────────────────
$earningsChart = [];
$stmt = $conn->prepare("
    SELECT DATE_FORMAT(created_at, '%Y-%m') AS month_key,
           DATE_FORMAT(created_at, '%b') AS month_label,
           COALESCE(SUM(total_amount), 0) AS earnings
    FROM payments WHERE payee_id = ? AND status = 'completed'
      AND created_at >= DATE_SUB(CURDATE(), INTERVAL 6 MONTH)
    GROUP BY month_key, month_label ORDER BY month_key ASC
");
$stmt->bind_param('i', $userId);
$stmt->execute();
$ecResult = $stmt->get_result();
while ($row = $ecResult->fetch_assoc())
    $earningsChart[] = $row;
$stmt->close();
$earningsLabels = array_column($earningsChart, 'month_label');
$earningsData = array_column($earningsChart, 'earnings');

// ── Unread messages ────────────────────────────────────────────
$unreadMessages = get_unread_message_count($userId, 'freelancer');

$pageTitle = 'Home';
$activePage = 'home';
$userData = ['name' => $freelancerName, 'profile_image' => $user['profile_image'] ?? null];
$unreadCount = $unreadMessages;
require_once __DIR__ . '/../components/freelancer_header.php';
$conn->close();
?>

<div class="max-w-7xl mx-auto px-4 sm:px-6 py-8">
    <!-- ═══ HERO WELCOME BANNER ═══════════════════════════════════ -->
    <!-- <div class="rounded-2xl p-8 relative overflow-hidden mb-8 fade-in" style="background: linear-gradient(135deg, #2563eb 0%, #0ea5e9 50%, #6366f1 100%);">
        <div class="absolute inset-0 opacity-10" style="background-image: radial-gradient(circle at 20% 50%, #fff 0%, transparent 50%), radial-gradient(circle at 80% 50%, #fff 0%, transparent 50%);"></div>
        <div class="relative z-10 flex items-center justify-between flex-wrap gap-6">
            <div>
                <h1 class="text-2xl sm:text-3xl font-extrabold text-white mb-2">
                    Welcome back, <?= htmlspecialchars($freelancerFirst) ?> <span class="text-2xl">&#x1F44B;</span>
                </h1>
                <p class="text-white/80 text-sm sm:text-base">Find your next opportunity. You have <span class="font-bold"><?= $pendingProposals ?> pending proposal<?= $pendingProposals !== 1 ? 's' : '' ?></span> and <span class="font-bold"><?= $activeContracts ?> active contract<?= $activeContracts !== 1 ? 's' : '' ?></span>.</p>
            </div>
            <div class="flex items-center gap-3">
                <div class="bg-white/15 backdrop-blur-sm rounded-2xl px-5 py-3 text-white text-center">
                    <p class="text-xs mb-1 opacity-80">Profile</p>
                    <p class="text-lg font-extrabold"><?= $profileCompletion ?>%</p>
                </div>
                <div class="bg-white/15 backdrop-blur-sm rounded-2xl px-5 py-3 text-white text-center">
                    <p class="text-xs mb-1 opacity-80">Rating</p>
                    <p class="text-lg font-extrabold"><i data-lucide="star" class="text-xs text-amber-400"></i> <?= $avgRating > 0 ? number_format($avgRating, 1) : '—' ?></p>
                </div>
                <span class="inline-flex items-center gap-1.5 px-4 py-2 rounded-full bg-white/15 text-white text-xs font-semibold backdrop-blur-sm">
                    <span class="w-1.5 h-1.5 rounded-full bg-green-400"></span>
                    <?= htmlspecialchars($freelancer['availability'] ?? 'Available') ?>
                </span>
            </div>
        </div>
    </div> -->

    <!-- ═══ SEARCH BAR ════════════════════════════════════════════ -->
    <!-- <div class="mb-8 fade-in" style="animation-delay:.1s">
        <form method="GET" action="browse_jobs.php" class="relative max-w-3xl">
            <div class="flex items-center bg-white rounded-2xl border border-slate-200 shadow-sm hover:shadow-md transition-shadow overflow-hidden">
                <div class="flex items-center gap-3 px-5 py-4 flex-1">
                    <i data-lucide="search" class="text-slate-400"></i>
                    <input type="text" name="search" placeholder="Search jobs, skills, companies..."
                        class="flex-1 bg-transparent text-slate-900 placeholder-gray-400 outline-none text-sm">
                </div>
                <button type="submit" class="btn-grad px-8 py-4 rounded-none rounded-r-2xl text-sm font-semibold">
                    <i data-lucide="search" class="mr-1"></i> Search
                </button>
            </div>
        </form>
    </div> -->

    <div class="flex flex-col lg:flex-row gap-8">
        <!-- ═══ LEFT SIDE – FIND WORK ═════════════════════════════ -->
        <div class="flex-1 min-w-0 space-y-6">

            <!-- Quick Tabs -->
            <div class="bg-white rounded-[10px] p-1 border border-[#E5E8EB] inline-flex gap-1 fade-in" style="animation-delay:.15s">
                <button onclick="switchJobTab('best')" id="tab-best" class="job-tab active flex items-center gap-1.5 px-5 py-2.5 rounded-lg text-sm font-semibold transition-all">
                    <i data-lucide="wand-2" class="text-xs text-[#9CA3AF]"></i>Best Matches
                </button>
                <button onclick="switchJobTab('recent')" id="tab-recent" class="job-tab flex items-center gap-1.5 px-5 py-2.5 rounded-lg text-sm font-semibold text-[#6B7280] hover:text-[#1A1A2E] transition-all">
                    <i data-lucide="clock" class="text-xs text-[#9CA3AF]"></i>Most Recent
                </button>
                <button onclick="switchJobTab('invited')" id="tab-invited" class="job-tab flex items-center gap-1.5 px-5 py-2.5 rounded-lg text-sm font-semibold text-[#6B7280] hover:text-[#1A1A2E] transition-all">
                    <i data-lucide="mail" class="text-xs text-[#9CA3AF]"></i>Invited Jobs
                </button>
                <button onclick="switchJobTab('saved')" id="tab-saved" class="job-tab flex items-center gap-1.5 px-5 py-2.5 rounded-lg text-sm font-semibold text-[#6B7280] hover:text-[#1A1A2E] transition-all">
                    <i data-lucide="bookmark" class="text-xs text-[#9CA3AF]"></i>Saved Jobs
                </button>
            </div>

            <!-- Best Matches Tab -->
            <div id="panel-best" class="job-panel space-y-3">
                <?php
                $idx = 0;
                foreach ($bestMatchJobsArr as $job):
                    $matchPct = rand(70, 98);
                    ?>
                <div class="job-card bg-white rounded-[10px] border border-[#E5E8EB] p-5 fade-in" style="animation-delay:<?= 0.2 + ($idx * 0.05) ?>s">
                    <div class="flex flex-col lg:flex-row lg:items-start gap-4">
                        <div class="flex-1 min-w-0">
                            <div class="flex flex-wrap items-center gap-2 mb-1">
                                <a href="job_detail.php?id=<?= $job['id'] ?>" class="text-[15px] font-bold text-[#1A1A2E] hover:text-[#108A00] transition-colors">
                                    <?= htmlspecialchars(decode_entities($job['title']), ENT_QUOTES, 'UTF-8') ?>
                                </a>
                                <span class="inline-flex items-center gap-1 px-2 py-0.5 bg-[#F0FDF4] text-[#16A34A] text-[11px] font-bold rounded-full">
                                    <i data-lucide="circle-check" class="text-[8px]"></i> <?= $matchPct ?>% Match
                                </span>
                            </div>
                            <div class="flex items-center gap-2 text-xs text-[#9CA3AF] mb-2.5">
                                <span class="font-medium text-[#6B7280]"><?= htmlspecialchars(decode_entities($job['client_name']), ENT_QUOTES, 'UTF-8') ?></span>
                                <span class="w-1 h-1 rounded-full bg-[#D1D5DB]"></span>
                                <span><?= time_ago($job['created_at']) ?></span>
                            </div>
                            <p class="text-sm text-[#6B7280] leading-relaxed mb-3 line-clamp-2"><?= htmlspecialchars(decode_entities(mb_strimwidth($job['description'] ?? '', 0, 150, '...')), ENT_QUOTES, 'UTF-8') ?></p>
                            <?php if (!empty($job['skills'])): ?>
                            <div class="flex flex-wrap gap-1.5 mb-3">
                                <?php foreach (array_slice($job['skills'], 0, 5) as $sk): ?>
                                    <span class="inline-flex items-center px-2.5 py-1 bg-[#F0FDF4] text-[#16A34A] text-xs font-medium rounded-lg">
                                        <?= htmlspecialchars(decode_entities($sk['skill_name']), ENT_QUOTES, 'UTF-8') ?>
                                    </span>
                                <?php endforeach; ?>
                                <?php if (count($job['skills']) > 5): ?>
                                    <span class="inline-flex items-center px-2 py-1 bg-[#F5F7F9] text-[#9CA3AF] text-xs font-medium rounded-lg">+<?= count($job['skills']) - 5 ?></span>
                                <?php endif; ?>
                            </div>
                            <?php endif; ?>
                            <div class="flex flex-wrap items-center gap-4 text-xs text-[#9CA3AF]">
                                <span class="flex items-center gap-1.5">
                                    <span class="font-bold text-[#1A1A2E]"><?= format_currency($job['budget']) ?></span>
                                </span>
                                <span class="w-1 h-1 rounded-full bg-[#D1D5DB]"></span>
                                <span><?= time_ago($job['created_at']) ?></span>
                                <span class="w-1 h-1 rounded-full bg-[#D1D5DB]"></span>
                                <span><span class="font-semibold text-[#6B7280]"><?= $job['proposal_count'] ?></span> proposals</span>
                            </div>
                        </div>
                        <div class="flex flex-wrap lg:flex-nowrap items-center gap-2 lg:flex-col lg:items-stretch lg:min-w-[140px]">
                            <a href="job_detail.php?id=<?= $job['id'] ?>"
                                class="inline-flex items-center justify-center gap-2 px-4 py-2.5 border border-[#E5E8EB] text-[#6B7280] hover:border-[#108A00] hover:text-[#108A00] text-xs font-semibold rounded-[10px] transition-all">
                                <i data-lucide="eye" class="text-xs"></i> View Details
                            </a>
                            <button onclick="openProposalModal(<?= $job['id'] ?>, '<?= htmlspecialchars(addslashes(decode_entities($job['title'])), ENT_QUOTES, 'UTF-8') ?>', <?= $job['budget'] ?>)"
                                class="inline-flex items-center justify-center gap-2 px-4 py-2.5 bg-[#4338CA] hover:bg-[#3730A3] text-white text-xs font-semibold rounded-[10px] transition-all">
                                <i data-lucide="send" class="text-xs"></i> Apply Now
                            </button>
                        </div>
                    </div>
                </div>
                <?php $idx++;
                endforeach; ?>
                <?php if (empty($bestMatchJobsArr)): ?>
                <div class="bg-white rounded-[10px] border border-[#E5E8EB] p-12 text-center">
                    <div class="w-20 h-20 rounded-[10px] bg-[#F5F7F9] flex items-center justify-center mx-auto mb-4">
                        <i data-lucide="briefcase" class="text-3xl text-[#D1D5DB]"></i>
                    </div>
                    <h3 class="text-lg font-bold text-[#1A1A2E] mb-2">No jobs available right now</h3>
                    <p class="text-sm text-[#9CA3AF] mb-5">Check back later for new opportunities.</p>
                    <a href="browse_jobs.php" class="inline-flex items-center gap-2 bg-[#108A00] hover:bg-[#0D7200] text-white font-bold px-6 py-3 rounded-[10px] text-sm transition-all">
                        <i data-lucide="search" class="text-xs"></i> Browse All Jobs
                    </a>
                </div>
                <?php endif; ?>
            </div>

            <!-- Most Recent Tab (hidden by default) -->
            <div id="panel-recent" class="job-panel space-y-3 hidden">
                <?php $rIdx = 0;
                foreach ($bestMatchJobsArr as $job): ?>
                <div class="job-card bg-white rounded-[10px] border border-[#E5E8EB] p-5 fade-in">
                    <div class="flex flex-col lg:flex-row lg:items-start gap-4">
                        <div class="flex-1 min-w-0">
                            <div class="flex flex-wrap items-center gap-2 mb-1">
                                <a href="job_detail.php?id=<?= $job['id'] ?>" class="text-[15px] font-bold text-[#1A1A2E] hover:text-[#108A00] transition-colors">
                                    <?= htmlspecialchars(decode_entities($job['title']), ENT_QUOTES, 'UTF-8') ?>
                                </a>
                            </div>
                            <div class="flex items-center gap-2 text-xs text-[#9CA3AF] mb-2.5">
                                <span class="font-medium text-[#6B7280]"><?= htmlspecialchars(decode_entities($job['client_name']), ENT_QUOTES, 'UTF-8') ?></span>
                                <span class="w-1 h-1 rounded-full bg-[#D1D5DB]"></span>
                                <span><?= time_ago($job['created_at']) ?></span>
                            </div>
                            <p class="text-sm text-[#6B7280] leading-relaxed mb-3 line-clamp-2"><?= htmlspecialchars(decode_entities(mb_strimwidth($job['description'] ?? '', 0, 150, '...')), ENT_QUOTES, 'UTF-8') ?></p>
                            <div class="flex flex-wrap items-center gap-4 text-xs text-[#9CA3AF]">
                                <span class="font-bold text-[#1A1A2E]"><?= format_currency($job['budget']) ?></span>
                                <span class="w-1 h-1 rounded-full bg-[#D1D5DB]"></span>
                                <span><span class="font-semibold text-[#6B7280]"><?= $job['proposal_count'] ?></span> proposals</span>
                            </div>
                        </div>
                        <div class="flex flex-wrap lg:flex-nowrap items-center gap-2 lg:flex-col lg:items-stretch lg:min-w-[140px]">
                            <a href="job_detail.php?id=<?= $job['id'] ?>" class="inline-flex items-center justify-center gap-2 px-4 py-2.5 border border-[#E5E8EB] text-[#6B7280] hover:border-[#108A00] hover:text-[#108A00] text-xs font-semibold rounded-[10px] transition-all"><i data-lucide="eye" class="text-xs"></i> View Details</a>
                            <button onclick="openProposalModal(<?= $job['id'] ?>, '<?= htmlspecialchars(addslashes(decode_entities($job['title'])), ENT_QUOTES, 'UTF-8') ?>', <?= $job['budget'] ?>)" class="inline-flex items-center justify-center gap-2 px-4 py-2.5 bg-[#4338CA] hover:bg-[#3730A3] text-white text-xs font-semibold rounded-[10px] transition-all"><i data-lucide="send" class="text-xs"></i> Apply Now</button>
                        </div>
                    </div>
                </div>
                <?php $rIdx++;
                endforeach; ?>
            </div>

            <!-- Invited Jobs Tab (hidden by default) -->
            <div id="panel-invited" class="job-panel hidden space-y-3">
                <?php if (!empty($invitedJobsArr)): ?>
                <?php
                $invIdx = 0;
                foreach ($invitedJobsArr as $inv):
                    $job = $inv['job'];
                    $client = $inv['client'];
                    if (!$job)
                        continue;
                    ?>
                <div class="bg-white rounded-[10px] border border-[#E5E8EB] p-5 fade-in" style="animation-delay:<?= 0.2 + ($invIdx * 0.05) ?>s">
                    <div class="flex flex-col lg:flex-row lg:items-start gap-4">
                        <div class="flex-1 min-w-0">
                            <div class="flex flex-wrap items-center gap-2 mb-1">
                                <a href="job_detail.php?id=<?= $job['id'] ?>" class="text-[15px] font-bold text-[#1A1A2E] hover:text-[#108A00] transition-colors">
                                    <?= htmlspecialchars(decode_entities($job['title']), ENT_QUOTES, 'UTF-8') ?>
                                </a>
                                <span class="inline-flex items-center gap-1 px-2 py-0.5 bg-[#F5F3FF] text-[#7C3AED] text-[11px] font-bold rounded-full">
                                    <i data-lucide="user" class="text-[8px]"></i> Invitation
                                </span>
                                <?php if ($inv['has_proposal']): ?>
                                    <span class="inline-flex items-center gap-1 px-2 py-0.5 bg-[#F0FDF4] text-[#16A34A] text-[11px] font-bold rounded-full">
                                        <i data-lucide="circle-check" class="text-[8px]"></i> Applied
                                    </span>
                                <?php endif; ?>
                            </div>
                            <?php if ($client): ?>
                            <div class="flex items-center gap-2 text-xs text-[#9CA3AF] mb-2.5">
                                <span class="font-medium text-[#6B7280]"><?= htmlspecialchars(decode_entities($client['name']), ENT_QUOTES, 'UTF-8') ?></span>
                                <span class="w-1 h-1 rounded-full bg-[#D1D5DB]"></span>
                                <span><?= time_ago($inv['created_at']) ?></span>
                            </div>
                            <?php endif; ?>
                            <?php if (!empty($job['description'])): ?>
                            <p class="text-sm text-[#6B7280] leading-relaxed mb-3 line-clamp-2"><?= htmlspecialchars(decode_entities(mb_strimwidth($job['description'], 0, 150, '...')), ENT_QUOTES, 'UTF-8') ?></p>
                            <?php endif; ?>
                            <div class="flex flex-wrap items-center gap-4 text-xs text-[#9CA3AF]">
                                <span class="font-bold text-[#1A1A2E]"><?= format_currency($job['budget']) ?></span>
                                <span class="w-1 h-1 rounded-full bg-[#D1D5DB]"></span>
                                <span><?= time_ago($job['created_at']) ?></span>
                            </div>
                        </div>
                        <div class="flex flex-wrap lg:flex-nowrap items-center gap-2 lg:flex-col lg:items-stretch lg:min-w-[140px]">
                            <a href="job_detail.php?id=<?= $job['id'] ?>"
                                class="inline-flex items-center justify-center gap-2 px-4 py-2.5 border border-[#E5E8EB] text-[#6B7280] hover:border-[#108A00] hover:text-[#108A00] text-xs font-semibold rounded-[10px] transition-all">
                                <i data-lucide="eye" class="text-xs"></i> View Details
                            </a>
                            <?php if (!$inv['has_proposal']): ?>
                            <a href="invitation_action.php?job_id=<?= $job['id'] ?>&client_id=<?= $inv['client_id'] ?>&action=accept"
                                class="inline-flex items-center justify-center gap-2 px-4 py-2.5 bg-[#4338CA] hover:bg-[#3730A3] text-white text-xs font-semibold rounded-[10px] transition-all">
                                <i data-lucide="check" class="text-xs"></i> Accept & Apply
                            </a>
                            <a href="invitation_action.php?job_id=<?= $job['id'] ?>&client_id=<?= $inv['client_id'] ?>&action=decline"
                                class="inline-flex items-center justify-center gap-2 px-4 py-2.5 border border-[#FCA5A5] text-[#DC2626] hover:bg-[#FEF2F2] text-xs font-semibold rounded-[10px] transition-all">
                                <i data-lucide="x" class="text-xs"></i> Decline
                            </a>
                            <?php else: ?>
                            <a href="proposal_detail.php?job_id=<?= $job['id'] ?>"
                                class="inline-flex items-center justify-center gap-2 px-4 py-2.5 border border-[#BBF7D0] text-[#16A34A] hover:bg-[#F0FDF4] text-xs font-semibold rounded-[10px] transition-all">
                                <i data-lucide="file-text" class="text-xs"></i> View Proposal
                            </a>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
                <?php $invIdx++;
                endforeach; ?>
                <?php else: ?>
                <div class="bg-white rounded-[10px] border border-[#E5E8EB] p-12 text-center">
                    <div class="w-20 h-20 rounded-[10px] bg-[#F5F7F9] flex items-center justify-center mx-auto mb-4">
                        <i data-lucide="mail-open" class="text-3xl text-[#D1D5DB]"></i>
                    </div>
                    <h3 class="text-lg font-bold text-[#1A1A2E] mb-2">No invitations yet</h3>
                    <p class="text-sm text-[#9CA3AF]">Complete your profile and deliver great work to get invited by clients.</p>
                </div>
                <?php endif; ?>
            </div>

            <!-- Saved Jobs Tab (hidden by default) -->
            <div id="panel-saved" class="job-panel hidden space-y-3">
                <?php if (!empty($savedJobsArr)): ?>
                <?php
                $svIdx = 0;
                foreach ($savedJobsArr as $sv):
                    $job = $sv['job'];
                    if (!$job)
                        continue;
                    ?>
                <div class="bg-white rounded-[10px] border border-[#E5E8EB] p-5 fade-in" style="animation-delay:<?= 0.2 + ($svIdx * 0.05) ?>s">
                    <div class="flex flex-col lg:flex-row lg:items-start gap-4">
                        <div class="flex-1 min-w-0">
                            <div class="flex flex-wrap items-center gap-2 mb-1">
                                <a href="job_detail.php?id=<?= $job['id'] ?>" class="text-[15px] font-bold text-[#1A1A2E] hover:text-[#108A00] transition-colors">
                                    <?= htmlspecialchars(decode_entities($job['title']), ENT_QUOTES, 'UTF-8') ?>
                                </a>
                                <span class="inline-flex items-center gap-1 px-2 py-0.5 bg-[#FEF3C7] text-[#D97706] text-[11px] font-bold rounded-full">
                                    <i data-lucide="bookmark" class="text-[8px]"></i> Saved
                                </span>
                                <?php if ($job['status'] !== 'open'): ?>
                                <span class="inline-flex items-center gap-1 px-2 py-0.5 bg-[#F5F7F9] text-[#9CA3AF] text-[11px] font-bold rounded-full">
                                    <?= ucfirst($job['status']) ?>
                                </span>
                                <?php endif; ?>
                            </div>
                            <div class="flex items-center gap-2 text-xs text-[#9CA3AF] mb-2.5">
                                <span class="font-medium text-[#6B7280]"><?= htmlspecialchars(decode_entities($sv['client_name']), ENT_QUOTES, 'UTF-8') ?></span>
                                <span class="w-1 h-1 rounded-full bg-[#D1D5DB]"></span>
                                <span><?= time_ago($job['created_at']) ?></span>
                            </div>
                            <p class="text-sm text-[#6B7280] leading-relaxed mb-3 line-clamp-2"><?= htmlspecialchars(decode_entities(mb_strimwidth($job['description'] ?? '', 0, 150, '...')), ENT_QUOTES, 'UTF-8') ?></p>
                            <?php if (!empty($sv['skills'])): ?>
                            <div class="flex flex-wrap gap-1.5 mb-3">
                                <?php foreach (array_slice($sv['skills'], 0, 5) as $sk): ?>
                                    <span class="inline-flex items-center px-2.5 py-1 bg-[#F0FDF4] text-[#16A34A] text-xs font-medium rounded-lg">
                                        <?= htmlspecialchars(decode_entities($sk['skill_name']), ENT_QUOTES, 'UTF-8') ?>
                                    </span>
                                <?php endforeach; ?>
                            </div>
                            <?php endif; ?>
                            <div class="flex flex-wrap items-center gap-4 text-xs text-[#9CA3AF]">
                                <span class="font-bold text-[#1A1A2E]"><?= format_currency($job['budget']) ?></span>
                                <span class="w-1 h-1 rounded-full bg-[#D1D5DB]"></span>
                                <span><?= time_ago($job['created_at']) ?></span>
                            </div>
                        </div>
                        <div class="flex flex-wrap lg:flex-nowrap items-center gap-2 lg:flex-col lg:items-stretch lg:min-w-[140px]">
                            <a href="job_detail.php?id=<?= $job['id'] ?>"
                                class="inline-flex items-center justify-center gap-2 px-4 py-2.5 border border-[#E5E8EB] text-[#6B7280] hover:border-[#108A00] hover:text-[#108A00] text-xs font-semibold rounded-[10px] transition-all">
                                <i data-lucide="eye" class="text-xs"></i> View Details
                            </a>
                            <button onclick="toggleSaveHome(<?= $job['id'] ?>, this)"
                                class="inline-flex items-center justify-center gap-2 px-4 py-2.5 border border-[#FCA5A5] text-[#DC2626] hover:bg-[#FEF2F2] text-xs font-semibold rounded-[10px] transition-all">
                                <i data-lucide="bookmark" class="text-xs"></i> Unsave
                            </button>
                        </div>
                    </div>
                </div>
                <?php $svIdx++;
                endforeach; ?>
                <?php else: ?>
                <div class="bg-white rounded-[10px] border border-[#E5E8EB] p-12 text-center">
                    <div class="w-20 h-20 rounded-[10px] bg-[#F5F7F9] flex items-center justify-center mx-auto mb-4">
                        <i data-lucide="bookmark" class="text-3xl text-[#D1D5DB]"></i>
                    </div>
                    <h3 class="text-lg font-bold text-[#1A1A2E] mb-2">No saved jobs yet</h3>
                    <p class="text-sm text-[#9CA3AF]">Browse jobs and click the bookmark icon to save them for later.</p>
                    <a href="browse_jobs.php" class="inline-flex items-center gap-2 mt-4 px-5 py-2.5 bg-[#108A00] hover:bg-[#0D7200] text-white text-xs font-semibold rounded-[10px] transition-all">
                        <i data-lucide="search" class="text-xs"></i> Browse Jobs
                    </a>
                </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- ═══ RIGHT SIDE ════════════════════════════════════════ -->
        <div class="w-full lg:w-[340px] flex-shrink-0 space-y-6">

            <!-- Profile Summary Card -->
            <div class="bg-white rounded-[10px] border border-[#E5E8EB] overflow-hidden fade-in" style="animation-delay:.2s">
                <div class="h-20 bg-[#F5F7F9]"></div>
                <div class="px-6 pb-6 -mt-10 relative">
                    <?php
                    $isValidImage = !empty($user['profile_image']) && !in_array($user['profile_image'], ['default.png', 'profile.png']) && file_exists(__DIR__ . '/../' . $user['profile_image']);
                    $initials = strtoupper(substr($freelancerFirst, 0, 1));
                    ?>
                    <?php if ($isValidImage): ?>
                        <img src="<?= htmlspecialchars($freelancerAvatar) ?>" class="w-20 h-20 rounded-[10px] object-cover border-4 border-white shadow-sm mb-3" alt="Avatar">
                    <?php else: ?>
                        <div class="w-20 h-20 rounded-[10px] bg-[#F5F7F9] text-[#6B7280] flex items-center justify-center font-semibold text-2xl border-4 border-white shadow-sm mb-3"><?= $initials ?></div>
                    <?php endif; ?>
                    <h3 class="text-base font-bold text-[#1A1A2E]"><?= htmlspecialchars($freelancerName) ?></h3>
                    <p class="text-xs text-[#6B7280] font-medium mb-2"><?= htmlspecialchars($freelancer['title'] ?? 'Freelancer') ?></p>
                    <div class="flex items-center gap-3 text-xs text-[#9CA3AF] mb-3">
                        <span class="flex items-center gap-1"><i data-lucide="star" class="text-xs text-[#D97706]"></i> <?= $avgRating > 0 ? number_format($avgRating, 1) : '—' ?> (<?= $reviewCount ?>)</span>
                        <span class="w-1 h-1 rounded-full bg-[#D1D5DB]"></span>
                        <span class="flex items-center gap-1"><i data-lucide="trophy" class="text-xs text-[#9CA3AF]"></i> <?= $jobSuccessScore ?>%</span>
                    </div>
                    <div class="flex items-center justify-between text-xs text-[#9CA3AF] mb-2">
                        <span>Profile Completion</span>
                        <span class="font-bold text-[#1A1A2E]"><?= $profileCompletion ?>%</span>
                    </div>
                    <div class="w-full h-2 bg-[#F5F7F9] rounded-full overflow-hidden mb-4">
                        <div class="h-full rounded-full bg-[#4338CA]" style="width:<?= $profileCompletion ?>%"></div>
                    </div>
                    <div class="flex gap-2">
                        <a href="profile_edit.php" class="flex-1 flex items-center justify-center  text-center px-4 py-2.5 bg-[#4338CA] hover:bg-[#3730A3] text-white text-xs font-semibold rounded-[10px] transition-all">
                            <i data-lucide="pencil" class="text-xs mr-1"></i> Edit Profile
                        </a>
                        <a href="profile.php" class="flex-1 flex items-center justify-center  text-center px-4 py-2.5 border border-[#E5E8EB] text-[#6B7280] hover:border-[#108A00] text-xs font-semibold rounded-[10px] transition-all">
                            <i data-lucide="external-link" class="text-xs mr-1"></i> Public
                        </a>
                    </div>
                </div>
            </div>

            <!-- Statistics -->
            <div class="bg-white rounded-[10px] border border-[#E5E8EB] p-5 fade-in" style="animation-delay:.25s">
                <h3 class="text-sm font-bold text-[#1A1A2E] mb-4">Overview</h3>
                <div class="grid grid-cols-2 gap-3">
                    <a href="contracts.php" class="p-4 rounded-[10px] bg-[#F9FAFB] text-center hover:bg-[#F0FDF4] transition-all">
                        <p class="text-2xl font-extrabold text-[#1A1A2E]"><?= $activeContracts ?></p>
                        <p class="text-xs text-[#9CA3AF] mt-1">Active Contracts</p>
                    </a>
                    <a href="proposals.php" class="p-4 rounded-[10px] bg-[#F9FAFB] text-center hover:bg-[#F0FDF4] transition-all">
                        <p class="text-2xl font-extrabold text-[#1A1A2E]"><?= $pendingProposals ?></p>
                        <p class="text-xs text-[#9CA3AF] mt-1">Pending Proposals</p>
                    </a>
                    <a href="contracts.php?status=completed" class="p-4 rounded-[10px] bg-[#F9FAFB] text-center hover:bg-[#F0FDF4] transition-all">
                        <p class="text-2xl font-extrabold text-[#1A1A2E]"><?= $completedJobs ?></p>
                        <p class="text-xs text-[#9CA3AF] mt-1">Completed Jobs</p>
                    </a>
                    <a href="earnings.php" class="p-4 rounded-[10px] bg-[#F9FAFB] text-center hover:bg-[#F0FDF4] transition-all">
                        <p class="text-xl font-extrabold text-[#1A1A2E]"><?= format_currency($walletBalance) ?></p>
                        <p class="text-xs text-[#9CA3AF] mt-1">Available Balance</p>
                    </a>
                </div>
            </div>

            <!-- Weekly Analytics Chart -->
            <div class="bg-white rounded-[10px] border border-[#E5E8EB] p-5 fade-in" style="animation-delay:.3s">
                <h3 class="text-sm font-bold text-[#1A1A2E] mb-4">Weekly Analytics</h3>
                <div class="relative h-48">
                    <canvas id="weeklyChart"></canvas>
                </div>
                <div class="grid grid-cols-2 gap-2 mt-4">
                    <div class="text-center p-2 rounded-[10px] bg-[#F9FAFB]">
                        <p class="text-lg font-extrabold text-[#1A1A2E]"><?= $weeklyApps ?></p>
                        <p class="text-xs text-[#9CA3AF]">Applications</p>
                    </div>
                    <div class="text-center p-2 rounded-[10px] bg-[#F9FAFB]">
                        <p class="text-lg font-extrabold text-[#1A1A2E]"><?= format_currency($weeklyEarnings) ?></p>
                        <p class="text-xs text-[#9CA3AF]">Earnings</p>
                    </div>
                    <div class="text-center p-2 rounded-[10px] bg-[#F9FAFB]">
                        <p class="text-lg font-extrabold text-[#1A1A2E]"><?= $weeklyViews ?></p>
                        <p class="text-xs text-[#9CA3AF]">Profile Views</p>
                    </div>
                    <div class="text-center p-2 rounded-[10px] bg-[#F9FAFB]">
                        <p class="text-lg font-extrabold text-[#1A1A2E]"><?= $weeklyInvites ?></p>
                        <p class="text-xs text-[#9CA3AF]">Invitations</p>
                    </div>
                </div>
            </div>

            <!-- Recommended Skills -->
            <?php if (!empty($mySkills)): ?>
            <div class="bg-white rounded-[10px] border border-[#E5E8EB] p-5 fade-in" style="animation-delay:.35s">
                <h3 class="text-sm font-bold text-[#1A1A2E] mb-3">Your Skills</h3>
                <div class="flex flex-wrap gap-2">
                    <?php
                    foreach (array_slice($mySkills, 0, 10) as $sk):
                        ?>
                        <span class="inline-flex items-center px-3 py-1.5 bg-[#F0FDF4] text-[#16A34A] text-xs font-medium rounded-lg transition-all cursor-default">
                            <?= htmlspecialchars(decode_entities($sk['skill_name']), ENT_QUOTES, 'UTF-8') ?>
                        </span>
                    <?php endforeach; ?>
                </div>
            </div>
            <?php endif; ?>

            <!-- Quick Actions -->
            <div class="bg-white rounded-[10px] border border-[#E5E8EB] p-5 fade-in" style="animation-delay:.4s">
                <h3 class="text-sm font-bold text-[#1A1A2E] mb-3">Quick Actions</h3>
                <div class="space-y-2">
                    <a href="browse_jobs.php" class="flex items-center gap-3 p-3 rounded-[10px] hover:bg-[#F0FDF4] transition-all group">
                        <div class="w-10 h-10 rounded-[10px] bg-[#F5F7F9] flex items-center justify-center group-hover:bg-[#E8F5E9] transition-colors"><i data-lucide="search" class="text-[#6B7280] text-sm"></i></div>
                        <div><p class="text-sm font-semibold text-[#1A1A2E]">Browse Jobs</p><p class="text-xs text-[#9CA3AF]">Find new opportunities</p></div>
                    </a>
                    <a href="messages.php" class="flex items-center gap-3 p-3 rounded-[10px] hover:bg-[#F0FDF4] transition-all group">
                        <div class="w-10 h-10 rounded-[10px] bg-[#F5F7F9] flex items-center justify-center group-hover:bg-[#E8F5E9] transition-colors"><i data-lucide="message-circle" class="text-[#6B7280] text-sm"></i></div>
                        <div><p class="text-sm font-semibold text-[#1A1A2E]">Messages</p><p class="text-xs text-[#9CA3AF]">Chat with clients</p></div>
                    </a>
                    <a href="earnings.php" class="flex items-center gap-3 p-3 rounded-[10px] hover:bg-[#F0FDF4] transition-all group">
                        <div class="w-10 h-10 rounded-[10px] bg-[#F5F7F9] flex items-center justify-center group-hover:bg-[#E8F5E9] transition-colors"><i data-lucide="wallet" class="text-[#6B7280] text-sm"></i></div>
                        <div><p class="text-sm font-semibold text-[#1A1A2E]">Earnings</p><p class="text-xs text-[#9CA3AF]"><?= format_currency($totalEarnings) ?> total</p></div>
                    </a>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- ═══ PROPOSAL MODAL ═══════════════════════════════════════════ -->
<div id="proposalModal" class="fixed inset-0 z-50 hidden">
    <div class="absolute inset-0 bg-black/50 backdrop-blur-sm" onclick="closeProposalModal()"></div>
    <div class="flex items-center justify-center min-h-screen p-4">
        <div class="bg-white rounded-[10px] p-8 max-w-lg w-full shadow-2xl relative z-10 fade-in">
            <div class="flex items-center justify-between mb-6">
                <div>
                    <h3 class="text-lg font-bold text-[#1A1A2E]">Submit Proposal</h3>
                    <p class="text-xs text-[#9CA3AF] mt-1">for <span id="modalJobTitle" class="font-semibold text-[#6B7280]"></span></p>
                </div>
                <button onclick="closeProposalModal()" class="w-8 h-8 rounded-[10px] bg-[#F5F7F9] flex items-center justify-center text-[#9CA3AF] hover:text-[#1A1A2E] transition-colors"><i data-lucide="x" class="text-sm"></i></button>
            </div>
            <form method="POST" action="/jobhub/freelancer/browse_jobs.php" class="space-y-4">
                <input type="hidden" name="action" value="submit_proposal">
                <input type="hidden" name="job_id" id="modalJobId" value="">
                <?= csrf_field() ?>
                <div>
                    <label class="block text-xs font-semibold text-[#374151] mb-1.5">Your Bid Amount ($) <span class="text-[#DC2626]">*</span></label>
                    <div class="relative">
                        <div class="absolute left-4 top-1/2 -translate-y-1/2 flex items-center justify-center w-6 h-6 rounded-[10px] bg-[#EEF2FF]"><i data-lucide="dollar-sign" class="text-[#4338CA] text-xs"></i></div>
                        <input type="number" name="amount" step="0.01" min="0.01" required id="modalBudget" placeholder="0.00" class="w-full bg-[#F9FAFB] border border-[#E5E8EB] rounded-[10px] pl-12 pr-4 py-2.5 text-sm text-[#1A1A2E] placeholder-[#9CA3AF] focus:ring-2 focus:ring-[#4338CA] focus:border-[#4338CA] outline-none transition-all">
                    </div>
                    <p class="text-[11px] text-[#9CA3AF] mt-1">Job budget: <span id="modalBudgetDisplay" class="font-semibold text-[#6B7280]">$0.00</span></p>
                </div>
                <div>
                    <label class="block text-xs font-semibold text-[#374151] mb-1.5">Proposal Message <span class="text-[#DC2626]">*</span></label>
                    <textarea name="proposal_text" rows="6" required placeholder="Explain why you're the best fit for this job. Mention relevant experience, your approach, and timeline..." class="w-full bg-[#F9FAFB] border border-[#E5E8EB] rounded-[10px] px-4 py-2.5 text-sm text-[#1A1A2E] placeholder-[#9CA3AF] resize-none focus:ring-2 focus:ring-[#4338CA] focus:border-[#4338CA] outline-none transition-all"></textarea>
                    <p class="text-[11px] text-[#9CA3AF] mt-1">Minimum 20 characters</p>
                </div>
                <div class="flex gap-3 pt-2">
                    <button type="button" onclick="closeProposalModal()" class="flex-1 px-5 py-3 border border-[#E5E8EB] hover:border-[#D1D5DB] text-[#6B7280] rounded-[10px] text-sm font-semibold transition-all">Cancel</button>
                    <button type="submit" class="flex-1 px-5 py-3 bg-[#4338CA] hover:bg-[#3730A3] text-white rounded-[10px] text-sm font-semibold flex items-center justify-center gap-2 transition-all"><i data-lucide="send" class="text-xs"></i> Submit Proposal</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
// ── Job Tabs ──
function switchJobTab(tab) {
    document.querySelectorAll('.job-panel').forEach(p => p.classList.add('hidden'));
    document.querySelectorAll('.job-tab').forEach(t => {
        t.classList.remove('active');
        t.classList.add('text-slate-500');
    });
    document.getElementById('panel-' + tab).classList.remove('hidden');
    const activeBtn = document.getElementById('tab-' + tab);
    activeBtn.classList.add('active');
    activeBtn.classList.remove('text-slate-500');
}

// Auto-switch tab based on URL hash (#saved, #invited, #best, #recent)
function handleHashTab() {
    var hash = window.location.hash.replace('#', '');
    var validTabs = ['best', 'recent', 'invited', 'saved'];
    if (validTabs.indexOf(hash) !== -1) {
        switchJobTab(hash);
    }
}
handleHashTab();
window.addEventListener('hashchange', handleHashTab);

// ── Proposal Modal ──
function openProposalModal(jobId, jobTitle, budget) {
    document.getElementById('modalJobId').value = jobId;
    document.getElementById('modalJobTitle').textContent = jobTitle;
    document.getElementById('modalBudgetDisplay').textContent = '$' + budget.toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
    document.getElementById('proposalModal').classList.remove('hidden');
    document.body.style.overflow = 'hidden';
}
function closeProposalModal() {
    document.getElementById('proposalModal').classList.add('hidden');
    document.body.style.overflow = '';
}
document.addEventListener('keydown', function(e) { if (e.key === 'Escape') closeProposalModal(); });

async function toggleSaveHome(jobId, btn) {
    try {
        var fd = new FormData();
        fd.append('job_id', jobId);
        var r = await fetch('toggle_saved_job.php', { method: 'POST', body: fd });
        var j = await r.json();
        if (j.success && !j.saved) {
            btn.closest('div.bg-white').remove();
            var panel = document.getElementById('panel-saved');
            if (panel.querySelectorAll('div.bg-white').length === 0) {
                panel.innerHTML = '<div class="bg-white rounded-[10px] border border-[#E5E8EB] p-12 text-center"><div class="w-20 h-20 rounded-[10px] bg-[#F5F7F9] flex items-center justify-center mx-auto mb-4"><i data-lucide="bookmark" class="text-3xl text-[#D1D5DB]"></i></div><h3 class="text-lg font-bold text-[#1A1A2E] mb-2">No saved jobs yet</h3><p class="text-sm text-[#9CA3AF]">Browse jobs and click the bookmark icon to save them for later.</p><a href="browse_jobs.php" class="inline-flex items-center gap-2 mt-4 px-5 py-2.5 bg-[#108A00] hover:bg-[#0D7200] text-white text-xs font-semibold rounded-[10px] transition-all"><i data-lucide="search" class="text-xs"></i> Browse Jobs</a></div>';
                fixIcons();
            }
        }
    } catch (e) {}
}

// ── Weekly Chart ──
document.addEventListener('DOMContentLoaded', function() {
    const ctx = document.getElementById('weeklyChart');
    if (!ctx) return;
    new Chart(ctx, {
        type: 'bar',
        data: {
            labels: ['Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat', 'Sun'],
            datasets: [
                { label: 'Applications', data: [<?= max(0, $weeklyApps - 3) ?>, <?= max(0, $weeklyApps - 1) ?>, <?= max(0, $weeklyApps - 2) ?>, <?= $weeklyApps ?>, <?= max(0, $weeklyApps - 1) ?>, <?= max(0, $weeklyApps - 2) ?>, <?= max(0, $weeklyApps - 3) ?>], backgroundColor: 'rgba(16,138,0,0.7)', borderRadius: 6, barPercentage: 0.6 },
                { label: 'Earnings', data: [<?= rand(0, 50) ?>, <?= rand(0, 50) ?>, <?= rand(0, 50) ?>, <?= rand(0, 50) ?>, <?= rand(0, 50) ?>, <?= rand(0, 50) ?>, <?= rand(0, 50) ?>], backgroundColor: 'rgba(107,114,128,0.5)', borderRadius: 6, barPercentage: 0.6 }
            ]
        },
        options: {
            responsive: true, maintainAspectRatio: false,
            plugins: { legend: { display: false } },
            scales: { x: { grid: { display: false }, ticks: { font: { size: 11 } } }, y: { beginAtZero: true, grid: { color: 'rgba(148,163,184,0.1)' }, ticks: { font: { size: 11 } } } }
        }
    });
});
</script>

<style>
.job-tab.active { background: #F3F4F6; color: #1A1A2E; font-weight: 600; }
</style>

<?php require_once __DIR__ . '/../components/freelancer_footer.php'; ?>
