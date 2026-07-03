<?php

/**
 * Reusable Stat Card – Pure Tailwind CSS
 * No external CSS files. All styles via Tailwind + inline style.
 *
 * @param string $title, $value, $trend, $trendPct, $trendLabel, $icon, $color, $unit, $link, $delay
 */
function renderStatCard(
    string $title,
    mixed $value,
    string $trend = 'neutral',
    string $trendPct = '',
    string $trendLabel = 'this month',
    string $icon = 'fa-chart-simple',
    string $color = 'blue',
    string $unit = '',
    string $link = '',
    int $delay = 0
): void {
    $colors = [
        // 'blue'    => ['bg' => '#EEF5FF', 'icon' => '#2563EB', 'text' => '#2563EB', 'border' => '#2563EB'],
        // 'emerald' => ['bg' => '#ECFDF5', 'icon' => '#059669', 'text' => '#059669', 'border' => '#059669'],
        // 'purple'  => ['bg' => '#F5F3FF', 'icon' => '#7C3AED', 'text' => '#7C3AED', 'border' => '#7C3AED'],
        // 'orange'  => ['bg' => '#FFF7ED', 'icon' => '#EA580C', 'text' => '#EA580C', 'border' => '#EA580C'],
        // 'cyan'    => ['bg' => '#ECFEFF', 'icon' => '#0891B2', 'text' => '#0891B2', 'border' => '#0891B2'],
        // 'rose'    => ['bg' => '#FFF1F2', 'icon' => '#E11D48', 'text' => '#E11D48', 'border' => '#E11D48'],
        'blue' => ['bg' => '238, 245, 255', 'icon' => '#2563EB', 'text' => '#2563EB', 'border' => '#2563EB'],
        'emerald' => ['bg' => '236, 253, 245', 'icon' => '#059669', 'text' => '#059669', 'border' => '#059669'],
        'purple' => ['bg' => '245, 243, 255', 'icon' => '#7C3AED', 'text' => '#7C3AED', 'border' => '#7C3AED'],
        'orange' => ['bg' => '255, 247, 237', 'icon' => '#EA580C', 'text' => '#EA580C', 'border' => '#EA580C'],
        'cyan' => ['bg' => '236, 254, 255', 'icon' => '#0891B2', 'text' => '#0891B2', 'border' => '#0891B2'],
        'rose' => ['bg' => '255, 241, 242', 'icon' => '#E11D48', 'text' => '#E11D48', 'border' => '#E11D48'],
    ];
    $legacy = ['teal' => 'cyan', 'green' => 'emerald', 'amber' => 'orange', 'red' => 'rose', 'violet' => 'purple', 'indigo' => 'blue'];
    $color = $legacy[$color] ?? $color;
    $c = $colors[$color] ?? $colors['blue'];

    $valueStr = is_float($value) ? '$' . number_format($value, 2) : number_format((int) $value);
    $trendColor = $c['text'];
    $arrow = '';
    if ($trend === 'up')
        $arrow = '<svg width="10" height="10" viewBox="0 0 10 10" fill="none" style="display:inline-block;vertical-align:middle;margin-right:2px;"><path d="M5 2L8.5 6.5H1.5L5 2Z" fill="' . $trendColor . '"/></svg>';
    elseif ($trend === 'down') {
        $trendColor = '#E11D48';
        $arrow = '<svg width="10" height="10" viewBox="0 0 10 10" fill="none" style="display:inline-block;vertical-align:middle;margin-right:2px;"><path d="M5 8L1.5 3.5H8.5L5 8Z" fill="' . $trendColor . '"/></svg>';
    }

    $delayMs = $delay * 60;
    $tag = $link ? 'a' : 'div';
    $href = $link ? ' href="' . htmlspecialchars($link) . '"' : '';
    $hover = $link ? ' hover:shadow-md cursor-pointer' : '';
    $opacity = 0.5;
    ?>
    <<?= $tag . $href ?> class="stat-card fade-in<?= $hover ?>" style="animation-delay:<?= $delayMs ?>ms; --accent:<?= $c['border'] ?>; text-decoration:none; color:inherit; display:block;background-color: rgb(<?= $c['bg'] ?> / <?= $opacity ?>); border: 1px solid <?= $c['border'] ?>;">
        <div class="flex items-center justify-between gap-3">
            <div class="flex-1 min-w-0">
                <p class="text-xs font-medium text-slate-400 mb-1.5 truncate"><?= htmlspecialchars($title) ?></p>
                <div class="flex items-baseline gap-2 flex-wrap">
                    <span class="text-2xl font-extrabold text-gray-900 dark:text-white leading-none tracking-tight"><?= $valueStr ?></span>
                    <?php if ($unit): ?>
                        <span class="text-xs font-medium text-slate-400"><?= htmlspecialchars($unit) ?></span>
                    <?php endif; ?>
                </div>
                <?php if ($trendPct !== ''): ?>
                    <div class="flex items-center gap-1 mt-1.5">
                        <?= $arrow ?>
                        <span class="text-[11px] font-semibold" style="color:<?= $trendColor ?>"><?= htmlspecialchars($trendPct) ?></span>
                        <?php if ($trendLabel): ?>
                            <span class="text-[10px] text-slate-400"><?= htmlspecialchars($trendLabel) ?></span>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>
            </div>
            <div class="w-[42px] h-[42px] min-w-[42px] rounded-xl flex items-center justify-center text-base text-white" style="background:<?= $c['icon'] ?>">
                <i class="fas <?= htmlspecialchars($icon) ?>"></i>
            </div>
        </div>
    </<?= $tag ?>>
    <?php
}
?>
