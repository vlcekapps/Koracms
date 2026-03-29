<?php
require_once __DIR__ . '/../db.php';
requireCapability('content_manage_shared', 'Přístup odepřen.');

$submissionId = inputInt('get', 'id');
$fieldName = trim((string)($_GET['field'] ?? ''));
$fileIndex = max(0, (int)($_GET['index'] ?? 0));

if ($submissionId === null || $fieldName === '') {
    http_response_code(404);
    exit;
}

$pdo = db_connect();
$submissionStmt = $pdo->prepare("SELECT data FROM cms_form_submissions WHERE id = ?");
$submissionStmt->execute([$submissionId]);
$submission = $submissionStmt->fetch() ?: null;

if (!$submission) {
    http_response_code(404);
    exit;
}

$submissionData = json_decode((string)($submission['data'] ?? ''), true) ?: [];
$fileItems = formSubmissionFileItems($submissionData[$fieldName] ?? null);
$fileItem = $fileItems[$fileIndex] ?? null;

if (!is_array($fileItem)) {
    http_response_code(404);
    exit;
}

$storedName = formSubmissionStoredFileName($fileItem);
$filePath = formUploadFilePath($storedName);
if ($filePath === '' || !is_file($filePath)) {
    http_response_code(404);
    exit;
}

$originalName = trim((string)($fileItem['original_name'] ?? ''));
if ($originalName === '') {
    $originalName = basename($storedName);
}

$mimeType = trim((string)($fileItem['mime_type'] ?? ''));
if ($mimeType === '') {
    $finfo = new finfo(FILEINFO_MIME_TYPE);
    $mimeType = (string)$finfo->file($filePath);
}
if ($mimeType === '') {
    $mimeType = 'application/octet-stream';
}

$fileSize = filesize($filePath);
$asciiName = preg_replace('/[^A-Za-z0-9._-]+/', '_', $originalName);
if (!is_string($asciiName) || $asciiName === '') {
    $asciiName = 'priloha';
}

header('Content-Type: ' . $mimeType);
if ($fileSize !== false) {
    header('Content-Length: ' . (string)$fileSize);
}
header('Content-Disposition: attachment; filename="' . $asciiName . '"; filename*=UTF-8\'\'' . rawurlencode($originalName));

$handle = fopen($filePath, 'rb');
if ($handle === false) {
    http_response_code(404);
    exit;
}

fpassthru($handle);
fclose($handle);
exit;
