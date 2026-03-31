<?php
require_once __DIR__ . '/../db.php';
checkMaintenanceMode();

$mediaId = inputInt('get', 'id');
if ($mediaId === null) {
    sendFileDownloadNotFound();
}

$media = mediaGetById($mediaId);
if ($media === null || !mediaCanPreviewPdf($media)) {
    sendFileDownloadNotFound();
}

$filePath = mediaOriginalPath($media);
if ($filePath === '' || !is_file($filePath)) {
    sendFileDownloadNotFound();
}

if (function_exists('header_remove')) {
    header_remove('X-Frame-Options');
    header_remove('Content-Security-Policy');
}

header('X-Frame-Options: SAMEORIGIN');
header("Content-Security-Policy: default-src 'self'; frame-ancestors 'self'");
header('X-Robots-Tag: noindex, nofollow', true);

sendStoredFileInline($filePath, mediaDownloadName($media), 'application/pdf');
