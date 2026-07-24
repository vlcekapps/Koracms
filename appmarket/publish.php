<?php

require_once __DIR__ . '/../db.php';

$requestMethod = requireHttpMethods(['POST']);
session_write_close();
header_remove('Set-Cookie');

if (!isModuleEnabled('appmarket')) {
    appmarketSendJson(404, ['error' => 'appmarket_disabled']);
}

rateLimit('appmarket_publish', 20, 3600, static function (): void {
    header('Retry-After: 3600');
    appmarketSendJson(429, ['error' => 'rate_limit_exceeded']);
});

$pdo = db_connect();
$tokenValue = appmarketBearerToken();
$token = $tokenValue !== '' ? appmarketAuthenticatePublishToken($pdo, $tokenValue) : null;
if ($token === null) {
    appmarketSendJson(401, ['error' => 'invalid_token']);
}

$app = appmarketFindApp($pdo, (int)$token['app_id']);
if ($app === null) {
    appmarketSendJson(404, ['error' => 'app_not_found']);
}

$metadataJson = trim((string)($_POST['metadata'] ?? ''));
$releaseNotes = (string)($_POST['release_notes'] ?? '');
if (strlen($metadataJson) > appmarketMetadataMaxBytes()
    || !appmarketReleaseNotesValid($releaseNotes)
) {
    appmarketSendJson(422, [
        'error' => 'metadata_too_large',
        'messages' => ['Metadata mohou mít nejvýše 64 KiB a seznam změn nejvýše 50 000 znaků.'],
    ]);
}
$upload = appmarketInspectReleaseUpload(
    is_array($_FILES['apk'] ?? null) ? $_FILES['apk'] : [],
    $metadataJson
);
if (empty($upload['ok'])) {
    appmarketSendJson(422, [
        'error' => 'invalid_release',
        'messages' => $upload['errors'],
    ]);
}

$result = appmarketCreateReleaseDraft($pdo, $app, $upload, $releaseNotes, null);
if (!$result['ok']) {
    appmarketSendJson(422, [
        'error' => 'release_rejected',
        'messages' => $result['errors'],
    ]);
}

$pdo->prepare('UPDATE cms_appmarket_publish_tokens SET last_used_at = NOW() WHERE id = ?')
    ->execute([(int)$token['id']]);
appmarketSendJson(201, [
    'schema_version' => 1,
    'status' => 'draft',
    'release_id' => $result['release_id'],
    'message' => 'Koncept vydání byl přijat a čeká na kontrolu superadminem.',
]);
