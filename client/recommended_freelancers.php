<?php
session_start();
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../auth/auth.php';
require_once __DIR__ . '/../includes/ai_engine.php';
require_role('client');

$currentPage = 'recommended_freelancers';

$userId = $_SESSION['user_id'];

// Get user info
$stmt = $conn->prepare('SELECT name, profile_image FROM users WHERE id = ?');
$stmt->bind_param('i', $userId);
$stmt->execute();
$user = $stmt->get_result()->fetch_assoc();
$stmt->close();

// Get client's jobs for dropdown
$stmt = $conn->prepare('SELECT id, title FROM jobs WHERE client_id = ? ORDER BY created_at DESC');
$stmt->bind_param('i', $userId);
$stmt->execute();
$client_jobs = $stmt->get_result();
$stmt->close();

$job_id = sanitize_int($_GET['job_id'] ?? 0);
$recommended = [];

if ($job_id) {
    // Verify job belongs to client
    $stmt = $conn->prepare('SELECT id, title, description, budget, embedding_vector FROM jobs WHERE id = ? AND client_id = ?');
    $stmt->bind_param('ii', $job_id, $userId);
    $stmt->execute();
    $job = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if ($job) {
        // Load or generate job embedding using ai_engine
        $embedding = json_decode($job['embedding_vector'] ?? '{}', true) ?: [];

        if (empty($embedding)) {
            $s2 = $conn->prepare('SELECT skill_id FROM job_skills WHERE job_id = ?');
            $s2->bind_param('i', $job_id);
            $s2->execute();
            $r2 = $s2->get_result();
            $job_skill_ids = [];
            while ($row = $r2->fetch_assoc()) {
                $job_skill_ids[] = (int) $row['skill_id'];
            }
            $s2->close();
            $embedding_json = ai_generate_job_embedding($job['title'], $job['description'] ?? $job['title'], $job_skill_ids, (float) $job['budget']);
            $embedding = json_decode($embedding_json, true);
        }

        $job_budget = (float) $job['budget'];

        $fl_stmt = $conn->prepare('
            SELECT f.id, f.user_id, f.title, f.skills_vector, f.hourly_rate, f.years_of_experience, f.availability,
                   u.name, u.profile_image
            FROM freelancers f
            JOIN users u ON f.user_id = u.id
        ');
        $fl_stmt->execute();
        $freelancers = $fl_stmt->get_result();
        $fl_stmt->close();

        while ($fl = $freelancers->fetch_assoc()) {
            $vector = json_decode($fl['skills_vector'] ?? '{}', true) ?: [];

            if (empty($vector)) {
                $s3 = $conn->prepare('
                    SELECT fs.skill_id, s.skill_name
                    FROM freelancer_skills fs
                    JOIN skills s ON fs.skill_id = s.id
                    WHERE fs.freelancer_id = ?
                ');
                $s3->bind_param('i', $fl['id']);
                $s3->execute();
                $r3 = $s3->get_result();
                $fl_skill_ids = [];
                $fl_skill_names = [];
                while ($row = $r3->fetch_assoc()) {
                    $fl_skill_ids[] = (int) $row['skill_id'];
                    $fl_skill_names[] = $row['skill_name'];
                }
                $s3->close();

                $flVectorJson = ai_generate_freelancer_vector(
                    $fl_skill_ids,
                    $fl_skill_names,
                    (float) $fl['hourly_rate'],
                    (int) $fl['years_of_experience'],
                    $fl['availability'] ?? 'Available',
                    ($fl['title'] ?? '') . ' ' . implode(' ', $fl_skill_names)
                );
                $vector = json_decode($flVectorJson, true);
            }

            $scoreResult = ai_score_freelancer_for_job($embedding, $vector, $job_budget);

            if ($scoreResult['total_score'] <= 0) continue;

            $recommended[] = [
                'freelancer_id' => $fl['id'],
                'user_id' => $fl['user_id'],
                'name' => $fl['name'],
                'profile_image' => $fl['profile_image'],
                'title' => $fl['title'],
                'hourly_rate' => $vector['hourly_rate'] ?? $fl['hourly_rate'],
                'years_of_experience' => $vector['experience_years'] ?? $fl['years_of_experience'],
                'availability' => $vector['availability'] ?? $fl['availability'],
                'skill_names' => $vector['skill_names'] ?? [],
                'total_score' => $scoreResult['total_score'],
                'breakdown' => $scoreResult['breakdown'],
            ];
        }

        usort($recommended, fn($a, $b) => $b['total_score'] <=> $a['total_score']);
        $recommended = array_slice($recommended, 0, 10);
    }
}

$pageTitle = 'Recommended Freelancers';
$pageSubtitle = 'AI-matched freelancers for your jobs';
$activePage = 'recommended_freelancers';
$user = ['name' => $user['name'] ?? 'Client', 'profile_image' => $user['profile_image'] ?? null];
$unreadCount = get_unread_message_count($userId, 'client');
$profileLink = 'profile.php';
require_once __DIR__ . '/../includes/client_topbar.php';
?>

<style>
    /* ── Premium Recommended Freelancers ──────────────────────── */
    .job-selector-bar {
        background: #fff;
        border: 1px solid rgba(0,0,0,0.06);
        box-shadow: 0 1px 3px rgba(0,0,0,0.04);
    }
    .dark .job-selector-bar {
        background: #1e293b;
        border-color: rgba(255,255,255,0.06);
    }

    .match-card {
        background: #fff;
        border: 1px solid rgba(0,0,0,0.06);
        box-shadow: 0 1px 3px rgba(0,0,0,0.04);
        transition: all 0.25s cubic-bezier(0.4, 0, 0.2, 1);
    }
    .match-card:hover {
        border-color: rgba(99,102,241,0.2);
        box-shadow: 0 8px 32px -4px rgba(99,102,241,0.08);
        transform: translateY(-2px);
    }
    .dark .match-card {
        background: #1e293b;
        border-color: rgba(255,255,255,0.06);
    }
    .dark .match-card:hover {
        border-color: rgba(99,102,241,0.3);
        box-shadow: 0 8px 32px -4px rgba(99,102,241,0.15);
    }

    /* Match score ring */
    .match-score-ring {
        position: relative;
        width: 64px;
        height: 64px;
    }
    .match-score-ring svg {
        transform: rotate(-90deg);
    }
    .match-score-ring .ring-bg {
        stroke: #e2e8f0;
    }
    .match-score-ring .ring-fill {
        transition: stroke-dashoffset 0.8s cubic-bezier(0.4, 0, 0.2, 1);
    }
    .dark .match-score-ring .ring-bg {
        stroke: #334155;
    }

    /* Progress bars */
    .progress-track {
        height: 4px;
        border-radius: 2px;
        background: #e2e8f0;
        overflow: hidden;
    }
    .dark .progress-track {
        background: #334155;
    }
    .progress-fill {
        height: 100%;
        border-radius: 2px;
        transition: width 0.6s cubic-bezier(0.4, 0, 0.2, 1);
    }

    /* Skill badges */
    .skill-badge {
        padding: 3px 10px;
        border-radius: 6px;
        font-size: 11px;
        font-weight: 600;
        background: #eef2ff;
        color: #4f46e5;
        border: 1px solid rgba(99,102,241,0.1);
        transition: all 0.15s ease;
    }
    .skill-badge:hover {
        background: #e0e7ff;
        border-color: rgba(99,102,241,0.2);
    }
    .dark .skill-badge {
        background: rgba(99,102,241,0.1);
        color: #818cf8;
        border-color: rgba(99,102,241,0.15);
    }

    /* Availability dot */
    .avail-dot {
        width: 6px;
        height: 6px;
        border-radius: 50%;
        display: inline-block;
    }
    .avail-dot.available {
        background: #10b981;
        box-shadow: 0 0 6px rgba(16,185,129,0.4);
    }
    .avail-dot.busy {
        background: #f59e0b;
        box-shadow: 0 0 6px rgba(245,158,11,0.4);
    }

    /* Empty state */
    .empty-state-card {
        background: #fff;
        border: 1px solid rgba(0,0,0,0.06);
        box-shadow: 0 1px 3px rgba(0,0,0,0.04);
    }
    .dark .empty-state-card {
        background: #1e293b;
        border-color: rgba(255,255,255,0.06);
    }
</style>

<main class="max-w-7xl mx-auto px-4 sm:px-6 py-8 flex flex-col gap-6">

    <!-- ═══════════════ Job Selector Bar ═══════════════ -->
    <div class="job-selector-bar rounded-2xl px-6 py-5 fade-in">
        <div class="flex flex-col sm:flex-row sm:items-center gap-4">
            <div class="flex-1">
                <div class="flex items-center gap-2 mb-1">
                    <i data-lucide="sparkles" class="w-4 h-4 text-indigo-500"></i>
                    <h2 class="text-base font-bold text-gray-900 dark:text-white m-0">AI Job Matching</h2>
                </div>
                <p class="text-xs text-gray-400 dark:text-slate-500 m-0">Select a job to discover top AI-matched freelancers</p>
            </div>
            <form method="GET" class="flex items-center gap-3 flex-1 sm:flex-initial">
                <div class="relative flex-1 sm:flex-initial">
                    <i data-lucide="briefcase" class="w-4 h-4 text-gray-400 absolute left-3 top-1/2 -translate-y-1/2 pointer-events-none"></i>
                    <select name="job_id" class="w-full sm:w-72 pl-9 pr-4 py-2.5 rounded-xl border border-gray-200 dark:border-slate-600 bg-white dark:bg-slate-700 text-sm font-medium text-gray-900 dark:text-white outline-none focus:border-indigo-500 focus:ring-2 focus:ring-indigo-500/10 transition-all appearance-none cursor-pointer">
                        <option value="">Select your job...</option>
                        <?php while ($j = $client_jobs->fetch_assoc()): ?>
                            <option value="<?= $j['id'] ?>" <?= $j['id'] == $job_id ? 'selected' : '' ?>>
                                <?= decode_over_encoded($j['title']) ?> (#<?= $j['id'] ?>)
                            </option>
                        <?php endwhile; ?>
                    </select>
                    <i data-lucide="chevron-down" class="w-4 h-4 text-gray-400 absolute right-3 top-1/2 -translate-y-1/2 pointer-events-none"></i>
                </div>
                <button type="submit" class="inline-flex items-center gap-2 px-5 py-2.5 bg-indigo-600 hover:bg-indigo-700 text-white text-sm font-bold rounded-xl shadow-lg shadow-indigo-600/20 hover:shadow-xl hover:shadow-indigo-600/30 active:scale-[0.97] transition-all whitespace-nowrap">
                    <i data-lucide="sparkles" class="w-4 h-4"></i> Find Matches
                </button>
            </form>
        </div>
    </div>

    <!-- ═══════════════ Results ═══════════════ -->
    <?php if ($job_id && empty($recommended)): ?>
        <div class="empty-state-card rounded-2xl p-16 text-center fade-in">
            <div class="w-20 h-20 rounded-2xl bg-gray-50 dark:bg-slate-700/50 flex items-center justify-center mx-auto mb-5">
                <i data-lucide="user-x" class="w-10 h-10 text-gray-300 dark:text-slate-500"></i>
            </div>
            <h3 class="text-base font-bold text-gray-900 dark:text-white mb-1">No matching freelancers found</h3>
            <p class="text-sm text-gray-400 dark:text-slate-500 max-w-sm mx-auto">Try selecting a different job or adjusting your requirements to find better matches.</p>
        </div>

    <?php elseif ($job_id && !empty($recommended)): ?>

        <!-- Results Count -->
        <div class="flex items-center justify-between">
            <p class="text-sm text-gray-500 dark:text-slate-400 m-0">
                <span class="font-bold text-gray-900 dark:text-white"><?= count($recommended) ?></span> freelancer<?= count($recommended) !== 1 ? 's' : '' ?> matched
            </p>
        </div>

        <div class="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-3 gap-5">
            <?php foreach ($recommended as $idx => $match): ?>
                <?php
                $score = round($match['total_score']);
                $scoreColor = $score >= 60 ? '#10b981' : ($score >= 40 ? '#4f46e5' : ($score >= 20 ? '#f59e0b' : '#94a3b8'));
                $scoreTextColor = $score >= 60 ? 'text-emerald-600 dark:text-emerald-400' : ($score >= 40 ? 'text-indigo-600 dark:text-indigo-400' : ($score >= 20 ? 'text-amber-600 dark:text-amber-400' : 'text-gray-400'));
                $scoreLabel = $score >= 60 ? 'Excellent' : ($score >= 40 ? 'Strong' : ($score >= 20 ? 'Fair' : 'Low'));
                $ringDash = 2 * M_PI * 26;
                $ringOffset = $ringDash - ($score / 100) * $ringDash;
                ?>
                <div class="match-card rounded-2xl overflow-hidden fade-in" style="animation-delay: <?= $idx * 0.05 ?>s">
                    <!-- Card Header -->
                    <div class="px-5 pt-5 pb-4">
                        <div class="flex items-start gap-4">
                            <!-- Avatar -->
                            <div class="relative flex-shrink-0">
                                <img src="<?= get_profile_image($match['profile_image']) ?>" class="w-14 h-14 rounded-2xl object-cover border-2 border-gray-100 dark:border-slate-600" alt="<?= sanitize_string($match['name']) ?>">
                                <?php if ($match['availability'] === 'Available'): ?>
                                    <span class="absolute -bottom-0.5 -right-0.5 w-4 h-4 bg-emerald-500 border-2 border-white dark:border-slate-800 rounded-full"></span>
                                <?php endif; ?>
                            </div>

                            <!-- Info -->
                            <div class="flex-1 min-w-0">
                                <p class="text-sm font-bold text-gray-900 dark:text-white m-0 truncate"><?= sanitize_string($match['name']) ?></p>
                                <p class="text-xs text-gray-400 dark:text-slate-500 m-0 mt-0.5 truncate"><?= sanitize_string($match['title'] ?? 'Freelancer') ?></p>
                                <div class="flex items-center gap-3 mt-2">
                                    <span class="flex items-center gap-1 text-[11px] text-gray-500 dark:text-slate-400">
                                        <i data-lucide="clock" class="w-3 h-3"></i>
                                        <?= $match['years_of_experience'] ?>yr exp
                                    </span>
                                    <span class="flex items-center gap-1 text-[11px] <?= $match['availability'] === 'Available' ? 'text-emerald-600 dark:text-emerald-400' : 'text-amber-600 dark:text-amber-400' ?>">
                                        <span class="avail-dot <?= $match['availability'] === 'Available' ? 'available' : 'busy' ?>"></span>
                                        <?= $match['availability'] ?>
                                    </span>
                                </div>
                            </div>

                            <!-- Match Score Ring -->
                            <div class="flex-shrink-0">
                                <div class="match-score-ring">
                                    <svg width="64" height="64" viewBox="0 0 64 64">
                                        <circle cx="32" cy="32" r="26" fill="none" stroke-width="5" class="ring-bg" />
                                        <circle cx="32" cy="32" r="26" fill="none" stroke-width="5" class="ring-fill"
                                            stroke="<?= $scoreColor ?>"
                                            stroke-linecap="round"
                                            stroke-dasharray="<?= $ringDash ?>"
                                            stroke-dashoffset="<?= $ringOffset ?>" />
                                    </svg>
                                    <div class="absolute inset-0 flex flex-col items-center justify-center">
                                        <span class="text-sm font-black <?= $scoreTextColor ?> leading-none"><?= $score ?>%</span>
                                        <span class="text-[8px] font-bold text-gray-400 dark:text-slate-500 uppercase tracking-wider mt-0.5"><?= $scoreLabel ?></span>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Skills -->
                    <?php if (!empty($match['skill_names'])): ?>
                    <div class="px-5 pb-4">
                        <div class="flex flex-wrap gap-1.5">
                            <?php foreach (array_slice($match['skill_names'], 0, 4) as $skill): ?>
                                <span class="skill-badge"><?= sanitize_string($skill) ?></span>
                            <?php endforeach; ?>
                            <?php if (count($match['skill_names']) > 4): ?>
                                <span class="skill-badge bg-gray-100 dark:bg-slate-600 text-gray-500 dark:text-slate-400 border-gray-200 dark:border-slate-500">+<?= count($match['skill_names']) - 4 ?></span>
                            <?php endif; ?>
                        </div>
                    </div>
                    <?php endif; ?>

                    <!-- Score Breakdown -->
                    <div class="px-5 pb-4">
                        <div class="space-y-2.5">
                            <!-- Skill Match -->
                            <div>
                                <div class="flex items-center justify-between mb-1">
                                    <span class="text-[11px] font-semibold text-gray-500 dark:text-slate-400">Skill Match</span>
                                    <span class="text-[11px] font-bold text-gray-700 dark:text-slate-300"><?= $match['breakdown']['skill_match'] ?>/50</span>
                                </div>
                                <div class="progress-track">
                                    <div class="progress-fill bg-indigo-500" style="width: <?= min(100, $match['breakdown']['skill_match'] / 50 * 100) ?>%"></div>
                                </div>
                            </div>
                            <!-- Rate Fit -->
                            <div>
                                <div class="flex items-center justify-between mb-1">
                                    <span class="text-[11px] font-semibold text-gray-500 dark:text-slate-400">Rate Fit</span>
                                    <span class="text-[11px] font-bold <?= $match['breakdown']['rate_fit'] >= 15 ? 'text-emerald-600 dark:text-emerald-400' : ($match['breakdown']['rate_fit'] >= 10 ? 'text-amber-600 dark:text-amber-400' : 'text-gray-500 dark:text-slate-400') ?>"><?= $match['breakdown']['rate_fit'] ?>/20</span>
                                </div>
                                <div class="progress-track">
                                    <div class="progress-fill <?= $match['breakdown']['rate_fit'] >= 15 ? 'bg-emerald-500' : ($match['breakdown']['rate_fit'] >= 10 ? 'bg-amber-500' : 'bg-gray-300 dark:bg-slate-600') ?>" style="width: <?= min(100, $match['breakdown']['rate_fit'] / 20 * 100) ?>%"></div>
                                </div>
                            </div>
                            <!-- Experience -->
                            <div>
                                <div class="flex items-center justify-between mb-1">
                                    <span class="text-[11px] font-semibold text-gray-500 dark:text-slate-400">Experience</span>
                                    <span class="text-[11px] font-bold text-gray-700 dark:text-slate-300"><?= $match['breakdown']['experience'] ?>/15</span>
                                </div>
                                <div class="progress-track">
                                    <div class="progress-fill bg-blue-500" style="width: <?= min(100, $match['breakdown']['experience'] / 15 * 100) ?>%"></div>
                                </div>
                            </div>
                            <!-- Availability -->
                            <div>
                                <div class="flex items-center justify-between mb-1">
                                    <span class="text-[11px] font-semibold text-gray-500 dark:text-slate-400">Availability</span>
                                    <span class="text-[11px] font-bold <?= $match['breakdown']['availability'] >= 10 ? 'text-emerald-600 dark:text-emerald-400' : 'text-gray-500 dark:text-slate-400' ?>"><?= $match['breakdown']['availability'] ?>/15</span>
                                </div>
                                <div class="progress-track">
                                    <div class="progress-fill <?= $match['breakdown']['availability'] >= 10 ? 'bg-emerald-500' : 'bg-gray-300 dark:bg-slate-600' ?>" style="width: <?= min(100, $match['breakdown']['availability'] / 15 * 100) ?>%"></div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Footer -->
                    <div class="px-5 py-4 border-t border-gray-100 dark:border-slate-700/50 flex items-center justify-between">
                        <div>
                            <span class="text-lg font-black text-gray-900 dark:text-white"><?= format_currency($match['hourly_rate']) ?></span>
                            <span class="text-xs font-normal text-gray-400 dark:text-slate-500">/hr</span>
                        </div>
                        <div class="flex items-center gap-2">
                            <a href="/jobhub/freelancer/profile.php?id=<?= $match['user_id'] ?>"
                                class="inline-flex items-center gap-1.5 px-4 py-2 bg-gray-100 hover:bg-gray-200 dark:bg-slate-700 dark:hover:bg-slate-600 text-gray-700 dark:text-slate-300 text-xs font-semibold rounded-lg transition-all">
                                Profile <i data-lucide="arrow-right" class="w-3 h-3"></i>
                            </a>
                            <a href="/jobhub/client/invite_jobs.php?freelancer_id=<?= $match['freelancer_id'] ?>&job_id=<?= $job_id ?>"
                                class="inline-flex items-center gap-1.5 px-4 py-2 bg-indigo-600 hover:bg-indigo-700 text-white text-xs font-bold rounded-lg shadow-sm shadow-indigo-600/20 transition-all">
                                <i data-lucide="send" class="w-3 h-3"></i> Invite
                            </a>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>

    <?php elseif (!$job_id): ?>
        <div class="empty-state-card rounded-2xl p-16 text-center fade-in">
            <div class="w-20 h-20 rounded-2xl bg-indigo-50 dark:bg-indigo-900/20 flex items-center justify-center mx-auto mb-5">
                <i data-lucide="brain" class="w-10 h-10 text-indigo-400 dark:text-indigo-500"></i>
            </div>
            <h3 class="text-base font-bold text-gray-900 dark:text-white mb-1">Select a job to see AI recommendations</h3>
            <p class="text-sm text-gray-400 dark:text-slate-500 max-w-md mx-auto">Our AI analyzes skill requirements, budget, and experience to find the best matches for your project.</p>
        </div>
    <?php endif; ?>
</main>

<?php require_once __DIR__ . '/../includes/client_footer.php'; ?>
