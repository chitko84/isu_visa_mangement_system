<?php
require_once __DIR__ . '/includes/functions.php';
secure_session_start();

if (!is_logged_in()) {
    http_response_code(403);
    exit('Please log in to access this file.');
}

$id = (int)($_GET['id'] ?? 0);
if ($id <= 0) {
    http_response_code(400);
    exit('Invalid file request.');
}

$stmt = $conn->prepare("
    SELECT d.document_id, d.document_type, d.document_path, a.student_id
    FROM visa_document d
    JOIN visa_renewal_application a ON a.application_id = d.application_id
    WHERE d.document_id = ?
    LIMIT 1
");
$stmt->bind_param("i", $id);
$stmt->execute();
$doc = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$doc) {
    http_response_code(404);
    exit('File not found.');
}

$role = normalize_role(current_role());
$studentOwns = $role === 'student' && (int)$_SESSION['user_id'] === (int)$doc['student_id'];
$staffCanReview = user_has_role(['staff', 'admin', 'super_admin', 'visa_officer']);
if (!$studentOwns && !$staffCanReview) {
    http_response_code(403);
    exit('You do not have permission to access this file.');
}

$path = str_replace('\\', '/', (string)$doc['document_path']);
if (str_starts_with($path, '../')) {
    $path = substr($path, 3);
}
$path = ltrim($path, '/');
$baseDir = realpath(__DIR__ . '/uploads/visa_documents');
$fullPath = realpath(__DIR__ . '/' . $path);

if (!$baseDir || !$fullPath || !str_starts_with($fullPath, $baseDir) || !is_file($fullPath)) {
    http_response_code(404);
    exit('File is unavailable.');
}

log_audit($conn, 'downloaded_document', 'visa_document', (int)$doc['document_id'], 'Downloaded/reviewed sensitive document.');

$mime = mime_content_type($fullPath) ?: 'application/octet-stream';
$filename = basename($fullPath);
header('Content-Type: ' . $mime);
header('Content-Disposition: inline; filename="' . str_replace('"', '', $filename) . '"');
header('Content-Length: ' . filesize($fullPath));
header('X-Content-Type-Options: nosniff');
readfile($fullPath);
exit;
