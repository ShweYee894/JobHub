<?php
/**
 * notifications_page.php (content only)
 * Reusable notifications content for freelancer & client dashboards.
 * This file is included by freelancer/notifications.php and client/notifications.php.
 *
 * Required variables before include:
 *   $userId (int), $conn (mysqli), $basePath (string)
 */

require_once __DIR__ . '/notifications.php';

$currentFilter = $_GET['filter'] ?? 'all';
$currentPage   = max(1, (int) ($_GET['page'] ?? 1));
$perPage       = 15;
$offset        = ($currentPage - 1) * $perPage;

$VALID_FILTERS = ['all', 'unread', 'messages', 'disputes'];
if (!in_array($currentFilter, $VALID_FILTERS, true)) {
    $currentFilter = 'all';
}

$ns            = new PlatformNotificationService($conn);
$notifications = $ns->getFiltered($userId, $currentFilter, $perPage, $offset);
$totalCount    = $ns->getFilteredCount($userId, $currentFilter);
$totalPages    = max(1, (int) ceil($totalCount / $perPage));
$unreadCount   = $ns->getUnreadCount($userId);

function notif_time_ago(string $datetime): string
{
    $now  = new DateTime();
    $past = new DateTime($datetime);
    $diff = $now->diff($past);
    if ($diff->y > 0) return $diff->y . 'y ago';
    if ($diff->m > 0) return $diff->m . 'mo ago';
    if ($diff->d > 0) { if ($diff->d >= 7) return floor($diff->d / 7) . 'w ago'; return $diff->d . 'd ago'; }
    if ($diff->h > 0) return $diff->h . 'h ago';
    if ($diff->i > 0) return $diff->i . 'm ago';
    return 'Just now';
}

function heroicon_s(string $name, string $class = ''): string
{
    $cls = htmlspecialchars($class);
    $svgs = [
        'check-circle' => '<svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="'.$cls.'"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75 11.25 15 15 9.75M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z" /></svg>',
        'bell-slash' => '<svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="'.$cls.'"><path stroke-linecap="round" stroke-linejoin="round" d="M18.364 18.364A9 9 0 0 0 5.636 5.636m12.728 12.728A9 9 0 0 1 5.636 5.636m12.728 12.728L5.636 5.636" /></svg>',
        'ellipsis-vertical' => '<svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="'.$cls.'"><path stroke-linecap="round" stroke-linejoin="round" d="M12 6.75a.75.75 0 1 1 0-1.5.75.75 0 0 1 0 1.5ZM12 12.75a.75.75 0 1 1 0-1.5.75.75 0 0 1 0 1.5ZM12 18.75a.75.75 0 1 1 0-1.5.75.75 0 0 1 0 1.5Z" /></svg>',
        'check' => '<svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="'.$cls.'"><path stroke-linecap="round" stroke-linejoin="round" d="m4.5 12.75 6 6 9-13.5" /></svg>',
        'eye-slash' => '<svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="'.$cls.'"><path stroke-linecap="round" stroke-linejoin="round" d="M3.98 8.223A10.477 10.477 0 0 0 1.934 12C3.226 16.338 7.244 19.5 12 19.5c.993 0 1.953-.138 2.863-.395M6.228 6.228A10.451 10.451 0 0 1 12 4.5c4.756 0 8.773 3.162 10.065 7.498a10.522 10.522 0 0 1-4.293 5.774M6.228 6.228 3 3m3.228 3.228 3.65 3.65m7.894 7.894L21 21m-3.228-3.228-3.65-3.65m0 0a3 3 0 1 0-4.243-4.243m4.242 4.242L9.88 9.88" /></svg>',
        'trash' => '<svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="'.$cls.'"><path stroke-linecap="round" stroke-linejoin="round" d="m14.74 9-.346 9m-4.788 0L9.26 9m9.968-3.21c.342.052.682.107 1.022.166m-1.022-.165L18.16 19.673a2.25 2.25 0 0 1-2.244 2.077H8.084a2.25 2.25 0 0 1-2.244-2.077L4.772 5.79m14.456 0a48.108 48.108 0 0 0-3.478-.397m-12 .562c.34-.059.68-.114 1.022-.165m0 0a48.11 48.11 0 0 1 3.478-.397m7.5 0v-.916c0-1.18-.91-2.164-2.09-2.201a51.964 51.964 0 0 0-3.32 0c-1.18.037-2.09 1.022-2.09 2.201v.916m7.5 0a48.667 48.667 0 0 0-7.5 0" /></svg>',
        'cog-6-tooth' => '<svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="'.$cls.'"><path stroke-linecap="round" stroke-linejoin="round" d="M9.594 3.94c.09-.542.56-.94 1.11-.94h2.593c.55 0 1.02.398 1.11.94l.213 1.281c.063.374.313.686.645.87.074.04.147.083.22.127.325.196.72.257 1.075.124l1.217-.456a1.125 1.125 0 0 1 1.37.49l1.296 2.247a1.125 1.125 0 0 1-.26 1.431l-1.003.827c-.293.241-.438.613-.43.992a7.723 7.723 0 0 1 0 .255c-.008.378.137.75.43.991l1.004.827c.424.35.534.955.26 1.43l-1.298 2.247a1.125 1.125 0 0 1-1.369.491l-1.217-.456c-.355-.133-.75-.072-1.076.124a6.47 6.47 0 0 1-.22.128c-.331.183-.581.495-.644.869l-.213 1.281c-.09.543-.56.94-1.11.94h-2.594c-.55 0-1.019-.398-1.11-.94l-.213-1.281c-.062-.374-.312-.686-.644-.87a6.52 6.52 0 0 1-.22-.127c-.325-.196-.72-.257-1.076-.124l-1.217.456a1.125 1.125 0 0 1-1.369-.49l-1.297-2.247a1.125 1.125 0 0 1 .26-1.431l1.004-.827c.292-.24.437-.613.43-.991a6.932 6.932 0 0 1 0-.255c.007-.38-.138-.751-.43-.992l-1.004-.827a1.125 1.125 0 0 1-.26-1.43l1.297-2.247a1.125 1.125 0 0 1 1.37-.491l1.216.456c.356.133.751.072 1.076-.124.072-.044.146-.086.22-.128.332-.183.582-.495.644-.869l.214-1.28Z" /><path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 1 1-6 0 3 3 0 0 1 6 0Z" /></svg>',
        'information-circle' => '<svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="'.$cls.'"><path stroke-linecap="round" stroke-linejoin="round" d="m11.25 11.25.041-.02a.75.75 0 0 1 1.063.852l-.708 2.836a.75.75 0 0 0 1.063.853l.041-.021M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Zm-9-3.75h.008v.008H12V8.25Z" /></svg>',
        'exclamation-triangle' => '<svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="'.$cls.'"><path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m-9.303 3.376c-.866 1.5.217 3.374 1.948 3.374h14.71c1.73 0 2.813-1.874 1.948-3.374L13.949 3.378c-.866-1.5-3.032-1.5-3.898 0L2.697 16.126ZM12 15.75h.007v.008H12v-.008Z" /></svg>',
        'x-circle' => '<svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="'.$cls.'"><path stroke-linecap="round" stroke-linejoin="round" d="m9.75 9.75 4.5 4.5m0-4.5-4.5 4.5M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z" /></svg>',
        'x-mark' => '<svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="'.$cls.'"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18 18 6M6 6l12 12" /></svg>',
        'chat-bubble-left-ellipsis' => '<svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="'.$cls.'"><path stroke-linecap="round" stroke-linejoin="round" d="M20.25 8.511c.884.284 1.5 1.128 1.5 2.097v4.286c0 1.136-.847 2.1-1.98 2.193-.34.027-.68.052-1.02.072v3.091l-3-3c-1.354 0-2.694-.055-4.02-.163a2.115 2.115 0 0 1-.825-.242m9.345-8.334a2.126 2.126 0 0 0-.476-.095 48.64 48.64 0 0 0-8.048 0c-1.131.094-1.976 1.057-1.976 2.192v4.286c0 .837.46 1.58 1.155 1.951m9.345-8.334V6.637c0-1.621-1.152-3.026-2.76-3.235A48.455 48.455 0 0 0 11.25 3c-2.115 0-4.198.137-6.24.402-1.608.209-2.76 1.614-2.76 3.235v6.226c0 1.621 1.152 3.026 2.76 3.235.577.075 1.157.14 1.74.194V21l4.155-4.155" /></svg>',
        'chat-bubble-left' => '<svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="'.$cls.'"><path stroke-linecap="round" stroke-linejoin="round" d="M20.25 8.511c.884.284 1.5 1.128 1.5 2.097v4.286c0 1.136-.847 2.1-1.98 2.193-.34.027-.68.052-1.02.072v3.091l-3-3c-1.354 0-2.694-.055-4.02-.163a2.115 2.115 0 0 1-.825-.242m9.345-8.334a2.126 2.126 0 0 0-.476-.095 48.64 48.64 0 0 0-8.048 0c-1.131.094-1.976 1.057-1.976 2.192v4.286c0 .837.46 1.58 1.155 1.951m9.345-8.334V6.637c0-1.621-1.152-3.026-2.76-3.235A48.455 48.455 0 0 0 11.25 3c-2.115 0-4.198.137-6.24.402-1.608.209-2.76 1.614-2.76 3.235v6.226c0 1.621 1.152 3.026 2.76 3.235.577.075 1.157.14 1.74.194V21l4.155-4.155" /></svg>',
        'chat-bubble-left-right' => '<svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="'.$cls.'"><path stroke-linecap="round" stroke-linejoin="round" d="M20.25 8.511c.884.284 1.5 1.128 1.5 2.097v4.286c0 1.136-.847 2.1-1.98 2.193-.34.027-.68.052-1.02.072v3.091l-3-3c-1.354 0-2.694-.055-4.02-.163a2.115 2.115 0 0 1-.825-.242m9.345-8.334a2.126 2.126 0 0 0-.476-.095 48.64 48.64 0 0 0-8.048 0c-1.131.094-1.976 1.057-1.976 2.192v4.286c0 .837.46 1.58 1.155 1.951m9.345-8.334V6.637c0-1.621-1.152-3.026-2.76-3.235A48.455 48.455 0 0 0 11.25 3c-2.115 0-4.198.137-6.24.402-1.608.209-2.76 1.614-2.76 3.235v6.226c0 1.621 1.152 3.026 2.76 3.235.577.075 1.157.14 1.74.194V21l4.155-4.155" /><path stroke-linecap="round" stroke-linejoin="round" d="M3.75 8.25h.008v.008H3.75V8.25Zm.375 0a.375.375 0 1 1-.75 0 .375.375 0 0 1 .75 0ZM3.75 15h.008v.008H3.75V15Zm.375 0a.375.375 0 1 1-.75 0 .375.375 0 0 1 .75 0Zm.375-3.75h.008v.008H4.5v-.008Zm.375 0a.375.375 0 1 1-.75 0 .375.375 0 0 1 .75 0Z" /></svg>',
        'folder' => '<svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="'.$cls.'"><path stroke-linecap="round" stroke-linejoin="round" d="M2.25 12.75V12A2.25 2.25 0 0 1 4.5 9.75h15A2.25 2.25 0 0 1 21.75 12v.75m-8.69-6.44-2.12-2.12a1.5 1.5 0 0 0-1.061-.44H4.5A2.25 2.25 0 0 0 2.25 6v12a2.25 2.25 0 0 0 2.25 2.25h15A2.25 2.25 0 0 0 21.75 18V9a2.25 2.25 0 0 0-2.25-2.25h-5.379a1.5 1.5 0 0 1-1.06-.44Z" /></svg>',
        'folder-checked' => '<svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="'.$cls.'"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75 11.25 15 15 9.75M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z" /><path stroke-linecap="round" stroke-linejoin="round" d="M2.25 12.75V12A2.25 2.25 0 0 1 4.5 9.75h15A2.25 2.25 0 0 1 21.75 12v.75m-8.69-6.44-2.12-2.12a1.5 1.5 0 0 0-1.061-.44H4.5A2.25 2.25 0 0 0 2.25 6v12a2.25 2.25 0 0 0 2.25 2.25h15A2.25 2.25 0 0 0 21.75 18V9a2.25 2.25 0 0 0-2.25-2.25h-5.379a1.5 1.5 0 0 1-1.06-.44Z" /></svg>',
        'flag' => '<svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="'.$cls.'"><path stroke-linecap="round" stroke-linejoin="round" d="M3 3v1.5M3 21v-6m0 0 2.77-.693a9 9 0 0 1 6.208.682l.108.054a9 9 0 0 0 6.086.71l3.114-.732a48.524 48.524 0 0 1-.005-10.499l-3.11.732a9 9 0 0 1-6.085-.711l-.108-.054a9 9 0 0 0-6.208-.682L3 4.5M3 15V4.5" /></svg>',
        'currency-dollar' => '<svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="'.$cls.'"><path stroke-linecap="round" stroke-linejoin="round" d="M12 6v12m-3-2.818.879.659 1.171-1.018.121.96 1.577-1.018A2.25 2.25 0 0 1 12 16.5c1.144 0 2.176-.418 2.974-1.118l1.577 1.018.121-.96 1.171 1.018.879-.659M12 6a3 3 0 1 0 0 6 3 3 0 0 0 0-6Z" /></svg>',
        'banknotes' => '<svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="'.$cls.'"><path stroke-linecap="round" stroke-linejoin="round" d="M2.25 18.75a60.07 60.07 0 0 1 15.797 2.101c.727.198 1.453-.342 1.453-1.096V18.75M3.75 4.5v.75A.75.75 0 0 1 3 6h-.75m0 0v-.375c0-.621.504-1.125 1.125-1.125H20.25M2.25 6v9m18-10.5v.75c0 .414.336.75.75.75h.75m-1.5-1.5h.375c.621 0 1.125.504 1.125 1.125v9.75c0 .621-.504 1.125-1.125 1.125h-.375m1.5-1.5H21a.75.75 0 0 0-.75.75v.75m0 0H3.75m0 0h-.375a1.125 1.125 0 0 1-1.125-1.125V15m1.5 1.5v-.75A.75.75 0 0 0 3 15h-.75M15 10.5a3 3 0 1 1-6 0 3 3 0 0 1 6 0Zm3 0h.008v.008H18V10.5Zm-12 0h.008v.008H6V10.5Z" /></svg>',
        'lock-closed' => '<svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="'.$cls.'"><path stroke-linecap="round" stroke-linejoin="round" d="M16.5 10.5V6.75a4.5 4.5 0 1 0-9 0v3.75m-.75 11.25h10.5a2.25 2.25 0 0 0 2.25-2.25v-6.75a2.25 2.25 0 0 0-2.25-2.25H6.75a2.25 2.25 0 0 0-2.25 2.25v6.75a2.25 2.25 0 0 0 2.25 2.25Z" /></svg>',
        'lock-open' => '<svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="'.$cls.'"><path stroke-linecap="round" stroke-linejoin="round" d="M13.5 10.5V6.75a4.5 4.5 0 1 1 9 0v3.75M3.75 21.75h10.5a2.25 2.25 0 0 0 2.25-2.25v-6.75a2.25 2.25 0 0 0-2.25-2.25H3.75a2.25 2.25 0 0 0-2.25 2.25v6.75a2.25 2.25 0 0 0 2.25 2.25Z" /></svg>',
        'arrow-uturn-left' => '<svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="'.$cls.'"><path stroke-linecap="round" stroke-linejoin="round" d="M9 15 3 9m0 0 6-6M3 9h12a6 6 0 0 1 0 12h-3" /></svg>',
        'exclamation-circle' => '<svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="'.$cls.'"><path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m9-.75a9 9 0 1 1-18 0 9 9 0 0 1 18 0Zm-9 3.75h.008v.008H12v-.008Z" /></svg>',
        'scale' => '<svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="'.$cls.'"><path stroke-linecap="round" stroke-linejoin="round" d="M12 3v17.25m0 0c-1.472 0-2.882.265-4.185.75M12 20.25c1.472 0 2.882.265 4.185.75M18.75 4.97A48.416 48.416 0 0 0 12 4.5c-2.291 0-4.545.16-6.75.47m13.5 0c1.01.143 2.01.317 3 .52m-3-.52 2.62 10.726c.122.499-.106 1.028-.589 1.202a5.989 5.989 0 0 1-2.031.352 5.989 5.989 0 0 1-2.031-.352c-.483-.174-.711-.703-.59-1.202L18.75 4.971Zm-16.5.52c.99-.203 1.99-.377 3-.52m0 0 2.62 10.726c.122.499-.106 1.028-.589 1.202a5.989 5.989 0 0 1-2.031.352 5.989 5.989 0 0 1-2.031-.352c-.483-.174-.711-.703-.59-1.202L5.25 4.971Z" /></svg>',
        'document-magnifying-glass' => '<svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="'.$cls.'"><path stroke-linecap="round" stroke-linejoin="round" d="M19.5 14.25v-2.625a3.375 3.375 0 0 0-3.375-3.375h-1.5A1.125 1.125 0 0 1 13.5 7.125v-1.5a3.375 3.375 0 0 0-3.375-3.375H8.25m5.231 13.481L15 17.25m-4.5-15H5.625c-.621 0-1.125.504-1.125 1.125v16.5c0 .621.504 1.125 1.125 1.125h12.75c.621 0 1.125-.504 1.125-1.125V11.25a9 9 0 0 0-9-9Zm3.75 11.625a2.625 2.625 0 1 1-5.25 0 2.625 2.625 0 0 1 5.25 0Z" /></svg>',
        'document-check' => '<svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="'.$cls.'"><path stroke-linecap="round" stroke-linejoin="round" d="M19.5 14.25v-2.625a3.375 3.375 0 0 0-3.375-3.375h-1.5A1.125 1.125 0 0 1 13.5 7.125v-1.5a3.375 3.375 0 0 0-3.375-3.375H8.25m3.75 9v6m3-3H9m1.5-12H5.625c-.621 0-1.125.504-1.125 1.125v17.25c0 .621.504 1.125 1.125 1.125h12.75c.621 0 1.125-.504 1.125-1.125V11.25a9 9 0 0 0-9-9Z" /></svg>',
        'document-text' => '<svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="'.$cls.'"><path stroke-linecap="round" stroke-linejoin="round" d="M19.5 14.25v-2.625a3.375 3.375 0 0 0-3.375-3.375h-1.5A1.125 1.125 0 0 1 13.5 7.125v-1.5a3.375 3.375 0 0 0-3.375-3.375H8.25m0 12.75h7.5m-7.5 3H12M10.5 2.25H5.625c-.621 0-1.125.504-1.125 1.125v17.25c0 .621.504 1.125 1.125 1.125h12.75c.621 0 1.125-.504 1.125-1.125V11.25a9 9 0 0 0-9-9Z" /></svg>',
        'user' => '<svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="'.$cls.'"><path stroke-linecap="round" stroke-linejoin="round" d="M15.75 6a3.75 3.75 0 1 1-7.5 0 3.75 3.75 0 0 1 7.5 0ZM4.501 20.118a7.5 7.5 0 0 1 14.998 0A17.933 17.933 0 0 1 12 21.75c-2.676 0-5.216-.584-7.499-1.632Z" /></svg>',
        'star' => '<svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="'.$cls.'"><path stroke-linecap="round" stroke-linejoin="round" d="M11.48 3.499a.562.562 0 0 1 1.04 0l2.125 5.111a.563.563 0 0 0 .475.345l5.518.442c.499.04.701.663.321.988l-4.204 3.602a.563.563 0 0 0-.182.557l1.285 5.385a.562.562 0 0 1-.84.61l-4.725-2.885a.562.562 0 0 0-.586 0L6.982 20.54a.562.562 0 0 1-.84-.61l1.285-5.386a.562.562 0 0 0-.182-.557l-4.204-3.602a.562.562 0 0 1 .321-.988l5.518-.442a.563.563 0 0 0 .475-.345L11.48 3.5Z" /></svg>',
    ];
    return $svgs[$name] ?? $svgs['check-circle'];
}

function notif_icon_svg(string $type): array
{
    $map = [
        'system'              => ['svg' => 'cog-6-tooth',   'color' => 'text-gray-400 dark:text-gray-500',  'bg' => 'bg-gray-100 dark:bg-gray-700/50'],
        'info'                => ['svg' => 'information-circle', 'color' => 'text-blue-500',                  'bg' => 'bg-blue-50 dark:bg-blue-900/20'],
        'success'             => ['svg' => 'check-circle',   'color' => 'text-green-500',                   'bg' => 'bg-green-50 dark:bg-green-900/20'],
        'warning'             => ['svg' => 'exclamation-triangle', 'color' => 'text-amber-500',              'bg' => 'bg-amber-50 dark:bg-amber-900/20'],
        'error'               => ['svg' => 'x-circle',      'color' => 'text-red-500',                     'bg' => 'bg-red-50 dark:bg-red-900/20'],
        'new_message'         => ['svg' => 'chat-bubble-left-ellipsis', 'color' => 'text-indigo-500',        'bg' => 'bg-indigo-50 dark:bg-indigo-900/20'],
        'message_read'        => ['svg' => 'chat-bubble-left', 'color' => 'text-indigo-400',                'bg' => 'bg-indigo-50 dark:bg-indigo-900/20'],
        'project_update'      => ['svg' => 'folder',         'color' => 'text-yellow-500',                  'bg' => 'bg-yellow-50 dark:bg-yellow-900/20'],
        'project_completed'   => ['svg' => 'folder-checked', 'color' => 'text-green-500',                  'bg' => 'bg-green-50 dark:bg-green-900/20'],
        'milestone_completed' => ['svg' => 'flag',           'color' => 'text-emerald-500',                'bg' => 'bg-emerald-50 dark:bg-emerald-900/20'],
        'milestone_approved'  => ['svg' => 'flag',           'color' => 'text-teal-500',                   'bg' => 'bg-teal-50 dark:bg-teal-900/20'],
        'payment_received'    => ['svg' => 'currency-dollar', 'color' => 'text-green-500',                 'bg' => 'bg-green-50 dark:bg-green-900/20'],
        'payment_sent'        => ['svg' => 'banknotes',      'color' => 'text-blue-500',                   'bg' => 'bg-blue-50 dark:bg-blue-900/20'],
        'escrow_funded'       => ['svg' => 'lock-closed',    'color' => 'text-indigo-500',                'bg' => 'bg-indigo-50 dark:bg-indigo-900/20'],
        'escrow_released'     => ['svg' => 'lock-open',      'color' => 'text-green-500',                 'bg' => 'bg-green-50 dark:bg-green-900/20'],
        'refund_processed'    => ['svg' => 'arrow-uturn-left', 'color' => 'text-orange-500',              'bg' => 'bg-orange-50 dark:bg-orange-900/20'],
        'dispute_opened'      => ['svg' => 'exclamation-circle', 'color' => 'text-red-500',               'bg' => 'bg-red-50 dark:bg-red-900/20'],
        'dispute_resolved'    => ['svg' => 'scale',           'color' => 'text-green-500',                'bg' => 'bg-green-50 dark:bg-green-900/20'],
        'dispute_dismissed'   => ['svg' => 'x-mark',          'color' => 'text-gray-400 dark:text-gray-500','bg' => 'bg-gray-100 dark:bg-gray-700/50'],
        'evidence_requested'  => ['svg' => 'document-magnifying-glass', 'color' => 'text-amber-500',      'bg' => 'bg-amber-50 dark:bg-amber-900/20'],
        'evidence_submitted'  => ['svg' => 'document-check',  'color' => 'text-blue-500',                 'bg' => 'bg-blue-50 dark:bg-blue-900/20'],
        'contract_sent'       => ['svg' => 'document-text',   'color' => 'text-purple-500',               'bg' => 'bg-purple-50 dark:bg-purple-900/20'],
        'contract_signed'     => ['svg' => 'document-check',  'color' => 'text-green-500',                'bg' => 'bg-green-50 dark:bg-green-900/20'],
        'withdrawal_completed'=> ['svg' => 'banknotes',       'color' => 'text-green-500',                'bg' => 'bg-green-50 dark:bg-green-900/20'],
        'withdrawal_failed'   => ['svg' => 'x-circle',        'color' => 'text-red-500',                  'bg' => 'bg-red-50 dark:bg-red-900/20'],
        'profile_updated'     => ['svg' => 'user',            'color' => 'text-indigo-500',               'bg' => 'bg-indigo-50 dark:bg-indigo-900/20'],
        'review_received'     => ['svg' => 'star',            'color' => 'text-amber-500',                'bg' => 'bg-amber-50 dark:bg-amber-900/20'],
        'review_replied'      => ['svg' => 'chat-bubble-left-right', 'color' => 'text-teal-500',          'bg' => 'bg-teal-50 dark:bg-teal-900/20'],
    ];
    return $map[$type] ?? $map['system'];
}
?>

<div class="max-w-7xl mx-auto px-4 sm:px-6 py-8">
    <div class="mb-6 flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
        <div class="flex items-center gap-3">
            <h1 class="text-2xl font-bold text-gray-900 dark:text-white">Notifications</h1>
            <?php if ($unreadCount > 0): ?>
                <span class="inline-flex items-center rounded-full bg-indigo-100 px-2.5 py-0.5 text-xs font-medium text-indigo-700 dark:bg-indigo-900/30 dark:text-indigo-400">
                    <?= $unreadCount ?> unread
                </span>
            <?php endif; ?>
        </div>
        <?php if ($unreadCount > 0): ?>
            <button id="markAllReadBtn" onclick="markAllRead()" class="inline-flex items-center gap-1.5 rounded-lg border border-gray-200 bg-white px-3 py-2 text-sm font-medium text-gray-700 shadow-sm transition-colors hover:bg-gray-50 dark:border-gray-600 dark:bg-gray-800 dark:text-gray-300 dark:hover:bg-gray-700">
                <?= heroicon_s('check-circle', 'h-4 w-4') ?>
                Mark all as read
            </button>
        <?php endif; ?>
    </div>

    <?php
    $tabFilters = [
        'all'      => ['label' => 'All',       'count' => $totalCount],
        'unread'   => ['label' => 'Unread',     'count' => $unreadCount],
        'messages' => ['label' => 'Messages',   'count' => $ns->getFilteredCount($userId, 'messages')],
        'disputes' => ['label' => 'Disputes',   'count' => $ns->getFilteredCount($userId, 'disputes')],
    ];
    ?>
    <div class="mb-4 flex gap-1 overflow-x-auto rounded-xl border border-gray-200 bg-white p-1 shadow-sm dark:border-gray-700 dark:bg-gray-800">
        <?php foreach ($tabFilters as $filterKey => $tab): ?>
            <a href="?filter=<?= $filterKey ?><?= $filterKey !== 'unread' ? '&page=1' : '' ?>"
               class="flex items-center gap-1.5 whitespace-nowrap rounded-lg px-3 py-2 text-sm font-medium transition-colors <?= $currentFilter === $filterKey ? 'bg-indigo-600 text-white shadow-sm' : 'text-gray-600 hover:bg-gray-100 dark:text-gray-400 dark:hover:bg-gray-700' ?>">
                <?= $tab['label'] ?>
                <?php if ($tab['count'] > 0): ?>
                    <span class="inline-flex h-5 min-w-[20px] items-center justify-center rounded-full px-1 text-[10px] font-bold <?= $currentFilter === $filterKey ? 'bg-white/20 text-white' : 'bg-gray-100 text-gray-600 dark:bg-gray-700 dark:text-gray-400' ?>">
                        <?= $tab['count'] > 99 ? '99+' : $tab['count'] ?>
                    </span>
                <?php endif; ?>
            </a>
        <?php endforeach; ?>
    </div>

    <?php if (empty($notifications)): ?>
        <div class="rounded-xl border border-gray-200 bg-white p-12 text-center shadow-sm dark:border-gray-700 dark:bg-gray-800">
            <?= heroicon_s('bell-slash', 'mx-auto h-12 w-12 text-gray-300 dark:text-gray-600') ?>
            <p class="mt-3 text-sm font-medium text-gray-500 dark:text-gray-400">No notifications to display.</p>
        </div>
    <?php else: ?>
        <div class="rounded-xl border border-gray-200 bg-white shadow-sm dark:border-gray-700 dark:bg-gray-800">
            <ul id="notif-list" class="divide-y divide-gray-100 dark:divide-gray-700/50">
                <?php foreach ($notifications as $n):
                    $isUnread   = !(bool) $n['is_read'];
                    $iconData   = notif_icon_svg($n['type']);
                    $timeLabel  = notif_time_ago($n['created_at']);
                    $linkTarget = $n['link'] ?? ($basePath . 'dashboard/');
                ?>
                    <li data-notif-id="<?= (int) $n['id'] ?>" data-notif-link="<?= htmlspecialchars($linkTarget) ?>"
                        class="notif-item group relative flex cursor-pointer items-start gap-3 px-4 py-3.5 transition-colors <?= $isUnread ? 'bg-indigo-50/70 hover:bg-indigo-100/70 dark:bg-indigo-900/10 dark:hover:bg-indigo-900/20' : 'bg-white hover:bg-gray-50 dark:bg-gray-800 dark:hover:bg-gray-750' ?>"
                        onclick="handleNotifClick(this)">
                        <?php if ($isUnread): ?>
                            <span class="absolute left-0 top-0 h-full w-1 rounded-l-xl bg-indigo-500"></span>
                        <?php endif; ?>
                        <div class="flex h-10 w-10 flex-shrink-0 items-center justify-center rounded-full <?= $iconData['bg'] ?>">
                            <?= heroicon_s($iconData['svg'], 'h-5 w-5 ' . $iconData['color']) ?>
                        </div>
                        <div class="min-w-0 flex-1 pt-0.5">
                            <div class="flex items-start justify-between gap-2">
                                <p class="text-sm font-semibold <?= $isUnread ? 'text-gray-900 dark:text-white' : 'text-gray-700 dark:text-gray-300' ?>">
                                    <?= htmlspecialchars($n['title']) ?>
                                    <?php if ($isUnread): ?>
                                        <span class="ml-1.5 inline-block h-2 w-2 rounded-full bg-indigo-500 align-middle"></span>
                                    <?php endif; ?>
                                </p>
                                <div class="flex flex-shrink-0 items-center gap-1">
                                    <span class="whitespace-nowrap text-xs text-gray-400 dark:text-gray-500"><?= $timeLabel ?></span>
                                    <div class="relative" x-data="{ open: false }" @click.stop>
                                        <button @click="open = !open" class="rounded-lg p-1 text-gray-400 transition-colors hover:bg-gray-100 hover:text-gray-600 dark:hover:bg-gray-700 dark:hover:text-gray-300">
                                            <?= heroicon_s('ellipsis-vertical', 'h-4 w-4') ?>
                                        </button>
                                        <div x-show="open" @click.outside="open = false"
                                             x-transition:enter="transition ease-out duration-100" x-transition:enter-start="opacity-0 scale-95" x-transition:enter-end="opacity-100 scale-100"
                                             x-transition:leave="transition ease-in duration-75" x-transition:leave-start="opacity-100 scale-100" x-transition:leave-end="opacity-0 scale-95"
                                             class="absolute right-0 z-50 mt-1 w-44 origin-top-right rounded-xl border border-gray-200 bg-white py-1 shadow-lg dark:border-gray-600 dark:bg-gray-800">
                                            <?php if ($isUnread): ?>
                                                <button onclick="markUnread(<?= (int) $n['id'] ?>, false)" class="flex w-full items-center gap-2 px-3 py-2 text-sm text-gray-700 hover:bg-gray-50 dark:text-gray-300 dark:hover:bg-gray-700">
                                                    <?= heroicon_s('check', 'h-4 w-4 text-gray-400') ?>
                                                    Mark as read
                                                </button>
                                            <?php else: ?>
                                                <button onclick="markUnread(<?= (int) $n['id'] ?>, true)" class="flex w-full items-center gap-2 px-3 py-2 text-sm text-gray-700 hover:bg-gray-50 dark:text-gray-300 dark:hover:bg-gray-700">
                                                    <?= heroicon_s('eye-slash', 'h-4 w-4 text-gray-400') ?>
                                                    Mark as unread
                                                </button>
                                            <?php endif; ?>
                                            <button onclick="deleteNotif(<?= (int) $n['id'] ?>)" class="flex w-full items-center gap-2 px-3 py-2 text-sm text-red-600 hover:bg-red-50 dark:text-red-400 dark:hover:bg-red-900/20">
                                                <?= heroicon_s('trash', 'h-4 w-4') ?>
                                                Delete
                                            </button>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <p class="mt-0.5 text-sm text-gray-500 dark:text-gray-400 line-clamp-2"><?= htmlspecialchars($n['message']) ?></p>
                        </div>
                    </li>
                <?php endforeach; ?>
            </ul>
        </div>

        <?php if ($totalPages > 1): ?>
            <nav class="mt-6 flex items-center justify-between">
                <p class="text-sm text-gray-500 dark:text-gray-400">Page <?= $currentPage ?> of <?= $totalPages ?></p>
                <div class="flex gap-2">
                    <?php if ($currentPage > 1): ?>
                        <a href="?filter=<?= $currentFilter ?>&page=<?= $currentPage - 1 ?>" class="rounded-lg border border-gray-300 bg-white px-3 py-2 text-sm font-medium text-gray-700 transition-colors hover:bg-gray-50 dark:border-gray-600 dark:bg-gray-800 dark:text-gray-300 dark:hover:bg-gray-700">Previous</a>
                    <?php endif; ?>
                    <?php if ($currentPage < $totalPages): ?>
                        <a href="?filter=<?= $currentFilter ?>&page=<?= $currentPage + 1 ?>" class="rounded-lg border border-gray-300 bg-white px-3 py-2 text-sm font-medium text-gray-700 transition-colors hover:bg-gray-50 dark:border-gray-600 dark:bg-gray-800 dark:text-gray-300 dark:hover:bg-gray-700">Next</a>
                    <?php endif; ?>
                </div>
            </nav>
        <?php endif; ?>
    <?php endif; ?>
</div>

<script>
const API_BASE = '<?= $basePath ?>shared/notifications_api.php';

function handleNotifClick(el) {
    const id   = el.dataset.notifId;
    const link = el.dataset.notifLink;
    fetch(API_BASE, {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded', 'X-Requested-With': 'XMLHttpRequest' },
        body: 'action=mark_read&id=' + id,
    }).finally(() => { window.location.href = link; });
}

function markUnread(id, makeUnread) {
    const action = makeUnread ? 'mark_unread' : 'mark_read';
    fetch(API_BASE, {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded', 'X-Requested-With': 'XMLHttpRequest' },
        body: 'action=' + action + '&id=' + id,
    }).then(r => r.json()).then(() => { window.location.reload(); });
}

function deleteNotif(id) {
    if (!confirm('Delete this notification?')) return;
    fetch(API_BASE, {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded', 'X-Requested-With': 'XMLHttpRequest' },
        body: 'action=delete&id=' + id,
    }).then(r => r.json()).then(() => { window.location.reload(); });
}

function markAllRead() {
    const btn = document.getElementById('markAllReadBtn');
    if (btn) { btn.disabled = true; btn.textContent = 'Marking...'; }
    fetch(API_BASE, {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded', 'X-Requested-With': 'XMLHttpRequest' },
        body: 'action=mark_all_read',
    }).then(r => r.json()).then(() => { window.location.reload(); });
}
</script>
<script src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js" defer></script>
