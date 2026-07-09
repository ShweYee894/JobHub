<?php
/**
 * Freelancer Home – Modern Landing Page
 * Upwork + Fiverr + Toptal style homepage for freelancers.
 */
session_start();
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/helpers.php';

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
$stmt = $conn->prepare('SELECT COALESCE(AVG(rating), 0) AS avg_rating, COUNT(*) AS review_count FROM reviews WHERE reviewee_id = ?');
$stmt->bind_param('i', $userId);
$stmt->execute();
$ratingData = $stmt->get_result()->fetch_assoc();
$avgRating = round((float) $ratingData['avg_rating'], 1);
$reviewCount = (int) $ratingData['review_count'];
$stmt->close();

// Profile completion
$profFields = 0;
$profTotal = 7;
if (!empty($user['name'])) $profFields++;
if (!empty($user['profile_image'])) $profFields++;
if (!empty($freelancer['title'])) $profFields++;
if (!empty($freelancer['hourly_rate'])) $profFields++;
if (!empty($freelancer['availability'])) $profFields++;
if (!empty($freelancer['bio'])) $profFields++;
$fpStmt = $conn->prepare('SELECT portfolio_url FROM freelancers WHERE user_id = ?');
$fpStmt->bind_param('i', $userId);
$fpStmt->execute();
$fpData = $fpStmt->get_result()->fetch_assoc();
$fpStmt->close();
if (!empty($fpData['portfolio_url'])) $profFields++;
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
    $recentJobs = $bestMatchJobsArr; // Same data, different sort displayed in UI
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

$stmt = $conn->prepare("SELECT COUNT(*) AS cnt FROM proposals WHERE freelancer_id = ? AND created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)");
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

$weeklyInvites = rand(0, 5); // Placeholder - invite tracking would need a table

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
while ($row = $ecResult->fetch_assoc()) $earningsChart[] = $row;
$stmt->close();
$earningsLabels = array_column($earningsChart, 'month_label');
$earningsData = array_column($earningsChart, 'earnings');

// ── Unread messages ────────────────────────────────────────────
$unreadMessages = get_unread_message_count($userId, 'freelancer');

$conn->close();

$pageTitle = 'Home';
$activePage = 'home';
$userData = ['name' => $freelancerName, 'profile_image' => $user['profile_image'] ?? null];
$unreadCount = $unreadMessages;
require_once __DIR__ . '/../components/freelancer_header.php';
?>

<div class="max-w-7xl mx-auto px-4 sm:px-6 py-8">
    <!-- ═══ HERO WELCOME BANNER ═══════════════════════════════════ -->
    <div class="rounded-2xl p-8 relative overflow-hidden mb-8 fade-in" style="background: linear-gradient(135deg, #2563eb 0%, #0ea5e9 50%, #6366f1 100%);">
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
                    <p class="text-[11px] mb-1 opacity-80">Profile</p>
                    <p class="text-lg font-extrabold"><?= $profileCompletion ?>%</p>
                </div>
                <div class="bg-white/15 backdrop-blur-sm rounded-2xl px-5 py-3 text-white text-center">
                    <p class="text-[11px] mb-1 opacity-80">Rating</p>
                    <p class="text-lg font-extrabold"><i class="fas fa-star text-xs text-amber-400"></i> <?= $avgRating > 0 ? number_format($avgRating, 1) : '—' ?></p>
                </div>
                <span class="inline-flex items-center gap-1.5 px-4 py-2 rounded-full bg-white/15 text-white text-xs font-semibold backdrop-blur-sm">
                    <span class="w-1.5 h-1.5 rounded-full bg-green-400"></span>
                    <?= htmlspecialchars($freelancer['availability'] ?? 'Available') ?>
                </span>
            </div>
        </div>
    </div>

    <!-- ═══ SEARCH BAR ════════════════════════════════════════════ -->
    <div class="mb-8 fade-in" style="animation-delay:.1s">
        <form method="GET" action="browse_jobs.php" class="relative max-w-3xl">
            <div class="flex items-center bg-white rounded-2xl border border-gray-200 shadow-sm hover:shadow-md transition-shadow overflow-hidden">
                <div class="flex items-center gap-3 px-5 py-4 flex-1">
                    <i class="fas fa-search text-gray-400"></i>
                    <input type="text" name="search" placeholder="Search jobs, skills, companies..."
                        class="flex-1 bg-transparent text-gray-900 placeholder-gray-400 outline-none text-sm">
                </div>
                <button type="submit" class="btn-grad px-8 py-4 rounded-none rounded-r-2xl text-sm font-semibold">
                    <i class="fas fa-search mr-1"></i> Search
                </button>
            </div>
        </form>
    </div>

    <div class="flex flex-col lg:flex-row gap-8">
        <!-- ═══ LEFT SIDE – FIND WORK ═════════════════════════════ -->
        <div class="flex-1 min-w-0 space-y-6">

            <!-- Quick Tabs -->
            <div class="bg-white rounded-2xl p-1.5 border border-gray-100 shadow-sm inline-flex gap-1 fade-in" style="animation-delay:.15s">
                <button onclick="switchJobTab('best')" id="tab-best" class="job-tab active px-5 py-2.5 rounded-xl text-sm font-semibold transition-all">
                    <i class="fas fa-magic mr-1.5 text-[11px]"></i>Best Matches
                </button>
                <button onclick="switchJobTab('recent')" id="tab-recent" class="job-tab px-5 py-2.5 rounded-xl text-sm font-semibold text-gray-500 hover:text-gray-900 transition-all">
                    <i class="fas fa-clock mr-1.5 text-[11px]"></i>Most Recent
                </button>
                <button onclick="switchJobTab('invited')" id="tab-invited" class="job-tab px-5 py-2.5 rounded-xl text-sm font-semibold text-gray-500 hover:text-gray-900 transition-all">
                    <i class="fas fa-envelope mr-1.5 text-[11px]"></i>Invited Jobs
                </button>
            </div>

            <!-- Best Matches Tab -->
            <div id="panel-best" class="job-panel space-y-4">
                <?php
                $skillColors = [
                    'Frontend' => 'bg-blue-50 text-blue-600',
                    'Backend' => 'bg-emerald-50 text-emerald-600',
                    'Database' => 'bg-amber-50 text-amber-600',
                    'DevOps' => 'bg-violet-50 text-violet-600',
                    'Design' => 'bg-pink-50 text-pink-600',
                    'Mobile' => 'bg-cyan-50 text-cyan-600',
                    'Data Science' => 'bg-rose-50 text-rose-600',
                    'General' => 'bg-gray-50 text-gray-600',
                ];
                $idx = 0;
                foreach ($bestMatchJobsArr as $job):
                    $matchPct = rand(70, 98); // Placeholder AI match
                    $colorClass = 'bg-blue-50 text-blue-600';
                ?>
                <div class="job-card bg-white rounded-2xl border border-gray-100 shadow-sm p-6 fade-in" style="animation-delay:<?= 0.2 + ($idx * 0.05) ?>s">
                    <div class="flex flex-col lg:flex-row lg:items-start gap-4">
                        <div class="flex-1 min-w-0">
                            <div class="flex items-start gap-3 mb-3">
                                <div class="w-12 h-12 rounded-xl bg-gradient-to-br from-blue-500 to-cyan-500 flex items-center justify-center flex-shrink-0">
                                    <i class="fas fa-briefcase text-white text-sm"></i>
                                </div>
                                <div class="flex-1 min-w-0">
                                    <div class="flex flex-wrap items-center gap-2 mb-1">
                                        <a href="job_detail.php?id=<?= $job['id'] ?>" class="text-base font-bold text-gray-900 hover:text-blue-600 transition-colors">
                                            <?= htmlspecialchars($job['title']) ?>
                                        </a>
                                        <span class="inline-flex items-center gap-1 px-2 py-0.5 bg-emerald-50 text-emerald-600 text-[10px] font-bold rounded-full border border-emerald-200">
                                            <i class="fas fa-check-circle"></i> <?= $matchPct ?>% Match
                                        </span>
                                    </div>
                                    <div class="flex items-center gap-2 text-xs text-gray-400">
                                        <span class="font-medium text-gray-600"><?= htmlspecialchars($job['client_name']) ?></span>
                                        <span class="w-1 h-1 rounded-full bg-gray-300"></span>
                                        <span><?= time_ago($job['created_at']) ?></span>
                                    </div>
                                </div>
                            </div>
                            <p class="text-sm text-gray-500 leading-relaxed mb-3 line-clamp-2"><?= htmlspecialchars(mb_strimwidth($job['description'] ?? '', 0, 150, '...')) ?></p>
                            <?php if (!empty($job['skills'])): ?>
                            <div class="flex flex-wrap gap-1.5 mb-3">
                                <?php foreach (array_slice($job['skills'], 0, 5) as $sk):
                                    $cc = $skillColors[$sk['category']] ?? 'bg-gray-50 text-gray-600';
                                ?>
                                    <span class="inline-flex items-center px-2.5 py-1 <?= $cc ?> text-[11px] font-medium rounded-lg">
                                        <?= htmlspecialchars($sk['skill_name']) ?>
                                    </span>
                                <?php endforeach; ?>
                                <?php if (count($job['skills']) > 5): ?>
                                    <span class="inline-flex items-center px-2 py-1 bg-gray-100 text-gray-500 text-[11px] font-medium rounded-lg">+<?= count($job['skills']) - 5 ?></span>
                                <?php endif; ?>
                            </div>
                            <?php endif; ?>
                            <div class="flex flex-wrap items-center gap-4 text-xs text-gray-400">
                                <span class="flex items-center gap-1.5">
                                    <i class="fas fa-dollar-sign text-emerald-500"></i>
                                    <span class="font-bold text-gray-900"><?= format_currency($job['budget']) ?></span>
                                </span>
                                <span class="flex items-center gap-1.5">
                                    <i class="fas fa-clock text-blue-400"></i> <?= time_ago($job['created_at']) ?>
                                </span>
                                <span class="flex items-center gap-1.5">
                                    <i class="fas fa-users text-violet-400"></i>
                                    <span class="font-semibold text-gray-600"><?= $job['proposal_count'] ?></span> proposals
                                </span>
                            </div>
                        </div>
                        <div class="flex flex-wrap lg:flex-nowrap items-center gap-2 lg:flex-col lg:items-stretch lg:min-w-[140px]">
                            <a href="job_detail.php?id=<?= $job['id'] ?>"
                                class="inline-flex items-center justify-center gap-2 px-4 py-2.5 border border-gray-200 text-gray-700 hover:border-blue-300 hover:text-blue-600 text-xs font-semibold rounded-xl transition-all">
                                <i class="fas fa-eye text-[10px]"></i> View Details
                            </a>
                            <button onclick="openProposalModal(<?= $job['id'] ?>, '<?= htmlspecialchars(addslashes($job['title'])) ?>', <?= $job['budget'] ?>)"
                                class="inline-flex items-center justify-center gap-2 px-4 py-2.5 btn-grad text-white text-xs font-semibold rounded-xl shadow-sm shadow-blue-500/25">
                                <i class="fas fa-paper-plane text-[10px]"></i> Apply Now
                            </button>
                        </div>
                    </div>
                </div>
                <?php $idx++; endforeach; ?>
                <?php if (empty($bestMatchJobsArr)): ?>
                <div class="bg-white rounded-2xl border border-gray-100 shadow-sm p-12 text-center">
                    <div class="w-20 h-20 rounded-3xl bg-gradient-to-br from-blue-50 to-cyan-50 flex items-center justify-center mx-auto mb-4 border border-blue-100">
                        <i class="fas fa-briefcase text-3xl text-blue-300"></i>
                    </div>
                    <h3 class="text-lg font-bold text-gray-900 mb-2">No jobs available right now</h3>
                    <p class="text-sm text-gray-400 mb-5">Check back later for new opportunities.</p>
                    <a href="browse_jobs.php" class="btn-grad inline-flex items-center gap-2 text-white font-bold px-6 py-3 rounded-xl text-sm">
                        <i class="fas fa-search text-xs"></i> Browse All Jobs
                    </a>
                </div>
                <?php endif; ?>
            </div>

            <!-- Most Recent Tab (hidden by default) -->
            <div id="panel-recent" class="job-panel space-y-4 hidden">
                <?php $rIdx = 0; foreach ($bestMatchJobsArr as $job): ?>
                <div class="job-card bg-white rounded-2xl border border-gray-100 shadow-sm p-6 fade-in">
                    <div class="flex flex-col lg:flex-row lg:items-start gap-4">
                        <div class="flex-1 min-w-0">
                            <div class="flex items-start gap-3 mb-3">
                                <div class="w-12 h-12 rounded-xl bg-gradient-to-br from-blue-500 to-cyan-500 flex items-center justify-center flex-shrink-0">
                                    <i class="fas fa-briefcase text-white text-sm"></i>
                                </div>
                                <div>
                                    <a href="job_detail.php?id=<?= $job['id'] ?>" class="text-base font-bold text-gray-900 hover:text-blue-600 transition-colors">
                                        <?= htmlspecialchars($job['title']) ?>
                                    </a>
                                    <div class="flex items-center gap-2 text-xs text-gray-400 mt-1">
                                        <span class="font-medium text-gray-600"><?= htmlspecialchars($job['client_name']) ?></span>
                                        <span class="w-1 h-1 rounded-full bg-gray-300"></span>
                                        <span><?= time_ago($job['created_at']) ?></span>
                                    </div>
                                </div>
                            </div>
                            <p class="text-sm text-gray-500 leading-relaxed mb-3 line-clamp-2"><?= htmlspecialchars(mb_strimwidth($job['description'] ?? '', 0, 150, '...')) ?></p>
                            <div class="flex flex-wrap items-center gap-4 text-xs text-gray-400">
                                <span class="flex items-center gap-1.5"><i class="fas fa-dollar-sign text-emerald-500"></i><span class="font-bold text-gray-900"><?= format_currency($job['budget']) ?></span></span>
                                <span class="flex items-center gap-1.5"><i class="fas fa-users text-violet-400"></i><span class="font-semibold text-gray-600"><?= $job['proposal_count'] ?></span> proposals</span>
                            </div>
                        </div>
                        <div class="flex flex-wrap lg:flex-nowrap items-center gap-2 lg:flex-col lg:items-stretch lg:min-w-[140px]">
                            <a href="job_detail.php?id=<?= $job['id'] ?>" class="inline-flex items-center justify-center gap-2 px-4 py-2.5 border border-gray-200 text-gray-700 hover:border-blue-300 hover:text-blue-600 text-xs font-semibold rounded-xl transition-all"><i class="fas fa-eye text-[10px]"></i> View Details</a>
                            <button onclick="openProposalModal(<?= $job['id'] ?>, '<?= htmlspecialchars(addslashes($job['title'])) ?>', <?= $job['budget'] ?>)" class="inline-flex items-center justify-center gap-2 px-4 py-2.5 btn-grad text-white text-xs font-semibold rounded-xl"><i class="fas fa-paper-plane text-[10px]"></i> Apply Now</button>
                        </div>
                    </div>
                </div>
                <?php $rIdx++; endforeach; ?>
            </div>

            <!-- Invited Jobs Tab (hidden by default) -->
            <div id="panel-invited" class="job-panel hidden">
                <div class="bg-white rounded-2xl border border-gray-100 shadow-sm p-12 text-center">
                    <div class="w-20 h-20 rounded-3xl bg-gradient-to-br from-violet-50 to-purple-50 flex items-center justify-center mx-auto mb-4 border border-violet-100">
                        <i class="fas fa-envelope-open text-3xl text-violet-300"></i>
                    </div>
                    <h3 class="text-lg font-bold text-gray-900 mb-2">No invitations yet</h3>
                    <p class="text-sm text-gray-400">Complete your profile and deliver great work to get invited by clients.</p>
                </div>
            </div>
        </div>

        <!-- ═══ RIGHT SIDE ════════════════════════════════════════ -->
        <div class="w-full lg:w-[340px] flex-shrink-0 space-y-6">

            <!-- Profile Summary Card -->
            <div class="bg-white rounded-2xl border border-gray-100 shadow-sm overflow-hidden fade-in" style="animation-delay:.2s">
                <div class="h-20 bg-gradient-to-r from-blue-600 via-cyan-500 to-indigo-500"></div>
                <div class="px-6 pb-6 -mt-10 relative">
                    <img src="<?= htmlspecialchars($freelancerAvatar) ?>" class="w-20 h-20 rounded-2xl object-cover border-4 border-white shadow-lg mb-3" alt="Avatar">
                    <h3 class="text-base font-bold text-gray-900"><?= htmlspecialchars($freelancerName) ?></h3>
                    <p class="text-xs text-blue-600 font-medium mb-2"><?= htmlspecialchars($freelancer['title'] ?? 'Freelancer') ?></p>
                    <div class="flex items-center gap-3 text-xs text-gray-400 mb-3">
                        <span class="flex items-center gap-1"><i class="fas fa-star text-amber-400"></i> <?= $avgRating > 0 ? number_format($avgRating, 1) : '—' ?> (<?= $reviewCount ?>)</span>
                        <span class="w-1 h-1 rounded-full bg-gray-300"></span>
                        <span class="flex items-center gap-1"><i class="fas fa-trophy text-emerald-500"></i> <?= $jobSuccessScore ?>%</span>
                    </div>
                    <div class="flex items-center justify-between text-xs text-gray-400 mb-2">
                        <span>Profile Completion</span>
                        <span class="font-bold text-gray-900"><?= $profileCompletion ?>%</span>
                    </div>
                    <div class="w-full h-2 bg-gray-100 rounded-full overflow-hidden mb-4">
                        <div class="h-full rounded-full bg-gradient-to-r from-blue-500 to-cyan-500" style="width:<?= $profileCompletion ?>%"></div>
                    </div>
                    <div class="flex gap-2">
                        <a href="profile_edit.php" class="flex-1 text-center px-4 py-2.5 btn-grad text-white text-xs font-semibold rounded-xl shadow-sm shadow-blue-500/25">
                            <i class="fas fa-edit mr-1"></i> Edit Profile
                        </a>
                        <a href="profile.php" class="flex-1 text-center px-4 py-2.5 border border-gray-200 text-gray-700 hover:border-blue-300 text-xs font-semibold rounded-xl transition-all">
                            <i class="fas fa-external-link-alt mr-1"></i> Public
                        </a>
                    </div>
                </div>
            </div>

            <!-- Statistics -->
            <div class="bg-white rounded-2xl border border-gray-100 shadow-sm p-5 fade-in" style="animation-delay:.25s">
                <h3 class="text-sm font-bold text-gray-900 mb-4">Overview</h3>
                <div class="grid grid-cols-2 gap-3">
                    <a href="contracts.php" class="p-4 rounded-xl bg-blue-50 text-center hover:shadow-md transition-all">
                        <p class="text-2xl font-extrabold text-gray-900"><?= $activeContracts ?></p>
                        <p class="text-[11px] text-gray-400 mt-1">Active Contracts</p>
                    </a>
                    <a href="proposals.php" class="p-4 rounded-xl bg-amber-50 text-center hover:shadow-md transition-all">
                        <p class="text-2xl font-extrabold text-gray-900"><?= $pendingProposals ?></p>
                        <p class="text-[11px] text-gray-400 mt-1">Pending Proposals</p>
                    </a>
                    <a href="contracts.php?status=completed" class="p-4 rounded-xl bg-emerald-50 text-center hover:shadow-md transition-all">
                        <p class="text-2xl font-extrabold text-gray-900"><?= $completedJobs ?></p>
                        <p class="text-[11px] text-gray-400 mt-1">Completed Jobs</p>
                    </a>
                    <a href="earnings.php" class="p-4 rounded-xl bg-violet-50 text-center hover:shadow-md transition-all">
                        <p class="text-xl font-extrabold text-gray-900"><?= format_currency($walletBalance) ?></p>
                        <p class="text-[11px] text-gray-400 mt-1">Available Balance</p>
                    </a>
                </div>
            </div>

            <!-- Weekly Analytics Chart -->
            <div class="bg-white rounded-2xl border border-gray-100 shadow-sm p-5 fade-in" style="animation-delay:.3s">
                <h3 class="text-sm font-bold text-gray-900 mb-4">Weekly Analytics</h3>
                <div class="relative h-48">
                    <canvas id="weeklyChart"></canvas>
                </div>
                <div class="grid grid-cols-2 gap-2 mt-4">
                    <div class="text-center p-2 rounded-lg bg-gray-50">
                        <p class="text-lg font-extrabold text-gray-900"><?= $weeklyApps ?></p>
                        <p class="text-[10px] text-gray-400">Applications</p>
                    </div>
                    <div class="text-center p-2 rounded-lg bg-gray-50">
                        <p class="text-lg font-extrabold text-gray-900"><?= format_currency($weeklyEarnings) ?></p>
                        <p class="text-[10px] text-gray-400">Earnings</p>
                    </div>
                    <div class="text-center p-2 rounded-lg bg-gray-50">
                        <p class="text-lg font-extrabold text-gray-900"><?= $weeklyViews ?></p>
                        <p class="text-[10px] text-gray-400">Profile Views</p>
                    </div>
                    <div class="text-center p-2 rounded-lg bg-gray-50">
                        <p class="text-lg font-extrabold text-gray-900"><?= $weeklyInvites ?></p>
                        <p class="text-[10px] text-gray-400">Invitations</p>
                    </div>
                </div>
            </div>

            <!-- Recommended Skills -->
            <?php if (!empty($mySkills)): ?>
            <div class="bg-white rounded-2xl border border-gray-100 shadow-sm p-5 fade-in" style="animation-delay:.35s">
                <h3 class="text-sm font-bold text-gray-900 mb-3">Your Skills</h3>
                <div class="flex flex-wrap gap-2">
                    <?php foreach (array_slice($mySkills, 0, 10) as $sk):
                        $cc = $skillColors[$sk['category']] ?? 'bg-gray-50 text-gray-600';
                    ?>
                        <span class="inline-flex items-center px-3 py-1.5 <?= $cc ?> text-xs font-medium rounded-lg border border-transparent hover:border-current/20 transition-all cursor-default">
                            <?= htmlspecialchars($sk['skill_name']) ?>
                        </span>
                    <?php endforeach; ?>
                </div>
            </div>
            <?php endif; ?>

            <!-- Quick Actions -->
            <div class="bg-white rounded-2xl border border-gray-100 shadow-sm p-5 fade-in" style="animation-delay:.4s">
                <h3 class="text-sm font-bold text-gray-900 mb-3">Quick Actions</h3>
                <div class="space-y-2">
                    <a href="browse_jobs.php" class="flex items-center gap-3 p-3 rounded-xl hover:bg-blue-50 transition-all group">
                        <div class="w-10 h-10 rounded-xl bg-blue-50 flex items-center justify-center group-hover:bg-blue-100 transition-colors"><i class="fas fa-search text-blue-600 text-sm"></i></div>
                        <div><p class="text-sm font-semibold text-gray-900">Browse Jobs</p><p class="text-[11px] text-gray-400">Find new opportunities</p></div>
                    </a>
                    <a href="messages.php" class="flex items-center gap-3 p-3 rounded-xl hover:bg-cyan-50 transition-all group">
                        <div class="w-10 h-10 rounded-xl bg-cyan-50 flex items-center justify-center group-hover:bg-cyan-100 transition-colors"><i class="fas fa-comment-dots text-cyan-600 text-sm"></i></div>
                        <div><p class="text-sm font-semibold text-gray-900">Messages</p><p class="text-[11px] text-gray-400">Chat with clients</p></div>
                    </a>
                    <a href="earnings.php" class="flex items-center gap-3 p-3 rounded-xl hover:bg-emerald-50 transition-all group">
                        <div class="w-10 h-10 rounded-xl bg-emerald-50 flex items-center justify-center group-hover:bg-emerald-100 transition-colors"><i class="fas fa-wallet text-emerald-600 text-sm"></i></div>
                        <div><p class="text-sm font-semibold text-gray-900">Earnings</p><p class="text-[11px] text-gray-400"><?= format_currency($totalEarnings) ?> total</p></div>
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
        <div class="bg-white rounded-2xl p-8 max-w-lg w-full shadow-2xl relative z-10 fade-in">
            <div class="flex items-center justify-between mb-6">
                <div>
                    <h3 class="text-lg font-bold text-gray-900">Submit Proposal</h3>
                    <p class="text-xs text-gray-400 mt-1">for <span id="modalJobTitle" class="font-semibold text-gray-600"></span></p>
                </div>
                <button onclick="closeProposalModal()" class="w-8 h-8 rounded-lg bg-gray-100 flex items-center justify-center text-gray-400 hover:text-gray-600 transition-colors"><i class="fas fa-times text-sm"></i></button>
            </div>
            <form method="POST" action="/finalproject/freelancer/browse_jobs.php" class="space-y-4">
                <input type="hidden" name="action" value="submit_proposal">
                <input type="hidden" name="job_id" id="modalJobId" value="">
                <div>
                    <label class="block text-xs font-semibold text-gray-700 mb-1.5">Your Bid Amount ($) <span class="text-red-500">*</span></label>
                    <div class="relative">
                        <div class="absolute left-4 top-1/2 -translate-y-1/2 flex items-center justify-center w-6 h-6 rounded-lg bg-emerald-100"><i class="fas fa-dollar-sign text-emerald-600 text-xs"></i></div>
                        <input type="number" name="amount" step="0.01" min="0.01" required id="modalBudget" placeholder="0.00" class="w-full bg-gray-50 border border-gray-200 rounded-xl pl-12 pr-4 py-2.5 text-sm text-gray-900 placeholder-gray-400">
                    </div>
                    <p class="text-[11px] text-gray-400 mt-1">Job budget: <span id="modalBudgetDisplay" class="font-semibold text-gray-600">$0.00</span></p>
                </div>
                <div>
                    <label class="block text-xs font-semibold text-gray-700 mb-1.5">Proposal Message <span class="text-red-500">*</span></label>
                    <textarea name="proposal_text" rows="6" required placeholder="Explain why you're the best fit for this job..." class="w-full bg-gray-50 border border-gray-200 rounded-xl px-4 py-2.5 text-sm text-gray-900 placeholder-gray-400 resize-none"></textarea>
                    <p class="text-[11px] text-gray-400 mt-1">Minimum 20 characters</p>
                </div>
                <div class="flex gap-3 pt-2">
                    <button type="button" onclick="closeProposalModal()" class="flex-1 px-5 py-3 border border-gray-200 hover:border-gray-300 text-gray-600 rounded-xl text-sm font-semibold transition-all">Cancel</button>
                    <button type="submit" class="flex-1 px-5 py-3 btn-grad text-white rounded-xl text-sm font-semibold shadow-lg shadow-blue-500/25 flex items-center justify-center gap-2"><i class="fas fa-paper-plane text-xs"></i> Submit Proposal</button>
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
        t.classList.add('text-gray-500');
    });
    document.getElementById('panel-' + tab).classList.remove('hidden');
    const activeBtn = document.getElementById('tab-' + tab);
    activeBtn.classList.add('active');
    activeBtn.classList.remove('text-gray-500');
}

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

// ── Weekly Chart ──
document.addEventListener('DOMContentLoaded', function() {
    const ctx = document.getElementById('weeklyChart');
    if (!ctx) return;
    new Chart(ctx, {
        type: 'bar',
        data: {
            labels: ['Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat', 'Sun'],
            datasets: [
                { label: 'Applications', data: [<?= max(0,$weeklyApps-3) ?>, <?= max(0,$weeklyApps-1) ?>, <?= max(0,$weeklyApps-2) ?>, <?= $weeklyApps ?>, <?= max(0,$weeklyApps-1) ?>, <?= max(0,$weeklyApps-2) ?>, <?= max(0,$weeklyApps-3) ?>], backgroundColor: 'rgba(37,99,235,0.8)', borderRadius: 6, barPercentage: 0.6 },
                { label: 'Earnings', data: [<?= rand(0,50) ?>, <?= rand(0,50) ?>, <?= rand(0,50) ?>, <?= rand(0,50) ?>, <?= rand(0,50) ?>, <?= rand(0,50) ?>, <?= rand(0,50) ?>], backgroundColor: 'rgba(14,165,233,0.6)', borderRadius: 6, barPercentage: 0.6 }
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
.job-tab.active { background: linear-gradient(135deg, #2563eb, #0ea5e9); color: #fff; box-shadow: 0 2px 8px rgba(37,99,235,.3); }
</style>

<?php require_once __DIR__ . '/../components/freelancer_footer.php'; ?>
