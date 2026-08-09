<?php

/**
 * notification_helper.php
 * Convenience functions that create common notifications.
 * Requires $conn (db connection) to be available when called.
 */

require_once __DIR__ . '/notifications.php';
require_once __DIR__ . '/EmailNotificationService.php';

/**
 * Get a NotificationService instance.
 */
function getNotificationService(): PlatformNotificationService
{
    global $conn;
    return new PlatformNotificationService($conn);
}

/**
 * Get an EmailNotificationService instance.
 */
function getEmailService(): EmailNotificationService
{
    global $conn;
    return new EmailNotificationService($conn);
}

/**
 * Get a user's role by ID.
 */
function getUserRole(int $userId): string
{
    global $conn;
    $stmt = $conn->prepare('SELECT role FROM users WHERE id = ?');
    $stmt->bind_param('i', $userId);
    $stmt->execute();
    $result = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    return $result['role'] ?? 'freelancer';
}

/**
 * Notify a freelancer that a new proposal has been submitted on their job.
 */
function notifyNewProposal(int $freelancerId, string $jobTitle, int $jobId): void
{
    $ns = getNotificationService();
    $ns->create(
        $freelancerId,
        'new_proposal',
        'New Proposal Received',
        "A new proposal has been submitted for your job: \"{$jobTitle}\".",
        "/jobhub/client/proposal_detail.php?id={$jobId}"
    );

    // Send email notification
    $emailService = getEmailService();
    $emailService->sendProposalReceived($freelancerId, $jobTitle, $jobId);
}

/**
 * Notify a freelancer that their proposal was accepted.
 */
function notifyProposalAccepted(int $freelancerId, string $clientName, int $contractId): void
{
    $ns = getNotificationService();
    $ns->create(
        $freelancerId,
        'proposal_accepted',
        'Proposal Accepted!',
        "Your proposal was accepted by {$clientName}. A contract has been created.",
        "/jobhub/freelancer/contract_detail.php?id={$contractId}"
    );

    // Send email notification
    $emailService = getEmailService();
    $emailService->sendProposalAccepted($freelancerId, $clientName, $contractId);
}

/**
 * Notify a freelancer about a new contract.
 */
function notifyNewContract(int $freelancerId, string $clientName, int $contractId): void
{
    $ns = getNotificationService();
    $ns->create(
        $freelancerId,
        'new_contract',
        'New Contract Created',
        "You have a new contract with {$clientName}.",
        "/jobhub/freelancer/contract_detail.php?id={$contractId}"
    );
}

/**
 * Notify a client that a contract has been created.
 */
function notifyClientContractCreated(int $clientId, string $freelancerName, int $contractId): void
{
    $ns = getNotificationService();
    $ns->create(
        $clientId,
        'contract_created',
        'New Contract Created',
        "A new contract has been created with {$freelancerName}.",
        "/jobhub/client/contract_detail.php?id={$contractId}"
    );

    // Send email notification
    $emailService = getEmailService();
    $emailService->sendContractCreated($clientId, $freelancerName, $contractId);
}

/**
 * Notify a freelancer that a milestone has been funded.
 */
function notifyMilestoneFunded(int $freelancerId, string $milestoneTitle, float $amount, int $contractId): void
{
    $ns = getNotificationService();
    $formatted = '$' . number_format($amount, 2);
    $ns->create(
        $freelancerId,
        'milestone_funded',
        'Milestone Funded',
        "The milestone \"{$milestoneTitle}\" has been funded with {$formatted}. You can now submit your work.",
        "/jobhub/freelancer/contract_detail.php?id={$contractId}"
    );

    // Send email notification
    $emailService = getEmailService();
    $emailService->sendMilestoneFunded($freelancerId, $milestoneTitle, $amount, $contractId);
}

/**
 * Notify a freelancer that a milestone has been approved.
 */
function notifyMilestoneApproved(int $freelancerId, string $milestoneTitle, float $amount, int $contractId): void
{
    $ns = getNotificationService();
    $formatted = '$' . number_format($amount, 2);
    $ns->create(
        $freelancerId,
        'milestone_approved',
        'Milestone Approved',
        "Your milestone \"{$milestoneTitle}\" has been approved. Payment of {$formatted} has been released.",
        "/jobhub/freelancer/earnings.php"
    );

    // Send email notification
    $emailService = getEmailService();
    $emailService->sendMilestoneApproved($freelancerId, $milestoneTitle, $amount, $contractId);
}

/**
 * Notify a client that a milestone has been submitted for review.
 */
function notifyMilestoneSubmitted(int $clientId, string $freelancerName, string $milestoneTitle): void
{
    $ns = getNotificationService();
    $ns->create(
        $clientId,
        'milestone_submitted',
        'Milestone Submitted',
        "{$freelancerName} has submitted the milestone: \"{$milestoneTitle}\" for your review.",
        '/jobhub/client/contracts.php'
    );
}

/**
 * Notify a freelancer that payment has been released.
 */
function notifyPaymentReleased(int $freelancerId, float $amount, int $contractId): void
{
    $ns = getNotificationService();
    $formatted = '$' . number_format($amount, 2);
    $ns->create(
        $freelancerId,
        'payment_released',
        'Payment Released',
        "A payment of {$formatted} has been released for your contract.",
        "/jobhub/freelancer/earnings.php"
    );

    // Send email notification
    $emailService = getEmailService();
    $emailService->sendPaymentReleased($freelancerId, $amount, $contractId);
}

/**
 * Notify a user about a new message.
 */
function notifyNewMessage(int $userId, string $senderName, int $roomId, string $role = 'freelancer'): void
{
    $ns = getNotificationService();
    $messagesPage = $role === 'client' ? 'client/messages.php' : 'freelancer/messages.php';
    $ns->create(
        $userId,
        'new_message',
        'New Message',
        "You have a new message from {$senderName}.",
        "/jobhub/{$messagesPage}?room={$roomId}"
    );
}

/**
 * Notify a user that they received a review.
 */
function notifyReviewReceived(int $userId, string $reviewerName, int $rating): void
{
    $role = getUserRole($userId);
    $link = ($role === 'client')
        ? '/jobhub/client/reviews.php'
        : '/jobhub/freelancer/reviews.php';

    $ns = getNotificationService();
    $stars = str_repeat('★', $rating) . str_repeat('☆', 5 - $rating);
    $ns->create(
        $userId,
        'review_received',
        'Review Received',
        "{$reviewerName} left you a review: {$stars}",
        $link
    );
}

/**
 * Notify users about a dispute being opened.
 */
function notifyDisputeOpened(int $userId, string $disputerName, string $contractTitle, int $contractId): void
{
    $role = getUserRole($userId);
    $link = ($role === 'client')
        ? "/jobhub/client/contract_detail.php?id={$contractId}"
        : "/jobhub/freelancer/contract_detail.php?id={$contractId}";

    $ns = getNotificationService();
    $ns->create(
        $userId,
        'dispute_opened',
        'Dispute Opened',
        "{$disputerName} has opened a dispute for contract: \"{$contractTitle}\".",
        $link
    );

    // Send email notification
    $emailService = getEmailService();
    $emailService->sendDisputeOpened($userId, $disputerName, $contractTitle, $contractId);
}

/**
 * Notify both parties about a dispute being resolved.
 */
function notifyDisputeResolved(int $userId, string $adminName, string $contractTitle, int $contractId, string $resolution): void
{
    $role = getUserRole($userId);
    $link = ($role === 'client')
        ? "/jobhub/client/contract_detail.php?id={$contractId}"
        : "/jobhub/freelancer/contract_detail.php?id={$contractId}";

    $ns = getNotificationService();
    $ns->create(
        $userId,
        'dispute_resolved',
        'Dispute Resolved',
        "Your dispute for contract \"{$contractTitle}\" has been resolved by {$adminName}.",
        $link
    );

    $emailService = getEmailService();
    $emailService->sendDisputeResolved($userId, $adminName, $contractTitle, $contractId, $resolution);
}

/**
 * Notify both parties about a dispute being dismissed.
 */
function notifyDisputeDismissed(int $userId, string $adminName, string $contractTitle, int $contractId, string $reason): void
{
    $role = getUserRole($userId);
    $link = ($role === 'client')
        ? "/jobhub/client/contract_detail.php?id={$contractId}"
        : "/jobhub/freelancer/contract_detail.php?id={$contractId}";

    $ns = getNotificationService();
    $ns->create(
        $userId,
        'dispute_dismissed',
        'Dispute Dismissed',
        "Your dispute for contract \"{$contractTitle}\" has been dismissed by {$adminName}.",
        $link
    );

    $emailService = getEmailService();
    $emailService->sendDisputeDismissed($userId, $adminName, $contractTitle, $contractId, $reason);
}

// ══════════════════════════════════════════════════════════════════════════
// INVITATION NOTIFICATION HELPERS
// ══════════════════════════════════════════════════════════════════════════

/**
 * Notify a freelancer that a client has invited them to a job.
 */
function notifyJobInvitation(int $freelancerId, string $clientName, string $jobTitle, int $jobId, int $clientId): void
{
    $ns = getNotificationService();
    $ns->create(
        $freelancerId,
        'job_invitation',
        'New Job Invitation',
        "{$clientName} has invited you to apply for: \"{$jobTitle}\".",
        "/jobhub/freelancer/invitation_action.php?job_id={$jobId}&client_id={$clientId}"
    );
}

/**
 * Notify a client that a freelancer accepted their job invitation.
 */
function notifyInvitationAccepted(int $clientId, string $freelancerName, string $jobTitle, int $jobId): void
{
    $ns = getNotificationService();
    $ns->create(
        $clientId,
        'invitation_accepted',
        'Invitation Accepted',
        "{$freelancerName} accepted your invitation to apply for: \"{$jobTitle}\".",
        "/jobhub/client/proposals.php"
    );
}

/**
 * Notify a client that a freelancer declined their job invitation.
 */
function notifyInvitationDeclined(int $clientId, string $freelancerName, string $jobTitle, int $jobId): void
{
    $ns = getNotificationService();
    $ns->create(
        $clientId,
        'invitation_declined',
        'Invitation Declined',
        "{$freelancerName} declined your invitation to apply for: \"{$jobTitle}\".",
        "/jobhub/client/invite_jobs.php"
    );
}

// ══════════════════════════════════════════════════════════════════════════
// MILESTONE LIFECYCLE NOTIFICATIONS
// ══════════════════════════════════════════════════════════════════════════

/**
 * Notify freelancer that a new milestone has been created.
 */
function notifyMilestoneCreated(int $freelancerId, string $milestoneTitle, float $amount, int $contractId): void
{
    $ns = getNotificationService();
    $formatted = '$' . number_format($amount, 2);
    $ns->create(
        $freelancerId,
        'milestone_created',
        'New Milestone Created',
        "A new milestone \"{$milestoneTitle}\" ({$formatted}) has been added to your contract.",
        "/jobhub/freelancer/contract_detail.php?id={$contractId}"
    );
}

/**
 * Notify freelancer that a revision has been requested on their submission.
 */
function notifyRevisionRequested(int $freelancerId, string $milestoneTitle, string $revisionNote, int $contractId): void
{
    $ns = getNotificationService();
    $notePreview = !empty($revisionNote) ? substr($revisionNote, 0, 100) . (strlen($revisionNote) > 100 ? '...' : '') : 'No notes provided.';
    $ns->create(
        $freelancerId,
        'revision_requested',
        'Revision Requested',
        "A revision has been requested for milestone \"{$milestoneTitle}\". Reason: {$notePreview}",
        "/jobhub/freelancer/contract_detail.php?id={$contractId}"
    );
}

/**
 * Notify client that a freelancer has resubmitted work.
 */
function notifySubmissionResubmitted(int $clientId, string $freelancerName, string $milestoneTitle, int $contractId): void
{
    $ns = getNotificationService();
    $ns->create(
        $clientId,
        'submission_resubmitted',
        'Work Resubmitted',
        "{$freelancerName} has resubmitted the milestone: \"{$milestoneTitle}\" for your review.",
        "/jobhub/client/contract_detail.php?id={$contractId}"
    );
}

/**
 * Notify client that a milestone has been completed and payment released.
 */
function notifyMilestoneCompleted(int $clientId, string $milestoneTitle, float $amount, int $contractId): void
{
    $ns = getNotificationService();
    $formatted = '$' . number_format($amount, 2);
    $ns->create(
        $clientId,
        'milestone_completed',
        'Milestone Completed',
        "Milestone \"{$milestoneTitle}\" has been completed. Payment of {$formatted} has been released.",
        "/jobhub/client/contract_detail.php?id={$contractId}"
    );
}

/**
 * Notify freelancer that a milestone has been deleted.
 */
function notifyMilestoneDeleted(int $freelancerId, string $milestoneTitle, int $contractId): void
{
    $ns = getNotificationService();
    $ns->create(
        $freelancerId,
        'milestone_deleted',
        'Milestone Removed',
        "Milestone \"{$milestoneTitle}\" has been removed from your contract.",
        "/jobhub/freelancer/contract_detail.php?id={$contractId}"
    );
}

/**
 * Notify freelancer that admin has force-completed a milestone.
 */
function notifyMilestoneForceCompleted(int $freelancerId, string $milestoneTitle, int $contractId): void
{
    $ns = getNotificationService();
    $ns->create(
        $freelancerId,
        'milestone_force_completed',
        'Milestone Completed (Admin)',
        "Milestone \"{$milestoneTitle}\" has been marked as completed by an administrator.",
        "/jobhub/freelancer/contract_detail.php?id={$contractId}"
    );
}
