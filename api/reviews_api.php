<?php
/**
 * Reviews API
 * Handles review submission and retrieval for the freelance marketplace.
 *
 * Endpoints:
 *   POST  ?action=submit         – Submit a review for a completed contract
 *   GET   ?action=by_user&user_id=X   – Get all reviews for a user
 *   GET   ?action=by_contract&contract_id=X – Get review for a contract
 */

session_start();
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/helpers.php';
require_once __DIR__ . '/../auth/auth.php';

header('Content-Type: application/json');

$action = $_GET['action'] ?? '';

// ── Authenticate all API requests ──────────────────────────────────────
if (!is_logged_in()) {
    json_response(['success' => false, 'message' => 'Authentication required.'], 401);
}

$userId = (int) $_SESSION['user_id'];

switch ($action) {

    // ── SUBMIT REVIEW ──────────────────────────────────────────────────
    case 'submit':
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            json_response(['success' => false, 'message' => 'POST request required.'], 405);
        }

        if (!verify_csrf_token()) {
            json_response(['success' => false, 'message' => 'Invalid CSRF token.'], 403);
        }

        $contractId = sanitize_int($_POST['contract_id'] ?? 0);
        $rating     = sanitize_int($_POST['rating'] ?? 0);
        $comment    = trim($_POST['comment'] ?? '');

        // Validate inputs
        if ($contractId <= 0) {
            json_response(['success' => false, 'message' => 'Invalid contract ID.'], 400);
        }
        if ($rating < 1 || $rating > 5) {
            json_response(['success' => false, 'message' => 'Rating must be between 1 and 5.'], 400);
        }
        if (empty($comment)) {
            json_response(['success' => false, 'message' => 'Review comment is required.'], 400);
        }
        if (strlen($comment) > 2000) {
            json_response(['success' => false, 'message' => 'Comment must be 2000 characters or fewer.'], 400);
        }

        // Verify contract exists and is completed
        $stmt = $conn->prepare(
            'SELECT id, client_id, freelancer_id, status FROM contracts WHERE id = ?'
        );
        $stmt->bind_param('i', $contractId);
        $stmt->execute();
        $contract = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        if (!$contract) {
            json_response(['success' => false, 'message' => 'Contract not found.'], 404);
        }
        if ($contract['status'] !== 'completed') {
            json_response(['success' => false, 'message' => 'Only completed contracts can be reviewed.'], 400);
        }

        // Verify user is part of the contract (client or freelancer)
        $isClient     = ((int) $contract['client_id'] === $userId);
        $isFreelancer = ((int) $contract['freelancer_id'] === $userId);

        if (!$isClient && !$isFreelancer) {
            json_response(['success' => false, 'message' => 'You are not part of this contract.'], 403);
        }

        // Determine reviewer and reviewee
        $reviewerId = $userId;
        $revieweeId = $isClient ? (int) $contract['freelancer_id'] : (int) $contract['client_id'];

        // Check if this user already reviewed this contract (both parties can review)
        $stmt = $conn->prepare('SELECT id FROM reviews WHERE contract_id = ? AND reviewer_id = ?');
        $stmt->bind_param('ii', $contractId, $reviewerId);
        $stmt->execute();
        $existing = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        if ($existing) {
            json_response(['success' => false, 'message' => 'You have already submitted a review for this contract.'], 409);
        }

        // Insert the review
        $stmt = $conn->prepare(
            'INSERT INTO reviews (contract_id, reviewer_id, reviewee_id, rating, comment, created_at)
             VALUES (?, ?, ?, ?, ?, NOW())'
        );
        $stmt->bind_param('iiiss', $contractId, $reviewerId, $revieweeId, $rating, $comment);

        if ($stmt->execute()) {
            $reviewId = $stmt->insert_id;
            $stmt->close();
            json_response([
                'success'   => true,
                'message'   => 'Review submitted successfully.',
                'review_id' => $reviewId,
            ]);
        } else {
            $stmt->close();
            json_response(['success' => false, 'message' => 'Failed to submit review. Please try again.'], 500);
        }
        break;

    // ── GET REVIEWS BY USER ────────────────────────────────────────────
    case 'by_user':
        $targetUserId = sanitize_int($_GET['user_id'] ?? 0);
        if ($targetUserId <= 0) {
            json_response(['success' => false, 'message' => 'Invalid user ID.'], 400);
        }

        $stmt = $conn->prepare('
            SELECT r.id, r.rating, r.comment, r.created_at,
                   u.name AS reviewer_name, u.profile_image AS reviewer_image,
                   j.title AS job_title
            FROM reviews r
            JOIN users u ON r.reviewer_id = u.id
            JOIN contracts c ON r.contract_id = c.id
            JOIN jobs j ON c.job_id = j.id
            WHERE r.reviewee_id = ?
            ORDER BY r.created_at DESC
        ');
        $stmt->bind_param('i', $targetUserId);
        $stmt->execute();
        $result = $stmt->get_result();

        $reviews = [];
        while ($row = $result->fetch_assoc()) {
            $reviews[] = [
                'id'              => (int) $row['id'],
                'rating'          => (int) $row['rating'],
                'comment'         => $row['comment'],
                'created_at'      => $row['created_at'],
                'reviewer_name'   => $row['reviewer_name'],
                'reviewer_image'  => $row['reviewer_image'],
                'job_title'       => $row['job_title'],
            ];
        }
        $stmt->close();

        // Calculate average rating
        $stmtAvg = $conn->prepare('
            SELECT COALESCE(AVG(rating), 0) AS avg_rating, COUNT(*) AS total
            FROM reviews WHERE reviewee_id = ?
        ');
        $stmtAvg->bind_param('i', $targetUserId);
        $stmtAvg->execute();
        $avgData = $stmtAvg->get_result()->fetch_assoc();
        $stmtAvg->close();

        json_response([
            'success'      => true,
            'reviews'      => $reviews,
            'avg_rating'   => round((float) $avgData['avg_rating'], 1),
            'total_reviews' => (int) $avgData['total'],
        ]);
        break;

    // ── GET REVIEW BY CONTRACT ─────────────────────────────────────────
    case 'by_contract':
        $contractId = sanitize_int($_GET['contract_id'] ?? 0);
        if ($contractId <= 0) {
            json_response(['success' => false, 'message' => 'Invalid contract ID.'], 400);
        }

        $stmt = $conn->prepare('
            SELECT r.id, r.rating, r.comment, r.created_at,
                   u.name AS reviewer_name, u.profile_image AS reviewer_image,
                   CASE WHEN r.reviewer_id = c.client_id THEN \'client\' ELSE \'freelancer\' END AS reviewer_role
            FROM reviews r
            JOIN users u ON r.reviewer_id = u.id
            JOIN contracts c ON r.contract_id = c.id
            WHERE r.contract_id = ?
            ORDER BY r.created_at DESC
        ');
        $stmt->bind_param('i', $contractId);
        $stmt->execute();
        $result = $stmt->get_result();

        $reviews = [];
        while ($row = $result->fetch_assoc()) {
            $reviews[] = [
                'id'             => (int) $row['id'],
                'rating'         => (int) $row['rating'],
                'comment'        => $row['comment'],
                'created_at'     => $row['created_at'],
                'reviewer_name'  => $row['reviewer_name'],
                'reviewer_image' => $row['reviewer_image'],
                'reviewer_role'  => $row['reviewer_role'],
            ];
        }
        $stmt->close();

        json_response([
            'success' => true,
            'reviews' => $reviews,
        ]);
        break;

    default:
        json_response(['success' => false, 'message' => 'Invalid action.'], 400);
        break;
}
