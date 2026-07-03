<?php

require_once __DIR__ . '/../db.php';
checkMaintenanceMode();
sendNoStoreNoIndexHeaders();

$requestMethod = requireHttpMethods(['GET', 'HEAD']);

if (!isModuleEnabled('reservations')) {
    sendReadOnlyNotFoundResponse('Kalendářová pozvánka nebyla nalezena.', $requestMethod === 'HEAD');
}

$token = trim((string)($_GET['token'] ?? ''));
if ($token === '' || preg_match('/^[a-f0-9]{32}$/', $token) !== 1) {
    sendReadOnlyNotFoundResponse('Kalendářová pozvánka nebyla nalezena.', $requestMethod === 'HEAD');
}

$booking = reservationBookingForCalendarToken(db_connect(), $token);
if ($booking === null) {
    sendReadOnlyNotFoundResponse('Kalendářová pozvánka nebyla nalezena.', $requestMethod === 'HEAD');
}

$ics = reservationBuildIcs($booking);
$downloadName = safeDownloadName(reservationIcsFilename($booking), 'rezervace.ics');

sendContentTypeNoSniffHeaders(
    'text/calendar; charset=UTF-8; method=PUBLISH',
    storedFileContentDisposition('attachment', $downloadName),
    'noindex, nofollow, noarchive'
);
header('Content-Length: ' . strlen($ics));

if ($requestMethod !== 'HEAD') {
    echo $ics;
}
