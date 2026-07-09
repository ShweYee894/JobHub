<?php
$page_title = 'My Reviews';
require_once __DIR__ . '/../auth/auth.php';
require_role('client');
require_once __DIR__ . '/../config/db.php';

$currentPage = 'reviews';
$userId = (int) $_SESSION['user_id'];

// ── Fetch user info ───────────────────────────────────────────────────
$stmt = $conn->prepare('SELECT name, profile_image FROM users WHERE id = ?');
$stmt->bind_param('i', $userId);
$stmt->execute();
$user = $stmt->get_result()->fetch_assoc();
$stmt->close();

// ── Completed contracts that haven't been reviewed by this client ─────
$stmtPending = $conn->prepare('
    SELECT c.id, c.total_budget, c.contract_type, c.created_at,
           j.title AS job_title,
           u.name AS freelancer_name, u.id AS freelancer_user_id
    FROM contracts c
    JOIN jobs j ON c.job_id = j.id
    JOIN users u ON c.freelancer_id = u.id
    WHERE c.client_id = ? AND c.status = "completed"
      AND c.id NOT IN (SELECT contract_id FROM reviews WHERE reviewer_id = ?)
    ORDER BY c.created_at DESC
');
$stmtPending->bind_param('ii', $userId, $userId);
$stmtPending->execute();
$pendingContracts = $stmtPending->get_result();
$stmtPending->close();

// ── Reviews already submitted by this client ──────────────────────────
$stmtSubmitted = $conn->prepare('
    SELECT r.id, r.rating, r.comment, r.created_at,
           j.title AS job_title,
           u.name AS reviewee_name, u.id AS reviewee_id, u.profile_image AS reviewee_image
    FROM reviews r
    JOIN contracts c ON r.contract_id = c.id
    JOIN jobs j ON c.job_id = j.id
    JOIN users u ON r.reviewee_id = u.id
    WHERE r.reviewer_id = ?
    ORDER BY r.created_at DESC
');
$stmtSubmitted->bind_param('i', $userId);
$stmtSubmitted->execute();
$submittedReviews = $stmtSubmitted->get_result();
$stmtSubmitted->close();

$revieweeIdMap = [];

$pageTitle = 'Reviews';
$pageSubtitle = "Rate freelancers you've worked with";
$activePage = 'reviews';
$user = ['name' => $user['name'] ?? 'Client', 'profile_image' => $user['profile_image'] ?? null];
$unreadCount = get_unread_message_count($userId, 'client');
$profileLink = 'profile.php';
require_once __DIR__ . '/../includes/client_topbar.php';
?>

    <?php display_flash('success') ?>
    <?php display_flash('error') ?>
<main class="max-w-7xl mx-auto px-4 sm:px-6 py-8 flex flex-col gap-6">  
    <!-- ═══ PENDING REVIEWS ═══════════════════════════════════════ -->
    <div class="bg-white rounded-2xl border border-gray-100 shadow-sm fade-in">
        <div class="p-6 pb-0">
            <h2 class="text-base font-bold text-gray-900 flex items-center gap-2">
                <div class="w-8 h-8 rounded-lg bg-amber-50 flex items-center justify-center">
                    <i class="fas fa-pen text-amber-500 text-sm"></i>
                </div>
                Pending Reviews
            </h2>
            <p class="text-xs text-gray-400 mt-1">Completed contracts awaiting your feedback</p>
        </div>

        <div class="p-6">
        <?php if ($pendingContracts->num_rows > 0): ?>
            <div class="space-y-4">
            <?php while ($pc = $pendingContracts->fetch_assoc()): ?>
                <div class="p-4 rounded-xl border border-amber-100 bg-amber-50/50" id="pending-<?= (int) $pc['id'] ?>">
                    <div class="flex flex-col sm:flex-row sm:items-center gap-4">
                        <div class="flex-1 min-w-0">
                            <h3 class="text-sm font-bold text-gray-900 mb-1"><?= sanitize_string($pc['job_title']) ?></h3>
                            <div class="flex flex-wrap items-center gap-3 text-xs text-gray-400">
                                <span class="flex items-center gap-1.5">
                                    <i class="fas fa-user text-blue-400"></i>
                                    <?= sanitize_string($pc['freelancer_name']) ?>
                                </span>
                                <span class="flex items-center gap-1.5">
                                    <i class="fas fa-dollar-sign text-emerald-500"></i>
                                    <?= format_currency((float) $pc['total_budget']) ?>
                                </span>
                                <span class="flex items-center gap-1.5">
                                    <i class="fas fa-calendar text-gray-400"></i>
                                    <?= date('M d, Y', strtotime($pc['created_at'])) ?>
                                </span>
                            </div>
                        </div>
                        <button onclick="openReviewModal(<?= (int) $pc['id'] ?>, <?= (int) $pc['freelancer_user_id'] ?>, '<?= sanitize_string(addslashes($pc['freelancer_name'])) ?>', '<?= sanitize_string(addslashes($pc['job_title'])) ?>')"
                                class="btn-grad inline-flex items-center gap-2 text-white text-xs font-semibold px-5 py-2.5 rounded-xl shadow-lg shadow-blue-500/25 flex-shrink-0">
                            <i class="fas fa-star text-[10px]"></i> Leave Review
                        </button>
                    </div>
                </div>
            <?php endwhile; ?>
            </div>
        <?php else: ?>
            <div class="text-center py-8">
                <div class="w-14 h-14 rounded-2xl bg-gray-100 flex items-center justify-center mx-auto mb-3">
                    <i class="fas fa-check-double text-xl text-gray-300"></i>
                </div>
                <p class="text-gray-500 text-sm mb-1">No pending reviews</p>
                <p class="text-gray-400 text-xs">All completed contracts have been reviewed.</p>
            </div>
        <?php endif; ?>
        </div>
    </div>

    <!-- ═══ SUBMITTED REVIEWS ═════════════════════════════════════ -->
    <div class="bg-white rounded-2xl border border-gray-100 shadow-sm fade-in" style="animation-delay:.15s">
        <div class="p-6 pb-0">
            <h2 class="text-base font-bold text-gray-900 flex items-center gap-2">
                <div class="w-8 h-8 rounded-lg bg-blue-50 flex items-center justify-center">
                    <i class="fas fa-star text-blue-500 text-sm"></i>
                </div>
                My Submitted Reviews
            </h2>
            <p class="text-xs text-gray-400 mt-1">Reviews you've left for freelancers</p>
        </div>

        <div class="p-6">
        <?php if ($submittedReviews->num_rows > 0): ?>
            <div class="space-y-4">
            <?php while ($sr = $submittedReviews->fetch_assoc()): ?>
                <div class="p-4 rounded-xl bg-gray-50 border border-gray-100">
                    <div class="flex items-start gap-3">
                        <img src="<?= get_profile_image($sr['reviewee_image']) ?>"
                             class="w-10 h-10 rounded-full object-cover flex-shrink-0" alt="">
                        <div class="flex-1 min-w-0">
                            <div class="flex items-center justify-between mb-1">
                                <div>
                                    <p class="text-sm font-semibold text-gray-900"><?= sanitize_string($sr['reviewee_name']) ?></p>
                                    <p class="text-[11px] text-gray-400"><?= sanitize_string($sr['job_title']) ?></p>
                                </div>
                                <span class="text-[11px] text-gray-400"><?= time_ago($sr['created_at']) ?></span>
                            </div>
                            <div class="flex items-center gap-0.5 mb-2">
                                <?php for ($i = 1; $i <= 5; $i++): ?>
                                <i class="fas fa-star text-xs <?= $i <= $sr['rating'] ? 'star-filled' : 'star-empty' ?>"></i>
                                <?php endfor; ?>
                                <span class="text-xs font-semibold text-gray-600 ml-1"><?= $sr['rating'] ?>/5</span>
                            </div>
                            <?php if (!empty($sr['comment'])): ?>
                            <p class="text-sm text-gray-600 leading-relaxed"><?= nl2br(sanitize_string($sr['comment'])) ?></p>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            <?php endwhile; ?>
            </div>
        <?php else: ?>
            <div class="text-center py-8">
                <div class="w-14 h-14 rounded-2xl bg-gray-100 flex items-center justify-center mx-auto mb-3">
                    <i class="fas fa-star text-xl text-gray-300"></i>
                </div>
                <p class="text-gray-500 text-sm">No reviews submitted yet</p>
                <p class="text-gray-400 text-xs mt-1">Complete a contract to leave your first review</p>
            </div>
        <?php endif; ?>
        </div>
    </div>

<!-- ═══ REVIEW MODAL ══════════════════════════════════════════════════ -->
<div id="reviewModal" class="fixed inset-0 z-50 hidden">
    <div class="absolute inset-0 bg-black/40 backdrop-blur-sm" onclick="closeReviewModal()"></div>
    <div class="absolute inset-0 flex items-center justify-center p-4">
        <div class="bg-white rounded-2xl shadow-2xl w-full max-w-md relative overflow-hidden">
            <div class="bg-gradient-to-r from-blue-600 to-cyan-500 px-6 py-5 text-white">
                <button onclick="closeReviewModal()" class="absolute top-4 right-4 w-8 h-8 rounded-full bg-white/20 flex items-center justify-center hover:bg-white/30 transition-colors">
                    <i class="fas fa-times text-sm"></i>
                </button>
                <h3 class="text-lg font-bold">Leave a Review</h3>
                <p class="text-sm text-white/80 mt-1" id="modalJobTitle"></p>
            </div>

            <form id="reviewForm" class="p-6 space-y-5">
                <?= csrf_field() ?>
                <input type="hidden" name="contract_id" id="modalContractId" value="">
                <input type="hidden" name="reviewee_id" id="modalRevieweeId" value="">
                <input type="hidden" name="rating" id="modalRating" value="0">

                <div>
                    <p class="text-sm font-semibold text-gray-700 mb-1">Reviewing: <span id="modalRevieweeName" class="text-blue-600"></span></p>
                </div>

                <div>
                    <label class="block text-sm font-semibold text-gray-700 mb-2">Your Rating</label>
                    <div class="star-rating flex items-center gap-1" id="starRating">
                        <?php for ($i = 1; $i <= 5; $i++): ?>
                        <button type="button" class="star-btn text-3xl text-gray-300 hover:text-amber-400 transition-colors focus:outline-none"
                                data-rating="<?= $i ?>"
                                onclick="setRating(<?= $i ?>)"
                                onmouseover="hoverRating(<?= $i ?>)"
                                onmouseout="resetHover()">
                            <i class="fas fa-star"></i>
                        </button>
                        <?php endfor; ?>
                    </div>
                    <p class="text-xs text-gray-400 mt-1" id="ratingLabel">Select a rating</p>
                </div>

                <div>
                    <label class="block text-sm font-semibold text-gray-700 mb-2">Your Review</label>
                    <textarea name="comment" id="modalComment" rows="4" maxlength="2000"
                              class="w-full px-4 py-3 rounded-xl border border-gray-200 text-sm text-gray-700 placeholder-gray-400 focus:ring-2 focus:ring-blue-500 focus:border-transparent transition-all resize-none"
                              placeholder="Share your experience working with this freelancer..."></textarea>
                    <div class="flex justify-between mt-1">
                        <p class="text-[11px] text-red-500 hidden" id="commentError">Comment is required</p>
                        <p class="text-[11px] text-gray-400 ml-auto"><span id="charCount">0</span>/2000</p>
                    </div>
                </div>
                <?php $conn->close(); ?>
                <button type="submit" id="submitReviewBtn"
                        class="w-full btn-grad text-white font-bold py-3 rounded-xl text-sm shadow-lg shadow-blue-500/25 disabled:opacity-50 disabled:cursor-not-allowed flex items-center justify-center gap-2">
                    <i class="fas fa-paper-plane text-xs"></i>
                    <span id="submitBtnText">Submit Review</span>
                    <i class="fas fa-spinner fa-spin text-xs hidden" id="submitSpinner"></i>
                </button>
            </form>
        </div>
    </div>
</div>

<!-- ═══ SUCCESS MODAL ══════════════════════════════════════════════════ -->
<div id="successModal" class="fixed inset-0 z-50 hidden">
    <div class="absolute inset-0 bg-black/40 backdrop-blur-sm" onclick="closeSuccessModal()"></div>
    <div class="absolute inset-0 flex items-center justify-center p-4">
        <div class="bg-white rounded-2xl shadow-2xl w-full max-w-sm p-8 text-center">
            <div class="w-16 h-16 rounded-full bg-emerald-100 flex items-center justify-center mx-auto mb-4">
                <i class="fas fa-check text-2xl text-emerald-500"></i>
            </div>
            <h3 class="text-lg font-bold text-gray-900 mb-2">Review Submitted!</h3>
            <p class="text-sm text-gray-500 mb-6">Thank you for your feedback. It helps our community.</p>
            <button onclick="closeSuccessModal()" class="btn-grad text-white font-semibold px-8 py-2.5 rounded-xl text-sm">
                Continue
            </button>
        </div>
    </div>
</div>
</main>
<script src="/finalproject/assets/js/reviews.js"></script>
<script>
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
<?php require_once __DIR__ . '/../includes/client_footer.php'; ?>
