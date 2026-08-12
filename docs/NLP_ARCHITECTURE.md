# NLP Architecture — JobHub AI Matching Engine

> Knowledge-sharing document for the development team.
> Explains every NLP concept used in the project, where it lives, and how it works.

---

## Table of Contents

1. [Overview](#1-overview)
2. [Pipeline Diagram](#2-pipeline-diagram)
3. [NLP Algorithms Explained](#3-nlp-algorithms-explained)
   - 3a. Tokenization & Stop Words
   - 3b. TF Weighting
   - 3c. Trigram Features
   - 3d. Cosine Similarity
   - 3e. Jaccard Similarity
   - 3f. Dense Embeddings (OpenAI vs Local)
4. [Scoring Model](#4-scoring-model)
5. [File Reference Table](#5-file-reference-table)
6. [Database Schema](#6-database-schema)
7. [Configuration](#7-configuration)
8. [How to Extend](#8-how-to-extend)

---

## 1. Overview

The AI matching system connects **freelancers** to **jobs** using Natural Language Processing. It works entirely in PHP — no Python, no external ML libraries.

**What it does:**
- Takes a job's title + description and a freelancer's skills + bio
- Converts both into numerical vectors (arrays of floats)
- Measures how similar those vectors are using cosine similarity
- Adds secondary signals (rate, experience, availability, recency)
- Ranks results by a composite score (0–100)

**Two embedding backends:**
| Backend | Vector Size | When Used |
|---|---|---|
| OpenAI API | 1536 dimensions | `OPENAI_API_KEY` is set |
| Local hash | 256 dimensions | Fallback (no API key) |

---

## 2. Pipeline Diagram

```
┌─────────────────────────────────────────────────────────────────┐
│                     INPUT DATA                                   │
│  Job: title, description, skill_ids, budget                     │
│  Freelancer: skill_ids, skill_names, bio, rate, experience      │
└──────────────────────┬──────────────────────┬───────────────────┘
                       │                      │
                       ▼                      ▼
┌──────────────────────────────┐ ┌──────────────────────────────┐
│  ai_extract_keywords()       │ │  ai_extract_keywords()       │
│  ↓ Tokenize (regex split)    │ │  ↓ Tokenize (regex split)    │
│  ↓ Remove stop words         │ │  ↓ Remove stop words         │
│  ↓ Remove words < 3 chars    │ │  ↓ Remove words < 3 chars    │
│  ↓ Count frequency           │ │  ↓ Count frequency           │
│  ↓ Return top 20 keywords    │ │  ↓ Return top 20 keywords    │
└──────────────┬───────────────┘ └──────────────┬───────────────┘
               │                                │
               ▼                                ▼
┌──────────────────────────────┐ ┌──────────────────────────────┐
│  ai_generate_job_embedding() │ │  ai_generate_freelancer_     │
│  ↓ Build embedding JSON      │ │     vector()                 │
│  ↓ Generate dense_vector     │ │  ↓ Build vector JSON         │
│  ↓ Store in jobs.            │ │  ↓ Generate dense_vector     │
│    embedding_vector          │ │  ↓ Store in freelancers.     │
│  (JSON column)               │ │    skills_vector (JSON col)  │
└──────────────┬───────────────┘ └──────────────┬───────────────┘
               │                                │
               └──────────┬─────────────────────┘
                          ▼
         ┌────────────────────────────────┐
         │  ai_cosine_similarity()        │
         │  cos(A,B) = (A·B) / (‖A‖·‖B‖) │
         │  Returns: 0.0 to 1.0           │
         └──────────────┬─────────────────┘
                        ▼
         ┌────────────────────────────────┐
         │  ai_score_freelancer_for_job() │
         │  or ai_score_job_for_          │
         │      freelancer()              │
         │  Multi-factor scoring          │
         │  Skill + Rate + Exp + Avail    │
         └──────────────┬─────────────────┘
                        ▼
         ┌────────────────────────────────┐
         │  Sort by total_score DESC      │
         │  Return top matches            │
         └────────────────────────────────┘
```

---

## 3. NLP Algorithms Explained

### 3a. Tokenization & Stop Words

**File:** `includes/ai_engine.php:103-119`

**What it does:** Breaks raw text into individual words and removes noise.

**Step-by-step:**
1. Lowercase everything: `"Web Developer"` → `"web developer"`
2. Strip non-alphanumeric chars: `"Node.js!"` → `"node js "`
3. Split on whitespace: `"web developer"` → `["web", "developer"]`
4. Remove words shorter than 3 characters: `"a"`, `"is"`, `"to"` → gone
5. Remove 86 English stop words (defined in `AI_STOP_WORDS` constant, lines 16-27)
6. Count word frequencies
7. Return top 20 keywords sorted by frequency

**Stop words list** (line 16-27):
```
the, is, at, which, on, a, an, and, or, but, in, with, to, for,
of, not, no, can, had, has, was, were, are, be, been, being,
have, having, do, does, did, doing, will, would, could, should,
may, might, shall, must, that, this, these, those, it, its,
from, by, as, if, then, than, so, just, also, about, into,
over, after, before, between, under, above, out, off, up, down,
all, each, every, both, few, more, most, other, some, such, any,
only, same, own, too, very, here, there, when, where, why, how,
what, who, whom, whose, through, during, until, while, again,
further, once, because, nor, against, during, once, twice
```

**Code reference:**
```php
// includes/ai_engine.php:103-119
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
```

---

### 3b. TF Weighting (Term Frequency)

**File:** `includes/ai_engine.php:243-260`

**What it does:** Gives more weight to words that appear more often in the text, but with diminishing returns.

**Formula:**
```
weight = log(1 + count) × (1 / max(1, totalWords))
```

**Why log-dampened?** If a word appears 10 times vs 1 time, it's more important — but not 10x more important. `log(1+10) = 2.4` vs `log(1+1) = 0.69`. The word is ~3.5x more important, not 10x.

**Where it's used:** Each keyword activates 3 dimensions in the 256-dim local vector (line 254-257). Higher weight = stronger signal in those dimensions.

**Code reference:**
```php
// includes/ai_engine.php:250-251
$weight = log(1 + $count) * (1.0 / max(1, $totalWords));

// Each keyword activates 3 dimensions
for ($i = 0; $i < 3; $i++) {
    $dim = ($base + $i * 37) % AI_LOCAL_DIMS;
    $vector[$dim] += $weight;
}
```

---

### 3c. Trigram Features

**File:** `includes/ai_engine.php:262-272`

**What it does:** Captures consecutive word pairs to detect short phrases like "machine learning" or "web developer".

**How it works:**
1. Take the keyword list: `["machine", "learning", "neural", "network"]`
2. Create pairs: `"machinelearning"`, `"learningneural"`, `"neuralnetwork"`
3. Hash each pair with `crc32()` to get a dimension index
4. Add 0.5 to that dimension

This helps the vector capture word-order information that single-word features miss.

**Code reference:**
```php
// includes/ai_engine.php:262-272
$trigramCount = min(count($wordArr) - 1, 20);
for ($i = 0; $i < $trigramCount; $i++) {
    $trigram = $wordArr[$i] . ($wordArr[$i + 1] ?? '');
    $hash = crc32($trigram);
    $dim = abs($hash) % AI_LOCAL_DIMS;
    $vector[$dim] += 0.5;
}
```

---

### 3d. Cosine Similarity

**File:** `includes/ai_engine.php:304-334`

**What it does:** Measures the angle between two vectors. If two vectors point in the same direction, they're similar.

**Formula:**
```
cos(A, B) = (A · B) / (‖A‖ × ‖B‖)
```

- `A · B` = dot product (sum of element-wise multiplication)
- `‖A‖` = magnitude (square root of sum of squared elements)
- Result range: `0.0` (completely different) to `1.0` (identical)

**Visual analogy:**
- Two vectors pointing the same way → angle = 0° → cos = 1.0 → perfect match
- Two vectors at 90° → cos = 0.0 → no match
- Two vectors pointing opposite ways → cos = -1.0 → opposite (clamped to 0.0 in our code)

**Why cosine over Euclidean distance?** Cosine is independent of vector magnitude. A short bio and a long bio about the same topic will have similar cosine similarity, even if one vector is "longer".

**Code reference:**
```php
// includes/ai_engine.php:304-334
function ai_cosine_similarity(array $vecA, array $vecB): float
{
    $len = min(count($vecA), count($vecB));

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
```

---

### 3e. Jaccard Similarity (Fallback)

**File:** `includes/ai_engine.php:346-354`

**What it does:** Measures overlap between two sets of skill IDs. Used when dense vectors aren't available.

**Formula:**
```
J(A, B) = |A ∩ B| / |A ∪ B|
```

**Example:**
- Freelancer skills: `{1, 2, 3, 4}`
- Job skills: `{2, 3, 5}`
- Intersection: `{2, 3}` → size 2
- Union: `{1, 2, 3, 4, 5}` → size 5
- Jaccard = 2/5 = **0.4**

**Code reference:**
```php
// includes/ai_engine.php:346-354
function ai_skill_jaccard(array $skillsA, array $skillsB): float
{
    if (empty($skillsA) || empty($skillsB)) {
        return 0.0;
    }
    $common = count(array_intersect($skillsA, $skillsB));
    $unique = count(array_unique(array_merge($skillsA, $skillsB)));
    return $unique > 0 ? $common / $unique : 0.0;
}
```

**When it's used:** In `ai_score_freelancer_for_job()` (line 467-470) and `ai_score_job_for_freelancer()` (line 533-535) — only when `ai_should_use_dense_vectors()` returns false (no `dense_vector` key in the stored JSON).

---

### 3f. Dense Embeddings

**File:** `includes/ai_engine.php:136-289`

Two backends for converting text into a fixed-size numerical vector:

#### OpenAI Backend (1536 dimensions)

**File:** `includes/ai_engine.php:152-204`

- Calls `https://api.openai.com/v1/embeddings`
- Models: `text-embedding-ada-002`, `text-embedding-3-small`, `text-embedding-3-large`
- Input truncated to 8000 chars
- Returns a 1536-element float array
- Requires `OPENAI_API_KEY` env var or constant

#### Local Hash Backend (256 dimensions)

**File:** `includes/ai_engine.php:216-289`

Three layers build the vector:

| Layer | What It Does | Dimensions Affected |
|---|---|---|
| **Skill IDs** (line 220-227) | Each skill ID maps to 4 dims via `(id * 7) % 256` | Skill-specific |
| **TF-weighted keywords** (line 243-260) | Each keyword hashes to 3 dims, weighted by frequency | Content-aware |
| **Trigrams** (line 262-272) | Word pairs hash to 1 dim each | Phrase-aware |

**Final step:** L2 normalization to unit vector (lines 274-286):
```
magnitude = sqrt(sum(v[i]²))
v[i] = v[i] / magnitude
```

This ensures all vectors have the same length, making cosine similarity meaningful.

---

## 4. Scoring Model

### Freelancer-for-Job Scoring (max 100 points)

**File:** `includes/ai_engine.php:455-503`

| Dimension | Max Points | How It's Calculated |
|---|---|---|
| **Skill/Semantic Match** | 50 | Cosine similarity × 50 (or Jaccard fallback) |
| **Rate Fit** | 20 | 20 if rate ≤ budget/hours, 10 if ≤ 1.5×, else 0 |
| **Experience** | 15 | min(15, years × 2) |
| **Availability** | 15 | 15 if "Available", else 0 |

### Job-for-Freelancer Scoring (max 90 points)

**File:** `includes/ai_engine.php:522-567`

| Dimension | Max Points | How It's Calculated |
|---|---|---|
| **Skill/Semantic Match** | 60 | Cosine similarity × 60 (or Jaccard fallback) |
| **Budget Fit** | 20 | 20 if budget ≥ rate × hours, 10 if ≥ 0.7×, else 0 |
| **Recency** | 10 | max(0, 10 × (1 - ageDays/30)) — decays over 30 days |

---

## 5. File Reference Table

| File | Role | Key Functions / Lines |
|---|---|---|
| `includes/ai_engine.php` | **Core NLP engine** — all algorithms | `ai_extract_keywords()` :103, `ai_cosine_similarity()` :304, `ai_skill_jaccard()` :346, `ai_generate_local_vector()` :216, `ai_call_openai_embedding()` :152, `ai_score_freelancer_for_job()` :455, `ai_score_job_for_freelancer()` :522 |
| `api/ai_matching.php` | REST API for matching | Endpoints: `?action=match_job` (line 118), `?action=match_freelancer` (line 226) |
| `client/recommended_freelancers.php` | Client dashboard — top 10 freelancers for a job | Loads job embedding, scores all freelancers, sorts by total_score (lines 37-119) |
| `freelancer/recommended_jobs.php` | Freelancer dashboard — top 20 jobs | Loads freelancer vector, scores all open jobs, sorts by total_score (lines 31-136) |
| `freelancer/job_search_api.php` | Full-text job search | `MATCH...AGAINST` in BOOLEAN MODE (line 61), skill ID intersection (line 67) |
| `admin/ai_monitor.php` | Monitoring dashboard | Vector coverage stats (line 39-85), similarity scan results (line 700-723) |
| `admin/ai_matching.php` | Match results UI | Score breakdown display (lines 191, 232, 259, 294-295, 325, 354-355) |
| `admin/generate_embeddings.php` | Batch embedding generator | Generates vectors for all jobs/freelancers (lines 37, 72) |
| `admin/settings.php` | AI config UI | Embedding model selector (line 947-952), similarity threshold (line 956-958) |
| `config/schema.sql` | Database schema | `freelancers.skills_vector JSON` (line 31), `jobs.embedding_vector JSON` (line 109), FULLTEXT index (line 122) |
| `config/platform_settings.json` | Default config | `ai_embedding_model` (line 36), `ai_min_similarity` (line 38) |

---

## 6. Database Schema

### Vector Storage Columns

```sql
-- Freelancer embedding (skills + bio + rate + experience)
ALTER TABLE freelancers ADD COLUMN skills_vector JSON DEFAULT NULL;
-- Structure: { skill_ids: [...], skill_names: [...], hourly_rate: float,
--              experience_years: int, availability: string,
--              dense_vector: [float, float, ...] }

-- Job embedding (title + description + skills + budget)
ALTER TABLE jobs ADD COLUMN embedding_vector JSON DEFAULT NULL;
-- Structure: { keywords: [...], skill_ids: [...], word_count: int,
--              title_words: [...], budget_normalized: float,
--              dense_vector: [float, float, ...] }
```

### Full-Text Search Index

```sql
ALTER TABLE jobs ADD FULLTEXT ft_job_search (title, description);
-- Used in: freelancer/job_search_api.php:61
-- Query: MATCH(title, description) AGAINST('+php +developer' IN BOOLEAN MODE)
```

---

## 7. Configuration

**File:** `config/platform_settings.json`

| Setting | Default | Description |
|---|---|---|
| `ai_matching_enabled` | `1` | Master switch for AI matching |
| `ai_embedding_model` | `text-embedding-ada-002` | OpenAI model or `Local Model` for hash backend |
| `ai_auto_match` | `0` | Auto-assign matches on job creation |
| `ai_min_similarity` | `70` | Minimum cosine similarity (0-100) to show a match |

**Backend detection** (`includes/ai_engine.php:73-80`):
- If `OPENAI_API_KEY` is set → OpenAI backend (1536-dim)
- Otherwise → Local hash backend (256-dim)

**Admin UI:** `admin/settings.php:947-958` — dropdown for model selection, slider for similarity threshold.

---

## 8. How to Extend

### Add a new scoring dimension

In `includes/ai_engine.php`, modify `ai_score_freelancer_for_job()` or `ai_score_job_for_freelancer()`:

```php
// 1. Calculate your new score (e.g., portfolio quality)
$portfolioScore = 0.0;
// ... your logic here ...
$portfolioScore = min(10.0, $portfolioScore); // cap it

// 2. Add to total
$totalScore = round($skillScore + $rateScore + $expScore + $availScore + $portfolioScore, 2);

// 3. Add to breakdown array
'breakdown' => [
    'skill_match'  => round($skillScore, 2),
    'rate_fit'     => round($rateScore, 2),
    'experience'   => round($expScore, 2),
    'availability' => round($availScore, 2),
    'portfolio'    => round($portfolioScore, 2),  // new
],
```

**Note:** You'll need to reduce other dimensions' max points to keep the total at 100.

### Swap the similarity algorithm

Replace calls to `ai_cosine_similarity()` in the scoring functions. For example, to use Euclidean distance:

```php
function ai_euclidean_distance(array $vecA, array $vecB): float
{
    $sum = 0.0;
    $len = min(count($vecA), count($vecB));
    for ($i = 0; $i < $len; $i++) {
        $diff = $vecA[$i] - $vecB[$i];
        $sum += $diff * $diff;
    }
    // Convert distance to similarity (0-1 range)
    return 1.0 / (1.0 + sqrt($sum));
}
```

### Add stop words

Edit the `AI_STOP_WORDS` constant at `includes/ai_engine.php:16-27`.

### Change local vector dimensions

Change `AI_LOCAL_DIMS` constant at `includes/ai_engine.php:30`. All dimension calculations (`% AI_LOCAL_DIMS`) will adapt automatically.

---

## Glossary

| Term | Definition |
|---|---|
| **Tokenization** | Splitting text into individual words (tokens) |
| **Stop Words** | Common words ("the", "is", "and") removed to reduce noise |
| **TF (Term Frequency)** | How often a word appears in a document |
| **Trigram** | A sequence of two consecutive words used as a feature |
| **Dense Vector** | A fixed-size array of floats representing text meaning |
| **Sparse Vector** | A vector with mostly zeros (our keyword frequencies before hashing) |
| **Cosine Similarity** | Angle-based measure of vector similarity (0 = different, 1 = same) |
| **Jaccard Similarity** | Set overlap measure (0 = no overlap, 1 = identical sets) |
| **L2 Normalization** | Scaling a vector to unit length (magnitude = 1) |
| **Embedding** | Converting text/objects into a numerical vector |
| **FULLTEXT Index** | MySQL index enabling natural language search queries |
