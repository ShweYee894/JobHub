<?php

/**
 * notification_helper.php
 * Convenience functions that create common notifications.
 * Requires $conn (db connection) to be available when called.
 */

require_once __DIR__ . '/notifications.php';

/**
 * Get a NotificationService instance.
 */
function getNotificationService(): PlatformNotificationService
{
    global $conn;
    return new PlatformNotificationService($conn);
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
        "/finalproject/client/proposal_detail.php?id={$jobId}"
    );
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
        "/finalproject/freelancer/contract_detail.php?id={$contractId}"
    );
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
        "/finalproject/freelancer/contract_detail.php?id={$contractId}"
    );
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
        '/finalproject/client/contracts.php'
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
        "/finalproject/freelancer/earnings.php"
    );
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
        "/finalproject/{$messagesPage}?room={$roomId}"
    );
}

/**
 * Notify a user that they received a review.
 */
function notifyReviewReceived(int $userId, string $reviewerName, int $rating): void
{
    $ns = getNotificationService();
    $stars = str_repeat('★', $rating) . str_repeat('☆', 5 - $rating);
    $ns->create(
        $userId,
        'review_received',
        'Review Received',
        "{$reviewerName} left you a review: {$stars}",
        '/finalproject/freelancer/reviews.php'
    );
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
        "/finalproject/freelancer/invitation_action.php?job_id={$jobId}&client_id={$clientId}"
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
        "/finalproject/client/proposals.php"
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
        "/finalproject/client/invite_jobs.php"
    );
}
