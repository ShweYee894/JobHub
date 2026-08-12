<?php
/**
 * Wallet Display Helpers
 * Helper functions for rendering wallet UI components.
 */

/**
 * Format currency with $ sign and commas.
 */
function wallet_format_currency(float $amount): string
{
    return '$' . number_format($amount, 2);
}

/**
 * Get the color class for a wallet history item direction.
 */
function wallet_direction_class(string $direction): string
{
    return match ($direction) {
        'credit' => 'text-emerald-600',
        'debit'  => 'text-red-500',
        default  => 'text-gray-600',
    };
}

/**
 * Get the icon color class for a wallet history item.
 */
function wallet_icon_color_class(string $color): string
{
    $map = [
        'emerald' => 'bg-emerald-50 text-emerald-600 dark:bg-emerald-950/50 dark:text-emerald-400',
        'amber'   => 'bg-amber-50 text-amber-600 dark:bg-amber-950/50 dark:text-amber-400',
        'blue'    => 'bg-blue-50 text-blue-600 dark:bg-blue-950/50 dark:text-blue-400',
        'purple'  => 'bg-purple-50 text-purple-600 dark:bg-purple-950/50 dark:text-purple-400',
        'red'     => 'bg-red-50 text-red-500 dark:bg-red-950/50 dark:text-red-400',
        'gray'    => 'bg-gray-50 text-gray-400 dark:bg-slate-700/50 dark:text-slate-400',
    ];
    return $map[$color] ?? 'bg-gray-50 text-gray-400 dark:bg-slate-700/50 dark:text-slate-400';
}

/**
 * Get badge class for wallet history status.
 */
function wallet_status_badge(string $type): string
{
    return match ($type) {
        'deposit'       => 'bg-emerald-50 text-emerald-600 border border-emerald-200/60 dark:bg-emerald-950/40 dark:text-emerald-400 dark:border-emerald-800/40',
        'escrow_hold'   => 'bg-amber-50 text-amber-600 border border-amber-200/60 dark:bg-amber-950/40 dark:text-amber-400 dark:border-amber-800/40',
        'escrow_release'=> 'bg-blue-50 text-blue-600 border border-blue-200/60 dark:bg-blue-950/40 dark:text-blue-400 dark:border-blue-800/40',
        'refund'        => 'bg-purple-50 text-purple-600 border border-purple-200/60 dark:bg-purple-950/40 dark:text-purple-400 dark:border-purple-800/40',
        'credit'        => 'bg-emerald-50 text-emerald-600 border border-emerald-200/60 dark:bg-emerald-950/40 dark:text-emerald-400 dark:border-emerald-800/40',
        'withdrawal'    => 'bg-red-50 text-red-500 border border-red-200/60 dark:bg-red-950/40 dark:text-red-400 dark:border-red-800/40',
        default         => 'bg-gray-50 text-gray-500 border border-gray-200/60 dark:bg-slate-700/40 dark:text-slate-400 dark:border-slate-600/40',
    };
}

/**
 * Group history items by date (Today, Yesterday, or date).
 */
function wallet_group_history_by_date(array $history): array
{
    $grouped = [];
    $today = date('Y-m-d');
    $yesterday = date('Y-m-d', strtotime('-1 day'));

    foreach ($history as $item) {
        $itemDate = date('Y-m-d', strtotime($item['date']));
        if ($itemDate === $today) {
            $group = 'Today';
        } elseif ($itemDate === $yesterday) {
            $group = 'Yesterday';
        } else {
            $group = date('M j, Y', strtotime($item['date']));
        }

        if (!isset($grouped[$group])) {
            $grouped[$group] = [];
        }
        $grouped[$group][] = $item;
    }

    return $grouped;
}

/**
 * Render a wallet stat card.
 */
function render_wallet_stat_card(string $label, float $value, string $icon, string $color, string $sublabel, int $delay = 0): void
{
    $colorMap = [
        'blue'    => 'bg-blue-50 text-blue-600',
        'emerald' => 'bg-emerald-50 text-emerald-600',
        'purple'  => 'bg-purple-50 text-purple-600',
        'amber'   => 'bg-amber-50 text-amber-600',
    ];
    $badgeColor = $colorMap[$color] ?? $colorMap['blue'];
    ?>
    <div class="stat-card bg-white rounded-2xl p-5 border border-gray-100 shadow-sm fade-in dark:bg-slate-800 dark:border-slate-700" style="animation-delay:<?= $delay ?>s">
        <div class="flex items-center justify-between mb-3">
            <div class="w-11 h-11 rounded-xl <?= $badgeColor ?> flex items-center justify-center">
                <i data-lucide="<?= $icon ?>" class="w-5 h-5"></i>
            </div>
            <span class="text-[10px] font-semibold uppercase tracking-wider <?= wallet_direction_class('credit') ?>"><?= htmlspecialchars($label) ?></span>
        </div>
        <p class="text-2xl font-black text-gray-900 dark:text-white wallet-amount" data-amount="<?= $value ?>"><?= wallet_format_currency($value) ?></p>
        <p class="text-xs text-gray-400 mt-1 dark:text-slate-500"><?= htmlspecialchars($sublabel) ?></p>
    </div>
    <?php
}

/**
 * Get icon name for transaction type.
 */
function wallet_type_icon(string $type): string
{
    return match ($type) {
        'deposit'       => 'plus-circle',
        'escrow_hold'   => 'shield',
        'escrow_release'=> 'check-circle',
        'refund'        => 'rotate-ccw',
        'credit'        => 'check-circle',
        'withdrawal'    => 'arrow-up-right',
        default         => 'circle',
    };
}

/**
 * Get icon color class for segmented table layout.
 */
function wallet_table_icon_class(string $color): string
{
    $map = [
        'emerald' => 'bg-emerald-50 text-emerald-500 dark:bg-emerald-950/50 dark:text-emerald-400',
        'amber'   => 'bg-amber-50 text-amber-500 dark:bg-amber-950/50 dark:text-amber-400',
        'blue'    => 'bg-blue-50 text-blue-500 dark:bg-blue-950/50 dark:text-blue-400',
        'purple'  => 'bg-purple-50 text-purple-500 dark:bg-purple-950/50 dark:text-purple-400',
        'red'     => 'bg-red-50 text-red-500 dark:bg-red-950/50 dark:text-red-400',
        'gray'    => 'bg-gray-100 text-gray-400 dark:bg-slate-700/50 dark:text-slate-400',
    ];
    return $map[$color] ?? $map['gray'];
}

/**
 * Render a single wallet history item – Strict Grid Ledger Design.
 *
 * Grid (md+): [1] [2-4] [5-7] [8-9] [10-11] [12]
 *              Icon  Type  Milestone  Amount  Badge  Action
 */
function render_wallet_history_item(array $item): void
{
    $dirClass = $item['direction'] === 'credit'
        ? 'text-emerald-600 dark:text-emerald-400'
        : ($item['direction'] === 'debit' ? 'text-slate-800 dark:text-slate-200' : 'text-gray-600 dark:text-gray-400');
    $prefix = $item['direction'] === 'credit' ? '+' : '';

    $iconBgClass = match ($item['color']) {
        'emerald' => 'bg-emerald-50 text-emerald-500 dark:bg-emerald-950/50 dark:text-emerald-400',
        'amber'   => 'bg-amber-50 text-amber-500 dark:bg-amber-950/50 dark:text-amber-400',
        'blue'    => 'bg-blue-50 text-blue-500 dark:bg-blue-950/50 dark:text-blue-400',
        'purple'  => 'bg-purple-50 text-purple-500 dark:bg-purple-950/50 dark:text-purple-400',
        'red'     => 'bg-red-50 text-red-500 dark:bg-red-950/50 dark:text-red-400',
        default   => 'bg-gray-100 text-gray-400 dark:bg-slate-700/50 dark:text-slate-400',
    };

    $badgeClass = match ($item['type']) {
        'deposit'        => 'bg-emerald-50 text-emerald-700 dark:bg-emerald-950/40 dark:text-emerald-400',
        'escrow_hold'    => 'bg-amber-50 text-amber-700 dark:bg-amber-950/40 dark:text-amber-400',
        'escrow_release' => 'bg-blue-50 text-blue-700 dark:bg-blue-950/40 dark:text-blue-400',
        'refund'         => 'bg-purple-50 text-purple-700 dark:bg-purple-950/40 dark:text-purple-400',
        'credit'         => 'bg-emerald-50 text-emerald-700 dark:bg-emerald-950/40 dark:text-emerald-400',
        'withdrawal'     => 'bg-red-50 text-red-600 dark:bg-red-950/40 dark:text-red-400',
        default          => 'bg-gray-100 text-gray-600 dark:bg-slate-700/40 dark:text-slate-400',
    };

    $badgeLabel = match ($item['type']) {
        'deposit'        => 'Deposit',
        'escrow_hold'    => 'Held',
        'escrow_release' => 'Released',
        'refund'         => 'Refund',
        'credit'         => 'Credit',
        'withdrawal'     => 'Withdrawal',
        default          => ucfirst(str_replace('_', ' ', $item['type'])),
    };

    $txnIcon = wallet_type_icon($item['type']);
    $milestoneTitle = $item['milestone_title'] ?? null;
    $milestoneId = $item['reference_id'] ?? null;
    ?>
    <div class="txn-row group" data-transaction-type="<?= htmlspecialchars($item['type']) ?>">
        <div class="txn-grid">
            <!-- Col 1: Icon -->
            <div class="col-icon">
                <div class="txn-icon-wrap w-9 h-9 md:w-10 md:h-10 rounded-full <?= $iconBgClass ?> flex items-center justify-center shrink-0">
                    <i data-lucide="<?= $txnIcon ?>" class="txn-icon"></i>
                </div>
            </div>

            <!-- Col 2-4: Transaction Type -->
            <div class="col-type">
                <p class="txn-type-label m-0"><?= htmlspecialchars($item['label']) ?></p>
                <p class="txn-type-sub m-0"><?= htmlspecialchars($item['description'] ?? '') ?></p>
            </div>

            <!-- Col 5-7: Milestone (hidden on mobile) -->
            <div class="col-milestone">
                <?php if ($milestoneTitle): ?>
                    <span class="txn-milestone-pill" title="<?= htmlspecialchars($milestoneTitle) ?>">
                        <?= htmlspecialchars($milestoneTitle) ?>
                    </span>
                <?php elseif ($milestoneId): ?>
                    <span class="txn-milestone-pill">Milestone #<?= (int) $milestoneId ?></span>
                <?php else: ?>
                    <span class="text-gray-300 dark:text-slate-600">—</span>
                <?php endif; ?>
            </div>

            <!-- Col 8-9: Amount -->
            <div class="col-amount">
                <span class="txn-amount <?= $dirClass ?>"><?= $prefix ?><?= wallet_format_currency($item['amount']) ?></span>
            </div>

            <!-- Col 10-11: Status Badge -->
            <div class="col-badge">
                <span class="txn-badge <?= $badgeClass ?>"><?= $badgeLabel ?></span>
            </div>

            <!-- Col 12: Action -->
            <div class="col-action">
                <a href="wallet_history_detail.php?id=<?= (int) $item['id'] ?>" class="txn-action-link">View Details</a>
            </div>
        </div>
    </div>
    <?php
}
