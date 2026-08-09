<?php
/**
 * AI Matching Engine
 * 
 * Centralized module for all AI matching logic:
 * - Keyword extraction (stop words removal + frequency analysis)
 * - Dense vector embedding generation (OpenAI API or local 256-dim fallback)
 * - Cosine similarity calculation
 * - Multi-factor scoring for job-freelancer matching
 * 
 * Backward compatible: works with existing embedding_vector/skills_vector
 * JSON that lacks the dense_vector key (falls back to skill-ID set intersection).
 */

// ── Stop Words (single source of truth) ───────────────────────────────────
const AI_STOP_WORDS = [
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

// ── Local Vector Dimensions ───────────────────────────────────────────────
const AI_LOCAL_DIMS = 256;

// ══════════════════════════════════════════════════════════════════════════
// SETTINGS
// ══════════════════════════════════════════════════════════════════════════

/**
 * Load AI settings from platform_settings.json.
 * Returns array with keys: ai_matching_enabled, ai_embedding_model, ai_auto_match, ai_min_similarity
 */
function ai_load_settings(): array
{
    $defaults = [
        'ai_matching_enabled' => 1,
        'ai_embedding_model'  => 'text-embedding-ada-002',
        'ai_auto_match'       => 0,
        'ai_min_similarity'   => 70,
    ];

    $settingsFile = __DIR__ . '/../config/platform_settings.json';
    if (!file_exists($settingsFile)) {
        $defaults['embedding_backend'] = ai_detect_embedding_backend();
        return $defaults;
    }

    $raw = json_decode(file_get_contents($settingsFile), true);
    if (!is_array($raw)) {
        $defaults['embedding_backend'] = ai_detect_embedding_backend();
        return $defaults;
    }

    return [
        'ai_matching_enabled' => (int) ($raw['ai_matching_enabled'] ?? $defaults['ai_matching_enabled']),
        'ai_embedding_model'  => (string) ($raw['ai_embedding_model'] ?? $defaults['ai_embedding_model']),
        'ai_auto_match'       => (int) ($raw['ai_auto_match'] ?? $defaults['ai_auto_match']),
        'ai_min_similarity'   => (int) ($raw['ai_min_similarity'] ?? $defaults['ai_min_similarity']),
        'embedding_backend'   => ai_detect_embedding_backend(),
    ];
}

/**
 * Detect whether OpenAI API is available for embedding generation.
 */
function ai_detect_embedding_backend(): string
{
    $apiKey = getenv('OPENAI_API_KEY');
    if (empty($apiKey) && defined('OPENAI_API_KEY')) {
        $apiKey = constant('OPENAI_API_KEY');
    }
    return (!empty($apiKey)) ? 'openai' : 'local';
}

/**
 * Check if dense vector matching should be used.
 * Returns true only if: matching is enabled AND both vectors have dense_vector key.
 */
function ai_should_use_dense_vectors(array $embeddingA, array $embeddingB): bool
{
    $settings = ai_load_settings();
    if (!$settings['ai_matching_enabled']) {
        return false;
    }
    return !empty($embeddingA['dense_vector']) && !empty($embeddingB['dense_vector']);
}

// ══════════════════════════════════════════════════════════════════════════
// KEYWORD EXTRACTION
// ══════════════════════════════════════════════════════════════════════════

/**
 * Extract keywords from text.
 * Tokenizes, lowercases, removes stop words, removes short words, returns top 20 by frequency.
 */
function ai_extract_keywords(string $text): array
{
    $text = strtolower($text);
    $text = preg_replace('/[^a-z0-9\s]/', ' ', $text);
    $words = preg_split('/\s+/', $text, -1, PREG_SPLIT_NO_EMPTY);

    $freq = [];
    foreach ($words as $word) {
        if (strlen($word) < 3 || in_array($word, AI_STOP_WORDS, true)) {
            continue;
        }
        $freq[$word] = ($freq[$word] ?? 0) + 1;
    }

    arsort($freq);
    return array_slice(array_keys($freq), 0, 20);
}

// ══════════════════════════════════════════════════════════════════════════
// DENSE VECTOR EMBEDDING GENERATION
// ══════════════════════════════════════════════════════════════════════════

/**
 * Generate a dense embedding vector for text + skills.
 * 
 * Strategy:
 *   1. Try OpenAI API if OPENAI_API_KEY env var or constant is set
 *   2. Fall back to local 256-dim hash-based vector
 * 
 * @param string $text       The text to embed (title + description, or bio)
 * @param array  $skillIds   Array of integer skill IDs
 * @return array             Dense vector as array of floats
 */
function ai_generate_dense_embedding(string $text, array $skillIds): array
{
    // Try OpenAI first
    $openaiVector = ai_call_openai_embedding($text);
    if ($openaiVector !== null) {
        return $openaiVector;
    }

    // Fallback to local vector
    return ai_generate_local_vector($text, $skillIds);
}

/**
 * Call OpenAI embeddings API.
 * Returns 1536-dim vector on success, null on failure.
 */
function ai_call_openai_embedding(string $text): ?array
{
    // Check for API key in environment or constant
    $apiKey = getenv('OPENAI_API_KEY');
    if (empty($apiKey) && defined('OPENAI_API_KEY')) {
        $apiKey = constant('OPENAI_API_KEY');
    }
    if (empty($apiKey)) {
        return null;
    }

    // Get model from settings
    $settings = ai_load_settings();
    $model = $settings['ai_embedding_model'] ?? 'text-embedding-ada-002';

    // Only use OpenAI models
    if (strpos($model, 'text-embedding') === false) {
        return null;
    }

    $payload = json_encode([
        'model' => $model,
        'input' => mb_substr($text, 0, 8000), // Truncate to avoid API limits
    ]);

    $ch = curl_init('https://api.openai.com/v1/embeddings');
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST           => true,
        CURLOPT_POSTFIELDS     => $payload,
        CURLOPT_HTTPHEADER     => [
            'Content-Type: application/json',
            'Authorization: Bearer ' . $apiKey,
        ],
        CURLOPT_TIMEOUT        => 10,
        CURLOPT_CONNECTTIMEOUT => 5,
    ]);

    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($httpCode !== 200 || $response === false) {
        return null;
    }

    $data = json_decode($response, true);
    if (empty($data['data'][0]['embedding'])) {
        return null;
    }

    return $data['data'][0]['embedding'];
}

/**
 * Generate a deterministic 256-dim local vector from text and skill IDs.
 * 
 * Uses a combination of:
 * - Skill ID positions (each skill activates specific dimensions)
 * - Text content hashing (title/description contribute to dimensions)
 * - TF-weighted keyword features
 * 
 * The vector is deterministic: same inputs always produce same output.
 */
function ai_generate_local_vector(string $text, array $skillIds): array
{
    $vector = array_fill(0, AI_LOCAL_DIMS, 0.0);

    // Layer 1: Skill ID features (each skill maps to 4 dimensions)
    foreach ($skillIds as $skillId) {
        $base = ($skillId * 7) % AI_LOCAL_DIMS;
        for ($i = 0; $i < 4; $i++) {
            $dim = ($base + $i) % AI_LOCAL_DIMS;
            $vector[$dim] += 1.0;
        }
    }

    // Layer 2: Text content features via hashing
    $text = strtolower($text);
    $text = preg_replace('/[^a-z0-9\s]/', ' ', $text);
    $words = preg_split('/\s+/', $text, -1, PREG_SPLIT_NO_EMPTY);

    // Count word frequencies (excluding stop words)
    $freq = [];
    foreach ($words as $word) {
        if (strlen($word) < 3 || in_array($word, AI_STOP_WORDS, true)) {
            continue;
        }
        $freq[$word] = ($freq[$word] ?? 0) + 1;
    }

    // Each keyword contributes to dimensions based on its hash
    $totalWords = count($freq);
    $idx = 0;
    foreach ($freq as $word => $count) {
        $hash = crc32($word);
        $base = abs($hash) % AI_LOCAL_DIMS;

        // TF weighting: more frequent = stronger signal, with diminishing returns
        $weight = log(1 + $count) * (1.0 / max(1, $totalWords));

        // Activate 3 dimensions per word
        for ($i = 0; $i < 3; $i++) {
            $dim = ($base + $i * 37) % AI_LOCAL_DIMS;
            $vector[$dim] += $weight;
        }
        $idx++;
        if ($idx >= 50) break; // Cap at 50 keywords
    }

    // Layer 3: Trigram features for short phrases
    if ($totalWords > 0) {
        $wordArr = array_keys($freq);
        $trigramCount = min(count($wordArr) - 1, 20);
        for ($i = 0; $i < $trigramCount; $i++) {
            $trigram = $wordArr[$i] . ($wordArr[$i + 1] ?? '');
            $hash = crc32($trigram);
            $dim = abs($hash) % AI_LOCAL_DIMS;
            $vector[$dim] += 0.5;
        }
    }

    // Normalize to unit vector (L2 norm)
    $magnitude = 0.0;
    foreach ($vector as $val) {
        $magnitude += $val * $val;
    }
    $magnitude = sqrt($magnitude);

    if ($magnitude > 0) {
        foreach ($vector as &$val) {
            $val = $val / $magnitude;
        }
        unset($val);
    }

    return $vector;
}

// ══════════════════════════════════════════════════════════════════════════
// COSINE SIMILARITY
// ══════════════════════════════════════════════════════════════════════════

/**
 * Calculate cosine similarity between two vectors.
 * 
 * Formula: cos(A, B) = (A . B) / (||A|| * ||B||)
 * 
 * @param array $vecA First vector (array of floats)
 * @param array $vecB Second vector (array of floats)
 * @return float Similarity score between 0.0 and 1.0
 */
function ai_cosine_similarity(array $vecA, array $vecB): float
{
    $lenA = count($vecA);
    $lenB = count($vecB);

    if ($lenA === 0 || $lenB === 0) {
        return 0.0;
    }

    // Use minimum length if vectors differ in size
    $len = min($lenA, $lenB);

    $dotProduct = 0.0;
    $magnitudeA = 0.0;
    $magnitudeB = 0.0;

    for ($i = 0; $i < $len; $i++) {
        $dotProduct += $vecA[$i] * $vecB[$i];
        $magnitudeA += $vecA[$i] * $vecA[$i];
        $magnitudeB += $vecB[$i] * $vecB[$i];
    }

    $magnitudeA = sqrt($magnitudeA);
    $magnitudeB = sqrt($magnitudeB);

    if ($magnitudeA == 0.0 || $magnitudeB == 0.0) {
        return 0.0;
    }

    return $dotProduct / ($magnitudeA * $magnitudeB);
}

// ══════════════════════════════════════════════════════════════════════════
// SKILL SIMILARITY (JACCARD — used as fallback/secondary signal)
// ══════════════════════════════════════════════════════════════════════════

/**
 * Calculate Jaccard similarity between two skill ID sets.
 * Used as fallback when dense vectors are not available.
 * 
 * Formula: J(A, B) = |A ∩ B| / |A ∪ B|
 */
function ai_skill_jaccard(array $skillsA, array $skillsB): float
{
    if (empty($skillsA) || empty($skillsB)) {
        return 0.0;
    }
    $common = count(array_intersect($skillsA, $skillsB));
    $unique = count(array_unique(array_merge($skillsA, $skillsB)));
    return $unique > 0 ? $common / $unique : 0.0;
}

// ══════════════════════════════════════════════════════════════════════════
// JOB EMBEDDING GENERATION
// ══════════════════════════════════════════════════════════════════════════

/**
 * Generate a complete job embedding vector (for storage in jobs.embedding_vector).
 * 
 * @param string $title       Job title
 * @param string $description Job description
 * @param array  $skillIds    Array of integer skill IDs from job_skills
 * @param float  $budget      Job budget (for normalization)
 * @return string             JSON-encoded embedding vector
 */
function ai_generate_job_embedding(string $title, string $description, array $skillIds, float $budget = 0): string
{
    $allText = $title . ' ' . $description;
    $keywords = ai_extract_keywords($allText);
    $titleWords = ai_extract_keywords($title);
    $wordCount = str_word_count(strtolower($allText));

    $budgetMax = 10000;
    $budgetNormalized = max(0.0, min(1.0, $budget / $budgetMax));

    $embedding = [
        'keywords'          => $keywords,
        'skill_ids'         => $skillIds,
        'word_count'        => $wordCount,
        'title_words'       => $titleWords,
        'budget_normalized' => round($budgetNormalized, 4),
    ];

    // Generate dense vector
    $embedding['dense_vector'] = ai_generate_dense_embedding($allText, $skillIds);

    return json_encode($embedding);
}

// ══════════════════════════════════════════════════════════════════════════
// FREELANCER VECTOR GENERATION
// ══════════════════════════════════════════════════════════════════════════

/**
 * Generate a complete freelancer vector (for storage in freelancers.skills_vector).
 * 
 * @param array  $skillIds       Array of integer skill IDs
 * @param array  $skillNames     Array of skill name strings
 * @param float  $hourlyRate     Freelancer hourly rate
 * @param int    $experienceYears Years of experience
 * @param string $availability   Availability status
 * @param string $text           Additional text for embedding (bio + title)
 * @return string                JSON-encoded vector
 */
function ai_generate_freelancer_vector(
    array $skillIds,
    array $skillNames,
    float $hourlyRate,
    int $experienceYears,
    string $availability = 'Available',
    string $text = ''
): string {
    if (empty($text)) {
        $text = implode(' ', $skillNames);
    }

    $vector = [
        'skill_ids'        => $skillIds,
        'skill_names'      => $skillNames,
        'hourly_rate'      => $hourlyRate,
        'experience_years' => $experienceYears,
        'availability'     => $availability,
    ];

    // Generate dense vector from skill names + any additional text
    $vector['dense_vector'] = ai_generate_dense_embedding($text, $skillIds);

    return json_encode($vector);
}

// ══════════════════════════════════════════════════════════════════════════
// SCORING FUNCTIONS
// ══════════════════════════════════════════════════════════════════════════

/**
 * Score a freelancer against a job.
 * 
 * Uses cosine similarity when dense vectors are available,
 * falls back to Jaccard skill intersection when they are not.
 * 
 * Scoring dimensions (max 100):
 *   - Skill/Semantic match: 50 points
 *   - Rate fit:            20 points
 *   - Experience:          15 points
 *   - Availability:        15 points
 * 
 * @param array  $jobEmbedding  Decoded job embedding_vector JSON
 * @param array  $flVector      Decoded freelancer skills_vector JSON
 * @param float  $jobBudget     Job budget amount
 * @return array                ['total_score' => float, 'breakdown' => [...]]
 */
function ai_score_freelancer_for_job(array $jobEmbedding, array $flVector, float $jobBudget): array
{
    $jobSkillIds = $jobEmbedding['skill_ids'] ?? [];
    $flSkillIds  = $flVector['skill_ids'] ?? [];

    // ── Skill/Semantic Match (max 50) ───────────────────────────────
    $skillScore = 0.0;

    if (ai_should_use_dense_vectors($jobEmbedding, $flVector)) {
        // Real cosine similarity on dense vectors
        $similarity = ai_cosine_similarity($jobEmbedding['dense_vector'], $flVector['dense_vector']);
        $skillScore = $similarity * 50.0;
    } elseif (!empty($jobSkillIds)) {
        // Fallback: Jaccard similarity on skill IDs (proportion of job skills matched)
        $common = count(array_intersect($flSkillIds, $jobSkillIds));
        $skillScore = ($common / count($jobSkillIds)) * 50.0;
    }

    // ── Rate Fit (max 20) ──────────────────────────────────────────
    $rateScore = 0.0;
    $flRate = $flVector['hourly_rate'] ?? 0;
    $estimatedHours = max(1, intval($jobEmbedding['word_count'] ?? 10));
    $maxRate = max(1, $jobBudget / $estimatedHours);

    if ($flRate <= $maxRate) {
        $rateScore = 20.0;
    } elseif ($flRate <= $maxRate * 1.5) {
        $rateScore = 10.0;
    }

    // ── Experience (max 15) ─────────────────────────────────────────
    $expYears = $flVector['experience_years'] ?? 0;
    $expScore = min(15.0, $expYears * 2.0);

    // ── Availability (max 15) ───────────────────────────────────────
    $availScore = (($flVector['availability'] ?? 'Available') === 'Available') ? 15.0 : 0.0;

    $totalScore = round($skillScore + $rateScore + $expScore + $availScore, 2);

    return [
        'total_score' => $totalScore,
        'breakdown'   => [
            'skill_match'  => round($skillScore, 2),
            'rate_fit'     => round($rateScore, 2),
            'experience'   => round($expScore, 2),
            'availability' => round($availScore, 2),
        ],
    ];
}

/**
 * Score a job against a freelancer.
 * 
 * Uses cosine similarity when dense vectors are available,
 * falls back to Jaccard skill intersection when they are not.
 * 
 * Scoring dimensions (max 90):
 *   - Skill/Semantic match: 60 points
 *   - Budget fit:          20 points
 *   - Recency:             10 points
 * 
 * @param array  $flVector      Decoded freelancer skills_vector JSON
 * @param array  $jobEmbedding  Decoded job embedding_vector JSON
 * @param float  $flRate        Freelancer hourly rate
 * @param string $jobCreatedAt  Job creation timestamp
 * @return array                ['total_score' => float, 'breakdown' => [...]]
 */
function ai_score_job_for_freelancer(array $flVector, array $jobEmbedding, float $flRate, string $jobCreatedAt = ''): array
{
    $jobSkillIds = $jobEmbedding['skill_ids'] ?? [];
    $flSkillIds  = $flVector['skill_ids'] ?? [];

    // ── Skill/Semantic Match (max 60) ───────────────────────────────
    $skillScore = 0.0;

    if (ai_should_use_dense_vectors($jobEmbedding, $flVector)) {
        $similarity = ai_cosine_similarity($jobEmbedding['dense_vector'], $flVector['dense_vector']);
        $skillScore = $similarity * 60.0;
    } elseif (!empty($jobSkillIds)) {
        $common = count(array_intersect($flSkillIds, $jobSkillIds));
        $skillScore = ($common / count($jobSkillIds)) * 60.0;
    }

    // ── Budget Fit (max 20) ─────────────────────────────────────────
    $budgetScore = 0.0;
    $jobBudget = (float) ($jobEmbedding['budget_normalized'] ?? 0) * 10000; // Denormalize
    $estimatedHours = max(1, intval($jobEmbedding['word_count'] ?? 10));
    $minRequiredRate = $flRate * $estimatedHours;

    if ($jobBudget >= $minRequiredRate) {
        $budgetScore = 20.0;
    } elseif ($jobBudget >= $minRequiredRate * 0.7) {
        $budgetScore = 10.0;
    }

    // ── Recency (max 10) ───────────────────────────────────────────
    $recencyScore = 0.0;
    if (!empty($jobCreatedAt)) {
        $ageDays = max(0, (time() - strtotime($jobCreatedAt)) / 86400);
        $recencyScore = max(0, 10.0 * (1 - $ageDays / 30));
    }

    $totalScore = round($skillScore + $budgetScore + $recencyScore, 2);

    return [
        'total_score' => $totalScore,
        'breakdown'   => [
            'skill_match' => round($skillScore, 2),
            'budget_fit'  => round($budgetScore, 2),
            'recency'     => round($recencyScore, 2),
        ],
    ];
}
