<?php
/**
 * AI Matching API
 * Generates embeddings, vectors, and computes match scores between jobs and freelancers.
 * Now uses real cosine similarity via includes/ai_engine.php
 */

session_start();
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../auth/auth.php';
require_once __DIR__ . '/../includes/ai_engine.php';

header('Content-Type: application/json');

if (!is_logged_in()) {
    json_response(['success' => false, 'message' => 'Authentication required.'], 401);
}

$action = $_GET['action'] ?? '';

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

    $stmt = $conn->prepare('SELECT skill_id FROM job_skills WHERE job_id = ?');
    $stmt->bind_param('i', $job_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $skill_ids = [];
    while ($row = $result->fetch_assoc()) {
        $skill_ids[] = (int) $row['skill_id'];
    }
    $stmt->close();

    // Generate embedding using ai_engine (includes dense vector for cosine similarity)
    $embedding = ai_generate_job_embedding($job['title'], $job['description'], $skill_ids, (float) $job['budget']);

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

    $stmt = $conn->prepare('SELECT id, hourly_rate, years_of_experience, availability, bio, title FROM freelancers WHERE id = ?');
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

    // Generate vector using ai_engine (includes dense vector for cosine similarity)
    $embeddingText = ($freelancer['title'] ?? '') . ' ' . ($freelancer['bio'] ?? '');
    $vector = ai_generate_freelancer_vector(
        $skill_ids,
        $skill_names,
        (float) $freelancer['hourly_rate'],
        (int) $freelancer['years_of_experience'],
        $freelancer['availability'] ?? 'Available',
        $embeddingText
    );

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

    $stmt = $conn->prepare('SELECT id, title, description, budget, embedding_vector FROM jobs WHERE id = ?');
    $stmt->bind_param('i', $job_id);
    $stmt->execute();
    $job = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if (!$job) {
        json_response(['error' => 'Job not found'], 404);
    }

    // Load or generate job embedding
    $job_embedding = json_decode($job['embedding_vector'] ?? '{}', true) ?: [];
    if (empty($job_embedding)) {
        // Generate embedding on the fly using ai_engine
        $s2 = $conn->prepare('SELECT skill_id FROM job_skills WHERE job_id = ?');
        $s2->bind_param('i', $job_id);
        $s2->execute();
        $r2 = $s2->get_result();
        $job_skill_ids = [];
        while ($row = $r2->fetch_assoc()) {
            $job_skill_ids[] = (int) $row['skill_id'];
        }
        $s2->close();
        $job_embedding_json = ai_generate_job_embedding($job['title'], $job['description'] ?? $job['title'], $job_skill_ids, (float) $job['budget']);
        $job_embedding = json_decode($job_embedding_json, true);
    }

    $job_budget = (float) $job['budget'];

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

        // Generate vector on the fly if missing using ai_engine
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

        // Score using ai_engine (cosine similarity when dense vectors exist, Jaccard fallback)
        $scoreResult = ai_score_freelancer_for_job($job_embedding, $vector, $job_budget);

        $matches[] = [
            'freelancer_id'      => $fl['id'],
            'user_id'            => $fl['user_id'],
            'name'               => $fl['name'],
            'profile_image'      => $fl['profile_image'],
            'title'              => $fl['title'],
            'hourly_rate'        => $vector['hourly_rate'] ?? $fl['hourly_rate'],
            'years_of_experience'=> $vector['experience_years'] ?? $fl['years_of_experience'],
            'availability'       => $vector['availability'] ?? $fl['availability'],
            'skill_names'        => $vector['skill_names'] ?? [],
            'total_score'        => $scoreResult['total_score'],
            'breakdown'          => $scoreResult['breakdown'],
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

    $stmt = $conn->prepare('SELECT id, hourly_rate, years_of_experience, availability, skills_vector, title, bio FROM freelancers WHERE id = ?');
    $stmt->bind_param('i', $freelancer_id);
    $stmt->execute();
    $freelancer = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if (!$freelancer) {
        json_response(['error' => 'Freelancer not found'], 404);
    }

    $fl_vector = json_decode($freelancer['skills_vector'] ?? '{}', true) ?: [];

    // Generate vector on the fly if missing using ai_engine
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
            (float) $freelancer['hourly_rate'],
            (int) $freelancer['years_of_experience'],
            $freelancer['availability'] ?? 'Available',
            ($freelancer['title'] ?? '') . ' ' . ($freelancer['bio'] ?? '')
        );
        $fl_vector = json_decode($flVectorJson, true);
    }

    $fl_rate = $fl_vector['hourly_rate'] ?? (float) $freelancer['hourly_rate'];

    // Get open jobs
    $stmt = $conn->prepare("SELECT id, client_id, title, description, budget, embedding_vector, created_at FROM jobs WHERE status = 'open' ORDER BY created_at DESC");
    $stmt->execute();
    $jobs = $stmt->get_result();
    $stmt->close();

    $matches = [];

    while ($job = $jobs->fetch_assoc()) {
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

        // Score using ai_engine (cosine similarity when dense vectors exist, Jaccard fallback)
        $scoreResult = ai_score_job_for_freelancer($fl_vector, $embedding, $fl_rate, $job['created_at']);

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
            'budget'          => (float) $job['budget'],
            'client_name'     => $client_name,
            'skill_names'     => $job_skill_names,
            'created_at'      => $job['created_at'],
            'total_score'     => $scoreResult['total_score'],
            'breakdown'       => $scoreResult['breakdown'],
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
