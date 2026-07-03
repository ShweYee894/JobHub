<?php
/**
 * Chat Body Component - Messages container + empty state + input area
 */
?>
<!-- Messages Container -->
<div id="chatMessages" class="flex-1 overflow-y-auto p-6 space-y-4 chat-messages">
</div>

<!-- Empty State Message -->
<div id="emptyChatMsg" class="flex-1 items-center justify-center hidden">
    <div class="text-center">
        <div class="w-14 h-14 rounded-2xl bg-gray-100 flex items-center justify-center mx-auto mb-3">
            <i class="fas fa-paper-plane text-xl text-gray-300"></i>
        </div>
        <p class="text-gray-500 text-sm">No messages yet</p>
        <p class="text-gray-400 text-xs mt-1">Send the first message to start the conversation</p>
    </div>
</div>

<!-- File Preview -->
<div id="filePreview" class="px-4 pt-2 hidden"></div>

<!-- Message Input -->
<div id="chatInputArea" class="p-4 border-t border-gray-100 hidden">
    <div class="flex items-end gap-2">
        <input type="file" id="fileInput" class="hidden" accept=".pdf,.doc,.docx,.zip,.png,.jpg,.jpeg">
        <button type="button" id="attachBtn" class="w-9 h-9 rounded-lg bg-gray-100 hover:bg-gray-200 flex items-center justify-center text-gray-400 hover:text-gray-600 transition-colors flex-shrink-0" title="Attach file">
            <i class="fas fa-paperclip text-sm"></i>
        </button>

        <div class="relative">
            <button type="button" id="emojiBtn" class="w-9 h-9 rounded-lg bg-gray-100 hover:bg-gray-200 flex items-center justify-center text-gray-400 hover:text-gray-600 transition-colors flex-shrink-0" title="Emoji">
                <i class="fas fa-smile text-sm"></i>
            </button>
            <div id="emojiPicker" class="hidden absolute bottom-12 left-0 bg-white border border-gray-200 rounded-2xl shadow-xl p-3 z-50 w-72">
                <div class="emoji-grid" id="emojiGrid"></div>
            </div>
        </div>

        <div class="flex-1">
            <textarea id="chatInput" rows="1" placeholder="Type your message..."
                class="w-full bg-gray-50 border border-gray-200 rounded-xl px-4 py-2.5 text-sm text-gray-900 placeholder-gray-400 resize-none focus:outline-none focus:border-blue-500 focus:ring-2 focus:ring-blue-500/10 transition-all"></textarea>
        </div>

        <button id="chatSendBtn" type="button" class="w-10 h-10 rounded-xl btn-grad flex items-center justify-center text-white shadow-sm shadow-blue-500/25 flex-shrink-0">
            <i class="fas fa-paper-plane text-sm"></i>
        </button>
    </div>
</div>

<!-- Placeholder (no room selected) -->
<div id="chatPlaceholder" class="flex-1 flex items-center justify-center">
    <div class="text-center">
        <div class="w-20 h-20 rounded-3xl bg-gradient-to-br from-blue-50 to-cyan-50 flex items-center justify-center mx-auto mb-5 border border-blue-100">
            <i class="fas fa-comments text-3xl text-blue-300"></i>
        </div>
        <h3 class="text-lg font-bold text-gray-900 mb-2">Select a Conversation</h3>
        <p class="text-sm text-gray-400 max-w-sm mx-auto">Choose a conversation from the left to start messaging.</p>
    </div>
</div>
