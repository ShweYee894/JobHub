<?php
session_start();
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../auth/auth.php';
require_once __DIR__ . '/../includes/ai_engine.php';
require_role('freelancer');

$userId = $_SESSION['user_id'];

// Get freelancer info
$stmt = $conn->prepare('SELECT id, name, profile_image FROM users WHERE id = ?');
$stmt->bind_param('i', $userId);
$stmt->execute();
$user = $stmt->get_result()->fetch_assoc();
$stmt->close();

$stmt = $conn->prepare('SELECT id FROM freelancers WHERE user_id = ?');
$stmt->bind_param('i', $userId);
$stmt->execute();
$freelancer = $stmt->get_result()->fetch_assoc();
$stmt->close();

$freelancerId = $freelancer['id'] ?? 0;

if (!$freelancerId) {
    set_flash('error', 'Freelancer profile not found.');
    header('Location: /jobhub/freelancer/dashboard.php');
    exit();
}

// Fetch recommended jobs using ai_engine (cosine similarity when dense vectors exist)
$matches = [];

// Get freelancer skills vector
$stmt = $conn->prepare('SELECT hourly_rate, years_of_experience, availability, skills_vector, title, bio FROM freelancers WHERE id = ?');
$stmt->bind_param('i', $freelancerId);
$stmt->execute();
$flData = $stmt->get_result()->fetch_assoc();
$stmt->close();

$fl_vector = json_decode($flData['skills_vector'] ?? '{}', true) ?: [];

// Generate vector on the fly if missing using ai_engine
if (empty($fl_vector)) {
    $s4 = $conn->prepare('
        SELECT fs.skill_id, s.skill_name
        FROM freelancer_skills fs
        JOIN skills s ON fs.skill_id = s.id
        WHERE fs.freelancer_id = ?
    ');
    $s4->bind_param('i', $freelancerId);
    $s4->execute();
    $r4 = $s4->get_result();
    $fl_skill_ids = [];
    $fl_skill_names = [];
    while ($row = $r4->fetch_assoc()) {
        $fl_skill_ids[] = (int) $row['skill_id'];
        $fl_skill_names[] = $row['skill_name'];
    }
    $s4->close();

    $flVectorJson = ai_generate_freelancer_vector(
        $fl_skill_ids,
        $fl_skill_names,
        (float) $flData['hourly_rate'],
        (int) $flData['years_of_experience'],
        $flData['availability'] ?? 'Available',
        ($flData['title'] ?? '') . ' ' . ($flData['bio'] ?? '')
    );
    $fl_vector = json_decode($flVectorJson, true);
}

$fl_rate = $fl_vector['hourly_rate'] ?? 0;

// Get all open jobs
$jobs_stmt = $conn->prepare("SELECT id, client_id, title, description, budget, embedding_vector, created_at FROM jobs WHERE status = 'open' ORDER BY created_at DESC");
$jobs_stmt->execute();
$jobs_result = $jobs_stmt->get_result();
$jobs_stmt->close();

while ($job = $jobs_result->fetch_assoc()) {
    $embedding = json_decode($job['embedding_vector'] ?? '{}', true) ?: [];

    // Generate embedding on the fly if missing using ai_engine
    if (empty($embedding)) {
        $s5 = $conn->prepare('SELECT skill_id FROM job_skills WHERE job_id = ?');
        $s5->bind_param('i', $job['id']);
        $s5->execute();
        $r5 = $s5->get_result();
        $job_skill_ids = [];
        while ($row = $r5->fetch_assoc()) {
            $job_skill_ids[] = (int) $row['skill_id'];
        }
        $s5->close();
        $embedding_json = ai_generate_job_embedding($job['title'], $job['description'] ?? $job['title'], $job_skill_ids, (float) $job['budget']);
        $embedding = json_decode($embedding_json, true);
    }

    // Score using ai_engine
    $scoreResult = ai_score_job_for_freelancer($fl_vector, $embedding, $fl_rate, $job['created_at']);

    if ($scoreResult['total_score'] <= 0) continue;

    // Get skill names
    $sn = $conn->prepare('SELECT s.skill_name FROM job_skills js JOIN skills s ON js.skill_id = s.id WHERE js.job_id = ?');
    $sn->bind_param('i', $job['id']);
    $sn->execute();
    $sr = $sn->get_result();
    $skill_names = [];
    while ($row = $sr->fetch_assoc()) {
        $skill_names[] = $row['skill_name'];
    }
    $sn->close();

    // Client info
    $cn = $conn->prepare('SELECT name FROM users WHERE id = ?');
    $cn->bind_param('i', $job['client_id']);
    $cn->execute();
    $client_name = $cn->get_result()->fetch_assoc()['name'] ?? 'Client';
    $cn->close();

    $matches[] = [
        'job_id' => $job['id'],
        'title' => $job['title'],
        'description' => $job['description'],
        'budget' => (float) $job['budget'],
        'client_name' => $client_name,
        'skill_names' => $skill_names,
        'created_at' => $job['created_at'],
        'total_score' => $scoreResult['total_score'],
        'skill_pct' => round($scoreResult['breakdown']['skill_match'] / 60 * 100),
    ];
}

usort($matches, fn($a, $b) => $b['total_score'] <=> $a['total_score']);
$recommended = array_slice($matches, 0, 20);

$activePage = 'recommended_jobs';
$pageTitle = 'Recommended Jobs';
$pageSubtitle = 'Jobs matched to your skills and experience';
$user = ['name' => $user['name'] ?? 'Freelancer', 'profile_image' => $user['profile_image'] ?? null];
$unreadCount = get_unread_message_count($userId, 'freelancer');
require_once __DIR__ . '/../components/freelancer_header.php';
?>
        <div class="max-w-7xl mx-auto px-4 sm:px-6 py-8">
            <?php if (empty($recommended)): ?>
            <div class="bg-white rounded-2xl p-12 border border-gray-100 shadow-sm text-center">
                <div class="w-16 h-16 rounded-2xl bg-gray-100 flex items-center justify-center mx-auto mb-4">
                    <i data-lucide="brain" class="text-2xl text-gray-400"></i>
                </div>
                <p class="text-gray-500 text-sm mb-2">No recommended jobs yet</p>
                <p class="text-gray-400 text-xs mb-4">Update your skills and preferences to get better recommendations</p>
                <a href="browse_jobs.php" class="btn-grad inline-flex items-center gap-2 text-white text-sm font-semibold px-5 py-2.5 rounded-xl">
                    <i data-lucide="search" class="text-xs"></i> Browse All Jobs
                </a>
            </div>
            <?php else: ?>

            <div class="grid grid-cols-1 lg:grid-cols-2 gap-4">
                <?php foreach ($recommended as $match): ?>
                <?php
                $scoreColor = ($match['total_score'] >= 50)
                    ? 'bg-emerald-500'
                    : (($match['total_score'] >= 30)
                        ? 'bg-indigo-500'
                        : (($match['total_score'] >= 15) ? 'bg-amber-500' : 'bg-gray-400'));
                $scoreText = ($match['total_score'] >= 50)
                    ? 'text-emerald-600'
                    : (($match['total_score'] >= 30)
                        ? 'text-indigo-600'
                        : (($match['total_score'] >= 15) ? 'text-amber-600' : 'text-gray-600'));
                ?>
                <div class="bg-white rounded-2xl p-5 border border-gray-100 shadow-sm hover:shadow-md hover:border-indigo-200 transition-all">
                    <div class="flex items-start justify-between mb-3">
                        <div class="flex-1 min-w-0">
                            <a href="job_detail.php?id=<?= $match['job_id'] ?>" class="text-base font-bold text-gray-900 hover:text-indigo-600 transition-colors line-clamp-1">
                                <?= sanitize_string($match['title']) ?>
                            </a>
                            <p class="text-xs text-gray-400 mt-1">by <?= sanitize_string($match['client_name']) ?> &middot; <?= time_ago($match['created_at']) ?></p>
                        </div>
                        <div class="ml-3 flex-shrink-0">
                            <div class="relative w-14 h-14">
                                <svg class="w-14 h-14 -rotate-90" viewBox="0 0 36 36">
                                    <path d="M18 2.0845 a 15.9155 15.9155 0 0 1 0 31.831 a 15.9155 15.9155 0 0 1 0 -31.831" fill="none" stroke="#e5e7eb" stroke-width="3"/>
                                    <path d="M18 2.0845 a 15.9155 15.9155 0 0 1 0 31.831 a 15.9155 15.9155 0 0 1 0 -31.831" fill="none" stroke="currentColor" stroke-width="3" stroke-dasharray="<?= $match['total_score'] ?>, 100" class="<?= $scoreText ?>"/>
                                </svg>
                                <span class="absolute inset-0 flex items-center justify-center text-xs font-bold <?= $scoreText ?>"><?= round($match['total_score']) ?>%</span>
                            </div>
                        </div>
                    </div>

                    <p class="text-sm text-gray-500 mb-3 line-clamp-2"><?= sanitize_string(truncate($match['description'], 150)) ?></p>

                    <div class="flex items-center justify-between">
                        <div class="flex flex-wrap gap-1">
                            <?php foreach (array_slice($match['skill_names'], 0, 4) as $skill): ?>
                                <span class="px-2 py-0.5 bg-indigo-50 text-indigo-600 rounded text-[10px] font-semibold"><?= sanitize_string($skill) ?></span>
                            <?php endforeach; ?>
                            <?php if (count($match['skill_names']) > 4): ?>
                                <span class="px-2 py-0.5 bg-gray-100 text-gray-500 rounded text-[10px] font-semibold">+<?= count($match['skill_names']) - 4 ?></span>
                            <?php endif; ?>
                        </div>
                        <span class="text-sm font-bold text-gray-900"><?= format_currency($match['budget']) ?></span>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
            <?php endif; ?>
<?php require_once __DIR__ . '/../components/freelancer_footer.php'; ?>
