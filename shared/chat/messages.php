<?php
/**
 * messages.php
 *
 * Modern two-column chat layout.
 * Left: conversation list (always visible).
 * Right: chat header + scrollable messages + fixed composer.
 * All interaction via AJAX — no page reloads.
 */

require_once __DIR__ . '/../../auth/auth.php';
require_role(['client', 'freelancer']);
require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../config/helpers.php';

$userId    = (int) $_SESSION['user_id'];
$role      = $_SESSION['user_role'];
$csrfToken = generate_csrf_token();

// Fetch current user info
$stmt = $conn->prepare('SELECT name, profile_image FROM users WHERE id = ?');
$stmt->bind_param('i', $userId);
$stmt->execute();
$currentUser = $stmt->get_result()->fetch_assoc();
$stmt->close();

$pageTitle  = 'Messages';
$activePage = 'messages';
$user       = ['name' => $currentUser['name'] ?? 'User', 'profile_image' => $currentUser['profile_image'] ?? null];

// Load the appropriate topbar
if ($role === 'client') {
    require_once __DIR__ . '/../../includes/client_topbar.php';
} else {
    require_once __DIR__ . '/../../components/freelancer_header.php';
}
?>

<!-- ═══ Chat Container ═══════════════════════════════════════════════════════ -->
<main class="max-w-7xl mx-auto px-4 sm:px-6 py-6">
    <div class="bg-white dark:bg-slate-800 rounded-2xl border border-gray-100 dark:border-slate-700 shadow-sm overflow-hidden h-[calc(100vh-120px)]">
        <div class="flex h-full">

            <!-- ═══ Left Panel: Conversations ════════════════════════════════ -->
            <div id="roomsPanel" class="w-full sm:w-80 lg:w-96 border-r border-gray-100 dark:border-slate-700 flex flex-col flex-shrink-0">

                <!-- Panel Header -->
                <div class="px-5 py-4 border-b border-gray-100 dark:border-slate-700">
                    <div class="flex items-center justify-between mb-3">
                        <h2 class="text-base font-bold text-gray-900 dark:text-white">Messages</h2>
                        <span id="totalUnread" class="hidden px-2 py-0.5 bg-red-500 text-white text-[10px] font-bold rounded-full"></span>
                    </div>
                    <div class="relative">
                        <i class="fas fa-search absolute left-3 top-1/2 -translate-y-1/2 text-gray-400 text-xs"></i>
                        <input
                            type="text"
                            id="conversationSearch"
                            placeholder="Search conversations..."
                            class="w-full bg-gray-50 dark:bg-slate-700 border border-gray-200 dark:border-slate-600 rounded-xl pl-9 pr-4 py-2.5 text-sm text-gray-900 dark:text-gray-100 placeholder-gray-400 focus:outline-none focus:border-blue-500 focus:ring-2 focus:ring-blue-500/10 transition-all"
                        >
                    </div>
                </div>

                <!-- Conversation List -->
                <div id="roomList" class="flex-1 overflow-y-auto">
                    <!-- Loading skeleton -->
                    <div id="roomListLoading" class="p-4 space-y-3">
                        <div class="flex items-center gap-3 animate-pulse">
                            <div class="w-11 h-11 rounded-full bg-gray-200 dark:bg-slate-600"></div>
                            <div class="flex-1 space-y-2">
                                <div class="h-3 bg-gray-200 dark:bg-slate-600 rounded w-3/4"></div>
                                <div class="h-2.5 bg-gray-200 dark:bg-slate-600 rounded w-1/2"></div>
                            </div>
                        </div>
                        <div class="flex items-center gap-3 animate-pulse">
                            <div class="w-11 h-11 rounded-full bg-gray-200 dark:bg-slate-600"></div>
                            <div class="flex-1 space-y-2">
                                <div class="h-3 bg-gray-200 dark:bg-slate-600 rounded w-2/3"></div>
                                <div class="h-2.5 bg-gray-200 dark:bg-slate-600 rounded w-2/5"></div>
                            </div>
                        </div>
                        <div class="flex items-center gap-3 animate-pulse">
                            <div class="w-11 h-11 rounded-full bg-gray-200 dark:bg-slate-600"></div>
                            <div class="flex-1 space-y-2">
                                <div class="h-3 bg-gray-200 dark:bg-slate-600 rounded w-4/5"></div>
                                <div class="h-2.5 bg-gray-200 dark:bg-slate-600 rounded w-1/3"></div>
                            </div>
                        </div>
                    </div>

                    <!-- Empty state -->
                    <div id="roomListEmpty" class="hidden py-16 px-6 text-center">
                        <div class="w-14 h-14 rounded-2xl bg-gray-100 dark:bg-slate-700 flex items-center justify-center mx-auto mb-3">
                            <i class="fas fa-comment-dots text-xl text-gray-300 dark:text-gray-500"></i>
                        </div>
                        <p class="text-gray-500 dark:text-gray-400 text-sm font-medium">No conversations yet</p>
                        <p class="text-gray-400 dark:text-gray-500 text-xs mt-1">Messages from your projects will appear here</p>
                    </div>
                </div>
            </div>

            <!-- ═══ Right Panel: Chat Area ═══════════════════════════════════ -->
            <div id="chatArea" class="flex-1 flex flex-col hidden sm:flex min-w-0">

                <!-- Chat Header -->
                <div id="chatHeader" class="px-5 py-3 border-b border-gray-100 dark:border-slate-700 items-center gap-3 hidden bg-white dark:bg-slate-800 flex-shrink-0">
                    <button id="backToList" class="sm:hidden text-gray-400 hover:text-gray-600 dark:hover:text-gray-300 mr-1 transition-colors">
                        <i class="fas fa-arrow-left"></i>
                    </button>
                    <img
                        id="chatHeaderAvatar"
                        src=""
                        class="w-10 h-10 rounded-full object-cover border-2 border-gray-100 dark:border-slate-600 flex-shrink-0"
                        alt=""
                    >
                    <div class="flex-1 min-w-0">
                        <h3 id="chatHeaderName" class="text-sm font-bold text-gray-900 dark:text-white truncate"></h3>
                        <p id="chatHeaderStatus" class="text-[11px] text-gray-400 dark:text-gray-500 font-medium"></p>
                    </div>
                </div>

                <!-- Messages Container -->
                <div id="chatMessages" class="flex-1 overflow-y-auto p-5 space-y-3 hidden" style="min-height:0;">
                </div>

                <!-- Empty Chat State -->
                <div id="emptyChatMsg" class="flex-1 items-center justify-center hidden" style="min-height:0;">
                    <div class="text-center">
                        <div class="w-14 h-14 rounded-2xl bg-gray-100 dark:bg-slate-700 flex items-center justify-center mx-auto mb-3">
                            <i class="fas fa-paper-plane text-xl text-gray-300 dark:text-gray-500"></i>
                        </div>
                        <p class="text-gray-500 dark:text-gray-400 text-sm">No messages yet</p>
                        <p class="text-gray-400 dark:text-gray-500 text-xs mt-1">Start the conversation by sending a message</p>
                    </div>
                </div>

                <!-- Placeholder (no room selected) -->
                <div id="chatPlaceholder" class="flex-1 flex items-center justify-center bg-white dark:bg-slate-800" style="min-height:0;">
                    <div class="text-center px-6">
                        <div class="w-20 h-20 rounded-3xl bg-gradient-to-br from-blue-50 to-cyan-50 dark:from-slate-700 dark:to-slate-600 flex items-center justify-center mx-auto mb-5 border border-blue-100 dark:border-slate-600">
                            <i class="fas fa-comments text-3xl text-blue-300 dark:text-blue-400"></i>
                        </div>
                        <h3 class="text-lg font-bold text-gray-900 dark:text-white mb-2">Select a Conversation</h3>
                        <p class="text-sm text-gray-400 dark:text-gray-500 max-w-xs mx-auto">Choose a conversation from the list to start messaging.</p>
                    </div>
                </div>

                <!-- ═══ Composer ═══════════════════════════════════════════════ -->
                <div id="chatInputArea" class="border-t border-gray-100 dark:border-slate-700 hidden bg-white dark:bg-slate-800 flex-shrink-0">
                    <!-- Typing indicator bar -->
                    <div id="typingBar" class="px-5 pt-2 hidden">
                        <p class="text-[11px] text-gray-400 dark:text-gray-500 font-medium flex items-center gap-1.5">
                            <span class="flex gap-0.5">
                                <span class="w-1.5 h-1.5 bg-gray-400 rounded-full animate-bounce" style="animation-delay:0ms"></span>
                                <span class="w-1.5 h-1.5 bg-gray-400 rounded-full animate-bounce" style="animation-delay:150ms"></span>
                                <span class="w-1.5 h-1.5 bg-gray-400 rounded-full animate-bounce" style="animation-delay:300ms"></span>
                            </span>
                            <span id="typingText">Someone is typing...</span>
                        </p>
                    </div>

                    <div class="px-4 py-3 flex items-end gap-2">
                        <!-- Attachment button -->
                        <button id="attachBtn" class="flex-shrink-0 w-9 h-9 rounded-full flex items-center justify-center text-gray-400 hover:text-blue-500 hover:bg-blue-50 dark:hover:bg-slate-700 transition-colors" title="Attach file">
                            <i class="fas fa-paperclip text-lg"></i>
                        </button>
                        <input type="file" id="fileInput" class="hidden" accept="image/*,.pdf,.doc,.docx,.zip,.rar,.txt">

                        <!-- Emoji button -->
                        <div class="relative flex-shrink-0">
                            <button id="emojiBtn" class="w-9 h-9 rounded-full flex items-center justify-center text-gray-400 hover:text-yellow-500 hover:bg-yellow-50 dark:hover:bg-slate-700 transition-colors" title="Emoji">
                                <i class="fas fa-smile text-lg"></i>
                            </button>
                            <!-- Emoji picker dropdown -->
                            <div id="emojiPicker" class="hidden absolute bottom-12 left-0 z-50 bg-white dark:bg-slate-800 border border-gray-200 dark:border-slate-600 rounded-xl shadow-xl p-3 w-72">
                                <div class="grid grid-cols-8 gap-1 max-h-48 overflow-y-auto" id="emojiGrid">
                                </div>
                            </div>
                        </div>

                        <!-- Text input -->
                        <div class="flex-1 min-w-0">
                            <textarea
                                id="chatInput"
                                rows="1"
                                placeholder="Type a message..."
                                class="w-full resize-none bg-gray-50 dark:bg-slate-700 border border-gray-200 dark:border-slate-600 rounded-xl px-4 py-2.5 text-sm text-gray-900 dark:text-gray-100 placeholder-gray-400 focus:outline-none focus:border-blue-500 focus:ring-2 focus:ring-blue-500/10 transition-all"
                                style="max-height: 120px;"
                            ></textarea>
                        </div>

                        <!-- Send button -->
                        <button id="chatSendBtn" class="flex-shrink-0 w-9 h-9 rounded-full bg-blue-500 hover:bg-blue-600 text-white flex items-center justify-center transition-colors disabled:opacity-50 disabled:cursor-not-allowed shadow-sm" title="Send message">
                            <i class="fas fa-paper-plane text-sm"></i>
                        </button>
                    </div>

                    <!-- File preview (hidden by default) -->
                    <div id="filePreview" class="hidden px-4 pb-3">
                        <div class="flex items-center gap-2 bg-gray-50 dark:bg-slate-700 rounded-lg px-3 py-2 text-sm">
                            <i class="fas fa-file text-gray-400"></i>
                            <span id="filePreviewName" class="flex-1 truncate text-gray-700 dark:text-gray-300"></span>
                            <span id="filePreviewSize" class="text-gray-400 text-xs"></span>
                            <button id="fileRemoveBtn" class="text-gray-400 hover:text-red-500 transition-colors">
                                <i class="fas fa-times"></i>
                            </button>
                        </div>
                    </div>
                </div>

            </div>

        </div>
    </div>
</main>

<!-- ═══ JavaScript ══════════════════════════════════════════════════════════ -->
<script src="/finalproject/shared/dark-toggle.js"></script>
<script src="/finalproject/assets/js/chat.js"></script>
<script>
document.addEventListener('DOMContentLoaded', () => {
    const chat = new Chat({
        userId:    <?= json_encode($userId) ?>,
        role:      <?= json_encode($role) ?>,
        csrfToken: <?= json_encode($csrfToken) ?>,
        userName:  <?= json_encode($currentUser['name'] ?? 'User') ?>,
        baseUrl:   '/finalproject'
    });
});
</script>

<?php
if ($role === 'client') {
    require_once __DIR__ . '/../../includes/client_footer.php';
} else {
    require_once __DIR__ . '/../../components/freelancer_footer.php';
}
?>
