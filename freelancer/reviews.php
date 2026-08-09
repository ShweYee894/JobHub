<?php

/**
 * Freelancer Reviews Page
 * Show completed contracts available for review + reviews received.
 */
$page_title = 'My Reviews';
require_once __DIR__ . '/../auth/auth.php';
require_role('freelancer');
require_once __DIR__ . '/../config/db.php';

$userId = (int) $_SESSION['user_id'];

// ── Fetch user info ───────────────────────────────────────────────────
$stmt = $conn->prepare('SELECT name, profile_image FROM users WHERE id = ?');
$stmt->bind_param('i', $userId);
$stmt->execute();
$user = $stmt->get_result()->fetch_assoc();
$stmt->close();

// ── Average rating summary ────────────────────────────────────────────
$stmtAvg = $conn->prepare('
    SELECT COALESCE(AVG(rating), 0) AS avg_rating, COUNT(*) AS total
    FROM reviews WHERE reviewee_id = ? AND COALESCE(is_hidden, 0) = 0
');
$stmtAvg->bind_param('i', $userId);
$stmtAvg->execute();
$ratingData = $stmtAvg->get_result()->fetch_assoc();
$stmtAvg->close();
$avgRating = round((float) $ratingData['avg_rating'], 1);
$totalReviews = (int) $ratingData['total'];

// ── Rating distribution ───────────────────────────────────────────────
$dist = [5 => 0, 4 => 0, 3 => 0, 2 => 0, 1 => 0];
if ($totalReviews > 0) {
    $stmtDist = $conn->prepare('SELECT rating, COUNT(*) AS cnt FROM reviews WHERE reviewee_id = ? AND COALESCE(is_hidden, 0) = 0 GROUP BY rating');
    $stmtDist->bind_param('i', $userId);
    $stmtDist->execute();
    $distResult = $stmtDist->get_result();
    while ($dRow = $distResult->fetch_assoc()) {
        $dist[(int) $dRow['rating']] = (int) $dRow['cnt'];
    }
    $stmtDist->close();
}

// ── Completed contracts not yet reviewed by freelancer ────────────────
$stmtPending = $conn->prepare('
    SELECT c.id, c.total_budget, c.contract_type, c.created_at,
           j.title AS job_title,
           u.name AS client_name, u.id AS client_user_id
    FROM contracts c
    JOIN jobs j ON c.job_id = j.id
    JOIN users u ON c.client_id = u.id
    WHERE c.freelancer_id = ? AND c.status = "completed"
      AND c.id NOT IN (SELECT contract_id FROM reviews WHERE reviewer_id = ?)
    ORDER BY c.created_at DESC
');
$stmtPending->bind_param('ii', $userId, $userId);
$stmtPending->execute();
$pendingContracts = $stmtPending->get_result();
$stmtPending->close();

// ── Reviews received by this freelancer ───────────────────────────────
$stmtReceived = $conn->prepare('
    SELECT r.id, r.rating, r.comment, r.created_at,
           j.title AS job_title,
           u.name AS reviewer_name, u.profile_image AS reviewer_image
    FROM reviews r
    JOIN contracts c ON r.contract_id = c.id
    JOIN jobs j ON c.job_id = j.id
    JOIN users u ON r.reviewer_id = u.id
    WHERE r.reviewee_id = ? AND COALESCE(r.is_hidden, 0) = 0
    ORDER BY r.created_at DESC
');
$stmtReceived->bind_param('i', $userId);
$stmtReceived->execute();
$receivedReviews = $stmtReceived->get_result();
$stmtReceived->close();

// ── Reviews given by this freelancer ──────────────────────────────────
$stmtGiven = $conn->prepare('
    SELECT r.id, r.rating, r.comment, r.created_at,
           j.title AS job_title,
           u.name AS reviewee_name, u.profile_image AS reviewee_image
    FROM reviews r
    JOIN contracts c ON r.contract_id = c.id
    JOIN jobs j ON c.job_id = j.id
    JOIN users u ON r.reviewee_id = u.id
    WHERE r.reviewer_id = ?
    ORDER BY r.created_at DESC
');
$stmtGiven->bind_param('i', $userId);
$stmtGiven->execute();
$givenReviews = $stmtGiven->get_result();
$stmtGiven->close();

$pageTitle = 'Reviews';
$pageSubtitle = 'Your reputation and feedback';
$activePage = 'reviews';
$user = ['name' => $user['name'] ?? 'Freelancer', 'profile_image' => $user['profile_image'] ?? null];
$unreadCount = get_unread_message_count($userId, 'freelancer');
require_once __DIR__ . '/../components/freelancer_header.php';
?>

<?php display_flash('success') ?>
<?php display_flash('error') ?>

<style>
    .reviews-page { font-family: 'Inter', sans-serif; }

    /* ── Sticky Left Panel ────────────────────────────── */
    .stats-panel {
        position: sticky;
        top: 80px;
        background: #fafafa;
        border: 1px solid #f0f0f0;
        border-radius: 16px;
        padding: 0;
        color: #1a1a1a;
        overflow: hidden;
        box-shadow: 0 1px 3px rgba(0,0,0,.04);
        transition: background .2s, border-color .2s, color .2s;
    }
    .dark .stats-panel {
        background: #1e293b;
        border-color: #334155;
        color: #e2e8f0;
        box-shadow: 0 1px 3px rgba(0,0,0,.2);
    }

    /* ── Top Section: Number + Stars ──────────────────── */
    .stats-hero {
        text-align: center;
        padding: 32px 28px 20px;
        background: linear-gradient(180deg, #ffffff 0%, #fafafa 100%);
        border-bottom: 1px solid #f0f0f0;
        transition: background .2s, border-color .2s;
    }
    .dark .stats-hero {
        background: linear-gradient(180deg, #1e293b 0%, #1a2332 100%);
        border-bottom-color: #334155;
    }
    .stats-big-number {
        font-size: 56px;
        font-weight: 800;
        letter-spacing: -2px;
        line-height: 1;
        color: #111827;
        font-family: 'Inter', system-ui, sans-serif;
        transition: color .2s;
    }
    .dark .stats-big-number { color: #f1f5f9; }
    .stats-stars-row {
        display: flex;
        align-items: center;
        justify-content: center;
        gap: 3px;
        margin-top: 10px;
        margin-bottom: 6px;
    }
    .stats-star-glow {
        font-size: 20px;
        color: #f59e0b;
        filter: drop-shadow(0 1px 2px rgba(245,158,11,.25));
    }
    .stats-star-dim {
        font-size: 20px;
        color: #e5e7eb;
    }
    .dark .stats-star-dim { color: #475569; }
    .stats-review-count {
        font-size: 13px;
        font-weight: 500;
        color: #9ca3af;
        margin: 0;
        font-family: 'Inter', system-ui, sans-serif;
    }

    /* ── Micro-Table Distribution ─────────────────────── */
    .dist-section { padding: 16px 20px; }
    .dist-table { width: 100%; border-collapse: collapse; }
    .dist-table tr { border-bottom: 1px solid #f3f4f6; transition: border-color .2s; }
    .dark .dist-table tr { border-bottom-color: #334155; }
    .dist-table tr:last-child { border-bottom: none; }
    .dist-table td {
        padding: 7px 0;
        font-size: 12px;
        font-family: 'Inter', system-ui, sans-serif;
        vertical-align: middle;
    }
    .dist-table .dist-star-cell {
        width: 14px;
        font-weight: 600;
        color: #6b7280;
        text-align: center;
        padding-right: 10px;
        transition: color .2s;
    }
    .dark .dist-table .dist-star-cell { color: #94a3b8; }
    .dist-table .dist-star-cell i { font-size: 10px; color: #d1d5db; margin-left: 2px; }
    .dark .dist-table .dist-star-cell i { color: #475569; }
    .dist-table .dist-bar-cell { width: 100px; padding: 0 12px; }
    .dist-bar-track {
        height: 5px;
        background: #f3f4f6;
        border-radius: 99px;
        overflow: hidden;
        transition: background .2s;
    }
    .dark .dist-bar-track { background: #334155; }
    .dist-bar-fill { height: 100%; border-radius: 99px; background: #f59e0b; }
    .dist-table .dist-count-cell {
        width: 20px;
        text-align: right;
        font-weight: 500;
        color: #9ca3af;
        font-variant-numeric: tabular-nums;
    }

    /* ── Verified Trust Mark ──────────────────────────── */
    .trust-mark-section {
        padding: 14px 20px;
        border-top: 1px solid #f0f0f0;
        background: #ffffff;
        transition: background .2s, border-color .2s;
    }
    .dark .trust-mark-section {
        background: #162032;
        border-top-color: #334155;
    }
    .trust-mark {
        display: inline-flex;
        align-items: center;
        gap: 6px;
        font-size: 12px;
        font-weight: 500;
        color: #6b7280;
        font-family: 'Inter', system-ui, sans-serif;
    }
    .dark .trust-mark { color: #94a3b8; }
    .trust-mark-icon {
        width: 16px;
        height: 16px;
        border-radius: 50%;
        background: linear-gradient(135deg, #f59e0b, #d97706);
        display: flex;
        align-items: center;
        justify-content: center;
        box-shadow: 0 1px 3px rgba(245,158,11,.25);
    }
    .trust-mark-icon i { font-size: 8px; color: #fff; margin: 0; }

    /* ── Tab Toggle ───────────────────────────────────── */
    .tab-toggle {
        display: inline-flex;
        background: #f1f5f9;
        border-radius: 12px;
        padding: 4px;
        gap: 2px;
        transition: background .2s;
    }
    .dark .tab-toggle { background: #1e293b; }
    .tab-btn {
        padding: 10px 20px;
        border-radius: 10px;
        font-size: 13px;
        font-weight: 600;
        color: #64748b;
        cursor: pointer;
        border: none;
        background: transparent;
        transition: all .25s cubic-bezier(.22,1,.36,1);
        position: relative;
        z-index: 1;
    }
    .tab-btn.active {
        background: #fff;
        color: #111827;
        box-shadow: 0 1px 3px rgba(0,0,0,.06), 0 1px 2px rgba(0,0,0,.04);
    }
    .dark .tab-btn.active {
        background: #334155;
        color: #f1f5f9;
        box-shadow: 0 1px 3px rgba(0,0,0,.2);
    }
    .tab-btn:hover:not(.active) { color: #334155; }
    .dark .tab-btn:hover:not(.active) { color: #94a3b8; }

    /* ── Review Cards ─────────────────────────────────── */
    .review-card {
        background: #fff;
        border: 1px solid #f1f5f9;
        border-radius: 16px;
        padding: 32px;
        position: relative;
        overflow: hidden;
        transition: all .3s cubic-bezier(.22,1,.36,1);
        box-shadow: 0 1px 3px rgba(0,0,0,.02), 0 4px 12px rgba(0,0,0,.02);
    }
    .dark .review-card {
        background: #1e293b;
        border-color: #334155;
        box-shadow: 0 1px 3px rgba(0,0,0,.1), 0 4px 12px rgba(0,0,0,.1);
    }
    .review-card::before {
        content: '\201C';
        position: absolute;
        top: 12px;
        right: 20px;
        font-size: 80px;
        font-weight: 900;
        color: #f1f5f9;
        line-height: 1;
        pointer-events: none;
        transition: color .3s;
    }
    .dark .review-card::before { color: #334155; }
    .review-card:hover {
        transform: translateY(-3px);
        box-shadow: 0 4px 12px rgba(0,0,0,.04), 0 12px 32px rgba(67,56,202,.06);
        border-color: #e0e7ff;
    }
    .dark .review-card:hover {
        box-shadow: 0 4px 12px rgba(0,0,0,.2), 0 12px 32px rgba(67,56,202,.08);
        border-color: #4338CA;
    }
    .review-card:hover::before { color: #eef2ff; }
    .dark .review-card:hover::before { color: #312e81; }
    .review-avatar {
        width: 44px;
        height: 44px;
        border-radius: 14px;
        object-fit: cover;
        border: 2px solid #f1f5f9;
        flex-shrink: 0;
        transition: border-color .2s;
    }
    .dark .review-avatar { border-color: #334155; }
    .review-name { font-size: 14px; font-weight: 700; color: #111827; transition: color .2s; }
    .dark .review-name { color: #f1f5f9; }
    .review-job { font-size: 12px; color: #94a3b8; }
    .review-time { font-size: 11px; color: #cbd5e1; font-weight: 500; }
    .review-stars [data-lucide="star"] { font-size: 12px; width: 12px; height: 12px; }
    .review-stars .star-on { color: #f59e0b; }
    .review-stars .star-off { color: #e2e8f0; }
    .dark .review-stars .star-off { color: #475569; }
    .review-rating-badge { font-size: 12px; font-weight: 700; color: #475569; margin-left: 6px; }
    .dark .review-rating-badge { color: #94a3b8; }
    .review-body { font-size: 14px; color: #475569; line-height: 1.7; margin-top: 14px; transition: color .2s; }
    .dark .review-body { color: #94a3b8; }

    /* ── Pending Cards ────────────────────────────────── */
    .pending-card {
        background: #fffbeb;
        border: 1px solid #fde68a;
        border-radius: 14px;
        padding: 24px 28px;
        transition: all .25s;
    }
    .dark .pending-card {
        background: rgba(245,158,11,.06);
        border-color: rgba(245,158,11,.15);
    }
    .pending-card:hover {
        box-shadow: 0 4px 16px rgba(245,158,11,.08);
        border-color: #fcd34d;
    }
    .dark .pending-card:hover {
        box-shadow: 0 4px 16px rgba(245,158,11,.1);
        border-color: rgba(245,158,11,.3);
    }

    /* ── Empty States ─────────────────────────────────── */
    .empty-icon {
        width: 64px;
        height: 64px;
        border-radius: 20px;
        display: flex;
        align-items: center;
        justify-content: center;
        margin: 0 auto 16px;
        background: #f3f4f6;
        transition: background .2s;
    }
    .dark .empty-icon { background: #334155; }

    /* ── Responsive ───────────────────────────────────── */
    @media (max-width: 1024px) { .stats-panel { position: static; } }
    @media (max-width: 640px) {
        .stats-big-number { font-size: 48px; }
        .review-card { padding: 24px; }
    }
</style>

<div class="reviews-page max-w-7xl mx-auto px-4 sm:px-6 mt-14 mb-12">
    <div class="flex flex-col lg:flex-row gap-8">

        <!-- ═══ LEFT: STATS PANEL (Sticky) ══════════════════════════════ -->
        <aside class="w-full lg:w-80 flex-shrink-0">
            <div class="stats-panel fade-in">
                <!-- Hero: Rating + Stars -->
                <div class="stats-hero">
                    <div class="stats-big-number"><?= $totalReviews > 0 ? number_format($avgRating, 1) : '—' ?></div>
                    <div class="stats-stars-row">
                        <?php for ($i = 1; $i <= 5; $i++): ?>
                            <i data-lucide="star" class="<?= $i <= round($avgRating) ? 'stats-star-glow' : 'stats-star-dim' ?>"></i>
                        <?php endfor; ?>
                    </div>
                    <p class="stats-review-count"><?= $totalReviews ?> review<?= $totalReviews !== 1 ? 's' : '' ?></p>
                </div>

                <!-- Micro-Table Distribution -->
                <div class="dist-section">
                    <table class="dist-table">
                        <?php for ($s = 5; $s >= 1; $s--):
                            $pct = $totalReviews > 0 ? round(($dist[$s] / $totalReviews) * 100) : 0;
                        ?>
                            <tr>
                                <td class="dist-star-cell"><?= $s ?><i data-lucide="star"></i></td>
                                <td class="dist-bar-cell">
                                    <div class="dist-bar-track">
                                        <div class="dist-bar-fill" style="width: <?= $pct ?>%"></div>
                                    </div>
                                </td>
                                <td class="dist-count-cell"><?= $dist[$s] ?></td>
                            </tr>
                        <?php endfor; ?>
                    </table>
                </div>

                <!-- Verified Trust Mark -->
                <div class="trust-mark-section">
                    <div class="trust-mark">
                        <span class="trust-mark-icon"><i data-lucide="check"></i></span>
                        Verified Trust Mark
                    </div>
                </div>
            </div>
        </aside>

        <!-- ═══ RIGHT: CONTENT ══════════════════════════════════════════ -->
        <div class="flex-1 min-w-0 space-y-8">

            <!-- ── Pending Reviews ───────────────────────────────────── -->
            <?php if ($pendingContracts->num_rows > 0): ?>
            <div class="fade-in" style="animation-delay:.05s">
                <div class="flex items-center gap-3 mb-4">
                    <div class="w-10 h-10 rounded-xl bg-amber-100 dark:bg-amber-900/30 flex items-center justify-center">
                        <i data-lucide="pen-line" class="text-amber-500 dark:text-amber-400"></i>
                    </div>
                    <div>
                        <h2 class="text-base font-bold text-gray-900 dark:text-gray-100">Pending Reviews</h2>
                        <p class="text-xs text-gray-400 dark:text-gray-500">Completed contracts awaiting your feedback</p>
                    </div>
                </div>
                <div class="space-y-4">
                    <?php while ($pc = $pendingContracts->fetch_assoc()): ?>
                        <div class="pending-card" id="pending-<?= (int) $pc['id'] ?>">
                            <div class="flex flex-col sm:flex-row sm:items-center gap-4">
                                <div class="flex-1 min-w-0">
                                    <h3 class="text-sm font-bold text-gray-900 dark:text-gray-100 mb-1"><?= sanitize_string($pc['job_title']) ?></h3>
                                    <div class="flex flex-wrap items-center gap-3 text-xs text-gray-400 dark:text-gray-500">
                                        <span class="flex items-center gap-1.5">
                                            <i data-lucide="user" class="text-indigo-400 dark:text-indigo-400"></i>
                                            <?= sanitize_string($pc['client_name']) ?>
                                        </span>
                                        <span class="flex items-center gap-1.5">
                                            <i data-lucide="dollar-sign" class="text-emerald-500 dark:text-emerald-400"></i>
                                            <?= format_currency((float) $pc['total_budget']) ?>
                                        </span>
                                        <span class="flex items-center gap-1.5">
                                            <i data-lucide="calendar" class="text-gray-400 dark:text-gray-500"></i>
                                            <?= date('M d, Y', strtotime($pc['created_at'])) ?>
                                        </span>
                                    </div>
                                </div>
                                <button onclick="openReviewModal(<?= (int) $pc['id'] ?>, <?= (int) $pc['client_user_id'] ?>, '<?= sanitize_string(addslashes($pc['client_name'])) ?>', '<?= sanitize_string(addslashes($pc['job_title'])) ?>')"
                                    class="btn-grad inline-flex items-center gap-2 text-white text-xs font-semibold px-5 py-2.5 rounded-xl shadow-lg shadow-indigo-500/25 flex-shrink-0 hover:shadow-xl hover:shadow-indigo-500/30 transition-shadow">
                                    <i data-lucide="star" class="text-[10px]"></i> Leave Review
                                </button>
                            </div>
                        </div>
                    <?php endwhile; ?>
                </div>
            </div>
            <?php endif; ?>

            <!-- ── Tab Toggle + Reviews ──────────────────────────────── -->
            <div class="fade-in" style="animation-delay:.1s">
                <div class="flex flex-col sm:flex-row sm:items-center gap-4 mb-6">
                    <div class="flex items-center gap-3">
                    <div class="w-10 h-10 rounded-xl bg-indigo-100 dark:bg-indigo-900/30 flex items-center justify-center">
                        <i data-lucide="messages-square" class="text-indigo-500 dark:text-indigo-400"></i>
                    </div>
                    <h2 class="text-base font-bold text-gray-900 dark:text-gray-100">Reviews</h2>
                    </div>
                    <div class="tab-toggle sm:ml-auto" id="reviewTabs">
                        <button class="tab-btn active" onclick="switchTab('received')" id="tabReceived">Reviews Received</button>
                        <button class="tab-btn" onclick="switchTab('given')" id="tabGiven">Reviews I've Given</button>
                    </div>
                </div>

                <!-- Reviews Received -->
                <div id="panelReceived" class="space-y-5">
                    <?php if ($receivedReviews->num_rows > 0): ?>
                        <?php while ($rv = $receivedReviews->fetch_assoc()): ?>
                            <div class="review-card">
                                <div class="flex items-start gap-4">
                                    <?php
                                    $rvImg = get_profile_image($rv['reviewer_image']);
                                    $rvHasImg = $rv['reviewer_image'] && $rvImg !== '/jobhub/assets/upload/profile.png';
                                    $rvInitials = strtoupper(substr($rv['reviewer_name'], 0, 1));
                                    ?>
                                    <?php if ($rvHasImg): ?>
                                        <img src="<?= htmlspecialchars($rvImg) ?>" class="review-avatar" alt="">
                                    <?php else: ?>
                                        <div class="review-avatar bg-indigo-100 dark:bg-indigo-900/40 text-indigo-600 dark:text-indigo-400 flex items-center justify-center font-bold text-sm"><?= $rvInitials ?></div>
                                    <?php endif; ?>
                                    <div class="flex-1 min-w-0">
                                        <div class="flex items-center justify-between mb-1">
                                            <div>
                                                <p class="review-name"><?= sanitize_string($rv['reviewer_name']) ?></p>
                                                <p class="review-job"><?= sanitize_string($rv['job_title']) ?></p>
                                            </div>
                                            <span class="review-time"><?= time_ago($rv['created_at']) ?></span>
                                        </div>
                                        <div class="flex items-center review-stars">
                                            <?php for ($i = 1; $i <= 5; $i++): ?>
                                                <i data-lucide="star" class="<?= $i <= $rv['rating'] ? 'star-on' : 'star-off' ?>"></i>
                                            <?php endfor; ?>
                                            <span class="review-rating-badge"><?= $rv['rating'] ?>/5</span>
                                        </div>
                                        <?php if (!empty($rv['comment'])): ?>
                                            <p class="review-body"><?= nl2br(sanitize_string($rv['comment'])) ?></p>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <div class="text-center py-16">
                            <div class="empty-icon">
                                <i data-lucide="messages-square" class="text-2xl text-gray-300 dark:text-gray-600"></i>
                            </div>
                            <p class="text-gray-500 dark:text-gray-400 text-sm font-semibold mb-1">No reviews received yet</p>
                            <p class="text-gray-400 dark:text-gray-500 text-xs">Complete contracts to start receiving client feedback</p>
                        </div>
                    <?php endif; ?>
                </div>

                <!-- Reviews Given (hidden by default) -->
                <div id="panelGiven" class="space-y-5 hidden">
                    <?php if ($givenReviews->num_rows > 0): ?>
                        <?php while ($gv = $givenReviews->fetch_assoc()): ?>
                            <div class="review-card">
                                <div class="flex items-start gap-4">
                                    <?php
                                    $gvImg = get_profile_image($gv['reviewee_image']);
                                    $gvHasImg = $gv['reviewee_image'] && $gvImg !== '/jobhub/assets/upload/profile.png';
                                    $gvInitials = strtoupper(substr($gv['reviewee_name'], 0, 1));
                                    ?>
                                    <?php if ($gvHasImg): ?>
                                        <img src="<?= htmlspecialchars($gvImg) ?>" class="review-avatar" alt="">
                                    <?php else: ?>
                                        <div class="review-avatar bg-indigo-100 dark:bg-indigo-900/40 text-indigo-600 dark:text-indigo-400 flex items-center justify-center font-bold text-sm"><?= $gvInitials ?></div>
                                    <?php endif; ?>
                                    <div class="flex-1 min-w-0">
                                        <div class="flex items-center justify-between mb-1">
                                            <div>
                                                <p class="review-name"><?= sanitize_string($gv['reviewee_name']) ?></p>
                                                <p class="review-job"><?= sanitize_string($gv['job_title']) ?></p>
                                            </div>
                                            <span class="review-time"><?= time_ago($gv['created_at']) ?></span>
                                        </div>
                                        <div class="flex items-center review-stars">
                                            <?php for ($i = 1; $i <= 5; $i++): ?>
                                                <i data-lucide="star" class="<?= $i <= $gv['rating'] ? 'star-on' : 'star-off' ?>"></i>
                                            <?php endfor; ?>
                                            <span class="review-rating-badge"><?= $gv['rating'] ?>/5</span>
                                        </div>
                                        <?php if (!empty($gv['comment'])): ?>
                                            <p class="review-body"><?= nl2br(sanitize_string($gv['comment'])) ?></p>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <div class="text-center py-16">
                            <div class="empty-icon">
                                <i data-lucide="pencil" class="text-2xl text-gray-300 dark:text-gray-600"></i>
                            </div>
                            <p class="text-gray-500 dark:text-gray-400 text-sm font-semibold mb-1">No reviews given yet</p>
                            <p class="text-gray-400 dark:text-gray-500 text-xs">Leave reviews for clients after completing contracts</p>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

        </div>
    </div>
</div>

<?php $conn->close(); ?>

<!-- ═══ REVIEW MODAL ══════════════════════════════════════════════════ -->
<div id="reviewModal" class="fixed inset-0 z-50 hidden">
    <div class="absolute inset-0 bg-black/40 backdrop-blur-sm" onclick="closeReviewModal()"></div>
    <div class="absolute inset-0 flex items-center justify-center p-4">
        <div class="bg-white dark:bg-slate-800 rounded-2xl shadow-2xl w-full max-w-md relative overflow-hidden">
            <!-- Header -->
            <div class="bg-gradient-to-r from-[#4338CA] to-[#6366F1] px-6 py-5 text-white">
                <button onclick="closeReviewModal()" class="absolute top-4 right-4 w-8 h-8 rounded-full bg-white/20 flex items-center justify-center hover:bg-white/30 transition-colors">
                    <i data-lucide="x" class="text-sm"></i>
                </button>
                <h3 class="text-lg font-bold">Leave a Review</h3>
                <p class="text-sm text-white/80 mt-1" id="modalJobTitle"></p>
            </div>

            <!-- Form -->
            <form id="reviewForm" class="p-6 space-y-5">
                <?= csrf_field() ?>
                <input type="hidden" name="contract_id" id="modalContractId" value="">
                <input type="hidden" name="reviewee_id" id="modalRevieweeId" value="">
                <input type="hidden" name="rating" id="modalRating" value="0">

                <div>
                    <p class="text-sm font-semibold text-gray-700 dark:text-gray-300 mb-1">Reviewing: <span id="modalRevieweeName" class="text-[#4338CA]"></span></p>
                </div>

                <!-- Star Rating -->
                <div>
                    <label class="block text-sm font-semibold text-gray-700 dark:text-gray-300 mb-2">Your Rating</label>
                    <div class="star-rating flex items-center gap-1" id="starRating">
                        <?php for ($i = 1; $i <= 5; $i++): ?>
                            <button type="button" class="star-btn text-3xl text-gray-300 dark:text-gray-600 hover:text-amber-400 transition-colors focus:outline-none"
                                data-rating="<?= $i ?>"
                                onclick="setRating(<?= $i ?>)"
                                onmouseover="hoverRating(<?= $i ?>)"
                                onmouseout="resetHover()">
                                <i data-lucide="star"></i>
                            </button>
                        <?php endfor; ?>
                    </div>
                    <p class="text-xs text-gray-400 mt-1" id="ratingLabel">Select a rating</p>
                </div>

                <!-- Comment -->
                <div>
                    <label class="block text-sm font-semibold text-gray-700 dark:text-gray-300 mb-2">Your Review</label>
                    <textarea name="comment" id="modalComment" rows="4" maxlength="2000"
                        class="w-full px-4 py-3 rounded-xl border border-gray-200 dark:border-gray-600 bg-white dark:bg-slate-700 text-sm text-gray-700 dark:text-gray-200 placeholder-gray-400 dark:placeholder-gray-500 focus:ring-2 focus:ring-[#4338CA] focus:border-transparent transition-all resize-none"
                        placeholder="Describe your experience working with this client..."></textarea>
                    <div class="flex justify-between mt-1">
                        <p class="text-[11px] text-red-500 hidden" id="commentError">Comment is required</p>
                        <p class="text-[11px] text-gray-400 ml-auto"><span id="charCount">0</span>/2000</p>
                    </div>
                </div>

                <!-- Submit -->
                <button type="submit" id="submitReviewBtn"
                    class="w-full btn-grad text-white font-bold py-3 rounded-xl text-sm shadow-lg shadow-indigo-500/25 disabled:opacity-50 disabled:cursor-not-allowed flex items-center justify-center gap-2 hover:shadow-xl hover:shadow-indigo-500/30 transition-shadow">
                    <i data-lucide="send" class="text-xs"></i>
                    <span id="submitBtnText">Submit Review</span>
                    <i data-lucide="loader" class="animate-spin text-xs hidden" id="submitSpinner"></i>
                </button>
            </form>
        </div>
    </div>
</div>

<!-- ═══ SUCCESS MODAL ══════════════════════════════════════════════════ -->
<div id="successModal" class="fixed inset-0 z-50 hidden">
    <div class="absolute inset-0 bg-black/40 backdrop-blur-sm" onclick="closeSuccessModal()"></div>
    <div class="absolute inset-0 flex items-center justify-center p-4">
        <div class="bg-white dark:bg-slate-800 rounded-2xl shadow-2xl w-full max-w-sm p-8 text-center">
            <div class="w-16 h-16 rounded-full bg-emerald-100 dark:bg-emerald-900/30 flex items-center justify-center mx-auto mb-4">
                <i data-lucide="check" class="text-2xl text-emerald-500 dark:text-emerald-400"></i>
            </div>
            <h3 class="text-lg font-bold text-gray-900 dark:text-gray-100 mb-2">Review Submitted!</h3>
            <p class="text-sm text-gray-500 dark:text-gray-400 mb-6">Thank you for your feedback.</p>
            <button onclick="closeSuccessModal()" class="btn-grad text-white font-semibold px-8 py-2.5 rounded-xl text-sm">
                Continue
            </button>
        </div>
    </div>
</div>

<script src="/jobhub/assets/js/reviews.js"></script>
<script>
    // ── Tab Switching ──────────────────────────────────────────────
    function switchTab(tab) {
        const received = document.getElementById('panelReceived');
        const given = document.getElementById('panelGiven');
        const btnR = document.getElementById('tabReceived');
        const btnG = document.getElementById('tabGiven');

        if (tab === 'received') {
            received.classList.remove('hidden');
            given.classList.add('hidden');
            btnR.classList.add('active');
            btnG.classList.remove('active');
        } else {
            given.classList.remove('hidden');
            received.classList.add('hidden');
            btnG.classList.add('active');
            btnR.classList.remove('active');
        }
    }

    // ── Review Modal ───────────────────────────────────────────────
    function openReviewModal(contractId, revieweeId, revieweeName, jobTitle) {
        document.getElementById('modalContractId').value = contractId;
        document.getElementById('modalRevieweeId').value = revieweeId;
        document.getElementById('modalRevieweeName').textContent = revieweeName;
        document.getElementById('modalJobTitle').textContent = jobTitle;
        document.getElementById('modalRating').value = 0;
        document.getElementById('modalComment').value = '';
        document.getElementById('charCount').textContent = '0';
        document.getElementById('commentError').classList.add('hidden');
        document.getElementById('ratingLabel').textContent = 'Select a rating';
        resetStarDisplay();
        document.getElementById('reviewModal').classList.remove('hidden');
        document.body.style.overflow = 'hidden';
    }

    function closeReviewModal() {
        document.getElementById('reviewModal').classList.add('hidden');
        document.body.style.overflow = '';
    }

    function closeSuccessModal() {
        document.getElementById('successModal').classList.add('hidden');
        document.body.style.overflow = '';
        window.location.reload();
    }
</script>
<?php require_once __DIR__ . '/../components/freelancer_footer.php'; ?>