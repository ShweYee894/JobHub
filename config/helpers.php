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
            'success' => 'bg-green-50 text-green-800 border-green-200',
            'error' => 'bg-red-50 text-red-800 border-red-200',
            'warning' => 'bg-yellow-50 text-yellow-800 border-yellow-200',
            'info' => 'bg-blue-50 text-blue-800 border-blue-200',
        ];
        $icons = [
            'success' => 'fa-check-circle',
            'error' => 'fa-exclamation-circle',
            'warning' => 'fa-exclamation-triangle',
            'info' => 'fa-info-circle',
        ];
        $color = $colors[$type] ?? $colors['info'];
        $icon = $icons[$type] ?? $icons['info'];
        echo '<div class="flex items-center gap-3 p-4 rounded-xl border ' . $color . ' mb-4">';
        echo '<i class="fas ' . $icon . '"></i>';
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
            return '/finalproject/assets/upload/profiles/' . $basename;
        }
    }
    return '/finalproject/assets/upload/profile.png';
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
    echo '<nav class="flex items-center justify-center gap-2 mt-8">';
    if ($pagination['has_prev']) {
        echo '<a href="' . $base_url . '&page=' . ($pagination['current_page'] - 1) . '" class="px-3 py-2 rounded-lg bg-white border border-gray-200 text-sm font-medium text-gray-600 hover:bg-gray-50">Prev</a>';
    }
    for ($i = 1; $i <= $pagination['total_pages']; $i++) {
        $active = $i === $pagination['current_page'] ? 'bg-blue-600 text-white border-blue-600' : 'bg-white border-gray-200 text-gray-600 hover:bg-gray-50';
        echo '<a href="' . $base_url . '&page=' . $i . '" class="px-3 py-2 rounded-lg border text-sm font-medium ' . $active . '">' . $i . '</a>';
    }
    if ($pagination['has_next']) {
        echo '<a href="' . $base_url . '&page=' . ($pagination['current_page'] + 1) . '" class="px-3 py-2 rounded-lg bg-white border border-gray-200 text-sm font-medium text-gray-600 hover:bg-gray-50">Next</a>';
    }
    echo '</nav>';
}
