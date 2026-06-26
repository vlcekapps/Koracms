<?php

require_once __DIR__ . '/../db.php';
requireCapability('content_manage_shared', 'Přístup odepřen.');
$isHeadRequest = requireReadOnlyHttpMethod();

$submissionId = inputInt('get', 'id');
$fieldName = trim((string)($_GET['field'] ?? ''));
$fileIndex = max(0, (int)($_GET['index'] ?? 0));

if ($submissionId === null || $fieldName === '') {
    sendFileDownloadNotFound('Příloha nebyla nalezena.', $isHeadRequest);
}

$pdo = db_connect();
$submissionStmt = $pdo->prepare("SELECT data FROM cms_form_submissions WHERE id = ?");
$submissionStmt->execute([$submissionId]);
$submission = $submissionStmt->fetch() ?: null;

if (!$submission) {
    sendFileDownloadNotFound('Příloha nebyla nalezena.', $isHeadRequest);
}

$submissionData = json_decode((string)($submission['data'] ?? ''), true) ?: [];
$fileItems = formSubmissionFileItems($submissionData[$fieldName] ?? null);
$fileItem = $fileItems[$fileIndex] ?? null;

if (!is_array($fileItem)) {
    sendFileDownloadNotFound('Příloha nebyla nalezena.', $isHeadRequest);
}

$storedName = formSubmissionStoredFileName($fileItem);
$filePath = formUploadFilePath($storedName);
if ($filePath === '' || !is_file($filePath) || !is_readable($filePath)) {
    sendFileDownloadNotFound('Příloha nebyla nalezena.', $isHeadRequest);
}

$originalName = safeDownloadName((string)($fileItem['original_name'] ?? ''), basename($storedName));

$mimeType = storedFileMimeType($filePath, (string)($fileItem['mime_type'] ?? ''));

$fileSize = filesize($filePath);
if (!is_int($fileSize)) {
    storedFileLogFailure('filesize', $filePath, ['endpoint' => 'admin/form_submission_file.php']);
    sendFileDownloadNotFound('Příloha nebyla nalezena.', $isHeadRequest);
}

$handle = fopen($filePath, 'rb');
if ($handle === false) {
    storedFileLogFailure('fopen', $filePath, ['endpoint' => 'admin/form_submission_file.php']);
    sendFileDownloadNotFound('Příloha nebyla nalezena.', $isHeadRequest);
}

sendAdminAttachmentHeaders($mimeType, $originalName, $fileSize);

if ($isHeadRequest) {
    fclose($handle);
    exit;
}

fpassthru($handle);
fclose($handle);
exit;
