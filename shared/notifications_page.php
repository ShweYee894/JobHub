<?php
/**
 * notifications_page.php
 * Full-page view of all notifications for the current user.
 */

session_start();
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/notifications.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: /finalproject/auth/login.php');
    exit;
}

$userId = (int) $_SESSION['user_id'];
$role   = $_SESSION['user_role'] ?? 'freelancer';
$ns     = new PlatformNotificationService($conn);

// Handle mark-read actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    if ($action === 'mark_read' && isset($_POST['id'])) {
        $ns->markRead((int) $_POST['id'], $userId);
    } elseif ($action === 'mark_all_read') {
        $ns->markAllRead($userId);
    }
    header('Location: notifications_page.php');
    exit;
}

// Pagination
$page    = max(1, (int) ($_GET['page'] ?? 1));
$perPage = 20;
$offset  = ($page - 1) * $perPage;
$notifications = $ns->getForUser($userId, $perPage, $offset);
$unreadCount  = $ns->getUnreadCount($userId);

// Get user info for layout
$userStmt = $conn->prepare('SELECT name, profile_image FROM users WHERE id = ?');
$userStmt->bind_param('i', $userId);
$userStmt->execute();
$user = $userStmt->get_result()->fetch_assoc();
$userStmt->close();

$pageTitle = 'Notifications';
$activePage = 'notifications';

// Determine profile link based on role
$profileLink = $role === 'client' ? '/finalproject/client/profile.php' : '/finalproject/freelancer/profile.php';
?>
<!DOCTYPE html>
<html lang="en" class="scroll-smooth">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width,initial-scale=1.0">
    <title><?= htmlspecialchars($pageTitle) ?> – JobHub</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800;900&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <link rel="stylesheet" href="/finalproject/shared/dark-mode.css">
    <script>
        (function() {
            var dark = localStorage.getItem('fh-dark-mode');
            if (dark === '1' || dark === 'dark' || (!dark && window.matchMedia('(prefers-color-scheme: dark)').matches)) {
                document.documentElement.classList.add('dark');
            }
        })();
    </script>
    <script>
        tailwind.config = {
            darkMode: 'class',
            theme: {
                extend: {
                    fontFamily: { inter: ['Inter', 'sans-serif'] },
                    colors: {
                        primary: { DEFAULT: '#2563eb', dark: '#1d4ed8', light: '#3b82f6' },
                        accent: { DEFAULT: '#14b8a6', dark: '#0d9488' },
                        surface: { DEFAULT: '#f8fafc', card: '#ffffff', border: '#e5edf6' }
                    }
                }
            }
        }
    </script>
    <style>
        *, *::before, *::after { box-sizing: border-box; }
        body { font-family: 'Inter', sans-serif; background: #F4F7FC; color: #1e293b; margin: 0; }
        html.dark body { background: #0f172a; color: #e2e8f0; }
        ::-webkit-scrollbar { width: 6px; height: 6px; }
        ::-webkit-scrollbar-track { background: transparent; }
        ::-webkit-scrollbar-thumb { background: #cbd5e1; border-radius: 999px; }
        html.dark ::-webkit-scrollbar-thumb { background: #475569; }
    </style>
</head>
<body class="min-h-screen">

    <!-- Top Navbar -->
    <nav class="fixed top-0 inset-x-0 z-50 py-3 bg-white/80 backdrop-blur-md border-b border-gray-100 dark:bg-slate-900/80 dark:border-slate-700">
        <div class="w-full mx-auto px-4 sm:px-6 flex items-center justify-between">
            <div class="flex items-center gap-6">
                <a href="/finalproject/index.php" class="flex items-center gap-1.5 group shrink-0">
                    <img src="/finalproject/assets/upload/logos/logo.png" alt="Logo" class="w-[36px] h-[36px] rounded-xl">
                    <span class="text-lg font-extrabold tracking-tight">
                        <span class="text-gray-900 dark:text-white">Job</span><span class="bg-gradient-to-r from-blue-600 to-teal-500 bg-clip-text text-transparent">Hub</span>
                    </span>
                </a>
                <a href="<?= $role === 'client' ? '/finalproject/client/dashboard.php' : '/finalproject/freelancer/dashboard.php' ?>" class="text-sm font-semibold text-gray-600 dark:text-slate-400 hover:text-gray-900 dark:hover:text-white transition">
                    <i class="fas fa-arrow-left mr-1"></i> Back to Dashboard
                </a>
            </div>
            <div class="flex items-center gap-3">
                <span class="text-sm font-semibold text-gray-700 dark:text-slate-300"><?= htmlspecialchars($user['name'] ?? 'User') ?></span>
                <img src="<?= htmlspecialchars(get_profile_image($user['profile_image'] ?? null)) ?>" class="w-9 h-9 rounded-xl object-cover border-2 border-gray-100 dark:border-slate-600" alt="Avatar">
            </div>
        </div>
    </nav>

    <div class="pt-20 pb-10 px-4 sm:px-6 max-w-3xl mx-auto">

        <!-- Header -->
        <div class="flex items-center justify-between mb-6">
            <div>
                <h1 class="text-2xl font-bold text-gray-900 dark:text-white">Notifications</h1>
                <p class="text-sm text-gray-500 dark:text-slate-400 mt-1">
                    <?= $unreadCount > 0 ? "You have {$unreadCount} unread notification" . ($unreadCount !== 1 ? 's' : '') : 'All caught up!' ?>
                </p>
            </div>
            <?php if ($unreadCount > 0): ?>
                <form method="POST" class="inline">
                    <input type="hidden" name="action" value="mark_all_read">
                    <button type="submit" class="text-sm font-semibold text-blue-600 hover:text-blue-800 dark:text-blue-400 dark:hover:text-blue-300 transition">
                        <i class="fas fa-check-double mr-1"></i> Mark all read
                    </button>
                </form>
            <?php endif; ?>
        </div>

        <!-- Notification List -->
        <?php if (empty($notifications)): ?>
            <div class="bg-white dark:bg-slate-800 rounded-2xl border border-gray-100 dark:border-slate-700 p-12 text-center">
                <i class="fas fa-bell-slash text-4xl text-gray-300 dark:text-slate-600 mb-4 block"></i>
                <p class="text-gray-500 dark:text-slate-400 font-medium">No notifications yet</p>
                <p class="text-sm text-gray-400 dark:text-slate-500 mt-1">You'll see notifications here when there's activity on your account.</p>
            </div>
        <?php else: ?>
            <div class="bg-white dark:bg-slate-800 rounded-2xl border border-gray-100 dark:border-slate-700 overflow-hidden divide-y divide-gray-50 dark:divide-slate-700">
                <?php foreach ($notifications as $n): ?>
                    <?php
                    $isUnread = empty($n['is_read']);
                    $iconMap = [
                        'new_proposal'       => ['icon' => 'fa-file-alt',       'bg' => 'bg-blue-100',    'color' => 'text-blue-600'],
                        'proposal_accepted'  => ['icon' => 'fa-check-circle',   'bg' => 'bg-green-100',   'color' => 'text-green-600'],
                        'new_contract'       => ['icon' => 'fa-handshake',      'bg' => 'bg-purple-100',  'color' => 'text-purple-600'],
                        'milestone_submitted'=> ['icon' => 'fa-flag-checkered', 'bg' => 'bg-yellow-100',  'color' => 'text-yellow-600'],
                        'payment_released'   => ['icon' => 'fa-dollar-sign',    'bg' => 'bg-emerald-100', 'color' => 'text-emerald-600'],
                        'new_message'        => ['icon' => 'fa-comment-dots',   'bg' => 'bg-cyan-100',    'color' => 'text-cyan-600'],
                        'review_received'    => ['icon' => 'fa-star',           'bg' => 'bg-orange-100',  'color' => 'text-orange-600'],
                        'job_invitation'     => ['icon' => 'fa-paper-plane',    'bg' => 'bg-indigo-100',  'color' => 'text-indigo-600'],
                        'invitation_accepted'=> ['icon' => 'fa-thumbs-up',      'bg' => 'bg-green-100',   'color' => 'text-green-600'],
                        'invitation_declined'=> ['icon' => 'fa-thumbs-down',    'bg' => 'bg-red-100',     'color' => 'text-red-600'],
                    ];
                    $iconData = $iconMap[$n['type']] ?? ['icon' => 'fa-bell', 'bg' => 'bg-gray-100', 'color' => 'text-gray-600'];
                    $link = $n['link'] ?? '#';
                    ?>
                    <a href="<?= htmlspecialchars($link) ?>" class="flex items-start gap-4 px-5 py-4 hover:bg-gray-50 dark:hover:bg-slate-700/50 transition <?= $isUnread ? 'bg-blue-50/40 dark:bg-blue-900/10' : '' ?>">
                        <div class="flex-shrink-0 mt-0.5 w-10 h-10 rounded-full flex items-center justify-center <?= $iconData['bg'] ?>">
                            <i class="fas <?= $iconData['icon'] ?> text-sm <?= $iconData['color'] ?>"></i>
                        </div>
                        <div class="flex-1 min-w-0">
                            <p class="text-sm font-semibold text-gray-800 dark:text-slate-200 leading-snug"><?= htmlspecialchars($n['title']) ?></p>
                            <p class="text-xs text-gray-500 dark:text-slate-400 mt-1 leading-relaxed"><?= htmlspecialchars($n['message']) ?></p>
                            <p class="text-[11px] text-gray-400 dark:text-slate-500 mt-1.5">
                                <i class="far fa-clock mr-1"></i><?= htmlspecialchars($n['created_at']) ?>
                            </p>
                        </div>
                        <?php if ($isUnread): ?>
                            <span class="mt-2 w-2.5 h-2.5 rounded-full bg-blue-500 flex-shrink-0"></span>
                        <?php endif; ?>
                    </a>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>

    <script>
        // Auto-mark as read when clicking a notification
        document.querySelectorAll('a[href]').forEach(function(el) {
            el.addEventListener('click', function(e) {
                var notifId = this.querySelector('[data-id]');
            });
        });
    </script>
</body>
</html>
