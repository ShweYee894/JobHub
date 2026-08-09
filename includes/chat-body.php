<?php

/**
 * Chat Body Component - Messages container + empty state + input area
 */
?>
<!-- Messages Container -->
<div id="chatMessages" class="flex-1 overflow-y-auto p-6 space-y-4" style="min-height: 0; display: flex; flex-direction: column;">
</div>

<!-- Empty State Message -->
<div id="emptyChatMsg" class="flex-1 items-center justify-center hidden" style="min-height: 0;">
    <div class="text-center">
        <div class="w-14 h-14 rounded-2xl bg-gray-100 dark:bg-slate-700 flex items-center justify-center mx-auto mb-3">
            <i data-lucide="send" class="text-xl text-gray-300 dark:text-gray-500"></i>
        </div>
        <p class="text-gray-500 dark:text-gray-400 text-sm">No messages yet</p>
        <p class="text-gray-400 dark:text-gray-500 text-xs mt-1">Send the first message to start the conversation</p>
    </div>
</div>

<!-- File Preview -->
<div id="filePreview" class="px-4 pt-2 hidden"></div>

<!-- Message Input -->
<div id="chatInputArea" class="p-4 border-t border-gray-100 dark:border-slate-700 hidden bg-white dark:bg-slate-800" style="flex-shrink: 0;">
    <div class="flex items-center gap-2">
        <input type="file" id="fileInput" class="hidden" accept=".pdf,.doc,.docx,.zip,.png,.jpg,.jpeg">
        <button type="button" id="attachBtn" class="w-9 h-9 rounded-lg bg-gray-100 dark:bg-slate-700 hover:bg-gray-200 dark:hover:bg-slate-600 flex items-center justify-center text-gray-400 hover:text-gray-600 dark:hover:text-gray-300 transition-colors flex-shrink-0" title="Attach file">
            <i data-lucide="paperclip" class="w-4 h-4"></i>
        </button>

        <div class="relative">
            <button type="button" id="emojiBtn" class="w-9 h-9 rounded-lg bg-gray-100 dark:bg-slate-700 hover:bg-gray-200 dark:hover:bg-slate-600 flex items-center justify-center text-gray-400 hover:text-gray-600 dark:hover:text-gray-300 transition-colors flex-shrink-0" title="Emoji">
                <i data-lucide="smile" class="w-4 h-4"></i>
            </button>
            <div id="emojiPicker" class="hidden absolute bottom-12 left-0 bg-white dark:bg-slate-800 border border-gray-200 dark:border-slate-600 rounded-2xl shadow-xl p-3 z-50 w-72">
                <div class="emoji-grid" id="emojiGrid"></div>
            </div>
        </div>

        <div class="flex-1">
            <textarea id="chatInput" rows="1" placeholder="Type your message..."
                class="w-full bg-gray-50 dark:bg-slate-700 border border-gray-200 dark:border-slate-600 rounded-xl px-4 py-2 text-sm text-gray-900 dark:text-gray-100 placeholder-gray-400 resize-none focus:outline-none focus:border-blue-500 focus:ring-2 focus:ring-blue-500/10 transition-all"></textarea>
        </div>

        <button id="chatSendBtn" type="button" class="w-9 h-9 rounded-xl bg-indigo-500 flex items-center justify-center text-white shadow-sm flex-shrink-0 ">
            <i data-lucide="send" class="w-4 h-4"></i>
        </button>
    </div>
</div>

<!-- Placeholder (no room selected) -->
<div id="chatPlaceholder" class="flex-1 flex items-center justify-center bg-white dark:bg-slate-800" style="min-height: 0;">
    <div class="text-center px-6">
        <div class="w-24 h-24 mx-auto mb-6 relative">
            <div class="absolute inset-0 bg-gradient-to-br from-blue-100 to-cyan-100 dark:from-slate-700 dark:to-slate-600 rounded-3xl rotate-6 opacity-60"></div>
            <div class="absolute inset-0 bg-gradient-to-br from-blue-50 to-indigo-50 dark:from-slate-700 dark:to-slate-600 rounded-3xl flex items-center justify-center border border-blue-100 dark:border-slate-600">
                <svg class="w-10 h-10 text-blue-400 dark:text-blue-300" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M8.625 12a.375.375 0 1 1-.75 0 .375.375 0 0 1 .75 0Zm0 0H8.25m4.125 0a.375.375 0 1 1-.75 0 .375.375 0 0 1 .75 0Zm0 0H12m4.125 0a.375.375 0 1 1-.75 0 .375.375 0 0 1 .75 0Zm0 0h-.375M21 12c0 4.556-4.03 8.25-9 8.25a9.764 9.764 0 0 1-2.555-.337A5.972 5.972 0 0 1 5.41 20.97a5.969 5.969 0 0 1-.474-.065 4.48 4.48 0 0 0 .978-2.025c.09-.457-.133-.901-.467-1.226C3.93 16.178 3 14.189 3 12c0-4.556 4.03-8.25 9-8.25s9 3.694 9 8.25Z" />
                </svg>
            </div>
        </div>
        <h3 class="text-lg font-bold text-gray-900 dark:text-white mb-2">No conversations yet</h3>
        <p class="text-sm text-gray-400 dark:text-gray-500 max-w-xs mx-auto leading-relaxed">Start by applying to a job or hiring a freelancer. Your messages will appear here.</p>
    </div>
</div>
