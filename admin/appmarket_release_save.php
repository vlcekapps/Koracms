<?php

require_once __DIR__ . '/layout.php';
requireCapability('appmarket_manage', 'Přístup odepřen. Pro správu Appmarketu nemáte potřebné oprávnění.');
requireModuleEnabled('appmarket');
requireHttpMethods(['POST']);
verifyCsrf();

$pdo = db_connect();
$appId = inputInt('post', 'app_id');
$app = $appId !== null ? appmarketFindApp($pdo, $appId) : null;
if ($app === null) {
    http_response_code(404);
    exit('Aplikace nebyla nalezena.');
}

$releaseNotes = (string)($_POST['release_notes'] ?? '');
if (!appmarketReleaseNotesValid($releaseNotes)) {
    $_SESSION['appmarket_release_flash'] = [
        'errors' => ['Seznam změn může mít nejvýše 50 000 znaků.'],
        'release_notes' => $releaseNotes,
        'release_notes_error' => 'Seznam změn může mít nejvýše 50 000 znaků.',
    ];
    header('Location: appmarket_release_form.php?app_id=' . (int)$app['id']);
    exit;
}
$upload = appmarketInspectReleaseUpload(
    $pdo,
    $app,
    is_array($_FILES['release_file'] ?? null) ? $_FILES['release_file'] : [],
    $releaseNotes
);
if (empty($upload['ok'])) {
    $_SESSION['appmarket_release_flash'] = [
        'errors' => $upload['errors'],
        'release_notes' => $releaseNotes,
    ];
    header('Location: appmarket_release_form.php?app_id=' . (int)$app['id']);
    exit;
}

$result = appmarketCreateReleaseDraft($pdo, $app, $upload, $releaseNotes, currentUserId());
if (!$result['ok']) {
    $_SESSION['appmarket_release_flash'] = [
        'errors' => $result['errors'],
        'release_notes' => $releaseNotes,
    ];
    header('Location: appmarket_release_form.php?app_id=' . (int)$app['id']);
    exit;
}

$_SESSION['appmarket_notice'] = 'Koncept vydání byl uložen. Před zveřejněním schvalte jeho podpisový certifikát.';
header('Location: appmarket.php?app_id=' . (int)$app['id']);
exit;
