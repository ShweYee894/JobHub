<?php
/**
 * Database Connection & Configuration
 * Uses MySQLi with error reporting for development.
 */

$servername = 'localhost';
$username   = 'root';
$password   = '';
$dbname     = 'freelancer_platform_db';

// Create connection
$conn = new mysqli($servername, $username, $password, $dbname);

// Check connection
if ($conn->connect_error) {
    error_log('[JobHub] Database connection failed: ' . $conn->connect_error);
    http_response_code(503);
    die('Service temporarily unavailable. Please try again later.');
}

// Set charset to utf8mb4
$conn->set_charset('utf8mb4');

// Start session if not already started and not explicitly closed
if (session_status() === PHP_SESSION_NONE && !defined('SESSION_CLOSED')) {
    session_start();
}

// Include helpers
require_once __DIR__ . '/helpers.php';
