<?php
/**
 * AI Matching API
 * Generates embeddings, vectors, and computes match scores between jobs and freelancers.
 */

require_once __DIR__ . '/../config/db.php';

header('Content-Type: application/json');

$action = $_GET['action'] ?? '';

// ── Stop Words List ────────────────────────────────────────────────────────
$STOP_WORDS = [
    'the','is','at','which','on','a','an','and','or','but','in','with','to','for',
    'of','not','no','can','had','has','was','were','are','be','been','being',
    'have','having','do','does','did','doing','will','would','could','should',
    'may','might','shall','must','that','this','these','those','it','its',
    'from','by','as','if','then','than','so','just','also','about','into',
    'over','after','before','between','under','above','out','off','up','down',
    'all','each','every','both','few','more','most','other','some','such','any',
    'only','same','own','too','very','here','there','when','where','why','how',
    'what','who','whom','whose','through','during','until','while','again',
    'further','once','because','nor','against','during','once','twice',
];

/**
 * Extract keywords from text.
 * Tokenizes, lowercases, removes stop words, removes short words, returns top 20 by frequency.
 */
function extract_keywords(string $text, array $stop_words): array {
    $text = strtolower($text);
    $text = preg_replace('/[^a-z0-9\s]/', ' ', $text);
    $words = preg_split('/\s+/', $text, -1, PREG_SPLIT_NO_EMPTY);

    $freq = [];
    foreach ($words as $word) {
        if (strlen($word) < 3 || in_array($word, $stop_words, true)) {
            continue;
        }
        $freq[$word] = ($freq[$word] ?? 0) + 1;
    }

    arsort($freq);
    return array_slice(array_keys($freq), 0, 20);
}

/**
 * Calculate cosine-like similarity on skill IDs.
 */
function skill_similarity(array $skills_a, array $skills_b): float {
    if (empty($skills_a) || empty($skills_b)) {
        return 0.0;
    }
    $common = count(array_intersect($skills_a, $skills_b));
    $unique = count(array_unique(array_merge($skills_a, $skills_b)));
    return $unique > 0 ? $common / $unique : 0.0;
}

// ── Generate Job Embedding ────────────────────────────────────────────────
if ($action === 'generate_job_embedding') {
    $job_id = sanitize_int($_GET['job_id'] ?? 0);

    $stmt = $conn->prepare('SELECT id, title, description, budget FROM jobs WHERE id = ?');
    $stmt->bind_param('i', $job_id);
    $stmt->execute();
    $job = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if (!$job) {
        json_response(['error' => 'Job not found'], 404);
    }

    $title_words = extract_keywords($job['title'], $STOP_WORDS);
    $desc_words = extract_keywords($job['description'], $STOP_WORDS);
    $all_keywords = array_unique(array_merge($title_words, $desc_words));
    $all_keywords = array_slice($all_keywords, 0, 20);

    $word_count = str_word_count(strtolower($job['title'] . ' ' . $job['description']));

    $stmt = $conn->prepare('SELECT skill_id FROM job_skills WHERE job_id = ?');
    $stmt->bind_param('i', $job_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $skill_ids = [];
    while ($row = $result->fetch_assoc()) {
        $skill_ids[] = (int) $row['skill_id'];
    }
    $stmt->close();

    $budget_max = 10000;
    $budget_normalized = max(0.0, min(1.0, (float) $job['budget'] / $budget_max));

    $embedding = json_encode([
        'keywords'          => $all_keywords,
        'skill_ids'         => $skill_ids,
        'word_count'        => $word_count,
        'title_words'       => $title_words,
        'budget_normalized' => round($budget_normalized, 4),
    ]);

    $stmt = $conn->prepare('UPDATE jobs SET embedding_vector = ? WHERE id = ?');
    $stmt->bind_param('si', $embedding, $job_id);
    $stmt->execute();
    $stmt->close();

    json_response([
        'success'   => true,
        'job_id'    => $job_id,
        'embedding' => json_decode($embedding, true),
    ]);
}

// ── Generate Freelancer Vector ────────────────────────────────────────────
if ($action === 'generate_freelancer_vector') {
    $freelancer_id = sanitize_int($_GET['freelancer_id'] ?? 0);

    $stmt = $conn->prepare('SELECT id, hourly_rate, years_of_experience, availability FROM freelancers WHERE id = ?');
    $stmt->bind_param('i', $freelancer_id);
    $stmt->execute();
    $freelancer = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if (!$freelancer) {
        json_response(['error' => 'Freelancer not found'], 404);
    }

    $stmt = $conn->prepare('
        SELECT fs.skill_id, s.skill_name
        FROM freelancer_skills fs
        JOIN skills s ON fs.skill_id = s.id
        WHERE fs.freelancer_id = ?
    ');
    $stmt->bind_param('i', $freelancer_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $skill_ids = [];
    $skill_names = [];
    while ($row = $result->fetch_assoc()) {
        $skill_ids[] = (int) $row['skill_id'];
        $skill_names[] = $row['skill_name'];
    }
    $stmt->close();

    $vector = json_encode([
        'skill_ids'        => $skill_ids,
        'skill_names'      => $skill_names,
        'hourly_rate'      => (float) $freelancer['hourly_rate'],
        'experience_years' => (int) $freelancer['years_of_experience'],
        'availability'     => $freelancer['availability'] ?? 'Available',
    ]);

    $stmt = $conn->prepare('UPDATE freelancers SET skills_vector = ? WHERE id = ?');
    $stmt->bind_param('si', $vector, $freelancer_id);
    $stmt->execute();
    $stmt->close();

    json_response([
        'success'       => true,
        'freelancer_id' => $freelancer_id,
        'vector'        => json_decode($vector, true),
    ]);
}

// ── Match Freelancers to a Job ────────────────────────────────────────────
if ($action === 'match_freelancers') {
    $job_id = sanitize_int($_GET['job_id'] ?? 0);

    $stmt = $conn->prepare('SELECT id, title, budget, embedding_vector FROM jobs WHERE id = ?');
    $stmt->bind_param('i', $job_id);
    $stmt->execute();
    $job = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if (!$job) {
        json_response(['error' => 'Job not found'], 404);
    }

    // Generate embedding if missing
    if (empty($job['embedding_vector'])) {
        // Inline generate
        $title_words = extract_keywords($job['title'], $STOP_WORDS);
        $desc_words = extract_keywords($job['description'] ?? $job['title'], $STOP_WORDS);
        $all_keywords = array_unique(array_merge($title_words, $desc_words));
        $all_keywords = array_slice($all_keywords, 0, 20);

        $word_count = str_word_count(strtolower($job['title']));

        $s2 = $conn->prepare('SELECT skill_id FROM job_skills WHERE job_id = ?');
        $s2->bind_param('i', $job_id);
        $s2->execute();
        $r2 = $s2->get_result();
        $job_skill_ids = [];
        while ($row = $r2->fetch_assoc()) {
            $job_skill_ids[] = (int) $row['skill_id'];
        }
        $s2->close();

        $job_embedding = [
            'keywords'          => $all_keywords,
            'skill_ids'         => $job_skill_ids,
            'word_count'        => $word_count,
            'title_words'       => $title_words,
            'budget_normalized' => round(min(1.0, (float) $job['budget'] / 10000), 4),
        ];
    } else {
        $job_embedding = json_decode($job['embedding_vector'], true);
    }

    $job_skill_ids = $job_embedding['skill_ids'] ?? [];
    $job_budget = (float) $job['budget'];
    $estimated_hours = max(1, intval($job_embedding['word_count'] ?? 10));
    // Normalize: use budget / 40 as max affordable hourly rate
    $max_rate = max(1, $job_budget / max(1, $estimated_hours));

    // Get all freelancers with their vectors
    $stmt = $conn->prepare('
        SELECT f.id, f.user_id, f.title, f.skills_vector, f.hourly_rate, f.years_of_experience, f.availability,
               u.name, u.profile_image
        FROM freelancers f
        JOIN users u ON f.user_id = u.id
    ');
    $stmt->execute();
    $freelancers = $stmt->get_result();
    $stmt->close();

    $matches = [];

    while ($fl = $freelancers->fetch_assoc()) {
        $vector = json_decode($fl['skills_vector'] ?? '{}', true) ?: [];

        if (empty($vector)) {
            // Generate vector on the fly
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

        // Skill match: common / job_skills_count (max 50 points)
        $skill_score = 0;
        if (!empty($job_skill_ids)) {
            $common = count(array_intersect($fl_skill_ids, $job_skill_ids));
            $skill_score = ($common / count($job_skill_ids)) * 50;
        }

        // Rate match: if freelancer rate <= max_rate, +20
        $rate_score = 0;
        $fl_rate = $vector['hourly_rate'] ?? $fl['hourly_rate'];
        if ($fl_rate <= $max_rate) {
            $rate_score = 20;
        } elseif ($fl_rate <= $max_rate * 1.5) {
            $rate_score = 10; // partial credit
        }

        // Experience match: years * 2, max 15
        $exp_score = min(15, ($vector['experience_years'] ?? $fl['years_of_experience'] ?? 0) * 2);

        // Availability bonus: if Available, +15
        $avail_score = ($vector['availability'] ?? $fl['availability']) === 'Available' ? 15 : 0;

        $total_score = round($skill_score + $rate_score + $exp_score + $avail_score, 2);

        $matches[] = [
            'freelancer_id'      => $fl['id'],
            'user_id'            => $fl['user_id'],
            'name'               => $fl['name'],
            'profile_image'      => $fl['profile_image'],
            'title'              => $fl['title'],
            'hourly_rate'        => $fl_rate,
            'years_of_experience'=> $vector['experience_years'] ?? $fl['years_of_experience'],
            'availability'       => $vector['availability'] ?? $fl['availability'],
            'skill_names'        => $vector['skill_names'] ?? [],
            'total_score'        => $total_score,
            'breakdown' => [
                'skill_match'     => round($skill_score, 2),
                'rate_fit'        => round($rate_score, 2),
                'experience'      => round($exp_score, 2),
                'availability'    => round($avail_score, 2),
            ],
        ];
    }

    usort($matches, fn($a, $b) => $b['total_score'] <=> $a['total_score']);
    $top_matches = array_slice($matches, 0, 10);

    json_response([
        'success'  => true,
        'job_id'   => $job_id,
        'job_title'=> $job['title'],
        'matches'  => $top_matches,
    ]);
}

// ── Match Jobs to a Freelancer ────────────────────────────────────────────
if ($action === 'match_jobs') {
    $freelancer_id = sanitize_int($_GET['freelancer_id'] ?? 0);

    $stmt = $conn->prepare('SELECT id, hourly_rate, years_of_experience, availability, skills_vector FROM freelancers WHERE id = ?');
    $stmt->bind_param('i', $freelancer_id);
    $stmt->execute();
    $freelancer = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if (!$freelancer) {
        json_response(['error' => 'Freelancer not found'], 404);
    }

    $fl_vector = json_decode($freelancer['skills_vector'] ?? '{}', true) ?: [];

    if (empty($fl_vector)) {
        $s4 = $conn->prepare('
            SELECT fs.skill_id, s.skill_name
            FROM freelancer_skills fs
            JOIN skills s ON fs.skill_id = s.id
            WHERE fs.freelancer_id = ?
        ');
        $s4->bind_param('i', $freelancer_id);
        $s4->execute();
        $r4 = $s4->get_result();
        $fl_vector['skill_ids'] = [];
        $fl_vector['skill_names'] = [];
        while ($row = $r4->fetch_assoc()) {
            $fl_vector['skill_ids'][] = (int) $row['skill_id'];
            $fl_vector['skill_names'][] = $row['skill_name'];
        }
        $s4->close();
        $fl_vector['hourly_rate'] = (float) $freelancer['hourly_rate'];
        $fl_vector['experience_years'] = (int) $freelancer['years_of_experience'];
    }

    $fl_skill_ids = $fl_vector['skill_ids'] ?? [];
    $fl_rate = $fl_vector['hourly_rate'] ?? (float) $freelancer['hourly_rate'];

    // Get open jobs
    $stmt = $conn->prepare("SELECT id, client_id, title, description, budget, embedding_vector, created_at FROM jobs WHERE status = 'open' ORDER BY created_at DESC");
    $stmt->execute();
    $jobs = $stmt->get_result();
    $stmt->close();

    $matches = [];
    $now = time();

    while ($job = $jobs->fetch_assoc()) {
        $embedding = json_decode($job['embedding_vector'] ?? '{}', true) ?: [];

        if (empty($embedding)) {
            // Generate embedding on the fly
            $title_words = extract_keywords($job['title'], $STOP_WORDS);
            $desc_words = extract_keywords($job['description'] ?? $job['title'], $STOP_WORDS);
            $all_kw = array_unique(array_merge($title_words, $desc_words));

            $s5 = $conn->prepare('SELECT skill_id FROM job_skills WHERE job_id = ?');
            $s5->bind_param('i', $job['id']);
            $s5->execute();
            $r5 = $s5->get_result();
            $embedding['skill_ids'] = [];
            while ($row = $r5->fetch_assoc()) {
                $embedding['skill_ids'][] = (int) $row['skill_id'];
            }
            $s5->close();
            $embedding['word_count'] = str_word_count($job['title'] . ' ' . ($job['description'] ?? ''));
        }

        $job_skill_ids = $embedding['skill_ids'] ?? [];
        $job_budget = (float) $job['budget'];
        $estimated_hours = max(1, intval($embedding['word_count'] ?? 10));
        $min_required_rate = $fl_rate * $estimated_hours;

        // Skill match: common / job_skills_count (max 60 points)
        $skill_score = 0;
        if (!empty($job_skill_ids)) {
            $common = count(array_intersect($fl_skill_ids, $job_skill_ids));
            $skill_score = ($common / count($job_skill_ids)) * 60;
        }

        // Budget fit: job budget >= hourly_rate * estimated_hours (max 20 points)
        $budget_score = 0;
        if ($job_budget >= $min_required_rate) {
            $budget_score = 20;
        } elseif ($job_budget >= $min_required_rate * 0.7) {
            $budget_score = 10;
        }

        // Recency: newer jobs get +10, decays over 30 days
        $age_days = max(0, ($now - strtotime($job['created_at'])) / 86400);
        $recency_score = max(0, 10 * (1 - $age_days / 30));

        $total_score = round($skill_score + $budget_score + $recency_score, 2);

        // Get client name
        $cs = $conn->prepare('SELECT name FROM users WHERE id = ?');
        $cs->bind_param('i', $job['client_id']);
        $cs->execute();
        $client_name = $cs->get_result()->fetch_assoc()['name'] ?? 'Client';
        $cs->close();

        // Get job skill names
        $sn = $conn->prepare('SELECT s.skill_name FROM job_skills js JOIN skills s ON js.skill_id = s.id WHERE js.job_id = ?');
        $sn->bind_param('i', $job['id']);
        $sn->execute();
        $sr = $sn->get_result();
        $job_skill_names = [];
        while ($row = $sr->fetch_assoc()) {
            $job_skill_names[] = $row['skill_name'];
        }
        $sn->close();

        $matches[] = [
            'job_id'          => $job['id'],
            'title'           => $job['title'],
            'budget'          => $job_budget,
            'client_name'     => $client_name,
            'skill_names'     => $job_skill_names,
            'created_at'      => $job['created_at'],
            'total_score'     => $total_score,
            'breakdown' => [
                'skill_match' => round($skill_score, 2),
                'budget_fit'  => round($budget_score, 2),
                'recency'     => round($recency_score, 2),
            ],
        ];
    }

    usort($matches, fn($a, $b) => $b['total_score'] <=> $a['total_score']);
    $top_matches = array_slice($matches, 0, 10);

    json_response([
        'success'       => true,
        'freelancer_id' => $freelancer_id,
        'matches'       => $top_matches,
    ]);
}

// ── Default: Unknown Action ───────────────────────────────────────────────
json_response(['error' => 'Invalid action. Valid: generate_job_embedding, generate_freelancer_vector, match_freelancers, match_jobs'], 400);
