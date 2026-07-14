<?php
/**
 * Chat Header Component
 * Requires: $otherName, $otherImage, $otherId (set by parent)
 */
$otherName = $otherName ?? 'User';
$otherImage = $otherImage ?? null;
$avatarUrl = $otherImage
    ? '/finalproject/' . htmlspecialchars($otherImage, ENT_QUOTES, 'UTF-8')
    : 'https://ui-avatars.com/api/?name=' . urlencode($otherName) . '&background=6366f1&color=fff&bold=true&size=40';
?>
<div id="chatHeader" class="px-6 py-4 border-b border-gray-100 dark:border-slate-700 items-center gap-3 hidden sm:flex bg-white dark:bg-slate-800" style="flex-shrink: 0;">
    <button onclick="chat._showListOnMobile()" class="sm:hidden text-gray-400 hover:text-gray-600 dark:hover:text-gray-300 mr-2 transition-colors">
        <i class="fas fa-arrow-left"></i>
    </button>
    <img id="chatHeaderAvatar" src="<?= $avatarUrl ?>"
         class="w-10 h-10 rounded-full object-cover border-2 border-gray-100 dark:border-slate-600 flex-shrink-0" alt="">
    <div class="flex-1 min-w-0">
        <h3 id="chatHeaderName" class="text-sm font-bold text-gray-900 dark:text-white truncate"><?= sanitize_string($otherName) ?></h3>
        <p id="chatHeaderStatus" class="text-[11px] text-gray-400 dark:text-gray-500 font-medium">Offline</p>
    </div>
</div>
