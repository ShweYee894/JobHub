<?php
/**
 * Auto-Close Expired Jobs
 * 
 * This script closes jobs where:
 * 1. The deadline has passed
 * 2. No activity for the configured number of days (default: 30)
 * 
 * Can be run via:
 * - Cron job: php C:\wamp64\www\jobhub\cron\auto_close_jobs.php
 * - Manual trigger from browse_jobs.php (with rate limiting)
 */

session_start();
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/helpers.php';

// Check if this is a CLI execution or web request
$isCli = php_sapi_name() === 'cli';

if (!$isCli) {
    // Web request - verify CSRF token for security
    if (!isset($_POST['action']) || $_POST['action'] !== 'auto_close_jobs') {
        json_response(['success' => false, 'message' => 'Invalid request.'], 400);
    }
    if (!verify_csrf_token()) {
        json_response(['success' => false, 'message' => 'Invalid CSRF token.'], 403);
    }
}

try {
    $closedCount = run_auto_close_jobs();
    
    if ($isCli) {
        echo "Auto-close completed. Closed {$closedCount} jobs.\n";
    } else {
        json_response([
            'success' => true,
            'message' => "Auto-close completed. Closed {$closedCount} expired jobs.",
            'closed_count' => $closedCount,
        ]);
    }
} catch (Exception $e) {
    if ($isCli) {
        echo "Error: " . $e->getMessage() . "\n";
        exit(1);
    } else {
        json_response(['success' => false, 'message' => 'Failed to run auto-close: ' . $e->getMessage()], 500);
    }
}

$conn->close();
