<?php
session_start();
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../auth/auth.php';
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
        // Get job embedding
        $embedding = json_decode($job['embedding_vector'] ?? '{}', true) ?: [];

        if (empty($embedding)) {
            $STOP_WORDS = [
                'the', 'is', 'at', 'which', 'on', 'a', 'an', 'and', 'or', 'but', 'in', 'with', 'to', 'for', 'of',
                'not', 'no', 'can', 'had', 'has', 'was', 'were', 'are', 'be', 'been', 'being', 'have', 'having',
                'do', 'does', 'did', 'doing', 'will', 'would', 'could', 'should', 'may', 'might', 'shall', 'must',
                'that', 'this', 'these', 'those', 'it', 'its', 'from', 'by', 'as', 'if', 'then', 'than', 'so',
                'just', 'also', 'about', 'into', 'over', 'after', 'before', 'between', 'under', 'above', 'out',
                'off', 'up', 'down', 'all', 'each', 'every', 'both', 'few', 'more', 'most', 'other', 'some',
                'such', 'any', 'only', 'same', 'own', 'too', 'very', 'here', 'there', 'when', 'where', 'why',
                'how', 'what', 'who', 'whom', 'whose', 'through', 'during', 'until', 'while', 'again', 'further',
                'once', 'because', 'nor', 'against', 'during', 'once', 'twice',
            ];

            $title_words = [];
            $text = strtolower($job['title']);
            $text = preg_replace('/[^a-z0-9\s]/', ' ', $text);
            $words = preg_split('/\s+/', $text, -1, PREG_SPLIT_NO_EMPTY);
            $freq = [];
            foreach ($words as $w) {
                if (strlen($w) < 3 || in_array($w, $STOP_WORDS, true))
                    continue;
                $freq[$w] = ($freq[$w] ?? 0) + 1;
            }
            arsort($freq);
            $title_words = array_slice(array_keys($freq), 0, 20);

            $s2 = $conn->prepare('SELECT skill_id FROM job_skills WHERE job_id = ?');
            $s2->bind_param('i', $job_id);
            $s2->execute();
            $r2 = $s2->get_result();
            $embedding['skill_ids'] = [];
            while ($row = $r2->fetch_assoc()) {
                $embedding['skill_ids'][] = (int) $row['skill_id'];
            }
            $s2->close();
            $embedding['word_count'] = str_word_count($job['title'] . ' ' . ($job['description'] ?? ''));
        }

        $job_skill_ids = $embedding['skill_ids'] ?? [];
        $job_budget = (float) $job['budget'];
        $estimated_hours = max(1, intval($embedding['word_count'] ?? 10));
        $max_rate = max(1, $job_budget / $estimated_hours);

        // Match freelancers
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
                $vector['skill_ids'] = [];
                $vector['skill_names'] = [];
                while ($row = $r3->fetch_assoc()) {
                    $vector['skill_ids'][] = (int) $row['skill_id'];
                    $vector['skill_names'][] = $row['skill_name'];
                }
                $s3->close();
                $vector['hourly_rate'] = (float) $fl['hourly_rate'];
                $vector['experience_years'] = (int) $fl['years_of_experience'];
                $vector['availability'] = $fl['availability'] ?? 'Available';
            }

            $fl_skill_ids = $vector['skill_ids'] ?? [];

            $skill_score = 0;
            if (!empty($job_skill_ids)) {
                $common = count(array_intersect($fl_skill_ids, $job_skill_ids));
                $skill_score = ($common / count($job_skill_ids)) * 50;
            }

            $fl_rate = $vector['hourly_rate'] ?? $fl['hourly_rate'];
            $rate_score = 0;
            if ($fl_rate <= $max_rate) {
                $rate_score = 20;
            } elseif ($fl_rate <= $max_rate * 1.5) {
                $rate_score = 10;
            }

            $exp_score = min(15, ($vector['experience_years'] ?? $fl['years_of_experience'] ?? 0) * 2);
            $avail_score = ($vector['availability'] ?? $fl['availability']) === 'Available' ? 15 : 0;

            $total_score = round($skill_score + $rate_score + $exp_score + $avail_score, 2);

            if ($total_score <= 0)
                continue;

            $recommended[] = [
                'freelancer_id' => $fl['id'],
                'name' => $fl['name'],
                'profile_image' => $fl['profile_image'],
                'title' => $fl['title'],
                'hourly_rate' => $fl_rate,
                'years_of_experience' => $vector['experience_years'] ?? $fl['years_of_experience'],
                'availability' => $vector['availability'] ?? $fl['availability'],
                'skill_names' => $vector['skill_names'] ?? [],
                'total_score' => $total_score,
                'breakdown' => [
                    'skill_match' => round($skill_score, 2),
                    'rate_fit' => round($rate_score, 2),
                    'experience' => round($exp_score, 2),
                    'availability' => round($avail_score, 2),
                ],
            ];
        }

        usort($recommended, fn($a, $b) => $b['total_score'] <=> $a['total_score']);
        $recommended = array_slice($recommended, 0, 10);
    }
}

$conn->close();

$navItems = [
    ['key' => 'dashboard', 'label' => 'Dashboard', 'url' => 'dashboard.php', 'icon' => 'fa-th-large'],
    ['key' => 'my_jobs', 'label' => 'My Jobs', 'url' => 'my_jobs.php', 'icon' => 'fa-briefcase'],
    ['key' => 'post_job', 'label' => 'Post a Job', 'url' => 'post_job.php', 'icon' => 'fa-plus-circle'],
    ['key' => 'proposals', 'label' => 'Proposals', 'url' => 'proposals.php', 'icon' => 'fa-file-alt'],
    ['key' => 'contracts', 'label' => 'Contracts', 'url' => 'contracts.php', 'icon' => 'fa-handshake'],
    ['key' => 'payment_history', 'label' => 'Payments', 'url' => 'payment_history.php', 'icon' => 'fa-credit-card'],
    ['key' => 'messages', 'label' => 'Messages', 'url' => 'messages.php', 'icon' => 'fa-comment-dots'],
];
$pageTitle = 'Recommended Freelancers';
$pageSubtitle = 'AI-matched freelancers for your jobs';
$activePage = 'recommended_freelancers';
$user = ['name' => $user['name'] ?? 'Client', 'profile_image' => $user['profile_image'] ?? null];
$unreadCount = 0;
$profileLink = 'profile.php';
require_once __DIR__ . '/../components/layout_start.php';
?>

    <!-- Job Selector -->
    <div class="bg-white rounded-2xl p-6 border border-gray-100 shadow-sm">
        <h2 class="text-lg font-bold text-gray-900 mb-3">Select a Job</h2>
        <p class="text-sm text-gray-400 mb-4">Choose a job to see AI-recommended freelancers matched to its requirements.</p>
        <form method="GET" class="flex items-center gap-3">
            <select name="job_id" class="flex-1 border border-gray-200 rounded-xl px-4 py-2.5 text-sm focus:ring-2 focus:ring-blue-500 focus:border-blue-500 outline-none">
                <option value="">Select your job...</option>
                <?php while ($j = $client_jobs->fetch_assoc()): ?>
                    <option value="<?= $j['id'] ?>" <?= $j['id'] == $job_id ? 'selected' : '' ?>>
                        <?= sanitize_string($j['title']) ?> (#<?= $j['id'] ?>)
                    </option>
                <?php endwhile; ?>
            </select>
            <button type="submit" class="bg-blue-600 hover:bg-blue-700 text-white text-sm font-semibold px-5 py-2.5 rounded-xl transition-colors inline-flex items-center gap-2 whitespace-nowrap">
                <i class="fas fa-search"></i> Find Matches
            </button>
        </form>
    </div>

    <!-- Results -->
    <?php if ($job_id && empty($recommended)): ?>
    <div class="bg-white rounded-2xl p-12 border border-gray-100 shadow-sm text-center">
        <div class="w-16 h-16 rounded-2xl bg-gray-100 flex items-center justify-center mx-auto mb-4">
            <i class="fas fa-user-slash text-2xl text-gray-400"></i>
        </div>
        <p class="text-gray-500 text-sm mb-2">No matching freelancers found</p>
        <p class="text-gray-400 text-xs">Try posting a job with different requirements or skills.</p>
    </div>
    <?php elseif ($job_id && !empty($recommended)): ?>

    <div class="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-3 gap-4">
        <?php foreach ($recommended as $match): ?>
        <?php
        $scoreColor = ($match['total_score'] >= 60)
            ? 'text-emerald-600'
            : (($match['total_score'] >= 40)
                ? 'text-blue-600'
                : (($match['total_score'] >= 20) ? 'text-amber-600' : 'text-gray-600'));
        $scoreBg = ($match['total_score'] >= 60)
            ? 'bg-emerald-50 border-emerald-200'
            : (($match['total_score'] >= 40)
                ? 'bg-blue-50 border-blue-200'
                : (($match['total_score'] >= 20) ? 'bg-amber-50 border-amber-200' : 'bg-gray-50 border-gray-200'));
        ?>
        <div class="bg-white rounded-2xl p-5 border border-gray-100 shadow-sm hover:shadow-md transition-all">
            <div class="flex items-start gap-4 mb-4">
                <img src="<?= get_profile_image($match['profile_image']) ?>" class="w-14 h-14 rounded-full object-cover border-2 border-gray-100 flex-shrink-0">
                <div class="flex-1 min-w-0">
                    <p class="font-bold text-gray-900 text-sm"><?= sanitize_string($match['name']) ?></p>
                    <p class="text-xs text-gray-400"><?= sanitize_string($match['title'] ?? 'Freelancer') ?></p>
                    <div class="flex items-center gap-2 mt-1">
                        <span class="text-xs text-gray-500"><i class="fas fa-clock mr-1"></i><?= $match['years_of_experience'] ?>yr</span>
                        <span class="text-xs <?= $match['availability'] === 'Available' ? 'text-emerald-600' : 'text-amber-600' ?>">
                            <i class="fas fa-circle text-[6px] mr-1"></i><?= $match['availability'] ?>
                        </span>
                    </div>
                </div>
                <div class="flex-shrink-0">
                    <div class="px-3 py-1.5 rounded-xl border <?= $scoreBg ?> text-center">
                        <p class="text-lg font-black <?= $scoreColor ?>"><?= round($match['total_score']) ?>%</p>
                        <p class="text-[9px] font-semibold text-gray-400 uppercase">Match</p>
                    </div>
                </div>
            </div>

            <div class="flex flex-wrap gap-1 mb-3">
                <?php foreach (array_slice($match['skill_names'], 0, 5) as $skill): ?>
                    <span class="px-2 py-0.5 bg-blue-50 text-blue-600 rounded text-[10px] font-semibold"><?= sanitize_string($skill) ?></span>
                <?php endforeach; ?>
                <?php if (count($match['skill_names']) > 5): ?>
                    <span class="px-2 py-0.5 bg-gray-100 text-gray-500 rounded text-[10px] font-semibold">+<?= count($match['skill_names']) - 5 ?></span>
                <?php endif; ?>
            </div>

            <div class="grid grid-cols-2 gap-2 text-[11px] mb-4">
                <div class="bg-gray-50 rounded-lg p-2">
                    <span class="text-gray-400">Skill Match</span>
                    <div class="flex items-center gap-1 mt-1">
                        <div class="flex-1 bg-gray-200 rounded-full h-1.5"><div class="bg-blue-500 h-1.5 rounded-full" style="width:<?= min(100, $match['breakdown']['skill_match'] / 50 * 100) ?>%"></div></div>
                        <span class="font-bold text-gray-600"><?= $match['breakdown']['skill_match'] ?>/50</span>
                    </div>
                </div>
                <div class="bg-gray-50 rounded-lg p-2">
                    <span class="text-gray-400">Rate Fit</span>
                    <p class="font-bold <?= $match['breakdown']['rate_fit'] >= 20 ? 'text-emerald-600' : ($match['breakdown']['rate_fit'] >= 10 ? 'text-amber-600' : 'text-gray-400') ?> mt-1"><?= $match['breakdown']['rate_fit'] ?>/20</p>
                </div>
                <div class="bg-gray-50 rounded-lg p-2">
                    <span class="text-gray-400">Experience</span>
                    <p class="font-bold text-gray-600 mt-1"><?= $match['breakdown']['experience'] ?>/15</p>
                </div>
                <div class="bg-gray-50 rounded-lg p-2">
                    <span class="text-gray-400">Availability</span>
                    <p class="font-bold <?= $match['breakdown']['availability'] >= 15 ? 'text-emerald-600' : 'text-gray-400' ?> mt-1"><?= $match['breakdown']['availability'] ?>/15</p>
                </div>
            </div>

            <div class="flex items-center justify-between pt-3 border-t border-gray-100">
                <span class="text-sm font-bold text-gray-900"><?= format_currency($match['hourly_rate']) ?><span class="text-xs font-normal text-gray-400">/hr</span></span>
                <a href="proposal_detail.php?freelancer_id=<?= $match['freelancer_id'] ?>&job_id=<?= $job_id ?>" class="text-xs text-blue-600 hover:text-blue-700 font-semibold inline-flex items-center gap-1 transition-colors">
                    View Profile <i class="fas fa-arrow-right text-[9px]"></i>
                </a>
            </div>
        </div>
        <?php endforeach; ?>
    </div>

    <?php elseif (!$job_id): ?>
    <div class="bg-white rounded-2xl p-12 border border-gray-100 shadow-sm text-center">
        <div class="w-16 h-16 rounded-2xl bg-blue-50 flex items-center justify-center mx-auto mb-4">
            <i class="fas fa-brain text-2xl text-blue-400"></i>
        </div>
        <p class="text-gray-500 text-sm mb-2">Select a job to see AI recommendations</p>
        <p class="text-gray-400 text-xs">Our AI analyzes skill requirements, budget, and experience to find the best matches.</p>
    </div>
    <?php endif; ?>

<?php require_once __DIR__ . '/../components/layout_end.php'; ?>
