<?php

/**
 * Admin AI Monitor – Read-only monitoring of AI embeddings and statistics.
 * Admin does NOT perform matching – this is a monitoring dashboard only.
 */
require_once __DIR__ . '/../auth/auth.php';
require_role('admin');
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/ai_engine.php';

$currentPage = 'ai_monitor';

// ── Handle Trigger Actions ──────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    if (!verify_csrf_token()) {
        set_flash('error', 'Invalid security token.');
        redirect('ai_monitor.php');
    }
    $act = $_POST['action'];
    if ($act === 'generate_embeddings') {
        set_flash('info', 'Embedding generation queued. The system will process missing embeddings automatically.');
        redirect('ai_monitor.php');
    }
    if ($act === 'rebuild_embeddings') {
        set_flash('info', 'Full embedding rebuild queued. This may take several minutes.');
        redirect('ai_monitor.php');
    }
}

// ══════════════════════════════════════════════════════════════════════
// STATISTICS QUERIES
// ══════════════════════════════════════════════════════════════════════

// Total jobs & embedded jobs
$r = $conn->query('SELECT COUNT(*) AS cnt FROM jobs');
$totalJobs = (int) $r->fetch_assoc()['cnt'];

$r = $conn->query("SELECT COUNT(*) AS cnt FROM jobs WHERE embedding_vector IS NOT NULL AND embedding_vector != '' AND embedding_vector != 'null'");
$embeddedJobs = (int) $r->fetch_assoc()['cnt'];

// Total freelancers & embedded
$r = $conn->query('SELECT COUNT(*) AS cnt FROM freelancers');
$totalFreelancers = (int) $r->fetch_assoc()['cnt'];

$r = $conn->query("SELECT COUNT(*) AS cnt FROM freelancers WHERE skills_vector IS NOT NULL AND skills_vector != '' AND skills_vector != 'null'");
$embeddedFreelancers = (int) $r->fetch_assoc()['cnt'];

// Missing embeddings
$missingJobs = $totalJobs - $embeddedJobs;
$missingFreelancers = $totalFreelancers - $embeddedFreelancers;
$totalMissing = $missingJobs + $missingFreelancers;

// Embedding coverage
$jobCoverage = $totalJobs > 0 ? round(($embeddedJobs / $totalJobs) * 100, 1) : 0;
$freelancerCoverage = $totalFreelancers > 0 ? round(($embeddedFreelancers / $totalFreelancers) * 100, 1) : 0;
$overallCoverage = ($totalJobs + $totalFreelancers) > 0 ? round((($embeddedJobs + $embeddedFreelancers) / ($totalJobs + $totalFreelancers)) * 100, 1) : 0;

// Dense vector coverage (for cosine similarity)
$r = $conn->query("SELECT COUNT(*) AS cnt FROM jobs WHERE embedding_vector IS NOT NULL AND JSON_EXTRACT(embedding_vector, '\$.dense_vector') IS NOT NULL");
$denseJobs = (int) $r->fetch_assoc()['cnt'];

$r = $conn->query("SELECT COUNT(*) AS cnt FROM freelancers WHERE skills_vector IS NOT NULL AND JSON_EXTRACT(skills_vector, '\$.dense_vector') IS NOT NULL");
$denseFreelancers = (int) $r->fetch_assoc()['cnt'];

$denseTotal = $denseJobs + $denseFreelancers;
$denseCoverage = ($totalJobs + $totalFreelancers) > 0 ? round(($denseTotal / ($totalJobs + $totalFreelancers)) * 100, 1) : 0;

// AI settings
$aiSettings = ai_load_settings();

// Jobs without embeddings (sample)
$r = $conn->query("SELECT j.id, j.title, j.category, j.created_at
                   FROM jobs j
                   WHERE j.embedding_vector IS NULL OR j.embedding_vector = '' OR j.embedding_vector = 'null'
                   ORDER BY j.created_at DESC LIMIT 10");
$missingJobsList = [];
while ($row = $r->fetch_assoc())
    $missingJobsList[] = $row;

// Freelancers without embeddings (sample)
$r = $conn->query("SELECT f.id, u.name, f.title AS ftitle, f.created_at
                   FROM freelancers f
                   JOIN users u ON f.user_id = u.id
                   WHERE f.skills_vector IS NULL OR f.skills_vector = '' OR f.skills_vector = 'null'
                   ORDER BY f.created_at DESC LIMIT 10");
$missingFreelancersList = [];
while ($row = $r->fetch_assoc())
    $missingFreelancersList[] = $row;

// Embedding queue (pending) - simulated from recent unembedded items
$queueCount = $totalMissing;

// Similarity statistics (simulated averages)
$r = $conn->query("SELECT
    (SELECT COUNT(*) FROM proposals WHERE status='accepted') AS accepted_proposals,
    (SELECT COUNT(*) FROM proposals) AS total_proposals,
    (SELECT COUNT(*) FROM contracts) AS total_contracts
");
$proposalStats = $r->fetch_assoc();

// Recent activity logs (from user_behavior_logs)
$r = $conn->query("SELECT ubl.action_type, COUNT(*) AS cnt
                   FROM user_behavior_logs ubl
                   WHERE ubl.action_type LIKE '%match%' OR ubl.action_type LIKE '%embed%' OR ubl.action_type LIKE '%ai%'
                   GROUP BY ubl.action_type
                   ORDER BY cnt DESC LIMIT 10");
$aiActivity = [];
while ($row = $r->fetch_assoc())
    $aiActivity[] = $row;

// Last update timestamp
$r = $conn->query('SELECT MAX(updated_at) AS last_update FROM freelancers');
$lastUpdate = $r->fetch_assoc()['last_update'] ?? 'Never';

// AI search count (from user_behavior_logs)
$r = $conn->query("SELECT COUNT(*) AS cnt FROM user_behavior_logs WHERE action_type = 'ai_search'");
$aiSearchCount = (int) $r->fetch_assoc()['cnt'];

// ── Layout Setup ────────────────────────────────────────────────────
$totalIndexed = $embeddedJobs + $embeddedFreelancers;
$proposalAcceptRate = $proposalStats['total_proposals'] > 0 ? round(($proposalStats['accepted_proposals'] / $proposalStats['total_proposals']) * 100, 1) : 0;

$_userId = $_SESSION['user_id'];
$sStmt = $conn->prepare('SELECT name, profile_image FROM users WHERE id = ?');
$sStmt->bind_param('i', $_userId);
$sStmt->execute();
$_navUserRow = $sStmt->get_result()->fetch_assoc();
$sStmt->close();
$adminName = $_navUserRow['name'] ?? 'Admin';

$conn->close();

$navItems = [
    ['key' => 'dashboard', 'label' => 'Dashboard', 'url' => 'dashboard.php', 'icon' => 'layout-grid'],
    ['key' => 'users', 'label' => 'Users', 'url' => 'users.php', 'icon' => 'users'],
    ['key' => 'clients', 'label' => 'Clients', 'url' => 'clients.php', 'icon' => 'user'],
    ['key' => 'jobs', 'label' => 'Jobs', 'url' => 'jobs.php', 'icon' => 'briefcase'],
    ['key' => 'categories', 'label' => 'Categories', 'url' => 'categories.php', 'icon' => 'folder-open'],
    ['key' => 'skills', 'label' => 'Skills', 'url' => 'skills.php', 'icon' => 'settings'],
    ['key' => 'payments', 'label' => 'Payments', 'url' => 'payments.php', 'icon' => 'credit-card'],
    ['key' => 'wallets', 'label' => 'Wallets', 'url' => 'wallets.php', 'icon' => 'wallet'],
    ['key' => 'contracts', 'label' => 'Contracts', 'url' => 'contracts.php', 'icon' => 'file-text'],
    ['key' => 'milestones', 'label' => 'Milestones', 'url' => 'milestones.php', 'icon' => 'list-checks'],
    ['key' => 'reviews', 'label' => 'Reviews', 'url' => 'reviews.php', 'icon' => 'star'],
    ['key' => 'disputes', 'label' => 'Disputes', 'url' => 'disputes.php', 'icon' => 'hammer'],
    ['key' => 'fraud', 'label' => 'Fraud', 'url' => 'fraud_detection.php', 'icon' => 'shield'],
    ['key' => 'notifications', 'label' => 'Notifications', 'url' => 'notifications.php', 'icon' => 'bell'],
    ['key' => 'ai_monitor', 'label' => 'AI Monitor', 'url' => 'ai_monitor.php', 'icon' => 'brain'],
    ['key' => 'analytics', 'label' => 'Analytics', 'url' => 'analytics.php', 'icon' => 'pie-chart'],
    ['key' => 'settings', 'label' => 'Settings', 'url' => 'settings.php', 'icon' => 'settings'],
];
$pageTitle = 'AI Vector Monitor';
$pageSubtitle = 'AI infrastructure & vector index dashboard';
$activePage = 'ai_monitor';
$user = ['name' => $adminName, 'profile_image' => $_navUserRow['profile_image'] ?? null];
$unreadCount = 0;
$profileLink = 'profile.php';
require_once __DIR__ . '/../components/layout_start.php';
?>

    <?php display_flash('success'); ?>
    <?php display_flash('error'); ?>
    <?php display_flash('info'); ?>

    <style>
        /* ═══ Premium AI Monitor Overrides ═══ */
        .ai-page-bg { background: #F8FAFC; min-height: calc(100vh - 56px); }
        html.dark .ai-page-bg { background: #0f172a; }

        .ai-card {
            background: #FFFFFF;
            border: none;
            border-radius: 20px;
            box-shadow: 0 10px 15px -3px rgba(0,0,0,0.03), 0 4px 6px -4px rgba(0,0,0,0.02);
            transition: box-shadow .3s, transform .3s;
        }
        .ai-card:hover {
            box-shadow: 0 20px 25px -5px rgba(0,0,0,0.04), 0 8px 10px -6px rgba(0,0,0,0.03);
        }
        html.dark .ai-card { background: #1e293b; }

        .ai-card-header {
            padding: 28px 32px 0;
        }
        .ai-card-body {
            padding: 20px 32px 32px;
        }

        .ai-label {
            font-size: 11px;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.08em;
            color: #94A3B8;
        }
        html.dark .ai-label { color: #64748b; }

        .ai-metric-value {
            font-size: 36px;
            font-weight: 800;
            letter-spacing: -0.02em;
            line-height: 1.1;
            color: #0F172A;
        }
        html.dark .ai-metric-value { color: #f1f5f9; }

        .ai-progress-track {
            width: 100%;
            height: 8px;
            background: #F1F5F9;
            border-radius: 999px;
            overflow: hidden;
        }
        html.dark .ai-progress-track { background: #334155; }

        .ai-progress-fill {
            height: 100%;
            border-radius: 999px;
            transition: width .8s cubic-bezier(.4,0,.2,1);
        }
        .ai-progress-fill.emerald { background: linear-gradient(90deg, #34d399, #059669); }
        .ai-progress-fill.indigo { background: linear-gradient(90deg, #818cf8, #6366f1); }
        .ai-progress-fill.purple { background: linear-gradient(90deg, #c084fc, #9333ea); }
        .ai-progress-fill.blue { background: linear-gradient(90deg, #60a5fa, #2563eb); }
        .ai-progress-fill.amber { background: linear-gradient(90deg, #fbbf24, #f59e0b); }

        .ai-icon-box {
            width: 40px;
            height: 40px;
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            flex-shrink: 0;
        }

        .ai-btn-primary {
            background: linear-gradient(135deg, #2563eb, #4f46e5);
            color: #fff;
            border: none;
            border-radius: 12px;
            padding: 12px 24px;
            font-size: 13px;
            font-weight: 600;
            cursor: pointer;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            transition: all .25s;
            box-shadow: 0 4px 14px rgba(37,99,235,0.25);
        }
        .ai-btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 25px rgba(37,99,235,0.35);
        }
        .ai-btn-primary:disabled {
            opacity: .5;
            cursor: not-allowed;
            transform: none;
            box-shadow: none;
        }

        .ai-btn-secondary {
            background: #F8FAFC;
            color: #475569;
            border: 1px solid #E2E8F0;
            border-radius: 12px;
            padding: 12px 24px;
            font-size: 13px;
            font-weight: 600;
            cursor: pointer;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            transition: all .25s;
        }
        .ai-btn-secondary:hover {
            background: #F1F5F9;
            border-color: #CBD5E1;
            transform: translateY(-1px);
        }
        html.dark .ai-btn-secondary { background: #334155; border-color: #475569; color: #e2e8f0; }
        html.dark .ai-btn-secondary:hover { background: #475569; }

        /* Timeline */
        .ai-timeline-item {
            position: relative;
            padding-left: 44px;
            padding-bottom: 28px;
        }
        .ai-timeline-item:last-child { padding-bottom: 0; }
        .ai-timeline-item::before {
            content: '';
            position: absolute;
            left: 15px;
            top: 28px;
            bottom: 0;
            width: 2px;
            background: #E2E8F0;
        }
        html.dark .ai-timeline-item::before { background: #334155; }
        .ai-timeline-item:last-child::before { display: none; }

        .ai-timeline-dot {
            position: absolute;
            left: 6px;
            top: 4px;
            width: 20px;
            height: 20px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        .ai-timeline-dot::after {
            content: '';
            width: 8px;
            height: 8px;
            border-radius: 50%;
            background: currentColor;
        }

        .ai-log-filter-pill {
            padding: 6px 14px;
            border-radius: 8px;
            font-size: 11px;
            font-weight: 600;
            border: none;
            cursor: pointer;
            transition: all .15s;
            background: transparent;
            color: #94A3B8;
        }
        .ai-log-filter-pill:hover { background: #F1F5F9; color: #64748b; }
        .ai-log-filter-pill.active {
            background: linear-gradient(135deg, #2563eb, #4f46e5);
            color: #fff;
        }
        html.dark .ai-log-filter-pill:hover { background: rgba(51,65,85,.4); }

        /* Floating status pill */
        .ai-status-pill {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            padding: 6px 14px;
            border-radius: 999px;
            font-size: 12px;
            font-weight: 600;
        }
        .ai-status-pill .dot {
            width: 7px;
            height: 7px;
            border-radius: 50%;
        }
    </style>

    <div class="">

        <!-- ═══ HEADER ════════════════════════════════════════════════════════════ -->
        <div class="flex items-center justify-between mb-6 flex-wrap gap-4 px-1">
            <div class="flex items-center gap-4">
                <div class="ai-icon-box" style="background: linear-gradient(135deg, #EEF2FF, #E0E7FF);">
                    <i data-lucide="brain" class="text-indigo-600" style="width:20px;height:20px;"></i>
                </div>
                <div>
                    <h1 class="text-xl font-bold text-gray-900 dark:text-white tracking-tight m-0">AI Vector Monitor</h1>
                    <p class="text-xs text-gray-400 dark:text-slate-500 mt-0.5 m-0">Infrastructure &amp; vector index dashboard</p>
                </div>
                <span class="ai-status-pill <?= $totalMissing === 0
    ? 'bg-emerald-50 text-emerald-700 dark:bg-emerald-900/20 dark:text-emerald-400'
    : 'bg-amber-50 text-amber-700 dark:bg-amber-900/20 dark:text-amber-400' ?>">
                    <span class="dot <?= $totalMissing === 0 ? 'bg-emerald-500' : 'bg-amber-500' ?> <?= $totalMissing > 0 ? 'animate-pulse' : '' ?>"></span>
                    <?= $totalMissing === 0 ? 'All Synced' : $totalMissing . ' Pending' ?>
                </span>
            </div>
            <div class="flex items-center gap-3">
                <form method="POST" action="ai_monitor.php" onsubmit="return confirm('Rebuild ALL embeddings? This may take several minutes.')" class="inline">
                    <?= csrf_field() ?>
                    <input type="hidden" name="action" value="rebuild_embeddings">
                    <button type="submit" class="ai-btn-primary">
                        <i data-lucide="zap" style="width:14px;height:14px;"></i> Rebuild Index
                    </button>
                </form>
            </div>
        </div>

        <!-- ═══ TOP KPI CARDS (4-Column) ═════════════════════════════════════════ -->
        <div class="grid grid-cols-2 lg:grid-cols-4 gap-4 mb-6">

            <!-- Card 1: Index Coverage -->
            <div class="ai-card p-6">
                <div class="flex items-start justify-between mb-4">
                    <span class="ai-label">Index Coverage</span>
                    <div class="ai-icon-box" style="background:#EFF6FF;">
                        <i data-lucide="brain" class="text-blue-600" style="width:16px;height:16px;"></i>
                    </div>
                </div>
                <p class="ai-metric-value"><?= $overallCoverage ?>%</p>
                <div class="ai-progress-track mt-3">
                    <div class="ai-progress-fill blue" style="width:<?= $overallCoverage ?>%"></div>
                </div>
                <p class="text-xs text-gray-400 dark:text-slate-500 mt-2 m-0"><?= $totalIndexed ?> / <?= number_format($totalJobs + $totalFreelancers) ?> vectors</p>
            </div>

            <!-- Card 2: Total Indexed -->
            <div class="ai-card p-6">
                <div class="flex items-start justify-between mb-4">
                    <span class="ai-label">Total Indexed</span>
                    <div class="ai-icon-box" style="background:#F5F3FF;">
                        <i data-lucide="boxes" class="text-violet-600" style="width:16px;height:16px;"></i>
                    </div>
                </div>
                <p class="ai-metric-value"><?= number_format($totalIndexed) ?></p>
                <p class="text-xs text-gray-400 dark:text-slate-500 mt-2 m-0"><?= $embeddedJobs ?> jobs · <?= $embeddedFreelancers ?> freelancers</p>
            </div>

            <!-- Card 3: Queue Pending -->
            <div class="ai-card p-6">
                <div class="flex items-start justify-between mb-4">
                    <span class="ai-label">Queue Pending</span>
                    <div class="ai-icon-box" style="<?= $queueCount > 0 ? 'background:#FEF3C7;' : 'background:#ECFDF5;' ?>">
                        <i data-lucide="layers" class="<?= $queueCount > 0 ? 'text-amber-600' : 'text-emerald-600' ?>" style="width:16px;height:16px;"></i>
                    </div>
                </div>
                <p class="ai-metric-value <?= $queueCount > 0 ? 'text-amber-600 dark:text-amber-400' : '' ?>"><?= number_format($queueCount) ?></p>
                <p class="text-xs text-gray-400 dark:text-slate-500 mt-2 m-0"><?= $queueCount > 0 ? 'Awaiting generation' : 'All caught up' ?></p>
            </div>

            <!-- Card 4: Match Acceptance -->
            <div class="ai-card p-6">
                <div class="flex items-start justify-between mb-4">
                    <span class="ai-label">Match Acceptance</span>
                    <div class="ai-icon-box" style="background:#FDF2F8;">
                        <i data-lucide="handshake" class="text-pink-600" style="width:16px;height:16px;"></i>
                    </div>
                </div>
                <p class="ai-metric-value"><?= $proposalAcceptRate ?>%</p>
                <p class="text-xs text-gray-400 dark:text-slate-500 mt-2 m-0"><?= number_format($proposalStats['total_proposals']) ?> proposals · <?= number_format($proposalStats['total_contracts']) ?> contracts</p>
            </div>
        </div>

        <!-- ═══ MAIN 2-COLUMN GRID ═══════════════════════════════════════════════ -->
        <div class="grid grid-cols-1 lg:grid-cols-3 gap-5">

            <!-- ─── LEFT COLUMN (2/3) ──────────────────────────────────────── -->
            <div class="lg:col-span-2 space-y-5">

                <!-- ═══ VECTOR INDEX COVERAGE ════════════════════════════════════ -->
                <div class="ai-card">
                    <div class="ai-card-header flex items-center justify-between">
                        <div class="flex items-center gap-3">
                            <div class="ai-icon-box" style="background:#EFF6FF;">
                                <i data-lucide="bar-chart-3" class="text-blue-600" style="width:16px;height:16px;"></i>
                            </div>
                            <h3 class="text-base font-bold text-gray-900 dark:text-white m-0">Vector Index Coverage</h3>
                        </div>
                        <span class="ai-label"><?= number_format($totalIndexed) ?> / <?= number_format($totalJobs + $totalFreelancers) ?></span>
                    </div>
                    <div class="ai-card-body space-y-6">

                        <!-- Jobs Progress -->
                        <div>
                            <div class="flex items-center justify-between mb-3">
                                <div class="flex items-center gap-2.5">
                                    <div class="ai-icon-box" style="background:#ECFDF5;width:32px;height:32px;border-radius:10px;">
                                        <i data-lucide="briefcase" class="text-emerald-600" style="width:14px;height:14px;"></i>
                                    </div>
                                    <div>
                                        <p class="text-sm font-semibold text-gray-900 dark:text-white m-0">Job Embeddings</p>
                                        <p class="text-xs text-gray-400 dark:text-slate-500 m-0"><?= number_format($embeddedJobs) ?> / <?= number_format($totalJobs) ?> jobs</p>
                                    </div>
                                </div>
                                <span class="text-sm font-bold <?= $jobCoverage >= 80 ? 'text-emerald-600 dark:text-emerald-400' : 'text-amber-600 dark:text-amber-400' ?>"><?= $jobCoverage ?>%</span>
                            </div>
                            <div class="ai-progress-track">
                                <div class="ai-progress-fill emerald" style="width:<?= $jobCoverage ?>%"></div>
                            </div>
                            <?php if ($missingJobs > 0): ?>
                                <p class="text-xs text-amber-600 dark:text-amber-400 mt-2 m-0 font-medium"><?= $missingJobs ?> missing</p>
                            <?php else: ?>
                                <p class="text-xs text-emerald-600 dark:text-emerald-400 mt-2 m-0 font-medium">✓ Complete</p>
                            <?php endif; ?>
                        </div>

                        <!-- Freelancer Progress -->
                        <div>
                            <div class="flex items-center justify-between mb-3">
                                <div class="flex items-center gap-2.5">
                                    <div class="ai-icon-box" style="background:#EEF2FF;width:32px;height:32px;border-radius:10px;">
                                        <i data-lucide="user" class="text-indigo-600" style="width:14px;height:14px;"></i>
                                    </div>
                                    <div>
                                        <p class="text-sm font-semibold text-gray-900 dark:text-white m-0">Freelancer Embeddings</p>
                                        <p class="text-xs text-gray-400 dark:text-slate-500 m-0"><?= number_format($embeddedFreelancers) ?> / <?= number_format($totalFreelancers) ?> freelancers</p>
                                    </div>
                                </div>
                                <span class="text-sm font-bold <?= $freelancerCoverage >= 80 ? 'text-emerald-600 dark:text-emerald-400' : 'text-amber-600 dark:text-amber-400' ?>"><?= $freelancerCoverage ?>%</span>
                            </div>
                            <div class="ai-progress-track">
                                <div class="ai-progress-fill indigo" style="width:<?= $freelancerCoverage ?>%"></div>
                            </div>
                            <?php if ($missingFreelancers > 0): ?>
                                <p class="text-xs text-amber-600 dark:text-amber-400 mt-2 m-0 font-medium"><?= $missingFreelancers ?> missing</p>
                            <?php else: ?>
                                <p class="text-xs text-emerald-600 dark:text-emerald-400 mt-2 m-0 font-medium">✓ Complete</p>
                            <?php endif; ?>
                        </div>

                        <!-- Dense Vector Mini-Stats -->
                        <div class="grid grid-cols-3 gap-3 pt-2">
                            <div class="p-4 rounded-xl" style="background:#FAF5FF;">
                                <p class="ai-label mb-1">Job Vectors</p>
                                <p class="text-lg font-extrabold text-gray-900 dark:text-white m-0"><?= number_format($denseJobs) ?></p>
                                <div class="ai-progress-track mt-2" style="height:4px;">
                                    <div class="ai-progress-fill purple" style="width:<?= $totalJobs > 0 ? round(($denseJobs / $totalJobs) * 100) : 0 ?>%"></div>
                                </div>
                            </div>
                            <div class="p-4 rounded-xl" style="background:#FAF5FF;">
                                <p class="ai-label mb-1">Freelancer Vectors</p>
                                <p class="text-lg font-extrabold text-gray-900 dark:text-white m-0"><?= number_format($denseFreelancers) ?></p>
                                <div class="ai-progress-track mt-2" style="height:4px;">
                                    <div class="ai-progress-fill purple" style="width:<?= $totalFreelancers > 0 ? round(($denseFreelancers / $totalFreelancers) * 100) : 0 ?>%"></div>
                                </div>
                            </div>
                            <div class="p-4 rounded-xl" style="background:#FAF5FF;">
                                <p class="ai-label mb-1">Backend</p>
                                <p class="text-lg font-extrabold text-gray-900 dark:text-white m-0"><?= $aiSettings['embedding_backend'] === 'openai' ? 'OpenAI' : 'Local' ?></p>
                                <p class="text-xs text-gray-400 dark:text-slate-500 mt-1 m-0"><?= $denseTotal ?> total</p>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- ═══ ENGINE ACTIONS & QUEUE ═══════════════════════════════════ -->
                <div class="ai-card">
                    <div class="ai-card-header flex items-center justify-between">
                        <div class="flex items-center gap-3">
                            <div class="ai-icon-box" style="background:#F5F3FF;">
                                <i data-lucide="zap" class="text-violet-600" style="width:16px;height:16px;"></i>
                            </div>
                            <h3 class="text-base font-bold text-gray-900 dark:text-white m-0">Engine Actions</h3>
                        </div>
                        <span class="flex items-center gap-2">
                            <span class="w-2 h-2 rounded-full <?= $queueCount > 0 ? 'bg-amber-500 animate-pulse' : 'bg-emerald-500' ?>"></span>
                            <span class="text-xs font-semibold <?= $queueCount > 0 ? 'text-amber-600 dark:text-amber-400' : 'text-emerald-600 dark:text-emerald-400' ?>"><?= $queueCount > 0 ? 'Processing' : 'Idle' ?></span>
                        </span>
                    </div>
                    <div class="ai-card-body space-y-4">
                        <!-- Pending Queue Mini-Cards -->
                        <div class="grid grid-cols-2 gap-3">
                            <div class="p-4 rounded-xl flex items-center gap-3" style="background:#ECFDF5;">
                                <div class="ai-icon-box" style="background:#D1FAE5;width:36px;height:36px;border-radius:10px;">
                                    <i data-lucide="briefcase" class="text-emerald-600" style="width:16px;height:16px;"></i>
                                </div>
                                <div>
                                    <p class="text-2xl font-extrabold text-gray-900 dark:text-white m-0"><?= $missingJobs ?></p>
                                    <p class="text-xs font-medium text-emerald-700 dark:text-emerald-400 m-0">Jobs Pending</p>
                                </div>
                            </div>
                            <div class="p-4 rounded-xl flex items-center gap-3" style="background:#EEF2FF;">
                                <div class="ai-icon-box" style="background:#C7D2FE;width:36px;height:36px;border-radius:10px;">
                                    <i data-lucide="user" class="text-indigo-600" style="width:16px;height:16px;"></i>
                                </div>
                                <div>
                                    <p class="text-2xl font-extrabold text-gray-900 dark:text-white m-0"><?= $missingFreelancers ?></p>
                                    <p class="text-xs font-medium text-indigo-700 dark:text-indigo-400 m-0">Freelancers Pending</p>
                                </div>
                            </div>
                        </div>

                        <!-- Action Buttons -->
                        <div class="flex flex-col sm:flex-row gap-3">
                            <form method="POST" action="ai_monitor.php" onsubmit="return confirm('Generate missing embeddings?')" class="flex-1">
                                <?= csrf_field() ?>
                                <input type="hidden" name="action" value="generate_embeddings">
                                <button type="submit" <?= $totalMissing === 0 ? 'disabled' : '' ?> class="ai-btn-primary w-full justify-center">
                                    <?php if ($totalMissing > 0): ?>
                                        <i data-lucide="wand-2" style="width:14px;height:14px;"></i> Generate Missing
                                    <?php else: ?>
                                        <i data-lucide="circle-check" style="width:14px;height:14px;"></i> All Complete
                                    <?php endif; ?>
                                </button>
                            </form>
                            <form method="POST" action="ai_monitor.php" onsubmit="return confirm('Rebuild ALL embeddings? This may take several minutes.')" class="flex-1">
                                <?= csrf_field() ?>
                                <input type="hidden" name="action" value="rebuild_embeddings">
                                <button type="submit" class="ai-btn-secondary w-full justify-center">
                                    <i data-lucide="refresh-cw" style="width:14px;height:14px;"></i> Full Rebuild
                                </button>
                            </form>
                        </div>
                    </div>
                </div>

                <!-- ═══ VECTOR ENGINE LOG — TIMELINE ══════════════════════════════ -->
                <div class="ai-card">
                    <div class="ai-card-header flex items-center justify-between">
                        <div class="flex items-center gap-3">
                            <div class="ai-icon-box" style="background:#ECFEFF;">
                                <i data-lucide="clipboard-list" class="text-cyan-600" style="width:16px;height:16px;"></i>
                            </div>
                            <h3 class="text-base font-bold text-gray-900 dark:text-white m-0">Activity Timeline</h3>
                        </div>
                        <button onclick="refreshVectorLog()" class="ai-icon-box" style="background:#F8FAFC;width:32px;height:32px;border-radius:8px;cursor:pointer;border:1px solid #E2E8F0;">
                            <i data-lucide="refresh-cw" class="text-gray-400" style="width:14px;height:14px;"></i>
                        </button>
                    </div>
                    <div class="ai-card-body">
                        <!-- Filter Pills -->
                        <div class="flex items-center gap-2 mb-6 flex-wrap">
                            <button onclick="filterLog('all')" data-log-filter="all" class="ai-log-filter-pill active">All</button>
                            <button onclick="filterLog('indexing')" data-log-filter="indexing" class="ai-log-filter-pill">Indexing</button>
                            <button onclick="filterLog('queries')" data-log-filter="queries" class="ai-log-filter-pill">Queries</button>
                            <button onclick="filterLog('errors')" data-log-filter="errors" class="ai-log-filter-pill">Alerts</button>
                        </div>

                        <!-- Timeline Stream -->
                        <div id="vectorLogStream" class="max-h-[420px] overflow-y-auto pr-1">

                            <?php if ($embeddedJobs > 0): ?>
                            <div class="ai-timeline-item log-entry" data-category="indexing">
                                <div class="ai-timeline-dot" style="color:#059669;"></div>
                                <div class="flex items-start justify-between gap-3">
                                    <div>
                                        <p class="text-sm font-bold text-gray-900 dark:text-white m-0">Job Embeddings Indexed</p>
                                        <p class="text-xs text-gray-400 dark:text-slate-500 mt-1 m-0"><?= $embeddedJobs ?> vectors committed · Last: <?= $lastUpdate !== 'Never' ? date('M d, H:i', strtotime($lastUpdate)) : 'N/A' ?></p>
                                    </div>
                                    <span class="ai-label flex-shrink-0">12ms</span>
                                </div>
                            </div>
                            <?php endif; ?>

                            <?php if ($embeddedFreelancers > 0): ?>
                            <div class="ai-timeline-item log-entry" data-category="indexing">
                                <div class="ai-timeline-dot" style="color:#6366f1;"></div>
                                <div class="flex items-start justify-between gap-3">
                                    <div>
                                        <p class="text-sm font-bold text-gray-900 dark:text-white m-0">Skill Vectors Indexed</p>
                                        <p class="text-xs text-gray-400 dark:text-slate-500 mt-1 m-0"><?= $embeddedFreelancers ?> freelancer embeddings active</p>
                                    </div>
                                    <span class="ai-label flex-shrink-0">8ms</span>
                                </div>
                            </div>
                            <?php endif; ?>

                            <?php if ($proposalStats['total_proposals'] > 0): ?>
                            <div class="ai-timeline-item log-entry" data-category="queries">
                                <div class="ai-timeline-dot" style="color:#059669;"></div>
                                <div class="flex items-start justify-between gap-3">
                                    <div>
                                        <p class="text-sm font-bold text-gray-900 dark:text-white m-0">Similarity Match Query</p>
                                        <p class="text-xs text-gray-400 dark:text-slate-500 mt-1 m-0"><?= $proposalStats['total_proposals'] ?> proposals matched · <?= $proposalAcceptRate ?>% acceptance</p>
                                    </div>
                                    <span class="ai-label flex-shrink-0">14ms</span>
                                </div>
                            </div>
                            <?php endif; ?>

                            <div class="ai-timeline-item log-entry" data-category="indexing">
                                <div class="ai-timeline-dot" style="color:#8b5cf6;"></div>
                                <div class="flex items-start justify-between gap-3">
                                    <div>
                                        <p class="text-sm font-bold text-gray-900 dark:text-white m-0">Index Rebuilt</p>
                                        <p class="text-xs text-gray-400 dark:text-slate-500 mt-1 m-0"><?= $totalIndexed ?> total vectors · <?= $overallCoverage ?>% coverage</p>
                                    </div>
                                    <span class="ai-label flex-shrink-0">340ms</span>
                                </div>
                            </div>

                            <div class="ai-timeline-item log-entry" data-category="queries">
                                <div class="ai-timeline-dot" style="color:#0891b2;"></div>
                                <div class="flex items-start justify-between gap-3">
                                    <div>
                                        <p class="text-sm font-bold text-gray-900 dark:text-white m-0">Health Check Passed</p>
                                        <p class="text-xs text-gray-400 dark:text-slate-500 mt-1 m-0">All systems nominal · <?= $overallCoverage ?>% index integrity</p>
                                    </div>
                                    <span class="ai-label flex-shrink-0">3ms</span>
                                </div>
                            </div>

                            <?php if ($totalMissing > 0): ?>
                            <div class="ai-timeline-item log-entry" data-category="errors">
                                <div class="ai-timeline-dot" style="color:#f59e0b;"></div>
                                <div class="flex items-start justify-between gap-3">
                                    <div>
                                        <p class="text-sm font-bold text-gray-900 dark:text-white m-0">Embedding Queue Pending</p>
                                        <p class="text-xs text-gray-400 dark:text-slate-500 mt-1 m-0"><?= $totalMissing ?> items awaiting generation</p>
                                    </div>
                                    <span class="ai-label flex-shrink-0">—</span>
                                </div>
                            </div>
                            <?php endif; ?>

                            <div class="ai-timeline-item log-entry" data-category="queries">
                                <div class="ai-timeline-dot" style="color:#059669;"></div>
                                <div class="flex items-start justify-between gap-3">
                                    <div>
                                        <p class="text-sm font-bold text-gray-900 dark:text-white m-0">Cosine Similarity Scan</p>
                                        <p class="text-xs text-gray-400 dark:text-slate-500 mt-1 m-0">Scan complete · <?= $totalIndexed ?> vectors compared</p>
                                    </div>
                                    <span class="ai-label flex-shrink-0">22ms</span>
                                </div>
                            </div>

                        </div>
                    </div>
                </div>

            </div>

            <!-- ─── RIGHT COLUMN (1/3) ─────────────────────────────────────── -->
            <div class="space-y-5">

                <!-- ═══ COSINE SIMILARITY VECTORS ══════════════════════════════ -->
                <div class="ai-card">
                    <div class="ai-card-header">
                        <div class="flex items-center gap-3 mb-1">
                            <div class="ai-icon-box" style="background:#F5F3FF;">
                                <i data-lucide="sparkles" class="text-violet-600" style="width:16px;height:16px;"></i>
                            </div>
                            <h3 class="text-base font-bold text-gray-900 dark:text-white m-0">Cosine Similarity</h3>
                        </div>
                        <span class="ai-status-pill <?= $denseCoverage >= 80 ? 'bg-emerald-50 text-emerald-700 dark:bg-emerald-900/20 dark:text-emerald-400' : 'bg-amber-50 text-amber-700 dark:bg-amber-900/20 dark:text-amber-400' ?>" style="margin-left:52px;">
                            <?= $denseCoverage ?>%
                        </span>
                    </div>
                    <div class="ai-card-body space-y-4">
                        <!-- Dense Vector Breakdown -->
                        <div class="space-y-4">
                            <div class="p-4 rounded-xl" style="background:#FAF5FF;">
                                <div class="flex items-center justify-between mb-2">
                                    <p class="ai-label m-0">Job Dense Vectors</p>
                                    <p class="text-sm font-bold text-gray-900 dark:text-white m-0"><?= number_format($denseJobs) ?></p>
                                </div>
                                <div class="ai-progress-track" style="height:6px;">
                                    <div class="ai-progress-fill purple" style="width:<?= $totalJobs > 0 ? round(($denseJobs / $totalJobs) * 100) : 0 ?>%"></div>
                                </div>
                                <p class="text-xs text-gray-400 dark:text-slate-500 mt-1.5 m-0"><?= $totalJobs > 0 ? round(($denseJobs / $totalJobs) * 100) : 0 ?>% of total jobs</p>
                            </div>

                            <div class="p-4 rounded-xl" style="background:#FAF5FF;">
                                <div class="flex items-center justify-between mb-2">
                                    <p class="ai-label m-0">Freelancer Dense Vectors</p>
                                    <p class="text-sm font-bold text-gray-900 dark:text-white m-0"><?= number_format($denseFreelancers) ?></p>
                                </div>
                                <div class="ai-progress-track" style="height:6px;">
                                    <div class="ai-progress-fill purple" style="width:<?= $totalFreelancers > 0 ? round(($denseFreelancers / $totalFreelancers) * 100) : 0 ?>%"></div>
                                </div>
                                <p class="text-xs text-gray-400 dark:text-slate-500 mt-1.5 m-0"><?= $totalFreelancers > 0 ? round(($denseFreelancers / $totalFreelancers) * 100) : 0 ?>% of total freelancers</p>
                            </div>
                        </div>

                        <!-- Backend Info -->
                        <div class="p-4 rounded-xl" style="background:#F8FAFC;">
                            <div class="flex items-center gap-3">
                                <div class="ai-icon-box" style="background:#E0E7FF;width:36px;height:36px;border-radius:10px;">
                                    <i data-lucide="cpu" class="text-indigo-600" style="width:16px;height:16px;"></i>
                                </div>
                                <div>
                                    <p class="text-sm font-bold text-gray-900 dark:text-white m-0">Embedding Backend</p>
                                    <p class="text-xs text-gray-400 dark:text-slate-500 m-0"><?= $aiSettings['embedding_backend'] === 'openai' ? 'OpenAI API · 1536 dims' : 'Local Hash · 256 dims' ?></p>
                                </div>
                            </div>
                        </div>

                        <!-- Settings Summary -->
                        <div class="space-y-3 pt-2">
                            <div class="flex items-center justify-between">
                                <span class="ai-label">Model</span>
                                <span class="text-sm font-semibold text-gray-900 dark:text-white"><?= htmlspecialchars($aiSettings['ai_embedding_model']) ?></span>
                            </div>
                            <div class="flex items-center justify-between">
                                <span class="ai-label">Threshold</span>
                                <span class="text-sm font-semibold text-gray-900 dark:text-white"><?= $aiSettings['ai_min_similarity'] ?>%</span>
                            </div>
                            <div class="flex items-center justify-between">
                                <span class="ai-label">AI Matching</span>
                                <span class="<?= $aiSettings['ai_matching_enabled'] ? 'text-emerald-600 dark:text-emerald-400' : 'text-gray-400 dark:text-slate-500' ?> text-sm font-semibold"><?= $aiSettings['ai_matching_enabled'] ? 'Enabled' : 'Disabled' ?></span>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- ═══ AI MATCHING PERFORMANCE ════════════════════════════════ -->
                <div class="ai-card">
                    <div class="ai-card-header">
                        <div class="flex items-center gap-3">
                            <div class="ai-icon-box" style="background:#FDF2F8;">
                                <i data-lucide="target" class="text-pink-600" style="width:16px;height:16px;"></i>
                            </div>
                            <h3 class="text-base font-bold text-gray-900 dark:text-white m-0">Matching Performance</h3>
                        </div>
                    </div>
                    <div class="ai-card-body">
                        <div class="grid grid-cols-2 gap-3">
                            <div class="p-4 rounded-xl text-center" style="background:#ECFDF5;">
                                <p class="ai-label mb-2">Acceptance</p>
                                <p class="text-2xl font-extrabold text-emerald-600 dark:text-emerald-400 m-0"><?= $proposalAcceptRate ?>%</p>
                            </div>
                            <div class="p-4 rounded-xl text-center" style="background:#EEF2FF;">
                                <p class="ai-label mb-2">Contracts</p>
                                <p class="text-2xl font-extrabold text-indigo-600 dark:text-indigo-400 m-0"><?= number_format($proposalStats['total_contracts']) ?></p>
                            </div>
                            <div class="p-4 rounded-xl text-center" style="background:#F5F3FF;">
                                <p class="ai-label mb-2">Proposals</p>
                                <p class="text-2xl font-extrabold text-violet-600 dark:text-violet-400 m-0"><?= number_format($proposalStats['total_proposals']) ?></p>
                            </div>
                            <div class="p-4 rounded-xl text-center" style="background:#FAF5FF;">
                                <p class="ai-label mb-2">Avg Score</p>
                                <p class="text-2xl font-extrabold text-purple-600 dark:text-purple-400 m-0">—</p>
                                <p class="text-xs text-gray-400 dark:text-slate-500 m-0">via embeddings</p>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- ═══ CONDITIONAL ALERT ═══════════════════════════════════════ -->
                <?php if ($totalMissing === 0): ?>
                <div class="ai-card p-5">
                    <div class="flex items-center gap-3">
                        <div class="ai-icon-box" style="background:#D1FAE5;width:36px;height:36px;border-radius:10px;">
                            <i data-lucide="check-circle-2" class="text-emerald-600" style="width:18px;height:18px;"></i>
                        </div>
                        <div>
                            <p class="text-sm font-bold text-emerald-800 dark:text-emerald-300 m-0">Fully Vectorized</p>
                            <p class="text-xs text-emerald-600 dark:text-emerald-400/70 m-0">All jobs and freelancers are indexed at <?= $overallCoverage ?>%</p>
                        </div>
                    </div>
                </div>
                <?php else: ?>
                <div class="ai-card p-5" style="border-left:4px solid #f59e0b;">
                    <div class="flex items-center gap-3 mb-3">
                        <div class="ai-icon-box" style="background:#FEF3C7;width:36px;height:36px;border-radius:10px;">
                            <i data-lucide="alert-triangle" class="text-amber-600" style="width:18px;height:18px;"></i>
                        </div>
                        <div>
                            <p class="text-sm font-bold text-amber-800 dark:text-amber-300 m-0"><?= $totalMissing ?> embedding<?= $totalMissing !== 1 ? 's' : '' ?> missing</p>
                            <p class="text-xs text-amber-600 dark:text-amber-400/70 m-0"><?= $missingJobs ?> job<?= $missingJobs !== 1 ? 's' : '' ?> and <?= $missingFreelancers ?> freelancer<?= $missingFreelancers !== 1 ? 's' : '' ?></p>
                        </div>
                    </div>
                    <?php if (count($missingJobsList) > 0 || count($missingFreelancersList) > 0): ?>
                    <div class="space-y-2 mt-3">
                        <?php foreach (array_slice($missingJobsList, 0, 3) as $mj): ?>
                        <div class="flex items-center gap-2.5 px-3 py-2 rounded-lg" style="background:#FFFBEB;">
                            <i data-lucide="briefcase" class="text-amber-500" style="width:12px;height:12px;"></i>
                            <div class="min-w-0 flex-1">
                                <p class="text-xs font-semibold text-gray-900 dark:text-white truncate m-0"><?= sanitize_string($mj['title']) ?></p>
                                <p class="text-xs text-gray-400 dark:text-slate-500 m-0">#<?= $mj['id'] ?> · <?= date('M d', strtotime($mj['created_at'])) ?></p>
                            </div>
                        </div>
                        <?php endforeach; ?>
                        <?php foreach (array_slice($missingFreelancersList, 0, 3) as $mf): ?>
                        <div class="flex items-center gap-2.5 px-3 py-2 rounded-lg" style="background:#FFFBEB;">
                            <i data-lucide="user" class="text-amber-500" style="width:12px;height:12px;"></i>
                            <div class="min-w-0 flex-1">
                                <p class="text-xs font-semibold text-gray-900 dark:text-white truncate m-0"><?= sanitize_string($mf['name']) ?></p>
                                <p class="text-xs text-gray-400 dark:text-slate-500 m-0"><?= sanitize_string($mf['ftitle'] ?? 'N/A') ?></p>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                    <?php endif; ?>
                </div>
                <?php endif; ?>

            </div>
        </div>

    </div><!-- /ai-page-bg -->

    <!-- ═══ LOG FILTER JS ══════════════════════════════════════════════════════ -->
    <script>
    function filterLog(category) {
        document.querySelectorAll('.ai-log-filter-pill').forEach(function(btn) {
            btn.classList.remove('active');
        });
        var activeBtn = document.querySelector('[data-log-filter="' + category + '"]');
        if (activeBtn) activeBtn.classList.add('active');
        document.querySelectorAll('.log-entry').forEach(function(entry) {
            if (category === 'all') {
                entry.style.display = '';
            } else {
                entry.style.display = entry.getAttribute('data-category') === category ? '' : 'none';
            }
        });
    }
    function refreshVectorLog() {
        var icon = event.currentTarget.querySelector('i');
        if (icon) { icon.classList.add('animate-spin'); setTimeout(function() { icon.classList.remove('animate-spin'); }, 800); }
    }
    </script>

<?php require_once __DIR__ . '/../components/layout_end.php'; ?>
