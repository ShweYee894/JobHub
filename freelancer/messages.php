<?php

/**
 * Freelancer Messages – Real-time chat with clients.
 *
 * Features:
 * - Conversation sidebar with search, unread badges, online indicators
 * - WhatsApp-style chat bubbles with read receipts
 * - File attachments (PDF, DOCX, ZIP, PNG, JPG)
 * - Emoji picker
 * - Typing indicators via SSE
 * - Browser notifications for new messages
 * - Dark mode support
 * - Responsive mobile layout (back button to conversation list)
 * - Auto-scroll with position preservation
 * - Auto-opens latest conversation on page load
 */
require_once __DIR__ . '/../auth/auth.php';
require_role('freelancer');
require_once __DIR__ . '/../config/db.php';

$userId = $_SESSION['user_id'];
$userName = $_SESSION['user_name'] ?? 'Freelancer';
$userProfile = $_SESSION['profile_image'];
$pageTitle = 'Messages';
$csrfToken = generate_csrf_token();
$activePage = 'messages';

$pageTitle = 'Messages';
$pageSubtitle = 'Communicate with your clients';
$user = ['name' => $userName ?? 'Freelancer', 'profile_image' => $userProfile ?? null];
$unreadCount = get_unread_message_count($userId, 'freelancer');
require_once __DIR__ . '/../components/freelancer_header.php';
?>
         <div class="max-w-7xl mx-auto px-4 sm:px-6 pb-8 pt-14">
            <div class="bg-white dark:bg-slate-800 rounded-2xl border border-gray-100 dark:border-slate-700 shadow-sm overflow-hidden fade-in " style="height: calc(100vh - 180px);">
                <div class="flex h-full">

                    <!-- ═══ ROOMS LIST ════════════════════════════════ -->
                    <div id="roomsPanel" class="w-full sm:w-80 border-r border-gray-100 dark:border-slate-700 flex flex-col">
                        <div class="p-4 border-b border-gray-100 dark:border-slate-700">
                            <h2 class="text-sm font-bold text-gray-900 dark:text-white mb-3">Conversations</h2>
                            <div class="relative">
                                <i class="fas fa-search absolute left-3 top-1/2 -translate-y-1/2 text-gray-400 text-xs"></i>
                                <input type="text" id="conversationSearch" placeholder="Search by name, job..."
                                    class="w-full bg-gray-50 dark:bg-slate-700 border border-gray-200 dark:border-slate-600 rounded-xl pl-9 pr-4 py-2 text-sm text-gray-900 dark:text-gray-100 placeholder-gray-400 focus:outline-none focus:border-blue-500 focus:ring-2 focus:ring-blue-500/10 transition-all">
                            </div>
                        </div>
                        <div id="roomList" class="flex-1 overflow-y-auto">
                        </div>
                    </div>

                    <!-- ═══ CHAT AREA ═════════════════════════════════ -->
                    <div id="chatArea" class="flex-1 flex flex-col hidden sm:flex">

                        <?php include __DIR__ . '/../includes/chat-header.php'; ?>
                        <?php include __DIR__ . '/../includes/chat-body.php'; ?>

                    </div>

                </div>
            </div>
         </div>
<script src="/finalproject/shared/dark-toggle.js"></script>
<script>
const emojis = ['😀','😁','😂','🤣','😃','😄','😅','😆','😉','😊','😋','😎','😍','🥰','😘','😗','😙','😚','🙂','🤗','🤩','🤔','🤨','😐','😑','😶','🙄','😏','😣','😥','😮','🤐','😯','😪','😫','🥱','😴','😌','😛','😜','🤪','😝','🤑','🤓','😎','🥳','🥺','🤩','💕','❤️','🧡','💛','💚','💙','💜','🖤','🤍','💯','💢','💥','💫','💦','👍','👎','👊','✊','🤛','🤜','👏','🙌','👐','🤝','🙏','✌️','🤞','🤟','🤘','👌','🔥','⭐','🌟','✨','💪','🎉','🎊','✅','❌','⏰','📎','📝','💼','📁','🗂️','📊','📈','🗓️','✏️','🖊️','📌','🔗','💰','🎁','🏆','🎯'];
const emojiGrid = document.getElementById('emojiGrid');
if (emojiGrid) {
    // 1. Add Tailwind classes to the grid container for sizing and layout
    emojiGrid.className = "grid grid-cols-6 gap-3 p-4 bg-gray-50 rounded-xl max-h-64 overflow-y-auto shadow-inner";
    emojis.forEach(emoji => {
        const btn = document.createElement('span');
        btn.className = 'emoji-btn';
        btn.textContent = emoji;
        btn.addEventListener('click', () => chat._insertEmoji(emoji));
        emojiGrid.appendChild(btn);
    });
}
</script>
<script src="/finalproject/assets/js/chat.js?v=<?= filemtime(__DIR__ . '/../assets/js/chat.js') ?>"></script>
<script>
document.addEventListener('DOMContentLoaded', () => {
    window.chat = new Chat({
        userId: <?= $userId ?>,
        role: 'freelancer',
        baseUrl: '/finalproject',
        csrfToken: '<?= $csrfToken ?>'
    });

    setTimeout(() => {
        if (window.chat && !window.chat.selectedRoomId && window.chat.conversations.length > 0) {
            const latest = window.chat.conversations[0];
            if (latest && latest.room_id) {
                window.chat.openRoom(latest.room_id, latest);
            }
        }
    }, 300);
});
</script>
<?php require_once __DIR__ . '/../components/freelancer_footer.php'; ?>
