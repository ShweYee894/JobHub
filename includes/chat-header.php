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
<div id="chatHeader" class="px-6 py-4 border-b border-gray-100 items-center gap-3 hidden">
    <button onclick="chat._showRoomsOnMobile()" class="sm:hidden text-gray-400 hover:text-gray-600 mr-2 transition-colors">
        <i class="fas fa-arrow-left"></i>
    </button>
    <img id="chatHeaderAvatar" src="<?= $avatarUrl ?>"
         class="w-10 h-10 rounded-full object-cover border-2 border-gray-100 flex-shrink-0" alt="">
    <div class="flex-1 min-w-0">
        <h3 id="chatHeaderName" class="text-sm font-bold text-gray-900 truncate"><?= sanitize_string($otherName) ?></h3>
        <p id="chatHeaderStatus" class="text-[11px] text-gray-400 font-medium">Offline</p>
    </div>
    <!-- <div id="chatHeaderActions" class="flex items-center gap-2">
        <button onclick="chat._showRoomsOnMobile()" class="lg:hidden w-8 h-8 rounded-lg bg-gray-100 hover:bg-gray-200 flex items-center justify-center text-gray-400 transition-colors" title="Back to conversations">
            <i class="fas fa-list text-sm"></i>
        </button>
    </div> -->
</div>
