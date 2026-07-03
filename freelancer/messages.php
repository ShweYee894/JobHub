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
$pageTitle = 'Messages';
$csrfToken = generate_csrf_token();
$activePage = 'messages';

$navItems = [
    ['key' => 'dashboard', 'label' => 'Dashboard', 'url' => 'dashboard.php', 'icon' => 'fa-th-large'],
    ['key' => 'profile', 'label' => 'Profile', 'url' => 'profile.php', 'icon' => 'fa-user'],
    ['key' => 'browse_jobs', 'label' => 'Browse Jobs', 'url' => 'browse_jobs.php', 'icon' => 'fa-search'],
    ['key' => 'proposals', 'label' => 'Proposals', 'url' => 'proposals.php', 'icon' => 'fa-file-alt'],
    ['key' => 'contracts', 'label' => 'Contracts', 'url' => 'contracts.php', 'icon' => 'fa-handshake'],
    ['key' => 'messages', 'label' => 'Messages', 'url' => 'messages.php', 'icon' => 'fa-comment-dots'],
    ['key' => 'earnings', 'label' => 'Earnings', 'url' => 'earnings.php', 'icon' => 'fa-wallet'],
];
$pageTitle = 'Messages';
$pageSubtitle = 'Communicate with your clients';
$user = ['name' => $userName ?? 'Freelancer', 'profile_image' => null];
$unreadCount = $unreadMessages ?? 0;
$profileLink = 'profile.php';
require_once __DIR__ . '/../components/layout_start.php';
?>
            <div class="bg-white rounded-2xl border border-gray-100 shadow-sm overflow-hidden fade-in" style="height: calc(100vh - 180px);">
                <div class="flex h-full">

                    <!-- ═══ ROOMS LIST ════════════════════════════════ -->
                    <div id="roomsPanel" class="w-full sm:w-80 border-r border-gray-100 flex flex-col">
                        <div class="p-4 border-b border-gray-100">
                            <h2 class="text-sm font-bold text-gray-900 mb-3">Conversations</h2>
                            <div class="relative">
                                <i class="fas fa-search absolute left-3 top-1/2 -translate-y-1/2 text-gray-400 text-xs"></i>
                                <input type="text" id="conversationSearch" placeholder="Search by name, job..."
                                    class="w-full bg-gray-50 border border-gray-200 rounded-xl pl-9 pr-4 py-2 text-sm text-gray-900 placeholder-gray-400 focus:outline-none focus:border-blue-500 focus:ring-2 focus:ring-blue-500/10 transition-all">
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

<script>
const emojis = ['😀','😁','😂','🤣','😃','😄','😅','😆','😉','😊','😋','😎','😍','🥰','😘','😗','😙','😚','🙂','🤗','🤩','🤔','🤨','😐','😑','😶','🙄','😏','😣','😥','😮','🤐','😯','😪','😫','🥱','😴','😌','😛','😜','🤪','😝','🤑','🤓','😎','🥳','🥺','🤩','💕','❤️','🧡','💛','💚','💙','💜','🖤','🤍','💯','💢','💥','💫','💦','👍','👎','👊','✊','🤛','🤜','👏','🙌','👐','🤝','🙏','✌️','🤞','🤟','🤘','👌','🔥','⭐','🌟','✨','💪','🎉','🎊','✅','❌','⏰','📎','📝','💼','📁','🗂️','📊','📈','🗓️','✏️','🖊️','📌','🔗','💰','🎁','🏆','🎯'];
const emojiGrid = document.getElementById('emojiGrid');
if (emojiGrid) {
    emojis.forEach(emoji => {
        const btn = document.createElement('span');
        btn.className = 'emoji-btn';
        btn.textContent = emoji;
        btn.addEventListener('click', () => chat._insertEmoji(emoji));
        emojiGrid.appendChild(btn);
    });
}
</script>
<script src="/finalproject/assets/js/chat.js"></script>
<script>
document.addEventListener('DOMContentLoaded', () => {
    window.chat = new Chat({
        userId: <?= $userId ?>,
        role: 'freelancer',
        baseUrl: '/finalproject',
        csrfToken: '<?= $csrfToken ?>'
    });
});
</script>
<?php require_once __DIR__ . '/../components/layout_end.php'; ?>
