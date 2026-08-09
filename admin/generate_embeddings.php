<?php
/**
 * Batch Generate Embeddings
 * POST only, CSRF protected.
 * Now uses ai_engine.php for dense vector generation (cosine similarity support).
 */

require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../auth/auth.php';
require_once __DIR__ . '/../includes/ai_engine.php';
require_role('admin');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    set_flash('error', 'Invalid request method.');
    redirect('/jobhub/admin/ai_matching.php');
}

if (!verify_csrf_token()) {
    set_flash('error', 'Invalid CSRF token. Please try again.');
    redirect('/jobhub/admin/ai_matching.php');
}

// ── Generate Embeddings for Jobs ───────────────────────────────────────────
$jobs_generated = 0;
$r = $conn->query("SELECT id, title, description, budget FROM jobs WHERE embedding_vector IS NULL OR embedding_vector = '' OR JSON_EXTRACT(embedding_vector, '$.dense_vector') IS NULL");
while ($job = $r->fetch_assoc()) {
    $stmt = $conn->prepare('SELECT skill_id FROM job_skills WHERE job_id = ?');
    $stmt->bind_param('i', $job['id']);
    $stmt->execute();
    $res = $stmt->get_result();
    $skill_ids = [];
    while ($row = $res->fetch_assoc()) {
        $skill_ids[] = (int) $row['skill_id'];
    }
    $stmt->close();

    // Generate embedding using ai_engine (includes dense vector for cosine similarity)
    $embedding = ai_generate_job_embedding($job['title'], $job['description'] ?? $job['title'], $skill_ids, (float) $job['budget']);

    $u = $conn->prepare('UPDATE jobs SET embedding_vector = ? WHERE id = ?');
    $u->bind_param('si', $embedding, $job['id']);
    $u->execute();
    $u->close();
    $jobs_generated++;
}

// ── Generate Vectors for Freelancers ───────────────────────────────────────
$freelancers_generated = 0;
$r = $conn->query("
    SELECT f.id, f.hourly_rate, f.years_of_experience, f.availability, f.title, f.bio
    FROM freelancers f
    WHERE f.skills_vector IS NULL OR f.skills_vector = '' OR JSON_EXTRACT(f.skills_vector, '$.dense_vector') IS NULL
");
while ($fl = $r->fetch_assoc()) {
    $stmt = $conn->prepare('
        SELECT fs.skill_id, s.skill_name
        FROM freelancer_skills fs
        JOIN skills s ON fs.skill_id = s.id
        WHERE fs.freelancer_id = ?
    ');
    $stmt->bind_param('i', $fl['id']);
    $stmt->execute();
    $res = $stmt->get_result();
    $skill_ids = [];
    $skill_names = [];
    while ($row = $res->fetch_assoc()) {
        $skill_ids[] = (int) $row['skill_id'];
        $skill_names[] = $row['skill_name'];
    }
    $stmt->close();

    // Generate vector using ai_engine (includes dense vector for cosine similarity)
    $embeddingText = ($fl['title'] ?? '') . ' ' . ($fl['bio'] ?? '');
    $vector = ai_generate_freelancer_vector(
        $skill_ids,
        $skill_names,
        (float) $fl['hourly_rate'],
        (int) $fl['years_of_experience'],
        $fl['availability'] ?? 'Available',
        $embeddingText
    );

    $u = $conn->prepare('UPDATE freelancers SET skills_vector = ? WHERE id = ?');
    $u->bind_param('si', $vector, $fl['id']);
    $u->execute();
    $u->close();
    $freelancers_generated++;
}

$conn->close();

set_flash('success', "Embeddings generated successfully! Jobs: {$jobs_generated}, Freelancers: {$freelancers_generated}");
redirect('/jobhub/admin/ai_matching.php');
