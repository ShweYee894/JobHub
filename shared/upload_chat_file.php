<?php
/**
 * Chat File Upload Handler
 * 
 * Handles file attachments for chat messages (PDF, DOCX, ZIP, PNG, JPG).
 * Validates MIME type, file size (max 10MB), sanitizes filenames.
 * Stores files in uploads/chat/ and returns the file path for chat_messages.payload.
 * 
 * POST Parameters: csrf_token, room_id, file
 * Returns: { success: true, file: { name, path, size, type } }
 */
session_start();
header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
    exit;
}

if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

$userId = (int) $_SESSION['user_id'];

// CSRF check
if (!isset($_POST['csrf_token']) || !isset($_SESSION['csrf_token']) ||
    !hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])) {
    http_response_code(403);
    echo json_encode(['error' => 'Invalid CSRF token']);
    exit;
}

// Validate room_id
if (!isset($_POST['room_id']) || !is_numeric($_POST['room_id'])) {
    http_response_code(400);
    echo json_encode(['error' => 'Invalid room_id']);
    exit;
}
$roomId = (int) $_POST['room_id'];

// Check file upload
if (!isset($_FILES['file']) || $_FILES['file']['error'] !== UPLOAD_ERR_OK) {
    http_response_code(400);
    echo json_encode(['error' => 'No file uploaded or upload error']);
    exit;
}

$file = $_FILES['file'];

// Max file size: 10MB
$maxSize = 10 * 1024 * 1024;
if ($file['size'] > $maxSize) {
    http_response_code(400);
    echo json_encode(['error' => 'File too large (max 10MB)']);
    exit;
}

// Allowed MIME types and extensions
$allowedTypes = [
    'application/pdf'                    => 'pdf',
    'application/msword'                 => 'doc',
    'application/vnd.openxmlformats-officedocument.wordprocessingml.document' => 'docx',
    'application/zip'                    => 'zip',
    'application/x-zip-compressed'       => 'zip',
    'image/png'                          => 'png',
    'image/jpeg'                         => 'jpg',
    'image/jpg'                          => 'jpg',
];

// Validate MIME type using finfo (more reliable than $_FILES type)
$finfo = new finfo(FILEINFO_MIME_TYPE);
$mimeType = $finfo->file($file['tmp_name']);

if (!isset($allowedTypes[$mimeType])) {
    http_response_code(400);
    echo json_encode(['error' => 'File type not allowed. Allowed: PDF, DOCX, ZIP, PNG, JPG']);
    exit;
}

$extension = $allowedTypes[$mimeType];

require_once __DIR__ . '/../config/db.php';

// Verify room access
$role = $_SESSION['user_role'] ?? '';
$whereClause = ($role === 'client') ? 'c.client_id' : 'c.freelancer_id';

$stmt = $conn->prepare("
    SELECT cr.id FROM chat_rooms cr
    JOIN contracts c ON cr.contract_id = c.id
    WHERE cr.id = ? AND $whereClause = ?
");
$stmt->bind_param('ii', $roomId, $userId);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    $stmt->close();
    http_response_code(403);
    echo json_encode(['error' => 'Room not found or access denied']);
    exit;
}
$stmt->close();

// Create upload directory
$uploadDir = __DIR__ . '/../assets/upload/chat/';
if (!is_dir($uploadDir)) {
    mkdir($uploadDir, 0755, true);
}

// Generate safe filename
$sanitizedName = preg_replace('/[^a-zA-Z0-9_\-]/', '_', pathinfo($file['name'], PATHINFO_FILENAME));
$filename = $sanitizedName . '_' . bin2hex(random_bytes(8)) . '.' . $extension;
$filepath = $uploadDir . $filename;

// Move uploaded file
if (!move_uploaded_file($file['tmp_name'], $filepath)) {
    http_response_code(500);
    echo json_encode(['error' => 'Failed to save uploaded file']);
    exit;
}

$fileUrl = '/finalproject/assets/upload/chat/' . $filename;

echo json_encode([
    'success' => true,
    'file'    => [
        'name' => htmlspecialchars($file['name'], ENT_QUOTES, 'UTF-8'),
        'path' => $fileUrl,
        'size' => $file['size'],
        'type' => $mimeType
    ]
]);
