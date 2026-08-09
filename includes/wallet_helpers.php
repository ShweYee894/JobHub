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
        'emerald' => 'bg-emerald-50 text-emerald-600',
        'amber'   => 'bg-amber-50 text-amber-600',
        'blue'    => 'bg-blue-50 text-blue-600',
        'purple'  => 'bg-purple-50 text-purple-600',
        'red'     => 'bg-red-50 text-red-500',
        'gray'    => 'bg-gray-50 text-gray-400',
    ];
    return $map[$color] ?? 'bg-gray-50 text-gray-400';
}

/**
 * Get badge class for wallet history status.
 */
function wallet_status_badge(string $type): string
{
    return match ($type) {
        'deposit'       => 'bg-emerald-50 text-emerald-600 border border-emerald-200',
        'escrow_hold'   => 'bg-amber-50 text-amber-600 border border-amber-200',
        'escrow_release'=> 'bg-blue-50 text-blue-600 border border-blue-200',
        'refund'        => 'bg-purple-50 text-purple-600 border border-purple-200',
        'credit'        => 'bg-emerald-50 text-emerald-600 border border-emerald-200',
        'withdrawal'    => 'bg-red-50 text-red-500 border border-red-200',
        default         => 'bg-gray-50 text-gray-500 border border-gray-200',
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
 * Render a single wallet history item.
 */
function render_wallet_history_item(array $item): void
{
    $dirClass = $item['direction'] === 'credit' ? 'text-emerald-600' : ($item['direction'] === 'debit' ? 'text-red-500' : 'text-gray-600');
    $prefix = $item['direction'] === 'credit' ? '+' : '-';
    $iconColor = wallet_icon_color_class($item['color']);
    $badgeClass = wallet_status_badge($item['type']);
    ?>
    <div class="flex items-center gap-4 p-4 hover:bg-gray-50 dark:hover:bg-slate-700/50 rounded-xl transition-colors">
        <div class="w-10 h-10 rounded-xl <?= $iconColor ?> flex items-center justify-center shrink-0">
            <i data-lucide="<?= $item['icon'] ?>" class="w-4 h-4"></i>
        </div>
        <div class="flex-1 min-w-0">
            <p class="text-sm font-semibold text-gray-900 dark:text-white m-0"><?= htmlspecialchars($item['label']) ?></p>
            <p class="text-[11px] text-gray-400 dark:text-slate-500 mt-0.5 m-0"><?= htmlspecialchars($item['description'] ?? '') ?></p>
        </div>
        <div class="text-right shrink-0">
            <p class="text-sm font-bold <?= $dirClass ?> m-0"><?= $prefix ?><?= wallet_format_currency($item['amount']) ?></p>
            <span class="inline-block px-2 py-0.5 rounded-md text-[10px] font-semibold <?= $badgeClass ?> mt-1">Completed</span>
        </div>
    </div>
    <?php
}
