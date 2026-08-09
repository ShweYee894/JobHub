<?php

/**
 * Security Helper Functions
 * CSRF protection, XSS prevention, input sanitization, and utility functions.
 */

// ── CSRF Token Generation ──────────────────────────────────────────────
function generate_csrf_token(): string
{
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function csrf_field(): string
{
    return '<input type="hidden" name="csrf_token" value="' . htmlspecialchars(generate_csrf_token(), ENT_QUOTES, 'UTF-8') . '">';
}

function verify_csrf_token(): bool
{
    if (empty($_POST['csrf_token']) || empty($_SESSION['csrf_token'])) {
        return false;
    }
    return hash_equals($_SESSION['csrf_token'], $_POST['csrf_token']);
}

// ── Input Sanitization ─────────────────────────────────────────────────
function sanitize_string(string $input): string
{
    return htmlspecialchars(trim($input), ENT_QUOTES, 'UTF-8');
}

function decode_over_encoded(string $str): string
{
    $decoded = html_entity_decode($str, ENT_QUOTES, 'UTF-8');
    $max = 5;
    while ($decoded !== $str && $max-- > 0) {
        $str = $decoded;
        $decoded = html_entity_decode($str, ENT_QUOTES, 'UTF-8');
    }
    return $decoded;
}

function sanitize_email(string $email): string
{
    return filter_var(trim($email), FILTER_SANITIZE_EMAIL);
}

function sanitize_int($input): int
{
    return (int) filter_var($input, FILTER_SANITIZE_NUMBER_INT);
}

function sanitize_float($input): float
{
    return (float) filter_var($input, FILTER_SANITIZE_NUMBER_FLOAT, FILTER_FLAG_ALLOW_FRACTION);
}

// ── Validation Helpers ─────────────────────────────────────────────────
function validate_email(string $email): bool
{
    return filter_var($email, FILTER_VALIDATE_EMAIL) !== false;
}

function validate_password(string $password): array
{
    $errors = [];
    if (strlen($password) < 8)
        $errors[] = 'Password must be at least 8 characters.';
    if (!preg_match('/[A-Z]/', $password))
        $errors[] = 'Password must contain an uppercase letter.';
    if (!preg_match('/[a-z]/', $password))
        $errors[] = 'Password must contain a lowercase letter.';
    if (!preg_match('/[0-9]/', $password))
        $errors[] = 'Password must contain a number.';
    return $errors;
}

// ── Flash Messages ─────────────────────────────────────────────────────
function set_flash(string $type, string $message): void
{
    $_SESSION['flash'][$type] = $message;
}

function get_flash(string $type): ?string
{
    if (isset($_SESSION['flash'][$type])) {
        $message = $_SESSION['flash'][$type];
        unset($_SESSION['flash'][$type]);
        return $message;
    }
    return null;
}

function display_flash(string $type): void
{
    $message = get_flash($type);
    if ($message) {
        $colors = [
            'success' => 'bg-green-50 text-green-800 border-green-200 dark:bg-green-900/20 dark:text-green-400 dark:border-green-800',
            'error' => 'bg-red-50 text-red-800 border-red-200 dark:bg-red-900/20 dark:text-red-400 dark:border-red-800',
            'warning' => 'bg-yellow-50 text-yellow-800 border-yellow-200 dark:bg-yellow-900/20 dark:text-yellow-400 dark:border-yellow-800',
            'info' => 'bg-blue-50 text-blue-800 border-blue-200 dark:bg-blue-900/20 dark:text-blue-400 dark:border-blue-800',
        ];
        $icons = [
            'success' => 'circle-check',
            'error' => 'circle-alert',
            'warning' => 'triangle-alert',
            'info' => 'info',
        ];
        $color = $colors[$type] ?? $colors['info'];
        $icon = $icons[$type] ?? $icons['info'];
        echo '<div class="flex items-center gap-3 p-4 rounded-xl border ' . $color . ' mb-4">';
        echo '<i data-lucide="' . $icon . '"></i>';
        echo '<span class="text-sm font-medium">' . sanitize_string($message) . '</span>';
        echo '</div>';
    }
}

// ── Redirect Helper ────────────────────────────────────────────────────
function redirect(string $url): void
{
    header("Location: $url");
    exit;
}

// ── Time Formatting ────────────────────────────────────────────────────
function time_ago(string $datetime): string
{
    $time = strtotime($datetime);
    $diff = time() - $time;
    if ($diff < 60)
        return 'just now';
    if ($diff < 3600)
        return floor($diff / 60) . ' min ago';
    if ($diff < 86400)
        return floor($diff / 3600) . 'h ago';
    if ($diff < 604800)
        return floor($diff / 86400) . 'd ago';
    return date('M j, Y', $time);
}

// ── Currency Formatting ────────────────────────────────────────────────
function format_currency(float $amount): string
{
    return '$' . number_format($amount, 2);
}

// // ── Profile Image URL ──────────────────────────────────────────────────
function get_profile_image(?string $filename): string
{
    if ($filename) {
        $basename = basename($filename);
        if (file_exists(__DIR__ . '/../assets/upload/profiles/' . $basename)) {
            return '/jobhub/assets/upload/profiles/' . $basename;
        }
    }
    return '/jobhub/assets/upload/profile.png';
}

// ── Truncate Text ──────────────────────────────────────────────────────
function truncate(string $text, int $length = 100): string
{
    if (strlen($text) <= $length)
        return $text;
    return substr($text, 0, $length) . '...';
}

// ── Get IP Address ─────────────────────────────────────────────────────
function get_ip_address(): string
{
    $keys = ['HTTP_CLIENT_IP', 'HTTP_X_FORWARDED_FOR', 'HTTP_X_FORWARDED', 'HTTP_FORWARDED_FOR', 'HTTP_FORWARDED', 'REMOTE_ADDR'];
    foreach ($keys as $key) {
        if (!empty($_SERVER[$key])) {
            $ip = explode(',', $_SERVER[$key])[0];
            return trim($ip);
        }
    }
    return '0.0.0.0';
}

// ── Generate Unique Token ──────────────────────────────────────────────
function generate_token(int $length = 32): string
{
    return bin2hex(random_bytes($length));
}

// ── JSON Response Helper ───────────────────────────────────────────────
function json_response(array $data, int $status_code = 200): void
{
    http_response_code($status_code);
    header('Content-Type: application/json');
    echo json_encode($data);
    exit;
}

// ── Pagination Helper ──────────────────────────────────────────────────
function paginate(int $total_items, int $per_page, int $current_page): array
{
    $total_pages = max(1, ceil($total_items / $per_page));
    $current_page = max(1, min($current_page, $total_pages));
    $offset = ($current_page - 1) * $per_page;
    return [
        'total_items' => $total_items,
        'per_page' => $per_page,
        'current_page' => $current_page,
        'total_pages' => $total_pages,
        'offset' => $offset,
        'has_prev' => $current_page > 1,
        'has_next' => $current_page < $total_pages,
    ];
}

function render_pagination(array $pagination, string $base_url): void
{
    if ($pagination['total_pages'] <= 1)
        return;

    $current = $pagination['current_page'];
    $total   = $pagination['total_pages'];
    $sep     = strpos($base_url, '?') !== false ? '&' : '?';

    echo '<nav class="flex items-center justify-center gap-6 mt-8 mb-4">';

    // ── Page numbers ──────────────────────────────────────────────
    echo '<div class="flex items-center gap-2">';

    // Prev arrow
    if ($pagination['has_prev']) {
        echo '<a href="' . $base_url . $sep . 'page=' . ($current - 1) . '" class="w-9 h-9 rounded-lg flex items-center justify-center text-gray-500 dark:text-slate-400 hover:bg-gray-100 dark:hover:bg-slate-700 transition-colors"><i data-lucide="chevron-left" class="w-4 h-4"></i></a>';
    } else {
        echo '<span class="w-9 h-9 rounded-lg flex items-center justify-center text-gray-300 dark:text-slate-600 cursor-not-allowed"><i data-lucide="chevron-left" class="w-4 h-4"></i></span>';
    }

    // Page number buttons with ellipsis
    $pages = [];
    if ($total <= 7) {
        for ($i = 1; $i <= $total; $i++) $pages[] = $i;
    } else {
        $pages[] = 1;
        if ($current > 3) $pages[] = '...';
        $start = max(2, $current - 1);
        $end   = min($total - 1, $current + 1);
        for ($i = $start; $i <= $end; $i++) $pages[] = $i;
        if ($current < $total - 2) $pages[] = '...';
        $pages[] = $total;
    }

    foreach ($pages as $p) {
        if ($p === '...') {
            echo '<span class="w-9 h-9 flex items-center justify-center text-gray-400 dark:text-slate-500 text-sm">...</span>';
        } else {
            $active = $p === $current
                ? 'bg-gray-900 dark:bg-white text-white dark:text-gray-900 border-gray-900 dark:border-white shadow-md'
                : 'bg-white dark:bg-slate-800 border-gray-200 dark:border-slate-700 text-gray-600 dark:text-slate-300 hover:bg-gray-50 dark:hover:bg-slate-700';
            echo '<a href="' . $base_url . $sep . 'page=' . $p . '" class="w-9 h-9 rounded-lg border text-sm font-semibold flex items-center justify-center transition-all ' . $active . '">' . $p . '</a>';
        }
    }

    // Next arrow
    if ($pagination['has_next']) {
        echo '<a href="' . $base_url . $sep . 'page=' . ($current + 1) . '" class="w-9 h-9 rounded-lg flex items-center justify-center text-gray-500 dark:text-slate-400 hover:bg-gray-100 dark:hover:bg-slate-700 transition-colors"><i data-lucide="chevron-right" class="w-4 h-4"></i></a>';
    } else {
        echo '<span class="w-9 h-9 rounded-lg flex items-center justify-center text-gray-300 dark:text-slate-600 cursor-not-allowed"><i data-lucide="chevron-right" class="w-4 h-4"></i></span>';
    }

    echo '</div>'; // end page numbers

    // ── Go to page input ──────────────────────────────────────────
    echo '<div class="flex items-center gap-2">';
    echo '<span class="text-xs text-gray-400 dark:text-slate-500 font-medium whitespace-nowrap">Go to page</span>';
    echo '<form method="GET" action="" class="flex items-center" onsubmit="return validateGoToPage(this)">';
    // Preserve all current query params except page
    foreach ($_GET as $key => $val) {
        if ($key !== 'page') {
            echo '<input type="hidden" name="' . htmlspecialchars($key) . '" value="' . htmlspecialchars($val) . '">';
        }
    }
    echo '<input type="number" name="page" min="1" max="' . $total . '" value="' . $current . '" class="w-16 h-9 px-2 rounded-lg border border-gray-200 dark:border-slate-600 text-sm text-center font-semibold text-gray-900 dark:text-white bg-white dark:bg-slate-700 focus:outline-none focus:ring-2 focus:ring-blue-500/30 focus:border-blue-400 transition-all">';
    echo '<button type="submit" class="w-9 h-9 rounded-lg bg-gray-900 dark:bg-white text-white dark:text-gray-900 flex items-center justify-center hover:bg-gray-700 dark:hover:bg-gray-200 transition-colors shadow-md"><i data-lucide="chevron-right" class="w-4 h-4"></i></button>';
    echo '</form>';
    echo '</div>'; // end go to page

    echo '</nav>'; // end nav

    // ── Inline JS for go-to-page validation ───────────────────────
    echo '<script>function validateGoToPage(f){var v=parseInt(f.page.value);if(isNaN(v)||v<1){f.page.value=1;}if(v>' . $total . '){f.page.value=' . $total . ';}return true;}</script>';
}

/**
 * Get unread message count for a user.
 * Works for both clients and freelancers.
 */
function get_unread_message_count(int $userId, string $role): int
{
    global $conn;
    if ($role === 'freelancer') {
        $stmt = $conn->prepare('SELECT COUNT(*) AS cnt FROM chat_messages cm JOIN chat_rooms cr ON cm.room_id = cr.id JOIN contracts c ON cr.contract_id = c.id WHERE c.freelancer_id = ? AND cm.sender_id != ? AND cm.is_read = 0');
        $stmt->bind_param('ii', $userId, $userId);
    } else {
        $stmt = $conn->prepare('SELECT COUNT(*) AS cnt FROM chat_messages cm JOIN chat_rooms cr ON cm.room_id = cr.id JOIN contracts c ON cr.contract_id = c.id WHERE c.client_id = ? AND cm.sender_id != ? AND cm.is_read = 0');
        $stmt->bind_param('ii', $userId, $userId);
    }
    $stmt->execute();
    $count = (int) $stmt->get_result()->fetch_assoc()['cnt'];
    $stmt->close();
    return $count;
}

/**
 * Auto-close expired jobs based on platform settings.
 * Closes jobs where deadline has passed AND no activity for configured days.
 */
function run_auto_close_jobs(): int
{
    global $conn;

    $settingsFile = __DIR__ . '/platform_settings.json';
    $settings = [];
    if (file_exists($settingsFile)) {
        $settings = json_decode(file_get_contents($settingsFile), true) ?? [];
    }
    $autoCloseDays = (int) ($settings['auto_close_jobs_days'] ?? 30);

    $cutoffDate = date('Y-m-d', strtotime("-{$autoCloseDays} days"));

    $stmt = $conn->prepare('
        UPDATE jobs 
        SET status = "closed", updated_at = NOW() 
        WHERE status = "open" 
        AND deadline IS NOT NULL 
        AND deadline < CURDATE()
        AND updated_at < ?
    ');
    $stmt->bind_param('s', $cutoffDate);
    $stmt->execute();
    $closedCount = $stmt->affected_rows;
    $stmt->close();

    return $closedCount;
}
