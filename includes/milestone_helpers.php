<?php
/**
 * Milestone Helper Functions
 * Permission checks, validation utilities, and status helpers.
 * Requires $conn (db connection) and helpers.php to be available.
 */

// ── Allowed status transitions ────────────────────────────────────────────
define('MILESTONE_STATUS_TRANSITIONS', [
    'pending'            => ['funded_in_escrow', 'disputed'],
    'funded_in_escrow'   => ['submitted', 'disputed'],
    'submitted'          => ['released', 'funded_in_escrow', 'disputed'],
    'released'           => [],
    'disputed'           => ['released', 'pending'],
]);

// ── Status display labels ─────────────────────────────────────────────────
define('MILESTONE_STATUS_LABELS', [
    'pending'            => 'Created',
    'funded_in_escrow'   => 'Funded',
    'submitted'          => 'Submitted',
    'released'           => 'Completed',
    'disputed'           => 'Disputed',
]);

// ── Status badge CSS classes ──────────────────────────────────────────────
define('MILESTONE_STATUS_CLASSES', [
    'pending'            => 'bg-gray-100 text-gray-600 border border-gray-200',
    'funded_in_escrow'   => 'bg-blue-50 text-blue-600 border border-blue-200',
    'submitted'          => 'bg-purple-50 text-purple-600 border border-purple-200',
    'released'           => 'bg-emerald-50 text-emerald-600 border border-emerald-200',
    'disputed'           => 'bg-red-50 text-red-500 border border-red-200',
]);

// ══════════════════════════════════════════════════════════════════════════
// PERMISSION CHECKS
// ══════════════════════════════════════════════════════════════════════════

/**
 * Check if a status transition is allowed.
 */
function is_valid_status_transition(string $from, string $to): bool {
    $allowed = MILESTONE_STATUS_TRANSITIONS[$from] ?? [];
    return in_array($to, $allowed, true);
}

/**
 * Check if the current user can create milestones for a contract.
 */
function can_create_milestone(array $contract, int $userId, string $role): bool {
    if ($role !== 'client') return false;
    return (int) $contract['client_id'] === $userId;
}

/**
 * Check if the current user can edit a milestone.
 * Only allowed when status = pending (before funding).
 */
function can_edit_milestone(array $milestone, int $userId, string $role): bool {
    if ($role === 'admin') return true;
    if ($role !== 'client') return false;
    if ((int) $milestone['client_id'] !== $userId) return false;
    return $milestone['status'] === 'pending';
}

/**
 * Check if the current user can delete a milestone.
 * Only allowed when status = pending and no submission exists.
 */
function can_delete_milestone(array $milestone, int $userId, string $role): bool {
    if ($role === 'admin') return true;
    if ($role !== 'client') return false;
    if ((int) $milestone['client_id'] !== $userId) return false;
    return $milestone['status'] === 'pending';
}

/**
 * Check if the current user can fund a milestone.
 */
function can_fund_milestone(array $milestone, int $userId, string $role): bool {
    if ($role !== 'client') return false;
    if ((int) $milestone['client_id'] !== $userId) return false;
    return $milestone['status'] === 'pending';
}

/**
 * Check if the current user can submit work on a milestone.
 */
function can_submit_milestone(array $milestone, int $userId, string $role): bool {
    if ($role !== 'freelancer') return false;
    if ((int) $milestone['freelancer_id'] !== $userId) return false;
    return $milestone['status'] === 'funded_in_escrow';
}

/**
 * Check if the current user can approve a milestone.
 */
function can_approve_milestone(array $milestone, int $userId, string $role): bool {
    if ($role !== 'client') return false;
    if ((int) $milestone['client_id'] !== $userId) return false;
    return $milestone['status'] === 'submitted';
}

/**
 * Check if the current user can request revision on a milestone.
 */
function can_request_revision(array $milestone, int $userId, string $role): bool {
    if ($role !== 'client') return false;
    if ((int) $milestone['client_id'] !== $userId) return false;
    return $milestone['status'] === 'submitted';
}

/**
 * Check if the current user can dispute a milestone.
 */
function can_dispute_milestone(array $milestone, int $userId, string $role): bool {
    if (!in_array($role, ['client', 'freelancer'])) return false;
    if ((int) $milestone['client_id'] !== $userId && (int) $milestone['freelancer_id'] !== $userId) return false;
    return !in_array($milestone['status'], ['released', 'disputed', 'pending']);
}

// ══════════════════════════════════════════════════════════════════════════
// VALIDATION HELPERS
// ══════════════════════════════════════════════════════════════════════════

/**
 * Validate milestone data for creation/update.
 * Returns error string or null if valid.
 */
function validate_milestone_data(string $title, float $amount, ?string $dueDate = null): ?string {
    if (empty($title) || strlen($title) > 255) {
        return 'Title is required (max 255 chars).';
    }
    if ($amount <= 0) {
        return 'Amount must be greater than 0.';
    }
    if ($amount > 999999.99) {
        return 'Amount exceeds maximum allowed.';
    }
    if (!empty($dueDate) && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $dueDate)) {
        return 'Invalid due date format (YYYY-MM-DD).';
    }
    return null;
}

/**
 * Check if milestone total exceeds contract budget.
 * Returns error string or null if valid.
 */
function validate_budget_constraint(int $contractId, float $newAmount, ?int $excludeMilestoneId = null): ?string {
    global $conn;

    $sql = 'SELECT COALESCE(SUM(amount), 0) AS total FROM milestones WHERE contract_id = ?';
    if ($excludeMilestoneId) {
        $sql .= ' AND id != ?';
        $stmt = $conn->prepare($sql);
        $stmt->bind_param('ii', $contractId, $excludeMilestoneId);
    } else {
        $stmt = $conn->prepare($sql);
        $stmt->bind_param('i', $contractId);
    }
    $stmt->execute();
    $existingTotal = (float) $stmt->get_result()->fetch_assoc()['total'];
    $stmt->close();

    $contractStmt = $conn->prepare('SELECT total_budget FROM contracts WHERE id = ?');
    $contractStmt->bind_param('i', $contractId);
    $contractStmt->execute();
    $budget = (float) $contractStmt->get_result()->fetch_assoc()['total_budget'];
    $contractStmt->close();

    if ($existingTotal + $newAmount > $budget) {
        return 'Total milestones would exceed contract budget.';
    }
    return null;
}

// ══════════════════════════════════════════════════════════════════════════
// STATUS DISPLAY HELPERS
// ══════════════════════════════════════════════════════════════════════════

/**
 * Get formatted status label for display.
 */
function get_milestone_status_label(string $status): string {
    return MILESTONE_STATUS_LABELS[$status] ?? ucfirst(str_replace('_', ' ', $status));
}

/**
 * Get CSS class for a milestone status badge.
 */
function get_milestone_status_class(string $status): string {
    return MILESTONE_STATUS_CLASSES[$status] ?? 'bg-gray-100 text-gray-600 border border-gray-200';
}

/**
 * Check if a milestone can receive submissions (resubmit after revision).
 */
function is_resubmit(array $milestone): bool {
    return $milestone['status'] === 'funded_in_escrow'
        && !empty($milestone['submission_github_url']);
}
